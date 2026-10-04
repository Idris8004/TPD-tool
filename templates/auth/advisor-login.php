<?php
/**
 * Travel Advisor (Pro User) Dedicated Member Login Template
 * Matches Travel Partner Directory "Member Login" screen (media_1791124763477.png)
 * Supports standalone URL (/advisor-login/, /member-login/, /login/), shortcode [tpd_advisor_login], and Elementor Widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_user_logged_in() && ! isset( $_GET['preview_login'] ) && ! ( defined( 'ELEMENTOR_VERSION' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) ) {
	$uid = get_current_user_id();
	if ( TPD_Tool_Roles::is_supplier( $uid ) ) {
		wp_safe_redirect( home_url( '/supplier-dashboard/' ) );
		exit;
	}
	wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
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
		<title><?php esc_html_e( 'Member Login | Travel Partner Directory', 'tpd-tool' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body style="margin:0; padding:0; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;">
	<?php
}
?>
<style>
	.tpd-member-login-hero {
		min-height: 100vh;
		width: 100%;
		background-image: linear-gradient(135deg, rgba(10, 37, 64, 0.84) 0%, rgba(30, 58, 95, 0.78) 55%, rgba(71, 85, 105, 0.75) 100%), url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1800&auto=format&fit=crop&q=80');
		background-size: cover;
		background-position: center;
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 48px 20px;
		box-sizing: border-box;
		font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
	}
	.tpd-member-login-box {
		width: 100%;
		max-width: 650px;
		text-align: center;
		color: #ffffff;
	}
	.tpd-ml-title {
		font-size: 28px;
		font-weight: 600;
		margin: 0 0 28px;
		color: #ffffff;
		letter-spacing: 0.3px;
	}
	.tpd-ml-title span {
		color: #00d4ff;
		font-weight: 700;
	}
	.tpd-ml-error-pill {
		display: none;
		background: #ffffff;
		color: #ff2a2a;
		font-size: 16px;
		font-weight: 500;
		padding: 12px 26px;
		border-radius: 6px;
		margin: 0 auto 26px;
		width: fit-content;
		max-width: 90%;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
	}
	.tpd-ml-success-pill {
		display: none;
		background: #ffffff;
		color: #059669;
		font-size: 15px;
		font-weight: 600;
		padding: 12px 26px;
		border-radius: 6px;
		margin: 0 auto 26px;
		width: fit-content;
		box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
	}
	.tpd-ml-inputs-row {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 24px;
		margin-bottom: 24px;
	}
	.tpd-ml-field-wrap {
		position: relative;
		background: #ffffff;
		border-radius: 3px;
		display: flex;
		align-items: center;
	}
	.tpd-ml-field-wrap input {
		width: 100%;
		border: none;
		background: transparent;
		padding: 16px 20px;
		font-size: 15.5px;
		color: #334155;
		outline: none;
		box-sizing: border-box;
	}
	.tpd-ml-field-wrap input::placeholder {
		color: #64748b;
	}
	.tpd-ml-eye-btn {
		position: absolute;
		right: 14px;
		top: 50%;
		transform: translateY(-50%);
		background: none;
		border: none;
		color: #64748b;
		cursor: pointer;
		font-size: 16px;
		padding: 4px;
	}
	.tpd-ml-submit-btn {
		width: 100%;
		padding: 16px 24px;
		border: none;
		border-radius: 3px;
		background: linear-gradient(90deg, #00798c 0%, #00c4eb 100%);
		color: #ffffff;
		font-size: 16px;
		font-weight: 600;
		cursor: pointer;
		margin-bottom: 16px;
		transition: opacity 0.2s, transform 0.15s;
	}
	.tpd-ml-submit-btn:hover {
		opacity: 0.95;
	}
	.tpd-ml-membership-btn {
		display: block;
		width: 100%;
		padding: 15px 24px;
		background: rgba(100, 116, 139, 0.55);
		border: 1.5px solid #00c4eb;
		border-radius: 3px;
		color: #ffffff;
		font-size: 15.5px;
		font-weight: 600;
		text-decoration: none;
		box-sizing: border-box;
		margin-bottom: 28px;
		transition: background 0.2s;
	}
	.tpd-ml-membership-btn:hover {
		background: rgba(0, 196, 235, 0.22);
		color: #ffffff;
	}
	.tpd-ml-meta-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		flex-wrap: wrap;
		gap: 12px;
		margin-bottom: 22px;
		font-size: 16px;
	}
	.tpd-ml-supplier-switch {
		display: flex;
		align-items: center;
		gap: 12px;
		color: #ffffff;
	}
	.tpd-ml-click-here {
		display: inline-block;
		padding: 7px 16px;
		border: 1.5px solid #00c4eb;
		background: rgba(100, 116, 139, 0.45);
		color: #ffffff;
		text-decoration: none;
		font-weight: 600;
		font-size: 15px;
		border-radius: 2px;
		transition: all 0.2s;
	}
	.tpd-ml-click-here:hover {
		background: #00c4eb;
		color: #013243;
	}
	.tpd-ml-underline-link {
		color: #ffffff;
		text-decoration: underline;
		font-size: 15px;
		cursor: pointer;
		background: none;
		border: none;
		padding: 0;
	}
	.tpd-ml-remember-label {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		cursor: pointer;
		font-size: 14.5px;
		color: #ffffff;
	}
	.tpd-ml-remember-label input[type="checkbox"] {
		appearance: none;
		-webkit-appearance: none;
		width: 20px;
		height: 20px;
		border-radius: 50%;
		background: #ffffff;
		border: 2px solid #ffffff;
		cursor: pointer;
		position: relative;
	}
	.tpd-ml-remember-label input[type="checkbox"]:checked {
		background: #00c4eb;
	}
	@media (max-width: 640px) {
		.tpd-ml-inputs-row { grid-template-columns: 1fr; gap: 14px; }
		.tpd-ml-meta-row { flex-direction: column; align-items: flex-start; }
	}
</style>

<div class="tpd-member-login-hero">
	<div class="tpd-member-login-box">
		<h1 class="tpd-ml-title"><span>Member</span> Login</h1>

		<div id="tpd-advisor-login-error" class="tpd-ml-error-pill"><?php esc_html_e( 'Invalid username or password', 'tpd-tool' ); ?></div>
		<div id="tpd-advisor-login-success" class="tpd-ml-success-pill"></div>

		<form id="tpd-advisor-login-form" method="post" novalidate>
			<input type="hidden" name="action" value="tpd_user_login">
			<input type="hidden" name="portal_type" value="advisor">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

			<div class="tpd-ml-inputs-row">
				<div class="tpd-ml-field-wrap">
					<input type="text" name="username" required placeholder="<?php esc_attr_e( 'UserName', 'tpd-tool' ); ?>" autocomplete="username">
				</div>
				<div class="tpd-ml-field-wrap">
					<input type="password" name="password" id="tpd-ml-pwd-input" required placeholder="<?php esc_attr_e( 'Password', 'tpd-tool' ); ?>" style="padding-right:44px;" autocomplete="current-password">
					<button type="button" class="tpd-ml-eye-btn" onclick="var i=document.getElementById('tpd-ml-pwd-input'); i.type = (i.type==='password'?'text':'password');">
						<i class="fa-solid fa-eye"></i>
					</button>
				</div>
			</div>

			<button type="submit" class="tpd-ml-submit-btn" id="tpd-advisor-login-btn">
				<?php esc_html_e( 'Login to your Account', 'tpd-tool' ); ?>
			</button>

			<a href="<?php echo esc_url( home_url( '/advisor-registration/' ) ); ?>" class="tpd-ml-membership-btn">
				<?php esc_html_e( 'Not a Member Learn About Membership', 'tpd-tool' ); ?>
			</a>

			<div class="tpd-ml-meta-row">
				<div class="tpd-ml-supplier-switch">
					<span><?php esc_html_e( 'Looking for supplier login ?', 'tpd-tool' ); ?></span>
					<a href="<?php echo esc_url( home_url( '/supplier-login/' ) ); ?>" class="tpd-ml-click-here"><?php esc_html_e( 'Click Here', 'tpd-tool' ); ?></a>
				</div>
				<button type="button" class="tpd-ml-underline-link" onclick="document.getElementById('tpd-forgot-pwd-box').style.display='block';">
					<?php esc_html_e( 'Forgot Password ?', 'tpd-tool' ); ?>
				</button>
			</div>

			<div class="tpd-ml-meta-row" style="margin-bottom:0;">
				<label class="tpd-ml-remember-label">
					<input type="checkbox" name="remember" value="1">
					<span><?php esc_html_e( 'Remember Me', 'tpd-tool' ); ?></span>
				</label>
				<a href="mailto:support@travelpartnerdirectory.com" class="tpd-ml-underline-link"><?php esc_html_e( 'Contact Support', 'tpd-tool' ); ?></a>
			</div>
		</form>

		<!-- Inline Forgot Password Box (No wp-login.php redirect) -->
		<div id="tpd-forgot-pwd-box" style="display:none; margin-top:24px; background:rgba(15, 23, 42, 0.85); border:1px solid #00c4eb; border-radius:8px; padding:20px; text-align:left;">
			<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
				<strong style="font-size:14.5px; color:#00d4ff;"><?php esc_html_e( 'Reset Your Password', 'tpd-tool' ); ?></strong>
				<button type="button" onclick="document.getElementById('tpd-forgot-pwd-box').style.display='none';" style="background:none; border:none; color:#cbd5e1; cursor:pointer;">&times;</button>
			</div>
			<p style="font-size:13px; color:#e2e8f0; margin:0 0 12px;"><?php esc_html_e( 'Enter your username or email address and we will send you a password reset link.', 'tpd-tool' ); ?></p>
			<div style="display:flex; gap:10px;">
				<input type="text" id="tpd-reset-user-login" placeholder="<?php esc_attr_e( 'Enter Username or Email', 'tpd-tool' ); ?>" style="flex:1; padding:10px 14px; border:none; border-radius:4px;">
				<button type="button" id="tpd-btn-send-reset" style="background:#00c4eb; color:#013243; border:none; padding:10px 18px; border-radius:4px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Send Reset Link', 'tpd-tool' ); ?></button>
			</div>
			<div id="tpd-reset-msg" style="margin-top:10px; font-size:13px; color:#4ade80; display:none;"></div>
		</div>
	</div>
</div>

<script>
(function() {
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce = '<?php echo esc_js( wp_create_nonce( 'tpd_nonce' ) ); ?>';

	var form = document.getElementById('tpd-advisor-login-form');
	if (form) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			var errPill = document.getElementById('tpd-advisor-login-error');
			var okPill = document.getElementById('tpd-advisor-login-success');
			var btn = document.getElementById('tpd-advisor-login-btn');
			errPill.style.display = 'none';
			okPill.style.display = 'none';

			var origText = btn.textContent;
			btn.disabled = true;
			btn.textContent = 'Signing in...';

			var fd = new FormData(form);
			fetch(ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					okPill.textContent = res.data.message || 'Login successful! Redirecting to your Dashboard...';
					okPill.style.display = 'block';
					window.location.href = res.data.redirect_url || '<?php echo esc_js( home_url( '/advisor-dashboard/' ) ); ?>';
				} else {
					btn.disabled = false;
					btn.textContent = origText;
					errPill.textContent = (res.data && res.data.message) ? res.data.message : 'Invalid username or password';
					errPill.style.display = 'block';
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = origText;
				errPill.textContent = 'Invalid username or password';
				errPill.style.display = 'block';
			});
		});
	}

	var resetBtn = document.getElementById('tpd-btn-send-reset');
	if (resetBtn) {
		resetBtn.addEventListener('click', function() {
			var loginVal = document.getElementById('tpd-reset-user-login').value.trim();
			var msgEl = document.getElementById('tpd-reset-msg');
			if (!loginVal) return;
			resetBtn.disabled = true;
			var fd = new FormData();
			fd.append('action', 'tpd_forgot_password');
			fd.append('user_login', loginVal);
			fd.append('nonce', nonce);
			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					resetBtn.disabled = false;
					msgEl.style.display = 'block';
					msgEl.textContent = (res.data && res.data.message) ? res.data.message : 'Password reset instructions sent to your email.';
				});
		});
	}
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
