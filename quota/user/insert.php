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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: <a href=\"/quota/user/help\">user</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> add</li>
	<li><h2>Description :</h2> provides the target quotas to the provided users</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias: user_name, username, user_names, users, usernames, login, logins, user_id, user_ids, uid, uids)</li>
			<li>quota : The name or id of the target quota. <span class=\"required\">required</span>. <span class=\"multiple\">multiple</span>. (alias : quota_name, quotas, quota_names, quota_id, quota_ids, qid, qids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_USER_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_USER_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid', 'user_names', 'usernames', 'logins', 'users', 'user_ids', 'uids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*"
	));
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'quota_name', 'quotas', 'quota_names', 'quota_id', 'quota_ids', 'qid', 'qids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*"
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$user_ids = '';
$user_names = '';
foreach( $user as $u )
{
	if( is_numeric($u) )
		$user_ids .= ','.$u;
	else if( strlen($u) > 0 )
		$user_names .= ",'".security::escape($u)."'";
}

$quota_ids = '';
$quota_names = '';
foreach( $quota as $q )
{
	if( is_numeric($q) )
		$quota_ids .= ','.$q;
	else if( strlen($q) > 0 )
		$quota_names .= ",'".security::escape($q)."'";
}

// =================================
// EXECUTE QUERY
// =================================
$sql = "INSERT IGNORE INTO user_quota (user_id, quota_id)
		SELECT DISTINCT u.user_id, q.quota_id
			FROM users u, quotas q
			WHERE (u.user_name IN(''{$user_names}) OR u.user_id IN(-1{$user_ids}))
			AND (q.quota_name IN(''{$quota_names}) OR q.quota_id IN(-1{$quota_ids}))";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>