/**
 * TPD Tool - Super Admin Control Hub Controller
 * Handles 1-Click Tier Switching and Feature Permission Matrix saving.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		// 1. One-Click User Membership Tier Toggle
		$(document).on('click', '.tpd-toggle-tier-btn', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var userId = $btn.data('user-id');
			var currentTier = $btn.attr('data-current-tier') || 'free';
			var targetTier = (currentTier === 'paid') ? 'free' : 'paid';

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Updating...');

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
					$btn.prop('disabled', false);

					if (res.success) {
						var newTier = res.data.tier;
						$btn.attr('data-current-tier', newTier);

						var $badge = $('#tier-badge-' + userId);

						if (newTier === 'paid') {
							$badge.removeClass('tpd-tier-free').addClass('tpd-tier-paid')
								  .html('<i class="fa-solid fa-crown text-gold"></i> Paid Pro');
							$btn.removeClass('tpd-btn-secondary').addClass('tpd-btn-outline')
								.text('Demote to Free');
						} else {
							$badge.removeClass('tpd-tier-paid').addClass('tpd-tier-free')
								  .text('Free Tier');
							$btn.removeClass('tpd-btn-outline').addClass('tpd-btn-secondary')
								.text('Upgrade to Paid Pro');
						}

						showAdminToast('User tier updated to ' + res.data.label);
					} else {
						alert(res.data.message || 'Failed to update tier.');
						$btn.text((currentTier === 'paid') ? 'Demote to Free' : 'Upgrade to Paid Pro');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert('Connection error. Please try again.');
					$btn.text((currentTier === 'paid') ? 'Demote to Free' : 'Upgrade to Paid Pro');
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
