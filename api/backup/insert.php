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
	'name'=>array('site', 'site_id', 'id'),
	'description'=>'The name or id of the target site.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'description'=>'The name of the database to remove.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
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
	$site = $a->getParam('site');
	$database = $a->getParam('database');
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
	
	if( ($site === null && $database === null) || ($site !== null && $database !== null) )
			throw new ApiException("Bad request", 500, "Please fill one of the params database or site but one at the time");

	// =================================
	// SELECT RECORDS
	// =================================			
	if( $database !== null )
	{
		$sql = "SELECT d.database_name, d.database_type, d.database_desc, d.database_server, u.user_id, u.user_name 
			FROM `databases` d
			LEFT JOIN users u ON(u.user_id = d.database_user)
			WHERE d.database_name = '".security::escape($database)."' AND d.database_user = {$userdata['user_id']}";
		$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		
		if( $result['database_name'] )
		{
			$identifier = md5($result['database_name'] . time() . rand(11111111, 99999999) ) . '.sql';
			$command = "/dns/tm/sys/usr/local/bin/dump {$result['database_type']} {$result['database_name']} {$identifier} {$userdata['user_ldap']} {$result['database_server']}";
			$GLOBALS['gearman']->sendAsync($command);
			
			$sql = "INSERT INTO backups (backup_identifier, backup_title, backup_user, backup_type, backup_url, backup_date) VALUES ('{$identifier}', 'Backup {$result['database_name']} ({$result['database_desc']})', {$userdata['user_id']}, 'database', 'https://download.olympe.in/{$identifier}.gz', UNIX_TIMESTAMP())";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
		else
			throw new ApiException("Forbidden", 302, "The database or the site does not belong to you.");
	}
	else if( $site !== null )
	{
		if( is_numeric($site) )
			$dn = $GLOBALS['ldap']->getDNfromUID($site);
		else
			$dn = ldap::buildDN(ldap::SUBDOMAIN, $GLOBALS['CONFIG']['DOMAIN'], $site);
		
		$result = $GLOBALS['ldap']->read($dn);
		
		if( is_array($result['owner']) )
			$result['owner'] = $result['owner'][0];
			
		if( $user !== null && $GLOBALS['ldap']->getUIDfromDN($result['owner']) != $userdata['user_ldap'] )
			throw new ApiException("Forbidden", 403, "User {$user} ({$userdata['user_ldap']}) does not match owner of the site {$site} ({$result['gidNumber']})");
		
		if( $result['homeDirectory'] )
		{
			$identifier = md5($result['homeDirectory'] . time() . rand(11111111, 99999999) ) . '.tar';
			$command = "/dns/tm/sys/usr/local/bin/dump site {$result['homeDirectory']} {$identifier} {$result['gidNumber']}";
			$GLOBALS['gearman']->sendAsync($command);
			
			$sql = "INSERT INTO backups (backup_identifier, backup_title, backup_user, backup_type, backup_url, backup_date) VALUES ('{$identifier}', 'Backup {$result['associatedDomain']}', {$userdata['user_id']}, 'site', 'https://download.olympe.in/{$identifier}.gz', UNIX_TIMESTAMP())";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('backup/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array('url'=>"https://download.olympe.in/{$identifier}.gz"));
});

return $a;

?>