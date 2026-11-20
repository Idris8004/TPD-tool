<?php
/**
 * Grading, attempts, deadlines and question-bank draws.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class QuizTest extends LmsTestCase {

	public function test_grading_rules_for_each_question_type(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][0]->id );

		TPD_LMS_Store::create_question( $quiz->id, array( 'type' => 'multiple', 'prompt' => 'Pick two' ), array(
			array( 'text' => 'A', 'is_correct' => true ),
			array( 'text' => 'B', 'is_correct' => true ),
			array( 'text' => 'C', 'is_correct' => false ),
		) );
		TPD_LMS_Store::create_question( $quiz->id, array(
			'type'             => 'short',
			'prompt'           => 'FIT stands for?',
			'accepted_answers' => "Flexible independent travel\nFree independent traveller",
		) );
		TPD_LMS_Store::create_question( $quiz->id, array( 'type' => 'true_false', 'prompt' => 'True?' ), array(
			array( 'text' => 'True', 'is_correct' => true ),
			array( 'text' => 'False', 'is_correct' => false ),
		) );
		list( $multi, $short, $tf ) = TPD_LMS_Store::questions_for_quiz( $quiz->id );
		list( $a, $b, $cc )         = $multi->options;

		$this->assertTrue( TPD_LMS_Quiz::grade( $multi, array( $b->id, $a->id ) ) );
		$this->assertTrue( TPD_LMS_Quiz::grade( $multi, array( (string) $a->id, $b->id, $a->id ) ), 'Duplicates and strings are fine.' );
		$this->assertFalse( TPD_LMS_Quiz::grade( $multi, array( $a->id ) ), 'Multiple select is all or nothing.' );
		$this->assertFalse( TPD_LMS_Quiz::grade( $multi, array( $a->id, $b->id, $cc->id ) ) );

		$this->assertTrue( TPD_LMS_Quiz::grade( $short, '  free   INDEPENDENT traveller ' ) );
		$this->assertFalse( TPD_LMS_Quiz::grade( $short, 'independent' ) );
		$this->assertFalse( TPD_LMS_Quiz::grade( $short, array( 'flexible independent travel' ) ) );

		$this->assertTrue( TPD_LMS_Quiz::grade( $tf, $tf->options[0]->id ) );
		$this->assertFalse( TPD_LMS_Quiz::grade( $tf, $tf->options[1]->id ) );
		$this->assertFalse( TPD_LMS_Quiz::grade( $tf, '' ) );
		$this->assertFalse( TPD_LMS_Quiz::grade( $tf, null ) );
	}

	public function test_question_builder_validates_correct_answers(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][0]->id );

		$this->assertTrue( is_wp_error( TPD_LMS_Store::create_question( $quiz->id, array( 'prompt' => '' ) ) ) );
		$this->assertTrue( is_wp_error( TPD_LMS_Store::create_question( $quiz->id, array( 'prompt' => 'No right answer' ), array(
			array( 'text' => 'A' ),
			array( 'text' => 'B' ),
		) ) ) );
		$this->assertTrue( is_wp_error( TPD_LMS_Store::create_question( $quiz->id, array( 'prompt' => 'One option' ), array(
			array( 'text' => 'A', 'is_correct' => true ),
		) ) ) );
	}

	public function test_score_is_a_percentage_of_points_and_passing_completes_the_lesson(): void {
		$c          = $this->make_course( array(), array( array( 'type' => 'quiz' ), array( 'type' => 'text' ) ) );
		$quiz       = $this->add_questions( $c['lessons'][0], 3, null, 1 );
		$quiz       = $this->add_questions( $c['lessons'][0], 1, null, 3 ); // One question worth 3 points.
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		// Only the 3-point question right: 3 of 6 points = 50%, below the 70% pass mark.
		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$answers = $this->answers_for( $attempt, false );
		$heavy   = end( $attempt->question_order );
		$answers[ $heavy ] = $this->answers_for( $attempt, true )[ $heavy ];
		$attempt = TPD_LMS_Quiz::submit( $attempt, $answers );

		$this->assertSame( 50, (int) $attempt->score );
		$this->assertFalse( (bool) $attempt->passed );
		$this->assertNotContains( (int) $c['lessons'][0]->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ), 'Failing does not complete the quiz.' );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$attempt = TPD_LMS_Quiz::submit( $attempt, $this->answers_for( $attempt ) );
		$this->assertSame( 100, (int) $attempt->score );
		$this->assertContains( (int) $c['lessons'][0]->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ) );
		$this->assertSame( 50, (int) TPD_LMS_Store::find( 'enrollments', $enrollment->id )->progress_percent );
	}

	public function test_submitting_twice_grades_once(): void {
		$c          = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz       = $this->add_questions( $c['lessons'][0], 2 );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$first   = TPD_LMS_Quiz::submit( $attempt, $this->answers_for( $attempt, false ) );
		$second  = TPD_LMS_Quiz::submit( $attempt, $this->answers_for( $attempt, true ) );

		$this->assertSame( 0, (int) $second->score, 'The second submit cannot change the result.' );
		$this->assertSame( $first->submitted_at, $second->submitted_at );
		$this->assertSame( 1, TPD_LMS_Quiz::attempts_used( $quiz, $enrollment ) );
	}

	public function test_attempt_limit_and_resuming_an_open_attempt(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 2 );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'max_attempts' => 1 ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $quiz->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$this->assertSame( $attempt->id, TPD_LMS_Quiz::start( $quiz, $enrollment )->id, 'Starting again resumes the open attempt.' );

		TPD_LMS_Quiz::submit( $attempt, $this->answers_for( $attempt, false ) );
		$this->assertFalse( TPD_LMS_Quiz::can_attempt( $quiz, $enrollment ) );
		$this->assertTrue( is_wp_error( TPD_LMS_Quiz::start( $quiz, $enrollment ) ) );
	}

	public function test_answers_after_the_deadline_are_ignored(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 2 );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'time_limit_minutes' => 10 ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $quiz->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$this->assertNotNull( $attempt->expires_at );

		// One right answer saved in time.
		$right = $this->answers_for( $attempt, true );
		$first = $attempt->question_order[0];
		$this->assertTrue( TPD_LMS_Quiz::save_answers( $attempt, array( $first => $right[ $first ] ) ) );

		// Time runs out. A late request with every answer right is graded on what was saved.
		TPD_LMS_Store::update( 'quiz_attempts', $attempt->id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 120 ) ) );
		$attempt = TPD_LMS_Quiz::get_attempt( $attempt->id );
		$this->assertFalse( TPD_LMS_Quiz::save_answers( $attempt, $right ), 'Autosave after the deadline is refused.' );
		$attempt = TPD_LMS_Quiz::submit( $attempt, $right );

		$this->assertSame( 50, (int) $attempt->score );
	}

	public function test_an_expired_open_attempt_is_graded_when_the_student_returns(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 2 );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'time_limit_minutes' => 5 ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $quiz->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$old = TPD_LMS_Quiz::start( $quiz, $enrollment );
		TPD_LMS_Store::update( 'quiz_attempts', $old->id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ) );

		$new = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$this->assertNotSame( $old->id, $new->id );
		$this->assertNotNull( TPD_LMS_Quiz::get_attempt( $old->id )->submitted_at );
	}

	public function test_answers_to_questions_not_drawn_are_dropped(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 6 );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'questions_per_attempt' => 3 ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $quiz->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$this->assertCount( 3, $attempt->question_order );

		$all = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id ) as $q ) {
			$all[ (int) $q->id ] = (int) $q->options[0]->id;
		}
		$attempt = TPD_LMS_Quiz::submit( $attempt, $all );

		$this->assertSame( 3, (int) $attempt->points_total, 'Scores count only the drawn questions.' );
		$this->assertCount( 3, $attempt->answers );
	}

	public function test_each_attempt_draws_a_balanced_random_set_from_the_bank(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$this->add_questions( $c['lessons'][0], 6, 'Module 1' );
		$this->add_questions( $c['lessons'][0], 3, 'Module 2' );
		$quiz = $this->add_questions( $c['lessons'][0], 1, 'Module 3' );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'questions_per_attempt' => 5 ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $quiz->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$topics = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id, false ) as $q ) {
			$topics[ (int) $q->id ] = $q->topic;
		}

		// 6/10, 3/10 and 1/10 of 5 = 3, 1.5 and 0.5: Module 1 gets 3, the rest go by largest remainder.
		for ( $run = 0; $run < 10; $run++ ) {
			$drawn  = TPD_LMS_Quiz::draw_questions( $quiz, $enrollment );
			$counts = array_count_values( array_map( function ( $id ) use ( $topics ) {
				return $topics[ $id ];
			}, $drawn ) );
			$this->assertCount( 5, $drawn );
			$this->assertSame( 3, $counts['Module 1'] );
			$this->assertSame( 2, ( $counts['Module 2'] ?? 0 ) + ( $counts['Module 3'] ?? 0 ) );
			$this->assertGreaterThanOrEqual( 1, $counts['Module 2'] );
		}
	}

	public function test_retakes_get_unseen_questions_first(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 6 );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'questions_per_attempt' => 3 ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $quiz->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$first  = TPD_LMS_Quiz::start( $quiz, $enrollment );
		TPD_LMS_Quiz::submit( $first, array() );
		$second = TPD_LMS_Quiz::start( $quiz, $enrollment );

		$this->assertSame( array(), array_values( array_intersect( $first->question_order, $second->question_order ) ) );
	}

	public function test_blank_or_oversized_draw_uses_the_whole_bank(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 4 );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$this->assertCount( 4, TPD_LMS_Quiz::draw_questions( $quiz, $enrollment ) );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'questions_per_attempt' => 50 ) );
		$this->assertCount( 4, TPD_LMS_Quiz::draw_questions( TPD_LMS_Store::find( 'quizzes', $quiz->id ), $enrollment ) );
	}

	public function test_topics_are_normalised(): void {
		$this->assertSame( 'Module 4', TPD_LMS_Store::normalize_topic( '4' ) );
		$this->assertSame( 'Module 4', TPD_LMS_Store::normalize_topic( ' module   4 ' ) );
		$this->assertSame( 'Module 4', TPD_LMS_Store::normalize_topic( 'Module 4: Client discovery' ) );
		$this->assertSame( '4 Seasons', TPD_LMS_Store::normalize_topic( '4 Seasons' ) );
		$this->assertSame( 'Cruise basics', TPD_LMS_Store::normalize_topic( '  Cruise   basics ' ) );
	}

	public function test_lesson_and_question_html_is_cleaned_on_save(): void {
		$c      = $this->make_course( array(), array( array(
			'type'    => 'text',
			'content' => '<p>Hello <strong>advisors</strong></p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
		) ) );
		$lesson = $c['lessons'][0];

		$this->assertStringContainsString( '<strong>advisors</strong>', $lesson->content );
		$this->assertStringNotContainsString( '<script', $lesson->content );
		$this->assertStringNotContainsString( 'javascript:', $lesson->content );
	}
}
