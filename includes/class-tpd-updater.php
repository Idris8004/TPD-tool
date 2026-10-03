<?php
/**
 * Self-Contained GitHub Auto-Updater for TPD Tool
 * Checks https://github.com/Idris8004/TPD-tool (main branch) for new versions
 * and integrates with native WordPress Plugin Updates & Auto-Updates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Updater {

	const GITHUB_USER   = 'Idris8004';
	const GITHUB_REPO   = 'TPD-tool';
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

	private function __construct() {
		$this->plugin_basename = plugin_basename( TPD_TOOL_FILE );
		$this->plugin_slug     = dirname( $this->plugin_basename );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_Popup_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_github_folder_name' ), 10, 4 );
		add_filter( 'auto_update_plugin', array( $this, 'enable_auto_update' ), 10, 2 );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, array( $this, 'add_plugin_action_links' ) );
		add_action( 'admin_init', array( $this, 'handle_manual_update_check' ) );
	}

	/**
	 * Fetch remote version from raw tpd-tool.php on GitHub main branch
	 */
	public function get_remote_version( $force = false ) {
		$transient_key = 'tpd_tool_gh_remote_version';
		if ( ! $force ) {
			$cached = get_transient( $transient_key );
			if ( ! empty( $cached ) ) {
				return $cached;
			}
		}

		$raw_url  = sprintf(
			'https://raw.githubusercontent.com/%s/%s/%s/tpd-tool.php?t=%d',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			self::GITHUB_BRANCH,
			time()
		);

		$response = wp_remote_get( $raw_url, array(
			'timeout' => 10,
			'headers' => array(
				'Cache-Control' => 'no-cache',
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/mi', $body, $matches ) ) {
			$remote_version = trim( $matches[1] );
			set_transient( $transient_key, $remote_version, 15 * MINUTE_IN_SECONDS );
			return $remote_version;
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
				'https://github.com/%s/%s/archive/refs/heads/%s.zip',
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
		$info->author        = '<a href="https://github.com/Idris8004/TPD-tool">Travel Partner Directory (TARC)</a>';
		$info->homepage      = 'https://github.com/Idris8004/TPD-tool';
		$info->download_link = sprintf(
			'https://github.com/%s/%s/archive/refs/heads/%s.zip',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			self::GITHUB_BRANCH
		);
		$info->sections      = array(
			'description' => 'Enterprise-grade travel marketplace suite featuring Multi-Step Registration Wizards, Dual Portals (Advisor & Supplier Dashboards), Bi-Directional WP CPT + ACF Sync, Dynamic Membership Plan Builder, Stripe/PayPal Checkout, and Super Admin Control Hub.',
			'changelog'   => '<h4>Latest Release (' . esc_html( $info->version ) . ')</h4><ul><li>Multi-step registration wizards for Travel Advisors & Supplier Partners</li><li>Full bi-directional sync between WP Custom Post Types, ACF Fields, and Frontend Dashboards</li><li>Rebuilt Super Admin Hub with KPI cards, user CRUD, plan promote/demote, dynamic plan builder, and payment gateway configuration</li></ul>',
		);

		return $info;
	}

	/**
	 * Rename extracted GitHub archive folder (e.g. TPD-tool-main -> tpd-tool)
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
			'<a href="' . esc_url( home_url( '/super-admin-dashboard/' ) ) . '" style="font-weight:700; color:#2563eb;">Super Admin Hub</a>',
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
