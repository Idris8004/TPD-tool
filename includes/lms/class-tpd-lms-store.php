<?php
/**
 * Native LMS data access: courses, chapters, lessons, quizzes, questions.
 *
 * Every query goes through $wpdb->prepare(). Rich text (descriptions, lesson
 * content, question prompts and explanations) is cleaned with wp_kses_post()
 * when it is saved; templates must still escape on output.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Store {

	const LESSON_TYPES   = array( 'text', 'video', 'download', 'quiz', 'exam' );
	const QUESTION_TYPES = array( 'single', 'multiple', 'true_false', 'short' );

	/* ------------------------------------------------------------------ */
	/* Generic helpers                                                     */
	/* ------------------------------------------------------------------ */

	public static function find( $short, $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( $short ) . ' WHERE id = %d', absint( $id ) ) );
	}

	/**
	 * Insert a row. Adds created_at / updated_at when the table has them.
	 *
	 * @return int New row id, or 0 on failure.
	 */
	public static function insert( $short, array $data, $timestamps = true ) {
		global $wpdb;
		if ( $timestamps ) {
			$now = TPD_LMS_Native::now();
			$data += array( 'created_at' => $now, 'updated_at' => $now );
		}
		$ok = $wpdb->insert( TPD_LMS_Schema::t( $short ), $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function update( $short, $id, array $data, $timestamps = true ) {
		global $wpdb;
		if ( $timestamps ) {
			$data['updated_at'] = TPD_LMS_Native::now();
		}
		return false !== $wpdb->update( TPD_LMS_Schema::t( $short ), $data, array( 'id' => absint( $id ) ) );
	}

	public static function delete( $short, $id ) {
		global $wpdb;
		return (bool) $wpdb->delete( TPD_LMS_Schema::t( $short ), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Courses                                                             */
	/* ------------------------------------------------------------------ */

	public static function get_course( $id_or_slug ) {
		global $wpdb;
		$table = TPD_LMS_Schema::t( 'courses' );
		if ( is_numeric( $id_or_slug ) ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id_or_slug ) ) );
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", sanitize_title( $id_or_slug ) ) );
	}

	/**
	 * @param string $status 'published', 'draft' or 'all'.
	 */
	public static function list_courses( $status = 'published', $instructor_id = 0 ) {
		global $wpdb;
		$table  = TPD_LMS_Schema::t( 'courses' );
		$where  = array( '1=1' );
		$params = array();
		if ( 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}
		if ( $instructor_id ) {
			$where[]  = 'instructor_id = %d';
			$params[] = absint( $instructor_id );
		}
		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC';
		return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
	}

	/**
	 * Create a course. Returns the new id, or a WP_Error.
	 */
	public static function create_course( array $data ) {
		$row = self::course_fields( $data );
		if ( '' === $row['title'] ) {
			return new WP_Error( 'title_required', __( 'Give the course a title.', 'tpd-tool' ) );
		}
		$row['slug']          = self::unique_slug( ! empty( $data['slug'] ) ? $data['slug'] : $row['title'] );
		$row['instructor_id'] = isset( $data['instructor_id'] ) ? absint( $data['instructor_id'] ) : get_current_user_id();
		$id                   = self::insert( 'courses', $row );
		return $id ? $id : new WP_Error( 'db_error', __( 'The course could not be saved. Try again.', 'tpd-tool' ) );
	}

	public static function update_course( $id, array $data ) {
		$row = self::course_fields( $data, true );
		if ( isset( $row['title'] ) && '' === $row['title'] ) {
			return new WP_Error( 'title_required', __( 'Give the course a title.', 'tpd-tool' ) );
		}
		if ( ! empty( $data['slug'] ) ) {
			$row['slug'] = self::unique_slug( $data['slug'], $id );
		}
		return self::update( 'courses', $id, $row );
	}

	/**
	 * Delete a course and everything under it.
	 */
	public static function delete_course( $course_id ) {
		global $wpdb;
		$course_id = absint( $course_id );
		foreach ( self::lessons_for_course( $course_id ) as $lesson ) {
			self::delete_lesson( $lesson->id );
		}
		$enrollment_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . TPD_LMS_Schema::t( 'enrollments' ) . ' WHERE course_id = %d', $course_id ) );
		foreach ( $enrollment_ids as $eid ) {
			$wpdb->delete( TPD_LMS_Schema::t( 'lesson_completions' ), array( 'enrollment_id' => (int) $eid ), array( '%d' ) );
		}
		$wpdb->delete( TPD_LMS_Schema::t( 'enrollments' ), array( 'course_id' => $course_id ), array( '%d' ) );
		$wpdb->delete( TPD_LMS_Schema::t( 'certificates' ), array( 'course_id' => $course_id ), array( '%d' ) );
		$wpdb->delete( TPD_LMS_Schema::t( 'chapters' ), array( 'course_id' => $course_id ), array( '%d' ) );
		$wpdb->delete( TPD_LMS_Schema::t( 'coupons' ), array( 'course_id' => $course_id ), array( '%d' ) );
		return self::delete( 'courses', $course_id );
	}

	private static function course_fields( array $data, $partial = false ) {
		$row  = array();
		$text = array( 'title', 'subtitle', 'level' );
		foreach ( $text as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$row[ $key ] = sanitize_text_field( (string) $data[ $key ] );
			}
		}
		if ( array_key_exists( 'description', $data ) ) {
			$row['description'] = wp_kses_post( (string) $data['description'] );
		}
		if ( array_key_exists( 'thumbnail_url', $data ) ) {
			$row['thumbnail_url'] = esc_url_raw( (string) $data['thumbnail_url'] );
		}
		if ( array_key_exists( 'price', $data ) ) {
			$row['price'] = round( max( 0.0, (float) $data['price'] ), 2 );
		}
		if ( array_key_exists( 'status', $data ) ) {
			$row['status'] = 'published' === $data['status'] ? 'published' : 'draft';
		}
		foreach ( array( 'sequential', 'drip_enabled', 'certificate_enabled' ) as $flag ) {
			if ( array_key_exists( $flag, $data ) ) {
				$row[ $flag ] = empty( $data[ $flag ] ) ? 0 : 1;
			}
		}
		if ( array_key_exists( 'access_days', $data ) ) {
			$row['access_days'] = ( '' === $data['access_days'] || null === $data['access_days'] ) ? null : absint( $data['access_days'] );
		}
		if ( array_key_exists( 'tier_included', $data ) ) {
			$row['tier_included'] = sanitize_key( (string) $data['tier_included'] ) ?: 'none';
		}
		if ( ! $partial ) {
			$row += array( 'title' => '', 'status' => 'draft' );
		}
		return $row;
	}

	public static function unique_slug( $candidate, $ignore_id = 0 ) {
		global $wpdb;
		$table = TPD_LMS_Schema::t( 'courses' );
		$base  = sanitize_title( $candidate );
		if ( '' === $base ) {
			$base = 'course';
		}
		$slug = $base;
		$n    = 2;
		while ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id <> %d", $slug, absint( $ignore_id ) ) ) ) {
			$slug = $base . '-' . $n++;
		}
		return $slug;
	}

	/**
	 * Admins and the course's own instructor can manage a course.
	 */
	public static function can_manage_course( $course, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id || ! $course ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		return (int) $course->instructor_id === $user_id;
	}

	/* ------------------------------------------------------------------ */
	/* Chapters ("modules" in the interface) and lessons                   */
	/* ------------------------------------------------------------------ */

	public static function chapters_for_course( $course_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'chapters' ) . ' WHERE course_id = %d ORDER BY position ASC, id ASC', absint( $course_id ) ) );
	}

	public static function create_chapter( $course_id, $title ) {
		global $wpdb;
		$title = sanitize_text_field( (string) $title );
		if ( '' === $title ) {
			return new WP_Error( 'title_required', __( 'Give the module a title.', 'tpd-tool' ) );
		}
		$next = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(position), 0) + 1 FROM ' . TPD_LMS_Schema::t( 'chapters' ) . ' WHERE course_id = %d', absint( $course_id ) ) );
		return self::insert( 'chapters', array( 'course_id' => absint( $course_id ), 'title' => $title, 'position' => $next ) );
	}

	public static function delete_chapter( $chapter_id ) {
		global $wpdb;
		$lesson_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . TPD_LMS_Schema::t( 'lessons' ) . ' WHERE chapter_id = %d', absint( $chapter_id ) ) );
		foreach ( $lesson_ids as $lid ) {
			self::delete_lesson( $lid );
		}
		return self::delete( 'chapters', $chapter_id );
	}

	/**
	 * Lessons of a course in learning order: by chapter position, then lesson position.
	 */
	public static function lessons_for_course( $course_id ) {
		global $wpdb;
		$l = TPD_LMS_Schema::t( 'lessons' );
		$c = TPD_LMS_Schema::t( 'chapters' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT l.* FROM {$l} l INNER JOIN {$c} c ON c.id = l.chapter_id
			 WHERE l.course_id = %d ORDER BY c.position ASC, c.id ASC, l.position ASC, l.id ASC",
			absint( $course_id )
		) );
	}

	public static function create_lesson( $chapter_id, array $data ) {
		global $wpdb;
		$chapter = self::find( 'chapters', $chapter_id );
		if ( ! $chapter ) {
			return new WP_Error( 'chapter_missing', __( 'That module no longer exists.', 'tpd-tool' ) );
		}
		$row = self::lesson_fields( $data );
		if ( '' === $row['title'] ) {
			return new WP_Error( 'title_required', __( 'Give the lesson a title.', 'tpd-tool' ) );
		}
		if ( 'exam' === $row['type'] && self::final_exam_for_course( $chapter->course_id ) ) {
			return new WP_Error( 'one_final_exam', __( 'This course already has a final exam. A course can have only one.', 'tpd-tool' ) );
		}
		$row['course_id']  = (int) $chapter->course_id;
		$row['chapter_id'] = (int) $chapter->id;
		$row['position']   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(position), 0) + 1 FROM ' . TPD_LMS_Schema::t( 'lessons' ) . ' WHERE chapter_id = %d', (int) $chapter->id ) );

		$lesson_id = self::insert( 'lessons', $row );
		if ( $lesson_id && in_array( $row['type'], array( 'quiz', 'exam' ), true ) ) {
			self::insert( 'quizzes', array(
				'lesson_id'           => $lesson_id,
				'is_final'            => 'exam' === $row['type'] ? 1 : 0,
				'require_all_lessons' => 'exam' === $row['type'] ? 1 : 0,
			) );
		}
		return $lesson_id ? $lesson_id : new WP_Error( 'db_error', __( 'The lesson could not be saved. Try again.', 'tpd-tool' ) );
	}

	public static function update_lesson( $lesson_id, array $data ) {
		$row = self::lesson_fields( $data, true );
		unset( $row['type'] ); // A lesson's type is fixed once created.
		if ( isset( $row['title'] ) && '' === $row['title'] ) {
			return new WP_Error( 'title_required', __( 'Give the lesson a title.', 'tpd-tool' ) );
		}
		return self::update( 'lessons', $lesson_id, $row );
	}

	public static function delete_lesson( $lesson_id ) {
		global $wpdb;
		$lesson_id = absint( $lesson_id );
		$quiz      = self::quiz_for_lesson( $lesson_id );
		if ( $quiz ) {
			self::delete_quiz( $quiz->id );
		}
		$wpdb->delete( TPD_LMS_Schema::t( 'lesson_completions' ), array( 'lesson_id' => $lesson_id ), array( '%d' ) );
		return self::delete( 'lessons', $lesson_id );
	}

	private static function lesson_fields( array $data, $partial = false ) {
		$row = array();
		if ( array_key_exists( 'title', $data ) ) {
			$row['title'] = sanitize_text_field( (string) $data['title'] );
		}
		if ( array_key_exists( 'type', $data ) || ! $partial ) {
			$type        = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'text';
			$row['type'] = in_array( $type, self::LESSON_TYPES, true ) ? $type : 'text';
		}
		if ( array_key_exists( 'content', $data ) ) {
			$row['content'] = wp_kses_post( (string) $data['content'] );
		}
		if ( array_key_exists( 'video_url', $data ) ) {
			$row['video_url'] = esc_url_raw( (string) $data['video_url'] );
		}
		if ( array_key_exists( 'attachment_id', $data ) ) {
			$row['attachment_id'] = $data['attachment_id'] ? absint( $data['attachment_id'] ) : null;
		}
		foreach ( array( 'duration_minutes', 'drip_days' ) as $num ) {
			if ( array_key_exists( $num, $data ) ) {
				$row[ $num ] = ( '' === $data[ $num ] || null === $data[ $num ] ) ? null : absint( $data[ $num ] );
			}
		}
		if ( array_key_exists( 'is_preview', $data ) ) {
			$row['is_preview'] = empty( $data['is_preview'] ) ? 0 : 1;
		}
		if ( ! $partial ) {
			$row += array( 'title' => '' );
		}
		return $row;
	}

	/* ------------------------------------------------------------------ */
	/* Quizzes and questions                                               */
	/* ------------------------------------------------------------------ */

	public static function quiz_for_lesson( $lesson_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'quizzes' ) . ' WHERE lesson_id = %d', absint( $lesson_id ) ) );
	}

	/**
	 * The course's final exam quiz, or null.
	 */
	public static function final_exam_for_course( $course_id ) {
		global $wpdb;
		$q = TPD_LMS_Schema::t( 'quizzes' );
		$l = TPD_LMS_Schema::t( 'lessons' );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT q.* FROM {$q} q INNER JOIN {$l} l ON l.id = q.lesson_id WHERE l.course_id = %d AND q.is_final = 1 LIMIT 1",
			absint( $course_id )
		) );
	}

	public static function update_quiz( $quiz_id, array $data ) {
		$row = array();
		if ( array_key_exists( 'passing_score', $data ) ) {
			$row['passing_score'] = min( 100, absint( $data['passing_score'] ) );
		}
		foreach ( array( 'max_attempts', 'time_limit_minutes', 'questions_per_attempt' ) as $num ) {
			if ( array_key_exists( $num, $data ) ) {
				$row[ $num ] = ( '' === $data[ $num ] || null === $data[ $num ] || 0 === absint( $data[ $num ] ) ) ? null : absint( $data[ $num ] );
			}
		}
		foreach ( array( 'shuffle_questions', 'shuffle_options', 'show_answers', 'require_all_lessons' ) as $flag ) {
			if ( array_key_exists( $flag, $data ) ) {
				$row[ $flag ] = empty( $data[ $flag ] ) ? 0 : 1;
			}
		}
		return self::update( 'quizzes', $quiz_id, $row );
	}

	public static function delete_quiz( $quiz_id ) {
		global $wpdb;
		$quiz_id = absint( $quiz_id );
		foreach ( self::questions_for_quiz( $quiz_id ) as $q ) {
			self::delete_question( $q->id );
		}
		$wpdb->delete( TPD_LMS_Schema::t( 'quiz_attempts' ), array( 'quiz_id' => $quiz_id ), array( '%d' ) );
		$wpdb->delete( TPD_LMS_Schema::t( 'exam_access_codes' ), array( 'quiz_id' => $quiz_id ), array( '%d' ) );
		$wpdb->delete( TPD_LMS_Schema::t( 'proctor_requests' ), array( 'quiz_id' => $quiz_id ), array( '%d' ) );
		return self::delete( 'quizzes', $quiz_id );
	}

	/**
	 * Questions of a quiz in order, each with ->options (ordered) attached.
	 */
	public static function questions_for_quiz( $quiz_id, $with_options = true ) {
		global $wpdb;
		$questions = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'questions' ) . ' WHERE quiz_id = %d ORDER BY position ASC, id ASC', absint( $quiz_id ) ) );
		if ( ! $with_options || ! $questions ) {
			return $questions;
		}
		$ids          = wp_list_pluck( $questions, 'id' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$options      = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . TPD_LMS_Schema::t( 'question_options' ) . " WHERE question_id IN ({$placeholders}) ORDER BY position ASC, id ASC", $ids ) );
		$by_question  = array();
		foreach ( $options as $o ) {
			$by_question[ (int) $o->question_id ][] = $o;
		}
		foreach ( $questions as $q ) {
			$q->options = isset( $by_question[ (int) $q->id ] ) ? $by_question[ (int) $q->id ] : array();
		}
		return $questions;
	}

	/**
	 * Create a question with its options.
	 *
	 * @param array $data    prompt, type, explanation, accepted_answers, points, topic.
	 * @param array $options List of array( 'text' => ..., 'is_correct' => bool ).
	 */
	public static function create_question( $quiz_id, array $data, array $options = array() ) {
		global $wpdb;
		$type   = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'single';
		$type   = in_array( $type, self::QUESTION_TYPES, true ) ? $type : 'single';
		$prompt = wp_kses_post( isset( $data['prompt'] ) ? (string) $data['prompt'] : '' );
		if ( '' === trim( wp_strip_all_tags( $prompt ) ) ) {
			return new WP_Error( 'prompt_required', __( 'Write the question before saving it.', 'tpd-tool' ) );
		}
		if ( 'short' !== $type ) {
			$correct = array_filter( $options, function ( $o ) {
				return ! empty( $o['is_correct'] );
			} );
			if ( count( $options ) < 2 || ! $correct ) {
				return new WP_Error( 'options_required', __( 'Add at least two answers and mark the correct one.', 'tpd-tool' ) );
			}
		}

		$position    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(position), 0) + 1 FROM ' . TPD_LMS_Schema::t( 'questions' ) . ' WHERE quiz_id = %d', absint( $quiz_id ) ) );
		$question_id = self::insert( 'questions', array(
			'quiz_id'          => absint( $quiz_id ),
			'topic'            => isset( $data['topic'] ) && '' !== trim( (string) $data['topic'] ) ? self::normalize_topic( $data['topic'] ) : null,
			'type'             => $type,
			'prompt'           => $prompt,
			'explanation'      => isset( $data['explanation'] ) ? wp_kses_post( (string) $data['explanation'] ) : null,
			'accepted_answers' => isset( $data['accepted_answers'] ) ? sanitize_textarea_field( (string) $data['accepted_answers'] ) : null,
			'points'           => isset( $data['points'] ) ? max( 1, absint( $data['points'] ) ) : 1,
			'position'         => $position,
		) );
		if ( ! $question_id ) {
			return new WP_Error( 'db_error', __( 'The question could not be saved. Try again.', 'tpd-tool' ) );
		}
		if ( 'short' !== $type ) {
			$pos = 1;
			foreach ( $options as $o ) {
				self::insert( 'question_options', array(
					'question_id' => $question_id,
					'text'        => sanitize_text_field( (string) ( isset( $o['text'] ) ? $o['text'] : '' ) ),
					'is_correct'  => empty( $o['is_correct'] ) ? 0 : 1,
					'position'    => $pos++,
				), false );
			}
		}
		return $question_id;
	}

	public static function delete_question( $question_id ) {
		global $wpdb;
		$wpdb->delete( TPD_LMS_Schema::t( 'question_options' ), array( 'question_id' => absint( $question_id ) ), array( '%d' ) );
		return self::delete( 'questions', $question_id );
	}

	/**
	 * "4", "module 4" and "Module 4: Client discovery" all become "Module 4".
	 * Other topics are trimmed and kept as written.
	 */
	public static function normalize_topic( $topic ) {
		$topic = trim( preg_replace( '/\s+/u', ' ', (string) $topic ) );
		if ( preg_match( '/^\d+$/', $topic ) ) {
			return 'Module ' . $topic;
		}
		if ( preg_match( '/^module\s*[:#\-–]?\s*(\d+)\b/iu', $topic, $m ) ) {
			return 'Module ' . $m[1];
		}
		return mb_substr( $topic, 0, 120 );
	}
}
