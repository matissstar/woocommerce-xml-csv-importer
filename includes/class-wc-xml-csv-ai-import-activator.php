<?php
/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation.
 */
class WC_XML_CSV_AI_Import_Activator {

    /**
     * Plugin activation handler.
     *
     * @since    1.0.0
     */
    public static function activate() {
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires WooCommerce to be installed and active.', 'bootflow-woocommerce-xml-csv-importer'));
        }

        // Create database tables
        self::create_tables();

        // Create upload directory
        self::create_upload_directory();

        // Set default options
        self::set_default_options();

        // Schedule cleanup cron job
        if (!wp_next_scheduled('wc_xml_csv_ai_import_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wc_xml_csv_ai_import_cleanup');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables.
     *
     * @since    1.0.0
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Imports table
        $table_imports = $wpdb->prefix . 'wc_itp_imports';
        $sql_imports = "CREATE TABLE $table_imports (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            file_url varchar(500) NOT NULL,
            file_path varchar(500) NULL,
            file_type varchar(10) NOT NULL DEFAULT 'xml',
            product_wrapper varchar(100) DEFAULT 'product',
            field_mappings longtext,
            processing_modes longtext,
            processing_configs longtext,
            ai_settings longtext,
            custom_fields longtext,
            import_filters longtext,
            filter_logic varchar(10) DEFAULT 'AND',
            draft_non_matching tinyint(1) DEFAULT 0,
            update_existing tinyint(1) DEFAULT 0,
            skip_unchanged tinyint(1) DEFAULT 0,
            handle_missing tinyint(1) DEFAULT 0,
            missing_action varchar(20) DEFAULT 'draft',
            delete_variations tinyint(1) DEFAULT 1,
            batch_size int(11) DEFAULT 50,
            schedule_type varchar(20) DEFAULT 'disabled',
            schedule_method varchar(30) DEFAULT 'action_scheduler',
            total_products int(11) DEFAULT 0,
            processed_products int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_run datetime NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY schedule_type (schedule_type),
            KEY last_run (last_run)
        ) $charset_collate;";

        // Import logs table
        $table_logs = $wpdb->prefix . 'wc_itp_import_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id int(11) NOT NULL AUTO_INCREMENT,
            import_id int(11) NOT NULL,
            level varchar(20) NOT NULL,
            message text,
            context text,
            product_sku varchar(100) NULL,
            product_id int(11) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY import_id (import_id),
            KEY level (level),
            KEY product_sku (product_sku),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_imports);
        dbDelta($sql_logs);

        // Run database migrations for existing installations
        self::migrate_database();

        // Update database version
        update_option('wc_xml_csv_ai_import_db_version', '1.2.0');
    }

    /**
     * Migrate database for existing installations.
     *
     * @since    1.1.0
     */
    private static function migrate_database() {
        global $wpdb;
        
        $table_imports = $wpdb->prefix . 'wc_itp_imports';
        
        // Check if skip_unchanged column exists
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_imports}` LIKE %s",
                'skip_unchanged'
            )
        );
        
        // Add skip_unchanged if missing
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_imports}` 
                ADD COLUMN `skip_unchanged` tinyint(1) DEFAULT 0 
                AFTER `update_existing`"
            );
        }
        
        // Check if batch_size column exists (v1.2.0)
        $batch_size_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_imports}` LIKE %s",
                'batch_size'
            )
        );
        
        // Add batch_size if missing
        if (empty($batch_size_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_imports}` 
                ADD COLUMN `batch_size` int(11) DEFAULT 50 
                AFTER `skip_unchanged`"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Added batch_size column to database'); }
        }
        
        // Check if file_path column exists (v1.3.0)
        $file_path_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_imports}` LIKE %s",
                'file_path'
            )
        );
        
        // Add file_path if missing
        if (empty($file_path_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_imports}` 
                ADD COLUMN `file_path` varchar(500) NULL 
                AFTER `file_url`"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Added file_path column to database'); }
        }
        
        // Migrate import_logs table (v1.2.1)
        $table_logs = $wpdb->prefix . 'wc_itp_import_logs';
        
        // Check if level column exists (was log_type before)
        $level_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_logs}` LIKE %s",
                'level'
            )
        );
        
        // Rename log_type to level if needed
        if (empty($level_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_logs}` 
                CHANGE COLUMN `log_type` `level` varchar(20) NOT NULL"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Renamed log_type to level in import_logs table'); }
        }
        
        // Check if context column exists
        $context_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_logs}` LIKE %s",
                'context'
            )
        );
        
        // Add context if missing
        if (empty($context_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_logs}` 
                ADD COLUMN `context` text NULL 
                AFTER `message`"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Added context column to import_logs table'); }
        }
        
        // Check if product_id column exists
        $product_id_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_logs}` LIKE %s",
                'product_id'
            )
        );
        
        // Add product_id if missing
        if (empty($product_id_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_logs}` 
                ADD COLUMN `product_id` int(11) NULL 
                AFTER `product_sku`"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Added product_id column to import_logs table'); }
        }
        
        // v1.4.0: Add missing products handling columns
        $handle_missing_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_imports}` LIKE %s",
                'handle_missing'
            )
        );
        
        if (empty($handle_missing_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_imports}` 
                ADD COLUMN `handle_missing` tinyint(1) DEFAULT 0 AFTER `skip_unchanged`,
                ADD COLUMN `missing_action` varchar(20) DEFAULT 'draft' AFTER `handle_missing`,
                ADD COLUMN `delete_variations` tinyint(1) DEFAULT 1 AFTER `missing_action`"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Added missing products handling columns to database'); }
        }
        
        // v1.5.0: Add schedule_method column
        $schedule_method_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$table_imports}` LIKE %s",
                'schedule_method'
            )
        );
        
        if (empty($schedule_method_exists)) {
            $wpdb->query(
                "ALTER TABLE `{$table_imports}` 
                ADD COLUMN `schedule_method` varchar(30) DEFAULT 'action_scheduler' AFTER `schedule_type`"
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC_XML_CSV_AI_Import: Added schedule_method column to database'); }
        }
    }

    /**
     * Create upload directory.
     *
     * @since    1.0.0
     */
    private static function create_upload_directory() {
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import/';
        $temp_dir = $plugin_upload_dir . 'temp/';

        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Create .htaccess file for security
        $htaccess_file = $plugin_upload_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.php>\n";
            $htaccess_content .= "deny from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }

        // Create index.php file for security
        $index_file = $plugin_upload_dir . 'index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }

    /**
     * Set default plugin options.
     *
     * @since    1.0.0
     */
    private static function set_default_options() {
        $default_settings = array(
            'chunk_size' => 50,
            'max_execution_time' => 300,
            'memory_limit' => '1G',
            'enable_logging' => true,
            'log_level' => 'info',
            'max_file_size' => 100,
            'allowed_extensions' => array('xml', 'csv'),
            'temp_directory' => 'wc-xml-csv-import/temp/',
            'default_ai_provider' => 'openai',
            'ai_request_timeout' => 30,
            'ai_retry_attempts' => 3,
            'enable_ai_caching' => true,
            'ai_cache_duration' => 24,
            'enable_php_formulas' => true,
            'auto_create_categories' => true,
            'update_existing_products' => false,
            'default_product_status' => 'publish'
        );

        add_option('wc_xml_csv_ai_import_settings', $default_settings);
    }
}