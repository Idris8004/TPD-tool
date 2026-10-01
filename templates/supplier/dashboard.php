<?php
/**
 * Supplier Portal - Supplier Dashboard
 * Pixel-perfect implementation based on Client Inspiration Design 2 (Sarah Mitchell - AmaWaterways).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_user_id = get_current_user_id();

if ( ! $current_user_id ) {
	$sarah_m = get_user_by( 'login', 'sarah.mitchell' );
	$current_user_id = $sarah_m ? $sarah_m->ID : 1;
}

$user = get_userdata( $current_user_id );
$user_tier = tpd_get_user_tier( $current_user_id );
$first_name = $user->first_name ?: 'Sarah';
$full_name  = $user->display_name ?: 'Sarah Mitchell';

// Supplier post ID
$supplier_post_id = 0;
$query_supp = get_posts( array(
	'post_type'      => 'supplier_listing',
	'posts_per_page' => 1,
	'meta_key'       => 'tpd_assigned_user',
	'meta_value'     => $current_user_id,
	'fields'         => 'ids',
) );
if ( ! empty( $query_supp ) ) {
	$supplier_post_id = $query_supp[0];
}

$metrics = TPD_Tool_Analytics::get_supplier_metrics( $supplier_post_id, $current_user_id );
$recent_convos = TPD_Tool_Chat::get_recent_conversations( $current_user_id );
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
						<span><?php esc_html_e( '1. User Dashboard', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-listings" class="tpd-tab-link" data-view="supp-listings">
						<i class="fa-solid fa-building-flag"></i>
						<span><?php esc_html_e( '2. My Partner Listings', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-chat" class="tpd-tab-link" data-view="supp-chat">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( '3. Chat & Messages', 'tpd-tool' ); ?></span>
						<span class="tpd-badge-counter tpd-badge-red">3</span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-meetings" class="tpd-tab-link" data-view="supp-meetings">
						<i class="fa-regular fa-calendar-check"></i>
						<span><?php esc_html_e( '4. Meeting Requests', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-settings" class="tpd-tab-link" data-view="supp-settings">
						<i class="fa-solid fa-gear"></i>
						<span><?php esc_html_e( '5. Profile Settings', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-partnership" class="tpd-tab-link" data-view="supp-partnership">
						<i class="fa-solid fa-handshake"></i>
						<span><?php esc_html_e( '6. TARC Partnership', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-help" class="tpd-tab-link" data-view="supp-help">
						<i class="fa-regular fa-circle-question"></i>
						<span><?php esc_html_e( '7. Help & Support', 'tpd-tool' ); ?></span>
					</a>
				</li>
			</ul>
		</nav>

		<div class="tpd-sb-bottom-actions">
			<a href="<?php echo esc_url( home_url( '/suppliers/amawaterways/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-outline-white tpd-btn-sm tpd-btn-block mb-3">
				<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View My Public Listing', 'tpd-tool' ); ?>
			</a>
			<div class="tpd-sb-help-prompt">
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
				<button type="button" class="tpd-top-icon-btn" title="Notifications">
					<i class="fa-regular fa-bell"></i>
					<span class="tpd-bell-badge">3</span>
				</button>

				<!-- Company Switcher Dropdown (AmaWaterways) -->
				<div class="tpd-company-pill-dropdown">
					<i class="fa-solid fa-crown text-gold"></i>
					<span class="tpd-company-name">AmaWaterways</span>
					<i class="fa-solid fa-chevron-down"></i>
				</div>

				<!-- User Profile Pill -->
				<div class="tpd-user-pill-dropdown">
					<img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=120&auto=format&fit=crop&q=80" alt="<?php echo esc_attr( $full_name ); ?>" class="tpd-user-avatar-sm">
					<div class="tpd-user-pill-info">
						<span class="tpd-user-name"><?php echo esc_html( $full_name ); ?></span>
						<span class="tpd-user-role-label">Regional BDM · <strong class="tpd-tier-tag"><?php echo esc_html( strtoupper( $user_tier ) ); ?></strong></span>
					</div>
					<i class="fa-solid fa-chevron-down tpd-chevron-icon"></i>
				</div>
			</div>
		</header>

		<main class="tpd-workspace-content">
			<!-- VIEW: OVERVIEW -->
			<div id="tpd-supp-overview" class="tpd-tab-panel active">
				<!-- Welcome Banner (AmaWaterways Banner + River Cruise Ship) -->
				<div class="tpd-hero-welcome-card tpd-supplier-hero-card" style="background-image: linear-gradient(90deg, rgba(11, 21, 38, 0.9) 0%, rgba(11, 21, 38, 0.4) 60%, rgba(11, 21, 38, 0.1) 100%), url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80');">
					<div class="tpd-shc-badge-card">
						<i class="fa-solid fa-crown text-gold fa-2x"></i>
						<h3>AmaWaterways</h3>
						<small>LEADING THE WAY IN RIVER CRUISING</small>
					</div>

					<div class="tpd-shc-text">
						<h1>Welcome back, <span><?php echo esc_html( $first_name ); ?>!</span></h1>
						<p><?php esc_html_e( 'Manage your presence, connect with travel advisors, and grow your business with TARC.', 'tpd-tool' ); ?></p>
					</div>

					<div class="tpd-shc-actions">
						<a href="<?php echo esc_url( home_url( '/suppliers/amawaterways/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline-white">
							<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View Public Listing', 'tpd-tool' ); ?>
						</a>
					</div>
				</div>

				<!-- 4 Metric Stat Cards confirmed by Client (Bookings Reported Removed) -->
				<div class="tpd-metrics-row-4" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; margin:24px 0;">
					<!-- 1. Profile Views -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-users text-blue"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['views']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['views']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['views']['trend'] . ' ' . $metrics['views']['sub'] ); ?></span>
					</div>

					<!-- 2. Active Conversations -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-comments text-cyan"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['conversations']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php esc_html_e( 'Active Conversations', 'tpd-tool' ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['conversations']['trend'] ); ?></span>
					</div>

					<!-- 3. Meeting Requests -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-regular fa-calendar-check text-purple"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['meetings']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['meetings']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['meetings']['trend'] ); ?></span>
					</div>

					<!-- 4. Listing Saves -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-star text-gold"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['saves']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['saves']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['saves']['trend'] ); ?></span>
					</div>
				</div>

				<!-- 3-Column Layout (Matching Image 2) -->
				<div class="tpd-supplier-dashboard-grid-3">
					<!-- COLUMN 1: Virtual Office & Manage Your Content -->
					<div class="tpd-sd-col">
						<!-- Virtual Office Card -->
						<div class="tpd-card-box tpd-virtual-office-card">
							<div class="tpd-box-header-clean">
								<div>
									<h4><?php esc_html_e( 'VIRTUAL OFFICE', 'tpd-tool' ); ?></h4>
									<span class="tpd-online-badge-text"><i class="fa-solid fa-circle text-green"></i> 3 Team Members Online Now</span>
								</div>
								<a href="#tpd-supp-team" class="tpd-clean-link tpd-tab-link" data-view="supp-team"><?php esc_html_e( 'Manage Team', 'tpd-tool' ); ?></a>
							</div>

							<div class="tpd-vo-members-list">
								<!-- Rep 1: Sarah Mitchell -->
								<div class="tpd-vo-member-row">
									<img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&auto=format&fit=crop&q=80" alt="Sarah Mitchell" class="tpd-vom-avatar">
									<div class="tpd-vom-info">
										<strong>Sarah Mitchell</strong>
										<span class="tpd-vom-role">Regional BDM – Southeast US</span>
										<span class="tpd-vom-spec">Specializes in: Luxury, Groups, Europe</span>
									</div>
									<div class="tpd-vom-action">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="2" data-recipient-name="Sarah Mitchell">
											<i class="fa-regular fa-comment"></i> <?php esc_html_e( 'Chat with Sarah', 'tpd-tool' ); ?>
										</button>
									</div>
								</div>

								<!-- Rep 2: Jason Carter -->
								<div class="tpd-vo-member-row">
									<img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&auto=format&fit=crop&q=80" alt="Jason Carter" class="tpd-vom-avatar">
									<div class="tpd-vom-info">
										<strong>Jason Carter</strong>
										<span class="tpd-vom-role">Inside Sales Support</span>
										<span class="tpd-vom-spec">Specializes in: Bookings, Groups</span>
									</div>
									<div class="tpd-vom-action">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-open-chat-btn" data-recipient-id="3" data-recipient-name="Jason Carter">
											<i class="fa-regular fa-comment"></i> <?php esc_html_e( 'Chat with Jason', 'tpd-tool' ); ?>
										</button>
									</div>
								</div>

								<!-- Rep 3: Emily Reynolds -->
								<div class="tpd-vo-member-row">
									<img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80" alt="Emily Reynolds" class="tpd-vom-avatar">
									<div class="tpd-vom-info">
										<strong>Emily Reynolds</strong>
										<span class="tpd-vom-role">National Accounts</span>
										<span class="tpd-vom-spec">Specializes in: Charters, Incentives</span>
									</div>
									<div class="tpd-vom-action">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-open-chat-btn" data-recipient-id="4" data-recipient-name="Emily Reynolds">
											<i class="fa-regular fa-envelope"></i> <?php esc_html_e( 'Send a Message', 'tpd-tool' ); ?>
										</button>
									</div>
								</div>
							</div>
						</div>

						<!-- Manage Your Content (8 Tiles Matching Image 2) -->
						<div class="tpd-card-box tpd-manage-content-card">
							<h4><?php esc_html_e( 'MANAGE YOUR CONTENT', 'tpd-tool' ); ?></h4>
							<div class="tpd-myc-grid-8">
								<a href="#tpd-supp-listing" class="tpd-myc-tile tpd-tab-link" data-view="supp-listing">
									<i class="fa-regular fa-file-lines"></i>
									<strong>Basic Information</strong>
									<p>Update description, destinations, travel types.</p>
								</a>

								<a href="#tpd-supp-listing" class="tpd-myc-tile tpd-tab-link" data-view="supp-listing">
									<i class="fa-regular fa-images"></i>
									<strong>Images & Media</strong>
									<p>Upload photos, videos, brand assets.</p>
								</a>

								<a href="#tpd-supp-resources" class="tpd-myc-tile tpd-tab-link" data-view="supp-resources">
									<i class="fa-regular fa-folder-open"></i>
									<strong>Advisor Resources</strong>
									<p>Add brochures, training, sales tools.</p>
								</a>

								<a href="#tpd-supp-news" class="tpd-myc-tile tpd-tab-link" data-view="supp-news">
									<i class="fa-regular fa-calendar"></i>
									<strong>News & Events</strong>
									<p>Post updates, offers, webinars, FAMs.</p>
								</a>

								<a href="#tpd-supp-team" class="tpd-myc-tile tpd-tab-link" data-view="supp-team">
									<i class="fa-solid fa-users"></i>
									<strong>Team Members</strong>
									<p>Manage Virtual Office team & availability.</p>
								</a>

								<a href="#tpd-supp-listing" class="tpd-myc-tile tpd-tab-link" data-view="supp-listing">
									<i class="fa-solid fa-percent"></i>
									<strong>Booking & Commission</strong>
									<p>Update booking details, commission info.</p>
								</a>

								<a href="#tpd-supp-promos" class="tpd-myc-tile tpd-tab-link" data-view="supp-promos">
									<i class="fa-solid fa-bullhorn"></i>
									<strong>Promotions & Offers</strong>
									<p>Highlight current offers and incentives.</p>
								</a>

								<a href="#tpd-supp-settings" class="tpd-myc-tile tpd-tab-link" data-view="supp-settings">
									<i class="fa-solid fa-link"></i>
									<strong>Integrations</strong>
									<p>Connect tools, calendar, and files.</p>
								</a>
							</div>
						</div>
					</div>

					<!-- COLUMN 2: Recent Conversations (Matching Image 2) -->
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
										<?php if ( $rc['unread'] ) : ?>
											<span class="tpd-rc-unread-dot"></span>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="tpd-rc-footer">
								<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline tpd-btn-block tpd-tab-link" data-view="supp-chat">
									<i class="fa-regular fa-comment-dots"></i> <?php esc_html_e( 'View All Messages', 'tpd-tool' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- COLUMN 3: Profile Status, Office Hours, Quick Actions (Matching Image 2) -->
					<div class="tpd-sd-col">
						<!-- Your Profile Status Card -->
						<div class="tpd-card-box tpd-profile-status-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'YOUR PROFILE STATUS', 'tpd-tool' ); ?></h4>
								<span class="tpd-badge-live"><i class="fa-solid fa-circle"></i> Live & Visible</span>
							</div>

							<p class="tpd-ps-desc"><?php esc_html_e( 'Your listing is active and visible to travel advisors.', 'tpd-tool' ); ?></p>

							<!-- Progress Bar (85% Complete) -->
							<div class="tpd-progress-wrap">
								<div class="tpd-progress-bar">
									<div class="tpd-progress-fill" style="width: 85%;"></div>
								</div>
								<span class="tpd-progress-label">85% Complete</span>
							</div>

							<!-- Checklist -->
							<ul class="tpd-checklist-items">
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Basic Information</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Logo & Images</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Description & Selling Points</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Destinations & Travel Types</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Advisor Resources</li>
								<li class="checked"><i class="fa-solid fa-circle-check"></i> Team Members (Virtual Office)</li>
								<li class="unchecked"><i class="fa-regular fa-circle"></i> Add Latest News or Offer</li>
							</ul>

							<div class="tpd-ps-footer-thumb">
								<img src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=300&auto=format&fit=crop&q=80" alt="Ship preview">
								<a href="#tpd-supp-listing" class="tpd-link-arrow tpd-tab-link" data-view="supp-listing"><?php esc_html_e( 'Edit My Listing', 'tpd-tool' ); ?> &rarr;</a>
							</div>
						</div>

						<!-- Upcoming Office Hours Card -->
						<div class="tpd-card-box tpd-office-hours-card">
							<div class="tpd-box-header-clean">
								<h4><?php esc_html_e( 'UPCOMING OFFICE HOURS', 'tpd-tool' ); ?></h4>
								<a href="#" class="tpd-clean-link"><?php esc_html_e( 'View All', 'tpd-tool' ); ?></a>
							</div>

							<div class="tpd-oh-item">
								<div class="tpd-oh-date-badge">
									<span>OCT</span>
									<strong>10</strong>
								</div>
								<div class="tpd-oh-body">
									<h5>Live Q&A with AmaWaterways</h5>
									<p>Ask us anything! Destination updates, new itineraries, and advisor tips.</p>
									<span class="tpd-oh-time"><i class="fa-regular fa-calendar"></i> Tuesday, Oct 10, 2026 · 12:00 PM - 2:00 PM ET</span>
								</div>
								<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline"><?php esc_html_e( 'Manage', 'tpd-tool' ); ?></button>
							</div>
						</div>

						<!-- Quick Actions (4 Buttons Matching Image 2) -->
						<div class="tpd-card-box tpd-quick-actions-bar">
							<h4><?php esc_html_e( 'QUICK ACTIONS', 'tpd-tool' ); ?></h4>
							<div class="tpd-qa-row-4">
								<a href="#tpd-supp-news" class="tpd-qa-action-btn tpd-tab-link" data-view="supp-news">
									<i class="fa-solid fa-bullhorn"></i>
									<span>Add News/Promotion</span>
								</a>

								<a href="#tpd-supp-resources" class="tpd-qa-action-btn tpd-tab-link" data-view="supp-resources">
									<i class="fa-regular fa-file-pdf"></i>
									<span>Upload Resource</span>
								</a>

								<a href="#tpd-supp-webinars" class="tpd-qa-action-btn tpd-tab-link" data-view="supp-webinars">
									<i class="fa-regular fa-calendar-plus"></i>
									<span>Create Event</span>
								</a>

								<a href="#tpd-supp-listing" class="tpd-qa-action-btn tpd-tab-link" data-view="supp-listing">
									<i class="fa-solid fa-pen-to-square"></i>
									<span>Update Listing</span>
								</a>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- VIEW: CHAT & MESSAGES -->
			<div id="tpd-supp-chat" class="tpd-tab-panel">
				<?php 
				if ( tpd_can_user( 'supplier_chat', $current_user_id ) ) {
					include TPD_TOOL_DIR . 'templates/shared/chat-box.php';
				} else {
					echo TPD_Tool_Tiers::render_locked_card(
						__( 'Advisor Chat is a Pro Feature', 'tpd-tool' ),
						__( 'Live messaging with travel advisors is available to Pro Tier supplier accounts.', 'tpd-tool' )
					);
				}
				?>
			</div>

			<!-- VIEW: MY PARTNER LISTINGS (Tab 2 - Create, Manage, Claim per Client Specification) -->
			<div id="tpd-supp-listings" class="tpd-tab-panel">
				<!-- Top 3 Action Buttons -->
				<div class="tpd-card-box mb-4" style="background:#0b1526; color:#ffffff; padding:24px 30px; border-radius:14px;">
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
						<div>
							<h3 style="margin:0 0 6px; font-size:20px; font-weight:800; color:#ffffff;"><i class="fa-solid fa-building-flag text-gold"></i> <?php esc_html_e( 'Partner Listings & Virtual Showcase Hub', 'tpd-tool' ); ?></h3>
							<p style="margin:0; font-size:13.5px; color:#cbd5e1;"><?php esc_html_e( 'Manage your company listings, invite team reps to your Virtual Office, or claim an existing brand listing.', 'tpd-tool' ); ?></p>
						</div>
						<div style="display:flex; gap:10px;">
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-primary" onclick="jQuery('#tpd-supp-manage-section').slideDown(); jQuery('#tpd-supp-claim-section').slideUp();">
								<i class="fa-solid fa-pen-to-square"></i> <?php esc_html_e( 'Manage Listing', 'tpd-tool' ); ?>
							</button>
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline-white" onclick="jQuery('#tpd-supp-claim-section').slideDown(); jQuery('#tpd-supp-manage-section').slideUp();">
								<i class="fa-solid fa-hand-holding-hand"></i> <?php esc_html_e( 'Claim a Listing', 'tpd-tool' ); ?>
							</button>
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline-white" onclick="alert('Create New Listing wizard ready!');">
								<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Create New Listing', 'tpd-tool' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Section A: CLAIM A LISTING (Search existing directory listings) -->
				<div id="tpd-supp-claim-section" class="tpd-card-box mb-4" style="display:none; border:2px dashed #3b82f6; background:#eff6ff;">
					<div class="tpd-ss-header mb-3">
						<div>
							<h4 style="margin:0; color:#1e40af;"><i class="fa-solid fa-magnifying-glass"></i> <?php esc_html_e( 'Claim an Existing Directory Listing', 'tpd-tool' ); ?></h4>
							<p style="margin:4px 0 0; font-size:13px; color:#3b82f6;">Search for your brand in our directory. Click 'Claim' to request ownership. Our team will verify and approve your request in the Super Admin panel.</p>
						</div>
						<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="jQuery('#tpd-supp-claim-section').slideUp();">Close</button>
					</div>

					<div style="margin-bottom:16px;">
						<input type="text" placeholder="Search directory by brand name (e.g. Four Seasons, Viking, Abercrombie)..." class="tpd-input" id="tpd-claim-search-input">
					</div>

					<table class="tpd-inquiries-table">
						<thead>
							<tr>
								<th>Brand / Company</th>
								<th>Category</th>
								<th>Headquarters</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><strong>Abercrombie & Kent</strong></td>
								<td>Tour Operator / Luxury</td>
								<td>London / Illinois</td>
								<td><button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-btn-claim-modal" data-id="28" data-title="Abercrombie & Kent">Claim Listing</button></td>
							</tr>
							<tr>
								<td><strong>Four Seasons Hotels & Resorts</strong></td>
								<td>Hotels & Luxury Resorts</td>
								<td>Toronto, Canada</td>
								<td><button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-btn-claim-modal" data-id="27" data-title="Four Seasons Hotels & Resorts">Claim Listing</button></td>
							</tr>
							<tr>
								<td><strong>Viking Ocean & River Cruises</strong></td>
								<td>Ocean & River Cruises</td>
								<td>Basel, Switzerland</td>
								<td><button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-btn-claim-modal" data-id="29" data-title="Viking Cruises">Claim Listing</button></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Section B: MANAGE A LISTING (TravPro Reference Tabbed Showcase Editor) -->
				<div id="tpd-supp-manage-section">
					<div class="tpd-card-box">
						<div class="tpd-ss-header mb-4">
							<div>
								<h3 style="margin:0;"><i class="fa-solid fa-sliders"></i> <?php esc_html_e( 'Manage Your Listing & Showcase (AmaWaterways)', 'tpd-tool' ); ?></h3>
								<span class="text-muted" style="font-size:13px;">Plan Level: <strong class="text-gold"><i class="fa-solid fa-crown"></i> Standard Listing</strong> · Status: <span class="text-green font-bold">Published & Live</span></span>
							</div>
							<div>
								<a href="<?php echo esc_url( home_url( '/suppliers/amawaterways/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline">
									<i class="fa-solid fa-eye"></i> View Live Showcase &rarr;
								</a>
							</div>
						</div>

						<!-- Editor Tab Navigation (Matching TravPro Reference) -->
						<div class="tpd-editor-tabs-bar" style="display:flex; gap:8px; border-bottom:2px solid #e2e8f0; margin-bottom:24px; overflow-x:auto;">
							<button type="button" class="tpd-tab-btn active" data-target="set-basic" style="padding:10px 16px; border:none; background:none; font-weight:700; color:#0b1526; border-bottom:3px solid #2563eb; cursor:pointer;">
								<i class="fa-solid fa-info-circle"></i> Basic Info & Logo
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-media" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-images"></i> Images & Media
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-resources" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-folder-open"></i> Advisor Resources & Social Packs
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-team" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-users"></i> Team Management
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-associations" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-certificate"></i> Associations & Conferences
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-promos" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-bullhorn"></i> Promotions & Offers
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-blogs" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-newspaper"></i> Blogs & FAQs
							</button>
						</div>

						<form id="tpd-supplier-listing-editor-form" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="tpd_save_supplier_listing">
							<input type="hidden" name="listing_id" value="<?php echo esc_attr( $supplier_post_id ?: 26 ); ?>">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

							<!-- Tab Content 1: Basic Info & Logo -->
							<div id="set-basic" class="tpd-editor-panel active">
								<div class="tpd-form-grid-2">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Company Display Name *', 'tpd-tool' ); ?></label>
										<input type="text" name="company_name" required value="<?php echo esc_attr( get_the_title( $supplier_post_id ) ?: 'AmaWaterways' ); ?>" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Tagline / Selling Proposition', 'tpd-tool' ); ?></label>
										<input type="text" name="tagline" value="Leading the Way in Luxury River Cruising" class="tpd-input">
									</div>
								</div>

								<div class="tpd-form-grid-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Advisor Booking Phone Number *', 'tpd-tool' ); ?></label>
										<input type="tel" name="phone" value="(800) 626-0126" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Advisor Booking Portal URL *', 'tpd-tool' ); ?></label>
										<input type="url" name="booking_url" value="https://www.amawaterways.com/travel-advisor-portal" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Headquarters Location', 'tpd-tool' ); ?></label>
										<input type="text" name="headquarters" value="Calabasas, CA, USA" class="tpd-input">
									</div>
								</div>

								<!-- Toggles (Rewards, USTOA, ASTA) -->
								<div class="tpd-form-grid-3 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Agent Rewards Program?', 'tpd-tool' ); ?></label>
										<select name="rewards" class="tpd-select">
											<option value="Yes" selected>Yes — Active Rewards</option>
											<option value="No">No</option>
										</select>
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Member of USTOA?', 'tpd-tool' ); ?></label>
										<select name="ustoa" class="tpd-select">
											<option value="Yes" selected>Yes — Active Member</option>
											<option value="No">No</option>
										</select>
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Member of ASTA?', 'tpd-tool' ); ?></label>
										<select name="asta" class="tpd-select">
											<option value="Yes" selected>Yes — Active Member</option>
											<option value="No">No</option>
										</select>
									</div>
								</div>

								<div class="tpd-form-group mt-3">
									<label><?php esc_html_e( 'Company Overview & Selling Points for Advisors', 'tpd-tool' ); ?></label>
									<textarea name="description" rows="4" class="tpd-textarea"><?php echo esc_textarea( get_post_field( 'post_content', $supplier_post_id ) ?: "AmaWaterways operates the finest fleet of luxury river cruise ships on the Danube, Rhine, Douro, Seine, Mekong, and Nile rivers. Offering twin-balcony staterooms, award-winning chef cuisine, complimentary wellness activities, and 100% commission protection for travel advisors." ); ?></textarea>
								</div>
							</div>

							<!-- Tab Content 2: Images & Media -->
							<div id="set-media" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-form-grid-2">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Custom Banner Photo', 'tpd-tool' ); ?></label>
										<div style="width:100%; height:110px; border-radius:10px; overflow:hidden; border:1px solid #cbd5e1; margin-bottom:8px;">
											<img id="tpd-supp-banner-preview" src="https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&auto=format&fit=crop&q=80" style="width:100%; height:100%; object-fit:cover;">
										</div>
										<input type="hidden" name="banner_url" id="tpd_supp_banner_url" value="">
										<input type="file" id="tpd-file-supp-banner" accept="image/*" style="display:none;">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-supp-banner').click();">
											<i class="fa-solid fa-panorama"></i> Change Banner
										</button>
									</div>

									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Main Showcase Video (YouTube or Vimeo URL)', 'tpd-tool' ); ?></label>
										<input type="url" name="video_url" value="https://www.youtube.com/watch?v=dQw4w9WgXcQ" placeholder="https://www.youtube.com/watch?v=..." class="tpd-input">
										<small class="text-muted" style="font-size:12px; margin-top:4px;">Appears on your main showcase page for advisors to preview your brand.</small>
									</div>
								</div>
							</div>

							<!-- Tab Content 3: Advisor Resources & Social Packs -->
							<div id="set-resources" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-ss-header mb-3">
									<h4 style="margin:0;"><i class="fa-regular fa-file-pdf text-red"></i> Downloadable Sales Toolkits & Brochures</h4>
									<span class="text-muted" style="font-size:12px;">Standard: Up to 5 PDFs · Premium: Up to 20 PDFs</span>
								</div>
								<div class="tpd-card-box mb-3" style="background:#f8fafc; padding:16px;">
									<p style="margin:0 0 10px; font-size:13px; font-weight:600;">Upload PDF Brochure or Commission Sheet:</p>
									<input type="file" id="tpd-file-supp-pdf" accept=".pdf" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" onclick="document.getElementById('tpd-file-supp-pdf').click();">
										<i class="fa-solid fa-cloud-arrow-up"></i> Upload PDF Document
									</button>
								</div>

								<!-- Social Pack Submission (Client Requirement) -->
								<div class="tpd-card-box mt-3" style="border:1px solid #e2e8f0; background:#fffbeb; padding:18px;">
									<h4 style="margin:0 0 8px; color:#b45309;"><i class="fa-solid fa-share-nodes"></i> Submit Social Media "Grab-and-Go" Content Pack</h4>
									<p style="font-size:12.5px; color:#92400e; margin:0 0 12px; line-height:1.5;">
										Send 4–8 promotional photos and caption text to the TARC team. We format and distribute them to the advisor community for quick social sharing!
									</p>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue" onclick="alert('Social pack uploader ready!');">
										<i class="fa-solid fa-paper-plane"></i> Send Social Pack to TARC Team
									</button>
								</div>
							</div>

							<!-- Tab Content 4: Team Management -->
							<div id="set-team" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-ss-header mb-3">
									<h4 style="margin:0;"><i class="fa-solid fa-user-plus"></i> Virtual Office Team Contacts</h4>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" onclick="jQuery('#tpd-add-rep-modal').slideDown();">
										<i class="fa-solid fa-plus"></i> Add Team Member
									</button>
								</div>

								<!-- Add Rep Inline Form -->
								<div id="tpd-add-rep-modal" class="tpd-card-box mb-3" style="display:none; background:#f0fdf4; border:1px solid #86efac; padding:16px;">
									<h5 style="margin:0 0 12px; color:#166534;">Invite / Assign Supplier User to Virtual Office</h5>
									<div class="tpd-form-grid-2">
										<input type="text" id="tpd_rep_name" placeholder="Member Full Name (e.g. Jason Carter)" class="tpd-input tpd-input-sm">
										<input type="text" id="tpd_rep_title" placeholder="Title (e.g. Inside Sales Support)" class="tpd-input tpd-input-sm">
									</div>
									<div class="tpd-form-grid-2 mt-2">
										<input type="email" id="tpd_rep_email" placeholder="Direct Email" class="tpd-input tpd-input-sm">
										<input type="tel" id="tpd_rep_phone" placeholder="Direct Phone (optional)" class="tpd-input tpd-input-sm">
									</div>
									<div class="mt-3" style="text-align:right;">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="jQuery('#tpd-add-rep-modal').slideUp();">Cancel</button>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" id="tpd-btn-save-rep">Save Team Member</button>
									</div>
								</div>

								<!-- Team Members List Container -->
								<?php
								$team_members = (array) get_post_meta( $supplier_post_id, '_tpd_team_members', true );
								if ( empty( $team_members ) ) {
									$team_members = array(
										array(
											'name'  => 'Sarah Mitchell',
											'title' => 'Director of Sales - Western Region',
											'email' => 'smitchell@amawaterways.com',
											'phone' => '(800) 626-0126 ext 412',
										),
										array(
											'name'  => 'Marcus Vance',
											'title' => 'Inside Sales Specialist',
											'email' => 'mvance@amawaterways.com',
											'phone' => '(800) 626-0126 ext 418',
										),
									);
								}
								?>
								<div id="tpd-team-members-list">
									<?php foreach ( $team_members as $tm ) : ?>
										<div class="tpd-team-card" style="display:flex; align-items:center; gap:12px; padding:12px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:10px;">
											<div style="width:40px; height:40px; border-radius:50%; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">
												<?php echo esc_html( substr( $tm['name'], 0, 1 ) ); ?>
											</div>
											<div style="flex:1;">
												<h5 style="margin:0; font-size:13.5px; font-weight:700;"><?php echo esc_html( $tm['name'] ); ?></h5>
												<p style="margin:0; font-size:12px; color:#64748b;">
													<?php echo esc_html( $tm['title'] ); ?> · <?php echo esc_html( $tm['email'] ); ?>
													<?php if ( ! empty( $tm['phone'] ) ) : ?> · <?php echo esc_html( $tm['phone'] ); ?><?php endif; ?>
												</p>
											</div>
											<span class="text-green" style="font-size:12px; font-weight:700;"><i class="fa-solid fa-circle"></i> Active</span>
										</div>
									<?php endforeach; ?>
								</div>
							</div>

							<!-- Tab Content 5, 6, 7 -->
							<div id="set-associations" class="tpd-editor-panel" style="display:none;">
								<h4>Associations & Upcoming Conferences</h4>
								<p class="text-muted" style="font-size:13px;">List professional travel associations (e.g. USTOA, CLIA, ASTA) and conferences your team will attend with dates.</p>
							</div>
							<div id="set-promos" class="tpd-editor-panel" style="display:none;">
								<h4>Promotions & Limited-Time Offers</h4>
								<p class="text-muted" style="font-size:13px;">Highlight up to 3 advisor incentives and consumer promotions with expiration dates.</p>
							</div>
							<div id="set-blogs" class="tpd-editor-panel" style="display:none;">
								<h4>Partner Blogs & FAQs</h4>
								<p class="text-muted" style="font-size:13px;">Publish educational articles directly to the TARC Partner Blog and provide answers to common advisor questions.</p>
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
		</main>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
