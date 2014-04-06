<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a service");
$a->addGrant(array('ACCESS', 'SERVICE_SELECT'));
$a->setReturn(array(array(
	'name'=>'the name of the service', 
	'version'=>'the service version',
	'vendor'=>'the service vendor',
	'description'=>'the service description',
	'user'=>array(
		'id'=>'the user id', 
		'name'=>'the username'
	),
	)));
$a->addParam(array(
	'name'=>array('service', 'name', 'service_name'),
	'description'=>'The name of the service',
	'optional'=>true,
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
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	}
	
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $service !== null )
		$where .= " AND s.service_name = '".security::escape($service)."'";
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
	$sql = "SELECT s.service_name, s.service_type, s.service_desc, s.service_description, u.user_id, u.user_name 
			FROM `services` s
			LEFT JOIN users u ON(u.user_id = s.service_user)
			WHERE true {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// FORMAT RESULT
	// =================================
	$services = array();
	foreach( $result as $r )
	{
		$s['name'] = $r['service_name'];
		$s['vendor'] = $r['service_type'];
		$s['version'] = $r['service_desc'];
		$s['description'] = $r['service_description'];
		$s['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
		
		$services[] = $s;		
	}

	responder::send($services);
});

return $a;

?>