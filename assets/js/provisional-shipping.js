/**
 * Provisional Shipping JavaScript
 */

jQuery(document).ready(function($) {
	
	
	// Test the first shipping method input
	var firstInput = $('input[name="provisional_shipping_method"]').first();
	
	
	// Handle shipping method selection
	$('input[name="provisional_shipping_method"]').on('change', function() {
		var selectedMethod = $(this).val();
		var estimatedCost = parseFloat($(this).data('cost')) || 0;
		var methodTitle = $(this).siblings('.method-title').text();

		
		// Update hidden field for form submission
		$('input[name="provisional_shipping_cost"]').val(estimatedCost);
		
		// Update display
		updateShippingDisplay(methodTitle, estimatedCost);
		
		// Trigger WooCommerce checkout update
		if (typeof wc_checkout_params !== 'undefined') {
			$(document.body).trigger('update_checkout');
		}
	});
	
	// Handle terms acceptance
	$('input[name="shipping_terms_accepted"]').on('change', function() {
		var isAccepted = $(this).is(':checked');
		
		// Enable/disable checkout button based on terms acceptance
		if (typeof wc_checkout_params !== 'undefined') {
			$(document.body).trigger('update_checkout');
		}
	});
	
	// Update shipping display
	function updateShippingDisplay(methodTitle, cost) {
		
		var selectedText = (wc_deposits_ps && wc_deposits_ps.i18n && wc_deposits_ps.i18n.selected) ? wc_deposits_ps.i18n.selected : 'Selected:';
		var estimatedCostText = (wc_deposits_ps && wc_deposits_ps.i18n && wc_deposits_ps.i18n.estimated_cost) ? wc_deposits_ps.i18n.estimated_cost : 'Estimated cost:';
		
		var displayHtml = '<p class="selected-method">' +
			'<strong>' + selectedText + '</strong> ' + methodTitle + '<br>' +
			'<small>' + estimatedCostText + ' ' + formatPrice(cost) + '</small>' +
			'</p>';

		
		$('.provisional-shipping-display').html(displayHtml);
	}
	
	// Format price
	function formatPrice(price) {
		if (typeof wc_price_format !== 'undefined') {
			return wc_price_format.replace('%s', parseFloat(price).toFixed(2));
		}
		return parseFloat(price).toFixed(2);
	}
	
	// Form validation
	$('form.checkout').on('submit', function(e) {
		if ($('.wc-deposits-ps-provisional-shipping').length > 0) {
			// Check if shipping method is selected
			var selectedMethod = $('input[name="provisional_shipping_method"]:checked').val();
			if (!selectedMethod) {
				e.preventDefault();
				var errorMessage = (wc_deposits_ps && wc_deposits_ps.i18n && wc_deposits_ps.i18n.select_shipping_method) ? wc_deposits_ps.i18n.select_shipping_method : 'Please select a shipping method.';
				showError(errorMessage);
				return false;
			}
			
			// Check if terms are accepted
			var termsAccepted = $('input[name="shipping_terms_accepted"]').is(':checked');
			if (!termsAccepted) {
				e.preventDefault();
				var errorMessage = (wc_deposits_ps && wc_deposits_ps.i18n && wc_deposits_ps.i18n.accept_terms) ? wc_deposits_ps.i18n.accept_terms : 'You must accept the provisional shipping terms.';
				showError(errorMessage);
				return false;
			}
		}
	});
	
	// Show error message
	function showError(message) {
		// Remove existing error messages
		$('.woocommerce-error').remove();
		
		// Add new error message
		var errorHtml = '<div class="woocommerce-error" role="alert">' + message + '</div>';
		$('form.checkout').prepend(errorHtml);
		
		// Scroll to error
		$('html, body').animate({
			scrollTop: $('.woocommerce-error').offset().top - 100
		}, 500);
	}
	
	// AJAX shipping calculation (for admin use)
	$('.calculate-final-shipping').on('click', function(e) {
		e.preventDefault();
		
		var orderId = $(this).data('order-id');
		var button = $(this);
		
		// Disable button and show loading
		button.prop('disabled', true).text('Calculating...');
		
		$.ajax({
			url: wc_deposits_ps.ajax_url,
			type: 'POST',
			data: {
				action: 'wc_deposits_ps_calculate_shipping',
				order_id: orderId,
				nonce: wc_deposits_ps.nonce
			},
			success: function(response) {
				if (response.success) {
					// Update display with final cost
					$('.final-shipping-cost').html(response.data.formatted_cost);
					button.text('Recalculate').prop('disabled', false);
				} else {
					alert('Error calculating shipping: ' + response.data);
					button.text('Calculate Final Shipping').prop('disabled', false);
				}
			},
			error: function() {
				alert('Error calculating shipping. Please try again.');
				button.text('Calculate Final Shipping').prop('disabled', false);
			}
		});
	});
	
	// Block checkout compatibility
	if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
		wp.data.subscribe(function() {
			// Re-initialize when block checkout updates
			setTimeout(function() {
				$('input[name="provisional_shipping_method"]').off('change').on('change', function() {
					var selectedMethod = $(this).val();
					var estimatedCost = parseFloat($(this).data('cost')) || 0;
					var methodTitle = $(this).siblings('.method-title').text();
					
					$('input[name="provisional_shipping_cost"]').val(estimatedCost);
					updateShippingDisplay(methodTitle, estimatedCost);
				});
			}, 1000);
		});
	}
	
	// Accessibility improvements
	$('.shipping-option').on('keydown', function(e) {
		if (e.keyCode === 13 || e.keyCode === 32) { // Enter or Space
			e.preventDefault();
			$(this).find('input[type="radio"]').prop('checked', true).trigger('change');
		}
	});
	
	// Focus management
	$('.shipping-option input[type="radio"]').on('focus', function() {
		$(this).closest('.shipping-option').addClass('focused');
	}).on('blur', function() {
		$(this).closest('.shipping-option').removeClass('focused');
	});
	
	// Initialize on page load
	$(document).ready(function() {
		// Set default selection if none is selected
		if ($('input[name="provisional_shipping_method"]:checked').length === 0) {
			$('input[name="provisional_shipping_method"]:first').prop('checked', true).trigger('change');
		}
	});
	
}); 