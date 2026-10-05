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

		// 14. Ad Space Configurator Save
		$(document).on('submit', '#tpd-sa-ad-banners-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving Ad Space...');

			var payload = $form.serialize() + '&action=tpd_admin_save_ad_banners&nonce=' + encodeURIComponent(adminNonce);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: payload,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Ad Space Configuration');
					if (res && res.success) {
						showAdminToast(res.data.message);
					}
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
