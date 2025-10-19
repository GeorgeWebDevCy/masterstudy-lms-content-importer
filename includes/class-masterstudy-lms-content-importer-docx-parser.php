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

	/**
	 * Parse a DOCX file into course/module/question structure.
	 *
         * @param string $file_path Absolute path to the uploaded DOCX file.
         * @param array  $config    Optional configuration (identifier patterns).
         *
         * @return array
	 *
	 * @throws RuntimeException When the file cannot be parsed.
	 */
        public function parse( string $file_path, array $config = array() ): array {
                $paragraphs = $this->extract_paragraphs( $file_path );

                if ( empty( $paragraphs ) ) {
                        throw new RuntimeException( __( 'The document appears to be empty.', 'masterstudy-lms-content-importer' ) );
                }

                $patterns = $this->prepare_identifier_patterns( $config['identifier_patterns'] ?? array() );

                $course_title = '';
                $course_intro = array();
                $modules      = array();

                $current_module = null;
                $current_lesson = null;
                $in_test        = false;

                foreach ( $paragraphs as $paragraph ) {
                        $line = trim( $paragraph );

			if ( '' === $line ) {
				continue;
			}

			if ( empty( $course_title ) ) {
				$course_title = $line;
			}

                        if ( $this->is_module_heading( $line, $patterns ) ) {
                                if ( ! empty( $current_module ) ) {
                                        if ( ! empty( $current_lesson ) ) {
                                                $current_module['lessons'][] = $current_lesson;
                                                $current_lesson              = null;
                                        }

                                        $modules[] = $this->normalize_module( $current_module );
                                }

                                $current_module = array(
                                        'title'        => $line,
                                        'lesson_lines' => array(),
                                        'test_lines'   => array(),
                                        'lessons'      => array(),
                                );
                                $in_test        = false;
                                continue;
                        }

			if ( empty( $current_module ) ) {
				$course_intro[] = $line;
				continue;
			}

                        if ( preg_match( '/^Test\b/i', $line ) ) {
                                if ( ! empty( $current_lesson ) ) {
                                        $current_module['lessons'][] = $current_lesson;
                                        $current_lesson              = null;
                                }

                                $in_test = true;
                                continue;
                        }

                        if ( $in_test ) {
                                $current_module['test_lines'][] = $line;
                        } else {
                                if ( $this->is_lesson_heading( $line, $patterns ) ) {
                                        if ( ! empty( $current_lesson ) ) {
                                                $current_module['lessons'][] = $current_lesson;
                                        }

                                        $current_lesson = array(
                                                'title' => $line,
                                                'lines' => array(),
                                        );
                                        continue;
                                }

                                if ( ! empty( $current_lesson ) ) {
                                        $current_lesson['lines'][] = $line;
                                } else {
                                        $current_module['lesson_lines'][] = $line;
                                }
                        }
                }

                if ( ! empty( $current_module ) ) {
                        if ( ! empty( $current_lesson ) ) {
                                $current_module['lessons'][] = $current_lesson;
                                $current_lesson              = null;
                        }

                        $modules[] = $this->normalize_module( $current_module );
                }

		return array(
			'title'       => $course_title,
			'description' => $this->format_block( $course_intro ),
			'modules'     => $modules,
		);
	}

	/**
	 * Extract paragraphs from DOCX file.
	 *
	 * @param string $file_path File path.
	 * @return array
	 *
	 * @throws RuntimeException When the file cannot be read.
	 */
	private function extract_paragraphs( string $file_path ): array {
		if ( ! class_exists( 'ZipArchive' ) ) {
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

		$xpath = new DOMXPath( $doc );
		$xpath->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

		$paragraphs = array();

		foreach ( $xpath->query( '//w:p' ) as $paragraph ) {
			$text = '';

			foreach ( $xpath->query( './/w:t', $paragraph ) as $text_node ) {
				$text .= $text_node->nodeValue;
			}

			if ( '' !== trim( $text ) ) {
				$paragraphs[] = preg_replace( '/\s+/u', ' ', $text );
			}
		}

		return $paragraphs;
	}

	/**
	 * Normalize module data.
	 *
	 * @param array $module Raw module data.
	 *
	 * @return array
	 */
        private function normalize_module( array $module ): array {
                $lessons = array();

                if ( ! empty( $module['lessons'] ) ) {
                        foreach ( $module['lessons'] as $lesson ) {
                                $lessons[] = array(
                                        'title'   => $lesson['title'],
                                        'content' => $this->format_block( $lesson['lines'] ?? array() ),
                                );
                        }
                }

                if ( empty( $lessons ) ) {
                        $lessons[] = array(
                                'title'   => '',
                                'content' => $this->format_block( $module['lesson_lines'] ?? array() ),
                        );
                }

                $quiz = $this->parse_quiz( $module['test_lines'], $module['title'] );

                return array(
                        'title'   => $module['title'],
                        'content' => $lessons[0]['content'],
                        'lessons' => $lessons,
                        'quiz'    => $quiz,
                );
        }

        /**
         * Prepare identifier patterns for module and lesson detection.
         *
         * @param array $raw_patterns Raw user supplied patterns.
         *
         * @return array{module:array<int, array{regex:bool,pattern:string}>, lesson:array<int, array{regex:bool,pattern:string}>, generic:array<int, array{regex:bool,pattern:string}>}
         */
        private function prepare_identifier_patterns( array $raw_patterns ): array {
                $normalized = array(
                        'module'  => array(),
                        'lesson'  => array(),
                        'generic' => array(),
                );

                foreach ( $raw_patterns as $pattern ) {
                        if ( '' === $pattern ) {
                                continue;
                        }

                        $type = null;

                        if ( false !== stripos( $pattern, 'module:' ) ) {
                                $type    = 'module';
                                $pattern = trim( substr( $pattern, stripos( $pattern, 'module:' ) + 7 ) );
                        } elseif ( false !== stripos( $pattern, 'lesson:' ) ) {
                                $type    = 'lesson';
                                $pattern = trim( substr( $pattern, stripos( $pattern, 'lesson:' ) + 7 ) );
                        } elseif ( false !== stripos( $pattern, 'module' ) ) {
                                $type = 'module';
                        } elseif ( false !== stripos( $pattern, 'lesson' ) ) {
                                $type = 'lesson';
                        }

                        $stored_pattern = $this->normalize_pattern_definition( $pattern );

                        if ( null === $type ) {
                                $normalized['generic'][] = $stored_pattern;
                        } else {
                                $normalized[ $type ][] = $stored_pattern;
                        }
                }

                return $normalized;
        }

        /**
         * Normalize pattern definition, turning slash wrapped strings into regexes.
         *
         * @param string $pattern Pattern definition.
         *
         * @return array{regex:bool,pattern:string}
         */
        private function normalize_pattern_definition( string $pattern ): array {
                $pattern = trim( $pattern );

                if ( 2 <= strlen( $pattern ) && '/' === $pattern[0] ) {
                        $last_delimiter = strrpos( $pattern, '/' );

                        if ( false !== $last_delimiter && 0 !== $last_delimiter ) {
                                $modifiers = substr( $pattern, $last_delimiter + 1 );
                                $body      = substr( $pattern, 1, $last_delimiter - 1 );

                                if ( '' !== $body ) {
                                        return array(
                                                'regex'   => true,
                                                'pattern' => '/' . $body . '/' . $modifiers,
                                        );
                                }
                        }
                }

                return array(
                        'regex'   => false,
                        'pattern' => $pattern,
                );
        }

        /**
         * Check whether the current line represents a module heading.
         *
         * @param string $line     Line to inspect.
         * @param array  $patterns Prepared patterns map.
         *
         * @return bool
         */
        private function is_module_heading( string $line, array $patterns ): bool {
                if ( preg_match( '/^MODULE\s+\d+\.?/i', $line ) ) {
                        return true;
                }

                return $this->matches_any_pattern( $line, $patterns['module'] )
                        || $this->matches_any_pattern( $line, $patterns['generic'] );
        }

        /**
         * Check whether the current line represents a lesson heading.
         *
         * @param string $line     Line to inspect.
         * @param array  $patterns Prepared patterns map.
         *
         * @return bool
         */
        private function is_lesson_heading( string $line, array $patterns ): bool {
                if ( preg_match( '/^Lesson\s+\d+\.?/i', $line ) ) {
                        return true;
                }

                return $this->matches_any_pattern( $line, $patterns['lesson'] )
                        || $this->matches_any_pattern( $line, $patterns['generic'] );
        }

        /**
         * Determine if a line matches any prepared pattern.
         *
         * @param string $line      The source line.
         * @param array  $patterns  List of normalized patterns.
         *
         * @return bool
         */
        private function matches_any_pattern( string $line, array $patterns ): bool {
                foreach ( $patterns as $pattern ) {
                        if ( $pattern['regex'] ) {
                                $result = @preg_match( $pattern['pattern'], $line ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

                                if ( false !== $result && 1 === $result ) {
                                        return true;
                                }
                        } elseif ( false !== stripos( $line, $pattern['pattern'] ) ) {
                                return true;
                        }
                }

                return false;
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
	 * Parse quiz data from module lines.
	 *
	 * @param array  $lines Lines located after the Test heading.
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
	 * @param array       $lines Question block lines.
	 * @param string|null $fallback_correct_letter Fallback correct answer letter.
	 *
	 * @return array|null
	 */
	private function build_question( array $lines, ?string $fallback_correct_letter ): ?array {
		$question_parts  = array();
		$options         = array();
		$current_option  = null;
		$correct_letter  = $fallback_correct_letter;

		foreach ( $lines as $line ) {
			$trimmed = ltrim( $line );

			if ( preg_match( '/^Correct\s*answer\s*[:：]?\s*([a-d])/i', $trimmed, $match ) ) {
				$correct_letter = strtolower( $match[1] );
				continue;
			}

			if ( preg_match( '/^Question\s*\d+[:\.]?\s*(.*)$/i', $trimmed, $match ) ) {
				$question_parts[] = '' !== $match[1] ? $match[1] : $trimmed;
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
	 * @param array $lines Result lines.
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

