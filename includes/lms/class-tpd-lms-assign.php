<?php
/**
 * Native LMS assignments: learners hand in a file and/or a written answer;
 * staff approve or return it with feedback.
 *
 * - Files go to a private folder (uploads/tpd-lms-private/) that the web
 *   server refuses to serve directly. They are streamed only to the learner
 *   who sent them and to course staff, after checking.
 * - With "require approval" on, the lesson completes when staff approve it.
 *   Otherwise it completes on submission, and staff can still leave feedback.
 * - A returned assignment can be sent again.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Assign {

	const STATUSES = array( 'pending', 'approved', 'returned' );

	public static function init() {
		add_action( 'wp_ajax_tpd_lms_submission_file', array( __CLASS__, 'serve_file' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Private storage                                                     */
	/* ------------------------------------------------------------------ */

	public static function private_dir() {
		$uploads = wp_upload_dir( null, false );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'tpd-lms-private';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		// Deny direct web access (Apache/LiteSpeed) and directory listing.
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return $dir;
	}

	/**
	 * Store an uploaded file privately.
	 *
	 * @param array $file   One entry of $_FILES.
	 * @param int   $max_mb Size limit.
	 * @return array|WP_Error array( path, name, size )
	 */
	public static function store_upload( $file, $max_mb ) {
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'upload_failed', __( 'The file didn’t upload. Try again.', 'tpd-tool' ) );
		}
		if ( (int) $file['size'] > $max_mb * MB_IN_BYTES ) {
			/* translators: %d: size limit in MB */
			return new WP_Error( 'too_big', sprintf( __( 'That file is larger than %d MB.', 'tpd-tool' ), $max_mb ) );
		}
		$name  = sanitize_file_name( wp_unslash( (string) $file['name'] ) );
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $name );
		$ext   = strtolower( (string) ( $check['ext'] ? $check['ext'] : pathinfo( $name, PATHINFO_EXTENSION ) ) );
		if ( ! in_array( $ext, TPD_LMS_Store::ASSIGNMENT_EXTENSIONS, true ) || ( empty( $check['type'] ) && ! in_array( $ext, array( 'txt', 'rtf' ), true ) ) ) {
			return new WP_Error( 'bad_type', __( 'Upload a PDF, Word, PowerPoint, Excel, text or image file.', 'tpd-tool' ) );
		}
		$stored = bin2hex( random_bytes( 16 ) ) . '.' . $ext;
		$dest   = self::private_dir() . '/' . $stored;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new WP_Error( 'upload_failed', __( 'The file couldn’t be saved. Try again.', 'tpd-tool' ) );
		}
		@chmod( $dest, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return array( 'path' => $stored, 'name' => $name, 'size' => (int) $file['size'] );
	}

	/* ------------------------------------------------------------------ */
	/* Submissions                                                         */
	/* ------------------------------------------------------------------ */

	public static function latest( $lesson_id, $enrollment_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'assignment_submissions' ) . ' WHERE lesson_id = %d AND enrollment_id = %d ORDER BY id DESC LIMIT 1',
			absint( $lesson_id ),
			absint( $enrollment_id )
		) );
	}

	/**
	 * A learner hands in an assignment.
	 *
	 * @return object|WP_Error Submission row.
	 */
	public static function submit( $lesson, $enrollment, $text, $file ) {
		$settings = TPD_LMS_Store::lesson_settings( $lesson );
		$latest   = self::latest( $lesson->id, $enrollment->id );
		if ( $latest && in_array( $latest->status, array( 'pending', 'approved' ), true ) ) {
			return new WP_Error( 'already_submitted', 'approved' === $latest->status ? __( 'This assignment is already approved.', 'tpd-tool' ) : __( 'Your assignment is waiting for review.', 'tpd-tool' ) );
		}
		$text     = TPD_LMS_Store::clean_html( (string) $text );
		$has_text = '' !== trim( wp_strip_all_tags( $text ) );
		$has_file = $file && ! empty( $file['tmp_name'] );
		if ( 'file' === $settings['submission'] && ! $has_file ) {
			return new WP_Error( 'file_required', __( 'Attach your file before sending.', 'tpd-tool' ) );
		}
		if ( 'text' === $settings['submission'] && ! $has_text ) {
			return new WP_Error( 'text_required', __( 'Write your answer before sending.', 'tpd-tool' ) );
		}
		if ( ! $has_text && ! $has_file ) {
			return new WP_Error( 'empty', __( 'Attach a file or write your answer before sending.', 'tpd-tool' ) );
		}
		$stored = null;
		if ( $has_file && 'text' !== $settings['submission'] ) {
			$stored = self::store_upload( $file, $settings['max_mb'] );
			if ( is_wp_error( $stored ) ) {
				return $stored;
			}
		}
		$auto = ! $settings['require_approval'];
		$id   = TPD_LMS_Store::insert( 'assignment_submissions', array(
			'lesson_id'     => (int) $lesson->id,
			'enrollment_id' => (int) $enrollment->id,
			'user_id'       => (int) $enrollment->user_id,
			'answer_text'   => $has_text && 'file' !== $settings['submission'] ? $text : null,
			'file_path'     => $stored ? $stored['path'] : null,
			'file_name'     => $stored ? $stored['name'] : null,
			'file_size'     => $stored ? $stored['size'] : null,
			'status'        => $auto ? 'approved' : 'pending',
		) );
		if ( ! $id ) {
			return new WP_Error( 'db_error', __( 'Your assignment couldn’t be saved. Try again.', 'tpd-tool' ) );
		}
		if ( $auto ) {
			TPD_LMS_Progress::complete( $enrollment, $lesson );
		}
		self::email_new_submission( TPD_LMS_Store::find( 'assignment_submissions', $id ), $lesson );
		return TPD_LMS_Store::find( 'assignment_submissions', $id );
	}

	/**
	 * Staff decision: approve (completes the lesson) or return with feedback.
	 */
	public static function review( $submission, $decision, $feedback ) {
		$decision = 'approve' === $decision ? 'approved' : 'returned';
		$feedback = sanitize_textarea_field( (string) $feedback );
		if ( 'returned' === $decision && '' === trim( $feedback ) ) {
			return new WP_Error( 'feedback_required', __( 'Tell the learner what to change before returning it.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::update( 'assignment_submissions', $submission->id, array(
			'status'      => $decision,
			'feedback'    => '' !== $feedback ? $feedback : null,
			'reviewed_by' => get_current_user_id(),
			'reviewed_at' => TPD_LMS_Native::now(),
		) );
		$enrollment = TPD_LMS_Store::find( 'enrollments', $submission->enrollment_id );
		$lesson     = TPD_LMS_Store::find( 'lessons', $submission->lesson_id );
		if ( 'approved' === $decision && $enrollment && $lesson ) {
			TPD_LMS_Progress::complete( $enrollment, $lesson );
		}
		$submission = TPD_LMS_Store::find( 'assignment_submissions', $submission->id );
		self::email_decision( $submission, $lesson );
		return $submission;
	}

	/** Submissions for a course, newest first, optionally by status. */
	public static function for_course( $course_id, $status = '' ) {
		global $wpdb;
		$s = TPD_LMS_Schema::t( 'assignment_submissions' );
		$l = TPD_LMS_Schema::t( 'lessons' );
		$sql = "SELECT s.* FROM {$s} s INNER JOIN {$l} l ON l.id = s.lesson_id WHERE l.course_id = %d";
		$args = array( absint( $course_id ) );
		if ( in_array( $status, self::STATUSES, true ) ) {
			$sql   .= ' AND s.status = %s';
			$args[] = $status;
		}
		return $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY s.created_at DESC, s.id DESC LIMIT 300', $args ) );
	}

	public static function pending_count( $course_id ) {
		return count( self::for_course( $course_id, 'pending' ) );
	}

	public static function file_url( $submission ) {
		return add_query_arg( array(
			'action'        => 'tpd_lms_submission_file',
			'submission_id' => (int) $submission->id,
			'nonce'         => wp_create_nonce( 'tpd_lms_submission_' . (int) $submission->id ),
		), admin_url( 'admin-ajax.php' ) );
	}

	/** Stream a submitted file to its learner or to course staff. */
	public static function serve_file() {
		$id = isset( $_GET['submission_id'] ) ? absint( $_GET['submission_id'] ) : 0;
		if ( ! wp_verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'tpd_lms_submission_' . $id ) ) {
			wp_die( esc_html__( 'This link has expired. Open the page again.', 'tpd-tool' ), '', array( 'response' => 403 ) );
		}
		$sub    = TPD_LMS_Store::find( 'assignment_submissions', $id );
		$lesson = $sub ? TPD_LMS_Store::find( 'lessons', $sub->lesson_id ) : null;
		$course = $lesson ? TPD_LMS_Store::get_course( (int) $lesson->course_id ) : null;
		$user   = get_current_user_id();
		if ( ! $sub || ! $sub->file_path || ! $course || ( (int) $sub->user_id !== $user && ! TPD_LMS_Store::can_manage_course( $course, $user ) ) ) {
			wp_die( esc_html__( 'You can’t open this file.', 'tpd-tool' ), '', array( 'response' => 403 ) );
		}
		$path = self::private_dir() . '/' . basename( $sub->file_path );
		if ( ! file_exists( $path ) ) {
			wp_die( esc_html__( 'The file is missing.', 'tpd-tool' ), '', array( 'response' => 404 ) );
		}
		$type = wp_check_filetype( $sub->file_name );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $sub->file_name ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Emails                                                              */
	/* ------------------------------------------------------------------ */

	private static function email_new_submission( $sub, $lesson ) {
		$course     = TPD_LMS_Store::get_course( (int) $lesson->course_id );
		$learner    = get_userdata( (int) $sub->user_id );
		$instructor = $course ? get_userdata( (int) $course->instructor_id ) : null;
		$to         = array( TPD_LMS_Mail::support_email() );
		if ( $instructor ) {
			$to[] = $instructor->user_email;
		}
		$body = implode( "\n", array(
			sprintf( '%s handed in "%s" (%s).', $learner ? $learner->display_name : 'A learner', $lesson->title, $course ? $course->title : '' ),
			'approved' === $sub->status ? 'It completed the lesson automatically; you can still leave feedback.' : 'It is waiting for your review.',
			'',
			'Review it in Super Admin → LMS Studio → the course → Assignments:',
			home_url( '/tpd-admin/#sa-lms-studio' ),
		) );
		TPD_LMS_Mail::send( $to, sprintf( 'Assignment handed in: %s', $lesson->title ), $body );
	}

	private static function email_decision( $sub, $lesson ) {
		$learner = get_userdata( (int) $sub->user_id );
		$course  = $lesson ? TPD_LMS_Store::get_course( (int) $lesson->course_id ) : null;
		if ( ! $learner || ! $lesson ) {
			return;
		}
		$approved = 'approved' === $sub->status;
		$body     = implode( "\n", array_filter( array(
			sprintf( 'Hi %s,', $learner->display_name ),
			'',
			$approved ? sprintf( 'Your assignment "%s" was approved.', $lesson->title ) : sprintf( 'Your assignment "%s" was returned so you can improve it.', $lesson->title ),
			$sub->feedback ? "\nFeedback:\n" . $sub->feedback : '',
			'',
			$approved ? 'Carry on with the course:' : 'Open the lesson to send it again:',
			add_query_arg( 'lms_course', $course ? $course->slug : '', home_url( '/advisor-dashboard/' ) ),
		), 'strlen' ) );
		TPD_LMS_Mail::send( $learner->user_email, $approved ? 'Your assignment was approved' : 'Your assignment was returned', $body );
	}
}
