<?php include("../common/settings.php") ?>
<?php include("inc/settings.php") ?>
<?php include("inc/functions.php") ?>
<?php
$logout = false;

// Log out
if (($_GET['action'] ?? '') != "") {
    if (($_GET['action'] ?? '') == "logout") {
        session_destroy();
        $logout = true;
    }
}

// Log in
if (($_POST['email'] ?? '') != "") {
    if (login(($_POST['email'] ?? ''), ($_POST['password'] ?? ''))) {
        if (($_POST['remember'] ?? '') == 1) {
            $expire=time()+60*60*24*365;
            setcookie("adminEmail", ($_POST['email'] ?? ''), array('expires' => $expire, 'httponly' => true, 'samesite' => 'Lax'));
        } else {
            setcookie("adminEmail", "", time()-3600);
        }
        echo '1';
    } else {
        sleep(1);
        echo '0';
    }
    exit();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
		<meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
<title><?php echo $siteName ?> | Login</title>
        <!--[if lt IE 9]>
          <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->
<link href="css/style.css" rel="stylesheet" type="text/css" />
<link href="css/icon.css" rel="stylesheet" type="text/css" />
<link rel="stylesheet" type="text/css" href="components/tipsy/tipsy.css" media="all"/>
<style type="text/css">
html {
	background-image: none;
}
label.iPhoneCheckLabelOn span {
	padding-left:0px
}
#versionBar {
	background-color:#212121;
	position:fixed;
	width:100%;
	height:35px;
	bottom:0;
	left:0;
	text-align:center;
	line-height:35px;
	z-index:11;
}
.copyright{
	text-align:center; font-size:10px; color:#CCC;
}
.copyright a{
	color:#a3d7e8; text-decoration:none
}    
</style>
</head>
<body class="loginPage">
         
<div id="alertMessage" class="error"></div>
<div id="successLogin"></div>
<div class="text_success"><img src="images/loadder/loader_green.gif"  alt="" /><span>Please wait</span></div>

<div id="login" >
  <div class="ribbon"></div>
  <div class="inner">
  <div class="logo" ><img src="/admin-custom/images/login-logo.png" alt="" /></div>
  <div class="formLogin">
   <form name="formLogin"  id="formLogin" action="">

          <div class="tip">
				<input name="email" type="text"  id="username_id"  title="Email" value="<?php echo htmlspecialchars($_COOKIE['adminEmail'] ?? '') ?>" />
          </div>
          <div class="tip">
				<input name="password" type="password" id="password"   title="Password"  />
          </div>

          <div class="loginButton">
              <div style="float:left; margin-left:-9px;">
                  <input type="checkbox" id="on_off" name="remember" class="on_off_checkbox"  value="1" <?php if (isset($_COOKIE['adminEmail'])) { ?>checked="checked"<?php } ?> />
                  <span class="f_help">Remember me</span>
              </div>
              <div style="float:right; padding:3px 0; margin-right:-12px;">
                  <div>
                    <ul class="uibutton-group">
                       <li><a class="uibutton normal" href="#" id="but_login" >Login</a></li>
                   </ul>
                  </div>

              </div>
			<div class="clear"></div>
		  </div>

    </form>
  </div>
</div>
  <div class="clear"></div>
  <div class="shadow"></div>
</div>

<!--Login div-->
<div class="clear"></div>
<div id="versionBar" >
  <div class="copyright" > &copy; Copyright <?php echo date('Y') ?> <?php if ($devName) { ?><span class="tip"><a href="http://www.climbcreative.co.uk" title="<?php echo $cmsName ?> by <?php echo $devName ?>" ><?php echo $devName ?></a></span><?php } ?> </div>
  <!-- // copyright-->
</div>
<!-- Link JScript-->
<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="components/effect/jquery-jrumble.js"></script>
<script type="text/javascript" src="components/ui/jquery.ui.min.js"></script>     
<script type="text/javascript" src="components/tipsy/jquery.tipsy.js"></script>
<script type="text/javascript" src="components/checkboxes/iphone.check.js"></script>
<script type="text/javascript" src="js/logincheck.js"></script>
<script type="text/javascript">

    $("input[type=password]") // submit form on 'return' press
        .keydown(function(e) {
            if (e.keyCode == 13)
                $(this).closest("form").submit();
        });

    <?php if ($logout) { ?>
    showSuccess('You have been logged out');
    <?php } ?>
    <?php if (isset($_COOKIE['adminEmail'])) { ?>
    $('#password').focus();
    <?php } else { ?>
    $('#username_id').focus();
    <?php } ?>
</script>
</body>
</html>