<?php
/**
 * The final exam as a separate card: locked until the course is finished,
 * or unlocked early by an admin (button or unlock key).
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class ExamCardTest extends LmsTestCase {

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', '__return_true' );
		parent::tearDown();
	}

	private function course_with_card( $card = true ) {
		$c = $this->make_course( array( 'exam_card' => $card ? 1 : 0, 'sequential' => 1 ), array(
			array( 'type' => 'text', 'title' => 'One' ),
			array( 'type' => 'text', 'title' => 'Two' ),
			array( 'type' => 'exam', 'title' => 'RTA final exam' ),
		) );
		$this->add_questions( $c['lessons'][2], 2 );
		$user = $this->make_user();
		return array( $c, $user, TPD_LMS_Progress::enroll( $user, $c['course']->id ) );
	}

	public function test_card_is_locked_until_the_course_is_finished(): void {
		list( $c, $user, $e ) = $this->course_with_card();

		$state = TPD_LMS_ExamCard::state( $e );
		$this->assertTrue( $state['locked'] );
		$this->assertSame( 2, $state['lessons_left'] );
		$this->assertSame( 'RTA final exam', $state['title'] );

		TPD_LMS_Progress::complete( $e, $c['lessons'][0] );
		TPD_LMS_Progress::complete( $e, $c['lessons'][1] );
		$state = TPD_LMS_ExamCard::state( TPD_LMS_Store::find( 'enrollments', $e->id ) );
		$this->assertFalse( $state['locked'] );
		$this->assertSame( 'finished', $state['unlocked_how'] );
	}

	public function test_no_card_when_the_course_keeps_the_exam_in_the_curriculum(): void {
		list( , , $e ) = $this->course_with_card( false );
		$this->assertNull( TPD_LMS_ExamCard::state( $e ) );
	}

	public function test_admin_unlock_skips_the_lesson_rules_and_can_be_undone(): void {
		list( $c, $user, $e ) = $this->course_with_card();
		$exam = $c['lessons'][2];
		$this->assertNotNull( TPD_LMS_Progress::lock_reason( $exam, $e ) );

		$e = TPD_LMS_ExamCard::unlock( $e, 1 );
		$this->assertNull( TPD_LMS_Progress::lock_reason( $exam, $e ), 'Sequential order and "finish every lesson" no longer apply.' );
		$this->assertSame( 'admin', TPD_LMS_ExamCard::state( $e )['unlocked_how'] );
		$this->assertNotNull( TPD_LMS_Progress::lock_reason( $c['lessons'][1], $e ), 'Other lessons keep their locks.' );

		$e = TPD_LMS_ExamCard::relock( $e );
		$this->assertNotNull( TPD_LMS_Progress::lock_reason( $exam, $e ) );
	}

	public function test_expired_access_still_locks_an_unlocked_exam(): void {
		list( $c, $user, $e ) = $this->course_with_card();
		$e = TPD_LMS_ExamCard::unlock( $e, 1 );
		TPD_LMS_Store::update( 'enrollments', $e->id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		$this->assertSame( 'Your access to this course has ended.', TPD_LMS_Progress::lock_reason( $c['lessons'][2], TPD_LMS_Store::find( 'enrollments', $e->id ) ) );
	}

	public function test_unlock_key_works_once_for_one_advisor_until_it_expires(): void {
		list( $c, $user, $e ) = $this->course_with_card();
		$other = TPD_LMS_Progress::enroll( $this->make_user(), $c['course']->id );
		delete_transient( 'tpd_lms_unlock_' . $other->id );

		$key = TPD_LMS_ExamCard::create_key( $e, 7 );
		$row = TPD_LMS_Store::find( 'enrollments', $e->id );
		$this->assertMatchesRegularExpression( '/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $key );
		$this->assertStringNotContainsString( str_replace( '-', '', $key ), (string) $row->exam_unlock_key_hash, 'Only a hash is stored.' );

		$this->assertSame( 'bad_key', TPD_LMS_ExamCard::redeem_key( $other, $key )->get_error_code(), 'Another advisor can’t use it.' );

		$e = TPD_LMS_ExamCard::redeem_key( $row, strtolower( str_replace( '-', ' ', $key ) ) );
		$this->assertIsObject( $e );
		$this->assertNotNull( $e->exam_unlocked_at );
		$this->assertNull( $e->exam_unlock_key_hash, 'The key is used up.' );
		$this->assertFalse( TPD_LMS_ExamCard::state( $e )['locked'] );

		// Expired key.
		$e2  = TPD_LMS_ExamCard::relock( $e );
		$key = TPD_LMS_ExamCard::create_key( $e2, 1 );
		TPD_LMS_Store::update( 'enrollments', $e2->id, array( 'exam_unlock_key_expires' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		delete_transient( 'tpd_lms_unlock_' . $e2->id );
		$this->assertSame( 'bad_key', TPD_LMS_ExamCard::redeem_key( TPD_LMS_Store::find( 'enrollments', $e2->id ), $key )->get_error_code() );
		delete_transient( 'tpd_lms_unlock_' . $e2->id );
	}

	public function test_five_wrong_keys_lock_out_for_fifteen_minutes(): void {
		list( $c, $user, $e ) = $this->course_with_card();
		delete_transient( 'tpd_lms_unlock_' . $e->id );
		$key = TPD_LMS_ExamCard::create_key( $e, 7 );
		$e   = TPD_LMS_Store::find( 'enrollments', $e->id );
		for ( $i = 0; $i < 5; $i++ ) {
			TPD_LMS_ExamCard::redeem_key( $e, 'WRNG-WRNG' );
		}
		$this->assertSame( 'locked_out', TPD_LMS_ExamCard::redeem_key( $e, $key )->get_error_code(), 'Even the right key waits out the lockout.' );
		delete_transient( 'tpd_lms_unlock_' . $e->id );
	}

	public function test_hub_data_carries_the_card_and_passing_shows_on_it(): void {
		list( $c, $user, $e ) = $this->course_with_card();
		$hub = TPD_LMS_Student::hub_enrollments( $user );
		$this->assertTrue( $hub[0]['exam_card']['locked'] );

		$e    = TPD_LMS_ExamCard::unlock( $e, 1 );
		$quiz = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][2]->id );
		$att  = TPD_LMS_Quiz::start( $quiz, $e );
		TPD_LMS_Quiz::submit( $att, $this->answers_for( $att ) );

		$state = TPD_LMS_ExamCard::state( TPD_LMS_Store::find( 'enrollments', $e->id ) );
		$this->assertTrue( $state['passed'] );
		$this->assertSame( 100, $state['best_score'] );
		$this->assertNull( TPD_LMS_Progress::certificate_for_enrollment( $e->id ), 'No certificate until every lesson is done as well.' );
	}
}
