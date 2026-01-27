<?php
/**
 * Plugin Uninstaller
 *
 * Fired when the plugin is deleted. This file is responsible for cleaning up
 * plugin data when the plugin is uninstalled.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */

global $wpdb;

// Check if user wants to keep data
$keep_data = get_option('wc_xml_csv_ai_import_keep_data_on_uninstall', false);

if (!$keep_data) {
    // Delete custom database tables
    $tables = array(
        $wpdb->prefix . 'wc_itp_imports',
        $wpdb->prefix . 'wc_itp_import_logs'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Delete plugin options
    $options_to_delete = array(
        'wc_xml_csv_ai_import_version',
        'wc_xml_csv_ai_import_ai_settings',
        'wc_xml_csv_ai_import_performance_settings',
        'wc_xml_csv_ai_import_import_settings',
        'wc_xml_csv_ai_import_file_settings',
        'wc_xml_csv_ai_import_logging_settings',
        'wc_xml_csv_ai_import_security_settings',
        'wc_xml_csv_ai_import_keep_data_on_uninstall'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Delete transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_xml_csv_ai_import_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_xml_csv_ai_import_%'");
    
    // Delete uploaded files (if directory exists and is within uploads)
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import/';
    
    if (file_exists($plugin_upload_dir) && strpos($plugin_upload_dir, $upload_dir['basedir']) === 0) {
        // Recursively delete directory and contents
        function wc_xml_csv_ai_import_delete_directory($dir) {
            if (!file_exists($dir)) {
                return true;
            }
            
            if (!is_dir($dir)) {
                return unlink($dir);
            }
            
            foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') {
                    continue;
                }
                
                if (!wc_xml_csv_ai_import_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                    return false;
                }
            }
            
            return rmdir($dir);
        }
        
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
    
    // Delete user meta related to the plugin
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wc_xml_csv_ai_import_%'");
    
    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    // Log uninstall event
} else {
}