<?php
/**
 * Custom Post Types, Taxonomies, Native Meta Boxes, Bi-Directional Sync & Dynamic ACF Bridge for TPD Tool
 * Connects WP Admin Custom Post Types (travel_advisor, supplier_listing, supplier_blog, tpd_event, tpd_inquiry)
 * 100% bi-directionally with the Frontend Advisor & Supplier Dashboards and ACF Field Groups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_CPT {

	/**
	 * Built-in ACF group keys so we don't duplicate core fields when rendering user-created ACF groups
	 */
	const BUILTIN_ACF_GROUPS = array( 'group_tpd_tool_advisor', 'group_tpd_tool_supplier' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_all' ), 0 );
		add_action( 'acf/init', array( __CLASS__, 'register_acf_fields' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_native_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_native_meta_boxes_and_sync_user' ), 20, 2 );

		// Custom Admin Columns for travel_advisor and supplier_listing
		add_filter( 'manage_travel_advisor_posts_columns', array( __CLASS__, 'advisor_admin_columns' ) );
		add_action( 'manage_travel_advisor_posts_custom_column', array( __CLASS__, 'advisor_admin_column_content' ), 10, 2 );

		add_filter( 'manage_supplier_listing_posts_columns', array( __CLASS__, 'supplier_admin_columns' ) );
		add_action( 'manage_supplier_listing_posts_custom_column', array( __CLASS__, 'supplier_admin_column_content' ), 10, 2 );
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
			array( 'travel_advisor', 'supplier_listing' ),
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
					'add_new_item'  => __( 'Add New Travel Advisor Profile', 'tpd-tool' ),
					'edit_item'     => __( 'Edit Travel Advisor Profile', 'tpd-tool' ),
				),
				'public'             => true,
				'has_archive'        => 'advisors',
				'rewrite'            => array( 'slug' => 'advisor-profile', 'with_front' => false ),
				'menu_position'      => 5,
				'menu_icon'          => 'dashicons-businessperson',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ),
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
					'add_new_item'  => __( 'Add New Supplier Listing', 'tpd-tool' ),
					'edit_item'     => __( 'Edit Supplier Listing', 'tpd-tool' ),
				),
				'public'             => true,
				'has_archive'        => 'suppliers',
				'rewrite'            => array( 'slug' => 'suppliers', 'with_front' => false ),
				'menu_position'      => 6,
				'menu_icon'          => 'dashicons-building',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author', 'custom-fields' ),
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
				'supports'           => array( 'title', 'editor', 'custom-fields' ),
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

	/**
	 * Custom Admin List Columns for Travel Advisors
	 */
	public static function advisor_admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['tpd_user']   = __( 'Linked WP User', 'tpd-tool' );
				$new['tpd_agency'] = __( 'Agency Name', 'tpd-tool' );
				$new['tpd_phone']  = __( 'Phone', 'tpd-tool' );
				$new['tpd_tier']   = __( 'Membership Plan', 'tpd-tool' );
			}
		}
		return $new;
	}

	public static function advisor_admin_column_content( $column, $post_id ) {
		$uid = (int) get_post_meta( $post_id, 'tpd_assigned_user', true );
		$user = $uid ? get_userdata( $uid ) : null;

		switch ( $column ) {
			case 'tpd_user':
				if ( $user ) {
					printf( '<strong>%s</strong><br><small>%s</small>', esc_html( $user->display_name ), esc_html( $user->user_email ) );
				} else {
					echo '<span style="color:#94a3b8;">Unlinked</span>';
				}
				break;
			case 'tpd_agency':
				echo esc_html( get_post_meta( $post_id, 'tpd_agency_name', true ) ?: '—' );
				break;
			case 'tpd_phone':
				echo esc_html( get_post_meta( $post_id, 'tpd_phone', true ) ?: '—' );
				break;
			case 'tpd_tier':
				$tier = $uid ? TPD_Tool_Tiers::get_user_tier( $uid ) : ( get_post_meta( $post_id, 'tpd_user_tier', true ) ?: 'basic' );
				printf( '<span style="background:#e0f2fe;color:#0369a1;padding:3px 8px;border-radius:4px;font-weight:700;font-size:11px;text-transform:uppercase;">%s</span>', esc_html( $tier ) );
				break;
		}
	}

	/**
	 * Custom Admin List Columns for Supplier Listings
	 */
	public static function supplier_admin_columns( $columns ) {
		$new = array();
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['tpd_rep']      = __( 'Linked Supplier Rep', 'tpd-tool' );
				$new['tpd_category'] = __( 'Category / Tagline', 'tpd-tool' );
				$new['tpd_featured'] = __( 'Spotlight', 'tpd-tool' );
				$new['tpd_tier']     = __( 'Plan Tier', 'tpd-tool' );
			}
		}
		return $new;
	}

	public static function supplier_admin_column_content( $column, $post_id ) {
		$uid = (int) get_post_meta( $post_id, 'tpd_assigned_user', true );
		$user = $uid ? get_userdata( $uid ) : null;

		switch ( $column ) {
			case 'tpd_rep':
				if ( $user ) {
					$pos = get_post_meta( $post_id, 'tpd_position_title', true ) ?: get_user_meta( $uid, 'tpd_position_title', true );
					printf( '<strong>%s</strong>%s<br><small>%s</small>', esc_html( $user->display_name ), $pos ? ' (' . esc_html( $pos ) . ')' : '', esc_html( $user->user_email ) );
				} else {
					echo '<span style="color:#94a3b8;">Unclaimed Directory Listing</span>';
				}
				break;
			case 'tpd_category':
				echo esc_html( get_post_meta( $post_id, 'tpd_tagline', true ) ?: get_post_meta( $post_id, 'tpd_supplier_category', true ) ?: '—' );
				break;
			case 'tpd_featured':
				$feat = get_post_meta( $post_id, 'tpd_featured_supplier', true );
				echo ( 'yes' === $feat ) ? '<span style="color:#d97706;font-weight:700;">★ Featured</span>' : 'Standard';
				break;
			case 'tpd_tier':
				$tier = $uid ? TPD_Tool_Tiers::get_user_tier( $uid ) : ( get_post_meta( $post_id, 'tpd_user_tier', true ) ?: 'basic' );
				printf( '<span style="background:#ede9fe;color:#6d28d9;padding:3px 8px;border-radius:4px;font-weight:700;font-size:11px;text-transform:uppercase;">%s</span>', esc_html( $tier ) );
				break;
		}
	}

	/**
	 * Register Native WordPress Meta Boxes (Bi-Directional Sync with Frontend Dashboards)
	 */
	public static function register_native_meta_boxes() {
		add_meta_box(
			'tpd_advisor_meta_box',
			__( 'TPD Advisor Profile, Agency & Credentials (Synced with Advisor Dashboard)', 'tpd-tool' ),
			array( __CLASS__, 'render_advisor_meta_box' ),
			'travel_advisor',
			'normal',
			'high'
		);

		add_meta_box(
			'tpd_supplier_meta_box',
			__( 'TPD Supplier Listing, Showcase & Representative Info (Synced with Supplier Dashboard)', 'tpd-tool' ),
			array( __CLASS__, 'render_supplier_meta_box' ),
			'supplier_listing',
			'normal',
			'high'
		);

		add_meta_box(
			'tpd_event_meta_box',
			__( 'TPD Event / Webinar Schedule Details', 'tpd-tool' ),
			array( __CLASS__, 'render_event_meta_box' ),
			'tpd_event',
			'normal',
			'high'
		);

		add_meta_box(
			'tpd_inquiry_meta_box',
			__( 'TPD Client Lead / Inquiry Details', 'tpd-tool' ),
			array( __CLASS__, 'render_inquiry_meta_box' ),
			'tpd_inquiry',
			'normal',
			'high'
		);
	}

	public static function render_advisor_meta_box( $post ) {
		wp_nonce_field( 'tpd_save_cpt_meta', 'tpd_cpt_meta_nonce' );
		$pid = $post->ID;
		$uid = (int) get_post_meta( $pid, 'tpd_assigned_user', true );
		$users = get_users( array( 'role__in' => array( 'travel_advisor', 'administrator' ) ) );
		$plans = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'advisor' ) : array();
		$cur_tier = $uid ? TPD_Tool_Tiers::get_user_tier( $uid ) : ( get_post_meta( $pid, 'tpd_user_tier', true ) ?: 'basic' );

		$fields = array(
			'tpd_agency_name'           => __( 'Agency Name', 'tpd-tool' ),
			'tpd_agency_address'        => __( 'Agency Address', 'tpd-tool' ),
			'tpd_phone'                 => __( 'Phone Number', 'tpd-tool' ),
			'tpd_location'              => __( 'Advisor Location (City, State, Country)', 'tpd-tool' ),
			'tpd_website'               => __( 'Website URL', 'tpd-tool' ),
			'tpd_business_structure'    => __( 'Advisor Role', 'tpd-tool' ),
			'tpd_agency_structure'      => __( 'Agency Structure', 'tpd-tool' ),
			'tpd_years_experience'      => __( 'Years in Industry', 'tpd-tool' ),
			'tpd_agency_advisors_count' => __( 'Advisors Count in Agency', 'tpd-tool' ),
			'tpd_personal_sales_volume' => __( 'Personal Annual Sales Volume', 'tpd-tool' ),
			'tpd_agency_sales_volume'   => __( 'Agency Annual Sales Volume', 'tpd-tool' ),
			'tpd_consortia'             => __( 'Consortia Affiliation', 'tpd-tool' ),
			'tpd_host_agency'           => __( 'Host Agency / Franchise', 'tpd-tool' ),
			'tpd_clia_number'           => __( 'CLIA Number', 'tpd-tool' ),
			'tpd_iata_number'           => __( 'IATA Number', 'tpd-tool' ),
			'tpd_arc_number'            => __( 'ARC Number', 'tpd-tool' ),
			'tpd_true_number'           => __( 'TRUE Number', 'tpd-tool' ),
			'tpd_headshot_url'          => __( 'Headshot Photo URL', 'tpd-tool' ),
			'tpd_logo_url'              => __( 'Agency Logo URL', 'tpd-tool' ),
			'tpd_banner_url'            => __( 'Profile Banner URL', 'tpd-tool' ),
		);
		?>
		<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; padding:12px 0;">
			<div>
				<label style="font-weight:700; display:block; margin-bottom:5px;"><?php esc_html_e( 'Linked WordPress User Account', 'tpd-tool' ); ?></label>
				<select name="tpd_assigned_user" style="width:100%; padding:8px;">
					<option value="0"><?php esc_html_e( '— Select Travel Advisor User —', 'tpd-tool' ); ?></option>
					<?php foreach ( $users as $u ) : ?>
						<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $uid, $u->ID ); ?>>
							<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label style="font-weight:700; display:block; margin-bottom:5px;"><?php esc_html_e( 'Membership Plan Tier', 'tpd-tool' ); ?></label>
				<select name="tpd_user_tier" style="width:100%; padding:8px;">
					<?php foreach ( $plans as $p_slug => $p_info ) : ?>
						<option value="<?php echo esc_attr( $p_slug ); ?>" <?php selected( $cur_tier, $p_slug ); ?>>
							<?php echo esc_html( $p_info['name'] . ' ($' . $p_info['monthly_price'] . '/mo)' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php foreach ( $fields as $meta_key => $label ) :
				$val = get_post_meta( $pid, $meta_key, true );
				if ( '' === $val && $uid ) {
					$val = get_user_meta( $uid, $meta_key, true );
				}
			?>
				<div>
					<label style="font-weight:600; display:block; margin-bottom:4px;"><?php echo esc_html( $label ); ?></label>
					<input type="text" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( $val ); ?>" style="width:100%; padding:7px 10px;">
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function render_supplier_meta_box( $post ) {
		wp_nonce_field( 'tpd_save_cpt_meta', 'tpd_cpt_meta_nonce' );
		$pid = $post->ID;
		$uid = (int) get_post_meta( $pid, 'tpd_assigned_user', true );
		$users = get_users( array( 'role__in' => array( 'supplier', 'administrator' ) ) );
		$plans = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'supplier' ) : array();
		$cur_tier = $uid ? TPD_Tool_Tiers::get_user_tier( $uid ) : ( get_post_meta( $pid, 'tpd_user_tier', true ) ?: 'basic' );
		$featured = get_post_meta( $pid, 'tpd_featured_supplier', true ) ?: 'no';

		$fields = array(
			'tpd_company_name'         => __( 'Company / Brand Name', 'tpd-tool' ),
			'tpd_tagline'              => __( 'Tagline / Value Proposition', 'tpd-tool' ),
			'tpd_position_title'       => __( 'Primary Rep Position / Title', 'tpd-tool' ),
			'tpd_primary_rep_phone'    => __( 'Advisor Booking / Rep Phone', 'tpd-tool' ),
			'tpd_booking_portal_url'   => __( 'Advisor Booking Portal URL', 'tpd-tool' ),
			'tpd_headquarters'         => __( 'Headquarters Location', 'tpd-tool' ),
			'tpd_country_of_residence' => __( 'Country', 'tpd-tool' ),
			'tpd_agent_rewards'        => __( 'Agent Rewards Program (Yes/No)', 'tpd-tool' ),
			'tpd_member_ustoa'         => __( 'Member of USTOA (Yes/No)', 'tpd-tool' ),
			'tpd_member_asta'          => __( 'Member of ASTA (Yes/No)', 'tpd-tool' ),
			'tpd_logo_url'             => __( 'Brand Logo URL', 'tpd-tool' ),
			'tpd_banner_url'           => __( 'Showcase Banner URL', 'tpd-tool' ),
			'tpd_main_video_url'       => __( 'Main Showcase Video URL', 'tpd-tool' ),
		);
		?>
		<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; padding:12px 0;">
			<div>
				<label style="font-weight:700; display:block; margin-bottom:5px;"><?php esc_html_e( 'Linked Supplier User', 'tpd-tool' ); ?></label>
				<select name="tpd_assigned_user" style="width:100%; padding:8px;">
					<option value="0"><?php esc_html_e( '— Unclaimed / Select Supplier User —', 'tpd-tool' ); ?></option>
					<?php foreach ( $users as $u ) : ?>
						<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $uid, $u->ID ); ?>>
							<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label style="font-weight:700; display:block; margin-bottom:5px;"><?php esc_html_e( 'Supplier Plan Tier', 'tpd-tool' ); ?></label>
				<select name="tpd_user_tier" style="width:100%; padding:8px;">
					<?php foreach ( $plans as $p_slug => $p_info ) : ?>
						<option value="<?php echo esc_attr( $p_slug ); ?>" <?php selected( $cur_tier, $p_slug ); ?>>
							<?php echo esc_html( $p_info['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label style="font-weight:700; display:block; margin-bottom:5px;"><?php esc_html_e( 'Featured Spotlight Status', 'tpd-tool' ); ?></label>
				<select name="tpd_featured_supplier" style="width:100%; padding:8px;">
					<option value="no" <?php selected( $featured, 'no' ); ?>><?php esc_html_e( 'Standard Placement', 'tpd-tool' ); ?></option>
					<option value="yes" <?php selected( $featured, 'yes' ); ?>><?php esc_html_e( '★ Featured on Advisor Dashboard', 'tpd-tool' ); ?></option>
				</select>
			</div>
		</div>
		<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; padding-top:8px;">
			<?php foreach ( $fields as $meta_key => $label ) :
				$val = get_post_meta( $pid, $meta_key, true );
			?>
				<div>
					<label style="font-weight:600; display:block; margin-bottom:4px;"><?php echo esc_html( $label ); ?></label>
					<input type="text" name="<?php echo esc_attr( $meta_key ); ?>" value="<?php echo esc_attr( $val ); ?>" style="width:100%; padding:7px 10px;">
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function render_event_meta_box( $post ) {
		wp_nonce_field( 'tpd_save_cpt_meta', 'tpd_cpt_meta_nonce' );
		$pid = $post->ID;
		?>
		<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; padding:10px 0;">
			<div>
				<label style="font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e( 'Event Date (YYYY-MM-DD)', 'tpd-tool' ); ?></label>
				<input type="date" name="_tpd_event_date" value="<?php echo esc_attr( get_post_meta( $pid, '_tpd_event_date', true ) ?: gmdate( 'Y-m-d' ) ); ?>" style="width:100%; padding:7px;">
			</div>
			<div>
				<label style="font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e( 'Event Time (e.g. 2:00 PM EST)', 'tpd-tool' ); ?></label>
				<input type="text" name="_tpd_event_time" value="<?php echo esc_attr( get_post_meta( $pid, '_tpd_event_time', true ) ?: '2:00 PM EST' ); ?>" style="width:100%; padding:7px;">
			</div>
			<div>
				<label style="font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e( 'Series / Host Label', 'tpd-tool' ); ?></label>
				<input type="text" name="_tpd_event_series" value="<?php echo esc_attr( get_post_meta( $pid, '_tpd_event_series', true ) ?: 'TARC Partner Webinar' ); ?>" style="width:100%; padding:7px;">
			</div>
		</div>
		<?php
	}

	public static function render_inquiry_meta_box( $post ) {
		wp_nonce_field( 'tpd_save_cpt_meta', 'tpd_cpt_meta_nonce' );
		$pid = $post->ID;
		?>
		<div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; padding:10px 0;">
			<div>
				<label style="font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e( 'Traveler Full Name', 'tpd-tool' ); ?></label>
				<input type="text" name="_tpd_traveler_name" value="<?php echo esc_attr( get_post_meta( $pid, '_tpd_traveler_name', true ) ); ?>" style="width:100%; padding:7px;">
			</div>
			<div>
				<label style="font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e( 'Destination', 'tpd-tool' ); ?></label>
				<input type="text" name="_tpd_destination" value="<?php echo esc_attr( get_post_meta( $pid, '_tpd_destination', true ) ); ?>" style="width:100%; padding:7px;">
			</div>
			<div>
				<label style="font-weight:600; display:block; margin-bottom:4px;"><?php esc_html_e( 'Estimated Budget', 'tpd-tool' ); ?></label>
				<input type="text" name="_tpd_budget" value="<?php echo esc_attr( get_post_meta( $pid, '_tpd_budget', true ) ); ?>" style="width:100%; padding:7px;">
			</div>
		</div>
		<?php
	}

	/**
	 * Save Native Meta Boxes in WP Admin & Sync Bi-Directionally with Linked User Meta
	 */
	public static function save_native_meta_boxes_and_sync_user( $post_id, $post ) {
		if ( ! isset( $_POST['tpd_cpt_meta_nonce'] ) || ! wp_verify_nonce( $_POST['tpd_cpt_meta_nonce'], 'tpd_save_cpt_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$uid = isset( $_POST['tpd_assigned_user'] ) ? absint( $_POST['tpd_assigned_user'] ) : (int) get_post_meta( $post_id, 'tpd_assigned_user', true );
		if ( isset( $_POST['tpd_assigned_user'] ) ) {
			update_post_meta( $post_id, 'tpd_assigned_user', $uid );
		}

		if ( isset( $_POST['tpd_user_tier'] ) ) {
			$tier = sanitize_text_field( wp_unslash( $_POST['tpd_user_tier'] ) );
			update_post_meta( $post_id, 'tpd_user_tier', $tier );
			if ( $uid ) {
				update_user_meta( $uid, 'tpd_user_tier', $tier );
			}
		}

		$all_meta_keys = array(
			'tpd_agency_name', 'tpd_agency_address', 'tpd_phone', 'tpd_location', 'tpd_website',
			'tpd_business_structure', 'tpd_agency_structure', 'tpd_years_experience', 'tpd_agency_advisors_count',
			'tpd_personal_sales_volume', 'tpd_agency_sales_volume', 'tpd_consortia', 'tpd_host_agency',
			'tpd_clia_number', 'tpd_iata_number', 'tpd_arc_number', 'tpd_true_number',
			'tpd_headshot_url', 'tpd_logo_url', 'tpd_banner_url',
			'tpd_company_name', 'tpd_tagline', 'tpd_position_title', 'tpd_primary_rep_phone',
			'tpd_booking_portal_url', 'tpd_headquarters', 'tpd_country_of_residence',
			'tpd_agent_rewards', 'tpd_member_ustoa', 'tpd_member_asta', 'tpd_main_video_url',
			'tpd_featured_supplier', '_tpd_event_date', '_tpd_event_time', '_tpd_event_series',
			'_tpd_traveler_name', '_tpd_destination', '_tpd_budget',
		);

		foreach ( $all_meta_keys as $mk ) {
			if ( isset( $_POST[ $mk ] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST[ $mk ] ) );
				update_post_meta( $post_id, $mk, $val );
				if ( $uid ) {
					update_user_meta( $uid, $mk, $val );
				}
			}
		}

		if ( 'tpd_event' === $post->post_type && ! empty( $_POST['_tpd_event_date'] ) ) {
			$ts = strtotime( sanitize_text_field( wp_unslash( $_POST['_tpd_event_date'] ) ) );
			if ( $ts ) {
				update_post_meta( $post_id, '_tpd_event_month', strtoupper( gmdate( 'M', $ts ) ) );
				update_post_meta( $post_id, '_tpd_event_day', gmdate( 'd', $ts ) );
			}
		}
	}

	/**
	 * Register ACF Field Groups (Aligned with Frontend Dashboard Meta Keys)
	 */
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
					'key'   => 'field_tpd_phone',
					'label' => __( 'Direct Phone', 'tpd-tool' ),
					'name'  => 'tpd_phone',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_location',
					'label' => __( 'Location (City, State, Country)', 'tpd-tool' ),
					'name'  => 'tpd_location',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_website',
					'label' => __( 'Website URL', 'tpd-tool' ),
					'name'  => 'tpd_website',
					'type'  => 'url',
				),
				array(
					'key'   => 'field_tpd_headshot_url',
					'label' => __( 'Headshot Photo URL', 'tpd-tool' ),
					'name'  => 'tpd_headshot_url',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_logo_url',
					'label' => __( 'Agency Logo URL', 'tpd-tool' ),
					'name'  => 'tpd_logo_url',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_banner_url',
					'label' => __( 'Banner Photo URL', 'tpd-tool' ),
					'name'  => 'tpd_banner_url',
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
					'key'   => 'field_tpd_tagline',
					'label' => __( 'Tagline', 'tpd-tool' ),
					'name'  => 'tpd_tagline',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_supp_logo_url',
					'label' => __( 'Logo URL', 'tpd-tool' ),
					'name'  => 'tpd_logo_url',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_tpd_supp_banner_url',
					'label' => __( 'Showcase Banner URL', 'tpd-tool' ),
					'name'  => 'tpd_banner_url',
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

	/**
	 * Retrieve Dynamic Fields Assigned to a Post Type via ACF Plugin OR Admin Custom Fields Builder
	 */
	public static function get_dynamic_fields( $post_type, $context = 'dashboard' ) {
		$dynamic_fields = array();
		$target_role    = ( 'supplier_listing' === $post_type ) ? 'supplier' : 'advisor';

		// 1. Admin-Configured Custom Fields from TPD Super Admin Hub
		if ( class_exists( 'TPD_Tool_Settings' ) ) {
			$admin_cfs = TPD_Tool_Settings::get_custom_fields( $target_role );
			foreach ( $admin_cfs as $cf_key => $cf ) {
				if ( 'registration' === $context && empty( $cf['show_on_registration'] ) ) {
					continue;
				}
				$dynamic_fields[ $cf_key ] = array(
					'key'      => $cf_key,
					'name'     => $cf_key,
					'label'    => $cf['label'],
					'type'     => $cf['type'],
					'required' => ! empty( $cf['required'] ),
					'choices'  => ! empty( $cf['options'] ) ? array_combine( $cf['options'], $cf['options'] ) : array(),
					'source'   => 'tpd_admin',
				);
			}
		}

		// 2. User-Created ACF Field Groups assigned to this CPT in WP Admin -> ACF
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
			foreach ( $groups as $group ) {
				if ( in_array( $group['key'], self::BUILTIN_ACF_GROUPS, true ) ) {
					continue;
				}
				$acf_fields = acf_get_fields( $group['key'] );
				if ( ! empty( $acf_fields ) ) {
					foreach ( $acf_fields as $af ) {
						if ( empty( $af['name'] ) || 'tab' === $af['type'] || 'message' === $af['type'] ) {
							continue;
						}
						$dynamic_fields[ $af['name'] ] = array(
							'key'        => $af['key'],
							'name'       => $af['name'],
							'label'      => $af['label'],
							'type'       => $af['type'],
							'required'   => ! empty( $af['required'] ),
							'choices'    => isset( $af['choices'] ) ? (array) $af['choices'] : array(),
							'group_name' => $group['title'],
							'source'     => 'acf',
						);
					}
				}
			}
		}

		return $dynamic_fields;
	}

	/**
	 * Render Dynamic ACF & Admin Custom Fields HTML inside Frontend Dashboards or Registration Forms
	 */
	public static function render_dynamic_fields_html( $post_type, $post_id = 0, $user_id = 0, $context = 'dashboard' ) {
		$fields = self::get_dynamic_fields( $post_type, $context );
		if ( empty( $fields ) ) {
			return;
		}
		?>
		<div class="tpd-dynamic-custom-fields-section mt-3" style="border-top:1px dashed #cbd5e1; padding-top:18px; margin-top:18px;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
				<h4 style="margin:0; font-size:14.5px; font-weight:700; color:#0b1526;">
					<i class="fa-solid fa-wand-magic-sparkles text-blue"></i> <?php esc_html_e( 'Additional Custom & ACF Profile Fields', 'tpd-tool' ); ?>
				</h4>
				<span style="font-size:11px; color:#64748b; background:#f1f5f9; padding:3px 8px; border-radius:6px;">
					<?php esc_html_e( 'Synced with WP Admin & ACF', 'tpd-tool' ); ?>
				</span>
			</div>
			<div class="tpd-form-grid-2">
				<?php foreach ( $fields as $f_name => $f ) :
					$val = '';
					if ( $post_id ) {
						$val = function_exists( 'get_field' ) ? get_field( $f_name, $post_id ) : get_post_meta( $post_id, $f_name, true );
						if ( '' === $val || null === $val ) {
							$val = get_post_meta( $post_id, $f_name, true );
						}
					}
					if ( ( '' === $val || null === $val ) && $user_id ) {
						$val = get_user_meta( $user_id, $f_name, true );
					}
					$req_attr = ! empty( $f['required'] ) ? 'required' : '';
					$req_star = ! empty( $f['required'] ) ? ' *' : '';
				?>
					<div class="tpd-form-group">
						<label>
							<?php echo esc_html( $f['label'] . $req_star ); ?>
							<?php if ( 'acf' === $f['source'] ) : ?>
								<small style="color:#64748b; font-weight:400;">(ACF)</small>
							<?php endif; ?>
						</label>
						<?php if ( 'textarea' === $f['type'] || 'wysiwyg' === $f['type'] ) : ?>
							<textarea name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" rows="3" class="tpd-textarea" <?php echo $req_attr; ?>><?php echo esc_textarea( is_scalar( $val ) ? $val : '' ); ?></textarea>
						<?php elseif ( 'select' === $f['type'] || 'radio' === $f['type'] ) : ?>
							<select name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" class="tpd-select" <?php echo $req_attr; ?>>
								<option value=""><?php esc_html_e( '— Select —', 'tpd-tool' ); ?></option>
								<?php foreach ( $f['choices'] as $c_val => $c_label ) : ?>
									<option value="<?php echo esc_attr( $c_val ); ?>" <?php selected( (string) $val, (string) $c_val ); ?>><?php echo esc_html( $c_label ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'checkbox' === $f['type'] || 'true_false' === $f['type'] ) : ?>
							<?php if ( ! empty( $f['choices'] ) ) :
								$arr_val = (array) $val;
							?>
								<div class="tpd-checkbox-grid">
									<?php foreach ( $f['choices'] as $c_val => $c_label ) : ?>
										<label class="tpd-pill-checkbox">
											<input type="checkbox" name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>][]" value="<?php echo esc_attr( $c_val ); ?>" <?php checked( in_array( (string) $c_val, array_map( 'strval', $arr_val ), true ) ); ?>>
											<span><?php echo esc_html( $c_label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<label class="tpd-pill-checkbox">
									<input type="checkbox" name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" value="1" <?php checked( ! empty( $val ) ); ?>>
									<span><?php esc_html_e( 'Yes / Enabled', 'tpd-tool' ); ?></span>
								</label>
							<?php endif; ?>
						<?php elseif ( 'number' === $f['type'] ) : ?>
							<input type="number" name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" value="<?php echo esc_attr( is_scalar( $val ) ? $val : '' ); ?>" class="tpd-input" <?php echo $req_attr; ?>>
						<?php elseif ( 'url' === $f['type'] ) : ?>
							<input type="url" name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" value="<?php echo esc_attr( is_scalar( $val ) ? $val : '' ); ?>" placeholder="https://" class="tpd-input" <?php echo $req_attr; ?>>
						<?php elseif ( 'date_picker' === $f['type'] || 'date' === $f['type'] ) : ?>
							<input type="date" name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" value="<?php echo esc_attr( is_scalar( $val ) ? $val : '' ); ?>" class="tpd-input" <?php echo $req_attr; ?>>
						<?php else : ?>
							<input type="text" name="tpd_dyn[<?php echo esc_attr( $f_name ); ?>]" value="<?php echo esc_attr( is_scalar( $val ) ? $val : '' ); ?>" class="tpd-input" <?php echo $req_attr; ?>>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Submitted Dynamic ACF & Custom Fields from Registration or Frontend Dashboards
	 */
	public static function save_dynamic_fields_submission( $post_type, $post_id, $user_id = 0 ) {
		if ( empty( $_POST['tpd_dyn'] ) || ! is_array( $_POST['tpd_dyn'] ) ) {
			return;
		}
		$fields = self::get_dynamic_fields( $post_type, 'all' );
		foreach ( $_POST['tpd_dyn'] as $f_name => $raw_val ) {
			$f_name = sanitize_key( $f_name );
			if ( is_array( $raw_val ) ) {
				$clean_val = array_map( 'sanitize_text_field', wp_unslash( $raw_val ) );
			} else {
				$clean_val = sanitize_textarea_field( wp_unslash( $raw_val ) );
			}

			if ( $post_id ) {
				update_post_meta( $post_id, $f_name, $clean_val );
				if ( function_exists( 'update_field' ) && isset( $fields[ $f_name ]['key'] ) && 'acf' === $fields[ $f_name ]['source'] ) {
					update_field( $fields[ $f_name ]['key'], $clean_val, $post_id );
				}
			}
			if ( $user_id ) {
				update_user_meta( $user_id, $f_name, $clean_val );
			}
		}
	}
}

TPD_Tool_CPT::init();
