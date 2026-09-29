/**
 * TPD Tool - Frontend Dashboard Orchestrator
 * Handles tab navigation, search filtering, event registration, and bookmarking.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. Tab Navigation System
		function switchTab(viewId) {
			if (!viewId) return;

			// Handle # prefix if present
			var cleanId = viewId.replace(/^#/, '');

			// Remove active state from nav items
			$('.tpd-sb-nav-list .tpd-nav-item').removeClass('active');

			// Find nav link matching this view
			var $activeNav = $('.tpd-tab-link[data-view="' + cleanId + '"], .tpd-tab-link[href="#tpd-view-' + cleanId + '"], .tpd-tab-link[href="#tpd-supp-' + cleanId + '"]').first();
			$activeNav.closest('.tpd-nav-item').addClass('active');

			// Hide all tab panels
			$('.tpd-tab-panel').removeClass('active');

			// Target panel can be #tpd-view-{cleanId} or #tpd-supp-{cleanId} or #{cleanId}
			var $targetPanel = $('#' + cleanId);
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-view-' + cleanId);
			}
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-supp-' + cleanId);
			}

			if ($targetPanel.length) {
				$targetPanel.addClass('active');
				// Smooth scroll to top of workspace
				window.scrollTo({ top: 0, behavior: 'smooth' });
			}
		}

		// Tab Link Click Handler
		$(document).on('click', '.tpd-tab-link', function(e) {
			var view = $(this).data('view');
			var href = $(this).attr('href');

			if (view) {
				e.preventDefault();
				switchTab(view);
				window.location.hash = view;
			} else if (href && href.indexOf('#') === 0 && href.length > 1) {
				e.preventDefault();
				switchTab(href.replace(/^#/, ''));
				window.location.hash = href;
			}
		});

		// Parse URL query parameter or hash on page load
		var urlParams = new URLSearchParams(window.location.search);
		var tabParam = urlParams.get('tab');
		if (tabParam) {
			switchTab(tabParam);
		} else if (window.location.hash) {
			switchTab(window.location.hash.replace(/^#/, ''));
		}

		// 2. Real-Time Supplier Directory Search
		$('#tpd-live-supplier-search').on('keyup', function() {
			var query = $(this).val().toLowerCase().trim();
			$('.tpd-supplier-profile-card, .tpd-supplier-list-card').each(function() {
				var text = $(this).text().toLowerCase();
				if (text.indexOf(query) !== -1 || query === '') {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});

		$('#tpd-btn-search-suppliers').on('click', function(e) {
			e.preventDefault();
			var query = $('#tpd-live-supplier-search').val().toLowerCase().trim();
			if (query) {
				// Switch to suppliers tab if not already on it
				switchTab('suppliers');
				$('#tpd-live-supplier-search').trigger('keyup');
			}
		});

		// 3. Event & Webinar One-Click Registration
		$(document).on('click', '.tpd-reg-event-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var eventId = $btn.data('event-id');

			if ($btn.hasClass('registered')) {
				return;
			}

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

			$.ajax({
				url: tpd_data.ajax_url,
				type: 'POST',
				data: {
					action: 'tpd_register_event',
					event_id: eventId,
					nonce: tpd_data.nonce
				},
				success: function(res) {
					if (res.success) {
						$btn.removeClass('tpd-btn-outline')
							.addClass('tpd-btn-primary registered')
							.html('<i class="fa-solid fa-circle-check"></i> Registered');
						
						// Show quick toast notification
						showToast(res.data.message || 'Successfully registered for webinar!');
					} else {
						alert(res.data.message || 'Registration failed.');
						$btn.prop('disabled', false).text('Register');
					}
				},
				error: function() {
					alert('Connection error. Please try again.');
					$btn.prop('disabled', false).text('Register');
				}
			});
		});

		// 4. Save / Bookmark Supplier Toggle
		$(document).on('click', '.tpd-toggle-favorite-btn, .tpd-btn-bookmark', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var supplierId = $btn.data('supplier-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: tpd_data.ajax_url,
				type: 'POST',
				data: {
					action: 'tpd_toggle_favorite_supplier',
					supplier_id: supplierId,
					nonce: tpd_data.nonce
				},
				success: function(res) {
					$btn.prop('disabled', false);
					if (res.success) {
						if (res.data.is_saved) {
							$btn.addClass('active saved text-gold').html('<i class="fa-solid fa-bookmark"></i> Saved');
						} else {
							$btn.removeClass('active saved text-gold').html('<i class="fa-regular fa-bookmark"></i> Save');
						}
						showToast(res.data.message);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		// Helper: Simple In-App Toast
		function showToast(message) {
			var $toast = $('<div class="tpd-app-toast">' + message + '</div>');
			$('body').append($toast);
			$toast.css({
				position: 'fixed',
				bottom: '24px',
				right: '24px',
				background: '#0b1526',
				color: '#ffffff',
				padding: '12px 20px',
				borderRadius: '10px',
				boxShadow: '0 10px 25px rgba(0,0,0,0.2)',
				fontSize: '13.5px',
				fontWeight: '600',
				zIndex: 999999,
				display: 'flex',
				alignItems: 'center',
				gap: '8px'
			}).fadeIn(200);

			setTimeout(function() {
				$toast.fadeOut(300, function() { $(this).remove(); });
			}, 3500);
		}
	});

})(jQuery);
