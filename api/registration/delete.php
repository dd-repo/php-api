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
	'name'=>array('name', 'user_name', 'username', 'login', 'user'),
	'description'=>'The name of the target user.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
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
	$user = $a->getParam('user');

	// =================================
	// EXECUTE QUERY
	// =================================
	$sql = "DELETE FROM register WHERE register_user='".security::escape($user)."'";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	responder::send("OK");
});

return $a;

?>