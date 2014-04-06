<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes a database");
$a->addGrant(array('ACCESS', 'DATABASE_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('database', 'name', 'database_name'),
	'description'=>'The name of the database to remove.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
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
	$database = $a->getParam('database');
	$user = $a->getParam('user');
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT d.database_type, d.database_user, d.database_server
				FROM users u
				LEFT JOIN `databases` d ON(d.database_user = u.user_id)
				WHERE database_name = '".security::escape($database)."'
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['database_type'] == null )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the database {$database}");
	}
	else
	{
		$sql = "SELECT d.database_type, d.database_user
				FROM `databases` d
				WHERE database_name = '".security::escape($database)."'";
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['database_type'] == null )
			throw new ApiException("Forbidden", 403, "Database {$database} does not exist");
	}

	// =================================
	// DELETE REMOTE DATABASE
	// =================================
	switch( $result['database_type'] )
	{
		case 'mysql':
			if( $result['database_server'] == 'sql.olympe.in' || $result['database_server'] == 'sql1.olympe.in' )
				$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
			else if( $result['database_server'] == 'sql2.olympe.in' )
				$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT2'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
			mysql_query("DROP USER '{$database}'", $link);
			mysql_query("DROP DATABASE `{$database}`", $link);
			mysql_close($link);
		break;
		case 'pgsql':
			$commands[] = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$database}";
			$GLOBALS['system']->exec($commands);
		break;
		case 'mongodb':
			$commands[] = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$database}";
			$GLOBALS['system']->exec($commands);
		break;
	}
	
	// =================================
	// DELETE LOCAL DATABASE
	// =================================
	$sql = "DELETE FROM `databases` WHERE database_name = '".security::escape($database)."'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// SYNC QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	syncQuota('DATABASES', $result['database_user']);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('database/delete', $a->getParams(), $result['database_user']);
	
	responder::send("OK");
});

return $a;

?>