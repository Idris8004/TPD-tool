<?php
/**
 * Elementor Widget: Upcoming Events Grid
 * Renders the modern 4-card upcoming events grid model.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class TPD_Elementor_Upcoming_Events extends Widget_Base {

	public function get_name() {
		return 'tpd_upcoming_events';
	}

	public function get_title() {
		return __( 'TPD — Upcoming Events Grid', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-calendar';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_events_content',
			array(
				'label' => __( 'Events Configuration', 'tpd-tool' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Section Heading', 'tpd-tool' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'Upcoming Events', 'tpd-tool' ),
				'placeholder' => __( 'Upcoming Events', 'tpd-tool' ),
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'        => __( 'Display Heading', 'tpd-tool' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of Events to Show', 'tpd-tool' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 4,
				'min'     => 1,
				'max'     => 24,
				'step'    => 1,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Grid Columns', 'tpd-tool' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '4',
				'options' => array(
					'1' => __( '1 Column', 'tpd-tool' ),
					'2' => __( '2 Columns', 'tpd-tool' ),
					'3' => __( '3 Columns', 'tpd-tool' ),
					'4' => __( '4 Columns (Default Model)', 'tpd-tool' ),
				),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button Label', 'tpd-tool' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => __( 'View Event', 'tpd-tool' ),
				'placeholder' => __( 'View Event', 'tpd-tool' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$args = array(
			'title'       => ! empty( $settings['title'] ) ? $settings['title'] : '',
			'show_title'  => ( 'yes' === $settings['show_title'] ),
			'limit'       => ! empty( $settings['limit'] ) ? absint( $settings['limit'] ) : 4,
			'columns'     => ! empty( $settings['columns'] ) ? absint( $settings['columns'] ) : 4,
			'button_text' => ! empty( $settings['button_text'] ) ? $settings['button_text'] : __( 'View Event', 'tpd-tool' ),
		);

		if ( class_exists( 'TPD_Tool_Events' ) ) {
			echo TPD_Tool_Events::render_events_grid( $args );
		}
	}
}
