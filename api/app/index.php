<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('app', 'apps'));
$i->setDescription("An app is a container for hosted content and code.");
$i->addEntry('insert', array('insert', 'create', 'add'));
$i->addEntry('select', array('select', 'list', 'view', 'search'));
$i->addEntry('update', array('update', 'change', 'rename', 'modify'));
$i->addEntry('delete', array('delete', 'remove', 'del', 'destroy'));
$i->addEntry('start', array('start', 'boot'));
$i->addEntry('stop', array('stop', 'end'));
$i->addEntry('restart', array('restart', 'reboot'));
$i->addEntry('rebuild', array('rebuild'));
$i->addEntry('grow', array('grow'));
$i->addEntry('shrink', array('shrink'));

return $i;

?>