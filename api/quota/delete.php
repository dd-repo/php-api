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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a quota</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>quota : The name or id of the quota to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, quota_name, id, quota_id, qid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'name', 'quota_name', 'id', 'quota_id', 'qid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'action'=>true
	));

// =================================
// DELETE LOCAL QUOTA
// =================================
if( is_numeric($quota) )
	$where = "quota_id = ".$quota;
else
	$where = "quota_name = '".security::escape($quota)."'";

$sql = "DELETE FROM quotas WHERE {$where}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>