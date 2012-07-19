<?php
/*
 * 
 * Copyright (c) 2012, E.K. van Dalen
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without 
 * modification, are permitted provided that the following conditions 
 * are met:
 * 
 * Redistributions of source code must retain the above copyright 
 * notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright 
 * notice, this list of conditions and the following disclaimer in the 
 * documentation and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS 
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT 
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS 
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE 
 * COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, 
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; 
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER 
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT 
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN 
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE 
 * POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * FatClientFramework (FCF) redbean extensions 
 * for applying a permissions layer with bean can server
 */
require_once(FCF_SYS . "/ext/redbean/rb.php");
class FCF_RedBean_BeanCan extends RedBean_BeanCan{
	protected function encodeResponse($response){
		return $response;
	}
	private function loadRelatedBeans($data){
		foreach($data as $k => $v){
			if(substr($k, 0, 3)=='own'||substr($k,0,6)=='shared'){
				$beanType = strtolower((substr($k,0,3)=='own') ? substr($k,3) : substr($k,6));
				if(is_array($v)){
					$repl = array();
					foreach($v as $bid){
						if(!(is_numeric($bid))) throw new Exception("related " . $beanType . " should be denoted by integer to be interpreted as id");
						$repl[] = RedBean_Facade::load($beanType, $bid);
					}
					$v = $repl;
				}else{
					if(!(is_numeric($v))) throw new Exception("related " . $beanType . " should be denoted by integer to be interpreted as id");
					$v = RedBean_Facade::load($beanType, $v);
				}
				$data[$k] = $v;
			}
		}
		return $data;
	}
	public function handleJSONRequest( $jsonString ) {
		$jsonArray = json_decode($jsonString,true);
		if (!$jsonArray) return $this->resp(null,null,-32700,'Cannot Parse JSON');
		if (!isset($jsonArray['jsonrpc'])) return $this->resp(null,null,-32600,'No RPC version');
		if (($jsonArray['jsonrpc']!='2.0')) return $this->resp(null,null,-32600,'Incompatible RPC Version');
		if (!isset($jsonArray['id'])) return $this->resp(null,null,-32600,'No ID');
		$id = $jsonArray['id'];
		if (!isset($jsonArray['method'])) return $this->resp(null,$id,-32600,'No method');
		if (!isset($jsonArray['params'])) {
			$data = array();
		}
		else {
			$data = $jsonArray['params'];
		}
		$method = explode(':',trim($jsonArray['method']));
		if (count($method)!=2) {
			return $this->resp(null, $id, -32600,'Invalid method signature. Use: BEAN:ACTION');
		}
		$beanType = $method[0];
		$action = $method[1];
		if (preg_match('/\W/',$beanType)) return $this->resp(null, $id, -32600,'Invalid Bean Type String');
		if (preg_match('/\W/',$action)) return $this->resp(null, $id, -32600,'Invalid Action String');
		try {
			switch($action) {
				case 'store':
					if (!isset($data[0])) return $this->resp(null, $id, -32602,'First param needs to be Bean Object');
					$data = $data[0];
					if (!isset($data['id'])) $bean = RedBean_Facade::dispense($beanType); else
						$bean = RedBean_Facade::load($beanType,$data['id']);
                                        $data = $this->loadRelatedBeans($data);
                                        $bean->import( $data );

					$rid = RedBean_Facade::store($bean);
					return $this->resp($rid, $id);
				case 'load':
					if (!isset($data[0])) return $this->resp(null, $id, -32602,'First param needs to be Bean ID');
					$bean = RedBean_Facade::load($beanType,$data[0]);
					return $this->resp($bean->export(),$id);
				case 'trash':
					if (!isset($data[0])) return $this->resp(null, $id, -32602,'First param needs to be Bean ID');
					$bean = RedBean_Facade::load($beanType,$data[0]);
					RedBean_Facade::trash($bean);
					return $this->resp('OK',$id);
				default:
					$modelName = $this->modelHelper->getModelName( $beanType );
					if (!class_exists($modelName)) return $this->resp(null, $id, -32601,'No such bean in the can!');
					$beanModel = new $modelName;
					if (!method_exists($beanModel,$action)) return $this->resp(null, $id, -32601,"Method not found in Bean: $beanType ");
					return $this->resp( call_user_func_array(array($beanModel,$action), $data), $id);
			}
		}
		catch(Exception $exception) {
			return $this->resp(null, $id, -32099,$exception->getCode()."-".$exception->getMessage());
		}
	}
}
class FatClientFramework
{
	public static $model_generator;
        public static function init(){
		// initialize session, setup redbean
                $config = SimpleConfig::getInstance();
		session_start();
		R::setup('mysql:host='.$config->dbHost.';dbname='.$config->dbName,$config->dbUser,$config->dbPass);
		// subscribe the model generator to before_dispense events
                self::$model_generator = new FCF_Model_Generator();
                R::$redbean->addEventListener('before_dispense',self::$model_generator);
                // load the framework model files
                foreach (glob(FCF_SYS . "/models/*.php") as $filename)
                {
                    require_once $filename;
                }
                // load the application model files
                foreach (glob(FCF_APP . "/models/*.php") as $filename)
                {
                    require_once $filename;
                }
                // if this is first use, the framework role model will create the admin
                if(R::count('role') == 0){
			R::store(R::dispense('role'));
		}
	}
}
class FCF_Transaction {
    private $trId;
    private $commArr;
    function __construct($tr_array){
		$this->trId = null;
		$this->commArr = array();
		foreach($tr_array as $k => $v){
			if($k == "tr"){
				$this->trId = $v;
			}elseif(substr($k,0,4) == "comm"){
				$this->commArr[(int)substr($k,4)] = $v;
			}
		}
	}
    function exec(){
        $can = new FCF_RedBean_BeanCan();
        R::begin();
        $r = null; // response
        try{ foreach($this->commArr as $c){
                $r = $can->handleJSONRequest($c);
                if(array_key_exists("error", $r)) 
                        throw new Exception();
            } R::commit();
        }catch(Exception $e) { R::rollback(); }
        R::close();
        echo  '{"' . $this->trId . '":' . json_encode($r) . "}";
		return;
    }
}
class FCF_SysCommand {
    private $comm;
    private $params;
    
    public static $SYSCOMM_NUKE = "nuke";
    public static $SYSCOMM_INSTALL = "install";
    
    function __construct($commandString, $commandParams){
        $this->comm = $commandString;
        $this->params = $commandParams;
    }
    function run(){
        $rsp = new stdClass();
        $rsp->id = $_POST["sys"];
        if($this->comm == self::$SYSCOMM_NUKE){
            R::nuke();  
        }elseif($this->comm == self::$SYSCOMM_INSTALL){
            // this is automated by setting red bean
        }
        return json_encode($rsp);
    }
}
class FCF_Exception extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
class FCF_Permission {
    public static $QUERY_TYPE_READ = "r";
    public static $QUERY_TYPE_WRITE = "w";
    public static $QUERY_TYPE_DELETE = "d";
    /**
     *
     * @var selfEnum
     */
    public $allowForQueryType;
    /**
     * 
     * @var int 
     */
    public $code;
    /**
     * 
     * @var redBeanBean 
     */
    public $allowForBean;
    
    /**
     *
     * @var assocArr
     */
    public $allowForKeyVal;
    public function __construct($allowForQueryType, $code, $allowForBean = null, $allowForKeyVal = null){
        $this->allowForQueryType = $allowForQueryType;
        $this->code = $code;
        $this->allowForBean = $allowForBean;
        $this->allowForKeyVal = $allowForKeyVal;
    }
}
class FCF_RedBean_SimpleModel extends RedBean_SimpleModel{
    protected function success(){
        $ret = new stdClass();
        $ret->success = 1;
        return $ret;
    }
    private static $permissions = array();
    public static function addPermission($permission){
        self::$permissions[] = $permission;
    }
    protected static function permit($permissionType, $beanInstance){
        // we can check each permission that the class has collected
        // where a permission for this instance and type (r,w,d)
        // can be based on the exact equivalence (same object reference)
        // and/or on the equivalence of a property value
        $i = 0;
        while($i<count(self::$permissions)){
            $permission = self::$permissions[$i];
            $thisIndex = $i;
            $i++;
            if(!($permission->allowForQueryType == $permissionType)) continue;
            if($permission->allowForBean != null){
                if($permission->allowForBean === $beanInstance){
                    array_splice(self::$permissions, $thisIndex, 1);
                    return true;
                }else if(is_string($permission->allowForBean)){
                    if($beanInstance == $permission->allowForBean){
                        array_splice(self::$permissions, $thisIndex, 1);
                        return true;
                    }
                }
            }
            if($permission->allowForKeyVal != null){
                $instProps = $beanInstance->getProperties();
                $key = key($permission->allowForKeyVal);
                $val = $permission->allowForKeyVal[$key];
                if(array_key_exists($key, $instProps)){
                    if($instProps[$key] == $val){
                        array_splice(self::$permissions, $thisIndex, 1);
                        return true;
                    }
                }
            }
        }
        return false;
    }
    private static function doesFieldExceptionApply($columnName, $exceptions){
        $makeException = false;
        if(!(array_key_exists($columnName, $exceptions))) return $makeException;
        $exceptionCriterium = $exceptions[$columnName];
        // we support this to be a boolean or a string designating a static function
        if(is_bool($exceptionCriterium)) return $exceptionCriterium;
        else if (!(is_string($exceptionCriterium))) return $makeException;
        // else:
        $classDesignationXPL = explode ("::", $exceptionCriterium);
        if(count($classDesignationXPL) == 1){
            $classDesignation = "self"; // assume self
            $methodDesignation = $exceptionCriterium;
        }else{
            $classDesignation = $classDesignationXPL[0];
            $methodDesignation = $classDesignationXPL[1];
        }
        if($classDesignation == "self"){
            $calledClass = get_called_class();
            $methodExists = method_exists($calledClass, $methodDesignation);
            if(!($methodExists)) throw new Exception("method " . $methodDesignation . " does not exist on class " . get_called_class());
            $makeException = call_user_func(array($calledClass, $methodDesignation));
            if(!(is_bool($makeException))){
                throw new Exception("not returning bool: " . $exceptionCriterium);
            }
        }else{
            $methodExists = method_exists($classDesignation, $methodDesignation);
            if(!($methodExists)) throw new Exception("method " . $methodDesignation . " does not exist on class " . $classDesignation);
            $exceptionCriterium = $classDesignation . "::" . $methodDesignation;
            $makeException = call_user_func($exceptionCriterium);
            if(!(is_bool($makeException))){
                throw new Exception("not returning bool: " . $exceptionCriterium);
            }
        }
        return $makeException;
    }
    // model specific find-function to facilitate selection thru bean can
    public static function find($getFieldsForNames = array(),$whereKeyIsValue = array()){
        // do we have a where clause?
        if(!(is_array($whereKeyIsValue))) throw new Exception("find exception 1");
        if(!(is_array($getFieldsForNames))) throw new Exception("find exception 2");
        if(count($getFieldsForNames) == 0)
            if(!(static::$findFieldsAllowReturnAllFields))
                throw new Exception("obligation to specify fields");
        if(count($whereKeyIsValue) == 0)
            if(!static::$findFieldsAllowSelectAllRecords)
                throw new Exception("where clause is obligatory");
        $selKeys = "";
        $selVals = array();
        $beanClassID = strtolower(substr(get_called_class(),6));
        $generallyAllowed = static::$findFieldsGenerallySearchable;
        foreach($whereKeyIsValue as $item){
            $k = key($item);
            $v = $item[$k];
            $allowThisField = $generallyAllowed;
            $makeException = self::doesFieldExceptionApply($k, static::$findFieldsSearchableExceptions);
            if($makeException == true) $allowThisField = !$generallyAllowed;
            if(!$allowThisField) throw new Exception("field not allowed for search: " . $k);
            else{
                $selKeys .= (strlen($selKeys) > 0) ? ' AND ' . $k . '= ?' : $k . '= ?';
                $selVals[] = $v;
            }
        }
        $unfilteredResults = R::find($beanClassID,$selKeys,$selVals);
        // now we have a result containing all fields. We will filter out the requested ones.
        // are all of the requested result fields allowed?
        $generallyAllowed = static::$findFieldsGenerallyFindable;
        if(count($getFieldsForNames) > 0){
            foreach($getFieldsForNames as $k){
                $allowThisField = $generallyAllowed;
                $makeException = self::doesFieldExceptionApply($k, static::$findFieldsFindableExceptions);
                if($makeException == true) $allowThisField = !$generallyAllowed;
                if(!$allowThisField) throw new Exception("field not allowed for return: " . $k);
            }
        }
        // after this check we're sure that if we have fields in $getFieldsForNames, they are permitted
        $filteredResults = array();
        foreach($unfilteredResults as $result){
            $resultProps = $result->getProperties();
            if(count($getFieldsForNames) > 0){
                $selectedProperties = array();
                foreach($getFieldsForNames as $v){
                    if(!(array_key_exists($v, $resultProps)))
                            throw new Exception("no such property in " . $beanClassID . ": " . $v);
                    $selectedProperties[$v] = $resultProps[$v];
                }
                $filteredResults[] = $selectedProperties;
            }else{
                $filteredResults[] = $resultProps;
            }
        }
        return $filteredResults;
    }
    protected static $findFieldsAllowReturnAllFields = false;
    protected static $findFieldsAllowSelectAllRecords = false;
    protected static $findFieldsGenerallyFindable = false;
    protected static $findFieldsFindableExceptions = array();
    protected static $findFieldsGenerallySearchable = false;
    protected static $findFieldsSearchableExceptions = array();
    // you can go to the Model_Role to see how this functionality can be used
    
    // check whether there is only one bean
    /**
     * will return the one bean from the array
     * and throw an exception containing error message when
     * the array is empty or contains more than one bean
     * 
     * @param type $beansArr
     * @param type $errorString
     * @return type 
     */
    protected static function allowOne($beansArr,$errMessage){
        $count = count($beansArr);
        if($count == 0) if($errMessage != null) throw new Exception("no result: " . $errMessage); else return false;
        if($count > 1) if($errMessage != null) throw new Exception("more than one bean: " . $errMessage);
        $key = key($beansArr);
        return $beansArr[$key];
    }
}
class MM extends FCF_RedBean_SimpleModel{
}
class FCF_Model_Generator implements RedBean_Observer{
    public function onEvent($eventname, $bean){
        $modelName = RedBean_ModelHelper::getModelName($bean);
	if (class_exists($modelName)) return;
        $boe = "bag";
    }
}
class FCF_Tools {
    public static function randStr($length){
        $ret = "";
        $randpick = "1234567890qwertyuiopasdfghjklzxcvbnm";
    	while (strlen($ret) < $length){
    		$ret .= $randpick[rand(0, strlen($randpick)-1)];
        }
        return $ret;
    }
}
?>