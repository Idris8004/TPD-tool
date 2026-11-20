<?php
/**
 * Shared helpers for native LMS tests. Everything a test creates is deleted afterwards.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

use PHPUnit\Framework\TestCase;

abstract class LmsTestCase extends TestCase {

	private $user_ids   = array();
	private $course_ids = array();

	protected function tearDown(): void {
		foreach ( $this->course_ids as $id ) {
			TPD_LMS_Store::delete_course( $id );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->user_ids as $id ) {
			wp_delete_user( $id );
		}
		$this->user_ids   = array();
		$this->course_ids = array();
		wp_set_current_user( 0 );
	}

	protected function make_user( $role = 'travel_advisor' ) {
		$login = 'lmstest_' . wp_generate_password( 8, false, false );
		$id    = wp_insert_user( array(
			'user_login' => $login,
			'user_email' => $login . '@example.test',
			'user_pass'  => wp_generate_password( 16 ),
			'role'       => $role,
		) );
		$this->assertIsInt( $id, 'Test user could not be created.' );
		$this->user_ids[] = $id;
		return $id;
	}

	/**
	 * Create a course. $lessons is a list of lesson specs, each
	 * array( 'type' => 'text', 'title' => ..., ...lesson fields ). All go in one module
	 * unless a spec has 'chapter' => n (1-based).
	 *
	 * @return array( 'course' => object, 'lessons' => object[] )
	 */
	protected function make_course( array $course = array(), array $lessons = array() ) {
		$instructor = isset( $course['instructor_id'] ) ? $course['instructor_id'] : $this->make_user( 'administrator' );
		$id         = TPD_LMS_Store::create_course( $course + array(
			'title'         => 'Test course ' . wp_generate_password( 6, false, false ),
			'status'        => 'published',
			'instructor_id' => $instructor,
		) );
		$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : 'Course not created.' );
		$this->course_ids[] = $id;

		$chapters = array();
		$made     = array();
		foreach ( $lessons ?: array( array( 'type' => 'text' ) ) as $i => $spec ) {
			$n = isset( $spec['chapter'] ) ? (int) $spec['chapter'] : 1;
			if ( ! isset( $chapters[ $n ] ) ) {
				$chapters[ $n ] = TPD_LMS_Store::create_chapter( $id, 'Module ' . $n );
			}
			unset( $spec['chapter'] );
			$lesson_id = TPD_LMS_Store::create_lesson( $chapters[ $n ], $spec + array( 'title' => 'Lesson ' . ( $i + 1 ) ) );
			$this->assertIsInt( $lesson_id, is_wp_error( $lesson_id ) ? $lesson_id->get_error_message() : 'Lesson not created.' );
			$made[] = TPD_LMS_Store::find( 'lessons', $lesson_id );
		}
		return array( 'course' => TPD_LMS_Store::get_course( $id ), 'lessons' => $made );
	}

	/**
	 * Add single-choice questions to a quiz lesson. Option A is always correct.
	 *
	 * @return object Quiz row.
	 */
	protected function add_questions( $lesson, $count, $topic = null, $points = 1 ) {
		$quiz = TPD_LMS_Store::quiz_for_lesson( $lesson->id );
		for ( $i = 1; $i <= $count; $i++ ) {
			$qid = TPD_LMS_Store::create_question( $quiz->id, array(
				'prompt' => "Question {$i} " . wp_generate_password( 4, false, false ),
				'topic'  => $topic,
				'points' => $points,
			), array(
				array( 'text' => 'Right', 'is_correct' => true ),
				array( 'text' => 'Wrong', 'is_correct' => false ),
			) );
			$this->assertIsInt( $qid );
		}
		return TPD_LMS_Store::find( 'quizzes', $quiz->id );
	}

	/** Answers that pick the correct option for every drawn question (or the wrong one). */
	protected function answers_for( $attempt, $correct = true ) {
		$answers = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $attempt->quiz_id ) as $q ) {
			if ( ! in_array( (int) $q->id, $attempt->question_order, true ) ) {
				continue;
			}
			foreach ( $q->options as $o ) {
				if ( (bool) (int) $o->is_correct === $correct ) {
					$answers[ (int) $q->id ] = (int) $o->id;
					break;
				}
			}
		}
		return $answers;
	}

	/** Move an enrollment's start back in time. */
	protected function backdate_enrollment( $enrollment, $days ) {
		TPD_LMS_Store::update( 'enrollments', $enrollment->id, array(
			'enrolled_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $days ),
		) );
		return TPD_LMS_Store::find( 'enrollments', $enrollment->id );
	}
}
