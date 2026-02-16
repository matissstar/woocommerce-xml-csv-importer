<?php
/**
 * Import Engine for processing imports.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import Engine class.
 */
class WC_XML_CSV_AI_Import_Importer {

    /**
     * Field processor instance
     *
     * @var WC_XML_CSV_AI_Import_Processor
     */
    private $processor;

    /**
     * XML parser instance
     *
     * @var WC_XML_CSV_AI_Import_XML_Parser
     */
    private $xml_parser;

    /**
     * CSV parser instance
     *
     * @var WC_XML_CSV_AI_Import_CSV_Parser
     */
    private $csv_parser;

    /**
     * Import configuration
     *
     * @var array
     */
    private $config;

    /**
     * Import ID
     *
     * @var int
     */
    private $import_id;

    /**
     * Current raw product data (for image placeholder parsing)
     *
     * @var array
     */
    private $current_raw_product_data = array();

    /**
     * Current row number for SKU generation
     *
     * @var int
     */
    private $current_row_number = 1;

    /**
     * Flag for CSV variable product mode
     *
     * @var bool
     */
    private $csv_variable_mode = false;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->processor = new WC_XML_CSV_AI_Import_Processor();
        $this->xml_parser = new WC_XML_CSV_AI_Import_XML_Parser();
        $this->csv_parser = new WC_XML_CSV_AI_Import_CSV_Parser();
    }

    /**
     * Start import process.
     *
     * @since    1.0.0
     * @param    array $import_data Import configuration
     * @return   int Import ID
     */
    public function start_import($import_data) {
        global $wpdb;

        try {
            // Validate import data
            $this->validate_import_data($import_data);
            
            // Check if this is updating an existing import or creating new
            $import_id = isset($import_data['import_id']) ? intval($import_data['import_id']) : 0;
            $is_update = ($import_id > 0);
            
            if ($is_update) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: UPDATING existing import ID: ' . $import_id); }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Creating NEW import record'); }
            }
            
            // Prepare import filters (PRO-only feature)
            $import_filters = array();
            
            // Only process filters if PRO license is active
            if (WC_XML_CSV_AI_Import_Features::is_available('import_filters')) {
                if (isset($import_data['import_filters']) && is_array($import_data['import_filters'])) {
                    foreach ($import_data['import_filters'] as $filter) {
                        if (!empty($filter['field']) && !empty($filter['operator'])) {
                            // Validate operator is in allowed list (including PRO regex operators)
                            $allowed_operators = array('=', '!=', '>', '<', '>=', '<=', 'contains', 'not_contains', 'empty', 'not_empty', 'regex_match', 'regex_not_match');
                            $operator = in_array($filter['operator'], $allowed_operators) ? $filter['operator'] : '=';
                            
                            $import_filters[] = array(
                                'field' => sanitize_text_field($filter['field']),
                                'operator' => $operator,  // Don't sanitize - use validated value
                                'value' => sanitize_text_field($filter['value'] ?? '')
                            );
                        }
                    }
                }
            }
            // FREE version: $import_filters stays empty, filters are ignored
            
            $import_record = array(
                'name' => sanitize_text_field($import_data['import_name']),
                'file_url' => sanitize_text_field($import_data['file_path']),
                'file_type' => sanitize_text_field($import_data['file_type'] ?? 'xml'),
                'product_wrapper' => sanitize_text_field($import_data['product_wrapper'] ?? 'product'),
                'field_mappings' => json_encode($import_data['field_mapping'] ?? array()),
                'processing_modes' => json_encode($import_data['processing_modes'] ?? array()),
                'processing_configs' => json_encode($import_data['processing_configs'] ?? array()),
                'ai_settings' => json_encode($import_data['ai_settings'] ?? array()),
                'custom_fields' => json_encode($import_data['custom_fields'] ?? array()),
                'schedule_type' => sanitize_text_field($import_data['schedule_type'] ?? 'disabled'),
                'update_existing' => isset($import_data['update_existing']) ? (int)$import_data['update_existing'] : 0,
                'import_filters' => json_encode($import_filters),
                'filter_logic' => sanitize_text_field($import_data['filter_logic'] ?? 'AND'),
                'draft_non_matching' => isset($import_data['draft_non_matching']) ? (int)$import_data['draft_non_matching'] : 0,
                'processed_products' => 0,
                'status' => 'processing'  // Set to processing so progress page kickstarts it
            );
            
            // Count total products - ALWAYS calculate (even for updates/re-runs)
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: About to count products'); }
            $total_products = 0;
            if ($import_data['file_type'] === 'xml') {
                // Load XML parser if not already loaded
                if (!class_exists('WC_XML_CSV_AI_Import_XML_Parser')) {
                    require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-xml-parser.php';
                }
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: XML Parser loaded'); }
                $this->xml_parser = new WC_XML_CSV_AI_Import_XML_Parser();
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Counting products in: ' . $import_data['file_path']); }
                $total_products = $this->xml_parser->count_products($import_data['file_path'], $import_data['product_wrapper']);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Total products from XML: ' . $total_products); }
            } else {
                // Load CSV parser if not already loaded  
                if (!class_exists('WC_XML_CSV_AI_Import_CSV_Parser')) {
                    require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-csv-parser.php';
                }
                $this->csv_parser = new WC_XML_CSV_AI_Import_CSV_Parser();
                $total_products = $this->csv_parser->count_products($import_data['file_path']) - 1; // Subtract header
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Total products from CSV: ' . $total_products); }
            }
            $import_record['total_products'] = $total_products;
            
            // Note: No product count limits in any tier (removed in v1.0.0)
            // Both FREE and PRO have unlimited products
            
            if ($is_update) {
                // UPDATE existing import
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Updating import record ID: ' . $import_id); }
                $result = $wpdb->update(
                    $wpdb->prefix . 'wc_itp_imports',
                    $import_record,
                    array('id' => $import_id),
                    null,
                    array('%d')
                );
                
                if ($result === false) {
                    $error = $wpdb->last_error;
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Database update error: ' . $error); }
                    throw new Exception(__('Failed to update import record: ', 'bootflow-woocommerce-xml-csv-importer') . $error);
                }
                
                $this->import_id = $import_id;
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Import updated successfully, ID: ' . $this->import_id); }
            } else {
                // INSERT new import
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Saving import record with ' . $import_record['total_products'] . ' products'); }
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: About to INSERT into database'); }
                $result = $wpdb->insert(
                    $wpdb->prefix . 'wc_itp_imports',
                    $import_record
                );
                
                if ($result === false) {
                    $error = $wpdb->last_error;
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Database insert error: ' . $error); }
                    throw new Exception(__('Failed to create import record: ', 'bootflow-woocommerce-xml-csv-importer') . $error);
                }

                $this->import_id = $wpdb->insert_id;
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Import created successfully, ID: ' . $this->import_id); }
            }
            
            // Load config from database to ensure proper format
            $this->load_import_config($this->import_id);

            // Log import start
            $this->log('info', sprintf(__('Import "%s" started with %d products.', 'bootflow-woocommerce-xml-csv-importer'), $import_record['name'], $total_products));

            // Return import ID - processing will be kickstarted by progress page
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ START_IMPORT: Returning import ID ' . $this->import_id . ' for kickstart by progress page'); }
            return $this->import_id;

        } catch (Exception $e) {
            if (isset($this->import_id)) {
                $this->log('error', $e->getMessage());
                $this->update_import_status('failed');
            }
            throw $e;
        }
    }

    /**
     * Load import configuration from database.
     *
     * @since    1.0.0
     * @param    int $import_id Import ID
     */
    private function load_import_config($import_id) {
        global $wpdb;

        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: Loading import_id=' . $import_id); }
        $import = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
            $import_id
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: Import found=' . ($import ? 'YES' : 'NO')); }
        if (!$import) {
            throw new Exception(__('Import not found.', 'bootflow-woocommerce-xml-csv-importer'));
        }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: file_path=' . ($import->file_path ?? 'NULL')); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: file_url=' . ($import->file_url ?? 'NULL')); }

        $this->import_id = $import_id;
        $this->config = array(
            'file_path' => $import->file_path ?: $import->file_url,  // Fallback to file_url if file_path is NULL
            'file_type' => $import->file_type ?? 'xml',
            'product_wrapper' => $import->product_wrapper,
            'field_mapping' => json_decode($import->field_mappings, true) ?: array(),
            'processing_modes' => json_decode($import->processing_modes ?? '[]', true) ?: array(),
            'processing_configs' => json_decode($import->processing_configs ?? '[]', true) ?: array(),
            'custom_fields' => json_decode($import->custom_fields ?? '[]', true) ?: array(),
            'import_filters' => json_decode($import->import_filters ?? '[]', true) ?: array(),
            'filter_logic' => $import->filter_logic ?? 'AND',
            'draft_non_matching' => isset($import->draft_non_matching) ? (int)$import->draft_non_matching : 0,
            'schedule_type' => $import->schedule_type,
            'update_existing' => $import->update_existing ?? '0',
            'handle_missing' => isset($import->handle_missing) ? (int)$import->handle_missing : 0,
            'missing_action' => $import->missing_action ?? 'draft',
            'delete_variations' => isset($import->delete_variations) ? (int)$import->delete_variations : 1
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: Config set, file_path=' . ($this->config['file_path'] ?? 'NULL')); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: product_wrapper=' . ($this->config['product_wrapper'] ?? 'NULL')); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: field_mapping count=' . count($this->config['field_mapping'])); }
        if (isset($this->config['field_mapping']['name'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: name field mapping=' . json_encode($this->config['field_mapping']['name'])); }
        }
        // Debug sale_price mapping specifically
        if (isset($this->config['field_mapping']['sale_price'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: sale_price mapping=' . json_encode($this->config['field_mapping']['sale_price'])); }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: sale_price mapping NOT FOUND!'); }
        }
        // Debug loaded config
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: Loaded config with ' . count($this->config['import_filters']) . ' filters'); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: Filter logic: ' . $this->config['filter_logic']); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ LOAD_CONFIG: Draft non-matching: ' . $this->config['draft_non_matching']); }
    }

    /**
     * Process import chunk.
     *
     * @since    1.0.0
     * @param    int $offset Starting position
     * @param    int $limit Number of products to process
     * @param    int $import_id Import ID (optional, for cron jobs)
     * @return   array Processing result
     */
    public function process_import_chunk($offset, $limit, $import_id = null) {
        // Ensure offset and limit are integers
        $offset = intval($offset);
        $limit = intval($limit);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Started with offset=' . $offset . ', limit=' . $limit . ', import_id=' . ($import_id ?? 'NULL')); }
        global $wpdb;

        try {
            // Load config if import_id provided (for cron jobs)
            if ($import_id && empty($this->config)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Loading config for import_id=' . $import_id); }
                $this->load_import_config($import_id);
            }
            
            // CRITICAL: Use transient lock to prevent parallel execution
            $lock_key = 'wc_import_lock_' . $this->import_id;
            $lock_time_key = 'wc_import_lock_time_' . $this->import_id;
            $lock_value = get_transient($lock_key);
            $lock_time = get_transient($lock_time_key);
            
            if ($lock_value !== false) {
                // Check if lock is stale (older than 3 minutes = likely timed out process)
                $lock_age = $lock_time ? (time() - intval($lock_time)) : 999;
                if ($lock_age < 180) {
                    // Another process is still working on this import (lock is fresh)
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: LOCKED - another process is running (lock=' . $lock_value . ', age=' . $lock_age . 's)'); }
                    return array(
                        'processed' => 0,
                        'errors' => array(),
                        'total_processed' => 0,
                        'total_products' => 0,
                        'completed' => false,
                        'locked' => true
                    );
                } else {
                    // Lock is stale - likely previous process timed out
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: STALE LOCK detected (age=' . $lock_age . 's) - clearing it'); }
                    delete_transient($lock_key);
                    delete_transient($lock_time_key);
                }
            }
            
            // Set lock with unique value and 5 minute expiry + timestamp
            $my_lock = uniqid('lock_', true);
            set_transient($lock_key, $my_lock, 300);
            set_transient($lock_time_key, time(), 300);
            
            // Verify we got the lock (race condition check)
            if (get_transient($lock_key) !== $my_lock) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Failed to acquire lock (race condition)'); }
                return array(
                    'processed' => 0,
                    'errors' => array(),
                    'total_processed' => 0,
                    'total_products' => 0,
                    'completed' => false,
                    'locked' => true
                );
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Lock acquired: ' . $my_lock); }
            
            // CRITICAL: Check if this chunk was already processed (prevent double processing)
            $current_import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                $this->import_id
            ));
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Current import status=' . ($current_import->status ?? 'NULL') . ', processed=' . ($current_import->processed_products ?? 'NULL')); }
            
            if ($current_import) {
                // If import already completed, skip
                if ($current_import->status === 'completed') {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: SKIPPING - import already completed'); }
                    delete_transient($lock_key); // Release lock
                    delete_transient($lock_time_key);
                    return array(
                        'processed' => 0,
                        'errors' => array(),
                        'total_processed' => $current_import->processed_products,
                        'total_products' => $current_import->total_products,
                        'completed' => true,
                        'skipped' => true
                    );
                }
                
                // If import is stopped (failed) or paused, abort
                if ($current_import->status === 'failed' || $current_import->status === 'paused') {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: ABORTING - import status is ' . $current_import->status); }
                    delete_transient($lock_key); // Release lock
                    delete_transient($lock_time_key);
                    return array(
                        'processed' => 0,
                        'errors' => array(),
                        'total_processed' => $current_import->processed_products,
                        'total_products' => $current_import->total_products,
                        'completed' => false,
                        'stopped' => ($current_import->status === 'failed'),
                        'paused' => ($current_import->status === 'paused')
                    );
                }
                
                // If offset is 0 but already processed some products, skip (prevent restart from beginning)
                $already_processed = intval($current_import->processed_products);
                if ($offset == 0 && $already_processed > 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: SKIPPING offset=0 - already processed ' . $already_processed . ' products'); }
                    delete_transient($lock_key); // Release lock
                    delete_transient($lock_time_key);
                    return array(
                        'processed' => 0,
                        'errors' => array(),
                        'total_processed' => $already_processed,
                        'total_products' => $current_import->total_products,
                        'completed' => $already_processed >= intval($current_import->total_products),
                        'skipped' => true
                    );
                }
                
                // CRITICAL FIX: Skip if this offset was already processed
                // This prevents double-processing when AJAX and Cron both try to process same offset
                // The correct check is: if we've already processed past this offset, skip it
                if ($offset > 0 && $already_processed > $offset) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: SKIPPING - offset ' . $offset . ' already processed (have ' . $already_processed . ')'); }
                    delete_transient($lock_key); // Release lock
                    delete_transient($lock_time_key);
                    return array(
                        'processed' => 0,
                        'errors' => array(),
                        'total_processed' => $already_processed,
                        'total_products' => $current_import->total_products,
                        'completed' => $already_processed >= intval($current_import->total_products),
                        'skipped' => true
                    );
                }
            }
            
            // CRITICAL FIX: Save import start time when first chunk begins
            // This is used by handle_missing_products() to correctly identify products not updated in THIS run
            $import_start_option_key = 'wc_import_start_time_' . $this->import_id;
            if ($offset == 0 && intval($current_import->processed_products ?? 0) == 0) {
                update_option($import_start_option_key, current_time('mysql'), false);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Saved import start time: ' . current_time('mysql')); }
            }
            
            $this->update_import_status('processing');

            // Extract products from file - determine type by file_type config
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: About to extract products, file_type=' . ($this->config['file_type'] ?? 'unknown')); }
            if (isset($this->config['file_type']) && $this->config['file_type'] === 'csv') {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Using CSV parser'); }
                
                // Check if CSV has variable products (grouped by parent SKU)
                $attributes_config = $this->config['field_mapping']['attributes_variations'] ?? array();
                $product_mode = $attributes_config['product_mode'] ?? 'simple';
                $csv_var_config = $attributes_config['csv_variation_config'] ?? array();
                
                if ($product_mode === 'variable' && !empty($csv_var_config['parent_sku_column'])) {
                    // CSV Variable Products Mode - extract grouped by parent
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: CSV VARIABLE MODE - grouping by parent SKU'); }
                    $grouped_result = $this->csv_parser->extract_products_grouped(
                        $this->config['file_path'],
                        $csv_var_config['parent_sku_column'],
                        $csv_var_config['type_column'] ?? '',
                        $offset,
                        $limit
                    );
                    $products = $grouped_result['products'] ?? array();
                    
                    // Update total_products to reflect grouped count (not row count)
                    $grouped_total = $grouped_result['total'] ?? 0;
                    if ($grouped_total > 0 && $offset == 0) {
                        // Only update on first chunk to avoid race conditions
                        $wpdb->update(
                            $wpdb->prefix . 'wc_itp_imports',
                            array('total_products' => $grouped_total),
                            array('id' => $this->import_id)
                        );
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Updated total_products to ' . $grouped_total . ' (grouped count)'); }
                    }
                    
                    // Mark that we're in CSV variable mode for import_single_product
                    $this->csv_variable_mode = true;
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Extracted ' . count($products) . ' grouped products (parents with variations)'); }
                } else {
                    // Standard CSV mode - each row is a product
                    $this->csv_variable_mode = false;
                    $products = $this->csv_parser->extract_products(
                        $this->config['file_path'],
                        $offset,
                        $limit
                    );
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Using XML parser'); }
                $this->csv_variable_mode = false;
                $products = $this->xml_parser->extract_products(
                    $this->config['file_path'],
                    $this->config['product_wrapper'],
                    $offset,
                    $limit
                );
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Extracted ' . count($products) . ' products'); }

            $processed_count = 0;
            $errors = array();
            $lock_key = 'wc_import_lock_' . $this->import_id;

            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Starting product loop'); }
            
            // Note: No product count limits in any tier (removed in v1.0.0)
            // Both FREE and PRO have unlimited products
            
            foreach ($products as $index => $product_data) {
                // Check if import was stopped/paused between products
                $current_status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                    $this->import_id
                ));
                
                if ($current_status === 'failed' || $current_status === 'paused') {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Import ' . $current_status . ' - stopping mid-batch at product ' . ($index + 1)); }
                    delete_transient($lock_key);
                    delete_transient($lock_time_key);
                    
                    // Update processed count before stopping
                    if ($processed_count > 0) {
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}wc_itp_imports SET processed_products = processed_products + %d WHERE id = %d",
                            $processed_count,
                            $this->import_id
                        ));
                    }
                    
                    return array(
                        'processed' => $processed_count,
                        'errors' => $errors,
                        'total_processed' => 0,
                        'total_products' => 0,
                        'completed' => false,
                        'stopped' => ($current_status === 'failed'),
                        'paused' => ($current_status === 'paused')
                    );
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Processing product ' . ($index + 1)); }
                try {
                    // Set current row number for SKU generation
                    $this->current_row_number = $offset + $index + 1;
                    $this->import_single_product($product_data);
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Product ' . ($index + 1) . ' imported successfully'); }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Product ' . ($index + 1) . ' failed: ' . $e->getMessage()); }
                    $errors[] = $e->getMessage();
                    $this->log('error', $e->getMessage(), isset($product_data['sku']) ? $product_data['sku'] : '');
                }
                // Always increment processed count (both success and skip/error)
                $processed_count++;
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Loop finished, processed=' . $processed_count); }

            // Update processed count using atomic SQL (prevents race conditions)
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}wc_itp_imports SET processed_products = processed_products + %d, last_run = %s WHERE id = %d",
                $processed_count,
                current_time('mysql'),
                $this->import_id
            ));

            // Check if import is complete or stopped
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                $this->import_id
            ));

            // Release lock
            $lock_key = 'wc_import_lock_' . $this->import_id;
            $lock_time_key = 'wc_import_lock_time_' . $this->import_id;
            delete_transient($lock_key);
            delete_transient($lock_time_key);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Lock released'); }

            // Check if stopped or paused - don't schedule next chunk
            if ($import->status === 'failed' || $import->status === 'paused') {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Import ' . $import->status . ' - NOT scheduling next chunk'); }
                return array(
                    'processed' => $processed_count,
                    'errors' => $errors,
                    'total_processed' => $import->processed_products,
                    'total_products' => $import->total_products,
                    'completed' => false,
                    'stopped' => ($import->status === 'failed'),
                    'paused' => ($import->status === 'paused')
                );
            }

            if ($import->processed_products >= $import->total_products) {
                // Safety check: don't mark as completed if total_products is NULL or 0 (invalid)
                if (empty($import->total_products) || intval($import->total_products) <= 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: WARNING - total_products is NULL or 0, cannot determine completion'); }
                    // Don't mark as completed, just return current state
                    return array(
                        'processed' => $processed_count,
                        'errors' => array('total_products is not set - import configuration error'),
                        'total_processed' => $import->processed_products,
                        'total_products' => 0,
                        'completed' => false
                    );
                }
                
                // Handle missing products before marking as completed
                if (!empty($this->config['handle_missing'])) {
                    $this->handle_missing_products();
                }
                
                // Phase 2: Resolve product relationships (grouped children, upsells, cross-sells)
                $this->resolve_product_relationships();
                
                // Phase 3: Final stock status sync for all variable products
                // This must happen LAST to avoid any WooCommerce hooks overriding our stock status
                // Schedule it on shutdown to ensure all WooCommerce hooks have completed
                add_action('shutdown', array($this, 'sync_variable_products_stock_status'), 999);
                
                $this->update_import_status('completed');
                $this->log('info', sprintf(__('Import completed successfully. Processed %d products.', 'bootflow-woocommerce-xml-csv-importer'), $import->processed_products));
            } else {
                // Schedule next chunk automatically to prevent hanging
                // This ensures continuous processing even if AJAX times out
                // Use processed_products from DB as next offset (more accurate than offset + limit)
                $next_offset = intval($import->processed_products);
                $this->schedule_next_chunk($next_offset, $limit);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Scheduled next chunk at offset=' . $next_offset); }
            }

            return array(
                'processed' => $processed_count,
                'errors' => $errors,
                'total_processed' => $import->processed_products,
                'total_products' => $import->total_products,
                'completed' => $import->processed_products >= $import->total_products
            );

        } catch (Exception $e) {
            // Release lock on error
            $lock_key = 'wc_import_lock_' . $this->import_id;
            $lock_time_key = 'wc_import_lock_time_' . $this->import_id;
            delete_transient($lock_key);
            delete_transient($lock_time_key);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Lock released (Exception)'); }
            
            $this->log('error', $e->getMessage());
            $this->update_import_status('failed');
            throw $e;
        } catch (Throwable $e) {
            // Release lock on any error (including TypeError, Error, etc.)
            $lock_key = 'wc_import_lock_' . $this->import_id;
            $lock_time_key = 'wc_import_lock_time_' . $this->import_id;
            delete_transient($lock_key);
            delete_transient($lock_time_key);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_CHUNK: Lock released (Throwable: ' . get_class($e) . ')'); }
            
            $this->log('error', $e->getMessage());
            $this->update_import_status('failed');
            throw $e;
        }
    }

    /**
     * Import single product.
     *
     * @since    1.0.0
     * @param    array $product_data Raw product data
     * @return   int Product ID
     */
    private function import_single_product($product_data) {
        try {
            $log_file = '/var/www/html/mobishop.lv/wp-content/image_import.log';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n=== IMPORT_SINGLE_PRODUCT CALLED ===\n", FILE_APPEND); }
            
            // Check if this is a grouped CSV product (parent + variations)
            if (!empty($this->csv_variable_mode) && isset($product_data['type']) && $product_data['type'] === 'variable') {
                return $this->import_csv_variable_product($product_data);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Product SKU: " . (($product_data['data']['sku'] ?? $product_data['sku']) ?? 'NO SKU') . "\n", FILE_APPEND); }
            
            // For grouped products, extract the actual data
            if (isset($product_data['data']) && is_array($product_data['data'])) {
                $product_data = $product_data['data'];
            }
            
            // Store raw product data for image placeholder parsing
            $this->current_raw_product_data = $product_data;
            
            // DEBUG: Log raw product data keys
            $image_keys = array_filter(array_keys($product_data), function($key) {
                return strpos($key, 'image') !== false;
            });
            $this->log('debug', 'Raw product data image keys: ' . implode(', ', $image_keys));
            
            // Check import filters BEFORE processing
            $passes_filters = $this->passes_import_filters($product_data);
            $force_status = null;
            
            if (!$passes_filters) {
                $sku = $product_data['sku'] ?? $product_data['id'] ?? 'Unknown';
                
                // If draft_non_matching is enabled, import as Draft instead of skipping
                if (!empty($this->config['draft_non_matching'])) {
                    $force_status = 'draft';
                    $this->log('info', sprintf(__('Product does not match filters - will be imported as Draft (SKU: %s)', 'bootflow-woocommerce-xml-csv-importer'), $sku));
                } else {
                    // Skip product if checkbox not enabled
                    $this->log('info', sprintf(__('Product skipped by import filters (SKU: %s)', 'bootflow-woocommerce-xml-csv-importer'), $sku));
                    return;
                }
            } else {
                // Product passes filters
                if (!empty($this->config['draft_non_matching'])) {
                    // When draft_non_matching is enabled, products that pass filters should be published
                    $force_status = 'publish';
                    $sku = $product_data['sku'] ?? $product_data['id'] ?? 'Unknown';
                    $this->log('info', sprintf(__('Product matches filters - will be imported as Published (SKU: %s)', 'bootflow-woocommerce-xml-csv-importer'), $sku));
                }
            }
            
            // Map raw data to WooCommerce fields
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[IMPORT_PROCESS] About to map product fields'); }
            $mapped_data = $this->map_product_fields($product_data);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[IMPORT_PROCESS] Mapped data keys: ' . json_encode(array_keys($mapped_data))); }

            // Check if product already exists BEFORE processing fields
            // This allows us to skip expensive AI/formula processing for fields that won't be updated
            $existing_product_id = null;
            if (!empty($mapped_data['sku'])) {
                $existing_product_id = wc_get_product_id_by_sku($mapped_data['sku']);
            }
            $is_existing_product = !empty($existing_product_id);

            // Process fields through configured processors
            // Pass existing_product_id to skip expensive processing for fields with update_on_sync=0
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[IMPORT_PROCESS] About to process product fields (existing: ' . ($is_existing_product ? 'YES' : 'NO') . ')'); }
            $processed_data = $this->process_product_fields($mapped_data, $product_data, $existing_product_id);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[IMPORT_PROCESS] Processed data keys: ' . json_encode(array_keys($processed_data))); }
            
            // Apply Pricing Engine rules to calculate final price
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[IMPORT_PROCESS] About to apply Pricing Engine'); }
            $processed_data = $this->apply_pricing_engine($processed_data, $product_data);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[IMPORT_PROCESS] After Pricing Engine - regular_price: ' . ($processed_data['regular_price'] ?? 'NOT SET')); }
            
            // Force status based on filter result (when draft_non_matching is enabled)
            if ($force_status !== null) {
                $processed_data['status'] = $force_status;
            }
            
            // DEBUG: Log update detection
            $update_debug_file = WP_CONTENT_DIR . '/update_debug.log';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "\n" . date('Y-m-d H:i:s') . " === IMPORT PRODUCT ===\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "SKU: " . ($processed_data['sku'] ?? 'EMPTY') . "\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Existing Product ID: " . ($existing_product_id ?: 'NOT FOUND') . "\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Stock Status in processed_data: " . ($processed_data['stock_status'] ?? 'NOT SET') . "\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Manage Stock in processed_data: " . ($processed_data['manage_stock'] ?? 'NOT SET') . "\n", FILE_APPEND); }

            // Validate required fields (name only required for new products)
            $this->validate_product_data($processed_data, $existing_product_id);

            // Get update_existing setting from import record
            global $wpdb;
            $import_record = $wpdb->get_row($wpdb->prepare("SELECT update_existing, skip_unchanged FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $this->import_id), ARRAY_A);
            $update_existing = ($import_record && $import_record['update_existing'] === '1') ? true : false;
            $skip_unchanged = ($import_record && $import_record['skip_unchanged'] === '1') ? true : false;
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Update Existing Flag: " . ($update_existing ? 'YES' : 'NO') . "\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Skip Unchanged Flag: " . ($skip_unchanged ? 'YES' : 'NO') . "\n", FILE_APPEND); }

            if ($existing_product_id && !$update_existing) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "ACTION: SKIP - Product exists but update disabled\n", FILE_APPEND); }
                throw new Exception(sprintf(__('Product with SKU "%s" already exists and update is disabled.', 'bootflow-woocommerce-xml-csv-importer'), $processed_data['sku']));
            }

            // Create or update product
            if ($existing_product_id && $update_existing) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "ACTION: UPDATE EXISTING PRODUCT\n", FILE_APPEND); }
                // Check if we should skip unchanged products
                if ($skip_unchanged && !$this->has_product_data_changed($existing_product_id, $processed_data)) {
                    $product_name = !empty($processed_data['name']) ? $processed_data['name'] : 'Product';
                    $this->log('info', sprintf(__('Skipped product (unchanged): %s (ID: %d)', 'bootflow-woocommerce-xml-csv-importer'), $product_name, $existing_product_id), $processed_data['sku']);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "ACTION: SKIPPED - Product unchanged\n", FILE_APPEND); }
                    return $existing_product_id; // Return existing ID without updating
                }
                
                $product_id = $this->update_product($existing_product_id, $processed_data, $mapped_data);
                $product_name = !empty($processed_data['name']) ? $processed_data['name'] : 'Product';
                $this->log('info', sprintf(__('Updated product: %s (ID: %d)', 'bootflow-woocommerce-xml-csv-importer'), $product_name, $product_id), $processed_data['sku']);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "RESULT: Updated product ID $product_id\n", FILE_APPEND); }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "ACTION: CREATE NEW PRODUCT\n", FILE_APPEND); }
                $product_id = $this->create_product($processed_data, $mapped_data);
                $product_name = !empty($processed_data['name']) ? $processed_data['name'] : 'Product';
                $this->log('info', sprintf(__('Created product: %s (ID: %d)', 'bootflow-woocommerce-xml-csv-importer'), $product_name, $product_id), $processed_data['sku']);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "RESULT: Created product ID $product_id\n", FILE_APPEND); }
            }

            // Process custom fields
            $this->process_custom_fields($product_id, $product_data);

            // Process attributes & variations
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ ABOUT TO CALL process_product_attributes for product: ' . $product_id); }
            
            // DEBUG: Log if product_data has variations
            $attr_debug_file = WP_CONTENT_DIR . '/product_data_debug.log';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $product_name = $processed_data['name'] ?? 'Unknown';
                file_put_contents($attr_debug_file, "\n" . date('Y-m-d H:i:s') . " === PRODUCT: $product_name (ID: $product_id) ===\n", FILE_APPEND);
                file_put_contents($attr_debug_file, "product_data TOP LEVEL KEYS: " . implode(', ', array_keys($product_data)) . "\n", FILE_APPEND);
                
                if (isset($product_data['variations'])) {
                    file_put_contents($attr_debug_file, "✓ Has 'variations' key - type: " . gettype($product_data['variations']) . "\n", FILE_APPEND);
                    if (is_array($product_data['variations'])) {
                        file_put_contents($attr_debug_file, "  variations keys: " . implode(', ', array_keys($product_data['variations'])) . "\n", FILE_APPEND);
                        if (isset($product_data['variations']['variation'])) {
                            file_put_contents($attr_debug_file, "  ✓ Has 'variations.variation' key\n", FILE_APPEND);
                            if (is_array($product_data['variations']['variation'])) {
                                $varCount = isset($product_data['variations']['variation'][0]) ? count($product_data['variations']['variation']) : 1;
                                file_put_contents($attr_debug_file, "  Variation count: $varCount\n", FILE_APPEND);
                            }
                        } else {
                            file_put_contents($attr_debug_file, "  ✗ Missing 'variation' key inside 'variations'\n", FILE_APPEND);
                        }
                    }
                } else {
                    file_put_contents($attr_debug_file, "✗ No 'variations' key in product_data\n", FILE_APPEND);
                }
            }
            
            $this->process_product_attributes($product_id, $product_data);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ FINISHED process_product_attributes'); }

            // CRITICAL SAFETY CHECK: Ensure variable products without variations are converted to simple
            // This catches cases where type=variable is set but no variations exist in source data
            // IMPORTANT: Clear cache first so we get fresh data after variations were created
            // Note: Do NOT use wc_delete_product_transients() here as it can trigger hooks that reset stock status
            wp_cache_delete($product_id, 'post_meta');
            clean_post_cache($product_id);
            $final_product = wc_get_product($product_id);
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log("★★★ SAFETY CHECK: Product $product_id, type=" . ($final_product ? $final_product->get_type() : 'null') . ", is_variable=" . ($final_product && $final_product->is_type('variable') ? 'YES' : 'NO')); }
            if ($final_product && $final_product->is_type('variable')) {
                // Use direct database query instead of get_children() which can be cached
                $variation_count = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
                    "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_parent = %d AND post_type = 'product_variation' AND post_status = 'publish'",
                    $product_id
                ));
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("★★★ SAFETY CHECK: Product $product_id has $variation_count variations (direct DB query)"); }
                if ($variation_count === 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ SAFETY CHECK: Product ' . $product_id . ' is variable with 0 variations - converting to simple'); }
                    wp_set_object_terms($product_id, 'simple', 'product_type');
                    // Ensure stock status is set correctly
                    $stock_status = get_post_meta($product_id, '_stock_status', true);
                    if (empty($stock_status) || $stock_status === 'outofstock') {
                        update_post_meta($product_id, '_stock_status', 'instock');
                    }
                    // Clear WooCommerce product cache to reflect the change
                    wc_delete_product_transients($product_id);
                } else {
                    // FINAL STOCK STATUS FIX: Variable product with variations - ensure stock status is synced
                    // WooCommerce sync can sometimes override our stock status, so we force it one final time
                    // Use direct DB query to avoid caching issues with get_posts()
                    global $wpdb;
                    $children = $wpdb->get_col($wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'",
                        $product_id
                    ));
                    $has_stock = false;
                    if (!empty($children)) {
                        // Check if any variation has instock status via direct DB query
                        $instock_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN (" . implode(',', array_map('intval', $children)) . ") AND meta_key = '_stock_status' AND meta_value = 'instock'"
                        ));
                        $has_stock = ($instock_count > 0);
                    }
                    $final_stock_status = $has_stock ? 'instock' : 'outofstock';
                    
                    // Use direct DB update to bypass any WooCommerce hooks
                    $wpdb->update(
                        $wpdb->postmeta,
                        array('meta_value' => $final_stock_status),
                        array('post_id' => $product_id, 'meta_key' => '_stock_status'),
                        array('%s'),
                        array('%d', '%s')
                    );
                    // Also update object cache
                    wp_cache_delete($product_id, 'post_meta');
                }
            }

            return $product_id;

        } catch (Exception $e) {
            throw new Exception(sprintf(__('Error importing product: %s', 'bootflow-woocommerce-xml-csv-importer'), $e->getMessage()));
        }
    }

    /**
     * Import CSV variable product (parent + variations from grouped data).
     *
     * @since    1.0.0
     * @param    array $grouped_data Grouped product data with 'data' (parent) and 'variations' array
     * @return   int Product ID
     */
    private function import_csv_variable_product($grouped_data) {
        $log_file = WP_CONTENT_DIR . '/csv_variable_import.log';
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n" . date('Y-m-d H:i:s') . " === CSV VARIABLE PRODUCT IMPORT ===\n", FILE_APPEND); }
        
        $parent_data = $grouped_data['data'] ?? array();
        $variations = $grouped_data['variations'] ?? array();
        $synthetic_parent = !empty($grouped_data['synthetic_parent']);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Parent data keys: " . implode(', ', array_keys($parent_data)) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Variations count: " . count($variations) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Synthetic parent: " . ($synthetic_parent ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        
        // Store raw product data
        $this->current_raw_product_data = $parent_data;
        
        // Get CSV variation config
        $attributes_config = $this->config['field_mapping']['attributes_variations'] ?? array();
        $csv_var_config = $attributes_config['csv_variation_config'] ?? array();
        $csv_attributes = $csv_var_config['attributes'] ?? array();
        $csv_fields = $csv_var_config['fields'] ?? array();
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "CSV Attributes config: " . json_encode($csv_attributes) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "CSV Fields config: " . json_encode($csv_fields) . "\n", FILE_APPEND); }
        
        // Map parent data to WooCommerce fields
        $mapped_data = $this->map_product_fields($parent_data);
        
        // Check if parent exists BEFORE processing (to skip AI for update_on_sync=0 fields)
        $existing_product_id = null;
        $parent_sku_column = $csv_var_config['parent_sku_column'] ?? 'Parent SKU';
        $check_sku = $mapped_data['sku'] ?? ($parent_data[$parent_sku_column] ?? null);
        if (!empty($check_sku)) {
            $existing_product_id = wc_get_product_id_by_sku($check_sku);
        }
        
        // Process fields - pass existing_product_id to skip AI for update_on_sync=0 fields
        $processed_data = $this->process_product_fields($mapped_data, $parent_data, $existing_product_id);
        
        // Apply Pricing Engine rules to calculate final price
        $processed_data = $this->apply_pricing_engine($processed_data, $parent_data);
        
        // For CSV variable products: if SKU is empty, use Parent SKU as the product SKU
        if (empty($processed_data['sku']) && !empty($parent_data[$parent_sku_column])) {
            $processed_data['sku'] = $parent_data[$parent_sku_column];
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Using Parent SKU as product SKU: " . $processed_data['sku'] . "\n", FILE_APPEND); }
        }
        
        // Get update settings
        global $wpdb;
        $import_record = $wpdb->get_row($wpdb->prepare("SELECT update_existing FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $this->import_id), ARRAY_A);
        $update_existing = ($import_record && $import_record['update_existing'] === '1') ? true : false;
        
        // IMPORTANT: Remove stock_status from processed_data for variable products
        // Variable products should NOT have their own stock_status - it's calculated from variations
        // If we set it here, create_product() will overwrite it and cause "out of stock" issue
        unset($processed_data['stock_status']);
        unset($processed_data['stock_quantity']);
        unset($processed_data['manage_stock']);
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Removed stock fields from parent - will be calculated from variations\n", FILE_APPEND); }
        
        // Create or update parent product
        if ($existing_product_id && $update_existing) {
            $product_id = $this->update_product($existing_product_id, $processed_data, $mapped_data);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Updated parent product ID: $product_id\n", FILE_APPEND); }
        } else if ($existing_product_id && !$update_existing) {
            throw new Exception(sprintf(__('Product with SKU "%s" already exists and update is disabled.', 'bootflow-woocommerce-xml-csv-importer'), $processed_data['sku']));
        } else {
            $product_id = $this->create_product($processed_data, $mapped_data);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Created parent product ID: $product_id\n", FILE_APPEND); }
        }
        
        // Convert to variable product type
        wp_set_object_terms($product_id, 'variable', 'product_type');
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Set product type to 'variable'\n", FILE_APPEND); }
        
        // Collect all attribute values from variations
        $all_attribute_values = array();
        foreach ($csv_attributes as $attr_config) {
            $attr_name = $attr_config['name'] ?? '';
            $source_column = $attr_config['source'] ?? '';
            if (empty($attr_name) || empty($source_column)) continue;
            
            $all_attribute_values[$attr_name] = array(
                'source' => $source_column,
                'values' => array()
            );
        }
        
        // Collect unique values from all variations
        foreach ($variations as $var_data) {
            foreach ($all_attribute_values as $attr_name => &$attr_info) {
                $value = $var_data[$attr_info['source']] ?? '';
                if (!empty($value) && !in_array($value, $attr_info['values'])) {
                    $attr_info['values'][] = $value;
                }
            }
            unset($attr_info); // Break reference to prevent PHP foreach reference bug
        }
        unset($attr_info); // Ensure reference is fully broken before next foreach
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Collected attribute values: " . json_encode($all_attribute_values) . "\n", FILE_APPEND); }
        
        // Create product attributes
        $product_attributes = array();
        $attr_position = 0;
        
        foreach ($all_attribute_values as $attr_name => $attr_info) {
            if (empty($attr_info['values'])) continue;
            
            // Sanitize attribute name for taxonomy
            $taxonomy_name = 'pa_' . sanitize_title($attr_name);
            
            // Register taxonomy
            $this->register_attribute_taxonomy($taxonomy_name, $attr_name);
            
            // Create terms
            $term_ids = array();
            foreach ($attr_info['values'] as $value) {
                $term_result = $this->get_or_create_attribute_term($value, $taxonomy_name);
                if ($term_result && isset($term_result->term_id)) {
                    $term_ids[] = $term_result->term_id;
                }
            }
            
            // Set terms to product
            wp_set_object_terms($product_id, $term_ids, $taxonomy_name);
            
            // Add to product attributes
            $product_attributes[$taxonomy_name] = array(
                'name' => $taxonomy_name,
                'value' => '',
                'position' => $attr_position++,
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Created attribute: $attr_name ($taxonomy_name) with " . count($term_ids) . " terms\n", FILE_APPEND); }
        }
        
        // Save attributes to parent product
        update_post_meta($product_id, '_product_attributes', $product_attributes);
        
        // Delete existing variations
        $existing_variations = get_posts(array(
            'post_type' => 'product_variation',
            'post_parent' => $product_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        foreach ($existing_variations as $var_id) {
            wp_delete_post($var_id, true);
        }
        
        // Create variations from CSV rows
        $variation_count = 0;
        foreach ($variations as $var_index => $var_data) {
            // Debug: Log variation data
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Variation #" . ($var_index + 1) . " data keys: " . implode(', ', array_keys($var_data)) . "\n", FILE_APPEND); }
            
            // Set variation attributes
            $var_attributes = array();
            foreach ($all_attribute_values as $attr_name => $attr_info) {
                $source_col = $attr_info['source'];
                $value = $var_data[$source_col] ?? '';
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Attr '$attr_name' source='$source_col' value='$value'\n", FILE_APPEND); }
                if (!empty($value)) {
                    $taxonomy_name = 'pa_' . sanitize_title($attr_name);
                    $var_attributes[$taxonomy_name] = sanitize_title($value);
                }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Final var_attributes: " . json_encode($var_attributes) . "\n", FILE_APPEND); }
            
            // Set variation fields from CSV
            $var_sku = $var_data[$csv_fields['sku'] ?? 'SKU'] ?? '';
            
            // Check if variation with this SKU already exists (for update scenario)
            $existing_variation_id = null;
            if (!empty($var_sku)) {
                $existing_variation_id = wc_get_product_id_by_sku($var_sku);
                if ($existing_variation_id) {
                    // Found existing variation - load it instead of creating new
                    $existing_product = wc_get_product($existing_variation_id);
                    if ($existing_product && $existing_product->is_type('variation')) {
                        // Update parent to new product and update variation
                        $variation = $existing_product;
                        $variation->set_parent_id($product_id);
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Found existing variation ID: $existing_variation_id, updating parent to $product_id\n", FILE_APPEND); }
                    } else {
                        // SKU exists but not a variation - skip with warning
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    WARNING: SKU '$var_sku' exists but is not a variation, skipping\n", FILE_APPEND); }
                        continue;
                    }
                } else {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                }
            } else {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
            }
            
            $variation->set_attributes($var_attributes);
            
            if (!empty($var_sku)) {
                $variation->set_sku($var_sku);
            }
            
            $var_price = $var_data[$csv_fields['regular_price'] ?? 'Price'] ?? '';
            if (!empty($var_price) && is_numeric($var_price)) {
                // Apply Pricing Engine to variation price
                $var_price = $this->apply_pricing_engine_to_price(floatval($var_price), $var_data);
                $variation->set_regular_price($var_price);
            }
            
            $var_sale_price = $var_data[$csv_fields['sale_price'] ?? 'Sale Price'] ?? '';
            if (!empty($var_sale_price) && is_numeric($var_sale_price)) {
                $variation->set_sale_price($var_sale_price);
            }
            
            // Handle stock management properly
            $var_stock_qty = $var_data[$csv_fields['stock_quantity'] ?? 'Stock Quantity'] ?? '';
            $var_stock_status = $var_data[$csv_fields['stock_status'] ?? 'Stock Status'] ?? '';
            $var_manage_stock = $var_data[$csv_fields['manage_stock'] ?? 'Manage Stock'] ?? '';
            
            // Normalize manage_stock value
            $should_manage_stock = in_array(strtolower(trim($var_manage_stock)), array('yes', '1', 'true', 'on'), true);
            
            if (!empty($var_stock_qty) && is_numeric($var_stock_qty)) {
                // Stock quantity provided - enable stock management
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity(intval($var_stock_qty));
                $variation->set_stock_status(intval($var_stock_qty) > 0 ? 'instock' : 'outofstock');
            } elseif ($var_manage_stock !== '') {
                // manage_stock field is explicitly set
                $variation->set_manage_stock($should_manage_stock);
                if (!$should_manage_stock) {
                    // Not managing stock - use stock_status from mapping or default to instock
                    $status = !empty($var_stock_status) ? strtolower(trim($var_stock_status)) : 'instock';
                    $status = ($status === 'outofstock') ? 'outofstock' : 'instock';
                    $variation->set_stock_status($status);
                }
            } else {
                // No stock info - don't manage stock, set status from mapping or default instock
                $variation->set_manage_stock(false);
                $status = !empty($var_stock_status) ? strtolower(trim($var_stock_status)) : 'instock';
                $status = ($status === 'outofstock') ? 'outofstock' : 'instock';
                $variation->set_stock_status($status);
            }
            
            // Set enabled
            $variation->set_status('publish');
            
            // Save variation with error handling
            try {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Saving variation...\n", FILE_APPEND); }
                $variation_id = $variation->save();
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Variation saved with ID: $variation_id\n", FILE_APPEND); }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ERROR saving variation: " . $e->getMessage() . "\n", FILE_APPEND); }
                continue;
            }
            
            // Set variation image if provided (skip on error to not block import)
            $var_image = $var_data[$csv_fields['image'] ?? 'Image'] ?? '';
            if (!empty($var_image) && filter_var($var_image, FILTER_VALIDATE_URL)) {
                try {
                    $image_id = $this->download_and_attach_image($var_image, $variation_id);
                    if ($image_id) {
                        set_post_thumbnail($variation_id, $image_id);
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    WARNING: Failed to set variation image: " . $e->getMessage() . "\n", FILE_APPEND); }
                }
            }
            
            $variation_count++;
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Created variation #$variation_count: $var_sku (ID: $variation_id)\n", FILE_APPEND); }
        }
        
        // Sync variable product - this calculates prices and stock from variations
        WC_Product_Variable::sync($product_id);
        
        // Force sync stock status - WooCommerce sometimes doesn't do this correctly
        // Check if any variation is in stock by looking at meta directly
        $children = get_posts(array(
            'post_type' => 'product_variation',
            'post_parent' => $product_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        $has_stock = false;
        foreach ($children as $child_id) {
            $child_stock_status = get_post_meta($child_id, '_stock_status', true);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Variation $child_id stock_status: $child_stock_status\n", FILE_APPEND); }
            if ($child_stock_status === 'instock') {
                $has_stock = true;
            }
        }
        
        // Update parent stock status directly in meta
        $new_stock_status = $has_stock ? 'instock' : 'outofstock';
        update_post_meta($product_id, '_stock_status', $new_stock_status);
        
        // Clear WooCommerce product cache
        wc_delete_product_transients($product_id);
        wp_cache_delete('product-' . $product_id, 'products');
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Set parent product $product_id stock_status to: $new_stock_status\n", FILE_APPEND); }
        
        $product_name = !empty($processed_data['name']) ? $processed_data['name'] : 'Variable Product';
        $this->log('info', sprintf(__('Imported variable product: %s with %d variations', 'bootflow-woocommerce-xml-csv-importer'), $product_name, $variation_count), $processed_data['sku'] ?? '');
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "=== CSV VARIABLE IMPORT COMPLETE: $product_name with $variation_count variations ===\n", FILE_APPEND); }
        
        return $product_id;
    }

    /**
     * Check if product passes import filters.
     *
     * @since    1.0.0
     * @param    array $product_data Raw product data
     * @return   bool True if passes all filters, false otherwise
     */
    private function passes_import_filters($product_data) {
        // PRO-only feature: if not PRO, skip all filters and import everything
        if (!WC_XML_CSV_AI_Import_Features::is_available('import_filters')) {
            return true; // FREE version = no filtering, import all
        }
        
        // Get import filters from config
        if (empty($this->config['import_filters']) || !is_array($this->config['import_filters'])) {
            return true; // No filters = import all
        }

        $filters = $this->config['import_filters'];

        if (empty($filters)) {
            return true; // No filters = import all
        }

        // Evaluate filters sequentially with their individual logic operators
        $cumulative_result = null;
        
        foreach ($filters as $index => $filter) {
            if (empty($filter['field']) || empty($filter['operator'])) {
                continue; // Skip invalid filters
            }

            $field = $filter['field'];
            $operator = $filter['operator'];
            $expected_value = isset($filter['value']) ? $filter['value'] : '';
            $logic = isset($filter['logic']) ? $filter['logic'] : 'AND'; // Default to AND

            // Get field value from product data (supports nested paths with dots)
            $actual_value = $this->xml_parser->extract_field_value($product_data, $field);

            // Evaluate filter condition
            $result = $this->evaluate_filter_condition($actual_value, $operator, $expected_value);

            // Log filter evaluation
            $this->log('debug', sprintf(
                'Filter #%d: %s %s "%s" | Actual: "%s" | Result: %s | Logic: %s',
                $index + 1,
                $field,
                $operator,
                $expected_value,
                $actual_value,
                $result ? 'PASS' : 'FAIL',
                $logic
            ));
            
            // Apply logic with previous result
            if ($cumulative_result === null) {
                // First filter
                $cumulative_result = $result;
            } else {
                // Combine with previous result using the PREVIOUS filter's logic
                $prev_index = $index - 1;
                $prev_logic = isset($filters[$prev_index]['logic']) ? $filters[$prev_index]['logic'] : 'AND';
                
                if ($prev_logic === 'OR') {
                    $cumulative_result = $cumulative_result || $result;
                } else {
                    $cumulative_result = $cumulative_result && $result;
                }
            }
        }

        return $cumulative_result !== null ? $cumulative_result : true;
    }

    /**
     * Evaluate single filter condition.
     *
     * @since    1.0.0
     * @param    mixed $actual_value Actual field value
     * @param    string $operator Comparison operator
     * @param    mixed $expected_value Expected value
     * @return   bool True if condition passes
     */
    private function evaluate_filter_condition($actual_value, $operator, $expected_value) {
        // Convert to string for comparison
        $actual = (string) $actual_value;
        $expected = (string) $expected_value;

        switch ($operator) {
            case '=':
                return $actual === $expected;

            case '!=':
                return $actual !== $expected;

            case '>':
                return is_numeric($actual) && is_numeric($expected) && floatval($actual) > floatval($expected);

            case '<':
                return is_numeric($actual) && is_numeric($expected) && floatval($actual) < floatval($expected);

            case '>=':
                return is_numeric($actual) && is_numeric($expected) && floatval($actual) >= floatval($expected);

            case '<=':
                return is_numeric($actual) && is_numeric($expected) && floatval($actual) <= floatval($expected);

            case 'contains':
                return stripos($actual, $expected) !== false;

            case 'not_contains':
                return stripos($actual, $expected) === false;

            case 'empty':
                return empty($actual);

            case 'not_empty':
                return !empty($actual);

            case 'regex_match':
                // Validate regex pattern to prevent fatal errors
                if (@preg_match($expected, '') === false) {
                    $this->log('warning', sprintf(__('Invalid regex pattern: %s', 'bootflow-woocommerce-xml-csv-importer'), $expected));
                    return true; // Invalid regex = pass (safe default)
                }
                return preg_match($expected, $actual) === 1;

            case 'regex_not_match':
                // Validate regex pattern to prevent fatal errors
                if (@preg_match($expected, '') === false) {
                    $this->log('warning', sprintf(__('Invalid regex pattern: %s', 'bootflow-woocommerce-xml-csv-importer'), $expected));
                    return true; // Invalid regex = pass (safe default)
                }
                return preg_match($expected, $actual) !== 1;

            default:
                return true; // Unknown operator = pass
        }
    }

    /**
     * Map raw product data to WooCommerce fields.
     *
     * @since    1.0.0
     * @param    array $product_data Raw product data
     * @return   array Mapped data
     */
    private function map_product_fields($product_data) {
        $mapped_data = array();
        $field_mappings = $this->config['field_mapping'];

        // Debug field mappings and source data
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Field mappings: ' . print_r($field_mappings, true)); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Product data keys: ' . print_r(array_keys($product_data), true)); }

        if (empty($field_mappings) || !is_array($field_mappings)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Field mappings are empty or not an array! Creating default mappings...'); }
            
            // Create basic default field mappings based on actual XML fields
            $field_mappings = array(
                'name' => array('source' => 'product_name', 'processing' => 'direct'),
                'regular_price' => array('source' => 'price', 'processing' => 'direct'),
                'sku' => array('source' => 'id', 'processing' => 'direct'),
                'short_description' => array('source' => 'short_specs', 'processing' => 'direct'),
                'description' => array('source' => 'description_lv', 'processing' => 'direct'),
                // Ensure stock quantity gets mapped by default from common XML field name
                'stock_quantity' => array('source' => 'quantity', 'processing' => 'direct'),
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Using default field mappings: ' . print_r($field_mappings, true)); }
        } else {
            // Check if field mappings have empty sources and fix them
            foreach ($field_mappings as $wc_field => &$mapping_config) {
                // Ensure mapping_config is an array
                if (!is_array($mapping_config)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Invalid mapping config for ' . $wc_field . ': ' . print_r($mapping_config, true)); }
                    continue;
                }
                
                if (empty($mapping_config['source'])) {
                    // Auto-assign common field mappings
                    switch ($wc_field) {
                        case 'name':
                            $mapping_config['source'] = 'product_name';
                            $mapping_config['processing'] = 'direct';
                            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Auto-assigned name field to product_name'); }
                            break;
                        case 'regular_price':
                            $mapping_config['source'] = 'price';
                            $mapping_config['processing'] = 'direct';
                            break;
                        case 'sku':
                            $mapping_config['source'] = 'id';
                            $mapping_config['processing'] = 'direct';
                            break;
                        case 'short_description':
                            $mapping_config['source'] = 'short_specs';
                            $mapping_config['processing'] = 'direct';
                            break;
                    }
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Updated field mappings: ' . print_r($field_mappings, true)); }
        }

        // Ensure field_mappings is an array before foreach
        if (!is_array($field_mappings)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Field mappings is not an array after all fixes! Type: ' . gettype($field_mappings)); }
            $field_mappings = array();
        }

        foreach ($field_mappings as $wc_field => $mapping_config) {
            // Skip if not an array
            if (!is_array($mapping_config)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Skipping invalid mapping config for ' . $wc_field); }
                continue;
            }
            
            // Handle boolean fields (Yes/No/Map mode)
            if (isset($mapping_config['boolean_mode'])) {
                $boolean_mode = $mapping_config['boolean_mode'];
                if ($boolean_mode === 'yes') {
                    $mapped_data[$wc_field] = 'yes';
                    continue;
                } elseif ($boolean_mode === 'no') {
                    $mapped_data[$wc_field] = 'no';
                    continue;
                }
                // If mode is 'map', continue to extract from source
            }
            
            // Handle select fields (Fixed/Map mode)
            if (isset($mapping_config['select_mode'])) {
                $select_mode = $mapping_config['select_mode'];
                if ($select_mode === 'fixed' && isset($mapping_config['fixed_value'])) {
                    $mapped_data[$wc_field] = $mapping_config['fixed_value'];
                    continue;
                }
                // If mode is 'map', continue to extract from source
            }
            
            // Handle SKU field with generate option
            if ($wc_field === 'sku' && isset($mapping_config['sku_mode']) && $mapping_config['sku_mode'] === 'generate') {
                $pattern = isset($mapping_config['sku_pattern']) ? $mapping_config['sku_pattern'] : 'PROD-{row}';
                $row_number = isset($this->current_row_number) ? $this->current_row_number : 1;
                
                // Get product name for {name} placeholder
                $product_name = '';
                if (isset($product_data['name'])) {
                    $product_name = sanitize_title(substr($product_data['name'], 0, 20));
                } elseif (isset($product_data['title'])) {
                    $product_name = sanitize_title(substr($product_data['title'], 0, 20));
                }
                
                // Generate SKU from pattern
                $generated_sku = $pattern;
                $generated_sku = str_replace('{row}', $row_number, $generated_sku);
                $generated_sku = str_replace('{timestamp}', time(), $generated_sku);
                $generated_sku = str_replace('{random}', strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6)), $generated_sku);
                $generated_sku = str_replace('{name}', $product_name, $generated_sku);
                $generated_sku = str_replace('{md5}', substr(md5($product_name . $row_number), 0, 8), $generated_sku);
                
                $mapped_data[$wc_field] = $generated_sku;
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("WC XML CSV AI Import - Generated SKU: $generated_sku (pattern: $pattern, row: $row_number)"); }
                continue;
            }
            
            if (empty($mapping_config['source'])) {
                continue;
            }

            $source_field = $mapping_config['source'];

            // Special handling for 'images' and 'featured_image' fields - keep as template string for later placeholder parsing
            if ($wc_field === 'images' || $wc_field === 'featured_image') {
                $mapped_data[$wc_field] = $source_field; // Keep the template string like "{image*}" or "{image2}"
                $log_file = WP_CONTENT_DIR . '/import_debug.log';
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "MAP: Keeping $wc_field as template: " . $source_field . "\n", FILE_APPEND); }
                continue;
            }

            // Check if source contains {placeholder} syntax (template string)
            // This allows combining multiple fields like "{price.#text} {price.@currency}"
            if (strpos($source_field, '{') !== false && strpos($source_field, '}') !== false) {
                // Parse template string - replace all {field} with actual values
                $value = $this->parse_template_string($source_field, $product_data);
            } else {
                // Simple field name - extract directly
                // Detect file type by available parser to avoid relying on config drift
                if (!empty($this->xml_parser)) {
                    $value = $this->xml_parser->extract_field_value($product_data, $source_field);
                } else {
                    $value = $this->csv_parser->extract_field_value($product_data, $source_field);
                }
            }

            if ($value !== null) {
                $mapped_data[$wc_field] = $value;
                // Debug mapping
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("WC XML CSV AI Import - Mapping $wc_field from $source_field: " . print_r($value, true)); }
                
                // Extra debug for brand
                if ($wc_field === 'brand') {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("BRAND MAPPING: WC field='brand', source='$source_field', value=" . print_r($value, true)); }
                }
            }
        }

        // Debug final mapped data
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Final mapped data: ' . print_r($mapped_data, true)); }

        return $mapped_data;
    }

    /**
     * Process mapped product fields through configured processors.
     *
     * @since    1.0.0
     * @param    array $mapped_data Mapped field data
     * @param    array $product_data Raw product data for context
     * @param    int|null $existing_product_id If product exists, its ID (to skip processing for update_on_sync=0 fields)
     * @return   array Processed data
     */
    private function process_product_fields($mapped_data, $product_data, $existing_product_id = null) {
        $field_mappings = $this->config['field_mapping'];
        $processed_data = array();
        $is_existing_product = !empty($existing_product_id);

        // Debug: Check if sale_price is in mapped_data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("★★★ MAPPED_DATA keys: " . implode(', ', array_keys($mapped_data)));
            if (isset($mapped_data['sale_price'])) {
                error_log("★★★ MAPPED_DATA[sale_price] = " . $mapped_data['sale_price']);
            } else {
                error_log("★★★ MAPPED_DATA[sale_price] NOT FOUND!");
            }
            if (isset($field_mappings['sale_price'])) {
                error_log("★★★ FIELD_MAPPINGS[sale_price] = " . json_encode($field_mappings['sale_price']));
            } else {
                error_log("★★★ FIELD_MAPPINGS[sale_price] NOT FOUND!");
            }
        }

        // Process all fields with full raw XML data as context
        // This allows any field to use any XML data as variables in formulas
        foreach ($mapped_data as $field_key => $value) {
            // Special handling for 'images' and 'featured_image' fields - keep template string unchanged
            if ($field_key === 'images' || $field_key === 'featured_image') {
                $processed_data[$field_key] = $value; // Keep as-is for placeholder parsing later
                continue;
            }
            
            if (isset($field_mappings[$field_key])) {
                $config = $field_mappings[$field_key];
                
                // OPTIMIZATION: Skip expensive processing (AI, PHP formulas) for existing products
                // if the field has update_on_sync disabled - the value won't be used anyway!
                if ($is_existing_product && isset($config['update_on_sync']) && $config['update_on_sync'] !== '1' && $config['update_on_sync'] !== 1 && $config['update_on_sync'] !== true) {
                    $processing_mode = $config['processing_mode'] ?? 'direct';
                    // Only skip expensive processing modes (AI and PHP formulas)
                    if ($processing_mode === 'ai_processing' || $processing_mode === 'php_formula') {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("★★★ SKIPPING {$processing_mode} for {$field_key} - existing product with update_on_sync=0");
                        }
                        // Just pass the raw value - it won't be saved anyway
                        $processed_data[$field_key] = $value;
                        continue;
                    }
                }
                
                // Debug: log what we're about to process
                if ($field_key === 'sale_price' || $field_key === 'regular_price') {
                    if (defined('WP_DEBUG') && WP_DEBUG) { 
                        error_log("★★★ PROCESSING {$field_key}: value={$value}, config=" . json_encode($config)); 
                    }
                }
                // Pass full raw product_data as context so all XML fields are available as variables
                $processed_data[$field_key] = $this->processor->process_field($value, $config, $product_data);
            } else {
                $processed_data[$field_key] = $value;
            }
        }

        // If quantity is mapped, ensure we will manage stock
        if (isset($processed_data['stock_quantity'])) {
            $processed_data['manage_stock'] = true;
        }

        return $processed_data;
    }

    /**
     * Apply Pricing Engine rules to calculate final price.
     *
     * @since    1.0.0
     * @param    array $processed_data Processed product data
     * @param    array $raw_data Raw product data for condition checking
     * @return   array Updated processed data with calculated price
     */
    private function apply_pricing_engine($processed_data, $raw_data) {
        $pricing_config = $this->config['field_mapping']['pricing_engine'] ?? null;
        
        // Check if pricing engine is enabled
        if (empty($pricing_config) || empty($pricing_config['enabled'])) {
            return $processed_data;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { 
            error_log('★★★ PRICING ENGINE: Starting with config: ' . json_encode($pricing_config)); 
        }
        
        // Get base price from configured source field
        $base_price_field = $pricing_config['base_price'] ?? 'price';
        $base_price = 0;
        
        // Try to get base price from raw data
        if (isset($raw_data[$base_price_field])) {
            $base_price = floatval(str_replace(',', '.', $raw_data[$base_price_field]));
        } elseif (isset($processed_data['regular_price'])) {
            $base_price = floatval(str_replace(',', '.', $processed_data['regular_price']));
        }
        
        if ($base_price <= 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log('★★★ PRICING ENGINE: No valid base price found, skipping'); 
            }
            return $processed_data;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { 
            error_log('★★★ PRICING ENGINE: Base price = ' . $base_price); 
        }
        
        // Find matching rule
        $matched_rule = null;
        $rules = $pricing_config['rules'] ?? array();
        
        foreach ($rules as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            
            if ($this->check_pricing_rule_conditions($rule, $base_price, $raw_data)) {
                $matched_rule = $rule;
                if (defined('WP_DEBUG') && WP_DEBUG) { 
                    error_log('★★★ PRICING ENGINE: Matched rule: ' . ($rule['name'] ?? 'Unnamed')); 
                }
                break; // First matching rule wins
            }
        }
        
        // Use default rule if no rule matched
        if (!$matched_rule) {
            $matched_rule = $pricing_config['default_rule'] ?? array(
                'markup_percent' => 0,
                'fixed_amount' => 0,
                'rounding' => 'none'
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log('★★★ PRICING ENGINE: Using default rule'); 
            }
        }
        
        // Calculate final price
        $markup_percent = floatval($matched_rule['markup_percent'] ?? 0);
        $fixed_amount = floatval($matched_rule['fixed_amount'] ?? 0);
        $rounding = $matched_rule['rounding'] ?? 'none';
        $min_price = !empty($matched_rule['min_price']) ? floatval($matched_rule['min_price']) : null;
        $max_price = !empty($matched_rule['max_price']) ? floatval($matched_rule['max_price']) : null;
        
        // If rule uses "inherit" rounding, get from default rule
        if ($rounding === 'inherit' && isset($pricing_config['default_rule']['rounding'])) {
            $rounding = $pricing_config['default_rule']['rounding'];
        }
        
        // Apply markup: price * (1 + markup%/100) + fixed
        $final_price = $base_price * (1 + $markup_percent / 100) + $fixed_amount;
        
        // Apply rounding
        $final_price = $this->apply_pricing_rounding($final_price, $rounding);
        
        // Apply min/max constraints
        if ($min_price !== null && $final_price < $min_price) {
            $final_price = $min_price;
        }
        if ($max_price !== null && $final_price > $max_price) {
            $final_price = $max_price;
        }
        
        // Ensure price is not negative
        if ($final_price < 0) {
            $final_price = 0;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { 
            error_log('★★★ PRICING ENGINE: Final price = ' . $final_price . ' (base=' . $base_price . ', markup=' . $markup_percent . '%, fixed=' . $fixed_amount . ')'); 
        }
        
        // Update regular_price in processed data
        $processed_data['regular_price'] = number_format($final_price, 2, '.', '');
        
        return $processed_data;
    }
    
    /**
     * Check if pricing rule conditions match.
     *
     * @param array $rule Rule configuration
     * @param float $base_price Base price value
     * @param array $raw_data Raw product data
     * @return bool True if all/any conditions match
     */
    private function check_pricing_rule_conditions($rule, $base_price, $raw_data) {
        $conditions = $rule['conditions'] ?? array();
        
        if (empty($conditions)) {
            return true; // No conditions = always matches
        }
        
        $logic = $rule['logic'] ?? 'AND';
        $results = array();
        
        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? '';
            $result = false;
            
            switch ($type) {
                case 'price_range':
                    $from = floatval(str_replace(',', '.', $condition['from'] ?? 0));
                    $to = floatval(str_replace(',', '.', $condition['to'] ?? PHP_INT_MAX));
                    $result = ($base_price >= $from && $base_price <= $to);
                    break;
                    
                case 'category':
                case 'brand':
                case 'supplier':
                    $operator = $condition['operator'] ?? 'equals';
                    $value = $condition['value'] ?? '';
                    $field_value = '';
                    
                    // Try to find the field in raw data
                    $possible_fields = array($type, ucfirst($type), strtoupper($type));
                    if ($type === 'category') {
                        $possible_fields = array_merge($possible_fields, array('category_id', 'categoryId', 'cat', 'categories'));
                    } elseif ($type === 'brand') {
                        $possible_fields = array_merge($possible_fields, array('brand_id', 'brandId', 'manufacturer'));
                    } elseif ($type === 'supplier') {
                        $possible_fields = array_merge($possible_fields, array('supplier_id', 'supplierId', 'vendor'));
                    }
                    
                    foreach ($possible_fields as $field) {
                        if (isset($raw_data[$field]) && !empty($raw_data[$field])) {
                            $field_value = (string)$raw_data[$field];
                            break;
                        }
                    }
                    
                    $result = $this->check_condition_operator($field_value, $operator, $value);
                    break;
                    
                case 'xml_field':
                    $field = $condition['field'] ?? '';
                    $operator = $condition['operator'] ?? 'equals';
                    $value = $condition['value'] ?? '';
                    $field_value = isset($raw_data[$field]) ? (string)$raw_data[$field] : '';
                    $result = $this->check_condition_operator($field_value, $operator, $value);
                    break;
                    
                case 'sku_pattern':
                    $pattern = $condition['pattern'] ?? '';
                    $sku = isset($raw_data['sku']) ? $raw_data['sku'] : (isset($raw_data['id']) ? $raw_data['id'] : '');
                    if (!empty($pattern)) {
                        $result = (preg_match('/' . preg_quote($pattern, '/') . '/i', $sku) === 1);
                    }
                    break;
            }
            
            $results[] = $result;
            
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log('★★★ PRICING ENGINE CONDITION: type=' . $type . ', result=' . ($result ? 'TRUE' : 'FALSE')); 
            }
        }
        
        // Apply logic (AND/OR)
        if ($logic === 'OR') {
            return in_array(true, $results, true);
        } else {
            return !in_array(false, $results, true);
        }
    }
    
    /**
     * Check condition operator.
     *
     * @param string $field_value Field value
     * @param string $operator Operator
     * @param string $expected Expected value
     * @return bool
     */
    private function check_condition_operator($field_value, $operator, $expected) {
        switch ($operator) {
            case 'equals':
                return strtolower($field_value) === strtolower($expected);
            case 'not_equals':
                return strtolower($field_value) !== strtolower($expected);
            case 'contains':
                return stripos($field_value, $expected) !== false;
            case 'not_contains':
                return stripos($field_value, $expected) === false;
            case 'starts_with':
                return stripos($field_value, $expected) === 0;
            case 'ends_with':
                return substr(strtolower($field_value), -strlen($expected)) === strtolower($expected);
            default:
                return false;
        }
    }
    
    /**
     * Apply rounding to price.
     *
     * @param float $price Price value
     * @param string $rounding Rounding type
     * @return float Rounded price
     */
    private function apply_pricing_rounding($price, $rounding) {
        switch ($rounding) {
            case '0.01':
                return round($price, 2);
            case '0.05':
                return round($price * 20) / 20;
            case '0.10':
                return round($price * 10) / 10;
            case '0.50':
                return round($price * 2) / 2;
            case '1.00':
                return round($price);
            case '0.99':
                // Round to nearest .99 (e.g., 10.45 -> 10.99, 10.00 -> 9.99)
                return floor($price) + 0.99;
            case '0.95':
                // Round to nearest .95
                return floor($price) + 0.95;
            case 'none':
            default:
                return round($price, 2);
        }
    }
    
    /**
     * Apply Pricing Engine to a single price value.
     * Used for variations where we only have the price, not full product data.
     *
     * @param float $base_price Base price value
     * @param array $raw_data Raw data for condition checking
     * @return string Calculated price as string
     */
    private function apply_pricing_engine_to_price($base_price, $raw_data = array()) {
        $pricing_config = $this->config['field_mapping']['pricing_engine'] ?? null;
        
        // Check if pricing engine is enabled
        if (empty($pricing_config) || empty($pricing_config['enabled'])) {
            return number_format($base_price, 2, '.', '');
        }
        
        if ($base_price <= 0) {
            return number_format($base_price, 2, '.', '');
        }
        
        // Find matching rule
        $matched_rule = null;
        $rules = $pricing_config['rules'] ?? array();
        
        foreach ($rules as $rule) {
            if (empty($rule['enabled'])) {
                continue;
            }
            
            if ($this->check_pricing_rule_conditions($rule, $base_price, $raw_data)) {
                $matched_rule = $rule;
                break;
            }
        }
        
        // Use default rule if no rule matched
        if (!$matched_rule) {
            $matched_rule = $pricing_config['default_rule'] ?? array(
                'markup_percent' => 0,
                'fixed_amount' => 0,
                'rounding' => 'none'
            );
        }
        
        // Calculate final price
        $markup_percent = floatval($matched_rule['markup_percent'] ?? 0);
        $fixed_amount = floatval($matched_rule['fixed_amount'] ?? 0);
        $rounding = $matched_rule['rounding'] ?? 'none';
        $min_price = !empty($matched_rule['min_price']) ? floatval($matched_rule['min_price']) : null;
        $max_price = !empty($matched_rule['max_price']) ? floatval($matched_rule['max_price']) : null;
        
        // If rule uses "inherit" rounding, get from default rule
        if ($rounding === 'inherit' && isset($pricing_config['default_rule']['rounding'])) {
            $rounding = $pricing_config['default_rule']['rounding'];
        }
        
        // Apply markup: price * (1 + markup%/100) + fixed
        $final_price = $base_price * (1 + $markup_percent / 100) + $fixed_amount;
        
        // Apply rounding
        $final_price = $this->apply_pricing_rounding($final_price, $rounding);
        
        // Apply min/max constraints
        if ($min_price !== null && $final_price < $min_price) {
            $final_price = $min_price;
        }
        if ($max_price !== null && $final_price > $max_price) {
            $final_price = $max_price;
        }
        
        // Ensure price is not negative
        if ($final_price < 0) {
            $final_price = 0;
        }
        
        return number_format($final_price, 2, '.', '');
    }

    /**
     * Validate product data.
     *
     * @since    1.0.0
     * @param    array $product_data Product data
     */
    private function validate_product_data($product_data, $existing_product_id = null) {
        // Debug: log what data we're validating
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Validating product data: ' . print_r($product_data, true)); }
        
        // Check required fields - name is only required for new products
        $required_fields = array();
        
        // If it's a new product (no existing ID), name is required
        if (!$existing_product_id) {
            $required_fields[] = 'name';
        }

        foreach ($required_fields as $field) {
            if (empty($product_data[$field])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Missing required field: ' . $field . ' - Product data keys: ' . print_r(array_keys($product_data), true)); }
                throw new Exception(sprintf(__('Required field "%s" is missing or empty.', 'bootflow-woocommerce-xml-csv-importer'), $field));
            }
        }

        // Validate SKU if provided
        if (!empty($product_data['sku']) && !preg_match('/^[a-zA-Z0-9\-_.]+$/', $product_data['sku'])) {
            throw new Exception(sprintf(__('Invalid SKU format: %s', 'bootflow-woocommerce-xml-csv-importer'), $product_data['sku']));
        }

        // Validate prices
        if (isset($product_data['regular_price']) && !is_numeric($product_data['regular_price'])) {
            throw new Exception(sprintf(__('Invalid regular price: %s', 'bootflow-woocommerce-xml-csv-importer'), $product_data['regular_price']));
        }

        if (isset($product_data['sale_price']) && !empty($product_data['sale_price']) && !is_numeric($product_data['sale_price'])) {
            throw new Exception(sprintf(__('Invalid sale price: %s', 'bootflow-woocommerce-xml-csv-importer'), $product_data['sale_price']));
        }
    }

    /**
     * Create new WooCommerce product.
     *
     * @since    1.0.0
     * @param    array $product_data Product data
     * @param    array $product_data Processed product data
     * @param    array $mapped_data Mapped data with template strings for images
     * @return   int Product ID
     */
    private function create_product($product_data, $mapped_data = array()) {
        // Determine product type - check explicit type field first
        $product_type = isset($product_data['type']) ? strtolower(trim($product_data['type'])) : '';
        
        // Auto-detect product type if not explicitly set
        if (empty($product_type) || $product_type === 'simple') {
            // If grouped_products field has values, it's a Grouped product
            if (!empty($product_data['grouped_products'])) {
                $product_type = 'grouped';
                $this->log('info', sprintf(__('Auto-detected Grouped product type for "%s" (has grouped_products field)', 'bootflow-woocommerce-xml-csv-importer'), $product_data['name'] ?? $product_data['sku'] ?? 'Unknown'));
            }
            // If external_url is set, it's an External product
            elseif (!empty($product_data['external_url'])) {
                $product_type = 'external';
                $this->log('info', sprintf(__('Auto-detected External product type for "%s" (has external_url field)', 'bootflow-woocommerce-xml-csv-importer'), $product_data['name'] ?? $product_data['sku'] ?? 'Unknown'));
            }
            // Default to simple
            else {
                $product_type = 'simple';
            }
        }
        
        // Create appropriate product object based on type
        switch ($product_type) {
            case 'grouped':
                // PRO feature check
                if (!WC_XML_CSV_AI_Import_Features::is_available('variable_products')) {
                    $this->log('warning', sprintf(__('Grouped products require PRO license. Importing "%s" as Simple product.', 'bootflow-woocommerce-xml-csv-importer'), $product_data['name']));
                    $product = new WC_Product_Simple();
                    $product_type = 'simple';
                } else {
                    $product = new WC_Product_Grouped();
                }
                break;
                
            case 'external':
            case 'affiliate':
                // PRO feature check
                if (!WC_XML_CSV_AI_Import_Features::is_available('variable_products')) {
                    $this->log('warning', sprintf(__('External products require PRO license. Importing "%s" as Simple product.', 'bootflow-woocommerce-xml-csv-importer'), $product_data['name']));
                    $product = new WC_Product_Simple();
                    $product_type = 'simple';
                } else {
                    $product = new WC_Product_External();
                }
                break;
                
            case 'variable':
                $product = new WC_Product_Variable();
                break;
                
            default:
                $product = new WC_Product_Simple();
                $product_type = 'simple';
        }

        // Set basic properties
        $product->set_name($product_data['name']);
        
        if (!empty($product_data['sku'])) {
            $product->set_sku($product_data['sku']);
        }

        if (!empty($product_data['description'])) {
            $product->set_description($product_data['description']);
        }

        if (!empty($product_data['short_description'])) {
            $product->set_short_description($product_data['short_description']);
        }

        // Set pricing
        if (!empty($product_data['regular_price'])) {
            $product->set_regular_price($product_data['regular_price']);
        }

        if (!empty($product_data['sale_price'])) {
            $product->set_sale_price($product_data['sale_price']);
        }

        // Set sale price dates
        if (!empty($product_data['sale_price_dates_from'])) {
            $product->set_date_on_sale_from($product_data['sale_price_dates_from']);
        }
        if (!empty($product_data['sale_price_dates_to'])) {
            $product->set_date_on_sale_to($product_data['sale_price_dates_to']);
        }

        // Set tax status/class
        if (!empty($product_data['tax_status'])) {
            $product->set_tax_status($product_data['tax_status']);
        }
        if (isset($product_data['tax_class'])) {
            $product->set_tax_class($product_data['tax_class']);
        }

        // Set inventory
        if (isset($product_data['manage_stock'])) {
            $manage = $product_data['manage_stock'];
            $product->set_manage_stock($manage === 'yes' || $manage === '1' || $manage === true || $manage === 1);
        }

        // If stock quantity is provided, force-enable manage_stock and set quantity
        if (isset($product_data['stock_quantity'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity((int) $product_data['stock_quantity']);
        }

        // Store stock_status for forcing after save
        $force_stock_status = null;
        if (!empty($product_data['stock_status'])) {
            $force_stock_status = $product_data['stock_status'];
            $product->set_stock_status($product_data['stock_status']);
        }

        // Set dimensions
        if (!empty($product_data['weight'])) {
            $product->set_weight($product_data['weight']);
        }

        if (!empty($product_data['length'])) {
            $product->set_length($product_data['length']);
        }

        if (!empty($product_data['width'])) {
            $product->set_width($product_data['width']);
        }

        if (!empty($product_data['height'])) {
            $product->set_height($product_data['height']);
        }

        // Set sold individually
        if (isset($product_data['sold_individually'])) {
            $sold_ind = $product_data['sold_individually'];
            $product->set_sold_individually($sold_ind === 'yes' || $sold_ind === '1' || $sold_ind === true);
        }

        // Set virtual/downloadable
        if (isset($product_data['virtual'])) {
            $product->set_virtual($product_data['virtual'] === 'yes' || $product_data['virtual'] === '1');
        }
        if (isset($product_data['downloadable'])) {
            $product->set_downloadable($product_data['downloadable'] === 'yes' || $product_data['downloadable'] === '1');
        }

        // Set download limit/expiry
        if (isset($product_data['download_limit'])) {
            $product->set_download_limit((int) $product_data['download_limit']);
        }
        if (isset($product_data['download_expiry'])) {
            $product->set_download_expiry((int) $product_data['download_expiry']);
        }

        // Set backorders
        if (isset($product_data['backorders'])) {
            $product->set_backorders($product_data['backorders']);
        }

        // Set featured
        if (isset($product_data['featured'])) {
            $product->set_featured($product_data['featured'] === 'yes' || $product_data['featured'] === '1');
        }
        
        // Set reviews allowed
        if (isset($product_data['reviews_allowed'])) {
            $product->set_reviews_allowed($product_data['reviews_allowed'] === 'yes' || $product_data['reviews_allowed'] === '1' || $product_data['reviews_allowed'] === true);
        }

        // Set shipping class - direct mapping first
        if (isset($product_data['shipping_class']) && !empty($product_data['shipping_class'])) {
            $shipping_class_slug = sanitize_title($product_data['shipping_class']);
            $shipping_class_term = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
            
            // Create shipping class if it doesn't exist
            if (!$shipping_class_term || is_wp_error($shipping_class_term)) {
                $new_term = wp_insert_term(
                    $product_data['shipping_class'], // Original name
                    'product_shipping_class',
                    array('slug' => $shipping_class_slug)
                );
                if (!is_wp_error($new_term)) {
                    $shipping_class_term = get_term($new_term['term_id'], 'product_shipping_class');
                    $this->log('debug', sprintf('Created shipping class: %s', $product_data['shipping_class']));
                }
            }
            
            if ($shipping_class_term && !is_wp_error($shipping_class_term)) {
                $product->set_shipping_class_id($shipping_class_term->term_id);
                $this->log('debug', sprintf('Set shipping class: %s for product: %s', $product_data['shipping_class'], $product_data['sku'] ?? 'unknown'));
            }
        } else {
            // Auto-assign shipping class based on dimensions and weight (only if no direct mapping)
            $this->auto_assign_shipping_class($product, $product_data);
        }

        // Set status - validate that status is a valid WordPress post_status
        $valid_statuses = array('publish', 'draft', 'pending', 'private', 'trash');
        $status = isset($product_data['status']) ? $product_data['status'] : 'publish';
        if (!in_array($status, $valid_statuses)) {
            // If status is not valid (e.g., accidentally mapped to wrong column), default to 'publish'
            $this->log('warning', 'Invalid product status "' . $status . '" - defaulting to "publish"');
            $status = 'publish';
        }
        $product->set_status($status);

        // Handle External product specific fields
        if ($product_type === 'external' && $product instanceof WC_Product_External) {
            if (!empty($product_data['external_url'])) {
                $product->set_product_url($product_data['external_url']);
            }
            if (!empty($product_data['button_text'])) {
                $product->set_button_text($product_data['button_text']);
            }
        }

        // Save product
        $product_id = $product->save();

        if (!$product_id) {
            throw new Exception(__('Failed to create product.', 'bootflow-woocommerce-xml-csv-importer'));
        }
        
        // SYNC _price meta - WooCommerce needs this for frontend display
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $price = ('' !== $sale_price && $sale_price !== null) ? $sale_price : $regular_price;
        if ('' !== $price && $price !== null) {
            update_post_meta($product_id, '_price', $price);
        }
        
        // FORCE stock_status after save - WooCommerce may have overridden it based on stock_quantity
        if ($force_stock_status) {
            global $wpdb;
            $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $force_stock_status),
                array('post_id' => $product_id, 'meta_key' => '_stock_status'),
                array('%s'),
                array('%d', '%s')
            );
            wc_delete_product_transients($product_id);
            wp_cache_delete($product_id, 'post_meta');
            
            $update_debug_file = WP_CONTENT_DIR . '/update_debug.log';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "CREATE FORCE EXECUTED: Product $product_id, setting stock_status to: $force_stock_status\n", FILE_APPEND); }
            
            $this->log('debug', 'Forced stock_status to: ' . $force_stock_status . ' for new product ID: ' . $product_id);
        }
        
        // DEBUG: Verify saved stock_status from DB
        $update_debug_file = WP_CONTENT_DIR . '/update_debug.log';
        global $wpdb;
        $db_stock_status = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock_status'", $product_id));
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "CREATE VERIFY (DB) - Product ID: $product_id, stock_status in DB: $db_stock_status, force_stock_status: " . ($force_stock_status ?? 'none') . "\n", FILE_APPEND); }

        // Save product identifiers (EAN, UPC, ISBN, MPN, GTIN)
        $this->save_product_identifiers($product_id, $product_data);

        // Handle categories
        if (!empty($product_data['categories'])) {
            $this->set_product_categories($product_id, $product_data['categories']);
        }

        // Handle tags
        if (!empty($product_data['tags'])) {
            $this->set_product_tags($product_id, $product_data['tags']);
        }

        // Handle brand
        $this->log('debug', 'CREATE_PRODUCT: Checking brand in product_data: ' . print_r($product_data['brand'] ?? 'NOT SET', true));
        if (!empty($product_data['brand'])) {
            $this->log('debug', 'CREATE_PRODUCT: Brand value: ' . print_r($product_data['brand'], true));
            $this->set_product_brand($product_id, $product_data['brand']);
        } else {
            $this->log('warning', 'CREATE_PRODUCT: No brand data found');
        }

        // Handle images
        $this->log('debug', 'CREATE_PRODUCT: Starting image processing');
        $this->log('debug', 'mapped_data keys: ' . implode(', ', array_keys($mapped_data)));
        $this->log('debug', 'mapped images: ' . print_r($mapped_data['images'] ?? 'NOT SET', true));
        $this->log('debug', 'mapped featured_image: ' . print_r($mapped_data['featured_image'] ?? 'NOT SET', true));
        
        if (!empty($mapped_data['images']) || !empty($mapped_data['featured_image'])) {
            $this->log('debug', 'Calling set_product_images...');
            $this->set_product_images($product_id, $mapped_data);
        } else {
            $this->log('warning', 'Skipping images - both images and featured_image empty!');
        }

        // Handle SEO meta fields
        $this->set_product_seo_meta($product_id, $product_data);

        // Handle Grouped product children (store SKUs for later resolution)
        if ($product_type === 'grouped' && $product instanceof WC_Product_Grouped) {
            if (!empty($product_data['grouped_products'])) {
                // Store the SKU list for phase 2 resolution (after all products are imported)
                update_post_meta($product_id, '_pending_grouped_skus', $product_data['grouped_products']);
                $this->log('info', sprintf(__('Grouped product "%s" - stored pending children SKUs: %s', 'bootflow-woocommerce-xml-csv-importer'), $product_data['name'], $product_data['grouped_products']));
            }
        }

        // Handle Upsells and Cross-sells (store SKUs for later resolution)
        if (!empty($product_data['upsell_ids'])) {
            update_post_meta($product_id, '_pending_upsell_skus', $product_data['upsell_ids']);
        }
        if (!empty($product_data['cross_sell_ids'])) {
            update_post_meta($product_id, '_pending_crosssell_skus', $product_data['cross_sell_ids']);
        }

        // Save import tracking meta
        update_post_meta($product_id, '_wc_import_id', $this->import_id);
        update_post_meta($product_id, '_wc_import_date', current_time('mysql'));
        update_post_meta($product_id, '_wc_import_source', 'create');

        return $product_id;
    }

    /**
     * Compare product data to check if update is needed.
     *
     * @since    1.0.0
     * @param    int $product_id Existing product ID
     * @param    array $new_data New product data to import
     * @return   bool True if data has changed, false if identical
     */
    private function has_product_data_changed($product_id, $new_data) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return true; // Product doesn't exist, needs creation
        }

        // Compare basic fields
        $fields_to_compare = array(
            'name' => 'get_name',
            'description' => 'get_description',
            'short_description' => 'get_short_description',
            'regular_price' => 'get_regular_price',
            'sale_price' => 'get_sale_price',
            'stock_quantity' => 'get_stock_quantity',
            'weight' => 'get_weight',
            'length' => 'get_length',
            'width' => 'get_width',
            'height' => 'get_height',
        );

        foreach ($fields_to_compare as $field => $getter) {
            if (!isset($new_data[$field])) {
                continue; // Field not mapped, skip comparison
            }

            $current_value = $product->$getter();
            $new_value = $new_data[$field];

            // Normalize values for comparison
            $current_value = trim((string)$current_value);
            $new_value = trim((string)$new_value);

            // For prices, compare as floats
            if (in_array($field, array('regular_price', 'sale_price', 'weight', 'length', 'width', 'height'))) {
                $current_value = floatval($current_value);
                $new_value = floatval($new_value);
            }

            // For stock quantity, compare as integers
            if ($field === 'stock_quantity') {
                $current_value = intval($current_value);
                $new_value = intval($new_value);
            }

            if ($current_value !== $new_value) {
                $this->log('info', sprintf(
                    'Field "%s" changed: "%s" → "%s"',
                    $field,
                    substr($current_value, 0, 50),
                    substr($new_value, 0, 50)
                ));
                return true; // Data has changed
            }
        }

        // Compare categories
        if (isset($new_data['categories'])) {
            $current_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
            $new_categories = is_array($new_data['categories']) ? $new_data['categories'] : array($new_data['categories']);
            
            sort($current_categories);
            sort($new_categories);
            
            if ($current_categories !== $new_categories) {
                $this->log('debug', 'Categories changed');
                return true;
            }
        }

        // Compare tags
        if (isset($new_data['tags'])) {
            $current_tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'));
            $new_tags = is_array($new_data['tags']) ? $new_data['tags'] : explode(',', $new_data['tags']);
            $new_tags = array_map('trim', $new_tags);
            
            sort($current_tags);
            sort($new_tags);
            
            if ($current_tags !== $new_tags) {
                $this->log('debug', 'Tags changed');
                return true;
            }
        }

        // Compare brand
        if (isset($new_data['brand'])) {
            $current_brands = wp_get_post_terms($product_id, 'product_brand', array('fields' => 'names'));
            $new_brand = is_array($new_data['brand']) ? $new_data['brand'] : array($new_data['brand']);
            $new_brand = array_map('trim', $new_brand);
            
            sort($current_brands);
            sort($new_brand);
            
            if ($current_brands !== $new_brand) {
                $this->log('debug', 'Brand changed');
                return true;
            }
        }

        $this->log('debug', 'No changes detected - all data identical');
        return false; // No changes detected
    }

    /**
     * Check if a field should be updated based on update_on_sync setting.
     *
     * @since    1.2.0
     * @param    string $field_key Field key (e.g., 'name', 'description')
     * @param    mixed $value The value to be set (used to check if empty/null)
     * @param    bool $is_new_product Whether this is a new product being created
     * @return   bool True if field should be updated, false otherwise
     */
    private function should_update_field($field_key, $value = null, $is_new_product = false) {
        // For NEW products, always save all mapped fields (ignore checkboxes)
        if ($is_new_product) {
            return true;
        }

        // FREE version: always update all fields (selective update is PRO feature)
        if (!WC_XML_CSV_AI_Import_Features::is_available('update_existing')) {
            return true;
        }

        // For EXISTING products (PRO only from here):
        // If no field mappings configured, update all fields (backwards compatibility)
        if (empty($this->config['field_mapping'])) {
            return true;
        }

        // Check if field has mapping configuration
        if (!isset($this->config['field_mapping'][$field_key])) {
            return true; // No mapping = update (backwards compatibility)
        }

        $mapping = $this->config['field_mapping'][$field_key];

        // Check update_on_sync flag - default to true if not set (backwards compatibility)
        if (!isset($mapping['update_on_sync'])) {
            return true;
        }

        // Get checkbox state ('1' = update enabled, '0' = update disabled)
        $update_enabled = $mapping['update_on_sync'] === '1' || $mapping['update_on_sync'] === 1 || $mapping['update_on_sync'] === true;

        // If checkbox is ENABLED, always update
        if ($update_enabled) {
            return true;
        }

        // If checkbox is DISABLED, NEVER update this field for existing products
        // This ensures that fields configured to NOT update are truly preserved
        return false;
    }

    /**
     * Update existing WooCommerce product.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    array $product_data Processed product data
     * @param    array $mapped_data Mapped data with template strings for images
     * @return   int Product ID
     */
    private function update_product($product_id, $product_data, $mapped_data = array()) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(sprintf(__('Product with ID %d not found.', 'bootflow-woocommerce-xml-csv-importer'), $product_id));
        }
        
        // DEBUG: Log update_product call
        $update_debug_file = WP_CONTENT_DIR . '/update_debug.log';
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "\n--- UPDATE_PRODUCT FUNCTION CALLED ---\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Product ID: $product_id\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Product data stock_status: " . ($product_data['stock_status'] ?? 'NOT SET') . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Product data manage_stock: " . ($product_data['manage_stock'] ?? 'NOT SET') . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "Current product stock_status: " . $product->get_stock_status() . "\n", FILE_APPEND); }

        // Update properties only if update_on_sync is enabled for that field
        // Pass false for $is_new_product since this is update_product
        if ($this->should_update_field('name', $product_data['name'] ?? null, false)) {
            $product->set_name($product_data['name'] ?? '');
        }

        if ($this->should_update_field('description', $product_data['description'] ?? null, false)) {
            $product->set_description($product_data['description'] ?? '');
        }

        if ($this->should_update_field('short_description', $product_data['short_description'] ?? null, false)) {
            $product->set_short_description($product_data['short_description'] ?? '');
        }

        if ($this->should_update_field('regular_price', $product_data['regular_price'] ?? null, false)) {
            $product->set_regular_price($product_data['regular_price'] ?? '');
        }

        // DEBUG: Log sale_price before setting
        if (defined('WP_DEBUG') && WP_DEBUG) { 
            file_put_contents($update_debug_file, "★★★ Sale price in product_data: " . ($product_data['sale_price'] ?? 'NOT SET') . "\n", FILE_APPEND); 
            file_put_contents($update_debug_file, "★★★ Regular price in product_data: " . ($product_data['regular_price'] ?? 'NOT SET') . "\n", FILE_APPEND); 
        }

        if ($this->should_update_field('sale_price', $product_data['sale_price'] ?? null, false)) {
            $product->set_sale_price($product_data['sale_price'] ?? '');
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                file_put_contents($update_debug_file, "★★★ SETTING sale_price to: " . ($product_data['sale_price'] ?? '') . "\n", FILE_APPEND); 
            }
        }

        // Sale price dates
        if (isset($product_data['sale_price_dates_from']) && !empty($product_data['sale_price_dates_from'])) {
            $product->set_date_on_sale_from($product_data['sale_price_dates_from']);
        }
        
        if (isset($product_data['sale_price_dates_to']) && !empty($product_data['sale_price_dates_to'])) {
            $product->set_date_on_sale_to($product_data['sale_price_dates_to']);
        }

        // Update stock quantity
        if (isset($product_data['stock_quantity']) && $this->should_update_field('stock_quantity', $product_data['stock_quantity'], false)) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($product_data['stock_quantity']);
        }

        // Update manage_stock (boolean field)
        // IMPORTANT: If manage_stock is YES but no stock_quantity provided, we need to handle stock_status differently
        $manage_stock_enabled = false;
        if (isset($product_data['manage_stock']) && $this->should_update_field('manage_stock', $product_data['manage_stock'], false)) {
            $manage = $product_data['manage_stock'];
            $manage_stock_enabled = ($manage === 'yes' || $manage === '1' || $manage === true || $manage === 1);
            $product->set_manage_stock($manage_stock_enabled);
        }

        // Update stock_status (instock/outofstock/onbackorder)
        // Store for later - WooCommerce may override this based on stock_quantity
        $force_stock_status = null;
        if (!empty($product_data['stock_status']) && $this->should_update_field('stock_status', $product_data['stock_status'], false)) {
            $force_stock_status = $product_data['stock_status'];
            $product->set_stock_status($product_data['stock_status']);
            $this->log('debug', 'Setting stock_status to: ' . $product_data['stock_status']);
        }

        // Update tax_status (taxable/shipping/none)
        if (!empty($product_data['tax_status']) && $this->should_update_field('tax_status', $product_data['tax_status'], false)) {
            $product->set_tax_status($product_data['tax_status']);
        }

        // Update tax_class
        if (isset($product_data['tax_class']) && $this->should_update_field('tax_class', $product_data['tax_class'], false)) {
            $product->set_tax_class($product_data['tax_class']);
        }

        // Update weight and dimensions
        if ($this->should_update_field('weight', $product_data['weight'] ?? null, false)) {
            $product->set_weight($product_data['weight'] ?? '');
        }

        if ($this->should_update_field('length', $product_data['length'] ?? null, false)) {
            $product->set_length($product_data['length'] ?? '');
        }

        if ($this->should_update_field('width', $product_data['width'] ?? null, false)) {
            $product->set_width($product_data['width'] ?? '');
        }

        if ($this->should_update_field('height', $product_data['height'] ?? null, false)) {
            $product->set_height($product_data['height'] ?? '');
        }

        // Shipping class - direct mapping or formula
        if (isset($product_data['shipping_class']) && !empty($product_data['shipping_class'])) {
            $shipping_class_slug = sanitize_title($product_data['shipping_class']);
            $shipping_class_term = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
            
            // Create shipping class if it doesn't exist
            if (!$shipping_class_term || is_wp_error($shipping_class_term)) {
                $new_term = wp_insert_term(
                    $product_data['shipping_class'], // Original name
                    'product_shipping_class',
                    array('slug' => $shipping_class_slug)
                );
                if (!is_wp_error($new_term)) {
                    $shipping_class_term = get_term($new_term['term_id'], 'product_shipping_class');
                    $this->log('debug', sprintf('Created shipping class: %s', $product_data['shipping_class']));
                }
            }
            
            if ($shipping_class_term && !is_wp_error($shipping_class_term)) {
                $product->set_shipping_class_id($shipping_class_term->term_id);
            }
        } elseif ($this->should_update_field('shipping_class', null, false)) {
            // Auto-assign shipping class based on formula
            $this->auto_assign_shipping_class($product, $product_data);
        }

        // Update status if provided - validate that status is a valid WordPress post_status
        if (isset($product_data['status']) && $this->should_update_field('status', $product_data['status'], false)) {
            $valid_statuses = array('publish', 'draft', 'pending', 'private', 'trash');
            $status = $product_data['status'];
            if (in_array($status, $valid_statuses)) {
                $product->set_status($status);
            } else {
                $this->log('warning', 'Invalid product status "' . $status . '" - skipping status update');
            }
        }

        // Update sold_individually
        if ($this->should_update_field('sold_individually', $product_data['sold_individually'] ?? null, false)) {
            $sold_ind = $product_data['sold_individually'] ?? '';
            $product->set_sold_individually($sold_ind === 'yes' || $sold_ind === '1' || $sold_ind === true);
        }

        // Update virtual/downloadable
        if ($this->should_update_field('virtual', $product_data['virtual'] ?? null, false)) {
            $product->set_virtual($product_data['virtual'] === 'yes' || $product_data['virtual'] === '1');
        }
        if ($this->should_update_field('downloadable', $product_data['downloadable'] ?? null, false)) {
            $product->set_downloadable($product_data['downloadable'] === 'yes' || $product_data['downloadable'] === '1');
        }

        // Update download limit/expiry
        if ($this->should_update_field('download_limit', $product_data['download_limit'] ?? null, false)) {
            $product->set_download_limit((int) ($product_data['download_limit'] ?? -1));
        }
        if ($this->should_update_field('download_expiry', $product_data['download_expiry'] ?? null, false)) {
            $product->set_download_expiry((int) ($product_data['download_expiry'] ?? -1));
        }

        // Update backorders
        if ($this->should_update_field('backorders', $product_data['backorders'] ?? null, false)) {
            $product->set_backorders($product_data['backorders'] ?? 'no');
        }

        // Update featured
        if ($this->should_update_field('featured', $product_data['featured'] ?? null, false)) {
            $product->set_featured($product_data['featured'] === 'yes' || $product_data['featured'] === '1');
        }
        
        // Update reviews allowed
        if ($this->should_update_field('reviews_allowed', $product_data['reviews_allowed'] ?? null, false)) {
            $product->set_reviews_allowed($product_data['reviews_allowed'] === 'yes' || $product_data['reviews_allowed'] === '1' || $product_data['reviews_allowed'] === true);
        }

        $product->save();
        
        // SYNC _price meta - WooCommerce needs this for frontend display
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $price = ('' !== $sale_price && $sale_price !== null) ? $sale_price : $regular_price;
        if ('' !== $price && $price !== null) {
            update_post_meta($product_id, '_price', $price);
        }
        
        // FORCE stock_status after save - WooCommerce may have overridden it based on stock_quantity
        if ($force_stock_status) {
            // Use direct database update to bypass WooCommerce hooks
            global $wpdb;
            $wpdb->update(
                $wpdb->postmeta,
                array('meta_value' => $force_stock_status),
                array('post_id' => $product_id, 'meta_key' => '_stock_status'),
                array('%s'),
                array('%d', '%s')
            );
            
            // Also clear WooCommerce product cache
            wc_delete_product_transients($product_id);
            wp_cache_delete($product_id, 'post_meta');
            
            // Debug
            $update_debug_file = WP_CONTENT_DIR . '/update_debug.log';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "FORCE EXECUTED: Product $product_id, setting stock_status to: $force_stock_status\n", FILE_APPEND); }
            
            $this->log('debug', 'Forced stock_status to: ' . $force_stock_status . ' for product ID: ' . $product_id);
        }
        
        // DEBUG: Verify saved stock_status after update - read directly from DB
        $update_debug_file = WP_CONTENT_DIR . '/update_debug.log';
        global $wpdb;
        $db_stock_status = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_stock_status'", $product_id));
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($update_debug_file, "UPDATE VERIFY (DB) - Product ID: $product_id, stock_status in DB: $db_stock_status, force_stock_status: " . ($force_stock_status ?? 'none') . "\n", FILE_APPEND); }

        // Save/update product identifiers (EAN, UPC, ISBN, MPN, GTIN)
        $this->save_product_identifiers($product_id, $product_data);

        // Handle categories - ONLY if categories data exists AND update is enabled
        $categories_to_set = $product_data['categories'] ?? null;
        if (!empty($categories_to_set) && $this->should_update_field('categories', $categories_to_set, false)) {
            $this->set_product_categories($product_id, $categories_to_set);
        }

        // Handle tags - ONLY if tags data exists AND update is enabled
        $tags_to_set = $product_data['tags'] ?? null;
        if (!empty($tags_to_set) && $this->should_update_field('tags', $tags_to_set, false)) {
            $this->set_product_tags($product_id, $tags_to_set);
        }

        // Handle brand
        $this->log('debug', 'UPDATE_PRODUCT: Checking brand in product_data: ' . print_r($product_data['brand'] ?? 'NOT SET', true));
        if ($this->should_update_field('brand', $product_data['brand'] ?? null, false)) {
            $this->log('debug', 'UPDATE_PRODUCT: Brand value: ' . print_r($product_data['brand'], true));
            $this->set_product_brand($product_id, $product_data['brand'] ?? '');
        } else {
            $this->log('warning', 'UPDATE_PRODUCT: No brand data found or update disabled');
        }

        // Handle images
        $this->log('debug', 'UPDATE_PRODUCT: Starting image processing');
        $this->log('debug', 'mapped_data keys: ' . implode(', ', array_keys($mapped_data)));
        $this->log('debug', 'mapped images: ' . print_r($mapped_data['images'] ?? 'NOT SET', true));
        $this->log('debug', 'mapped featured_image: ' . print_r($mapped_data['featured_image'] ?? 'NOT SET', true));
        
        if ($this->should_update_field('images', $mapped_data['images'] ?? null, false)) {
            $this->log('debug', 'Calling set_product_images from update_product...');
            $this->set_product_images($product_id, $mapped_data);
        } else {
            $this->log('warning', 'UPDATE: Skipping images - update disabled!');
        }

        // Handle SEO meta fields
        if ($this->should_update_field('seo_title', null, false) || $this->should_update_field('seo_description', null, false) || $this->should_update_field('seo_keywords', null, false)) {
            $this->set_product_seo_meta($product_id, $product_data);
        }

        // Update import tracking meta
        update_post_meta($product_id, '_wc_import_id', $this->import_id);
        update_post_meta($product_id, '_wc_import_date', current_time('mysql'));
        update_post_meta($product_id, '_wc_import_source', 'update');

        return $product_id;
    }

    /**
     * Set product categories.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    mixed $categories Categories (array or string)
     */
    private function set_product_categories($product_id, $categories) {
        if (is_string($categories)) {
            $categories = array_map('trim', explode(',', $categories));
        }

        $category_ids = array();
        $all_tags = array(); // Collect all category elements as tags
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $auto_create = isset($settings['auto_create_categories']) ? $settings['auto_create_categories'] : true;

        $this->log_debug('SET_PRODUCT_CATEGORIES: Processing categories for product', array(
            'product_id' => $product_id,
            'categories' => $categories,
            'auto_create' => $auto_create
        ));

        foreach ($categories as $category_path) {
            $category_path = trim($category_path);
            if (empty($category_path)) continue;

            // Auto-detect separator: >, -, |, //, ->
            $separator = $this->detect_category_separator($category_path);
            
            if ($separator) {
                $this->log_debug('CATEGORY_HIERARCHY: Detected separator', array(
                    'path' => $category_path,
                    'separator' => $separator
                ));
                
                // Hierarchical category path detected
                $hierarchy_id = $this->create_category_hierarchy($category_path, $separator, $auto_create);
                if ($hierarchy_id) {
                    $category_ids[] = $hierarchy_id;
                    $this->log_debug('CATEGORY_HIERARCHY: Created hierarchy', array(
                        'path' => $category_path,
                        'final_category_id' => $hierarchy_id
                    ));
                }
                
                // Extract each element as a tag
                $elements = array_map('trim', explode($separator, $category_path));
                foreach ($elements as $element) {
                    if (!empty($element) && $element !== '>' && $element !== '-') {
                        $all_tags[] = $element;
                    }
                }
            } else {
                $this->log_debug('CATEGORY_SIMPLE: Single category (no hierarchy)', array(
                    'path' => $category_path
                ));
                
                // Single category (no hierarchy)
                $category = get_term_by('name', $category_path, 'product_cat');
                
                if (!$category && $auto_create) {
                    $result = wp_insert_term($category_path, 'product_cat');
                    if (!is_wp_error($result)) {
                        $category_ids[] = $result['term_id'];
                        $this->log_debug('CATEGORY_CREATED: New category', array(
                            'name' => $category_path,
                            'term_id' => $result['term_id']
                        ));
                    }
                } elseif ($category) {
                    $category_ids[] = $category->term_id;
                }
                
                // Add as tag
                $all_tags[] = $category_path;
            }
        }

        // Set categories
        if (!empty($category_ids)) {
            wp_set_object_terms($product_id, $category_ids, 'product_cat');
            $this->log_debug('CATEGORIES_ASSIGNED: Product categories set', array(
                'product_id' => $product_id,
                'category_ids' => $category_ids
            ));
        }
        
        // Set tags (all category elements)
        if (!empty($all_tags)) {
            $all_tags = array_unique($all_tags); // Remove duplicates
            wp_set_object_terms($product_id, $all_tags, 'product_tag', true); // Append tags
            $this->log_debug('TAGS_ASSIGNED: Product tags set from categories', array(
                'product_id' => $product_id,
                'tags' => $all_tags,
                'count' => count($all_tags)
            ));
        }
        
        // Auto-set category thumbnails from first product image
        $this->auto_set_category_thumbnails($category_ids, $product_id);
    }
    
    /**
     * Auto-set category thumbnails from first product image.
     * Only sets thumbnail if category doesn't have one already.
     *
     * @since    1.0.0
     * @param    array $category_ids Array of category term IDs
     * @param    int $product_id Product ID to get image from
     */
    private function auto_set_category_thumbnails($category_ids, $product_id) {
        if (empty($category_ids)) {
            return;
        }
        
        // Get product's featured image
        $thumbnail_id = get_post_thumbnail_id($product_id);
        
        if (!$thumbnail_id) {
            return; // Product has no image
        }
        
        foreach ($category_ids as $category_id) {
            // Check if category already has a thumbnail
            $existing_thumbnail = get_term_meta($category_id, 'thumbnail_id', true);
            
            if (empty($existing_thumbnail)) {
                // Category has no thumbnail, set it from product image
                update_term_meta($category_id, 'thumbnail_id', $thumbnail_id);
                
                $category = get_term($category_id, 'product_cat');
                $category_name = $category ? $category->name : "ID: $category_id";
                
                $this->log_debug('AUTO_CATEGORY_THUMBNAIL: Set from product', array(
                    'category_id' => $category_id,
                    'category_name' => $category_name,
                    'product_id' => $product_id,
                    'thumbnail_id' => $thumbnail_id
                ));
            }
        }
    }

    /**
     * Detect category separator in path string.
     * Supports: >, -, |, //, ->
     *
     * @param string $path Category path string
     * @return string|false Detected separator or false
     */
    private function detect_category_separator($path) {
        $separators = array('->', '//', '>', '-', '|');
        
        foreach ($separators as $sep) {
            if (strpos($path, $sep) !== false) {
                return $sep;
            }
        }
        
        return false;
    }

    /**
     * Create hierarchical category structure.
     *
     * @param string $path Full category path (e.g., "ZOO preces - Barības trauki - Doggy Village")
     * @param string $separator Separator character
     * @param bool $auto_create Whether to auto-create categories
     * @return int|false Category ID of the deepest level, or false on failure
     */
    private function create_category_hierarchy($path, $separator, $auto_create = true) {
        $elements = array_map('trim', explode($separator, $path));
        $elements = array_filter($elements); // Remove empty elements
        
        if (empty($elements)) {
            return false;
        }
        
        $this->log_debug('CREATE_CATEGORY_HIERARCHY: Starting', array(
            'full_path' => $path,
            'separator' => $separator,
            'elements' => $elements,
            'element_count' => count($elements)
        ));
        
        $parent_id = 0;
        $category_id = false;
        $hierarchy_path = array();
        
        foreach ($elements as $index => $category_name) {
            if (empty($category_name)) continue;
            
            $hierarchy_path[] = $category_name;
            $current_path = implode(' > ', $hierarchy_path);
            
            // Check if category exists with this parent
            $existing = get_term_by('name', $category_name, 'product_cat');
            
            if ($existing && $existing->parent == $parent_id) {
                // Category exists with correct parent
                $category_id = $existing->term_id;
                $this->log_debug('CATEGORY_EXISTS: Using existing category', array(
                    'level' => $index + 1,
                    'name' => $category_name,
                    'term_id' => $category_id,
                    'parent_id' => $parent_id,
                    'current_path' => $current_path
                ));
            } elseif ($existing && $existing->parent != $parent_id) {
                // Category exists but with different parent - check for duplicate
                $term_args = array(
                    'name' => $category_name,
                    'parent' => $parent_id,
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false
                );
                $matching_terms = get_terms($term_args);
                
                if (!empty($matching_terms)) {
                    $category_id = $matching_terms[0]->term_id;
                    $this->log_debug('CATEGORY_FOUND: Found matching category with correct parent', array(
                        'level' => $index + 1,
                        'name' => $category_name,
                        'term_id' => $category_id,
                        'parent_id' => $parent_id,
                        'current_path' => $current_path
                    ));
                } elseif ($auto_create) {
                    // Create with correct parent
                    $result = wp_insert_term($category_name, 'product_cat', array('parent' => $parent_id));
                    if (!is_wp_error($result)) {
                        $category_id = $result['term_id'];
                        $this->log_debug('CATEGORY_CREATED: New category with parent', array(
                            'level' => $index + 1,
                            'name' => $category_name,
                            'term_id' => $category_id,
                            'parent_id' => $parent_id,
                            'current_path' => $current_path
                        ));
                    } else {
                        $this->log_debug('CATEGORY_ERROR: Failed to create', array(
                            'level' => $index + 1,
                            'name' => $category_name,
                            'parent_id' => $parent_id,
                            'error' => $result->get_error_message()
                        ));
                    }
                }
            } elseif (!$existing && $auto_create) {
                // Create new category
                $result = wp_insert_term($category_name, 'product_cat', array('parent' => $parent_id));
                if (!is_wp_error($result)) {
                    $category_id = $result['term_id'];
                    $this->log_debug('CATEGORY_CREATED: New root category', array(
                        'level' => $index + 1,
                        'name' => $category_name,
                        'term_id' => $category_id,
                        'parent_id' => $parent_id,
                        'current_path' => $current_path
                    ));
                } else {
                    $this->log_debug('CATEGORY_ERROR: Failed to create root', array(
                        'level' => $index + 1,
                        'name' => $category_name,
                        'parent_id' => $parent_id,
                        'error' => $result->get_error_message()
                    ));
                }
            }
            
            // Move to next level
            if ($category_id) {
                $parent_id = $category_id;
            }
        }
        
        $this->log_debug('CREATE_CATEGORY_HIERARCHY: Completed', array(
            'full_path' => $path,
            'final_category_id' => $category_id,
            'final_path' => implode(' > ', $hierarchy_path)
        ));
        
        return $category_id;
    }

    /**
     * Set product tags.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    mixed $tags Tags (array or string)
     */
    /**
     * Set product tags.
     * Supports comma-separated tags and hierarchical separator detection.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    mixed $tags Tags (array or string)
     */
    private function set_product_tags($product_id, $tags) {
        if (is_string($tags)) {
            // First split by comma (for multiple tag entries)
            $tag_entries = array_map('trim', explode(',', $tags));
            $all_tags = array();
            
            foreach ($tag_entries as $tag_entry) {
                if (empty($tag_entry)) continue;
                
                // Check if this entry has hierarchical separators
                $separator = $this->detect_category_separator($tag_entry);
                
                if ($separator) {
                    // Split hierarchical path into individual tags
                    $elements = array_map('trim', explode($separator, $tag_entry));
                    foreach ($elements as $element) {
                        if (!empty($element) && $element !== '>' && $element !== '-') {
                            $all_tags[] = $element;
                        }
                    }
                    
                    $this->log_debug('TAGS_HIERARCHICAL: Split tag entry', array(
                        'entry' => $tag_entry,
                        'separator' => $separator,
                        'tags' => $elements
                    ));
                } else {
                    // Single tag
                    $all_tags[] = $tag_entry;
                }
            }
            
            $tags = array_unique($all_tags); // Remove duplicates
            
            $this->log_debug('TAGS_PROCESSED: Final tags list', array(
                'product_id' => $product_id,
                'tags' => $tags,
                'count' => count($tags)
            ));
        }

        if (!empty($tags)) {
            wp_set_object_terms($product_id, $tags, 'product_tag');
        }
    }

    /**
     * Set product brand.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    array $product_data Product data
     */
    private function save_product_identifiers($product_id, $product_data) {
        $field_mappings = $this->config['field_mapping'] ?? array();
        $primary_identifier = null;
        $primary_value = null;
        
        // Find which identifier is marked as primary
        $identifier_fields = array('ean', 'upc', 'isbn', 'mpn', 'gtin');
        foreach ($identifier_fields as $id_field) {
            if (isset($field_mappings[$id_field]['is_primary']) && 
                ($field_mappings[$id_field]['is_primary'] === '1' || $field_mappings[$id_field]['is_primary'] === 1)) {
                $primary_identifier = $id_field;
                break;
            }
        }
        
        // EAN - European Article Number
        if (!empty($product_data['ean'])) {
            update_post_meta($product_id, '_ean', sanitize_text_field($product_data['ean']));
            if ($primary_identifier === 'ean') {
                $primary_value = $product_data['ean'];
            }
        }
        
        // UPC - Universal Product Code
        if (!empty($product_data['upc'])) {
            update_post_meta($product_id, '_upc', sanitize_text_field($product_data['upc']));
            if ($primary_identifier === 'upc') {
                $primary_value = $product_data['upc'];
            }
        }
        
        // ISBN - International Standard Book Number
        if (!empty($product_data['isbn'])) {
            update_post_meta($product_id, '_isbn', sanitize_text_field($product_data['isbn']));
            if ($primary_identifier === 'isbn') {
                $primary_value = $product_data['isbn'];
            }
        }
        
        // MPN - Manufacturer Part Number
        if (!empty($product_data['mpn'])) {
            update_post_meta($product_id, '_mpn', sanitize_text_field($product_data['mpn']));
            if ($primary_identifier === 'mpn') {
                $primary_value = $product_data['mpn'];
            }
        }
        
        // GTIN - Global Trade Item Number
        if (!empty($product_data['gtin'])) {
            update_post_meta($product_id, '_gtin', sanitize_text_field($product_data['gtin']));
            if ($primary_identifier === 'gtin') {
                $primary_value = $product_data['gtin'];
            }
        }
        
        // Save primary identifier to WooCommerce standard field (_global_unique_id)
        if ($primary_value) {
            update_post_meta($product_id, '_global_unique_id', sanitize_text_field($primary_value));
            $this->log('debug', 'PRIMARY IDENTIFIER set for product ' . $product_id . ': ' . strtoupper($primary_identifier) . '=' . $primary_value);
        } elseif (!empty($product_data['gtin'])) {
            // Fallback: if no primary selected, use GTIN
            update_post_meta($product_id, '_global_unique_id', sanitize_text_field($product_data['gtin']));
        } elseif (!empty($product_data['ean'])) {
            // Fallback: if no GTIN, use EAN
            update_post_meta($product_id, '_global_unique_id', sanitize_text_field($product_data['ean']));
        }
        
        $this->log('debug', 'IDENTIFIERS saved for product ' . $product_id . ': EAN=' . ($product_data['ean'] ?? 'N/A') . ', UPC=' . ($product_data['upc'] ?? 'N/A') . ', Primary=' . ($primary_identifier ?? 'auto'));
    }

    /**
     * Set product brand.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    mixed $brand Brand (string or array)
     */
    private function set_product_brand($product_id, $brand) {
        $this->log('debug', 'SET_PRODUCT_BRAND called with: ' . print_r($brand, true));
        
        if (is_string($brand)) {
            $brand = trim($brand);
        }

        $this->log('debug', 'SET_PRODUCT_BRAND wp_set_object_terms with brand: ' . print_r($brand, true));
        $result = wp_set_object_terms($product_id, $brand, 'product_brand');
        $this->log('debug', 'SET_PRODUCT_BRAND result: ' . print_r($result, true));
    }

    /**
     * Set product SEO meta fields.
     * Supports Yoast SEO, Rank Math, and All in One SEO plugins.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    array $product_data Product data containing SEO fields
     */
    private function set_product_seo_meta($product_id, $product_data) {
        // Meta Title
        if (!empty($product_data['meta_title'])) {
            $meta_title = $product_data['meta_title'];
            
            // Yoast SEO
            if (defined('WPSEO_VERSION')) {
                update_post_meta($product_id, '_yoast_wpseo_title', $meta_title);
            }
            // Rank Math
            if (class_exists('RankMath')) {
                update_post_meta($product_id, 'rank_math_title', $meta_title);
            }
            // All in One SEO
            if (function_exists('aioseo')) {
                update_post_meta($product_id, '_aioseo_title', $meta_title);
            }
        }

        // Meta Description
        if (!empty($product_data['meta_description'])) {
            $meta_description = $product_data['meta_description'];
            
            // Yoast SEO
            if (defined('WPSEO_VERSION')) {
                update_post_meta($product_id, '_yoast_wpseo_metadesc', $meta_description);
            }
            // Rank Math
            if (class_exists('RankMath')) {
                update_post_meta($product_id, 'rank_math_description', $meta_description);
            }
            // All in One SEO
            if (function_exists('aioseo')) {
                update_post_meta($product_id, '_aioseo_description', $meta_description);
            }
        }

        // Meta Keywords (Focus Keyword for SEO plugins)
        if (!empty($product_data['meta_keywords'])) {
            $meta_keywords = $product_data['meta_keywords'];
            
            // Yoast SEO
            if (defined('WPSEO_VERSION')) {
                update_post_meta($product_id, '_yoast_wpseo_focuskw', $meta_keywords);
            }
            // Rank Math
            if (class_exists('RankMath')) {
                update_post_meta($product_id, 'rank_math_focus_keyword', $meta_keywords);
            }
            // All in One SEO
            if (function_exists('aioseo')) {
                update_post_meta($product_id, '_aioseo_keywords', $meta_keywords);
            }
        }
    }

    /**
     * Parse image placeholder syntax.
     * Supports: image* = all values, image[1] = first, image[2] = second, image = first value
     * Also supports old {field_name} syntax for backward compatibility
     * Example: image*, image[2], image, {image*}, {image[2]}, {image}
     *
     * @since    1.0.0
     * @param    string $template Template string with placeholders
     * @param    array $product_data Product data containing all XML fields
     * @return   array Array of image URLs
     */
    private function parse_image_placeholders($template, $product_data) {
        if (empty($template)) {
            return array();
        }

        $result_urls = array();
        
        // DEBUG: Log all image-related keys in product_data
        $image_related_keys = array_filter(array_keys($product_data), function($k) { 
            return stripos($k, 'image') !== false || stripos($k, 'bilde') !== false || stripos($k, 'attels') !== false;
        });
        $this->log('debug', "PARSE_IMAGE_PLACEHOLDERS: All image-related keys: " . implode(', ', $image_related_keys));
        
        // Split by comma, space, or both to support multiple entries like "{image1} {image2},{image3}"
        $entries = array_filter(array_map('trim', preg_split('/[\s,]+/', $template)));
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: Template='$template', entries=" . json_encode($entries)); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: product_data keys sample: " . implode(', ', array_slice(array_keys($product_data), 0, 30))); }
        
        foreach ($entries as $entry) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: Processing entry='$entry'"); }
            
            // field_name* or {field_name*} - all values from field_nameN (e.g., image1, image2, image3...)
            // Also checks for field_name.0, field_name.1 and field_name array
            if (preg_match('/^([a-zA-Z0-9_]+)\*$/', $entry, $matches) || preg_match('/^\{([a-zA-Z0-9_]+)\*\}$/', $entry, $matches)) {
                $field_base = $matches[1];
                $this->log('debug', "WILDCARD: Matched field_base='$field_base'");
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: Wildcard match for '$field_base*'"); }
                
                // FIRST: Check for numbered fields like image1, image2, image3... (common XML format)
                $numbered_values = array();
                $consecutive_empty = 0;
                for ($i = 1; $i <= 100; $i++) {
                    $numbered_key = $field_base . $i;
                    if (isset($product_data[$numbered_key])) {
                        $val = $this->extract_text_value($product_data[$numbered_key]);
                        if (!empty($val)) {
                            $numbered_values[] = $val;
                            $consecutive_empty = 0; // Reset empty counter
                            $this->log('debug', "WILDCARD: Found $numbered_key = " . substr($val, 0, 60));
                            if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: Found $numbered_key = " . substr($val, 0, 50)); }
                        } else {
                            // Field exists but is empty (like image5, image6 with no URL)
                            $consecutive_empty++;
                            $this->log('debug', "WILDCARD: $numbered_key is EMPTY (consecutive=$consecutive_empty)");
                            if ($consecutive_empty > 3) {
                                // Stop after 3 consecutive empty fields
                                break;
                            }
                        }
                    } else {
                        // Field doesn't exist at all - stop
                        $this->log('debug', "WILDCARD: $numbered_key NOT SET - stopping loop");
                        break;
                    }
                }
                
                $this->log('debug', "WILDCARD: Total numbered_values found: " . count($numbered_values));
                
                if (!empty($numbered_values)) {
                    $result_urls = array_merge($result_urls, $numbered_values);
                } else {
                    // FALLBACK: Try old format (field.0, field.1 or field array)
                    $field_values = $this->get_all_field_values($field_base, $product_data);
                    $result_urls = array_merge($result_urls, $field_values);
                }
            }
            // field_name[N] or {field_name[N]} - specific value by index (1-based)
            elseif (preg_match('/^([a-zA-Z0-9_]+)\[(\d+)\]$/', $entry, $matches) || preg_match('/^\{([a-zA-Z0-9_]+)\[(\d+)\]\}$/', $entry, $matches)) {
                $field_base = $matches[1];
                $index = intval($matches[2]);
                
                // First try numbered key (image1, image2...)
                $numbered_key = $field_base . $index;
                if (isset($product_data[$numbered_key]) && !empty($product_data[$numbered_key])) {
                    $val = $this->extract_text_value($product_data[$numbered_key]);
                    if (!empty($val)) {
                        $result_urls[] = $val;
                    }
                } else {
                    // Fallback to dot notation (0-based)
                    $field_key = $field_base . '.' . ($index - 1);
                    if (isset($product_data[$field_key])) {
                        $result_urls[] = $product_data[$field_key];
                    }
                }
            }
            // field_name or {field_name} - direct field access (e.g., {image1} gets product_data['image1'])
            elseif (preg_match('/^([a-zA-Z0-9_]+)$/', $entry, $matches) || preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $entry, $matches)) {
                $field_name = $matches[1];
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: Direct field match for '$field_name'"); }
                
                // Try exact field name first (e.g., 'image1')
                if (isset($product_data[$field_name]) && !empty($product_data[$field_name])) {
                    $val = $this->extract_text_value($product_data[$field_name]);
                    if (!empty($val)) {
                        $result_urls[] = $val;
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("PARSE_IMAGE_PLACEHOLDERS: Found $field_name = " . substr($val, 0, 50)); }
                    }
                }
                // Try field_name.0 (old format)
                elseif (isset($product_data[$field_name . '.0'])) {
                    $result_urls[] = $product_data[$field_name . '.0'];
                }
            }
            // Plain URL (no placeholder - starts with http)
            elseif (preg_match('/^https?:\/\//', $entry)) {
                $result_urls[] = $entry;
            }
        }

        // Remove empty values and return
        return array_filter(array_map('trim', $result_urls));
    }

    /**
     * Parse template string with {placeholder} syntax.
     * Replaces all {field.path} placeholders with actual values from product data.
     * Example: "{price.#text} {price.@attributes.currency}" becomes "1299.99 USD"
     *
     * @since    1.0.0
     * @param    string $template The template string with placeholders
     * @param    array  $product_data The product data array
     * @return   string The parsed string with placeholders replaced
     */
    private function parse_template_string($template, $product_data) {
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("TEMPLATE_PARSE: Input template: " . $template); }
        
        // Find all {placeholder} patterns - supports field.path, field.@attr, field.#text, etc.
        $result = preg_replace_callback(
            '/\{([^}]+)\}/',
            function($matches) use ($product_data) {
                $field_path = $matches[1];
                
                // Try to extract value using the appropriate parser
                if (!empty($this->xml_parser)) {
                    $value = $this->xml_parser->extract_field_value($product_data, $field_path);
                } elseif (!empty($this->csv_parser)) {
                    $value = $this->csv_parser->extract_field_value($product_data, $field_path);
                } else {
                    // Fallback: direct key access
                    $value = isset($product_data[$field_path]) ? $product_data[$field_path] : '';
                }
                
                // Handle array values
                if (is_array($value)) {
                    $value = $this->extract_text_value($value);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log("TEMPLATE_PARSE: Placeholder {" . $field_path . "} = " . print_r($value, true)); }
                
                return $value !== null ? $value : '';
            },
            $template
        );
        
        // Trim and clean up multiple spaces
        $result = trim(preg_replace('/\s+/', ' ', $result));
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("TEMPLATE_PARSE: Output result: " . $result); }
        
        return $result;
    }

    /**
     * Parse field placeholders in a template string for variation data.
     * Supports {field.path} syntax and direct field access.
     * Used for custom meta fields in variations where we work with simple arrays.
     *
     * @since    1.0.0
     * @param    string $template The template string with placeholders like {field_name}
     * @param    array  $var_data The variation data array
     * @return   string|null The parsed value or null if not found
     */
    private function parse_field_placeholders($template, $var_data) {
        if (empty($template) || !is_string($template)) {
            return null;
        }
        
        // Check if it's a placeholder template {field}
        if (preg_match('/^\{([^}]+)\}$/', trim($template), $matches)) {
            // Single placeholder - extract the field name
            $field_path = $matches[1];
            
            // Try direct access first
            if (isset($var_data[$field_path])) {
                $value = $var_data[$field_path];
                return is_array($value) ? $this->extract_text_value($value) : (string)$value;
            }
            
            // Try nested path with dots (e.g., "meta.supplier_id")
            if (strpos($field_path, '.') !== false) {
                $parts = explode('.', $field_path);
                $current = $var_data;
                
                foreach ($parts as $part) {
                    if (is_array($current) && isset($current[$part])) {
                        $current = $current[$part];
                    } else {
                        return null;
                    }
                }
                
                return is_array($current) ? $this->extract_text_value($current) : (string)$current;
            }
            
            return null;
        }
        
        // Multiple placeholders in template string
        if (strpos($template, '{') !== false) {
            $result = preg_replace_callback(
                '/\{([^}]+)\}/',
                function($matches) use ($var_data) {
                    $field_path = $matches[1];
                    
                    // Try direct access
                    if (isset($var_data[$field_path])) {
                        $value = $var_data[$field_path];
                        return is_array($value) ? $this->extract_text_value($value) : (string)$value;
                    }
                    
                    // Try nested path
                    if (strpos($field_path, '.') !== false) {
                        $parts = explode('.', $field_path);
                        $current = $var_data;
                        
                        foreach ($parts as $part) {
                            if (is_array($current) && isset($current[$part])) {
                                $current = $current[$part];
                            } else {
                                return '';
                            }
                        }
                        
                        return is_array($current) ? $this->extract_text_value($current) : (string)$current;
                    }
                    
                    return '';
                },
                $template
            );
            
            return trim($result);
        }
        
        // No placeholders - treat as direct field name
        if (isset($var_data[$template])) {
            $value = $var_data[$template];
            return is_array($value) ? $this->extract_text_value($value) : (string)$value;
        }
        
        return null;
    }

    /**
     * Extract text value from various XML/array structures.
     * Handles: string, ['@content' => 'text'], ['@attributes' => [...], '@content' => 'text'], 
     * or SimpleXML-style arrays where text is nested.
     *
     * @since    1.0.0
     * @param    mixed $value The value to extract text from
     * @return   string|null The extracted text value or null if not found
     */
    private function extract_text_value($value) {
        // Already a string
        if (is_string($value)) {
            return $value;
        }
        
        // Not an array - convert to string if possible
        if (!is_array($value)) {
            return is_scalar($value) ? (string)$value : null;
        }
        
        // Check for #text key (XML text content from our parser)
        if (isset($value['#text'])) {
            return is_string($value['#text']) ? $value['#text'] : null;
        }
        
        // Check for @content key (alternative XML parsing style)
        if (isset($value['@content'])) {
            return is_string($value['@content']) ? $value['@content'] : null;
        }
        
        // Check for 0 key with string value (common in SimpleXML)
        if (isset($value[0]) && is_string($value[0])) {
            return $value[0];
        }
        
        // Look for any string value that looks like a URL or text content
        // Skip @attributes key
        foreach ($value as $key => $subvalue) {
            if ($key === '@attributes') {
                continue;
            }
            if (is_string($subvalue) && !empty($subvalue)) {
                // Prefer values that look like URLs for image fields
                if (filter_var($subvalue, FILTER_VALIDATE_URL)) {
                    return $subvalue;
                }
            }
        }
        
        // Last resort - get first non-attribute string value
        foreach ($value as $key => $subvalue) {
            if ($key === '@attributes') {
                continue;
            }
            if (is_string($subvalue)) {
                return $subvalue;
            }
            // Recurse one level
            if (is_array($subvalue)) {
                $extracted = $this->extract_text_value($subvalue);
                if ($extracted !== null) {
                    return $extracted;
                }
            }
        }
        
        return null;
    }

    /**
     * Get all values for a field base name (e.g., "image" returns all image.0, image.1, etc.)
     *
     * @since    1.0.0
     * @param    string $field_base Base field name
     * @param    array $product_data Product data
     * @return   array Array of field values
     */
    private function get_all_field_values($field_base, $product_data) {
        $values = array();
        
        // Safety check - ensure we have valid inputs
        if (!is_string($field_base) || empty($field_base)) {
            $this->log('warning', "get_all_field_values: Invalid field_base provided");
            return $values;
        }
        
        if (!is_array($product_data)) {
            $this->log('warning', "get_all_field_values: product_data is not an array");
            return $values;
        }
        
        $this->log('debug', "get_all_field_values: Looking for field '$field_base'");
        $this->log('debug', "Available keys sample: " . implode(', ', array_slice(array_keys($product_data), 0, 30)));
        
        // ZERO: Handle nested path like "eans.ean" or "package_dimensions.width"
        if (strpos($field_base, '.') !== false && !preg_match('/\.\d+$/', $field_base)) {
            $path_parts = explode('.', $field_base);
            $current = $product_data;
            foreach ($path_parts as $part) {
                if (is_array($current) && isset($current[$part])) {
                    $current = $current[$part];
                } else {
                    $current = null;
                    break;
                }
            }
            if ($current !== null) {
                $extracted = $this->extract_text_value($current);
                if ($extracted !== null && is_string($extracted)) {
                    $this->log('debug', "Found nested value for '$field_base': " . substr($extracted, 0, 50));
                    return [$extracted];
                } elseif (is_array($current) && isset($current[0])) {
                    // Array of values
                    foreach ($current as $index => $val) {
                        $ext = $this->extract_text_value($val);
                        if ($ext !== null && is_string($ext)) {
                            $values[$index] = $ext;
                        }
                    }
                    if (!empty($values)) {
                        ksort($values);
                        return array_values($values);
                    }
                }
            }
        }
        
        // FIRST: Check if field exists as PHP array directly
        if (isset($product_data[$field_base]) && is_array($product_data[$field_base])) {
            // Check if it's an indexed array [0, 1, 2...]
            if (isset($product_data[$field_base][0])) {
                $this->log('debug', "Found PHP indexed array for '$field_base' with " . count($product_data[$field_base]) . " items");
                foreach ($product_data[$field_base] as $index => $value) {
                    if (!empty($value)) {
                        // Handle XML elements with attributes: ['@attributes' => [...], '@content' => 'actual value']
                        // Or SimpleXML style: element text is in @content or as direct value
                        $extracted = $this->extract_text_value($value);
                        
                        // Ensure extracted is a string for logging
                        $log_value = is_string($extracted) ? substr($extracted, 0, 100) : '[complex value]';
                        $this->log('debug', "  [$index] = " . $log_value);
                        
                        if (is_string($extracted) && !empty($extracted)) {
                            $values[$index] = $extracted;
                        }
                    }
                }
            } else {
                // Associative array - could be single element with attributes
                $this->log('debug', "Found associative array for '$field_base'");
                $extracted = $this->extract_text_value($product_data[$field_base]);
                if (!empty($extracted) && is_string($extracted)) {
                    $values[0] = $extracted;
                }
            }
        }
        // SECOND: Check if field exists as simple value
        elseif (isset($product_data[$field_base]) && !is_array($product_data[$field_base])) {
            $this->log('debug', "Found simple value for '$field_base'");
            $values[0] = $product_data[$field_base];
        }
        // THIRD: Legacy - try dot notation (field.0, field.1)
        else {
            $this->log('debug', "Trying legacy dot notation for '$field_base'");
            foreach ($product_data as $key => $value) {
                if (preg_match('/^' . preg_quote($field_base, '/') . '\.(\d+)$/', $key, $matches)) {
                    $index = intval($matches[1]);
                    $extracted = $this->extract_text_value($value);
                    if (is_string($extracted)) {
                        $this->log('debug', "  Found: $key = " . substr($extracted, 0, 100));
                        $values[$index] = $extracted;
                    }
                }
            }
        }
        
        $this->log('debug', "get_all_field_values: Found " . count($values) . " values for '$field_base'");
        
        // Sort by index and return values
        ksort($values);
        return array_values($values);
    }

    /**
     * Set product images.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    array $product_data Product data containing image URLs
     */
    private function set_product_images($product_id, $product_data) {
        $this->log('debug', '=== SET_PRODUCT_IMAGES CALLED ===');
        $this->log('debug', 'Product ID: ' . $product_id);
        $this->log('debug', 'images field: ' . print_r($product_data['images'] ?? 'NOT SET', true));
        $this->log('debug', 'featured_image field: ' . print_r($product_data['featured_image'] ?? 'NOT SET', true));
        
        $image_urls = array();

        // Process 'images' field with placeholder support
        if (!empty($product_data['images'])) {
            $this->log('debug', 'Processing images field...');
            if (is_string($product_data['images'])) {
                $this->log('debug', 'Images is string: ' . $product_data['images']);
                // DEBUG: Log current_raw_product_data image keys
                $raw_image_keys = array_filter(array_keys($this->current_raw_product_data), function($k) { return strpos($k, 'image') !== false; });
                $this->log('debug', 'current_raw_product_data image keys: ' . implode(', ', $raw_image_keys));
                $this->log('debug', 'image1 value: ' . ($this->current_raw_product_data['image1'] ?? 'NOT SET'));
                $this->log('debug', 'image2 value: ' . ($this->current_raw_product_data['image2'] ?? 'NOT SET'));
                $this->log('debug', 'image3 value: ' . ($this->current_raw_product_data['image3'] ?? 'NOT SET'));
                // Parse placeholders like image*, image[1] against RAW product data
                $parsed_images = $this->parse_image_placeholders($product_data['images'], $this->current_raw_product_data);
                $this->log('debug', 'Parsed ' . count($parsed_images) . ' images');
                if (!empty($parsed_images)) {
                    $this->log('debug', 'Parsed image URLs: ' . implode(', ', array_map(function($u) { return substr($u, 0, 60); }, $parsed_images)));
                }
                $image_urls = array_merge($image_urls, $parsed_images);
            } else {
                $this->log('debug', 'Images is array');
                $additional_images = (array)$product_data['images'];
                $image_urls = array_merge($image_urls, $additional_images);
            }
        } else {
            $this->log('warning', 'Images field is EMPTY!');
        }

        // Process 'featured_image' field with placeholder support
        if (!empty($product_data['featured_image'])) {
            $this->log('debug', 'Featured image present: ' . $product_data['featured_image']);
            
            // Check if it's a placeholder template like {image2}
            if (is_string($product_data['featured_image']) && strpos($product_data['featured_image'], '{') !== false) {
                $this->log('debug', 'Featured image is placeholder template, parsing...');
                $parsed_featured = $this->parse_image_placeholders($product_data['featured_image'], $this->current_raw_product_data);
                if (!empty($parsed_featured)) {
                    $this->log('debug', 'Parsed featured image URL: ' . $parsed_featured[0]);
                    // If featured_image is specified, it takes priority as first image
                    array_unshift($image_urls, $parsed_featured[0]);
                } else {
                    $this->log('warning', 'Failed to parse featured_image placeholder!');
                }
            } else {
                // Direct URL - use as-is
                array_unshift($image_urls, $product_data['featured_image']);
            }
        } else {
            $this->log('warning', 'Featured image field is EMPTY!');
        }
        
        $this->log('debug', 'Total image URLs: ' . count($image_urls));
        
        // If no featured_image but we have images, first image becomes featured
        // (This is already handled by the order above)

        if (empty($image_urls)) {
            $this->log('warning', 'NO IMAGE URLS - exiting set_product_images');
            return;
        }

        // Use parallel download for better performance (5 concurrent downloads)
        $this->log('debug', 'Starting PARALLEL image download for ' . count($image_urls) . ' URLs');
        $attachment_ids = $this->download_images_parallel($image_urls, $product_id, 5);

        if (!empty($attachment_ids)) {
            $this->log('debug', 'Downloaded ' . count($attachment_ids) . ' images successfully');
            
            // Set featured image
            set_post_thumbnail($product_id, $attachment_ids[0]);
            $this->log('debug', 'Set featured image: ' . $attachment_ids[0]);

            // Set gallery images
            if (count($attachment_ids) > 1) {
                $gallery_ids = implode(',', array_slice($attachment_ids, 1));
                update_post_meta($product_id, '_product_image_gallery', $gallery_ids);
                $this->log('debug', 'Set gallery images: ' . $gallery_ids);
            } else {
                $this->log('debug', 'Only 1 image - no gallery');
            }
        } else {
            $this->log('error', 'NO ATTACHMENTS downloaded!');
        }
    }

    /**
     * Check if an image already exists in the media library by URL.
     * This prevents re-downloading the same image multiple times.
     *
     * @since    1.0.0
     * @param    string $image_url Image URL to check
     * @return   int|false Attachment ID if exists, false otherwise
     */
    private function get_existing_attachment_by_url($image_url) {
        global $wpdb;
        
        // Method 1: Check by _source_url meta (set during import)
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_source_url' AND meta_value = %s LIMIT 1",
            $image_url
        ));
        
        if ($attachment_id) {
            // Verify attachment still exists
            if (get_post($attachment_id)) {
                return intval($attachment_id);
            }
        }
        
        // Method 2: Check by filename in guid (less reliable but catches manual uploads)
        $filename = sanitize_file_name(pathinfo($image_url, PATHINFO_FILENAME));
        if (!empty($filename) && strlen($filename) > 3) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' 
                AND post_status = 'inherit'
                AND guid LIKE %s 
                LIMIT 1",
                '%' . $wpdb->esc_like($filename) . '%'
            ));
            
            if ($attachment_id && get_post($attachment_id)) {
                return intval($attachment_id);
            }
        }
        
        return false;
    }

    /**
     * Download multiple images in parallel using curl_multi.
     * This significantly speeds up imports with multiple images per product.
     *
     * @since    1.0.0
     * @param    array $image_urls Array of image URLs
     * @param    int $product_id Product ID
     * @param    int $max_concurrent Maximum concurrent downloads (default 5)
     * @return   array Array of attachment IDs (preserving order)
     */
    private function download_images_parallel($image_urls, $product_id, $max_concurrent = 5) {
        // Include required files
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_ids = array();
        $temp_files = array();
        $urls_to_download = array();
        
        // Filter valid URLs and check for existing attachments first
        $valid_urls = array();
        $skipped_count = 0;
        
        foreach ($image_urls as $index => $url) {
            $url = trim($url);
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                // Check if this image already exists in media library
                $existing_id = $this->get_existing_attachment_by_url($url);
                if ($existing_id) {
                    $attachment_ids[$index] = $existing_id;
                    $skipped_count++;
                    $this->log('debug', 'Image already exists (ID: ' . $existing_id . '), skipping download: ' . basename($url));
                } else {
                    $urls_to_download[$index] = $url;
                }
            }
        }
        
        if ($skipped_count > 0) {
            $this->log('debug', 'Skipped ' . $skipped_count . ' images (already in media library)');
        }
        
        // If all images already exist, just return them
        if (empty($urls_to_download)) {
            $this->log('debug', 'All ' . count($attachment_ids) . ' images already exist - no downloads needed!');
            ksort($attachment_ids);
            return array_values($attachment_ids);
        }
        
        $this->log('debug', 'Parallel download starting for ' . count($urls_to_download) . ' NEW images (max ' . $max_concurrent . ' concurrent)');
        
        // Process in batches if more than max_concurrent
        $url_chunks = array_chunk($urls_to_download, $max_concurrent, true);
        
        foreach ($url_chunks as $chunk) {
            $mh = curl_multi_init();
            $curl_handles = array();
            $chunk_temp_files = array();
            
            // Initialize curl handles for each URL in this chunk
            foreach ($chunk as $index => $url) {
                // Create temp file
                $temp_file = wp_tempnam(basename($url));
                $fp = fopen($temp_file, 'w');
                
                if (!$fp) {
                    $this->log('warning', 'Failed to create temp file for: ' . $url);
                    continue;
                }
                
                $ch = curl_init();
                curl_setopt_array($ch, array(
                    CURLOPT_URL => $url,
                    CURLOPT_FILE => $fp,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WooCommerce Import)',
                    CURLOPT_HTTPHEADER => array(
                        'Accept: image/*,*/*',
                    ),
                ));
                
                curl_multi_add_handle($mh, $ch);
                $curl_handles[$index] = array(
                    'handle' => $ch,
                    'fp' => $fp,
                    'temp_file' => $temp_file,
                    'url' => $url
                );
            }
            
            // Execute all handles in parallel
            $running = null;
            do {
                $status = curl_multi_exec($mh, $running);
                if ($running) {
                    curl_multi_select($mh);
                }
            } while ($running > 0 && $status === CURLM_OK);
            
            // Process results
            foreach ($curl_handles as $index => $data) {
                fclose($data['fp']);
                
                $http_code = curl_getinfo($data['handle'], CURLINFO_HTTP_CODE);
                $error = curl_error($data['handle']);
                
                curl_multi_remove_handle($mh, $data['handle']);
                curl_close($data['handle']);
                
                if ($http_code !== 200 || !empty($error) || !file_exists($data['temp_file']) || filesize($data['temp_file']) < 100) {
                    $this->log('warning', 'Failed to download image ' . ($index + 1) . ': ' . $data['url'] . ' (HTTP: ' . $http_code . ')');
                    @unlink($data['temp_file']);
                    continue;
                }
                
                // Store for attachment processing
                $chunk_temp_files[$index] = array(
                    'temp_file' => $data['temp_file'],
                    'url' => $data['url']
                );
            }
            
            curl_multi_close($mh);
            
            // Now process downloaded files into attachments (this part is sequential but fast)
            foreach ($chunk_temp_files as $index => $data) {
                try {
                    $file_info = wp_check_filetype(basename($data['url']));
                    
                    // If no extension detected from URL, try to detect from content
                    if (empty($file_info['ext'])) {
                        $mime_type = mime_content_type($data['temp_file']);
                        $ext_map = array(
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/webp' => 'webp'
                        );
                        $file_info['ext'] = $ext_map[$mime_type] ?? 'jpg';
                        $file_info['type'] = $mime_type;
                    }
                    
                    // Prepare file array
                    $file_array = array(
                        'name' => sanitize_file_name(pathinfo($data['url'], PATHINFO_FILENAME)) . '.' . $file_info['ext'],
                        'tmp_name' => $data['temp_file']
                    );
                    
                    // Upload file
                    $attachment_id = media_handle_sideload($file_array, $product_id);
                    
                    // Clean up temp file
                    @unlink($data['temp_file']);
                    
                    if (is_wp_error($attachment_id)) {
                        $this->log('warning', 'Failed to attach image ' . ($index + 1) . ': ' . $attachment_id->get_error_message());
                        continue;
                    }
                    
                    // Store source URL for future duplicate detection
                    update_post_meta($attachment_id, '_source_url', $data['url']);
                    
                    $attachment_ids[$index] = $attachment_id;
                    $this->log('debug', 'Parallel downloaded image ' . ($index + 1) . ', attachment ID: ' . $attachment_id);
                    
                } catch (Exception $e) {
                    $this->log('warning', 'Error processing image ' . ($index + 1) . ': ' . $e->getMessage());
                    @unlink($data['temp_file']);
                }
            }
        }
        
        // Sort by original index to preserve order
        ksort($attachment_ids);
        
        $this->log('debug', 'Parallel download completed: ' . count($attachment_ids) . ' images (downloaded: ' . count($urls_to_download) . ', cached: ' . $skipped_count . ')');
        
        return array_values($attachment_ids);
    }

    /**
     * Download and attach image to product.
     *
     * @since    1.0.0
     * @param    string $image_url Image URL
     * @param    int $product_id Product ID
     * @return   int|false Attachment ID or false on failure
     */
    private function download_and_attach_image($image_url, $product_id) {
        // Include required files
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download image
        $temp_file = download_url($image_url);

        if (is_wp_error($temp_file)) {
            throw new Exception($temp_file->get_error_message());
        }

        // Get file info
        $file_info = wp_check_filetype(basename($image_url));
        if (!$file_info['ext']) {
            unlink($temp_file);
            throw new Exception(__('Invalid image file type.', 'bootflow-woocommerce-xml-csv-importer'));
        }

        // Prepare file array
        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $temp_file
        );

        // Upload file
        $attachment_id = media_handle_sideload($file_array, $product_id);

        // Clean up temp file
        @unlink($temp_file);

        if (is_wp_error($attachment_id)) {
            throw new Exception($attachment_id->get_error_message());
        }

        // Store source URL for future duplicate detection
        update_post_meta($attachment_id, '_source_url', $image_url);

        return $attachment_id;
    }

    /**
     * Process custom fields.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    array $product_data Raw product data
     */
    private function process_custom_fields($product_id, $product_data) {
        // Check if custom_fields exists in config
        if (!isset($this->config['custom_fields'])) {
            return;
        }
        
        $custom_fields = $this->config['custom_fields'];
        
        // If it's a string, decode it
        if (is_string($custom_fields)) {
            $custom_fields = json_decode($custom_fields, true);
        }

        if (empty($custom_fields) || !is_array($custom_fields)) {
            return;
        }

        foreach ($custom_fields as $field_config) {
            if (empty($field_config['name']) || empty($field_config['source'])) {
                continue;
            }

            $field_name = sanitize_key($field_config['name']);
            $source_field = $field_config['source'];

            // Extract value
            if ($this->config['file_type'] === 'xml') {
                $value = $this->xml_parser->extract_field_value($product_data, $source_field);
            } else {
                $value = $this->csv_parser->extract_field_value($product_data, $source_field);
            }

            if ($value !== null) {
                // Process value if configured
                if (isset($field_config['processing_mode']) && $field_config['processing_mode'] !== 'direct') {
                    $value = $this->processor->process_field($value, $field_config, $product_data);
                }

                // Save as meta
                update_post_meta($product_id, '_' . $field_name, $value);
            }
        }
    }

    /**
     * Process product attributes & variations.
     *
     * @since    1.0.0
     * @param    int $product_id Product ID
     * @param    array $product_data Raw product data
     */
    private function process_product_attributes($product_id, $product_data) {
        $log_file = WP_CONTENT_DIR . '/attributes_debug.log';
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, date('Y-m-d H:i:s') . " - PROCESS_ATTRIBUTES called for product: $product_id\n", FILE_APPEND); }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESS_ATTRIBUTES called for product ID: ' . $product_id); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Config keys: ' . implode(', ', array_keys($this->config))); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ field_mapping keys: ' . implode(', ', array_keys($this->config['field_mapping'] ?? []))); }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Config keys: " . implode(', ', array_keys($this->config)) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "field_mapping keys: " . implode(', ', array_keys($this->config['field_mapping'] ?? [])) . "\n", FILE_APPEND); }
        
        // Check if attributes configuration exists
        if (!isset($this->config['field_mapping']['attributes_variations'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "ERROR: No attributes_variations config found!\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ No attributes_variations config found'); }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "attributes_variations FOUND!\n", FILE_APPEND); }
        
        $attributes_config = $this->config['field_mapping']['attributes_variations'];
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "attributes_config type: " . gettype($attributes_config) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ attributes_config type: ' . gettype($attributes_config)); }
        
        // If it's a string, decode it
        if (is_string($attributes_config)) {
            $attributes_config = json_decode($attributes_config, true);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Decoded from string\n", FILE_APPEND); }
        }

        // ═══════════════════════════════════════════════════════════════════════════
        // NORMALIZE FIRST: Convert new UI format to old format BEFORE any checks!
        // This ensures variation_attributes → attributes conversion happens early
        // ═══════════════════════════════════════════════════════════════════════════
        $product_mode = $attributes_config['product_mode'] ?? null;
        $variation_mode = $attributes_config['variation_mode'] ?? 'auto';
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Initial product_mode: " . ($product_mode ?? 'null') . ", variation_mode: $variation_mode\n", FILE_APPEND); }
        
        // If new product_mode is set, convert to old format for compatibility
        if ($product_mode === 'variable') {
            $variation_mode = 'map';
            // Convert new variation_path to old map_config format
            if (!empty($attributes_config['variation_path']) && empty($attributes_config['map_config']['container_xpath'])) {
                $attributes_config['map_config'] = $attributes_config['map_config'] ?? array();
                $attributes_config['map_config']['container_xpath'] = $attributes_config['variation_path'];
            }
            // Convert new variation_fields to old map_config format
            if (!empty($attributes_config['variation_fields']) && is_array($attributes_config['variation_fields'])) {
                $vf = $attributes_config['variation_fields'];
                $attributes_config['map_config']['sku_source'] = $vf['sku'] ?? '';
                $attributes_config['map_config']['parent_sku_source'] = $vf['parent_sku'] ?? '';
                $attributes_config['map_config']['price_source'] = $vf['regular_price'] ?? '';
                $attributes_config['map_config']['sale_price_source'] = $vf['sale_price'] ?? '';
                $attributes_config['map_config']['sale_price_dates_from_source'] = $vf['sale_price_dates_from'] ?? '';
                $attributes_config['map_config']['sale_price_dates_to_source'] = $vf['sale_price_dates_to'] ?? '';
                $attributes_config['map_config']['stock_source'] = $vf['stock_quantity'] ?? '';
                $attributes_config['map_config']['stock_status_source'] = $vf['stock_status'] ?? '';
                $attributes_config['map_config']['manage_stock_source'] = $vf['manage_stock'] ?? '';
                $attributes_config['map_config']['low_stock_amount_source'] = $vf['low_stock_amount'] ?? '';
                $attributes_config['map_config']['backorders_source'] = $vf['backorders'] ?? '';
                $attributes_config['map_config']['backorders_default'] = $vf['backorders_default'] ?? '';
                $attributes_config['map_config']['weight_source'] = $vf['weight'] ?? '';
                $attributes_config['map_config']['weight_inherit'] = !empty($vf['weight_inherit']);
                $attributes_config['map_config']['length_source'] = $vf['length'] ?? '';
                $attributes_config['map_config']['width_source'] = $vf['width'] ?? '';
                $attributes_config['map_config']['height_source'] = $vf['height'] ?? '';
                $attributes_config['map_config']['dimensions_inherit'] = !empty($vf['dimensions_inherit']);
                $attributes_config['map_config']['description_source'] = $vf['description'] ?? '';
                $attributes_config['map_config']['shipping_class_source'] = $vf['shipping_class'] ?? '';
                $attributes_config['map_config']['image_source'] = $vf['image'] ?? '';
                $attributes_config['map_config']['virtual_source'] = $vf['virtual'] ?? '';
                $attributes_config['map_config']['downloadable_source'] = $vf['downloadable'] ?? '';
                $attributes_config['map_config']['download_limit_source'] = $vf['download_limit'] ?? '';
                $attributes_config['map_config']['download_expiry_source'] = $vf['download_expiry'] ?? '';
                $attributes_config['map_config']['downloads_source'] = $vf['downloads'] ?? '';
                // Product identifiers
                $attributes_config['map_config']['ean_source'] = $vf['ean'] ?? '';
                $attributes_config['map_config']['upc_source'] = $vf['upc'] ?? '';
                $attributes_config['map_config']['gtin_source'] = $vf['gtin'] ?? '';
                $attributes_config['map_config']['isbn_source'] = $vf['isbn'] ?? '';
                $attributes_config['map_config']['mpn_source'] = $vf['mpn'] ?? '';
            }
            // Pass custom meta fields for variations (can come as 'var_meta' or 'variation_meta' from JS)
            $var_meta = $attributes_config['var_meta'] ?? $attributes_config['variation_meta'] ?? array();
            if (!empty($var_meta) && is_array($var_meta)) {
                $attributes_config['map_config']['var_meta'] = $var_meta;
            }
            // Convert new variation_attributes to old attributes format
            if (!empty($attributes_config['variation_attributes']) && is_array($attributes_config['variation_attributes'])) {
                $attributes_config['attributes'] = array();
                foreach ($attributes_config['variation_attributes'] as $varAttr) {
                    $attributes_config['attributes'][] = array(
                        'name' => $varAttr['name'] ?? '',
                        'values_source' => $varAttr['source'] ?? '',
                        'array_index' => isset($varAttr['array_index']) ? $varAttr['array_index'] : null,
                        'visible' => 1,
                        'used_for_variations' => 1
                    );
                }
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Converted " . count($attributes_config['variation_attributes']) . " variation_attributes to attributes\n", FILE_APPEND); }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "✓ Converted new UI format: product_mode=variable → variation_mode=map, path=" . ($attributes_config['map_config']['container_xpath'] ?? 'N/A') . "\n", FILE_APPEND); }
        } elseif ($product_mode === 'attributes' || $product_mode === 'simple') {
            // Simple or attributes mode - no variations
            $variation_mode = 'none';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Product mode: $product_mode - no variations\n", FILE_APPEND); }
        }
        
        $attributes_config['variation_mode'] = $variation_mode;

        // ═══════════════════════════════════════════════════════════════════════════
        // NOW check for attributes (after normalization)
        // ═══════════════════════════════════════════════════════════════════════════
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "After normalization - checking attributes array... isset=" . (isset($attributes_config['attributes']) ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "is_array=" . (is_array($attributes_config['attributes'] ?? null) ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "empty=" . (empty($attributes_config['attributes']) ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        
        // Also check for display_attributes and attribute_pairs (key-value mode)
        $has_variation_attributes_config = !empty($attributes_config['attributes']) && is_array($attributes_config['attributes']);
        $has_display_attributes_config = !empty($attributes_config['display_attributes']) && is_array($attributes_config['display_attributes']);
        $has_attribute_pairs_config = !empty($attributes_config['attribute_pairs']) && is_array($attributes_config['attribute_pairs']);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Has variation attributes config: " . ($has_variation_attributes_config ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Has display attributes config: " . ($has_display_attributes_config ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Has attribute pairs config (key-value): " . ($has_attribute_pairs_config ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        
        // Only return if ALL are empty (and not variable product mode)
        if (!$has_variation_attributes_config && !$has_display_attributes_config && !$has_attribute_pairs_config && $product_mode !== 'variable') {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "ERROR: No attributes, display_attributes or attribute_pairs in config!\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ No attributes, display_attributes or attribute_pairs in config'); }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Attributes array OK! Count: " . count($attributes_config['attributes'] ?? []) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Display attributes array OK! Count: " . count($attributes_config['display_attributes'] ?? []) . "\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Attribute pairs array OK! Count: " . count($attributes_config['attribute_pairs'] ?? []) . "\n", FILE_APPEND); }

        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PROCESSING ATTRIBUTES for product ID: ' . $product_id); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Attributes config: ' . print_r($attributes_config, true)); }

        $product = wc_get_product($product_id);
        if (!$product) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "ERROR: Could not load product!\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Could not load product ID: ' . $product_id); }
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Product loaded OK\n", FILE_APPEND); }
        $map_config = $attributes_config['map_config'] ?? array();
        
        // Check variation mode - if MAP mode, auto-detect attributes from variations
        
        // AUTO-DETECT ATTRIBUTES FROM VARIATIONS (when in MAP mode)
        if ($variation_mode === 'map') {
            $container_xpath = $map_config['container_xpath'] ?? 'variations.variation';
            $variations_data = $this->get_nested_value($product_data, $container_xpath);
            
            if (!empty($variations_data)) {
                // Ensure array format
                if (!isset($variations_data[0])) {
                    $variations_data = array($variations_data);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "🔍 MAP MODE: Auto-detecting attributes from " . count($variations_data) . " variations\n", FILE_APPEND); }
                
                // Check if we need to auto-detect attributes:
                // 1. No valid attributes configured, OR
                // 2. Configured attributes don't exist in THIS product's variations
                $need_auto_detect = true;
                $first_variation = $variations_data[0];
                $first_var_attrs = $this->get_nested_value($first_variation, 'attributes');
                
                foreach ($attributes_config['attributes'] as $attr) {
                    if (!empty($attr['name'])) {
                        // Check if this attribute name (lowercase) exists in the variation's attributes
                        $attr_key = strtolower(str_replace(' ', '_', $attr['name']));
                        
                        if (!empty($first_var_attrs) && is_array($first_var_attrs)) {
                            // Check for attribute in various formats
                            $found = false;
                            foreach (array_keys($first_var_attrs) as $key) {
                                if (strtolower($key) === $attr_key) {
                                    $found = true;
                                    break;
                                }
                            }
                            if ($found) {
                                $need_auto_detect = false;
                                break;
                            }
                        }
                    }
                }
                
                if ($need_auto_detect && defined('WP_DEBUG') && WP_DEBUG) { 
                    file_put_contents($log_file, "  - Configured attributes not found in this product's variations, auto-detecting...\n", FILE_APPEND); 
                }
                
                if ($need_auto_detect) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - No valid attributes configured, auto-detecting from first variation\n", FILE_APPEND); }
                    
                    // Get attributes from first variation
                    $first_variation = $variations_data[0];
                    $attrs_data = $this->get_nested_value($first_variation, 'attributes');
                    
                    if (!empty($attrs_data) && is_array($attrs_data)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Found attributes element with keys: " . implode(', ', array_keys($attrs_data)) . "\n", FILE_APPEND); }
                        
                        // Create attributes config from detected keys
                        $auto_attributes = array();
                        foreach (array_keys($attrs_data) as $attr_key) {
                            // Skip numeric keys and special elements
                            if (is_numeric($attr_key) || $attr_key === '@attributes') {
                                continue;
                            }
                            
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Auto-adding attribute: $attr_key\n", FILE_APPEND); }
                            
                            // Collect all values for this attribute from all variations
                            $all_values = array();
                            foreach ($variations_data as $var) {
                                $var_attrs = $this->get_nested_value($var, 'attributes');
                                if (isset($var_attrs[$attr_key]) && !empty($var_attrs[$attr_key])) {
                                    $all_values[] = $var_attrs[$attr_key];
                                }
                            }
                            $all_values = array_unique($all_values);
                            
                            $auto_attributes[] = array(
                                'name' => ucfirst($attr_key),  // Capitalize for display
                                'xml_attribute_key' => $attr_key,
                                'values_source' => '',
                                'visible' => 1,
                                'used_for_variations' => 1,
                                '_auto_values' => $all_values  // Pre-collected values
                            );
                            
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Values: " . implode(', ', $all_values) . "\n", FILE_APPEND); }
                        }
                        
                        // Replace empty attributes with auto-detected ones
                        if (!empty($auto_attributes)) {
                            $attributes_config['attributes'] = $auto_attributes;
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✓ Auto-detected " . count($auto_attributes) . " attributes\n", FILE_APPEND); }
                        }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✗ No 'attributes' element found in variation\n", FILE_APPEND); }
                    }
                }
            }
        }
        
        // CRITICAL: Check if any attributes are marked as "used for variations"
        // AND if actual variation data exists in XML/CSV
        $has_variation_attributes = false;
        foreach ($attributes_config['attributes'] as $attr_config) {
            if (isset($attr_config['used_for_variations']) && $attr_config['used_for_variations'] == 1) {
                $has_variation_attributes = true;
                break;
            }
        }
        
        // Check if actual variation data exists in product_data
        // This prevents creating empty variable products when XML has no <variations> block
        $has_actual_variations = false;
        if ($has_variation_attributes && $variation_mode === 'map') {
            $map_config = $attributes_config['map_config'] ?? array();
            $variation_paths = array('variations.variation', 'variations', 'variants.variant', 'variants', 'variation', 'variant');
            
            // Check configured container_xpath first
            if (!empty($map_config['container_xpath'])) {
                $custom_path = trim($map_config['container_xpath'], '/');
                $custom_path = str_replace('//', '.', $custom_path);
                array_unshift($variation_paths, $custom_path);
            }
            
            foreach ($variation_paths as $path) {
                $variations_data = $this->get_nested_value($product_data, $path);
                if (!empty($variations_data) && is_array($variations_data)) {
                    $has_actual_variations = true;
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "✓ Found actual variations at path: $path\n", FILE_APPEND); }
                    break;
                }
            }
            
            if (!$has_actual_variations) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "⚠️ No actual variation data found in XML - keeping as simple product\n", FILE_APPEND); }
                // Reset to simple product mode - don't create variations
                $has_variation_attributes = false;
            }
        }
        
        if ($has_variation_attributes) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "🔄 CONVERTING PRODUCT TO VARIABLE TYPE (found variation attributes AND actual variation data)\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Converting product ID ' . $product_id . ' to variable type'); }
            
            // Change product type to variable
            wp_set_object_terms($product_id, 'variable', 'product_type');
            
            // Reload product as variable product object
            $product = wc_get_product($product_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "✓ Product converted to variable type\n", FILE_APPEND); }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "ℹ️ No variation attributes or no actual variations - keeping as simple product\n", FILE_APPEND); }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Starting foreach loop...\n", FILE_APPEND); }

        $product_attributes = array();

        // Process variation attributes (only if they exist)
        if (!empty($attributes_config['attributes']) && is_array($attributes_config['attributes'])) {
        foreach ($attributes_config['attributes'] as $index => $attr_config) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n=== Processing attribute $index ===\n", FILE_APPEND); }
            if (empty($attr_config['name'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: empty name\n", FILE_APPEND); }
                continue;
            }

            // Auto-add pa_ prefix if not present (WooCommerce requirement)
            $original_name = $attr_config['name'];
            if (strpos($original_name, 'pa_') !== 0) {
                $attr_name = 'pa_' . sanitize_title($original_name);
                $attr_label = $original_name; // Use original as label (e.g., "Materiāls", "Material")
            } else {
                $attr_name = sanitize_title($original_name);
                $attr_label = str_replace('pa_', '', $original_name);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Attribute: $attr_name (Label: $attr_label)\n", FILE_APPEND); }
            
            // Get values from configured source
            $raw_value = null;
            $values_source = $attr_config['values_source'] ?? '';
            $xml_attr_name = $attr_config['xml_attribute_name'] ?? '';
            $xml_attr_key = $attr_config['xml_attribute_key'] ?? '';  // NEW: explicit XML key (e.g., "size", "color")
            $array_index = isset($attr_config['array_index']) ? $attr_config['array_index'] : null;
            
            // Check if we're in "map" mode - auto-extract from variations
            $variation_mode = $attributes_config['variation_mode'] ?? 'auto';
            $map_config = $attributes_config['map_config'] ?? array();
            
            // USE PRE-COLLECTED VALUES if available (from auto-detection)
            if (!empty($attr_config['_auto_values'])) {
                $raw_value = $attr_config['_auto_values'];
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Using pre-collected values: " . implode(', ', $raw_value) . "\n", FILE_APPEND); }
            }
            // AUTO-EXTRACT from variations when in "map" mode
            // Use xml_attribute_key if provided, otherwise fall back to attr_label
            else if ($variation_mode === 'map') {
                // Determine which key to use for extraction
                $search_key = !empty($xml_attr_key) ? $xml_attr_key : $attr_label;
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - MAP MODE: Auto-extracting from variations. Key='$search_key', Source='$values_source', ArrayIndex=" . (is_null($array_index) ? 'null' : $array_index) . "\n", FILE_APPEND); }
                
                // Find variations in product data
                $container_xpath = $map_config['container_xpath'] ?? 'variations.variation';
                $variations_data = $this->get_nested_value($product_data, $container_xpath);
                
                if (!empty($variations_data)) {
                    // Ensure array format
                    if (!isset($variations_data[0])) {
                        $variations_data = array($variations_data);
                    }
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Found " . count($variations_data) . " variations\n", FILE_APPEND); }
                    
                    // Collect all unique values for this attribute from all variations
                    $collected_values = array();
                    
                    foreach ($variations_data as $var_data) {
                        $value = null;
                        
                        // Method 1: Use array_index if provided (e.g., attributes.attribute[0])
                        if ($array_index !== null && is_numeric($array_index)) {
                            // Try source path with array index
                            $possible_paths = array(
                                $values_source,                    // e.g., "attributes.attribute"
                                'attributes.attribute',            // common pattern
                                'attribute'
                            );
                            
                            foreach ($possible_paths as $base_path) {
                                if (empty($base_path)) continue;
                                $array_data = $this->get_nested_value($var_data, $base_path);
                                if (is_array($array_data) && isset($array_data[$array_index])) {
                                    $value = $array_data[$array_index];
                                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Found via array_index at '$base_path[$array_index]': $value\n", FILE_APPEND); }
                                    break;
                                }
                            }
                        }
                        
                        // Method 2: Fall back to key-based search
                        if (empty($value)) {
                            $key_lower = strtolower($search_key);
                            $key_clean = sanitize_title($search_key);
                            
                            $attr_paths = array(
                                'attributes.' . $key_lower,
                                'attributes.' . $search_key,
                                'attributes.' . $key_clean,
                                $key_lower,
                                $search_key,
                                $key_clean
                            );
                            
                            // Also add values_source if provided
                            if (!empty($values_source)) {
                                array_unshift($attr_paths, $values_source);
                            }
                            
                            foreach ($attr_paths as $path) {
                                $found = $this->get_nested_value($var_data, $path);
                                if (!empty($found) && is_string($found)) {
                                    $value = $found;
                                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Found via key search at '$path': $value\n", FILE_APPEND); }
                                    break;
                                }
                            }
                        }
                        
                        // Method 3: Handle <attribute name="Size">S</attribute> XML format
                        // Where attrs_data['attribute'] is an array with @attributes.name and #text or value
                        if (empty($value)) {
                            $attrs_data = $this->get_nested_value($var_data, 'attributes');
                            if (is_array($attrs_data) && isset($attrs_data['attribute'])) {
                                $attr_array = $attrs_data['attribute'];
                                // Ensure it's an array of attributes (not single attribute)
                                if (!isset($attr_array[0])) {
                                    $attr_array = array($attr_array);
                                }
                                foreach ($attr_array as $attr_item) {
                                    if (is_array($attr_item)) {
                                        // Check @attributes.name or @name or name
                                        $item_name = $attr_item['@attributes']['name'] ?? $attr_item['@name'] ?? $attr_item['name'] ?? '';
                                        if (strcasecmp($item_name, $search_key) === 0) {
                                            // Get value from #text, #value, value, or the item itself if string
                                            $val = $attr_item['#text'] ?? $attr_item['#value'] ?? $attr_item['value'] ?? '';
                                            if (!empty($val) && is_string($val)) {
                                                $value = $val;
                                                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Found via name-value XML format '$search_key': $value\n", FILE_APPEND); }
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (!empty($value) && is_string($value)) {
                            $collected_values[] = trim($value);
                        }
                    }
                    
                    // Get unique values
                    $collected_values = array_unique($collected_values);
                    
                    if (!empty($collected_values)) {
                        $raw_value = $collected_values;
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✓ Collected " . count($raw_value) . " unique values from variations: " . implode(', ', $raw_value) . "\n", FILE_APPEND); }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✗ No values found in variations\n", FILE_APPEND); }
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✗ No variations found at '$container_xpath'\n", FILE_APPEND); }
                }
            }
            // Special handling for XML <attributes><attribute> structure
            // If xml_attribute_name is set, use it to match XML attributes by name
            else if ($this->config['file_type'] === 'xml' && 
                !empty($xml_attr_name) &&
                isset($product_data['attributes']['attribute'])) {
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - XML attribute name: $xml_attr_name\n", FILE_APPEND); }
                
                $xml_attributes = $product_data['attributes']['attribute'];
                // Normalize to array if single attribute
                if (!isset($xml_attributes[0])) {
                    $xml_attributes = array($xml_attributes);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Searching in " . count($xml_attributes) . " XML attributes\n", FILE_APPEND); }
                
                // Match by XML attribute name
                foreach ($xml_attributes as $xml_attr) {
                    $xml_name = $xml_attr['name'] ?? '';
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Checking: '$xml_name'\n", FILE_APPEND); }
                    if (strcasecmp($xml_name, $xml_attr_name) === 0) {
                        $raw_value = isset($xml_attr['value']) ? $xml_attr['value'] : null;
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✓ MATCHED '$xml_attr_name' → Value: $raw_value\n", FILE_APPEND); }
                        break;
                    }
                }
                
                if ($raw_value === null) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✗ No match found for XML attribute '$xml_attr_name'\n", FILE_APPEND); }
                }
            } else if (!empty($values_source)) {
                // Standard field extraction from values_source
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Values source: $values_source\n", FILE_APPEND); }
                
                // Extract value from XML/CSV
                try {
                    // Check if source contains {placeholder} syntax (like other fields use)
                    if (strpos($values_source, '{') !== false && strpos($values_source, '}') !== false) {
                        // Parse template string - replace all {field} with actual values
                        $raw_value = $this->parse_template_string($values_source, $product_data);
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Parsed from template: " . print_r($raw_value, true) . "\n", FILE_APPEND); }
                    } else {
                        // Simple field name - extract directly
                        if ($this->config['file_type'] === 'xml') {
                            $raw_value = $this->xml_parser->extract_field_value($product_data, $values_source);
                        } else {
                            $raw_value = $this->csv_parser->extract_field_value($product_data, $values_source);
                        }
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Extracted: " . print_r($raw_value, true) . "\n", FILE_APPEND); }
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND); }
                    continue;
                }
            } else if (empty($raw_value)) {
                // Only error if raw_value wasn't already set by map mode auto-extraction
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ERROR: No xml_attribute_name or values_source configured!\n", FILE_APPEND); }
                continue;
            }
            
            if (empty($raw_value)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: no value found\n", FILE_APPEND); }
                continue;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Got raw value: " . print_r($raw_value, true) . "\n", FILE_APPEND); }

            // Parse values based on mode
            $values_mode = $attr_config['values_mode'] ?? 'single';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Values mode: $values_mode\n", FILE_APPEND); }
            
            $values = array();
            if ($values_mode === 'split' && is_string($raw_value)) {
                // Split by comma or pipe
                $values = array_map('trim', preg_split('/[,|]/', $raw_value));
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Split into: " . count($values) . " values\n", FILE_APPEND); }
            } elseif (is_array($raw_value)) {
                // Already an array (multiple XML elements)
                $values = $raw_value;
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Already array: " . count($values) . " values\n", FILE_APPEND); }
            } else {
                // Single value
                $values = array($raw_value);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Single value\n", FILE_APPEND); }
            }

            // Filter out empty values
            $values = array_filter($values, function($v) {
                return !empty($v);
            });

            if (empty($values)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: no values after filtering\n", FILE_APPEND); }
                continue;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Final values: " . print_r($values, true) . "\n", FILE_APPEND); }

            // Register attribute taxonomy if doesn't exist
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Registering taxonomy: $attr_name\n", FILE_APPEND); }
            $this->register_attribute_taxonomy($attr_name, $attr_label);

            // Create/get terms
            $term_ids = array();
            foreach ($values as $value) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Creating term: $value in taxonomy $attr_name\n", FILE_APPEND); }
                // FIXED: Correct parameter order - value first, then taxonomy
                $term_result = $this->get_or_create_attribute_term($value, $attr_name);
                if ($term_result && isset($term_result->term_id)) {
                    $term_ids[] = $term_result->term_id;
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "      - Term ID: " . $term_result->term_id . "\n", FILE_APPEND); }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "      - FAILED to create term!\n", FILE_APPEND); }
                }
            }

            if (empty($term_ids)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ERROR: No terms created!\n", FILE_APPEND); }
                continue;
            }

            // Set product attribute
            $is_visible = isset($attr_config['visible']) && $attr_config['visible'] == 1;
            $is_variation = isset($attr_config['used_for_variations']) && $attr_config['used_for_variations'] == 1;

            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Creating product attribute: visible=$is_visible, variation=$is_variation\n", FILE_APPEND); }

            $product_attributes[$attr_name] = array(
                'name' => $attr_name,
                'value' => '', // WooCommerce uses taxonomy terms, not this field
                'position' => $index,
                'is_visible' => $is_visible ? 1 : 0,
                'is_variation' => $is_variation ? 1 : 0,
                'is_taxonomy' => 1
            );

            // Set terms to product
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Setting " . count($term_ids) . " terms to product\n", FILE_APPEND); }
            wp_set_object_terms($product_id, $term_ids, $attr_name);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - ✓ Attribute complete!\n", FILE_APPEND); }
        }
        } // End of if (!empty($attributes_config['attributes']))

        // =====================================================
        // PROCESS KEY-VALUE ATTRIBUTE PAIRS (BigBuy format)
        // e.g., {attribute1} → "Colour", {value1} → "Blue"
        // =====================================================
        $attribute_pairs = $attributes_config['attribute_pairs'] ?? array();
        $attr_input_mode = $attributes_config['attr_input_mode'] ?? 'standard';
        
        if ($attr_input_mode === 'key_value' && !empty($attribute_pairs) && is_array($attribute_pairs)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n=== PROCESSING KEY-VALUE ATTRIBUTE PAIRS ===\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Found " . count($attribute_pairs) . " attribute pairs to process\n", FILE_APPEND); }
            
            $attr_position = count($product_attributes);
            
            foreach ($attribute_pairs as $pair) {
                $name_field = $pair['name_field'] ?? '';
                $value_field = $pair['value_field'] ?? '';
                
                if (empty($name_field) || empty($value_field)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: empty name_field or value_field\n", FILE_APPEND); }
                    continue;
                }
                
                // Parse attribute name from template (e.g., {attribute1} → "Colour")
                $attr_name_value = $this->parse_template_string($name_field, $product_data);
                if (empty($attr_name_value)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: parsed name is empty for '$name_field'\n", FILE_APPEND); }
                    continue;
                }
                
                // Parse attribute value from template (e.g., {value1} → "Blue")
                $attr_value = $this->parse_template_string($value_field, $product_data);
                if (empty($attr_value)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: parsed value is empty for '$value_field'\n", FILE_APPEND); }
                    continue;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Processing pair: $name_field='$attr_name_value' → $value_field='$attr_value'\n", FILE_APPEND); }
                
                // Prepare taxonomy name from the dynamic attribute name
                $original_name = trim($attr_name_value);
                if (strpos($original_name, 'pa_') !== 0) {
                    $attr_name = 'pa_' . sanitize_title($original_name);
                    $attr_label = $original_name;
                } else {
                    $attr_name = sanitize_title($original_name);
                    $attr_label = str_replace('pa_', '', $original_name);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Taxonomy: $attr_name (Label: $attr_label)\n", FILE_APPEND); }
                
                // Parse values (handle array or string with comma/pipe separators)
                $values = array();
                if (is_array($attr_value)) {
                    $values = $attr_value;
                } else if (is_string($attr_value)) {
                    if (preg_match('/[,|]/', $attr_value)) {
                        $values = array_map('trim', preg_split('/[,|]/', $attr_value));
                    } else {
                        $values = array(trim($attr_value));
                    }
                }
                
                $values = array_filter($values, function($v) {
                    return !empty($v);
                });
                
                if (empty($values)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Skipping: no values after filtering\n", FILE_APPEND); }
                    continue;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Values: " . implode(', ', $values) . "\n", FILE_APPEND); }
                
                // Register attribute taxonomy
                $this->register_attribute_taxonomy($attr_name, $attr_label);
                
                // Create/get terms
                $term_ids = array();
                foreach ($values as $value) {
                    $term_result = $this->get_or_create_attribute_term($value, $attr_name);
                    if ($term_result && isset($term_result->term_id)) {
                        $term_ids[] = $term_result->term_id;
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "      - Term created: $value (ID: " . $term_result->term_id . ")\n", FILE_APPEND); }
                    }
                }
                
                if (empty($term_ids)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - ERROR: No terms created!\n", FILE_APPEND); }
                    continue;
                }
                
                // Add to product attributes
                $product_attributes[$attr_name] = array(
                    'name' => $attr_name,
                    'value' => '',
                    'position' => $attr_position++,
                    'is_visible' => 1,
                    'is_variation' => 0,
                    'is_taxonomy' => 1
                );
                
                // Set terms to product
                wp_set_object_terms($product_id, $term_ids, $attr_name);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - ✓ Key-Value pair attribute complete!\n", FILE_APPEND); }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "=== KEY-VALUE PAIRS PROCESSING COMPLETE ===\n\n", FILE_APPEND); }
        }

        // =====================================================
        // PROCESS DISPLAY ATTRIBUTES (non-variation attributes)
        // =====================================================
        $display_attributes = $attributes_config['display_attributes'] ?? array();
        if (!empty($display_attributes) && is_array($display_attributes)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n=== PROCESSING DISPLAY ATTRIBUTES ===\n", FILE_APPEND); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Found " . count($display_attributes) . " display attributes to process\n", FILE_APPEND); }
            
            $attr_position = count($product_attributes); // Continue position after variation attributes
            
            foreach ($display_attributes as $display_attr) {
                if (empty($display_attr['name'])) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Skipping: empty name\n", FILE_APPEND); }
                    continue;
                }
                
                $original_name = $display_attr['name'];
                $source_field = $display_attr['source'] ?? '';
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  - Processing display attribute: $original_name (source: $source_field)\n", FILE_APPEND); }
                
                // Get value from source field in product_data
                $raw_value = null;
                if (!empty($source_field)) {
                    // Check if source contains {placeholder} syntax (like other fields use)
                    if (strpos($source_field, '{') !== false && strpos($source_field, '}') !== false) {
                        // Parse template string - replace all {field} with actual values
                        $raw_value = $this->parse_template_string($source_field, $product_data);
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Parsed from template: " . print_r($raw_value, true) . "\n", FILE_APPEND); }
                    } else {
                        // Simple field name - try direct access or nested path
                        if (isset($product_data[$source_field])) {
                            $raw_value = $product_data[$source_field];
                        } else {
                            // Try nested path extraction
                            $raw_value = $this->get_nested_value($product_data, $source_field);
                        }
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Extracted value: " . print_r($raw_value, true) . "\n", FILE_APPEND); }
                    }
                }
                
                if (empty($raw_value)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Skipping: no value found for source '$source_field'\n", FILE_APPEND); }
                    continue;
                }
                
                // Prepare taxonomy name
                if (strpos($original_name, 'pa_') !== 0) {
                    $attr_name = 'pa_' . sanitize_title($original_name);
                    $attr_label = $original_name;
                } else {
                    $attr_name = sanitize_title($original_name);
                    $attr_label = str_replace('pa_', '', $original_name);
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Taxonomy: $attr_name (Label: $attr_label)\n", FILE_APPEND); }
                
                // Parse values (handle array or string with comma/pipe separators)
                $values = array();
                if (is_array($raw_value)) {
                    $values = $raw_value;
                } else if (is_string($raw_value)) {
                    // Check if contains comma or pipe, then split
                    if (preg_match('/[,|]/', $raw_value)) {
                        $values = array_map('trim', preg_split('/[,|]/', $raw_value));
                    } else {
                        $values = array(trim($raw_value));
                    }
                }
                
                $values = array_filter($values, function($v) {
                    return !empty($v);
                });
                
                if (empty($values)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Skipping: no values after filtering\n", FILE_APPEND); }
                    continue;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - Values: " . implode(', ', $values) . "\n", FILE_APPEND); }
                
                // Register attribute taxonomy
                $this->register_attribute_taxonomy($attr_name, $attr_label);
                
                // Create/get terms
                $term_ids = array();
                foreach ($values as $value) {
                    $term_result = $this->get_or_create_attribute_term($value, $attr_name);
                    if ($term_result && isset($term_result->term_id)) {
                        $term_ids[] = $term_result->term_id;
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "      - Term created: $value (ID: " . $term_result->term_id . ")\n", FILE_APPEND); }
                    }
                }
                
                if (empty($term_ids)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - ERROR: No terms created!\n", FILE_APPEND); }
                    continue;
                }
                
                // Set visibility (display attributes are always visible, never used for variations)
                $is_visible = isset($display_attr['visible']) ? ($display_attr['visible'] == 1) : true;
                
                $product_attributes[$attr_name] = array(
                    'name' => $attr_name,
                    'value' => '',
                    'position' => $attr_position++,
                    'is_visible' => $is_visible ? 1 : 0,
                    'is_variation' => 0, // Display attributes are NOT for variations
                    'is_taxonomy' => 1
                );
                
                // Set terms to product
                wp_set_object_terms($product_id, $term_ids, $attr_name);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    - ✓ Display attribute complete!\n", FILE_APPEND); }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "=== DISPLAY ATTRIBUTES PROCESSING COMPLETE ===\n\n", FILE_APPEND); }
        }

        // Save attributes to product
        if (!empty($product_attributes)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\nSaving " . count($product_attributes) . " attributes to product meta\n", FILE_APPEND); }
            update_post_meta($product_id, '_product_attributes', $product_attributes);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "✓✓✓ ATTRIBUTES SAVED SUCCESSFULLY! ✓✓✓\n", FILE_APPEND); }
            
            // CRITICAL: Generate variations if product is variable type
            if ($has_variation_attributes) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n🔄 GENERATING VARIATIONS...\n", FILE_APPEND); }
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Generating variations for product ID: ' . $product_id); }
                // Pass product_data for "map" mode to read variations from XML
                $this->generate_product_variations($product_id, $product_attributes, $attributes_config, $product_data);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\nERROR: No attributes to save!\n", FILE_APPEND); }
        }
        
        // CRITICAL: Check if product is variable type but has no variations
        // This can happen when type=variable is set but no variation data exists in XML
        $final_product = wc_get_product($product_id);
        if ($final_product && $final_product->is_type('variable')) {
            $variation_count = count($final_product->get_children());
            if ($variation_count === 0) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "⚠️ Product is variable type but has 0 variations - converting to SIMPLE\n", FILE_APPEND); }
                wp_set_object_terms($product_id, 'simple', 'product_type');
                // Ensure stock status is set correctly
                $stock_status = get_post_meta($product_id, '_stock_status', true);
                if (empty($stock_status)) {
                    update_post_meta($product_id, '_stock_status', 'instock');
                }
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  ✓ Converted to simple product\n", FILE_APPEND); }
            }
        }
    }
    
    /**
     * Generate product variations from attributes.
     *
     * @since    1.0.0
     * @param    int $product_id Parent product ID
     * @param    array $product_attributes Saved attributes array
     * @param    array $attributes_config Attributes configuration
     * @param    array $product_data Raw product data from XML/CSV (for map mode)
     */
    private function generate_product_variations($product_id, $product_attributes, $attributes_config, $product_data = array()) {
        $log_file = WP_CONTENT_DIR . '/variations_debug.log';
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, date('Y-m-d H:i:s') . " - GENERATING VARIATIONS for product: $product_id\n", FILE_APPEND); }
        
        // NORMALIZE: Support both old and new UI formats
        $product_mode = $attributes_config['product_mode'] ?? null;
        $variation_mode = $attributes_config['variation_mode'] ?? 'auto';
        
        // Convert new format to old format
        if ($product_mode === 'variable') {
            $variation_mode = 'map';
            // Ensure map_config has container_xpath from variation_path
            if (!empty($attributes_config['variation_path']) && empty($attributes_config['map_config']['container_xpath'])) {
                $attributes_config['map_config'] = $attributes_config['map_config'] ?? array();
                $attributes_config['map_config']['container_xpath'] = $attributes_config['variation_path'];
            }
            // Convert variation_fields to map_config
            if (!empty($attributes_config['variation_fields'])) {
                $vf = $attributes_config['variation_fields'];
                $attributes_config['map_config']['sku_source'] = $vf['sku'] ?? '';
                $attributes_config['map_config']['price_source'] = $vf['regular_price'] ?? '';
                $attributes_config['map_config']['sale_price_source'] = $vf['sale_price'] ?? '';
                $attributes_config['map_config']['sale_price_dates_from_source'] = $vf['sale_price_dates_from'] ?? '';
                $attributes_config['map_config']['sale_price_dates_to_source'] = $vf['sale_price_dates_to'] ?? '';
                $attributes_config['map_config']['stock_source'] = $vf['stock_quantity'] ?? '';
                $attributes_config['map_config']['stock_status_source'] = $vf['stock_status'] ?? '';
                $attributes_config['map_config']['manage_stock_source'] = $vf['manage_stock'] ?? '';
                $attributes_config['map_config']['low_stock_amount_source'] = $vf['low_stock_amount'] ?? '';
                $attributes_config['map_config']['weight_source'] = $vf['weight'] ?? '';
                $attributes_config['map_config']['length_source'] = $vf['length'] ?? '';
                $attributes_config['map_config']['width_source'] = $vf['width'] ?? '';
                $attributes_config['map_config']['height_source'] = $vf['height'] ?? '';
                $attributes_config['map_config']['description_source'] = $vf['description'] ?? '';
                $attributes_config['map_config']['shipping_class_source'] = $vf['shipping_class'] ?? '';
                $attributes_config['map_config']['image_source'] = $vf['image'] ?? '';
                $attributes_config['map_config']['virtual_source'] = $vf['virtual'] ?? '';
                $attributes_config['map_config']['downloadable_source'] = $vf['downloadable'] ?? '';
                // Product identifiers
                $attributes_config['map_config']['ean_source'] = $vf['ean'] ?? '';
                $attributes_config['map_config']['upc_source'] = $vf['upc'] ?? '';
                $attributes_config['map_config']['gtin_source'] = $vf['gtin'] ?? '';
                $attributes_config['map_config']['isbn_source'] = $vf['isbn'] ?? '';
                $attributes_config['map_config']['mpn_source'] = $vf['mpn'] ?? '';
            }
        } elseif ($product_mode === 'simple' || $product_mode === 'attributes') {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Product mode is $product_mode - skipping variation generation\n", FILE_APPEND); }
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Variation mode: $variation_mode\n", FILE_APPEND); }
        
        // First, delete all existing variations to regenerate fresh
        $existing_variations = get_posts(array(
            'post_type' => 'product_variation',
            'post_parent' => $product_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        if (!empty($existing_variations)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Deleting " . count($existing_variations) . " existing variations...\n", FILE_APPEND); }
            foreach ($existing_variations as $variation_id) {
                wp_delete_post($variation_id, true);
            }
        }
        
        $parent_product = wc_get_product($product_id);
        $variation_count = 0;
        
        // MAP MODE: Read variations directly from XML/CSV data
        if ($variation_mode === 'map' && !empty($product_data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "📋 USING MAP MODE - reading variations from source data\n", FILE_APPEND); }
            $variation_count = $this->create_mapped_variations($product_id, $product_attributes, $attributes_config, $product_data, $parent_product);
        } else {
            // AUTO MODE: Generate all combinations from attributes
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "🔄 USING AUTO MODE - generating all attribute combinations\n", FILE_APPEND); }
            $variation_count = $this->create_auto_variations($product_id, $product_attributes, $attributes_config, $parent_product);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "✓✓✓ GENERATED $variation_count VARIATIONS! ✓✓✓\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Generated ' . $variation_count . ' variations for product ' . $product_id); }
        
        // CRITICAL: If no variations were created, convert back to simple product
        // This prevents "out of stock" issue for variable products without variations
        if ($variation_count === 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "⚠️ No variations created - converting product back to SIMPLE type\n", FILE_APPEND); }
            wp_set_object_terms($product_id, 'simple', 'product_type');
            
            // Reload product to get correct type
            $parent_product = wc_get_product($product_id);
            if ($parent_product) {
                // Ensure stock status is set correctly for simple product
                $current_stock_status = get_post_meta($product_id, '_stock_status', true);
                if (empty($current_stock_status) || $current_stock_status === 'outofstock') {
                    // Check if we have stock quantity
                    $stock_qty = get_post_meta($product_id, '_stock', true);
                    if (!empty($stock_qty) && intval($stock_qty) > 0) {
                        update_post_meta($product_id, '_stock_status', 'instock');
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  ✓ Set stock status to 'instock' (qty: $stock_qty)\n", FILE_APPEND); }
                    }
                }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  ✓ Product converted to simple type\n", FILE_APPEND); }
            return; // Skip sync since it's no longer variable
        }
        
        // Sync variation data with parent
        if (function_exists('WC_Product_Variable::sync')) {
            WC_Product_Variable::sync($product_id);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "✓ Synced variations with parent product\n", FILE_APPEND); }
        }
        
        // Force sync stock status - WooCommerce sometimes doesn't do this correctly
        $children = get_posts(array(
            'post_type' => 'product_variation',
            'post_parent' => $product_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ));
        
        // CRITICAL: Force all variations to be instock if they don't manage stock
        // WooCommerce sync() can reset stock_status to outofstock even when manage_stock=no
        foreach ($children as $child_id) {
            $child_manage_stock = get_post_meta($child_id, '_manage_stock', true);
            $child_stock_status = get_post_meta($child_id, '_stock_status', true);
            
            // If not managing stock and status is outofstock, force to instock
            if ($child_manage_stock === 'no' && $child_stock_status !== 'instock') {
                update_post_meta($child_id, '_stock_status', 'instock');
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Fixed variation $child_id stock_status to instock (manage_stock=no)\n", FILE_APPEND); }
            }
        }
        
        $has_stock = false;
        foreach ($children as $child_id) {
            $child_stock_status = get_post_meta($child_id, '_stock_status', true);
            if ($child_stock_status === 'instock') {
                $has_stock = true;
                break;
            }
        }
        
        // Update parent stock status directly
        $new_stock_status = $has_stock ? 'instock' : 'outofstock';
        update_post_meta($product_id, '_stock_status', $new_stock_status);
        
        // Sync the variable product to update prices and other aggregated data
        WC_Product_Variable::sync($product_id);
        
        // CRITICAL: Fix variations AGAIN after second sync - sync can reset stock_status
        global $wpdb;
        foreach ($children as $child_id) {
            $child_manage_stock = get_post_meta($child_id, '_manage_stock', true);
            $child_stock_status = get_post_meta($child_id, '_stock_status', true);
            
            // If not managing stock and status is outofstock, force to instock
            if ($child_manage_stock === 'no' && $child_stock_status !== 'instock') {
                $wpdb->update(
                    $wpdb->postmeta,
                    array('meta_value' => 'instock'),
                    array('post_id' => $child_id, 'meta_key' => '_stock_status'),
                    array('%s'),
                    array('%d', '%s')
                );
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Fixed variation $child_id stock_status to instock after sync\n", FILE_APPEND); }
            }
        }
        
        // IMPORTANT: Re-set parent stock status AFTER sync because sync can reset it
        // Use direct DB query to bypass any WooCommerce hooks
        $wpdb->update(
            $wpdb->postmeta,
            array('meta_value' => $new_stock_status),
            array('post_id' => $product_id, 'meta_key' => '_stock_status'),
            array('%s'),
            array('%d', '%s')
        );
        
        // Clear ALL WooCommerce product caches to ensure stock status is reflected
        // Note: We use direct cache deletion instead of wc_delete_product_transients()
        // to avoid triggering hooks that might reset stock status
        wp_cache_delete('product-' . $product_id, 'products');
        wp_cache_delete($product_id, 'post_meta');
        clean_post_cache($product_id);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Set parent product $product_id stock_status to: $new_stock_status\n", FILE_APPEND); }
    }
    
    /**
     * Create variations from mapped XML/CSV data.
     *
     * @since    1.0.0
     * @param    int $product_id Parent product ID
     * @param    array $product_attributes Saved attributes array
     * @param    array $attributes_config Attributes configuration
     * @param    array $product_data Raw product data
     * @param    WC_Product $parent_product Parent product object
     * @return   int Number of variations created
     */
    private function create_mapped_variations($product_id, $product_attributes, $attributes_config, $product_data, $parent_product) {
        $log_file = WP_CONTENT_DIR . '/variations_debug.log';
        $variation_count = 0;
        
        // DEBUG: Log what product_data contains
        if (defined('WP_DEBUG') && WP_DEBUG) { 
            file_put_contents($log_file, "\n=== CREATE_MAPPED_VARIATIONS for product $product_id ===\n", FILE_APPEND); 
            file_put_contents($log_file, "  product_data keys: " . implode(', ', array_keys($product_data)) . "\n", FILE_APPEND);
            if (isset($product_data['variations'])) {
                file_put_contents($log_file, "  variations key EXISTS, type: " . gettype($product_data['variations']) . "\n", FILE_APPEND);
                if (is_array($product_data['variations'])) {
                    file_put_contents($log_file, "  variations sub-keys: " . implode(', ', array_keys($product_data['variations'])) . "\n", FILE_APPEND);
                }
            } else {
                file_put_contents($log_file, "  variations key NOT FOUND!\n", FILE_APPEND);
            }
        }
        
        // Get map configuration
        $map_config = $attributes_config['map_config'] ?? array();
        
        // Try to find variations in product data
        // Common paths: variations.variation, variants.variant, variation, etc.
        $variations_data = null;
        $possible_paths = array(
            'variations.variation',
            'variations',
            'variants.variant', 
            'variants',
            'variation',
            'variant'
        );
        
        // First try configured container_xpath if provided
        if (!empty($map_config['container_xpath'])) {
            $custom_path = trim($map_config['container_xpath'], '/');
            $custom_path = str_replace('//', '.', $custom_path);
            array_unshift($possible_paths, $custom_path);
        }
        
        foreach ($possible_paths as $path) {
            $data = $this->get_nested_value($product_data, $path);
            if (!empty($data)) {
                $variations_data = $data;
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Found variations at path: $path\n", FILE_APPEND); }
                break;
            }
        }
        
        if (empty($variations_data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  WARNING: No variations found in product data. Available keys: " . implode(', ', array_keys($product_data)) . "\n", FILE_APPEND); }
            // Fall back to auto mode
            return $this->create_auto_variations($product_id, $product_attributes, $attributes_config, $parent_product);
        }
        
        // Ensure variations_data is an array of variations
        if (!isset($variations_data[0])) {
            $variations_data = array($variations_data);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Processing " . count($variations_data) . " variations from source data\n", FILE_APPEND); }
        
        // Check if auto-detect mode is enabled
        $attribute_detection_mode = $attributes_config['attribute_detection_mode'] ?? 'manual';
        $auto_attributes_path = $attributes_config['auto_attributes_path'] ?? 'attributes';
        $auto_create_attributes = $attributes_config['auto_create_attributes'] ?? true;
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Attribute detection mode: $attribute_detection_mode\n", FILE_APPEND); }
        
        // Get attribute info for matching
        $attr_configs = $attributes_config['attributes'] ?? array();
        $variation_attrs_map = array(); // Maps attribute name to full config
        
        if ($attribute_detection_mode === 'auto') {
            // AUTO-DETECT MODE: Dynamically discover attributes from first variation
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  AUTO-DETECT: Analyzing first variation to discover attributes...\n", FILE_APPEND); }
            
            // Get attributes from first variation to discover structure
            $first_var = $variations_data[0] ?? array();
            $attrs_data = $this->get_nested_value($first_var, $auto_attributes_path) ?? $first_var;
            
            if (is_array($attrs_data)) {
                foreach ($attrs_data as $attr_key => $attr_value) {
                    // Skip non-string values (nested arrays, etc.)
                    if (!is_string($attr_value) && !is_numeric($attr_value)) {
                        continue;
                    }
                    
                    // Skip common non-attribute fields
                    $skip_fields = array('sku', 'price', 'regular_price', 'sale_price', 'stock', 'stock_quantity', 'manage_stock', 'stock_status', 'weight', 'length', 'width', 'height', 'description', 'image', 'virtual', 'downloadable', 'parent_sku', 'gtin', 'ean', 'upc', 'isbn', 'mpn', 'enabled', 'status', 'backorders', 'shipping_class', 'tax_class', 'download_limit', 'download_expiry', 'downloads', 'low_stock_amount', 'sale_price_dates_from', 'sale_price_dates_to', 'menu_order');
                    if (in_array(strtolower($attr_key), $skip_fields)) {
                        continue;
                    }
                    
                    // This is an attribute! Create/get taxonomy
                    $attr_name = ucfirst(str_replace('_', ' ', $attr_key));
                    $taxonomy_name = $this->sanitize_attribute_name($attr_name);
                    
                    // Create attribute if needed
                    if ($auto_create_attributes) {
                        $this->ensure_attribute_taxonomy_exists($attr_name, $taxonomy_name);
                    }
                    
                    $variation_attrs_map[$attr_name] = array(
                        'taxonomy' => $taxonomy_name,
                        'source_key' => $attr_key,  // Direct key in attributes object
                        'source' => '',
                        'array_index' => null
                    );
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    AUTO-DETECTED attribute: '$attr_name' (key: $attr_key) → $taxonomy_name\n", FILE_APPEND); }
                }
            }
            
            // Also need to update parent product with these attributes
            $this->update_parent_with_auto_attributes($product_id, $variation_attrs_map, $variations_data, $auto_attributes_path, $parent_product);
            
        } else {
            // MANUAL MODE: Use configured attributes
            foreach ($attr_configs as $attr_config) {
                if (!empty($attr_config['used_for_variations'])) {
                    $attr_name = $attr_config['name'];
                    $taxonomy_name = $this->sanitize_attribute_name($attr_name);
                    $variation_attrs_map[$attr_name] = array(
                        'taxonomy' => $taxonomy_name,
                        'source' => $attr_config['values_source'] ?? '',
                        'array_index' => isset($attr_config['array_index']) ? $attr_config['array_index'] : null
                    );
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Attribute config: $attr_name → source='" . ($attr_config['values_source'] ?? '') . "', array_index=" . (isset($attr_config['array_index']) ? $attr_config['array_index'] : 'null') . "\n", FILE_APPEND); }
                }
            }
        }
        
        // Process each variation from source data
        foreach ($variations_data as $var_index => $var_data) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  \n  --- Variation #$var_index ---\n", FILE_APPEND); }
            
            if (!is_array($var_data)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Skipping non-array variation data\n", FILE_APPEND); }
                continue;
            }
            
            // Create variation product
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            
            // Extract variation attributes (e.g., size=S, color=Black)
            $var_attributes = array();
            
            // Try to find attributes in variation data
            $attrs_data = $this->get_nested_value($var_data, 'attributes') ?? $var_data;
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Available attrs_data keys: " . (is_array($attrs_data) ? implode(', ', array_keys($attrs_data)) : 'not array') . "\n", FILE_APPEND); }
            
            foreach ($variation_attrs_map as $attr_name => $attr_cfg) {
                $taxonomy_name = $attr_cfg['taxonomy'];
                $source = $attr_cfg['source'] ?? '';
                $array_index = $attr_cfg['array_index'] ?? null;
                $source_key = $attr_cfg['source_key'] ?? null; // For auto-detect mode
                
                // Try different paths to find attribute value
                $attr_value = null;
                
                // AUTO-DETECT MODE: Use source_key to directly access attribute in attrs_data
                if ($source_key !== null) {
                    // Get attributes data at the configured path
                    $attrs_data_for_var = $this->get_nested_value($var_data, $auto_attributes_path) ?? $var_data;
                    
                    if (is_array($attrs_data_for_var) && isset($attrs_data_for_var[$source_key])) {
                        $attr_value = $attrs_data_for_var[$source_key];
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    AUTO-DETECT: Found '$attr_name' using source_key '$source_key': $attr_value\n", FILE_APPEND); }
                    }
                }
                
                // MANUAL MODE fallback: Try various path patterns
                if (empty($attr_value)) {
                // XML: <attributes><size>S</size><color>Black</color></attributes>
                $attr_name_lower = strtolower($attr_name);
                $attr_name_underscore = strtolower(str_replace(' ', '_', $attr_name));
                
                // Try direct access first (most reliable for standard XML)
                // Format 1: <attributes><size>S</size><color>Black</color></attributes>
                $direct_paths = array(
                    'attributes.' . $attr_name_lower,
                    'attributes.' . $attr_name,
                    'attributes.' . $attr_name_underscore,
                    $attr_name_lower,
                    $attr_name,
                );
                
                foreach ($direct_paths as $path) {
                    $value = $this->get_nested_value($var_data, $path);
                    if (!empty($value) && is_string($value)) {
                        $attr_value = $value;
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Found attribute '$attr_name' at path '$path': $attr_value\n", FILE_APPEND); }
                        break;
                    }
                }
                
                // Format 2: <attributes><attribute name="Size">S</attribute></attributes>
                // In this case attrs_data will have 'attribute' array with '@attributes.name' and '#text'
                if (empty($attr_value) && is_array($attrs_data) && isset($attrs_data['attribute'])) {
                    $attr_array = $attrs_data['attribute'];
                    // Ensure it's an array of attributes (not single attribute)
                    if (!isset($attr_array[0])) {
                        $attr_array = array($attr_array);
                    }
                    foreach ($attr_array as $attr_item) {
                        if (is_array($attr_item)) {
                            // Check @attributes.name or @name
                            $item_name = $attr_item['@attributes']['name'] ?? $attr_item['@name'] ?? $attr_item['name'] ?? '';
                            if (strcasecmp($item_name, $attr_name) === 0) {
                                // Get value from #text or direct value
                                $attr_value = $attr_item['#text'] ?? $attr_item['#value'] ?? $attr_item['value'] ?? (is_string($attr_item) ? $attr_item : '');
                                if ($attr_value) {
                                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Found attribute '$attr_name' in name-value format: $attr_value\n", FILE_APPEND); }
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // If array_index is set, use it to access array element
                if (empty($attr_value) && $array_index !== null && is_numeric($array_index)) {
                    // Try source path with array index
                    $possible_paths = array(
                        $source,                           // e.g., "attributes.attribute"
                        'attributes.attribute',            // common pattern
                        'attribute'
                    );
                    
                    foreach ($possible_paths as $base_path) {
                        if (empty($base_path)) continue;
                        $array_data = $this->get_nested_value($var_data, $base_path);
                        if (is_array($array_data) && isset($array_data[$array_index])) {
                            $attr_value = $array_data[$array_index];
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Found attribute '$attr_name' at '$base_path[$array_index]': $attr_value\n", FILE_APPEND); }
                            break;
                        }
                    }
                }
                
                // Fallback: Try using source path directly (cleaned up)
                if (empty($attr_value) && !empty($source)) {
                    // Clean source - remove variations.variation[0]. prefix if present
                    $clean_source = preg_replace('/^variations\.variation\[\d+\]\./', '', $source);
                    $clean_source = preg_replace('/^variations\.variation\./', '', $clean_source);
                    
                    if ($clean_source !== $source) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Cleaned source: '$source' → '$clean_source'\n", FILE_APPEND); }
                    }
                
                    $possible_attr_paths = array(
                        $clean_source,
                        strtolower($attr_name),
                        $attr_name,
                        'attributes.' . strtolower($attr_name),
                        'attributes.' . $attr_name,
                        strtolower(str_replace(' ', '_', $attr_name)),
                    );
                
                    foreach ($possible_attr_paths as $attr_path) {
                        $value = $this->get_nested_value($var_data, $attr_path);
                        if (!empty($value) && is_string($value)) {
                            $attr_value = $value;
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Found attribute '$attr_name' at path '$attr_path': $attr_value\n", FILE_APPEND); }
                            break;
                        }
                    }
                }
                } // End of manual mode fallback
                
                if ($attr_value) {
                    // Create/get term and use slug
                    $term = $this->get_or_create_attribute_term($attr_value, $taxonomy_name);
                    if ($term) {
                        $var_attributes[$taxonomy_name] = $term->slug;
                    }
                }
            }
            
            if (empty($var_attributes)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    WARNING: No attributes found for this variation, skipping\n", FILE_APPEND); }
                continue;
            }
            
            $variation->set_attributes($var_attributes);
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set attributes: " . json_encode($var_attributes) . "\n", FILE_APPEND); }
            
            // Set SKU
            $var_sku = $this->get_variation_field($var_data, $map_config, 'sku', array('sku', 'SKU', 'variation_sku'));
            if ($var_sku) {
                // Check if SKU is unique
                $existing_id = wc_get_product_id_by_sku($var_sku);
                if (!$existing_id) {
                    $variation->set_sku($var_sku);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set SKU: $var_sku\n", FILE_APPEND); }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    SKU '$var_sku' already exists (product $existing_id), skipping SKU\n", FILE_APPEND); }
                }
            }
            
            // Set Price
            $var_price = $this->get_variation_field($var_data, $map_config, 'price', array('price', 'regular_price', 'Price'));
            if ($var_price !== null) {
                $price_clean = preg_replace('/[^0-9.,]/', '', $var_price);
                $price_clean = str_replace(',', '.', $price_clean);
                $variation->set_regular_price($price_clean);
                $variation->set_price($price_clean);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set price: $price_clean\n", FILE_APPEND); }
            } else {
                // Use parent price
                $parent_price = $parent_product->get_regular_price();
                if ($parent_price) {
                    $variation->set_regular_price($parent_price);
                    $variation->set_price($parent_price);
                }
            }
            
            // Set Sale Price
            $var_sale_price = $this->get_variation_field($var_data, $map_config, 'sale_price', array('sale_price', 'special_price'));
            if ($var_sale_price !== null) {
                $sale_clean = preg_replace('/[^0-9.,]/', '', $var_sale_price);
                $sale_clean = str_replace(',', '.', $sale_clean);
                $variation->set_sale_price($sale_clean);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set sale price: $sale_clean\n", FILE_APPEND); }
            }
            
            // Set Stock
            $var_stock = $this->get_variation_field($var_data, $map_config, 'stock', array('stock', 'stock_quantity', 'qty', 'quantity'));
            $var_stock_status = $this->get_variation_field($var_data, $map_config, 'stock_status', array('stock_status'));
            $var_manage_stock = $this->get_variation_field($var_data, $map_config, 'manage_stock', array('manage_stock'));
            
            // Debug: log stock values
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                file_put_contents($log_file, "    Stock debug - var_stock: " . var_export($var_stock, true) . ", var_stock_status: " . var_export($var_stock_status, true) . ", var_manage_stock: " . var_export($var_manage_stock, true) . "\n", FILE_APPEND); 
            }
            
            if ($var_stock !== null && $var_stock !== '') {
                // Stock quantity is provided - enable stock management
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity((int)$var_stock);
                $variation->set_stock_status((int)$var_stock > 0 ? 'instock' : 'outofstock');
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set stock: $var_stock (managed)\n", FILE_APPEND); }
            } elseif ($var_manage_stock !== null) {
                // Manage stock field is explicitly set
                $should_manage = in_array(strtolower($var_manage_stock), array('yes', '1', 'true', 'on'));
                $variation->set_manage_stock($should_manage);
                if (!$should_manage) {
                    // Not managing stock - set status from mapping or default to instock
                    $status = 'instock';
                    if ($var_stock_status !== null && !empty($var_stock_status)) {
                        $status = strtolower($var_stock_status) === 'outofstock' ? 'outofstock' : 'instock';
                    }
                    $variation->set_stock_status($status);
                }
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set manage_stock: " . ($should_manage ? 'yes' : 'no') . "\n", FILE_APPEND); }
            } else {
                // No stock info provided - don't manage stock, set as in stock
                $variation->set_manage_stock(false);
                // Check if stock_status is explicitly set
                if ($var_stock_status !== null && !empty($var_stock_status)) {
                    $status = strtolower($var_stock_status) === 'outofstock' ? 'outofstock' : 'instock';
                    $variation->set_stock_status($status);
                } else {
                    $variation->set_stock_status('instock');
                }
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    No stock provided - set to instock (unmanaged)\n", FILE_APPEND); }
            }
            
            // Set Backorders
            $var_backorders = $this->get_variation_field($var_data, $map_config, 'backorders', array('backorders'));
            $backorders_default = $map_config['backorders_default'] ?? '';
            if ($var_backorders !== null && !empty($var_backorders)) {
                // Map from source value
                $backorders_value = strtolower($var_backorders);
                if (in_array($backorders_value, array('no', 'notify', 'yes'))) {
                    $variation->set_backorders($backorders_value);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set backorders: $backorders_value\n", FILE_APPEND); }
                }
            } elseif (!empty($backorders_default)) {
                // Use default value from dropdown
                $variation->set_backorders($backorders_default);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set backorders (default): $backorders_default\n", FILE_APPEND); }
            }
            
            // Check for weight/dimensions inheritance from parent
            $weight_inherit = !empty($map_config['weight_inherit']);
            $dimensions_inherit = !empty($map_config['dimensions_inherit']);
            
            // Set Weight (with inheritance option)
            $var_weight = $this->get_variation_field($var_data, $map_config, 'weight', array('weight', 'Weight'));
            if ($weight_inherit && ($var_weight === null || $var_weight === '')) {
                // Inherit from parent
                $parent_weight = $parent_product->get_weight();
                if ($parent_weight) {
                    $variation->set_weight(floatval($parent_weight));
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Inherited weight from parent: $parent_weight\n", FILE_APPEND); }
                }
            } elseif ($var_weight !== null && $var_weight !== '') {
                $variation->set_weight(floatval($var_weight));
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set weight: $var_weight\n", FILE_APPEND); }
            }
            
            // Set Dimensions (with inheritance option)
            $var_length = $this->get_variation_field($var_data, $map_config, 'length', array('length', 'Length'));
            $var_width = $this->get_variation_field($var_data, $map_config, 'width', array('width', 'Width'));
            $var_height = $this->get_variation_field($var_data, $map_config, 'height', array('height', 'Height'));
            
            if ($dimensions_inherit && ($var_length === null || $var_length === '') && ($var_width === null || $var_width === '') && ($var_height === null || $var_height === '')) {
                // Inherit all dimensions from parent
                $parent_length = $parent_product->get_length();
                $parent_width = $parent_product->get_width();
                $parent_height = $parent_product->get_height();
                if ($parent_length) { $variation->set_length(floatval($parent_length)); }
                if ($parent_width) { $variation->set_width(floatval($parent_width)); }
                if ($parent_height) { $variation->set_height(floatval($parent_height)); }
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Inherited dimensions from parent: {$parent_length}x{$parent_width}x{$parent_height}\n", FILE_APPEND); }
            } else {
                // Use variation-specific dimensions
                if ($var_length !== null && $var_length !== '') {
                    $variation->set_length(floatval($var_length));
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set length: $var_length\n", FILE_APPEND); }
                }
                if ($var_width !== null && $var_width !== '') {
                    $variation->set_width(floatval($var_width));
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set width: $var_width\n", FILE_APPEND); }
                }
                if ($var_height !== null && $var_height !== '') {
                    $variation->set_height(floatval($var_height));
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set height: $var_height\n", FILE_APPEND); }
                }
            }
            
            // Set Description
            $var_description = $this->get_variation_field($var_data, $map_config, 'description', array('description', 'Description'));
            if ($var_description !== null) {
                $variation->set_description($var_description);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set description\n", FILE_APPEND); }
            }
            
            // Set Shipping Class
            $var_shipping_class = $this->get_variation_field($var_data, $map_config, 'shipping_class', array('shipping_class'));
            if ($var_shipping_class !== null && !empty($var_shipping_class)) {
                $shipping_class_id = $this->get_shipping_class_id($var_shipping_class);
                if ($shipping_class_id) {
                    $variation->set_shipping_class_id($shipping_class_id);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set shipping class: $var_shipping_class\n", FILE_APPEND); }
                }
            }
            
            // Set Low Stock Amount
            $var_low_stock = $this->get_variation_field($var_data, $map_config, 'low_stock_amount', array('low_stock_amount', 'low_stock_threshold'));
            if ($var_low_stock !== null) {
                $variation->set_low_stock_amount((int)$var_low_stock);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set low stock amount: $var_low_stock\n", FILE_APPEND); }
            }
            
            // Set Sale Price Dates
            $var_sale_from = $this->get_variation_field($var_data, $map_config, 'sale_price_dates_from', array('sale_price_dates_from', 'sale_date_from'));
            if ($var_sale_from !== null && !empty($var_sale_from)) {
                $date_from = strtotime($var_sale_from);
                if ($date_from) {
                    $variation->set_date_on_sale_from($date_from);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set sale date from: $var_sale_from\n", FILE_APPEND); }
                }
            }
            
            $var_sale_to = $this->get_variation_field($var_data, $map_config, 'sale_price_dates_to', array('sale_price_dates_to', 'sale_date_to'));
            if ($var_sale_to !== null && !empty($var_sale_to)) {
                $date_to = strtotime($var_sale_to);
                if ($date_to) {
                    $variation->set_date_on_sale_to($date_to);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set sale date to: $var_sale_to\n", FILE_APPEND); }
                }
            }
            
            // Set Virtual - variation value overrides parent, otherwise inherit from parent
            $var_virtual = $this->get_variation_field($var_data, $map_config, 'virtual', array('virtual'));
            if ($var_virtual !== null && $var_virtual !== '') {
                // Variation has explicit virtual setting - use it
                $is_virtual = in_array(strtolower($var_virtual), array('yes', '1', 'true', 'on'));
                $variation->set_virtual($is_virtual);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set virtual from variation: " . ($is_virtual ? 'yes' : 'no') . "\n", FILE_APPEND); }
            } else {
                // Inherit from parent product
                $parent_virtual = $parent_product->get_virtual();
                $variation->set_virtual($parent_virtual);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Inherited virtual from parent: " . ($parent_virtual ? 'yes' : 'no') . "\n", FILE_APPEND); }
            }
            
            // Set Downloadable - variation value overrides parent, otherwise inherit from parent
            $var_downloadable = $this->get_variation_field($var_data, $map_config, 'downloadable', array('downloadable'));
            if ($var_downloadable !== null && $var_downloadable !== '') {
                // Variation has explicit downloadable setting - use it
                $is_downloadable = in_array(strtolower($var_downloadable), array('yes', '1', 'true', 'on'));
                $variation->set_downloadable($is_downloadable);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set downloadable from variation: " . ($is_downloadable ? 'yes' : 'no') . "\n", FILE_APPEND); }
            } else {
                // Inherit from parent product
                $parent_downloadable = $parent_product->get_downloadable();
                $variation->set_downloadable($parent_downloadable);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Inherited downloadable from parent: " . ($parent_downloadable ? 'yes' : 'no') . "\n", FILE_APPEND); }
            }
            
            // Set Download Limit
            $var_download_limit = $this->get_variation_field($var_data, $map_config, 'download_limit', array('download_limit'));
            if ($var_download_limit !== null && $var_download_limit !== '') {
                $variation->set_download_limit((int)$var_download_limit);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set download limit: $var_download_limit\n", FILE_APPEND); }
            }
            
            // Set Download Expiry
            $var_download_expiry = $this->get_variation_field($var_data, $map_config, 'download_expiry', array('download_expiry'));
            if ($var_download_expiry !== null && $var_download_expiry !== '') {
                $variation->set_download_expiry((int)$var_download_expiry);
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set download expiry: $var_download_expiry\n", FILE_APPEND); }
            }
            
            // Set Downloads (downloadable files)
            $var_downloads = $this->get_variation_field($var_data, $map_config, 'downloads', array('downloads', 'downloadable_files', 'download_files'));
            if ($var_downloads !== null && !empty($var_downloads)) {
                $downloads = $this->parse_downloadable_files($var_downloads);
                if (!empty($downloads)) {
                    $variation->set_downloads($downloads);
                    // Auto-enable downloadable if files are set
                    $variation->set_downloadable(true);
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set " . count($downloads) . " download files\n", FILE_APPEND); }
                }
            }
            
            // Set Image (skip example.com URLs and use try-catch)
            $var_image = $this->get_variation_field($var_data, $map_config, 'image', array('image', 'image_url', 'img', 'photo'));
            if ($var_image && filter_var($var_image, FILTER_VALIDATE_URL)) {
                // Skip example.com or placeholder URLs
                if (strpos($var_image, 'example.com') === false && strpos($var_image, 'placeholder') === false) {
                    try {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Importing image: $var_image\n", FILE_APPEND); }
                        $image_id = $this->download_and_attach_image($var_image, $product_id);
                        if ($image_id) {
                            $variation->set_image_id($image_id);
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set image ID: $image_id\n", FILE_APPEND); }
                        }
                    } catch (Exception $e) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Image import failed: " . $e->getMessage() . "\n", FILE_APPEND); }
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Skipping example/placeholder image: $var_image\n", FILE_APPEND); }
                }
            }
            
            // Save variation
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Saving variation...\n", FILE_APPEND); }
            try {
                $variation_id = $variation->save();
                
                if ($variation_id) {
                    $variation_count++;
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ✓ Created variation ID: $variation_id\n", FILE_APPEND); }
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Created mapped variation ID: ' . $variation_id); }
                    
                    // CRITICAL: Force stock status after save to prevent WooCommerce from overriding
                    // WooCommerce sometimes resets stock_status during save() process
                    $saved_stock_status = get_post_meta($variation_id, '_stock_status', true);
                    
                    // Determine intended status based on mapping/data
                    if ($var_stock !== null && $var_stock !== '') {
                        // Stock quantity was provided - base status on quantity
                        $intended_status = ((int)$var_stock > 0) ? 'instock' : 'outofstock';
                    } elseif ($var_stock_status !== null && !empty($var_stock_status)) {
                        // Stock status was explicitly mapped - use it
                        $intended_status = (strtolower($var_stock_status) === 'outofstock') ? 'outofstock' : 'instock';
                    } else {
                        // No stock info - default to instock
                        $intended_status = 'instock';
                    }
                    
                    // Fix if saved status doesn't match intended
                    if ($saved_stock_status !== $intended_status) {
                        update_post_meta($variation_id, '_stock_status', $intended_status);
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Fixed stock_status from '$saved_stock_status' to: $intended_status\n", FILE_APPEND); }
                    }
                    
                    // Ensure manage_stock is set correctly
                    $saved_manage_stock = get_post_meta($variation_id, '_manage_stock', true);
                    if ($var_stock === null && $saved_manage_stock !== 'no') {
                        update_post_meta($variation_id, '_manage_stock', 'no');
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Fixed manage_stock to: no\n", FILE_APPEND); }
                    }
                    
                    // Set Product Identifiers (EAN, UPC, GTIN, ISBN, MPN) - must be after save
                    $identifier_fields = array('ean', 'upc', 'gtin', 'isbn', 'mpn');
                    foreach ($identifier_fields as $id_field) {
                        $var_id_value = $this->get_variation_field($var_data, $map_config, $id_field, array($id_field, strtoupper($id_field)));
                        if ($var_id_value !== null && !empty($var_id_value)) {
                            update_post_meta($variation_id, '_' . $id_field, sanitize_text_field($var_id_value));
                            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set $id_field: $var_id_value\n", FILE_APPEND); }
                        }
                    }
                    
                    // Set Custom Meta Fields for variations (var_meta from mapping)
                    $var_meta_fields = $map_config['var_meta'] ?? array();
                    if (!empty($var_meta_fields) && is_array($var_meta_fields)) {
                        foreach ($var_meta_fields as $meta_item) {
                            $meta_key = $meta_item['key'] ?? '';
                            $meta_source = $meta_item['source'] ?? '';
                            
                            if (empty($meta_key) || empty($meta_source)) {
                                continue;
                            }
                            
                            // Parse the source value from variation data
                            $meta_value = $this->parse_field_placeholders($meta_source, $var_data);
                            
                            if ($meta_value !== null && $meta_value !== '') {
                                // Ensure meta key starts with underscore if it's meant to be hidden
                                $clean_key = sanitize_key($meta_key);
                                update_post_meta($variation_id, $clean_key, sanitize_text_field($meta_value));
                                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Set custom meta $clean_key: $meta_value\n", FILE_APPEND); }
                            }
                        }
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ✗ Failed to save variation (returned 0)\n", FILE_APPEND); }
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ✗ Exception saving variation: " . $e->getMessage() . "\n", FILE_APPEND); }
            }
        }
        
        return $variation_count;
    }
    
    /**
     * Get variation field value from mapped config or default paths.
     *
     * @param array $var_data Variation data
     * @param array $map_config Map configuration
     * @param string $field_name Field name (sku, price, stock, etc.)
     * @param array $default_paths Default paths to try
     * @return mixed|null Field value or null
     */
    private function get_variation_field($var_data, $map_config, $field_name, $default_paths = array()) {
        // First try mapped field
        $mapped_source = $map_config[$field_name . '_source'] ?? '';
        if (!empty($mapped_source)) {
            // Clean the source path - remove {curly braces} wrapper
            $clean_source = trim($mapped_source);
            if (preg_match('/^\{(.+)\}$/', $clean_source, $matches)) {
                $clean_source = $matches[1];
            }
            
            // Convert absolute variation path to relative path
            // E.g., "variations.variation[0].sku" → "sku"
            // E.g., "variations.variation.sku" → "sku"
            $container_xpath = $map_config['container_xpath'] ?? 'variations.variation';
            $prefix_patterns = array(
                $container_xpath . '[0].',
                $container_xpath . '[1].',
                $container_xpath . '[2].',
                $container_xpath . '.',
                // Also try common defaults
                'variations.variation[0].',
                'variations.variation.',
            );
            
            foreach ($prefix_patterns as $prefix) {
                if (strpos($clean_source, $prefix) === 0) {
                    $clean_source = substr($clean_source, strlen($prefix));
                    break;
                }
            }
            
            $value = $this->get_nested_value($var_data, $clean_source);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        
        // Try default paths
        foreach ($default_paths as $path) {
            $value = $this->get_nested_value($var_data, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Parse downloadable files from string or array.
     * 
     * Formats supported:
     * - Single URL: https://example.com/file.pdf
     * - Name|URL: File Name|https://example.com/file.pdf
     * - Multiple comma-separated: Name1|URL1,Name2|URL2
     * - Multiple URLs concatenated: URL1URL2 (split by http/https)
     *
     * @param string|array $downloads Downloads data
     * @return array Array of WC_Product_Download objects
     */
    private function parse_downloadable_files($downloads) {
        $download_objects = array();
        
        // Handle array input (e.g., from XML)
        if (is_array($downloads)) {
            $downloads = implode(',', array_filter($downloads));
        }
        
        $downloads = trim($downloads);
        if (empty($downloads)) {
            return array();
        }
        
        // Check if URLs are concatenated without separator (URL1URL2)
        // Split by http:// or https:// keeping the protocol
        if (preg_match('/https?:\/\/.*https?:\/\//', $downloads) && strpos($downloads, ',') === false && strpos($downloads, '|') === false) {
            // Split by http(s):// but keep the protocol in result
            $parts = preg_split('/(https?:\/\/)/', $downloads, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $urls = array();
            for ($i = 0; $i < count($parts); $i++) {
                if (preg_match('/^https?:$/', $parts[$i] . ':')) {
                    // This is a protocol, combine with next part
                    if (isset($parts[$i + 1])) {
                        $urls[] = $parts[$i] . $parts[$i + 1];
                        $i++;
                    }
                }
            }
            if (!empty($urls)) {
                $downloads = implode(',', $urls);
            }
        }
        
        // Split by comma for multiple files
        $files = array_map('trim', explode(',', $downloads));
        
        foreach ($files as $index => $file) {
            if (empty($file)) {
                continue;
            }
            
            $name = '';
            $url = '';
            
            // Check if format is Name|URL
            if (strpos($file, '|') !== false) {
                $parts = explode('|', $file, 2);
                $name = trim($parts[0]);
                $url = trim($parts[1]);
            } else {
                // Just URL
                $url = $file;
                // Generate name from filename
                $path_parts = parse_url($url, PHP_URL_PATH);
                if ($path_parts) {
                    $name = basename($path_parts);
                } else {
                    $name = 'Download ' . ($index + 1);
                }
            }
            
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            
            // Create WC_Product_Download object
            $download = new WC_Product_Download();
            $download->set_id(md5($url)); // Generate unique ID from URL
            $download->set_name($name);
            $download->set_file($url);
            
            $download_objects[] = $download;
        }
        
        return $download_objects;
    }
    
    /**
     * Get shipping class ID by name or slug.
     *
     * @param string $shipping_class_name Shipping class name or slug
     * @return int|false Shipping class term ID or false
     */
    private function get_shipping_class_id($shipping_class_name) {
        if (empty($shipping_class_name)) {
            return false;
        }
        
        // First try to find by slug
        $term = get_term_by('slug', sanitize_title($shipping_class_name), 'product_shipping_class');
        if ($term) {
            return $term->term_id;
        }
        
        // Try to find by name
        $term = get_term_by('name', $shipping_class_name, 'product_shipping_class');
        if ($term) {
            return $term->term_id;
        }
        
        // Create if doesn't exist
        $result = wp_insert_term($shipping_class_name, 'product_shipping_class');
        if (!is_wp_error($result)) {
            return $result['term_id'];
        }
        
        return false;
    }
    
    /**
     * Get or create attribute term.
     *
     * @param string $term_name Term name
     * @param string $taxonomy Taxonomy name
     * @return WP_Term|false Term object or false
     */
    private function get_or_create_attribute_term($term_name, $taxonomy) {
        $term_name = trim($term_name);
        if (empty($term_name)) {
            return false;
        }
        
        // Check if term exists
        $term = get_term_by('name', $term_name, $taxonomy);
        if ($term) {
            return $term;
        }
        
        // Also check by slug
        $term_slug = sanitize_title($term_name);
        $term = get_term_by('slug', $term_slug, $taxonomy);
        if ($term) {
            return $term;
        }
        
        // Create new term
        $result = wp_insert_term($term_name, $taxonomy);
        if (!is_wp_error($result)) {
            return get_term($result['term_id'], $taxonomy);
        }
        
        return false;
    }
    
    /**
     * Create variations automatically from attribute combinations.
     *
     * @since    1.0.0
     * @param    int $product_id Parent product ID
     * @param    array $product_attributes Saved attributes array
     * @param    array $attributes_config Attributes configuration
     * @param    WC_Product $parent_product Parent product object
     * @return   int Number of variations created
     */
    private function create_auto_variations($product_id, $product_attributes, $attributes_config, $parent_product) {
        $log_file = WP_CONTENT_DIR . '/variations_debug.log';
        $variation_count = 0;
        
        // Get all variation attributes (is_variation = 1)
        $variation_attributes = array();
        foreach ($product_attributes as $attr_name => $attr_data) {
            if (!empty($attr_data['is_variation'])) {
                // Get all terms for this attribute
                $terms = get_terms(array(
                    'taxonomy' => $attr_name,
                    'hide_empty' => false,
                    'object_ids' => $product_id
                ));
                
                if (!is_wp_error($terms) && !empty($terms)) {
                    $variation_attributes[$attr_name] = wp_list_pluck($terms, 'slug');
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  Attribute: $attr_name - Values: " . implode(', ', $variation_attributes[$attr_name]) . "\n", FILE_APPEND); }
                }
            }
        }
        
        if (empty($variation_attributes)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "ERROR: No variation attributes found!\n", FILE_APPEND); }
            return 0;
        }
        
        // Generate all possible combinations
        $combinations = $this->get_attribute_combinations($variation_attributes);
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Generated " . count($combinations) . " variation combinations\n", FILE_APPEND); }
        
        foreach ($combinations as $combination) {
            // Create variation
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            
            // Set attributes for this variation
            $variation->set_attributes($combination);
            
            // Get variation config for pricing and stock
            $auto_config = $attributes_config['auto_config'] ?? array();
            
            // Set price (default to parent price)
            $parent_price = $parent_product->get_regular_price();
            if (!empty($parent_price)) {
                $variation->set_regular_price($parent_price);
                $variation->set_price($parent_price);
            }
            
            // Set SKU pattern if configured
            if (!empty($auto_config['sku_pattern'])) {
                $parent_sku = $parent_product->get_sku();
                $sku_suffix = implode('-', array_values($combination));
                $variation_sku = $parent_sku . '-' . $sku_suffix;
                $variation->set_sku($variation_sku);
            }
            
            // Set stock
            if (isset($auto_config['stock_mode'])) {
                if ($auto_config['stock_mode'] === 'fixed' && isset($auto_config['stock_fixed'])) {
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity((int)$auto_config['stock_fixed']);
                    $variation->set_stock_status('instock');
                } elseif ($auto_config['stock_mode'] === 'parent') {
                    $parent_stock = $parent_product->get_stock_quantity();
                    if ($parent_stock !== null) {
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity($parent_stock);
                        $variation->set_stock_status('instock');
                    }
                }
            } else {
                // Default: inherit from parent
                $variation->set_stock_status('instock');
            }
            
            // Inherit Virtual/Downloadable from parent product (auto-generated variations)
            $variation->set_virtual($parent_product->get_virtual());
            $variation->set_downloadable($parent_product->get_downloadable());
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                file_put_contents($log_file, "    Inherited virtual: " . ($parent_product->get_virtual() ? 'yes' : 'no') . ", downloadable: " . ($parent_product->get_downloadable() ? 'yes' : 'no') . " from parent\n", FILE_APPEND); 
            }
            
            $variation_id = $variation->save();
            
            if ($variation_id) {
                $variation_count++;
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  ✓ Created variation ID: $variation_id - " . json_encode($combination) . "\n", FILE_APPEND); }
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Created variation ID: ' . $variation_id); }
            }
        }
        
        return $variation_count;
    }
    
    /**
     * Get all possible attribute combinations for variations.
     *
     * @since    1.0.0
     * @param    array $attributes Associative array of attributes and their values
     * @return   array Array of all possible combinations
     */
    private function get_attribute_combinations($attributes) {
        if (empty($attributes)) {
            return array();
        }
        
        $result = array(array());
        
        foreach ($attributes as $attr_name => $values) {
            $temp = array();
            foreach ($result as $result_item) {
                foreach ($values as $value) {
                    $temp[] = array_merge($result_item, array($attr_name => $value));
                }
            }
            $result = $temp;
        }
        
        return $result;
    }

    /**
     * Register WooCommerce attribute taxonomy.
     *
     * @since    1.0.0
     * @param    string $attr_name Attribute name (e.g., "pa_material")
     * @param    string $attr_label Attribute label (e.g., "Material")
     */
    private function register_attribute_taxonomy($attr_name, $attr_label) {
        global $wpdb;

        // Check if attribute already exists in WooCommerce
        $attribute_slug = str_replace('pa_', '', $attr_name);
        $attribute_id = $wpdb->get_var($wpdb->prepare(
            "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
            $attribute_slug
        ));

        if (!$attribute_id) {
            // Create new attribute
            $wpdb->insert(
                $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                array(
                    'attribute_name' => $attribute_slug,
                    'attribute_label' => $attr_label,
                    'attribute_type' => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public' => 0
                )
            );

            // Clear WooCommerce attribute cache
            delete_transient('wc_attribute_taxonomies');
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Created new attribute taxonomy: ' . $attr_name); }
        } else {
            // Update label if it's incorrect (e.g., contains "pa_" prefix)
            $current_label = $wpdb->get_var($wpdb->prepare(
                "SELECT attribute_label FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_id = %d",
                $attribute_id
            ));
            
            // Fix label if it starts with pa_ or is different from expected
            if ($current_label && (strpos($current_label, 'pa_') === 0 || $current_label !== $attr_label)) {
                $wpdb->update(
                    $wpdb->prefix . 'woocommerce_attribute_taxonomies',
                    array('attribute_label' => $attr_label),
                    array('attribute_id' => $attribute_id)
                );
                
                // Clear WooCommerce attribute cache
                delete_transient('wc_attribute_taxonomies');
                
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Updated attribute label: ' . $attr_name . ' → ' . $attr_label); }
            }
        }

        // Register taxonomy for this request
        if (!taxonomy_exists($attr_name)) {
            register_taxonomy($attr_name, 'product', array(
                'labels' => array('name' => $attr_label),
                'hierarchical' => false,
                'show_ui' => false,
                'query_var' => true,
                'rewrite' => false,
            ));
        }
    }

    /**
     * Schedule background processing.
     *
     * @since    1.0.0
     */
    private function schedule_background_processing() {
        wp_schedule_single_event(time(), 'wc_xml_csv_ai_import_process_chunk', array($this->import_id, 0, 5));
    }

    /**
     * Schedule next processing chunk.
     *
     * @since    1.0.0
     * @param    int $offset Next offset
     * @param    int $limit Chunk size
     */
    private function schedule_next_chunk($offset, $limit) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import_logs/import_debug.log';
        
        // Check if this exact event already exists to prevent duplicates
        $existing = wp_next_scheduled('wc_xml_csv_ai_import_process_chunk', array($this->import_id, $offset, $limit));
        if ($existing) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "SCHEDULE_CHUNK: Event already exists for import={$this->import_id}, offset={$offset}\n", FILE_APPEND); }
            return;
        }
        
        // Also clear any existing events for this import with offset=0 if we're scheduling a higher offset
        if ($offset > 0) {
            $offset0_scheduled = wp_next_scheduled('wc_xml_csv_ai_import_process_chunk', array($this->import_id, 0, $limit));
            if ($offset0_scheduled) {
                wp_unschedule_event($offset0_scheduled, 'wc_xml_csv_ai_import_process_chunk', array($this->import_id, 0, $limit));
                if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "SCHEDULE_CHUNK: Cleared stale offset=0 event\n", FILE_APPEND); }
            }
        }
        
        // Schedule immediately (time() instead of time()+5) for faster processing
        $result = wp_schedule_single_event(time(), 'wc_xml_csv_ai_import_process_chunk', array($this->import_id, $offset, $limit));
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "SCHEDULE_CHUNK: Scheduled import={$this->import_id}, offset={$offset}, limit={$limit}, result=" . ($result ? 'OK' : 'FAIL') . "\n", FILE_APPEND); }
    }

    /**
     * Update import status.
     *
     * @since    1.0.0
     * @param    string $status New status
     */
    private function update_import_status($status) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'wc_itp_imports',
            array('status' => $status),
            array('id' => $this->import_id)
        );
    }

    /**
     * Phase 2: Resolve product relationships after all products are imported.
     * Handles: Grouped product children, Upsells, Cross-sells
     * This is called when import completes to link products by SKU.
     *
     * @since    1.0.0
     */
    private function resolve_product_relationships() {
        global $wpdb;
        
        $this->log('info', __('Starting Phase 2: Resolving product relationships...', 'bootflow-woocommerce-xml-csv-importer'));
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ RESOLVE_RELATIONSHIPS: Starting Phase 2'); }
        
        $resolved_count = 0;
        $error_count = 0;
        
        // 1. Resolve Grouped Product Children
        $grouped_products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm.meta_value as pending_skus
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_import ON p.ID = pm_import.post_id
             WHERE pm.meta_key = '_pending_grouped_skus'
               AND pm_import.meta_key = '_wc_import_id'
               AND pm_import.meta_value = %s
               AND p.post_type = 'product'",
            $this->import_id
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ RESOLVE_RELATIONSHIPS: Found ' . count($grouped_products) . ' grouped products to resolve'); }
        
        foreach ($grouped_products as $grouped) {
            try {
                $product = wc_get_product($grouped->ID);
                if (!$product || !$product instanceof WC_Product_Grouped) {
                    continue;
                }
                
                $sku_list = $grouped->pending_skus;
                $child_ids = $this->resolve_skus_to_ids($sku_list);
                
                if (!empty($child_ids)) {
                    $product->set_children($child_ids);
                    $product->save();
                    
                    // Remove the pending meta
                    delete_post_meta($grouped->ID, '_pending_grouped_skus');
                    
                    $this->log('info', sprintf(__('Resolved grouped product ID %d with %d children', 'bootflow-woocommerce-xml-csv-importer'), $grouped->ID, count($child_ids)));
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ RESOLVE_RELATIONSHIPS: Grouped ID=' . $grouped->ID . ' linked to children: ' . implode(',', $child_ids)); }
                    $resolved_count++;
                } else {
                    $this->log('warning', sprintf(__('Could not resolve children for grouped product ID %d (SKUs: %s)', 'bootflow-woocommerce-xml-csv-importer'), $grouped->ID, $sku_list));
                    $error_count++;
                }
            } catch (Exception $e) {
                $this->log('error', sprintf(__('Error resolving grouped product ID %d: %s', 'bootflow-woocommerce-xml-csv-importer'), $grouped->ID, $e->getMessage()));
                $error_count++;
            }
        }
        
        // 2. Resolve Upsells
        $upsell_products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm.meta_value as pending_skus
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_import ON p.ID = pm_import.post_id
             WHERE pm.meta_key = '_pending_upsell_skus'
               AND pm_import.meta_key = '_wc_import_id'
               AND pm_import.meta_value = %s
               AND p.post_type = 'product'",
            $this->import_id
        ));
        
        foreach ($upsell_products as $item) {
            try {
                $product = wc_get_product($item->ID);
                if (!$product) {
                    continue;
                }
                
                $upsell_ids = $this->resolve_skus_to_ids($item->pending_skus);
                
                if (!empty($upsell_ids)) {
                    $product->set_upsell_ids($upsell_ids);
                    $product->save();
                    delete_post_meta($item->ID, '_pending_upsell_skus');
                    $resolved_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        // 3. Resolve Cross-sells
        $crosssell_products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm.meta_value as pending_skus
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm_import ON p.ID = pm_import.post_id
             WHERE pm.meta_key = '_pending_crosssell_skus'
               AND pm_import.meta_key = '_wc_import_id'
               AND pm_import.meta_value = %s
               AND p.post_type = 'product'",
            $this->import_id
        ));
        
        foreach ($crosssell_products as $item) {
            try {
                $product = wc_get_product($item->ID);
                if (!$product) {
                    continue;
                }
                
                $crosssell_ids = $this->resolve_skus_to_ids($item->pending_skus);
                
                if (!empty($crosssell_ids)) {
                    $product->set_cross_sell_ids($crosssell_ids);
                    $product->save();
                    delete_post_meta($item->ID, '_pending_crosssell_skus');
                    $resolved_count++;
                }
            } catch (Exception $e) {
                $error_count++;
            }
        }
        
        $this->log('info', sprintf(__('Phase 2 completed: %d relationships resolved, %d errors', 'bootflow-woocommerce-xml-csv-importer'), $resolved_count, $error_count));
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ RESOLVE_RELATIONSHIPS: Completed - resolved=' . $resolved_count . ', errors=' . $error_count); }
    }

    /**
     * Phase 3: Sync stock status for all variable products.
     * This must run LAST to ensure WooCommerce hooks don't override our stock status.
     * Uses direct DB queries to bypass all WooCommerce hooks.
     * Public because it's called from shutdown hook.
     *
     * @since    1.0.0
     */
    public function sync_variable_products_stock_status() {
        global $wpdb;
        
        $this->log('info', __('Starting Phase 3: Syncing variable products stock status...', 'bootflow-woocommerce-xml-csv-importer'));
        
        // Get all variable products (no prepare needed - no user input)
        $variable_products = $wpdb->get_col(
            "SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             WHERE p.post_type = 'product'
               AND tt.taxonomy = 'product_type'
               AND t.slug = 'variable'
               AND p.post_status = 'publish'"
        );
        
        $synced_count = 0;
        
        foreach ($variable_products as $product_id) {
            // Get all variations for this product
            $children = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'",
                $product_id
            ));
            
            if (empty($children)) {
                continue;
            }
            
            // Check if any variation has instock status
            $instock_count = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE post_id IN (" . implode(',', array_map('intval', $children)) . ") 
                 AND meta_key = '_stock_status' 
                 AND meta_value = 'instock'"
            );
            
            $new_stock_status = ($instock_count > 0) ? 'instock' : 'outofstock';
            
            // Direct SQL update - bypass all hooks
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE post_id = %d AND meta_key = '_stock_status'",
                $new_stock_status,
                $product_id
            ));
            
            $synced_count++;
        }
        
        $this->log('info', sprintf(__('Phase 3 completed: %d variable products stock status synced', 'bootflow-woocommerce-xml-csv-importer'), $synced_count));
    }

    /**
     * Convert comma-separated SKU list to array of product IDs.
     *
     * @since    1.0.0
     * @param    string $sku_list Comma-separated SKU list
     * @return   array Array of product IDs
     */
    private function resolve_skus_to_ids($sku_list) {
        if (empty($sku_list)) {
            return array();
        }
        
        $skus = array_map('trim', explode(',', $sku_list));
        $ids = array();
        
        foreach ($skus as $sku) {
            if (empty($sku)) {
                continue;
            }
            
            $product_id = wc_get_product_id_by_sku($sku);
            if ($product_id) {
                $ids[] = $product_id;
            } else {
                $this->log('warning', sprintf(__('Could not find product with SKU: %s', 'bootflow-woocommerce-xml-csv-importer'), $sku));
            }
        }
        
        return $ids;
    }

    /**
     * Handle missing products - products that were in previous import but not in current.
     * This is called when import completes and handle_missing is enabled.
     *
     * @since    1.0.0
     */
    private function handle_missing_products() {
        global $wpdb;
        
        $action = $this->config['missing_action'] ?? 'draft';
        $delete_variations = $this->config['delete_variations'] ?? 1;
        
        $this->log('info', sprintf(__('Processing missing products with action: %s', 'bootflow-woocommerce-xml-csv-importer'), $action));
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Starting with action=' . $action . ', delete_variations=' . $delete_variations); }
        
        // Find all products that:
        // 1. Have _wc_import_id = current import ID (were last updated by this import)
        // 2. Have _wc_import_date OLDER than import start time (not updated in THIS run)
        
        // Get import start time from the import record
        $import = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
            $this->import_id
        ));
        
        if (!$import) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Import not found, aborting'); }
            return;
        }
        
        // CRITICAL FIX: Use the saved import start time from when this run began
        // This fixes the bug where last_run was from the last chunk, not the import start
        $import_start_option_key = 'wc_import_start_time_' . $this->import_id;
        $import_start_time = get_option($import_start_option_key);
        
        // Fallback to created_at if no saved start time (shouldn't happen in normal flow)
        if (empty($import_start_time)) {
            $import_start_time = $import->created_at;
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: WARNING - No saved start time found, using created_at: ' . $import_start_time); }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Using saved import start time: ' . $import_start_time); }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Import start time = ' . $import_start_time); }
        
        // Query for products that belong to this import but weren't updated in this run
        $missing_products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm_sku.meta_value as sku, pm_date.meta_value as import_date
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm_import ON p.ID = pm_import.post_id AND pm_import.meta_key = '_wc_import_id'
             LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
             LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = '_wc_import_date'
             WHERE pm_import.meta_value = %s
               AND p.post_type IN ('product', 'product_variation')
               AND p.post_status NOT IN ('trash', 'auto-draft')
               AND (pm_date.meta_value IS NULL OR pm_date.meta_value < %s)",
            $this->import_id,
            $import_start_time
        ));
        
        $count = count($missing_products);
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Found ' . $count . ' missing products'); }
        
        if ($count === 0) {
            $this->log('info', __('No missing products found.', 'bootflow-woocommerce-xml-csv-importer'));
            return;
        }
        
        $processed = 0;
        $errors = 0;
        
        foreach ($missing_products as $product) {
            try {
                $product_id = $product->ID;
                $sku = $product->sku ?? 'N/A';
                
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Processing product ID=' . $product_id . ', SKU=' . $sku . ', action=' . $action); }
                
                // Get product object
                $wc_product = wc_get_product($product_id);
                if (!$wc_product) {
                    continue;
                }
                
                // Check if this is a variation - if parent is being processed, skip variation
                if ($wc_product->is_type('variation')) {
                    // Variations will be handled with their parent
                    continue;
                }
                
                switch ($action) {
                    case 'draft':
                        // Move to draft
                        wp_update_post(array(
                            'ID' => $product_id,
                            'post_status' => 'draft'
                        ));
                        $this->log('info', sprintf(__('Product "%s" (SKU: %s) moved to draft - no longer in feed.', 'bootflow-woocommerce-xml-csv-importer'), $product->post_title, $sku), $sku);
                        break;
                        
                    case 'outofstock':
                        // Mark as out of stock
                        $wc_product->set_stock_status('outofstock');
                        $wc_product->set_manage_stock(true);
                        $wc_product->set_stock_quantity(0);
                        $wc_product->save();
                        $this->log('info', sprintf(__('Product "%s" (SKU: %s) marked as out of stock - no longer in feed.', 'bootflow-woocommerce-xml-csv-importer'), $product->post_title, $sku), $sku);
                        break;
                        
                    case 'backorder':
                        // Allow backorder
                        $wc_product->set_stock_status('onbackorder');
                        $wc_product->set_manage_stock(true);
                        $wc_product->set_stock_quantity(0);
                        $wc_product->set_backorders('yes');
                        $wc_product->save();
                        $this->log('info', sprintf(__('Product "%s" (SKU: %s) set to backorder - no longer in feed.', 'bootflow-woocommerce-xml-csv-importer'), $product->post_title, $sku), $sku);
                        break;
                        
                    case 'trash':
                        // Move to trash
                        wp_trash_post($product_id);
                        $this->log('info', sprintf(__('Product "%s" (SKU: %s) moved to trash - no longer in feed.', 'bootflow-woocommerce-xml-csv-importer'), $product->post_title, $sku), $sku);
                        break;
                        
                    case 'delete':
                        // Permanently delete
                        wp_delete_post($product_id, true);
                        $this->log('warning', sprintf(__('Product "%s" (SKU: %s) permanently deleted - no longer in feed.', 'bootflow-woocommerce-xml-csv-importer'), $product->post_title, $sku), $sku);
                        break;
                }
                
                // Handle variations for variable products
                if ($delete_variations && $wc_product->is_type('variable') && $action !== 'delete') {
                    $variation_ids = $wc_product->get_children();
                    foreach ($variation_ids as $var_id) {
                        $variation = wc_get_product($var_id);
                        if (!$variation) continue;
                        
                        switch ($action) {
                            case 'draft':
                                wp_update_post(array('ID' => $var_id, 'post_status' => 'draft'));
                                break;
                            case 'outofstock':
                                $variation->set_stock_status('outofstock');
                                $variation->set_stock_quantity(0);
                                $variation->save();
                                break;
                            case 'backorder':
                                $variation->set_stock_status('onbackorder');
                                $variation->set_stock_quantity(0);
                                $variation->set_backorders('yes');
                                $variation->save();
                                break;
                            case 'trash':
                                wp_trash_post($var_id);
                                break;
                        }
                    }
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Processed ' . count($variation_ids) . ' variations for product ID=' . $product_id); }
                }
                
                $processed++;
                
            } catch (Exception $e) {
                $errors++;
                $this->log('error', sprintf(__('Error processing missing product ID %d: %s', 'bootflow-woocommerce-xml-csv-importer'), $product->ID, $e->getMessage()));
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Error - ' . $e->getMessage()); }
            }
        }
        
        $this->log('info', sprintf(__('Missing products cleanup completed: %d processed, %d errors.', 'bootflow-woocommerce-xml-csv-importer'), $processed, $errors));
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ HANDLE_MISSING: Completed - processed=' . $processed . ', errors=' . $errors); }
        
        // Clean up the import start time option after processing is complete
        delete_option($import_start_option_key);
    }

    /**
     * Log import message.
     *
     * @since    1.0.0
     * @param    string $type Log type
     * @param    string $message Log message
     * @param    string $sku Product SKU (optional)
     */
    private function log($type, $message, $sku = '') {
        global $wpdb;

        // Don't save debug logs to database - they clutter the UI
        // Debug logs only go to error_log when WP_DEBUG is enabled
        if ($type === 'debug') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WC Import Debug] ' . $message);
            }
            return;
        }

        $wpdb->insert(
            $wpdb->prefix . 'wc_itp_import_logs',
            array(
                'import_id' => $this->import_id,
                'level' => $type,  // Changed from log_type to level to match table structure
                'message' => $message,
                'product_sku' => $sku,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * Validate import data.
     *
     * @since    1.0.0
     * @param    array $import_data Import configuration
     */
    private function validate_import_data($import_data) {
        $import_name = $import_data['import_name'] ?? $import_data['name'] ?? '';
        if (empty($import_name)) {
            throw new Exception(__('Import name is required.', 'bootflow-woocommerce-xml-csv-importer'));
        }

        if (empty($import_data['file_path']) || !file_exists($import_data['file_path'])) {
            throw new Exception(__('Valid file path is required.', 'bootflow-woocommerce-xml-csv-importer'));
        }

        if (!in_array($import_data['file_type'], array('xml', 'csv'))) {
            throw new Exception(__('File type must be XML or CSV.', 'bootflow-woocommerce-xml-csv-importer'));
        }
    }

    /**
     * Auto-assign shipping class based on product dimensions and weight using PHP formula.
     *
     * @since    1.0.0
     * @param    WC_Product $product Product object
     * @param    array $product_data Product data array with dimensions and weight
     */
    private function auto_assign_shipping_class($product, $product_data) {
        // Check if shipping class formula is configured
        $field_mappings = $this->config['field_mapping'] ?? array();
        $shipping_formula = $field_mappings['shipping_class_formula']['formula'] ?? '';
        
        // Skip if no formula configured
        if (empty($shipping_formula)) {
            return;
        }
        
        // Get dimensions and weight
        $weight = !empty($product_data['weight']) ? floatval($product_data['weight']) : 0;
        $length = !empty($product_data['length']) ? floatval($product_data['length']) : 0;
        $width = !empty($product_data['width']) ? floatval($product_data['width']) : 0;
        $height = !empty($product_data['height']) ? floatval($product_data['height']) : 0;
        
        // Skip if no dimensions/weight provided
        if ($weight == 0 && $length == 0 && $width == 0 && $height == 0) {
            return;
        }
        
        try {
            // Execute formula to get shipping class slug
            $shipping_class_slug = $this->execute_shipping_class_formula(
                $shipping_formula,
                $weight,
                $length,
                $width,
                $height
            );
            
            if (empty($shipping_class_slug)) {
                return;
            }
            
            // Get shipping class term by slug
            $shipping_class_term = get_term_by('slug', $shipping_class_slug, 'product_shipping_class');
            
            if ($shipping_class_term && !is_wp_error($shipping_class_term)) {
                // Set shipping class ID
                $product->set_shipping_class_id($shipping_class_term->term_id);
                
                // Log assignment
                if (defined('WP_DEBUG') && WP_DEBUG) { 
                    error_log(sprintf(
                        'Auto-assigned shipping class "%s" (%s) to SKU=%s based on: weight=%skg, dimensions=%s×%s×%scm',
                        $shipping_class_term->name,
                        $shipping_class_slug,
                        $product_data['sku'] ?? '',
                        $weight,
                        $length,
                        $width,
                        $height
                    )); 
                }
            } else {
                // Shipping class doesn't exist - log warning
                if (defined('WP_DEBUG') && WP_DEBUG) { 
                    error_log(sprintf(
                        'WARNING: Shipping class "%s" not found for SKU=%s. Please create it in: WooCommerce → Settings → Shipping → Shipping classes',
                        $shipping_class_slug,
                        $product_data['sku'] ?? ''
                    )); 
                }
            }
        } catch (Exception $e) {
            // Log formula execution error
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log(sprintf(
                    'ERROR: Shipping class formula error for SKU=%s: %s',
                    $product_data['sku'] ?? '',
                    $e->getMessage()
                )); 
            }
        }
    }
    
    /**
     * Execute shipping class formula with provided dimensions and weight.
     *
     * @since    1.0.0
     * @param    string $formula PHP formula code
     * @param    float $weight Product weight
     * @param    float $length Product length
     * @param    float $width Product width
     * @param    float $height Product height
     * @return   string Shipping class slug
     */
    private function execute_shipping_class_formula($formula, $weight, $length, $width, $height) {
        // PRO feature check - Shipping formulas require PRO license
        if (!WC_XML_CSV_AI_Import_Features::is_available('php_processing')) {
            if (isset($this->logger) && $this->logger) {
                $this->logger->log('info', 'Shipping formula blocked - PRO license required');
            }
            return ''; // Return empty without formula processing
        }
        
        // Prepare formula for execution
        $formula = trim($formula);
        
        if (empty($formula)) {
            return '';
        }
        
        // Log formula execution attempt
        if (isset($this->logger) && $this->logger) {
            $this->logger->log(
                'debug',
                'Executing shipping class formula',
                array(
                    'weight' => $weight,
                    'length' => $length,
                    'width' => $width,
                    'height' => $height,
                    'formula_preview' => substr($formula, 0, 100)
                )
            );
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Executing shipping class formula: weight=' . $weight . ', length=' . $length . ', width=' . $width . ', height=' . $height); }
        }
        
        // Wrap in function for sandboxing with proper error handling
        $wrapped_formula = '
            return (function($weight, $length, $width, $height) {
                try {
                    ' . $formula . '
                } catch (Throwable $e) {
                    return "";
                }
            })(' . var_export($weight, true) . ', ' 
                . var_export($length, true) . ', ' 
                . var_export($width, true) . ', ' 
                . var_export($height, true) . ');
        ';
        
        // Execute formula
        try {
            $result = @eval($wrapped_formula);
            
            if ($result === false) {
                throw new Exception('Formula returned false - possible syntax error');
            }
            
            if (isset($this->logger) && $this->logger) {
                $this->logger->log(
                    'debug',
                    'Shipping class formula result: ' . var_export($result, true),
                    array(
                        'weight' => $weight,
                        'dimensions' => "{$length}x{$width}x{$height}"
                    )
                );
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Shipping class formula result: ' . var_export($result, true)); }
            }
            
            return is_string($result) ? trim($result) : '';
            
        } catch (Throwable $e) {
            $error_msg = 'Shipping formula error: ' . $e->getMessage();
            
            if (isset($this->logger) && $this->logger) {
                $this->logger->log(
                    'error',
                    $error_msg,
                    array(
                        'formula' => $formula,
                        'weight' => $weight,
                        'length' => $length,
                        'width' => $width,
                        'height' => $height
                    )
                );
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log($error_msg . ' | Formula: ' . substr($formula, 0, 200)); }
            
            // Don't throw exception - just return empty and log error
            return '';
        }
    }

    /**
     * Enhanced debug logging with timestamp and context.
     * Logs to both file and database.
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_debug($message, $context = array()) {
        global $wpdb;
        
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
        
        // Log to database (for admin panel viewing)
        if (isset($this->import_id) && $this->import_id > 0) {
            $table_name = $wpdb->prefix . 'wc_itp_import_logs';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'import_id' => $this->import_id,
                        'level' => 'debug',
                        'message' => $message,
                        'context' => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
            }
        }
    }
    
    /**
     * Get nested value from array using dot notation.
     *
     * @param array $array Source array
     * @param string $path Dot-notation path (e.g., "variations.variation.price")
     * @return mixed|null Value or null if not found
     */
    private function get_nested_value($array, $path) {
        if (empty($path) || !is_array($array)) {
            return $array;
        }
        
        $keys = explode('.', $path);
        $value = $array;
        
        foreach ($keys as $key) {
            // Handle array notation like [0]
            if (preg_match('/(.+)\[(\d+)\]/', $key, $matches)) {
                $key = $matches[1];
                $index = intval($matches[2]);
                
                if (isset($value[$key]) && is_array($value[$key]) && isset($value[$key][$index])) {
                    $value = $value[$key][$index];
                } else {
                    return null;
                }
            } elseif (isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Sanitize attribute name for WooCommerce taxonomy.
     *
     * @param string $name Attribute name
     * @return string Sanitized attribute name with pa_ prefix
     */
    private function sanitize_attribute_name($name) {
        $name = trim($name);
        
        // Already has pa_ prefix
        if (strpos($name, 'pa_') === 0) {
            return sanitize_title($name);
        }
        
        // Add pa_ prefix
        return 'pa_' . sanitize_title($name);
    }
    
    /**
     * Ensure attribute taxonomy exists in WooCommerce.
     * Creates the attribute if it doesn't exist.
     *
     * @param string $attr_name Human-readable attribute name (e.g., "Size")
     * @param string $taxonomy_name Taxonomy name with pa_ prefix (e.g., "pa_size")
     * @return bool True if attribute exists or was created
     */
    private function ensure_attribute_taxonomy_exists($attr_name, $taxonomy_name) {
        $log_file = WP_CONTENT_DIR . '/variations_debug.log';
        
        // Remove pa_ prefix for attribute slug
        $attribute_slug = str_replace('pa_', '', $taxonomy_name);
        
        // Check if attribute taxonomy already exists
        if (taxonomy_exists($taxonomy_name)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Attribute taxonomy '$taxonomy_name' already exists\n", FILE_APPEND); }
            return true;
        }
        
        // Check if attribute exists in WooCommerce attributes table
        $attribute_id = wc_attribute_taxonomy_id_by_name($attribute_slug);
        
        if ($attribute_id) {
            // Attribute exists but taxonomy not registered - register it
            register_taxonomy($taxonomy_name, 'product', array(
                'hierarchical' => false,
                'labels' => array('name' => $attr_name),
                'show_ui' => false,
                'query_var' => true,
                'rewrite' => false,
            ));
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Registered existing attribute taxonomy '$taxonomy_name'\n", FILE_APPEND); }
            return true;
        }
        
        // Create new attribute
        $args = array(
            'name' => $attr_name,
            'slug' => $attribute_slug,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false
        );
        
        $result = wc_create_attribute($args);
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ERROR creating attribute '$attr_name': " . $result->get_error_message() . "\n", FILE_APPEND); }
            return false;
        }
        
        // Register the taxonomy immediately so we can use it
        register_taxonomy($taxonomy_name, 'product', array(
            'hierarchical' => false,
            'labels' => array('name' => $attr_name),
            'show_ui' => false,
            'query_var' => true,
            'rewrite' => false,
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ✓ Created new attribute '$attr_name' → $taxonomy_name (ID: $result)\n", FILE_APPEND); }
        
        return true;
    }
    
    /**
     * Update parent product with auto-detected attributes.
     *
     * @param int $product_id Parent product ID
     * @param array $variation_attrs_map Map of attributes discovered
     * @param array $variations_data All variations data
     * @param string $auto_attributes_path Path to attributes in variation data
     * @param WC_Product $parent_product Parent product object
     */
    private function update_parent_with_auto_attributes($product_id, $variation_attrs_map, $variations_data, $auto_attributes_path, $parent_product) {
        $log_file = WP_CONTENT_DIR . '/variations_debug.log';
        
        if (empty($variation_attrs_map)) {
            return;
        }
        
        // Collect all unique values for each attribute from all variations
        $all_values = array();
        foreach ($variation_attrs_map as $attr_name => $attr_cfg) {
            $all_values[$attr_name] = array();
        }
        
        foreach ($variations_data as $var_data) {
            $attrs_data = $this->get_nested_value($var_data, $auto_attributes_path) ?? $var_data;
            
            if (!is_array($attrs_data)) {
                continue;
            }
            
            foreach ($variation_attrs_map as $attr_name => $attr_cfg) {
                $source_key = $attr_cfg['source_key'] ?? strtolower(str_replace(' ', '_', $attr_name));
                $value = $attrs_data[$source_key] ?? null;
                
                if (!empty($value) && is_string($value)) {
                    $all_values[$attr_name][$value] = $value;
                }
            }
        }
        
        // Build WooCommerce product attributes array
        $product_attributes = array();
        
        foreach ($variation_attrs_map as $attr_name => $attr_cfg) {
            $taxonomy_name = $attr_cfg['taxonomy'];
            $values = array_values($all_values[$attr_name] ?? array());
            
            if (empty($values)) {
                continue;
            }
            
            // Add terms to taxonomy
            foreach ($values as $term_value) {
                if (!term_exists($term_value, $taxonomy_name)) {
                    $term_result = wp_insert_term($term_value, $taxonomy_name);
                    if (is_wp_error($term_result)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    Error adding term '$term_value' to $taxonomy_name: " . $term_result->get_error_message() . "\n", FILE_APPEND); }
                    }
                }
            }
            
            // Create attribute for product
            $product_attributes[$taxonomy_name] = array(
                'name' => $taxonomy_name,
                'value' => '',
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1,
            );
            
            // Set object terms (link product to attribute values)
            wp_set_object_terms($product_id, $values, $taxonomy_name);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "    ✓ Added attribute $taxonomy_name with values: " . implode(', ', $values) . "\n", FILE_APPEND); }
        }
        
        // Save attributes to product
        update_post_meta($product_id, '_product_attributes', $product_attributes);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "  ✓ Updated parent product with " . count($product_attributes) . " auto-detected attributes\n", FILE_APPEND); }
    }
}