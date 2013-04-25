<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

$a = new action();
$a->addAlias(array('bcompute'));
$a->setDescription("Compute user billing");
$a->addGrant(array('ACCESS', 'USER_SELECT'));
$a->setReturn("OK");

$a->setExecute(function() use ($a)
{
	// =================================
	// CHECK AUTH
	// =================================
	$a->checkAuth();

	// =================================
	// GET CURRENT DATE
	// =================================
	if( date('j') != 1 )
		throw new ApiException("Not the 1", 500, "We are not the first day of the month!");
	else
	{
		$day = 1;
		$month = date('n');
		$year = date('Y');
		
		$now = mktime(0, 0, 0, $month, $day, $year);
		$datenow = date('Y-m-d', $now);
		$previousmonth = mktime(0, 0, 0, $month-1, $day, $year);
		$dateprevious = date('Y-m-d', $previousmonth);
		$daysinmonth =  round(($now-$previousmonth)/3600/24);
	}

	// =================================
	// GET USERS
	// =================================	
	$sql = "SELECT u.user_id, u.user_name
			FROM users u
			WHERE u.user_id != 1";
	$result = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

	foreach( $result as $r )
	{
		// =================================
		// GET NON BILLED USER PLANS
		// =================================
		$sql = "SELECT p.plan_desc, p.plan_id, p.plan_price, p.plan_type, up.plan_start_date, up.plan_end_date
				FROM user_plan up
				LEFT JOIN plans p ON(p.plan_id = up.plan_id)
				WHERE up.user_id = {$r['user_id']} AND up.plan_billed = 0";
		$plans = $GLOBALS['db']->query($sql, mysql::ANY_ROW);

		$bill = array();
		$current['memory'] = 0;
		$current['disk'] = 0;
		$i = 0;
		foreach( $plans as $p )
		{
			// =================================
			// ALL PLANS END NOW
			// =================================
			if( $p['plan_end_date'] == 0 )
			{
				$type = $p['plan_type'];
				$p['plan_end_date'] = $now;
				$sql = "UPDATE user_plan SET plan_end_date = {$p['plan_end_date']}, plan_billed = 1 WHERE plan_id = {$p['plan_id']} AND user_id = {$r['user_id']} AND plan_start_date = {$p['plan_start_date']}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);
				$current[$type] = $p['plan_id'];
			}
			else
			{
				$sql = "UPDATE user_plan SET plan_billed = 1 WHERE plan_id = {$p['plan_id']} AND user_id = {$r['user_id']} AND plan_start_date = {$p['plan_start_date']}";
				$GLOBALS['db']->query($sql, mysql::NO_ROW);			
			}

			// =================================
			// COMPUTE PLANS DURATION
			// =================================
			$duration = round(($p['plan_end_date']-$p['plan_start_date'])/3600/24);
			$amount = round($duration*$p['plan_price']/$daysinmonth);
			
			$bill[$i]['plan'] = $p['plan_desc'];
			$bill[$i]['duration'] =  $duration . ' days';
			$bill[$i]['price'] =  $amount . ' euros';
			$bill[$i]['dates'] = array('start' => date('Y-m-d', $p['plan_start_date']), 'end' => date('Y-m-d', $p['plan_end_date']));
			
			$i++;
		}

		// =================================
		// INSERT NEW PLAN CYCLE
		// =================================			
		if( $current['memory'] != 0 )
		{
			$sql = "INSERT INTO user_plan (plan_id, user_id, plan_start_date) VALUES ({$current['memory']}, {$r['user_id']}, '{$now}')";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
		if( $current['disk'] != 0 )
		{
			$sql = "INSERT INTO user_plan (plan_id, user_id, plan_start_date) VALUES ({$current['disk']}, {$r['user_id']}, '{$now}')";
			$GLOBALS['db']->query($sql, mysql::NO_ROW);
		}
		
		$foo = print_r($bill, true);
		$mail = "<pre>{$foo}</pre>";
		mail('samuel.hassine@anotherservice.com', "[BILLING] From {$dateprevious} to {$datenow} - User {$r['user_name']} ({$r['user_id']}) ", $mail, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: Another Service <no-reply@anotherservice.com>\r\n");
	}
	
	responder::send("OK");
});

return $a;

?>