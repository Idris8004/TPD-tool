<?php
/**
 * Native LMS module: loader and on/off switch.
 *
 * The LMS (courses, lessons, quizzes, certificates) runs inside TPD when this
 * module is enabled. Until a super admin enables it, nothing here runs: no
 * tables are created, no hooks are added, and TPD keeps using the external LMS
 * bridge in class-tpd-lms.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-tpd-lms-schema.php';
require_once __DIR__ . '/class-tpd-lms-store.php';
require_once __DIR__ . '/class-tpd-lms-progress.php';
require_once __DIR__ . '/class-tpd-lms-quiz.php';
require_once __DIR__ . '/class-tpd-lms-importer.php';
require_once __DIR__ . '/class-tpd-lms-coupons.php';
require_once __DIR__ . '/class-tpd-lms-mail.php';
require_once __DIR__ . '/class-tpd-lms-exam.php';
require_once __DIR__ . '/class-tpd-lms-reports.php';
require_once __DIR__ . '/class-tpd-lms-assign.php';
require_once __DIR__ . '/class-tpd-lms-examcard.php';
require_once __DIR__ . '/class-tpd-lms-admin.php';
require_once __DIR__ . '/class-tpd-lms-student.php';

class TPD_LMS_Native {

	const OPT_SETTINGS = 'tpd_lms_native';

	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'tpd-lms', 'TPD_LMS_Native_CLI' );
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		add_action( 'init', array( 'TPD_LMS_Schema', 'maybe_upgrade' ), 4 );
		TPD_LMS_Admin::init();
		TPD_LMS_Student::init();
		TPD_LMS_Mail::init();
		TPD_LMS_Exam::init();
		TPD_LMS_Assign::init();
	}

	/**
	 * Whether the native LMS is switched on.
	 */
	public static function is_enabled() {
		$settings = get_option( self::OPT_SETTINGS, array() );
		return is_array( $settings ) && ! empty( $settings['enabled'] );
	}

	/**
	 * Switch the native LMS on: create its tables, then save the flag.
	 */
	public static function enable() {
		TPD_LMS_Schema::install();
		$settings            = (array) get_option( self::OPT_SETTINGS, array() );
		$settings['enabled'] = 1;
		update_option( self::OPT_SETTINGS, $settings, false );
	}

	/**
	 * Switch the native LMS off. Its tables and data are kept.
	 */
	public static function disable() {
		$settings            = (array) get_option( self::OPT_SETTINGS, array() );
		$settings['enabled'] = 0;
		update_option( self::OPT_SETTINGS, $settings, false );
	}

	/**
	 * Organisation named on certificates. Defaults to TARC's full name, because
	 * the dashboard's WordPress site name ("Dashboard") isn't meant for learners.
	 */
	public static function issuer_name() {
		$settings = get_option( self::OPT_SETTINGS, array() );
		$name     = is_array( $settings ) && ! empty( $settings['issuer_name'] ) ? (string) $settings['issuer_name'] : '';
		return '' !== trim( $name ) ? $name : 'Travel Advisor Resource Center';
	}

	/**
	 * Current time in UTC, in MySQL format. All LMS timestamps are stored in UTC.
	 */
	public static function now( $offset_seconds = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() + (int) $offset_seconds );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Manage the native TPD LMS.
	 */
	class TPD_LMS_Native_CLI {

		/**
		 * Show whether the native LMS is on and which tables exist.
		 */
		public function status() {
			WP_CLI::log( 'Native LMS: ' . ( TPD_LMS_Native::is_enabled() ? 'on' : 'off' ) );
			WP_CLI::log( 'Schema version: ' . ( get_option( TPD_LMS_Schema::OPT_DB_VER ) ?: 'not installed' ) );
			foreach ( TPD_LMS_Schema::table_names() as $table ) {
				WP_CLI::log( sprintf( '  %-40s %s', $table, TPD_LMS_Schema::table_exists( $table ) ? 'ok' : 'missing' ) );
			}
		}

		/**
		 * Create the tables and switch the native LMS on.
		 */
		public function enable() {
			TPD_LMS_Native::enable();
			WP_CLI::success( 'Native LMS is on.' );
		}

		/**
		 * Switch the native LMS off. Data is kept.
		 */
		public function disable() {
			TPD_LMS_Native::disable();
			WP_CLI::success( 'Native LMS is off. Its data is kept.' );
		}
	}
}

TPD_LMS_Native::init();
