<?php
/**
 * Shortcodes Registry for TPD Tool
 * Includes Full Portal Shortcodes, Auth Shortcodes, and Universal Dynamic Field Shortcodes ([tpd_field]).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Shortcodes {

	public static function init() {
		add_shortcode( 'tpd_advisor_dashboard', array( __CLASS__, 'render_advisor_dashboard' ) );
		add_shortcode( 'tpd_supplier_dashboard', array( __CLASS__, 'render_supplier_dashboard' ) );
		add_shortcode( 'tpd_admin_dashboard', array( __CLASS__, 'render_admin_dashboard' ) );
		add_shortcode( 'tpd_advisor_registration', array( __CLASS__, 'render_advisor_registration' ) );
		add_shortcode( 'tpd_supplier_registration', array( __CLASS__, 'render_supplier_registration' ) );
		add_shortcode( 'tpd_advisor_login', array( __CLASS__, 'render_advisor_login' ) );
		add_shortcode( 'tpd_member_login', array( __CLASS__, 'render_advisor_login' ) );
		add_shortcode( 'tpd_login', array( __CLASS__, 'render_advisor_login' ) );
		add_shortcode( 'tpd_supplier_login', array( __CLASS__, 'render_supplier_login' ) );
		add_shortcode( 'tpd_chat_inbox', array( __CLASS__, 'render_chat_inbox' ) );
		add_shortcode( 'tpd_saved_suppliers', array( __CLASS__, 'render_saved_suppliers' ) );
		add_shortcode( 'tpd_events_list', array( __CLASS__, 'render_events_list' ) );

		// Universal Dynamic Field Shortcodes for Elementor / Gutenberg / Theme Templates
		add_shortcode( 'tpd_field', array( __CLASS__, 'render_dynamic_field' ) );
		add_shortcode( 'tpd_advisor_field', array( __CLASS__, 'render_dynamic_field' ) );
		add_shortcode( 'tpd_supplier_field', array( __CLASS__, 'render_dynamic_field' ) );
	}

	/**
	 * Universal Dynamic Field Shortcode:
	 * [tpd_field key="tpd_agency_name" source="auto" render="text|badges|image|button" default=""]
	 */
	public static function render_dynamic_field( $atts ) {
		$atts = shortcode_atts(
			array(
				'key'     => 'display_name',
				'post_id' => 0,
				'user_id' => 0,
				'source'  => 'auto',
				'render'  => 'text',
				'label'   => '',
				'default' => '',
				'class'   => '',
			),
			$atts,
			'tpd_field'
		);

		$val = class_exists( 'TPD_Tool_CPT' )
			? TPD_Tool_CPT::resolve_field_value( $atts['key'], absint( $atts['post_id'] ), absint( $atts['user_id'] ), $atts['source'] )
			: '';

		if ( '' === $val ) {
			$val = $atts['default'];
		}
		if ( '' === $val ) {
			return '';
		}

		$cls = sanitize_html_class( $atts['class'] );

		if ( 'image' === $atts['render'] ) {
			return sprintf(
				'<img src="%s" alt="%s" class="tpd-sc-img %s" style="max-width:100%%; height:auto;" />',
				esc_url( $val ),
				esc_attr( $atts['key'] ),
				esc_attr( $cls )
			);
		}

		if ( 'badges' === $atts['render'] ) {
			$items = array_filter( array_map( 'trim', explode( ',', $val ) ) );
			$out   = '<span class="tpd-sc-badges ' . esc_attr( $cls ) . '" style="display:inline-flex; flex-wrap:wrap; gap:6px;">';
			foreach ( $items as $item ) {
				$out .= '<span style="background:#e0f2fe; color:#0369a1; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:600;">' . esc_html( $item ) . '</span>';
			}
			$out .= '</span>';
			return $out;
		}

		if ( 'button' === $atts['render'] ) {
			$btn_label = ! empty( $atts['label'] ) ? $atts['label'] : $val;
			return sprintf(
				'<a href="%s" class="tpd-btn tpd-btn-sm tpd-btn-primary %s" style="text-decoration:none;">%s</a>',
				esc_url( $val ),
				esc_attr( $cls ),
				esc_html( $btn_label )
			);
		}

		return wp_kses_post( $val );
	}

	public static function render_advisor_dashboard( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/advisor/dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_supplier_dashboard( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/supplier/dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_admin_dashboard( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/admin/super-admin-dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_advisor_login( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/auth/advisor-login.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_supplier_login( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/auth/supplier-login.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_chat_inbox( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/shared/chat-box.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_saved_suppliers( $atts ) {
		ob_start();
		$saved_ids = TPD_Tool_Favorites::get_saved_supplier_ids();
		?>
		<div class="tpd-saved-suppliers-widget">
			<h4><?php esc_html_e( 'Saved Suppliers', 'tpd-tool' ); ?></h4>
			<?php if ( ! empty( $saved_ids ) ) : ?>
				<ul class="tpd-saved-list">
					<?php foreach ( $saved_ids as $sid ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $sid ) ); ?>"><?php echo esc_html( get_the_title( $sid ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="text-muted"><?php esc_html_e( 'No saved suppliers yet.', 'tpd-tool' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_events_list( $atts ) {
		ob_start();
		$events = TPD_Tool_Events::get_upcoming_events();
		?>
		<div class="tpd-events-compact-list">
			<?php foreach ( $events as $ev ) : ?>
				<div class="tpd-event-row">
					<div class="tpd-event-date-box">
						<span class="tpd-ed-month"><?php echo esc_html( $ev['month'] ); ?></span>
						<span class="tpd-ed-day"><?php echo esc_html( $ev['day'] ); ?></span>
					</div>
					<div class="tpd-event-info">
						<h5><?php echo esc_html( $ev['title'] ); ?></h5>
						<p><?php echo esc_html( $ev['series'] ); ?> · <?php echo esc_html( $ev['time'] ); ?></p>
					</div>
					<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline tpd-reg-event-btn" data-event-id="<?php echo esc_attr( $ev['id'] ); ?>">
						<?php esc_html_e( 'Register', 'tpd-tool' ); ?>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_advisor_registration( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/registration/advisor-registration.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	public static function render_supplier_registration( $atts ) {
		ob_start();
		$template = TPD_TOOL_DIR . 'templates/registration/supplier-registration.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}
}

TPD_Tool_Shortcodes::init();
