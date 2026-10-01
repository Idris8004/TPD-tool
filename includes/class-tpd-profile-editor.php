<?php
/**
 * Frontend Profile & Listing Editor for TPD Tool
 * Enables Travel Advisors and Suppliers to manage their profile pictures,
 * agency logos, banners, brochures, team members, and listing content directly from the frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Profile_Editor {

	public static function init() {
		add_action( 'wp_ajax_tpd_save_advisor_profile', array( __CLASS__, 'ajax_save_advisor_profile' ) );
		add_action( 'wp_ajax_tpd_save_supplier_listing', array( __CLASS__, 'ajax_save_supplier_listing' ) );
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

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		if ( empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file was uploaded.', 'tpd-tool' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_overrides = array( 'test_form' => false );
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
	 * AJAX: Save Advisor Profile Updates
	 */
	public static function ajax_save_advisor_profile() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$display_name = trim( $first_name . ' ' . $last_name );
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$agency_name  = isset( $_POST['agency_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agency_name'] ) ) : '';
		$location     = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$website      = isset( $_POST['website'] ) ? esc_url_raw( wp_unslash( $_POST['website'] ) ) : '';
		$bio          = isset( $_POST['bio'] ) ? wp_kses_post( wp_unslash( $_POST['bio'] ) ) : '';
		$headshot_url = isset( $_POST['headshot_url'] ) ? esc_url_raw( wp_unslash( $_POST['headshot_url'] ) ) : '';
		$logo_url     = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$banner_url   = isset( $_POST['banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['banner_url'] ) ) : '';

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
		if ( $phone ) update_user_meta( $user_id, 'tpd_phone', $phone );

		// Find or update paired travel_advisor CPT
		$query = get_posts( array(
			'post_type'      => 'travel_advisor',
			'posts_per_page' => 1,
			'meta_key'       => 'tpd_assigned_user',
			'meta_value'     => $user_id,
			'fields'         => 'ids',
		) );

		if ( ! empty( $query ) ) {
			$post_id = $query[0];
			wp_update_post( array(
				'ID'           => $post_id,
				'post_title'   => $display_name ?: get_the_title( $post_id ),
				'post_content' => $bio,
			) );

			update_post_meta( $post_id, 'tpd_agency_name', $agency_name );
			update_post_meta( $post_id, 'tpd_phone', $phone );
			update_post_meta( $post_id, 'tpd_location', $location );
			update_post_meta( $post_id, 'tpd_website', $website );
			update_post_meta( $post_id, 'tpd_headshot_url', $headshot_url );
			update_post_meta( $post_id, 'tpd_logo_url', $logo_url );
			update_post_meta( $post_id, 'tpd_banner_url', $banner_url );

			if ( ! empty( $_POST['destinations'] ) ) {
				$dests = array_map( 'sanitize_text_field', (array) $_POST['destinations'] );
				wp_set_object_terms( $post_id, $dests, 'travel_destination' );
			}
			if ( ! empty( $_POST['travel_styles'] ) ) {
				$styles = array_map( 'sanitize_text_field', (array) $_POST['travel_styles'] );
				wp_set_object_terms( $post_id, $styles, 'travel_style' );
			}
		}

		wp_send_json_success( array(
			'message'      => __( 'Advisor profile updated successfully!', 'tpd-tool' ),
			'display_name' => $display_name,
			'headshot_url' => $headshot_url,
		) );
	}

	/**
	 * AJAX: Save Supplier Listing Showcase Content
	 */
	public static function ajax_save_supplier_listing() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Authentication required.', 'tpd-tool' ) ) );
		}

		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		if ( ! $listing_id ) {
			// Create new listing if none exists
			$listing_id = wp_insert_post( array(
				'post_type'    => 'supplier_listing',
				'post_title'   => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : 'New Supplier Listing',
				'post_status'  => 'publish',
				'post_author'  => $user_id,
			) );
			update_post_meta( $listing_id, 'tpd_assigned_user', $user_id );
		}

		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$tagline      = isset( $_POST['tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['tagline'] ) ) : '';
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$booking_url  = isset( $_POST['booking_url'] ) ? esc_url_raw( wp_unslash( $_POST['booking_url'] ) ) : '';
		$rewards      = isset( $_POST['rewards'] ) ? sanitize_text_field( wp_unslash( $_POST['rewards'] ) ) : 'No';
		$ustoa        = isset( $_POST['ustoa'] ) ? sanitize_text_field( wp_unslash( $_POST['ustoa'] ) ) : 'No';
		$asta         = isset( $_POST['asta'] ) ? sanitize_text_field( wp_unslash( $_POST['asta'] ) ) : 'No';
		$description  = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
		$logo_url     = isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '';
		$banner_url   = isset( $_POST['banner_url'] ) ? esc_url_raw( wp_unslash( $_POST['banner_url'] ) ) : '';
		$video_url    = isset( $_POST['video_url'] ) ? esc_url_raw( wp_unslash( $_POST['video_url'] ) ) : '';

		wp_update_post( array(
			'ID'           => $listing_id,
			'post_title'   => $company_name ?: get_the_title( $listing_id ),
			'post_content' => $description,
		) );

		update_post_meta( $listing_id, 'tpd_tagline', $tagline );
		update_post_meta( $listing_id, 'tpd_primary_rep_phone', $phone );
		update_post_meta( $listing_id, 'tpd_booking_portal_url', $booking_url );
		update_post_meta( $listing_id, 'tpd_agent_rewards', $rewards );
		update_post_meta( $listing_id, 'tpd_member_ustoa', $ustoa );
		update_post_meta( $listing_id, 'tpd_member_asta', $asta );
		update_post_meta( $listing_id, 'tpd_logo_url', $logo_url );
		update_post_meta( $listing_id, 'tpd_banner_url', $banner_url );
		update_post_meta( $listing_id, 'tpd_main_video_url', $video_url );

		wp_send_json_success( array(
			'message'    => __( 'Supplier Listing & Showcase updated successfully!', 'tpd-tool' ),
			'listing_id' => $listing_id,
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

		$listing_id = isset( $_POST['listing_id'] ) ? absint( $_POST['listing_id'] ) : 0;
		$member_name = isset( $_POST['member_name'] ) ? sanitize_text_field( wp_unslash( $_POST['member_name'] ) ) : '';
		$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone       = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$photo_url   = isset( $_POST['photo_url'] ) ? esc_url_raw( wp_unslash( $_POST['photo_url'] ) ) : '';

		if ( ! $listing_id || empty( $member_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Listing ID and Member Name are required.', 'tpd-tool' ) ) );
		}

		$team = (array) get_post_meta( $listing_id, '_tpd_team_members', true );
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
			// Assign ownership of the supplier listing CPT to the claiming supplier user
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
	 * Get All Claims with Fallback Demonstration Claims
	 */
	public static function get_claims() {
		$claims = get_option( 'tpd_pending_listing_claims', false );
		if ( false === $claims || empty( $claims ) ) {
			// Seed a sample demonstration claim so Super Admin review queue is immediately visible
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
