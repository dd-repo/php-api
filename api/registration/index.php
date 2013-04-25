<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('registration', 'registrations'));
$i->setDescription("A registration is a ticket for user creation.");
$i->addEntry('insert', array('join', 'register', 'signup', 'subscribe', 'insert', 'create', 'add'));
$i->addEntry('select', array('select', 'list', 'view', 'search'));
$i->addEntry('delete', array('delete', 'remove', 'del', 'cancel'));

return $i;

?>