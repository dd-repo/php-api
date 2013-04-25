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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a quota</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>quota : The name or id of the quota to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : quota, name, quota_name, quotas, names, quota_names, id, quota_id, qid, ids, quota_ids, qids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching quotas [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'name', 'quota_name', 'quotas', 'names', 'quota_names', 'id', 'quota_id', 'qid', 'ids', 'quota_ids', 'qids'),
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
if( $quota !== null && count($quota) > 0 )
{
	foreach( $quota as $q )
	{
		if( is_numeric($q) )
		{
			if( strlen($where_id) == 0 ) $where_id = ' OR q.quota_id IN(-1';
			$where_id .= ','.$q;
		}
		else
		{
			if( strlen($where_name) == 0 ) $where_name = '';
			$where_name .= " OR q.quota_name LIKE '%".security::escape($q)."%'";
		}
	}
	if( strlen($where_id) > 0 ) $where_id .= ')';
}
else
	$where_name = " OR true";

// =================================
// SELECT RECORDS
// =================================
$sql = "SELECT q.quota_id, q.quota_name FROM quotas q WHERE false {$where_name} {$where_id}";
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$quotas = array();
foreach( $result as $r )
	$quotas[] = array('name'=>$r['quota_name'], 'id'=>$r['quota_id']);

responder::send($quotas);

?>