<?php
/**
 * Elementor Widget: Supplier Partner Login
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Supplier_Login extends Widget_Base {

	public function get_name() {
		return 'tpd_supplier_login';
	}

	public function get_title() {
		return __( 'TPD Supplier Partner Login', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_supplier_login]' );
	}
}
