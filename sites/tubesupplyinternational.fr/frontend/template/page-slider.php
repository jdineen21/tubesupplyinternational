<!doctype html>
<!--[if lt IE 7 ]> <html class="ie6" lang="fr"> <![endif]-->
<!--[if IE 7 ]>    <html class="ie7" lang="fr"> <![endif]-->
<!--[if IE 8 ]>    <html class="ie8" lang="fr"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html lang="fr"> <!--<![endif]-->
<head>
<?php include('includes/htmlhead.php');?>

</head>

<body>

<div class="wrapper">
<div class="container_16">
	<?php include('includes/header.php');?>
	
	<?php include('includes/sidebar.php');?>

    <div class="grid_12 content">
    
   	<?php echo $this->content('Page Header Text'); ?>
    
    
    <!-- slider -->
    <div class="pageSlider">
    
    <?php
		$repeatRegion = $this->repeatRegion('Slider Images');
		while($repeatRow = db_fetch_assoc($repeatRegion)) { ?>
        
        <div class="sliderImage">
        
        <?php echo $this->image($this->content('Slider Image', $repeatRow['rowId'])); ?>
        </div><!-- /sliderImage -->

	<?php }?>

	</div><!-- /pageSlider -->
    <!-- /slider -->
    
    </div><!-- /grid_12 -->
    <div class="content grid_12">
    
    <?php echo $this->content('Page Content'); ?>
   
	

	</div><!-- end content grid_12 -->
	<div class="clear"></div>

	<?php include('includes/footer.php');?>

</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


