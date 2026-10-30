<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1"/>

<title><?php echo $this->getTitle($this->getCurrentBranchId()) ?></title>

<meta name="keywords" content="<?php echo $this->getMetaKeywords() ?>">
<meta name="description" content="<?php echo $this->getMetaDescription() ?>">

<link href="/frontend/css/reset.css" rel="stylesheet" type="text/css">
<link href="/frontend/css/960_16_col.css" rel="stylesheet" type="text/css">
<link href="/frontend/css/main.css" rel="stylesheet" type="text/css">
<link href="/frontend/css/cookiecuttr.css" rel="stylesheet" type="text/css" />
<link href="/frontend/css/colorbox.css" rel="stylesheet" type="text/css" />

<link rel="shortcut icon" href="/frontend/images/favicon.png">
<link rel="apple-touch-icon" href="/frontend/images/apple-touch-icon.png">

<script type="text/javascript" src="//use.typekit.net/svr8adr.js"></script>
<script type="text/javascript">try{Typekit.load();}catch(e){}</script>

<!-- Grab Google CDN's jQuery, with a protocol relative URL; fall back to local if necessary -->
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script>window.jQuery || document.write("<script src='/frontend/js/jquery-1.8.0.min.js'>\x3C/script>")</script>
<script src="/frontend/js/jquery.cookie.js"></script>
<script src="/frontend/js/jquery.cookiecuttr.js"></script>
<script src="/frontend/js/jquery.cycle.all.js"></script>
<script src="/frontend/js/main.js"></script>
<script src="/frontend/js/jquery.tweet.js"></script>
<script src="/frontend/js/jquery.colorbox-min.js"></script>

<script>$(document).ready(function () { $.cookieCuttr({cookieAcceptButtonText: 'Continue'});  });</script>
<!--[if lt IE 9]>
<script src="/frontend/js/html5shiv.js"></script>
<![endif]-->

<script>
	<?php if ($this->getSetting('analyticsUA')) { ?>
	var _gaq=[["_setAccount","<?php echo $this->getSetting('analyticsUA') ?>"],["_trackPageview"]];
	(function(d,t){var g=d.createElement(t),s=d.getElementsByTagName(t)[0];g.async=1;
	g.src=("https:"==location.protocol?"//ssl":"//www")+".google-analytics.com/ga.js";
	s.parentNode.insertBefore(g,s)}(document,"script"));
 	<?php } ?>
	var $buoop = {}
	$buoop.ol = window.onload;
	window.onload=function(){
		try {if ($buoop.ol) $buoop.ol();}catch (e) {}
		var e = document.createElement("script");
		e.setAttribute("type", "text/javascript");
		e.setAttribute("src", "//browser-update.org/update.js");
		document.body.appendChild(e);
	}

</script>


<meta name="google-site-verification" content="3Y_xoWa3qCJ6bwwMr-R6mbct_oaJOszLUaAsD2LMPTk" />
<meta name="msvalidate.01" content="5725FABAAA0DB33E6295FFFA98DCA54E" />
