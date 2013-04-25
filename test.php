<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$help = request::getAction(false, false);
if( $help == 'help' || $help == 'doc' )
{
	$body = "
<h1><a href=\"/help\">API Help</a> :: test</h1>
<ul>
	<li><h2>Alias :</h2> hello</li>
	<li><h2>Description :</h2> test the api</li>
	<li><h2>Parameters :</h2></li>
	<li><h2>Returns :</h2> Hello World!</li>
	<li><h2>Required grants :</h2></li>
</ul>";
	responder::help($body);
}

responder::send("Hello World!");

?>