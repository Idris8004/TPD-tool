<?php
/**
 * Elementor Widget: TARC Dual Gateway Portal Login
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
		return __( 'TARC Dual Gateway Portal Login', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-dual-button';
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
			'show_hero',
			array(
				'label'        => __( 'Show Intro Hero Section', 'tpd-tool' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Show', 'tpd-tool' ),
				'label_off'    => __( 'Hide', 'tpd-tool' ),
				'return_value' => 'yes',
				'default'      => 'yes',
				'description'  => __( 'Toggle the company introduction & accredited highlight cards above the login gateways.', 'tpd-tool' ),
			)
		);

		$this->add_control(
			'default_portal',
			array(
				'label'   => __( 'Default Open Portal', 'tpd-tool' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'advisor',
				'options' => array(
					'advisor'  => __( 'Travel Advisor Portal', 'tpd-tool' ),
					'supplier' => __( 'Supplier Partner Portal', 'tpd-tool' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings       = $this->get_settings_for_display();
		$show_hero      = ( ! empty( $settings['show_hero'] ) && 'yes' === $settings['show_hero'] ) ? 'yes' : 'no';
		$default_portal = ! empty( $settings['default_portal'] ) ? sanitize_key( $settings['default_portal'] ) : 'advisor';

		echo do_shortcode( sprintf( '[tpd_portal_login show_hero="%s" default_portal="%s"]', esc_attr( $show_hero ), esc_attr( $default_portal ) ) );
	}
}
