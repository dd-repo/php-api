<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify an account");
$a->addGrant(array('ACCESS', 'ACCOUNT_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('account', 'name', 'id', 'account_id'),
	'description'=>'The name or id of the account to remove.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
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
	'name'=>array('pass', 'password', 'account_password', 'account_pass'),
	'description'=>'The password of the account.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('firstname', 'givenname', 'first_name', 'account_firstname', 'account_givenname', 'account_first_name', 'account_given_name'),
	'description'=>'The first name of the account.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('lastname', 'sn', 'account_lastname', 'account_sn', 'account_last_name'),
	'description'=>'The last name of the account.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('redirection', 'mail', 'redirect', 'account_redirect'),
	'description'=>'The redirection email.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));
$a->addParam(array(
	'name'=>array('alternate', 'alternate_email'),
	'description'=>'The alternative email.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
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
	'name'=>array('team', 'team_id'),
	'description'=>'The id of the team.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('key', 'ssh'),
	'description'=>'The SSH key',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>500,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('join'),
	'description'=>'Mode team join (can be add/delete).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>6,
	'match'=>"(add|delete)"
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
	$account = $a->getParam('account');
	$domain = $a->getParam('domain');
	$pass = $a->getParam('pass');
	$firstname = $a->getParam('firstname');
	$lastname = $a->getParam('lastname');
	$redirection = $a->getParam('redirection');
	$alternate = $a->getParam('alternate');
	$mode = $a->getParam('mode');
	$team = $a->getParam('team');
	$join = $a->getParam('join');
	$key = $a->getParam('key');
	$user = $a->getParam('user');
	
	// =================================
	// GET REMOTE INFO
	// =================================
	if( is_numeric($account) )
		$dn = $GLOBALS['ldap']->getDNfromUID($account);
	else
		$dn = ldap::buildDN(ldap::USER, $domain, $account);
	
	$result = $GLOBALS['ldap']->read($dn);

	if( $result == null || $result['uidNumber'] == null )
		throw new ApiException("Unknown account", 412, "Unknown account : {$account}");
		
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
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the account {$account} ({$result['gidNumber']})");
	}
	
	// =================================
	// UPDATE REMOTE ACCOUNT
	// =================================
	$params = array();
	$params2 = array();
	if( $pass !== null )
		$params['userPassword'] = $pass;
	if( $firstname !== null )
		$params['givenName'] = $firstname;
	if( $lastname !== null )
		$params['sn'] = $lastname;
	if( $key !== null && $mode == 'add'  )
		$params2['sshPublicKey'] = $key;
	if( $redirection !== null )
		$params2['mailForwardingAddress'] = $redirection;
	if( $alternate !== null )
		$params2['mailAlternateAddress'] = $alternate;
		
	if( $mode == 'add' )
		$GLOBALS['ldap']->replace($dn, $params2, ldap::ADD);
	elseif( $mode == 'delete' )
		$GLOBALS['ldap']->replace($dn, $params2, ldap::DELETE);	
	
	$GLOBALS['ldap']->replace($dn, $params);

	if( $key !== null && $mode == 'delete')
	{
		$newkeys = array();
		if( is_array($result['sshPublicKey']) )
		{
			$i = 0;
			foreach( $result['sshPublicKey'] as $k )
			{
				if( $i != $key )
					$newkeys[] = $k;
				$i++;
			}
		}
		
		$params3['sshPublicKey'] = $newkeys;
		$GLOBALS['ldap']->replace($dn, $params3);
	}
	
	// =================================
	// TEAM JOIN
	// =================================
	if( $join == 'add' )
	{
		$group_dn = $GLOBALS['ldap']->getDNfromUID($team);
		$mod['member'] = $dn;
		$GLOBALS['ldap']->replace($group_dn, $mod, ldap::ADD);
	}
	elseif( $join == 'delete' )
	{
		$group_dn = $GLOBALS['ldap']->getDNfromUID($team);
		$mod['member'] = $dn;
		$GLOBALS['ldap']->replace($group_dn, $mod, ldap::DELETE);		
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('account/update', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");
});

return $a;

?>