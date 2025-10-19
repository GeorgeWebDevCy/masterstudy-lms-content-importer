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
	 * @param array  $args      Extra args (author_id, status).
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
                        array(
                                'identifier_patterns' => $options['identifier_patterns'],
                        )
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
                        $author_id,
                        array(
                                'lesson_title_template' => $options['lesson_title_template'],
                        )
                );

                return $course_id;
        }

        /**
         * Normalise options passed from the admin layer.
         *
         * @param array $args Raw options array.
         *
         * @return array{lesson_title_template:string,identifier_patterns:array}
         */
        private function normalize_import_options( array $args ): array {
                $lesson_template = __( 'Overview', 'masterstudy-lms-content-importer' );

                if ( isset( $args['lesson_title_template'] ) && is_string( $args['lesson_title_template'] ) ) {
                        $lesson_template = trim( $args['lesson_title_template'] );

                        if ( '' === $lesson_template ) {
                                $lesson_template = __( 'Overview', 'masterstudy-lms-content-importer' );
                        }
                }

                $identifier_patterns = array();

                if ( ! empty( $args['identifier_patterns'] ) && is_array( $args['identifier_patterns'] ) ) {
                        $identifier_patterns = array_values(
                                array_filter(
                                        array_map(
                                                static function ( $pattern ) {
                                                        return is_string( $pattern ) ? trim( $pattern ) : '';
                                                },
                                                $args['identifier_patterns']
                                        )
                                )
                        );
                }

                return array(
                        'lesson_title_template' => $lesson_template,
                        'identifier_patterns'   => $identifier_patterns,
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
	 * @param int   $course_id Course ID.
	 * @param array $modules   Parsed modules.
	 * @param int   $author_id Author ID.
	 */
        private function create_modules( int $course_id, array $modules, int $author_id, array $options = array() ): void {
                $section_repository    = new CurriculumSectionRepository();
                $material_repository   = new CurriculumMaterialRepository();
                $lesson_repository     = new LessonRepository();
                $quiz_repository       = new QuizRepository();
                $question_repository   = new QuestionRepository();
                $section_order         = 0;
                $lesson_template       = ! empty( $options['lesson_title_template'] ) ? $options['lesson_title_template'] : __( 'Overview', 'masterstudy-lms-content-importer' );

                foreach ( $modules as $module ) {
                        $section_order++;
			$section = $section_repository->create(
				array(
					'course_id' => $course_id,
					'title'     => $module['title'],
					'order'     => $section_order,
				)
			);

			if ( empty( $section->id ) ) {
				continue;
			}

                        $lessons = array();

                        if ( ! empty( $module['lessons'] ) && is_array( $module['lessons'] ) ) {
                                $lessons = $module['lessons'];
                        } elseif ( isset( $module['content'] ) ) {
                                $lessons[] = array(
                                        'title'   => '',
                                        'content' => $module['content'],
                                );
                        }

                        if ( empty( $lessons ) ) {
                                $lessons[] = array(
                                        'title'   => '',
                                        'content' => '',
                                );
                        }

                        $lesson_position = 0;

                        foreach ( $lessons as $lesson ) {
                                $lesson_position++;

                                $resolved_title = $this->resolve_lesson_title(
                                        $lesson_template,
                                        $module['title'],
                                        isset( $lesson['title'] ) ? $lesson['title'] : '',
                                        $lesson_position
                                );

                                $lesson_id = $lesson_repository->create(
                                        array(
                                                'title'   => $resolved_title,
                                                'content' => isset( $lesson['content'] ) ? $lesson['content'] : '',
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
					'title'     => $quiz_data['title'] . ' Quiz',
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
         * Build a lesson title from the configured template.
         *
         * @param string $template      User provided template.
         * @param string $module_title  Module heading.
         * @param string $lesson_title  Parsed lesson heading.
         * @param int    $lesson_index  Lesson position within the module (1 based).
         *
         * @return string
         */
        private function resolve_lesson_title( string $template, string $module_title, string $lesson_title, int $lesson_index ): string {
                $fallback_lesson = '' !== trim( $lesson_title )
                        ? $lesson_title
                        : sprintf(
                                /* translators: %d: lesson index */
                                __( 'Lesson %d', 'masterstudy-lms-content-importer' ),
                                $lesson_index
                        );

                $replacements = array(
                        '%module%' => $module_title,
                        '%lesson%' => $fallback_lesson,
                        '%index%'  => (string) $lesson_index,
                );

                $template = strtr(
                        $template,
                        array(
                                '{{module}}' => '%module%',
                                '{{lesson}}' => '%lesson%',
                                '{{index}}'  => '%index%',
                        )
                );

                $resolved = strtr( $template, $replacements );
                $resolved = trim( $resolved );

                if ( '' === $resolved ) {
                        $resolved = $fallback_lesson;
                }

                return $resolved;
        }
}

