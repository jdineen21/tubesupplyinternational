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
    
    <!-- start MAIN CONENT-->
   	<div class="grid_12 omega">
        
        <div class="content grid_12 alpha omega">
        
        <h1><?php echo $this->getTitle(); ?></h1>
        <?php echo $this->image($this->content('Page Image'), 'rendered', 'pageImg') ?> <!-- add clas to this page image -->
        
        
        
        <div class="grid_8 alpha">
        <?php echo $this->content('Page Intro'); ?>
        </div><!-- /grid_6 alpha omega -->
        
        
        <div class="grid_4 omega advert">
        	<div class="adImage"><a href="/products/proteccion-a-corrosion-e-fin"><img src="/frontend/images/page/e-fin_ad.jpg" width="215" height="186" alt="e fin advert tsi"></a></div><!-- /adImage -->
        </div><!-- /grid_6 alpha omega -->
        
        </div><!-- end content grid_12 -->
        
        <div class="clear"></div>
        
        <div class="grid_12 alpha content columnImageMargin" style="margin-bottom: 5px;"><?php echo $this->content('Full Width Page Text'); ?></div><!-- end grid_12 omega content -->
        
        <div class="clear"></div>
        
        <div class="grid_6 alpha content columnImageMargin" style="padding-top: 30px; margin-top: 0;"><?php echo $this->content('Product Text 1'); ?></div><!-- end grid_6 alpha -->
        <div class="grid_6 omega content columnImageMargin" style="margin-top: 0;"><?php echo $this->image($this->content('Product Image 1'), 'rendered', 'columnImage') ?></div><!-- end grid_6 omega -->
        
        <div class="clear"></div>
        
        <div class="grid_6 alpha content columnImageMargin"><?php echo $this->content('Product Text 2'); ?></div><!-- end grid_6 alpha -->
        <div class="grid_6 omega content columnImageMargin" style="padding-top: 10px;"><?php echo $this->image($this->content('Product Image 2'), 'rendered', 'columnImage') ?></div><!-- end grid_6 omega -->
        
        <div class="clear"></div>
        
        <div class="grid_6 alpha content columnImageMargin" style="padding-top: 9px;"><?php echo $this->content('Product Text 3'); ?></div><!-- end grid_6 alpha -->
        <div class="grid_6 omega content columnImageMargin"><?php echo $this->image($this->content('Product Image 3'), 'rendered', 'columnImage') ?></div><!-- end grid_6 omega -->
        
        <div class="clear"></div>
        
        <div class="grid_6 alpha content columnImageMargin"><?php echo $this->content('Product Text 4'); ?></div><!-- end grid_6 alpha -->
        <div class="grid_6 omega content columnImageMargin" style="padding-top: 12px;"><?php echo $this->image($this->content('Product Image 4'), 'rendered', 'columnImage') ?></div><!-- end grid_6 omega -->
        
        <div class="clear"></div>
        
       	<div class="grid_6 alpha content columnImageMargin" style="padding-top: 25px; margin-top: 0;"><?php echo $this->content('Product Text 5'); ?></div><!-- end grid_6 alpha -->
        <div class="grid_6 omega content columnImageMargin" style="margin-top: 0;"><?php echo $this->image($this->content('Product Image 5'), 'rendered', 'columnImage') ?></div><!-- end grid_6 omega --> 
        
        
	</div>
    <!-- end grid_12 MAIN CONENT-->


	<?php include('includes/footer.php');?>

</div><!-- end .container_16 -->
</div><!-- end .wrapper -->

<?php include('includes/footer-outer.php');?>

</body>
</html>


