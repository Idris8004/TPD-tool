<?php
/**
 * Native LMS course builder: super admin AJAX actions.
 *
 * Every action checks the TPD admin nonce, requires manage_options, and checks
 * that the item belongs to a course the user can manage. Registered only while
 * the native LMS is on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Admin {

	const ACTIONS = array(
		'course_list',
		'course_get',
		'course_save',
		'course_delete',
		'chapter_save',
		'chapter_delete',
		'reorder',
		'lesson_get',
		'lesson_save',
		'lesson_delete',
		'quiz_save',
		'question_save',
		'question_delete',
		'question_import',
		'settings_save',
		'coupon_list',
		'coupon_save',
		'coupon_toggle',
		'coupon_delete',
		'quiz_access_save',
		'students',
		'student_detail',
		'student_enroll',
		'code_create',
		'code_revoke',
		'quiz_report',
		'exam_unlock',
		'exam_relock',
		'exam_key',
		'proctor_list',
		'proctor_approve',
		'proctor_decline',
		'proctor_resend',
		'media_upload',
		'assignments',
		'assignment_review',
	);

	/** Largest question file accepted for import, in bytes. */
	const IMPORT_MAX_BYTES = 5242880;

	public static function init() {
		foreach ( self::ACTIONS as $action ) {
			add_action( 'wp_ajax_tpd_lms_' . $action, array( __CLASS__, 'dispatch' ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_wp_admin' ), 20 );
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                              */
	/* ------------------------------------------------------------------ */

	public static function enqueue() {
		$uri = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		if ( 'tpd-admin' === $uri || is_page( 'tpd-admin' ) ) {
			self::enqueue_builder();
		}
	}

	public static function enqueue_wp_admin( $hook ) {
		if ( false !== strpos( (string) $hook, 'tpd-super-admin' ) ) {
			self::enqueue_builder();
		}
	}

	private static function enqueue_builder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$js  = TPD_TOOL_DIR . 'assets/js/tpd-lms-admin.js';
		$css = TPD_TOOL_DIR . 'assets/css/tpd-lms.css';
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'tpd-lms', TPD_TOOL_URL . 'assets/css/tpd-lms.css', array(), file_exists( $css ) ? filemtime( $css ) : TPD_TOOL_VERSION );
		wp_enqueue_script( 'tpd-lms-admin', TPD_TOOL_URL . 'assets/js/tpd-lms-admin.js', array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : TPD_TOOL_VERSION, true );
		wp_localize_script( 'tpd-lms-admin', 'tpdLmsAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tpd_admin_nonce' ),
			'tiers'   => self::tier_choices(),
		) );
	}

	private static function tier_choices() {
		$choices = array( 'none' => __( 'Not included (sold separately)', 'tpd-tool' ) );
		$plans   = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans() : array();
		foreach ( (array) $plans as $slug => $plan ) {
			/* translators: %s: membership plan name */
			$choices[ $slug ] = sprintf( __( 'Included with %s and above', 'tpd-tool' ), isset( $plan['name'] ) ? $plan['name'] : ucfirst( $slug ) );
		}
		$choices['all'] = __( 'Included for every member', 'tpd-tool' );
		return $choices;
	}

	/* ------------------------------------------------------------------ */
	/* Dispatch and guards                                                 */
	/* ------------------------------------------------------------------ */

	public static function dispatch() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only super admins can manage courses.', 'tpd-tool' ) ), 403 );
		}
		$action = isset( $_POST['action'] ) ? substr( sanitize_key( wp_unslash( $_POST['action'] ) ), strlen( 'tpd_lms_' ) ) : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'tpd-tool' ) ), 400 );
		}
		$result = call_user_func( array( __CLASS__, 'action_' . $action ), self::post() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
		wp_send_json_success( $result );
	}

	/** POST data with slashes removed. Values are sanitized by the store. */
	private static function post() {
		return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatch().
	}

	private static function course_or_error( $course_id ) {
		$course = TPD_LMS_Store::get_course( absint( $course_id ) );
		if ( ! $course ) {
			return new WP_Error( 'course_missing', __( 'That course no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		if ( ! TPD_LMS_Store::can_manage_course( $course ) ) {
			return new WP_Error( 'forbidden', __( 'You can’t manage this course.', 'tpd-tool' ) );
		}
		return $course;
	}

	private static function lesson_or_error( $lesson_id ) {
		$lesson = TPD_LMS_Store::find( 'lessons', $lesson_id );
		if ( ! $lesson ) {
			return new WP_Error( 'lesson_missing', __( 'That lesson no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$course = self::course_or_error( $lesson->course_id );
		return is_wp_error( $course ) ? $course : $lesson;
	}

	private static function quiz_or_error( $quiz_id ) {
		$quiz = TPD_LMS_Store::find( 'quizzes', $quiz_id );
		if ( ! $quiz ) {
			return new WP_Error( 'quiz_missing', __( 'That quiz no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$lesson = self::lesson_or_error( $quiz->lesson_id );
		return is_wp_error( $lesson ) ? $lesson : $quiz;
	}

	/* ------------------------------------------------------------------ */
	/* Courses                                                             */
	/* ------------------------------------------------------------------ */

	private static function action_course_list() {
		global $wpdb;
		$out = array();
		foreach ( TPD_LMS_Store::list_courses( 'all' ) as $course ) {
			$out[] = self::course_summary( $course );
		}
		return array(
			'courses'  => $out,
			'settings' => array(
				'issuer_name'   => TPD_LMS_Native::issuer_name(),
				'support_email' => TPD_LMS_Mail::support_email(),
				'pending_proctor' => (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . TPD_LMS_Schema::t( 'proctor_requests' ) . " WHERE status = 'pending'" ),
				'verify_url'  => home_url( '/' . TPD_LMS_Student::CERT_PATH . '/' ),
			),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Exam access, students, reports, proctoring                          */
	/* ------------------------------------------------------------------ */

	private static function action_quiz_access_save( $p ) {
		$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}
		$ok = TPD_LMS_Exam::save_access( $quiz->id, isset( $p['access_mode'] ) ? (string) $p['access_mode'] : 'open', isset( $p['access_password'] ) ? (string) $p['access_password'] : null );
		return is_wp_error( $ok ) ? $ok : self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	private static function enrollment_or_error( $enrollment_id ) {
		$e = TPD_LMS_Store::find( 'enrollments', $enrollment_id );
		if ( ! $e ) {
			return new WP_Error( 'enrollment_missing', __( 'That enrollment no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$course = self::course_or_error( $e->course_id );
		return is_wp_error( $course ) ? $course : $e;
	}

	private static function action_students( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		return array( 'course' => self::course_summary( $course ), 'students' => TPD_LMS_Reports::students( $course->id ) );
	}

	private static function action_student_detail( $p ) {
		$e = self::enrollment_or_error( isset( $p['enrollment_id'] ) ? $p['enrollment_id'] : 0 );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$card = TPD_LMS_ExamCard::state( $e );
		if ( $card ) {
			$by                    = $e->exam_unlocked_by ? get_userdata( (int) $e->exam_unlocked_by ) : null;
			$card['unlocked_at']   = $e->exam_unlocked_at;
			$card['unlocked_by']   = $e->exam_unlocked_at ? ( $by ? $by->display_name : __( 'unlock key', 'tpd-tool' ) ) : null;
			$card['key_expires']   = $card['has_key'] ? $e->exam_unlock_key_expires : null;
			$card['key_days']      = TPD_LMS_ExamCard::KEY_DAYS;
		}
		return array( 'enrollment_id' => (int) $e->id, 'valid_for' => TPD_LMS_Exam::VALID_FOR, 'exam_card' => $card ) + TPD_LMS_Reports::student( $e );
	}

	private static function action_exam_unlock( $p ) {
		$e = self::enrollment_or_error( isset( $p['enrollment_id'] ) ? $p['enrollment_id'] : 0 );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		TPD_LMS_ExamCard::unlock( $e, get_current_user_id() );
		return self::action_student_detail( array( 'enrollment_id' => $e->id ) );
	}

	private static function action_exam_relock( $p ) {
		$e = self::enrollment_or_error( isset( $p['enrollment_id'] ) ? $p['enrollment_id'] : 0 );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		TPD_LMS_ExamCard::relock( $e );
		return self::action_student_detail( array( 'enrollment_id' => $e->id ) );
	}

	/** Make an unlock key; shown once, and optionally emailed to the advisor. */
	private static function action_exam_key( $p ) {
		$e = self::enrollment_or_error( isset( $p['enrollment_id'] ) ? $p['enrollment_id'] : 0 );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$email = ! empty( $p['email'] ) && '0' !== (string) $p['email'];
		$key   = TPD_LMS_ExamCard::create_key( $e, isset( $p['days'] ) ? (int) $p['days'] : 7, $email );
		return array( 'unlock_key' => $key, 'emailed' => $email ) + self::action_student_detail( array( 'enrollment_id' => $e->id ) );
	}

	/** Enroll an existing TPD member by email (they get the "you're enrolled" email). */
	private static function action_student_enroll( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$user = get_user_by( 'email', sanitize_email( isset( $p['email'] ) ? (string) $p['email'] : '' ) );
		if ( ! $user ) {
			return new WP_Error( 'no_member', __( 'No TPD member has that email address. They need a TPD account first.', 'tpd-tool' ) );
		}
		if ( TPD_LMS_Progress::get_enrollment( $user->ID, $course->id ) ) {
			return new WP_Error( 'already_enrolled', __( 'That member is already enrolled.', 'tpd-tool' ) );
		}
		$e = TPD_LMS_Progress::enroll( $user->ID, $course->id );
		return is_wp_error( $e ) ? $e : self::action_students( array( 'course_id' => $course->id ) );
	}

	/**
	 * Staff make a one-time code for one student. The code is shown once here;
	 * with a proctor email it's also sent to the proctor with the private link.
	 */
	private static function action_code_create( $p ) {
		$e = self::enrollment_or_error( isset( $p['enrollment_id'] ) ? $p['enrollment_id'] : 0 );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		$quiz = TPD_LMS_Store::find( 'quizzes', isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
		$lesson = $quiz ? TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) : null;
		if ( ! $quiz || ! $lesson || (int) $lesson->course_id !== (int) $e->course_id ) {
			return new WP_Error( 'quiz_missing', __( 'That exam isn’t part of this course.', 'tpd-tool' ) );
		}
		$email = sanitize_email( isset( $p['proctor_email'] ) ? (string) $p['proctor_email'] : '' );
		$name  = sanitize_text_field( isset( $p['proctor_name'] ) ? (string) $p['proctor_name'] : '' );
		if ( '' !== $email && ! is_email( $email ) ) {
			return new WP_Error( 'bad_email', __( 'Check the proctor’s email address.', 'tpd-tool' ) );
		}
		$code = TPD_LMS_Exam::create_code( $quiz->id, $e->id, isset( $p['valid_hours'] ) ? (int) $p['valid_hours'] : 72, array(
			'name'  => $name,
			'email' => $email,
			'notes' => isset( $p['notes'] ) ? (string) $p['notes'] : '',
		) );
		if ( is_wp_error( $code ) ) {
			return $code;
		}
		$emailed = null;
		if ( '' !== $email ) {
			// Record it as an approved request so the proctor gets the same email and private link.
			$rid = TPD_LMS_Store::insert( 'proctor_requests', array(
				'quiz_id'             => (int) $quiz->id,
				'enrollment_id'       => (int) $e->id,
				'proctor_name'        => '' !== $name ? $name : __( 'Proctor', 'tpd-tool' ),
				'proctor_email'       => $email,
				'status'              => 'pending',
				'timezone'            => wp_timezone_string(),
			) );
			$request = TPD_LMS_Store::find( 'proctor_requests', $rid );
			TPD_LMS_Store::update( 'proctor_requests', $rid, array(
				'status'              => 'approved',
				'decision_note'       => sanitize_textarea_field( isset( $p['notes'] ) ? (string) $p['notes'] : '' ) ?: null,
				'decided_by'          => get_current_user_id(),
				'decided_at'          => TPD_LMS_Native::now(),
				'exam_access_code_id' => (int) $code['id'],
			) );
			$res     = TPD_LMS_Exam::resend( TPD_LMS_Store::find( 'proctor_requests', $rid ) );
			$emailed = ! is_wp_error( $res ) && $res['emails_sent'];
		}
		return array( 'code' => $code['code'], 'emailed' => $emailed ) + self::action_student_detail( array( 'enrollment_id' => $e->id ) );
	}

	private static function action_code_revoke( $p ) {
		$code = TPD_LMS_Store::find( 'exam_access_codes', isset( $p['code_id'] ) ? $p['code_id'] : 0 );
		if ( ! $code ) {
			return new WP_Error( 'code_missing', __( 'That code no longer exists.', 'tpd-tool' ) );
		}
		$e = self::enrollment_or_error( $code->enrollment_id );
		if ( is_wp_error( $e ) ) {
			return $e;
		}
		if ( 'active' === TPD_LMS_Exam::code_status( $code ) ) {
			TPD_LMS_Exam::revoke_code( $code->id );
		}
		return self::action_student_detail( array( 'enrollment_id' => $e->id ) );
	}

	private static function action_quiz_report( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		return is_wp_error( $course ) ? $course : array( 'course' => self::course_summary( $course ), 'quizzes' => TPD_LMS_Reports::quizzes( $course->id ) );
	}

	private static function action_proctor_list( $p ) {
		global $wpdb;
		$status = isset( $p['status'] ) && in_array( $p['status'], array( 'pending', 'approved', 'declined', 'cancelled' ), true ) ? $p['status'] : 'pending';
		$rows   = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'proctor_requests' ) . ' WHERE status = %s ORDER BY created_at DESC LIMIT 200',
			$status
		) );
		$counts = array();
		foreach ( $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . TPD_LMS_Schema::t( 'proctor_requests' ) . ' GROUP BY status' ) as $r ) {
			$counts[ $r->status ] = (int) $r->n;
		}
		$out = array();
		foreach ( $rows as $r ) {
			$c     = TPD_LMS_Exam::context( $r );
			$out[] = array(
				'id'           => (int) $r->id,
				'student'      => $c['student'] ? $c['student']->display_name . ' <' . $c['student']->user_email . '>' : '?',
				'exam'         => ( $c['lesson'] ? $c['lesson']->title : '?' ) . ' · ' . ( $c['course'] ? $c['course']->title : '?' ),
				'proctor'      => $r->proctor_name . ' <' . $r->proctor_email . '>',
				'phone'        => (string) $r->proctor_phone,
				'relationship' => (string) $r->proctor_relationship,
				'preferred'    => TPD_LMS_Exam::format_time( $r->preferred_at, $r->timezone ),
				'notes'        => (string) $r->student_notes,
				'decision'     => (string) $r->decision_note,
				'code_status'  => $c['code'] ? TPD_LMS_Exam::code_status( $c['code'] ) : null,
				'confirmed'    => (bool) $r->proctor_confirmed_at,
				'flagged'      => (bool) $r->proctor_flagged,
				'report'       => (string) $r->proctor_report,
				'created_at'   => $r->created_at,
			);
		}
		return array( 'status' => $status, 'counts' => $counts, 'requests' => $out, 'valid_for' => TPD_LMS_Exam::VALID_FOR );
	}

	private static function request_or_error( $id ) {
		$r = TPD_LMS_Store::find( 'proctor_requests', $id );
		if ( ! $r ) {
			return new WP_Error( 'request_missing', __( 'That request no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$e = self::enrollment_or_error( $r->enrollment_id );
		return is_wp_error( $e ) ? $e : $r;
	}

	private static function action_proctor_approve( $p ) {
		$r = self::request_or_error( isset( $p['request_id'] ) ? $p['request_id'] : 0 );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$res = TPD_LMS_Exam::approve( $r, isset( $p['valid_hours'] ) ? (int) $p['valid_hours'] : 72, isset( $p['note'] ) ? (string) $p['note'] : '' );
		return is_wp_error( $res ) ? $res : $res + self::action_proctor_list( array( 'status' => 'pending' ) );
	}

	private static function action_proctor_decline( $p ) {
		$r = self::request_or_error( isset( $p['request_id'] ) ? $p['request_id'] : 0 );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$res = TPD_LMS_Exam::decline( $r, isset( $p['reason'] ) ? (string) $p['reason'] : '' );
		return is_wp_error( $res ) ? $res : $res + self::action_proctor_list( array( 'status' => 'pending' ) );
	}

	private static function action_proctor_resend( $p ) {
		$r = self::request_or_error( isset( $p['request_id'] ) ? $p['request_id'] : 0 );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$res = TPD_LMS_Exam::resend( $r );
		return is_wp_error( $res ) ? $res : $res + self::action_proctor_list( array( 'status' => 'approved' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Coupons                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Upload one file to the media library (used for narration recorded in the
	 * browser). Audio only, size-limited, through WordPress's own upload checks.
	 */
	private static function action_media_upload() {
		if ( empty( $_FILES['file'] ) || ! current_user_can( 'upload_files' ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatch().
			return new WP_Error( 'no_file', __( 'No recording was received. Try again.', 'tpd-tool' ) );
		}
		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( (int) $file['size'] > 50 * MB_IN_BYTES ) {
			return new WP_Error( 'too_big', __( 'That recording is larger than 50 MB. Record shorter parts.', 'tpd-tool' ) );
		}
		if ( 0 !== strpos( (string) $file['type'], 'audio/' ) && 0 !== strpos( (string) $file['type'], 'video/webm' ) ) {
			return new WP_Error( 'bad_type', __( 'Only audio recordings can be uploaded here.', 'tpd-tool' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( 'file', 0, array( 'post_title' => sanitize_text_field( isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : __( 'Slide narration', 'tpd-tool' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return array( 'id' => (int) $id, 'name' => basename( (string) get_attached_file( $id ) ), 'url' => (string) wp_get_attachment_url( $id ) );
	}

	/** Assignment submissions for a course (review queue). */
	private static function action_assignments( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$status = isset( $p['status'] ) && in_array( $p['status'], TPD_LMS_Assign::STATUSES, true ) ? $p['status'] : 'pending';
		$out    = array();
		foreach ( TPD_LMS_Assign::for_course( $course->id, $status ) as $s ) {
			$user   = get_userdata( (int) $s->user_id );
			$lesson = TPD_LMS_Store::find( 'lessons', $s->lesson_id );
			$out[]  = array(
				'id'          => (int) $s->id,
				'learner'     => $user ? $user->display_name . ' <' . $user->user_email . '>' : '?',
				'lesson'      => $lesson ? $lesson->title : '?',
				'text'        => TPD_LMS_Store::clean_html( (string) $s->answer_text ),
				'file_name'   => (string) $s->file_name,
				'file_size'   => $s->file_size ? size_format( (int) $s->file_size ) : '',
				'file_url'    => $s->file_path ? TPD_LMS_Assign::file_url( $s ) : null,
				'feedback'    => (string) $s->feedback,
				'status'      => $s->status,
				'created_at'  => $s->created_at,
				'reviewed_at' => $s->reviewed_at,
			);
		}
		$counts = array();
		foreach ( TPD_LMS_Assign::STATUSES as $st ) {
			$counts[ $st ] = count( TPD_LMS_Assign::for_course( $course->id, $st ) );
		}
		return array( 'course' => self::course_summary( $course ), 'status' => $status, 'counts' => $counts, 'submissions' => $out );
	}

	private static function action_assignment_review( $p ) {
		$sub    = TPD_LMS_Store::find( 'assignment_submissions', isset( $p['submission_id'] ) ? $p['submission_id'] : 0 );
		$lesson = $sub ? TPD_LMS_Store::find( 'lessons', $sub->lesson_id ) : null;
		if ( ! $sub || ! $lesson ) {
			return new WP_Error( 'submission_missing', __( 'That submission no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$course = self::course_or_error( $lesson->course_id );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$res = TPD_LMS_Assign::review( $sub, isset( $p['decision'] ) ? (string) $p['decision'] : '', isset( $p['feedback'] ) ? (string) $p['feedback'] : '' );
		return is_wp_error( $res ) ? $res : self::action_assignments( array( 'course_id' => $course->id, 'status' => 'pending' ) );
	}

	private static function action_coupon_list() {
		$courses = array();
		foreach ( TPD_LMS_Store::list_courses( 'all' ) as $c ) {
			$courses[ (int) $c->id ] = $c->title;
		}
		$out = array();
		foreach ( TPD_LMS_Coupons::all() as $cp ) {
			$out[] = array(
				'id'         => (int) $cp->id,
				'code'       => $cp->code,
				'label'      => TPD_LMS_Coupons::label( $cp ),
				'type'       => $cp->type,
				'value'      => (float) $cp->value,
				'course'     => $cp->course_id ? ( isset( $courses[ (int) $cp->course_id ] ) ? $courses[ (int) $cp->course_id ] : __( '(deleted course)', 'tpd-tool' ) ) : __( 'All courses', 'tpd-tool' ),
				'uses'       => (int) $cp->uses,
				'max_uses'   => null === $cp->max_uses ? null : (int) $cp->max_uses,
				'expires_at' => $cp->expires_at,
				'active'     => (bool) $cp->active,
			);
		}
		$choices = array();
		foreach ( $courses as $id => $title ) {
			$choices[] = array( 'id' => $id, 'title' => $title );
		}
		return array( 'coupons' => $out, 'courses' => $choices );
	}

	private static function action_coupon_save( $p ) {
		$id = TPD_LMS_Coupons::create( $p );
		return is_wp_error( $id ) ? $id : self::action_coupon_list();
	}

	private static function action_coupon_toggle( $p ) {
		$cp = TPD_LMS_Store::find( 'coupons', isset( $p['coupon_id'] ) ? $p['coupon_id'] : 0 );
		if ( ! $cp ) {
			return new WP_Error( 'coupon_missing', __( 'That coupon no longer exists.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::update( 'coupons', $cp->id, array( 'active' => (int) $cp->active ? 0 : 1 ) );
		return self::action_coupon_list();
	}

	private static function action_coupon_delete( $p ) {
		$cp = TPD_LMS_Store::find( 'coupons', isset( $p['coupon_id'] ) ? $p['coupon_id'] : 0 );
		if ( ! $cp ) {
			return new WP_Error( 'coupon_missing', __( 'That coupon no longer exists.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::delete( 'coupons', $cp->id );
		return self::action_coupon_list();
	}

	private static function action_settings_save( $p ) {
		$name = sanitize_text_field( isset( $p['issuer_name'] ) ? (string) $p['issuer_name'] : '' );
		if ( '' === $name ) {
			return new WP_Error( 'issuer_required', __( 'Enter the name to print on certificates.', 'tpd-tool' ) );
		}
		$support = sanitize_email( isset( $p['support_email'] ) ? (string) $p['support_email'] : '' );
		if ( '' !== $support && ! is_email( $support ) ) {
			return new WP_Error( 'bad_email', __( 'Check the support email address.', 'tpd-tool' ) );
		}
		$settings                = (array) get_option( TPD_LMS_Native::OPT_SETTINGS, array() );
		$settings['issuer_name'] = substr( $name, 0, 120 );
		if ( '' !== $support ) {
			$settings['support_email'] = $support;
		}
		update_option( TPD_LMS_Native::OPT_SETTINGS, $settings, false );
		return self::action_course_list();
	}

	private static function action_course_get( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		return is_wp_error( $course ) ? $course : self::course_tree( $course );
	}

	private static function action_course_save( $p ) {
		$fields = array();
		foreach ( array( 'title', 'subtitle', 'description', 'thumbnail_url', 'level', 'price', 'status', 'access_days', 'tier_included', 'slug' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$fields[ $key ] = $p[ $key ];
			}
		}
		foreach ( array( 'sequential', 'drip_enabled', 'certificate_enabled', 'exam_card' ) as $flag ) {
			if ( array_key_exists( $flag, $p ) ) {
				$fields[ $flag ] = '1' === (string) $p[ $flag ] ? 1 : 0;
			}
		}
		if ( isset( $fields['tier_included'] ) && ! array_key_exists( $fields['tier_included'], self::tier_choices() ) ) {
			$fields['tier_included'] = 'none';
		}

		$course_id = isset( $p['course_id'] ) ? absint( $p['course_id'] ) : 0;
		if ( $course_id ) {
			$course = self::course_or_error( $course_id );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			if ( isset( $fields['status'] ) && 'published' === $fields['status'] && 0 === count( TPD_LMS_Store::lessons_for_course( $course_id ) ) ) {
				return new WP_Error( 'empty_course', __( 'Add at least one lesson before publishing.', 'tpd-tool' ) );
			}
			$ok = TPD_LMS_Store::update_course( $course_id, $fields );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
		} else {
			$fields['status'] = 'draft'; // New courses start as drafts.
			$course_id        = TPD_LMS_Store::create_course( $fields );
			if ( is_wp_error( $course_id ) ) {
				return $course_id;
			}
		}
		return self::course_tree( TPD_LMS_Store::get_course( $course_id ) );
	}

	private static function action_course_delete( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		if ( self::enrollment_count( $course->id ) > 0 ) {
			return new WP_Error( 'has_students', __( 'Students are enrolled in this course, so it can’t be deleted. Unpublish it instead.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::delete_course( $course->id );
		return array( 'deleted' => (int) $course->id );
	}

	/* ------------------------------------------------------------------ */
	/* Modules and order                                                   */
	/* ------------------------------------------------------------------ */

	private static function action_chapter_save( $p ) {
		$title = isset( $p['title'] ) ? $p['title'] : '';
		if ( ! empty( $p['chapter_id'] ) ) {
			$chapter = TPD_LMS_Store::find( 'chapters', $p['chapter_id'] );
			if ( ! $chapter ) {
				return new WP_Error( 'chapter_missing', __( 'That module no longer exists. Reload the page.', 'tpd-tool' ) );
			}
			$course = self::course_or_error( $chapter->course_id );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			$ok = TPD_LMS_Store::rename_chapter( $chapter->id, $title );
		} else {
			$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			$ok = TPD_LMS_Store::create_chapter( $course->id, $title );
		}
		return is_wp_error( $ok ) ? $ok : self::course_tree( $course );
	}

	private static function action_chapter_delete( $p ) {
		$chapter = TPD_LMS_Store::find( 'chapters', isset( $p['chapter_id'] ) ? $p['chapter_id'] : 0 );
		if ( ! $chapter ) {
			return new WP_Error( 'chapter_missing', __( 'That module no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$course = self::course_or_error( $chapter->course_id );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		TPD_LMS_Store::delete_chapter( $chapter->id );
		foreach ( self::enrollment_ids( $course->id ) as $eid ) {
			TPD_LMS_Progress::recalculate( $eid );
		}
		return self::course_tree( $course );
	}

	private static function action_reorder( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$structure = json_decode( isset( $p['structure'] ) ? (string) $p['structure'] : '', true );
		if ( ! is_array( $structure ) ) {
			return new WP_Error( 'bad_order', __( 'The new order could not be read. Reload the page and try again.', 'tpd-tool' ) );
		}
		$ok = TPD_LMS_Store::reorder( $course->id, $structure );
		return is_wp_error( $ok ) ? $ok : self::course_tree( $course );
	}

	/* ------------------------------------------------------------------ */
	/* Lessons                                                             */
	/* ------------------------------------------------------------------ */

	private static function action_lesson_get( $p ) {
		$lesson = self::lesson_or_error( isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		return is_wp_error( $lesson ) ? $lesson : self::lesson_detail( $lesson );
	}

	private static function action_lesson_save( $p ) {
		$fields = array();
		foreach ( array( 'title', 'type', 'content', 'video_url', 'attachment_id', 'duration_minutes', 'drip_days' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$fields[ $key ] = $p[ $key ];
			}
		}
		if ( array_key_exists( 'is_preview', $p ) ) {
			$fields['is_preview'] = '1' === (string) $p['is_preview'] ? 1 : 0;
		}
		if ( isset( $p['settings'] ) ) {
			$settings = json_decode( (string) $p['settings'], true );
			if ( is_array( $settings ) ) {
				$fields['settings'] = $settings; // Cleaned per lesson type by the store.
			}
		}
		if ( ! empty( $fields['attachment_id'] ) && ! get_post( absint( $fields['attachment_id'] ) ) ) {
			return new WP_Error( 'file_missing', __( 'That file is no longer in the media library. Choose it again.', 'tpd-tool' ) );
		}

		if ( ! empty( $p['lesson_id'] ) ) {
			$lesson = self::lesson_or_error( $p['lesson_id'] );
			if ( is_wp_error( $lesson ) ) {
				return $lesson;
			}
			$ok = TPD_LMS_Store::update_lesson( $lesson->id, $fields );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
			$lesson_id = (int) $lesson->id;
		} else {
			$chapter = TPD_LMS_Store::find( 'chapters', isset( $p['chapter_id'] ) ? $p['chapter_id'] : 0 );
			if ( ! $chapter ) {
				return new WP_Error( 'chapter_missing', __( 'That module no longer exists. Reload the page.', 'tpd-tool' ) );
			}
			$course = self::course_or_error( $chapter->course_id );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			$lesson_id = TPD_LMS_Store::create_lesson( $chapter->id, $fields );
			if ( is_wp_error( $lesson_id ) ) {
				return $lesson_id;
			}
			foreach ( self::enrollment_ids( $course->id ) as $eid ) {
				TPD_LMS_Progress::recalculate( $eid ); // A new lesson lowers everyone's progress.
			}
		}
		return self::lesson_detail( TPD_LMS_Store::find( 'lessons', $lesson_id ) );
	}

	private static function action_lesson_delete( $p ) {
		$lesson = self::lesson_or_error( isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		if ( is_wp_error( $lesson ) ) {
			return $lesson;
		}
		$course = TPD_LMS_Store::get_course( $lesson->course_id );
		TPD_LMS_Store::delete_lesson( $lesson->id );
		foreach ( self::enrollment_ids( $course->id ) as $eid ) {
			TPD_LMS_Progress::recalculate( $eid );
		}
		return self::course_tree( $course );
	}

	/* ------------------------------------------------------------------ */
	/* Quizzes and questions                                               */
	/* ------------------------------------------------------------------ */

	private static function action_quiz_save( $p ) {
		$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}
		$fields = array();
		foreach ( array( 'passing_score', 'max_attempts', 'time_limit_minutes', 'questions_per_attempt' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$fields[ $key ] = $p[ $key ];
			}
		}
		foreach ( array( 'shuffle_questions', 'shuffle_options', 'show_answers', 'require_all_lessons' ) as $flag ) {
			if ( array_key_exists( $flag, $p ) ) {
				$fields[ $flag ] = '1' === (string) $p[ $flag ] ? 1 : 0;
			}
		}
		if ( isset( $fields['passing_score'] ) && ( absint( $fields['passing_score'] ) < 1 || absint( $fields['passing_score'] ) > 100 ) ) {
			return new WP_Error( 'bad_pass_mark', __( 'The pass mark must be between 1 and 100%.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::update_quiz( $quiz->id, $fields );
		return self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	private static function action_question_save( $p ) {
		$options = json_decode( isset( $p['options'] ) ? (string) $p['options'] : '[]', true );
		$options = is_array( $options ) ? $options : array();
		$data    = array();
		foreach ( array( 'type', 'prompt', 'explanation', 'accepted_answers', 'points', 'topic' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$data[ $key ] = $p[ $key ];
			}
		}

		if ( ! empty( $p['question_id'] ) ) {
			$question = TPD_LMS_Store::find( 'questions', $p['question_id'] );
			if ( ! $question ) {
				return new WP_Error( 'question_missing', __( 'That question no longer exists. Reload the page.', 'tpd-tool' ) );
			}
			$quiz = self::quiz_or_error( $question->quiz_id );
			if ( is_wp_error( $quiz ) ) {
				return $quiz;
			}
			$ok = TPD_LMS_Store::update_question( $question->id, $data, $options );
		} else {
			$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
			if ( is_wp_error( $quiz ) ) {
				return $quiz;
			}
			$ok = TPD_LMS_Store::create_question( $quiz->id, $data, $options );
		}
		return is_wp_error( $ok ) ? $ok : self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	private static function action_question_delete( $p ) {
		$question = TPD_LMS_Store::find( 'questions', isset( $p['question_id'] ) ? $p['question_id'] : 0 );
		if ( ! $question ) {
			return new WP_Error( 'question_missing', __( 'That question no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$quiz = self::quiz_or_error( $question->quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}
		TPD_LMS_Store::delete_question( $question->id );
		return self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	/**
	 * Preview (default) or import questions from an uploaded file or pasted text.
	 * Pass commit=1 to save; the same input is parsed again, so nothing is kept between calls.
	 */
	private static function action_question_import( $p ) {
		$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$file = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatch().
		if ( $file && ! empty( $file['tmp_name'] ) ) {
			if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'upload_failed', __( 'The file didn’t upload. Try again.', 'tpd-tool' ) );
			}
			if ( (int) $file['size'] > self::IMPORT_MAX_BYTES ) {
				return new WP_Error( 'too_big', __( 'That file is larger than 5 MB. Split it into smaller files.', 'tpd-tool' ) );
			}
			$items = TPD_LMS_Importer::parse_file( $file['tmp_name'], sanitize_file_name( wp_unslash( $file['name'] ) ) );
		} elseif ( isset( $p['text'] ) && '' !== trim( (string) $p['text'] ) ) {
			try {
				$items = TPD_LMS_Importer::parse_text( (string) $p['text'] );
			} catch ( RuntimeException $e ) {
				$items = new WP_Error( 'unreadable', $e->getMessage() );
			}
		} else {
			return new WP_Error( 'no_input', __( 'Choose a file or paste your questions first.', 'tpd-tool' ) );
		}
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		if ( ! $items ) {
			return new WP_Error( 'no_questions', __( 'No questions were found. Check the format against the template.', 'tpd-tool' ) );
		}

		$items = TPD_LMS_Importer::apply_default_topic( $items, isset( $p['default_topic'] ) ? $p['default_topic'] : '' );

		if ( empty( $p['commit'] ) ) {
			$items = TPD_LMS_Importer::mark_duplicates( $quiz->id, $items );
			return array( 'preview' => $items );
		}

		list( $added, $skipped ) = TPD_LMS_Importer::import( $quiz->id, $items );
		$out           = self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
		$out['import'] = array( 'added' => $added, 'skipped' => $skipped );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Response shapes                                                     */
	/* ------------------------------------------------------------------ */

	private static function enrollment_ids( $course_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE course_id = %d', absint( $course_id ) ) ) );
	}

	private static function enrollment_count( $course_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE course_id = %d', absint( $course_id ) ) );
	}

	private static function course_summary( $course ) {
		$instructor = get_userdata( (int) $course->instructor_id );
		return array(
			'id'            => (int) $course->id,
			'title'         => $course->title,
			'slug'          => $course->slug,
			'subtitle'      => (string) $course->subtitle,
			'status'        => $course->status,
			'price'         => (float) $course->price,
			'thumbnail_url' => (string) $course->thumbnail_url,
			'lesson_count'  => count( TPD_LMS_Store::lessons_for_course( $course->id ) ),
			'student_count' => self::enrollment_count( $course->id ),
			'instructor'    => $instructor ? $instructor->display_name : '',
			'updated_at'    => $course->updated_at,
		);
	}

	private static function course_tree( $course ) {
		$lessons_by_chapter = array();
		foreach ( TPD_LMS_Store::lessons_for_course( $course->id ) as $l ) {
			$quiz                                         = in_array( $l->type, TPD_LMS_Store::QUIZ_TYPES, true ) ? TPD_LMS_Store::quiz_for_lesson( $l->id ) : null;
			$lessons_by_chapter[ (int) $l->chapter_id ][] = array(
				'id'             => (int) $l->id,
				'title'          => $l->title,
				'type'           => $l->type,
				'is_preview'     => (bool) $l->is_preview,
				'drip_days'      => null === $l->drip_days ? null : (int) $l->drip_days,
				'question_count' => $quiz ? TPD_LMS_Quiz::question_count( $quiz ) : null,
			);
		}
		$chapters = array();
		foreach ( TPD_LMS_Store::chapters_for_course( $course->id ) as $ch ) {
			$chapters[] = array(
				'id'      => (int) $ch->id,
				'title'   => $ch->title,
				'lessons' => isset( $lessons_by_chapter[ (int) $ch->id ] ) ? $lessons_by_chapter[ (int) $ch->id ] : array(),
			);
		}
		return array(
			'course'   => array_merge( self::course_summary( $course ), array(
				'description'         => (string) $course->description,
				'level'               => $course->level,
				'sequential'          => (bool) $course->sequential,
				'drip_enabled'        => (bool) $course->drip_enabled,
				'certificate_enabled' => (bool) $course->certificate_enabled,
				'access_days'         => null === $course->access_days ? null : (int) $course->access_days,
				'tier_included'       => $course->tier_included,
				'has_final_exam'      => (bool) TPD_LMS_Store::final_exam_for_course( $course->id ),
				'exam_card'           => (bool) $course->exam_card,
				'pending_assignments' => TPD_LMS_Assign::pending_count( $course->id ),
			) ),
			'chapters' => $chapters,
		);
	}

	/** Lesson settings plus the names/URLs the editor shows (files, slide audio, local start time). */
	private static function settings_for_editor( $lesson ) {
		$s    = TPD_LMS_Store::lesson_settings( $lesson );
		$file = function ( $id ) {
			return array(
				'id'   => (int) $id,
				'name' => basename( (string) get_attached_file( (int) $id ) ),
				'url'  => (string) wp_get_attachment_url( (int) $id ),
			);
		};
		if ( 'download' === $lesson->type ) {
			$ids = $s['files'];
			if ( $lesson->attachment_id && ! in_array( (int) $lesson->attachment_id, $ids, true ) ) {
				array_unshift( $ids, (int) $lesson->attachment_id );
			}
			$s['files'] = array_map( $file, $ids );
		}
		if ( 'presentation' === $lesson->type ) {
			foreach ( $s['slides'] as $i => $slide ) {
				$s['slides'][ $i ]['audio'] = $slide['audio_id'] ? $file( $slide['audio_id'] ) : null;
			}
		}
		if ( 'live' === $lesson->type ) {
			$s['starts_local'] = '';
			if ( $s['starts_at'] ) {
				$dt = new DateTime( $s['starts_at'], new DateTimeZone( 'UTC' ) );
				$dt->setTimezone( new DateTimeZone( $s['timezone'] ) );
				$s['starts_local'] = $dt->format( 'Y-m-d\TH:i' );
			}
		}
		return $s;
	}

	private static function lesson_detail( $lesson ) {
		$out = array(
			'lesson' => array(
				'id'               => (int) $lesson->id,
				'course_id'        => (int) $lesson->course_id,
				'chapter_id'       => (int) $lesson->chapter_id,
				'title'            => $lesson->title,
				'type'             => $lesson->type,
				'content'          => (string) $lesson->content,
				'video_url'        => (string) $lesson->video_url,
				'attachment_id'    => $lesson->attachment_id ? (int) $lesson->attachment_id : null,
				'attachment_name'  => $lesson->attachment_id ? basename( (string) get_attached_file( (int) $lesson->attachment_id ) ) : '',
				'duration_minutes' => null === $lesson->duration_minutes ? null : (int) $lesson->duration_minutes,
				'is_preview'       => (bool) $lesson->is_preview,
				'drip_days'        => null === $lesson->drip_days ? null : (int) $lesson->drip_days,
				'attachment_url'   => $lesson->attachment_id ? (string) wp_get_attachment_url( (int) $lesson->attachment_id ) : '',
				'settings'         => self::settings_for_editor( $lesson ),
			),
			'quiz'      => null,
			'questions' => array(),
		);
		if ( 'assignment' === $lesson->type ) {
			$out['pending_submissions'] = count( array_filter( TPD_LMS_Assign::for_course( $lesson->course_id, 'pending' ), function ( $s ) use ( $lesson ) {
				return (int) $s->lesson_id === (int) $lesson->id;
			} ) );
		}
		$quiz = TPD_LMS_Store::quiz_for_lesson( $lesson->id );
		if ( $quiz ) {
			$out['quiz'] = array(
				'id'                    => (int) $quiz->id,
				'is_final'              => (bool) $quiz->is_final,
				'passing_score'         => (int) $quiz->passing_score,
				'max_attempts'          => null === $quiz->max_attempts ? null : (int) $quiz->max_attempts,
				'time_limit_minutes'    => null === $quiz->time_limit_minutes ? null : (int) $quiz->time_limit_minutes,
				'questions_per_attempt' => null === $quiz->questions_per_attempt ? null : (int) $quiz->questions_per_attempt,
				'shuffle_questions'     => (bool) $quiz->shuffle_questions,
				'shuffle_options'       => (bool) $quiz->shuffle_options,
				'show_answers'          => (bool) $quiz->show_answers,
				'require_all_lessons'   => (bool) $quiz->require_all_lessons,
				'access_mode'           => $quiz->access_mode,
				'access_password'       => 'password' === $quiz->access_mode ? (string) TPD_LMS_Exam::password_for( $quiz ) : '',
			);
			foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id ) as $q ) {
				$options = array();
				foreach ( $q->options as $o ) {
					$options[] = array( 'id' => (int) $o->id, 'text' => $o->text, 'is_correct' => (bool) $o->is_correct );
				}
				$out['questions'][] = array(
					'id'               => (int) $q->id,
					'type'             => $q->type,
					'topic'            => (string) $q->topic,
					'prompt'           => $q->prompt,
					'explanation'      => (string) $q->explanation,
					'accepted_answers' => (string) $q->accepted_answers,
					'points'           => (int) $q->points,
					'options'          => $options,
				);
			}
		}
		return $out;
	}
}
