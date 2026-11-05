<?php
/**
 * Elementor Widget: Super Admin Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Admin_Login extends Widget_Base {

	public function get_name() {
		return 'tpd_admin_login';
	}

	public function get_title() {
		return __( 'TPD Super Admin Login', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-shield-check';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_admin_login]' );
	}
}
