<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$action = request::getAction();
switch($action)
{
	case 'add':
	case 'insert':
		request::forward('/quota/user/insert'); break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		request::forward('/quota/user/select'); break;
	case 'delete':
	case 'del':
	case 'remove':
		request::forward('/quota/user/delete'); break;
	case 'modify':
	case 'fix':
	case 'define':
	case 'raise':
	case 'lower':
	case 'consume':
	case 'free':
	case 'change':
		request::forward('/quota/user/update'); break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: user</h1>
<ul>
	<li><h2><a href=\"/quota/user/insert/help\">insert</a></h2> (alias : link, bind, enter, join, add)</li>
	<li><h2><a href=\"/quota/user/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/quota/user/delete/help\">delete</a></h2> (alias : del, remove, leave, unbind, exit, unlink, quit)</li>
	<li><h2><a href=\"/quota/user/update/help\">update</a></h2> (alias : modify, fix, define, raise, lower, consume, free, change)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>