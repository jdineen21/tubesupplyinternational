<!doctype html>
<!--[if lt IE 7 ]> <html class="ie6" lang="en"> <![endif]-->
<!--[if IE 7 ]>    <html class="ie7" lang="en"> <![endif]-->
<!--[if IE 8 ]>    <html class="ie8" lang="en"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html lang="en"> <!--<![endif]-->
<head>
<?php include('includes/htmlhead.php');?>

</head>

<body>

<div class="wrapper">
<div class="container_16">
	<?php include('includes/header.php');?>
	
	<?php include('includes/sidebar.php');?>
	
	<div class="content grid_12">
    
   	<?php echo $this->content('Page Header Text'); ?>
    
    <?php echo $this->image($this->content('Page Image'), 'rendered', 'pageImg') ?> <!-- add clas to this page image -->
    
    <?php echo $this->content('Page Content'); ?>

	</div><!-- end content grid_12 -->
	<div class="clear"></div>

	<?php include('includes/footer.php');?>

</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


