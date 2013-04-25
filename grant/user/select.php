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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: <a href=\"/grant/user/help\">user</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search, check, verify</li>
	<li><h2>Description :</h2> lists all grants of a user or all users of a grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the grants to search for. <span class=\"optional\">optional</span>. (alias : grant_name, grant_id, kid)</li>
			<li>user : The name or id of the user to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>overall : Whether or not to consider group grants when searching for users. <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching users or grants [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_USER_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_USER_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant', 'grant_name', 'grant_id', 'kid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$overall = request::getCheckParam(array(
	'name'=>array('overall'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));

if( $grant !== null && $user !== null )
	throw new ApiException("Too many parameters", 412, "Cannot specify both grant ({$grant}} and user ({$user})");
if( $grant === null && $user === null )
	throw new ApiException("Missing conditional parameter", 412, "Cannot specify none of grant and user");

if( $overall == '1' || $overall == 'yes' || $overall == 'true' || $overall === true || $overall === 1 )
	$overall = true;
else
	$overall = false;

// =================================
// EXECUTE QUERY FOR GRANT
// =================================
if( $grant !== null )
{
	if( is_numeric($grant) )
		$where = "k.grant_id=".$grant;
	else
		$where = "k.grant_name = '".security::escape($grant)."'";
	
	$sql = "SELECT u.user_name, u.user_id
			FROM grants k
			LEFT JOIN user_grant uk ON(k.grant_id = uk.grant_id)
			LEFT JOIN users u ON(u.user_id = uk.user_id)
			WHERE {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$users = array();
	foreach( $result as $r )
		if( $r['user_id'] != null )
			$users[] = array('name'=>$r['user_name'], 'id'=>$r['user_id']);
	
	responder::send($users);
}

// =================================
// EXECUTE QUERY FOR USER
// =================================
else
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";
	
	if( $overall )
	{
		$sql = "SELECT DISTINCT k.grant_name, k.grant_id 
				FROM users u
				LEFT JOIN user_grant uk ON(u.user_id = uk.user_id)
				LEFT JOIN user_group ug ON(u.user_id = ug.user_id)
				LEFT JOIN group_grant gk ON(ug.group_id = gk.group_id)
				LEFT JOIN grants k ON(k.grant_id = gk.grant_id OR k.grant_id = uk.grant_id)
				WHERE {$where}";
	}
	else
	{
		$sql = "SELECT k.grant_name, k.grant_id
				FROM users u
				LEFT JOIN user_grant uk ON(u.user_id = uk.user_id)
				LEFT JOIN grants k ON(k.grant_id = uk.grant_id)
				WHERE {$where}";
	}
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$grants = array();
	foreach( $result as $r )
		if( $r['grant_id'] != null )
			$grants[] = array('name'=>$r['grant_name'], 'id'=>$r['grant_id']);
	
	responder::send($grants);
}

?>