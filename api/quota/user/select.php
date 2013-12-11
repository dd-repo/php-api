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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/quota/help\">quota</a> :: <a href=\"/quota/user/help\">user</a> :: select</h1>
<ul>
	<li><h2>Alias :</h2> list, view, search</li>
	<li><h2>Description :</h2> lists all quotas of a user or all users of a quota</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>user : The name or id of the users to search for. <span class=\"optional\">optional</span>. <span class=\"urlizable\">urlizable</span>. (alias : user_name, username, login, user_id, uid)</li>
			<li>quota : The name or id of the quota to search for. <span class=\"optional\">optional</span>. (alias : quota_name, quota_id, qid)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> the matching quotas or users [{'name', 'id', 'max', 'used'},...]</li>
	<li><h2>Required grants :</h2> ACCESS, QUOTA_USER_SELECT</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'QUOTA_USER_SELECT'));

// =================================
// GET PARAMETERS
// =================================
$user = request::getCheckParam(array(
	'name'=>array('user_name', 'username', 'login', 'user', 'user_id', 'uid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$quota = request::getCheckParam(array(
	'name'=>array('quota', 'quota_name', 'quota_id', 'qid'),
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT
	));

if( $user !== null && $quota !== null )
	throw new ApiException("Too many parameters", 412, "Cannot specify both user ({$user}) and quota ({$quota})");
if( $user === null && $quota === null )
	throw new ApiException("Missing conditional parameter", 412, "Cannot specify none of user and quota");

// =================================
// EXECUTE QUERY FOR USER
// =================================
if( $user !== null )
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";
	
	$sql = "SELECT q.quota_name, q.quota_id, uq.quota_max, uq.quota_used
			FROM users u
			LEFT JOIN user_quota uq ON(u.user_id = uq.user_id)
			LEFT JOIN quotas q ON(q.quota_id = uq.quota_id)
			WHERE {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$quotas = array();
	foreach( $result as $r )
		if( $r['quota_id'] != null )
			$quotas[] = array('name'=>$r['quota_name'], 'id'=>$r['quota_id'], 'max'=>$r['quota_max'], 'used'=>$r['quota_used']);
	
	responder::send($quotas);
}

// =================================
// EXECUTE QUERY FOR QUOTA
// =================================
else
{
	if( is_numeric($quota) )
		$where = "q.quota_id=".$quota;
	else
		$where = "q.quota_name = '".security::escape($quota)."'";
	
	$sql = "SELECT u.user_name, u.user_id, uq.quota_max, uq.quota_used
			FROM quotas q
			LEFT JOIN user_quota uq ON(q.quota_id = uq.quota_id)
			LEFT JOIN users u ON(u.user_id = uq.user_id)
			WHERE {$where}";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
	
	$users = array();
	foreach( $result as $r )
		if( $r['user_id'] != null )
			$users[] = array('name'=>$r['user_name'], 'id'=>$r['user_id'], 'max'=>$r['quota_max'], 'used'=>$r['quota_used']);
	
	responder::send($users);
}

?>