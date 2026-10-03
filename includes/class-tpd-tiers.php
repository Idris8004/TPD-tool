<?php
/**
 * Tier Management & Feature Gating for TPD Tool
 * Handles Free vs. Paid Membership Tiers and Admin Permission Configuration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Tiers {

	const OPTION_PERMISSIONS = 'tpd_tier_permissions';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'ensure_default_permissions' ) );
		add_action( 'init', array( __CLASS__, 'export_advisors_csv' ) );
		add_action( 'wp_ajax_tpd_update_user_tier', array( __CLASS__, 'ajax_update_user_tier' ) );
		add_action( 'wp_ajax_tpd_save_tier_permissions', array( __CLASS__, 'ajax_save_tier_permissions' ) );
	}

	/**
	 * Available Platform Features for Gating
	 */
	public static function get_all_features() {
		return array(
			// Advisor Features
			'advisor' => array(
				'advisor_chat' => array(
					'label'       => __( 'Live Chat & Messaging with Suppliers', 'tpd-tool' ),
					'description' => __( 'Direct instant chat messaging with supplier BDM reps and guests.', 'tpd-tool' ),
					'icon'        => 'fa-comments',
				),
				'advisor_analytics_deep' => array(
					'label'       => __( '30-Day Comparative Analytics', 'tpd-tool' ),
					'description' => __( 'View profile impressions, message trends, and client inquiry analytics.', 'tpd-tool' ),
					'icon'        => 'fa-chart-line',
				),
				'advisor_inquiries' => array(
					'label'       => __( 'Direct Client Lead Management', 'tpd-tool' ),
					'description' => __( 'Receive, manage, and respond to traveler inquiries.', 'tpd-tool' ),
					'icon'        => 'fa-inbox',
				),
				'advisor_saved_suppliers' => array(
					'label'       => __( 'Unlimited Saved Suppliers', 'tpd-tool' ),
					'description' => __( 'Bookmark preferred suppliers for instant 1-click access and updates.', 'tpd-tool' ),
					'icon'        => 'fa-bookmark',
				),
				'advisor_meetings' => array(
					'label'       => __( 'Schedule Supplier Meetings', 'tpd-tool' ),
					'description' => __( 'Book 1-on-1 virtual discovery calls with supplier BDMs.', 'tpd-tool' ),
					'icon'        => 'fa-calendar-check',
				),
				'advisor_events' => array(
					'label'       => __( 'Events & Webinars Registration', 'tpd-tool' ),
					'description' => __( 'Access vendor training webinars, panels, and destination deep dives.', 'tpd-tool' ),
					'icon'        => 'fa-video',
				),
			),

			// Supplier Features
			'supplier' => array(
				'supplier_virtual_office' => array(
					'label'       => __( 'Virtual Office Multi-Rep Showcase', 'tpd-tool' ),
					'description' => __( 'Showcase regional sales directors with live online presence status.', 'tpd-tool' ),
					'icon'        => 'fa-users',
				),
				'supplier_chat' => array(
					'label'       => __( 'Direct Advisor Chat & Email Alerts', 'tpd-tool' ),
					'description' => __( 'Engage advisors in real-time chat with instant email notifications.', 'tpd-tool' ),
					'icon'        => 'fa-comment-dots',
				),
				'supplier_quote_requests' => array(
					'label'       => __( 'Quote Request Center (RFQ)', 'tpd-tool' ),
					'description' => __( 'Receive custom itinerary pricing and quote requests from verified advisors.', 'tpd-tool' ),
					'icon'        => 'fa-paper-plane',
				),
				'supplier_meetings' => array(
					'label'       => __( 'Meeting Scheduler & Office Hours', 'tpd-tool' ),
					'description' => __( 'Host live Q&A webinars and schedule 1-on-1 advisor consultations.', 'tpd-tool' ),
					'icon'        => 'fa-calendar-days',
				),
				'supplier_resources' => array(
					'label'       => __( 'Advisor Toolkits & Content Uploads', 'tpd-tool' ),
					'description' => __( 'Distribute digital brochures, commission sheets, and sales toolkits.', 'tpd-tool' ),
					'icon'        => 'fa-folder-open',
				),
				'supplier_analytics_deep' => array(
					'label'       => __( 'Full Funnel Analytics & Reporting', 'tpd-tool' ),
					'description' => __( 'Track listing saves, conversation volume, quote rates, and reported bookings.', 'tpd-tool' ),
					'icon'        => 'fa-chart-pie',
				),
				'supplier_featured' => array(
					'label'       => __( 'Priority Directory Placement', 'tpd-tool' ),
					'description' => __( 'Featured card status on the advisor dashboard and directory search.', 'tpd-tool' ),
					'icon'        => 'fa-star',
				),
			),
		);
	}

	/**
	 * Default Permissions Setup (Basic, Standard, Premium)
	 */
	public static function ensure_default_permissions() {
		$existing = get_option( self::OPTION_PERMISSIONS );
		if ( empty( $existing ) || ! isset( $existing['standard'] ) ) {
			$defaults = array(
				'basic' => array(
					// Basic Advisor (Free): Directory access, chat, meetings, saved suppliers, basic profile
					'advisor_chat'            => 1,
					'advisor_saved_suppliers' => 1,
					'advisor_meetings'        => 1,
					'advisor_events'          => 1,
					'advisor_basic_profile'   => 1,
					// Basic Supplier (Free): Basic listing & logo
					'supplier_basic_info'     => 1,
				),
				'standard' => array(
					// Standard Advisor ($9.99/mo): All basic + Cruise comparison, Discovery call, Enhanced profile
					'advisor_chat'            => 1,
					'advisor_saved_suppliers' => 1,
					'advisor_meetings'        => 1,
					'advisor_events'          => 1,
					'advisor_basic_profile'   => 1,
					'advisor_analytics_deep'  => 1,
					'advisor_inquiries'       => 1,
					'advisor_cruise_comp'     => 1,
					'advisor_discovery_call'  => 1,
					'advisor_enhanced_profile'=> 1,
					'advisor_travel_blog'     => 1,

					// Standard Supplier: Custom banner, 1 gallery, 1 video, 5 PDFs, team members, 1 news
					'supplier_basic_info'     => 1,
					'supplier_custom_banner'  => 1,
					'supplier_gallery'        => 1,
					'supplier_resources_5'    => 1,
					'supplier_team_mgmt'      => 1,
					'supplier_news_events'    => 1,
					'supplier_promos'         => 1,
					'supplier_chat'           => 1,
					'supplier_meetings'       => 1,
				),
				'premium' => array(
					// Premium Advisor ($19.99/mo): Everything unlocked + 10 specialties, albums, custom inquiry
					'advisor_chat'            => 1,
					'advisor_saved_suppliers' => 1,
					'advisor_meetings'        => 1,
					'advisor_events'          => 1,
					'advisor_basic_profile'   => 1,
					'advisor_analytics_deep'  => 1,
					'advisor_inquiries'       => 1,
					'advisor_cruise_comp'     => 1,
					'advisor_discovery_call'  => 1,
					'advisor_enhanced_profile'=> 1,
					'advisor_travel_blog'     => 1,
					'advisor_premium_albums'  => 1,
					'advisor_custom_inquiry'  => 1,
					'advisor_dispute_reviews' => 1,

					// Premium Supplier: 3 galleries, 10 videos, 20 PDFs, social packs, 5 events, 8 associations, priority
					'supplier_basic_info'     => 1,
					'supplier_custom_banner'  => 1,
					'supplier_gallery'        => 1,
					'supplier_resources_5'    => 1,
					'supplier_resources_20'   => 1,
					'supplier_team_mgmt'      => 1,
					'supplier_news_events'    => 1,
					'supplier_promos'         => 1,
					'supplier_chat'           => 1,
					'supplier_meetings'       => 1,
					'supplier_social_packs'   => 1,
					'supplier_videos_tab'     => 1,
					'supplier_analytics_deep' => 1,
					'supplier_featured'       => 1,
				),
			);
			// Also maintain backward-compatible aliases for free and paid
			$defaults['free'] = $defaults['basic'];
			$defaults['paid'] = $defaults['premium'];

			update_option( self::OPTION_PERMISSIONS, $defaults );
		}
	}

	/**
	 * Get User Tier ('basic', 'standard', 'premium')
	 */
	public static function get_user_tier( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return 'basic';
		}

		// Administrators always have full premium access
		if ( user_can( $user_id, 'manage_options' ) ) {
			return 'premium';
		}

		$tier = get_user_meta( $user_id, 'tpd_user_tier', true );
		if ( empty( $tier ) || 'free' === $tier ) {
			return 'basic';
		}
		if ( 'paid' === $tier ) {
			return 'standard';
		}
		return sanitize_text_field( $tier );
	}

	/**
	 * Set User Tier (supports Basic, Standard, Premium, and any Custom Plans created by Admin)
	 */
	public static function set_user_tier( $user_id, $tier ) {
		if ( 'free' === $tier ) {
			$tier = 'basic';
		} elseif ( 'paid' === $tier ) {
			$tier = 'standard';
		}
		$plans = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans() : array();
		$valid_tiers = ! empty( $plans ) ? array_keys( $plans ) : array( 'basic', 'standard', 'premium' );

		$tier = sanitize_key( $tier );
		if ( ! in_array( $tier, $valid_tiers, true ) ) {
			$tier = 'basic';
		}
		update_user_meta( $user_id, 'tpd_user_tier', $tier );

		// Also sync tier to linked CPT post
		$linked = get_posts( array(
			'post_type'      => array( 'travel_advisor', 'supplier_listing' ),
			'posts_per_page' => 1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );
		if ( ! empty( $linked ) ) {
			update_post_meta( $linked[0], 'tpd_user_tier', $tier );
		}

		return $tier;
	}

	/**
	 * Check if User Can Access a Specific Feature
	 */
	public static function can_user( $feature_slug, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}

		// Admins can do anything
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$tier = self::get_user_tier( $user_id );
		$permissions = get_option( self::OPTION_PERMISSIONS, array() );

		if ( isset( $permissions[ $tier ][ $feature_slug ] ) && $permissions[ $tier ][ $feature_slug ] ) {
			return true;
		}

		return false;
	}

	/**
	 * AJAX: Toggle User Tier by Admin (Promote / Demote Plan)
	 */
	public static function ajax_update_user_tier() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$target_user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$new_tier       = isset( $_POST['tier'] ) ? sanitize_text_field( $_POST['tier'] ) : 'basic';

		if ( ! $target_user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user ID' ) );
		}

		$updated_tier = self::set_user_tier( $target_user_id, $new_tier );
		$plan_info    = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plan( $updated_tier ) : array( 'name' => ucfirst( $updated_tier ) );

		if ( class_exists( 'TPD_Tool_Settings' ) ) {
			TPD_Tool_Settings::record_subscription( $target_user_id, $updated_tier, 'Month', 'Admin Assigned' );
		}

		wp_send_json_success( array(
			'user_id' => $target_user_id,
			'tier'    => $updated_tier,
			'label'   => isset( $plan_info['name'] ) ? $plan_info['name'] : ucfirst( $updated_tier ),
		) );
	}

	/**
	 * Export Advisors Demographics to CSV
	 */
	public static function export_advisors_csv() {
		if ( ! isset( $_GET['tpd_action'] ) || 'export_advisors_csv' !== $_GET['tpd_action'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized access.', 'tpd-tool' ) );
		}

		check_admin_referer( 'tpd_export_advisors', 'nonce' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=tpd-advisors-demographics-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array(
			'User ID',
			'Full Name',
			'Username',
			'Email',
			'Phone',
			'Location',
			'Agency Name',
			'Agency Address',
			'Role',
			'Agency Structure',
			'Consortia',
			'Host Agency',
			'Personal Sales Volume',
			'Agency Sales Volume',
			'CLIA Number',
			'IATA Number',
			'ARC Number',
			'TRUE Number',
			'Membership Tier',
			'Registered Date',
		) );

		$advisors = get_users( array(
			'role'    => 'travel_advisor',
			'orderby' => 'registered',
			'order'   => 'DESC',
		) );

		foreach ( $advisors as $adv ) {
			$uid = $adv->ID;
			fputcsv( $output, array(
				$uid,
				$adv->display_name,
				$adv->user_login,
				$adv->user_email,
				get_user_meta( $uid, 'tpd_phone', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_location', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_agency_name', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_agency_address', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_business_structure', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_agency_structure', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_consortia', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_host_agency', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_personal_sales_volume', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_agency_sales_volume', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_clia_num', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_iata_num', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_arc_num', true ) ?: 'N/A',
				get_user_meta( $uid, 'tpd_true_num', true ) ?: 'N/A',
				strtoupper( self::get_user_tier( $uid ) ),
				$adv->user_registered,
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Save Tier Permissions Matrix by Admin
	 */
	public static function ajax_save_tier_permissions() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$free_perms = isset( $_POST['free_permissions'] ) ? (array) $_POST['free_permissions'] : array();
		$paid_perms = isset( $_POST['paid_permissions'] ) ? (array) $_POST['paid_permissions'] : array();

		$new_permissions = array(
			'free' => array(),
			'paid' => array(),
		);

		foreach ( $free_perms as $f ) {
			$new_permissions['free'][ sanitize_text_field( $f ) ] = 1;
		}
		foreach ( $paid_perms as $p ) {
			$new_permissions['paid'][ sanitize_text_field( $p ) ] = 1;
		}

		update_option( self::OPTION_PERMISSIONS, $new_permissions );

		wp_send_json_success( array(
			'message' => __( 'Tier permissions matrix updated successfully!', 'tpd-tool' ),
		) );
	}

	/**
	 * Render Sleek Pro Feature Teaser Card
	 */
	public static function render_locked_card( $title, $description = '', $feature_slug = '' ) {
		ob_start();
		?>
		<div class="tpd-feature-locked-card">
			<div class="tpd-flc-icon"><i class="fa-solid fa-lock"></i></div>
			<span class="tpd-flc-pill"><i class="fa-solid fa-crown"></i> Pro Tier Feature</span>
			<h3><?php echo esc_html( $title ); ?></h3>
			<p><?php echo esc_html( $description ?: 'This advanced capability is available exclusively to Paid / Pro Tier members.' ); ?></p>
			<div class="tpd-flc-actions">
				<a href="mailto:admin@localhost.com?subject=Request%20Pro%20Tier%20Upgrade" class="tpd-btn tpd-btn-secondary tpd-btn-sm">
					<i class="fa-solid fa-arrow-up-right-from-square"></i> Request Pro Upgrade
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}

TPD_Tool_Tiers::init();

// Global helper functions
function tpd_get_user_tier( $user_id = null ) {
	return TPD_Tool_Tiers::get_user_tier( $user_id );
}

function tpd_can_user( $feature_slug, $user_id = null ) {
	return TPD_Tool_Tiers::can_user( $feature_slug, $user_id );
}
