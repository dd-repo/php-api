<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('backup', 'backups'));
$i->setDescription("A backup is a dump of files or databases.");
$i->addEntry('insert', array('insert', 'create', 'add'));
$i->addEntry('select', array('select', 'list', 'view', 'search'));
$i->addEntry('update', array('update', 'change', 'rename', 'modify'));
$i->addEntry('delete', array('delete', 'remove', 'del', 'destroy'));
$i->addEntry('restore', array('restore'));
$i->addEntry('purge', array('purge'));

return $i;

?>
