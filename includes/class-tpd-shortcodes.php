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

		// Client Trip Inquiry Form & Inquire Button Shortcodes
		add_shortcode( 'tpd_advisor_inquiry_form', array( __CLASS__, 'render_inquiry_form' ) );
		add_shortcode( 'tpd_inquire_button', array( __CLASS__, 'render_inquire_button' ) );

		// AJAX Submission Handler for Client Inquiries
		add_action( 'wp_ajax_tpd_submit_client_inquiry', array( __CLASS__, 'ajax_submit_client_inquiry' ) );
		add_action( 'wp_ajax_nopriv_tpd_submit_client_inquiry', array( __CLASS__, 'ajax_submit_client_inquiry' ) );

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

	/**
	 * Render Public Trip Inquiry Form for an Advisor
	 * Usage: [tpd_advisor_inquiry_form advisor_id="123"]
	 */
	public static function render_inquiry_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'advisor_id' => 0,
				'title'      => 'Request a Custom Travel Consultation',
			),
			$atts,
			'tpd_advisor_inquiry_form'
		);

		$advisor_id = absint( $atts['advisor_id'] );
		if ( ! $advisor_id ) {
			$post_id = get_the_ID();
			if ( $post_id && 'travel_advisor' === get_post_type( $post_id ) ) {
				$advisor_id = (int) get_post_meta( $post_id, 'tpd_assigned_user', true ) ?: $post_id;
			}
		}

		ob_start();
		?>
		<div class="tpd-inquiry-form-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:24px; box-shadow:0 4px 14px rgba(0,0,0,0.04); max-width:620px; margin:0 auto;">
			<h4 style="margin:0 0 8px; font-size:18px; color:#0b1526; font-weight:800;"><i class="fa-solid fa-paper-plane text-gold"></i> <?php echo esc_html( $atts['title'] ); ?></h4>
			<p style="margin:0 0 16px; font-size:13px; color:#64748b;"><?php esc_html_e( 'Submit your travel dates, preferred destination, and budget directly to this verified travel advisor.', 'tpd-tool' ); ?></p>

			<form class="tpd-client-inquiry-form" method="post">
				<input type="hidden" name="action" value="tpd_submit_client_inquiry">
				<input type="hidden" name="advisor_id" value="<?php echo esc_attr( $advisor_id ); ?>">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'tpd_nonce' ) ); ?>">

				<div class="tpd-form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Your Name *', 'tpd-tool' ); ?></label>
						<input type="text" name="traveler_name" required placeholder="Full Name" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
					</div>
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Your Email *', 'tpd-tool' ); ?></label>
						<input type="email" name="traveler_email" required placeholder="email@example.com" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
					</div>
				</div>

				<div class="tpd-form-grid-3" style="display:grid; grid-template-columns:repeat(3, 1fr); gap:12px; margin-bottom:12px;">
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Phone Number', 'tpd-tool' ); ?></label>
						<input type="tel" name="traveler_phone" placeholder="+1 (555) 000-0000" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
					</div>
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Destination *', 'tpd-tool' ); ?></label>
						<input type="text" name="destination" required placeholder="e.g. Italy, Caribbean" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
					</div>
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Approx Budget', 'tpd-tool' ); ?></label>
						<select name="budget" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; background:#fff;">
							<option value="$2,500 - $5,000">$2,500 - $5,000</option>
							<option value="$5,000 - $10,000" selected>$5,000 - $10,000</option>
							<option value="$10,000 - $20,000">$10,000 - $20,000</option>
							<option value="$20,000+">$20,000+ (Luxury)</option>
						</select>
					</div>
				</div>

				<div class="tpd-form-grid-2" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Estimated Travel Dates', 'tpd-tool' ); ?></label>
						<input type="text" name="travel_dates" placeholder="e.g. June 2026 or Spring 2026" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
					</div>
					<div>
						<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Number of Travelers', 'tpd-tool' ); ?></label>
						<input type="text" name="passengers" placeholder="e.g. 2 Adults, 2 Kids" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
					</div>
				</div>

				<div style="margin-bottom:14px;">
					<label style="display:block; font-size:12px; font-weight:700; color:#334155; margin-bottom:4px;"><?php esc_html_e( 'Trip Details & Requests *', 'tpd-tool' ); ?></label>
					<textarea name="message" rows="3" required placeholder="Tell the advisor about your dream itinerary, preferences, accommodation style, etc." style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;"></textarea>
				</div>

				<div class="tpd-inquiry-status" style="display:none; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;"></div>

				<button type="submit" class="tpd-btn tpd-btn-darkblue tpd-btn-block" style="width:100%; padding:12px; font-size:14px; font-weight:700;">
					<i class="fa-solid fa-paper-plane"></i> <?php esc_html_e( 'Submit Trip Inquiry', 'tpd-tool' ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Inquire Button that opens modal or scrolls to inquiry form
	 */
	public static function render_inquire_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'advisor_id' => 0,
				'label'      => 'Inquire with Advisor',
				'class'      => '',
			),
			$atts,
			'tpd_inquire_button'
		);

		return sprintf(
			'<button type="button" class="tpd-btn tpd-btn-primary tpd-btn-open-inquiry-modal %s" data-advisor-id="%d"><i class="fa-solid fa-paper-plane"></i> %s</button>',
			esc_attr( $atts['class'] ),
			absint( $atts['advisor_id'] ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * AJAX Handler for Client Trip Inquiry Submissions
	 */
	public static function ajax_submit_client_inquiry() {
		check_ajax_referer( 'tpd_nonce', 'nonce' );

		$name        = isset( $_POST['traveler_name'] ) ? sanitize_text_field( wp_unslash( $_POST['traveler_name'] ) ) : '';
		$email       = isset( $_POST['traveler_email'] ) ? sanitize_email( wp_unslash( $_POST['traveler_email'] ) ) : '';
		$phone       = isset( $_POST['traveler_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['traveler_phone'] ) ) : '';
		$destination = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '';
		$dates       = isset( $_POST['travel_dates'] ) ? sanitize_text_field( wp_unslash( $_POST['travel_dates'] ) ) : '';
		$passengers  = isset( $_POST['passengers'] ) ? sanitize_text_field( wp_unslash( $_POST['passengers'] ) ) : '';
		$budget      = isset( $_POST['budget'] ) ? sanitize_text_field( wp_unslash( $_POST['budget'] ) ) : '';
		$msg         = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$advisor_id  = isset( $_POST['advisor_id'] ) ? absint( $_POST['advisor_id'] ) : 0;

		if ( empty( $name ) || empty( $email ) || empty( $destination ) ) {
			wp_send_json_error( array( 'message' => __( 'Please complete all required fields.', 'tpd-tool' ) ) );
		}

		$post_id = wp_insert_post( array(
			'post_type'    => 'tpd_inquiry',
			'post_status'  => 'publish',
			'post_title'   => sprintf( 'Inquiry from %s for %s', $name, $destination ),
			'post_content' => $msg,
			'post_author'  => $advisor_id ?: 1,
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to submit inquiry. Please try again.', 'tpd-tool' ) ) );
		}

		update_post_meta( $post_id, '_tpd_advisor_id', $advisor_id );
		update_post_meta( $post_id, '_tpd_traveler_name', $name );
		update_post_meta( $post_id, '_tpd_traveler_email', $email );
		update_post_meta( $post_id, '_tpd_traveler_phone', $phone );
		update_post_meta( $post_id, '_tpd_destination', $destination );
		update_post_meta( $post_id, '_tpd_travel_dates', $dates );
		update_post_meta( $post_id, '_tpd_passengers', $passengers );
		update_post_meta( $post_id, '_tpd_budget', $budget );
		update_post_meta( $post_id, '_tpd_status', 'New' );
		update_post_meta( $post_id, '_tpd_timestamp', current_time( 'timestamp' ) );

		// Send email notification to advisor
		$advisor_email = '';
		$advisor_user  = $advisor_id ? get_userdata( $advisor_id ) : null;
		if ( $advisor_user ) {
			$advisor_email = $advisor_user->user_email;
		} elseif ( $advisor_id ) {
			$adv_post_author = (int) get_post_field( 'post_author', $advisor_id );
			$adv_author_user = $adv_post_author ? get_userdata( $adv_post_author ) : null;
			if ( $adv_author_user ) {
				$advisor_email = $adv_author_user->user_email;
			}
		}

		if ( is_email( $advisor_email ) ) {
			$subj = sprintf( '[TPD Lead] New Trip Inquiry from %s on Travel Partner Directory', $name );
			$body = "Hi,\n\nYou have received a new client trip inquiry on Travel Partner Directory:\n\n" .
				"Traveler Name: " . $name . "\n" .
				"Email: " . $email . "\n" .
				"Phone: " . $phone . "\n" .
				"Destination: " . $destination . "\n" .
				"Estimated Dates: " . $dates . "\n" .
				"Passengers: " . $passengers . "\n" .
				"Budget: " . $budget . "\n\n" .
				"Trip Details:\n" . $msg . "\n\n" .
				"Log in to your Advisor Dashboard to manage this lead:\n" .
				home_url( '/advisor-dashboard/?tab=inquiries' ) . "\n\n" .
				"Travel Partner Directory by TARC\n";

			wp_mail( $advisor_email, $subj, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Thank you! Your inquiry has been sent to the advisor. They will be in touch shortly.', 'tpd-tool' ),
		) );
	}
}

TPD_Tool_Shortcodes::init();
