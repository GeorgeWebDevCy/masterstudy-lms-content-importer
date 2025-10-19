<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.georgenicolaou.me
 * @since             1.0.0
 * @package           Masterstudy_Lms_Content_Importer
 *
 * @wordpress-plugin
 * Plugin Name:       Masterstudy LMS Content Importer
 * Plugin URI:        https://www.georgenicolaou.me/plugins/masterstudy-lms-content-importer
 * Description:       Accepts a Word Document and imports it as a Masterstudy Course (courses,lessons,sections,quizzes)
 * Version:           1.13.4
 * Author:            George Nicolaou
 * Author URI:        https://www.georgenicolaou.me/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       masterstudy-lms-content-importer
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'MASTERSTUDY_LMS_CONTENT_IMPORTER_VERSION', '1.13.4' );

/**
 * Set up automatic updates via GitHub using Plugin Update Checker.
 */
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
        require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-masterstudy-lms-content-importer-updater.php';

if ( class_exists( 'Masterstudy_Lms_Content_Importer_Updater' ) ) {
        new Masterstudy_Lms_Content_Importer_Updater( __FILE__ );
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-masterstudy-lms-content-importer-activator.php
 */
function activate_masterstudy_lms_content_importer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-masterstudy-lms-content-importer-activator.php';
	Masterstudy_Lms_Content_Importer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-masterstudy-lms-content-importer-deactivator.php
 */
function deactivate_masterstudy_lms_content_importer() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-masterstudy-lms-content-importer-deactivator.php';
	Masterstudy_Lms_Content_Importer_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_masterstudy_lms_content_importer' );
register_deactivation_hook( __FILE__, 'deactivate_masterstudy_lms_content_importer' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-masterstudy-lms-content-importer.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_masterstudy_lms_content_importer() {

	$plugin = new Masterstudy_Lms_Content_Importer();
	$plugin->run();

}
run_masterstudy_lms_content_importer();
