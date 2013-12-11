<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

function checkGrantsFromUserPass($grants = array())
{
	$user = request::getCheckParam(array(
		'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
		'optional'=>false,
		'minlength'=>1,
		'maxlength'=>30,
		'match'=>request::LOWER|request::NUMBER|request::PUNCT
		));
	$pass = request::getCheckParam(array(
		'name'=>array('pass', 'password', 'user_password', 'user_pass'),
		'optional'=>false,
		'minlength'=>3,
		'maxlength'=>30,
		'match'=>request::PHRASE|request::SPECIAL
		));
	
	// =================================
	// PREPARE USER WHERE CLAUSE
	// =================================
	if( is_numeric($user) )
		$where = "u.user_id={$user}";
	else
		$where = "u.user_name='".security::escape($user)."'";
	
	$grantlist = '';
	foreach($grants as $g)
		$grantlist .= ",'".security::escape($g)."'";
	
	// =================================
	// GET LOCAL USER
	// =================================
	$sql = "SELECT u.user_id, u.user_name, u.user_ldap FROM users u WHERE {$where}";
	$result = $GLOBALS['db']->query($sql);
	if( $result === null )
		throw new ApiException("Invalid creditentials", 403, "The creditentials provided do not match locally for user : " . $user);
	$uid = $result['user_id'];
	
	// =================================
	// CHECK REMOTE AUTHENTICATION
	// =================================
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $result['user_name']);
	$GLOBALS['ldap']->bind($dn, $pass);
	
	// =================================
	// LOAD LOCAL GRANTS
	// =================================
	$sql = "SELECT COUNT(DISTINCT k.grant_id) as grant_count
			FROM users u
			LEFT JOIN user_grant uk ON(u.user_id = uk.user_id)
			LEFT JOIN user_group ug ON(u.user_id = ug.user_id)
			LEFT JOIN group_grant gk ON(ug.group_id = gk.group_id)
			LEFT JOIN grants k ON(k.grant_id = gk.grant_id OR k.grant_id = uk.grant_id)
			WHERE u.user_id = {$uid} AND k.grant_name IN(''{$grantlist})";
	$result = $GLOBALS['db']->query($sql);
	
	if( !$result || $result['grant_count'] != 2 )
		throw new ApiException('Unsufficient privileges', 403, 'Not all required grants available : ACCESS, TOKEN_INSERT');
}

$action = request::getAction();
switch($action)
{
	case 'create':
	case 'add':
	case 'insert':
		if( security::getToken() !== null )
		{
			security::requireGrants(array('ACCESS', 'SELF_TOKEN_INSERT'));
			request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
			request::addParam('user', security::getUser());
		}
		else
		{
			checkGrantsFromUserPass(array('ACCESS', 'SELF_TOKEN_INSERT'));
			grantStore::add('ACCESS');
		}
		grantStore::add('TOKEN_INSERT');
		request::forward('/token/insert');
		break;
	case 'list':
	case 'view':
	case 'select':
	case 'search':
		if( security::getToken() !== null )
		{
			security::requireGrants(array('ACCESS', 'SELF_TOKEN_SELECT'));
			request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
			request::addParam('user', security::getUser());
		}
		else
		{
			checkGrantsFromUserPass(array('ACCESS', 'SELF_TOKEN_SELECT'));
			grantStore::add('ACCESS');
		}
		grantStore::add('TOKEN_SELECT');
		request::forward('/token/select');
		break;
	case 'update':
	case 'modify':
	case 'change':
	case 'extend':
	case 'report':
	case 'rename':
		security::requireGrants(array('ACCESS', 'SELF_TOKEN_UPDATE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('TOKEN_UPDATE');
		request::forward('/token/update');
		break;
	case 'delete':
	case 'del':
	case 'remove':
	case 'destroy':
		security::requireGrants(array('ACCESS', 'SELF_TOKEN_DELETE'));
		request::clearParam(array('user_name', 'username', 'login', 'user', 'user_id', 'uid'));
		request::addParam('user', security::getUser());
		grantStore::add('TOKEN_DELETE');
		request::forward('/token/delete');
		break;
	case 'grant':
	case 'grants':
		request::forward('/self/token/grant/index');
		break;
	case 'help':
	case 'doc':
		$body = "
<h1><a href=\"/help\">API Help</a> :: <a href=\"/self/help\">self</a> :: token</h1>
<ul>
	<li><h2><a href=\"/token/insert/help\">insert</a></h2> (alias : create, add)</li>
	<li><h2><a href=\"/token/select/help\">select</a></h2> (alias : list, view, search)</li>
	<li><h2><a href=\"/token/update/help\">update</a></h2> (alias : modify, change, rename, extend, report)</li>
	<li><h2><a href=\"/token/delete/help\">delete</a></h2> (alias : del, remove, destroy)</li>
	<li><h2><a href=\"/self/token/grant/help\">grant</a></h2> (alias : grants)</li>
</ul>";
		responder::help($body);
		break;
	default:
		throw new ApiException("Unsupported operation", 501, "Undefined action : " . $action);
}

?>