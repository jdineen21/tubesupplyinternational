$(document).ready(function() {
	
	
	var chartState = 'swg';
	
	$('.tabs').click(function(){
		
		if (chartState == 'swg') {
			
			$('.tabs').addClass('swap');
			$('#bwg').show();
			$('#swg').hide();
			chartState = 'bwg';
			
		} else {
			
			$('.tabs').removeClass('swap');
			$('#swg').show();
			$('#bwg').hide();
			chartState = 'swg';
			
		}
		
	});
	
	
		/***************************************************
							Twitter
		***************************************************/
	 	  /*---- Footer Twitter ----*/
	  $(".tweet").tweet({
        	count: 2,
        	username: "climbcreative",
        	loading_text: "loading twitter..."
			  
		});
		
		$(".popup").colorbox({opacity: 0.5});



/*-----------------------------------------------------------------------------------*/
/*	J-Cycle
/*-----------------------------------------------------------------------------------*/
	
		
		$('.pageSlider').cycle({
		timeout: 5000,
		random: false,
		speed: 1500,
		pager: '.pagination'
	})








});
