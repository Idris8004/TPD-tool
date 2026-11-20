<?php
/**
 * Advisor-side helpers: plan access, video links, checkout fulfilment, hub data.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class StudentTest extends LmsTestCase {

	public function test_plan_access_follows_the_plan_order(): void {
		$plans = array_keys( (array) TPD_Tool_Settings::get_plans() );
		$this->assertGreaterThanOrEqual( 2, count( $plans ), 'TPD needs at least two plans for this test.' );
		$low  = $plans[0];
		$high = $plans[ count( $plans ) - 1 ];

		$c       = $this->make_course( array( 'price' => 100, 'tier_included' => $high ) );
		$student = $this->make_user();

		TPD_Tool_Tiers::set_user_tier( $student, $low );
		$this->assertFalse( TPD_LMS_Student::tier_includes( $student, $c['course'] ) );
		TPD_Tool_Tiers::set_user_tier( $student, $high );
		$this->assertTrue( TPD_LMS_Student::tier_includes( $student, $c['course'] ) );

		TPD_LMS_Store::update_course( $c['course']->id, array( 'tier_included' => 'all' ) );
		TPD_Tool_Tiers::set_user_tier( $student, $low );
		$this->assertTrue( TPD_LMS_Student::tier_includes( $student, TPD_LMS_Store::get_course( $c['course']->id ) ) );

		TPD_LMS_Store::update_course( $c['course']->id, array( 'tier_included' => 'none' ) );
		$this->assertFalse( TPD_LMS_Student::tier_includes( $student, TPD_LMS_Store::get_course( $c['course']->id ) ) );
	}

	public function test_video_links_become_privacy_friendly_embeds(): void {
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', TPD_LMS_Student::video_embed_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10' ) );
		$this->assertSame( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0', TPD_LMS_Student::video_embed_url( 'https://youtu.be/dQw4w9WgXcQ' ) );
		$this->assertSame( 'https://player.vimeo.com/video/76979871?dnt=1', TPD_LMS_Student::video_embed_url( 'https://vimeo.com/76979871' ) );
		$this->assertSame( '', TPD_LMS_Student::video_embed_url( 'https://example.com/video.mp4' ) );
		$this->assertSame( '', TPD_LMS_Student::video_embed_url( 'javascript:alert(1)' ) );
	}

	public function test_fulfilment_enrolls_once_and_renews_expired_access(): void {
		$c       = $this->make_course( array( 'access_days' => 30 ) );
		$student = $this->make_user();
		$tx      = (object) array( 'id' => 501, 'user_id' => $student, 'item_id' => $c['course']->slug );

		$this->assertTrue( TPD_LMS_Student::fulfill( $tx )['ok'] );
		$this->assertTrue( TPD_LMS_Student::fulfill( $tx )['ok'] );
		$this->assertCount( 1, TPD_LMS_Progress::enrollments_for_user( $student ) );
		$e = TPD_LMS_Progress::get_enrollment( $student, $c['course']->id );
		$this->assertSame( 501, (int) $e->tx_id );

		TPD_LMS_Store::update( 'enrollments', $e->id, array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ) );
		$this->assertTrue( TPD_LMS_Student::fulfill( (object) array( 'id' => 502, 'user_id' => $student, 'item_id' => $c['course']->slug ) )['ok'] );
		$e = TPD_LMS_Progress::get_enrollment( $student, $c['course']->id );
		$this->assertFalse( TPD_LMS_Progress::is_expired( $e ), 'Paying again renews access.' );
		$this->assertSame( 502, (int) $e->tx_id );

		$this->assertFalse( TPD_LMS_Student::fulfill( (object) array( 'id' => 503, 'user_id' => $student, 'item_id' => 'no-such-course' ) )['ok'] );
	}

	public function test_hub_data_lists_only_published_courses_and_the_users_enrollments(): void {
		$pub     = $this->make_course( array( 'status' => 'published', 'price' => 20 ) );
		$draft   = $this->make_course( array( 'status' => 'draft' ) );
		$student = $this->make_user();
		TPD_LMS_Progress::enroll( $student, $pub['course']->id );

		$slugs = wp_list_pluck( TPD_LMS_Student::hub_catalog( $student ), 'slug' );
		$this->assertContains( $pub['course']->slug, $slugs );
		$this->assertNotContains( $draft['course']->slug, $slugs );

		$mine = TPD_LMS_Student::hub_enrollments( $student );
		$this->assertCount( 1, $mine );
		$this->assertSame( $pub['course']->slug, $mine[0]['course_slug'] );
		$this->assertNull( $mine[0]['certificate'] );
	}
}
