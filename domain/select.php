<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a domain");
$a->addGrant(array('ACCESS', 'DOMAIN_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the domain', 
	'hostname'=>'the complete domain hostname', 
	'homeDirectory'=>'the directory of the domain',
	'cNAMERecord'=>'the CNAME Record of the domain',
	'aRecord'=>'the aRecord of the domain',
	'mXRecord'=>'the MX records of the domain',
	'nSRecord'=>'the NS records of the domain',
	'mailHost'=>'the mailHost of the domain',
	'user'=>array(
		'id'=>'the user id', 
		'name'=>'the username'
	),
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name', 'domain_id', 'id'),
	'description'=>'The name or id of the domain to search for.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>200,
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
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Whether or not to include only the number of matches. Default is false.',
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
	$domain = $a->getParam('domain');
	$user = $a->getParam('user');
	$count = $a->getParam('count');
	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 ) $count = true;
	else $count = false;
	
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
	if( $domain !== null )
	{
		if( is_numeric($domain) )
			$dn = $GLOBALS['ldap']->getDNfromUID($domain);
		else
			$dn = ldap::buildDN(ldap::DOMAIN, $domain);
		
		$result = $GLOBALS['ldap']->read($dn);
	}
	else if( $user !== null )
	{
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::DOMAIN, "(owner={$user_dn})"), $count);
	}
	else
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::DOMAIN), $count);
	
	if( $count === true )
		responder::send($result);
	
	// =================================
	// FORMAT RESULT
	// =================================
	$domains = array();
	if( $domain !== null )
	{
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $user !== null && $GLOBALS['ldap']->getUIDfromDN($result['owner']) != $userdata['user_ldap'] )
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the domain {$domain} ({$result['gidNumber']})");
			
		$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = ".$GLOBALS['ldap']->getUIDfromDN($result['owner']);
		$info = $GLOBALS['db']->query($sql);
			
		$d['hostname'] = $result['associatedDomain'];
		$d['id'] = $result['uidNumber'];
		$d['homeDirectory'] = $result['homeDirectory'];
		$d['aRecord'] = $result['aRecord'];
		$d['mxRecord'] = $result['mxRecord'];
		$d['nSRecord'] = $result['nSRecord'];
		$d['mailHost'] = $result['mailHost'];
		$d['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
		
		$domains[] = $d;
	}
	else
	{
		$ldaps = '';			
		foreach( $result as $r )
		{
			if( is_array($r['owner']) )
				$r['owner'] = $r['owner'][0];
			
			$ldaps .= ','.$GLOBALS['ldap']->getUIDfromDN($r['owner']);
		}
		
		$sql = "SELECT user_id, user_name, user_ldap FROM users WHERE user_ldap IN(-1{$ldaps})";
		$info = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
		
		foreach( $result as $r )
		{
			$d['hostname'] = $r['associatedDomain'];
			$d['id'] = $r['uidNumber'];
			$d['homeDirectory'] = $r['homeDirectory'];
			$d['aRecord'] = $r['aRecord'];
			$d['mxRecord'] = $r['mxRecord'];
			$d['nSRecord'] = $r['nSRecord'];
			$d['mailHost'] = $r['mailHost'];
			$d['user'] = array('id'=>'', 'name'=>'');
			
			foreach( $info as $i )
			{
				if( $i['user_ldap'] == $r['gidNumber'] )
				{
					$d['user']['id'] = $i['user_id'];
					$d['user']['name'] = $i['user_name'];
					break;
				}
			}
			
			$domains[] = $d;
		}
	}

	responder::send($domains);
});

return $a;

?>