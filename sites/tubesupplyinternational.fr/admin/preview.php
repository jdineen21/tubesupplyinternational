<?php $currentSection = "preview"; $currentPageName = "Preview"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php

$_SESSION['a_contentPreview'] = $_GET['mode'] ?? '';

$url = '/';

if (($_GET['branchId'] ?? '') != '') {
	$url = getURL($_GET['branchId'] ?? '');
}

?>
<!DOCTYPE html>

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("inc/htmlhead.php") ?>
</head>
<body class="<?php echo $currentSection ?>Page">
    <div id="alertMessage" class="error"></div>

    <?php include("inc/header.php") ?>
    <?php include("inc/sidebar.php") ?>

    <div id="content">
        <div class="inner">

            <?php include("inc/topcolumn.php") ?>

            <!-- Start content -->

			<a class="uibutton icon prev " href="content-edit.htm?action=editCurrent">Edit current page</a>
			<h3>Viewing <?php echo htmlspecialchars($_GET['mode'] ?? '') ?> preview</h3>

			
            <iframe src="<?php echo $url ?>" style="width: 100%; height: 1500px; border: 0;"></iframe>




            <?php include("inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->
</body>
</html>