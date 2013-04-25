<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'allow':
	case 'accept':
	case 'give':
	case 'add':
	case 'insert':
		security::requireGrants(array('ACCESS', 'SELF_TOKEN_GRANT_INSERT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('GRANT_TOKEN_INSERT');
		request::forward('/grant/token/insert');
		break;
	case 'check':
	case 'view':
	case 'list':
	case 'select':
	case 'search':
	case 'verify':
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		
		if( !request::hasParam(array('token_value', 'token')) )
			request::addParam('token', security::getToken());
		
		grantStore::add('GRANT_TOKEN_SELECT');
		request::forward('/grant/token/select');
		break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'reject':
	case 'deny':
	case 'revoke':
		security::requireGrants(array('ACCESS', 'SELF_TOKEN_GRANT_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('GRANT_TOKEN_DELETE');
		request::forward('/grant/token/delete');
		break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: <a href=\"/self/token/help\">token</a> :: grant</h1>
<ul>
	<li><h2><a href=\"/grant/token/insert/help\">insert</a></h2> (alias : allow, add, accept, give)</li>
	<li><h2><a href=\"/grant/token/select/help\">select</a></h2> (alias : list, view, search, check, verify)</li>
	<li><h2><a href=\"/grant/token/delete/help\">delete</a></h2> (alias : del, remove, reject, deny, revoke)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>