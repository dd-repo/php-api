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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: <a href=\"/grant/group/help\">group</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search, check, verify</li>
	<li><h2>Description :</h2> lists all grants of a group or all groups of a grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the grants to search for. <span class=\"optional\">optional</span>. (alias : grant_name, grant_id, kid)</li>
			<li>group : The name or id of the group to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : group_name, group_id, gid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching groups or grants [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_GROUP_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_GROUP_SELECT'));

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
$group = request::getCheckParam(array(
	'name'=>array('group', 'group_name', 'group_id', 'gid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE,
	'action'=>true
	));

if( $grant !== null && $group !== null )
	throw new ApiException("Too many parameters", 412, "Cannot specify both grant ({$grant}} and group ({$group})");
if( $grant === null && $group === null )
	throw new ApiException("Missing conditional parameter", 412, "Cannot specify none of grant and group");

// =================================
// EXECUTE QUERY FOR GRANT
// =================================
if( $grant !== null )
{
	if( is_numeric($grant) )
		$where = "k.grant_id=".$grant;
	else
		$where = "k.grant_name = '".security::escape($grant)."'";
	
	$sql = "SELECT g.group_name, g.group_id
			FROM grants k
			LEFT JOIN group_grant gk ON(k.grant_id = gk.grant_id)
			LEFT JOIN groups g ON(g.group_id = gk.group_id)
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
	
	$sql = "SELECT k.grant_name, k.grant_id
			FROM groups g
			LEFT JOIN group_grant gk ON(g.group_id = gk.group_id)
			LEFT JOIN grants k ON(k.grant_id = gk.grant_id)
			WHERE {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$grants = array();
	foreach( $result as $r )
		if( $r['grant_id'] != null )
			$grants[] = array('name'=>$r['grant_name'], 'id'=>$r['grant_id']);
	
	responder::send($grants);
}

?>