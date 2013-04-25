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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new quota</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>quota : The name of the new quota. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, quota_name)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created quota {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'name', 'quota_name'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'action'=>true
	));
	
if( is_numeric($quota) )
	throw new ApiException("Parameter validation failed", 412, "Parameter quota may not be numeric : " . $quota);

// =================================
// CHECK IF QUOTA EXISTS
// =================================
$sql = "SELECT quota_id FROM quotas WHERE quota_name = '".security::escape($quota)."'";
$result = $GLOBALS['db']->query($sql);

if( $result !== null && $result['quota_id'] !== null )
	throw new ApiException("Quota already exists", 412, "Existing local quota : " . $quota);

// =================================
// INSERT QUOTA
// =================================
$sql = "INSERT INTO quotas (quota_name) VALUES ('".security::escape($quota)."')";
$GLOBALS['db']->query($sql, mysql::NO_ROW);
$gid = $GLOBALS['db']->last_id();

responder::send(array("name"=>$quota, "id"=>$gid));

?>