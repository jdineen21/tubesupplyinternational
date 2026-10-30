<div class="grid_4 sidebar">
	<h4>НАШИ ПРОДУКТЫ</h4>
    	<?php // not using due to exclusion of one product $this->generateMenuFromBranchType('product', 6) ?>
        <?php $this->generateMenuHTML('Product Menu',6); ?>

        <!-- START MENU BOX -->
        <div class="subProduct"><a href="/products/plain-stock-tubes">
        	<img src="/frontend/images/sidebar/subproduct1.jpg" width="58" height="33" alt="sub product icon" />
            <div class="subProductText" >
            	<h4>Ассортимент труб</h4>
                <p>Смотрите ассортимент обыкновенных труб &gt; </p>
            </div><!-- menuBoxText -->
        </a></div><!-- END menuBox -->
        <!-- END MENU BOX -->




	<div class="sidebarLink"><a href="/downloads/TSI_Certificate.pdf" target="_blank">
		<p>Посмотрите наш <br />сертификат одобрения &gt;</p></a>
    </div><!-- /sidebarLink -->

</div><!-- end .sidebar -->