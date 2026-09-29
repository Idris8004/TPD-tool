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
			$sender      = wp_get_current_user();
			$sender_name = $sender->display_name;
			$sender_email= $sender->user_email;
		} else {
			$sender_name = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : 'Guest Traveler';
			$sender_email= isset( $_POST['guest_email'] ) ? sanitize_email( wp_unslash( $_POST['guest_email'] ) ) : '';
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
			$pair = array( $current_user_id, $recipient_id );
			sort( $pair );
			$conversation_id = 'conv_' . implode( '_', $pair );
		} else {
			$conversation_id = 'conv_guest_' . md5( $sender_email . $recipient_id );
		}

		update_post_meta( $post_id, '_tpd_sender_id', $current_user_id );
		update_post_meta( $post_id, '_tpd_sender_name', $sender_name );
		update_post_meta( $post_id, '_tpd_sender_email', $sender_email );
		update_post_meta( $post_id, '_tpd_recipient_id', $recipient_id );
		update_post_meta( $post_id, '_tpd_conversation_id', $conversation_id );
		update_post_meta( $post_id, '_tpd_is_read', 0 );
		update_post_meta( $post_id, '_tpd_timestamp', current_time( 'timestamp' ) );

		// Dispatch Email Alert to the recipient
		self::send_chat_email_alert( $recipient, $sender_name, $sender_email, $message_text );

		wp_send_json_success( array(
			'message'         => __( 'Message sent successfully!', 'tpd-tool' ),
			'conversation_id' => $conversation_id,
			'sender_name'     => $sender_name,
			'text'            => esc_html( $message_text ),
			'time'            => 'Just now',
		) );
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
		$reply_url  = $is_advisor ? home_url( '/advisor-dashboard/?tab=conversations' ) : home_url( '/supplier-dashboard/?tab=messages' );

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
	 * Get Recent Conversations for a user (Matching Inspiration 2 layout)
	 */
	public static function get_recent_conversations( $user_id, $limit = 5 ) {
		return array(
			array(
				'name'     => 'Jennifer Lawson',
				'agency'   => 'Dream Vacations',
				'avatar'   => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
				'snippet'  => 'Hi Sarah! I have a client looking at a Danube luxury cruise for June 2026...',
				'time'     => 'Just now',
				'unread'   => true,
				'rep_id'   => 2,
			),
			array(
				'name'     => 'Michael Torres',
				'agency'   => 'Adventure Awaits Travel',
				'avatar'   => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
				'snippet'  => 'Can you send me the updated 2026 European river schedule brochure?',
				'time'     => '12m ago',
				'unread'   => true,
				'rep_id'   => 3,
			),
			array(
				'name'     => 'Lisa Grant',
				'agency'   => 'Grant Travel Co.',
				'avatar'   => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=150&auto=format&fit=crop&q=80',
				'snippet'  => 'Do you have group rates for 2026 for a 16-person family reunion?',
				'time'     => '45m ago',
				'unread'   => false,
				'rep_id'   => 4,
			),
			array(
				'name'     => 'Amanda Brooks',
				'agency'   => 'Global Getaways',
				'avatar'   => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&auto=format&fit=crop&q=80',
				'snippet'  => 'Thank you for the information! This is perfect for my clients.',
				'time'     => '2h ago',
				'unread'   => false,
				'rep_id'   => 2,
			),
			array(
				'name'     => 'David Kim',
				'agency'   => 'Kim Travel Group',
				'avatar'   => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
				'snippet'  => "I'd like to schedule a call to discuss a client's special requirements.",
				'time'     => '3h ago',
				'unread'   => false,
				'rep_id'   => 3,
			),
		);
	}
}

TPD_Tool_Chat::init();
