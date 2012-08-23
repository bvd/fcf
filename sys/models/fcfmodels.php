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
    // to do deprecate
    public static function getLoggedInUser(){
        $thisSS = MM::allowOne(R::find('session',' ssid = ?',array(session_id())),null);
        $user;
        // to open the user we need a permission because opening users is generally not allowed
        
        if($thisSS) $user = R::relatedOne($thisSS, 'user'); else return false;
        if(!$user) return false;
        return $user;
    }
    // to do deprecate
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
    // TODO this should be part of the user model.
    // it should be enough to just retrieve the session user from the session model
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
        $ret->screenName = $user->screenName;
        
        $ret->userRoles = array();
        $rolesArr = $user->sharedRole;
        foreach($rolesArr as $id => $roleBean){
            $ret->userRoles[$id] = $roleBean->name;
        }
        
        
        return $ret;
    }
    public function update(){
    	if(parent::canWrite()) return true;
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
        if($this->bean->resetEmail == 1){
            $config = SimpleConfig::GetInstance();
            $this->bean->mailToken = FCF_Tools::randStr(32);
            $to      = $this->bean->mail;
            $subject = 'email reset for urbantranslations.net';
            $message = 'Please click this link to complete your email reset: <a href="' . $config->base_url . 'index.php?userInviteResetEmail=' . $this->bean->mailToken . '">go here</a>';
            $headers =      'From: ' . $config->sysmailfrom . "\r\n" .
                            'Reply-To: ' . $config->sysmailreply . "\r\n" .
                            'X-Mailer: PHP/' . phpversion();
            mail($to, $subject, $message, $headers);
            return true;
	}
        //
        // IN CASE OF THE DEFAULT IE ACCOUNT CREATION
        //
        $config = SimpleConfig::GetInstance();
        $this->bean->mailToken = FCF_Tools::randStr(32);
        $to      = $this->bean->mail;
        $subject = 'invitation to urbantranslations.net';
        $message = 'Welcome to the urban translations website. Please complete your subscription under the following link. After you complete your subscription, if you forget your password, you may use this e-mail address to reset your password. Now, <a href="' . $config->base_url . 'index.php?userInvite=' . $this->bean->mailToken . '">go here</a>';
        $headers = 'From: ' . $config->sysmailfrom . PHP_EOL;
        $headers .= 'Reply-To: ' . $config->sysmailreply . PHP_EOL;
        $headers .= 'X-Mailer: PHP/' . phpversion() . PHP_EOL;
        $headers .= 'MIME-Version: 1.0' . PHP_EOL;
        $headers .= 'Content-Type: text/html; charset=ISO-8859-1' . PHP_EOL;
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
    public function userNameForToken($token){
        $userinvite = MM::allowOne(R::find('userinvite',' mailToken = ?',array($token)),'could not find userinvite for token');
        if($userinvite->resetEmail){
            $user = Model_Session::getLoggedInUser();
            $user->mail = $userinvite->mail;
            R::store($user);
        }else{
            $user = MM::allowOne(R::find('user',' mail = ?',array($userinvite->mail)), 'could not find user for ' . $userinvite->mail); 
        }
        return $user->loginName;
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
    	//
        //
        // VALIDATORS
        //
        //
        
        $bean = $this->bean;
        //
        // a user must always have a location
        //
        $ownLocation = $bean->ownLocation;
        if(!$ownLocation) {
            throw new FCF_Exception('cannot update user without ownLocation property');
        }
        //
        // the login name may not have less than 8 characters
        //
        parent::minLengthOfField($bean,"loginName",8);
        
        //
        // the login name must be unique
        //
        parent::uniqueBeanForField($bean, "loginName");
        
        //
        // the email address must be unique
        //
        parent::uniqueBeanForField($bean, "mail");
        
        $allowed = false;
        // either we have specific write permission
        // or we have common write permission
        // or we look for exceptional permissive situations
        
        $allowed = parent::permit(FCF_Permission::$QUERY_TYPE_WRITE, $this->bean);
        if(!$allowed) $allowed = parent::canWrite();
        if($allowed){
            return true;
        }else{
            $allowed = Model_Session::hasLoggedInUserForOneOfRoles(array(
                Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_MODERATOR),
                Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_SYSADMIN),
            ));
        }
        if(!$allowed){
            $loggedInUser = Model_Session::getLoggedInUser();
            if($loggedInUser){
                if($loggedInUser->id == $this->bean->id){
                    $allowed = true;
                }
            }
        }
        if(!($allowed)){
            // if not allowed at this point, the user must be invited
            if($this->bean->invite){
                parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_READ, 1329439469, 'userinvite'));
                $invite = MM::allowOne(R::find('userinvite', ' mailToken = ?',array($this->bean->invite)),"invitation was not found");
                $allowed = true;
            }
        }
        if(!$allowed) throw new FCF_Exception("not allowed: Model_User->update");
        //
        //
        // END OF VALIDATOR SECTION,
        // START OF FILTER SECTION
        //
        //
        foreach($this->bean->ownLocation as $arrId => $loc){
            $loc->pending = 0;
        }
        $this->bean->pending = 0;
        if(isset($invite)){
            parent::setSecurityLevel(parent::SECURITY_LEVEL_ALLOW_WRITE);
            // retrieve the book-to-user relation
            $answer = R::relatedOne($invite, 'answer');
            $role = R::relatedOne($invite, 'role');
            if($answer){
                $book = R::relatedOne($answer, 'book');
                R::associate($book,$this->bean);
            }
            R::associate($role,$this->bean);
            $this->bean->mail = $invite->mail;
            R::trash($invite);
            $sess = MM::allowOne(R::findOrDispense('session', ' ssid = ?',array(session_id())),null);
            $sess->ssid = session_id();
            R::store($sess);
            $_SESSION['authUser'] = "y";
            R::associate($sess, $this->bean);
        }
    }
    public function after_update() {
        
    }
    public function open() {
        if(!$this->bean->pending){
            // TODO it is generally allowed to open a user but it will be stripped according to role
            // TODO TO DEPR it is generally forbidden to open a user 
            // except if the program added a permission during this script execution
            $permitted = parent::permit(FCF_Permission::$QUERY_TYPE_READ,$this->bean);
            if(!($permitted)){
                // if it is not permitted we can make exceptions
                $sessionUserIsAdminOrModerator;
                $sessionUserIsOpenedUser;
                // To find out who is the session user
                // the session has a method that returns this user.
                // Beware that, in order to do so, it thus opens a user.
                // This will bring about this function again (Model_User->open).
                // To prevent a never ending loop from occuring we need a permission for a user type bean.
                $permission = new FCF_Permission(FCF_Permission::$QUERY_TYPE_READ, 1345046702, null, array("type" => "user"));
                // we could add it to the parent (if it were another bean type) but we might as well use this class.
                self::addPermission($permission);
                $sessionUser = Model_Session::getLoggedInUser();
                $sessionUserIsOpenedUser = ($sessionUser == null) ? false : $sessionUser->id == $this->id;
                if($sessionUserIsOpenedUser) return;
                // the same story as above about the nested loop problem
                $permission = new FCF_Permission(FCF_Permission::$QUERY_TYPE_READ, 1345107157, null, array("type" => "user"));
                self::addPermission($permission);
                // roles to permit reading a user:
                $allowedRoleIds = array();
                $allowedRoleIds[] = Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_SYSADMIN);
                $allowedRoleIds[] = Model_Role::getRoleIdForName(Model_Role::$ROLE_NAME_MODERATOR);
                $sessionUserHasOneOfAllowedRoles = Model_Session::hasLoggedInUserForOneOfRoles($allowedRoleIds);
                if($sessionUserHasOneOfAllowedRoles) return;
                // now we are going to use an always readable policy
                // with a restrictive per field permission
                // where exceptions are made on following fields
                $fieldExceptions = array("id","screenName");
                $fieldsToRemove = array();
                $props = $this->bean->getProperties();
                foreach ($props as $k => $v) {
                    if(!(in_array($k,$fieldExceptions))){
                        $fieldsToRemove[] = $k;
                    }
                }
                $noClue = "almost";
                foreach($fieldsToRemove as $fieldToRemove){
                    $this->bean->removeProperty($fieldToRemove);
                }
                $noClue = "true";
            }
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
    public function resetEmail($mail){
        $user = Model_Session::getLoggedInUser();
        if(!$user) throw new FCF_Exception("no user was returned from session");
        if($user->id < 0) throw new FCF_Exception("no user was logged in to session");
        $userinvite = R::dispense('userinvite');
        $userinvite->mail = $mail;
        $userinvite->resetEmail = 1;	
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
        parent::addPermission(new FCF_Permission(FCF_Permission::$QUERY_TYPE_WRITE, 1345642595, $adUser));
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
class Model_Location extends FCF_RedBean_SimpleModel {
    public function update() {
        
    }
	public function after_update() {
        
    }
	public function open() {
        //throw new spdException('not implemented');
    }
	public function delete() {
        throw new spdException('not implemented');
    }
	public function after_delete() {
        throw new spdException('not implemented');
    }
	public function dispense() {
        $this->bean->pending = true;
        $this->time = time();
    }
}
class spdTools {
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