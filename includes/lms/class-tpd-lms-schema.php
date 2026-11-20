<?php
/**
 * Native LMS database tables ({prefix}tpd_lms_*).
 *
 * Students and instructors are WordPress users. Payments stay in the existing
 * {prefix}tpd_transactions ledger, so there is no separate orders table:
 * enrollments.tx_id points at the TPD transaction that paid for the seat.
 *
 * All timestamps are UTC.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Schema {

	const DB_VERSION = '1.1';
	const OPT_DB_VER = 'tpd_lms_db_ver';

	/** Short names of every LMS table, without the prefix. */
	const TABLES = array(
		'courses',
		'chapters',
		'lessons',
		'quizzes',
		'questions',
		'question_options',
		'enrollments',
		'lesson_completions',
		'quiz_attempts',
		'certificates',
		'coupons',
		'exam_access_codes',
		'proctor_requests',
	);

	/**
	 * Full table name for a short name, e.g. t( 'courses' ) => wp_tpd_lms_courses.
	 */
	public static function t( $short ) {
		global $wpdb;
		return $wpdb->prefix . 'tpd_lms_' . $short;
	}

	public static function table_names() {
		return array_map( array( __CLASS__, 't' ), self::TABLES );
	}

	public static function table_exists( $table ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function maybe_upgrade() {
		if ( get_option( self::OPT_DB_VER ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or upgrade every LMS table. Safe to run more than once.
	 */
	public static function install() {
		global $wpdb;
		$c = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = 'CREATE TABLE ' . self::t( 'courses' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			instructor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL DEFAULT '',
			slug VARCHAR(190) NOT NULL DEFAULT '',
			subtitle VARCHAR(255) NULL,
			description LONGTEXT NULL,
			thumbnail_url VARCHAR(500) NULL,
			level VARCHAR(40) NOT NULL DEFAULT 'Beginner',
			price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			status VARCHAR(16) NOT NULL DEFAULT 'draft',
			sequential TINYINT(1) NOT NULL DEFAULT 0,
			drip_enabled TINYINT(1) NOT NULL DEFAULT 0,
			certificate_enabled TINYINT(1) NOT NULL DEFAULT 1,
			access_days SMALLINT UNSIGNED NULL,
			tier_included VARCHAR(40) NOT NULL DEFAULT 'none',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY instructor_id (instructor_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'chapters' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			position INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'lessons' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL,
			chapter_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL DEFAULT '',
			type VARCHAR(16) NOT NULL DEFAULT 'text',
			content LONGTEXT NULL,
			video_url VARCHAR(500) NULL,
			attachment_id BIGINT UNSIGNED NULL,
			duration_minutes SMALLINT UNSIGNED NULL,
			is_preview TINYINT(1) NOT NULL DEFAULT 0,
			drip_days SMALLINT UNSIGNED NULL,
			position INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY chapter_id (chapter_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'quizzes' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lesson_id BIGINT UNSIGNED NOT NULL,
			is_final TINYINT(1) NOT NULL DEFAULT 0,
			passing_score TINYINT UNSIGNED NOT NULL DEFAULT 70,
			max_attempts SMALLINT UNSIGNED NULL,
			time_limit_minutes SMALLINT UNSIGNED NULL,
			questions_per_attempt SMALLINT UNSIGNED NULL,
			shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
			shuffle_options TINYINT(1) NOT NULL DEFAULT 0,
			show_answers TINYINT(1) NOT NULL DEFAULT 1,
			require_all_lessons TINYINT(1) NOT NULL DEFAULT 0,
			access_mode VARCHAR(16) NOT NULL DEFAULT 'open',
			access_password TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY lesson_id (lesson_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'questions' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			topic VARCHAR(120) NULL,
			type VARCHAR(16) NOT NULL DEFAULT 'single',
			prompt TEXT NOT NULL,
			explanation TEXT NULL,
			accepted_answers TEXT NULL,
			points SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			position INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_id (quiz_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'question_options' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			question_id BIGINT UNSIGNED NOT NULL,
			text VARCHAR(500) NOT NULL DEFAULT '',
			is_correct TINYINT(1) NOT NULL DEFAULT 0,
			position INT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY question_id (question_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'enrollments' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			tx_id BIGINT UNSIGNED NULL,
			progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
			enrolled_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			completed_at DATETIME NULL,
			last_activity_at DATETIME NULL,
			last_lesson_id BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_course (user_id,course_id),
			KEY course_id (course_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'lesson_completions' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			lesson_id BIGINT UNSIGNED NOT NULL,
			completed_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY enrollment_lesson (enrollment_id,lesson_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'quiz_attempts' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			question_order LONGTEXT NOT NULL,
			answers LONGTEXT NULL,
			results LONGTEXT NULL,
			points_earned SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			points_total SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			score TINYINT UNSIGNED NULL,
			passed TINYINT(1) NOT NULL DEFAULT 0,
			started_at DATETIME NOT NULL,
			expires_at DATETIME NULL,
			submitted_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_enrollment (quiz_id,enrollment_id),
			KEY user_id (user_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'certificates' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			course_id BIGINT UNSIGNED NOT NULL,
			code VARCHAR(20) NOT NULL,
			final_score TINYINT UNSIGNED NULL,
			issued_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY enrollment_id (enrollment_id),
			UNIQUE KEY code (code),
			KEY user_id (user_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'coupons' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NULL,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			code VARCHAR(60) NOT NULL,
			type VARCHAR(10) NOT NULL DEFAULT 'percent',
			value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			max_uses INT UNSIGNED NULL,
			uses INT UNSIGNED NOT NULL DEFAULT 0,
			expires_at DATE NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'exam_access_codes' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			code_hash VARCHAR(64) NOT NULL,
			code_enc TEXT NULL,
			proctor_name VARCHAR(120) NULL,
			proctor_email VARCHAR(190) NULL,
			notes TEXT NULL,
			expires_at DATETIME NOT NULL,
			used_at DATETIME NULL,
			quiz_attempt_id BIGINT UNSIGNED NULL,
			revoked_at DATETIME NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY quiz_enrollment (quiz_id,enrollment_id)
		) $c;";

		$sql[] = 'CREATE TABLE ' . self::t( 'proctor_requests' ) . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			quiz_id BIGINT UNSIGNED NOT NULL,
			enrollment_id BIGINT UNSIGNED NOT NULL,
			proctor_name VARCHAR(120) NOT NULL DEFAULT '',
			proctor_email VARCHAR(190) NOT NULL DEFAULT '',
			proctor_phone VARCHAR(40) NULL,
			proctor_relationship VARCHAR(120) NULL,
			preferred_at DATETIME NULL,
			timezone VARCHAR(64) NULL,
			student_notes TEXT NULL,
			status VARCHAR(16) NOT NULL DEFAULT 'pending',
			decision_note TEXT NULL,
			decided_by BIGINT UNSIGNED NULL,
			decided_at DATETIME NULL,
			exam_access_code_id BIGINT UNSIGNED NULL,
			proctor_token_hash VARCHAR(64) NULL,
			proctor_confirmed_at DATETIME NULL,
			proctor_flagged TINYINT(1) NOT NULL DEFAULT 0,
			proctor_report TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY proctor_token_hash (proctor_token_hash),
			KEY status_created (status,created_at)
		) $c;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::OPT_DB_VER, self::DB_VERSION, false );
	}
}
