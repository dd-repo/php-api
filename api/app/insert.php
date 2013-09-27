<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new app");
$a->addGrant(array('ACCESS', 'APP_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the app', 
	'name'=>'the app name'
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that app belong to.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('runtime', 'app_runtime'),
	'description'=>'The target runtime',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));	
$a->addParam(array(
	'name'=>array('framework', 'app_framework'),
	'description'=>'The target framework',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));	
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$a->addParam(array(
	'name'=>array('app'),
	'description'=>'The specified app.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER
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
	$runtime = $a->getParam('runtime');
	$framework = $a->getParam('framework');
	$user = $a->getParam('user');
	$application = $a->getParam('app');
	
	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_name FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
		
	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	// =================================
	// CHECK QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	checkQuota('MEMORY', $user);
	checkQuota('APPS', $user);
	
	// =================================
	// INSERT REMOTE APP
	// =================================
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$app = '';
	for( $u = 1; $u <= 8; $u++ )
	{
		$number = strlen($chars);
		$number = mt_rand(0,($number-1));
		$app .= $chars[$number];
	}
	$app = $runtime . '-' . $app;

	$dn = ldap::buildDN(ldap::APP, $domain, $app);
	$params = array('dn' => $dn, 'uid' => $app, 'domain' => $domain, 'owner' => $user_dn);
	
	$handler = new app();
	$data = $handler->build($params);
	
	$result = $GLOBALS['ldap']->create($dn, $data);

	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::ADD);
	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$data['runtime'] = $runtime;
	$data['url'] = $app . '.' . $GLOBALS['CONFIG']['DEV_DOMAIN'];
	$data['framework'] = $framework;
	$data['application'] = $application;
	$data['token'] = $userdata['user_cf_token'];
	$GLOBALS['system']->create(system::APP, $data);
	
	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('MEMORY', $user);
	syncQuota('APPS', $user);

	responder::send(array("name"=>$app, "id"=>$data['uidNumber']));
});

return $a;


?>