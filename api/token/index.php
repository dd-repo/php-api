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
		request::forward('/token/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/token/select'); break;
	case 'update':
	case 'modify':
	case 'change':
	case 'extend':
	case 'report':
	case 'rename':
		request::forward('/token/update'); break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		request::forward('/token/delete'); break;
	case 'purge':
	case 'clean':
	case 'cleanup':
		request::forward('/token/purge'); break;
	case 'grant':
	case 'grants':
		request::forward('/grant/token/index'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: token</h1>
<ul>
	<li><h2><a href=\"/token/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/token/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/token/update/help\">update</a></h2> (alias : modify, change, rename, extend, report)</li>
	<li><h2><a href=\"/token/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/token/purge/help\">purge</a></h2> (alias : clean)</li>
	<li><h2><a href=\"/grant/token/help\">grant</a></h2> (alias : grants)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>