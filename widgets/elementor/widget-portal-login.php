<?php
/**
 * Elementor Widget: TARC Unified Executive Portal Login
 * Travel Advisor Resource Center (TARC)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class TPD_Elementor_Portal_Login extends Widget_Base {

	public function get_name() {
		return 'tarc_portal_login';
	}

	public function get_title() {
		return __( 'TARC Unified Portal Login', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_portal_settings',
			array(
				'label' => __( 'Portal Settings', 'tpd-tool' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'default_portal',
			array(
				'label'   => __( 'Default Active Role', 'tpd-tool' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'advisor',
				'options' => array(
					'advisor'  => __( 'Travel Advisor', 'tpd-tool' ),
					'supplier' => __( 'Supplier Partner', 'tpd-tool' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings       = $this->get_settings_for_display();
		$default_portal = ! empty( $settings['default_portal'] ) ? sanitize_key( $settings['default_portal'] ) : 'advisor';

		echo do_shortcode( sprintf( '[tpd_portal_login default_portal="%s"]', esc_attr( $default_portal ) ) );
	}
}
