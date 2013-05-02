<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a app");
$a->addGrant(array('ACCESS', 'APP_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the app', 
	'name'=>'the name of the app',
	'uris'=>'the uris of the app',
	'homeDirectory'=>'the directory of the app',
	'domain'=>'the app domain',
	'instances'=>array(
		array(
			'id'=>'the id of the instance',
			'state'=>'the state of the instance',
			'uptime'=>'the uptime of the instance',
			'disk'=>array(
					'quota'=>'the disk quota of the instance',
					'usage'=>'the disk usage if the instance'
			),
			'memory'=>array(
					'quota'=>'the memory quota of the instance',
					'usage'=>'the memory usage if the instance'
			),
			'cpu'=>array(
					'quota'=>'the cpu quota of the instance',
					'usage'=>'the cpu usage if the instance'
			)
		)
	),
	'user'=>array(
		'id'=>'the user id', 
		'name'=>'the username'
	)
	)));
$a->addParam(array(
	'name'=>array('app', 'app_name', 'app_id', 'id', 'uid'),
	'description'=>'The name or the id of the app',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Whether or not to include only the number of matches. Default is false.',
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
	$app = $a->getParam('app');
	$user = $a->getParam('user');
	$count = $a->getParam('count');
	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 ) $count = true;
	else $count = false;
	
	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap, user_cf_token FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	
		// =================================
		// SYNC QUOTA
		// =================================
		grantStore::add('QUOTA_USER_INTERNAL');
		request::forward('/quota/user/internal');
		syncQuota('MEMORY', $user);
		syncQuota('APPS', $user);
	}

	// =================================
	// SELECT REMOTE ENTRIES
	// =================================
	if( $app !== null )
	{
		if( is_numeric($app) )
			$dn = $GLOBALS['ldap']->getDNfromUID($app);
		else
			$dn = ldap::buildDN(ldap::APP, $app);
		
		$result = $GLOBALS['ldap']->read($dn);
		
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		
		if( $ownerdn != $result['owner'] )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the app {$app}");
	}
	elseif( $user !== null )
	{
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP, "(owner={$user_dn})"), $count);
	}
	else
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP), $count);
	
	if( $count === true )
		responder::send($result);
	
	// =================================
	// FORMAT RESULT
	// =================================
	$apps = array();
	if( $app !== null )
	{
		try 
		{
			$cf_info = cf::send('apps/' . $result['uid'], 'GET', array(), $userdata['user_cf_token']);
			$cf_stats = cf::send('apps/' . $result['uid'] . '/stats', 'GET', array(), $userdata['user_cf_token']);
		}
		catch(Exception $e) 
		{
		}
		
		$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$result['homeDirectory']}'";
		$storage = $GLOBALS['db']->query($sql);			
					
		$infos['name'] = $result['uid'];
		$infos['id'] = $result['uidNumber'];
		$infos['homeDirectory'] = $result['homeDirectory'];
		$infos['envs'] = json_decode($result['description'], true);
		$infos['size'] = $storage['storage_size'];
		$infos['uris'] = $cf_info['uris'];
		$infos['services'] = $cf_info['services'];
		$infos['instances'] = array();
		$i = 0;
		if( $cf_stats )
		{
			foreach( $cf_stats as $s )
			{
				$infos['instances'][$i]['id'] = $i;
				$infos['instances'][$i]['state'] = $s['state'];
				$infos['instances'][$i]['uptime'] = $s['stats']['uptime'];
				$infos['instances'][$i]['disk']['quota'] = $s['stats']['disk_quota'];
				$infos['instances'][$i]['disk']['usage'] = $s['stats']['usage']['disk'];
				$infos['instances'][$i]['memory']['quota'] = $s['stats']['mem_quota'];
				$infos['instances'][$i]['memory']['usage'] = $s['stats']['usage']['mem'];
				$infos['instances'][$i]['cpu']['quota'] = $s['stats']['cores'];
				$infos['instances'][$i]['cpu']['usage'] = $s['stats']['usage']['cpu'];			
				$i++;			
			}
		}
		
		$apps[] = $infos;
	}
	else
	{
		foreach( $result as $r )
		{
			$cf_info = false;
			$cf_stats = false;
			
			try 
			{
				$cf_info = cf::send('apps/' . $r['uid'], 'GET', array(), $userdata['user_cf_token']);
				$cf_stats = cf::send('apps/' . $r['uid'] . '/stats', 'GET', array(), $userdata['user_cf_token']);
			}
			catch(Exception $e) 
			{
			}		
			
			$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$r['homeDirectory']}'";
			$storage = $GLOBALS['db']->query($sql);			
			$infos['name'] = $r['uid'];
			$infos['id'] = $r['uidNumber'];
			$infos['homeDirectory'] = $r['homeDirectory'];
			$infos['envs'] = json_decode($r['description'], true);
			$infos['size'] = $storage['storage_size'];
			$infos['uris'] = $cf_info['uris'];
			$infos['services'] = $cf_info['services'];
			$infos['instances'] = array();
			$i = 0;
			if( $cf_stats )
			{
				foreach( $cf_stats as $s )
				{
					$infos['instances'][$i]['id'] = $i;
					$infos['instances'][$i]['state'] = $s['state'];
					$infos['instances'][$i]['uptime'] = $s['stats']['uptime'];
					$infos['instances'][$i]['disk']['quota'] = $s['stats']['disk_quota'];
					$infos['instances'][$i]['disk']['usage'] = $s['stats']['usage']['disk'];
					$infos['instances'][$i]['memory']['quota'] = $s['stats']['mem_quota'];
					$infos['instances'][$i]['memory']['usage'] = $s['stats']['usage']['mem'];
					$infos['instances'][$i]['cpu']['quota'] = $s['stats']['cores'];
					$infos['instances'][$i]['cpu']['usage'] = $s['stats']['usage']['cpu'];			
					$i++;	
				}
			}
			
			$apps[] = $infos;
		}
	}

	responder::send($apps);
});

return $a;

?>