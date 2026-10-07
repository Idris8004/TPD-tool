<?php
/**
 * Registration & Authentication Engine for TPD Tool
 * Handles multi-step Travel Advisor (Pro User) and Supplier registration workflows,
 * Smart Cross-Role Email Detection & Dual-Portal Account Activation (Same or Separate Credentials),
 * Enterprise Password Hashing (wp_hash_password / wp_check_password), Brute-Force Rate Limiting,
 * Strict Role-Isolated Login, and Paired CPT Creation (travel_advisor & supplier_listing).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Registration {

	const MAX_LOGIN_ATTEMPTS = 5;
	const LOCKOUT_SECONDS    = 900; // 15 minutes

	public static function init() {
		add_action( 'wp_ajax_tpd_register_advisor', array( __CLASS__, 'ajax_register_advisor' ) );
		add_action( 'wp_ajax_nopriv_tpd_register_advisor', array( __CLASS__, 'ajax_register_advisor' ) );

		add_action( 'wp_ajax_tpd_register_supplier', array( __CLASS__, 'ajax_register_supplier' ) );
		add_action( 'wp_ajax_nopriv_tpd_register_supplier', array( __CLASS__, 'ajax_register_supplier' ) );

		add_action( 'wp_ajax_tpd_check_registration_email', array( __CLASS__, 'ajax_check_registration_email' ) );
		add_action( 'wp_ajax_nopriv_tpd_check_registration_email', array( __CLASS__, 'ajax_check_registration_email' ) );

		add_action( 'wp_ajax_tpd_user_login', array( __CLASS__, 'ajax_user_login' ) );
		add_action( 'wp_ajax_nopriv_tpd_user_login', array( __CLASS__, 'ajax_user_login' ) );

		add_action( 'wp_ajax_tpd_forgot_password', array( __CLASS__, 'ajax_forgot_password' ) );
		add_action( 'wp_ajax_nopriv_tpd_forgot_password', array( __CLASS__, 'ajax_forgot_password' ) );

		add_shortcode( 'tpd_advisor_registration', array( __CLASS__, 'render_advisor_registration' ) );
		add_shortcode( 'tpd_supplier_registration', array( __CLASS__, 'render_supplier_registration' ) );
	}

	/**
	 * Helper: Check if a secondary portal username is already taken by another user
	 */
	public static function is_portal_username_taken( $username, $exclude_user_id = 0 ) {
		$existing = get_user_by( 'login', $username );
		if ( $existing && (int) $existing->ID !== (int) $exclude_user_id ) {
			return true;
		}

		$meta_users = get_users( array(
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key'   => 'tpd_advisor_username',
					'value' => $username,
				),
				array(
					'key'   => 'tpd_supplier_username',
					'value' => $username,
				),
			),
			'fields' => 'ID',
		) );

		foreach ( $meta_users as $uid ) {
			if ( (int) $uid !== (int) $exclude_user_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Helper: Verify a user's existing password (checks both WP primary hash and portal-specific hashes)
	 */
	public static function verify_existing_user_password( $user, $plain_password ) {
		if ( ! $user || '' === $plain_password ) {
			return false;
		}
		if ( wp_check_password( $plain_password, $user->user_pass, $user->ID ) ) {
			return true;
		}
		$adv_hash = get_user_meta( $user->ID, 'tpd_advisor_password_hash', true );
		if ( ! empty( $adv_hash ) && wp_check_password( $plain_password, $adv_hash, $user->ID ) ) {
			return true;
		}
		$supp_hash = get_user_meta( $user->ID, 'tpd_supplier_password_hash', true );
		if ( ! empty( $supp_hash ) && wp_check_password( $plain_password, $supp_hash, $user->ID ) ) {
			return true;
		}
		return false;
	}

	/**
	 * AJAX Handler: Check Email on Step 1 of Registration for Same-Role vs Cross-Role Detection
	 */
	public static function ajax_check_registration_email() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$target_role = isset( $_POST['target_role'] ) ? sanitize_key( wp_unslash( $_POST['target_role'] ) ) : 'travel_advisor';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'tpd-tool' ) ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_success( array(
				'status' => 'available',
			) );
		}

		$is_adv  = TPD_Tool_Roles::is_advisor( $user->ID );
		$is_supp = TPD_Tool_Roles::is_supplier( $user->ID );

		// 1. Already registered in the SAME role
		if ( ( 'travel_advisor' === $target_role && $is_adv ) || ( 'supplier' === $target_role && $is_supp ) ) {
			$role_label = ( 'supplier' === $target_role ) ? __( 'Supplier Partner', 'tpd-tool' ) : __( 'Travel Advisor', 'tpd-tool' );
			$login_url  = ( 'supplier' === $target_role ) ? home_url( '/supplier-login/' ) : home_url( '/advisor-login/' );

			wp_send_json_success( array(
				'status'    => 'same_role_exists',
				'login_url' => $login_url,
				'message'   => sprintf(
					/* translators: %s: Role label */
					__( 'This email is already registered as a %s. Please log in to your dashboard instead.', 'tpd-tool' ),
					$role_label
				),
			) );
		}

		// 2. Registered in the OPPOSITE role — Offer Cross-Role Dual Portal Upgrade!
		$existing_role_label = $is_supp ? __( 'Supplier Partner', 'tpd-tool' ) : __( 'Travel Advisor', 'tpd-tool' );
		$target_role_label   = ( 'supplier' === $target_role ) ? __( 'Supplier Partner', 'tpd-tool' ) : __( 'Travel Advisor', 'tpd-tool' );
		$logged_in_same_user = ( is_user_logged_in() && (int) get_current_user_id() === (int) $user->ID );

		wp_send_json_success( array(
			'status'              => 'cross_role_available',
			'existing_role_label' => $existing_role_label,
			'target_role_label'   => $target_role_label,
			'existing_username'   => $user->user_login,
			'first_name'          => $user->first_name,
			'last_name'           => $user->last_name,
			'phone'               => get_user_meta( $user->ID, 'tpd_phone', true ),
			'country'             => get_user_meta( $user->ID, 'tpd_country', true ) ?: get_user_meta( $user->ID, 'tpd_country_of_residence', true ),
			'logged_in_same_user' => $logged_in_same_user,
			'message'             => sprintf(
				/* translators: 1: Existing role, 2: Target role */
				__( 'Your email is already registered as a %1$s. Do you want to continue as a %2$s also? Complete the required information below to access both dashboards.', 'tpd-tool' ),
				$existing_role_label,
				$target_role_label
			),
		) );
	}

	/**
	 * AJAX Handler: Register Travel Advisor (Pro User)
	 * Supports New Account Creation AND Cross-Role Activation for existing Supplier Users.
	 */
	public static function ajax_register_advisor() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		// 1. Sanitize Account & Personal Details
		$first_name          = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name           = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email               = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$username            = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$password            = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm_password    = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : $password;
		$tier                = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : 'basic';
		$billing_cycle       = isset( $_POST['billing_cycle'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cycle'] ) ) : 'Month';
		$payment_gateway     = isset( $_POST['payment_gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_gateway'] ) ) : 'Stripe';
		$confirm_cross_role  = ! empty( $_POST['confirm_cross_role'] );
		$credential_mode     = isset( $_POST['credential_mode'] ) ? sanitize_key( wp_unslash( $_POST['credential_mode'] ) ) : 'same';
		$existing_acct_pwd   = isset( $_POST['existing_account_password'] ) ? (string) wp_unslash( $_POST['existing_account_password'] ) : '';

		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'First name, last name, and email address are required.', 'tpd-tool' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'tpd-tool' ) ) );
		}

		$existing_user = get_user_by( 'email', $email );
		$is_cross_role = false;

		if ( $existing_user ) {
			// Check if already a Travel Advisor
			if ( TPD_Tool_Roles::is_advisor( $existing_user->ID ) ) {
				wp_send_json_error( array(
					'message'   => __( 'Your email is already registered as a Travel Advisor. Please log in to your Advisor Dashboard.', 'tpd-tool' ),
					'login_url' => home_url( '/advisor-login/' ),
				) );
			}

			// Existing Supplier signing up for Travel Advisor Portal
			if ( ! $confirm_cross_role ) {
				wp_send_json_error( array(
					'cross_role_prompt'   => true,
					'existing_role_label' => __( 'Supplier Partner', 'tpd-tool' ),
					'target_role_label'   => __( 'Travel Advisor', 'tpd-tool' ),
					'existing_username'   => $existing_user->user_login,
					'message'             => __( 'Your email is already registered as a Supplier Partner. Confirm below to also register as a Travel Advisor and choose whether to use the same or separate login credentials.', 'tpd-tool' ),
				) );
			}

			$is_cross_role = true;
			$user_id       = $existing_user->ID;

			// Security Verification: Ensure caller owns the existing account
			$logged_in_as_owner = ( is_user_logged_in() && (int) get_current_user_id() === (int) $user_id );
			if ( ! $logged_in_as_owner ) {
				$pwd_to_verify = ! empty( $existing_acct_pwd ) ? $existing_acct_pwd : $password;
				if ( empty( $pwd_to_verify ) || ! self::verify_existing_user_password( $existing_user, $pwd_to_verify ) ) {
					wp_send_json_error( array(
						'message' => __( 'Security verification failed: Please enter your correct existing Supplier password to link and activate your Travel Advisor dashboard.', 'tpd-tool' ),
					) );
				}
			}

			// Handle Same vs Separate Credentials for Advisor Portal
			if ( 'separate' === $credential_mode ) {
				if ( empty( $password ) || strlen( $password ) < 6 ) {
					wp_send_json_error( array( 'message' => __( 'Your new Advisor Portal password must be at least 6 characters long.', 'tpd-tool' ) ) );
				}
				if ( $password !== $confirm_password ) {
					wp_send_json_error( array( 'message' => __( 'Advisor Portal passwords do not match.', 'tpd-tool' ) ) );
				}
				if ( ! empty( $username ) && self::is_portal_username_taken( $username, $user_id ) ) {
					wp_send_json_error( array( 'message' => __( 'This Advisor username is already taken by another account. Please choose another.', 'tpd-tool' ) ) );
				}

				// Securely hash the separate Advisor password with wp_hash_password()
				update_user_meta( $user_id, 'tpd_advisor_password_hash', wp_hash_password( $password ) );
				if ( ! empty( $username ) ) {
					update_user_meta( $user_id, 'tpd_advisor_username', $username );
				}
			} else {
				// Use same username & password across both dashboards
				delete_user_meta( $user_id, 'tpd_advisor_password_hash' );
				if ( ! empty( $username ) && $username !== $existing_user->user_login && ! self::is_portal_username_taken( $username, $user_id ) ) {
					update_user_meta( $user_id, 'tpd_advisor_username', $username );
				}
			}

			// Grant Travel Advisor role while keeping existing Supplier role!
			$existing_user->add_role( 'travel_advisor' );
		} else {
			// Brand-New User Registration
			if ( empty( $password ) ) {
				wp_send_json_error( array( 'message' => __( 'Password is required.', 'tpd-tool' ) ) );
			}
			if ( $password !== $confirm_password ) {
				wp_send_json_error( array( 'message' => __( 'Passwords do not match. Please verify your password confirmation.', 'tpd-tool' ) ) );
			}
			if ( strlen( $password ) < 6 ) {
				wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters long.', 'tpd-tool' ) ) );
			}

			if ( empty( $username ) ) {
				$username = sanitize_user( current( explode( '@', $email ) ) );
				if ( self::is_portal_username_taken( $username ) ) {
					$username .= '_' . wp_rand( 100, 999 );
				}
			} elseif ( self::is_portal_username_taken( $username ) ) {
				wp_send_json_error( array( 'message' => __( 'This username is already taken. Please choose another.', 'tpd-tool' ) ) );
			}

			// Create WordPress User (hashes password via wp_hash_password internally)
			$user_id = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}

			$display_name = trim( $first_name . ' ' . $last_name );
			wp_update_user( array(
				'ID'           => $user_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name ?: $username,
				'role'         => 'travel_advisor',
			) );
		}

		$display_name = trim( $first_name . ' ' . $last_name );

		// 2. Sanitize Agency & Credential Details
		$phone_code          = isset( $_POST['phone_country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_country_code'] ) ) : '';
		$raw_phone           = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone               = trim( ( $phone_code ? $phone_code . ' ' : '' ) . $raw_phone );
		$profile_handle      = isset( $_POST['profile_handle'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_handle'] ) ) : ( $username ?: $display_name );
		$country             = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'United States';
		$location            = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : $country;
		$agency_name         = isset( $_POST['agency_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_name'] ) ) : '';
		$agency_address      = isset( $_POST['agency_address'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_address'] ) ) : '';
		$business_structure  = isset( $_POST['business_structure'] ) ? sanitize_text_field( wp_unslash( $_POST['business_structure'] ) ) : '';
		$agency_structure    = isset( $_POST['agency_structure'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_structure'] ) ) : '';
		$years_experience    = isset( $_POST['years_experience'] ) ? sanitize_text_field( wp_unslash( $_POST['years_experience'] ) ) : '';
		$clients_per_year    = isset( $_POST['clients_per_year'] ) ? sanitize_text_field( wp_unslash( $_POST['clients_per_year'] ) ) : '';
		$agency_advisors_cnt = isset( $_POST['agency_advisors_count'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_advisors_count'] ) ) : '';
		$sales_volume        = isset( $_POST['sales_volume'] ) ? sanitize_text_field( wp_unslash( $_POST['sales_volume'] ) ) : '';
		$agency_sales_volume = isset( $_POST['agency_sales_volume'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_sales_volume'] ) ) : '';
		$consortia           = isset( $_POST['consortia'] ) ? sanitize_text_field( wp_unslash( $_POST['consortia'] ) ) : '';
		$host_agency         = isset( $_POST['host_agency'] ) ? sanitize_text_field( wp_unslash( $_POST['host_agency'] ) ) : '';

		// Accreditations
		$clia_num = ! empty( $_POST['has_clia'] ) && isset( $_POST['clia_num'] ) ? sanitize_text_field( wp_unslash( $_POST['clia_num'] ) ) : ( isset( $_POST['clia_num'] ) ? sanitize_text_field( wp_unslash( $_POST['clia_num'] ) ) : '' );
		$iata_num = ! empty( $_POST['has_iata'] ) && isset( $_POST['iata_num'] ) ? sanitize_text_field( wp_unslash( $_POST['iata_num'] ) ) : ( isset( $_POST['iata_num'] ) ? sanitize_text_field( wp_unslash( $_POST['iata_num'] ) ) : '' );
		$arc_num  = ! empty( $_POST['has_arc'] )  && isset( $_POST['arc_num'] )  ? sanitize_text_field( wp_unslash( $_POST['arc_num'] ) )  : ( isset( $_POST['arc_num'] ) ? sanitize_text_field( wp_unslash( $_POST['arc_num'] ) ) : '' );
		$true_num = ! empty( $_POST['has_true'] ) && isset( $_POST['true_num'] ) ? sanitize_text_field( wp_unslash( $_POST['true_num'] ) ) : ( isset( $_POST['true_num'] ) ? sanitize_text_field( wp_unslash( $_POST['true_num'] ) ) : '' );

		// Save User Meta (100% synced for CSV export, Admin Hub, and Dashboard)
		update_user_meta( $user_id, 'tpd_account_status', 'active' );
		update_user_meta( $user_id, 'tpd_profile_handle', $profile_handle );
		update_user_meta( $user_id, 'tpd_country', $country );
		if ( $phone ) {
			update_user_meta( $user_id, 'tpd_phone', $phone );
		}
		update_user_meta( $user_id, 'tpd_location', $location );
		update_user_meta( $user_id, 'tpd_agency_name', $agency_name );
		update_user_meta( $user_id, 'tpd_agency_address', $agency_address );
		update_user_meta( $user_id, 'tpd_business_structure', $business_structure );
		update_user_meta( $user_id, 'tpd_agency_structure', $agency_structure );
		update_user_meta( $user_id, 'tpd_years_experience', $years_experience );
		update_user_meta( $user_id, 'tpd_clients_per_year', $clients_per_year );
		update_user_meta( $user_id, 'tpd_agency_advisors_count', $agency_advisors_cnt );
		update_user_meta( $user_id, 'tpd_personal_sales_volume', $sales_volume );
		update_user_meta( $user_id, 'tpd_agency_sales_volume', $agency_sales_volume );
		update_user_meta( $user_id, 'tpd_consortia', $consortia );
		update_user_meta( $user_id, 'tpd_host_agency', $host_agency );
		update_user_meta( $user_id, 'tpd_clia_num', $clia_num );
		update_user_meta( $user_id, 'tpd_iata_num', $iata_num );
		update_user_meta( $user_id, 'tpd_arc_num', $arc_num );
		update_user_meta( $user_id, 'tpd_true_num', $true_num );

		// Set User Tier & Record Plan Subscription
		$final_tier = TPD_Tool_Tiers::set_user_tier( $user_id, $tier );
		if ( class_exists( 'TPD_Tool_Settings' ) ) {
			TPD_Tool_Settings::record_subscription( $user_id, $final_tier, $billing_cycle, $payment_gateway );
		}

		// 4. Create Paired CPT Record (travel_advisor)
		$post_id = wp_insert_post( array(
			'post_type'    => 'travel_advisor',
			'post_title'   => $display_name ?: $username,
			'post_name'    => sanitize_title( $profile_handle ?: $display_name ),
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => $user_id,
		) );

		if ( ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'tpd_assigned_user', $user_id );
			update_post_meta( $post_id, 'tpd_profile_handle', $profile_handle );
			update_post_meta( $post_id, 'tpd_country', $country );
			update_post_meta( $post_id, 'tpd_agency_name', $agency_name );
			update_post_meta( $post_id, 'tpd_agency_address', $agency_address );
			update_post_meta( $post_id, 'tpd_business_structure', $business_structure );
			update_post_meta( $post_id, 'tpd_agency_structure', $agency_structure );
			update_post_meta( $post_id, 'tpd_years_experience', $years_experience );
			update_post_meta( $post_id, 'tpd_clients_per_year', $clients_per_year );
			update_post_meta( $post_id, 'tpd_agency_advisors_count', $agency_advisors_cnt );
			update_post_meta( $post_id, 'tpd_personal_sales_volume', $sales_volume );
			update_post_meta( $post_id, 'tpd_agency_sales_volume', $agency_sales_volume );
			update_post_meta( $post_id, 'tpd_consortia', $consortia );
			update_post_meta( $post_id, 'tpd_host_agency', $host_agency );
			update_post_meta( $post_id, 'tpd_phone', $phone );
			update_post_meta( $post_id, 'tpd_location', $location );
			update_post_meta( $post_id, 'tpd_user_tier', $final_tier );
			update_post_meta( $post_id, 'tpd_clia_number', $clia_num );
			update_post_meta( $post_id, 'tpd_iata_number', $iata_num );
			update_post_meta( $post_id, 'tpd_arc_number', $arc_num );
			update_post_meta( $post_id, 'tpd_true_number', $true_num );

			if ( class_exists( 'TPD_Tool_CPT' ) ) {
				TPD_Tool_CPT::save_dynamic_fields_submission( 'travel_advisor', $post_id, $user_id );
			}
		}

		// 5. Automatically Authenticate the User
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		// 6. Send Welcome Email
		$subject = __( 'Welcome to Travel Partner Directory by TARC!', 'tpd-tool' );
		$message = sprintf(
			"Dear %s,\n\nWelcome to Travel Partner Directory! Your Travel Advisor account (%s) is now active.\n\nAccess your Advisor Dashboard here:\n%s\n\nThank you for partnering with TARC!\nTravel Partner Directory Team",
			$first_name,
			strtoupper( $final_tier ),
			home_url( '/advisor-dashboard/' )
		);
		wp_mail( $email, $subject, $message );

		wp_send_json_success( array(
			'message'      => $is_cross_role
				? __( 'Dual-Portal Activated! Your Travel Advisor & Supplier dashboards are now both enabled. Opening Advisor Dashboard...', 'tpd-tool' )
				: __( 'Registration complete! Opening your Advisor Dashboard...', 'tpd-tool' ),
			'redirect_url' => home_url( '/advisor-dashboard/' ),
		) );
	}

	/**
	 * AJAX Handler: Register Supplier Partner
	 * Supports New Account Creation AND Cross-Role Activation for existing Travel Advisor Users.
	 */
	public static function ajax_register_supplier() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$first_name         = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : ( isset( $_POST['rep_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rep_first_name'] ) ) : '' );
		$last_name          = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : ( isset( $_POST['rep_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rep_last_name'] ) ) : '' );
		$email              = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$username           = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$phone_code         = isset( $_POST['phone_country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_country_code'] ) ) : '';
		$raw_phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone              = trim( ( $phone_code ? $phone_code . ' ' : '' ) . $raw_phone );
		$position_title     = isset( $_POST['position_title'] ) ? sanitize_text_field( wp_unslash( $_POST['position_title'] ) ) : '';
		$company_name       = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$country            = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : ( isset( $_POST['country_of_residence'] ) ? sanitize_text_field( wp_unslash( $_POST['country_of_residence'] ) ) : '' );
		$password           = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$confirm_password   = isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : $password;
		$tier               = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : 'basic';
		$billing_cycle      = isset( $_POST['billing_cycle'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cycle'] ) ) : 'Month';
		$confirm_cross_role = ! empty( $_POST['confirm_cross_role'] );
		$credential_mode    = isset( $_POST['credential_mode'] ) ? sanitize_key( wp_unslash( $_POST['credential_mode'] ) ) : 'same';
		$existing_acct_pwd  = isset( $_POST['existing_account_password'] ) ? (string) wp_unslash( $_POST['existing_account_password'] ) : '';

		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $phone ) || empty( $company_name ) || empty( $country ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required (*) fields, including First Name, Last Name, Phone, Email, Supplier/Company Name, and Country.', 'tpd-tool' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'tpd-tool' ) ) );
		}

		$existing_user = get_user_by( 'email', $email );
		$is_cross_role = false;

		if ( $existing_user ) {
			// Check if already a Supplier
			if ( TPD_Tool_Roles::is_supplier( $existing_user->ID ) ) {
				wp_send_json_error( array(
					'message'   => __( 'Your email is already registered as a Supplier Partner. Please log in to your Supplier Dashboard.', 'tpd-tool' ),
					'login_url' => home_url( '/supplier-login/' ),
				) );
			}

			// Existing Travel Advisor signing up for Supplier Portal
			if ( ! $confirm_cross_role ) {
				wp_send_json_error( array(
					'cross_role_prompt'   => true,
					'existing_role_label' => __( 'Travel Advisor', 'tpd-tool' ),
					'target_role_label'   => __( 'Supplier Partner', 'tpd-tool' ),
					'existing_username'   => $existing_user->user_login,
					'message'             => __( 'Your email is already registered as a Travel Advisor. Confirm below to also register as a Supplier Partner and choose whether to use the same or separate login credentials.', 'tpd-tool' ),
				) );
			}

			$is_cross_role = true;
			$user_id       = $existing_user->ID;

			// Security Verification: Ensure caller owns the existing account
			$logged_in_as_owner = ( is_user_logged_in() && (int) get_current_user_id() === (int) $user_id );
			if ( ! $logged_in_as_owner ) {
				$pwd_to_verify = ! empty( $existing_acct_pwd ) ? $existing_acct_pwd : $password;
				if ( empty( $pwd_to_verify ) || ! self::verify_existing_user_password( $existing_user, $pwd_to_verify ) ) {
					wp_send_json_error( array(
						'message' => __( 'Security verification failed: Please enter your correct existing Travel Advisor password to link and activate your Supplier Partner dashboard.', 'tpd-tool' ),
					) );
				}
			}

			// Handle Same vs Separate Credentials for Supplier Portal
			if ( 'separate' === $credential_mode ) {
				if ( empty( $password ) || strlen( $password ) < 6 ) {
					wp_send_json_error( array( 'message' => __( 'Your new Supplier Portal password must be at least 6 characters long.', 'tpd-tool' ) ) );
				}
				if ( $password !== $confirm_password ) {
					wp_send_json_error( array( 'message' => __( 'Supplier Portal passwords do not match.', 'tpd-tool' ) ) );
				}
				if ( ! empty( $username ) && self::is_portal_username_taken( $username, $user_id ) ) {
					wp_send_json_error( array( 'message' => __( 'This Supplier username is already taken by another account. Please choose another.', 'tpd-tool' ) ) );
				}

				// Securely hash the separate Supplier password with wp_hash_password()
				update_user_meta( $user_id, 'tpd_supplier_password_hash', wp_hash_password( $password ) );
				if ( ! empty( $username ) ) {
					update_user_meta( $user_id, 'tpd_supplier_username', $username );
				}
			} else {
				// Use same username & password across both dashboards
				delete_user_meta( $user_id, 'tpd_supplier_password_hash' );
				if ( ! empty( $username ) && $username !== $existing_user->user_login && ! self::is_portal_username_taken( $username, $user_id ) ) {
					update_user_meta( $user_id, 'tpd_supplier_username', $username );
				}
			}

			// Grant Supplier role while keeping existing Travel Advisor role!
			$existing_user->add_role( 'supplier' );
		} else {
			// Brand-New Supplier User Registration
			if ( empty( $password ) ) {
				wp_send_json_error( array( 'message' => __( 'Password is required.', 'tpd-tool' ) ) );
			}
			if ( $password !== $confirm_password ) {
				wp_send_json_error( array( 'message' => __( 'Passwords do not match. Please check your password confirmation.', 'tpd-tool' ) ) );
			}
			if ( strlen( $password ) < 6 ) {
				wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters long.', 'tpd-tool' ) ) );
			}

			if ( empty( $username ) ) {
				$username = sanitize_user( current( explode( '@', $email ) ) );
				if ( self::is_portal_username_taken( $username ) ) {
					$username .= '_' . wp_rand( 100, 999 );
				}
			} elseif ( self::is_portal_username_taken( $username ) ) {
				wp_send_json_error( array( 'message' => __( 'This username is already taken. Please choose another.', 'tpd-tool' ) ) );
			}

			$user_id = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}

			$display_name = trim( $first_name . ' ' . $last_name );
			wp_update_user( array(
				'ID'           => $user_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name ?: $username,
				'role'         => 'supplier',
			) );
		}

		$display_name = trim( $first_name . ' ' . $last_name );

		update_user_meta( $user_id, 'tpd_account_status', 'active' );
		update_user_meta( $user_id, 'tpd_phone', $phone );
		update_user_meta( $user_id, 'tpd_position_title', $position_title );
		update_user_meta( $user_id, 'tpd_company_name', $company_name );
		update_user_meta( $user_id, 'tpd_country_of_residence', $country );

		$final_tier = TPD_Tool_Tiers::set_user_tier( $user_id, $tier );
		if ( class_exists( 'TPD_Tool_Settings' ) ) {
			TPD_Tool_Settings::record_subscription( $user_id, $final_tier, $billing_cycle, 'Stripe' );
		}

		// Create Paired supplier_listing CPT Post so Supplier Dashboard & WP Admin are 100% connected
		$listing_id = wp_insert_post( array(
			'post_type'    => 'supplier_listing',
			'post_title'   => $company_name ?: $display_name,
			'post_content' => '',
			'post_status'  => 'publish',
			'post_author'  => $user_id,
		) );

		if ( ! is_wp_error( $listing_id ) ) {
			update_post_meta( $listing_id, 'tpd_assigned_user', $user_id );
			update_post_meta( $listing_id, 'tpd_company_name', $company_name );
			update_post_meta( $listing_id, 'tpd_position_title', $position_title );
			update_post_meta( $listing_id, 'tpd_primary_rep_phone', $phone );
			update_post_meta( $listing_id, 'tpd_country_of_residence', $country );
			update_post_meta( $listing_id, 'tpd_headquarters', $country );
			update_post_meta( $listing_id, 'tpd_user_tier', $final_tier );

			// Initialize Virtual Office team with the registering representative
			update_post_meta( $listing_id, '_tpd_team_members', array(
				array(
					'name'      => $display_name,
					'title'     => $position_title ?: 'Trade Representative',
					'email'     => $email,
					'phone'     => $phone,
					'photo_url' => '',
				),
			) );

			if ( class_exists( 'TPD_Tool_CPT' ) ) {
				TPD_Tool_CPT::save_dynamic_fields_submission( 'supplier_listing', $listing_id, $user_id );
			}
		}

		// Authenticate User
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		wp_send_json_success( array(
			'message'      => $is_cross_role
				? __( 'Dual-Portal Activated! Your Supplier Partner & Travel Advisor dashboards are now both enabled. Opening Supplier Dashboard...', 'tpd-tool' )
				: __( 'Supplier account & company listing created! Opening your Supplier Dashboard...', 'tpd-tool' ),
			'redirect_url' => home_url( '/supplier-dashboard/' ),
		) );
	}

	/**
	 * AJAX Handler: Dedicated Frontend Login (Advisor & Supplier)
	 * Enforces:
	 * - Brute-Force Rate Limiting (5 attempts / 15 mins)
	 * - Primary & Secondary Portal-Specific Username/Password Verification (wp_check_password)
	 * - Strict Role Isolation (Advisor Login requires travel_advisor; Supplier Login requires supplier)
	 * - Active Account Status Verification
	 */
	public static function ajax_user_login() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$login_input = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		$password    = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$remember    = ! empty( $_POST['remember'] );
		$portal_type = isset( $_POST['portal_type'] ) ? sanitize_key( wp_unslash( $_POST['portal_type'] ) ) : 'advisor';

		if ( empty( $login_input ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter both your username/email and password.', 'tpd-tool' ) ) );
		}

		// 1. Brute-Force Rate Limit Check
		$client_ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		$rate_key     = 'tpd_login_lock_' . md5( $client_ip . '|' . strtolower( $login_input ) );
		$failed_count = (int) get_transient( $rate_key );

		if ( $failed_count >= self::MAX_LOGIN_ATTEMPTS ) {
			wp_send_json_error( array(
				'message' => __( 'Too many failed login attempts. For your security, please wait 15 minutes before trying again.', 'tpd-tool' ),
			) );
		}

		// 2. Resolve User by Email, Portal-Specific Username, or Primary WP Username
		$user = null;
		if ( is_email( $login_input ) ) {
			$user = get_user_by( 'email', $login_input );
		}

		if ( ! $user ) {
			$portal_meta_key = ( 'supplier' === $portal_type ) ? 'tpd_supplier_username' : 'tpd_advisor_username';
			$matched_users   = get_users( array(
				'meta_key'   => $portal_meta_key,
				'meta_value' => $login_input,
				'number'     => 1,
			) );
			if ( ! empty( $matched_users ) ) {
				$user = $matched_users[0];
			}
		}

		if ( ! $user ) {
			$user = get_user_by( 'login', $login_input );
		}

		if ( ! $user ) {
			set_transient( $rate_key, $failed_count + 1, self::LOCKOUT_SECONDS );
			wp_send_json_error( array( 'message' => __( 'Invalid username or password', 'tpd-tool' ) ) );
		}

		// 3. Verify Password using WordPress Cryptographic Hash Check (wp_check_password)
		$password_valid   = false;
		$portal_hash_meta = ( 'supplier' === $portal_type ) ? 'tpd_supplier_password_hash' : 'tpd_advisor_password_hash';
		$portal_pwd_hash  = get_user_meta( $user->ID, $portal_hash_meta, true );

		if ( ! empty( $portal_pwd_hash ) ) {
			// Check portal-specific password hash first, or primary WP password hash
			if ( wp_check_password( $password, $portal_pwd_hash, $user->ID ) || wp_check_password( $password, $user->user_pass, $user->ID ) ) {
				$password_valid = true;
			}
		} else {
			if ( wp_check_password( $password, $user->user_pass, $user->ID ) ) {
				$password_valid = true;
			}
		}

		if ( ! $password_valid ) {
			set_transient( $rate_key, $failed_count + 1, self::LOCKOUT_SECONDS );
			wp_send_json_error( array( 'message' => __( 'Invalid username or password', 'tpd-tool' ) ) );
		}

		// 4. Verify Account Status (Active vs Suspended/Inactive)
		if ( ! TPD_Tool_Roles::is_account_active( $user->ID ) ) {
			wp_logout();
			wp_send_json_error( array(
				'message' => __( 'Your account is currently inactive or suspended. Please contact support.', 'tpd-tool' ),
			) );
		}

		// 5. Enforce Strict Role Isolation per Portal
		$is_admin = TPD_Tool_Roles::is_admin( $user->ID );
		$is_adv   = TPD_Tool_Roles::is_advisor( $user->ID );
		$is_supp  = TPD_Tool_Roles::is_supplier( $user->ID );

		if ( 'advisor' === $portal_type && ! $is_adv && ! $is_admin ) {
			if ( $is_supp ) {
				wp_send_json_error( array(
					'message'        => __( 'Your account is registered as a Supplier Partner only, not a Travel Advisor. Would you like to also register as a Travel Advisor with this email?', 'tpd-tool' ),
					'cross_role_url' => add_query_arg(
						array(
							'cross_role'       => '1',
							'cross_role_email' => rawurlencode( $user->user_email ),
						),
						home_url( '/advisor-registration/' )
					),
					'cross_role_btn' => __( 'Continue & Register as Travel Advisor Also', 'tpd-tool' ),
				) );
			}
			wp_send_json_error( array(
				'message' => __( 'Access denied: A Travel Advisor account is required to sign in here.', 'tpd-tool' ),
			) );
		}

		if ( 'supplier' === $portal_type && ! $is_supp && ! $is_admin ) {
			if ( $is_adv ) {
				wp_send_json_error( array(
					'message'        => __( 'Your account is registered as a Travel Advisor only, not a Supplier Partner. Would you like to also register as a Supplier Partner with this email?', 'tpd-tool' ),
					'cross_role_url' => add_query_arg(
						array(
							'cross_role'       => '1',
							'cross_role_email' => rawurlencode( $user->user_email ),
						),
						home_url( '/supplier-registration/' )
					),
					'cross_role_btn' => __( 'Continue & Register as Supplier Partner Also', 'tpd-tool' ),
				) );
			}
			wp_send_json_error( array(
				'message' => __( 'Access denied: A Supplier Partner account is required to sign in here.', 'tpd-tool' ),
			) );
		}

		// 6. Clear Rate Limit & Establish Secure Authenticated Session
		delete_transient( $rate_key );
		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );

		$redirect_url = ( 'supplier' === $portal_type ) ? home_url( '/supplier-dashboard/' ) : home_url( '/advisor-dashboard/' );

		wp_send_json_success( array(
			'message'      => __( 'Login successful! Redirecting to your dashboard...', 'tpd-tool' ),
			'redirect_url' => $redirect_url,
		) );
	}

	/**
	 * AJAX Handler: Inline Forgot Password Request
	 */
	public static function ajax_forgot_password() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		if ( empty( $user_login ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your username or email address.', 'tpd-tool' ) ) );
		}

		$result = retrieve_password( $user_login );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => __( 'If an account matches that username or email, a password reset link has been sent.', 'tpd-tool' ) ) );
		}

		wp_send_json_success( array(
			'message' => __( 'A password reset link has been emailed to your registered address.', 'tpd-tool' ),
		) );
	}

	/**
	 * Shortcode: [tpd_advisor_registration]
	 */
	public static function render_advisor_registration() {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/registration/advisor-registration.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Shortcode: [tpd_supplier_registration]
	 */
	public static function render_supplier_registration() {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/registration/supplier-registration.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}
}

TPD_Tool_Registration::init();
