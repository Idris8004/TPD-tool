<?php
/**
 * Native LMS quiz engine: attempts, question-bank draws and grading.
 *
 * Rules (ported from the TARC learning platform):
 * - Multiple-select questions are all or nothing.
 * - Short answers ignore capital letters and extra spaces.
 * - The score is a percentage of points; a student passes at or above the pass mark.
 * - With "questions per attempt" set, each attempt draws that many questions at
 *   random. Every topic gets a share in proportion to its size (largest
 *   remainder), and questions seen in earlier attempts are used only once
 *   unseen ones run out. Scores count only the drawn questions.
 * - Time limits are enforced on the server: answers sent after the deadline
 *   are ignored and the attempt is graded on what was saved in time.
 * - Never trust scores or answers' correctness from the browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Quiz {

	/** Seconds allowed after the deadline for the submit request to arrive. */
	const DEADLINE_GRACE = 30;

	public static function attempts_used( $quiz, $enrollment ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d AND submitted_at IS NOT NULL',
			(int) $quiz->id,
			(int) $enrollment->id
		) );
	}

	public static function question_count( $quiz ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . TPD_LMS_Schema::t( 'questions' ) . ' WHERE quiz_id = %d', (int) $quiz->id ) );
	}

	/**
	 * Number of questions in one attempt.
	 */
	public static function attempt_size( $quiz ) {
		$bank = self::question_count( $quiz );
		return $quiz->questions_per_attempt ? min( (int) $quiz->questions_per_attempt, $bank ) : $bank;
	}

	public static function can_attempt( $quiz, $enrollment ) {
		if ( 0 === self::question_count( $quiz ) ) {
			return false;
		}
		if ( null === $quiz->max_attempts ) {
			return true;
		}
		return self::attempts_used( $quiz, $enrollment ) < (int) $quiz->max_attempts;
	}

	public static function open_attempt( $quiz, $enrollment ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d AND submitted_at IS NULL ORDER BY id DESC LIMIT 1',
			(int) $quiz->id,
			(int) $enrollment->id
		) );
	}

	/**
	 * Return the open attempt if one is still running, otherwise start a new one.
	 * An expired open attempt is graded first.
	 *
	 * @return object|WP_Error Attempt row (JSON columns decoded).
	 */
	public static function start( $quiz, $enrollment ) {
		$open = self::open_attempt( $quiz, $enrollment );
		if ( $open ) {
			if ( ! self::is_past_deadline( $open ) ) {
				return self::decode( $open );
			}
			self::submit( $open, null );
		}
		if ( ! self::can_attempt( $quiz, $enrollment ) ) {
			return new WP_Error( 'no_attempts_left', __( 'You have used all your attempts for this quiz.', 'tpd-tool' ) );
		}

		$now = time();
		$id  = TPD_LMS_Store::insert( 'quiz_attempts', array(
			'quiz_id'        => (int) $quiz->id,
			'enrollment_id'  => (int) $enrollment->id,
			'user_id'        => (int) $enrollment->user_id,
			'question_order' => wp_json_encode( self::draw_questions( $quiz, $enrollment ) ),
			'answers'        => wp_json_encode( new stdClass() ),
			'started_at'     => gmdate( 'Y-m-d H:i:s', $now ),
			'expires_at'     => $quiz->time_limit_minutes ? gmdate( 'Y-m-d H:i:s', $now + 60 * (int) $quiz->time_limit_minutes ) : null,
		) );
		return $id ? self::get_attempt( $id ) : new WP_Error( 'db_error', __( 'The attempt could not be started. Try again.', 'tpd-tool' ) );
	}

	public static function get_attempt( $attempt_id ) {
		$row = TPD_LMS_Store::find( 'quiz_attempts', $attempt_id );
		return $row ? self::decode( $row ) : null;
	}

	/**
	 * Pick the question ids for a new attempt (see class notes).
	 *
	 * @return int[]
	 */
	public static function draw_questions( $quiz, $enrollment ) {
		global $wpdb;
		$bank = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, topic FROM ' . TPD_LMS_Schema::t( 'questions' ) . ' WHERE quiz_id = %d ORDER BY position ASC, id ASC',
			(int) $quiz->id
		) );
		$all_ids = array_map( 'intval', wp_list_pluck( $bank, 'id' ) );
		$size    = self::attempt_size( $quiz );

		if ( $size >= count( $bank ) ) {
			if ( $quiz->shuffle_questions ) {
				shuffle( $all_ids );
			}
			return $all_ids;
		}

		// Questions this student saw in earlier attempts.
		$seen = array();
		$prev = $wpdb->get_col( $wpdb->prepare(
			'SELECT question_order FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d',
			(int) $quiz->id,
			(int) $enrollment->id
		) );
		foreach ( $prev as $json ) {
			foreach ( (array) json_decode( $json, true ) as $qid ) {
				$seen[ (int) $qid ] = true;
			}
		}

		$groups = array();
		foreach ( $bank as $q ) {
			$groups[ (string) $q->topic ][] = (int) $q->id;
		}

		$quotas     = array();
		$remainders = array();
		foreach ( $groups as $topic => $ids ) {
			$exact                = $size * count( $ids ) / count( $bank );
			$quotas[ $topic ]     = (int) floor( $exact );
			$remainders[ $topic ] = $exact - floor( $exact );
		}
		$topics = array_map( 'strval', array_keys( $remainders ) );
		shuffle( $topics ); // Random tie-break, then largest remainder first.
		$order = array_flip( $topics );
		usort( $topics, function ( $a, $b ) use ( $remainders, $order ) {
			$cmp = $remainders[ $b ] <=> $remainders[ $a ];
			return 0 !== $cmp ? $cmp : $order[ $a ] - $order[ $b ]; // Stable on PHP 7.4 too.
		} );
		foreach ( array_slice( $topics, 0, $size - array_sum( $quotas ) ) as $topic ) {
			$quotas[ $topic ]++;
		}

		$picked = array();
		foreach ( $groups as $topic => $ids ) {
			shuffle( $ids );
			$unseen = array();
			$again  = array();
			foreach ( $ids as $id ) {
				if ( isset( $seen[ $id ] ) ) {
					$again[] = $id;
				} else {
					$unseen[] = $id;
				}
			}
			$picked = array_merge( $picked, array_slice( array_merge( $unseen, $again ), 0, $quotas[ (string) $topic ] ) );
		}

		if ( $quiz->shuffle_questions ) {
			shuffle( $picked );
			return $picked;
		}
		$keep = array_flip( $picked );
		return array_values( array_filter( $all_ids, function ( $id ) use ( $keep ) {
			return isset( $keep[ $id ] );
		} ) );
	}

	/**
	 * Save answers during an attempt (autosave). Ignored after the deadline.
	 */
	public static function save_answers( $attempt, array $answers ) {
		$attempt = is_object( $attempt ) ? $attempt : self::get_attempt( $attempt );
		if ( ! $attempt || ! empty( $attempt->submitted_at ) || self::is_past_deadline( $attempt ) ) {
			return false;
		}
		return TPD_LMS_Store::update( 'quiz_attempts', $attempt->id, array(
			'answers' => wp_json_encode( self::only_drawn( $attempt, $answers ) ),
		) );
	}

	/**
	 * Grade and close an attempt. Idempotent.
	 *
	 * @param object     $attempt Attempt row.
	 * @param array|null $answers Final answers keyed by question id, or null to
	 *                            grade what was saved. After the deadline, the
	 *                            answers sent now are ignored.
	 * @return object Attempt row (decoded).
	 */
	public static function submit( $attempt, $answers ) {
		$attempt = self::decode( TPD_LMS_Store::find( 'quiz_attempts', is_object( $attempt ) ? $attempt->id : $attempt ) );
		if ( ! empty( $attempt->submitted_at ) ) {
			return $attempt;
		}
		if ( null === $answers || self::is_past_deadline( $attempt ) ) {
			$answers = (array) $attempt->answers;
		}
		$answers = self::only_drawn( $attempt, (array) $answers );

		$quiz      = TPD_LMS_Store::find( 'quizzes', $attempt->quiz_id );
		$questions = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( (int) $quiz->id ) as $q ) {
			$questions[ (int) $q->id ] = $q;
		}

		$earned  = 0;
		$total   = 0;
		$results = array();
		foreach ( $attempt->question_order as $qid ) {
			if ( ! isset( $questions[ $qid ] ) ) {
				continue; // Question deleted since the attempt started.
			}
			$q      = $questions[ $qid ];
			$total += (int) $q->points;
			$ok     = self::grade( $q, isset( $answers[ $qid ] ) ? $answers[ $qid ] : null );
			if ( $ok ) {
				$earned += (int) $q->points;
			}
			$results[ $qid ] = $ok;
		}

		$score  = $total ? (int) round( $earned / $total * 100 ) : 0;
		$passed = $score >= (int) $quiz->passing_score;

		// Only the first request to close the attempt wins, so two submits can't both grade it.
		global $wpdb;
		$closed = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' SET answers = %s, results = %s, points_earned = %d, points_total = %d, score = %d, passed = %d, submitted_at = %s, updated_at = %s WHERE id = %d AND submitted_at IS NULL',
			wp_json_encode( (object) $answers ),
			wp_json_encode( (object) $results ),
			$earned,
			$total,
			$score,
			$passed ? 1 : 0,
			TPD_LMS_Native::now(),
			TPD_LMS_Native::now(),
			(int) $attempt->id
		) );

		$attempt = self::get_attempt( $attempt->id );
		if ( $closed && $passed ) {
			$enrollment = TPD_LMS_Store::find( 'enrollments', $attempt->enrollment_id );
			$lesson     = TPD_LMS_Store::find( 'lessons', $quiz->lesson_id );
			if ( $enrollment && $lesson ) {
				TPD_LMS_Progress::complete( $enrollment, $lesson );
			}
		}
		if ( $closed ) {
			do_action( 'tpd_lms_attempt_submitted', $attempt, $quiz );
		}
		return $attempt;
	}

	/**
	 * Grade one raw answer. True only when fully correct.
	 */
	public static function grade( $question, $answer ) {
		$correct = array();
		foreach ( (array) ( isset( $question->options ) ? $question->options : array() ) as $o ) {
			if ( (int) $o->is_correct ) {
				$correct[] = (int) $o->id;
			}
		}
		sort( $correct );

		switch ( $question->type ) {
			case 'single':
			case 'true_false':
				return is_scalar( $answer ) && '' !== (string) $answer && in_array( (int) $answer, $correct, true );
			case 'multiple':
				if ( ! $correct ) {
					return false;
				}
				$given = array_values( array_unique( array_map( 'intval', (array) $answer ) ) );
				sort( $given );
				return $given === $correct;
			case 'short':
				return is_string( $answer ) && in_array( self::normalize( $answer ), self::accepted_answers( $question ), true );
		}
		return false;
	}

	/**
	 * Accepted short answers, one per line.
	 */
	public static function accepted_answers( $question ) {
		$out = array();
		foreach ( preg_split( '/\r?\n/', (string) $question->accepted_answers ) as $a ) {
			$a = self::normalize( $a );
			if ( '' !== $a ) {
				$out[] = $a;
			}
		}
		return $out;
	}

	public static function normalize( $s ) {
		$s = (string) $s;
		$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
		return trim( preg_replace( '/\s+/u', ' ', $s ) );
	}

	public static function is_past_deadline( $attempt ) {
		return ! empty( $attempt->expires_at ) && strtotime( $attempt->expires_at . ' UTC' ) + self::DEADLINE_GRACE < time();
	}

	/** Keep only answers to questions drawn for this attempt. */
	private static function only_drawn( $attempt, array $answers ) {
		$order = is_array( $attempt->question_order ) ? $attempt->question_order : (array) json_decode( $attempt->question_order, true );
		$keep  = array_flip( array_map( 'intval', $order ) );
		$out   = array();
		foreach ( $answers as $qid => $value ) {
			if ( isset( $keep[ (int) $qid ] ) ) {
				$out[ (int) $qid ] = $value;
			}
		}
		return $out;
	}

	private static function decode( $row ) {
		if ( ! $row ) {
			return $row;
		}
		$row->question_order = array_map( 'intval', (array) json_decode( (string) $row->question_order, true ) );
		$row->answers        = (array) json_decode( (string) $row->answers, true );
		$row->results        = (array) json_decode( (string) $row->results, true );
		return $row;
	}
}
