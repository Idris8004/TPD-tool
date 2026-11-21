<?php
/**
 * Native LMS for advisors: course data for the Resources & Tools tab, the lesson
 * player's AJAX actions, checkout hooks, downloads and certificate pages.
 *
 * Every action needs a logged-in, active TPD account, and re-checks the lesson
 * lock rules on the server. Correct answers never reach the browser before an
 * attempt is submitted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Student {

	const ACTIONS = array( 'outline', 'lesson', 'complete', 'quiz_start', 'quiz_save', 'quiz_submit', 'proctor_request', 'proctor_cancel', 'assignment_submit', 'exam_unlock' );

	/** Public certificate page: /certificate-verify/ and /certificate-verify/{CODE}/ */
	const CERT_PATH = 'certificate-verify';

	public static function init() {
		foreach ( self::ACTIONS as $action ) {
			add_action( 'wp_ajax_tpd_lms_s_' . $action, array( __CLASS__, 'dispatch' ) );
		}
		add_action( 'wp_ajax_tpd_lms_download', array( __CLASS__, 'download' ) );
		add_action( 'wp_ajax_tpd_lms_file', array( __CLASS__, 'serve_lesson_file' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_filter( 'template_include', array( __CLASS__, 'certificate_template' ), 1000 );
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                              */
	/* ------------------------------------------------------------------ */

	public static function enqueue() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$uri = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		if ( ! in_array( $uri, array( 'advisor-dashboard', 'advisor-portal', 'supplier-dashboard', 'supplier-portal' ), true ) && ! is_page( array( 'advisor-dashboard', 'supplier-dashboard' ) ) ) {
			return;
		}
		$js  = TPD_TOOL_DIR . 'assets/js/tpd-lms-student.js';
		$css = TPD_TOOL_DIR . 'assets/css/tpd-lms.css';
		wp_enqueue_style( 'tpd-lms', TPD_TOOL_URL . 'assets/css/tpd-lms.css', array(), file_exists( $css ) ? filemtime( $css ) : TPD_TOOL_VERSION );
		wp_enqueue_script( 'tpd-lms-student', TPD_TOOL_URL . 'assets/js/tpd-lms-student.js', array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : TPD_TOOL_VERSION, true );
		wp_localize_script( 'tpd-lms-student', 'tpdLmsStudent', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tpd_lms_student' ),
		) );
	}

	/* ------------------------------------------------------------------ */
	/* Data for the Resources & Tools tab (shapes resources-hub.php uses) */
	/* ------------------------------------------------------------------ */

	/**
	 * Published courses, in the shape resources-hub.php expects.
	 */
	public static function hub_catalog( $user_id ) {
		$out = array();
		foreach ( TPD_LMS_Store::list_courses( 'published' ) as $course ) {
			$lessons    = TPD_LMS_Store::lessons_for_course( $course->id );
			$minutes    = array_sum( array_map( 'intval', wp_list_pluck( $lessons, 'duration_minutes' ) ) );
			$instructor = get_userdata( (int) $course->instructor_id );
			$included   = self::tier_includes( $user_id, $course );
			$out[]      = array(
				'id'                  => (int) $course->id,
				'slug'                => $course->slug,
				'title'               => $course->title,
				'subtitle'            => (string) $course->subtitle,
				'price'               => $included ? 0.0 : (float) $course->price,
				'list_price'          => (float) $course->price,
				'is_free'             => $included || (float) $course->price <= 0,
				'included_with_plan'  => $included && (float) $course->price > 0,
				'thumbnail_url'       => (string) $course->thumbnail_url,
				'lessons_count'       => count( $lessons ),
				'total_minutes'       => $minutes,
				'level'               => $course->level,
				'certificate_enabled' => (bool) $course->certificate_enabled,
				'instructor'          => array( 'name' => $instructor ? $instructor->display_name : __( 'TARC Instructor', 'tpd-tool' ) ),
				'learn_path'          => $course->slug,
			);
		}
		return $out;
	}

	/**
	 * The user's enrollments, in the shape resources-hub.php expects.
	 */
	public static function hub_enrollments( $user_id ) {
		$out = array();
		foreach ( TPD_LMS_Progress::enrollments_for_user( $user_id ) as $e ) {
			$course = TPD_LMS_Store::get_course( (int) $e->course_id );
			if ( ! $course ) {
				continue;
			}
			$cert  = TPD_LMS_Progress::certificate_for_enrollment( (int) $e->id );
			$out[] = array(
				'course_slug'      => $course->slug,
				'course_title'     => $course->title,
				'course_subtitle'  => (string) $course->subtitle,
				'thumbnail_url'    => (string) $course->thumbnail_url,
				'progress_percent' => (int) $e->progress_percent,
				'completed_at'     => $e->completed_at,
				'expired'          => TPD_LMS_Progress::is_expired( $e ),
				'learn_path'       => $course->slug,
				'certificate'      => $cert ? array( 'code' => $cert->code, 'path' => self::certificate_url( $cert->code ) ) : null,
				'exam_card'        => TPD_LMS_ExamCard::state( $e ),
			);
		}
		return $out;
	}

	/**
	 * Whether the user's membership plan includes the course.
	 * Plans are ranked in the order the super admin lists them; "all" includes everyone.
	 */
	public static function tier_includes( $user_id, $course ) {
		$needed = (string) $course->tier_included;
		if ( '' === $needed || 'none' === $needed || ! $user_id ) {
			return false;
		}
		if ( 'all' === $needed ) {
			return true;
		}
		$plans = class_exists( 'TPD_Tool_Settings' ) ? array_keys( (array) TPD_Tool_Settings::get_plans() ) : array( 'basic', 'standard', 'premium' );
		$have  = function_exists( 'tpd_get_user_tier' ) ? tpd_get_user_tier( $user_id ) : 'basic';
		$need_rank = array_search( $needed, $plans, true );
		$have_rank = array_search( $have, $plans, true );
		return false !== $need_rank && false !== $have_rank && $have_rank >= $need_rank;
	}

	/* ------------------------------------------------------------------ */
	/* Checkout hooks (called from TPD_Tool_Financial when native is on)   */
	/* ------------------------------------------------------------------ */

	/**
	 * Server-side price for an LMS course, for TPD_Tool_Financial::resolve_item().
	 */
	public static function resolve_checkout_item( $user_id, $item_id, $currency, $coupon_code = null ) {
		$course = TPD_LMS_Store::get_course( $item_id );
		if ( ! $course || 'published' !== $course->status ) {
			return new WP_Error( 'course_not_found', __( 'That course isn’t available.', 'tpd-tool' ) );
		}
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, $course->id );
		if ( $enrollment && ! TPD_LMS_Progress::is_expired( $enrollment ) ) {
			return new WP_Error( 'already_enrolled', __( 'You’re already enrolled in this course. Open it from My Resources.', 'tpd-tool' ) );
		}
		$price = self::tier_includes( $user_id, $course ) ? 0.0 : (float) $course->price;

		// Coupon from TPD's checkout requests (added by tpd-lms-student.js).
		if ( null === $coupon_code ) {
			$coupon_code = isset( $_POST['lms_coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['lms_coupon'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- TPD's checkout checks its nonce first.
		}
		$extra       = array( 'course_id' => (int) $course->id, 'native' => 1, 'list_price' => round( $price, 2 ) );
		$coupon_note = '';
		$item_extra  = array();
		if ( '' !== trim( (string) $coupon_code ) && $price > 0 ) {
			$coupon = TPD_LMS_Coupons::find_by_code( $coupon_code );
			$reason = TPD_LMS_Coupons::unusable_reason( $coupon, $course );
			if ( null !== $reason ) {
				// The preview shows the reason next to the field; paying with a bad code is refused.
				$is_preview = isset( $_POST['action'] ) && 'tpd_get_checkout_preview' === $_POST['action']; // phpcs:ignore WordPress.Security.NonceVerification
				if ( ! $is_preview ) {
					return new WP_Error( 'bad_coupon', $reason );
				}
				$item_extra['coupon_error'] = $reason;
			} else {
				$discount             = TPD_LMS_Coupons::discount_for( $coupon, $price );
				$price               -= $discount;
				$extra['coupon_id']   = (int) $coupon->id;
				$extra['coupon_code'] = $coupon->code;
				$extra['discount']    = $discount;
				/* translators: 1: coupon code, 2: discount label like "20% off" */
				$coupon_note               = sprintf( __( 'Coupon %1$s: %2$s', 'tpd-tool' ), $coupon->code, TPD_LMS_Coupons::label( $coupon ) );
				$item_extra['coupon_ok']   = $coupon_note;
				$item_extra['list_amount'] = $extra['list_price'];
			}
		}

		return array_merge( array(
			'item_type'     => 'lms_course',
			'item_id'       => $course->slug,
			'item_title'    => $course->title,
			'billing_cycle' => 'once',
			'amount'        => round( max( 0.0, $price ), 2 ),
			'currency'      => $currency,
			'description'   => trim( ( '' !== (string) $course->subtitle ? $course->subtitle : __( 'TARC course enrollment', 'tpd-tool' ) ) . ( $coupon_note ? ' · ' . $coupon_note : '' ) ),
			'extra'         => $extra,
		), $item_extra );
	}

	/**
	 * Enroll the buyer after payment, for TPD_Tool_Financial::fulfill_transaction().
	 *
	 * @return array( ok, error? )
	 */
	public static function fulfill( $tx ) {
		$course = TPD_LMS_Store::get_course( $tx->item_id );
		if ( ! $course ) {
			return array( 'ok' => false, 'error' => __( 'The course no longer exists.', 'tpd-tool' ) );
		}
		$enrollment = TPD_LMS_Progress::get_enrollment( (int) $tx->user_id, $course->id );
		if ( $enrollment && TPD_LMS_Progress::is_expired( $enrollment ) ) {
			// Renewal: give a fresh access period.
			TPD_LMS_Store::update( 'enrollments', $enrollment->id, array(
				'tx_id'      => (int) $tx->id,
				'expires_at' => $course->access_days ? TPD_LMS_Native::now( DAY_IN_SECONDS * (int) $course->access_days ) : null,
			) );
			self::record_coupon_use( $tx );
			return array( 'ok' => true );
		}
		$enrollment = TPD_LMS_Progress::enroll( (int) $tx->user_id, $course->id, (int) $tx->id );
		if ( is_wp_error( $enrollment ) ) {
			return array( 'ok' => false, 'error' => $enrollment->get_error_message() );
		}
		self::record_coupon_use( $tx );
		return array( 'ok' => true );
	}

	/** Count the coupon on this transaction, once (TPD marks the transaction fulfilled right after). */
	private static function record_coupon_use( $tx ) {
		$meta = ! empty( $tx->meta_json ) ? json_decode( (string) $tx->meta_json, true ) : array();
		if ( ! empty( $meta['extra']['coupon_id'] ) && empty( $meta['fulfilled_at'] ) ) {
			TPD_LMS_Coupons::record_use( (int) $meta['extra']['coupon_id'] );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Player AJAX                                                         */
	/* ------------------------------------------------------------------ */

	public static function dispatch() {
		check_ajax_referer( 'tpd_lms_student', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Your session ended. Log in again.', 'tpd-tool' ) ), 401 );
		}
		if ( class_exists( 'TPD_Tool_Roles' ) && ! TPD_Tool_Roles::is_account_active( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Your account is not active.', 'tpd-tool' ) ), 403 );
		}
		$action = isset( $_POST['action'] ) ? substr( sanitize_key( wp_unslash( $_POST['action'] ) ), strlen( 'tpd_lms_s_' ) ) : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'tpd-tool' ) ), 400 );
		}
		$result = call_user_func( array( __CLASS__, 'action_' . $action ), $user_id, wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) {
			$data = array( 'message' => $result->get_error_message() );
			$extra = $result->get_error_data();
			if ( is_array( $extra ) ) {
				$data = array_merge( $data, $extra );
			}
			wp_send_json_error( $data, 422 );
		}
		wp_send_json_success( $result );
	}

	/** Course outline with lock state for this user. Works before enrolling (previews). */
	private static function action_outline( $user_id, $p ) {
		$course = TPD_LMS_Store::get_course( isset( $p['course'] ) ? (string) $p['course'] : '' );
		$manage = $course && TPD_LMS_Store::can_manage_course( $course, $user_id );
		if ( ! $course || ( 'published' !== $course->status && ! $manage ) ) {
			return new WP_Error( 'course_missing', __( 'That course isn’t available.', 'tpd-tool' ) );
		}
		return self::outline( $course, $user_id );
	}

	private static function outline( $course, $user_id ) {
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, $course->id );
		$manage     = TPD_LMS_Store::can_manage_course( $course, $user_id );
		$ordered    = TPD_LMS_Store::lessons_for_course( $course->id );
		$done       = TPD_LMS_Progress::completed_lesson_ids( $enrollment );
		$by_chapter = array();
		$resume     = null;
		$card_exam  = TPD_LMS_ExamCard::exam_lesson( $course );
		foreach ( $ordered as $l ) {
			if ( $card_exam && (int) $l->id === (int) $card_exam->id ) {
				continue; // Shown as its own card, outside the curriculum.
			}
			$lock = $manage ? null : TPD_LMS_Progress::lock_reason( $l, $enrollment, $ordered, $done );
			$is_done = in_array( (int) $l->id, $done, true );
			if ( null === $resume && ! $is_done && null === $lock ) {
				$resume = (int) $l->id;
			}
			$by_chapter[ (int) $l->chapter_id ][] = array(
				'id'         => (int) $l->id,
				'title'      => $l->title,
				'type'       => $l->type,
				'minutes'    => null === $l->duration_minutes ? null : (int) $l->duration_minutes,
				'is_preview' => (bool) $l->is_preview,
				'done'       => $is_done,
				'locked'     => null !== $lock,
				'lock'       => $lock,
			);
		}
		if ( null === $resume && $enrollment && $enrollment->last_lesson_id ) {
			$resume = (int) $enrollment->last_lesson_id;
		}
		$chapters = array();
		foreach ( TPD_LMS_Store::chapters_for_course( $course->id ) as $ch ) {
			$chapters[] = array(
				'id'      => (int) $ch->id,
				'title'   => $ch->title,
				'lessons' => isset( $by_chapter[ (int) $ch->id ] ) ? $by_chapter[ (int) $ch->id ] : array(),
			);
		}
		$cert     = $enrollment ? TPD_LMS_Progress::certificate_for_enrollment( (int) $enrollment->id ) : null;
		$included = self::tier_includes( $user_id, $course );
		return array(
			'course'     => array(
				'id'                  => (int) $course->id,
				'slug'                => $course->slug,
				'title'               => $course->title,
				'subtitle'            => (string) $course->subtitle,
				'description'         => TPD_LMS_Store::clean_html( (string) $course->description ),
				'price'               => $included ? 0.0 : (float) $course->price,
				'is_free'             => $included || (float) $course->price <= 0,
				'included_with_plan'  => $included && (float) $course->price > 0,
				'certificate_enabled' => (bool) $course->certificate_enabled,
				'status'              => $course->status,
			),
			'enrolled'   => (bool) $enrollment,
			'expired'    => TPD_LMS_Progress::is_expired( $enrollment ),
			'preview'    => $manage && ! $enrollment,
			'progress'   => $enrollment ? (int) $enrollment->progress_percent : 0,
			'resume'     => $resume,
			'chapters'   => $chapters,
			'certificate'=> $cert ? array( 'code' => $cert->code, 'url' => self::certificate_url( $cert->code ) ) : null,
			'exam_card'  => $enrollment ? TPD_LMS_ExamCard::state( $enrollment ) : null,
		);
	}

	/** The advisor types an unlock key on the exam card. */
	private static function action_exam_unlock( $user_id, $p ) {
		$course     = TPD_LMS_Store::get_course( isset( $p['course'] ) ? (string) $p['course'] : '' );
		$enrollment = $course ? TPD_LMS_Progress::get_enrollment( $user_id, $course->id ) : null;
		if ( ! $enrollment || ! TPD_LMS_ExamCard::exam_lesson( $course ) ) {
			return new WP_Error( 'no_exam', __( 'That exam isn’t available.', 'tpd-tool' ) );
		}
		$enrollment = TPD_LMS_ExamCard::redeem_key( $enrollment, isset( $p['key'] ) ? (string) $p['key'] : '' );
		return is_wp_error( $enrollment ) ? $enrollment : array( 'exam_card' => TPD_LMS_ExamCard::state( $enrollment ) );
	}

	/** One lesson's content, if the user may open it. */
	private static function action_lesson( $user_id, $p ) {
		$lesson = TPD_LMS_Store::find( 'lessons', isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		$check  = self::can_open( $lesson, $user_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$course     = TPD_LMS_Store::get_course( (int) $lesson->course_id );
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, $course->id );
		if ( $enrollment ) {
			TPD_LMS_Store::update( 'enrollments', $enrollment->id, array( 'last_lesson_id' => (int) $lesson->id, 'last_activity_at' => TPD_LMS_Native::now() ) );
		}

		$out = array(
			'id'       => (int) $lesson->id,
			'title'    => $lesson->title,
			'type'     => $lesson->type,
			'content'  => TPD_LMS_Store::clean_html( (string) $lesson->content ),
			'minutes'  => null === $lesson->duration_minutes ? null : (int) $lesson->duration_minutes,
			'done'     => in_array( (int) $lesson->id, TPD_LMS_Progress::completed_lesson_ids( $enrollment ), true ),
			'can_mark' => (bool) $enrollment && in_array( $lesson->type, TPD_LMS_Store::MARKABLE_TYPES, true ),
			'video'    => null,
			'file'     => null,
			'files'    => array(),
			'quiz'     => null,
		);
		$settings = TPD_LMS_Store::lesson_settings( $lesson );

		switch ( $lesson->type ) {
			case 'pdf':
				if ( $lesson->attachment_id ) {
					$out['pdf'] = array(
						'url'            => self::file_url( $lesson->id, 'pdf' ),
						'name'           => basename( (string) get_attached_file( (int) $lesson->attachment_id ) ),
						'allow_download' => $settings['allow_download'],
					);
				}
				break;
			case 'audio':
				$src = $lesson->attachment_id ? wp_get_attachment_url( (int) $lesson->attachment_id ) : $settings['audio_url'];
				$out['audio'] = $src ? array( 'src' => $src ) : null;
				break;
			case 'multimedia':
				$out['embed'] = '' !== $settings['embed_url'] ? array( 'src' => $settings['embed_url'], 'height' => $settings['height'] ) : null;
				break;
			case 'presentation':
				$slides = array();
				foreach ( $settings['slides'] as $i => $slide ) {
					$slides[] = array(
						'audio' => $slide['audio_id'] ? wp_get_attachment_url( $slide['audio_id'] ) : null,
						'text'  => $slide['text'],
					);
				}
				$out['presentation'] = $lesson->attachment_id ? array(
					'pdf'                => self::file_url( $lesson->id, 'pdf' ),
					'slides'             => $slides,
					'require_all_slides' => $settings['require_all_slides'],
					'auto_advance'       => $settings['auto_advance'],
					'allow_download'     => $settings['allow_download'],
				) : null;
				break;
			case 'live':
				$start = $settings['starts_at'] ? strtotime( $settings['starts_at'] . ' UTC' ) : 0;
				$mins  = $lesson->duration_minutes ? (int) $lesson->duration_minutes : 60;
				$out['live'] = array(
					'join_url'      => $settings['join_url'],
					'starts_at'     => $start ? gmdate( 'c', $start ) : null,
					'ends_at'       => $start ? gmdate( 'c', $start + 60 * $mins ) : null,
					'host_timezone' => $settings['timezone'],
					'recording_url' => $settings['recording_url'],
					'now'           => gmdate( 'c' ),
				);
				break;
			case 'assignment':
				$sub = $enrollment ? TPD_LMS_Assign::latest( $lesson->id, $enrollment->id ) : null;
				$out['assignment'] = array(
					'submission'       => $settings['submission'],
					'require_approval' => $settings['require_approval'],
					'max_mb'           => $settings['max_mb'],
					'extensions'       => TPD_LMS_Store::ASSIGNMENT_EXTENSIONS,
					'latest'           => $sub ? array(
						'status'     => $sub->status,
						'feedback'   => (string) $sub->feedback,
						'file_name'  => (string) $sub->file_name,
						'file_url'   => $sub->file_path ? TPD_LMS_Assign::file_url( $sub ) : null,
						'text'       => TPD_LMS_Store::clean_html( (string) $sub->answer_text ),
						'created_at' => $sub->created_at,
					) : null,
				);
				break;
			case 'download':
				$ids = $settings['files'];
				if ( $lesson->attachment_id && ! in_array( (int) $lesson->attachment_id, $ids, true ) ) {
					array_unshift( $ids, (int) $lesson->attachment_id ); // Lessons made before multi-file downloads.
				}
				foreach ( $ids as $i => $aid ) {
					$path = get_attached_file( $aid );
					if ( $path ) {
						$out['files'][] = array(
							'name' => basename( $path ),
							'size' => file_exists( $path ) ? size_format( filesize( $path ) ) : '',
							'url'  => add_query_arg( array(
								'action'    => 'tpd_lms_download',
								'lesson_id' => (int) $lesson->id,
								'file'      => $aid,
								'nonce'     => wp_create_nonce( 'tpd_lms_download_' . (int) $lesson->id ),
							), admin_url( 'admin-ajax.php' ) ),
						);
					}
				}
				break;
		}

		if ( 'video' === $lesson->type ) {
			$embed = self::video_embed_url( (string) $lesson->video_url );
			if ( $embed ) {
				$out['video'] = array( 'kind' => 'embed', 'src' => $embed );
			} elseif ( $lesson->attachment_id ) {
				$out['video'] = array( 'kind' => 'file', 'src' => wp_get_attachment_url( (int) $lesson->attachment_id ) );
			} elseif ( '' !== (string) $lesson->video_url ) {
				$out['video'] = array( 'kind' => 'link', 'src' => esc_url_raw( $lesson->video_url ) );
			}
		}
		if ( $lesson->attachment_id && 'download' === $lesson->type ) {
			$out['file'] = array(
				'name' => basename( (string) get_attached_file( (int) $lesson->attachment_id ) ),
				'url'  => add_query_arg( array(
					'action'    => 'tpd_lms_download',
					'lesson_id' => (int) $lesson->id,
					'nonce'     => wp_create_nonce( 'tpd_lms_download_' . (int) $lesson->id ),
				), admin_url( 'admin-ajax.php' ) ),
			);
		}
		if ( in_array( $lesson->type, TPD_LMS_Store::QUIZ_TYPES, true ) ) {
			$out['quiz'] = self::quiz_summary( TPD_LMS_Store::quiz_for_lesson( $lesson->id ), $enrollment );
		}
		return $out;
	}

	/** Lock-checked link to a lesson's PDF (PDF and presentation lessons). */
	private static function file_url( $lesson_id, $what ) {
		return add_query_arg( array(
			'action'    => 'tpd_lms_file',
			'lesson_id' => (int) $lesson_id,
			'what'      => $what,
			'nonce'     => wp_create_nonce( 'tpd_lms_file_' . (int) $lesson_id ),
		), admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Stream a PDF/presentation lesson's file inline (for the in-page viewer)
	 * to someone allowed to open the lesson.
	 */
	public static function serve_lesson_file() {
		$lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
		if ( ! wp_verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'tpd_lms_file_' . $lesson_id ) ) {
			wp_die( esc_html__( 'This link has expired. Open the lesson again.', 'tpd-tool' ), '', array( 'response' => 403 ) );
		}
		$lesson = TPD_LMS_Store::find( 'lessons', $lesson_id );
		$check  = self::can_open( $lesson, get_current_user_id() );
		if ( is_wp_error( $check ) || ! $lesson->attachment_id || ! in_array( $lesson->type, array( 'pdf', 'presentation' ), true ) ) {
			wp_die( esc_html( is_wp_error( $check ) ? $check->get_error_message() : __( 'This lesson has no file.', 'tpd-tool' ) ), '', array( 'response' => 403 ) );
		}
		$path = get_attached_file( (int) $lesson->attachment_id );
		if ( ! $path || ! file_exists( $path ) || 'application/pdf' !== get_post_mime_type( (int) $lesson->attachment_id ) ) {
			wp_die( esc_html__( 'The file is missing. Let the course team know.', 'tpd-tool' ), '', array( 'response' => 404 ) );
		}
		$settings = TPD_LMS_Store::lesson_settings( $lesson );
		$download = ! empty( $_GET['download'] ) && ! empty( $settings['allow_download'] );
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="' . str_replace( '"', '', basename( $path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/** Hand in an assignment (multipart: answer text and/or a file). */
	private static function action_assignment_submit( $user_id, $p ) {
		$lesson = TPD_LMS_Store::find( 'lessons', isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		$check  = self::can_open( $lesson, $user_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		if ( 'assignment' !== $lesson->type ) {
			return new WP_Error( 'not_assignment', __( 'This lesson isn’t an assignment.', 'tpd-tool' ) );
		}
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, (int) $lesson->course_id );
		if ( ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'Enroll in the course to hand in assignments.', 'tpd-tool' ) );
		}
		$file = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatch().
		$sub  = TPD_LMS_Assign::submit( $lesson, $enrollment, isset( $p['answer'] ) ? (string) $p['answer'] : '', $file );
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		return array(
			'lesson'  => self::action_lesson( $user_id, array( 'lesson_id' => $lesson->id ) ),
			'outline' => self::outline( TPD_LMS_Store::get_course( (int) $lesson->course_id ), $user_id ),
		);
	}

	private static function action_complete( $user_id, $p ) {
		$lesson = TPD_LMS_Store::find( 'lessons', isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		$check  = self::can_open( $lesson, $user_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		if ( ! in_array( $lesson->type, TPD_LMS_Store::MARKABLE_TYPES, true ) ) {
			$messages = array(
				'survey'     => __( 'Send your survey answers to complete this lesson.', 'tpd-tool' ),
				'assignment' => __( 'Hand in your assignment to complete this lesson.', 'tpd-tool' ),
			);
			return new WP_Error( 'cannot_mark', isset( $messages[ $lesson->type ] ) ? $messages[ $lesson->type ] : __( 'Pass the quiz to complete this lesson.', 'tpd-tool' ) );
		}
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, (int) $lesson->course_id );
		if ( ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'Enroll in the course to track your progress.', 'tpd-tool' ) );
		}
		TPD_LMS_Progress::complete( $enrollment, $lesson );
		return self::outline( TPD_LMS_Store::get_course( (int) $lesson->course_id ), $user_id );
	}

	private static function action_quiz_start( $user_id, $p ) {
		$lesson = TPD_LMS_Store::find( 'lessons', isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		$check  = self::can_open( $lesson, $user_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$quiz       = TPD_LMS_Store::quiz_for_lesson( $lesson ? $lesson->id : 0 );
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, (int) $lesson->course_id );
		if ( ! $quiz ) {
			return new WP_Error( 'no_quiz', __( 'This lesson has no quiz.', 'tpd-tool' ) );
		}
		if ( ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'Enroll in the course to take quizzes.', 'tpd-tool' ) );
		}
		$attempt = TPD_LMS_Exam::start( $quiz, $enrollment, $user_id, isset( $p['access_code'] ) ? (string) $p['access_code'] : '' );
		return is_wp_error( $attempt ) ? $attempt : self::attempt_for_student( $attempt, $quiz );
	}

	/** Ask for a proctor for a code-locked exam. */
	private static function action_proctor_request( $user_id, $p ) {
		$lesson = TPD_LMS_Store::find( 'lessons', isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		$check  = self::can_open( $lesson, $user_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$quiz       = TPD_LMS_Store::quiz_for_lesson( $lesson->id );
		$enrollment = TPD_LMS_Progress::get_enrollment( $user_id, (int) $lesson->course_id );
		if ( ! $quiz || ! $enrollment ) {
			return new WP_Error( 'not_enrolled', __( 'Enroll in the course first.', 'tpd-tool' ) );
		}
		$request = TPD_LMS_Exam::request_proctor( $quiz, $enrollment, $p );
		return is_wp_error( $request ) ? $request : array( 'quiz' => self::quiz_summary( $quiz, $enrollment ) );
	}

	private static function action_proctor_cancel( $user_id, $p ) {
		$request    = TPD_LMS_Store::find( 'proctor_requests', isset( $p['request_id'] ) ? $p['request_id'] : 0 );
		$enrollment = $request ? TPD_LMS_Store::find( 'enrollments', $request->enrollment_id ) : null;
		if ( ! $request || ! $enrollment || (int) $enrollment->user_id !== (int) $user_id ) {
			return new WP_Error( 'request_missing', __( 'That request isn’t available.', 'tpd-tool' ) );
		}
		$ok = TPD_LMS_Exam::cancel_request( $request );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array( 'quiz' => self::quiz_summary( TPD_LMS_Store::find( 'quizzes', $request->quiz_id ), $enrollment ) );
	}

	private static function action_quiz_save( $user_id, $p ) {
		$attempt = self::own_attempt( $user_id, isset( $p['attempt_id'] ) ? $p['attempt_id'] : 0 );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		$saved = TPD_LMS_Quiz::save_answers( $attempt, self::answers_from( $p ) );
		return array( 'saved' => (bool) $saved, 'expired' => TPD_LMS_Quiz::is_past_deadline( $attempt ) );
	}

	private static function action_quiz_submit( $user_id, $p ) {
		$attempt = self::own_attempt( $user_id, isset( $p['attempt_id'] ) ? $p['attempt_id'] : 0 );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		$attempt    = TPD_LMS_Quiz::submit( $attempt, self::answers_from( $p ) );
		$quiz       = TPD_LMS_Store::find( 'quizzes', $attempt->quiz_id );
		$lesson     = TPD_LMS_Store::find( 'lessons', $quiz->lesson_id );
		$enrollment = TPD_LMS_Store::find( 'enrollments', $attempt->enrollment_id );
		return array(
			'result'  => self::result_for_student( $attempt, $quiz ),
			'quiz'    => self::quiz_summary( $quiz, $enrollment ),
			'outline' => self::outline( TPD_LMS_Store::get_course( (int) $lesson->course_id ), $user_id ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Guards and shapes                                                   */
	/* ------------------------------------------------------------------ */

	/** @return true|WP_Error */
	private static function can_open( $lesson, $user_id ) {
		if ( ! $lesson ) {
			return new WP_Error( 'lesson_missing', __( 'That lesson isn’t available.', 'tpd-tool' ) );
		}
		$course = TPD_LMS_Store::get_course( (int) $lesson->course_id );
		$manage = TPD_LMS_Store::can_manage_course( $course, $user_id );
		if ( ! $course || ( 'published' !== $course->status && ! $manage ) ) {
			return new WP_Error( 'lesson_missing', __( 'That lesson isn’t available.', 'tpd-tool' ) );
		}
		$lock = TPD_LMS_Progress::lock_reason_for_user( $lesson, $user_id );
		return null === $lock ? true : new WP_Error( 'locked', $lock );
	}

	private static function own_attempt( $user_id, $attempt_id ) {
		$attempt = TPD_LMS_Quiz::get_attempt( absint( $attempt_id ) );
		if ( ! $attempt || (int) $attempt->user_id !== (int) $user_id ) {
			return new WP_Error( 'attempt_missing', __( 'That attempt isn’t available. Reload and try again.', 'tpd-tool' ) );
		}
		return $attempt;
	}

	/** answers[<question id>] = option id | option ids[] | text */
	private static function answers_from( $p ) {
		$out = array();
		foreach ( (array) ( isset( $p['answers'] ) ? $p['answers'] : array() ) as $qid => $value ) {
			if ( is_array( $value ) ) {
				$out[ absint( $qid ) ] = array_map( 'absint', $value );
			} else {
				$out[ absint( $qid ) ] = sanitize_text_field( (string) $value );
			}
		}
		return $out;
	}

	private static function quiz_summary( $quiz, $enrollment ) {
		if ( ! $quiz ) {
			return null;
		}
		global $wpdb;
		$best = $enrollment ? $wpdb->get_var( $wpdb->prepare(
			'SELECT MAX(score) FROM ' . TPD_LMS_Schema::t( 'quiz_attempts' ) . ' WHERE quiz_id = %d AND enrollment_id = %d AND submitted_at IS NOT NULL',
			(int) $quiz->id,
			(int) $enrollment->id
		) ) : null;
		$used = $enrollment ? TPD_LMS_Quiz::attempts_used( $quiz, $enrollment ) : 0;
		$open = $enrollment ? TPD_LMS_Quiz::open_attempt( $quiz, $enrollment ) : null;

		$request = null;
		if ( $enrollment && 'code' === $quiz->access_mode ) {
			$r = TPD_LMS_Exam::latest_request( $quiz->id, $enrollment->id );
			if ( $r && 'cancelled' !== $r->status ) {
				$code    = $r->exam_access_code_id ? TPD_LMS_Store::find( 'exam_access_codes', $r->exam_access_code_id ) : null;
				$request = array(
					'id'          => (int) $r->id,
					'status'      => $r->status,
					'proctor'     => $r->proctor_name,
					'preferred'   => TPD_LMS_Exam::format_time( $r->preferred_at, $r->timezone ),
					'note'        => (string) $r->decision_note,
					'code_status' => $code ? TPD_LMS_Exam::code_status( $code ) : null,
				);
			}
		}
		$course   = $enrollment ? TPD_LMS_Store::get_course( (int) $enrollment->course_id ) : null;
		$is_staff = $course && TPD_LMS_Store::can_manage_course( $course, get_current_user_id() );
		return array(
			'access_mode'     => $quiz->access_mode,
			'needs_access'    => TPD_LMS_Exam::needs_check( $quiz ) && ! $is_staff && ! ( $open && ! TPD_LMS_Quiz::is_past_deadline( $open ) ),
			'proctor_request' => $request,
			'id'              => (int) $quiz->id,
			'is_final'        => (bool) $quiz->is_final,
			'is_survey'       => TPD_LMS_Store::is_survey_quiz( $quiz->id ),
			'passing_score'   => (int) $quiz->passing_score,
			'max_attempts'    => null === $quiz->max_attempts ? null : (int) $quiz->max_attempts,
			'attempts_used'   => $used,
			'can_attempt'     => $enrollment ? TPD_LMS_Quiz::can_attempt( $quiz, $enrollment ) || ( $open && ! TPD_LMS_Quiz::is_past_deadline( $open ) ) : false,
			'has_open'        => $open && ! TPD_LMS_Quiz::is_past_deadline( $open ),
			'time_limit'      => null === $quiz->time_limit_minutes ? null : (int) $quiz->time_limit_minutes,
			'question_count'  => TPD_LMS_Quiz::attempt_size( $quiz ),
			'best_score'      => null === $best ? null : (int) $best,
		);
	}

	/** Questions for an attempt, without correct answers. */
	private static function attempt_for_student( $attempt, $quiz ) {
		$by_id = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id ) as $q ) {
			$by_id[ (int) $q->id ] = $q;
		}
		$questions = array();
		foreach ( $attempt->question_order as $qid ) {
			if ( ! isset( $by_id[ $qid ] ) ) {
				continue;
			}
			$q       = $by_id[ $qid ];
			$options = array();
			foreach ( $q->options as $o ) {
				$options[] = array( 'id' => (int) $o->id, 'text' => $o->text );
			}
			if ( $quiz->shuffle_options && 'true_false' !== $q->type ) {
				// Same order every time this attempt is shown.
				mt_srand( crc32( $attempt->id . ':' . $qid ) );
				for ( $i = count( $options ) - 1; $i > 0; $i-- ) {
					$j = mt_rand( 0, $i );
					$t = $options[ $i ];
					$options[ $i ] = $options[ $j ];
					$options[ $j ] = $t;
				}
				mt_srand();
			}
			$questions[] = array(
				'id'      => (int) $q->id,
				'type'    => $q->type,
				'prompt'  => TPD_LMS_Store::clean_html( $q->prompt ),
				'points'  => (int) $q->points,
				'options' => 'short' === $q->type ? array() : $options,
			);
		}
		$remaining = $attempt->expires_at ? max( 0, strtotime( $attempt->expires_at . ' UTC' ) - time() ) : null;
		return array(
			'attempt_id'        => (int) $attempt->id,
			'questions'         => $questions,
			'answers'           => (object) $attempt->answers,
			'seconds_remaining' => $remaining,
		);
	}

	/** Result after submitting; correct answers only if the quiz shows them. */
	private static function result_for_student( $attempt, $quiz ) {
		if ( TPD_LMS_Store::is_survey_quiz( $quiz->id ) ) {
			return array( 'survey' => true, 'passed' => true, 'review' => array() );
		}
		$out = array(
			'score'         => (int) $attempt->score,
			'passed'        => (bool) $attempt->passed,
			'passing_score' => (int) $quiz->passing_score,
			'points_earned' => (int) $attempt->points_earned,
			'points_total'  => (int) $attempt->points_total,
			'review'        => array(),
		);
		if ( ! $quiz->show_answers ) {
			return $out;
		}
		$by_id = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id ) as $q ) {
			$by_id[ (int) $q->id ] = $q;
		}
		foreach ( $attempt->question_order as $qid ) {
			if ( ! isset( $by_id[ $qid ] ) ) {
				continue;
			}
			$q       = $by_id[ $qid ];
			$correct = array();
			foreach ( $q->options as $o ) {
				if ( (int) $o->is_correct ) {
					$correct[] = $o->text;
				}
			}
			$given = isset( $attempt->answers[ $qid ] ) ? $attempt->answers[ $qid ] : null;
			$given_text = array();
			foreach ( $q->options as $o ) {
				if ( in_array( (int) $o->id, array_map( 'intval', (array) $given ), true ) ) {
					$given_text[] = $o->text;
				}
			}
			$out['review'][] = array(
				'prompt'      => TPD_LMS_Store::clean_html( $q->prompt ),
				'ok'          => ! empty( $attempt->results[ $qid ] ),
				'given'       => 'short' === $q->type ? (string) $given : implode( ', ', $given_text ),
				'correct'     => 'short' === $q->type ? implode( ' · ', array_filter( preg_split( '/\r?\n/', (string) $q->accepted_answers ) ) ) : implode( ', ', $correct ),
				'explanation' => TPD_LMS_Store::clean_html( (string) $q->explanation ),
			);
		}
		return $out;
	}

	/**
	 * YouTube / Vimeo link to a privacy-friendly embed URL, or '' for anything else.
	 */
	public static function video_embed_url( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
			return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
		}
		if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Downloads                                                           */
	/* ------------------------------------------------------------------ */

	/**
	 * Stream a download lesson's file to someone allowed to open the lesson.
	 */
	public static function download() {
		$lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
		if ( ! wp_verify_nonce( isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '', 'tpd_lms_download_' . $lesson_id ) ) {
			wp_die( esc_html__( 'This download link has expired. Open the lesson again.', 'tpd-tool' ), '', array( 'response' => 403 ) );
		}
		$lesson = TPD_LMS_Store::find( 'lessons', $lesson_id );
		$check  = self::can_open( $lesson, get_current_user_id() );
		if ( is_wp_error( $check ) ) {
			wp_die( esc_html( $check->get_error_message() ), '', array( 'response' => 403 ) );
		}
		// Only files that belong to this lesson can be asked for.
		$allowed = TPD_LMS_Store::lesson_settings( $lesson );
		$allowed = isset( $allowed['files'] ) ? $allowed['files'] : array();
		if ( $lesson->attachment_id ) {
			$allowed[] = (int) $lesson->attachment_id;
		}
		$aid = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : (int) $lesson->attachment_id;
		if ( ! $aid || ! in_array( $aid, $allowed, true ) ) {
			wp_die( esc_html__( 'This lesson has no such file.', 'tpd-tool' ), '', array( 'response' => 403 ) );
		}
		$path = get_attached_file( $aid );
		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'The file is missing. Let the course team know.', 'tpd-tool' ), '', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'Content-Type: ' . ( get_post_mime_type( $aid ) ?: 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', basename( $path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Certificates                                                        */
	/* ------------------------------------------------------------------ */

	public static function certificate_url( $code ) {
		return home_url( '/' . self::CERT_PATH . '/' . rawurlencode( $code ) . '/' );
	}

	/**
	 * Public certificate page: /certificate-verify/ shows a lookup form,
	 * /certificate-verify/{CODE}/ shows the certificate or "not found".
	 */
	public static function certificate_template( $template ) {
		$path  = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		$parts = explode( '/', $path );
		if ( self::CERT_PATH !== $parts[0] ) {
			return $template;
		}
		$code = isset( $parts[1] ) ? strtoupper( sanitize_text_field( rawurldecode( $parts[1] ) ) ) : '';
		if ( '' === $code && isset( $_GET['code'] ) ) {
			$code = strtoupper( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
			if ( '' !== $code ) {
				wp_safe_redirect( self::certificate_url( $code ) );
				exit;
			}
		}
		global $wp_query;
		$wp_query->is_404 = false;
		status_header( 200 );
		set_query_var( 'tpd_lms_cert_code', $code );
		return TPD_TOOL_DIR . 'templates/lms/certificate.php';
	}
}
