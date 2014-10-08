<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('select', 'list', 'view', 'search'));
$a->setDescription("Searches for a user");
$a->addGrant(array('ACCESS', 'USER_SELECT'));
$a->setReturn(array(array(
	'id'=>'the id of the user', 
	'name'=>'the user login', 
	'uid'=>'the ldap id of the user',
	'email'=>'the email of the account',
	'firstname'=>'the firstname of the user',
	'lastname'=>'the lastname of the user',
	'date'=>'the registration date of the user',
	'ip'=>'the registration ip of the user',
	'last'=>'last status change'
	)));
$a->addParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user', 'names', 'user_names', 'usernames', 'logins', 'users', 'id', 'user_id', 'uid', 'ids', 'user_ids', 'uids'),
	'description'=>'The name or id of the users to search for.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'array'=>true,
	'delimiter'=>"\\s*(,|;|\\s)\\s*",
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'user_email', 'user_mail'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('from'),
	'description'=>'From subscription date.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('to'),
	'description'=>'To subscription date.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('count'),
	'description'=>'Return only the number of entries.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('fast'),
	'description'=>'Return fast response with only user name and id.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('quota', 'quotas'),
	'description'=>'Return user quotas as well.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('search'),
	'description'=>'Activate LIKE % % search.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('order'),
	'description'=>'Order return.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>20,
	'match'=>"(user_date|user_name|user_id)"
	));
$a->addParam(array(
	'name'=>array('order_type'),
	'description'=>'Order type.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>4,
	'match'=>"(ASC|DESC)"
	));
$a->addParam(array(
	'name'=>array('group'),
	'description'=>'Group by?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>request::UPPER
	));
$a->addParam(array(
	'name'=>array('limit'),
	'description'=>'Limit response.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>11,
	'match'=>request::NUMBER
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
	$user = $a->getParam('user');
	$mail = $a->getParam('mail');
	$from = $a->getParam('from');
	$to = $a->getParam('to');
	$count = $a->getParam('count');
	$fast = $a->getParam('fast');
	$quota = $a->getParam('quota');
	$search = $a->getParam('search');
	$order = $a->getParam('order');
	$order_type = $a->getParam('order_type');
	$group = $a->getParam('group');
	$limit = $a->getParam('limit');
	
	// =================================
	// PROCESS PARAMETERS
	// =================================	
	if( $count == '1' || $count == 'yes' || $count == 'true' || $count === true || $count === 1 )
		$count = true;
	else
		$count = false;
	if( $fast == '1' || $fast == 'yes' || $fast == 'true' || $fast === true || $fast === 1 )
		$fast = true;
	else
		$fast = false;

	if( $quota == '1' || $quota == 'yes' || $quota == 'true' || $quota === true || $quota === 1 )
		$quota = true;
	else
		$quota = false;

	if( $search == '1' || $search == 'yes' || $search == 'true' || $search === true || $search === 1 )
		$search = true;
	else
		$search = false;
	
	if( $limit === null )
		$limit = 100;
		
	// =================================
	// SEARCH IN LDAP
	// =================================	
	if( $mail !== null && count($user) == 0 )
	{
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::USER, "(mailForwardingAddress=*{$mail}*)"));
		
		$user = array();
		foreach( $result as $r )
			$user[] = $r['uid'];
	}

	// =================================
	// PREPARE WHERE CLAUSE
	// =================================
	$where_name = '';
	$where_id = '';
	if( $user !== null && count($user) > 0 )
	{
		foreach( $user as $u )
		{
			if( is_numeric($u) )
			{
				if( strlen($where_id) == 0 ) $where_id = ' OR u.user_id IN(-1';
				$where_id .= ','.$u;
			}
			else
			{
				if( strlen($where_name) == 0 ) $where_name = '';
				
				if( $search === true )
					$where_name .= " OR u.user_name LIKE '%".security::escape($u)."%'";
				else
					$where_name .= " OR u.user_name = '".security::escape($u)."'";
			}
		}
		if( strlen($where_id) > 0 ) $where_id .= ')';
	}
	else
		$where_name = " OR true";

	if( $order !== null )
		$order = 'u.' . $order;
	else
		$order = 'u.user_name';
	
	$where = '';
	if( $from !== null )
		$where .= " AND user_date > {$from}";
	if( $to !== null )
		$where .= " AND user_date < {$to}";
	
	if( $order_type === null )
		$order_type = 'ASC';
	
	// =================================
	// SELECT RECORDS
	// =================================
	if( $count === true )
	{
		$sql = "SELECT COUNT(user_id) as count FROM users WHERE 1";
		$result = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
		
		responder::send($result);
	}
	
	if( $quota === true )
	{
		$sql = "SELECT u.user_id, u.user_report, u.user_name, u.user_ldap, u.user_status, u.user_zabbix, u.user_billing, u.user_date, u.user_last_update, q.quota_id, q.quota_name, uq.quota_max, uq.quota_used
				FROM users u
				LEFT JOIN user_quota uq ON(u.user_id = uq.user_id)
				LEFT JOIN quotas q ON(uq.quota_id = q.quota_id)
				WHERE false {$where_name} {$where_id} {$where}
				ORDER BY {$order} {$order_type}
				LIMIT 0,{$limit}";
	}
	else if( $group !== null )
	{
		$sql = "SELECT COUNT(user_id) as count, {$group} (FROM_UNIXTIME(user_date)) as {$group} FROM users
				WHERE false {$where_name} {$where_id} {$where} GROUP BY {$group} (FROM_UNIXTIME(user_date))";
	}
	else
	{
		$sql = "SELECT u.user_id, u.user_report, u.user_name, u.user_ldap, u.user_date, u.user_zabbix, u.user_billing, u.user_last_update, u.user_status
				FROM users u
				WHERE false {$where_name} {$where_id} {$where}
				ORDER BY {$order} {$order_type}
				LIMIT 0,{$limit}";
	}
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);
		
	if( $group !== null )
		responder::send($result);
	
	// =================================
	// FORMAT RESULT
	// =================================
	$users = array();
	$current = null;
	foreach( $result as $r )
	{
		if( $current == null || $current['id'] != $r['user_id'] )
		{
			if( $current != null )
				$users[] = $current;
			
			$current = array('name'=>$r['user_name'], 'id'=>$r['user_id'], 'billing'=>$r['user_billing'], 'report'=>$r['user_report'], 'zabbix'=>$r['user_zabbix'], 'uid'=>$r['user_ldap'], 'firstname'=>'', 'lastname'=>'', 'email'=>'', 'status'=>$r['user_status'], 'date'=>$r['user_date'], 'ip'=>'', 'last'=>$r['user_last_update']);
			
			if( $quota )
				$current['quotas'] = array();
		}
		
		if( $quota && $r['quota_id'] != null )
			$current['quotas'][] = array('id'=>$r['quota_id'], 'name'=>$r['quota_name'], 'max'=>$r['quota_max'], 'used'=>$r['quota_used']);
	}
	if( $current != null )
		$users[] = $current;
			
	if( $fast )
	{
		responder::send($users);
		exit;
	}

	// =================================
	// RETREIVE INFO FROM REMOTE USER
	// =================================
	try
	{		
		$remote = $users;
		$i = 0;
		foreach( $remote as $r )
		{
			if( $r['uid'] )
			{
				$dn = $GLOBALS['ldap']->getDNfromUID($r['uid']);
				$result = $GLOBALS['ldap']->read($dn);
				
				$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$result['homeDirectory']}'";
				$storage = $GLOBALS['db']->query($sql);
			
				$users[$i]['firstname'] = $result['givenName'];
				$users[$i]['lastname'] = $result['sn'];
				$users[$i]['language'] = $result['gecos'];
				$users[$i]['ip'] = $result['ipHostNumber'];
				$users[$i]['postal_address'] = $result['postalAddress'];
				$users[$i]['postal_code'] = $result['postalCode'];
				$users[$i]['organisation'] = $result['o'];
				$users[$i]['locality'] = $result['l'];
				$users[$i]['description'] = $result['description'];
				$users[$i]['keys'] = $result['sshPublicKey'];
				$users[$i]['email'] = (isset($result['mailForwardingAddress'])?$result['mailForwardingAddress']:$result['mail']);
				$users[$i]['size'] = $storage['storage_size'];
			}
			$i++;
		}
		
		if( $from !== null || $to !== null )
		{
			if( $to === null )
				$to = time();
			$output = array();
			foreach( $users as $u )
			{		
				if( $u['date'] >= $from && $u['date'] <= $to )
					$output[] = $u;
			}
		}
		else
			$output = $users;
			
	}
	catch(Exception $e) { }
	
	responder::send($output);
});

return $a;

?>
