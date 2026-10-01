<?php
/**
 * Core Orchestrator for TPD Tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Core {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'template_include', array( $this, 'template_loader' ), 99 );
		add_action( 'admin_init', array( $this, 'restrict_admin_access' ) );
		add_action( 'after_setup_theme', array( $this, 'hide_admin_bar' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	public function enqueue_assets() {
		// Font Awesome 6
		wp_enqueue_style( 'font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );

		// Google Fonts
		wp_enqueue_style( 'tpd-fonts', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Caveat:wght@600&display=swap', array(), null );

		// TPD Tool Stylesheet
		wp_enqueue_style( 'tpd-tool-style', TPD_TOOL_URL . 'assets/css/tpd-tool.css', array( 'font-awesome-6', 'tpd-fonts' ), TPD_TOOL_VERSION );
		wp_enqueue_style( 'tpd-chat-style', TPD_TOOL_URL . 'assets/css/tpd-chat.css', array( 'tpd-tool-style' ), TPD_TOOL_VERSION );

		// Scripts
		wp_enqueue_script( 'tpd-dashboard-js', TPD_TOOL_URL . 'assets/js/tpd-dashboard.js', array( 'jquery' ), TPD_TOOL_VERSION, true );
		wp_enqueue_script( 'tpd-chat-js', TPD_TOOL_URL . 'assets/js/tpd-chat.js', array( 'jquery' ), TPD_TOOL_VERSION, true );
		wp_enqueue_script( 'tpd-admin-js', TPD_TOOL_URL . 'assets/js/tpd-admin.js', array( 'jquery' ), TPD_TOOL_VERSION, true );

		$current_uid = get_current_user_id();

		wp_localize_script( 'tpd-dashboard-js', 'tpd_data', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'tpd_nonce' ),
			'chat_nonce'  => wp_create_nonce( 'tpd_chat_nonce' ),
			'admin_nonce' => wp_create_nonce( 'tpd_admin_nonce' ),
			'site_url'    => home_url(),
			'user_id'     => $current_uid,
			'user_tier'   => tpd_get_user_tier( $current_uid ),
			'is_advisor'  => TPD_Tool_Roles::is_advisor( $current_uid ),
			'is_supplier' => TPD_Tool_Roles::is_supplier( $current_uid ),
			'is_admin'    => TPD_Tool_Roles::is_admin( $current_uid ),
		) );
	}

	public function enqueue_admin_assets() {
		wp_enqueue_style( 'font-awesome-6', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
		wp_enqueue_style( 'tpd-fonts', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'tpd-tool-style', TPD_TOOL_URL . 'assets/css/tpd-tool.css', array( 'font-awesome-6' ), TPD_TOOL_VERSION );
		wp_enqueue_script( 'tpd-admin-js', TPD_TOOL_URL . 'assets/js/tpd-admin.js', array( 'jquery' ), TPD_TOOL_VERSION, true );

		wp_localize_script( 'tpd-admin-js', 'tpd_data', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'tpd_nonce' ),
			'admin_nonce' => wp_create_nonce( 'tpd_admin_nonce' ),
		) );
	}

	public function template_loader( $template ) {
		$uri = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

		// Advisor Dashboard
		if ( $uri === 'advisor-dashboard' || is_page( 'advisor-dashboard' ) || $uri === 'advisor-portal' ) {
			$file = TPD_TOOL_DIR . 'templates/advisor/dashboard.php';
			if ( file_exists( $file ) ) {
				status_header( 200 );
				return $file;
			}
		}

		// Supplier Dashboard
		if ( $uri === 'supplier-dashboard' || is_page( 'supplier-dashboard' ) || $uri === 'supplier-portal' ) {
			$file = TPD_TOOL_DIR . 'templates/supplier/dashboard.php';
			if ( file_exists( $file ) ) {
				status_header( 200 );
				return $file;
			}
		}

		// Super Admin Dashboard
		if ( $uri === 'tpd-admin' || is_page( 'tpd-admin' ) ) {
			$file = TPD_TOOL_DIR . 'templates/admin/super-admin-dashboard.php';
			if ( file_exists( $file ) ) {
				status_header( 200 );
				return $file;
			}
		}

		// Advisor Registration Page
		if ( $uri === 'advisor-registration' || is_page( 'advisor-registration' ) ) {
			$file = TPD_TOOL_DIR . 'templates/registration/advisor-registration.php';
			if ( file_exists( $file ) ) {
				status_header( 200 );
				return $file;
			}
		}

		// Supplier Registration Page
		if ( $uri === 'supplier-registration' || is_page( 'supplier-registration' ) ) {
			$file = TPD_TOOL_DIR . 'templates/registration/supplier-registration.php';
			if ( file_exists( $file ) ) {
				status_header( 200 );
				return $file;
			}
		}

		return $template;
	}

	public function restrict_admin_access() {
		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();
		if ( in_array( 'travel_advisor', (array) $user->roles, true ) ) {
			wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
			exit;
		}
		if ( in_array( 'supplier', (array) $user->roles, true ) ) {
			wp_safe_redirect( home_url( '/supplier-dashboard/' ) );
			exit;
		}
	}

	public function hide_admin_bar() {
		if ( ! is_user_logged_in() ) return;
		$user = wp_get_current_user();
		if ( in_array( 'travel_advisor', (array) $user->roles, true ) || in_array( 'supplier', (array) $user->roles, true ) ) {
			show_admin_bar( false );
		}
	}

	public function register_admin_menu() {
		add_menu_page(
			__( 'TPD Super Admin', 'tpd-tool' ),
			__( 'TPD Control Hub', 'tpd-tool' ),
			'manage_options',
			'tpd-super-admin',
			array( $this, 'render_super_admin_page' ),
			'dashicons-superhero',
			3
		);
	}

	public function render_super_admin_page() {
		include TPD_TOOL_DIR . 'templates/admin/super-admin-dashboard.php';
	}

	public static function activate() {
		TPD_Tool_Roles::register_roles();
		TPD_Tool_CPT::register_all();
		TPD_Tool_Tiers::ensure_default_permissions();
		TPD_Tool_Seeder::seed_all();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
