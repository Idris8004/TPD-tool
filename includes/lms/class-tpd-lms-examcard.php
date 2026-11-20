<?php
/**
 * Native LMS exam card: a course's final exam shown as its own card, outside
 * the curriculum, for courses with "exam_card" on (the RTA program).
 *
 * - Every enrolled advisor gets the card. It is locked until every other
 *   lesson is done, unless an admin unlocks it early, with the "Unlock exam"
 *   button or an unlock key the advisor types on the card.
 * - Unlock keys work once, for one advisor, until they expire. Only a hash is
 *   stored. Five wrong tries lock the advisor out for 15 minutes.
 * - Exam passwords/proctor codes still apply when starting an attempt, and the
 *   certificate still needs the exam passed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_ExamCard {

	const MAX_WRONG = 5;
	const LOCKOUT   = 900;
	const KEY_DAYS  = array( 1 => '1 day', 7 => '1 week', 30 => '30 days', 90 => '90 days' );
	const ALPHABET  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

	/** The course's final exam lesson when the course shows it as a card, else null. */
	public static function exam_lesson( $course ) {
		if ( ! $course || empty( $course->exam_card ) ) {
			return null;
		}
		$quiz = TPD_LMS_Store::final_exam_for_course( (int) $course->id );
		return $quiz ? TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) : null;
	}

	/**
	 * Card state for one enrollment.
	 *
	 * @return array|null
	 */
	public static function state( $enrollment ) {
		$course = $enrollment ? TPD_LMS_Store::get_course( (int) $enrollment->course_id ) : null;
		$lesson = self::exam_lesson( $course );
		if ( ! $lesson ) {
			return null;
		}
		$quiz    = TPD_LMS_Store::quiz_for_lesson( $lesson->id );
		$ordered = TPD_LMS_Store::lessons_for_course( (int) $course->id );
		$done    = TPD_LMS_Progress::completed_lesson_ids( $enrollment );
		$left    = 0;
		foreach ( $ordered as $l ) {
			if ( 'exam' !== $l->type && ! in_array( (int) $l->id, $done, true ) ) {
				$left++;
			}
		}
		$lock = TPD_LMS_Progress::lock_reason( $lesson, $enrollment, $ordered, $done );

		global $wpdb;
		$best = $wpdb->get_var( $wpdb->prepare(
			'SELECT MAX(score) FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d AND submitted_at IS NOT NULL',
			(int) $quiz->id,
			(int) $enrollment->id
		) );
		$passed = in_array( (int) $lesson->id, $done, true );
		$how    = null;
		if ( null === $lock ) {
			$how = ! empty( $enrollment->exam_unlocked_at ) ? 'admin' : 'finished';
		}
		return array(
			'lesson_id'     => (int) $lesson->id,
			'title'         => $lesson->title,
			'course_slug'   => $course->slug,
			'course_title'  => $course->title,
			'locked'        => null !== $lock,
			'lock'          => $lock,
			'lessons_left'  => $left,
			'unlocked_how'  => $how,
			'passed'        => $passed,
			'best_score'    => null === $best ? null : (int) $best,
			'passing_score' => (int) $quiz->passing_score,
			'attempts_used' => TPD_LMS_Quiz::attempts_used( $quiz, $enrollment ),
			'max_attempts'  => null === $quiz->max_attempts ? null : (int) $quiz->max_attempts,
			'time_limit'    => null === $quiz->time_limit_minutes ? null : (int) $quiz->time_limit_minutes,
			'questions'     => TPD_LMS_Quiz::attempt_size( $quiz ),
			'has_key'       => ! empty( $enrollment->exam_unlock_key_hash ) && strtotime( $enrollment->exam_unlock_key_expires . ' UTC' ) > time(),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Admin unlock                                                        */
	/* ------------------------------------------------------------------ */

	public static function unlock( $enrollment, $by_user_id ) {
		TPD_LMS_Store::update( 'enrollments', $enrollment->id, array(
			'exam_unlocked_at'        => TPD_LMS_Native::now(),
			'exam_unlocked_by'        => absint( $by_user_id ) ?: null,
			'exam_unlock_key_hash'    => null,
			'exam_unlock_key_expires' => null,
		) );
		$enrollment = TPD_LMS_Store::find( 'enrollments', $enrollment->id );
		self::email_unlocked( $enrollment );
		return $enrollment;
	}

	/** Undo an early unlock. The card follows the normal rule again. */
	public static function relock( $enrollment ) {
		TPD_LMS_Store::update( 'enrollments', $enrollment->id, array(
			'exam_unlocked_at'        => null,
			'exam_unlocked_by'        => null,
			'exam_unlock_key_hash'    => null,
			'exam_unlock_key_expires' => null,
		) );
		return TPD_LMS_Store::find( 'enrollments', $enrollment->id );
	}

	public static function normalize_key( $key ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $key ) );
	}

	/**
	 * Make a new unlock key for one advisor (replaces any earlier key).
	 *
	 * @return string The key, shown once.
	 */
	public static function create_key( $enrollment, $days = 7, $email_to_advisor = false ) {
		$days  = isset( self::KEY_DAYS[ (int) $days ] ) ? (int) $days : 7;
		$chars = '';
		for ( $i = 0; $i < 8; $i++ ) {
			$chars .= self::ALPHABET[ random_int( 0, strlen( self::ALPHABET ) - 1 ) ];
		}
		$key = substr( $chars, 0, 4 ) . '-' . substr( $chars, 4 );
		TPD_LMS_Store::update( 'enrollments', $enrollment->id, array(
			'exam_unlock_key_hash'    => hash( 'sha256', self::normalize_key( $key ) ),
			'exam_unlock_key_expires' => TPD_LMS_Native::now( DAY_IN_SECONDS * $days ),
		) );
		if ( $email_to_advisor ) {
			self::email_key( TPD_LMS_Store::find( 'enrollments', $enrollment->id ), $key, $days );
		}
		return $key;
	}

	/**
	 * The advisor types an unlock key on the card.
	 *
	 * @return object|WP_Error Enrollment.
	 */
	public static function redeem_key( $enrollment, $key ) {
		$lockkey = 'tpd_lms_unlock_' . (int) $enrollment->id;
		$tries   = get_transient( $lockkey );
		$tries   = is_array( $tries ) ? $tries : array( 'count' => 0, 'until' => 0 );
		if ( $tries['count'] >= self::MAX_WRONG && $tries['until'] > time() ) {
			$minutes = (int) ceil( ( $tries['until'] - time() ) / 60 );
			/* translators: %d: minutes */
			return new WP_Error( 'locked_out', sprintf( _n( 'Too many wrong tries. Wait %d minute, then try again.', 'Too many wrong tries. Wait %d minutes, then try again.', $minutes, 'tpd-tool' ), $minutes ) );
		}
		$given = self::normalize_key( $key );
		$valid = '' !== $given
			&& ! empty( $enrollment->exam_unlock_key_hash )
			&& strtotime( $enrollment->exam_unlock_key_expires . ' UTC' ) > time()
			&& hash_equals( (string) $enrollment->exam_unlock_key_hash, hash( 'sha256', $given ) );
		if ( ! $valid ) {
			$tries['count']++;
			$tries['until'] = time() + self::LOCKOUT;
			set_transient( $lockkey, $tries, self::LOCKOUT );
			return new WP_Error( 'bad_key', __( 'That key isn’t valid. Keys work once, for one advisor, until they expire. Ask TARC for a new one.', 'tpd-tool' ) );
		}
		delete_transient( $lockkey );
		TPD_LMS_Store::update( 'enrollments', $enrollment->id, array(
			'exam_unlocked_at'        => TPD_LMS_Native::now(),
			'exam_unlocked_by'        => null, // Unlocked with a key.
			'exam_unlock_key_hash'    => null,
			'exam_unlock_key_expires' => null,
		) );
		return TPD_LMS_Store::find( 'enrollments', $enrollment->id );
	}

	/* ------------------------------------------------------------------ */
	/* Emails                                                              */
	/* ------------------------------------------------------------------ */

	private static function exam_link( $enrollment ) {
		$course = TPD_LMS_Store::get_course( (int) $enrollment->course_id );
		return add_query_arg( 'lms_course', $course ? $course->slug : '', home_url( '/advisor-dashboard/' ) ) . '#tpd-view-resources';
	}

	private static function email_unlocked( $enrollment ) {
		$user   = get_userdata( (int) $enrollment->user_id );
		$course = TPD_LMS_Store::get_course( (int) $enrollment->course_id );
		$lesson = self::exam_lesson( $course );
		if ( ! $user || ! $lesson ) {
			return;
		}
		TPD_LMS_Mail::send( $user->user_email, sprintf( 'Your %s is unlocked', $lesson->title ), implode( "\n", array(
			sprintf( 'Hi %s,', $user->display_name ),
			'',
			sprintf( 'The course team unlocked "%s" for you. You can take it from Resources & Tools on your dashboard:', $lesson->title ),
			self::exam_link( $enrollment ),
		) ) );
	}

	private static function email_key( $enrollment, $key, $days ) {
		$user   = get_userdata( (int) $enrollment->user_id );
		$course = TPD_LMS_Store::get_course( (int) $enrollment->course_id );
		$lesson = self::exam_lesson( $course );
		if ( ! $user || ! $lesson ) {
			return;
		}
		TPD_LMS_Mail::send( $user->user_email, sprintf( 'Your unlock key for %s', $lesson->title ), implode( "\n", array(
			sprintf( 'Hi %s,', $user->display_name ),
			'',
			sprintf( 'Here is your key to unlock "%s":', $lesson->title ),
			'',
			'    ' . $key,
			'',
			sprintf( 'Open Resources & Tools on your dashboard, find the exam card and type the key. It works once and expires in %s.', self::KEY_DAYS[ $days ] ),
			self::exam_link( $enrollment ),
		) ) );
	}
}
