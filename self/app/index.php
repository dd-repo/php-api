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
		security::requireGrants(array('ACCESS', 'SELF_APP_INSERT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('APP_INSERT');
		request::forward('/app/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		security::requireGrants(array('ACCESS', 'SELF_APP_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('APP_SELECT');
		request::forward('/app/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		security::requireGrants(array('ACCESS', 'SELF_APP_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('APP_UPDATE');
		request::forward('/app/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		security::requireGrants(array('ACCESS', 'SELF_APP_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('APP_DELETE');
		request::forward('/app/delete'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: app</h1>
<ul>
	<li><h2><a href=\"/app/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/app/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/app/update/help\">update</a></h2> (alias : modify, change)</li>
	<li><h2><a href=\"/app/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>