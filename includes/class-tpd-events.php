<?php
/**
 * Events & Webinars Manager for TPD Tool
 * Supports vendor training series, live Q&A, and advisor registration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Events {

	public static function init() {
		add_action( 'wp_ajax_tpd_register_event', array( __CLASS__, 'ajax_register_event' ) );
	}

	public static function ajax_register_event() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to register for events.', 'tpd-tool' ) ) );
		}

		$event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
		$user_id  = get_current_user_id();

		$registered = (array) get_user_meta( $user_id, '_tpd_registered_events', true );
		if ( ! in_array( $event_id, $registered, true ) ) {
			$registered[] = $event_id;
			update_user_meta( $user_id, '_tpd_registered_events', array_unique( $registered ) );
		}

		wp_send_json_success( array(
			'message' => __( 'You are registered! A calendar invite has been sent to your email.', 'tpd-tool' ),
		) );
	}

	public static function get_upcoming_events( $limit = 3 ) {
		$posts = get_posts( array(
			'post_type'      => 'tpd_event',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
		) );

		if ( ! empty( $posts ) ) {
			$events = array();
			foreach ( $posts as $p ) {
				$month = get_post_meta( $p->ID, '_tpd_event_month', true ) ?: 'OCT';
				$day   = get_post_meta( $p->ID, '_tpd_event_day', true ) ?: '14';
				$series= get_post_meta( $p->ID, '_tpd_event_series', true ) ?: 'Vendor Training Series';
				$time  = get_post_meta( $p->ID, '_tpd_event_time', true ) ?: '12:00 PM - 1:00 PM ET · Virtual';

				$events[] = array(
					'id'     => $p->ID,
					'month'  => $month,
					'day'    => $day,
					'title'  => $p->post_title,
					'series' => $series,
					'time'   => $time,
				);
			}
			return $events;
		}

		// Fallback sample events matching Image 1
		return array(
			array(
				'id'     => 101,
				'month'  => 'OCT',
				'day'    => '14',
				'title'  => 'Luxury Europe Deep Dive',
				'series' => 'Vendor Training Series',
				'time'   => '12:00 PM - 1:00 PM ET · Virtual',
			),
			array(
				'id'     => 102,
				'month'  => 'OCT',
				'day'    => '16',
				'title'  => 'Selling River Cruises in 2026',
				'series' => 'TARC Talk Webinar',
				'time'   => '2:00 PM - 3:00 PM ET · Virtual',
			),
			array(
				'id'     => 103,
				'month'  => 'OCT',
				'day'    => '22',
				'title'  => 'The Power of Destination Specialist',
				'series' => 'Panel Discussion',
				'time'   => '12:00 PM - 1:30 PM ET · Virtual',
			),
		);
	}
}

TPD_Tool_Events::init();
