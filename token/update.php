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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/token/help\">token</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change, rename, extend, report</li>
	<li><h2>Description :</h2> change the name or lease time of a token of the target user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>token : The token value to change. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : value, token_value)</li>
			<li>name : The new friendly name of the token. <span class=\"optional\">optional</span>. (alias : token_name, label, description)</li>
			<li>lease : The lease time of the token (0 or 'never' will not expire; before now's timestamp is considered seconds from now). <span class=\"optional\">optional</span>. (alias : timeout, time, length, ttl, expire, token_lease)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, TOKEN_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'TOKEN_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$value = request::getCheckParam(array(
	'name'=>array('value', 'token_value', 'token'),
	'optional'=>false,
	'minlength'=>32,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}",
	'action'=>true
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
	'match'=>request::PHRASE|request::SPECIAL
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

if( $name === null && $lease === null )
	throw new ApiException("Missing parameter", 400, "Nothing to change");

// =================================
// PREPARE UPDATE
// =================================
$update = '';
if( $name !== null )
	$update .= " token_name='".security::escape($name)."'";
if( $lease !== null )
{
	if( $lease == 'never' )
		$lease = 0;
	else if( $lease < time()-86400 )
		$lease = time() + $lease;
	
	if( strlen($update) > 0 )
		$update .= ',';
	$update .= " token_lease = ".$lease;
}

if( is_numeric($user) )
	$where = "user_id=".$user;
else
	$where = "user_name = '".security::escape($user)."'";

// =================================
// UPDATE TOkEN
// =================================
$sql = "UPDATE tokens SET {$update} WHERE token_value='{$value}' AND token_user=(SELECT user_id FROM users WHERE {$where})";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>