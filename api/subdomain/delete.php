<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a subdomain");
$a->addGrant(array('ACCESS', 'SUBDOMAIN_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('subdomain', 'name', 'id', 'subdomain_id'),
	'description'=>'The name or id of the subdomain to remove.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that subdomains belong to.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
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
	$subdomain = $a->getParam('subdomain');
	$domain = $a->getParam('domain');
	$user = $a->getParam('user');

	// =================================
	// GET REMOTE INFO
	// =================================
	if( is_numeric($subdomain) )
		$dn = $GLOBALS['ldap']->getDNfromUID($subdomain);
	else
		$dn = ldap::buildDN(ldap::SUBDOMAIN, $domain, $subdomain);
	
	$result = $GLOBALS['ldap']->read($dn);
		
	if( $result == null || $result['uidNumber'] == null )
		throw new ApiException("Unknown subdomain", 412, "Unknown subdomain : {$subdomain}");
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

		// =================================
		// GET REMOTE USER DN
		// =================================	
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);

		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $result['owner'] != $user_dn )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the subdomain {$subdomain}");
	}

	// =================================
	// DELETE REMOTE SUBDOMAIN
	// =================================
	$GLOBALS['ldap']->delete($dn);

	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$command = "rm -Rf {$data['homeDirectory']}";	
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('subdomain/delete', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");
});

return $a;

?>