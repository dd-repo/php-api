<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a log entry");
$a->addGrant(array('ACCESS', 'LOG_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the log', 
	'params'=>'the iparams', 
	'method'=>'the method', 
	'user'=>array(array(
		'id'=>'the user id', 
		'name'=>'the username')
	),
	)));
$a->addParam(array(
	'name'=>array('search', 'keyword'),
	'description'=>'The keyword to search.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('method', 'log_method'),
	'description'=>'The method.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('start'),
	'description'=>'The start limit.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('end'),
	'description'=>'The end limit.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Return only the number of entries.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
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
	$search = $a->getParam('search');
	$method = $a->getParam('method');
	$count = $a->getParam('count');
	$user = $a->getParam('user');
	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 )
		$count = true;
	else
		$count = false;
		
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where = '';
	if( $search !== null )
		$where .= " AND (l.log_method LIKE '%".security::escape($search)."'% OR l.log_params LIKE '%".security::escape($search)."'%)";
	if( $method !== null )
		$where .= " AND l.log_method = '".security::escape($method)."'";
	if( $user !== null )
	{
		if( is_numeric($user) )
			$where .= " AND u.user_id = " . $user;
		else
			$where .= " AND u.user_name = '".security::escape($user)."'";
	}
	if( $start === null )
		$start = 0;
	if( $end === null )
		$end = 100;
	
	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT l.log_id, l.log_method, l.log_params, l.log_date, l.log_ip, u.user_id, u.user_name 
			FROM user_log l
			LEFT JOIN users u ON(u.user_id = l.log_user)
			WHERE true {$where} ORDER BY log_date DESC LIMIT {$start},{$end}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	if( $count === true )
		responder::send(array('count'=>count($result)));
	
	// =================================
	// FORMAT RESULT
	// =================================
	$logs = array();
	foreach( $result as $r )
	{		
		$l['id'] = $r['log_id'];
		$l['method'] = $r['log_method'];
		$l['params'] = $r['log_params'];
		$l['ip'] = $r['log_ip'];
		$l['date'] = $r['log_date'];
		$l['user'] = array('id'=>$r['user_id'], 'name'=>$r['user_name']);
		
		$logs[] = $l;
	}

	responder::send($logs);
});

return $a;

?>