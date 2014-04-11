<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a service");
$a->addGrant(array('ACCESS', 'SERVICE_UPDATE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('service', 'name', 'service_name'),
	'description'=>'The name of the service to remove.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>60,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('desc', 'service_desc', 'description'),
	'description'=>'The service description.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::PHRASE|request::SPECIAL,
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
	'name'=>array('pass', 'password'),
	'description'=>'The password of the service.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
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
	$service = $a->getParam('service');
	$pass = $a->getParam('pass');
	$desc = $a->getParam('desc');
	$branch = $a->getParam('branch');
	$user = $a->getParam('user');
	
	// =================================
	// GET LOCAL DATABASE INFO
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT s.service_type, s.service_user
				FROM users u
				LEFT JOIN `services` s ON(s.service_user = u.user_id)
				WHERE service_name = '".security::escape($service)."'
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	}
	else
	{
		$sql = "SELECT s.service_type, s.service_user
				FROM `services` s
				WHERE service_name = '".security::escape($service)."'";
	}
	
	$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
	if( $result == null || $result['service_type'] == null )
		throw new ApiException("Forbidden", 403, "Service {$service} not found (for user {$user} ?)");

	// =================================
	// UPDATE LOCAL SERVICE
	// =================================
	if( $desc !== null )
	{
		$sql = "UPDATE `services` SET service_description = '".security::escape($desc)."' WHERE service_name = '".security::escape($service)."'";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
	if( $branch !== null )
		$service = $service . '-' . $branch;
	
	// =================================
	// UPDATE REMOTE SERVICE
	// =================================
	if( $pass !== null )
	{
		switch( $result['service_type'] )
		{
			case 'mysql':
				$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
				$link->query("SET PASSWORD FOR '{$service}'@'%' = PASSWORD('".security::escape($pass)."')");
			break;	
			case 'pgsql':
				$commands[] = "/dns/tm/sys/usr/local/bin/update-db-pgsql {$service} ".security::escape($pass)."";
				$GLOBALS['system']->exec($commands);
			break;
			case 'mongodb':
				$commands[] = "/dns/tm/sys/usr/local/bin/update-db-mongodb {$service} ".security::escape($pass)."";
				$GLOBALS['system']->exec($commands);
			break;
		}
	}

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('service/update', $a->getParams(), $result['service_user']);
	
	responder::send("OK");
});

return $a;

?>