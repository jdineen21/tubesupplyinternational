<?php $currentSection = "dashboard"; $currentPageName = "Dashboard"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

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

			<?php if ($dashboardMessage) { ?>
			<div id="dashboardMessage"></div><script type="text/javascript">$('#dashboardMessage').load('ajax/dashboard-message-proxy.htm');</script>
			<?php } ?>

            <!-- full width -->
            <div class="widget">
                <div class="header"><span><span class="ico gray home"></span>Welcome</span>
                </div><!-- End header -->

                <div class="content">

                <!-- title box -->
                <div class="boxtitle min">website status</div>
				
				Your site is currently <?php if ($siteSettings['offlineMode'] == 'Y') { ?><span style="color: #dc1d1d">offline</span>. This can be changed in the settings menu.<?php } else { ?>online.<?php } ?>

                </div><!-- End content -->
            </div><!-- End full width -->
			
			

			<?php include("../admin-custom/dashboard.php") ?>



            <?php include("inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->
</body>
</html>