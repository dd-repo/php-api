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
	'name'=>array('branch'),
	'description'=>'The target branch',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER
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
	'name'=>array('database'),
	'description'=>'Include database in app branch backup?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('auto'),
	'description'=>'Is this an automatic task?',
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
	$branch = $a->getParam('branch');
	$service = $a->getParam('service');
	$auto = $a->getParam('auto');
	$user = $a->getParam('user');
		
	if( $auto == '1' || $auto == 'yes' || $auto == 'true' || $auto === true || $auto === 1 ) $auto = 1;
	else $auto = 0;
	if( $database == '1' || $database == 'yes' || $database == 'true' || $database === true || $database === 1 ) $database = true;
	else $database = false;
	
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
	// SERVICE BACKUP
	// =================================
	if( $service !== null )
	{
		// =================================
		// PREPARE WHERE CLAUSE
		// =================================
		$where = " AND s.service_name = '".security::escape($service)."'";
		if( $user !== null )
		{
			if( is_numeric($user) )
				$where .= " AND u.user_id = " . $user;
			else
				$where .= " AND u.user_name = '".security::escape($user)."'";
		}
	
		// =================================
		// SELECT RECORDS
		// =================================
		$sql = "SELECT s.service_name, s.service_type, s.service_app, s.service_desc, s.service_description, s.service_host, u.user_id, u.user_name, u.user_ldap 
			FROM `services` s
			LEFT JOIN users u ON(u.user_id = s.service_user)
			WHERE true {$where}";
		$result = $GLOBALS['db']->query($sql);

		// =================================
		// DO BACKUP
		// =================================		
		if( $result['service_name'] )
		{			
			if( $branch !== null )
				$result['service_name'] = $result['service_name'] . "-{$branch}";
			
			$identifier = md5($result['service_name'] . time() . rand(11111111, 99999999) ) . '.' . $result['service_type'];
			$command = "/dns/tm/sys/usr/local/bin/dump {$result['service_type']} {$result['service_name']} {$identifier} {$result['user_ldap']} {$result['service_host']} {$result['user_name']}";
			$GLOBALS['gearman']->sendSync($command);
			
			if( $branch !== null )
				$title = "Backup {$result['service_name']}-{$branch} ({$result['service_description']})";
			else
				$title = "Backup {$result['service_name']} ({$result['service_description']})";
			
			$sql = "INSERT INTO backups (backup_identifier, backup_title, backup_user, backup_type, backup_url, backup_date, backup_auto) VALUES ('{$identifier}', '{$title}', {$result['user_id']}, 'service', 'https://download.anotherservice.com/{$identifier}.gz', UNIX_TIMESTAMP(), {$auto})";
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
		
		$userinfo = $GLOBALS['ldap']->read($result['owner']);
					
		if( $result['homeDirectory'] )
		{	
			$identifier = md5($result['homeDirectory'] . time() . rand(11111111, 99999999) ) . '.tar';
			if( $branch !== null && $database === true )
			{
				$sql = "SELECT b.branch_name, b.app_id, b.app_name, s.service_name, s.service_type, s.service_host, s.service_description FROM service_branch b LEFT JOIN services s ON(s.service_name = b.service_name) WHERE b.app_id = '{$result['uidNumber']}' AND b.branch_name = '{$branch}'";
				$services = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
				
				foreach( $services as $s )
				{
					$command = "/dns/tm/sys/usr/local/bin/dump-partial {$s['service_type']} {$s['service_name']} {$identifier} {$userinfo['uidNumber']} {$s['service_host']} {$userinfo['uid']}";
					$GLOBALS['gearman']->sendSync($command);
				}
			}
			
			$sql = "SELECT app_binary, app_tag FROM apps WHERE app_id = '{$result['uidNumber']}'";
			$appinfo = $GLOBALS['db']->query($sql);		

			$type = "app";
			if( $branch !== null )
			{
				$command = "/dns/tm/sys/usr/local/bin/dump app {$result['homeDirectory']}/".security::escape($branch)." {$identifier} {$result['gidNumber']}";
				$title = "Backup {$result['uid']}-{$branch} ({$appinfo['app_tag']})";
				if( $database === true )
					$type = "full";				
			}
			else
			{
				$command = "/dns/tm/sys/usr/local/bin/dump app {$result['homeDirectory']} {$identifier} {$result['gidNumber']}";
				$title = "Backup {$result['uid']} ({$appinfo['app_tag']})";
			}
			$GLOBALS['gearman']->sendAsync($command);
			
			$sql = "INSERT INTO backups (backup_identifier, backup_title, backup_user, backup_type, backup_url, backup_date, backup_auto) VALUES ('{$identifier}', '{$title}', {$result['user_id']}, '{$type}', 'https://download.anotherservice.com/{$identifier}.gz', UNIX_TIMESTAMP(), {$auto})";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('backup/insert', $a->getParams(), $result['user_id']);
	
	responder::send(array('url'=>"https://download.anotherservice.com/{$identifier}.gz"));
});

return $a;

?>