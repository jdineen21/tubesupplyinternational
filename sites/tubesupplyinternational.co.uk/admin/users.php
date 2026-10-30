<?php $currentSection = "users"; $currentPageName = "Users"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php
$updated = false;

if (($_GET['action'] ?? '') == 'added') {

    $updated = true;

}

if (($_POST['action'] ?? '') == 'delete') {

	$query = sprintf("DELETE FROM a_users WHERE userId = '%s'",
		db_escape( $_POST['actionId'] ?? '' )
		);
    db_query($query);

    $updated = true;

}

$query = sprintf("SELECT * FROM a_users WHERE email <> 'mailus@climbcreative.co.uk' ORDER BY username");
$usersResult = db_query($query);

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
                <div class="header"><span><span class="ico gray group"></span>Admin Users</span>
					<div id="buttom_top"><a class="uibutton icon add" href="users-new.htm" >New User</a></div>
                </div><!-- End header -->

                <div class="content">

              
			  
			  	<table class="display">
                    <thead>
                      <tr>
                        <th width="174" align="left">Username</th>
                        <th width="352" >Email Address</th>
                        <th width="246" >Last Login</th>
                        <th width="199" >Management</th>
                      </tr>
                    </thead>
                    <tbody>
                  
                      <?php while($usersRow = db_fetch_assoc($usersResult)) { ?>
					  <tr>

                        <td align="left"><?php echo $usersRow['username'] ?></td>
                        <td><?php echo $usersRow['email'] ?></td>
                        <td><?php echo $usersRow['lastLogin'] ?></td>
                        <td>
                          <span class="tip" >
                              <a href="users-edit.htm?userId=<?php echo $usersRow['userId'] ?>" title="Edit" >
                                 <img src="images/icon/icon_edit.png" >
                              </a>
                          </span> 
						  
						  <form action="users.htm" method="POST" style="display: inline">
						  	<input type="hidden" name="action" class="formAction" value="delete">
						  	<input type="hidden" name="actionId" class="formActionId" value="<?php echo $usersRow['userId'] ?>">
                          	<span class="tip" >
                              <a id="1" class="Delete" name="<?php echo $usersRow['username'] ?>" title="Delete"  >
                                  <img src="images/icon/icon_delete.png" >
                              </a>
                          	</span> 
						  </form>
                        </td>
                      </tr>
						<?php } ?>
                   
                    </tbody>
               </table>

			  
			  
			  


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