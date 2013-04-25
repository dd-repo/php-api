<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('user', 'users'));
$i->setDescription("A user allows platform authentification.");
$i->addEntry('insert', array('insert', 'create', 'add'));
$i->addEntry('select', array('select', 'list', 'view', 'search'));
$i->addEntry('update', array('update', 'change', 'rename', 'modify'));
$i->addEntry('delete', array('delete', 'remove', 'del', 'destroy'));
$i->addEntry('qcompute', array('qcompute'));
$i->addEntry('bcompute', array('bcompute'));
$i->addEntry('/group/user/index', array('group', 'groups'));
$i->addEntry('/grant/user/index', array('grant', 'grants'));
$i->addEntry('/quota/user/index', array('quota', 'quotas'));

return $i;

?>