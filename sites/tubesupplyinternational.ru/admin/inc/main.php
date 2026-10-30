<?php

// check login status
if(isset($_SESSION['a_login']))
{
	// Get user info
	$query = sprintf("SELECT * FROM a_users WHERE email = '%s' AND password = '%s'",
	    db_escape($_SESSION['a_email'] ?? ''),
		db_escape($_SESSION['a_login'] ?? '')
		);
	$userInfoResult = db_query($query);
	$userInfoArr = db_fetch_assoc($userInfoResult);
	
	// if user is a viewer only then redirect to site
	if ($userInfoArr['loginType'] == 'viewer') {
		header("Location: /");
		exit;
	}
	
}
else
{
	// Not logged in
	header("Location: /admin/login.htm"); // absolute, so pages in admin/ajax and admin/editors reach it too
	exit;
}



?>