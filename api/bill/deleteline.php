<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('insertline', 'addline'));
$a->setDescription("Add a line to a bill");
$a->addGrant(array('ACCESS', 'BILL_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('bill', 'bill_id', 'id', 'bid'),
	'description'=>'The id of the bill to modify.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::NUMBER,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('line', 'line_id', 'lid'),
	'description'=>'The id of the line to delete.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::NUMBER,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
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
	$line = $a->getParam('line');
	$user = $a->getParam('user');
	
	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	}
	
	// =================================
	// UPDATE BILL
	// =================================
	$where = '';
	if( $user !== null )
		$where .= " AND bill_user = {$userdata['user_id']}";
		
	$amount_ati = $amount+($amount*($vat/100));
	$amount_ati = round($amount_ati, 2);
	
	if( $plan === null )
		$plan = 0;
	
	$sql = "DELETE FROM bill_line WHERE line_id = {$line}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	$sql = "SELECT line_amount_ati, line_amount_et FROM bill_line WHERE line_bill = {$bill}";
	$lines = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$total_ati = 0;
	$total_et = 0;
	
	foreach( $lines as $l )
	{
		$total_ati = $total_ati+$l['line_amount_ati'];
		$total_et = $total_et+$l['line_amount_et'];
	}
	
	$sql = "UPDATE bills SET bill_amount_et = '{$total_et}', bill_amount_ati = '{$total_ati}' WHERE bill_id = {$bill}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	responder::send("OK");
});

return $a;

?>