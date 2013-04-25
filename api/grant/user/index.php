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
		request::forward('/grant/user/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
	case 'check':
	case 'verify':
		request::forward('/grant/user/select'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'reject':
	case 'deny':
	case 'revoke':
		request::forward('/grant/user/delete'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: user</h1>
<ul>
	<li><h2><a href=\"/grant/user/insert/help\">insert</a></h2> (alias : allow, add, accept, give)</li>
	<li><h2><a href=\"/grant/user/select/help\">select</a></h2> (alias : list, view, search, check, verify)</li>
	<li><h2><a href=\"/grant/user/delete/help\">delete</a></h2> (alias : del, remove, reject, deny, revoke)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>