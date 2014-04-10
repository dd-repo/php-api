<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('delete', 'del', 'remove', 'destroy'));
$a->setDescription("Removes an app");
$a->addGrant(array('ACCESS', 'APP_DELETE'));
$a->setReturn("OK");

$a->addParam(array(
	'name'=>array('app', 'app_name', 'app_id', 'id', 'uid'),
	'description'=>'The name or id of the app to remove.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'name', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The domain of the app.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
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
	$app = $a->getParam('app');
	$user = $a->getParam('user');
	$domain = $a->getParam('domain');

	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_name, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
		
	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	$userinfo = $GLOBALS['ldap']->read($user_dn);
	
	// =================================
	// GET APP DN
	// =================================
	if( is_numeric($app) )
		$dn = $GLOBALS['ldap']->getDNfromUID($app);
	elseif( $domain != null )
		$dn = ldap::buildDN(ldap::APP, $domain, $app);
	else
		throw new ApiException("Can not find app", 412, "Can not find app without domain: {$app}");
	
	// =================================
	// GET APP DATA
	// =================================	
	if( $dn )
		$data = $GLOBALS['ldap']->read($dn);	
	else
		throw new ApiException("Not found", 404, "Can not find app: {$app}");
	
	// =================================
	// CHECK OWNER
	// =================================	
	if( is_array($data['owner']) )
		$data['owner'] = $data['owner'][0];
			
	if( $user_dn != $data['owner'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the app {$app}");

	
	// =================================
	// DELETE OTHERS
	// =================================
	$extra = json_decode($data['description'], true);
	
	if( is_array($extra['branches']) )
	{
		$branches = '';
		foreach( $extra['branches'] as $k => $v )
		{
			$branches = $branches . "{$k} ";
			if( count($v['urls']) > 0 )
			{
				foreach( $v['urls'] as $u )
				{
					$dn2 = $GLOBALS['ldap']->getDNfromHostname($u);
					$data2 = $GLOBALS['ldap']->read($dn2);
					$commands[] = "rm {$data2['homeDirectory']}";
				}
			}
			if( count($v['instances']) > 0 )
			{
				foreach( $v['instances'] as $i )
				{
					$command = "sv stop {$data['uid']}-{$k}-{$i['id']} && rm /etc/service/{$data['uid']}-{$k}-{$i['id']}";
					$GLOBALS['gearman']->sendAsync($command, $i['host']);
			
					$command = "docker rmi registry:5000/".strtolower($data['uid'])."-{$k}";
					$GLOBALS['gearman']->sendAsync($command, $i['host']);
					
					$sql = "UPDATE ports SET used = 0 WHERE port = {$i['port']}";
					$GLOBALS['db']->query($sql, mysql::NO_ROW);				
				}
			}
		}
	}
	
	// =================================
	// DELETE REMOTE APP
	// =================================
	$GLOBALS['ldap']->delete($dn);
	
	// =================================
	// DELETE LOCAL APP
	// =================================	
	$sql = "DELETE FROM apps WHERE app_id = {$data['uidNumber']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// POST-DELETE SYSTEM ACTIONS
	// =================================
	$commands[] = "/dns/tm/sys/usr/local/bin/app-delete {$data['uid']} {$data['homeDirectory']} ".strtolower($data['uid'])." \"{$branches}\"";
	$GLOBALS['gearman']->sendAsync($commands);
	
	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::DELETE);

	// =================================
	// DELETEE SYMLINK
	// =================================
	$command = "rm {$userinfo['homeDirectory']}/{$data['uid']}.git";
	$GLOBALS['gearman']->sendAsync($command);
	
	// =================================
	// SYNC QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	syncQuota('APPS', $user);
	syncQuota('MEMORY', $user);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('app/delete', $a->getParams(), $userdata['user_id']);
	
	responder::send("OK");
});

return $a;

?>