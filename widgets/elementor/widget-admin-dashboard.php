<?php
/**
 * Elementor Widget: Super Admin Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Admin_Dashboard extends Widget_Base {

	public function get_name() {
		return 'tpd_admin_dashboard';
	}

	public function get_title() {
		return __( 'TPD Super Admin Dashboard', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_admin_dashboard]' );
	}
}
