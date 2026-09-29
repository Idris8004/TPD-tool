<?php
/**
 * Role Management for TPD Tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Roles {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'ensure_roles' ) );
	}

	public static function register_roles() {
		// Travel Advisor Role
		add_role(
			'travel_advisor',
			__( 'Travel Advisor', 'tpd-tool' ),
			array(
				'read'         => true,
				'upload_files' => true,
				'edit_posts'   => false,
				'delete_posts' => false,
			)
		);

		// Supplier Role
		add_role(
			'supplier',
			__( 'Supplier', 'tpd-tool' ),
			array(
				'read'         => true,
				'upload_files' => true,
				'edit_posts'   => true,
				'delete_posts' => false,
			)
		);
	}

	public static function ensure_roles() {
		if ( ! get_role( 'travel_advisor' ) || ! get_role( 'supplier' ) ) {
			self::register_roles();
		}
	}

	public static function is_advisor( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && in_array( 'travel_advisor', (array) $user->roles, true );
	}

	public static function is_supplier( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		return $user && in_array( 'supplier', (array) $user->roles, true );
	}

	public static function is_admin( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}
		return user_can( $user_id, 'manage_options' );
	}
}

TPD_Tool_Roles::init();
