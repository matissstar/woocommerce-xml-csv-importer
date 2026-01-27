<?php
/**
 * Define the internationalization functionality.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

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
        load_plugin_textdomain(
            'wc-xml-csv-import',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}