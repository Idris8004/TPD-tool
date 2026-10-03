<?php
/**
 * Travel Partner Directory by TARC - Advisor Dashboard
 * Dynamically connected to WP User Meta, travel_advisor CPT, supplier_listing CPT,
 * ACF Field Groups, and Super Admin Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();
if ( ! $current_user_id ) {
	$adv_users = get_users( array( 'role' => 'travel_advisor', 'number' => 1, 'orderby' => 'ID', 'order' => 'DESC' ) );
	$current_user_id = ! empty( $adv_users ) ? $adv_users[0]->ID : 1;
}

$user       = get_userdata( $current_user_id );
$user_tier  = tpd_get_user_tier( $current_user_id );
$plan_info  = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plan( $user_tier ) : array( 'name' => ucfirst( $user_tier ), 'max_specialties' => 5 );
$first_name = ( $user && $user->first_name ) ? $user->first_name : ( $user ? $user->display_name : 'Advisor' );
$last_name  = ( $user && $user->last_name ) ? $user->last_name : '';
$full_name  = ( $user && $user->display_name ) ? $user->display_name : trim( $first_name . ' ' . $last_name );

// Paired travel_advisor CPT post ID
$advisor_post_id = 0;
$query_adv = get_posts( array(
	'post_type'      => 'travel_advisor',
	'posts_per_page' => 1,
	'meta_key'       => 'tpd_assigned_user',
	'meta_value'     => $current_user_id,
	'fields'         => 'ids',
) );
if ( ! empty( $query_adv ) ) {
	$advisor_post_id = $query_adv[0];
}

// Pull real profile & agency metadata (checking both post_meta and user_meta)
$agency_name    = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_agency_name', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_agency_name', true );
$agency_address = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_agency_address', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_agency_address', true );
$phone          = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_phone', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_phone', true );
$location       = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_location', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_location', true );
$website        = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_website', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_website', true );
$consortia      = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_consortia', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_consortia', true );
$host_agency    = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_host_agency', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_host_agency', true );
$clia_num       = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_clia_number', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_clia_num', true );
$iata_num       = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_iata_number', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_iata_num', true );
$arc_num        = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_arc_number', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_arc_num', true );
$true_num       = ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_true_number', true ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_true_num', true );
$headshot_url   = get_user_meta( $current_user_id, 'tpd_headshot_url', true ) ?: ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_headshot_url', true ) : '' );
$logo_url       = get_user_meta( $current_user_id, 'tpd_logo_url', true ) ?: ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_logo_url', true ) : '' );
$banner_url     = get_user_meta( $current_user_id, 'tpd_banner_url', true ) ?: ( $advisor_post_id ? get_post_meta( $advisor_post_id, 'tpd_banner_url', true ) : '' );
$bio_content    = $advisor_post_id ? get_post_field( 'post_content', $advisor_post_id ) : '';

$avatar_src = $headshot_url ?: 'https://ui-avatars.com/api/?name=' . rawurlencode( $full_name ) . '&background=2563eb&color=fff&size=128';

$metrics            = TPD_Tool_Analytics::get_advisor_metrics( $advisor_post_id, $current_user_id );
$saved_supplier_ids = TPD_Tool_Favorites::get_saved_supplier_ids( $current_user_id );
$events             = TPD_Tool_Events::get_upcoming_events( 4 );
$ads_cfg            = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_ad_banners() : array();
$all_plans          = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'advisor' ) : array();
$faqs               = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_faqs() : array();

// Fetch Real Supplier Listings from CPT
$supplier_posts = get_posts( array(
	'post_type'      => 'supplier_listing',
	'posts_per_page' => 12,
	'post_status'    => 'publish',
) );

// Fetch Real News / Blogs from supplier_blog CPT
$news_posts = get_posts( array(
	'post_type'      => 'supplier_blog',
	'posts_per_page' => 6,
	'post_status'    => 'publish',
) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Advisor Dashboard | Travel Partner Directory by TARC', 'tpd-tool' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="tpd-app-body tpd-theme-dark-sidebar">

<div class="tpd-portal-shell">
	<!-- 1. LEFT SIDEBAR (Dark Navy #0b1526) -->
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
					<a href="#tpd-view-overview" class="tpd-tab-link" data-view="overview">
						<i class="fa-solid fa-house"></i>
						<span><?php esc_html_e( '1. Dashboard', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-profile" class="tpd-tab-link" data-view="profile">
						<i class="fa-regular fa-id-card"></i>
						<span><?php esc_html_e( '2. My Profile Listing', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-suppliers" class="tpd-tab-link" data-view="suppliers">
						<i class="fa-solid fa-magnifying-glass"></i>
						<span><?php esc_html_e( '3. Search Suppliers', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-saved" class="tpd-tab-link" data-view="saved">
						<i class="fa-regular fa-bookmark"></i>
						<span><?php esc_html_e( '4. Saved Suppliers', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-inquiries" class="tpd-tab-link" data-view="inquiries">
						<i class="fa-solid fa-inbox"></i>
						<span><?php esc_html_e( '5. Client Inquiries', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-conversations" class="tpd-tab-link" data-view="conversations">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( '6. Chat & Messages', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-meetings" class="tpd-tab-link" data-view="meetings">
						<i class="fa-regular fa-calendar"></i>
						<span><?php esc_html_e( '7. Meeting Requests', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-resources" class="tpd-tab-link" data-view="resources">
						<i class="fa-solid fa-book-open"></i>
						<span><?php esc_html_e( '8. Resources & Tools', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-comparison" class="tpd-tab-link" data-view="comparison">
						<i class="fa-solid fa-ship"></i>
						<span><?php esc_html_e( '9. Cruise Comparison', 'tpd-tool' ); ?></span>
						<small class="tpd-badge-tag" style="background:#fef3c7; color:#b45309; font-size:9.5px; padding:2px 6px; border-radius:4px; margin-left:auto;">Soon</small>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-connect" class="tpd-tab-link" data-view="connect">
						<i class="fa-solid fa-users-rays"></i>
						<span><?php esc_html_e( '10. The TARC Connect', 'tpd-tool' ); ?></span>
						<small class="tpd-badge-tag" style="background:#fef3c7; color:#b45309; font-size:9.5px; padding:2px 6px; border-radius:4px; margin-left:auto;">Soon</small>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-events" class="tpd-tab-link" data-view="events">
						<i class="fa-regular fa-calendar-check"></i>
						<span><?php esc_html_e( '11. Upcoming Events', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-news" class="tpd-tab-link" data-view="news">
						<i class="fa-solid fa-bullhorn"></i>
						<span><?php esc_html_e( '12. TARC News', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-settings" class="tpd-tab-link" data-view="settings">
						<i class="fa-solid fa-gear"></i>
						<span><?php esc_html_e( '13. Settings & Plan', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-help" class="tpd-tab-link" data-view="help">
						<i class="fa-regular fa-circle-question"></i>
						<span><?php esc_html_e( '14. Help & Support', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-ads" class="tpd-tab-link" data-view="ads">
						<i class="fa-solid fa-rectangle-ad"></i>
						<span><?php esc_html_e( '15. Ad Space', 'tpd-tool' ); ?></span>
					</a>
				</li>
			</ul>
		</nav>

		<div class="tpd-sb-footer-card" style="background-image: url('https://images.unsplash.com/photo-1533105079780-92b9be482077?w=400&auto=format&fit=crop&q=80');">
			<div class="tpd-sbfc-overlay">
				<p class="tpd-sbfc-quote"><em>Stronger Partnerships.<br>More Bookings.</em></p>
			</div>
		</div>
	</aside>

	<!-- 2. MAIN CONTENT AREA -->
	<div class="tpd-portal-body">
		<!-- Top Navigation Header -->
		<header class="tpd-portal-topbar">
			<div class="tpd-topbar-search">
				<i class="fa-solid fa-magnifying-glass"></i>
				<input type="text" placeholder="<?php esc_attr_e( 'Search suppliers, destinations, or travel types...', 'tpd-tool' ); ?>" class="tpd-top-search-input" id="tpd-topbar-search-input">
			</div>

			<div class="tpd-topbar-actions">
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/tpd-admin/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none;">
						<i class="fa-solid fa-shield-halved text-gold"></i> <?php esc_html_e( 'Super Admin Hub', 'tpd-tool' ); ?>
					</a>
				<?php endif; ?>

				<!-- User Profile Pill -->
				<div class="tpd-user-pill-dropdown tpd-tab-link" data-view="profile" style="cursor:pointer;">
					<img src="<?php echo esc_url( $avatar_src ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" class="tpd-user-avatar-sm">
					<div class="tpd-user-pill-info">
						<span class="tpd-user-name"><?php echo esc_html( $full_name ); ?></span>
						<span class="tpd-user-role-label">
							<?php echo esc_html( $agency_name ?: __( 'Travel Advisor', 'tpd-tool' ) ); ?> · <strong class="tpd-tier-tag"><?php echo esc_html( strtoupper( $user_tier ) ); ?></strong>
						</span>
					</div>
				</div>

				<a href="<?php echo esc_url( wp_logout_url( home_url( '/advisor-registration/' ) ) ); ?>" class="tpd-top-icon-btn" title="<?php esc_attr_e( 'Log Out', 'tpd-tool' ); ?>" style="text-decoration:none; color:#64748b;">
					<i class="fa-solid fa-right-from-bracket"></i>
				</a>
			</div>
		</header>

		<!-- Workspace / Main View Area -->
		<main class="tpd-workspace-content">
			<!-- VIEW 1: OVERVIEW -->
			<div id="tpd-view-overview" class="tpd-tab-panel active">
				<!-- Dynamic Welcome Banner -->
				<?php
				$hero_bg    = ! empty( $ads_cfg['welcome_bg'] ) ? $ads_cfg['welcome_bg'] : 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=1600&auto=format&fit=crop&q=80';
				$hero_sub   = ! empty( $ads_cfg['welcome_title'] ) ? $ads_cfg['welcome_title'] : 'Find the right suppliers. Get the answers you need. Help your clients travel better.';
				$hero_quote = ! empty( $ads_cfg['welcome_quote'] ) ? $ads_cfg['welcome_quote'] : 'Same clients. Bigger possibilities.';
				?>
				<div class="tpd-hero-welcome-card" style="background-image: linear-gradient(90deg, rgba(11, 21, 38, 0.85) 0%, rgba(11, 21, 38, 0.45) 50%, rgba(11, 21, 38, 0.15) 100%), url('<?php echo esc_url( $hero_bg ); ?>');">
					<div class="tpd-hwc-content">
						<h1 class="tpd-hwc-title">Welcome back, <span><?php echo esc_html( $first_name ); ?>!</span></h1>
						<p class="tpd-hwc-subtitle"><?php echo esc_html( $hero_sub ); ?></p>
					</div>
					<div class="tpd-hwc-quote-wrap">
						<span class="tpd-hwc-quote">"<?php echo esc_html( $hero_quote ); ?>"</span>
					</div>
				</div>

				<!-- Dynamic Admin-Configurable Square Ad Space Row -->
				<div class="tpd-ad-banner-strip mb-4" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-top:20px;">
					<div class="tpd-ad-square-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; gap:14px; align-items:center; position:relative; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
						<span style="position:absolute; top:8px; right:10px; font-size:9.5px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
							<?php echo esc_html( ! empty( $ads_cfg['ad_1_tag'] ) ? $ads_cfg['ad_1_tag'] : 'SPONSORED' ); ?>
						</span>
						<img src="<?php echo esc_url( ! empty( $ads_cfg['ad_1_image'] ) ? $ads_cfg['ad_1_image'] : 'https://images.unsplash.com/photo-1548574505-5e239809ee19?w=160&auto=format&fit=crop&q=80' ); ?>" alt="Ad 1" style="width:72px; height:72px; border-radius:8px; object-fit:cover;">
						<div style="flex:1;">
							<h5 style="margin:0 0 4px; font-size:13.5px; font-weight:700; color:#0f172a;"><?php echo esc_html( ! empty( $ads_cfg['ad_1_title'] ) ? $ads_cfg['ad_1_title'] : 'Earn 18% Commission on 2026 Danube Charters' ); ?></h5>
							<p style="margin:0 0 6px; font-size:12px; color:#64748b;"><?php echo esc_html( ! empty( $ads_cfg['ad_1_subtitle'] ) ? $ads_cfg['ad_1_subtitle'] : 'Limited Time Advisor Incentive' ); ?></p>
							<a href="<?php echo esc_url( ! empty( $ads_cfg['ad_1_link_url'] ) ? $ads_cfg['ad_1_link_url'] : '#tpd-view-suppliers' ); ?>" class="tpd-tab-link" data-view="suppliers" style="font-size:11.5px; font-weight:700; color:#2563eb; text-decoration:none;">
								<?php echo esc_html( ! empty( $ads_cfg['ad_1_link_text'] ) ? $ads_cfg['ad_1_link_text'] : 'Explore Itineraries' ); ?> &rarr;
							</a>
						</div>
					</div>

					<div class="tpd-ad-square-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; gap:14px; align-items:center; position:relative; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
						<span style="position:absolute; top:8px; right:10px; font-size:9.5px; font-weight:800; background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; text-transform:uppercase;">
							<?php echo esc_html( ! empty( $ads_cfg['ad_2_tag'] ) ? $ads_cfg['ad_2_tag'] : 'FEATURED' ); ?>
						</span>
						<img src="<?php echo esc_url( ! empty( $ads_cfg['ad_2_image'] ) ? $ads_cfg['ad_2_image'] : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=160&auto=format&fit=crop&q=80' ); ?>" alt="Ad 2" style="width:72px; height:72px; border-radius:8px; object-fit:cover;">
						<div style="flex:1;">
							<h5 style="margin:0 0 4px; font-size:13.5px; font-weight:700; color:#0f172a;"><?php echo esc_html( ! empty( $ads_cfg['ad_2_title'] ) ? $ads_cfg['ad_2_title'] : 'Preferred Partner Luxury Resort Program' ); ?></h5>
							<p style="margin:0 0 6px; font-size:12px; color:#64748b;"><?php echo esc_html( ! empty( $ads_cfg['ad_2_subtitle'] ) ? $ads_cfg['ad_2_subtitle'] : 'Daily breakfast, $100 resort credits & VIP perks' ); ?></p>
							<a href="<?php echo esc_url( ! empty( $ads_cfg['ad_2_link_url'] ) ? $ads_cfg['ad_2_link_url'] : '#tpd-view-suppliers' ); ?>" class="tpd-tab-link" data-view="suppliers" style="font-size:11.5px; font-weight:700; color:#2563eb; text-decoration:none;">
								<?php echo esc_html( ! empty( $ads_cfg['ad_2_link_text'] ) ? $ads_cfg['ad_2_link_text'] : 'View Partner Desk' ); ?> &rarr;
							</a>
						</div>
					</div>
				</div>

				<!-- 3 Analytics Metric Cards -->
				<div class="tpd-metrics-row-3">
					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-blue"><i class="fa-solid fa-eye"></i></div>
						<div class="tpd-mw-body">
							<div class="tpd-mw-val-line">
								<span class="tpd-mw-value"><?php echo esc_html( $metrics['profile_views']['value'] ); ?></span>
								<div class="tpd-mw-sparkline"><i class="fa-solid fa-chart-simple"></i></div>
							</div>
							<span class="tpd-mw-label"><?php echo esc_html( $metrics['profile_views']['label'] ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['profile_views']['trend'] . ' ' . $metrics['profile_views']['sub'] ); ?></span>
						</div>
					</div>

					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-navy"><i class="fa-solid fa-users"></i></div>
						<div class="tpd-mw-body">
							<span class="tpd-mw-value"><?php echo esc_html( $metrics['client_inquiries']['value'] ); ?></span>
							<span class="tpd-mw-label"><?php echo esc_html( $metrics['client_inquiries']['label'] ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['client_inquiries']['trend'] . ' ' . $metrics['client_inquiries']['sub'] ); ?></span>
						</div>
					</div>

					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-cyan"><i class="fa-solid fa-comment-dots"></i></div>
						<div class="tpd-mw-body">
							<span class="tpd-mw-value"><?php echo esc_html( $metrics['messages']['value'] ); ?></span>
							<span class="tpd-mw-label"><?php esc_html_e( 'Supplier Messages', 'tpd-tool' ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['messages']['trend'] . ' ' . $metrics['messages']['sub'] ); ?></span>
						</div>
					</div>
				</div>

				<!-- Two Column Body -->
				<div class="tpd-dashboard-columns">
					<div class="tpd-dash-col-main">
						<!-- Search Filter Box -->
						<div class="tpd-card-box tpd-supplier-search-box">
							<div class="tpd-ss-header">
								<h3><?php esc_html_e( 'Find Your Next Supplier', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-suppliers" class="tpd-link-arrow tpd-tab-link" data-view="suppliers"><?php esc_html_e( 'Browse Full Directory', 'tpd-tool' ); ?> &rarr;</a>
							</div>
							<p class="tpd-ss-desc"><?php esc_html_e( 'Search our supplier directory and connect directly with partner representatives.', 'tpd-tool' ); ?></p>

							<div class="tpd-ss-search-bar">
								<div class="tpd-ss-input-wrap">
									<i class="fa-solid fa-magnifying-glass"></i>
									<input type="text" placeholder="<?php esc_attr_e( 'Search suppliers, destinations, travel types, or keywords...', 'tpd-tool' ); ?>" id="tpd-live-supplier-search">
								</div>
								<button type="button" class="tpd-btn tpd-btn-darkblue" id="tpd-btn-search-suppliers">
									<?php esc_html_e( 'Search', 'tpd-tool' ); ?>
								</button>
							</div>
						</div>

						<!-- Dynamic Featured Suppliers Grid (Pulled from supplier_listing CPT) -->
						<div class="tpd-featured-suppliers-section">
							<div class="tpd-fss-header">
								<h3><?php esc_html_e( 'Featured Suppliers', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-suppliers" class="tpd-link-arrow tpd-tab-link" data-view="suppliers"><?php esc_html_e( 'View All Suppliers', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-featured-suppliers-grid">
								<?php
								$display_suppliers = array_slice( $supplier_posts, 0, 4 );
								foreach ( $display_suppliers as $sp ) :
									$sp_banner = get_post_meta( $sp->ID, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=500&auto=format&fit=crop&q=80';
									$sp_cat    = get_post_meta( $sp->ID, 'tpd_tagline', true ) ?: get_post_meta( $sp->ID, 'tpd_supplier_category', true ) ?: 'Preferred Supplier Partner';
									$sp_owner  = (int) get_post_meta( $sp->ID, 'tpd_assigned_user', true ) ?: 1;
									$sp_team   = (array) get_post_meta( $sp->ID, '_tpd_team_members', true );
									$rep_count = ! empty( $sp_team ) ? count( $sp_team ) : 1;
								?>
									<div class="tpd-supp-showcase-card tpd-supplier-profile-card">
										<div class="tpd-ssc-thumb" style="background-image: url('<?php echo esc_url( $sp_banner ); ?>');">
											<span class="tpd-reps-online-badge"><i class="fa-solid fa-circle"></i> <?php echo esc_html( $rep_count ); ?> Rep<?php echo $rep_count > 1 ? 's' : ''; ?> Online</span>
										</div>
										<div class="tpd-ssc-body">
											<div class="tpd-ssc-logo-row">
												<span class="tpd-brand-title"><i class="fa-solid fa-building-circle-check text-blue"></i> <?php echo esc_html( $sp->post_title ); ?></span>
											</div>
											<span class="tpd-ssc-category"><?php echo esc_html( wp_trim_words( $sp_cat, 5 ) ); ?></span>
											<div class="tpd-ssc-actions" style="margin-top:auto;">
												<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline" style="text-decoration:none; text-align:center;"><?php esc_html_e( 'View Profile', 'tpd-tool' ); ?></a>
												<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="<?php echo esc_attr( $sp_owner ); ?>" data-recipient-name="<?php echo esc_attr( $sp->post_title ); ?>">
													<i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Chat Now', 'tpd-tool' ); ?>
												</button>
											</div>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- Dynamic TARC News Row (Pulled from supplier_blog CPT) -->
						<div class="tpd-news-section-card">
							<div class="tpd-fss-header">
								<h3><?php esc_html_e( 'TARC News & Partner Updates', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-news" class="tpd-link-arrow tpd-tab-link" data-view="news"><?php esc_html_e( 'View All News', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-news-cards-grid">
								<?php if ( ! empty( $news_posts ) ) : ?>
									<?php foreach ( array_slice( $news_posts, 0, 3 ) as $np ) : ?>
										<article class="tpd-news-mini-card">
											<div class="tpd-nmc-thumb" style="background-image: url('https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=400&auto=format&fit=crop&q=80');">
												<span class="tpd-news-date"><?php echo esc_html( strtoupper( get_the_date( 'M j, Y', $np->ID ) ) ); ?></span>
											</div>
											<div class="tpd-nmc-body">
												<h4><?php echo esc_html( $np->post_title ); ?></h4>
												<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $np->post_content ), 18 ) ); ?></p>
											</div>
										</article>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="text-muted"><?php esc_html_e( 'No partner news articles published yet.', 'tpd-tool' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>

					<!-- RIGHT SIDEBAR COLUMN -->
					<aside class="tpd-dash-col-side">
						<!-- Quick Actions -->
						<div class="tpd-card-box tpd-quick-actions-box">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'Quick Actions', 'tpd-tool' ); ?></h4>
							</div>

							<div class="tpd-quick-actions-grid-6">
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-tab-link" data-view="suppliers">
									<i class="fa-solid fa-magnifying-glass"></i>
									<span><?php esc_html_e( 'Search Suppliers', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-conversations" class="tpd-qa-tile tpd-tab-link" data-view="conversations">
									<i class="fa-regular fa-comment-dots"></i>
									<span><?php esc_html_e( 'Message a Supplier', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-profile" class="tpd-qa-tile tpd-tab-link" data-view="profile">
									<i class="fa-regular fa-id-card"></i>
									<span><?php esc_html_e( 'Edit My Profile', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-meetings" class="tpd-tab-link tpd-qa-tile" data-view="meetings">
									<i class="fa-regular fa-calendar-plus"></i>
									<span><?php esc_html_e( 'Schedule a Meeting', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-saved" class="tpd-tab-link tpd-qa-tile" data-view="saved">
									<i class="fa-regular fa-heart"></i>
									<span><?php esc_html_e( 'Saved Suppliers', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-settings" class="tpd-tab-link tpd-qa-tile" data-view="settings">
									<i class="fa-solid fa-crown"></i>
									<span><?php esc_html_e( 'Upgrade Plan', 'tpd-tool' ); ?></span>
								</a>
							</div>
						</div>

						<!-- Saved Suppliers Mini List -->
						<div class="tpd-card-box tpd-saved-suppliers-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'Saved Suppliers', 'tpd-tool' ); ?></h4>
								<a href="#tpd-view-saved" class="tpd-clean-link tpd-tab-link" data-view="saved"><?php esc_html_e( 'View All', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-saved-suppliers-list">
								<?php
								$saved_list = ! empty( $saved_supplier_ids ) ? get_posts( array( 'post_type' => 'supplier_listing', 'post__in' => $saved_supplier_ids, 'posts_per_page' => 5 ) ) : array_slice( $supplier_posts, 0, 4 );
								foreach ( $saved_list as $sv_sp ) :
								?>
									<div class="tpd-saved-supp-item">
										<div class="tpd-ssi-icon"><i class="fa-solid fa-building text-blue"></i></div>
										<div class="tpd-ssi-info">
											<strong><?php echo esc_html( $sv_sp->post_title ); ?></strong>
											<span><?php echo esc_html( get_post_meta( $sv_sp->ID, 'tpd_tagline', true ) ?: 'Verified Supplier' ); ?></span>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- Upcoming Events & Webinars -->
						<div class="tpd-card-box tpd-events-widget-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'Upcoming Events & Webinars', 'tpd-tool' ); ?></h4>
								<a href="#tpd-view-events" class="tpd-clean-link tpd-tab-link" data-view="events"><?php esc_html_e( 'View All', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-events-stack">
								<?php foreach ( $events as $ev ) : ?>
									<div class="tpd-event-stack-row">
										<div class="tpd-esr-date">
											<span class="tpd-esr-m"><?php echo esc_html( $ev['month'] ); ?></span>
											<span class="tpd-esr-d"><?php echo esc_html( $ev['day'] ); ?></span>
										</div>
										<div class="tpd-esr-details">
											<h5><?php echo esc_html( $ev['title'] ); ?></h5>
											<span class="tpd-esr-series"><?php echo esc_html( $ev['series'] ); ?></span>
											<span class="tpd-esr-time"><?php echo esc_html( $ev['time'] ); ?></span>
										</div>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-reg-event-btn" data-event-id="<?php echo esc_attr( $ev['id'] ); ?>">
											<?php esc_html_e( 'Register', 'tpd-tool' ); ?>
										</button>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</aside>
				</div>
			</div>

			<!-- VIEW 2: MY PROFILE LISTING (100% Real Data + Dynamic ACF & Custom Fields) -->
			<div id="tpd-view-profile" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header mb-3">
						<div>
							<h3><i class="fa-regular fa-id-card"></i> <?php esc_html_e( 'My Profile Listing & Public Showcase', 'tpd-tool' ); ?></h3>
							<p class="text-muted" style="margin:4px 0 0;"><?php esc_html_e( 'All edits save directly to your Travel Advisor Directory Profile and sync with WP Admin.', 'tpd-tool' ); ?></p>
						</div>
						<div class="tpd-profile-status-badge">
							<?php if ( $advisor_post_id ) : ?>
								<a href="<?php echo esc_url( get_permalink( $advisor_post_id ) ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none;">
									<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View Public Profile', 'tpd-tool' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>

					<form id="tpd-advisor-profile-editor-form" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="tpd_save_advisor_profile">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

						<!-- Media & Visual Identity -->
						<div class="tpd-editor-section" style="border:1px solid #e2e8f0; border-radius:14px; padding:22px; margin-bottom:24px; background:#f8fafc;">
							<h4 style="margin:0 0 16px; font-size:15px; font-weight:700; color:#0b1526;"><i class="fa-solid fa-camera"></i> <?php esc_html_e( 'Profile Picture, Agency Logo & Banner', 'tpd-tool' ); ?></h4>
							
							<div style="display:grid; grid-template-columns: auto 1fr 1fr; gap:24px; align-items:start;">
								<!-- Headshot -->
								<div style="text-align:center;">
									<label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;"><?php esc_html_e( 'Profile Photo', 'tpd-tool' ); ?></label>
									<img id="tpd-preview-headshot" src="<?php echo esc_url( $avatar_src ); ?>" style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid #ffffff; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin-bottom:10px; display:block; margin-left:auto; margin-right:auto;">
									<input type="hidden" name="headshot_url" id="tpd_headshot_url" value="<?php echo esc_attr( $headshot_url ); ?>">
									<input type="file" id="tpd-file-headshot" accept="image/*" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-headshot').click();">
										<i class="fa-solid fa-arrow-up-from-bracket"></i> <?php esc_html_e( 'Change Photo', 'tpd-tool' ); ?>
									</button>
								</div>

								<!-- Agency Logo -->
								<div>
									<label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;"><?php esc_html_e( 'Agency Logo', 'tpd-tool' ); ?></label>
									<div style="width:100%; height:90px; border:2px dashed #cbd5e1; border-radius:10px; display:flex; align-items:center; justify-content:center; background:#ffffff; overflow:hidden;">
										<img id="tpd-preview-logo" src="<?php echo esc_url( $logo_url ); ?>" style="<?php echo $logo_url ? 'max-height:80px;' : 'display:none;'; ?>">
										<span id="tpd-logo-placeholder" style="<?php echo $logo_url ? 'display:none;' : ''; ?> font-size:12px; color:#94a3b8;"><i class="fa-regular fa-image"></i> <?php esc_html_e( 'Upload Agency Logo', 'tpd-tool' ); ?></span>
									</div>
									<input type="hidden" name="logo_url" id="tpd_logo_url" value="<?php echo esc_attr( $logo_url ); ?>">
									<input type="file" id="tpd-file-logo" accept="image/*" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline mt-2" onclick="document.getElementById('tpd-file-logo').click();">
										<i class="fa-solid fa-upload"></i> <?php esc_html_e( 'Upload Logo', 'tpd-tool' ); ?>
									</button>
								</div>

								<!-- Banner Selection -->
								<div>
									<label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;"><?php esc_html_e( 'Profile Banner Header', 'tpd-tool' ); ?></label>
									<div style="width:100%; height:90px; border-radius:10px; overflow:hidden; border:1px solid #cbd5e1; position:relative;">
										<img id="tpd-preview-banner" src="<?php echo esc_url( $banner_url ?: 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800&auto=format&fit=crop&q=80' ); ?>" style="width:100%; height:100%; object-fit:cover;">
									</div>
									<input type="hidden" name="banner_url" id="tpd_banner_url" value="<?php echo esc_attr( $banner_url ); ?>">
									<input type="file" id="tpd-file-banner" accept="image/*" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline mt-2" onclick="document.getElementById('tpd-file-banner').click();">
										<i class="fa-solid fa-panorama"></i> <?php esc_html_e( 'Upload Custom Banner', 'tpd-tool' ); ?>
									</button>
								</div>
							</div>
						</div>

						<!-- Core Details (100% Real User & CPT Values) -->
						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="first_name" required value="<?php echo esc_attr( $user ? $user->first_name : '' ); ?>" placeholder="<?php esc_attr_e( 'First Name', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="last_name" required value="<?php echo esc_attr( $user ? $user->last_name : '' ); ?>" placeholder="<?php esc_attr_e( 'Last Name', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Agency Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="agency_name" required value="<?php echo esc_attr( $agency_name ); ?>" placeholder="<?php esc_attr_e( 'Your Travel Agency Name', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
								<input type="tel" name="phone" required value="<?php echo esc_attr( $phone ); ?>" placeholder="<?php esc_attr_e( 'Phone Number', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
								<input type="email" name="email" required value="<?php echo esc_attr( $user ? $user->user_email : '' ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'City, State / Region, Country *', 'tpd-tool' ); ?></label>
								<input type="text" name="location" required value="<?php echo esc_attr( $location ); ?>" placeholder="<?php esc_attr_e( 'City, State, Country', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Agency Address', 'tpd-tool' ); ?></label>
								<input type="text" name="agency_address" value="<?php echo esc_attr( $agency_address ); ?>" placeholder="<?php esc_attr_e( 'Street, City, State, ZIP', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Agency Website Link', 'tpd-tool' ); ?></label>
								<input type="url" name="website" value="<?php echo esc_attr( $website ); ?>" placeholder="https://" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Consortia Affiliation', 'tpd-tool' ); ?></label>
								<input type="text" name="consortia" value="<?php echo esc_attr( $consortia ); ?>" placeholder="e.g. Virtuoso, Signature" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Host Agency / Franchise', 'tpd-tool' ); ?></label>
								<input type="text" name="host_agency" value="<?php echo esc_attr( $host_agency ); ?>" placeholder="e.g. Avoya, Nexion" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'CLIA / IATA Number', 'tpd-tool' ); ?></label>
								<input type="text" name="clia_number" value="<?php echo esc_attr( $clia_num ?: $iata_num ); ?>" placeholder="Enter Verification ID" class="tpd-input">
							</div>
						</div>

						<!-- Bio / About -->
						<div class="tpd-form-group mt-3">
							<label><?php esc_html_e( 'About Your Travel Practice & Client Philosophy', 'tpd-tool' ); ?></label>
							<textarea name="bio" rows="4" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Describe your travel advisory experience, specializations, and the value you deliver to travelers...', 'tpd-tool' ); ?>"><?php echo esc_textarea( $bio_content ); ?></textarea>
						</div>

						<!-- Travel Specialties -->
						<?php
						$saved_dests  = $advisor_post_id ? wp_get_object_terms( $advisor_post_id, 'travel_destination', array( 'fields' => 'names' ) ) : array();
						$saved_styles = $advisor_post_id ? wp_get_object_terms( $advisor_post_id, 'travel_style', array( 'fields' => 'names' ) ) : array();
						if ( is_wp_error( $saved_dests ) ) $saved_dests = array();
						if ( is_wp_error( $saved_styles ) ) $saved_styles = array();
						?>
						<div class="tpd-form-group mt-3">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
								<label class="font-bold"><?php esc_html_e( 'Travel Specialties: Destinations', 'tpd-tool' ); ?></label>
								<small class="text-muted"><?php printf( esc_html__( 'Current Plan Limit: Max %d selections', 'tpd-tool' ), (int) $plan_info['max_specialties'] ); ?></small>
							</div>
							<div class="tpd-checkbox-grid">
								<?php
								$dest_choices = array( 'Western Europe', 'Mediterranean', 'Danube River', 'Caribbean', 'Alaska', 'Africa & Safari', 'Japan & Asia', 'South Pacific', 'Antarctica & Polar', 'South America' );
								foreach ( $dest_choices as $dc ) :
								?>
									<label class="tpd-pill-checkbox">
										<input type="checkbox" name="destinations[]" value="<?php echo esc_attr( $dc ); ?>" <?php checked( in_array( $dc, $saved_dests, true ) ); ?>>
										<span><?php echo esc_html( $dc ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="tpd-form-group mt-3">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
								<label class="font-bold"><?php esc_html_e( 'Travel Specialties: Styles', 'tpd-tool' ); ?></label>
							</div>
							<div class="tpd-checkbox-grid">
								<?php
								$style_choices = array( 'River Cruises', 'Luxury Ocean', 'Custom Tailored', 'All-Inclusive', 'Romance & Honeymoon', 'Family & Multigen', 'Expedition & Adventure', 'Group Charters' );
								foreach ( $style_choices as $sc ) :
								?>
									<label class="tpd-pill-checkbox">
										<input type="checkbox" name="travel_styles[]" value="<?php echo esc_attr( $sc ); ?>" <?php checked( in_array( $sc, $saved_styles, true ) ); ?>>
										<span><?php echo esc_html( $sc ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<!-- Dynamic ACF Field Groups & Admin Custom Fields Bridge -->
						<?php
						if ( class_exists( 'TPD_Tool_CPT' ) ) {
							TPD_Tool_CPT::render_dynamic_fields_html( 'travel_advisor', $advisor_post_id, $current_user_id, 'dashboard' );
						}
						?>

						<div id="tpd-advisor-profile-status" class="tpd-reg-status" style="display:none; margin-top:16px;"></div>

						<div class="mt-4" style="text-align:right;">
							<button type="submit" class="tpd-btn tpd-btn-darkblue" id="tpd-save-advisor-profile-btn" style="padding:12px 28px;">
								<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Profile Changes', 'tpd-tool' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- VIEW 3: SEARCH SUPPLIERS DIRECTORY -->
			<div id="tpd-view-suppliers" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header mb-3">
						<h3><i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Supplier Partner Directory', 'tpd-tool' ); ?></h3>
						<span class="text-muted"><?php printf( esc_html__( '%d Active Partner Brands', 'tpd-tool' ), count( $supplier_posts ) ); ?></span>
					</div>
					<div class="tpd-featured-suppliers-grid">
						<?php foreach ( $supplier_posts as $sp ) :
							$sp_banner = get_post_meta( $sp->ID, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=500&auto=format&fit=crop&q=80';
							$sp_cat    = get_post_meta( $sp->ID, 'tpd_tagline', true ) ?: 'Verified Supplier Partner';
							$sp_owner  = (int) get_post_meta( $sp->ID, 'tpd_assigned_user', true ) ?: 1;
							$is_saved  = in_array( $sp->ID, $saved_supplier_ids, true );
						?>
							<div class="tpd-supp-showcase-card tpd-supplier-profile-card">
								<div class="tpd-ssc-thumb" style="background-image: url('<?php echo esc_url( $sp_banner ); ?>'); display:flex; justify-content:space-between; align-items:flex-start;">
									<span class="tpd-reps-online-badge"><i class="fa-solid fa-circle"></i> Online</span>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-toggle-favorite-btn <?php echo $is_saved ? 'active saved text-gold' : ''; ?>" data-supplier-id="<?php echo esc_attr( $sp->ID ); ?>" style="background:rgba(255,255,255,0.9); border:none; border-radius:6px; padding:4px 8px; cursor:pointer;">
										<i class="<?php echo $is_saved ? 'fa-solid' : 'fa-regular'; ?> fa-bookmark"></i>
									</button>
								</div>
								<div class="tpd-ssc-body">
									<span class="tpd-brand-title"><?php echo esc_html( $sp->post_title ); ?></span>
									<span class="tpd-ssc-category"><?php echo esc_html( $sp_cat ); ?></span>
									<div class="tpd-ssc-actions" style="margin-top:auto;">
										<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline" style="text-decoration:none; text-align:center;"><?php esc_html_e( 'Showcase', 'tpd-tool' ); ?></a>
										<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="<?php echo esc_attr( $sp_owner ); ?>" data-recipient-name="<?php echo esc_attr( $sp->post_title ); ?>">
											<i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Chat', 'tpd-tool' ); ?>
										</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- VIEW 4: SAVED SUPPLIERS -->
			<div id="tpd-view-saved" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header">
						<h3><i class="fa-regular fa-bookmark"></i> <?php esc_html_e( 'Your Saved Suppliers', 'tpd-tool' ); ?></h3>
					</div>
					<div class="tpd-featured-suppliers-grid mt-4">
						<?php echo do_shortcode( '[tpd_saved_suppliers]' ); ?>
					</div>
				</div>
			</div>

			<!-- VIEW 5: CLIENT INQUIRIES -->
			<div id="tpd-view-inquiries" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header">
						<h3><i class="fa-solid fa-inbox"></i> <?php esc_html_e( 'Client Trip Inquiries & Leads', 'tpd-tool' ); ?></h3>
					</div>
					<?php
					$inquiries = get_posts( array( 'post_type' => 'tpd_inquiry', 'posts_per_page' => 15 ) );
					if ( ! empty( $inquiries ) ) :
					?>
						<table class="tpd-inquiries-table mt-3">
							<thead>
								<tr>
									<th>Traveler</th>
									<th>Destination</th>
									<th>Budget</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $inquiries as $inq ) : ?>
									<tr>
										<td><strong><?php echo esc_html( get_post_meta( $inq->ID, '_tpd_traveler_name', true ) ?: $inq->post_title ); ?></strong></td>
										<td><?php echo esc_html( get_post_meta( $inq->ID, '_tpd_destination', true ) ?: 'Europe' ); ?></td>
										<td><span class="tpd-budget-badge"><?php echo esc_html( get_post_meta( $inq->ID, '_tpd_budget', true ) ?: '$10,000+' ); ?></span></td>
										<td><?php echo esc_html( get_the_date( 'M j, Y', $inq->ID ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="text-muted"><?php esc_html_e( 'No client inquiries received yet.', 'tpd-tool' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- VIEW 6: CHAT & MESSAGES -->
			<div id="tpd-view-conversations" class="tpd-tab-panel">
				<?php
				if ( tpd_can_user( 'advisor_chat', $current_user_id ) ) {
					include TPD_TOOL_DIR . 'templates/shared/chat-box.php';
				} else {
					echo TPD_Tool_Tiers::render_locked_card(
						__( 'Live Chat & Messaging is a Pro Feature', 'tpd-tool' ),
						__( 'Direct real-time chat with luxury cruise lines, tour operators, and resort BDMs is restricted to Paid / Pro Tier members.', 'tpd-tool' )
					);
				}
				?>
			</div>

			<!-- VIEW 7: MEETING REQUESTS -->
			<div id="tpd-view-meetings" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-regular fa-calendar"></i> <?php esc_html_e( '1-on-1 Supplier BDM Meeting Scheduler', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Schedule virtual discovery calls and group contract consultations with supplier sales directors.', 'tpd-tool' ); ?></p>
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; margin-top:18px;">
						<?php foreach ( array_slice( $supplier_posts, 0, 4 ) as $sp ) : ?>
							<div style="border:1px solid #e2e8f0; border-radius:12px; padding:18px; background:#f8fafc;">
								<h4 style="margin:0 0 6px;"><?php echo esc_html( $sp->post_title ); ?></h4>
								<p style="font-size:12.5px; color:#64748b; margin:0 0 14px;"><?php echo esc_html( get_post_meta( $sp->ID, 'tpd_tagline', true ) ?: 'Trade Partner Desk' ); ?></p>
								<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue" onclick="alert('Meeting request sent to ' + <?php echo wp_json_encode( $sp->post_title ); ?> + ' BDM team!');">
									<i class="fa-regular fa-calendar-check"></i> <?php esc_html_e( 'Request 15-Min Discovery Call', 'tpd-tool' ); ?>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- VIEW 8: RESOURCES & TOOLS -->
			<div id="tpd-view-resources" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-solid fa-book-open"></i> <?php esc_html_e( 'Advisor Sales Toolkits & Brochures', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Download partner commission guides, deck plans, and grab-and-go social media assets.', 'tpd-tool' ); ?></p>
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-top:18px;">
						<div style="border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
							<i class="fa-regular fa-file-pdf text-red fa-2x" style="margin-bottom:10px;"></i>
							<h4 style="margin:0 0 6px;">2026 Luxury River Cruise Charter Guide</h4>
							<p style="font-size:12px; color:#64748b;">Complete commission protection tiers and group policy reference.</p>
						</div>
						<div style="border:1px solid #e2e8f0; border-radius:12px; padding:18px;">
							<i class="fa-regular fa-file-pdf text-blue fa-2x" style="margin-bottom:10px;"></i>
							<h4 style="margin:0 0 6px;">Preferred Resort Amenities Directory</h4>
							<p style="font-size:12px; color:#64748b;">VIP client perks, room upgrade priority, and resort credit matrix.</p>
						</div>
					</div>
				</div>
			</div>

			<!-- VIEW 9 & 10: CRUISE COMPARISON & TARC CONNECT -->
			<div id="tpd-view-comparison" class="tpd-tab-panel">
				<div class="tpd-card-box text-center" style="padding:60px 30px;">
					<i class="fa-solid fa-ship" style="font-size:52px; color:#2563eb; margin-bottom:16px;"></i>
					<h2 style="font-size:24px; font-weight:800; color:#0b1526;"><?php esc_html_e( 'Cruise Comparison Engine', 'tpd-tool' ); ?></h2>
					<span style="background:#fef3c7; color:#b45309; font-size:12px; font-weight:800; padding:4px 14px; border-radius:999px; text-transform:uppercase;">Coming Soon</span>
					<p style="color:#64748b; max-width:540px; margin:16px auto 0; line-height:1.5;">
						Compare river and ocean luxury cruise lines side-by-side, evaluating ship capacities, inclusions, and current promotions.
					</p>
				</div>
			</div>

			<div id="tpd-view-connect" class="tpd-tab-panel">
				<div class="tpd-card-box text-center" style="padding:60px 30px;">
					<i class="fa-solid fa-users-rays" style="font-size:52px; color:#8b5cf6; margin-bottom:16px;"></i>
					<h2 style="font-size:24px; font-weight:800; color:#0b1526;"><?php esc_html_e( 'The TARC Connect — Discovery Call Platform', 'tpd-tool' ); ?></h2>
					<span style="background:#fef3c7; color:#b45309; font-size:12px; font-weight:800; padding:4px 14px; border-radius:999px; text-transform:uppercase;">Coming Soon</span>
					<p style="color:#64748b; max-width:540px; margin:16px auto 0; line-height:1.5;">
						Book 1-on-1 discovery consultations with verified supplier BDMs and sales directors.
					</p>
				</div>
			</div>

			<!-- VIEW 11: EVENTS & WEBINARS -->
			<div id="tpd-view-events" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-regular fa-calendar-check"></i> <?php esc_html_e( 'Upcoming Events & Webinars Schedule', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Live events synced from WP Admin Events & Webinars.', 'tpd-tool' ); ?></p>
					<?php echo do_shortcode( '[tpd_events_list]' ); ?>
				</div>
			</div>

			<!-- VIEW 12: TARC NEWS -->
			<div id="tpd-view-news" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-solid fa-bullhorn"></i> <?php esc_html_e( 'TARC News & Supplier Articles', 'tpd-tool' ); ?></h3>
					<div class="tpd-news-cards-grid mt-3">
						<?php foreach ( $news_posts as $np ) : ?>
							<article class="tpd-news-mini-card">
								<div class="tpd-nmc-thumb" style="background-image: url('https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=400&auto=format&fit=crop&q=80');">
									<span class="tpd-news-date"><?php echo esc_html( strtoupper( get_the_date( 'M j, Y', $np->ID ) ) ); ?></span>
								</div>
								<div class="tpd-nmc-body">
									<h4><?php echo esc_html( $np->post_title ); ?></h4>
									<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $np->post_content ), 24 ) ); ?></p>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- VIEW 13: SETTINGS, PLAN UPGRADE & SELF-SERVICE ACCOUNT DELETION -->
			<div id="tpd-view-settings" class="tpd-tab-panel">
				<!-- Membership Plan Upgrade Card -->
				<div class="tpd-card-box mb-4">
					<div class="tpd-ss-header mb-3">
						<div>
							<h3><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'Membership Plan & Billing', 'tpd-tool' ); ?></h3>
							<p class="text-muted" style="margin:4px 0 0;">
								<?php printf( esc_html__( 'Your current active plan: %s', 'tpd-tool' ), '<strong>' . esc_html( $plan_info['name'] ) . '</strong>' ); ?>
							</p>
						</div>
					</div>

					<form id="tpd-user-upgrade-plan-form">
						<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap:16px; margin-bottom:18px;">
							<?php foreach ( $all_plans as $p_slug => $p_info ) :
								$m_price = (float) $p_info['monthly_price'];
								$y_price = (float) $p_info['yearly_price'];
							?>
								<label class="tpd-tier-option" style="border:2px solid <?php echo ( $user_tier === $p_slug ) ? '#2563eb' : '#e2e8f0'; ?>; border-radius:12px; padding:18px; cursor:pointer; background:<?php echo ( $user_tier === $p_slug ) ? '#f8fbff' : '#fff'; ?>;">
									<input type="radio" name="plan_slug" value="<?php echo esc_attr( $p_slug ); ?>" <?php checked( $user_tier, $p_slug ); ?>>
									<div>
										<span class="tpd-to-tag"><?php echo esc_html( $p_info['badge'] ); ?></span>
										<h4 style="margin:4px 0;"><?php echo esc_html( $p_info['name'] ); ?></h4>
										<strong style="font-size:18px; display:block; margin-bottom:8px;">
											<?php echo $m_price <= 0 ? 'Free' : '$' . number_format( $m_price, 2 ) . '/mo ($' . number_format( $y_price, 0 ) . '/yr)'; ?>
										</strong>
										<ul style="list-style:none; padding:0; margin:0; font-size:12px; color:#475569;">
											<?php foreach ( (array) $p_info['features_list'] as $fl ) : ?>
												<li style="margin-bottom:4px;"><i class="fa-solid fa-check text-green"></i> <?php echo esc_html( $fl ); ?></li>
											<?php endforeach; ?>
										</ul>
									</div>
								</label>
							<?php endforeach; ?>
						</div>

						<div style="display:flex; gap:14px; align-items:center; flex-wrap:wrap;">
							<select name="billing_type" class="tpd-select" style="max-width:200px;">
								<option value="Month"><?php esc_html_e( 'Monthly Billing', 'tpd-tool' ); ?></option>
								<option value="Year"><?php esc_html_e( 'Annual Billing', 'tpd-tool' ); ?></option>
							</select>
							<select name="gateway" class="tpd-select" style="max-width:220px;">
								<option value="Stripe"><?php esc_html_e( 'Credit Card (Stripe)', 'tpd-tool' ); ?></option>
								<option value="PayPal"><?php esc_html_e( 'PayPal Checkout', 'tpd-tool' ); ?></option>
							</select>
							<button type="submit" class="tpd-btn tpd-btn-darkblue" id="tpd-btn-upgrade-plan">
								<i class="fa-solid fa-bolt"></i> <?php esc_html_e( 'Update Membership Plan', 'tpd-tool' ); ?>
							</button>
						</div>
					</form>
				</div>

				<!-- Account Password Security -->
				<div class="tpd-card-box mb-4">
					<h3><i class="fa-solid fa-lock"></i> <?php esc_html_e( 'Update Password & Security', 'tpd-tool' ); ?></h3>
					<form id="tpd-user-password-form" class="mt-3">
						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'New Password *', 'tpd-tool' ); ?></label>
								<input type="password" name="new_password" required placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Confirm New Password *', 'tpd-tool' ); ?></label>
								<input type="password" name="confirm_password" required placeholder="<?php esc_attr_e( 'Confirm new password', 'tpd-tool' ); ?>" class="tpd-input">
							</div>
						</div>
						<button type="submit" class="tpd-btn tpd-btn-darkblue"><?php esc_html_e( 'Update Password', 'tpd-tool' ); ?></button>
					</form>
				</div>

				<!-- Danger Zone: Delete Account -->
				<div class="tpd-card-box" style="border:1px solid #fecaca; background:#fef2f2;">
					<h3 style="color:#991b1b; margin:0 0 8px;"><i class="fa-solid fa-triangle-exclamation"></i> <?php esc_html_e( 'Delete Account', 'tpd-tool' ); ?></h3>
					<p style="color:#7f1d1d; font-size:13px; margin:0 0 14px;">
						<?php esc_html_e( 'Permanently delete your Travel Advisor user account and remove your profile listing from the public directory.', 'tpd-tool' ); ?>
					</p>
					<button type="button" class="tpd-btn tpd-btn-sm" id="tpd-btn-delete-own-account" style="background:#dc2626; color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer;">
						<i class="fa-solid fa-trash-can"></i> <?php esc_html_e( 'Permanently Delete My Account', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>

			<!-- VIEW 14: HELP & SUPPORT (Dynamic FAQs) -->
			<div id="tpd-view-help" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-regular fa-circle-question"></i> <?php esc_html_e( 'Frequently Asked Questions & Support', 'tpd-tool' ); ?></h3>
					<div class="mt-3" style="display:flex; flex-direction:column; gap:14px;">
						<?php foreach ( $faqs as $faq ) : ?>
							<div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#f8fafc;">
								<h4 style="margin:0 0 6px; font-size:14.5px; color:#0b1526;"><?php echo esc_html( $faq['q'] ); ?></h4>
								<p style="margin:0; font-size:13px; color:#475569;"><?php echo esc_html( $faq['a'] ); ?></p>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- VIEW 15: AD SPACE -->
			<div id="tpd-view-ads" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-solid fa-rectangle-ad"></i> <?php esc_html_e( 'Featured Partner Ad Placements', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Sponsored campaigns and partner promotions managed by the Super Admin.', 'tpd-tool' ); ?></p>
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px; margin-top:20px;">
						<div class="tpd-card-box" style="border:1px solid #e2e8f0; background:#f8fafc; padding:20px;">
							<span class="tpd-badge-tag" style="background:#fef3c7; color:#b45309; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700;"><?php echo esc_html( $ads_cfg['ad_1_tag'] ); ?></span>
							<h4 style="margin:10px 0 6px;"><?php echo esc_html( $ads_cfg['ad_1_title'] ); ?></h4>
							<p style="font-size:13px; color:#64748b;"><?php echo esc_html( $ads_cfg['ad_1_subtitle'] ); ?></p>
							<a href="#tpd-view-suppliers" class="tpd-tab-link tpd-btn tpd-btn-xs tpd-btn-darkblue" data-view="suppliers" style="text-decoration:none; display:inline-block;"><?php echo esc_html( $ads_cfg['ad_1_link_text'] ); ?></a>
						</div>

						<div class="tpd-card-box" style="border:1px solid #e2e8f0; background:#f8fafc; padding:20px;">
							<span class="tpd-badge-tag" style="background:#e0f2fe; color:#0369a1; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700;"><?php echo esc_html( $ads_cfg['ad_2_tag'] ); ?></span>
							<h4 style="margin:10px 0 6px;"><?php echo esc_html( $ads_cfg['ad_2_title'] ); ?></h4>
							<p style="font-size:13px; color:#64748b;"><?php echo esc_html( $ads_cfg['ad_2_subtitle'] ); ?></p>
							<a href="#tpd-view-suppliers" class="tpd-tab-link tpd-btn tpd-btn-xs tpd-btn-darkblue" data-view="suppliers" style="text-decoration:none; display:inline-block;"><?php echo esc_html( $ads_cfg['ad_2_link_text'] ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>

<div id="tpd-chat-drawer-container" style="display:none;"></div>

<?php wp_footer(); ?>
</body>
</html>
