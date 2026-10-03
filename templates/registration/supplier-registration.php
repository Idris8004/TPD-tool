<?php
/**
 * Supplier Partner Multi-Step Registration Form Template
 * Includes exact client-requested fields:
 * First Name *, Last Name *, Phone Number *, Email Address *, Username *,
 * Position/Title, Supplier/Company Name *, Country *, Password *, Confirm Password *
 * Plus Admin-configurable Plans, Dropdowns & Dynamic Custom/ACF Fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_cfg  = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_form_config() : array();
$plans     = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans( 'supplier' ) : array();
$countries = ! empty( $form_cfg['countries'] ) ? $form_cfg['countries'] : array( 'United States', 'Canada', 'United Kingdom', 'Australia', 'Germany', 'France', 'Italy', 'Spain', 'Switzerland', 'Other' );

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
?>
<style>
	.tpd-registration-wrapper { max-width: 860px; margin: 32px auto; padding: 0 16px; font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; color: #0f172a; }
	.tpd-reg-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 40px 44px; box-shadow: 0 16px 40px -8px rgba(11, 21, 38, 0.08); }
	.tpd-reg-header { text-align: center; margin-bottom: 28px; }
	.tpd-reg-badge { display: inline-flex; align-items: center; gap: 6px; background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; padding: 5px 14px; border-radius: 999px; margin-bottom: 10px; }
	.tpd-reg-header h2 { font-size: 26px; font-weight: 800; color: #0b1526; margin: 0 0 8px; }
	.tpd-reg-header p { color: #64748b; font-size: 14.5px; margin: 0 auto; max-width: 620px; line-height: 1.5; }
	.tpd-wizard-steps { display: flex; justify-content: center; gap: 80px; align-items: center; margin: 0 0 32px; position: relative; }
	.tpd-w-step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; gap: 6px; background: #ffffff; padding: 0 12px; cursor: pointer; }
	.tpd-w-circle { width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; border: 2px solid #cbd5e1; color: #64748b; font-weight: 800; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
	.tpd-w-label { font-size: 12px; font-weight: 700; color: #64748b; }
	.tpd-w-step.active .tpd-w-circle { background: #2563eb; border-color: #2563eb; color: #ffffff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15); }
	.tpd-w-step.active .tpd-w-label { color: #0b1526; }
	.tpd-w-step.completed .tpd-w-circle { background: #10b981; border-color: #10b981; color: #ffffff; }
	.tpd-step-pane { display: none; }
	.tpd-step-pane.active { display: block; }
	.tpd-form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px; }
	.tpd-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; }
	.tpd-form-group label { font-size: 13px; font-weight: 600; color: #334155; }
	.tpd-input, .tpd-select, .tpd-textarea { border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 14px; font-size: 13.5px; color: #0f172a; background: #fff; width: 100%; box-sizing: border-box; outline: none; }
	.tpd-input:focus, .tpd-select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
	.tpd-wizard-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
	@media (max-width: 768px) { .tpd-reg-card { padding: 24px 20px; } .tpd-form-grid-2 { grid-template-columns: 1fr; } .tpd-wizard-steps { gap: 24px; } }
</style>

<?php
if ( is_user_logged_in() && ! isset( $_GET['preview_form'] ) ) {
	$current_user = wp_get_current_user();
	?>
	<div class="tpd-registration-wrapper">
		<div class="tpd-reg-already-logged-in" style="background:#fff; padding:40px; border-radius:16px; border:1px solid #e2e8f0; text-align:center;">
			<i class="fa-solid fa-circle-check" style="font-size:46px; color:#10b981; margin-bottom:14px;"></i>
			<h3><?php printf( esc_html__( 'You are currently signed in as %s', 'tpd-tool' ), esc_html( $current_user->display_name ) ); ?></h3>
			<p style="color:#64748b; margin-bottom:22px;"><?php esc_html_e( 'Access your Supplier Portal or preview the Supplier registration form below.', 'tpd-tool' ); ?></p>
			<div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
				<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-darkblue" style="text-decoration:none;">
					<i class="fa-solid fa-gauge-high"></i> <?php esc_html_e( 'Go to Supplier Dashboard', 'tpd-tool' ); ?>
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
			<div class="tpd-reg-badge"><i class="fa-solid fa-ship"></i> SUPPLIER PARTNER NETWORK</div>
			<h2><?php echo esc_html( ! empty( $form_cfg['supplier_title'] ) ? $form_cfg['supplier_title'] : __( 'Create Your Supplier Partner Account', 'tpd-tool' ) ); ?></h2>
			<p><?php echo esc_html( ! empty( $form_cfg['supplier_subtitle'] ) ? $form_cfg['supplier_subtitle'] : __( 'Register as a supplier representative to create, claim, and manage company listings, showcase your team in the Virtual Office, and connect with travel advisors.', 'tpd-tool' ) ); ?></p>
		</div>

		<!-- 2-Step Wizard Header -->
		<div class="tpd-wizard-steps" id="tpd-supp-wizard-steps">
			<div class="tpd-w-step active" data-step="1">
				<div class="tpd-w-circle">1</div>
				<span class="tpd-w-label"><?php esc_html_e( 'Representative & Company Info', 'tpd-tool' ); ?></span>
			</div>
			<div class="tpd-w-step" data-step="2">
				<div class="tpd-w-circle">2</div>
				<span class="tpd-w-label"><?php esc_html_e( 'Account Security & Plan', 'tpd-tool' ); ?></span>
			</div>
		</div>

		<form id="tpd-supplier-registration-form" class="tpd-reg-form" method="post">
			<input type="hidden" name="action" value="tpd_register_supplier">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

			<!-- STEP 1: REPRESENTATIVE & COMPANY DETAILS -->
			<div class="tpd-step-pane active" data-step-pane="1">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-building-user"></i> <?php esc_html_e( 'Step 1: Representative & Company Details', 'tpd-tool' ); ?></h3>

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
							<input type="tel" name="phone" required placeholder="(800) 555-0100" class="tpd-input" style="flex:1;">
						</div>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Email Address *', 'tpd-tool' ); ?></label>
						<input type="email" name="email" required placeholder="<?php esc_attr_e( 'representative@company.com', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Position/Title', 'tpd-tool' ); ?></label>
						<input type="text" name="position_title" placeholder="<?php esc_attr_e( 'e.g. Regional Business Development Manager', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Supplier/Company Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="company_name" required placeholder="<?php esc_attr_e( 'e.g. Oceanic Luxury Cruises & Resorts', 'tpd-tool' ); ?>" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Country *', 'tpd-tool' ); ?></label>
					<select name="country" class="tpd-select" required>
						<option value=""><?php esc_html_e( '— Select Country —', 'tpd-tool' ); ?></option>
						<?php foreach ( $countries as $c_name ) : ?>
							<option value="<?php echo esc_attr( $c_name ); ?>" <?php selected( $c_name, 'United States' ); ?>><?php echo esc_html( $c_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Dynamic Admin Custom Fields & ACF Fields for Suppliers -->
				<?php
				if ( class_exists( 'TPD_Tool_CPT' ) ) {
					TPD_Tool_CPT::render_dynamic_fields_html( 'supplier_listing', 0, 0, 'registration' );
				}
				?>

				<div class="tpd-wizard-nav" style="justify-content:flex-end;">
					<button type="button" class="tpd-btn tpd-btn-darkblue tpd-wizard-next-btn" data-next="2">
						<?php esc_html_e( 'Next: Account Login & Plan', 'tpd-tool' ); ?> <i class="fa-solid fa-arrow-right"></i>
					</button>
				</div>
			</div>

			<!-- STEP 2: USERNAME, PASSWORD & PLAN SELECTION -->
			<div class="tpd-step-pane" data-step-pane="2">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-key"></i> <?php esc_html_e( 'Step 2: Account Login Credentials & Plan', 'tpd-tool' ); ?></h3>

				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Username *', 'tpd-tool' ); ?></label>
					<input type="text" name="username" required placeholder="<?php esc_attr_e( 'Enter your preferred username', 'tpd-tool' ); ?>" class="tpd-input">
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Password *', 'tpd-tool' ); ?></label>
						<div style="position:relative;">
							<input type="password" name="password" id="tpd_supplier_password" required placeholder="<?php esc_attr_e( 'Minimum 8 characters', 'tpd-tool' ); ?>" class="tpd-input" style="padding-right:40px;">
							<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
								<i class="fa-solid fa-eye"></i>
							</button>
						</div>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Confirm Password *', 'tpd-tool' ); ?></label>
						<div style="position:relative;">
							<input type="password" name="confirm_password" id="tpd_supplier_confirm_password" required placeholder="<?php esc_attr_e( 'Confirm your password', 'tpd-tool' ); ?>" class="tpd-input" style="padding-right:40px;">
							<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
								<i class="fa-solid fa-eye"></i>
							</button>
						</div>
					</div>
				</div>

				<!-- Supplier Plan Selection -->
				<div class="tpd-form-group mt-3">
					<label class="font-bold"><?php esc_html_e( 'Select Supplier Listing Tier', 'tpd-tool' ); ?></label>
					<div class="tpd-tier-selection-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-top:8px;">
						<?php
						$first_p = true;
						foreach ( $plans as $p_slug => $p_info ) :
							$m_price = (float) $p_info['monthly_price'];
						?>
							<label class="tpd-tier-option" style="border:2px solid #e2e8f0; border-radius:12px; padding:16px; cursor:pointer;">
								<input type="radio" name="tier" value="<?php echo esc_attr( $p_slug ); ?>" <?php checked( $first_p ); ?>>
								<div class="tpd-to-content">
									<span class="tpd-to-tag"><?php echo esc_html( $p_info['badge'] ); ?></span>
									<h4 style="margin:4px 0;"><?php echo esc_html( $p_info['name'] ); ?></h4>
									<strong style="font-size:18px; color:#0b1526;">
										<?php echo $m_price <= 0 ? 'Free' : '$' . number_format( $m_price, 2 ) . '/mo'; ?>
									</strong>
								</div>
							</label>
						<?php
							$first_p = false;
						endforeach;
						?>
					</div>
				</div>

				<div class="tpd-notice-box" style="margin-top:16px; padding:12px 16px; background:#f0fdf4; border-left:4px solid #16a34a; border-radius:6px; font-size:12.5px; color:#166534;">
					<i class="fa-solid fa-circle-check"></i> <strong>Instant Company Listing Provisioning:</strong> Your supplier account and paired company listing will be created immediately and connected to your Supplier Portal!
				</div>

				<div class="tpd-wizard-nav">
					<button type="button" class="tpd-btn tpd-btn-outline tpd-wizard-prev-btn" data-prev="1">
						<i class="fa-solid fa-arrow-left"></i> <?php esc_html_e( 'Back', 'tpd-tool' ); ?>
					</button>
					<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-btn-lg" id="tpd-supplier-reg-btn">
						<i class="fa-solid fa-arrow-right"></i> <?php esc_html_e( 'Create Supplier Account & Open Dashboard', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>

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
