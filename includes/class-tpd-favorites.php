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
}

TPD_Tool_Favorites::init();
