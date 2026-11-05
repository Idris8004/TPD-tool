<?php
/**
 * Supplier Representative Account Info & Self-Serve Profile Editor Template
 * 
 * Accessible at /supplier-account-info/ or via shortcode: [tpd_supplier_account_info]
 * Allows Supplier Representatives and BDMs to update their personal public profile,
 * contact details, avatar, bio, and manage their linked company listings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/supplier-login/?redirect_to=' . urlencode( home_url( '/supplier-account-info/' ) ) ) );
	exit;
}

$current_user_id = get_current_user_id();

// Verify role
if ( ! TPD_Tool_Roles::is_supplier( $current_user_id ) && ! current_user_can( 'manage_options' ) ) {
	wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
	exit;
}

$user       = get_userdata( $current_user_id );
$user_tier  = tpd_get_user_tier( $current_user_id );
$first_name = $user ? $user->first_name : '';
$last_name  = $user ? $user->last_name : '';
$full_name  = ( $user && $user->display_name ) ? $user->display_name : trim( $first_name . ' ' . $last_name );
$email      = $user ? $user->user_email : '';

$position_title   = get_user_meta( $current_user_id, 'tpd_position_title', true ) ?: __( 'Business Development Manager', 'tpd-tool' );
$company_name     = get_user_meta( $current_user_id, 'tpd_company_name', true ) ?: __( 'Travel Supplier Partner', 'tpd-tool' );
$phone            = get_user_meta( $current_user_id, 'tpd_phone', true ) ?: '';
$additional_phone = get_user_meta( $current_user_id, 'tpd_additional_phone', true ) ?: '';
$country          = get_user_meta( $current_user_id, 'tpd_country_of_residence', true ) ?: 'United States';
$bio              = $user ? $user->description : get_user_meta( $current_user_id, 'tpd_bio', true );
$headshot_url     = get_user_meta( $current_user_id, 'tpd_headshot_url', true ) ?: get_user_meta( $current_user_id, 'tpd_avatar_url', true );
$avatar_src       = $headshot_url ?: 'https://ui-avatars.com/api/?name=' . rawurlencode( $full_name ?: 'Partner' ) . '&background=00798c&color=fff&size=200';

$linkedin = get_user_meta( $current_user_id, 'tpd_social_linkedin', true );
$twitter  = get_user_meta( $current_user_id, 'tpd_social_twitter', true );
$website  = get_user_meta( $current_user_id, 'tpd_website', true );

// Linked Company Listings
$my_listings = get_posts( array(
	'post_type'      => 'supplier_listing',
	'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
	'posts_per_page' => -1,
	'meta_query'     => array(
		'relation' => 'OR',
		array( 'key' => 'tpd_assigned_user', 'value' => $current_user_id ),
		array( 'key' => 'post_author', 'value' => $current_user_id ),
	),
) );
if ( empty( $my_listings ) ) {
	$my_listings = get_posts( array(
		'post_type'      => 'supplier_listing',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'author'         => $current_user_id,
	) );
}

$public_rep_url = home_url( '/supplier-profile/?user_id=' . $current_user_id );

$countries = array( 'United States', 'Canada', 'United Kingdom', 'Australia', 'New Zealand', 'France', 'Germany', 'Italy', 'Spain', 'Mexico', 'South Africa', 'United Arab Emirates', 'Singapore', 'Japan', 'Brazil', 'Other' );

$is_standalone = ! did_action( 'get_header' ) && ! defined( 'TPD_EMBEDDED_PROFILE' );
if ( $is_standalone ) {
	if ( defined( 'TPD_PLUGIN_DIR' ) && file_exists( TPD_PLUGIN_DIR . 'templates/header.php' ) ) {
		include TPD_PLUGIN_DIR . 'templates/header.php';
	} elseif ( locate_template( 'header.php' ) ) {
		get_header();
	}
}
?>

<div class="tpd-account-info-container">
	<div class="tpd-container">
		<!-- Top Bar & Breadcrumb -->
		<div class="tpd-acc-topbar">
			<div class="tpd-acc-breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><i class="fa-solid fa-house"></i> <?php esc_html_e( 'Home', 'tpd-tool' ); ?></a>
				<span class="sep">/</span>
				<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>"><?php esc_html_e( 'Supplier Portal', 'tpd-tool' ); ?></a>
				<span class="sep">/</span>
				<span class="current"><?php esc_html_e( 'Representative Profile & Account Info', 'tpd-tool' ); ?></span>
			</div>
			<div class="tpd-acc-top-actions">
				<a href="<?php echo esc_url( $public_rep_url ); ?>" target="_blank" class="tpd-btn tpd-btn-teal tpd-btn-sm">
					<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'View Public Representative Profile', 'tpd-tool' ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-outline tpd-btn-sm">
					<i class="fa-solid fa-sliders"></i> <?php esc_html_e( 'Supplier Dashboard', 'tpd-tool' ); ?>
				</a>
			</div>
		</div>

		<!-- Representative Hero Banner -->
		<div class="tpd-acc-hero-card">
			<div class="tpd-acc-avatar-area">
				<div class="tpd-acc-avatar-wrap">
					<img id="tpd-acc-avatar-img" src="<?php echo esc_url( $avatar_src ); ?>" alt="<?php echo esc_attr( $full_name ); ?>">
					<button type="button" class="tpd-acc-camera-btn" onclick="document.getElementById('tpd-acc-photo-input').click();" title="<?php esc_attr_e( 'Change Profile Photo', 'tpd-tool' ); ?>">
						<i class="fa-solid fa-camera"></i>
					</button>
					<input type="file" id="tpd-acc-photo-input" accept="image/*" style="display:none;">
				</div>
			</div>
			<div class="tpd-acc-hero-info">
				<div class="tpd-acc-pill-row">
					<?php if ( 'enhanced' === $user_tier ) : ?>
						<span class="tpd-tier-badge enhanced"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'Enhanced Supplier Member', 'tpd-tool' ); ?></span>
					<?php else : ?>
						<span class="tpd-tier-badge basic"><i class="fa-solid fa-shield-halved"></i> <?php esc_html_e( 'Verified Supplier Member', 'tpd-tool' ); ?></span>
					<?php endif; ?>
					<span class="tpd-status-pill"><i class="fa-solid fa-circle text-success"></i> <?php esc_html_e( 'Active Trade Partner', 'tpd-tool' ); ?></span>
				</div>
				<h1 class="tpd-acc-name"><?php echo esc_html( $full_name ); ?></h1>
				<p class="tpd-acc-sub">
					<strong><?php echo esc_html( $position_title ); ?></strong> &bull; <span><?php echo esc_html( $company_name ); ?></span> &bull; <span><i class="fa-solid fa-location-dot"></i> <?php echo esc_html( $country ); ?></span>
				</p>
			</div>
		</div>

		<!-- Main Grid -->
		<div class="tpd-acc-grid">
			<!-- Left / Main Form Column -->
			<div class="tpd-acc-main-col">
				<div class="tpd-card-box">
					<div class="tpd-card-box-header">
						<div>
							<h2><i class="fa-solid fa-user-pen"></i> <?php esc_html_e( 'Representative Profile Details', 'tpd-tool' ); ?></h2>
							<p class="text-muted"><?php esc_html_e( 'These details are publicly visible to Travel Advisors on your Representative Profile page and in B2B live chat.', 'tpd-tool' ); ?></p>
						</div>
					</div>

					<form id="tpd-standalone-rep-form" class="tpd-form-body">
						<input type="hidden" name="action" value="tpd_save_supplier_rep_profile">
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">
						<input type="hidden" name="headshot_url" id="tpd_acc_headshot_url" value="<?php echo esc_attr( $headshot_url ); ?>">

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'First Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="first_name" required value="<?php echo esc_attr( $first_name ); ?>" class="tpd-input" placeholder="e.g. David">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Last Name *', 'tpd-tool' ); ?></label>
								<input type="text" name="last_name" required value="<?php echo esc_attr( $last_name ); ?>" class="tpd-input" placeholder="e.g. Chen">
							</div>
						</div>

						<div class="tpd-form-grid-2">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Position / Title *', 'tpd-tool' ); ?></label>
								<input type="text" name="position_title" required value="<?php echo esc_attr( $position_title ); ?>" class="tpd-input" placeholder="e.g. VP of Trade Sales / Regional BDM">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Company / Organization *', 'tpd-tool' ); ?></label>
								<input type="text" name="company_name" required value="<?php echo esc_attr( $company_name ); ?>" class="tpd-input" placeholder="e.g. Direct Ferries">
							</div>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Direct Phone Number *', 'tpd-tool' ); ?></label>
								<input type="tel" name="phone" required value="<?php echo esc_attr( $phone ); ?>" class="tpd-input" placeholder="+1 (555) 000-0000">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Additional Desk Phone', 'tpd-tool' ); ?></label>
								<input type="tel" name="additional_phone" value="<?php echo esc_attr( $additional_phone ); ?>" class="tpd-input" placeholder="e.g. Toll-free or Ext">
							</div>
							<div class="tpd-form-group">
								<label><?php esc_html_e( 'Account Email *', 'tpd-tool' ); ?></label>
								<input type="email" name="email" required value="<?php echo esc_attr( $email ); ?>" class="tpd-input">
							</div>
						</div>

						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Country / Primary Market *', 'tpd-tool' ); ?></label>
							<select name="country" class="tpd-select">
								<?php foreach ( $countries as $c_item ) : ?>
									<option value="<?php echo esc_attr( $c_item ); ?>" <?php selected( $country, $c_item ); ?>><?php echo esc_html( $c_item ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="tpd-form-group">
							<label><?php esc_html_e( 'Representative Bio / Professional Background', 'tpd-tool' ); ?></label>
							<textarea name="bio" rows="4" class="tpd-textarea" placeholder="<?php esc_attr_e( 'Introduce yourself to travel advisors. Outline your industry experience, how you support agencies with reservations, group bookings, VIP FAM trips, or commission questions.', 'tpd-tool' ); ?>"><?php echo esc_textarea( $bio ); ?></textarea>
							<span class="tpd-help-text"><?php esc_html_e( 'Displayed in the "About the Representative" section of your public profile.', 'tpd-tool' ); ?></span>
						</div>

						<div class="tpd-form-divider">
							<span><i class="fa-solid fa-share-nodes"></i> <?php esc_html_e( 'Professional & Social Profiles', 'tpd-tool' ); ?></span>
						</div>

						<div class="tpd-form-grid-3">
							<div class="tpd-form-group">
								<label><i class="fa-brands fa-linkedin text-linkedin"></i> <?php esc_html_e( 'LinkedIn URL', 'tpd-tool' ); ?></label>
								<input type="url" name="linkedin" value="<?php echo esc_attr( $linkedin ); ?>" class="tpd-input" placeholder="https://linkedin.com/in/username">
							</div>
							<div class="tpd-form-group">
								<label><i class="fa-brands fa-x-twitter"></i> <?php esc_html_e( 'Twitter / X URL', 'tpd-tool' ); ?></label>
								<input type="url" name="twitter" value="<?php echo esc_attr( $twitter ); ?>" class="tpd-input" placeholder="https://x.com/handle">
							</div>
							<div class="tpd-form-group">
								<label><i class="fa-solid fa-link"></i> <?php esc_html_e( 'Official Website', 'tpd-tool' ); ?></label>
								<input type="url" name="website" value="<?php echo esc_attr( $website ); ?>" class="tpd-input" placeholder="https://company.com">
							</div>
						</div>

						<div class="tpd-form-footer">
							<button type="submit" id="tpd-save-rep-btn" class="tpd-btn tpd-btn-teal tpd-btn-lg">
								<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Profile Changes', 'tpd-tool' ); ?>
							</button>
							<span id="tpd-rep-save-msg" class="tpd-save-feedback"></span>
						</div>
					</form>
				</div>
			</div>

			<!-- Right Sidebar Column -->
			<div class="tpd-acc-side-col">
				<!-- Linked Company Listings Card -->
				<div class="tpd-card-box mb-4">
					<h3 class="tpd-card-subtitle"><i class="fa-solid fa-building-flag"></i> <?php esc_html_e( 'Your Company Listings', 'tpd-tool' ); ?></h3>
					<p class="text-muted" style="font-size:13px;"><?php esc_html_e( 'These are the company showcases connected to your representative account:', 'tpd-tool' ); ?></p>

					<?php if ( ! empty( $my_listings ) ) : ?>
						<div class="tpd-side-listings-list">
							<?php foreach ( $my_listings as $item ) :
								$item_id   = $item->ID;
								$item_logo = get_post_meta( $item_id, 'tpd_logo_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=100&auto=format&fit=crop&q=80';
								$item_url  = get_permalink( $item_id );
								$status    = get_post_status( $item_id );
							?>
								<div class="tpd-side-listing-card">
									<img src="<?php echo esc_url( $item_logo ); ?>" alt="<?php echo esc_attr( get_the_title( $item_id ) ); ?>" class="tpd-side-list-img">
									<div class="tpd-side-list-info">
										<h4><?php echo esc_html( get_the_title( $item_id ) ); ?></h4>
										<span class="status-badge <?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
										<div class="tpd-side-list-btns">
											<a href="<?php echo esc_url( $item_url ); ?>" target="_blank" class="tpd-btn tpd-btn-xs tpd-btn-outline" title="<?php esc_attr_e( 'View Public Showcase', 'tpd-tool' ); ?>">
												<i class="fa-solid fa-eye"></i> View
											</a>
											<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-teal" title="<?php esc_attr_e( 'Edit in Dashboard', 'tpd-tool' ); ?>">
												<i class="fa-solid fa-pen"></i> Edit
											</a>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="tpd-empty-side-box">
							<p><?php esc_html_e( 'No company listings created yet.', 'tpd-tool' ); ?></p>
							<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-teal">
								<i class="fa-solid fa-plus"></i> <?php esc_html_e( 'Create Listing', 'tpd-tool' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>

				<!-- Membership Status Card -->
				<div class="tpd-card-box tpd-plan-status-card">
					<div class="tpd-psc-header">
						<span class="tpd-psc-icon"><i class="fa-solid fa-crown text-gold"></i></span>
						<div>
							<h4 style="margin:0; font-size:16px; font-weight:700;"><?php esc_html_e( 'Supplier Membership', 'tpd-tool' ); ?></h4>
							<span class="tpd-psc-tier"><?php echo esc_html( strtoupper( $user_tier ) ); ?> <?php esc_html_e( 'TIER', 'tpd-tool' ); ?></span>
						</div>
					</div>
					<ul class="tpd-psc-perks">
						<li><i class="fa-solid fa-check text-teal"></i> <?php esc_html_e( 'Public Representative Profile', 'tpd-tool' ); ?></li>
						<li><i class="fa-solid fa-check text-teal"></i> <?php esc_html_e( 'Direct Advisor Messaging & Chat', 'tpd-tool' ); ?></li>
						<li><i class="fa-solid fa-check text-teal"></i> <?php esc_html_e( 'Discovery Meeting Requests', 'tpd-tool' ); ?></li>
						<?php if ( 'enhanced' === $user_tier ) : ?>
							<li><i class="fa-solid fa-check text-teal"></i> <?php esc_html_e( 'Multi-Tab Showcase (Conferences, Brochures, FAQs, Videos)', 'tpd-tool' ); ?></li>
							<li><i class="fa-solid fa-check text-teal"></i> <?php esc_html_e( 'Priority Placement in Directory', 'tpd-tool' ); ?></li>
						<?php else : ?>
							<li class="disabled"><i class="fa-solid fa-lock"></i> <?php esc_html_e( 'Multi-Tab Showcase (Conferences, Brochures, FAQs)', 'tpd-tool' ); ?></li>
						<?php endif; ?>
					</ul>
					<?php if ( 'enhanced' !== $user_tier ) : ?>
						<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>#tpd-supp-settings" class="tpd-btn tpd-btn-gold tpd-btn-block">
							<i class="fa-solid fa-arrow-up-right-dots"></i> <?php esc_html_e( 'Upgrade to Enhanced Tier', 'tpd-tool' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var photoInput = document.getElementById('tpd-acc-photo-input');
	var avatarImg  = document.getElementById('tpd-acc-avatar-img');
	var hiddenUrl  = document.getElementById('tpd_acc_headshot_url');
	var form       = document.getElementById('tpd-standalone-rep-form');
	var saveBtn    = document.getElementById('tpd-save-rep-btn');
	var msgSpan    = document.getElementById('tpd-rep-save-msg');

	// AJAX Avatar Upload
	if (photoInput) {
		photoInput.addEventListener('change', function(e) {
			if (!this.files || !this.files[0]) return;
			var file = this.files[0];
			var fd = new FormData();
			fd.append('action', 'tpd_upload_media_file');
			fd.append('nonce', '<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>');
			fd.append('file', file);

			if (msgSpan) {
				msgSpan.innerHTML = '<span style="color:#00798c;"><i class="fa-solid fa-spinner fa-spin"></i> Uploading photo...</span>';
			}

			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				body: fd
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success && res.data && res.data.url) {
					if (avatarImg) avatarImg.src = res.data.url;
					if (hiddenUrl) hiddenUrl.value = res.data.url;
					if (msgSpan) {
						msgSpan.innerHTML = '<span style="color:#10b981;"><i class="fa-solid fa-check"></i> Photo updated! Click "Save Profile Changes" below.</span>';
					}
				} else {
					var err = (res.data && res.data.message) ? res.data.message : 'Photo upload failed.';
					if (msgSpan) {
						msgSpan.innerHTML = '<span style="color:#ef4444;"><i class="fa-solid fa-triangle-exclamation"></i> ' + err + '</span>';
					}
				}
			})
			.catch(function(err) {
				if (msgSpan) {
					msgSpan.innerHTML = '<span style="color:#ef4444;">Network error during upload.</span>';
				}
			});
		});
	}

	// Form Submission
	if (form) {
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			if (saveBtn) {
				saveBtn.disabled = true;
				saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
			}
			if (msgSpan) {
				msgSpan.innerHTML = '';
			}

			var fd = new FormData(form);

			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				body: fd
			})
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (saveBtn) {
					saveBtn.disabled = false;
					saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Profile Changes', 'tpd-tool' ); ?>';
				}
				if (res.success) {
					if (msgSpan) {
						msgSpan.innerHTML = '<span style="color:#10b981; font-weight:600;"><i class="fa-solid fa-circle-check"></i> ' + (res.data && res.data.message ? res.data.message : 'Saved successfully!') + '</span>';
					}
				} else {
					var err = (res.data && res.data.message) ? res.data.message : 'Could not save profile.';
					if (msgSpan) {
						msgSpan.innerHTML = '<span style="color:#ef4444; font-weight:600;"><i class="fa-solid fa-triangle-exclamation"></i> ' + err + '</span>';
					}
				}
			})
			.catch(function() {
				if (saveBtn) {
					saveBtn.disabled = false;
					saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> <?php esc_html_e( 'Save Profile Changes', 'tpd-tool' ); ?>';
				}
				if (msgSpan) {
					msgSpan.innerHTML = '<span style="color:#ef4444; font-weight:600;">Server communication error.</span>';
				}
			});
		});
	}
});
</script>

<?php
if ( $is_standalone ) {
	if ( defined( 'TPD_PLUGIN_DIR' ) && file_exists( TPD_PLUGIN_DIR . 'templates/footer.php' ) ) {
		include TPD_PLUGIN_DIR . 'templates/footer.php';
	} elseif ( locate_template( 'footer.php' ) ) {
		get_footer();
	}
}
