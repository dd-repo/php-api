<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a team");
$a->addGrant(array('ACCESS', 'ACCOUNT_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('team', 'name', 'id', 'team_id'),
	'description'=>'The name or id of the team to remove.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that team belong to.',
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
	$team = $a->getParam('team');
	$domain = $a->getParam('domain');
	$user = $a->getParam('user');

	// =================================
	// GET REMOTE INFO
	// =================================
	if( is_numeric($team) )
		$dn = $GLOBALS['ldap']->getDNfromUID($team);
	else
		$dn = ldap::buildDN(ldap::GROUP, $domain, $team);
	
	$result = $GLOBALS['ldap']->read($dn);

	if( $result == null || $result['uidNumber'] == null )
		throw new ApiException("Unknown team", 412, "Unknown team : {$team}");
		
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
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
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the team {$team} ({$result['gidNumber']})");
	}

	// =================================
	// DELETE REMOTE team
	// =================================
	$GLOBALS['ldap']->delete($dn);

	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::DELETE);
	
	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$GLOBALS['system']->delete(system::USER, $result);
	
	responder::send("OK");
});

return $a;

?>