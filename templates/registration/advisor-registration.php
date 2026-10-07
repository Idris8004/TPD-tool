<?php
/**
 * Travel Advisor (Pro User) Split-Screen Multi-Step Registration Template
 * Matches Travel Partner Directory split-screen vertical stepper design.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_cfg     = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_form_config() : array();
$plans        = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'advisor' ) : array();
$payments     = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_payment_settings() : array();
$steps_mode   = isset( $form_cfg['advisor_steps'] ) && 2 === (int) $form_cfg['advisor_steps'] ? 2 : 3;
$currency_sym = isset( $payments['currency_symbol'] ) ? $payments['currency_symbol'] : '$';
$countries    = ! empty( $form_cfg['countries'] ) ? $form_cfg['countries'] : array( 'United States', 'Canada', 'United Kingdom', 'Australia', 'Germany', 'France', 'Italy', 'Spain', 'Mexico', 'Other' );

$is_standalone = ! did_action( 'wp_head' );
if ( $is_standalone ) {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php esc_html_e( 'Travel Advisor Registration | Travel Partner Directory', 'tpd-tool' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body class="tpd-reg-standalone-body" style="margin:0; padding:0; background:#ffffff; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;">
	<?php
}
?>
<style>
	.tpd-split-auth-shell {
		display: flex;
		min-height: 100vh;
		width: 100%;
		background: #ffffff;
		font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
		color: #1e293b;
	}
	/* Left Dark Teal Sidebar */
	.tpd-split-aside {
		width: 360px;
		min-width: 320px;
		background: #013243;
		color: #ffffff;
		padding: 64px 44px;
		display: flex;
		flex-direction: column;
		align-items: center;
		box-sizing: border-box;
	}
	.tpd-split-brand {
		text-align: center;
		margin-bottom: 64px;
		text-decoration: none;
		color: #ffffff;
	}
	.tpd-split-brand-pin {
		width: 48px;
		height: 48px;
		border-radius: 50%;
		border: 2.5px solid #ffffff;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 20px;
		margin-bottom: 10px;
		color: #ffffff;
	}
	.tpd-split-brand h1 {
		margin: 0;
		font-size: 19px;
		font-weight: 800;
		letter-spacing: 0.4px;
		color: #ffffff;
	}
	.tpd-split-brand span {
		display: block;
		font-size: 10px;
		letter-spacing: 2.5px;
		text-transform: uppercase;
		color: #94a3b8;
		margin-top: 3px;
	}
	/* Vertical Stepper */
	.tpd-v-stepper {
		width: 100%;
		max-width: 240px;
		display: flex;
		flex-direction: column;
		gap: 0;
	}
	.tpd-v-step {
		display: flex;
		align-items: flex-start;
		gap: 16px;
		position: relative;
		padding-bottom: 42px;
		cursor: pointer;
	}
	.tpd-v-step:last-child {
		padding-bottom: 0;
	}
	.tpd-v-step:not(:last-child)::after {
		content: '';
		position: absolute;
		left: 19px;
		top: 42px;
		bottom: 4px;
		border-left: 1.5px dashed rgba(255, 255, 255, 0.35);
	}
	.tpd-v-box {
		width: 40px;
		height: 40px;
		border-radius: 6px;
		border: 1.5px dashed rgba(255, 255, 255, 0.4);
		background: rgba(255, 255, 255, 0.08);
		color: rgba(255, 255, 255, 0.75);
		font-weight: 700;
		font-size: 14px;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		transition: all 0.25s ease;
	}
	.tpd-v-step.active .tpd-v-box {
		background: #ffffff;
		color: #013243;
		border: 1.5px solid #ffffff;
		box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
	}
	.tpd-v-step.done .tpd-v-box {
		background: #0d9488;
		color: #ffffff;
		border: 1.5px solid #0d9488;
	}
	.tpd-v-meta {
		display: flex;
		flex-direction: column;
		padding-top: 2px;
	}
	.tpd-v-meta small {
		font-size: 11.5px;
		color: rgba(255, 255, 255, 0.7);
		font-weight: 500;
	}
	.tpd-v-meta strong {
		font-size: 13.5px;
		color: #ffffff;
		font-weight: 600;
		margin-top: 2px;
	}
	/* Right Main Area */
	.tpd-split-main {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 48px 32px;
		background: #ffffff;
		box-sizing: border-box;
	}
	.tpd-split-card-wrap {
		width: 100%;
		max-width: 680px;
	}
	.tpd-split-card {
		background: #ffffff;
		border-radius: 14px;
		padding: 40px 46px;
		box-shadow: 0 10px 40px rgba(1, 50, 67, 0.07);
		border: 1px solid #f1f5f9;
	}
	.tpd-pane-heading {
		font-size: 16px;
		font-weight: 600;
		color: #00798c;
		margin: 0 0 26px;
	}
	.tpd-step-pane {
		display: none;
	}
	.tpd-step-pane.active {
		display: block;
		animation: tpdStepFade 0.25s ease;
	}
	@keyframes tpdStepFade {
		from { opacity: 0; transform: translateY(6px); }
		to { opacity: 1; transform: translateY(0); }
	}
	.tpd-f-row-2 {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 28px;
		margin-bottom: 22px;
	}
	.tpd-f-group {
		display: flex;
		flex-direction: column;
		margin-bottom: 22px;
	}
	.tpd-f-row-2 .tpd-f-group {
		margin-bottom: 0;
	}
	.tpd-f-group label {
		font-size: 13.5px;
		font-weight: 600;
		color: #00798c;
		margin-bottom: 6px;
	}
	.tpd-line-input,
	.tpd-line-select {
		border: none;
		border-bottom: 1.5px solid #334155;
		border-radius: 0;
		padding: 10px 2px;
		font-size: 14px;
		color: #0f172a;
		background: transparent;
		width: 100%;
		outline: none;
		transition: border-color 0.2s;
		box-sizing: border-box;
	}
	.tpd-line-input::placeholder {
		color: #94a3b8;
	}
	.tpd-line-input:focus,
	.tpd-line-select:focus {
		border-bottom-color: #00798c;
	}
	.tpd-btn-teal-outline {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		width: 100%;
		padding: 13px 24px;
		background: #ffffff;
		color: #00798c;
		border: 1.5px solid #00798c;
		border-radius: 5px;
		font-size: 13px;
		font-weight: 700;
		letter-spacing: 0.8px;
		text-transform: uppercase;
		cursor: pointer;
		transition: all 0.2s;
	}
	.tpd-btn-teal-outline:hover {
		background: #00798c;
		color: #ffffff;
	}
	.tpd-btn-teal-solid {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		width: 100%;
		padding: 14px 24px;
		background: #013243;
		color: #ffffff;
		border: 1.5px solid #013243;
		border-radius: 5px;
		font-size: 13px;
		font-weight: 700;
		letter-spacing: 0.8px;
		text-transform: uppercase;
		cursor: pointer;
		transition: all 0.2s;
	}
	.tpd-btn-teal-solid:hover {
		background: #00798c;
		border-color: #00798c;
	}
	.tpd-card-Support-links {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-top: 22px;
		padding-top: 14px;
		font-size: 13px;
		color: #013243;
		font-weight: 600;
	}
	.tpd-card-Support-links a {
		color: #00798c;
		text-decoration: none;
		font-weight: 700;
	}
	.tpd-bottom-switch-bar {
		display: block;
		width: 100%;
		margin-top: 22px;
		padding: 13px 20px;
		text-align: center;
		border: 1.5px solid #00798c;
		border-radius: 5px;
		color: #00798c;
		font-size: 12.5px;
		font-weight: 700;
		letter-spacing: 0.6px;
		text-transform: uppercase;
		text-decoration: none;
		box-sizing: border-box;
		transition: all 0.2s;
	}
	.tpd-bottom-switch-bar:hover {
		background: #f0fdfa;
	}
	.tpd-plan-cards-row {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
		gap: 14px;
		margin: 14px 0 20px;
	}
	.tpd-plan-card-option {
		border: 2px solid #e2e8f0;
		border-radius: 10px;
		padding: 16px;
		cursor: pointer;
		transition: all 0.2s;
		background: #ffffff;
		display: block;
	}
	.tpd-plan-card-option.selected,
	.tpd-plan-card-option:has(input:checked) {
		border-color: #00798c;
		background: #f0fdfa;
	}
	.tpd-reg-status {
		margin-top: 16px;
		padding: 12px 16px;
		border-radius: 8px;
		font-size: 13.5px;
		font-weight: 600;
	}
	.tpd-reg-status.error {
		background: #fef2f2;
		color: #991b1b;
		border: 1px solid #fecaca;
	}
	.tpd-reg-status.success {
		background: #f0fdf4;
		color: #166534;
		border: 1px solid #bbf7d0;
	}
	@media (max-width: 900px) {
		.tpd-split-auth-shell { flex-direction: column; }
		.tpd-split-aside { width: 100%; padding: 32px 20px; }
		.tpd-v-stepper { flex-direction: row; max-width: 100%; justify-content: center; gap: 20px; }
		.tpd-v-step { padding-bottom: 0; }
		.tpd-v-step::after { display: none; }
		.tpd-f-row-2 { grid-template-columns: 1fr; gap: 18px; }
		.tpd-split-card { padding: 28px 22px; }
	}
</style>

<?php
$logged_in_uid = get_current_user_id();
if ( is_user_logged_in() && TPD_Tool_Roles::is_advisor( $logged_in_uid ) && ! isset( $_GET['preview_form'] ) ) {
	$current_user = wp_get_current_user();
	?>
	<div class="tpd-split-auth-shell">
		<aside class="tpd-split-aside">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tpd-split-brand">
				<div class="tpd-split-brand-pin"><i class="fa-solid fa-location-dot"></i></div>
				<h1>Travel Partner</h1>
				<span>DIRECTORY</span>
			</a>
		</aside>
		<main class="tpd-split-main">
			<div class="tpd-split-card-wrap">
				<div class="tpd-split-card" style="text-align:center;">
					<i class="fa-solid fa-circle-check" style="font-size:44px; color:#00798c; margin-bottom:14px;"></i>
					<h3 style="margin:0 0 8px; color:#013243;"><?php printf( esc_html__( 'Welcome, %s', 'tpd-tool' ), esc_html( $current_user->display_name ) ); ?></h3>
					<p style="color:#64748b; font-size:14px; margin-bottom:24px;"><?php esc_html_e( 'Your Travel Advisor account is active. Continue to your Advisor Dashboard or preview the registration steps.', 'tpd-tool' ); ?></p>
					<div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
						<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" class="tpd-btn-teal-solid" style="width:auto; text-decoration:none; padding:12px 28px;">
							<?php esc_html_e( 'Open Advisor Dashboard', 'tpd-tool' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( 'preview_form', '1' ) ); ?>" class="tpd-btn-teal-outline" style="width:auto; text-decoration:none; padding:12px 28px;">
							<?php esc_html_e( 'Preview Registration Form', 'tpd-tool' ); ?>
						</a>
					</div>
				</div>
			</div>
		</main>
	</div>
	<?php
	if ( $is_standalone ) {
		wp_footer();
		echo '</body></html>';
	}
	return;
}

// Pre-fill if logged in as a Supplier Partner activating a Travel Advisor profile
$is_logged_in_supplier = ( is_user_logged_in() && TPD_Tool_Roles::is_supplier( $logged_in_uid ) && ! TPD_Tool_Roles::is_advisor( $logged_in_uid ) );
$prefill_user          = $is_logged_in_supplier ? get_userdata( $logged_in_uid ) : null;
$prefill_fname         = $prefill_user ? $prefill_user->first_name : '';
$prefill_lname         = $prefill_user ? $prefill_user->last_name : '';
$prefill_email         = $prefill_user ? $prefill_user->user_email : ( isset( $_GET['cross_role_email'] ) ? sanitize_email( wp_unslash( $_GET['cross_role_email'] ) ) : '' );
$prefill_phone         = $prefill_user ? get_user_meta( $logged_in_uid, 'tpd_phone', true ) : '';
$prefill_username      = $prefill_user ? $prefill_user->user_login : '';
?>

<div class="tpd-split-auth-shell">
	<!-- Left Dark Teal Sidebar with Vertical Stepper -->
	<aside class="tpd-split-aside">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tpd-split-brand">
			<div class="tpd-split-brand-pin"><i class="fa-solid fa-location-dot"></i></div>
			<h1>Travel Partner</h1>
			<span>DIRECTORY</span>
		</a>

		<div class="tpd-v-stepper" id="tpd-advisor-stepper">
			<div class="tpd-v-step tpd-w-step active" data-step="1">
				<div class="tpd-v-box">1</div>
				<div class="tpd-v-meta">
					<small><?php esc_html_e( 'Step 1', 'tpd-tool' ); ?></small>
					<strong><?php esc_html_e( 'Tell Us About Yourself', 'tpd-tool' ); ?></strong>
				</div>
			</div>

			<div class="tpd-v-step tpd-w-step" data-step="2">
				<div class="tpd-v-box">2</div>
				<div class="tpd-v-meta">
					<small><?php esc_html_e( 'Step 2', 'tpd-tool' ); ?></small>
					<strong><?php esc_html_e( 'Advisor Registration', 'tpd-tool' ); ?></strong>
				</div>
			</div>

			<?php if ( 3 === $steps_mode ) : ?>
			<div class="tpd-v-step tpd-w-step" data-step="3">
				<div class="tpd-v-box">3</div>
				<div class="tpd-v-meta">
					<small><?php esc_html_e( 'Step 3', 'tpd-tool' ); ?></small>
					<strong><?php esc_html_e( 'One Final Step', 'tpd-tool' ); ?></strong>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</aside>

	<!-- Right Form Content -->
	<main class="tpd-split-main">
		<div class="tpd-split-card-wrap">
			<div class="tpd-split-card tpd-reg-card">
				<form id="tpd-advisor-registration-form" class="tpd-reg-form" method="post" novalidate>
					<input type="hidden" name="action" value="tpd_register_advisor">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
					<input type="hidden" name="confirm_cross_role" id="tpd_adv_confirm_cross_role" value="<?php echo $is_logged_in_supplier ? '1' : '0'; ?>">

					<!-- Cross-Role Detection Prompt Banner -->
					<div id="tpd-adv-cross-role-banner" style="<?php echo $is_logged_in_supplier ? 'display:block;' : 'display:none;'; ?> background:#f0fdfa; border:1.5px solid #00798c; border-radius:10px; padding:16px 20px; margin-bottom:22px;">
						<div style="display:flex; align-items:flex-start; gap:12px;">
							<i class="fa-solid fa-layer-group" style="color:#00798c; font-size:20px; margin-top:2px;"></i>
							<div style="flex:1;">
								<strong id="tpd-adv-cr-title" style="color:#013243; font-size:14px; display:block; margin-bottom:4px;">
									<?php esc_html_e( 'Your email is already registered as a Supplier Partner!', 'tpd-tool' ); ?>
								</strong>
								<p id="tpd-adv-cr-desc" style="color:#334155; font-size:13px; margin:0 0 10px; line-height:1.45;">
									<?php esc_html_e( 'Do you want to continue and also register as a Travel Advisor? Complete your Advisor information below and you will be able to log into both dashboards (using the same or a separate username & password).', 'tpd-tool' ); ?>
								</p>
								<div id="tpd-adv-cr-actions" style="<?php echo $is_logged_in_supplier ? 'display:none;' : 'display:flex;'; ?> gap:10px; flex-wrap:wrap;">
									<button type="button" id="tpd-adv-cr-accept-btn" style="background:#00798c; color:#fff; border:none; padding:8px 16px; border-radius:5px; font-size:12.5px; font-weight:700; cursor:pointer;">
										<i class="fa-solid fa-check"></i> <?php esc_html_e( 'Yes, Continue as Travel Advisor Also', 'tpd-tool' ); ?>
									</button>
									<a href="<?php echo esc_url( home_url( '/supplier-login/' ) ); ?>" style="background:#fff; color:#00798c; border:1px solid #00798c; padding:7px 14px; border-radius:5px; font-size:12.5px; font-weight:700; text-decoration:none;">
										<?php esc_html_e( 'Go to Supplier Login', 'tpd-tool' ); ?>
									</a>
								</div>
							</div>
						</div>
					</div>

					<!-- Same-Role Duplicate Email Alert Banner -->
					<div id="tpd-adv-same-role-alert" style="display:none; background:#fef2f2; border:1.5px solid #ef4444; border-radius:10px; padding:14px 18px; margin-bottom:20px; color:#991b1b; font-size:13.5px; font-weight:600;">
						<span id="tpd-adv-same-role-msg"></span>
						<a href="<?php echo esc_url( home_url( '/advisor-login/' ) ); ?>" style="margin-left:10px; color:#00798c; text-decoration:underline; font-weight:700;"><?php esc_html_e( 'Log In Here', 'tpd-tool' ); ?></a>
					</div>

					<!-- STEP 1: TELL US ABOUT YOURSELF -->
					<div class="tpd-step-pane tpd-wizard-pane active" data-step="1" data-step-pane="1">
						<h3 class="tpd-pane-heading"><?php esc_html_e( 'Tell Us About Yourself', 'tpd-tool' ); ?></h3>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="first_name" id="tpd_adv_first_name" required value="<?php echo esc_attr( $prefill_fname ); ?>" placeholder="<?php esc_attr_e( 'Enter First Name', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="last_name" id="tpd_adv_last_name" required value="<?php echo esc_attr( $prefill_lname ); ?>" placeholder="<?php esc_attr_e( 'Enter Last Name', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
						</div>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
								<input type="tel" name="phone" id="tpd_adv_phone" required value="<?php echo esc_attr( $prefill_phone ); ?>" placeholder="<?php esc_attr_e( 'Enter Phone Number Here', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Username *', 'tpd-tool' ); ?></label>
								<input type="text" name="username" id="tpd_adv_username" required value="<?php echo esc_attr( $prefill_username ); ?>" placeholder="<?php esc_attr_e( 'Example: TravelPro123', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
						</div>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Profile Handle *', 'tpd-tool' ); ?></label>
								<input type="text" name="profile_handle" id="tpd_adv_handle" required value="<?php echo esc_attr( $prefill_username ); ?>" placeholder="<?php esc_attr_e( 'Example: JaneDoe1', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
								<input type="email" name="email" id="tpd_adv_email" required value="<?php echo esc_attr( $prefill_email ); ?>" placeholder="<?php esc_attr_e( 'Enter Email Address', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
						</div>

						<div style="margin-top:28px;">
							<button type="button" class="tpd-btn-teal-outline tpd-wizard-next-btn" data-next="2">
								<?php esc_html_e( 'NEXT STEP', 'tpd-tool' ); ?> <i class="fa-solid fa-caret-right"></i>
							</button>
						</div>
					</div>

					<!-- STEP 2: ADVISOR REGISTRATION (AGENCY & CREDENTIALS) -->
					<div class="tpd-step-pane tpd-wizard-pane" data-step="2" data-step-pane="2">
						<h3 class="tpd-pane-heading"><?php esc_html_e( 'Advisor Registration — Agency & Credentials', 'tpd-tool' ); ?></h3>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Agency / Company Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="agency_name" required placeholder="<?php esc_attr_e( 'Enter Agency Name', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Country *', 'tpd-tool' ); ?></label>
								<select name="country" class="tpd-line-select" required>
									<option value=""><?php esc_html_e( 'Select Country', 'tpd-tool' ); ?></option>
									<?php foreach ( $countries as $c_name ) : ?>
										<option value="<?php echo esc_attr( $c_name ); ?>" <?php selected( $c_name, 'United States' ); ?>><?php echo esc_html( $c_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'City & State / Province *', 'tpd-tool' ); ?></label>
								<input type="text" name="location" id="tpd_advisor_location_input" required placeholder="<?php esc_attr_e( 'e.g. Miami, FL', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Street Address (Not Publicized)', 'tpd-tool' ); ?></label>
								<input type="text" name="agency_address" id="tpd_agency_address_input" placeholder="<?php esc_attr_e( 'Enter Street Address', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
						</div>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'When did you begin in the travel industry? *', 'tpd-tool' ); ?></label>
								<select name="years_experience" class="tpd-line-select" required>
									<?php for ( $yr = (int) gmdate( 'Y' ); $yr >= 1980; $yr-- ) : ?>
										<option value="<?php echo esc_attr( $yr ); ?>" <?php selected( $yr, 2018 ); ?>><?php echo esc_html( $yr ); ?></option>
									<?php endfor; ?>
								</select>
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'How many clients do you book per year? *', 'tpd-tool' ); ?></label>
								<select name="clients_per_year" class="tpd-line-select" required>
									<option value="1 to 50">1 to 50</option>
									<option value="51 to 100">51 to 100</option>
									<option value="101 to 250">101 to 250</option>
									<option value="250+">250+</option>
								</select>
							</div>
						</div>

						<div class="tpd-f-row-2">
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Previous Year (GROSS) Travel Sales Volume *', 'tpd-tool' ); ?></label>
								<select name="sales_volume" class="tpd-line-select" required>
									<option value="$0 - $100,000">$0 - $100,000</option>
									<option value="$100,000 - $250,000">$100,000 - $250,000</option>
									<option value="$250,000 - $500,000">$250,000 - $500,000</option>
									<option value="$500,000 - $1,000,000">$500,000 - $1,000,000</option>
									<option value="$1,000,000+">$1,000,000+</option>
								</select>
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Host Agency / Consortia Affiliation', 'tpd-tool' ); ?></label>
								<input type="text" name="host_agency" placeholder="<?php esc_attr_e( 'e.g. Virtuoso, KHM Travel, Independent', 'tpd-tool' ); ?>" class="tpd-line-input">
							</div>
						</div>

						<div class="tpd-f-group">
							<label><?php esc_html_e( 'Industry Certifications (Optional)', 'tpd-tool' ); ?></label>
							<div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:10px; margin-top:6px;">
								<input type="text" name="clia_num" placeholder="CLIA #" class="tpd-line-input">
								<input type="text" name="iata_num" placeholder="IATA #" class="tpd-line-input">
								<input type="text" name="arc_num" placeholder="ARC #" class="tpd-line-input">
								<input type="text" name="true_num" placeholder="TRUE #" class="tpd-line-input">
							</div>
						</div>

						<?php
						if ( class_exists( 'TPD_Tool_CPT' ) ) {
							TPD_Tool_CPT::render_dynamic_fields_html( 'travel_advisor', 0, 0, 'registration' );
						}
						?>

						<?php if ( 2 === $steps_mode ) : ?>
							<div class="tpd-f-row-2" style="margin-top:18px;">
								<div class="tpd-f-group">
									<label><?php esc_html_e( 'Password *', 'tpd-tool' ); ?></label>
									<input type="password" name="password" <?php echo $is_logged_in_supplier ? '' : 'required'; ?> placeholder="<?php esc_attr_e( 'Create Password (min 6 chars)', 'tpd-tool' ); ?>" class="tpd-line-input">
								</div>
								<div class="tpd-f-group">
									<label><?php esc_html_e( 'Confirm Password *', 'tpd-tool' ); ?></label>
									<input type="password" name="confirm_password" <?php echo $is_logged_in_supplier ? '' : 'required'; ?> placeholder="<?php esc_attr_e( 'Confirm Password', 'tpd-tool' ); ?>" class="tpd-line-input">
								</div>
							</div>
							<input type="hidden" name="tier" value="basic">
							<div style="display:flex; gap:14px; margin-top:26px;">
								<button type="button" class="tpd-btn-teal-outline tpd-wizard-prev-btn" data-prev="1" style="flex:1;">
									<i class="fa-solid fa-caret-left"></i> <?php esc_html_e( 'PREVIOUS', 'tpd-tool' ); ?>
								</button>
								<button type="submit" class="tpd-btn-teal-solid" style="flex:2;">
									<?php esc_html_e( 'COMPLETE REGISTRATION', 'tpd-tool' ); ?> <i class="fa-solid fa-check"></i>
								</button>
							</div>
						<?php else : ?>
							<div style="display:flex; gap:14px; margin-top:26px;">
								<button type="button" class="tpd-btn-teal-outline tpd-wizard-prev-btn" data-prev="1" style="flex:1;">
									<i class="fa-solid fa-caret-left"></i> <?php esc_html_e( 'PREVIOUS', 'tpd-tool' ); ?>
								</button>
								<button type="button" class="tpd-btn-teal-outline tpd-wizard-next-btn" data-next="3" style="flex:2;">
									<?php esc_html_e( 'NEXT STEP', 'tpd-tool' ); ?> <i class="fa-solid fa-caret-right"></i>
								</button>
							</div>
						<?php endif; ?>
					</div>

					<!-- STEP 3: ONE FINAL STEP (PASSWORD, PLAN & CHECKOUT) -->
					<?php if ( 3 === $steps_mode ) : ?>
					<div class="tpd-step-pane tpd-wizard-pane" data-step="3" data-step-pane="3">
						<h3 class="tpd-pane-heading"><?php esc_html_e( 'One Final Step — Account Security & Plan', 'tpd-tool' ); ?></h3>

						<!-- Dual-Portal Credential Mode Selector (Shown when upgrading a Supplier email to also have Advisor access) -->
						<div id="tpd-adv-dual-cred-box" style="<?php echo $is_logged_in_supplier ? 'display:block;' : 'display:none;'; ?> background:#f8fafc; border:1.5px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:22px;">
							<strong style="display:block; font-size:13.5px; color:#013243; margin-bottom:10px;">
								<i class="fa-solid fa-key" style="color:#00798c;"></i> <?php esc_html_e( 'Dual-Dashboard Login Preference', 'tpd-tool' ); ?>
							</strong>
							<label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; margin-bottom:8px; cursor:pointer;">
								<input type="radio" name="credential_mode" value="same" checked class="tpd-adv-cred-mode-radio">
								<span><?php esc_html_e( 'Use the SAME Username & Password as my Supplier account for both dashboards', 'tpd-tool' ); ?></span>
							</label>
							<label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#1e293b; cursor:pointer;">
								<input type="radio" name="credential_mode" value="separate" class="tpd-adv-cred-mode-radio">
								<span><?php esc_html_e( 'Create a DIFFERENT Username & Password specifically for my Advisor Dashboard', 'tpd-tool' ); ?></span>
							</label>

							<?php if ( ! $is_logged_in_supplier ) : ?>
								<div id="tpd-adv-verify-existing-pwd-wrap" style="margin-top:14px; padding-top:12px; border-top:1px dashed #cbd5e1;">
									<label style="font-size:12.5px; font-weight:700; color:#00798c; display:block; margin-bottom:4px;">
										<?php esc_html_e( 'Verify Your Current Supplier Password * (Required to link accounts)', 'tpd-tool' ); ?>
									</label>
									<input type="password" name="existing_account_password" id="tpd_adv_existing_pwd" placeholder="<?php esc_attr_e( 'Enter your existing Supplier password', 'tpd-tool' ); ?>" class="tpd-line-input">
								</div>
							<?php endif; ?>
						</div>

						<div class="tpd-f-row-2" id="tpd-adv-new-pwd-row" style="<?php echo $is_logged_in_supplier ? 'display:none;' : ''; ?>">
							<div class="tpd-f-group">
								<label id="tpd-adv-pwd-label"><?php esc_html_e( 'Password *', 'tpd-tool' ); ?></label>
								<div style="position:relative;">
									<input type="password" name="password" id="tpd_advisor_password" <?php echo $is_logged_in_supplier ? '' : 'required'; ?> placeholder="<?php esc_attr_e( 'Enter Password (min 6 chars)', 'tpd-tool' ); ?>" class="tpd-line-input" style="padding-right:34px;">
									<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:4px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
										<i class="fa-solid fa-eye"></i>
									</button>
								</div>
							</div>
							<div class="tpd-f-group">
								<label><?php esc_html_e( 'Confirm Password *', 'tpd-tool' ); ?></label>
								<div style="position:relative;">
									<input type="password" name="confirm_password" id="tpd_advisor_confirm_password" <?php echo $is_logged_in_supplier ? '' : 'required'; ?> placeholder="<?php esc_attr_e( 'Confirm Password', 'tpd-tool' ); ?>" class="tpd-line-input" style="padding-right:34px;">
									<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:4px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
										<i class="fa-solid fa-eye"></i>
									</button>
								</div>
							</div>
						</div>

						<div class="tpd-f-group" style="margin-top:16px;">
							<label><?php esc_html_e( 'Choose Your Advisor Membership Plan', 'tpd-tool' ); ?></label>
							<div class="tpd-plan-cards-row">
								<?php
								$first_plan = true;
								foreach ( $plans as $p_slug => $p_info ) :
									$m_price = isset( $p_info['monthly_price'] ) ? (float) $p_info['monthly_price'] : 0;
									$y_price = isset( $p_info['yearly_price'] ) ? (float) $p_info['yearly_price'] : ( isset( $p_info['annual_price'] ) ? (float) $p_info['annual_price'] : 0 );
									$features = isset( $p_info['features_list'] ) ? (array) $p_info['features_list'] : ( isset( $p_info['features'] ) ? (array) $p_info['features'] : array() );
								?>
									<label class="tpd-plan-card-option <?php echo $first_plan ? 'selected' : ''; ?>">
										<input type="radio" name="tier" class="tpd-plan-radio" value="<?php echo esc_attr( $p_slug ); ?>" data-monthly="<?php echo esc_attr( $m_price ); ?>" <?php checked( $first_plan ); ?> style="margin-right:6px;">
										<strong style="font-size:14.5px; color:#013243;"><?php echo esc_html( $p_info['name'] ); ?></strong>
										<div style="font-size:17px; font-weight:800; color:#00798c; margin:6px 0;">
											<?php echo $m_price <= 0 ? esc_html__( 'Free', 'tpd-tool' ) : esc_html( $currency_sym . number_format( $m_price, 2 ) . '/mo' ); ?>
										</div>
										<ul style="list-style:none; padding:0; margin:6px 0 0; font-size:11.5px; color:#475569;">
											<?php foreach ( array_slice( $features, 0, 3 ) as $f_line ) : ?>
												<li style="margin-bottom:3px;"><i class="fa-solid fa-check" style="color:#0d9488;"></i> <?php echo esc_html( $f_line ); ?></li>
											<?php endforeach; ?>
										</ul>
									</label>
								<?php
									$first_plan = false;
								endforeach;
								?>
							</div>
						</div>

						<!-- Payment Checkout Box (auto-toggles when paid plan selected) -->
						<div id="tpd-reg-payment-checkout" style="display:none; background:#f8fafc; border:1px solid #cbd5e1; border-radius:10px; padding:18px; margin-bottom:20px;">
							<h4 style="margin:0 0 12px; font-size:13.5px; color:#013243;"><i class="fa-solid fa-lock" style="color:#0d9488;"></i> <?php esc_html_e( 'Payment Method (Stripe / PayPal)', 'tpd-tool' ); ?></h4>
							<div class="tpd-f-row-2">
								<div class="tpd-f-group">
									<label><?php esc_html_e( 'Select Gateway', 'tpd-tool' ); ?></label>
									<select name="payment_gateway" class="tpd-line-select">
										<option value="Stripe">Credit / Debit Card (Stripe)</option>
										<option value="PayPal">PayPal Checkout</option>
									</select>
								</div>
								<div class="tpd-f-group">
									<label><?php esc_html_e( 'Billing Cycle', 'tpd-tool' ); ?></label>
									<select name="billing_cycle" class="tpd-line-select">
										<option value="Month">Monthly Billing</option>
										<option value="Year">Annual Billing</option>
									</select>
								</div>
							</div>
						</div>

						<div style="display:flex; gap:14px; margin-top:24px;">
							<button type="button" class="tpd-btn-teal-outline tpd-wizard-prev-btn" data-prev="2" style="flex:1;">
								<i class="fa-solid fa-caret-left"></i> <?php esc_html_e( 'PREVIOUS', 'tpd-tool' ); ?>
							</button>
							<button type="submit" class="tpd-btn-teal-solid" id="tpd-advisor-reg-btn" style="flex:2;">
								<?php esc_html_e( 'COMPLETE REGISTRATION', 'tpd-tool' ); ?> <i class="fa-solid fa-caret-right"></i>
							</button>
						</div>
					</div>
					<?php endif; ?>

					<div class="tpd-reg-status" style="display:none;"></div>
				</form>

				<!-- Footer Links inside Card -->
				<div class="tpd-card-Support-links">
					<span><?php esc_html_e( 'Already Registered ?', 'tpd-tool' ); ?> <a href="<?php echo esc_url( home_url( '/advisor-login/' ) ); ?>"><?php esc_html_e( 'Login', 'tpd-tool' ); ?></a></span>
					<a href="mailto:support@travelpartnerdirectory.com"><?php esc_html_e( 'Contact Support', 'tpd-tool' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/advisor-dashboard/#help' ) ); ?>"><?php esc_html_e( 'How to Guide', 'tpd-tool' ); ?></a>
				</div>
			</div>

			<!-- Bottom Switcher Bar -->
			<a href="<?php echo esc_url( home_url( '/supplier-registration/' ) ); ?>" class="tpd-bottom-switch-bar">
				<?php esc_html_e( 'LOOKING FOR SUPPLIER REGISTRATION? CLICK HERE', 'tpd-tool' ); ?>
			</a>
		</div>
	</main>
</div>

<script>
(function() {
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce = '<?php echo esc_js( wp_create_nonce( 'tpd_nonce' ) ); ?>';
	var sameRoleBlocked = false;

	function activateCrossRoleAdvisorMode(data) {
		document.getElementById('tpd_adv_confirm_cross_role').value = '1';
		var banner = document.getElementById('tpd-adv-cross-role-banner');
		if (banner) banner.style.display = 'block';
		var dualCredBox = document.getElementById('tpd-adv-dual-cred-box');
		if (dualCredBox) dualCredBox.style.display = 'block';

		if (data) {
			var fn = document.getElementById('tpd_adv_first_name');
			var ln = document.getElementById('tpd_adv_last_name');
			var ph = document.getElementById('tpd_adv_phone');
			var un = document.getElementById('tpd_adv_username');
			var hd = document.getElementById('tpd_adv_handle');
			if (fn && !fn.value && data.first_name) fn.value = data.first_name;
			if (ln && !ln.value && data.last_name) ln.value = data.last_name;
			if (ph && !ph.value && data.phone) ph.value = data.phone;
			if (un && !un.value && data.existing_username) un.value = data.existing_username;
			if (hd && !hd.value && data.existing_username) hd.value = data.existing_username;
		}
		updateAdvisorCredentialFields();
	}

	function updateAdvisorCredentialFields() {
		var isCrossRole = document.getElementById('tpd_adv_confirm_cross_role').value === '1';
		var checkedRadio = document.querySelector('input.tpd-adv-cred-mode-radio:checked');
		var mode = checkedRadio ? checkedRadio.value : 'same';
		var pwdRow = document.getElementById('tpd-adv-new-pwd-row');
		var pwdInp = document.getElementById('tpd_advisor_password');
		var cnfInp = document.getElementById('tpd_advisor_confirm_password');
		var pwdLbl = document.getElementById('tpd-adv-pwd-label');

		if (!isCrossRole) {
			if (pwdRow) pwdRow.style.display = '';
			if (pwdInp) pwdInp.required = true;
			if (cnfInp) cnfInp.required = true;
			return;
		}

		if (mode === 'same') {
			if (pwdRow) pwdRow.style.display = 'none';
			if (pwdInp) { pwdInp.required = false; pwdInp.value = ''; }
			if (cnfInp) { cnfInp.required = false; cnfInp.value = ''; }
		} else {
			if (pwdRow) pwdRow.style.display = '';
			if (pwdLbl) pwdLbl.textContent = 'New Separate Advisor Password *';
			if (pwdInp) pwdInp.required = true;
			if (cnfInp) cnfInp.required = true;
		}
	}

	document.querySelectorAll('.tpd-adv-cred-mode-radio').forEach(function(r) {
		r.addEventListener('change', updateAdvisorCredentialFields);
	});

	var acceptBtn = document.getElementById('tpd-adv-cr-accept-btn');
	if (acceptBtn) {
		acceptBtn.addEventListener('click', function() {
			document.getElementById('tpd-adv-cr-actions').style.display = 'none';
			activateCrossRoleAdvisorMode();
		});
	}

	function checkAdvisorEmail(email, callback) {
		if (!email || email.indexOf('@') === -1) {
			if (callback) callback('available');
			return;
		}
		var fd = new FormData();
		fd.append('action', 'tpd_check_registration_email');
		fd.append('email', email);
		fd.append('target_role', 'travel_advisor');
		fd.append('nonce', nonce);

		fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				var sameAlert = document.getElementById('tpd-adv-same-role-alert');
				var crossBanner = document.getElementById('tpd-adv-cross-role-banner');
				if (res.success && res.data) {
					if (res.data.status === 'same_role_exists') {
						sameRoleBlocked = true;
						if (crossBanner) crossBanner.style.display = 'none';
						if (sameAlert) {
							document.getElementById('tpd-adv-same-role-msg').textContent = res.data.message;
							sameAlert.style.display = 'block';
						}
						if (callback) callback('same_role_exists');
					} else if (res.data.status === 'cross_role_available') {
						sameRoleBlocked = false;
						if (sameAlert) sameAlert.style.display = 'none';
						activateCrossRoleAdvisorMode(res.data);
						if (callback) callback('cross_role_available');
					} else {
						sameRoleBlocked = false;
						if (sameAlert) sameAlert.style.display = 'none';
						if (crossBanner) crossBanner.style.display = 'none';
						document.getElementById('tpd_adv_confirm_cross_role').value = '0';
						var dualCredBox = document.getElementById('tpd-adv-dual-cred-box');
						if (dualCredBox) dualCredBox.style.display = 'none';
						updateAdvisorCredentialFields();
						if (callback) callback('available');
					}
				} else if (callback) {
					callback('available');
				}
			})
			.catch(function() {
				if (callback) callback('available');
			});
	}

	var emailInp = document.getElementById('tpd_adv_email');
	if (emailInp) {
		emailInp.addEventListener('blur', function() {
			checkAdvisorEmail(emailInp.value.trim());
		});
		if (emailInp.value.trim()) {
			checkAdvisorEmail(emailInp.value.trim());
		}
	}

	function switchAdvisorStep(targetStep) {
		var form = document.getElementById('tpd-advisor-registration-form');
		if (!form) return;
		var panes = form.querySelectorAll('.tpd-step-pane');
		panes.forEach(function(p) {
			var s = parseInt(p.getAttribute('data-step') || p.getAttribute('data-step-pane'), 10);
			if (s === targetStep) {
				p.classList.add('active');
			} else {
				p.classList.remove('active');
			}
		});
		var steps = document.querySelectorAll('#tpd-advisor-stepper .tpd-v-step');
		steps.forEach(function(st) {
			var s = parseInt(st.getAttribute('data-step'), 10);
			st.classList.remove('active', 'done');
			if (s < targetStep) st.classList.add('done');
			else if (s === targetStep) st.classList.add('active');
		});
	}

	document.addEventListener('click', function(e) {
		var nextBtn = e.target.closest('#tpd-advisor-registration-form .tpd-wizard-next-btn');
		if (nextBtn) {
			e.preventDefault();
			e.stopImmediatePropagation();
			var currentPane = nextBtn.closest('.tpd-step-pane');
			var nextStep = parseInt(nextBtn.getAttribute('data-next'), 10);
			var requiredFields = currentPane.querySelectorAll('input[required], select[required], textarea[required]');
			var valid = true;
			var firstBad = null;
			requiredFields.forEach(function(inp) {
				if (!inp.value || !inp.value.trim()) {
					valid = false;
					inp.style.borderBottomColor = '#dc2626';
					if (!firstBad) firstBad = inp;
				} else {
					inp.style.borderBottomColor = '#334155';
				}
			});
			if (!valid) {
				if (firstBad) firstBad.focus();
				return;
			}

			if (nextStep === 2 && emailInp) {
				checkAdvisorEmail(emailInp.value.trim(), function(status) {
					if (status !== 'same_role_exists') {
						switchAdvisorStep(nextStep);
					}
				});
				return;
			}

			switchAdvisorStep(nextStep);
			return;
		}

		var prevBtn = e.target.closest('#tpd-advisor-registration-form .tpd-wizard-prev-btn');
		if (prevBtn) {
			e.preventDefault();
			e.stopImmediatePropagation();
			var prevStep = parseInt(prevBtn.getAttribute('data-prev'), 10);
			switchAdvisorStep(prevStep);
		}
	}, true);
})();
</script>

<?php
if ( $is_standalone ) {
	wp_footer();
	?>
	</body>
	</html>
	<?php
}
?>
