<?php $currentSection = "settings"; $currentPageName = "Settings"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php
$updated = false;

if (($_POST['action'] ?? '') == 'submit') {

    $query = sprintf("UPDATE a_settings SET offlineMode = '%s', analyticsUA = '%s'",
        db_escape( iif(($_POST['offlineMode'] ?? ''),'N','Y') ),
		db_escape( $_POST['analyticsUA'] ?? '')
		);
    db_query($query);

    $updated = true;

}



$query = sprintf("SELECT * FROM a_settings");
$settingsResult = db_query($query);
$settingsRow = db_fetch_assoc($settingsResult);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("inc/htmlhead.php") ?>
</head>
<body class="<?php echo $currentSection ?>">
    <div id="alertMessage" class="error"></div>

    <?php include("inc/header.php") ?>
    <?php include("inc/sidebar.php") ?>

    <div id="content">
        <div class="inner">

            <?php include("inc/topcolumn.php") ?>

            <!-- Start content -->



            <!-- full width -->
            <div class="widget">
                <div class="header"><span><span class="ico gray gear"></span>Site Settings</span>
                </div><!-- End header -->

                <div class="content">

                <!-- title box -->
                <div class="boxtitle min">General Settings</div>


                    <form id="settingsForm" action="settings.htm" method="post" >

                        <div class="section">
                            <label>Site Online / Offline<small>Set this to OFF to put the site offline. The offline template will be used.</small></label>
                            <div>
                                <input type="checkbox" name="offlineMode" class="on_off_checkbox" value="Y" <?php if ($settingsRow['offlineMode'] == 'N') { echo 'checked="checked"'; } ?>  />
                            </div>
                        </div>
						
						<div class="section" >
                            <label>Google Analytics UA code <small>Enter just the account code, i.e UA-XXXXX-X, not the HTML.</small></label>   
                            <div> <input type="text" name="analyticsUA" id="analyticsUA" class="" value="<?php echo ($settingsRow['analyticsUA']) ?>" /></div>
                        </div>



                    <div class="section last">
                        <input type="hidden" name="action" value="submit" />
                        <div><a class="uibutton loading" title="Saving" rel="javascript:;" onclick="changesMade = false; $(this).parents('form').submit();" >Save Changes</a></div>
                    </div>

                    </form>



            </div><!-- End content -->
            </div><!-- End full width -->





            <?php include("inc/footer.php") ?>
        </div> <!--// End inner -->
    </div> <!--// End content -->
    <script type="text/javascript">
        <?php if ($updated) { ?>
        showSuccess('Settings updated');
        <?php } ?>
    </script>
</body>
</html>