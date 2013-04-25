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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/token/help\">token</a> :: purge</h1>
<ul>
	<li><h2>Alias :</h2> clean, cleanup</li>
	<li><h2>Description :</h2> remove all outdated tokens of the target user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, TOKEN_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'TOKEN_DELETE'));

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

// =================================
// PREPARE WHERE CLAUSE
// =================================
if( is_numeric($user) )
	$where = "u.user_id=".$user;
else
	$where = "u.user_name = '".security::escape($user)."'";

// =================================
// EXECUTE QUERY
// =================================
$sql = "DELETE t FROM tokens t
		LEFT JOIN users u ON(t.token_user = u.user_id)
		WHERE token_lease > 0 AND token_lease < UNIX_TIMESTAMP() AND {$where}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>