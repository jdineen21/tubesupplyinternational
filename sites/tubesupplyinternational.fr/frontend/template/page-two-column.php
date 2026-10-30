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
    
    <div class="content grid_12">
    	<div class="grid_6 alpha content">
        	<h1><?php echo $this->getTitle(); ?></h1>
        	<?php echo $this->content('Page Content Left'); ?>
        </div><!-- end grid_6 alpha content -->
        
        <div class="grid_6 omega content">
			<?php echo $this->content('Page Content Right'); ?>        
        </div><!-- end grid_6 omega content -->
  
	</div><!-- end content grid_12 -->
    
    
    
	<div class="clear"></div>

	<?php include('includes/footer.php');?>

</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


