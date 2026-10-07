<?php
/**
 * Frontend Profile & Listing Editor for TPD Tool
 * Enables Travel Advisors and Suppliers to manage their profile pictures,
 * agency logos, banners, brochures, team members, listing content, and dynamic ACF/custom fields
 * directly from the frontend with 100% bi-directional sync to WP Admin CPTs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Profile_Editor {

	public static function init() {
		add_action( 'wp_ajax_tpd_save_advisor_profile', array( __CLASS__, 'ajax_save_advisor_profile' ) );
		add_action( 'wp_ajax_tpd_save_supplier_listing', array( __CLASS__, 'ajax_save_supplier_listing' ) );
		add_action( 'wp_ajax_tpd_save_supplier_rep_profile', array( __CLASS__, 'ajax_save_supplier_rep_profile' ) );
		add_action( 'wp_ajax_tpd_upload_media_file', array( __CLASS__, 'ajax_upload_media' ) );
		add_action( 'wp_ajax_tpd_submit_claim_listing', array( __CLASS__, 'ajax_submit_claim_listing' ) );
		add_action( 'wp_ajax_tpd_admin_review_claim', array( __CLASS__, 'ajax_review_claim' ) );
		add_action( 'wp_ajax_tpd_manage_team_member', array( __CLASS__, 'ajax_manage_team_member' ) );
	}

	/**
	 * AJAX Upload for Profile Headshots, Logos, Banners, and PDF Brochures
	 */
	public static function ajax_upload_media() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		if ( ! is_user_logged_in() || ! TPD_Tool_Roles::is_account_active() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'tpd-tool' ) ) );
		}

		// Validate file extension and MIME type against strict allowlist
		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
		);
		$filetype = wp_check_filetype( sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ), $allowed_mimes );
		if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Security Error: Only JPG, PNG, GIF, WEBP images and PDF documents are allowed.', 'tpd-tool' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);
		$attachment_id = media_handle_upload( 'file', 0, array(), $upload_overrides );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		$url = wp_get_attachment_url( $attachment_id );

		wp_send_json_success( array(
			'attachment_id' => $attachment_id,
			'url'           => $url,
			'message'       => __( 'File uploaded successfully!', 'tpd-tool' ),
		) );
	}

	/**
	 * AJAX: Save Advisor Profile Updates (Bi-Directionally Synced with travel_advisor CPT & ACF)
	 */
	public static function ajax_save_advisor_profile() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}
		if ( ! TPD_Tool_Roles::is_advisor( $user_id ) && ! TPD_Tool_Roles::is_admin( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized: Travel Advisor role required.', 'tpd-tool' ) ) );
		}

		$first_name         = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name          = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name       = trim( $first_name . ' ' . $last_name );
		$phone              = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email              = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$profile_handle     = isset( $_POST['profile_handle'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_handle'] ) ) : '';
		$agency_name        = isset( $_POST['agency_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_name'] ) ) : '';
		$agency_address     = isset( $_POST['agency_address'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_address'] ) ) : '';
		$location           = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$website            = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$has_host           = isset( $_POST['has_host'] ) ? sanitize_text_field( wp_unslash( $_POST['has_host'] ) ) : 'yes';
		$consortia          = isset( $_POST['consortia'] ) ? sanitize_text_field( wp_unslash( $_POST['consortia'] ) ) : '';
		$host_agency        = isset( $_POST['host_agency'] ) ? sanitize_text_field( wp_unslash( $_POST['host_agency'] ) ) : '';
		$clia_num           = isset( $_POST['clia_number'] ) ? sanitize_text_field( wp_unslash( $_POST['clia_number'] ) ) : '';
		$iata_num           = isset( $_POST['iata_number'] ) ? sanitize_text_field( wp_unslash( $_POST['iata_number'] ) ) : '';
		$arc_num            = isset( $_POST['arc_number'] ) ? sanitize_text_field( wp_unslash( $_POST['arc_number'] ) ) : '';
		$true_num           = isset( $_POST['true_number'] ) ? sanitize_text_field( wp_unslash( $_POST['true_number'] ) ) : '';
		$bio                = isset( $_POST['bio'] ) ? wp_kses_post( wp_unslash( $_POST['bio'] ) ) : '';
		$consultation_fee   = isset( $_POST['consultation_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['consultation_fee'] ) ) : 'yes';
		$years_experience   = isset( $_POST['years_experience'] ) ? sanitize_text_field( wp_unslash( $_POST['years_experience'] ) ) : '';
		$group_travel_spec  = isset( $_POST['group_travel_spec'] ) ? sanitize_text_field( wp_unslash( $_POST['group_travel_spec'] ) ) : '';
		$clients_per_year   = isset( $_POST['clients_per_year'] ) ? sanitize_text_field( wp_unslash( $_POST['clients_per_year'] ) ) : '';
		$sales_volume       = isset( $_POST['sales_volume'] ) ? sanitize_text_field( wp_unslash( $_POST['sales_volume'] ) ) : '';
		$sales_volume_goal  = isset( $_POST['sales_volume_goal'] ) ? sanitize_text_field( wp_unslash( $_POST['sales_volume_goal'] ) ) : '';
		$pref_suppliers     = isset( $_POST['preferred_suppliers'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_suppliers'] ) ) : '';
		$travel_types       = isset( $_POST['travel_types'] ) ? sanitize_text_field( wp_unslash( $_POST['travel_types'] ) ) : '';
		$home_airports      = isset( $_POST['home_airports'] ) ? sanitize_text_field( wp_unslash( $_POST['home_airports'] ) ) : '';
		$country            = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'United States';
		$accolades          = isset( $_POST['accolades'] ) ? sanitize_text_field( wp_unslash( $_POST['accolades'] ) ) : '';
		$portfolio          = isset( $_POST['portfolio_highlights'] ) ? sanitize_textarea_field( wp_unslash( $_POST['portfolio_highlights'] ) ) : '';
		$social_facebook    = isset( $_POST['social_facebook'] ) ? esc_url_raw( wp_unslash( $_POST['social_facebook'] ) ) : '';
		$social_twitter     = isset( $_POST['social_twitter'] ) ? esc_url_raw( wp_unslash( $_POST['social_twitter'] ) ) : '';
		$social_youtube     = isset( $_POST['social_youtube'] ) ? esc_url_raw( wp_unslash( $_POST['social_youtube'] ) ) : '';
		$social_instagram   = isset( $_POST['social_instagram'] ) ? esc_url_raw( wp_unslash( $_POST['social_instagram'] ) ) : '';
		$social_tiktok      = isset( $_POST['social_tiktok'] ) ? esc_url_raw( wp_unslash( $_POST['social_tiktok'] ) ) : '';
		$social_linkedin    = isset( $_POST['social_linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['social_linkedin'] ) ) : '';
		$headshot_url       = isset( $_POST['headshot_url'] ) ? esc_url_raw( wp_unslash( $_POST['headshot_url'] ) ) : '';
		$logo_url           = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$banner_url         = isset( $_POST['banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['banner_url'] ) ) : '';
		if ( ! empty( $_POST['preset_banner_choice'] ) && empty( $banner_url ) ) {
			$banner_url = esc_url_raw( wp_unslash( $_POST['preset_banner_choice'] ) );
		}

		// Update WP User
		$user_data = array( 'ID' => $user_id );
		if ( $first_name ) $user_data['first_name'] = $first_name;
		if ( $last_name ) $user_data['last_name'] = $last_name;
		if ( $display_name ) $user_data['display_name'] = $display_name;
		if ( $email && is_email( $email ) ) $user_data['user_email'] = $email;
		wp_update_user( $user_data );

		// Update user meta
		if ( $headshot_url ) update_user_meta( $user_id, 'tpd_headshot_url', $headshot_url );
		if ( $logo_url ) update_user_meta( $user_id, 'tpd_logo_url', $logo_url );
		if ( $banner_url ) update_user_meta( $user_id, 'tpd_banner_url', $banner_url );
		update_user_meta( $user_id, 'tpd_profile_handle', $profile_handle );
		update_user_meta( $user_id, 'tpd_phone', $phone );
		update_user_meta( $user_id, 'tpd_agency_name', $agency_name );
		update_user_meta( $user_id, 'tpd_agency_address', $agency_address );
		update_user_meta( $user_id, 'tpd_location', $location );
		update_user_meta( $user_id, 'tpd_website', $website );
		update_user_meta( $user_id, 'tpd_has_host', $has_host );
		update_user_meta( $user_id, 'tpd_consortia', $consortia );
		update_user_meta( $user_id, 'tpd_host_agency', $host_agency );
		update_user_meta( $user_id, 'tpd_clia_num', $clia_num );
		update_user_meta( $user_id, 'tpd_iata_num', $iata_num );
		update_user_meta( $user_id, 'tpd_arc_num', $arc_num );
		update_user_meta( $user_id, 'tpd_true_num', $true_num );
		update_user_meta( $user_id, 'tpd_consultation_fee', $consultation_fee );
		update_user_meta( $user_id, 'tpd_years_experience', $years_experience );
		update_user_meta( $user_id, 'tpd_group_travel_spec', $group_travel_spec );
		update_user_meta( $user_id, 'tpd_clients_per_year', $clients_per_year );
		update_user_meta( $user_id, 'tpd_personal_sales_volume', $sales_volume );
		update_user_meta( $user_id, 'tpd_sales_volume_goal', $sales_volume_goal );
		update_user_meta( $user_id, 'tpd_preferred_suppliers', $pref_suppliers );
		update_user_meta( $user_id, 'tpd_travel_types', $travel_types );
		update_user_meta( $user_id, 'tpd_home_airports', $home_airports );
		update_user_meta( $user_id, 'tpd_country', $country );
		update_user_meta( $user_id, 'tpd_accolades', $accolades );
		update_user_meta( $user_id, 'tpd_portfolio_highlights', $portfolio );
		update_user_meta( $user_id, 'tpd_social_facebook', $social_facebook );
		update_user_meta( $user_id, 'tpd_social_twitter', $social_twitter );
		update_user_meta( $user_id, 'tpd_social_youtube', $social_youtube );
		update_user_meta( $user_id, 'tpd_social_instagram', $social_instagram );
		update_user_meta( $user_id, 'tpd_social_tiktok', $social_tiktok );
		update_user_meta( $user_id, 'tpd_social_linkedin', $social_linkedin );

		// Find or create paired travel_advisor CPT
		$query = get_posts( array(
			'post_type'      => 'travel_advisor',
			'posts_per_page' => 1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );

		if ( empty( $query ) ) {
			$post_id = wp_insert_post( array(
				'post_type'    => 'travel_advisor',
				'post_title'   => $display_name ?: 'Travel Advisor',
				'post_name'    => sanitize_title( $profile_handle ?: $display_name ),
				'post_content' => $bio,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
			) );
			update_post_meta( $post_id, 'tpd_assigned_user', $user_id );
		} else {
			$post_id = $query[0];
			$upd_post = array(
				'ID'           => $post_id,
				'post_title'   => $display_name ?: get_the_title( $post_id ),
				'post_content' => $bio,
			);
			if ( $profile_handle ) {
				$upd_post['post_name'] = sanitize_title( $profile_handle );
			}
			wp_update_post( $upd_post );
		}

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'tpd_profile_handle', $profile_handle );
			update_post_meta( $post_id, 'tpd_agency_name', $agency_name );
			update_post_meta( $post_id, 'tpd_agency_address', $agency_address );
			update_post_meta( $post_id, 'tpd_phone', $phone );
			update_post_meta( $post_id, 'tpd_location', $location );
			update_post_meta( $post_id, 'tpd_website', $website );
			update_post_meta( $post_id, 'tpd_has_host', $has_host );
			update_post_meta( $post_id, 'tpd_consortia', $consortia );
			update_post_meta( $post_id, 'tpd_host_agency', $host_agency );
			update_post_meta( $post_id, 'tpd_clia_number', $clia_num );
			update_post_meta( $post_id, 'tpd_iata_number', $iata_num );
			update_post_meta( $post_id, 'tpd_arc_number', $arc_num );
			update_post_meta( $post_id, 'tpd_true_number', $true_num );
			update_post_meta( $post_id, 'tpd_consultation_fee', $consultation_fee );
			update_post_meta( $post_id, 'tpd_years_experience', $years_experience );
			update_post_meta( $post_id, 'tpd_group_travel_spec', $group_travel_spec );
			update_post_meta( $post_id, 'tpd_clients_per_year', $clients_per_year );
			update_post_meta( $post_id, 'tpd_personal_sales_volume', $sales_volume );
			update_post_meta( $post_id, 'tpd_sales_volume_goal', $sales_volume_goal );
			update_post_meta( $post_id, 'tpd_preferred_suppliers', $pref_suppliers );
			update_post_meta( $post_id, 'tpd_travel_types', $travel_types );
			update_post_meta( $post_id, 'tpd_home_airports', $home_airports );
			update_post_meta( $post_id, 'tpd_country', $country );
			update_post_meta( $post_id, 'tpd_accolades', $accolades );
			update_post_meta( $post_id, 'tpd_portfolio_highlights', $portfolio );
			update_post_meta( $post_id, 'tpd_social_facebook', $social_facebook );
			update_post_meta( $post_id, 'tpd_social_twitter', $social_twitter );
			update_post_meta( $post_id, 'tpd_social_youtube', $social_youtube );
			update_post_meta( $post_id, 'tpd_social_instagram', $social_instagram );
			update_post_meta( $post_id, 'tpd_social_tiktok', $social_tiktok );
			update_post_meta( $post_id, 'tpd_social_linkedin', $social_linkedin );
			if ( $headshot_url ) update_post_meta( $post_id, 'tpd_headshot_url', $headshot_url );
			if ( $logo_url ) update_post_meta( $post_id, 'tpd_logo_url', $logo_url );
			if ( $banner_url ) update_post_meta( $post_id, 'tpd_banner_url', $banner_url );

			if ( isset( $_POST['destinations'] ) ) {
				$dests = array_map( 'sanitize_text_field', (array) $_POST['destinations'] );
				wp_set_object_terms( $post_id, $dests, 'travel_destination' );
			}
			if ( isset( $_POST['travel_styles'] ) ) {
				$styles = array_map( 'sanitize_text_field', (array) $_POST['travel_styles'] );
				wp_set_object_terms( $post_id, $styles, 'travel_style' );
			}

			// Save Dynamic ACF & Admin Custom Fields
			if ( class_exists( 'TPD_Tool_CPT' ) ) {
				TPD_Tool_CPT::save_dynamic_fields_submission( 'travel_advisor', $post_id, $user_id );
			}
		}

		wp_send_json_success( array(
			'message'      => __( 'Account Info & Directory Profile updated and synced!', 'tpd-tool' ),
			'display_name' => $display_name,
			'headshot_url' => $headshot_url,
		) );
	}

	/**
	 * AJAX: Save Supplier Listing Showcase Content (Bi-Directionally Synced with supplier_listing CPT & ACF)
	 */
	public static function ajax_save_supplier_listing() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}
		if ( ! TPD_Tool_Roles::is_supplier( $user_id ) && ! TPD_Tool_Roles::is_admin( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized: Supplier Partner role required.', 'tpd-tool' ) ) );
		}

		$listing_id   = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		if ( $listing_id > 0 && ! TPD_Tool_Roles::is_admin( $user_id ) ) {
			$post_obj      = get_post( $listing_id );
			$assigned_user = (int) get_post_meta( $listing_id, 'tpd_assigned_user', true );
			if ( ! $post_obj || 'supplier_listing' !== $post_obj->post_type || ( (int) $post_obj->post_author !== $user_id && $assigned_user !== $user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not own this supplier listing.', 'tpd-tool' ) ) );
			}
		}

		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$tagline      = isset( $_POST['tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['tagline'] ) ) : '';
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$booking_url  = isset( $_POST['booking_url'] ) ? esc_url_raw( wp_unslash( $_POST['booking_url'] ) ) : '';
		$headquarters = isset( $_POST['headquarters'] ) ? sanitize_text_field( wp_unslash( $_POST['headquarters'] ) ) : '';
		$rewards      = isset( $_POST['rewards'] ) ? sanitize_text_field( wp_unslash( $_POST['rewards'] ) ) : 'No';
		$ustoa        = isset( $_POST['ustoa'] ) ? sanitize_text_field( wp_unslash( $_POST['ustoa'] ) ) : 'No';
		$asta         = isset( $_POST['asta'] ) ? sanitize_text_field( wp_unslash( $_POST['asta'] ) ) : 'No';
		$description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$logo_url     = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$banner_url   = isset( $_POST['banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['banner_url'] ) ) : '';
		$video_url    = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';
		$promo_title  = isset( $_POST['promo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_title'] ) ) : '';
		$promo_desc   = isset( $_POST['promo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['promo_desc'] ) ) : '';

		if ( ! $listing_id ) {
			$listing_id = wp_insert_post( array(
				'post_type'    => 'supplier_listing',
				'post_title'   => $company_name ?: 'New Supplier Listing',
				'post_content' => $description,
				'post_status'  => 'publish',
				'post_author'  => $user_id,
			) );
			update_post_meta( $listing_id, 'tpd_assigned_user', $user_id );
		} else {
			wp_update_post( array(
				'ID'           => $listing_id,
				'post_title'   => $company_name ?: get_the_title( $listing_id ),
				'post_content' => $description,
			) );
		}

		update_post_meta( $listing_id, 'tpd_company_name', $company_name );
		update_post_meta( $listing_id, 'tpd_tagline', $tagline );
		update_post_meta( $listing_id, 'tpd_primary_rep_phone', $phone );
		update_post_meta( $listing_id, 'tpd_booking_portal_url', $booking_url );
		update_post_meta( $listing_id, 'tpd_headquarters', $headquarters );
		update_post_meta( $listing_id, 'tpd_agent_rewards', $rewards );
		update_post_meta( $listing_id, 'tpd_member_ustoa', $ustoa );
		update_post_meta( $listing_id, 'tpd_member_asta', $asta );
		if ( $logo_url ) update_post_meta( $listing_id, 'tpd_logo_url', $logo_url );
		if ( $banner_url ) update_post_meta( $listing_id, 'tpd_banner_url', $banner_url );
		update_post_meta( $listing_id, 'tpd_main_video_url', $video_url );
		update_post_meta( $listing_id, 'tpd_promo_title', $promo_title );
		update_post_meta( $listing_id, 'tpd_promo_desc', $promo_desc );

		// Sync company name back to user meta
		if ( $company_name ) {
			update_user_meta( $user_id, 'tpd_company_name', $company_name );
		}

		// Save Dynamic ACF & Admin Custom Fields
		if ( class_exists( 'TPD_Tool_CPT' ) ) {
			TPD_Tool_CPT::save_dynamic_fields_submission( 'supplier_listing', $listing_id, $user_id );
		}

		wp_send_json_success( array(
			'message'      => __( 'Supplier Listing, Showcase & Custom Fields updated!', 'tpd-tool' ),
			'listing_id'   => $listing_id,
			'company_name' => $company_name,
		) );
	}

	/**
	 * AJAX: Save Supplier Personal Representative Profile Settings
	 */
	public static function ajax_save_supplier_rep_profile() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}
		if ( ! TPD_Tool_Roles::is_supplier( $user_id ) && ! TPD_Tool_Roles::is_admin( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized: Supplier Partner role required.', 'tpd-tool' ) ) );
		}

		$first_name     = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name      = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name   = trim( $first_name . ' ' . $last_name );
		$email          = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone          = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$position_title = isset( $_POST['position_title'] ) ? sanitize_text_field( wp_unslash( $_POST['position_title'] ) ) : '';
		$company_name   = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$country        = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$avatar_url     = isset( $_POST['headshot_url'] ) ? esc_url_raw( wp_unslash( $_POST['headshot_url'] ) ) : '';

		$user_args = array( 'ID' => $user_id );
		if ( $first_name ) $user_args['first_name'] = $first_name;
		if ( $last_name ) $user_args['last_name'] = $last_name;
		if ( $display_name ) $user_args['display_name'] = $display_name;
		if ( $email && is_email( $email ) ) $user_args['user_email'] = $email;
		wp_update_user( $user_args );

		update_user_meta( $user_id, 'tpd_phone', $phone );
		update_user_meta( $user_id, 'tpd_position_title', $position_title );
		update_user_meta( $user_id, 'tpd_company_name', $company_name );
		update_user_meta( $user_id, 'tpd_country_of_residence', $country );
		if ( $avatar_url ) {
			update_user_meta( $user_id, 'tpd_headshot_url', $avatar_url );
		}

		// Sync with linked supplier_listing CPT
		$listings = get_posts( array(
			'post_type'      => 'supplier_listing',
			'posts_per_page' => 1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );
		if ( ! empty( $listings ) ) {
			$lid = $listings[0];
			update_post_meta( $lid, 'tpd_position_title', $position_title );
			update_post_meta( $lid, 'tpd_country_of_residence', $country );
			if ( $company_name ) {
				update_post_meta( $lid, 'tpd_company_name', $company_name );
				wp_update_post( array( 'ID' => $lid, 'post_title' => $company_name ) );
			}
		}

		wp_send_json_success( array(
			'message'        => __( 'Representative profile settings saved!', 'tpd-tool' ),
			'display_name'   => $display_name,
			'position_title' => $position_title,
			'company_name'   => $company_name,
			'headshot_url'   => $avatar_url,
		) );
	}

	/**
	 * AJAX: Submit Claim a Listing Request
	 */
	public static function ajax_submit_claim_listing() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		$notes      = isset( $_POST['claim_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['claim_notes'] ) ) : '';

		if ( ! $user_id || ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing or authentication.', 'tpd-tool' ) ) );
		}

		$claims = (array) get_option( 'tpd_pending_listing_claims', array() );
		$claims[ $listing_id ] = array(
			'user_id'    => $user_id,
			'listing_id' => $listing_id,
			'date'       => current_time( 'mysql' ),
			'notes'      => $notes,
			'status'     => 'pending',
		);
		update_option( 'tpd_pending_listing_claims', $claims );

		// Notify Super Admin via email
		$admin_email = get_option( 'admin_email' );
		$user = get_userdata( $user_id );
		$listing_title = get_the_title( $listing_id );
		wp_mail(
			$admin_email,
			sprintf( '[TPD Claim Request] %s claimed %s', $user->display_name, $listing_title ),
			sprintf( "%s (%s) has submitted a claim for listing '%s'.\n\nNotes: %s\n\nPlease approve or reject this claim in the TPD Super Admin Hub:\n%s",
				$user->display_name,
				$user->user_email,
				$listing_title,
				$notes,
				admin_url( 'admin.php?page=tpd-super-admin' )
			)
		);

		wp_send_json_success( array(
			'message' => __( 'Claim request submitted! Our team will verify and activate your listing shortly.', 'tpd-tool' ),
		) );
	}

	/**
	 * AJAX: Manage Virtual Office Team Member per Listing
	 */
	public static function ajax_manage_team_member() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$listing_id  = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		$member_name = isset( $_POST['member_name'] ) ? sanitize_text_field( wp_unslash( $_POST['member_name'] ) ) : '';
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone       = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$photo_url   = isset( $_POST['photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) ) : '';

		if ( ! $listing_id || empty( $member_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Listing ID and Member Name are required.', 'tpd-tool' ) ) );
		}

		if ( ! TPD_Tool_Roles::is_admin( $user_id ) ) {
			$post_obj      = get_post( $listing_id );
			$assigned_user = (int) get_post_meta( $listing_id, 'tpd_assigned_user', true );
			if ( ! $post_obj || ( (int) $post_obj->post_author !== $user_id && $assigned_user !== $user_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not own this supplier listing.', 'tpd-tool' ) ) );
			}
		}

		$team = get_post_meta( $listing_id, '_tpd_team_members', true );
		if ( ! is_array( $team ) ) {
			$team = array();
		}
		$team[] = array(
			'name'      => $member_name,
			'title'     => $title,
			'email'     => $email,
			'phone'     => $phone,
			'photo_url' => $photo_url,
		);
		update_post_meta( $listing_id, '_tpd_team_members', $team );

		wp_send_json_success( array(
			'message' => __( 'Team member added to Virtual Office!', 'tpd-tool' ),
			'team'    => $team,
		) );
	}

	/**
	 * AJAX: Super Admin Review Listing Claim (Approve / Reject)
	 */
	public static function ajax_review_claim() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'tpd-tool' ) ) );
		}

		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		$decision   = isset( $_POST['decision'] ) ? sanitize_text_field( $_POST['decision'] ) : '';

		if ( ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing ID.', 'tpd-tool' ) ) );
		}

		$claims = (array) get_option( 'tpd_pending_listing_claims', array() );
		if ( ! isset( $claims[ $listing_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Claim record not found.', 'tpd-tool' ) ) );
		}

		if ( 'approve' === $decision ) {
			$claiming_user_id = $claims[ $listing_id ]['user_id'];
			wp_update_post( array(
				'ID'          => $listing_id,
				'post_author' => $claiming_user_id,
			) );
			update_post_meta( $listing_id, 'tpd_assigned_user', $claiming_user_id );
			$claims[ $listing_id ]['status'] = 'approved';
			update_option( 'tpd_pending_listing_claims', $claims );

			wp_send_json_success( array(
				'message' => __( 'Claim approved! The listing has been assigned to the supplier.', 'tpd-tool' ),
				'status'  => 'approved',
			) );
		} elseif ( 'reject' === $decision ) {
			$claims[ $listing_id ]['status'] = 'rejected';
			update_option( 'tpd_pending_listing_claims', $claims );

			wp_send_json_success( array(
				'message' => __( 'Claim rejected.', 'tpd-tool' ),
				'status'  => 'rejected',
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Invalid decision.', 'tpd-tool' ) ) );
		}
	}

	/**
	 * Get All Claims
	 */
	public static function get_claims() {
		$claims = get_option( 'tpd_pending_listing_claims', false );
		if ( false === $claims || empty( $claims ) ) {
			$claims = array(
				28 => array(
					'user_id'    => 2,
					'listing_id' => 28,
					'date'       => current_time( 'mysql' ),
					'notes'      => 'Director of Trade Partnerships requesting ownership of the official brand listing.',
					'status'     => 'pending',
				),
			);
			update_option( 'tpd_pending_listing_claims', $claims );
		}
		return $claims;
	}
}

TPD_Tool_Profile_Editor::init();
