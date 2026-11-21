<?php
/**
 * The old external LMS bridge is gone; toolkits live in TPD_Tool_Resources and
 * courses go through the native LMS only.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class ResourcesTest extends LmsTestCase {

	private $saved_resources;

	protected function setUp(): void {
		parent::setUp();
		$this->saved_resources = get_option( TPD_Tool_Resources::OPT_RESOURCES );
	}

	protected function tearDown(): void {
		update_option( TPD_Tool_Resources::OPT_RESOURCES, $this->saved_resources );
		parent::tearDown();
	}

	public function test_bridge_code_is_gone(): void {
		$this->assertFalse( class_exists( 'TPD_Tool_LMS', false ) );
		foreach ( array( 'tpd_get_lms_sso_url', 'tpd_admin_save_lms_settings', 'tpd_admin_test_lms_connection', 'tpd_admin_toggle_lms_course_publish', 'tpd_admin_sync_lms_cache' ) as $action ) {
			$this->assertFalse( has_action( 'wp_ajax_' . $action ), $action );
		}
		$this->assertArrayNotHasKey( '/tpd/v1/lms-webhook', rest_get_server()->get_routes() );
		$this->assertFalse( method_exists( 'TPD_Tool_Financial', 'record_external_lms_order' ) );
	}

	public function test_toolkit_actions_still_registered(): void {
		foreach ( array( 'tpd_get_resources_data', 'tpd_admin_save_custom_resource', 'tpd_admin_delete_custom_resource' ) as $action ) {
			$this->assertNotFalse( has_action( 'wp_ajax_' . $action ), $action );
		}
	}

	public function test_old_bridge_secrets_are_deleted(): void {
		update_option( TPD_Tool_Resources::OPT_OLD_BRIDGE, array( 'sso_secret' => 'x', 'api_token' => 'y' ) );
		TPD_Tool_Resources::ensure_defaults();
		$this->assertFalse( get_option( TPD_Tool_Resources::OPT_OLD_BRIDGE, false ) );
	}

	public function test_access_rules_free_tier_and_purchase(): void {
		$user = $this->make_user();
		TPD_Tool_Tiers::set_user_tier( $user, 'basic' );
		$free    = array( 'id' => 'r_free', 'price' => 0 );
		$premium = array( 'id' => 'r_prem', 'price' => 29, 'tier_included' => 'premium' );
		$solo    = array( 'id' => 'r_solo', 'price' => 10, 'tier_included' => 'none' );

		$this->assertTrue( TPD_Tool_Resources::user_has_resource_access( $user, $free ) );
		$this->assertFalse( TPD_Tool_Resources::user_has_resource_access( $user, $premium ) );
		$this->assertFalse( TPD_Tool_Resources::user_has_resource_access( $user, $solo ) );

		TPD_Tool_Resources::unlock_custom_resource( $user, 'r_solo' );
		$this->assertTrue( TPD_Tool_Resources::user_has_resource_access( $user, $solo ) );
		$this->assertFalse( TPD_Tool_Resources::user_has_resource_access( $user, $premium ), 'Unlocking one does not unlock others.' );
	}

	public function test_checkout_prices_a_resource_and_fulfilment_unlocks_it(): void {
		$user = $this->make_user();
		update_option( TPD_Tool_Resources::OPT_RESOURCES, array(
			'r_kit' => array( 'id' => 'r_kit', 'title' => 'Kit', 'category' => 'Sales Toolkit', 'price' => 19.5, 'tier_included' => 'none', 'status' => 'published', 'file_url' => 'https://example.com/kit.pdf' ),
		) );

		$item = TPD_Tool_Financial::resolve_item( $user, 'resource', 'r_kit' );
		$this->assertSame( 19.5, $item['amount'] );

		$tx = (object) array( 'id' => 0, 'user_id' => $user, 'item_type' => 'resource', 'item_id' => 'r_kit', 'item_title' => 'Kit', 'meta_json' => '' );
		global $wpdb;
		$wpdb->insert( TPD_Tool_Financial::table_name(), array( 'tx_ref' => 'TEST-' . wp_generate_password( 8, false ), 'user_id' => $user, 'item_type' => 'resource', 'item_id' => 'r_kit', 'item_title' => 'Kit', 'amount' => 19.5, 'currency' => 'USD', 'gateway' => 'test', 'status' => 'paid', 'meta_json' => '', 'created_at' => current_time( 'mysql' ) ) );
		$tx->id = (int) $wpdb->insert_id;

		$res = TPD_Tool_Financial::fulfill_transaction( $tx );
		$this->assertTrue( $res['ok'] );
		$this->assertArrayNotHasKey( 'sso_url', $res );
		$this->assertSame( 0.0, TPD_Tool_Financial::resolve_item( $user, 'resource', 'r_kit' )['amount'], 'Owned now, so it costs nothing.' );

		$wpdb->delete( TPD_Tool_Financial::table_name(), array( 'id' => $tx->id ) );
	}

	public function test_course_checkout_uses_the_native_lms(): void {
		$c    = $this->make_course( array( 'price' => 49 ), array( array( 'type' => 'text', 'title' => 'One' ) ) );
		$user = $this->make_user();
		$item = TPD_Tool_Financial::resolve_item( $user, 'lms_course', $c['course']->slug );
		$this->assertFalse( is_wp_error( $item ), is_wp_error( $item ) ? $item->get_error_message() : "" );
		$this->assertSame( 'lms_course', $item['item_type'] );
		$this->assertEquals( 49, $item['amount'] );
	}
}
