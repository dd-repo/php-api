<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('create', 'add'));
$a->setDescription("Creates a new repo");
$a->addGrant(array('ACCESS', 'SERVICE_INSERT'));
$a->setReturn(array(array(
	'name'=>'the repo name'
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
	'name'=>array('desc', 'repo_desc', 'description'),
	'description'=>'The repository description.',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>200,
	'match'=>request::LOWER|request::UPPER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('type', 'repo_type'),
	'description'=>'The repository type.',
	'optional'=>false,
	'minlength'=>2,
	'maxlength'=>20,
	'match'=>"(git|svn|hg)"
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
	$desc = $a->getParam('desc');
	$type = $a->getParam('type');
	$user = $a->getParam('user');
	
	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_id FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");

	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	// =================================
	// GET DOMAIN DATA
	// =================================
	$dn = ldap::buildDN(ldap::DOMAIN, $domain);
	$result = $GLOBALS['ldap']->read($dn);
	
	if( $result == null || $result['uid'] == null )
		throw new ApiException("Unknown domain", 412, "Unknown domain : {$domain}");
		
	// =================================
	// GENERATE NAME
	// =================================
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$repo = '';
	for( $u = 1; $u <= 8; $u++ )
	{
		$number = strlen($chars);
		$number = mt_rand(0,($number-1));
		$repo .= $chars[$number];
	}
	$repo = $type . '-' . $repo;
	
	// =================================
	// INSERT REMOTE REPO
	// =================================
	$dir = $result['homeDirectory'] . '/var/' . $type . '/' . $repo;
	$dn = ldap::buildDN(ldap::REPO, $domain, $repo);
	$params = array('dn' => $dn, 'uid' => $repo, 'domain' => $domain, 'owner' => $user_dn, 'homeDirectory' => $dir, 'description' => $desc, 'gecos' => $type);
	
	$handler = new repo();
	$data = $handler->build($params);
	$result = $GLOBALS['ldap']->create($dn, $data);

	// =================================
	// INSERT REMOTE REPO
	// =================================
	switch( $type )
	{
		case 'git':
			$GLOBALS['system']->create(system::GIT, $data);
		break;
		case 'svn':
			$GLOBALS['system']->create(system::SVN, $data);
		break;
		case 'hg':
			$GLOBALS['system']->create(system::MERCURIAL, $data);
		break;
	}

	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::ADD);

	responder::send(array("name"=>$repo));
});

return $a;

?>