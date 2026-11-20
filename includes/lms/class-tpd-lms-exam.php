<?php
/**
 * Native LMS exam access and proctoring.
 *
 * Rules (ported from the learning platform's briefing):
 * - Each quiz or exam is open, behind one shared password (stored encrypted,
 *   case-sensitive), or behind one-time codes.
 * - A code works once, for one student, until it expires, and is linked to the
 *   attempt it started. The check applies only when starting a new attempt, not
 *   when resuming one. Course staff skip it. Five wrong tries lock the student
 *   out for 15 minutes.
 * - For code-locked exams, students request a proctor. Staff approve (which
 *   creates the code and emails the proctor the code, instructions and a private
 *   link) or decline with a reason. Only a hash of the link token is stored.
 *   The student never sees the code. The proctor confirms the session or
 *   reports a problem on that page. New requests email support and the instructor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Exam {

	const MODES          = array( 'open', 'password', 'code' );
	const MAX_WRONG      = 5;
	const LOCKOUT        = 900; // 15 minutes.
	const VALID_FOR      = array( 24 => '24 hours', 72 => '3 days', 168 => '1 week', 336 => '2 weeks' );
	const PROCTOR_PATH   = 'proctor';
	const CODE_ALPHABET  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'proctor_template' ), 1000 );
	}

	/* ------------------------------------------------------------------ */
	/* Encryption for the exam password and codes                          */
	/* ------------------------------------------------------------------ */

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . 'tpd-lms-exam', true );
	}

	public static function encrypt( $plain ) {
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( (string) $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		return 'v1:' . base64_encode( $iv . $tag . $ct );
	}

	public static function decrypt( $stored ) {
		if ( 0 !== strpos( (string) $stored, 'v1:' ) ) {
			return null;
		}
		$raw = base64_decode( substr( $stored, 3 ), true );
		if ( false === $raw || strlen( $raw ) < 29 ) {
			return null;
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $plain ? null : $plain;
	}

	/* ------------------------------------------------------------------ */
	/* Settings (from the builder)                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * @return true|WP_Error
	 */
	public static function save_access( $quiz_id, $mode, $password = null ) {
		$mode = in_array( $mode, self::MODES, true ) ? $mode : 'open';
		$row  = array( 'access_mode' => $mode );
		if ( 'password' === $mode ) {
			$quiz = TPD_LMS_Store::find( 'quizzes', $quiz_id );
			if ( null !== $password && '' !== (string) $password ) {
				$row['access_password'] = self::encrypt( (string) $password );
			} elseif ( ! $quiz || ! self::decrypt( (string) $quiz->access_password ) ) {
				return new WP_Error( 'password_required', __( 'Set the exam password.', 'tpd-tool' ) );
			}
		}
		TPD_LMS_Store::update( 'quizzes', $quiz_id, $row );
		return true;
	}

	public static function password_for( $quiz ) {
		return $quiz && $quiz->access_password ? self::decrypt( $quiz->access_password ) : null;
	}

	/* ------------------------------------------------------------------ */
	/* Starting an attempt                                                 */
	/* ------------------------------------------------------------------ */

	public static function needs_check( $quiz ) {
		return $quiz && in_array( $quiz->access_mode, array( 'password', 'code' ), true );
	}

	private static function lock_key( $user_id, $quiz_id ) {
		return 'tpd_lms_exam_' . (int) $user_id . '_' . (int) $quiz_id;
	}

	/**
	 * Start (or resume) an attempt, checking the password or code for new attempts.
	 *
	 * @return object|WP_Error Attempt.
	 */
	public static function start( $quiz, $enrollment, $user_id, $given = '' ) {
		$open   = TPD_LMS_Quiz::open_attempt( $quiz, $enrollment );
		$resume = $open && ! TPD_LMS_Quiz::is_past_deadline( $open );
		$course = TPD_LMS_Store::get_course( (int) $enrollment->course_id );
		if ( $resume || ! self::needs_check( $quiz ) || TPD_LMS_Store::can_manage_course( $course, $user_id ) ) {
			return TPD_LMS_Quiz::start( $quiz, $enrollment );
		}
		if ( ! TPD_LMS_Quiz::can_attempt( $quiz, $enrollment ) ) {
			return new WP_Error( 'no_attempts_left', __( 'You have used all your attempts for this quiz.', 'tpd-tool' ) );
		}

		$key   = self::lock_key( $user_id, $quiz->id );
		$tries = get_transient( $key );
		$tries = is_array( $tries ) ? $tries : array( 'count' => 0, 'until' => 0 );
		if ( $tries['count'] >= self::MAX_WRONG && $tries['until'] > time() ) {
			$minutes = (int) ceil( ( $tries['until'] - time() ) / 60 );
			/* translators: %d: minutes to wait */
			return new WP_Error( 'locked_out', sprintf( _n( 'Too many wrong tries. Wait %d minute, then try again.', 'Too many wrong tries. Wait %d minutes, then try again.', $minutes, 'tpd-tool' ), $minutes ), array( 'needs_access' => true ) );
		}
		$wrong = function ( $message ) use ( $key, $tries ) {
			$tries['count']++;
			$tries['until'] = time() + self::LOCKOUT;
			set_transient( $key, $tries, self::LOCKOUT );
			return new WP_Error( 'bad_access', $message, array( 'needs_access' => true ) );
		};
		$given = trim( (string) $given );

		if ( 'password' === $quiz->access_mode ) {
			$password = self::password_for( $quiz );
			if ( '' === $given ) {
				return new WP_Error( 'need_access', __( 'Enter the exam password from your proctor.', 'tpd-tool' ), array( 'needs_access' => true ) );
			}
			if ( null === $password || ! hash_equals( $password, $given ) ) {
				return $wrong( __( 'That password isn’t right. Check it with your proctor.', 'tpd-tool' ) );
			}
			delete_transient( $key );
			return TPD_LMS_Quiz::start( $quiz, $enrollment );
		}

		// One-time code.
		if ( '' === $given ) {
			return new WP_Error( 'need_access', __( 'Enter the one-time code your proctor gives you.', 'tpd-tool' ), array( 'needs_access' => true ) );
		}
		global $wpdb;
		$hash = hash( 'sha256', self::normalize_code( $given ) );
		$t    = TPD_LMS_Schema::t( 'exam_access_codes' );
		$code = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE quiz_id = %d AND enrollment_id = %d AND code_hash = %s AND used_at IS NULL AND revoked_at IS NULL AND expires_at > %s LIMIT 1",
			(int) $quiz->id,
			(int) $enrollment->id,
			$hash,
			TPD_LMS_Native::now()
		) );
		if ( ! $code ) {
			return $wrong( __( 'That code isn’t valid. Codes work once, for one student, until they expire. Ask your proctor or TARC for a new one.', 'tpd-tool' ) );
		}
		// Claim the code first, so two tabs can't both use it.
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET used_at = %s WHERE id = %d AND used_at IS NULL", TPD_LMS_Native::now(), (int) $code->id ) );
		if ( ! $claimed ) {
			return $wrong( __( 'That code has already been used.', 'tpd-tool' ) );
		}
		delete_transient( $key );
		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		if ( ! is_wp_error( $attempt ) ) {
			$wpdb->update( $t, array( 'quiz_attempt_id' => (int) $attempt->id ), array( 'id' => (int) $code->id ) );
		}
		return $attempt;
	}

	/* ------------------------------------------------------------------ */
	/* One-time codes                                                      */
	/* ------------------------------------------------------------------ */

	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
	}

	public static function generate_code() {
		$chars = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$chars .= self::CODE_ALPHABET[ random_int( 0, strlen( self::CODE_ALPHABET ) - 1 ) ];
		}
		return substr( $chars, 0, 4 ) . '-' . substr( $chars, 4 );
	}

	/**
	 * Create a one-time code for one student on one exam.
	 *
	 * @return array|WP_Error array( 'id' => int, 'code' => string )
	 */
	public static function create_code( $quiz_id, $enrollment_id, $valid_hours, array $proctor = array(), $preferred_at = null ) {
		$valid_hours = isset( self::VALID_FOR[ (int) $valid_hours ] ) ? (int) $valid_hours : 72;
		$expires     = time() + HOUR_IN_SECONDS * $valid_hours;
		if ( $preferred_at ) {
			$expires = max( $expires, strtotime( $preferred_at . ' UTC' ) + DAY_IN_SECONDS ); // At least a day after the session.
		}
		$plain = self::generate_code();
		$id    = TPD_LMS_Store::insert( 'exam_access_codes', array(
			'quiz_id'       => absint( $quiz_id ),
			'enrollment_id' => absint( $enrollment_id ),
			'code_hash'     => hash( 'sha256', self::normalize_code( $plain ) ),
			'code_enc'      => self::encrypt( $plain ),
			'proctor_name'  => isset( $proctor['name'] ) ? sanitize_text_field( $proctor['name'] ) : null,
			'proctor_email' => isset( $proctor['email'] ) ? sanitize_email( $proctor['email'] ) : null,
			'notes'         => isset( $proctor['notes'] ) ? sanitize_textarea_field( $proctor['notes'] ) : null,
			'expires_at'    => gmdate( 'Y-m-d H:i:s', $expires ),
			'created_by'    => get_current_user_id() ?: null,
			'created_at'    => TPD_LMS_Native::now(),
		), false );
		return $id ? array( 'id' => $id, 'code' => $plain ) : new WP_Error( 'db_error', __( 'The code could not be created. Try again.', 'tpd-tool' ) );
	}

	public static function revoke_code( $code_id ) {
		return TPD_LMS_Store::update( 'exam_access_codes', $code_id, array( 'revoked_at' => TPD_LMS_Native::now() ), false );
	}

	public static function code_status( $code ) {
		if ( $code->revoked_at ) {
			return 'cancelled';
		}
		if ( $code->used_at ) {
			return 'used';
		}
		return strtotime( $code->expires_at . ' UTC' ) <= time() ? 'expired' : 'active';
	}

	public static function codes_for( $quiz_id, $enrollment_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'exam_access_codes' ) . ' WHERE quiz_id = %d AND enrollment_id = %d ORDER BY id DESC',
			absint( $quiz_id ),
			absint( $enrollment_id )
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Proctor requests                                                    */
	/* ------------------------------------------------------------------ */

	/**
	 * A student asks for a proctor for a code-locked exam.
	 *
	 * @return object|WP_Error Request row.
	 */
	public static function request_proctor( $quiz, $enrollment, array $data ) {
		if ( 'code' !== $quiz->access_mode ) {
			return new WP_Error( 'not_proctored', __( 'This exam doesn’t need a proctor.', 'tpd-tool' ) );
		}
		if ( self::active_request( $quiz->id, $enrollment->id ) ) {
			return new WP_Error( 'already_requested', __( 'You already have a proctor request for this exam. Cancel it to send a new one.', 'tpd-tool' ) );
		}
		$name  = sanitize_text_field( isset( $data['proctor_name'] ) ? $data['proctor_name'] : '' );
		$email = sanitize_email( isset( $data['proctor_email'] ) ? $data['proctor_email'] : '' );
		if ( '' === $name || ! is_email( $email ) ) {
			return new WP_Error( 'proctor_required', __( 'Enter your proctor’s name and a valid email address.', 'tpd-tool' ) );
		}
		$user = get_userdata( (int) $enrollment->user_id );
		if ( $user && strtolower( $user->user_email ) === strtolower( $email ) ) {
			return new WP_Error( 'self_proctor', __( 'Your proctor must be someone else. Enter their email address, not yours.', 'tpd-tool' ) );
		}
		$tz = isset( $data['timezone'] ) && in_array( $data['timezone'], timezone_identifiers_list(), true ) ? $data['timezone'] : 'UTC';
		$preferred = null;
		if ( ! empty( $data['preferred_at'] ) ) {
			try {
				$dt = new DateTime( (string) $data['preferred_at'], new DateTimeZone( $tz ) );
				$dt->setTimezone( new DateTimeZone( 'UTC' ) );
				$preferred = $dt->format( 'Y-m-d H:i:s' );
			} catch ( Exception $e ) {
				return new WP_Error( 'bad_time', __( 'Choose a valid date and time for the session.', 'tpd-tool' ) );
			}
		}
		$id = TPD_LMS_Store::insert( 'proctor_requests', array(
			'quiz_id'              => (int) $quiz->id,
			'enrollment_id'        => (int) $enrollment->id,
			'proctor_name'         => substr( $name, 0, 120 ),
			'proctor_email'        => $email,
			'proctor_phone'        => substr( sanitize_text_field( isset( $data['proctor_phone'] ) ? $data['proctor_phone'] : '' ), 0, 40 ) ?: null,
			'proctor_relationship' => substr( sanitize_text_field( isset( $data['proctor_relationship'] ) ? $data['proctor_relationship'] : '' ), 0, 120 ) ?: null,
			'preferred_at'         => $preferred,
			'timezone'             => $tz,
			'student_notes'        => sanitize_textarea_field( isset( $data['student_notes'] ) ? $data['student_notes'] : '' ) ?: null,
			'status'               => 'pending',
		) );
		if ( ! $id ) {
			return new WP_Error( 'db_error', __( 'Your request could not be sent. Try again.', 'tpd-tool' ) );
		}
		$request = TPD_LMS_Store::find( 'proctor_requests', $id );
		self::email_new_request( $request );
		return $request;
	}

	/** The student's pending or approved request for an exam, if any. */
	public static function active_request( $quiz_id, $enrollment_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'proctor_requests' ) . " WHERE quiz_id = %d AND enrollment_id = %d AND status IN ('pending','approved') ORDER BY id DESC LIMIT 1",
			absint( $quiz_id ),
			absint( $enrollment_id )
		) );
	}

	public static function latest_request( $quiz_id, $enrollment_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'proctor_requests' ) . ' WHERE quiz_id = %d AND enrollment_id = %d ORDER BY id DESC LIMIT 1',
			absint( $quiz_id ),
			absint( $enrollment_id )
		) );
	}

	public static function cancel_request( $request ) {
		if ( 'pending' !== $request->status ) {
			return new WP_Error( 'not_pending', __( 'Only a request that hasn’t been decided can be cancelled.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::update( 'proctor_requests', $request->id, array( 'status' => 'cancelled' ) );
		return true;
	}

	/**
	 * Approve: create the code, issue the proctor link, email proctor and student.
	 *
	 * @return array|WP_Error array( 'emails_sent' => bool )
	 */
	public static function approve( $request, $valid_hours, $note = '' ) {
		if ( 'pending' !== $request->status ) {
			return new WP_Error( 'not_pending', __( 'This request has already been decided.', 'tpd-tool' ) );
		}
		$code = self::create_code( $request->quiz_id, $request->enrollment_id, $valid_hours, array(
			'name'  => $request->proctor_name,
			'email' => $request->proctor_email,
			'notes' => $note,
		), $request->preferred_at );
		if ( is_wp_error( $code ) ) {
			return $code;
		}
		TPD_LMS_Store::update( 'proctor_requests', $request->id, array(
			'status'              => 'approved',
			'decision_note'       => sanitize_textarea_field( (string) $note ) ?: null,
			'decided_by'          => get_current_user_id(),
			'decided_at'          => TPD_LMS_Native::now(),
			'exam_access_code_id' => (int) $code['id'],
		) );
		$request = TPD_LMS_Store::find( 'proctor_requests', $request->id );
		$token   = self::issue_token( $request );
		$sent    = self::email_proctor( $request, $token ) & self::email_decision( $request );
		return array( 'emails_sent' => (bool) $sent );
	}

	public static function decline( $request, $reason ) {
		$reason = sanitize_textarea_field( (string) $reason );
		if ( 'pending' !== $request->status ) {
			return new WP_Error( 'not_pending', __( 'This request has already been decided.', 'tpd-tool' ) );
		}
		if ( '' === trim( $reason ) ) {
			return new WP_Error( 'reason_required', __( 'Tell the student why, so they can send a better request.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::update( 'proctor_requests', $request->id, array(
			'status'        => 'declined',
			'decision_note' => $reason,
			'decided_by'    => get_current_user_id(),
			'decided_at'    => TPD_LMS_Native::now(),
		) );
		return array( 'emails_sent' => self::email_decision( TPD_LMS_Store::find( 'proctor_requests', $request->id ) ) );
	}

	/** A new private link and a fresh copy of the email. The old link stops working. */
	public static function resend( $request ) {
		if ( 'approved' !== $request->status ) {
			return new WP_Error( 'not_approved', __( 'Only approved requests have a proctor email.', 'tpd-tool' ) );
		}
		return array( 'emails_sent' => self::email_proctor( $request, self::issue_token( $request ) ) );
	}

	private static function issue_token( $request ) {
		$token = wp_generate_password( 48, false, false );
		TPD_LMS_Store::update( 'proctor_requests', $request->id, array( 'proctor_token_hash' => hash( 'sha256', $token ) ) );
		return $token;
	}

	public static function find_by_token( $token ) {
		global $wpdb;
		if ( strlen( (string) $token ) !== 48 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'proctor_requests' ) . ' WHERE proctor_token_hash = %s',
			hash( 'sha256', (string) $token )
		) );
	}

	public static function proctor_url( $token ) {
		return home_url( '/' . self::PROCTOR_PATH . '/' . $token . '/' );
	}

	/** Context for emails and pages: student, course, exam, code. */
	public static function context( $request ) {
		$enrollment = TPD_LMS_Store::find( 'enrollments', $request->enrollment_id );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $request->quiz_id );
		$lesson     = $quiz ? TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) : null;
		$course     = $lesson ? TPD_LMS_Store::get_course( (int) $lesson->course_id ) : null;
		$code       = $request->exam_access_code_id ? TPD_LMS_Store::find( 'exam_access_codes', $request->exam_access_code_id ) : null;
		return array(
			'student'    => $enrollment ? get_userdata( (int) $enrollment->user_id ) : null,
			'enrollment' => $enrollment,
			'quiz'       => $quiz,
			'lesson'     => $lesson,
			'course'     => $course,
			'code'       => $code,
		);
	}

	/** A UTC time shown in a time zone, e.g. "Mar 4, 2027 at 3:00 pm (America/New_York)". */
	public static function format_time( $utc, $tz ) {
		if ( ! $utc ) {
			return '';
		}
		try {
			$zone = new DateTimeZone( $tz ? $tz : 'UTC' );
		} catch ( Exception $e ) {
			$zone = new DateTimeZone( 'UTC' );
		}
		return wp_date( 'M j, Y \a\t g:i a', strtotime( $utc . ' UTC' ), $zone ) . ' (' . $zone->getName() . ')';
	}

	/* ------------------------------------------------------------------ */
	/* Emails                                                              */
	/* ------------------------------------------------------------------ */

	private static function email_new_request( $request ) {
		$c          = self::context( $request );
		$instructor = $c['course'] ? get_userdata( (int) $c['course']->instructor_id ) : null;
		$to         = array( TPD_LMS_Mail::support_email() );
		if ( $instructor ) {
			$to[] = $instructor->user_email;
		}
		$body = implode( "\n", array_filter( array(
			'A student has asked for a proctor.',
			'',
			sprintf( 'Student: %s (%s)', $c['student'] ? $c['student']->display_name : '?', $c['student'] ? $c['student']->user_email : '?' ),
			sprintf( 'Exam: %s, in %s', $c['lesson'] ? $c['lesson']->title : '?', $c['course'] ? $c['course']->title : '?' ),
			sprintf( 'Proctor: %s <%s>', $request->proctor_name, $request->proctor_email ),
			$request->proctor_relationship ? 'Relationship: ' . $request->proctor_relationship : '',
			$request->preferred_at ? 'Preferred time: ' . self::format_time( $request->preferred_at, $request->timezone ) : '',
			$request->student_notes ? "Notes from the student:\n" . $request->student_notes : '',
			'',
			'Approve or decline it in Super Admin → LMS Studio → Proctoring:',
			home_url( '/tpd-admin/#sa-lms-studio' ),
		), 'strlen' ) );
		return TPD_LMS_Mail::send( $to, sprintf( 'Proctor request: %s', $c['student'] ? $c['student']->display_name : '' ), $body );
	}

	private static function email_proctor( $request, $token ) {
		$c    = self::context( $request );
		$body = implode( "\n", array_filter( array(
			sprintf( 'Hi %s,', $request->proctor_name ),
			'',
			sprintf( '%s has asked you to proctor their exam "%s" for %s.', $c['student'] ? $c['student']->display_name : 'A student', $c['lesson'] ? $c['lesson']->title : '', $c['course'] ? $c['course']->title : '' ),
			$request->preferred_at ? 'Their preferred time: ' . self::format_time( $request->preferred_at, $request->timezone ) : '',
			'',
			'What to do:',
			'1. Open your private proctor page (below) when you meet the student. It shows the one-time exam code.',
			'2. Watch the student start the exam, then type the code in for them. Don’t share it in advance.',
			'3. Stay with them until they submit. Then confirm the session on the same page, or report a problem.',
			'',
			'Your private proctor page:',
			self::proctor_url( $token ),
			'',
			'The code works once and expires ' . ( $c['code'] ? self::format_time( $c['code']->expires_at, $request->timezone ) : 'soon' ) . '.',
			$request->decision_note ? "\nNote from the course team:\n" . $request->decision_note : '',
			'',
			'Keep this link private: anyone with it can see the code.',
		), 'strlen' ) );
		return TPD_LMS_Mail::send( $request->proctor_email, sprintf( 'Proctor instructions for %s', $c['student'] ? $c['student']->display_name : 'a student' ), $body );
	}

	private static function email_decision( $request ) {
		$c = self::context( $request );
		if ( ! $c['student'] ) {
			return false;
		}
		if ( 'approved' === $request->status ) {
			$body = implode( "\n", array_filter( array(
				sprintf( 'Hi %s,', $c['student']->display_name ),
				'',
				sprintf( 'Your proctor request for "%s" was approved. We’ve emailed %s the exam code and instructions.', $c['lesson'] ? $c['lesson']->title : '', $request->proctor_name ),
				'When you meet, open the exam on your dashboard and your proctor will enter the code.',
				$request->decision_note ? "\nNote from the course team:\n" . $request->decision_note : '',
			), 'strlen' ) );
			$subject = 'Your proctor request was approved';
		} else {
			$body = implode( "\n", array(
				sprintf( 'Hi %s,', $c['student']->display_name ),
				'',
				sprintf( 'Your proctor request for "%s" wasn’t approved:', $c['lesson'] ? $c['lesson']->title : '' ),
				$request->decision_note,
				'',
				'You can send a new request from the exam page on your dashboard.',
			) );
			$subject = 'About your proctor request';
		}
		return TPD_LMS_Mail::send( $c['student']->user_email, $subject, $body );
	}

	/* ------------------------------------------------------------------ */
	/* Proctor page: /proctor/{token}/                                     */
	/* ------------------------------------------------------------------ */

	public static function proctor_template( $template ) {
		$path  = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		$parts = explode( '/', $path );
		if ( self::PROCTOR_PATH !== $parts[0] || empty( $parts[1] ) ) {
			return $template;
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		// Light rate limit per IP against guessing.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-f:.]/i', '', wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$rkey  = 'tpd_lms_proctor_' . md5( $ip );
		$count = (int) get_transient( $rkey );
		set_transient( $rkey, $count + 1, MINUTE_IN_SECONDS );
		$request = $count < 30 ? self::find_by_token( sanitize_text_field( $parts[1] ) ) : null;

		$notice = '';
		if ( $request && 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['proctor_action'] ) ) {
			if ( ! wp_verify_nonce( isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '', 'tpd_lms_proctor_' . $request->id ) ) {
				$notice = 'expired';
			} elseif ( 'confirm' === $_POST['proctor_action'] ) {
				TPD_LMS_Store::update( 'proctor_requests', $request->id, array( 'proctor_confirmed_at' => TPD_LMS_Native::now(), 'proctor_flagged' => 0 ) );
				$notice = 'confirmed';
			} elseif ( 'report' === $_POST['proctor_action'] ) {
				$report = sanitize_textarea_field( isset( $_POST['proctor_report'] ) ? wp_unslash( $_POST['proctor_report'] ) : '' );
				if ( '' === trim( $report ) ) {
					$notice = 'report_empty';
				} else {
					TPD_LMS_Store::update( 'proctor_requests', $request->id, array( 'proctor_confirmed_at' => TPD_LMS_Native::now(), 'proctor_flagged' => 1, 'proctor_report' => $report ) );
					$c = self::context( $request );
					TPD_LMS_Mail::send( TPD_LMS_Mail::support_email(), 'Proctor reported a problem', sprintf( "%s reported a problem with %s's exam \"%s\":\n\n%s", $request->proctor_name, $c['student'] ? $c['student']->display_name : '?', $c['lesson'] ? $c['lesson']->title : '?', $report ) );
					$notice = 'reported';
				}
			}
			$request = TPD_LMS_Store::find( 'proctor_requests', $request->id );
		}

		global $wp_query;
		$wp_query->is_404 = false;
		status_header( $request ? 200 : 404 );
		set_query_var( 'tpd_lms_proctor_request', $request );
		set_query_var( 'tpd_lms_proctor_notice', $notice );
		return TPD_TOOL_DIR . 'templates/lms/proctor.php';
	}
}
