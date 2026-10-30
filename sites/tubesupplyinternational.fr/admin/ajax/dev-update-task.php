<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php

$taskId = $_POST['taskId'] ?? '';
$status = $_POST['status'] ?? '';

if ($status == 'true') {
	$status = 'Y';
} else {
	$status = 'N';
}

$query = sprintf("UPDATE dev_tasks SET complete = '%s' WHERE taskId = '%s'",        
	db_escape($status),    
    db_escape($taskId));
db_query($query);
?>