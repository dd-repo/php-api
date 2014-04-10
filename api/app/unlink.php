<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('unlink'));
$a->setDescription("Unlink and delete subservice");
$a->addGrant(array('ACCESS', 'SERVICE_DELETE'));
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
	'name'=>array('branch', 'env', 'environment'),
	'description'=>'The environement of the app',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>request::LOWER,
	));	
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
	$app = $a->getParam('app');
	$branch = $a->getParam('branch');
	$service = $a->getParam('service');
	$user = $a->getParam('user');

	// =================================
	// GET APP DN
	// =================================
	if( is_numeric($app) )
		$dn = $GLOBALS['ldap']->getDNfromUID($app);
	elseif( $domain != null )
		$dn = ldap::buildDN(ldap::APP, $domain, $app);
	else
		throw new ApiException("Can not find app", 412, "Can not find app without domain");
	
	// =================================
	// GET APP DATA
	// =================================	
	if( $dn )
		$data = $GLOBALS['ldap']->read($dn);	
	else
		throw new ApiException("Not found", 404, "Can not find app: {$app}");
		
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

		// =================================
		// GET REMOTE USER DN
		// =================================	
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
		if( $data['owner'] != $user_dn )
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
	$sql = "SELECT s.service_name, s.service_type, s.service_desc, s.service_description, s.service_host, u.user_id, u.user_name 
			FROM `services` s
			LEFT JOIN users u ON(u.user_id = s.service_user)
			WHERE true {$where}";
	$result = $GLOBALS['db']->query($sql);
	
	if( !$result['service_name'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the service {$service}");
		
	// =================================
	// DELETE SERVICE
	// =================================	
	$sql = "UPDATE services SET service_app = '' WHERE service_name = '".security::escape($service)."'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);		
	
	if( $branch != 'master' )
	{
		$new_service = $result['service_name'] . '-' . security::encode($branch);
		
		switch( $result['service_type'] )
		{
			case 'mysql':
				$link = mysql_connect($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'] . ':' . $GLOBALS['CONFIG']['MYSQL_ROOT_PORT'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD']);
				mysql_query("DROP USER '{$new_service}'", $link);
				mysql_query("DROP DATABASE `{$new_service}`", $link);
				mysql_close($link);
			break;
			case 'pgsql':
				$command = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$new_service}";
				$GLOBALS['gearman']->sendAsync($command);
			break;
			case 'mongodb':
				$command = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$new_service}";
				$GLOBALS['gearman']->sendAsync($command);
			break;
		}

		// =================================
		// DELETE SUBSERVICE
		// =================================			
		$sql = "DELETE FROM service_branch WHERE service_name = '{$result['service_name']}' AND branch_name = '".security::escape($branch)."' AND app_id = '{$data['uidNumber']}'";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('app/unlink', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");	
});

return $a;

?>
