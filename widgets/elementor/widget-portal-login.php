<?php
/**
 * Elementor Widget: TARC Dual Gateway Portal Login
 * Travel Advisor Resource Center (TARC)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Portal_Login extends Widget_Base {

	public function get_name() {
		return 'tarc_portal_login';
	}

	public function get_title() {
		return __( 'TARC Dual Gateway Portal Login', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-dual-button';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_portal_login]' );
	}
}
