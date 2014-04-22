<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$howto = "<h1>How to use the API</h1>
<ul><li><h2>Introduction</h2> 
	The API is a URL-HTTP-based simple interface to view information or act on the system.<br />
	It all starts with an <em>action</em> you want to perform. For some actions, <em>parameters</em> may be necessary to provide more information about what you want to do.<br />
	Also, for some actions you will have to provide proper <em>creditentials</em> in order to check if you have the permission to proceed.
</li><li><h2>HTTP Method</h2> 
	The API is HTTP method independant, you can use POST or GET or both to perform requests.
</li><li><h2>Actions</h2> 
	The action you want to perform is composed of 'words' separated by forward slashes just like a URL. <br />
	Hence, you may type it in the URL, but this is not mandatory: you may also use parameters to specify the action you want to perform. <br />
	You will notice that many actions have one or more <em>alias</em>. This is for convenience and means exactly the same.<br />
	Note that when using parameters to specify the action, the actual URL has precedence and there is not order guarantee for the remaining parameters.
</li><li><h2>Parameters</h2>
	You may provide parameters to specify or refine the action you wish to perform.<br />
	There are various ways to provide parameters: via the URL (GET) or either via the content of the request (POST). <br />
	Moreover, in any or both of those, you may use additionnal 'languages' to pass parameters: json or xml. <br />
	In order to provide json-encoded OR xml-encoded parameters, use a wrapper parameter in the content or in the url.<br />
	Some parameters accept multiple values, in this case, use a coma-separated (coma, semicolon, space or tab) list or a xml or json array. Note that if multiple values are specified whereas a single value was expected, it will produce an error.<br />
	Some parameters are required in which case you must provide it otherwise it will produce an error.<br />
	Some parameters are optional, hence you may ommit them or send a null or empty value.<br />
	Some parameters are urlizable, in which case, thay may be appended to the action.<br />
	Just like actions, parameters may also have one or more alias for convenience. However, note that if you provide several different alias in the same request, values will be merged like a multiple value.<br />
	You may mix any ways to pass parameters (GET, POST, JSON, XML) at the same time, but if you set parameters more than once they will be merged like multiple values.
	Parameters and actions are case insensitive.<br />
	<h3>Predefined parameters</h3> (coma-sparated values are alias)
	<ul>
		<li>".implode(',',$GLOBALS['CONFIG']['PARAMETERS']['ACTION'])." : the name of the action parameter if not specified in the url.</li>
		<li>".implode(',',$GLOBALS['CONFIG']['PARAMETERS']['XML'])." : the name of the parameter that should be interpreted as xml.</li>
		<li>".implode(',',$GLOBALS['CONFIG']['PARAMETERS']['JSON'])." : the name of the patameter that should be interpreted as json.</li>
		<li>".implode(',',$GLOBALS['CONFIG']['PARAMETERS']['FORMAT'])." : the name of the output type parameter.</li>
		<li>".implode(',',$GLOBALS['CONFIG']['PARAMETERS']['NOHTTP'])." : the name of the parameter to specify to always use 200 http header status.</li>
		<li>".implode(',',$GLOBALS['CONFIG']['PARAMETERS']['TOKEN'])." : the name of the parameter to specify the creditentials for a particular request.</li>
	</ul>
</li><li><h2>Response</h2>
	The response of the request will always be sent back. All actions have a response.<br />
	Some responses are very basic but some other may produce complex lists of elements.<br />
	Hence, for convenience, you may choose how the API will respond, using the output type parameter. Currently supported response types are : json, xml, php, dump and html.<br />
	If no output type is provided, json is assumed.<br />
	Note that xml array values are not supported by the norm, hence tags named <em>item_X</em> (where X is the index of the element) will be used.
</li><li><h2>Errors</h2>
	Some errors may occur while calling the API. Those are based on the http status codes but use those only as a code/name hint. <br />
	Errors are formatted like responses (using the output type) and always contain a numeric <em>code</em> and a short explanatory <em>message</em>.<br />
	Along with the response, the http status header will be set to match the error code. However, you can disable this behavior using the proper parameter.<br />
	Note that non-API errors may happen, thus it will not match the API error behavior and will fallback on regular http errors.<br />
	<h3>List of potential errors</h3>
	<ul>
		<li>200 : OK</li>
		<li>400 : Bad Request</li>
		<li>401 : Unauthorized</li>
		<li>403 : Forbidden</li>
		<li>404 : Not Found</li>
		<li>405 : Method Not Allowed</li>
		<li>406 : Not Acceptable</li>
		<li>409 : Conflict</li>
		<li>412 : Precondition Failed</li>
		<li>500 : Internal Server Error</li>
		<li>501 : Not Implemented</li>
		<li>503 : Service Unavailable</li>
	</ul>
</li><li><h2>Security</h2>
	You should use the API using https encryption ! <br />
	Every single parameter is checked against a strict set of rules. If any parameter does not match, an error (412) will be produced.<br />
	<h3>Authentication and authorization</h3> : <br />
	For most requests, you will need to provide creditentials. In some rare cases, you may provide your username and password, but this is less secure as you cannot restrict possible actions : everything you are allowed to do may be done.<br />
	Usually, you will need to provide a user:token (the username followed by colon followed by the token) pair in order to identify and authorize yourself.<br />
	You may create (or modify or delete) tokens that have only a subset of your available roles; hence, you may share a token with friends or applications to give those only specific restricted permissions.<br />
	Note that if you lose some privileges, all tokens for which you had enabled those will also lose that role.<br />
	Tokens also have a validity period (which may be set to be permanent) in order to allow access only in a particular time-frame period.<br />
	With those tokens, you may truly choose who may access which features of your account.
</li></ul>";

$i = new index();
$i->setDescription($howto);
$i->addEntry('self', array('self', 'me', 'myself'));
$i->addEntry('user', array('user', 'users'));
$i->addEntry('group', array('group', 'groups'));
$i->addEntry('token', array('token', 'tokens'));
$i->addEntry('grant', array('grant', 'grants'));
$i->addEntry('quota', array('quota', 'quotas'));
$i->addEntry('registration', array('registration', 'registrations'));
$i->addEntry('domain', array('domain', 'domains'));
$i->addEntry('alias', array('alias', 'aliases'));
$i->addEntry('subdomain', array('subdomain', 'subdomains'));
$i->addEntry('account', array('account', 'accounts'));
$i->addEntry('team', array('team', 'teams'));
$i->addEntry('app', array('app', 'apps'));
$i->addEntry('bill', array('bill', 'bills'));
$i->addEntry('service', array('service', 'services'));
$i->addEntry('repo', array('repo', 'repos'));
$i->addEntry('news', array('news'));
$i->addEntry('backup', array('backup', 'backups'));
$i->addEntry('log', array('log', 'logs'));
$i->addEntry('test', array('test', 'hello'));

return $i;

?>