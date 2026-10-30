<?php
// if ( $_SERVER["REQUEST_URI"] == '/' )
// {

// 	define("MOBILE_SITE", "http://m.tubesupplyinternational.co.uk");
// 	if ($_GET["switch"] != "full") {
// 		$wa_url = "http://detect.webanywhere.mobi";
// 		$wa_ua = urlencode($_SERVER["HTTP_USER_AGENT"]);
// 		$wa_data = file_get_contents($wa_url . "?ua=" . $wa_ua);
// 		global $wa_params;
// 		$wa_params = explode(",",$wa_data);
// 		function WA_GetValue($param) {
// 			global $wa_params;
// 			foreach ($wa_params as $value) {
// 				$val_arr = explode("=",$value);
// 				if ($val_arr[0] == $param) {
// 					return $val_arr[1];
// 					break;
// 				}
// 			}
// 			unset($value);
// 		}
// 		if (WA_GetValue("redirect") == "1") {
// 			header("Location: " . MOBILE_SITE );
// 		}
// 	}


// }
?>




<header class="grid_16">

	<a href="/"><img src="/frontend/images/header/new-logo.png" width="auto" height="88" alt="tube supply international logo" style="display:inline;"/></a>

    <div class="clear"></div>

    <div class="tagline">

    	<p>Фирмы и поставщики высококачественных<br />
    	<span style="color: #03509d;">теплообменных/котельных труб<br />
		для предприятий обрабатывающей промышленности   </span></p>
    </div>

	<div class="clear"></div>

    <div class="languageMenu">
    	<div class="languageMenuInner">
        	<ul>
            	<li><a href="http://www.tubesupplyinternational.co.uk" class="flagUk"></a></li>
                <li><a href="http://www.tubesupplyinternational.es" class="flagSpain"></a></li>
               	<li><a href="http://www.tubesupplyinternational.pt" class="flagPortugal"></a></li>
                <li><a href="http://www.tubesupplyinternational.fr" class="flagFrance"></a></li>
                <li><a href="http://www.tubesupplyinternational.ru" class="flagRussia current"></a></li>
                <li><a href="http://www.tubesupplyinternational.sa.com" class="flagSaudiArabia"></a></li>
            </ul>
        </div><!-- /languageMenuInner -->
    </div><!-- /languageMenu -->

    <div class="contactDetails">
    	<p><span style="color: #054e9d;">Звоните нам на:</span> <?php echo $this->content('Telephone Number',0,'global'); ?></p>
        <!--<p><span style="color: #f02020;">24hr emergency line:</span> <?php // echo $this->content('Emergency Number',0,'global'); ?></p>-->
    </div><!-- END contactDetails -->

    <div class="clear"></div>

	<nav><?php $this->generateMenuHTML('Main Navigation',0,'mainNav'); ?></nav>

    <div class="clear"></div>

    <div class="contactButton"><a href="/contact-us"></a></div>
    <div class="emergencyButton"><a href="/emergency-delivery"></a></div>

    <div class="clear"></div>

</header>