<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes a user");
$a->addGrant(array('ACCESS', 'USER_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'id', 'user_id', 'uid'),
	'description'=>'The name or id of the user to delete.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
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
	$user = $a->getParam('user');
	
	// =================================
	// GET LOCAL USER INFO
	// =================================
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";

	$sql = "SELECT u.user_id, u.user_name, u.user_ldap FROM users u WHERE {$where}";
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['user_id'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET USER INFO
	// =================================	
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $result['user_name']);
	$data = $GLOBALS['ldap']->read($dn);
	
	if( $dn )
	{
		// =================================
		// APPS
		// =================================
		$option = "(owner={$dn})";
		$apps = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP, $option));
	
		foreach( $apps as $a )
		{
			if( $a['dn'] ) 
			{
				$GLOBALS['ldap']->delete($a['dn']);
				$GLOBALS['system']->delete(system::APP, $a);
			}
		}
		
		// =================================
		// DOMAINS
		// =================================
		$option = "(owner={$dn})";
		$domains = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::DOMAIN, $option));
	
		foreach( $domains as $d )
		{
			if( $d['dn'] ) 
			{
				$GLOBALS['ldap']->delete($d['dn']);
				$GLOBALS['system']->delete(system::DOMAIN, $d);
			}
		}
		
		// =================================
		// DELETE REMOTE USER
		// =================================
		$GLOBALS['ldap']->delete($dn);
	}
	
	// =================================
	// DELETE LOCAL USER
	// =================================
	$sql = "DELETE FROM users WHERE user_id={$result['user_id']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// DELETE PIWIK USER
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.deleteUser&userLogin={$result['user_name']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);

	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$GLOBALS['system']->delete(system::USER, $data);
	
	responder::send("OK");
});

return $a;

?>