/**
 * TPD Tool - Super Admin Control Hub Controller
 * Handles Sidebar Navigation, User CRUD (View/Add/Edit/Delete/Promote/Demote/Status),
 * Plan Builder, Payment Gateway Settings, Form & Custom Field Configurator, Content & Ads.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		var ajaxUrl = (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php';
		var adminNonce = (typeof tpd_data !== 'undefined' && tpd_data.admin_nonce) ? tpd_data.admin_nonce : '';

		// 1. Super Admin Sidebar Navigation
		$(document).on('click', '.tpd-sa-nav-link[data-pane]', function(e) {
			e.preventDefault();
			var paneId = $(this).data('pane');
			$('.tpd-sa-nav-link').removeClass('active');
			$(this).addClass('active');

			$('.tpd-sa-pane').removeClass('active');
			$('#tpd-sa-pane-' + paneId).addClass('active');
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});

		// 2. Live Table Search for Advisors & Suppliers
		$('#tpd-sa-search-advisors').on('keyup', function() {
			var q = $(this).val().toLowerCase().trim();
			$('#tpd-sa-advisors-table tbody tr').each(function() {
				var txt = $(this).text().toLowerCase();
				$(this).toggle(txt.indexOf(q) !== -1 || q === '');
			});
		});

		$('#tpd-sa-search-suppliers').on('keyup', function() {
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
			var $ind = $('#tier-ind-' + userId);

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
					if (res.success) {
						$ind.fadeIn(200).delay(1800).fadeOut(300);
						showAdminToast('User plan updated to ' + res.data.label);
					} else {
						alert(res.data.message || 'Failed to update tier.');
					}
				},
				error: function() {
					$select.prop('disabled', false);
					alert('Connection error. Please try again.');
				}
			});
		});

		// 4. Toggle User Active / Suspended Status
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
					if (res.success) {
						if (res.data.status === 'active') {
							$btn.removeClass('tpd-badge-suspended').addClass('tpd-badge-active').text('Active');
						} else {
							$btn.removeClass('tpd-badge-active').addClass('tpd-badge-suspended').text('Suspended');
						}
						showAdminToast(res.data.message);
					} else {
						alert(res.data.message || 'Failed to toggle status.');
					}
				}
			});
		});

		// 5. View User Details Modal (Eye Icon)
		$(document).on('click', '.tpd-btn-view-user', function(e) {
			e.preventDefault();
			var userId = $(this).data('user-id');
			$('#tpd-sa-view-user-body').html('<p style="text-align:center; padding:20px;"><i class="fa-solid fa-spinner fa-spin"></i> Loading user profile...</p>');
			$('#tpd-sa-view-user-modal').addClass('open');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_get_user_details',
					user_id: userId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res.success) {
						var d = res.data;
						var html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13.5px;">' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Full Name:</strong><br>' + (d.first_name + ' ' + d.last_name) + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Username:</strong><br>@' + d.username + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Email:</strong><br>' + d.email + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Phone:</strong><br>' + (d.phone || '—') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Agency / Company:</strong><br>' + (d.company || '—') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Position / Title:</strong><br>' + (d.position_title || '—') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Country / Location:</strong><br>' + (d.country || '—') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Current Plan:</strong><br><span style="text-transform:uppercase; font-weight:700; color:#1d4ed8;">' + d.tier + '</span> (' + d.account_status + ')</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Plan Expiry:</strong><br>' + (d.plan_expiry || 'Lifetime / N/A') + '</div>' +
							'<div style="background:#f8fafc; padding:12px; border-radius:8px;"><strong>Registered Date:</strong><br>' + d.registered + '</div>' +
						'</div>';
						if (d.cpt_edit_url) {
							html += '<div style="margin-top:16px; padding-top:12px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">' +
								'<span style="font-size:12.5px; color:#64748b;">Paired WP Custom Post Type ID: #' + d.cpt_id + '</span>' +
								'<a href="' + d.cpt_edit_url + '" class="tpd-sa-btn tpd-sa-btn-blue"><i class="fa-solid fa-arrow-up-right-from-square"></i> Edit Full Post in WP Admin</a>' +
							'</div>';
						}
						$('#tpd-sa-view-user-body').html(html);
					} else {
						$('#tpd-sa-view-user-body').html('<p style="color:#dc2626;">Failed to load user details.</p>');
					}
				}
			});
		});

		// 6. Add / Edit User Modal (Pencil Icon & + Add Advisor / Supplier Buttons)
		$('#tpd-btn-open-add-advisor').on('click', function() {
			$('#tpd-sa-save-user-form')[0].reset();
			$('#sa_edit_user_id').val(0);
			$('#sa_edit_user_role').val('travel_advisor');
			$('#tpd-sa-edit-modal-title').text('Add New Travel Advisor');
			$('#tpd-sa-edit-user-modal').addClass('open');
		});

		$('#tpd-btn-open-add-supplier').on('click', function() {
			$('#tpd-sa-save-user-form')[0].reset();
			$('#sa_edit_user_id').val(0);
			$('#sa_edit_user_role').val('supplier_partner');
			$('#tpd-sa-edit-modal-title').text('Add New Supplier Partner');
			$('#tpd-sa-edit-user-modal').addClass('open');
		});

		$(document).on('click', '.tpd-btn-edit-user', function(e) {
			e.preventDefault();
			var userId = $(this).data('user-id');
			$('#tpd-sa-save-user-form')[0].reset();
			$('#tpd-sa-edit-modal-title').text('Edit User Account #' + userId);
			$('#tpd-sa-edit-user-modal').addClass('open');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_get_user_details',
					user_id: userId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res.success) {
						var d = res.data;
						$('#sa_edit_user_id').val(d.id);
						$('#sa_edit_user_role').val(d.role);
						$('#sa_edit_first_name').val(d.first_name);
						$('#sa_edit_last_name').val(d.last_name);
						$('#sa_edit_email').val(d.email);
						$('#sa_edit_phone').val(d.phone);
						$('#sa_edit_company').val(d.company);
						$('#sa_edit_position').val(d.position_title);
						$('#sa_edit_country').val(d.country);
						$('#sa_edit_tier').val(d.tier);
						$('#sa_edit_status').val(d.account_status);
					}
				}
			});
		});

		$('#tpd-sa-save-user-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Account & Sync CPT');
					if (res.success) {
						$('#tpd-sa-edit-user-modal').removeClass('open');
						showAdminToast(res.data.message);
						setTimeout(function() {
							window.location.reload();
						}, 800);
					} else {
						alert(res.data.message || 'Failed to save user.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Account & Sync CPT');
					alert('Connection error.');
				}
			});
		});

		// 7. Delete User & Paired Custom Post Type (Trash Icon)
		$(document).on('click', '.tpd-btn-delete-user', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var userId = $btn.data('user-id');
			var name = $btn.data('name');

			if (!confirm('Are you sure you want to permanently delete "' + name + '" and their paired directory Custom Post Type? This action cannot be undone.')) {
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
					if (res.success) {
						$('#tpd-user-row-' + userId).fadeOut(300, function() { $(this).remove(); });
						showAdminToast(res.data.message);
					} else {
						$btn.prop('disabled', false);
						alert(res.data.message || 'Failed to delete user.');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert('Connection error.');
				}
			});
		});

		// 8. Membership Plan Builder (Save / Edit / Delete Plan)
		$('#tpd-sa-plan-builder-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving Plan...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Membership Plan');
					if (res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert(res.data.message || 'Failed to save plan.');
					}
				}
			});
		});

		$(document).on('click', '.tpd-edit-plan-btn', function(e) {
			e.preventDefault();
			var plan = $(this).data('plan');
			if (!plan) return;
			$('#plan_slug').val(plan.slug);
			$('#plan_name').val(plan.name);
			$('#plan_target_role').val(plan.target_role || 'all');
			$('#plan_monthly_price').val(plan.monthly_price);
			$('#plan_annual_price').val(plan.annual_price);
			$('#plan_duration_days').val(plan.duration_days || 365);
			$('#plan_badge_text').val(plan.badge_text || '');
			$('#plan_description').val(plan.description || '');
			if (Array.isArray(plan.features)) {
				$('#plan_features').val(plan.features.join('\n'));
			}
			showAdminToast('Loaded "' + plan.name + '" into Plan Builder form.');
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
					if (res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert(res.data.message || 'Cannot delete this plan.');
					}
				}
			});
		});

		// 9. Payment Gateways Configuration Save
		$('#tpd-sa-payment-settings-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving Gateways...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Payment Gateway Configuration');
					if (res.success) {
						showAdminToast(res.data.message);
					} else {
						alert(res.data.message || 'Failed to save payment settings.');
					}
				}
			});
		});

		// 10. Feature Permission Matrix Form Submission
		$('#tpd-tier-permissions-form').on('submit', function(e) {
			e.preventDefault();
			var $btn = $('#tpd-save-permissions-btn');

			var freePerms = [];
			$('input[name="free_permissions[]"]:checked').each(function() {
				freePerms.push($(this).val());
			});

			var paidPerms = [];
			$('input[name="paid_permissions[]"]:checked').each(function() {
				paidPerms.push($(this).val());
			});

			$btn.prop('disabled', true).text('Saving Matrix...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_save_tier_permissions',
					free_permissions: freePerms,
					paid_permissions: paidPerms,
					nonce: adminNonce
				},
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Feature Permission Matrix');
					if (res.success) {
						showAdminToast(res.data.message || 'Permissions updated!');
					}
				}
			});
		});

		// 11. Registration Wizard & Dropdown Configurator Save
		$('#tpd-sa-form-config-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Registration Form Settings');
					if (res.success) {
						showAdminToast(res.data.message);
					}
				}
			});
		});

		// 12. Dynamic Custom Fields Builder (Add & Delete)
		$('#tpd-sa-custom-field-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Adding Field...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-plus"></i> Add Custom Field');
					if (res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert(res.data.message || 'Failed to add custom field.');
					}
				}
			});
		});

		$(document).on('click', '.tpd-delete-cf-btn', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			var key = $(this).data('key');
			if (!confirm('Remove custom field "' + key + '"?')) return;

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_custom_field',
					target: target,
					field_key: key,
					nonce: adminNonce
				},
				success: function(res) {
					if (res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 600);
					}
				}
			});
		});

		// 13. Featured Supplier Toggle & Pending Brand Claim Approval
		$(document).on('click', '.tpd-toggle-featured-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var postId = $btn.data('id');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_toggle_featured_supplier',
					post_id: postId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res.success) {
						if (res.data.is_featured === 'yes') {
							$btn.removeClass('tpd-sa-btn-outline').addClass('tpd-sa-btn-amber').html('<i class="fa-solid fa-star"></i> Featured');
						} else {
							$btn.removeClass('tpd-sa-btn-amber').addClass('tpd-sa-btn-outline').html('<i class="fa-regular fa-star"></i> Standard');
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
					if (res.success) {
						$statusBadge.text(decision + 'd');
						$row.find('.tpd-claim-action-btn').parent().html('<span style="font-size:12px; color:#64748b;">Reviewed</span>');
						showAdminToast(res.data.message);
					} else {
						alert(res.data.message || 'Failed to process claim.');
						$btn.prop('disabled', false);
					}
				}
			});
		});

		// 14. Quick Publish Blogs / Events / FAQs & Delete Post
		$('.tpd-sa-quick-content-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Publishing...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).text('Publish');
					if (res.success) {
						showAdminToast(res.data.message);
						setTimeout(function() { window.location.reload(); }, 700);
					} else {
						alert(res.data.message || 'Failed to publish.');
					}
				}
			});
		});

		$(document).on('click', '.tpd-delete-post-btn', function(e) {
			e.preventDefault();
			var postId = $(this).data('id');
			if (!confirm('Delete this item permanently?')) return;

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'tpd_admin_delete_content_item',
					post_id: postId,
					nonce: adminNonce
				},
				success: function(res) {
					if (res.success) {
						$('#tpd-post-row-' + postId).fadeOut(250, function() { $(this).remove(); });
						showAdminToast(res.data.message);
					}
				}
			});
		});

		// 15. Ad Space Configurator Save
		$('#tpd-sa-ad-banners-form').on('submit', function(e) {
			e.preventDefault();
			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true).text('Saving Ad Space...');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: $form.serialize(),
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Ad Space Configuration');
					if (res.success) {
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
