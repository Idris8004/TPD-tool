<?php
/**
 * Elementor Integration for TPD Tool
 * Registers custom Elementor category, Portal/Auth/Dynamic Field widgets,
 * and Native Elementor Dynamic Tags (Advisor & Supplier groups) synced with Dashboards & ACF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Elementor {

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register_dynamic_tags' ) );
	}

	public static function add_category( $elements_manager ) {
		$elements_manager->add_category(
			'tpd-elements',
			array(
				'title' => __( 'TPD Marketplace & Portals', 'tpd-tool' ),
				'icon'  => 'fa fa-compass',
			)
		);
	}

	public static function register_widgets( $widgets_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		// Require widget classes
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-advisor-dashboard.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-supplier-dashboard.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-admin-dashboard.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-chat-inbox.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-advisor-login.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-supplier-login.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-advisor-registration.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-supplier-registration.php';
		require_once TPD_TOOL_DIR . 'widgets/elementor/widget-dynamic-field.php';

		$widgets_manager->register( new \TPD_Elementor_Advisor_Dashboard() );
		$widgets_manager->register( new \TPD_Elementor_Supplier_Dashboard() );
		$widgets_manager->register( new \TPD_Elementor_Admin_Dashboard() );
		$widgets_manager->register( new \TPD_Elementor_Chat_Inbox() );
		$widgets_manager->register( new \TPD_Elementor_Advisor_Login() );
		$widgets_manager->register( new \TPD_Elementor_Supplier_Login() );
		$widgets_manager->register( new \TPD_Elementor_Advisor_Registration() );
		$widgets_manager->register( new \TPD_Elementor_Supplier_Registration() );
		$widgets_manager->register( new \TPD_Elementor_Dynamic_Field() );
	}

	/**
	 * Register Native Elementor Dynamic Tags for Advisor, Supplier, Event & ACF Fields
	 */
	public static function register_dynamic_tags( $dynamic_tags_manager ) {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		require_once TPD_TOOL_DIR . 'widgets/elementor/dynamic-tags/class-tpd-dynamic-tags.php';

		if ( method_exists( $dynamic_tags_manager, 'register_group' ) ) {
			$dynamic_tags_manager->register_group(
				'tpd-advisor-tags',
				array(
					'title' => __( 'TPD — Travel Advisor Fields', 'tpd-tool' ),
				)
			);
			$dynamic_tags_manager->register_group(
				'tpd-supplier-tags',
				array(
					'title' => __( 'TPD — Supplier Partner Fields', 'tpd-tool' ),
				)
			);
		}

		$tag_classes = array(
			// Advisor Tags
			'TPD_Tag_Advisor_Name',
			'TPD_Tag_Advisor_Agency',
			'TPD_Tag_Advisor_Contact',
			'TPD_Tag_Advisor_Location',
			'TPD_Tag_Advisor_Bio',
			'TPD_Tag_Advisor_Affiliation',
			'TPD_Tag_Advisor_Certifications',
			'TPD_Tag_Advisor_Experience',
			'TPD_Tag_Advisor_Specialties',
			'TPD_Tag_Advisor_Any_Field',
			// Supplier Tags
			'TPD_Tag_Supplier_Company',
			'TPD_Tag_Supplier_Tagline',
			'TPD_Tag_Supplier_Description',
			'TPD_Tag_Supplier_Headquarters',
			'TPD_Tag_Supplier_Rep',
			'TPD_Tag_Supplier_Promo',
			'TPD_Tag_Supplier_Programs',
			// Media & Link Tags
			'TPD_Dynamic_Tag_Image',
			'TPD_Dynamic_Tag_URL',
		);

		foreach ( $tag_classes as $tc ) {
			if ( class_exists( $tc ) ) {
				$dynamic_tags_manager->register( new $tc() );
			}
		}
	}
}

TPD_Tool_Elementor::init();
