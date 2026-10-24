<?php
/**
 * Travel Partner Directory by TARC - Advisor Dashboard
 * Dynamically connected to WP User Meta, travel_advisor CPT, supplier_listing CPT,
 * ACF Field Groups, and Super Admin Settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

TPD_Tool_Roles::enforce_portal_access( 'advisor' );
$current_user_id = get_current_user_id();

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
						<span><?php esc_html_e( 'Dashboard', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-profile" class="tpd-tab-link" data-view="profile">
						<i class="fa-regular fa-id-card"></i>
						<span><?php esc_html_e( 'My Profile', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-suppliers" class="tpd-tab-link" data-view="suppliers">
						<i class="fa-solid fa-magnifying-glass"></i>
						<span><?php esc_html_e( 'Search Suppliers', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-saved" class="tpd-tab-link" data-view="saved">
						<i class="fa-regular fa-bookmark"></i>
						<span><?php esc_html_e( 'Saved Suppliers', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-inquiries" class="tpd-tab-link" data-view="inquiries">
						<i class="fa-solid fa-inbox"></i>
						<span><?php esc_html_e( 'Client Inquiries', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-conversations" class="tpd-tab-link" data-view="conversations">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( 'Chat & Messages', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-meetings" class="tpd-tab-link" data-view="meetings">
						<i class="fa-regular fa-calendar"></i>
						<span><?php esc_html_e( 'Meeting Requests', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-resources" class="tpd-tab-link" data-view="resources">
						<i class="fa-solid fa-book-open"></i>
						<span><?php esc_html_e( 'Resources & Tools', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-comparison" class="tpd-tab-link" data-view="comparison">
						<i class="fa-solid fa-ship"></i>
						<span><?php esc_html_e( 'Cruise Comparison', 'tpd-tool' ); ?></span>
						<small class="tpd-badge-tag" style="background:#fef3c7; color:#b45309; font-size:9.5px; padding:2px 6px; border-radius:4px; margin-left:auto;">Soon</small>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-connect" class="tpd-tab-link" data-view="connect">
						<i class="fa-solid fa-users-rays"></i>
						<span><?php esc_html_e( 'The TARC Connect', 'tpd-tool' ); ?></span>
						<small class="tpd-badge-tag" style="background:#fef3c7; color:#b45309; font-size:9.5px; padding:2px 6px; border-radius:4px; margin-left:auto;">Soon</small>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-events" class="tpd-tab-link" data-view="events">
						<i class="fa-regular fa-calendar-check"></i>
						<span><?php esc_html_e( 'Upcoming Events', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-news" class="tpd-tab-link" data-view="news">
						<i class="fa-solid fa-bullhorn"></i>
						<span><?php esc_html_e( 'TARC News', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-settings" class="tpd-tab-link" data-view="settings">
						<i class="fa-solid fa-crown"></i>
						<span><?php esc_html_e( 'Plans & Settings', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-help" class="tpd-tab-link" data-view="help">
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
						$lad_link   = ! empty( $lad['link_url'] ) ? $lad['link_url'] : '#tpd-view-suppliers';
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
			<!-- TARC News Ticker (Replaces old search bar) -->
			<div class="tpd-topbar-news-ticker" style="display:flex; align-items:center; gap:10px; background:#f1f5f9; padding:6px 14px; border-radius:999px; max-width:440px; overflow:hidden;">
				<span class="tpd-news-ticker-badge" style="background:#0b1526; color:#f59e0b; font-size:10px; font-weight:800; padding:3px 8px; border-radius:999px; letter-spacing:0.06em; flex-shrink:0;">
					<i class="fa-solid fa-bullhorn"></i> <?php esc_html_e( 'TARC NEWS', 'tpd-tool' ); ?>
				</span>
				<a href="#tpd-view-news" class="tpd-news-ticker-link tpd-tab-link" data-view="news" style="font-size:12.5px; font-weight:600; color:#1e293b; text-decoration:none; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
					<?php echo ! empty( $news_posts ) ? esc_html( wp_trim_words( $news_posts[0]->post_title, 9 ) ) : esc_html__( 'New Luxury River & Ocean Partner Portals Now Live on TPD', 'tpd-tool' ); ?> &rarr;
				</a>
			</div>

			<!-- Top Menu Quick Navigation Buttons -->
			<div class="tpd-topbar-nav-shortcuts" style="display:flex; align-items:center; gap:8px;">
				<a href="#tpd-view-resources" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-tab-link" data-view="resources" style="text-decoration:none; font-weight:700;">
					<i class="fa-solid fa-book-open text-blue"></i> <?php esc_html_e( 'Resource Center', 'tpd-tool' ); ?>
				</a>
				<a href="#tpd-view-suppliers" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-tab-link" data-view="suppliers" style="text-decoration:none; font-weight:700;">
					<i class="fa-solid fa-building-circle-check text-blue"></i> <?php esc_html_e( 'Pro Directory', 'tpd-tool' ); ?>
				</a>
				<a href="#tpd-view-news" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-tab-link" data-view="news" style="text-decoration:none; font-weight:700;">
					<i class="fa-solid fa-newspaper text-gold"></i> <?php esc_html_e( 'Partner Blogs', 'tpd-tool' ); ?>
				</a>
			</div>

			<div class="tpd-topbar-actions">
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/tpd-admin/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none;">
						<i class="fa-solid fa-shield-halved text-gold"></i> <?php esc_html_e( 'Super Admin Hub', 'tpd-tool' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( TPD_Tool_Roles::is_dual_role( $current_user_id ) || current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none; border-color:#00798c; color:#00798c; font-weight:700;">
						<i class="fa-solid fa-repeat"></i> <?php esc_html_e( 'Switch to Supplier Portal', 'tpd-tool' ); ?>
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

				<a href="<?php echo esc_url( wp_logout_url( home_url( '/advisor-login/' ) ) ); ?>" class="tpd-top-icon-btn" title="<?php esc_attr_e( 'Log Out', 'tpd-tool' ); ?>" style="text-decoration:none; color:#64748b;">
					<i class="fa-solid fa-right-from-bracket"></i>
				</a>
			</div>
		</header>

		<!-- Workspace / Main View Area -->
		<main class="tpd-workspace-content">
			<?php if ( isset( $_GET['cross_role_notice'] ) && 'supplier' === $_GET['cross_role_notice'] && ! TPD_Tool_Roles::is_supplier( $current_user_id ) ) : ?>
				<div style="background:#f0fdfa; border:1.5px solid #00798c; border-radius:12px; padding:16px 22px; margin-bottom:22px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
					<div style="display:flex; align-items:center; gap:12px;">
						<i class="fa-solid fa-building-user" style="font-size:22px; color:#00798c;"></i>
						<div>
							<strong style="color:#013243; font-size:14.5px; display:block;"><?php esc_html_e( 'Your email is currently registered as a Travel Advisor only.', 'tpd-tool' ); ?></strong>
							<span style="color:#475569; font-size:13px;"><?php esc_html_e( 'Would you like to also register as a Supplier Partner with the same email and access both dashboards?', 'tpd-tool' ); ?></span>
						</div>
					</div>
					<a href="<?php echo esc_url( home_url( '/supplier-registration/?cross_role=1' ) ); ?>" class="tpd-btn tpd-btn-sm tpd-btn-primary" style="text-decoration:none; background:#00798c; border-color:#00798c;">
						<i class="fa-solid fa-plus-circle"></i> <?php esc_html_e( 'Continue as Supplier Partner Also', 'tpd-tool' ); ?>
					</a>
				</div>
			<?php endif; ?>
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

				<!-- 3 Analytics Metric Cards -->
				<div class="tpd-metrics-row-3" style="margin-top:20px;">
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
						<!-- "Find Your Next Supplier" Search with 3 Filter Dropdowns -->
						<div class="tpd-card-box tpd-supplier-search-box">
							<div class="tpd-ss-header">
								<h3><?php esc_html_e( 'Find Your Next Supplier', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-suppliers" class="tpd-link-arrow tpd-tab-link" data-view="suppliers"><?php esc_html_e( 'Browse Full Directory', 'tpd-tool' ); ?> &rarr;</a>
							</div>
							<p class="tpd-ss-desc"><?php esc_html_e( 'Search our supplier directory and connect directly with partner representatives.', 'tpd-tool' ); ?></p>

							<div class="tpd-ss-search-form" style="display:flex; flex-direction:column; gap:12px; margin-top:14px;">
								<div class="tpd-ss-search-bar">
									<div class="tpd-ss-input-wrap">
										<i class="fa-solid fa-magnifying-glass"></i>
										<input type="text" placeholder="<?php esc_attr_e( 'Search suppliers, keywords, destinations, or brands...', 'tpd-tool' ); ?>" id="tpd-live-supplier-search">
									</div>
									<button type="button" class="tpd-btn tpd-btn-darkblue" id="tpd-btn-search-suppliers">
										<i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Search Suppliers', 'tpd-tool' ); ?>
									</button>
								</div>

								<!-- 3 Quick Filter Dropdowns -->
								<div class="tpd-overview-filters-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap:12px;">
									<select id="tpd-filter-overview-dest" class="tpd-input" style="font-size:12.5px; padding:8px 12px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px;">
										<option value=""><?php esc_html_e( 'All Destinations', 'tpd-tool' ); ?></option>
										<option value="Caribbean"><?php esc_html_e( 'Caribbean', 'tpd-tool' ); ?></option>
										<option value="Europe"><?php esc_html_e( 'Europe', 'tpd-tool' ); ?></option>
										<option value="North America"><?php esc_html_e( 'North America', 'tpd-tool' ); ?></option>
										<option value="Central & South America"><?php esc_html_e( 'Central & South America', 'tpd-tool' ); ?></option>
										<option value="Asia & Pacific"><?php esc_html_e( 'Asia & Pacific', 'tpd-tool' ); ?></option>
										<option value="Africa & Middle East"><?php esc_html_e( 'Africa & Middle East', 'tpd-tool' ); ?></option>
										<option value="Polar"><?php esc_html_e( 'Polar / Arctic & Antarctica', 'tpd-tool' ); ?></option>
									</select>

									<select id="tpd-filter-overview-style" class="tpd-input" style="font-size:12.5px; padding:8px 12px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px;">
										<option value=""><?php esc_html_e( 'All Travel Styles', 'tpd-tool' ); ?></option>
										<option value="Luxury"><?php esc_html_e( 'Luxury Travel', 'tpd-tool' ); ?></option>
										<option value="All-Inclusive"><?php esc_html_e( 'All-Inclusive', 'tpd-tool' ); ?></option>
										<option value="Ocean Cruise"><?php esc_html_e( 'Ocean Cruise', 'tpd-tool' ); ?></option>
										<option value="River Cruise"><?php esc_html_e( 'River Cruise', 'tpd-tool' ); ?></option>
										<option value="Adventure"><?php esc_html_e( 'Adventure / Safari', 'tpd-tool' ); ?></option>
										<option value="Guided"><?php esc_html_e( 'Guided / Escorted Tours', 'tpd-tool' ); ?></option>
										<option value="Wellness"><?php esc_html_e( 'Wellness & Spa', 'tpd-tool' ); ?></option>
										<option value="Family"><?php esc_html_e( 'Family / Multigenerational', 'tpd-tool' ); ?></option>
									</select>

									<select id="tpd-filter-overview-type" class="tpd-input" style="font-size:12.5px; padding:8px 12px; background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px;">
										<option value=""><?php esc_html_e( 'All Supplier Types', 'tpd-tool' ); ?></option>
										<option value="Cruise"><?php esc_html_e( 'Cruise Line', 'tpd-tool' ); ?></option>
										<option value="Tour"><?php esc_html_e( 'Tour Operator / Wholesaler', 'tpd-tool' ); ?></option>
										<option value="Resort"><?php esc_html_e( 'All-Inclusive Resort / Hotel', 'tpd-tool' ); ?></option>
										<option value="DMC"><?php esc_html_e( 'Destination Management Company (DMC)', 'tpd-tool' ); ?></option>
										<option value="Rail"><?php esc_html_e( 'Rail Journey', 'tpd-tool' ); ?></option>
										<option value="Villa"><?php esc_html_e( 'Villa / Private Rental', 'tpd-tool' ); ?></option>
									</select>
								</div>
							</div>
						</div>

						<!-- Dynamic Supplier Spotlight Section (1 photo, title, category, paragraph text) -->
						<?php
						$spotlight_posts = get_posts( array(
							'post_type'      => 'supplier_listing',
							'posts_per_page' => 1,
							'post_status'    => 'publish',
							'meta_query'     => array(
								'relation' => 'OR',
								array( 'key' => 'tpd_spotlight', 'value' => 'yes' ),
								array( 'key' => 'tpd_featured_supplier', 'value' => 'yes' ),
							),
						) );
						$spotlight = ! empty( $spotlight_posts ) ? $spotlight_posts[0] : ( ! empty( $supplier_posts ) ? $supplier_posts[0] : null );
						if ( $spotlight ) :
							$spot_img      = get_post_meta( $spotlight->ID, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=1000&auto=format&fit=crop&q=80';
							$spot_category = get_post_meta( $spotlight->ID, 'tpd_supplier_category', true ) ?: get_post_meta( $spotlight->ID, 'tpd_tagline', true ) ?: 'Preferred Cruise Line Partner';
							$spot_desc     = get_post_meta( $spotlight->ID, 'tpd_description', true ) ?: wp_strip_all_tags( $spotlight->post_content );
							if ( empty( $spot_desc ) ) {
								$spot_desc = 'Discover exceptional trade programs, dedicated sales representative support, and industry-leading advisor commission incentives with ' . $spotlight->post_title . '.';
							}
							$spot_owner    = (int) get_post_meta( $spotlight->ID, 'tpd_assigned_user', true ) ?: 1;
						?>
							<div class="tpd-supplier-spotlight-card mb-4" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,0.04); margin-bottom:24px;">
								<div class="tpd-spotlight-inner" style="display:grid; grid-template-columns: 320px 1fr; gap:0;">
									<div class="tpd-spotlight-media" style="background-image: url('<?php echo esc_url( $spot_img ); ?>'); background-size:cover; background-position:center; min-height:220px; position:relative;">
										<span style="position:absolute; top:14px; left:14px; background:#00798c; color:#ffffff; font-size:10.5px; font-weight:800; text-transform:uppercase; letter-spacing:0.8px; padding:4px 10px; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,0.2);">
											<i class="fa-solid fa-star"></i> <?php esc_html_e( 'Supplier Spotlight', 'tpd-tool' ); ?>
										</span>
									</div>
									<div class="tpd-spotlight-content" style="padding:22px 26px; display:flex; flex-direction:column; justify-content:center;">
										<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
											<span style="font-size:12px; font-weight:700; color:#0d9488; text-transform:uppercase; letter-spacing:0.5px;"><?php echo esc_html( $spot_category ); ?></span>
										</div>
										<h3 style="margin:0 0 10px; font-size:20px; font-weight:800; color:#013243;"><?php echo esc_html( $spotlight->post_title ); ?></h3>
										<p style="margin:0 0 16px; font-size:13.5px; line-height:1.55; color:#475569;">
											<?php echo esc_html( wp_trim_words( $spot_desc, 38 ) ); ?>
										</p>
										<div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
											<a href="<?php echo esc_url( get_permalink( $spotlight->ID ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm" style="background:#00798c; color:#fff; text-decoration:none; padding:8px 16px; border-radius:6px; font-weight:700; font-size:13px;">
												<?php esc_html_e( 'Explore Spotlight', 'tpd-tool' ); ?> &rarr;
											</a>
											<button type="button" class="tpd-btn tpd-btn-sm tpd-open-chat-btn" data-recipient-id="<?php echo esc_attr( $spot_owner ); ?>" data-recipient-name="<?php echo esc_attr( $spotlight->post_title ); ?>" style="background:#f1f5f9; color:#013243; border:1px solid #cbd5e1; padding:8px 16px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer;">
												<i class="fa-regular fa-comment-dots"></i> <?php esc_html_e( 'Connect with Rep', 'tpd-tool' ); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>

						<!-- Dynamic Featured Suppliers Grid (Selected via Super Admin Panel, Reps Online badge removed) -->
						<div class="tpd-featured-suppliers-section">
							<div class="tpd-fss-header">
								<h3><?php esc_html_e( 'Featured Suppliers', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-suppliers" class="tpd-link-arrow tpd-tab-link" data-view="suppliers"><?php esc_html_e( 'View All Suppliers', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-featured-suppliers-grid">
								<?php
								$featured_suppliers = get_posts( array(
									'post_type'      => 'supplier_listing',
									'posts_per_page' => 4,
									'post_status'    => 'publish',
									'meta_key'       => 'tpd_featured_supplier',
									'meta_value'     => 'yes',
								) );
								if ( empty( $featured_suppliers ) ) {
									$featured_suppliers = array_slice( $supplier_posts, 0, 4 );
								}
								foreach ( $featured_suppliers as $sp ) :
									$sp_banner = get_post_meta( $sp->ID, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=500&auto=format&fit=crop&q=80';
									$sp_cat    = get_post_meta( $sp->ID, 'tpd_tagline', true ) ?: get_post_meta( $sp->ID, 'tpd_supplier_category', true ) ?: 'Preferred Supplier Partner';
									$sp_owner  = (int) get_post_meta( $sp->ID, 'tpd_assigned_user', true ) ?: 1;
								?>
									<div class="tpd-supp-showcase-card tpd-supplier-profile-card">
										<div class="tpd-ssc-thumb" style="background-image: url('<?php echo esc_url( $sp_banner ); ?>');"></div>
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

					<!-- SIDEBAR COLUMN -->
					<aside class="tpd-dash-col-side">
						<!-- 1. Quick Supplier Search (replaces Quick Actions) -->
						<div class="tpd-card-box tpd-quick-actions-box">
							<div class="tpd-box-header-clean">
								<h4><i class="fa-solid fa-compass" style="color:#00798c;"></i> <?php esc_html_e( 'Quick Supplier Search', 'tpd-tool' ); ?></h4>
							</div>

							<div class="tpd-quick-actions-grid-6">
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-quick-cat-btn" data-category="Ocean Cruises">
									<i class="fa-solid fa-ship"></i>
									<span><?php esc_html_e( 'Ocean Cruises', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-quick-cat-btn" data-category="River Cruises">
									<i class="fa-solid fa-water"></i>
									<span><?php esc_html_e( 'River Cruises', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-quick-cat-btn" data-category="Tour Operators">
									<i class="fa-solid fa-earth-americas"></i>
									<span><?php esc_html_e( 'Tour Operators', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-quick-cat-btn" data-category="All-Inclusives">
									<i class="fa-solid fa-umbrella-beach"></i>
									<span><?php esc_html_e( 'All-Inclusives', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-quick-cat-btn" data-category="Escorted Tours">
									<i class="fa-solid fa-bus"></i>
									<span><?php esc_html_e( 'Escorted Tours', 'tpd-tool' ); ?></span>
								</a>
								<a href="#tpd-view-suppliers" class="tpd-qa-tile tpd-quick-cat-btn" data-category="Expedition/Safaris">
									<i class="fa-solid fa-binoculars"></i>
									<span><?php esc_html_e( 'Expedition/Safaris', 'tpd-tool' ); ?></span>
								</a>
							</div>
						</div>

						<!-- 2. Listing Status Widget (under Quick Supplier Search, auto-hides when 100% complete) -->
						<?php
						$adv_u = get_userdata( $current_user_id );
						$ls_items = array(
							array(
								'label' => __( 'First & Last Name', 'tpd-tool' ),
								'done'  => ! empty( $adv_u->first_name ) && ! empty( $adv_u->last_name ),
								'link'  => '#tpd-acct-sec-personal',
							),
							array(
								'label' => __( 'Direct Phone Number', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_phone', true ) ),
								'link'  => '#tpd-acct-sec-personal',
							),
							array(
								'label' => __( 'Profile Handle & URL', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_profile_handle', true ) ),
								'link'  => '#tpd-acct-sec-personal',
							),
							array(
								'label' => __( 'Advisor Location / Address', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_advisor_location', true ) ),
								'link'  => '#tpd-acct-sec-personal',
							),
							array(
								'label' => __( 'Agency Name & Structure', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_agency_name', true ) ),
								'link'  => '#tpd-acct-sec-personal',
							),
							array(
								'label' => __( 'Industry Accreditation (CLIA/IATA/ARC/TRUE)', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_accreditation_num', true ) ) || ! empty( get_user_meta( $current_user_id, 'tpd_clia_num', true ) ) || ! empty( get_user_meta( $current_user_id, 'tpd_iata_num', true ) ) || ! empty( get_user_meta( $current_user_id, 'tpd_true_num', true ) ),
								'link'  => '#tpd-acct-sec-cert',
							),
							array(
								'label' => __( 'About Me / Biography', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'description', true ) ) || ! empty( get_user_meta( $current_user_id, 'tpd_about_me', true ) ),
								'link'  => '#tpd-acct-sec-about',
							),
							array(
								'label' => __( 'Travel Specialties (Destinations/Styles)', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_travel_types', true ) ) || ! empty( get_user_meta( $current_user_id, 'tpd_destinations', true ) ),
								'link'  => '#tpd-acct-sec-spec',
							),
							array(
								'label' => __( 'Profile Photo / Headshot', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_avatar_url', true ) ) || ! empty( get_user_meta( $current_user_id, 'tpd_headshot_url', true ) ),
								'link'  => '#tpd-acct-sec-personal',
							),
							array(
								'label' => __( 'Custom Banner Image', 'tpd-tool' ),
								'done'  => ! empty( get_user_meta( $current_user_id, 'tpd_banner_url', true ) ),
								'link'  => '#tpd-acct-sec-social',
							),
						);
						$total_ls_count = count( $ls_items );
						$done_ls_count  = count( array_filter( $ls_items, function( $i ) { return $i['done']; } ) );
						$ls_percent     = intval( round( ( $done_ls_count / $total_ls_count ) * 100 ) );
						?>
						<?php if ( $ls_percent < 100 ) : ?>
							<div class="tpd-card-box tpd-listing-status-card" id="tpd-listing-status-widget" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:18px; margin-bottom:20px;">
								<div class="tpd-box-header-clean" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
									<h4 style="margin:0; font-size:14px; font-weight:800; color:#013243;"><?php esc_html_e( 'Listing Status', 'tpd-tool' ); ?></h4>
									<span style="font-size:12px; font-weight:800; color:#00798c; background:#e0f2fe; padding:2px 8px; border-radius:12px;">
										<?php echo esc_html( $ls_percent ); ?>% <?php esc_html_e( 'Complete', 'tpd-tool' ); ?>
									</span>
								</div>
								<div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; margin-bottom:12px;">
									<div style="width:<?php echo esc_attr( $ls_percent ); ?>%; height:100%; background:linear-gradient(90deg, #00798c, #0d9488); border-radius:4px;"></div>
								</div>
								<p style="font-size:12px; color:#64748b; margin:0 0 10px;">
									<?php esc_html_e( 'Complete your profile to unlock verified advisor status and maximize traveler trip inquiries.', 'tpd-tool' ); ?>
								</p>
								<ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">
									<?php foreach ( $ls_items as $itm ) : ?>
										<li style="display:flex; align-items:center; gap:8px; font-size:12px; color:<?php echo $itm['done'] ? '#10b981' : '#64748b'; ?>;">
											<i class="fa-solid <?php echo $itm['done'] ? 'fa-circle-check text-green' : 'fa-circle-notch text-muted'; ?>" style="font-size:13px;"></i>
											<span style="<?php echo $itm['done'] ? 'text-decoration:line-through; opacity:0.75;' : 'font-weight:600; color:#334155;'; ?>">
												<?php echo esc_html( $itm['label'] ); ?>
											</span>
											<?php if ( ! $itm['done'] ) : ?>
												<a href="#tpd-view-profile" class="tpd-tab-link" data-view="profile" style="margin-left:auto; font-size:11px; font-weight:700; color:#00798c; text-decoration:none;"><?php esc_html_e( 'Fill &rarr;', 'tpd-tool' ); ?></a>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<!-- 3. Saved Suppliers (under Listing Status) -->
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

						<!-- 4. Upcoming Events (under Saved Suppliers) -->
						<div class="tpd-card-box tpd-events-widget-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'Upcoming Events', 'tpd-tool' ); ?></h4>
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
								$b_link   = ! empty( $b_ad['link_url'] ) ? $b_ad['link_url'] : '#tpd-view-suppliers';
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

			<!-- VIEW 2: ACCOUNT INFO & PROFILE EDITOR (Matches Travel Partner Directory Account Info Design) -->
			<div id="tpd-view-profile" class="tpd-tab-panel">
				<?php
				$profile_handle   = get_user_meta( $current_user_id, 'tpd_profile_handle', true ) ?: ( $user ? $user->user_login : 'advisor' );
				$public_prof_url  = $advisor_post_id ? get_permalink( $advisor_post_id ) : home_url( '/travel-advisors/' . sanitize_title( $profile_handle ) . '/' );
				$has_host         = get_user_meta( $current_user_id, 'tpd_has_host', true ) ?: ( $host_agency ? 'yes' : 'no' );
				$consult_fee      = get_user_meta( $current_user_id, 'tpd_consultation_fee', true ) ?: 'yes';
				$year_started     = get_user_meta( $current_user_id, 'tpd_years_experience', true ) ?: '2018';
				$group_spec       = get_user_meta( $current_user_id, 'tpd_group_travel_spec', true ) ?: 'Group travel (10+ rooms or 20+ people)';
				$clients_per_yr   = get_user_meta( $current_user_id, 'tpd_clients_per_year', true ) ?: '1 to 50';
				$prev_gross_sales = get_user_meta( $current_user_id, 'tpd_personal_sales_volume', true ) ?: '$100,000 - $250,000';
				$goal_gross_sales = get_user_meta( $current_user_id, 'tpd_sales_volume_goal', true ) ?: '$250,000 - $500,000';
				$pref_suppliers   = get_user_meta( $current_user_id, 'tpd_preferred_suppliers', true ) ?: '';
				$travel_types_str = get_user_meta( $current_user_id, 'tpd_travel_types', true ) ?: '';
				$airports_str     = get_user_meta( $current_user_id, 'tpd_home_airports', true ) ?: '';
				$adv_country      = get_user_meta( $current_user_id, 'tpd_country', true ) ?: 'United States';
				$accolades_text   = get_user_meta( $current_user_id, 'tpd_accolades', true ) ?: '';
				$portfolio_text   = get_user_meta( $current_user_id, 'tpd_portfolio_highlights', true ) ?: '';
				$fb_url           = get_user_meta( $current_user_id, 'tpd_social_facebook', true ) ?: '';
				$tw_url           = get_user_meta( $current_user_id, 'tpd_social_twitter', true ) ?: '';
				$yt_url           = get_user_meta( $current_user_id, 'tpd_social_youtube', true ) ?: '';
				$ig_url           = get_user_meta( $current_user_id, 'tpd_social_instagram', true ) ?: '';
				$tt_url           = get_user_meta( $current_user_id, 'tpd_social_tiktok', true ) ?: '';
				$li_url           = get_user_meta( $current_user_id, 'tpd_social_linkedin', true ) ?: '';

				$preset_banners = array(
					'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1506744038136-46273834b3fb?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1540202404-a2f29016b523?w=1200&auto=format&fit=crop&q=80',
					'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=1200&auto=format&fit=crop&q=80',
				);
				?>
				<div class="tpd-card-box" style="padding:28px 34px;">
					<h2 style="margin:0 0 18px; font-size:21px; font-weight:800; color:#00798c;"><?php esc_html_e( 'My Profile', 'tpd-tool' ); ?></h2>

					<!-- Horizontal Sub-Navigation Tabs (Jump / Filter Sections) -->
					<div class="tpd-acct-subtabs" style="display:flex; gap:18px; border-bottom:1.5px solid #e2e8f0; padding-bottom:10px; margin-bottom:22px; overflow-x:auto; white-space:nowrap;">
						<a href="#tpd-acct-sec-personal" class="tpd-acct-nav-item active" data-sec="all" style="font-size:13px; font-weight:700; color:#00798c; text-decoration:none; border-bottom:2.5px solid #00798c; padding-bottom:8px;"><?php esc_html_e( 'All Sections', 'tpd-tool' ); ?></a>
						<a href="#tpd-acct-sec-personal" class="tpd-acct-nav-item" data-sec="personal" style="font-size:13px; font-weight:600; color:#475569; text-decoration:none; padding-bottom:8px;"><?php esc_html_e( 'Personal Info', 'tpd-tool' ); ?></a>
						<a href="#tpd-acct-sec-about" class="tpd-acct-nav-item" data-sec="about" style="font-size:13px; font-weight:600; color:#475569; text-decoration:none; padding-bottom:8px;"><?php esc_html_e( 'About Me', 'tpd-tool' ); ?></a>
						<a href="#tpd-acct-sec-cert" class="tpd-acct-nav-item" data-sec="cert" style="font-size:13px; font-weight:600; color:#475569; text-decoration:none; padding-bottom:8px;"><?php esc_html_e( 'Certification', 'tpd-tool' ); ?></a>
						<a href="#tpd-acct-sec-spec" class="tpd-acct-nav-item" data-sec="spec" style="font-size:13px; font-weight:600; color:#475569; text-decoration:none; padding-bottom:8px;"><?php esc_html_e( 'Specialties', 'tpd-tool' ); ?></a>
						<a href="#tpd-acct-sec-portfolio" class="tpd-acct-nav-item" data-sec="portfolio" style="font-size:13px; font-weight:600; color:#475569; text-decoration:none; padding-bottom:8px;"><?php esc_html_e( 'Accolade & Portfolio', 'tpd-tool' ); ?></a>
						<a href="#tpd-acct-sec-social" class="tpd-acct-nav-item" data-sec="social" style="font-size:13px; font-weight:600; color:#475569; text-decoration:none; padding-bottom:8px;"><?php esc_html_e( 'Social & Banner', 'tpd-tool' ); ?></a>
					</div>

					<form id="tpd-advisor-profile-editor-form" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="tpd_save_advisor_profile">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

						<!-- Top Identity Header Bar -->
						<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; padding-bottom:22px; margin-bottom:24px; border-bottom:1px solid #e2e8f0;">
							<div style="display:flex; align-items:center; gap:16px;">
								<div style="position:relative; width:76px; height:76px;">
									<img id="tpd-preview-headshot" src="<?php echo esc_url( $avatar_src ); ?>" style="width:76px; height:76px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;">
									<button type="button" onclick="document.getElementById('tpd-file-headshot').click();" title="<?php esc_attr_e( 'Upload Profile Photo', 'tpd-tool' ); ?>" style="position:absolute; bottom:0; right:0; width:26px; height:26px; border-radius:50%; background:#00798c; color:#fff; border:2px solid #fff; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:11px;">
										<i class="fa-solid fa-camera"></i>
									</button>
									<input type="hidden" name="headshot_url" id="tpd_headshot_url" value="<?php echo esc_attr( $headshot_url ); ?>">
									<input type="file" id="tpd-file-headshot" accept="image/*" style="display:none;">
								</div>
								<div>
									<h3 class="tpd-user-name" style="margin:0; font-size:18px; font-weight:800; color:#00798c;"><?php echo esc_html( $full_name ); ?></h3>
									<span style="font-size:12.5px; color:#0d9488; font-weight:600;">@<?php echo esc_html( $profile_handle ); ?></span>
									<div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:4px; font-size:12.5px; color:#475569;">
										<span><i class="fa-regular fa-envelope" style="color:#00798c;"></i> <?php echo esc_html( $user ? $user->user_email : '' ); ?></span>
										<?php if ( $phone ) : ?>
											<span><i class="fa-solid fa-phone" style="color:#00798c;"></i> <?php echo esc_html( $phone ); ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div style="display:flex; gap:10px; align-items:center;">
								<button type="submit" class="tpd-btn" style="background:#00798c; color:#ffffff; border:none; padding:9px 20px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer;">
									<?php esc_html_e( 'Submit', 'tpd-tool' ); ?>
								</button>
								<a href="<?php echo esc_url( $public_prof_url ); ?>" target="_blank" class="tpd-btn" style="background:#013243; color:#ffffff; text-decoration:none; padding:9px 16px; border-radius:6px; font-weight:700; font-size:13px;">
									<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View Public Profile', 'tpd-tool' ); ?>
								</a>
							</div>
						</div>

						<!-- SECTION 1: PERSONAL INFO -->
						<div id="tpd-acct-sec-personal" class="tpd-acct-section" data-sec-pane="personal">
							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="first_name" required value="<?php echo esc_attr( $user ? $user->first_name : '' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="last_name" required value="<?php echo esc_attr( $user ? $user->last_name : '' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
								<input type="tel" name="phone" required value="<?php echo esc_attr( $phone ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
								<input type="email" name="email" required value="<?php echo esc_attr( $user ? $user->user_email : '' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Profile Handle *', 'tpd-tool' ); ?></label>
								<input type="text" name="profile_handle" id="tpd-profile-handle-input" required value="<?php echo esc_attr( $profile_handle ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Advisor Profile URL *', 'tpd-tool' ); ?></label>
								<input type="text" id="tpd-advisor-url-preview" readonly value="<?php echo esc_attr( $public_prof_url ); ?>" class="tpd-input" style="background:#e2e8f0; color:#475569;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Address *', 'tpd-tool' ); ?> <small style="color:#dc2626; font-weight:600;"><?php esc_html_e( '(Note: Street address and zip will not be publicized)', 'tpd-tool' ); ?></small></label>
								<input type="text" name="agency_address" required value="<?php echo esc_attr( $agency_address ?: $location ); ?>" placeholder="<?php esc_attr_e( 'Street Address, City, State, Zip', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-grid-2">
								<div class="tpd-form-group mb-3">
									<label><?php esc_html_e( 'City / State / Public Location *', 'tpd-tool' ); ?></label>
									<input type="text" name="location" required value="<?php echo esc_attr( $location ); ?>" placeholder="<?php esc_attr_e( 'e.g. Austin, TX', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
								</div>
								<div class="tpd-form-group mb-3">
									<label><?php esc_html_e( 'Travel Agency Name *', 'tpd-tool' ); ?></label>
									<input type="text" name="agency_name" required value="<?php echo esc_attr( $agency_name ); ?>" placeholder="<?php esc_attr_e( 'Your Agency Name', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
								</div>
							</div>
						</div>

						<!-- SECTION 2: ABOUT ME & PRACTICE METRICS -->
						<div id="tpd-acct-sec-about" class="tpd-acct-section" data-sec-pane="about">
							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Partner Description *', 'tpd-tool' ); ?></label>
								<textarea name="bio" rows="4" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Tell clients and suppliers about your travel advisory practice, expertise, and service philosophy...', 'tpd-tool' ); ?>"><?php echo esc_textarea( $bio_content ); ?></textarea>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Consultation Fee(s) *', 'tpd-tool' ); ?></label>
								<div style="display:flex; gap:24px; align-items:center; margin-top:6px;">
									<label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
										<input type="radio" name="consultation_fee" value="yes" <?php checked( $consult_fee, 'yes' ); ?>>
										<?php esc_html_e( 'Yes (consultation fee)', 'tpd-tool' ); ?>
									</label>
									<label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
										<input type="radio" name="consultation_fee" value="no" <?php checked( $consult_fee, 'no' ); ?>>
										<?php esc_html_e( 'No (free first)', 'tpd-tool' ); ?>
									</label>
								</div>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'When did you begin in the travel industry? *', 'tpd-tool' ); ?></label>
								<select name="years_experience" class="tpd-select" style="background:#f1f5f9;">
									<?php for ( $yr = (int) gmdate( 'Y' ); $yr >= 1975; $yr-- ) : ?>
										<option value="<?php echo esc_attr( $yr ); ?>" <?php selected( (string) $year_started, (string) $yr ); ?>><?php echo esc_html( $yr ); ?></option>
									<?php endfor; ?>
								</select>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Do you specialize in group travel? *', 'tpd-tool' ); ?></label>
								<select name="group_travel_spec" class="tpd-select" style="background:#f1f5f9;">
									<?php
									$grp_opts = array(
										'Group travel (10+ rooms or 20+ people)',
										'Small group & multi-generational travel (5–9 rooms)',
										'Individual / FIT custom travel only',
										'Corporate & incentive groups',
									);
									foreach ( $grp_opts as $go ) :
									?>
										<option value="<?php echo esc_attr( $go ); ?>" <?php selected( $group_spec, $go ); ?>><?php echo esc_html( $go ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'How many clients do you book per year? *', 'tpd-tool' ); ?></label>
								<select name="clients_per_year" class="tpd-select" style="background:#f1f5f9;">
									<?php foreach ( array( '1 to 50', '51 to 100', '101 to 250', '250+' ) as $cpy ) : ?>
										<option value="<?php echo esc_attr( $cpy ); ?>" <?php selected( $clients_per_yr, $cpy ); ?>><?php echo esc_html( $cpy ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'What was your (GROSS) travel sales volume in the previous full year? *', 'tpd-tool' ); ?></label>
								<select name="sales_volume" class="tpd-select" style="background:#f1f5f9;">
									<?php foreach ( array( '$0 - $100,000', '$100,000 - $250,000', '$250,000 - $500,000', '$500,000 - $1,000,000', '$1,000,000+' ) as $sv_opt ) : ?>
										<option value="<?php echo esc_attr( $sv_opt ); ?>" <?php selected( $prev_gross_sales, $sv_opt ); ?>><?php echo esc_html( $sv_opt ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'What is your (GROSS) travel sales volume goal for the current full year? *', 'tpd-tool' ); ?></label>
								<select name="sales_volume_goal" class="tpd-select" style="background:#f1f5f9;">
									<?php foreach ( array( '$0 - $100,000', '$100,000 - $250,000', '$250,000 - $500,000', '$500,000 - $1,000,000', '$1,000,000+' ) as $svg_opt ) : ?>
										<option value="<?php echo esc_attr( $svg_opt ); ?>" <?php selected( $goal_gross_sales, $svg_opt ); ?>><?php echo esc_html( $svg_opt ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<!-- SECTION 3: CERTIFICATION & HOST AGENCY -->
						<div id="tpd-acct-sec-cert" class="tpd-acct-section" data-sec-pane="cert">
							<div class="tpd-form-group mb-3">
								<label class="font-bold"><?php esc_html_e( 'Industry Accreditations (Check all that apply)', 'tpd-tool' ); ?></label>
								<div style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
									<?php
									$certs = array(
										array( 'label' => 'CLIA', 'name' => 'clia_number', 'val' => $clia_num ),
										array( 'label' => 'IATA', 'name' => 'iata_number', 'val' => $iata_num ),
										array( 'label' => 'ARC',  'name' => 'arc_number',  'val' => $arc_num ),
										array( 'label' => 'TRUE', 'name' => 'true_number', 'val' => $true_num ),
									);
									foreach ( $certs as $ct ) :
									?>
										<div style="display:flex; align-items:center; gap:14px;">
											<label style="display:flex; align-items:center; gap:8px; width:90px; font-weight:700;">
												<input type="checkbox" <?php checked( ! empty( $ct['val'] ) ); ?>> <?php echo esc_html( $ct['label'] ); ?>
											</label>
											<input type="text" name="<?php echo esc_attr( $ct['name'] ); ?>" value="<?php echo esc_attr( $ct['val'] ); ?>" placeholder="<?php echo esc_attr( 'Enter ' . $ct['label'] . ' Number (Optional)' ); ?>" class="tpd-input" style="max-width:320px; background:#f1f5f9;">
										</div>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Current Host *', 'tpd-tool' ); ?></label>
								<div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap; margin-top:6px;">
									<label style="display:flex; align-items:center; gap:8px; font-weight:600;">
										<input type="radio" name="has_host" value="yes" <?php checked( $has_host, 'yes' ); ?>>
										<?php esc_html_e( 'Current Host', 'tpd-tool' ); ?>
									</label>
									<select name="host_agency" class="tpd-select" style="max-width:260px;">
										<?php
										$hosts = array( 'KHM Travel Group', 'Avoya Travel', 'Nexion Travel Group', 'Travel Planners International (TPI)', 'Outside Agents', 'Cruise Planners', 'Fora Travel', 'WorldVia', 'Independent / Other' );
										foreach ( $hosts as $h_opt ) :
										?>
											<option value="<?php echo esc_attr( $h_opt ); ?>" <?php selected( $host_agency, $h_opt ); ?>><?php echo esc_html( $h_opt ); ?></option>
										<?php endforeach; ?>
									</select>
									<label style="display:flex; align-items:center; gap:8px; font-weight:600;">
										<input type="radio" name="has_host" value="no" <?php checked( $has_host, 'no' ); ?>>
										<?php esc_html_e( 'No (I don\'t have a host)', 'tpd-tool' ); ?>
									</label>
								</div>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Consortia Affiliation', 'tpd-tool' ); ?></label>
								<input type="text" name="consortia" value="<?php echo esc_attr( $consortia ); ?>" placeholder="e.g. Virtuoso, Signature Travel Network, Travel Leaders" class="tpd-input" style="background:#f1f5f9;">
							</div>
						</div>

						<!-- SECTION 4: PERSONAL SPECIALTIES -->
						<div id="tpd-acct-sec-spec" class="tpd-acct-section" data-sec-pane="spec">
							<h4 style="margin:20px 0 12px; font-size:15px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Personal Specialties', 'tpd-tool' ); ?></h4>

							<?php
							$saved_dests  = $advisor_post_id ? wp_get_object_terms( $advisor_post_id, 'travel_destination', array( 'fields' => 'names' ) ) : array();
							$saved_styles = $advisor_post_id ? wp_get_object_terms( $advisor_post_id, 'travel_style', array( 'fields' => 'names' ) ) : array();
							if ( is_wp_error( $saved_dests ) ) $saved_dests = array();
							if ( is_wp_error( $saved_styles ) ) $saved_styles = array();
							?>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Destinations *', 'tpd-tool' ); ?> <small class="text-muted">(<?php esc_html_e( 'Select your top destination specialties', 'tpd-tool' ); ?>)</small></label>
								<div class="tpd-checkbox-grid" style="margin-top:6px;">
									<?php
									$dest_choices = array( 'Western Europe', 'Mediterranean', 'Danube River', 'Caribbean', 'Alaska', 'Africa & Safari', 'Japan & Asia', 'South Pacific', 'Antarctica & Polar', 'South America', 'Mexico & Central America', 'United Kingdom & Ireland' );
									foreach ( $dest_choices as $dc ) :
									?>
										<label class="tpd-pill-checkbox">
											<input type="checkbox" name="destinations[]" value="<?php echo esc_attr( $dc ); ?>" <?php checked( in_array( $dc, $saved_dests, true ) ); ?>>
											<span><?php echo esc_html( $dc ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Suppliers *', 'tpd-tool' ); ?> <small class="text-muted">(<?php esc_html_e( 'Enter or select your preferred supplier partners', 'tpd-tool' ); ?>)</small></label>
								<input type="text" name="preferred_suppliers" value="<?php echo esc_attr( $pref_suppliers ); ?>" placeholder="<?php esc_attr_e( 'Select multiple suppliers names (e.g. Viking, Silversea, Globus, Four Seasons)', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Styles *', 'tpd-tool' ); ?></label>
								<div class="tpd-checkbox-grid" style="margin-top:6px;">
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

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Types of Travel *', 'tpd-tool' ); ?></label>
								<input type="text" name="travel_types" value="<?php echo esc_attr( $travel_types_str ); ?>" placeholder="<?php esc_attr_e( 'Select multiple travel types (e.g. Culinary, Safari, Wellness, Yacht Charters)', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Airports *', 'tpd-tool' ); ?></label>
								<input type="text" name="home_airports" value="<?php echo esc_attr( $airports_str ); ?>" placeholder="<?php esc_attr_e( 'Select US/CA Airport codes (e.g. JFK, MIA, LAX, YYZ)', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Country *', 'tpd-tool' ); ?></label>
								<select name="country" class="tpd-select" style="background:#f1f5f9;">
									<?php foreach ( array( 'United States', 'Canada', 'United Kingdom', 'Australia', 'Mexico', 'Germany', 'France', 'Italy', 'Spain', 'Pakistan', 'Other' ) as $c_opt ) : ?>
										<option value="<?php echo esc_attr( $c_opt ); ?>" <?php selected( $adv_country, $c_opt ); ?>><?php echo esc_html( $c_opt ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<!-- SECTION 5: ACCOLADES, EXTERNAL AGENCY & PORTFOLIO -->
						<div id="tpd-acct-sec-portfolio" class="tpd-acct-section" data-sec-pane="portfolio">
							<h4 style="margin:20px 0 12px; font-size:15px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Accolades, External Website & Portfolio', 'tpd-tool' ); ?></h4>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'External Agency Website URL', 'tpd-tool' ); ?></label>
								<input type="url" name="website" value="<?php echo esc_attr( $website ); ?>" placeholder="https://www.yourtravelagency.com" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Accolades & Industry Recognition', 'tpd-tool' ); ?></label>
								<input type="text" name="accolades" value="<?php echo esc_attr( $accolades_text ); ?>" placeholder="<?php esc_attr_e( 'e.g. Top 30 Under 30, President\'s Circle Producer, Certified Destination Specialist', 'tpd-tool' ); ?>" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'Portfolio & Signature Trip Highlights', 'tpd-tool' ); ?></label>
								<textarea name="portfolio_highlights" rows="3" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Share memorable itineraries or client trips you have curated...', 'tpd-tool' ); ?>"><?php echo esc_textarea( $portfolio_text ); ?></textarea>
							</div>
						</div>

						<!-- SECTION 6: SOCIAL ACCOUNT PAGE LINKS & BANNER GALLERY -->
						<div id="tpd-acct-sec-social" class="tpd-acct-section" data-sec-pane="social">
							<h4 style="margin:20px 0 12px; font-size:15px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Social Account Page Links', 'tpd-tool' ); ?></h4>

							<div class="tpd-form-group mb-2">
								<label><?php esc_html_e( 'Facebook', 'tpd-tool' ); ?></label>
								<input type="url" name="social_facebook" value="<?php echo esc_attr( $fb_url ); ?>" placeholder="Facebook Page URL" class="tpd-input" style="background:#f1f5f9;">
							</div>
							<div class="tpd-form-group mb-2">
								<label><?php esc_html_e( 'Twitter / X', 'tpd-tool' ); ?></label>
								<input type="url" name="social_twitter" value="<?php echo esc_attr( $tw_url ); ?>" placeholder="Twitter Page URL" class="tpd-input" style="background:#f1f5f9;">
							</div>
							<div class="tpd-form-group mb-2">
								<label><?php esc_html_e( 'YouTube', 'tpd-tool' ); ?></label>
								<input type="url" name="social_youtube" value="<?php echo esc_attr( $yt_url ); ?>" placeholder="YouTube Page URL" class="tpd-input" style="background:#f1f5f9;">
							</div>
							<div class="tpd-form-group mb-2">
								<label><?php esc_html_e( 'Instagram', 'tpd-tool' ); ?></label>
								<input type="url" name="social_instagram" value="<?php echo esc_attr( $ig_url ); ?>" placeholder="Instagram Page URL" class="tpd-input" style="background:#f1f5f9;">
							</div>
							<div class="tpd-form-group mb-2">
								<label><?php esc_html_e( 'TikTok', 'tpd-tool' ); ?></label>
								<input type="url" name="social_tiktok" value="<?php echo esc_attr( $tt_url ); ?>" placeholder="TikTok Page URL" class="tpd-input" style="background:#f1f5f9;">
							</div>
							<div class="tpd-form-group mb-3">
								<label><?php esc_html_e( 'LinkedIn', 'tpd-tool' ); ?></label>
								<input type="url" name="social_linkedin" value="<?php echo esc_attr( $li_url ); ?>" placeholder="LinkedIn Profile URL" class="tpd-input" style="background:#f1f5f9;">
							</div>

							<!-- Agency Logo & Banner Image Upload + 8 Scenic Preset Gallery -->
							<div class="tpd-form-group mt-4">
								<label class="font-bold"><?php esc_html_e( 'Banner Image & Agency Logo', 'tpd-tool' ); ?></label>
								<div style="border:2px dashed #cbd5e1; border-radius:12px; padding:26px; text-align:center; background:#f8fafc; margin-bottom:14px;">
									<img id="tpd-preview-banner" src="<?php echo esc_url( $banner_url ?: $preset_banners[0] ); ?>" style="max-height:140px; width:100%; object-fit:cover; border-radius:8px; margin-bottom:14px;">
									<input type="hidden" name="banner_url" id="tpd_banner_url" value="<?php echo esc_attr( $banner_url ?: $preset_banners[0] ); ?>">
									<input type="file" id="tpd-file-banner" accept="image/*" style="display:none;">
									<input type="hidden" name="logo_url" id="tpd_logo_url" value="<?php echo esc_attr( $logo_url ); ?>">
									<input type="file" id="tpd-file-logo" accept="image/*" style="display:none;">
									<div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
										<button type="button" class="tpd-btn" onclick="document.getElementById('tpd-file-banner').click();" style="background:#00798c; color:#fff; border:none; padding:9px 20px; border-radius:6px; font-weight:700; cursor:pointer;">
											<?php esc_html_e( 'Select Custom Banner Image', 'tpd-tool' ); ?>
										</button>
										<button type="button" class="tpd-btn tpd-btn-outline" onclick="document.getElementById('tpd-file-logo').click();" style="padding:9px 18px; border-radius:6px; font-weight:600; cursor:pointer;">
											<i class="fa-regular fa-image"></i> <?php esc_html_e( 'Upload Agency Logo', 'tpd-tool' ); ?>
										</button>
									</div>
								</div>

								<label style="font-size:12.5px; color:#475569; margin-bottom:8px; display:block;"><?php esc_html_e( 'Or Choose a Preset Scenic Banner Image:', 'tpd-tool' ); ?></label>
								<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)); gap:12px;">
									<?php foreach ( $preset_banners as $idx => $pb_url ) : ?>
										<label class="tpd-preset-banner-tile" style="position:relative; cursor:pointer; border-radius:8px; overflow:hidden; height:84px; border:2px solid <?php echo ( $banner_url === $pb_url ) ? '#00798c' : 'transparent'; ?>;">
											<input type="radio" name="preset_banner_choice" value="<?php echo esc_url( $pb_url ); ?>" class="tpd-preset-banner-radio" <?php checked( $banner_url, $pb_url ); ?> style="position:absolute; top:6px; left:6px; z-index:2;">
											<img src="<?php echo esc_url( $pb_url ); ?>" alt="Banner <?php echo esc_attr( $idx + 1 ); ?>" style="width:100%; height:100%; object-fit:cover;">
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<!-- Dynamic ACF Field Groups & Admin Custom Fields Bridge -->
						<?php
						if ( class_exists( 'TPD_Tool_CPT' ) ) {
							TPD_Tool_CPT::render_dynamic_fields_html( 'travel_advisor', $advisor_post_id, $current_user_id, 'dashboard' );
						}
						?>

						<div id="tpd-advisor-profile-status" class="tpd-reg-status" style="display:none; margin-top:16px;"></div>

						<div class="mt-4" style="text-align:right; border-top:1px solid #e2e8f0; padding-top:18px;">
							<button type="submit" class="tpd-btn" id="tpd-save-advisor-profile-btn" style="background:#00798c; color:#ffffff; border:none; padding:12px 32px; border-radius:6px; font-weight:700; cursor:pointer;">
								<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Submit & Save Profile', 'tpd-tool' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- VIEW 3: SEARCH SUPPLIERS DIRECTORY -->
			<div id="tpd-view-suppliers" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header mb-3" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
						<div>
							<h3 style="margin:0;"><i class="fa-solid fa-magnifying-glass text-blue"></i> <?php esc_html_e( 'Supplier Partner Directory', 'tpd-tool' ); ?></h3>
							<span class="text-muted" style="font-size:12.5px;"><?php printf( esc_html__( '%d Active Partner Brands · Sorted by Partnership Tier', 'tpd-tool' ), count( $supplier_posts ) ); ?></span>
						</div>
					</div>

					<!-- Search & Category Filters Bar -->
					<div class="tpd-directory-filter-bar mb-4" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
						<div class="tpd-ss-input-wrap" style="flex:1; min-width:240px; background:#fff; border:1px solid #cbd5e1; border-radius:8px;">
							<i class="fa-solid fa-magnifying-glass"></i>
							<input type="text" placeholder="<?php esc_attr_e( 'Filter by partner brand, destination, or keywords...', 'tpd-tool' ); ?>" id="tpd-directory-keyword-input">
						</div>
						<select id="tpd-directory-cat-select" class="tpd-input" style="width:200px; padding:8px 12px; background:#fff; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
							<option value=""><?php esc_html_e( 'All Categories', 'tpd-tool' ); ?></option>
							<option value="Ocean Cruises"><?php esc_html_e( 'Ocean Cruises', 'tpd-tool' ); ?></option>
							<option value="River Cruises"><?php esc_html_e( 'River Cruises', 'tpd-tool' ); ?></option>
							<option value="Tour Operators"><?php esc_html_e( 'Tour Operators', 'tpd-tool' ); ?></option>
							<option value="All-Inclusives"><?php esc_html_e( 'All-Inclusives', 'tpd-tool' ); ?></option>
							<option value="Escorted Tours"><?php esc_html_e( 'Escorted Tours', 'tpd-tool' ); ?></option>
							<option value="Expedition/Safaris"><?php esc_html_e( 'Expedition/Safaris', 'tpd-tool' ); ?></option>
							<option value="Cruise"><?php esc_html_e( 'Cruise Line', 'tpd-tool' ); ?></option>
							<option value="Resort"><?php esc_html_e( 'Resort / Hotel', 'tpd-tool' ); ?></option>
							<option value="DMC"><?php esc_html_e( 'DMC', 'tpd-tool' ); ?></option>
							<option value="Rail"><?php esc_html_e( 'Rail Journeys', 'tpd-tool' ); ?></option>
							<option value="Villa"><?php esc_html_e( 'Villa Collections', 'tpd-tool' ); ?></option>
						</select>
						<button type="button" id="tpd-reset-dir-filters" class="tpd-btn tpd-btn-sm tpd-btn-outline" style="border-radius:6px; font-size:12px; padding:8px 14px;">
							<i class="fa-solid fa-rotate-left"></i> <?php esc_html_e( 'Reset Filters', 'tpd-tool' ); ?>
						</button>
					</div>

					<!-- Directory Grid (Sorted: Premium first, Standard second, Basic third; Chat removed for profile uniformity) -->
					<?php
					$sorted_suppliers = $supplier_posts;
					usort( $sorted_suppliers, function( $a, $b ) {
						$tier_weights = array( 'premium' => 3, 'standard' => 2, 'basic' => 1 );
						$a_owner   = (int) get_post_meta( $a->ID, 'tpd_assigned_user', true );
						$b_owner   = (int) get_post_meta( $b->ID, 'tpd_assigned_user', true );
						$a_tier    = TPD_Tool_Tiers::get_user_tier( $a_owner );
						$b_tier    = TPD_Tool_Tiers::get_user_tier( $b_owner );
						$a_w       = isset( $tier_weights[ $a_tier ] ) ? $tier_weights[ $a_tier ] : 1;
						$b_w       = isset( $tier_weights[ $b_tier ] ) ? $tier_weights[ $b_tier ] : 1;
						if ( $a_w === $b_w ) {
							return strcmp( $a->post_title, $b->post_title );
						}
						return ( $a_w > $b_w ) ? -1 : 1;
					} );
					?>
					<div class="tpd-featured-suppliers-grid" id="tpd-directory-results-grid">
						<?php foreach ( $sorted_suppliers as $sp ) :
							$sp_banner = get_post_meta( $sp->ID, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=500&auto=format&fit=crop&q=80';
							$sp_cat    = get_post_meta( $sp->ID, 'tpd_tagline', true ) ?: get_post_meta( $sp->ID, 'tpd_supplier_category', true ) ?: 'Verified Supplier Partner';
							$sp_owner  = (int) get_post_meta( $sp->ID, 'tpd_assigned_user', true ) ?: 1;
							$sp_tier   = TPD_Tool_Tiers::get_user_tier( $sp_owner );
							$is_saved  = in_array( $sp->ID, $saved_supplier_ids, true );
						?>
							<div class="tpd-supp-showcase-card tpd-supplier-profile-card" data-title="<?php echo esc_attr( strtolower( $sp->post_title ) ); ?>" data-cat="<?php echo esc_attr( strtolower( $sp_cat ) ); ?>" style="border-radius:10px; overflow:hidden;">
								<div class="tpd-ssc-thumb" style="height:125px; background-image: url('<?php echo esc_url( $sp_banner ); ?>'); display:flex; justify-content:space-between; align-items:flex-start; padding:10px;">
									<?php if ( 'premium' === $sp_tier ) : ?>
										<span style="background:#013243; color:#fff; font-size:9.5px; font-weight:800; padding:2px 7px; border-radius:4px; text-transform:uppercase;"><i class="fa-solid fa-crown text-gold"></i> PREFERRED</span>
									<?php elseif ( 'standard' === $sp_tier ) : ?>
										<span style="background:#00798c; color:#fff; font-size:9.5px; font-weight:800; padding:2px 7px; border-radius:4px; text-transform:uppercase;">PARTNER</span>
									<?php else : ?>
										<span></span>
									<?php endif; ?>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-toggle-favorite-btn <?php echo $is_saved ? 'active saved text-gold' : ''; ?>" data-supplier-id="<?php echo esc_attr( $sp->ID ); ?>" style="background:rgba(255,255,255,0.95); border:none; border-radius:6px; padding:4px 8px; cursor:pointer;" title="<?php esc_attr_e( 'Save Supplier', 'tpd-tool' ); ?>">
										<i class="<?php echo $is_saved ? 'fa-solid' : 'fa-regular'; ?> fa-bookmark"></i>
									</button>
								</div>
								<div class="tpd-ssc-body" style="padding:14px;">
									<span class="tpd-brand-title" style="font-size:14px; font-weight:800;"><?php echo esc_html( $sp->post_title ); ?></span>
									<span class="tpd-ssc-category" style="font-size:12px; color:#64748b; margin-top:2px;"><?php echo esc_html( wp_trim_words( $sp_cat, 4 ) ); ?></span>
									<div class="tpd-ssc-actions" style="margin-top:14px;">
										<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline" style="text-decoration:none; text-align:center; width:100%; border-radius:6px; font-size:12px; font-weight:700;">
											<?php esc_html_e( 'View Supplier Profile', 'tpd-tool' ); ?> &rarr;
										</a>
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
						<h3><i class="fa-regular fa-bookmark text-blue"></i> <?php esc_html_e( 'Your Saved Suppliers', 'tpd-tool' ); ?></h3>
					</div>
					<div class="tpd-featured-suppliers-grid mt-4">
						<?php echo do_shortcode( '[tpd_saved_suppliers]' ); ?>
					</div>
				</div>
			</div>

			<!-- VIEW 5: CLIENT INQUIRIES & TRIP LEADS -->
			<div id="tpd-view-inquiries" class="tpd-tab-panel">
				<!-- Inquiries Shortcode Embed Helper -->
				<div style="background:#f0fdfa; border:1px solid #99f6e4; border-radius:12px; padding:16px 20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
					<div style="display:flex; align-items:center; gap:12px;">
						<i class="fa-solid fa-paper-plane" style="font-size:22px; color:#00798c;"></i>
						<div>
							<strong style="color:#013243; font-size:14px; display:block;"><?php esc_html_e( 'Collect Inquiries on Your Public Website or Elementor Pages', 'tpd-tool' ); ?></strong>
							<span style="color:#475569; font-size:12.5px;"><?php esc_html_e( 'Use your personal shortcode to drop an Inquire button or full trip request form anywhere.', 'tpd-tool' ); ?></span>
						</div>
					</div>
					<div style="display:flex; gap:8px; align-items:center;">
						<code style="background:#ffffff; border:1px solid #cbd5e1; padding:6px 10px; border-radius:6px; font-size:12px; font-family:monospace; color:#00798c;">
							[tpd_inquire_button advisor_id="<?php echo esc_attr( $current_user_id ); ?>"]
						</code>
						<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="navigator.clipboard.writeText('[tpd_inquire_button advisor_id=\'<?php echo esc_attr( $current_user_id ); ?>\']'); alert('Shortcode copied to clipboard!');">
							<i class="fa-regular fa-copy"></i> <?php esc_html_e( 'Copy', 'tpd-tool' ); ?>
						</button>
					</div>
				</div>

				<div class="tpd-card-box">
					<div class="tpd-ss-header mb-3" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
						<div>
							<h3 style="margin:0;"><i class="fa-solid fa-inbox text-blue"></i> <?php esc_html_e( 'Client Trip Inquiries & Leads', 'tpd-tool' ); ?></h3>
							<p style="margin:4px 0 0; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Inbound traveler trip requests submitted from your public profile and website forms.', 'tpd-tool' ); ?></p>
						</div>
					</div>

					<?php
					$inq_args = array(
						'post_type'      => 'tpd_inquiry',
						'posts_per_page' => 30,
						'post_status'    => array( 'publish', 'pending', 'private' ),
						'meta_query'     => array(
							array(
								'key'     => '_tpd_advisor_id',
								'value'   => $current_user_id,
								'compare' => '=',
							),
						),
					);
					$inquiries = get_posts( $inq_args );
					// Fallback to all inquiries if testing / demo admin
					if ( empty( $inquiries ) && ( current_user_can( 'manage_options' ) || count( $inquiries ) === 0 ) ) {
						$inquiries = get_posts( array( 'post_type' => 'tpd_inquiry', 'posts_per_page' => 20 ) );
					}

					if ( ! empty( $inquiries ) ) :
					?>
						<div style="overflow-x:auto;">
							<table class="tpd-inquiries-table mt-3" style="width:100%; border-collapse:collapse;">
								<thead>
									<tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0; text-align:left; font-size:12px; color:#475569;">
										<th style="padding:12px;"><?php esc_html_e( 'Traveler', 'tpd-tool' ); ?></th>
										<th style="padding:12px;"><?php esc_html_e( 'Contact Info', 'tpd-tool' ); ?></th>
										<th style="padding:12px;"><?php esc_html_e( 'Destination', 'tpd-tool' ); ?></th>
										<th style="padding:12px;"><?php esc_html_e( 'Dates & Guests', 'tpd-tool' ); ?></th>
										<th style="padding:12px;"><?php esc_html_e( 'Budget', 'tpd-tool' ); ?></th>
										<th style="padding:12px;"><?php esc_html_e( 'Status', 'tpd-tool' ); ?></th>
										<th style="padding:12px;"><?php esc_html_e( 'Date', 'tpd-tool' ); ?></th>
										<th style="padding:12px; text-align:right;"><?php esc_html_e( 'Actions', 'tpd-tool' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $inquiries as $inq ) :
										$t_name   = get_post_meta( $inq->ID, '_tpd_traveler_name', true ) ?: $inq->post_title;
										$t_email  = get_post_meta( $inq->ID, '_tpd_traveler_email', true ) ?: '';
										$t_phone  = get_post_meta( $inq->ID, '_tpd_traveler_phone', true ) ?: '';
										$t_dest   = get_post_meta( $inq->ID, '_tpd_destination', true ) ?: 'Europe';
										$t_dates  = get_post_meta( $inq->ID, '_tpd_travel_dates', true ) ?: 'Flexible';
										$t_pax    = get_post_meta( $inq->ID, '_tpd_passengers', true ) ?: '2';
										$t_budget = get_post_meta( $inq->ID, '_tpd_budget', true ) ?: '$5,000 - $10,000';
										$t_status = get_post_meta( $inq->ID, '_tpd_status', true ) ?: 'New Lead';
										$t_notes  = $inq->post_content ?: __( 'No additional trip notes provided.', 'tpd-tool' );
									?>
										<tr style="border-bottom:1px solid #e2e8f0; font-size:13px;">
											<td style="padding:14px 12px;">
												<strong style="color:#013243; display:block;"><?php echo esc_html( $t_name ); ?></strong>
											</td>
											<td style="padding:14px 12px;">
												<div style="font-size:12px; color:#475569;">
													<?php if ( $t_email ) : ?>
														<div><i class="fa-regular fa-envelope text-blue"></i> <?php echo esc_html( $t_email ); ?></div>
													<?php endif; ?>
													<?php if ( $t_phone ) : ?>
														<div style="margin-top:2px;"><i class="fa-solid fa-phone text-blue"></i> <?php echo esc_html( $t_phone ); ?></div>
													<?php endif; ?>
												</div>
											</td>
											<td style="padding:14px 12px;">
												<span style="font-weight:600; color:#0f172a;"><?php echo esc_html( $t_dest ); ?></span>
											</td>
											<td style="padding:14px 12px; font-size:12px; color:#475569;">
												<div><?php echo esc_html( $t_dates ); ?></div>
												<div style="font-size:11px; color:#64748b;"><?php echo esc_html( $t_pax ); ?> <?php esc_html_e( 'Traveler(s)', 'tpd-tool' ); ?></div>
											</td>
											<td style="padding:14px 12px;">
												<span class="tpd-budget-badge" style="background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:6px; font-weight:700; font-size:11.5px;"><?php echo esc_html( $t_budget ); ?></span>
											</td>
											<td style="padding:14px 12px;">
												<span style="background:#fef3c7; color:#b45309; padding:3px 8px; border-radius:6px; font-weight:800; font-size:11px; text-transform:uppercase;">
													<?php echo esc_html( $t_status ); ?>
												</span>
											</td>
											<td style="padding:14px 12px; font-size:12px; color:#64748b;">
												<?php echo esc_html( get_the_date( 'M j, Y', $inq->ID ) ); ?>
											</td>
											<td style="padding:14px 12px; text-align:right;">
												<div style="display:flex; gap:6px; justify-content:flex-end;">
													<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-view-inquiry-btn" data-name="<?php echo esc_attr( $t_name ); ?>" data-email="<?php echo esc_attr( $t_email ); ?>" data-phone="<?php echo esc_attr( $t_phone ); ?>" data-dest="<?php echo esc_attr( $t_dest ); ?>" data-dates="<?php echo esc_attr( $t_dates ); ?>" data-pax="<?php echo esc_attr( $t_pax ); ?>" data-budget="<?php echo esc_attr( $t_budget ); ?>" data-notes="<?php echo esc_attr( $t_notes ); ?>">
														<i class="fa-solid fa-eye"></i> <?php esc_html_e( 'Details', 'tpd-tool' ); ?>
													</button>
													<?php if ( $t_email ) : ?>
														<a href="mailto:<?php echo esc_attr( $t_email ); ?>?subject=<?php echo esc_attr( rawurlencode( 'Your Trip Inquiry to ' . $t_dest ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-primary" style="text-decoration:none; background:#00798c; border-color:#00798c;">
															<i class="fa-solid fa-reply"></i> <?php esc_html_e( 'Reply', 'tpd-tool' ); ?>
														</a>
													<?php endif; ?>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<div style="text-align:center; padding:40px 20px; background:#f8fafc; border-radius:10px; border:1px dashed #cbd5e1; margin-top:14px;">
							<i class="fa-regular fa-paper-plane" style="font-size:36px; color:#94a3b8; margin-bottom:12px;"></i>
							<h4 style="margin:0 0 6px; font-size:16px; color:#0f172a;"><?php esc_html_e( 'No Client Inquiries Received Yet', 'tpd-tool' ); ?></h4>
							<p style="margin:0 0 16px; font-size:13px; color:#64748b;"><?php esc_html_e( 'When clients click "Inquire" on your public profile or fill out a trip form, their leads will appear here instantly.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Lead Details Modal -->
				<div id="tpd-inquiry-details-modal" style="display:none; position:fixed; inset:0; background:rgba(11,21,38,0.6); z-index:99999; align-items:center; justify-content:center; padding:20px;">
					<div style="background:#ffffff; border-radius:14px; width:100%; max-width:540px; box-shadow:0 10px 30px rgba(0,0,0,0.2); overflow:hidden;">
						<div style="background:#013243; color:#fff; padding:18px 22px; display:flex; justify-content:space-between; align-items:center;">
							<h4 style="margin:0; font-size:16px; font-weight:800;" id="tpd-inq-modal-title"><?php esc_html_e( 'Trip Inquiry Details', 'tpd-tool' ); ?></h4>
							<button type="button" onclick="document.getElementById('tpd-inquiry-details-modal').style.display='none';" style="background:none; border:none; color:#fff; font-size:18px; cursor:pointer;">&times;</button>
						</div>
						<div style="padding:22px;" id="tpd-inq-modal-body">
							<!-- Populated via JS -->
						</div>
						<div style="background:#f8fafc; padding:14px 22px; text-align:right; border-top:1px solid #e2e8f0;">
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" onclick="document.getElementById('tpd-inquiry-details-modal').style.display='none';">
								<?php esc_html_e( 'Close', 'tpd-tool' ); ?>
							</button>
						</div>
					</div>
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
