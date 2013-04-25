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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change, rename</li>
	<li><h2>Description :</h2> changes the name of a quota</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>quota : The new name of the quota. <span class=\"required\">required</span>. (alias : name, quota_name)</li>
			<li>id : The id of the quota to rename. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : qid, quota_id)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'name', 'quota_name'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT
	));
$qid = request::getCheckParam(array(
	'name'=>array('id', 'quota_id', 'qid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER,
	'action'=>true
	));

// =================================
// UPDATE LOCAL QUOTA
// =================================
$sql = "UPDATE quotas SET quota_name='".security::escape($quota)."' WHERE quota_id={$qid}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>