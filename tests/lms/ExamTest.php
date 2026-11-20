<?php
/**
 * Coupons, exam passwords and codes, proctoring, and emails.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class ExamTest extends LmsTestCase {

	private $mails = array();
	private $coupon_ids = array();

	protected function setUp(): void {
		parent::setUp();
		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		foreach ( $this->coupon_ids as $id ) {
			TPD_LMS_Store::delete( 'coupons', $id );
		}
		parent::tearDown();
	}

	public function capture_mail( $return, $atts ) {
		$this->mails[] = $atts;
		return true;
	}

	private function mails_to( $email ) {
		return array_values( array_filter( $this->mails, function ( $m ) use ( $email ) {
			return in_array( $email, (array) $m['to'], true );
		} ) );
	}

	private function exam_course( $mode = 'code' ) {
		$c    = $this->make_course( array(), array( array( 'type' => 'text' ), array( 'type' => 'exam' ) ) );
		$quiz = $this->add_questions( $c['lessons'][1], 2 );
		TPD_LMS_Store::update_quiz( $quiz->id, array( 'require_all_lessons' => 0 ) );
		if ( 'password' === $mode ) {
			TPD_LMS_Exam::save_access( $quiz->id, 'password', 'Sail-Away 7' );
		} elseif ( 'code' === $mode ) {
			TPD_LMS_Exam::save_access( $quiz->id, 'code' );
		}
		$student    = $this->make_user();
		$enrollment = TPD_LMS_Progress::enroll( $student, $c['course']->id );
		return array( $c, TPD_LMS_Store::find( 'quizzes', $quiz->id ), $student, $enrollment );
	}

	/* Coupons ------------------------------------------------------------ */

	private function coupon( array $data ) {
		$id = TPD_LMS_Coupons::create( $data + array( 'code' => 'T' . wp_generate_password( 6, false, false ), 'type' => 'percent', 'value' => 20 ) );
		$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
		$this->coupon_ids[] = $id;
		return TPD_LMS_Store::find( 'coupons', $id );
	}

	public function test_coupon_rules_and_checkout_price(): void {
		$c       = $this->make_course( array( 'price' => 200 ) );
		$other   = $this->make_course( array( 'price' => 50 ) );
		$student = $this->make_user();

		$pct = $this->coupon( array( 'value' => 25 ) );
		$r   = TPD_LMS_Student::resolve_checkout_item( $student, $c['course']->slug, 'USD', strtolower( $pct->code ) );
		$this->assertSame( 150.0, $r['amount'], 'Codes are not case-sensitive; 25% off 200.' );
		$this->assertSame( (int) $pct->id, $r['extra']['coupon_id'] );

		$fixed = $this->coupon( array( 'type' => 'fixed', 'value' => 500 ) );
		$this->assertSame( 0.0, TPD_LMS_Student::resolve_checkout_item( $student, $c['course']->slug, 'USD', $fixed->code )['amount'], 'A discount never goes below zero.' );

		$scoped = $this->coupon( array( 'course_id' => $other['course']->id ) );
		$this->assertSame( 'bad_coupon', TPD_LMS_Student::resolve_checkout_item( $student, $c['course']->slug, 'USD', $scoped->code )->get_error_code(), 'A coupon for another course is refused at payment.' );

		$expired = $this->coupon( array( 'expires_at' => gmdate( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ) ) );
		$this->assertNotNull( TPD_LMS_Coupons::unusable_reason( $expired, $c['course'] ) );
		$today = $this->coupon( array( 'expires_at' => wp_date( 'Y-m-d' ) ) );
		$this->assertNull( TPD_LMS_Coupons::unusable_reason( $today, $c['course'] ), 'It works through the end of its last day.' );

		$limited = $this->coupon( array( 'max_uses' => 1 ) );
		TPD_LMS_Coupons::record_use( $limited->id );
		$this->assertSame( 'That coupon has been used up.', TPD_LMS_Coupons::unusable_reason( TPD_LMS_Store::find( 'coupons', $limited->id ), $c['course'] ) );

		TPD_LMS_Store::update( 'coupons', $pct->id, array( 'active' => 0 ) );
		$this->assertSame( 'That coupon is no longer active.', TPD_LMS_Coupons::unusable_reason( TPD_LMS_Store::find( 'coupons', $pct->id ), $c['course'] ) );

		$this->assertTrue( is_wp_error( TPD_LMS_Coupons::create( array( 'code' => $fixed->code, 'type' => 'percent', 'value' => 10 ) ) ), 'Codes are unique.' );
		$this->assertTrue( is_wp_error( TPD_LMS_Coupons::create( array( 'code' => 'BIGPCT', 'type' => 'percent', 'value' => 120 ) ) ) );
	}

	public function test_a_coupon_use_is_counted_once_on_payment(): void {
		$c       = $this->make_course( array( 'price' => 100 ) );
		$student = $this->make_user();
		$cp      = $this->coupon( array() );
		$tx      = (object) array( 'id' => 900, 'user_id' => $student, 'item_id' => $c['course']->slug, 'meta_json' => wp_json_encode( array( 'extra' => array( 'coupon_id' => (int) $cp->id ) ) ) );

		TPD_LMS_Student::fulfill( $tx );
		$this->assertSame( 1, (int) TPD_LMS_Store::find( 'coupons', $cp->id )->uses );

		$tx->meta_json = wp_json_encode( array( 'extra' => array( 'coupon_id' => (int) $cp->id ), 'fulfilled_at' => '2026-01-01 00:00:00' ) );
		TPD_LMS_Student::fulfill( $tx );
		$this->assertSame( 1, (int) TPD_LMS_Store::find( 'coupons', $cp->id )->uses, 'An already-fulfilled transaction does not count again.' );
	}

	/* Encryption --------------------------------------------------------- */

	public function test_secrets_are_encrypted_and_tamper_proof(): void {
		$stored = TPD_LMS_Exam::encrypt( 'Sail-Away 7' );
		$this->assertStringNotContainsString( 'Sail', $stored );
		$this->assertSame( 'Sail-Away 7', TPD_LMS_Exam::decrypt( $stored ) );
		$this->assertNotSame( $stored, TPD_LMS_Exam::encrypt( 'Sail-Away 7' ), 'Each encryption is different.' );
		$tampered = substr( $stored, 0, -2 ) . ( 'A' === substr( $stored, -2, 1 ) ? 'B' : 'A' ) . substr( $stored, -1 );
		$this->assertNull( TPD_LMS_Exam::decrypt( $tampered ) );
		$this->assertNull( TPD_LMS_Exam::decrypt( 'plain text' ) );
	}

	/* Shared password ---------------------------------------------------- */

	public function test_password_is_case_sensitive_and_five_wrong_tries_lock_out(): void {
		list( $c, $quiz, $student, $e ) = $this->exam_course( 'password' );
		delete_transient( 'tpd_lms_exam_' . $student . '_' . $quiz->id );

		$this->assertSame( 'need_access', TPD_LMS_Exam::start( $quiz, $e, $student, '' )->get_error_code() );
		$this->assertSame( 'bad_access', TPD_LMS_Exam::start( $quiz, $e, $student, 'sail-away 7' )->get_error_code() );
		$attempt = TPD_LMS_Exam::start( $quiz, $e, $student, '  Sail-Away 7 ' );
		$this->assertIsObject( $attempt );
		$this->assertSame( $attempt->id, TPD_LMS_Exam::start( $quiz, $e, $student, '' )->id, 'Resuming needs no password.' );
		TPD_LMS_Quiz::submit( $attempt, array() );

		for ( $i = 0; $i < 5; $i++ ) {
			TPD_LMS_Exam::start( $quiz, $e, $student, 'wrong' );
		}
		$locked = TPD_LMS_Exam::start( $quiz, $e, $student, 'Sail-Away 7' );
		$this->assertSame( 'locked_out', $locked->get_error_code(), 'Even the right password waits out the lockout.' );
		delete_transient( 'tpd_lms_exam_' . $student . '_' . $quiz->id );
	}

	public function test_course_staff_skip_the_check(): void {
		list( $c, $quiz ) = $this->exam_course( 'password' );
		$admin = $this->make_user( 'administrator' );
		$e     = TPD_LMS_Progress::enroll( $admin, $c['course']->id );
		$this->assertIsObject( TPD_LMS_Exam::start( $quiz, $e, $admin, '' ) );
	}

	/* One-time codes ----------------------------------------------------- */

	public function test_a_code_works_once_for_one_student_until_it_expires(): void {
		list( $c, $quiz, $student, $e ) = $this->exam_course( 'code' );
		$other   = $this->make_user();
		$other_e = TPD_LMS_Progress::enroll( $other, $c['course']->id );
		delete_transient( 'tpd_lms_exam_' . $other . '_' . $quiz->id );

		$code = TPD_LMS_Exam::create_code( $quiz->id, $e->id, 24 );
		$row  = TPD_LMS_Store::find( 'exam_access_codes', $code['id'] );
		$this->assertMatchesRegularExpression( '/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', $code['code'] );
		$this->assertStringNotContainsString( str_replace( '-', '', $code['code'] ), $row->code_hash . $row->code_enc, 'The code is never stored in plain text.' );

		$this->assertSame( 'bad_access', TPD_LMS_Exam::start( $quiz, $other_e, $other, $code['code'] )->get_error_code(), 'Another student can’t use it.' );

		$attempt = TPD_LMS_Exam::start( $quiz, $e, $student, strtolower( str_replace( '-', ' ', $code['code'] ) ) );
		$this->assertIsObject( $attempt, 'Case and separators don’t matter.' );
		$row = TPD_LMS_Store::find( 'exam_access_codes', $code['id'] );
		$this->assertSame( (int) $attempt->id, (int) $row->quiz_attempt_id );
		$this->assertSame( 'used', TPD_LMS_Exam::code_status( $row ) );
		TPD_LMS_Quiz::submit( $attempt, array() );

		delete_transient( 'tpd_lms_exam_' . $student . '_' . $quiz->id );
		$this->assertSame( 'bad_access', TPD_LMS_Exam::start( $quiz, $e, $student, $code['code'] )->get_error_code(), 'It works only once.' );

		$old = TPD_LMS_Exam::create_code( $quiz->id, $e->id, 24 );
		TPD_LMS_Store::update( 'exam_access_codes', $old['id'], array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), false );
		$this->assertSame( 'bad_access', TPD_LMS_Exam::start( $quiz, $e, $student, $old['code'] )->get_error_code(), 'Expired codes fail.' );

		$cancelled = TPD_LMS_Exam::create_code( $quiz->id, $e->id, 24 );
		TPD_LMS_Exam::revoke_code( $cancelled['id'] );
		$this->assertSame( 'bad_access', TPD_LMS_Exam::start( $quiz, $e, $student, $cancelled['code'] )->get_error_code(), 'Cancelled codes fail.' );
		delete_transient( 'tpd_lms_exam_' . $student . '_' . $quiz->id );
	}

	/* Proctoring --------------------------------------------------------- */

	public function test_proctor_request_approval_emails_and_private_page(): void {
		list( $c, $quiz, $student, $e ) = $this->exam_course( 'code' );
		$user = get_userdata( $student );

		$this->assertSame( 'self_proctor', TPD_LMS_Exam::request_proctor( $quiz, $e, array( 'proctor_name' => 'Me', 'proctor_email' => $user->user_email ) )->get_error_code() );
		$request = TPD_LMS_Exam::request_proctor( $quiz, $e, array(
			'proctor_name'  => 'Pat Proctor',
			'proctor_email' => 'pat@example.test',
			'preferred_at'  => '2030-03-04T15:00',
			'timezone'      => 'America/New_York',
		) );
		$this->assertIsObject( $request );
		$this->assertSame( '2030-03-04 20:00:00', $request->preferred_at, 'Stored in UTC.' );
		$this->assertCount( 1, $this->mails_to( TPD_LMS_Mail::support_email() ), 'Support is told about new requests.' );
		$this->assertSame( 'already_requested', TPD_LMS_Exam::request_proctor( $quiz, $e, array( 'proctor_name' => 'X', 'proctor_email' => 'x@example.test' ) )->get_error_code() );

		$this->assertSame( 'reason_required', TPD_LMS_Exam::decline( $request, '  ' )->get_error_code() );
		$res = TPD_LMS_Exam::approve( $request, 24, 'Bring photo ID.' );
		$this->assertTrue( $res['emails_sent'] );

		$to_proctor = $this->mails_to( 'pat@example.test' );
		$this->assertCount( 1, $to_proctor );
		$this->assertMatchesRegularExpression( '~/proctor/([A-Za-z0-9]{48})/~', $to_proctor[0]['message'] );
		preg_match( '~/proctor/([A-Za-z0-9]{48})/~', $to_proctor[0]['message'], $m );
		$token = $m[1];

		$to_student = $this->mails_to( $user->user_email );
		$this->assertCount( 2, $to_student, 'The enrollment email, then the approval.' );
		$approval = end( $to_student );
		$this->assertSame( 'Your proctor request was approved', $approval['subject'] );
		$request = TPD_LMS_Store::find( 'proctor_requests', $request->id );
		$code    = TPD_LMS_Exam::decrypt( TPD_LMS_Store::find( 'exam_access_codes', $request->exam_access_code_id )->code_enc );
		$this->assertStringNotContainsString( $code, $approval['message'], 'The student never sees the code.' );
		$this->assertStringNotContainsString( $code, $to_proctor[0]['message'], 'The code is on the private page, not in the email.' );

		$this->assertSame( (int) $request->id, (int) TPD_LMS_Exam::find_by_token( $token )->id );
		$this->assertNotSame( $token, $request->proctor_token_hash, 'Only a hash of the link is stored.' );
		$code_row = TPD_LMS_Store::find( 'exam_access_codes', $request->exam_access_code_id );
		$this->assertGreaterThan( strtotime( '2030-03-05 19:00:00 UTC' ), strtotime( $code_row->expires_at . ' UTC' ), 'The code lasts at least a day after the planned session.' );

		TPD_LMS_Exam::resend( $request );
		$this->assertNull( TPD_LMS_Exam::find_by_token( $token ), 'A new link replaces the old one.' );
		$this->assertSame( 'not_pending', TPD_LMS_Exam::cancel_request( $request )->get_error_code(), 'Decided requests can’t be cancelled.' );
	}

	public function test_declining_tells_the_student_and_a_new_request_is_allowed(): void {
		list( $c, $quiz, $student, $e ) = $this->exam_course( 'code' );
		$request = TPD_LMS_Exam::request_proctor( $quiz, $e, array( 'proctor_name' => 'Sam', 'proctor_email' => 'sam@example.test' ) );
		TPD_LMS_Exam::decline( $request, 'Your proctor must not be a family member.' );

		$mails = $this->mails_to( get_userdata( $student )->user_email );
		$last  = end( $mails );
		$this->assertSame( 'About your proctor request', $last['subject'] );
		$this->assertStringContainsString( 'family member', $last['message'] );
		$this->assertIsObject( TPD_LMS_Exam::request_proctor( $quiz, $e, array( 'proctor_name' => 'Lee', 'proctor_email' => 'lee@example.test' ) ) );
	}

	/* Learner emails ----------------------------------------------------- */

	public function test_enrollment_and_certificate_emails(): void {
		$c       = $this->make_course( array(), array( array( 'type' => 'text' ) ) );
		$student = $this->make_user();
		$email   = get_userdata( $student )->user_email;

		$e = TPD_LMS_Progress::enroll( $student, $c['course']->id );
		$this->assertCount( 1, $this->mails_to( $email ) );
		$this->assertStringContainsString( 'lms_course=' . $c['course']->slug, $this->mails_to( $email )[0]['message'] );

		TPD_LMS_Progress::complete( $e, $c['lessons'][0] );
		$mails = $this->mails_to( $email );
		$this->assertCount( 2, $mails );
		$cert = TPD_LMS_Progress::certificate_for_enrollment( $e->id );
		$this->assertStringContainsString( $cert->code, $mails[1]['message'] );
	}
}
