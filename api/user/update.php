<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('modify', 'change'));
$a->setDescription("Modify a user");
$a->addGrant(array('ACCESS', 'USER_UPDATE'));
$a->setReturn("OK");
$a->addParam(array(
	'name'=>array('name', 'user_name', 'username', 'login', 'user', 'id', 'user_id', 'uid'),
	'description'=>'The name or id of the user',
	'optional'=>false,
	'minlength'=>1,
	'maxlength'=>30,
	'match'=>request::LOWER|request::NUMBER|request::PUNCT,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('pass', 'password', 'user_password', 'user_pass'),
	'description'=>'The password of the user.',
	'optional'=>true,
	'minlength'=>3,
	'maxlength'=>50,
	'match'=>request::PHRASE|request::SPECIAL,
	'action'=>true
	));
$a->addParam(array(
	'name'=>array('firstname', 'givenname', 'first_name', 'user_firstname', 'user_givenname', 'user_first_name', 'user_given_name'),
	'description'=>'The first name of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('lastname', 'sn', 'user_lastname', 'user_sn', 'user_last_name'),
	'description'=>'The last name of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('mail', 'email', 'user_email', 'user_mail'),
	'description'=>'The email of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>150,
	'match'=>"^[_\\w\\.-]+@[a-zA-Z0-9\\.-]{1,100}\\.[a-zA-Z0-9]{2,6}$"
	));
$a->addParam(array(
	'name'=>array('language', 'lang'),
	'description'=>'The user language.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>2,
	'match'=>request::UPPER
	));
$a->addParam(array(
	'name'=>array('ip'),
	'description'=>'IP address of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('plan'),
	'description'=>'The user plan.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('plan_type'),
	'description'=>'The user plan type (storage || memory).',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::LOWER
	));
$a->addParam(array(
	'name'=>array('iban'),
	'description'=>'The user plan.',
	'optional'=>true,
	'minlength'=>10,
	'maxlength'=>150,
	'match'=>request::LOWER|request::UPPER|request::NUMBER
	));
$a->addParam(array(
	'name'=>array('bic'),
	'description'=>'The user BIC.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>50,
	'match'=>request::LOWER|request::UPPER|request::NUMBER
	));
$a->addParam(array(
	'name'=>array('postal_address', 'address', 'user_address'),
	'description'=>'The postal address of the user (JSON encoded).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>500,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('postal_code', 'code'),
	'description'=>'The postal code of the user.',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>5,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('organisation', 'o'),
	'description'=>'The organisation of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('locality', 'l', 'city'),
	'description'=>'The city of the user.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::PHRASE
	));
$a->addParam(array(
	'name'=>array('status', 'user_status'),
	'description'=>'The user status.',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('report'),
	'description'=>'Receive reports?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
	'match'=>"(1|0|yes|no|true|false)"
	));
$a->addParam(array(
	'name'=>array('zabbix'),
	'description'=>'The user zabbix id.',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>50,
	'match'=>request::NUMBER
	));
$a->addParam(array(
	'name'=>array('key', 'ssh'),
	'description'=>'The SSH key',
	'optional'=>true,
	'minlength'=>0,
	'maxlength'=>1000,
	'match'=>request::ALL
	));
$a->addParam(array(
	'name'=>array('mode'),
	'description'=>'Mode for alternate or redirection email (can be add/delete).',
	'optional'=>true,
	'minlength'=>2,
	'maxlength'=>6,
	'match'=>"(add|delete)"
	));
$a->addParam(array(
	'name'=>array('billing'),
	'description'=>'Billing?',
	'optional'=>true,
	'minlength'=>1,
	'maxlength'=>5,
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
	$pass = $a->getParam('pass');
	$firstname = $a->getParam('firstname');
	$lastname = $a->getParam('lastname');
	$mail = $a->getParam('mail');
	$plan = $a->getParam('plan');
	$plan_type = $a->getParam('plan_type');
	$iban = $a->getParam('iban');
	$bic = $a->getParam('bic');
	$address = $a->getParam('postal_address');
	$code = $a->getParam('postal_code');
	$organisation = $a->getParam('organisation');
	$locality = $a->getParam('locality');
	$status = $a->getParam('status');
	$report = $a->getParam('report');
	$zabbix = $a->getParam('zabbix');
	$language = $a->getParam('language');
	$key = $a->getParam('key');
	$mode = $a->getParam('mode');
	$billing = $a->getParam('billing');
	
	if( $status == '0' || $status == 'no' || $status == 'false' || $status === false || $status === 0 ) $status = 0;
	else if( $status !== null ) $status = 1;
	else $status = 'user_status';

	if( $plan_type === null )
		$plan_type = 'memory';
	
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
	// GET PLAN INFO
	// =================================		
	$sql = "SELECT up.plan_id, up.plan_start_date 
			FROM user_plan up
			LEFT JOIN plans p ON(p.plan_id = up.plan_id)
			WHERE up.user_id = {$result['user_id']} AND p.plan_type = '{$plan_type}' ORDER BY up.plan_start_date DESC";
	$plan_info = $GLOBALS['db']->query($sql);
	
	// =================================
	// GET REMOTE USER INFO
	// =================================		
	$dn = ldap::buildDN(ldap::USER, $GLOBALS['CONFIG']['DOMAIN'], $result['user_name']);
	$data = $GLOBALS['ldap']->read($dn);

	// =================================
	// UPDATE USER
	// =================================
	if( $status == 1 )
		$last = time();
	else
		$last = 'user_last';
		
	$sql = "UPDATE users SET user_iban = ".($iban!=null?"'{$iban}'":"user_iban").", user_bic = ".($bic!==null?"'{$bic}'":"user_bic").", user_report = ".($report!==null?"'{$report}'":"user_bic").", user_zabbix = ".($zabbix!=null?"'{$zabbix}'":"user_zabbix").", user_billing = ".($billing!=null?"'{$billing}'":"user_billing").", user_status = {$status}, user_last = {$last} WHERE user_id = {$result['user_id']}";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);
	
	// =================================
	// UPDATE PLAN
	// =================================	
	if( $plan !== null && $plan != $plan_info['plan_id'] )
	{
		if( $plan_info['plan_id'] )
		{
			$sql = "UPDATE user_plan SET plan_end_date = ".time()." WHERE plan_id = {$plan_info['plan_id']} AND user_id = {$result['user_id']} AND plan_start_date = {$plan_info['plan_start_date']}";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
		
		// Hardcoded conditions
		// end storage plans if memory plan includes enough disk space
		if( $plan_type == 'memory' )
		{
			// plans 3 and 4, plan 8 is included
			if( $plan == 3 || $plan == 4 )
			{
				$sql = "UPDATE user_plan SET plan_end_date = ".time()." WHERE plan_id = 8 AND user_id = {$result['user_id']}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
			}
			// plans 5 and 6, plan 8 and 9 are included
			elseif( $plan == 5 || $plan == 6 )
			{
				$sql = "UPDATE user_plan SET plan_end_date = ".time()." WHERE plan_id = 8 AND user_id = {$result['user_id']}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				$sql = "UPDATE user_plan SET plan_end_date = ".time()." WHERE plan_id = 9 AND user_id = {$result['user_id']}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
			}
		}
		
		if( $plan != 99 )
		{
			$sql = "INSERT INTO user_plan (plan_id, user_id, plan_start_date, plan_end_date) VALUES ({$plan}, {$result['user_id']}, ".time().", 0)";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);	
		}
	}
	
	// =================================
	// UPDATE REMOTE USER
	// =================================
	$params = array();
	$params2 = array();
	if( $pass !== null )
		$params['userPassword'] = $pass;
	if( $firstname !== null )
		$params['givenName'] = $firstname;
	if( $lastname !== null )
		$params['sn'] = $lastname;
	if( $mail !== null )
		$params['mailForwardingAddress'] = $mail;
	if( $address !== null )
		$params['postalAddress'] = $address;	
	if( $code !== null )
		$params['postalCode'] = $code;	
	if( $organisation !== null )
		$params['o'] = $organisation;	
	if( $locality !== null )
		$params['l'] = $locality;	
	if( $language !== null )
		$params['gecos'] = $language;	
	if( $key !== null && $mode == 'add'  )
		$params2['sshPublicKey'] = $key;
	$GLOBALS['ldap']->replace($dn, $params);

	if( $mode == 'add' )
		$GLOBALS['ldap']->replace($dn, $params2, ldap::ADD);
	elseif( $mode == 'delete' )
		$GLOBALS['ldap']->replace($dn, $params2, ldap::DELETE);	
	
	if( $key !== null && $mode == 'delete')
	{
		$newkeys = array();
		if( is_array($data['sshPublicKey']) )
		{
			$i = 0;
			foreach( $data['sshPublicKey'] as $k )
			{
				if( $i != $key )
					$newkeys[] = $k;
				$i++;
			}
		}
		
		$params3['sshPublicKey'] = $newkeys;
		$GLOBALS['ldap']->replace($dn, $params3);
	}
	
	try
	{
		if( $pass !== null )
		{	
			// =================================
			// UPDATE PIWIK USER
			// =================================
			$url = "https://{$GLOBALS['CONFIG']['PIWIK_URL']}/index.php?module=API&method=UsersManager.updateUser&userLogin={$result['user_name']}&password={$pass}&format=JSON&token_auth={$GLOBALS['CONFIG']['PIWIK_TOKEN']}";
			@file_get_contents($url);
		}
	}
	catch(Exception $e)
	{
	
	}
	
	// =================================
	// LOG ACTION
	// =================================	
	logger::insert('user/update', $a->getParams(), $result['user_id']);
	
	responder::send("OK");
});

return $a;

?>
