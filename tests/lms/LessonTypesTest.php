<?php
/**
 * The 12 lesson types: settings, surveys, assignments, completion rules.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class LessonTypesTest extends LmsTestCase {

	private $attachments = array();

	protected function tearDown(): void {
		foreach ( $this->attachments as $id ) {
			wp_delete_attachment( $id, true );
		}
		$this->attachments = array();
		parent::tearDown();
	}

	private function attachment( $name = 'file.pdf', $mime = 'application/pdf' ) {
		$id = wp_insert_attachment( array( 'post_title' => $name, 'post_mime_type' => $mime, 'post_status' => 'inherit' ) );
		$this->attachments[] = $id;
		return $id;
	}

	public function test_every_type_can_be_created_and_quiz_types_get_a_quiz(): void {
		$c = $this->make_course( array(), array_map( function ( $t ) {
			return array( 'type' => $t, 'title' => ucfirst( $t ) );
		}, TPD_LMS_Store::LESSON_TYPES ) );

		$this->assertCount( 12, $c['lessons'] );
		foreach ( $c['lessons'] as $l ) {
			$has_quiz = (bool) TPD_LMS_Store::quiz_for_lesson( $l->id );
			$this->assertSame( in_array( $l->type, TPD_LMS_Store::QUIZ_TYPES, true ), $has_quiz, $l->type );
		}
		$survey = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][ array_search( 'survey', TPD_LMS_Store::LESSON_TYPES, true ) ]->id );
		$this->assertSame( array( 0, 0, 1 ), array( (int) $survey->passing_score, (int) $survey->show_answers, (int) $survey->max_attempts ) );
	}

	public function test_settings_are_cleaned_per_type(): void {
		$this->assertSame( '', TPD_LMS_Store::sanitize_settings( 'multimedia', array( 'embed_url' => 'http://insecure.example.com' ) )['embed_url'], 'Embeds must be https.' );
		$this->assertSame( '', TPD_LMS_Store::sanitize_settings( 'multimedia', array( 'embed_url' => 'javascript:alert(1)' ) )['embed_url'] );
		$this->assertSame( 300, TPD_LMS_Store::sanitize_settings( 'multimedia', array( 'height' => 50 ) )['height'] );
		$this->assertSame( array( 'allow_download' => true ), TPD_LMS_Store::sanitize_settings( 'pdf', array( 'bogus' => 1 ) ), 'Unknown keys are dropped; PDFs are downloadable by default.' );
		$this->assertSame( 20, TPD_LMS_Store::sanitize_settings( 'assignment', array( 'max_mb' => 500 ) )['max_mb'] );
		$this->assertSame( 'either', TPD_LMS_Store::sanitize_settings( 'assignment', array( 'submission' => 'video' ) )['submission'] );

		$pdf = $this->attachment();
		$files = TPD_LMS_Store::sanitize_settings( 'download', array( 'files' => array( $pdf, $pdf, 999999999, 'x' ) ) )['files'];
		$this->assertSame( array( $pdf ), $files, 'Only real attachments, once each.' );

		$live = TPD_LMS_Store::sanitize_settings( 'live', array( 'starts_local' => '2030-03-04T15:00', 'timezone' => 'America/New_York', 'join_url' => 'https://zoom.us/j/123' ) );
		$this->assertSame( '2030-03-04 20:00:00', $live['starts_at'], 'Stored in UTC.' );
		$this->assertSame( 'UTC', TPD_LMS_Store::sanitize_settings( 'live', array( 'timezone' => 'Mars/Olympus' ) )['timezone'] );

		$deck = TPD_LMS_Store::sanitize_settings( 'presentation', array( 'slides' => array( array( 'audio_id' => 999999999, 'text' => '<b>Hi</b><script>x</script>' ) ) ) );
		$this->assertNull( $deck['slides'][0]['audio_id'], 'Missing narration files are dropped.' );
		$this->assertSame( '<b>Hi</b>x', $deck['slides'][0]['text'] );
		$this->assertTrue( $deck['require_all_slides'] );
		$this->assertFalse( $deck['auto_advance'] );
	}

	public function test_settings_round_trip_and_type_is_fixed(): void {
		$c = $this->make_course( array(), array( array( 'type' => 'multimedia' ) ) );
		$l = $c['lessons'][0];
		TPD_LMS_Store::update_lesson( $l->id, array( 'type' => 'text', 'settings' => array( 'embed_url' => 'https://form.typeform.com/to/abc', 'height' => 700 ) ) );
		$l = TPD_LMS_Store::find( 'lessons', $l->id );

		$this->assertSame( 'multimedia', $l->type, 'A lesson can’t change type.' );
		$this->assertSame( array( 'embed_url' => 'https://form.typeform.com/to/abc', 'height' => 700 ), TPD_LMS_Store::lesson_settings( $l ) );
	}

	public function test_survey_questions_need_no_right_answer_and_submitting_completes_the_lesson(): void {
		$c          = $this->make_course( array( 'certificate_enabled' => 0 ), array( array( 'type' => 'survey' ), array( 'type' => 'text' ) ) );
		$quiz       = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][0]->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$q1 = TPD_LMS_Store::create_question( $quiz->id, array( 'type' => 'single', 'prompt' => 'How useful was this?' ), array( array( 'text' => 'Very' ), array( 'text' => 'Somewhat' ) ) );
		$q2 = TPD_LMS_Store::create_question( $quiz->id, array( 'type' => 'short', 'prompt' => 'What should we add?' ) );
		$this->assertIsInt( $q1 );
		$this->assertIsInt( $q2, 'Written survey answers need no accepted answers.' );
		$this->assertTrue( is_wp_error( TPD_LMS_Store::create_question( $quiz->id, array( 'type' => 'single', 'prompt' => 'One choice' ), array( array( 'text' => 'Only' ) ) ) ) );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$opts    = TPD_LMS_Store::questions_for_quiz( $quiz->id )[0]->options;
		$attempt = TPD_LMS_Quiz::submit( $attempt, array( $q1 => $opts[1]->id, $q2 => 'More examples' ) );

		$this->assertTrue( (bool) $attempt->passed );
		$this->assertContains( (int) $c['lessons'][0]->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ) );
		$this->assertFalse( TPD_LMS_Quiz::can_attempt( $quiz, $enrollment ), 'One response per advisor.' );

		$report = TPD_LMS_Reports::quizzes( $c['course']->id );
		$this->assertTrue( $report[0]['is_survey'] );
		$this->assertSame( 1, $report[0]['responses'] );
		$this->assertSame( array( 0, 1 ), array_column( $report[0]['questions'][0]['options'], 'count' ) );
		$this->assertSame( array( 'More examples' ), $report[0]['questions'][1]['texts'] );
	}

	public function test_assignment_needs_approval_then_completes_and_returned_work_can_be_resent(): void {
		$c          = $this->make_course( array( 'certificate_enabled' => 0 ), array( array( 'type' => 'assignment' ), array( 'type' => 'text' ) ) );
		$lesson     = $c['lessons'][0];
		TPD_LMS_Store::update_lesson( $lesson->id, array( 'settings' => array( 'submission' => 'text', 'require_approval' => true ) ) );
		$lesson     = TPD_LMS_Store::find( 'lessons', $lesson->id );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );
		add_filter( 'pre_wp_mail', '__return_true' );

		$this->assertSame( 'text_required', TPD_LMS_Assign::submit( $lesson, $enrollment, '   ', null )->get_error_code() );
		$sub = TPD_LMS_Assign::submit( $lesson, $enrollment, '<p>My client intake plan</p><script>x</script>', null );
		$this->assertSame( 'pending', $sub->status );
		$this->assertStringNotContainsString( '<script', $sub->answer_text );
		$this->assertNotContains( (int) $lesson->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ), 'Not complete until approved.' );
		$this->assertSame( 'already_submitted', TPD_LMS_Assign::submit( $lesson, $enrollment, 'Again', null )->get_error_code() );

		$this->assertSame( 'feedback_required', TPD_LMS_Assign::review( $sub, 'return', '' )->get_error_code() );
		$sub = TPD_LMS_Assign::review( $sub, 'return', 'Add a budget section.' );
		$this->assertSame( 'returned', $sub->status );

		$sub2 = TPD_LMS_Assign::submit( $lesson, $enrollment, 'Now with a budget section', null );
		$this->assertSame( 'pending', $sub2->status, 'Returned work can be sent again.' );
		TPD_LMS_Assign::review( $sub2, 'approve', 'Great work.' );
		$this->assertContains( (int) $lesson->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ) );
		remove_filter( 'pre_wp_mail', '__return_true' );
	}

	public function test_deleting_the_lesson_removes_its_submissions(): void {
		$c          = $this->make_course( array(), array( array( 'type' => 'assignment' ) ) );
		$lesson     = $c['lessons'][0];
		TPD_LMS_Store::update_lesson( $lesson->id, array( 'settings' => array( 'submission' => 'text' ) ) );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );
		add_filter( 'pre_wp_mail', '__return_true' );
		$sub = TPD_LMS_Assign::submit( TPD_LMS_Store::find( 'lessons', $lesson->id ), $enrollment, 'Answer', null );
		remove_filter( 'pre_wp_mail', '__return_true' );

		TPD_LMS_Store::delete_lesson( $lesson->id );
		$this->assertNull( TPD_LMS_Store::find( 'assignment_submissions', $sub->id ) );
	}

	public function test_assignment_without_approval_completes_on_submission(): void {
		$c          = $this->make_course( array( 'certificate_enabled' => 0 ), array( array( 'type' => 'assignment' ) ) );
		$lesson     = $c['lessons'][0];
		TPD_LMS_Store::update_lesson( $lesson->id, array( 'settings' => array( 'submission' => 'either', 'require_approval' => false ) ) );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );
		add_filter( 'pre_wp_mail', '__return_true' );

		$sub = TPD_LMS_Assign::submit( TPD_LMS_Store::find( 'lessons', $lesson->id ), $enrollment, 'Done', null );
		remove_filter( 'pre_wp_mail', '__return_true' );
		$this->assertSame( 'approved', $sub->status );
		$this->assertContains( (int) $lesson->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ) );
	}

	public function test_private_folder_refuses_direct_access(): void {
		$dir = TPD_LMS_Assign::private_dir();
		$this->assertFileExists( $dir . '/.htaccess' );
		$this->assertStringContainsString( 'Require all denied', file_get_contents( $dir . '/.htaccess' ) );
		$this->assertFileExists( $dir . '/index.php' );
	}

	public function test_only_viewing_types_can_be_marked_complete(): void {
		$this->assertSame(
			array( 'video', 'text', 'pdf', 'audio', 'download', 'multimedia', 'presentation', 'live' ),
			TPD_LMS_Store::MARKABLE_TYPES
		);
		foreach ( array( 'quiz', 'exam', 'survey', 'assignment' ) as $t ) {
			$this->assertNotContains( $t, TPD_LMS_Store::MARKABLE_TYPES );
		}
	}
}
