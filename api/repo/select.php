<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('list', 'view', 'search'));
$a->setDescription("Searches for a repo");
$a->addGrant(array('ACCESS', 'SERVICE_SELECT'));
$a->setReturn(array(array(
	'name'=>'the name of the repo', 
	'type'=>'the repo type',
	'dir'=>'the repo directory',
	'description'=>'the repo description',
	'user'=>array(
		'id'=>'the user id', 
		'name'=>'the username'
	),
	)));
$a->addParam(array(
	'name'=>array('repo', 'name', 'repo_name', 'id', 'repo_id'),
	'description'=>'The name or the id of the repo',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('user', 'user_name', 'username', 'login', 'user_id', 'uid'),
	'description'=>'The name or id of the target user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>30,
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
	$repo = $a->getParam('repo');
	$user = $a->getParam('user');

	// =================================
	// GET USER DATA
	// =================================
	if( $user !== null )
	{ 
		$sql = "SELECT user_ldap FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
		$userdata = $GLOBALS['db']->query($sql);
		if( $userdata == null || $userdata['user_ldap'] == null )
			throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
	}

	// =================================
	// SELECT REMOTE ENTRIES
	// =================================
	if( $repo !== null )
	{
		if( is_numeric($repo) )
			$dn = $GLOBALS['ldap']->getDNfromUID($repo);
		else
			$dn = ldap::buildDN(ldap::REPO, $repo);
		
		$info = $GLOBALS['ldap']->read($dn);
		
		if( is_array($info['owner']) )
			$info['owner'] = $info['owner'][0];
		
		$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		
		if( $ownerdn != $info['owner'] )
			throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the repo {$repo}");

		if( is_array($info['owner']) )
			$info['owner'] = $info['owner'][0];
		
		$result = array();
		$result[0] = $info;
		$result[0]['dn'] = $dn;
	}
	elseif( $user !== null )
	{
		$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::REPO, "(owner={$user_dn})"), $count);
	}
	else
		$result = $GLOBALS['ldap']->search($GLOBALS['CONFIG']['LDAP_BASE'], ldap::buildFilter(ldap::REPO), $count);
	
	// =================================
	// FORMAT RESULT
	// =================================
	$repos = array();
	foreach( $result as $r )
	{
		$sql = "SELECT storage_size FROM storages WHERE storage_path = '{$r['homeDirectory']}'";
		$storage = $GLOBALS['db']->query($sql);
		
		$re['name'] = $r['uid'];
		$re['id'] = $r['uidNumber'];
		$re['type'] = $r['gecos'];
		$re['description'] = $r['description'];
		$re['dir'] = $r['homeDirectory'];
		$re['size'] = $storage['storage_size'];
		
		$apps = array();
		$groups = array();
		$users = array();
		if( is_array($r['member']) )
		{
			foreach( $r['member'] as $m )
			{
				if( strpos($m, 'ou=Apps') !== false )
				{
					$app = $GLOBALS['ldap']->read($m);
					$apps[] = array('name'=>$app['uid'],'id'=>$app['uidNumber']);
				}
				if( strpos($m, 'ou=Groups') !== false )
				{
					$group = $GLOBALS['ldap']->read($m);
					$groups[] = array('name'=>$group['uid'],'id'=>$group['uidNumber']);
				}
				if( strpos($m, 'ou=Users') !== false )
				{
					$user = $GLOBALS['ldap']->read($m);
					$users[] = array('name'=>$user['uid'],'id'=>$user['uidNumber']);
				}
			}
		}
		elseif( $r['member'] )
		{
			if( strpos($r['member'], 'ou=Apps') !== false )
			{
				$app = $GLOBALS['ldap']->read($r['member']);
				$apps[] = array('name'=>$app['uid'],'id'=>$app['uidNumber']);
			}
			if( strpos($r['member'], 'ou=Groups') !== false )
			{
				$group = $GLOBALS['ldap']->read($r['member']);
				$groups[] = array('name'=>$group['uid'],'id'=>$group['uidNumber']);
			}
			if( strpos($r['member'], 'ou=Users') !== false )
			{
				$user = $GLOBALS['ldap']->read($r['member']);
				$users[] = array('name'=>$user['uid'],'id'=>$user['uidNumber']);
			}
		}
		
		$re['users'] = $users;
		$re['apps'] = $apps;
		$re['groups'] = $groups;
		
		$repos[] = $re;
	}

	responder::send($repos);
});

return $a;

?>