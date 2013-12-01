<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new account");
$a->addGrant(array('ACCESS', 'ACCOUNT_INSERT'));
$a->setReturn(array(array(
	'name'=>'the account name'
	)));
$a->addParam(array(
	'name'=>array('name', 'account', 'account_name'),
	'description'=>'The name of the new account.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that account belong to.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));	
$a->addParam(array(
	'name'=>array('pass', 'password'),
	'description'=>'The password of the account.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('firstname', 'givenname', 'first_name'),
	'description'=>'The first name of the new account.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('lastname', 'sn', 'user_lastname'),
	'description'=>'The last name of the account.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('redirection', 'email'),
	'description'=>'The email address for mail redirection.',
	'optional'=>true,
	'minlength'=>0,
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
	$account = $a->getParam('account');
	$domain = $a->getParam('domain');
	$pass = $a->getParam('pass');
	$user = $a->getParam('user');
	$firstname = $a->getParam('firstname');
	$lastname = $a->getParam('lastname');
	$redirection = $a->getParam('redirection');
	
	if( is_numeric($account) )
		throw new ApiException("Parameter validation failed", 412, "Parameter account may not be numeric : " . $account);

	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_name FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
		
	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);

	// =================================
	// CHECK IF REMOTE ACCOUNT EXISTS
	// =================================
	try
	{
		$dn = ldap::buildDN(ldap::USER, $domain, $account);
		$result = $GLOBALS['ldap']->read($dn);
		
		// this should throw a 404 if the user does NOT exist
		throw new ApiException("Account already exists", 412, "Existing remote account : " . $account);
	}
	catch(Exception $e)
	{
		// if this is not the 404 we expect, rethrow it
		if( !($e instanceof ApiException) || !preg_match("/Entry not found/s", $e.'') )
			throw $e;
	}

	// =================================
	// INSERT REMOTE ACCOUNT
	// =================================
	$dn = ldap::buildDN(ldap::USER, $domain, $account);
	$params = array('dn' => $dn, 'uid' => $account, 'userPassword' => $pass, 'domain' => $domain, 'owner'=>$user_dn);
	
	if( $firstname !== null )
		$params['givenName'] = $firstname;
	if( $lastname !== null )
		$params['sn'] = $lastname;
	if( $redirection !== null )
		$params['mailForwardingAddress'] = $redirection;
	
	$handler = new user();
	$data = $handler->build($params);
	
	$result = $GLOBALS['ldap']->create($dn, $data);

	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::ADD);
	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$commands[] = "mkdir -p {$data['homeDirectory']} && chown {$data['uidNumber']}:{$data['gidNumber']} {$data['homeDirectory']} && chmod 750 {$data['homeDirectory']}";
	$GLOBALS['system']->exec($commands);
	
	responder::send(array("name"=>$account));
});

return $a;

?>