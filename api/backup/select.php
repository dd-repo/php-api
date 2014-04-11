<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a backup entry");
$a->addGrant(array('ACCESS', 'BACKUP_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the backup', 
	'type'=>'the type', 
	'user'=>array(array(
		'id'=>'the user id', 
		'name'=>'the username')
	),
	)));
$a->addParam(array(
	'name'=>array('backup', 'id', 'backup_id'),
	'description'=>'The id of the backup.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
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
	$user = $a->getParam('user');

	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $id !== null )
		$where .= " AND backup_id = {$id}";
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
	$sql = "SELECT b.backup_identifier, b.backup_id, b.backup_title, b.backup_date, b.backup_type, b.backup_auto, b.backup_url, u.user_id, u.user_name 
			FROM backups b
			LEFT JOIN users u ON(u.user_id = b.backup_user)
			WHERE true {$where} ORDER BY backup_date DESC";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	if( $count === true )
		responder::send(array('count'=>count($result)));
	
	// =================================
	// FORMAT RESULT
	// =================================
	$backups = array();
	foreach( $result as $r )
	{		
		$b['id'] = $r['backup_id'];
		$b['title'] = $r['backup_title'];
		$b['date'] = $r['backup_date'];
		$b['type'] = $r['backup_type'];
		$b['url'] = $r['backup_url'];
		$b['auto'] = $r['backup_auto'];
		$b['identifier'] = $r['backup_identifier'];
		$b['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
		
		$backups[] = $b;
	}

	responder::send($backups);
});

return $a;

?>