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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2>  searches for a group</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>group : The name or id of the group to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : name, group_name, groups, names, group_names, id, group_id, gid, ids, group_ids, gids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching groups [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, GROUP_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GROUP_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$group = request::getCheckParam(array(
	'name'=>array('group', 'name', 'group_name', 'groups', 'names', 'group_names', 'id', 'group_id', 'gid', 'ids', 'group_ids', 'gids'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$where_name = '';
$where_id = '';
if( $group !== null && count($group) > 0 )
{
	foreach( $group as $g )
	{
		if( is_numeric($g) )
		{
			if( strlen($where_id) == 0 ) $where_id = ' OR g.group_id IN(-1';
			$where_id .= ','.$g;
		}
		else
		{
			if( strlen($where_name) == 0 ) $where_name = '';
			$where_name .= " OR g.group_name LIKE '%".security::escape($g)."%'";
		}
	}
	if( strlen($where_id) > 0 ) $where_id .= ')';
}
else
	$where_name = " OR true";

// =================================
// EXECUTE QUERY
// =================================
$sql = "SELECT g.group_id, g.group_name
		FROM groups g
		WHERE false {$where_name} {$where_id}
		GROUP BY g.group_id";
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$groups = array();
foreach( $result as $r )
	$groups[] = array('name'=>$r['group_name'], 'id'=>$r['group_id']);
	
responder::send($groups);

?>