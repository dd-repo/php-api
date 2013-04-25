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
		request::forward('/group/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/group/select'); break;
	case 'update':
	case 'modify':
	case 'change':
	case 'rename':
		request::forward('/group/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/group/delete'); break;
	case 'grant':
	case 'grants':
		request::forward('/grant/group/index'); break;
	case 'user':
	case 'users':
	case 'member':
	case 'members':
		request::forward('/group/user/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: group</h1>
<ul>
	<li><h2><a href=\"/group/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/group/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/group/update/help\">update</a></h2> (alias : modify, change, rename)</li>
	<li><h2><a href=\"/group/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/grant/group/help\">grant</a></h2> (alias : grants)</li>
	<li><h2><a href=\"/group/user/help\">user</a></h2> (alias : users, member, members)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>