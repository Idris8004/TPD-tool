<?php
/**
 * TARC Official Dual Gateway Portal Login Template
 * Travel Advisor Resource Center (TARC)
 *
 * Supports:
 * - Standalone full-page portal (No theme header/footer)
 * - Homepage takeover (when enabled in settings)
 * - Shortcodes: [tpd_portal_login], [tarc_portal_login], [tpd_dual_login]
 * - Elementor Widget
 *
 * Features:
 * - Real AJAX authentication via tpd_user_login (Role-specific verification & redirects)
 * - Real inline forgot-password handling via tpd_forgot_password
 * - Full company branding & accredited value proposition cards
 * - Interactive accordion split (One side open with luxury form, one side closed teaser)
 * - Official TARC Brand Palette
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Redirect already-logged-in users unless previewing or inside Elementor editor
if ( is_user_logged_in() && ! isset( $_GET['preview_login'] ) && ( ! class_exists( 'TPD_Tool_Roles' ) || ! TPD_Tool_Roles::is_elementor_editor_context() ) ) {
	$current_uid = get_current_user_id();
	if ( class_exists( 'TPD_Tool_Roles' ) ) {
		if ( TPD_Tool_Roles::is_advisor( $current_uid ) || TPD_Tool_Roles::is_admin( $current_uid ) ) {
			wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
			exit;
		}
		if ( TPD_Tool_Roles::is_supplier( $current_uid ) ) {
			wp_safe_redirect( home_url( '/supplier-dashboard/' ) );
			exit;
		}
	}
}

$is_standalone = ! did_action( 'wp_head' );

if ( $is_standalone ) {
	?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php esc_html_e( 'Official Portal Login | Travel Advisor Resource Center', 'tpd-tool' ); ?></title>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class( 'tpd-standalone-portal-body' ); ?>>
	<?php
}
?>

<!-- Scoped Brand Design Tokens & Stylesheet -->
<style>
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

	body.tpd-standalone-portal-body {
		margin: 0;
		padding: 0;
		font-family: var(--tarc-font-body);
		background: var(--tarc-warm-alabaster);
		color: var(--tarc-dark-teal);
		min-height: 100vh;
		display: flex;
		flex-direction: column;
		overflow-x: hidden;
		line-height: 1.5;
	}

	.tarc-portal-wrapper {
		font-family: var(--tarc-font-body);
		color: var(--tarc-dark-teal);
		background: var(--tarc-warm-alabaster);
		width: 100%;
		box-sizing: border-box;
	}

	.tarc-portal-wrapper * {
		box-sizing: border-box;
	}

	/* Top Brand Header */
	.tarc-portal-nav {
		background: var(--tarc-dark-teal);
		border-bottom: 1px solid rgba(151, 188, 199, 0.25);
		padding: 16px 44px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		color: var(--tarc-pure-white);
		position: sticky;
		top: 0;
		z-index: 100;
	}

	.tarc-nav-brand {
		display: flex;
		align-items: center;
		gap: 14px;
		text-decoration: none;
	}

	.tarc-nav-emblem {
		width: 48px;
		height: 48px;
		background: rgba(255, 255, 255, 0.08);
		border: 1.5px solid var(--tarc-warm-ochre-gold);
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--tarc-warm-ochre-gold);
		font-size: 22px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
	}

	.tarc-nav-titles {
		display: flex;
		flex-direction: column;
	}

	.tarc-nav-name-full {
		font-family: var(--tarc-font-heading);
		font-size: 23px;
		font-weight: 700;
		letter-spacing: -0.01em;
		color: var(--tarc-pure-white);
		line-height: 1.2;
	}

	.tarc-nav-subtext {
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.16em;
		color: var(--tarc-soft-mist-teal);
		margin-top: 2px;
	}

	.tarc-nav-trust {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	.tarc-trust-pill {
		font-size: 11.5px;
		font-weight: 600;
		padding: 6px 14px;
		border-radius: 20px;
		background: rgba(255, 255, 255, 0.08);
		border: 1px solid rgba(151, 188, 199, 0.3);
		color: var(--tarc-pure-white);
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.tarc-trust-pill i {
		color: var(--tarc-warm-ochre-gold);
	}

	/* Hero & Company Intro Section */
	.tarc-company-hero {
		position: relative;
		background: 
			linear-gradient(145deg, rgba(21, 65, 79, 0.94) 0%, rgba(35, 106, 127, 0.88) 100%),
			url('https://images.unsplash.com/photo-1548574505-5e239809ee19?w=1600&auto=format&fit=crop&q=85') center/cover no-repeat;
		padding: 56px 40px 48px;
		color: var(--tarc-pure-white);
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
	}

	.tarc-hero-container {
		max-width: 980px;
		width: 100%;
		margin: 0 auto;
	}

	.tarc-accredited-badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		background: rgba(197, 160, 89, 0.15);
		border: 1.5px solid rgba(197, 160, 89, 0.5);
		padding: 6px 16px;
		border-radius: 30px;
		font-size: 11.5px;
		font-weight: 700;
		letter-spacing: 0.1em;
		text-transform: uppercase;
		color: var(--tarc-warm-ochre-gold);
		margin-bottom: 20px;
		backdrop-filter: blur(8px);
	}

	.tarc-hero-title {
		font-family: var(--tarc-font-heading);
		font-size: 42px;
		line-height: 1.18;
		font-weight: 700;
		margin: 0 0 16px;
		color: var(--tarc-pure-white);
	}

	.tarc-hero-title span {
		color: var(--tarc-warm-ochre-gold);
		font-style: italic;
	}

	.tarc-hero-desc {
		font-size: 15.5px;
		line-height: 1.7;
		color: rgba(243, 244, 238, 0.92);
		max-width: 840px;
		margin: 0 auto 34px;
	}

	.tarc-features-grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		gap: 16px;
		text-align: left;
	}

	.tarc-feature-card {
		display: flex;
		align-items: center;
		gap: 14px;
		background: rgba(21, 65, 79, 0.65);
		border: 1px solid rgba(151, 188, 199, 0.25);
		padding: 14px 18px;
		border-radius: 12px;
		backdrop-filter: blur(10px);
		transition: all 0.25s ease;
	}

	.tarc-feature-card:hover {
		background: rgba(21, 65, 79, 0.9);
		border-color: var(--tarc-warm-ochre-gold);
		transform: translateY(-2px);
	}

	.tarc-feature-icon {
		width: 38px;
		height: 38px;
		border-radius: 10px;
		background: rgba(255, 255, 255, 0.08);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--tarc-warm-ochre-gold);
		font-size: 17px;
		flex-shrink: 0;
	}

	.tarc-feature-text {
		font-size: 13px;
		font-weight: 600;
		color: var(--tarc-pure-white);
		line-height: 1.35;
	}

	/* Gateways Section */
	.tarc-gateways-section {
		padding: 44px 40px 64px;
		max-width: 1140px;
		width: 100%;
		margin: 0 auto;
	}

	.tarc-section-intro {
		text-align: center;
		margin-bottom: 30px;
	}

	.tarc-section-eyebrow {
		font-size: 12px;
		font-weight: 800;
		letter-spacing: 0.16em;
		text-transform: uppercase;
		color: var(--tarc-deep-slate-teal);
		margin-bottom: 6px;
		display: block;
	}

	.tarc-section-heading {
		font-family: var(--tarc-font-heading);
		font-size: 32px;
		color: var(--tarc-dark-teal);
		font-weight: 700;
		margin: 0;
	}

	.tarc-gateway-pill-switch {
		display: inline-flex;
		background: var(--tarc-warm-sand);
		padding: 5px;
		border-radius: 14px;
		border: 1px solid rgba(151, 188, 199, 0.4);
		margin: 18px auto 0;
		box-shadow: 0 4px 12px rgba(21, 65, 79, 0.04);
	}

	.tarc-switch-btn {
		padding: 11px 24px;
		border-radius: 10px;
		border: none;
		background: transparent;
		font-family: inherit;
		font-size: 13.5px;
		font-weight: 700;
		color: #5e7a84;
		cursor: pointer;
		display: flex;
		align-items: center;
		gap: 8px;
		transition: all 0.25s ease;
	}

	.tarc-switch-btn.active.advisor-pill {
		background: var(--tarc-deep-slate-teal);
		color: var(--tarc-pure-white);
		box-shadow: 0 4px 14px rgba(35, 106, 127, 0.3);
	}

	.tarc-switch-btn.active.supplier-pill {
		background: var(--tarc-dark-teal);
		color: var(--tarc-pure-white);
		box-shadow: 0 4px 14px rgba(21, 65, 79, 0.3);
	}

	.tarc-dot {
		width: 7px;
		height: 7px;
		border-radius: 50%;
		background: var(--tarc-warm-ochre-gold);
	}

	/* Split Accordion Container */
	.tarc-split-container {
		display: flex;
		gap: 26px;
		align-items: stretch;
		margin-top: 26px;
		transition: all 0.45s cubic-bezier(0.4, 0, 0.2, 1);
	}

	.tarc-gateway-card {
		background: var(--tarc-pure-white);
		border-radius: 22px;
		border: 1.5px solid rgba(151, 188, 199, 0.45);
		box-shadow: 0 16px 40px rgba(21, 65, 79, 0.06);
		overflow: hidden;
		display: flex;
		flex-direction: column;
		transition: all 0.45s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
	}

	.tarc-gateway-header {
		padding: 24px 32px;
		color: var(--tarc-pure-white);
		position: relative;
	}

	.card-advisor .tarc-gateway-header {
		background: linear-gradient(135deg, var(--tarc-deep-slate-teal) 0%, #174857 100%);
		border-bottom: 3px solid var(--tarc-warm-ochre-gold);
	}

	.card-supplier .tarc-gateway-header {
		background: linear-gradient(135deg, var(--tarc-dark-teal) 0%, #0d2830 100%);
		border-bottom: 3px solid var(--tarc-sage-teal);
	}

	.tarc-badge-tag {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 10.5px;
		font-weight: 800;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		padding: 4px 11px;
		border-radius: 12px;
		background: rgba(255, 255, 255, 0.15);
		margin-bottom: 10px;
	}

	.card-advisor .tarc-badge-tag {
		color: var(--tarc-warm-ochre-gold);
		border: 1px solid rgba(197, 160, 89, 0.4);
	}

	.card-supplier .tarc-badge-tag {
		color: var(--tarc-sage-teal);
		border: 1px solid rgba(100, 162, 173, 0.4);
	}

	.tarc-gateway-name {
		font-family: var(--tarc-font-heading);
		font-size: 24px;
		font-weight: 700;
		margin: 0 0 4px;
		display: flex;
		align-items: center;
		gap: 10px;
	}

	.tarc-gateway-caption {
		font-size: 13.5px;
		line-height: 1.5;
		margin: 0;
		color: rgba(243, 244, 238, 0.9);
	}

	/* Accordion Mode Switch */
	.tarc-split-container.mode-advisor .card-advisor {
		flex: 1.6;
		cursor: default;
		border-color: rgba(35, 106, 127, 0.4);
		box-shadow: 0 20px 50px rgba(21, 65, 79, 0.1);
	}
	.tarc-split-container.mode-advisor .card-advisor .tarc-form-view {
		display: flex;
		animation: tarcFadeIn 0.35s ease;
	}
	.tarc-split-container.mode-advisor .card-advisor .tarc-teaser-view {
		display: none;
	}

	.tarc-split-container.mode-advisor .card-supplier {
		flex: 0.72;
		cursor: pointer;
		background: #fafbfc;
		border-color: rgba(151, 188, 199, 0.4);
	}
	.tarc-split-container.mode-advisor .card-supplier:hover {
		border-color: var(--tarc-dark-teal);
		box-shadow: 0 18px 45px rgba(21, 65, 79, 0.14);
		transform: translateY(-2px);
	}
	.tarc-split-container.mode-advisor .card-supplier .tarc-form-view {
		display: none;
	}
	.tarc-split-container.mode-advisor .card-supplier .tarc-teaser-view {
		display: flex;
		animation: tarcFadeIn 0.35s ease;
	}

	.tarc-split-container.mode-supplier .card-supplier {
		flex: 1.6;
		cursor: default;
		border-color: rgba(21, 65, 79, 0.4);
		box-shadow: 0 20px 50px rgba(21, 65, 79, 0.1);
	}
	.tarc-split-container.mode-supplier .card-supplier .tarc-form-view {
		display: flex;
		animation: tarcFadeIn 0.35s ease;
	}
	.tarc-split-container.mode-supplier .card-supplier .tarc-teaser-view {
		display: none;
	}

	.tarc-split-container.mode-supplier .card-advisor {
		flex: 0.72;
		cursor: pointer;
		background: #fafbfc;
		border-color: rgba(151, 188, 199, 0.4);
	}
	.tarc-split-container.mode-supplier .card-advisor:hover {
		border-color: var(--tarc-deep-slate-teal);
		box-shadow: 0 18px 45px rgba(21, 65, 79, 0.14);
		transform: translateY(-2px);
	}
	.tarc-split-container.mode-supplier .card-advisor .tarc-form-view {
		display: none;
	}
	.tarc-split-container.mode-supplier .card-advisor .tarc-teaser-view {
		display: flex;
		animation: tarcFadeIn 0.35s ease;
	}

	@keyframes tarcFadeIn {
		from { opacity: 0; transform: translateY(10px); }
		to { opacity: 1; transform: translateY(0); }
	}

	/* Form View */
	.tarc-form-view {
		padding: 34px 34px 30px;
		flex-direction: column;
		flex: 1;
		justify-content: space-between;
		background: #ffffff;
	}

	.tarc-privilege-ribbon {
		display: flex;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
		margin-bottom: 24px;
		padding-bottom: 18px;
		border-bottom: 1px solid var(--tarc-warm-sand);
	}

	.tarc-privilege-pill {
		font-size: 11px;
		font-weight: 700;
		letter-spacing: 0.04em;
		padding: 5px 11px;
		border-radius: 8px;
		background: var(--tarc-warm-sand);
		color: var(--tarc-dark-teal);
		display: inline-flex;
		align-items: center;
		gap: 6px;
		border: 1px solid rgba(151, 188, 199, 0.35);
	}

	.tarc-privilege-pill i {
		color: var(--tarc-warm-ochre-gold);
		font-size: 11px;
	}

	.tarc-form-group {
		margin-bottom: 20px;
	}

	.tarc-label-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}

	.tarc-form-label {
		font-size: 12px;
		font-weight: 800;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: var(--tarc-dark-teal);
	}

	.tarc-label-note {
		font-size: 11.5px;
		color: #7b949e;
	}

	.tarc-input-box {
		position: relative;
		display: flex;
		align-items: center;
		border-radius: 12px;
	}

	.tarc-icon-frame {
		position: absolute;
		left: 10px;
		width: 34px;
		height: 34px;
		border-radius: 8px;
		background: rgba(100, 162, 173, 0.12);
		display: flex;
		align-items: center;
		justify-content: center;
		color: var(--tarc-sage-teal);
		font-size: 14px;
		transition: all 0.2s ease;
		pointer-events: none;
	}

	.tarc-input-ctrl {
		width: 100%;
		height: 52px;
		padding: 0 46px 0 54px;
		border: 1.5px solid var(--tarc-soft-mist-teal);
		border-radius: 12px;
		font-size: 14.5px;
		font-family: inherit;
		color: var(--tarc-dark-teal);
		background: #fdfdfc;
		outline: none;
		transition: all 0.25s ease;
	}

	.tarc-input-ctrl::placeholder {
		color: #9cb1ba;
		font-size: 14px;
	}

	.card-advisor .tarc-input-ctrl:focus {
		background: #ffffff;
		border-color: var(--tarc-deep-slate-teal);
		box-shadow: 0 0 0 4px rgba(35, 106, 127, 0.12);
	}

	.card-advisor .tarc-input-ctrl:focus ~ .tarc-icon-frame {
		background: var(--tarc-deep-slate-teal);
		color: #ffffff;
	}

	.card-supplier .tarc-input-ctrl:focus {
		background: #ffffff;
		border-color: var(--tarc-dark-teal);
		box-shadow: 0 0 0 4px rgba(21, 65, 79, 0.12);
	}

	.card-supplier .tarc-input-ctrl:focus ~ .tarc-icon-frame {
		background: var(--tarc-dark-teal);
		color: #ffffff;
	}

	.tarc-toggle-eye {
		position: absolute;
		right: 12px;
		background: none;
		border: none;
		color: var(--tarc-sage-teal);
		cursor: pointer;
		padding: 8px;
		font-size: 15px;
		border-radius: 6px;
	}

	.tarc-toggle-eye:hover {
		color: var(--tarc-dark-teal);
		background: rgba(151, 188, 199, 0.15);
	}

	.tarc-meta-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin: 10px 0 24px;
		font-size: 13px;
	}

	.tarc-remember-check {
		display: flex;
		align-items: center;
		gap: 8px;
		color: #5e7a84;
		cursor: pointer;
		user-select: none;
		font-weight: 500;
	}

	.tarc-remember-check input {
		accent-color: var(--tarc-deep-slate-teal);
		width: 16px;
		height: 16px;
		border-radius: 4px;
		cursor: pointer;
	}

	.card-supplier .tarc-remember-check input {
		accent-color: var(--tarc-dark-teal);
	}

	.tarc-forgot-btn {
		background: none;
		border: none;
		padding: 0;
		color: var(--tarc-deep-slate-teal);
		font-family: inherit;
		font-size: 13px;
		font-weight: 700;
		cursor: pointer;
		transition: color 0.2s;
	}

	.tarc-forgot-btn:hover {
		color: var(--tarc-warm-ochre-gold);
		text-decoration: underline;
	}

	.tarc-submit-btn {
		width: 100%;
		height: 52px;
		border: none;
		border-radius: 12px;
		color: var(--tarc-pure-white);
		font-family: inherit;
		font-size: 15px;
		font-weight: 700;
		letter-spacing: 0.02em;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 12px;
		transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
	}

	.card-advisor .tarc-submit-btn {
		background: linear-gradient(135deg, var(--tarc-deep-slate-teal) 0%, #174857 100%);
		box-shadow: 0 8px 20px rgba(35, 106, 127, 0.28);
		border: 1px solid rgba(197, 160, 89, 0.3);
	}

	.card-advisor .tarc-submit-btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 12px 28px rgba(35, 106, 127, 0.38);
	}

	.card-supplier .tarc-submit-btn {
		background: linear-gradient(135deg, var(--tarc-dark-teal) 0%, #0d2830 100%);
		box-shadow: 0 8px 20px rgba(21, 65, 79, 0.28);
		border: 1px solid rgba(100, 162, 173, 0.4);
	}

	.card-supplier .tarc-submit-btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 12px 28px rgba(21, 65, 79, 0.38);
	}

	.tarc-submit-btn i {
		color: var(--tarc-warm-ochre-gold);
		font-size: 14px;
		transition: transform 0.2s ease;
	}

	.tarc-submit-btn:hover i {
		transform: translateX(4px);
	}

	.tarc-security-line {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
		margin-top: 14px;
		font-size: 11.5px;
		color: #7b949e;
		font-weight: 500;
	}

	.tarc-security-line i {
		color: var(--tarc-sage-teal);
	}

	.tarc-notice-msg {
		display: none;
		margin-top: 14px;
		padding: 12px 16px;
		border-radius: 10px;
		font-size: 13px;
		line-height: 1.45;
		text-align: left;
	}

	.tarc-notice-msg.error {
		background: #fef2f2;
		border: 1px solid #fecaca;
		color: #b91c1c;
	}

	.tarc-notice-msg.success {
		background: #f0fdf4;
		border: 1px solid #bbf7d0;
		color: #15803d;
	}

	.tarc-card-footer {
		margin-top: 26px;
		padding: 16px 20px;
		background: var(--tarc-warm-alabaster);
		border: 1px solid rgba(151, 188, 199, 0.3);
		border-radius: 12px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		font-size: 13px;
		color: #5e7a84;
	}

	.tarc-reg-link {
		font-weight: 700;
		color: var(--tarc-deep-slate-teal);
		text-decoration: none;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		transition: color 0.2s;
	}

	.card-supplier .tarc-reg-link {
		color: var(--tarc-dark-teal);
	}

	.tarc-reg-link:hover {
		color: var(--tarc-warm-ochre-gold);
		text-decoration: underline;
	}

	/* Teaser View */
	.tarc-teaser-view {
		padding: 44px 28px;
		flex-direction: column;
		align-items: center;
		text-align: center;
		justify-content: center;
		flex: 1;
		gap: 18px;
		background: #fafbfc;
		transition: background 0.3s ease;
	}

	.tarc-gateway-card:hover .tarc-teaser-view {
		background: #f5f7f9;
	}

	.tarc-teaser-icon {
		width: 70px;
		height: 70px;
		border-radius: 20px;
		background: var(--tarc-pure-white);
		border: 1.5px solid var(--tarc-soft-mist-teal);
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 28px;
		box-shadow: 0 8px 20px rgba(21, 65, 79, 0.08);
		transition: all 0.3s ease;
	}

	.card-advisor .tarc-teaser-icon {
		color: var(--tarc-deep-slate-teal);
	}

	.card-supplier .tarc-teaser-icon {
		color: var(--tarc-dark-teal);
	}

	.tarc-gateway-card:hover .tarc-teaser-icon {
		transform: scale(1.08) translateY(-3px);
		border-color: var(--tarc-warm-ochre-gold);
		box-shadow: 0 12px 24px rgba(21, 65, 79, 0.14);
	}

	.tarc-teaser-title {
		font-family: var(--tarc-font-heading);
		font-size: 21px;
		font-weight: 700;
		color: var(--tarc-dark-teal);
		margin: 4px 0 0;
		line-height: 1.3;
	}

	.tarc-teaser-desc {
		font-size: 13.5px;
		line-height: 1.6;
		color: #5e7a84;
		max-width: 270px;
		margin: 0;
	}

	.tarc-teaser-btn {
		padding: 12px 22px;
		border-radius: 30px;
		background: var(--tarc-pure-white);
		border: 1.5px solid rgba(151, 188, 199, 0.6);
		font-size: 13px;
		font-weight: 700;
		color: var(--tarc-dark-teal);
		display: flex;
		align-items: center;
		gap: 8px;
		transition: all 0.25s ease;
		margin-top: 6px;
		box-shadow: 0 4px 10px rgba(21, 65, 79, 0.05);
	}

	.tarc-gateway-card:hover .tarc-teaser-btn {
		background: var(--tarc-dark-teal);
		color: var(--tarc-pure-white);
		border-color: var(--tarc-dark-teal);
		box-shadow: 0 8px 18px rgba(21, 65, 79, 0.2);
	}

	.card-advisor:hover .tarc-teaser-btn {
		background: var(--tarc-deep-slate-teal);
		border-color: var(--tarc-deep-slate-teal);
		box-shadow: 0 8px 18px rgba(35, 106, 127, 0.2);
	}

	.tarc-teaser-btn i {
		color: var(--tarc-warm-ochre-gold);
		font-size: 12px;
		transition: transform 0.2s;
	}

	.tarc-gateway-card:hover .tarc-teaser-btn i {
		transform: translateX(3px);
	}

	/* Inline Password Reset Box */
	.tarc-reset-modal {
		display: none;
		margin-top: 20px;
		background: var(--tarc-warm-sand);
		border: 1.5px solid var(--tarc-warm-ochre-gold);
		border-radius: 12px;
		padding: 20px;
		text-align: left;
	}

	.tarc-reset-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 8px;
	}

	.tarc-reset-header strong {
		color: var(--tarc-dark-teal);
		font-size: 14.5px;
	}

	.tarc-reset-close {
		background: none;
		border: none;
		font-size: 18px;
		cursor: pointer;
		color: #7b949e;
	}

	.tarc-reset-input-row {
		display: flex;
		gap: 10px;
		margin-top: 12px;
	}

	.tarc-reset-input {
		flex: 1;
		height: 44px;
		padding: 0 14px;
		border: 1.5px solid var(--tarc-soft-mist-teal);
		border-radius: 8px;
		font-size: 13.5px;
		outline: none;
	}

	.tarc-reset-submit {
		height: 44px;
		padding: 0 18px;
		background: var(--tarc-deep-slate-teal);
		color: #ffffff;
		border: none;
		border-radius: 8px;
		font-weight: 700;
		cursor: pointer;
		font-size: 13px;
	}

	/* Bottom Legal Footer */
	.tarc-portal-footer {
		background: var(--tarc-dark-teal);
		border-top: 1px solid rgba(151, 188, 199, 0.25);
		padding: 24px 44px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		color: var(--tarc-soft-mist-teal);
		font-size: 12.5px;
	}

	.tarc-footer-links {
		display: flex;
		gap: 18px;
	}

	.tarc-footer-links a {
		color: var(--tarc-soft-mist-teal);
		text-decoration: none;
	}

	.tarc-footer-links a:hover {
		color: var(--tarc-warm-ochre-gold);
	}

	@media (max-width: 1024px) {
		.tarc-features-grid {
			grid-template-columns: 1fr 1fr;
		}
		.tarc-split-container {
			flex-direction: column;
		}
		.tarc-split-container.mode-advisor .card-advisor,
		.tarc-split-container.mode-supplier .card-supplier,
		.tarc-split-container.mode-advisor .card-supplier,
		.tarc-split-container.mode-supplier .card-advisor {
			flex: 1;
			width: 100%;
		}
		.tarc-card-footer {
			flex-direction: column;
			gap: 10px;
			text-align: center;
		}
	}

	@media (max-width: 768px) {
		.tarc-portal-nav {
			padding: 14px 20px;
			flex-direction: column;
			gap: 12px;
			text-align: center;
		}
		.tarc-company-hero {
			padding: 40px 20px;
		}
		.tarc-hero-title {
			font-size: 32px;
		}
		.tarc-features-grid {
			grid-template-columns: 1fr;
		}
		.tarc-gateways-section {
			padding: 32px 20px 48px;
		}
		.tarc-form-view {
			padding: 26px 20px;
		}
		.tarc-portal-footer {
			flex-direction: column;
			gap: 14px;
			text-align: center;
			padding: 20px;
		}
	}
</style>

<div class="tarc-portal-wrapper">

	<!-- Top Navigation Bar -->
	<header class="tarc-portal-nav">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tarc-nav-brand">
			<div class="tarc-nav-emblem">
				<i class="fa-solid fa-compass"></i>
			</div>
			<div class="tarc-nav-titles">
				<span class="tarc-nav-name-full"><?php esc_html_e( 'Travel Advisor Resource Center', 'tpd-tool' ); ?></span>
				<span class="tarc-nav-subtext"><?php esc_html_e( 'TARC • Accredited Directory & Learning Platform', 'tpd-tool' ); ?></span>
			</div>
		</a>

		<div class="tarc-nav-trust">
			<div class="tarc-trust-pill">
				<i class="fa-solid fa-shield-halved"></i>
				<span><?php esc_html_e( '256-Bit TLS SSO', 'tpd-tool' ); ?></span>
			</div>
			<div class="tarc-trust-pill">
				<i class="fa-solid fa-circle-check"></i>
				<span><?php esc_html_e( 'Verified Network', 'tpd-tool' ); ?></span>
			</div>
		</div>
	</header>

	<!-- Company Hero & Mission Introduction -->
	<section class="tarc-company-hero">
		<div class="tarc-hero-container">
			<div class="tarc-accredited-badge">
				<i class="fa-solid fa-shield-halved"></i> <?php esc_html_e( 'Accredited Industry Gateway', 'tpd-tool' ); ?>
			</div>

			<h1 class="tarc-hero-title">
				<?php esc_html_e( 'Connecting Elite Advisors with', 'tpd-tool' ); ?> <span><?php esc_html_e( 'World-Class Suppliers', 'tpd-tool' ); ?>.</span>
			</h1>

			<p class="tarc-hero-desc">
				<?php esc_html_e( 'Travel Advisor Resource Center (TARC) is the premier collaborative ecosystem dedicated to luxury travel professionals. We connect certified independent travel advisors with world-class hotels, cruise lines, destination management companies, and tour operators — providing verified supplier directories, accredited LMS specialist training, and exclusive partner amenities.', 'tpd-tool' ); ?>
			</p>

			<!-- 4 Highlight Cards -->
			<div class="tarc-features-grid">
				<div class="tarc-feature-card">
					<div class="tarc-feature-icon">
						<i class="fa-solid fa-graduation-cap"></i>
					</div>
					<div class="tarc-feature-text">
						<?php esc_html_e( 'TARC Academy & LMS Specialist Certifications', 'tpd-tool' ); ?>
					</div>
				</div>

				<div class="tarc-feature-card">
					<div class="tarc-feature-icon">
						<i class="fa-solid fa-hotel"></i>
					</div>
					<div class="tarc-feature-text">
						<?php esc_html_e( '500+ Verified Luxury Suppliers & Amenity Perks', 'tpd-tool' ); ?>
					</div>
				</div>

				<div class="tarc-feature-card">
					<div class="tarc-feature-icon">
						<i class="fa-solid fa-comments"></i>
					</div>
					<div class="tarc-feature-text">
						<?php esc_html_e( 'Real-Time Advisor-Supplier Direct Messaging', 'tpd-tool' ); ?>
					</div>
				</div>

				<div class="tarc-feature-card">
					<div class="tarc-feature-icon">
						<i class="fa-solid fa-chart-line"></i>
					</div>
					<div class="tarc-feature-text">
						<?php esc_html_e( 'Tiered Commission Accelerators & Co-Op Deals', 'tpd-tool' ); ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Dual Gateway Portals (Accordion Split) -->
	<main class="tarc-gateways-section">
		<div class="tarc-section-intro">
			<span class="tarc-section-eyebrow"><?php esc_html_e( 'Interactive Portal Gateway', 'tpd-tool' ); ?></span>
			<h2 class="tarc-section-heading"><?php esc_html_e( 'Select Your Portal to Sign In', 'tpd-tool' ); ?></h2>

			<div class="tarc-gateway-pill-switch">
				<button type="button" class="tarc-switch-btn active advisor-pill" id="tarcBtnPillAdvisor" onclick="tarcSetGateway('advisor')">
					<span class="tarc-dot"></span>
					<i class="fa-solid fa-user-tie"></i> <?php esc_html_e( 'Travel Advisor', 'tpd-tool' ); ?>
				</button>
				<button type="button" class="tarc-switch-btn" id="tarcBtnPillSupplier" onclick="tarcSetGateway('supplier')">
					<i class="fa-solid fa-building"></i> <?php esc_html_e( 'Supplier Partner', 'tpd-tool' ); ?>
				</button>
			</div>
		</div>

		<div class="tarc-split-container mode-advisor" id="tarcSplitContainer">

			<!-- ==================== GATEWAY 1: TRAVEL ADVISOR ==================== -->
			<section class="tarc-gateway-card card-advisor" id="tarcCardAdvisor" onclick="tarcOnCardClick('advisor')">
				<header class="tarc-gateway-header">
					<div class="tarc-badge-tag">
						<i class="fa-solid fa-user-tie"></i> <?php esc_html_e( 'Advisor & Agency Access', 'tpd-tool' ); ?>
					</div>
					<h3 class="tarc-gateway-name">
						<i class="fa-solid fa-graduation-cap" style="color:var(--tarc-warm-ochre-gold);"></i>
						<?php esc_html_e( 'Travel Advisor Portal', 'tpd-tool' ); ?>
					</h3>
					<p class="tarc-gateway-caption">
						<?php esc_html_e( 'Access LMS specialist training, directory of vetted properties, and client amenity benefits.', 'tpd-tool' ); ?>
					</p>
				</header>

				<!-- STATE A: OPEN FULL FORM -->
				<div class="tarc-form-view" onclick="event.stopPropagation()">
					<div class="tarc-privilege-ribbon">
						<span class="tarc-privilege-pill"><i class="fa-solid fa-graduation-cap"></i> <?php esc_html_e( 'LMS Modules & Exams', 'tpd-tool' ); ?></span>
						<span class="tarc-privilege-pill"><i class="fa-solid fa-hotel"></i> <?php esc_html_e( '500+ Verified Suppliers', 'tpd-tool' ); ?></span>
						<span class="tarc-privilege-pill"><i class="fa-solid fa-comments"></i> <?php esc_html_e( 'Direct Supplier Chat', 'tpd-tool' ); ?></span>
					</div>

					<form id="tarcAdvisorLoginForm" onsubmit="tarcHandleAuth(event, 'advisor')">
						<input type="hidden" name="action" value="tpd_user_login">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
						<input type="hidden" name="portal_type" value="advisor">

						<div class="tarc-form-group">
							<div class="tarc-label-row">
								<label class="tarc-form-label" for="tarcAdvisorUser"><?php esc_html_e( 'Advisor Email or Username', 'tpd-tool' ); ?></label>
								<span class="tarc-label-note"><?php esc_html_e( 'Registered Account', 'tpd-tool' ); ?></span>
							</div>
							<div class="tarc-input-box">
								<div class="tarc-icon-frame">
									<i class="fa-solid fa-envelope"></i>
								</div>
								<input 
									type="text" 
									id="tarcAdvisorUser" 
									name="username" 
									class="tarc-input-ctrl" 
									placeholder="advisor@tarctpc.com" 
									required 
									autocomplete="username"
								/>
							</div>
						</div>

						<div class="tarc-form-group">
							<div class="tarc-label-row">
								<label class="tarc-form-label" for="tarcAdvisorPass"><?php esc_html_e( 'Password', 'tpd-tool' ); ?></label>
								<span class="tarc-label-note"><?php esc_html_e( 'Minimum 8 characters', 'tpd-tool' ); ?></span>
							</div>
							<div class="tarc-input-box">
								<div class="tarc-icon-frame">
									<i class="fa-solid fa-lock"></i>
								</div>
								<input 
									type="password" 
									id="tarcAdvisorPass" 
									name="password" 
									class="tarc-input-ctrl" 
									placeholder="••••••••••••" 
									required 
									autocomplete="current-password"
								/>
								<button type="button" class="tarc-toggle-eye" onclick="tarcTogglePass('tarcAdvisorPass', this)" title="<?php esc_attr_e( 'Show/Hide Password', 'tpd-tool' ); ?>">
									<i class="fa-regular fa-eye"></i>
								</button>
							</div>
						</div>

						<div class="tarc-meta-row">
							<label class="tarc-remember-check">
								<input type="checkbox" name="remember" value="1" checked />
								<span><?php esc_html_e( 'Keep me signed in on this device', 'tpd-tool' ); ?></span>
							</label>
							<button type="button" class="tarc-forgot-btn" onclick="tarcOpenReset('advisor')">
								<?php esc_html_e( 'Forgot Password?', 'tpd-tool' ); ?>
							</button>
						</div>

						<button type="submit" class="tarc-submit-btn" id="tarcBtnAdvisorSubmit">
							<span><?php esc_html_e( 'Sign In to Advisor Portal', 'tpd-tool' ); ?></span>
							<i class="fa-solid fa-arrow-right"></i>
						</button>

						<div class="tarc-security-line">
							<i class="fa-solid fa-shield-halved"></i>
							<span><?php esc_html_e( 'Protected by TARC 256-Bit TLS SSO • Auto-Session Timeout', 'tpd-tool' ); ?></span>
						</div>

						<div class="tarc-notice-msg" id="tarcAdvisorNotice"></div>
					</form>

					<!-- Inline Password Reset Box -->
					<div class="tarc-reset-modal" id="tarcAdvisorResetBox">
						<div class="tarc-reset-header">
							<strong><i class="fa-solid fa-key" style="color:var(--tarc-warm-ochre-gold);"></i> <?php esc_html_e( 'Reset Advisor Password', 'tpd-tool' ); ?></strong>
							<button type="button" class="tarc-reset-close" onclick="document.getElementById('tarcAdvisorResetBox').style.display='none';">&times;</button>
						</div>
						<p style="font-size:12.5px; color:#5e7a84; margin:0 0 10px;"><?php esc_html_e( 'Enter your registered email address and we will dispatch a secure reset link immediately.', 'tpd-tool' ); ?></p>
						<div class="tarc-reset-input-row">
							<input type="email" id="tarcAdvisorResetEmail" class="tarc-reset-input" placeholder="advisor@example.com">
							<button type="button" class="tarc-reset-submit" onclick="tarcSubmitReset('advisor')"><?php esc_html_e( 'Send Link', 'tpd-tool' ); ?></button>
						</div>
						<div id="tarcAdvisorResetMsg" style="margin-top:8px; font-size:12.5px; display:none;"></div>
					</div>

					<footer class="tarc-card-footer">
						<span><?php esc_html_e( 'Not yet an accredited advisor?', 'tpd-tool' ); ?></span>
						<a href="<?php echo esc_url( home_url( '/advisor-registration/' ) ); ?>" class="tarc-reg-link">
							<span><?php esc_html_e( 'Apply for Advisor Accreditation', 'tpd-tool' ); ?></span>
							<i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
						</a>
					</footer>
				</div>

				<!-- STATE B: CLOSED TEASER -->
				<div class="tarc-teaser-view">
					<div class="tarc-teaser-icon">
						<i class="fa-solid fa-graduation-cap"></i>
					</div>
					<h4 class="tarc-teaser-title"><?php esc_html_e( 'Travel Advisor Portal', 'tpd-tool' ); ?></h4>
					<p class="tarc-teaser-desc"><?php esc_html_e( 'Access specialist certifications, directory perks, and verified luxury itineraries.', 'tpd-tool' ); ?></p>
					<div class="tarc-teaser-btn">
						<span><?php esc_html_e( 'Open Advisor Portal', 'tpd-tool' ); ?></span>
						<i class="fa-solid fa-arrow-right"></i>
					</div>
				</div>
			</section>

			<!-- ==================== GATEWAY 2: SUPPLIER PARTNER ==================== -->
			<section class="tarc-gateway-card card-supplier" id="tarcCardSupplier" onclick="tarcOnCardClick('supplier')">
				<header class="tarc-gateway-header">
					<div class="tarc-badge-tag">
						<i class="fa-solid fa-building"></i> <?php esc_html_e( 'Hospitality Brands & Operators', 'tpd-tool' ); ?>
					</div>
					<h3 class="tarc-gateway-name">
						<i class="fa-solid fa-hotel" style="color:var(--tarc-sage-teal);"></i>
						<?php esc_html_e( 'Supplier Partner Portal', 'tpd-tool' ); ?>
					</h3>
					<p class="tarc-gateway-caption">
						<?php esc_html_e( 'Manage property listings, update incentives, publish co-ops, and message accredited advisors.', 'tpd-tool' ); ?>
					</p>
				</header>

				<!-- STATE A: OPEN FULL FORM -->
				<div class="tarc-form-view" onclick="event.stopPropagation()">
					<div class="tarc-privilege-ribbon">
						<span class="tarc-privilege-pill"><i class="fa-solid fa-building"></i> <?php esc_html_e( 'Directory Management', 'tpd-tool' ); ?></span>
						<span class="tarc-privilege-pill"><i class="fa-solid fa-bullhorn"></i> <?php esc_html_e( 'Co-Op Promotions', 'tpd-tool' ); ?></span>
						<span class="tarc-privilege-pill"><i class="fa-solid fa-envelope-open-text"></i> <?php esc_html_e( 'Advisor Inquiries', 'tpd-tool' ); ?></span>
					</div>

					<form id="tarcSupplierLoginForm" onsubmit="tarcHandleAuth(event, 'supplier')">
						<input type="hidden" name="action" value="tpd_user_login">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
						<input type="hidden" name="portal_type" value="supplier">

						<div class="tarc-form-group">
							<div class="tarc-label-row">
								<label class="tarc-form-label" for="tarcSupplierUser"><?php esc_html_e( 'Supplier Partner Email', 'tpd-tool' ); ?></label>
								<span class="tarc-label-note"><?php esc_html_e( 'Company Account', 'tpd-tool' ); ?></span>
							</div>
							<div class="tarc-input-box">
								<div class="tarc-icon-frame">
									<i class="fa-solid fa-envelope"></i>
								</div>
								<input 
									type="email" 
									id="tarcSupplierUser" 
									name="username" 
									class="tarc-input-ctrl" 
									placeholder="partner@resortluxury.com" 
									required 
									autocomplete="username"
								/>
							</div>
						</div>

						<div class="tarc-form-group">
							<div class="tarc-label-row">
								<label class="tarc-form-label" for="tarcSupplierPass"><?php esc_html_e( 'Password', 'tpd-tool' ); ?></label>
								<span class="tarc-label-note"><?php esc_html_e( 'Minimum 8 characters', 'tpd-tool' ); ?></span>
							</div>
							<div class="tarc-input-box">
								<div class="tarc-icon-frame">
									<i class="fa-solid fa-lock"></i>
								</div>
								<input 
									type="password" 
									id="tarcSupplierPass" 
									name="password" 
									class="tarc-input-ctrl" 
									placeholder="••••••••••••" 
									required 
									autocomplete="current-password"
								/>
								<button type="button" class="tarc-toggle-eye" onclick="tarcTogglePass('tarcSupplierPass', this)" title="<?php esc_attr_e( 'Show/Hide Password', 'tpd-tool' ); ?>">
									<i class="fa-regular fa-eye"></i>
								</button>
							</div>
						</div>

						<div class="tarc-meta-row">
							<label class="tarc-remember-check">
								<input type="checkbox" name="remember" value="1" checked />
								<span><?php esc_html_e( 'Keep me signed in on this device', 'tpd-tool' ); ?></span>
							</label>
							<button type="button" class="tarc-forgot-btn" onclick="tarcOpenReset('supplier')">
								<?php esc_html_e( 'Forgot Password?', 'tpd-tool' ); ?>
							</button>
						</div>

						<button type="submit" class="tarc-submit-btn" id="tarcBtnSupplierSubmit">
							<span><?php esc_html_e( 'Sign In to Supplier Portal', 'tpd-tool' ); ?></span>
							<i class="fa-solid fa-arrow-right"></i>
						</button>

						<div class="tarc-security-line">
							<i class="fa-solid fa-shield-halved"></i>
							<span><?php esc_html_e( 'Protected by TARC 256-Bit TLS SSO • Partner Data Protection', 'tpd-tool' ); ?></span>
						</div>

						<div class="tarc-notice-msg" id="tarcSupplierNotice"></div>
					</form>

					<!-- Inline Password Reset Box -->
					<div class="tarc-reset-modal" id="tarcSupplierResetBox">
						<div class="tarc-reset-header">
							<strong><i class="fa-solid fa-key" style="color:var(--tarc-warm-ochre-gold);"></i> <?php esc_html_e( 'Reset Supplier Password', 'tpd-tool' ); ?></strong>
							<button type="button" class="tarc-reset-close" onclick="document.getElementById('tarcSupplierResetBox').style.display='none';">&times;</button>
						</div>
						<p style="font-size:12.5px; color:#5e7a84; margin:0 0 10px;"><?php esc_html_e( 'Enter your registered supplier partner email address to receive password instructions.', 'tpd-tool' ); ?></p>
						<div class="tarc-reset-input-row">
							<input type="email" id="tarcSupplierResetEmail" class="tarc-reset-input" placeholder="partner@hotelgroup.com">
							<button type="button" class="tarc-reset-submit" onclick="tarcSubmitReset('supplier')"><?php esc_html_e( 'Send Link', 'tpd-tool' ); ?></button>
						</div>
						<div id="tarcSupplierResetMsg" style="margin-top:8px; font-size:12.5px; display:none;"></div>
					</div>

					<footer class="tarc-card-footer">
						<span><?php esc_html_e( 'Want to feature your luxury brand?', 'tpd-tool' ); ?></span>
						<a href="<?php echo esc_url( home_url( '/supplier-registration/' ) ); ?>" class="tarc-reg-link">
							<span><?php esc_html_e( 'Join as Supplier Partner', 'tpd-tool' ); ?></span>
							<i class="fa-solid fa-arrow-right" style="font-size:11px;"></i>
						</a>
					</footer>
				</div>

				<!-- STATE B: CLOSED TEASER -->
				<div class="tarc-teaser-view">
					<div class="tarc-teaser-icon">
						<i class="fa-solid fa-hotel"></i>
					</div>
					<h4 class="tarc-teaser-title"><?php esc_html_e( 'Supplier Partner Portal', 'tpd-tool' ); ?></h4>
					<p class="tarc-teaser-desc"><?php esc_html_e( 'Manage directory profiles, advisor outreach, and co-op marketing packages.', 'tpd-tool' ); ?></p>
					<div class="tarc-teaser-btn">
						<span><?php esc_html_e( 'Open Supplier Portal', 'tpd-tool' ); ?></span>
						<i class="fa-solid fa-arrow-right"></i>
					</div>
				</div>
			</section>

		</div>
	</main>

	<!-- Bottom Legal Footer -->
	<footer class="tarc-portal-footer">
		<div>
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php esc_html_e( 'Travel Advisor Resource Center (TARC). All rights reserved.', 'tpd-tool' ); ?></span>
		</div>
		<div class="tarc-footer-links">
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php esc_html_e( 'Privacy Policy', 'tpd-tool' ); ?></a>
			<span>&bull;</span>
			<a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>"><?php esc_html_e( 'Terms of Service', 'tpd-tool' ); ?></a>
			<span>&bull;</span>
			<a href="mailto:admin@thetarc.com"><?php esc_html_e( 'Administrator Support', 'tpd-tool' ); ?></a>
		</div>
	</footer>

</div>

<!-- Interactive Accordion & Real Authentication Scripts -->
<script>
(function() {
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
	var activeRole = 'advisor';

	window.tarcSetGateway = function(role) {
		activeRole = role;
		var container = document.getElementById('tarcSplitContainer');
		var pillAdv = document.getElementById('tarcBtnPillAdvisor');
		var pillSup = document.getElementById('tarcBtnPillSupplier');

		if (role === 'advisor') {
			container.className = 'tarc-split-container mode-advisor';
			pillAdv.className = 'tarc-switch-btn active advisor-pill';
			pillAdv.innerHTML = '<span class="tarc-dot"></span><i class="fa-solid fa-user-tie"></i> <?php echo esc_js( __( 'Travel Advisor', 'tpd-tool' ) ); ?>';
			pillSup.className = 'tarc-switch-btn';
			pillSup.innerHTML = '<i class="fa-solid fa-building"></i> <?php echo esc_js( __( 'Supplier Partner', 'tpd-tool' ) ); ?>';
		} else {
			container.className = 'tarc-split-container mode-supplier';
			pillSup.className = 'tarc-switch-btn active supplier-pill';
			pillSup.innerHTML = '<span class="tarc-dot"></span><i class="fa-solid fa-building"></i> <?php echo esc_js( __( 'Supplier Partner', 'tpd-tool' ) ); ?>';
			pillAdv.className = 'tarc-switch-btn';
			pillAdv.innerHTML = '<i class="fa-solid fa-user-tie"></i> <?php echo esc_js( __( 'Travel Advisor', 'tpd-tool' ) ); ?>';
		}
	};

	window.tarcOnCardClick = function(role) {
		if (activeRole !== role) {
			window.tarcSetGateway(role);
		}
	};

	window.tarcTogglePass = function(inputId, btn) {
		var field = document.getElementById(inputId);
		var icon = btn.querySelector('i');
		if (field.type === 'password') {
			field.type = 'text';
			icon.className = 'fa-regular fa-eye-slash';
		} else {
			field.type = 'password';
			icon.className = 'fa-regular fa-eye';
		}
	};

	window.tarcOpenReset = function(role) {
		var box = document.getElementById(role === 'advisor' ? 'tarcAdvisorResetBox' : 'tarcSupplierResetBox');
		box.style.display = box.style.display === 'block' ? 'none' : 'block';
	};

	window.tarcSubmitReset = function(role) {
		var emailInput = document.getElementById(role === 'advisor' ? 'tarcAdvisorResetEmail' : 'tarcSupplierResetEmail');
		var msgEl = document.getElementById(role === 'advisor' ? 'tarcAdvisorResetMsg' : 'tarcSupplierResetMsg');
		var emailVal = emailInput.value.trim();
		if (!emailVal) return;

		msgEl.style.display = 'block';
		msgEl.style.color = '#0284c7';
		msgEl.textContent = 'Processing request...';

		var fd = new FormData();
		fd.append('action', 'tpd_forgot_password');
		fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'tpd_nonce' ) ); ?>');
		fd.append('user_login', emailVal);

		fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					msgEl.style.color = '#15803d';
					msgEl.textContent = res.data && res.data.message ? res.data.message : 'Password reset link sent to your email!';
				} else {
					msgEl.style.color = '#b91c1c';
					msgEl.textContent = res.data && res.data.message ? res.data.message : 'Could not find that email address.';
				}
			})
			.catch(function() {
				msgEl.style.color = '#b91c1c';
				msgEl.textContent = 'Network error. Please try again later.';
			});
	};

	window.tarcHandleAuth = function(e, role) {
		e.preventDefault();
		var isAdvisor = role === 'advisor';
		var form = document.getElementById(isAdvisor ? 'tarcAdvisorLoginForm' : 'tarcSupplierLoginForm');
		var btn = document.getElementById(isAdvisor ? 'tarcBtnAdvisorSubmit' : 'tarcBtnSupplierSubmit');
		var notice = document.getElementById(isAdvisor ? 'tarcAdvisorNotice' : 'tarcSupplierNotice');
		var origBtnText = btn.innerHTML;

		notice.style.display = 'none';
		notice.className = 'tarc-notice-msg';

		btn.disabled = true;
		btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js( __( 'Authenticating credentials...', 'tpd-tool' ) ); ?>';

		var fd = new FormData(form);

		fetch(ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin'
		})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res.success) {
				notice.className = 'tarc-notice-msg success';
				notice.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + (res.data && res.data.message ? res.data.message : 'Login successful! Redirecting to workspace...');
				notice.style.display = 'block';

				var redirectTarget = res.data && res.data.redirect_url 
					? res.data.redirect_url 
					: (isAdvisor ? '<?php echo esc_js( home_url( '/advisor-dashboard/' ) ); ?>' : '<?php echo esc_js( home_url( '/supplier-dashboard/' ) ); ?>');
				
				setTimeout(function() {
					window.location.href = redirectTarget;
				}, 600);
			} else {
				btn.disabled = false;
				btn.innerHTML = origBtnText;

				notice.className = 'tarc-notice-msg error';
				var err = (res.data && res.data.message) ? res.data.message : 'Invalid login credentials.';
				notice.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + err;
				notice.style.display = 'block';
			}
		})
		.catch(function() {
			btn.disabled = false;
			btn.innerHTML = origBtnText;

			notice.className = 'tarc-notice-msg error';
			notice.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Unable to connect to server. Please try again.';
			notice.style.display = 'block';
		});
	};
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
