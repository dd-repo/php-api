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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: <a href=\"/grant/token/help\">token</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> allow, accept, give, add</li>
	<li><h2>Description :</h2> give the target grants to the provided tokens (of a user)</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the target grant. <span class=\"required\">required</span>. <span class=\"multiple\">multiple</span>. (alias: grant_name, grant_names, grants, grant_id, grant_ids, kid, kids)</li>
			<li>token : The value of the target token. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : token_name, tokens, token_names)</li>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_TOKEN_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_TOKEN_INSERT'));

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
$token = request::getCheckParam(array(
	'name'=>array('token_value', 'token', 'tokens', 'token_values'),
	'optional'=>false,
	'minlength'=>32,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}",
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
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

$tokens = '';
foreach( $token as $t )
	if( strlen($t) > 0 )
		$tokens .= ",'".security::escape($t)."'";

if( is_numeric($user) )
	$where = "u.user_id=".$user;
else
	$where = "u.user_name = '".security::escape($user)."'";

// =================================
// EXECUTE QUERY
// =================================
$sql = "INSERT IGNORE INTO token_grant (grant_id, token_id)
		SELECT DISTINCT k.grant_id, t.token_id
			FROM users u LEFT JOIN tokens t ON(u.user_id=t.token_user), grants k
			WHERE (k.grant_name IN(''{$grant_names}) OR k.grant_id IN(-1{$grant_ids}))
			AND (t.token_value IN(''{$tokens})) AND {$where}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>