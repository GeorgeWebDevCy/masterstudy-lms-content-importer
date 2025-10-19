<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://https://www.georgenicolaou.me
 * @since      1.0.0
 *
 * @package    Masterstudy_Lms_Content_Importer
 * @subpackage Masterstudy_Lms_Content_Importer/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Masterstudy_Lms_Content_Importer
 * @subpackage Masterstudy_Lms_Content_Importer/includes
 * @author     George Nicolaou <oriobas.elite@gmail.com>
 */
class Masterstudy_Lms_Content_Importer_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'masterstudy-lms-content-importer',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
