<?php

if( !defined('PROPER_START') )
{
	header("HTTP/1.0 403 Forbidden");
	exit;
}

if( security::hasGrants(array('ACCESS', 'TOKEN_CLEANUP')) )
{
	// =================================
	// CLEANUP INVALID TOKEN GRANTS
	// =================================
	$sql = "DELETE tk FROM token_grant tk
			LEFT JOIN tokens t ON(t.token_id = tk.token_id)
			LEFT JOIN (
				SELECT DISTINCT k.grant_id, u.user_id FROM users u 
				LEFT JOIN user_grant uk ON(u.user_id = uk.user_id)
				LEFT JOIN user_group ug ON(u.user_id = ug.user_id)
				LEFT JOIN group_grant gk ON(ug.group_id = gk.group_id)
				LEFT JOIN grants k ON(k.grant_id = gk.grant_id OR k.grant_id = uk.grant_id)
				) tmp ON(t.token_user = tmp.user_id AND tk.grant_id = tmp.grant_id)
			WHERE tmp.grant_id IS NULL OR tmp.user_id IS NULL;";
	$GLOBALS['db']->query($sql, mysql::NO_ROW);

	// CAUTION, THIS DOES NOT RETURN ANYTHING, IT SHOULD BE CALLED FROM ANOTHER PAGE ONLY !
	// THOUGH IS DOESNT REALLY MATTER IF CALLED DIRECTLY AS THIS IS JUST SANITIZING.
}

?>