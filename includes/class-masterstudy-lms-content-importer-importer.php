<?php

/**
 * Handles MasterStudy course import from parsed DOCX data.
 *
 * @since 1.0.0
 * @package Masterstudy_Lms_Content_Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MasterStudy\Lms\Enums\LessonType;
use MasterStudy\Lms\Repositories\CurriculumMaterialRepository;
use MasterStudy\Lms\Repositories\CurriculumSectionRepository;
use MasterStudy\Lms\Repositories\LessonRepository;
use MasterStudy\Lms\Repositories\QuestionRepository;
use MasterStudy\Lms\Repositories\QuizRepository;

class Masterstudy_Lms_Content_Importer_Importer {

	/**
	 * DOCX parser instance.
	 *
	 * @var Masterstudy_Lms_Content_Importer_Docx_Parser
	 */
	private $parser;

	/**
	 * Constructor.
	 *
	 * @param Masterstudy_Lms_Content_Importer_Docx_Parser $parser Parser helper.
	 */
	public function __construct( Masterstudy_Lms_Content_Importer_Docx_Parser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * Import DOCX file and create MasterStudy LMS course.
	 *
	 * @param string $file_path Uploaded DOCX path.
	 * @param array  $args      Extra args (author_id, status, lesson_title_template, parser_options).
	 *
	 * @return int Created course ID.
	 *
	 * @throws RuntimeException When import fails.
	 */
	public function import( string $file_path, array $args = array() ): int {
		$this->assert_dependencies();

		$options = $this->normalize_import_options( $args );

		$data = $this->parser->parse(
			$file_path,
			$options['parser_options']
		);

		if ( empty( $data['modules'] ) ) {
			throw new RuntimeException( __( 'No modules were detected in the document.', 'masterstudy-lms-content-importer' ) );
		}

		$author_id = ! empty( $args['author_id'] ) ? (int) $args['author_id'] : get_current_user_id();
		$status    = ! empty( $args['status'] ) ? $args['status'] : 'publish';

		$course_id = $this->create_course(
			$data['title'],
			$data['description'],
			$author_id,
			$status
		);

		$this->create_modules(
			$course_id,
			$data['modules'],
			$options['lesson_title_template']
		);

		return $course_id;
	}

	/**
	 * Normalise options passed from the admin layer.
	 *
	 * @param array $args Raw options array.
	 *
	 * @return array{lesson_title_template:string,parser_options:array}
	 */
	private function normalize_import_options( array $args ): array {
		$lesson_template = '%lesson_source_title%';

		if ( isset( $args['lesson_title_template'] ) && is_string( $args['lesson_title_template'] ) ) {
			$lesson_template = trim( $args['lesson_title_template'] );

			if ( '' === $lesson_template ) {
				$lesson_template = '%lesson_source_title%';
			}
		}

		$parser_options = array(
			'lesson_title_template' => $lesson_template,
			'module_identifier'     => '',
			'lesson_identifier'     => '',
			'use_toc'               => true,
		);

		if ( ! empty( $args['parser_options'] ) && is_array( $args['parser_options'] ) ) {
			$parser_options = array_merge( $parser_options, $args['parser_options'] );
		}

		return array(
			'lesson_title_template' => $lesson_template,
			'parser_options'        => $parser_options,
		);
	}

	/**
	 * Ensure MasterStudy plugin classes exist.
	 *
	 * @throws RuntimeException When dependencies are missing.
	 */
	private function assert_dependencies(): void {
		$required = array(
			QuestionRepository::class,
			QuizRepository::class,
			LessonRepository::class,
			CurriculumSectionRepository::class,
			CurriculumMaterialRepository::class,
		);

		foreach ( $required as $class ) {
			if ( ! class_exists( $class ) ) {
				throw new RuntimeException(
					sprintf(
						/* translators: %s: class name */
						__( 'Required class %s is not available. Please ensure the MasterStudy LMS plugin is active.', 'masterstudy-lms-content-importer' ),
						$class
					)
				);
			}
		}
	}

	/**
	 * Create base course post.
	 *
	 * @param string $title       Course title.
	 * @param string $description Course description HTML.
	 * @param int    $author_id   Course author.
	 * @param string $status      Post status.
	 *
	 * @return int Course ID.
	 *
	 * @throws RuntimeException When course cannot be created.
	 */
	private function create_course( string $title, string $description, int $author_id, string $status ): int {
		$slug = sanitize_title( $title );

		if ( '' === $slug ) {
			$slug = 'imported-course-' . wp_generate_password( 6, false );
		}

		$postarr = array(
			'post_author'  => $author_id,
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $status,
			'post_type'    => 'stm-courses',
			'post_name'    => $slug,
		);

		$course_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $course_id ) ) {
			throw new RuntimeException( $course_id->get_error_message() );
		}

		update_post_meta( $course_id, 'status', $status );

		return (int) $course_id;
	}

	/**
	 * Create sections, lessons, and quizzes for each module.
	 *
	 * @param int    $course_id             Course ID.
	 * @param array  $modules               Parsed modules.
	 * @param string $lesson_title_template Template for fallback lesson titles.
	 */
	private function create_modules( int $course_id, array $modules, string $lesson_title_template ): void {
		$section_repository  = new CurriculumSectionRepository();
		$material_repository = new CurriculumMaterialRepository();
		$lesson_repository   = new LessonRepository();
		$quiz_repository     = new QuizRepository();
		$question_repository = new QuestionRepository();
		$section_order       = 0;

		foreach ( $modules as $module_index => $module ) {
			$section_order++;
			$module_title = $this->ensure_numbered_module_title( $module['title'] ?? '', $module_index + 1 );

			$section = $section_repository->create(
				array(
					'course_id' => $course_id,
					'title'     => $module_title,
					'order'     => $section_order,
				)
			);

			if ( empty( $section->id ) ) {
				continue;
			}

			$lessons = ! empty( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array();

			if ( empty( $lessons ) ) {
				$lessons[] = array(
					'title'   => $this->format_lesson_title( $lesson_title_template, $module_title, $module_index + 1, 1, '' ),
					'content' => '',
				);
			}

			foreach ( $lessons as $lesson_index => $lesson ) {
					$title = isset( $lesson['title'] ) ? trim( $lesson['title'] ) : '';

					if ( '' === $title ) {
						$title = $this->format_lesson_title(
							$lesson_title_template,
							$module_title,
							$module_index + 1,
							$lesson_index + 1,
							''
						);
					}

					$lesson_id = $lesson_repository->create(
						array(
							'title'   => $title,
						'content' => $lesson['content'] ?? '',
						'type'    => LessonType::TEXT,
					)
				);

				$material_repository->create(
					array(
						'post_id'    => $lesson_id,
						'section_id' => $section->id,
					)
				);
			}

			$quiz_data = $module['quiz'];
			$quiz_title = ! empty( $quiz_data['title'] )
				? $quiz_data['title']
				: sprintf( '%s %s', $module_title, __( 'Quiz', 'masterstudy-lms-content-importer' ) );

			if ( empty( $quiz_data['questions'] ) ) {
				continue;
			}

			$question_ids = array();

			foreach ( $quiz_data['questions'] as $question ) {
				$answers = array_map(
					function ( $answer ) {
						return array(
							'text'   => $answer['text'],
							'isTrue' => (bool) $answer['isTrue'],
						);
					},
					$question['answers']
				);

				$question_ids[] = $question_repository->create(
					array(
						'question'   => $question['question'],
						'answers'    => $answers,
						'type'       => 'single_choice',
						'view_type'  => 'list',
						'categories' => array(),
					)
				);
			}

			if ( empty( $question_ids ) ) {
				continue;
			}

			$quiz_id = $quiz_repository->create(
				array(
					'title'     => $quiz_title,
					'content'   => '',
					'questions' => $question_ids,
					'style'     => 'default',
				)
			);

			$material_repository->create(
				array(
					'post_id'    => $quiz_id,
					'section_id' => $section->id,
				)
			);
		}
	}

	/**
	 * Ensure module titles include numbering.
	 *
	 * @param string $title        Raw module title.
	 * @param int    $module_index Module index (1-based).
	 *
	 * @return string
	 */
	private function ensure_numbered_module_title( string $title, int $module_index ): string {
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
	 * Build a fallback lesson title from template tokens.
	 *
	 * @param string $template            Template string.
	 * @param string $module_title        Module title.
	 * @param int    $module_index        Module index (1-based).
	 * @param int    $lesson_index        Lesson index (1-based).
	 * @param string $lesson_source_title Source title.
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
			$title_body = trim( $lesson_source_title );
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
}
