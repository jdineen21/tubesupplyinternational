<!doctype html>
<!--[if lt IE 7 ]> <html class="ie6" lang="es"> <![endif]-->
<!--[if IE 7 ]>    <html class="ie7" lang="es"> <![endif]-->
<!--[if IE 8 ]>    <html class="ie8" lang="es"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html lang="es"> <!--<![endif]-->
<head>
<?php include('includes/htmlhead.php');?>

</head>

<body>

<div class="wrapper">
<div class="container_16">
	<?php include('includes/header.php');?>
	
	<?php include('includes/sidebar.php');?>
	
	<div class="content grid_12">
    
   	<h1><?php echo $this->getTitle(); ?></h1>
    
    <?php echo $this->image($this->content('Page Image'), 'rendered', 'pageImg') ?> <!-- add clas to this page image -->
    
    <?php echo $this->content('Page Content'); ?>




   
	<div class="content grid_12 alpha omega">
    
    	<?php echo $this->content('Intro Text')?>

	</div><!-- end content grid_12 -->
    
    
    
    
    
    <!-- left column text -->
    <div class="grid_6 alpha content">
    	<?php echo $this->content('Left Column Text')?>
    </div><!-- end grid_6 alpha -->
    <!-- end left column text -->
    
    
    
    
    
    <!-- right column text -->
    <div class="grid_6 omega content">
    	<?php echo $this->content('Right Column Text')?>
    </div><!-- end grid_6 omega -->
    <!-- end right column text -->
    
    
    
    
    
    <div class="stockChart"><img src="/frontend/images/page/stock-chart.png" width="700" height="506"/></div><!-- end stockChart -->
    
    
    
    

	</div><!-- end content grid_12 -->
	<div class="clear"></div>

	<?php include('includes/footer.php');?>

</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


