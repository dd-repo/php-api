<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'remove', 'cancel'));
$a->setDescription("Removes a pending registration");
$a->addGrant(array('ACCESS', 'REGISTRATION_DELETE'));
$a->setReturn(array('OK')); 

$a->addParam(array(
	'name'=>array('id', 'code'),
	'description'=>'The code of the target registration.',
	'optional'=>true,
	'minlength'=>32,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}"
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
	$code = $a->getParam('code');

	// =================================
	// EXECUTE QUERY
	// =================================
	$sql = "DELETE FROM register WHERE register_code='".security::escape($code)."'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	responder::send("OK");
});

return $a;

?>