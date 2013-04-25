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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/bill/help\">bill</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> del, remove, destroy</li>
	<li><h2>Description :</h2> removes a bill</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>id : The id of the bill to remove. <span class=\"required\">required</span>. (alias : bill)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, BILL_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'BILL_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$bill = request::getCheckParam(array(
	'name'=>array('id', 'bill'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));

// =================================
// EXECUTE QUERY
// =================================
$sql = "DELETE FROM bills WHERE bill_id = {$bill}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

responder::send("OK");

?>