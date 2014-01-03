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
	'name'=>array('restart'),
	'description'=>'Restart the application',
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
	'name'=>array('reassign'),
	'description'=>'Reassign all ports of the app',
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
	'name'=>array('host', 'hostname'),
	'description'=>'The host of the instance',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$a->addParam(array(
	'name'=>array('instance'),
	'description'=>'The instance',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>2,
	'match'=>request::NUMBER,
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
	'name'=>array('pass', 'password'),
	'description'=>'The password of the app.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL
	));
$a->addParam(array(
	'name'=>array('tag', 'app_tag'),
	'description'=>'The tag of the app',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200
	));
$a->addParam(array(
	'name'=>array('cache'),
	'description'=>'Whether or not to active nginx cache.',
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
	$restart = $a->getParam('restart');
	$rebuild = $a->getParam('rebuild');
	$reassign = $a->getParam('reassign');
	$service = $a->getParam('service');
	$url = $a->getParam('url');
	$mode = $a->getParam('mode');
	$branch = $a->getParam('branch');
	$hostname = $a->getParam('hostname');
	$instance = $a->getParam('instance');
	$pass = $a->getParam('pass');
	$tag = $a->getParam('tag');
	$cache = $a->getParam('cache');
	$user = $a->getParam('user');

	if( $cache == '1' || $cache == 'yes' || $cache == 'true' || $cache === true || $cache === 1 ) $cache = 1;
	else if( $cache !== null ) $cache = 0;
	
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
	
	$expl = explode('-', $data['uid']);
	$language = $expl[0];
	
	$sql = "SELECT app_id FROM apps WHERE app_id = {$data['uidNumber']}";
	$appresult = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		
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
	$docker = false;
	if( $tag !== null )
	{
		if( !$appresult['app_id'] )
		{
			$sql = "INSERT INTO apps (app_id, app_tag) VALUES ({$data['uidNumber']}, '".security::encode($tag)."')";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
		else
		{
			$sql = "UPDATE apps SET app_tag = '".security::encode($tag)."' WHERE app_id = {$data['uidNumber']}";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
	}
	if( $pass !== null )
	{
		$params['userPassword'] = $pass;
		$GLOBALS['ldap']->replace($dn, $params);
	}
	if( $reassign !== null )
	{
		$extra = json_decode($data['description'], true);
		
		$newinstances = array();
		if( is_array($extra['branches']) )
		{
			foreach( $extra['branches'] as $k => $v )
			{
				$sql = "SELECT port, used FROM ports WHERE used = 0";
				$portresult = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
				if( !$portresult['port'] )
				{
					$sql = "INSERT INTO ports (used) VALUES (1)";
					$GLOBALS['db']->query($sql, mysql::NO_ROW);
					$port = $GLOBALS['db']->last_id();
				}
				else
				{
					$port = $portresult['port'];
					$sql = "UPDATE ports SET used = 1 WHERE port = {$port}";
					$GLOBALS['db']->query($sql, mysql::NO_ROW);
				}
				
				if( is_array($v['instances']) )
				{
					foreach( $v['instances'] as $key => $value )
						$newinstances[] = array('host' => $value['host'], 'port' => $port, 'memory' => $value['memory'], 'cpu' => $value['cpu']);
						
					$extra['branches'][$k]['instances'] = $newinstances;
				}
			}
		}
		
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
	}
	
	if( $hostname !== null && $instance !== null && $branch !== null )
	{
		$extra = json_decode($data['description'], true);
		
		$newinstances = array();
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $key => $value )
			{
				if( $key == $instance )
					$newinstances[] = array('host' => $hostname, 'port' => $value['port'], 'memory' => $value['memory'], 'cpu' => $value['cpu']);	
				else
					$newinstances[] = array('host' => $value['host'], 'port' => $value['port'], 'memory' => $value['memory'], 'cpu' => $value['cpu']);	
			}
		
			$extra['branches'][$branch]['instances'] = $newinstances;
		
			$params = array('description'=>json_encode($extra));
			$GLOBALS['ldap']->replace($dn, $params);
		}
	}
	if( $cache !== null )
	{
		$extra = json_decode($data['description'], true);
		$extra['cache'] = $cache;
		
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
	}
	if( $memory !== null && $branch !== null )
	{
		checkQuota('MEMORY', $user);

		$extra = json_decode($data['description'], true);
		
		$newinstances = array();
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $i )
				$newinstances[] = array('host' => $i['host'], 'port' => $i['port'], 'memory' => $memory, 'cpu' => $i['cpu']);	

			$extra['branches'][$branch]['instances'] = $newinstances;
		
			$params = array('description'=>json_encode($extra));
			$GLOBALS['ldap']->replace($dn, $params);
		}
	
		syncQuota('MEMORY', $user);
		
		$docker = true;
	}
	
	if( $instances !== null && $branch !== null )
	{
		checkQuota('MEMORY', $user);
		
		$extra = json_decode($data['description'], true);
		
		$newinstances = array();
		if( $extra['branches'][$branch]['instances'] )
		{
			$count = 0;
			foreach( $extra['branches'][$branch]['instances'] as $i )
			{
				if( $count < $instances )
					$newinstances[] = array('host' => $i['host'], 'port' => $i['port'], 'memory' => $i['memory'], 'cpu' => $i['cpu']);	
				
				$count++;
			}
			
			for( $j = $count; $j < $instances; $j++ )
				$newinstances[] = array('port' => $i['port'], 'memory' => $i['memory'], 'cpu' => $i['cpu']);	
			
			$extra['branches'][$branch]['instances'] = $newinstances;
		
			$params = array('description'=>json_encode($extra));
			$GLOBALS['ldap']->replace($dn, $params);
		}

		syncQuota('MEMORY', $user);
		
		$docker = true;
	}
	
	if( $start !== null && $branch !== null )
	{
		$commands = array();
		$extra = json_decode($data['description'], true);
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $key => $value )
				$commands[] = "/dns/tm/sys/usr/local/bin/runit-manage {$value['host']} {$data['uid']}-{$branch}-{$key} start";
		}
		
		$GLOBALS['system']->exec($commands);
	}
	else if( $stop !== null && $branch !== null )
	{
		$commands = array();
		$extra = json_decode($data['description'], true);
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $key => $value )
				$commands[] = "/dns/tm/sys/usr/local/bin/runit-manage {$value['host']} {$data['uid']}-{$branch}-{$key} stop ".strtolower($data['uid'])." {$branch}";
		}
		
		$GLOBALS['system']->exec($commands);
	}
	else if( $restart !== null && $branch !== null )
	{
		$extra = json_decode($data['description'], true);
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $key => $value )
			{
				$commands = array();
				$commands[] = "/dns/tm/sys/usr/local/bin/runit-manage {$value['host']} {$data['uid']}-{$branch}-{$key} stop ".strtolower($data['uid'])." {$branch}";
				$commands[] = "sleep 2 && /dns/tm/sys/usr/local/bin/runit-manage {$value['host']} {$data['uid']}-{$branch}-{$key} start";
				$GLOBALS['system']->exec($commands);
			}
		}
	}
	if( $rebuild !== null && $branch !== null )
	{
		$commands = array();
		$commands[] = "/dns/tm/sys/usr/local/bin/app-rebuild {$data['uid']} {$data['homeDirectory']} {$branch} ".strtolower($data['uid'])." {$file}";
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

		$commands = array();
		$commands[] = "ln -s {$data['homeDirectory']}/{$branch} {$data['data2']['homeDirectory']}";
		$commands[] = "/dns/tm/sys/usr/local/bin/app-update {$data['uid']} \"{$texturls}\"";
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

		$commands = array();
		$commands[] = "rm {$data['data2']['homeDirectory']}";			
		$commands[] = "/dns/tm/sys/usr/local/bin/app-update {$data['uid']} \"{$texturls}\"";
		$GLOBALS['system']->exec($commands);
	}
	else if( $branch !== null && $mode == 'add' )
	{
		$extra = json_decode($data['description'], true);
		$commands = array();
		$commands[] = "/dns/tm/sys/usr/local/bin/create-branch {$data['uid']} {$data['homeDirectory']} {$data['uidNumber']} {$data['gidNumber']} {$branch} ".strtolower($data['uid'])." {$language} \"{$appresult['app_binary']}\"";
		$GLOBALS['system']->exec($commands);
	
		$sql = "SELECT port, used FROM ports WHERE used = 0";
		$portresult = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		if( !$portresult['port'] )
		{
			$sql = "INSERT INTO ports (used) VALUES (1)";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
			$port = $GLOBALS['db']->last_id();
		}
		else
		{
			$port = $portresult['port'];
			$sql = "UPDATE ports SET used = 1 WHERE port = {$port}";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
	
		$extra['branches'][$branch] = array('instances'=>array(array('port'=>$port, 'memory' => '128', 'cpu' => 1)));
		
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
		$commands = array();
		$commands[] = "/dns/tm/sys/usr/local/bin/app-reload {$data['uid']}";
		$GLOBALS['system']->exec($commands);
	}
	
	responder::send("OK");	
});

return $a;

?>
