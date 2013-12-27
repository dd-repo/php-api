<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a domain");
$a->addGrant(array('ACCESS', 'DOMAIN_UPDATE'));
$a->setReturn("OK");

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
	'name'=>array('arecord'),
	'description'=>'The A Record of the domain.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>100,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('mx1'),
	'description'=>'The MX 1 Record of the domain.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>300,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('mx2'),
	'description'=>'The MX 2 Record of the domain.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>300,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('mailer'),
	'description'=>'Enable domain emails management.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
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
	$arecord = $a->getParam('arecord');
	$mx1 = $a->getParam('mx1');
	$mx2 = $a->getParam('mx2');
	$mailer = $a->getParam('mailer');
	$user = $a->getParam('user');

	if( $mailer == '1' || $mailer == 'yes' || $mailer == 'true' || $mailer === true || $mailer === 1 )
		$mailer = true;
	elseif( $mailer == '0' || $mailer == 'no' || $mailer == 'false' || $mailer === false || $mailer === 0 )
		$mailer = false;
		
	// =================================
	// SELECT REMOTE ENTRIES
	// =================================
	if( is_numeric($domain) )
		$dn = $GLOBALS['ldap']->getDNfromUID($domain);
	else
		$dn = ldap::buildDN(ldap::DOMAIN, $domain);
		
	$result = $GLOBALS['ldap']->read($dn);

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
	
		if( $result['owner'] != $user_dn )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the subdomain {$subdomain}");
	}

	// =================================
	// UPDATE REMOTE DOMAIN
	// =================================
	$params = array();
	if( $mx1 !== null  && $mx2 === null )
	{
		$params['mXRecord'][0] = '10 ' . $mx1;
		$params['mXRecord'][1] = '20 ' . $result['mXRecord'][1];
	}
	else if( $mx2 !== null && $mx1 === null )
	{
		$params['mXRecord'][0] = '10 ' . $result['mXRecord'][0];
		$params['mXRecord'][1] = '20 ' . $mx2;	
	}
	else if( $mx1 !== null  && $mx2 !== null )
	{
		$params['mXRecord'][0] = '10 ' . $mx1;
		$params['mXRecord'][1] = '20 ' . $mx2;	
	}
	
	if( $arecord !== null && strpos($arecord, ',') !== false )
	{
		$records = explode(',', $arecord);
		$params['aRecord'] = array();
		foreach( $records as $r )
			$params['aRecord'][] = $arecord;
	}
	else if( $arecord !== null )
		$params['aRecord'] = $arecord;
	
	if( $mailer !== null )
	{
		if( $mailer === false )
			$GLOBALS['ldap']->replace($dn, array('mailHost'=>$result['mailHost']), ldap::DELETE);
		elseif( $mailer === true )
			$params['mailHost'] = 'mail.' . $GLOBALS['CONFIG']['DOMAIN'];
	}
	
	$GLOBALS['ldap']->replace($dn, $params);

	responder::send("OK");
});

return $a;

?>