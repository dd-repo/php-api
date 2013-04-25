<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$i = new index();
$i->addAlias(array('self', 'me', 'myself'));
$i->setDescription("All 'self' actions are shortcuts that will be executed with the current logged user as target for every action that allows a 'user' parameter.
					Note that privilegied actions will be called with specific grants.");
$i->addEntry('/user/select', array('select', 'view', 'whoami', 'detail', 'details', 'list'), function() use ($i)
{
	security::requireGrants(array('ACCESS', 'SELF_SELECT'));
	request::clearParam(array('name', 'user_name', 'username', 'login', 'user', 'names', 'user_names', 'usernames', 'logins', 'users', 'id', 'user_id', 'uid', 'ids', 'user_ids', 'uids'));
	request::addParam('user', security::getUser());
	grantStore::add('USER_SELECT');
});
$i->addEntry('/user/update', array('update', 'modify', 'change'), function() use ($i)
{
	security::requireGrants(array('ACCESS', 'SELF_UPDATE'));
	request::clearParam(array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'));
	request::addParam('user', security::getUser());
	grantStore::add('USER_UPDATE');
});
$i->addEntry('/user/delete', array('delete', 'del', 'remove', 'destroy', 'suicide'), function() use ($i)
{
	security::requireGrants(array('ACCESS', 'SELF_DELETE'));
	request::clearParam(array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'));
	request::addParam('user', security::getUser());
	grantStore::add('USER_DELETE');
});
$i->addEntry('/quota/user/select', array('quota', 'quotas', 'limit', 'limits'), function() use ($i)
{
	security::requireGrants(array('ACCESS', 'SELF_QUOTA_SELECT'));
	request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
	request::addParam('user', security::getUser());
	grantStore::add('QUOTA_USER_SELECT');
});
$i->addEntry('/grant/user/select', array('grant', 'grants', 'access', 'can', 'check'), function() use ($i)
{
	security::requireGrants(array('ACCESS', 'SELF_GRANT_SELECT'));
	request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
	request::addParam('user', security::getUser());
	request::clearParam('overall');
	request::addParam('overall', 'true');
	grantStore::add('GRANT_USER_SELECT');
});
$i->addEntry('token', array('token', 'tokens'));
$i->addEntry('group', array('group', 'groups'));
$i->addEntry('domain', array('domain', 'domains'));
$i->addEntry('subdomain', array('subdomain', 'subdomains'));
$i->addEntry('app', array('app', 'apps'));
$i->addEntry('service', array('service', 'services'));
$i->addEntry('account', array('account', 'accounts'));
$i->addEntry('team', array('team', 'teams'));
$i->addEntry('repo', array('repo', 'repos'));
$i->addEntry('bill', array('bill', 'bills'));

return $i;

?>