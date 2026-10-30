<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php

$menuId = $_GET['menuId'] ?? '';
$i = 0;

// reset the menu first
$query = sprintf("UPDATE a_tree AS T INNER JOIN a_branchtypes B ON T.type = B.typeName  SET T.menuId = 0, T.sortOrder = 0  WHERE T.menuId = '%s' AND B.inStructure = 'Y'",
    db_escape($menuId));

db_query($query);

foreach (($_POST['branchId'] ?? array()) as $key => $value) {

    $i++;
    $branchId = $key;
    $parentId = $value;
    if ($parentId == 'root') { $parentId = 0; }
    $sortOrder = $i;

    $query = sprintf("UPDATE a_tree SET menuId = '%s', parentId = '%s', sortOrder = '%s' WHERE branchId = '%s'",
        db_escape($menuId),
        db_escape($parentId),
        db_escape($sortOrder),
        db_escape($branchId));
    db_query($query);
}


?>