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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the grant to remove. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : name, grant_name, id, grant_id, gid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant', 'name', 'grant_name', 'id', 'grant_id', 'gid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'action'=>true
	));

// =================================
// DELETE LOCAL GRANT
// =================================
if( is_numeric($grant) )
	$where = "grant_id = ".$grant;
else
	$where = "grant_name = '".security::escape($grant)."'";

$sql = "DELETE FROM grants WHERE {$where}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>