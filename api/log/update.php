<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Edit a database");
$a->addGrant(array('ACCESS', 'DATABASE_UPDATE'));
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
	'name'=>array('pass', 'password'),
	'description'=>'The password of the account.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('desc', 'description'),
	'description'=>'The description of the database.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL
	));
$a->addParam(array(
	'name'=>array('server', 'database_server'),
	'description'=>'The server of the database',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>15,
	'match'=>"(sql.olympe.in|sql1.olympe.in|sql2.olympe.in)"
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
	$pass = $a->getParam('pass');
	$desc = $a->getParam('desc');
	$server = $a->getParam('server');
	$user = $a->getParam('user');
	
	// =================================
	// GET LOCAL DATABASE INFO
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT d.database_type, d.database_server, d.database_user
				FROM users u
				LEFT JOIN `databases` d ON(d.database_user = u.user_id)
				WHERE database_name = '".security::escape($database)."'
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	}
	else
	{
		$sql = "SELECT d.database_type, d.database_server, d.database_user
				FROM `databases` d
				WHERE database_name = '".security::escape($database)."'";
	}
	
	$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
	if( $result == null || $result['database_type'] == null )
		throw new ApiException("Forbidden", 403, "Database {$database} not found (for user {$user} ?)");

	// =================================
	// UPDATE LOCAL DATABASE
	// =================================
	$set = '';
	if( $desc !== null )
		$set .= ", database_desc = '".security::escape($desc)."'";
	if( $server !== null )
		$set .= ", database_server = '".security::escape($server)."'";
		
	$sql = "UPDATE `databases` SET database_name = database_name {$set} WHERE database_name = '".security::escape($database)."'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// UPDATE REMOTE DATABASE
	// =================================
	if( $server !== null && $server != $result['database_server'] & $pass !== null )
	{
		switch( $result['database_type'] )
		{
			case 'mysql':
				$commands[] = "/dns/tm/sys/usr/local/bin/migrate-db-mysql {$database} ".security::escape($pass)." {$result['database_server']} {$server}";
				$GLOBALS['system']->exec($commands);
			break;
		}
	}
	else if( $pass !== null )
	{
		switch( $result['database_type'] )
		{
			case 'mysql':
				if( $result['database_server'] == 'sql.olympe.in' || $result['database_server'] == 'sql1.olympe.in' )
					$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
				else if( $result['database_server'] == 'sql2.olympe.in' )
					$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT2'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
				mysql_query("SET PASSWORD FOR '{$database}'@'%' = PASSWORD('".security::escape($pass)."')", $link);
				mysql_close($link);
			break;
		case 'pgsql':
			$commands[] = "/dns/tm/sys/usr/local/bin/update-db-pgsql {$database} ".security::escape($pass)."";
			$GLOBALS['system']->exec($commands);
		break;
		case 'mongodb':
			$commands[] = "/dns/tm/sys/usr/local/bin/update-db-mongodb {$database} ".security::escape($pass)."";
			$GLOBALS['system']->exec($commands);
		break;
		}
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('database/update', $a->getParams(), $result['database_user']);
	
	responder::send("OK");
});

return $a;

?>