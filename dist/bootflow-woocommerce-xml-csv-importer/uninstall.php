<?php
/**
 * Plugin Uninstaller
 *
 * Fired when the plugin is deleted. This file is responsible for cleaning up
 * plugin data when the plugin is uninstalled.
 *
 * WP.org compliance: Only deletes this plugin's data with proper prefixes
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

// WP.org compliance: security check for uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// WP.org compliance: additional security - verify we're uninstalling THIS plugin
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clean up plugin data
 */

global $wpdb;

// Check if user wants to keep data
$keep_data = get_option('wc_xml_csv_ai_import_keep_data_on_uninstall', false);

if (!$keep_data) {
    // WP.org compliance: Delete custom database tables with proper escaping
    // These tables are created by this plugin only
    $table_imports = $wpdb->prefix . 'wc_itp_imports';
    $table_logs = $wpdb->prefix . 'wc_itp_import_logs';
    
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_imports));
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup
    $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table_logs));
    
    // Delete plugin options - only this plugin's options with specific prefix
    $options_to_delete = array(
        'wc_xml_csv_ai_import_version',
        'wc_xml_csv_ai_import_ai_settings',
        'wc_xml_csv_ai_import_performance_settings',
        'wc_xml_csv_ai_import_import_settings',
        'wc_xml_csv_ai_import_file_settings',
        'wc_xml_csv_ai_import_logging_settings',
        'wc_xml_csv_ai_import_security_settings',
        'wc_xml_csv_ai_import_keep_data_on_uninstall',
        'wc_xml_csv_ai_import_license'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // WP.org compliance: Delete transients with specific plugin prefix only
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Uninstall cleanup with specific prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_wc_xml_csv_ai_import_%'
        )
    );
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Uninstall cleanup with specific prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_wc_xml_csv_ai_import_%'
        )
    );
    
    // Delete uploaded files (if directory exists and is within uploads)
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = trailingslashit($upload_dir['basedir']) . 'woo_xml_csv_ai_smart_import/';
    
    // WP.org compliance: verify path is within uploads before deletion
    if (file_exists($plugin_upload_dir) && strpos(realpath($plugin_upload_dir), realpath($upload_dir['basedir'])) === 0) {
        // Recursively delete directory and contents
        wc_xml_csv_ai_import_delete_directory($plugin_upload_dir);
    }
    
    // Clear any scheduled events
    $scheduled_hooks = array(
        'wc_xml_csv_ai_import_process_batch',
        'wc_xml_csv_ai_import_cleanup_files',
        'wc_xml_csv_ai_import_cleanup_logs'
    );
    
    foreach ($scheduled_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
    
    // WP.org compliance: Delete user meta with specific plugin prefix only
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Uninstall cleanup with specific prefix
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            'wc_xml_csv_ai_import_%'
        )
    );
    
    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Recursively delete a directory
 * WP.org compliance: helper function for cleanup
 *
 * @param string $dir Directory path
 * @return bool Success
 */
function wc_xml_csv_ai_import_delete_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return wp_delete_file($dir);
    }
    
    $items = scandir($dir);
    if ($items === false) {
        return false;
    }
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        if (!wc_xml_csv_ai_import_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}