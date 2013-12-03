<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('del', 'remove', 'destroy'));
$a->setDescription("Removes a repository");
$a->addGrant(array('ACCESS', 'SERVICE_DELETE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('repo', 'name', 'repo_name', 'id', 'repo_id'),
	'description'=>'The name or the id of the repo',
	'optional'=>false,
	'minlength'=>3,
	'maxlength'=>100,
	'match'=>request::UPPER|request::LOWER|request::NUMBER|request::PUNCT
	));
$a->addParam(array(
	'name'=>array('domain', 'domain_name'),
	'description'=>'The domain of the repo.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>200,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT
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
	$domain = $a->getParam('domain');
	$user = $a->getParam('user');

	// =================================
	// GET USER DATA
	// =================================
	$sql = "SELECT user_ldap, user_name FROM users u WHERE ".(is_numeric($user)?"u.user_id=".$user:"u.user_name = '".security::escape($user)."'");
	$userdata = $GLOBALS['db']->query($sql);
	if( $userdata == null || $userdata['user_ldap'] == null )
		throw new ApiException("Unknown user", 412, "Unknown user : {$user}");
		
	// =================================
	// GET REMOTE USER DN
	// =================================	
	$user_dn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	// =================================
	// GET REPO DN
	// =================================
	if( is_numeric($repo) )
		$dn = $GLOBALS['ldap']->getDNfromUID($repo);
	elseif( $domain != null )
		$dn = ldap::buildDN(ldap::REPO, $domain, $repo);
	else
		throw new ApiException("Can not find repo", 412, "Can not find repo without domain: {$repo}");
	
	// =================================
	// GET REPO DATA
	// =================================	
	if( $dn )
		$data = $GLOBALS['ldap']->read($dn);	
	else
		throw new ApiException("Not found", 404, "Can not find repo: {$repo}");
	
	// =================================
	// CHECK OWNER
	// =================================
	$ownerdn = $GLOBALS['ldap']->getDNfromUID($userdata['user_ldap']);
	
	if( is_array($data['owner']) )
		$data['owner'] = $data['owner'][0];
			
	if( $ownerdn != $data['owner'] )
		throw new ApiException("Forbidden", 403, "User {$user} does not match owner of the repo {$repo}");

	// =================================
	// DELETE REMOTE REPO
	// =================================
	$GLOBALS['ldap']->delete($dn);
	$commands[] = "rm -Rf {$data['homeDirectory']}";
	$GLOBALS['system']->exec($commands);
	
	// =================================
	// UPDATE REMOTE USER
	// =================================
	$mod['member'] = $dn;
	$GLOBALS['ldap']->replace($user_dn, $mod, ldap::DELETE);
	
	responder::send("OK");
});

return $a;

?>