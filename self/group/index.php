<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'member':
	case 'view':
	case 'list':
	case 'select':
	case 'search':
		security::requireGrants(array('ACCESS', 'SELF_GROUP_SELECT'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('GROUP_USER_SELECT');
		request::forward('/group/user/select');
		break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'quit':
	case 'leave':
	case 'exit':
	case 'unbind':
	case 'unlink':
		security::requireGrants(array('ACCESS', 'SELF_GROUP_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid', 'user_names', 'usernames', 'logins', 'users', 'user_ids', 'uids'));
		request::addParam('user', security::getUser());
		grantStore::add('GROUP_USER_DELETE');
		request::forward('/group/user/delete');
		break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: group</h1>
<ul>
	<li><h2><a href=\"/group/user/select/help\">select</a></h2> (alias : view, member, list)</li>
	<li><h2><a href=\"/group/user/delete/help\">delete</a></h2> (alias : del, remove, quit, leave, exit, unbind, unlink)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>