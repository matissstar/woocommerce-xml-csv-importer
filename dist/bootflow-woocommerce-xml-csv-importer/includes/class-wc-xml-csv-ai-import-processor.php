<?php
/**
 * Field Processor for handling different processing modes.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Field Processor class.
 */
class WC_XML_CSV_AI_Import_Processor {

    /**
     * AI Providers instance
     *
     * @var WC_XML_CSV_AI_Import_AI_Providers
     */
    private $ai_providers;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->ai_providers = null; // AI disabled in FREE version
    }

    /**
     * Process field value based on configuration.
     *
     * @since    1.0.0
     * @param    mixed $value Original field value
     * @param    array $config Field configuration
     * @param    array $product_data Complete product data for context
     * @return   mixed Processed value
     */
    public function process_field($value, $config, $product_data = array()) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PROCESSOR: process_field called with value={$value}, config=" . json_encode($config)); }
            
            $processing_mode = isset($config['processing_mode']) ? $config['processing_mode'] : 'direct';
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PROCESSOR: processing_mode = {$processing_mode}"); }

            switch ($processing_mode) {
                case 'direct':
                    return $this->process_direct($value);
                
                case 'php_formula':
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PROCESSOR: Entering php_formula case"); }
                    return $this->process_php_formula($value, $config, $product_data);
                
                case 'ai_processing':
                    return $this->process_ai($value, $config, $product_data);
                
                case 'hybrid':
                    return $this->process_hybrid($value, $config, $product_data);
                
                default:
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PROCESSOR: Default case - using direct processing"); }
                    return $this->process_direct($value);
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Field Processing Error: ' . $e->getMessage()); }
            // Return original value on error
            return $value;
        }
    }

    /**
     * Process field with direct mapping (no transformation).
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @return   mixed Sanitized value
     */
    private function process_direct($value) {
        if (is_array($value)) {
            return implode(', ', array_filter($value, 'is_scalar'));
        }
        
        return is_string($value) ? trim($value) : $value;
    }

    /**
     * Process field with PHP formula.
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @param    array $config Field configuration
     * @param    array $product_data Product context
     * @return   mixed Processed value
     */
    private function process_php_formula($value, $config, $product_data = array()) {
        // PRO feature check - PHP formulas require PRO license
        if (!WC_XML_CSV_AI_Import_Features::is_available('php_processing')) {
            $this->log_debug('PHP_FORMULA_BLOCKED: PRO license required for PHP formulas', array());
            return $this->process_direct($value); // Return direct value without formula processing
        }
        
        $this->log_debug('PHP_FORMULA_START: Processing field with PHP formula', array(
            'original_value' => is_string($value) ? substr($value, 0, 100) : gettype($value),
            'value_length' => is_string($value) ? strlen($value) : 'N/A'
        ));
        
        $formula = isset($config['php_formula']) ? trim($config['php_formula']) : '';
        
        if (empty($formula)) {
            $this->log_debug('PHP_FORMULA_EMPTY: Formula is empty, using direct processing', array());
            return $this->process_direct($value);
        }

        $this->log_debug('PHP_FORMULA_EXECUTE: Executing formula', array(
            'formula_length' => strlen($formula),
            'formula_preview' => substr($formula, 0, 200)
        ));
        
        $start_time = microtime(true);
        
        try {
            // Create sandboxed environment
            $result = $this->execute_php_formula($formula, $value, $product_data);
            
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $this->log_debug('PHP_FORMULA_SUCCESS: Formula executed successfully', array(
                'execution_time_ms' => $execution_time,
                'result_length' => is_string($result) ? strlen($result) : 'N/A',
                'result_preview' => is_string($result) ? substr($result, 0, 200) : gettype($result)
            ));
            
            return $result;
        } catch (Exception $e) {
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $this->log_debug('PHP_FORMULA_ERROR: Formula execution failed', array(
                'execution_time_ms' => $execution_time,
                'error' => $e->getMessage(),
                'formula_preview' => substr($formula, 0, 200)
            ));
            
            return $value; // Return original on error
        }
    }

    /**
     * Process field with AI.
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @param    array $config Field configuration
     * @param    array $product_data Product context
     * @return   mixed Processed value
     */
    private function process_ai($value, $config, $product_data = array()) {
        $prompt = isset($config['ai_prompt']) ? trim($config['ai_prompt']) : '';
        $provider = isset($config['ai_provider']) ? $config['ai_provider'] : 'openai';
        
        $this->log_debug('AI_PROCESSING_START: Starting AI field processing', array(
            'provider' => $provider,
            'prompt_length' => strlen($prompt),
            'original_value' => is_string($value) ? substr($value, 0, 100) : gettype($value),
            'value_length' => is_string($value) ? strlen($value) : 'N/A'
        ));
        
        if (empty($prompt)) {
            $this->log_debug('AI_PROCESSING_EMPTY: No prompt provided, using direct processing', array());
            return $this->process_direct($value);
        }

        // Build context for AI
        $context = $this->build_product_context($product_data);
        
        $this->log_debug('AI_PROCESSING_CONTEXT: Built product context', array(
            'context_fields' => array_keys($context),
            'context_size' => strlen(json_encode($context))
        ));
        
        $start_time = microtime(true);
        
        try {
            $result = $this->ai_providers->process_field($value, $prompt, array('provider' => $provider), $context);
            
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $this->log_debug('AI_PROCESSING_SUCCESS: AI processing completed', array(
                'provider' => $provider,
                'execution_time_ms' => $execution_time,
                'result_length' => is_string($result) ? strlen($result) : 'N/A',
                'result_preview' => is_string($result) ? substr($result, 0, 200) : gettype($result)
            ));
            
            return $result;
        } catch (Exception $e) {
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            
            $this->log_debug('AI_PROCESSING_ERROR: AI processing failed', array(
                'provider' => $provider,
                'execution_time_ms' => $execution_time,
                'error' => $e->getMessage(),
                'prompt_preview' => substr($prompt, 0, 200)
            ));
            
            return $value; // Return original on error
        }
    }

    /**
     * Process field with hybrid mode (AI first, then PHP).
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @param    array $config Field configuration
     * @param    array $product_data Product context
     * @return   mixed Processed value
     */
    private function process_hybrid($value, $config, $product_data = array()) {
        
        // First, apply AI processing if configured
        $ai_prompt = isset($config['hybrid_ai_prompt']) ? trim($config['hybrid_ai_prompt']) : '';
        $ai_provider = isset($config['hybrid_ai_provider']) ? $config['hybrid_ai_provider'] : 'openai';
        
        
        if (!empty($ai_prompt)) {
            $context = $this->build_product_context($product_data);
            $value = $this->ai_providers->process_field($value, $ai_prompt, array('provider' => $ai_provider), $context);
        }

        // Then, apply PHP formula for technical adjustments (trim, lowercase, etc)
        $php_formula = isset($config['hybrid_php']) ? trim($config['hybrid_php']) : '';
        
        if (!empty($php_formula)) {
            $value = $this->execute_php_formula($php_formula, $value, $product_data);
        }

        return $value;
    }

    /**
     * Execute PHP formula in sandboxed environment.
     *
     * @since    1.0.0
     * @param    string $formula PHP formula
     * @param    mixed $value Original value
     * @param    array $product_data Product data
     * @return   mixed Result
     */
    private function execute_php_formula($formula, $value, $product_data = array()) {
        
        try {
            // Security: Check if PHP formulas are enabled
            $settings = get_option('wc_xml_csv_ai_import_settings', array());
            if (empty($settings['enable_php_formulas'])) {
                throw new Exception(__('PHP formulas are disabled in settings.', 'bootflow-woocommerce-xml-csv-importer'));
            }
            

            // Whitelist of allowed functions
            // WP.org compliance: preg_replace removed as it can execute code with /e modifier in older PHP
            $allowed_functions = array(
                // Math functions
                'abs', 'ceil', 'floor', 'round', 'max', 'min', 'number_format', 'pow', 'sqrt', 'log', 'exp', 'fmod',
                // String functions
                'trim', 'strlen', 'substr', 'strtolower', 'strtoupper', 'ucwords', 'ucfirst',
                'str_replace', 'str_ireplace', 'htmlspecialchars', 'strip_tags', 'nl2br', 'wordwrap',
                'ltrim', 'rtrim', 'str_pad', 'str_repeat', 'strrev', 'chunk_split',
                // Multibyte string functions (UTF-8 support)
                'mb_strtolower', 'mb_strtoupper', 'mb_strlen', 'mb_substr', 'mb_convert_case',
                // Array functions
                'count', 'array_merge', 'implode', 'explode', 'in_array', 'array_key_exists', 'array_map', 'array_slice',
                'array_filter', 'array_values', 'array_keys', 'array_unique', 'array_reverse', 'array_pop', 'array_shift',
                // Type functions
                'is_numeric', 'is_string', 'is_array', 'empty', 'isset', 'intval', 'floatval', 'strval', 'boolval',
                // Other safe functions
                'date', 'time', 'strtotime', 'sprintf', 'json_encode', 'json_decode', 'preg_match', 'preg_match_all',
                // Control structures (not functions, but caught by regex)
                'if', 'else', 'elseif', 'switch', 'case', 'for', 'foreach', 'while', 'do', 'return'
            );

            // Security check: Scan for disallowed functions and constructs
            $dangerous_patterns = array(
                '/\b(eval|exec|system|shell_exec|passthru|file_get_contents|file_put_contents|fopen|fwrite|include|require)\s*\(/i',
                '/\$\w*GLOBALS|\$_[A-Z]+/i', // Global variables
                '/\b(function|class|interface|trait)\s+/i', // Function/class definitions
                '/\-\>|\:\:/i', // Object/static method calls
                '/\$this\b/i' // $this reference
            );

            foreach ($dangerous_patterns as $pattern) {
                if (preg_match($pattern, $formula)) {
                    throw new Exception(__('Formula contains disallowed constructs.', 'bootflow-woocommerce-xml-csv-importer'));
                }
            }

            // Extract function calls and validate
            // First, remove all string literals from formula to avoid false positives
            // (e.g., "'Akumulatori' => 'Akumulators'" should not be detected as function call)
            $formula_without_strings = preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/', '', $formula);
            
            if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/i', $formula_without_strings, $matches)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Found function calls in formula: " . json_encode($matches[1])); }
                foreach ($matches[1] as $function_name) {
                    if (!in_array(strtolower($function_name), $allowed_functions)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Blocked function: " . $function_name); }
                        throw new Exception(sprintf(__('Function "%s" is not allowed.', 'bootflow-woocommerce-xml-csv-importer'), $function_name));
                    }
                }
            }

            // Prepare variables - include ALL product_data fields as variables
            // This allows formulas to access ANY XML field (e.g., $product_name, $brand, $category_id, etc.)
            // NOTE: Do NOT add 'value' to variables array - it's passed separately to avoid conflicts
            $variables = array();
            
            // Debug: Log available product_data fields
            $this->log_debug('PHP_FORMULA_VARIABLES: Available product_data keys', array('keys' => array_keys($product_data)));
            
            // Add all product_data fields as variables
            // Convert field names with special chars to valid PHP variable names
            foreach ($product_data as $key => $val) {
                // Convert dots and other special chars to underscores for valid variable names
                $var_name = str_replace(array('.', '-', ' '), '_', $key);
                $variables[$var_name] = $val;
                
                // Debug: Log each variable being added
                if (in_array($key, ['category', 'product_name', 'code', 'eans.ean', 'eans'])) {
                    $val_preview = is_array($val) ? json_encode($val) : (is_string($val) ? substr($val, 0, 100) : gettype($val));
                    $this->log_debug('PHP_FORMULA_VARIABLE_MAPPED', array('field' => $key, 'var_name' => $var_name, 'value' => $val_preview));
                }
            }
            
            // Ensure common fields exist even if not in product_data
            if (!isset($variables['name'])) $variables['name'] = '';
            if (!isset($variables['sku'])) $variables['sku'] = '';
            if (!isset($variables['price'])) $variables['price'] = 0;
            if (!isset($variables['category'])) $variables['category'] = '';
            if (!isset($variables['brand'])) $variables['brand'] = '';
            if (!isset($variables['weight'])) $variables['weight'] = 0;
            if (!isset($variables['ean'])) {
                $variables['ean'] = isset($variables['eans_ean']) ? $variables['eans_ean'] : '';
            }
            if (!isset($variables['gtin'])) $variables['gtin'] = '';

            
            // Execute in isolated scope with defined variables
            // Pass $value separately to avoid conflicts with product data
            $result = $this->safe_eval($formula, $variables, $value);
            
            
            return $result;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('PHP Formula Error: ' . $e->getMessage()); }
            return $value; // Return original value on error
        }
    }

    /**
     * Safe evaluation of PHP code.
     *
     * @since    1.0.0
     * @param    string $code PHP code
     * @param    array $variables Variables to inject
     * @return   mixed Result
     */
    private function safe_eval($code, $variables = array(), $value_param = null) {
        // Disable error reporting temporarily
        $old_error_reporting = error_reporting(0);
        
        try {
            // Use output buffering to capture any output
            ob_start();
            
            // IMPORTANT: Save the formula code before extract overwrites $code variable
            $formula_code = $code;
            
            // Set $value BEFORE extract so it's available but won't be overwritten
            $value = $value_param;
            
            // Extract ALL variables to local scope BEFORE eval
            // This is necessary for the use() clause in the closure to work
            // NOTE: This will overwrite $code if product has 'code' field
            extract($variables, EXTR_OVERWRITE);
            
            // Build use clause dynamically from all variable names PLUS $value
            // IMPORTANT: Filter out invalid PHP variable names (e.g., @attributes, #text)
            $var_names = array_filter(array_keys($variables), function($name) {
                // PHP variable names must start with letter or underscore, then letters/numbers/underscores
                return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name);
            });
            $var_names = array_merge(array('value'), $var_names);
            $use_clause = empty($var_names) ? '' : 'use ($' . implode(', $', $var_names) . ')';
            
            // Smart formula normalization - fix common user mistakes
            $formula_code = $this->normalize_php_formula($formula_code, $value);
            
            // Create isolated function using anonymous function with dynamic variables
            // Execute as IIFE (Immediately Invoked Function Expression)
            $eval_code = '(function() ' . $use_clause . ' { ' . $formula_code . ' })()';
            
            
            // Execute eval with return statement
            try {
                $result = $value_param; // eval disabled in FREE version
            } catch (\Throwable $eval_error) {
                throw $eval_error;
            }
            
            // Clean output buffer
            ob_end_clean();
            
            // Restore error reporting
            error_reporting($old_error_reporting);
            
            return $result;
            
        } catch (ParseError $e) {
            ob_end_clean();
            error_reporting($old_error_reporting);
            throw new Exception(__('PHP formula syntax error: ', 'bootflow-woocommerce-xml-csv-importer') . $e->getMessage());
        } catch (Error $e) {
            ob_end_clean();
            error_reporting($old_error_reporting);
            throw new Exception(__('PHP formula execution error: ', 'bootflow-woocommerce-xml-csv-importer') . $e->getMessage());
        }
    }

    /**
    /**
     * Normalize PHP formula to fix common user mistakes.
     * Makes formulas more forgiving and user-friendly.
     *
     * @since    1.0.0
     * @param    string $formula Raw formula from user
     * @param    mixed  $default_value Default value to return if no else branch
     * @return   string Normalized formula ready for execution
     */
    private function normalize_php_formula($formula, $default_value = null) {
        $formula = trim($formula);
        
        // Handle simple expressions without any control structures
        $has_control = preg_match('/\b(if|else|elseif|switch|for|foreach|while|do)\b/i', $formula);
        
        if (!$has_control) {
            // Simple expression - just add return (if not already there)
            $formula = rtrim($formula, ';');
            // Check if formula already starts with 'return'
            if (preg_match('/^\s*return\b/i', $formula)) {
                return $formula . ';';
            }
            return 'return ' . $formula . ';';
        }
        
        // For complex formulas with control structures, keep the original formatting
        // Only do minimal normalization to preserve multi-line code blocks
        
        // If formula already ends with return statement, use as-is
        if (preg_match('/return\s+[^;]+;\s*$/i', $formula)) {
            return $formula;
        }
        
        // If formula has else block covering all cases, use as-is
        if (stripos($formula, 'else {') !== false || stripos($formula, 'else{') !== false) {
            return $formula;
        }
        
        // Pattern: condition ? true : false (ternary without return)
        if (preg_match('/^\$?\w+.*\?.*:.*$/i', $formula) && stripos($formula, 'return') === false) {
            $formula = rtrim($formula, ';');
            return 'return ' . $formula . ';';
        }
        
        // For simple single-line if without braces, normalize
        $single_line = preg_replace('/\s+/', ' ', $formula);
        if (preg_match('/^if\s*\((.+?)\)\s*return\s+(.+?)(?:;?\s*)?$/i', $single_line, $matches)) {
            $condition = trim($matches[1]);
            $return_value = rtrim(trim($matches[2]), ';');
            return "if ({$condition}) { return {$return_value}; } return \$value;";
        }
        
        return $formula;
    }

    /**
     * Build product context for AI processing.
     *
     * @since    1.0.0
     * @param    array $product_data Product data
     * @return   array Context array
     */
    private function build_product_context($product_data) {
        $context = array();
        
        if (isset($product_data['name']) && !empty($product_data['name'])) {
            $context['name'] = $product_data['name'];
        }
        
        if (isset($product_data['category']) && !empty($product_data['category'])) {
            $context['category'] = $product_data['category'];
        }
        
        if (isset($product_data['brand']) && !empty($product_data['brand'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('PROCESSOR: Brand value being added to context: ' . print_r($product_data['brand'], true)); }
            $context['brand'] = $product_data['brand'];
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('PROCESSOR: No brand in product_data or empty'); }
        }
        
        if (isset($product_data['price']) && !empty($product_data['price'])) {
            $context['price'] = $product_data['price'];
        }
        
        // Add regular_price as fallback
        if (isset($product_data['regular_price']) && !empty($product_data['regular_price'])) {
            $context['price'] = $product_data['regular_price'];
        }
        
        // Add EAN/GTIN
        if (isset($product_data['ean']) && !empty($product_data['ean'])) {
            $context['ean'] = $product_data['ean'];
        }
        
        if (isset($product_data['gtin']) && !empty($product_data['gtin'])) {
            $context['gtin'] = $product_data['gtin'];
        }
        
        return $context;
    }

    /**
     * Validate and sanitize field value based on WooCommerce field type.
     *
     * @since    1.0.0
     * @param    mixed $value Field value
     * @param    string $field_type WooCommerce field type
     * @return   mixed Sanitized value
     */
    public function validate_field_value($value, $field_type) {
        switch ($field_type) {
            case 'sku':
                // SKU should be alphanumeric with limited special characters
                return preg_replace('/[^a-zA-Z0-9\-_.]/', '', (string)$value);
                
            case 'price':
            case 'regular_price':
            case 'sale_price':
                // Ensure numeric price
                $price = preg_replace('/[^0-9.,]/', '', (string)$value);
                $price = str_replace(',', '.', $price);
                return is_numeric($price) ? floatval($price) : 0;
                
            case 'stock_quantity':
                // Integer stock quantity
                return max(0, intval($value));
                
            case 'weight':
            case 'length':
            case 'width':
            case 'height':
                // Numeric dimensions
                $dimension = preg_replace('/[^0-9.,]/', '', (string)$value);
                $dimension = str_replace(',', '.', $dimension);
                return is_numeric($dimension) ? floatval($dimension) : 0;
                
            case 'name':
            case 'description':
            case 'short_description':
                // Text fields
                return wp_kses_post((string)$value);
                
            case 'categories':
            case 'tags':
                // Categories/tags - handle arrays or comma-separated strings
                if (is_array($value)) {
                    return array_map('trim', $value);
                }
                return array_map('trim', explode(',', (string)$value));
                
            case 'images':
            case 'featured_image':
            case 'gallery_images':
                // Image URLs - handle arrays or comma-separated strings
                if (is_array($value)) {
                    return array_filter(array_map('esc_url_raw', $value));
                }
                return array_filter(array_map('esc_url_raw', explode(',', (string)$value)));
                
            case 'status':
                // Product status
                $valid_statuses = array('publish', 'draft', 'private');
                return in_array($value, $valid_statuses) ? $value : 'publish';
                
            case 'stock_status':
                // Stock status
                $valid_statuses = array('instock', 'outofstock', 'onbackorder');
                return in_array($value, $valid_statuses) ? $value : 'instock';
                
            case 'tax_status':
                // Tax status
                $valid_statuses = array('taxable', 'shipping', 'none');
                return in_array($value, $valid_statuses) ? $value : 'taxable';
                
            case 'manage_stock':
                // Boolean for stock management
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                
            default:
                // Default sanitization for other fields
                return is_string($value) ? sanitize_text_field($value) : $value;
        }
    }

    /**
     * Process multiple fields in batch.
     *
     * @since    1.0.0
     * @param    array $field_values Array of field values
     * @param    array $field_configs Array of field configurations
     * @param    array $product_data Product context
     * @return   array Processed field values
     */
    public function process_fields_batch($field_values, $field_configs, $product_data = array()) {
        $processed_values = array();
        
        foreach ($field_values as $field_key => $value) {
            if (isset($field_configs[$field_key])) {
                $processed_values[$field_key] = $this->process_field($value, $field_configs[$field_key], $product_data);
                
                // Apply field-specific validation
                $processed_values[$field_key] = $this->validate_field_value($processed_values[$field_key], $field_key);
            } else {
                $processed_values[$field_key] = $this->process_direct($value);
            }
        }
        
        return $processed_values;
    }

    /**
     * Enhanced debug logging with timestamp and context.
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_debug($message, $context = array()) {
        // Generate timestamp with milliseconds using WordPress timezone
        $timestamp = current_time('Y-m-d H:i:s') . '.' . sprintf('%03d', floor(microtime(true) * 1000) % 1000);
        
        // Format context as JSON if not empty
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        // Log to file in uploads directory
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import_logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        $log_file = $log_dir . '/import_debug.log';
        $log_entry = "[{$timestamp}] {$message}{$context_str}\n";
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log($log_entry, 3, $log_file); }
    }
}