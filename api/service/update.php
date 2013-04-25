<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$help = request::getAction(false, false);
if( $help == 'help' || $help == 'doc' )
{
	$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/database/help\">database</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> changes the password of a database</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>database : The name of the database. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, database_name)</li>
			<li>pass : The new password of the database. <span class=\"optional\">optional</span>. (alias : password, database_pass, database_password)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, DATABASE_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'DATABASE_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$database = request::getCheckParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::UPPER|request::NUMBER,
	'action'=>true
	));
$pass = request::getCheckParam(array(
	'name'=>array('pass', 'password', 'site_pass', 'site_password'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::PHRASE|request::SPECIAL
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

// =================================
// GET LOCAL DATABASE INFO
// =================================
if( $user !== null )
{
	$sql = "SELECT d.database_type
			FROM users u
			LEFT JOIN `databases` d ON(d.database_user = u.user_id)
			WHERE database_name = '".security::escape($database)."'
			AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
}
else
{
	$sql = "SELECT d.database_type
			FROM `databases` d
			WHERE database_name = '".security::escape($database)."'";
}
$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
if( $result == null || $result['database_type'] == null )
	throw new ApiException("Forbidden", 403, "Database {$database} not found (for user {$user} ?)");

// =================================
// UPDATE REMOTE DATABASE
// =================================
$params = array('userPassword'=>base64_encode($pass), 'type'=>$result['database_type']);
asapi::send('/databases/'.$database, 'PUT', $params);

responder::send("OK");

?>