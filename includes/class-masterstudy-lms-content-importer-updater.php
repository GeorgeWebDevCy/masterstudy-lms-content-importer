<?php

/**
 * Handles automatic update checks for the plugin using the Plugin Update Checker library.
 *
 * @package    Masterstudy_Lms_Content_Importer
 * @subpackage Masterstudy_Lms_Content_Importer/includes
 */
class Masterstudy_Lms_Content_Importer_Updater {

        /**
         * Repository URL used for update checks.
         */
        const REPOSITORY_URL = 'https://github.com/GeorgeWebDevCy/masterstudy-lms-content-importer/';

        /**
         * The update checker instance.
         *
         * @var \YahnisElsts\PluginUpdateChecker\v5p6\Plugin\UpdateChecker|null
         */
        private $update_checker = null;

        /**
         * Bootstraps the updater.
         *
         * @param string $plugin_file Absolute path to the main plugin file.
         */
        public function __construct( $plugin_file ) {
                if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
                        return;
                }

                $this->update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
                        self::REPOSITORY_URL,
                        $plugin_file,
                        'masterstudy-lms-content-importer'
                );

                if ( null === $this->update_checker ) {
                        return;
                }

                $this->update_checker->setBranch( 'main' );

                $token = $this->get_authentication_token();
                if ( ! empty( $token ) ) {
                        $this->update_checker->setAuthentication( array( 'token' => $token ) );
                }

                $this->maybe_enable_release_assets();
        }

        /**
         * Fetch an optional authentication token from a constant or environment variable.
         *
         * @return string
         */
        private function get_authentication_token() {
                if ( defined( 'MASTERSTUDY_LMS_CONTENT_IMPORTER_GITHUB_TOKEN' ) ) {
                        return MASTERSTUDY_LMS_CONTENT_IMPORTER_GITHUB_TOKEN;
                }

                $token = getenv( 'MASTERSTUDY_LMS_CONTENT_IMPORTER_GITHUB_TOKEN' );
                if ( false !== $token ) {
                        return $token;
                }

                return '';
        }

        /**
         * Enable release asset support when available.
         */
        private function maybe_enable_release_assets() {
                if ( ! method_exists( $this->update_checker, 'getVcsApi' ) ) {
                        return;
                }

                $vcs_api = $this->update_checker->getVcsApi();
                if ( null === $vcs_api || ! method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
                        return;
                }

                $vcs_api->enableReleaseAssets();
        }
}
