<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('nearlimit'));
$a->setDescription("List user near limits");
$a->addGrant(array('ACCESS', 'USER_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the user', 
	'name'=>'the user login', 
	'uid'=>'the ldap id of the user'
	)));
	
$a->addParam(array(
	'name'=>array('quota', 'name', 'quota_name', 'id', 'quota_id'),
	'description'=>'The name or id of quota to check',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::ALPHANUM|request::PUNCT
	));

$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET PARAMETERS
	// =================================
	$quota = $a->getParam('quota');
	
	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	if( is_numeric($quota) )
		$where = "uq.quota_id = {$quota}";
	else
		$where = "q.quota_name = '{$quota}'";

	// =================================
	// SELECT RECORDS
	// =================================
	$sql = "SELECT u.user_id, u.user_name, u.user_ldap, u.user_status, u.user_date, u.user_last_update, q.quota_id, q.quota_name, uq.quota_max, uq.quota_used
			FROM users u
			LEFT JOIN user_quota uq ON(u.user_id = uq.user_id)
			LEFT JOIN quotas q ON(uq.quota_id = q.quota_id)
			WHERE {$where}
			ORDER BY uq.quota_used DESC";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	// =================================
	// FORMAT RESULT
	// =================================
	$user = null;
	$users = array();
	foreach( $result as $r )
	{
		if( $r['quota_max'] != 0 && round(($r['quota_used']*100)/$r['quota_max']) >= 80 )
		{
			$user = array('name'=>$r['user_name'], 'id'=>$r['user_id'], 'uid'=>$r['user_ldap'], 'firstname'=>'', 'lastname'=>'', 'email'=>'', 'status'=>$r['user_status'], 'date'=>$r['user_date'], 'ip'=>'', 'last'=>$r['user_last_update']);
			$user['quotas'] = array('id'=>$r['quota_id'], 'name'=>$r['quota_name'], 'max'=>$r['quota_max'], 'used'=>$r['quota_used']);
			
			$users[] = $user;
		}
	}
			
	responder::send($users);
});

return $a;

?>
