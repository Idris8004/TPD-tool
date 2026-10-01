<?php
/**
 * Travel Advisor (Pro User) Registration Form Template
 * Updated with exact specifications from Client Document
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	?>
	<div class="tpd-reg-already-logged-in">
		<i class="fa-solid fa-circle-check text-green"></i>
		<h3><?php printf( esc_html__( 'You are already logged in as %s', 'tpd-tool' ), esc_html( $current_user->display_name ) ); ?></h3>
		<p><?php esc_html_e( 'Access your Advisor Dashboard to manage your profile, view leads, and search suppliers.', 'tpd-tool' ); ?></p>
		<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-darkblue">
			<i class="fa-solid fa-gauge-high"></i> <?php esc_html_e( 'Go to Advisor Dashboard', 'tpd-tool' ); ?>
		</a>
	</div>
	<?php
	return;
}
?>
<div class="tpd-registration-wrapper">
	<div class="tpd-reg-card">
		<!-- Header -->
		<div class="tpd-reg-header">
			<div class="tpd-reg-badge"><i class="fa-solid fa-compass"></i> TRAVEL PARTNER DIRECTORY BY TARC</div>
			<h2><?php esc_html_e( 'Join as a Verified Travel Advisor', 'tpd-tool' ); ?></h2>
			<p><?php esc_html_e( 'Get discovered by high-intent travelers, connect directly with supplier BDMs, and grow your high-yield bookings.', 'tpd-tool' ); ?></p>
		</div>

		<!-- Registration Form -->
		<form id="tpd-advisor-registration-form" class="tpd-reg-form" method="post">
			<input type="hidden" name="action" value="tpd_register_advisor">
			<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

			<!-- 1. Account & Personal Details -->
			<div class="tpd-form-section">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-user-shield"></i> <?php esc_html_e( '1. Account & Personal Details', 'tpd-tool' ); ?></h3>
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="first_name" required placeholder="e.g. Steven" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="last_name" required placeholder="e.g. Gould" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Work Email Address *', 'tpd-tool' ); ?></label>
						<input type="email" name="email" required placeholder="steven@gouldtravel.com" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Phone Number *', 'tpd-tool' ); ?></label>
						<div class="tpd-phone-input-wrap" style="display:flex; gap:8px;">
							<select name="phone_country_code" class="tpd-select tpd-select-sm" style="max-width: 105px;">
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

				<div class="tpd-form-group">
					<label><?php esc_html_e( 'Advisor Location *', 'tpd-tool' ); ?></label>
					<input type="text" name="location" id="tpd_advisor_location_input" required placeholder="Enter city, state/province, and country" class="tpd-input">
					<small class="text-muted" style="font-size:12px; color:#64748b; margin-top:4px;">
						<i class="fa-solid fa-circle-info"></i> <?php esc_html_e( 'Note: For location services only, will not be displayed publicly.', 'tpd-tool' ); ?>
					</small>
				</div>
			</div>

			<!-- 2. Agency Details & Industry Credentials -->
			<div class="tpd-form-section">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-award"></i> <?php esc_html_e( '2. Agency Details & Industry Credentials', 'tpd-tool' ); ?></h3>
				
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Agency Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="agency_name" required placeholder="e.g. Gould Travel Group" class="tpd-input">
					</div>
					<div class="tpd-form-group">
						<div style="display:flex; justify-content:space-between; align-items:center;">
							<label><?php esc_html_e( 'Agency Address *', 'tpd-tool' ); ?></label>
							<button type="button" id="tpd-btn-same-address" class="tpd-btn-link" style="font-size:12px; color:#2563eb; background:none; border:none; cursor:pointer; text-decoration:underline;">
								<i class="fa-regular fa-copy"></i> <?php esc_html_e( 'Same address as advisor', 'tpd-tool' ); ?>
							</button>
						</div>
						<input type="text" name="agency_address" id="tpd_agency_address_input" required placeholder="Street, Suite, City, State, ZIP" class="tpd-input">
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Your Role *', 'tpd-tool' ); ?></label>
						<select name="business_structure" class="tpd-select" required>
							<option value="Agency Owner">Agency Owner</option>
							<option value="Travel Advisor - Independent Contractor" selected>Travel Advisor - Independent Contractor (IC)</option>
							<option value="Travel Advisor - Employee">Travel Advisor - Employee</option>
							<option value="Agency Manager/Leader">Agency Manager / Leader</option>
							<option value="Other">Other</option>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Agency Structure *', 'tpd-tool' ); ?></label>
						<select name="agency_structure" class="tpd-select" required>
							<option value="Independent Agency (no host)">Independent Agency (no host)</option>
							<option value="Host Agency">Host Agency</option>
							<option value="Hosted Agency" selected>Hosted Agency</option>
							<option value="Franchise">Franchise (e.g. Dream Vacations, Cruise Planners)</option>
							<option value="Other">Other</option>
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

				<!-- Sales Volumes (Personal vs Agency) -->
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'PERSONAL Estimated Annual Sales Volume *', 'tpd-tool' ); ?></label>
						<select name="sales_volume" class="tpd-select" required>
							<option value="$0-$250,000">$0 - $250,000</option>
							<option value="$250,000-$500,000">$250,000 - $500,000</option>
							<option value="$500,000-$750,000" selected>$500,000 - $750,000</option>
							<option value="$750,000-$1M">$750,000 - $1,000,000</option>
							<option value="$1M-$1.5M">$1,000,000 - $1,500,000</option>
							<option value="$1.5M-$2M">$1,500,000 - $2,000,000</option>
							<option value="$2M-$5M">$2,000,000 - $5,000,000</option>
							<option value="$5M-$10M">$5,000,000 - $10,000,000</option>
							<option value="$10M+">$10,000,000+</option>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'AGENCY Estimated Annual Sales Volume *', 'tpd-tool' ); ?></label>
						<select name="agency_sales_volume" class="tpd-select" required>
							<option value="$0-$250,000">$0 - $250,000</option>
							<option value="$250,000-$500,000">$250,000 - $500,000</option>
							<option value="$500,000-$1M" selected>$500,000 - $1,000,000</option>
							<option value="$1M-$3M">$1,000,000 - $3,000,000</option>
							<option value="$3M-$5M">$3,000,000 - $5,000,000</option>
							<option value="$5M-$10M">$5,000,000 - $10,000,000</option>
							<option value="$10M-$15M">$10,000,000 - $15,000,000</option>
							<option value="$15M-$20M">$15,000,000 - $20,000,000</option>
							<option value="$20M+">$20,000,000+</option>
						</select>
					</div>
				</div>

				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Consortia (if affiliated)', 'tpd-tool' ); ?></label>
						<select name="consortia" class="tpd-select">
							<option value="">None / Independent</option>
							<option value="Virtuoso">Virtuoso</option>
							<option value="Signature Travel Network">Signature Travel Network</option>
							<option value="Travel Leaders Network">Travel Leaders Network</option>
							<option value="Ensemble Travel Group">Ensemble Travel Group</option>
							<option value="Other Consortia">Other Consortia</option>
						</select>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Host Agency / Franchise (if affiliated)', 'tpd-tool' ); ?></label>
						<select name="host_agency" class="tpd-select">
							<option value="">None / Direct</option>
							<option value="Avoya Travel">Avoya Travel</option>
							<option value="Travel Planners International (TPI)">Travel Planners International (TPI)</option>
							<option value="OASIS Travel Network">OASIS Travel Network</option>
							<option value="KHM Travel Group">KHM Travel Group</option>
							<option value="Nexion Travel Group">Nexion Travel Group</option>
							<option value="Cruise Planners">Cruise Planners</option>
							<option value="Dream Vacations">Dream Vacations</option>
							<option value="InteleTravel">InteleTravel</option>
							<option value="Other Host">Other Host</option>
						</select>
					</div>
				</div>

				<!-- Industry Accreditation Checkboxes + Numbers -->
				<div class="tpd-form-group mt-3">
					<label class="mb-2 block font-bold"><?php esc_html_e( 'Industry Accreditation Type & Number *', 'tpd-tool' ); ?></label>
					<p class="text-muted" style="font-size:12.5px; margin-top:-4px; margin-bottom:10px;">Select your credentials and enter your active verification ID:</p>
					
					<div class="tpd-accreditations-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
						<div class="tpd-acc-box" style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_clia" value="1" checked> CLIA #
							</label>
							<input type="text" name="clia_num" placeholder="Enter CLIA ID" class="tpd-input tpd-input-sm" style="font-size:12.5px;">
						</div>

						<div class="tpd-acc-box" style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_iata" value="1"> IATA / IATAN #
							</label>
							<input type="text" name="iata_num" placeholder="Enter IATA ID" class="tpd-input tpd-input-sm" style="font-size:12.5px;">
						</div>

						<div class="tpd-acc-box" style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_arc" value="1"> ARC #
							</label>
							<input type="text" name="arc_num" placeholder="Enter ARC ID" class="tpd-input tpd-input-sm" style="font-size:12.5px;">
						</div>

						<div class="tpd-acc-box" style="border:1px solid #e2e8f0; border-radius:10px; padding:12px; background:#f8fafc;">
							<label style="display:flex; align-items:center; gap:8px; font-weight:700; margin-bottom:6px;">
								<input type="checkbox" name="has_true" value="1"> TRUE #
							</label>
							<input type="text" name="true_num" placeholder="Enter TRUE ID" class="tpd-input tpd-input-sm" style="font-size:12.5px;">
						</div>
					</div>
				</div>
			</div>

			<!-- 3. Account Creation & Security -->
			<div class="tpd-form-section">
				<h3 class="tpd-fs-title"><i class="fa-solid fa-key"></i> <?php esc_html_e( '3. Account Login Details', 'tpd-tool' ); ?></h3>
				<div class="tpd-form-grid-2">
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Choose Username *', 'tpd-tool' ); ?></label>
						<input type="text" name="username" id="tpd_advisor_username" required placeholder="e.g. stevengould" class="tpd-input">
						<small id="tpd-username-notice" style="display:none; font-size:11.5px;"></small>
					</div>
					<div class="tpd-form-group">
						<label><?php esc_html_e( 'Password *', 'tpd-tool' ); ?></label>
						<div style="position:relative;">
							<input type="password" name="password" id="tpd_advisor_password" required placeholder="Min 8 chars (1 capital, 1 lowercase, 1 number)" class="tpd-input" style="padding-right:40px;">
							<button type="button" class="tpd-pwd-toggle-btn" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
								<i class="fa-solid fa-eye"></i>
							</button>
						</div>
						<div style="display:flex; justify-content:space-between; margin-top:4px;">
							<small class="text-muted" style="font-size:11px;">Must be at least 8 chars with 1 number, 1 capital, 1 lowercase</small>
							<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" target="_blank" style="font-size:11px; color:#2563eb;">Forgot password?</a>
						</div>
					</div>
				</div>

				<!-- Plan Selection (Basic, Standard, Premium) -->
				<div class="tpd-form-group mt-3">
					<label class="mb-2 block font-bold"><?php esc_html_e( 'Select Your Membership Plan:', 'tpd-tool' ); ?></label>
					<div class="tpd-tier-selection-grid" style="grid-template-columns: repeat(3, 1fr); gap:14px;">
						<!-- Basic Plan (Free) -->
						<label class="tpd-tier-option">
							<input type="radio" name="tier" value="basic" checked>
							<div class="tpd-to-content">
								<span class="tpd-to-tag">Standard Access</span>
								<h4>Basic Plan</h4>
								<span class="tpd-to-price">$0 <span>/ Free</span></span>
								<ul class="tpd-to-features">
									<li><i class="fa-solid fa-check text-green"></i> TARC Dashboard</li>
									<li><i class="fa-solid fa-check text-green"></i> Supplier Directory & Favorites</li>
									<li><i class="fa-solid fa-check text-green"></i> Chat with Suppliers</li>
									<li><i class="fa-solid fa-check text-green"></i> Basic Advisor Profile (5 specialties)</li>
								</ul>
							</div>
						</label>

						<!-- Standard Plan ($9.99/mo) -->
						<label class="tpd-tier-option">
							<input type="radio" name="tier" value="standard">
							<div class="tpd-to-content">
								<span class="tpd-to-tag" style="color:#2563eb;">Enhanced</span>
								<h4>Standard Plan</h4>
								<span class="tpd-to-price">$9.99 <span>/ mo ($99/yr)</span></span>
								<ul class="tpd-to-features">
									<li><i class="fa-solid fa-check text-green"></i> Everything in Basic</li>
									<li><i class="fa-solid fa-check text-blue"></i> Cruise Comparison Platform</li>
									<li><i class="fa-solid fa-check text-blue"></i> Discovery Call Platform</li>
									<li><i class="fa-solid fa-check text-blue"></i> Custom Banner, Travel Blog & FAQs</li>
								</ul>
							</div>
						</label>

						<!-- Premium Plan ($19.99/mo) -->
						<label class="tpd-tier-option tpd-tier-featured">
							<input type="radio" name="tier" value="premium">
							<div class="tpd-to-content">
								<span class="tpd-to-tag tpd-to-gold"><i class="fa-solid fa-crown"></i> Recommended</span>
								<h4>Premium Plan</h4>
								<span class="tpd-to-price">$19.99 <span>/ mo ($179/yr)</span></span>
								<ul class="tpd-to-features">
									<li><i class="fa-solid fa-check text-green"></i> Everything in Standard</li>
									<li><i class="fa-solid fa-check text-gold"></i> 10 Travel Specialties each</li>
									<li><i class="fa-solid fa-check text-gold"></i> Custom Inquiry Link & Photo Albums</li>
									<li><i class="fa-solid fa-check text-gold"></i> Dispute Reviews & Priority Visibility</li>
								</ul>
							</div>
						</label>
					</div>
				</div>

				<!-- Verification Notice -->
				<div class="tpd-notice-box" style="margin-top:16px; padding:12px 16px; background:#eff6ff; border-left:4px solid #2563eb; border-radius:6px; font-size:12.5px; color:#1e40af;">
					<i class="fa-solid fa-shield-halved"></i> <strong>Instant Fast Access:</strong> Profile listings go live immediately once credentials and required fields are completed. Detailed photos, logos, and travel specialties are configured inside your Advisor Dashboard after sign-up!
				</div>
			</div>

			<!-- Status Messages -->
			<div class="tpd-reg-status" style="display:none;"></div>

			<!-- Submit Button -->
			<div class="tpd-reg-submit-wrap">
				<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-btn-lg" id="tpd-advisor-reg-btn">
					<i class="fa-solid fa-arrow-right-to-bracket"></i> <?php esc_html_e( 'Complete Registration & Open Dashboard', 'tpd-tool' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
