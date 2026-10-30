<?php
/*
Atlas (c) Climb Creative 2012
*****************************************************************
*/

// Site specific
$siteName = "TSI";
$siteURL = "http://www.tubesupplyinternational.co.uk"; // URL without trailing slash
$dashboardMessage = false; // Enables the Atlas dashboard message ajaxed from atlas.climbcreative.co.uk/dashboard-message.php (that server no longer answers)

// Database settings
require(__DIR__ . '/config.php'); // $dbhost, $dbname, $dbuser, $dbpass, $dbcharset
require(__DIR__ . '/db.php');

$dbMain = db_connect($dbhost, $dbuser, $dbpass, $dbname, $dbcharset ?? '') or die ('Error connecting to database. Please try again later.');


// Other vars
$liveMode = "live"; // dev,live  change depending on environment

// PHP 8 reports many old notices as warnings. On the live site write them
// to the error log rather than showing them to visitors.
if ($liveMode == 'live') {
	ini_set('display_errors', '0');
	ini_set('log_errors', '1');
}

$cmsName = "Atlas";
$devName = "Climb Creative";



// Handle the sesssion
// (A session id in the URL, ?sid=, is no longer accepted: it let anyone fix a
// visitor's session, and the Flash uploader that needed it no longer runs.)
ini_set('session.use_strict_mode', '1');  // only use session ids this server created
ini_set('session.cookie_httponly', '1');  // page scripts cannot read the session cookie
ini_set('session.cookie_samesite', 'Lax'); // other sites cannot post forms with an admin's session
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
	ini_set('session.cookie_secure', '1');
}
session_start();
$currentSessionId = session_id();
?>
