<?php
/**
 * Native LMS emails. Plain text, like TPD's other emails.
 *
 * Mail problems never break enrollment, checkout or proctoring: send() catches
 * failures and logs them (the equivalent of the learning platform's SafeMail).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Mail {

	public static function init() {
		add_action( 'tpd_lms_enrolled', array( __CLASS__, 'on_enrolled' ), 10, 2 );
		add_action( 'tpd_lms_certificate_issued', array( __CLASS__, 'on_certificate' ), 10, 3 );
	}

	/** Address for staff notifications and replies. */
	public static function support_email() {
		$settings = get_option( TPD_LMS_Native::OPT_SETTINGS, array() );
		$email    = is_array( $settings ) && ! empty( $settings['support_email'] ) ? (string) $settings['support_email'] : '';
		return is_email( $email ) ? $email : 'admin@thetarc.com';
	}

	/**
	 * @return bool Whether the email was handed to the mail server.
	 */
	public static function send( $to, $subject, $body ) {
		$to = array_values( array_unique( array_filter( (array) $to, 'is_email' ) ) );
		if ( ! $to ) {
			return false;
		}
		$issuer  = TPD_LMS_Native::issuer_name();
		$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $issuer . ' <' . self::support_email() . '>' );
		$body    = rtrim( $body ) . "\n\n" . $issuer . "\n" . home_url( '/' ) . "\n";
		try {
			$ok = wp_mail( $to, $subject, $body, $headers );
		} catch ( Throwable $e ) {
			$ok = false;
		}
		if ( ! $ok ) {
			error_log( 'TPD LMS: email "' . $subject . '" to ' . implode( ', ', $to ) . ' could not be sent.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
		return (bool) $ok;
	}

	private static function dashboard_url( $user_id, $slug = '' ) {
		$base = class_exists( 'TPD_Tool_Roles' ) && TPD_Tool_Roles::is_supplier( $user_id ) && ! TPD_Tool_Roles::is_advisor( $user_id ) ? '/supplier-dashboard/' : '/advisor-dashboard/';
		return $slug ? add_query_arg( 'lms_course', $slug, home_url( $base ) ) : home_url( $base );
	}

	/* ------------------------------------------------------------------ */
	/* Learner emails                                                      */
	/* ------------------------------------------------------------------ */

	public static function on_enrolled( $enrollment, $course ) {
		$user = get_userdata( (int) $enrollment->user_id );
		if ( ! $user ) {
			return;
		}
		$lines   = array();
		$lines[] = sprintf( 'Hi %s,', $user->display_name );
		$lines[] = '';
		$lines[] = sprintf( 'You’re enrolled in "%s". Your course is ready in Resources & Tools on your dashboard.', $course->title );

		$tx = $enrollment->tx_id && class_exists( 'TPD_Tool_Financial' ) ? TPD_Tool_Financial::get_tx_by_id( (int) $enrollment->tx_id ) : null;
		if ( $tx && (float) $tx->amount > 0 ) {
			$meta    = json_decode( (string) $tx->meta_json, true );
			$lines[] = '';
			$lines[] = 'Your receipt';
			$lines[] = sprintf( 'Reference: %s', $tx->tx_ref );
			$lines[] = sprintf( 'Amount paid: %s %s', number_format( (float) $tx->amount, 2 ), $tx->currency );
			if ( ! empty( $meta['extra']['coupon_code'] ) ) {
				$lines[] = sprintf( 'Coupon: %s (%s off)', $meta['extra']['coupon_code'], number_format( (float) $meta['extra']['discount'], 2 ) );
			}
			$lines[] = sprintf( 'Paid with: %s', ucfirst( (string) $tx->gateway ) );
		}
		if ( $course->access_days && $enrollment->expires_at ) {
			$lines[] = '';
			$lines[] = sprintf( 'Your access lasts until %s.', wp_date( get_option( 'date_format' ), strtotime( $enrollment->expires_at . ' UTC' ) ) );
		}
		$lines[] = '';
		$lines[] = 'Start learning:';
		$lines[] = self::dashboard_url( $user->ID, $course->slug );
		self::send( $user->user_email, sprintf( 'You’re enrolled: %s', $course->title ), implode( "\n", $lines ) );
	}

	public static function on_certificate( $certificate, $enrollment, $course ) {
		$user = get_userdata( (int) $certificate->user_id );
		if ( ! $user ) {
			return;
		}
		$body = implode( "\n", array(
			sprintf( 'Congratulations, %s!', $user->display_name ),
			'',
			sprintf( 'You’ve completed "%s" and earned your certificate.', $course->title ),
			'',
			'View, print or share it here:',
			TPD_LMS_Student::certificate_url( $certificate->code ),
			'',
			sprintf( 'Verification code: %s. Suppliers, host agencies and clients can check it at the link above.', $certificate->code ),
		) );
		self::send( $user->user_email, sprintf( 'Your certificate: %s', $course->title ), $body );
	}
}
