<?php
/**
 * Elementor Integration for TPD Tool
 * Registers custom Elementor category and widgets for Dashboards, Login, Registration, and Chat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Elementor {

	public static function init() {
		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'add_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
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

		$widgets_manager->register( new \TPD_Elementor_Advisor_Dashboard() );
		$widgets_manager->register( new \TPD_Elementor_Supplier_Dashboard() );
		$widgets_manager->register( new \TPD_Elementor_Admin_Dashboard() );
		$widgets_manager->register( new \TPD_Elementor_Chat_Inbox() );
		$widgets_manager->register( new \TPD_Elementor_Advisor_Login() );
		$widgets_manager->register( new \TPD_Elementor_Supplier_Login() );
		$widgets_manager->register( new \TPD_Elementor_Advisor_Registration() );
		$widgets_manager->register( new \TPD_Elementor_Supplier_Registration() );
	}
}

TPD_Tool_Elementor::init();
