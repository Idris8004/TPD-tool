<?php
/**
 * Elementor Widget: Advisor Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class TPD_Elementor_Advisor_Dashboard extends Widget_Base {

	public function get_name() {
		return 'tpd_advisor_dashboard';
	}

	public function get_title() {
		return __( 'TPD Advisor Dashboard', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-dashboard';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Dashboard Settings', 'tpd-tool' ),
			)
		);

		$this->add_control(
			'show_header',
			array(
				'label'        => __( 'Show Header Bar', 'tpd-tool' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		echo do_shortcode( '[tpd_advisor_dashboard]' );
	}
}
