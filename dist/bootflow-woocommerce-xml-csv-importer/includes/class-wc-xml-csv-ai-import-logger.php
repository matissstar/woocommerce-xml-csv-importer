<?php
/**
 * Logger Class
 *
 * Provides debug logging that respects WP_DEBUG setting.
 * Replaces direct error_log calls throughout the plugin.
 *
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 * @since      1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Class
 */
class WC_XML_CSV_AI_Import_Logger {

    /**
     * Log a debug message (only if WP_DEBUG is enabled)
     *
     * @param string $message Message to log
     * @param string $level   Log level: debug, info, warning, error
     */
    public static function log($message, $level = 'debug') {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Format the message
        $prefix = '[WC XML CSV AI Import]';
        $formatted = sprintf('%s [%s] %s', $prefix, strtoupper($level), $message);

        // Log to error_log
        error_log($formatted);
    }

    /**
     * Log debug message
     *
     * @param string $message Message to log
     */
    public static function debug($message) {
        self::log($message, 'debug');
    }

    /**
     * Log info message
     *
     * @param string $message Message to log
     */
    public static function info($message) {
        self::log($message, 'info');
    }

    /**
     * Log warning message
     *
     * @param string $message Message to log
     */
    public static function warning($message) {
        self::log($message, 'warning');
    }

    /**
     * Log error message (always logs, even without WP_DEBUG)
     *
     * @param string $message Message to log
     */
    public static function error($message) {
        $prefix = '[WC XML CSV AI Import]';
        $formatted = sprintf('%s [ERROR] %s', $prefix, $message);
        error_log($formatted);
    }

    /**
     * Log to a custom file (only if WP_DEBUG is enabled)
     *
     * @param string $filename Filename without path
     * @param string $message  Message to log
     */
    public static function log_to_file($filename, $message) {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/' . sanitize_file_name($filename);
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted = sprintf("[%s] %s\n", $timestamp, $message);
        
        // Use error suppression and check result
        $result = @file_put_contents($log_file, $formatted, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            self::error("Could not write to log file: $filename");
        }
    }

    /**
     * Clear a log file
     *
     * @param string $filename Filename without path
     */
    public static function clear_log_file($filename) {
        $log_file = WP_CONTENT_DIR . '/' . sanitize_file_name($filename);
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
    }
}

/**
 * Global helper function for logging
 *
 * @param string $message Message to log
 * @param string $level   Log level
 */
function wc_xml_csv_ai_log($message, $level = 'debug') {
    WC_XML_CSV_AI_Import_Logger::log($message, $level);
}
