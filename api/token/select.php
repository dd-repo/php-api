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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/token/help\">token</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> searches for a token of the target user</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the target user. <span class=\"required\">required</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>pass : The password of the user if not using token authentication. <span class=\"optional\">optional</span>. (alias : password, user_pass, user_password)</li>
			<li>lease : The lease time of the token (0 or 'never' will not expire; before now's timestamp is considered seconds from now). <span class=\"optional\">optional</span>. (alias : timeout, time, length, ttl, expire, token_lease)</li>
			<li>token : The token values to list. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : value, token_value, values, token_values, tokens)</li>
			<li>name : A name filter for the search. <span class=\"optional\">optional</span>. (alias : token_name, label, description, like, filter)</li>
			<li>before : A time filter for tokens that expire before the provided timestamp. <span class=\"optional\">optional</span>. (alias : expired, expire_before, ended, valid_before, lease_before)</li>
			<li>after : A time filter for tokens that expire after the provided timestamp. <span class=\"optional\">optional</span>. (alias : expire, expire_after, end, ends, valid_after, lease_after, leased, lease)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching tokens [{'name', 'token', 'lease'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, TOKEN_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'TOKEN_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$valueFilters = request::getCheckParam(array(
	'name'=>array('value', 'token_value', 'token', 'tokens', 'values', 'token_values'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}",
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));
$nameFilter = request::getCheckParam(array(
	'name'=>array('name', 'like', 'filter', 'token_name', 'description', 'label'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>request::PHRASE|request::SPECIAL
	));
$beforeFilter = request::getCheckParam(array(
	'name'=>array('before', 'expired', 'expire_before', 'ended', 'valid_before', 'lease_before'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>11,
	'match'=>request::NUMBER
	));
$afterFilter = request::getCheckParam(array(
	'name'=>array('after', 'expire', 'expire_after', 'end', 'ends', 'valid_after', 'lease_after', 'leased', 'lease'),
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>11,
	'match'=>"(never|[0-9]{1,11})"
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
if( is_numeric($user) )
	$where = "u.user_id=".$user;
else
	$where = "u.user_name = '".security::escape($user)."'";

if( $valueFilters !== null && count($valueFilters) > 0 )
{
	$where .= " AND (false";
	foreach( $valueFilters as $vf )
		$where .= " OR t.token_value LIKE '%".security::escape($vf)."%'";
	$where .= ")";
}
if( $nameFilter !== null )
	$where .= " AND t.token_name LIKE '%".security::escape($nameFilter)."%'";
if( $beforeFilter !== null )
{
	if( $beforeFilter < 946684800 ) // 1st Jan 2000
		$beforeFilter = time() + $beforeFilter;
	$where .= " AND t.token_lease < " . $beforeFilter;
}
if( $afterFilter !== null )
{
	if( $afterFilter == 'never' )
		$afterFilter = 0;
	if( $beforeFilter !== null && ($afterFilter == 0 || $afterFilter > $beforeFilter) )
		throw new ApiException("Impossible date filter", 400, "Invalid date interval : before " . $beforeFilter . " and after " . $afterFilter);
	
	if( $afterFilter < 946684800 ) // 1st Jan 2000
		$afterFilter = time() + $afterFilter;

	if( $afterFilter == 0 )
		$where .= " AND t.token_lease = 0";
	else
		$where .= " AND t.token_lease > " . $afterFilter;
}

// =================================
// SELECT RECORDS
// =================================
$sql = "SELECT t.token_value, t.token_name, t.token_lease
		FROM tokens t
		LEFT JOIN users u ON(u.user_id = t.token_user)
		WHERE {$where} ORDER BY t.token_name";
$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

// =================================
// FORMAT RESULT
// =================================
$tokens = array();
foreach( $result as $r )
	$tokens[] = array('name'=>$r['token_name'], 'token'=>$r['token_value'], 'lease'=>$r['token_lease']);

responder::send($tokens);

?>