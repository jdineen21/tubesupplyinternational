<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php

$typeId = $_POST['typeId'] ?? '';
$orderByPost = $_POST['orderBy'] ?? '';
$orderBy = '';
$orderByDirection = '';

if ($orderByPost == 'dragdrop') {
	$orderBy = 'sortOrder';
	$orderByDirection = 'ASC';
}

if ($orderByPost == 'dateAsc') {
	$orderBy = 'dateAdded';
	$orderByDirection = 'ASC';
}

if ($orderByPost == 'dateDesc') {
	$orderBy = 'dateAdded';
	$orderByDirection = 'DESC';
}

if ($orderByPost == 'alphaAsc') {
	$orderBy = 'title';
	$orderByDirection = 'ASC';
}

if ($orderByPost == 'alphaDesc') {
	$orderBy = 'title';
	$orderByDirection = 'DESC';
}

$query = sprintf("UPDATE a_branchtypes SET orderBy = '%s', orderByDirection = '%s' WHERE typeId = '%s'",        
	db_escape($orderBy),
    db_escape($orderByDirection),
    db_escape($typeId));
db_query($query);

?>