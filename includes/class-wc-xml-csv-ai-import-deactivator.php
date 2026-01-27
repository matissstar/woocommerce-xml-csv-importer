<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

/**
 * Fired during plugin deactivation.
 */
class WC_XML_CSV_AI_Import_Deactivator {

    /**
     * Plugin deactivation handler.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('wc_xml_csv_ai_import_cleanup');
        wp_clear_scheduled_hook('wc_xml_csv_ai_import_process');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Clean up temporary files (optional - only if user wants complete cleanup)
        $clean_on_deactivate = get_option('wc_xml_csv_ai_import_clean_on_deactivate', false);
        if ($clean_on_deactivate) {
            self::cleanup_temp_files();
        }
    }

    /**
     * Clean up temporary files.
     *
     * @since    1.0.0
     */
    private static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wc-xml-csv-import/temp/';

        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Complete plugin removal (called from uninstall.php).
     *
     * @since    1.0.0
     */
    public static function uninstall() {
        global $wpdb;

        // Remove database tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wc_itp_imports");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wc_itp_import_logs");

        // Remove plugin options
        delete_option('wc_xml_csv_ai_import_settings');
        delete_option('wc_xml_csv_ai_import_db_version');
        delete_option('wc_xml_csv_ai_import_clean_on_deactivate');

        // Remove user meta
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wc_xml_csv_ai_import_%'");

        // Remove upload directory
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/wc-xml-csv-import/';
        if (is_dir($plugin_upload_dir)) {
            self::remove_directory($plugin_upload_dir);
        }

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Recursively remove directory.
     *
     * @since    1.0.0
     * @param    string $dir Directory path
     */
    private static function remove_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::remove_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}