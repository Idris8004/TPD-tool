<?php
/**
 * Supplier Portal - Supplier Dashboard
 * Dynamically connected to WP User Meta, supplier_listing CPT, ACF Field Groups,
 * and Super Admin Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

TPD_Tool_Roles::enforce_portal_access( 'supplier' );
$current_user_id = get_current_user_id();

$user       = get_userdata( $current_user_id );
$user_tier  = tpd_get_user_tier( $current_user_id );
$plan_info  = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plan( $user_tier ) : array( 'name' => ucfirst( $user_tier ) );
$first_name = ( $user && $user->first_name ) ? $user->first_name : ( $user ? $user->display_name : 'Partner' );
$last_name  = ( $user && $user->last_name ) ? $user->last_name : '';
$full_name  = ( $user && $user->display_name ) ? $user->display_name : trim( $first_name . ' ' . $last_name );

// All supplier_listing CPT posts owned by or assigned to this supplier
$my_listings = get_posts( array(
	'post_type'      => 'supplier_listing',
	'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
	'posts_per_page' => -1,
	'meta_query'     => array(
		'relation' => 'OR',
		array(
			'key'   => 'tpd_assigned_user',
			'value' => $current_user_id,
		),
		array(
			'key'   => 'post_author',
			'value' => $current_user_id,
		),
	),
) );
if ( empty( $my_listings ) ) {
	$my_listings = get_posts( array(
		'post_type'      => 'supplier_listing',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'author'         => $current_user_id,
	) );
}

$supplier_post_id = ! empty( $my_listings ) ? $my_listings[0]->ID : 0;

// Pull real metadata from User & Paired supplier_listing CPT
$company_name   = ( $supplier_post_id ? get_the_title( $supplier_post_id ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_company_name', true ) ?: __( 'Partner Brand', 'tpd-tool' );
$position_title = get_user_meta( $current_user_id, 'tpd_position_title', true ) ?: ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_position_title', true ) : '' ) ?: __( 'Trade Representative', 'tpd-tool' );
$rep_phone      = get_user_meta( $current_user_id, 'tpd_phone', true ) ?: ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_primary_rep_phone', true ) : '' );
$headshot_url   = get_user_meta( $current_user_id, 'tpd_headshot_url', true ) ?: ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_rep_headshot_url', true ) : '' );
$avatar_src     = $headshot_url ?: 'https://ui-avatars.com/api/?name=' . rawurlencode( $full_name ) . '&background=0284c7&color=fff&size=128';

// Listing Showcase fields
$tagline                = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_tagline', true ) : '';
$booking_url            = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_booking_portal_url', true ) : '';
$education_training_url = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_education_training_url', true ) : '';
$additional_website     = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_additional_website', true ) : '';
$additional_phone_1     = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_additional_phone_1', true ) : '';
$additional_phone_2     = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_additional_phone_2', true ) : '';
$headquarters           = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_headquarters', true ) : '' ) ?: $country;
$rewards                = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_agent_rewards', true ) : '' ) ?: 'Yes';
$rewards_desc           = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_agent_rewards_desc', true ) : '';
$ustoa                  = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_member_ustoa', true ) : '' ) ?: 'Yes';
$asta                   = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_member_asta', true ) : '' ) ?: 'Yes';
$clia                   = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_member_clia', true ) : '' ) ?: 'No';
$associations           = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_associations', true ) : '';
$logo_url               = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_logo_url', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_logo_url', true );
$banner_url             = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_banner_url', true ) : '' ) ?: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80';
$gallery_images         = class_exists( 'TPD_Tool_Profile_Editor' ) ? TPD_Tool_Profile_Editor::get_user_gallery( $current_user_id, $supplier_post_id ) : array();
$video_url              = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_main_video_url', true ) : '';
$promo_title            = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_promo_title', true ) : '';
$promo_desc             = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_promo_desc', true ) : '';
$description            = $supplier_post_id ? get_post_field( 'post_content', $supplier_post_id ) : '';
$public_url             = $supplier_post_id ? get_permalink( $supplier_post_id ) : home_url( '/suppliers/' );

// Social Media Links
$social_facebook  = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_social_facebook', true ) : '';
$social_twitter   = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_social_twitter', true ) : '';
$social_youtube   = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_social_youtube', true ) : '';
$social_instagram = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_social_instagram', true ) : '';
$social_tiktok    = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_social_tiktok', true ) : '';
$social_linkedin  = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_social_linkedin', true ) : '';

// Taxonomies (Destinations, Styles, Supplier Types)
$selected_destinations = $supplier_post_id ? wp_get_object_terms( $supplier_post_id, 'travel_destination', array( 'fields' => 'names' ) ) : array();
if ( is_wp_error( $selected_destinations ) || ! is_array( $selected_destinations ) ) {
	$selected_destinations = array();
}
$selected_styles = $supplier_post_id ? wp_get_object_terms( $supplier_post_id, 'travel_style', array( 'fields' => 'names' ) ) : array();
$selected_style  = ! empty( $selected_styles ) && ! is_wp_error( $selected_styles ) ? $selected_styles[0] : '';

$selected_stypes = $supplier_post_id ? wp_get_object_terms( $supplier_post_id, 'supplier_type', array( 'fields' => 'names' ) ) : array();
$selected_stype  = ! empty( $selected_stypes ) && ! is_wp_error( $selected_stypes ) ? $selected_stypes[0] : ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_supplier_type', true ) : '' );

// Standard Styles Options
$available_styles = array( 'Adventure', 'All-Inclusive', 'Beach & Island', 'Custom Itineraries', 'Expedition', 'Family & Multigenerational', 'Guided Tours', 'Luxury', 'Ocean Cruise', 'Rail & Train', 'River Cruise', 'Romantic & Honeymoon', 'Safari & Wildlife', 'Ski & Winter', 'Wellness & Spa' );

// Standard Supplier Types Options
$available_supplier_types = array( 'Tour Operator', 'Cruise Line', 'River Cruise', 'Transportation & Ferries', 'Hotel & Resort', 'Rail Operator', 'Tourism Board / DMO', 'Villa & Private Estate', 'Airline Partner', 'Destination Management Company (DMC)' );

// 6-Tab Membership Data
$is_supplier_enhanced = class_exists( 'TPD_Tool_Tiers' ) ? TPD_Tool_Tiers::is_enhanced_supplier( $current_user_id ) : false;
$conferences          = $supplier_post_id ? get_post_meta( $supplier_post_id, '_tpd_supplier_conferences', true ) : array();
$conferences          = is_array( $conferences ) ? $conferences : array();
$resources            = $supplier_post_id ? get_post_meta( $supplier_post_id, '_tpd_supplier_resources', true ) : array();
$resources            = is_array( $resources ) ? $resources : array();
$supplier_faqs        = $supplier_post_id ? get_post_meta( $supplier_post_id, '_tpd_supplier_faqs', true ) : array();
$supplier_faqs        = is_array( $supplier_faqs ) ? $supplier_faqs : array();
$supplier_videos      = $supplier_post_id ? get_post_meta( $supplier_post_id, '_tpd_supplier_videos', true ) : array();
$supplier_videos      = is_array( $supplier_videos ) ? $supplier_videos : array();

// Virtual Office Team Members
$team_members = $supplier_post_id ? get_post_meta( $supplier_post_id, '_tpd_team_members', true ) : array();
if ( empty( $team_members ) || ! is_array( $team_members ) ) {
	$team_members = array(
		array(
			'name'      => $full_name,
			'title'     => $position_title,
			'email'     => $user ? $user->user_email : '',
			'phone'     => $rep_phone,
			'photo_url' => $avatar_src,
		),
	);
}

$metrics       = TPD_Tool_Analytics::get_supplier_metrics( $supplier_post_id, $current_user_id );
$recent_convos = TPD_Tool_Chat::get_recent_conversations( $current_user_id );
$all_plans     = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'supplier' ) : array();
$form_cfg      = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_form_config() : array();
$countries     = ! empty( $form_cfg['countries'] ) ? $form_cfg['countries'] : array( 'United States', 'Canada', 'United Kingdom', 'Australia', 'Germany', 'France', 'Italy', 'Spain', 'Other' );
$faqs          = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_faqs() : array();

// All directory listings for Claim a Listing search
$all_directory_listings = get_posts( array(
	'post_type'      => 'supplier_listing',
	'posts_per_page' => 30,
	'post_status'    => 'publish',
) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Supplier Portal | Travel Partner Directory by TARC', 'tpd-tool' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="tpd-app-body tpd-theme-dark-sidebar">

<div class="tpd-portal-shell">
	<!-- 1. LEFT SIDEBAR -->
	<aside class="tpd-portal-sidebar">
		<div class="tpd-sidebar-brand">
			<div class="tpd-sb-logo-icon"><i class="fa-solid fa-compass"></i></div>
			<div class="tpd-sb-logo-copy">
				<span class="tpd-sb-title">TRAVEL PARTNER</span>
				<span class="tpd-sb-subtitle">DIRECTORY</span>
				<span class="tpd-sb-by">BY TARC</span>
			</div>
		</div>

		<nav class="tpd-sb-menu">
			<ul class="tpd-sb-nav-list">
				<li class="tpd-nav-item active">
					<a href="#tpd-supp-overview" class="tpd-tab-link" data-view="supp-overview">
						<i class="fa-solid fa-house"></i>
						<span><?php esc_html_e( 'Dashboard', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-listings" class="tpd-tab-link" data-view="supp-listings">
						<i class="fa-solid fa-building-flag"></i>
						<span><?php esc_html_e( 'Partner Listings', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-chat" class="tpd-tab-link" data-view="supp-chat">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( 'Chat & Messages', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-meetings" class="tpd-tab-link" data-view="supp-meetings">
						<i class="fa-regular fa-calendar-check"></i>
						<span><?php esc_html_e( 'Meeting Requests', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-settings" class="tpd-tab-link" data-view="supp-settings">
						<i class="fa-regular fa-id-card"></i>
						<span><?php esc_html_e( 'Account Info & Plan', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-partnership" class="tpd-tab-link" data-view="supp-partnership">
						<i class="fa-solid fa-handshake"></i>
						<span><?php esc_html_e( 'TARC Partnership', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-help" class="tpd-tab-link" data-view="supp-help">
						<i class="fa-regular fa-circle-question"></i>
						<span><?php esc_html_e( 'Help & Support', 'tpd-tool' ); ?></span>
					</a>
				</li>
			</ul>

			<!-- Dynamic Left Navigation Sidebar Square Ads (Directly Under Help & Support) -->
			<?php
			$left_sidebar_ads = TPD_Tool_Settings::get_left_sidebar_ads();
			if ( ! empty( $left_sidebar_ads ) ) :
			?>
				<div class="tpd-sb-ads-container">
					<?php foreach ( $left_sidebar_ads as $lad ) :
						if ( empty( $lad['image_url'] ) || ( isset( $lad['status'] ) && 'inactive' === $lad['status'] ) ) {
							continue;
						}
						$lad_link   = ! empty( $lad['link_url'] ) ? $lad['link_url'] : '#';
						$lad_target = ( ! empty( $lad['target'] ) && '_blank' === $lad['target'] ) || ( strpos( $lad_link, 'http' ) === 0 ) ? '_blank' : '_self';
						$lad_alt    = ! empty( $lad['alt_text'] ) ? $lad['alt_text'] : 'Partner Sponsor';
					?>
						<div class="tpd-sb-ad-card">
							<a href="<?php echo esc_url( $lad_link ); ?>" target="<?php echo esc_attr( $lad_target ); ?>" rel="noopener noreferrer" title="<?php echo esc_attr( $lad_alt ); ?>">
								<img src="<?php echo esc_url( $lad['image_url'] ); ?>" alt="<?php echo esc_attr( $lad_alt ); ?>" class="tpd-sb-ad-img">
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</nav>

		<div class="tpd-sb-bottom-actions">
			<a href="<?php echo esc_url( $public_url ); ?>" target="_blank" class="tpd-btn tpd-btn-outline-white tpd-btn-sm tpd-btn-block mb-3" style="text-decoration:none; text-align:center;">
				<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View My Public Listing', 'tpd-tool' ); ?>
			</a>
			<div class="tpd-sb-help-prompt tpd-tab-link" data-view="supp-help" style="cursor:pointer;">
				<i class="fa-regular fa-circle-question"></i>
				<div>
					<strong><?php esc_html_e( 'Need Help?', 'tpd-tool' ); ?></strong>
					<span><?php esc_html_e( 'Contact the TARC Team', 'tpd-tool' ); ?></span>
				</div>
			</div>
		</div>
	</aside>

	<!-- 2. MAIN WORKSPACE -->
	<div class="tpd-portal-body">
		<!-- Top Navigation Header -->
		<header class="tpd-portal-topbar">
			<div class="tpd-portal-title-area">
				<h2><?php esc_html_e( 'Supplier Portal', 'tpd-tool' ); ?></h2>
			</div>

			<div class="tpd-topbar-actions">
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/tpd-admin/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none;">
						<i class="fa-solid fa-shield-halved text-gold"></i> <?php esc_html_e( 'Super Admin Hub', 'tpd-tool' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( TPD_Tool_Roles::is_dual_role( $current_user_id ) || current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none; border-color:#00798c; color:#00798c; font-weight:700;">
						<i class="fa-solid fa-repeat"></i> <?php esc_html_e( 'Switch to Advisor Portal', 'tpd-tool' ); ?>
					</a>
				<?php endif; ?>

				<!-- Company Pill (Real Company Name) -->
				<div class="tpd-company-pill-dropdown tpd-tab-link" data-view="supp-listings" style="cursor:pointer;">
					<i class="fa-solid fa-crown text-gold"></i>
					<span class="tpd-company-name"><?php echo esc_html( $company_name ); ?></span>
				</div>

				<!-- User Profile Pill -->
				<div class="tpd-user-pill-dropdown tpd-tab-link" data-view="supp-settings" style="cursor:pointer;">
					<img src="<?php echo esc_url( $avatar_src ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" class="tpd-user-avatar-sm">
					<div class="tpd-user-pill-info">
						<span class="tpd-user-name"><?php echo esc_html( $full_name ); ?></span>
						<span class="tpd-user-role-label"><span class="tpd-user-pos-label"><?php echo esc_html( $position_title ); ?></span> · <strong class="tpd-tier-tag"><?php echo esc_html( strtoupper( $user_tier ) ); ?></strong></span>
					</div>
				</div>

				<a href="<?php echo esc_url( wp_logout_url( home_url( '/supplier-login/' ) ) ); ?>" class="tpd-top-icon-btn" title="<?php esc_attr_e( 'Log Out', 'tpd-tool' ); ?>" style="text-decoration:none; color:#64748b;">
					<i class="fa-solid fa-right-from-bracket"></i>
				</a>
			</div>
		</header>

		<main class="tpd-workspace-content">
			<?php if ( isset( $_GET['cross_role_notice'] ) && 'advisor' === $_GET['cross_role_notice'] && ! TPD_Tool_Roles::is_advisor( $current_user_id ) ) : ?>
				<div style="background:#f0fdfa; border:1.5px solid #00798c; border-radius:12px; padding:16px 22px; margin-bottom:22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
					<div style="display:flex; align-items:center; gap:12px;">
						<i class="fa-solid fa-user-tie" style="font-size:22px; color:#00798c;"></i>
						<div>
							<strong style="color:#013243; font-size:14.5px; display:block;"><?php esc_html_e( 'Your email is currently registered as a Supplier Partner only.', 'tpd-tool' ); ?></strong>
							<span style="color:#475569; font-size:13px;"><?php esc_html_e( 'Would you like to also register as a Travel Advisor with the same email and access both dashboards?', 'tpd-tool' ); ?></span>
						</div>
					</div>
					<a href="<?php echo esc_url( home_url( '/advisor-registration/?cross_role=1' ) ); ?>" class="tpd-btn tpd-btn-sm tpd-btn-primary" style="text-decoration:none; background:#00798c; border-color:#00798c;">
						<i class="fa-solid fa-plus-circle"></i> <?php esc_html_e( 'Continue as Travel Advisor Also', 'tpd-tool' ); ?>
					</a>
				</div>
			<?php endif; ?>
			<!-- VIEW 1: OVERVIEW -->
			<div id="tpd-supp-overview" class="tpd-tab-panel active">
				<!-- Welcome Banner (Real Company Name & Banner) -->
				<div class="tpd-hero-welcome-card tpd-supplier-hero-card" style="background-image: linear-gradient(90deg, rgba(11, 21, 38, 0.9) 0%, rgba(11, 21, 38, 0.4) 60%, rgba(11, 21, 38, 0.1) 100%), url('<?php echo esc_url( $banner_url ); ?>');">
					<div class="tpd-shc-badge-card">
						<i class="fa-solid fa-crown text-gold fa-2x"></i>
						<h3 class="tpd-company-name"><?php echo esc_html( $company_name ); ?></h3>
						<small><?php echo esc_html( strtoupper( $tagline ?: 'VERIFIED SUPPLIER PARTNER' ) ); ?></small>
					</div>

					<div class="tpd-shc-text">
						<h1>Welcome back, <span><?php echo esc_html( $first_name ); ?>!</span></h1>
						<p><?php esc_html_e( 'Manage your brand presence, connect with travel advisors, and grow your trade partnerships.', 'tpd-tool' ); ?></p>
					</div>

					<div class="tpd-shc-actions">
						<a href="<?php echo esc_url( $public_url ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline-white" style="text-decoration:none;">
							<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View Public Listing', 'tpd-tool' ); ?>
						</a>
					</div>
				</div>

				<!-- 4 Metric Stat Cards -->
				<div class="tpd-metrics-row-4" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin:24px 0;">
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-users text-blue"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['views']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['views']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['views']['trend'] . ' ' . $metrics['views']['sub'] ); ?></span>
					</div>

					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-comments text-cyan"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['conversations']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php esc_html_e( 'Active Conversations', 'tpd-tool' ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['conversations']['trend'] ); ?></span>
					</div>

					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-regular fa-calendar-check text-purple"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['meetings']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['meetings']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['meetings']['trend'] ); ?></span>
					</div>

					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-star text-gold"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['saves']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['saves']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['saves']['trend'] ); ?></span>
					</div>
				</div>

				<!-- 3-Column Layout -->
				<div class="tpd-supplier-dashboard-grid-3">
					<!-- COLUMN 1: Virtual Office & Manage Content -->
					<div class="tpd-sd-col">
						<div class="tpd-card-box tpd-virtual-office-card">
							<div class="tpd-box-header-clean">
								<div>
									<h4><?php esc_html_e( 'VIRTUAL OFFICE', 'tpd-tool' ); ?></h4>
									<span class="tpd-online-badge-text"><i class="fa-solid fa-circle text-green"></i> <?php echo count( $team_members ); ?> Team Member<?php echo count( $team_members ) > 1 ? 's' : ''; ?> Online</span>
								</div>
								<a href="#tpd-supp-listings" class="tpd-clean-link tpd-tab-link" data-view="supp-listings" data-subtab="set-team"><?php esc_html_e( 'Manage Team', 'tpd-tool' ); ?></a>
							</div>

							<div class="tpd-vo-members-list">
								<?php foreach ( $team_members as $tm ) :
									$tm_avatar = ! empty( $tm['photo_url'] ) ? $tm['photo_url'] : 'https://ui-avatars.com/api/?name=' . rawurlencode( $tm['name'] ) . '&background=2563eb&color=fff&size=96';
								?>
									<div class="tpd-vo-member-row">
										<img src="<?php echo esc_url( $tm_avatar ); ?>" alt="<?php echo esc_attr( $tm['name'] ); ?>" class="tpd-vom-avatar">
										<div class="tpd-vom-info">
											<strong><?php echo esc_html( $tm['name'] ); ?></strong>
											<span class="tpd-vom-role"><?php echo esc_html( ! empty( $tm['title'] ) ? $tm['title'] : 'Trade Representative' ); ?></span>
											<?php if ( ! empty( $tm['email'] ) ) : ?>
												<span class="tpd-vom-spec"><?php echo esc_html( $tm['email'] ); ?></span>
											<?php endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- Manage Your Content Tiles -->
						<div class="tpd-card-box tpd-manage-content-card">
							<h4><?php esc_html_e( 'MANAGE YOUR CONTENT', 'tpd-tool' ); ?></h4>
							<div class="tpd-myc-grid-8">
								<a href="#tpd-supp-listings" class="tpd-myc-tile tpd-tab-link" data-view="supp-listings" data-subtab="set-basic">
									<i class="fa-regular fa-file-lines"></i>
									<strong>Basic Information</strong>
									<p>Update description, tagline, booking portal.</p>
								</a>
								<a href="#tpd-supp-listings" class="tpd-myc-tile tpd-tab-link" data-view="supp-listings" data-subtab="set-media">
									<i class="fa-regular fa-images"></i>
									<strong>Images & Media</strong>
									<p>Upload photos, videos, brand assets.</p>
								</a>
								<a href="#tpd-supp-listings" class="tpd-myc-tile tpd-tab-link" data-view="supp-listings" data-subtab="set-resources">
									<i class="fa-regular fa-folder-open"></i>
									<strong>Advisor Resources</strong>
									<p>Add brochures, training, sales tools.</p>
								</a>
								<a href="#tpd-supp-listings" class="tpd-myc-tile tpd-tab-link" data-view="supp-listings" data-subtab="set-team">
									<i class="fa-solid fa-users"></i>
									<strong>Team Members</strong>
									<p>Manage Virtual Office team & reps.</p>
								</a>
							</div>
						</div>
					</div>

					<!-- COLUMN 2: Recent Conversations -->
					<div class="tpd-sd-col">
						<div class="tpd-card-box tpd-recent-convos-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'RECENT CONVERSATIONS', 'tpd-tool' ); ?></h4>
								<a href="#tpd-supp-chat" class="tpd-clean-link tpd-tab-link" data-view="supp-chat"><?php esc_html_e( 'View All', 'tpd-tool' ); ?></a>
							</div>

							<div class="tpd-rc-list">
								<?php foreach ( $recent_convos as $rc ) : ?>
									<div class="tpd-rc-item tpd-open-chat-btn" data-recipient-id="<?php echo esc_attr( $rc['rep_id'] ); ?>" data-recipient-name="<?php echo esc_attr( $rc['name'] ); ?>">
										<img src="<?php echo esc_url( $rc['avatar'] ); ?>" alt="<?php echo esc_attr( $rc['name'] ); ?>" class="tpd-rc-avatar">
										<div class="tpd-rc-body">
											<div class="tpd-rc-head">
												<strong><?php echo esc_html( $rc['name'] ); ?></strong>
												<span class="tpd-rc-time"><?php echo esc_html( $rc['time'] ); ?></span>
											</div>
											<span class="tpd-rc-agency"><?php echo esc_html( $rc['agency'] ); ?></span>
											<p class="tpd-rc-snippet"><?php echo esc_html( $rc['snippet'] ); ?></p>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- COLUMN 3: Profile Status -->
					<div class="tpd-sd-col">
						<div class="tpd-card-box tpd-profile-status-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'YOUR LISTING STATUS', 'tpd-tool' ); ?></h4>
								<span class="tpd-badge-live"><i class="fa-solid fa-circle"></i> Live & Visible</span>
							</div>
							<p class="tpd-ps-desc"><?php printf( esc_html__( 'Your company listing (%s) is active and connected to WP Admin.', 'tpd-tool' ), esc_html( $company_name ) ); ?></p>

							<ul class="tpd-checklist-items">
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Company Name & Representative Linked</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Virtual Office Team Active</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Directory Profile Published</li>
							</ul>

							<div class="tpd-ps-footer-thumb">
								<img src="<?php echo esc_url( $banner_url ); ?>" alt="Preview">
								<a href="#tpd-supp-listings" class="tpd-link-arrow tpd-tab-link" data-view="supp-listings"><?php esc_html_e( 'Edit My Listing', 'tpd-tool' ); ?> &rarr;</a>
							</div>
						</div>
					</div>
				</div>

				<!-- Dynamic Bottom Banner Ads (Overview Workspace Footer) -->
				<?php
				$bottom_strip_ads = TPD_Tool_Settings::get_bottom_strip_ads();
				if ( ! empty( $bottom_strip_ads ) ) :
				?>
					<div class="tpd-overview-bottom-ads-strip" style="margin-top:28px;">
						<div class="tpd-bottom-ads-grid" style="display:grid; grid-template-columns: repeat(<?php echo min( count( $bottom_strip_ads ), 4 ); ?>, 1fr); gap:16px;">
							<?php foreach ( $bottom_strip_ads as $b_ad ) :
								if ( empty( $b_ad['image_url'] ) || ( isset( $b_ad['status'] ) && 'inactive' === $b_ad['status'] ) ) {
									continue;
								}
								$b_link   = ! empty( $b_ad['link_url'] ) ? $b_ad['link_url'] : '#';
								$b_target = ( ! empty( $b_ad['target'] ) && '_blank' === $b_ad['target'] ) || ( strpos( $b_link, 'http' ) === 0 ) ? '_blank' : '_self';
								$b_alt    = ! empty( $b_ad['alt_text'] ) ? $b_ad['alt_text'] : 'Partner Offer';
							?>
								<div class="tpd-bottom-ad-card" style="border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.04); transition:all 0.2s ease; background:#fff;">
									<a href="<?php echo esc_url( $b_link ); ?>" target="<?php echo esc_attr( $b_target ); ?>" rel="noopener noreferrer" title="<?php echo esc_attr( $b_alt ); ?>" style="display:block; width:100%; height:100%;">
										<img src="<?php echo esc_url( $b_ad['image_url'] ); ?>" alt="<?php echo esc_attr( $b_alt ); ?>" style="width:100%; height:140px; object-fit:cover; display:block;">
									</a>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- VIEW 2: MY PARTNER LISTINGS (Manage Showcase + Claim Listing + Dynamic ACF/Custom Fields) -->
			<div id="tpd-supp-listings" class="tpd-tab-panel">
				<!-- Top Action Header -->
				<div class="tpd-card-box mb-4" style="background:#0b1526; color:#ffffff; padding:24px 30px; border-radius:14px;">
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
						<div>
							<h3 style="margin:0 0 6px; font-size:20px; font-weight:800; color:#ffffff;"><i class="fa-solid fa-building-flag text-gold"></i> <?php esc_html_e( 'Partner Listings & Virtual Showcase Hub', 'tpd-tool' ); ?></h3>
							<p style="margin:0; font-size:13.5px; color:#cbd5e1;"><?php esc_html_e( 'Manage your brand showcases, create new listings, edit details, or remove inactive directory profiles.', 'tpd-tool' ); ?></p>
						</div>
						<div style="display:flex; gap:10px;">
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-primary" id="tpd-btn-create-new-listing" style="background:#00798c; border-color:#00798c;">
								<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add New Listing', 'tpd-tool' ); ?>
							</button>
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline-white" id="tpd-btn-toggle-claim-box">
								<i class="fa-solid fa-hand-holding-hand"></i> <?php esc_html_e( 'Claim Existing Listing', 'tpd-tool' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Section 0: MY DIRECTORY LISTINGS TABLE (List, Edit, Delete) -->
				<div class="tpd-card-box mb-4" id="tpd-supp-my-listings-overview">
					<div class="tpd-ss-header mb-3" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
						<div>
							<h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-list-check text-blue"></i> <?php esc_html_e( 'My Directory Listings', 'tpd-tool' ); ?></h4>
							<p style="margin:2px 0 0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Click Edit to update your showcase content, media, and resources, or Delete to remove a listing.', 'tpd-tool' ); ?></p>
						</div>
					</div>

					<?php if ( ! empty( $my_listings ) ) : ?>
						<div style="overflow-x:auto;">
							<table class="tpd-inquiries-table" style="width:100%; min-width:650px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Showcase / Brand', 'tpd-tool' ); ?></th>
										<th><?php esc_html_e( 'Category / Tagline', 'tpd-tool' ); ?></th>
										<th><?php esc_html_e( 'Status', 'tpd-tool' ); ?></th>
										<th><?php esc_html_e( 'Engagement', 'tpd-tool' ); ?></th>
										<th style="text-align:right;"><?php esc_html_e( 'Actions', 'tpd-tool' ); ?></th>
									</tr>
								</thead>
								<tbody id="tpd-supp-listings-tbody">
									<?php foreach ( $my_listings as $ml ) :
										$ml_logo    = get_post_meta( $ml->ID, 'tpd_logo_url', true ) ?: '';
										$ml_tagline = get_post_meta( $ml->ID, 'tpd_tagline', true ) ?: get_post_meta( $ml->ID, 'tpd_supplier_category', true ) ?: 'Supplier Partner';
										$ml_views   = (int) get_post_meta( $ml->ID, 'tpd_listing_views', true );
										$ml_saves   = (int) get_post_meta( $ml->ID, 'tpd_listing_saves', true );
										$ml_status  = get_post_status( $ml->ID );
										$is_active_editing = ( $ml->ID === $supplier_post_id );
									?>
										<tr id="tpd-listing-row-<?php echo esc_attr( $ml->ID ); ?>" style="<?php echo $is_active_editing ? 'background:#f0f9ff;' : ''; ?>">
											<td>
												<div style="display:flex; align-items:center; gap:12px;">
													<?php if ( $ml_logo ) : ?>
														<img src="<?php echo esc_url( $ml_logo ); ?>" alt="<?php echo esc_attr( $ml->post_title ); ?>" style="width:40px; height:40px; object-fit:contain; border-radius:6px; border:1px solid #e2e8f0; background:#fff; padding:2px;">
													<?php else : ?>
														<div style="width:40px; height:40px; border-radius:6px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:16px;">
															<i class="fa-solid fa-building"></i>
														</div>
													<?php endif; ?>
													<div>
														<strong style="font-size:14px; color:#0f172a; display:block;"><?php echo esc_html( $ml->post_title ); ?></strong>
														<span style="font-size:11.5px; color:#64748b;">ID #<?php echo esc_html( $ml->ID ); ?> · <?php echo esc_html( get_post_meta( $ml->ID, 'tpd_headquarters', true ) ?: 'Global' ); ?></span>
													</div>
												</div>
											</td>
											<td><span style="font-size:13px; color:#334155;"><?php echo esc_html( wp_trim_words( $ml_tagline, 5 ) ); ?></span></td>
											<td>
												<?php if ( 'publish' === $ml_status ) : ?>
													<span style="background:#dcfce7; color:#15803d; font-size:11px; font-weight:700; padding:3px 8px; border-radius:4px; text-transform:uppercase;"><i class="fa-solid fa-circle-check"></i> Published</span>
												<?php elseif ( 'pending' === $ml_status ) : ?>
													<span style="background:#fef3c7; color:#b45309; font-size:11px; font-weight:700; padding:3px 8px; border-radius:4px; text-transform:uppercase;"><i class="fa-solid fa-clock"></i> Pending</span>
												<?php else : ?>
													<span style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:3px 8px; border-radius:4px; text-transform:uppercase;">Draft</span>
												<?php endif; ?>
											</td>
											<td>
												<span style="font-size:12px; color:#475569;">
													<i class="fa-regular fa-eye text-blue"></i> <?php echo esc_html( $ml_views ); ?> ·
													<i class="fa-regular fa-bookmark text-gold"></i> <?php echo esc_html( $ml_saves ); ?>
												</span>
											</td>
											<td style="text-align:right;">
												<div style="display:inline-flex; gap:6px; align-items:center;">
													<a href="<?php echo esc_url( get_permalink( $ml->ID ) ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none;" title="<?php esc_attr_e( 'View Public Showcase', 'tpd-tool' ); ?>">
														<i class="fa-solid fa-eye"></i> View
													</a>
													<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-btn-load-listing-edit" data-id="<?php echo esc_attr( $ml->ID ); ?>" data-title="<?php echo esc_attr( $ml->post_title ); ?>" title="<?php esc_attr_e( 'Edit this Listing', 'tpd-tool' ); ?>" style="background:#00798c;">
														<i class="fa-solid fa-pen-to-square"></i> Edit
													</button>
													<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-danger tpd-btn-delete-listing" data-id="<?php echo esc_attr( $ml->ID ); ?>" data-title="<?php echo esc_attr( $ml->post_title ); ?>" title="<?php esc_attr_e( 'Delete this Listing', 'tpd-tool' ); ?>" style="background:#dc2626; color:#fff; border:none; padding:4px 8px; border-radius:4px; cursor:pointer;">
														<i class="fa-regular fa-trash-can"></i>
													</button>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<div class="tpd-empty-state-box" style="text-align:center; padding:36px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px;">
							<i class="fa-solid fa-building-circle-check" style="font-size:36px; color:#94a3b8; margin-bottom:12px; display:block;"></i>
							<h4 style="margin:0 0 6px; font-size:16px; font-weight:700; color:#1e293b;"><?php esc_html_e( 'No Listings Created Yet', 'tpd-tool' ); ?></h4>
							<p style="margin:0 0 16px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Publish your brand showcase so travel advisors across North America can discover and book with you.', 'tpd-tool' ); ?></p>
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-primary" id="tpd-btn-create-first-listing" style="background:#00798c; border-color:#00798c;">
								<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Create Your First Listing', 'tpd-tool' ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>

				<!-- Section A: CLAIM A LISTING (Dynamically lists all supplier_listing CPT posts) -->
				<div id="tpd-supp-claim-section" class="tpd-card-box mb-4" style="display:none; border:2px dashed #3b82f6; background:#eff6ff;">
					<div class="tpd-ss-header mb-3">
						<div>
							<h4 style="margin:0; color:#1e40af;"><i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Claim an Existing Directory Listing', 'tpd-tool' ); ?></h4>
							<p style="margin:4px 0 0; font-size:13px; color:#3b82f6;"><?php esc_html_e( 'Search for your brand in the directory and submit a verification request to the Super Admin.', 'tpd-tool' ); ?></p>
						</div>
						<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="jQuery('#tpd-supp-claim-section').slideUp(); jQuery('#tpd-supp-manage-section').slideDown();">Close</button>
					</div>

					<div style="margin-bottom:16px;">
						<input type="text" placeholder="<?php esc_attr_e( 'Search directory by brand name...', 'tpd-tool' ); ?>" class="tpd-input" id="tpd-claim-search-input">
					</div>

					<table class="tpd-inquiries-table">
						<thead>
							<tr>
								<th>Brand / Company</th>
								<th>Tagline / Category</th>
								<th>Location</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $all_directory_listings as $dl ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $dl->post_title ); ?></strong></td>
									<td><?php echo esc_html( get_post_meta( $dl->ID, 'tpd_tagline', true ) ?: 'Supplier Partner' ); ?></td>
									<td><?php echo esc_html( get_post_meta( $dl->ID, 'tpd_headquarters', true ) ?: 'Global' ); ?></td>
									<td>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-btn-claim-modal" data-id="<?php echo esc_attr( $dl->ID ); ?>" data-title="<?php echo esc_attr( $dl->post_title ); ?>">
											<?php esc_html_e( 'Claim Listing', 'tpd-tool' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- Section B: MANAGE A LISTING (100% Connected to supplier_listing CPT & ACF) -->
				<div id="tpd-supp-manage-section">
					<div class="tpd-card-box">
						<div class="tpd-ss-header mb-4">
							<div>
								<h3 style="margin:0;" id="tpd-listing-editor-heading"><i class="fa-solid fa-sliders"></i> <?php printf( esc_html__( 'Manage Listing & Showcase (%s)', 'tpd-tool' ), esc_html( $company_name ) ); ?></h3>
								<span class="text-muted" style="font-size:13px;">
									<?php printf( esc_html__( 'Plan Level: %s · Status: Published & Live', 'tpd-tool' ), '<strong>' . esc_html( $plan_info['name'] ) . '</strong>' ); ?>
								</span>
							</div>
							<div>
								<a href="<?php echo esc_url( $public_url ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline" id="tpd-listing-editor-live-link" style="text-decoration:none;">
									<i class="fa-solid fa-eye"></i> <?php esc_html_e( 'View Live Showcase', 'tpd-tool' ); ?> &rarr;
								</a>
							</div>
						</div>

						<!-- Editor Sub-Tabs (Unified Membership Structure) -->
						<div class="tpd-editor-tabs-bar" style="display:flex; gap:8px; border-bottom:2px solid #e2e8f0; margin-bottom:24px; overflow-x:auto;">
							<button type="button" class="tpd-tab-btn active" data-target="set-about" style="padding:10px 16px; border:none; background:none; font-weight:700; color:#0b1526; border-bottom:3px solid #2563eb; cursor:pointer;">
								<i class="fa-solid fa-info-circle"></i> <?php esc_html_e( 'About', 'tpd-tool' ); ?>
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-associations" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-award"></i> <?php esc_html_e( 'Associations & Conferences', 'tpd-tool' ); ?>
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<span style="font-size:10px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; margin-left:6px;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'ENHANCED', 'tpd-tool' ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-resources" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-folder-open"></i> <?php esc_html_e( 'Resources & Brochures', 'tpd-tool' ); ?>
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<span style="font-size:10px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; margin-left:6px;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'ENHANCED', 'tpd-tool' ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-faqs" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-circle-question"></i> <?php esc_html_e( 'FAQs', 'tpd-tool' ); ?>
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<span style="font-size:10px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; margin-left:6px;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'ENHANCED', 'tpd-tool' ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-gallery" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-images"></i> <?php esc_html_e( 'Photo Gallery', 'tpd-tool' ); ?>
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<span style="font-size:10px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; margin-left:6px;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'ENHANCED', 'tpd-tool' ); ?></span>
								<?php endif; ?>
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-videos" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-video"></i> <?php esc_html_e( 'Videos', 'tpd-tool' ); ?>
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<span style="font-size:10px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; margin-left:6px;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'ENHANCED', 'tpd-tool' ); ?></span>
								<?php endif; ?>
							</button>
						</div>

						<form id="tpd-supplier-listing-editor-form" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="tpd_save_supplier_listing">
							<input type="hidden" name="listing_id" value="<?php echo esc_attr( $supplier_post_id ); ?>">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

							<!-- TAB 1: ABOUT (Basic & Free: Accessible to all) -->
							<div id="set-about" class="tpd-editor-panel active">
								<!-- Title -->
								<div class="tpd-form-group">
									<label><?php esc_html_e( 'Title *', 'tpd-tool' ); ?></label>
									<input type="text" name="company_name" required value="<?php echo esc_attr( $company_name ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'e.g. Direct Ferries', 'tpd-tool' ); ?>">
								</div>

								<!-- Description -->
								<div class="tpd-form-group mt-3">
									<label><?php esc_html_e( 'Description *', 'tpd-tool' ); ?></label>
									<textarea name="description" rows="5" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Founded in 1999, describe your company history, fleet, properties, destinations, advisor commission structure, and key value propositions...', 'tpd-tool' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
								</div>

								<!-- Primary Phone & Advisor Portal Link -->
								<div class="tpd-form-grid-2 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Primary Phone Number', 'tpd-tool' ); ?></label>
										<input type="tel" name="phone" value="<?php echo esc_attr( $rep_phone ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'Enter Primary Phone Number', 'tpd-tool' ); ?>">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Advisor Portal Link', 'tpd-tool' ); ?></label>
										<input type="url" name="booking_url" value="<?php echo esc_attr( $booking_url ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'Enter Advisor Portal Link', 'tpd-tool' ); ?>">
									</div>
								</div>

								<!-- Education & Training Website & Additional Website -->
								<div class="tpd-form-grid-2 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Education & Training Website', 'tpd-tool' ); ?></label>
										<input type="url" name="education_training_url" value="<?php echo esc_attr( $education_training_url ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'Enter Education & Training Website', 'tpd-tool' ); ?>">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Additional Website', 'tpd-tool' ); ?></label>
										<input type="url" name="additional_website" value="<?php echo esc_attr( $additional_website ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'Enter Additional Website', 'tpd-tool' ); ?>">
									</div>
								</div>

								<!-- Additional Phone 1 & Additional Phone 2 -->
								<div class="tpd-form-grid-2 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Additional Phone Number 1', 'tpd-tool' ); ?></label>
										<input type="tel" name="additional_phone_1" value="<?php echo esc_attr( $additional_phone_1 ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'Enter Additional Phone Number 1', 'tpd-tool' ); ?>">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Additional Phone Number 2', 'tpd-tool' ); ?></label>
										<input type="tel" name="additional_phone_2" value="<?php echo esc_attr( $additional_phone_2 ); ?>" class="tpd-input" placeholder="<?php esc_attr_e( 'Enter Additional Phone Number 2', 'tpd-tool' ); ?>">
									</div>
								</div>

								<!-- Agent Rewards & Member of USTOA Radios -->
								<div class="tpd-form-grid-2 mt-3">
									<div class="tpd-form-group">
										<label style="display:block; margin-bottom:8px; font-weight:700;"><?php esc_html_e( 'Agent Rewards?', 'tpd-tool' ); ?></label>
										<div style="display:flex; gap:20px; align-items:center;">
											<label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:13.5px; font-weight:600; color:#1e293b;">
												<input type="radio" name="rewards" value="Yes" <?php checked( $rewards, 'Yes' ); ?>> <?php esc_html_e( 'Yes', 'tpd-tool' ); ?>
											</label>
											<label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:13.5px; font-weight:600; color:#1e293b;">
												<input type="radio" name="rewards" value="No" <?php checked( $rewards !== 'Yes', true ); ?>> <?php esc_html_e( 'No', 'tpd-tool' ); ?>
											</label>
										</div>
									</div>
									<div class="tpd-form-group">
										<label style="display:block; margin-bottom:8px; font-weight:700;"><?php esc_html_e( 'Member of USTOA?', 'tpd-tool' ); ?></label>
										<div style="display:flex; gap:20px; align-items:center;">
											<label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:13.5px; font-weight:600; color:#1e293b;">
												<input type="radio" name="ustoa" value="Yes" <?php checked( $ustoa, 'Yes' ); ?>> <?php esc_html_e( 'Yes', 'tpd-tool' ); ?>
											</label>
											<label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; font-size:13.5px; font-weight:600; color:#1e293b;">
												<input type="radio" name="ustoa" value="No" <?php checked( $ustoa !== 'Yes', true ); ?>> <?php esc_html_e( 'No', 'tpd-tool' ); ?>
											</label>
										</div>
									</div>
								</div>

								<!-- Styles & My Travel Specialties Destinations -->
								<div class="tpd-form-grid-2 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Styles', 'tpd-tool' ); ?></label>
										<select name="travel_styles[]" class="tpd-select" id="tpd-supp-style-select">
											<option value=""><?php esc_html_e( 'Select Style', 'tpd-tool' ); ?></option>
											<?php foreach ( $available_styles as $st ) : ?>
												<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $selected_style, $st ); ?>><?php echo esc_html( $st ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'My Travel Specialties Destinations', 'tpd-tool' ); ?></label>
										<div class="tpd-destinations-pill-container" id="tpd-destinations-pill-container" style="min-height:44px; padding:6px 10px; border:1px solid #cbd5e1; border-radius:8px; background:#ffffff; display:flex; flex-wrap:wrap; gap:6px; align-items:center;">
											<?php if ( ! empty( $selected_destinations ) ) : ?>
												<?php foreach ( $selected_destinations as $dest_name ) : ?>
													<span class="tpd-dest-pill" style="display:inline-flex; align-items:center; gap:6px; background:#00798c; color:#ffffff; font-size:12px; font-weight:700; padding:4px 10px; border-radius:6px;">
														<span><?php echo esc_html( $dest_name ); ?></span>
														<button type="button" class="tpd-remove-dest-pill" data-dest="<?php echo esc_attr( $dest_name ); ?>" style="background:none; border:none; color:#ffffff; font-size:11px; cursor:pointer; padding:0; line-height:1;" title="Remove">&times;</button>
														<input type="hidden" name="destinations[]" value="<?php echo esc_attr( $dest_name ); ?>">
													</span>
												<?php endforeach; ?>
											<?php endif; ?>
											<input type="text" id="tpd-add-dest-input" placeholder="<?php esc_attr_e( '+ Type country/region & press Enter...', 'tpd-tool' ); ?>" style="border:none; outline:none; font-size:12.5px; padding:4px 6px; flex:1; min-width:140px; color:#1e293b;">
										</div>
										<span style="font-size:11px; color:#64748b; margin-top:4px; display:block;"><?php esc_html_e( 'Type any country or destination and press Enter or comma to add as a tag pill.', 'tpd-tool' ); ?></span>
									</div>
								</div>

								<!-- Suppliers Types -->
								<div class="tpd-form-group mt-3">
									<label><?php esc_html_e( 'Suppliers Types', 'tpd-tool' ); ?></label>
									<select name="supplier_type" class="tpd-select" id="tpd-supp-type-select">
										<option value=""><?php esc_html_e( 'Select Suppliers Types', 'tpd-tool' ); ?></option>
										<?php foreach ( $available_supplier_types as $stype_item ) : ?>
											<option value="<?php echo esc_attr( $stype_item ); ?>" <?php selected( $selected_stype, $stype_item ); ?>><?php echo esc_html( $stype_item ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<!-- Video Url -->
								<div class="tpd-form-group mt-3">
									<label><?php esc_html_e( 'Video Url', 'tpd-tool' ); ?></label>
									<input type="url" name="video_url" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." class="tpd-input">
								</div>

								<!-- Social Media Links (Enter Url) -->
								<div class="tpd-social-links-section mt-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
									<div style="margin-bottom:14px;">
										<h4 style="margin:0 0 2px; font-size:14.5px; font-weight:800; color:#0f172a;">
											<?php esc_html_e( 'Social Media Links', 'tpd-tool' ); ?> <span style="color:#ef4444; font-size:12.5px; font-weight:600;">(<?php esc_html_e( 'Enter Url', 'tpd-tool' ); ?>)</span>
										</h4>
										<span style="font-size:12px; color:#64748b;"><?php esc_html_e( 'Add your verified brand social channels so travel advisors can follow your announcements and client marketing.', 'tpd-tool' ); ?></span>
									</div>

									<div class="tpd-form-grid-2">
										<div class="tpd-form-group">
											<label style="font-size:12.5px; color:#334155; font-weight:600;"><i class="fa-brands fa-facebook text-blue"></i> <?php esc_html_e( 'Facebook', 'tpd-tool' ); ?></label>
											<input type="url" name="social_facebook" value="<?php echo esc_attr( $social_facebook ); ?>" placeholder="https://www.facebook.com/your-brand" class="tpd-input tpd-input-sm">
										</div>
										<div class="tpd-form-group">
											<label style="font-size:12.5px; color:#334155; font-weight:600;"><i class="fa-brands fa-x-twitter"></i> <?php esc_html_e( 'Twitter', 'tpd-tool' ); ?></label>
											<input type="url" name="social_twitter" value="<?php echo esc_attr( $social_twitter ); ?>" placeholder="https://twitter.com/your-handle" class="tpd-input tpd-input-sm">
										</div>
									</div>

									<div class="tpd-form-grid-2 mt-3">
										<div class="tpd-form-group">
											<label style="font-size:12.5px; color:#334155; font-weight:600;"><i class="fa-brands fa-youtube text-red"></i> <?php esc_html_e( 'YouTube', 'tpd-tool' ); ?></label>
											<input type="url" name="social_youtube" value="<?php echo esc_attr( $social_youtube ); ?>" placeholder="https://www.youtube.com/@your-channel" class="tpd-input tpd-input-sm">
										</div>
										<div class="tpd-form-group">
											<label style="font-size:12.5px; color:#334155; font-weight:600;"><i class="fa-brands fa-instagram text-purple"></i> <?php esc_html_e( 'Instagram', 'tpd-tool' ); ?></label>
											<input type="url" name="social_instagram" value="<?php echo esc_attr( $social_instagram ); ?>" placeholder="https://www.instagram.com/your-brand" class="tpd-input tpd-input-sm">
										</div>
									</div>

									<div class="tpd-form-grid-2 mt-3">
										<div class="tpd-form-group">
											<label style="font-size:12.5px; color:#334155; font-weight:600;"><i class="fa-brands fa-tiktok"></i> <?php esc_html_e( 'TikTok', 'tpd-tool' ); ?></label>
											<input type="url" name="social_tiktok" value="<?php echo esc_attr( $social_tiktok ); ?>" placeholder="https://tiktok.com/@your-profile" class="tpd-input tpd-input-sm">
										</div>
										<div class="tpd-form-group">
											<label style="font-size:12.5px; color:#334155; font-weight:600;"><i class="fa-brands fa-linkedin text-blue"></i> <?php esc_html_e( 'LinkedIn', 'tpd-tool' ); ?></label>
											<input type="url" name="social_linkedin" value="<?php echo esc_attr( $social_linkedin ); ?>" placeholder="https://linkedin.com/in/your-profile" class="tpd-input tpd-input-sm">
										</div>
									</div>
								</div>

								<!-- Tagline & Headquarters Details -->
								<div class="tpd-form-grid-2 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Tagline / Value Proposition', 'tpd-tool' ); ?></label>
										<input type="text" name="tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'e.g. Bespoke Luxury Voyages & Ferries', 'tpd-tool' ); ?>" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Headquarters Location', 'tpd-tool' ); ?></label>
										<input type="text" name="headquarters" value="<?php echo esc_attr( $headquarters ); ?>" placeholder="e.g. London, UK / Global" class="tpd-input">
									</div>
								</div>

								<!-- Brand Logo & Header Banner Section -->
								<div class="tpd-form-grid-2 mt-4" style="margin-bottom:20px;">
									<!-- Card 1: Company Brand Logo -->
									<div class="tpd-card-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<label style="font-weight:700; font-size:13.5px; display:block; margin-bottom:4px;"><?php esc_html_e( 'Company Brand Logo *', 'tpd-tool' ); ?></label>
										<p style="margin:0 0 12px; font-size:12px; color:#64748b;"><?php esc_html_e( 'Transparent PNG, SVG, or high-res JPG. Shown on directory cards, search results & showcase header.', 'tpd-tool' ); ?></p>

										<div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
											<div class="tpd-logo-preview-box" style="width:160px; height:80px; border:1.5px dashed #cbd5e1; border-radius:8px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; padding:6px;">
												<img id="tpd-supp-logo-preview" src="<?php echo esc_url( $logo_url ); ?>" style="max-width:100%; max-height:100%; object-fit:contain; <?php echo empty( $logo_url ) ? 'display:none;' : ''; ?>" alt="Brand Logo">
												<div id="tpd-supp-logo-placeholder" style="text-align:center; color:#94a3b8; <?php echo ! empty( $logo_url ) ? 'display:none;' : ''; ?>">
													<i class="fa-regular fa-image" style="font-size:20px; margin-bottom:2px; display:block;"></i>
													<span style="font-size:10.5px; font-weight:600;"><?php esc_html_e( 'No logo uploaded', 'tpd-tool' ); ?></span>
												</div>
											</div>
											<div>
												<input type="hidden" name="logo_url" id="tpd_supp_logo_url" value="<?php echo esc_attr( $logo_url ); ?>">
												<input type="file" id="tpd-file-supp-logo" accept="image/*" style="display:none;">
												<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
													<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" onclick="document.getElementById('tpd-file-supp-logo').click();">
														<i class="fa-solid fa-cloud-arrow-up"></i> <?php esc_html_e( 'Upload Brand Logo', 'tpd-tool' ); ?>
													</button>
													<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" id="tpd-supp-remove-logo-btn" style="<?php echo empty( $logo_url ) ? 'display:none;' : ''; ?>">
														<i class="fa-solid fa-trash-can text-red"></i> <?php esc_html_e( 'Remove', 'tpd-tool' ); ?>
													</button>
												</div>
												<span style="display:block; font-size:11px; color:#64748b; margin-top:4px;"><?php esc_html_e( 'Recommended: 400x200px or vector SVG.', 'tpd-tool' ); ?></span>
											</div>
										</div>
									</div>

									<!-- Card 2: Custom Showcase Banner Photo -->
									<div class="tpd-card-box" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<label style="font-weight:700; font-size:13.5px; display:block; margin-bottom:4px;"><?php esc_html_e( 'Custom Showcase Banner Photo', 'tpd-tool' ); ?></label>
										<p style="margin:0 0 12px; font-size:12px; color:#64748b;"><?php esc_html_e( 'Scenic photo of your ship, resort, property, or landscape for the showcase header.', 'tpd-tool' ); ?></p>

										<div style="width:100%; height:80px; border-radius:8px; overflow:hidden; border:1px solid #cbd5e1; margin-bottom:8px; background:#fff;">
											<img id="tpd-supp-banner-preview" src="<?php echo esc_url( $banner_url ); ?>" style="width:100%; height:100%; object-fit:cover;">
										</div>
										<input type="hidden" name="banner_url" id="tpd_supp_banner_url" value="<?php echo esc_attr( $banner_url ); ?>">
										<input type="file" id="tpd-file-supp-banner" accept="image/*" style="display:none;">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-supp-banner').click();">
											<i class="fa-solid fa-panorama"></i> <?php esc_html_e( 'Upload Banner Image', 'tpd-tool' ); ?>
										</button>
									</div>
								</div>

								<!-- Dynamic ACF Field Groups & Admin Custom Fields Bridge -->
								<?php
								if ( class_exists( 'TPD_Tool_CPT' ) ) {
									TPD_Tool_CPT::render_dynamic_fields_html( 'supplier_listing', $supplier_post_id, $current_user_id, 'dashboard' );
								}
								?>
							</div>

							<!-- TAB 2: ASSOCIATIONS & CONFERENCES (Enhanced / Paid Listing) -->
							<div id="set-associations" class="tpd-editor-panel" style="display:none;">
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<?php echo TPD_Tool_Tiers::render_enhanced_tab_locked( __( 'Associations & Conferences Showcase', 'tpd-tool' ), __( 'Display your industry accreditations (USTOA, ASTA, CLIA, Consortia affiliations) and promote upcoming industry trade conferences attended to thousands of travel advisors.', 'tpd-tool' ), 'fa-award' ); ?>
								<?php else : ?>
									<!-- Industry Accreditations -->
									<div class="tpd-card-box mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<h4 style="margin:0 0 12px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-certificate text-blue"></i> <?php esc_html_e( 'Industry Association Memberships', 'tpd-tool' ); ?></h4>
										<div class="tpd-form-grid-3">
											<div class="tpd-form-group">
												<label><?php esc_html_e( 'Member of USTOA?', 'tpd-tool' ); ?></label>
												<select name="ustoa" class="tpd-select">
													<option value="Yes" <?php selected( $ustoa, 'Yes' ); ?>>Yes — Active USTOA Member</option>
													<option value="No" <?php selected( $ustoa, 'No' ); ?>>No</option>
												</select>
											</div>
											<div class="tpd-form-group">
												<label><?php esc_html_e( 'Member of ASTA?', 'tpd-tool' ); ?></label>
												<select name="asta" class="tpd-select">
													<option value="Yes" <?php selected( $asta, 'Yes' ); ?>>Yes — Active ASTA Member</option>
													<option value="No" <?php selected( $asta, 'No' ); ?>>No</option>
												</select>
											</div>
											<div class="tpd-form-group">
												<label><?php esc_html_e( 'Member of CLIA?', 'tpd-tool' ); ?></label>
												<select name="clia" class="tpd-select">
													<option value="Yes" <?php selected( $clia, 'Yes' ); ?>>Yes — Active CLIA Member</option>
													<option value="No" <?php selected( $clia, 'No' ); ?>>No</option>
												</select>
											</div>
										</div>
										<div class="tpd-form-group mt-3">
											<label><?php esc_html_e( 'Consortia, Networks & Other Affiliations', 'tpd-tool' ); ?></label>
											<input type="text" name="associations" value="<?php echo esc_attr( $associations ); ?>" placeholder="<?php esc_attr_e( 'e.g. Virtuoso, Signature Travel Network, Ensemble, Travel Leaders Network, PATH', 'tpd-tool' ); ?>" class="tpd-input">
											<span style="font-size:11.5px; color:#64748b;"><?php esc_html_e( 'Comma-separated list of recognized travel consortia and trade bodies.', 'tpd-tool' ); ?></span>
										</div>
									</div>

									<!-- Advisor Rewards & Incentives -->
									<div class="tpd-card-box mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<h4 style="margin:0 0 12px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-gift text-gold"></i> <?php esc_html_e( 'Travel Advisor Rewards & Incentive Program', 'tpd-tool' ); ?></h4>
										<div class="tpd-form-grid-2">
											<div class="tpd-form-group">
												<label><?php esc_html_e( 'Active Agent Rewards Program?', 'tpd-tool' ); ?></label>
												<select name="rewards" class="tpd-select">
													<option value="Yes" <?php selected( $rewards, 'Yes' ); ?>>Yes — Active Rewards / Incentive</option>
													<option value="No" <?php selected( $rewards, 'No' ); ?>>No</option>
												</select>
											</div>
											<div class="tpd-form-group">
												<label><?php esc_html_e( 'Incentive Headline / Promotion Title', 'tpd-tool' ); ?></label>
												<input type="text" name="promo_title" value="<?php echo esc_attr( $promo_title ); ?>" placeholder="<?php esc_attr_e( 'e.g. Earn 2% Bonus Commission on Q3 Bookings', 'tpd-tool' ); ?>" class="tpd-input">
											</div>
										</div>
										<div class="tpd-form-group mt-2">
											<label><?php esc_html_e( 'Rewards Details, Booking Code & Terms', 'tpd-tool' ); ?></label>
											<textarea name="promo_desc" rows="2" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Explain how travel advisors register client bookings, promo codes, and bonus commission payout schedule...', 'tpd-tool' ); ?>"><?php echo esc_textarea( $promo_desc ); ?></textarea>
										</div>
									</div>

									<!-- Conferences & Events Attended -->
									<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
										<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
											<div>
												<h4 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-calendar-days text-purple"></i> <?php esc_html_e( 'Upcoming Conferences & Trade Events Attended', 'tpd-tool' ); ?></h4>
												<p style="margin:0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Add upcoming trade shows, conferences, start/end dates, event flyer files, and meeting locations.', 'tpd-tool' ); ?></p>
											</div>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" id="tpd-btn-add-conference">
												<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Conference', 'tpd-tool' ); ?>
											</button>
										</div>

										<div id="tpd-conferences-container" style="display:flex; flex-direction:column; gap:14px;">
											<?php if ( ! empty( $conferences ) ) : ?>
												<?php foreach ( $conferences as $idx => $conf ) :
													$c_name       = isset( $conf['title'] ) && ! empty( $conf['title'] ) ? $conf['title'] : ( isset( $conf['name'] ) ? $conf['name'] : '' );
													$c_start_date = isset( $conf['start_date'] ) ? $conf['start_date'] : '';
													$c_end_date   = isset( $conf['end_date'] ) ? $conf['end_date'] : '';
													$c_date       = isset( $conf['date'] ) ? $conf['date'] : '';
													$c_loc        = isset( $conf['location'] ) ? $conf['location'] : '';
													$c_file       = isset( $conf['file_url'] ) ? $conf['file_url'] : '';
													$c_url        = isset( $conf['url'] ) ? $conf['url'] : '';
												?>
													<div class="tpd-repeater-row tpd-conf-row" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:10px;">
														<div style="display:grid; grid-template-columns: 2fr 1fr 1fr auto; gap:10px; align-items:center;">
															<input type="text" class="tpd-input tpd-input-sm conf-name" placeholder="<?php esc_attr_e( 'Conference / Event Title *', 'tpd-tool' ); ?>" value="<?php echo esc_attr( $c_name ); ?>">
															<div style="display:flex; flex-direction:column;">
																<span style="font-size:10.5px; color:#64748b; font-weight:700; margin-bottom:2px;"><?php esc_html_e( 'Start Date', 'tpd-tool' ); ?></span>
																<input type="date" class="tpd-input tpd-input-sm conf-start-date" value="<?php echo esc_attr( $c_start_date ); ?>" title="Start Date">
															</div>
															<div style="display:flex; flex-direction:column;">
																<span style="font-size:10.5px; color:#64748b; font-weight:700; margin-bottom:2px;"><?php esc_html_e( 'End Date', 'tpd-tool' ); ?></span>
																<input type="date" class="tpd-input tpd-input-sm conf-end-date" value="<?php echo esc_attr( $c_end_date ); ?>" title="End Date">
															</div>
															<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-danger tpd-remove-repeater-row" style="background:#fee2e2; color:#dc2626; border:none; padding:8px 12px; border-radius:6px; cursor:pointer;" title="Remove conference"><i class="fa-solid fa-trash-can"></i></button>
														</div>
														<div style="display:grid; grid-template-columns: 1.5fr 1.5fr 1.5fr; gap:10px; align-items:center;">
															<input type="text" class="tpd-input tpd-input-sm conf-location" placeholder="<?php esc_attr_e( 'Location / Booth # (e.g. Las Vegas, Booth 402)', 'tpd-tool' ); ?>" value="<?php echo esc_attr( $c_loc ); ?>">
															<div style="display:flex; gap:6px; align-items:center;">
																<input type="url" class="tpd-input tpd-input-sm conf-file-url" placeholder="<?php esc_attr_e( 'Event Flyer / Agenda File URL', 'tpd-tool' ); ?>" value="<?php echo esc_attr( $c_file ); ?>">
																<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-btn-upload-conf-file" title="Upload Flyer or Schedule PDF">
																	<i class="fa-solid fa-file-arrow-up"></i>
																</button>
															</div>
															<input type="url" class="tpd-input tpd-input-sm conf-url" placeholder="<?php esc_attr_e( 'Info Link https://', 'tpd-tool' ); ?>" value="<?php echo esc_attr( $c_url ); ?>">
														</div>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
										<input type="file" id="tpd-file-conf-single-upload" accept=".pdf,.doc,.docx,.jpg,.png" style="display:none;">
										<input type="hidden" name="conferences_json" id="tpd-conferences-json" value="<?php echo esc_attr( wp_json_encode( $conferences ) ); ?>">
									</div>
								<?php endif; ?>
							</div>

							<!-- TAB 3: RESOURCES & BROCHURES (Enhanced / Paid Listing) -->
							<div id="set-resources" class="tpd-editor-panel" style="display:none;">
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<?php echo TPD_Tool_Tiers::render_enhanced_tab_locked( __( 'Resources & Brochures Library', 'tpd-tool' ), __( 'Upload and distribute digital brochures, sales guides, deck plans, commission schedules, and advisor marketing collateral directly on your showcase.', 'tpd-tool' ), 'fa-folder-open' ); ?>
								<?php else : ?>
									<div class="tpd-card-box mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
											<div>
												<h4 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-file-pdf text-red"></i> <?php esc_html_e( 'Advisor Document & Digital Brochure Library', 'tpd-tool' ); ?></h4>
												<p style="margin:0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Upload downloadable PDF marketing guides, deck plans, itineraries, and sales tools for travel advisors.', 'tpd-tool' ); ?></p>
											</div>
											<div style="display:flex; gap:8px;">
												<input type="file" id="tpd-file-supp-resource-upload" accept=".pdf,.doc,.docx" style="display:none;">
												<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-supp-resource-upload').click();">
													<i class="fa-solid fa-cloud-arrow-up"></i> <?php esc_html_e( 'Upload PDF', 'tpd-tool' ); ?>
												</button>
												<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" id="tpd-btn-add-resource">
													<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Resource Item', 'tpd-tool' ); ?>
												</button>
											</div>
										</div>
									</div>

									<div id="tpd-resources-container" style="display:flex; flex-direction:column; gap:12px;">
										<?php if ( ! empty( $resources ) ) : ?>
											<?php foreach ( $resources as $res ) : ?>
												<div class="tpd-repeater-row tpd-res-row" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px; display:grid; grid-template-columns: 2fr 1.5fr 2fr 2fr auto; gap:10px; align-items:center;">
													<input type="text" class="tpd-input tpd-input-sm res-title" placeholder="Resource Title (e.g. 2026 Fleet Brochure)" value="<?php echo esc_attr( isset( $res['title'] ) ? $res['title'] : '' ); ?>">
													<select class="tpd-select tpd-input-sm res-category">
														<option value="Brochure" <?php selected( isset( $res['category'] ) ? $res['category'] : '', 'Brochure' ); ?>>Brochure</option>
														<option value="Sales Guide" <?php selected( isset( $res['category'] ) ? $res['category'] : '', 'Sales Guide' ); ?>>Sales Guide</option>
														<option value="Deck Plan" <?php selected( isset( $res['category'] ) ? $res['category'] : '', 'Deck Plan' ); ?>>Deck Plan / Factsheet</option>
														<option value="Commission Sheet" <?php selected( isset( $res['category'] ) ? $res['category'] : '', 'Commission Sheet' ); ?>>Commission Sheet</option>
														<option value="Advisor Flyer" <?php selected( isset( $res['category'] ) ? $res['category'] : '', 'Advisor Flyer' ); ?>>Advisor Flyer</option>
														<option value="Webinar PDF" <?php selected( isset( $res['category'] ) ? $res['category'] : '', 'Webinar PDF' ); ?>>Webinar PDF</option>
													</select>
													<input type="url" class="tpd-input tpd-input-sm res-url" placeholder="File URL https://" value="<?php echo esc_attr( isset( $res['file_url'] ) ? $res['file_url'] : '' ); ?>">
													<input type="text" class="tpd-input tpd-input-sm res-desc" placeholder="Brief note for advisors" value="<?php echo esc_attr( isset( $res['description'] ) ? $res['description'] : '' ); ?>">
													<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-danger tpd-remove-repeater-row" style="background:#fee2e2; color:#dc2626; border:none; padding:6px 10px; border-radius:6px; cursor:pointer;" title="Remove resource"><i class="fa-solid fa-trash-can"></i></button>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
									<input type="hidden" name="resources_json" id="tpd-resources-json" value="<?php echo esc_attr( wp_json_encode( $resources ) ); ?>">
								<?php endif; ?>
							</div>

							<!-- TAB 4: FAQS (Enhanced / Paid Listing) -->
							<div id="set-faqs" class="tpd-editor-panel" style="display:none;">
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<?php echo TPD_Tool_Tiers::render_enhanced_tab_locked( __( 'Supplier FAQs for Travel Advisors', 'tpd-tool' ), __( 'Provide answers to advisors\' top questions regarding booking procedures, commission payment cycles, deposit deadlines, FAM trips, and client protections.', 'tpd-tool' ), 'fa-circle-question' ); ?>
								<?php else : ?>
									<div class="tpd-card-box mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
											<div>
												<h4 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-circle-question text-teal"></i> <?php esc_html_e( 'Frequently Asked Questions for Travel Advisors', 'tpd-tool' ); ?></h4>
												<p style="margin:0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Build a custom FAQ knowledgebase answering advisors\' most common questions before they book.', 'tpd-tool' ); ?></p>
											</div>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" id="tpd-btn-add-faq">
												<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add FAQ Item', 'tpd-tool' ); ?>
											</button>
										</div>
									</div>

									<div id="tpd-faqs-container" style="display:flex; flex-direction:column; gap:14px;">
										<?php if ( ! empty( $supplier_faqs ) ) : ?>
											<?php foreach ( $supplier_faqs as $f_item ) : ?>
												<div class="tpd-repeater-row tpd-faq-row" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:14px;">
													<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
														<label style="font-weight:700; font-size:13px; color:#1e293b;"><i class="fa-solid fa-circle-question text-blue"></i> <?php esc_html_e( 'Question:', 'tpd-tool' ); ?></label>
														<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-danger tpd-remove-repeater-row" style="background:#fee2e2; color:#dc2626; border:none; padding:4px 8px; border-radius:6px; cursor:pointer;" title="Remove FAQ"><i class="fa-solid fa-trash-can"></i> Remove</button>
													</div>
													<input type="text" class="tpd-input tpd-input-sm faq-question mb-2" placeholder="e.g. What is your advisor commission payment schedule?" value="<?php echo esc_attr( isset( $f_item['question'] ) ? $f_item['question'] : '' ); ?>" style="width:100%;">
													<label style="font-weight:700; font-size:12.5px; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Answer:', 'tpd-tool' ); ?></label>
													<textarea class="tpd-textarea faq-answer" rows="2" placeholder="e.g. Commissions are paid bi-weekly upon receipt of final client payment..." style="width:100%; font-size:12.5px;"><?php echo esc_textarea( isset( $f_item['answer'] ) ? $f_item['answer'] : '' ); ?></textarea>
												</div>
											<?php endforeach; ?>
										<?php endif; ?>
									</div>
									<input type="hidden" name="faqs_json" id="tpd-faqs-json" value="<?php echo esc_attr( wp_json_encode( $supplier_faqs ) ); ?>">
								<?php endif; ?>
							</div>

							<!-- TAB 5: PHOTO GALLERY (Enhanced / Paid Listing) -->
							<div id="set-gallery" class="tpd-editor-panel" style="display:none;">
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<?php echo TPD_Tool_Tiers::render_enhanced_tab_locked( __( 'Showcase Photo Gallery', 'tpd-tool' ), __( 'Display high-resolution property, fleet, cabin, suite, dining, excursion, and amenity photos to inspire travel advisors and their clients.', 'tpd-tool' ), 'fa-images' ); ?>
								<?php else : ?>
									<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
										<div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
											<div>
												<h4 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-regular fa-images text-blue"></i> <?php esc_html_e( 'Showcase Photo Gallery', 'tpd-tool' ); ?></h4>
												<p style="margin:0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Upload high-resolution property, cabin, dining, excursion, and amenity photos to present to travel advisors.', 'tpd-tool' ); ?></p>
											</div>
											<div>
												<input type="file" id="tpd-file-supp-gallery" accept="image/*" multiple style="display:none;">
												<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" onclick="document.getElementById('tpd-file-supp-gallery').click();" style="display:inline-flex; align-items:center; gap:6px;">
													<i class="fa-solid fa-cloud-arrow-up"></i> <?php esc_html_e( '+ Add Photos to Showcase Gallery', 'tpd-tool' ); ?>
												</button>
											</div>
										</div>

										<div id="tpd-supp-gallery-empty" style="text-align:center; padding:28px 16px; background:#f8fafc; border:2px dashed #cbd5e1; border-radius:10px; margin-bottom:16px; <?php echo ! empty( $gallery_images ) ? 'display:none;' : ''; ?>">
											<i class="fa-regular fa-images" style="font-size:32px; color:#94a3b8; margin-bottom:8px; display:block;"></i>
											<h5 style="margin:0 0 4px; font-size:13.5px; font-weight:700; color:#334155;"><?php esc_html_e( 'No Photos in Showcase Gallery Yet', 'tpd-tool' ); ?></h5>
											<p style="margin:0 0 12px; font-size:12px; color:#64748b;"><?php esc_html_e( 'Upload photos of your offerings to attract and educate registered travel advisors.', 'tpd-tool' ); ?></p>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-supp-gallery').click();">
												<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Upload Photos', 'tpd-tool' ); ?>
											</button>
										</div>

										<div id="tpd-supp-gallery-grid" class="tpd-gallery-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)); gap:12px;">
											<?php if ( ! empty( $gallery_images ) ) : ?>
												<?php foreach ( $gallery_images as $g_url ) : ?>
													<div class="tpd-gallery-item" data-url="<?php echo esc_url( $g_url ); ?>">
														<img src="<?php echo esc_url( $g_url ); ?>" class="tpd-gallery-thumb" alt="Showcase Photo">
														<input type="hidden" name="gallery_images[]" value="<?php echo esc_url( $g_url ); ?>">
														<button type="button" class="tpd-gallery-remove-btn tpd-remove-gallery-item-btn" title="<?php esc_attr_e( 'Remove photo', 'tpd-tool' ); ?>">
															<i class="fa-solid fa-xmark"></i>
														</button>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
										<input type="hidden" name="gallery_images_json" id="tpd-gallery-images-json" value="<?php echo esc_attr( wp_json_encode( $gallery_images ) ); ?>">
									</div>
								<?php endif; ?>
							</div>

							<!-- TAB 6: VIDEOS (Enhanced / Paid Listing) -->
							<div id="set-videos" class="tpd-editor-panel" style="display:none;">
								<?php if ( ! $is_supplier_enhanced ) : ?>
									<?php echo TPD_Tool_Tiers::render_enhanced_tab_locked( __( 'Showcase Videos & Webinars', 'tpd-tool' ), __( 'Embed your brand video showcase, cabin walkthroughs, and recorded product training webinars for travel advisors to watch on demand.', 'tpd-tool' ), 'fa-video' ); ?>
								<?php else : ?>
									<!-- Main Showcase Video -->
									<div class="tpd-card-box mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
										<h4 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-circle-play text-red"></i> <?php esc_html_e( 'Main Showcase Video (YouTube or Vimeo URL)', 'tpd-tool' ); ?></h4>
										<p style="margin:0 0 12px; font-size:12px; color:#64748b;"><?php esc_html_e( 'Primary promotional video featured prominently at the top of your public showcase profile.', 'tpd-tool' ); ?></p>
										<input type="url" name="video_url" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://www.youtube.com/watch?v=... or https://vimeo.com/..." class="tpd-input">
									</div>

									<!-- Multi-Video & Webinar Showcase Repeater -->
									<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
										<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
											<div>
												<h4 style="margin:0 0 4px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-film text-purple"></i> <?php esc_html_e( 'Additional Videos, Property Tours & Recorded Webinars', 'tpd-tool' ); ?></h4>
												<p style="margin:0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Add on-demand training webinars, ship/cabin walkthroughs, and destination overview videos.', 'tpd-tool' ); ?></p>
											</div>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" id="tpd-btn-add-video">
												<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Video', 'tpd-tool' ); ?>
											</button>
										</div>

										<div id="tpd-videos-container" style="display:flex; flex-direction:column; gap:12px;">
											<?php if ( ! empty( $supplier_videos ) ) : ?>
												<?php foreach ( $supplier_videos as $vid ) : ?>
													<div class="tpd-repeater-row tpd-vid-row" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; display:grid; grid-template-columns: 2fr 3fr 2fr auto; gap:10px; align-items:center;">
														<input type="text" class="tpd-input tpd-input-sm vid-title" placeholder="Video Title (e.g. 2026 Fleet Walkthrough)" value="<?php echo esc_attr( isset( $vid['title'] ) ? $vid['title'] : '' ); ?>">
														<input type="url" class="tpd-input tpd-input-sm vid-url" placeholder="YouTube or Vimeo URL" value="<?php echo esc_attr( isset( $vid['video_url'] ) ? $vid['video_url'] : '' ); ?>">
														<input type="text" class="tpd-input tpd-input-sm vid-desc" placeholder="Brief note / duration" value="<?php echo esc_attr( isset( $vid['description'] ) ? $vid['description'] : '' ); ?>">
														<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-danger tpd-remove-repeater-row" style="background:#fee2e2; color:#dc2626; border:none; padding:6px 10px; border-radius:6px; cursor:pointer;" title="Remove video"><i class="fa-solid fa-trash-can"></i></button>
													</div>
												<?php endforeach; ?>
											<?php endif; ?>
										</div>
										<input type="hidden" name="videos_json" id="tpd-videos-json" value="<?php echo esc_attr( wp_json_encode( $supplier_videos ) ); ?>">
									</div>
								<?php endif; ?>
							</div>

							<div id="tpd-supplier-listing-status" class="tpd-reg-status" style="display:none; margin-top:20px;"></div>

							<div class="mt-4" style="text-align:right; border-top:1px solid #e2e8f0; padding-top:18px;">
								<button type="submit" class="tpd-btn tpd-btn-darkblue" id="tpd-save-supplier-listing-btn" style="padding:12px 30px;">
									<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Listing & Showcase Changes', 'tpd-tool' ); ?>
								</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- VIEW 3: CHAT & MESSAGES -->
			<div id="tpd-supp-chat" class="tpd-tab-panel">
				<?php include TPD_TOOL_DIR . 'templates/shared/chat-box.php'; ?>
			</div>

			<!-- VIEW 4: MEETING REQUESTS -->
			<div id="tpd-supp-meetings" class="tpd-tab-panel">
				<div class="tpd-card-box mb-4" style="background:linear-gradient(135deg, #0b1526 0%, #1e3a8a 100%); color:#ffffff; padding:26px 30px; border-radius:14px;">
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
						<div>
							<h3 style="margin:0 0 6px; font-size:20px; font-weight:800; color:#ffffff;"><i class="fa-regular fa-calendar-check text-gold"></i> <?php esc_html_e( 'Advisor Discovery Call & 1-on-1 Meeting Requests', 'tpd-tool' ); ?></h3>
							<p style="margin:0; font-size:13.5px; color:#cbd5e1;"><?php esc_html_e( 'Manage 1-on-1 consultation requests from verified Travel Advisors and configure your Virtual Office Hours.', 'tpd-tool' ); ?></p>
						</div>
						<span style="background:rgba(16,185,129,0.2); border:1px solid #34d399; color:#6ee7b7; padding:6px 14px; border-radius:999px; font-size:12.5px; font-weight:700;">
							<i class="fa-solid fa-circle"></i> <?php esc_html_e( 'Virtual Office Hours Active', 'tpd-tool' ); ?>
						</span>
					</div>
				</div>

				<!-- Virtual Office Hours & Calendar Settings -->
				<div class="tpd-card-box mb-4">
					<h4 style="margin:0 0 6px; font-size:16px; color:#0b1526;"><i class="fa-solid fa-clock text-blue"></i> <?php esc_html_e( 'Virtual Office Hours & Booking Calendar Link', 'tpd-tool' ); ?></h4>
					<p class="text-muted" style="margin:0 0 16px; font-size:13px;"><?php esc_html_e( 'Set your preferred weekly availability and Calendly/Teams/Zoom booking link for instant advisor consultations.', 'tpd-tool' ); ?></p>
					<div class="tpd-form-grid-3">
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Weekly Office Hours Window', 'tpd-tool' ); ?></label>
							<input type="text" class="tpd-input" value="Mon – Fri · 9:00 AM – 5:00 PM EST" placeholder="e.g. Mon - Fri, 9am - 5pm EST">
						</div>
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Direct Calendar / Zoom Booking URL', 'tpd-tool' ); ?></label>
							<input type="url" class="tpd-input" value="<?php echo esc_attr( $booking_url ?: 'https://calendly.com/' . sanitize_title( $company_name ) ); ?>" placeholder="https://calendly.com/your-brand">
						</div>
						<div class="tpd-form-group" style="display:flex; align-items:flex-end;">
							<button type="button" class="tpd-btn tpd-btn-darkblue tpd-btn-save-office-hours" style="width:100%;">
								<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Office Hours', 'tpd-tool' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Incoming Advisor Meeting Requests Table -->
				<div class="tpd-card-box">
					<div class="tpd-ss-header mb-3">
						<div>
							<h4 style="margin:0; font-size:16px; color:#0b1526;"><i class="fa-solid fa-user-clock text-purple"></i> <?php esc_html_e( 'Incoming Advisor Consultation Requests', 'tpd-tool' ); ?></h4>
							<p class="text-muted" style="margin:4px 0 0; font-size:13px;"><?php esc_html_e( 'Review requested discovery calls, group booking inquiries, and product training sessions.', 'tpd-tool' ); ?></p>
						</div>
					</div>

					<table class="tpd-inquiries-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Travel Advisor', 'tpd-tool' ); ?></th>
								<th><?php esc_html_e( 'Host Agency', 'tpd-tool' ); ?></th>
								<th><?php esc_html_e( 'Preferred Date & Time', 'tpd-tool' ); ?></th>
								<th><?php esc_html_e( 'Discussion Topic', 'tpd-tool' ); ?></th>
								<th><?php esc_html_e( 'Status', 'tpd-tool' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'tpd-tool' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>Sarah Jenkins, CTA</strong></td>
								<td>Virtuoso Luxury Travel</td>
								<td>Thu, Oct 24 · 2:00 PM EST</td>
								<td>2026 Family Group Charter & Commission Tiers</td>
								<td>
									<span class="tpd-meeting-status-badge" style="background:#fef3c7; color:#b45309; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700;">
										<i class="fa-solid fa-clock"></i> Pending Confirmation
									</span>
								</td>
								<td style="display:flex; gap:8px; flex-wrap:wrap;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary tpd-btn-confirm-meeting" data-advisor="Sarah Jenkins">
										<i class="fa-solid fa-calendar-check"></i> <?php esc_html_e( 'Confirm Call', 'tpd-tool' ); ?>
									</button>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-open-chat-btn" data-recipient-id="2" data-recipient-name="Sarah Jenkins">
										<i class="fa-regular fa-comment-dots"></i> <?php esc_html_e( 'Message', 'tpd-tool' ); ?>
									</button>
								</td>
							</tr>
							<tr>
								<td><strong>Michael Vance, CTC</strong></td>
								<td>Signature Travel Network</td>
								<td>Fri, Oct 25 · 11:30 AM EST</td>
								<td>VIP Client Amenities & Fam Trip Eligibility</td>
								<td>
									<span class="tpd-meeting-status-badge" style="background:#fef3c7; color:#b45309; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700;">
										<i class="fa-solid fa-clock"></i> Pending Confirmation
									</span>
								</td>
								<td style="display:flex; gap:8px; flex-wrap:wrap;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary tpd-btn-confirm-meeting" data-advisor="Michael Vance">
										<i class="fa-solid fa-calendar-check"></i> <?php esc_html_e( 'Confirm Call', 'tpd-tool' ); ?>
									</button>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-open-chat-btn" data-recipient-id="3" data-recipient-name="Michael Vance">
										<i class="fa-regular fa-comment-dots"></i> <?php esc_html_e( 'Message', 'tpd-tool' ); ?>
									</button>
								</td>
							</tr>
							<tr>
								<td><strong>Elena Rostova</strong></td>
								<td>Bespoke Horizons Agency</td>
								<td>Tue, Oct 29 · 3:00 PM EST</td>
								<td>1-on-1 Brand Showcase & Brochure Review</td>
								<td>
									<span class="tpd-meeting-status-badge" style="background:#dcfce7; color:#166534; padding:4px 10px; border-radius:999px; font-size:11.5px; font-weight:700;">
										<i class="fa-solid fa-circle-check"></i> Confirmed
									</span>
								</td>
								<td style="display:flex; gap:8px; flex-wrap:wrap;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-open-chat-btn" data-recipient-id="4" data-recipient-name="Elena Rostova">
										<i class="fa-regular fa-comment-dots"></i> <?php esc_html_e( 'Message', 'tpd-tool' ); ?>
									</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<!-- VIEW 5: PROFILE & PLAN SETTINGS (Edit Representative Details, Upgrade Plan, Password Security, Delete Account) -->
			<div id="tpd-supp-settings" class="tpd-tab-panel">
				<div class="tpd-card-box mb-4">
					<h3><i class="fa-solid fa-user-gear"></i> <?php esc_html_e( 'Representative Profile Details', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Update your personal representative details, position/title, company name, and country.', 'tpd-tool' ); ?></p>

					<form id="tpd-supplier-rep-profile-form" class="mt-3">
						<input type="hidden" name="action" value="tpd_save_supplier_rep_profile">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

						<!-- Representative Avatar Upload -->
						<div style="display:flex; align-items:center; gap:16px; margin-bottom:20px; padding-bottom:18px; border-bottom:1px solid #e2e8f0;">
							<div style="position:relative; width:72px; height:72px;">
								<img id="tpd-supp-rep-avatar-preview" src="<?php echo esc_url( $avatar_src ); ?>" style="width:72px; height:72px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;" alt="Representative Avatar">
								<button type="button" onclick="document.getElementById('tpd-file-supp-headshot').click();" title="<?php esc_attr_e( 'Upload Representative Photo', 'tpd-tool' ); ?>" style="position:absolute; bottom:0; right:0; width:26px; height:26px; border-radius:50%; background:#0284c7; color:#fff; border:2px solid #fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:11px;">
									<i class="fa-solid fa-camera"></i>
								</button>
								<input type="hidden" name="headshot_url" id="tpd_supp_rep_headshot_url" value="<?php echo esc_attr( $headshot_url ); ?>">
								<input type="file" id="tpd-file-supp-headshot" accept="image/*" style="display:none;">
							</div>
							<div>
								<h4 style="margin:0 0 4px; font-size:15px; font-weight:700; color:#0f172a;"><?php esc_html_e( 'Representative Headshot Photo', 'tpd-tool' ); ?></h4>
								<p style="margin:0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Upload your professional headshot for travel advisors to recognise you in the directory & chat.', 'tpd-tool' ); ?></p>
								<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-supp-headshot').click();" style="margin-top:6px;">
									<i class="fa-solid fa-cloud-arrow-up"></i> <?php esc_html_e( 'Upload Headshot', 'tpd-tool' ); ?>
								</button>
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="first_name" required value="<?php echo esc_attr( $user ? $user->first_name : '' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="last_name" required value="<?php echo esc_attr( $user ? $user->last_name : '' ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Position/Title', 'tpd-tool' ); ?></label>
								<input type="text" name="position_title" value="<?php echo esc_attr( $position_title ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Supplier/Company Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="company_name" required value="<?php echo esc_attr( $company_name ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
								<input type="tel" name="phone" required value="<?php echo esc_attr( $rep_phone ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
								<input type="email" name="email" required value="<?php echo esc_attr( $user ? $user->user_email : '' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Country *', 'tpd-tool' ); ?></label>
								<select name="country" class="tpd-select">
									<?php foreach ( $countries as $c_opt ) : ?>
										<option value="<?php echo esc_attr( $c_opt ); ?>" <?php selected( $country, $c_opt ); ?>><?php echo esc_html( $c_opt ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<button type="submit" class="tpd-btn tpd-btn-darkblue mt-2">
							<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Representative Profile', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>

				<!-- Plan Upgrade Card -->
				<div class="tpd-card-box mb-4">
					<h3><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'Supplier Membership Plan', 'tpd-tool' ); ?></h3>
					<form id="tpd-user-upgrade-plan-form" class="mt-3">
						<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:16px;">
							<?php foreach ( $all_plans as $p_slug => $p_info ) :
								$m_price = (float) $p_info['monthly_price'];
							?>
								<label class="tpd-tier-option" style="border:2px solid <?php echo ( $user_tier === $p_slug ) ? '#2563eb' : '#e2e8f0'; ?>; border-radius:12px; padding:16px; cursor:pointer;">
									<input type="radio" name="plan_slug" value="<?php echo esc_attr( $p_slug ); ?>" <?php checked( $user_tier, $p_slug ); ?>>
									<div>
										<span class="tpd-to-tag"><?php echo esc_html( $p_info['badge'] ); ?></span>
										<h4 style="margin:4px 0;"><?php echo esc_html( $p_info['name'] ); ?></h4>
										<strong><?php echo $m_price <= 0 ? 'Free' : '$' . number_format( $m_price, 2 ) . '/mo'; ?></strong>
									</div>
								</label>
							<?php endforeach; ?>
						</div>
						<button type="submit" class="tpd-btn tpd-btn-darkblue"><?php esc_html_e( 'Update Supplier Plan', 'tpd-tool' ); ?></button>
					</form>
				</div>

				<!-- Password & Account Security -->
				<div class="tpd-card-box mb-4">
					<h3><i class="fa-solid fa-lock text-blue"></i> <?php esc_html_e( 'Password & Account Security', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Update your Supplier Portal login password.', 'tpd-tool' ); ?></p>
					<form id="tpd-user-password-form" class="mt-3">
						<input type="hidden" name="action" value="tpd_update_user_password">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Current Password *', 'tpd-tool' ); ?></label>
								<input type="password" name="current_password" required class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'New Password *', 'tpd-tool' ); ?></label>
								<input type="password" name="new_password" required minlength="6" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Confirm New Password *', 'tpd-tool' ); ?></label>
								<input type="password" name="confirm_password" required minlength="6" class="tpd-input">
							</div>
						</div>
						<button type="submit" class="tpd-btn tpd-btn-darkblue mt-2">
							<i class="fa-solid fa-key"></i> <?php esc_html_e( 'Update Password', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>

				<!-- Self-Service Account Deletion -->
				<div class="tpd-card-box" style="border:1px solid #fecaca; background:#fef2f2;">
					<h3 style="color:#991b1b; margin:0 0 8px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php esc_html_e( 'Delete Supplier Account', 'tpd-tool' ); ?></h3>
					<p style="color:#7f1d1d; font-size:13px; margin:0 0 14px;">
						<?php esc_html_e( 'Permanently delete your Supplier user account and associated brand listings.', 'tpd-tool' ); ?>
					</p>
					<button type="button" class="tpd-btn tpd-btn-sm" id="tpd-btn-delete-own-account" style="background:#dc2626; color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer;">
						<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Permanently Delete My Account', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>

			<!-- VIEW 6: TARC PARTNERSHIP -->
			<div id="tpd-supp-partnership" class="tpd-tab-panel">
				<div class="tpd-card-box mb-4" style="background:linear-gradient(135deg, #0b1526 0%, #0f172a 60%, #1e3a8a 100%); color:#ffffff; padding:30px; border-radius:14px;">
					<span style="background:rgba(245,158,11,0.2); border:1px solid #f59e0b; color:#fcd34d; padding:4px 12px; border-radius:999px; font-size:11.5px; font-weight:800; letter-spacing:0.06em;">
						<i class="fa-solid fa-star"></i> <?php esc_html_e( 'TARC TRADE MARKETING & CO-OP PROGRAMS', 'tpd-tool' ); ?>
					</span>
					<h3 style="margin:12px 0 8px; font-size:24px; font-weight:800; color:#ffffff;"><?php esc_html_e( 'Amplify Your Brand Across the TARC Advisor Network', 'tpd-tool' ); ?></h3>
					<p style="margin:0; font-size:14px; color:#cbd5e1; max-width:740px; line-height:1.55;">
						<?php esc_html_e( 'Put your brand, vessels, properties, and commission incentives directly in front of high-producing Travel Advisors through featured directory placements, live webinars, and newsletter takeovers.', 'tpd-tool' ); ?>
					</p>
				</div>

				<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(290px, 1fr)); gap:20px;">
					<!-- Package 1 -->
					<div class="tpd-card-box" style="display:flex; flex-direction:column; justify-content:space-between; border-top:4px solid #2563eb;">
						<div>
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
								<span style="background:#eff6ff; color:#1d4ed8; font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:6px;">DIRECTORY SPOTLIGHT</span>
								<strong style="color:#0b1526; font-size:16px;">$295 / mo</strong>
							</div>
							<h4 style="margin:0 0 8px; font-size:17px; color:#0b1526;"><?php esc_html_e( 'Featured Partner Directory Placement', 'tpd-tool' ); ?></h4>
							<p style="font-size:13px; color:#475569; line-height:1.5; margin:0 0 14px;">
								<?php esc_html_e( 'Pin your Supplier Showcase to the top of Advisor search results with a Gold Verified Partner badge and priority Virtual Office visibility.', 'tpd-tool' ); ?>
							</p>
							<ul style="list-style:none; padding:0; margin:0 0 18px; font-size:12.5px; color:#334155; display:flex; flex-direction:column; gap:6px;">
								<li><i class="fa-solid fa-check text-green"></i> Top-row placement in Advisor Directory Search</li>
								<li><i class="fa-solid fa-check text-green"></i> Featured card on Advisor Portal Home Dashboard</li>
								<li><i class="fa-solid fa-check text-green"></i> Monthly impression & click-through analytics</li>
							</ul>
						</div>
						<button type="button" class="tpd-btn tpd-btn-darkblue tpd-btn-block tpd-btn-request-partnership" data-package="Featured Partner Directory Placement">
							<i class="fa-solid fa-paper-plane"></i> <?php esc_html_e( 'Request Spotlight Slot', 'tpd-tool' ); ?>
						</button>
					</div>

					<!-- Package 2 -->
					<div class="tpd-card-box" style="display:flex; flex-direction:column; justify-content:space-between; border-top:4px solid #00798c;">
						<div>
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
								<span style="background:#f0fdfa; color:#0f766e; font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:6px;">LIVE EDUCATION</span>
								<strong style="color:#0b1526; font-size:16px;">$450 / session</strong>
							</div>
							<h4 style="margin:0 0 8px; font-size:17px; color:#0b1526;"><?php esc_html_e( 'Dedicated Advisor Masterclass Webinar', 'tpd-tool' ); ?></h4>
							<p style="font-size:13px; color:#475569; line-height:1.5; margin:0 0 14px;">
								<?php esc_html_e( 'Host a 45-minute live interactive training webinar with TARC Travel Advisors, including full registration list and on-demand replay hosting.', 'tpd-tool' ); ?>
							</p>
							<ul style="list-style:none; padding:0; margin:0 0 18px; font-size:12.5px; color:#334155; display:flex; flex-direction:column; gap:6px;">
								<li><i class="fa-solid fa-check text-green"></i> Dedicated email invitation to all TARC Advisors</li>
								<li><i class="fa-solid fa-check text-green"></i> Featured on Advisor Upcoming Events calendar</li>
								<li><i class="fa-solid fa-check text-green"></i> Full attendee lead report & recording archive</li>
							</ul>
						</div>
						<button type="button" class="tpd-btn tpd-btn-darkblue tpd-btn-block tpd-btn-request-partnership" data-package="Dedicated Advisor Masterclass Webinar">
							<i class="fa-solid fa-paper-plane"></i> <?php esc_html_e( 'Book Webinar Date', 'tpd-tool' ); ?>
						</button>
					</div>

					<!-- Package 3 -->
					<div class="tpd-card-box" style="display:flex; flex-direction:column; justify-content:space-between; border-top:4px solid #f59e0b;">
						<div>
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
								<span style="background:#fef3c7; color:#b45309; font-size:11.5px; font-weight:700; padding:4px 10px; border-radius:6px;">EMAIL MARKETING</span>
								<strong style="color:#0b1526; font-size:16px;">$350 / issue</strong>
							</div>
							<h4 style="margin:0 0 8px; font-size:17px; color:#0b1526;"><?php esc_html_e( 'TARC Advisor Newsletter Takeover', 'tpd-tool' ); ?></h4>
							<p style="font-size:13px; color:#475569; line-height:1.5; margin:0 0 14px;">
								<?php esc_html_e( 'Showcase your latest advisor booking incentives, FAM trips, or new itinerary launches in the weekly TARC Advisor Dispatch.', 'tpd-tool' ); ?>
							</p>
							<ul style="list-style:none; padding:0; margin:0 0 18px; font-size:12.5px; color:#334155; display:flex; flex-direction:column; gap:6px;">
								<li><i class="fa-solid fa-check text-green"></i> Hero banner + 200-word editorial spotlight</li>
								<li><i class="fa-solid fa-check text-green"></i> Direct link to your booking portal & brochure PDF</li>
								<li><i class="fa-solid fa-check text-green"></i> Synchronized post in TARC News feed</li>
							</ul>
						</div>
						<button type="button" class="tpd-btn tpd-btn-darkblue tpd-btn-block tpd-btn-request-partnership" data-package="TARC Advisor Newsletter Takeover">
							<i class="fa-solid fa-paper-plane"></i> <?php esc_html_e( 'Reserve Newsletter Issue', 'tpd-tool' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- VIEW 7: HELP & SUPPORT -->
			<div id="tpd-supp-help" class="tpd-tab-panel">
				<div class="tpd-form-grid-2" style="align-items:start; gap:24px;">
					<!-- Left Column: FAQs -->
					<div class="tpd-card-box">
						<h3 style="margin:0 0 8px;"><i class="fa-regular fa-circle-question text-blue"></i> <?php esc_html_e( 'Supplier Partner FAQs', 'tpd-tool' ); ?></h3>
						<p class="text-muted" style="margin:0 0 18px; font-size:13px;"><?php esc_html_e( 'Answers to common questions about managing your Supplier Showcase, Virtual Office team, and Advisor leads.', 'tpd-tool' ); ?></p>
						<div style="display:flex; flex-direction:column; gap:14px;">
							<?php foreach ( $faqs as $faq ) : ?>
								<div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;">
									<h4 style="margin:0 0 6px; font-size:14.5px; color:#0b1526;"><i class="fa-solid fa-circle-info" style="color:#2563eb; margin-right:6px;"></i><?php echo esc_html( $faq['q'] ); ?></h4>
									<p style="margin:0; font-size:13px; color:#475569; line-height:1.55;"><?php echo esc_html( $faq['a'] ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Right Column: Direct Partner Support Form -->
					<div class="tpd-card-box">
						<h3 style="margin:0 0 8px;"><i class="fa-solid fa-headset text-gold"></i> <?php esc_html_e( 'Contact TARC Partner Success Team', 'tpd-tool' ); ?></h3>
						<p class="text-muted" style="margin:0 0 18px; font-size:13px;">
							<?php esc_html_e( 'Need assistance with your brand showcase, custom fields, billing tier, or claiming an existing listing? Send a priority message to our Partner Concierge.', 'tpd-tool' ); ?>
						</p>

						<form id="tpd-supplier-support-ticket-form">
							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Subject / Inquiry Category *', 'tpd-tool' ); ?></label>
								<select class="tpd-select" required>
									<option value="Showcase & Listing Assistance"><?php esc_html_e( 'Showcase & Listing Optimization', 'tpd-tool' ); ?></option>
									<option value="Claiming an Existing Brand Listing"><?php esc_html_e( 'Claiming an Existing Brand Listing', 'tpd-tool' ); ?></option>
									<option value="Membership Plan & Billing"><?php esc_html_e( 'Membership Plan & Billing', 'tpd-tool' ); ?></option>
									<option value="Co-Op Marketing & Webinars"><?php esc_html_e( 'Co-Op Marketing & Webinars', 'tpd-tool' ); ?></option>
									<option value="Technical Support"><?php esc_html_e( 'Technical Support', 'tpd-tool' ); ?></option>
								</select>
							</div>
							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'How can our team help you? *', 'tpd-tool' ); ?></label>
								<textarea rows="5" class="tpd-textarea" required placeholder="<?php esc_attr_e( 'Describe your question or request...', 'tpd-tool' ); ?>"></textarea>
							</div>
							<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-btn-block">
								<i class="fa-solid fa-paper-plane"></i> <?php esc_html_e( 'Submit Priority Partner Request', 'tpd-tool' ); ?>
							</button>
						</form>

						<div style="margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0; display:flex; justify-content:space-between; font-size:12.5px; color:#64748b;">
							<span><i class="fa-regular fa-envelope"></i> partners@travelpartnerdirectory.com</span>
							<span><i class="fa-regular fa-clock"></i> Mon–Fri · 9am–6pm EST</span>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>

<!-- Self-Contained Tab & Sub-Tab Fallback Controller (immune to stale browser JS caches) -->
<script>
(function() {
	function activateSupplierTab(rawView, subTabId) {
		if (!rawView) return;
		var clean = String(rawView).replace(/^#/, '');
		var stripped = clean.replace(/^(tpd-view-|tpd-supp-|tpd-|supp-|view-)/, '');
		var candidates = [
			clean,
			'tpd-' + clean,
			'tpd-supp-' + stripped,
			'tpd-view-' + stripped,
			'tpd-supp-' + clean
		];
		var targetEl = null;
		for (var i = 0; i < candidates.length; i++) {
			var el = document.getElementById(candidates[i]);
			if (el && el.classList.contains('tpd-tab-panel')) {
				targetEl = el;
				break;
			}
		}
		if (!targetEl) return;

		// Hide all tab panels and show targetEl
		var panels = document.querySelectorAll('.tpd-tab-panel');
		for (var j = 0; j < panels.length; j++) {
			panels[j].classList.remove('active');
			panels[j].style.display = 'none';
		}
		targetEl.classList.add('active');
		targetEl.style.display = 'block';

		// Update active sidebar item
		var navItems = document.querySelectorAll('.tpd-sb-nav-list .tpd-nav-item');
		for (var k = 0; k < navItems.length; k++) {
			navItems[k].classList.remove('active');
		}
		var resolvedId = targetEl.getAttribute('id');
		var navLink = document.querySelector(
			'.tpd-sb-nav-list .tpd-tab-link[href="#' + resolvedId + '"], ' +
			'.tpd-sb-nav-list .tpd-tab-link[data-view="' + clean + '"], ' +
			'.tpd-sb-nav-list .tpd-tab-link[data-view="supp-' + stripped + '"]'
		);
		if (navLink) {
			var parentLi = navLink.closest('.tpd-nav-item');
			if (parentLi) parentLi.classList.add('active');
		}

		if (subTabId) {
			var subBtn = document.querySelector('.tpd-tab-btn[data-target="' + subTabId + '"]');
			if (subBtn) subBtn.click();
		}
	}

	document.addEventListener('click', function(e) {
		var link = e.target.closest('.tpd-tab-link');
		if (link) {
			var view = link.getAttribute('data-view') || link.getAttribute('href');
			var subtab = link.getAttribute('data-subtab') || '';
			if (view) {
				e.preventDefault();
				activateSupplierTab(view, subtab);
			}
		}
	});
})();
</script>

<?php wp_footer(); ?>
</body>
</html>

