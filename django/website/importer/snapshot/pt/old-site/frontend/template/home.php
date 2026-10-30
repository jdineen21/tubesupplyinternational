<!doctype html>
<!--[if lt IE 7 ]> <html class="ie6" lang="pt"> <![endif]-->
<!--[if IE 7 ]>    <html class="ie7" lang="pt"> <![endif]-->
<!--[if IE 8 ]>    <html class="ie8" lang="pt"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html lang="pt"> <!--<![endif]-->
<head>
<?php include('includes/htmlhead.php');?>

</head>

<body>

<div class="wrapper">
<div class="container_16">
	<?php include('includes/header.php');?>

	<?php include('includes/sidebar.php');?>

	<div class="content grid_12">
		<div class="homeFeature">
            <video autoplay width="700" height="360" loop>
              <source src="/frontend/video/home.mp4" type="video/mp4">
              <source src="/frontend/video/home.webm" type="video/webm">
              <img src="/frontend/video/home.jpg" width="700" height="360"/>
            </video>
			<img src="/frontend/images/home/pipes.png" class="homeFeatureImgOverlay" />


		</div><!-- END homeFeature -->

    <?php echo $this->content('Page Header Text'); ?>

    <?php echo $this->content('Home Page Content'); ?>

	</div><!-- end grid_12 -->
	<div class="clear"></div>

	<?php include('includes/footer.php');?>

</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


