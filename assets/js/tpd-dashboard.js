/**
 * TPD Tool - Frontend Dashboard & Multi-Step Wizard Orchestrator
 * Handles tab navigation, multi-step registration wizards, profile editors,
 * plan upgrades, password updates, account deletion, and directory interactions.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. Tab Navigation System (Supports Supplier #tpd-supp-*, Advisor #tpd-view-*, and direct panel IDs)
		function switchTab(viewId, subTabId) {
			if (!viewId) return;

			var cleanId = String(viewId).replace(/^#/, '');
			var strippedId = cleanId.replace(/^(tpd-view-|tpd-supp-|tpd-|supp-|view-)/, '');

			// Resolve target panel across all naming conventions
			var $targetPanel = $('#' + cleanId);
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-' + cleanId);
			}
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-supp-' + strippedId);
			}
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-view-' + strippedId);
			}
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-supp-' + cleanId);
			}
			if (!$targetPanel.length) {
				$targetPanel = $('#tpd-view-' + cleanId);
			}

			// Safety guard: never hide the active panel if the requested panel does not exist on this portal
			if (!$targetPanel.length) {
				return;
			}

			var resolvedPanelId = $targetPanel.attr('id');

			// Remove active state from sidebar nav items
			$('.tpd-sb-nav-list .tpd-nav-item').removeClass('active');

			// Find nav link matching this view or panel ID
			var $activeNav = $(
				'.tpd-sb-nav-list .tpd-tab-link[data-view="' + cleanId + '"], ' +
				'.tpd-sb-nav-list .tpd-tab-link[data-view="supp-' + strippedId + '"], ' +
				'.tpd-sb-nav-list .tpd-tab-link[data-view="' + strippedId + '"], ' +
				'.tpd-sb-nav-list .tpd-tab-link[href="#' + resolvedPanelId + '"], ' +
				'.tpd-sb-nav-list .tpd-tab-link[href="#' + cleanId + '"]'
			).first();
			$activeNav.closest('.tpd-nav-item').addClass('active');

			// Hide all tab panels and reveal the target panel
			$('.tpd-tab-panel').removeClass('active').css('display', 'none');
			$targetPanel.addClass('active').css('display', 'block');

			// Optional: activate inner sub-tab (e.g. set-basic, set-media, set-resources, set-team, set-promos)
			if (subTabId) {
				var $subBtn = $('.tpd-tab-btn[data-target="' + subTabId + '"]');
				if ($subBtn.length) {
					$subBtn.trigger('click');
				}
			}

			window.scrollTo({ top: 0, behavior: 'smooth' });
		}

		// Expose globally for inline fallbacks
		window.tpdSwitchTab = switchTab;

		// Tab Link Click Handler
		$(document).on('click', '.tpd-tab-link', function(e) {
			var view = $(this).data('view');
			var href = $(this).attr('href');
			var subtab = $(this).data('subtab') || '';

			if (view) {
				e.preventDefault();
				switchTab(view, subtab);
				if (history.replaceState) {
					history.replaceState(null, null, '#' + view);
				} else {
					window.location.hash = view;
				}
			} else if (href && href.indexOf('#') === 0 && href.length > 1) {
				e.preventDefault();
				var targetHash = href.replace(/^#/, '');
				switchTab(targetHash, subtab);
				if (history.replaceState) {
					history.replaceState(null, null, '#' + targetHash);
				} else {
					window.location.hash = href;
				}
			}
		});

		// Parse URL query parameter or hash on page load
		var urlParams = new URLSearchParams(window.location.search);
		var tabParam = urlParams.get('tab');
		if (tabParam) {
			switchTab(tabParam);
		} else if (window.location.hash && window.location.hash.length > 1) {
			switchTab(window.location.hash.replace(/^#/, ''));
		}


		// 2. Multi-Step Registration Wizard Navigation & Validation
		function goToWizardStep($form, nextStep) {
			$form.find('.tpd-wizard-pane, .tpd-step-pane').removeClass('active');
			$form.find('.tpd-wizard-pane[data-step="' + nextStep + '"], .tpd-step-pane[data-step-pane="' + nextStep + '"]').addClass('active');

			$('.tpd-w-step').each(function() {
				var stepNum = parseInt($(this).data('step'), 10);
				$(this).removeClass('active done completed');
				if (stepNum < nextStep) {
					$(this).addClass('done completed');
				} else if (stepNum === nextStep) {
					$(this).addClass('active');
				}
			});

			var $card = $form.closest('.tpd-reg-card, .tpd-split-card');
			if ($card.length) {
				window.scrollTo({ top: Math.max(0, $card.offset().top - 30), behavior: 'smooth' });
			}
		}

		$(document).on('click', '.tpd-wizard-next-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $btn.closest('form');
			var $currentPane = $btn.closest('.tpd-wizard-pane, .tpd-step-pane');
			var nextStep = parseInt($btn.data('next'), 10);

			// Validate required fields inside current pane
			var isValid = true;
			var firstInvalid = null;

			$currentPane.find('input[required], select[required], textarea[required]').each(function() {
				var val = $(this).val();
				if (!val || $.trim(val) === '') {
					isValid = false;
					$(this).css({ 'border-color': '#ef4444', 'border-bottom-color': '#ef4444' });
					if (!firstInvalid) firstInvalid = $(this);
				} else {
					$(this).css({ 'border-color': '#cbd5e1', 'border-bottom-color': '#334155' });
				}
			});

			if (!isValid) {
				showToast('<i class="fa-solid fa-circle-exclamation"></i> Please complete all required fields (*) in this step.');
				if (firstInvalid) firstInvalid.focus();
				return;
			}

			// Step password match check if password fields are inside current pane
			var $pwd = $currentPane.find('input[name="password"]');
			var $confirmPwd = $currentPane.find('input[name="confirm_password"]');
			if ($pwd.length && $confirmPwd.length) {
				if ($pwd.val().length < 6) {
					$pwd.css({ 'border-color': '#ef4444', 'border-bottom-color': '#ef4444' }).focus();
					showToast('<i class="fa-solid fa-lock"></i> Password must be at least 6 characters.');
					return;
				}
				if ($pwd.val() !== $confirmPwd.val()) {
					$confirmPwd.css({ 'border-color': '#ef4444', 'border-bottom-color': '#ef4444' }).focus();
					showToast('<i class="fa-solid fa-triangle-exclamation"></i> Passwords do not match. Please verify Confirm Password.');
					return;
				}
			}

			goToWizardStep($form, nextStep);
		});

		$(document).on('click', '.tpd-wizard-prev-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $btn.closest('form');
			var prevStep = parseInt($btn.data('prev'), 10);
			goToWizardStep($form, prevStep);
		});

		// Plan Card Radio Selection & Dynamic Payment Checkout Box
		$(document).on('change', '.tpd-plan-radio', function() {
			var $radio = $(this);
			var $form = $radio.closest('form');
			$form.find('.tpd-plan-card-option').removeClass('selected');
			$radio.closest('.tpd-plan-card-option').addClass('selected');

			var monthlyPrice = parseFloat($radio.data('monthly') || 0);
			var $checkoutBox = $form.find('#tpd-reg-payment-checkout');
			if ($checkoutBox.length) {
				if (monthlyPrice > 0) {
					$checkoutBox.slideDown(200);
				} else {
					$checkoutBox.slideUp(200);
				}
			}
		});

		// Account Info Horizontal Sub-Tab Filtering (Personal Info, About Me, Certification, Specialties, etc.)
		$(document).on('click', '.tpd-acct-nav-item[data-sec]', function(e) {
			e.preventDefault();
			var sec = $(this).data('sec');
			$('.tpd-acct-nav-item').removeClass('active').css({ 'color': '#475569', 'border-bottom': 'none', 'font-weight': '600' });
			$(this).addClass('active').css({ 'color': '#00798c', 'border-bottom': '2.5px solid #00798c', 'font-weight': '700' });

			if (sec === 'all') {
				$('.tpd-acct-section').show();
			} else {
				$('.tpd-acct-section').hide();
				$('.tpd-acct-section[data-sec-pane="' + sec + '"]').fadeIn(200);
			}
		});

		// Live Update Advisor Profile URL when Profile Handle changes
		$(document).on('input', '#tpd-profile-handle-input', function() {
			var slug = $(this).val().toLowerCase().replace(/[^a-z0-9-_]/g, '');
			var base = (typeof tpd_data !== 'undefined' && tpd_data.site_url) ? tpd_data.site_url : window.location.origin;
			$('#tpd-advisor-url-preview').val(base.replace(/\/$/, '') + '/travel-advisors/' + (slug || 'advisor') + '/');
		});

		// Preset Scenic Banner Image Radio Selection
		$(document).on('change', '.tpd-preset-banner-radio', function() {
			var url = $(this).val();
			if (url) {
				$('#tpd-preview-banner').attr('src', url);
				$('#tpd_banner_url').val(url);
				$('.tpd-preset-banner-tile').css('border-color', 'transparent');
				$(this).closest('.tpd-preset-banner-tile').css('border-color', '#00798c');
				showToast('<i class="fa-regular fa-image"></i> Preset banner selected! Click Submit to save.');
			}
		});

		// 3. Real-Time Supplier Directory Search
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
				switchTab('suppliers');
				$('#tpd-live-supplier-search').trigger('keyup');
			}
		});

		// 4. Event & Webinar One-Click Registration
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

		// 5. Save / Bookmark Supplier Toggle
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

		// 6. AJAX Registration Form Submission (Travel Advisor & Supplier)
		$('#tpd-advisor-registration-form, #tpd-supplier-registration-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var $status = $form.find('.tpd-reg-status');
			var originalBtnHtml = $btn.html();

			// Verify password match before submitting (when new password fields are visible)
			var isCrossRole = $form.find('input[name="confirm_cross_role"]').val() === '1';
			var credMode = $form.find('input[name="credential_mode"]:checked').val() || 'same';
			var pwd = $form.find('input[name="password"]').val() || '';
			var confirmPwd = $form.find('input[name="confirm_password"]').val() || '';

			if (!isCrossRole || credMode === 'separate') {
				if (pwd !== confirmPwd) {
					$status.removeClass('success').addClass('error')
						.html('<i class="fa-solid fa-circle-exclamation"></i> Passwords do not match.')
						.fadeIn();
					return;
				}
			}

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
						}, 1100);
					} else {
						$btn.prop('disabled', false).html(originalBtnHtml);
						if (res.data && res.data.cross_role_prompt) {
							$form.find('input[name="confirm_cross_role"]').val('1');
							$('#tpd-adv-cross-role-banner, #tpd-supp-cross-role-banner').slideDown(200);
							$('#tpd-adv-dual-cred-box, #tpd-supp-dual-cred-box').slideDown(200);
						}
						$status.addClass('error')
							.html('<i class="fa-solid fa-circle-exclamation"></i> ' + ((res.data && res.data.message) ? res.data.message : 'Registration failed. Please check required fields.'))
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

		// 7. Unified Media Uploading Helper
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
			uploadMediaFile(file, function(url) {
				showToast('<i class="fa-solid fa-file-pdf"></i> PDF Brochure uploaded successfully! Added to Advisor Resources.');
			});
		});

		// 8. Travel Advisor Profile Editor AJAX Save
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
						showToast(res.data.message || 'Advisor profile updated & synced with WP Custom Post Type!');
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

		// 9. Supplier Representative Account Profile Save
		$('#tpd-supplier-rep-profile-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						showToast(res.data.message || 'Supplier profile updated & synced with CPT!');
					} else {
						alert(res.data.message || 'Update failed.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Connection error. Please try again.');
				}
			});
		});

		// 10. Supplier Listing Showcase Editor AJAX Save
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
						showToast(res.data.message || 'Supplier listing updated & synced with WP CPT!');
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

		// 11. User Self-Service Plan Upgrade / Change
		$('#tpd-user-upgrade-plan-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Updating Plan...');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						showToast(res.data.message || 'Membership plan updated!');
						setTimeout(function() {
							window.location.reload();
						}, 900);
					} else {
						alert(res.data.message || 'Failed to update plan.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Connection error. Please try again.');
				}
			});
		});

		// 12. User Password Update Form
		$('#tpd-user-password-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Updating...');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res.success) {
						$form[0].reset();
						showToast(res.data.message || 'Password updated successfully!');
					} else {
						alert(res.data.message || 'Failed to update password.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Connection error. Please try again.');
				}
			});
		});

		// 13. User Self-Service Account Deletion
		$(document).on('click', '#tpd-btn-delete-own-account', function(e) {
			e.preventDefault();
			var confirmText = prompt('WARNING: This will permanently delete your account and remove your directory listing. Type DELETE to confirm:');
			if (confirmText !== 'DELETE') {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Deleting Account...');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'tpd_user_delete_own_account',
					confirm_text: confirmText,
					nonce: (typeof tpd_data !== 'undefined' ? tpd_data.nonce : '')
				},
				success: function(res) {
					if (res.success) {
						showToast(res.data.message || 'Account deleted.');
						window.location.href = res.data.redirect_url || '/';
					} else {
						$btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i> Delete My Account Permanently');
						alert(res.data.message || 'Could not delete account.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-trash-can"></i> Delete My Account Permanently');
					alert('Connection error.');
				}
			});
		});

		// 14. TravPro Connect Showcase Editor Sub-Tab Switching
		$(document).on('click', '.tpd-tab-btn[data-target]', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			$('.tpd-tab-btn[data-target]').removeClass('active').css({ 'color': '#64748b', 'border-bottom': 'none', 'font-weight': '600' });
			$(this).addClass('active').css({ 'color': '#0b1526', 'border-bottom': '3px solid #2563eb', 'font-weight': '700' });

			$('.tpd-editor-panel').hide();
			$('#' + target).fadeIn(200);
		});

		// 15. Virtual Office Team Member Management
		$('#tpd-btn-save-rep').on('click', function(e) {
			e.preventDefault();
			var listingId = $('input[name="listing_id"]').val();
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

		// 16. Claim Listing Search & Modal Submission
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

		// 17. "Same Address as Advisor" Copy Button
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

		// 18. Password Show / Hide Toggle
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

		// 19. Supplier Meeting Request Actions (Confirm Call / Reschedule)
		$(document).on('click', '.tpd-btn-confirm-meeting', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $row = $btn.closest('tr');
			var advisorName = $btn.data('advisor') || 'Advisor';

			$btn.prop('disabled', true).html('<i class="fa-solid fa-check"></i> Confirmed');
			$row.find('.tpd-meeting-status-badge')
				.css({ background: '#dcfce7', color: '#166534' })
				.html('<i class="fa-solid fa-circle-check"></i> Confirmed');

			showToast('<i class="fa-solid fa-calendar-check"></i> 1-on-1 Discovery Call confirmed with ' + advisorName + '! Calendar invite sent.');
		});

		$(document).on('click', '.tpd-btn-save-office-hours', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var orig = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');
			setTimeout(function() {
				$btn.prop('disabled', false).html(orig);
				showToast('<i class="fa-solid fa-clock"></i> Virtual Office Hours & Calendar Link saved!');
			}, 400);
		});

		// 20. TARC Partnership Package Inquiry
		$(document).on('click', '.tpd-btn-request-partnership', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var pkg = $btn.data('package') || 'TARC Partnership';

			$btn.prop('disabled', true)
				.removeClass('tpd-btn-darkblue tpd-btn-primary')
				.addClass('tpd-btn-outline')
				.html('<i class="fa-solid fa-circle-check text-green"></i> Inquiry Sent to TARC');

			showToast('<i class="fa-solid fa-handshake"></i> Your inquiry for "' + pkg + '" has been sent to the TARC Partnerships team!');
		});

		// 21. Supplier Support Ticket Submission
		$(document).on('submit', '#tpd-supplier-support-ticket-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var orig = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Sending...');
			setTimeout(function() {
				$btn.prop('disabled', false).html(orig);
				$form[0].reset();
				showToast('<i class="fa-solid fa-paper-plane"></i> Support request submitted! A TARC Partner Specialist will reply shortly.');
			}, 500);
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

