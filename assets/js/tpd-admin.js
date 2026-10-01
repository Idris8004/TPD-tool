/**
 * TPD Tool - Super Admin Control Hub Controller
 * Handles 1-Click Tier Switching and Feature Permission Matrix saving.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. Instant 3-Tier Plan Switcher (Basic, Standard, Premium)
		$(document).on('change', '.tpd-tier-select', function(e) {
			var $select = $(this);
			var userId = $select.data('user-id');
			var targetTier = $select.val();
			var $ind = $('#tier-ind-' + userId);

			$select.prop('disabled', true);

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'tpd_update_user_tier',
					user_id: userId,
					tier: targetTier,
					nonce: (typeof tpd_data !== 'undefined' && tpd_data.admin_nonce) ? tpd_data.admin_nonce : ''
				},
				success: function(res) {
					$select.prop('disabled', false);

					if (res.success) {
						var newTier = res.data.tier;
						var $badge = $('#tier-badge-' + userId);

						$badge.removeClass('tpd-tier-basic tpd-tier-standard tpd-tier-premium tpd-tier-free tpd-tier-paid')
							  .addClass('tpd-tier-' + newTier);

						if (newTier === 'premium') {
							$badge.html('<i class="fa-solid fa-crown text-gold"></i> Premium Plan');
						} else if (newTier === 'standard') {
							$badge.html('<i class="fa-solid fa-gem text-blue"></i> Standard Plan');
						} else {
							$badge.text('Basic (Free)');
						}

						$ind.fadeIn(200).delay(2000).fadeOut(300);
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

		// 2. Pending Brand Listing Claim Action (Approve / Reject)
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
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'tpd_admin_review_claim',
					listing_id: listingId,
					decision: decision,
					nonce: (typeof tpd_data !== 'undefined' && tpd_data.admin_nonce) ? tpd_data.admin_nonce : ''
				},
				success: function(res) {
					if (res.success) {
						if (decision === 'approve') {
							$statusBadge.css({ 'background': '#dcfce7', 'color': '#166534' }).text('approved');
						} else {
							$statusBadge.css({ 'background': '#fee2e2', 'color': '#991b1b' }).text('rejected');
						}
						$row.find('.tpd-claim-action-btn').parent().html('<span class="text-muted" style="font-size:12px;">Reviewed</span>');
						showAdminToast(res.data.message);
					} else {
						alert(res.data.message || 'Failed to process claim.');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert('Connection error. Please try again.');
				}
			});
		});

		// 2. Feature Permission Matrix Form Submission
		$('#tpd-tier-permissions-form').on('submit', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $('#tpd-save-permissions-btn');
			var $status = $('#tpd-matrix-status-msg');

			var freePerms = [];
			$('input[name="free_permissions[]"]:checked').each(function() {
				freePerms.push($(this).val());
			});

			var paidPerms = [];
			$('input[name="paid_permissions[]"]:checked').each(function() {
				paidPerms.push($(this).val());
			});

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving Matrix...');
			$status.hide();

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'tpd_save_tier_permissions',
					free_permissions: freePerms,
					paid_permissions: paidPerms,
					nonce: (typeof tpd_data !== 'undefined' && tpd_data.admin_nonce) ? tpd_data.admin_nonce : ''
				},
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Feature Permissions Matrix');

					if (res.success) {
						$status.html('<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || 'Permissions updated successfully!')).fadeIn();
						setTimeout(function() {
							$status.fadeOut();
						}, 4000);
					} else {
						alert(res.data.message || 'Failed to update permissions.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk"></i> Save Feature Permissions Matrix');
					alert('Connection error. Please try again.');
				}
			});
		});

		function showAdminToast(msg) {
			var $toast = $('<div class="tpd-admin-toast"><i class="fa-solid fa-circle-check text-green"></i> ' + msg + '</div>');
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
