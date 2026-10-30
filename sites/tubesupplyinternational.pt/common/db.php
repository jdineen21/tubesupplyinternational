<?php
/*
Database functions used by the site, built on mysqli.

These replace the old mysql_* functions, which PHP 7 removed. They keep
the old behaviour the site relies on: a query that fails returns false
(and is written to the PHP error log) instead of stopping the page, and
fetching from a failed query returns false.
*****************************************************************
*/

function db_connect($host, $user, $pass, $name, $charset = '') {
	global $dbMain;

	mysqli_report(MYSQLI_REPORT_OFF); // return false on errors, like mysql_* did
	$dbMain = @mysqli_connect($host, $user, $pass, $name);
	if (!$dbMain) {
		error_log('Database connection failed: ' . mysqli_connect_error());
		return false;
	}

	if ($charset != '') {
		mysqli_set_charset($dbMain, $charset);
	}

	// The site was written for MySQL 5 without strict mode (e.g. it saves ''
	// into number columns), so keep strict mode off on newer servers too.
	mysqli_query($dbMain, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");

	return $dbMain;
}

function db_query($query) {
	global $dbMain;

	$result = mysqli_query($dbMain, $query);
	if ($result === false) {
		error_log('Database query failed: ' . mysqli_error($dbMain) . ' - ' . $query);
	}
	return $result;
}

function db_escape($value) {
	global $dbMain;
	return mysqli_real_escape_string($dbMain, (string) $value);
}

function db_fetch_assoc($result) {
	if (!$result instanceof mysqli_result) {
		return false;
	}
	$row = mysqli_fetch_assoc($result);
	return $row === null ? false : $row;
}

function db_num_rows($result) {
	return $result instanceof mysqli_result ? mysqli_num_rows($result) : 0;
}

function db_insert_id() {
	global $dbMain;
	return mysqli_insert_id($dbMain);
}

function db_data_seek($result, $row) {
	return $result instanceof mysqli_result ? mysqli_data_seek($result, $row) : false;
}
?>
