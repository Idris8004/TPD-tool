/**
 * TPD Tool - Real-Time Chat & Instant Messaging Controller
 * 100% Self-Hosted WordPress Database Chat + 5s Live Polling + Email Alerts
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		var $stream = $('#tpd-chat-stream');
		var isLoadingThread = false;

		// Scroll to bottom helper
		function scrollToBottom(instant) {
			var $s = $('#tpd-chat-stream');
			if ($s.length) {
				if (instant) {
					$s.scrollTop($s.prop('scrollHeight'));
				} else {
					$s.animate({ scrollTop: $s.prop('scrollHeight') }, 250);
				}
			}
		}

		// Initial scroll
		scrollToBottom(true);

		// Render message array into #tpd-chat-stream
		function renderThreadMessages(messages, partnerName, scrollIfChanged) {
			var $s = $('#tpd-chat-stream');
			if (!$s.length) return;

			if (!messages || !messages.length) {
				var emptyHtml = '<div class="tpd-chat-empty-state" style="text-align:center; padding:48px 20px; color:#64748b;">' +
					'<i class="fa-regular fa-paper-plane" style="font-size:32px; color:#94a3b8; margin-bottom:10px; display:block;"></i>' +
					'<strong style="color:#0b1526; display:block; margin-bottom:4px;">Start a conversation with ' + $('<div>').text(partnerName || 'Partner').html() + '</strong>' +
					'<span style="font-size:12.5px;">Messages are saved directly to your portal inbox and trigger an instant email alert to the recipient.</span>' +
					'</div>';
				$s.html(emptyHtml);
				return;
			}

			var existingCount = $s.find('.tpd-chat-bubble').length;
			var html = '';

			for (var i = 0; i < messages.length; i++) {
				var m = messages[i];
				var safeText = $('<div>').text(m.text || '').html();
				var safeTime = $('<div>').text(m.time || '').html();
				if (m.is_outbound) {
					html += '<div class="tpd-chat-bubble tpd-bubble-outbound" data-msg-id="' + m.id + '">' +
						'<div class="tpd-cb-text">' + safeText + '</div>' +
						'<div class="tpd-cb-time">' + safeTime + ' · Sent</div>' +
						'</div>';
				} else {
					var safeSender = $('<div>').text(m.sender_name || partnerName || 'Partner').html();
					html += '<div class="tpd-chat-bubble tpd-bubble-inbound" data-msg-id="' + m.id + '">' +
						'<div class="tpd-cb-sender">' + safeSender + '</div>' +
						'<div class="tpd-cb-text">' + safeText + '</div>' +
						'<div class="tpd-cb-time">' + safeTime + ' · Received</div>' +
						'</div>';
				}
			}

			if (!scrollIfChanged || messages.length !== existingCount) {
				$s.html(html);
				scrollToBottom(!scrollIfChanged);
			}
		}

		// Load a specific partner's thread from the database
		function loadChatThread(partnerId, silentPoll) {
			if (!partnerId || isLoadingThread) return;
			isLoadingThread = true;

			$.ajax({
				url: (typeof tpd_data !== 'undefined') ? tpd_data.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				data: {
					action: 'tpd_get_chat_thread',
					partner_id: partnerId,
					nonce: (typeof tpd_data !== 'undefined' && tpd_data.chat_nonce) ? tpd_data.chat_nonce : ''
				},
				success: function(res) {
					isLoadingThread = false;
					if (res && res.success && res.data) {
						$('#tpd-chat-recipient-id').val(res.data.partner_id);
						$('#tpd-chat-active-name').text(res.data.partner_name);
						if (res.data.partner_org) {
							$('#tpd-chat-active-org').text(res.data.partner_org);
						}
						if (res.data.partner_avatar) {
							$('#tpd-chat-active-avatar').attr('src', res.data.partner_avatar);
						}
						renderThreadMessages(res.data.messages || [], res.data.partner_name, !!silentPoll);
					}
				},
				error: function() {
					isLoadingThread = false;
				}
			});
		}

		// Click on a Contact Row in the Left Sidebar
		$(document).on('click', '.tpd-chat-contact-item', function(e) {
			e.preventDefault();
			var $item = $(this);
			var partnerId = $item.data('partner-id');
			var partnerName = $item.data('partner-name');
			var partnerOrg = $item.data('partner-org');
			var partnerAvatar = $item.data('partner-avatar');

			$('.tpd-chat-contact-item').removeClass('active').css({
				background: '#ffffff',
				borderLeftColor: 'transparent'
			});
			$item.addClass('active').css({
				background: '#eff6ff',
				borderLeftColor: '#2563eb'
			});
			$item.find('.tpd-unread-dot').remove();

			if (partnerId) {
				$('#tpd-chat-recipient-id').val(partnerId);
			}
			if (partnerName) {
				$('#tpd-chat-active-name').text(partnerName);
			}
			if (partnerOrg) {
				$('#tpd-chat-active-org').text(partnerOrg);
			}
			if (partnerAvatar) {
				$('#tpd-chat-active-avatar').attr('src', partnerAvatar);
			}

			loadChatThread(partnerId, false);
		});

		// Filter Contacts in Left Sidebar
		$(document).on('keyup', '#tpd-chat-contact-search', function() {
			var q = $(this).val().toLowerCase().trim();
			$('.tpd-chat-contact-item').each(function() {
				var txt = $(this).text().toLowerCase();
				if (!q || txt.indexOf(q) !== -1) {
					$(this).show();
				} else {
					$(this).hide();
				}
			});
		});

		// Handle Chat Form Submit
		$(document).on('submit', '#tpd-chat-composer-form', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $textarea = $form.find('#tpd-chat-message-input');
			var $btn = $form.find('#tpd-chat-submit-btn');
			var message = $textarea.val().trim();
			var recipientId = $('#tpd-chat-recipient-id').val() || 1;

			if (!message) {
				$textarea.focus();
				return;
			}

			var formData = {
				action: 'tpd_send_chat_message',
				recipient_id: recipientId,
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
						$('#tpd-chat-stream .tpd-chat-empty-state').remove();

						// Append outbound message bubble
						var bubbleHtml = '<div class="tpd-chat-bubble tpd-bubble-outbound">' +
							'<div class="tpd-cb-text">' + $('<div>').text(message).html() + '</div>' +
							'<div class="tpd-cb-time">' + (res.data.time || 'Just now') + ' · Delivered & Emailed</div>' +
							'</div>';

						$('#tpd-chat-stream').append(bubbleHtml);
						$textarea.val('');
						scrollToBottom(false);

						// Update snippet on the active contact item
						var $activeContact = $('.tpd-chat-contact-item[data-partner-id="' + recipientId + '"]');
						if ($activeContact.length) {
							$activeContact.find('.tpd-contact-snippet').text(message);
						}

						showChatNotice(res.data.message || 'Message saved & email alert sent!');
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

		// Trigger Chat with Specific Rep or Advisor (from Virtual Office, Recent Conversations, or Meeting Requests)
		$(document).on('click', '.tpd-open-chat-btn, .tpd-btn-chat-rep', function(e) {
			e.preventDefault();
			var repId = $(this).data('rep-id') || $(this).data('recipient-id') || $(this).data('user-id');
			var repName = $(this).data('rep-name') || $(this).data('recipient-name') || 'Partner Contact';

			// Switch to conversations / supp-chat view
			if (typeof window.tpdSwitchTab === 'function') {
				if ($('#tpd-supp-chat').length) {
					window.tpdSwitchTab('supp-chat');
				} else if ($('#tpd-view-conversations').length) {
					window.tpdSwitchTab('conversations');
				}
			} else if ($('.tpd-tab-link[data-view="conversations"]').length) {
				$('.tpd-tab-link[data-view="conversations"]').first().trigger('click');
			} else if ($('.tpd-tab-link[data-view="supp-chat"]').length) {
				$('.tpd-tab-link[data-view="supp-chat"]').first().trigger('click');
			}

			if (repId) {
				$('#tpd-chat-recipient-id').val(repId);
				var $matchingContact = $('.tpd-chat-contact-item[data-partner-id="' + repId + '"]');
				if ($matchingContact.length) {
					$matchingContact.first().trigger('click');
				} else {
					if (repName) {
						$('#tpd-chat-active-name').text(repName);
					}
					loadChatThread(repId, false);
				}
			} else if (repName) {
				$('#tpd-chat-active-name').text(repName);
			}

			$('#tpd-chat-message-input').focus();
		});

		// Live 5-Second Real-Time Polling while the Chat Box is visible
		setInterval(function() {
			var $chatBox = $('#tpd-chat-stream');
			if ($chatBox.length && $chatBox.is(':visible')) {
				var activePartnerId = $('#tpd-chat-recipient-id').val();
				if (activePartnerId) {
					loadChatThread(activePartnerId, true);
				}
			}
		}, 5000);

		function showChatNotice(msg) {
			var $s = $('#tpd-chat-stream');
			var $note = $('<div class="tpd-chat-notice-banner">' +
				'<i class="fa-solid fa-circle-check text-green"></i> ' + msg +
				'</div>');
			$s.append($note);
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
			scrollToBottom(false);
			setTimeout(function() {
				$note.fadeOut(400, function() { $(this).remove(); });
			}, 3500);
		}
	});

})(jQuery);

