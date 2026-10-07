<?php
/**
 * Shared Real-Time Chat & Messaging Component for TPD Tool
 * 100% Database-Backed Messenger (No Third-Party Paid Service Required)
 * Supports internal Advisor <-> Supplier chat, live AJAX thread switching, 5s polling, and email alerts.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
$contacts        = class_exists( 'TPD_Tool_Chat' ) ? TPD_Tool_Chat::get_chat_contacts_for_user( $current_user_id ) : array();

$recipient_id = isset( $args['recipient_id'] ) ? absint( $args['recipient_id'] ) : 0;
if ( ! $recipient_id && ! empty( $contacts ) ) {
	$recipient_id = (int) $contacts[0]['rep_id'];
}
if ( ! $recipient_id ) {
	$recipient_id = 1;
}

$recipient_user   = get_userdata( $recipient_id );
$recipient_name   = $recipient_user ? ( $recipient_user->display_name ?: $recipient_user->user_login ) : 'Partner Contact';
$recipient_org    = class_exists( 'TPD_Tool_Chat' ) ? TPD_Tool_Chat::get_user_organization_label( $recipient_id ) : 'Verified Partner';
$recipient_avatar = get_user_meta( $recipient_id, 'tpd_avatar_url', true );
if ( empty( $recipient_avatar ) ) {
	$recipient_avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode( $recipient_name ) . '&background=00798c&color=fff&size=120';
}

$initial_messages = ( $current_user_id && class_exists( 'TPD_Tool_Chat' ) )
	? TPD_Tool_Chat::get_thread_messages( $current_user_id, $recipient_id )
	: array();
?>
<div class="tpd-chat-workspace-grid" style="display:grid; grid-template-columns: 320px 1fr; gap:20px; align-items:stretch; min-height:580px;">

	<!-- LEFT COLUMN: REAL CONTACTS & CONVERSATION THREADS -->
	<div class="tpd-card-box" style="padding:0; display:flex; flex-direction:column; overflow:hidden; border:1px solid #e2e8f0;">
		<div style="padding:18px 18px 14px; border-bottom:1px solid #e2e8f0; background:#0b1526; color:#ffffff;">
			<h4 style="margin:0 0 10px; font-size:15px; font-weight:800; color:#ffffff; display:flex; align-items:center; justify-content:space-between;">
				<span><i class="fa-regular fa-comments text-gold"></i> <?php esc_html_e( 'Direct Messages', 'tpd-tool' ); ?></span>
				<span style="font-size:11px; background:rgba(255,255,255,0.12); padding:3px 9px; border-radius:999px; font-weight:600;"><?php echo count( $contacts ); ?> <?php esc_html_e( 'Contacts', 'tpd-tool' ); ?></span>
			</h4>
			<input type="text" id="tpd-chat-contact-search" placeholder="<?php esc_attr_e( 'Search advisors or suppliers...', 'tpd-tool' ); ?>" style="width:100%; padding:8px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.2); background:rgba(255,255,255,0.08); color:#ffffff; font-size:12.5px; outline:none;">
		</div>

		<div id="tpd-chat-contacts-list" style="flex:1; overflow-y:auto; max-height:500px; divide-y:1px solid #f1f5f9;">
			<?php if ( ! empty( $contacts ) ) : ?>
				<?php foreach ( $contacts as $idx => $c ) :
					$is_active = ( (int) $c['rep_id'] === (int) $recipient_id );
				?>
					<div class="tpd-chat-contact-item <?php echo $is_active ? 'active' : ''; ?>"
						data-partner-id="<?php echo esc_attr( $c['rep_id'] ); ?>"
						data-partner-name="<?php echo esc_attr( $c['name'] ); ?>"
						data-partner-org="<?php echo esc_attr( $c['agency'] ); ?>"
						data-partner-avatar="<?php echo esc_url( $c['avatar'] ); ?>"
						style="display:flex; align-items:center; gap:12px; padding:13px 16px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background 0.15s; background:<?php echo $is_active ? '#eff6ff' : '#ffffff'; ?>; border-left:4px solid <?php echo $is_active ? '#2563eb' : 'transparent'; ?>;">
						<div style="position:relative; flex-shrink:0;">
							<img src="<?php echo esc_url( $c['avatar'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" style="width:42px; height:42px; border-radius:50%; object-fit:cover;">
							<?php if ( ! empty( $c['unread'] ) ) : ?>
								<span class="tpd-unread-dot" style="position:absolute; top:0; right:0; width:10px; height:10px; border-radius:50%; background:#ef4444; border:2px solid #ffffff;"></span>
							<?php endif; ?>
						</div>
						<div style="flex:1; min-width:0;">
							<div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
								<strong style="font-size:13.5px; color:#0b1526; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $c['name'] ); ?></strong>
								<span style="font-size:11px; color:#64748b; flex-shrink:0;"><?php echo esc_html( $c['time'] ); ?></span>
							</div>
							<div style="font-size:11.5px; color:#00798c; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html( $c['agency'] ); ?></div>
							<div class="tpd-contact-snippet" style="font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;"><?php echo esc_html( $c['snippet'] ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			<?php else : ?>
				<div style="padding:24px; text-align:center; color:#64748b; font-size:13px;">
					<?php esc_html_e( 'No registered contacts found yet.', 'tpd-tool' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- RIGHT COLUMN: ACTIVE CONVERSATION STREAM -->
	<div class="tpd-chat-messenger-box" style="display:flex; flex-direction:column; height:100%;">
		<div class="tpd-cmb-header">
			<div class="tpd-cmb-user-info">
				<div class="tpd-cmb-avatar-dot">
					<img id="tpd-chat-active-avatar" src="<?php echo esc_url( $recipient_avatar ); ?>" alt="<?php echo esc_attr( $recipient_name ); ?>" class="tpd-cmb-avatar">
					<span class="tpd-online-dot"></span>
				</div>
				<div>
					<h4 id="tpd-chat-active-name" style="margin:0;"><?php echo esc_html( $recipient_name ); ?></h4>
					<span class="tpd-cmb-status"><i class="fa-solid fa-circle text-green"></i> <span id="tpd-chat-active-org"><?php echo esc_html( $recipient_org ); ?></span> · <?php esc_html_e( 'Live Portal Chat & Email Alerts Active', 'tpd-tool' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Chat Message Stream -->
		<div class="tpd-cmb-stream" id="tpd-chat-stream" style="flex:1; min-height:360px; max-height:420px; overflow-y:auto;">
			<?php if ( ! empty( $initial_messages ) ) : ?>
				<?php foreach ( $initial_messages as $msg ) : ?>
					<?php if ( $msg['is_outbound'] ) : ?>
						<div class="tpd-chat-bubble tpd-bubble-outbound" data-msg-id="<?php echo esc_attr( $msg['id'] ); ?>">
							<div class="tpd-cb-text"><?php echo esc_html( $msg['text'] ); ?></div>
							<div class="tpd-cb-time"><?php echo esc_html( $msg['time'] ); ?> · <?php esc_html_e( 'Sent', 'tpd-tool' ); ?></div>
						</div>
					<?php else : ?>
						<div class="tpd-chat-bubble tpd-bubble-inbound" data-msg-id="<?php echo esc_attr( $msg['id'] ); ?>">
							<div class="tpd-cb-sender"><?php echo esc_html( $msg['sender_name'] ); ?></div>
							<div class="tpd-cb-text"><?php echo esc_html( $msg['text'] ); ?></div>
							<div class="tpd-cb-time"><?php echo esc_html( $msg['time'] ); ?> · <?php esc_html_e( 'Received', 'tpd-tool' ); ?></div>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="tpd-chat-empty-state" style="text-align:center; padding:48px 20px; color:#64748b;">
					<i class="fa-regular fa-paper-plane" style="font-size:32px; color:#94a3b8; margin-bottom:10px; display:block;"></i>
					<strong style="color:#0b1526; display:block; margin-bottom:4px;"><?php printf( esc_html__( 'Start a conversation with %s', 'tpd-tool' ), esc_html( $recipient_name ) ); ?></strong>
					<span style="font-size:12.5px;"><?php esc_html_e( 'Messages are saved directly to your portal inbox and trigger an instant email alert to the recipient.', 'tpd-tool' ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<!-- Message Input Form -->
		<form id="tpd-chat-composer-form" class="tpd-cmb-footer" method="post">
			<input type="hidden" name="recipient_id" id="tpd-chat-recipient-id" value="<?php echo esc_attr( $recipient_id ); ?>">

			<?php if ( ! $current_user_id ) : ?>
				<div class="tpd-guest-fields-row">
					<input type="text" name="guest_name" placeholder="Your Name" required class="tpd-input tpd-input-sm">
					<input type="email" name="guest_email" placeholder="Your Email (for reply)" required class="tpd-input tpd-input-sm">
				</div>
			<?php endif; ?>

			<div class="tpd-cmb-input-row">
				<textarea name="message" id="tpd-chat-message-input" rows="2" placeholder="<?php esc_attr_e( 'Type your message... (Press Enter to send, Shift+Enter for new line)', 'tpd-tool' ); ?>" class="tpd-chat-textarea" required></textarea>
				<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-chat-send-btn" id="tpd-chat-submit-btn">
					<i class="fa-solid fa-paper-plane"></i>
				</button>
			</div>
			<p class="tpd-chat-alert-note"><i class="fa-solid fa-bolt text-gold"></i> <?php esc_html_e( 'Real-time database sync enabled (auto-refreshes every 5s + dispatches instant email notification).', 'tpd-tool' ); ?></p>
		</form>
	</div>
</div>

