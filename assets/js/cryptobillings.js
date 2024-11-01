
jQuery( function( $ ) {
	'use strict';
	
	$("body").on("click", ".coin-item-click" , function() {
		$(".active-coin").removeClass("active-coin");
		$(this).addClass("active-coin");
	});

});
