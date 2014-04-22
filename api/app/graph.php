<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('graph', 'graphs', 'value', 'values'));
$a->setDescription("Select graph values");
$a->addGrant(array('ACCESS', 'APP_SELECT'));
$a->setReturn(array(array(
	'id'=>'the entry id', 
	'date'=>'the entry date',
	'app'=>'the app',
	'value'=>'the value'
	)));
$a->addParam(array(
	'name'=>array('app', 'app_name', 'name'),
	'description'=>'The app name.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('branch', 'graph_branch'),
	'description'=>'The app branch.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>20,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('instance', 'instance_id'),
	'description'=>'The instance name',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('graph', 'graph_type', 'type'),
	'description'=>'The graph type.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>40,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('from'),
	'description'=>'From date.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('to'),
	'description'=>'To date.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
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
	'name'=>array('order'),
	'description'=>'Order return.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>20,
	'match'=>"(value_id|value_date|value_app)"
	));
$a->addParam(array(
	'name'=>array('order_type'),
	'description'=>'Order type.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>4,
	'match'=>"(ASC|DESC)"
	));
$a->addParam(array(
	'name'=>array('start'),
	'description'=>'Start response.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('limit'),
	'description'=>'Limit response.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('group'),
	'description'=>'Group by?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>request::UPPER
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
	$app = $a->getParam('app');
	$branch = $a->getParam('branch');
	$graph = $a->getParam('graph');
	$instance = $a->getParam('instance');
	$from = $a->getParam('from');
	$to = $a->getParam('to');
	$count = $a->getParam('count');
	$order = $a->getParam('order');
	$order_type = $a->getParam('order_type');
	$start = $a->getParam('start');
	$limit = $a->getParam('limit');
	$group = $a->getParam('group');
	$user = $a->getParam('user');

	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 )
		$count = true;
	else
		$count = false;
		
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$limitation = '';
	$where = '';
	if( $app !== null )
		$where .= " AND value_app = '{$app}'";
	if( $branch !== null )
		$where .= " AND value_branch = '{$branch}'";
	if( $graph !== null )
		$where .= " AND value_graph = '{$graph}'";
	if( $instance !== null )
		$where .= " AND value_instance = '{$instance}'";
	if( $from !== null )
		$where .= " AND value_date >= {$from}";
	if( $to !== null )
		$where .= " AND value_date <= {$to}";
	if( $start !== null && $limit !== null )
		$limitation .= $start . ", " . $limit;
	else
		$limitation .= "0, 12";
			
	if( $order === null )
		$order = 'value_date';
	if( $ordered === null )
		$ordered = 'DESC';
		
	// =================================
	// SELECT RECORDS
	// =================================
	if( $group !== null )
		$sql = "SELECT AVG(value_number) as average, {$group} (FROM_UNIXTIME(value_date)) as {$group} FROM graphs_value WHERE 1 {$where} GROUP BY {$group} (FROM_UNIXTIME(value_date)) ORDER BY {$order} {$ordered}";
	if( $count === true )
		$sql = "SELECT COUNT(value_id) as count FROM graphs_value WHERE 1 {$where} ORDER BY {$order} {$ordered} LIMIT {$limitation}";	
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	responder::send($result);
});

return $a;

?>