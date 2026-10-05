<?php
/**
 * Plugin Name: TPD Tool - Travel Partner Directory Enterprise Suite
 * Plugin URI: https://github.com/Idris8004/TPD-tool
 * Description: Enterprise-grade travel marketplace suite featuring Multi-Step Registration Wizards, Dual Portals (Advisor & Supplier Dashboards), Bi-Directional WP CPT + ACF Sync, Dynamic Membership Plan Builder, Payment Integration, Real-Time Chat, Analytics Engine, and Super Admin Control Hub.
 * Version: 2.5.1
 * Author: Travel Partner Directory (TARC)
 * Author URI: https://github.com/Idris8004/TPD-tool
 * GitHub Plugin URI: Idris8004/TPD-tool
 * GitHub Branch: main
 * Primary Branch: main
 * Text Domain: tpd-tool
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TPD_TOOL_VERSION', '2.5.1' );
define( 'TPD_TOOL_FILE', __FILE__ );
define( 'TPD_TOOL_DIR', plugin_dir_path( __FILE__ ) );
define( 'TPD_TOOL_URL', plugin_dir_url( __FILE__ ) );

// Load Core Architecture Classes
require_once TPD_TOOL_DIR . 'includes/class-tpd-updater.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-roles.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-settings.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-tiers.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-cpt.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-analytics.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-chat.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-favorites.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-events.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-registration.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-profile-editor.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-shortcodes.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-elementor.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-seeder.php';
require_once TPD_TOOL_DIR . 'includes/class-tpd-core.php';

/**
 * Main Plugin Bootstrap
 */
function tpd_tool_init() {
	return TPD_Tool_Core::get_instance();
}

add_action( 'plugins_loaded', 'tpd_tool_init' );

// Activation & Deactivation
register_activation_hook( TPD_TOOL_FILE, array( 'TPD_Tool_Core', 'activate' ) );
register_deactivation_hook( TPD_TOOL_FILE, array( 'TPD_Tool_Core', 'deactivate' ) );
