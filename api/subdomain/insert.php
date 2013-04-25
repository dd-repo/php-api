<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new subdomain");
$a->addGrant(array('ACCESS', 'SUBDOMAIN_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the subdomain', 
	'name'=>'the subdomain name'
	)));
$a->addParam(array(
	'name'=>array('subdomain', 'name'),
	'description'=>'The new subdomain name.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the new domain.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
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
	$subdomain = $a->getParam('subdomain');
	$domain = $a->getParam('domain');
	$user = $a->getParam('user');
	
	if( is_numeric($subdomain) )
		throw new ApiException("Parameter validation failed", 412, "Parameter subdomain may not be numeric : " . $subdomain);

	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	// =================================
	// CHECK IF REMOTE SUBDOMAIN EXISTS
	// =================================
	try
	{
		$dn = ldap::buildDN(ldap::SUBDOMAIN, $domain, $subdomain);
		$result = $GLOBALS['ldap']->read($dn);
		
		// this should throw a 404 if the user does NOT exist
		throw new ApiException("Subdomain already exists", 412, "Existing remote subdomain : " . $subdomain);
	}
	catch(Exception $e)
	{
		// if this is not the 404 we expect, rethrow it
		if( !($e instanceof ApiException) || !preg_match("/Entry not found/s", $e.'') )
			throw $e;
	}

	// =================================
	// INSERT REMOTE SUBDOMAIN
	// =================================
	$dn = ldap::buildDN(ldap::SUBDOMAIN, $domain, $subdomain);
	$parts = explode('.', $subdomain);
	$params = array('dn' => $dn, 'subdomain' => $subdomain, 'uid' => $parts[0], 'domain' => $domain, 'owner' => $user_dn);
	
	$handler = new subdomain();
	$data = $handler->build($params);
	
	$result = $GLOBALS['ldap']->create($dn, $data);
	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$GLOBALS['system']->create(system::SUBDOMAIN, $data);
	
	responder::send(array("name"=>$subdomain, "id"=>$result['uidNumber']));
});

return $a;

?>