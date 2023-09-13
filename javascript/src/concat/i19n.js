(function ($) {
	
	var i19nTranslate = function() {
		
		var $overlay = false;
		var $btn = false;
		
		function init() {
			$btn = $(".open-i19n-popup");
			$overlay = $(".translate-up");
			
			if( !$btn.length ) {
				return;
			}
			
			action_click();
		}
		
		function action_click() {
			$btn.on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				// open form
				Overlay.open( $overlay );
				
				bind_close();
				
				return false;
			});
		}
		
		function bind_close() {
			$(".close-i19n-popup").on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				action_close();
				
				unbind_close();
				
				return false;
			});
		}
		
		function unbind_close() {
			$(".close-i19n-popup").off('click');
		}
		
		function action_close() {
			Overlay.close( $overlay );
		}
		
		return {
			init: init
		}
	}();
	
	var i19nAddNew = function() {

		var $overlay = false;
		var $btn = false;
		
		function init() {
			$btn = $(".open-new-popup");
			$overlay = $(".addnew-up");
			
			if( !$btn.length ) {
				return;
			}
			
			action_click();
		}

		function action_click() {
			$btn.on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				// open form 
				Overlay.open( $overlay );
				
				bind_close();
				
				return false;
			});
		}
		
		function bind_close() {
			$(".close-new-popup").on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				
				action_close();
				
				unbind_close();
				
				return false;
			});
		}
		
		function unbind_close() {
			$(".close-new-popup").off('click');
		}
		
		function action_close() {
			Overlay.close( $overlay );
		}
		
		return {
			init: init
		}
		
	}();
	
	var Overlay = function() {
		
		function open( $o ) {
			$o.removeClass('hide-this').show();
		}
		
		function close( $o ) {
			$o.addClass('hide-this').hide();
		}
		
		return {
			open: open,
			close: close,
		}
		
	}();
	
	/*
	i19nTranslate.init();
	i19nAddNew.init();
	*/
	
	$(".open-i19n-popup").entwine({
		onmatch: function() {
			i19nTranslate.init();
		}
	});
	
	$(".open-new-popup").entwine({
		onmatch: function() {
			i19nAddNew.init();
		}
	});
	
})(jQuery);
