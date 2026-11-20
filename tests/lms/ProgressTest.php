<?php
/**
 * Enrollment, lesson locks, completion and certificates.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class ProgressTest extends LmsTestCase {

	public function test_enrolling_is_idempotent_and_links_the_paying_transaction(): void {
		$c       = $this->make_course();
		$student = $this->make_user();

		$first  = TPD_LMS_Progress::enroll( $student, $c['course']->id );
		$second = TPD_LMS_Progress::enroll( $student, $c['course']->id, 77 );

		$this->assertSame( $first->id, $second->id );
		$this->assertSame( 77, (int) $second->tx_id );
		$this->assertCount( 1, TPD_LMS_Progress::enrollments_for_user( $student ) );
	}

	public function test_access_days_set_an_expiry_and_expired_access_locks_lessons(): void {
		$c          = $this->make_course( array( 'access_days' => 30 ) );
		$student    = $this->make_user();
		$enrollment = TPD_LMS_Progress::enroll( $student, $c['course']->id );

		$this->assertNotNull( $enrollment->expires_at );
		$this->assertNull( TPD_LMS_Progress::lock_reason( $c['lessons'][0], $enrollment ) );

		TPD_LMS_Store::update( 'enrollments', $enrollment->id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		$enrollment = TPD_LMS_Store::find( 'enrollments', $enrollment->id );
		$this->assertSame( 'Your access to this course has ended.', TPD_LMS_Progress::lock_reason( $c['lessons'][0], $enrollment ) );
	}

	public function test_non_enrolled_users_only_see_previews(): void {
		$c = $this->make_course( array(), array(
			array( 'type' => 'text', 'is_preview' => 1 ),
			array( 'type' => 'text' ),
		) );

		$this->assertNull( TPD_LMS_Progress::lock_reason( $c['lessons'][0], null ) );
		$this->assertSame( 'Enroll to unlock this lesson.', TPD_LMS_Progress::lock_reason( $c['lessons'][1], null ) );
	}

	public function test_drip_lessons_open_after_n_days(): void {
		$c          = $this->make_course( array( 'drip_enabled' => 1 ), array(
			array( 'type' => 'text' ),
			array( 'type' => 'text', 'drip_days' => 7 ),
		) );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$this->assertStringStartsWith( 'Available on ', TPD_LMS_Progress::lock_reason( $c['lessons'][1], $enrollment ) );

		$enrollment = $this->backdate_enrollment( $enrollment, 8 );
		$this->assertNull( TPD_LMS_Progress::lock_reason( $c['lessons'][1], $enrollment ) );
	}

	public function test_sequential_courses_lock_later_lessons(): void {
		$c          = $this->make_course( array( 'sequential' => 1 ), array(
			array( 'type' => 'text', 'title' => 'Welcome' ),
			array( 'type' => 'text', 'title' => 'Second', 'chapter' => 2 ),
		) );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$this->assertSame( 'Complete "Welcome" first.', TPD_LMS_Progress::lock_reason( $c['lessons'][1], $enrollment ) );

		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][0] );
		$this->assertNull( TPD_LMS_Progress::lock_reason( $c['lessons'][1], $enrollment ) );
	}

	public function test_final_exam_waits_for_every_other_lesson(): void {
		$c          = $this->make_course( array(), array(
			array( 'type' => 'text' ),
			array( 'type' => 'video' ),
			array( 'type' => 'exam' ),
		) );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );
		$exam       = $c['lessons'][2];

		$this->assertSame( 'Finish all lessons to unlock the final exam (2 left).', TPD_LMS_Progress::lock_reason( $exam, $enrollment ) );
		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][0] );
		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][1] );
		$this->assertNull( TPD_LMS_Progress::lock_reason( $exam, $enrollment ) );
	}

	public function test_course_managers_are_never_locked_out_but_other_instructors_are(): void {
		$owner = $this->make_user( 'travel_advisor' );
		$c     = $this->make_course( array( 'instructor_id' => $owner, 'sequential' => 1 ), array(
			array( 'type' => 'text' ),
			array( 'type' => 'text' ),
		) );
		$admin    = $this->make_user( 'administrator' );
		$stranger = $this->make_user( 'travel_advisor' );

		$this->assertNull( TPD_LMS_Progress::lock_reason_for_user( $c['lessons'][1], $owner ) );
		$this->assertNull( TPD_LMS_Progress::lock_reason_for_user( $c['lessons'][1], $admin ) );
		$this->assertNotNull( TPD_LMS_Progress::lock_reason_for_user( $c['lessons'][1], $stranger ) );
		$this->assertFalse( TPD_LMS_Store::can_manage_course( $c['course'], $stranger ) );
	}

	public function test_progress_rounds_down_and_completion_is_recorded_once(): void {
		$c          = $this->make_course( array( 'certificate_enabled' => 0 ), array(
			array( 'type' => 'text' ),
			array( 'type' => 'text' ),
			array( 'type' => 'text' ),
		) );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		$completed = 0;
		$hook      = function () use ( &$completed ) {
			$completed++;
		};
		add_action( 'tpd_lms_course_completed', $hook );

		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][0] );
		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][0] ); // Twice: still one completion.
		$this->assertSame( 33, (int) TPD_LMS_Store::find( 'enrollments', $enrollment->id )->progress_percent );

		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][1] );
		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][2] );
		TPD_LMS_Progress::recalculate( $enrollment->id );
		remove_action( 'tpd_lms_course_completed', $hook );

		$row = TPD_LMS_Store::find( 'enrollments', $enrollment->id );
		$this->assertSame( 100, (int) $row->progress_percent );
		$this->assertNotNull( $row->completed_at );
		$this->assertSame( 1, $completed );
		$this->assertNull( TPD_LMS_Progress::certificate_for_enrollment( $enrollment->id ), 'Certificates are off for this course.' );
	}

	public function test_certificate_needs_a_passed_final_exam_and_is_issued_once(): void {
		$c          = $this->make_course( array(), array(
			array( 'type' => 'text' ),
			array( 'type' => 'exam' ),
		) );
		$quiz       = $this->add_questions( $c['lessons'][1], 4 );
		$enrollment = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );

		TPD_LMS_Progress::complete( $enrollment, $c['lessons'][0] );
		$this->assertNull( TPD_LMS_Progress::issue_certificate( $enrollment ), 'No certificate before the exam is passed.' );

		$attempt = TPD_LMS_Quiz::start( $quiz, $enrollment );
		$attempt = TPD_LMS_Quiz::submit( $attempt, $this->answers_for( $attempt ) );
		$this->assertTrue( (bool) $attempt->passed );

		$cert = TPD_LMS_Progress::certificate_for_enrollment( $enrollment->id );
		$this->assertNotNull( $cert );
		$this->assertSame( 100, (int) $cert->final_score );
		$this->assertMatchesRegularExpression( '/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $cert->code );
		$this->assertSame( $cert->id, TPD_LMS_Progress::issue_certificate( $enrollment )->id );
		$this->assertSame( $cert->id, TPD_LMS_Progress::find_certificate( strtolower( $cert->code ) )->id );
	}

	public function test_only_one_final_exam_per_course(): void {
		$c       = $this->make_course( array(), array( array( 'type' => 'exam' ) ) );
		$chapter = TPD_LMS_Store::chapters_for_course( $c['course']->id )[0];

		$second = TPD_LMS_Store::create_lesson( $chapter->id, array( 'type' => 'exam', 'title' => 'Another exam' ) );
		$this->assertWPError( $second );
	}

	public function test_lessons_follow_module_order_then_lesson_order(): void {
		$c = $this->make_course( array(), array(
			array( 'title' => 'B1', 'chapter' => 2 ),
			array( 'title' => 'A1', 'chapter' => 1 ),
			array( 'title' => 'B2', 'chapter' => 2 ),
		) );
		$titles = wp_list_pluck( TPD_LMS_Store::lessons_for_course( $c['course']->id ), 'title' );
		// Module 2 was created first, so it comes first; lessons keep their order inside it.
		$this->assertSame( array( 'B1', 'B2', 'A1' ), array_values( $titles ) );
	}

	protected function assertWPError( $value ): void {
		$this->assertTrue( is_wp_error( $value ), 'Expected a WP_Error.' );
	}
}
