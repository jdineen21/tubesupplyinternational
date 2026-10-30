<?php include("../../common/settings.php") ?>
<?php include("../inc/settings.php") ?>
<?php include("../inc/functions.php") ?>
<?php include("../inc/main.php") ?>
<?php
// Off by default ($dashboardMessage in common/settings.php): the message
// server no longer answers, and its HTML goes straight into the admin page.
if ($dashboardMessage) {
	echo file_get_contents('http://atlas.climbcreative.co.uk/dashboard-message.php', false, stream_context_create(array('http' => array('timeout' => 3))));
}
?>