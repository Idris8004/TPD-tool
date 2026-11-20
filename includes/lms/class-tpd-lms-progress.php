<?php
/**
 * Native LMS progress: enrollment, lesson locks, completion and certificates.
 *
 * Rules (ported from the TARC learning platform, see its briefing.md):
 * - Lesson locks are checked in this order: not enrolled (only free previews
 *   are open), access expired, drip date not reached, sequential order, final
 *   exam gated until every lesson is done. Course managers bypass locks.
 * - Text, video and download lessons are completed by the student. Quizzes and
 *   exams are completed only by passing them (see TPD_LMS_Quiz::submit()).
 * - A certificate is issued when progress reaches 100%, the course has
 *   certificates on, and the final exam (if any) has been passed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Progress {

	/**
	 * Enroll a user in a course. Idempotent: returns the existing enrollment
	 * if there is one, and links the paying transaction if it was missing.
	 *
	 * @return object|WP_Error Enrollment row.
	 */
	public static function enroll( $user_id, $course_id, $tx_id = null ) {
		global $wpdb;
		$user_id = absint( $user_id );
		$course  = TPD_LMS_Store::get_course( absint( $course_id ) );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'user_missing', __( 'That user account does not exist.', 'tpd-tool' ) );
		}
		if ( ! $course ) {
			return new WP_Error( 'course_missing', __( 'That course does not exist.', 'tpd-tool' ) );
		}

		$existing = self::get_enrollment( $user_id, $course->id );
		if ( $existing ) {
			if ( $tx_id && ! $existing->tx_id ) {
				TPD_LMS_Store::update( 'enrollments', $existing->id, array( 'tx_id' => absint( $tx_id ) ) );
				$existing = TPD_LMS_Store::find( 'enrollments', $existing->id );
			}
			return $existing;
		}

		$now = TPD_LMS_Native::now();
		$id  = TPD_LMS_Store::insert( 'enrollments', array(
			'user_id'     => $user_id,
			'course_id'   => (int) $course->id,
			'tx_id'       => $tx_id ? absint( $tx_id ) : null,
			'enrolled_at' => $now,
			'expires_at'  => $course->access_days ? TPD_LMS_Native::now( DAY_IN_SECONDS * (int) $course->access_days ) : null,
		) );

		if ( ! $id ) {
			// Lost a race with a parallel request (unique user_id + course_id): use the winner's row.
			$existing = self::get_enrollment( $user_id, $course->id );
			return $existing ? $existing : new WP_Error( 'db_error', __( 'The enrollment could not be saved. Try again.', 'tpd-tool' ) );
		}

		$enrollment = TPD_LMS_Store::find( 'enrollments', $id );
		do_action( 'tpd_lms_enrolled', $enrollment, $course );
		return $enrollment;
	}

	public static function get_enrollment( $user_id, $course_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE user_id = %d AND course_id = %d',
			absint( $user_id ),
			absint( $course_id )
		) );
	}

	public static function enrollments_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE user_id = %d ORDER BY COALESCE(last_activity_at, enrolled_at) DESC',
			absint( $user_id )
		) );
	}

	public static function is_expired( $enrollment ) {
		return $enrollment && ! empty( $enrollment->expires_at ) && strtotime( $enrollment->expires_at . ' UTC' ) <= time();
	}

	/**
	 * @return int[] Ids of lessons this enrollment has completed.
	 */
	public static function completed_lesson_ids( $enrollment ) {
		global $wpdb;
		if ( ! $enrollment ) {
			return array();
		}
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
			'SELECT lesson_id FROM ' . TPD_LMS_Schema::t( 'lesson_completions' ) . ' WHERE enrollment_id = %d',
			(int) $enrollment->id
		) ) );
	}

	/**
	 * Returns null when the lesson is open, or a reason it is locked.
	 *
	 * @param object      $lesson     Lesson row.
	 * @param object|null $enrollment Enrollment row, or null when not enrolled.
	 * @param array|null  $ordered    Lessons in order (pass to avoid re-querying in loops).
	 * @param int[]|null  $done       Completed lesson ids (same).
	 */
	public static function lock_reason( $lesson, $enrollment, $ordered = null, $done = null ) {
		if ( ! $enrollment ) {
			return ! empty( $lesson->is_preview ) ? null : __( 'Enroll to unlock this lesson.', 'tpd-tool' );
		}
		if ( self::is_expired( $enrollment ) ) {
			return __( 'Your access to this course has ended.', 'tpd-tool' );
		}
		// Unlocked early by an admin (button or unlock key): skip the order and "finish every lesson" checks.
		if ( 'exam' === $lesson->type && ! empty( $enrollment->exam_unlocked_at ) ) {
			return null;
		}

		$course = TPD_LMS_Store::get_course( (int) $lesson->course_id );

		if ( $course && $course->drip_enabled && $lesson->drip_days ) {
			$opens = strtotime( $enrollment->enrolled_at . ' UTC' ) + DAY_IN_SECONDS * (int) $lesson->drip_days;
			if ( $opens > time() ) {
				/* translators: %s: date the lesson opens */
				return sprintf( __( 'Available on %s.', 'tpd-tool' ), wp_date( 'M j, Y', $opens ) );
			}
		}

		if ( null === $ordered ) {
			$ordered = TPD_LMS_Store::lessons_for_course( (int) $lesson->course_id );
		}
		if ( null === $done ) {
			$done = self::completed_lesson_ids( $enrollment );
		}

		if ( $course && $course->sequential ) {
			foreach ( $ordered as $l ) {
				if ( (int) $l->id === (int) $lesson->id ) {
					break;
				}
				if ( ! in_array( (int) $l->id, $done, true ) ) {
					/* translators: %s: title of the lesson to complete first */
					return sprintf( __( 'Complete "%s" first.', 'tpd-tool' ), $l->title );
				}
			}
		}

		if ( 'exam' === $lesson->type ) {
			$quiz = TPD_LMS_Store::quiz_for_lesson( (int) $lesson->id );
			if ( $quiz && $quiz->require_all_lessons ) {
				$remaining = 0;
				foreach ( $ordered as $l ) {
					if ( (int) $l->id !== (int) $lesson->id && 'exam' !== $l->type && ! in_array( (int) $l->id, $done, true ) ) {
						$remaining++;
					}
				}
				if ( $remaining > 0 ) {
					/* translators: %d: number of lessons left */
					return sprintf( _n( 'Finish all lessons to unlock the final exam (%d left).', 'Finish all lessons to unlock the final exam (%d left).', $remaining, 'tpd-tool' ), $remaining );
				}
			}
		}

		return null;
	}

	/**
	 * Lock reason for a specific user: course managers are never locked out.
	 */
	public static function lock_reason_for_user( $lesson, $user_id ) {
		$course = TPD_LMS_Store::get_course( (int) $lesson->course_id );
		if ( TPD_LMS_Store::can_manage_course( $course, $user_id ) ) {
			return null;
		}
		return self::lock_reason( $lesson, self::get_enrollment( $user_id, (int) $lesson->course_id ) );
	}

	/**
	 * Mark a lesson complete for an enrollment and update progress.
	 */
	public static function complete( $enrollment, $lesson ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			'INSERT IGNORE INTO ' . TPD_LMS_Schema::t( 'lesson_completions' ) . ' (enrollment_id, lesson_id, completed_at) VALUES (%d, %d, %s)',
			(int) $enrollment->id,
			(int) $lesson->id,
			TPD_LMS_Native::now()
		) );
		self::recalculate( (int) $enrollment->id );
	}

	/**
	 * Recompute progress_percent, set completed_at at 100%, and issue the certificate.
	 */
	public static function recalculate( $enrollment_id ) {
		global $wpdb;
		$enrollment = TPD_LMS_Store::find( 'enrollments', $enrollment_id );
		if ( ! $enrollment ) {
			return;
		}
		$lesson_ids = wp_list_pluck( TPD_LMS_Store::lessons_for_course( (int) $enrollment->course_id ), 'id' );
		$lesson_ids = array_map( 'intval', $lesson_ids );
		$done       = array_intersect( self::completed_lesson_ids( $enrollment ), $lesson_ids );
		$total      = count( $lesson_ids );
		$percent    = $total ? (int) floor( count( $done ) / $total * 100 ) : 0;

		$update         = array( 'progress_percent' => $percent );
		$just_completed = false;
		if ( 100 === $percent && empty( $enrollment->completed_at ) ) {
			$update['completed_at'] = TPD_LMS_Native::now();
			$just_completed         = true;
		}
		TPD_LMS_Store::update( 'enrollments', $enrollment->id, $update );

		$enrollment = TPD_LMS_Store::find( 'enrollments', $enrollment->id );
		if ( $just_completed ) {
			do_action( 'tpd_lms_course_completed', $enrollment );
		}
		if ( 100 === $percent ) {
			self::issue_certificate( $enrollment );
		}
	}

	/**
	 * Issue the certificate if the enrollment qualifies. Idempotent.
	 *
	 * @return object|null Certificate row.
	 */
	public static function issue_certificate( $enrollment ) {
		global $wpdb;
		$course = TPD_LMS_Store::get_course( (int) $enrollment->course_id );
		if ( ! $course || ! $course->certificate_enabled ) {
			return null;
		}
		$existing = self::certificate_for_enrollment( (int) $enrollment->id );
		if ( $existing ) {
			return $existing;
		}

		$score = null;
		$final = TPD_LMS_Store::final_exam_for_course( (int) $course->id );
		if ( $final ) {
			$best = $wpdb->get_var( $wpdb->prepare(
				'SELECT MAX(score) FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d AND passed = 1',
				(int) $final->id,
				(int) $enrollment->id
			) );
			if ( null === $best ) {
				return null;
			}
			$score = (int) $best;
		}

		do {
			$code = self::random_code();
		} while ( self::find_certificate( $code ) );

		$id = TPD_LMS_Store::insert( 'certificates', array(
			'enrollment_id' => (int) $enrollment->id,
			'user_id'       => (int) $enrollment->user_id,
			'course_id'     => (int) $course->id,
			'code'          => $code,
			'final_score'   => $score,
			'issued_at'     => TPD_LMS_Native::now(),
		), false );

		if ( ! $id ) {
			// A parallel request issued it first (unique enrollment_id).
			return self::certificate_for_enrollment( (int) $enrollment->id );
		}

		$certificate = TPD_LMS_Store::find( 'certificates', $id );
		do_action( 'tpd_lms_certificate_issued', $certificate, $enrollment, $course );
		return $certificate;
	}

	public static function certificate_for_enrollment( $enrollment_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'certificates' ) . ' WHERE enrollment_id = %d', absint( $enrollment_id ) ) );
	}

	public static function find_certificate( $code ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'certificates' ) . ' WHERE code = %s', strtoupper( trim( (string) $code ) ) ) );
	}

	/**
	 * Verification code like "K7QF-2MXP-9TRA" (no 0/O/1/I to avoid misreading).
	 */
	private static function random_code() {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$parts    = array();
		for ( $p = 0; $p < 3; $p++ ) {
			$part = '';
			for ( $i = 0; $i < 4; $i++ ) {
				$part .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
			$parts[] = $part;
		}
		return implode( '-', $parts );
	}
}
