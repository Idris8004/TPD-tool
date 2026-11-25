<?php
/**
 * Payments, plans and registration security (2.13.3).
 *
 * Covers: forged webhooks, cross-gateway / wrong-amount webhooks, cross-role sign-up
 * keeping the member's plan, and the fresh nonce handed back after sign-up.
 * Everything created here is removed again; settings are restored.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class PaymentsSecurityTest extends LmsTestCase {

	private $saved_payments;
	private $saved_subs;
	private $tx_ids = array();
	private $extra_users = array();

	protected function setUp(): void {
		parent::setUp();
		$this->saved_payments = get_option( TPD_Tool_Settings::OPT_PAYMENTS );
		$this->saved_subs     = get_option( TPD_Tool_Settings::OPT_SUBSCRIPTIONS );
		add_filter( 'send_auth_cookies', '__return_false' );
	}

	protected function tearDown(): void {
		global $wpdb;
		update_option( TPD_Tool_Settings::OPT_PAYMENTS, $this->saved_payments );
		update_option( TPD_Tool_Settings::OPT_SUBSCRIPTIONS, $this->saved_subs );
		foreach ( $this->tx_ids as $id ) {
			$wpdb->delete( TPD_Tool_Financial::table_name(), array( 'id' => $id ), array( '%d' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->extra_users as $id ) {
			wp_delete_user( $id );
		}
		remove_filter( 'send_auth_cookies', '__return_false' );
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
		$_POST = $_REQUEST = array();
		parent::tearDown();
	}

	// ---------------------------------------------------------------- helpers

	private function set_payments( array $over ) {
		$p = is_array( $this->saved_payments ) ? $this->saved_payments : array();
		update_option( TPD_Tool_Settings::OPT_PAYMENTS, array_merge( $p, $over ) );
	}

	private function make_tx( $user_id, $gateway, $amount = 49.00, $plan = 'premium' ) {
		global $wpdb;
		TPD_Tool_Financial::maybe_create_table();
		$ref = TPD_Tool_Financial::generate_tx_ref( 'TPD-TEST' );
		$wpdb->insert( TPD_Tool_Financial::table_name(), array(
			'tx_ref'        => $ref,
			'user_id'       => $user_id,
			'item_type'     => 'plan',
			'item_id'       => $plan,
			'item_title'    => 'Test plan',
			'billing_cycle' => 'month',
			'amount'        => $amount,
			'currency'      => 'USD',
			'gateway'       => $gateway,
			'gateway_ref'   => 'ORDER-' . $ref,
			'status'        => 'pending',
			'created_at'    => current_time( 'mysql' ),
		) );
		$this->tx_ids[] = (int) $wpdb->insert_id;
		return TPD_Tool_Financial::get_tx_by_ref( $ref );
	}

	private function rest( $route, array $body, array $headers = array() ) {
		$req = new WP_REST_Request( 'POST', $route );
		$req->set_body( wp_json_encode( $body ) );
		$req->set_header( 'content-type', 'application/json' );
		foreach ( $headers as $k => $v ) {
			$req->set_header( $k, $v );
		}
		return rest_get_server()->dispatch( $req );
	}

	private function stripe_event( $tx, $amount_cents, $currency = 'usd' ) {
		return array(
			'type' => 'checkout.session.completed',
			'data' => array( 'object' => array(
				'id'             => 'cs_test_' . wp_generate_password( 8, false ),
				'payment_status' => 'paid',
				'amount_total'   => $amount_cents,
				'currency'       => $currency,
				'metadata'       => array( 'tx_ref' => $tx->tx_ref ),
			) ),
		);
	}

	private function stripe_signed( array $event, $secret ) {
		$body = wp_json_encode( $event );
		$t    = time();
		$sig  = hash_hmac( 'sha256', $t . '.' . $body, $secret );
		$req  = new WP_REST_Request( 'POST', '/tpd/v1/stripe-webhook' );
		$req->set_body( $body );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_header( 'stripe-signature', "t={$t},v1={$sig}" );
		return rest_get_server()->dispatch( $req );
	}

	private function paypal_capture_event( $tx, $value ) {
		return array(
			'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
			'resource'   => array(
				'id'        => 'CAP-1',
				'status'    => 'COMPLETED',
				'custom_id' => $tx->tx_ref,
				'amount'    => array( 'value' => $value, 'currency_code' => 'USD' ),
			),
		);
	}

	private function tier( $user_id ) {
		return get_user_meta( $user_id, 'tpd_user_tier', true );
	}

	private function tx_status( $tx ) {
		return TPD_Tool_Financial::get_tx_by_id( $tx->id )->status;
	}

	/** Run a wp_ajax handler and return its decoded JSON response. */
	private function ajax( callable $handler, array $post ) {
		$_POST = $_REQUEST = $post + array( 'nonce' => wp_create_nonce( 'tpd_nonce' ) );
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
			// wp_send_json_* ends with wp_die(), which we turned into this exception.
		} finally {
			$out = ob_get_clean();
			remove_filter( 'wp_doing_ajax', $doing );
			remove_filter( 'wp_die_ajax_handler', $die );
		}
		return json_decode( $out, true );
	}

	// ---------------------------------------------------------------- webhooks

	public function test_paypal_webhook_refuses_everything_without_webhook_id() {
		$this->set_payments( array( 'paypal_webhook_id' => '', 'paypal_webhook_secret' => '' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'paypal' );

		$res = $this->rest( '/tpd/v1/paypal-webhook', $this->paypal_capture_event( $tx, '49.00' ) );

		$this->assertSame( 403, $res->get_status() );
		$this->assertSame( 'pending', $this->tx_status( $tx ) );
		$this->assertNotSame( 'premium', $this->tier( $user ) );
	}

	public function test_paypal_test_signature_header_no_longer_bypasses() {
		$this->set_payments( array( 'paypal_webhook_id' => 'WH-KNOWN-ID' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'paypal' );

		$res = $this->rest( '/tpd/v1/paypal-webhook', $this->paypal_capture_event( $tx, '49.00' ), array( 'x-paypal-test-signature' => 'WH-KNOWN-ID' ) );

		$this->assertSame( 403, $res->get_status() );
		$this->assertSame( 'pending', $this->tx_status( $tx ) );
	}

	public function test_stripe_webhook_refuses_everything_without_secret() {
		$this->set_payments( array( 'stripe_webhook_secret' => '' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'stripe' );

		$res = $this->rest( '/tpd/v1/stripe-webhook', $this->stripe_event( $tx, 4900 ) );

		$this->assertSame( 403, $res->get_status() );
		$this->assertSame( 'pending', $this->tx_status( $tx ) );
	}

	public function test_stripe_webhook_rejects_bad_signature() {
		$this->set_payments( array( 'stripe_webhook_secret' => 'whsec_test_123' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'stripe' );

		$res = $this->stripe_signed( $this->stripe_event( $tx, 4900 ), 'whsec_WRONG' );

		$this->assertSame( 403, $res->get_status() );
		$this->assertSame( 'pending', $this->tx_status( $tx ) );
	}

	public function test_stripe_webhook_ignores_other_gateways_orders() {
		$this->set_payments( array( 'stripe_webhook_secret' => 'whsec_test_123' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'paypal' );

		$this->stripe_signed( $this->stripe_event( $tx, 4900 ), 'whsec_test_123' );

		$this->assertSame( 'pending', $this->tx_status( $tx ) );
	}

	public function test_stripe_webhook_ignores_wrong_amount() {
		$this->set_payments( array( 'stripe_webhook_secret' => 'whsec_test_123' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'stripe', 49.00 );

		$this->stripe_signed( $this->stripe_event( $tx, 100 ), 'whsec_test_123' );
		$this->assertSame( 'pending', $this->tx_status( $tx ) );

		$this->stripe_signed( $this->stripe_event( $tx, 4900, 'eur' ), 'whsec_test_123' );
		$this->assertSame( 'pending', $this->tx_status( $tx ) );
	}

	public function test_stripe_webhook_fulfils_a_correct_signed_payment() {
		$this->set_payments( array( 'stripe_webhook_secret' => 'whsec_test_123' ) );
		$user = $this->make_user();
		$tx   = $this->make_tx( $user, 'stripe', 49.00, 'premium' );

		$res = $this->stripe_signed( $this->stripe_event( $tx, 4900 ), 'whsec_test_123' );

		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( 'paid', $this->tx_status( $tx ) );
		$this->assertSame( 'premium', $this->tier( $user ) );
	}

	// ---------------------------------------------------------------- registration

	public function test_cross_role_signup_keeps_paid_plan_and_expiry() {
		$supplier = $this->make_user( 'supplier' );
		$expiry   = gmdate( 'Y-m-d', strtotime( '+20 days' ) );
		update_user_meta( $supplier, 'tpd_user_tier', 'standard' );
		update_user_meta( $supplier, 'plan_expires_at', $expiry );
		wp_set_current_user( $supplier );

		$res = $this->ajax( array( 'TPD_Tool_Registration', 'ajax_register_advisor' ), array(
			'first_name'         => 'Cross',
			'last_name'          => 'Role',
			'email'              => get_userdata( $supplier )->user_email,
			'confirm_cross_role' => '1',
			'credential_mode'    => 'same',
			'tier'               => 'basic',
		) );

		$this->assertTrue( ! empty( $res['success'] ), wp_json_encode( $res ) );
		$this->assertTrue( TPD_Tool_Roles::is_advisor( $supplier ) );
		$this->assertSame( 'standard', $this->tier( $supplier ) );
		$this->assertSame( $expiry, get_user_meta( $supplier, 'plan_expires_at', true ) );
	}

	public function test_new_signup_starts_basic_and_returns_a_nonce_valid_after_login() {
		wp_set_current_user( 0 );
		$login = 'paytest_' . wp_generate_password( 6, false, false );

		$res = $this->ajax( array( 'TPD_Tool_Registration', 'ajax_register_advisor' ), array(
			'first_name'       => 'Pay',
			'last_name'        => 'Test',
			'email'            => $login . '@example.test',
			'username'         => $login,
			'password'         => 'Str0ng-pass!',
			'confirm_password' => 'Str0ng-pass!',
			'tier'             => 'premium',
			'billing_cycle'    => 'Month',
		) );

		$user = get_user_by( 'login', $login );
		$this->assertNotFalse( $user, wp_json_encode( $res ) );
		$this->extra_users[] = $user->ID;

		$this->assertSame( 'basic', $this->tier( $user->ID ), 'Paid tier must not be granted before payment.' );

		$premium = TPD_Tool_Settings::get_plan( 'premium' );
		if ( ! empty( $premium['monthly_price'] ) && (float) $premium['monthly_price'] > 0 ) {
			$this->assertTrue( ! empty( $res['data']['requires_payment'] ) );
			$this->assertNotEmpty( $res['data']['nonce'] );
			// The new nonce must pass the checkout's check_ajax_referer for the logged-in user.
			wp_set_current_user( $user->ID );
			$this->assertNotFalse( wp_verify_nonce( $res['data']['nonce'], 'tpd_nonce' ) );
		}
	}
}
