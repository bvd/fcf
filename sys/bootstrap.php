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
 * FatClientFramework (FCF) startup script
 * bootstrapping and returning the initial page or setting up extended redbean
 * in case of a system command or a bean can style query.
 */
class FCF_Bootstrap
{
	public static function run(){
		// what is the location of the front controller
		$scriptLocation = $_SERVER['SCRIPT_FILENAME'];
		// where is our front controller's directory
		$scriptDir = substr($scriptLocation, 0, strrpos($scriptLocation, '/'));
		// define root directory
		define("FCF_ROOT", $scriptDir);
		// define the path to the application
		define("FCF_APP", FCF_ROOT . "/app");
		// if we do not have to run a command or a transaction:
		if(!(isset($_POST["tr"]) || isset($_GET["tr"]) || isset($_POST["comm"]) || isset($_GET["comm"]))){
			self::initPage();
		}else{
			// setup redbean, initialize directories, etc.
			self::initFCF();
			if(isset($_POST["tr"]) || isset($_GET["tr"])){
				// execute a transaction
				$tr = new FCF_Transaction(isset($_POST["tr"]) ? $_POST : $_GET);
				exit($tr->exec());
			}
			else{
				// execute a command
				$commName = isset($_POST["comm"]) ? $_POST["comm"] : $_GET["comm"];
				$commParams = isset($_POST['params']) ? $_POST['params'] : isset($_GET['params']) ? $_GET['params'] : array();
				$comm = new FCF_SysCommand($commName, $commParams);
				exit($comm->run());
			}
		}
	}
	public static function initFCF(){
		// define the path to the framework main directory
		define("FCF_SYS", FCF_ROOT . "/sys");
		
		// require the configuration class
		require_once(FCF_SYS . "/ext/config/SimpleConfig.php");
		
		// set the base configuration file
		if(!(is_file(FCF_APP . "/conf/base.php"))) throw new Exception("config file not found at " . FCF_APP . "/conf/base.php");
		SimpleConfig::setFile(FCF_APP . "/conf/base.php");
		
		require_once(FCF_SYS . "/fcf.php");
		FatClientFramework::init();
	}
	public static function initPage(){
		$indexPage = FCF_APP . "/pages/index.php";
                if(!(file_exists($indexPage))){
			die("error: " . $indexPage . " does not exist");
		}
                require_once $indexPage;
	}
}
//ob_start();
try{
	FCF_Bootstrap::run();
} 
catch (Exception $e) {
	//ob_end_clean();
	echo "ERROR: " . $e->getMessage();
}
//ob_end_flush();
?>