jQuery(document).ready(function($) {
	"use strict";

	// Handle preloader
	function removePreloader() {
		$("#preloader").fadeOut(300, function() {
			$(this).remove();
		});
	}

	// Remove preloader on document ready
	removePreloader();

	// Backup removal on window load
	$(window).on('load', removePreloader);

	// Final fallback
	setTimeout(removePreloader, 1000);

	$(window).scroll(function() {
		var scroll = $(window).scrollTop();
		var box = $('.header-text').height();
		var header = $('header').height();

		if (scroll >= box - header) {
			$("header").addClass("background-header");
		} else {
			$("header").removeClass("background-header");
		}
	});

	if ($('.owl-clients').length) {
		$('.owl-clients').owlCarousel({
			loop: true,
			nav: false,
			dots: true,
			items: 1,
			margin: 30,
			autoplay: false,
			smartSpeed: 700,
			autoplayTimeout: 6000,
			responsive: {
				0: {
					items: 1,
					margin: 0
				},
				460: {
					items: 1,
					margin: 0
				},
				576: {
					items: 3,
					margin: 20
				},
				992: {
					items: 5,
					margin: 30
				}
			}
		});
	}

	if ($('.owl-banner').length) {
		$('.owl-banner').owlCarousel({
			loop: true,
			nav: true,
			dots: true,
			items: 3,
			margin: 10,
			autoplay: false,
			smartSpeed: 700,
			autoplayTimeout: 6000,
			responsive: {
				0: {
					items: 1,
					margin: 0
				},
				460: {
					items: 1,
					margin: 0
				},
				576: {
					items: 1,
					margin: 10
				},
				992: {
					items: 3,
					margin: 10
				}
			}
		});
	}
});