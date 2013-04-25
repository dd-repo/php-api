<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'link':
	case 'bind':
	case 'enter':
	case 'join':
	case 'add':
	case 'insert':
		request::forward('/group/user/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/group/user/select'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'leave':
	case 'unbind':
	case 'exit':
	case 'unlink':
	case 'quit':
		request::forward('/group/user/delete'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/group/help\">group</a> :: user</h1>
<ul>
	<li><h2><a href=\"/group/user/insert/help\">insert</a></h2> (alias : link, bind, enter, join, add)</li>
	<li><h2><a href=\"/group/user/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/group/user/delete/help\">delete</a></h2> (alias : del, remove, leave, unbind, exit, unlink, quit)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>