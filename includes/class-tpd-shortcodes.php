<?php
/**
 * Shortcodes Registry for TPD Tool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_Tool_Shortcodes {

	public static function init() {
		add_shortcode( 'tpd_advisor_dashboard', array( __CLASS__, 'render_advisor_dashboard' ) );
		add_shortcode( 'tpd_supplier_dashboard', array( __CLASS__, 'render_supplier_dashboard' ) );
		add_shortcode( 'tpd_admin_dashboard', array( __CLASS__, 'render_admin_dashboard' ) );
		add_shortcode( 'tpd_chat_inbox', array( __CLASS__, 'render_chat_inbox' ) );
		add_shortcode( 'tpd_saved_suppliers', array( __CLASS__, 'render_saved_suppliers' ) );
		add_shortcode( 'tpd_events_list', array( __CLASS__, 'render_events_list' ) );
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
}

TPD_Tool_Shortcodes::init();
