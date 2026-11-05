<?php
/**
 * Public Supplier User / Representative Profile Template
 * 
 * Displays the representative's personal professional profile, credentials,
 * direct contact options (chat, phone, email), and their company listings.
 * 
 * Can be used as a standalone page (/supplier-profile/) or via shortcode:
 * [tpd_supplier_user_profile user_id="123"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_standalone = ! did_action( 'get_header' ) && ! defined( 'TPD_EMBEDDED_PROFILE' );
if ( $is_standalone ) {
	if ( defined( 'TPD_PLUGIN_DIR' ) && file_exists( TPD_PLUGIN_DIR . 'templates/header.php' ) ) {
		include TPD_PLUGIN_DIR . 'templates/header.php';
	} elseif ( locate_template( 'header.php' ) ) {
		get_header();
	}
}

// 1. Resolve Target Supplier User ID
$target_user_id = 0;
if ( ! empty( $user_id ) ) {
	$target_user_id = absint( $user_id );
} elseif ( ! empty( $_GET['user_id'] ) ) {
	$target_user_id = absint( $_GET['user_id'] );
} elseif ( ! empty( $_GET['rep_id'] ) ) {
	$target_user_id = absint( $_GET['rep_id'] );
} elseif ( is_user_logged_in() ) {
	$current_uid = get_current_user_id();
	if ( TPD_Tool_Roles::is_supplier( $current_uid ) || current_user_can( 'manage_options' ) ) {
		$target_user_id = $current_uid;
	}
}

// Fallback: If no user specified, pick the first supplier user associated with a listing
if ( ! $target_user_id ) {
	$sample_listing = get_posts( array(
		'post_type'      => 'supplier_listing',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	) );
	if ( ! empty( $sample_listing ) ) {
		$assigned = (int) get_post_meta( $sample_listing[0]->ID, 'tpd_assigned_user', true );
		if ( $assigned ) {
			$target_user_id = $assigned;
		} else {
			$target_user_id = (int) $sample_listing[0]->post_author;
		}
	}
}

$target_user = $target_user_id ? get_userdata( $target_user_id ) : null;

if ( ! $target_user ) :
?>
	<div class="tpd-container" style="padding:60px 20px; text-align:center;">
		<div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:40px; max-width:600px; margin:0 auto; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
			<i class="fa-solid fa-user-slash" style="font-size:40px; color:#94a3b8; margin-bottom:16px;"></i>
			<h2 style="font-size:22px; font-weight:800; color:#0f172a; margin:0 0 8px;"><?php esc_html_e( 'Supplier Profile Not Found', 'tpd-tool' ); ?></h2>
			<p style="color:#64748b; font-size:14px; margin-bottom:20px;"><?php esc_html_e( 'The requested supplier representative profile does not exist or has been deactivated.', 'tpd-tool' ); ?></p>
			<a href="<?php echo esc_url( home_url( '/suppliers/' ) ); ?>" class="tpd-btn tpd-btn-teal" style="display:inline-flex; align-items:center; gap:8px;">
				<i class="fa-solid fa-building"></i> <?php esc_html_e( 'Browse Supplier Directory', 'tpd-tool' ); ?>
			</a>
		</div>
	</div>
<?php
	if ( $is_standalone ) {
		if ( defined( 'TPD_PLUGIN_DIR' ) && file_exists( TPD_PLUGIN_DIR . 'templates/footer.php' ) ) {
			include TPD_PLUGIN_DIR . 'templates/footer.php';
		} elseif ( locate_template( 'footer.php' ) ) {
			get_footer();
		}
	}
	return;
endif;

// Profile Details
$full_name      = $target_user->display_name ?: trim( $target_user->first_name . ' ' . $target_user->last_name );
$position_title = get_user_meta( $target_user_id, 'tpd_position_title', true ) ?: __( 'Business Development Manager', 'tpd-tool' );
$company_name   = get_user_meta( $target_user_id, 'tpd_company_name', true ) ?: __( 'Verified Travel Supplier', 'tpd-tool' );
$phone          = get_user_meta( $target_user_id, 'tpd_phone', true ) ?: '';
$email          = $target_user->user_email;
$country        = get_user_meta( $target_user_id, 'tpd_country_of_residence', true ) ?: __( 'United States', 'tpd-tool' );
$bio            = $target_user->description ?: get_user_meta( $target_user_id, 'tpd_bio', true );
$tier           = get_user_meta( $target_user_id, 'tpd_user_tier', true ) ?: 'free';
$headshot_url   = get_user_meta( $target_user_id, 'tpd_headshot_url', true ) ?: ( get_user_meta( $target_user_id, 'tpd_avatar_url', true ) ?: get_avatar_url( $target_user_id, array( 'size' => 200 ) ) );
$cover_url      = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80';

// Social links
$linkedin = get_user_meta( $target_user_id, 'tpd_social_linkedin', true );
$twitter  = get_user_meta( $target_user_id, 'tpd_social_twitter', true );
$website  = get_user_meta( $target_user_id, 'tpd_website', true );

// Find linked Supplier Listings
$user_listings = get_posts( array(
	'post_type'      => 'supplier_listing',
	'posts_per_page' => 6,
	'post_status'    => 'publish',
	'meta_query'     => array(
		'relation' => 'OR',
		array( 'key' => 'tpd_assigned_user', 'value' => $target_user_id ),
		array( 'key' => '_tpd_author', 'value' => $target_user_id ),
	),
) );

if ( empty( $user_listings ) ) {
	$user_listings = get_posts( array(
		'post_type'      => 'supplier_listing',
		'author'         => $target_user_id,
		'posts_per_page' => 6,
		'post_status'    => 'publish',
	) );
}

$viewer_id = get_current_user_id();
$is_own_profile = ( $viewer_id === $target_user_id );
?>

<div class="tpd-supplier-user-profile-wrapper">
	<!-- 1. Hero Cover & Header -->
	<div class="tpd-sup-hero" style="background-image: url('<?php echo esc_url( $cover_url ); ?>');">
		<div class="tpd-sup-hero-overlay"></div>
		<div class="tpd-container">
			<div class="tpd-sup-hero-inner">
				<div class="tpd-sup-avatar-wrap">
					<img src="<?php echo esc_url( $headshot_url ); ?>" alt="<?php echo esc_attr( $full_name ); ?>" class="tpd-sup-avatar-img">
					<span class="tpd-sup-online-status" title="<?php esc_attr_e( 'Verified Active BDM', 'tpd-tool' ); ?>"></span>
					<span class="tpd-sup-badge-check" title="<?php esc_attr_e( 'Accredited Partner', 'tpd-tool' ); ?>"><i class="fa-solid fa-check"></i></span>
				</div>

				<div class="tpd-sup-hero-meta">
					<div class="tpd-sup-tier-pill">
						<?php if ( 'enhanced' === $tier ) : ?>
							<span class="tpd-badge-enhanced"><i class="fa-solid fa-crown text-gold"></i> <?php esc_html_e( 'Enhanced Supplier Partner', 'tpd-tool' ); ?></span>
						<?php else : ?>
							<span class="tpd-badge-basic"><i class="fa-solid fa-shield-halved"></i> <?php esc_html_e( 'Verified Supplier Partner', 'tpd-tool' ); ?></span>
						<?php endif; ?>
					</div>
					<h1 class="tpd-sup-name"><?php echo esc_html( $full_name ); ?></h1>
					<p class="tpd-sup-title-company">
						<strong><?php echo esc_html( $position_title ); ?></strong> &bull; <span><?php echo esc_html( $company_name ); ?></span>
					</p>
					<p class="tpd-sup-location">
						<i class="fa-solid fa-location-dot"></i> <?php echo esc_html( $country ); ?>
					</p>
				</div>

				<div class="tpd-sup-hero-actions">
					<?php if ( $is_own_profile ) : ?>
						<a href="<?php echo esc_url( home_url( '/supplier-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-teal">
							<i class="fa-solid fa-user-pen"></i> <?php esc_html_e( 'Edit My Profile', 'tpd-tool' ); ?>
						</a>
					<?php else : ?>
						<button type="button" class="tpd-btn tpd-btn-teal tpd-open-chat-btn" data-recipient-id="<?php echo esc_attr( $target_user_id ); ?>" data-recipient-name="<?php echo esc_attr( $full_name ); ?>">
							<i class="fa-regular fa-comment-dots"></i> <?php esc_html_e( 'Direct BDM Chat', 'tpd-tool' ); ?>
						</button>
						<button type="button" class="tpd-btn tpd-btn-outline-white tpd-open-inquiry-modal" data-supplier-id="<?php echo esc_attr( $target_user_id ); ?>" data-supplier-name="<?php echo esc_attr( $company_name ); ?>">
							<i class="fa-regular fa-calendar-check"></i> <?php esc_html_e( 'Request Discovery Call', 'tpd-tool' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- 2. Main Profile Content -->
	<div class="tpd-container tpd-sup-body-container">
		<div class="tpd-sup-layout">
			<!-- Main Column -->
			<div class="tpd-sup-main-col">
				<!-- About the Representative -->
				<div class="tpd-sup-card mb-4">
					<h2 class="tpd-sup-card-title">
						<i class="fa-regular fa-address-card"></i> <?php esc_html_e( 'About the Representative', 'tpd-tool' ); ?>
					</h2>
					<div class="tpd-sup-bio-content">
						<?php if ( ! empty( $bio ) ) : ?>
							<?php echo wp_kses_post( wpautop( $bio ) ); ?>
						<?php else : ?>
							<p class="text-muted">
								<?php echo esc_html( sprintf( __( '%s is the dedicated Business Development representative for %s on Travel Pro Directory, assisting travel advisors with reservations, group quotes, product training, and dedicated agency support.', 'tpd-tool' ), $full_name, $company_name ) ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="tpd-sup-highlights-strip">
						<div class="tpd-hl-item">
							<i class="fa-solid fa-briefcase"></i>
							<div>
								<strong><?php esc_html_e( 'Role', 'tpd-tool' ); ?></strong>
								<span><?php echo esc_html( $position_title ); ?></span>
							</div>
						</div>
						<div class="tpd-hl-item">
							<i class="fa-solid fa-building"></i>
							<div>
								<strong><?php esc_html_e( 'Organization', 'tpd-tool' ); ?></strong>
								<span><?php echo esc_html( $company_name ); ?></span>
							</div>
						</div>
						<div class="tpd-hl-item">
							<i class="fa-solid fa-earth-americas"></i>
							<div>
								<strong><?php esc_html_e( 'Territory / Base', 'tpd-tool' ); ?></strong>
								<span><?php echo esc_html( $country ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<!-- Company Listings Represented -->
				<div class="tpd-sup-card mb-4">
					<h2 class="tpd-sup-card-title">
						<i class="fa-solid fa-building-flag"></i> <?php esc_html_e( 'Company Listings & Brands Represented', 'tpd-tool' ); ?>
					</h2>
					<p class="tpd-card-desc"><?php esc_html_e( 'Official B2B supplier listing showcases managed by this representative.', 'tpd-tool' ); ?></p>

					<?php if ( ! empty( $user_listings ) ) : ?>
						<div class="tpd-sup-listings-grid">
							<?php foreach ( $user_listings as $listing ) :
								$lid         = $listing->ID;
								$ltitle      = get_the_title( $lid );
								$llogo       = get_post_meta( $lid, 'tpd_logo_url', true ) ?: ( get_the_post_thumbnail_url( $lid, 'medium' ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=300&auto=format&fit=crop&q=80' );
								$lcat        = get_post_meta( $lid, 'supplier_type', true ) ?: __( 'Travel Supplier', 'tpd-tool' );
								$lrewards    = get_post_meta( $lid, 'tpd_agent_rewards', true );
								$lurl        = get_permalink( $lid );
								$lexcerpt    = get_the_excerpt( $lid ) ?: wp_trim_words( $listing->post_content, 18 );
							?>
								<div class="tpd-sup-listing-item">
									<div class="tpd-slist-logo-wrap">
										<img src="<?php echo esc_url( $llogo ); ?>" alt="<?php echo esc_attr( $ltitle ); ?>" class="tpd-slist-logo">
									</div>
									<div class="tpd-slist-body">
										<div class="tpd-slist-meta">
											<span class="tpd-slist-cat"><?php echo esc_html( $lcat ); ?></span>
											<?php if ( 'yes' === strtolower( (string) $lrewards ) || '1' === (string) $lrewards ) : ?>
												<span class="tpd-slist-reward-badge"><i class="fa-solid fa-star"></i> <?php esc_html_e( 'Agent Rewards', 'tpd-tool' ); ?></span>
											<?php endif; ?>
										</div>
										<h3 class="tpd-slist-title"><a href="<?php echo esc_url( $lurl ); ?>"><?php echo esc_html( $ltitle ); ?></a></h3>
										<p class="tpd-slist-desc"><?php echo esc_html( $lexcerpt ); ?></p>
										<div class="tpd-slist-actions">
											<a href="<?php echo esc_url( $lurl ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-teal">
												<i class="fa-solid fa-eye"></i> <?php esc_html_e( 'View Listing Showcase &rarr;', 'tpd-tool' ); ?>
											</a>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="tpd-empty-box" style="padding:24px; background:#f8fafc; border-radius:12px; text-align:center;">
							<p style="color:#64748b; margin:0;"><?php esc_html_e( 'No active public listings assigned to this representative.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Sidebar Column -->
			<div class="tpd-sup-side-col">
				<!-- Direct Contact Desk -->
				<div class="tpd-sup-card mb-4">
					<h3 class="tpd-sup-side-title"><i class="fa-solid fa-address-book"></i> <?php esc_html_e( 'Direct Desk Contact', 'tpd-tool' ); ?></h3>

					<ul class="tpd-sup-contact-list">
						<?php if ( ! empty( $phone ) ) : ?>
							<li>
								<i class="fa-solid fa-phone"></i>
								<div>
									<label><?php esc_html_e( 'Direct Phone', 'tpd-tool' ); ?></label>
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
								</div>
							</li>
						<?php endif; ?>

						<?php if ( ! empty( $email ) ) : ?>
							<li>
								<i class="fa-regular fa-envelope"></i>
								<div>
									<label><?php esc_html_e( 'Direct Email', 'tpd-tool' ); ?></label>
									<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
								</div>
							</li>
						<?php endif; ?>

						<li>
							<i class="fa-solid fa-globe"></i>
							<div>
								<label><?php esc_html_e( 'Location / Territory', 'tpd-tool' ); ?></label>
								<span><?php echo esc_html( $country ); ?></span>
							</div>
						</li>
					</ul>

					<?php if ( ! empty( $linkedin ) || ! empty( $twitter ) || ! empty( $website ) ) : ?>
						<div class="tpd-sup-social-strip">
							<span class="tpd-social-label"><?php esc_html_e( 'Professional Links:', 'tpd-tool' ); ?></span>
							<div class="tpd-social-icons">
								<?php if ( ! empty( $linkedin ) ) : ?>
									<a href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener noreferrer" title="LinkedIn" class="tpd-soc-icon"><i class="fa-brands fa-linkedin-in"></i></a>
								<?php endif; ?>
								<?php if ( ! empty( $twitter ) ) : ?>
									<a href="<?php echo esc_url( $twitter ); ?>" target="_blank" rel="noopener noreferrer" title="Twitter / X" class="tpd-soc-icon"><i class="fa-brands fa-x-twitter"></i></a>
								<?php endif; ?>
								<?php if ( ! empty( $website ) ) : ?>
									<a href="<?php echo esc_url( $website ); ?>" target="_blank" rel="noopener noreferrer" title="Company Website" class="tpd-soc-icon"><i class="fa-solid fa-link"></i></a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- Advisor Security & Guarantee Card -->
				<div class="tpd-sup-card tpd-trust-card">
					<div class="tpd-tc-icon"><i class="fa-solid fa-shield-halved"></i></div>
					<h4><?php esc_html_e( 'Verified Trade Partner', 'tpd-tool' ); ?></h4>
					<p><?php esc_html_e( 'This representative has been verified by Travel Pro Directory. Advisors can communicate safely and negotiate trade commissions directly.', 'tpd-tool' ); ?></p>
					<div class="tpd-tc-badges">
						<span><i class="fa-solid fa-check"></i> Identity Verified</span>
						<span><i class="fa-solid fa-check"></i> B2B Direct Access</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php
if ( $is_standalone ) {
	if ( defined( 'TPD_PLUGIN_DIR' ) && file_exists( TPD_PLUGIN_DIR . 'templates/footer.php' ) ) {
		include TPD_PLUGIN_DIR . 'templates/footer.php';
	} elseif ( locate_template( 'footer.php' ) ) {
		get_footer();
	}
}
