<?php
/**
 * Native Elementor Dynamic Tags for TPD Tool (Text, Image, and URL Dynamic Tags)
 * Enables clicking the Dynamic Tag icon on ANY Elementor Heading, Text, Image, Background,
 * Button, or Link control and binding directly to Advisor, Supplier, Event, or ACF fields.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {

	/**
	 * 1. TPD Dynamic Text / Meta / Number Tag
	 */
	class TPD_Dynamic_Tag_Text extends \Elementor\Core\DynamicTags\Tag {

		public function get_name() {
			return 'tpd-dynamic-text';
		}

		public function get_title() {
			return __( 'TPD Profile / Dashboard Field', 'tpd-tool' );
		}

		public function get_group() {
			return 'tpd-dynamic-tags';
		}

		public function get_categories() {
			return array(
				\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
				\Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
				\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
			);
		}

		protected function register_controls() {
			$options = class_exists( 'TPD_Elementor_Dynamic_Field' ) ? TPD_Elementor_Dynamic_Field::get_field_options() : array( 'display_name' => 'Full Name' );

			$this->add_control(
				'field_key',
				array(
					'label'   => __( 'TPD / ACF Field', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'display_name',
					'options' => $options,
				)
			);

			$this->add_control(
				'custom_meta_key',
				array(
					'label'       => __( 'Custom Meta / ACF Key', 'tpd-tool' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => 'tpd_agency_name',
					'condition'   => array(
						'field_key' => 'custom_meta',
					),
				)
			);

			$this->add_control(
				'source_mode',
				array(
					'label'   => __( 'Data Context', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'auto',
					'options' => array(
						'auto'         => __( 'Auto (Current Post or Logged-In User)', 'tpd-tool' ),
						'current_post' => __( 'Current Post / Loop Item', 'tpd-tool' ),
						'current_user' => __( 'Logged-In User', 'tpd-tool' ),
					),
				)
			);
		}

		public function render() {
			$field_key   = $this->get_settings( 'field_key' );
			$custom_key  = $this->get_settings( 'custom_meta_key' );
			$source_mode = $this->get_settings( 'source_mode' ) ?: 'auto';

			if ( 'custom_meta' === $field_key ) {
				$field_key = $custom_key;
			}

			$val = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( $field_key, 0, 0, $source_mode ) : '';
			echo wp_kses_post( $val );
		}
	}
}

if ( class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {

	/**
	 * 2. TPD Dynamic Image Tag (Headshot, Agency/Supplier Logo, Cover Banner)
	 */
	class TPD_Dynamic_Tag_Image extends \Elementor\Core\DynamicTags\Data_Tag {

		public function get_name() {
			return 'tpd-dynamic-image';
		}

		public function get_title() {
			return __( 'TPD Image (Headshot, Logo, Banner)', 'tpd-tool' );
		}

		public function get_group() {
			return 'tpd-dynamic-tags';
		}

		public function get_categories() {
			return array(
				\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY,
				\Elementor\Modules\DynamicTags\Module::MEDIA_CATEGORY,
			);
		}

		protected function register_controls() {
			$this->add_control(
				'image_field',
				array(
					'label'   => __( 'Image Field', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'tpd_headshot_url',
					'options' => array(
						'tpd_headshot_url' => __( 'Headshot / Avatar Photo (tpd_headshot_url)', 'tpd-tool' ),
						'tpd_logo_url'     => __( 'Agency / Supplier Brand Logo (tpd_logo_url)', 'tpd-tool' ),
						'tpd_banner_url'   => __( 'Profile / Showcase Hero Banner (tpd_banner_url)', 'tpd-tool' ),
						'custom_meta'      => __( 'Custom Image Meta / ACF Key...', 'tpd-tool' ),
					),
				)
			);

			$this->add_control(
				'custom_meta_key',
				array(
					'label'       => __( 'Custom Image Meta Key', 'tpd-tool' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => 'my_custom_image_url',
					'condition'   => array(
						'image_field' => 'custom_meta',
					),
				)
			);

			$this->add_control(
				'source_mode',
				array(
					'label'   => __( 'Data Context', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'auto',
					'options' => array(
						'auto'         => __( 'Auto (Current Post or Logged-In User)', 'tpd-tool' ),
						'current_post' => __( 'Current Post / Loop Item', 'tpd-tool' ),
						'current_user' => __( 'Logged-In User', 'tpd-tool' ),
					),
				)
			);

			$this->add_control(
				'fallback_image',
				array(
					'label' => __( 'Fallback Image', 'tpd-tool' ),
					'type'  => \Elementor\Controls_Manager::MEDIA,
				)
			);
		}

		public function get_value( array $options = array() ) {
			$field_key   = $this->get_settings( 'image_field' );
			$custom_key  = $this->get_settings( 'custom_meta_key' );
			$source_mode = $this->get_settings( 'source_mode' ) ?: 'auto';

			if ( 'custom_meta' === $field_key ) {
				$field_key = $custom_key;
			}

			$url = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( $field_key, 0, 0, $source_mode ) : '';

			if ( empty( $url ) ) {
				$fallback = $this->get_settings( 'fallback_image' );
				if ( ! empty( $fallback['url'] ) ) {
					return $fallback;
				}
				return array(
					'id'  => 0,
					'url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
				);
			}

			$att_id = attachment_url_to_postid( $url );
			return array(
				'id'  => $att_id ?: 0,
				'url' => $url,
			);
		}
	}

	/**
	 * 3. TPD Dynamic URL / Social / Booking Link Tag
	 */
	class TPD_Dynamic_Tag_URL extends \Elementor\Core\DynamicTags\Data_Tag {

		public function get_name() {
			return 'tpd-dynamic-url';
		}

		public function get_title() {
			return __( 'TPD URL (Website, Booking, Social, Contact)', 'tpd-tool' );
		}

		public function get_group() {
			return 'tpd-dynamic-tags';
		}

		public function get_categories() {
			return array(
				\Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
			);
		}

		protected function register_controls() {
			$this->add_control(
				'url_field',
				array(
					'label'   => __( 'Select Link / URL Field', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'permalink',
					'options' => array(
						'permalink'              => __( 'Public Directory Profile Permalink', 'tpd-tool' ),
						'tpd_website'            => __( 'Advisor Website URL (tpd_website)', 'tpd-tool' ),
						'tpd_booking_portal_url' => __( 'Supplier Booking Portal URL (tpd_booking_portal_url)', 'tpd-tool' ),
						'tpd_main_video_url'     => __( 'Supplier Showcase Video URL (tpd_main_video_url)', 'tpd-tool' ),
						'email_mailto'           => __( 'Direct Email Link (mailto:)', 'tpd-tool' ),
						'phone_tel'              => __( 'Direct Phone Call Link (tel:)', 'tpd-tool' ),
						'tpd_social_facebook'    => __( 'Facebook URL (tpd_social_facebook)', 'tpd-tool' ),
						'tpd_social_instagram'   => __( 'Instagram URL (tpd_social_instagram)', 'tpd-tool' ),
						'tpd_social_linkedin'    => __( 'LinkedIn URL (tpd_social_linkedin)', 'tpd-tool' ),
						'tpd_social_youtube'     => __( 'YouTube URL (tpd_social_youtube)', 'tpd-tool' ),
						'tpd_social_tiktok'      => __( 'TikTok URL (tpd_social_tiktok)', 'tpd-tool' ),
						'tpd_social_twitter'     => __( 'X / Twitter URL (tpd_social_twitter)', 'tpd-tool' ),
						'custom_meta'            => __( 'Custom URL Meta / ACF Key...', 'tpd-tool' ),
					),
				)
			);

			$this->add_control(
				'custom_meta_key',
				array(
					'label'       => __( 'Custom URL Meta Key', 'tpd-tool' ),
					'type'        => \Elementor\Controls_Manager::TEXT,
					'placeholder' => 'my_custom_url',
					'condition'   => array(
						'url_field' => 'custom_meta',
					),
				)
			);

			$this->add_control(
				'source_mode',
				array(
					'label'   => __( 'Data Context', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => 'auto',
					'options' => array(
						'auto'         => __( 'Auto (Current Post or Logged-In User)', 'tpd-tool' ),
						'current_post' => __( 'Current Post / Loop Item', 'tpd-tool' ),
						'current_user' => __( 'Logged-In User', 'tpd-tool' ),
					),
				)
			);
		}

		public function get_value( array $options = array() ) {
			$url_field   = $this->get_settings( 'url_field' );
			$custom_key  = $this->get_settings( 'custom_meta_key' );
			$source_mode = $this->get_settings( 'source_mode' ) ?: 'auto';

			if ( 'custom_meta' === $url_field ) {
				$url_field = $custom_key;
			}

			if ( 'email_mailto' === $url_field ) {
				$email = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( 'email', 0, 0, $source_mode ) : '';
				return $email ? 'mailto:' . $email : '#';
			}

			if ( 'phone_tel' === $url_field ) {
				$phone = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( 'tpd_phone', 0, 0, $source_mode ) : '';
				if ( ! $phone && class_exists( 'TPD_Tool_CPT' ) ) {
					$phone = TPD_Tool_CPT::resolve_field_value( 'tpd_primary_rep_phone', 0, 0, $source_mode );
				}
				return $phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) : '#';
			}

			$val = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( $url_field, 0, 0, $source_mode ) : '';
			return $val ?: '#';
		}
	}
}
