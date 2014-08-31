<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new user");
$a->addGrant(array('ACCESS', 'USER_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the user', 
	'name'=>'the user login'
	)));
$a->addParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user'),
	'description'=>'The name of the new user.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('pass', 'password', 'user_password', 'user_pass'),
	'description'=>'The password of the user.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('firstname', 'givenname', 'first_name', 'user_firstname', 'user_givenname', 'user_first_name', 'user_given_name'),
	'description'=>'The first name of the new user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('lastname', 'sn', 'user_lastname', 'user_sn', 'user_last_name'),
	'description'=>'The last name of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'user_email', 'user_mail'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));
$a->addParam(array(
	'name'=>array('language', 'lang'),
	'description'=>'The user language.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>2,
	'match'=>request::UPPER
	));
$a->addParam(array(
	'name'=>array('ip'),
	'description'=>'IP address of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('plan'),
	'description'=>'The user plan.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('plan_type'),
	'description'=>'The user plan type (storage || memory).',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('iban'),
	'description'=>'The user plan.',
	'optional'=>true,
	'minlength'=>10,
	'maxlength'=>150,
	'match'=>request::LOWER|request::UPPER|request::NUMBER
	));
$a->addParam(array(
	'name'=>array('bic'),
	'description'=>'The user BIC.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::UPPER|request::NUMBER
	));
$a->addParam(array(
	'name'=>array('postal_address', 'address', 'user_address'),
	'description'=>'The postal address of the user (JSON encoded).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>500,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('postal_code', 'code'),
	'description'=>'The postal code of the user.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>5,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('organisation', 'o'),
	'description'=>'The organisation of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('locality', 'l', 'city'),
	'description'=>'The city of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('status', 'user_status'),
	'description'=>'The user status.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('report'),
	'description'=>'Receive reports?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('zabbix'),
	'description'=>'The user zabbix id.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('key', 'ssh'),
	'description'=>'The SSH key',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1000,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('mode'),
	'description'=>'Mode for alternate or redirection email (can be add/delete).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>6,
	'match'=>"(add|delete)"
	));
$a->addParam(array(
	'name'=>array('billing'),
	'description'=>'Billing?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
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
	$pass = $a->getParam('pass');
	$firstname = $a->getParam('firstname');
	$lastname = $a->getParam('lastname');
	$mail = $a->getParam('mail');
	$ip = $a->getParam('ip');
	$language = $a->getParam('language');
		
	if( is_numeric($user) )
		throw new ApiException("Parameter validation failed", 412, "Parameter user may not be numeric : " . $user);

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
	// INSERT REMOTE USER
	// =================================
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $user);
	$params = array('dn' => $dn, 'uid' => $user, 'userPassword' => $pass, 'domain' => $GLOBALS['CONFIG']['DOMAIN']);
	
	if( $firstname !== null )
		$params['givenName'] = $firstname;
	if( $lastname !== null )
		$params['sn'] = $lastname;
	if( $mail !== null )
		$params['mailForwardingAddress'] = $mail;
	if( $ip !== null )
		$params['ipHostNumber'] = security::escape($ip);
	if( $language !== null )
		$params['gecos'] = $language;
		
	$handler = new user();
	$data = $handler->build($params);
	
	$result = $GLOBALS['ldap']->create($dn, $data);
	
	// =================================
	// INSERT LOCAL USER
	// =================================
	$sql = "INSERT INTO users (user_name, user_ldap, user_date) VALUES ('".security::escape($user)."', {$data['uidNumber']}, ".time().")";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	$uid = $GLOBALS['db']->last_id();

	// =================================
	// INSERT FIRST PLAN
	// =================================
	//$sql = "INSERT INTO user_plan (plan_id, user_id, plan_start_date) VALUES (1, {$uid}, ".time().")";
	//$GLOBALS['db']->query($sql, mysql::NO_ROW);	
	
	// =================================
	// INSERT PIWIK USER
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.addUser&userLogin={$user}&password={$pass}&email={$user}@{$GLOBALS['CONFIG']['DOMAIN']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);

	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$command = "mkdir -p {$data['homeDirectory']} && chown {$data['uidNumber']}:{$data['gidNumber']} {$data['homeDirectory']} && chmod 750 {$data['homeDirectory']}";
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('user/insert', $a->getParams(), $uid);
	
	responder::send(array("name"=>$user, "id"=>$uid));
});

return $a;

?>
