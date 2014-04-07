<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Create a new backup");
$a->addGrant(array('ACCESS', 'BACKUP_INSERT'));
$a->setReturn(array(array(
	'url'=>'the URL to the backup'
	)));
$a->addParam(array(
	'name'=>array('app', 'site_id', 'id'),
	'description'=>'The name or id of the target app.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('service', 'name', 'service_name'),
	'description'=>'The name of the service',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>false
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
	$service = $a->getParam('database');
	$user = $a->getParam('user');
		
	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	}
	
	if( ($app === null && $service === null) || ($app !== null && $service !== null) )
			throw new ApiException("Bad request", 500, "Please fill one of the params service or app but one at the time");

	// =================================
	// SELECT RECORDS
	// =================================			
	if( $service !== null )
	{
		$sql = "SELECT s.service_name, s.service_type, s.service_desc, s.service_description, s.service_host, u.user_id, u.user_name 
			FROM `services` s
			LEFT JOIN users u ON(u.user_id = s.service_user)
			WHERE s.service_name = '".security::escape($service)."' AND s.service_user = {$userdata['user_id']}";
		$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
		
		if( $result['service_name'] )
		{
			$identifier = md5($result['service_name'] . time() . rand(11111111, 99999999) ) . '.sql';
			$command = "/dns/tm/sys/usr/local/bin/dump {$result['service_type']} {$result['service_name']} {$identifier} {$userdata['user_ldap']} {$result['service_host']}";
			$GLOBALS['gearman']->sendAsync($command);
			
			$sql = "INSERT INTO backups (backup_identifier, backup_title, backup_user, backup_type, backup_url, backup_date) VALUES ('{$identifier}', 'Backup {$result['service_name']} ({$result['service_desc']})', {$userdata['user_id']}, 'service', 'https://download.anotherservice.com/{$identifier}.gz', UNIX_TIMESTAMP())";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
		else
			throw new ApiException("Forbidden", 302, "The app or the service does not belong to you.");
	}
	else if( $app !== null )
	{
		if( is_numeric($app) )
			$dn = $GLOBALS['ldap']->getDNfromUID($app);
		else
			$dn = ldap::buildDN(ldap::APP, $app);
		
		$result = $GLOBALS['ldap']->read($dn);
		
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $user !== null && $GLOBALS['ldap']->getUIDfromDN($result['owner']) != $userdata['user_ldap'] )
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the app {$app} ({$result['gidNumber']})");
		
		if( $result['homeDirectory'] )
		{
			$sql = "SELECT app_binary, app_tag FROM apps WHERE app_id = '{$result['uidNumber']}'";
			$appinfo = $GLOBALS['db']->query($sql);
		
			$identifier = md5($result['homeDirectory'] . time() . rand(11111111, 99999999) ) . '.tar';
			$command = "/dns/tm/sys/usr/local/bin/dump app {$result['homeDirectory']} {$identifier} {$result['gidNumber']}";
			$GLOBALS['gearman']->sendAsync($command);
			
			$sql = "INSERT INTO backups (backup_identifier, backup_title, backup_user, backup_type, backup_url, backup_date) VALUES ('{$identifier}', 'Backup {$result['uid']} ({$appinfo['app_tag']})', {$userdata['user_id']}, 'site', 'https://download.anotherservice.com/{$identifier}.gz', UNIX_TIMESTAMP())";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('backup/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array('url'=>"https://download.anotherservice.com/{$identifier}.gz"));
});

return $a;

?>