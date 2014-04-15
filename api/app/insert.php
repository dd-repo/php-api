<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new app");
$a->addGrant(array('ACCESS', 'APP_INSERT'));
$a->setReturn(array(array(
	'id'=>'the id of the app', 
	'name'=>'the app name'
	)));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The name of the domain that app belong to.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('runtime', 'app_runtime'),
	'description'=>'The target runtime',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
	));	
$a->addParam(array(
	'name'=>array('pass', 'password'),
	'description'=>'The password of the service.',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('binary', 'app_binary'),
	'description'=>'The binary to execute.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>150,
	'match'=>request::PHRASE|request::SPECIAL
	));
$a->addParam(array(
	'name'=>array('tag', 'app_tag'),
	'description'=>'The tag of the app',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>200
	));
$a->addParam(array(
	'name'=>array('nodocker'),
	'description'=>'No docker for this app',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|yes|true)"
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'address', 'user_email', 'user_mail', 'user_address'),
	'description'=>'The email of the user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>false,
	'minlength'=>0,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
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
	$domain = $a->getParam('domain');
	$runtime = $a->getParam('runtime');
	$pass = $a->getParam('pass');
	$binary = $a->getParam('binary');
	$tag = $a->getParam('tag');
	$nodocker = $a->getParam('nodocker');
	$email = $a->getParam('email');
	$user = $a->getParam('user');
	
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
	// CHECK QUOTA
	// =================================
	grantStore::add('QUOTA_USER_INTERNAL');
	request::forward('/quota/user/internal');
	checkQuota('MEMORY', $user);
	checkQuota('APPS', $user);
	
	// =================================
	// INSERT REMOTE APP
	// =================================
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$app = '';
	for( $u = 1; $u <= 8; $u++ )
	{
		$number = strlen($chars);
		$number = mt_rand(0,($number-1));
		$app .= $chars[$number];
	}
	$app = $runtime . '-' . $app;
	
	$sql = "SELECT port, used FROM ports WHERE used = 0";
	$portresult = $GLOBALS['db']->query($sql, mysql::ONE_ROW);
	if( !$portresult['port'] )
	{
		$sql = "INSERT INTO ports (used) VALUES (1)";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
		$port = $GLOBALS['db']->last_id();
	}
	else
	{
		$port = $portresult['port'];
		$sql = "UPDATE ports SET used = 1 WHERE port = {$port}";
		$GLOBALS['db']->query($sql, mysql::NO_ROW);
	}

	$extra = array();
	if( $nodocker != null )
		$extra['branches'] = array('master' => array('instances'=>array()));
	else
		$extra['branches'] = array('master' => array('instances'=>array(array('port'=>$port, 'memory' => '128', 'cpu' => 1))));
	
	$dn = ldap::buildDN(ldap::APP, $domain, $app);
	$params = array('dn' => $dn, 'uid' => $app, 'userPassword' => $pass, 'domain' => $domain, 'description' => json_encode($extra), 'mailForwardingAddress'=>$email, 'owner' => $user_dn);
	
	$handler = new app();
	$data = $handler->build($params);
	
	$result = $GLOBALS['ldap']->create($dn, $data);

	$sql = "INSERT INTO apps (app_id, app_binary, app_tag) VALUES ({$data['uidNumber']}, '".security::encode($binary)."', '".security::encode($tag)."')";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
			
	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::ADD);
	
	// =================================
	// POST-CREATE SYSTEM ACTIONS
	// =================================
	$commands[] = "/dns/tm/sys/usr/local/bin/app-create {$app} {$data['homeDirectory']} {$data['uidNumber']} {$data['gidNumber']} {$runtime} ".strtolower($app)." ".security::encode($domain)." \"".security::encode($binary)."\"";
	$commands[] = "cd {$userinfo['homeDirectory']} && ln -s ".str_replace("Apps/{$app}", "var/git/{$app}", $data['homeDirectory'])." {$app}.git";
	$GLOBALS['gearman']->sendAsync($commands);
	
	// =================================
	// SYNC QUOTA
	// =================================
	syncQuota('MEMORY', $user);
	syncQuota('APPS', $user);

	// =================================
	// INSERT PIWIK APP
	// =================================
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=SitesManager.addSite&siteName={$app}&urls=http://{{$app}.anotherservice.net&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	$json = json_decode(@file_get_contents($url), true);
	$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.setUserAccess&userLogin={$userdata['user_name']}&access=admin&idSites={$json['value']}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
	@file_get_contents($url);
	
	$sql = "UPDATE apps SET app_piwik = '{$json['value']}' WHERE app_id = {$data['uidNumber']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('app/insert', $a->getParams(), $userdata['user_id']);
	
	responder::send(array("name"=>$app, "id"=>$data['uidNumber']));
});

return $a;

?>