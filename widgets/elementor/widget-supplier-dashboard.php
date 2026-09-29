<?php
/**
 * Elementor Widget: Supplier Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Supplier_Dashboard extends Widget_Base {

	public function get_name() {
		return 'tpd_supplier_dashboard';
	}

	public function get_title() {
		return __( 'TPD Supplier Dashboard', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-site-identity';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_supplier_dashboard]' );
	}
}
