<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a news");
$a->addGrant(array('ACCESS', 'NEWS_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('news_id', 'id'),
	'description'=>'The id of the news',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
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

	// =================================
	// DELETE NEWS
	// =================================
	$sql = "DELETE FROM news WHERE news_id = '{$id}'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	responder::send("OK");
});

return $a;

?>