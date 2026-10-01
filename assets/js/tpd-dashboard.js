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

		// 5. AJAX Registration Form Submission (Travel Advisor & Supplier)
		$('#tpd-advisor-registration-form, #tpd-supplier-registration-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var $status = $form.find('.tpd-reg-status');
			var originalBtnHtml = $btn.html();

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Processing Registration...');
			$status.hide().removeClass('error success');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					if (res.success) {
						$status.addClass('success')
							.html('<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || 'Registration successful! Redirecting...'))
							.fadeIn();
						setTimeout(function() {
							window.location.href = res.data.redirect_url || '/advisor-dashboard/';
						}, 1200);
					} else {
						$btn.prop('disabled', false).html(originalBtnHtml);
						$status.addClass('error')
							.html('<i class="fa-solid fa-circle-exclamation"></i> ' + (res.data.message || 'Registration failed. Please check required fields.'))
							.fadeIn();
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(originalBtnHtml);
					$status.addClass('error')
						.html('<i class="fa-solid fa-triangle-exclamation"></i> Connection error. Please try again.')
						.fadeIn();
				}
			});
		});

		// 6. Unified Media Uploading Helper
		function uploadMediaFile(file, successCallback) {
			if (!file) return;

			var formData = new FormData();
			formData.append('file', file);
			formData.append('action', 'tpd_upload_media_file');
			formData.append('nonce', (typeof tpd_data !== 'undefined' ? tpd_data.nonce : ''));

			showToast('<i class="fa-solid fa-spinner fa-spin"></i> Uploading media file...');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(res) {
					if (res.success) {
						successCallback(res.data.url, res.data);
					} else {
						alert(res.data.message || 'Media upload failed.');
					}
				},
				error: function() {
					alert('Connection error during media upload. Please try again.');
				}
			});
		}

		// Media File Triggers for Advisor & Supplier
		$('#tpd-file-headshot').on('change', function() {
			var file = this.files[0];
			uploadMediaFile(file, function(url) {
				$('#tpd-preview-headshot').attr('src', url);
				$('#tpd_headshot_url').val(url);
				showToast('<i class="fa-solid fa-camera"></i> Profile photo uploaded! Click Save Profile to apply.');
			});
		});

		$('#tpd-file-logo').on('change', function() {
			var file = this.files[0];
			uploadMediaFile(file, function(url) {
				$('#tpd-preview-logo').attr('src', url).show();
				$('#tpd-logo-placeholder').hide();
				$('#tpd_logo_url').val(url);
				showToast('<i class="fa-solid fa-image"></i> Agency logo uploaded! Click Save Profile to apply.');
			});
		});

		$('#tpd-file-banner').on('change', function() {
			var file = this.files[0];
			uploadMediaFile(file, function(url) {
				$('#tpd-preview-banner').attr('src', url);
				$('#tpd_banner_url').val(url);
				showToast('<i class="fa-solid fa-panorama"></i> Custom banner uploaded! Click Save Profile to apply.');
			});
		});

		$('#tpd-file-supp-banner').on('change', function() {
			var file = this.files[0];
			uploadMediaFile(file, function(url) {
				$('#tpd-supp-banner-preview').attr('src', url);
				$('#tpd_supp_banner_url').val(url);
				showToast('<i class="fa-solid fa-panorama"></i> Showcase banner uploaded! Click Save Listing to apply.');
			});
		});

		$('#tpd-file-supp-pdf').on('change', function() {
			var file = this.files[0];
			uploadMediaFile(file, function(url, data) {
				showToast('<i class="fa-solid fa-file-pdf"></i> PDF Brochure uploaded successfully! Added to Advisor Resources.');
			});
		});

		// 7. Travel Advisor Profile Editor AJAX Save
		$('#tpd-advisor-profile-editor-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $('#tpd-save-advisor-profile-btn');
			var $status = $('#tpd-advisor-profile-status');
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving Profile...');
			$status.hide().removeClass('error success');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						$status.addClass('success')
							.html('<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || 'Profile saved successfully!'))
							.fadeIn();
						showToast(res.data.message || 'Advisor profile updated successfully!');
						if (res.data.display_name) {
							$('.tpd-user-name').text(res.data.display_name);
						}
						if (res.data.headshot_url) {
							$('.tpd-user-avatar-sm').attr('src', res.data.headshot_url);
						}
					} else {
						$status.addClass('error')
							.html('<i class="fa-solid fa-circle-exclamation"></i> ' + (res.data.message || 'Save failed.'))
							.fadeIn();
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					$status.addClass('error')
						.html('<i class="fa-solid fa-triangle-exclamation"></i> Connection error. Please try again.')
						.fadeIn();
				}
			});
		});

		// 8. Supplier Listing Showcase Editor AJAX Save
		$('#tpd-supplier-listing-editor-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $('#tpd-save-supplier-listing-btn');
			var $status = $('#tpd-supplier-listing-status');
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving Showcase...');
			$status.hide().removeClass('error success');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						$status.addClass('success')
							.html('<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || 'Showcase saved successfully!'))
							.fadeIn();
						showToast(res.data.message || 'Supplier listing updated successfully!');
					} else {
						$status.addClass('error')
							.html('<i class="fa-solid fa-circle-exclamation"></i> ' + (res.data.message || 'Save failed.'))
							.fadeIn();
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					$status.addClass('error')
						.html('<i class="fa-solid fa-triangle-exclamation"></i> Connection error. Please try again.')
						.fadeIn();
				}
			});
		});

		// 9. TravPro Connect Showcase Editor Sub-Tab Switching
		$(document).on('click', '.tpd-tab-btn[data-target]', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			$('.tpd-tab-btn[data-target]').removeClass('active').css({ 'color': '#64748b', 'border-bottom': 'none', 'font-weight': '600' });
			$(this).addClass('active').css({ 'color': '#0b1526', 'border-bottom': '3px solid #2563eb', 'font-weight': '700' });

			$('.tpd-editor-panel').hide();
			$('#' + target).fadeIn(200);
		});

		// 10. Virtual Office Team Member Management
		$('#tpd-btn-save-rep').on('click', function(e) {
			e.preventDefault();
			var listingId = $('input[name="listing_id"]').val() || 26;
			var name = $('#tpd_rep_name').val().trim();
			var title = $('#tpd_rep_title').val().trim();
			var email = $('#tpd_rep_email').val().trim();
			var phone = $('#tpd_rep_phone').val().trim();

			if (!name || !email) {
				alert('Please enter both team member name and direct email.');
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'tpd_manage_team_member',
					listing_id: listingId,
					member_name: name,
					title: title,
					email: email,
					phone: phone,
					nonce: (typeof tpd_data !== 'undefined' ? tpd_data.nonce : '')
				},
				success: function(res) {
					$btn.prop('disabled', false).text('Save Team Member');
					if (res.success) {
						var initial = name.charAt(0).toUpperCase();
						var newRepHtml = '<div class="tpd-team-card" style="display:flex; align-items:center; gap:12px; padding:12px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:10px;">' +
							'<div style="width:40px; height:40px; border-radius:50%; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">' + initial + '</div>' +
							'<div style="flex:1;">' +
								'<h5 style="margin:0; font-size:13.5px; font-weight:700;">' + name + '</h5>' +
								'<p style="margin:0; font-size:12px; color:#64748b;">' + (title || 'Sales Representative') + ' · ' + email + (phone ? ' · ' + phone : '') + '</p>' +
							'</div>' +
							'<span class="text-green" style="font-size:12px; font-weight:700;"><i class="fa-solid fa-circle"></i> Active</span>' +
						'</div>';
						$('#tpd-team-members-list').append(newRepHtml);
						$('#tpd_rep_name, #tpd_rep_title, #tpd_rep_email, #tpd_rep_phone').val('');
						$('#tpd-add-rep-modal').slideUp();
						showToast('Team member added to Virtual Office!');
					} else {
						alert(res.data.message || 'Failed to add team member.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Save Team Member');
					alert('Connection error. Please try again.');
				}
			});
		});

		// 11. Claim Listing Search & Modal Submission
		$('#tpd-claim-search-input').on('keyup', function() {
			var q = $(this).val().toLowerCase().trim();
			$('#tpd-supp-claim-section tbody tr').each(function() {
				var rowText = $(this).text().toLowerCase();
				if (rowText.indexOf(q) !== -1 || q === '') {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});

		$(document).on('click', '.tpd-btn-claim-modal', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var brandName = $btn.data('title');
			var listingId = $btn.data('id');

			var notes = prompt('Enter your corporate title and email verification notes to claim ownership of "' + brandName + '":', 'Brand Representative requesting official showcase ownership');

			if (notes !== null && notes.trim() !== '') {
				$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Submitting...');

				$.ajax({
					url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
					type: 'POST',
					data: {
						action: 'tpd_submit_claim_listing',
						listing_id: listingId,
						claim_notes: notes,
						nonce: (typeof tpd_data !== 'undefined') ? tpd_data.nonce : ''
					},
					success: function(res) {
						if (res.success) {
							$btn.removeClass('tpd-btn-darkblue').addClass('tpd-btn-outline')
								.html('<i class="fa-solid fa-clock"></i> Claim Pending')
								.prop('disabled', true);
							showToast(res.data.message || 'Claim submitted successfully!');
						} else {
							alert(res.data.message || 'Failed to submit claim.');
							$btn.prop('disabled', false).text('Claim Listing');
						}
					},
					error: function() {
						$btn.prop('disabled', false).text('Claim Listing');
						alert('Connection error. Please try again.');
					}
				});
			}
		});

		// 12. "Same Address as Advisor" Copy Button
		$('#tpd-btn-same-address').on('click', function(e) {
			e.preventDefault();
			var advLoc = $('#tpd_advisor_location_input').val();
			if (advLoc) {
				$('#tpd_agency_address_input').val(advLoc);
				showToast('Copied advisor location to agency address.');
			} else {
				alert('Please enter your Advisor Location first.');
			}
		});

		// 13. Password Show / Hide Toggle
		$(document).on('click', '.tpd-pwd-toggle-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $input = $btn.closest('div').find('input');
			var currentType = $input.attr('type');

			if (currentType === 'password') {
				$input.attr('type', 'text');
				$btn.html('<i class="fa-solid fa-eye-slash"></i>');
			} else {
				$input.attr('type', 'password');
				$btn.html('<i class="fa-solid fa-eye"></i>');
			}
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
