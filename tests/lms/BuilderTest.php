<?php
/**
 * Course builder data rules: reordering, editing questions, deleting.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class BuilderTest extends LmsTestCase {

	public function test_editor_formatting_is_kept_and_positioning_removed(): void {
		$html  = '<p style="text-align:center"><span style="font-family: georgia, serif; font-size: 24px; color: #b91c1c; background-color: #fef08a; position: fixed; top: 0; z-index: 99">Hi</span> H<sub>2</sub>O x<sup>2</sup> <s>old</s></p><script>alert(1)</script>';
		$clean = TPD_LMS_Store::clean_html( $html );

		foreach ( array( 'font-family: georgia, serif', 'font-size: 24px', 'color: #b91c1c', 'background-color: #fef08a', 'text-align:center', '<sub>2</sub>', '<sup>2</sup>', '<s>old</s>' ) as $kept ) {
			$this->assertStringContainsString( $kept, $clean );
		}
		foreach ( array( 'position', 'top:', 'z-index', '<script' ) as $gone ) {
			$this->assertStringNotContainsString( $gone, $clean );
		}
		$this->assertStringContainsString( 'position:fixed', wp_kses_post( '<span style="position:fixed">x</span>' ), 'Only the LMS cleaner removes positioning; WordPress itself is unchanged.' );
	}

	public function test_reorder_moves_lessons_between_modules(): void {
		$c        = $this->make_course( array(), array(
			array( 'title' => 'A1', 'chapter' => 1 ),
			array( 'title' => 'A2', 'chapter' => 1 ),
			array( 'title' => 'B1', 'chapter' => 2 ),
		) );
		$chapters = TPD_LMS_Store::chapters_for_course( $c['course']->id );
		list( $a1, $a2, $b1 ) = $c['lessons'];

		$ok = TPD_LMS_Store::reorder( $c['course']->id, array(
			array( 'chapter_id' => $chapters[1]->id, 'lesson_ids' => array( $b1->id, $a2->id ) ),
			array( 'chapter_id' => $chapters[0]->id, 'lesson_ids' => array( $a1->id ) ),
		) );

		$this->assertTrue( $ok );
		$this->assertSame( array( 'B1', 'A2', 'A1' ), array_values( wp_list_pluck( TPD_LMS_Store::lessons_for_course( $c['course']->id ), 'title' ) ) );
		$this->assertSame( (int) $chapters[1]->id, (int) TPD_LMS_Store::find( 'lessons', $a2->id )->chapter_id );
	}

	public function test_reorder_rejects_lessons_or_modules_from_elsewhere_and_missing_items(): void {
		$c     = $this->make_course( array(), array( array( 'title' => 'A1' ), array( 'title' => 'A2' ) ) );
		$other = $this->make_course( array(), array( array( 'title' => 'X1' ) ) );
		$ch    = TPD_LMS_Store::chapters_for_course( $c['course']->id )[0];
		$och   = TPD_LMS_Store::chapters_for_course( $other['course']->id )[0];

		$this->assertTrue( is_wp_error( TPD_LMS_Store::reorder( $c['course']->id, array(
			array( 'chapter_id' => $ch->id, 'lesson_ids' => array( $c['lessons'][0]->id, $c['lessons'][1]->id, $other['lessons'][0]->id ) ),
		) ) ), 'A lesson from another course is refused.' );
		$this->assertTrue( is_wp_error( TPD_LMS_Store::reorder( $c['course']->id, array(
			array( 'chapter_id' => $och->id, 'lesson_ids' => array( $c['lessons'][0]->id, $c['lessons'][1]->id ) ),
		) ) ), 'A module from another course is refused.' );
		$this->assertTrue( is_wp_error( TPD_LMS_Store::reorder( $c['course']->id, array(
			array( 'chapter_id' => $ch->id, 'lesson_ids' => array( $c['lessons'][0]->id ) ),
		) ) ), 'Leaving a lesson out is refused.' );
		$this->assertSame( 'X1', TPD_LMS_Store::lessons_for_course( $other['course']->id )[0]->title, 'The other course is untouched.' );
	}

	public function test_editing_a_question_replaces_its_answers_and_validates(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz = $this->add_questions( $c['lessons'][0], 1 );
		$q    = TPD_LMS_Store::questions_for_quiz( $quiz->id )[0];

		$ok = TPD_LMS_Store::update_question( $q->id, array( 'type' => 'multiple', 'prompt' => 'Pick both', 'topic' => '3' ), array(
			array( 'text' => 'A', 'is_correct' => true ),
			array( 'text' => 'B', 'is_correct' => true ),
			array( 'text' => '   ', 'is_correct' => false ),
		) );
		$this->assertTrue( $ok );
		$q = TPD_LMS_Store::questions_for_quiz( $quiz->id )[0];
		$this->assertSame( 'multiple', $q->type );
		$this->assertSame( 'Module 3', $q->topic );
		$this->assertCount( 2, $q->options, 'Blank answers are dropped.' );

		$this->assertTrue( is_wp_error( TPD_LMS_Store::update_question( $q->id, array( 'type' => 'single', 'prompt' => 'One only' ), array(
			array( 'text' => 'A', 'is_correct' => true ),
			array( 'text' => 'B', 'is_correct' => true ),
		) ) ), 'Single choice with two correct answers is refused.' );
		$this->assertTrue( is_wp_error( TPD_LMS_Store::update_question( $q->id, array( 'type' => 'short', 'prompt' => 'Type it', 'accepted_answers' => '' ) ) ) );

		$ok = TPD_LMS_Store::update_question( $q->id, array( 'type' => 'short', 'prompt' => 'Type it', 'accepted_answers' => "IATA\nI.A.T.A." ) );
		$this->assertTrue( $ok );
		$q = TPD_LMS_Store::questions_for_quiz( $quiz->id )[0];
		$this->assertCount( 0, $q->options, 'Short answers have no options.' );
		$this->assertTrue( TPD_LMS_Quiz::grade( $q, ' iata ' ) );
	}

	public function test_deleting_a_lesson_removes_its_quiz_and_lowers_progress_correctly(): void {
		$c          = $this->make_course( array( 'certificate_enabled' => 0 ), array( array( 'type' => 'text' ), array( 'type' => 'quiz' ) ) );
		$quiz       = $this->add_questions( $c['lessons'][1], 2 );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );
		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][0] );
		$this->assertSame( 50, (int) TPD_LMS_Store::find( 'enrollments', $enrollment->id )->progress_percent );

		TPD_LMS_Store::delete_lesson( $c['lessons'][1]->id );
		TPD_LMS_Progress::recalculate( $enrollment->id );

		$this->assertNull( TPD_LMS_Store::find( 'quizzes', $quiz->id ) );
		$this->assertSame( array(), TPD_LMS_Store::questions_for_quiz( $quiz->id ) );
		$this->assertSame( 100, (int) TPD_LMS_Store::find( 'enrollments', $enrollment->id )->progress_percent );
	}
}
