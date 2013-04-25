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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/bill/help\">bill</a> :: insert</h1>
<ul>
	<li><h2>Alias :</h2> create, add</li>
	<li><h2>Description :</h2> creates a new bill</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>service : The service ID of the bill. <span class=\"optional\">optional</span>. (alias : bill_service)</li>
			<li>number : Services number. <span class=\"optional\">optional</span>.</li>
			<li>from : From date. <span class=\"optional\">optional</span>.</li>
			<li>to : To date. <span class=\"optional\">optional</span>.</li>
			<li>vat :  Bill vat (default 19.6). <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the newly created bill {'id'}</li>
	<li><h2>Required grants :</h2> ACCESS, BILL_INSERT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'BILL_INSERT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$service = request::getCheckParam(array(
	'name'=>array('service', 'bill_service'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$number = request::getCheckParam(array(
	'name'=>array('number'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>10,
	'match'=>request::NUMBER
	));
$vat = request::getCheckParam(array(
	'name'=>array('vat'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>10,
	'match'=>request::NUMBER|request::PUNCT
	));
$from = request::getCheckParam(array(
	'name'=>array('from'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$to = request::getCheckParam(array(
	'name'=>array('to'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));	
	
if( $number == null )
	$number = 1;
if( $vat == null )
	$vat = 19.6;
	
// =================================
// GET USER DATA
// =================================
$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
$userdata = $GLOBALS['db']->query($sql);
if( $userdata == null || $userdata['user_ldap'] == null )
	throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

// =================================
// INSERT BILL
// =================================
$sql = "INSERT INTO bills (bill_user,bill_date,bill_vat,bill_from,bill_to) VALUES ({$userdata['user_id']},".time().",'{$vat}','{$from}','{$to}')";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

$id = $GLOBALS['db']->last_id();

// =================================
// INSERT SERVICES
// =================================
if( $service !== null )
{
	$sql = "INSERT INTO bill_service (bill_id,service_id,service_count) VALUES({$id},{$service},{$number})";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
}

responder::send(array("id"=>$id));

?>