/**
 * TPD Tool - Real-Time Chat & Instant Messaging Controller
 * Handles sending messages, real-time bubble insertion, and guest traveler alerts.
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		var $stream = $('#tpd-chat-stream');

		// Scroll to bottom helper
		function scrollToBottom() {
			if ($stream.length) {
				$stream.animate({ scrollTop: $stream.prop('scrollHeight') }, 300);
			}
		}

		// Initial scroll
		scrollToBottom();

		// Handle Chat Form Submit
		$(document).on('submit', '#tpd-chat-composer-form', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $textarea = $form.find('#tpd-chat-message-input');
			var $btn = $form.find('#tpd-chat-submit-btn');
			var message = $textarea.val().trim();

			if (!message) {
				$textarea.focus();
				return;
			}

			var formData = {
				action: 'tpd_send_chat_message',
				recipient_id: $('#tpd-chat-recipient-id').val() || 2,
				message: message,
				nonce: (typeof tpd_data !== 'undefined' && tpd_data.chat_nonce) ? tpd_data.chat_nonce : ''
			};

			var $guestName = $form.find('input[name="guest_name"]');
			var $guestEmail = $form.find('input[name="guest_email"]');
			if ($guestName.length && $guestName.val()) {
				formData.guest_name = $guestName.val().trim();
			}
			if ($guestEmail.length && $guestEmail.val()) {
				formData.guest_email = $guestEmail.val().trim();
			}

			$btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: formData,
				success: function(res) {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i>');

					if (res.success) {
						// Append outbound message bubble
						var bubbleHtml = '<div class="tpd-chat-bubble tpd-bubble-outbound">' +
							'<div class="tpd-cb-text">' + $('<div>').text(message).html() + '</div>' +
							'<div class="tpd-cb-time">Just now · Delivered & Emailed</div>' +
							'</div>';

						$stream.append(bubbleHtml);
						$textarea.val('');
						scrollToBottom();

						// Show friendly notification
						var alertMsg = res.data.message || 'Message sent! Email alert dispatched.';
						showChatNotice(alertMsg);
					} else {
						alert(res.data.message || 'Failed to send message.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i>');
					alert('Connection error. Please try again.');
				}
			});
		});

		// Allow Enter key to submit (Shift+Enter for newline)
		$(document).on('keydown', '#tpd-chat-message-input', function(e) {
			if (e.which === 13 && !e.shiftKey) {
				e.preventDefault();
				$('#tpd-chat-composer-form').trigger('submit');
			}
		});

		// Trigger Chat with Specific Rep (e.g. from Virtual Office or Rep Card)
		$(document).on('click', '.tpd-open-chat-btn, .tpd-btn-chat-rep', function(e) {
			e.preventDefault();
			var repId = $(this).data('rep-id') || $(this).data('user-id');
			var repName = $(this).data('rep-name') || 'Representative';

			if (repId) {
				$('#tpd-chat-recipient-id').val(repId);
			}
			if (repName) {
				$('#tpd-chat-active-name').text(repName);
			}

			// Switch to conversations view
			if ($('.tpd-tab-link[data-view="conversations"]').length) {
				$('.tpd-tab-link[data-view="conversations"]').trigger('click');
			} else if ($('.tpd-tab-link[data-view="supp-chat"]').length) {
				$('.tpd-tab-link[data-view="supp-chat"]').trigger('click');
			}

			$('#tpd-chat-message-input').focus();
		});

		function showChatNotice(msg) {
			var $note = $('<div class="tpd-chat-notice-banner">' +
				'<i class="fa-solid fa-circle-check text-green"></i> ' + msg +
				'</div>');
			$stream.append($note);
			$note.css({
				textAlign: 'center',
				fontSize: '12px',
				color: '#10b981',
				padding: '6px 12px',
				background: '#ecfdf5',
				borderRadius: '8px',
				margin: '8px auto',
				maxWidth: '85%'
			});
			scrollToBottom();
			setTimeout(function() {
				$note.fadeOut(400, function() { $(this).remove(); });
			}, 4000);
		}
	});

})(jQuery);
