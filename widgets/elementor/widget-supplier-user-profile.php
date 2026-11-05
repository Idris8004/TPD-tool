<?php
/**
 * Elementor Widget: Supplier User / Representative Profile
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class TPD_Elementor_Supplier_User_Profile extends Widget_Base {

	public function get_name() {
		return 'tpd_supplier_user_profile';
	}

	public function get_title() {
		return __( 'TPD Supplier Rep Profile', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-user-circle-o';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Profile Settings', 'tpd-tool' ),
			)
		);

		$this->add_control(
			'user_id',
			array(
				'label'       => __( 'Specific Supplier User ID (Optional)', 'tpd-tool' ),
				'type'        => Controls_Manager::NUMBER,
				'description' => __( 'Leave empty to automatically detect from URL parameter (?user_id=) or logged-in supplier.', 'tpd-tool' ),
				'default'     => '',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$uid = ! empty( $settings['user_id'] ) ? absint( $settings['user_id'] ) : 0;
		if ( $uid > 0 ) {
			echo do_shortcode( '[tpd_supplier_user_profile user_id="' . $uid . '"]' );
		} else {
			echo do_shortcode( '[tpd_supplier_user_profile]' );
		}
	}
}
