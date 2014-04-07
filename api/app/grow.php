<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('grow'));
$a->setDescription("Grow an app");
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
	$memory = $a->getParam('memory');
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
	
		if( $result['owner'] != $user_dn )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the subdomain {$subdomain}");
	}
	else
	{
		if( is_array($data['owner']) )
			$data['owner'] = $data['owner'][0];
				
		$ownerdata = $GLOBALS['ldap']->read($data['owner']);
		
		$sql = "SELECT user_ldap, user_id FROM users u WHERE u.user_ldap = '{$ownerdata['uidNumber']}'";
		$userdata = $GLOBALS['db']->query($sql);
	}
	
	// =================================
	// INITIATE QUOTAS
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	
	// =================================
	// GROW APP
	// =================================	
	checkQuota('MEMORY', $userdata['user_id']);
	
	$extra = json_decode($data['description'], true);
	
	if( $memory !== null )
	{
		$newinstances = array();
		if( $extra['branches'][$branch]['instances'] )
		{
			foreach( $extra['branches'][$branch]['instances'] as $i )
			{
				if( $i['memory'] >= $memory )
					throw new ApiException("Not growing", 500, "Your are not growing the app {$app}!");
					
				$newinstances[] = array('host' => $i['host'], 'port' => $i['port'], 'memory' => $memory, 'cpu' => $i['cpu']);	
			}
			$extra['branches'][$branch]['instances'] = $newinstances;
		
			$params = array('description'=>json_encode($extra));
			$GLOBALS['ldap']->replace($dn, $params);
		}
	}
	if( $instances !== null )
	{		
		$newinstances = array();
		$count = 0;
		if( $extra['branches'][$branch]['instances'] )
		{
			if( count($extra['branches'][$branch]['instances']) >= $instances )
				throw new ApiException("Not growing", 500, "Your are not growing the app {$app}!");
					
			foreach( $extra['branches'][$branch]['instances'] as $i )
			{
				if( $count < $instances )
					$newinstances[] = array('host' => $i['host'], 'port' => $i['port'], 'memory' => $i['memory'], 'cpu' => $i['cpu']);	
				
				$count++;
			}
		}
		else
		{
			$sql = "SELECT port, used FROM ports WHERE used = 0";
			$portresult = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
			if( !$portresult['port'] )
			{
				$sql = "INSERT INTO ports (used) VALUES (1)";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				$i['port'] = $GLOBALS['db']->last_id();
			}
			else
			{
				$i['port'] = $portresult['port'];
				$sql = "UPDATE ports SET used = 1 WHERE port = {$port}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
			}
			$i['memory'] = 128;
			$i['cpu'] = 1;
		}
		
		for( $j = $count; $j < $instances; $j++ )
			$newinstances[] = array('port' => $i['port'], 'memory' => $i['memory'], 'cpu' => $i['cpu']);	
		
		$extra['branches'][$branch]['instances'] = $newinstances;
	
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
	}
	
	syncQuota('MEMORY', $user);
	$command = "/dns/tm/sys/usr/local/bin/app-reload {$data['uid']}";
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// RESTART APP IF MEMORY GROWING
	// =================================			
	if( $extra['branches'][$branch]['instances'] && $memory !== null )
	{
		foreach( $extra['branches'][$branch]['instances'] as $key => $value )
		{
			$command = "/dns/tm/sys/usr/local/bin/runit-manage {$value['host']} {$data['uid']}-{$branch}-{$key} stop ".strtolower($data['uid'])." {$branch} && /dns/tm/sys/usr/local/bin/runit-manage {$value['host']} {$data['uid']}-{$branch}-{$key} start";
			$GLOBALS['gearman']->sendAsync($command);
		}
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('app/grow', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");	
});

return $a;

?>
