<?php
/**
 * Question import: pasted text, CSV, Excel and Word. Ported from the learning platform's tests.
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

class ImporterTest extends LmsTestCase {

	private $files = array();

	protected function tearDown(): void {
		foreach ( $this->files as $f ) {
			@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		parent::tearDown();
	}

	public function test_pasted_text_format(): void {
		$items = TPD_LMS_Importer::parse_text( <<<'TXT'
RTA final exam question bank

Module 1: Foundations
1. Which body accredits agencies
to issue airline tickets?
A. IATA
B. CLIA
C) ASTA
Answer: A
Explanation: IATA accreditation.

2. Select the discovery call topics.
a) Budget
b) Dates
c) Commission
Answer: a, b
Points: 2

Topic: Module 8
Q3: Host agencies can provide booking tools.
Answer: True

4. What does FIT stand for?
Answer: Flexible independent travel | Free independent traveller

5. Pick the cruise line association.
A. IATA
B. CLIA *
C. ASTA

6. Broken question
A. Only one choice
Answer: Z
TXT
		);

		$this->assertCount( 6, $items );
		list( $one, $two, $three, $four, $five, $six ) = $items;
		$this->assertSame( array( 'Module 1', 'single', "Which body accredits agencies\nto issue airline tickets?" ), array( $one['topic'], $one['type'], $one['prompt'] ) );
		$this->assertSame( array( true, false, false ), array_column( $one['options'], 'correct' ) );
		$this->assertSame( 'ASTA', $one['options'][2]['text'] );
		$this->assertSame( 'IATA accreditation.', $one['explanation'] );
		$this->assertSame( array( 'multiple', 2, array( true, true, false ) ), array( $two['type'], $two['points'], array_column( $two['options'], 'correct' ) ) );
		$this->assertSame( array( 'Module 8', 'true_false', array( true, false ) ), array( $three['topic'], $three['type'], array_column( $three['options'], 'correct' ) ) );
		$this->assertSame( array( 'short', "Flexible independent travel\nFree independent traveller" ), array( $four['type'], $four['accepted_answers'] ) );
		$this->assertSame( array( 'CLIA', true ), array( $five['options'][1]['text'], $five['options'][1]['correct'] ) );
		foreach ( array( $one, $two, $three, $four, $five ) as $ok ) {
			$this->assertSame( array(), $ok['errors'] );
		}
		$this->assertCount( 2, $six['errors'] ); // One choice, unknown answer letter.
	}

	public function test_answer_given_as_letter_and_text_or_text_only(): void {
		$items = TPD_LMS_Importer::parse_text( "1. Q?\nA. Host agency\nB. Supplier\nAnswer: B. Supplier\n\n2. Q2?\nA. Host agency\nB. Supplier\nAnswer: host agency" );
		$this->assertSame( array( false, true ), array_column( $items[0]['options'], 'correct' ) );
		$this->assertSame( array( true, false ), array_column( $items[1]['options'], 'correct' ) );
	}

	public function test_the_template_csv_parses_cleanly(): void {
		// The same rows the builder's "Download the spreadsheet template" button produces, with Excel's BOM.
		$csv = "\xEF\xBB\xBF" . implode( "\r\n", array(
			'"Topic","Type","Question","Option A","Option B","Option C","Option D","Option E","Correct","Points","Explanation"',
			'"Module 1","single","Which body accredits travel agencies to sell airline tickets worldwide?","IATA","CLIA","ASTA","UNWTO","","A","1","IATA accreditation lets agencies issue airline tickets."',
			'"Module 4","multiple","Which of these belong in a client discovery call? Select all that apply.","Budget","Travel dates","The advisor\'s commission rate","Who is travelling","","A, B, D","1",""',
			'"Module 8","true_false","A host agency can provide booking tools and supplier relationships to independent advisors.","","","","","","True","1",""',
			'"Module 12","short","What does the abbreviation FIT stand for in travel?","","","","","","Flexible independent travel | Free independent traveller","1","Both forms are in common use."',
		) );
		$items = TPD_LMS_Importer::parse_file( $this->temp( $csv ), 'bank.csv' );

		$this->assertCount( 4, $items );
		$this->assertSame( array( 'single', 'multiple', 'true_false', 'short' ), array_column( $items, 'type' ) );
		$this->assertSame( array( 'Module 1', 'Module 4', 'Module 8', 'Module 12' ), array_column( $items, 'topic' ) );
		$this->assertSame( array( array(), array(), array(), array() ), array_column( $items, 'errors' ) );
	}

	public function test_semicolon_csv_with_loose_headers(): void {
		$items = TPD_LMS_Importer::parse_file( $this->temp( "Module;Question text;Choice A;Choice B;Answer key\n3;Pick B;No;Yes;B\n" ), 'bank.csv' );
		$this->assertSame( array( 'Module 3', 'Pick B', array( false, true ) ), array( $items[0]['topic'], $items[0]['prompt'], array_column( $items[0]['options'], 'correct' ) ) );
	}

	public function test_excel_preview_then_import_skips_problems_and_duplicates(): void {
		$c    = $this->make_course( array(), array( array( 'type' => 'exam' ) ) );
		$quiz = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][0]->id );

		$items = TPD_LMS_Importer::parse_file( $this->xlsx( array(
			array( 'Module', 'Question', 'A', 'B', 'C', 'D', 'Answer', 'Points' ),
			array( '1', 'Which body accredits agencies?', 'IATA', 'CLIA', 'ASTA', 'UNWTO', 'A', '1' ),
			array( '2', 'Pick the cruise association', 'IATA', 'CLIA', '', '', 'B', '' ),
			array( '2', 'Is TARC for travel advisors?', '', '', '', '', 'TRUE', '' ),
			array( '3', 'Missing its answer', 'Yes', 'No', '', '', '', '' ),
		) ), 'bank.xlsx' );

		$this->assertCount( 4, $items );
		$this->assertSame( 'Mark the correct answer (e.g. "Answer: B").', $items[3]['errors'][0] );

		list( $added, $skipped ) = TPD_LMS_Importer::import( $quiz->id, $items );
		$this->assertSame( array( 3, 1 ), array( $added, $skipped ) );

		$questions = TPD_LMS_Store::questions_for_quiz( $quiz->id );
		$this->assertSame( array( 'Module 1', 'Module 2', 'Module 2' ), array_values( wp_list_pluck( $questions, 'topic' ) ) );
		$this->assertSame( array( 'single', 'single', 'true_false' ), array_values( wp_list_pluck( $questions, 'type' ) ) );
		$correct = array_values( array_filter( $questions[1]->options, function ( $o ) {
			return (int) $o->is_correct;
		} ) );
		$this->assertSame( 'CLIA', $correct[0]->text );

		// The same question again is a duplicate.
		$again = TPD_LMS_Importer::mark_duplicates( $quiz->id, TPD_LMS_Importer::parse_file( $this->xlsx( array(
			array( 'Module', 'Question', 'A', 'B', 'Answer' ),
			array( '1', 'Which body accredits agencies?', 'IATA', 'CLIA', 'A' ),
		) ), 'bank.xlsx' ) );
		$this->assertTrue( $again[0]['duplicate'] );
		$this->assertSame( array( 0, 1 ), TPD_LMS_Importer::import( $quiz->id, $again ) );
	}

	public function test_word_upload_with_automatic_numbering(): void {
		$items = TPD_LMS_Importer::parse_file( $this->docx( array(
			array( 'Module 4: Client discovery', null ),
			array( 'What should a discovery call cover first?', array( 'num' => 1, 'lvl' => 0 ) ),
			array( "The client's goals", array( 'num' => 2, 'lvl' => 0 ) ),
			array( 'Your fees', array( 'num' => 2, 'lvl' => 0 ) ),
			array( 'Answer: A', null ),
			array( 'Host agencies help independent advisors.', array( 'num' => 1, 'lvl' => 0 ) ),
			array( 'Answer: True', null ),
		) ), 'bank.docx' );

		$this->assertCount( 2, $items );
		$this->assertSame(
			array( 'Module 4', 'What should a discovery call cover first?', array( "The client's goals", 'Your fees' ), array( true, false ) ),
			array( $items[0]['topic'], $items[0]['prompt'], array_column( $items[0]['options'], 'text' ), array_column( $items[0]['options'], 'correct' ) )
		);
		$this->assertSame( array( 'true_false', array() ), array( $items[1]['type'], $items[1]['errors'] ) );
	}

	public function test_default_topic_and_plain_text_storage(): void {
		$c     = $this->make_course( array(), array( array( 'type' => 'quiz' ) ) );
		$quiz  = TPD_LMS_Store::quiz_for_lesson( $c['lessons'][0]->id );
		$items = TPD_LMS_Importer::apply_default_topic( TPD_LMS_Importer::parse_text( "1. First <b>bold</b>?\nAnswer: True\n\n2. Second?\nAnswer: False" ), '7' );
		TPD_LMS_Importer::import( $quiz->id, $items );

		$questions = TPD_LMS_Store::questions_for_quiz( $quiz->id );
		$this->assertSame( array( 'Module 7', 'Module 7' ), array_values( wp_list_pluck( $questions, 'topic' ) ) );
		$this->assertStringNotContainsString( '<b>', $questions[0]->prompt, 'Imported text is stored as plain text, not HTML.' );
		$this->assertTrue( TPD_LMS_Importer::mark_duplicates( $quiz->id, TPD_LMS_Importer::parse_text( "1. First <b>bold</b>?\nAnswer: True" ) )[0]['duplicate'], 'Escaped text still matches as a duplicate.' );
	}

	public function test_unknown_or_broken_files_are_refused(): void {
		$this->assertTrue( is_wp_error( TPD_LMS_Importer::parse_file( $this->temp( '%PDF' ), 'bank.pdf' ) ) );
		$this->assertTrue( is_wp_error( TPD_LMS_Importer::parse_file( $this->temp( 'not a zip' ), 'bank.xlsx' ) ) );
		$this->assertTrue( is_wp_error( TPD_LMS_Importer::parse_file( $this->temp( "Topic,Answer\n1,A" ), 'bank.csv' ) ), 'A sheet without a Question column is refused.' );
	}

	/* Helpers ------------------------------------------------------------ */

	private function temp( $content ) {
		$path = tempnam( sys_get_temp_dir(), 'imp' );
		file_put_contents( $path, $content );
		$this->files[] = $path;
		return $path;
	}

	private function xlsx( array $rows ) {
		$strings    = array();
		$sheet_rows = '';
		foreach ( $rows as $r => $row ) {
			$cells = '';
			foreach ( $row as $c => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$ref = chr( 65 + $c ) . ( $r + 1 );
				if ( ctype_digit( $value ) ) {
					$cells .= "<c r=\"$ref\"><v>$value</v></c>";
					continue;
				}
				if ( 'TRUE' === $value ) {
					$cells .= "<c r=\"$ref\" t=\"b\"><v>1</v></c>";
					continue;
				}
				$strings[] = htmlspecialchars( $value, ENT_XML1 );
				$cells    .= "<c r=\"$ref\" t=\"s\"><v>" . ( count( $strings ) - 1 ) . '</v></c>';
			}
			$sheet_rows .= '<row r="' . ( $r + 1 ) . '">' . $cells . '</row>';
		}
		$ns   = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
		$path = $this->temp( '' );
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::OVERWRITE );
		$zip->addFromString( 'xl/sharedStrings.xml', "<sst $ns>" . implode( '', array_map( function ( $s ) {
			return "<si><t>$s</t></si>";
		}, $strings ) ) . '</sst>' );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', "<worksheet $ns><sheetData>$sheet_rows</sheetData></worksheet>" );
		$zip->close();
		return $path;
	}

	/** A minimal .docx. List 1 is decimal (questions), list 2 is upper-letter (choices). */
	private function docx( array $paragraphs ) {
		$w    = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
		$body = '';
		foreach ( $paragraphs as $p ) {
			list( $text, $num ) = $p;
			$ppr   = $num ? "<w:pPr><w:numPr><w:ilvl w:val=\"{$num['lvl']}\"/><w:numId w:val=\"{$num['num']}\"/></w:numPr></w:pPr>" : '';
			$body .= "<w:p>$ppr<w:r><w:t>" . htmlspecialchars( $text, ENT_XML1 ) . '</w:t></w:r></w:p>';
		}
		$numbering = "<w:numbering $w>"
			. '<w:abstractNum w:abstractNumId="10"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum>'
			. '<w:abstractNum w:abstractNumId="20"><w:lvl w:ilvl="0"><w:numFmt w:val="upperLetter"/></w:lvl></w:abstractNum>'
			. '<w:num w:numId="1"><w:abstractNumId w:val="10"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="20"/></w:num>'
			. '</w:numbering>';
		$path = $this->temp( '' );
		$zip  = new ZipArchive();
		$zip->open( $path, ZipArchive::OVERWRITE );
		$zip->addFromString( 'word/document.xml', "<w:document $w><w:body>$body</w:body></w:document>" );
		$zip->addFromString( 'word/numbering.xml', $numbering );
		$zip->close();
		return $path;
	}
}
