<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes a backup");
$a->addGrant(array('ACCESS', 'BACKUP_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('id', 'backup', 'backup_id'),
	'description'=>'The id of the backup.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
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
	$backup = $a->getParam('backup');
	$user = $a->getParam('user');
	
	// =================================
	// CHECK OWNER
	// =================================
	if( $user !== null )
	{
		$sql = "SELECT b.backup_id, b.backup_user, b.backup_identifier
				FROM users u
				LEFT JOIN backups b ON(b.backup_user = u.user_id)
				WHERE backup_id = {$backup}
				AND ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['backup_id'] == null )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the backup {$backup}");
	}
	else
	{
		$sql = "SELECT b.backup_id, b.backup_user, b.backup_identifier
				FROM `backups` b
				WHERE backup_name = '".security::escape($backup)."'";
		$result = $GLOBALS['db']->query($sql);
		
		if( $result == null || $result['backup_id'] == null )
			throw new ApiException("Forbidden", 403, "Backup {$backup} does not exist");
	}

	// =================================
	// DELETE REMOTE BACKUP
	// =================================
	$command = "rm /dns/com/anotherservice/download/{$result['backup_identifier']}.gz";
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// DELETE LOCAL BACKUP
	// =================================
	$sql = "DELETE FROM backups WHERE backup_id = {$backup}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('backup/delete', $a->getParams(), $result['backup_user']);
	
	responder::send("OK");
});

return $a;

?>