<?php
/**
 * Unified TPD Checkout Modal Partial
 * Handles Membership Plans, LMS Training Courses, and TPD Sales Toolkits/Resources
 * across Test/Sandbox Mode, Stripe Checkout, and PayPal Orders v2.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pay_settings = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_payment_settings() : array();
$pay_mode     = ( isset( $pay_settings['mode'] ) && 'live' === $pay_settings['mode'] ) ? 'live' : 'test';
$pay_symbol   = ! empty( $pay_settings['currency_symbol'] ) ? $pay_settings['currency_symbol'] : '$';
$stripe_ready = ! empty( $pay_settings['stripe_enabled'] ) && ! empty( $pay_settings['stripe_secret_key'] );
$paypal_ready = ! empty( $pay_settings['paypal_enabled'] ) && ! empty( $pay_settings['paypal_client_id'] ) && ! empty( $pay_settings['paypal_secret'] );
?>

<div id="tpd-checkout-modal" style="display:none; position:fixed; inset:0; background:rgba(11,21,38,0.68); z-index:999999; align-items:center; justify-content:center; padding:20px;">
	<div style="background:#ffffff; border-radius:16px; width:100%; max-width:500px; box-shadow:0 24px 60px rgba(0,0,0,0.3); overflow:hidden; font-family:'Plus Jakarta Sans', system-ui, sans-serif;">
		<!-- Header -->
		<div style="background:linear-gradient(135deg, #0b1526 0%, #013243 100%); color:#ffffff; padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
			<div>
				<span style="font-size:10.5px; font-weight:800; letter-spacing:0.08em; color:#fcd34d; text-transform:uppercase;">
					<i class="fa-solid fa-shield-check"></i> <?php echo 'test' === $pay_mode ? esc_html__( 'SANDBOX TEST CHECKOUT', 'tpd-tool' ) : esc_html__( 'SECURE TPD CHECKOUT', 'tpd-tool' ); ?>
				</span>
				<h3 style="margin:4px 0 0; font-size:18px; font-weight:800; color:#ffffff;"><?php esc_html_e( 'Complete Your Order', 'tpd-tool' ); ?></h3>
			</div>
			<button type="button" id="tpd-close-checkout-modal" style="background:rgba(255,255,255,0.12); border:none; color:#ffffff; width:32px; height:32px; border-radius:8px; font-size:18px; cursor:pointer;">&times;</button>
		</div>

		<!-- Body -->
		<div style="padding:24px;">
			<div id="tpd-checkout-loading" style="text-align:center; padding:26px 0; display:none;">
				<i class="fa-solid fa-spinner fa-spin" style="font-size:28px; color:#00798c;"></i>
				<p style="margin:10px 0 0; font-size:13.5px; color:#64748b;"><?php esc_html_e( 'Verifying live pricing...', 'tpd-tool' ); ?></p>
			</div>

			<div id="tpd-checkout-content">
				<!-- Item Summary Card -->
				<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:16px 18px; margin-bottom:18px;">
					<div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
						<div>
							<span id="tpd-co-item-badge" style="display:inline-block; background:#e0f2fe; color:#0369a1; font-size:10.5px; font-weight:800; padding:2px 8px; border-radius:5px; text-transform:uppercase; margin-bottom:6px;">ITEM</span>
							<h4 id="tpd-co-item-title" style="margin:0 0 4px; font-size:16px; font-weight:800; color:#0f172a;">—</h4>
							<p id="tpd-co-item-desc" style="margin:0; font-size:12.5px; color:#64748b; line-height:1.4;">—</p>
						</div>
						<div style="text-align:right; flex-shrink:0;">
							<span style="display:block; font-size:11px; color:#64748b; font-weight:600;"><?php esc_html_e( 'Total', 'tpd-tool' ); ?></span>
							<strong id="tpd-co-item-price" data-symbol="<?php echo esc_attr( $pay_symbol ); ?>" style="font-size:22px; font-weight:800; color:#0b1526;">$0.00</strong>
						</div>
					</div>
				</div>

				<!-- Gateway Options (Hidden when price == 0) -->
				<div id="tpd-co-gateways-wrap" style="margin-bottom:20px;">
					<label style="display:block; font-size:12.5px; font-weight:700; color:#334155; margin-bottom:10px;"><?php esc_html_e( 'Select Payment Method:', 'tpd-tool' ); ?></label>
					<div style="display:flex; flex-direction:column; gap:10px;">
						<?php if ( 'test' === $pay_mode ) : ?>
							<label style="display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid #00798c; background:#f0fdfa; border-radius:10px; cursor:pointer;">
								<input type="radio" name="tpd_co_gateway" value="test" checked>
								<i class="fa-solid fa-flask" style="color:#00798c; font-size:16px;"></i>
								<div style="flex:1;">
									<strong style="font-size:13.5px; color:#0f172a; display:block;"><?php esc_html_e( 'Instant Sandbox Test Payment', 'tpd-tool' ); ?></strong>
									<span style="font-size:11.5px; color:#475569;"><?php esc_html_e( 'Completes checkout immediately in Test Mode for verification.', 'tpd-tool' ); ?></span>
								</div>
							</label>
						<?php endif; ?>

						<?php if ( $stripe_ready ) : ?>
							<label style="display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid #e2e8f0; background:#ffffff; border-radius:10px; cursor:pointer;">
								<input type="radio" name="tpd_co_gateway" value="stripe" <?php checked( 'live' === $pay_mode ); ?>>
								<i class="fa-brands fa-stripe" style="color:#635bff; font-size:22px;"></i>
								<div style="flex:1;">
									<strong style="font-size:13.5px; color:#0f172a; display:block;"><?php esc_html_e( 'Credit / Debit Card (Stripe Checkout)', 'tpd-tool' ); ?></strong>
									<span style="font-size:11.5px; color:#64748b;"><?php esc_html_e( 'Encrypted payment page with automatic return.', 'tpd-tool' ); ?></span>
								</div>
							</label>
						<?php endif; ?>

						<?php if ( $paypal_ready ) : ?>
							<label style="display:flex; align-items:center; gap:10px; padding:12px 14px; border:1.5px solid #e2e8f0; background:#ffffff; border-radius:10px; cursor:pointer;">
								<input type="radio" name="tpd_co_gateway" value="paypal" <?php checked( 'live' === $pay_mode && ! $stripe_ready ); ?>>
								<i class="fa-brands fa-paypal" style="color:#003087; font-size:20px;"></i>
								<div style="flex:1;">
									<strong style="font-size:13.5px; color:#0f172a; display:block;"><?php esc_html_e( 'PayPal Express Checkout', 'tpd-tool' ); ?></strong>
									<span style="font-size:11.5px; color:#64748b;"><?php esc_html_e( 'Pay securely via your PayPal balance or linked bank.', 'tpd-tool' ); ?></span>
								</div>
							</label>
						<?php endif; ?>
					</div>
				</div>

				<div id="tpd-co-status-msg" style="display:none; padding:12px 14px; border-radius:8px; font-size:13px; margin-bottom:14px;"></div>

				<input type="hidden" id="tpd_co_item_type" value="">
				<input type="hidden" id="tpd_co_item_id" value="">
				<input type="hidden" id="tpd_co_billing_cycle" value="once">

				<div style="display:flex; gap:10px; justify-content:flex-end;">
					<button type="button" id="tpd-co-cancel-btn" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'Cancel', 'tpd-tool' ); ?></button>
					<button type="button" id="tpd-co-submit-btn" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" style="background:#00798c; border-color:#00798c; padding:10px 22px;">
						<i class="fa-solid fa-lock"></i> <?php esc_html_e( 'Confirm & Pay', 'tpd-tool' ); ?>
					</button>
				</div>
			</div>

			<!-- Success Post-Purchase View -->
			<div id="tpd-checkout-success" style="display:none; text-align:center; padding:12px 0;">
				<div style="width:56px; height:56px; border-radius:50%; background:#dcfce7; color:#16a34a; display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:12px;">
					<i class="fa-solid fa-check"></i>
				</div>
				<h4 style="margin:0 0 6px; font-size:18px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Order Confirmed!', 'tpd-tool' ); ?></h4>
				<p id="tpd-co-success-text" style="margin:0 0 18px; font-size:13.5px; color:#475569; line-height:1.5;"></p>
				<div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
					<a href="#" id="tpd-co-launch-sso-btn" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" style="display:none; background:#00798c; border-color:#00798c; text-decoration:none;">
						<i class="fa-solid fa-play"></i> <?php esc_html_e( 'Launch Course Now (SSO)', 'tpd-tool' ); ?>
					</a>
					<button type="button" id="tpd-co-done-btn" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'Done', 'tpd-tool' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
