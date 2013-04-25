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
		request::forward('/quota/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/quota/select'); break;
	case 'update':
	case 'modify':
	case 'change':
	case 'rename':
		request::forward('/quota/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/quota/delete'); break;
	case 'user':
	case 'users':
		request::forward('/quota/user/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: quota</h1>
<ul>
	<li><h2><a href=\"/quota/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/quota/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/quota/update/help\">update</a></h2> (alias : modify, change, rename)</li>
	<li><h2><a href=\"/quota/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/quota/user/help\">user</a></h2> (alias : users)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>