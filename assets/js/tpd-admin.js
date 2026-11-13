/**
 * TPD Tool - Super Admin Control Hub Controller
 * Synchronized with templates/admin/super-admin-dashboard.php and includes/class-tpd-settings.php
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		var ajaxUrl = (typeof tpd_data !== 'undefined' && tpd_data.ajax_url) ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php';
		var adminNonce = (typeof tpd_data !== 'undefined' && tpd_data.admin_nonce) ? tpd_data.admin_nonce : $('#tpd_sa_admin_nonce').val() || '';

		// Helper to switch Super Admin panes reliably
		window.tpdSwitchAdminPane = function(paneId) {
			if (!paneId) return;
			paneId = String(paneId).replace(/^#/, '');

			$('.tpd-sa-nav li').removeClass('active');
			$('.tpd-sa-nav-link').removeClass('active');
			var $link = $('.tpd-sa-nav-link[data-pane="' + paneId + '"]');
			$link.addClass('active');
			$link.closest('li').addClass('active');

			$('.tpd-sa-pane').removeClass('active').hide();
			var $target = $('#' + paneId);
			if (!$target.length) {
				$target = $('#tpd-sa-pane-' + paneId);
			}
			$target.addClass('active').show();
		};

		// 1. Super Admin Sidebar Navigation
		$(document).on('click', '.tpd-sa-nav-link[data-pane], .tpd-sa-jump-pane[data-pane]', function(e) {
			e.preventDefault();
			var paneId = $(this).attr('data-pane');
			window.tpdSwitchAdminPane(paneId);
		});

		// Handle URL hash on initial load
		if (window.location.hash && $(window.location.hash).hasClass('tpd-sa-pane')) {
			window.tpdSwitchAdminPane(window.location.hash.substring(1));
		}

		// 2. Live Table Search for Advisors & Suppliers
		$(document).on('keyup input', '#tpd-sa-search-advisors', function() {
			var q = $(this).val().toLowerCase().trim();
			$('#tpd-sa-advisors-table tbody tr').each(function() {
				var txt = $(this).text().toLowerCase();
				$(this).toggle(txt.indexOf(q) !== -1 || q === '');
			});
		});

		$(document).on('keyup input', '#tpd-sa-search-suppliers', function() {
			var q = $(this).val().toLowerCase().trim();
			$('#tpd-sa-suppliers-table tbody tr').each(function() {
				var txt = $(this).text().toLowerCase();
				$(this).toggle(txt.indexOf(q) !== -1 || q === '');
			});
		});

		// 3. Instant Plan Promote / Demote Switcher
		$(document).on('change', '.tpd-tier-select', function() {
			var $select = $(this);
			var userId = $select.data('user-id');
			var targetTier = $select.val();

			$select.prop('disabled', true);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_update_user_tier',
					user_id: userId,
					tier: targetTier,
					nonce: adminNonce
				},
				success: function(res) {
					$select.prop('disabled', false);
					if (res && res.success) {
						showAdminToast('User plan updated to ' + res.data.label);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to update tier.');
					}
				},
				error: function() {
					$select.prop('disabled', false);
					alert('Connection error. Please try again.');
				}
			});
		});

		// 4. Toggle User Active / Inactive Status
		$(document).on('click', '.tpd-toggle-user-status', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var userId = $btn.data('user-id');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_toggle_user_status',
					user_id: userId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						$btn.removeClass('active inactive tpd-badge-active tpd-badge-suspended')
							.addClass(res.data.status)
							.text(res.data.label || (res.data.status === 'active' ? 'Active' : 'Inactive'));
						showAdminToast('Account status changed to ' + (res.data.label || res.data.status));
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to toggle status.');
					}
				}
			});
		});

		// 5. View User Details Modal (Eye Icon)
		$(document).on('click', '.tpd-btn-view-user', function(e) {
			e.preventDefault();
			var userId = $(this).data('user-id');
			$('#tpd-view-modal-body').html('<p style="text-align:center; padding:24px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading user profile...</p>');
			$('#tpd-sa-view-user-modal').addClass('open').css('display', 'flex');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_get_user',
					user_id: userId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						var d = res.data;
						var isSupplier = (d.role === 'supplier');
						var html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13.5px;">' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Full Name:</strong><br>' + (d.display_name || (d.first_name + ' ' + d.last_name)) + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Username:</strong><br>@' + (d.username || '—') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Email Address:</strong><br>' + (d.email || '—') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Phone Number:</strong><br>' + (d.phone || '—') + '</div>';

						if (isSupplier) {
							html += '<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Supplier / Company Name:</strong><br>' + (d.company_name || '—') + '</div>' +
								'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Position / Title:</strong><br>' + (d.position_title || '—') + '</div>' +
								'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Country:</strong><br>' + (d.country || '—') + '</div>';
						} else {
							html += '<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Agency Name:</strong><br>' + (d.agency_name || '—') + '</div>' +
								'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Location:</strong><br>' + (d.location || '—') + '</div>' +
								'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Consortia / Host Agency:</strong><br>' + ((d.consortia || '—') + ' / ' + (d.host_agency || '—')) + '</div>' +
								'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Certifications (CLIA / IATA):</strong><br>' + ((d.clia_num || '—') + ' / ' + (d.iata_num || '—')) + '</div>';
						}

						html += '<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Membership Plan:</strong><br><span style="text-transform:uppercase; font-weight:800; color:#164e87;">' + (d.tier || 'basic') + '</span> (' + (d.status || 'active') + ')</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0;"><strong>Registered Date:</strong><br>' + (d.registered || '—') + '</div>' +
						'</div>';

						if (d.post_id) {
							html += '<div style="margin-top:16px; padding-top:12px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">' +
								'<span style="font-size:12.5px; color:#64748b;">Paired Directory CPT Post ID: <strong>#' + d.post_id + '</strong></span>' +
								'<a href="/wp-admin/post.php?post=' + d.post_id + '&action=edit" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-darkblue" style="background:#164e87; color:#fff; text-decoration:none;"><i class="fa-solid fa-arrow-up-right-from-square"></i> Edit Full Post in WP Admin</a>' +
							'</div>';
						}
						$('#tpd-view-modal-body').html(html);
					} else {
						$('#tpd-view-modal-body').html('<p style="color:#dc2626;">Failed to load user details.</p>');
					}
				}
			});
		});

		// 6. Add / Edit User Modal (Pencil Icon & + Add Advisor / Supplier Buttons)
		$(document).on('click', '#tpd-btn-open-add-advisor', function(e) {
			e.preventDefault();
			var form = $('#tpd-sa-save-user-form')[0];
			if (form) form.reset();
			$('#eu_user_id').val(0);
			$('#eu_role').val('travel_advisor');
			$('#eu-advisor-fields').show();
			$('#eu-supplier-fields').hide();
			$('#tpd-edit-modal-title').text('Add New Travel Advisor (Pro User)');
			$('#tpd-sa-edit-user-modal').addClass('open').css('display', 'flex');
		});

		$(document).on('click', '#tpd-btn-open-add-supplier', function(e) {
			e.preventDefault();
			var form = $('#tpd-sa-save-user-form')[0];
			if (form) form.reset();
			$('#eu_user_id').val(0);
			$('#eu_role').val('supplier');
			$('#eu-advisor-fields').hide();
			$('#eu-supplier-fields').show();
			$('#tpd-edit-modal-title').text('Add New Supplier User');
			$('#tpd-sa-edit-user-modal').addClass('open').css('display', 'flex');
		});

		$(document).on('click', '.tpd-btn-edit-user', function(e) {
			e.preventDefault();
			var userId = $(this).data('user-id');
			var form = $('#tpd-sa-save-user-form')[0];
			if (form) form.reset();
			$('#tpd-edit-modal-title').text('Edit User Account #' + userId);
			$('#tpd-sa-edit-user-modal').addClass('open').css('display', 'flex');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_get_user',
					user_id: userId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						var d = res.data;
						$('#eu_user_id').val(d.user_id);
						$('#eu_role').val(d.role);
						$('#eu_first_name').val(d.first_name);
						$('#eu_last_name').val(d.last_name);
						$('#eu_email').val(d.email);
						$('#eu_username').val(d.username);
						$('#eu_phone').val(d.phone);
						$('#eu_tier').val(d.tier);
						$('#eu_status').val(d.status);
						$('#eu_agency_name').val(d.agency_name);
						$('#eu_location').val(d.location);
						$('#eu_company_name').val(d.company_name);
						$('#eu_position_title').val(d.position_title);
						$('#eu_country').val(d.country);

						if (d.role === 'supplier') {
							$('#eu-advisor-fields').hide();
							$('#eu-supplier-fields').show();
						} else {
							$('#eu-advisor-fields').show();
							$('#eu-supplier-fields').hide();
						}
					}
				}
			});
		});

		$(document).on('submit', '#tpd-sa-save-user-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving...');

			var payload = $form.serialize() + '&action=tpd_admin_save_user&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save User Account');
					if (res && res.success) {
						$('#tpd-sa-edit-user-modal').removeClass('open').fadeOut(150);
						showAdminToast(res.data.message);
						setTimeout(function() {
							window.location.reload();
						}, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to save user.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save User Account');
					alert('Connection error.');
				}
			});
		});

		// 7. Delete User & Paired Custom Post Type (Trash Icon)
		$(document).on('click', '.tpd-btn-delete-user', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var userId = $btn.data('user-id');
			var $row = $('#sa-user-row-' + userId);
			var userName = $row.find('td:nth-child(2)').text().trim() || ('User #' + userId);

			if (!confirm('Are you sure you want to permanently delete "' + userName + '" and their paired directory profile? This action cannot be undone.')) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_user',
					user_id: userId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						$row.fadeOut(250, function() { $(this).remove(); });
						showAdminToast(res.data.message);
					} else {
						$btn.prop('disabled', false);
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to delete user.');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert('Connection error.');
				}
			});
		});

		// 8. Membership Plan Builder (Save / Edit / Delete Plan)
		$(document).on('click', '.tpd-edit-plan-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			$('#pb_plan_slug').val($btn.data('slug') || '');
			$('#pb_plan_name').val($btn.data('name') || '');
			$('#pb_plan_badge').val($btn.data('badge') || '');
			$('#pb_monthly_price').val($btn.data('monthly') !== undefined ? $btn.data('monthly') : 0);
			$('#pb_yearly_price').val($btn.data('yearly') !== undefined ? $btn.data('yearly') : 0);
			$('#pb_plan_role').val($btn.data('role') || 'all');
			$('#pb_max_specialties').val($btn.data('specialties') || 5);
			$('#pb_max_pdfs').val($btn.data('pdfs') || 5);
			$('#pb_features_list').val($btn.data('features') || '');
			showAdminToast('Loaded "' + ($btn.data('name') || 'Plan') + '" into editor below.');
			$('html, body').animate({
				scrollTop: $('#tpd-sa-plan-builder-form').offset().top - 80
			}, 300);
		});

		$(document).on('submit', '#tpd-sa-plan-builder-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving Plan...');

			var payload = $form.serialize() + '&action=tpd_admin_save_plan&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Membership Plan');
					if (res && res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to save plan.');
					}
				}
			});
		});

		$(document).on('click', '.tpd-delete-plan-btn', function(e) {
			e.preventDefault();
			var slug = $(this).data('slug');
			if (!confirm('Delete membership plan "' + slug + '"?')) return;

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_plan',
					plan_slug: slug,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Cannot delete this plan.');
					}
				}
			});
		});

		// 9. Payment Gateways Configuration Save
		$(document).on('submit', '#tpd-sa-payment-settings-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving Gateways...');

			var payload = $form.serialize() + '&action=tpd_admin_save_payment_settings&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Payment Gateway Configuration');
					if (res && res.success) {
						showAdminToast(res.data.message);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to save payment settings.');
					}
				}
			});
		});

		// 10. Registration Wizard & Dropdown Configurator Save
		$(document).on('submit', '#tpd-sa-form-config-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving...');

			var payload = $form.serialize() + '&action=tpd_admin_save_form_config&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Form Settings & Dropdown Options');
					if (res && res.success) {
						showAdminToast(res.data.message);
					}
				}
			});
		});

		// 11. Dynamic Custom Fields Builder (Add & Delete)
		$(document).on('submit', '#tpd-sa-custom-field-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Adding Field...');

			var payload = $form.serialize() + '&action=tpd_admin_save_custom_field&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-plus"></i> Add Custom Field');
					if (res && res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to add custom field.');
					}
				}
			});
		});

		$(document).on('click', '.tpd-delete-cf-btn', function(e) {
			e.preventDefault();
			var key = $(this).data('key');
			if (!confirm('Remove custom field "' + key + '"?')) return;

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_custom_field',
					field_key: key,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 600);
					}
				}
			});
		});

		// 12. Featured Supplier Toggle & Pending Brand Claim Approval
		$(document).on('click', '.tpd-toggle-featured-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var postId = $btn.data('post-id') || $btn.data('id');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_toggle_featured_listing',
					post_id: postId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						if (res.data.featured === 'yes') {
							$btn.css({ background: '#fef3c7', color: '#b45309' }).html('<i class="fa-solid fa-star"></i> Featured Spotlight');
						} else {
							$btn.css({ background: '#f1f5f9', color: '#64748b' }).html('<i class="fa-solid fa-star"></i> Standard');
						}
						showAdminToast(res.data.message);
					}
				}
			});
		});

		$(document).on('click', '.tpd-claim-action-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var listingId = $btn.data('id');
			var decision = $btn.data('decision');
			var $row = $('#claim-row-' + listingId);
			var $statusBadge = $('#claim-status-' + listingId);

			if (!confirm('Are you sure you want to ' + decision + ' this listing claim?')) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_review_claim',
					listing_id: listingId,
					decision: decision,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						$statusBadge.text(decision + 'd');
						$row.find('.tpd-claim-action-btn').parent().html('<span style="font-size:12px; color:#64748b;">Reviewed</span>');
						showAdminToast(res.data.message);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to process claim.');
						$btn.prop('disabled', false);
					}
				}
			});
		});

		// 13. Quick Publish Blogs / Events / FAQs & Delete Post
		$(document).on('submit', '.tpd-sa-quick-content-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origText = $btn.text();
			$btn.prop('disabled', true).text('Publishing...');

			var payload = $form.serialize() + '&action=tpd_admin_quick_create_content&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).text(origText);
					if (res && res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to publish.');
					}
				}
			});
		});

		$(document).on('click', '.tpd-delete-post-btn', function(e) {
			e.preventDefault();
			var postId = $(this).data('post-id') || $(this).data('id');
			if (!confirm('Delete this item permanently?')) return;

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_post',
					post_id: postId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						$('#sa-post-row-' + postId + ', #tpd-post-row-' + postId).fadeOut(250, function() { $(this).remove(); });
						showAdminToast(res.data.message);
					}
				}
			});
		});

		// 14. WordPress Media Library Picker for Ad Images & Banners
		$(document).on('click', '.tpd-choose-media-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var targetSelector = $btn.data('target');
			var $input, $previewImg, $placeholder;

			if (targetSelector) {
				$input = $(targetSelector);
			} else {
				var $card = $btn.closest('.tpd-ad-repeater-card');
				$input = $card.find('.tpd-ad-field-image');
				$previewImg = $card.find('.tpd-ad-preview-img');
				$placeholder = $card.find('.tpd-ad-placeholder');
			}

			if (typeof wp === 'undefined' || !wp.media) {
				alert('WordPress Media Library is unavailable. Please verify admin privileges.');
				return;
			}

			var mediaFrame = wp.media({
				title: 'Select Ad Banner Image',
				button: { text: 'Use This Image' },
				multiple: false,
				library: { type: 'image' }
			});

			mediaFrame.on('select', function() {
				var attachment = mediaFrame.state().get('selection').first().toJSON();
				if (attachment && attachment.url) {
					if ($input) {
						$input.val(attachment.url).trigger('change');
					}
					if ($previewImg) {
						$previewImg.attr('src', attachment.url).show();
						if ($placeholder) $placeholder.hide();
					}
				}
			});

			mediaFrame.open();
		});

		// Live preview update on image URL change/input
		$(document).on('input change', '.tpd-ad-field-image', function() {
			var url = $(this).val().trim();
			var $card = $(this).closest('.tpd-ad-repeater-card');
			var $preview = $card.find('.tpd-ad-preview-img');
			var $placeholder = $card.find('.tpd-ad-placeholder');
			if (url) {
				$preview.attr('src', url).show();
				$placeholder.hide();
			} else {
				$preview.hide();
				$placeholder.show();
			}
		});

		// Add New Left Sidebar Ad Row
		$(document).on('click', '#tpd-add-left-ad-btn', function(e) {
			e.preventDefault();
			var tmpl = document.getElementById('tpd-left-ad-template');
			if (tmpl) {
				var clone = tmpl.content.cloneNode(true);
				$('#tpd-left-ads-list').append(clone);
				showAdminToast('New Left Sidebar Ad row added.');
			}
		});

		// Add New Bottom Strip Banner Ad Row
		$(document).on('click', '#tpd-add-bottom-ad-btn', function(e) {
			e.preventDefault();
			var tmpl = document.getElementById('tpd-bottom-ad-template');
			if (tmpl) {
				var clone = tmpl.content.cloneNode(true);
				$('#tpd-bottom-ads-list').append(clone);
				showAdminToast('New Bottom Banner Ad row added.');
			}
		});

		// Remove Ad Row
		$(document).on('click', '.tpd-remove-ad-btn', function(e) {
			e.preventDefault();
			var $card = $(this).closest('.tpd-ad-repeater-card');
			$card.fadeOut(200, function() {
				$(this).remove();
			});
		});

		// Save Ad Banners & Dynamic Repeater Configuration (WP Bannerize Style)
		$(document).on('submit', '#tpd-sa-ad-banners-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving All Ads...');

			var leftAds = [];
			$('#tpd-left-ads-list .tpd-left-ad-row').each(function(i) {
				var $row = $(this);
				var img = $row.find('.tpd-ad-field-image').val().trim();
				if (!img) return;
				leftAds.push({
					id: 'left_' + (i + 1),
					image_url: img,
					link_url: $row.find('.tpd-ad-field-link').val().trim() || '#tpd-view-suppliers',
					alt_text: $row.find('.tpd-ad-field-alt').val().trim() || 'Partner Sponsor',
					target: $row.find('.tpd-ad-field-target').val() || '_blank',
					status: $row.find('.tpd-ad-field-status').val() || 'active'
				});
			});

			var bottomAds = [];
			$('#tpd-bottom-ads-list .tpd-bottom-ad-row').each(function(i) {
				var $row = $(this);
				var img = $row.find('.tpd-ad-field-image').val().trim();
				if (!img) return;
				bottomAds.push({
					id: 'bottom_' + (i + 1),
					image_url: img,
					link_url: $row.find('.tpd-ad-field-link').val().trim() || '#tpd-view-suppliers',
					alt_text: $row.find('.tpd-ad-field-alt').val().trim() || 'Partner Offer',
					target: $row.find('.tpd-ad-field-target').val() || '_blank',
					status: $row.find('.tpd-ad-field-status').val() || 'active'
				});
			});

			var formData = $form.serializeArray();
			formData.push({ name: 'action', value: 'tpd_admin_save_ad_banners' });
			formData.push({ name: 'nonce', value: adminNonce });
			formData.push({ name: 'left_sidebar_ads_json', value: JSON.stringify(leftAds) });
			formData.push({ name: 'bottom_strip_ads_json', value: JSON.stringify(bottomAds) });

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						showAdminToast(res.data.message || 'Ad banners successfully saved and published!');
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to save ad banners.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error while saving ad banners.');
				}
			});
		});

		// ---------------------------------------------------------------------
		// 15. Super Admin Account Creation & Credential Management
		// ---------------------------------------------------------------------
		function generateSecurePassword(length) {
			length = length || 14;
			var charset = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%&*';
			var retVal = '';
			for (var i = 0, n = charset.length; i < length; ++i) {
				retVal += charset.charAt(Math.floor(Math.random() * n));
			}
			return retVal;
		}

		// Open Create Super Admin Modal
		$(document).on('click', '#tpd-btn-open-create-admin', function(e) {
			e.preventDefault();
			var $form = $('#tpd-sa-create-admin-form');
			if ($form.length) {
				$form[0].reset();
			}
			$('#tpd-sa-create-admin-modal').addClass('open').fadeIn(150);
		});

		// Generate Password inside Create Admin Form
		$(document).on('click', '#csa_btn_gen_pass', function(e) {
			e.preventDefault();
			var pass = generateSecurePassword(14);
			$('#csa_password').attr('type', 'text').val(pass);
		});

		// Submit Create Super Admin Form
		$(document).on('submit', '#tpd-sa-create-admin-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Creating...');

			var formData = $form.serialize();
			formData += '&action=tpd_admin_create_super_admin&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						$('#tpd-sa-create-admin-modal').removeClass('open').fadeOut(150);
						showAdminToast(res.data.message || 'Super Admin account created successfully!');
						setTimeout(function() {
							window.location.reload();
						}, 1200);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to create Super Admin account.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error while creating Super Admin account.');
				}
			});
		});

		// Open Change Admin Password Modal
		$(document).on('click', '.tpd-btn-admin-change-pass', function(e) {
			e.preventDefault();
			var uid = $(this).attr('data-user-id');
			var uname = $(this).attr('data-username');
			$('#cap_user_id').val(uid);
			$('#cap_target_username').text(uname);
			$('#cap_new_password').attr('type', 'password').val('');
			$('#tpd-sa-admin-pass-modal').addClass('open').fadeIn(150);
		});

		// Generate Password inside Admin Password Modal
		$(document).on('click', '#cap_btn_gen_pass', function(e) {
			e.preventDefault();
			var pass = generateSecurePassword(14);
			$('#cap_new_password').attr('type', 'text').val(pass);
		});

		// Submit Change Admin Password Form
		$(document).on('submit', '#tpd-sa-admin-pass-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Updating...');

			var formData = $form.serialize();
			formData += '&action=tpd_admin_update_admin_password&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: formData,
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						$('#tpd-sa-admin-pass-modal').removeClass('open').fadeOut(150);
						showAdminToast(res.data.message || 'Password updated successfully!');
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to update password.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error while updating password.');
				}
			});
		});

		// Delete Super Admin
		$(document).on('click', '.tpd-btn-admin-del', function(e) {
			e.preventDefault();
			var uid = $(this).attr('data-user-id');
			var uname = $(this).attr('data-username');
			if (!confirm('Are you sure you want to permanently delete Super Admin account "' + uname + '"? This action cannot be undone.')) {
				return;
			}

			var $row = $('#sa-admin-row-' + uid);
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_super_admin',
					user_id: uid,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						$row.fadeOut(300, function() { $(this).remove(); });
						showAdminToast(res.data.message || 'Super Admin deleted.');
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to delete administrator.');
					}
				},
				error: function() {
					alert('Network error while deleting administrator.');
				}
			});
		});

		// Helper to resolve front/user nonce alongside adminNonce
		var userNonce = (typeof tpd_data !== 'undefined' && tpd_data.nonce) ? tpd_data.nonce : '';

		// ---------------------------------------------------------------------
		// 16. Financial Ledger Filtering & Transaction Actions
		// ---------------------------------------------------------------------
		function filterFinancialTable() {
			var q = ($('#tpd-fin-search').val() || '').toLowerCase().trim();
			var itemType = $('#tpd-fin-type-filter').val() || 'all';
			var status = $('#tpd-fin-status-filter').val() || 'all';

			$('#tpd-fin-table tbody tr').each(function() {
				var $tr = $(this);
				if (!$tr.attr('data-item-type')) return;
				var rType = $tr.attr('data-item-type') || '';
				var rStatus = $tr.attr('data-status') || '';
				var txt = $tr.text().toLowerCase();

				var okQ = (q === '' || txt.indexOf(q) !== -1);
				var okType = (itemType === 'all' || rType === itemType);
				var okStatus = (status === 'all' || rStatus === status);

				$tr.toggle(okQ && okType && okStatus);
			});
		}

		$(document).on('keyup input', '#tpd-fin-search', filterFinancialTable);
		$(document).on('change', '#tpd-fin-type-filter, #tpd-fin-status-filter', filterFinancialTable);

		$(document).on('click', '.tpd-tx-status-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var txId = $btn.data('id');
			var nextStatus = $btn.data('status');
			var confirmMsg = (nextStatus === 'paid')
				? 'Mark this transaction as PAID and run automatic plan/LMS fulfillment?'
				: 'Mark this transaction as REFUNDED?';
			if (!confirm(confirmMsg)) return;

			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_update_tx_status',
					tx_id: txId,
					status: nextStatus,
					nonce: adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						showAdminToast(res.data.message || 'Transaction updated.');
						window.location.hash = 'sa-financial';
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to update transaction.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error updating transaction.');
				}
			});
		});

		// ---------------------------------------------------------------------
		// 17. LMS Studio & Custom TPD Resources Controller
		// ---------------------------------------------------------------------
		$(document).on('click', '.tpd-sa-lms-subtab', function(e) {
			e.preventDefault();
			var tab = $(this).data('lmstab') || 'studio';
			$('.tpd-sa-lms-subtab').each(function() {
				var active = ($(this).data('lmstab') === tab);
				$(this).toggleClass('active', active);
				if (active) {
					$(this).css({ background: '#164e87', color: '#ffffff', borderColor: '#164e87' });
				} else {
					$(this).css({ background: '#f8fafc', color: '#334155', borderColor: '#cbd5e1' });
				}
			});
			$('.tpd-sa-lms-panel').hide().removeClass('active');
			$('#tpd-sa-lms-tab-' + tab).fadeIn(140).addClass('active');
		});

		
		$(document).on('click', '.tpd-sa-jump-lms-settings', function(e) {
			e.preventDefault();
			$('.tpd-sa-lms-subtab[data-lmstab="settings"]').trigger('click');
			$('input[name="sso_secret"]').focus();
		});

		function generateSecureHex(byteLength) {
			byteLength = byteLength || 32;
			var arr = new Uint8Array(byteLength);
			if (window.crypto && window.crypto.getRandomValues) {
				window.crypto.getRandomValues(arr);
			} else {
				for (var i = 0; i < byteLength; i++) {
					arr[i] = Math.floor(Math.random() * 256);
				}
			}
			var hex = '';
			for (var j = 0; j < arr.length; j++) {
				hex += arr[j].toString(16).padStart(2, '0');
			}
			return hex;
		}

		$(document).on('click', '#tpd-btn-gen-lms-keys', function(e) {
			e.preventDefault();
			var sso = generateSecureHex(32);
			var api = generateSecureHex(32);
			var wh = generateSecureHex(32);

			$('input[name="sso_secret"]').attr('type', 'text').val(sso);
			$('input[name="api_token"]').attr('type', 'text').val(api);
			$('input[name="webhook_secret"]').attr('type', 'text').val(wh);

			showAdminToast('Secure keys generated! Click "Save LMS Settings" below to apply.');
		});

		$(document).on('submit', '#tpd-sa-lms-settings-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

			var payload = $form.serialize() + '&action=tpd_admin_save_lms_settings&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						showAdminToast(res.data.message || 'LMS settings saved!');
						window.location.hash = 'sa-lms-studio';
						setTimeout(function() { window.location.reload(); }, 750);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to save LMS settings.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error saving LMS settings.');
				}
			});
		});

		$(document).on('click', '#tpd-sa-test-lms-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Testing...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_test_lms_connection',
					nonce: adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						showAdminToast(res.data.message || 'LMS connection healthy!');
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'LMS health check failed.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error testing LMS connection.');
				}
			});
		});

		$(document).on('click', '#tpd-sa-sync-lms-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Syncing...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_sync_lms_cache',
					nonce: adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						showAdminToast(res.data.message || 'LMS cache synced!');
						window.location.hash = 'sa-lms-studio';
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'LMS sync failed.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error syncing LMS cache.');
				}
			});
		});

		$(document).on('click', '.tpd-lms-toggle-publish-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var slug = $btn.data('slug');
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_toggle_course_publish',
					course_slug: slug,
					nonce: adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false);
					if (res && res.success && res.data) {
						var isPub = (res.data.status === 'published');
						var $pill = $('.tpd-lms-status-pill[data-slug="' + slug + '"]');
						$pill.toggleClass('active', isPub).toggleClass('inactive', !isPub).text(isPub ? 'PUBLISHED' : 'DRAFT');
						$btn.html('<i class="fa-solid fa-power-off"></i> ' + (isPub ? 'Set Draft' : 'Publish'));
						showAdminToast(res.data.message || 'Course status updated.');
					} else {
						$btn.html(origHtml);
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to toggle course status.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error updating course status.');
				}
			});
		});

		function fetchAdminLmsSsoUrl(redirectPath, onSuccess, onError) {
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_get_lms_sso_url',
					redirect: redirectPath || '/studio',
					nonce: userNonce
				},
				success: function(res) {
					if (res && res.success && res.data && res.data.sso_url) {
						onSuccess(res.data.sso_url);
					} else if (onError) {
						onError((res && res.data && res.data.message) ? res.data.message : 'Could not build SSO URL.');
					}
				},
				error: function() {
					if (onError) onError('Network error generating LMS SSO token.');
				}
			});
		}

		$(document).on('click', '#tpd-sa-load-lms-iframe', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var origHtml = $btn.html();
			var route = $('#tpd-sa-lms-route').val() || '/studio';
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Connecting SSO...');

			fetchAdminLmsSsoUrl(route, function(ssoUrl) {
				$btn.prop('disabled', false).html(origHtml);
				$('#tpd-sa-lms-iframe-placeholder').hide();
				showLmsStudioView(ssoUrl, route);
				showAdminToast('LMS Studio SSO session loaded.');
			}, function(errMsg) {
				$btn.prop('disabled', false).html(origHtml);
				if (errMsg && (errMsg.indexOf('not configured') !== -1 || errMsg.indexOf('disabled') !== -1)) {
					if (confirm(errMsg + '\n\nWould you like to open "Connection & .env Setup" now to configure or generate keys?')) {
						$('.tpd-sa-lms-subtab[data-lmstab="settings"]').trigger('click');
						$('input[name="sso_secret"]').focus();
					}
				} else {
					alert(errMsg);
				}
			});
		});

		
		// Studio Simulator & Iframe Toggle Controller
		function showLmsStudioView(ssoUrl, route) {
			$('#tpd-sa-lms-iframe-placeholder').hide();
			$('#tpd-sa-lms-active-dest').text(route || '/studio');
			$('#tpd-sa-lms-active-sso-link').attr('href', ssoUrl || '#');
			$('#tpd-sa-lms-active-bar').css('display', 'flex').show();
			$('#tpd-sa-lms-simulator').hide();
			$('#tpd-sa-lms-iframe').attr('src', ssoUrl).show();
			$('#tpd-sa-mode-label').text('Switch to Studio Simulator');
		}

		function showLmsStudioSimulator() {
			$('#tpd-sa-lms-iframe-placeholder').hide();
			$('#tpd-sa-lms-active-dest').text('/studio (Interactive Simulator)');
			$('#tpd-sa-lms-active-bar').css('display', 'flex').show();
			$('#tpd-sa-lms-iframe').hide();
			$('#tpd-sa-lms-simulator').fadeIn(150);
			$('#tpd-sa-mode-label').text('Switch to Live Iframe');
		}

		$(document).on('click', '.tpd-sa-trigger-load-studio', function(e) {
			e.preventDefault();
			$('#tpd-sa-load-lms-iframe').trigger('click');
		});

		$(document).on('click', '.tpd-sa-open-simulator-btn', function(e) {
			e.preventDefault();
			showLmsStudioSimulator();
		});

		$(document).on('click', '#tpd-sa-toggle-studio-mode', function(e) {
			e.preventDefault();
			if ($('#tpd-sa-lms-simulator').is(':visible')) {
				$('#tpd-sa-lms-simulator').hide();
				$('#tpd-sa-lms-iframe').show();
				$('#tpd-sa-mode-label').text('Switch to Studio Simulator');
			} else {
				showLmsStudioSimulator();
			}
		});

		$(document).on('click', '#tpd-sa-close-lms-frame', function(e) {
			e.preventDefault();
			$('#tpd-sa-lms-active-bar, #tpd-sa-lms-iframe, #tpd-sa-lms-simulator').hide();
			$('#tpd-sa-lms-iframe-placeholder').fadeIn(150);
		});

		$(document).on('click', '.tpd-sim-manage-curriculum', function(e) {
			e.preventDefault();
			var title = $(this).data('title') || 'Course';
			alert('Curriculum for "' + title + '":\n\n1. Course Introduction & Welcome\n2. Partner Commission Structures & Tier Matrix\n3. Destination Highlights & Private Amenities\n4. Final Knowledge Quiz & Certificate Exam');
		});

		$(document).on('click', '.tpd-sim-quiz-builder', function(e) {
			e.preventDefault();
			var title = $(this).data('title') || 'Course';
			alert('Quiz Builder for "' + title + '":\n\n10 Multiple-Choice Questions active. Passing score: 80% to earn official Certificate of Completion.');
		});

		$(document).on('click', '.tpd-sim-new-course-btn', function(e) {
			e.preventDefault();
			var name = prompt('Enter New Course Title to create in LMS Studio:');
			if (name && name.trim()) {
				showAdminToast('Draft course "' + name.trim() + '" created in LMS Studio!');
			}
		});

		$(document).on('click', '#tpd-sa-open-lms-tab, .tpd-sa-sso-jump', function(e) {
			e.preventDefault();
			var route = $(this).data('redirect') || $('#tpd-sa-lms-route').val() || '/studio';
			var tab = window.open('about:blank', '_blank');

			fetchAdminLmsSsoUrl(route, function(ssoUrl) {
				if (tab && !tab.closed) {
					tab.location.href = ssoUrl;
				} else {
					window.open(ssoUrl, '_blank');
				}
			}, function(errMsg) {
				if (tab && !tab.closed) tab.close();
				alert(errMsg);
			});
		});

		$(document).on('submit', '#tpd-sa-resource-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var origHtml = $btn.html();
			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

			var payload = $form.serialize() + '&action=tpd_admin_save_custom_resource&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html(origHtml);
					if (res && res.success) {
						showAdminToast(res.data.message || 'TPD Resource published!');
						window.location.hash = 'sa-lms-studio';
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to save resource.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html(origHtml);
					alert('Network error saving resource.');
				}
			});
		});

		$(document).on('click', '.tpd-sa-delete-resource-btn', function(e) {
			e.preventDefault();
			var resId = $(this).data('id');
			if (!confirm('Delete this TPD resource/toolkit?')) return;

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_custom_resource',
					resource_id: resId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res && res.success) {
						$('#sa-res-row-' + resId).fadeOut(250, function() { $(this).remove(); });
						showAdminToast(res.data.message || 'Resource deleted.');
					} else {
						alert((res && res.data && res.data.message) ? res.data.message : 'Failed to delete resource.');
					}
				},
				error: function() {
					alert('Network error deleting resource.');
				}
			});
		});

		function showAdminToast(msg) {
			var $toast = $('<div class="tpd-admin-toast"><i class="fa-solid fa-circle-check" style="color:#4ade80;"></i> ' + msg + '</div>');
			$('body').append($toast);
			$toast.css({
				position: 'fixed',
				bottom: '24px',
				right: '24px',
				background: '#0b1526',
				color: '#ffffff',
				padding: '12px 20px',
				borderRadius: '10px',
				boxShadow: '0 10px 25px rgba(0,0,0,0.25)',
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
