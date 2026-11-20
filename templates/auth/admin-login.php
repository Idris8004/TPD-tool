<?php
/**
 * Dedicated Super Admin Portal Login Template
 * Executive Style (Travel Advisor Resource Center)
 * 
 * Completely separates Super Admin Dashboard authentication from native WordPress /wp-login.php
 * and public advisor/supplier login forms.
 * 
 * Accessible at:
 *  - URL: /admin-login/ or /tpd-admin-login/
 *  - Shortcode: [tpd_admin_login]
 *  - Elementor Widget: TPD Super Admin Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If already logged in as Admin, redirect straight to Super Admin Hub
if ( is_user_logged_in() && ! isset( $_GET['preview_login'] ) && ( ! class_exists( 'TPD_Tool_Roles' ) || ! TPD_Tool_Roles::is_elementor_editor_context() ) ) {
	$current_uid = get_current_user_id();
	if ( class_exists( 'TPD_Tool_Roles' ) && TPD_Tool_Roles::is_admin( $current_uid ) ) {
		$target_redirect = ! empty( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/tpd-admin/' );
		wp_safe_redirect( $target_redirect );
		exit;
	}
}

$initial_notice = '';
if ( isset( $_GET['auth_required'] ) ) {
	$initial_notice = __( 'Administrator authentication required to access the Super Admin Control Hub.', 'tpd-tool' );
} elseif ( isset( $_GET['role_restricted'] ) ) {
	$initial_notice = __( 'Access Denied: Only users with Administrator privileges can enter this portal.', 'tpd-tool' );
} elseif ( isset( $_GET['loggedout'] ) ) {
	$initial_notice = __( 'You have been securely logged out of the Super Admin Hub.', 'tpd-tool' );
}

$redirect_to_val = ! empty( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/tpd-admin/' );

$is_standalone = ! did_action( 'wp_head' );

if ( $is_standalone ) {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php esc_html_e( 'Super Admin Portal | Travel Advisor Resource Center', 'tpd-tool' ); ?></title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'tpd-executive-admin-body' ); ?>>
	<?php
}
?>

<!-- Scoped Super Admin Executive Stylesheet -->
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

	body.tpd-executive-admin-body {
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

	.tarc-admin-login-wrapper {
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

	.tarc-admin-card {
		background: var(--tarc-pure-white);
		max-width: 490px;
		width: 100%;
		border-radius: 24px;
		border: 1px solid rgba(151, 188, 199, 0.45);
		box-shadow: 0 20px 50px rgba(21, 65, 79, 0.1), 0 2px 8px rgba(0, 0, 0, 0.04);
		padding: 44px 42px;
		box-sizing: border-box;
		position: relative;
		text-align: left;
	}

	/* Card Top Branding */
	.tarc-admin-brand {
		display: flex;
		align-items: center;
		gap: 12px;
		margin-bottom: 22px;
	}

	.tarc-admin-emblem {
		width: 44px;
		height: 44px;
		border-radius: 12px;
		background: rgba(21, 65, 79, 0.08);
		border: 1.5px solid var(--tarc-warm-ochre-gold);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--tarc-warm-ochre-gold);
		font-size: 20px;
		flex-shrink: 0;
	}

	.tarc-admin-brand-text {
		display: flex;
		flex-direction: column;
	}

	.tarc-admin-brand-title {
		font-family: var(--tarc-font-heading);
		font-size: 16px;
		font-weight: 700;
		color: var(--tarc-dark-teal);
		line-height: 1.2;
		letter-spacing: -0.01em;
	}

	.tarc-admin-brand-sub {
		font-size: 10.5px;
		font-weight: 800;
		color: var(--tarc-warm-ochre-gold);
		text-transform: uppercase;
		letter-spacing: 0.12em;
		margin-top: 2px;
	}

	/* Card Header */
	.tarc-admin-header {
		margin-bottom: 26px;
	}

	.tarc-admin-header h2 {
		font-family: var(--tarc-font-heading) !important;
		font-size: 32px !important;
		font-weight: 700 !important;
		color: var(--tarc-dark-teal) !important;
		margin: 0 0 6px !important;
		line-height: 1.2 !important;
		letter-spacing: -0.02em !important;
	}

	.tarc-admin-header p {
		font-size: 14.5px;
		color: #5e7a84;
		margin: 0;
		line-height: 1.5;
	}

	/* Security Shield Callout */
	.tarc-security-tag {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 4px 10px;
		border-radius: 6px;
		background: rgba(197, 160, 89, 0.12);
		border: 1px solid rgba(197, 160, 89, 0.35);
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--tarc-dark-teal);
		margin-bottom: 22px;
	}

	.tarc-security-tag i {
		color: var(--tarc-warm-ochre-gold);
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

	/* Form Fields */
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
		border-color: var(--tarc-dark-teal);
		box-shadow: 0 0 0 4px rgba(21, 65, 79, 0.14);
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

	/* Meta: Remember & Forgot */
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
		accent-color: var(--tarc-dark-teal);
		border-radius: 4px;
		cursor: pointer;
	}

	.tarc-forgot-link {
		color: var(--tarc-dark-teal);
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
		background: linear-gradient(135deg, var(--tarc-dark-teal) 0%, #0d2830 100%);
		color: var(--tarc-pure-white);
		font-family: var(--tarc-font-body);
		font-size: 15px;
		font-weight: 700;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		box-shadow: 0 6px 20px rgba(21, 65, 79, 0.3);
		border: 1px solid rgba(197, 160, 89, 0.3);
		transition: all 0.25s ease;
		box-sizing: border-box;
	}

	.tarc-btn-submit:hover {
		background: linear-gradient(135deg, #1b4f60 0%, #153c48 100%);
		border-color: var(--tarc-warm-ochre-gold);
		transform: translateY(-1px);
		box-shadow: 0 10px 26px rgba(21, 65, 79, 0.4);
	}

	.tarc-btn-submit:disabled {
		opacity: 0.65;
		cursor: not-allowed;
		transform: none !important;
	}

	/* Card Bottom Navigation */
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

	/* Inline Reset Password Panel */
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
		background: var(--tarc-dark-teal);
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
		.tarc-admin-card {
			padding: 32px 24px;
			border-radius: 18px;
		}
		.tarc-admin-header h2 {
			font-size: 26px !important;
		}
	}
</style>

<div class="tarc-admin-login-wrapper">
	<div class="tarc-admin-card">

		<!-- Top Branding -->
		<div class="tarc-admin-brand">
			<div class="tarc-admin-emblem">
				<i class="fa-solid fa-shield-halved"></i>
			</div>
			<div class="tarc-admin-brand-text">
				<span class="tarc-admin-brand-title"><?php esc_html_e( 'Travel Advisor Resource Center', 'tpd-tool' ); ?></span>
				<span class="tarc-admin-brand-sub"><?php esc_html_e( 'Control Operations', 'tpd-tool' ); ?></span>
			</div>
		</div>

		<!-- Card Header -->
		<div class="tarc-admin-header">
			<h2><?php esc_html_e( 'Super Admin Portal', 'tpd-tool' ); ?></h2>
			<p><?php esc_html_e( 'Enter your administrator credentials to access the TPD Super Admin Hub.', 'tpd-tool' ); ?></p>
		</div>

		<div class="tarc-security-tag">
			<i class="fa-solid fa-lock"></i> <?php esc_html_e( 'Restricted Access • Administrator Authentication', 'tpd-tool' ); ?>
		</div>

		<!-- Status / Notice Alerts -->
		<div id="tarcAdminNotice" class="tarc-notice-msg <?php echo ! empty( $initial_notice ) ? 'error' : ''; ?>">
			<?php echo esc_html( $initial_notice ); ?>
		</div>

		<!-- Authentication Form -->
		<form id="tarcAdminLoginForm" onsubmit="tarcHandleAdminSubmit(event)">
			<input type="hidden" name="action" value="tpd_user_login">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
			<input type="hidden" name="portal_type" value="admin">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to_val ); ?>">

			<!-- Username / Email Field -->
			<div class="tarc-form-group">
				<label class="tarc-form-label" for="tarcAdminUserField"><?php esc_html_e( 'Administrator Email or Username', 'tpd-tool' ); ?></label>
				<div class="tarc-input-wrap">
					<i class="fa-solid fa-user-shield tarc-input-icon"></i>
					<input 
						type="text" 
						id="tarcAdminUserField" 
						name="username" 
						required 
						class="tarc-form-input" 
						placeholder="admin@domain.com"
						autocomplete="username">
				</div>
			</div>

			<!-- Password Field -->
			<div class="tarc-form-group">
				<label class="tarc-form-label" for="tarcAdminPassField"><?php esc_html_e( 'Security Password', 'tpd-tool' ); ?></label>
				<div class="tarc-input-wrap">
					<i class="fa-solid fa-key tarc-input-icon"></i>
					<input 
						type="password" 
						id="tarcAdminPassField" 
						name="password" 
						required 
						class="tarc-form-input" 
						placeholder="••••••••••••"
						autocomplete="current-password">
					<button type="button" class="tarc-toggle-pass" onclick="tarcToggleAdminPass()" title="<?php esc_attr_e( 'Toggle password visibility', 'tpd-tool' ); ?>">
						<i class="fa-solid fa-eye" id="tarcAdminEyeIcon"></i>
					</button>
				</div>
			</div>

			<!-- Meta: Remember & Forgot -->
			<div class="tarc-form-meta">
				<label class="tarc-remember-wrap">
					<input type="checkbox" name="remember" value="1">
					<span><?php esc_html_e( 'Remember Session', 'tpd-tool' ); ?></span>
				</label>
				<button type="button" class="tarc-forgot-link" onclick="tarcToggleAdminReset(true)">
					<?php esc_html_e( 'Reset Password', 'tpd-tool' ); ?>
				</button>
			</div>

			<!-- Submit Button -->
			<button type="submit" class="tarc-btn-submit" id="tarcAdminSubmitBtn">
				<i class="fa-solid fa-shield-halved"></i>
				<span id="tarcAdminBtnText"><?php esc_html_e( 'Sign In to Super Admin Hub', 'tpd-tool' ); ?></span>
				<i class="fa-solid fa-arrow-right"></i>
			</button>
		</form>

		<!-- Inline Reset Password Panel -->
		<div class="tarc-reset-panel" id="tarcAdminResetPanel">
			<h4><?php esc_html_e( 'Reset Administrator Password', 'tpd-tool' ); ?></h4>
			<p><?php esc_html_e( 'Enter your administrator email and we will dispatch a password recovery link.', 'tpd-tool' ); ?></p>
			<div class="tarc-reset-flex">
				<input type="text" id="tarcAdminResetInput" placeholder="admin@domain.com">
				<button type="button" id="tarcAdminResetBtn" onclick="tarcSendAdminReset()"><?php esc_html_e( 'Send Link', 'tpd-tool' ); ?></button>
			</div>
			<div id="tarcAdminResetStatus" style="font-size:12px; margin-top:8px; display:none;"></div>
			<button type="button" class="tarc-reset-cancel" onclick="tarcToggleAdminReset(false)"><?php esc_html_e( '&larr; Return to Sign In', 'tpd-tool' ); ?></button>
		</div>

		<!-- Bottom Nav to Public Portal -->
		<div class="tarc-card-bottom">
			<div>
				<?php esc_html_e( 'Looking for Travel Advisor or Supplier login?', 'tpd-tool' ); ?>
				<a href="<?php echo esc_url( home_url( '/portal-login/' ) ); ?>"><?php esc_html_e( 'Go to Portal Login &rarr;', 'tpd-tool' ); ?></a>
			</div>
		</div>

	</div>
</div>

<script>
(function() {
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

	window.tarcToggleAdminPass = function() {
		var input = document.getElementById('tarcAdminPassField');
		var icon  = document.getElementById('tarcAdminEyeIcon');
		if (input.type === 'password') {
			input.type = 'text';
			icon.className = 'fa-solid fa-eye-slash';
		} else {
			input.type = 'password';
			icon.className = 'fa-solid fa-eye';
		}
	};

	window.tarcToggleAdminReset = function(show) {
		var panel = document.getElementById('tarcAdminResetPanel');
		panel.style.display = show ? 'block' : 'none';
		if (show) {
			document.getElementById('tarcAdminResetInput').focus();
		}
	};

	window.tarcSendAdminReset = function() {
		var val = document.getElementById('tarcAdminResetInput').value.trim();
		var btn = document.getElementById('tarcAdminResetBtn');
		var msg = document.getElementById('tarcAdminResetStatus');
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

	window.tarcHandleAdminSubmit = function(e) {
		e.preventDefault();
		var form   = document.getElementById('tarcAdminLoginForm');
		var btn    = document.getElementById('tarcAdminSubmitBtn');
		var notice = document.getElementById('tarcAdminNotice');

		notice.style.display = 'none';
		notice.className     = 'tarc-notice-msg';

		var origText = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js( __( 'Authenticating Super Admin...', 'tpd-tool' ) ); ?>';

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
				notice.innerHTML   = '<i class="fa-solid fa-circle-check"></i> ' + (res.data.message || '<?php echo esc_js( __( 'Access granted! Opening Super Admin Hub...', 'tpd-tool' ) ); ?>');
				notice.style.display = 'block';

				var dest = res.data.redirect_url || '<?php echo esc_js( $redirect_to_val ); ?>';
				setTimeout(function() {
					window.location.href = dest;
				}, 400);
			} else {
				btn.disabled = false;
				btn.innerHTML = origText;
				notice.className = 'tarc-notice-msg error';
				notice.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + ((res.data && res.data.message) ? res.data.message : '<?php echo esc_js( __( 'Invalid administrator credentials.', 'tpd-tool' ) ); ?>');
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
