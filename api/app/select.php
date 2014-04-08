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
	'urls'=>'the urls of the app',
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
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id'),
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
$a->addParam(array(
	'name'=>array('extended'),
	'description'=>'Whether or not to show stats. Default is false.',
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
	$extended = $a->getParam('extended');
	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 ) $count = true;
	else $count = false;
	if( $extended == '1' || $extended == 'yes' || $extended == 'true' || $extended === true || $extended === 1 ) $extended = true;
	else $extended = false;
	
	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
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
		
		if( $user != null )
		{
			if( is_array($result['owner']) )
				$result['owner'] = $result['owner'][0];
				
			$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
			
			if( $ownerdn != $result['owner'] )
				throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the app {$app}");
		}
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
		$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$result['homeDirectory']}'";
		$storage = $GLOBALS['db']->query($sql);
		$sql = "SELECT app_binary, app_tag FROM apps WHERE app_id = '{$result['uidNumber']}'";
		$appinfo = $GLOBALS['db']->query($sql);
		
		$extra = json_decode($result['description'], true);
		
		$infos['name'] = $result['uid'];
		$infos['id'] = $result['uidNumber'];
		$infos['certificate'] = $result['gecos'];
		$infos['binary'] = $appinfo['app_binary'];
		$infos['tag'] = $appinfo['app_tag'];
		$infos['homeDirectory'] = $result['homeDirectory'];
		$infos['branches'] = $extra['branches'];
		$infos['cache'] = $extra['cache'];
		$infos['size'] = $storage['storage_size'];
		
		if( $extra['branches'] )
		{
			foreach( $extra['branches'] as $key => $value )
			{
				if( is_array($infos['branches'][$key]) )
				{
					$infos['branches'][$key]['instances'] = array();
					if( $value['instances'] )
					{
						$j = 0;
						foreach( $value['instances'] as $i )
						{
							if( $extended == true )
							{
								$command = "/usr/local/bin/docker-json {$result['uid']}-{$key}-{$j} {$i['port']}";
								$info = $GLOBALS['gearman']->sendSync($command, $i['host']);
								$info = json_decode($info, true);
							}
							else
								$info = array();
														
							$infos['branches'][$key]['instances'][$j]['id'] = $j;
							$infos['branches'][$key]['instances'][$j]['host'] = $i['host'];
							$infos['branches'][$key]['instances'][$j]['port'] = $i['port'];
							$infos['branches'][$key]['instances'][$j]['status'] = $info['status'];
							$infos['branches'][$key]['instances'][$j]['docker'] = $info['docker'];
							$infos['branches'][$key]['instances'][$j]['uptime'] = $info['uptime'];
							$infos['branches'][$key]['instances'][$j]['memory']['quota'] = $i['memory'];
							$infos['branches'][$key]['instances'][$j]['memory']['usage'] = round($info['memory']/1024);
							$infos['branches'][$key]['instances'][$j]['cpu']['quota'] = $i['cpu'];
							$infos['branches'][$key]['instances'][$j]['cpu']['usage'] = $info['cpu']*100;			
							$j++;
						}
					}
				}
			}
		}
		
		$apps[] = $infos;
	}
	else
	{
		foreach( $result as $r )
		{			
			$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$r['homeDirectory']}'";
			$storage = $GLOBALS['db']->query($sql);		
			$sql = "SELECT app_binary, app_tag FROM apps WHERE app_id = '{$r['uidNumber']}'";
			$appinfo = $GLOBALS['db']->query($sql);
		
			$extra = json_decode($r['description'], true);
			
			$infos['name'] = $r['uid'];
			$infos['id'] = $r['uidNumber'];
			$infos['homeDirectory'] = $r['homeDirectory'];
			$infos['binary'] = $appinfo['app_binary'];
			$infos['tag'] = $appinfo['app_tag'];
			$infos['certificate'] = $r['gecos'];
			$infos['size'] = $storage['storage_size'];
			$infos['branches'] = $extra['branches'];
			$infos['cache'] = $extra['cache'];
			
			if( $extra['branches'] )
			{
				foreach( $extra['branches'] as $key => $value )
				{
					if( is_array($infos['branches'][$key]) )
					{
						$infos['branches'][$key]['instances'] = array();
						if( $value['instances'] )
						{
							$j = 0;
							foreach( $value['instances'] as $i )
							{
								if( $extended == true )
								{
									$command = "/usr/local/bin/docker-json {$r['uid']}-{$key}-{$j} {$i['port']}";
									$info = $GLOBALS['gearman']->sendSync($command, $i['host']);
									$info = json_decode($info, true);
								}
								else
									$info = array();
															
								$infos['branches'][$key]['instances'][$j]['id'] = $j;
								$infos['branches'][$key]['instances'][$j]['host'] = $i['host'];
								$infos['branches'][$key]['instances'][$j]['port'] = $i['port'];
								$infos['branches'][$key]['instances'][$j]['status'] = $info['status'];
								$infos['branches'][$key]['instances'][$j]['docker'] = $info['docker'];
								$infos['branches'][$key]['instances'][$j]['uptime'] = $info['uptime'];
								$infos['branches'][$key]['instances'][$j]['memory']['quota'] = $i['memory'];
								$infos['branches'][$key]['instances'][$j]['memory']['usage'] = round($info['memory']/1024);
								$infos['branches'][$key]['instances'][$j]['cpu']['quota'] = $i['cpu'];
								$infos['branches'][$key]['instances'][$j]['cpu']['usage'] = $info['cpu']*100;		
								$j++;
							}
						}
					}
				}
			}
			$apps[] = $infos;
		}
	}

	responder::send($apps);
});

return $a;

?>