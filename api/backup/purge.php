<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('purge'));
$a->setDescription("Purge old backups");
$a->addGrant(array('ACCESS', 'BACKUP_DELETE'));
$a->setReturn("OK");

$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

    // HOW MANY SECONDS IN A DAY, A WEEK, A MONTH or A YEAR
    $S_DAY = 60 * 60 * 24;
    $S_WEEK = $S_DAY * 7;
    $S_MONTH = $S_DAY * 30;
    $S_YEAR = $S_DAY * 365;

	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
    // delete backups older than 5 years
	$where = "UNIX_TIMESTAMP() - backup_date > 5*$S_YEAR";
    // for backup older than 1 year, keep 1 backup per year
    $where .= " OR ( UNIX_TIMESTAMP() - backup_date > $S_YEAR AND MONTH(FROM_UNIXTIME( backup_date )) != 1 )";
    // for backup older than 1 month, keep 1 backup per month 
    $where .= " OR ( UNIX_TIMESTAMP() - backup_date > $S_MONTH AND DAYOFMONTH(FROM_UNIXTIME( backup_date )) > 7 )";
    // for backup older than 1 week, keep 1 backup per week 
    $where .= " OR ( UNIX_TIMESTAMP() - backup_date > $S_WEEK AND DAYOFWEEK(FROM_UNIXTIME( backup_date )) != 2 )";

	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT backup_identifier FROM backups WHERE {$where};";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	foreach( $result as $backup )
	{
        $command = "[ -f /dns/com/anotherservice/download/{$backup['backup_identifier']}.gz ] && rm /dns/com/anotherservice/download/{$backup['backup_identifier']}.gz";
        $GLOBALS['gearman']->sendAsync($command);

        $sql = "DELETE FROM backups WHERE backup_identifier = '{$backup['backup_identifier']}'";
        $GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
    // =================================
    // LOG ACTION
    // =================================    
    logger::insert('backup/purge', $a->getParams());

	responder::send("OK");
});

return $a;
?>
