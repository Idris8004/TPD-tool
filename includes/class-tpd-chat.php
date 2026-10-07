<?php
/**
 * Live Chat & Messaging Engine with Email Alerts for TPD Tool
 * Supports internal user-to-user chat and guest-to-advisor/supplier chat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Chat {

	public static function init() {
		add_action( 'wp_ajax_tpd_send_chat_message', array( __CLASS__, 'ajax_send_message' ) );
		add_action( 'wp_ajax_nopriv_tpd_send_chat_message', array( __CLASS__, 'ajax_send_message' ) );

		add_action( 'wp_ajax_tpd_get_chat_thread', array( __CLASS__, 'ajax_get_thread' ) );
		add_action( 'wp_ajax_tpd_mark_convo_read', array( __CLASS__, 'ajax_mark_read' ) );
	}

	/**
	 * Deterministic conversation ID between two user IDs
	 */
	public static function get_conversation_id( $user_a, $user_b ) {
		$pair = array( absint( $user_a ), absint( $user_b ) );
		sort( $pair );
		return 'conv_' . implode( '_', $pair );
	}

	/**
	 * Send Chat Message & Dispatch Instant Email Notification
	 */
	public static function ajax_send_message() {
		check_ajax_referer( 'tpd_chat_nonce', 'nonce' );

		$recipient_id = isset( $_POST['recipient_id'] ) ? absint( $_POST['recipient_id'] ) : 0;
		$message_text = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $recipient_id || empty( $message_text ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing recipient or message text.', 'tpd-tool' ) ) );
		}

		$current_user_id = get_current_user_id();

		if ( $current_user_id ) {
			$sender       = wp_get_current_user();
			$sender_name  = $sender->display_name ?: $sender->user_login;
			$sender_email = $sender->user_email;
		} else {
			$sender_name  = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : 'Guest Traveler';
			$sender_email = isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
		}

		$recipient = get_userdata( $recipient_id );
		if ( ! $recipient ) {
			wp_send_json_error( array( 'message' => __( 'Recipient user not found.', 'tpd-tool' ) ) );
		}

		// Create Message Post Record
		$post_id = wp_insert_post( array(
			'post_type'    => 'tpd_message',
			'post_status'  => 'publish',
			'post_title'   => sprintf( 'Chat: %s to %s', $sender_name, $recipient->display_name ),
			'post_content' => $message_text,
			'post_author'  => $current_user_id ?: 1,
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save message.', 'tpd-tool' ) ) );
		}

		// Generate conversation key (deterministic between 2 users or with guest)
		if ( $current_user_id ) {
			$conversation_id = self::get_conversation_id( $current_user_id, $recipient_id );
		} else {
			$conversation_id = 'conv_guest_' . md5( $sender_email . $recipient_id );
		}

		$now_ts = current_time( 'timestamp' );

		update_post_meta( $post_id, '_tpd_sender_id', $current_user_id );
		update_post_meta( $post_id, '_tpd_sender_name', $sender_name );
		update_post_meta( $post_id, '_tpd_sender_email', $sender_email );
		update_post_meta( $post_id, '_tpd_recipient_id', $recipient_id );
		update_post_meta( $post_id, '_tpd_conversation_id', $conversation_id );
		update_post_meta( $post_id, '_tpd_is_read', 0 );
		update_post_meta( $post_id, '_tpd_timestamp', $now_ts );

		// Dispatch Email Alert to the recipient
		self::send_chat_email_alert( $recipient, $sender_name, $sender_email, $message_text );

		wp_send_json_success( array(
			'message'         => __( 'Message sent!', 'tpd-tool' ),
			'message_id'      => $post_id,
			'conversation_id' => $conversation_id,
			'sender_id'       => $current_user_id,
			'sender_name'     => $sender_name,
			'text'            => esc_html( $message_text ),
			'time'            => date_i18n( 'g:i A', $now_ts ),
		) );
	}

	/**
	 * Fetch real message history between current user and selected partner_id
	 */
	public static function ajax_get_thread() {
		check_ajax_referer( 'tpd_chat_nonce', 'nonce' );

		$current_user_id = get_current_user_id();
		$partner_id      = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : 0;

		if ( ! $current_user_id || ! $partner_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid conversation parameters.', 'tpd-tool' ) ) );
		}

		$partner_user = get_userdata( $partner_id );
		$partner_name = $partner_user ? ( $partner_user->display_name ?: $partner_user->user_login ) : 'Partner Contact';
		$partner_role = self::get_user_organization_label( $partner_id );
		$partner_avatar = get_user_meta( $partner_id, 'tpd_avatar_url', true );
		if ( empty( $partner_avatar ) ) {
			$partner_avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode( $partner_name ) . '&background=00798c&color=fff&size=120';
		}

		$messages = self::get_thread_messages( $current_user_id, $partner_id );

		// Mark incoming messages in this thread as read
		$conv_id = self::get_conversation_id( $current_user_id, $partner_id );
		self::mark_conversation_read( $conv_id, $current_user_id );

		wp_send_json_success( array(
			'partner_id'     => $partner_id,
			'partner_name'   => $partner_name,
			'partner_org'    => $partner_role,
			'partner_avatar' => $partner_avatar,
			'messages'       => $messages,
		) );
	}

	/**
	 * Mark conversation read via AJAX
	 */
	public static function ajax_mark_read() {
		check_ajax_referer( 'tpd_chat_nonce', 'nonce' );
		$current_user_id = get_current_user_id();
		$partner_id      = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : 0;
		if ( $current_user_id && $partner_id ) {
			$conv_id = self::get_conversation_id( $current_user_id, $partner_id );
			self::mark_conversation_read( $conv_id, $current_user_id );
		}
		wp_send_json_success();
	}

	/**
	 * Mark all unread messages sent to $user_id in $conversation_id as read
	 */
	public static function mark_conversation_read( $conversation_id, $user_id ) {
		$unread = get_posts( array(
			'post_type'      => 'tpd_message',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_tpd_conversation_id',
					'value'   => $conversation_id,
					'compare' => '=',
				),
				array(
					'key'     => '_tpd_recipient_id',
					'value'   => $user_id,
					'compare' => '=',
				),
				array(
					'key'     => '_tpd_is_read',
					'value'   => 0,
					'compare' => '=',
				),
			),
		) );

		foreach ( $unread as $msg_id ) {
			update_post_meta( $msg_id, '_tpd_is_read', 1 );
		}
	}

	/**
	 * Get ordered message array between two users
	 */
	public static function get_thread_messages( $current_user_id, $partner_id, $limit = 80 ) {
		if ( ! $current_user_id || ! $partner_id ) {
			return array();
		}

		$conv_id = self::get_conversation_id( $current_user_id, $partner_id );

		$posts = get_posts( array(
			'post_type'      => 'tpd_message',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_tpd_conversation_id',
					'value'   => $conv_id,
					'compare' => '=',
				),
			),
		) );

		$result = array();
		foreach ( $posts as $p ) {
			$sender_id   = (int) get_post_meta( $p->ID, '_tpd_sender_id', true );
			$sender_name = get_post_meta( $p->ID, '_tpd_sender_name', true ) ?: 'User';
			$ts          = (int) get_post_meta( $p->ID, '_tpd_timestamp', true );
			$time_label  = $ts ? date_i18n( 'M j, g:i A', $ts ) : get_the_date( 'M j, g:i A', $p );

			$result[] = array(
				'id'          => $p->ID,
				'sender_id'   => $sender_id,
				'sender_name' => $sender_name,
				'is_outbound' => ( $sender_id === (int) $current_user_id ),
				'text'        => wp_strip_all_tags( $p->post_content ),
				'time'        => $time_label,
			);
		}

		return $result;
	}

	/**
	 * Helper: Get user's company or agency label
	 */
	public static function get_user_organization_label( $user_id ) {
		$company = get_user_meta( $user_id, 'tpd_company_name', true );
		if ( ! empty( $company ) ) {
			return $company;
		}
		$agency = get_user_meta( $user_id, 'tpd_agency_name', true );
		if ( ! empty( $agency ) ) {
			return $agency;
		}
		$u = get_userdata( $user_id );
		if ( $u && in_array( 'supplier', (array) $u->roles, true ) ) {
			return 'Verified Supplier Partner';
		}
		if ( $u && in_array( 'travel_advisor', (array) $u->roles, true ) ) {
			return 'Verified Travel Advisor';
		}
		return 'TARC Member';
	}

	/**
	 * Get real contacts list for the Chat sidebar (Suppliers for Advisors, Advisors for Suppliers, plus any active threads)
	 */
	public static function get_chat_contacts_for_user( $user_id ) {
		$contacts = array();
		$seen_ids = array( (int) $user_id );

		// 1. First, pull partners from existing message threads involving $user_id
		if ( $user_id ) {
			$messages = get_posts( array(
				'post_type'      => 'tpd_message',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_tpd_recipient_id',
						'value'   => $user_id,
						'compare' => '=',
					),
					array(
						'key'     => '_tpd_sender_id',
						'value'   => $user_id,
						'compare' => '=',
					),
				),
			) );

			foreach ( $messages as $m ) {
				$s_id     = (int) get_post_meta( $m->ID, '_tpd_sender_id', true );
				$r_id     = (int) get_post_meta( $m->ID, '_tpd_recipient_id', true );
				$other_id = ( $s_id === (int) $user_id ) ? $r_id : $s_id;

				if ( ! $other_id || in_array( $other_id, $seen_ids, true ) ) {
					continue;
				}
				$other_user = get_userdata( $other_id );
				if ( ! $other_user ) {
					continue;
				}

				$seen_ids[] = $other_id;
				$name       = $other_user->display_name ?: $other_user->user_login;
				$avatar     = get_user_meta( $other_id, 'tpd_avatar_url', true );
				if ( empty( $avatar ) ) {
					$avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode( $name ) . '&background=00798c&color=fff&size=120';
				}
				$ts      = (int) get_post_meta( $m->ID, '_tpd_timestamp', true );
				$is_read = (int) get_post_meta( $m->ID, '_tpd_is_read', true );

				$contacts[] = array(
					'rep_id'  => $other_id,
					'name'    => $name,
					'agency'  => self::get_user_organization_label( $other_id ),
					'avatar'  => $avatar,
					'snippet' => wp_trim_words( wp_strip_all_tags( $m->post_content ), 10, '...' ),
					'time'    => $ts ? human_time_diff( $ts, current_time( 'timestamp' ) ) . ' ago' : 'Recent',
					'unread'  => ( $r_id === (int) $user_id && 0 === $is_read ),
				);
			}
		}

		// 2. Also include registered cross-portal users so the user can message any registered Advisor or Supplier immediately
		$is_supplier = class_exists( 'TPD_Tool_Roles' ) && TPD_Tool_Roles::is_supplier( $user_id ) && ! TPD_Tool_Roles::is_advisor( $user_id );
		$target_roles = $is_supplier ? array( 'travel_advisor', 'supplier', 'administrator' ) : array( 'supplier', 'travel_advisor', 'administrator' );

		$users = get_users( array(
			'role__in' => $target_roles,
			'number'   => 25,
			'orderby'  => 'registered',
			'order'    => 'DESC',
		) );

		foreach ( $users as $u ) {
			if ( in_array( (int) $u->ID, $seen_ids, true ) ) {
				continue;
			}
			$seen_ids[] = (int) $u->ID;
			$name       = $u->display_name ?: $u->user_login;
			$avatar     = get_user_meta( $u->ID, 'tpd_avatar_url', true );
			if ( empty( $avatar ) ) {
				$avatar = 'https://ui-avatars.com/api/?name=' . rawurlencode( $name ) . '&background=1d4ed8&color=fff&size=120';
			}

			$contacts[] = array(
				'rep_id'  => (int) $u->ID,
				'name'    => $name,
				'agency'  => self::get_user_organization_label( $u->ID ),
				'avatar'  => $avatar,
				'snippet' => 'Click to start a direct conversation',
				'time'    => 'Online',
				'unread'  => false,
			);
		}

		return $contacts;
	}

	/**
	 * Send Email Notification with Reply Link
	 */
	public static function send_chat_email_alert( $recipient, $sender_name, $sender_email, $message_text ) {
		$recipient_email = $recipient->user_email;
		if ( ! is_email( $recipient_email ) ) {
			return;
		}

		$is_advisor = in_array( 'travel_advisor', (array) $recipient->roles, true );
		$reply_url  = $is_advisor ? home_url( '/advisor-dashboard/?tab=conversations' ) : home_url( '/supplier-dashboard/?tab=supp-chat' );

		$subject = sprintf( '[TPD Chat] New message from %s on Travel Partner Directory', $sender_name );

		$body  = "Hi " . $recipient->display_name . ",\n\n";
		$body .= "You have received a new message on Travel Partner Directory from " . $sender_name . ":\n\n";
		$body .= "--------------------------------------------------\n";
		$body .= '"' . $message_text . "\"\n";
		$body .= "--------------------------------------------------\n\n";
		$body .= "To view the full conversation and reply, log in to your portal here:\n";
		$body .= $reply_url . "\n\n";
		$body .= "Travel Partner Directory by TARC\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( ! empty( $sender_email ) && is_email( $sender_email ) ) {
			$headers[] = 'Reply-To: ' . $sender_name . ' <' . $sender_email . '>';
		}

		wp_mail( $recipient_email, $subject, $body, $headers );
	}

	/**
	 * Get unread messages count for a user
	 */
	public static function get_unread_count_for_user( $user_id ) {
		if ( ! $user_id ) {
			return 0;
		}

		$query = new WP_Query( array(
			'post_type'      => 'tpd_message',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_tpd_recipient_id',
					'value'   => $user_id,
					'compare' => '=',
				),
				array(
					'key'     => '_tpd_is_read',
					'value'   => 0,
					'compare' => '=',
				),
			),
		) );

		return (int) $query->found_posts;
	}

	/**
	 * Get total conversation threads count for a user
	 */
	public static function get_total_conversations_count_for_user( $user_id ) {
		if ( ! $user_id ) {
			return 0;
		}

		$messages = get_posts( array(
			'post_type'      => 'tpd_message',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_tpd_recipient_id',
					'value'   => $user_id,
					'compare' => '=',
				),
				array(
					'key'     => '_tpd_sender_id',
					'value'   => $user_id,
					'compare' => '=',
				),
			),
		) );

		$unique_convos = array();
		foreach ( $messages as $m ) {
			$c_id = get_post_meta( $m->ID, '_tpd_conversation_id', true );
			if ( $c_id ) {
				$unique_convos[ $c_id ] = true;
			}
		}

		return count( $unique_convos );
	}

	/**
	 * Get Recent Conversations for a user (pulls real DB threads first, then active portal members)
	 */
	public static function get_recent_conversations( $user_id, $limit = 5 ) {
		$contacts = self::get_chat_contacts_for_user( $user_id );
		if ( ! empty( $contacts ) ) {
			return array_slice( $contacts, 0, $limit );
		}

		return array();
	}
}

TPD_Tool_Chat::init();

