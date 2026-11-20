<?php
/**
 * Test bootstrap for the native LMS module.
 *
 * Loads a real WordPress that has this plugin active, then switches the native
 * LMS on. Point TPD_WP_LOAD at that site's wp-load.php. Use a local or staging
 * site only: tests create and delete their own users and courses.
 *
 *   TPD_WP_LOAD=/path/to/wordpress/wp-load.php php phpunit.phar -c tests/lms/phpunit.xml
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$wp_load = getenv( 'TPD_WP_LOAD' );
if ( ! $wp_load || ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "Set TPD_WP_LOAD to the wp-load.php of a local WordPress with TPD active.\n" );
	exit( 1 );
}

$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
define( 'WP_USE_THEMES', false );
require $wp_load;

if ( ! class_exists( 'TPD_LMS_Native' ) ) {
	fwrite( STDERR, "TPD Tool is not active on that WordPress site.\n" );
	exit( 1 );
}

TPD_LMS_Native::enable();

require __DIR__ . '/LmsTestCase.php';
