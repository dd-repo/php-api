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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name of the new grant. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, grant_name)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created grant {'name', 'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant', 'name', 'grant_name'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'action'=>true
	));
	
if( is_numeric($grant) )
	throw new ApiException("Parameter validation failed", 412, "Parameter grant may not be numeric : " . $grant);

// =================================
// CHECK IF GRANT EXISTS
// =================================
$sql = "SELECT grant_id FROM grants WHERE grant_name = '".security::escape($grant)."'";
$result = $GLOBALS['db']->query($sql);

if( $result !== null && $result['grant_id'] !== null )
	throw new ApiException("Grant already exists", 412, "Existing local grant : " . $grant);

// =================================
// INSERT GRANT
// =================================
$sql = "INSERT INTO grants (grant_name) VALUES ('".security::escape($grant)."')";
$GLOBALS['db']->query($sql, mysql::NO_ROW);
$gid = $GLOBALS['db']->last_id();

responder::send(array("name"=>$grant, "id"=>$gid));

?>