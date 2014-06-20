<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes an bill");
$a->addGrant(array('ACCESS', 'BILL_DELETE'));
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
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
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
	$user = $a->getParam('user');
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT b.bill_id, b.bill_user
				FROM users u
				LEFT JOIN bills b ON(b.bill_user = u.user_id)
				WHERE bill_id = '".security::escape($bill)."'
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['bill_id'] == null )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the bill {$bill}");
	}
	else
	{
		$sql = "SELECT b.bill_id, b.bill_user
				FROM bills b
				LEFT JOIN users u ON(u.user_id = b.bill_user)
				WHERE bill_id = '".security::escape($bill)."'";
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['bill_id'] == null )
			throw new ApiException("Forbidden", 403, "Bill {$bill} does not exist");
	}
	
	// =================================
	// DELETE BILL
	// =================================
	$sql = "DELETE FROM bills WHERE bill_id = {$bill} AND bill_status = 0";	
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('bill/delete', $a->getParams(), $result['bill_user']);
	
	responder::send("OK");
});

return $a;

?>