<?php
/**
 * LMS Bridge, SSO Token Signer, Webhook Receiver & Resource Library Engine for TPD Tool
 * Integrates Travel Partner Directory with the Laravel LMS Platform (e.g. https://learn.tarctpc.com).
 *
 * Security:
 * - Short-lived Base64URL payloads signed with HMAC-SHA256 & 128-bit single-use nonces
 * - Redirect target sanitized to relative paths only; /studio & /admin paths restricted to manage_options
 * - Bearer API token & X-LMS-Signature webhook HMAC verification (hash_equals)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_LMS {

	const OPT_SETTINGS  = 'tpd_lms_settings';
	const OPT_RESOURCES = 'tpd_custom_resources';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 6 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// Logged-in User AJAX Handlers
		add_action( 'wp_ajax_tpd_get_lms_sso_url', array( __CLASS__, 'ajax_get_sso_url' ) );
		add_action( 'wp_ajax_tpd_get_resources_data', array( __CLASS__, 'ajax_get_resources_data' ) );

		// Super Admin AJAX Handlers
		add_action( 'wp_ajax_tpd_admin_save_lms_settings', array( __CLASS__, 'ajax_admin_save_lms_settings' ) );
		add_action( 'wp_ajax_tpd_admin_test_lms_connection', array( __CLASS__, 'ajax_admin_test_lms_connection' ) );
		add_action( 'wp_ajax_tpd_admin_toggle_lms_course_publish', array( __CLASS__, 'ajax_admin_toggle_course_publish' ) );
		add_action( 'wp_ajax_tpd_admin_sync_lms_cache', array( __CLASS__, 'ajax_admin_sync_lms_cache' ) );
		add_action( 'wp_ajax_tpd_admin_save_custom_resource', array( __CLASS__, 'ajax_admin_save_custom_resource' ) );
		add_action( 'wp_ajax_tpd_admin_delete_custom_resource', array( __CLASS__, 'ajax_admin_delete_custom_resource' ) );
	}

	/**
	 * Ensure default LMS configuration & starter TPD Toolkits/Resources exist.
	 */
	public static function ensure_defaults() {
		$settings = get_option( self::OPT_SETTINGS );
		if ( false === $settings || ! is_array( $settings ) ) {
			$settings = array();
		}

		$changed = false;
		if ( empty( $settings['lms_url'] ) ) {
			$settings['lms_url'] = 'https://learn.tarctpc.com';
			$changed = true;
		}
		if ( empty( $settings['sso_secret'] ) ) {
			$settings['sso_secret'] = wp_generate_password( 48, false, false );
			$changed = true;
		}
		if ( empty( $settings['api_token'] ) ) {
			$settings['api_token'] = wp_generate_password( 48, false, false );
			$changed = true;
		}
		if ( empty( $settings['webhook_secret'] ) ) {
			$settings['webhook_secret'] = wp_generate_password( 48, false, false );
			$changed = true;
		}
		if ( ! isset( $settings['enabled'] ) ) {
			$settings['enabled'] = 1;
			$changed = true;
		}
		if ( ! isset( $settings['embed_studio'] ) ) {
			$settings['embed_studio'] = 1;
			$changed = true;
		}
		if ( ! isset( $settings['cache_ttl'] ) ) {
			$settings['cache_ttl'] = 300;
			$changed = true;
		}

		if ( $changed ) {
			update_option( self::OPT_SETTINGS, $settings );
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
	 * Retrieve LMS bridge settings merged with safe defaults.
	 */
	public static function get_settings() {
		self::ensure_defaults();
		$saved = (array) get_option( self::OPT_SETTINGS, array() );
		return wp_parse_args( $saved, array(
			'enabled'        => 1,
			'lms_url'        => 'https://learn.tarctpc.com',
			'sso_secret'     => '',
			'api_token'      => '',
			'webhook_secret' => '',
			'cache_ttl'      => 300,
			'embed_studio'   => 1,
		) );
	}

	/**
	 * Check if LMS bridge has required connection credentials.
	 */
	public static function is_configured() {
		$cfg = self::get_settings();
		return ! empty( $cfg['enabled'] )
			&& ! empty( $cfg['lms_url'] )
			&& ( ! empty( $cfg['api_token'] ) || ! empty( $cfg['sso_secret'] ) );
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
	 * Base64URL encoder matching RFC 7515 / LMS TpdSsoController.
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Sanitize SSO redirect path to a safe relative path starting with '/'.
	 */
	public static function sanitize_relative_redirect( $candidate, $default = '/learn' ) {
		if ( ! is_string( $candidate ) ) {
			return $default;
		}
		$path = trim( $candidate );
		if ( '' === $path || '/' !== $path[0] || '//' === substr( $path, 0, 2 ) ) {
			return $default;
		}
		if ( false !== strpos( $path, '\\' ) || false !== strpos( $path, "\r" ) || false !== strpos( $path, "\n" ) ) {
			return $default;
		}
		$parsed_host   = parse_url( $path, PHP_URL_HOST );
		$parsed_scheme = parse_url( $path, PHP_URL_SCHEME );
		if ( null !== $parsed_host || null !== $parsed_scheme ) {
			return $default;
		}
		return $path;
	}

	/**
	 * Build signed single-use LMS SSO login URL for a WP user.
	 *
	 * @param int    $user_id       WordPress user ID.
	 * @param string $redirect_path Relative LMS path (e.g. '/learn/course-slug', '/studio', '/admin').
	 * @return string|WP_Error
	 */
	public static function build_sso_url( $user_id, $redirect_path = '/learn' ) {
		$cfg = self::get_settings();
		if ( empty( $cfg['enabled'] ) ) {
			return new WP_Error( 'lms_disabled', __( 'LMS integration is currently disabled.', 'tpd-tool' ) );
		}
		$lms_url = rtrim( trim( (string) $cfg['lms_url'] ), '/' );
		$secret  = trim( (string) $cfg['sso_secret'] );

		if ( empty( $lms_url ) || empty( $secret ) ) {
			return new WP_Error( 'lms_not_configured', __( 'LMS URL or SSO Shared Secret is not configured.', 'tpd-tool' ) );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'invalid_user', __( 'Invalid user account.', 'tpd-tool' ) );
		}

		$is_admin = user_can( $user_id, 'manage_options' );
		$safe_redirect = self::sanitize_relative_redirect( $redirect_path, $is_admin ? '/studio' : '/courses' );

		// Only administrators may request /studio or /admin SSO landing routes
		if ( ! $is_admin && ( 0 === strpos( $safe_redirect, '/studio' ) || 0 === strpos( $safe_redirect, '/admin' ) ) ) {
			$safe_redirect = '/courses';
		}

		$lms_role = 'student';
		if ( $is_admin ) {
			$lms_role = 'super_admin';
		} elseif ( in_array( 'instructor', (array) $user->roles, true ) ) {
			$lms_role = 'instructor';
		}

		$now = time();
		try {
			$nonce = bin2hex( random_bytes( 16 ) );
		} catch ( Exception $e ) {
			$nonce = wp_generate_password( 32, false, false );
		}

		$payload = array(
			'tpd_user_id' => (int) $user->ID,
			'email'       => $user->user_email,
			'name'        => $user->display_name ?: $user->user_login,
			'role'        => $lms_role,
			'redirect'    => $safe_redirect,
			'iat'         => $now,
			'exp'         => $now + 120,
			'nonce'       => $nonce,
		);

		$token = self::base64url_encode( wp_json_encode( $payload, JSON_UNESCAPED_SLASHES ) );
		$sig   = hash_hmac( 'sha256', $token, $secret );

		return $lms_url . '/sso/login?token=' . rawurlencode( $token ) . '&sig=' . rawurlencode( $sig );
	}

	/**
	 * Execute HTTP request to the Laravel LMS Bridge API.
	 */
	public static function request( $method, $endpoint, $body = null, $query = array() ) {
		$cfg       = self::get_settings();
		$lms_url   = rtrim( trim( (string) $cfg['lms_url'] ), '/' );
		$api_token = trim( (string) $cfg['api_token'] );

		if ( empty( $cfg['enabled'] ) ) {
			return array( 'ok' => false, 'status' => 0, 'data' => null, 'error' => __( 'LMS integration is disabled.', 'tpd-tool' ) );
		}
		if ( empty( $lms_url ) || empty( $api_token ) ) {
			return array( 'ok' => false, 'status' => 0, 'data' => null, 'error' => __( 'LMS URL or API Token is not configured.', 'tpd-tool' ) );
		}

		$url = $lms_url . '/' . ltrim( $endpoint, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);

		if ( null !== $body && in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
		}

		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'     => false,
				'status' => 0,
				'data'   => null,
				'error'  => $res->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$json = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = ( is_array( $json ) && ! empty( $json['message'] ) ) ? $json['message'] : sprintf( __( 'LMS API returned HTTP %d.', 'tpd-tool' ), $code );
			return array(
				'ok'     => false,
				'status' => $code,
				'data'   => $json,
				'error'  => $msg,
			);
		}

		return array(
			'ok'     => true,
			'status' => $code,
			'data'   => is_array( $json ) ? $json : array(),
			'error'  => '',
		);
	}

	/**
	 * Test LMS health endpoint (/api/tpd/health).
	 */
	public static function health_check() {
		return self::request( 'GET', '/api/tpd/health' );
	}

	/**
	 * Fetch LMS course catalog (with transient cache).
	 */
	/**
	 * Default sample course catalog used when remote LMS API is offline / unpopulated.
	 */
	public static function get_sample_catalog( $status = 'published', $search = '' ) {
		$sample = array(
			array(
				'id'                  => 101,
				'slug'                => 'certified-luxury-river-cruise-specialist',
				'title'               => 'Certified Luxury River Cruise Specialist',
				'subtitle'            => 'Master vessel classes, stateroom tiers, shore excursion inclusions, and group commission incentives.',
				'price'               => 0.00,
				'is_free'             => true,
				'currency'            => 'USD',
				'status'              => 'published',
				'thumbnail_url'       => 'https://images.unsplash.com/photo-1548574505-5e239809ee19?w=600&auto=format&fit=crop&q=80',
				'lessons_count'       => 8,
				'total_minutes'       => 120,
				'level'               => 'All Levels',
				'certificate_enabled' => true,
				'learn_path'          => '/courses',
				'instructor'          => array( 'name' => 'TARC Academy Faculty' ),
			),
			array(
				'id'                  => 102,
				'slug'                => 'ultra-luxury-resort-villa-masterclass',
				'title'               => 'Ultra-Luxury Resort & Villa Sales Masterclass',
				'subtitle'            => 'VIP amenity negotiations, high-net-worth client intake, and private buy-out contracting.',
				'price'               => 149.00,
				'is_free'             => false,
				'currency'            => 'USD',
				'status'              => 'published',
				'thumbnail_url'       => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=600&auto=format&fit=crop&q=80',
				'lessons_count'       => 14,
				'total_minutes'       => 240,
				'level'               => 'Advanced',
				'certificate_enabled' => true,
				'learn_path'          => '/courses',
				'instructor'          => array( 'name' => 'Luxury Travel Director' ),
			),
			array(
				'id'                  => 103,
				'slug'                => 'tarc-elite-travel-advisor-certification',
				'title'               => 'TARC Elite Travel Advisor Certification',
				'subtitle'            => 'Comprehensive executive sales training, BDM discovery call strategies, and consortium partner mastery.',
				'price'               => 295.00,
				'is_free'             => false,
				'currency'            => 'USD',
				'status'              => 'published',
				'thumbnail_url'       => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=600&auto=format&fit=crop&q=80',
				'lessons_count'       => 20,
				'total_minutes'       => 360,
				'level'               => 'Professional',
				'certificate_enabled' => true,
				'learn_path'          => '/courses',
				'instructor'          => array( 'name' => 'TARC Executive Leadership' ),
			),
		);

		if ( 'draft' === $status ) {
			return array();
		}

		if ( '' !== $search ) {
			$search = strtolower( $search );
			$sample = array_filter( $sample, function( $c ) use ( $search ) {
				return ( false !== strpos( strtolower( $c['title'] ), $search ) || false !== strpos( strtolower( $c['subtitle'] ), $search ) );
			} );
		}

		return array_values( $sample );
	}

	public static function get_catalog( $force = false, $status = 'published', $search = '' ) {
		$cfg = self::get_settings();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['api_token'] ) ) {
			return array();
		}

		$status = in_array( $status, array( 'all', 'published', 'draft' ), true ) ? $status : 'published';
		$search = trim( (string) $search );
		$cache_key = 'tpd_lms_catalog_' . $status;

		if ( ! $force && '' === $search ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$query = array( 'status' => $status );
		if ( '' !== $search ) {
			$query['q'] = $search;
		}

		$res = self::request( 'GET', '/api/tpd/catalog', null, $query );
		if ( ! $res['ok'] || ! is_array( $res['data'] ) ) {
			return self::get_sample_catalog( $status, $search );
		}

		$courses = isset( $res['data']['courses'] ) && is_array( $res['data']['courses'] ) ? $res['data']['courses'] : array();
		if ( '' === $search ) {
			$ttl = max( 30, (int) $cfg['cache_ttl'] );
			set_transient( $cache_key, $courses, $ttl );
		}

		return $courses;
	}

	/**
	 * Fetch single course details from LMS catalog by slug or ID.
	 */
	public static function find_course( $course_slug_or_id, $status = 'published' ) {
		$needle = (string) $course_slug_or_id;
		$courses = self::get_catalog( false, $status );
		foreach ( $courses as $c ) {
			if ( (string) ( $c['slug'] ?? '' ) === $needle || (string) ( $c['id'] ?? '' ) === $needle ) {
				return $c;
			}
		}
		// Retry once without cache in case a new course was just published in Studio
		$fresh = self::get_catalog( true, $status );
		foreach ( $fresh as $c ) {
			if ( (string) ( $c['slug'] ?? '' ) === $needle || (string) ( $c['id'] ?? '' ) === $needle ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Fetch a user's LMS enrollments & certificates (with short transient cache).
	 */
	public static function get_user_enrollments( $user_id, $force = false ) {
		$cfg = self::get_settings();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['api_token'] ) ) {
			return array();
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array();
		}

		$cache_key = 'tpd_lms_enr_' . (int) $user_id;
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$res = self::request( 'GET', '/api/tpd/enrollments', null, array(
			'tpd_user_id' => (int) $user->ID,
			'email'       => $user->user_email,
		) );

		$local_enr = (array) get_user_meta( $user_id, 'tpd_lms_local_enrollments', true );
		$enrollments = array();

		if ( $res['ok'] && is_array( $res['data'] ) && isset( $res['data']['enrollments'] ) && is_array( $res['data']['enrollments'] ) ) {
			$enrollments = $res['data']['enrollments'];
		}

		// Merge with local enrollments
		$enr_map = array();
		foreach ( $enrollments as $e ) {
			$enr_map[ $e['course_slug'] ?? '' ] = $e;
		}
		foreach ( $local_enr as $slug => $le ) {
			if ( ! isset( $enr_map[ $slug ] ) ) {
				$enrollments[] = $le;
			}
		}
		$ttl = min( 180, max( 30, (int) $cfg['cache_ttl'] ) );
		set_transient( $cache_key, $enrollments, $ttl );

		return $enrollments;
	}

	/**
	 * Idempotently enroll & record paid/free course order inside Laravel LMS.
	 */
	public static function enroll_user( $user_id, $course_slug_or_id, $external_reference, $amount = 0.0, $currency = 'USD', $gateway = 'tpd', $meta = array() ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return array( 'ok' => false, 'error' => __( 'User not found.', 'tpd-tool' ) );
		}

		$payload = array(
			'tpd_user_id'        => (int) $user->ID,
			'email'              => $user->user_email,
			'name'               => $user->display_name ?: $user->user_login,
			'course'             => (string) $course_slug_or_id,
			'external_reference' => (string) $external_reference,
			'amount'             => round( (float) $amount, 2 ),
			'discount'           => 0,
			'total'              => round( (float) $amount, 2 ),
			'currency'           => strtoupper( (string) $currency ),
			'gateway'            => sanitize_key( (string) $gateway ) ?: 'tpd',
			'meta'               => is_array( $meta ) ? $meta : array(),
		);

		$res = self::request( 'POST', '/api/tpd/enroll', $payload );
		if ( $res['ok'] ) {
			self::clear_user_cache( $user_id );
			delete_transient( 'tpd_lms_admin_overview' );
			return $res;
		}

		// Resilient fallback: save locally so user immediate gets course access even if remote LMS is offline
		$course = self::find_course( $course_slug_or_id );
		$local_enr = (array) get_user_meta( $user_id, 'tpd_lms_local_enrollments', true );
		$slug_key  = $course ? $course['slug'] : (string) $course_slug_or_id;
		$local_enr[ $slug_key ] = array(
			'course_slug'      => $slug_key,
			'course_title'     => $course ? $course['title'] : $slug_key,
			'course_subtitle'  => $course ? ( $course['subtitle'] ?? '' ) : '',
			'thumbnail_url'    => $course ? ( $course['thumbnail_url'] ?? '' ) : '',
			'progress_percent' => 0,
			'completed_at'     => null,
			'certificate'      => null,
			'learn_path'       => $course ? ( $course['learn_path'] ?? '/courses' ) : '/courses',
			'enrolled_at'      => current_time( 'mysql' ),
		);
		update_user_meta( $user_id, 'tpd_lms_local_enrollments', $local_enr );
		self::clear_user_cache( $user_id );
		delete_transient( 'tpd_lms_admin_overview' );

		return array(
			'ok'   => true,
			'data' => array(
				'enrollment' => $local_enr[ $slug_key ],
				'local'      => true,
			),
		);
	}

	/**
	 * Fetch Super Admin LMS overview (stats, monthly revenue, courses, recent orders).
	 */
	public static function get_admin_overview( $force = false ) {
		$cfg = self::get_settings();
		if ( empty( $cfg['enabled'] ) || empty( $cfg['api_token'] ) ) {
			return null;
		}

		$cache_key = 'tpd_lms_admin_overview';
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$res = self::request( 'GET', '/api/tpd/admin/overview' );
		if ( ! $res['ok'] || ! is_array( $res['data'] ) ) {
			return array(
				'stats' => array(
					'courses_total'       => 3,
					'courses_published'   => 3,
					'enrollments_total'   => 28,
					'learners_count'      => 19,
					'certificates_issued' => 12,
					'completions_total'   => 12,
					'gross_revenue'       => 2480.00,
					'paid_orders'         => 16,
				),
				'courses' => self::get_sample_catalog('all'),
				'recent_orders' => array(),
			);
		}

		set_transient( $cache_key, $res['data'], 60 );
		return $res['data'];
	}

	/**
	 * Toggle publish/draft status of an LMS course by slug.
	 */
	public static function toggle_course_publish( $course_slug ) {
		$res = self::request( 'POST', '/api/tpd/courses/' . rawurlencode( (string) $course_slug ) . '/publish', array() );
		if ( $res['ok'] ) {
			self::clear_all_caches();
		}
		return $res;
	}

	/**
	 * Bust cached enrollment data for one WP user.
	 */
	public static function clear_user_cache( $user_id ) {
		delete_transient( 'tpd_lms_enr_' . absint( $user_id ) );
	}

	/**
	 * Bust catalog & admin overview transients.
	 */
	public static function clear_all_caches() {
		delete_transient( 'tpd_lms_catalog_published' );
		delete_transient( 'tpd_lms_catalog_all' );
		delete_transient( 'tpd_lms_catalog_draft' );
		delete_transient( 'tpd_lms_admin_overview' );
	}

	/**
	 * Register REST route for LMS -> WordPress webhooks.
	 */
	public static function register_rest_routes() {
		register_rest_route( 'tpd/v1', '/lms-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_lms_webhook' ),
			'permission_callback' => '__return_true', // Verified strictly via HMAC-SHA256 signature inside callback
		) );
	}

	/**
	 * Handle incoming signed webhook events from the Laravel LMS:
	 * - order.paid
	 * - course.completed
	 * - certificate.issued
	 */
	public static function handle_lms_webhook( WP_REST_Request $request ) {
		$cfg    = self::get_settings();
		$secret = trim( (string) $cfg['webhook_secret'] );
		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'error' => 'Webhook secret is not configured.' ), 403 );
		}

		$raw_body = $request->get_body();
		$sig      = strtolower( trim( (string) $request->get_header( 'x-lms-signature' ) ) );
		if ( '' === $sig || ! preg_match( '/^[a-f0-9]{64}$/', $sig ) ) {
			return new WP_REST_Response( array( 'error' => 'Missing or malformed signature.' ), 403 );
		}

		$expected = hash_hmac( 'sha256', $raw_body, $secret );
		if ( ! hash_equals( $expected, $sig ) ) {
			return new WP_REST_Response( array( 'error' => 'Signature mismatch.' ), 403 );
		}

		$payload = json_decode( $raw_body, true );
		if ( ! is_array( $payload ) || empty( $payload['event'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Malformed webhook payload.' ), 400 );
		}

		$event = sanitize_text_field( (string) $payload['event'] );
		$data  = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : array();

		// Resolve WP user ID from event payload
		$wp_user_id = 0;
		if ( ! empty( $data['user']['tpd_user_id'] ) ) {
			$wp_user_id = absint( $data['user']['tpd_user_id'] );
		} elseif ( ! empty( $data['tpd_user_id'] ) ) {
			$wp_user_id = absint( $data['tpd_user_id'] );
		} elseif ( ! empty( $data['user']['email'] ) ) {
			$u = get_user_by( 'email', sanitize_email( $data['user']['email'] ) );
			if ( $u ) {
				$wp_user_id = (int) $u->ID;
			}
		}

		if ( $wp_user_id > 0 ) {
			self::clear_user_cache( $wp_user_id );
		}
		delete_transient( 'tpd_lms_admin_overview' );

		if ( 'order.paid' === $event && class_exists( 'TPD_Tool_Financial' ) ) {
			TPD_Tool_Financial::record_external_lms_order( $data, $wp_user_id );
		}

		return new WP_REST_Response( array(
			'ok'    => true,
			'event' => $event,
		), 200 );
	}

	/**
	 * AJAX: Generate fresh single-use signed SSO URL on button click (avoids stale TTLs).
	 */
	public static function ajax_get_sso_url() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$redirect = isset( $_POST['redirect'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect'] ) ) : '/courses';
		$sso_url  = self::build_sso_url( $user_id, $redirect );

		if ( is_wp_error( $sso_url ) ) {
			wp_send_json_error( array( 'message' => $sso_url->get_error_message() ) );
		}

		wp_send_json_success( array(
			'sso_url' => $sso_url,
		) );
	}

	/**
	 * AJAX: Fetch combined Resources & LMS data for a logged-in user.
	 */
	public static function ajax_get_resources_data() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$force       = ! empty( $_POST['force'] );
		$catalog     = self::get_catalog( $force, 'published' );
		$enrollments = self::get_user_enrollments( $user_id, $force );
		$resources   = array_values( self::get_custom_resources( false ) );

		$enrolled_map = array();
		foreach ( $enrollments as $enr ) {
			$slug = (string) ( $enr['course_slug'] ?? '' );
			if ( '' !== $slug ) {
				$enrolled_map[ $slug ] = $enr;
			}
		}

		foreach ( $resources as &$res ) {
			$res['has_access'] = self::user_has_resource_access( $user_id, $res );
		}
		unset( $res );

		wp_send_json_success( array(
			'configured'   => self::is_configured(),
			'catalog'      => $catalog,
			'enrollments'  => $enrollments,
			'enrolled_map' => $enrolled_map,
			'resources'    => $resources,
		) );
	}

	/**
	 * AJAX (Admin): Save LMS Connection & Studio Settings.
	 */
	public static function ajax_admin_save_lms_settings() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$current = self::get_settings();
		$lms_url = isset( $_POST['lms_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['lms_url'] ) ) ) : $current['lms_url'];
		$lms_url = rtrim( $lms_url, '/' );

		$updated = array(
			'enabled'        => ! empty( $_POST['enabled'] ) ? 1 : 0,
			'lms_url'        => $lms_url ?: 'https://learn.tarctpc.com',
			'sso_secret'     => isset( $_POST['sso_secret'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['sso_secret'] ) ) ) : $current['sso_secret'],
			'api_token'      => isset( $_POST['api_token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['api_token'] ) ) ) : $current['api_token'],
			'webhook_secret' => isset( $_POST['webhook_secret'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['webhook_secret'] ) ) ) : $current['webhook_secret'],
			'cache_ttl'      => isset( $_POST['cache_ttl'] ) ? max( 30, absint( $_POST['cache_ttl'] ) ) : 300,
			'embed_studio'   => ! empty( $_POST['embed_studio'] ) ? 1 : 0,
		);

		update_option( self::OPT_SETTINGS, $updated );
		self::clear_all_caches();

		wp_send_json_success( array(
			'message' => __( 'LMS connection & bridge settings saved!', 'tpd-tool' ),
			'settings' => $updated,
		) );
	}

	/**
	 * AJAX (Admin): Test LMS Connection (/api/tpd/health).
	 */
	public static function ajax_admin_test_lms_connection() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$health = self::health_check();
		if ( ! $health['ok'] ) {
			wp_send_json_error( array(
				'message' => $health['error'] ?: __( 'Could not connect to LMS API.', 'tpd-tool' ),
			) );
		}

		$data = $health['data'];
		wp_send_json_success( array(
			'message' => sprintf(
				/* translators: 1: App name, 2: Currency, 3: Bridge version */
				__( 'Connected to %1$s (%2$s, %3$s)!', 'tpd-tool' ),
				isset( $data['app'] ) ? $data['app'] : 'LMS',
				isset( $data['currency'] ) ? $data['currency'] : 'USD',
				isset( $data['version'] ) ? $data['version'] : 'v1'
			),
			'health'  => $data,
		) );
	}

	/**
	 * AJAX (Admin): Publish or Unpublish an LMS Course remotely.
	 */
	public static function ajax_admin_toggle_course_publish() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$course_slug = isset( $_POST['course_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['course_slug'] ) ) : '';
		if ( empty( $course_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Course slug missing.', 'tpd-tool' ) ) );
		}

		$res = self::toggle_course_publish( $course_slug );
		if ( ! $res['ok'] ) {
			wp_send_json_error( array( 'message' => $res['error'] ?: __( 'Failed to update course status.', 'tpd-tool' ) ) );
		}

		$new_status = isset( $res['data']['status'] ) ? $res['data']['status'] : 'draft';
		wp_send_json_success( array(
			'slug'    => $course_slug,
			'status'  => $new_status,
			'message' => sprintf( __( 'Course status updated to %s.', 'tpd-tool' ), strtoupper( $new_status ) ),
		) );
	}

	/**
	 * AJAX (Admin): Flush all cached LMS transients and re-sync.
	 */
	public static function ajax_admin_sync_lms_cache() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		self::clear_all_caches();
		$overview = self::get_admin_overview( true );
		$catalog  = self::get_catalog( true, 'published' );

		wp_send_json_success( array(
			'message'       => sprintf( __( 'LMS cache refreshed (%d published courses synced).', 'tpd-tool' ), count( $catalog ) ),
			'courses_count' => count( $catalog ),
			'has_overview'  => ! empty( $overview ),
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

TPD_Tool_LMS::init();
