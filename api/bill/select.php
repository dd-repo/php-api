<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a bill");
$a->addGrant(array('ACCESS', 'BILL_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the bill', 
	'name'=>'the name of the bill', 
	'reference'=>'reference of the bill',
	'data'=>'date of the bill',
	'lines'=>array(
		'id'=>'id of the line',
		'title'=>'title of the line'
	),
	'user'=>array(
		'id'=>'the user id', 
		'name'=>'the username'
	)
	)));
	
$a->addParam(array(
	'name'=>array('bill', 'bill_id', 'id', 'bid'),
	'description'=>'The id of the bill to select.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::NUMBER,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
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
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $bill !== null )
		$where .= " AND b.bill_id = '{$bill}'";
	if( $user !== null )
	{
		if( is_numeric($user) )
			$where .= " AND u.user_id = " . $user;
		else
			$where .= " AND u.user_name = '".security::escape($user)."'";
	}
	
	// =================================
	// SELECT REMOTE ENTRIES
	// =================================
	$sql = "SELECT b.bill_id, b.bill_real_id, b.bill_name, b.bill_ref, b.bill_user, b.bill_date, b.bill_status, b.bill_amount_et, b.bill_amount_ati, u.user_name, u.user_id 
			FROM bills b
			LEFT JOIN users u ON(u.user_id = b.bill_user)
			WHERE true {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// FORMAT RESULT
	// =================================
	$bills = array();
	foreach( $result as $r )
	{
		$sql = "SELECT line_id, line_bill, line_name, line_description, line_vat, line_amount_et, line_amount_ati, line_plan FROM bill_line WHERE line_bill = {$r['bill_id']}";
		$lines = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
		
		foreach( $lines as $l )
			$bill_lines = array('id'=>$l['line_id'], 'name'=>$l['line_name'], 'description'=>$l['line_description'], 'vat'=>$l['line_vat'], 'amount_et'=>$l['line_amount_et'], 'amount_ati'=>$l['line_amount_ati'], 'plan'=>$l['line_plan']);
		
		$bills[] = array(
			'id' => $r['bill_id'],
			'rid' => $r['bill_real_id'],
			'name' => $r['bill_name'],
			'reference' => $r['bill_ref'],
			'status' => $r['bill_status'],
			'date' => $r['bill_date'],
			'amount_et' => $r['bill_amount_et'],
			'amount_ati' => $r['bill_amount_ati'],
			'user' => array('name'=> $r['user_name'], 'id'=>$r['user_id']),
			'lines' => $bill_lines
		);
	}
	
	responder::send($bills);
});

return $a;

?>