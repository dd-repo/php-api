<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new database");
$a->addGrant(array('ACCESS', 'DATABASE_INSERT'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('type', 'database_type', 'db_type'),
	'description'=>'The type of database.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>12,
	'match'=>"(mysql|pgsql|mongodb)"
	));
$a->addParam(array(
	'name'=>array('pass', 'password'),
	'description'=>'The password of the account.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('desc', 'description'),
	'description'=>'The description of the database.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL
	));
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>1,
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
	$type = $a->getParam('type');
	$pass = $a->getParam('pass');
	$desc = $a->getParam('desc');
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
	checkQuota('DATABASES', $user);

	// =================================
	// GENERATE NAME
	// =================================
	while(true)
	{
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$base = '';
		for( $u = 1; $u <= 8; $u++ )
		{
			$number = strlen($chars);
			$number = mt_rand(0,($number-1));
			$base .= $chars[$number];
		}
		
		// check if that database name already exists
		$sql = "SELECT database_name FROM `databases` WHERE database_name='{$base}'";
		$exists = $GLOBALS['db']->query($sql);
		if( $exists == null || $exists['database_name'] == null )
			break;
	}

	// =================================
	// INSERT REMOTE DATABASE
	// =================================
	switch( $type )
	{
		case 'mysql':
			$server = 'sql2.olympe.in';
			if( $server == 'sql.olympe.in' || $server == 'sql1.olympe.in' )
				$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
			else if( $server == 'sql2.olympe.in' )
				$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT2'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
			mysql_query("CREATE USER '{$base}'@'%' IDENTIFIED BY '{$pass}'", $link);
			mysql_query("CREATE DATABASE `{$base}` CHARACTER SET utf8 COLLATE utf8_unicode_ci", $link);
			mysql_query("GRANT USAGE ON * . * TO '{$base}'@'%' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0", $link);
			mysql_query("GRANT ALL PRIVILEGES ON `{$base}` . * TO '{$base}'@'%'", $link);
			mysql_query("FLUSH PRIVILEGES", $link);
			mysql_close($link);
		break;
		case 'pgsql':
			$server = 'sql.olympe.in';
			$commands[] = "/dns/tm/sys/usr/local/bin/create-db-pgsql {$base} {$pass} {$server}";
			$GLOBALS['system']->exec($commands);
		break;
		case 'mongodb':
			$server = 'sql.olympe.in';
			$commands[] = "/dns/tm/sys/usr/local/bin/create-db-mongodb {$base} {$pass} {$server}";
			$GLOBALS['system']->exec($commands);
		break;
	}
	
	// =================================
	// INSERT LOCAL DATABASE
	// =================================
	$sql = "INSERT INTO `databases` (database_name, database_type, database_user, database_desc, database_server, database_date) VALUE ('{$base}', '{$type}', {$userdata['user_id']}, '".security::escape($desc)."', '{$server}', UNIX_TIMESTAMP())";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('DATABASES', $user);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('database/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array("name"=>$base));
});

return $a;

?>