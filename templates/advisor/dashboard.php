<?php
/**
 * Travel Partner Directory by TARC - Advisor Dashboard
 * Pixel-perfect implementation based on Client Inspiration Design 1 (Steven Gould).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();

// If not logged in, preview as Steven Gould for demonstration, or prompt login
if ( ! $current_user_id ) {
	$steven = get_user_by( 'login', 'steven.gould' );
	$current_user_id = $steven ? $steven->ID : 1;
}

$user = get_userdata( $current_user_id );
$user_tier = tpd_get_user_tier( $current_user_id );
$first_name = $user->first_name ?: 'Steven';
$full_name  = $user->display_name ?: 'Steven Gould';

// Paired advisor post ID
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

$metrics = TPD_Tool_Analytics::get_advisor_metrics( $advisor_post_id, $current_user_id );
$saved_supplier_ids = TPD_Tool_Favorites::get_saved_supplier_ids( $current_user_id );
$events = TPD_Tool_Events::get_upcoming_events( 3 );
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
		<!-- Sidebar Brand Header -->
		<div class="tpd-sidebar-brand">
			<div class="tpd-sb-logo-icon"><i class="fa-solid fa-compass"></i></div>
			<div class="tpd-sb-logo-copy">
				<span class="tpd-sb-title">TRAVEL PARTNER</span>
				<span class="tpd-sb-subtitle">DIRECTORY</span>
				<span class="tpd-sb-by">BY TARC</span>
			</div>
		</div>

		<!-- Navigation Menu -->
		<!-- Navigation Menu (15 Confirmed Tabs) -->
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
						<span class="tpd-badge-counter tpd-badge-red">5</span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-conversations" class="tpd-tab-link" data-view="conversations">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( '6. Chat & Messages', 'tpd-tool' ); ?></span>
						<span class="tpd-badge-counter tpd-badge-red">3</span>
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
						<span><?php esc_html_e( '13. Settings', 'tpd-tool' ); ?></span>
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

		<!-- Bottom Promotional Graphic (Scenic coast) -->
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
				<input type="text" placeholder="<?php esc_attr_e( 'Search suppliers, destinations, or travel types...', 'tpd-tool' ); ?>" class="tpd-top-search-input">
			</div>

			<div class="tpd-topbar-actions">
				<!-- Notification Bell with Badge -->
				<button type="button" class="tpd-top-icon-btn" title="Notifications">
					<i class="fa-regular fa-bell"></i>
					<span class="tpd-bell-badge">3</span>
				</button>

				<!-- User Profile Pill -->
				<div class="tpd-user-pill-dropdown">
					<img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&auto=format&fit=crop&q=80" alt="<?php echo esc_attr( $full_name ); ?>" class="tpd-user-avatar-sm">
					<div class="tpd-user-pill-info">
						<span class="tpd-user-name"><?php echo esc_html( $full_name ); ?></span>
						<span class="tpd-user-role-label"><?php esc_html_e( 'Travel Advisor', 'tpd-tool' ); ?> · <strong class="tpd-tier-tag"><?php echo esc_html( strtoupper( $user_tier ) ); ?></strong></span>
					</div>
					<i class="fa-solid fa-chevron-down tpd-chevron-icon"></i>
				</div>
			</div>
		</header>

		<!-- Workspace / Main View Area -->
		<main class="tpd-workspace-content">
			<!-- VIEW: OVERVIEW (Default Dashboard) -->
			<div id="tpd-view-overview" class="tpd-tab-panel active">
				<!-- Welcome Banner (Scenic European Coastal Landscape) -->
				<div class="tpd-hero-welcome-card" style="background-image: linear-gradient(90deg, rgba(11, 21, 38, 0.85) 0%, rgba(11, 21, 38, 0.45) 50%, rgba(11, 21, 38, 0.15) 100%), url('https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=1600&auto=format&fit=crop&q=80');">
					<div class="tpd-hwc-content">
						<h1 class="tpd-hwc-title">Welcome back, <span><?php echo esc_html( $first_name ); ?>!</span></h1>
						<p class="tpd-hwc-subtitle"><?php esc_html_e( 'Find the right suppliers. Get the answers you need. Help your clients travel better.', 'tpd-tool' ); ?></p>
					</div>
					<div class="tpd-hwc-quote-wrap">
						<span class="tpd-hwc-quote">"Same clients. Bigger possibilities."</span>
					</div>
				</div>

				<!-- Square Ad Space Row (Directly Under Banner per Client Requirement) -->
				<div class="tpd-ad-banner-strip mb-4" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-top:20px;">
					<div class="tpd-ad-square-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; gap:14px; align-items:center; position:relative; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
						<span style="position:absolute; top:8px; right:10px; font-size:9.5px; font-weight:800; background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:4px; text-transform:uppercase;">SPONSORED</span>
						<img src="https://images.unsplash.com/photo-1548574505-5e239809ee19?w=160&auto=format&fit=crop&q=80" alt="AmaWaterways" style="width:72px; height:72px; border-radius:8px; object-fit:cover;">
						<div style="flex:1;">
							<h5 style="margin:0 0 4px; font-size:13.5px; font-weight:700; color:#0f172a;">Earn 18% Commission on 2026 Danube Charters</h5>
							<p style="margin:0 0 6px; font-size:12px; color:#64748b;">AmaWaterways Luxury River Cruises · Limited Time Advisor Incentive</p>
							<a href="#tpd-view-suppliers" class="tpd-tab-link" data-view="suppliers" style="font-size:11.5px; font-weight:700; color:#2563eb; text-decoration:none;">Explore Itineraries &rarr;</a>
						</div>
					</div>

					<div class="tpd-ad-square-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; display:flex; gap:14px; align-items:center; position:relative; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
						<span style="position:absolute; top:8px; right:10px; font-size:9.5px; font-weight:800; background:#e0f2fe; color:#0369a1; padding:2px 6px; border-radius:4px; text-transform:uppercase;">FEATURED</span>
						<img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=160&auto=format&fit=crop&q=80" alt="Four Seasons" style="width:72px; height:72px; border-radius:8px; object-fit:cover;">
						<div style="flex:1;">
							<h5 style="margin:0 0 4px; font-size:13.5px; font-weight:700; color:#0f172a;">Four Seasons Preferred Partner Program</h5>
							<p style="margin:0 0 6px; font-size:12px; color:#64748b;">Daily breakfast, $100 resort credits & VIP perks for your clients</p>
							<a href="#tpd-view-suppliers" class="tpd-tab-link" data-view="suppliers" style="font-size:11.5px; font-weight:700; color:#2563eb; text-decoration:none;">View Partner Desk &rarr;</a>
						</div>
					</div>
				</div>

				<!-- Metrics Analytics Row (3 Key Metrics confirmed by Client) -->
				<div class="tpd-metrics-row-3">
					<!-- Card 1: Profile Views -->
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

					<!-- Card 2: Client Inquiries -->
					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-navy"><i class="fa-solid fa-users"></i></div>
						<div class="tpd-mw-body">
							<span class="tpd-mw-value"><?php echo esc_html( $metrics['client_inquiries']['value'] ); ?></span>
							<span class="tpd-mw-label"><?php echo esc_html( $metrics['client_inquiries']['label'] ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['client_inquiries']['trend'] . ' ' . $metrics['client_inquiries']['sub'] ); ?></span>
						</div>
					</div>

					<!-- Card 3: Supplier Messages -->
					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-cyan"><i class="fa-solid fa-comment-dots"></i></div>
						<div class="tpd-mw-body">
							<span class="tpd-mw-value"><?php echo esc_html( $metrics['messages']['value'] ); ?></span>
							<span class="tpd-mw-label"><?php esc_html_e( 'Supplier Messages', 'tpd-tool' ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['messages']['trend'] . ' ' . $metrics['messages']['sub'] ); ?></span>
						</div>
					</div>
				</div>

				<!-- Two Column Body: Left Main Flow + Right Sidebar Widgets -->
				<div class="tpd-dashboard-columns">
					<!-- LEFT COLUMN -->
					<div class="tpd-dash-col-main">
						<!-- "Find Your Next Supplier" Search Filter Box -->
						<div class="tpd-card-box tpd-supplier-search-box">
							<div class="tpd-ss-header">
								<h3><?php esc_html_e( 'Find Your Next Supplier', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-suppliers" class="tpd-link-arrow tpd-tab-link" data-view="suppliers"><?php esc_html_e( 'Advanced Search', 'tpd-tool' ); ?> &rarr;</a>
							</div>
							<p class="tpd-ss-desc"><?php esc_html_e( 'Search our exclusive supplier directory and connect with the right partners for your clients.', 'tpd-tool' ); ?></p>

							<div class="tpd-ss-search-bar">
								<div class="tpd-ss-input-wrap">
									<i class="fa-solid fa-magnifying-glass"></i>
									<input type="text" placeholder="<?php esc_attr_e( 'Search suppliers, destinations, travel types, or keywords...', 'tpd-tool' ); ?>" id="tpd-live-supplier-search">
								</div>
								<button type="button" class="tpd-btn tpd-btn-darkblue" id="tpd-btn-search-suppliers">
									<?php esc_html_e( 'Search', 'tpd-tool' ); ?>
								</button>
							</div>

							<!-- Filter Dropdowns Row -->
							<div class="tpd-ss-filters-row">
								<div class="tpd-filter-select-pill">
									<i class="fa-solid fa-location-dot"></i>
									<select>
										<option value=""><?php esc_html_e( 'Destination', 'tpd-tool' ); ?></option>
										<option value="europe">Europe</option>
										<option value="africa">Africa</option>
										<option value="alaska">Alaska</option>
										<option value="caribbean">Caribbean</option>
									</select>
									<i class="fa-solid fa-chevron-down"></i>
								</div>

								<div class="tpd-filter-select-pill">
									<i class="fa-solid fa-plane"></i>
									<select>
										<option value=""><?php esc_html_e( 'Travel Type', 'tpd-tool' ); ?></option>
										<option value="river-cruises">River Cruises</option>
										<option value="luxury">Luxury Escapes</option>
										<option value="all-inclusive">All-Inclusive</option>
										<option value="custom">Custom Travel</option>
									</select>
									<i class="fa-solid fa-chevron-down"></i>
								</div>

								<div class="tpd-filter-select-pill">
									<i class="fa-solid fa-building"></i>
									<select>
										<option value=""><?php esc_html_e( 'Supplier Type', 'tpd-tool' ); ?></option>
										<option value="cruise-line">Cruise Line</option>
										<option value="hotel-resort">Hotels & Resorts</option>
										<option value="tour-operator">Tour Operator</option>
									</select>
									<i class="fa-solid fa-chevron-down"></i>
								</div>

								<div class="tpd-filter-select-pill">
									<i class="fa-solid fa-user-check"></i>
									<select>
										<option value=""><?php esc_html_e( 'Client Type', 'tpd-tool' ); ?></option>
										<option value="couples">Couples / Romance</option>
										<option value="groups">Groups</option>
										<option value="families">Families</option>
									</select>
									<i class="fa-solid fa-chevron-down"></i>
								</div>
							</div>
						</div>

						<!-- "Featured Suppliers" Carousel / Grid (Matching Image 1) -->
						<div class="tpd-featured-suppliers-section">
							<div class="tpd-fss-header">
								<h3><?php esc_html_e( 'Featured Suppliers', 'tpd-tool' ); ?></h3>
								<a href="#tpd-view-suppliers" class="tpd-link-arrow tpd-tab-link" data-view="suppliers"><?php esc_html_e( 'View All Suppliers', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-featured-suppliers-grid">
								<!-- Supplier Card 1: AmaWaterways -->
								<div class="tpd-supp-showcase-card">
									<div class="tpd-ssc-thumb" style="background-image: url('https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=500&auto=format&fit=crop&q=80');">
										<span class="tpd-reps-online-badge"><i class="fa-solid fa-circle"></i> 3 Reps Online</span>
									</div>
									<div class="tpd-ssc-body">
										<div class="tpd-ssc-logo-row">
											<span class="tpd-brand-title"><i class="fa-solid fa-crown text-gold"></i> AmaWaterways</span>
										</div>
										<span class="tpd-ssc-category">River Cruises</span>
										<div class="tpd-ssc-tags">
											<span>Europe</span><span>Luxury</span><span>Groups</span>
										</div>
										<div class="tpd-ssc-actions">
											<a href="<?php echo esc_url( home_url( '/suppliers/amawaterways/' ) ); ?>" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'View Profile', 'tpd-tool' ); ?></a>
											<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="2" data-recipient-name="Sarah Mitchell (AmaWaterways)">
												<i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Chat Now', 'tpd-tool' ); ?>
											</button>
										</div>
									</div>
								</div>

								<!-- Supplier Card 2: Four Seasons -->
								<div class="tpd-supp-showcase-card">
									<div class="tpd-ssc-thumb" style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=500&auto=format&fit=crop&q=80');">
										<span class="tpd-reps-online-badge"><i class="fa-solid fa-circle"></i> 2 Reps Online</span>
									</div>
									<div class="tpd-ssc-body">
										<div class="tpd-ssc-logo-row">
											<span class="tpd-brand-title"><i class="fa-solid fa-tree text-green"></i> Four Seasons</span>
										</div>
										<span class="tpd-ssc-category">Hotels & Resorts</span>
										<div class="tpd-ssc-tags">
											<span>Luxury</span><span>All-Inclusive</span><span>Couples</span>
										</div>
										<div class="tpd-ssc-actions">
											<a href="#" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'View Profile', 'tpd-tool' ); ?></a>
											<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="1" data-recipient-name="Four Seasons Trade Desk">
												<i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Chat Now', 'tpd-tool' ); ?>
											</button>
										</div>
									</div>
								</div>

								<!-- Supplier Card 3: Abercrombie & Kent -->
								<div class="tpd-supp-showcase-card">
									<div class="tpd-ssc-thumb" style="background-image: url('https://images.unsplash.com/photo-1516426122078-c23e76319801?w=500&auto=format&fit=crop&q=80');">
										<span class="tpd-reps-online-badge"><i class="fa-solid fa-circle"></i> 1 Rep Online</span>
									</div>
									<div class="tpd-ssc-body">
										<div class="tpd-ssc-logo-row">
											<span class="tpd-brand-title"><strong>AK</strong> Abercrombie & Kent</span>
										</div>
										<span class="tpd-ssc-category">Tour Operator</span>
										<div class="tpd-ssc-tags">
											<span>Africa</span><span>Luxury</span><span>Custom Travel</span>
										</div>
										<div class="tpd-ssc-actions">
											<a href="#" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'View Profile', 'tpd-tool' ); ?></a>
											<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="1" data-recipient-name="A&K Safari Specialist">
												<i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Chat Now', 'tpd-tool' ); ?>
											</button>
										</div>
									</div>
								</div>

								<!-- Supplier Card 4: Viking -->
								<div class="tpd-supp-showcase-card">
									<div class="tpd-ssc-thumb" style="background-image: url('https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=500&auto=format&fit=crop&q=80');">
										<span class="tpd-reps-online-badge"><i class="fa-solid fa-circle"></i> 4 Reps Online</span>
									</div>
									<div class="tpd-ssc-body">
										<div class="tpd-ssc-logo-row">
											<span class="tpd-brand-title"><i class="fa-solid fa-ship text-red"></i> Viking</span>
										</div>
										<span class="tpd-ssc-category">Ocean & River Cruises</span>
										<div class="tpd-ssc-tags">
											<span>Europe</span><span>River</span><span>Cultural</span>
										</div>
										<div class="tpd-ssc-actions">
											<a href="#" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'View Profile', 'tpd-tool' ); ?></a>
											<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="1" data-recipient-name="Viking Cruise Consultant">
												<i class="fa-solid fa-comment"></i> <?php esc_html_e( 'Chat Now', 'tpd-tool' ); ?>
											</button>
										</div>
									</div>
								</div>
							</div>
						</div>

						<!-- TARC News Row (Bottom of Image 1) -->
						<div class="tpd-news-section-card">
							<div class="tpd-fss-header">
								<h3><?php esc_html_e( 'TARC News', 'tpd-tool' ); ?></h3>
								<a href="#" class="tpd-link-arrow"><?php esc_html_e( 'View All News', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-news-cards-grid">
								<article class="tpd-news-mini-card">
									<div class="tpd-nmc-thumb" style="background-image: url('https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=400&auto=format&fit=crop&q=80');">
										<span class="tpd-news-date">OCT 10, 2026</span>
									</div>
									<div class="tpd-nmc-body">
										<h4>New Supplier Partnership Spotlight: Explore More of Italy</h4>
										<p>We're excited to welcome new partners offering exclusive bespoke experiences for TARC advisors.</p>
									</div>
								</article>

								<article class="tpd-news-mini-card">
									<div class="tpd-nmc-thumb" style="background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&auto=format&fit=crop&q=80');">
										<span class="tpd-news-date">OCT 7, 2026</span>
									</div>
									<div class="tpd-nmc-body">
										<h4>Updates to the Virtual Office</h4>
										<p>New features are now live to help you connect with supplier partners even faster via instant messaging.</p>
									</div>
								</article>

								<article class="tpd-news-mini-card">
									<div class="tpd-nmc-thumb" style="background-image: url('https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=400&auto=format&fit=crop&q=80');">
										<span class="tpd-news-date">OCT 5, 2026</span>
									</div>
									<div class="tpd-nmc-body">
										<h4>TARC Advisor Success Stories</h4>
										<p>See how fellow advisors are using the directory to grow their high-yield bookings and client retention.</p>
									</div>
								</article>
							</div>
						</div>
					</div>

					<!-- RIGHT SIDEBAR COLUMN -->
					<aside class="tpd-dash-col-side">
						<!-- 1. Quick Actions (6 Grid Tiles) -->
						<div class="tpd-card-box tpd-quick-actions-box">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'Quick Actions', 'tpd-tool' ); ?></h4>
								<a href="#" class="tpd-clean-link"><?php esc_html_e( 'Customize', 'tpd-tool' ); ?></a>
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

								<a href="#" class="tpd-qa-tile tpd-open-inquiry-modal">
									<i class="fa-solid fa-file-invoice-dollar"></i>
									<span><?php esc_html_e( 'Request a Quote', 'tpd-tool' ); ?></span>
								</a>

								<a href="#tpd-view-meetings" class="tpd-tab-link tpd-qa-tile" data-view="meetings">
									<i class="fa-regular fa-calendar-plus"></i>
									<span><?php esc_html_e( 'Schedule a Meeting', 'tpd-tool' ); ?></span>
								</a>

								<a href="#tpd-view-saved" class="tpd-tab-link tpd-qa-tile" data-view="saved">
									<i class="fa-regular fa-heart"></i>
									<span><?php esc_html_e( 'View Saved Suppliers', 'tpd-tool' ); ?></span>
								</a>

								<a href="#tpd-view-resources" class="tpd-tab-link tpd-qa-tile" data-view="resources">
									<i class="fa-regular fa-folder-open"></i>
									<span><?php esc_html_e( 'Access Resources', 'tpd-tool' ); ?></span>
								</a>
							</div>
						</div>

						<!-- 2. Saved Suppliers Mini List -->
						<div class="tpd-card-box tpd-saved-suppliers-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'Saved Suppliers', 'tpd-tool' ); ?></h4>
								<a href="#tpd-view-saved" class="tpd-clean-link tpd-tab-link" data-view="saved"><?php esc_html_e( 'View All', 'tpd-tool' ); ?> &rarr;</a>
							</div>

							<div class="tpd-saved-suppliers-list">
								<div class="tpd-saved-supp-item">
									<div class="tpd-ssi-icon"><i class="fa-solid fa-crown text-gold"></i></div>
									<div class="tpd-ssi-info">
										<strong>AmaWaterways</strong>
										<span>River Cruises</span>
									</div>
									<button type="button" class="tpd-btn-dots"><i class="fa-solid fa-ellipsis-vertical"></i></button>
								</div>

								<div class="tpd-saved-supp-item">
									<div class="tpd-ssi-icon"><i class="fa-solid fa-tree text-green"></i></div>
									<div class="tpd-ssi-info">
										<strong>Four Seasons</strong>
										<span>Hotels & Resorts</span>
									</div>
									<button type="button" class="tpd-btn-dots"><i class="fa-solid fa-ellipsis-vertical"></i></button>
								</div>

								<div class="tpd-saved-supp-item">
									<div class="tpd-ssi-icon"><strong>AK</strong></div>
									<div class="tpd-ssi-info">
										<strong>Abercrombie & Kent</strong>
										<span>Tour Operator</span>
									</div>
									<button type="button" class="tpd-btn-dots"><i class="fa-solid fa-ellipsis-vertical"></i></button>
								</div>

								<div class="tpd-saved-supp-item">
									<div class="tpd-ssi-icon"><i class="fa-solid fa-ship text-red"></i></div>
									<div class="tpd-ssi-info">
										<strong>Viking</strong>
										<span>Ocean & River Cruises</span>
									</div>
									<button type="button" class="tpd-btn-dots"><i class="fa-solid fa-ellipsis-vertical"></i></button>
								</div>

								<div class="tpd-saved-supp-item">
									<div class="tpd-ssi-icon"><i class="fa-solid fa-umbrella-beach text-blue"></i></div>
									<div class="tpd-ssi-info">
										<strong>Sandals</strong>
										<span>All-Inclusive Resorts</span>
									</div>
									<button type="button" class="tpd-btn-dots"><i class="fa-solid fa-ellipsis-vertical"></i></button>
								</div>
							</div>
						</div>

						<!-- 3. Upcoming Events & Webinars -->
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

			<!-- VIEW: CONVERSATIONS / CHAT (Tab matching "My Conversations (3)") -->
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

			<!-- VIEW: SAVED SUPPLIERS (Tab) -->
			<div id="tpd-view-saved" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header">
						<h3><i class="fa-regular fa-bookmark"></i> <?php esc_html_e( 'Your Saved Suppliers', 'tpd-tool' ); ?></h3>
						<span class="text-muted"><?php esc_html_e( 'Access contact details and rep online status for your bookmarked suppliers.', 'tpd-tool' ); ?></span>
					</div>
					<div class="tpd-featured-suppliers-grid mt-4">
						<?php echo do_shortcode( '[tpd_saved_suppliers]' ); ?>
					</div>
				</div>
			</div>

			<!-- VIEW: CLIENT INQUIRIES (Tab) -->
			<div id="tpd-view-inquiries" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header">
						<h3><i class="fa-solid fa-inbox"></i> <?php esc_html_e( 'Client Trip Inquiries & Leads', 'tpd-tool' ); ?></h3>
					</div>
					<p class="text-muted"><?php esc_html_e( 'Inquiries submitted directly by travelers seeking your specialized planning services.', 'tpd-tool' ); ?></p>
					<?php
					$inquiries = get_posts( array( 'post_type' => 'tpd_inquiry', 'posts_per_page' => 10 ) );
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

			<!-- VIEW: EVENTS & WEBINARS (Tab 11) -->
			<div id="tpd-view-events" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-regular fa-calendar-check"></i> <?php esc_html_e( 'Upcoming Events & Webinars Schedule', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Combined live calendar pulling from Events Directory and TARC Talk masterclasses.', 'tpd-tool' ); ?></p>
					<?php echo do_shortcode( '[tpd_events_list]' ); ?>
				</div>
			</div>

			<!-- VIEW: MY PROFILE LISTING (Tab 2 - Frontend Profile Editor) -->
			<div id="tpd-view-profile" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<div class="tpd-ss-header mb-3">
						<div>
							<h3><i class="fa-regular fa-id-card"></i> <?php esc_html_e( 'My Profile Listing & Public Showcase', 'tpd-tool' ); ?></h3>
							<p class="text-muted" style="margin:4px 0 0;"><?php esc_html_e( 'Manage your headshot, agency branding, bio, and travel specialties displayed to travelers and supplier BDMs.', 'tpd-tool' ); ?></p>
						</div>
						<div class="tpd-profile-status-badge">
							<span style="background:#ecfdf5; color:#047857; font-size:12px; font-weight:700; padding:4px 12px; border-radius:999px; border:1px solid #a7f3d0;">
								<i class="fa-solid fa-circle-check"></i> Profile Active & Verified
							</span>
						</div>
					</div>

					<form id="tpd-advisor-profile-editor-form" method="post" enctype="multipart/form-data">
						<input type="hidden" name="action" value="tpd_save_advisor_profile">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

						<!-- Media & Visual Identity -->
						<div class="tpd-editor-section" style="border:1px solid #e2e8f0; border-radius:14px; padding:22px; margin-bottom:24px; background:#f8fafc;">
							<h4 style="margin:0 0 16px; font-size:15px; font-weight:700; color:#0b1526;"><i class="fa-solid fa-camera"></i> Profile Picture & Visual Branding</h4>
							
							<div style="display:grid; grid-template-columns: auto 1fr 1fr; gap:24px; align-items:start;">
								<!-- Headshot -->
								<div style="text-align:center;">
									<label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;">Profile Photo</label>
									<img id="tpd-preview-headshot" src="<?php echo esc_url( get_user_meta( $current_user_id, 'tpd_headshot_url', true ) ?: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=160&auto=format&fit=crop&q=80' ); ?>" style="width:110px; height:110px; border-radius:50%; object-fit:cover; border:3px solid #ffffff; box-shadow:0 4px 12px rgba(0,0,0,0.1); margin-bottom:10px;">
									<input type="hidden" name="headshot_url" id="tpd_headshot_url" value="<?php echo esc_attr( get_user_meta( $current_user_id, 'tpd_headshot_url', true ) ); ?>">
									<input type="file" id="tpd-file-headshot" accept="image/*" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-headshot').click();">
										<i class="fa-solid fa-arrow-up-from-bracket"></i> Change Photo
									</button>
								</div>

								<!-- Agency Logo -->
								<div>
									<label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;">Agency Logo</label>
									<div style="width:100%; height:90px; border:2px dashed #cbd5e1; border-radius:10px; display:flex; align-items:center; justify-content:center; background:#ffffff; overflow:hidden;">
										<img id="tpd-preview-logo" src="<?php echo esc_url( get_user_meta( $current_user_id, 'tpd_logo_url', true ) ?: '' ); ?>" style="<?php echo get_user_meta( $current_user_id, 'tpd_logo_url', true ) ? 'max-height:80px;' : 'display:none;'; ?>">
										<span id="tpd-logo-placeholder" style="<?php echo get_user_meta( $current_user_id, 'tpd_logo_url', true ) ? 'display:none;' : ''; ?> font-size:12px; color:#94a3b8;"><i class="fa-regular fa-image"></i> Upload Agency Logo</span>
									</div>
									<input type="hidden" name="logo_url" id="tpd_logo_url" value="<?php echo esc_attr( get_user_meta( $current_user_id, 'tpd_logo_url', true ) ); ?>">
									<input type="file" id="tpd-file-logo" accept="image/*" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline mt-2" onclick="document.getElementById('tpd-file-logo').click();">
										<i class="fa-solid fa-upload"></i> Upload Logo
									</button>
								</div>

								<!-- Banner Selection -->
								<div>
									<label style="display:block; font-size:12px; font-weight:700; margin-bottom:8px;">Profile Banner Header</label>
									<div style="width:100%; height:90px; border-radius:10px; overflow:hidden; border:1px solid #cbd5e1; position:relative;">
										<img id="tpd-preview-banner" src="<?php echo esc_url( get_user_meta( $current_user_id, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1516483638261-f4dbaf036963?w=800&auto=format&fit=crop&q=80' ); ?>" style="width:100%; height:100%; object-fit:cover;">
									</div>
									<input type="hidden" name="banner_url" id="tpd_banner_url" value="<?php echo esc_attr( get_user_meta( $current_user_id, 'tpd_banner_url', true ) ); ?>">
									<input type="file" id="tpd-file-banner" accept="image/*" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline mt-2" onclick="document.getElementById('tpd-file-banner').click();">
										<i class="fa-solid fa-panorama"></i> Upload Custom Banner
									</button>
								</div>
							</div>
						</div>

						<!-- Core Details -->
						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="first_name" required value="<?php echo esc_attr( $user->first_name ?: 'Steven' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="last_name" required value="<?php echo esc_attr( $user->last_name ?: 'Gould' ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Agency Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="agency_name" required value="<?php echo esc_attr( get_post_meta( $advisor_post_id, 'tpd_agency_name', true ) ?: 'Gould Travel Group' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
								<input type="tel" name="phone" required value="<?php echo esc_attr( get_post_meta( $advisor_post_id, 'tpd_phone', true ) ?: '(555) 482-9102' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
								<input type="email" name="email" required value="<?php echo esc_attr( $user->user_email ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'City, State / Region, Country *', 'tpd-tool' ); ?></label>
								<input type="text" name="location" required value="<?php echo esc_attr( get_post_meta( $advisor_post_id, 'tpd_location', true ) ?: 'Boston, MA, United States' ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Agency Website Link', 'tpd-tool' ); ?></label>
								<input type="url" name="website" value="<?php echo esc_attr( get_post_meta( $advisor_post_id, 'tpd_website', true ) ?: 'https://gouldtravel.com' ); ?>" class="tpd-input">
							</div>
						</div>

						<!-- Bio / About -->
						<div class="tpd-form-group mt-3">
							<label><?php esc_html_e( 'About Your Travel Practice & Client Philosophy', 'tpd-tool' ); ?></label>
							<textarea name="bio" rows="4" class="tpd-textarea"><?php echo esc_textarea( get_post_field( 'post_content', $advisor_post_id ) ?: "With over 12 years of luxury travel advisory experience, Steven Gould specializes in bespoke European river itineraries, custom Mediterranean charters, and private villa bookings. Steven works closely with luxury supplier partners to secure exclusive amenities, private shore excursions, and VIP perks for high-end leisure travelers." ); ?></textarea>
						</div>

						<!-- Travel Specialties (Max 5 for Basic, Max 10 for Premium) -->
						<div class="tpd-form-group mt-3">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
								<label class="font-bold"><?php esc_html_e( 'Travel Specialties: Destinations', 'tpd-tool' ); ?></label>
								<small class="text-muted"><?php echo ( 'premium' === $user_tier ) ? 'Premium Plan: Max 10 selections' : 'Basic/Standard: Max 5 selections'; ?></small>
							</div>
							<div class="tpd-checkbox-grid">
								<label class="tpd-pill-checkbox"><input type="checkbox" name="destinations[]" value="Western Europe" checked> <span>Western Europe</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="destinations[]" value="Mediterranean" checked> <span>Mediterranean</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="destinations[]" value="Danube River" checked> <span>Danube River</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="destinations[]" value="Caribbean"> <span>Caribbean</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="destinations[]" value="Alaska"> <span>Alaska</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="destinations[]" value="Africa & Safari"> <span>Africa & Safari</span></label>
							</div>
						</div>

						<div class="tpd-form-group mt-3">
							<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
								<label class="font-bold"><?php esc_html_e( 'Travel Specialties: Styles', 'tpd-tool' ); ?></label>
								<small class="text-muted"><?php echo ( 'premium' === $user_tier ) ? 'Premium Plan: Max 10 selections' : 'Basic/Standard: Max 5 selections'; ?></small>
							</div>
							<div class="tpd-checkbox-grid">
								<label class="tpd-pill-checkbox"><input type="checkbox" name="travel_styles[]" value="River Cruises" checked> <span>River Cruises</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="travel_styles[]" value="Luxury Ocean" checked> <span>Luxury Ocean</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="travel_styles[]" value="Custom Tailored" checked> <span>Custom Tailored</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="travel_styles[]" value="All-Inclusive"> <span>All-Inclusive</span></label>
								<label class="tpd-pill-checkbox"><input type="checkbox" name="travel_styles[]" value="Romance & Honeymoon"> <span>Romance & Honeymoon</span></label>
							</div>
						</div>

						<div id="tpd-advisor-profile-status" class="tpd-reg-status" style="display:none; margin-top:16px;"></div>

						<div class="mt-4" style="text-align:right;">
							<button type="submit" class="tpd-btn tpd-btn-darkblue" id="tpd-save-advisor-profile-btn" style="padding:12px 28px;">
								<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Profile Changes', 'tpd-tool' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<!-- VIEW: CRUISE COMPARISON (Tab 9 - Coming Soon per Client) -->
			<div id="tpd-view-comparison" class="tpd-tab-panel">
				<div class="tpd-card-box text-center" style="padding:60px 30px;">
					<i class="fa-solid fa-ship" style="font-size:52px; color:#2563eb; margin-bottom:16px;"></i>
					<h2 style="font-size:24px; font-weight:800; color:#0b1526;"><?php esc_html_e( 'Cruise Comparison Engine', 'tpd-tool' ); ?></h2>
					<span style="background:#fef3c7; color:#b45309; font-size:12px; font-weight:800; padding:4px 14px; border-radius:999px; text-transform:uppercase;">Coming Soon in Prototype 2</span>
					<p style="color:#64748b; max-width:540px; margin:16px auto 24px; line-height:1.5;">
						Compare river and ocean luxury cruise lines side-by-side, evaluating ship capacities, inclusions, commission protection, and current promotions for your clients.
					</p>
					<button type="button" class="tpd-btn tpd-btn-outline" onclick="alert('The Cruise Comparison tool is currently in development for Prototype 2!');">
						<i class="fa-regular fa-bell"></i> <?php esc_html_e( 'Notify Me When Available', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>

			<!-- VIEW: THE TARC CONNECT / DISCOVERY CALL (Tab 10 - Coming Soon per Client) -->
			<div id="tpd-view-connect" class="tpd-tab-panel">
				<div class="tpd-card-box text-center" style="padding:60px 30px;">
					<i class="fa-solid fa-users-rays" style="font-size:52px; color:#8b5cf6; margin-bottom:16px;"></i>
					<h2 style="font-size:24px; font-weight:800; color:#0b1526;"><?php esc_html_e( 'The TARC Connect — Discovery Call Platform', 'tpd-tool' ); ?></h2>
					<span style="background:#fef3c7; color:#b45309; font-size:12px; font-weight:800; padding:4px 14px; border-radius:999px; text-transform:uppercase;">Coming Soon in Prototype 2</span>
					<p style="color:#64748b; max-width:540px; margin:16px auto 24px; line-height:1.5;">
						Seamlessly book 1-on-1 discovery consultations with verified supplier BDMs and sales directors to discuss group contracts and custom traveler itineraries.
					</p>
					<button type="button" class="tpd-btn tpd-btn-outline" onclick="alert('The Discovery Call Platform is currently in development for Prototype 2!');">
						<i class="fa-regular fa-calendar-plus"></i> <?php esc_html_e( 'Request Early Access', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>

			<!-- VIEW: AD SPACE (Tab 15 per Client) -->
			<div id="tpd-view-ads" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-solid fa-rectangle-ad"></i> <?php esc_html_e( 'Featured Partner Ad Placements', 'tpd-tool' ); ?></h3>
					<p class="text-muted"><?php esc_html_e( 'Explore sponsored campaigns and exclusive partner promotions curated for TPD advisors.', 'tpd-tool' ); ?></p>
					
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px; margin-top:20px;">
						<div class="tpd-card-box" style="border:1px solid #e2e8f0; background:#f8fafc; padding:20px;">
							<span class="tpd-badge-tag" style="background:#fef3c7; color:#b45309; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700;">PROMOTIONAL INCENTIVE</span>
							<h4 style="margin:10px 0 6px;">AmaWaterways 2026 River Charters</h4>
							<p style="font-size:13px; color:#64748b;">Earn 18% protected advisor commission + $100 onboard credit per stateroom.</p>
							<a href="#tpd-view-suppliers" class="tpd-tab-link tpd-btn tpd-btn-xs tpd-btn-darkblue" data-view="suppliers">View Partner Listing</a>
						</div>

						<div class="tpd-card-box" style="border:1px solid #e2e8f0; background:#f8fafc; padding:20px;">
							<span class="tpd-badge-tag" style="background:#e0f2fe; color:#0369a1; font-size:11px; padding:3px 8px; border-radius:4px; font-weight:700;">PREFERRED PARTNER</span>
							<h4 style="margin:10px 0 6px;">Four Seasons Private Retreats</h4>
							<p style="font-size:13px; color:#64748b;">Luxury villa collections worldwide with dedicated concierge support.</p>
							<a href="#tpd-view-suppliers" class="tpd-tab-link tpd-btn tpd-btn-xs tpd-btn-darkblue" data-view="suppliers">View Partner Listing</a>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>

<!-- Global Chat Drawer / Modal Container -->
<div id="tpd-chat-drawer-container" style="display:none;"></div>

<?php wp_footer(); ?>
</body>
</html>
