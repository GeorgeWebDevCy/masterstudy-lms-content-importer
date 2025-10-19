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
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Masterstudy_Lms_Content_Importer
 * @subpackage Masterstudy_Lms_Content_Importer/admin
 * @author     George Nicolaou <oriobas.elite@gmail.com>
 */
class Masterstudy_Lms_Content_Importer_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Messages to display on the admin page.
	 *
	 * @var array<int, array{type:string, text:string}>
	 */
	private $messages = array();

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Masterstudy_Lms_Content_Importer_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Masterstudy_Lms_Content_Importer_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/masterstudy-lms-content-importer-admin.css', array(), $this->version, 'all' );

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
				<?php esc_html_e( 'Upload a .docx file that follows the MasterStudy LMS curriculum structure (Modules, practical exercises, and tests). The importer will create a new course with sections, lessons, and quizzes.', 'masterstudy-lms-content-importer' ); ?>
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
					'author_id' => get_current_user_id(),
					'status'    => 'publish',
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
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Masterstudy_Lms_Content_Importer_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Masterstudy_Lms_Content_Importer_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/masterstudy-lms-content-importer-admin.js', array( 'jquery' ), $this->version, false );

	}

}
