<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('update', 'modify', 'change'));
$a->setDescription("Update a repo");
$a->addGrant(array('ACCESS', 'SERVICE_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('repo', 'name', 'repo_name', 'repo_id', 'id'),
	'description'=>'The name or id of the repo',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that app belong to.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('desc', 'repo_desc', 'description'),
	'description'=>'The repository description.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('member', 'member_id'),
	'description'=>'The id of the member.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::NUMBER
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
	'name'=>array('permission', 'permissions', 'rights'),
	'description'=>'Permission for joining.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>3,
	'match'=>"(rx|rwx)"
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>800,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
	
$a->setExecute(function() use ($a)
{
	$repo = $a->getParam('repo');
	$domain = $a->getParam('domain');
	$desc = $a->getParam('desc');
	$member = $a->getParam('member');
	$join = $a->getParam('join');
	$email = $a->getParam('email');
	$permission = $a->getParam('permission');
	$user = $a->getParam('user');

	// =================================
	// GET REMOTE INFO
	// =================================
	if( is_numeric($repo) )
		$dn = $GLOBALS['ldap']->getDNfromUID($repo);
	else
		$dn = ldap::buildDN(ldap::USER, $domain, $repo);
	
	$result = $GLOBALS['ldap']->read($dn);

	if( $result == null || $result['uidNumber'] == null )
		throw new ApiException("Unknown repo", 412, "Unknown repo : {$repo}");
		
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
	// UPDATE REMOTE REPO
	// =================================
	$params = array();
	if( $desc !== null )
		$params['description'] = $desc;
	if( $email !== null )
	{
		if( strpos($email, ',') !== false )
		{
			$emails = explode(',', $email);
			$params['mailForwardingAddress'] = array();
			foreach( $emails as $e )
				$params['mailForwardingAddress'][] = $e;
		}
		else
			$params['mailForwardingAddress'] = $email;
		
		$GLOBALS['ldap']->replace($dn, $params);
	}
	
	$GLOBALS['ldap']->replace($dn, $params);

	// =================================
	// MEMBERSHIP
	// =================================
	if( $join == 'add' )
	{
		$memberdn = $GLOBALS['ldap']->getDNfromUID($member);
		$memberinfo = $GLOBALS['ldap']->read($memberdn);
		
		if( $permission === null )
			$permission = 'rwx';
			
		$sql = "INSERT INTO permissions (permission_object, permission_directory, permission_right) VALUES ({$memberinfo['uidNumber']}, '{$result['homeDirectory']}', '{$permission}')";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
		
		$command = array();
		if( strpos($memberdn, 'ou=Groups') !== false )
		{
			$command[] = "setfacl -Rm d:g:{$memberinfo['uidNumber']}:{$permission} {$result['homeDirectory']}";
			if( strpos($result['homeDirectory'], 'Apps') !== false )
				$command[] = "setfacl -Rm d:g:{$memberinfo['uidNumber']}:{$permission} {$result['homeDirectory']}.git/";
		}
		else
		{
			$command[] = "setfacl -Rm d:u:{$memberinfo['uidNumber']}:{$permission} {$result['homeDirectory']}";
			if( strpos($result['homeDirectory'], 'Apps') !== false )
				$command[] = "setfacl -Rm d:u:{$memberinfo['uidNumber']}:{$permission} {$result['homeDirectory']}.git/";
		}
		$GLOBALS['gearman']->sendAsync($command);
		
		$mod['member'] = $memberdn;
		$GLOBALS['ldap']->replace($dn, $mod, ldap::ADD);
	}
	elseif( $join == 'delete' )
	{
		$memberdn = $GLOBALS['ldap']->getDNfromUID($member);
		$memberinfo = $GLOBALS['ldap']->read($memberdn);
		
		$sql = "DELETE FROM permissions WHERE permission_object = {$memberinfo['uidNumber']} AND permission_directory = '{$result['homeDirectory']}'";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
		
		$command = array();
		if( strpos($memberdn, 'ou=Groups') !== false )
		{
			$command[] = "setfacl -Rx d:g:{$memberinfo['gidNumber']} {$result['homeDirectory']}";
			if( strpos($result['homeDirectory'], 'Apps') !== false )
				$command[] = "setfacl -Rx d:g:{$memberinfo['gidNumber']} {$result['homeDirectory']}.git/";	
		}
		else
		{
			$command[] = "setfacl -Rx d:u:{$memberinfo['uidNumber']} {$result['homeDirectory']}";
			if( strpos($result['homeDirectory'], 'Apps') !== false )
				$command[] = "setfacl -Rx d:u:{$memberinfo['gidNumber']} {$result['homeDirectory']}.git/";	
		}
		$GLOBALS['gearman']->sendAsync($command);
		
		$mod['member'] = $memberdn;
		$GLOBALS['ldap']->replace($dn, $mod, ldap::DELETE);		
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('repo/update', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");
});

return $a;

?>
