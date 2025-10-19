<?php

/**
 * DOCX parsing helper used to extract MasterStudy course structure.
 *
 * @since 1.0.0
 * @package Masterstudy_Lms_Content_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Masterstudy_Lms_Content_Importer_Docx_Parser {

	private const WP_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

	/**
	 * Parse a DOCX file into course/module/lesson/question structure.
	 *
	 * @param string $file_path Absolute path to the uploaded DOCX file.
	 * @param array  $options   Optional configuration (lesson template, identifiers, toc preference).
	 *
	 * @return array
	 *
	 * @throws RuntimeException When the file cannot be parsed.
	 */
	public function parse( string $file_path, array $options = array() ): array {
		$options = array_merge(
			array(
				'lesson_title_template' => '%lesson_source_title%',
				'module_identifier'     => 'Module',
				'lesson_identifier'     => '',
				'use_toc'               => true,
			),
			$options
		);

		$doc        = $this->load_document_dom( $file_path );
		$paragraphs = $this->extract_paragraphs( $doc );

		if ( empty( $paragraphs ) ) {
			throw new RuntimeException( __( 'The document appears to be empty.', 'masterstudy-lms-content-importer' ) );
		}

		$toc_modules = array();

		if ( ! empty( $options['use_toc'] ) ) {
			$toc_modules = $this->build_toc_structure( $paragraphs );
		}

		$course_title    = '';
		$course_intro    = array();
		$modules         = array();
		$current_module  = null;
		$current_lesson  = null;
		$collecting_test = false;
		$module_index    = 0;
		$module_cursor   = 0;

		foreach ( $paragraphs as $paragraph ) {
			$text  = $paragraph['text'];
			$style = $paragraph['style'];

			if ( '' === $text ) {
				continue;
			}

			if ( $this->is_toc_paragraph( $style ) ) {
				continue;
			}

			if ( '' === $course_title ) {
				$course_title = $text;
			}

			$normalized_text = $this->normalize_label( $text );
			$module_template = null;

			if ( isset( $toc_modules[ $module_cursor ] ) && $normalized_text === $toc_modules[ $module_cursor ]['label'] ) {
				$module_template = $toc_modules[ $module_cursor ];
				$module_cursor++;
			}

			if ( $module_template || $this->matches_identifier( $text, $options['module_identifier'] ) ) {
				if ( $current_module ) {
					$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );
					$modules[] = $this->finalize_module( $current_module, $options['lesson_title_template'] );
				}

				$module_index++;

				$current_module = array(
					'title'          => $module_template['title'] ?? $text,
					'label'          => $module_template['label'] ?? $this->normalize_label( $text ),
					'lesson_queue'   => $module_template['lessons'] ?? array(),
					'lessons'        => array(),
					'lesson_counter' => 0,
					'module_index'   => $module_index,
					'quiz_lines'     => array(),
				);

				$current_lesson  = null;
				$collecting_test = false;
				continue;
			}

			if ( ! $current_module ) {
				$course_intro[] = $text;
				continue;
			}

			if ( preg_match( '/^Test\b/i', $text ) ) {
				$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );
				$collecting_test = true;
				continue;
			}

			if ( $collecting_test ) {
				$current_module['quiz_lines'][] = $text;
				continue;
			}

			$lesson_template = null;

			if ( ! empty( $current_module['lesson_queue'] ) ) {
				$next_lesson = $current_module['lesson_queue'][0];

				if ( $normalized_text === $next_lesson['label'] ) {
					array_shift( $current_module['lesson_queue'] );
					$lesson_template = $next_lesson['title'];
				}
			}

			if ( null === $lesson_template && $this->matches_identifier( $text, $options['lesson_identifier'] ) ) {
				$lesson_template = $text;
			}

			if ( null !== $lesson_template ) {
				$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );

				$current_lesson = array(
					'title'        => '',
					'source_title' => $lesson_template,
					'content_lines'=> array(),
					'explicit'     => true,
				);
				continue;
			}

			if ( ! $current_lesson ) {
				$current_lesson = array(
					'title'        => '',
					'source_title' => '',
					'content_lines'=> array(),
					'explicit'     => false,
				);
			}

			$current_lesson['content_lines'][] = $text;
		}

		if ( $current_module ) {
			$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );
			$modules[] = $this->finalize_module( $current_module, $options['lesson_title_template'] );
		}

		return array(
			'title'       => $course_title,
			'description' => $this->format_block( $course_intro ),
			'modules'     => $modules,
		);
	}

	/**
	 * Finalize the current lesson and store it under the current module.
	 *
	 * @param array $module                 Module reference.
	 * @param ?array &$lesson               Lesson reference.
	 * @param string $lesson_title_template Template supplied by the user.
	 */
	private function finalize_current_lesson( array &$module, ?array &$lesson, string $lesson_title_template ): void {
		if ( null === $lesson ) {
			return;
		}

		$lesson_index = $module['lesson_counter'] + 1;
		$module['lesson_counter'] = $lesson_index;

		$title = $lesson['title'];

		if ( '' === $title ) {
			$title = $this->format_lesson_title(
				$lesson_title_template,
				$module['title'],
				$module['module_index'],
				$lesson_index,
				$lesson['source_title']
			);
		}

		$module['lessons'][] = array(
			'title'   => $title,
			'content' => $this->format_block( $lesson['content_lines'] ?? array() ),
		);

		$lesson = null;
	}

	/**
	 * Finalize module data.
	 *
	 * @param array  $module                Raw module data.
	 * @param string $lesson_title_template Template for fallback titles.
	 *
	 * @return array
	 */
	private function finalize_module( array $module, string $lesson_title_template ): array {
		$lessons = $module['lessons'];

		if ( empty( $lessons ) ) {
			$lessons[] = array(
				'title'   => $this->format_lesson_title(
					$lesson_title_template,
					$module['title'],
					$module['module_index'],
					1,
					''
				),
				'content' => '',
			);
		}

		$quiz = $this->parse_quiz( $module['quiz_lines'] ?? array(), $module['title'] );

		return array(
			'title'   => $module['title'],
			'lessons' => $lessons,
			'quiz'    => $quiz,
		);
	}

	/**
	 * Create DOMDocument from DOCX.
	 *
	 * @param string $file_path File path.
	 *
	 * @return DOMDocument
	 *
	 * @throws RuntimeException When the file cannot be read.
	 */
	private function load_document_dom( string $file_path ): DOMDocument {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new RuntimeException( __( 'The ZipArchive PHP extension is required to parse DOCX files.', 'masterstudy-lms-content-importer' ) );
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			throw new RuntimeException( __( 'Unable to open the DOCX file.', 'masterstudy-lms-content-importer' ) );
		}

		$xml = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml ) {
			throw new RuntimeException( __( 'Invalid DOCX structure: missing document.xml.', 'masterstudy-lms-content-importer' ) );
		}

		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;

		if ( ! @$doc->loadXML( $xml ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new RuntimeException( __( 'Unable to read the DOCX XML content.', 'masterstudy-lms-content-importer' ) );
		}

		return $doc;
	}

	/**
	 * Extract paragraphs with their associated styles.
	 *
	 * @param DOMDocument $doc Document object.
	 *
	 * @return array<int, array{text:string, style:string}>
	 */
	private function extract_paragraphs( DOMDocument $doc ): array {
		$xpath = new DOMXPath( $doc );
		$xpath->registerNamespace( 'w', self::WP_NS );

		$paragraphs = array();

		foreach ( $xpath->query( '//w:p' ) as $paragraph ) {
			$text = '';

			foreach ( $xpath->query( './/w:t', $paragraph ) as $text_node ) {
				$text .= $text_node->nodeValue;
			}

			$text  = preg_replace( '/\s+/u', ' ', trim( $text ) );
			$style = '';

			/** @var DOMNode $paragraph */
			$style_node = $xpath->query( './w:pPr/w:pStyle', $paragraph )->item( 0 );

			if ( $style_node instanceof DOMNode ) {
				$style = $style_node->attributes->getNamedItemNS( self::WP_NS, 'val' );
				$style = $style ? $style->nodeValue : '';
			}

			if ( '' !== $text ) {
				$paragraphs[] = array(
					'text'  => $text,
					'style' => $style,
				);
			}
		}

		return $paragraphs;
	}

	/**
	 * Build TOC hierarchy from paragraphs.
	 *
	 * @param array $paragraphs Paragraph collection.
	 *
	 * @return array<int, array{title:string,label:string,lessons:array<int, array{title:string,label:string}>}>
	 */
	private function build_toc_structure( array $paragraphs ): array {
		$modules        = array();
		$current_module = null;

		foreach ( $paragraphs as $paragraph ) {
			$style = $paragraph['style'];
			$text  = $this->cleanup_toc_title( $paragraph['text'] );

			if ( '' === $text || '' === $style ) {
				continue;
			}

			if ( stripos( $style, 'TOC1' ) === 0 ) {
				$modules[]      = array(
					'title'   => $text,
					'label'   => $this->normalize_label( $text ),
					'lessons' => array(),
				);
				$current_module = count( $modules ) - 1;
				continue;
			}

			if ( stripos( $style, 'TOC2' ) === 0 && null !== $current_module ) {
				$modules[ $current_module ]['lessons'][] = array(
					'title' => $text,
					'label' => $this->normalize_label( $text ),
				);
			}
		}

		return $modules;
	}

	/**
	 * Determine if the style corresponds to TOC.
	 *
	 * @param string $style Paragraph style.
	 *
	 * @return bool
	 */
	private function is_toc_paragraph( string $style ): bool {
		return stripos( $style, 'TOC' ) === 0;
	}

	/**
	 * Cleanup TOC entry removing dot leaders and page numbers.
	 *
	 * @param string $title Raw TOC title.
	 *
	 * @return string
	 */
	private function cleanup_toc_title( string $title ): string {
		$title = preg_replace( '/\.{2,}/', ' ', $title );
		$title = preg_replace( '/\s+\d+$/', '', $title );

		return trim( $title );
	}

	/**
	 * Normalize label for comparisons.
	 *
	 * @param string $text Raw text.
	 *
	 * @return string
	 */
	private function normalize_label( string $text ): string {
		$text = $this->cleanup_toc_title( $text );
		$text = strtolower( $text );

		return preg_replace( '/\s+/', ' ', trim( $text ) );
	}

	/**
	 * Determine if text matches identifier prefix.
	 *
	 * @param string $text       Paragraph text.
	 * @param string $identifier Identifier provided by user.
	 *
	 * @return bool
	 */
	private function matches_identifier( string $text, string $identifier ): bool {
		if ( '' === $identifier ) {
			return false;
		}

		$text       = $this->normalize_label( $text );
		$identifier = strtolower( trim( $identifier ) );

		return 0 === strpos( $text, $identifier );
	}

	/**
	 * Convert lines into wpautop formatted block.
	 *
	 * @param array $lines Lines to join.
	 *
	 * @return string
	 */
	private function format_block( array $lines ): string {
		if ( empty( $lines ) ) {
			return '';
		}

		$text = trim( implode( "\n\n", $lines ) );

		if ( '' === $text ) {
			return '';
		}

		return function_exists( 'wpautop' ) ? wpautop( $text ) : '<p>' . nl2br( esc_html( $text ) ) . '</p>';
	}

	/**
	 * Build lesson title using template fallback.
	 *
	 * @param string $template            Template supplied by user.
	 * @param string $module_title        Module title.
	 * @param int    $module_index        Module index (1-based).
	 * @param int    $lesson_index        Lesson index within module (1-based).
	 * @param string $lesson_source_title Lesson heading detected in the document.
	 *
	 * @return string
	 */
	private function format_lesson_title( string $template, string $module_title, int $module_index, int $lesson_index, string $lesson_source_title ): string {
		$template = trim( $template );

		if ( '' === $template ) {
			$template = '%lesson_source_title%';
		}

		$replacements = array(
			'%module_title%'        => $module_title,
			'%module_index%'        => (string) $module_index,
			'%lesson_index%'        => (string) $lesson_index,
			'%lesson_source_title%' => $lesson_source_title,
		);

		$title = strtr( $template, $replacements );
		$title = trim( $title );

		if ( '' === $title ) {
			$title = $module_title . ' - ' . sprintf(
				/* translators: 1: lesson index */
				__( 'Lesson %d', 'masterstudy-lms-content-importer' ),
				$lesson_index
			);
		}

		return $title;
	}

	/**
	 * Parse quiz data from module lines.
	 *
	 * @param array  $lines        Lines located after the Test heading.
	 * @param string $module_title Module title for quiz naming.
	 *
	 * @return array
	 */
	private function parse_quiz( array $lines, string $module_title ): array {
		if ( empty( $lines ) ) {
			return array(
				'title'     => $module_title,
				'questions' => array(),
			);
		}

		$question_blocks = array();
		$results_lines   = array();
		$current_block   = array();
		$collect_results = false;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( '' === $trimmed ) {
				continue;
			}

			if ( preg_match( '/^RESULT/i', $trimmed ) ) {
				$collect_results = true;
				continue;
			}

			if ( $collect_results ) {
				$results_lines[] = $trimmed;
				continue;
			}

			if ( preg_match( '/^Question\s*\d+/i', $trimmed ) && ! empty( $current_block ) ) {
				$question_blocks[] = $current_block;
				$current_block     = array( $trimmed );
				continue;
			}

			if ( preg_match( '/^Correct\s*answer/i', $trimmed ) ) {
				$current_block[]   = $trimmed;
				$question_blocks[] = $current_block;
				$current_block     = array();
				continue;
			}

			$current_block[] = $trimmed;
		}

		if ( ! empty( $current_block ) ) {
			$question_blocks[] = $current_block;
		}

		$results_map = $this->parse_results_map( $results_lines, count( $question_blocks ) );

		$questions = array();

		foreach ( $question_blocks as $index => $block ) {
			$question = $this->build_question(
				$block,
				$results_map[ $index + 1 ] ?? null
			);

			if ( ! empty( $question ) ) {
				$questions[] = $question;
			}
		}

		return array(
			'title'     => $module_title,
			'questions' => $questions,
		);
	}

	/**
	 * Parse question block into structured question.
	 *
	 * @param array       $lines                   Question block lines.
	 * @param string|null $fallback_correct_letter Fallback correct answer letter.
	 *
	 * @return array|null
	 */
	private function build_question( array $lines, ?string $fallback_correct_letter ): ?array {
		$question_parts = array();
		$options        = array();
		$current_option = null;
		$correct_letter = $fallback_correct_letter;

		foreach ( $lines as $line ) {
			$trimmed = ltrim( $line );

			if ( preg_match( '/^Correct\s*answer\s*[:：]?\s*([a-d])/i', $trimmed, $match ) ) {
				$correct_letter = strtolower( $match[1] );
				continue;
			}

			if ( preg_match( '/^Question\s*\d+[:\.]?\s*(.*)$/i', $trimmed, $match ) ) {
				$content = '' !== $match[1] ? $match[1] : $trimmed;
				$question_parts[] = $content;
				continue;
			}

			if ( preg_match( '/^([a-d])[\)\.]\s*(.*)$/i', $trimmed, $match ) ) {
				if ( null !== $current_option ) {
					$options[] = $current_option;
				}

				$current_option = array(
					'label' => strtolower( $match[1] ),
					'text'  => $match[2],
				);
				continue;
			}

			if ( null !== $current_option ) {
				$current_option['text'] .= ' ' . $trimmed;
			} else {
				$question_parts[] = $trimmed;
			}
		}

		if ( null !== $current_option ) {
			$options[] = $current_option;
		}

		$question_text = trim( preg_replace( '/\s+/', ' ', implode( ' ', $question_parts ) ) );

		if ( '' === $question_text || empty( $options ) ) {
			return null;
		}

		if ( empty( $correct_letter ) ) {
			$correct_letter = $options[0]['label'] ?? 'a';
		}

		$answers = array();

		foreach ( $options as $option ) {
			$answers[] = array(
				'text'   => trim( $option['text'] ),
				'isTrue' => $option['label'] === $correct_letter,
			);
		}

		return array(
			'question' => $question_text,
			'answers'  => $answers,
		);
	}

	/**
	 * Convert results lines into a map question_number => correct_letter.
	 *
	 * @param array $lines          Result lines.
	 * @param int   $question_count Parsed question count.
	 *
	 * @return array<int, string>
	 */
	private function parse_results_map( array $lines, int $question_count ): array {
		if ( empty( $lines ) ) {
			return array();
		}

		$map    = array();
		$joined = strtolower( implode( ' ', $lines ) );
		$parts  = preg_split( '/[;,]+/', $joined );

		if ( false === $parts ) {
			return array();
		}

		$position = 1;

		foreach ( $parts as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			if ( preg_match( '/^(\d+)\.?([a-d])$/', $part, $match ) ) {
				$map[ (int) $match[1] ] = $match[2];
				continue;
			}

			if ( preg_match( '/^[a-d]$/', $part ) ) {
				while ( $position <= $question_count && isset( $map[ $position ] ) ) {
					$position++;
				}

				if ( $position <= $question_count ) {
					$map[ $position ] = $part;
					$position++;
				}
			}
		}

		return $map;
	}
}
