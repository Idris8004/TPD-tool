<?php
/**
 * Native LMS course builder: super admin AJAX actions.
 *
 * Every action checks the TPD admin nonce, requires manage_options, and checks
 * that the item belongs to a course the user can manage. Registered only while
 * the native LMS is on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Admin {

	const ACTIONS = array(
		'course_list',
		'course_get',
		'course_save',
		'course_delete',
		'chapter_save',
		'chapter_delete',
		'reorder',
		'lesson_get',
		'lesson_save',
		'lesson_delete',
		'quiz_save',
		'question_save',
		'question_delete',
		'question_import',
	);

	/** Largest question file accepted for import, in bytes. */
	const IMPORT_MAX_BYTES = 5242880;

	public static function init() {
		foreach ( self::ACTIONS as $action ) {
			add_action( 'wp_ajax_tpd_lms_' . $action, array( __CLASS__, 'dispatch' ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_wp_admin' ), 20 );
	}

	/* ------------------------------------------------------------------ */
	/* Assets                                                              */
	/* ------------------------------------------------------------------ */

	public static function enqueue() {
		$uri = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' );
		if ( 'tpd-admin' === $uri || is_page( 'tpd-admin' ) ) {
			self::enqueue_builder();
		}
	}

	public static function enqueue_wp_admin( $hook ) {
		if ( false !== strpos( (string) $hook, 'tpd-super-admin' ) ) {
			self::enqueue_builder();
		}
	}

	private static function enqueue_builder() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$js  = TPD_TOOL_DIR . 'assets/js/tpd-lms-admin.js';
		$css = TPD_TOOL_DIR . 'assets/css/tpd-lms.css';
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'tpd-lms', TPD_TOOL_URL . 'assets/css/tpd-lms.css', array(), file_exists( $css ) ? filemtime( $css ) : TPD_TOOL_VERSION );
		wp_enqueue_script( 'tpd-lms-admin', TPD_TOOL_URL . 'assets/js/tpd-lms-admin.js', array( 'jquery' ), file_exists( $js ) ? filemtime( $js ) : TPD_TOOL_VERSION, true );
		wp_localize_script( 'tpd-lms-admin', 'tpdLmsAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'tpd_admin_nonce' ),
			'tiers'   => self::tier_choices(),
		) );
	}

	private static function tier_choices() {
		$choices = array( 'none' => __( 'Not included (sold separately)', 'tpd-tool' ) );
		$plans   = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_plans() : array();
		foreach ( (array) $plans as $slug => $plan ) {
			/* translators: %s: membership plan name */
			$choices[ $slug ] = sprintf( __( 'Included with %s and above', 'tpd-tool' ), isset( $plan['name'] ) ? $plan['name'] : ucfirst( $slug ) );
		}
		$choices['all'] = __( 'Included for every member', 'tpd-tool' );
		return $choices;
	}

	/* ------------------------------------------------------------------ */
	/* Dispatch and guards                                                 */
	/* ------------------------------------------------------------------ */

	public static function dispatch() {
		check_ajax_referer( 'tpd_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Only super admins can manage courses.', 'tpd-tool' ) ), 403 );
		}
		$action = isset( $_POST['action'] ) ? substr( sanitize_key( wp_unslash( $_POST['action'] ) ), strlen( 'tpd_lms_' ) ) : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown action.', 'tpd-tool' ) ), 400 );
		}
		$result = call_user_func( array( __CLASS__, 'action_' . $action ), self::post() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 422 );
		}
		wp_send_json_success( $result );
	}

	/** POST data with slashes removed. Values are sanitized by the store. */
	private static function post() {
		return wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatch().
	}

	private static function course_or_error( $course_id ) {
		$course = TPD_LMS_Store::get_course( absint( $course_id ) );
		if ( ! $course ) {
			return new WP_Error( 'course_missing', __( 'That course no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		if ( ! TPD_LMS_Store::can_manage_course( $course ) ) {
			return new WP_Error( 'forbidden', __( 'You can’t manage this course.', 'tpd-tool' ) );
		}
		return $course;
	}

	private static function lesson_or_error( $lesson_id ) {
		$lesson = TPD_LMS_Store::find( 'lessons', $lesson_id );
		if ( ! $lesson ) {
			return new WP_Error( 'lesson_missing', __( 'That lesson no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$course = self::course_or_error( $lesson->course_id );
		return is_wp_error( $course ) ? $course : $lesson;
	}

	private static function quiz_or_error( $quiz_id ) {
		$quiz = TPD_LMS_Store::find( 'quizzes', $quiz_id );
		if ( ! $quiz ) {
			return new WP_Error( 'quiz_missing', __( 'That quiz no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$lesson = self::lesson_or_error( $quiz->lesson_id );
		return is_wp_error( $lesson ) ? $lesson : $quiz;
	}

	/* ------------------------------------------------------------------ */
	/* Courses                                                             */
	/* ------------------------------------------------------------------ */

	private static function action_course_list() {
		global $wpdb;
		$out = array();
		foreach ( TPD_LMS_Store::list_courses( 'all' ) as $course ) {
			$out[] = self::course_summary( $course );
		}
		return array( 'courses' => $out );
	}

	private static function action_course_get( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		return is_wp_error( $course ) ? $course : self::course_tree( $course );
	}

	private static function action_course_save( $p ) {
		$fields = array();
		foreach ( array( 'title', 'subtitle', 'description', 'thumbnail_url', 'level', 'price', 'status', 'access_days', 'tier_included', 'slug' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$fields[ $key ] = $p[ $key ];
			}
		}
		foreach ( array( 'sequential', 'drip_enabled', 'certificate_enabled' ) as $flag ) {
			if ( array_key_exists( $flag, $p ) ) {
				$fields[ $flag ] = '1' === (string) $p[ $flag ] ? 1 : 0;
			}
		}
		if ( isset( $fields['tier_included'] ) && ! array_key_exists( $fields['tier_included'], self::tier_choices() ) ) {
			$fields['tier_included'] = 'none';
		}

		$course_id = isset( $p['course_id'] ) ? absint( $p['course_id'] ) : 0;
		if ( $course_id ) {
			$course = self::course_or_error( $course_id );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			if ( isset( $fields['status'] ) && 'published' === $fields['status'] && 0 === count( TPD_LMS_Store::lessons_for_course( $course_id ) ) ) {
				return new WP_Error( 'empty_course', __( 'Add at least one lesson before publishing.', 'tpd-tool' ) );
			}
			$ok = TPD_LMS_Store::update_course( $course_id, $fields );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
		} else {
			$fields['status'] = 'draft'; // New courses start as drafts.
			$course_id        = TPD_LMS_Store::create_course( $fields );
			if ( is_wp_error( $course_id ) ) {
				return $course_id;
			}
		}
		return self::course_tree( TPD_LMS_Store::get_course( $course_id ) );
	}

	private static function action_course_delete( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		if ( self::enrollment_count( $course->id ) > 0 ) {
			return new WP_Error( 'has_students', __( 'Students are enrolled in this course, so it can’t be deleted. Unpublish it instead.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::delete_course( $course->id );
		return array( 'deleted' => (int) $course->id );
	}

	/* ------------------------------------------------------------------ */
	/* Modules and order                                                   */
	/* ------------------------------------------------------------------ */

	private static function action_chapter_save( $p ) {
		$title = isset( $p['title'] ) ? $p['title'] : '';
		if ( ! empty( $p['chapter_id'] ) ) {
			$chapter = TPD_LMS_Store::find( 'chapters', $p['chapter_id'] );
			if ( ! $chapter ) {
				return new WP_Error( 'chapter_missing', __( 'That module no longer exists. Reload the page.', 'tpd-tool' ) );
			}
			$course = self::course_or_error( $chapter->course_id );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			$ok = TPD_LMS_Store::rename_chapter( $chapter->id, $title );
		} else {
			$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			$ok = TPD_LMS_Store::create_chapter( $course->id, $title );
		}
		return is_wp_error( $ok ) ? $ok : self::course_tree( $course );
	}

	private static function action_chapter_delete( $p ) {
		$chapter = TPD_LMS_Store::find( 'chapters', isset( $p['chapter_id'] ) ? $p['chapter_id'] : 0 );
		if ( ! $chapter ) {
			return new WP_Error( 'chapter_missing', __( 'That module no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$course = self::course_or_error( $chapter->course_id );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		TPD_LMS_Store::delete_chapter( $chapter->id );
		foreach ( self::enrollment_ids( $course->id ) as $eid ) {
			TPD_LMS_Progress::recalculate( $eid );
		}
		return self::course_tree( $course );
	}

	private static function action_reorder( $p ) {
		$course = self::course_or_error( isset( $p['course_id'] ) ? $p['course_id'] : 0 );
		if ( is_wp_error( $course ) ) {
			return $course;
		}
		$structure = json_decode( isset( $p['structure'] ) ? (string) $p['structure'] : '', true );
		if ( ! is_array( $structure ) ) {
			return new WP_Error( 'bad_order', __( 'The new order could not be read. Reload the page and try again.', 'tpd-tool' ) );
		}
		$ok = TPD_LMS_Store::reorder( $course->id, $structure );
		return is_wp_error( $ok ) ? $ok : self::course_tree( $course );
	}

	/* ------------------------------------------------------------------ */
	/* Lessons                                                             */
	/* ------------------------------------------------------------------ */

	private static function action_lesson_get( $p ) {
		$lesson = self::lesson_or_error( isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		return is_wp_error( $lesson ) ? $lesson : self::lesson_detail( $lesson );
	}

	private static function action_lesson_save( $p ) {
		$fields = array();
		foreach ( array( 'title', 'type', 'content', 'video_url', 'attachment_id', 'duration_minutes', 'drip_days' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$fields[ $key ] = $p[ $key ];
			}
		}
		if ( array_key_exists( 'is_preview', $p ) ) {
			$fields['is_preview'] = '1' === (string) $p['is_preview'] ? 1 : 0;
		}
		if ( ! empty( $fields['attachment_id'] ) && ! get_post( absint( $fields['attachment_id'] ) ) ) {
			return new WP_Error( 'file_missing', __( 'That file is no longer in the media library. Choose it again.', 'tpd-tool' ) );
		}

		if ( ! empty( $p['lesson_id'] ) ) {
			$lesson = self::lesson_or_error( $p['lesson_id'] );
			if ( is_wp_error( $lesson ) ) {
				return $lesson;
			}
			$ok = TPD_LMS_Store::update_lesson( $lesson->id, $fields );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
			$lesson_id = (int) $lesson->id;
		} else {
			$chapter = TPD_LMS_Store::find( 'chapters', isset( $p['chapter_id'] ) ? $p['chapter_id'] : 0 );
			if ( ! $chapter ) {
				return new WP_Error( 'chapter_missing', __( 'That module no longer exists. Reload the page.', 'tpd-tool' ) );
			}
			$course = self::course_or_error( $chapter->course_id );
			if ( is_wp_error( $course ) ) {
				return $course;
			}
			$lesson_id = TPD_LMS_Store::create_lesson( $chapter->id, $fields );
			if ( is_wp_error( $lesson_id ) ) {
				return $lesson_id;
			}
			foreach ( self::enrollment_ids( $course->id ) as $eid ) {
				TPD_LMS_Progress::recalculate( $eid ); // A new lesson lowers everyone's progress.
			}
		}
		return self::lesson_detail( TPD_LMS_Store::find( 'lessons', $lesson_id ) );
	}

	private static function action_lesson_delete( $p ) {
		$lesson = self::lesson_or_error( isset( $p['lesson_id'] ) ? $p['lesson_id'] : 0 );
		if ( is_wp_error( $lesson ) ) {
			return $lesson;
		}
		$course = TPD_LMS_Store::get_course( $lesson->course_id );
		TPD_LMS_Store::delete_lesson( $lesson->id );
		foreach ( self::enrollment_ids( $course->id ) as $eid ) {
			TPD_LMS_Progress::recalculate( $eid );
		}
		return self::course_tree( $course );
	}

	/* ------------------------------------------------------------------ */
	/* Quizzes and questions                                               */
	/* ------------------------------------------------------------------ */

	private static function action_quiz_save( $p ) {
		$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}
		$fields = array();
		foreach ( array( 'passing_score', 'max_attempts', 'time_limit_minutes', 'questions_per_attempt' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$fields[ $key ] = $p[ $key ];
			}
		}
		foreach ( array( 'shuffle_questions', 'shuffle_options', 'show_answers', 'require_all_lessons' ) as $flag ) {
			if ( array_key_exists( $flag, $p ) ) {
				$fields[ $flag ] = '1' === (string) $p[ $flag ] ? 1 : 0;
			}
		}
		if ( isset( $fields['passing_score'] ) && ( absint( $fields['passing_score'] ) < 1 || absint( $fields['passing_score'] ) > 100 ) ) {
			return new WP_Error( 'bad_pass_mark', __( 'The pass mark must be between 1 and 100%.', 'tpd-tool' ) );
		}
		TPD_LMS_Store::update_quiz( $quiz->id, $fields );
		return self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	private static function action_question_save( $p ) {
		$options = json_decode( isset( $p['options'] ) ? (string) $p['options'] : '[]', true );
		$options = is_array( $options ) ? $options : array();
		$data    = array();
		foreach ( array( 'type', 'prompt', 'explanation', 'accepted_answers', 'points', 'topic' ) as $key ) {
			if ( array_key_exists( $key, $p ) ) {
				$data[ $key ] = $p[ $key ];
			}
		}

		if ( ! empty( $p['question_id'] ) ) {
			$question = TPD_LMS_Store::find( 'questions', $p['question_id'] );
			if ( ! $question ) {
				return new WP_Error( 'question_missing', __( 'That question no longer exists. Reload the page.', 'tpd-tool' ) );
			}
			$quiz = self::quiz_or_error( $question->quiz_id );
			if ( is_wp_error( $quiz ) ) {
				return $quiz;
			}
			$ok = TPD_LMS_Store::update_question( $question->id, $data, $options );
		} else {
			$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
			if ( is_wp_error( $quiz ) ) {
				return $quiz;
			}
			$ok = TPD_LMS_Store::create_question( $quiz->id, $data, $options );
		}
		return is_wp_error( $ok ) ? $ok : self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	private static function action_question_delete( $p ) {
		$question = TPD_LMS_Store::find( 'questions', isset( $p['question_id'] ) ? $p['question_id'] : 0 );
		if ( ! $question ) {
			return new WP_Error( 'question_missing', __( 'That question no longer exists. Reload the page.', 'tpd-tool' ) );
		}
		$quiz = self::quiz_or_error( $question->quiz_id );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}
		TPD_LMS_Store::delete_question( $question->id );
		return self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
	}

	/**
	 * Preview (default) or import questions from an uploaded file or pasted text.
	 * Pass commit=1 to save; the same input is parsed again, so nothing is kept between calls.
	 */
	private static function action_question_import( $p ) {
		$quiz = self::quiz_or_error( isset( $p['quiz_id'] ) ? $p['quiz_id'] : 0 );
		if ( is_wp_error( $quiz ) ) {
			return $quiz;
		}

		$file = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatch().
		if ( $file && ! empty( $file['tmp_name'] ) ) {
			if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				return new WP_Error( 'upload_failed', __( 'The file didn’t upload. Try again.', 'tpd-tool' ) );
			}
			if ( (int) $file['size'] > self::IMPORT_MAX_BYTES ) {
				return new WP_Error( 'too_big', __( 'That file is larger than 5 MB. Split it into smaller files.', 'tpd-tool' ) );
			}
			$items = TPD_LMS_Importer::parse_file( $file['tmp_name'], sanitize_file_name( wp_unslash( $file['name'] ) ) );
		} elseif ( isset( $p['text'] ) && '' !== trim( (string) $p['text'] ) ) {
			try {
				$items = TPD_LMS_Importer::parse_text( (string) $p['text'] );
			} catch ( RuntimeException $e ) {
				$items = new WP_Error( 'unreadable', $e->getMessage() );
			}
		} else {
			return new WP_Error( 'no_input', __( 'Choose a file or paste your questions first.', 'tpd-tool' ) );
		}
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		if ( ! $items ) {
			return new WP_Error( 'no_questions', __( 'No questions were found. Check the format against the template.', 'tpd-tool' ) );
		}

		$items = TPD_LMS_Importer::apply_default_topic( $items, isset( $p['default_topic'] ) ? $p['default_topic'] : '' );

		if ( empty( $p['commit'] ) ) {
			$items = TPD_LMS_Importer::mark_duplicates( $quiz->id, $items );
			return array( 'preview' => $items );
		}

		list( $added, $skipped ) = TPD_LMS_Importer::import( $quiz->id, $items );
		$out           = self::lesson_detail( TPD_LMS_Store::find( 'lessons', $quiz->lesson_id ) );
		$out['import'] = array( 'added' => $added, 'skipped' => $skipped );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Response shapes                                                     */
	/* ------------------------------------------------------------------ */

	private static function enrollment_ids( $course_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE course_id = %d', absint( $course_id ) ) ) );
	}

	private static function enrollment_count( $course_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE course_id = %d', absint( $course_id ) ) );
	}

	private static function course_summary( $course ) {
		$instructor = get_userdata( (int) $course->instructor_id );
		return array(
			'id'            => (int) $course->id,
			'title'         => $course->title,
			'slug'          => $course->slug,
			'subtitle'      => (string) $course->subtitle,
			'status'        => $course->status,
			'price'         => (float) $course->price,
			'thumbnail_url' => (string) $course->thumbnail_url,
			'lesson_count'  => count( TPD_LMS_Store::lessons_for_course( $course->id ) ),
			'student_count' => self::enrollment_count( $course->id ),
			'instructor'    => $instructor ? $instructor->display_name : '',
			'updated_at'    => $course->updated_at,
		);
	}

	private static function course_tree( $course ) {
		$lessons_by_chapter = array();
		foreach ( TPD_LMS_Store::lessons_for_course( $course->id ) as $l ) {
			$quiz                                         = in_array( $l->type, array( 'quiz', 'exam' ), true ) ? TPD_LMS_Store::quiz_for_lesson( $l->id ) : null;
			$lessons_by_chapter[ (int) $l->chapter_id ][] = array(
				'id'             => (int) $l->id,
				'title'          => $l->title,
				'type'           => $l->type,
				'is_preview'     => (bool) $l->is_preview,
				'drip_days'      => null === $l->drip_days ? null : (int) $l->drip_days,
				'question_count' => $quiz ? TPD_LMS_Quiz::question_count( $quiz ) : null,
			);
		}
		$chapters = array();
		foreach ( TPD_LMS_Store::chapters_for_course( $course->id ) as $ch ) {
			$chapters[] = array(
				'id'      => (int) $ch->id,
				'title'   => $ch->title,
				'lessons' => isset( $lessons_by_chapter[ (int) $ch->id ] ) ? $lessons_by_chapter[ (int) $ch->id ] : array(),
			);
		}
		return array(
			'course'   => array_merge( self::course_summary( $course ), array(
				'description'         => (string) $course->description,
				'level'               => $course->level,
				'sequential'          => (bool) $course->sequential,
				'drip_enabled'        => (bool) $course->drip_enabled,
				'certificate_enabled' => (bool) $course->certificate_enabled,
				'access_days'         => null === $course->access_days ? null : (int) $course->access_days,
				'tier_included'       => $course->tier_included,
				'has_final_exam'      => (bool) TPD_LMS_Store::final_exam_for_course( $course->id ),
			) ),
			'chapters' => $chapters,
		);
	}

	private static function lesson_detail( $lesson ) {
		$out = array(
			'lesson' => array(
				'id'               => (int) $lesson->id,
				'course_id'        => (int) $lesson->course_id,
				'chapter_id'       => (int) $lesson->chapter_id,
				'title'            => $lesson->title,
				'type'             => $lesson->type,
				'content'          => (string) $lesson->content,
				'video_url'        => (string) $lesson->video_url,
				'attachment_id'    => $lesson->attachment_id ? (int) $lesson->attachment_id : null,
				'attachment_name'  => $lesson->attachment_id ? basename( (string) get_attached_file( (int) $lesson->attachment_id ) ) : '',
				'duration_minutes' => null === $lesson->duration_minutes ? null : (int) $lesson->duration_minutes,
				'is_preview'       => (bool) $lesson->is_preview,
				'drip_days'        => null === $lesson->drip_days ? null : (int) $lesson->drip_days,
			),
			'quiz'      => null,
			'questions' => array(),
		);
		$quiz = TPD_LMS_Store::quiz_for_lesson( $lesson->id );
		if ( $quiz ) {
			$out['quiz'] = array(
				'id'                    => (int) $quiz->id,
				'is_final'              => (bool) $quiz->is_final,
				'passing_score'         => (int) $quiz->passing_score,
				'max_attempts'          => null === $quiz->max_attempts ? null : (int) $quiz->max_attempts,
				'time_limit_minutes'    => null === $quiz->time_limit_minutes ? null : (int) $quiz->time_limit_minutes,
				'questions_per_attempt' => null === $quiz->questions_per_attempt ? null : (int) $quiz->questions_per_attempt,
				'shuffle_questions'     => (bool) $quiz->shuffle_questions,
				'shuffle_options'       => (bool) $quiz->shuffle_options,
				'show_answers'          => (bool) $quiz->show_answers,
				'require_all_lessons'   => (bool) $quiz->require_all_lessons,
			);
			foreach ( TPD_LMS_Store::questions_for_quiz( $quiz->id ) as $q ) {
				$options = array();
				foreach ( $q->options as $o ) {
					$options[] = array( 'id' => (int) $o->id, 'text' => $o->text, 'is_correct' => (bool) $o->is_correct );
				}
				$out['questions'][] = array(
					'id'               => (int) $q->id,
					'type'             => $q->type,
					'topic'            => (string) $q->topic,
					'prompt'           => $q->prompt,
					'explanation'      => (string) $q->explanation,
					'accepted_answers' => (string) $q->accepted_answers,
					'points'           => (int) $q->points,
					'options'          => $options,
				);
			}
		}
		return $out;
	}
}
