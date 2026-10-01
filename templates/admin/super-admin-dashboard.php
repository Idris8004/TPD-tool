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
$claims = class_exists( 'TPD_Tool_Profile_Editor' ) ? TPD_Tool_Profile_Editor::get_claims() : array();

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
			<p><?php esc_html_e( 'Manage registered users, assign 3-Tier plans (Basic, Standard, Premium), review listing claims, export demographics, and configure feature permissions.', 'tpd-tool' ); ?></p>
		</div>
		<div class="tpd-admin-header-links" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'tpd_action', 'export_advisors_csv' ), 'tpd_export_advisors', 'nonce' ) ); ?>" class="tpd-btn tpd-btn-sm tpd-btn-primary" style="background:#047857; border-color:#047857; color:#fff;">
				<i class="fa-solid fa-file-csv"></i> <?php esc_html_e( 'Export Advisors Demographics CSV', 'tpd-tool' ); ?>
			</a>
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
			<span class="tpd-kpi-label"><?php esc_html_e( 'Pending Claims', 'tpd-tool' ); ?></span>
			<h2 class="tpd-kpi-value text-gold"><?php echo esc_html( count( $claims ) ); ?></h2>
			<span class="tpd-kpi-sub"><?php esc_html_e( 'Awaiting Admin Verification', 'tpd-tool' ); ?></span>
		</div>

		<div class="tpd-kpi-card">
			<span class="tpd-kpi-label"><?php esc_html_e( 'Total Platform Interactions', 'tpd-tool' ); ?></span>
			<h2 class="tpd-kpi-value text-green"><?php echo esc_html( $summary['inquiries'] + $summary['messages'] ); ?></h2>
			<span class="tpd-kpi-sub"><?php esc_html_e( 'Inquiries & Messages', 'tpd-tool' ); ?></span>
		</div>
	</div>

	<!-- Section 1: Users & Tier Management Table -->
	<div class="tpd-admin-panel-card">
		<div class="tpd-panel-header">
			<h3><i class="fa-solid fa-users-gear"></i> <?php esc_html_e( 'User Directory & 3-Tier Membership Plan Manager', 'tpd-tool' ); ?></h3>
			<p><?php esc_html_e( 'Instantly change any user between Basic (Free), Standard ($9.99/mo), and Premium ($19.99/mo) tiers with live permission gating.', 'tpd-tool' ); ?></p>
		</div>

		<div class="tpd-panel-body">
			<table class="tpd-admin-table" id="tpd-users-tier-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User / Member', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Role', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Email Address', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Current Tier Status', 'tpd-tool' ); ?></th>
						<th><?php esc_html_e( 'Instant Tier Assignment', 'tpd-tool' ); ?></th>
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
									<small>(@<?php echo esc_html( $u->user_login ); ?>)</small>
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
									<?php 
									if ( 'premium' === $tier ) {
										echo '<i class="fa-solid fa-crown text-gold"></i> Premium Plan';
									} elseif ( 'standard' === $tier ) {
										echo '<i class="fa-solid fa-gem text-blue"></i> Standard Plan';
									} else {
										echo 'Basic (Free)';
									}
									?>
								</span>
							</td>
							<td>
								<div style="display:flex; align-items:center; gap:8px;">
									<select class="tpd-select tpd-select-sm tpd-tier-select" data-user-id="<?php echo esc_attr( $u->ID ); ?>" style="font-weight:600; padding:6px 10px; border-radius:6px;">
										<option value="basic" <?php selected( $tier, 'basic' ); ?>>Basic (Free)</option>
										<option value="standard" <?php selected( $tier, 'standard' ); ?>>Standard ($9.99/mo)</option>
										<option value="premium" <?php selected( $tier, 'premium' ); ?>>Premium ($19.99/mo)</option>
									</select>
									<span class="tpd-tier-update-indicator" id="tier-ind-<?php echo esc_attr( $u->ID ); ?>" style="display:none; color:#16a34a; font-size:14px;"><i class="fa-solid fa-check"></i></span>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Section 2: Pending Brand Listing Claims Queue -->
	<div class="tpd-admin-panel-card mt-4">
		<div class="tpd-panel-header">
			<div style="display:flex; justify-content:space-between; align-items:center;">
				<div>
					<h3><i class="fa-solid fa-clipboard-check text-gold"></i> <?php esc_html_e( 'Pending Brand Listing Claims Queue', 'tpd-tool' ); ?></h3>
					<p><?php esc_html_e( 'Suppliers requesting verified ownership of existing directory listings. Review credentials and approve to assign listing management.', 'tpd-tool' ); ?></p>
				</div>
				<span class="tpd-badge-count" style="background:#fef3c7; color:#b45309; padding:4px 12px; border-radius:999px; font-weight:800; font-size:12px;">
					<?php echo count( $claims ); ?> Active Requests
				</span>
			</div>
		</div>

		<div class="tpd-panel-body">
			<?php if ( ! empty( $claims ) ) : ?>
				<table class="tpd-admin-table" id="tpd-claims-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Listing / Brand', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Claiming Supplier User', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Date Submitted', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Verification Notes', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Status', 'tpd-tool' ); ?></th>
							<th><?php esc_html_e( 'Review Action', 'tpd-tool' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $claims as $lid => $c ) : 
							$claimer = get_userdata( $c['user_id'] );
							$listing_title = get_the_title( $lid ) ?: ( 'Listing #' . $lid );
							$status = isset( $c['status'] ) ? $c['status'] : 'pending';
						?>
							<tr id="claim-row-<?php echo esc_attr( $lid ); ?>">
								<td>
									<strong><?php echo esc_html( $listing_title ); ?></strong>
									<br><small class="text-muted">Post ID: <?php echo esc_html( $lid ); ?></small>
								</td>
								<td>
									<?php if ( $claimer ) : ?>
										<strong><?php echo esc_html( $claimer->display_name ); ?></strong>
										<br><small><?php echo esc_html( $claimer->user_email ); ?></small>
									<?php else : ?>
										<span class="text-muted">User #<?php echo esc_html( $c['user_id'] ); ?></span>
									<?php endif; ?>
								</td>
								<td><small><?php echo esc_html( $c['date'] ); ?></small></td>
								<td style="max-width:280px; font-size:12.5px; color:#475569;"><?php echo esc_html( $c['notes'] ); ?></td>
								<td>
									<span class="tpd-claim-status-badge tpd-status-<?php echo esc_attr( $status ); ?>" id="claim-status-<?php echo esc_attr( $lid ); ?>" style="padding:4px 10px; border-radius:6px; font-size:11.5px; font-weight:700; text-transform:uppercase; <?php echo ( 'approved' === $status ) ? 'background:#dcfce7; color:#166534;' : ( ( 'rejected' === $status ) ? 'background:#fee2e2; color:#991b1b;' : 'background:#fef3c7; color:#92400e;' ); ?>">
										<?php echo esc_html( $status ); ?>
									</span>
								</td>
								<td>
									<?php if ( 'pending' === $status ) : ?>
										<div style="display:flex; gap:6px;">
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-primary tpd-claim-action-btn" data-id="<?php echo esc_attr( $lid ); ?>" data-decision="approve" style="background:#16a34a; border-color:#16a34a;">
												<i class="fa-solid fa-check"></i> Approve
											</button>
											<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-claim-action-btn" data-id="<?php echo esc_attr( $lid ); ?>" data-decision="reject" style="color:#dc2626; border-color:#fca5a5;">
												<i class="fa-solid fa-xmark"></i> Reject
											</button>
										</div>
									<?php else : ?>
										<span class="text-muted" style="font-size:12px;">Reviewed</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="text-muted"><?php esc_html_e( 'No claims pending review at this time.', 'tpd-tool' ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Section 3: Tier Permissions Matrix (Configuring What Basic vs Standard vs Premium Can Access) -->
	<div class="tpd-admin-panel-card mt-4">
		<div class="tpd-panel-header">
			<h3><i class="fa-solid fa-sliders"></i> <?php esc_html_e( 'Feature Permission Matrix (Plan Gating Rules)', 'tpd-tool' ); ?></h3>
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
