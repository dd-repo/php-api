<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a subdomain");
$a->addGrant(array('ACCESS', 'SUBDOMAIN_SELECT'));
$a->setReturn(array(array(
	'name'=>'the subdomaine name', 
	'id'=>'the id of the subdomain', 
	'hostname'=>'the complete subdomain hostname', 
	'homeDirectory'=>'the directory of the subdomain',
	'cNAMERecord'=>'the CNAME Record of the subdomain',
	'aRecord'=>'the aRecord of the subdomain',
	'user'=>array(array(
		'id'=>'the user id', 
		'name'=>'the username')
	),
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that subdomains belong to.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
	));
$a->addParam(array(
	'name'=>array('subdomain', 'subdomain_id', 'id'),
	'description'=>'The name or id of the target subdomain.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
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
	$domain = $a->getParam('domain');
	$subdomain = $a->getParam('subdomain');
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
	// SELECT REMOTE ENTRIES
	// =================================
	if( $subdomain !== null )
	{
		if( is_numeric($subdomain) )
			$dn = $GLOBALS['ldap']->getDNfromUID($subdomain);
		else
			$dn = ldap::buildDN(ldap::SUBDOMAIN, $domain, $subdomain);
		
		$result = $GLOBALS['ldap']->read($dn);
	}
	else if( $user !== null )
	{
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::SUBDOMAIN, "(owner={$user_dn})"));
	}
	else if ( $domain !== null )
		$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::SUBDOMAIN));
	else
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::SUBDOMAIN));

	// =================================
	// FORMAT RESULT
	// =================================
	$subdomains = array();
	if( $subdomain !== null )
	{
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $user !== null && $GLOBALS['ldap']->getUIDfromDN($result['owner']) != $userdata['user_ldap'] )
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the subdomain {$subdomain} ({$result['gidNumber']})");
		
		$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = ".$GLOBALS['ldap']->getUIDfromDN($result['owner']);
		$info = $GLOBALS['db']->query($sql);
		
		$s['name'] = $result['uid'];
		$s['id'] = $result['uidNumber'];
		$s['hostname'] = $result['associatedDomain'];
		$s['homeDirectory'] = $result['homeDirectory'];
		$s['cNAMERecord'] = $result['cNAMERecord'];
		$s['aRecord'] = $result['aRecord'];
		$s['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
		
		$subdomains[] = $s;
	}
	else
	{
		foreach( $result as $r )
		{
			$s['name'] = $r['uid'];
			$s['id'] = $r['uidNumber'];
			$s['hostname'] = $r['associatedDomain'];
			$s['homeDirectory'] = $r['homeDirectory'];
			$s['cNAMERecord'] = $r['cNAMERecord'];
			$s['aRecord'] = $r['aRecord'];
			$s['user'] = array('id'=>'', 'name'=>'');
			
			$subdomains[] = $s;		
		}
	}

	responder::send($subdomains);
});

return $a;

?>