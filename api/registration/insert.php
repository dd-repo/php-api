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
	$mail = $a->getParam('mail');
	
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
			WHERE register_email = '".security::escape($mail)."'";
	$result = $GLOBALS['db']->query($sql);

	if( $result !== null || $result['user_id'] !== null )
		throw new ApiException("Pending request already exists", 412, "Existing pending request for : {$user} : {$mail}");

	// =================================
	// CHECK FOR USER WITH THE SAME EMAIL
	// =================================		
	$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::USER, "(mailForwardingAddress={$mail})"));
	
	if( count($result) > 0 )
		throw new ApiException("Email already exists", 412, "Existing email : {$mail}");
	
	// =================================
	// INSERT PENDING REQUEST
	// =================================
	$code = md5($user.$email.time());
	$sql = "INSERT INTO register (register_email, register_code, register_date)
			VALUES ('".security::escape($mail)."', '{$code}', UNIX_TIMESTAMP())";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	responder::send(array("code"=>$code));
});

return $a;

?>