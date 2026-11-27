<?php
/**
 * Security & production-readiness regression tests.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class SecurityAuditTest extends LmsTestCase {

	private $saved_claims;
	private $saved_resources;
	private $post_ids = array();

	protected function setUp(): void {
		parent::setUp();
		$this->saved_claims    = get_option( 'tpd_pending_listing_claims' );
		$this->saved_resources = get_option( TPD_Tool_Resources::OPT_RESOURCES );
	}

	protected function tearDown(): void {
		update_option( 'tpd_pending_listing_claims', $this->saved_claims );
		update_option( TPD_Tool_Resources::OPT_RESOURCES, $this->saved_resources );
		foreach ( $this->post_ids as $pid ) {
			wp_delete_post( $pid, true );
		}
		$_POST = $_REQUEST = $_FILES = array();
		parent::tearDown();
	}

	private function ajax( callable $handler, array $post, $nonce_action = 'tpd_nonce' ) {
		$_POST = $_REQUEST = $post + array( 'nonce' => wp_create_nonce( $nonce_action ) );
		$doing = static function () { return true; };
		$die   = static function () {
			return static function () { throw new RuntimeException( 'ajax_die' ); };
		};
		add_filter( 'wp_doing_ajax', $doing );
		add_filter( 'wp_die_ajax_handler', $die );
		ob_start();
		try {
			$handler();
		} catch ( RuntimeException $e ) {
		} finally {
			$out = ob_get_clean();
			remove_filter( 'wp_doing_ajax', $doing );
			remove_filter( 'wp_die_ajax_handler', $die );
		}
		return json_decode( $out, true );
	}

	public function test_dynamic_fields_whitelist_blocks_arbitrary_meta_overwrite() {
		$user_id = $this->make_user( 'travel_advisor' );
		update_user_meta( $user_id, 'tpd_user_tier', 'basic' );

		$post_id = wp_insert_post( array(
			'post_type'   => 'travel_advisor',
			'post_title'  => 'Whitelist Test Advisor',
			'post_status' => 'publish',
			'post_author' => $user_id,
		) );
		$this->post_ids[] = $post_id;

		$_POST['tpd_dyn'] = array(
			'tpd_user_tier'   => 'premium',
			'wp_capabilities' => array( 'administrator' => true ),
			'plan_expires_at' => '2099-12-31',
		);

		TPD_Tool_CPT::save_dynamic_fields_submission( 'travel_advisor', $post_id, $user_id );

		$this->assertSame( 'basic', get_user_meta( $user_id, 'tpd_user_tier', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'plan_expires_at', true ) );
		$this->assertSame( false, user_can( $user_id, 'manage_options' ) );
	}

	public function test_media_upload_rejects_svg() {
		$user_id = $this->make_user( 'supplier' );
		wp_set_current_user( $user_id );

		$_FILES['file'] = array(
			'name'     => 'xss.svg',
			'type'     => 'image/svg+xml',
			'tmp_name' => __FILE__,
			'error'    => UPLOAD_ERR_OK,
			'size'     => 100,
		);

		$res = $this->ajax( array( 'TPD_Tool_Profile_Editor', 'ajax_upload_media' ), array() );
		$this->assertSame( false, ! empty( $res['success'] ), wp_json_encode( $res ) );
	}

	public function test_check_registration_email_redacts_pii_for_logged_out_visitor() {
		$supplier = $this->make_user( 'supplier' );
		wp_update_user( array( 'ID' => $supplier, 'first_name' => 'SecretFirst', 'last_name' => 'SecretLast' ) );
		update_user_meta( $supplier, 'tpd_phone', '+15551234567' );
		update_user_meta( $supplier, 'tpd_country', 'Canada' );

		wp_set_current_user( 0 );
		$res = $this->ajax( array( 'TPD_Tool_Registration', 'ajax_check_registration_email' ), array(
			'email'       => get_userdata( $supplier )->user_email,
			'target_role' => 'travel_advisor',
		) );

		$this->assertTrue( ! empty( $res['success'] ), wp_json_encode( $res ) );
		$this->assertSame( 'cross_role_available', $res['data']['status'] );
		$this->assertSame( '', $res['data']['first_name'] );
		$this->assertSame( '', $res['data']['last_name'] );
		$this->assertSame( '', $res['data']['phone'] );
		$this->assertSame( '', $res['data']['existing_username'] );
	}

	public function test_resources_api_redacts_locked_file_url() {
		$advisor = $this->make_user( 'travel_advisor' );
		update_user_meta( $advisor, 'tpd_user_tier', 'basic' );
		wp_set_current_user( $advisor );

		update_option( TPD_Tool_Resources::OPT_RESOURCES, array(
			'paid_kit' => array(
				'id'            => 'paid_kit',
				'title'         => 'Paid Toolkit',
				'price'         => 99.00,
				'tier_included' => 'none',
				'file_url'      => 'https://example.com/secret-paid-file.pdf',
				'status'        => 'published',
			),
		) );

		$res = $this->ajax( array( 'TPD_Tool_Resources', 'ajax_get_resources_data' ), array() );
		$this->assertTrue( ! empty( $res['success'] ), wp_json_encode( $res ) );
		$this->assertSame( 1, count( $res['data']['resources'] ) );
		$this->assertSame( false, $res['data']['resources'][0]['has_access'] );
		$this->assertSame( '', $res['data']['resources'][0]['file_url'] );
	}

	public function test_get_claims_does_not_seed_fake_claim_and_rejects_non_listing() {
		delete_option( 'tpd_pending_listing_claims' );
		$this->assertSame( array(), TPD_Tool_Profile_Editor::get_claims() );

		$supplier = $this->make_user( 'supplier' );
		wp_set_current_user( $supplier );

		$page_id = wp_insert_post( array(
			'post_type'   => 'page',
			'post_title'  => 'Ordinary Page',
			'post_status' => 'publish',
		) );
		$this->post_ids[] = $page_id;

		$res = $this->ajax( array( 'TPD_Tool_Profile_Editor', 'ajax_submit_claim_listing' ), array(
			'listing_id'  => $page_id,
			'claim_notes' => 'Trying to claim a page',
		) );
		$this->assertSame( false, ! empty( $res['success'] ), wp_json_encode( $res ) );
	}

	public function test_events_do_not_return_fake_default_models() {
		$events = TPD_Tool_Events::get_upcoming_events( 10 );
		foreach ( $events as $ev ) {
			$post = get_post( (int) $ev['id'] );
			$this->assertTrue( $post && 'tpd_event' === $post->post_type, 'Event must be a real published tpd_event post, not a hardcoded model.' );
		}
	}

	public function test_csv_formula_sanitization() {
		$this->assertSame( "'=CMD|'/C calc'!A0", TPD_Tool_Settings::sanitize_csv_cell( "=CMD|'/C calc'!A0" ) );
		$this->assertSame( "'+1+1", TPD_Tool_Settings::sanitize_csv_cell( '+1+1' ) );
		$this->assertSame( "'-2+3", TPD_Tool_Settings::sanitize_csv_cell( '-2+3' ) );
		$this->assertSame( "'@SUM(1,2)", TPD_Tool_Settings::sanitize_csv_cell( '@SUM(1,2)' ) );
		$this->assertSame( 'Normal Text', TPD_Tool_Settings::sanitize_csv_cell( 'Normal Text' ) );
	}

	public function test_client_inquiry_and_guest_chat_reject_admin_recipient() {
		$admin_id = $this->make_user( 'administrator' );
		wp_set_current_user( 0 );

		$inq = $this->ajax( array( 'TPD_Tool_Shortcodes', 'ajax_submit_client_inquiry' ), array(
			'traveler_name'  => 'Attacker',
			'traveler_email' => 'attacker@example.test',
			'destination'    => 'Paris',
			'advisor_id'     => $admin_id,
		) );
		$this->assertSame( false, ! empty( $inq['success'] ), wp_json_encode( $inq ) );

		$chat = $this->ajax( array( 'TPD_Tool_Chat', 'ajax_send_message' ), array(
			'recipient_id' => $admin_id,
			'guest_name'   => 'Guest',
			'guest_email'  => 'guest@example.test',
			'message'      => 'Hello admin',
		), 'tpd_chat_nonce' );
		$this->assertSame( false, ! empty( $chat['success'] ), wp_json_encode( $chat ) );
	}
}
