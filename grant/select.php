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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the grant to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : name, grant_name, grants, names, grant_names, id, grant_id, gid, ids, grant_ids, gids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching grants [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant', 'name', 'grant_name', 'grants', 'names', 'grant_names', 'id', 'grant_id', 'gid', 'ids', 'grant_ids', 'gids'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$where_name = '';
$where_id = '';
if( $grant !== null && count($grant) > 0 )
{
	foreach( $grant as $g )
	{
		if( is_numeric($g) )
		{
			if( strlen($where_id) == 0 ) $where_id = ' OR k.grant_id IN(-1';
			$where_id .= ','.$g;
		}
		else
		{
			if( strlen($where_name) == 0 ) $where_name = '';
			$where_name .= " OR k.grant_name LIKE '%".security::escape($g)."%'";
		}
	}
	if( strlen($where_id) > 0 ) $where_id .= ')';
}
else
	$where_name = " OR true";

// =================================
// SELECT RECORDS
// =================================
$sql = "SELECT k.grant_id, k.grant_name
		FROM grants k
		WHERE false {$where_name} {$where_id}
		ORDER BY k.grant_name";
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$grants = array();
foreach( $result as $r )
	$grants[] = array('name'=>$r['grant_name'], 'id'=>$r['grant_id']);

responder::send($grants);

?>