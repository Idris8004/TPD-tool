<?php
/**
 * Dedicated Super Admin Control Hub for TPD Tool
 * Matches reference admin layout (Dashboard KPIs with Last 7 Days, Last 10 Advisors/Suppliers,
 * Last 10 Advisor Plan List, Pro Users table with View/Edit/Delete/Promote/Demote/Add/Export,
 * Supplier Users table, Dynamic Plan Builder, Payment Gateway Integration, Form & Custom Fields Builder,
 * Content Manager, and Ad Space Configurator).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_in_admin = is_admin();

if ( ! current_user_can( 'manage_options' ) ) {
	if ( ! $is_in_admin ) {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Super Admin Hub | Travel Partner Directory', 'tpd-tool' ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body class="tpd-app-body" style="background: #f8fafc; padding: 40px 20px;">
			<div style="max-width: 560px; margin: 60px auto; padding: 36px; text-align: center; background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 4px 25px rgba(0,0,0,0.06);">
				<i class="fa-solid fa-shield-halved" style="font-size: 48px; color: #d97706; margin-bottom: 16px;"></i>
				<h2 style="font-size: 22px; font-weight: 800; color: #0b1526;"><?php esc_html_e( 'Administrator Access Required', 'tpd-tool' ); ?></h2>
				<p style="color: #64748b; font-size: 14px; margin: 12px 0 24px; line-height: 1.5;"><?php esc_html_e( 'Please log in with an administrator account to access the TPD Super Admin Control Hub.', 'tpd-tool' ); ?></p>
				<a href="<?php echo esc_url( wp_login_url( home_url( '/tpd-admin/' ) ) ); ?>" class="tpd-btn tpd-btn-darkblue" style="padding: 12px 24px; text-decoration:none;">
					<i class="fa-solid fa-right-to-bracket"></i> <?php esc_html_e( 'Log In as Administrator', 'tpd-tool' ); ?>
				</a>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		return;
	} else {
		wp_die( esc_html__( 'Unauthorized access.', 'tpd-tool' ) );
	}
}

if ( ! $is_in_admin ) {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php esc_html_e( 'Super Admin Hub | Travel Partner Directory', 'tpd-tool' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body class="tpd-app-body" style="background: #f1f5f9; margin: 0;">
	<?php
}

// Gather Real Platform Data & 7-Day Metrics
$seven_days_ago = strtotime( '-7 days' );

$advisors = get_users( array(
	'role'    => 'travel_advisor',
	'orderby' => 'registered',
	'order'   => 'DESC',
) );

$suppliers = get_users( array(
	'role'    => 'supplier',
	'orderby' => 'registered',
	'order'   => 'DESC',
) );

$blogs = get_posts( array(
	'post_type'      => 'supplier_blog',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
) );

$events = get_posts( array(
	'post_type'      => 'tpd_event',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
) );

$supplier_listings = get_posts( array(
	'post_type'      => 'supplier_listing',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
) );

// Calculate Last 7 Days Counts & Plan Counts
$adv_7d  = 0;
$supp_7d = 0;
$blog_7d = 0;
$ev_7d   = 0;

$plan_totals = array( 'basic' => 0, 'standard' => 0, 'premium' => 0 );
$plan_7d     = array( 'basic' => 0, 'standard' => 0, 'premium' => 0 );

foreach ( $advisors as $u ) {
	$reg_ts = strtotime( $u->user_registered );
	$is_7d  = ( $reg_ts >= $seven_days_ago );
	if ( $is_7d ) {
		$adv_7d++;
	}
	$t = tpd_get_user_tier( $u->ID );
	if ( ! isset( $plan_totals[ $t ] ) ) {
		$plan_totals[ $t ] = 0;
		$plan_7d[ $t ]     = 0;
	}
	$plan_totals[ $t ]++;
	if ( $is_7d ) {
		$plan_7d[ $t ]++;
	}
}

foreach ( $suppliers as $u ) {
	if ( strtotime( $u->user_registered ) >= $seven_days_ago ) {
		$supp_7d++;
	}
}

foreach ( $blogs as $b ) {
	if ( strtotime( $b->post_date ) >= $seven_days_ago ) {
		$blog_7d++;
	}
}

foreach ( $events as $ev ) {
	if ( strtotime( $ev->post_date ) >= $seven_days_ago ) {
		$ev_7d++;
	}
}

$all_plans           = TPD_Tool_Settings::get_plans();
$payment_settings    = TPD_Tool_Settings::get_payment_settings();
$subscriptions       = TPD_Tool_Settings::get_subscriptions( 15 );
$form_cfg            = TPD_Tool_Settings::get_form_config();
$custom_fields       = TPD_Tool_Settings::get_custom_fields();
$ads_cfg             = TPD_Tool_Settings::get_ad_banners();
$faqs                = TPD_Tool_Settings::get_faqs();
$all_features        = TPD_Tool_Tiers::get_all_features();
$current_permissions = get_option( TPD_Tool_Tiers::OPTION_PERMISSIONS, array() );
$claims              = class_exists( 'TPD_Tool_Profile_Editor' ) ? TPD_Tool_Profile_Editor::get_claims() : array();
?>

<style>
	/* Dedicated Admin Shell Matching Client Reference Screenshots */
	.tpd-sa-shell { display: flex; min-height: 100vh; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #1e293b; }
	.tpd-sa-sidebar { width: 250px; background: #ffffff; border-right: 1px solid #e2e8f0; flex-shrink: 0; display: flex; flex-direction: column; }
	.tpd-sa-brand { background: #164e87; color: #ffffff; padding: 18px 20px; display: flex; align-items: center; gap: 12px; }
	.tpd-sa-brand i { font-size: 24px; color: #38bdf8; }
	.tpd-sa-brand-text strong { display: block; font-size: 13.5px; letter-spacing: 0.4px; }
	.tpd-sa-brand-text small { font-size: 10.5px; color: #bae6fd; }
	.tpd-sa-nav { list-style: none; padding: 12px 0; margin: 0; flex: 1; }
	.tpd-sa-nav li a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #334155; text-decoration: none; font-size: 13.5px; font-weight: 600; border-left: 4px solid transparent; transition: all 0.15s; }
	.tpd-sa-nav li a:hover { background: #f8fafc; color: #164e87; }
	.tpd-sa-nav li.active a { background: #164e87; color: #ffffff; border-left-color: #38bdf8; }
	.tpd-sa-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
	.tpd-sa-topbar { background: #164e87; color: #ffffff; padding: 14px 28px; display: flex; justify-content: space-between; align-items: center; }
	.tpd-sa-content { padding: 26px 28px; }
	.tpd-sa-pane { display: none; }
	.tpd-sa-pane.active { display: block; }
	/* Blue KPI Cards matching Reference Image 2 */
	.tpd-sa-kpi-row-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 18px; }
	.tpd-sa-kpi-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 24px; }
	.tpd-sa-blue-card { background: linear-gradient(135deg, #2b6cb0 0%, #1e4e8c 100%); color: #ffffff; border-radius: 10px; padding: 18px 20px; box-shadow: 0 4px 12px rgba(22, 78, 135, 0.15); }
	.tpd-sa-bc-title { font-size: 15px; font-weight: 700; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid rgba(255,255,255,0.18); padding-bottom: 8px; }
	.tpd-sa-bc-stats { display: flex; justify-content: space-between; align-items: flex-end; }
	.tpd-sa-bc-main-num { font-size: 28px; font-weight: 800; line-height: 1; }
	.tpd-sa-bc-7d { text-align: right; font-size: 12px; color: #e0f2fe; }
	.tpd-sa-bc-7d strong { display: block; font-size: 18px; color: #ffffff; }
	/* Tables matching Reference Image 1 & 2 */
	.tpd-sa-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); }
	.tpd-sa-card-title { font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 16px; }
	.tpd-sa-table { width: 100%; border-collapse: collapse; font-size: 13px; }
	.tpd-sa-table th { background: #164e87; color: #ffffff; padding: 11px 14px; text-align: left; font-weight: 700; }
	.tpd-sa-table td { padding: 11px 14px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
	.tpd-sa-table tr:nth-child(even) td { background: #f8fafc; }
	/* Action Buttons (Eye, Pencil, Trash) matching Reference Image 1 */
	.tpd-act-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 5px; border: none; color: #ffffff; cursor: pointer; font-size: 13px; margin-right: 4px; }
	.tpd-act-view { background: #1d4ed8; }
	.tpd-act-edit { background: #0d9488; }
	.tpd-act-del  { background: #dc2626; }
	.tpd-status-pill { display: inline-block; padding: 4px 10px; border-radius: 5px; font-size: 11.5px; font-weight: 700; cursor: pointer; border: none; }
	.tpd-status-pill.active { background: #15803d; color: #ffffff; }
	.tpd-status-pill.inactive { background: #b91c1c; color: #ffffff; }
	/* Modal Overlay */
	.tpd-sa-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 20px; }
	.tpd-sa-modal-backdrop.open { display: flex !important; }
	.tpd-sa-modal { background: #ffffff; border-radius: 14px; max-width: 680px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.25); }
	.tpd-sa-blue-card.tpd-sa-jump-pane { cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease; }
	.tpd-sa-blue-card.tpd-sa-jump-pane:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(22, 78, 135, 0.25); }
	@media (max-width: 1100px) { .tpd-sa-kpi-row-4, .tpd-sa-kpi-row-3 { grid-template-columns: 1fr 1fr; } .tpd-sa-shell { flex-direction: column; } .tpd-sa-sidebar { width: 100%; } }
</style>

<input type="hidden" id="tpd_sa_admin_nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_admin_nonce' ) ); ?>">

<div class="tpd-sa-shell">
	<!-- LEFT SIDEBAR (Matching Old Admin Panel Screenshot) -->
	<aside class="tpd-sa-sidebar">
		<div class="tpd-sa-brand">
			<i class="fa-solid fa-compass"></i>
			<div class="tpd-sa-brand-text">
				<strong>TRAVEL PARTNER</strong>
				<small>DIRECTORY BY TARC</small>
			</div>
		</div>

		<ul class="tpd-sa-nav">
			<li class="active">
				<a href="#sa-dashboard" class="tpd-sa-nav-link" data-pane="sa-dashboard">
					<i class="fa-solid fa-gauge-high"></i> <span><?php esc_html_e( 'Dashboard', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-pro-users" class="tpd-sa-nav-link" data-pane="sa-pro-users">
					<i class="fa-solid fa-user-tie"></i> <span><?php esc_html_e( 'Pro Users (Advisors)', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-supplier-users" class="tpd-sa-nav-link" data-pane="sa-supplier-users">
					<i class="fa-solid fa-building-user"></i> <span><?php esc_html_e( 'Supplier Users', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-suppliers-cpt" class="tpd-sa-nav-link" data-pane="sa-suppliers-cpt">
					<i class="fa-solid fa-building-flag"></i> <span><?php esc_html_e( 'Suppliers & Claims', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-plans" class="tpd-sa-nav-link" data-pane="sa-plans">
					<i class="fa-solid fa-crown"></i> <span><?php esc_html_e( 'Plans & Payments', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-forms" class="tpd-sa-nav-link" data-pane="sa-forms">
					<i class="fa-solid fa-sliders"></i> <span><?php esc_html_e( 'Forms & Custom Fields', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-blogs" class="tpd-sa-nav-link" data-pane="sa-blogs">
					<i class="fa-solid fa-newspaper"></i> <span><?php esc_html_e( 'Blogs & Articles', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-events" class="tpd-sa-nav-link" data-pane="sa-events">
					<i class="fa-regular fa-calendar-days"></i> <span><?php esc_html_e( 'Events & Webinars', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-faqs" class="tpd-sa-nav-link" data-pane="sa-faqs">
					<i class="fa-regular fa-circle-question"></i> <span><?php esc_html_e( 'FAQs & Help', 'tpd-tool' ); ?></span>
				</a>
			</li>
			<li>
				<a href="#sa-ads" class="tpd-sa-nav-link" data-pane="sa-ads">
					<i class="fa-solid fa-rectangle-ad"></i> <span><?php esc_html_e( 'Ad Space & Banners', 'tpd-tool' ); ?></span>
				</a>
			</li>
		</ul>
	</aside>

	<!-- MAIN CONTENT -->
	<div class="tpd-sa-main">
		<!-- Top Header Bar -->
		<header class="tpd-sa-topbar">
			<div style="display:flex; align-items:center; gap:14px;">
				<h3 style="margin:0; font-size:17px; font-weight:700; color:#fff;"><?php esc_html_e( 'TPD Super Admin Control Hub', 'tpd-tool' ); ?></h3>
			</div>
			<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
				<a href="<?php echo esc_url( home_url( '/advisor-registration/?preview_form=1' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline-white" style="text-decoration:none;">
					<i class="fa-solid fa-user-plus"></i> <?php esc_html_e( 'Advisor Form', 'tpd-tool' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/supplier-registration/?preview_form=1' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline-white" style="text-decoration:none;">
					<i class="fa-solid fa-building"></i> <?php esc_html_e( 'Supplier Form', 'tpd-tool' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline-white" style="text-decoration:none;">
					<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Advisor Portal', 'tpd-tool' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline-white" style="text-decoration:none;">
					<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Supplier Portal', 'tpd-tool' ); ?>
				</a>
			</div>
		</header>

		<div class="tpd-sa-content">

			<!-- =========================================================================
			     PANE 1: DASHBOARD OVERVIEW (Matching Reference Image media_1791045494351.png)
			     ========================================================================= -->
			<div id="sa-dashboard" class="tpd-sa-pane active">
				<!-- Row 1: 4 Blue KPI Cards (Total + Last 7 Days) -->
				<div class="tpd-sa-kpi-row-4">
					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-pro-users" title="<?php esc_attr_e( 'Click to manage Travel Advisors', 'tpd-tool' ); ?>">
						<div class="tpd-sa-bc-title"><i class="fa-solid fa-user-tie"></i> <?php esc_html_e( 'Travel Advisors', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo count( $advisors ); ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo esc_html( $adv_7d ); ?></strong>
							</div>
						</div>
					</div>

					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-supplier-users" title="<?php esc_attr_e( 'Click to manage Supplier Users', 'tpd-tool' ); ?>">
						<div class="tpd-sa-bc-title"><i class="fa-solid fa-building-user"></i> <?php esc_html_e( 'Supplier Users', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo count( $suppliers ); ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo esc_html( $supp_7d ); ?></strong>
							</div>
						</div>
					</div>

					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-blogs" title="<?php esc_attr_e( 'Click to manage Blogs & Articles', 'tpd-tool' ); ?>">
						<div class="tpd-sa-bc-title"><i class="fa-solid fa-newspaper"></i> <?php esc_html_e( 'Blogs', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo count( $blogs ); ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo esc_html( $blog_7d ); ?></strong>
							</div>
						</div>
					</div>

					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-events" title="<?php esc_attr_e( 'Click to manage Events & Webinars', 'tpd-tool' ); ?>">
						<div class="tpd-sa-bc-title"><i class="fa-regular fa-calendar-check"></i> <?php esc_html_e( 'Events', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo count( $events ); ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo esc_html( $ev_7d ); ?></strong>
							</div>
						</div>
					</div>
				</div>

				<!-- Row 2: 3 Plan KPI Cards (Basic, Standard, Premium with Last 7 Days) -->
				<div class="tpd-sa-kpi-row-3">
					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-plans">
						<div class="tpd-sa-bc-title"><i class="fa-solid fa-layer-group"></i> <?php esc_html_e( 'Basic Plan', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo isset( $plan_totals['basic'] ) ? (int) $plan_totals['basic'] : 0; ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo isset( $plan_7d['basic'] ) ? (int) $plan_7d['basic'] : 0; ?></strong>
							</div>
						</div>
					</div>

					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-plans">
						<div class="tpd-sa-bc-title"><i class="fa-solid fa-gem"></i> <?php esc_html_e( 'Standard Plan', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo isset( $plan_totals['standard'] ) ? (int) $plan_totals['standard'] : 0; ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo isset( $plan_7d['standard'] ) ? (int) $plan_7d['standard'] : 0; ?></strong>
							</div>
						</div>
					</div>

					<div class="tpd-sa-blue-card tpd-sa-jump-pane" data-pane="sa-plans">
						<div class="tpd-sa-bc-title"><i class="fa-solid fa-crown"></i> <?php esc_html_e( 'Premium Plan', 'tpd-tool' ); ?></div>
						<div class="tpd-sa-bc-stats">
							<span class="tpd-sa-bc-main-num"><?php echo isset( $plan_totals['premium'] ) ? (int) $plan_totals['premium'] : 0; ?></span>
							<div class="tpd-sa-bc-7d">
								<?php esc_html_e( 'Last 7 Days', 'tpd-tool' ); ?>
								<strong><?php echo isset( $plan_7d['premium'] ) ? (int) $plan_7d['premium'] : 0; ?></strong>
							</div>
						</div>
					</div>
				</div>

				<!-- Row 3: Two Side-by-Side Tables (Last 10 Advisor List & Last 10 Suppliers List) -->
				<div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
					<div class="tpd-sa-card">
						<h4 class="tpd-sa-card-title"><?php esc_html_e( 'Last 10 Advisor List', 'tpd-tool' ); ?></h4>
						<table class="tpd-sa-table">
							<thead>
								<tr>
									<th>S.No.</th>
									<th>Name</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$sno = 1;
								foreach ( array_slice( $advisors, 0, 10 ) as $adv ) :
								?>
									<tr>
										<td><?php echo esc_html( $sno++ ); ?></td>
										<td><strong><?php echo esc_html( $adv->display_name ); ?></strong></td>
										<td><?php echo esc_html( gmdate( 'd M Y', strtotime( $adv->user_registered ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<div class="tpd-sa-card">
						<h4 class="tpd-sa-card-title"><?php esc_html_e( 'Last 10 Suppliers List', 'tpd-tool' ); ?></h4>
						<table class="tpd-sa-table">
							<thead>
								<tr>
									<th>S.No.</th>
									<th>Name</th>
									<th>Date</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$sno_s = 1;
								foreach ( array_slice( $suppliers, 0, 10 ) as $sup ) :
									$c_name = get_user_meta( $sup->ID, 'tpd_company_name', true ) ?: $sup->display_name;
								?>
									<tr>
										<td><?php echo esc_html( $sno_s++ ); ?></td>
										<td><strong><?php echo esc_html( $c_name ); ?></strong> <small style="color:#64748b;">(<?php echo esc_html( $sup->display_name ); ?>)</small></td>
										<td><?php echo esc_html( gmdate( 'd M Y', strtotime( $sup->user_registered ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Row 4: Last 10 Advisor Plan List (Matching Reference Image 2 Bottom Table) -->
				<div class="tpd-sa-card">
					<h4 class="tpd-sa-card-title"><?php esc_html_e( 'Last 10 Advisor Plan List', 'tpd-tool' ); ?></h4>
					<table class="tpd-sa-table">
						<thead>
							<tr>
								<th>S.No.</th>
								<th>Users</th>
								<th>Plan</th>
								<th>Type</th>
								<th>Amount</th>
								<th>Date</th>
								<th>Expiry</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$sub_i = 1;
							foreach ( array_slice( $subscriptions, 0, 10 ) as $sub ) :
							?>
								<tr>
									<td><?php echo esc_html( $sub_i++ ); ?></td>
									<td><strong><?php echo esc_html( $sub['user_name'] ); ?></strong></td>
									<td><?php echo esc_html( $sub['plan_name'] ); ?></td>
									<td><?php echo esc_html( $sub['billing_type'] ); ?></td>
									<td><strong><?php echo esc_html( $sub['amount'] ); ?></strong></td>
									<td><?php echo esc_html( $sub['date'] ); ?></td>
									<td><?php echo esc_html( $sub['expiry'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 2: PRO USERS / ADVISOR LIST (Matching Reference Image media_1791045478158.png)
			     ========================================================================= -->
			<div id="sa-pro-users" class="tpd-sa-pane">
				<div class="tpd-sa-card">
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:20px;">
						<h3 style="margin:0; font-size:20px; font-weight:800;"><?php esc_html_e( 'Advisor List (Pro Users)', 'tpd-tool' ); ?></h3>
						<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
							<input type="text" id="tpd-sa-search-advisors" placeholder="<?php esc_attr_e( 'Search...', 'tpd-tool' ); ?>" class="tpd-input" style="width:240px; padding:8px 12px;">
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" id="tpd-btn-open-add-advisor" style="background:#164e87;">
								<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Advisor', 'tpd-tool' ); ?>
							</button>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'tpd_action', 'export_advisors_csv' ), 'tpd_export_advisors', 'nonce' ) ); ?>" class="tpd-btn tpd-btn-sm" style="background:#164e87; color:#fff; text-decoration:none; padding:9px 16px; border-radius:8px; font-weight:700;">
								<i class="fa-solid fa-file-export"></i> <?php esc_html_e( 'Export', 'tpd-tool' ); ?>
							</a>
						</div>
					</div>

					<table class="tpd-sa-table" id="tpd-sa-advisors-table">
						<thead>
							<tr>
								<th>S.No.</th>
								<th>Name</th>
								<th>Plan Name (Promote / Demote)</th>
								<th>Email Id</th>
								<th>Username</th>
								<th>Agency Name</th>
								<th>Status</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$idx = 1;
							foreach ( $advisors as $adv ) :
								$uid    = $adv->ID;
								$tier   = tpd_get_user_tier( $uid );
								$status = get_user_meta( $uid, 'tpd_account_status', true ) ?: 'active';
								$ag_nm  = get_user_meta( $uid, 'tpd_agency_name', true );
								if ( ! $ag_nm ) {
									$p = get_posts( array( 'post_type' => 'travel_advisor', 'posts_per_page' => 1, 'meta_key' => 'tpd_assigned_user', 'meta_value' => $uid, 'fields' => 'ids' ) );
									if ( ! empty( $p ) ) {
										$ag_nm = get_post_meta( $p[0], 'tpd_agency_name', true );
									}
								}
							?>
								<tr id="sa-user-row-<?php echo esc_attr( $uid ); ?>">
									<td><?php echo esc_html( $idx++ ); ?></td>
									<td><strong><?php echo esc_html( $adv->display_name ); ?></strong></td>
									<td>
										<select class="tpd-select tpd-select-sm tpd-tier-select" data-user-id="<?php echo esc_attr( $uid ); ?>" style="max-width:170px; font-weight:600; padding:5px 8px;">
											<?php foreach ( $all_plans as $p_slug => $p_info ) : ?>
												<option value="<?php echo esc_attr( $p_slug ); ?>" <?php selected( $tier, $p_slug ); ?>>
													<?php echo esc_html( $p_info['name'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><?php echo esc_html( $adv->user_email ); ?></td>
									<td><?php echo esc_html( $adv->user_login ); ?></td>
									<td><?php echo esc_html( $ag_nm ?: '—' ); ?></td>
									<td>
										<button type="button" class="tpd-status-pill <?php echo esc_attr( $status ); ?> tpd-toggle-user-status" data-user-id="<?php echo esc_attr( $uid ); ?>">
											<?php echo esc_html( ucfirst( $status ) ); ?>
										</button>
									</td>
									<td style="white-space:nowrap;">
										<button type="button" class="tpd-act-btn tpd-act-view tpd-btn-view-user" data-user-id="<?php echo esc_attr( $uid ); ?>" title="View Details"><i class="fa-regular fa-eye"></i></button>
										<button type="button" class="tpd-act-btn tpd-act-edit tpd-btn-edit-user" data-user-id="<?php echo esc_attr( $uid ); ?>" title="Edit User"><i class="fa-solid fa-pen-to-square"></i></button>
										<button type="button" class="tpd-act-btn tpd-act-del tpd-btn-delete-user" data-user-id="<?php echo esc_attr( $uid ); ?>" title="Delete User"><i class="fa-regular fa-trash-can"></i></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 3: SUPPLIER USERS (Manage Supplier Representatives, Plans, View/Edit/Delete)
			     ========================================================================= -->
			<div id="sa-supplier-users" class="tpd-sa-pane">
				<div class="tpd-sa-card">
					<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; margin-bottom:20px;">
						<h3 style="margin:0; font-size:20px; font-weight:800;"><?php esc_html_e( 'Supplier Users List', 'tpd-tool' ); ?></h3>
						<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
							<input type="text" id="tpd-sa-search-suppliers" placeholder="<?php esc_attr_e( 'Search suppliers...', 'tpd-tool' ); ?>" class="tpd-input" style="width:240px; padding:8px 12px;">
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" id="tpd-btn-open-add-supplier" style="background:#164e87;">
								<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Supplier User', 'tpd-tool' ); ?>
							</button>
							<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'tpd_action', 'export_suppliers_csv' ), 'tpd_export_suppliers', 'nonce' ) ); ?>" class="tpd-btn tpd-btn-sm" style="background:#164e87; color:#fff; text-decoration:none; padding:9px 16px; border-radius:8px; font-weight:700;">
								<i class="fa-solid fa-file-export"></i> <?php esc_html_e( 'Export', 'tpd-tool' ); ?>
							</a>
						</div>
					</div>

					<table class="tpd-sa-table" id="tpd-sa-suppliers-table">
						<thead>
							<tr>
								<th>S.No.</th>
								<th>Name</th>
								<th>Supplier / Company Name</th>
								<th>Position/Title</th>
								<th>Plan Name</th>
								<th>Email Id</th>
								<th>Username</th>
								<th>Country</th>
								<th>Status</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$s_idx = 1;
							foreach ( $suppliers as $sup ) :
								$uid    = $sup->ID;
								$tier   = tpd_get_user_tier( $uid );
								$status = get_user_meta( $uid, 'tpd_account_status', true ) ?: 'active';
								$comp   = get_user_meta( $uid, 'tpd_company_name', true );
								$pos    = get_user_meta( $uid, 'tpd_position_title', true );
								$cntry  = get_user_meta( $uid, 'tpd_country_of_residence', true ) ?: 'United States';
								if ( ! $comp ) {
									$sp_p = get_posts( array( 'post_type' => 'supplier_listing', 'posts_per_page' => 1, 'meta_key' => 'tpd_assigned_user', 'meta_value' => $uid, 'fields' => 'ids' ) );
									if ( ! empty( $sp_p ) ) {
										$comp = get_the_title( $sp_p[0] );
									}
								}
							?>
								<tr id="sa-user-row-<?php echo esc_attr( $uid ); ?>">
									<td><?php echo esc_html( $s_idx++ ); ?></td>
									<td><strong><?php echo esc_html( $sup->display_name ); ?></strong></td>
									<td><strong><?php echo esc_html( $comp ?: '—' ); ?></strong></td>
									<td><?php echo esc_html( $pos ?: '—' ); ?></td>
									<td>
										<select class="tpd-select tpd-select-sm tpd-tier-select" data-user-id="<?php echo esc_attr( $uid ); ?>" style="max-width:160px; font-weight:600; padding:5px 8px;">
											<?php foreach ( $all_plans as $p_slug => $p_info ) : ?>
												<option value="<?php echo esc_attr( $p_slug ); ?>" <?php selected( $tier, $p_slug ); ?>>
													<?php echo esc_html( $p_info['name'] ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td><?php echo esc_html( $sup->user_email ); ?></td>
									<td><?php echo esc_html( $sup->user_login ); ?></td>
									<td><?php echo esc_html( $cntry ); ?></td>
									<td>
										<button type="button" class="tpd-status-pill <?php echo esc_attr( $status ); ?> tpd-toggle-user-status" data-user-id="<?php echo esc_attr( $uid ); ?>">
											<?php echo esc_html( ucfirst( $status ) ); ?>
										</button>
									</td>
									<td style="white-space:nowrap;">
										<button type="button" class="tpd-act-btn tpd-act-view tpd-btn-view-user" data-user-id="<?php echo esc_attr( $uid ); ?>" title="View Details"><i class="fa-regular fa-eye"></i></button>
										<button type="button" class="tpd-act-btn tpd-act-edit tpd-btn-edit-user" data-user-id="<?php echo esc_attr( $uid ); ?>" title="Edit Supplier"><i class="fa-solid fa-pen-to-square"></i></button>
										<button type="button" class="tpd-act-btn tpd-act-del tpd-btn-delete-user" data-user-id="<?php echo esc_attr( $uid ); ?>" title="Delete Supplier"><i class="fa-regular fa-trash-can"></i></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 4: SUPPLIER LISTINGS CPT & PENDING BRAND CLAIMS
			     ========================================================================= -->
			<div id="sa-suppliers-cpt" class="tpd-sa-pane">
				<!-- Pending Claims Queue -->
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><i class="fa-solid fa-clipboard-check text-gold"></i> <?php esc_html_e( 'Pending Brand Listing Claims Queue', 'tpd-tool' ); ?></h3>
					<table class="tpd-sa-table">
						<thead>
							<tr>
								<th>Listing / Brand</th>
								<th>Claiming Supplier User</th>
								<th>Date Submitted</th>
								<th>Verification Notes</th>
								<th>Status</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $claims as $lid => $c ) :
								$claimer = get_userdata( $c['user_id'] );
								$listing_title = get_the_title( $lid ) ?: ( 'Listing #' . $lid );
								$status = isset( $c['status'] ) ? $c['status'] : 'pending';
							?>
								<tr id="claim-row-<?php echo esc_attr( $lid ); ?>">
									<td><strong><?php echo esc_html( $listing_title ); ?></strong></td>
									<td><?php echo $claimer ? esc_html( $claimer->display_name . ' (' . $claimer->user_email . ')' ) : 'User #' . esc_html( $c['user_id'] ); ?></td>
									<td><?php echo esc_html( $c['date'] ); ?></td>
									<td><?php echo esc_html( $c['notes'] ); ?></td>
									<td><span id="claim-status-<?php echo esc_attr( $lid ); ?>" style="font-weight:700; text-transform:uppercase;"><?php echo esc_html( $status ); ?></span></td>
									<td>
										<?php if ( 'pending' === $status ) : ?>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary tpd-claim-action-btn" data-id="<?php echo esc_attr( $lid ); ?>" data-decision="approve" style="background:#16a34a;">Approve</button>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-claim-action-btn" data-id="<?php echo esc_attr( $lid ); ?>" data-decision="reject" style="color:#dc2626;">Reject</button>
										<?php else : ?>
											<span class="text-muted">Reviewed</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- All Supplier Directory Listings -->
				<div class="tpd-sa-card">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
						<h3 class="tpd-sa-card-title" style="margin:0;"><?php esc_html_e( 'All Supplier Directory Listings (CPT)', 'tpd-tool' ); ?></h3>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=supplier_listing' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-outline" style="text-decoration:none;">
							<i class="fa-brands fa-wordpress"></i> <?php esc_html_e( 'Open in WP Admin CPT Editor', 'tpd-tool' ); ?>
						</a>
					</div>
					<table class="tpd-sa-table">
						<thead>
							<tr>
								<th>ID</th>
								<th>Company / Brand</th>
								<th>Linked User</th>
								<th>Spotlight / Featured</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $supplier_listings as $sl ) :
								$sl_uid  = (int) get_post_meta( $sl->ID, 'tpd_assigned_user', true );
								$sl_user = $sl_uid ? get_userdata( $sl_uid ) : null;
								$is_feat = ( 'yes' === get_post_meta( $sl->ID, 'tpd_featured_supplier', true ) );
							?>
								<tr id="sa-post-row-<?php echo esc_attr( $sl->ID ); ?>">
									<td>#<?php echo esc_html( $sl->ID ); ?></td>
									<td><strong><?php echo esc_html( $sl->post_title ); ?></strong></td>
									<td><?php echo $sl_user ? esc_html( $sl_user->display_name ) : 'Unassigned'; ?></td>
									<td>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-toggle-featured-btn" data-post-id="<?php echo esc_attr( $sl->ID ); ?>" style="background:<?php echo $is_feat ? '#fef3c7' : '#f1f5f9'; ?>; color:<?php echo $is_feat ? '#b45309' : '#64748b'; ?>; border:1px solid #cbd5e1;">
											<i class="fa-solid fa-star"></i> <?php echo $is_feat ? 'Featured Spotlight' : 'Standard'; ?>
										</button>
									</td>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $sl->ID ) ); ?>" class="tpd-act-btn tpd-act-edit" style="text-decoration:none;" title="Edit in WP Admin"><i class="fa-solid fa-pen"></i></a>
										<button type="button" class="tpd-act-btn tpd-act-del tpd-delete-post-btn" data-post-id="<?php echo esc_attr( $sl->ID ); ?>"><i class="fa-regular fa-trash-can"></i></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 5: DYNAMIC MEMBERSHIP PLAN BUILDER & PAYMENT INTEGRATION
			     ========================================================================= -->
			<div id="sa-plans" class="tpd-sa-pane">
				<!-- Dynamic Membership Plans Manager -->
				<div class="tpd-sa-card">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
						<div>
							<h3 class="tpd-sa-card-title" style="margin:0;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'Membership Plan Builder (Create, Edit, Price & Gate Plans)', 'tpd-tool' ); ?></h3>
							<p style="margin:4px 0 0; font-size:13px; color:#64748b;"><?php esc_html_e( 'Create custom plans or edit existing plans. Changes immediately update the Registration Wizards and User Dashboards.', 'tpd-tool' ); ?></p>
						</div>
					</div>

					<!-- Existing Plans Grid -->
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:18px; margin-bottom:24px;">
						<?php foreach ( $all_plans as $p_slug => $p_info ) : ?>
							<div style="border:2px solid #e2e8f0; border-radius:12px; padding:18px; background:#f8fafc; position:relative;">
								<span style="font-size:11px; font-weight:800; text-transform:uppercase; background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:6px;"><?php echo esc_html( $p_info['badge'] ); ?></span>
								<h4 style="margin:8px 0 4px; font-size:18px;"><?php echo esc_html( $p_info['name'] ); ?> <small style="font-size:12px; color:#64748b;">(<?php echo esc_html( $p_slug ); ?>)</small></h4>
								<div style="font-size:20px; font-weight:800; color:#0b1526; margin-bottom:10px;">
									$<?php echo esc_html( number_format( (float) $p_info['monthly_price'], 2 ) ); ?>/mo · $<?php echo esc_html( number_format( (float) $p_info['yearly_price'], 2 ) ); ?>/yr
								</div>
								<ul style="padding-left:18px; margin:0 0 14px; font-size:12.5px; color:#475569;">
									<?php foreach ( (array) $p_info['features_list'] as $fl ) : ?>
										<li><?php echo esc_html( $fl ); ?></li>
									<?php endforeach; ?>
								</ul>
								<div style="display:flex; gap:8px;">
									<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-edit-plan-btn"
										data-slug="<?php echo esc_attr( $p_slug ); ?>"
										data-name="<?php echo esc_attr( $p_info['name'] ); ?>"
										data-badge="<?php echo esc_attr( $p_info['badge'] ); ?>"
										data-monthly="<?php echo esc_attr( $p_info['monthly_price'] ); ?>"
										data-yearly="<?php echo esc_attr( $p_info['yearly_price'] ); ?>"
										data-role="<?php echo esc_attr( $p_info['role'] ); ?>"
										data-specialties="<?php echo esc_attr( $p_info['max_specialties'] ); ?>"
										data-pdfs="<?php echo esc_attr( $p_info['max_pdfs'] ); ?>"
										data-features="<?php echo esc_attr( implode( "\n", (array) $p_info['features_list'] ) ); ?>">
										<i class="fa-solid fa-pen"></i> Edit Plan
									</button>
									<?php if ( 'basic' !== $p_slug ) : ?>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-delete-plan-btn" data-slug="<?php echo esc_attr( $p_slug ); ?>" style="color:#dc2626; border-color:#fca5a5;">
											<i class="fa-solid fa-trash"></i> Delete
										</button>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<!-- Create / Edit Plan Form -->
					<form id="tpd-sa-plan-builder-form" style="border-top:2px dashed #cbd5e1; padding-top:20px;">
						<h4 style="margin:0 0 14px;"><i class="fa-solid fa-plus-circle text-blue"></i> <?php esc_html_e( 'Create New Plan or Update Selected Plan', 'tpd-tool' ); ?></h4>
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Plan Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="plan_name" id="pb_plan_name" required placeholder="e.g. Enterprise VIP Plan" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Plan Slug (ID)', 'tpd-tool' ); ?></label>
								<input type="text" name="plan_slug" id="pb_plan_slug" placeholder="e.g. enterprise (auto-generated if blank)" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Plan Badge / Tag', 'tpd-tool' ); ?></label>
								<input type="text" name="plan_badge" id="pb_plan_badge" placeholder="e.g. Most Popular" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Monthly Price ($) *', 'tpd-tool' ); ?></label>
								<input type="number" step="0.01" name="monthly_price" id="pb_monthly_price" required value="9.99" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Annual Price ($) *', 'tpd-tool' ); ?></label>
								<input type="number" step="0.01" name="yearly_price" id="pb_yearly_price" required value="99.00" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Target User Role', 'tpd-tool' ); ?></label>
								<select name="plan_role" id="pb_plan_role" class="tpd-select">
									<option value="all">Both Advisors & Suppliers</option>
									<option value="advisor">Travel Advisors Only</option>
									<option value="supplier">Suppliers Only</option>
								</select>
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Max Travel Specialties Allowed', 'tpd-tool' ); ?></label>
								<input type="number" name="max_specialties" id="pb_max_specialties" value="10" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Max Resource PDFs Allowed', 'tpd-tool' ); ?></label>
								<input type="number" name="max_pdfs" id="pb_max_pdfs" value="20" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Plan Bullet Features (One per line)', 'tpd-tool' ); ?></label>
							<textarea name="features_list" id="pb_features_list" rows="4" class="tpd-textarea" placeholder="Everything in Standard&#10;Priority Directory Placement&#10;Unlimited Lead Inquiries"></textarea>
						</div>

						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;">
							<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Membership Plan', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>

				<!-- Payment Gateway Integration Card -->
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><i class="fa-regular fa-credit-card text-blue"></i> <?php esc_html_e( 'Payment Gateway Integration (Stripe & PayPal)', 'tpd-tool' ); ?></h3>
					<form id="tpd-sa-payment-settings-form">
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Gateway Environment Mode', 'tpd-tool' ); ?></label>
								<select name="mode" class="tpd-select">
									<option value="test" <?php selected( $payment_settings['mode'], 'test' ); ?>>Test / Sandbox Mode</option>
									<option value="live" <?php selected( $payment_settings['mode'], 'live' ); ?>>Live Production Mode</option>
								</select>
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Platform Currency', 'tpd-tool' ); ?></label>
								<select name="currency" class="tpd-select">
									<option value="USD" <?php selected( $payment_settings['currency'], 'USD' ); ?>>USD ($)</option>
									<option value="EUR" <?php selected( $payment_settings['currency'], 'EUR' ); ?>>EUR (€)</option>
									<option value="GBP" <?php selected( $payment_settings['currency'], 'GBP' ); ?>>GBP (£)</option>
									<option value="CAD" <?php selected( $payment_settings['currency'], 'CAD' ); ?>>CAD (CA$)</option>
								</select>
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Active Gateways', 'tpd-tool' ); ?></label>
								<div style="display:flex; gap:14px; padding-top:8px;">
									<label><input type="checkbox" name="stripe_enabled" value="1" <?php checked( ! empty( $payment_settings['stripe_enabled'] ) ); ?>> Stripe</label>
									<label><input type="checkbox" name="paypal_enabled" value="1" <?php checked( ! empty( $payment_settings['paypal_enabled'] ) ); ?>> PayPal</label>
								</div>
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Stripe Publishable Key', 'tpd-tool' ); ?></label>
								<input type="text" name="stripe_publishable_key" value="<?php echo esc_attr( $payment_settings['stripe_publishable_key'] ); ?>" placeholder="pk_test_..." class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Stripe Secret Key', 'tpd-tool' ); ?></label>
								<input type="password" name="stripe_secret_key" value="<?php echo esc_attr( $payment_settings['stripe_secret_key'] ); ?>" placeholder="sk_test_..." class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'PayPal Client ID', 'tpd-tool' ); ?></label>
								<input type="text" name="paypal_client_id" value="<?php echo esc_attr( $payment_settings['paypal_client_id'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'PayPal Secret', 'tpd-tool' ); ?></label>
								<input type="password" name="paypal_secret" value="<?php echo esc_attr( $payment_settings['paypal_secret'] ); ?>" class="tpd-input">
							</div>
						</div>

						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;">
							<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Payment Gateway Configuration', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 6: REGISTRATION FORM CONFIGURATOR & DYNAMIC CUSTOM / ACF FIELDS
			     ========================================================================= -->
			<div id="sa-forms" class="tpd-sa-pane">
				<!-- Dynamic Custom Fields Builder -->
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><i class="fa-solid fa-wand-magic-sparkles text-blue"></i> <?php esc_html_e( 'Dynamic Custom Fields Builder (Synced with Registration & User Dashboards)', 'tpd-tool' ); ?></h3>
					<p style="font-size:13px; color:#64748b; margin-top:-8px; margin-bottom:16px;">
						<?php esc_html_e( 'Add custom fields to Advisor or Supplier profiles here, OR create ACF Field Groups in WP Admin -> ACF and assign them to "Travel Advisor" or "Supplier Listing". Both automatically render and save in the User Dashboards!', 'tpd-tool' ); ?>
					</p>

					<?php if ( ! empty( $custom_fields ) ) : ?>
						<table class="tpd-sa-table mb-4">
							<thead>
								<tr>
									<th>Field Key</th>
									<th>Label</th>
									<th>Type</th>
									<th>Target Role</th>
									<th>Show on Registration</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $custom_fields as $cf_k => $cf ) : ?>
									<tr>
										<td><code><?php echo esc_html( $cf_k ); ?></code></td>
										<td><strong><?php echo esc_html( $cf['label'] ); ?></strong></td>
										<td><?php echo esc_html( ucfirst( $cf['type'] ) ); ?></td>
										<td><?php echo esc_html( ucfirst( $cf['target'] ) ); ?></td>
										<td><?php echo ! empty( $cf['show_on_registration'] ) ? 'Yes' : 'Dashboard Only'; ?></td>
										<td>
											<button type="button" class="tpd-act-btn tpd-act-del tpd-delete-cf-btn" data-key="<?php echo esc_attr( $cf_k ); ?>"><i class="fa-regular fa-trash-can"></i></button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<form id="tpd-sa-custom-field-form" style="background:#f8fafc; padding:18px; border-radius:10px; border:1px solid #e2e8f0;">
						<h4 style="margin:0 0 12px; font-size:14.5px;"><?php esc_html_e( '+ Add New Custom Field', 'tpd-tool' ); ?></h4>
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Field Label *', 'tpd-tool' ); ?></label>
								<input type="text" name="label" required placeholder="e.g. LinkedIn Profile URL" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Field Type', 'tpd-tool' ); ?></label>
								<select name="type" class="tpd-select">
									<option value="text">Text Input</option>
									<option value="textarea">Multi-line Textarea</option>
									<option value="url">Website / URL</option>
									<option value="number">Number</option>
									<option value="select">Dropdown Select</option>
									<option value="checkbox">Checkboxes</option>
								</select>
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Assign To', 'tpd-tool' ); ?></label>
								<select name="target" class="tpd-select">
									<option value="advisor">Pro Users (Travel Advisors)</option>
									<option value="supplier">Supplier Partners</option>
									<option value="both">Both Advisors & Suppliers</option>
								</select>
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Dropdown / Checkbox Options (comma-separated, if applicable)', 'tpd-tool' ); ?></label>
								<input type="text" name="options" placeholder="Option 1, Option 2, Option 3" class="tpd-input">
							</div>
							<div class="tpd-form-group" style="justify-content:center;">
								<div style="display:flex; gap:18px; padding-top:18px;">
									<label><input type="checkbox" name="required" value="1"> <?php esc_html_e( 'Required Field', 'tpd-tool' ); ?></label>
									<label><input type="checkbox" name="show_on_registration" value="1" checked> <?php esc_html_e( 'Show on Registration Form', 'tpd-tool' ); ?></label>
								</div>
							</div>
						</div>

						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;">
							<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Add Custom Field', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>

				<!-- Registration Form Dropdowns & Steps Configurator -->
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><i class="fa-solid fa-sliders"></i> <?php esc_html_e( 'Registration Form Steps & Dropdown Options Configurator', 'tpd-tool' ); ?></h3>
					<form id="tpd-sa-form-config-form">
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Advisor Form Wizard Steps', 'tpd-tool' ); ?></label>
								<select name="advisor_steps" class="tpd-select">
									<option value="3" <?php selected( (int) $form_cfg['advisor_steps'], 3 ); ?>>3-Step Wizard (Personal -> Agency -> Plan)</option>
									<option value="2" <?php selected( (int) $form_cfg['advisor_steps'], 2 ); ?>>2-Step Wizard (Personal -> Agency)</option>
								</select>
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Advisor Registration Heading', 'tpd-tool' ); ?></label>
								<input type="text" name="advisor_title" value="<?php echo esc_attr( $form_cfg['advisor_title'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Supplier Registration Heading', 'tpd-tool' ); ?></label>
								<input type="text" name="supplier_title" value="<?php echo esc_attr( $form_cfg['supplier_title'] ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Consortia Dropdown Options (One per line)', 'tpd-tool' ); ?></label>
								<textarea name="consortia_options" rows="5" class="tpd-textarea"><?php echo esc_textarea( implode( "\n", (array) $form_cfg['consortia_options'] ) ); ?></textarea>
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Host Agency Dropdown Options (One per line)', 'tpd-tool' ); ?></label>
								<textarea name="host_agency_options" rows="5" class="tpd-textarea"><?php echo esc_textarea( implode( "\n", (array) $form_cfg['host_agency_options'] ) ); ?></textarea>
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Advisor Role Options (One per line)', 'tpd-tool' ); ?></label>
								<textarea name="advisor_roles" rows="4" class="tpd-textarea"><?php echo esc_textarea( implode( "\n", (array) $form_cfg['advisor_roles'] ) ); ?></textarea>
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Supplier Countries List (One per line)', 'tpd-tool' ); ?></label>
								<textarea name="countries" rows="4" class="tpd-textarea"><?php echo esc_textarea( implode( "\n", (array) $form_cfg['countries'] ) ); ?></textarea>
							</div>
						</div>

						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;">
							<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Form Settings & Dropdown Options', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>

				<!-- Elementor Dynamic Tags, ACF Keys & Shortcodes Reference -->
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><i class="fa-brands fa-elementor" style="color:#92003b;"></i> <?php esc_html_e( 'Elementor Dynamic Tags, ACF Field Keys & Shortcodes Reference', 'tpd-tool' ); ?></h3>
					<p style="font-size:13px; color:#64748b; margin-top:-6px; margin-bottom:16px;">
						<?php esc_html_e( 'Every Advisor and Supplier dashboard field is automatically registered as an ACF Field Group, exposed to Elementor Dynamic Tags ("TPD Directory & Dashboard Fields"), available in the "TPD Dynamic Field" Elementor Widget, and callable via shortcode.', 'tpd-tool' ); ?>
					</p>
					<?php
					$master_catalog = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::get_master_field_catalog() : array();
					?>
					<div style="display:grid; grid-template-columns: 1fr 1fr; gap:18px;">
						<div>
							<h4 style="margin:0 0 10px; font-size:14px; color:#164e87;"><?php esc_html_e( 'Travel Advisor Fields (CPT: travel_advisor)', 'tpd-tool' ); ?></h4>
							<div style="max-height:340px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px;">
								<table class="tpd-sa-table" style="font-size:12px;">
									<thead>
										<tr>
											<th>Field Label</th>
											<th>ACF / Meta Key</th>
											<th>Shortcode</th>
										</tr>
									</thead>
									<tbody>
										<?php if ( ! empty( $master_catalog['advisor'] ) ) : foreach ( $master_catalog['advisor'] as $f_key => $f_cfg ) : ?>
											<tr>
												<td><strong><?php echo esc_html( $f_cfg['label'] ); ?></strong></td>
												<td><code><?php echo esc_html( $f_cfg['meta_key'] ); ?></code></td>
												<td><code>[tpd_field key="<?php echo esc_attr( $f_key ); ?>"]</code></td>
											</tr>
										<?php endforeach; endif; ?>
									</tbody>
								</table>
							</div>
						</div>

						<div>
							<h4 style="margin:0 0 10px; font-size:14px; color:#164e87;"><?php esc_html_e( 'Supplier Listing Fields (CPT: supplier_listing)', 'tpd-tool' ); ?></h4>
							<div style="max-height:340px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px;">
								<table class="tpd-sa-table" style="font-size:12px;">
									<thead>
										<tr>
											<th>Field Label</th>
											<th>ACF / Meta Key</th>
											<th>Shortcode</th>
										</tr>
									</thead>
									<tbody>
										<?php if ( ! empty( $master_catalog['supplier'] ) ) : foreach ( $master_catalog['supplier'] as $f_key => $f_cfg ) : ?>
											<tr>
												<td><strong><?php echo esc_html( $f_cfg['label'] ); ?></strong></td>
												<td><code><?php echo esc_html( $f_cfg['meta_key'] ); ?></code></td>
												<td><code>[tpd_field key="<?php echo esc_attr( $f_key ); ?>"]</code></td>
											</tr>
										<?php endforeach; endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 7: BLOGS & SUPPLIER ARTICLES
			     ========================================================================= -->
			<div id="sa-blogs" class="tpd-sa-pane">
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><?php esc_html_e( 'Publish New TARC News / Supplier Blog Article', 'tpd-tool' ); ?></h3>
					<form class="tpd-sa-quick-content-form">
						<input type="hidden" name="content_type" value="blog">
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Article Title *', 'tpd-tool' ); ?></label>
							<input type="text" name="title" required placeholder="Enter article headline..." class="tpd-input">
						</div>
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Article Content *', 'tpd-tool' ); ?></label>
							<textarea name="body" rows="4" required class="tpd-textarea"></textarea>
						</div>
						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;"><?php esc_html_e( 'Publish Article', 'tpd-tool' ); ?></button>
					</form>
				</div>

				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><?php esc_html_e( 'Published Blogs & Articles', 'tpd-tool' ); ?></h3>
					<table class="tpd-sa-table">
						<thead>
							<tr>
								<th>S.No.</th>
								<th>Title</th>
								<th>Date</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$b_i = 1;
							foreach ( $blogs as $b ) :
							?>
								<tr id="sa-post-row-<?php echo esc_attr( $b->ID ); ?>">
									<td><?php echo esc_html( $b_i++ ); ?></td>
									<td><strong><?php echo esc_html( $b->post_title ); ?></strong></td>
									<td><?php echo esc_html( get_the_date( 'd M Y', $b->ID ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $b->ID ) ); ?>" class="tpd-act-btn tpd-act-edit" style="text-decoration:none;"><i class="fa-solid fa-pen"></i></a>
										<button type="button" class="tpd-act-btn tpd-act-del tpd-delete-post-btn" data-post-id="<?php echo esc_attr( $b->ID ); ?>"><i class="fa-regular fa-trash-can"></i></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 8: EVENTS & WEBINARS
			     ========================================================================= -->
			<div id="sa-events" class="tpd-sa-pane">
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><?php esc_html_e( 'Create New Event / Webinar', 'tpd-tool' ); ?></h3>
					<form class="tpd-sa-quick-content-form">
						<input type="hidden" name="content_type" value="event">
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Event / Webinar Title *', 'tpd-tool' ); ?></label>
								<input type="text" name="title" required placeholder="e.g. 2026 Mediterranean Masterclass" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Event Date *', 'tpd-tool' ); ?></label>
								<input type="date" name="event_date" required value="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+7 days' ) ) ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Event Time', 'tpd-tool' ); ?></label>
								<input type="text" name="event_time" value="2:00 PM EST" class="tpd-input">
							</div>
						</div>
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Series / Host Name', 'tpd-tool' ); ?></label>
							<input type="text" name="event_series" value="TARC Partner Webinar" class="tpd-input">
						</div>
						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;"><?php esc_html_e( 'Publish Event', 'tpd-tool' ); ?></button>
					</form>
				</div>

				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><?php esc_html_e( 'Scheduled Events & Webinars', 'tpd-tool' ); ?></h3>
					<table class="tpd-sa-table">
						<thead>
							<tr>
								<th>S.No.</th>
								<th>Event Title</th>
								<th>Date & Time</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$ev_i = 1;
							foreach ( $events as $ev ) :
							?>
								<tr id="sa-post-row-<?php echo esc_attr( $ev->ID ); ?>">
									<td><?php echo esc_html( $ev_i++ ); ?></td>
									<td><strong><?php echo esc_html( $ev->post_title ); ?></strong></td>
									<td><?php echo esc_html( get_post_meta( $ev->ID, '_tpd_event_date', true ) . ' ' . get_post_meta( $ev->ID, '_tpd_event_time', true ) ); ?></td>
									<td>
										<button type="button" class="tpd-act-btn tpd-act-del tpd-delete-post-btn" data-post-id="<?php echo esc_attr( $ev->ID ); ?>"><i class="fa-regular fa-trash-can"></i></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 9: FAQS & HELP
			     ========================================================================= -->
			<div id="sa-faqs" class="tpd-sa-pane">
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><?php esc_html_e( 'Add Platform FAQ (Displayed in Advisor & Supplier Help Tabs)', 'tpd-tool' ); ?></h3>
					<form class="tpd-sa-quick-content-form">
						<input type="hidden" name="content_type" value="faq">
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Question *', 'tpd-tool' ); ?></label>
							<input type="text" name="title" required class="tpd-input">
						</div>
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Answer *', 'tpd-tool' ); ?></label>
							<textarea name="body" rows="3" required class="tpd-textarea"></textarea>
						</div>
						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;"><?php esc_html_e( 'Save FAQ', 'tpd-tool' ); ?></button>
					</form>
				</div>
			</div>

			<!-- =========================================================================
			     PANE 10: AD SPACE & DASHBOARD BANNERS
			     ========================================================================= -->
			<div id="sa-ads" class="tpd-sa-pane">
				<div class="tpd-sa-card">
					<h3 class="tpd-sa-card-title"><i class="fa-solid fa-rectangle-ad text-blue"></i> <?php esc_html_e( 'Configure Welcome Hero Banner & Sponsored Ad Spaces', 'tpd-tool' ); ?></h3>
					<form id="tpd-sa-ad-banners-form">
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Welcome Banner Subtitle', 'tpd-tool' ); ?></label>
								<input type="text" name="welcome_title" value="<?php echo esc_attr( $ads_cfg['welcome_title'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Welcome Banner Quote', 'tpd-tool' ); ?></label>
								<input type="text" name="welcome_quote" value="<?php echo esc_attr( $ads_cfg['welcome_quote'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Welcome Banner Image URL', 'tpd-tool' ); ?></label>
								<input type="url" name="welcome_bg" value="<?php echo esc_attr( $ads_cfg['welcome_bg'] ); ?>" class="tpd-input">
							</div>
						</div>

						<h4 style="margin:18px 0 10px;"><?php esc_html_e( 'Sponsored Square Ad Slot #1', 'tpd-tool' ); ?></h4>
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label>Badge Tag</label>
								<input type="text" name="ad_1_tag" value="<?php echo esc_attr( $ads_cfg['ad_1_tag'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label>Headline</label>
								<input type="text" name="ad_1_title" value="<?php echo esc_attr( $ads_cfg['ad_1_title'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label>Subtitle</label>
								<input type="text" name="ad_1_subtitle" value="<?php echo esc_attr( $ads_cfg['ad_1_subtitle'] ); ?>" class="tpd-input">
							</div>
						</div>

						<h4 style="margin:18px 0 10px;"><?php esc_html_e( 'Sponsored Square Ad Slot #2', 'tpd-tool' ); ?></h4>
						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label>Badge Tag</label>
								<input type="text" name="ad_2_tag" value="<?php echo esc_attr( $ads_cfg['ad_2_tag'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label>Headline</label>
								<input type="text" name="ad_2_title" value="<?php echo esc_attr( $ads_cfg['ad_2_title'] ); ?>" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label>Subtitle</label>
								<input type="text" name="ad_2_subtitle" value="<?php echo esc_attr( $ads_cfg['ad_2_subtitle'] ); ?>" class="tpd-input">
							</div>
						</div>

						<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;">
							<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Ad Space Configuration', 'tpd-tool' ); ?>
						</button>
					</form>
				</div>
			</div>

		</div>
	</div>
</div>

<!-- =========================================================================
     MODAL 1: VIEW USER DETAILS (Eye Icon)
     ========================================================================= -->
<div class="tpd-sa-modal-backdrop" id="tpd-sa-view-user-modal">
	<div class="tpd-sa-modal">
		<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
			<h3 style="margin:0;" id="tpd-view-modal-title"><?php esc_html_e( 'User Profile Details', 'tpd-tool' ); ?></h3>
			<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="jQuery('#tpd-sa-view-user-modal').removeClass('open').fadeOut(150);">Close</button>
		</div>
		<div id="tpd-view-modal-body" style="font-size:13.5px; line-height:1.7;"></div>
	</div>
</div>

<!-- =========================================================================
     MODAL 2: ADD / EDIT ADVISOR OR SUPPLIER USER (Pencil Icon & + Add Button)
     ========================================================================= -->
<div class="tpd-sa-modal-backdrop" id="tpd-sa-edit-user-modal">
	<div class="tpd-sa-modal">
		<div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
			<h3 style="margin:0;" id="tpd-edit-modal-title"><?php esc_html_e( 'Add / Edit User Account', 'tpd-tool' ); ?></h3>
			<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" onclick="jQuery('#tpd-sa-edit-user-modal').removeClass('open').fadeOut(150);">Cancel</button>
		</div>

		<form id="tpd-sa-save-user-form">
			<input type="hidden" name="user_id" id="eu_user_id" value="0">
			<input type="hidden" name="role" id="eu_role" value="travel_advisor">

			<div class="tpd-form-grid-2">
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
					<input type="text" name="first_name" id="eu_first_name" required class="tpd-input">
				</div>
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
					<input type="text" name="last_name" id="eu_last_name" required class="tpd-input">
				</div>
			</div>

			<div class="tpd-form-grid-2">
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
					<input type="email" name="email" id="eu_email" required class="tpd-input">
				</div>
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Username', 'tpd-tool' ); ?></label>
					<input type="text" name="username" id="eu_username" class="tpd-input">
				</div>
			</div>

			<div class="tpd-form-grid-3">
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Phone Number', 'tpd-tool' ); ?></label>
					<input type="text" name="phone" id="eu_phone" class="tpd-input">
				</div>
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Membership Plan', 'tpd-tool' ); ?></label>
					<select name="tier" id="eu_tier" class="tpd-select">
						<?php foreach ( $all_plans as $p_slug => $p_info ) : ?>
							<option value="<?php echo esc_attr( $p_slug ); ?>"><?php echo esc_html( $p_info['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Account Status', 'tpd-tool' ); ?></label>
					<select name="status" id="eu_status" class="tpd-select">
						<option value="active">Active</option>
						<option value="inactive">Inactive</option>
					</select>
				</div>
			</div>

			<!-- Advisor-specific fields -->
			<div id="eu-advisor-fields">
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Agency Name', 'tpd-tool' ); ?></label>
						<input type="text" name="agency_name" id="eu_agency_name" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Location (City, State, Country)', 'tpd-tool' ); ?></label>
						<input type="text" name="location" id="eu_location" class="tpd-input">
					</div>
				</div>
			</div>

			<!-- Supplier-specific fields -->
			<div id="eu-supplier-fields" style="display:none;">
				<div class="tpd-form-grid-3">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Supplier / Company Name', 'tpd-tool' ); ?></label>
						<input type="text" name="company_name" id="eu_company_name" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Position/Title', 'tpd-tool' ); ?></label>
						<input type="text" name="position_title" id="eu_position_title" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Country', 'tpd-tool' ); ?></label>
						<input type="text" name="country" id="eu_country" class="tpd-input">
					</div>
				</div>
			</div>

			<div class="tpd-form-group">
				<label><?php esc_html_e( 'Set / Reset Password (leave blank to keep existing)', 'tpd-tool' ); ?></label>
				<input type="password" name="password" id="eu_password" class="tpd-input">
			</div>

			<div style="text-align:right; margin-top:16px;">
				<button type="submit" class="tpd-btn tpd-btn-darkblue" style="background:#164e87;">
					<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save User Account', 'tpd-tool' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<script>
(function() {
	function activateAdminPane(paneId) {
		if (!paneId) return;
		paneId = String(paneId).replace(/^#/, '');

		var navItems = document.querySelectorAll('.tpd-sa-nav li');
		navItems.forEach(function(li) { li.classList.remove('active'); });

		var navLinks = document.querySelectorAll('.tpd-sa-nav-link');
		navLinks.forEach(function(a) {
			a.classList.remove('active');
			if (a.getAttribute('data-pane') === paneId) {
				a.classList.add('active');
				if (a.parentElement) a.parentElement.classList.add('active');
			}
		});

		var panes = document.querySelectorAll('.tpd-sa-pane');
		panes.forEach(function(p) {
			if (p.id === paneId || p.id === ('tpd-sa-pane-' + paneId)) {
				p.classList.add('active');
				p.style.display = 'block';
			} else {
				p.classList.remove('active');
				p.style.display = 'none';
			}
		});
	}

	document.addEventListener('click', function(e) {
		var trigger = e.target.closest('.tpd-sa-nav-link[data-pane], .tpd-sa-jump-pane[data-pane]');
		if (trigger) {
			e.preventDefault();
			var paneId = trigger.getAttribute('data-pane');
			activateAdminPane(paneId);
		}
	});
})();
</script>

<?php
if ( ! $is_in_admin ) {
	wp_footer();
	?>
	</body>
	</html>
	<?php
}
?>
