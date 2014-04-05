<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for an account");
$a->addGrant(array('ACCESS', 'ACCOUNT_SELECT'));
$a->setReturn(array(array(
	'name'=>'the name of the account', 
	'id'=>'the id of the account', 
	'firstname'=>'the firstname of the account', 
	'lastname'=>'the lastname of the account',
	'redirection'=>'the email redirection of the account',
	'alternate'=>'the email alternative of the account',
	'homeDirectory'=>'the directory of the account',
	'mail'=>'the email address of th account',
	'user'=>array(
		'id'=>'the user id', 
		'name'=>'the username'
	),
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that accounts belong to.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
	));
$a->addParam(array(
	'name'=>array('name', 'account_name', 'account', 'id', 'account_id'),
	'description'=>' The name or id of the account to search for.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>100,
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
	'description'=>'Return only the number of entries.',
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
	$account = $a->getParam('account');
	$user = $a->getParam('user');
	$count = $a->getParam('count');

	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 )
		$count = true;
	else
		$count = false;
		
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
	if( $account !== null )
	{
		if( is_numeric($account) )
			$dn = $GLOBALS['ldap']->getDNfromUID($account);
		else
			$dn = ldap::buildDN(ldap::USER, $domain, $account);

		$result = $GLOBALS['ldap']->read($dn);
	}
	else if( $user !== null )
	{
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		if( $count )
		{
			$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::USER, "(owner={$user_dn})"), true);
			responder::send($result);
		}
		else
			$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::USER, "(owner={$user_dn})"));
	}
	else
	{
		if( $count )
		{
			$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::USER), true);
			responder::send($result);
		}
		else
			$result = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::USER));
	}

	// =================================
	// FORMAT RESULT
	// =================================
	$accounts = array();
	if( $account !== null )
	{
		if( $user !== null && $GLOBALS['ldap']->getUIDfromDN($result['owner']) != $userdata['user_ldap'] )
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the account {$account} ({$result['gidNumber']})");
		
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		$sql = "SELECT user_id, user_name FROM users WHERE user_ldap = ".$GLOBALS['ldap']->getUIDfromDN($result['owner']);
		$info = $GLOBALS['db']->query($sql);

		$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$result['homeDirectory']}'";
		$storage = $GLOBALS['db']->query($sql);
		
		$ac['dn'] = $dn;
		$ac['name'] = $result['uid'];
		$ac['id'] = $result['uidNumber'];
		$ac['size'] = $storage['storage_size'];
		$ac['firstname'] = $result['givenName'];
		$ac['lastname'] = $result['sn'];
		$ac['redirection'] = $result['mailForwardingAddress'];
		$ac['homeDirectory'] = $result['homeDirectory'];
		$ac['alternate'] = $result['mailAlternateAddress'];
		$ac['mail'] = $result['mail'];
		$ac['user'] = array('id'=>$info['user_id'], 'name'=>$info['user_name']);
		
		$groups = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::GROUP, "(member={$dn})"));
		
		if( $groups['uid'] )
		{
			$ac['groups'] = array();
			$ac['groups'][] = array('name'=>$groups['uid'],'id'=>$groups['uidNumber']);
		}
		elseif( is_array($groups) )
		{
			$ac['groups'] = array();
			foreach( $groups as $g )
			{
				$ac['groups'][] = array('name'=>$g['uid'],'id'=>$g['uidNumber']);
			}
		}
		else
			$ac['groups'] = array();
		
		$accounts[] = $ac;
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
			$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$r['homeDirectory']}'";
			$storage = $GLOBALS['db']->query($sql);
			
			$ac['dn'] = $r['dn'];
			$ac['name'] = $r['uid'];
			$ac['id'] = $r['uidNumber'];
			$ac['firstname'] = $r['givenName'];
			$ac['lastname'] = $r['sn'];
			$ac['redirection'] = $r['mailForwardingAddress'];
			$ac['homeDirectory'] = $r['homeDirectory'];
			$ac['size'] = $storage['storage_size'];
			$ac['mail'] = $r['mail'];
			$ac['user'] = array('id'=>'', 'name'=>'');
	
			$groups = $GLOBALS['ldap']->search(ldap::buildDN(ldap::DOMAIN, $domain), ldap::buildFilter(ldap::GROUP, "(member={$r['dn']})"));

			if( $groups['uid'] )
			{
				$ac['groups'] = array();
				$ac['groups'][] = array('name'=>$groups['uid'],'id'=>$groups['uidNumber']);
			}
			elseif( count($groups) > 0 )
			{
				$ac['groups'] = array();
				foreach( $groups as $g )
				{
					$ac['groups'][] = array('name'=>$g['uid'],'id'=>$g['uidNumber']);
				}
			}
			else
				$ac['groups'] = array();
			
			foreach( $info as $i )
			{
				if( $i['user_ldap'] == $r['gidNumber'] )
				{
					$ac['user']['id'] = $i['user_id'];
					$ac['user']['name'] = $i['user_name'];
					break;
				}
			}
			
			$accounts[] = $ac;		
		}
	}

	responder::send($accounts);
});

return $a;

?>