<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('restore'));
$a->setDescription("Restore a backup");
$a->addGrant(array('ACCESS', 'BACKUP_INSERT'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('backup', 'id', 'backup_id'),
	'description'=>'The id of the backup.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
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
	$id = $a->getParam('id');
	$branch = $a->getParam('branch');
	$user = $a->getParam('user');

	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = " AND backup_id = {$id}";
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
	$sql = "SELECT b.backup_identifier, b.backup_id, b.backup_title, b.backup_date, b.backup_type, b.backup_auto, b.backup_url, b.backup_service_name, b.backup_service_id, u.user_id, u.user_name , u.user_ldap
			FROM backups b
			LEFT JOIN users u ON(u.user_id = b.backup_user)
			WHERE true {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);

	if( $result['backup_type'] == 'full' || $result['backup_type'] == 'app' )
	{
		$dn = $GLOBALS['ldap']->getDNfromUID($result['backup_service_id']);
		$data = $GLOBALS['ldap']->read($dn);
		$command = "/dns/tm/sys/usr/local/bin/restore app {$result['backup_service_name']} {$data['homeDirectory']} {$result['backup_identifier']} {$data['gidNumber']} {$result['user_name']}";
	}
	else
	{
		$sql = "SELECT service_name, service_type, service_host FROM services WHERE service_name = '{$result['service_name']}'";
		$data = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		
		if( $branch !== null )
		{
			$result['backup_service_name'] = explode('-', $result['backup_service_name']);
			$result['backup_service_name'] = "{$result['backup_service_name'][0]}-{$result['backup_service_name'][1]}-" . security::encode($branch);
		}
		
		$command = "/dns/tm/sys/usr/local/bin/restore {$result['backup_type']} {$result['backup_service_name']} {$result['backup_identifier']} {$result['user_ldap']} {$data['service_host']} {$result['user_name']}";
	}
	$GLOBALS['gearman']->sendAsync($command);
	
	responder::send("OK");
});

return $a;

?>