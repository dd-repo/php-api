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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a group</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>group : The name or id of the group to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, group_name, id, group_id, gid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GROUP_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GROUP_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$group = request::getCheckParam(array(
	'name'=>array('group', 'name', 'group_name', 'id', 'group_id', 'gid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE,
	'action'=>true
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
if( is_numeric($group) )
	$where = "group_id=".$group;
else
	$where = "group_name = '".security::escape($group)."'";

// =================================
// EXECUTE QUERY
// =================================
$sql = "DELETE FROM groups WHERE {$where}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// CLEANUP INVALID TOKEN GRANTS
// =================================
grantStore::add('TOKEN_CLEANUP');
request::forward('/token/cleanup');

responder::send("OK");

?>