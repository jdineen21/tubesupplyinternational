<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php
echo generateSlug($_GET['slug'] ?? '');
?>