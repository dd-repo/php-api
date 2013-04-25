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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: <a href=\"/grant/user/help\">user</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> allow, accept, give, add</li>
	<li><h2>Description :</h2> give the target grants to the provided users</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the target grant. <span class=\"required\">required</span>. <span class=\"multiple\">multiple</span>. (alias: grant_name, grant_names, grants, grant_id, grant_ids, kid, kids)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : user_name, username, login, user, user_names, usernames, logins, users, user_id, uid, user_ids, uids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_USER_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_USER_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant_name', 'grant', 'grant_id', 'kid', 'grant_names', 'grants', 'grant_ids', 'kids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*"
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_names', 'usernames', 'logins', 'users', 'user_id', 'uid', 'user_ids', 'uids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$grant_ids = '';
$grant_names = '';
foreach( $grant as $k )
{
	if( is_numeric($k) )
		$grant_ids .= ','.$k;
	else if( strlen($k) > 0 )
		$grant_names .= ",'".security::escape($k)."'";
}

$user_ids = '';
$user_names = '';
foreach( $user as $u )
{
	if( is_numeric($u) )
		$user_ids .= ','.$u;
	else if( strlen($u) > 0 )
		$user_names .= ",'".security::escape($u)."'";
}

// =================================
// EXECUTE QUERY
// =================================
$sql = "INSERT IGNORE INTO user_grant (grant_id, user_id)
		SELECT DISTINCT k.grant_id, u.user_id
			FROM grants k, users u
			WHERE (k.grant_name IN(''{$grant_names}) OR k.grant_id IN(-1{$grant_ids}))
			AND (u.user_name IN(''{$user_names}) OR u.user_id IN(-1{$user_ids}))";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>