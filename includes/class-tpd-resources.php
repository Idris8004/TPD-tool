<?php
/**
 * TPD Resource Library: sales toolkits and downloadable resources shown in
 * Resources & Tools, sold through the TPD checkout or included with a plan.
 *
 * Courses are handled by the native LMS module (includes/lms). This class used
 * to live in class-tpd-lms.php next to the external Laravel LMS bridge, which
 * has been removed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Resources {

	const OPT_RESOURCES = 'tpd_custom_resources';

	/** Settings of the removed external LMS bridge (URL, SSO and API secrets). */
	const OPT_OLD_BRIDGE = 'tpd_lms_settings';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 6 );

		add_action( 'wp_ajax_tpd_get_resources_data', array( __CLASS__, 'ajax_get_resources_data' ) );
		add_action( 'wp_ajax_tpd_admin_save_custom_resource', array( __CLASS__, 'ajax_admin_save_custom_resource' ) );
		add_action( 'wp_ajax_tpd_admin_delete_custom_resource', array( __CLASS__, 'ajax_admin_delete_custom_resource' ) );
	}

	/**
	 * Starter resources on a fresh site, and removal of the old bridge's stored secrets.
	 */
	public static function ensure_defaults() {
		if ( false !== get_option( self::OPT_OLD_BRIDGE, false ) ) {
			delete_option( self::OPT_OLD_BRIDGE );
		}

		$resources = get_option( self::OPT_RESOURCES );
		if ( false === $resources || ! is_array( $resources ) ) {
			$resources = array(
				'res_river_charter_2026' => array(
					'id'            => 'res_river_charter_2026',
					'title'         => '2026 Luxury River Cruise Charter & Commission Guide',
					'category'      => 'Commission Guide',
					'description'   => 'Complete commission protection tiers, charter group policy matrix, and seasonal advisor bonus schedules.',
					'price'         => 0.00,
					'file_url'      => '#tpd-view-suppliers',
					'thumbnail_url' => 'https://images.unsplash.com/photo-1548574505-5e239809ee19?w=600&auto=format&fit=crop&q=80',
					'tier_included' => 'all',
					'status'        => 'published',
					'created_at'    => current_time( 'mysql' ),
				),
				'res_resort_amenities_2026' => array(
					'id'            => 'res_resort_amenities_2026',
					'title'         => 'Preferred Resort Amenities & VIP Client Perks Directory',
					'category'      => 'Sales Toolkit',
					'description'   => 'VIP client perks, complimentary breakfast & spa credits, upgrade priority codes, and BDM escalation desks.',
					'price'         => 0.00,
					'file_url'      => '#tpd-view-suppliers',
					'thumbnail_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&auto=format&fit=crop&q=80',
					'tier_included' => 'all',
					'status'        => 'published',
					'created_at'    => current_time( 'mysql' ),
				),
				'res_social_media_kit' => array(
					'id'            => 'res_social_media_kit',
					'title'         => 'Luxury Group Booking Proposal & Contract Bundle',
					'category'      => 'Marketing Asset',
					'description'   => 'Ready-to-brand advisor client proposal decks, T&C checklists, and itinerary presentation templates.',
					'price'         => 29.00,
					'file_url'      => '#tpd-view-suppliers',
					'thumbnail_url' => 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?w=600&auto=format&fit=crop&q=80',
					'tier_included' => 'premium',
					'status'        => 'published',
					'created_at'    => current_time( 'mysql' ),
				),
			);
			update_option( self::OPT_RESOURCES, $resources );
		}
	}

	/**
	 * Retrieve TPD-managed custom resources & toolkits.
	 */
	public static function get_custom_resources( $include_drafts = false ) {
		self::ensure_defaults();
		$all = (array) get_option( self::OPT_RESOURCES, array() );
		if ( $include_drafts ) {
			return $all;
		}
		return array_filter( $all, function( $item ) {
			return ! isset( $item['status'] ) || 'published' === $item['status'];
		} );
	}

	/**
	 * Check whether user has unlocked a TPD resource (purchased, free, or tier-included).
	 */
	public static function user_has_resource_access( $user_id, $resource ) {
		if ( ! is_array( $resource ) || empty( $resource['id'] ) ) {
			return false;
		}
		$price = isset( $resource['price'] ) ? (float) $resource['price'] : 0.0;
		if ( $price <= 0 ) {
			return true;
		}

		// Tier-included unlock
		$tier_inc = isset( $resource['tier_included'] ) ? (string) $resource['tier_included'] : 'none';
		$user_tier = function_exists( 'tpd_get_user_tier' ) ? tpd_get_user_tier( $user_id ) : 'basic';
		if ( 'all' === $tier_inc ) {
			return true;
		}
		if ( 'standard' === $tier_inc && in_array( $user_tier, array( 'standard', 'premium' ), true ) ) {
			return true;
		}
		if ( 'premium' === $tier_inc && 'premium' === $user_tier ) {
			return true;
		}

		$unlocked = (array) get_user_meta( $user_id, 'tpd_unlocked_resources', true );
		return in_array( (string) $resource['id'], $unlocked, true ) || isset( $unlocked[ (string) $resource['id'] ] );
	}

	/**
	 * Unlock a TPD custom resource for a user.
	 */
	public static function unlock_custom_resource( $user_id, $resource_id ) {
		$unlocked = (array) get_user_meta( $user_id, 'tpd_unlocked_resources', true );
		$unlocked = array_filter( $unlocked );
		if ( ! in_array( (string) $resource_id, $unlocked, true ) ) {
			$unlocked[] = (string) $resource_id;
		}
		update_user_meta( $user_id, 'tpd_unlocked_resources', array_values( $unlocked ) );
	}

	/**
	 * AJAX: Resources & Tools data for the logged-in user (courses come from the native LMS).
	 */
	public static function ajax_get_resources_data() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$native      = class_exists( 'TPD_LMS_Native' ) && TPD_LMS_Native::is_enabled();
		$catalog     = $native ? TPD_LMS_Student::hub_catalog( $user_id ) : array();
		$enrollments = $native ? TPD_LMS_Student::hub_enrollments( $user_id ) : array();
		$resources   = array_values( self::get_custom_resources( false ) );

		$enrolled_map = array();
		foreach ( $enrollments as $enr ) {
			$slug = (string) ( $enr['course_slug'] ?? '' );
			if ( '' !== $slug ) {
				$enrolled_map[ $slug ] = $enr;
			}
		}

		foreach ( $resources as &$res ) {
			$has_access        = self::user_has_resource_access( $user_id, $res );
			$res['has_access'] = $has_access;
			if ( ! $has_access ) {
				$res['file_url'] = '';
			}
		}
		unset( $res );

		wp_send_json_success( array(
			'configured'   => $native,
			'catalog'      => $catalog,
			'enrollments'  => $enrollments,
			'enrolled_map' => $enrolled_map,
			'resources'    => $resources,
		) );
	}

	/**
	 * AJAX (Admin): Create or update a TPD Custom Resource / Toolkit.
	 */
	public static function ajax_admin_save_custom_resource() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$res_id = isset( $_POST['resource_id'] ) ? sanitize_key( wp_unslash( $_POST['resource_id'] ) ) : '';
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $title ) {
			wp_send_json_error( array( 'message' => __( 'Resource title is required.', 'tpd-tool' ) ) );
		}

		$resources = self::get_custom_resources( true );
		if ( '' === $res_id ) {
			$res_id = 'res_' . sanitize_key( substr( sanitize_title( $title ), 0, 20 ) ) . '_' . wp_rand( 100, 999 );
		}

		$price = isset( $_POST['price'] ) ? max( 0.0, round( (float) $_POST['price'], 2 ) ) : 0.0;
		$tier  = isset( $_POST['tier_included'] ) ? sanitize_key( wp_unslash( $_POST['tier_included'] ) ) : 'none';
		if ( ! in_array( $tier, array( 'none', 'all', 'standard', 'premium' ), true ) ) {
			$tier = 'none';
		}

		$resources[ $res_id ] = array(
			'id'            => $res_id,
			'title'         => $title,
			'category'      => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'Sales Toolkit',
			'description'   => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'price'         => $price,
			'file_url'      => isset( $_POST['file_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['file_url'] ) ) ) : '',
			'thumbnail_url' => isset( $_POST['thumbnail_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['thumbnail_url'] ) ) ) : '',
			'tier_included' => $tier,
			'status'        => ( isset( $_POST['status'] ) && 'draft' === $_POST['status'] ) ? 'draft' : 'published',
			'created_at'    => isset( $resources[ $res_id ]['created_at'] ) ? $resources[ $res_id ]['created_at'] : current_time( 'mysql' ),
		);

		update_option( self::OPT_RESOURCES, $resources );
		wp_send_json_success( array(
			'message'  => __( 'TPD Resource saved and published!', 'tpd-tool' ),
			'resource' => $resources[ $res_id ],
		) );
	}

	/**
	 * AJAX (Admin): Delete a TPD Custom Resource.
	 */
	public static function ajax_admin_delete_custom_resource() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$res_id    = isset( $_POST['resource_id'] ) ? sanitize_key( wp_unslash( $_POST['resource_id'] ) ) : '';
		$resources = self::get_custom_resources( true );
		if ( isset( $resources[ $res_id ] ) ) {
			unset( $resources[ $res_id ] );
			update_option( self::OPT_RESOURCES, $resources );
		}

		wp_send_json_success( array( 'message' => __( 'Resource deleted.', 'tpd-tool' ) ) );
	}
}

TPD_Tool_Resources::init();
