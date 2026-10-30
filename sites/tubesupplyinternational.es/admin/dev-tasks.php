<?php $currentSection = "developer"; $currentPageName = "Task List"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php
$updated = false;
if (($_SESSION['a_loginType'] ?? '') != 'developer') {
	header('location: /admin');
	exit;
}


if (($_POST['action'] ?? '') == 'submit') {

	$query = sprintf("INSERT INTO dev_tasks SET task = '%s'",
	        db_escape( $_POST['new'] ?? '' )
			);
    	db_query($query);


}



$query = sprintf("SELECT * FROM dev_tasks ORDER BY complete,sortOrder");
$tasksResult = db_query($query);


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <title><?php echo $siteName ?> | <?php echo $currentPageName ?></title>
    <?php include("inc/htmlhead.php") ?>
	
	<script type="text/javascript">
		
		function updateTask(id) {
			
			postData = 'taskId=' + id + '&status=' + $('#taskId-'+id).is(':checked');
			$.ajax({
                type: "POST",
                url: "ajax/dev-update-task.htm",
                data: postData
            });
		
			changesMade = false;
		}
	
	
	</script>
	
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
                <div class="header"><span><span class="ico gray gear"></span>Task List</span>
                </div><!-- End header -->

                <div class="content">

                <!-- title box -->


                    <form id="settingsForm" action="dev-tasks.htm" method="post" >

                        <div class="section">
                            
							<?php while($task = db_fetch_assoc($tasksResult)) { ?>
							<div <?php if ($task['complete'] == 'Y') { echo 'class="doneTask"'; } ?>>
								<input type="checkbox" name="genre" id="taskId-<?php echo $task['taskId'] ?>"  value="Y" class="ck" onclick="updateTask(<?php echo $task['taskId'] ?>)" <?php if ($task['complete'] == 'Y') { echo 'checked="checked"'; } ?>/>
								<label for="taskId-<?php echo $task['taskId'] ?>"><?php echo $task['task'] ?></label>
							</div>
							<?php } ?>
							
							
                        </div>

						<div class="section last">
							<div>
                                <input type="text" name="new" id="new" class="validate[required] full" maxlength="1000"  />
                            </div>
                       	 <input type="hidden" name="action" value="submit" />
                       	 <div><a class="uibutton loading" title="Saving" rel="javascript:;" onclick="changesMade = false;$(this).parents('form').submit();" >Add New Task</a></div>
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