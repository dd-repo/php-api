<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify an app");
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
	'name'=>array('instances', 'number'),
	'description'=>'The number of instances',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));	
$a->addParam(array(
	'name'=>array('memory'),
	'description'=>'The app memory (in MB)',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('start'),
	'description'=>'Start the application',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|yes|true)"
	));	
$a->addParam(array(
	'name'=>array('stop'),
	'description'=>'Stop the application',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|yes|true)"
	));
$a->addParam(array(
	'name'=>array('url', 'uri'),
	'description'=>'The url of the app',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>500,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));	
$a->addParam(array(
	'name'=>array('service'),
	'description'=>'The service of the app',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));		
$a->addParam(array(
	'name'=>array('mode'),
	'description'=>'Mode for application address and services (can be add/delete).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>6,
	'match'=>"(add|delete)"
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
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
	$instances = $a->getParam('instances');
	$memory = $a->getParam('memory');
	$start = $a->getParam('start');
	$stop = $a->getParam('stop');
	$service = $a->getParam('service');
	$url = $a->getParam('url');
	$mode = $a->getParam('mode');
	$user = $a->getParam('user');
	
	// =================================
	// GET USERS
	// =================================
	$sql = "SELECT user_ldap, user_cf_token FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET APP DN
	// =================================
	if( is_numeric($app) )
		$dn = $GLOBALS['ldap']->getDNfromUID($app);
	elseif( $domain != null )
		$dn = ldap::buildDN(ldap::APP, $domain, $app);
	else
		throw new ApiException("Can not find app", 412, "Can not find app without domain: {$app}");
		
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
	$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	if( is_array($data['owner']) )
			$data['owner'] = $data['owner'][0];
			
	if( $ownerdn != $data['owner'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the app {$app}");
		
	// =================================
	// GET CF INFO
	// =================================
	$cf_info = cf::send('apps/' . $data['uid'], 'GET', array(), $userdata['user_cf_token']);
	$cf_stats = cf::send('apps/' . $data['uid']. '/stats', 'GET', array(), $userdata['user_cf_token']);

	// =================================
	// INITIATE QUOTAS
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	
	// =================================
	// UPDATE REMOTE APP
	// =================================
	$params = array();
	$params['services'] = $cf_info['services'];
	if( $memory !== null )
	{
		checkQuota('MEMORY', $user);
		$params['resources'] = $cf_info['resources'];
		$params['resources']['memory'] = $memory;
		cf::send('apps/' . $data['uid'], 'PUT', $params, $userdata['user_cf_token']);
		$params['state'] = 'STOPPED';
		cf::send('apps/' . $data['uid'], 'PUT', $params, $userdata['user_cf_token']);
		$params['state'] = 'STARTED';
		cf::send('apps/' . $data['uid'], 'PUT', $params, $userdata['user_cf_token']);
		syncQuota('MEMORY', $user);
		
		responder::send("OK");
	}
	if( $service !== null && $mode == 'add' )
		$params['services'][] = $service;
	elseif( $service !== null && $mode == 'delete' )
	{
		$key = array_search($service, $params['services']);
		if( $key !== false )
			unset($params['services'][$key]);
	}
	if( $instances !== null )
	{
		checkQuota('MEMORY', $user);
		$params['instances'] = $instances;
	}
	if( $start !== null )
		$params['state'] = 'STARTED';
	elseif( $stop !== null )
		$params['state'] = 'STOPPED';
	
	if( $url !== null && $mode == 'add' )
	{
		$params['uris'] = $cf_info['uris'];
		$params['uris'][] = $url;
	}
	elseif( $url !== null && $mode == 'delete' )
	{
		$params['uris'] = $cf_info['uris'];
		$key = array_search($url, $params['uris']);
		
		if( $key !== false )
		{
			$uris = array();
			foreach( $params['uris'] as $k => $v )
			{
				if( $k != $key )
					$uris[] = $v;
			}
			$params['uris'] = $uris;
		}
	}

	cf::send('apps/' . $data['uid'], 'PUT', $params, $userdata['user_cf_token']);
	syncQuota('MEMORY', $user);

	responder::send("OK");	
});

return $a;

?>