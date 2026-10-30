<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php

$type = $_GET['type'] ?? '';
$i = 0;


foreach (($_POST[$type] ?? array()) as $key => $value) {

    $i++;
    $branchId = $value;
    
    $sortOrder = $i;

    $query = sprintf("UPDATE a_tree SET sortOrder = '%s' WHERE branchId = '%s'",        
        db_escape($sortOrder),
        db_escape($branchId));
    db_query($query);

    //echo $query . "\n";
}


?>