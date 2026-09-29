<?php
/**
 * Database Seeder for TPD Tool
 * Seeds realistic users matching the Client's Inspiration Designs:
 * - Steven Gould (Travel Advisor)
 * - Sarah Mitchell (AmaWaterways - Regional BDM)
 * - AmaWaterways, Four Seasons, Abercrombie & Kent, Viking, Sandals
 * - Events, analytics, and sample conversations
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Seeder {

	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'tpd-tool-seed', array( __CLASS__, 'cli_seed' ) );
		}
	}

	public static function cli_seed() {
		self::seed_all();
		WP_CLI::success( 'TPD Tool seeded with Inspiration Designs data (Steven Gould, AmaWaterways, Events & Portals)!' );
	}

	public static function seed_all() {
		// 1. Seed Taxonomies
		self::seed_taxonomies();

		// 2. Seed Users
		$users = self::seed_users();

		// 3. Seed Suppliers
		$suppliers = self::seed_suppliers( $users );

		// 4. Seed Advisors
		$advisors = self::seed_advisors( $users, $suppliers );

		// 5. Seed Events
		self::seed_events();

		// 6. Ensure Pages
		self::ensure_pages();
	}

	private static function seed_taxonomies() {
		$destinations = array( 'Europe', 'Africa', 'Alaska', 'Caribbean', 'Italy', 'Japan' );
		foreach ( $destinations as $d ) {
			if ( ! term_exists( $d, 'travel_destination' ) ) {
				wp_insert_term( $d, 'travel_destination' );
			}
		}

		$styles = array( 'River Cruises', 'Ocean Cruises', 'Luxury', 'All-Inclusive', 'Couples', 'Groups', 'Custom Travel', 'Cultural' );
		foreach ( $styles as $s ) {
			if ( ! term_exists( $s, 'travel_style' ) ) {
				wp_insert_term( $s, 'travel_style' );
			}
		}

		$brands = array( 'AmaWaterways', 'Four Seasons', 'Abercrombie & Kent', 'Viking', 'Sandals' );
		foreach ( $brands as $b ) {
			if ( ! term_exists( $b, 'supplier_brand' ) ) {
				wp_insert_term( $b, 'supplier_brand' );
			}
		}
	}

	private static function seed_users() {
		$users_data = array(
			// 1. Steven Gould (Design Inspiration 1 Advisor)
			'steven.gould' => array(
				'email'        => 'steven@gouldtravel.com',
				'display_name' => 'Steven Gould',
				'first_name'   => 'Steven',
				'last_name'    => 'Gould',
				'role'         => 'travel_advisor',
				'tier'         => 'paid', // Pro Tier
				'password'     => 'AdvisorPass123!',
			),
			// 2. Sarah Jenkins (Free Tier Advisor for comparison)
			'sarah.jenkins' => array(
				'email'        => 'sarah@luxetravelpros.com',
				'display_name' => 'Sarah Jenkins, VTA',
				'first_name'   => 'Sarah',
				'last_name'    => 'Jenkins',
				'role'         => 'travel_advisor',
				'tier'         => 'free', // Free Tier to test upgrade card!
				'password'     => 'AdvisorPass123!',
			),
			// 3. Sarah Mitchell (Design Inspiration 2 Supplier - AmaWaterways)
			'sarah.mitchell' => array(
				'email'        => 'smitchell@amawaterways.com',
				'display_name' => 'Sarah Mitchell',
				'first_name'   => 'Sarah',
				'last_name'    => 'Mitchell',
				'role'         => 'supplier',
				'tier'         => 'paid', // Pro Supplier
				'password'     => 'SupplierPass123!',
			),
			// 4. David Chen (Free Tier Supplier)
			'david.chen' => array(
				'email'        => 'tradesales@railbookers.com',
				'display_name' => 'David Chen',
				'first_name'   => 'David',
				'last_name'    => 'Chen',
				'role'         => 'supplier',
				'tier'         => 'free', // Free Supplier
				'password'     => 'SupplierPass123!',
			),
		);

		$created = array();

		foreach ( $users_data as $login => $u ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user ) {
				$uid = wp_create_user( $login, $u['password'], $u['email'] );
				if ( ! is_wp_error( $uid ) ) {
					wp_update_user( array(
						'ID'           => $uid,
						'display_name' => $u['display_name'],
						'first_name'   => $u['first_name'],
						'last_name'    => $u['last_name'],
						'role'         => $u['role'],
					) );
					update_user_meta( $uid, 'tpd_user_tier', $u['tier'] );
					$created[ $login ] = $uid;
				}
			} else {
				update_user_meta( $user->ID, 'tpd_user_tier', $u['tier'] );
				$created[ $login ] = $user->ID;
			}
		}

		return $created;
	}

	private static function seed_suppliers( $users ) {
		$suppliers = array(
			'amawaterways' => array(
				'title'       => 'AmaWaterways',
				'slug'        => 'amawaterways',
				'user_id'     => $users['sarah.mitchell'] ?? 1,
				'category'    => 'river_cruises',
				'tagline'     => 'Leading the Way in River Cruising Across Europe & Beyond',
				'logo'        => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=200&auto=format&fit=crop&q=80',
				'cover'       => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1400&auto=format&fit=crop&q=80',
				'reps_online' => 3,
				'destinations'=> array( 'Europe' ),
				'styles'      => array( 'River Cruises', 'Luxury', 'Groups' ),
				'brand'       => 'AmaWaterways',
			),
			'four_seasons' => array(
				'title'       => 'Four Seasons Hotels & Resorts',
				'slug'        => 'four-seasons-hotels-resorts',
				'user_id'     => $users['sarah.mitchell'] ?? 1,
				'category'    => 'hotels_resorts',
				'tagline'     => 'Legendary Luxury Escapes & World-Class Hospitality',
				'logo'        => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=200&auto=format&fit=crop&q=80',
				'cover'       => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=1400&auto=format&fit=crop&q=80',
				'reps_online' => 2,
				'destinations'=> array( 'Europe', 'Caribbean' ),
				'styles'      => array( 'Luxury', 'All-Inclusive', 'Couples' ),
				'brand'       => 'Four Seasons',
			),
			'abercrombie_kent' => array(
				'title'       => 'Abercrombie & Kent',
				'slug'        => 'abercrombie-kent',
				'user_id'     => $users['david.chen'] ?? 1,
				'category'    => 'tour_operator',
				'tagline'     => 'Pioneering Luxury Safaris and Curated Escorted Expeditions',
				'logo'        => 'https://images.unsplash.com/photo-1534447677768-be436bb09401?w=200&auto=format&fit=crop&q=80',
				'cover'       => 'https://images.unsplash.com/photo-1516426122078-c23e76319801?w=1400&auto=format&fit=crop&q=80',
				'reps_online' => 1,
				'destinations'=> array( 'Africa', 'Europe' ),
				'styles'      => array( 'Luxury', 'Custom Travel' ),
				'brand'       => 'Abercrombie & Kent',
			),
			'viking' => array(
				'title'       => 'Viking Ocean & River Cruises',
				'slug'        => 'viking-cruises',
				'user_id'     => $users['david.chen'] ?? 1,
				'category'    => 'ocean_cruises',
				'tagline'     => 'Exploring the World in Comfort – Destination-Focused Cruising',
				'logo'        => 'https://images.unsplash.com/photo-1505705694340-019e1e335916?w=200&auto=format&fit=crop&q=80',
				'cover'       => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1400&auto=format&fit=crop&q=80',
				'reps_online' => 4,
				'destinations'=> array( 'Europe' ),
				'styles'      => array( 'River Cruises', 'Cultural' ),
				'brand'       => 'Viking',
			),
			'sandals' => array(
				'title'       => 'Sandals Resorts',
				'slug'        => 'sandals-resorts',
				'user_id'     => $users['sarah.mitchell'] ?? 1,
				'category'    => 'hotels_resorts',
				'tagline'     => 'Luxury Included Vacations for Two in Love Across the Caribbean',
				'logo'        => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=200&auto=format&fit=crop&q=80',
				'cover'       => 'https://images.unsplash.com/photo-1512813195386-6cf811ad3542?w=1400&auto=format&fit=crop&q=80',
				'reps_online' => 2,
				'destinations'=> array( 'Caribbean' ),
				'styles'      => array( 'All-Inclusive', 'Couples' ),
				'brand'       => 'Sandals',
			),
		);

		$post_ids = array();
		foreach ( $suppliers as $k => $s ) {
			$existing = get_page_by_path( $s['slug'], OBJECT, 'supplier_listing' );
			$pid = $existing ? $existing->ID : 0;
			if ( ! $pid ) {
				$pid = wp_insert_post( array(
					'post_type'    => 'supplier_listing',
					'post_title'   => $s['title'],
					'post_name'    => $s['slug'],
					'post_content' => $s['tagline'],
					'post_status'  => 'publish',
					'post_author'  => $s['user_id'],
				) );
			}
			if ( $pid && ! is_wp_error( $pid ) ) {
				update_post_meta( $pid, 'tpd_assigned_user', $s['user_id'] );
				update_post_meta( $pid, 'tpd_company_name', $s['title'] );
				update_post_meta( $pid, 'tpd_supplier_category', $s['category'] );
				update_post_meta( $pid, 'tpd_logo', $s['logo'] );
				update_post_meta( $pid, 'tpd_supplier_cover', $s['cover'] );
				update_post_meta( $pid, 'tpd_reps_online', $s['reps_online'] );
				update_post_meta( $pid, 'tpd_profile_views', 1842 );
				update_post_meta( $pid, 'tpd_listing_saves', 427 );
				update_post_meta( $pid, 'tpd_meeting_requests', 31 );
				update_post_meta( $pid, 'tpd_quote_requests', 18 );
				update_post_meta( $pid, 'tpd_bookings_reported', 7 );

				if ( ! empty( $s['destinations'] ) ) {
					wp_set_object_terms( $pid, $s['destinations'], 'travel_destination', false );
				}
				if ( ! empty( $s['styles'] ) ) {
					wp_set_object_terms( $pid, $s['styles'], 'travel_style', false );
				}
				if ( ! empty( $s['brand'] ) ) {
					wp_set_object_terms( $pid, array( $s['brand'] ), 'supplier_brand', false );
				}
				$post_ids[ $k ] = $pid;
			}
		}

		return $post_ids;
	}

	private static function seed_advisors( $users, $suppliers ) {
		// Seed Steven Gould (Inspiration 1 Advisor)
		$steven_uid = $users['steven.gould'] ?? 1;
		$existing = get_page_by_path( 'steven-gould', OBJECT, 'travel_advisor' );
		$pid = $existing ? $existing->ID : 0;
		if ( ! $pid ) {
			$pid = wp_insert_post( array(
				'post_type'    => 'travel_advisor',
				'post_title'   => 'Steven Gould',
				'post_name'    => 'steven-gould',
				'post_content' => 'Bespoke European luxury and river cruise specialist.',
				'post_status'  => 'publish',
				'post_author'  => $steven_uid,
			) );
		}

		if ( $pid && ! is_wp_error( $pid ) ) {
			update_post_meta( $pid, 'tpd_assigned_user', $steven_uid );
			update_post_meta( $pid, 'tpd_agency_name', 'Gould Luxury Travel' );
			update_post_meta( $pid, 'tpd_profile_handle', '@steven_gould' );
			update_post_meta( $pid, 'tpd_city_state', 'New York, NY' );
			update_post_meta( $pid, 'tpd_headshot', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&auto=format&fit=crop&q=80' );
			update_post_meta( $pid, 'tpd_cover_banner', 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=1400&auto=format&fit=crop&q=80' );
			update_post_meta( $pid, 'tpd_profile_views', 1248 );
			update_post_meta( $pid, 'tpd_inquiries_count', 6 );
			update_post_meta( $pid, 'tpd_accreditations', array( 'ARC', 'CLIA', 'IATA', 'TRUE', 'ASTA' ) );

			// Bookmark the 5 suppliers for Steven Gould
			if ( ! empty( $suppliers ) ) {
				update_user_meta( $steven_uid, '_tpd_saved_suppliers', array_values( $suppliers ) );
			}
		}
	}

	private static function seed_events() {
		$events = array(
			array(
				'title'  => 'Luxury Europe Deep Dive',
				'slug'   => 'luxury-europe-deep-dive',
				'month'  => 'OCT',
				'day'    => '14',
				'series' => 'Vendor Training Series',
				'time'   => '12:00 PM - 1:00 PM ET · Virtual',
			),
			array(
				'title'  => 'Selling River Cruises in 2026',
				'slug'   => 'selling-river-cruises-in-2026',
				'month'  => 'OCT',
				'day'    => '16',
				'series' => 'TARC Talk Webinar',
				'time'   => '2:00 PM - 3:00 PM ET · Virtual',
			),
			array(
				'title'  => 'The Power of Destination Specialist',
				'slug'   => 'power-of-destination-specialist',
				'month'  => 'OCT',
				'day'    => '22',
				'series' => 'Panel Discussion',
				'time'   => '12:00 PM - 1:30 PM ET · Virtual',
			),
		);

		foreach ( $events as $ev ) {
			$existing = get_page_by_path( $ev['slug'], OBJECT, 'tpd_event' );
			$pid = $existing ? $existing->ID : 0;
			if ( ! $pid ) {
				$pid = wp_insert_post( array(
					'post_type'   => 'tpd_event',
					'post_title'  => $ev['title'],
					'post_name'   => $ev['slug'],
					'post_status' => 'publish',
				) );
			}
			if ( $pid && ! is_wp_error( $pid ) ) {
				update_post_meta( $pid, '_tpd_event_month', $ev['month'] );
				update_post_meta( $pid, '_tpd_event_day', $ev['day'] );
				update_post_meta( $pid, '_tpd_event_series', $ev['series'] );
				update_post_meta( $pid, '_tpd_event_time', $ev['time'] );
			}
		}
	}

	private static function ensure_pages() {
		$pages = array(
			'advisor-dashboard' => array(
				'title'   => 'Advisor Portal',
				'content' => '[tpd_advisor_dashboard]',
			),
			'supplier-dashboard' => array(
				'title'   => 'Supplier Portal',
				'content' => '[tpd_supplier_dashboard]',
			),
			'tpd-admin' => array(
				'title'   => 'TPD Super Admin Dashboard',
				'content' => '[tpd_admin_dashboard]',
			),
		);

		foreach ( $pages as $slug => $data ) {
			$p = get_page_by_path( $slug );
			if ( ! $p ) {
				wp_insert_post( array(
					'post_title'   => $data['title'],
					'post_name'    => $slug,
					'post_content' => $data['content'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				) );
			}
		}
	}
}

TPD_Tool_Seeder::init();
