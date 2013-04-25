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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new group</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>group : The name of the new group. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, group_name)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created group {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, GROUP_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GROUP_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$group = request::getCheckParam(array(
	'name'=>array('group', 'name', 'group_name'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE,
	'action'=>true
	));

if( is_numeric($group) )
	throw new ApiException("Parameter validation failed", 412, "Parameter group may not be numeric : " . $group);

// =================================
// CHECK IF GROUP EXISTS
// =================================
$sql = "SELECT group_id FROM groups WHERE group_name = '".security::escape($group)."'";
$result = $GLOBALS['db']->query($sql);

if( $result !== null && $result['group_id'] !== null )
	throw new ApiException("Group already exists", 412, "Existing local group : " . $group);

// =================================
// INSERT GROUP
// =================================
$sql = "INSERT INTO groups (group_name) VALUES ('".security::escape($group)."')";
$GLOBALS['db']->query($sql, mysql::NO_ROW);
$gid = $GLOBALS['db']->last_id();

responder::send(array("name"=>$group, "id"=>$gid));

?>