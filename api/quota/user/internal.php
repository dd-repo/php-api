<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

// WARNING : THIS PAGE ONLY PROVIDES 2 FUNCTIONS TO CHECK/SYNC
// THE USER QUOTA. IT SHOULD NOT BE CALLED DIRECTLY.

security::requireGrants(array('QUOTA_USER_INTERNAL'));

function checkQuota($type, $user)
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";
	
	$sql = "SELECT uq.quota_max, uq.quota_used
			FROM quotas q 
			LEFT JOIN user_quota uq ON(q.quota_id = uq.quota_id)
			LEFT JOIN users u ON(u.user_id = uq.user_id)
			WHERE q.quota_name='".security::escape($type)."' 
			AND {$where}";
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['quota_max'] == null || $result['quota_used'] >= $result['quota_max']+1 )
		throw new ApiException("Unsufficient quota", 412, "Quota limit reached or not set : {$result['quota_used']}/{$result['quota_max']}");
}

function syncQuota($type, $user)
{
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";

	$count = "quota_used";
	switch($type)
	{
		case 'DOMAINS':
			$sql = "SELECT user_ldap FROM users u WHERE {$where}";
			$userdata = $GLOBALS['db']->query($sql);
			if( $userdata == null || $userdata['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
			$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::DOMAIN, "(owner={$user_dn})"));
			$count = count($result);
			break;
		case 'APPS':
			$sql = "SELECT user_ldap FROM users u WHERE {$where}";
			$userdata = $GLOBALS['db']->query($sql);
			if( $userdata == null || $userdata['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
			$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP, "(owner={$user_dn})"));
			$count = count($result);
			break;
		case 'MEMORY':
			$sql = "SELECT user_ldap FROM users u WHERE {$where}";
			$userdata = $GLOBALS['db']->query($sql);
			if( $userdata == null || $userdata['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$apps = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP, "(owner={$user_dn})"));
			$count = 0;
			foreach( $apps as $a )
			{
				$extra = json_decode($a['description'], true);
				if( is_array($extra['branches']) )
				{
					foreach( $extra['branches'] as $key => $value )
					{
						if( is_array($value['instances']) )
						{
							foreach( $value['instances'] as $i )
								$count = $count+$i['memory'];
						}
					}
				}
			}
		break;
		case 'SERVICES':
			$sql = "SELECT user_ldap, user_id FROM users u WHERE {$where}";
			$userdata = $GLOBALS['db']->query($sql);
			if( $userdata == null || $userdata['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$sql = "SELECT COUNT(service_name) as count FROM services WHERE service_user = {$userdata['user_id']}";
			$result = $GLOBALS['db']->query($sql);
			$count = $result['count'];
		break;
		case 'DISK':
			$sql = "SELECT user_ldap, user_id FROM users u WHERE {$where}";
			$userdata = $GLOBALS['db']->query($sql);
			if( $userdata == null || $userdata['user_ldap'] == null )
				throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
			$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
			$usage = 0;
			$usage = $GLOBALS['system']->getquota($userdata['user_ldap']);
			$usage = round($usage/1024);
			
			$apps = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP, "(owner={$user_dn})"));
			foreach( $apps as $a )
			{
				$u = 0;
				$u = $GLOBALS['system']->getquota($a['uidNumber']);
				$u = round($u/1024);
				
				$sql = "SELECT storage_size, storage_id FROM storages WHERE storage_path = '{$a['homeDirectory']}'";
				$store = $GLOBALS['db']->query($sql);
				if( $store['storage_id'] )
					$sql = "UPDATE storages SET storage_size = {$u} WHERE storage_id = {$store['storage_id']}";
				else
					$sql = "INSERT INTO storages (storage_path, storage_size) VALUES ('{$a['homeDirectory']}', {$u})";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				
				$usage = $usage+$u;
			}
			
			$users = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::USER, "(owner={$user_dn})"));
			foreach( $users as $user )
			{
				$u = 0;
				$u = $GLOBALS['system']->getquota($user['uidNumber']);
				$u = round($u/1024);
				
				$sql = "SELECT storage_size, storage_id FROM storages WHERE storage_path = '{$user['homeDirectory']}'";
				$store = $GLOBALS['db']->query($sql);
				if( $store['storage_id'] )
					$sql = "UPDATE storages SET storage_size = {$u} WHERE storage_id = {$store['storage_id']}";
				else
					$sql = "INSERT INTO storages (storage_path, storage_size) VALUES ('{$user['homeDirectory']}', {$u})";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				
				$usage = $usage+$u;
			}

			$repos = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::REPO, "(owner={$user_dn})"));			
			foreach( $repos as $r )
			{
				$u = 0;
				$u = $GLOBALS['system']->getquota($r['uidNumber']);
				$u = round($u/1024);
				
				$sql = "SELECT storage_size, storage_id FROM storages WHERE storage_path = '{$r['homeDirectory']}'";
				$store = $GLOBALS['db']->query($sql);
				if( $store['storage_id'] )
					$sql = "UPDATE storages SET storage_size = {$u} WHERE storage_id = {$store['storage_id']}";
				else
					$sql = "INSERT INTO storages (storage_path, storage_size) VALUES ('{$r['homeDirectory']}', {$u})";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				
				$usage = $usage+$u;
			}
			
			$count = $usage;
		break;
		default:
			throw new ApiException("Undefined quota type", 500, "Not preconfigured for quota type : {$type}");
	}
	
	if( $count !== null && $count !== false )
	{
		$sql = "UPDATE IGNORE user_quota 
			SET quota_used=LEAST({$count},quota_max)
			WHERE quota_id IN (SELECT q.quota_id FROM quotas q WHERE q.quota_name='".security::escape($type)."')
			AND user_id IN (SELECT u.user_id FROM users u WHERE {$where})";
			
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}
	
}

// ========================= DECLARE ACTION

$a = new action();
$a->addAlias(array('internal'));
$a->setDescription("Include utility functions for the quota");
$a->addGrant(array('QUOTA_USER_INTERNAL'));

$a->setExecute(function() use ($a)
{
	$a->checkAuth();
});

return $a;


?>
