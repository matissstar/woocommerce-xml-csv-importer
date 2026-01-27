<?php
/**
 * Security and Validation Helper Class
 *
 * Provides security functions, input validation, and sanitization
 * for the XML/CSV AI Import plugin
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WC_XML_CSV_AI_Import_Security {

    /**
     * Initialize security measures
     */
    public static function init() {
        // Add security headers
        add_action('admin_init', array(__CLASS__, 'add_security_headers'));
        
        // Validate all AJAX requests
        add_action('wp_ajax_wc_xml_csv_ai_import_upload_file', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_parse_file', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_test_ai', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_start_import', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_get_progress', array(__CLASS__, 'validate_ajax_request'), 1);
        // NOTE: ping_cron removed from validation - it only calls spawn_cron() and needs to work with GET requests
        add_action('wp_ajax_wc_xml_csv_ai_import_control_import', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_save_mapping', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_process_batch', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_kickstart', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_delete_products_batch', array(__CLASS__, 'validate_ajax_request'), 1);
        add_action('wp_ajax_wc_xml_csv_ai_import_get_products_count', array(__CLASS__, 'validate_ajax_request'), 1);
    }

    /**
     * Add security headers
     */
    public static function add_security_headers() {
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'wc-xml-csv-import') !== false) {
            // Only add headers if they haven't been sent yet
            if (!headers_sent()) {
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: SAMEORIGIN');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
            }
        }
    }

    /**
     * Validate AJAX requests
     */
    public static function validate_ajax_request() {
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SECURITY validate_ajax_request CALLED! ★★★'); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Action: ' . ($_POST['action'] ?? 'no action')); }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SECURITY FAILED: not logged in ★★★'); }
            wp_die(__('You must be logged in to perform this action.', 'wc-xml-csv-import'));
        }

        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SECURITY FAILED: no manage_woocommerce capability ★★★'); }
            wp_die(__('You do not have permission to perform this action.', 'wc-xml-csv-import'));
        }

        // Verify nonce - support both standard POST and FormData
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '');
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SECURITY nonce: ' . ($nonce ? substr($nonce, 0, 10) . '...' : 'EMPTY')); }
        
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wc_xml_csv_ai_import_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SECURITY FAILED: nonce check failed ★★★'); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce empty: ' . (empty($nonce) ? 'YES' : 'NO')); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce verify: ' . (wp_verify_nonce($nonce, 'wc_xml_csv_ai_import_nonce') ? 'PASS' : 'FAIL')); }
            wp_die(__('Security check failed.', 'wc-xml-csv-import'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SECURITY PASSED! ★★★'); }

        // Rate limiting
        $user_id = get_current_user_id();
        $rate_limit_key = 'wc_xml_csv_ai_import_rate_limit_' . $user_id;
        $current_count = get_transient($rate_limit_key) ?: 0;
        
        if ($current_count >= 60) { // 60 requests per minute
            wp_die(__('Rate limit exceeded. Please wait before making another request.', 'wc-xml-csv-import'));
        }
        
        set_transient($rate_limit_key, $current_count + 1, 60);
    }

    /**
     * Sanitize file upload
     */
    public static function sanitize_file_upload($file) {
        $errors = array();
        
        if (!$file || !is_array($file)) {
            $errors[] = __('No file uploaded.', 'wc-xml-csv-import');
            return array('file' => null, 'errors' => $errors);
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = __('File is too large.', 'wc-xml-csv-import');
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = __('File upload was incomplete.', 'wc-xml-csv-import');
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                case UPLOAD_ERR_CANT_WRITE:
                    $errors[] = __('Server error during upload.', 'wc-xml-csv-import');
                    break;
                default:
                    $errors[] = __('Unknown upload error.', 'wc-xml-csv-import');
                    break;
            }
            return array('file' => null, 'errors' => $errors);
        }

        // Validate file name
        $filename = sanitize_file_name($file['name']);
        if (empty($filename)) {
            $errors[] = __('Invalid file name.', 'wc-xml-csv-import');
            return array('file' => null, 'errors' => $errors);
        }

        // Check file extension
        $allowed_extensions = array('xml', 'csv');
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = __('File type not allowed. Only XML and CSV files are supported.', 'wc-xml-csv-import');
            return array('file' => null, 'errors' => $errors);
        }

        // Check file size
        $max_size = 100 * 1024 * 1024; // 100MB
        if ($file['size'] > $max_size) {
            $errors[] = __('File is too large. Maximum size is 100MB.', 'wc-xml-csv-import');
            return array('file' => null, 'errors' => $errors);
        }

        // Check MIME type
        $allowed_mimes = array(
            'xml' => array('application/xml', 'text/xml'),
            'csv' => array('text/csv', 'application/csv', 'text/plain')
        );

        $file_mime = mime_content_type($file['tmp_name']);
        if (!in_array($file_mime, $allowed_mimes[$file_extension])) {
            $errors[] = __('File MIME type does not match extension.', 'wc-xml-csv-import');
            return array('file' => null, 'errors' => $errors);
        }

        // Scan for malicious content
        if (self::scan_file_for_threats($file['tmp_name'], $file_extension)) {
            $errors[] = __('File contains potentially malicious content.', 'wc-xml-csv-import');
            return array('file' => null, 'errors' => $errors);
        }

        return array('file' => $file, 'errors' => $errors);
    }

    /**
     * Scan file for threats
     */
    private static function scan_file_for_threats($file_path, $extension) {
        $dangerous_patterns = array(
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/file_get_contents\s*\(/i',
        );

        // Read first 8KB of file for scanning
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return true; // Err on the side of caution
        }

        $content = fread($handle, 8192);
        fclose($handle);

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize field mapping data
     */
    public static function sanitize_field_mapping($mapping_data) {
        if (!is_array($mapping_data)) {
            return array();
        }

        $sanitized = array();
        
        foreach ($mapping_data as $source_field => $mapping) {
            $source_field = sanitize_text_field($source_field);
            
            if (!is_array($mapping)) {
                continue;
            }

            $sanitized_mapping = array(
                'target' => sanitize_text_field($mapping['target'] ?? ''),
                'mode' => sanitize_text_field($mapping['mode'] ?? 'direct'),
                'formula' => self::sanitize_php_formula($mapping['formula'] ?? ''),
                'ai_prompt' => sanitize_textarea_field($mapping['ai_prompt'] ?? ''),
                'ai_provider' => sanitize_text_field($mapping['ai_provider'] ?? ''),
                'enabled' => (bool)($mapping['enabled'] ?? true)
            );

            // Validate mode
            $allowed_modes = array('direct', 'php_formula', 'ai', 'hybrid');
            if (!in_array($sanitized_mapping['mode'], $allowed_modes)) {
                $sanitized_mapping['mode'] = 'direct';
            }

            // Validate target field
            $allowed_targets = array(
                'name', 'description', 'short_description', 'sku', 'price', 'sale_price',
                'stock_quantity', 'category', 'tags', 'images', 'weight', 'length',
                'width', 'height', 'status', 'visibility', 'featured'
            );
            
            if (!in_array($sanitized_mapping['target'], $allowed_targets)) {
                continue; // Skip invalid targets
            }

            $sanitized[$source_field] = $sanitized_mapping;
        }

        return $sanitized;
    }

    /**
     * Sanitize PHP formula
     */
    public static function sanitize_php_formula($formula) {
        if (empty($formula)) {
            return '';
        }

        $formula = trim($formula);
        
        // Remove PHP tags if present
        $formula = str_replace(array('<?php', '<?', '?>'), '', $formula);
        
        // Check formula length
        if (strlen($formula) > 500) {
            return '';
        }

        // Get allowed functions from settings
        $security_settings = get_option('wc_xml_csv_ai_import_security_settings', array());
        $allowed_functions = $security_settings['allowed_php_functions'] ?? array(
            'strlen', 'substr', 'trim', 'strtoupper', 'strtolower', 'ucfirst',
            'number_format', 'round', 'ceil', 'floor', 'abs', 'str_replace'
        );

        // Check for dangerous functions
        $dangerous_functions = array(
            'eval', 'exec', 'system', 'shell_exec', 'passthru', 'file_get_contents',
            'file_put_contents', 'fopen', 'fwrite', 'include', 'require', 'unlink',
            'rmdir', 'mkdir', 'chmod', 'chown', 'mail', 'header', 'setcookie',
            'session_start', 'phpinfo', 'highlight_file', 'show_source'
        );

        foreach ($dangerous_functions as $func) {
            if (preg_match('/\b' . preg_quote($func, '/') . '\s*\(/i', $formula)) {
                return ''; // Reject formula with dangerous functions
            }
        }

        // Validate that only allowed functions are used
        if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $formula, $matches)) {
            foreach ($matches[1] as $function_name) {
                if (!in_array(strtolower($function_name), array_map('strtolower', $allowed_functions))) {
                    return ''; // Reject formula with disallowed functions
                }
            }
        }

        return $formula;
    }

    /**
     * Sanitize AI prompt
     */
    public static function sanitize_ai_prompt($prompt) {
        if (empty($prompt)) {
            return '';
        }

        // Basic sanitization
        $prompt = sanitize_textarea_field($prompt);
        
        // Check length
        if (strlen($prompt) > 2000) {
            $prompt = substr($prompt, 0, 2000);
        }

        // Remove any HTML tags
        $prompt = wp_strip_all_tags($prompt);
        
        // Check for potential injection attempts
        $dangerous_patterns = array(
            '/\bignore\s+previous\s+instructions\b/i',
            '/\bforget\s+everything\b/i',
            '/\bact\s+as\s+a\b/i',
            '/\bpretend\s+you\s+are\b/i',
            '/\brole\s*:\s*system\b/i',
            '/\bsystem\s*:\b/i',
        );

        foreach ($dangerous_patterns as $pattern) {
            $prompt = preg_replace($pattern, '[FILTERED]', $prompt);
        }

        return $prompt;
    }

    /**
     * Validate import settings
     */
    public static function validate_import_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }

        $validated = array();
        
        // Validate import name
        $validated['name'] = sanitize_text_field($settings['name'] ?? '');
        if (empty($validated['name'])) {
            $validated['name'] = 'Import ' . date('Y-m-d H:i:s');
        }

        // Validate schedule
        $allowed_schedules = array('disabled', 'once', 'hourly', 'daily', 'weekly', 'monthly');
        $validated['schedule'] = sanitize_text_field($settings['schedule'] ?? 'disabled');
        if (!in_array($validated['schedule'], $allowed_schedules)) {
            $validated['schedule'] = 'disabled';
        }

        // Validate batch size
        $validated['batch_size'] = absint($settings['batch_size'] ?? 50);
        if ($validated['batch_size'] < 1 || $validated['batch_size'] > 500) {
            $validated['batch_size'] = 50;
        }

        // Validate other boolean settings
        $validated['update_existing'] = (bool)($settings['update_existing'] ?? false);
        $validated['create_categories'] = (bool)($settings['create_categories'] ?? false);
        $validated['download_images'] = (bool)($settings['download_images'] ?? false);

        return $validated;
    }

    /**
     * Secure file path
     */
    public static function secure_file_path($path) {
        // Remove any directory traversal attempts
        $path = str_replace(array('../', '..\\', '../', '..\\'), '', $path);
        
        // Remove null bytes
        $path = str_replace(chr(0), '', $path);
        
        // Sanitize
        $path = sanitize_file_name(basename($path));
        
        return $path;
    }

    /**
     * Log security events
     */
    public static function log_security_event($event, $details = '') {
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_ip' => self::get_user_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'event' => $event,
            'details' => $details,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        );

        // Log to WordPress error log
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import Security Event: ' . json_encode($log_data)); }
        
        // Also log to plugin's logging system if available
        if (class_exists('WC_XML_CSV_AI_Import_Logger')) {
            WC_XML_CSV_AI_Import_Logger::log('security', $event, $details);
        }
    }

    /**
     * Get user IP address
     */
    private static function get_user_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Check if request is from admin area
     */
    public static function is_admin_request() {
        return is_admin() || (defined('DOING_AJAX') && DOING_AJAX);
    }

    /**
     * Validate database queries
     */
    public static function validate_db_query($table, $operation, $data = array()) {
        global $wpdb;
        
        // Ensure table name is valid
        $allowed_tables = array(
            $wpdb->prefix . 'wc_itp_imports',
            $wpdb->prefix . 'wc_itp_import_logs'
        );
        
        if (!in_array($table, $allowed_tables)) {
            self::log_security_event('invalid_table_access', $table);
            return false;
        }

        // Validate operation
        $allowed_operations = array('SELECT', 'INSERT', 'UPDATE', 'DELETE');
        if (!in_array(strtoupper($operation), $allowed_operations)) {
            self::log_security_event('invalid_db_operation', $operation);
            return false;
        }

        // Log potentially dangerous operations
        if (in_array(strtoupper($operation), array('DELETE', 'UPDATE')) && empty($data['where'])) {
            self::log_security_event('dangerous_db_operation', $operation . ' without WHERE clause');
            return false;
        }

        return true;
    }
}

// Initialize security measures
WC_XML_CSV_AI_Import_Security::init();