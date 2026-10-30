<?php
/*
Atlas (c) Climb Creative Ltd 2012
*****************************************************************


SESSIONS:********************************************************
$_SESSION["a_email"]
$_SESSION["a_login"] - the password

*/



//Set no caching
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); 
header("Cache-Control: no-store, no-cache, must-revalidate"); 
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");


$siteSettings = db_fetch_assoc(db_query('SELECT a_settings.*, HTREE.templateId AS homeTemplateId, H.templateName AS homeTemplateName, OTREE.templateId AS offlineTemplateId, O.templateName AS offlineTemplateName FROM a_settings LEFT OUTER JOIN a_tree HTREE ON a_settings.homeBranchId = HTREE.branchId LEFT OUTER JOIN a_templates H ON HTREE.templateId = H.templateId   LEFT OUTER JOIN a_tree OTREE ON a_settings.offlineBranchId = OTREE.branchId LEFT OUTER JOIN a_templates O ON OTREE.templateId = O.templateId'));

include($_SERVER['DOCUMENT_ROOT'] . '/admin/version.php');

?>