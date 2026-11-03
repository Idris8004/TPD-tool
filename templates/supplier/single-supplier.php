<?php
/**
 * Single Supplier Listing & Showcase Template (Unified Membership Structure)
 * 
 * Basic/Free Listing:
 * - Displays basic company information (Name, Tagline, Phone, Booking URL, Headquarters)
 * - Displays About Us / Company Overview
 * - Paid tabs/sections remain hidden on public view.
 * 
 * Enhanced/Paid Listing:
 * - Displays full interactive showcase with separate tabs:
 *   1. About (Company Story, Highlights, Desk Contacts)
 *   2. Associations & Conferences (USTOA, ASTA, CLIA, Consortia, Upcoming Conferences Attended, Rewards)
 *   3. Resources & Brochures (Downloadable PDFs, Sales Guides, Deck Plans)
 *   4. FAQs (Interactive FAQ Accordion for Travel Advisors)
 *   5. Photo Gallery (High-Resolution Multi-Image Grid with Lightbox)
 *   6. Videos (Featured Video Player & Webinar Showcase)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_standalone_template = ! did_action( 'get_header' ) && ! defined( 'TPD_EMBEDDED_PROFILE' );
if ( $is_standalone_template ) {
	get_header();
}

$post_id = get_the_ID();

// Core Brand Identity & Basic Fields
$company_name = get_the_title( $post_id );
$tagline      = get_post_meta( $post_id, 'tpd_tagline', true ) ?: '';
$phone        = get_post_meta( $post_id, 'tpd_primary_rep_phone', true ) ?: '';
$booking_url  = get_post_meta( $post_id, 'tpd_booking_portal_url', true ) ?: '';
$headquarters = get_post_meta( $post_id, 'tpd_headquarters', true ) ?: 'Global';
$country      = get_post_meta( $post_id, 'tpd_country_of_residence', true ) ?: '';
$description  = get_post_field( 'post_content', $post_id );
$logo_url     = get_post_meta( $post_id, 'tpd_logo_url', true ) ?: '';
$banner_url   = get_post_meta( $post_id, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80';

// Membership Level Check
$is_enhanced  = function_exists( 'tpd_is_listing_enhanced' ) ? tpd_is_listing_enhanced( $post_id ) : false;

// Enhanced Listing Fields (Loaded only if paid)
$ustoa        = get_post_meta( $post_id, 'tpd_member_ustoa', true ) ?: 'No';
$asta         = get_post_meta( $post_id, 'tpd_member_asta', true ) ?: 'No';
$clia         = get_post_meta( $post_id, 'tpd_member_clia', true ) ?: 'No';
$associations = get_post_meta( $post_id, 'tpd_associations', true ) ?: '';
$rewards      = get_post_meta( $post_id, 'tpd_agent_rewards', true ) ?: 'No';
$promo_title  = get_post_meta( $post_id, 'tpd_promo_title', true ) ?: '';
$promo_desc   = get_post_meta( $post_id, 'tpd_promo_desc', true ) ?: get_post_meta( $post_id, 'tpd_agent_rewards_desc', true ) ?: '';
$conferences  = get_post_meta( $post_id, '_tpd_supplier_conferences', true );
$conferences  = is_array( $conferences ) ? array_filter( $conferences ) : array();
$resources    = get_post_meta( $post_id, '_tpd_supplier_resources', true );
$resources    = is_array( $resources ) ? array_filter( $resources ) : array();
$faqs         = get_post_meta( $post_id, '_tpd_supplier_faqs', true );
$faqs         = is_array( $faqs ) ? array_filter( $faqs ) : array();
$raw_gallery  = get_post_meta( $post_id, 'tpd_gallery_images', true );
$gallery      = is_array( $raw_gallery ) ? $raw_gallery : ( ! empty( $raw_gallery ) ? json_decode( $raw_gallery, true ) : array() );
$gallery      = is_array( $gallery ) ? array_values( array_filter( $gallery ) ) : array();
$video_url    = get_post_meta( $post_id, 'tpd_main_video_url', true ) ?: '';
$videos       = get_post_meta( $post_id, '_tpd_supplier_videos', true );
$videos       = is_array( $videos ) ? array_filter( $videos ) : array();

// Helper to convert YouTube / Vimeo URLs to embeds
function tpd_get_video_embed_url( $url ) {
	if ( empty( $url ) ) return '';
	if ( preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i', $url, $matches ) ) {
		return 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1';
	}
	if ( preg_match( '/vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|video\/|)(\d+)/i', $url, $matches ) ) {
		return 'https://player.vimeo.com/video/' . $matches[3];
	}
	return $url;
}
$embed_main_video = tpd_get_video_embed_url( $video_url );

// Track listing view
if ( class_exists( 'TPD_Tool_Analytics' ) ) {
	$views = (int) get_post_meta( $post_id, 'tpd_listing_views', true );
	update_post_meta( $post_id, 'tpd_listing_views', $views + 1 );
}
?>

<div class="tpd-showcase-public-wrapper" style="background:#f8fafc; min-height:100vh; padding-bottom:60px; font-family:'Plus Jakarta Sans', sans-serif; color:#0f172a;">
	
	<!-- 1. HERO COVER BANNER -->
	<div class="tpd-showcase-hero" style="position:relative; width:100%; height:320px; background-image:url('<?php echo esc_url( $banner_url ); ?>'); background-size:cover; background-position:center; background-color:#013243;">
		<div style="position:absolute; inset:0; background:linear-gradient(180deg, rgba(1,50,67,0.3) 0%, rgba(1,50,67,0.85) 100%);"></div>
		
		<div style="max-width:1200px; margin:0 auto; height:100%; position:relative; padding:0 24px; display:flex; align-items:flex-end;">
			<div class="tpd-showcase-hero-content" style="display:flex; align-items:flex-end; gap:24px; width:100%; margin-bottom:-40px; z-index:10; flex-wrap:wrap;">
				<!-- Brand Logo Card -->
				<div class="tpd-showcase-logo-box" style="width:130px; height:130px; border-radius:16px; background:#ffffff; box-shadow:0 10px 25px rgba(0,0,0,0.15); border:3px solid #ffffff; overflow:hidden; display:flex; align-items:center; justify-content:center; padding:10px; flex-shrink:0;">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $company_name ); ?>" style="max-width:100%; max-height:100%; object-fit:contain;">
					<?php else : ?>
						<div style="text-align:center; color:#94a3b8; font-size:36px;"><i class="fa-solid fa-building"></i></div>
					<?php endif; ?>
				</div>

				<!-- Title, Tagline & Meta Pills -->
				<div style="flex:1; min-width:280px; padding-bottom:12px;">
					<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
						<h1 style="margin:0; font-size:28px; font-weight:800; color:#ffffff; text-shadow:0 2px 4px rgba(0,0,0,0.4);"><?php echo esc_html( $company_name ); ?></h1>
						<?php if ( $is_enhanced ) : ?>
							<span style="background:linear-gradient(135deg, #eab308 0%, #ca8a04 100%); color:#0f172a; font-size:11px; font-weight:800; padding:4px 10px; border-radius:999px; text-transform:uppercase; letter-spacing:0.5px; box-shadow:0 2px 8px rgba(234,179,8,0.3);">
								<i class="fa-solid fa-crown"></i> <?php esc_html_e( 'Enhanced Partner', 'tpd-tool' ); ?>
							</span>
						<?php else : ?>
							<span style="background:rgba(255,255,255,0.2); backdrop-filter:blur(4px); color:#ffffff; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; text-transform:uppercase;">
								<?php esc_html_e( 'Verified Supplier', 'tpd-tool' ); ?>
							</span>
						<?php endif; ?>
					</div>

					<?php if ( $tagline ) : ?>
						<p style="margin:0 0 10px; font-size:15px; color:#e2e8f0; text-shadow:0 1px 3px rgba(0,0,0,0.5);"><?php echo esc_html( $tagline ); ?></p>
					<?php endif; ?>

					<div style="display:flex; gap:14px; flex-wrap:wrap; font-size:13px; color:#cbd5e1;">
						<span><i class="fa-solid fa-location-dot text-gold"></i> <?php echo esc_html( $headquarters ); ?></span>
						<?php if ( $country && $country !== $headquarters ) : ?>
							<span><i class="fa-solid fa-earth-americas text-blue"></i> <?php echo esc_html( $country ); ?></span>
						<?php endif; ?>
						<?php if ( $phone ) : ?>
							<span><i class="fa-solid fa-phone text-green"></i> <?php echo esc_html( $phone ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<!-- Quick CTAs -->
				<div style="display:flex; gap:10px; padding-bottom:12px; flex-wrap:wrap;">
					<?php if ( $booking_url ) : ?>
						<a href="<?php echo esc_url( $booking_url ); ?>" target="_blank" rel="noopener noreferrer" class="tpd-btn tpd-btn-sm" style="background:#00798c; color:#ffffff; font-weight:700; border:none; padding:10px 18px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; box-shadow:0 4px 12px rgba(0,121,140,0.3);">
							<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Advisor Booking Portal', 'tpd-tool' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $phone ) : ?>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" class="tpd-btn tpd-btn-sm" style="background:#ffffff; color:#0f172a; font-weight:700; border:1px solid #cbd5e1; padding:10px 16px; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
							<i class="fa-solid fa-phone-volume text-blue"></i> <?php esc_html_e( 'Call Support', 'tpd-tool' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<!-- 2. MAIN SHOWCASE CONTAINER -->
	<div style="max-width:1200px; margin:60px auto 0; padding:0 24px;">

		<?php if ( ! $is_enhanced ) : ?>
			<!-- ========================================================== -->
			<!-- BASIC / FREE LISTING VIEW                                  -->
			<!-- Company Profile, About Us, Key Details, and Advisor Portal -->
			<!-- ========================================================== -->
			<div class="tpd-basic-showcase-grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:28px;">
				<!-- Left Column: About Us -->
				<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 16px; font-size:18px; font-weight:800; color:#0f172a; border-bottom:2px solid #f1f5f9; padding-bottom:12px;">
						<i class="fa-solid fa-circle-info text-blue"></i> <?php printf( esc_html__( 'About %s', 'tpd-tool' ), esc_html( $company_name ) ); ?>
					</h3>
					
					<?php if ( $description ) : ?>
						<div class="tpd-showcase-body-text" style="font-size:14.5px; line-height:1.75; color:#334155;">
							<?php echo wpautop( $description ); ?>
						</div>
					<?php else : ?>
						<p style="color:#64748b; font-size:14px;"><?php esc_html_e( 'Welcome to our partner directory profile. Travel advisors can book and access live support through our dedicated trade desk.', 'tpd-tool' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Right Column: Quick Company Information -->
				<div>
					<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.03); margin-bottom:24px;">
						<h4 style="margin:0 0 16px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-address-card text-blue"></i> <?php esc_html_e( 'Company Information', 'tpd-tool' ); ?></h4>
						
						<div style="display:flex; flex-direction:column; gap:14px; font-size:13.5px;">
							<div>
								<span style="display:block; color:#64748b; font-size:11.5px; font-weight:700; text-transform:uppercase;"><?php esc_html_e( 'Headquarters', 'tpd-tool' ); ?></span>
								<strong style="color:#0f172a;"><?php echo esc_html( $headquarters ); ?></strong>
							</div>
							<?php if ( $phone ) : ?>
								<div>
									<span style="display:block; color:#64748b; font-size:11.5px; font-weight:700; text-transform:uppercase;"><?php esc_html_e( 'Advisor Support Phone', 'tpd-tool' ); ?></span>
									<strong style="color:#0f172a;"><?php echo esc_html( $phone ); ?></strong>
								</div>
							<?php endif; ?>
							<?php if ( $booking_url ) : ?>
								<div>
									<span style="display:block; color:#64748b; font-size:11.5px; font-weight:700; text-transform:uppercase;"><?php esc_html_e( 'Booking Portal', 'tpd-tool' ); ?></span>
									<a href="<?php echo esc_url( $booking_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#00798c; font-weight:700; word-break:break-all; text-decoration:none;">
										<?php echo esc_html( $booking_url ); ?> &rarr;
									</a>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="tpd-card-box" style="background:linear-gradient(135deg, #013243 0%, #00798c 100%); color:#ffffff; border-radius:14px; padding:24px;">
						<h4 style="margin:0 0 8px; font-size:15px; font-weight:800; color:#ffffff;"><i class="fa-solid fa-handshake text-gold"></i> <?php esc_html_e( 'Are you a Travel Advisor?', 'tpd-tool' ); ?></h4>
						<p style="margin:0 0 16px; font-size:13px; color:#e2e8f0; line-height:1.6;"><?php esc_html_e( 'Register or log in to message this supplier, request dedicated itineraries, and access exclusive trade incentives.', 'tpd-tool' ); ?></p>
						<a href="<?php echo esc_url( home_url( '/advisor-registration/' ) ); ?>" class="tpd-btn tpd-btn-sm tpd-btn-gold" style="background:#eab308; color:#0f172a; font-weight:800; border:none; padding:10px 18px; border-radius:8px; text-decoration:none; display:inline-block;">
							<?php esc_html_e( 'Join Travel Advisor Directory', 'tpd-tool' ); ?> &rarr;
						</a>
					</div>
				</div>
			</div>

		<?php else : ?>
			<!-- ========================================================== -->
			<!-- ENHANCED / PAID LISTING VIEW                               -->
			<!-- Organized into Separate Tabs:                              -->
			<!-- About, Associations & Conferences, Resources & Brochures,  -->
			<!-- FAQs, Photo Gallery, Videos                                -->
			<!-- ========================================================== -->

			<!-- Showcase Navigation Tabs Bar -->
			<div class="tpd-showcase-tabs-nav" style="display:flex; gap:10px; border-bottom:2px solid #e2e8f0; margin-bottom:28px; overflow-x:auto;">
				<button type="button" class="tpd-pub-tab active" data-tab="pub-about" style="padding:12px 20px; border:none; background:none; font-weight:700; font-size:14.5px; color:#0f172a; border-bottom:3px solid #00798c; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-info-circle text-blue"></i> <?php esc_html_e( 'About', 'tpd-tool' ); ?>
				</button>
				<button type="button" class="tpd-pub-tab" data-tab="pub-associations" style="padding:12px 20px; border:none; background:none; font-weight:600; font-size:14.5px; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-award text-gold"></i> <?php esc_html_e( 'Associations & Conferences', 'tpd-tool' ); ?>
				</button>
				<button type="button" class="tpd-pub-tab" data-tab="pub-resources" style="padding:12px 20px; border:none; background:none; font-weight:600; font-size:14.5px; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-folder-open text-purple"></i> <?php esc_html_e( 'Resources & Brochures', 'tpd-tool' ); ?>
					<?php if ( ! empty( $resources ) ) : ?><span style="background:#e0e7ff; color:#4338ca; font-size:11px; padding:2px 7px; border-radius:999px;"><?php echo count( $resources ); ?></span><?php endif; ?>
				</button>
				<button type="button" class="tpd-pub-tab" data-tab="pub-faqs" style="padding:12px 20px; border:none; background:none; font-weight:600; font-size:14.5px; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-circle-question text-teal"></i> <?php esc_html_e( 'FAQs', 'tpd-tool' ); ?>
					<?php if ( ! empty( $faqs ) ) : ?><span style="background:#ccfbf1; color:#0f766e; font-size:11px; padding:2px 7px; border-radius:999px;"><?php echo count( $faqs ); ?></span><?php endif; ?>
				</button>
				<button type="button" class="tpd-pub-tab" data-tab="pub-gallery" style="padding:12px 20px; border:none; background:none; font-weight:600; font-size:14.5px; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-images text-blue"></i> <?php esc_html_e( 'Photo Gallery', 'tpd-tool' ); ?>
					<?php if ( ! empty( $gallery ) ) : ?><span style="background:#e0f2fe; color:#0284c7; font-size:11px; padding:2px 7px; border-radius:999px;"><?php echo count( $gallery ); ?></span><?php endif; ?>
				</button>
				<button type="button" class="tpd-pub-tab" data-tab="pub-videos" style="padding:12px 20px; border:none; background:none; font-weight:600; font-size:14.5px; color:#64748b; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-video text-red"></i> <?php esc_html_e( 'Videos', 'tpd-tool' ); ?>
				</button>
			</div>

			<!-- TAB 1: ABOUT -->
			<div id="pub-about" class="tpd-pub-panel" style="display:block;">
				<div style="display:grid; grid-template-columns: 2fr 1fr; gap:28px;">
					<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:32px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
						<h3 style="margin:0 0 16px; font-size:19px; font-weight:800; color:#0f172a; border-bottom:2px solid #f1f5f9; padding-bottom:12px;">
							<i class="fa-solid fa-book-open text-blue"></i> <?php printf( esc_html__( 'About %s', 'tpd-tool' ), esc_html( $company_name ) ); ?>
						</h3>
						<?php if ( $description ) : ?>
							<div class="tpd-showcase-body-text" style="font-size:14.5px; line-height:1.8; color:#334155;">
								<?php echo wpautop( $description ); ?>
							</div>
						<?php else : ?>
							<p style="color:#64748b; font-size:14px;"><?php esc_html_e( 'Welcome to our official showcase on the Travel Partner Directory. Browse our resources, accreditations, and upcoming conference schedule.', 'tpd-tool' ); ?></p>
						<?php endif; ?>

						<?php if ( 'Yes' === $rewards && ( $promo_title || $promo_desc ) ) : ?>
							<div style="margin-top:28px; background:linear-gradient(135deg, #fef9c3 0%, #fef08a 100%); border:1px solid #fde047; border-radius:12px; padding:20px;">
								<span style="font-size:11px; font-weight:800; color:#854d0e; text-transform:uppercase; letter-spacing:0.5px; display:inline-flex; align-items:center; gap:4px; margin-bottom:4px;">
									<i class="fa-solid fa-gift"></i> <?php esc_html_e( 'Active Advisor Incentive', 'tpd-tool' ); ?>
								</span>
								<h4 style="margin:0 0 6px; font-size:16px; font-weight:800; color:#713f12;"><?php echo esc_html( $promo_title ?: 'Travel Advisor Rewards Program' ); ?></h4>
								<p style="margin:0; font-size:13px; color:#854d0e; line-height:1.6;"><?php echo esc_html( $promo_desc ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<!-- Sidebar Cards -->
					<div>
						<div class="tpd-card-box mb-4" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
							<h4 style="margin:0 0 14px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-id-badge text-blue"></i> <?php esc_html_e( 'Trade Desk & Support', 'tpd-tool' ); ?></h4>
							<div style="display:flex; flex-direction:column; gap:12px; font-size:13.5px;">
								<div>
									<span style="display:block; color:#64748b; font-size:11.5px; font-weight:700; text-transform:uppercase;"><?php esc_html_e( 'Headquarters', 'tpd-tool' ); ?></span>
									<strong style="color:#0f172a;"><?php echo esc_html( $headquarters ); ?></strong>
								</div>
								<?php if ( $phone ) : ?>
									<div>
										<span style="display:block; color:#64748b; font-size:11.5px; font-weight:700; text-transform:uppercase;"><?php esc_html_e( 'Support Desk Phone', 'tpd-tool' ); ?></span>
										<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" style="color:#00798c; font-weight:700; text-decoration:none;"><?php echo esc_html( $phone ); ?></a>
									</div>
								<?php endif; ?>
								<?php if ( $booking_url ) : ?>
									<div>
										<span style="display:block; color:#64748b; font-size:11.5px; font-weight:700; text-transform:uppercase;"><?php esc_html_e( 'Online Booking Portal', 'tpd-tool' ); ?></span>
										<a href="<?php echo esc_url( $booking_url ); ?>" target="_blank" rel="noopener noreferrer" style="color:#00798c; font-weight:700; word-break:break-all; text-decoration:none;">
											<?php echo esc_html( $booking_url ); ?> &rarr;
										</a>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<div class="tpd-card-box" style="background:linear-gradient(135deg, #013243 0%, #00798c 100%); color:#ffffff; border-radius:14px; padding:24px;">
							<h4 style="margin:0 0 8px; font-size:15px; font-weight:800; color:#ffffff;"><i class="fa-solid fa-comments text-gold"></i> <?php esc_html_e( 'Connect with this Supplier', 'tpd-tool' ); ?></h4>
							<p style="margin:0 0 16px; font-size:13px; color:#e2e8f0; line-height:1.6;"><?php esc_html_e( 'Log in to your Advisor Dashboard to chat live with BDMs, request discovery meetings, and bookmark this partner.', 'tpd-tool' ); ?></p>
							<a href="<?php echo esc_url( home_url( '/advisor-dashboard/' ) ); ?>" class="tpd-btn tpd-btn-sm tpd-btn-gold" style="background:#eab308; color:#0f172a; font-weight:800; border:none; padding:10px 18px; border-radius:8px; text-decoration:none; display:inline-block;">
								<?php esc_html_e( 'Open Advisor Dashboard', 'tpd-tool' ); ?> &rarr;
							</a>
						</div>
					</div>
				</div>
			</div>

			<!-- TAB 2: ASSOCIATIONS & CONFERENCES -->
			<div id="pub-associations" class="tpd-pub-panel" style="display:none;">
				<!-- Accreditations & Consortia Badges -->
				<div class="tpd-card-box mb-4" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 18px; font-size:18px; font-weight:800; color:#0f172a;">
						<i class="fa-solid fa-certificate text-gold"></i> <?php esc_html_e( 'Industry Association Memberships & Accreditations', 'tpd-tool' ); ?>
					</h3>
					
					<div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px;">
						<?php if ( 'Yes' === $ustoa ) : ?>
							<div style="display:flex; align-items:center; gap:10px; background:#f0fdf4; border:1px solid #86efac; padding:10px 16px; border-radius:10px;">
								<i class="fa-solid fa-circle-check text-green" style="font-size:18px;"></i>
								<div>
									<strong style="font-size:13.5px; color:#166534; display:block;">USTOA Active Member</strong>
									<span style="font-size:11.5px; color:#15803d;">United States Tour Operators Assoc.</span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 'Yes' === $asta ) : ?>
							<div style="display:flex; align-items:center; gap:10px; background:#eff6ff; border:1px solid #93c5fd; padding:10px 16px; border-radius:10px;">
								<i class="fa-solid fa-circle-check text-blue" style="font-size:18px;"></i>
								<div>
									<strong style="font-size:13.5px; color:#1e40af; display:block;">ASTA Member</strong>
									<span style="font-size:11.5px; color:#1d4ed8;">American Society of Travel Advisors</span>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( 'Yes' === $clia ) : ?>
							<div style="display:flex; align-items:center; gap:10px; background:#faf5ff; border:1px solid #d8b4fe; padding:10px 16px; border-radius:10px;">
								<i class="fa-solid fa-circle-check text-purple" style="font-size:18px;"></i>
								<div>
									<strong style="font-size:13.5px; color:#6b21a8; display:block;">CLIA Member</strong>
									<span style="font-size:11.5px; color:#7e22ce;">Cruise Lines International Assoc.</span>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( $associations ) : ?>
						<div style="padding-top:16px; border-top:1px solid #f1f5f9;">
							<span style="display:block; font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:6px;"><?php esc_html_e( 'Consortia, Networks & Recognized Affiliations:', 'tpd-tool' ); ?></span>
							<p style="margin:0; font-size:14px; color:#0f172a; font-weight:600;"><?php echo esc_html( $associations ); ?></p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Upcoming Conferences Attended -->
				<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 8px; font-size:18px; font-weight:800; color:#0f172a;">
						<i class="fa-solid fa-calendar-days text-purple"></i> <?php esc_html_e( 'Upcoming Conferences & Trade Shows Attended', 'tpd-tool' ); ?>
					</h3>
					<p style="margin:0 0 20px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Meet our sales team and BDM representatives at these upcoming travel trade events.', 'tpd-tool' ); ?></p>

					<?php if ( ! empty( $conferences ) ) : ?>
						<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:16px;">
							<?php foreach ( $conferences as $c ) : ?>
								<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; justify-content:space-between;">
									<div>
										<span style="font-size:11px; font-weight:800; color:#0284c7; background:#e0f2fe; padding:3px 8px; border-radius:4px; text-transform:uppercase;">
											<i class="fa-solid fa-calendar"></i> <?php echo esc_html( isset( $c['date'] ) ? $c['date'] : 'Upcoming' ); ?>
										</span>
										<h4 style="margin:10px 0 6px; font-size:16px; font-weight:800; color:#0f172a;"><?php echo esc_html( isset( $c['name'] ) ? $c['name'] : 'Conference' ); ?></h4>
										<?php if ( ! empty( $c['location'] ) ) : ?>
											<p style="margin:0 0 12px; font-size:13px; color:#475569;"><i class="fa-solid fa-map-pin text-gold"></i> <?php echo esc_html( $c['location'] ); ?></p>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $c['url'] ) ) : ?>
										<a href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:12.5px; font-weight:700; color:#00798c; text-decoration:none;">
											<?php esc_html_e( 'Event Information', 'tpd-tool' ); ?> &rarr;
										</a>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div style="text-align:center; padding:36px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b;">
							<i class="fa-regular fa-calendar" style="font-size:32px; color:#94a3b8; margin-bottom:8px; display:block;"></i>
							<p style="margin:0; font-size:13.5px;"><?php esc_html_e( 'No upcoming conferences listed right now. Check back soon for updated event appearances.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- TAB 3: RESOURCES & BROCHURES -->
			<div id="pub-resources" class="tpd-pub-panel" style="display:none;">
				<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 6px; font-size:18px; font-weight:800; color:#0f172a;">
						<i class="fa-solid fa-folder-open text-purple"></i> <?php esc_html_e( 'Advisor Resources, Digital Brochures & Collateral', 'tpd-tool' ); ?>
					</h3>
					<p style="margin:0 0 24px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Download official brochures, commission schedules, sales guides, and promotional flyers.', 'tpd-tool' ); ?></p>

					<?php if ( ! empty( $resources ) ) : ?>
						<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:18px;">
							<?php foreach ( $resources as $r ) : ?>
								<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; display:flex; flex-direction:column; justify-content:space-between; transition:all 0.2s ease;">
									<div>
										<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
											<span style="font-size:10.5px; font-weight:800; background:#e0e7ff; color:#4338ca; padding:3px 8px; border-radius:4px; text-transform:uppercase;">
												<?php echo esc_html( isset( $r['category'] ) ? $r['category'] : 'Brochure' ); ?>
											</span>
											<i class="fa-solid fa-file-pdf" style="font-size:20px; color:#ef4444;"></i>
										</div>
										<h4 style="margin:0 0 6px; font-size:15px; font-weight:800; color:#0f172a;"><?php echo esc_html( isset( $r['title'] ) ? $r['title'] : 'Resource' ); ?></h4>
										<?php if ( ! empty( $r['description'] ) ) : ?>
											<p style="margin:0 0 14px; font-size:12.5px; color:#64748b; line-height:1.5;"><?php echo esc_html( $r['description'] ); ?></p>
										<?php endif; ?>
									</div>
									<?php if ( ! empty( $r['file_url'] ) ) : ?>
										<a href="<?php echo esc_url( $r['file_url'] ); ?>" target="_blank" download class="tpd-btn tpd-btn-xs" style="background:#00798c; color:#ffffff; font-weight:700; border:none; padding:8px 14px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
											<i class="fa-solid fa-cloud-arrow-down"></i> <?php esc_html_e( 'Download Document', 'tpd-tool' ); ?>
										</a>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div style="text-align:center; padding:36px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b;">
							<i class="fa-regular fa-folder" style="font-size:32px; color:#94a3b8; margin-bottom:8px; display:block;"></i>
							<p style="margin:0; font-size:13.5px;"><?php esc_html_e( 'No digital brochures or resources uploaded yet.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- TAB 4: FAQS -->
			<div id="pub-faqs" class="tpd-pub-panel" style="display:none;">
				<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 6px; font-size:18px; font-weight:800; color:#0f172a;">
						<i class="fa-solid fa-circle-question text-teal"></i> <?php esc_html_e( 'Frequently Asked Questions for Travel Advisors', 'tpd-tool' ); ?>
					</h3>
					<p style="margin:0 0 24px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Find fast answers to common questions about booking procedures, commissions, and policies.', 'tpd-tool' ); ?></p>

					<?php if ( ! empty( $faqs ) ) : ?>
						<div class="tpd-pub-faqs-accordion" style="display:flex; flex-direction:column; gap:12px;">
							<?php foreach ( $faqs as $idx => $f ) : ?>
								<div class="tpd-faq-item" style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">
									<button type="button" class="tpd-faq-trigger" style="width:100%; text-align:left; padding:16px 20px; background:#f8fafc; border:none; display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-weight:700; font-size:14.5px; color:#0f172a;">
										<span><?php echo esc_html( isset( $f['question'] ) ? $f['question'] : '' ); ?></span>
										<i class="fa-solid fa-chevron-down" style="font-size:12px; color:#64748b; transition:transform 0.2s ease;"></i>
									</button>
									<div class="tpd-faq-content" style="display:none; padding:18px 20px; background:#ffffff; font-size:13.5px; color:#334155; line-height:1.7; border-top:1px solid #e2e8f0;">
										<?php echo wpautop( isset( $f['answer'] ) ? $f['answer'] : '' ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div style="text-align:center; padding:36px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b;">
							<i class="fa-regular fa-circle-question" style="font-size:32px; color:#94a3b8; margin-bottom:8px; display:block;"></i>
							<p style="margin:0; font-size:13.5px;"><?php esc_html_e( 'No FAQs published for this supplier yet.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- TAB 5: PHOTO GALLERY -->
			<div id="pub-gallery" class="tpd-pub-panel" style="display:none;">
				<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 6px; font-size:18px; font-weight:800; color:#0f172a;">
						<i class="fa-solid fa-images text-blue"></i> <?php esc_html_e( 'Showcase Photo Gallery', 'tpd-tool' ); ?>
					</h3>
					<p style="margin:0 0 24px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Explore high-resolution fleet, resort, cabin, suite, dining, and destination photos.', 'tpd-tool' ); ?></p>

					<?php if ( ! empty( $gallery ) ) : ?>
						<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:16px;">
							<?php foreach ( $gallery as $g_url ) : ?>
								<div class="tpd-pub-gallery-card" style="border-radius:10px; overflow:hidden; aspect-ratio:4/3; background:#e2e8f0; box-shadow:0 2px 6px rgba(0,0,0,0.06); cursor:pointer;" onclick="window.open('<?php echo esc_url( $g_url ); ?>', '_blank');">
									<img src="<?php echo esc_url( $g_url ); ?>" alt="Showcase Photo" style="width:100%; height:100%; object-fit:cover; transition:transform 0.3s ease;">
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div style="text-align:center; padding:36px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b;">
							<i class="fa-regular fa-images" style="font-size:32px; color:#94a3b8; margin-bottom:8px; display:block;"></i>
							<p style="margin:0; font-size:13.5px;"><?php esc_html_e( 'No photo gallery items published yet.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- TAB 6: VIDEOS -->
			<div id="pub-videos" class="tpd-pub-panel" style="display:none;">
				<div class="tpd-card-box" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:28px; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
					<h3 style="margin:0 0 6px; font-size:18px; font-weight:800; color:#0f172a;">
						<i class="fa-solid fa-video text-red"></i> <?php esc_html_e( 'Showcase Videos & Recorded Webinars', 'tpd-tool' ); ?>
					</h3>
					<p style="margin:0 0 24px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Watch official brand trailers, cabin and resort walkthroughs, and recorded advisor training webinars.', 'tpd-tool' ); ?></p>

					<!-- Featured Main Showcase Video -->
					<?php if ( $embed_main_video ) : ?>
						<div style="margin-bottom:32px;">
							<h4 style="margin:0 0 12px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-play text-red"></i> <?php esc_html_e( 'Featured Brand Video', 'tpd-tool' ); ?></h4>
							<div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.1); background:#000;">
								<iframe src="<?php echo esc_url( $embed_main_video ); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="position:absolute; top:0; left:0; width:100%; height:100%;"></iframe>
							</div>
						</div>
					<?php endif; ?>

					<!-- Additional Videos / Webinars -->
					<?php if ( ! empty( $videos ) ) : ?>
						<h4 style="margin:0 0 14px; font-size:15px; font-weight:800; color:#0f172a;"><i class="fa-solid fa-film text-purple"></i> <?php esc_html_e( 'Webinar Replays & Walkthroughs', 'tpd-tool' ); ?></h4>
						<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
							<?php foreach ( $videos as $v ) :
								$v_embed = tpd_get_video_embed_url( isset( $v['video_url'] ) ? $v['video_url'] : '' );
							?>
								<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
									<?php if ( $v_embed ) : ?>
										<div style="position:relative; padding-bottom:56.25%; height:0; background:#000;">
											<iframe src="<?php echo esc_url( $v_embed ); ?>" frameborder="0" allowfullscreen style="position:absolute; top:0; left:0; width:100%; height:100%;"></iframe>
										</div>
									<?php endif; ?>
									<div style="padding:14px;">
										<h5 style="margin:0 0 4px; font-size:14px; font-weight:800; color:#0f172a;"><?php echo esc_html( isset( $v['title'] ) ? $v['title'] : 'Video' ); ?></h5>
										<?php if ( ! empty( $v['description'] ) ) : ?>
											<p style="margin:0; font-size:12px; color:#64748b;"><?php echo esc_html( $v['description'] ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php elseif ( ! $embed_main_video ) : ?>
						<div style="text-align:center; padding:36px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#64748b;">
							<i class="fa-solid fa-video" style="font-size:32px; color:#94a3b8; margin-bottom:8px; display:block;"></i>
							<p style="margin:0; font-size:13.5px;"><?php esc_html_e( 'No videos or webinars published yet.', 'tpd-tool' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>

		<?php endif; ?>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Frontend Tab Switcher for Enhanced Listing Profile
	var tabs = document.querySelectorAll('.tpd-pub-tab');
	var panels = document.querySelectorAll('.tpd-pub-panel');

	tabs.forEach(function(tab) {
		tab.addEventListener('click', function(e) {
			e.preventDefault();
			var target = this.getAttribute('data-tab');
			tabs.forEach(function(t) {
				t.classList.remove('active');
				t.style.borderBottom = 'none';
				t.style.color = '#64748b';
				t.style.fontWeight = '600';
			});
			this.classList.add('active');
			this.style.borderBottom = '3px solid #00798c';
			this.style.color = '#0f172a';
			this.style.fontWeight = '700';

			panels.forEach(function(p) {
				p.style.display = 'none';
			});
			var activePanel = document.getElementById(target);
			if (activePanel) {
				activePanel.style.display = 'block';
			}
		});
	});

	// FAQ Accordion Toggle
	var faqTriggers = document.querySelectorAll('.tpd-faq-trigger');
	faqTriggers.forEach(function(btn) {
		btn.addEventListener('click', function() {
			var content = this.nextElementSibling;
			var icon = this.querySelector('i');
			if (content.style.display === 'block') {
				content.style.display = 'none';
				if (icon) icon.style.transform = 'rotate(0deg)';
			} else {
				content.style.display = 'block';
				if (icon) icon.style.transform = 'rotate(180deg)';
			}
		});
	});
});
</script>

<?php
if ( ! empty( $is_standalone_template ) ) {
	get_footer();
}
