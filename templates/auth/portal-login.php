<?php
/**
 * TARC Official Unified Portal Login Template
 * Executive Style (Travel Advisor & Supplier Partner)
 * 
 * Supports:
 * - Standalone full-page login (No theme header/footer)
 * - Shortcodes: [tpd_portal_login], [tpd_login], [tpd_advisor_login], [tpd_supplier_login]
 * - Elementor Widget: TARC Dual Gateway Portal Login
 * 
 * Features:
 * - Executive Centered Card Design (Official TARC Brand Palette)
 * - Segmented Role Pill Switcher (Travel Advisor & Supplier Partner)
 * - Real AJAX authentication via tpd_user_login (Role segregation & secure redirects)
 * - Real inline forgot-password handling via tpd_forgot_password
 * - Zero clutter: No sidebars, no headers, no footers, no distracting callout banners
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Determine default active portal
$default_portal = 'advisor';
if ( ! empty( $atts['default_portal'] ) && in_array( strtolower( (string) $atts['default_portal'] ), array( 'advisor', 'supplier' ), true ) ) {
	$default_portal = strtolower( (string) $atts['default_portal'] );
} elseif ( isset( $_GET['role'] ) && in_array( strtolower( (string) $_GET['role'] ), array( 'advisor', 'supplier' ), true ) ) {
	$default_portal = strtolower( (string) $_GET['role'] );
} elseif ( ! empty( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'supplier' ) !== false ) {
	$default_portal = 'supplier';
}

// Redirect already-logged-in users unless previewing or inside Elementor editor
if ( is_user_logged_in() && ! isset( $_GET['preview_login'] ) && ( ! class_exists( 'TPD_Tool_Roles' ) || ! TPD_Tool_Roles::is_elementor_editor_context() ) ) {
	$current_uid = get_current_user_id();
	if ( class_exists( 'TPD_Tool_Roles' ) ) {
		if ( TPD_Tool_Roles::is_advisor( $current_uid ) ) {
			wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
			exit;
		}
		if ( TPD_Tool_Roles::is_supplier( $current_uid ) ) {
			wp_safe_redirect( home_url( '/supplier-dashboard/' ) );
			exit;
		}
		if ( TPD_Tool_Roles::is_admin( $current_uid ) ) {
			wp_safe_redirect( home_url( '/tpd-admin/' ) );
			exit;
		}
	}
}

$initial_notice = '';
if ( isset( $_GET['auth_required'] ) ) {
	$initial_notice = __( 'Please sign in to access your portal workspace.', 'tpd-tool' );
} elseif ( isset( $_GET['role_restricted'] ) ) {
	$initial_notice = __( 'Access denied: Please sign in with the required account credentials.', 'tpd-tool' );
} elseif ( isset( $_GET['account_suspended'] ) ) {
	$initial_notice = __( 'Your account is currently inactive or suspended. Please contact support.', 'tpd-tool' );
} elseif ( isset( $_GET['loggedout'] ) ) {
	$initial_notice = __( 'You have been securely signed out.', 'tpd-tool' );
}

$is_standalone = ! did_action( 'wp_head' );

if ( $is_standalone ) {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php esc_html_e( 'Sign In to Portal | Travel Advisor Resource Center', 'tpd-tool' ); ?></title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'tpd-executive-login-body' ); ?>>
	<?php
}
?>

<!-- Scoped Executive Login Stylesheet -->
<style>
	@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
	@import url('https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');

	:root {
		--tarc-deep-slate-teal: #236a7f;
		--tarc-sage-teal:       #64a2ad;
		--tarc-warm-ochre-gold: #c5a059;
		--tarc-warm-alabaster:  #f3f4ee;
		--tarc-dark-teal:       #15414f;
		--tarc-pure-white:      #ffffff;
		--tarc-soft-mist-teal:  #97bcc7;
		--tarc-warm-sand:       #eee4db;
		--tarc-soft-peach:      #f0d1bd;
		--tarc-font-heading:    'Playfair Display', Georgia, serif;
		--tarc-font-body:       'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
	}

	body.tpd-executive-login-body {
		margin: 0;
		padding: 0;
		font-family: var(--tarc-font-body);
		background-color: var(--tarc-warm-alabaster);
		color: var(--tarc-dark-teal);
		min-height: 100vh;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow-x: hidden;
	}

	.tarc-exec-login-wrapper {
		width: 100%;
		min-height: 100vh;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 40px 20px;
		box-sizing: border-box;
		background: var(--tarc-warm-alabaster);
		font-family: var(--tarc-font-body);
	}

	.tarc-exec-card {
		background: var(--tarc-pure-white);
		max-width: 490px;
		width: 100%;
		border-radius: 24px;
		border: 1px solid rgba(151, 188, 199, 0.45);
		box-shadow: 0 20px 50px rgba(21, 65, 79, 0.08), 0 2px 8px rgba(0, 0, 0, 0.03);
		padding: 44px 42px;
		box-sizing: border-box;
		position: relative;
		text-align: left;
	}

	/* Card Top Branding */
	.tarc-exec-brand {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 22px;
	}

	.tarc-exec-emblem {
		width: 44px;
		height: 44px;
		border-radius: 12px;
		background: rgba(35, 106, 127, 0.08);
		border: 1.5px solid var(--tarc-warm-ochre-gold);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--tarc-warm-ochre-gold);
		font-size: 20px;
		flex-shrink: 0;
	}

	.tarc-exec-brand-text {
		display: flex;
		flex-direction: column;
	}

	.tarc-exec-brand-title {
		font-family: var(--tarc-font-heading);
		font-size: 16px;
		font-weight: 700;
		color: var(--tarc-dark-teal);
		line-height: 1.2;
		letter-spacing: -0.01em;
	}

	.tarc-exec-brand-sub {
		font-size: 10.5px;
		font-weight: 700;
		color: var(--tarc-sage-teal);
		text-transform: uppercase;
		letter-spacing: 0.1em;
		margin-top: 2px;
	}

	/* Card Title & Subtitle */
	.tarc-exec-header {
		margin-bottom: 24px;
	}

	.tarc-exec-header h2 {
		font-family: var(--tarc-font-heading) !important;
		font-size: 32px !important;
		font-weight: 700 !important;
		color: var(--tarc-dark-teal) !important;
		margin: 0 0 6px !important;
		line-height: 1.2 !important;
		letter-spacing: -0.02em !important;
	}

	.tarc-exec-header p {
		font-size: 14.5px;
		color: #5e7a84;
		margin: 0;
		line-height: 1.5;
	}

	/* Role Pill Switcher (Warm Sand Segmented Control) */
	.tarc-role-switcher {
		display: flex;
		background: var(--tarc-warm-sand);
		padding: 5px;
		border-radius: 14px;
		margin-bottom: 26px;
		border: 1px solid rgba(151, 188, 199, 0.35);
		box-sizing: border-box;
	}

	.tarc-role-btn {
		flex: 1;
		padding: 12px 14px;
		border: none;
		background: transparent;
		border-radius: 10px;
		font-family: var(--tarc-font-body);
		font-size: 13.5px;
		font-weight: 700;
		color: #5e7a84;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		transition: all 0.25s ease;
		user-select: none;
	}

	.tarc-role-btn:hover:not(.active) {
		color: var(--tarc-deep-slate-teal);
	}

	.tarc-role-btn.active.advisor-mode {
		background: var(--tarc-deep-slate-teal);
		color: var(--tarc-pure-white);
		box-shadow: 0 4px 12px rgba(35, 106, 127, 0.28);
	}

	.tarc-role-btn.active.supplier-mode {
		background: var(--tarc-dark-teal);
		color: var(--tarc-pure-white);
		box-shadow: 0 4px 12px rgba(21, 65, 79, 0.28);
	}

	.tarc-role-dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: var(--tarc-warm-ochre-gold);
		display: inline-block;
	}

	/* Notice Message */
	.tarc-notice-msg {
		padding: 12px 16px;
		border-radius: 10px;
		font-size: 13px;
		line-height: 1.4;
		margin-bottom: 20px;
		display: none;
		text-align: left;
	}

	.tarc-notice-msg.error {
		background: #fef2f2;
		color: #991b1b;
		border: 1px solid #fecaca;
		display: block;
	}

	.tarc-notice-msg.success {
		background: #f0fdf4;
		color: #166534;
		border: 1px solid #bbf7d0;
		display: block;
	}

	/* Form Field Controls */
	.tarc-form-group {
		margin-bottom: 18px;
		text-align: left;
	}

	.tarc-form-label {
		display: block;
		font-size: 13px;
		font-weight: 700;
		color: var(--tarc-dark-teal);
		margin-bottom: 8px;
		letter-spacing: 0.1px;
	}

	.tarc-input-wrap {
		position: relative;
		display: flex;
		align-items: center;
	}

	.tarc-input-icon {
		position: absolute;
		left: 16px;
		color: var(--tarc-sage-teal);
		font-size: 15px;
		pointer-events: none;
		transition: color 0.2s ease;
	}

	.tarc-form-input {
		width: 100%;
		height: 50px;
		padding: 0 44px 0 44px;
		border: 1.5px solid var(--tarc-soft-mist-teal);
		border-radius: 12px;
		font-size: 14.5px;
		font-family: var(--tarc-font-body);
		color: var(--tarc-dark-teal);
		background: var(--tarc-pure-white);
		transition: all 0.2s ease;
		box-sizing: border-box;
	}

	.tarc-form-input::placeholder {
		color: #9ab4be;
	}

	.tarc-form-input:focus {
		outline: none;
		border-color: var(--tarc-deep-slate-teal);
		box-shadow: 0 0 0 4px rgba(35, 106, 127, 0.12);
	}

	.tarc-toggle-pass {
		position: absolute;
		right: 14px;
		background: none;
		border: none;
		color: var(--tarc-sage-teal);
		cursor: pointer;
		font-size: 15px;
		padding: 6px;
		transition: color 0.2s;
	}

	.tarc-toggle-pass:hover {
		color: var(--tarc-dark-teal);
	}

	/* Meta: Remember & Forgot Password */
	.tarc-form-meta {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin: 10px 0 24px;
		font-size: 13.5px;
	}

	.tarc-remember-wrap {
		display: flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		user-select: none;
		color: #5e7a84;
		font-weight: 500;
	}

	.tarc-remember-wrap input[type="checkbox"] {
		width: 17px;
		height: 17px;
		accent-color: var(--tarc-deep-slate-teal);
		border-radius: 4px;
		cursor: pointer;
	}

	.tarc-forgot-link {
		color: var(--tarc-deep-slate-teal);
		text-decoration: none;
		font-weight: 700;
		cursor: pointer;
		background: none;
		border: none;
		padding: 0;
		font-family: inherit;
		font-size: 13.5px;
		transition: color 0.2s ease;
	}

	.tarc-forgot-link:hover {
		color: var(--tarc-warm-ochre-gold);
		text-decoration: underline;
	}

	/* Submit Button */
	.tarc-btn-submit {
		width: 100%;
		height: 52px;
		border: none;
		border-radius: 12px;
		background: var(--tarc-deep-slate-teal);
		color: var(--tarc-pure-white);
		font-family: var(--tarc-font-body);
		font-size: 15px;
		font-weight: 700;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		box-shadow: 0 6px 18px rgba(35, 106, 127, 0.25);
		transition: all 0.25s ease;
		box-sizing: border-box;
	}

	.tarc-btn-submit:hover {
		background: #1b5364;
		transform: translateY(-1px);
		box-shadow: 0 10px 24px rgba(35, 106, 127, 0.35);
	}

	.tarc-btn-submit.supplier-active {
		background: var(--tarc-dark-teal);
		box-shadow: 0 6px 18px rgba(21, 65, 79, 0.25);
	}

	.tarc-btn-submit.supplier-active:hover {
		background: #0d2a33;
		box-shadow: 0 10px 24px rgba(21, 65, 79, 0.35);
	}

	.tarc-btn-submit:disabled {
		opacity: 0.65;
		cursor: not-allowed;
		transform: none !important;
	}

	/* Card Footer / Registration Switch */
	.tarc-card-bottom {
		margin-top: 26px;
		padding-top: 20px;
		border-top: 1px solid var(--tarc-warm-sand);
		text-align: center;
		font-size: 13px;
		color: #5e7a84;
	}

	.tarc-card-bottom a {
		color: var(--tarc-deep-slate-teal);
		text-decoration: none;
		font-weight: 700;
		transition: color 0.2s ease;
	}

	.tarc-card-bottom a:hover {
		color: var(--tarc-warm-ochre-gold);
		text-decoration: underline;
	}

	.tarc-admin-link-row {
		margin-top: 10px;
		font-size: 12px;
	}

	.tarc-admin-link-row a {
		color: #8da6af;
		text-decoration: none;
	}

	.tarc-admin-link-row a:hover {
		color: var(--tarc-dark-teal);
	}

	/* Inline Forgot Password Modal */
	.tarc-reset-panel {
		display: none;
		margin-top: 20px;
		padding: 18px 20px;
		background: var(--tarc-warm-alabaster);
		border: 1px solid rgba(151, 188, 199, 0.5);
		border-radius: 12px;
		text-align: left;
		animation: tarcFadeIn 0.3s ease;
	}

	.tarc-reset-panel h4 {
		font-family: var(--tarc-font-heading) !important;
		font-size: 17px !important;
		font-weight: 700 !important;
		color: var(--tarc-dark-teal) !important;
		margin: 0 0 6px !important;
	}

	.tarc-reset-panel p {
		font-size: 12.5px;
		color: #5e7a84;
		margin: 0 0 12px;
		line-height: 1.4;
	}

	.tarc-reset-flex {
		display: flex;
		gap: 8px;
	}

	.tarc-reset-flex input {
		flex: 1;
		height: 42px;
		padding: 0 14px;
		border: 1.5px solid var(--tarc-soft-mist-teal);
		border-radius: 8px;
		font-size: 13.5px;
		box-sizing: border-box;
	}

	.tarc-reset-flex button {
		height: 42px;
		padding: 0 16px;
		border: none;
		border-radius: 8px;
		background: var(--tarc-deep-slate-teal);
		color: #ffffff;
		font-weight: 700;
		font-size: 13px;
		cursor: pointer;
		white-space: nowrap;
	}

	.tarc-reset-cancel {
		margin-top: 8px;
		display: inline-block;
		font-size: 12px;
		color: #5e7a84;
		cursor: pointer;
		background: none;
		border: none;
		padding: 0;
	}

	@keyframes tarcFadeIn {
		from { opacity: 0; transform: translateY(6px); }
		to { opacity: 1; transform: translateY(0); }
	}

	@media (max-width: 540px) {
		.tarc-exec-card {
			padding: 32px 24px;
			border-radius: 18px;
		}
		.tarc-exec-header h2 {
			font-size: 26px !important;
		}
	}
</style>

<div class="tarc-exec-login-wrapper">
	<div class="tarc-exec-card">

		<!-- Top Branding -->
		<div class="tarc-exec-brand">
			<div class="tarc-exec-emblem">
				<i class="fa-solid fa-compass"></i>
			</div>
			<div class="tarc-exec-brand-text">
				<span class="tarc-exec-brand-title"><?php esc_html_e( 'Travel Advisor Resource Center', 'tpd-tool' ); ?></span>
				<span class="tarc-exec-brand-sub"><?php esc_html_e( 'TARC Official Gateway', 'tpd-tool' ); ?></span>
			</div>
		</div>

		<!-- Card Title -->
		<div class="tarc-exec-header">
			<h2><?php esc_html_e( 'Sign in to your Portal', 'tpd-tool' ); ?></h2>
			<p><?php esc_html_e( 'Select your role to access your personalized workspace.', 'tpd-tool' ); ?></p>
		</div>

		<!-- Role Pill Switcher -->
		<div class="tarc-role-switcher" role="tablist">
			<button 
				type="button" 
				class="tarc-role-btn <?php echo 'advisor' === $default_portal ? 'active advisor-mode' : ''; ?>" 
				id="tarcPillAdvisor"
				onclick="tarcSelectRole('advisor')">
				<i class="fa-solid fa-user-tie"></i>
				<span><?php esc_html_e( 'Travel Advisor', 'tpd-tool' ); ?></span>
				<?php if ( 'advisor' === $default_portal ) : ?><span class="tarc-role-dot"></span><?php endif; ?>
			</button>

			<button 
				type="button" 
				class="tarc-role-btn <?php echo 'supplier' === $default_portal ? 'active supplier-mode' : ''; ?>" 
				id="tarcPillSupplier"
				onclick="tarcSelectRole('supplier')">
				<i class="fa-solid fa-hotel"></i>
				<span><?php esc_html_e( 'Supplier Partner', 'tpd-tool' ); ?></span>
				<?php if ( 'supplier' === $default_portal ) : ?><span class="tarc-role-dot"></span><?php endif; ?>
			</button>
		</div>

		<!-- Status / Notice Alerts -->
		<div id="tarcLoginNotice" class="tarc-notice-msg <?php echo ! empty( $initial_notice ) ? 'error' : ''; ?>">
			<?php echo esc_html( $initial_notice ); ?>
		</div>

		<!-- Authentication Form -->
		<form id="tarcUnifiedLoginForm" onsubmit="tarcHandleSubmit(event)">
			<input type="hidden" name="action" value="tpd_user_login">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
			<input type="hidden" name="portal_type" id="tarcPortalTypeInput" value="<?php echo esc_attr( $default_portal ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( ! empty( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '' ); ?>">

			<!-- Username / Email Field -->
			<div class="tarc-form-group">
				<label class="tarc-form-label" for="tarcUserField" id="tarcUserLabel">
					<?php echo 'supplier' === $default_portal ? esc_html__( 'Supplier Email Address or Username', 'tpd-tool' ) : esc_html__( 'Advisor Email Address or Username', 'tpd-tool' ); ?>
				</label>
				<div class="tarc-input-wrap">
					<i class="fa-solid fa-envelope tarc-input-icon"></i>
					<input 
						type="text" 
						id="tarcUserField" 
						name="username" 
						required 
						class="tarc-form-input" 
						placeholder="<?php echo 'supplier' === $default_portal ? 'partner@resortluxury.com' : 'advisor@agency.com'; ?>"
						autocomplete="username">
				</div>
			</div>

			<!-- Password Field -->
			<div class="tarc-form-group">
				<label class="tarc-form-label" for="tarcPassField"><?php esc_html_e( 'Password', 'tpd-tool' ); ?></label>
				<div class="tarc-input-wrap">
					<i class="fa-solid fa-lock tarc-input-icon"></i>
					<input 
						type="password" 
						id="tarcPassField" 
						name="password" 
						required 
						class="tarc-form-input" 
						placeholder="••••••••••••"
						autocomplete="current-password">
					<button type="button" class="tarc-toggle-pass" onclick="tarcTogglePass()" title="<?php esc_attr_e( 'Toggle password visibility', 'tpd-tool' ); ?>">
						<i class="fa-solid fa-eye" id="tarcEyeIcon"></i>
					</button>
				</div>
			</div>

			<!-- Meta: Remember & Forgot -->
			<div class="tarc-form-meta">
				<label class="tarc-remember-wrap">
					<input type="checkbox" name="remember" value="1">
					<span><?php esc_html_e( 'Keep me signed in', 'tpd-tool' ); ?></span>
				</label>
				<button type="button" class="tarc-forgot-link" onclick="tarcToggleResetPanel(true)">
					<?php esc_html_e( 'Forgot password?', 'tpd-tool' ); ?>
				</button>
			</div>

			<!-- Submit Button -->
			<button 
				type="submit" 
				class="tarc-btn-submit <?php echo 'supplier' === $default_portal ? 'supplier-active' : ''; ?>" 
				id="tarcSubmitBtn">
				<span id="tarcSubmitText">
					<?php echo 'supplier' === $default_portal ? esc_html__( 'Sign In to Supplier Portal', 'tpd-tool' ) : esc_html__( 'Sign In to Advisor Portal', 'tpd-tool' ); ?>
				</span>
				<i class="fa-solid fa-arrow-right"></i>
			</button>
		</form>

		<!-- Inline Reset Password Panel -->
		<div class="tarc-reset-panel" id="tarcResetPanel">
			<h4><?php esc_html_e( 'Reset Your Password', 'tpd-tool' ); ?></h4>
			<p><?php esc_html_e( 'Enter your registered email address or username and we will send you a password reset link.', 'tpd-tool' ); ?></p>
			<div class="tarc-reset-flex">
				<input type="text" id="tarcResetInput" placeholder="name@domain.com">
				<button type="button" id="tarcResetSubmitBtn" onclick="tarcSendReset()"><?php esc_html_e( 'Send Link', 'tpd-tool' ); ?></button>
			</div>
			<div id="tarcResetStatus" style="font-size:12px; margin-top:8px; display:none;"></div>
			<button type="button" class="tarc-reset-cancel" onclick="tarcToggleResetPanel(false)"><?php esc_html_e( '&larr; Return to Sign In', 'tpd-tool' ); ?></button>
		</div>

		<!-- Card Footer: Registration Links -->
		<div class="tarc-card-bottom" id="tarcRegBox">
			<div id="tarcAdvisorRegPrompt" style="<?php echo 'supplier' === $default_portal ? 'display:none;' : 'display:block;'; ?>">
				<span><?php esc_html_e( "Don't have an Advisor account?", 'tpd-tool' ); ?></span>
				<a href="<?php echo esc_url( home_url( '/advisor-registration/' ) ); ?>"><?php esc_html_e( 'Apply for Advisor Accreditation &rarr;', 'tpd-tool' ); ?></a>
			</div>
			<div id="tarcSupplierRegPrompt" style="<?php echo 'supplier' === $default_portal ? 'display:block;' : 'display:none;'; ?>">
				<span><?php esc_html_e( 'New hospitality or cruise partner?', 'tpd-tool' ); ?></span>
				<a href="<?php echo esc_url( home_url( '/supplier-registration/' ) ); ?>"><?php esc_html_e( 'Register as a Supplier &rarr;', 'tpd-tool' ); ?></a>
			</div>
			<div class="tarc-admin-link-row">
				<a href="<?php echo esc_url( home_url( '/admin-login/' ) ); ?>"><i class="fa-solid fa-shield-halved"></i> <?php esc_html_e( 'Super Administrator Portal', 'tpd-tool' ); ?></a>
			</div>
		</div>

	</div>
</div>

<!-- Real Authentication Script -->
<script>
(function() {
	var ajaxUrl    = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var currentRole = '<?php echo esc_js( $default_portal ); ?>';

	window.tarcSelectRole = function(role) {
		currentRole = role;
		document.getElementById('tarcPortalTypeInput').value = role;

		var btnAdv = document.getElementById('tarcPillAdvisor');
		var btnSup = document.getElementById('tarcPillSupplier');
		var subBtn = document.getElementById('tarcSubmitBtn');
		var subTxt = document.getElementById('tarcSubmitText');
		var usrLbl = document.getElementById('tarcUserLabel');
		var usrInp = document.getElementById('tarcUserField');
		var regAdv = document.getElementById('tarcAdvisorRegPrompt');
		var regSup = document.getElementById('tarcSupplierRegPrompt');

		if (role === 'advisor') {
			btnAdv.className = 'tarc-role-btn active advisor-mode';
			btnAdv.innerHTML = '<i class="fa-solid fa-user-tie"></i><span><?php echo esc_js( __( 'Travel Advisor', 'tpd-tool' ) ); ?></span><span class="tarc-role-dot"></span>';

			btnSup.className = 'tarc-role-btn';
			btnSup.innerHTML = '<i class="fa-solid fa-hotel"></i><span><?php echo esc_js( __( 'Supplier Partner', 'tpd-tool' ) ); ?></span>';

			subBtn.className = 'tarc-btn-submit';
			subTxt.textContent = '<?php echo esc_js( __( 'Sign In to Advisor Portal', 'tpd-tool' ) ); ?>';
			usrLbl.textContent = '<?php echo esc_js( __( 'Advisor Email Address or Username', 'tpd-tool' ) ); ?>';
			usrInp.placeholder = 'advisor@agency.com';

			if (regAdv) regAdv.style.display = 'block';
			if (regSup) regSup.style.display = 'none';
		} else {
			btnSup.className = 'tarc-role-btn active supplier-mode';
			btnSup.innerHTML = '<i class="fa-solid fa-hotel"></i><span><?php echo esc_js( __( 'Supplier Partner', 'tpd-tool' ) ); ?></span><span class="tarc-role-dot"></span>';

			btnAdv.className = 'tarc-role-btn';
			btnAdv.innerHTML = '<i class="fa-solid fa-user-tie"></i><span><?php echo esc_js( __( 'Travel Advisor', 'tpd-tool' ) ); ?></span>';

			subBtn.className = 'tarc-btn-submit supplier-active';
			subTxt.textContent = '<?php echo esc_js( __( 'Sign In to Supplier Portal', 'tpd-tool' ) ); ?>';
			usrLbl.textContent = '<?php echo esc_js( __( 'Supplier Email Address or Username', 'tpd-tool' ) ); ?>';
			usrInp.placeholder = 'partner@resortluxury.com';

			if (regAdv) regAdv.style.display = 'none';
			if (regSup) regSup.style.display = 'block';
		}
	};

	window.tarcTogglePass = function() {
		var input = document.getElementById('tarcPassField');
		var icon  = document.getElementById('tarcEyeIcon');
		if (input.type === 'password') {
			input.type = 'text';
			icon.className = 'fa-solid fa-eye-slash';
		} else {
			input.type = 'password';
			icon.className = 'fa-solid fa-eye';
		}
	};

	window.tarcToggleResetPanel = function(show) {
		var panel = document.getElementById('tarcResetPanel');
		panel.style.display = show ? 'block' : 'none';
		if (show) {
			document.getElementById('tarcResetInput').focus();
		}
	};

	window.tarcSendReset = function() {
		var val = document.getElementById('tarcResetInput').value.trim();
		var btn = document.getElementById('tarcResetSubmitBtn');
		var msg = document.getElementById('tarcResetStatus');
		if (!val) return;

		btn.disabled = true;
		btn.textContent = '<?php echo esc_js( __( 'Sending...', 'tpd-tool' ) ); ?>';

		var fd = new FormData();
		fd.append('action', 'tpd_forgot_password');
		fd.append('user_login', val);
		fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'tpd_nonce' ) ); ?>');

		fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				btn.disabled = false;
				btn.textContent = '<?php echo esc_js( __( 'Send Link', 'tpd-tool' ) ); ?>';
				msg.style.display = 'block';
				msg.style.color = res.success ? '#166534' : '#991b1b';
				msg.textContent = (res.data && res.data.message) ? res.data.message : 'Notice sent.';
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = '<?php echo esc_js( __( 'Send Link', 'tpd-tool' ) ); ?>';
				msg.style.display = 'block';
				msg.style.color = '#991b1b';
				msg.textContent = 'Unable to send reset request.';
			});
	};

	window.tarcHandleSubmit = function(e) {
		e.preventDefault();
		var form   = document.getElementById('tarcUnifiedLoginForm');
		var btn    = document.getElementById('tarcSubmitBtn');
		var notice = document.getElementById('tarcLoginNotice');

		notice.style.display = 'none';
		notice.className     = 'tarc-notice-msg';

		var origText = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js( __( 'Authenticating...', 'tpd-tool' ) ); ?>';

		var fd = new FormData(form);

		fetch(ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin'
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res.success) {
				notice.className   = 'tarc-notice-msg success';
				notice.innerHTML   = '<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || '<?php echo esc_js( __( 'Authentication successful. Opening portal...', 'tpd-tool' ) ); ?>');
				notice.style.display = 'block';

				var dest = res.data.redirect_url || (currentRole === 'supplier' ? '<?php echo esc_js( home_url( '/supplier-dashboard/' ) ); ?>' : '<?php echo esc_js( home_url( '/advisor-dashboard/' ) ); ?>');
				setTimeout(function() {
					window.location.href = dest;
				}, 400);
			} else {
				btn.disabled = false;
				btn.innerHTML = origText;
				notice.className = 'tarc-notice-msg error';
				notice.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + ((res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Invalid username or password.', 'tpd-tool' ) ); ?>');
				notice.style.display = 'block';
			}
		})
		.catch(function() {
			btn.disabled = false;
			btn.innerHTML = origText;
			notice.className = 'tarc-notice-msg error';
			notice.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <?php echo esc_js( __( 'Unable to connect to server. Please try again.', 'tpd-tool' ) ); ?>';
			notice.style.display = 'block';
		});
	};
})();
</script>

<?php
if ( $is_standalone ) {
	?>
	</body>
	</html>
	<?php
}
