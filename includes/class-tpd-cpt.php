<?php
/**
 * Custom Post Types, Taxonomies & Data Schema for TPD Tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_CPT {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_all' ), 0 );
		add_action( 'acf/init', array( __CLASS__, 'register_acf_fields' ) );
	}

	public static function register_all() {
		self::register_taxonomies();
		self::register_cpts();
	}

	private static function register_taxonomies() {
		// 1. Travel Destinations
		register_taxonomy(
			'travel_destination',
			array( 'travel_advisor', 'supplier_listing', 'supplier_blog', 'tpd_event' ),
			array(
				'labels'            => array(
					'name'          => __( 'Destinations', 'tpd-tool' ),
					'singular_name' => __( 'Destination', 'tpd-tool' ),
					'menu_name'     => __( 'Destinations', 'tpd-tool' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'destination', 'with_front' => false ),
			)
		);

		// 2. Travel Styles
		register_taxonomy(
			'travel_style',
			array( 'travel_advisor', 'supplier_listing', 'tpd_event' ),
			array(
				'labels'            => array(
					'name'          => __( 'Travel Styles', 'tpd-tool' ),
					'singular_name' => __( 'Travel Style', 'tpd-tool' ),
					'menu_name'     => __( 'Travel Styles', 'tpd-tool' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'travel-style', 'with_front' => false ),
			)
		);

		// 3. Supplier Brands
		register_taxonomy(
			'supplier_brand',
			array( 'travel_advisor', 'supplier_listing', 'supplier_blog', 'tpd_event' ),
			array(
				'labels'            => array(
					'name'          => __( 'Supplier Brands', 'tpd-tool' ),
					'singular_name' => __( 'Supplier Brand', 'tpd-tool' ),
					'menu_name'     => __( 'Supplier Brands', 'tpd-tool' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'supplier-brand', 'with_front' => false ),
			)
		);

		// 4. Advisor Country / Location
		register_taxonomy(
			'advisor_country',
			array( 'travel_advisor' ),
			array(
				'labels'            => array(
					'name'          => __( 'Countries', 'tpd-tool' ),
					'singular_name' => __( 'Country', 'tpd-tool' ),
					'menu_name'     => __( 'Countries', 'tpd-tool' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'country', 'with_front' => false ),
			)
		);
	}

	private static function register_cpts() {
		// 1. Travel Advisor CPT
		register_post_type(
			'travel_advisor',
			array(
				'labels'             => array(
					'name'          => __( 'Travel Advisors', 'tpd-tool' ),
					'singular_name' => __( 'Travel Advisor', 'tpd-tool' ),
					'menu_name'     => __( 'Travel Advisors', 'tpd-tool' ),
				),
				'public'             => true,
				'has_archive'        => 'advisors',
				'rewrite'            => array( 'slug' => 'advisor-profile', 'with_front' => false ),
				'menu_position'      => 5,
				'menu_icon'          => 'dashicons-businessperson',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'show_in_rest'       => true,
			)
		);

		// 2. Supplier Listing CPT
		register_post_type(
			'supplier_listing',
			array(
				'labels'             => array(
					'name'          => __( 'Suppliers', 'tpd-tool' ),
					'singular_name' => __( 'Supplier Listing', 'tpd-tool' ),
					'menu_name'     => __( 'Suppliers', 'tpd-tool' ),
				),
				'public'             => true,
				'has_archive'        => 'suppliers',
				'rewrite'            => array( 'slug' => 'suppliers', 'with_front' => false ),
				'menu_position'      => 6,
				'menu_icon'          => 'dashicons-building',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'show_in_rest'       => true,
			)
		);

		// 3. Supplier Blog CPT
		register_post_type(
			'supplier_blog',
			array(
				'labels'             => array(
					'name'          => __( 'Supplier Articles', 'tpd-tool' ),
					'singular_name' => __( 'Supplier Article', 'tpd-tool' ),
					'menu_name'     => __( 'Supplier Articles', 'tpd-tool' ),
				),
				'public'             => true,
				'has_archive'        => 'supplier-insights',
				'rewrite'            => array( 'slug' => 'supplier-insights', 'with_front' => false ),
				'menu_position'      => 7,
				'menu_icon'          => 'dashicons-welcome-write-blog',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ),
				'show_in_rest'       => true,
			)
		);

		// 4. Events & Webinars CPT
		register_post_type(
			'tpd_event',
			array(
				'labels'             => array(
					'name'          => __( 'Events & Webinars', 'tpd-tool' ),
					'singular_name' => __( 'Event / Webinar', 'tpd-tool' ),
					'menu_name'     => __( 'Events & Webinars', 'tpd-tool' ),
				),
				'public'             => true,
				'has_archive'        => 'events',
				'rewrite'            => array( 'slug' => 'event', 'with_front' => false ),
				'menu_position'      => 8,
				'menu_icon'          => 'dashicons-calendar-alt',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'show_in_rest'       => true,
			)
		);

		// 5. Inquiries / Leads CPT
		register_post_type(
			'tpd_inquiry',
			array(
				'labels'             => array(
					'name'          => __( 'Client Inquiries', 'tpd-tool' ),
					'singular_name' => __( 'Inquiry', 'tpd-tool' ),
					'menu_name'     => __( 'Inquiries / Leads', 'tpd-tool' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'menu_position'      => 9,
				'menu_icon'          => 'dashicons-email-alt',
				'supports'           => array( 'title', 'custom-fields' ),
			)
		);

		// 6. Chat Messages CPT
		register_post_type(
			'tpd_message',
			array(
				'labels'             => array(
					'name'          => __( 'Chat Messages', 'tpd-tool' ),
					'singular_name' => __( 'Chat Message', 'tpd-tool' ),
					'menu_name'     => __( 'Chat Messages', 'tpd-tool' ),
				),
				'public'             => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'menu_position'      => 10,
				'menu_icon'          => 'dashicons-format-chat',
				'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
			)
		);
	}

	public static function register_acf_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Field Group: Advisor Profile
		acf_add_local_field_group( array(
			'key'      => 'group_tpd_tool_advisor',
			'title'    => __( 'Advisor Profile & Credentials', 'tpd-tool' ),
			'fields'   => array(
				array(
					'key'   => 'field_tab_adv_biz',
					'label' => __( 'Business Info', 'tpd-tool' ),
					'type'  => 'tab',
				),
				array(
					'key'   => 'field_tpd_assigned_user',
					'label' => __( 'Linked WP User Account', 'tpd-tool' ),
					'name'  => 'tpd_assigned_user',
					'type'  => 'user',
					'role'  => array( 'travel_advisor', 'administrator' ),
				),
				array(
					'key'   => 'field_tpd_agency_name',
					'label' => __( 'Agency Name', 'tpd-tool' ),
					'name'  => 'tpd_agency_name',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_profile_handle',
					'label' => __( 'Tagline / Handle', 'tpd-tool' ),
					'name'  => 'tpd_profile_handle',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_phone',
					'label' => __( 'Direct Phone / Office', 'tpd-tool' ),
					'name'  => 'tpd_phone',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_city_state',
					'label' => __( 'City, State / Region', 'tpd-tool' ),
					'name'  => 'tpd_city_state',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tab_adv_cred',
					'label' => __( 'Credentials & Accreditations', 'tpd-tool' ),
					'type'  => 'tab',
				),
				array(
					'key'   => 'field_tpd_accreditations',
					'label' => __( 'Accreditations', 'tpd-tool' ),
					'name'  => 'tpd_accreditations',
					'type'  => 'checkbox',
					'choices' => array(
						'ARC'  => 'ARC',
						'CLIA' => 'CLIA',
						'IATA' => 'IATA',
						'TRUE' => 'TRUE',
						'ASTA' => 'ASTA (VTA)',
					),
				),
				array(
					'key'   => 'field_tab_adv_media',
					'label' => __( 'Headshot & Banner', 'tpd-tool' ),
					'type'  => 'tab',
				),
				array(
					'key'   => 'field_tpd_headshot',
					'label' => __( 'Headshot URL', 'tpd-tool' ),
					'name'  => 'tpd_headshot',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_cover_banner',
					'label' => __( 'Cover Banner URL', 'tpd-tool' ),
					'name'  => 'tpd_cover_banner',
					'type'  => 'text',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'travel_advisor',
					),
				),
			),
		) );

		// Field Group: Supplier Profile
		acf_add_local_field_group( array(
			'key'      => 'group_tpd_tool_supplier',
			'title'    => __( 'Supplier Company Listing', 'tpd-tool' ),
			'fields'   => array(
				array(
					'key'   => 'field_tpd_supp_assigned_user',
					'label' => __( 'Linked WP User Account', 'tpd-tool' ),
					'name'  => 'tpd_assigned_user',
					'type'  => 'user',
					'role'  => array( 'supplier', 'administrator' ),
				),
				array(
					'key'   => 'field_tpd_company_name',
					'label' => __( 'Company Name', 'tpd-tool' ),
					'name'  => 'tpd_company_name',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_supplier_category',
					'label' => __( 'Supplier Category', 'tpd-tool' ),
					'name'  => 'tpd_supplier_category',
					'type'  => 'select',
					'choices' => array(
						'river_cruises'   => 'River Cruises',
						'ocean_cruises'   => 'Ocean Cruises',
						'hotels_resorts'  => 'Hotels & Resorts',
						'tour_operator'   => 'Tour Operator',
						'rail_transport'  => 'Rail & Luxury Transport',
					),
				),
				array(
					'key'   => 'field_tpd_logo',
					'label' => __( 'Logo URL', 'tpd-tool' ),
					'name'  => 'tpd_logo',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_supplier_cover',
					'label' => __( 'Cover Photo URL', 'tpd-tool' ),
					'name'  => 'tpd_supplier_cover',
					'type'  => 'text',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'supplier_listing',
					),
				),
			),
		) );
	}
}

TPD_Tool_CPT::init();
