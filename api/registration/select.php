<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('select', 'list', 'view', 'search'));
$a->setDescription("Searches for a pending registration");
$a->addGrant(array('ACCESS', 'REGISTRATION_SELECT'));
$a->setReturn(array(array(
	'user'=>'the user login', 
	'email'=>'the email of the target registration', 
	'date'=>'the date of the ticket'
	)));
$a->addParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>6,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$",
	));
$a->addParam(array(
	'name'=>array('code', 'validation'),
	'description'=>'The validation code.',
	'optional'=>true,
	'minlength'=>32,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}"
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
	$mail = $a->getParam('mail');
	$code = $a->getParam('code');
	
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $mail !== null )
		$where .= " AND register_email LIKE '%".security::escape($mail)."%'";
	if( $code !== null )
		$where .= " AND register_code = '".security::escape($code)."'";

	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT register_email, register_date
			FROM register
			WHERE true {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// FORMAT RESULT
	// =================================
	$registrations = array();
	foreach( $result as $r )
		$registrations[] = array('email'=>$r['register_email'], 'date'=>$r['register_date']);

	responder::send($registrations);
});

return $a;	

?>