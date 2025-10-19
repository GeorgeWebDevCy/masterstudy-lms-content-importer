<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://https://www.georgenicolaou.me
 * @since      1.0.0
 *
 * @package    Masterstudy_Lms_Content_Importer
 * @subpackage Masterstudy_Lms_Content_Importer/admin
 */

/**
 * Handles admin experience for the importer.
 */
class Masterstudy_Lms_Content_Importer_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Admin notices to render.
	 *
	 * @var array<int, array{type:string, text:string}>
	 */
	private $messages = array();

	/**
	 * Parsed mapping waiting for confirmation.
	 *
	 * @var array<string, mixed>|null
	 */
	private $preview_data = null;

	/**
	 * Sanitized values to repopulate the form.
	 *
	 * @var array<string, mixed>
	 */
	private $form_values = array();

	/**
	 * Page number where parsing should start (if provided).
	 *
	 * @var int
	 */
	private $start_page = 1;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @param string $plugin_name Plugin slug.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->form_values = $this->get_default_form_values();
		$this->start_page  = 1;
		$this->preview_data = null;
	}

	/**
	 * Register the stylesheets for the admin area.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/masterstudy-lms-content-importer-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/masterstudy-lms-content-importer-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	/**
	 * Register the admin menu item.
	 */
	public function register_menu_page() {
		add_menu_page(
			__( 'Word Course Importer', 'masterstudy-lms-content-importer' ),
			__( 'Course Importer', 'masterstudy-lms-content-importer' ),
			'manage_options',
			'masterstudy-lms-content-importer',
			array( $this, 'render_import_page' ),
			'dashicons-upload',
			59
		);
	}

	/**
	 * Render the import admin page.
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'masterstudy-lms-content-importer' ) );
		}

		if ( isset( $_POST['ms_lms_import_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->handle_import_request();
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Course from Word Document', 'masterstudy-lms-content-importer' ); ?></h1>
			<?php $this->render_messages(); ?>
			<p>
				<?php esc_html_e( 'Upload a .docx file that follows your MasterStudy LMS curriculum structure. The importer creates sections, lessons, and quizzes directly from the document.', 'masterstudy-lms-content-importer' ); ?>
			</p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ms_lms_import_course', 'ms_lms_import_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ms_lms_import_docx"><?php esc_html_e( 'Word Document (.docx)', 'masterstudy-lms-content-importer' ); ?></label>
						</th>
						<td>
							<input type="file" id="ms_lms_import_docx" name="ms_lms_import_docx" accept=".docx" required />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ms_lms_lesson_title_template"><?php esc_html_e( 'Lesson Title Template', 'masterstudy-lms-content-importer' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="ms_lms_lesson_title_template"
								name="ms_lms_lesson_title_template"
								class="regular-text"
								value="<?php echo esc_attr( $this->get_form_value( 'lesson_title_template' ) ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Use placeholders like %module_title%, %module_index%, %lesson_index%, or %lesson_source_title%.', 'masterstudy-lms-content-importer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ms_lms_module_identifier"><?php esc_html_e( 'Module Heading Identifier', 'masterstudy-lms-content-importer' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="ms_lms_module_identifier"
								name="ms_lms_module_identifier"
								class="regular-text"
								value="<?php echo esc_attr( $this->get_form_value( 'module_identifier' ) ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Text pattern that marks module headings when a table of contents is not available (e.g. "Module").', 'masterstudy-lms-content-importer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ms_lms_lesson_identifier"><?php esc_html_e( 'Lesson Heading Identifier', 'masterstudy-lms-content-importer' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="ms_lms_lesson_identifier"
								name="ms_lms_lesson_identifier"
								class="regular-text"
								value="<?php echo esc_attr( $this->get_form_value( 'lesson_identifier' ) ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Optional text pattern for identifying lesson headings when multiple levels share the same heading style (e.g. "Lesson").', 'masterstudy-lms-content-importer' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Use Table of Contents', 'masterstudy-lms-content-importer' ); ?></th>
						<td>
							<label for="ms_lms_use_toc">
								<input
									type="checkbox"
									id="ms_lms_use_toc"
									name="ms_lms_use_toc"
									value="1"
									<?php checked( $this->get_form_value( 'use_toc' ) ); ?>
								/>
								<?php esc_html_e( 'Prefer the Word table of contents to detect modules and lessons when it is present.', 'masterstudy-lms-content-importer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ms_lms_start_page"><?php esc_html_e( 'Skip pages before', 'masterstudy-lms-content-importer' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								min="1"
								id="ms_lms_start_page"
								name="ms_lms_start_page"
								class="small-text"
								value="<?php echo esc_attr( (string) $this->start_page ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Set the first page of actual course content. All pages before this number will be ignored.', 'masterstudy-lms-content-importer' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Import Course', 'masterstudy-lms-content-importer' ) ); ?>
			</form>
			<?php $this->render_preview(); ?>
		</div>
		<?php
	}

	/**
	 * Handle uploaded DOCX and trigger importer.
	 */
	private function handle_import_request() {
		check_admin_referer( 'ms_lms_import_course', 'ms_lms_import_nonce' );

		if ( ! empty( $_POST['ms_lms_import_confirm'] ) && ! empty( $_POST['ms_lms_import_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$this->process_confirm_request();
			return;
		}

		$values = array(
			'lesson_title_template' => $this->sanitize_template( $_POST['ms_lms_lesson_title_template'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'module_identifier'     => $this->sanitize_identifier( $_POST['ms_lms_module_identifier'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'lesson_identifier'     => $this->sanitize_identifier( $_POST['ms_lms_lesson_identifier'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'use_toc'               => ! empty( $_POST['ms_lms_use_toc'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$this->form_values = array_merge( $this->get_default_form_values(), $values );
		$this->start_page  = $this->sanitize_start_page( $_POST['ms_lms_start_page'] ?? 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( empty( $_FILES['ms_lms_import_docx']['name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$this->add_message( 'error', __( 'Please select a DOCX file to import.', 'masterstudy-lms-content-importer' ) );
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$uploaded = wp_handle_upload(
			wp_unslash( $_FILES['ms_lms_import_docx'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			array(
				'test_form' => false,
				'mimes'     => array(
					'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				),
			)
		);

		if ( isset( $uploaded['error'] ) ) {
			$this->add_message( 'error', $uploaded['error'] );
			return;
		}

		$file_path = $uploaded['file'] ?? '';

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$this->add_message( 'error', __( 'Upload failed. Please try again.', 'masterstudy-lms-content-importer' ) );
			return;
		}

		try {
			$parser   = new Masterstudy_Lms_Content_Importer_Docx_Parser();
			$parsed   = $parser->parse(
				$file_path,
				array(
					'lesson_title_template' => $this->form_values['lesson_title_template'],
					'module_identifier'     => $this->form_values['module_identifier'],
					'lesson_identifier'     => $this->form_values['lesson_identifier'],
					'use_toc'               => $this->form_values['use_toc'],
					'start_page'            => $this->start_page,
				)
			);

			$token   = wp_generate_password( 20, false, false );
			$token   = preg_replace( '/[^a-zA-Z0-9]/', '', $token );
			$success = set_transient(
				$this->get_transient_key( $token ),
				array(
					'file_path'      => $file_path,
					'parser_options' => array(
						'lesson_title_template' => $this->form_values['lesson_title_template'],
						'module_identifier'     => $this->form_values['module_identifier'],
						'lesson_identifier'     => $this->form_values['lesson_identifier'],
						'use_toc'               => (bool) $this->form_values['use_toc'],
						'start_page'            => $this->start_page,
					),
					'form_values'    => $this->form_values,
					'start_page'     => $this->start_page,
					'mapping'        => $parsed,
					'created_at'     => time(),
				),
				HOUR_IN_SECONDS
			);

			if ( ! $success ) {
				wp_delete_file( $file_path );
				$this->add_message( 'error', __( 'Unable to prepare the preview. Please try again.', 'masterstudy-lms-content-importer' ) );
				return;
			}

			$this->preview_data = array(
				'token'   => $token,
				'mapping' => $parsed,
			);

			$this->add_message( 'info', __( 'Review the detected course structure below, then confirm the import.', 'masterstudy-lms-content-importer' ) );
		} catch ( Exception $exception ) {
			$this->add_message( 'error', $exception->getMessage() );

			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}
	}

	/**
	 * Process the confirmation request and perform the import.
	 */
	private function process_confirm_request() {
		$token = wp_unslash( $_POST['ms_lms_import_token'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', $token );

		if ( '' === $token ) {
			$this->add_message( 'error', __( 'Invalid import token. Please upload the document again.', 'masterstudy-lms-content-importer' ) );
			return;
		}

		$state = get_transient( $this->get_transient_key( $token ) );

		if ( empty( $state ) || ! is_array( $state ) ) {
			$this->add_message( 'error', __( 'Your import session has expired. Please upload the document again.', 'masterstudy-lms-content-importer' ) );
			return;
		}

		$this->form_values  = array_merge( $this->get_default_form_values(), $state['form_values'] ?? array() );
		$this->start_page   = isset( $state['start_page'] ) ? (int) $state['start_page'] : 1;
		$this->preview_data = array(
			'token'   => $token,
			'mapping' => $state['mapping'] ?? array(),
		);

		$file_path = $state['file_path'] ?? '';

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			delete_transient( $this->get_transient_key( $token ) );
			$this->preview_data = null;
			$this->add_message( 'error', __( 'The uploaded file could not be found. Please upload the document again.', 'masterstudy-lms-content-importer' ) );
			return;
		}

		$cleanup = false;

		try {
			$parser_options = $state['parser_options'] ?? array();

			$importer = new Masterstudy_Lms_Content_Importer_Importer( new Masterstudy_Lms_Content_Importer_Docx_Parser() );

			$course_id = $importer->import(
				$file_path,
				array(
					'author_id'             => get_current_user_id(),
					'status'                => 'publish',
					'lesson_title_template' => $this->form_values['lesson_title_template'],
					'parser_options'        => $parser_options,
					'start_page'            => $this->start_page,
				)
			);

			$edit_link = get_edit_post_link( $course_id );

			if ( $edit_link ) {
				$this->add_message(
					'success',
					sprintf(
						/* translators: %s: edit course link */
						__( 'Course imported successfully. <a href="%s">View the course</a>.', 'masterstudy-lms-content-importer' ),
						esc_url( $edit_link )
					)
				);
			} else {
				$this->add_message( 'success', __( 'Course imported successfully.', 'masterstudy-lms-content-importer' ) );
			}

			$cleanup            = true;
			$this->preview_data = null;
			$this->form_values  = $this->get_default_form_values();
			$this->start_page   = 1;
		} catch ( Exception $exception ) {
			$this->add_message( 'error', $exception->getMessage() );
		} finally {
			if ( $cleanup ) {
				delete_transient( $this->get_transient_key( $token ) );

				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
			}
		}
	}

	/**
	 * Render queued messages.
	 */
	private function render_messages() {
		foreach ( $this->messages as $message ) {
			$type = 'notice-info';

			switch ( $message['type'] ) {
				case 'success':
					$type = 'notice-success';
					break;
				case 'error':
					$type = 'notice-error';
					break;
				case 'warning':
					$type = 'notice-warning';
					break;
			}

			printf(
				'<div class="notice %1$s"><p>%2$s</p></div>',
				esc_attr( $type ),
				wp_kses_post( $message['text'] )
			);
		}
	}

	/**
	 * Queue message to display.
	 *
	 * @param string $type Message type.
	 * @param string $text Message text.
	 */
	private function add_message( string $type, string $text ) {
		$this->messages[] = array(
			'type' => $type,
			'text' => $text,
		);
	}

	/**
	 * Render the mapping preview and confirmation form.
	 */
	private function render_preview() {
		if ( empty( $this->preview_data ) || empty( $this->preview_data['mapping']['modules'] ) ) {
			return;
		}

		$modules = $this->preview_data['mapping']['modules'];
		$token   = $this->preview_data['token'];

		?>
		<div class="notice notice-info is-dismissible">
			<p><?php esc_html_e( 'The following course structure was detected. Confirm to complete the import or upload a new document to start over.', 'masterstudy-lms-content-importer' ); ?></p>
		</div>

		<div class="ms-lms-import-preview">
			<h2><?php esc_html_e( 'Course Structure Preview', 'masterstudy-lms-content-importer' ); ?></h2>
			<ol class="ms-lms-import-preview__modules">
				<?php foreach ( $modules as $index => $module ) : ?>
					<li>
						<strong><?php echo esc_html( $module['title'] ?? sprintf( __( 'Module %d', 'masterstudy-lms-content-importer' ), $index + 1 ) ); ?></strong>
						<?php
						$lessons = isset( $module['lessons'] ) && is_array( $module['lessons'] ) ? $module['lessons'] : array();
						?>
						<?php if ( ! empty( $lessons ) ) : ?>
							<ul class="ms-lms-import-preview__lessons">
								<?php foreach ( $lessons as $lesson_index => $lesson ) : ?>
									<li><?php echo esc_html( $lesson['title'] ?? sprintf( __( 'Lesson %d', 'masterstudy-lms-content-importer' ), $lesson_index + 1 ) ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php
						$quiz        = $module['quiz'] ?? array();
						$question_count = isset( $quiz['questions'] ) && is_array( $quiz['questions'] ) ? count( $quiz['questions'] ) : 0;
						?>
						<p class="ms-lms-import-preview__quiz">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: question count */
									_n(
										'%d quiz question detected.',
										'%d quiz questions detected.',
										$question_count,
										'masterstudy-lms-content-importer'
									),
									$question_count
								)
							);
							?>
						</p>
					</li>
				<?php endforeach; ?>
			</ol>
			<form method="post" class="ms-lms-import-preview__actions">
				<?php wp_nonce_field( 'ms_lms_import_course', 'ms_lms_import_nonce' ); ?>
				<input type="hidden" name="ms_lms_import_confirm" value="1" />
				<input type="hidden" name="ms_lms_import_token" value="<?php echo esc_attr( $token ); ?>" />
				<?php submit_button( __( 'Confirm Import', 'masterstudy-lms-content-importer' ), 'primary', 'ms-lms-import-confirm', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Default form values.
	 *
	 * @return array<string, mixed>
	 */
	private function get_default_form_values(): array {
		return array(
			'lesson_title_template' => '%lesson_source_title%',
			'module_identifier'     => 'Module',
			'lesson_identifier'     => '',
			'use_toc'               => true,
		);
	}

	/**
	 * Retrieve a stored form value.
	 *
	 * @param string $key Form key.
	 *
	 * @return mixed
	 */
	private function get_form_value( string $key ) {
		$defaults = $this->get_default_form_values();

		if ( array_key_exists( $key, $this->form_values ) ) {
			return $this->form_values[ $key ];
		}

		return $defaults[ $key ] ?? '';
	}

	/**
	 * Sanitize lesson template input.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private function sanitize_template( string $value ): string {
		$value = wp_strip_all_tags( wp_unslash( $value ) );

		if ( '' === trim( $value ) ) {
			return $this->get_default_form_values()['lesson_title_template'];
		}

		return $value;
	}

	/**
	 * Sanitize identifier input.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private function sanitize_identifier( string $value ): string {
		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Sanitize start page input.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int
	 */
	private function sanitize_start_page( $value ): int {
		$page = (int) $value;

		return max( 1, $page );
	}

	/**
	 * Build a transient key for storing import state.
	 *
	 * @param string $token Token.
	 *
	 * @return string
	 */
	private function get_transient_key( string $token ): string {
		return 'ms_lms_content_import_' . $token;
	}
}
