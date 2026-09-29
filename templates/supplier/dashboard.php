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
						<span><?php esc_html_e( 'Dashboard', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-listing" class="tpd-tab-link" data-view="supp-listing">
						<i class="fa-regular fa-file-lines"></i>
						<span><?php esc_html_e( 'My Listing', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-office" class="tpd-tab-link" data-view="supp-office">
						<i class="fa-solid fa-users"></i>
						<span><?php esc_html_e( 'Virtual Office', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-chat" class="tpd-tab-link" data-view="supp-chat">
						<i class="fa-regular fa-comments"></i>
						<span><?php esc_html_e( 'Chat & Messages', 'tpd-tool' ); ?></span>
						<span class="tpd-badge-counter tpd-badge-red">3</span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-meetings" class="tpd-tab-link" data-view="supp-meetings">
						<i class="fa-regular fa-calendar-check"></i>
						<span><?php esc_html_e( 'Meeting Scheduler', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-resources" class="tpd-tab-link" data-view="supp-resources">
						<i class="fa-regular fa-folder-open"></i>
						<span><?php esc_html_e( 'Resources & Content', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-news" class="tpd-tab-link" data-view="supp-news">
						<i class="fa-regular fa-newspaper"></i>
						<span><?php esc_html_e( 'News & Events', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-analytics" class="tpd-tab-link" data-view="supp-analytics">
						<i class="fa-solid fa-chart-line"></i>
						<span><?php esc_html_e( 'Analytics', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-team" class="tpd-tab-link" data-view="supp-team">
						<i class="fa-solid fa-user-group"></i>
						<span><?php esc_html_e( 'Team Management', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-leads" class="tpd-tab-link" data-view="supp-leads">
						<i class="fa-solid fa-inbox"></i>
						<span><?php esc_html_e( 'Leads & Inquiries', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-promos" class="tpd-tab-link" data-view="supp-promos">
						<i class="fa-solid fa-bullhorn"></i>
						<span><?php esc_html_e( 'Promotions', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-webinars" class="tpd-tab-link" data-view="supp-webinars">
						<i class="fa-solid fa-video"></i>
						<span><?php esc_html_e( 'TARC Talk & Webinars', 'tpd-tool' ); ?></span>
					</a>
				</li>
				<li class="tpd-nav-item">
					<a href="#tpd-supp-settings" class="tpd-tab-link" data-view="supp-settings">
						<i class="fa-solid fa-gear"></i>
						<span><?php esc_html_e( 'Directory Settings', 'tpd-tool' ); ?></span>
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

				<!-- 6 Metric Stat Cards in a row (Matching Image 2) -->
				<div class="tpd-metrics-row-6">
					<!-- 1. Profile Views -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-users text-blue"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['views']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['views']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['views']['trend'] . ' ' . $metrics['views']['sub'] ); ?></span>
					</div>

					<!-- 2. Advisor Conversations -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-comments text-cyan"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['conversations']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['conversations']['label'] ); ?></span>
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

					<!-- 4. Quote Requests -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-paper-plane text-blue"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['quotes']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['quotes']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['quotes']['trend'] ); ?></span>
					</div>

					<!-- 5. Listing Saves -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-star text-gold"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['saves']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['saves']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['saves']['trend'] ); ?></span>
					</div>

					<!-- 6. Bookings Reported -->
					<div class="tpd-metric-widget-mini">
						<div class="tpd-mwm-top">
							<i class="fa-solid fa-chart-simple text-green"></i>
							<span class="tpd-mwm-value"><?php echo esc_html( $metrics['bookings']['value'] ); ?></span>
						</div>
						<span class="tpd-mwm-label"><?php echo esc_html( $metrics['bookings']['label'] ); ?></span>
						<span class="tpd-mwm-trend text-green"><i class="fa-solid fa-arrow-up"></i> <?php echo esc_html( $metrics['bookings']['trend'] ); ?></span>
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

			<!-- VIEW: MY LISTING (Via acf_form or custom editor) -->
			<div id="tpd-supp-listing" class="tpd-tab-panel">
				<div class="tpd-card-box">
					<h3><i class="fa-regular fa-file-lines"></i> <?php esc_html_e( 'Company Listing Editor', 'tpd-tool' ); ?></h3>
					<?php
					if ( $supplier_post_id && function_exists( 'acf_form' ) ) {
						acf_form( array(
							'post_id'      => $supplier_post_id,
							'field_groups' => array( 'group_tpd_tool_supplier' ),
							'submit_value' => __( 'Save Listing Changes', 'tpd-tool' ),
						) );
					} else {
						echo '<p class="text-muted">Listing editor ready.</p>';
					}
					?>
				</div>
			</div>
		</main>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
