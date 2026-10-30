<?php $currentSection = "users"; $currentPageName = "Add New User"; ?>
<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php include("inc/main.php") ?>
<?php
$updated = false;
$error = '';

if (($_POST['action'] ?? '') == 'submit') {

	$username = strip_tags($_POST['username'] ?? '');
	$username = trim(substr($username, 0, 50));
	
	$email = strip_tags($_POST['email'] ?? '');
	$email = trim(substr($email, 0, 255));
	
	$password = $_POST['password'] ?? '';
	

	//check if email already exists
	$query = sprintf("SELECT * FROM a_users WHERE email = '%s'",
		db_escape( $_POST['email'] ?? '' )
		);
	$userResult = db_query($query);
	if (db_num_rows($userResult) > 0) {
		$error = 'A user already exists with that email address';
	} elseif (strlen($password) < 6) {
		$error = 'Please choose a password of at least 6 characters';
	} else {
		
		$query = sprintf("INSERT INTO a_users SET username = '%s', email = '%s', password = '%s'",
	        db_escape( $username ),
			db_escape( $email ),
			db_escape( encrypt($password) )
			);
    	db_query($query);
		setUserPassword(db_insert_id(), $password);
		

    	header('location: users.htm?action=added');
	}

}




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
                <div class="header"><span><span class="ico gray gear"></span>Add New User</span>
                	<div id="buttom_top"><a class="uibutton special icon answer" href="users.htm" >Cancel</a></div>
				</div><!-- End header -->

                <div class="content">

					<?php if ($error) { ?>
						<div class="alertMessage error"><?php echo $error ?></div>
					<?php } ?>

                    <form id="settingsForm" action="users-new.htm" method="post" class="validate" >

                        <div class="section">
                            <label>Username<small>This will how you will be identified in the admin</small></label>
                            <div>
                                <input type="text" name="username" id="username" class="validate[required] full"  />
                            </div>
                        </div>


						<div class="section">
                            <label>Email Address<small>You will login via your email</small></label>
                            <div>
                                <input type="text" name="email" id="email" class="validate[required,custom[email]] full"  />
                            </div>
                        </div>

						<div class="section">
                            <label>Password<small>Must be at least 6 characters</small></label>
                            <div>
                                      <input type="password"  class="validate[required,minSize[6]] medium"  name="password" id="password"  /><label>Password</label>
                                      </div>
                                      <div>
                                      <input type="password" class="validate[required,equals[password]] medium"  name="passwordCon" id="passwordCon"  /><label>Confirm Password</label>
                                            
                                      </div>

                        </div>


                    <div class="section last">
                        <input type="hidden" name="action" value="submit" />
                        <div><a class="uibutton loading" title="Saving" rel="javascript:;" onclick="changesMade = false; $(this).parents('form').submit();" >Add User</a></div>
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