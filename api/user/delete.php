<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes a user");
$a->addGrant(array('ACCESS', 'USER_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'id', 'user_id', 'uid'),
	'description'=>'The name or id of the user to delete.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
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
	
	// =================================
	// GET LOCAL USER INFO
	// =================================
	if( is_numeric($user) )
		$where = "u.user_id=".$user;
	else
		$where = "u.user_name = '".security::escape($user)."'";

	$sql = "SELECT u.user_id, u.user_name, u.user_ldap FROM users u WHERE {$where}";
	$result = $GLOBALS['db']->query($sql);
	
	if( $result == null || $result['user_id'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET USER INFO
	// =================================	
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $result['user_name']);
	$data = $GLOBALS['ldap']->read($dn);
	
	if( $dn )
	{
		// =================================
		// APPS
		// =================================
		$option = "(owner={$dn})";
		$apps = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::APP, $option));
	
		foreach( $apps as $a )
		{
			if( $a['dn'] ) 
			{
				$extra = json_decode($a['description'], true);
				if( is_array($extra['branches']) )
				{
					$branches = '';
					foreach( $extra['branches'] as $k => $v )
						$branches = $branches . " {$k}";
				}
				
				$GLOBALS['ldap']->delete($a['dn']);
				
				$command = "/dns/tm/sys/usr/local/bin/app-delete {$data['uid']} {$data['homeDirectory']} ".strtolower($data['uid'])." \"{$branches}\"";
				$GLOBALS['gearman']->sendAsync($command);
			}
		}
		
		// =================================
		// DOMAINS
		// =================================
		$option = "(owner={$dn})";
		$domains = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::DOMAIN, $option));
	
		foreach( $domains as $d )
		{
			if( $d['dn'] ) 
			{
				$GLOBALS['ldap']->delete($d['dn']);
				$command = "rm -Rf {$data['homeDirectory']}";
				$GLOBALS['gearman']->sendAsync($command);
			}
		}
		
		// =================================
		// SERVICES
		// =================================
		$sql = "SELECT * FROM services WHERE service_user = {$result['user_id']}";
		$services = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

		foreach( $services as $s )
		{
			switch( $s['service_type'] )
			{
				case 'mysql':
					$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
					$link->query("DROP USER '{$s['service_name']}'");
					$link->query("DROP DATABASE `{$s['service_name']}`");
				break;
				case 'pgsql':
					$command = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$s['service_name']}";
					$GLOBALS['gearman']->sendAsync($command);
				break;
				case 'mongodb':
					$command = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$s['service_name']}";
					$GLOBALS['gearman']->sendAsync($command);
				break;
			}

			// =================================
			// DELETE SUBSERVICES
			// =================================
			$sql = "SELECT * FROM service_branch WHERE service_name = '".security::escape($s['service_name'])."'";
			$subservices = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

			foreach( $subservices as $sub )
			{
				$subservice = $service . '-' . $sub['branch_name'];
				switch( $s['service_type'] )
				{
					case 'mysql':
						$link = new mysqli($GLOBALS['CONFIG']['MYSQL_ROOT_HOST'], $GLOBALS['CONFIG']['MYSQL_ROOT_USER'], $GLOBALS['CONFIG']['MYSQL_ROOT_PASSWORD'], 'mysql', $GLOBALS['CONFIG']['MYSQL_ROOT_PORT']);
						$link->query("DROP USER '{$subservice}'");
						$link->query("DROP DATABASE `{$subservice}`");
					break;
					case 'pgsql':
						$command = "/dns/tm/sys/usr/local/bin/drop-db-pgsql {$subservice}";
						$GLOBALS['gearman']->sendAsync($command);
					break;
					case 'mongodb':
						$command = "/dns/tm/sys/usr/local/bin/drop-db-mongodb {$subservice}";
						$GLOBALS['gearman']->sendAsync($command);
					break;
				}
			}
		}
		
		// =================================
		// DELETE REMOTE USER
		// =================================
		$GLOBALS['ldap']->delete($dn);
	}
	
	// =================================
	// DELETE LOCAL USER
	// =================================
	$sql = "DELETE FROM users WHERE user_id={$result['user_id']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// =================================
	// DELETE PIWIK USER
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.deleteUser&userLogin={$result['user_name']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);

	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$command = "rm -Rf {$data['homeDirectory']}";
	$GLOBALS['gearman']->sendAsync($command);
	
	responder::send("OK");
});

return $a;

?>