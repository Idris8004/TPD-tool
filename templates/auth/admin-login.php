<?php
/**
 * Dedicated Super Admin Portal Login Template
 * 
 * Completely separates Super Admin Dashboard authentication from native WordPress /wp-login.php
 * and standard public member/advisor/supplier login forms.
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
if ( is_user_logged_in() && ! isset( $_GET['preview_login'] ) && ! TPD_Tool_Roles::is_elementor_editor_context() ) {
	$current_uid = get_current_user_id();
	if ( TPD_Tool_Roles::is_admin( $current_uid ) ) {
		$target_redirect = ! empty( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/tpd-admin/' );
		wp_safe_redirect( $target_redirect );
		exit;
	}
}

$initial_notice = '';
if ( isset( $_GET['auth_required'] ) ) {
	$initial_notice = __( 'Administrator authentication required to access the TPD Super Admin Control Hub.', 'tpd-tool' );
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
		<title><?php esc_html_e( 'Super Admin Login | Travel Partner Directory', 'tpd-tool' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body style="margin:0; padding:0; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; background-color: #0b1526;">
	<?php
}
?>

<style>
	.tpd-admin-login-screen {
		min-height: 100vh;
		width: 100%;
		background: radial-gradient(circle at 50% 20%, #1e293b 0%, #0b1526 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 40px 20px;
		box-sizing: border-box;
		font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
	}
	.tpd-admin-login-card {
		width: 100%;
		max-width: 500px;
		background: #ffffff;
		border-radius: 20px;
		padding: 44px 40px;
		box-sizing: border-box;
		box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
		text-align: center;
		position: relative;
		overflow: hidden;
	}
	.tpd-admin-login-card::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 6px;
		background: linear-gradient(90deg, #f59e0b 0%, #00798c 50%, #0284c7 100%);
	}
	.tpd-al-logo-wrap {
		display: inline-flex;
		flex-direction: column;
		align-items: center;
		margin-bottom: 24px;
		color: #0b1526;
		text-decoration: none;
	}
	.tpd-al-shield-icon {
		width: 58px;
		height: 58px;
		border-radius: 16px;
		background: linear-gradient(135deg, #0b1526 0%, #1e293b 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 12px;
		color: #f59e0b;
		font-size: 26px;
		box-shadow: 0 8px 20px rgba(11, 21, 38, 0.35);
		border: 1px solid rgba(245, 158, 11, 0.3);
	}
	.tpd-al-title {
		font-size: 22px;
		font-weight: 800;
		color: #0b1526;
		margin: 0 0 4px;
		letter-spacing: -0.5px;
	}
	.tpd-al-subtitle {
		font-size: 11px;
		font-weight: 800;
		letter-spacing: 1.2px;
		color: #00798c;
		text-transform: uppercase;
	}
	.tpd-al-badge-notice {
		background: #f8fafc;
		border: 1px solid #e2e8f0;
		border-radius: 10px;
		padding: 10px 14px;
		margin-bottom: 22px;
		font-size: 12.5px;
		color: #64748b;
		display: flex;
		align-items: center;
		gap: 8px;
		text-align: left;
		line-height: 1.4;
	}
	.tpd-al-badge-notice i {
		color: #f59e0b;
		font-size: 16px;
		flex-shrink: 0;
	}
	.tpd-al-error-pill {
		background: #fef2f2;
		color: #b91c1c;
		border: 1px solid #fecaca;
		border-radius: 8px;
		padding: 10px 14px;
		font-size: 13px;
		margin-bottom: 18px;
		display: none;
		text-align: left;
		line-height: 1.4;
	}
	.tpd-al-success-pill {
		background: #ecfdf5;
		color: #047857;
		border: 1px solid #a7f3d0;
		border-radius: 8px;
		padding: 10px 14px;
		font-size: 13px;
		margin-bottom: 18px;
		display: none;
		text-align: left;
	}
	.tpd-al-form-group {
		margin-bottom: 16px;
		text-align: left;
	}
	.tpd-al-form-group label {
		display: block;
		font-size: 12px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		color: #475569;
		margin-bottom: 6px;
	}
	.tpd-al-input-wrap {
		position: relative;
		display: flex;
		align-items: center;
	}
	.tpd-al-input-wrap i.prefix-icon {
		position: absolute;
		left: 14px;
		color: #94a3b8;
		font-size: 14px;
	}
	.tpd-al-input {
		width: 100%;
		padding: 12px 14px 12px 38px;
		border: 1.5px solid #cbd5e1;
		border-radius: 10px;
		font-size: 14px;
		color: #0f172a;
		background: #f8fafc;
		transition: all 0.2s ease;
		box-sizing: border-box;
		font-family: inherit;
	}
	.tpd-al-input:focus {
		outline: none;
		border-color: #00798c;
		background: #ffffff;
		box-shadow: 0 0 0 3px rgba(0, 121, 140, 0.15);
	}
	.tpd-al-eye-btn {
		position: absolute;
		right: 12px;
		background: none;
		border: none;
		color: #94a3b8;
		cursor: pointer;
		font-size: 14px;
		padding: 4px;
	}
	.tpd-al-eye-btn:hover {
		color: #0f172a;
	}
	.tpd-al-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin: 14px 0 22px;
		font-size: 13px;
	}
	.tpd-al-remember {
		display: flex;
		align-items: center;
		gap: 6px;
		color: #475569;
		cursor: pointer;
		user-select: none;
	}
	.tpd-al-remember input {
		cursor: pointer;
	}
	.tpd-al-forgot-link {
		color: #00798c;
		text-decoration: none;
		font-weight: 600;
		cursor: pointer;
		background: none;
		border: none;
		padding: 0;
		font-size: 13px;
	}
	.tpd-al-forgot-link:hover {
		text-decoration: underline;
	}
	.tpd-al-submit-btn {
		width: 100%;
		padding: 13px 20px;
		background: linear-gradient(135deg, #0b1526 0%, #1e293b 100%);
		color: #ffffff;
		border: 1px solid rgba(245, 158, 11, 0.35);
		border-radius: 10px;
		font-size: 15px;
		font-weight: 700;
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		gap: 10px;
		transition: all 0.2s ease;
		box-shadow: 0 4px 15px rgba(11, 21, 38, 0.25);
	}
	.tpd-al-submit-btn:hover {
		background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
		border-color: #f59e0b;
		transform: translateY(-1px);
	}
	.tpd-al-submit-btn:disabled {
		opacity: 0.7;
		cursor: not-allowed;
	}
	.tpd-al-switch-bar {
		margin-top: 24px;
		padding-top: 18px;
		border-top: 1px solid #f1f5f9;
		display: flex;
		flex-direction: column;
		gap: 8px;
		font-size: 12.5px;
		color: #64748b;
	}
	.tpd-al-switch-bar a {
		color: #00798c;
		text-decoration: none;
		font-weight: 600;
	}
	.tpd-al-switch-bar a:hover {
		text-decoration: underline;
	}
	.tpd-al-forgot-panel {
		display: none;
		margin-top: 16px;
		padding: 16px;
		background: #f8fafc;
		border-radius: 10px;
		border: 1px solid #e2e8f0;
		text-align: left;
	}
</style>

<div class="tpd-admin-login-screen">
	<div class="tpd-admin-login-card">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tpd-al-logo-wrap">
			<div class="tpd-al-shield-icon">
				<i class="fa-solid fa-shield-halved"></i>
			</div>
			<h1 class="tpd-al-title"><?php esc_html_e( 'Super Admin Hub', 'tpd-tool' ); ?></h1>
			<span class="tpd-al-subtitle"><?php esc_html_e( 'Travel Partner Directory Control Center', 'tpd-tool' ); ?></span>
		</a>

		<div class="tpd-al-badge-notice">
			<i class="fa-solid fa-lock"></i>
			<span><?php esc_html_e( 'Secure Administrative Access. All login attempts are verified and restricted to authorized site directors.', 'tpd-tool' ); ?></span>
		</div>

		<div id="tpd-admin-login-error" class="tpd-al-error-pill" style="<?php echo ! empty( $initial_notice ) ? 'display:block;' : ''; ?>">
			<?php echo esc_html( $initial_notice ); ?>
		</div>
		<div id="tpd-admin-login-success" class="tpd-al-success-pill"></div>

		<form id="tpd-admin-login-form" novalidate>
			<input type="hidden" name="action" value="tpd_user_login">
			<input type="hidden" name="portal_type" value="admin">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to_val ); ?>">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

			<div class="tpd-al-form-group">
				<label><?php esc_html_e( 'Administrator Username or Email', 'tpd-tool' ); ?></label>
				<div class="tpd-al-input-wrap">
					<i class="fa-solid fa-user-shield prefix-icon"></i>
					<input type="text" name="username" required class="tpd-al-input" placeholder="admin@domain.com" autocomplete="username">
				</div>
			</div>

			<div class="tpd-al-form-group">
				<label><?php esc_html_e( 'Security Password', 'tpd-tool' ); ?></label>
				<div class="tpd-al-input-wrap">
					<i class="fa-solid fa-key prefix-icon"></i>
					<input type="password" name="password" id="tpd-admin-pwd-input" required class="tpd-al-input" placeholder="••••••••••••" autocomplete="current-password" style="padding-right:40px;">
					<button type="button" class="tpd-al-eye-btn" onclick="var p=document.getElementById('tpd-admin-pwd-input'); p.type=(p.type==='password'?'text':'password');" title="<?php esc_attr_e( 'Toggle Password Visibility', 'tpd-tool' ); ?>">
						<i class="fa-solid fa-eye"></i>
					</button>
				</div>
			</div>

			<div class="tpd-al-row">
				<label class="tpd-al-remember">
					<input type="checkbox" name="remember" value="1">
					<span><?php esc_html_e( 'Remember Session', 'tpd-tool' ); ?></span>
				</label>
				<button type="button" class="tpd-al-forgot-link" onclick="document.getElementById('tpd-admin-forgot-box').style.display='block';">
					<?php esc_html_e( 'Reset Password', 'tpd-tool' ); ?>
				</button>
			</div>

			<button type="submit" id="tpd-admin-login-btn" class="tpd-al-submit-btn">
				<i class="fa-solid fa-right-to-bracket text-gold"></i>
				<span><?php esc_html_e( 'Sign In to Super Admin Hub', 'tpd-tool' ); ?></span>
			</button>
		</form>

		<!-- Inline Password Reset Box -->
		<div id="tpd-admin-forgot-box" class="tpd-al-forgot-panel">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
				<strong style="font-size:13.5px; color:#0b1526;"><?php esc_html_e( 'Admin Password Reset', 'tpd-tool' ); ?></strong>
				<button type="button" onclick="document.getElementById('tpd-admin-forgot-box').style.display='none';" style="background:none; border:none; cursor:pointer; font-size:18px;">&times;</button>
			</div>
			<p style="font-size:12px; color:#64748b; margin:0 0 10px;"><?php esc_html_e( 'Enter your administrative email or username to receive a secure password recovery link.', 'tpd-tool' ); ?></p>
			<div style="display:flex; gap:8px;">
				<input type="text" id="tpd-admin-reset-input" placeholder="admin@domain.com" style="flex:1; padding:9px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px;">
				<button type="button" id="tpd-admin-reset-btn" style="background:#0b1526; color:#fff; border:none; padding:9px 14px; border-radius:6px; font-weight:700; cursor:pointer; font-size:13px;"><?php esc_html_e( 'Send Link', 'tpd-tool' ); ?></button>
			</div>
			<div id="tpd-admin-reset-msg" style="margin-top:8px; font-size:12px; color:#059669; display:none;"></div>
		</div>

		<div class="tpd-al-switch-bar">
			<div>
				<?php esc_html_e( 'Travel Advisor Portal?', 'tpd-tool' ); ?> <a href="<?php echo esc_url( home_url( '/advisor-login/' ) ); ?>"><?php esc_html_e( 'Member Login &rarr;', 'tpd-tool' ); ?></a>
			</div>
			<div>
				<?php esc_html_e( 'Supplier Partner Portal?', 'tpd-tool' ); ?> <a href="<?php echo esc_url( home_url( '/supplier-login/' ) ); ?>"><?php esc_html_e( 'Supplier Sign-in &rarr;', 'tpd-tool' ); ?></a>
			</div>
			<div style="margin-top:4px;">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><i class="fa-solid fa-arrow-left"></i> <?php esc_html_e( 'Back to Directory Home', 'tpd-tool' ); ?></a>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('tpd-admin-login-form');
	var btn  = document.getElementById('tpd-admin-login-btn');
	var err  = document.getElementById('tpd-admin-login-error');
	var ok   = document.getElementById('tpd-admin-login-success');
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

	if (form) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			err.style.display = 'none';
			ok.style.display  = 'none';

			var origHtml = btn.innerHTML;
			btn.disabled = true;
			btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Authenticating...';

			var fd = new FormData(form);

			fetch(ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					ok.textContent = res.data.message || 'Access granted! Opening Super Admin Hub...';
					ok.style.display = 'block';
					var target = res.data.redirect_url || '<?php echo esc_js( $redirect_to_val ); ?>';
					setTimeout(function() {
						window.location.href = target;
					}, 400);
				} else {
					btn.disabled = false;
					btn.innerHTML = origHtml;
					err.textContent = (res.data && res.data.message) ? res.data.message : 'Invalid administrator credentials.';
					err.style.display = 'block';
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.innerHTML = origHtml;
				err.textContent = 'Server communication error. Please try again.';
				err.style.display = 'block';
			});
		});
	}

	// Reset Password AJAX
	var resetBtn = document.getElementById('tpd-admin-reset-btn');
	if (resetBtn) {
		resetBtn.addEventListener('click', function() {
			var val = document.getElementById('tpd-admin-reset-input').value.trim();
			var msg = document.getElementById('tpd-admin-reset-msg');
			if (!val) return;
			resetBtn.disabled = true;
			resetBtn.textContent = 'Sending...';

			var fd = new FormData();
			fd.append('action', 'tpd_forgot_password');
			fd.append('user_login', val);
			fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'tpd_nonce' ) ); ?>');

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					resetBtn.disabled = false;
					resetBtn.textContent = 'Send Link';
					msg.style.display = 'block';
					msg.style.color = res.success ? '#059669' : '#dc2626';
					msg.textContent = (res.data && res.data.message) ? res.data.message : 'Notice sent.';
				})
				.catch(function() {
					resetBtn.disabled = false;
					resetBtn.textContent = 'Send Link';
					msg.style.display = 'block';
					msg.style.color = '#dc2626';
					msg.textContent = 'Failed to process request.';
				});
		});
	}
});
</script>

<?php
if ( $is_standalone ) {
	?>
	</body>
	</html>
	<?php
}
