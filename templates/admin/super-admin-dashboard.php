<?php
/**
 * Dedicated Super Admin Dashboard for TPD Tool
 * Allows Super Admin to view all registered users, toggle Free vs Paid tiers,
 * inspect platform analytics, and configure the Tier Permission Matrix.
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
			<div class="tpd-admin-hub-wrapper" style="max-width: 600px; margin: 60px auto; padding: 36px; text-align: center; background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 4px 25px rgba(0,0,0,0.06);">
				<i class="fa-solid fa-shield-halved" style="font-size: 48px; color: #d97706; margin-bottom: 16px;"></i>
				<h2 style="font-size: 22px; font-weight: 800; color: #0b1526;"><?php esc_html_e( 'Administrator Access Required', 'tpd-tool' ); ?></h2>
				<p style="color: #64748b; font-size: 14px; margin: 12px 0 24px; line-height: 1.5;"><?php esc_html_e( 'The TPD Super Admin Control Hub is restricted to verified administrators. Please log in with an administrator account to view and manage user tiers.', 'tpd-tool' ); ?></p>
				<a href="<?php echo esc_url( wp_login_url( home_url( '/tpd-admin/' ) ) ); ?>" class="tpd-btn tpd-btn-darkblue" style="padding: 12px 24px;">
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
	<body class="tpd-app-body" style="background: #f8fafc; padding: 30px 20px;">
	<?php
}

$summary = TPD_Tool_Analytics::get_platform_summary();
$all_features = TPD_Tool_Tiers::get_all_features();
$current_permissions = get_option( TPD_Tool_Tiers::OPTION_PERMISSIONS, array() );

// Fetch all registered advisors and suppliers
$users = get_users( array(
	'role__in' => array( 'travel_advisor', 'supplier' ),
	'number'   => 50,
) );
?>
<div class="tpd-admin-hub-wrapper">
	<div class="tpd-admin-hub-header">
		<div>
			<h1><i class="fa-solid fa-shield-halved text-gold"></i> <?php esc_html_e( 'TPD Super Admin Control Hub', 'tpd-tool' ); ?></h1>
			<p><?php esc_html_e( 'Manage registered users, toggle Free vs. Paid tiers, configure feature permissions, and monitor platform metrics.', 'tpd-tool' ); ?></p>
		</div>
		<div class="tpd-admin-header-links">
			<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline">
				<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Open Advisor Portal', 'tpd-tool' ); ?>
			</a>
			<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline">
				<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Open Supplier Portal', 'tpd-tool' ); ?>
			</a>
		</div>
	</div>

	<!-- Top Metric KPI Cards -->
	<div class="tpd-admin-kpi-grid">
		<div class="tpd-kpi-card">
			<span class="tpd-kpi-label"><?php esc_html_e( 'Registered Advisors', 'tpd-tool' ); ?></span>
			<h2 class="tpd-kpi-value text-blue"><?php echo esc_html( $summary['advisors'] ); ?></h2>
			<span class="tpd-kpi-sub"><?php esc_html_e( 'Verified Professionals', 'tpd-tool' ); ?></span>
		</div>

		<div class="tpd-kpi-card">
			<span class="tpd-kpi-label"><?php esc_html_e( 'Registered Suppliers', 'tpd-tool' ); ?></span>
			<h2 class="tpd-kpi-value text-purple"><?php echo esc_html( $summary['suppliers'] ); ?></h2>
			<span class="tpd-kpi-sub"><?php esc_html_e( 'Partner Brands', 'tpd-tool' ); ?></span>
		</div>

		<div class="tpd-kpi-card">
			<span class="tpd-kpi-label"><?php esc_html_e( 'Paid / Pro Users', 'tpd-tool' ); ?></span>
			<h2 class="tpd-kpi-value text-gold"><?php echo esc_html( $summary['paid_users'] ); ?></h2>
			<span class="tpd-kpi-sub"><?php echo esc_html( $summary['free_users'] ); ?> <?php esc_html_e( 'Free Tier Users', 'tpd-tool' ); ?></span>
		</div>

		<div class="tpd-kpi-card">
			<span class="tpd-kpi-label"><?php esc_html_e( 'Total Inquiries & Messages', 'tpd-tool' ); ?></span>
			<h2 class="tpd-kpi-value text-green"><?php echo esc_html( $summary['inquiries'] + $summary['messages'] ); ?></h2>
			<span class="tpd-kpi-sub"><?php esc_html_e( 'Platform Interactions', 'tpd-tool' ); ?></span>
		</div>
	</div>

	<!-- Section 1: Users & Tier Management Table -->
	<div class="tpd-admin-panel-card">
		<div class="tpd-panel-header">
			<h3><i class="fa-solid fa-users-gear"></i> <?php esc_html_e( 'User Directory & Membership Tier Management', 'tpd-tool' ); ?></h3>
			<p><?php esc_html_e( 'Instantly switch any user between Free and Paid tiers to grant or restrict platform features.', 'tpd-tool' ); ?></p>
		</div>

		<div class="tpd-panel-body">
			<table class="tpd-admin-table" id="tpd-users-tier-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User / Member', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Role', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Email Address', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Current Tier', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'One-Click Tier Switcher', 'tpd-tool' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $users as $u ) : 
						$tier = tpd_get_user_tier( $u->ID );
						$role = in_array( 'travel_advisor', (array) $u->roles, true ) ? 'Advisor' : 'Supplier';
					?>
						<tr data-user-id="<?php echo esc_attr( $u->ID ); ?>">
							<td>
								<div class="tpd-user-cell">
									<strong><?php echo esc_html( $u->display_name ); ?></strong>
									<small>(<?php echo esc_html( $u->user_login ); ?>)</small>
								</div>
							</td>
							<td>
								<span class="tpd-role-badge tpd-role-<?php echo strtolower( $role ); ?>">
									<?php echo esc_html( $role ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $u->user_email ); ?></td>
							<td>
								<span class="tpd-tier-badge tpd-tier-<?php echo esc_attr( $tier ); ?>" id="tier-badge-<?php echo esc_attr( $u->ID ); ?>">
									<?php echo ( 'paid' === $tier ) ? '<i class="fa-solid fa-crown text-gold"></i> Paid Pro' : 'Free Tier'; ?>
								</span>
							</td>
							<td>
								<button type="button" 
									class="tpd-btn tpd-btn-xs <?php echo ( 'paid' === $tier ) ? 'tpd-btn-outline' : 'tpd-btn-secondary'; ?> tpd-toggle-tier-btn" 
									data-user-id="<?php echo esc_attr( $u->ID ); ?>" 
									data-current-tier="<?php echo esc_attr( $tier ); ?>">
									<?php echo ( 'paid' === $tier ) ? __( 'Demote to Free', 'tpd-tool' ) : __( 'Upgrade to Paid Pro', 'tpd-tool' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Section 2: Tier Permissions Matrix (Configuring What Free vs Paid Can Access) -->
	<div class="tpd-admin-panel-card mt-4">
		<div class="tpd-panel-header">
			<h3><i class="fa-solid fa-sliders"></i> <?php esc_html_e( 'Feature Permission Matrix (Free vs. Paid Tiers)', 'tpd-tool' ); ?></h3>
			<p><?php esc_html_e( 'Configure which capabilities are accessible to Free Tier accounts vs. Paid Pro accounts.', 'tpd-tool' ); ?></p>
		</div>

		<div class="tpd-panel-body">
			<form id="tpd-tier-permissions-form">
				<!-- Advisor Permissions Table -->
				<h4 class="tpd-matrix-subtitle"><i class="fa-solid fa-user-tie"></i> <?php esc_html_e( 'Travel Advisor Features', 'tpd-tool' ); ?></h4>
				<table class="tpd-matrix-table mb-4">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature Capability', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Description', 'tpd-tool' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'Free Tier Allowed', 'tpd-tool' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'Paid Pro Tier Allowed', 'tpd-tool' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_features['advisor'] as $f_slug => $f_info ) : 
							$free_checked = ! empty( $current_permissions['free'][ $f_slug ] );
							$paid_checked = ! empty( $current_permissions['paid'][ $f_slug ] );
						?>
							<tr>
								<td>
									<strong><i class="fa-solid <?php echo esc_attr( $f_info['icon'] ); ?>"></i> <?php echo esc_html( $f_info['label'] ); ?></strong>
								</td>
								<td class="text-muted"><?php echo esc_html( $f_info['description'] ); ?></td>
								<td class="text-center">
									<input type="checkbox" name="free_permissions[]" value="<?php echo esc_attr( $f_slug ); ?>" <?php checked( $free_checked ); ?>>
								</td>
								<td class="text-center">
									<input type="checkbox" name="paid_permissions[]" value="<?php echo esc_attr( $f_slug ); ?>" <?php checked( $paid_checked ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- Supplier Permissions Table -->
				<h4 class="tpd-matrix-subtitle"><i class="fa-solid fa-building"></i> <?php esc_html_e( 'Supplier Partner Features', 'tpd-tool' ); ?></h4>
				<table class="tpd-matrix-table mb-4">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature Capability', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Description', 'tpd-tool' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'Free Tier Allowed', 'tpd-tool' ); ?></th>
							<th class="text-center"><?php esc_html_e( 'Paid Pro Tier Allowed', 'tpd-tool' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $all_features['supplier'] as $f_slug => $f_info ) : 
							$free_checked = ! empty( $current_permissions['free'][ $f_slug ] );
							$paid_checked = ! empty( $current_permissions['paid'][ $f_slug ] );
						?>
							<tr>
								<td>
									<strong><i class="fa-solid <?php echo esc_attr( $f_info['icon'] ); ?>"></i> <?php echo esc_html( $f_info['label'] ); ?></strong>
								</td>
								<td class="text-muted"><?php echo esc_html( $f_info['description'] ); ?></td>
								<td class="text-center">
									<input type="checkbox" name="free_permissions[]" value="<?php echo esc_attr( $f_slug ); ?>" <?php checked( $free_checked ); ?>>
								</td>
								<td class="text-center">
									<input type="checkbox" name="paid_permissions[]" value="<?php echo esc_attr( $f_slug ); ?>" <?php checked( $paid_checked ); ?>>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="tpd-matrix-footer">
					<button type="submit" class="tpd-btn tpd-btn-primary" id="tpd-save-permissions-btn">
						<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Feature Permissions Matrix', 'tpd-tool' ); ?>
					</button>
					<span id="tpd-matrix-status-msg" style="display:none;" class="text-green font-bold"></span>
				</div>
			</form>
	</div>
</div>

<?php
if ( ! $is_in_admin ) {
	wp_footer();
	?>
	</body>
	</html>
	<?php
}
?>
