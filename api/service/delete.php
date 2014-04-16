<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a service");
$a->addGrant(array('ACCESS', 'SERVICE_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('service', 'name', 'service_name'),
	'description'=>'The name of the service',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
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
	$service = $a->getParam('service');
	$user = $a->getParam('user');
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT s.service_name, s.service_user, s.service_type, s.service_user
				FROM users u
				LEFT JOIN services s ON(s.service_user = u.user_id)
				WHERE service_name = '".security::escape($service)."'
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['service_name'] == null )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the service {$service}");
	}
	else
	{
		$sql = "SELECT s.service_name, s.service_user, s.service_type, s.service_user
				FROM services s
				LEFT JOIN users u ON(u.user_id = s.service_user)
				WHERE service_name = '".security::escape($service)."'";
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['service_name'] == null )
			throw new ApiException("Forbidden", 403, "Service {$service} does not exist");
	}

	// =================================
	// DELETE REMOTE DATABASE
	// =================================
	switch( $result['service_type'] )
	{
		case 'mysql':
			$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
			$link->query("DROP USER '{$service}'");
			$link->query("DROP DATABASE `{$service}-master`");
		break;
		case 'pgsql':
			$command = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$service} {$service}-master";
			$GLOBALS['gearman']->sendAsync($command);
		break;
		case 'mongodb':
			$command = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$service} {$service}-master";
			$GLOBALS['gearman']->sendAsync($command);
		break;
	}

	// =================================
	// DELETE SUBSERVICES
	// =================================
	$sql = "SELECT * FROM service_branch WHERE service_name = '".security::escape($service)."'";
	$subservices = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	foreach( $subservices as $s )
	{
		$subservice = $service . '-' . $s['branch_name'];
		switch( $result['service_type'] )
		{
			case 'mysql':
				$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
				$link->query("DROP USER '{$subservice}'");
				$link->query("DROP DATABASE `{$subservice}`");
			break;
			case 'pgsql':
				$command = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$subservice}";
				$GLOBALS['gearman']->sendAsync($command);
			break;
			case 'mongodb':
				$command = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$subservice}";
				$GLOBALS['gearman']->sendAsync($command);
			break;
		}
	}
	
	// =================================
	// DELETE LOCAL SERVICE
	// =================================
	$sql = "DELETE FROM services WHERE service_name = '".security::escape($service)."'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// SYNC QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	syncQuota('SERVICES', $result['service_user']);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('service/delete', $a->getParams(), $result['service_user']);
	
	responder::send("OK");
});

return $a;

?>