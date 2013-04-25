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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: <a href=\"/group/user/help\">user</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> link, bind, enter, join, add</li>
	<li><h2>Description :</h2> makes the target users member of the provided groups</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias: user_name, username, user_names, users, usernames, login, logins, user_id, user_ids, uid, uids)</li>
			<li>group : The name or id of the target group. <span class=\"required\">required</span>. <span class=\"multiple\">multiple</span>. (alias : group_name, groups, group_names, group_id, group_ids, gid, gids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GROUP_USER_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GROUP_USER_INSERT'));

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
$group = request::getCheckParam(array(
	'name'=>array('group', 'group_name', 'groups', 'group_names', 'group_id', 'group_ids', 'gid', 'gids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE,
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

$group_ids = '';
$group_names = '';
foreach( $group as $g )
{
	if( is_numeric($g) )
		$group_ids .= ','.$g;
	else if( strlen($g) > 0 )
		$group_names .= ",'".security::escape($g)."'";
}

// =================================
// EXECUTE QUERY
// =================================
$sql = "INSERT IGNORE INTO user_group (user_id, group_id)
		SELECT DISTINCT u.user_id, g.group_id
			FROM users u, groups g
			WHERE (u.user_name IN(''{$user_names}) OR u.user_id IN(-1{$user_ids}))
			AND (g.group_name IN(''{$group_names}) OR g.group_id IN(-1{$group_ids}))";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>