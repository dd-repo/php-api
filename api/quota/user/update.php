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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: <a href=\"/quota/user/help\">user</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, fix, define, raise, lower, consume, free, change</li>
	<li><h2>Description :</h2> updates the target quotas of the provided users</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias: user_name, username, user_names, users, usernames, login, logins, user_id, user_ids, uid, uids)</li>
			<li>quota : The name or id of the target quota. <span class=\"required\">required</span>. <span class=\"multiple\">multiple</span>. (alias : quota_name, quotas, quota_names, quota_id, quota_ids, qid, qids)</li>
			<li>max : The maximum value of the quota. <span class=\"optional\">optional</span>. (alias : maximum, limit, quota_max)</li>
			<li>current : The current value of the quota. <span class=\"optional\">optional</span>. (alias : value, amount, used, quota_used)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_USER_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_USER_UPDATE'));

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
$max = request::getCheckParam(array(
	'name'=>array('max', 'maximum', 'limit', 'quota_max'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$current = request::getCheckParam(array(
	'name'=>array('current', 'value', 'amount', 'used', 'quota_used'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$over = request::getCheckParam(array(
	'name'=>array('over'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
	
if( $over == '1' || $over == 'yes' || $over == 'true' || $over === true || $over === 1 )
	$over = true;
else
	$over = false;
	
if( $user === null && $quota === null )
	throw new ApiException("Missing conditional parameter", 412, "Cannot specify none of max and current");
	
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
$sql = "UPDATE IGNORE user_quota 
		SET quota_max=".($max===null?'quota_max':$max).",";
if( $over )
	$sql .= "quota_used=".($current===null?'quota_used':$current);
else
	$sql .= "quota_used=LEAST(".($current===null?'quota_used':$current).",".($max===null?'quota_max':$max).")";

$sql .= "
		WHERE quota_id IN (SELECT q.quota_id FROM quotas q WHERE (q.quota_name IN(''{$quota_names}) OR q.quota_id IN(-1{$quota_ids})))
		AND user_id IN (SELECT u.user_id FROM users u WHERE (u.user_name IN(''{$user_names}) OR u.user_id IN(-1{$user_ids})))";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>