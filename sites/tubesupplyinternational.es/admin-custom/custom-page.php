<?php $currentSection = ""; $currentPageName = ""; ?>
<?php include("../common/settings.php") ?>
<?php include("../admin/inc/settings.php") ?>
<?php include("../admin/inc/functions.php") ?>
<?php include("../admin/inc/main.php") ?>
<?php



?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("../admin/inc/htmlhead.php") ?>
</head>
<body class="<?php echo $currentSection ?>">
    <div id="alertMessage" class="error"></div>

    <?php include("../admin/inc/header.php") ?>
    <?php include("../admin/inc/sidebar.php") ?>

    <div id="content">
        <div class="inner">

            <?php include("../admin/inc/topcolumn.php") ?>

            <!-- Start content -->



            <!-- full width -->
            <div class="widget">
                <div class="header"><span><span class="ico gray gear"></span>Custom Page</span>
                </div><!-- End header -->

                <div class="content">

                <!-- title box -->
                <div class="boxtitle min">Sub Heading</div>


                 


            </div><!-- End content -->
            </div><!-- End full width -->





            <?php include("../admin/inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->
    <script type="text/javascript">
        <?php if ($updated) { ?>
        showSuccess('Settings updated');
        <?php } ?>
    </script>
</body>
</html>