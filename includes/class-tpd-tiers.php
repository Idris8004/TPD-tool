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
	 * Default Permissions Setup
	 */
	public static function ensure_default_permissions() {
		if ( false === get_option( self::OPTION_PERMISSIONS ) ) {
			$defaults = array(
				'free' => array(
					// Free advisors can save suppliers and view basic events
					'advisor_saved_suppliers' => 1,
					'advisor_events'          => 1,
					// Free suppliers can manage basic content
					'supplier_resources'      => 1,
				),
				'paid' => array(
					// Paid tier gets everything unlocked!
					'advisor_chat'            => 1,
					'advisor_analytics_deep'  => 1,
					'advisor_inquiries'       => 1,
					'advisor_saved_suppliers' => 1,
					'advisor_meetings'        => 1,
					'advisor_events'          => 1,

					'supplier_virtual_office' => 1,
					'supplier_chat'           => 1,
					'supplier_quote_requests' => 1,
					'supplier_meetings'       => 1,
					'supplier_resources'      => 1,
					'supplier_analytics_deep' => 1,
					'supplier_featured'       => 1,
				),
			);
			update_option( self::OPTION_PERMISSIONS, $defaults );
		}
	}

	/**
	 * Get User Tier ('free' or 'paid')
	 */
	public static function get_user_tier( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return 'free';
		}

		// Administrators always have full paid/super access
		if ( user_can( $user_id, 'manage_options' ) ) {
			return 'paid';
		}

		$tier = get_user_meta( $user_id, 'tpd_user_tier', true );
		return ! empty( $tier ) ? sanitize_text_field( $tier ) : 'free';
	}

	/**
	 * Set User Tier
	 */
	public static function set_user_tier( $user_id, $tier ) {
		$valid_tier = ( 'paid' === $tier ) ? 'paid' : 'free';
		update_user_meta( $user_id, 'tpd_user_tier', $valid_tier );
		return $valid_tier;
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
	 * AJAX: Toggle User Tier by Admin
	 */
	public static function ajax_update_user_tier() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$target_user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$new_tier       = isset( $_POST['tier'] ) ? sanitize_text_field( $_POST['tier'] ) : 'free';

		if ( ! $target_user_id ) {
			wp_send_json_error( array( 'message' => 'Invalid user ID' ) );
		}

		$updated_tier = self::set_user_tier( $target_user_id, $new_tier );

		wp_send_json_success( array(
			'user_id' => $target_user_id,
			'tier'    => $updated_tier,
			'label'   => ( 'paid' === $updated_tier ) ? 'Paid (Pro Tier)' : 'Free Tier',
		) );
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
