<?php
/**
 * Elementor Widget: Supplier Registration Wizard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Supplier_Registration extends Widget_Base {

	public function get_name() {
		return 'tpd_supplier_registration';
	}

	public function get_title() {
		return __( 'TPD Supplier Registration Wizard', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_supplier_registration]' );
	}
}
