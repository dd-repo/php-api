<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes an alias");
$a->addGrant(array('ACCESS', 'DOMAIN_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('domain', 'domain_name', 'id', 'domain_id'),
	'description'=>'The name or id of the domain to remove.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>"([a-z0-9_\\-]{2,200}(\\.[a-z0-9_\\-]{2,5}){1,2}|[0-9]+)",
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
	$domain = $a->getParam('domain');
	$user = $a->getParam('user');
	
	// =================================
	// GET REMOTE DOMAIN
	// =================================	
	if( is_numeric($domain) )
		$dn = $GLOBALS['ldap']->getDNfromUID($domain);
	else
		$dn = ldap::buildDN(ldap::DOMAIN, $domain);
	
	$data = $GLOBALS['ldap']->read($dn);
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
		
		$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		
		if( $data['owner'] != $ownerdn )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the domain {$domain}");
	}

	// =================================
	// DELETE REMOTE DOMAIN
	// =================================
	$GLOBALS['ldap']->delete($dn);

	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$command = "rm -Rf {$data['homeDirectory']}";
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// SYNC QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	syncQuota('DOMAINS', $userdata['user_id']);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('alias/delete', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");
});

return $a;

?>