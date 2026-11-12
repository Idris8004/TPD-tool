<?php
/**
 * Unified Checkout Engine, Financial Ledger & Revenue Analytics for TPD Tool
 * Handles:
 * - Custom Ledger DB Table (`{$wpdb->prefix}tpd_transactions`)
 * - Authoritative Server-Side Pricing & Checkout Initiation (Membership Plans, LMS Courses, TPD Resources/Tools)
 * - Stripe Checkout Sessions, PayPal Orders v2, and Test/Sandbox Mode Checkout
 * - Idempotent Fulfillment (Plan Activation, LMS Course Enrollment via API, Resource Unlock)
 * - Financial Analytics Queries, Admin Management & CSV Export
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Financial {

	const DB_VERSION    = '1.0';
	const OPT_DB_VER    = 'tpd_financial_db_ver';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_create_table' ), 4 );
		add_action( 'init', array( __CLASS__, 'handle_csv_export' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

		// User Checkout AJAX Endpoints
		add_action( 'wp_ajax_tpd_initiate_checkout', array( __CLASS__, 'ajax_initiate_checkout' ) );
		add_action( 'wp_ajax_tpd_verify_checkout_return', array( __CLASS__, 'ajax_verify_checkout_return' ) );
		add_action( 'wp_ajax_tpd_get_checkout_preview', array( __CLASS__, 'ajax_get_checkout_preview' ) );

		// Super Admin Financial AJAX Endpoints
		add_action( 'wp_ajax_tpd_admin_update_tx_status', array( __CLASS__, 'ajax_admin_update_tx_status' ) );
	}

	/**
	 * Table name helper.
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'tpd_transactions';
	}

	/**
	 * Idempotently create the financial transactions table.
	 */
	public static function maybe_create_table() {
		if ( get_option( self::OPT_DB_VER ) === self::DB_VERSION ) {
			return;
		}
		self::create_table();
	}

	/**
	 * Execute dbDelta to create/upgrade tpd_transactions table.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tx_ref VARCHAR(80) NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			user_email VARCHAR(190) NOT NULL DEFAULT '',
			user_name VARCHAR(190) NOT NULL DEFAULT '',
			user_role VARCHAR(40) NOT NULL DEFAULT '',
			item_type VARCHAR(32) NOT NULL DEFAULT 'plan',
			item_id VARCHAR(160) NOT NULL DEFAULT '',
			item_title VARCHAR(255) NOT NULL DEFAULT '',
			billing_cycle VARCHAR(20) NOT NULL DEFAULT 'once',
			amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(10) NOT NULL DEFAULT 'USD',
			gateway VARCHAR(32) NOT NULL DEFAULT 'test',
			gateway_ref VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(24) NOT NULL DEFAULT 'pending',
			meta_json LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			paid_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tx_ref (tx_ref),
			KEY idx_user_id (user_id),
			KEY idx_item_type (item_type),
			KEY idx_status (status),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::OPT_DB_VER, self::DB_VERSION );
	}

	/**
	 * Generate a unique transaction reference string.
	 */
	public static function generate_tx_ref( $prefix = 'TPD-TXN' ) {
		return strtoupper( sanitize_key( $prefix ) ) . '-' . gmdate( 'Ymd' ) . '-' . strtoupper( substr( md5( uniqid( wp_rand(), true ) ), 0, 8 ) );
	}

	/**
	 * Determine effective primary user role label for financial attribution.
	 */
	private static function detect_user_role_label( $user_id ) {
		if ( TPD_Tool_Roles::is_admin( $user_id ) ) {
			return 'admin';
		}
		$is_adv  = TPD_Tool_Roles::is_advisor( $user_id );
		$is_supp = TPD_Tool_Roles::is_supplier( $user_id );
		if ( $is_adv && $is_supp ) {
			return 'dual_role';
		}
		if ( $is_supp ) {
			return 'supplier';
		}
		if ( $is_adv ) {
			return 'travel_advisor';
		}
		return 'member';
	}

	/**
	 * Resolve item metadata & authoritative server-side price.
	 * Never trust client-supplied prices!
	 *
	 * @return array|WP_Error
	 */
	public static function resolve_item( $user_id, $item_type, $item_id, $billing_cycle = 'once' ) {
		$payments = TPD_Tool_Settings::get_payment_settings();
		$currency = ! empty( $payments['currency'] ) ? strtoupper( $payments['currency'] ) : 'USD';

		$item_type     = sanitize_key( $item_type );
		$item_id       = sanitize_text_field( (string) $item_id );
		$billing_cycle = strtolower( trim( (string) $billing_cycle ) );

		if ( 'plan' === $item_type ) {
			$plan = TPD_Tool_Settings::get_plan( $item_id );
			if ( empty( $plan ) || empty( $plan['name'] ) ) {
				return new WP_Error( 'invalid_plan', __( 'Selected membership plan does not exist.', 'tpd-tool' ) );
			}
			$is_yearly     = ( false !== strpos( $billing_cycle, 'year' ) || false !== strpos( $billing_cycle, 'annual' ) );
			$cycle_norm    = $is_yearly ? 'year' : 'month';
			$price         = $is_yearly ? (float) ( $plan['yearly_price'] ?? 0 ) : (float) ( $plan['monthly_price'] ?? 0 );
			$cycle_display = $is_yearly ? __( 'Annual', 'tpd-tool' ) : __( 'Monthly', 'tpd-tool' );

			return array(
				'item_type'     => 'plan',
				'item_id'       => (string) ( $plan['slug'] ?? $item_id ),
				'item_title'    => $plan['name'] . ' (' . $cycle_display . ')',
				'billing_cycle' => $cycle_norm,
				'amount'        => round( max( 0.0, $price ), 2 ),
				'currency'      => $currency,
				'description'   => sprintf( __( 'Membership Plan Upgrade — %s Billing', 'tpd-tool' ), $cycle_display ),
				'extra'         => array( 'plan_name' => $plan['name'] ),
			);
		}

		if ( 'lms_course' === $item_type ) {
			if ( ! class_exists( 'TPD_Tool_LMS' ) ) {
				return new WP_Error( 'lms_unavailable', __( 'LMS integration module is not loaded.', 'tpd-tool' ) );
			}
			$course = TPD_Tool_LMS::find_course( $item_id, 'published' );
			if ( ! $course ) {
				return new WP_Error( 'course_not_found', __( 'The selected LMS course was not found or is not published.', 'tpd-tool' ) );
			}
			$course_currency = ! empty( $course['currency'] ) ? strtoupper( $course['currency'] ) : $currency;
			$price           = isset( $course['price'] ) ? (float) $course['price'] : 0.0;

			return array(
				'item_type'     => 'lms_course',
				'item_id'       => (string) ( $course['slug'] ?? $item_id ),
				'item_title'    => (string) ( $course['title'] ?? $item_id ),
				'billing_cycle' => 'once',
				'amount'        => round( max( 0.0, $price ), 2 ),
				'currency'      => $course_currency,
				'description'   => (string) ( $course['subtitle'] ?? __( 'LMS Training Course Enrollment', 'tpd-tool' ) ),
				'extra'         => array(
					'course_id'  => $course['id'] ?? 0,
					'learn_path' => $course['learn_path'] ?? '/courses',
				),
			);
		}

		if ( 'resource' === $item_type ) {
			if ( ! class_exists( 'TPD_Tool_LMS' ) ) {
				return new WP_Error( 'lms_unavailable', __( 'Resource library module is not loaded.', 'tpd-tool' ) );
			}
			$resources = TPD_Tool_LMS::get_custom_resources( false );
			if ( empty( $resources[ $item_id ] ) ) {
				return new WP_Error( 'resource_not_found', __( 'Selected resource or toolkit was not found.', 'tpd-tool' ) );
			}
			$res   = $resources[ $item_id ];
			$price = TPD_Tool_LMS::user_has_resource_access( $user_id, $res ) ? 0.0 : (float) ( $res['price'] ?? 0 );

			return array(
				'item_type'     => 'resource',
				'item_id'       => (string) $res['id'],
				'item_title'    => (string) $res['title'],
				'billing_cycle' => 'once',
				'amount'        => round( max( 0.0, $price ), 2 ),
				'currency'      => $currency,
				'description'   => (string) ( $res['category'] ?? __( 'TPD Toolkit & Resource', 'tpd-tool' ) ),
				'extra'         => array( 'file_url' => $res['file_url'] ?? '' ),
			);
		}

		return new WP_Error( 'unsupported_item', __( 'Unsupported checkout item type.', 'tpd-tool' ) );
	}

	/**
	 * Fetch a transaction row by tx_ref.
	 */
	public static function get_tx_by_ref( $tx_ref ) {
		global $wpdb;
		self::maybe_create_table();
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE tx_ref = %s LIMIT 1", $tx_ref ) );
	}

	/**
	 * Fetch a transaction row by numeric ID.
	 */
	public static function get_tx_by_id( $id ) {
		global $wpdb;
		self::maybe_create_table();
		$table = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) ) );
	}

	/**
	 * Idempotently fulfill a paid transaction according to its item_type.
	 *
	 * @return array Result metadata (status message, redirect/sso info).
	 */
	public static function fulfill_transaction( $tx ) {
		global $wpdb;
		if ( ! $tx || empty( $tx->id ) ) {
			return array( 'ok' => false, 'error' => 'Invalid transaction.' );
		}

		$meta = array();
		if ( ! empty( $tx->meta_json ) ) {
			$decoded = json_decode( $tx->meta_json, true );
			if ( is_array( $decoded ) ) {
				$meta = $decoded;
			}
		}

		if ( ! empty( $meta['fulfilled_at'] ) ) {
			return array(
				'ok'      => true,
				'already' => true,
				'message' => __( 'Your purchase has been confirmed and unlocked!', 'tpd-tool' ),
			);
		}

		$user_id = (int) $tx->user_id;
		$result  = array( 'ok' => true, 'message' => __( 'Purchase completed!', 'tpd-tool' ) );

		if ( 'plan' === $tx->item_type ) {
			$updated_tier = TPD_Tool_Tiers::set_user_tier( $user_id, $tx->item_id );
			$cycle_label  = ( 'year' === $tx->billing_cycle ) ? 'Year' : 'Month';
			TPD_Tool_Settings::record_subscription( $user_id, $updated_tier, $cycle_label, ucfirst( $tx->gateway ) );

			$plan = TPD_Tool_Settings::get_plan( $updated_tier );
			$result['message'] = sprintf(
				/* translators: %s: plan name */
				__( 'Membership upgraded to %s!', 'tpd-tool' ),
				$plan['name']
			);
			$result['reload'] = true;

		} elseif ( 'lms_course' === $tx->item_type ) {
			if ( class_exists( 'TPD_Tool_LMS' ) ) {
				$enroll_res = TPD_Tool_LMS::enroll_user(
					$user_id,
					$tx->item_id,
					$tx->tx_ref,
					(float) $tx->amount,
					$tx->currency,
					$tx->gateway,
					array( 'wp_tx_id' => (int) $tx->id )
				);

				if ( empty( $enroll_res['ok'] ) ) {
					// Record error in meta for retry without hiding payment receipt
					$meta['lms_enroll_error'] = $enroll_res['error'] ?? 'Unknown LMS enrollment error';
					$wpdb->update(
						self::table_name(),
						array( 'meta_json' => wp_json_encode( $meta ) ),
						array( 'id' => (int) $tx->id ),
						array( '%s' ),
						array( '%d' )
					);
					return array(
						'ok'    => false,
						'error' => sprintf(
							/* translators: %s: error text */
							__( 'Payment succeeded (%1$s), but automatic LMS enrollment failed: %2$s', 'tpd-tool' ),
							$tx->tx_ref,
							$meta['lms_enroll_error']
						),
					);
				}

				$learn_path = $enroll_res['data']['enrollment']['learn_path'] ?? ( $meta['extra']['learn_path'] ?? '/courses' );
				$sso_url    = TPD_Tool_LMS::build_sso_url( $user_id, $learn_path );
				if ( ! is_wp_error( $sso_url ) ) {
					$result['sso_url'] = $sso_url;
				}
			}

			$result['message'] = sprintf(
				/* translators: %s: course title */
				__( 'You are now enrolled in "%s"! It is ready in My Resources.', 'tpd-tool' ),
				$tx->item_title
			);
			$result['refresh_resources'] = true;

		} elseif ( 'resource' === $tx->item_type ) {
			if ( class_exists( 'TPD_Tool_LMS' ) ) {
				TPD_Tool_LMS::unlock_custom_resource( $user_id, $tx->item_id );
			}
			$result['message'] = sprintf(
				/* translators: %s: resource title */
				__( '"%s" has been unlocked and added to My Resources!', 'tpd-tool' ),
				$tx->item_title
			);
			$result['refresh_resources'] = true;
		}

		$meta['fulfilled_at'] = current_time( 'mysql' );
		unset( $meta['lms_enroll_error'] );

		$wpdb->update(
			self::table_name(),
			array( 'meta_json' => wp_json_encode( $meta ) ),
			array( 'id' => (int) $tx->id ),
			array( '%s' ),
			array( '%d' )
		);

		return $result;
	}

	/**
	 * Record an external LMS order received via /lms-webhook (order.paid) idempotently.
	 */
	public static function record_external_lms_order( $data, $wp_user_id = 0 ) {
		global $wpdb;
		self::maybe_create_table();

		$ref = ! empty( $data['reference'] ) ? sanitize_text_field( (string) $data['reference'] ) : '';
		if ( '' === $ref ) {
			return false;
		}

		// If already recorded (e.g. initiated from TPD Checkout), skip duplicate insert
		$existing = self::get_tx_by_ref( $ref );
		if ( $existing ) {
			return (int) $existing->id;
		}

		$user  = $wp_user_id ? get_userdata( $wp_user_id ) : null;
		$email = $user ? $user->user_email : sanitize_email( (string) ( $data['user']['email'] ?? '' ) );
		$name  = $user ? ( $user->display_name ?: $user->user_login ) : sanitize_text_field( (string) ( $data['user']['name'] ?? 'LMS Learner' ) );

		$course_slug  = sanitize_text_field( (string) ( $data['course']['slug'] ?? '' ) );
		$course_title = sanitize_text_field( (string) ( $data['course']['title'] ?? $course_slug ) );
		$amount       = isset( $data['total'] ) ? round( (float) $data['total'], 2 ) : 0.0;
		$currency     = ! empty( $data['currency'] ) ? strtoupper( sanitize_text_field( $data['currency'] ) ) : 'USD';
		$gateway      = ! empty( $data['gateway'] ) ? sanitize_key( (string) $data['gateway'] ) : 'lms_direct';
		$now          = current_time( 'mysql' );

		$wpdb->insert(
			self::table_name(),
			array(
				'tx_ref'        => $ref,
				'user_id'       => $wp_user_id,
				'user_email'    => $email,
				'user_name'     => $name,
				'user_role'     => $wp_user_id ? self::detect_user_role_label( $wp_user_id ) : 'lms_user',
				'item_type'     => 'lms_course',
				'item_id'       => $course_slug ?: 'lms-course',
				'item_title'    => $course_title ?: 'LMS Course',
				'billing_cycle' => 'once',
				'amount'        => $amount,
				'currency'      => $currency,
				'gateway'       => $gateway,
				'gateway_ref'   => $ref,
				'status'        => 'paid',
				'meta_json'     => wp_json_encode( array( 'source' => 'lms_webhook', 'fulfilled_at' => $now ) ),
				'created_at'    => $now,
				'paid_at'       => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Record a plan upgrade into the ledger (used by test/legacy upgrades or admin assignment).
	 */
	public static function log_plan_transaction( $user_id, $plan_slug, $billing_type = 'Month', $gateway = 'test' ) {
		global $wpdb;
		self::maybe_create_table();

		$resolved = self::resolve_item( $user_id, 'plan', $plan_slug, $billing_type );
		if ( is_wp_error( $resolved ) ) {
			return 0;
		}
		$user = get_userdata( $user_id );
		$now  = current_time( 'mysql' );
		$tx_ref = self::generate_tx_ref( 'TPD-PLAN' );

		$wpdb->insert(
			self::table_name(),
			array(
				'tx_ref'        => $tx_ref,
				'user_id'       => (int) $user_id,
				'user_email'    => $user ? $user->user_email : '',
				'user_name'     => $user ? ( $user->display_name ?: $user->user_login ) : 'User #' . $user_id,
				'user_role'     => self::detect_user_role_label( $user_id ),
				'item_type'     => 'plan',
				'item_id'       => $resolved['item_id'],
				'item_title'    => $resolved['item_title'],
				'billing_cycle' => $resolved['billing_cycle'],
				'amount'        => $resolved['amount'],
				'currency'      => $resolved['currency'],
				'gateway'       => $resolved['amount'] <= 0 ? 'free' : sanitize_key( $gateway ),
				'gateway_ref'   => '',
				'status'        => 'paid',
				'meta_json'     => wp_json_encode( array( 'fulfilled_at' => $now ) ),
				'created_at'    => $now,
				'paid_at'       => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * AJAX: Return authoritative item pricing & available gateway options for Checkout Modal.
	 */
	public static function ajax_get_checkout_preview() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'tpd-tool' ) ) );
		}

		$item_type     = isset( $_POST['item_type'] ) ? sanitize_key( wp_unslash( $_POST['item_type'] ) ) : '';
		$item_id       = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';
		$billing_cycle = isset( $_POST['billing_cycle'] ) ? sanitize_key( wp_unslash( $_POST['billing_cycle'] ) ) : 'once';

		$resolved = self::resolve_item( $user_id, $item_type, $item_id, $billing_cycle );
		if ( is_wp_error( $resolved ) ) {
			wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
		}

		$payments = TPD_Tool_Settings::get_payment_settings();
		$mode     = ( isset( $payments['mode'] ) && 'live' === $payments['mode'] ) ? 'live' : 'test';

		wp_send_json_success( array(
			'item'     => $resolved,
			'gateways' => array(
				'mode'           => $mode,
				'test_available' => ( 'test' === $mode ),
				'stripe_ready'   => ! empty( $payments['stripe_enabled'] ) && ! empty( $payments['stripe_secret_key'] ),
				'paypal_ready'   => ! empty( $payments['paypal_enabled'] ) && ! empty( $payments['paypal_client_id'] ) && ! empty( $payments['paypal_secret'] ),
			),
		) );
	}

	/**
	 * AJAX: Create pending transaction and run immediate (Free/Test) or Gateway (Stripe/PayPal) Checkout.
	 */
	public static function ajax_initiate_checkout() {
		global $wpdb;
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		self::maybe_create_table();

		$item_type     = isset( $_POST['item_type'] ) ? sanitize_key( wp_unslash( $_POST['item_type'] ) ) : '';
		$item_id       = isset( $_POST['item_id'] ) ? sanitize_text_field( wp_unslash( $_POST['item_id'] ) ) : '';
		$billing_cycle = isset( $_POST['billing_cycle'] ) ? sanitize_key( wp_unslash( $_POST['billing_cycle'] ) ) : 'once';
		$req_gateway   = isset( $_POST['gateway'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) ) : 'test';
		$return_base   = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : '';

		if ( empty( $return_base ) || false === wp_validate_redirect( $return_base, false ) ) {
			$return_base = TPD_Tool_Roles::is_supplier( $user_id ) && ! TPD_Tool_Roles::is_advisor( $user_id )
				? home_url( '/supplier-dashboard/' )
				: home_url( '/advisor-dashboard/' );
		}
		// Strip stale checkout query params from return_base
		$return_base = remove_query_arg( array( 'tpd_checkout_return', 'tpd_checkout_cancel', 'tx_ref', 'session_id', 'token', 'PayerID' ), $return_base );

		$resolved = self::resolve_item( $user_id, $item_type, $item_id, $billing_cycle );
		if ( is_wp_error( $resolved ) ) {
			wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
		}

		$payments = TPD_Tool_Settings::get_payment_settings();
		$mode     = ( isset( $payments['mode'] ) && 'live' === $payments['mode'] ) ? 'live' : 'test';
		$user     = get_userdata( $user_id );
		$tx_ref   = self::generate_tx_ref();
		$now      = current_time( 'mysql' );
		$amount   = (float) $resolved['amount'];

		// Choose gateway cleanly
		$stripe_ready = ! empty( $payments['stripe_enabled'] ) && ! empty( $payments['stripe_secret_key'] );
		$paypal_ready = ! empty( $payments['paypal_enabled'] ) && ! empty( $payments['paypal_client_id'] ) && ! empty( $payments['paypal_secret'] );

		if ( $amount <= 0 ) {
			$gateway = 'free';
		} elseif ( 'test' === $req_gateway && 'test' === $mode ) {
			$gateway = 'test';
		} elseif ( 'stripe' === $req_gateway ) {
			if ( ! $stripe_ready ) {
				if ( 'test' === $mode ) {
					$gateway = 'test';
				} else {
					wp_send_json_error( array( 'message' => __( 'Stripe is not configured in Super Admin Payment Settings.', 'tpd-tool' ) ) );
				}
			} else {
				$gateway = 'stripe';
			}
		} elseif ( 'paypal' === $req_gateway ) {
			if ( ! $paypal_ready ) {
				if ( 'test' === $mode ) {
					$gateway = 'test';
				} else {
					wp_send_json_error( array( 'message' => __( 'PayPal is not configured in Super Admin Payment Settings.', 'tpd-tool' ) ) );
				}
			} else {
				$gateway = 'paypal';
			}
		} elseif ( 'test' === $mode ) {
			$gateway = 'test';
		} else {
			wp_send_json_error( array( 'message' => __( 'Please select an enabled payment method.', 'tpd-tool' ) ) );
		}

		$is_immediate = ( $amount <= 0 || 'test' === $gateway );
		$meta         = array(
			'mode'       => $mode,
			'extra'      => $resolved['extra'],
			'return_url' => $return_base,
		);

		$inserted = $wpdb->insert(
			self::table_name(),
			array(
				'tx_ref'        => $tx_ref,
				'user_id'       => $user_id,
				'user_email'    => $user ? $user->user_email : '',
				'user_name'     => $user ? ( $user->display_name ?: $user->user_login ) : '',
				'user_role'     => self::detect_user_role_label( $user_id ),
				'item_type'     => $resolved['item_type'],
				'item_id'       => $resolved['item_id'],
				'item_title'    => $resolved['item_title'],
				'billing_cycle' => $resolved['billing_cycle'],
				'amount'        => $amount,
				'currency'      => $resolved['currency'],
				'gateway'       => $gateway,
				'gateway_ref'   => '',
				'status'        => $is_immediate ? 'paid' : 'pending',
				'meta_json'     => wp_json_encode( $meta ),
				'created_at'    => $now,
				'paid_at'       => $is_immediate ? $now : null,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			wp_send_json_error( array( 'message' => __( 'Database error creating checkout transaction.', 'tpd-tool' ) ) );
		}

		$tx = self::get_tx_by_ref( $tx_ref );

		// Immediate fulfillment for Free or Sandbox Test Checkout
		if ( $is_immediate ) {
			$fulfill = self::fulfill_transaction( $tx );
			if ( empty( $fulfill['ok'] ) ) {
				wp_send_json_error( array( 'message' => $fulfill['error'] ) );
			}
			wp_send_json_success( array(
				'status'            => 'paid',
				'immediate'         => true,
				'tx_ref'            => $tx_ref,
				'message'           => $fulfill['message'],
				'sso_url'           => $fulfill['sso_url'] ?? '',
				'reload'            => ! empty( $fulfill['reload'] ),
				'refresh_resources' => ! empty( $fulfill['refresh_resources'] ),
			) );
		}

		// External Gateway Session Creation (Stripe or PayPal)
		if ( 'stripe' === $gateway ) {
			$session = self::create_stripe_checkout_session( $tx, $resolved, $payments, $return_base );
			if ( is_wp_error( $session ) ) {
				$wpdb->update( self::table_name(), array( 'status' => 'failed' ), array( 'id' => (int) $tx->id ), array( '%s' ), array( '%d' ) );
				wp_send_json_error( array( 'message' => $session->get_error_message() ) );
			}
			$wpdb->update(
				self::table_name(),
				array( 'gateway_ref' => $session['id'] ),
				array( 'id' => (int) $tx->id ),
				array( '%s' ),
				array( '%d' )
			);
			wp_send_json_success( array(
				'status'       => 'redirect',
				'immediate'    => false,
				'tx_ref'       => $tx_ref,
				'checkout_url' => $session['url'],
			) );
		}

		if ( 'paypal' === $gateway ) {
			$order = self::create_paypal_order( $tx, $resolved, $payments, $return_base );
			if ( is_wp_error( $order ) ) {
				$wpdb->update( self::table_name(), array( 'status' => 'failed' ), array( 'id' => (int) $tx->id ), array( '%s' ), array( '%d' ) );
				wp_send_json_error( array( 'message' => $order->get_error_message() ) );
			}
			$wpdb->update(
				self::table_name(),
				array( 'gateway_ref' => $order['id'] ),
				array( 'id' => (int) $tx->id ),
				array( '%s' ),
				array( '%d' )
			);
			wp_send_json_success( array(
				'status'       => 'redirect',
				'immediate'    => false,
				'tx_ref'       => $tx_ref,
				'checkout_url' => $order['approve_url'],
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Unable to initialize checkout session.', 'tpd-tool' ) ) );
	}

	/**
	 * Create a Stripe Checkout Session via REST API (pure WP HTTP, no SDK required).
	 */
	private static function create_stripe_checkout_session( $tx, $resolved, $payments, $return_base ) {
		$secret_key  = trim( (string) $payments['stripe_secret_key'] );
		$success_url = add_query_arg( array(
			'tpd_checkout_return' => '1',
			'tx_ref'              => $tx->tx_ref,
		), $return_base ) . '&session_id={CHECKOUT_SESSION_ID}';
		$cancel_url  = add_query_arg( array(
			'tpd_checkout_cancel' => '1',
			'tx_ref'              => $tx->tx_ref,
		), $return_base );

		$unit_amount = (int) round( (float) $tx->amount * 100 );
		$body = array(
			'mode'                                           => 'payment',
			'success_url'                                    => $success_url,
			'cancel_url'                                     => $cancel_url,
			'customer_email'                                 => $tx->user_email,
			'client_reference_id'                            => $tx->tx_ref,
			'metadata[tx_ref]'                               => $tx->tx_ref,
			'metadata[user_id]'                              => (string) $tx->user_id,
			'metadata[item_type]'                            => $tx->item_type,
			'line_items[0][price_data][currency]'            => strtolower( $tx->currency ),
			'line_items[0][price_data][unit_amount]'         => $unit_amount,
			'line_items[0][price_data][product_data][name]'  => $resolved['item_title'],
			'line_items[0][quantity]'                        => 1,
		);

		$res = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $secret_key,
			),
			'body'    => $body,
		) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( $code < 200 || $code >= 300 || empty( $json['url'] ) || empty( $json['id'] ) ) {
			$msg = $json['error']['message'] ?? __( 'Stripe API rejected checkout session request.', 'tpd-tool' );
			return new WP_Error( 'stripe_error', $msg );
		}

		return array(
			'id'  => $json['id'],
			'url' => $json['url'],
		);
	}

	/**
	 * PayPal API base URL according to configured mode.
	 */
	private static function paypal_base_url( $payments ) {
		return ( isset( $payments['mode'] ) && 'live' === $payments['mode'] )
			? 'https://api-m.paypal.com'
			: 'https://api-m.sandbox.paypal.com';
	}

	/**
	 * Obtain PayPal OAuth2 Bearer access token.
	 */
	private static function get_paypal_access_token( $payments ) {
		$base   = self::paypal_base_url( $payments );
		$client = trim( (string) $payments['paypal_client_id'] );
		$secret = trim( (string) $payments['paypal_secret'] );

		$res = wp_remote_post( $base . '/v1/oauth2/token', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $client . ':' . $secret ),
				'Accept'        => 'application/json',
			),
			'body'    => array( 'grant_type' => 'client_credentials' ),
		) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $json['access_token'] ) ) {
			return new WP_Error( 'paypal_auth_error', $json['error_description'] ?? __( 'PayPal authentication failed.', 'tpd-tool' ) );
		}
		return $json['access_token'];
	}

	/**
	 * Create a PayPal Order (v2 Checkout Orders API).
	 */
	private static function create_paypal_order( $tx, $resolved, $payments, $return_base ) {
		$token = self::get_paypal_access_token( $payments );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$base        = self::paypal_base_url( $payments );
		$return_url  = add_query_arg( array( 'tpd_checkout_return' => '1', 'tx_ref' => $tx->tx_ref ), $return_base );
		$cancel_url  = add_query_arg( array( 'tpd_checkout_cancel' => '1', 'tx_ref' => $tx->tx_ref ), $return_base );

		$payload = array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array(
				array(
					'reference_id' => $tx->tx_ref,
					'description'  => substr( $resolved['item_title'], 0, 120 ),
					'amount'       => array(
						'currency_code' => strtoupper( $tx->currency ),
						'value'         => number_format( (float) $tx->amount, 2, '.', '' ),
					),
				),
			),
			'application_context' => array(
				'return_url'  => $return_url,
				'cancel_url'  => $cancel_url,
				'user_action' => 'PAY_NOW',
			),
		);

		$res = wp_remote_post( $base . '/v2/checkout/orders', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $payload ),
		) );

		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $json['id'] ) || empty( $json['links'] ) ) {
			return new WP_Error( 'paypal_order_error', $json['message'] ?? __( 'Failed to create PayPal order.', 'tpd-tool' ) );
		}

		$approve_url = '';
		foreach ( $json['links'] as $link ) {
			if ( isset( $link['rel'] ) && in_array( $link['rel'], array( 'approve', 'payer-action' ), true ) ) {
				$approve_url = $link['href'];
				break;
			}
		}
		if ( '' === $approve_url ) {
			return new WP_Error( 'paypal_link_error', __( 'PayPal approval link missing from response.', 'tpd-tool' ) );
		}

		return array(
			'id'          => $json['id'],
			'approve_url' => $approve_url,
		);
	}

	/**
	 * AJAX: Verify Stripe Session or PayPal Order after user returns from checkout URL.
	 */
	public static function ajax_verify_checkout_return() {
		global $wpdb;
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$tx_ref     = isset( $_POST['tx_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['tx_ref'] ) ) : '';
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$pp_token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

		$tx = self::get_tx_by_ref( $tx_ref );
		if ( ! $tx || ( (int) $tx->user_id !== (int) $user_id && ! current_user_can( 'manage_options' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Checkout transaction not found.', 'tpd-tool' ) ) );
		}

		if ( 'paid' === $tx->status ) {
			$fulfill = self::fulfill_transaction( $tx );
			wp_send_json_success( array(
				'status'            => 'paid',
				'message'           => $fulfill['message'] ?? __( 'Payment confirmed!', 'tpd-tool' ),
				'sso_url'           => $fulfill['sso_url'] ?? '',
				'reload'            => ! empty( $fulfill['reload'] ),
				'refresh_resources' => ! empty( $fulfill['refresh_resources'] ),
			) );
		}

		$payments = TPD_Tool_Settings::get_payment_settings();

		if ( 'stripe' === $tx->gateway ) {
			$sid = $session_id ?: $tx->gateway_ref;
			if ( empty( $sid ) || empty( $payments['stripe_secret_key'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Stripe verification reference missing.', 'tpd-tool' ) ) );
			}

			$res = wp_remote_get( 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( $sid ), array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . trim( $payments['stripe_secret_key'] ) ),
			) );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			$json = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( empty( $json['payment_status'] ) || 'paid' !== $json['payment_status'] ) {
				wp_send_json_error( array( 'message' => __( 'Payment has not completed yet.', 'tpd-tool' ) ) );
			}

			$wpdb->update(
				self::table_name(),
				array(
					'status'      => 'paid',
					'gateway_ref' => $sid,
					'paid_at'     => current_time( 'mysql' ),
				),
				array( 'id' => (int) $tx->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$tx      = self::get_tx_by_id( $tx->id );
			$fulfill = self::fulfill_transaction( $tx );
			if ( empty( $fulfill['ok'] ) ) {
				wp_send_json_error( array( 'message' => $fulfill['error'] ) );
			}

			wp_send_json_success( array(
				'status'            => 'paid',
				'message'           => $fulfill['message'],
				'sso_url'           => $fulfill['sso_url'] ?? '',
				'reload'            => ! empty( $fulfill['reload'] ),
				'refresh_resources' => ! empty( $fulfill['refresh_resources'] ),
			) );
		}

		if ( 'paypal' === $tx->gateway ) {
			$oid   = $pp_token ?: $tx->gateway_ref;
			$token = self::get_paypal_access_token( $payments );
			if ( is_wp_error( $token ) ) {
				wp_send_json_error( array( 'message' => $token->get_error_message() ) );
			}

			$base = self::paypal_base_url( $payments );
			$res  = wp_remote_post( $base . '/v2/checkout/orders/' . rawurlencode( $oid ) . '/capture', array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			) );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
			$json = json_decode( wp_remote_retrieve_body( $res ), true );
			if ( empty( $json['status'] ) || 'COMPLETED' !== $json['status'] ) {
				wp_send_json_error( array( 'message' => $json['message'] ?? __( 'PayPal capture did not complete.', 'tpd-tool' ) ) );
			}

			$wpdb->update(
				self::table_name(),
				array(
					'status'      => 'paid',
					'gateway_ref' => $oid,
					'paid_at'     => current_time( 'mysql' ),
				),
				array( 'id' => (int) $tx->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$tx      = self::get_tx_by_id( $tx->id );
			$fulfill = self::fulfill_transaction( $tx );
			if ( empty( $fulfill['ok'] ) ) {
				wp_send_json_error( array( 'message' => $fulfill['error'] ) );
			}

			wp_send_json_success( array(
				'status'            => 'paid',
				'message'           => $fulfill['message'],
				'sso_url'           => $fulfill['sso_url'] ?? '',
				'reload'            => ! empty( $fulfill['reload'] ),
				'refresh_resources' => ! empty( $fulfill['refresh_resources'] ),
			) );
		}

		wp_send_json_error( array( 'message' => __( 'Unsupported gateway verification.', 'tpd-tool' ) ) );
	}

	/**
	 * Register Stripe Webhook endpoint (/wp-json/tpd/v1/stripe-webhook).
	 */
	public static function register_rest_routes() {
		register_rest_route( 'tpd/v1', '/stripe-webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_stripe_webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Process Stripe checkout.session.completed webhook with HMAC verification when secret configured.
	 */
	public static function handle_stripe_webhook( WP_REST_Request $request ) {
		global $wpdb;
		$payments = TPD_Tool_Settings::get_payment_settings();
		$secret   = ! empty( $payments['stripe_webhook_secret'] ) ? trim( (string) $payments['stripe_webhook_secret'] ) : '';
		$raw_body = $request->get_body();

		if ( '' !== $secret ) {
			$sig_header = (string) $request->get_header( 'stripe-signature' );
			$parts      = array();
			foreach ( explode( ',', $sig_header ) as $kv ) {
				$pair = explode( '=', trim( $kv ), 2 );
				if ( 2 === count( $pair ) ) {
					$parts[ $pair[0] ] = $pair[1];
				}
			}
			if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
				return new WP_REST_Response( array( 'error' => 'Invalid Stripe signature header.' ), 403 );
			}
			if ( abs( time() - (int) $parts['t'] ) > 300 ) {
				return new WP_REST_Response( array( 'error' => 'Timestamp outside tolerance.' ), 403 );
			}
			$signed_payload = $parts['t'] . '.' . $raw_body;
			$expected       = hash_hmac( 'sha256', $signed_payload, $secret );
			if ( ! hash_equals( $expected, $parts['v1'] ) ) {
				return new WP_REST_Response( array( 'error' => 'Signature mismatch.' ), 403 );
			}
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid payload.' ), 400 );
		}

		if ( 'checkout.session.completed' === $event['type'] ) {
			$session = $event['data']['object'] ?? array();
			$tx_ref  = $session['metadata']['tx_ref'] ?? ( $session['client_reference_id'] ?? '' );
			if ( $tx_ref ) {
				$tx = self::get_tx_by_ref( sanitize_text_field( $tx_ref ) );
				if ( $tx && 'paid' !== $tx->status ) {
					$wpdb->update(
						self::table_name(),
						array(
							'status'      => 'paid',
							'gateway_ref' => sanitize_text_field( (string) ( $session['id'] ?? $tx->gateway_ref ) ),
							'paid_at'     => current_time( 'mysql' ),
						),
						array( 'id' => (int) $tx->id ),
						array( '%s', '%s', '%s' ),
						array( '%d' )
					);
					self::fulfill_transaction( self::get_tx_by_id( $tx->id ) );
				}
			}
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Financial summary metrics & analytics for Super Admin Financial pane.
	 */
	public static function get_summary_stats() {
		global $wpdb;
		self::maybe_create_table();
		$table = self::table_name();

		$total_rev   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid'" );
		$rev_30d     = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid' AND paid_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ) ) );
		$rev_7d      = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid' AND paid_at >= %s", gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ) ) );
		$plans_rev   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid' AND item_type = 'plan'" );
		$lms_rev     = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid' AND item_type = 'lms_course'" );
		$res_rev     = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE status = 'paid' AND item_type = 'resource'" );
		$paid_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'paid'" );
		$pend_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" );

		// Last 6 months trend
		$monthly = array();
		for ( $i = 5; $i >= 0; $i-- ) {
			$m_key = gmdate( 'Y-m', strtotime( "-{$i} months" ) );
			$monthly[ $m_key ] = array(
				'month' => $m_key,
				'label' => gmdate( 'M Y', strtotime( $m_key . '-01' ) ),
				'value' => 0.0,
			);
		}
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(paid_at, '%%Y-%%m') AS ym, COALESCE(SUM(amount), 0) AS total FROM {$table} WHERE status = 'paid' AND paid_at >= %s GROUP BY ym",
				gmdate( 'Y-m-01 00:00:00', strtotime( '-5 months' ) )
			)
		);
		foreach ( (array) $rows as $r ) {
			if ( isset( $monthly[ $r->ym ] ) ) {
				$monthly[ $r->ym ]['value'] = (float) $r->total;
			}
		}

		// By Gateway
		$by_gateway = $wpdb->get_results( "SELECT gateway, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total FROM {$table} WHERE status = 'paid' GROUP BY gateway ORDER BY total DESC" );

		return array(
			'total_revenue'    => $total_rev,
			'revenue_30d'      => $rev_30d,
			'revenue_7d'       => $rev_7d,
			'plans_revenue'    => $plans_rev,
			'lms_revenue'      => $lms_rev,
			'resources_revenue'=> $res_rev,
			'paid_count'       => $paid_count,
			'pending_count'    => $pend_count,
			'avg_order'        => $paid_count > 0 ? round( $total_rev / $paid_count, 2 ) : 0.0,
			'monthly'          => array_values( $monthly ),
			'by_gateway'       => $by_gateway,
		);
	}

	/**
	 * Query filtered ledger transactions.
	 */
	public static function get_transactions( $args = array() ) {
		global $wpdb;
		self::maybe_create_table();
		$table = self::table_name();

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['item_type'] ) && 'all' !== $args['item_type'] ) {
			$where[]  = 'item_type = %s';
			$params[] = sanitize_key( $args['item_type'] );
		}
		if ( ! empty( $args['status'] ) && 'all' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(tx_ref LIKE %s OR user_name LIKE %s OR user_email LIKE %s OR item_title LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 100;
		$sql   = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT ' . max( 1, min( 500, $limit ) );

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * AJAX (Admin): Update a transaction status (e.g. Mark Paid & Fulfill or Refund).
	 */
	public static function ajax_admin_update_tx_status() {
		global $wpdb;
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$tx_id  = isset( $_POST['tx_id'] ) ? absint( $_POST['tx_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! in_array( $status, array( 'paid', 'refunded', 'failed' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid status.' ) );
		}

		$tx = self::get_tx_by_id( $tx_id );
		if ( ! $tx ) {
			wp_send_json_error( array( 'message' => 'Transaction not found.' ) );
		}

		$update = array( 'status' => $status );
		$format = array( '%s' );
		if ( 'paid' === $status && empty( $tx->paid_at ) ) {
			$update['paid_at'] = current_time( 'mysql' );
			$format[]          = '%s';
		}

		$wpdb->update( self::table_name(), $update, array( 'id' => $tx_id ), $format, array( '%d' ) );

		$msg = sprintf( __( 'Transaction %1$s marked %2$s.', 'tpd-tool' ), $tx->tx_ref, strtoupper( $status ) );
		if ( 'paid' === $status ) {
			$fulfill = self::fulfill_transaction( self::get_tx_by_id( $tx_id ) );
			if ( ! empty( $fulfill['ok'] ) && ! empty( $fulfill['message'] ) ) {
				$msg = $fulfill['message'];
			} elseif ( ! empty( $fulfill['error'] ) ) {
				wp_send_json_error( array( 'message' => $fulfill['error'] ) );
			}
		}

		wp_send_json_success( array(
			'message' => $msg,
			'status'  => $status,
		) );
	}

	/**
	 * Handle Financial Ledger CSV Export for Super Admin.
	 */
	public static function handle_csv_export() {
		if ( ! isset( $_GET['tpd_action'] ) || 'export_financial_csv' !== $_GET['tpd_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'tpd-tool' ) );
		}
		check_admin_referer( 'tpd_export_financial', 'nonce' );

		$rows = self::get_transactions( array( 'limit' => 500 ) );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tpd-financial-ledger-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array(
			'Reference',
			'Date',
			'Paid Date',
			'User ID',
			'User Name',
			'User Email',
			'User Role',
			'Item Type',
			'Item ID',
			'Item Title',
			'Billing Cycle',
			'Amount',
			'Currency',
			'Gateway',
			'Gateway Ref',
			'Status',
		) );

		foreach ( $rows as $r ) {
			fputcsv( $out, array(
				$r->tx_ref,
				$r->created_at,
				$r->paid_at ?: '',
				$r->user_id,
				$r->user_name,
				$r->user_email,
				$r->user_role,
				$r->item_type,
				$r->item_id,
				$r->item_title,
				$r->billing_cycle,
				$r->amount,
				$r->currency,
				$r->gateway,
				$r->gateway_ref,
				$r->status,
			) );
		}
		fclose( $out );
		exit;
	}
}

TPD_Tool_Financial::init();
