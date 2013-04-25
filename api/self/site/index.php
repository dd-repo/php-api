<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'create':
	case 'add':
	case 'insert':
		security::requireGrants(array('ACCESS', 'SELF_SITE_INSERT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_INSERT');
		request::forward('/site/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		security::requireGrants(array('ACCESS', 'SELF_SITE_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_SELECT');
		request::forward('/site/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		security::requireGrants(array('ACCESS', 'SELF_SITE_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_UPDATE');
		request::forward('/site/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		security::requireGrants(array('ACCESS', 'SELF_SITE_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('SITE_DELETE');
		request::forward('/site/delete'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: site</h1>
<ul>
	<li><h2><a href=\"/site/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/site/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/site/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/site/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>