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
		<nav class="tpd-sb-menu">
			<ul class="tpd-sb-nav-list">
				<li class="tpd-nav-item active">
					<a href="#tpd-view-overview" class="tpd-tab-link" data-view="overview">
						<i class="fa-solid fa-house"></i>
						<span><?php esc_html_e( 'Dashboard', 'tpd-tool' ); ?></span>
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
					<a href="#tpd-view-conversations" class="tpd-tab-link" data-view="conversations">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( 'My Conversations', 'tpd-tool' ); ?></span>
						<span class="tpd-badge-counter tpd-badge-red">3</span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-inquiries" class="tpd-tab-link" data-view="inquiries">
						<i class="fa-solid fa-inbox"></i>
						<span><?php esc_html_e( 'Client Inquiries', 'tpd-tool' ); ?></span>
						<span class="tpd-badge-counter tpd-badge-red">5</span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-meetings" class="tpd-tab-link" data-view="meetings">
						<i class="fa-regular fa-calendar"></i>
						<span><?php esc_html_e( 'My Meetings', 'tpd-tool' ); ?></span>
					</a>
				</li>

				<li class="tpd-nav-divider"></li>

				<li class="tpd-nav-item">
					<a href="#tpd-view-comparison" class="tpd-tab-link" data-view="comparison">
						<i class="fa-solid fa-ship"></i>
						<span><?php esc_html_e( 'Cruise Comparison', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-connect" class="tpd-tab-link" data-view="connect">
						<i class="fa-solid fa-users-rays"></i>
						<span><?php esc_html_e( 'The TARC Connect', 'tpd-tool' ); ?></span>
						<small class="tpd-nav-subtext"><?php esc_html_e( 'Discovery Call Platform', 'tpd-tool' ); ?></small>
					</a>
				</li>

				<li class="tpd-nav-divider"></li>

				<li class="tpd-nav-item">
					<a href="#tpd-view-clients" class="tpd-tab-link" data-view="clients">
						<i class="fa-solid fa-user-group"></i>
						<span><?php esc_html_e( 'My Clients', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-resources" class="tpd-tab-link" data-view="resources">
						<i class="fa-solid fa-book-open"></i>
						<span><?php esc_html_e( 'Resources & Tools', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-events" class="tpd-tab-link" data-view="events">
						<i class="fa-regular fa-calendar-check"></i>
						<span><?php esc_html_e( 'Events & Webinars', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-news" class="tpd-tab-link" data-view="news">
						<i class="fa-solid fa-bullhorn"></i>
						<span><?php esc_html_e( 'TARC News', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-profile" class="tpd-tab-link" data-view="profile">
						<i class="fa-regular fa-user"></i>
						<span><?php esc_html_e( 'My Profile', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-settings" class="tpd-tab-link" data-view="settings">
						<i class="fa-solid fa-gear"></i>
						<span><?php esc_html_e( 'Settings', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-view-help" class="tpd-tab-link" data-view="help">
						<i class="fa-regular fa-circle-question"></i>
						<span><?php esc_html_e( 'Help & Support', 'tpd-tool' ); ?></span>
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

				<!-- Metrics Analytics Row (3 Wide Cards) -->
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

					<!-- Card 2: Messages -->
					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-cyan"><i class="fa-solid fa-comment-dots"></i></div>
						<div class="tpd-mw-body">
							<span class="tpd-mw-value"><?php echo esc_html( $metrics['messages']['value'] ); ?></span>
							<span class="tpd-mw-label"><?php echo esc_html( $metrics['messages']['label'] ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['messages']['trend'] . ' ' . $metrics['messages']['sub'] ); ?></span>
						</div>
					</div>

					<!-- Card 3: Client Inquiries -->
					<div class="tpd-metric-widget">
						<div class="tpd-mw-icon tpd-mw-navy"><i class="fa-solid fa-users"></i></div>
						<div class="tpd-mw-body">
							<span class="tpd-mw-value"><?php echo esc_html( $metrics['client_inquiries']['value'] ); ?></span>
							<span class="tpd-mw-label"><?php echo esc_html( $metrics['client_inquiries']['label'] ); ?></span>
							<span class="tpd-mw-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['client_inquiries']['trend'] . ' ' . $metrics['client_inquiries']['sub'] ); ?></span>
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

			<!-- VIEW: EVENTS & WEBINARS (Tab) -->
			<div id="tpd-view-events" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-regular fa-calendar-check"></i> <?php esc_html_e( 'Events & Webinars Schedule', 'tpd-tool' ); ?></h3>
					<?php echo do_shortcode( '[tpd_events_list]' ); ?>
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
