<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('join', 'register', 'signup', 'subscribe', 'insert', 'create', 'add'));
$a->setDescription("Insert a new registration ticket");
$a->addGrant(array('ACCESS', 'REGISTRATION_INSERT'));
$a->setReturn(array(array(
	'code'=>'the registration ephmeral token', 
	)));
$a->addParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user'),
	'description'=>'The name of the target user.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('plan'),
	'description'=>'The name of the target user.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>2,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'description'=>'The email of the user.',
	'optional'=>false,
	'minlength'=>6,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
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
	$plan = $a->getParam('plan');
	$mail = $a->getParam('mail');
	
	if( $plan === null )
		$plan = 0;
		
	// =================================
	// CLEANUP TOO OLD REQUESTS
	// =================================
	// > 10 days old
	$sql = "DELETE FROM register WHERE register_date < (UNIX_TIMESTAMP() - 864000)";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// CHECK IF PENDING REQUEST EXISTS
	// =================================
	$sql = "SELECT register_id FROM register 
			WHERE register_user = '".security::escape($user)."'
			OR register_email = '".security::escape($mail)."'";
	$result = $GLOBALS['db']->query($sql);

	if( $result !== null || $result['user_id'] !== null )
		throw new ApiException("Pending request already exists", 412, "Existing pending request for : {$user} : {$mail}");

	// =================================
	// CHECK IF LOCAL USER EXISTS
	// =================================
	$sql = "SELECT user_id FROM users WHERE user_name = '".security::escape($user)."'";
	$result = $GLOBALS['db']->query($sql);

	if( $result !== null || $result['user_id'] !== null )
		throw new ApiException("User already exists", 412, "Existing local user : " . $user);

	// =================================
	// CHECK IF REMOTE USER EXISTS
	// =================================
	try
	{
		$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $user);
		$result = $GLOBALS['ldap']->read($dn);
		
		// this should throw a 404 if the user does NOT exist
		throw new ApiException("User already exists", 412, "Existing remote user : " . $user);
	}
	catch(Exception $e)
	{
		// if this is not the 404 we expect, rethrow it
		if( !($e instanceof ApiException) || !preg_match("/Entry not found/s", $e.'') )
			throw $e;
	}

	// =================================
	// INSERT PENDING REQUEST
	// =================================
	$code = md5($user.$email.time());
	$sql = "INSERT INTO register (register_user, register_email, register_code, register_date, register_plan)
			VALUES ('".security::escape($user)."', '".security::escape($mail)."', '{$code}', UNIX_TIMESTAMP(), '{$plan}')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	responder::send(array("code"=>$code));
});

return $a;

?>