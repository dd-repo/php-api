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
		security::requireGrants(array('ACCESS', 'SELF_BILL_INSERT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('BILL_INSERT');
		request::forward('/bill/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		security::requireGrants(array('ACCESS', 'SELF_BILL_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('BILL_SELECT');
		request::forward('/bill/select'); break;
	case 'update':
	case 'modify':
	case 'change':
		security::requireGrants(array('ACCESS', 'SELF_BILL_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('BILL_UPDATE');
		request::forward('/bill/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		security::requireGrants(array('ACCESS', 'SELF_BILL_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('BILL_DELETE');
		request::forward('/bill/delete'); break;
	case 'insertline':
	case 'addline':
		security::requireGrants(array('ACCESS', 'SELF_BILL_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('BILL_UPDATE');
		request::forward('/bill/insertline'); break;
	case 'deleteline':
	case 'removeline':
		security::requireGrants(array('ACCESS', 'SELF_BILL_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('BILL_UPDATE');
		request::forward('/bill/deleteline'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: bill</h1>
<ul>
	<li><h2><a href=\"/bill/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/bill/update/help\">update</a></h2> (alias : update, modify, change)</li>
	<li><h2><a href=\"/bill/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/bill/delete/help\">delete</a></h2> (alias : delete, remove)</li>
	<li><h2><a href=\"/bill/insertline/help\">insertline</a></h2> (alias : insertline, addline)</li>
	<li><h2><a href=\"/bill/deleteline/help\">deleteline</a></h2> (alias : deleteline, removeline)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>