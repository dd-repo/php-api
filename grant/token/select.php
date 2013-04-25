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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: <a href=\"/grant/token/help\">token</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search, check, verify</li>
	<li><h2>Description :</h2> lists all grants of a token (of a user) or all tokens of a grant</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the grants to search for. <span class=\"optional\">optional</span>. (alias : grant_name, grant_id, kid)</li>
			<li>token : The value of the token to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : token_name)</li>
			<li>user : The name or id of the target user. <span class=\"optional\">optional</span>. (alias : user_name, username, login, user_id, uid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching tokens [{'user':{'name', 'id'},'token'},...] or grants [{'name', 'id'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_TOKEN_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_TOKEN_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant', 'grant_name', 'grant_id', 'kid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT
	));
$token = request::getCheckParam(array(
	'name'=>array('token_value', 'token'),
	'optional'=>true,
	'minlength'=>32,
	'maxlength'=>32,
	'match'=>"[a-fA-F0-9]{32,32}",
	'action'=>true
	));
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));

if( $grant !== null && $token !== null )
	throw new ApiException("Too many parameters", 412, "Cannot specify both grant ({$grant}} and token ({$token})");
if( $grant === null && $token === null )
	throw new ApiException("Missing conditional parameter", 412, "Cannot specify none of grant and token");
if( $token !== null && $user === null )
	throw new ApiException("Missing conditional parameter", 412, "Must specify the user if targetting a token");

// =================================
// EXECUTE QUERY FOR GRANT
// =================================
if( $grant !== null )
{
	if( is_numeric($grant) )
		$where = "k.grant_id=".$grant;
	else
		$where = "k.grant_name = '".security::escape($grant)."'";
	
	if( $user !== null )
	{
		if( is_numeric($user) )
			$where .= " AND u.user_id=".$user;
		else
			$where .= " AND u.user_name = '".security::escape($user)."'";
	}
	
	$sql = "SELECT t.token_value, u.user_id, u.user_name
			FROM users u
			LEFT JOIN tokens t ON(t.token_user = u.user_id)
			LEFT JOIN token_grant tk ON(t.token_id = tk.token_id)
			LEFT JOIN grants k ON(k.grant_id = tk.grant_id)
			WHERE {$where}
			ORDER BY u.user_name, t.token_value";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$tokens = array();
	foreach( $result as $r )
		if( $r['user_id'] != null )
			$tokens[] = array('token'=>$r['token_value'], 'user'=>array('name'=>$r['user_name'], 'id'=>$r['user_id']));
	
	responder::send($tokens);
}

// =================================
// EXECUTE QUERY FOR TOKEN
// =================================
else
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";
	
	$sql = "SELECT k.grant_name, k.grant_id
			FROM grants k
			LEFT JOIN token_grant tk ON(k.grant_id = tk.grant_id)
			LEFT JOIN tokens t ON(t.token_id = tk.token_id)
			LEFT JOIN users u ON(t.token_user = u.user_id)
			WHERE t.token_value = '".security::escape($token)."' AND {$where}
			ORDER BY k.grant_name";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$grants = array();
	foreach( $result as $r )
		if( $r['grant_id'] != null )
			$grants[] = array('name'=>$r['grant_name'], 'id'=>$r['grant_id']);
	
	responder::send($grants);
}

?>