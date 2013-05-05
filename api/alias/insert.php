<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new alias");
$a->addGrant(array('ACCESS', 'DOMAIN_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the domain', 
	'domain'=>'the domain name'
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name', 'domain_id', 'id'),
	'description'=>'The name of the new domain.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('source', 'source_name', 'source_id', 'id'),
	'description'=>'The name of the new domain.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('type'),
	'description'=>'Type of alias.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>15,
	'match'=>"(transparent|permanent)",
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'description' => 'The name or id of the target user.',
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
	$domain = $a->getParam('domain');
	$source = $a->getParam('source');
	$type = $a->getParam('type');
	$user = $a->getParam('user');
	
	if( is_numeric($domain) )
		throw new ApiException("Parameter validation failed", 412, "Parameter domain may not be numeric : " . $domain);

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
	// SELECT REMOTE SOURCE
	// =================================
	if( is_numeric($source) )
		$dn = $GLOBALS['ldap']->getDNfromUID($source);
	else
		$dn = ldap::buildDN(ldap::DOMAIN, $source);
		
	$source_data = $GLOBALS['ldap']->read($dn);
		
	// =================================
	// CHECK QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	checkQuota('DOMAINS', $user);

	// =================================
	// CHECK IF REMOTE DOMAIN EXISTS
	// =================================
	try
	{
		$dn = ldap::buildDN(ldap::DOMAIN, $domain);
		$result = $GLOBALS['ldap']->read($dn);
		
		// this should throw a 404 if the user does NOT exist
		throw new ApiException("Domain already exists", 412, "Existing remote domain : " . $domain);
	}
	catch(Exception $e)
	{
		// if this is not the 404 we expect, rethrow it
		if( !($e instanceof ApiException) || !preg_match("/Entry not found/s", $e.'') )
			throw $e;
	}

	// =================================
	// INSERT REMOTE DOMAIN
	// =================================
	$dn = ldap::buildDN(ldap::DOMAIN, $domain);
	$split = explode('.', $domain);
	$name = $split[0];
	$params = array('dn' => $dn, 'uid' => $name, 'domain' => $domain, 'type'=>$type, 'source' => $source_data['associatedDomain'], 'owner' => $user_dn);
	
	$handler = new alias();
	$data = $handler->build($params);
	
	$GLOBALS['ldap']->create($dn, $data);
		
	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('DOMAINS', $user);

	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$data['source'] = $source_data;
	$data['type'] = $type;
	$GLOBALS['system']->create(system::ALIAS, $data);

	// =================================
	// INSERT DEFAULT SUBDOMAINS
	// =================================
	$dn = ldap::buildDN(ldap::SUBDOMAIN, $domain, '*');
	$params = array('dn' => $dn, 'subdomain' => '*', 'uid' => '*', 'domain' => $domain, 'owner' => $user_dn);
	$handler = new subdomain();
	$data = $handler->build($params);
	$GLOBALS['ldap']->create($dn, $data);
	
	responder::send(array("domain"=>$domain, "id"=>$data['uidNumber']));
});

return $a;

?>
