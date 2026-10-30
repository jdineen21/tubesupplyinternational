<?php
$sent = false;

if (($_POST['Action'] ?? '') == 'send') {

		include("./securimage/securimage.php");
		$img = new Securimage();
		$valid = $img->check($_POST['code'] ?? '');

		if($valid == true) {

	$email = $this->content('Email Address',0,'global');

	$subject = "Contact form message from TSI Website Spain.";
	$body = "Name: " . ($_POST["Name"] ?? '') . "\n\n";
	$body .= "Contact: " . ($_POST["Contact"] ?? '') . "\n\n";
	$body .= "Email: " . ($_POST["Email"] ?? '') . "\n\n";
	$body .= "Job Description: " . ($_POST["Job"] ?? '') . "\n\n";
	$body .= "Company Name: " . ($_POST["Company"] ?? '') . "\n\n";
	$body .= "Country: " . ($_POST["Country"] ?? '') . "\n\n";
	$body .= "Message: " . ($_POST["Message"] ?? '') . "\n\n";
	$headers = "From: $email";

	mail($email, $subject, $body, $headers);

	$sent = true;

	} else {
		// fail
		$errorMsg = "Code incorrect. Please enter the code again.";
	}

}
?>


<!doctype html>
<!--[if lt IE 7 ]> <html class="ie6" lang="es"> <![endif]-->
<!--[if IE 7 ]>    <html class="ie7" lang="es"> <![endif]-->
<!--[if IE 8 ]>    <html class="ie8" lang="es"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html lang="es"> <!--<![endif]-->
<head>
<?php include('includes/htmlhead.php');?>

<script src="/frontend/js/jquery.validate.min.js"></script>
<script src="/frontend/js/contact.js"></script>

</head>

<body>

<div class="wrapper">
<div class="container_16">
	<?php include('includes/header.php');?>

	<?php include('includes/sidebar.php');?>

	<div class="content grid_12">

   	<?php echo $this->content('Page Header Text'); ?>


     <div style="width: 690px; margin-bottom: 33px;">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1150.2513985143119!2d-1.5868146915444583!3d54.78870649162099!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x487e7dec329f0661%3A0xfb34e11f41da5aca!2sAykley%20Heads%2C%20Durham%20DH1%205TS!5e0!3m2!1sen!2suk!4v1599208297370!5m2!1sen!2suk" width="100%" height="330" frameborder="0" style="border:0;" allowfullscreen></iframe>
        <!-- <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2380.488283878005!2d-1.3683550669639744!3d53.3703123711805!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x48799c3103153529%3A0x83945d318eb9c668!2sUniversal+Supply+Ltd!5e0!3m2!1sen!2suk!4v1488386637522" width="100%" height="330" frameborder="0" style="border:0" allowfullscreen></iframe>
       -->
     </div>
<!--      <script src="http://maps.google.com/maps/api/js?sensor=false" type="text/javascript"></script>
            <script type="text/javascript">
            var point = new google.maps.LatLng(54.751169, -1.290885);
      var point2 = new google.maps.LatLng(54.751530, -1.291550);
            var centrePoint = new google.maps.LatLng(54.751330, -1.290885);

            var myMapOptions = {
                zoom: 17,     // change zoom level
                center: centrePoint,
                mapTypeControl: true,
        // scrollwheel: false,
                mapTypeControlOptions: {style: google.maps.MapTypeControlStyle.DROPDOWN_MENU},
                navigationControl: true,
                navigationControlOptions: {style: google.maps.NavigationControlStyle.SMALL},
                mapTypeId: google.maps.MapTypeId.HYBRID
            };

            var map = new google.maps.Map(document.getElementById('gMap'),myMapOptions);

            var image = new google.maps.MarkerImage(
              '/frontend/images/contact/map-pin.png',
              new google.maps.Size(40,55),
              new google.maps.Point(0,0),
              new google.maps.Point(25,50)
            );


            var marker = new google.maps.Marker({
              draggable: false,
              raiseOnDrag: false,
              icon: image,
              map: map,
              position: point
            });

      var marker = new google.maps.Marker({
              draggable: false,
              raiseOnDrag: false,
              icon: image,
              map: map,
              position: point2
            });

            </script> -->








    	<div class="grid_6 alpha contactText">
        	<?php echo $this->content('Left Column Text'); ?>
        </div><!-- grid_8 alpha -->

        <div class="grid_6 omega contactText">

		<table width="340" border="0" cellspacing="0" cellpadding="0" class="detailsTable">
              <tr>
                <td width="55"><p><strong>Tel:</strong></p></td>
                <td width="285"><?php echo $this->content('Telephone Number',0,'global'); ?></td>
              </tr>
              <tr>
                <td width="55"><p><strong>Email:</strong></p></td>
                <td width="285"><span><a href="mailto:<?php echo $this->content('Email Address',0,'global'); ?>"><?php echo $this->content('Email Address',0,'global'); ?></a></span></td>
              </tr>
        </table>




    	<?php echo $this->content('Right Column Text'); ?>

        </div><!-- grid_8 omega -->

	</div><!-- end content grid_12 -->





















    <div class="grid_12 omega contactForm">



	<?php if ($sent) { ?> Thank you for submitting your message. We will contact you shortly. <?php } else { ?>






            <form action="<?php echo htmlspecialchars($this->getURL()) ?>" method="post" id="contactForm" class="validate">
                <div class="grid_4 alpha content">
                    <p>Nombre (necesario)</p>
                    <input name="Name" type="text" value="<?php echo htmlspecialchars($_POST["Name"] ?? '');?>" class="required">
                </div><!-- END grid_4 -->

                <div class="grid_4 content">
                    <p>Numero de contacto (necesario)</p>
                    <input name="Contact" type="text" value="<?php echo htmlspecialchars($_POST["Contact"] ?? '');?>" class="required">
                </div><!-- END grid_4 -->

                <div class="grid_4 omega content">
                    <p>Correo electrónico</p>
                    <input name="Email" type="text" value="<?php echo htmlspecialchars($_POST["Email"] ?? '');?>" class="email">
                </div><!-- END grid_4 -->

                <div class="clear"></div>

                <div class="grid_4 alpha content">
                    <p>Descripción de las tareas</p>
                    <input name="Job" type="text" value="<?php echo htmlspecialchars($_POST["Job"] ?? '');?>">
                </div><!-- END grid_4 -->

                <div class="grid_4 content">
                    <p>Nombre de la  Compañía</p>
                    <input name="Company" type="text" value="<?php echo htmlspecialchars($_POST["Company"] ?? '');?>">
                </div><!-- END grid_4 -->

                <div class="grid_4 omega content">
                    <p>País</p>
                    <input name="Country" type="text" value="<?php echo htmlspecialchars($_POST["Country"] ?? '');?>">
                </div><!-- END grid_4 -->

                <div class="clear"></div>

                <div class="grid_12 alpha omega content">
                    <p>Su Mensaje:</p>
                    <textarea name="Message"><?php echo htmlspecialchars($_POST["Message"] ?? '');?></textarea>
              	</div><!-- end grid_12 alpha omega content -->


            	<div class="clear"></div>

                <div class="grid_4 alpha content">
					<?php if (isset($errorMsg)) { ?>
                    <div class="errorMessage" style="color: #F00;"><?php echo $errorMsg; ?></div>
                    <?php } ?>

                    <p>Por favor ingrese el código a su derecha:</p>
                	<table width="340" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                    <td width="115" valign="top"><input type="text" name="code" id="code" class="inputBox" style="width: 100px;" /></td>
                    </tr></table>

                </div><!-- END grid_4 -->

                <div class="grid_4 content topPadding">

                	<table width="216" border="0" cellspacing="0" cellpadding="0" style="background-color: #FFF;">
                    	<tr>
                            <td width="187" valign="top"><img id="siimage" align="left" src="/securimage/securimage_show.php?sid=<?php echo md5(time()); ?>" /></td>
                            <td width="29" align="right" valign="top"><object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" width="19" height="19" id="SecurImage_as3" align="middle" >
                              <param name="allowScriptAccess" value="sameDomain" />
                              <param name="allowFullScreen" value="false" />
                              <param name="movie" value="/securimage/securimage_play.swf?audio=securimage_play.php&bgColor1=#ffffff&bgColor2=#ffffff&iconColor=#569c33&roundedCorner=5" />
                              <param name="quality" value="high" />
                              <param name="bgcolor" value="#ffffff" />
                              <embed src="/securimage/securimage_play.swf?audio=/securimage/securimage_play.php&bgColor1=#ffffff&bgColor2=#ffffff&iconColor=#569c33&roundedCorner=5" quality="high" bgcolor="#ffffff" width="19" height="19" name="SecurImage_as3" align="middle" allowscriptaccess="sameDomain" allowfullscreen="false" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
                                </object>

                             <!-- pass a session id to the query string of the script to prevent ie caching -->
                            <a tabindex="-1" style="border-style: none; margin: 0 2px 0 0;" href="#" title="Refresh Image" onClick="document.getElementById('siimage').src = '/securimage/securimage_show.php?sid=' + Math.random(); return false"><img src="/securimage/images/refresh.png" alt="Reload Image" border="0" onClick="this.blur()" align="bottom" /></a></td>
                        </tr>
                    </table>

                </div><!-- END grid_4 content -->

                <div class="grid_4 omega content topPadding">
                	<button type="submit" class="submitButton">Enviar Mensaje</button>
                    <input type="hidden" name="Action" value="send">
                </div><!-- END grid_4 -->
                </form>
                <div class="clear"></div>

            <?php } ?>

            <br /><br /><br /><br />
	</div><!-- END grid_12 omega -->








		<div class="clear"></div>

		<?php include('includes/footer.php');?>

	</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


