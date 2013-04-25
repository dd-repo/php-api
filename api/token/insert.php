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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/token/help\">token</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new token for the target user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>pass : The password of the user if not using token authentication. <span class=\"optional\">optional</span>. (alias : password, user_pass, user_password)</li>
			<li>lease : The lease time of the token (0 or 'never' will not expire; before now's timestamp is considered seconds from now). <span class=\"optional\">optional</span>. (alias : timeout, time, length, ttl, expire, token_lease)</li>
			<li>name : The new token's friendly name. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : token_name, label, description)</li>
			<li>grant : The name or id of grants to give to the new token. <span class=\"optional\">optional</span>. (alias : grant_name, grants, grant_names, grant_id, grant_ids, gid, gids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created token {'token'}</li>
	<li><h2>Required grants :</h2> ACCESS, TOKEN_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'TOKEN_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$lease = request::getCheckParam(array(
	'name'=>array('lease', 'timeout', 'time', 'length', 'ttl', 'expire', 'token_lease'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>"(never|[0-9]{1,11})"
	));
$name = request::getCheckParam(array(
	'name'=>array('name', 'token_name', 'label', 'description'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$grants = request::getCheckParam(array(
	'name'=>array('grant', 'grant_name', 'grants', 'grant_names', 'grant_id', 'grant_ids', 'gid', 'gids'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>150,
	'match'=>request::ALPHANUM|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*"
	));

if( $lease === null )
	$lease = 1800; // 30 min by default
if( $lease == 'never' )
	$lease = 0;
else if( $lease < time()-86400 )
	$lease = time() + $lease;

if( is_numeric($name) )
	throw new ApiException("Parameter validation failed", 412, "Parameter name may not be numeric : " . $name);

// =================================
// PREPARE WHERE CLAUSE
// =================================
if( is_numeric($user) )
	$where = "u.user_id=".$user;
else
	$where = "u.user_name = '".security::escape($user)."'";

// =================================
// CREATE TOKEN
// =================================
$token = md5($user.$lease.time());
$sql = "INSERT INTO tokens (token_user, token_lease, token_name, token_value) 
		VALUES ((SELECT u.user_id FROM users u WHERE {$where}), {$lease}, '".security::escape($name)."', '{$token}')";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// GIVE GRANTS
// =================================
if( $grants !== null && count($grants) > 0 )
{
	$token_id = $GLOBALS['db']->last_id();
	$grant_ids = '';
	$grant_names = '';
	foreach( $grants as $g )
	{
		if( strlen($g) == 0 )
			continue;
		if( is_numeric($g) )
			$grant_ids .= ','.$g;
		else if( strlen($g) > 0 )
			$grant_names .= ",'".security::escape($g)."'";
	}
	
	$sql = "INSERT IGNORE INTO token_grant (token_id, grant_id)
			SELECT DISTINCT {$token_id}, k.grant_id
				FROM users u 
				LEFT JOIN user_grant uk ON(u.user_id = uk.user_id)
				LEFT JOIN user_group ug ON(u.user_id = ug.user_id)
				LEFT JOIN group_grant gk ON(ug.group_id = gk.group_id)
				LEFT JOIN grants k ON(k.grant_id = gk.grant_id OR k.grant_id = uk.grant_id)
				WHERE {$where} AND (k.grant_name IN(''{$grant_names}) OR k.grant_id IN(-1{$grant_ids}))";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
}

responder::send(array('token'=>$token));
	
?>