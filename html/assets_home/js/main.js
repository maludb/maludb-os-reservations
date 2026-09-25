
(function($) {
	'use strict';
	
	jQuery(document).ready(function($){	
	
		/*START MENU JS*/
			$('.main_menu a').bind('click', function (event) {
				var $anchor = $(this);
				$('html').stop().animate({
					scrollTop: $($anchor.attr('href')).offset().top - 0
				}, 1000);
				event.preventDefault();
			});

			$(window).on('scroll', function() {
			  if ($(this).scrollTop() > 100) {
				$('.menu-top').addClass('menu-shrink');
			  } else {
				$('.menu-top').removeClass('menu-shrink');
			  }
			});
			
			$(document).on('click','.navbar-collapse.in',function(e) {
			if( $(e.target).is('a') && $(e.target).attr('class') != 'dropdown-toggle' ) {
				$(this).collapse('hide');
			}
			});				
		/*END MENU JS*/
	
	
		// Owl Carousel for Clients	
			var clientCarousel = $('.client');
			clientCarousel.owlCarousel({
				loop:false,
				autoplay:false,
				dots:false,
				margin:30,
				responsive:{
					0:{
						items:3
					},
					400:{
						items:3
					},
					600:{
						items:4
					},
					992:{
						items:4
					}
				}
			});	
		
  	
		// Start Owl Carousel for screenshot	
		  $("#owl-app-screen").owlCarousel({
		 
			  autoPlay: 3000, //Set AutoPlay to 3 seconds
		 
			  items : 4,
			  itemsDesktop : [1199,3],
			  itemsDesktopSmall : [979,3],
			  navigationText : ["prev","next"],
			  pagination : true
		 
		  });  
		// End Owl Carousel for screenshot
		
  
		// Start Owl Carousel for Testimonials	
			var testiCarousel = $('#review-carousel');
			testiCarousel.owlCarousel({
				loop:false,
				autoplay:false,
				dots:false,
				responsive:{
					0:{
						items:1
					},
					400:{
						items:1
					},
					600:{
						items:1
					},
					992:{
						items:1
					}
				}
			});		
		// End Owl Carousel for Testimonials

		/*---------------------
		 Start statistics-counter
		--------------------- */	
			$('.statistics-counter').counterUp({
				delay: 50,
				time: 3000
			});	
		/*---------------------
		 Start statistics-counter
		--------------------- */
		
        /*=======================================================
          Start  Testimonial Slider
        ======================================================*/
			var owl = $("#owl-tm");
			owl.owlCarousel({
				singleItem: true,
				autoPlay: 5000,
				stopOnHover: true
			});
		 /*=======================================================
          End  Testimonial Slider
        ======================================================*/
	});	
})(jQuery);
