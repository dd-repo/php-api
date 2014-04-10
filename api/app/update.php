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
	'name'=>array('url', 'uri'),
	'description'=>'The url of the app',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>500,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
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
	'description'=>'Mode for application and environment (can be add/delete).',
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
	'name'=>array('member', 'member_id'),
	'description'=>'The id of the member.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('join'),
	'description'=>'Mode team join (can be add/delete).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>6,
	'match'=>"(add|delete)"
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
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
	$url = $a->getParam('url');
	$mode = $a->getParam('mode');
	$branch = $a->getParam('branch');
	$hostname = $a->getParam('hostname');
	$instance = $a->getParam('instance');
	$pass = $a->getParam('pass');
	$tag = $a->getParam('tag');
	$cache = $a->getParam('cache');
	$member = $a->getParam('member');
	$join = $a->getParam('join');
	$email = $a->getParam('email');
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
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
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
		
		$sql = "SELECT user_ldap, user_id FROM users u WHERE u.user_ldap = '{$ownerdata['uidNumber']}'";
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
		$params = array('userPassword' => $pass);
		$GLOBALS['ldap']->replace($dn, $params);
	}

	if( $email !== null )
	{
		$params = array('mailForwardingAddress' => $email);
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
	
	if( $url !== null && $branch != null && $mode == 'add' )
	{
		$extra = json_decode($data['description'], true);
		$dn2 = $GLOBALS['ldap']->getDNfromHostname($url);
		$data['data2'] = $GLOBALS['ldap']->read($dn2);
		
		$extra['branches'][$branch]['urls'][] = $url;
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);
		
		$commands = array();
		$commands[] = "ln -s {$data['homeDirectory']}/{$branch} {$data['data2']['homeDirectory']}";
		$commands[] = "/dns/tm/sys/usr/local/bin/app-update {$data['uid']}";
		$GLOBALS['gearman']->sendAsync($commands);
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

		$commands = array();
		$commands[] = "rm {$data['data2']['homeDirectory']}";			
		$commands[] = "/dns/tm/sys/usr/local/bin/app-update {$data['uid']}";
		$GLOBALS['gearman']->sendAsync($commands);
	}
	else if( $branch !== null && $mode == 'add' )
	{
		$extra = json_decode($data['description'], true);
		$command = "/dns/tm/sys/usr/local/bin/create-branch {$data['uid']} {$data['homeDirectory']} {$data['uidNumber']} {$data['gidNumber']} {$branch} ".strtolower($data['uid'])." {$language} \"{$appresult['app_binary']}\"";
		$GLOBALS['gearman']->sendAsync($command);
	
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
		$extra = json_decode($data['description'], true);

		// FREE PORT AND STOP DOCKER
		if( count($extra['branches'][$branch]['instances']) > 0 )
		{
			foreach( $extra['branches'][$branch]['instances'] as $i )
			{
				$command = "sv stop {$data['uid']}-".security::escape($branch)."-{$i['id']}; rm /etc/service/{$data['uid']}-".security::escape($branch)."-{$i['id']}; docker rmi registry:5000/".strtolower($data['uid'])."-".security::escape($branch);
				$GLOBALS['gearman']->sendSync($command, $i['host']);
			
				if( $i['port'] )
				{
					$sql = "UPDATE ports SET used = 0 WHERE port = {$i['port']}";
					$GLOBALS['db']->query($sql, mysql::NO_ROW);		
				}
			}
		}
			
		// DELETE SYMLINKS
		if( count($extra['branches'][$branch]['urls']) > 0 )
		{
			foreach( $extra['branches'][$branch]['urls'] as $u )
			{
				$dn2 = $GLOBALS['ldap']->getDNfromHostname($u);
				$data2 = $GLOBALS['ldap']->read($dn2);
				$commands[] = "rm {$data2['homeDirectory']}";
			}
		}
		$GLOBALS['gearman']->sendAsync($commands);
		
		unset($extra['branches'][$branch]);
		$params = array('description'=>json_encode($extra));
		$GLOBALS['ldap']->replace($dn, $params);		
		
		$command = "rm -Rf {$data['homeDirectory']}/".security::escape($branch);
		$GLOBALS['gearman']->sendAsync($command);
	}
	
	// =================================
	// MEMBERSHIP
	// =================================
	if( $join == 'add' && $member !== null )
	{
		$group_dn = $GLOBALS['ldap']->getDNfromUID($member);
		$mod['member'] = $group_dn;
		$GLOBALS['ldap']->replace($dn, $mod, ldap::ADD);
	}
	elseif( $join == 'delete' && $member !== null )
	{
		$group_dn = $GLOBALS['ldap']->getDNfromUID($member);
		$mod['member'] = $group_dn;
		$GLOBALS['ldap']->replace($dn, $mod, ldap::DELETE);		
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('app/update', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");	
});

return $a;

?>