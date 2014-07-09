<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a bill");
$a->addGrant(array('ACCESS', 'BILL_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('bill', 'bill_id', 'id', 'bid'),
	'description'=>'The id of the bill to remove.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::NUMBER,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('status', 'bill_status'),
	'description'=>'The status of the bill',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>1,
	'match'=>request::NUMBER,
	));
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$bill = $a->getParam('bill');
	$status = $a->getParam('status');
	$user = $a->getParam('user');
	
	// =================================
	// UPDATE BILL
	// =================================
	$sql = "UPDATE bills SET bill_status = '{$status}' WHERE bill_id = {$bill}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	if( $status == 2 )
	{
		$sql = "SELECT bill_real_id FROM bills WHERE 1 ORDER BY bill_real_id DESC";
		$info = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		
		$uid = $info['bill_real_id']+1;
		$formatuid = str_pad($uid, 4, '0', STR_PAD_LEFT);
		$year = date('Y');
		
		$sql = "UPDATE bills SET bill_real_id = {$uid}, bill_name = 'BI{$year}-{$formatuid}' WHERE bill_id = {$uid}";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
	responder::send("OK");
});

?>