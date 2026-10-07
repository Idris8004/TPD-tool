<?php
/**
 * Role Management & Portal Access Security Guard for TPD Tool
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
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( 'travel_advisor', $roles, true );
	}

	public static function is_supplier( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) $user->roles;
		return in_array( 'supplier', $roles, true ) || in_array( 'supplier_partner', $roles, true );
	}

	public static function is_dual_role( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		return self::is_advisor( $user_id ) && self::is_supplier( $user_id );
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

	public static function is_account_active( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( ! $user_id ) {
			return false;
		}
		$status = get_user_meta( $user_id, 'tpd_account_status', true );
		return ! in_array( $status, array( 'suspended', 'inactive' ), true );
	}

	/**
	 * Check if Elementor Editor / Preview is active for an authorized admin
	 */
	public static function is_elementor_editor_context() {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return false;
		}
		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'elementor', 'elementor_ajax' ), true ) ) {
			return true;
		}
		if ( isset( $_GET['elementor-preview'] ) ) {
			return true;
		}
		if ( defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' ) ) {
			$instance = \Elementor\Plugin::$instance;
			if ( ( isset( $instance->editor ) && $instance->editor->is_edit_mode() ) || ( isset( $instance->preview ) && $instance->preview->is_preview_mode() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Strict Portal Access Guard
	 * Prevents unauthenticated access, enforces role isolation between Advisor and Supplier portals,
	 * and blocks suspended/inactive accounts.
	 *
	 * @param string $portal_type 'advisor' | 'supplier' | 'admin'
	 */
	public static function enforce_portal_access( $portal_type = 'advisor' ) {
		if ( self::is_elementor_editor_context() ) {
			return;
		}

		nocache_headers();

		// 1. Must be logged in
		if ( ! is_user_logged_in() ) {
			if ( 'supplier' === $portal_type ) {
				wp_safe_redirect( add_query_arg( 'auth_required', '1', home_url( '/supplier-login/' ) ) );
				exit;
			} elseif ( 'admin' === $portal_type ) {
				wp_safe_redirect( wp_login_url( home_url( '/tpd-admin/' ) ) );
				exit;
			} else {
				wp_safe_redirect( add_query_arg( 'auth_required', '1', home_url( '/advisor-login/' ) ) );
				exit;
			}
		}

		$user_id = get_current_user_id();

		// 2. Super Admin always has access to all portals
		if ( self::is_admin( $user_id ) ) {
			return;
		}

		// 3. Check if account is suspended or deactivated by Super Admin
		if ( ! self::is_account_active( $user_id ) ) {
			wp_logout();
			$login_url = ( 'supplier' === $portal_type ) ? home_url( '/supplier-login/' ) : home_url( '/advisor-login/' );
			wp_safe_redirect( add_query_arg( 'account_suspended', '1', $login_url ) );
			exit;
		}

		// 4. Super Admin Portal check
		if ( 'admin' === $portal_type ) {
			if ( self::is_advisor( $user_id ) ) {
				wp_safe_redirect( home_url( '/advisor-dashboard/' ) );
				exit;
			}
			if ( self::is_supplier( $user_id ) ) {
				wp_safe_redirect( home_url( '/supplier-dashboard/' ) );
				exit;
			}
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		// 5. Strict Role Isolation: Advisor Portal requires travel_advisor role
		if ( 'advisor' === $portal_type ) {
			if ( ! self::is_advisor( $user_id ) ) {
				// User is logged in as Supplier only — redirect to Supplier Dashboard with cross-role prompt
				if ( self::is_supplier( $user_id ) ) {
					wp_safe_redirect( add_query_arg( 'cross_role_notice', 'advisor', home_url( '/supplier-dashboard/' ) ) );
					exit;
				}
				wp_safe_redirect( add_query_arg( 'role_restricted', 'advisor', home_url( '/advisor-login/' ) ) );
				exit;
			}
		}

		// 6. Strict Role Isolation: Supplier Portal requires supplier role
		if ( 'supplier' === $portal_type ) {
			if ( ! self::is_supplier( $user_id ) ) {
				// User is logged in as Advisor only — redirect to Advisor Dashboard with cross-role prompt
				if ( self::is_advisor( $user_id ) ) {
					wp_safe_redirect( add_query_arg( 'cross_role_notice', 'supplier', home_url( '/advisor-dashboard/' ) ) );
					exit;
				}
				wp_safe_redirect( add_query_arg( 'role_restricted', 'supplier', home_url( '/supplier-login/' ) ) );
				exit;
			}
		}
	}
}

TPD_Tool_Roles::init();
