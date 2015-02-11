<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('rebuild'));
$a->setDescription("Rebuild an app");
$a->addGrant(array('ACCESS', 'APP_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('app', 'app_name', 'app_id', 'id', 'uid'),
	'description'=>'The name or the id of the app',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('branch', 'env', 'environment'),
	'description'=>'The environement of the app',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>request::LOWER,
	));	
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
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
	$app = $a->getParam('app');
	$branch = $a->getParam('branch');
	$user = $a->getParam('user');

	// =================================
	// GET APP DN
	// =================================
	if( is_numeric($app) )
		$dn = $GLOBALS['ldap']->getDNfromUID($app);
	elseif( $domain != null )
		$dn = ldap::buildDN(ldap::APP, $domain, $app);
	else
		throw new ApiException("Can not find app", 412, "Can not find app without domain");
	
	// =================================
	// GET APP DATA
	// =================================	
	if( $dn )
		$data = $GLOBALS['ldap']->read($dn);	
	else
		throw new ApiException("Not found", 404, "Can not find app: {$app}");
		
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
	
		if( $data['owner'] != $user_dn )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the subdomain {$subdomain}");
	}
	
	// =================================
	// REBUILD APP
	// =================================	
	$command = "/dns/tm/sys/usr/local/bin/app-rebuild {$app} {$data['homeDirectory']} {$branch} {$runtime} ".strtolower($app)." \"".security::encode($binary)."\"";
	$GLOBALS['gearman']->sendSync($command, 'docker-master');
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('app/rebuild', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");	
});

return $a;

?>
