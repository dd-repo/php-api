<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('qcompute'));
$a->setDescription("Compute user quotas");
$a->addGrant(array('ACCESS', 'USER_SELECT'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('type', 'quota', 'quota_type'),
	'description'=>'The quota type.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::UPPER
	));
$a->addParam(array(
	'name'=>array('user', 'user_id', 'id'),
	'description'=>'The user name or id',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
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
	$type = $a->getParam('type');
	$user = $a->getParam('user');

	// =================================
	// GET USERS
	// =================================
	$day = date('j');
	if( $day%2 == 1 )
		$limits = 'LIMIT 0,20000';
	else
		$limits = 'LIMIT 20000,20000';
		
	if( $user !== null )
	{
		if( is_numeric($user) )
			$sql = "SELECT user_id FROM users u WHERE user_id = {$user}";
		else
			$sql = "SELECT user_id FROM users u WHERE user_name = '{$user}'";
	}
	else
		$sql = "SELECT user_id FROM users u WHERE user_id != 1 {$limits}";
		
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	// =================================
	// INIT QUOTAS
	// =================================		
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
		
	foreach( $result as $r )
	{
		// =================================
		// SYNC DOMAINS QUOTA
		// =================================
		syncQuota('DOMAINS', $r['user_id']);

		// =================================
		// SYNC SERVICES QUOTA
		// =================================
		syncQuota('SERVICES', $r['user_id']);

		// =================================
		// SYNC DISK QUOTA
		// =================================
		syncQuota('DISK', $r['user_id']);

		// =================================
		// SYNC MEMORY QUOTA
		// =================================
		syncQuota('MEMORY', $r['user_id']);
		
		// =================================
		// SYNC APPS QUOTA
		// =================================
		syncQuota('APPS', $r['user_id']);
		
		$sql = "UPDATE users SET user_last_update = UNIX_TIMESTAMP() WHERE user_id = {$r['user_id']}";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
	responder::send("OK");
});

return $a;

?>