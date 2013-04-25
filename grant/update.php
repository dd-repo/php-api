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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change, rename</li>
	<li><h2>Description :</h2> changes the name of a grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The new name of the grant. <span class=\"required\">required</span>. (alias : name, grant_name)</li>
			<li>id : The id of the grant to rename. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : gid, grant_id)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant', 'name', 'grant_name'),
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT
	));
$gid = request::getCheckParam(array(
	'name'=>array('id', 'grant_id', 'gid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER,
	'action'=>true
	));

// =================================
// UPDATE LOCAL GRANT
// =================================
$sql = "UPDATE grants SET grant_name='".security::escape($grant)."' WHERE grant_id={$gid}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>