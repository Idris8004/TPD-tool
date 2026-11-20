<?php
/**
 * Native LMS reports for LMS Studio: students per course, one student's
 * progress and attempts, and per-question results for each quiz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Reports {

	public static function students( $course_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE course_id = %d ORDER BY enrolled_at DESC',
			absint( $course_id )
		) );
		$out = array();
		foreach ( $rows as $e ) {
			$user  = get_userdata( (int) $e->user_id );
			$cert  = TPD_LMS_Progress::certificate_for_enrollment( (int) $e->id );
			$out[] = array(
				'enrollment_id' => (int) $e->id,
				'name'          => $user ? $user->display_name : __( '(deleted account)', 'tpd-tool' ),
				'email'         => $user ? $user->user_email : '',
				'progress'      => (int) $e->progress_percent,
				'enrolled_at'   => $e->enrolled_at,
				'completed_at'  => $e->completed_at,
				'last_activity' => $e->last_activity_at,
				'expired'       => TPD_LMS_Progress::is_expired( $e ),
				'paid'          => (bool) $e->tx_id,
				'certificate'   => $cert ? $cert->code : null,
			);
		}
		return $out;
	}

	public static function student( $enrollment ) {
		global $wpdb;
		$user     = get_userdata( (int) $enrollment->user_id );
		$done     = TPD_LMS_Progress::completed_lesson_ids( $enrollment );
		$times    = $wpdb->get_results( $wpdb->prepare( 'SELECT lesson_id, completed_at FROM ' . TPD_LMS_Schema::t( 'lesson_completions' ) . ' WHERE enrollment_id = %d', (int) $enrollment->id ), OBJECT_K );
		$lessons  = array();
		$exams    = array();
		foreach ( TPD_LMS_Store::lessons_for_course( (int) $enrollment->course_id ) as $l ) {
			$item = array(
				'id'           => (int) $l->id,
				'title'        => $l->title,
				'type'         => $l->type,
				'done'         => in_array( (int) $l->id, $done, true ),
				'completed_at' => isset( $times[ $l->id ] ) ? $times[ $l->id ]->completed_at : null,
				'attempts'     => array(),
			);
			$quiz = in_array( $l->type, TPD_LMS_Store::QUIZ_TYPES, true ) ? TPD_LMS_Store::quiz_for_lesson( $l->id ) : null;
			if ( $quiz ) {
				$attempts = $wpdb->get_results( $wpdb->prepare(
					'SELECT id, score, passed, started_at, submitted_at FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d ORDER BY id ASC',
					(int) $quiz->id,
					(int) $enrollment->id
				) );
				foreach ( $attempts as $a ) {
					$item['attempts'][] = array(
						'score'        => null === $a->score ? null : (int) $a->score,
						'passed'       => (bool) $a->passed,
						'started_at'   => $a->started_at,
						'submitted_at' => $a->submitted_at,
					);
				}
				if ( 'open' !== $quiz->access_mode ) {
					$codes = array();
					foreach ( TPD_LMS_Exam::codes_for( $quiz->id, $enrollment->id ) as $code ) {
						$codes[] = array(
							'id'            => (int) $code->id,
							'status'        => TPD_LMS_Exam::code_status( $code ),
							'proctor'       => trim( (string) $code->proctor_name . ( $code->proctor_email ? ' <' . $code->proctor_email . '>' : '' ) ),
							'expires_at'    => $code->expires_at,
							'used_at'       => $code->used_at,
						);
					}
					$exams[] = array(
						'quiz_id'     => (int) $quiz->id,
						'title'       => $l->title,
						'access_mode' => $quiz->access_mode,
						'codes'       => $codes,
					);
				}
			}
			$lessons[] = $item;
		}
		return array(
			'student' => array(
				'name'        => $user ? $user->display_name : __( '(deleted account)', 'tpd-tool' ),
				'email'       => $user ? $user->user_email : '',
				'progress'    => (int) $enrollment->progress_percent,
				'enrolled_at' => $enrollment->enrolled_at,
				'expires_at'  => $enrollment->expires_at,
			),
			'lessons' => $lessons,
			'exams'   => $exams,
		);
	}

	/**
	 * For each quiz: attempts, pass rate, average score and, per question, how
	 * often it was answered correctly (from submitted attempts' stored results).
	 */
	/**
	 * Survey results: how many chose each option, and the written answers.
	 */
	public static function survey( $lesson, $quiz ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare(
			'SELECT answers FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND submitted_at IS NOT NULL',
			(int) $quiz->id
		) );
		$responses = array();
		foreach ( $rows as $json ) {
			$responses[] = (array) json_decode( (string) $json, true );
		}
		$questions = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id ) as $q ) {
			$item = array(
				'prompt'  => wp_strip_all_tags( html_entity_decode( (string) $q->prompt, ENT_QUOTES, 'UTF-8' ) ),
				'type'    => $q->type,
				'answers' => 0,
				'options' => array(),
				'texts'   => array(),
			);
			$counts = array();
			foreach ( $q->options as $o ) {
				$counts[ (int) $o->id ] = 0;
			}
			foreach ( $responses as $r ) {
				if ( ! isset( $r[ $q->id ] ) || '' === $r[ $q->id ] || array() === $r[ $q->id ] ) {
					continue;
				}
				$item['answers']++;
				if ( 'short' === $q->type ) {
					$item['texts'][] = (string) $r[ $q->id ];
				} else {
					foreach ( (array) $r[ $q->id ] as $oid ) {
						if ( isset( $counts[ (int) $oid ] ) ) {
							$counts[ (int) $oid ]++;
						}
					}
				}
			}
			foreach ( $q->options as $o ) {
				$n                 = $counts[ (int) $o->id ];
				$item['options'][] = array(
					'text'    => $o->text,
					'count'   => $n,
					'percent' => $item['answers'] ? (int) round( 100 * $n / $item['answers'] ) : 0,
				);
			}
			$item['texts'] = array_slice( $item['texts'], -100 );
			$questions[]   = $item;
		}
		return array(
			'title'     => $lesson->title,
			'is_survey' => true,
			'responses' => count( $responses ),
			'questions' => $questions,
		);
	}

	public static function quizzes( $course_id ) {
		global $wpdb;
		$out = array();
		foreach ( TPD_LMS_Store::lessons_for_course( $course_id ) as $l ) {
			if ( ! in_array( $l->type, TPD_LMS_Store::QUIZ_TYPES, true ) ) {
				continue;
			}
			$quiz = TPD_LMS_Store::quiz_for_lesson( $l->id );
			if ( ! $quiz ) {
				continue;
			}
			if ( 'survey' === $l->type ) {
				$out[] = self::survey( $l, $quiz );
				continue;
			}
			$attempts = $wpdb->get_results( $wpdb->prepare(
				'SELECT score, passed, results FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND submitted_at IS NOT NULL',
				(int) $quiz->id
			) );
			$seen    = array();
			$right   = array();
			$scores  = array();
			$passes  = 0;
			foreach ( $attempts as $a ) {
				$scores[] = (int) $a->score;
				$passes  += (int) $a->passed;
				foreach ( (array) json_decode( (string) $a->results, true ) as $qid => $ok ) {
					$seen[ (int) $qid ]  = ( isset( $seen[ (int) $qid ] ) ? $seen[ (int) $qid ] : 0 ) + 1;
					$right[ (int) $qid ] = ( isset( $right[ (int) $qid ] ) ? $right[ (int) $qid ] : 0 ) + ( $ok ? 1 : 0 );
				}
			}
			$questions = array();
			foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id, false ) as $q ) {
				$n           = isset( $seen[ (int) $q->id ] ) ? $seen[ (int) $q->id ] : 0;
				$questions[] = array(
					'prompt'  => wp_strip_all_tags( html_entity_decode( (string) $q->prompt, ENT_QUOTES, 'UTF-8' ) ),
					'topic'   => (string) $q->topic,
					'answers' => $n,
					'correct' => $n ? (int) round( 100 * $right[ (int) $q->id ] / $n ) : null,
				);
			}
			usort( $questions, function ( $a, $b ) {
				// Hardest first; unanswered last.
				if ( null === $a['correct'] ) {
					return 1;
				}
				if ( null === $b['correct'] ) {
					return -1;
				}
				return $a['correct'] - $b['correct'];
			} );
			$out[] = array(
				'title'     => $l->title,
				'is_final'  => (bool) $quiz->is_final,
				'attempts'  => count( $attempts ),
				'pass_rate' => $attempts ? (int) round( 100 * $passes / count( $attempts ) ) : null,
				'average'   => $scores ? (int) round( array_sum( $scores ) / count( $scores ) ) : null,
				'questions' => $questions,
			);
		}
		return $out;
	}
}
