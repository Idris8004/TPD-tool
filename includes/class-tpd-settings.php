<?php
/**
 * Dynamic Platform Configuration, Membership Plans, Payments, Form Builder & Admin CRUD Engine
 * Makes all plans, registration fields, custom fields, payment gateways, and dashboard elements
 * 100% configurable by the Super Admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Settings {

	const OPT_PLANS         = 'tpd_membership_plans';
	const OPT_PAYMENTS      = 'tpd_payment_settings';
	const OPT_SUBSCRIPTIONS = 'tpd_plan_subscriptions';
	const OPT_FORM_CONFIG   = 'tpd_registration_form_config';
	const OPT_CUSTOM_FIELDS = 'tpd_custom_fields_config';
	const OPT_AD_BANNERS    = 'tpd_ad_banners_config';
	const OPT_FAQS          = 'tpd_platform_faqs';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_defaults' ), 5 );
		add_action( 'init', array( __CLASS__, 'handle_csv_exports' ) );

		// Super Admin AJAX CRUD Handlers
		add_action( 'wp_ajax_tpd_admin_save_user', array( __CLASS__, 'ajax_admin_save_user' ) );
		add_action( 'wp_ajax_tpd_admin_get_user', array( __CLASS__, 'ajax_admin_get_user' ) );
		add_action( 'wp_ajax_tpd_admin_delete_user', array( __CLASS__, 'ajax_admin_delete_user' ) );
		add_action( 'wp_ajax_tpd_admin_toggle_user_status', array( __CLASS__, 'ajax_admin_toggle_user_status' ) );

		// Plans & Payment AJAX Handlers
		add_action( 'wp_ajax_tpd_admin_save_plan', array( __CLASS__, 'ajax_admin_save_plan' ) );
		add_action( 'wp_ajax_tpd_admin_delete_plan', array( __CLASS__, 'ajax_admin_delete_plan' ) );
		add_action( 'wp_ajax_tpd_admin_save_payment_settings', array( __CLASS__, 'ajax_admin_save_payment_settings' ) );

		// Form Config, Custom Fields, Ads & Content AJAX Handlers
		add_action( 'wp_ajax_tpd_admin_save_form_config', array( __CLASS__, 'ajax_admin_save_form_config' ) );
		add_action( 'wp_ajax_tpd_admin_save_custom_field', array( __CLASS__, 'ajax_admin_save_custom_field' ) );
		add_action( 'wp_ajax_tpd_admin_delete_custom_field', array( __CLASS__, 'ajax_admin_delete_custom_field' ) );
		add_action( 'wp_ajax_tpd_admin_save_ad_banners', array( __CLASS__, 'ajax_admin_save_ad_banners' ) );
		add_action( 'wp_ajax_tpd_admin_toggle_featured_listing', array( __CLASS__, 'ajax_admin_toggle_featured_listing' ) );
		add_action( 'wp_ajax_tpd_admin_quick_create_content', array( __CLASS__, 'ajax_admin_quick_create_content' ) );
		add_action( 'wp_ajax_tpd_admin_delete_post', array( __CLASS__, 'ajax_admin_delete_post' ) );

		// User Self-Service Actions (Upgrade Plan, Change Password, Delete Own Account)
		add_action( 'wp_ajax_tpd_user_upgrade_plan', array( __CLASS__, 'ajax_user_upgrade_plan' ) );
		add_action( 'wp_ajax_tpd_user_update_account_security', array( __CLASS__, 'ajax_user_update_account_security' ) );
		add_action( 'wp_ajax_tpd_delete_own_account', array( __CLASS__, 'ajax_delete_own_account' ) );
	}

	/**
	 * Ensure Default Plans, Form Config, Payment Settings, Subscriptions & Ad Banners
	 */
	public static function ensure_defaults() {
		// 1. Default Membership Plans
		$plans = get_option( self::OPT_PLANS );
		if ( empty( $plans ) || ! is_array( $plans ) ) {
			$plans = array(
				'basic' => array(
					'slug'            => 'basic',
					'name'            => 'Basic Plan',
					'badge'           => 'Free Access',
					'monthly_price'   => 0,
					'yearly_price'    => 0,
					'role'            => 'all',
					'max_specialties' => 5,
					'max_pdfs'        => 1,
					'featured'        => false,
					'features_list'   => array(
						'TARC Interactive Dashboard',
						'Supplier Directory & Saved Favorites',
						'Direct Chat & Messaging with Partners',
						'Public Directory Profile (Up to 5 Specialties)',
					),
				),
				'standard' => array(
					'slug'            => 'standard',
					'name'            => 'Standard Plan',
					'badge'           => 'Enhanced',
					'monthly_price'   => 9.99,
					'yearly_price'    => 99.00,
					'role'            => 'all',
					'max_specialties' => 5,
					'max_pdfs'        => 5,
					'featured'        => false,
					'features_list'   => array(
						'Everything in Basic Plan',
						'30-Day Comparative Analytics & Insights',
						'Direct Client Lead & Inquiry Management',
						'Custom Banner, Travel Blog & Up to 5 PDFs',
					),
				),
				'premium' => array(
					'slug'            => 'premium',
					'name'            => 'Premium Plan',
					'badge'           => 'Recommended',
					'monthly_price'   => 19.99,
					'yearly_price'    => 179.00,
					'role'            => 'all',
					'max_specialties' => 10,
					'max_pdfs'        => 20,
					'featured'        => true,
					'features_list'   => array(
						'Everything in Standard Plan',
						'Up to 10 Travel Specialties & Photo Albums',
						'Priority Featured Directory Placement',
						'Up to 20 Resource PDFs & Social Media Packs',
					),
				),
			);
			update_option( self::OPT_PLANS, $plans );
		}

		// 2. Default Payment Settings
		$payments = get_option( self::OPT_PAYMENTS );
		if ( empty( $payments ) || ! is_array( $payments ) ) {
			$payments = array(
				'mode'                   => 'test',
				'currency'               => 'USD',
				'currency_symbol'        => '$',
				'stripe_enabled'         => 1,
				'stripe_publishable_key' => 'pk_test_tpd_51N9xSampleKey',
				'stripe_secret_key'      => '',
				'paypal_enabled'         => 1,
				'paypal_client_id'       => '',
				'paypal_secret'          => '',
				'offline_enabled'        => 1,
			);
			update_option( self::OPT_PAYMENTS, $payments );
		}

		// 3. Default Form Configuration (Advisor & Supplier)
		$form_cfg = get_option( self::OPT_FORM_CONFIG );
		if ( empty( $form_cfg ) || ! is_array( $form_cfg ) ) {
			$form_cfg = array(
				'advisor_steps'         => 3,
				'supplier_steps'        => 2,
				'advisor_title'         => 'Join as a Verified Travel Advisor',
				'advisor_subtitle'      => 'Get discovered by high-intent travelers, connect directly with supplier BDMs, and grow your high-yield bookings.',
				'supplier_title'        => 'Create Your Supplier Partner Account',
				'supplier_subtitle'     => 'Register as a supplier representative to manage company listings, connect with advisors in the Virtual Office, and share trade resources.',
				'require_accreditation' => 0,
				'advisor_roles'         => array(
					'Agency Owner',
					'Travel Advisor - Independent Contractor',
					'Travel Advisor - Employee',
					'Agency Manager/Leader',
					'Other',
				),
				'agency_structures'     => array(
					'Independent Agency (no host)',
					'Host Agency',
					'Hosted Agency',
					'Franchise',
					'Other',
				),
				'consortia_options'     => array(
					'Virtuoso',
					'Signature Travel Network',
					'Travel Leaders Network',
					'Ensemble Travel Group',
					'MAST Travel Network',
					'WESTA',
					'Other Consortia',
				),
				'host_agency_options'   => array(
					'Avoya Travel',
					'Travel Planners International (TPI)',
					'OASIS Travel Network',
					'KHM Travel Group',
					'Nexion Travel Group',
					'Cruise Planners',
					'Dream Vacations',
					'InteleTravel',
					'Fora Travel',
					'Outside Agents',
					'Other Host',
				),
				'personal_sales_options' => array(
					'$0-$250,000',
					'$250,000-$500,000',
					'$500,000-$750,000',
					'$750,000-$1M',
					'$1M-$1.5M',
					'$1.5M-$2M',
					'$2M-$5M',
					'$5M-$10M',
					'$10M+',
				),
				'agency_sales_options'  => array(
					'$0-$250,000',
					'$250,000-$500,000',
					'$500,000-$1M',
					'$1M-$3M',
					'$3M-$5M',
					'$5M-$10M',
					'$10M-$15M',
					'$15M-$20M',
					'$20M+',
				),
				'countries'             => array(
					'United States',
					'Canada',
					'United Kingdom',
					'Australia',
					'New Zealand',
					'Germany',
					'France',
					'Italy',
					'Spain',
					'Switzerland',
					'Mexico',
					'United Arab Emirates',
					'Singapore',
					'Other',
				),
			);
			update_option( self::OPT_FORM_CONFIG, $form_cfg );
		}

		// 4. Default Ad Banners & Welcome Hero Config
		$ads = get_option( self::OPT_AD_BANNERS );
		if ( empty( $ads ) || ! is_array( $ads ) ) {
			$ads = array(
				'welcome_title'    => 'Find the right suppliers. Get the answers you need. Help your clients travel better.',
				'welcome_quote'    => 'Same clients. Bigger possibilities.',
				'welcome_bg'       => 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=1600&auto=format&fit=crop&q=80',
				'ad_1_tag'         => 'SPONSORED',
				'ad_1_title'       => 'Earn 18% Commission on 2026 Danube River Charters',
				'ad_1_subtitle'    => 'Luxury River Cruises · Limited Time Advisor Incentive',
				'ad_1_image'       => 'https://images.unsplash.com/photo-1548574505-5e239809ee19?w=160&auto=format&fit=crop&q=80',
				'ad_1_link_text'   => 'Explore Itineraries',
				'ad_1_link_url'    => '#tpd-view-suppliers',
				'ad_2_tag'         => 'FEATURED',
				'ad_2_title'       => 'Preferred Partner Luxury Resort Program',
				'ad_2_subtitle'    => 'Daily breakfast, $100 resort credits & VIP perks for your clients',
				'ad_2_image'       => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=160&auto=format&fit=crop&q=80',
				'ad_2_link_text'   => 'View Partner Desk',
				'ad_2_link_url'    => '#tpd-view-suppliers',
			);
			update_option( self::OPT_AD_BANNERS, $ads );
		}

		// 5. Default Subscription Ledger History
		$subs = get_option( self::OPT_SUBSCRIPTIONS );
		if ( false === $subs ) {
			$subs = array();
			// Populate from existing advisor users if any exist
			$advs = get_users( array( 'role' => 'travel_advisor', 'number' => 10, 'orderby' => 'registered', 'order' => 'DESC' ) );
			foreach ( $advs as $u ) {
				$t = TPD_Tool_Tiers::get_user_tier( $u->ID );
				$p = isset( $plans[ $t ] ) ? $plans[ $t ] : $plans['basic'];
				$reg_ts = strtotime( $u->user_registered );
				$subs[] = array(
					'id'           => uniqid( 'sub_' ),
					'user_id'      => $u->ID,
					'user_name'    => $u->display_name,
					'plan_slug'    => $t,
					'plan_name'    => $p['name'],
					'billing_type' => 'Month',
					'amount'       => '$' . number_format( (float) $p['monthly_price'], 2 ),
					'date'         => gmdate( 'Y-m-d', $reg_ts ),
					'expiry'       => gmdate( 'Y-m-d', strtotime( '+1 month', $reg_ts ) ),
					'gateway'      => ( $p['monthly_price'] > 0 ) ? 'Stripe' : 'Free',
				);
			}
			update_option( self::OPT_SUBSCRIPTIONS, $subs );
		}

		// 6. Default Platform FAQs
		$faqs = get_option( self::OPT_FAQS );
		if ( empty( $faqs ) || ! is_array( $faqs ) ) {
			$faqs = array(
				array(
					'q' => 'How do I connect directly with a Supplier BDM?',
					'a' => 'Navigate to Search Suppliers or your Dashboard Featured Suppliers and click "Chat Now" to message online trade representatives in real time.',
				),
				array(
					'q' => 'How do I update my profile photo, agency logo, and custom fields?',
					'a' => 'Open Tab 2 (My Profile Listing / My Partner Listings) in your dashboard. All changes immediately sync with your public directory profile.',
				),
				array(
					'q' => 'How can I upgrade or change my membership plan?',
					'a' => 'Go to the Settings tab in your dashboard and select your preferred plan under Membership & Billing.',
				),
			);
			update_option( self::OPT_FAQS, $faqs );
		}
	}

	/**
	 * Get Membership Plans
	 */
	public static function get_plans( $role = 'all' ) {
		self::ensure_defaults();
		$plans = get_option( self::OPT_PLANS, array() );
		if ( 'all' === $role ) {
			return $plans;
		}
		$filtered = array();
		foreach ( $plans as $slug => $plan ) {
			$plan_role = isset( $plan['role'] ) ? $plan['role'] : 'all';
			if ( 'all' === $plan_role || $role === $plan_role ) {
				$filtered[ $slug ] = $plan;
			}
		}
		return $filtered;
	}

	/**
	 * Get Single Plan Info
	 */
	public static function get_plan( $slug ) {
		$plans = self::get_plans();
		if ( isset( $plans[ $slug ] ) ) {
			return $plans[ $slug ];
		}
		return isset( $plans['basic'] ) ? $plans['basic'] : array(
			'slug'          => $slug,
			'name'          => ucfirst( $slug ) . ' Plan',
			'monthly_price' => 0,
			'yearly_price'  => 0,
		);
	}

	/**
	 * Record a Plan Subscription / Upgrade Transaction
	 */
	public static function record_subscription( $user_id, $plan_slug, $billing_type = 'Month', $gateway = 'Stripe' ) {
		$user = get_userdata( $user_id );
		$plan = self::get_plan( $plan_slug );
		$payments = self::get_payment_settings();
		$symbol = isset( $payments['currency_symbol'] ) ? $payments['currency_symbol'] : '$';

		$is_yearly = ( stripos( $billing_type, 'year' ) !== false || stripos( $billing_type, 'annual' ) !== false );
		$price     = $is_yearly ? (float) $plan['yearly_price'] : (float) $plan['monthly_price'];
		$type_label = $is_yearly ? 'Year' : 'Month';
		$expiry    = $is_yearly ? gmdate( 'Y-m-d', strtotime( '+1 year' ) ) : gmdate( 'Y-m-d', strtotime( '+1 month' ) );

		$subs = (array) get_option( self::OPT_SUBSCRIPTIONS, array() );
		array_unshift( $subs, array(
			'id'           => uniqid( 'sub_' ),
			'user_id'      => $user_id,
			'user_name'    => $user ? $user->display_name : 'User #' . $user_id,
			'plan_slug'    => $plan_slug,
			'plan_name'    => $plan['name'],
			'billing_type' => $type_label,
			'amount'       => $symbol . number_format( $price, 2 ),
			'date'         => gmdate( 'Y-m-d' ),
			'expiry'       => $expiry,
			'gateway'      => $price > 0 ? $gateway : 'Free',
		) );

		// Keep up to 100 recent records
		$subs = array_slice( $subs, 0, 100 );
		update_option( self::OPT_SUBSCRIPTIONS, $subs );

		update_user_meta( $user_id, 'tpd_plan_billing_type', $type_label );
		update_user_meta( $user_id, 'tpd_plan_amount', $symbol . number_format( $price, 2 ) );
		update_user_meta( $user_id, 'tpd_plan_start_date', gmdate( 'Y-m-d' ) );
		update_user_meta( $user_id, 'tpd_plan_expiry_date', $expiry );
	}

	public static function get_subscriptions( $limit = 10 ) {
		self::ensure_defaults();
		$subs = (array) get_option( self::OPT_SUBSCRIPTIONS, array() );
		return array_slice( $subs, 0, $limit );
	}

	public static function get_payment_settings() {
		self::ensure_defaults();
		return get_option( self::OPT_PAYMENTS, array() );
	}

	public static function get_form_config() {
		self::ensure_defaults();
		return get_option( self::OPT_FORM_CONFIG, array() );
	}

	public static function get_custom_fields( $target = 'all' ) {
		$fields = (array) get_option( self::OPT_CUSTOM_FIELDS, array() );
		if ( 'all' === $target ) {
			return $fields;
		}
		$out = array();
		foreach ( $fields as $k => $f ) {
			if ( ! empty( $f['target'] ) && ( $f['target'] === $target || 'both' === $f['target'] ) ) {
				$out[ $k ] = $f;
			}
		}
		return $out;
	}

	public static function get_ad_banners() {
		self::ensure_defaults();
		return get_option( self::OPT_AD_BANNERS, array() );
	}

	public static function get_faqs() {
		self::ensure_defaults();
		return get_option( self::OPT_FAQS, array() );
	}

	/**
	 * Export CSV for Advisors or Suppliers
	 */
	public static function handle_csv_exports() {
		if ( ! isset( $_GET['tpd_action'] ) || 'export_suppliers_csv' !== $_GET['tpd_action'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'tpd-tool' ) );
		}
		check_admin_referer( 'tpd_export_suppliers', 'nonce' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tpd-suppliers-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array(
			'User ID',
			'Full Name',
			'Username',
			'Email',
			'Phone',
			'Position/Title',
			'Company Name',
			'Country',
			'Membership Plan',
			'Status',
			'Registered Date',
		) );

		$suppliers = get_users( array(
			'role'    => 'supplier',
			'orderby' => 'registered',
			'order'   => 'DESC',
		) );

		foreach ( $suppliers as $sup ) {
			$uid = $sup->ID;
			$status = get_user_meta( $uid, 'tpd_account_status', true ) ?: 'active';
			fputcsv( $output, array(
				$uid,
				$sup->display_name,
				$sup->user_login,
				$sup->user_email,
				get_user_meta( $uid, 'tpd_phone', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_position_title', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_company_name', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_country_of_residence', true ) ?: 'N/A',
				strtoupper( TPD_Tool_Tiers::get_user_tier( $uid ) ),
				ucfirst( $status ),
				$sup->user_registered,
			) );
		}
		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Admin Get Single User Details (for View & Edit Modals)
	 */
	public static function ajax_admin_get_user() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => 'User not found.' ) );
		}

		$role = in_array( 'supplier', (array) $user->roles, true ) ? 'supplier' : 'travel_advisor';
		$tier = TPD_Tool_Tiers::get_user_tier( $user_id );
		$status = get_user_meta( $user_id, 'tpd_account_status', true ) ?: 'active';

		// Find paired CPT post
		$cpt_type = ( 'supplier' === $role ) ? 'supplier_listing' : 'travel_advisor';
		$posts = get_posts( array(
			'post_type'      => $cpt_type,
			'posts_per_page' => 1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );
		$post_id = ! empty( $posts ) ? $posts[0] : 0;

		$data = array(
			'user_id'             => $user_id,
			'post_id'             => $post_id,
			'role'                => $role,
			'first_name'          => $user->first_name,
			'last_name'           => $user->last_name,
			'display_name'        => $user->display_name,
			'username'            => $user->user_login,
			'email'               => $user->user_email,
			'tier'                => $tier,
			'status'              => $status,
			'registered'          => gmdate( 'd M Y', strtotime( $user->user_registered ) ),
			'phone'               => get_user_meta( $user_id, 'tpd_phone', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_phone', true ) : '' ),
			'location'            => get_user_meta( $user_id, 'tpd_location', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_location', true ) : '' ),
			'agency_name'         => get_user_meta( $user_id, 'tpd_agency_name', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_agency_name', true ) : '' ),
			'agency_address'      => get_user_meta( $user_id, 'tpd_agency_address', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_agency_address', true ) : '' ),
			'business_structure'  => get_user_meta( $user_id, 'tpd_business_structure', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_business_structure', true ) : '' ),
			'agency_structure'    => get_user_meta( $user_id, 'tpd_agency_structure', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_agency_structure', true ) : '' ),
			'consortia'           => get_user_meta( $user_id, 'tpd_consortia', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_consortia', true ) : '' ),
			'host_agency'         => get_user_meta( $user_id, 'tpd_host_agency', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_host_agency', true ) : '' ),
			'personal_sales'      => get_user_meta( $user_id, 'tpd_personal_sales_volume', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_personal_sales_volume', true ) : '' ),
			'agency_sales'        => get_user_meta( $user_id, 'tpd_agency_sales_volume', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_agency_sales_volume', true ) : '' ),
			'clia_num'            => get_user_meta( $user_id, 'tpd_clia_num', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_clia_number', true ) : '' ),
			'iata_num'            => get_user_meta( $user_id, 'tpd_iata_num', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_iata_number', true ) : '' ),
			'arc_num'             => get_user_meta( $user_id, 'tpd_arc_num', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_arc_number', true ) : '' ),
			'true_num'            => get_user_meta( $user_id, 'tpd_true_num', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_true_number', true ) : '' ),
			'company_name'        => get_user_meta( $user_id, 'tpd_company_name', true ) ?: ( $post_id ? get_the_title( $post_id ) : '' ),
			'position_title'      => get_user_meta( $user_id, 'tpd_position_title', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_position_title', true ) : '' ),
			'country'             => get_user_meta( $user_id, 'tpd_country_of_residence', true ) ?: ( $post_id ? get_post_meta( $post_id, 'tpd_country_of_residence', true ) : '' ),
		);

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Admin Create or Update Advisor / Supplier User + Sync Paired CPT
	 */
	public static function ajax_admin_save_user() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$user_id        = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$role           = isset( $_POST['role'] ) && 'supplier' === $_POST['role'] ? 'supplier' : 'travel_advisor';
		$first_name     = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name      = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$username       = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		$password       = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$tier           = isset( $_POST['tier'] ) ? sanitize_text_field( wp_unslash( $_POST['tier'] ) ) : 'basic';
		$status         = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active';
		$phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$location       = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$agency_name    = isset( $_POST['agency_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_name'] ) ) : '';
		$agency_address = isset( $_POST['agency_address'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_address'] ) ) : '';
		$company_name   = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$position_title = isset( $_POST['position_title'] ) ? sanitize_text_field( wp_unslash( $_POST['position_title'] ) ) : '';
		$country        = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$consortia      = isset( $_POST['consortia'] ) ? sanitize_text_field( wp_unslash( $_POST['consortia'] ) ) : '';
		$host_agency    = isset( $_POST['host_agency'] ) ? sanitize_text_field( wp_unslash( $_POST['host_agency'] ) ) : '';
		$clia_num       = isset( $_POST['clia_num'] ) ? sanitize_text_field( wp_unslash( $_POST['clia_num'] ) ) : '';
		$iata_num       = isset( $_POST['iata_num'] ) ? sanitize_text_field( wp_unslash( $_POST['iata_num'] ) ) : '';

		if ( empty( $first_name ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => 'First name and email are required.' ) );
		}

		$display_name = trim( $first_name . ' ' . $last_name );

		if ( ! $user_id ) {
			// Create new user
			if ( email_exists( $email ) ) {
				wp_send_json_error( array( 'message' => 'Email address is already in use.' ) );
			}
			if ( empty( $username ) ) {
				$username = sanitize_user( current( explode( '@', $email ) ) );
				if ( username_exists( $username ) ) {
					$username .= '_' . wp_rand( 100, 999 );
				}
			} elseif ( username_exists( $username ) ) {
				wp_send_json_error( array( 'message' => 'Username already exists.' ) );
			}
			if ( empty( $password ) ) {
				$password = wp_generate_password( 12, true );
			}

			$user_id = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}

			wp_update_user( array(
				'ID'           => $user_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name ?: $username,
				'role'         => $role,
			) );
		} else {
			// Update existing user
			$update_args = array(
				'ID'           => $user_id,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name ?: $username,
				'user_email'   => $email,
			);
			if ( ! empty( $password ) ) {
				$update_args['user_pass'] = $password;
			}
			$res = wp_update_user( $update_args );
			if ( is_wp_error( $res ) ) {
				wp_send_json_error( array( 'message' => $res->get_error_message() ) );
			}
		}

		// Save User Meta
		TPD_Tool_Tiers::set_user_tier( $user_id, $tier );
		update_user_meta( $user_id, 'tpd_account_status', $status );
		update_user_meta( $user_id, 'tpd_phone', $phone );
		update_user_meta( $user_id, 'tpd_location', $location );
		update_user_meta( $user_id, 'tpd_agency_name', $agency_name );
		update_user_meta( $user_id, 'tpd_agency_address', $agency_address );
		update_user_meta( $user_id, 'tpd_company_name', $company_name );
		update_user_meta( $user_id, 'tpd_position_title', $position_title );
		update_user_meta( $user_id, 'tpd_country_of_residence', $country );
		update_user_meta( $user_id, 'tpd_consortia', $consortia );
		update_user_meta( $user_id, 'tpd_host_agency', $host_agency );
		update_user_meta( $user_id, 'tpd_clia_num', $clia_num );
		update_user_meta( $user_id, 'tpd_iata_num', $iata_num );

		// Sync with Paired CPT Post
		$cpt_type = ( 'supplier' === $role ) ? 'supplier_listing' : 'travel_advisor';
		$posts = get_posts( array(
			'post_type'      => $cpt_type,
			'posts_per_page' => 1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );

		$post_title = ( 'supplier' === $role && $company_name ) ? $company_name : ( $display_name ?: $username );

		if ( empty( $posts ) ) {
			$post_id = wp_insert_post( array(
				'post_type'   => $cpt_type,
				'post_title'  => $post_title,
				'post_status' => 'publish',
				'post_author' => $user_id,
			) );
		} else {
			$post_id = $posts[0];
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => $post_title,
			) );
		}

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'tpd_assigned_user', $user_id );
			update_post_meta( $post_id, 'tpd_user_tier', $tier );
			update_post_meta( $post_id, 'tpd_phone', $phone );
			update_post_meta( $post_id, 'tpd_location', $location );
			update_post_meta( $post_id, 'tpd_agency_name', $agency_name );
			update_post_meta( $post_id, 'tpd_agency_address', $agency_address );
			update_post_meta( $post_id, 'tpd_company_name', $company_name );
			update_post_meta( $post_id, 'tpd_position_title', $position_title );
			update_post_meta( $post_id, 'tpd_country_of_residence', $country );
			update_post_meta( $post_id, 'tpd_consortia', $consortia );
			update_post_meta( $post_id, 'tpd_host_agency', $host_agency );
			update_post_meta( $post_id, 'tpd_clia_number', $clia_num );
			update_post_meta( $post_id, 'tpd_iata_number', $iata_num );
		}

		wp_send_json_success( array(
			'message' => 'User account and directory listing saved successfully!',
			'user_id' => $user_id,
		) );
	}

	/**
	 * AJAX: Admin Delete User & Paired CPT
	 */
	public static function ajax_admin_delete_user() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$target_uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $target_uid || $target_uid === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => 'Cannot delete this account.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		// Delete linked CPT posts
		$linked = get_posts( array(
			'post_type'      => array( 'travel_advisor', 'supplier_listing' ),
			'posts_per_page' => -1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $target_uid,
			'fields'         => 'ids',
		) );
		foreach ( $linked as $pid ) {
			wp_delete_post( $pid, true );
		}

		wp_delete_user( $target_uid );

		wp_send_json_success( array(
			'message' => 'User account and associated profile deleted.',
			'user_id' => $target_uid,
		) );
	}

	/**
	 * AJAX: Toggle User Active/Inactive Status
	 */
	public static function ajax_admin_toggle_user_status() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$target_uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$current    = get_user_meta( $target_uid, 'tpd_account_status', true ) ?: 'active';
		$next       = ( 'active' === $current ) ? 'inactive' : 'active';
		update_user_meta( $target_uid, 'tpd_account_status', $next );

		wp_send_json_success( array(
			'user_id' => $target_uid,
			'status'  => $next,
			'label'   => ucfirst( $next ),
		) );
	}

	/**
	 * AJAX: Admin Create / Edit Membership Plan
	 */
	public static function ajax_admin_save_plan() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$name          = isset( $_POST['plan_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_name'] ) ) : '';
		$slug          = isset( $_POST['plan_slug'] ) && ! empty( $_POST['plan_slug'] ) ? sanitize_title( wp_unslash( $_POST['plan_slug'] ) ) : sanitize_title( $name );
		$badge         = isset( $_POST['plan_badge'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_badge'] ) ) : 'Standard';
		$monthly       = isset( $_POST['monthly_price'] ) ? (float) $_POST['monthly_price'] : 0;
		$yearly        = isset( $_POST['yearly_price'] ) ? (float) $_POST['yearly_price'] : 0;
		$role          = isset( $_POST['plan_role'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_role'] ) ) : 'all';
		$specialties   = isset( $_POST['max_specialties'] ) ? absint( $_POST['max_specialties'] ) : 5;
		$pdfs          = isset( $_POST['max_pdfs'] ) ? absint( $_POST['max_pdfs'] ) : 5;
		$featured      = ! empty( $_POST['featured'] );
		$features_raw  = isset( $_POST['features_list'] ) ? sanitize_textarea_field( wp_unslash( $_POST['features_list'] ) ) : '';
		$features_list = array_filter( array_map( 'trim', explode( "\n", $features_raw ) ) );
		$capabilities  = isset( $_POST['capabilities'] ) ? array_map( 'sanitize_text_field', (array) $_POST['capabilities'] ) : array();

		if ( empty( $name ) || empty( $slug ) ) {
			wp_send_json_error( array( 'message' => 'Plan name is required.' ) );
		}

		$plans = self::get_plans();
		$plans[ $slug ] = array(
			'slug'            => $slug,
			'name'            => $name,
			'badge'           => $badge,
			'monthly_price'   => $monthly,
			'yearly_price'    => $yearly,
			'role'            => $role,
			'max_specialties' => $specialties,
			'max_pdfs'        => $pdfs,
			'featured'        => $featured,
			'features_list'   => ! empty( $features_list ) ? array_values( $features_list ) : array( 'Full Directory Access' ),
		);
		update_option( self::OPT_PLANS, $plans );

		// Also update permissions matrix for this plan if capabilities passed
		if ( ! empty( $capabilities ) ) {
			$perms = get_option( TPD_Tool_Tiers::OPTION_PERMISSIONS, array() );
			$perms[ $slug ] = array();
			foreach ( $capabilities as $cap ) {
				$perms[ $slug ][ $cap ] = 1;
			}
			update_option( TPD_Tool_Tiers::OPTION_PERMISSIONS, $perms );
		}

		wp_send_json_success( array(
			'message' => sprintf( 'Membership plan "%s" saved successfully!', $name ),
			'plan'    => $plans[ $slug ],
		) );
	}

	/**
	 * AJAX: Admin Delete Membership Plan
	 */
	public static function ajax_admin_delete_plan() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$slug = isset( $_POST['plan_slug'] ) ? sanitize_title( wp_unslash( $_POST['plan_slug'] ) ) : '';
		if ( 'basic' === $slug ) {
			wp_send_json_error( array( 'message' => 'The default Basic plan cannot be deleted, only edited.' ) );
		}

		$plans = self::get_plans();
		if ( isset( $plans[ $slug ] ) ) {
			unset( $plans[ $slug ] );
			update_option( self::OPT_PLANS, $plans );
		}

		wp_send_json_success( array( 'message' => 'Plan deleted.' ) );
	}

	/**
	 * AJAX: Save Payment Gateway Settings
	 */
	public static function ajax_admin_save_payment_settings() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$currency = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'USD';
		$symbols  = array( 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'CA$', 'AUD' => 'A$' );

		$settings = array(
			'mode'                   => isset( $_POST['mode'] ) && 'live' === $_POST['mode'] ? 'live' : 'test',
			'currency'               => $currency,
			'currency_symbol'        => isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : '$',
			'stripe_enabled'         => ! empty( $_POST['stripe_enabled'] ) ? 1 : 0,
			'stripe_publishable_key' => isset( $_POST['stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_publishable_key'] ) ) : '',
			'stripe_secret_key'      => isset( $_POST['stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_secret_key'] ) ) : '',
			'paypal_enabled'         => ! empty( $_POST['paypal_enabled'] ) ? 1 : 0,
			'paypal_client_id'       => isset( $_POST['paypal_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_client_id'] ) ) : '',
			'paypal_secret'          => isset( $_POST['paypal_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal_secret'] ) ) : '',
			'offline_enabled'        => ! empty( $_POST['offline_enabled'] ) ? 1 : 0,
		);

		update_option( self::OPT_PAYMENTS, $settings );
		wp_send_json_success( array( 'message' => 'Payment gateway settings saved!' ) );
	}

	/**
	 * AJAX: Save Registration Form Configuration
	 */
	public static function ajax_admin_save_form_config() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$cfg = self::get_form_config();

		$cfg['advisor_steps']         = isset( $_POST['advisor_steps'] ) ? absint( $_POST['advisor_steps'] ) : 3;
		$cfg['supplier_steps']        = isset( $_POST['supplier_steps'] ) ? absint( $_POST['supplier_steps'] ) : 2;
		$cfg['advisor_title']         = isset( $_POST['advisor_title'] ) ? sanitize_text_field( wp_unslash( $_POST['advisor_title'] ) ) : $cfg['advisor_title'];
		$cfg['advisor_subtitle']      = isset( $_POST['advisor_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['advisor_subtitle'] ) ) : $cfg['advisor_subtitle'];
		$cfg['supplier_title']        = isset( $_POST['supplier_title'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_title'] ) ) : $cfg['supplier_title'];
		$cfg['supplier_subtitle']     = isset( $_POST['supplier_subtitle'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_subtitle'] ) ) : $cfg['supplier_subtitle'];
		$cfg['require_accreditation'] = ! empty( $_POST['require_accreditation'] ) ? 1 : 0;

		$list_fields = array(
			'advisor_roles',
			'agency_structures',
			'consortia_options',
			'host_agency_options',
			'personal_sales_options',
			'agency_sales_options',
			'countries',
		);

		foreach ( $list_fields as $lf ) {
			if ( isset( $_POST[ $lf ] ) ) {
				$lines = explode( "\n", sanitize_textarea_field( wp_unslash( $_POST[ $lf ] ) ) );
				$clean = array_values( array_filter( array_map( 'trim', $lines ) ) );
				if ( ! empty( $clean ) ) {
					$cfg[ $lf ] = $clean;
				}
			}
		}

		update_option( self::OPT_FORM_CONFIG, $cfg );
		wp_send_json_success( array( 'message' => 'Registration form settings & dropdown options saved!' ) );
	}

	/**
	 * AJAX: Save Dynamic Custom Field for Advisor / Supplier
	 */
	public static function ajax_admin_save_custom_field() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$label   = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$key     = isset( $_POST['field_key'] ) && ! empty( $_POST['field_key'] ) ? sanitize_key( $_POST['field_key'] ) : 'tpd_cf_' . sanitize_key( $label );
		$type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'text';
		$target  = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : 'advisor';
		$options = isset( $_POST['options'] ) ? sanitize_text_field( wp_unslash( $_POST['options'] ) ) : '';
		$req     = ! empty( $_POST['required'] ) ? 1 : 0;
		$on_reg  = ! empty( $_POST['show_on_registration'] ) ? 1 : 0;

		if ( empty( $label ) ) {
			wp_send_json_error( array( 'message' => 'Field label is required.' ) );
		}

		$fields = self::get_custom_fields();
		$fields[ $key ] = array(
			'key'                  => $key,
			'label'                => $label,
			'type'                 => $type,
			'target'               => $target,
			'options'              => array_values( array_filter( array_map( 'trim', explode( ',', $options ) ) ) ),
			'required'             => $req,
			'show_on_registration' => $on_reg,
			'show_in_dashboard'    => 1,
		);

		update_option( self::OPT_CUSTOM_FIELDS, $fields );
		wp_send_json_success( array(
			'message' => sprintf( 'Custom field "%s" added and synced to %s forms & dashboard!', $label, ucfirst( $target ) ),
			'field'   => $fields[ $key ],
		) );
	}

	/**
	 * AJAX: Delete Custom Field
	 */
	public static function ajax_admin_delete_custom_field() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$key = isset( $_POST['field_key'] ) ? sanitize_key( $_POST['field_key'] ) : '';
		$fields = self::get_custom_fields();
		if ( isset( $fields[ $key ] ) ) {
			unset( $fields[ $key ] );
			update_option( self::OPT_CUSTOM_FIELDS, $fields );
		}
		wp_send_json_success( array( 'message' => 'Custom field removed.' ) );
	}

	/**
	 * AJAX: Save Ad Banners & Hero Config
	 */
	public static function ajax_admin_save_ad_banners() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$ads = self::get_ad_banners();
		$keys = array(
			'welcome_title', 'welcome_quote', 'welcome_bg',
			'ad_1_tag', 'ad_1_title', 'ad_1_subtitle', 'ad_1_image', 'ad_1_link_text', 'ad_1_link_url',
			'ad_2_tag', 'ad_2_title', 'ad_2_subtitle', 'ad_2_image', 'ad_2_link_text', 'ad_2_link_url',
		);
		foreach ( $keys as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				$ads[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ) );
			}
		}
		update_option( self::OPT_AD_BANNERS, $ads );
		wp_send_json_success( array( 'message' => 'Dashboard welcome banner & sponsored ad spaces updated!' ) );
	}

	/**
	 * AJAX: Toggle Featured / Spotlight Status on a Supplier Listing
	 */
	public static function ajax_admin_toggle_featured_listing() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$cur = get_post_meta( $post_id, 'tpd_featured_supplier', true );
		$next = ( 'yes' === $cur ) ? 'no' : 'yes';
		update_post_meta( $post_id, 'tpd_featured_supplier', $next );

		wp_send_json_success( array(
			'post_id'  => $post_id,
			'featured' => $next,
			'message'  => ( 'yes' === $next ) ? 'Supplier added to Featured Spotlight!' : 'Removed from Featured Spotlight.',
		) );
	}

	/**
	 * AJAX: Quick Create Blog, Event, or FAQ from Super Admin Hub
	 */
	public static function ajax_admin_quick_create_content() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$content_type = isset( $_POST['content_type'] ) ? sanitize_text_field( wp_unslash( $_POST['content_type'] ) ) : '';
		$title        = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body         = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

		if ( empty( $title ) ) {
			wp_send_json_error( array( 'message' => 'Title / Question is required.' ) );
		}

		if ( 'faq' === $content_type ) {
			$faqs = self::get_faqs();
			$faqs[] = array( 'q' => $title, 'a' => wp_strip_all_tags( $body ) );
			update_option( self::OPT_FAQS, $faqs );
			wp_send_json_success( array( 'message' => 'FAQ published to user dashboards!' ) );
		}

		$post_type = ( 'event' === $content_type ) ? 'tpd_event' : 'supplier_blog';
		$pid = wp_insert_post( array(
			'post_type'    => $post_type,
			'post_title'   => $title,
			'post_content' => $body,
			'post_status'  => 'publish',
		) );

		if ( is_wp_error( $pid ) ) {
			wp_send_json_error( array( 'message' => $pid->get_error_message() ) );
		}

		if ( 'event' === $content_type ) {
			$ev_date   = isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : gmdate( 'Y-m-d', strtotime( '+7 days' ) );
			$ev_time   = isset( $_POST['event_time'] ) ? sanitize_text_field( wp_unslash( $_POST['event_time'] ) ) : '2:00 PM EST';
			$ev_series = isset( $_POST['event_series'] ) ? sanitize_text_field( wp_unslash( $_POST['event_series'] ) ) : 'TARC Partner Webinar';
			$ts        = strtotime( $ev_date );
			update_post_meta( $pid, '_tpd_event_date', $ev_date );
			update_post_meta( $pid, '_tpd_event_month', strtoupper( gmdate( 'M', $ts ) ) );
			update_post_meta( $pid, '_tpd_event_day', gmdate( 'd', $ts ) );
			update_post_meta( $pid, '_tpd_event_time', $ev_time );
			update_post_meta( $pid, '_tpd_event_series', $ev_series );
		}

		wp_send_json_success( array(
			'message' => 'Content published and live on dashboards!',
			'post_id' => $pid,
		) );
	}

	/**
	 * AJAX: Delete Post (Blog, Event, Supplier Listing) from Super Admin Hub
	 */
	public static function ajax_admin_delete_post() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id ) {
			wp_delete_post( $post_id, true );
		}
		wp_send_json_success( array( 'message' => 'Item deleted.' ) );
	}

	/**
	 * AJAX: User Self-Service Plan Upgrade / Checkout
	 */
	public static function ajax_user_upgrade_plan() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Authentication required.' ) );
		}

		$plan_slug    = isset( $_POST['plan_slug'] ) ? sanitize_title( wp_unslash( $_POST['plan_slug'] ) ) : 'basic';
		$billing_type = isset( $_POST['billing_type'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_type'] ) ) : 'Month';
		$gateway      = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : 'Stripe';

		$updated_tier = TPD_Tool_Tiers::set_user_tier( $user_id, $plan_slug );
		self::record_subscription( $user_id, $updated_tier, $billing_type, $gateway );

		$plan = self::get_plan( $updated_tier );
		wp_send_json_success( array(
			'message' => sprintf( 'Your membership has been updated to %s (%sly billing)!', $plan['name'], $billing_type ),
			'tier'    => $updated_tier,
		) );
	}

	/**
	 * AJAX: User Self-Service Password Update
	 */
	public static function ajax_user_update_account_security() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Authentication required.' ) );
		}

		$new_pass = isset( $_POST['new_password'] ) ? $_POST['new_password'] : '';
		$confirm  = isset( $_POST['confirm_password'] ) ? $_POST['confirm_password'] : '';

		if ( empty( $new_pass ) || strlen( $new_pass ) < 8 ) {
			wp_send_json_error( array( 'message' => 'Password must be at least 8 characters long.' ) );
		}
		if ( $new_pass !== $confirm ) {
			wp_send_json_error( array( 'message' => 'Passwords do not match.' ) );
		}

		wp_set_password( $new_pass, $user_id );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		wp_send_json_success( array( 'message' => 'Your password has been updated successfully!' ) );
	}

	/**
	 * AJAX: User Self-Service Account Deletion
	 */
	public static function ajax_delete_own_account() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => 'Authentication required.' ) );
		}
		if ( current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Administrator accounts cannot be deleted from the frontend portal.' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';

		$linked = get_posts( array(
			'post_type'      => array( 'travel_advisor', 'supplier_listing' ),
			'posts_per_page' => -1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );
		foreach ( $linked as $pid ) {
			wp_delete_post( $pid, true );
		}

		wp_logout();
		wp_delete_user( $user_id );

		wp_send_json_success( array(
			'message'      => 'Your account and directory profile have been permanently deleted.',
			'redirect_url' => home_url( '/' ),
		) );
	}
}

TPD_Tool_Settings::init();
