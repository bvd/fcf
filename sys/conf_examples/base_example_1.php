<?php
/**
 * you should rename this file to base.php
 * and fill in your own values here 
 */
$config = array();
/**
* the database
*/
$config['dbName'] = '';
$config['dbUser'] = '';
$config['dbPass'] = '';
$config['dbHost'] = '';
/**
* the administrative user
*/
$config['adminLoginName'] = '';
$config['adminScreenName'] = '';
$config['adminPassword'] = '';
$config['adminMa'] = 0;
$config['adminNa'] = 0;
$config['adminMail'] = '';
/**
* email data
*/ 
$config['sysmailfrom'] = "";
$config['sysmailreply'] = "";
/**
* base URL 
*/
$config['base_url'] = "";
/**
 * base dir where we store all types of files owned by / uploaded by users
 */
$config['userfiles_url'] = $config['base_url'] . "useruploads/";
$config['userfiles_path'] = FCF_ROOT . "/useruploads/";

/**
 * return the configuration array
 */
return $config;
?>