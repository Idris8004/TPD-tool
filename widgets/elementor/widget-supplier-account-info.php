<?php
/**
 * Elementor Widget: Supplier Account Info
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Supplier_Account_Info extends Widget_Base {

	public function get_name() {
		return 'tpd_supplier_account_info';
	}

	public function get_title() {
		return __( 'TPD Supplier Account Info', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_supplier_account_info]' );
	}
}
