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
<h1><a href=\"/help\">API Help</a> :: <a href=\"/grant/help\">grant</a> :: <a href=\"/grant/group/help\">group</a> :: delete</h1>
<ul>
	<li><h2>Alias :</h2> deny, reject, revoke, del, remove</li>
	<li><h2>Description :</h2> remove grants from the provided groups</li>
	<li><h2>Parameters :</h2>
		<ul>
			<li>grant : The name or id of the target grant. <span class=\"required\">required</span>. <span class=\"multiple\">multiple</span>. (alias: grant_name, grant_names, grants, grant_id, grant_ids, kid, kids)</li>
			<li>group : The name or id of the target group. <span class=\"required\">required</span>. <span class=\"urlizable\">urlizable</span>. <span class=\"multiple\">multiple</span>. (alias : group_name, groups, group_names, group_id, group_ids, gid, gids)</li>
		</ul>
	</li>
	<li><h2>Returns :</h2> OK</li>
	<li><h2>Required grants :</h2> ACCESS, GRANT_GROUP_DELETE</li>
</ul>";
	responder::help($body);
}

// =================================
// CHECK AUTH
// =================================
security::requireGrants(array('ACCESS', 'GRANT_GROUP_DELETE'));

// =================================
// GET PARAMETERS
// =================================
$grant = request::getCheckParam(array(
	'name'=>array('grant_name', 'grant', 'grant_id', 'kid', 'grant_names', 'grants', 'grant_ids', 'kids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*"
	));
$group = request::getCheckParam(array(
	'name'=>array('group', 'group_name', 'groups', 'group_names', 'group_id', 'group_ids', 'gid', 'gids'),
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT|request::SPACE,
	'action'=>true,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*"
	));

// =================================
// PREPARE WHERE CLAUSE
// =================================
$grant_ids = '';
$grant_names = '';
foreach( $grant as $k )
{
	if( is_numeric($k) )
		$grant_ids .= ','.$k;
	else if( strlen($k) > 0 )
		$grant_names .= ",'".security::escape($k)."'";
}

$group_ids = '';
$group_names = '';
foreach( $group as $g )
{
	if( is_numeric($g) )
		$group_ids .= ','.$g;
	else if( strlen($g) > 0 )
		$group_names .= ",'".security::escape($g)."'";
}

// =================================
// EXECUTE QUERY
// =================================
$sql = "DELETE gk FROM group_grant gk
		LEFT JOIN grants k ON(k.grant_id = gk.grant_id)
		LEFT JOIN groups g ON(g.group_id = gk.group_id)
		WHERE (k.grant_name IN(''{$grant_names}) OR k.grant_id IN(-1{$grant_ids}))
		AND (g.group_name IN(''{$group_names}) OR g.group_id IN(-1{$group_ids}))";
$GLOBALS['db']->query($sql, mysql::NO_ROW);

// =================================
// CLEANUP INVALID TOKEN GRANTS
// =================================
grantStore::add('TOKEN_CLEANUP');
request::forward('/token/cleanup');

responder::send("OK");

?>