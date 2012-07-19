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
 * FatClientFramework (FCF) on board models 
 * containing mainly permissions related functionality
 */
class Model_Session extends FCF_RedBean_SimpleModel {   
    public static function fakeReturnTrueIfAdmin(){
        return true;
    }
    public static function hasLoggedInUserForRoles($roleIds){
        $userRoles = self::findRolesForLoggedInUser();
        // verify for each role if user has them
        foreach($roleIds as $roleId){
            if(!(array_key_exists($roleId, $userRoles))) return false;
        }return true;
    }
    public static function getLoggedInUser(){
        $thisSS = MM::allowOne(R::find('session',' ssid = ?',array(session_id())),null);
        $user;
        if($thisSS) $user = R::relatedOne($thisSS, 'user'); else return false;
        if(!$user) return false;
        return $user;
    }
    public static function findRolesForLoggedInUser(){
        // get this sessions user
        $thisSS = MM::allowOne(R::find('session',' ssid = ?',array(session_id())),null);
        $user;
        if($thisSS) $user = R::relatedOne($thisSS, 'user'); else return false;
        // get his roles
        if(!$user) return false;
        if(is_a($user,"RedBean_OODBBean")) $roles = R::related($user,'role');
        else $roles = R::related($user->bean,'role');
        return $roles;
    }
    public static function hasLoggedInUserForOneOfRoles($roleIds){
        $userRoles = self::findRolesForLoggedInUser();
        if(!$userRoles) return false;
        // verify for each role if user has them
        $hasOneOfRoles = false;
        foreach($roleIds as $roleId){
            if(array_key_exists($roleId, $userRoles)) $hasOneOfRoles = true;
        }return $hasOneOfRoles;
    }
    
    public function generateChallenge(){
    	$ret = FCF_Tools::randStr(32);
    	$ssid = session_id();
        $rr = R::findOrDispense('session',' ssid = :ssid', array( ':ssid'=>$ssid ));
        $sess = $rr[key($rr)];
    	$sess->chall = $ret;
    	$sess->challTime = time();
        $sess->ssid = $ssid;
        self::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1328398090, $sess));
    	R::store($sess);
    	return $ret;
    }
    public function submitResponse($loginName,$response){
        $ssid = session_id();
    	$sess = R::find('session',' ssid = :ssid', array( ':ssid'=>$ssid ));
        if(count($sess) == 0) throw new FCF_Exception("auth fail 1");
        if(count($sess) > 1 ) throw new FCF_Exception("auth fail 2");
        $sess = $sess[key($sess)];
        if(time() - $sess->challTime > 60) throw new FCF_Exception("auth fail 3");
        Model_User::addPermission( new FCF_Permission( FCF_Permission::$QUERY_TYPE_READ, 1328396966, null, array("loginName" => $loginName)));
        $u = R::find('user', ' loginName = :un', array( ':un'=>$loginName));
        if(count($u) == 0) throw new FCF_Exception("auth fail 4");
        if(count($u) > 1 ) throw new FCF_Exception("auth fail 5");
        $u = $u[key($u)];
        $psw = $u->password;
        $chall = $sess->chall;
        $hash = hash('sha512', $chall . $psw);
        if($hash != $response) throw new FCF_Exception("auth fail 6");
        self::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1328397884, $sess));
        R::associate($u, $sess);
        $sess->chall = null;
        R::store($sess);
        $_SESSION['authUser'] = "y";
        $ret = new stdClass();
        $ret->userId = $u->id;
        $ret->userScreenName = $u->screenName;
        $ret->userRoles = array();
        $rolesArr = $u->sharedRole;
        foreach($rolesArr as $id => $roleBean){
            $ret->userRoles[$id] = $roleBean->name;
        }
        return $ret;
    }
    public function destroy(){
        
        $thisSS = MM::allowOne(R::find('session',' ssid = ?',array(session_id())),null);
        if($thisSS){
            $thisSS->ssid = null;
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, "1329562691", $thisSS));
            R::store($thisSS);
        }
        session_destroy();
        $ret = new stdClass();
        $ret->success = "great";
    	return $ret;
    }
    public function getUser(){
        $thisSS = MM::allowOne(R::find('session',' ssid = ?',array(session_id())),null);
        $user;
        if($thisSS) $user = R::relatedOne($thisSS, 'user');
        else{
            $ret = new stdClass();
            $ret->id = -1;
            return $ret;
        }
        if(!$user){
            $ret = new stdClass();
            $ret->id = -1;
            return $ret;
        }
        $ret = new stdClass();
        $ret->id = $user->id;
        return $ret;
    }
    public function update(){
    	if(!(parent::permit(FCF_Permission::$QUERY_TYPE_WRITE, $this->bean))) throw new FCF_Exception ("update not permitted");
    }
    public function after_update(){}
    public function open(){}
    public function delete(){}
    public function after_delete(){}
    public function dispense(){ }
}
class Model_Userinvite extends FCF_RedBean_SimpleModel{
    public function update(){
        // if user has admin or moderator role
        // he may invite users for all roles
        // except there is only one (root) admin allowed
        if(Model_Session::hasLoggedInUserForOneOfRoles(array(
                Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_SYSADMIN),
                Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_MODERATOR))
        )){
            // a user invite must be related to a role
            if(!($this->bean->__isset('sharedRole'))){
                throw new Exception('Model Userinvite update exception 3');
            }
            // check each role intended for the user to be invited
            $sharedRoles = $this->bean->sharedRole;
            foreach($sharedRoles as $role){
                if($role->name == Model_Role::$ROLE_NAME_SYSADMIN){
                    throw new Exception ('Model Userinvite update exception 2');
                }
            }
        }else{
            // if the user is not moderator or admin, 
            // the script must have run a permission before reaching this point 
            if( !( parent::permit(FCF_Permission::$QUERY_TYPE_WRITE,$this->bean) ) ){
                throw new Exception('Model Userinvite update exception 1');
            }
        }
        
        //
        // when arriving here, permission-wize everything is OK...
	//
        		
        // an invite can be used as a solution to the following problems
        // 
        // 1 - to reset the password via mail-sent invite
        // 2 - to create an account via mail-sent invite
        
        //
        // IN CASE OF PASSWORD RESET
        //
        if($this->bean->resetPassword == 1){
			$config = SimpleConfig::GetInstance();
            $this->bean->mailToken = FCF_Tools::randStr(32);
			$to      = $this->bean->mail;
            $subject = 'password reset for urbantranslations.net';
            $message = 'Please click this link to reset your password: <a href="' . $config->base_url . 'index.php?userInviteResetPassword=' . $this->bean->mailToken . '">go here</a>';
            $headers = 'From: ' . $config->sysmailfrom . "\r\n" .
                'Reply-To: ' . $config->sysmailreply . "\r\n" .
                'X-Mailer: PHP/' . phpversion();
            mail($to, $subject, $message, $headers);
            return true;
	}
        
        
        //
        // IN CASE OF ACCOUNT CREATION
        //
        $config = SimpleConfig::GetInstance();
        $this->bean->mailToken = FCF_Tools::randStr(32);
        $to      = $this->bean->mail;
        $subject = 'invitation to urbantranslations.net';
        $message = 'Please click this link to activate your subscription: <a href="' . $config->base_url . 'index.php?userInvite=' . $this->bean->mailToken . '">go here</a>';
        $headers = 'From: ' . $config->sysmailfrom . "\r\n" .
            'Reply-To: ' . $config->sysmailreply . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
        mail($to, $subject, $message, $headers);
	return true;
        
    }
    public function after_update(){}
    public function open(){
        return parent::permit(FCF_Permission::$QUERY_TYPE_READ, 'userinvite');
    }
    public function delete(){}
    public function after_delete(){}
    public function dispense(){ 
        $this->bean->pending = true;
        $this->bean->time = time();
    }
    private function userForToken($token){
        $userinvite = MM::allowOne(R::find('userinvite',' mailToken = ?',array($token)),'could not find userinvite for token');
        $user = MM::allowOne(R::find('user',' mail = ?',array($userinvite->mail)), 'could not find user for ' . $userinvite->mail);
        return $user;
    }
    public function userNameForToken($token){
        return $this->userForToken($token)->loginName;
    }
    public function resetPassword($token,$passwdhash){
        $user = $this->userForToken($token);
        $user->password = $passwdhash;
        parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1337647060, $user));
        R::store($user);
        $ret = new stdClass();
        $ret->success = 1;
        return $ret;
    }
}
class Model_User extends FCF_RedBean_SimpleModel {
    
    
	/**
	 * 
	 * You can only store a user if it has the ownLocation property pointing to an existing location.
	 */
    public function update() {
    	if(!$this->bean->ownLocation) throw new FCF_Exception('cannot update user without ownLocation property');
        if(!strlen($this->bean->loginName) >= 8) throw new FCF_Exception('loginName must have 8 or more characters');
        foreach($this->bean->ownLocation as $arrId => $loc){
            $loc->pending = 0;
        }
        $this->bean->pending = 0;
        if(parent::permit(FCF_Permission::$QUERY_TYPE_WRITE, $this->bean)) return true;
        // if you are logged in as an admin, OK
        if(Model_Session::hasLoggedInUserForOneOfRoles(array(
            Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_MODERATOR),
            Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_SYSADMIN),
        ))){
            return true;
        }
        // if you were invited, OK
        if($this->bean->invite){
            
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_READ, 1329439469, 'userinvite'));
            $invite = MM::allowOne(R::find('userinvite', ' mailToken = ?',array($this->bean->invite)),"invitation was not found");
            // if this passed, we retrieve the book to user relation
            $answer = R::relatedOne($invite, 'answer');
            $role = R::relatedOne($invite, 'role');
            $book = R::relatedOne($answer, 'book');
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440252, $this->bean));
            R::associate($book,$this->bean);
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440254, $this->bean));
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440256, $role->bean));
            R::associate($role,$this->bean);
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440258, $this->bean));
            $this->bean->mail = $invite->mail;
            R::trash($invite);
            $sess = MM::allowOne(R::findOrDispense('session', ' ssid = ?',array(session_id())),null);
            $sess->ssid = session_id();
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440268, $sess));
            R::store($sess);
            $_SESSION['authUser'] = "y";
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440259, $sess->bean));
            parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1329440260, $this->bean));
            R::associate($sess, $this->bean);
            return true;
        }
    }
	public function after_update() {
        
    }
    public function open() {
        // not-pending (existing) users need special permissions
        if(!$this->bean->pending){
            return parent::permit(FCF_Permission::$QUERY_TYPE_READ,$this->bean);
        }
    }
	public function delete() {
        throw new FCF_Exception('not implemented');
    }
	public function after_delete() {
        throw new FCF_Exception('not implemented');
    }
	/**
	 * Whenever a user bean is dispensed, it will first check
	 * the count of users so far. If no user is in the database
	 * it will first create an administrative user. The user will
	 * have the first location as well
	 */
    public function dispense() {
        $this->bean->pending = true;
        $this->bean->time = time();
    }
	/**
	 *
	 * request a password reset email
	 *
	 */
    public function resetPassword($mail){
        $user = MM::allowOne(R::find('user',' mail = ?',array($mail)),'could not find mail address');

        $userinvite = R::dispense('userinvite');


        $userinvite->mail = $user->mail;
        $userinvite->resetPassword = 1;
		
        parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1337639411, $userinvite));
        R::store($userinvite);
        $ret = new stdClass();
        $ret->success = 1;
        return $ret;
    }
    public function hasReviewed($uid){
        $user = R::find('user', ' id = ?',array($uid));
        if(count($user) != 1) throw new Exception("zero or multiple users for id " . $uid);
        $user = $user[key($user)];
        $reviews = R::related($user, 'review');
        $ret = new stdClass();
        $ret->numReviews = count($reviews);
        return $ret;
    }
    public function hasBook($uid){
        $user = R::find('user', ' id = ?',array($uid));
        if(count($user) != 1) throw new Exception("zero or multiple users for id " . $uid);
        $user = $user[key($user)];
        $book = R::related($user, 'book');
        // get other users related to this book
        // this user must be younger than all of these users
        $oneBook = $book[key($book)];
        $usersForBook = R::Related($oneBook,'user');
        $ret = new stdClass();
        foreach($usersForBook as $id => $userBean){
            if($userBean->time > $user->time){
                $ret->numBooks = 0;
                return $ret;
            }
        }
        $ret->numBooks = count($book);
        return $ret;
    }
}
class Model_Role extends FCF_RedBean_SimpleModel {
    
    // it is not allowed (false) or allowed (true) to get all fields returned.
    // if this is true, not specifying fields will return you all fields
    protected static $findFieldsAllowReturnAllFields = false;
    
    // it is not allowed (false) or allowed (true) to select all records
    // if this is true, not specifying key value pairs for the
    // 'where-clause', will return you all records
    protected static $findFieldsAllowSelectAllRecords = false;
    
    // it is generally forbidden (false) or allowed (true) 
    // for all column values to be retrieved. Exceptions to
    // this general rule can be defined in the exceptions array.
    protected static $findFieldsGenerallyFindable = false;
    
    // it is generally forbidden (false) of allowed (true)
    // for all column values to be used in the 'where-clause'.
    // Exceptions to this general rule can be defined in the exceptions array.
    protected static $findFieldsGenerallySearchable = false;
    
    // you can define static methods on this class
    // or public static methods on other classes
    // to decide (by returning a boolean)
    // if an exception will be made to the general rule.
    // you can also simply use a boolean to define a non-dynamic exception.
    protected static $findFieldsSearchableExceptions = array(
        "id"    =>  "self::returnTrue",
        "name"  =>  "Model_Session::fakeReturnTrueIfAdmin",
        "barf"  =>  true // it's no problem to use non-existing fieldnames here
    );
    protected static $findFieldsFindableExceptions = array(
        "id"    =>  "self::returnTrue",
        "name"  =>  "Model_Session::fakeReturnTrueIfAdmin",
        "barf"  =>  true
    );
    public static function returnTrue(){ 
        return true;
    }
    

    /**
     * role name for the system administrator
     */
    public static $ROLE_NAME_SYSADMIN = 'sysadmin';
    /**
     * role name for a moderating user
     */
    public static $ROLE_NAME_MODERATOR = 'moderator';
    /**
     * role name for a registered user
     */
    public static $ROLE_NAME_REGISTERED_USER = 'registeredUser';
    /**
     * role name for an anonymous user
     */
    public static $ROLE_NAME_ANONYMOUS_USER = 'anonymousUser';

    public static function getRoleIdForName($name){
        $role = MM::allowOne(R::find('role', ' name = ?',array($name)),"role getRoleIdForName 1");
        return $role->id;
    }
    /**
    * You can update the name of a role, needs admin permission TODO implement admin permission
    */
    public function update() { 
        // needs special permissions
        if(!parent::permit(FCF_Permission::$QUERY_TYPE_WRITE, $this->bean)){
            throw new FCF_Exception('need special permissions to update a role');
        }
    }
        
    public function after_update() {
        if(R::count('role') == 1){
        // assuming the first role is admin, create the other basic roles
        $mod = R::dispense('role');
        $reg = R::dispense('role');
        $ano = R::dispense('role');
        $mod->name = self::$ROLE_NAME_MODERATOR;
        $reg->name = self::$ROLE_NAME_REGISTERED_USER;
        $ano->name = self::$ROLE_NAME_ANONYMOUS_USER;
        parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1328398343, $mod));
        R::store($mod);
        parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1328398344, $reg));
        R::store($reg);
        parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1328398345, $ano));
        R::store($ano);

        // now create the user with the admin role
        $config = SimpleConfig::GetInstance();
        $adUser = R::dispense('user');
        $adUser->loginName = $config->adminLoginName;
        $adUser->screenName = $config->adminScreenName;
        $adUser->password = hash("sha512", $config->adminPassword);
        $adUser->mail = $config->adminMail;
        $adUserLoc = R::dispense('location');
        $adUserLoc->Ma = $config->adminMa;
        $adUserLoc->Na = $config->adminNa;
        $adUser->ownLocation[] = $adUserLoc;
        $adminRole = R::find('role', ' name = :rolename', array( ':rolename'=>'sysadmin' ));
        $adminRole = $adminRole[key($adminRole)];
        $adUser->sharedRole[] = $adminRole;
        R::store($adUserLoc);
        R::store($adUser);
        	
        }
    }
    public function open() {
        
    }
	public function delete() {
        throw new FCF_Exception('not implemented');
    }
	public function after_delete() {
        throw new FCF_Exception('not implemented');
    }
	public function dispense() {
		if(R::count('role') == 0){
        	if(!(R::count('location') == 0 && R::count('user') == 0)) throw new FCF_Exception('corrupt db on first init');
        	$this->bean->name = self::$ROLE_NAME_SYSADMIN;
        	parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1328398455, $this->bean));
        }
        $this->bean->pending = false;
        $this->bean->time = time();
    }
    /**
     * CUSTOM METHODS
     */
    public function roleForName($name){
        $res = R::find('role', ' name=:nm', array('nm' => $name));
        $ret = $res[key($res)];
        return $ret;
    }
}
?>