<?php
/**
 * Elementor Widget: TPD Dynamic Field (ACF / Profile & Dashboard Data)
 * Allows designing custom Advisor & Supplier profiles, cards, and dashboards in Elementor
 * (works in both Elementor Free and Elementor Pro).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Elementor_Dynamic_Field extends \Elementor\Widget_Base {

	public function get_name() {
		return 'tpd_dynamic_field';
	}

	public function get_title() {
		return __( 'TPD Dynamic Field (ACF / Profile Data)', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-database';
	}

	public function get_categories() {
		return array( 'tpd-elements', 'general' );
	}

	public function get_keywords() {
		return array( 'tpd', 'acf', 'dynamic', 'field', 'advisor', 'supplier', 'profile', 'meta', 'badge', 'image' );
	}

	/**
	 * Build flat options array from TPD_Tool_CPT::get_master_field_catalog()
	 */
	public static function get_field_options() {
		$options = array();
		if ( class_exists( 'TPD_Tool_CPT' ) ) {
			$catalog = TPD_Tool_CPT::get_master_field_catalog();
			foreach ( $catalog['advisor'] as $k => $info ) {
				$options[ $k ] = 'Advisor: ' . $info['label'] . ' (' . $info['meta_key'] . ')';
			}
			foreach ( $catalog['supplier'] as $k => $info ) {
				$options[ $k ] = 'Supplier: ' . $info['label'] . ' (' . $info['meta_key'] . ')';
			}
			foreach ( $catalog['event'] as $k => $info ) {
				$options[ $k ] = 'Event: ' . $info['label'] . ' (' . $info['meta_key'] . ')';
			}
		}
		$options['permalink']   = 'General: Public Directory Profile URL';
		$options['custom_meta'] = 'Custom ACF / Post / User Meta Key...';
		return $options;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'TPD Dynamic Field Setup', 'tpd-tool' ),
			)
		);

		$this->add_control(
			'field_key',
			array(
				'label'   => __( 'Select TPD / ACF Field', 'tpd-tool' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'display_name',
				'options' => self::get_field_options(),
			)
		);

		$this->add_control(
			'custom_meta_key',
			array(
				'label'       => __( 'Custom ACF / Meta Key', 'tpd-tool' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. tpd_agency_name or my_acf_field', 'tpd-tool' ),
				'condition'   => array(
					'field_key' => 'custom_meta',
				),
			)
		);

		$this->add_control(
			'source_mode',
			array(
				'label'   => __( 'Data Source Context', 'tpd-tool' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => array(
					'auto'         => __( 'Auto-Detect (Current Post / Loop Item or Logged-In User)', 'tpd-tool' ),
					'current_post' => __( 'Current Post / Loop Grid Item Only', 'tpd-tool' ),
					'current_user' => __( 'Currently Logged-In User Only', 'tpd-tool' ),
				),
			)
		);

		$this->add_control(
			'render_mode',
			array(
				'label'   => __( 'Display Format', 'tpd-tool' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'text',
				'options' => array(
					'text'   => __( 'Text / Heading / HTML', 'tpd-tool' ),
					'badges' => __( 'Badges / Pills (Splits comma-separated items)', 'tpd-tool' ),
					'image'  => __( 'Image (Headshot, Logo, or Banner)', 'tpd-tool' ),
					'button' => __( 'Action Button / Link (Website, Booking, Social)', 'tpd-tool' ),
				),
			)
		);

		$this->add_control(
			'html_tag',
			array(
				'label'     => __( 'HTML Tag', 'tpd-tool' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'div',
				'options'   => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'p'    => 'p',
					'span' => 'span',
					'div'  => 'div',
				),
				'condition' => array(
					'render_mode' => 'text',
				),
			)
		);

		$this->add_control(
			'prefix_text',
			array(
				'label'       => __( 'Prefix Label (Optional)', 'tpd-tool' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Agency: ', 'tpd-tool' ),
				'condition'   => array(
					'render_mode' => array( 'text', 'button' ),
				),
			)
		);

		$this->add_control(
			'suffix_text',
			array(
				'label'       => __( 'Suffix Text (Optional)', 'tpd-tool' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. + Years', 'tpd-tool' ),
				'condition'   => array(
					'render_mode' => 'text',
				),
			)
		);

		$this->add_control(
			'button_label',
			array(
				'label'       => __( 'Button Label', 'tpd-tool' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Visit Website', 'tpd-tool' ),
				'condition'   => array(
					'render_mode' => 'button',
				),
			)
		);

		$this->add_control(
			'fallback_text',
			array(
				'label'       => __( 'Fallback Value (if field is empty)', 'tpd-tool' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => __( 'Leave blank to hide when empty', 'tpd-tool' ),
			)
		);

		$this->end_controls_section();

		// STYLE SECTION
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Field Styling', 'tpd-tool' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => __( 'Text / Foreground Color', 'tpd-tool' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#0f172a',
				'selectors' => array(
					'{{WRAPPER}} .tpd-el-dynamic-field' => 'color: {{VALUE}};',
					'{{WRAPPER}} .tpd-el-dynamic-badge' => 'color: {{VALUE}};',
					'{{WRAPPER}} .tpd-el-dynamic-btn'   => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'bg_color',
			array(
				'label'     => __( 'Background Color (for Badges / Button)', 'tpd-tool' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .tpd-el-dynamic-badge' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .tpd-el-dynamic-btn'   => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'selector' => '{{WRAPPER}} .tpd-el-dynamic-field, {{WRAPPER}} .tpd-el-dynamic-badge, {{WRAPPER}} .tpd-el-dynamic-btn',
			)
		);

		$this->add_control(
			'img_width',
			array(
				'label'      => __( 'Image Width (px or %)', 'tpd-tool' ),
				'type'       => \Elementor\Controls_Manager::TEXT,
				'default'    => '120px',
				'condition'  => array(
					'render_mode' => 'image',
				),
				'selectors'  => array(
					'{{WRAPPER}} .tpd-el-dynamic-img' => 'width: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'img_height',
			array(
				'label'      => __( 'Image Height (px or auto)', 'tpd-tool' ),
				'type'       => \Elementor\Controls_Manager::TEXT,
				'default'    => '120px',
				'condition'  => array(
					'render_mode' => 'image',
				),
				'selectors'  => array(
					'{{WRAPPER}} .tpd-el-dynamic-img' => 'height: {{VALUE}}; object-fit: cover;',
				),
			)
		);

		$this->add_control(
			'img_radius',
			array(
				'label'      => __( 'Border Radius (e.g. 50% for circle, 12px for rounded)', 'tpd-tool' ),
				'type'       => \Elementor\Controls_Manager::TEXT,
				'default'    => '12px',
				'selectors'  => array(
					'{{WRAPPER}} .tpd-el-dynamic-img, {{WRAPPER}} .tpd-el-dynamic-badge, {{WRAPPER}} .tpd-el-dynamic-btn' => 'border-radius: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings    = $this->get_settings_for_display();
		$field_key   = ( 'custom_meta' === $settings['field_key'] ) ? $settings['custom_meta_key'] : $settings['field_key'];
		$source_mode = ! empty( $settings['source_mode'] ) ? $settings['source_mode'] : 'auto';
		$render_mode = ! empty( $settings['render_mode'] ) ? $settings['render_mode'] : 'text';

		$val = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( $field_key, 0, 0, $source_mode ) : '';

		if ( '' === $val && ! empty( $settings['fallback_text'] ) ) {
			$val = $settings['fallback_text'];
		}

		// Show helpful placeholder in Elementor editor preview if value is empty
		if ( '' === $val && class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$val = '[' . $field_key . ']';
		}

		if ( '' === $val ) {
			return;
		}

		if ( 'image' === $render_mode ) {
			printf(
				'<img src="%s" alt="%s" class="tpd-el-dynamic-img" style="display:inline-block; max-width:100%%;" />',
				esc_url( $val ),
				esc_attr( $field_key )
			);
			return;
		}

		if ( 'badges' === $render_mode ) {
			$items = array_filter( array_map( 'trim', explode( ',', $val ) ) );
			echo '<div class="tpd-el-dynamic-badges-wrap" style="display:flex; flex-wrap:wrap; gap:8px;">';
			foreach ( $items as $item ) {
				printf(
					'<span class="tpd-el-dynamic-badge" style="display:inline-block; padding:5px 12px; background:#e0f2fe; color:#0369a1; font-size:12.5px; font-weight:600; border-radius:999px;">%s</span>',
					esc_html( $item )
				);
			}
			echo '</div>';
			return;
		}

		if ( 'button' === $render_mode ) {
			$href = $val;
			if ( is_email( $val ) ) {
				$href = 'mailto:' . $val;
			} elseif ( in_array( $field_key, array( 'tpd_phone', 'tpd_primary_rep_phone' ), true ) ) {
				$href = 'tel:' . preg_replace( '/[^0-9+]/', '', $val );
			}
			$btn_label = ! empty( $settings['button_label'] ) ? $settings['button_label'] : $val;
			printf(
				'<a href="%s" class="tpd-el-dynamic-btn" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:#00798c; color:#ffffff; text-decoration:none; font-weight:700; border-radius:8px;">%s%s</a>',
				esc_url( $href ),
				! empty( $settings['prefix_text'] ) ? '<span>' . esc_html( $settings['prefix_text'] ) . '</span>' : '',
				esc_html( $btn_label )
			);
			return;
		}

		// Default: Text / Heading / HTML
		$allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'p', 'span', 'div' );
		$tag = in_array( $settings['html_tag'], $allowed_tags, true ) ? $settings['html_tag'] : 'div';

		printf(
			'<%1$s class="tpd-el-dynamic-field">%2$s%3$s%4$s</%1$s>',
			esc_attr( $tag ),
			! empty( $settings['prefix_text'] ) ? '<span class="tpd-el-prefix" style="opacity:0.75;">' . esc_html( $settings['prefix_text'] ) . '</span>' : '',
			wp_kses_post( $val ),
			! empty( $settings['suffix_text'] ) ? '<span class="tpd-el-suffix" style="opacity:0.75;">' . esc_html( $settings['suffix_text'] ) . '</span>' : ''
		);
	}
}
