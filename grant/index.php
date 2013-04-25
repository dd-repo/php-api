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
		request::forward('/grant/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/grant/select'); break;
	case 'update':
	case 'modify':
	case 'change':
	case 'rename':
		request::forward('/grant/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/grant/delete'); break;
	case 'user':
	case 'users':
		request::forward('/grant/user/index'); break;
	case 'group':
	case 'groups':
		request::forward('/grant/group/index'); break;
	case 'token':
	case 'tokens':
		request::forward('/grant/token/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: grant</h1>
<ul>
	<li><h2><a href=\"/grant/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/grant/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/grant/update/help\">update</a></h2> (alias : modify, change, rename)</li>
	<li><h2><a href=\"/grant/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/grant/user/help\">user</a></h2> (alias : users)</li>
	<li><h2><a href=\"/grant/group/help\">group</a></h2> (alias : groups)</li>
	<li><h2><a href=\"/grant/token/help\">token</a></h2> (alias : tokens)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>