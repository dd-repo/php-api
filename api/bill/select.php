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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/object/help\">bill</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a bill</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>id : The id of the bill to search for. <span class=\"optional\">optional</span>. (alias : bill)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>from : From date. <span class=\"optional\">optional</span>.</li>
			<li>to : To date. <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching bills [{'id', 'date', 'total_ic', 'total_ec', 'services'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, BILL_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'BILL_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$bill = request::getCheckParam(array(
	'name'=>array('bill', 'id'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>200,
	'match'=>request::NUMBER
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
	
$bills = array();
	
responder::send($bills);

?>