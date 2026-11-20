<?php
/**
 * Travel Advisor Login Template (Unified Portal Gateway)
 * Both Advisor and Supplier logins are now consolidated into portal-login.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$atts = array( 'default_portal' => 'advisor' );
include TPD_TOOL_DIR . 'templates/auth/portal-login.php';
