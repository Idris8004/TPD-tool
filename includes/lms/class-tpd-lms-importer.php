<?php
/**
 * Native LMS question import: .xlsx, .csv, .docx, .txt files or pasted text.
 *
 * Ported from the TARC learning platform's QuestionImporter. Staff always see a
 * preview first; questions with errors and questions already in the quiz are skipped.
 *
 * Each parsed item: source, type, topic, prompt, options (text, correct),
 * accepted_answers, points, explanation, errors[].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPD_LMS_Importer {

	const MAX_OPTIONS = 8;

	const TEMPLATE_HEADERS = array( 'Topic', 'Type', 'Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Correct', 'Points', 'Explanation' );

	/**
	 * @param string $path     Uploaded file path.
	 * @param string $filename Original file name (for the extension).
	 * @return array|WP_Error Items.
	 */
	public static function parse_file( $path, $filename ) {
		$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		try {
			switch ( $ext ) {
				case 'xlsx':
					return self::parse_rows( self::read_xlsx( $path ) );
				case 'csv':
					return self::parse_rows( self::read_csv( $path ) );
				case 'docx':
					return self::parse_text( self::read_docx( $path ) );
				case 'txt':
				case 'md':
					return self::parse_text( (string) file_get_contents( $path ) );
			}
			return new WP_Error( 'bad_type', __( 'Upload an .xlsx, .csv, .docx or .txt file.', 'tpd-tool' ) );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'unreadable', $e->getMessage() );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Spreadsheets                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * First row is the header. Column names are matched loosely ("Option A", "A", "Choice A"...).
	 */
	public static function parse_rows( array $rows ) {
		$rows = array_values( array_filter( $rows, function ( $r ) {
			return '' !== implode( '', array_map( 'trim', array_map( 'strval', (array) $r ) ) );
		} ) );
		if ( count( $rows ) < 2 ) {
			return array();
		}

		$cols = array();
		foreach ( $rows[0] as $i => $name ) {
			$key = self::column_key( (string) $name );
			if ( null !== $key && ! isset( $cols[ $key ] ) ) {
				$cols[ $key ] = $i;
			}
		}
		if ( ! isset( $cols['prompt'] ) ) {
			throw new RuntimeException( __( 'The first row needs column names, including a "Question" column. Download the template to see the layout.', 'tpd-tool' ) );
		}

		$items = array();
		foreach ( array_slice( $rows, 1 ) as $n => $row ) {
			$get = function ( $key ) use ( $cols, $row ) {
				return isset( $cols[ $key ] ) ? trim( (string) ( isset( $row[ $cols[ $key ] ] ) ? $row[ $cols[ $key ] ] : '' ) ) : '';
			};
			$options = array();
			foreach ( range( 'a', 'h' ) as $letter ) {
				$text = $get( 'opt_' . $letter );
				if ( '' !== $text ) {
					$options[ $letter ] = $text;
				}
			}
			$items[] = self::build( array(
				/* translators: %d: spreadsheet row number */
				'source'      => sprintf( __( 'Row %d', 'tpd-tool' ), $n + 2 ),
				'topic'       => $get( 'topic' ),
				'type'        => $get( 'type' ),
				'prompt'      => $get( 'prompt' ),
				'options'     => $options,
				'answer'      => $get( 'correct' ),
				'points'      => $get( 'points' ),
				'explanation' => $get( 'explanation' ),
			) );
		}
		return $items;
	}

	private static function column_key( $name ) {
		$n = trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $name ) ) );
		if ( preg_match( '/^(?:option|choice|answer option)?\s*([a-h])$/', $n, $m ) ) {
			return 'opt_' . $m[1];
		}
		$map = array(
			'topic'       => array( 'topic', 'module', 'section', 'category', 'chapter' ),
			'type'        => array( 'type', 'question type' ),
			'prompt'      => array( 'question', 'prompt', 'question text' ),
			'correct'     => array( 'correct', 'answer', 'correct answer', 'correct answers', 'answers', 'key', 'answer key' ),
			'points'      => array( 'points', 'point', 'marks', 'score' ),
			'explanation' => array( 'explanation', 'feedback', 'rationale' ),
		);
		foreach ( $map as $key => $names ) {
			if ( in_array( $n, $names, true ) ) {
				return $key;
			}
		}
		return null;
	}

	public static function read_csv( $path ) {
		$content = (string) file_get_contents( $path );
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', $content ); // Excel's UTF-8 marker.
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' );
		}
		$first     = (string) strtok( $content, "\n" );
		$delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';

		$h = fopen( 'php://temp', 'r+' );
		fwrite( $h, $content );
		rewind( $h );
		$rows = array();
		while ( false !== ( $r = fgetcsv( $h, 0, $delimiter, '"', '\\' ) ) ) {
			$rows[] = $r;
		}
		fclose( $h );
		return $rows;
	}

	/**
	 * Reads the first worksheet of an .xlsx file.
	 */
	public static function read_xlsx( $path ) {
		$zip    = self::open_zip( $path, 'xlsx' );
		$shared = array();
		$xml    = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $xml ) {
			foreach ( self::xml( $xml )->si as $si ) {
				$shared[] = isset( $si->t ) ? (string) $si->t : implode( '', array_map( 'strval', $si->xpath( './/*[local-name()="t"]' ) ) );
			}
		}
		$sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		if ( false === $sheet ) {
			for ( $i = 0; $i < $zip->numFiles; $i++ ) {
				if ( preg_match( '#^xl/worksheets/[^/]+\.xml$#', $zip->getNameIndex( $i ) ) ) {
					$sheet = $zip->getFromIndex( $i );
					break;
				}
			}
		}
		$zip->close();
		if ( false === $sheet ) {
			throw new RuntimeException( __( 'That spreadsheet has no worksheet.', 'tpd-tool' ) );
		}

		$rows = array();
		foreach ( self::xml( $sheet )->sheetData->row as $row ) {
			$cells = array();
			$next  = 0;
			foreach ( $row->c as $c ) {
				$col  = isset( $c['r'] ) ? self::column_index( preg_replace( '/\d+/', '', (string) $c['r'] ) ) : $next;
				$next = $col + 1;
				$type = (string) $c['t'];
				if ( 's' === $type ) {
					$value = isset( $shared[ (int) $c->v ] ) ? $shared[ (int) $c->v ] : '';
				} elseif ( 'inlineStr' === $type ) {
					$value = implode( '', array_map( 'strval', $c->xpath( './/*[local-name()="t"]' ) ) );
				} elseif ( 'b' === $type ) {
					$value = '1' === (string) $c->v ? 'TRUE' : 'FALSE';
				} else {
					$value = (string) $c->v;
				}
				$cells[ $col ] = $value;
			}
			if ( $cells ) {
				$filled = array_fill( 0, max( array_keys( $cells ) ) + 1, '' );
				$rows[] = array_replace( $filled, $cells );
			}
		}
		return $rows;
	}

	private static function column_index( $letters ) {
		$n = 0;
		foreach ( str_split( strtoupper( $letters ) ) as $ch ) {
			$n = $n * 26 + ( ord( $ch ) - 64 );
		}
		return max( 0, $n - 1 );
	}

	/* ------------------------------------------------------------------ */
	/* Word documents and plain text                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Paragraph text from a .docx. Word's automatic numbering isn't part of the
	 * text, so it is put back: a lettered list item becomes "A.", "B." (answer
	 * choices) and a numbered one becomes "1." (a question). For bullets or
	 * unknown formats the list level decides: top level is a question, deeper
	 * levels are choices.
	 */
	public static function read_docx( $path ) {
		$zip           = self::open_zip( $path, 'docx' );
		$xml           = $zip->getFromName( 'word/document.xml' );
		$numbering_xml = $zip->getFromName( 'word/numbering.xml' );
		$zip->close();
		$formats = false !== $numbering_xml ? self::list_formats( $numbering_xml ) : array();
		if ( false === $xml ) {
			throw new RuntimeException( __( 'That Word file has no document text.', 'tpd-tool' ) );
		}

		$doc = new DOMDocument();
		if ( ! @$doc->loadXML( $xml, LIBXML_NONET ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			throw new RuntimeException( __( 'That Word file couldn’t be read. Save it again from Word and retry.', 'tpd-tool' ) );
		}
		$xp = new DOMXPath( $doc );
		$xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

		$lines  = array();
		$letter = 0;
		foreach ( $xp->query( '//w:body//w:p' ) as $p ) {
			$text = '';
			foreach ( $xp->query( './/w:t|.//w:tab|.//w:br', $p ) as $node ) {
				if ( 't' === $node->localName ) {
					$text .= $node->textContent;
				} elseif ( 'tab' === $node->localName ) {
					$text .= ' ';
				} else {
					$text .= "\n";
				}
			}
			$text = trim( $text );
			if ( '' === $text ) {
				$lines[] = '';
				continue;
			}

			$numbered = $xp->query( './w:pPr/w:numPr', $p )->length > 0;
			if ( $numbered && ! self::is_marked( $text ) ) {
				$lvl_node = $xp->query( './w:pPr/w:numPr/w:ilvl/@w:val', $p )->item( 0 );
				$num_node = $xp->query( './w:pPr/w:numPr/w:numId/@w:val', $p )->item( 0 );
				$level    = $lvl_node ? (int) $lvl_node->nodeValue : 0;
				$num_id   = $num_node ? (string) $num_node->nodeValue : '';
				$format   = isset( $formats[ $num_id ][ $level ] ) ? $formats[ $num_id ][ $level ] : '';
				$is_choice = false !== strpos( strtolower( $format ), 'letter' ) || ( ! in_array( $format, array( 'decimal', 'decimalZero' ), true ) && $level > 0 );
				if ( $is_choice ) {
					$text = chr( 65 + min( $letter++, 7 ) ) . '. ' . $text;
				} else {
					$text   = '1. ' . $text;
					$letter = 0;
				}
			} else {
				$letter = 0; // Choices are consecutive; anything else starts a new set.
			}
			$lines[] = $text;
		}
		return implode( "\n", $lines );
	}

	/** [numId => [level => numFmt]] from word/numbering.xml. */
	private static function list_formats( $xml ) {
		$doc = new DOMDocument();
		if ( ! @$doc->loadXML( $xml, LIBXML_NONET ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return array();
		}
		$xp = new DOMXPath( $doc );
		$xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
		$val = function ( $query, $context ) use ( $xp ) {
			$n = $xp->query( $query, $context )->item( 0 );
			return $n ? (string) $n->nodeValue : '';
		};

		$abstract = array();
		foreach ( $xp->query( '//w:abstractNum' ) as $a ) {
			foreach ( $xp->query( './w:lvl', $a ) as $lvl ) {
				$abstract[ $a->getAttribute( 'w:abstractNumId' ) ][ (int) $lvl->getAttribute( 'w:ilvl' ) ] = $val( './w:numFmt/@w:val', $lvl );
			}
		}
		$formats = array();
		foreach ( $xp->query( '//w:num' ) as $num ) {
			$abs_id = $val( './w:abstractNumId/@w:val', $num );
			$id     = $num->getAttribute( 'w:numId' );
			$formats[ $id ] = isset( $abstract[ $abs_id ] ) ? $abstract[ $abs_id ] : array();
			foreach ( $xp->query( './w:lvlOverride/w:lvl', $num ) as $lvl ) {
				$formats[ $id ][ (int) $lvl->getAttribute( 'w:ilvl' ) ] = $val( './w:numFmt/@w:val', $lvl );
			}
		}
		return $formats;
	}

	/** True when the text already starts with its own number/letter or is a labelled line. */
	private static function is_marked( $text ) {
		return (bool) preg_match( '/^(?:(?:q(?:uestion)?\s*)?\d+\s*[.):]|\(?[a-h][.)]\s|(?:answer|correct answer|ans|explanation|points?|topic|module)\b)/i', $text );
	}

	/**
	 * Plain-text format:
	 *
	 *   Topic: Module 4            (applies to the questions below it)
	 *   1. Question text?
	 *   A. Choice
	 *   B. Choice
	 *   Answer: B                  (several: "A, C"; true/false: "True"; short answer: "IATA | International Air Transport Association")
	 *   Explanation: ...           (optional)
	 *   Points: 2                  (optional)
	 *
	 * A choice ending with "*" is also taken as correct.
	 */
	public static function parse_text( $text ) {
		$text    = str_replace( array( "\r\n", "\r", "\xC2\xA0" ), array( "\n", "\n", ' ' ), (string) $text );
		$items   = array();
		$topic   = '';
		$current = null;
		$last    = null;
		$line_no = 0;

		$flush = function () use ( &$current, &$items ) {
			if ( $current ) {
				$items[] = self::build( $current );
			}
			$current = null;
		};

		foreach ( explode( "\n", $text ) as $raw ) {
			$line_no++;
			$line = trim( $raw );
			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^(?:topic|module|section)\s*[:\-–]\s*(.+)$/iu', $line, $m ) && preg_match( '/^topic|^section|^module\s*:/i', $line ) ) {
				$flush();
				$topic = preg_match( '/^module/i', $line ) ? 'Module ' . trim( $m[1] ) : trim( $m[1] );
				continue;
			}
			if ( preg_match( '/^module\s+\d+\b/i', $line ) && ! ( $current && ! $current['options'] && '' === $current['answer'] ) ) {
				$flush();
				$topic = $line;
				continue;
			}
			if ( preg_match( '/^(?:q(?:uestion)?\s*)?\d+\s*[.):]\s*(.+)$/i', $line, $m ) ) {
				$flush();
				$current = array(
					/* translators: %d: line number in the pasted text or file */
					'source'      => sprintf( __( 'Line %d', 'tpd-tool' ), $line_no ),
					'topic'       => $topic,
					'type'        => '',
					'prompt'      => trim( $m[1] ),
					'options'     => array(),
					'answer'      => '',
					'points'      => '',
					'explanation' => '',
				);
				$last    = 'prompt';
				continue;
			}
			if ( ! $current ) {
				continue; // Text before the first question (titles, instructions).
			}

			if ( preg_match( '/^(?:correct answers?|answers?|ans|key)\s*[:\-–]\s*(.+)$/iu', $line, $m ) ) {
				$current['answer'] = trim( $m[1] );
				$last              = null;
				continue;
			}
			if ( preg_match( '/^(?:explanation|rationale|feedback)\s*[:\-–]\s*(.*)$/iu', $line, $m ) ) {
				$current['explanation'] = trim( $m[1] );
				$last                   = 'explanation';
				continue;
			}
			if ( preg_match( '/^(?:points?|marks?)\s*[:\-–]\s*(\d+)\s*$/iu', $line, $m ) ) {
				$current['points'] = $m[1];
				$last              = null;
				continue;
			}
			if ( preg_match( '/^(?:type)\s*[:\-–]\s*(.+)$/iu', $line, $m ) ) {
				$current['type'] = trim( $m[1] );
				$last            = null;
				continue;
			}
			if ( preg_match( '/^\(?([a-h])\s*[.)]\s*(.+)$/i', $line, $m ) && '' === $current['answer'] ) {
				$current['options'][ strtolower( $m[1] ) ] = trim( $m[2] );
				$last                                       = 'opt_' . strtolower( $m[1] );
				continue;
			}
			// Continuation of the previous line.
			if ( 'prompt' === $last ) {
				$current['prompt'] .= "\n" . $line;
			} elseif ( 'explanation' === $last ) {
				$current['explanation'] .= ' ' . $line;
			} elseif ( $last && 0 === strpos( $last, 'opt_' ) ) {
				$current['options'][ substr( $last, 4 ) ] .= ' ' . $line;
			}
		}
		$flush();
		return $items;
	}

	/* ------------------------------------------------------------------ */
	/* Shared                                                              */
	/* ------------------------------------------------------------------ */

	/** Normalise one raw question and record what's wrong with it. */
	private static function build( array $raw ) {
		$errors  = array();
		$answer  = trim( (string) $raw['answer'] );
		$options = array();
		foreach ( $raw['options'] as $letter => $text ) {
			$star               = (bool) preg_match( '/\s*\*$/', $text );
			$options[ $letter ] = array( 'text' => trim( preg_replace( '/\s*\*$/', '', $text ) ), 'star' => $star );
		}

		$type = self::type( (string) $raw['type'], $options, $answer );
		if ( null === $type ) {
			/* translators: %s: question type as written in the file */
			$errors[] = sprintf( __( 'Unknown question type "%s".', 'tpd-tool' ), $raw['type'] );
			$type     = 'single';
		}

		$topic = trim( (string) $raw['topic'] );
		$item  = array(
			'source'           => $raw['source'],
			'type'             => $type,
			'topic'            => '' !== $topic ? TPD_LMS_Store::normalize_topic( $topic ) : '',
			'prompt'           => trim( (string) $raw['prompt'] ),
			'options'          => array(),
			'accepted_answers' => null,
			'points'           => 1,
			'explanation'      => '' !== trim( (string) $raw['explanation'] ) ? trim( (string) $raw['explanation'] ) : null,
		);

		if ( '' === $item['prompt'] ) {
			$errors[] = __( 'The question text is empty.', 'tpd-tool' );
		}
		if ( '' !== (string) $raw['points'] ) {
			if ( ctype_digit( (string) $raw['points'] ) && (int) $raw['points'] >= 1 && (int) $raw['points'] <= 100 ) {
				$item['points'] = (int) $raw['points'];
			} else {
				$errors[] = __( 'Points must be a whole number from 1 to 100.', 'tpd-tool' );
			}
		}

		if ( 'short' === $type ) {
			$accepted = array_values( array_filter( array_map( 'trim', preg_split( '/\s*[|\n]\s*/', $answer ) ) ) );
			if ( ! $accepted ) {
				$errors[] = __( 'Add the accepted answer (separate alternatives with "|").', 'tpd-tool' );
			}
			$item['accepted_answers'] = implode( "\n", $accepted );
		} elseif ( 'true_false' === $type ) {
			$value = self::truthy( $answer );
			if ( null === $value ) {
				$errors[] = __( 'The answer must be True or False.', 'tpd-tool' );
			}
			$item['options'] = array(
				array( 'text' => 'True', 'correct' => true === $value ),
				array( 'text' => 'False', 'correct' => false === $value ),
			);
		} else {
			if ( count( $options ) < 2 ) {
				$errors[] = __( 'Add at least two answer choices.', 'tpd-tool' );
			}
			if ( count( $options ) > self::MAX_OPTIONS ) {
				/* translators: %d: maximum number of answer choices */
				$errors[] = sprintf( __( 'Use at most %d answer choices.', 'tpd-tool' ), self::MAX_OPTIONS );
			}
			$correct = self::correct_letters( $answer, $options );
			if ( null === $correct ) {
				/* translators: %s: the answer as written in the file */
				$errors[] = sprintf( __( 'The answer "%s" doesn’t match any choice. Use the letter, e.g. B.', 'tpd-tool' ), $answer );
			} elseif ( ! $correct ) {
				$errors[] = __( 'Mark the correct answer (e.g. "Answer: B").', 'tpd-tool' );
			} elseif ( 'single' === $type && count( $correct ) > 1 ) {
				$errors[] = __( 'One-answer questions can have only one correct choice. Set the type to "multiple" for several.', 'tpd-tool' );
			}
			foreach ( $options as $letter => $o ) {
				$item['options'][] = array(
					'text'    => function_exists( 'mb_substr' ) ? mb_substr( $o['text'], 0, 255 ) : substr( $o['text'], 0, 255 ),
					'correct' => in_array( $letter, (array) $correct, true ),
				);
			}
		}

		$item['errors'] = $errors;
		return $item;
	}

	private static function type( $given, array $options, $answer ) {
		$g = trim( preg_replace( '/[^a-z]+/', ' ', strtolower( $given ) ) );
		if ( '' !== $g ) {
			if ( in_array( $g, array( 'single', 'mc', 'mcq', 'multiple choice', 'one answer', 'single choice', 'radio' ), true ) ) {
				return 'single';
			}
			if ( in_array( $g, array( 'multiple', 'multi', 'multiple answer', 'multiple answers', 'select all', 'checkbox', 'several' ), true ) ) {
				return 'multiple';
			}
			if ( in_array( $g, array( 'true false', 'tf', 'truefalse', 'true or false', 'boolean' ), true ) ) {
				return 'true_false';
			}
			if ( in_array( $g, array( 'short', 'short answer', 'text', 'fill in', 'fill in the blank', 'open' ), true ) ) {
				return 'short';
			}
			return null;
		}
		if ( ! $options ) {
			return null !== self::truthy( $answer ) ? 'true_false' : 'short';
		}
		$stars   = count( array_filter( $options, function ( $o ) {
			return $o['star'];
		} ) );
		$letters = self::correct_letters( $answer, $options );
		return max( $stars, count( (array) $letters ) ) > 1 ? 'multiple' : 'single';
	}

	/**
	 * Letters of the correct choices: from "B", "A, C", "a;c", the choice text
	 * itself, or "*" marks. Null if unmatched.
	 */
	private static function correct_letters( $answer, array $options ) {
		if ( '' === $answer ) {
			return array_keys( array_filter( $options, function ( $o ) {
				return $o['star'];
			} ) );
		}
		// "B. Host agency" or "(b) host agency": the letter decides.
		if ( preg_match( '/^\(?([a-h])\s*[.)]\s+\S/i', trim( $answer ), $m ) ) {
			$letter = strtolower( $m[1] );
			return isset( $options[ $letter ] ) ? array( $letter ) : null;
		}
		$parts = preg_split( '/\s*(?:,|;|\band\b|&|\s)\s*/i', strtolower( trim( $answer ) ), -1, PREG_SPLIT_NO_EMPTY );
		$parts = array_map( function ( $p ) {
			return trim( $p, ' .)(' );
		}, $parts );
		$letters_only = array_filter( $parts, function ( $p ) {
			return (bool) preg_match( '/^[a-h]$/', $p );
		} );
		if ( $parts && $letters_only === $parts ) {
			foreach ( $parts as $p ) {
				if ( ! isset( $options[ $p ] ) ) {
					return null;
				}
			}
			return array_values( array_unique( $parts ) );
		}
		foreach ( $options as $letter => $o ) {
			if ( TPD_LMS_Quiz::normalize( $o['text'] ) === TPD_LMS_Quiz::normalize( $answer ) ) {
				return array( $letter );
			}
		}
		return null;
	}

	private static function truthy( $answer ) {
		$a = TPD_LMS_Quiz::normalize( $answer );
		if ( in_array( $a, array( 'true', 't', 'yes', '1' ), true ) ) {
			return true;
		}
		if ( in_array( $a, array( 'false', 'f', 'no', '0' ), true ) ) {
			return false;
		}
		return null;
	}

	private static function open_zip( $path, $kind ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( __( 'This server can’t open Excel or Word files. Save the file as .csv or .txt, or paste the questions instead.', 'tpd-tool' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			/* translators: %s: file type, xlsx or docx */
			throw new RuntimeException( sprintf( __( 'That file couldn’t be opened as .%s. Save it again from Excel or Word and retry.', 'tpd-tool' ), $kind ) );
		}
		return $zip;
	}

	private static function xml( $xml ) {
		$doc = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
		if ( false === $doc ) {
			throw new RuntimeException( __( 'That file couldn’t be read.', 'tpd-tool' ) );
		}
		return $doc;
	}

	/* ------------------------------------------------------------------ */
	/* Preview and saving                                                  */
	/* ------------------------------------------------------------------ */

	/** Normalised text of questions already in the quiz, for duplicate checks. */
	public static function existing_prompts( $quiz_id ) {
		$out = array();
		foreach ( TPD_LMS_Store::questions_for_quiz( $quiz_id, false ) as $q ) {
			$out[ TPD_LMS_Quiz::normalize( html_entity_decode( wp_strip_all_tags( $q->prompt ), ENT_QUOTES, 'UTF-8' ) ) ] = true;
		}
		return $out;
	}

	/** Give questions without a topic the default one ("7" becomes "Module 7"). */
	public static function apply_default_topic( array $items, $default ) {
		$default = '' !== trim( (string) $default ) ? TPD_LMS_Store::normalize_topic( $default ) : '';
		if ( '' === $default ) {
			return $items;
		}
		foreach ( $items as &$item ) {
			if ( '' === $item['topic'] ) {
				$item['topic'] = $default;
			}
		}
		return $items;
	}

	/** Mark valid items that duplicate a question already in the quiz (or earlier in the file). */
	public static function mark_duplicates( $quiz_id, array $items ) {
		$existing = self::existing_prompts( $quiz_id );
		foreach ( $items as &$item ) {
			$key               = TPD_LMS_Quiz::normalize( $item['prompt'] );
			$item['duplicate'] = ! $item['errors'] && '' !== $key && isset( $existing[ $key ] );
			if ( ! $item['errors'] ) {
				$existing[ $key ] = true;
			}
		}
		return $items;
	}

	/**
	 * Add the valid items to the quiz, skipping errors and duplicates.
	 *
	 * @return array( added, skipped )
	 */
	public static function import( $quiz_id, array $items ) {
		$added   = 0;
		$skipped = 0;
		foreach ( self::mark_duplicates( $quiz_id, $items ) as $item ) {
			if ( $item['errors'] || $item['duplicate'] ) {
				$skipped++;
				continue;
			}
			$options = array();
			foreach ( $item['options'] as $o ) {
				$options[] = array( 'text' => $o['text'], 'is_correct' => $o['correct'] );
			}
			$id = TPD_LMS_Store::create_question( $quiz_id, array(
				'type'             => $item['type'],
				'topic'            => $item['topic'],
				'prompt'           => esc_html( $item['prompt'] ), // Imported text is plain text, never HTML.
				'explanation'      => null !== $item['explanation'] ? esc_html( $item['explanation'] ) : null,
				'accepted_answers' => $item['accepted_answers'],
				'points'           => $item['points'],
			), $options );
			if ( is_wp_error( $id ) ) {
				$skipped++;
			} else {
				$added++;
			}
		}
		return array( $added, $skipped );
	}
}
