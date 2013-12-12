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
	'name'=>array('rebuild'),
	'description'=>'Rebuild the application',
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
	'name'=>array('branch', 'env', 'environment'),
	'description'=>'The environement of the app',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>request::LOWER,
	));	
$a->addParam(array(
	'name'=>array('mode'),
	'description'=>'Mode for application address, services and environment (can be add/delete).',
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
	$rebuild = $a->getParam('rebuild');
	$service = $a->getParam('service');
	$url = $a->getParam('url');
	$mode = $a->getParam('mode');
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
		throw new ApiException("Can not find app", 412, "Can not find app without domain: {$app}");
		
	// =================================
	// GET APP DATA
	// =================================	
	if( $dn )
		$data = $GLOBALS['ldap']->read($dn);	
	else
		throw new ApiException("Not found", 404, "Can not find app: {$app}");
	
	if( $user !== null )
	{
		// =================================
		// GET USERS
		// =================================
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	
		// =================================
		// CHECK OWNER
		// =================================
		$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		
		if( is_array($data['owner']) )
			$data['owner'] = $data['owner'][0];
				
		if( $ownerdn != $data['owner'] && $user != 'admin' )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the app {$app}");
	}
	else
	{
		if( is_array($data['owner']) )
			$data['owner'] = $data['owner'][0];
				
		$ownerdata = $GLOBALS['ldap']->read($data['owner']);
		
		$sql = "SELECT user_ldap FROM users u WHERE u.user_ldap = '{$ownerdata['uidNumber']}'";
		$userdata = $GLOBALS['db']->query($sql);
	}
	
	// =================================
	// INITIATE QUOTAS
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	
	// =================================
	// UPDATE REMOTE APP
	// =================================
	$params = array();
	if( $memory !== null && $branch !== null )
	{
		checkQuota('MEMORY', $user);

		$extra = json_decode($data['description'], true);
		
		$newinstances = array();
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $i )
				$newinstances[] = array('memory' => $memory, 'cpu' => 1);	
		}
		else
			$newinstances[] = array('memory' => $memory, 'cpu' => 1);
			
		$extra['branches'][$branch]['instances'] = $newinstances;
		
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
		
		syncQuota('MEMORY', $user);
		
		$docker = true;
	}
	
	if( $instances !== null && $instances != 0 && $branch !== null )
	{
		$memory = 128; 
		$cpu = 1;	
		
		$extra = json_decode($data['description'], true);
		
		if( $extra['branches'][$branch]['instances'] )
		{
			$memory = $extra['branches'][$branch]['instances'][0]['memory']; 
			$cpu = $extra['branches'][$branch]['instances'][0]['cpu'];
		}
		
		$newinstances = array();
		for($i = 0; $i < $instances; $i++ )
			$newinstances[] = array('memory' => $memory, 'cpu' => $cpu);
		
		$extra['branches'][$branch]['instances'] = $newinstances;
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
		
		$docker = true;
	}
	
	if( $start !== null )
	{
		$commands[] = "/dns/tm/sys/usr/local/bin/manage-docker {$data['uid']} start";
		$GLOBALS['system']->exec($commands);
	}
	else if( $stop !== null )
	{
		$commands[] = "/dns/tm/sys/usr/local/bin/manage-docker {$data['uid']} stop";
		$GLOBALS['system']->exec($commands);
	}
	if( $rebuild !== null )
	{
		$commands[] = "/dns/tm/sys/usr/local/bin/rebuild-app {$data['uid']} {$data['homeDirectory']} ".strtolower($data['uid']);
		$GLOBALS['system']->exec($commands);
	}
	
	if( $url !== null && $branch != null && $mode == 'add' )
	{
		$extra = json_decode($data['description'], true);
		$dn2 = $GLOBALS['ldap']->getDNfromHostname($url);
		$data['data2'] = $GLOBALS['ldap']->read($dn2);
		
		$extra['branches'][$branch]['urls'][] = $url;
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
		
		$texturls = '';
		foreach( $extra['branches'][$branch]['urls'] as $uri )
			$texturls .= ' ' . $key;

		$commands[] = "ln -s {$data['homeDirectory']}/{$branch} {$data['data2']['homeDirectory']}";
		$commands[] = "/dns/tm/sys/usr/local/bin/update-app {$data['uid']} \"{$texturls}\"";
		$GLOBALS['system']->exec($commands);
	}
	else if( $url !== null && $mode == 'delete' && $branch != null )
	{
		$extra = json_decode($data['description'], true);
		$dn2 = $GLOBALS['ldap']->getDNfromHostname($url);
		$data['data2'] = $GLOBALS['ldap']->read($dn2);

		$uris = array();
		foreach( $extra['branches'][$branch]['urls'] as $v )
		{
			if( $v != $url )
				$uris[] = $v;
		}
		$urls = $uris;
		
		$extra['branches'][$branch]['urls'] = $urls;
		
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);		
		
		$texturls = '';
		foreach( $extra['branches'][$branch]['urls'] as $key => $value )
			$texturls .= ' ' . $key;

		$commands[] = "rm {$data['data2']['homeDirectory']}";			
		$commands[] = "/dns/tm/sys/usr/local/bin/update-app {$data['uid']} \"{$texturls}\"";
		$GLOBALS['system']->exec($commands);
	}
	else if( $branch !== null && $mode == 'add' )
	{
		$extra = json_decode($data['description'], true);		
		$commands[] = "/dns/tm/sys/usr/local/bin/create-branch {$data['uid']} {$data['homeDirectory']} {$data['uidNumber']} {$data['gidNumber']} {$branch} ".strtolower($data['uid']);
		$GLOBALS['system']->exec($commands);
		
		$extra['branches'][] = $branch;
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
	}
	else if( $branch !== null && $mode == 'delete' )
	{
		// todo
	}
	
	if( $user !== null )
		syncQuota('MEMORY', $user);
	
	if( $docker === true )
	{
		$commands[] = "/dns/tm/sys/usr/local/bin/docker-update";
		$GLOBALS['system']->exec($commands);
	}
	
	responder::send("OK");	
});

return $a;

?>
