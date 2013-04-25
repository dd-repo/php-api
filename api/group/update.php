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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change, rename</li>
	<li><h2>Description :</h2> changes the name of a group</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>group : The new name of the group. <span class=\"required\">required</span>. (alias : name, group_name)</li>
			<li>id : The id of the group to rename. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : gid, group_id)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GROUP_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GROUP_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$group = request::getCheckParam(array(
	'name'=>array('group', 'name', 'group_name'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE
	));
$gid = request::getCheckParam(array(
	'name'=>array('id', 'group_id', 'gid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER,
	'action'=>true
	));

// =================================
// EXECUTE QUERY
// =================================
$sql = "UPDATE groups SET group_name='".security::escape($group)."' WHERE group_id={$gid}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>