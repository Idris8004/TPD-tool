<?php
/**
 * Elementor Widget: Advisor Member Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Advisor_Login extends Widget_Base {

	public function get_name() {
		return 'tpd_advisor_login';
	}

	public function get_title() {
		return __( 'TPD Advisor Member Login', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_advisor_login]' );
	}
}
