<?php
/**
 * Registration Engine for TPD Tool
 * Handles multi-step Travel Advisor (Pro User) and Supplier registration workflows,
 * automated user provisioning, paired CPT creation (travel_advisor & supplier_listing),
 * dynamic custom/ACF field saving, and subscription plan initialization.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Registration {

	public static function init() {
		add_action( 'wp_ajax_tpd_register_advisor', array( __CLASS__, 'ajax_register_advisor' ) );
		add_action( 'wp_ajax_nopriv_tpd_register_advisor', array( __CLASS__, 'ajax_register_advisor' ) );

		add_action( 'wp_ajax_tpd_register_supplier', array( __CLASS__, 'ajax_register_supplier' ) );
		add_action( 'wp_ajax_nopriv_tpd_register_supplier', array( __CLASS__, 'ajax_register_supplier' ) );

		add_shortcode( 'tpd_advisor_registration', array( __CLASS__, 'render_advisor_registration' ) );
		add_shortcode( 'tpd_supplier_registration', array( __CLASS__, 'render_supplier_registration' ) );
	}

	/**
	 * AJAX Handler: Register Travel Advisor (Pro User)
	 */
	public static function ajax_register_advisor() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		// 1. Sanitize Account & Personal Details
		$first_name       = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name        = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$username         = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$password         = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : $password;
		$tier             = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : 'basic';
		$billing_cycle    = isset( $_POST['billing_cycle'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cycle'] ) ) : 'Month';
		$payment_gateway  = isset( $_POST['payment_gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_gateway'] ) ) : 'Stripe';

		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'First name, last name, email address, and password are required.', 'tpd-tool' ) ) );
		}

		if ( $password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match. Please verify your password confirmation.', 'tpd-tool' ) ) );
		}

		if ( strlen( $password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters long.', 'tpd-tool' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'tpd-tool' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'An account with this email address already exists. Please log in.', 'tpd-tool' ) ) );
		}

		if ( empty( $username ) ) {
			$username = sanitize_user( current( explode( '@', $email ) ) );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_rand( 100, 999 );
			}
		} elseif ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'This username is already taken. Please choose another.', 'tpd-tool' ) ) );
		}

		// 2. Sanitize Agency & Credential Details
		$phone_code          = isset( $_POST['phone_country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_country_code'] ) ) : '';
		$raw_phone           = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone               = trim( ( $phone_code ? $phone_code . ' ' : '' ) . $raw_phone );
		$profile_handle      = isset( $_POST['profile_handle'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_handle'] ) ) : $username;
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

		// 3. Create WordPress User
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

		// Save User Meta (100% synced for CSV export, Admin Hub, and Dashboard)
		update_user_meta( $user_id, 'tpd_account_status', 'active' );
		update_user_meta( $user_id, 'tpd_profile_handle', $profile_handle );
		update_user_meta( $user_id, 'tpd_country', $country );
		update_user_meta( $user_id, 'tpd_phone', $phone );
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
		wp_set_auth_cookie( $user_id, true );

		// 6. Send Welcome Email
		$subject = __( 'Welcome to Travel Partner Directory by TARC!', 'tpd-tool' );
		$message = sprintf(
			"Dear %s,\n\nWelcome to Travel Partner Directory! Your Travel Advisor account (%s) has been created.\n\nAccess your Advisor Dashboard here:\n%s\n\nThank you for partnering with TARC!\nTravel Partner Directory Team",
			$first_name,
			strtoupper( $final_tier ),
			home_url( '/advisor-dashboard/' )
		);
		wp_mail( $email, $subject, $message );

		wp_send_json_success( array(
			'message'      => __( 'Registration complete! Opening your Advisor Dashboard...', 'tpd-tool' ),
			'redirect_url' => home_url( '/advisor-dashboard/' ),
		) );
	}

	/**
	 * AJAX Handler: Register Supplier Partner
	 * Supports exact client fields:
	 * First Name *, Last Name *, Phone Number *, Email Address *, Username *,
	 * Position/Title, Supplier/Company Name *, Country *, Password *, Confirm Password *
	 */
	public static function ajax_register_supplier() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$first_name       = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : ( isset( $_POST['rep_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rep_first_name'] ) ) : '' );
		$last_name        = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : ( isset( $_POST['rep_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rep_last_name'] ) ) : '' );
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$username         = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$phone_code       = isset( $_POST['phone_country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_country_code'] ) ) : '';
		$raw_phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone            = trim( ( $phone_code ? $phone_code . ' ' : '' ) . $raw_phone );
		$position_title   = isset( $_POST['position_title'] ) ? sanitize_text_field( wp_unslash( $_POST['position_title'] ) ) : '';
		$company_name     = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$country          = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : ( isset( $_POST['country_of_residence'] ) ? sanitize_text_field( wp_unslash( $_POST['country_of_residence'] ) ) : '' );
		$password         = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : $password;
		$tier             = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : 'basic';
		$billing_cycle    = isset( $_POST['billing_cycle'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cycle'] ) ) : 'Month';

		if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $phone ) || empty( $company_name ) || empty( $country ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required (*) fields, including First Name, Last Name, Phone, Email, Supplier/Company Name, Country, and Password.', 'tpd-tool' ) ) );
		}

		if ( $password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match. Please check your password confirmation.', 'tpd-tool' ) ) );
		}

		if ( strlen( $password ) < 6 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters long.', 'tpd-tool' ) ) );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'tpd-tool' ) ) );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'An account with this email address already exists.', 'tpd-tool' ) ) );
		}

		if ( empty( $username ) ) {
			$username = sanitize_user( current( explode( '@', $email ) ) );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_rand( 100, 999 );
			}
		} elseif ( username_exists( $username ) ) {
			wp_send_json_error( array( 'message' => __( 'This username is already taken. Please choose another.', 'tpd-tool' ) ) );
		}

		// Create User
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
		wp_set_auth_cookie( $user_id, true );

		wp_send_json_success( array(
			'message'      => __( 'Supplier account & company listing created! Opening your Supplier Dashboard...', 'tpd-tool' ),
			'redirect_url' => home_url( '/supplier-dashboard/' ),
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
