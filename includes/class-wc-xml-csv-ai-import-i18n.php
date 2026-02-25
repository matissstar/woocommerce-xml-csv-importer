<?php
/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define the internationalization functionality.
 */
class WC_XML_CSV_AI_Import_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain() {
        // WP.org compliance: text domain must match plugin slug
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for development/non-WP.org translation loading
        load_plugin_textdomain(
            'bootflow-product-importer',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}