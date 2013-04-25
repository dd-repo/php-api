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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: <a href=\"/group/user/help\">user</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> lists all groups of a user or all users of a group</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the users to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>group : The name or id of the group to search for. <span class=\"optional\">optional</span>. (alias : group_name, group_id, gid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching groups or users [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, GROUP_USER_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GROUP_USER_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$group = request::getCheckParam(array(
	'name'=>array('group', 'group_name', 'group_id', 'gid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE
	));

if( $user !== null && $group !== null )
	throw new ApiException("Too many parameters", 412, "Cannot specify both user ({$user}} and group ({$group})");
if( $user === null && $group === null )
	throw new ApiException("Missing conditional parameter", 412, "Cannot specify none of user and group");

// =================================
// EXECUTE QUERY FOR USER
// =================================
if( $user !== null )
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";
	
	$sql = "SELECT g.group_name, g.group_id
			FROM users u
			LEFT JOIN user_group ug ON(u.user_id = ug.user_id)
			LEFT JOIN groups g ON(g.group_id = ug.group_id)
			WHERE {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$groups = array();
	foreach( $result as $r )
		if( $r['group_id'] != null )
			$groups[] = array('name'=>$r['group_name'], 'id'=>$r['group_id']);
	
	responder::send($groups);
}

// =================================
// EXECUTE QUERY FOR GROUP
// =================================
else
{
	if( is_numeric($group) )
		$where = "g.group_id=".$group;
	else
		$where = "g.group_name = '".security::escape($group)."'";
	
	$sql = "SELECT u.user_name, u.user_id
			FROM groups g
			LEFT JOIN user_group ug ON(g.group_id = ug.group_id)
			LEFT JOIN users u ON(u.user_id = ug.user_id)
			WHERE {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$users = array();
	foreach( $result as $r )
		if( $r['user_id'] != null )
			$users[] = array('name'=>$r['user_name'], 'id'=>$r['user_id']);
	
	responder::send($users);
}

?>