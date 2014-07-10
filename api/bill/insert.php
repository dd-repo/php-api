<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new bill");
$a->addGrant(array('ACCESS', 'BILL_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the bill'
	)));

$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
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
	$user = $a->getParam('user');
	
	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_name, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// INSERT BILL
	// =================================
	$time = strtotime("last day of previous month");
	$sql = "INSERT INTO bills (bill_user, bill_date) VALUES ({$userdata['user_id']}, '{$time}')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	$uid = $GLOBALS['db']->last_id();
	$formatuid = str_pad($uid, 6, '0', STR_PAD_LEFT);
	
	$year = date('Y', $time);
	$month = date('F', $time);
	
	$sql = "UPDATE bills SET bill_name = 'CO-AS{$year}-{$formatuid}', bill_ref = '{$userdata['user_name']} ({$month} {$year})' WHERE bill_id = {$uid}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('bill/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array("id"=>$uid));
});

return $a;

?>