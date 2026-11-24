<?php
/**
 * Self-Contained GitHub Auto-Updater for TPD Tool
 * Checks https://github.com/TARC-LLC/dashboard (main branch) for new versions
 * and integrates with native WordPress Plugin Updates & Auto-Updates.
 * Supports private repository access via TPD_GITHUB_TOKEN defined in wp-config.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Updater {

	const GITHUB_USER   = 'TARC-LLC';
	const GITHUB_REPO   = 'dashboard';
	const GITHUB_BRANCH = 'main';

	private static $instance = null;
	private $plugin_slug;
	private $plugin_basename;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Retrieve read-only GitHub Personal Access Token configured in wp-config.php (or option fallback).
	 */
	public static function get_token() {
		if ( defined( 'TPD_GITHUB_TOKEN' ) && ! empty( TPD_GITHUB_TOKEN ) ) {
			return trim( TPD_GITHUB_TOKEN );
		}
		if ( defined( 'TPD_GH_TOKEN' ) && ! empty( TPD_GH_TOKEN ) ) {
			return trim( TPD_GH_TOKEN );
		}
		return trim( (string) get_option( 'tpd_github_token', '' ) );
	}

	private function __construct() {
		$this->plugin_basename = plugin_basename( TPD_TOOL_FILE );
		$this->plugin_slug     = dirname( $this->plugin_basename );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_Popup_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_github_folder_name' ), 10, 4 );
		add_filter( 'http_request_args', array( $this, 'attach_github_token_to_download' ), 10, 2 );
		add_filter( 'auto_update_plugin', array( $this, 'enable_auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_plugin_action_links' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_update_check' ) );
	}

	/**
	 * Fetch remote version from TARC-LLC/dashboard on GitHub main branch
	 */
	public function get_remote_version( $force = false ) {
		$transient_key = 'tpd_tool_gh_remote_version';
		if ( ! $force ) {
			$cached = get_transient( $transient_key );
			if ( ! empty( $cached ) ) {
				return $cached;
			}
		}

		$token   = self::get_token();
		$headers = array(
			'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			'Accept'        => 'application/vnd.github.v3.raw',
			'Cache-Control' => 'no-cache',
		);
		if ( ! empty( $token ) ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		// 1. Direct GitHub REST API raw contents call (recommended for private repos)
		$api_url = sprintf(
			'https://api.github.com/repos/%s/%s/contents/tpd-tool.php?ref=%s',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			self::GITHUB_BRANCH
		);

		$response = wp_remote_get( $api_url, array(
			'timeout' => 12,
			'headers' => $headers,
		) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );
			if ( preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/mi', $body, $matches ) ) {
				$remote_version = trim( $matches[1] );
				set_transient( $transient_key, $remote_version, 15 * MINUTE_IN_SECONDS );
				return $remote_version;
			}
		}

		// 2. Fallback to raw.githubusercontent.com with Authorization header
		$raw_url = sprintf(
			'https://raw.githubusercontent.com/%s/%s/%s/tpd-tool.php?t=%d',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			self::GITHUB_BRANCH,
			time()
		);
		$raw_headers = array(
			'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			'Cache-Control' => 'no-cache',
		);
		if ( ! empty( $token ) ) {
			$raw_headers['Authorization'] = 'Bearer ' . $token;
		}

		$raw_response = wp_remote_get( $raw_url, array(
			'timeout' => 10,
			'headers' => $raw_headers,
		) );

		if ( ! is_wp_error( $raw_response ) && 200 === wp_remote_retrieve_response_code( $raw_response ) ) {
			$body = wp_remote_retrieve_body( $raw_response );
			if ( preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/mi', $body, $matches ) ) {
				$remote_version = trim( $matches[1] );
				set_transient( $transient_key, $remote_version, 15 * MINUTE_IN_SECONDS );
				return $remote_version;
			}
		}

		return false;
	}

	/**
	 * Inject update into WordPress update_plugins transient
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_version = $this->get_remote_version();
		if ( ! $remote_version ) {
			return $transient;
		}

		if ( version_compare( TPD_TOOL_VERSION, $remote_version, '<' ) ) {
			$zip_url = sprintf(
				'https://api.github.com/repos/%s/%s/zipball/%s',
				self::GITHUB_USER,
				self::GITHUB_REPO,
				self::GITHUB_BRANCH
			);

			$update_obj              = new stdClass();
			$update_obj->slug        = $this->plugin_slug;
			$update_obj->plugin      = $this->plugin_basename;
			$update_obj->new_version = $remote_version;
			$update_obj->url         = sprintf( 'https://github.com/%s/%s', self::GITHUB_USER, self::GITHUB_REPO );
			$update_obj->package     = $zip_url;
			$update_obj->tested      = get_bloginfo( 'version' );

			$transient->response[ $this->plugin_basename ] = $update_obj;
		}

		return $transient;
	}

	/**
	 * Provide plugin information modal in WP Admin
	 */
	public function plugin_Popup_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $res;
		}

		$remote_version = $this->get_remote_version();

		$info                = new stdClass();
		$info->name          = 'TPD Tool - Travel Partner Directory Enterprise Suite';
		$info->slug          = $this->plugin_slug;
		$info->version       = $remote_version ? $remote_version : TPD_TOOL_VERSION;
		$info->author        = '<a href="https://github.com/TARC-LLC/dashboard">Travel Partner Directory (TARC)</a>';
		$info->homepage      = 'https://github.com/TARC-LLC/dashboard';
		$info->download_link = sprintf(
			'https://api.github.com/repos/%s/%s/zipball/%s',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			self::GITHUB_BRANCH
		);
		$info->sections      = array(
			'description' => 'Enterprise-grade travel marketplace suite featuring Multi-Step Registration Wizards, Dual Portals (Advisor & Supplier Dashboards), Bi-Directional WP CPT + ACF Sync, Dynamic Membership Plan Builder, Stripe/PayPal Checkout, and Super Admin Control Hub.',
			'changelog'   => '<h4>Latest Release (' . esc_html( $info->version ) . ')</h4><ul><li>Unified login, verified discovery call meeting requests via portal chat, automatic webhook fulfillment, and plan expiration handling.</li><li>Pointed auto-updater directly to private TARC-LLC/dashboard repository with token authentication.</li></ul>',
		);

		return $info;
	}

	/**
	 * Attach GitHub token to package download requests for private repo
	 */
	public function attach_github_token_to_download( $args, $url ) {
		$token = self::get_token();
		if ( empty( $token ) ) {
			return $args;
		}

		// When downloading zipball from api.github.com, GitHub sends a 302 redirect
		// to codeload.github.com or AWS S3. Sending Authorization headers to codeload/S3
		// results in an AWS S3 "InvalidArgument: Only one auth mechanism allowed" error.
		if ( false !== strpos( $url, 'codeload.github.com' ) || false !== strpos( $url, 'amazonaws.com' ) ) {
			if ( isset( $args['headers']['Authorization'] ) ) {
				unset( $args['headers']['Authorization'] );
			}
			return $args;
		}

		if ( ( false !== strpos( $url, 'github.com' ) || false !== strpos( $url, 'api.github.com' ) ) && false !== strpos( $url, self::GITHUB_USER . '/' . self::GITHUB_REPO ) ) {
			if ( empty( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
				$args['headers'] = array();
			}
			$args['headers']['Authorization'] = 'Bearer ' . $token;
			$args['headers']['User-Agent']    = 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url();
			$args['headers']['Accept']        = 'application/vnd.github.v3+json';
		}
		return $args;
	}

	/**
	 * AJAX: Save GitHub Token in database option
	 */
	public static function ajax_save_github_updater_settings() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'tpd-tool' ) ) );
		}

		$token = isset( $_POST['github_token'] ) ? sanitize_text_field( wp_unslash( $_POST['github_token'] ) ) : '';
		update_option( 'tpd_github_token', $token );
		delete_transient( 'tpd_tool_gh_remote_version' );

		$remote_version = self::get_instance()->get_remote_version( true );

		wp_send_json_success( array(
			'message'         => __( 'GitHub updater token saved successfully!', 'tpd-tool' ),
			'remote_version'  => $remote_version ? $remote_version : __( 'Connected, awaiting release tag', 'tpd-tool' ),
			'has_update'      => $remote_version ? version_compare( TPD_TOOL_VERSION, $remote_version, '<' ) : false,
			'current_version' => TPD_TOOL_VERSION,
		) );
	}

	/**
	 * AJAX: Force check GitHub update status
	 */
	public static function ajax_check_github_update_status() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'tpd-tool' ) ) );
		}

		delete_transient( 'tpd_tool_gh_remote_version' );
		delete_site_transient( 'update_plugins' );
		$remote_version = self::get_instance()->get_remote_version( true );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$has_update = $remote_version ? version_compare( TPD_TOOL_VERSION, $remote_version, '<' ) : false;

		wp_send_json_success( array(
			'current_version' => TPD_TOOL_VERSION,
			'remote_version'  => $remote_version ?: __( 'Unable to fetch remote version. Verify GitHub token has read access to TARC-LLC/dashboard.', 'tpd-tool' ),
			'has_update'      => $has_update,
			'message'         => $has_update
				? sprintf( __( 'Update available! Version %s is ready on TARC-LLC/dashboard (Current: %s).', 'tpd-tool' ), $remote_version, TPD_TOOL_VERSION )
				: sprintf( __( 'Up to date! You are running the latest version (%s).', 'tpd-tool' ), TPD_TOOL_VERSION ),
		) );
	}

	/**
	 * Rename extracted GitHub archive folder (e.g. TARC-LLC-dashboard-abcd123 -> tpd-tool)
	 */
	public function fix_github_folder_name( $source, $remote_source, $upgrader, $hook_extra = null ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}

		$desired_Dest = trailingslashit( $remote_source ) . $this->plugin_slug;
		if ( untrailingslashit( $source ) !== untrailingslashit( $desired_Dest ) ) {
			if ( $wp_filesystem->move( $source, $desired_Dest, true ) ) {
				return trailingslashit( $desired_Dest );
			}
		}

		return $source;
	}

	/**
	 * Enable automatic background updates for TPD Tool
	 */
	public function enable_auto_update( $update, $item ) {
		if ( isset( $item->slug ) && $item->slug === $this->plugin_slug ) {
			return true;
		}
		return $update;
	}

	/**
	 * Add "Check GitHub Update" and "Super Admin Hub" links on Plugins page
	 */
	public function add_plugin_action_links( $links ) {
		$check_url = wp_nonce_url(
			admin_url( 'plugins.php?tpd_force_gh_check=1' ),
			'tpd_force_gh_check_nonce'
		);
		$custom_links = array(
			'<a href="' . esc_url( home_url( '/tpd-admin/' ) ) . '" style="font-weight:700; color:#2563eb;">Super Admin Hub</a>',
			'<a href="' . esc_url( $check_url ) . '" style="font-weight:600; color:#16a34a;">Check for GitHub Update</a>',
		);
		return array_merge( $custom_links, $links );
	}

	/**
	 * Handle manual "Check for GitHub Update" click in WP Admin -> Plugins
	 */
	public function handle_manual_update_check() {
		if ( isset( $_GET['tpd_force_gh_check'] ) && current_user_can( 'update_plugins' ) ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'tpd_force_gh_check_nonce' ) ) {
				delete_transient( 'tpd_tool_gh_remote_version' );
				$this->get_remote_version( true );
				delete_site_transient( 'update_plugins' );
				wp_update_plugins();
				wp_safe_redirect( admin_url( 'plugins.php?tpd_gh_checked=1' ) );
				exit;
			}
		}
	}
}

TPD_Tool_Updater::get_instance();
