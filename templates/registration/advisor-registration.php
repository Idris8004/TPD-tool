<?php
/**
 * Travel Advisor (Pro User) Multi-Step Registration Form Template
 * Dynamically configured by Super Admin (Steps, Dropdown Options, Plans, Payment Gateways & Custom/ACF Fields).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_cfg = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_form_config() : array();
$plans    = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'advisor' ) : array();
$payments = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_payment_settings() : array();
$steps_mode = isset( $form_cfg['advisor_steps'] ) && 2 === (int) $form_cfg['advisor_steps'] ? 2 : 3;
$currency_sym = isset( $payments['currency_symbol'] ) ? $payments['currency_symbol'] : '$';

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
	<body class="tpd-app-body" style="background:#f1f5f9; padding:40px 16px; margin:0; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;">
	<?php
}
?>
<style>
	.tpd-registration-wrapper { max-width: 900px; margin: 32px auto; padding: 0 16px; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: #0f172a; }
	.tpd-reg-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 40px 44px; box-shadow: 0 16px 40px -8px rgba(11, 21, 38, 0.08); }
	.tpd-reg-header { text-align: center; margin-bottom: 28px; }
	.tpd-reg-badge { display: inline-flex; align-items: center; gap: 6px; background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; padding: 5px 14px; border-radius: 999px; margin-bottom: 10px; }
	.tpd-reg-header h2 { font-size: 26px; font-weight: 800; color: #0b1526; margin: 0 0 8px; }
	.tpd-reg-header p { color: #64748b; font-size: 14.5px; margin: 0 auto; max-width: 620px; line-height: 1.5; }
	/* Multi-Step Wizard Progress Bar */
	.tpd-wizard-steps { display: flex; justify-content: space-between; align-items: center; margin: 0 0 32px; position: relative; padding: 0 12px; }
	.tpd-wizard-steps::before { content: ''; position: absolute; top: 18px; left: 40px; right: 40px; height: 3px; background: #e2e8f0; z-index: 1; }
	.tpd-w-step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 6px; background: #ffffff; padding: 0 12px; cursor: pointer; }
	.tpd-w-circle { width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; border: 2px solid #cbd5e1; color: #64748b; font-weight: 800; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
	.tpd-w-label { font-size: 12px; font-weight: 700; color: #64748b; }
	.tpd-w-step.active .tpd-w-circle { background: #2563eb; border-color: #2563eb; color: #ffffff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15); }
	.tpd-w-step.active .tpd-w-label { color: #0b1526; }
	.tpd-w-step.completed .tpd-w-circle { background: #10b981; border-color: #10b981; color: #ffffff; }
	.tpd-w-step.completed .tpd-w-label { color: #047857; }
	.tpd-step-pane { display: none; animation: tpdFadeIn 0.25s ease; }
	.tpd-step-pane.active { display: block; }
	@keyframes tpdFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
	.tpd-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
	.tpd-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
	.tpd-form-group label { font-size: 13px; font-weight: 600; color: #334155; }
	.tpd-input, .tpd-select, .tpd-textarea { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 14px; font-size: 13.5px; color: #0f172a; background: #fff; width: 100%; box-sizing: border-box; outline: none; }
	.tpd-input:focus, .tpd-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
	.tpd-wizard-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
	.tpd-tier-selection-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin-top: 12px; }
	.tpd-tier-option { position: relative; border: 2px solid #e2e8f0; border-radius: 14px; padding: 20px; cursor: pointer; background: #fff; display: block; transition: all 0.2s; }
	.tpd-tier-option:has(input:checked) { border-color: #2563eb; background: #f8fbff; box-shadow: 0 8px 20px rgba(37,99,235,0.08); }
	.tpd-payment-box { margin-top: 22px; border: 1px solid #cbd5e1; border-radius: 14px; padding: 22px; background: #f8fafc; }
	@media (max-width: 768px) { .tpd-reg-card { padding: 24px 20px; } .tpd-form-grid-2 { grid-template-columns: 1fr; } }
</style>

<?php
if ( is_user_logged_in() && ! isset( $_GET['preview_form'] ) ) {
	$current_user = wp_get_current_user();
	?>
	<div class="tpd-registration-wrapper">
		<div class="tpd-reg-already-logged-in" style="background:#fff; padding:40px; border-radius:16px; border:1px solid #e2e8f0; text-align:center;">
			<i class="fa-solid fa-circle-check" style="font-size:46px; color:#10b981; margin-bottom:14px;"></i>
			<h3><?php printf( esc_html__( 'You are currently signed in as %s', 'tpd-tool' ), esc_html( $current_user->display_name ) ); ?></h3>
			<p style="color:#64748b; margin-bottom:22px;"><?php esc_html_e( 'Access your Advisor Dashboard or preview the registration form below.', 'tpd-tool' ); ?></p>
			<div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
				<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-darkblue" style="text-decoration:none;">
					<i class="fa-solid fa-gauge-high"></i> <?php esc_html_e( 'Go to Advisor Dashboard', 'tpd-tool' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'preview_form', '1' ) ); ?>" class="tpd-btn tpd-btn-outline" style="text-decoration:none; padding:10px 20px; border:1px solid #cbd5e1; border-radius:8px; color:#0f172a; font-weight:600;">
					<i class="fa-regular fa-eye"></i> <?php esc_html_e( 'Preview Registration Form', 'tpd-tool' ); ?>
				</a>
			</div>
		</div>
	</div>
	<?php
	if ( $is_standalone ) {
		wp_footer();
		echo '</body></html>';
	}
	return;
}
?>

<div class="tpd-registration-wrapper">
	<div class="tpd-reg-card">
		<!-- Header -->
		<div class="tpd-reg-header">
			<div class="tpd-reg-badge"><i class="fa-solid fa-compass"></i> TRAVEL PARTNER DIRECTORY BY TARC</div>
			<h2><?php echo esc_html( ! empty( $form_cfg['advisor_title'] ) ? $form_cfg['advisor_title'] : __( 'Join as a Verified Travel Advisor', 'tpd-tool' ) ); ?></h2>
			<p><?php echo esc_html( ! empty( $form_cfg['advisor_subtitle'] ) ? $form_cfg['advisor_subtitle'] : __( 'Get discovered by high-intent travelers, connect directly with supplier BDMs, and grow your high-yield bookings.', 'tpd-tool' ) ); ?></p>
		</div>

		<!-- Multi-Step Progress Header -->
		<div class="tpd-wizard-steps" id="tpd-adv-wizard-steps">
			<div class="tpd-w-step active" data-step="1">
				<div class="tpd-w-circle">1</div>
				<span class="tpd-w-label"><?php esc_html_e( 'Personal & Login', 'tpd-tool' ); ?></span>
			</div>
			<div class="tpd-w-step" data-step="2">
				<div class="tpd-w-circle">2</div>
				<span class="tpd-w-label"><?php esc_html_e( 'Agency & Credentials', 'tpd-tool' ); ?></span>
			</div>
			<?php if ( 3 === $steps_mode ) : ?>
			<div class="tpd-w-step" data-step="3">
				<div class="tpd-w-circle">3</div>
				<span class="tpd-w-label"><?php esc_html_e( 'Plan & Checkout', 'tpd-tool' ); ?></span>
			</div>
			<?php endif; ?>
		</div>

		<!-- Registration Form -->
		<form id="tpd-advisor-registration-form" class="tpd-reg-form" method="post">
			<input type="hidden" name="action" value="tpd_register_advisor">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

			<!-- STEP 1: PERSONAL & LOGIN DETAILS -->
			<div class="tpd-step-pane active" data-step-pane="1">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-user-shield"></i> <?php esc_html_e( 'Step 1: Personal & Account Login Details', 'tpd-tool' ); ?></h3>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="first_name" required placeholder="<?php esc_attr_e( 'Enter your first name', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="last_name" required placeholder="<?php esc_attr_e( 'Enter your last name', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
						<input type="email" name="email" required placeholder="<?php esc_attr_e( 'advisor@youragency.com', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
						<div style="display:flex; gap:8px;">
							<select name="phone_country_code" class="tpd-select" style="max-width: 110px;">
								<option value="+1">+1 (US/CA)</option>
								<option value="+44">+44 (UK)</option>
								<option value="+61">+61 (AU)</option>
								<option value="+33">+33 (FR)</option>
								<option value="+49">+49 (DE)</option>
								<option value="+39">+39 (IT)</option>
								<option value="+34">+34 (ES)</option>
							</select>
							<input type="tel" name="phone" required placeholder="(555) 000-0000" class="tpd-input" style="flex:1;">
						</div>
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Username *', 'tpd-tool' ); ?></label>
						<input type="text" name="username" required placeholder="<?php esc_attr_e( 'Choose a unique username', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Advisor Location *', 'tpd-tool' ); ?></label>
						<input type="text" name="location" id="tpd_advisor_location_input" required placeholder="<?php esc_attr_e( 'City, State/Province, Country', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Password *', 'tpd-tool' ); ?></label>
						<div style="position:relative;">
							<input type="password" name="password" id="tpd_advisor_password" required placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'tpd-tool' ); ?>" class="tpd-input" style="padding-right:40px;">
							<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
								<i class="fa-solid fa-eye"></i>
							</button>
						</div>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Confirm Password *', 'tpd-tool' ); ?></label>
						<div style="position:relative;">
							<input type="password" name="confirm_password" id="tpd_advisor_confirm_password" required placeholder="<?php esc_attr_e( 'Re-enter your password', 'tpd-tool' ); ?>" class="tpd-input" style="padding-right:40px;">
							<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
								<i class="fa-solid fa-eye"></i>
							</button>
						</div>
					</div>
				</div>

				<div class="tpd-wizard-nav" style="justify-content:flex-end;">
					<button type="button" class="tpd-btn tpd-btn-darkblue tpd-wizard-next-btn" data-next="2">
						<?php esc_html_e( 'Next: Agency & Credentials', 'tpd-tool' ); ?> <i class="fa-solid fa-arrow-right"></i>
					</button>
				</div>
			</div>

			<!-- STEP 2: AGENCY DETAILS & INDUSTRY CREDENTIALS -->
			<div class="tpd-step-pane" data-step-pane="2">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-award"></i> <?php esc_html_e( 'Step 2: Agency Details & Industry Credentials', 'tpd-tool' ); ?></h3>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Agency Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="agency_name" required placeholder="<?php esc_attr_e( 'e.g. Horizon Luxury Travel', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<div style="display:flex; justify-content:space-between; align-items:center;">
							<label><?php esc_html_e( 'Agency Address *', 'tpd-tool' ); ?></label>
							<button type="button" id="tpd-btn-same-address" style="font-size:12px; color:#2563eb; background:none; border:none; cursor:pointer; text-decoration:underline; padding:0;">
								<i class="fa-regular fa-copy"></i> <?php esc_html_e( 'Same as advisor location', 'tpd-tool' ); ?>
							</button>
						</div>
						<input type="text" name="agency_address" id="tpd_agency_address_input" required placeholder="<?php esc_attr_e( 'Street, Suite, City, State, Postal Code', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Your Role *', 'tpd-tool' ); ?></label>
						<select name="business_structure" class="tpd-select" required>
							<?php
							$roles = ! empty( $form_cfg['advisor_roles'] ) ? $form_cfg['advisor_roles'] : array( 'Agency Owner', 'Travel Advisor - Independent Contractor', 'Travel Advisor - Employee', 'Agency Manager/Leader', 'Other' );
							foreach ( $roles as $r_opt ) :
							?>
								<option value="<?php echo esc_attr( $r_opt ); ?>"><?php echo esc_html( $r_opt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Agency Structure *', 'tpd-tool' ); ?></label>
						<select name="agency_structure" class="tpd-select" required>
							<?php
							$structs = ! empty( $form_cfg['agency_structures'] ) ? $form_cfg['agency_structures'] : array( 'Independent Agency (no host)', 'Host Agency', 'Hosted Agency', 'Franchise', 'Other' );
							foreach ( $structs as $s_opt ) :
							?>
								<option value="<?php echo esc_attr( $s_opt ); ?>"><?php echo esc_html( $s_opt ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Years in the Travel Industry', 'tpd-tool' ); ?></label>
						<select name="years_experience" class="tpd-select">
							<option value="0–2 years">0 – 2 years</option>
							<option value="3–5 years" selected>3 – 5 years</option>
							<option value="6–10 years">6 – 10 years</option>
							<option value="10+ years">10+ years</option>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'How many advisors are part of your agency? *', 'tpd-tool' ); ?></label>
						<select name="agency_advisors_count" class="tpd-select" required>
							<option value="Just me">Just me</option>
							<option value="2-5" selected>2 - 5</option>
							<option value="6-9">6 - 9</option>
							<option value="10-24">10 - 24</option>
							<option value="25-50">25 - 50</option>
							<option value="50-99">50 - 99</option>
							<option value="100+">100+</option>
						</select>
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'PERSONAL Estimated Annual Sales Volume *', 'tpd-tool' ); ?></label>
						<select name="sales_volume" class="tpd-select" required>
							<?php
							$p_vols = ! empty( $form_cfg['personal_sales_options'] ) ? $form_cfg['personal_sales_options'] : array( '$0-$250,000', '$250,000-$500,000', '$500,000-$750,000', '$750,000-$1M', '$1M+' );
							foreach ( $p_vols as $pv ) :
							?>
								<option value="<?php echo esc_attr( $pv ); ?>"><?php echo esc_html( $pv ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'AGENCY Estimated Annual Sales Volume *', 'tpd-tool' ); ?></label>
						<select name="agency_sales_volume" class="tpd-select" required>
							<?php
							$a_vols = ! empty( $form_cfg['agency_sales_options'] ) ? $form_cfg['agency_sales_options'] : array( '$0-$250,000', '$250,000-$500,000', '$500,000-$1M', '$1M-$3M', '$3M+' );
							foreach ( $a_vols as $av ) :
							?>
								<option value="<?php echo esc_attr( $av ); ?>"><?php echo esc_html( $av ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Consortia (if affiliated)', 'tpd-tool' ); ?></label>
						<select name="consortia" class="tpd-select">
							<option value=""><?php esc_html_e( 'None / Independent', 'tpd-tool' ); ?></option>
							<?php
							$consortia_opts = ! empty( $form_cfg['consortia_options'] ) ? $form_cfg['consortia_options'] : array( 'Virtuoso', 'Signature Travel Network', 'Travel Leaders Network', 'Ensemble Travel Group', 'Other Consortia' );
							foreach ( $consortia_opts as $co ) :
							?>
								<option value="<?php echo esc_attr( $co ); ?>"><?php echo esc_html( $co ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Host Agency / Franchise (if affiliated)', 'tpd-tool' ); ?></label>
						<select name="host_agency" class="tpd-select">
							<option value=""><?php esc_html_e( 'None / Direct', 'tpd-tool' ); ?></option>
							<?php
							$host_opts = ! empty( $form_cfg['host_agency_options'] ) ? $form_cfg['host_agency_options'] : array( 'Avoya Travel', 'Travel Planners International (TPI)', 'KHM Travel Group', 'Nexion Travel Group', 'Cruise Planners', 'Other Host' );
							foreach ( $host_opts as $ho ) :
							?>
								<option value="<?php echo esc_attr( $ho ); ?>"><?php echo esc_html( $ho ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<!-- Industry Accreditations -->
				<div class="tpd-form-group mt-2">
					<label class="font-bold"><?php esc_html_e( 'Industry Accreditation Type & Number', 'tpd-tool' ); ?></label>
					<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap:12px; margin-top:6px;">
						<div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_clia" value="1"> CLIA #
							</label>
							<input type="text" name="clia_num" placeholder="Enter CLIA ID" class="tpd-input" style="font-size:12.5px; padding:7px 10px;">
						</div>
						<div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_iata" value="1"> IATA / IATAN #
							</label>
							<input type="text" name="iata_num" placeholder="Enter IATA ID" class="tpd-input" style="font-size:12.5px; padding:7px 10px;">
						</div>
						<div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_arc" value="1"> ARC #
							</label>
							<input type="text" name="arc_num" placeholder="Enter ARC ID" class="tpd-input" style="font-size:12.5px; padding:7px 10px;">
						</div>
						<div style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_true" value="1"> TRUE #
							</label>
							<input type="text" name="true_num" placeholder="Enter TRUE ID" class="tpd-input" style="font-size:12.5px; padding:7px 10px;">
						</div>
					</div>
				</div>

				<!-- Dynamic Admin Custom Fields & ACF Fields -->
				<?php
				if ( class_exists( 'TPD_Tool_CPT' ) ) {
					TPD_Tool_CPT::render_dynamic_fields_html( 'travel_advisor', 0, 0, 'registration' );
				}
				?>

				<?php if ( 2 === $steps_mode ) : ?>
					<!-- If Admin configured 2-Step mode, include plan selection here -->
					<input type="hidden" name="tier" value="basic">
					<div class="tpd-wizard-nav">
						<button type="button" class="tpd-btn tpd-btn-outline tpd-wizard-prev-btn" data-prev="1">
							<i class="fa-solid fa-arrow-left"></i> <?php esc_html_e( 'Back', 'tpd-tool' ); ?>
						</button>
						<button type="submit" class="tpd-btn tpd-btn-darkblue" id="tpd-advisor-reg-btn">
							<i class="fa-solid fa-check-circle"></i> <?php esc_html_e( 'Complete Registration & Open Dashboard', 'tpd-tool' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="tpd-wizard-nav">
						<button type="button" class="tpd-btn tpd-btn-outline tpd-wizard-prev-btn" data-prev="1">
							<i class="fa-solid fa-arrow-left"></i> <?php esc_html_e( 'Back', 'tpd-tool' ); ?>
						</button>
						<button type="button" class="tpd-btn tpd-btn-darkblue tpd-wizard-next-btn" data-next="3">
							<?php esc_html_e( 'Next: Select Membership Plan', 'tpd-tool' ); ?> <i class="fa-solid fa-arrow-right"></i>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<!-- STEP 3: DYNAMIC MEMBERSHIP PLAN SELECTION & PAYMENT CHECKOUT -->
			<?php if ( 3 === $steps_mode ) : ?>
			<div class="tpd-step-pane" data-step-pane="3">
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
					<h3 class="tpd-fs-title" style="margin:0;"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'Step 3: Select Your Membership Plan', 'tpd-tool' ); ?></h3>
					<!-- Billing Cycle Selector -->
					<div style="display:inline-flex; background:#f1f5f9; padding:4px; border-radius:10px; border:1px solid #cbd5e1;">
						<label style="padding:6px 14px; border-radius:8px; cursor:pointer; font-size:12.5px; font-weight:700;">
							<input type="radio" name="billing_cycle" value="Month" checked class="tpd-billing-cycle-radio"> <?php esc_html_e( 'Monthly Billing', 'tpd-tool' ); ?>
						</label>
						<label style="padding:6px 14px; border-radius:8px; cursor:pointer; font-size:12.5px; font-weight:700; color:#047857;">
							<input type="radio" name="billing_cycle" value="Year" class="tpd-billing-cycle-radio"> <?php esc_html_e( 'Annual Billing (Save 20%)', 'tpd-tool' ); ?>
						</label>
					</div>
				</div>

				<div class="tpd-tier-selection-grid">
					<?php
					$first_plan = true;
					foreach ( $plans as $p_slug => $p_info ) :
						$m_price = (float) $p_info['monthly_price'];
						$y_price = (float) $p_info['yearly_price'];
						$is_feat = ! empty( $p_info['featured'] );
					?>
						<label class="tpd-tier-option <?php echo $is_feat ? 'tpd-tier-featured' : ''; ?>" data-price="<?php echo esc_attr( $m_price ); ?>">
							<input type="radio" name="tier" value="<?php echo esc_attr( $p_slug ); ?>" data-monthly="<?php echo esc_attr( $m_price ); ?>" data-yearly="<?php echo esc_attr( $y_price ); ?>" <?php checked( $first_plan ); ?>>
							<div class="tpd-to-content">
								<span class="tpd-to-tag <?php echo $is_feat ? 'tpd-to-gold' : ''; ?>">
									<?php if ( $is_feat ) : ?><i class="fa-solid fa-crown"></i> <?php endif; ?>
									<?php echo esc_html( ! empty( $p_info['badge'] ) ? $p_info['badge'] : 'Membership' ); ?>
								</span>
								<h4><?php echo esc_html( $p_info['name'] ); ?></h4>
								<span class="tpd-to-price">
									<?php if ( $m_price <= 0 ) : ?>
										<?php echo esc_html( $currency_sym . '0' ); ?> <span>/ Free</span>
									<?php else : ?>
										<?php echo esc_html( $currency_sym . number_format( $m_price, 2 ) ); ?> <span>/ mo (<?php echo esc_html( $currency_sym . number_format( $y_price, 0 ) ); ?>/yr)</span>
									<?php endif; ?>
								</span>
								<ul class="tpd-to-features">
									<?php foreach ( (array) $p_info['features_list'] as $feat_line ) : ?>
										<li><i class="fa-solid fa-check text-green"></i> <?php echo esc_html( $feat_line ); ?></li>
									<?php endforeach; ?>
								</ul>
							</div>
						</label>
					<?php
						$first_plan = false;
					endforeach;
					?>
				</div>

				<!-- Integrated Payment Gateway Box (Shown automatically when a Paid Plan is chosen) -->
				<div id="tpd-reg-payment-checkout" class="tpd-payment-box" style="display:none;">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
						<h4 style="margin:0; font-size:15px; color:#0b1526;"><i class="fa-solid fa-lock text-green"></i> <?php esc_html_e( 'Secure Membership Checkout', 'tpd-tool' ); ?></h4>
						<span style="font-size:11.5px; background:#dcfce7; color:#166534; padding:3px 10px; border-radius:999px; font-weight:700;">
							<?php echo ( isset( $payments['mode'] ) && 'live' === $payments['mode'] ) ? 'Live Gateway Active' : 'Sandbox / Instant Approval Mode'; ?>
						</span>
					</div>

					<div class="tpd-form-grid-2">
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Select Payment Method', 'tpd-tool' ); ?></label>
							<select name="payment_gateway" class="tpd-select">
								<?php if ( ! empty( $payments['stripe_enabled'] ) ) : ?>
									<option value="Stripe">Credit / Debit Card (Stripe)</option>
								<?php endif; ?>
								<?php if ( ! empty( $payments['paypal_enabled'] ) ) : ?>
									<option value="PayPal">PayPal Express Checkout</option>
								<?php endif; ?>
								<option value="Invoice / Direct">Agency Invoice / Instant Activation</option>
							</select>
						</div>
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Cardholder Name', 'tpd-tool' ); ?></label>
							<input type="text" name="cardholder_name" placeholder="<?php esc_attr_e( 'Name on card', 'tpd-tool' ); ?>" class="tpd-input">
						</div>
					</div>

					<div class="tpd-form-grid-2">
						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Card Number (Test Mode: Optional)', 'tpd-tool' ); ?></label>
							<input type="text" placeholder="•••• •••• •••• 4242" class="tpd-input">
						</div>
						<div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Expiry', 'tpd-tool' ); ?></label>
								<input type="text" placeholder="MM / YY" class="tpd-input">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'CVC', 'tpd-tool' ); ?></label>
								<input type="text" placeholder="123" class="tpd-input">
							</div>
						</div>
					</div>
				</div>

				<div class="tpd-wizard-nav">
					<button type="button" class="tpd-btn tpd-btn-outline tpd-wizard-prev-btn" data-prev="2">
						<i class="fa-solid fa-arrow-left"></i> <?php esc_html_e( 'Back to Agency Info', 'tpd-tool' ); ?>
					</button>
					<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-btn-lg" id="tpd-advisor-reg-btn">
						<i class="fa-solid fa-arrow-right-to-bracket"></i> <?php esc_html_e( 'Complete Registration & Open Dashboard', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>
			<?php endif; ?>

			<!-- Status Messages -->
			<div class="tpd-reg-status" style="display:none;"></div>
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
