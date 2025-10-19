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

       private const WP_NS      = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
       private const REL_NS     = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
       private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

       /**
        * Relationship map keyed by relationship Id.
        *
        * @var array<string, array{target:string, mode:string, type:string}>
        */
       private $relationship_map = array();

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
				'module_identifier'     => 'MODULE',
				'lesson_identifier'     => '',
				'use_toc'               => true,
				'start_page'            => 1,
			),
			$options
		);

		$doc        = $this->load_document_dom( $file_path );
		$paragraphs = $this->extract_paragraphs( $doc );

		if ( empty( $paragraphs ) ) {
			throw new RuntimeException( __( 'The document appears to be empty.', 'masterstudy-lms-content-importer' ) );
		}

		$course_title    = '';
		$course_intro    = array();
		$modules         = array();
		$current_module  = null;
		$current_lesson  = null;
		$collecting_test = false;
		$module_index    = 0;
		$start_page = max( 1, (int) $options['start_page'] );

		foreach ( $paragraphs as $paragraph ) {
                       $style = $paragraph['style'] ?? '';
                       $text  = isset( $paragraph['text'] ) ? trim( $paragraph['text'] ) : '';
                       $html  = isset( $paragraph['html'] ) ? trim( $paragraph['html'] ) : $text;
                       $page  = $paragraph['page'] ?? 1;

			if ( $page < $start_page || '' === $text ) {
				continue;
			}

			if ( $this->is_toc_paragraph( $style ) ) {
				continue;
			}

			if ( '' === $course_title ) {
				$course_title = $text;
			}

			$normalized_text = $this->normalize_label( $text );

			$is_module_heading = $this->is_heading( $style, 'Heading1' );

			if ( ! $is_module_heading && '' !== $options['module_identifier'] ) {
				$identifier = strtolower( $options['module_identifier'] );
				if ( '' !== $identifier && 0 === strpos( strtolower( $normalized_text ), $identifier ) ) {
					$is_module_heading = true;
				}
			}

			if ( $is_module_heading ) {
				$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );
				if ( $current_module ) {
					$modules[] = $this->finalize_module( $current_module, $options['lesson_title_template'] );
				}

				$module_index++;

				$current_module = array(
					'title'          => $text,
					'label'          => $normalized_text,
					'lessons'        => array(),
					'lesson_counter' => 0,
					'module_index'   => $module_index,
					'quiz_lines'     => array(),
					'quiz_heading'   => '',
				);

				$current_lesson  = null;
				$collecting_test = false;
				continue;
			}

			if ( ! $current_module ) {
                               $course_intro[] = $html;
				continue;
			}

			$is_heading_two   = $this->is_heading( $style, 'Heading2' );
			$is_heading_three = $this->is_heading( $style, 'Heading3' );
			$is_lesson_heading = $is_heading_two || $is_heading_three;

			if ( ! $is_lesson_heading && '' !== $options['lesson_identifier'] ) {
				if ( false !== stripos( $normalized_text, strtolower( $options['lesson_identifier'] ) ) ) {
					$is_lesson_heading = true;
				}
			}

			if ( $is_lesson_heading ) {
				if ( preg_match( '/^test\b/i', $text ) ) {
					$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );
					$current_module['quiz_heading'] = $text;
					$collecting_test                = true;
					continue;
				}

				$this->finalize_current_lesson( $current_module, $current_lesson, $options['lesson_title_template'] );

				$current_lesson = array(
					'title'        => '',
					'source_title' => $text,
					'content_lines'=> array(),
				);
				$collecting_test = false;
				continue;
			}

			if ( $collecting_test ) {
                                $current_module['quiz_lines'][] = $text;
                                continue;
                        }

                        if ( null === $current_lesson ) {
				$current_lesson = array(
					'title'        => '',
					'source_title' => '',
					'content_lines'=> array(),
				);
			}

                        $current_lesson['content_lines'][] = $html;
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
        * @param ?array $module                Module reference.
        * @param ?array &$lesson               Lesson reference.
        * @param string $lesson_title_template Template supplied by the user.
        */
       private function finalize_current_lesson( ?array &$module, ?array &$lesson, string $lesson_title_template ): void {
               if ( null === $module || null === $lesson ) {
                       return;
               }

		$lesson_index = $module['lesson_counter'] + 1;
		$module['lesson_counter'] = $lesson_index;

		$title = $lesson['title'];

		if ( '' === $title ) {
			$title = $this->format_lesson_title(
				$lesson_title_template,
				$this->format_module_title( $module['title'], $module['module_index'] ),
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
		$module_title = $this->format_module_title( $module['title'], $module['module_index'] );
		$quiz_title   = $this->format_quiz_title( $module['quiz_heading'] ?? '', $module_title );
		$lessons      = $module['lessons'];

		if ( empty( $lessons ) ) {
			$lessons[] = array(
				'title'   => $this->format_lesson_title(
					$lesson_title_template,
					$module_title,
					$module['module_index'],
					1,
					''
				),
				'content' => '',
			);
		}

		$quiz = $this->parse_quiz( $module['quiz_lines'] ?? array(), $quiz_title );

		return array(
			'title'   => $module_title,
			'lessons' => $lessons,
			'quiz'    => $quiz,
		);
	}

	/**
	 * Normalize quiz title, converting "Test" headings to "Quiz".
	 *
	 * @param string $heading      Heading text from document.
	 * @param string $module_title Normalized module title.
	 *
	 * @return string
	 */
	private function format_quiz_title( string $heading, string $module_title ): string {
		$heading = trim( $heading );

		if ( '' === $heading ) {
			return sprintf( '%s %s', $module_title, __( 'Quiz', 'masterstudy-lms-content-importer' ) );
		}

		if ( preg_match( '/^Test(.*)$/i', $heading, $matches ) ) {
			$suffix = trim( $matches[1] );

			if ( '' === $suffix ) {
				return __( 'Quiz', 'masterstudy-lms-content-importer' );
			}

			return sprintf(
				/* translators: 1: quiz suffix */
				__( 'Quiz %s', 'masterstudy-lms-content-importer' ),
				$suffix
			);
		}

		return sprintf( '%s %s', $module_title, __( 'Quiz', 'masterstudy-lms-content-importer' ) );
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

                if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
                        throw new RuntimeException( __( 'The DOM PHP extension is required to parse DOCX files.', 'masterstudy-lms-content-importer' ) );
                }

		$zip = new ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			throw new RuntimeException( __( 'Unable to open the DOCX file.', 'masterstudy-lms-content-importer' ) );
		}

               $xml       = $zip->getFromName( 'word/document.xml' );
               $rels_xml  = $zip->getFromName( 'word/_rels/document.xml.rels' );
               $zip->close();

               if ( false === $xml ) {
                       throw new RuntimeException( __( 'Invalid DOCX structure: missing document.xml.', 'masterstudy-lms-content-importer' ) );
               }

               $doc = new DOMDocument();
               $doc->preserveWhiteSpace = false;

               if ( ! @$doc->loadXML( $xml ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                       throw new RuntimeException( __( 'Unable to read the DOCX XML content.', 'masterstudy-lms-content-importer' ) );
               }

               $this->relationship_map = $this->parse_relationships_manifest( $rels_xml );

               return $doc;
       }

       /**
        * Parse the relationship manifest.
        *
        * @param string|false $rels_xml Relationship XML string.
        *
        * @return array<string, array{target:string, mode:string, type:string}>
        */
       private function parse_relationships_manifest( $rels_xml ): array {
               if ( false === $rels_xml || '' === trim( $rels_xml ) ) {
                       return array();
               }

               $manifest = new DOMDocument();
               $manifest->preserveWhiteSpace = false;

               if ( ! @$manifest->loadXML( $rels_xml ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                       return array();
               }

               $xpath = new DOMXPath( $manifest );
               $xpath->registerNamespace( 'rel', self::PKG_REL_NS );

               $map = array();

               foreach ( $xpath->query( '/rel:Relationships/rel:Relationship' ) as $relationship ) {
                       if ( ! $relationship instanceof DOMElement ) {
                               continue;
                       }

                       $id     = $relationship->getAttribute( 'Id' );
                       $target = $relationship->getAttribute( 'Target' );
                       $type   = $relationship->getAttribute( 'Type' );

                       if ( '' === $id || '' === $target ) {
                               continue;
                       }

                       $map[ $id ] = array(
                               'target' => $target,
                               'mode'   => $relationship->getAttribute( 'TargetMode' ),
                               'type'   => $type,
                       );
               }

               return $map;
       }

       /**
         * Extract paragraphs with their associated styles.
         *
         * @param DOMDocument $doc Document object.
        *
        * @return array<int, array{text:string, style:string, html?:string}>
         */
        private function extract_paragraphs( DOMDocument $doc ): array {
                $xpath = new DOMXPath( $doc );
                $xpath->registerNamespace( 'w', self::WP_NS );

                $paragraphs = array();
                $page       = 1;

               foreach ( $xpath->query( '//w:p' ) as $paragraph ) {
                       if ( ! $paragraph instanceof DOMElement ) {
                               continue;
                       }

                        foreach ( $xpath->query( './/w:br[@w:type="page"]', $paragraph ) as $unused ) {
                                $page++;
                        }

                       $tokens = $this->collect_paragraph_tokens( $paragraph, $xpath );
                       $text   = '';

                       foreach ( $tokens as $token ) {
                               $text .= $token['text'];
                       }

                       if ( '' === $text ) {
                               foreach ( $xpath->query( './/w:t', $paragraph ) as $text_node ) {
                                       $text .= $text_node->nodeValue;
                               }
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
                               $entry = array(
                                       'text'  => $text,
                                       'style' => $style,
                                       'page'  => $page,
                               );

                               $html = $this->build_paragraph_html( $tokens );

                               if ( '' !== $html ) {
                                       $entry['html'] = $html;
                               }

                               $paragraphs[] = $entry;
                        }
                }

                return $paragraphs;
        }

        /**
        * Collect paragraph tokens preserving hyperlink information.
        *
        * @param DOMElement $paragraph Paragraph node.
        * @param DOMXPath   $xpath     XPath helper.
        *
        * @return array<int, array{type:string, text:string, href?:string}>
        */
       private function collect_paragraph_tokens( DOMElement $paragraph, DOMXPath $xpath ): array {
               $tokens = array();

               foreach ( $paragraph->childNodes as $child ) {
                       if ( ! $child instanceof DOMElement ) {
                               continue;
                       }

                       if ( self::WP_NS === $child->namespaceURI && 'hyperlink' === $child->localName ) {
                               $text = $this->collect_node_text( $child, $xpath );

                               if ( '' === $text ) {
                                       continue;
                               }

                               $tokens[] = array(
                                       'type' => 'hyperlink',
                                       'text' => $text,
                                       'href' => $this->resolve_hyperlink_href( $child ),
                               );

                               continue;
                       }

                       $text = $this->collect_node_text( $child, $xpath );

                       if ( '' === $text ) {
                               continue;
                       }

                       $tokens[] = array(
                               'type' => 'text',
                               'text' => $text,
                       );
               }

               if ( empty( $tokens ) ) {
                       $text = $this->collect_node_text( $paragraph, $xpath );

                       if ( '' !== $text ) {
                               $tokens[] = array(
                                       'type' => 'text',
                                       'text' => $text,
                               );
                       }
               }

               return $tokens;
       }

       /**
        * Retrieve textual content from a node.
        *
        * @param DOMNode  $node  Node to inspect.
        * @param DOMXPath $xpath XPath helper.
        *
        * @return string
        */
       private function collect_node_text( DOMNode $node, DOMXPath $xpath ): string {
               $text = '';

               foreach ( $xpath->query( './/w:t', $node ) as $text_node ) {
                       $text .= $text_node->nodeValue;
               }

               return $text;
       }

       /**
        * Convert paragraph tokens into HTML fragments.
        *
        * @param array<int, array{type:string, text:string, href?:string}> $tokens Token list.
        *
        * @return string
        */
       private function build_paragraph_html( array $tokens ): string {
               if ( empty( $tokens ) ) {
                       return '';
               }

               $fragments = array();
               $count     = count( $tokens );

               foreach ( $tokens as $index => $token ) {
                       if ( empty( $token['text'] ) ) {
                               continue;
                       }

                       $segment = $this->normalize_text_segment( $token['text'], 0 === $index, $index === $count - 1 );

                       if ( '' === $segment ) {
                               continue;
                       }

                       if ( 'hyperlink' === $token['type'] ) {
                               $href = $token['href'] ?? '';

                               if ( '' === $href ) {
                                       $fragments[] = $this->escape_html( $segment );
                                       continue;
                               }

                               $fragments[] = sprintf(
                                       '<a href="%s">%s</a>',
                                       $this->escape_url( $href ),
                                       $this->escape_html( $segment )
                               );

                               continue;
                       }

                       $fragments[] = $this->escape_html( $segment );
               }

               return trim( implode( '', $fragments ) );
       }

       /**
        * Normalize token text to keep whitespace predictable.
        *
        * @param string $segment   Text segment.
        * @param bool   $is_first  Whether this is the first token.
        * @param bool   $is_last   Whether this is the last token.
        *
        * @return string
        */
       private function normalize_text_segment( string $segment, bool $is_first, bool $is_last ): string {
               $normalized = preg_replace( '/\s+/u', ' ', $segment );

               if ( null === $normalized ) {
                       $normalized = $segment;
               }

               if ( $is_first ) {
                       $normalized = ltrim( $normalized );
               }

               if ( $is_last ) {
                       $normalized = rtrim( $normalized );
               }

               return $normalized;
       }

       /**
        * Resolve hyperlink target from relationship map.
        *
        * @param DOMElement $hyperlink Hyperlink node.
        *
        * @return string
        */
       private function resolve_hyperlink_href( DOMElement $hyperlink ): string {
               $anchor = $hyperlink->getAttributeNS( self::WP_NS, 'anchor' );

               if ( '' !== $anchor ) {
                       return '#' . ltrim( $anchor, '#' );
               }

               $relationship_id = $hyperlink->getAttributeNS( self::REL_NS, 'id' );

               if ( '' === $relationship_id ) {
                       return '';
               }

               if ( ! isset( $this->relationship_map[ $relationship_id ] ) ) {
                       return '';
               }

               $relationship = $this->relationship_map[ $relationship_id ];

               if ( empty( $relationship['type'] ) || false === stripos( $relationship['type'], 'hyperlink' ) ) {
                       return '';
               }

               return $relationship['target'];
       }

       /**
        * Escape text for safe output.
        *
        * @param string $text Text to escape.
        *
        * @return string
        */
       private function escape_html( string $text ): string {
               if ( function_exists( 'esc_html' ) ) {
                       return esc_html( $text );
               }

               return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
       }

       /**
        * Escape URL attribute value for safe output.
        *
        * @param string $url URL to escape.
        *
        * @return string
        */
       private function escape_url( string $url ): string {
               if ( function_exists( 'esc_url' ) ) {
                       return esc_url( $url );
               }

               return htmlspecialchars( $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
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
			$page  = $paragraph['page'];
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
					'page'    => $page,
				);
				$current_module = count( $modules ) - 1;
				continue;
			}

			if ( stripos( $style, 'TOC2' ) === 0 && null !== $current_module ) {
				$modules[ $current_module ]['lessons'][] = array(
					'title' => $text,
					'label' => $this->normalize_label( $text ),
					'page'  => $page,
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
	 * Check if style matches a specific heading level.
	 *
	 * @param string $style  Paragraph style.
	 * @param string $target Target heading (e.g. Heading1).
	 *
	 * @return bool
	 */
	private function is_heading( string $style, string $target ): bool {
		return '' !== $style && 0 === strcasecmp( $style, $target );
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

                if ( function_exists( 'wpautop' ) ) {
                        $html = wpautop( $text );
                } else {
                        $html = '<p>' . nl2br( $text ) . '</p>';
                }

                if ( function_exists( 'wp_kses_post' ) ) {
                        return wp_kses_post( $html );
                }

                if ( function_exists( 'esc_html' ) ) {
                        return '<p>' . nl2br( esc_html( $text ) ) . '</p>';
                }

                return $html;
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

		$title_body = trim( strtr( $template, $replacements ) );
		if ( '' === $title_body ) {
			$title_body = $lesson_source_title;
		}
		$prefix = sprintf( '%d.%d', $module_index, $lesson_index );

		if ( '' === $title_body ) {
			return $prefix;
		}

		if ( preg_match( '/^' . preg_quote( $prefix, '/' ) . '\\b/', $title_body ) ) {
			return $title_body;
		}

		return $prefix . ' ' . $title_body;
	}

	/**
	 * Ensure module titles contain numbering.
	 *
	 * @param string $title        Raw module title.
	 * @param int    $module_index Module index (1-based).
	 *
	 * @return string
	 */
	private function format_module_title( string $title, int $module_index ): string {
		$title = trim( $title );

		if ( '' === $title ) {
			return $this->build_module_title_with_prefix( $module_index, '' );
		}

		if ( preg_match( '/module\s+(\d+)[\.\s:-]*(.*)/i', $title, $matches ) ) {
			$index = (int) $matches[1];
			$rest  = isset( $matches[2] ) ? $this->trim_module_rest( $matches[2] ) : '';

			return $this->build_module_title_with_prefix( $index, $rest );
		}

		if ( preg_match( '/^(\d+)\.?\s*(.*)$/', $title, $matches ) ) {
			$index = (int) $matches[1];
			$rest  = isset( $matches[2] ) ? $this->trim_module_rest( $matches[2] ) : '';

			return $this->build_module_title_with_prefix( $index, $rest );
		}

		return $this->build_module_title_with_prefix( $module_index, $this->trim_module_rest( $title ) );
	}

	/**
	 * Trim punctuation from detected module title suffixes.
	 *
	 * @param string $title Module suffix.
	 *
	 * @return string
	 */
	private function trim_module_rest( string $title ): string {
		return trim( ltrim( $title, " .:-" ) );
	}

	/**
	 * Build the final module title string prefixed with MODULE.
	 *
	 * @param int    $module_index Module index (1-based).
	 * @param string $module_title Module suffix.
	 *
	 * @return string
	 */
	private function build_module_title_with_prefix( int $module_index, string $module_title ): string {
		$module_title = trim( $module_title );

		if ( '' === $module_title ) {
			return sprintf(
				/* translators: %s: module index */
				__( 'MODULE %d', 'masterstudy-lms-content-importer' ),
				$module_index
			);
		}

		return sprintf(
			/* translators: 1: module index, 2: module title */
			__( 'MODULE %1$d. %2$s', 'masterstudy-lms-content-importer' ),
			$module_index,
			$module_title
		);
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
