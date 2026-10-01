<?php
/**
 * Supplier Partner Registration Form Template (User Level)
 * Updated with exact specifications from Client Document
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_standalone = ! did_action( 'wp_head' );
if ( $is_standalone ) {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php esc_html_e( 'Supplier Partner Registration | Travel Partner Directory', 'tpd-tool' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body class="tpd-app-body" style="background:#f1f5f9; padding:40px 16px; margin:0; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;">
	<?php
}

if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	?>
	<div class="tpd-reg-already-logged-in">
		<i class="fa-solid fa-circle-check text-green"></i>
		<h3><?php printf( esc_html__( 'You are already logged in as %s', 'tpd-tool' ), esc_html( $current_user->display_name ) ); ?></h3>
		<p><?php esc_html_e( 'Access your Supplier Portal to manage company listings, Virtual Office reps, and live inquiries.', 'tpd-tool' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-darkblue">
			<i class="fa-solid fa-gauge-high"></i> <?php esc_html_e( 'Go to Supplier Dashboard', 'tpd-tool' ); ?>
		</a>
	</div>
	<?php
	if ( $is_standalone ) {
		wp_footer();
		?>
		</body>
		</html>
		<?php
	}
	return;
}
?>
<div class="tpd-registration-wrapper">
	<div class="tpd-reg-card">
		<!-- Header -->
		<div class="tpd-reg-header">
			<div class="tpd-reg-badge"><i class="fa-solid fa-ship"></i> SUPPLIER PARTNER NETWORK</div>
			<h2><?php esc_html_e( 'Create Your Supplier User Account', 'tpd-tool' ); ?></h2>
			<p><?php esc_html_e( 'Sign up as a trade representative to create, claim, and manage partner listings, showcase your team in the Virtual Office, and connect with verified travel advisors.', 'tpd-tool' ); ?></p>
		</div>

		<form id="tpd-supplier-registration-form" class="tpd-reg-form" method="post">
			<input type="hidden" name="action" value="tpd_register_supplier">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

			<div class="tpd-form-section">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-user"></i> <?php esc_html_e( 'Representative Contact Details', 'tpd-tool' ); ?></h3>
				
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="rep_first_name" required placeholder="e.g. Sarah" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="rep_last_name" required placeholder="e.g. Mitchell" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Primary Contact Phone Number *', 'tpd-tool' ); ?></label>
						<div style="display:flex; gap:8px;">
							<select name="phone_country_code" class="tpd-select tpd-select-sm" style="max-width: 105px;">
								<option value="+1">+1 (US/CA)</option>
								<option value="+44">+44 (UK)</option>
								<option value="+61">+61 (AU)</option>
								<option value="+33">+33 (FR)</option>
								<option value="+49">+49 (DE)</option>
								<option value="+39">+39 (IT)</option>
								<option value="+34">+34 (ES)</option>
							</select>
							<input type="tel" name="phone" required placeholder="(800) 555-0199" class="tpd-input" style="flex:1;">
						</div>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Primary Email Address *', 'tpd-tool' ); ?></label>
						<input type="email" name="email" required placeholder="smitchell@brand.com" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Country of Residence *', 'tpd-tool' ); ?></label>
					<select name="country_of_residence" class="tpd-select" required>
						<option value="United States" selected>United States</option>
						<option value="Canada">Canada</option>
						<option value="United Kingdom">United Kingdom</option>
						<option value="Australia">Australia</option>
						<option value="Germany">Germany</option>
						<option value="France">France</option>
						<option value="Italy">Italy</option>
						<option value="Spain">Spain</option>
						<option value="Other">Other Country</option>
					</select>
				</div>
			</div>

			<div class="tpd-form-section">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-key"></i> <?php esc_html_e( 'Account Login Credentials', 'tpd-tool' ); ?></h3>
				
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Choose Username *', 'tpd-tool' ); ?></label>
						<input type="text" name="username" required placeholder="e.g. sarahmitchell" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Password *', 'tpd-tool' ); ?></label>
						<div style="position:relative;">
							<input type="password" name="password" id="tpd_supplier_password" required placeholder="Minimum 8 characters" class="tpd-input" style="padding-right:40px;">
							<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
								<i class="fa-solid fa-eye"></i>
							</button>
						</div>
					</div>
				</div>

				<div class="tpd-notice-box" style="margin-top:16px; padding:12px 16px; background:#f0fdf4; border-left:4px solid #16a34a; border-radius:6px; font-size:12.5px; color:#166534;">
					<i class="fa-solid fa-circle-check"></i> <strong>Automatic Account Approval:</strong> As a registered supplier user, you can immediately create free Basic listings, join existing brand listings as a team member, or claim an existing company listing in your dashboard!
				</div>
			</div>

			<div class="tpd-reg-status" style="display:none;"></div>

			<div class="tpd-reg-submit-wrap">
				<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-btn-lg" id="tpd-supplier-reg-btn">
					<i class="fa-solid fa-arrow-right"></i> <?php esc_html_e( 'Create Supplier Account & Open Dashboard', 'tpd-tool' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<?php
if ( $is_standalone ) {
	wp_footer();
	?>
	</body>
	</html>
	<?php
}
?>
