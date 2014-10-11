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
	'name'=>array('date', 'bill_date'),
	'description'=>'Timestamp of the bill',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::NUMBER,
	));
$a->addParam(array(
	'name'=>array('type', 'bill_type'),
	'description'=>'The type of the bill',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>1,
	'match'=>request::NUMBER,
	));
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
	$date = $a->getParam('date');
	$type = $a->getParam('type');
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
	if( $date != null )
		$time = $date;
	else
		$time = time();
	
	$year = date('Y', $time);
	$month = date('F', $time);
	
	if( $type != null )
	{
		switch( $type )
		{
			case 1:
				$ref = "{$userdata['user_name']} ({$month} {$year})";
			break;
			case 2:
				$ref = "{$userdata['user_name']} ({$month} {$year})";
			break;
			case 3:
				$ref = "{$userdata['user_name']} ({$year})";
			break;
		}
	}
	else
		$ref = "{$userdata['user_name']} ({$month} {$year})";
		
	$sql = "INSERT INTO bills (bill_user, bill_date) VALUES ({$userdata['user_id']}, '{$time}')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	$uid = $GLOBALS['db']->last_id();
	$formatuid = str_pad($uid, 6, '0', STR_PAD_LEFT);
	
	$sql = "UPDATE bills SET bill_name = 'CO-AS{$year}-{$formatuid}', bill_ref = '{$ref}' WHERE bill_id = {$uid}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('bill/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array("id"=>$uid));
});

return $a;

?>