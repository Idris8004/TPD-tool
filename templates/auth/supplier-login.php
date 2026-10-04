<?php
/**
 * Supplier Partner Dedicated Login Template
 * Matches Travel Pro Directory Supplier Login screen (media_1791124760761.png)
 * Supports standalone URL (/supplier-login/), shortcode [tpd_supplier_login], and Elementor Widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_user_logged_in() && ! isset( $_GET['preview_login'] ) && ! ( defined( 'ELEMENTOR_VERSION' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) ) {
	$uid = get_current_user_id();
	if ( TPD_Tool_Roles::is_advisor( $uid ) ) {
		wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
		exit;
	}
	wp_safe_redirect( home_url( '/supplier-dashboard/' ) );
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
		<title><?php esc_html_e( 'Supplier Partner Login | Travel Partner Directory', 'tpd-tool' ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body style="margin:0; padding:0; font-family:'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;">
	<?php
}
?>
<style>
	.tpd-supplier-login-screen {
		min-height: 100vh;
		width: 100%;
		background: linear-gradient(120deg, #00798c 0%, #0096ad 55%, #00b4d8 100%);
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 48px 20px;
		box-sizing: border-box;
		font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
	}
	.tpd-supplier-login-card {
		width: 100%;
		max-width: 640px;
		background: #daf2f6;
		border-radius: 22px;
		padding: 54px 72px;
		box-sizing: border-box;
		box-shadow: 0 20px 50px rgba(1, 50, 67, 0.22);
		text-align: center;
	}
	.tpd-sl-logo-wrap {
		display: inline-flex;
		flex-direction: column;
		align-items: center;
		margin-bottom: 34px;
		color: #006d77;
		text-decoration: none;
	}
	.tpd-sl-pin-icon {
		width: 54px;
		height: 54px;
		border-radius: 50% 50% 50% 0;
		transform: rotate(-45deg);
		background: #006d77;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 14px;
		box-shadow: 0 6px 16px rgba(0, 109, 119, 0.25);
	}
	.tpd-sl-pin-icon i {
		transform: rotate(45deg);
		color: #daf2f6;
		font-size: 22px;
	}
	.tpd-sl-logo-wrap h2 {
		margin: 0;
		font-size: 21px;
		font-weight: 800;
		color: #006d77;
		line-height: 1.1;
	}
	.tpd-sl-logo-wrap span {
		font-size: 10.5px;
		font-weight: 700;
		letter-spacing: 2.5px;
		color: #006d77;
		margin-top: 3px;
	}
	.tpd-sl-form-inner {
		max-width: 440px;
		margin: 0 auto;
	}
	.tpd-sl-error-pill {
		display: none;
		background: #ffffff;
		color: #e11d48;
		font-size: 14.5px;
		font-weight: 600;
		padding: 11px 20px;
		border-radius: 6px;
		margin-bottom: 20px;
		box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
	}
	.tpd-sl-success-pill {
		display: none;
		background: #ffffff;
		color: #059669;
		font-size: 14.5px;
		font-weight: 600;
		padding: 11px 20px;
		border-radius: 6px;
		margin-bottom: 20px;
	}
	.tpd-sl-input-box {
		position: relative;
		background: #ffffff;
		border-radius: 3px;
		margin-bottom: 20px;
	}
	.tpd-sl-input-box input {
		width: 100%;
		border: none;
		background: transparent;
		padding: 16px 18px;
		font-size: 15px;
		color: #1e293b;
		outline: none;
		box-sizing: border-box;
	}
	.tpd-sl-input-box input::placeholder {
		color: #475569;
	}
	.tpd-sl-submit-btn {
		width: 100%;
		padding: 15px 24px;
		border: none;
		border-radius: 3px;
		background: linear-gradient(90deg, #006d77 0%, #00b4d8 100%);
		color: #ffffff;
		font-size: 16px;
		font-weight: 600;
		cursor: pointer;
		margin-top: 4px;
		margin-bottom: 24px;
		transition: opacity 0.2s;
	}
	.tpd-sl-submit-btn:hover {
		opacity: 0.94;
	}
	.tpd-sl-bottom-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		font-size: 14.5px;
	}
	.tpd-sl-remember {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		color: #0f172a;
		cursor: pointer;
		font-weight: 500;
	}
	.tpd-sl-remember input[type="checkbox"] {
		appearance: none;
		-webkit-appearance: none;
		width: 20px;
		height: 20px;
		border-radius: 50%;
		background: #ffffff;
		border: 1.5px solid #006d77;
		cursor: pointer;
	}
	.tpd-sl-remember input[type="checkbox"]:checked {
		background: #006d77;
	}
	.tpd-sl-forgot-btn {
		background: none;
		border: none;
		color: #00798c;
		font-size: 15.5px;
		font-weight: 700;
		cursor: pointer;
		padding: 0;
	}
	.tpd-sl-switch-links {
		margin-top: 26px;
		padding-top: 18px;
		border-top: 1px solid rgba(0, 109, 119, 0.15);
		display: flex;
		justify-content: space-between;
		flex-wrap: wrap;
		gap: 10px;
		font-size: 13px;
		color: #013243;
	}
	.tpd-sl-switch-links a {
		color: #006d77;
		font-weight: 700;
		text-decoration: none;
	}
	@media (max-width: 640px) {
		.tpd-supplier-login-card { padding: 36px 24px; }
	}
</style>

<div class="tpd-supplier-login-screen">
	<div class="tpd-supplier-login-card">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tpd-sl-logo-wrap">
			<div class="tpd-sl-pin-icon"><i class="fa-solid fa-user"></i></div>
			<h2>Travel Pro</h2>
			<span>DIRECTORY</span>
		</a>

		<div class="tpd-sl-form-inner">
			<div id="tpd-supplier-login-error" class="tpd-sl-error-pill"><?php esc_html_e( 'Invalid username or password', 'tpd-tool' ); ?></div>
			<div id="tpd-supplier-login-success" class="tpd-sl-success-pill"></div>

			<form id="tpd-supplier-login-form" method="post" novalidate>
				<input type="hidden" name="action" value="tpd_user_login">
				<input type="hidden" name="portal_type" value="supplier">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

				<div class="tpd-sl-input-box">
					<input type="text" name="username" required placeholder="<?php esc_attr_e( 'UserName', 'tpd-tool' ); ?>" autocomplete="username">
				</div>

				<div class="tpd-sl-input-box">
					<input type="password" name="password" id="tpd-sl-pwd-input" required placeholder="<?php esc_attr_e( 'Password', 'tpd-tool' ); ?>" style="padding-right:42px;" autocomplete="current-password">
					<button type="button" onclick="var i=document.getElementById('tpd-sl-pwd-input'); i.type=(i.type==='password'?'text':'password');" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#64748b; cursor:pointer;">
						<i class="fa-solid fa-eye"></i>
					</button>
				</div>

				<button type="submit" class="tpd-sl-submit-btn" id="tpd-supplier-login-btn">
					<?php esc_html_e( 'Login to your Account', 'tpd-tool' ); ?>
				</button>

				<div class="tpd-sl-bottom-row">
					<label class="tpd-sl-remember">
						<input type="checkbox" name="remember" value="1">
						<span><?php esc_html_e( 'Remember Me', 'tpd-tool' ); ?></span>
					</label>

					<button type="button" class="tpd-sl-forgot-btn" onclick="document.getElementById('tpd-supp-forgot-box').style.display='block';">
						<?php esc_html_e( 'Forgot Password ?', 'tpd-tool' ); ?>
					</button>
				</div>
			</form>

			<!-- Inline Forgot Password Box -->
			<div id="tpd-supp-forgot-box" style="display:none; margin-top:20px; background:#ffffff; border-radius:10px; padding:16px; text-align:left;">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
					<strong style="font-size:13.5px; color:#006d77;"><?php esc_html_e( 'Reset Password', 'tpd-tool' ); ?></strong>
					<button type="button" onclick="document.getElementById('tpd-supp-forgot-box').style.display='none';" style="background:none; border:none; cursor:pointer;">&times;</button>
				</div>
				<div style="display:flex; gap:8px;">
					<input type="text" id="tpd-supp-reset-input" placeholder="<?php esc_attr_e( 'Username or Email', 'tpd-tool' ); ?>" style="flex:1; padding:9px 12px; border:1px solid #cbd5e1; border-radius:4px;">
					<button type="button" id="tpd-supp-reset-btn" style="background:#006d77; color:#fff; border:none; padding:9px 14px; border-radius:4px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Send', 'tpd-tool' ); ?></button>
				</div>
				<div id="tpd-supp-reset-msg" style="margin-top:8px; font-size:12.5px; color:#059669; display:none;"></div>
			</div>

			<div class="tpd-sl-switch-links">
				<span><?php esc_html_e( 'New Supplier?', 'tpd-tool' ); ?> <a href="<?php echo esc_url( home_url( '/supplier-registration/' ) ); ?>"><?php esc_html_e( 'Register Partner Account', 'tpd-tool' ); ?></a></span>
				<span><?php esc_html_e( 'Travel Advisor?', 'tpd-tool' ); ?> <a href="<?php echo esc_url( home_url( '/advisor-login/' ) ); ?>"><?php esc_html_e( 'Member Login', 'tpd-tool' ); ?></a></span>
			</div>
		</div>
	</div>
</div>

<script>
(function() {
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var nonce = '<?php echo esc_js( wp_create_nonce( 'tpd_nonce' ) ); ?>';

	var form = document.getElementById('tpd-supplier-login-form');
	if (form) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			var errPill = document.getElementById('tpd-supplier-login-error');
			var okPill = document.getElementById('tpd-supplier-login-success');
			var btn = document.getElementById('tpd-supplier-login-btn');
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
					okPill.textContent = res.data.message || 'Login successful! Opening Supplier Portal...';
					okPill.style.display = 'block';
					window.location.href = res.data.redirect_url || '<?php echo esc_js( home_url( '/supplier-dashboard/' ) ); ?>';
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

	var resetBtn = document.getElementById('tpd-supp-reset-btn');
	if (resetBtn) {
		resetBtn.addEventListener('click', function() {
			var val = document.getElementById('tpd-supp-reset-input').value.trim();
			var msg = document.getElementById('tpd-supp-reset-msg');
			if (!val) return;
			resetBtn.disabled = true;
			var fd = new FormData();
			fd.append('action', 'tpd_forgot_password');
			fd.append('user_login', val);
			fd.append('nonce', nonce);
			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					resetBtn.disabled = false;
					msg.style.display = 'block';
					msg.textContent = (res.data && res.data.message) ? res.data.message : 'Password reset link sent to your email.';
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
