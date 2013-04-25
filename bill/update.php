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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/bill/help\">bill</a> :: update</h1>
<ul>
	<li><h2>Alias :</h2> modify, change</li>
	<li><h2>Description :</h2> update a bill</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>bill : The ID of the bill to update. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. (alias : bill_id, id)</li>
			<li>status : The new status of the bill. <span class=\"optional\">optional</span>.</li>
			<li>service : The service ID. <span class=\"optional\">optional</span>. (alias : bill_service)</li>
			<li>name : The service name. <span class=\"optional\">optional</span>. (alias : service_name)</li>
			<li>desc : The service description. <span class=\"optional\">optional</span>. (alias : service_desc)</li>
			<li>price : The service price. <span class=\"optional\">optional</span>.</li>
			<li>vat : The service VAT. <span class=\"optional\">optional</span>.</li>
			<li>number : Services number. <span class=\"optional\">optional</span>.</li>
			<li>operation : [1 (default) = add | 2 = delete] <span class=\"optional\">optional</span>.</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, BILL_UPDATE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'BILL_UPDATE'));

// =================================
// GET PARAMETERS
// =================================
$bill = request::getCheckParam(array(
	'name'=>array('bill', 'bill_id', 'id'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::NUMBER,
	'action'=>true
	));
$status = request::getCheckParam(array(
	'name'=>array('status'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));
$service = request::getCheckParam(array(
	'name'=>array('service', 'bill_service'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$name = request::getCheckParam(array(
	'name'=>array('name', 'service_name'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL|request::PUNCT
	));
$desc = request::getCheckParam(array(
	'name'=>array('desc', 'service_desc'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL|request::PUNCT
	));
$price = request::getCheckParam(array(
	'name'=>array('price', 'service_price'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::NUMBER|request::PUNCT
	));
$vat = request::getCheckParam(array(
	'name'=>array('vat', 'service_vat'),
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200,
	'match'=>request::NUMBER|request::PUNCT
	));
$number = request::getCheckParam(array(
	'name'=>array('number'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>10,
	'match'=>request::NUMBER
	));
$operation = request::getCheckParam(array(
	'name'=>array('operation'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1,
	'match'=>request::NUMBER
	));

if( $operation == null )
	$operation = 1;

// =================================
// UPDATE BILL
// =================================
$sql = "UPDATE bills SET bill_status = '{$status}' WHERE bill_id = {$bill}";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

if( $operation == 1 )
{
	$sql = "INSERT INTO bill_service (bill_id,service_id,service_count,service_name,service_description,service_amount,service_vat) VALUES('{$bill}','{$service}','{$number}', '{$name}','{$desc}','{$price}', '{$vat}')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
}

if( $operation == 2 )
{
	$sql = "DELETE FROM bill_service WHERE bill_id = {$bill} AND id = {$service}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
}

responder::send("OK");

?>