<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new service");
$a->addGrant(array('ACCESS', 'SERVICE_INSERT'));
$a->setReturn(array(array(
	'name'=>'the service name'
	)));

$a->addParam(array(
	'name'=>array('desc', 'service_desc', 'description'),
	'description'=>'The service description.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
	));
$a->addParam(array(
	'name'=>array('vendor', 'vendor_name', 'service_vendor'),
	'description'=>'The service vendor.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>20,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('version', 'service_version'),
	'description'=>'The service version.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>10,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	));
$a->addParam(array(
	'name'=>array('pass', 'password'),
	'description'=>'The password of the service.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
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
	$desc = $a->getParam('desc');
	$vendor = $a->getParam('vendor');
	$version = $a->getParam('version');
	$pass = $a->getParam('pass');
	$user = $a->getParam('user');
	
	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	
	// =================================
	// CHECK QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	checkQuota('SERVICES', $user);

	// =================================
	// GENERATE NAME
	// =================================
	while(true)
	{
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$service = '';
		for( $u = 1; $u <= 8; $u++ )
		{
			$number = strlen($chars);
			$number = mt_rand(0,($number-1));
			$service .= $chars[$number];
		}
		$username = $vendor . '-' . $service;
		$service = $username . '-master';
		
		// check if that service name already exists
		$sql = "SELECT service_name FROM services WHERE service_name='{$service}'";
		$exists = $GLOBALS['db']->query($sql);
		if( $exists == null || $exists['service_name'] == null )
			break;
	}

	// =================================
	// INSERT REMOTE SERVICE
	// =================================
	switch( $vendor )
	{
		case 'mysql':
			$server = 'mysql.anotherservice.com';
			$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
			$link->query("CREATE USER '{$username}'@'%' IDENTIFIED BY '".security::encode($pass)."'");
			$link->query("CREATE DATABASE `{$service}` CHARACTER SET utf8 COLLATE utf8_unicode_ci");
			$link->query("GRANT USAGE ON * . * TO '{$username}'@'%' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0");
			$link->query("GRANT ALL PRIVILEGES ON `{$service}` . * TO '{$username}'@'%'");
			$link->query("FLUSH PRIVILEGES");
		break;
		case 'pgsql':
			$server = 'pgsql.anotherservice.com';
			$command = "/dns/tm/sys/usr/local/bin/create-db-pgsql {$username} {$service} ".security::encode($pass)." {$server}";
			$GLOBALS['gearman']->sendAsync($command);
		break;
		case 'mongodb':
			$server = 'mongo.anotherservice.com';
			$command = "/dns/tm/sys/usr/local/bin/create-db-mongodb {$username} {$service} ".security::encode($pass)." {$server}";
			$GLOBALS['gearman']->sendAsync($command);
		break;
	}
	
	// =================================
	// INSERT LOCAL SERVICE
	// =================================
	$sql = "INSERT INTO services (service_name, service_description, service_type, service_user, service_desc, service_host) VALUE ('{$username}', '".security::escape($desc)."', '{$vendor}', {$userdata['user_id']}, '{$version}', '{$server}')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('SERVICES', $user);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('service/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array("name"=>$service));
});

return $a;

?>