<?php
/**
 * Saved / Favorite Suppliers Manager for TPD Tool
 * Allows advisors to bookmark and manage favorite supplier partners.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Favorites {

	public static function init() {
		add_action( 'wp_ajax_tpd_toggle_favorite_supplier', array( __CLASS__, 'ajax_toggle_favorite' ) );
	}

	public static function ajax_toggle_favorite() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to save suppliers.', 'tpd-tool' ) ) );
		}

		$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
		if ( ! $supplier_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid supplier ID.', 'tpd-tool' ) ) );
		}

		$user_id = get_current_user_id();
		$saved   = self::get_saved_supplier_ids( $user_id );

		if ( in_array( $supplier_id, $saved, true ) ) {
			// Remove from favorites
			$saved = array_diff( $saved, array( $supplier_id ) );
			$is_saved = false;
			$msg = __( 'Supplier removed from saved list.', 'tpd-tool' );
		} else {
			// Add to favorites
			$saved[] = $supplier_id;
			$is_saved = true;
			$msg = __( 'Supplier added to your saved list!', 'tpd-tool' );
		}

		update_user_meta( $user_id, '_tpd_saved_suppliers', array_values( $saved ) );

		// Increment supplier listing saves metric
		$current_saves = (int) get_post_meta( $supplier_id, 'tpd_listing_saves', true );
		update_post_meta( $supplier_id, 'tpd_listing_saves', max( 0, $current_saves + ( $is_saved ? 1 : -1 ) ) );

		wp_send_json_success( array(
			'is_saved' => $is_saved,
			'message'  => $msg,
			'count'    => count( $saved ),
		) );
	}

	public static function get_saved_supplier_ids( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return array();
		}

		$saved = get_user_meta( $user_id, '_tpd_saved_suppliers', true );
		return is_array( $saved ) ? $saved : array();
	}

	public static function is_supplier_saved( $supplier_id, $user_id = null ) {
		$saved = self::get_saved_supplier_ids( $user_id );
		return in_array( (int) $supplier_id, $saved, true );
	}

	/**
	 * Render Saved Suppliers Grid for Advisor Dashboard & Shortcode [tpd_saved_suppliers]
	 */
	public static function render_saved_suppliers( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return '<div class="tpd-alert tpd-alert-info" style="padding:16px; border-radius:10px; background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe;">' . esc_html__( 'Please log in to view your saved suppliers.', 'tpd-tool' ) . '</div>';
		}

		$saved_ids = self::get_saved_supplier_ids( $user_id );
		if ( empty( $saved_ids ) ) {
			ob_start();
			?>
			<div class="tpd-empty-state-box" style="text-align:center; padding:48px 24px; background:#ffffff; border:1.5px dashed #cbd5e1; border-radius:14px; margin-top:8px;">
				<div style="width:64px; height:64px; border-radius:50%; background:#f0f9ff; color:#0284c7; display:inline-flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:16px;">
					<i class="fa-regular fa-bookmark"></i>
				</div>
				<h4 style="margin:0 0 8px; font-size:18px; font-weight:700; color:#0f172a;"><?php esc_html_e( 'No Saved Suppliers Yet', 'tpd-tool' ); ?></h4>
				<p style="margin:0 0 20px; font-size:13.5px; color:#64748b; max-width:440px; margin-left:auto; margin-right:auto; line-height:1.5;">
					<?php esc_html_e( 'Bookmark preferred supplier partners while browsing the directory to save them here for fast 1-click access and direct communication.', 'tpd-tool' ); ?>
				</p>
				<a href="#tpd-view-suppliers" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-tab-link" data-view="suppliers" style="padding:10px 22px; font-weight:700; border-radius:8px; text-decoration:none; display:inline-flex; align-items:center; gap:8px;">
					<i class="fa-solid fa-compass"></i> <?php esc_html_e( 'Explore Supplier Directory', 'tpd-tool' ); ?>
				</a>
			</div>
			<?php
			return ob_get_clean();
		}

		$suppliers = get_posts( array(
			'post_type'      => 'supplier_listing',
			'post__in'       => $saved_ids,
			'posts_per_page' => -1,
			'orderby'        => 'post__in',
		) );

		if ( empty( $suppliers ) ) {
			return '<div class="tpd-empty-state-box" style="text-align:center; padding:36px 20px; background:#ffffff; border:1px dashed #cbd5e1; border-radius:12px;"><p style="color:#64748b; margin:0;">' . esc_html__( 'No saved suppliers found in the directory.', 'tpd-tool' ) . '</p></div>';
		}

		ob_start();
		?>
		<div class="tpd-saved-suppliers-grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:20px; margin-top:8px;">
			<?php foreach ( $suppliers as $sp ) :
				$sp_banner = get_post_meta( $sp->ID, 'tpd_banner_url', true ) ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=500&auto=format&fit=crop&q=80';
				$sp_logo   = get_post_meta( $sp->ID, 'tpd_logo_url', true ) ?: '';
				$sp_cat    = get_post_meta( $sp->ID, 'tpd_tagline', true ) ?: get_post_meta( $sp->ID, 'tpd_supplier_category', true ) ?: 'Verified Supplier Partner';
				$sp_owner  = (int) get_post_meta( $sp->ID, 'tpd_assigned_user', true ) ?: 1;
				$sp_tier   = class_exists( 'TPD_Tool_Tiers' ) ? TPD_Tool_Tiers::get_user_tier( $sp_owner ) : 'free';
				$sp_hq     = get_post_meta( $sp->ID, 'tpd_headquarters', true ) ?: '';
			?>
				<div class="tpd-supp-showcase-card tpd-saved-supplier-card" id="tpd-saved-card-<?php echo esc_attr( $sp->ID ); ?>" style="border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.04); display:flex; flex-direction:column; transition:transform 0.2s ease, box-shadow 0.2s ease;">
					<div class="tpd-ssc-thumb" style="height:130px; background-image: url('<?php echo esc_url( $sp_banner ); ?>'); background-size:cover; background-position:center; position:relative; display:flex; justify-content:space-between; align-items:flex-start; padding:12px;">
						<div style="position:absolute; inset:0; background:linear-gradient(to bottom, rgba(0,0,0,0.2) 0%, rgba(0,0,0,0.7) 100%);"></div>
						<div style="position:relative; z-index:2;">
							<?php if ( 'premium' === $sp_tier ) : ?>
								<span style="background:#013243; color:#fff; font-size:10px; font-weight:800; padding:3px 8px; border-radius:4px; text-transform:uppercase;"><i class="fa-solid fa-crown text-gold"></i> PREFERRED</span>
							<?php elseif ( 'standard' === $sp_tier ) : ?>
								<span style="background:#00798c; color:#fff; font-size:10px; font-weight:800; padding:3px 8px; border-radius:4px; text-transform:uppercase;">PARTNER</span>
							<?php endif; ?>
						</div>
						<button type="button" class="tpd-btn tpd-btn-xs tpd-toggle-favorite-btn active saved text-gold" data-supplier-id="<?php echo esc_attr( $sp->ID ); ?>" style="position:relative; z-index:2; background:#ffffff; border:none; border-radius:6px; padding:6px 10px; cursor:pointer;" title="<?php esc_attr_e( 'Remove from Saved', 'tpd-tool' ); ?>">
							<i class="fa-solid fa-bookmark" style="color:#eab308;"></i>
						</button>
					</div>
					<div class="tpd-ssc-body" style="padding:16px; display:flex; flex-direction:column; flex:1;">
						<div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
							<?php if ( $sp_logo ) : ?>
								<img src="<?php echo esc_url( $sp_logo ); ?>" alt="<?php echo esc_attr( $sp->post_title ); ?>" style="width:36px; height:36px; object-fit:contain; border-radius:6px; border:1px solid #f1f5f9; background:#fff;">
							<?php endif; ?>
							<div>
								<h4 style="margin:0; font-size:15px; font-weight:800; color:#0f172a; line-height:1.3;"><?php echo esc_html( $sp->post_title ); ?></h4>
								<?php if ( $sp_hq ) : ?>
									<span style="font-size:11.5px; color:#64748b;"><i class="fa-solid fa-location-dot" style="color:#00798c;"></i> <?php echo esc_html( $sp_hq ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<p style="margin:4px 0 16px; font-size:12.5px; color:#64748b; line-height:1.4;"><?php echo esc_html( wp_trim_words( $sp_cat, 8 ) ); ?></p>
						<div style="margin-top:auto; display:flex; gap:8px;">
							<a href="<?php echo esc_url( get_permalink( $sp->ID ) ); ?>" target="_blank" class="tpd-btn tpd-btn-sm tpd-btn-outline" style="flex:1; text-align:center; text-decoration:none; font-size:12px; font-weight:700; border-radius:6px; padding:8px 10px;">
								<i class="fa-solid fa-eye"></i> <?php esc_html_e( 'Showcase', 'tpd-tool' ); ?>
							</a>
							<a href="#tpd-view-chat" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-tab-link" data-view="chat" data-recipient="<?php echo esc_attr( $sp_owner ); ?>" style="flex:1; text-align:center; text-decoration:none; font-size:12px; font-weight:700; border-radius:6px; padding:8px 10px; background:#013243; color:#fff;">
								<i class="fa-solid fa-comment-dots"></i> <?php esc_html_e( 'Message', 'tpd-tool' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

TPD_Tool_Favorites::init();
