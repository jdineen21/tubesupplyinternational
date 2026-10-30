<div class="grid_4 sidebar">
	<h4>Our Products</h4>
    	<?php // not using due to exclusion of one product $this->generateMenuFromBranchType('product', 6) ?>
        <?php $this->generateMenuHTML('Product Menu',6); ?>

        <!-- START MENU BOX -->
        <div class="subProduct"><a href="/products/plain-stock-tubes">
        	<img src="/frontend/images/sidebar/subproduct1.jpg" width="58" height="33" alt="sub product icon" />
            <div class="subProductText" >
            	<h4>Stock Tube</h4>
                <p>View Plain Stock Tubes &gt; </p>
            </div><!-- menuBoxText -->
        </a></div><!-- END menuBox -->
        <!-- END MENU BOX -->



        <!-- If statement for brochure download not to appear in sidebar on home page -->
        <?php if ($this->getCurrentBranchId() != '1') {?>

        <!-- START MENU BOX -->
        <!-- <div class="subProduct"><a href="/downloads/TSI_Brochure.pdf" target="_blank">
            <img src="/frontend/images/sidebar/subproduct2.jpg" width="58" height="33" alt="sub product icon" />
            <div class="subProductText" >
                <h4>Brochure Download</h4>
                <p>Download PDF &gt;</p>
            </div>menuBoxText
        </a></div> -->
        <!-- END MENU BOX -->

        <?php } ?>
        <!-- /If statement for brochure download not to appear in sidebar on home page -->


        <div class="sidebar_paragraph">
            <p>We are fully aware that we must provide products and services which meet the onerous requirements of our clients operating throughout the world. With this in mind all of the materials we supply are from companies with management systems and procedures which are accredited to ISO 9001:2015 and audited to ensure that all of the products are of high quality, fully traceable and in line with the requested material specifications.</p>
            <p>The comprehensive quality assurance accreditations of our production mills ensure that the products supplied are fully in accordance with the highest industry standards as used in the oil refining, petrochemical, fertiliser production and power generation industries.</p>
            <p style="color: #03509d; margin-bottom:8px;">QUALITY STATEMENT</p>
            <p>We are fully committed to ensuring that all of our goods and services are fully in accordance with the highest standards of industry, and with the needs and specifications of our clients.</p>
        </div>
        <!-- /.sidebar_paragraph -->

	<!-- <div class="sidebarLink"><a href="/downloads/TSI_Certificate.pdf" target="_blank">
		<p>View our Certificate <br>of approval &gt;</p></a>
    </div>/sidebarLink -->

</div><!-- end .sidebar -->
