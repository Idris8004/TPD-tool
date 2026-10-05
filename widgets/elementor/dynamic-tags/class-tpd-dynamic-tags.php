<?php
/**
 * Native Elementor Pro / Free Dynamic Tags for TPD Tool
 * Populates the Elementor Dynamic Tags dropdown menu (stacked coins icon) on Headings,
 * Text Editors, Images, Backgrounds, Buttons, and Links with:
 *   - TPD — Travel Advisor (Full Name, Agency Name, Phone, Email, Location, Bio, Consortia, Certifications, Specialties, Headshot, Logo, Banner, Social URLs, etc.)
 *   - TPD — Supplier Partner (Company Name, Tagline, Description, Headquarters, Rep Info, Phone, Promo, Badges, Brand Logo, Showcase Banner, Booking Portal URL, Video URL, etc.)
 *   - TPD — Custom & ACF Fields (Any Advisor, Supplier, Event, or Admin Custom Field)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper to build field dropdown options without depending on Widget_Base
 */
function tpd_get_elementor_dynamic_field_options( $group = 'all' ) {
	$options = array();
	if ( class_exists( 'TPD_Tool_CPT' ) ) {
		$catalog = TPD_Tool_CPT::get_master_field_catalog();
		if ( 'all' === $group || 'advisor' === $group ) {
			foreach ( $catalog['advisor'] as $k => $info ) {
				$prefix = ( 'all' === $group ) ? 'Advisor: ' : '';
				$options[ $k ] = $prefix . $info['label'];
			}
		}
		if ( 'all' === $group || 'supplier' === $group ) {
			foreach ( $catalog['supplier'] as $k => $info ) {
				$prefix = ( 'all' === $group ) ? 'Supplier: ' : '';
				$options[ $k ] = $prefix . $info['label'];
			}
		}
		if ( 'all' === $group || 'event' === $group ) {
			foreach ( $catalog['event'] as $k => $info ) {
				$prefix = ( 'all' === $group ) ? 'Event: ' : '';
				$options[ $k ] = $prefix . $info['label'];
			}
		}
	}
	$options['permalink']   = 'Public Directory Profile URL';
	$options['custom_meta'] = 'Custom ACF / Meta Key...';
	return $options;
}

if ( class_exists( '\Elementor\Core\DynamicTags\Tag' ) ) {

	/**
	 * Base Reusable Text Dynamic Tag for TPD
	 */
	abstract class TPD_Abstract_Text_Tag extends \Elementor\Core\DynamicTags\Tag {

		protected $default_field = 'display_name';
		protected $field_group   = 'all';

		public function get_categories() {
			return array(
				\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
				\Elementor\Modules\DynamicTags\Module::POST_META_CATEGORY,
				\Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
			);
		}

		protected function register_controls() {
			$this->add_control(
				'field_key',
				array(
					'label'   => __( 'Field to Display', 'tpd-tool' ),
					'type'    => \Elementor\Controls_Manager::SELECT,
					'default' => $this->default_field,
					'options' => tpd_get_elementor_dynamic_field_options( $this->field_group ),
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
					'label'   => __( 'Data Source', 'tpd-tool' ),
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
			$field_key   = $this->get_settings( 'field_key' ) ?: $this->default_field;
			$custom_key  = $this->get_settings( 'custom_meta_key' );
			$source_mode = $this->get_settings( 'source_mode' ) ?: 'auto';

			if ( 'custom_meta' === $field_key && ! empty( $custom_key ) ) {
				$field_key = $custom_key;
			}

			$val = class_exists( 'TPD_Tool_CPT' ) ? TPD_Tool_CPT::resolve_field_value( $field_key, 0, 0, $source_mode ) : '';
			echo wp_kses_post( $val );
		}
	}

	// =========================================================================
	// GROUP 1: TPD — TRAVEL ADVISOR TAGS (Appears directly in Dynamic Tags Menu)
	// =========================================================================

	class TPD_Tag_Advisor_Name extends TPD_Abstract_Text_Tag {
		protected $default_field = 'display_name';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-name'; }
		public function get_title() { return __( 'Advisor Full Name', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Agency extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_agency_name';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-agency'; }
		public function get_title() { return __( 'Advisor Agency Name', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Contact extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_phone';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-contact'; }
		public function get_title() { return __( 'Advisor Phone / Email / Handle', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Location extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_location';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-location'; }
		public function get_title() { return __( 'Advisor Location & Country', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Bio extends TPD_Abstract_Text_Tag {
		protected $default_field = 'bio';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-bio'; }
		public function get_title() { return __( 'Advisor Biography / About Me', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Affiliation extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_consortia';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-affiliation'; }
		public function get_title() { return __( 'Advisor Consortia & Host Agency', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Certifications extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_clia_number';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-certifications'; }
		public function get_title() { return __( 'Advisor Certifications (CLIA / IATA / ARC / TRUE)', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Experience extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_years_experience';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-experience'; }
		public function get_title() { return __( 'Advisor Experience & Sales Volume', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Specialties extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_travel_types';
		protected $field_group   = 'advisor';
		public function get_name()  { return 'tpd-advisor-specialties'; }
		public function get_title() { return __( 'Advisor Specialties & Destinations', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	class TPD_Tag_Advisor_Any_Field extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_agency_name';
		protected $field_group   = 'all';
		public function get_name()  { return 'tpd-dynamic-text'; }
		public function get_title() { return __( 'All TPD / ACF Advisor & Supplier Fields', 'tpd-tool' ); }
		public function get_group() { return 'tpd-advisor-tags'; }
	}

	// =========================================================================
	// GROUP 2: TPD — SUPPLIER PARTNER TAGS (Appears directly in Dynamic Tags Menu)
	// =========================================================================

	class TPD_Tag_Supplier_Company extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_company_name';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-company'; }
		public function get_title() { return __( 'Supplier / Company Name', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}

	class TPD_Tag_Supplier_Tagline extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_tagline';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-tagline'; }
		public function get_title() { return __( 'Supplier Brand Tagline', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}

	class TPD_Tag_Supplier_Description extends TPD_Abstract_Text_Tag {
		protected $default_field = 'supplier_description';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-description'; }
		public function get_title() { return __( 'Supplier Overview / Description', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}

	class TPD_Tag_Supplier_Headquarters extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_headquarters';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-headquarters'; }
		public function get_title() { return __( 'Supplier Headquarters & Country', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}

	class TPD_Tag_Supplier_Rep extends TPD_Abstract_Text_Tag {
		protected $default_field = 'rep_full_name';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-rep'; }
		public function get_title() { return __( 'Supplier Rep Name, Title & Phone', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}

	class TPD_Tag_Supplier_Promo extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_promo_title';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-promo'; }
		public function get_title() { return __( 'Supplier Advisor Promo / Incentive', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}

	class TPD_Tag_Supplier_Programs extends TPD_Abstract_Text_Tag {
		protected $default_field = 'tpd_agent_rewards';
		protected $field_group   = 'supplier';
		public function get_name()  { return 'tpd-supplier-programs'; }
		public function get_title() { return __( 'Supplier Rewards / USTOA / ASTA Status', 'tpd-tool' ); }
		public function get_group() { return 'tpd-supplier-tags'; }
	}
}

if ( class_exists( '\Elementor\Core\DynamicTags\Data_Tag' ) ) {

	/**
	 * TPD Dynamic Image Tag (Headshot, Agency/Supplier Logo, Cover Banner)
	 */
	class TPD_Dynamic_Tag_Image extends \Elementor\Core\DynamicTags\Data_Tag {

		public function get_name() {
			return 'tpd-dynamic-image';
		}

		public function get_title() {
			return __( 'TPD Image (Headshot, Logo, Banner)', 'tpd-tool' );
		}

		public function get_group() {
			return 'tpd-advisor-tags';
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
	 * TPD Dynamic URL / Social / Booking Link Tag
	 */
	class TPD_Dynamic_Tag_URL extends \Elementor\Core\DynamicTags\Data_Tag {

		public function get_name() {
			return 'tpd-dynamic-url';
		}

		public function get_title() {
			return __( 'TPD URL (Website, Booking, Social, Contact)', 'tpd-tool' );
		}

		public function get_group() {
			return 'tpd-advisor-tags';
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
