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

// Paired supplier_listing CPT post ID
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

// Pull real metadata from User & Paired supplier_listing CPT
$company_name   = ( $supplier_post_id ? get_the_title( $supplier_post_id ) : '' ) ?: get_user_meta( $current_user_id, 'tpd_company_name', true ) ?: __( 'Partner Brand', 'tpd-tool' );
$position_title = get_user_meta( $current_user_id, 'tpd_position_title', true ) ?: ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_position_title', true ) : '' ) ?: __( 'Trade Representative', 'tpd-tool' );
$rep_phone      = get_user_meta( $current_user_id, 'tpd_phone', true ) ?: ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_primary_rep_phone', true ) : '' );
$country        = get_user_meta( $current_user_id, 'tpd_country_of_residence', true ) ?: ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_country_of_residence', true ) : '' );
$headshot_url   = get_user_meta( $current_user_id, 'tpd_headshot_url', true );
$avatar_src     = $headshot_url ?: 'https://ui-avatars.com/api/?name=' . rawurlencode( $full_name ) . '&background=0284c7&color=fff&size=128';

// Listing Showcase fields
$tagline      = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_tagline', true ) : '';
$booking_url  = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_booking_portal_url', true ) : '';
$headquarters = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_headquarters', true ) : '' ) ?: $country;
$rewards      = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_agent_rewards', true ) : '' ) ?: 'Yes';
$ustoa        = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_member_ustoa', true ) : '' ) ?: 'Yes';
$asta         = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_member_asta', true ) : '' ) ?: 'Yes';
$logo_url     = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_logo_url', true ) : '';
$banner_url   = ( $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_banner_url', true ) : '' ) ?: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80';
$video_url    = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_main_video_url', true ) : '';
$promo_title  = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_promo_title', true ) : '';
$promo_desc   = $supplier_post_id ? get_post_meta( $supplier_post_id, 'tpd_promo_desc', true ) : '';
$description  = $supplier_post_id ? get_post_field( 'post_content', $supplier_post_id ) : '';
$public_url   = $supplier_post_id ? get_permalink( $supplier_post_id ) : home_url( '/suppliers/' );

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
			</div>

			<!-- VIEW 2: MY PARTNER LISTINGS (Manage Showcase + Claim Listing + Dynamic ACF/Custom Fields) -->
			<div id="tpd-supp-listings" class="tpd-tab-panel">
				<!-- Top Action Header -->
				<div class="tpd-card-box mb-4" style="background:#0b1526; color:#ffffff; padding:24px 30px; border-radius:14px;">
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
						<div>
							<h3 style="margin:0 0 6px; font-size:20px; font-weight:800; color:#ffffff;"><i class="fa-solid fa-building-flag text-gold"></i> <?php esc_html_e( 'Partner Listings & Virtual Showcase Hub', 'tpd-tool' ); ?></h3>
							<p style="margin:0; font-size:13.5px; color:#cbd5e1;"><?php esc_html_e( 'Manage your company listing, add Virtual Office reps, or claim an existing brand listing.', 'tpd-tool' ); ?></p>
						</div>
						<div style="display:flex; gap:10px;">
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-primary" onclick="jQuery('#tpd-supp-manage-section').slideDown(); jQuery('#tpd-supp-claim-section').slideUp();">
								<i class="fa-solid fa-pen-to-square"></i> <?php esc_html_e( 'Manage My Listing', 'tpd-tool' ); ?>
							</button>
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline-white" onclick="jQuery('#tpd-supp-claim-section').slideDown(); jQuery('#tpd-supp-manage-section').slideUp();">
								<i class="fa-solid fa-hand-holding-hand"></i> <?php esc_html_e( 'Claim Existing Listing', 'tpd-tool' ); ?>
							</button>
						</div>
					</div>
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
								<h3 style="margin:0;"><i class="fa-solid fa-sliders"></i> <?php printf( esc_html__( 'Manage Listing & Showcase (%s)', 'tpd-tool' ), esc_html( $company_name ) ); ?></h3>
								<span class="text-muted" style="font-size:13px;">
									<?php printf( esc_html__( 'Plan Level: %s · Status: Published & Live', 'tpd-tool' ), '<strong>' . esc_html( $plan_info['name'] ) . '</strong>' ); ?>
								</span>
							</div>
							<div>
								<a href="<?php echo esc_url( $public_url ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline" style="text-decoration:none;">
									<i class="fa-solid fa-eye"></i> <?php esc_html_e( 'View Live Showcase', 'tpd-tool' ); ?> &rarr;
								</a>
							</div>
						</div>

						<!-- Editor Sub-Tabs -->
						<div class="tpd-editor-tabs-bar" style="display:flex; gap:8px; border-bottom:2px solid #e2e8f0; margin-bottom:24px; overflow-x:auto;">
							<button type="button" class="tpd-tab-btn active" data-target="set-basic" style="padding:10px 16px; border:none; background:none; font-weight:700; color:#0b1526; border-bottom:3px solid #2563eb; cursor:pointer;">
								<i class="fa-solid fa-info-circle"></i> Basic Info & Custom Fields
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-media" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-images"></i> Images & Media
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-resources" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-folder-open"></i> Advisor Resources
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-team" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-users"></i> Virtual Office Team
							</button>
							<button type="button" class="tpd-tab-btn" data-target="set-promos" style="padding:10px 16px; border:none; background:none; font-weight:600; color:#64748b; cursor:pointer;">
								<i class="fa-solid fa-bullhorn"></i> Promotions & Offers
							</button>
						</div>

						<form id="tpd-supplier-listing-editor-form" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="tpd_save_supplier_listing">
							<input type="hidden" name="listing_id" value="<?php echo esc_attr( $supplier_post_id ); ?>">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

							<!-- Sub-Tab 1: Basic Info & Dynamic ACF/Custom Fields -->
							<div id="set-basic" class="tpd-editor-panel active">
								<div class="tpd-form-grid-2">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Supplier / Company Display Name *', 'tpd-tool' ); ?></label>
										<input type="text" name="company_name" required value="<?php echo esc_attr( $company_name ); ?>" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Tagline / Value Proposition', 'tpd-tool' ); ?></label>
										<input type="text" name="tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'e.g. Bespoke Luxury River & Ocean Voyages', 'tpd-tool' ); ?>" class="tpd-input">
									</div>
								</div>

								<div class="tpd-form-grid-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Advisor Booking / Support Phone *', 'tpd-tool' ); ?></label>
										<input type="tel" name="phone" value="<?php echo esc_attr( $rep_phone ); ?>" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Advisor Booking Portal URL', 'tpd-tool' ); ?></label>
										<input type="url" name="booking_url" value="<?php echo esc_attr( $booking_url ); ?>" placeholder="https://" class="tpd-input">
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Headquarters / Country', 'tpd-tool' ); ?></label>
										<input type="text" name="headquarters" value="<?php echo esc_attr( $headquarters ); ?>" class="tpd-input">
									</div>
								</div>

								<div class="tpd-form-grid-3 mt-3">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Agent Rewards Program?', 'tpd-tool' ); ?></label>
										<select name="rewards" class="tpd-select">
											<option value="Yes" <?php selected( $rewards, 'Yes' ); ?>>Yes — Active Rewards</option>
											<option value="No" <?php selected( $rewards, 'No' ); ?>>No</option>
										</select>
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Member of USTOA?', 'tpd-tool' ); ?></label>
										<select name="ustoa" class="tpd-select">
											<option value="Yes" <?php selected( $ustoa, 'Yes' ); ?>>Yes — Active Member</option>
											<option value="No" <?php selected( $ustoa, 'No' ); ?>>No</option>
										</select>
									</div>
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Member of ASTA?', 'tpd-tool' ); ?></label>
										<select name="asta" class="tpd-select">
											<option value="Yes" <?php selected( $asta, 'Yes' ); ?>>Yes — Active Member</option>
											<option value="No" <?php selected( $asta, 'No' ); ?>>No</option>
										</select>
									</div>
								</div>

								<div class="tpd-form-group mt-3">
									<label><?php esc_html_e( 'Company Overview & Selling Points for Advisors', 'tpd-tool' ); ?></label>
									<textarea name="description" rows="4" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Describe your brand, fleet/properties, advisor commission structure, and key selling points...', 'tpd-tool' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
								</div>

								<!-- Dynamic ACF Field Groups & Admin Custom Fields Bridge -->
								<?php
								if ( class_exists( 'TPD_Tool_CPT' ) ) {
									TPD_Tool_CPT::render_dynamic_fields_html( 'supplier_listing', $supplier_post_id, $current_user_id, 'dashboard' );
								}
								?>
							</div>

							<!-- Sub-Tab 2: Images & Media -->
							<div id="set-media" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-form-grid-2">
									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Custom Showcase Banner Photo', 'tpd-tool' ); ?></label>
										<div style="width:100%; height:110px; border-radius:10px; overflow:hidden; border:1px solid #cbd5e1; margin-bottom:8px;">
											<img id="tpd-supp-banner-preview" src="<?php echo esc_url( $banner_url ); ?>" style="width:100%; height:100%; object-fit:cover;">
										</div>
										<input type="hidden" name="banner_url" id="tpd_supp_banner_url" value="<?php echo esc_attr( $banner_url ); ?>">
										<input type="file" id="tpd-file-supp-banner" accept="image/*" style="display:none;">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="document.getElementById('tpd-file-supp-banner').click();">
											<i class="fa-solid fa-panorama"></i> <?php esc_html_e( 'Upload Banner Image', 'tpd-tool' ); ?>
										</button>
									</div>

									<div class="tpd-form-group">
										<label><?php esc_html_e( 'Main Showcase Video (YouTube or Vimeo URL)', 'tpd-tool' ); ?></label>
										<input type="url" name="video_url" value="<?php echo esc_attr( $video_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." class="tpd-input">
									</div>
								</div>
							</div>

							<!-- Sub-Tab 3: Advisor Resources -->
							<div id="set-resources" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-card-box mb-3" style="background:#f8fafc; padding:16px;">
									<p style="margin:0 0 10px; font-size:13px; font-weight:600;"><?php esc_html_e( 'Upload PDF Brochure, Deck Plan, or Commission Sheet:', 'tpd-tool' ); ?></p>
									<input type="file" id="tpd-file-supp-pdf" accept=".pdf" style="display:none;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" onclick="document.getElementById('tpd-file-supp-pdf').click();">
										<i class="fa-solid fa-cloud-arrow-up"></i> <?php esc_html_e( 'Upload PDF Document', 'tpd-tool' ); ?>
									</button>
								</div>
							</div>

							<!-- Sub-Tab 4: Team Management -->
							<div id="set-team" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-ss-header mb-3">
									<h4 style="margin:0;"><i class="fa-solid fa-user-plus"></i> <?php esc_html_e( 'Virtual Office Team Contacts', 'tpd-tool' ); ?></h4>
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" onclick="jQuery('#tpd-add-rep-modal').slideDown();">
										<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Team Member', 'tpd-tool' ); ?>
									</button>
								</div>

								<div id="tpd-add-rep-modal" class="tpd-card-box mb-3" style="display:none; background:#f0fdf4; border:1px solid #86efac; padding:16px;">
									<h5 style="margin:0 0 12px; color:#166534;"><?php esc_html_e( 'Add Representative to Virtual Office', 'tpd-tool' ); ?></h5>
									<div class="tpd-form-grid-2">
										<input type="text" id="tpd_rep_name" placeholder="Full Name" class="tpd-input tpd-input-sm">
										<input type="text" id="tpd_rep_title" placeholder="Position / Title" class="tpd-input tpd-input-sm">
									</div>
									<div class="tpd-form-grid-2 mt-2">
										<input type="email" id="tpd_rep_email" placeholder="Direct Email" class="tpd-input tpd-input-sm">
										<input type="tel" id="tpd_rep_phone" placeholder="Direct Phone" class="tpd-input tpd-input-sm">
									</div>
									<div class="mt-3" style="text-align:right;">
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="jQuery('#tpd-add-rep-modal').slideUp();">Cancel</button>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary" id="tpd-btn-save-rep">Save Team Member</button>
									</div>
								</div>

								<div id="tpd-team-members-list">
									<?php foreach ( $team_members as $tm ) : ?>
										<div class="tpd-team-card" style="display:flex; align-items:center; gap:12px; padding:12px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:10px;">
											<div style="width:40px; height:40px; border-radius:50%; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;">
												<?php echo esc_html( strtoupper( substr( $tm['name'], 0, 1 ) ) ); ?>
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

							<!-- Sub-Tab 5: Promotions & Offers -->
							<div id="set-promos" class="tpd-editor-panel" style="display:none;">
								<div class="tpd-form-group">
									<label><?php esc_html_e( 'Featured Advisor Incentive / Promotion Title', 'tpd-tool' ); ?></label>
									<input type="text" name="promo_title" value="<?php echo esc_attr( $promo_title ); ?>" placeholder="e.g. Earn 2% Bonus Commission on Q3 Bookings" class="tpd-input">
								</div>
								<div class="tpd-form-group mt-2">
									<label><?php esc_html_e( 'Promotion Details & Booking Code', 'tpd-tool' ); ?></label>
									<textarea name="promo_desc" rows="3" class="tpd-textarea"><?php echo esc_textarea( $promo_desc ); ?></textarea>
								</div>
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

