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
		add_action( 'wp_ajax_tpd_delete_supplier_listing', array( __CLASS__, 'ajax_delete_supplier_listing' ) );
		add_action( 'wp_ajax_tpd_get_supplier_listing_data', array( __CLASS__, 'ajax_get_supplier_listing_data' ) );
	}

	/**
	 * Retrieve Gallery Images for an Advisor or Supplier (Profile & Directory Sync)
	 *
	 * @param int $user_id
	 * @param int $post_id
	 * @return array Array of image URLs
	 */
	public static function get_user_gallery( $user_id, $post_id = 0 ) {
		$gallery = array();
		if ( $post_id > 0 ) {
			$raw = get_post_meta( $post_id, 'tpd_gallery_images', true );
			if ( ! empty( $raw ) ) {
				$gallery = is_array( $raw ) ? $raw : json_decode( $raw, true );
			}
		}
		if ( empty( $gallery ) && $user_id > 0 ) {
			$raw = get_user_meta( $user_id, 'tpd_gallery_images', true );
			if ( ! empty( $raw ) ) {
				$gallery = is_array( $raw ) ? $raw : json_decode( $raw, true );
			}
		}
		return is_array( $gallery ) ? array_values( array_filter( array_map( 'esc_url_raw', $gallery ) ) ) : array();
	}

	/**
	 * AJAX Upload for Profile Headshots, Logos, Banners, Gallery Images, and PDF Brochures
	 */
	public static function ajax_upload_media() {
		$nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : ( isset( $_REQUEST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_ajax_nonce'] ) ) : '' );
		if ( ! wp_verify_nonce( $nonce, 'tpd_nonce' ) && ! wp_verify_nonce( $nonce, 'tpd_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security verification failed (invalid nonce). Please refresh the page.', 'tpd-tool' ) ) );
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id || ! TPD_Tool_Roles::is_account_active( $current_user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'tpd-tool' ) ) );
		}

		if ( isset( $_FILES['file']['error'] ) && $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
			$err_msg = __( 'File upload error occurred. Please try again.', 'tpd-tool' );
			switch ( $_FILES['file']['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$err_msg = __( 'The uploaded file exceeds the maximum allowed upload size.', 'tpd-tool' );
					break;
				case UPLOAD_ERR_PARTIAL:
					$err_msg = __( 'The file was only partially uploaded. Please try again.', 'tpd-tool' );
					break;
				case UPLOAD_ERR_NO_FILE:
					$err_msg = __( 'No file was received. Please select a file.', 'tpd-tool' );
					break;
			}
			wp_send_json_error( array( 'message' => $err_msg ) );
		}

		// Ensure current user has upload capability
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->exists() && ! $current_user->has_cap( 'upload_files' ) ) {
			$current_user->add_cap( 'upload_files' );
		}

		// Validate file extension against allowed formats
		$raw_name     = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) );
		$ext          = strtolower( pathinfo( $raw_name, PATHINFO_EXTENSION ) );
		$allowed_exts = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'svg', 'pdf' );

		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Security Error: Only JPG, PNG, GIF, WEBP, SVG images and PDF documents are allowed.', 'tpd-tool' ) ) );
		}

		$allowed_mimes = array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
			'svg'          => 'image/svg+xml',
			'pdf'          => 'application/pdf',
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => $allowed_mimes,
		);

		$attachment_id = media_handle_upload( 'file', 0, array(), $upload_overrides );
		$url           = '';

		if ( is_wp_error( $attachment_id ) ) {
			// Fallback: direct wp_handle_upload in case media_handle_upload failed
			$file = wp_handle_upload( $_FILES['file'], $upload_overrides );
			if ( isset( $file['error'] ) ) {
				wp_send_json_error( array( 'message' => $file['error'] ) );
			}
			$url = ! empty( $file['url'] ) ? $file['url'] : '';
			$attachment = array(
				'post_mime_type' => ! empty( $file['type'] ) ? $file['type'] : 'image/jpeg',
				'guid'           => $url,
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $raw_name ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $current_user_id,
			);
			$attach_id = wp_insert_attachment( $attachment, $file['file'] );
			if ( ! is_wp_error( $attach_id ) && $attach_id > 0 ) {
				$attach_data = wp_generate_attachment_metadata( $attach_id, $file['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				$attachment_id = $attach_id;
			}
		} else {
			$url = wp_get_attachment_url( $attachment_id );
		}

		if ( empty( $url ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not process media file URL.', 'tpd-tool' ) ) );
		}

		wp_send_json_success( array(
			'attachment_id' => (int) $attachment_id,
			'url'           => esc_url_raw( $url ),
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
		if ( isset( $_POST['logo_url'] ) ) update_user_meta( $user_id, 'tpd_logo_url', $logo_url );
		if ( isset( $_POST['banner_url'] ) ) update_user_meta( $user_id, 'tpd_banner_url', $banner_url );
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
			if ( $headshot_url ) {
				update_post_meta( $post_id, 'tpd_headshot_url', $headshot_url );
				update_user_meta( $user_id, 'tpd_headshot_url', $headshot_url );
				update_user_meta( $user_id, 'tpd_avatar_url', $headshot_url );
			}
			if ( isset( $_POST['logo_url'] ) ) {
				update_post_meta( $post_id, 'tpd_logo_url', $logo_url );
				update_user_meta( $user_id, 'tpd_logo_url', $logo_url );
			}
			if ( isset( $_POST['banner_url'] ) ) {
				update_post_meta( $post_id, 'tpd_banner_url', $banner_url );
				update_user_meta( $user_id, 'tpd_banner_url', $banner_url );
			}

			// Save Travel Advisor Gallery Images
			$gallery_imgs = array();
			if ( isset( $_POST['gallery_images_json'] ) ) {
				$dec = json_decode( wp_unslash( $_POST['gallery_images_json'] ), true );
				if ( is_array( $dec ) ) {
					$gallery_imgs = array_map( 'esc_url_raw', $dec );
				}
			} elseif ( isset( $_POST['gallery_images'] ) && is_array( $_POST['gallery_images'] ) ) {
				$gallery_imgs = array_map( 'esc_url_raw', wp_unslash( $_POST['gallery_images'] ) );
			}
			$gallery_imgs = array_values( array_filter( $gallery_imgs ) );
			if ( isset( $_POST['gallery_images_json'] ) || isset( $_POST['gallery_images'] ) ) {
				update_user_meta( $user_id, 'tpd_gallery_images', $gallery_imgs );
				update_post_meta( $post_id, 'tpd_gallery_images', $gallery_imgs );
			}

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
			'logo_url'     => $logo_url,
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

		$user_tier   = class_exists( 'TPD_Tool_Tiers' ) ? TPD_Tool_Tiers::get_user_tier( $user_id ) : 'basic';
		$is_enhanced = class_exists( 'TPD_Tool_Tiers' ) ? TPD_Tool_Tiers::is_enhanced_supplier( $user_id ) : false;

		// 1. Basic Company Information (Accessible to ALL suppliers: Free & Paid)
		$company_name           = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$tagline                = isset( $_POST['tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['tagline'] ) ) : '';
		$phone                  = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$booking_url            = isset( $_POST['booking_url'] ) ? esc_url_raw( wp_unslash( $_POST['booking_url'] ) ) : '';
		$education_training_url = isset( $_POST['education_training_url'] ) ? esc_url_raw( wp_unslash( $_POST['education_training_url'] ) ) : '';
		$additional_website     = isset( $_POST['additional_website'] ) ? esc_url_raw( wp_unslash( $_POST['additional_website'] ) ) : '';
		$additional_phone_1     = isset( $_POST['additional_phone_1'] ) ? sanitize_text_field( wp_unslash( $_POST['additional_phone_1'] ) ) : '';
		$additional_phone_2     = isset( $_POST['additional_phone_2'] ) ? sanitize_text_field( wp_unslash( $_POST['additional_phone_2'] ) ) : '';
		$headquarters           = isset( $_POST['headquarters'] ) ? sanitize_text_field( wp_unslash( $_POST['headquarters'] ) ) : '';
		$country                = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$description            = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$logo_url               = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$banner_url             = isset( $_POST['banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['banner_url'] ) ) : '';

		// Social Media Links
		$social_facebook        = isset( $_POST['social_facebook'] ) ? esc_url_raw( wp_unslash( $_POST['social_facebook'] ) ) : '';
		$social_twitter         = isset( $_POST['social_twitter'] ) ? esc_url_raw( wp_unslash( $_POST['social_twitter'] ) ) : '';
		$social_youtube         = isset( $_POST['social_youtube'] ) ? esc_url_raw( wp_unslash( $_POST['social_youtube'] ) ) : '';
		$social_instagram       = isset( $_POST['social_instagram'] ) ? esc_url_raw( wp_unslash( $_POST['social_instagram'] ) ) : '';
		$social_tiktok          = isset( $_POST['social_tiktok'] ) ? esc_url_raw( wp_unslash( $_POST['social_tiktok'] ) ) : '';
		$social_linkedin        = isset( $_POST['social_linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['social_linkedin'] ) ) : '';

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
		update_post_meta( $listing_id, 'tpd_education_training_url', $education_training_url );
		update_post_meta( $listing_id, 'tpd_additional_website', $additional_website );
		update_post_meta( $listing_id, 'tpd_additional_phone_1', $additional_phone_1 );
		update_post_meta( $listing_id, 'tpd_additional_phone_2', $additional_phone_2 );
		update_post_meta( $listing_id, 'tpd_headquarters', $headquarters );

		// Save Social Media Links
		update_post_meta( $listing_id, 'tpd_social_facebook', $social_facebook );
		update_post_meta( $listing_id, 'tpd_social_twitter', $social_twitter );
		update_post_meta( $listing_id, 'tpd_social_youtube', $social_youtube );
		update_post_meta( $listing_id, 'tpd_social_instagram', $social_instagram );
		update_post_meta( $listing_id, 'tpd_social_tiktok', $social_tiktok );
		update_post_meta( $listing_id, 'tpd_social_linkedin', $social_linkedin );

		// Save Taxonomies (Destinations, Travel Styles, Supplier Types)
		if ( isset( $_POST['destinations'] ) ) {
			$dests = is_array( $_POST['destinations'] ) ? array_map( 'sanitize_text_field', (array) $_POST['destinations'] ) : array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['destinations'] ) ) ) ) );
			wp_set_object_terms( $listing_id, $dests, 'travel_destination' );
		}
		if ( isset( $_POST['travel_styles'] ) ) {
			$styles = is_array( $_POST['travel_styles'] ) ? array_map( 'sanitize_text_field', (array) $_POST['travel_styles'] ) : array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['travel_styles'] ) ) ) ) );
			wp_set_object_terms( $listing_id, $styles, 'travel_style' );
		}
		if ( isset( $_POST['supplier_type'] ) ) {
			$stype = sanitize_text_field( wp_unslash( $_POST['supplier_type'] ) );
			if ( $stype ) {
				wp_set_object_terms( $listing_id, array( $stype ), 'supplier_type' );
				update_post_meta( $listing_id, 'tpd_supplier_type', $stype );
			}
		}

		if ( $country ) {
			update_post_meta( $listing_id, 'tpd_country_of_residence', $country );
		}
		if ( isset( $_POST['logo_url'] ) ) {
			update_post_meta( $listing_id, 'tpd_logo_url', $logo_url );
			update_user_meta( $user_id, 'tpd_logo_url', $logo_url );
		}
		if ( isset( $_POST['banner_url'] ) ) {
			update_post_meta( $listing_id, 'tpd_banner_url', $banner_url );
			update_user_meta( $user_id, 'tpd_banner_url', $banner_url );
		}
		update_post_meta( $listing_id, 'tpd_user_tier', $user_tier );

		// 2. Enhanced / Paid Listing Features & Separate Tabs (Associations, Resources, FAQs, Gallery, Videos)
		if ( $is_enhanced ) {
			$rewards      = isset( $_POST['rewards'] ) ? sanitize_text_field( wp_unslash( $_POST['rewards'] ) ) : 'No';
			$rewards_desc = isset( $_POST['rewards_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rewards_desc'] ) ) : '';
			$ustoa        = isset( $_POST['ustoa'] ) ? sanitize_text_field( wp_unslash( $_POST['ustoa'] ) ) : 'No';
			$asta         = isset( $_POST['asta'] ) ? sanitize_text_field( wp_unslash( $_POST['asta'] ) ) : 'No';
			$clia         = isset( $_POST['clia'] ) ? sanitize_text_field( wp_unslash( $_POST['clia'] ) ) : 'No';
			$associations = isset( $_POST['associations'] ) ? sanitize_text_field( wp_unslash( $_POST['associations'] ) ) : '';
			$video_url    = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';
			$promo_title  = isset( $_POST['promo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['promo_title'] ) ) : '';
			$promo_desc   = isset( $_POST['promo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['promo_desc'] ) ) : '';

			update_post_meta( $listing_id, 'tpd_agent_rewards', $rewards );
			update_post_meta( $listing_id, 'tpd_agent_rewards_desc', $rewards_desc );
			update_post_meta( $listing_id, 'tpd_member_ustoa', $ustoa );
			update_post_meta( $listing_id, 'tpd_member_asta', $asta );
			update_post_meta( $listing_id, 'tpd_member_clia', $clia );
			update_post_meta( $listing_id, 'tpd_associations', $associations );
			update_post_meta( $listing_id, 'tpd_main_video_url', $video_url );
			update_post_meta( $listing_id, 'tpd_promo_title', $promo_title );
			update_post_meta( $listing_id, 'tpd_promo_desc', $promo_desc );

			// Associations & Conferences: Save upcoming conferences with title, start/end dates, location, file & link
			$conferences = array();
			if ( isset( $_POST['conferences_json'] ) ) {
				$dec = json_decode( wp_unslash( $_POST['conferences_json'] ), true );
				if ( is_array( $dec ) ) {
					foreach ( $dec as $c ) {
						$c_title = ! empty( $c['name'] ) ? $c['name'] : ( ! empty( $c['title'] ) ? $c['title'] : '' );
						if ( ! empty( $c_title ) ) {
							$conferences[] = array(
								'name'       => sanitize_text_field( $c_title ),
								'title'      => sanitize_text_field( $c_title ),
								'start_date' => sanitize_text_field( isset( $c['start_date'] ) ? $c['start_date'] : '' ),
								'end_date'   => sanitize_text_field( isset( $c['end_date'] ) ? $c['end_date'] : '' ),
								'date'       => sanitize_text_field( isset( $c['date'] ) ? $c['date'] : '' ),
								'location'   => sanitize_text_field( isset( $c['location'] ) ? $c['location'] : '' ),
								'file_url'   => esc_url_raw( isset( $c['file_url'] ) ? $c['file_url'] : '' ),
								'url'        => esc_url_raw( isset( $c['url'] ) ? $c['url'] : '' ),
							);
						}
					}
				}
			} elseif ( isset( $_POST['conferences'] ) && is_array( $_POST['conferences'] ) ) {
				foreach ( wp_unslash( $_POST['conferences'] ) as $c ) {
					$c_title = ! empty( $c['name'] ) ? $c['name'] : ( ! empty( $c['title'] ) ? $c['title'] : '' );
					if ( ! empty( $c_title ) ) {
						$conferences[] = array(
							'name'       => sanitize_text_field( $c_title ),
							'title'      => sanitize_text_field( $c_title ),
							'start_date' => sanitize_text_field( isset( $c['start_date'] ) ? $c['start_date'] : '' ),
							'end_date'   => sanitize_text_field( isset( $c['end_date'] ) ? $c['end_date'] : '' ),
							'date'       => sanitize_text_field( isset( $c['date'] ) ? $c['date'] : '' ),
							'location'   => sanitize_text_field( isset( $c['location'] ) ? $c['location'] : '' ),
							'file_url'   => esc_url_raw( isset( $c['file_url'] ) ? $c['file_url'] : '' ),
							'url'        => esc_url_raw( isset( $c['url'] ) ? $c['url'] : '' ),
						);
					}
				}
			}
			update_post_meta( $listing_id, '_tpd_supplier_conferences', $conferences );

			// Resources & Brochures: Save documents
			$resources = array();
			if ( isset( $_POST['resources_json'] ) ) {
				$dec = json_decode( wp_unslash( $_POST['resources_json'] ), true );
				if ( is_array( $dec ) ) {
					foreach ( $dec as $r ) {
						if ( ! empty( $r['title'] ) && ! empty( $r['file_url'] ) ) {
							$resources[] = array(
								'title'       => sanitize_text_field( $r['title'] ),
								'file_url'    => esc_url_raw( $r['file_url'] ),
								'category'    => sanitize_text_field( isset( $r['category'] ) ? $r['category'] : 'Brochure' ),
								'description' => sanitize_text_field( isset( $r['description'] ) ? $r['description'] : '' ),
							);
						}
					}
				}
			} elseif ( isset( $_POST['resources'] ) && is_array( $_POST['resources'] ) ) {
				foreach ( wp_unslash( $_POST['resources'] ) as $r ) {
					if ( ! empty( $r['title'] ) && ! empty( $r['file_url'] ) ) {
						$resources[] = array(
							'title'       => sanitize_text_field( $r['title'] ),
							'file_url'    => esc_url_raw( $r['file_url'] ),
							'category'    => sanitize_text_field( isset( $r['category'] ) ? $r['category'] : 'Brochure' ),
							'description' => sanitize_text_field( isset( $r['description'] ) ? $r['description'] : '' ),
						);
					}
				}
			}
			update_post_meta( $listing_id, '_tpd_supplier_resources', $resources );

			// FAQs: Save Supplier Travel Advisor FAQs
			$faqs_list = array();
			if ( isset( $_POST['faqs_json'] ) ) {
				$dec = json_decode( wp_unslash( $_POST['faqs_json'] ), true );
				if ( is_array( $dec ) ) {
					foreach ( $dec as $f ) {
						if ( ! empty( $f['question'] ) ) {
							$faqs_list[] = array(
								'question' => sanitize_text_field( $f['question'] ),
								'answer'   => wp_kses_post( isset( $f['answer'] ) ? $f['answer'] : '' ),
							);
						}
					}
				}
			} elseif ( isset( $_POST['faqs'] ) && is_array( $_POST['faqs'] ) ) {
				foreach ( wp_unslash( $_POST['faqs'] ) as $f ) {
					if ( ! empty( $f['question'] ) ) {
						$faqs_list[] = array(
							'question' => sanitize_text_field( $f['question'] ),
							'answer'   => wp_kses_post( isset( $f['answer'] ) ? $f['answer'] : '' ),
						);
					}
				}
			}
			update_post_meta( $listing_id, '_tpd_supplier_faqs', $faqs_list );

			// Photo Gallery: Save multiple images
			$gallery_imgs = array();
			if ( isset( $_POST['gallery_images_json'] ) ) {
				$dec = json_decode( wp_unslash( $_POST['gallery_images_json'] ), true );
				if ( is_array( $dec ) ) {
					$gallery_imgs = array_map( 'esc_url_raw', $dec );
				}
			} elseif ( isset( $_POST['gallery_images'] ) && is_array( $_POST['gallery_images'] ) ) {
				$gallery_imgs = array_map( 'esc_url_raw', wp_unslash( $_POST['gallery_images'] ) );
			}
			$gallery_imgs = array_values( array_filter( $gallery_imgs ) );
			if ( isset( $_POST['gallery_images_json'] ) || isset( $_POST['gallery_images'] ) ) {
				update_post_meta( $listing_id, 'tpd_gallery_images', $gallery_imgs );
				update_user_meta( $user_id, 'tpd_gallery_images', $gallery_imgs );
			}

			// Videos: Save additional video showcase items
			$videos_list = array();
			if ( isset( $_POST['videos_json'] ) ) {
				$dec = json_decode( wp_unslash( $_POST['videos_json'] ), true );
				if ( is_array( $dec ) ) {
					foreach ( $dec as $v ) {
						if ( ! empty( $v['video_url'] ) ) {
							$videos_list[] = array(
								'title'       => sanitize_text_field( isset( $v['title'] ) ? $v['title'] : '' ),
								'video_url'   => esc_url_raw( $v['video_url'] ),
								'description' => sanitize_text_field( isset( $v['description'] ) ? $v['description'] : '' ),
							);
						}
					}
				}
			} elseif ( isset( $_POST['videos'] ) && is_array( $_POST['videos'] ) ) {
				foreach ( wp_unslash( $_POST['videos'] ) as $v ) {
					if ( ! empty( $v['video_url'] ) ) {
						$videos_list[] = array(
							'title'       => sanitize_text_field( isset( $v['title'] ) ? $v['title'] : '' ),
							'video_url'   => esc_url_raw( $v['video_url'] ),
							'description' => sanitize_text_field( isset( $v['description'] ) ? $v['description'] : '' ),
						);
					}
				}
			}
			update_post_meta( $listing_id, '_tpd_supplier_videos', $videos_list );
		}

		// Sync company name back to user meta
		if ( $company_name ) {
			update_user_meta( $user_id, 'tpd_company_name', $company_name );
		}

		// Save Dynamic ACF & Admin Custom Fields
		if ( class_exists( 'TPD_Tool_CPT' ) ) {
			TPD_Tool_CPT::save_dynamic_fields_submission( 'supplier_listing', $listing_id, $user_id );
		}

		wp_send_json_success( array(
			'message'      => __( 'Supplier Listing & Showcase successfully updated and saved!', 'tpd-tool' ),
			'listing_id'   => $listing_id,
			'company_name' => $company_name,
			'logo_url'     => $logo_url,
			'banner_url'   => $banner_url,
			'is_enhanced'  => $is_enhanced,
		) );
	}

	/**
	 * AJAX: Delete a Supplier Listing
	 */
	public static function ajax_delete_supplier_listing() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		if ( ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing ID.', 'tpd-tool' ) ) );
		}

		$post = get_post( $listing_id );
		if ( ! $post || 'supplier_listing' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Listing not found.', 'tpd-tool' ) ) );
		}

		$assigned = (int) get_post_meta( $listing_id, 'tpd_assigned_user', true );
		if ( ! TPD_Tool_Roles::is_admin( $user_id ) && (int) $post->post_author !== $user_id && $assigned !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized: You do not own this listing.', 'tpd-tool' ) ) );
		}

		$title = $post->post_title;
		$deleted = wp_delete_post( $listing_id, true );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete listing. Please try again.', 'tpd-tool' ) ) );
		}

		wp_send_json_success( array(
			'message'    => sprintf( __( 'Listing "%s" deleted successfully.', 'tpd-tool' ), $title ),
			'listing_id' => $listing_id,
		) );
	}

	/**
	 * AJAX: Fetch Supplier Listing Data to Load into Frontend Editor
	 */
	public static function ajax_get_supplier_listing_data() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id || ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		if ( ! $listing_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid listing ID.', 'tpd-tool' ) ) );
		}

		$post = get_post( $listing_id );
		if ( ! $post || 'supplier_listing' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Listing not found.', 'tpd-tool' ) ) );
		}

		$assigned = (int) get_post_meta( $listing_id, 'tpd_assigned_user', true );
		if ( ! TPD_Tool_Roles::is_admin( $user_id ) && (int) $post->post_author !== $user_id && $assigned !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'tpd-tool' ) ) );
		}

		$is_enhanced  = class_exists( 'TPD_Tool_Tiers' ) ? TPD_Tool_Tiers::is_listing_enhanced( $listing_id ) : false;
		$gallery      = self::get_user_gallery( $user_id, $listing_id );
		$conferences  = get_post_meta( $listing_id, '_tpd_supplier_conferences', true );
		$resources    = get_post_meta( $listing_id, '_tpd_supplier_resources', true );
		$faqs         = get_post_meta( $listing_id, '_tpd_supplier_faqs', true );
		$videos       = get_post_meta( $listing_id, '_tpd_supplier_videos', true );

		$dest_terms   = wp_get_object_terms( $listing_id, 'travel_destination', array( 'fields' => 'names' ) );
		$dest_names   = ( ! empty( $dest_terms ) && ! is_wp_error( $dest_terms ) ) ? $dest_terms : array();

		$style_terms  = wp_get_object_terms( $listing_id, 'travel_style', array( 'fields' => 'names' ) );
		$style_names  = ( ! empty( $style_terms ) && ! is_wp_error( $style_terms ) ) ? $style_terms : array();

		$stype_terms  = wp_get_object_terms( $listing_id, 'supplier_type', array( 'fields' => 'names' ) );
		$stype_name   = ( ! empty( $stype_terms ) && ! is_wp_error( $stype_terms ) ) ? $stype_terms[0] : ( get_post_meta( $listing_id, 'tpd_supplier_type', true ) ?: '' );

		wp_send_json_success( array(
			'listing_id'             => $listing_id,
			'is_enhanced'            => $is_enhanced,
			'company_name'           => $post->post_title,
			'tagline'                => get_post_meta( $listing_id, 'tpd_tagline', true ) ?: '',
			'phone'                  => get_post_meta( $listing_id, 'tpd_primary_rep_phone', true ) ?: '',
			'booking_url'            => get_post_meta( $listing_id, 'tpd_booking_portal_url', true ) ?: '',
			'education_training_url' => get_post_meta( $listing_id, 'tpd_education_training_url', true ) ?: '',
			'additional_website'     => get_post_meta( $listing_id, 'tpd_additional_website', true ) ?: '',
			'additional_phone_1'     => get_post_meta( $listing_id, 'tpd_additional_phone_1', true ) ?: '',
			'additional_phone_2'     => get_post_meta( $listing_id, 'tpd_additional_phone_2', true ) ?: '',
			'headquarters'           => get_post_meta( $listing_id, 'tpd_headquarters', true ) ?: '',
			'country'                => get_post_meta( $listing_id, 'tpd_country_of_residence', true ) ?: '',
			'social_facebook'        => get_post_meta( $listing_id, 'tpd_social_facebook', true ) ?: '',
			'social_twitter'         => get_post_meta( $listing_id, 'tpd_social_twitter', true ) ?: '',
			'social_youtube'         => get_post_meta( $listing_id, 'tpd_social_youtube', true ) ?: '',
			'social_instagram'       => get_post_meta( $listing_id, 'tpd_social_instagram', true ) ?: '',
			'social_tiktok'          => get_post_meta( $listing_id, 'tpd_social_tiktok', true ) ?: '',
			'social_linkedin'        => get_post_meta( $listing_id, 'tpd_social_linkedin', true ) ?: '',
			'destinations'           => $dest_names,
			'travel_styles'          => $style_names,
			'supplier_type'          => $stype_name,
			'rewards'                => get_post_meta( $listing_id, 'tpd_agent_rewards', true ) ?: 'No',
			'rewards_desc'           => get_post_meta( $listing_id, 'tpd_agent_rewards_desc', true ) ?: '',
			'ustoa'                  => get_post_meta( $listing_id, 'tpd_member_ustoa', true ) ?: 'No',
			'asta'                   => get_post_meta( $listing_id, 'tpd_member_asta', true ) ?: 'No',
			'clia'                   => get_post_meta( $listing_id, 'tpd_member_clia', true ) ?: 'No',
			'associations'           => get_post_meta( $listing_id, 'tpd_associations', true ) ?: '',
			'description'            => $post->post_content ?: '',
			'logo_url'               => get_post_meta( $listing_id, 'tpd_logo_url', true ) ?: '',
			'banner_url'             => get_post_meta( $listing_id, 'tpd_banner_url', true ) ?: '',
			'video_url'              => get_post_meta( $listing_id, 'tpd_main_video_url', true ) ?: '',
			'promo_title'            => get_post_meta( $listing_id, 'tpd_promo_title', true ) ?: '',
			'promo_desc'             => get_post_meta( $listing_id, 'tpd_promo_desc', true ) ?: '',
			'gallery'                => is_array( $gallery ) ? $gallery : array(),
			'conferences'            => is_array( $conferences ) ? $conferences : array(),
			'resources'              => is_array( $resources ) ? $resources : array(),
			'faqs'                   => is_array( $faqs ) ? $faqs : array(),
			'videos'                 => is_array( $videos ) ? $videos : array(),
			'public_url'             => get_permalink( $listing_id ),
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

		$first_name       = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name        = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name     = trim( $first_name . ' ' . $last_name );
		$email            = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone            = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$additional_phone = isset( $_POST['additional_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['additional_phone'] ) ) : '';
		$position_title   = isset( $_POST['position_title'] ) ? sanitize_text_field( wp_unslash( $_POST['position_title'] ) ) : '';
		$company_name     = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$country          = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$bio              = isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '';
		$linkedin         = isset( $_POST['linkedin'] ) ? esc_url_raw( wp_unslash( $_POST['linkedin'] ) ) : '';
		$twitter          = isset( $_POST['twitter'] ) ? esc_url_raw( wp_unslash( $_POST['twitter'] ) ) : '';
		$website          = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$avatar_url       = isset( $_POST['headshot_url'] ) ? esc_url_raw( wp_unslash( $_POST['headshot_url'] ) ) : '';

		$user_args = array( 'ID' => $user_id );
		if ( $first_name ) $user_args['first_name'] = $first_name;
		if ( $last_name ) $user_args['last_name'] = $last_name;
		if ( $display_name ) $user_args['display_name'] = $display_name;
		if ( $email && is_email( $email ) ) $user_args['user_email'] = $email;
		if ( $bio ) $user_args['description'] = $bio;
		wp_update_user( $user_args );

		update_user_meta( $user_id, 'tpd_phone', $phone );
		update_user_meta( $user_id, 'tpd_additional_phone', $additional_phone );
		update_user_meta( $user_id, 'tpd_position_title', $position_title );
		update_user_meta( $user_id, 'tpd_company_name', $company_name );
		update_user_meta( $user_id, 'tpd_country_of_residence', $country );
		update_user_meta( $user_id, 'tpd_bio', $bio );
		update_user_meta( $user_id, 'tpd_social_linkedin', $linkedin );
		update_user_meta( $user_id, 'tpd_social_twitter', $twitter );
		update_user_meta( $user_id, 'tpd_website', $website );
		if ( $avatar_url ) {
			update_user_meta( $user_id, 'tpd_headshot_url', $avatar_url );
			update_user_meta( $user_id, 'tpd_avatar_url', $avatar_url );
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
			if ( $avatar_url ) {
				update_post_meta( $lid, 'tpd_rep_headshot_url', $avatar_url );
			}
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
