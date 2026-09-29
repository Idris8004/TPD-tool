<?php
/**
 * Shared Real-Time Chat & Messaging Component for TPD Tool
 * Handles internal user chat and guest traveler messages with instant email notifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$recipient_id    = isset( $args['recipient_id'] ) ? absint( $args['recipient_id'] ) : 2;
$recipient_user  = get_userdata( $recipient_id );
$recipient_name  = $recipient_user ? $recipient_user->display_name : 'Representative';
?>
<div class="tpd-chat-messenger-box">
	<div class="tpd-cmb-header">
		<div class="tpd-cmb-user-info">
			<div class="tpd-cmb-avatar-dot">
				<img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&auto=format&fit=crop&q=80" alt="<?php echo esc_attr( $recipient_name ); ?>" class="tpd-cmb-avatar">
				<span class="tpd-online-dot"></span>
			</div>
			<div>
				<h4 id="tpd-chat-active-name"><?php echo esc_html( $recipient_name ); ?></h4>
				<span class="tpd-cmb-status"><i class="fa-solid fa-circle text-green"></i> Active on TPD Directory · Email Notifications Enabled</span>
			</div>
		</div>
	</div>

	<!-- Chat Message Stream -->
	<div class="tpd-cmb-stream" id="tpd-chat-stream">
		<!-- Sample Initial Thread -->
		<div class="tpd-chat-bubble tpd-bubble-inbound">
			<div class="tpd-cb-sender">Jennifer Lawson (Dream Vacations)</div>
			<div class="tpd-cb-text">Hello! I have a client inquiring about private charter options for Danube river cruises in late 2026. Do you have current group availability?</div>
			<div class="tpd-cb-time">10:42 AM · Received</div>
		</div>

		<div class="tpd-chat-bubble tpd-bubble-outbound">
			<div class="tpd-cb-text">Hi Jennifer! Yes, we have select charter dates open for September and October 2026 with full commission protection for advisors. Let me send over the charter packet.</div>
			<div class="tpd-cb-time">10:45 AM · Delivered</div>
		</div>
	</div>

	<!-- Message Input Form -->
	<form id="tpd-chat-composer-form" class="tpd-cmb-footer" method="post">
		<input type="hidden" name="recipient_id" id="tpd-chat-recipient-id" value="<?php echo esc_attr( $recipient_id ); ?>">

		<?php if ( ! $current_user_id ) : ?>
			<div class="tpd-guest-fields-row">
				<input type="text" name="guest_name" placeholder="Your Name" required class="tpd-input tpd-input-sm">
				<input type="email" name="guest_email" placeholder="Your Email (for advisor reply)" required class="tpd-input tpd-input-sm">
			</div>
		<?php endif; ?>

		<div class="tpd-cmb-input-row">
			<textarea name="message" id="tpd-chat-message-input" rows="2" placeholder="<?php esc_attr_e( 'Type your message... (recipient will receive instant dashboard message & email notification)', 'tpd-tool' ); ?>" class="tpd-chat-textarea" required></textarea>
			<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-chat-send-btn" id="tpd-chat-submit-btn">
				<i class="fa-solid fa-paper-plane"></i>
			</button>
		</div>
		<p class="tpd-chat-alert-note"><i class="fa-solid fa-bell"></i> <?php esc_html_e( 'Messages trigger immediate on-site alerts and formatted email notifications.', 'tpd-tool' ); ?></p>
	</form>
</div>
