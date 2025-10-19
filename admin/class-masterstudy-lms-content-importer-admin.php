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
	 * Sanitized values to repopulate the form.
	 *
	 * @var array<string, mixed>
	 */
	private $form_values = array();

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
				</table>
				<?php submit_button( __( 'Import Course', 'masterstudy-lms-content-importer' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle uploaded DOCX and trigger importer.
	 */
	private function handle_import_request() {
		check_admin_referer( 'ms_lms_import_course', 'ms_lms_import_nonce' );

		$values = array(
			'lesson_title_template' => $this->sanitize_template( $_POST['ms_lms_lesson_title_template'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'module_identifier'     => $this->sanitize_identifier( $_POST['ms_lms_module_identifier'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'lesson_identifier'     => $this->sanitize_identifier( $_POST['ms_lms_lesson_identifier'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'use_toc'               => ! empty( $_POST['ms_lms_use_toc'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);

		$this->form_values = array_merge( $this->get_default_form_values(), $values );

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
			$importer = new Masterstudy_Lms_Content_Importer_Importer( $parser );

			$course_id = $importer->import(
				$file_path,
				array(
					'author_id'             => get_current_user_id(),
					'status'                => 'publish',
					'lesson_title_template' => $this->form_values['lesson_title_template'],
					'parser_options'        => array(
						'lesson_title_template' => $this->form_values['lesson_title_template'],
						'module_identifier'     => $this->form_values['module_identifier'],
						'lesson_identifier'     => $this->form_values['lesson_identifier'],
						'use_toc'               => $this->form_values['use_toc'],
					),
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
		} catch ( Exception $exception ) {
			$this->add_message( 'error', $exception->getMessage() );
		} finally {
			if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
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
}
