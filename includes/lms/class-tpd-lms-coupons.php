<?php
/**
 * Native LMS coupons: percent or fixed discounts on course checkout.
 *
 * Rules (ported from the learning platform's Coupon model):
 * - A coupon works while active, not past the end of its expiry day, and under
 *   its use limit. It applies to one course, or to every course when none is set.
 * - The discount never exceeds the price. A use is counted once, when the
 *   payment is confirmed (TPD_LMS_Student::fulfill()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Coupons {

	public static function normalize_code( $code ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $code ) );
	}

	public static function find_by_code( $code ) {
		global $wpdb;
		$code = self::normalize_code( $code );
		if ( '' === $code ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'coupons' ) . ' WHERE code = %s', $code ) );
	}

	/**
	 * @return string|null Null when usable, otherwise why not.
	 */
	public static function unusable_reason( $coupon, $course ) {
		if ( ! $coupon ) {
			return __( 'That coupon code isn’t valid.', 'tpd-tool' );
		}
		if ( ! (int) $coupon->active ) {
			return __( 'That coupon is no longer active.', 'tpd-tool' );
		}
		if ( $coupon->course_id && (int) $coupon->course_id !== (int) $course->id ) {
			return __( 'That coupon is for a different course.', 'tpd-tool' );
		}
		if ( $coupon->expires_at && strtotime( $coupon->expires_at . ' 23:59:59 ' . wp_timezone_string() ) < time() ) {
			return __( 'That coupon has expired.', 'tpd-tool' );
		}
		if ( null !== $coupon->max_uses && (int) $coupon->uses >= (int) $coupon->max_uses ) {
			return __( 'That coupon has been used up.', 'tpd-tool' );
		}
		return null;
	}

	public static function discount_for( $coupon, $amount ) {
		$amount = (float) $amount;
		$d      = 'percent' === $coupon->type ? $amount * ( (float) $coupon->value / 100 ) : (float) $coupon->value;
		return round( min( $amount, max( 0.0, $d ) ), 2 );
	}

	public static function label( $coupon ) {
		if ( 'percent' === $coupon->type ) {
			return rtrim( rtrim( number_format( (float) $coupon->value, 2, '.', '' ), '0' ), '.' ) . '% off';
		}
		return '$' . number_format( (float) $coupon->value, 2 ) . ' off';
	}

	/** Count one use. Called once per confirmed payment. */
	public static function record_use( $coupon_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . TPD_LMS_Schema::t( 'coupons' ) . ' SET uses = uses + 1 WHERE id = %d', absint( $coupon_id ) ) );
	}

	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'coupons' ) . ' ORDER BY created_at DESC, id DESC' );
	}

	/**
	 * @return int|WP_Error New coupon id.
	 */
	public static function create( array $data ) {
		$code = self::normalize_code( isset( $data['code'] ) ? $data['code'] : '' );
		if ( strlen( $code ) < 3 ) {
			return new WP_Error( 'code_required', __( 'Use a code of at least 3 letters or numbers.', 'tpd-tool' ) );
		}
		if ( self::find_by_code( $code ) ) {
			return new WP_Error( 'code_taken', __( 'That code is already in use. Pick another.', 'tpd-tool' ) );
		}
		$type  = isset( $data['type'] ) && 'fixed' === $data['type'] ? 'fixed' : 'percent';
		$value = round( (float) ( isset( $data['value'] ) ? $data['value'] : 0 ), 2 );
		if ( $value <= 0 || ( 'percent' === $type && $value > 100 ) ) {
			return new WP_Error( 'bad_value', 'percent' === $type ? __( 'The discount must be between 1 and 100%.', 'tpd-tool' ) : __( 'The discount must be more than $0.', 'tpd-tool' ) );
		}
		$course_id = ! empty( $data['course_id'] ) ? absint( $data['course_id'] ) : null;
		if ( $course_id && ! TPD_LMS_Store::get_course( $course_id ) ) {
			return new WP_Error( 'course_missing', __( 'That course no longer exists.', 'tpd-tool' ) );
		}
		$expires = isset( $data['expires_at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $data['expires_at'] ) ? $data['expires_at'] : null;
		$max     = isset( $data['max_uses'] ) && '' !== (string) $data['max_uses'] && absint( $data['max_uses'] ) > 0 ? absint( $data['max_uses'] ) : null;

		$id = TPD_LMS_Store::insert( 'coupons', array(
			'course_id'  => $course_id,
			'created_by' => get_current_user_id(),
			'code'       => $code,
			'type'       => $type,
			'value'      => $value,
			'max_uses'   => $max,
			'expires_at' => $expires,
			'active'     => 1,
		) );
		return $id ? $id : new WP_Error( 'db_error', __( 'The coupon could not be saved. Try again.', 'tpd-tool' ) );
	}
}
