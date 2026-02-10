<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/admin
 */

/**
 * The admin-specific functionality of the plugin.
 */
class WC_XML_CSV_AI_Import_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'wc-xml-csv-import') === false) {
            return;
        }
        
        wp_enqueue_style(
            $this->plugin_name . '-admin',
            WC_XML_CSV_AI_IMPORT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'wc-xml-csv-import') === false) {
            return;
        }
        
        wp_enqueue_script(
            $this->plugin_name . '-admin',
            WC_XML_CSV_AI_IMPORT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            $this->version . '.' . time(),
            false
        );

        // Localize script for AJAX
        wp_localize_script(
            $this->plugin_name . '-admin',
            'wc_xml_csv_ai_import_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_xml_csv_ai_import_nonce'),
                'strings' => array(
                    'uploading' => __('Uploading file...', 'wc-xml-csv-import'),
                    'parsing' => __('Parsing file structure...', 'wc-xml-csv-import'),
                    'importing' => __('Importing products...', 'wc-xml-csv-import'),
                    'complete' => __('Import complete!', 'wc-xml-csv-import'),
                    'error' => __('An error occurred:', 'wc-xml-csv-import'),
                    'confirm_import' => __('Are you sure you want to start the import?', 'wc-xml-csv-import'),
                    'test_ai' => __('Testing AI provider...', 'wc-xml-csv-import')
                )
            )
        );
        
        // Also localize as wcAiImportData for consistency across all pages
        wp_localize_script(
            $this->plugin_name . '-admin',
            'wcAiImportData',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_xml_csv_ai_import_nonce'),
                'i18n' => array(
                    'deleting_products' => __('Deleting Products', 'wc-xml-csv-import'),
                    'products_deleted' => __('products deleted', 'wc-xml-csv-import'),
                    'cancel' => __('Cancel', 'wc-xml-csv-import'),
                    'close' => __('Close', 'wc-xml-csv-import'),
                    'confirm_delete_products' => __('Are you sure you want to delete all products from this import?', 'wc-xml-csv-import'),
                    'counting_products' => __('Counting products...', 'wc-xml-csv-import'),
                    'deleting' => __('Deleting...', 'wc-xml-csv-import'),
                    'no_products_found' => __('No products found to delete.', 'wc-xml-csv-import'),
                    'all_products_deleted' => __('All %d products deleted successfully!', 'wc-xml-csv-import'),
                )
            )
        );
    }

    /**
     * Redirect old/incorrect page slugs to correct ones.
     *
     * @since    1.0.0
     */
    public function redirect_old_slugs() {
        if (!isset($_GET['page'])) {
            return;
        }
        
        // Only redirect OLD slugs to NEW ones (don't include same->same mappings!)
        $old_slugs = array(
            'woo_xml_csv_ai_smart_import_logs' => 'wc-xml-csv-import-logs',
        );
        
        $current_page = $_GET['page'];
        
        if (isset($old_slugs[$current_page])) {
            $redirect_url = add_query_arg(array('page' => $old_slugs[$current_page]), admin_url('admin.php'));
            
            // Preserve other GET parameters
            foreach ($_GET as $key => $value) {
                if ($key !== 'page') {
                    $redirect_url = add_query_arg($key, $value, $redirect_url);
                }
            }
            
            wp_redirect($redirect_url);
            exit;
        }
    }
    
    /**
     * Add admin menu items.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        // Add top-level menu with icon
        add_menu_page(
            __('XML/CSV AI Import', 'wc-xml-csv-import'),
            __('AI Import', 'wc-xml-csv-import'),
            'manage_options',
            'wc-xml-csv-import',
            array($this, 'display_import_page'),
            'dashicons-upload',
            56 // Position (after WooCommerce at 55)
        );

        // Add submenu pages
        add_submenu_page(
            'wc-xml-csv-import',
            __('New Import', 'wc-xml-csv-import'),
            __('New Import', 'wc-xml-csv-import'),
            'manage_options',
            'wc-xml-csv-import',
            array($this, 'display_import_page')
        );

        add_submenu_page(
            'wc-xml-csv-import',
            __('Import History', 'wc-xml-csv-import'),
            __('History', 'wc-xml-csv-import'),
            'manage_options',
            'wc-xml-csv-import-history',
            array($this, 'display_history_page')
        );

        add_submenu_page(
            'wc-xml-csv-import',
            __('Import Settings', 'wc-xml-csv-import'),
            __('Settings', 'wc-xml-csv-import'),
            'manage_options',
            'wc-xml-csv-import-settings',
            array($this, 'display_settings_page')
        );

        add_submenu_page(
            'wc-xml-csv-import',
            __('Import Logs', 'wc-xml-csv-import'),
            __('Logs', 'wc-xml-csv-import'),
            'manage_options',
            'wc-xml-csv-import-logs',
            array($this, 'display_logs_page')
        );
    }

    /**
     * Display main import page.
     *
     * @since    1.0.0
     */
    public function display_import_page() {
        // Handle Re-run action with resume dialog
        if (isset($_GET['action']) && $_GET['action'] === 'rerun' && isset($_GET['import_id'])) {
            $import_id = intval($_GET['import_id']);
            
            // Check if this is a confirmed action (resume or restart)
            if (isset($_GET['resume_action'])) {
                $resume_action = sanitize_text_field($_GET['resume_action']);
                $this->rerun_import($import_id, $resume_action === 'resume');
                return;
            }
            
            // Check if import has progress
            global $wpdb;
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                $import_id
            ), ARRAY_A);
            
            if ($import && $import['processed_products'] > 0 && $import['processed_products'] < $import['total_products']) {
                // Show resume dialog
                $this->display_resume_dialog($import);
                return;
            }
            
            // No progress or completed - just restart
            $this->rerun_import($import_id, false);
            return;
        }
        
        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        
        echo '<div class="wrap wc-xml-csv-import-wrap">';
        echo '<h1>' . __('WooCommerce XML/CSV Smart AI Import', 'wc-xml-csv-import') . '</h1>';
        
        // Progress indicator
        $this->display_progress_indicator($step);
        
        switch ($step) {
            case 1:
                $this->display_step_1_upload();
                break;
            case 2:
                $this->display_step_2_mapping();
                break;
            case 3:
                $this->display_step_3_progress();
                break;
            default:
                $this->display_step_1_upload();
                break;
        }
        
        echo '</div>';
    }

    /**
     * Display progress indicator.
     *
     * @since    1.0.0
     * @param    int $current_step Current step
     */
    private function display_progress_indicator($current_step) {
        $steps = array(
            1 => __('Upload File', 'wc-xml-csv-import'),
            2 => __('Map Fields', 'wc-xml-csv-import'),
            3 => __('Import Progress', 'wc-xml-csv-import')
        );
        
        echo '<div class="wc-ai-import-progress-indicator">';
        echo '<ul class="wc-ai-import-steps">';
        
        foreach ($steps as $step_num => $step_name) {
            $class = 'step';
            if ($step_num < $current_step) {
                $class .= ' completed';
            } elseif ($step_num == $current_step) {
                $class .= ' active';
            }
            
            echo '<li class="' . esc_attr($class) . '">';
            echo '<span class="step-number">' . esc_html($step_num) . '</span>';
            echo '<span class="step-name">' . esc_html($step_name) . '</span>';
            echo '</li>';
        }
        
        echo '</ul>';
        echo '</div>';
    }

    /**
     * Display step 1: File upload.
     *
     * @since    1.0.0
     */
    private function display_step_1_upload() {
        include_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/admin/partials/step-1-upload.php';
    }

    /**
     * Display step 2: Field mapping.
     *
     * @since    1.0.0
     */
    private function display_step_2_mapping() {
        global $wpdb;
        
        // Check if Edit mode (import_id in URL)
        $import_id = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;
        
        // HANDLE POST SUBMISSION FIRST (before any output)
        if ($import_id > 0 && isset($_POST['update_import'])) {
            // VISUAL DEBUG
            echo '<div style="position:fixed; top:50px; right:20px; background:yellow; padding:20px; z-index:9999; border:3px solid red;">';
            echo '<h3>DEBUG: POST DETECTED!</h3>';
            echo 'Import ID: ' . $import_id . '<br>';
            echo 'Calling display_import_details()...<br>';
            echo '</div>';
            
            // Redirect to display_import_details for POST handling
            $this->display_import_details($import_id);
            return;
        }
        
        // Get parameters from URL OR from database
        if ($import_id > 0) {
            // Edit mode - load from database
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                $import_id
            ), ARRAY_A);
            
            if ($import) {
                $file_path = $import['file_path'];
                $file_type = $import['file_type'];
                $import_name = $import['name'];
                $schedule_type = $import['schedule_type'];
                $product_wrapper = $import['product_wrapper'];
                $update_existing = $import['update_existing'];
                $skip_unchanged = $import['skip_unchanged'];
                $batch_size = $import['batch_size'] ?? 50;
            } else {
                $file_path = '';
                $file_type = '';
                $import_name = '';
                $schedule_type = '';
                $product_wrapper = 'product';
                $update_existing = '0';
                $skip_unchanged = '0';
                $batch_size = 50;
            }
        } else {
            // New import mode - get from URL parameters
            $file_path = isset($_GET['file_path']) ? sanitize_text_field($_GET['file_path']) : '';
            $file_type = isset($_GET['file_type']) ? sanitize_text_field($_GET['file_type']) : '';
            $import_name = isset($_GET['import_name']) ? sanitize_text_field($_GET['import_name']) : '';
            $schedule_type = isset($_GET['schedule_type']) ? sanitize_text_field($_GET['schedule_type']) : '';
            $product_wrapper = isset($_GET['product_wrapper']) ? sanitize_text_field($_GET['product_wrapper']) : 'product';
            $update_existing = isset($_GET['update_existing']) ? sanitize_text_field($_GET['update_existing']) : '0';
            $skip_unchanged = isset($_GET['skip_unchanged']) ? sanitize_text_field($_GET['skip_unchanged']) : '0';
        }
        
        // Output wcAiImportData BEFORE the page content so it's available when admin.js loads
        if (!empty($file_path)) {
            ?>
            <script type="text/javascript">
            var wcAiImportData = {
                file_path: '<?php echo esc_js($file_path); ?>',
                file_type: '<?php echo esc_js($file_type); ?>',
                import_name: '<?php echo esc_js($import_name); ?>',
                schedule_type: '<?php echo esc_js($schedule_type); ?>',
                product_wrapper: '<?php echo esc_js($product_wrapper); ?>',
                update_existing: '<?php echo esc_js($update_existing); ?>',
                skip_unchanged: '<?php echo esc_js($skip_unchanged); ?>',
                batch_size: <?php echo intval($batch_size ?? 50); ?>,
                ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('wc_xml_csv_ai_import_nonce'); ?>'
            };
            console.log('wcAiImportData defined:', wcAiImportData);
            </script>
            <?php
        }
        
        include_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/admin/partials/step-2-mapping.php';
    }

    /**
     * Display step 3: Import progress.
     *
     * @since    1.0.0
     */
    private function display_step_3_progress() {
        include_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/admin/partials/step-3-progress.php';
    }

    /**
     * Display import history page.
     *
     * @since    1.0.0
     */
    public function display_history_page() {
        global $wpdb;
        
        // Handle edit action - redirect to Step 2 with import data
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['import_id'])) {
            $this->display_step_2_mapping();
            return;
        }
        
        // Handle view action
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['import_id'])) {
            $this->display_import_details(intval($_GET['import_id']));
            return;
        }
        
        // Handle delete import action
        if (isset($_GET['action']) && $_GET['action'] === 'delete_import' && isset($_GET['import_id'])) {
            $import_id = intval($_GET['import_id']);
            
            // Verify nonce
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_import_' . $import_id)) {
                // Get import data to access file_path
                $import = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                    $import_id
                ), ARRAY_A);
                
                // Delete the file if it exists
                if ($import && !empty($import['file_path']) && file_exists($import['file_path'])) {
                    @unlink($import['file_path']);
                }
                
                // Delete database record
                $deleted = $wpdb->delete(
                    $wpdb->prefix . 'wc_itp_imports',
                    array('id' => $import_id),
                    array('%d')
                );
                
                if ($deleted) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Import and file deleted successfully.', 'wc-xml-csv-import') . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to delete import.', 'wc-xml-csv-import') . '</p></div>';
                }
            }
        }
        
        // Handle delete products action
        if (isset($_GET['action']) && $_GET['action'] === 'delete_products' && isset($_GET['import_id'])) {
            $import_id = intval($_GET['import_id']);
            
            // Verify nonce
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_products_' . $import_id)) {
                // Get all products associated with this import
                $product_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wc_import_id' AND meta_value = %d",
                    $import_id
                ));
                
                $deleted_count = 0;
                foreach ($product_ids as $product_id) {
                    if (wp_delete_post($product_id, true)) {
                        $deleted_count++;
                    }
                }
                
                // Update import's processed_products count to 0
                $wpdb->update(
                    $wpdb->prefix . 'wc_itp_imports',
                    array('processed_products' => 0),
                    array('id' => $import_id),
                    array('%d'),
                    array('%d')
                );
                
                if ($deleted_count > 0) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d products deleted successfully.', 'wc-xml-csv-import'), $deleted_count) . '</p></div>';
                } else {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . __('No products found to delete.', 'wc-xml-csv-import') . '</p></div>';
                }
            }
        }
        
        echo '<div class="wrap">';
        echo '<h1>' . __('Import History', 'wc-xml-csv-import') . '</h1>';
        
        // Get imports
        $imports = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}wc_itp_imports ORDER BY created_at DESC",
            ARRAY_A
        );
        
        if (empty($imports)) {
            echo '<p>' . __('No imports found.', 'wc-xml-csv-import') . '</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>' . __('Name', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('File Type', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('Products', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('Status', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('Schedule', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('Created', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('Last Run', 'wc-xml-csv-import') . '</th>';
            echo '<th>' . __('Actions', 'wc-xml-csv-import') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($imports as $import) {
                // Get actual product count from database (products with this import_id meta)
                $actual_product_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wc_import_id' AND meta_value = %d",
                    $import['id']
                ));
                
                $schedule_label = 'Disabled';
                if (!empty($import['schedule_type']) && $import['schedule_type'] !== 'none' && $import['schedule_type'] !== 'disabled') {
                    $schedule_labels = array(
                        '15min' => __('Every 15 min', 'wc-xml-csv-import'),
                        'hourly' => __('Hourly', 'wc-xml-csv-import'),
                        '6hours' => __('Every 6h', 'wc-xml-csv-import'),
                        'daily' => __('Daily', 'wc-xml-csv-import'),
                        'weekly' => __('Weekly', 'wc-xml-csv-import'),
                        'monthly' => __('Monthly', 'wc-xml-csv-import')
                    );
                    $schedule_label = $schedule_labels[$import['schedule_type']] ?? $import['schedule_type'];
                }
                
                echo '<tr>';
                echo '<td>' . esc_html($import['name']) . '</td>';
                echo '<td>' . esc_html(strtoupper($import['file_type'])) . '</td>';
                // Show actual products in DB / processed from file / total in file
                echo '<td title="' . esc_attr(sprintf(__('In database: %d, Processed: %d, In file: %d', 'wc-xml-csv-import'), $actual_product_count, $import['processed_products'], $import['total_products'])) . '">' . esc_html($actual_product_count) . ' <small style="color:#666;">(' . esc_html($import['processed_products']) . '/' . esc_html($import['total_products']) . ')</small></td>';
                echo '<td>' . esc_html(ucfirst($import['status'])) . '</td>';
                echo '<td>' . esc_html($schedule_label) . '</td>';
                echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import['created_at']))) . '</td>';
                echo '<td>' . ($import['last_run'] ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import['last_run']))) : __('Never', 'wc-xml-csv-import')) . '</td>';
                echo '<td>';
                
                // Edit button
                echo '<a href="' . admin_url('admin.php?page=wc-xml-csv-import-history&action=edit&import_id=' . $import['id']) . '" class="button button-small button-primary">' . __('Edit', 'wc-xml-csv-import') . '</a> ';
                
                // Stop button - only show if import is processing
                if ($import['status'] === 'processing') {
                    echo '<a href="' . admin_url('admin.php?page=wc-xml-csv-import-history&action=stop&import_id=' . $import['id']) . '" class="button button-small">' . __('Stop', 'wc-xml-csv-import') . '</a> ';
                }
                
                // Re-run button
                echo '<a href="' . admin_url('admin.php?page=wc-xml-csv-import&action=rerun&import_id=' . $import['id']) . '" class="button button-small">' . __('Re-run', 'wc-xml-csv-import') . '</a> ';
                
                // Delete import button
                $delete_import_url = wp_nonce_url(
                    admin_url('admin.php?page=wc-xml-csv-import-history&action=delete_import&import_id=' . $import['id']),
                    'delete_import_' . $import['id']
                );
                echo '<a href="' . esc_url($delete_import_url) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this import and its file?', 'wc-xml-csv-import')) . '\')">' . __('Delete import', 'wc-xml-csv-import') . '</a> ';
                
                // Delete products button (AJAX with progress)
                echo '<button type="button" class="button button-small button-link-delete delete-products-ajax" data-import-id="' . esc_attr($import['id']) . '" data-nonce="' . wp_create_nonce('wc_xml_csv_ai_import_nonce') . '">' . __('Delete products', 'wc-xml-csv-import') . '</button>';
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        
        echo '</div>';
    }

    /**
     * Display import details with full editing capability.
     */
    private function display_import_details($import_id) {
        global $wpdb;
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== display_import_details() called for import_id: ' . $import_id); }
        

        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $import_id), ARRAY_A);

        if (!$import) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import not found in database for ID: ' . $import_id); }
            echo '<div class="wrap"><h1>' . __('Import Not Found', 'wc-xml-csv-import') . '</h1>';
            echo '<p><a href="' . admin_url('admin.php?page=wc-xml-csv-import-history') . '" class="button">Back</a></p></div>';
            return;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import found: ' . $import['name'] . ', has field_mappings: ' . (empty($import['field_mappings']) ? 'NO' : 'YES')); }

        // Patch: If file_path is empty, try to auto-fill from plugin upload dir
        if (empty($import['file_path'])) {
            $plugin_upload_dir = WP_CONTENT_DIR . '/uploads/woo_xml_csv_ai_smart_import/';
            if (is_dir($plugin_upload_dir)) {
                $files = glob($plugin_upload_dir . '*');
                if ($files && count($files) > 0) {
                    // Try to find a file that matches import name or type
                    $found = false;
                    foreach ($files as $f) {
                        if (stripos(basename($f), $import['name']) !== false || stripos(basename($f), $import['file_type']) !== false) {
                            $import['file_path'] = $f;
                            $found = true;
                            break;
                        }
                    }
                    // If not found, just use the first file
                    if (!$found) {
                        $import['file_path'] = $files[0];
                    }
                }
            }
        }
        
        // Debug: Log request method
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== EDIT PAGE LOAD ==='); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Request method: ' . $_SERVER['REQUEST_METHOD']); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST data exists: ' . (empty($_POST) ? 'NO' : 'YES')); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('update_import in POST: ' . (isset($_POST['update_import']) ? 'YES' : 'NO')); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('run_import_now in POST: ' . (isset($_POST['run_import_now']) ? 'YES' : 'NO')); }
        
        // Handle "Run Import Now" button - saves mapping AND starts import
        if (isset($_POST['run_import_now'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== RUN IMPORT NOW CLICKED ==='); }
            
            // Verify nonce
            if (!check_admin_referer('update_import_' . $import_id, '_wpnonce', false)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check FAILED for RUN IMPORT NOW'); }
                wp_die(__('Security check failed. Please try again.', 'wc-xml-csv-import'));
            }
            
            // First, save the mappings (same as update_import)
            $_POST['update_import'] = true; // Trigger save logic below
            // Don't return - let it fall through to save logic, then redirect to step 3
        }
        
        // Handle form submission (only validate nonce on POST, not on GET/view)
        if (isset($_POST['update_import'])) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST data present: YES'); }
            
            // DEBUG: Log ALL POST data
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== FULL POST DATA ==='); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST keys: ' . implode(', ', array_keys($_POST))); }
            if (isset($_POST['field_mapping']['description'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('description POST data: ' . print_r($_POST['field_mapping']['description'], true)); }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('update_existing: ' . ($_POST['update_existing'] ?? 'NOT SET')); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('skip_unchanged: ' . ($_POST['skip_unchanged'] ?? 'NOT SET')); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('schedule_type: ' . ($_POST['schedule_type'] ?? 'NOT SET')); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('schedule_method: ' . ($_POST['schedule_method'] ?? 'NOT SET')); }
            
            // Verify nonce only for POST submissions
            if (!check_admin_referer('update_import_' . $import_id, '_wpnonce', false)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check FAILED for POST submission'); }
                wp_die(__('Security check failed. Please try again.', 'wc-xml-csv-import'));
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check: VALID'); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== SAVING IMPORT MAPPINGS ==='); }
            $field_mapping = isset($_POST['field_mapping']) ? $_POST['field_mapping'] : array();
            $custom_fields = isset($_POST['custom_fields']) ? $_POST['custom_fields'] : array();
            
            // DEBUG: Log shipping_class_formula specifically BEFORE stripslashes
            if (isset($_POST['field_mapping']['shipping_class_formula'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ shipping_class_formula RAW POST: ' . print_r($_POST['field_mapping']['shipping_class_formula'], true)); }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ WARNING: shipping_class_formula NOT in POST data!'); }
            }
            
            // Stripslashes from POST data to prevent double escaping
            $field_mapping = stripslashes_deep($field_mapping);
            $custom_fields = stripslashes_deep($custom_fields);
            
            // DEBUG: Log shipping_class_formula AFTER stripslashes
            if (isset($field_mapping['shipping_class_formula'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ shipping_class_formula AFTER stripslashes: ' . print_r($field_mapping['shipping_class_formula'], true)); }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Raw field_mapping count: ' . count($field_mapping)); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Raw field_mapping sample: ' . print_r(array_slice($field_mapping, 0, 2, true), true)); }
            
            // Merge mappings - save ALL fields that have processing_mode or source
            $all_mappings = array();
            foreach ($field_mapping as $wc_field => $mapping_data) {
                // Special handling for shipping_class_formula (uses [formula] instead of [processing_mode])
                // IMPORTANT: Save even if formula is empty (user might want to clear it)
                if ($wc_field === 'shipping_class_formula') {
                    $all_mappings[$wc_field] = $mapping_data;
                    if (!empty($mapping_data['formula'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("★ Saving shipping_class_formula with formula: " . substr($mapping_data['formula'], 0, 100)); }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("★ Saving shipping_class_formula with EMPTY formula (clearing it)"); }
                    }
                }
                // Save field if it has processing_mode OR source OR update_on_sync flag
                // This ensures update_on_sync checkbox state is always saved
                elseif (!empty($mapping_data['processing_mode']) || !empty($mapping_data['source']) || isset($mapping_data['update_on_sync'])) {
                    $all_mappings[$wc_field] = $mapping_data;
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Saving field: {$wc_field} - mode=" . ($mapping_data['processing_mode'] ?? 'none') . " source=" . ($mapping_data['source'] ?? 'none') . " update_on_sync=" . ($mapping_data['update_on_sync'] ?? 'not set')); }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Skipping empty field: {$wc_field}"); }
                }
            }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Merged mappings count: ' . count($all_mappings)); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Merged mappings sample: ' . print_r(array_slice($all_mappings, 0, 2, true), true)); }
            
            // CRITICAL: If no mappings, don't overwrite existing ones!
            if (empty($all_mappings) && !empty($import['field_mappings'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WARNING: No new mappings provided, keeping existing mappings'); }
                $all_mappings = json_decode($import['field_mappings'], true);
                if (!is_array($all_mappings)) {
                    $all_mappings = array();
                }
            }
            
            // Add custom fields
            foreach ($custom_fields as $cf) {
                if (!empty($cf['name']) && !empty($cf['source'])) {
                    $all_mappings['_custom_' . sanitize_key($cf['name'])] = $cf;
                }
            }
            
            // Collect import filters
            $import_filters = array();
            $filter_logic = isset($_POST['filter_logic']) ? sanitize_text_field($_POST['filter_logic']) : 'AND';
            $draft_non_matching = isset($_POST['draft_non_matching']) ? 1 : 0;
            
            if (isset($_POST['import_filters']) && is_array($_POST['import_filters'])) {
                foreach ($_POST['import_filters'] as $filter) {
                    if (!empty($filter['field']) && !empty($filter['operator'])) {
                        // Validate operator is in allowed list
                        $allowed_operators = array('=', '!=', '>', '<', '>=', '<=', 'contains', 'not_contains', 'empty', 'not_empty');
                        $operator = in_array($filter['operator'], $allowed_operators) ? $filter['operator'] : '=';
                        
                        $filter_data = array(
                            'field' => sanitize_text_field($filter['field']),
                            'operator' => $operator,  // Don't sanitize - use validated value
                            'value' => sanitize_text_field($filter['value'] ?? '')
                        );
                        
                        // Add logic if present (for chaining with next filter)
                        if (isset($filter['logic'])) {
                            $filter_data['logic'] = in_array($filter['logic'], array('AND', 'OR')) ? $filter['logic'] : 'AND';
                        }
                        
                        $import_filters[] = $filter_data;
                    }
                }
            }
            
            // IMPORTANT: Preserve file_path and file_url from existing record
            $existing_import = $wpdb->get_row($wpdb->prepare(
                "SELECT file_path, file_url FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", 
                $import_id
            ), ARRAY_A);
            
            $update_data = array(
                'file_path' => $existing_import['file_path'],  // Preserve file path
                'file_url' => $existing_import['file_url'],    // Preserve file URL
                'field_mappings' => json_encode($all_mappings),
                'import_filters' => json_encode($import_filters),
                'filter_logic' => $filter_logic,
                'draft_non_matching' => $draft_non_matching,
                'schedule_type' => sanitize_text_field($_POST['schedule_type'] ?? $_POST['schedule_type_hidden'] ?? 'none'),
                'schedule_method' => sanitize_text_field($_POST['schedule_method'] ?? $_POST['schedule_method_hidden'] ?? 'action_scheduler'),
                'update_existing' => isset($_POST['update_existing']) ? '1' : '0',
                'skip_unchanged' => isset($_POST['skip_unchanged']) ? '1' : '0',
                'handle_missing' => isset($_POST['handle_missing']) ? '1' : '0',
                'missing_action' => sanitize_text_field($_POST['missing_action'] ?? 'draft'),
                'delete_variations' => isset($_POST['delete_variations']) ? '1' : '0',
                'batch_size' => isset($_POST['batch_size']) ? absint($_POST['batch_size']) : 50
            );
            
            // DEBUG: Log schedule fields
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log('★★★ SCHEDULE_TYPE from POST: ' . ($_POST['schedule_type'] ?? 'NOT SET')); 
                error_log('★★★ SCHEDULE_METHOD from POST: ' . ($_POST['schedule_method'] ?? 'NOT SET')); 
            }
            
            // DEBUG: Log batch_size specifically
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ BATCH_SIZE from POST: ' . ($_POST['batch_size'] ?? 'NOT SET')); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ BATCH_SIZE after absint: ' . $update_data['batch_size']); }
            
            // DEBUG: Show shipping_class_formula in JSON before saving
            if (isset($all_mappings['shipping_class_formula'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ shipping_class_formula in all_mappings: ' . print_r($all_mappings['shipping_class_formula'], true)); }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ ERROR: shipping_class_formula NOT in all_mappings!'); }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('JSON to save: ' . substr(json_encode($all_mappings), 0, 500)); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Filters to save: ' . json_encode($import_filters)); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Filter logic: ' . $filter_logic); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Updating import ID: ' . $import_id); }
            
            // DEBUG: Show what we're about to save
            /*
            echo '<div style="background: #fff; padding: 20px; margin: 20px 0; border: 2px solid #0073aa;">';
            echo '<h2>DEBUG: Data being saved to database</h2>';
            echo '<h3>Images field:</h3><pre>' . print_r($all_mappings['images'] ?? 'NOT SET', true) . '</pre>';
            echo '<h3>Featured Image field:</h3><pre>' . print_r($all_mappings['featured_image'] ?? 'NOT SET', true) . '</pre>';
            echo '<h3>Import Filters (' . count($import_filters) . '):</h3><pre>' . print_r($import_filters, true) . '</pre>';
            echo '<h3>Filter Logic: ' . $filter_logic . '</h3>';
            echo '<h3>All mappings (first 5):</h3><pre>' . print_r(array_slice($all_mappings, 0, 5, true), true) . '</pre>';
            echo '<h3>Total fields: ' . count($all_mappings) . '</h3>';
            echo '</div>';
            */
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ ABOUT TO UPDATE DATABASE ★★★'); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import ID: ' . $import_id); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Total mappings: ' . count($all_mappings)); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Mappings JSON length: ' . strlen(json_encode($all_mappings))); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('File path to save: ' . ($update_data['file_path'] ?? 'NULL')); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('schedule_type to save: ' . $update_data['schedule_type']); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('schedule_method to save: ' . $update_data['schedule_method']); }
            
            $result = $wpdb->update(
                $wpdb->prefix . 'wc_itp_imports', 
                $update_data, 
                array('id' => $import_id), 
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%d'),  // 14 formats for 14 fields
                array('%d')
            );
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ UPDATE RESULT: ' . ($result !== false ? 'SUCCESS (rows: ' . $result . ')' : 'FAILED - ' . $wpdb->last_error)); }
            
            // DEBUG: Verify what was actually saved
            if ($result !== false) {
                $saved_import = $wpdb->get_row($wpdb->prepare("SELECT field_mappings, file_path FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $import_id), ARRAY_A);
                if ($saved_import) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ VERIFIED file_path: ' . ($saved_import['file_path'] ?? 'NULL')); }
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ VERIFIED mappings length: ' . strlen($saved_import['field_mappings'] ?? '')); }
                    $saved_mappings = json_decode($saved_import['field_mappings'], true);
                    if (isset($saved_mappings['sku'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Sample SKU field: ' . print_r($saved_mappings['sku'], true)); }
                    }
                    $saved_mappings = json_decode($saved_import['field_mappings'], true);
                    if (isset($saved_mappings['shipping_class_formula'])) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ VERIFIED: shipping_class_formula saved to DB: ' . print_r($saved_mappings['shipping_class_formula'], true)); }
                    } else {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ ERROR: shipping_class_formula NOT in saved DB data!'); }
                    }
                }
            }
            
            // Check if "Run Import Now" was clicked
            $should_run_import = isset($_POST['run_import_now']);
            
            if ($should_run_import) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ RUN IMPORT NOW - Redirecting to progress page after save'); }
                
                // Set import status to processing and reset processed count
                $wpdb->update(
                    $wpdb->prefix . 'wc_itp_imports',
                    array(
                        'status' => 'pending',  // Set to pending - progress page will kickstart
                        'processed_products' => 0  // Reset processed count
                    ),
                    array('id' => $import_id),
                    array('%s', '%d'),
                    array('%d')
                );
                
                // DON'T trigger import here - let progress page kickstart handle it
                // This prevents double processing
                
                // Redirect to progress page (step 3)
                $redirect_url = admin_url('admin.php?page=wc-xml-csv-import&step=3&import_id=' . $import_id);
                wp_redirect($redirect_url);
                exit;
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Import updated successfully.', 'wc-xml-csv-import') . '</p></div>';
            $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $import_id), ARRAY_A);
            
            // DEBUG: Log reloaded batch_size
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ After UPDATE - Reloaded batch_size from DB: ' . ($import['batch_size'] ?? 'NULL')); }
        }
        
        // Get existing mappings from field_mappings column
        $existing_mappings = array();
        $mapping_source = 'field_mappings';
        
        if (!empty($import['field_mappings'])) {
            $existing_mappings = json_decode($import['field_mappings'], true);
            if (!is_array($existing_mappings)) {
                $existing_mappings = array();
            }
        }
        
        if (!is_array($existing_mappings)) {
            $existing_mappings = array();
        }
        
        if (!empty($existing_mappings)) {
            echo '<!-- DEBUG: Loaded ' . count($existing_mappings) . ' existing mappings from ' . $mapping_source . ' -->';
            echo '<!-- DEBUG: First mapping keys: ' . implode(', ', array_slice(array_keys($existing_mappings), 0, 5)) . ' -->';
            echo '<!-- DEBUG: SKU mapping: ' . (isset($existing_mappings['sku']) ? json_encode($existing_mappings['sku']) : 'NOT SET') . ' -->';
            echo '<!-- DEBUG: Name mapping: ' . (isset($existing_mappings['name']) ? json_encode($existing_mappings['name']) : 'NOT SET') . ' -->';
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import Edit - Loaded ' . count($existing_mappings) . ' existing mappings from ' . $mapping_source); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import Edit - Sample mapping: ' . print_r(array_slice($existing_mappings, 0, 2, true), true)); }
        } else {
            echo '<!-- DEBUG: No field_mappings data found -->';
            echo '<!-- DEBUG: Import field_mappings value: ' . var_export($import['field_mappings'] ?? 'NOT SET', true) . ' -->';
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Import Edit - No field_mappings data found in import record'); }
        }
        
        // Generate secret key
        $import_secret = get_option('wc_xml_csv_ai_import_secret_' . $import_id);
        if (empty($import_secret)) {
            $import_secret = wp_generate_password(32, false);
            update_option('wc_xml_csv_ai_import_secret_' . $import_id, $import_secret);
        }
        
        $cron_url = admin_url('admin-ajax.php?action=wc_xml_csv_ai_import_single_cron&import_id=' . $import_id . '&secret=' . $import_secret);
        
        // Load file structure for dropdowns - use XML Parser for proper nested field support
        $file_path = $import['file_path'];
        $file_fields = array();
        if (file_exists($file_path)) {
            if ($import['file_type'] === 'xml') {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ Loading file structure for import edit using XML Parser. Product wrapper: ' . ($import['product_wrapper'] ?: 'product')); }
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ File path: ' . $file_path); }
                
                // Use XML Parser class to get proper structure with nested fields
                $xml_parser = new WC_XML_CSV_AI_Import_XML_Parser();
                $structure_result = $xml_parser->parse_structure($file_path, $import['product_wrapper'] ?: 'product', 1, 1);
                
                if (!empty($structure_result['structure'])) {
                    // Extract field paths from structure (filter out object/array types, only keep text fields)
                    foreach ($structure_result['structure'] as $field) {
                        if ($field['type'] !== 'object' && $field['type'] !== 'array') {
                            $file_fields[] = $field['path'];
                        }
                    }
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ Loaded ' . count($file_fields) . ' file fields (including nested) for dropdowns: ' . implode(', ', array_slice($file_fields, 0, 10))); }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ ERROR: XML Parser returned empty structure'); }
                }
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★ ERROR: File does not exist: ' . $file_path); }
        }
        
        // WooCommerce fields structure
        $woocommerce_fields = array(
            'basic' => array(
                'title' => __('Basic Product Fields', 'wc-xml-csv-import'),
                'fields' => array(
                    'sku' => array('label' => __('Product Code (SKU)', 'wc-xml-csv-import'), 'required' => true),
                    'name' => array('label' => __('Product Name', 'wc-xml-csv-import'), 'required' => false),
                    'description' => array('label' => __('Description', 'wc-xml-csv-import'), 'required' => false),
                    'short_description' => array('label' => __('Short Description', 'wc-xml-csv-import'), 'required' => false),
                    'status' => array('label' => __('Product Status', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'pricing' => array(
                'title' => __('Pricing Fields', 'wc-xml-csv-import'),
                'fields' => array(
                    'regular_price' => array('label' => __('Regular Price', 'wc-xml-csv-import'), 'required' => false),
                    'sale_price' => array('label' => __('Sale Price', 'wc-xml-csv-import'), 'required' => false),
                    'tax_status' => array('label' => __('Tax Status', 'wc-xml-csv-import'), 'required' => false),
                    'tax_class' => array('label' => __('Tax Class', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'inventory' => array(
                'title' => __('Inventory Fields', 'wc-xml-csv-import'),
                'fields' => array(
                    'manage_stock' => array('label' => __('Manage Stock', 'wc-xml-csv-import'), 'required' => false),
                    'stock_quantity' => array('label' => __('Stock Quantity', 'wc-xml-csv-import'), 'required' => false),
                    'stock_status' => array('label' => __('Stock Status', 'wc-xml-csv-import'), 'required' => false),
                    'backorders' => array('label' => __('Allow Backorders', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'physical' => array(
                'title' => __('Physical Properties', 'wc-xml-csv-import'),
                'fields' => array(
                    'weight' => array('label' => __('Weight', 'wc-xml-csv-import'), 'required' => false),
                    'length' => array('label' => __('Length', 'wc-xml-csv-import'), 'required' => false),
                    'width' => array('label' => __('Width', 'wc-xml-csv-import'), 'required' => false),
                    'height' => array('label' => __('Height', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'shipping_class_assignment' => array(
                'title' => __('Shipping Class Assignment', 'wc-xml-csv-import'),
                'fields' => array(
                    'shipping_class_formula' => array('label' => __('Shipping Class Formula', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'media' => array(
                'title' => __('Media Fields', 'wc-xml-csv-import'),
                'fields' => array(
                    'images' => array(
                        'label' => __('Product Images', 'wc-xml-csv-import'), 
                        'required' => false,
                        'type' => 'textarea',
                        'description' => __('Enter image URLs or use placeholders: {image} = first image, {image[1]} = first, {image[2]} = second, {image*} = all images. Separate multiple values with commas.', 'wc-xml-csv-import')
                    ),
                    'featured_image' => array('label' => __('Featured Image', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'taxonomy' => array(
                'title' => __('Categories & Tags', 'wc-xml-csv-import'),
                'fields' => array(
                    'categories' => array('label' => __('Product Categories', 'wc-xml-csv-import'), 'required' => false),
                    'tags' => array('label' => __('Product Tags', 'wc-xml-csv-import'), 'required' => false),
                    'brand' => array('label' => __('Brand', 'wc-xml-csv-import'), 'required' => false),
                )
            ),
            'seo' => array(
                'title' => __('SEO Fields', 'wc-xml-csv-import'),
                'fields' => array(
                    'meta_title' => array('label' => __('Meta Title', 'wc-xml-csv-import'), 'required' => false),
                    'meta_description' => array('label' => __('Meta Description', 'wc-xml-csv-import'), 'required' => false),
                    'meta_keywords' => array('label' => __('Meta Keywords', 'wc-xml-csv-import'), 'required' => false),
                )
            )
        );
        
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $ai_providers = array('openai' => 'OpenAI GPT', 'gemini' => 'Google Gemini', 'claude' => 'Anthropic Claude', 'grok' => 'xAI Grok', 'copilot' => 'Microsoft Copilot');
        
        // Output HTML
        include_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/admin/partials/import-edit.php';
    }

    /**
     * Display resume dialog for partially completed imports.
     *
     * @since    1.0.0
     * @param    array $import Import data
     */
    private function display_resume_dialog($import) {
        $percentage = round(($import['processed_products'] / $import['total_products']) * 100, 1);
        $remaining = $import['total_products'] - $import['processed_products'];
        
        echo '<div class="wrap wc-xml-csv-import-wrap">';
        echo '<h1>' . __('Resume Import?', 'wc-xml-csv-import') . '</h1>';
        
        echo '<div class="card" style="max-width: 600px; padding: 20px; margin: 20px 0;">';
        echo '<h2 style="margin-top: 0;">' . esc_html($import['name']) . '</h2>';
        
        echo '<div class="import-progress-summary" style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        echo '<p style="font-size: 16px; margin: 0 0 10px 0;">';
        echo '<strong>' . __('Current Progress:', 'wc-xml-csv-import') . '</strong> ';
        echo '<span style="color: #0073aa; font-size: 20px;">' . $percentage . '%</span>';
        echo '</p>';
        echo '<p style="margin: 5px 0;">' . sprintf(__('%d of %d products processed', 'wc-xml-csv-import'), 
            $import['processed_products'], $import['total_products']) . '</p>';
        echo '<p style="margin: 5px 0; color: #666;">' . sprintf(__('%d products remaining', 'wc-xml-csv-import'), $remaining) . '</p>';
        echo '</div>';
        
        echo '<p style="font-size: 14px; color: #555;">' . __('This import was previously started. Would you like to:', 'wc-xml-csv-import') . '</p>';
        
        echo '<div class="resume-actions" style="display: flex; gap: 15px; margin-top: 20px;">';
        
        // Resume button
        $resume_url = admin_url('admin.php?page=wc-xml-csv-import&action=rerun&import_id=' . $import['id'] . '&resume_action=resume');
        echo '<a href="' . esc_url($resume_url) . '" class="button button-primary button-hero" style="display: flex; align-items: center; gap: 8px;">';
        echo '<span class="dashicons dashicons-controls-play" style="margin-top: 5px;"></span>';
        echo '<span>';
        echo '<strong>' . __('Continue Import', 'wc-xml-csv-import') . '</strong><br>';
        echo '<small style="font-weight: normal;">' . sprintf(__('Resume from product %d', 'wc-xml-csv-import'), $import['processed_products'] + 1) . '</small>';
        echo '</span>';
        echo '</a>';
        
        // Start Over button
        $restart_url = admin_url('admin.php?page=wc-xml-csv-import&action=rerun&import_id=' . $import['id'] . '&resume_action=restart');
        echo '<a href="' . esc_url($restart_url) . '" class="button button-secondary button-hero" style="display: flex; align-items: center; gap: 8px;" onclick="return confirm(\'' . esc_js(__('Are you sure? This will reset progress and start from the beginning.', 'wc-xml-csv-import')) . '\')">';
        echo '<span class="dashicons dashicons-update" style="margin-top: 5px;"></span>';
        echo '<span>';
        echo '<strong>' . __('Start Over', 'wc-xml-csv-import') . '</strong><br>';
        echo '<small style="font-weight: normal;">' . __('Reset and import all products', 'wc-xml-csv-import') . '</small>';
        echo '</span>';
        echo '</a>';
        
        echo '</div>';
        
        // Cancel link
        echo '<p style="margin-top: 20px;">';
        echo '<a href="' . admin_url('admin.php?page=wc-xml-csv-import-history') . '">' . __('← Back to Import History', 'wc-xml-csv-import') . '</a>';
        echo '</p>';
        
        echo '</div>'; // .card
        echo '</div>'; // .wrap
    }

    /**
     * Re-run an existing import.
     *
     * @since    1.0.0
     * @param    int $import_id Import ID to re-run
     * @param    bool $resume Whether to resume from current position (true) or restart (false)
     */
    private function rerun_import($import_id, $resume = false) {
        global $wpdb;
        
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import_logs/import_debug.log';
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n=== RERUN IMPORT CALLED ===\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Import ID: $import_id, Resume: " . ($resume ? 'YES' : 'NO') . "\n", FILE_APPEND); }
        
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $import_id), ARRAY_A);
        
        if (!$import) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Import not found!\n", FILE_APPEND); }
            wp_die(__('Import not found.', 'wc-xml-csv-import'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Import found: " . $import['name'] . "\n", FILE_APPEND); }
        
        // Prepare update data
        $update_data = array(
            'status' => 'pending'  // Use pending - kickstart will set to processing
        );
        $update_formats = array('%s');
        
        // Only reset processed count if NOT resuming
        if (!$resume) {
            $update_data['processed_products'] = 0;
            $update_formats[] = '%d';
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Resetting processed_products to 0\n", FILE_APPEND); }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Resuming from processed_products: " . $import['processed_products'] . "\n", FILE_APPEND); }
        }
        
        // Update import status
        $result = $wpdb->update(
            $wpdb->prefix . 'wc_itp_imports',
            $update_data,
            array('id' => $import_id),
            $update_formats,
            array('%d')
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("Status reset result: " . ($result !== false ? "SUCCESS" : "FAILED")); }
        
        // DON'T trigger import here - let progress page kickstart handle it
        // This prevents double processing when both this function and kickstart run
        
        // Just redirect to progress page - kickstart will start the import
        $redirect_url = admin_url('admin.php?page=wc-xml-csv-import&step=3&import_id=' . $import_id);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Display settings page.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        // Load settings
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        
        // Generate secret key if not exists
        if (empty($settings['cron_secret_key'])) {
            $settings['cron_secret_key'] = wp_generate_password(32, false);
            update_option('wc_xml_csv_ai_import_settings', $settings);
        }
        
        // Include the settings page partial (with all tabs)
        include_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/admin/partials/settings-page.php';
    }

    /**
     * Normalize PHP formula to fix common user mistakes.
     * Makes formulas more forgiving and user-friendly.
     *
     * @since    1.0.0
     * @param    string $formula Raw formula from user
     * @return   string Normalized formula ready for execution
     */
    private function normalize_php_formula($formula) {
        $formula = trim($formula);
        
        // Handle simple expressions without any control structures
        $has_control = preg_match('/\b(if|else|elseif|switch|for|foreach|while|do)\b/i', $formula);
        
        if (!$has_control) {
            // Simple expression - just add return
            $formula = rtrim($formula, ';');
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
     * Detect file type from URL path patterns.
     *
     * @since    1.0.0
     * @param    string $url The URL to analyze
     * @return   string 'xml', 'csv', or empty string if unknown
     */
    private function detect_file_type_from_url($url) {
        // First check file extension
        $path = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if (in_array($extension, array('xml', 'csv'))) {
            return $extension;
        }
        
        // Check URL path patterns (e.g., /xml/ or /csv/)
        $url_lower = strtolower($url);
        
        if (strpos($url_lower, '/xml/') !== false || strpos($url_lower, '/xml?') !== false) {
            return 'xml';
        }
        
        if (strpos($url_lower, '/csv/') !== false || strpos($url_lower, '/csv?') !== false) {
            return 'csv';
        }
        
        // Check query parameters
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query) {
            parse_str($query, $params);
            foreach ($params as $key => $value) {
                $key_lower = strtolower($key);
                $value_lower = strtolower($value);
                
                if (in_array($key_lower, array('format', 'type', 'output', 'export'))) {
                    if (in_array($value_lower, array('xml', 'csv'))) {
                        return $value_lower;
                    }
                }
            }
        }
        
        return '';
    }

    /**
     * Detect file type from file content.
     *
     * @since    1.0.0
     * @param    string $file_path Path to the file
     * @return   string 'xml' or 'csv' (defaults to xml if uncertain)
     */
    private function detect_file_type_from_content($file_path) {
        if (!file_exists($file_path)) {
            return 'xml'; // Default fallback
        }
        
        // Read first 4KB of file
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return 'xml';
        }
        
        $sample = fread($handle, 4096);
        fclose($handle);
        
        if (empty($sample)) {
            return 'xml';
        }
        
        // Trim BOM and whitespace
        $sample = ltrim($sample, "\xEF\xBB\xBF\xFE\xFF\xFF\xFE\x00"); // UTF-8, UTF-16 BOMs
        $sample = ltrim($sample);
        
        // Check for XML declaration or root element
        if (strpos($sample, '<?xml') === 0) {
            return 'xml';
        }
        
        // Check if starts with < (likely XML element)
        if (strpos($sample, '<') === 0) {
            return 'xml';
        }
        
        // Check for common CSV patterns
        // CSV typically has commas or semicolons as delimiters
        $first_line_end = strpos($sample, "\n");
        $first_line = $first_line_end !== false ? substr($sample, 0, $first_line_end) : $sample;
        
        // Count potential delimiters in first line
        $comma_count = substr_count($first_line, ',');
        $semicolon_count = substr_count($first_line, ';');
        $tab_count = substr_count($first_line, "\t");
        
        // If we have multiple delimiters, likely CSV
        if ($comma_count >= 2 || $semicolon_count >= 2 || $tab_count >= 2) {
            // Additional check: CSV shouldn't have XML-like content
            if (strpos($sample, '</') === false && strpos($sample, '/>') === false) {
                return 'csv';
            }
        }
        
        // Check Content-Type from response headers if available in meta
        // This is a fallback for downloaded files
        
        // Default to XML if uncertain
        return 'xml';
    }

    /**
     * Save plugin settings.
     *
     * @since    1.0.0
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['wc_xml_csv_ai_import_settings_nonce'], 'wc_xml_csv_ai_import_settings')) {
            return;
        }
        
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        
        $settings['chunk_size'] = intval($_POST['chunk_size']);
        $settings['max_file_size'] = intval($_POST['max_file_size']);
        $settings['default_ai_provider'] = sanitize_text_field($_POST['default_ai_provider']);
        $settings['ai_api_keys'] = array();
        
        if (isset($_POST['ai_api_keys']) && is_array($_POST['ai_api_keys'])) {
            foreach ($_POST['ai_api_keys'] as $provider => $key) {
                $settings['ai_api_keys'][sanitize_text_field($provider)] = sanitize_text_field($key);
            }
        }
        
        update_option('wc_xml_csv_ai_import_settings', $settings);
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('Settings saved successfully.', 'wc-xml-csv-import') . '</p>';
        echo '</div>';
    }

    /**
     * Handle file upload AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_file_upload() {
        // Log every invocation to detect duplicates
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ UPLOAD HANDLER CALLED at ' . microtime(true)); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ Request ID: ' . ($_POST['import_name'] ?? 'NO NAME') . ' - ' . uniqid()); }
        
        // Verify nonce - support both standard POST and FormData
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '');
        if (!wp_verify_nonce($nonce, 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        try {
            // Debug: Log received data (remove this in production)
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Upload data received: ' . print_r($_POST, true)); }
            
            $upload_method = sanitize_text_field($_POST['upload_method'] ?? '');
            $import_name = sanitize_text_field($_POST['import_name'] ?? '');
            $schedule_type = sanitize_text_field($_POST['schedule_type'] ?? 'once');
            $product_wrapper = sanitize_text_field($_POST['product_wrapper'] ?? 'product');
            $update_existing = isset($_POST['update_existing']) ? '1' : '0';
            $skip_unchanged = isset($_POST['skip_unchanged']) ? '1' : '0';
            $force_file_type = isset($_POST['force_file_type']) ? sanitize_text_field($_POST['force_file_type']) : 'auto';
            $handle_missing = isset($_POST['handle_missing']) ? '1' : '0';
            $missing_action = isset($_POST['missing_action']) ? sanitize_text_field($_POST['missing_action']) : 'draft';
            $delete_variations = isset($_POST['delete_variations']) ? '1' : '0';
            
            // Validate required fields
            if (empty($import_name)) {
                throw new Exception(__('Import name is required.', 'wc-xml-csv-import'));
            }
            
            if (empty($upload_method)) {
                throw new Exception(__('Upload method is required.', 'wc-xml-csv-import'));
            }
            
            $file_path = '';
            $file_type = '';
            
            if ($upload_method === 'file' && isset($_FILES['file'])) {
                // Handle file upload
                $uploaded_file = $_FILES['file'];
                
                if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception(__('File upload error.', 'wc-xml-csv-import'));
                }
                
                $file_type = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
                
                // Allow files without extension (from URLs) or with xml/csv extension
                if (!empty($file_type) && !in_array($file_type, array('xml', 'csv'))) {
                    throw new Exception(__('Invalid file type. Only XML and CSV files are allowed.', 'wc-xml-csv-import'));
                }
                
                // Apply force file type if set
                if ($force_file_type !== 'auto') {
                    $file_type = $force_file_type;
                } elseif (empty($file_type)) {
                    // Auto-detect from content if no extension
                    $file_type = $this->detect_file_type_from_content($uploaded_file['tmp_name']);
                }
                
                $upload_dir = wp_upload_dir();
                // Fix uppercase /Var issue
                $basedir = str_replace('/Var/', '/var/', $upload_dir['basedir']);
                $plugin_upload_dir = $basedir . '/woo_xml_csv_ai_smart_import/';
                
                // Create directory if it doesn't exist
                if (!is_dir($plugin_upload_dir)) {
                    wp_mkdir_p($plugin_upload_dir);
                }
                
                $file_path = $plugin_upload_dir . time() . '_' . sanitize_file_name($uploaded_file['name']);
                
                if (!move_uploaded_file($uploaded_file['tmp_name'], $file_path)) {
                    throw new Exception(__('Failed to upload file.', 'wc-xml-csv-import'));
                }
                
            } elseif ($upload_method === 'url') {
                // Handle URL upload
                $file_url = esc_url_raw($_POST['file_url'] ?? '');
                
                if (empty($file_url)) {
                    throw new Exception(__('File URL is required.', 'wc-xml-csv-import'));
                }

                // Download file
                $upload_dir = wp_upload_dir();
                // Fix uppercase /Var issue
                $basedir = str_replace('/Var/', '/var/', $upload_dir['basedir']);
                $plugin_upload_dir = $basedir . '/woo_xml_csv_ai_smart_import/';

                // Create directory if it doesn't exist
                if (!is_dir($plugin_upload_dir)) {
                    wp_mkdir_p($plugin_upload_dir);
                }

                $base_filename = sanitize_file_name(basename(parse_url($file_url, PHP_URL_PATH)));
                if (empty($base_filename)) {
                    $base_filename = 'download';
                }
                
                // Save WITHOUT extension - same as Browse upload
                $file_path = $plugin_upload_dir . time() . '_' . $base_filename;

                // Download with streaming for large files
                $temp_file = fopen($file_path, 'w');
                if (!$temp_file) {
                    throw new Exception(__('Failed to create temporary file.', 'wc-xml-csv-import'));
                }
                
                $response = wp_remote_get($file_url, array(
                    'timeout' => 600,
                    'sslverify' => false,
                    'stream' => true,
                    'filename' => $file_path
                ));
                
                if (is_wp_error($response)) {
                    fclose($temp_file);
                    if (file_exists($file_path)) unlink($file_path);
                    throw new Exception(__('Failed to download file from URL: ', 'wc-xml-csv-import') . $response->get_error_message());
                }

                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code !== 200) {
                    fclose($temp_file);
                    if (file_exists($file_path)) unlink($file_path);
                    throw new Exception(__('Failed to download file from URL. HTTP Status: ', 'wc-xml-csv-import') . $response_code);
                }
                
                fclose($temp_file);
                
                if (!file_exists($file_path) || filesize($file_path) === 0) {
                    throw new Exception(__('Downloaded file is empty or failed to save.', 'wc-xml-csv-import'));
                }
                
                // Wait for file to fully download - check file size stability
                $prev_size = 0;
                $stable_count = 0;
                $max_wait = 60; // 60 seconds max
                $waited = 0;
                
                while ($waited < $max_wait) {
                    clearstatcache(true, $file_path);
                    $current_size = filesize($file_path);
                    
                    if ($current_size === $prev_size) {
                        $stable_count++;
                        if ($stable_count >= 3) {
                            // File size stable for 3 checks (1.5 seconds) - download complete
                            break;
                        }
                    } else {
                        $stable_count = 0;
                        $prev_size = $current_size;
                    }
                    
                    usleep(500000); // 0.5 seconds
                    $waited++;
                }
                
                // Determine file type
                if ($force_file_type !== 'auto') {
                    $file_type = $force_file_type;
                } else {
                    // Try to detect from URL path
                    $file_type = $this->detect_file_type_from_url($file_url);
                    
                    // If still unknown, detect from downloaded content
                    if (empty($file_type)) {
                        $file_type = $this->detect_file_type_from_content($file_path);
                    }
                }
                
            } else {
                throw new Exception(__('No file provided.', 'wc-xml-csv-import'));
            }
            
            // Validate file exists
            if (!file_exists($file_path)) {
                throw new Exception(__('File upload failed - file does not exist.', 'wc-xml-csv-import'));
            }
            
            // Validate file size
            $file_size = filesize($file_path);
            if ($file_size === 0) {
                unlink($file_path);
                throw new Exception(__('File is empty.', 'wc-xml-csv-import'));
            }
            
            // Load parser classes if not already loaded
            if ($file_type === 'xml') {
                if (!class_exists('WC_XML_CSV_AI_Import_XML_Parser')) {
                    require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-xml-parser.php';
                }
                $parser = new WC_XML_CSV_AI_Import_XML_Parser();
                $validation = $parser->validate_xml_file($file_path);
            } else {
                if (!class_exists('WC_XML_CSV_AI_Import_CSV_Parser')) {
                    require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-csv-parser.php';
                }
                $parser = new WC_XML_CSV_AI_Import_CSV_Parser();
                $validation = $parser->validate_csv_file($file_path);
            }
            
            if (!$validation['valid']) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                throw new Exception($validation['message']);
            }
            
            // Count products before redirect
            $total_products = 0;
            if ($file_type === 'xml') {
                $count_result = $parser->count_products_and_extract_structure($file_path, $product_wrapper);
                if ($count_result['success']) {
                    $total_products = $count_result['total_products'];
                }
            } else {
                $count_result = $parser->count_rows_and_extract_structure($file_path);
                if ($count_result['success']) {
                    $total_products = $count_result['total_rows'];
                }
            }
            
            // Store in session
            if (!session_id()) {
                session_start();
            }
            $_SESSION['wc_import_total_products'] = $total_products;
            session_write_close();
            
            // Create import record in database
            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_itp_imports';
            
            $wpdb->insert(
                $table_name,
                array(
                    'name' => $import_name,
                    'file_path' => $file_path,
                    'file_url' => $file_path, // Store same path for backward compatibility
                    'file_type' => $file_type,
                    'product_wrapper' => $product_wrapper,
                    'schedule_type' => $schedule_type,
                    'update_existing' => $update_existing,
                    'skip_unchanged' => $skip_unchanged,
                    'handle_missing' => $handle_missing,
                    'missing_action' => $missing_action,
                    'delete_variations' => $delete_variations,
                    'total_products' => $total_products,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s')
            );
            
            $import_id = $wpdb->insert_id;
            
            wp_send_json_success(array(
                'message' => __('File uploaded successfully.', 'wc-xml-csv-import'),
                'total_products' => $total_products,
                'import_id' => $import_id,
                'redirect_url' => admin_url('admin.php?page=wc-xml-csv-import&step=2&import_id=' . $import_id)
            ));
            
        } catch (Exception $e) {
            // Debug: Log the error (remove this in production)
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Upload error: ' . $e->getMessage()); }
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle parse structure AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_parse_structure() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Parse structure started'); }
            
            $file_path = sanitize_text_field($_POST['file_path']);
            $file_type = sanitize_text_field($_POST['file_type']);
            $page = intval($_POST['page'] ?? 1);
            $per_page = intval($_POST['per_page'] ?? 5);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                error_log('WC XML CSV AI Import - Parse structure params: ' . json_encode([
                    'file_path' => $file_path,
                    'file_type' => $file_type,
                    'page' => $page,
                    'per_page' => $per_page
                ])); 
            }
            
            // Wait for file to be fully written - retry up to 5 times
            $max_retries = 5;
            $retry_delay = 200000; // 200ms in microseconds
            $file_ready = false;
            
            for ($i = 0; $i < $max_retries; $i++) {
                if (file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    if ($file_size > 0) {
                        // Try to open and read file to ensure it's not locked
                        clearstatcache(true, $file_path);
                        $handle = @fopen($file_path, 'r');
                        if ($handle) {
                            fclose($handle);
                            $file_ready = true;
                            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - File ready after ' . ($i + 1) . ' attempts'); }
                            break;
                        }
                    }
                }
                if ($i < $max_retries - 1) {
                    usleep($retry_delay);
                }
            }
            
            if (!$file_ready) {
                throw new Exception(__('File is not ready yet. Please refresh the page and try again.', 'wc-xml-csv-import'));
            }
            
            if ($file_type === 'xml') {
                $product_wrapper = sanitize_text_field($_POST['product_wrapper']);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Using XML parser with wrapper: ' . $product_wrapper); }
                
                $xml_parser = new WC_XML_CSV_AI_Import_XML_Parser();
                $result = $xml_parser->parse_structure($file_path, $product_wrapper, $page, $per_page);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Using CSV parser'); }
                $csv_parser = new WC_XML_CSV_AI_Import_CSV_Parser();
                $result = $csv_parser->parse_structure($file_path, $page, $per_page);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Parse structure completed successfully'); }
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Parse structure error: ' . $e->getMessage()); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Parse structure trace: ' . $e->getTraceAsString()); }
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle start import AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_start_import() {
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ handle_start_import CALLED! ★★★'); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST data: ' . print_r($_POST, true)); }
        
        // Verify nonce - support both standard POST and FormData
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '');
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce check: ' . ($nonce ? 'exists' : 'missing')); }
        
        if (!wp_verify_nonce($nonce, 'wc_xml_csv_ai_import_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ NONCE VERIFICATION FAILED! ★★★'); }
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Nonce verified successfully'); }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        try {
            // Debug: Log received data (remove this in production)
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Start import data received'); }
            
            // Decode JSON strings from FormData
            $field_mapping = array();
            $custom_fields = array();
            $import_filters = array();
            
            if (isset($_POST['field_mapping_json'])) {
                $field_mapping = json_decode(stripslashes($_POST['field_mapping_json']), true);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('field_mapping from JSON: ' . print_r($field_mapping, true)); }
            }
            
            if (isset($_POST['custom_fields_json'])) {
                $custom_fields = json_decode(stripslashes($_POST['custom_fields_json']), true);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('custom_fields from JSON: ' . print_r($custom_fields, true)); }
            }
            
            if (isset($_POST['import_filters_json'])) {
                $import_filters = json_decode(stripslashes($_POST['import_filters_json']), true);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('import_filters from JSON: ' . print_r($import_filters, true)); }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('After decode - field_mapping count: ' . count($field_mapping)); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Attributes check: ' . print_r($field_mapping['attributes_variations'] ?? 'NOT SET', true)); }
            
            // Check if this is updating existing import
            $import_id = isset($_POST['import_id']) ? intval($_POST['import_id']) : 0;
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ handle_start_import: import_id from POST = ' . $import_id); }
            
            // Collect import data from form fields
            $import_data = array(
                'import_id' => $import_id,  // Pass import_id to importer
                'import_name' => sanitize_text_field($_POST['import_name'] ?? ''),
                'file_path' => sanitize_text_field($_POST['file_path'] ?? ''),
                'file_type' => sanitize_text_field($_POST['file_type'] ?? ''),
                'schedule_type' => sanitize_text_field($_POST['schedule_type'] ?? 'once'),
                'product_wrapper' => sanitize_text_field($_POST['product_wrapper'] ?? 'product'),
                'update_existing' => isset($_POST['update_existing']) ? $_POST['update_existing'] : '0',
                'skip_unchanged' => isset($_POST['skip_unchanged']) ? $_POST['skip_unchanged'] : '0',
                'field_mapping' => $field_mapping,
                'processing_modes' => $_POST['processing_modes'] ?? array(),
                'processing_configs' => $_POST['processing_configs'] ?? array(),
                'ai_settings' => $_POST['ai_settings'] ?? array(),
                'custom_fields' => $custom_fields,
                'import_filters' => $import_filters,
                'filter_logic' => sanitize_text_field($_POST['filter_logic'] ?? 'AND'),
                'draft_non_matching' => isset($_POST['draft_non_matching']) ? 1 : 0
            );
            
            // Load importer class if not loaded
            if (!class_exists('WC_XML_CSV_AI_Import_Importer')) {
                require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-importer.php';
            }
            
            $importer = new WC_XML_CSV_AI_Import_Importer();
            $import_id = $importer->start_import($import_data);
            
            wp_send_json_success(array(
                'import_id' => $import_id,
                'message' => __('Import started successfully.', 'wc-xml-csv-import'),
                'debug' => 'Import ID: ' . $import_id . ', File: ' . $import_data['file_path']
            ));
            
        } catch (Exception $e) {
            // Debug: Log the error (remove this in production)
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Start import error: ' . $e->getMessage()); }
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle kickstart import AJAX request.
     * Triggers import processing for stuck imports at 0%.
     *
     * @since    1.0.0
     */
    public function handle_kickstart_import() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        $import_id = intval($_POST['import_id']);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ KICKSTART IMPORT CALLED for ID: ' . $import_id); }
        
        try {
            global $wpdb;
            
            // Check if import is already in progress or completed
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                $import_id
            ));
            
            if (!$import) {
                wp_send_json_error(array('message' => __('Import not found.', 'wc-xml-csv-import')));
                return;
            }
            
            // Don't kickstart if already completed or if products already processed
            if ($import->status === 'completed') {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ KICKSTART: Skipped - import already completed'); }
                wp_send_json_success(array(
                    'message' => __('Import already completed.', 'wc-xml-csv-import'),
                    'skipped' => true
                ));
                return;
            }
            
            if (intval($import->processed_products) > 0) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ KICKSTART: Skipped - already processed ' . $import->processed_products . ' products'); }
                wp_send_json_success(array(
                    'message' => __('Import already in progress.', 'wc-xml-csv-import'),
                    'skipped' => true
                ));
                return;
            }
            
            // Trigger import chunk processing directly
            do_action('wc_xml_csv_ai_import_process_chunk', $import_id, 0, 5);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ KICKSTART: Import triggered successfully'); }
            
            wp_send_json_success(array(
                'message' => __('Import processing started.', 'wc-xml-csv-import')
            ));
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ KICKSTART ERROR: ' . $e->getMessage()); }
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle cron ping AJAX request - triggers WP-Cron to run.
     * 
     * This is called every 2 seconds from progress page to ensure
     * WP-Cron continues processing import chunks.
     * Also checks for stuck imports and reschedules them.
     *
     * @since    1.0.0
     */
    public function handle_ping_cron() {
        // No nonce/permission check - this is a simple ping to trigger cron
        // and is safe because it only triggers already-scheduled events
        
        $import_id = isset($_REQUEST['import_id']) ? intval($_REQUEST['import_id']) : 0;
        
        // Trigger WP-Cron
        spawn_cron();
        
        // Check if import is stuck (has processing status but no scheduled cron event)
        if ($import_id > 0) {
            global $wpdb;
            $import = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
                $import_id
            ));
            
            if ($import && $import->status === 'processing') {
                // Check if there's a scheduled cron event for this import
                $has_scheduled_event = false;
                $cron_array = _get_cron_array();
                if (is_array($cron_array)) {
                    foreach ($cron_array as $timestamp => $crons) {
                        if (isset($crons['wc_xml_csv_ai_import_process_chunk'])) {
                            foreach ($crons['wc_xml_csv_ai_import_process_chunk'] as $key => $event) {
                                if (isset($event['args'][0]) && $event['args'][0] == $import_id) {
                                    $has_scheduled_event = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Check for stale lock (lock exists but is older than 3 minutes)
                $lock_key = 'wc_import_lock_' . $import_id;
                $lock_time_key = 'wc_import_lock_time_' . $import_id;
                $lock_exists = get_transient($lock_key) !== false;
                $lock_time = get_transient($lock_time_key);
                $lock_age = $lock_time ? (time() - intval($lock_time)) : 999;
                $lock_is_stale = $lock_exists && $lock_age > 180;
                
                // If no scheduled event and no active lock (or stale lock), reschedule
                if (!$has_scheduled_event && (!$lock_exists || $lock_is_stale)) {
                    // Clear stale lock if exists
                    if ($lock_is_stale) {
                        delete_transient($lock_key);
                        delete_transient($lock_time_key);
                    }
                    
                    // Calculate next offset based on already processed products
                    $processed = intval($import->processed_products);
                    $chunk_size = 5; // Match the chunk size used elsewhere
                    
                    // Schedule next chunk
                    wp_schedule_single_event(time(), 'wc_xml_csv_ai_import_process_chunk', array($import_id, $processed, $chunk_size));
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('★★★ PING_CRON: Rescheduled stuck import ' . $import_id . ' at offset ' . $processed); }
                }
            }
        }
        
        // Return minimal response
        wp_send_json_success(array('pinged' => true));
    }

    /**
     * Handle test URL AJAX request.
     * Tests if a URL is accessible via wp_remote_get
     *
     * @since    1.0.0
     */
    public function handle_test_url() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wc-xml-csv-import')));
            return;
        }
        
        $url = esc_url_raw($_POST['url'] ?? '');
        
        if (empty($url)) {
            wp_send_json_error(array('message' => __('URL is required.', 'wc-xml-csv-import')));
            return;
        }
        
        try {
            // Test URL accessibility using wp_remote_get
            $response = wp_remote_get($url, array(
                'timeout' => 10,
                'sslverify' => false,  // Allow self-signed certificates on local servers
                'user-agent' => 'WooCommerce XML CSV AI Import/1.0'
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                wp_send_json_success(array(
                    'message' => sprintf(__('URL is accessible (HTTP %d).', 'wc-xml-csv-import'), $response_code)
                ));
            } else {
                wp_send_json_error(array(
                    'message' => sprintf(__('URL returned HTTP %d. Expected 200.', 'wc-xml-csv-import'), $response_code)
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }

    /**
     * Handle get progress AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_get_progress() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        global $wpdb;
        
        $import_id = intval($_POST['import_id']);
        
        $import = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d", $import_id),
            ARRAY_A
        );
        
        if (!$import) {
            wp_send_json_error(array('message' => __('Import not found.', 'wc-xml-csv-import')));
        }
        
        // Get only the 15 most recent logs, ordered by ID (more reliable than timestamp)
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_import_logs WHERE import_id = %d ORDER BY id DESC LIMIT 15",
                $import_id
            ),
            ARRAY_A
        );
        
        $percentage = $import['total_products'] > 0 ? round(($import['processed_products'] / $import['total_products']) * 100) : 0;
        
        wp_send_json_success(array(
            'status' => $import['status'],
            'total_products' => $import['total_products'],
            'processed_products' => $import['processed_products'],
            'percentage' => $percentage,
            'logs' => $logs
        ));
    }

    /**
     * Handle AI auto-mapping AJAX request.
     * ADVANCED tier only feature.
     *
     * @since    1.0.0
     */
    public function handle_ai_auto_mapping() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wc-xml-csv-import')));
            return;
        }
        
        // Check if AI mapping is available (ADVANCED tier)
        // For now, allow if any AI API key is configured
        $ai_settings = get_option('wc_xml_csv_ai_import_ai_settings', array());
        $has_ai_key = !empty($ai_settings['openai_api_key']) || 
                      !empty($ai_settings['claude_api_key']) || 
                      !empty($ai_settings['gemini_api_key']) ||
                      !empty($ai_settings['grok_api_key']);
        
        if (!$has_ai_key) {
            wp_send_json_error(array(
                'message' => __('AI auto-mapping requires an AI API key. Please configure one in Settings.', 'wc-xml-csv-import')
            ));
            return;
        }
        
        try {
            $source_fields = isset($_POST['source_fields']) ? array_map('sanitize_text_field', $_POST['source_fields']) : array();
            $provider = sanitize_text_field($_POST['provider'] ?? 'openai');
            $file_type = sanitize_text_field($_POST['file_type'] ?? 'xml');
            $sample_data = isset($_POST['sample_data']) ? $_POST['sample_data'] : array();
            
            if (empty($source_fields)) {
                wp_send_json_error(array('message' => __('No source fields provided.', 'wc-xml-csv-import')));
                return;
            }
            
            // Sanitize sample data
            if (is_array($sample_data)) {
                array_walk_recursive($sample_data, function(&$value) {
                    if (is_string($value)) {
                        $value = wp_kses_post($value);
                    }
                });
            }
            
            $ai_providers = new WC_XML_CSV_AI_Import_AI_Providers();
            $result = $ai_providers->auto_map_fields($source_fields, $provider, $file_type, $sample_data);
            
            // Get stats
            $stats = isset($result['stats']) ? $result['stats'] : array(
                'total_fields' => count($source_fields),
                'ai_mapped' => count($result['mappings']),
                'auto_filled' => 0,
                'unmapped' => count($result['unmapped'] ?? array())
            );
            
            // Build response array
            $response = array(
                'mappings' => $result['mappings'],
                'confidence' => $result['confidence'],
                'unmapped' => $result['unmapped'],
                'auto_filled' => $result['auto_filled'] ?? array(),
                'mapped_count' => count($result['mappings']),
                'provider' => $result['provider'],
                'stats' => $stats,
                'message' => sprintf(
                    __('AI mapped %d fields, auto-filled %d fields. Total: %d of %d fields mapped.', 'wc-xml-csv-import'),
                    $stats['ai_mapped'],
                    $stats['auto_filled'],
                    count($result['mappings']),
                    $stats['total_fields']
                )
            );
            
            // Add warning if some fields are still unmapped
            if ($stats['unmapped'] > 0) {
                $response['message'] .= ' ' . sprintf(
                    __('Warning: %d fields could not be mapped automatically.', 'wc-xml-csv-import'),
                    $stats['unmapped']
                );
            }
            
            // Add product structure info if available
            if (!empty($result['product_structure'])) {
                $response['product_structure'] = $result['product_structure'];
                
                // Update message if variable product detected
                if (!empty($result['product_structure']['has_variations'])) {
                    $response['message'] .= ' ' . __('Variable product structure detected.', 'wc-xml-csv-import');
                    
                    if (!empty($result['product_structure']['detected_attributes'])) {
                        $attr_count = count($result['product_structure']['detected_attributes']);
                        $response['message'] .= ' ' . sprintf(
                            _n('%d attribute found.', '%d attributes found.', $attr_count, 'wc-xml-csv-import'),
                            $attr_count
                        );
                    }
                }
            }
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle license activation AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_activate_license() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wc-xml-csv-import')));
            return;
        }
        
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        
        if (empty($license_key)) {
            wp_send_json_error(array('message' => __('Please enter a license key.', 'wc-xml-csv-import')));
            return;
        }
        
        // Try to activate the license
        $result = WC_XML_CSV_AI_Import_License::activate_license($license_key);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'tier' => $result['tier']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    /**
     * Handle license deactivation AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_deactivate_license() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wc-xml-csv-import')));
            return;
        }
        
        // Deactivate the license
        $result = WC_XML_CSV_AI_Import_License::deactivate_license();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message']
            ));
        }
    }

    /**
     * Handle test AI AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_test_ai() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        try {
            $provider = sanitize_text_field($_POST['provider']);
            $test_prompt = wp_unslash($_POST['test_prompt']); // Don't sanitize - may contain HTML
            $test_value = wp_unslash($_POST['test_value']); // Don't sanitize - may contain HTML
            
            // Build test context from sample data if available
            $context = array();
            if (!empty($_POST['sample_data'])) {
                $sample_data = $_POST['sample_data'];
                if (!empty($sample_data['name'])) {
                    $context['name'] = $sample_data['name'];
                }
                if (!empty($sample_data['price'])) {
                    $context['price'] = $sample_data['price'];
                }
                if (!empty($sample_data['ean'])) {
                    $context['ean'] = $sample_data['ean'];
                }
                if (!empty($sample_data['brand'])) {
                    $context['brand'] = $sample_data['brand'];
                }
                if (!empty($sample_data['category'])) {
                    $context['category'] = $sample_data['category'];
                }
            }
            
            $ai_providers = new WC_XML_CSV_AI_Import_AI_Providers();
            $result = $ai_providers->process_field($test_value, $test_prompt, array('provider' => $provider), $context);
            
            wp_send_json_success(array(
                'result' => $result,
                'message' => __('AI test completed successfully.', 'wc-xml-csv-import')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle test PHP formula AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_test_php() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        // PRO feature check - PHP formulas require PRO license
        if (!WC_XML_CSV_AI_Import_Features::is_available('php_processing')) {
            wp_send_json_error(array(
                'message' => __('PHP Formula processing is a PRO feature. Please upgrade to use this functionality.', 'wc-xml-csv-import'),
                'upgrade_required' => true,
                'upgrade_url' => WC_XML_CSV_AI_Import_License::get_upgrade_url()
            ));
            return;
        }
        
        try {
            $formula = stripslashes($_POST['formula']);
            $test_value = $_POST['test_value'];
            $sample_data = $_POST['sample_data'] ?? array();
            
            // Prepare variables for formula evaluation
            $value = $test_value;
            $name = $sample_data['product_name'] ?? ($sample_data['name'] ?? '');
            $price = $sample_data['price'] ?? ($sample_data['regular_price'] ?? 0);
            $sku = $sample_data['id'] ?? ($sample_data['sku'] ?? '');
            $category = $sample_data['category'] ?? '';
            $brand = $sample_data['brand'] ?? '';
            $weight = $sample_data['gross_weight'] ?? ($sample_data['weight'] ?? 0);
            $length = $sample_data['package_dimensions.length'] ?? ($sample_data['length'] ?? 0);
            $width = $sample_data['package_dimensions.width'] ?? ($sample_data['width'] ?? 0);
            $height = $sample_data['package_dimensions.height'] ?? ($sample_data['height'] ?? 0);
            $ean = $sample_data['eans.ean'] ?? ($sample_data['ean'] ?? '');
            $gtin = $sample_data['gtin'] ?? '';
            
            // Smart formula normalization
            $formula = $this->normalize_php_formula($formula);
            
            // Execute formula - wrap in anonymous function to allow complex code
            $wrapped_formula = "
                \$func = function() use (\$value, \$name, \$price, \$sku, \$category, \$brand, \$weight, \$length, \$width, \$height, \$ean, \$gtin) {
                    {$formula}
                };
                return \$func();
            ";
            
            $result = eval($wrapped_formula);
            
            // Format result for display
            $formatted_result = is_array($result) ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $result;
            
            wp_send_json_success(array(
                'result' => $formatted_result,
                'raw_result' => $result,
                'message' => __('PHP formula test completed successfully.', 'wc-xml-csv-import')
            ));
            
        } catch (ParseError $e) {
            wp_send_json_error(array(
                'message' => 'Syntax error: ' . $e->getMessage()
            ));
        } catch (Throwable $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle save mapping AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_save_mapping() {
        global $wpdb;
        
        // DEBUG: Log that handler was called
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents(__DIR__ . '/../../save_mapping_debug.log', date('Y-m-d H:i:s') . " - AJAX handler called\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents(__DIR__ . '/../../save_mapping_debug.log', "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND); }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        try {
            $import_id = intval($_POST['import_id']);
            $mapping_data_json = stripslashes($_POST['mapping_data']);
            $mapping_data = json_decode($mapping_data_json, true);
            
            if (!$import_id || !$mapping_data) {
                wp_send_json_error(array('message' => __('Invalid import ID or mapping data.', 'wc-xml-csv-import')));
                return;
            }
            
            // Prepare field mappings for database
            $field_mappings = array();
            
            // Process standard field mappings
            if (isset($mapping_data['field_mapping'])) {
                $field_mappings = $mapping_data['field_mapping'];
            }
            
            // Process custom fields - save as array, not individual fields
            if (isset($mapping_data['custom_fields']) && is_array($mapping_data['custom_fields'])) {
                // Add each custom field with a unique key
                $cf_index = 0;
                foreach ($mapping_data['custom_fields'] as $field_config) {
                    if (is_array($field_config) && !empty($field_config['name'])) {
                        $field_mappings['custom_' . $cf_index] = $field_config;
                        $cf_index++;
                    }
                }
            }
            
            // Prepare update data
            $update_data = array(
                'field_mappings' => json_encode($field_mappings, JSON_UNESCAPED_UNICODE)
            );
            
            // Add filters if present
            if (isset($mapping_data['import_filters'])) {
                $update_data['import_filters'] = json_encode($mapping_data['import_filters'], JSON_UNESCAPED_UNICODE);
            }
            
            // Add filter logic if present
            if (isset($mapping_data['filter_logic'])) {
                $update_data['filter_logic'] = sanitize_text_field($mapping_data['filter_logic']);
            }
            
            // Add draft_non_matching if present
            if (isset($mapping_data['draft_non_matching'])) {
                $update_data['draft_non_matching'] = intval($mapping_data['draft_non_matching']);
            }
            
            // Add update_existing if present (CRITICAL FIX)
            if (isset($mapping_data['update_existing'])) {
                $update_data['update_existing'] = $mapping_data['update_existing'] === '1' ? '1' : '0';
            }
            
            // Add skip_unchanged if present (CRITICAL FIX)
            if (isset($mapping_data['skip_unchanged'])) {
                $update_data['skip_unchanged'] = $mapping_data['skip_unchanged'] === '1' ? '1' : '0';
            }
            
            // Add batch_size if present (CRITICAL FIX)
            if (isset($mapping_data['batch_size'])) {
                $update_data['batch_size'] = intval($mapping_data['batch_size']);
            }
            
            // Add schedule_type if present (for scheduled imports)
            if (isset($mapping_data['schedule_type'])) {
                $valid_schedules = array('none', 'disabled', '15min', 'hourly', '6hours', 'daily', 'weekly', 'monthly');
                $schedule = sanitize_text_field($mapping_data['schedule_type']);
                if (in_array($schedule, $valid_schedules)) {
                    $update_data['schedule_type'] = $schedule;
                }
            }
            
            // Add schedule_method if present (action_scheduler or server_cron)
            if (isset($mapping_data['schedule_method'])) {
                $valid_methods = array('action_scheduler', 'server_cron');
                $method = sanitize_text_field($mapping_data['schedule_method']);
                if (in_array($method, $valid_methods)) {
                    $update_data['schedule_method'] = $method;
                }
            }
            
            // Update database
            $table_name = $wpdb->prefix . 'wc_itp_imports';
            
            // Build format specifiers dynamically based on update_data keys
            $format_map = array(
                'field_mappings' => '%s',
                'import_filters' => '%s',
                'filter_logic' => '%s',
                'draft_non_matching' => '%d',
                'update_existing' => '%s',
                'skip_unchanged' => '%s',
                'batch_size' => '%d',
                'schedule_type' => '%s',
                'schedule_method' => '%s'
            );
            $formats = array();
            foreach (array_keys($update_data) as $key) {
                $formats[] = isset($format_map[$key]) ? $format_map[$key] : '%s';
            }
            
            $result = $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $import_id),
                $formats,
                array('%d')
            );
            
            if ($result === false) {
                wp_send_json_error(array('message' => __('Database error: ', 'wc-xml-csv-import') . $wpdb->last_error));
                return;
            }
            
            wp_send_json_success(array(
                'message' => __('Mapping configuration saved successfully.', 'wc-xml-csv-import'),
                'updated_fields' => count($field_mappings)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle test shipping formula AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_test_shipping() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import')));
            return;
        }
        
        // PRO feature check - Shipping formulas require PRO license
        if (!WC_XML_CSV_AI_Import_Features::is_available('php_processing')) {
            wp_send_json_error(array(
                'message' => __('Shipping formula processing is a PRO feature. Please upgrade to use this functionality.', 'wc-xml-csv-import'),
                'upgrade_required' => true,
                'upgrade_url' => WC_XML_CSV_AI_Import_License::get_upgrade_url()
            ));
            return;
        }
        
        try {
            $formula = stripslashes($_POST['formula']);
            $weight = floatval($_POST['weight']);
            $length = floatval($_POST['length']);
            $width = floatval($_POST['width']);
            $height = floatval($_POST['height']);
            
            // Execute formula
            $wrapped_formula = "
                \$func = function() use (\$weight, \$length, \$width, \$height) {
                    {$formula}
                };
                return \$func();
            ";
            
            $result = eval($wrapped_formula);
            
            wp_send_json_success(array(
                'result' => $result,
                'message' => __('Shipping formula test completed successfully.', 'wc-xml-csv-import')
            ));
            
        } catch (ParseError $e) {
            wp_send_json_error(array(
                'message' => 'Syntax error: ' . $e->getMessage()
            ));
        } catch (Throwable $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle cron import execution.
     *
     * @since    1.0.0
     */
    public function handle_cron_import() {
        global $wpdb;
        
        // PRO-only feature: scheduled imports require PRO license
        if (!WC_XML_CSV_AI_Import_Features::is_available('scheduled_import')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Scheduling is a PRO feature'); }
            wp_die('Scheduled imports require PRO license', 'Forbidden', array('response' => 403));
        }
        
        // Verify secret key
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $secret = sanitize_text_field($_GET['secret'] ?? $_REQUEST['secret'] ?? '');
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Received secret: ' . $secret); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Expected secret: ' . ($settings['cron_secret_key'] ?? 'NOT SET')); }
        
        if (empty($secret) || empty($settings['cron_secret_key']) || $secret !== $settings['cron_secret_key']) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Invalid secret key'); }
            wp_die('Unauthorized', 'Unauthorized', array('response' => 401));
        }
        
        // Find scheduled imports that are ready to run
        $table_name = $wpdb->prefix . 'wc_itp_imports';
        $current_time = current_time('mysql');
        
        $scheduled_imports = $wpdb->get_results(
            $wpdb->prepare("
                SELECT * FROM {$table_name}
                WHERE status IN ('scheduled', 'processing', 'completed')
                AND schedule_type IN ('15min', 'hourly', '6hours', 'daily', 'weekly', 'monthly')
                AND (last_run IS NULL OR DATE_ADD(last_run, INTERVAL 
                    CASE schedule_type
                        WHEN '15min' THEN 15
                        WHEN 'hourly' THEN 60
                        WHEN '6hours' THEN 360
                        WHEN 'daily' THEN 1440
                        WHEN 'weekly' THEN 10080
                        WHEN 'monthly' THEN 43200
                        ELSE 1
                    END MINUTE
                ) <= %s)
                LIMIT 5
            ", $current_time),
            ARRAY_A
        );
        
        if (empty($scheduled_imports)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: No imports ready to run'); }
            echo 'No imports scheduled';
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Found ' . count($scheduled_imports) . ' imports to run'); }
        
        // Process each scheduled import
        foreach ($scheduled_imports as $import) {
            try {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Starting import ID ' . $import['id']); }
                
                // Reset processed_products to 0 for new scheduled run and update status
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'processing',
                        'processed_products' => 0,
                        'last_run' => current_time('mysql')
                    ),
                    array('id' => $import['id']),
                    array('%s', '%d', '%s'),
                    array('%d')
                );
                
                // Run the import - process ALL products in a loop until completed
                $importer = new WC_XML_CSV_AI_Import_Importer();
                $offset = 0; // Always start from 0 for scheduled imports
                // Use import-specific batch_size if set, otherwise fall back to global chunk_size
                $chunk_size = intval($import['batch_size'] ?? $settings['chunk_size'] ?? 50);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: import[batch_size]=' . ($import['batch_size'] ?? 'NULL') . ', settings[chunk_size]=' . ($settings['chunk_size'] ?? 'NULL') . ', final chunk_size=' . $chunk_size); }
                
                // Try to set unlimited time for cron execution
                @set_time_limit(0);
                @ini_set('max_execution_time', 0);
                
                // Loop until all products are processed (no time limit for server cron)
                $completed = false;
                $max_iterations = 10000; // Safety limit to prevent infinite loops
                $iteration = 0;
                
                while (!$completed && $iteration < $max_iterations) {
                    $iteration++;
                    
                    $result = $importer->process_import_chunk($offset, $chunk_size, $import['id']);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Processed chunk offset=' . $offset . ', processed=' . ($result['processed'] ?? 0) . ', completed=' . ($result['completed'] ? 'YES' : 'NO')); }
                    
                    if ($result['completed']) {
                        $completed = true;
                    } else if (isset($result['locked']) && $result['locked']) {
                        // Another process is running, wait and retry
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Locked, waiting...'); }
                        sleep(2);
                    } else if (isset($result['stopped']) && $result['stopped']) {
                        // Import was stopped/failed
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Import stopped/failed'); }
                        break;
                    } else {
                        // Continue to next chunk
                        $offset = $result['total_processed'] ?? ($offset + $chunk_size);
                    }
                }
                
                // Update status based on result
                if ($completed) {
                    $wpdb->update(
                        $table_name,
                        array('status' => 'completed'),
                        array('id' => $import['id']),
                        array('%s'),
                        array('%d')
                    );
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Completed import ID ' . $import['id']); }
                } else {
                    // Still processing, will continue on next cron run
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Import ID ' . $import['id'] . ' paused at offset ' . $offset . ', will continue on next run'); }
                }
                
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import Cron: Error processing import ID ' . $import['id'] . ': ' . $e->getMessage()); }
                
                $wpdb->update(
                    $table_name,
                    array('status' => 'error'),
                    array('id' => $import['id']),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
        echo 'Processed ' . count($scheduled_imports) . ' imports';
        exit;
    }

    /**
     * Handle single import cron execution.
     */
    public function handle_single_import_cron() {
        global $wpdb;
        
        $import_id = intval($_GET['import_id'] ?? 0);
        $secret = sanitize_text_field($_GET['secret'] ?? '');
        
        if (empty($import_id) || empty($secret)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Missing import_id or secret'); }
            wp_die('Invalid request', 'Bad Request', array('response' => 400));
        }
        
        $stored_secret = get_option('wc_xml_csv_ai_import_secret_' . $import_id);
        if (empty($stored_secret) || $secret !== $stored_secret) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Invalid secret for import #' . $import_id); }
            wp_die('Unauthorized', 'Unauthorized', array('response' => 401));
        }
        
        $table_name = $wpdb->prefix . 'wc_itp_imports';
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $import_id), ARRAY_A);
        
        if (!$import) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Import #' . $import_id . ' not found'); }
            echo 'Import not found';
            exit;
        }
        
        if (empty($import['schedule_type']) || $import['schedule_type'] === 'none') {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Import #' . $import_id . ' has no schedule'); }
            echo 'No schedule configured';
            exit;
        }
        
        // Check if it's time to run
        $should_run = false;
        if (empty($import['last_run'])) {
            $should_run = true;
        } else {
            $last_run = strtotime($import['last_run']);
            $intervals = array('15min'=>900, 'hourly'=>3600, '6hours'=>21600, 'daily'=>86400, 'weekly'=>604800, 'monthly'=>2592000);
            $interval = $intervals[$import['schedule_type']] ?? 0;
            $should_run = (time() >= ($last_run + $interval));
        }
        
        if (!$should_run) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Import #' . $import_id . ' not ready to run yet'); }
            echo 'Not time to run yet';
            exit;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Starting import #' . $import_id); }
        
        try {
            // Check if we need to download fresh XML from URL
            if (!empty($import['original_file_url'])) {
                $old_file = $import['file_url'];
                
                // Download fresh file
                $download_result = $this->download_import_file($import['original_file_url'], $import_id);
                
                if ($download_result['success']) {
                    $new_file_path = $download_result['file_path'];
                    
                    // Delete old XML file if URL changed or new file downloaded
                    if (!empty($old_file) && $old_file !== $new_file_path && file_exists($old_file)) {
                        unlink($old_file);
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Deleted old file: ' . $old_file); }
                    }
                    
                    // Update file_url in database
                    $wpdb->update(
                        $table_name,
                        array('file_url' => $new_file_path),
                        array('id' => $import_id),
                        array('%s'),
                        array('%d')
                    );
                    
                    $import['file_url'] = $new_file_path;
                } else {
                    throw new Exception('Failed to download file: ' . ($download_result['message'] ?? 'Unknown error'));
                }
            }
            
            $wpdb->update($table_name, array('status'=>'processing', 'last_run'=>current_time('mysql')), array('id'=>$import_id), array('%s','%s'), array('%d'));
            
            $importer = new WC_XML_CSV_AI_Import_Importer();
            $result = $importer->import_batch($import_id, 0, 5);
            
            if ($result['completed']) {
                $wpdb->update($table_name, array('status'=>'completed'), array('id'=>$import_id), array('%s'), array('%d'));
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Completed import #' . $import_id); }
                echo 'Import completed successfully';
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Partial import #' . $import_id . ' - ' . $result['processed'] . '/' . $result['total']); }
                echo 'Processing: ' . $result['processed'] . '/' . $result['total'];
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Error in import #' . $import_id . ': ' . $e->getMessage()); }
            $wpdb->update($table_name, array('status'=>'error'), array('id'=>$import_id), array('%s'), array('%d'));
            echo 'Error: ' . $e->getMessage();
        }
        
        exit;
    }

    /**
     * Handle AJAX control import (pause/resume/stop/retry).
     */
    public function ajax_control_import() {
        global $wpdb;
        
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        $import_id = intval($_POST['import_id'] ?? 0);
        $action = sanitize_text_field($_POST['control_action'] ?? '');
        
        if (!$import_id || !$action) {
            wp_send_json_error('Missing parameters');
        }
        
        $table = $wpdb->prefix . 'wc_itp_imports';
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $import_id));
        
        if (!$import) {
            wp_send_json_error('Import not found');
        }
        
        switch ($action) {
            case 'pause':
                $wpdb->update($table, array('status' => 'paused'), array('id' => $import_id), array('%s'), array('%d'));
                
                // Clear cron jobs
                $hooks = array('wc_xml_csv_ai_import_process_chunk', 'wc_xml_csv_ai_import_retry_chunk', 'wc_xml_csv_ai_import_single_chunk');
                foreach ($hooks as $hook) {
                    wp_clear_scheduled_hook($hook, array($import_id));
                }
                
                // Clear transient locks to stop running batch immediately
                delete_transient('wc_import_lock_' . $import_id);
                delete_transient('wc_import_lock_time_' . $import_id);
                
                wp_send_json_success(array('status' => 'paused', 'message' => 'Import paused'));
                break;
                
            case 'resume':
                $wpdb->update($table, array('status' => 'processing'), array('id' => $import_id), array('%s'), array('%d'));
                
                // Schedule next chunk to start processing with correct parameters
                $current_offset = intval($import->processed_products);
                $batch_size = intval($import->batch_size) ?: 10;
                wp_schedule_single_event(time() + 2, 'wc_xml_csv_ai_import_process_chunk', array($import_id, $current_offset, $batch_size));
                
                wp_send_json_success(array('status' => 'processing', 'message' => 'Import resumed'));
                break;
                
            case 'stop':
                $wpdb->update($table, array('status' => 'failed'), array('id' => $import_id), array('%s'), array('%d'));
                
                // Clear ALL cron jobs
                $hooks = array('wc_xml_csv_ai_import_process_chunk', 'wc_xml_csv_ai_import_retry_chunk', 'wc_xml_csv_ai_import_single_chunk');
                foreach ($hooks as $hook) {
                    wp_clear_scheduled_hook($hook, array($import_id));
                    wp_clear_scheduled_hook($hook);
                }
                
                // Clear transient locks to stop running batch immediately
                delete_transient('wc_import_lock_' . $import_id);
                delete_transient('wc_import_lock_time_' . $import_id);
                
                wp_send_json_success(array('status' => 'failed', 'message' => 'Import stopped'));
                break;
                
            case 'retry':
                $wpdb->update($table, array('status' => 'processing'), array('id' => $import_id), array('%s'), array('%d'));
                wp_send_json_success(array('status' => 'processing', 'message' => 'Import retrying'));
                break;
                
            default:
                wp_send_json_error('Invalid action');
        }
    }

    /**
     * Handle async batch processing (for re-run button).
     */
    public function handle_process_batch() {
        global $wpdb;
        
        // CRITICAL DEBUG: Write to a separate file to ensure we see this
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents('/tmp/batch_called.log', date('Y-m-d H:i:s') . " - BATCH CALLED!\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: handle_process_batch called!'); }
        
        // CHECK KILL FLAG FIRST
        $kill_flag = dirname(__FILE__) . '/../../IMPORT_KILLED.flag';
        if (file_exists($kill_flag)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: KILL FLAG DETECTED - ABORTING BATCH!'); }
            wp_send_json_error('Import killed by emergency stop');
            exit;
        }
        
        // Check nonce for authenticated requests
        if (is_user_logged_in()) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: User is logged in, checking nonce...'); }
            check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Nonce verified!'); }
        }
        
        $import_id = intval($_POST['import_id'] ?? $_GET['import_id'] ?? 0);
        $offset = intval($_POST['offset'] ?? $_GET['offset'] ?? 0);
        $limit = intval($_POST['limit'] ?? $_GET['limit'] ?? 50);
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: import_id=' . $import_id . ', offset=' . $offset . ', limit=' . $limit); }
        
        if (empty($import_id)) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Missing import_id in process_batch'); }
            wp_send_json_error('Missing import ID');
        }
        
        // CHECK IMPORT STATUS BEFORE PROCESSING
        $table = $wpdb->prefix . 'wc_itp_imports';
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $import_id));
        
        if (!$import) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Import not found: ' . $import_id); }
            wp_send_json_error('Import not found');
        }
        
        if ($import->status !== 'processing') {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Import status is ' . $import->status . ' - ABORTING BATCH'); }
            wp_send_json_error('Import not in processing status: ' . $import->status);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Processing batch for import #' . $import_id . ', offset=' . $offset); }
        
        // DEBUG: Check if importer class exists
        if (!class_exists('WC_XML_CSV_AI_Import_Importer')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Importer class does not exist, loading...'); }
            require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-importer.php';
        }
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Importer class loaded successfully'); }
        
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Creating Importer instance...'); }
            $importer = new WC_XML_CSV_AI_Import_Importer();
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Importer instance created, calling process_import_chunk...'); }
            
            $result = $importer->process_import_chunk($offset, $limit, $import_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Batch result - processed=' . ($result['processed'] ?? 0) . ', errors=' . count($result['errors'] ?? [])); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Full result: ' . json_encode($result)); }
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: EXCEPTION in batch processing: ' . $e->getMessage()); }
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import: Exception trace: ' . $e->getTraceAsString()); }
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle WP Cron chunk processing.
     * Called by wp_schedule_single_event hook.
     *
     * @since    1.0.0
     * @param    int $import_id Import ID
     * @param    int $offset Starting offset
     * @param    int $limit Chunk size
     */
    public function handle_cron_process_chunk($import_id, $offset, $limit) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import_logs/import_debug.log';
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "\n=== CRON HANDLE CALLED ===\n", FILE_APPEND); }
        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Import ID: $import_id, Offset: $offset, Limit: $limit\n", FILE_APPEND); }
        
        try {
            // Load importer class if not loaded
            if (!class_exists('WC_XML_CSV_AI_Import_Importer')) {
                require_once WC_XML_CSV_AI_IMPORT_PLUGIN_DIR . 'includes/class-wc-xml-csv-ai-import-importer.php';
            }
            
            $importer = new WC_XML_CSV_AI_Import_Importer($import_id);
            $result = $importer->process_import_chunk($offset, $limit, $import_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "Chunk completed: " . json_encode($result) . "\n", FILE_APPEND); }
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($log_file, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND); }
        }
    }

    /**
     * Display logs viewer page.
     *
     * @since    1.0.0
     */
    public function display_logs_page() {
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-xml-csv-import'));
        }
        
        // Check if PRO feature is available
        if (!WC_XML_CSV_AI_Import_Features::is_available('detailed_logs')) {
            $this->display_logs_pro_upsell();
            return;
        }
        
        global $wpdb;
        ?>
        <div class="wrap wc-xml-csv-import">
            <h1><?php echo esc_html__('Import Logs', 'wc-xml-csv-import'); ?></h1>
            
            <?php
            // Get imports for dropdown
            $table = $wpdb->prefix . 'wc_itp_imports';
            $imports = $wpdb->get_results("SELECT id, name FROM {$table} ORDER BY id DESC LIMIT 20");
            
            // Handle import selection
            $selected_import = isset($_GET['import_id']) ? intval($_GET['import_id']) : ($imports[0]->id ?? 0);
            
            // Get logs from database
            $logs_table = $wpdb->prefix . 'wc_itp_import_logs';
            $logs = array();
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$logs_table}'") == $logs_table && $selected_import > 0) {
                $logs = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$logs_table} WHERE import_id = %d ORDER BY created_at DESC LIMIT 1000",
                    $selected_import
                ));
            }
            
            // Also show import_debug.log file
            $upload_dir = wp_upload_dir();
            $debug_log = $upload_dir['basedir'] . '/woo_xml_csv_ai_smart_import_logs/import_debug.log';
            $show_file_log = isset($_GET['view']) && $_GET['view'] === 'file';
            ?>
            
            <div class="log-viewer-controls" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
                <form method="get" style="display: inline-block; margin-right: 20px;">
                    <input type="hidden" name="page" value="wc-xml-csv-import-logs">
                    
                    <label for="view-select"><?php _e('View:', 'wc-xml-csv-import'); ?></label>
                    <select name="view" id="view-select" onchange="this.form.submit()" style="margin-right: 20px;">
                        <option value="database" <?php selected(!$show_file_log); ?>>Database Logs</option>
                        <option value="file" <?php selected($show_file_log); ?>>Debug File (import_debug.log)</option>
                    </select>
                    
                    <?php if (!$show_file_log && !empty($imports)): ?>
                        <label for="import-select"><?php _e('Import:', 'wc-xml-csv-import'); ?></label>
                        <select name="import_id" id="import-select" onchange="this.form.submit()">
                            <?php foreach ($imports as $import): ?>
                                <option value="<?php echo esc_attr($import->id); ?>" <?php selected($selected_import, $import->id); ?>>
                                    #<?php echo $import->id; ?> - <?php echo esc_html($import->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </form>
                
                <button type="button" class="button" onclick="location.reload()">
                    <?php _e('Refresh', 'wc-xml-csv-import'); ?>
                </button>
                
                <label style="margin-left: 20px;">
                    <input type="checkbox" id="auto-refresh" onchange="toggleAutoRefresh(this.checked)">
                    <?php _e('Auto-refresh (5s)', 'wc-xml-csv-import'); ?>
                </label>
                
                <?php if ($show_file_log && file_exists($debug_log)): ?>
                    <a href="?page=wc-xml-csv-import-logs&view=file&action=clear" class="button" onclick="return confirm('<?php _e('Clear debug log file?', 'wc-xml-csv-import'); ?>')" style="margin-left: 10px;">
                        <?php _e('Clear Log', 'wc-xml-csv-import'); ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="log-viewer-content" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; max-height: 600px; overflow-y: auto;">
                <?php
                if ($show_file_log) {
                    // Show import_debug.log file
                    if (isset($_GET['action']) && $_GET['action'] === 'clear' && file_exists($debug_log)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents($debug_log, ''); }
                        echo '<div class="notice notice-success"><p>' . __('Debug log cleared.', 'wc-xml-csv-import') . '</p></div>';
                    }
                    
                    if (file_exists($debug_log)) {
                        $log_content = file_get_contents($debug_log);
                        if (empty($log_content)) {
                            echo '<div style="color: #888;">' . __('Log file is empty.', 'wc-xml-csv-import') . '</div>';
                        } else {
                            // Show last 500 lines
                            $lines = explode("\n", $log_content);
                            $lines = array_slice($lines, -500);
                            $log_content = implode("\n", $lines);
                            
                            $log_content = htmlspecialchars($log_content);
                            $log_content = preg_replace('/\[([\d\-: .]+)\]/', '<span style="color: #9e9e9e;">[$1]</span>', $log_content);
                            $log_content = preg_replace('/(ERROR|CRITICAL|CATEGORY_ERROR)/', '<span style="color: #f44336; font-weight: bold;">$1</span>', $log_content);
                            $log_content = preg_replace('/(WARNING)/', '<span style="color: #ff9800; font-weight: bold;">$1</span>', $log_content);
                            $log_content = preg_replace('/(CATEGORY_CREATED|CATEGORY_EXISTS|TAGS_ASSIGNED)/', '<span style="color: #4caf50; font-weight: bold;">$1</span>', $log_content);
                            $log_content = preg_replace('/(CATEGORY_HIERARCHY|CREATE_CATEGORY_HIERARCHY)/', '<span style="color: #2196f3; font-weight: bold;">$1</span>', $log_content);
                            
                            echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">' . $log_content . '</pre>';
                        }
                    } else {
                        echo '<div style="color: #f44336;">' . __('Debug log file not found.', 'wc-xml-csv-import') . '</div>';
                    }
                } else {
                    // Show database logs
                    if (empty($logs)) {
                        echo '<div style="color: #888;">' . __('No logs found for this import.', 'wc-xml-csv-import') . '</div>';
                    } else {
                        foreach (array_reverse($logs) as $log) {
                            $level_color = array(
                                'error' => '#f44336',
                                'warning' => '#ff9800',
                                'info' => '#4caf50',
                                'debug' => '#2196f3'
                            )[$log->level] ?? '#d4d4d4';
                            
                            echo '<div style="margin-bottom: 10px; border-left: 3px solid ' . $level_color . '; padding-left: 10px;">';
                            echo '<span style="color: #9e9e9e;">[' . esc_html($log->created_at) . ']</span> ';
                            echo '<span style="color: ' . $level_color . '; font-weight: bold;">' . strtoupper(esc_html($log->level)) . '</span> ';
                            echo '<span>' . esc_html($log->message) . '</span>';
                            if (!empty($log->context)) {
                                echo '<div style="color: #888; font-size: 11px; margin-top: 5px;">' . esc_html($log->context) . '</div>';
                            }
                            echo '</div>';
                        }
                    }
                }
                ?>
            </div>
            </div>
            
            <script>
            var autoRefreshInterval = null;
            
            function toggleAutoRefresh(enabled) {
                // Save state to localStorage
                localStorage.setItem('wc_import_logs_auto_refresh', enabled ? '1' : '0');
                
                if (enabled) {
                    autoRefreshInterval = setInterval(function() {
                        location.reload();
                    }, 5000);
                } else {
                    if (autoRefreshInterval) {
                        clearInterval(autoRefreshInterval);
                        autoRefreshInterval = null;
                    }
                }
            }
            
            // Auto-scroll to bottom on page load
            document.addEventListener('DOMContentLoaded', function() {
                var logContent = document.querySelector('.log-viewer-content');
                if (logContent) {
                    logContent.scrollTop = logContent.scrollHeight;
                }
                
                // Restore auto-refresh state from localStorage
                var autoRefreshEnabled = localStorage.getItem('wc_import_logs_auto_refresh') === '1';
                var checkbox = document.getElementById('auto-refresh');
                if (checkbox) {
                    checkbox.checked = autoRefreshEnabled;
                    if (autoRefreshEnabled) {
                        toggleAutoRefresh(true);
                    }
                }
            });
            </script>
        </div>
        <?php
    }

    /**
     * Display PRO upsell page for Logs feature.
     *
     * @since    1.0.0
     */
    private function display_logs_pro_upsell() {
        $upgrade_url = WC_XML_CSV_AI_Import_License::get_upgrade_url();
        ?>
        <div class="wrap wc-xml-csv-import">
            <h1><?php echo esc_html__('Import Logs', 'wc-xml-csv-import'); ?></h1>
            
            <div style="max-width: 700px; margin: 40px auto; text-align: center;">
                <!-- PRO Feature Card -->
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 50px 40px; color: white; box-shadow: 0 20px 60px rgba(102, 126, 234, 0.4);">
                    
                    <div style="font-size: 64px; margin-bottom: 20px;">📊</div>
                    
                    <h2 style="font-size: 28px; margin: 0 0 15px 0; color: white;">
                        <?php _e('Import Logs & Debugging', 'wc-xml-csv-import'); ?>
                    </h2>
                    
                    <p style="font-size: 16px; opacity: 0.9; margin-bottom: 30px; line-height: 1.6;">
                        <?php _e('Track every import in detail. Monitor progress, debug issues, and ensure data quality with comprehensive logging.', 'wc-xml-csv-import'); ?>
                    </p>
                    
                    <!-- Features List -->
                    <div style="text-align: left; background: rgba(255,255,255,0.15); border-radius: 12px; padding: 25px 30px; margin-bottom: 30px;">
                        <div style="display: grid; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: rgba(255,255,255,0.2); padding: 6px 10px; border-radius: 6px;">✅</span>
                                <span><?php _e('Real-time import progress tracking', 'wc-xml-csv-import'); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: rgba(255,255,255,0.2); padding: 6px 10px; border-radius: 6px;">✅</span>
                                <span><?php _e('Detailed error messages with context', 'wc-xml-csv-import'); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: rgba(255,255,255,0.2); padding: 6px 10px; border-radius: 6px;">✅</span>
                                <span><?php _e('Debug file viewer for troubleshooting', 'wc-xml-csv-import'); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: rgba(255,255,255,0.2); padding: 6px 10px; border-radius: 6px;">✅</span>
                                <span><?php _e('Filter logs by import and log level', 'wc-xml-csv-import'); ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="background: rgba(255,255,255,0.2); padding: 6px 10px; border-radius: 6px;">✅</span>
                                <span><?php _e('Auto-refresh for live monitoring', 'wc-xml-csv-import'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upgrade Button -->
                    <a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" 
                       style="display: inline-block; background: white; color: #667eea; padding: 16px 40px; border-radius: 8px; font-weight: 600; font-size: 16px; text-decoration: none; transition: transform 0.2s, box-shadow 0.2s;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.2)';"
                       onmouseout="this.style.transform=''; this.style.boxShadow='';">
                        <?php _e('Upgrade to PRO', 'wc-xml-csv-import'); ?> →
                    </a>
                    
                    <p style="margin-top: 20px; font-size: 13px; opacity: 0.7;">
                        <?php _e('Unlock all PRO features including scheduled imports, advanced filters, and AI processing.', 'wc-xml-csv-import'); ?>
                    </p>
                </div>
                
                <!-- Back Link -->
                <p style="margin-top: 25px;">
                    <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import-history'); ?>" style="color: #666; text-decoration: none;">
                        ← <?php _e('Back to Import History', 'wc-xml-csv-import'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Handle update import URL AJAX request.
     *
     * @since    1.0.0
     */
    public function handle_update_import_url() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'wc-xml-csv-import')));
            return;
        }
        
        $import_id = intval($_POST['import_id'] ?? 0);
        $new_url = esc_url_raw($_POST['file_url'] ?? '');
        
        if (!$import_id || !$new_url) {
            wp_send_json_error(array('message' => __('Invalid import ID or URL.', 'wc-xml-csv-import')));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_xml_csv_ai_imports';
        
        // Get current import to find old file
        $import = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $import_id
        ), ARRAY_A);
        
        if (!$import) {
            wp_send_json_error(array('message' => __('Import not found.', 'wc-xml-csv-import')));
            return;
        }
        
        // Delete old XML file if it exists and URL is changing
        if (!empty($import['file_url']) && $import['file_url'] !== $new_url) {
            if (file_exists($import['file_url'])) {
                unlink($import['file_url']);
            }
        }
        
        // Update URL in database
        $updated = $wpdb->update(
            $table_name,
            array('original_file_url' => $new_url),
            array('id' => $import_id),
            array('%s'),
            array('%d')
        );
        
        if ($updated === false) {
            wp_send_json_error(array('message' => __('Failed to update URL in database.', 'wc-xml-csv-import')));
            return;
        }
        
        wp_send_json_success(array('message' => __('URL updated successfully. Next import will use the new URL.', 'wc-xml-csv-import')));
    }

    /**
     * Download import file from URL for cron jobs.
     *
     * @since    1.0.0
     * @param    string $url File URL
     * @param    int $import_id Import ID
     * @return   array Result with success status and file_path or message
     */
    private function download_import_file($url, $import_id) {
        $upload_dir = wp_upload_dir();
        $basedir = str_replace('/Var/', '/var/', $upload_dir['basedir']);
        $plugin_upload_dir = $basedir . '/woo_xml_csv_ai_smart_import/';
        
        if (!is_dir($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);
        }
        
        $base_filename = sanitize_file_name(basename(parse_url($url, PHP_URL_PATH)));
        if (empty($base_filename)) {
            $base_filename = 'import_' . $import_id;
        }
        
        $file_path = $plugin_upload_dir . time() . '_' . $base_filename;
        
        // Download with streaming
        $temp_file = fopen($file_path, 'w');
        if (!$temp_file) {
            return array('success' => false, 'message' => 'Failed to create temporary file');
        }
        
        $response = wp_remote_get($url, array(
            'timeout' => 600,
            'sslverify' => false,
            'stream' => true,
            'filename' => $file_path
        ));
        
        fclose($temp_file);
        
        if (is_wp_error($response)) {
            if (file_exists($file_path)) unlink($file_path);
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            if (file_exists($file_path)) unlink($file_path);
            return array('success' => false, 'message' => 'HTTP Status: ' . $response_code);
        }
        
        if (!file_exists($file_path) || filesize($file_path) === 0) {
            return array('success' => false, 'message' => 'Downloaded file is empty');
        }
        
        return array('success' => true, 'file_path' => $file_path);
    }
    
    /**
     * AJAX handler to detect attribute values from source field.
     */
    public function ajax_detect_attribute_values() {
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        $import_id = intval($_POST['import_id'] ?? 0);
        $source_field = sanitize_text_field($_POST['source_field'] ?? '');
        
        if (!$import_id || !$source_field) {
            wp_send_json_error(array('message' => 'Missing parameters'));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wc_itp_imports';
        $import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $import_id));
        
        if (!$import) {
            wp_send_json_error(array('message' => 'Import not found'));
        }
        
        $file_path = $import->file_path;
        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => 'Import file not found'));
        }
        
        // Parse file to extract unique values from source field
        $values = array();
        
        try {
            if ($import->file_type === 'xml') {
                $xml_parser = new WC_XML_CSV_AI_Import_XML_Parser();
                $products = $xml_parser->parse($file_path, $import->product_wrapper);
                
                // Extract values from source field
                foreach ($products as $product) {
                    $value = $this->get_nested_value($product, $source_field);
                    if (!empty($value)) {
                        if (is_array($value)) {
                            // If array of values (e.g., multiple attributes)
                            foreach ($value as $v) {
                                if (is_array($v) && isset($v['value'])) {
                                    $values[] = $v['value'];
                                } else {
                                    $values[] = (string)$v;
                                }
                            }
                        } else {
                            $values[] = (string)$value;
                        }
                    }
                }
            } else {
                // CSV parsing
                $csv_parser = new WC_XML_CSV_AI_Import_CSV_Parser();
                $products = $csv_parser->parse($file_path);
                
                foreach ($products as $product) {
                    if (isset($product[$source_field]) && !empty($product[$source_field])) {
                        // Check if comma-separated
                        if (strpos($product[$source_field], ',') !== false) {
                            $split_values = array_map('trim', explode(',', $product[$source_field]));
                            $values = array_merge($values, $split_values);
                        } else {
                            $values[] = $product[$source_field];
                        }
                    }
                }
            }
            
            // Get unique values and limit to first 10 for UI
            $values = array_unique($values);
            $values = array_filter($values); // Remove empty
            $values = array_values($values); // Re-index
            
            // Limit to 20 values max for UI performance
            if (count($values) > 20) {
                $values = array_slice($values, 0, 20);
            }
            
            if (empty($values)) {
                wp_send_json_error(array('message' => 'No values found in source field: ' . $source_field));
            }
            
            wp_send_json_success(array('values' => $values));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error parsing file: ' . $e->getMessage()));
        }
    }
    
    /**
     * Get nested value from array using dot notation.
     */
    private function get_nested_value($array, $path) {
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
     * AJAX: Save mapping recipe
     */
    public function ajax_save_recipe() {
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wc-xml-csv-import')));
        }
        
        $recipe_name = sanitize_text_field($_POST['recipe_name'] ?? '');
        $mapping_data = isset($_POST['mapping_data']) ? $_POST['mapping_data'] : array();
        $existing_recipe_id = sanitize_text_field($_POST['recipe_id'] ?? '');
        
        if (empty($recipe_name)) {
            wp_send_json_error(array('message' => __('Recipe name is required', 'wc-xml-csv-import')));
        }
        
        // Get existing recipes
        $recipes = get_option('wc_xml_csv_ai_import_recipes', array());
        
        // Check if updating existing recipe (by ID) or by name match
        $recipe_id = null;
        $is_update = false;
        
        // First check if recipe_id was provided (loaded recipe)
        if (!empty($existing_recipe_id) && isset($recipes[$existing_recipe_id])) {
            $recipe_id = $existing_recipe_id;
            $is_update = true;
        } else {
            // Check if a recipe with this name already exists
            foreach ($recipes as $id => $recipe) {
                if (strtolower($recipe['name']) === strtolower($recipe_name)) {
                    $recipe_id = $id;
                    $is_update = true;
                    break;
                }
            }
        }
        
        // If no existing recipe found, create new ID
        if (!$recipe_id) {
            $recipe_id = sanitize_title($recipe_name) . '_' . time();
        }
        
        // Save/update recipe
        $recipes[$recipe_id] = array(
            'name' => $recipe_name,
            'mapping_data' => $mapping_data,
            'created_at' => $is_update && isset($recipes[$recipe_id]['created_at']) 
                ? $recipes[$recipe_id]['created_at'] 
                : current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        update_option('wc_xml_csv_ai_import_recipes', $recipes);
        
        $message = $is_update 
            ? __('Recipe updated successfully', 'wc-xml-csv-import')
            : __('Recipe saved successfully', 'wc-xml-csv-import');
        
        wp_send_json_success(array(
            'message' => $message,
            'recipe_id' => $recipe_id,
            'is_update' => $is_update,
            'recipes' => $this->get_recipes_list()
        ));
    }

    /**
     * AJAX: Load recipe
     */
    public function ajax_load_recipe() {
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        $recipe_id = sanitize_text_field($_POST['recipe_id'] ?? '');
        
        if (empty($recipe_id)) {
            wp_send_json_error(array('message' => __('Recipe ID is required', 'wc-xml-csv-import')));
        }
        
        $recipes = get_option('wc_xml_csv_ai_import_recipes', array());
        
        if (!isset($recipes[$recipe_id])) {
            wp_send_json_error(array('message' => __('Recipe not found', 'wc-xml-csv-import')));
        }
        
        wp_send_json_success(array(
            'recipe' => $recipes[$recipe_id],
            'message' => __('Recipe loaded successfully', 'wc-xml-csv-import')
        ));
    }

    /**
     * AJAX: Delete recipe
     */
    public function ajax_delete_recipe() {
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'wc-xml-csv-import')));
        }
        
        $recipe_id = sanitize_text_field($_POST['recipe_id'] ?? '');
        
        if (empty($recipe_id)) {
            wp_send_json_error(array('message' => __('Recipe ID is required', 'wc-xml-csv-import')));
        }
        
        $recipes = get_option('wc_xml_csv_ai_import_recipes', array());
        
        if (isset($recipes[$recipe_id])) {
            unset($recipes[$recipe_id]);
            update_option('wc_xml_csv_ai_import_recipes', $recipes);
        }
        
        wp_send_json_success(array(
            'message' => __('Recipe deleted successfully', 'wc-xml-csv-import'),
            'recipes' => $this->get_recipes_list()
        ));
    }

    /**
     * AJAX: Get recipes list
     */
    public function ajax_get_recipes() {
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        wp_send_json_success(array(
            'recipes' => $this->get_recipes_list()
        ));
    }

    /**
     * Helper: Get recipes list for dropdown
     */
    private function get_recipes_list() {
        $recipes = get_option('wc_xml_csv_ai_import_recipes', array());
        $list = array();
        
        foreach ($recipes as $id => $recipe) {
            $list[] = array(
                'id' => $id,
                'name' => $recipe['name'],
                'created_at' => $recipe['created_at']
            );
        }
        
        // Sort by created_at descending (newest first)
        usort($list, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $list;
    }

    /**
     * AJAX: Auto-detect mapping based on field name matching
     */
    public function ajax_auto_detect_mapping() {
        check_ajax_referer('wc_xml_csv_ai_import_nonce', 'nonce');
        
        $source_fields = isset($_POST['source_fields']) ? array_map('sanitize_text_field', $_POST['source_fields']) : array();
        
        if (empty($source_fields)) {
            wp_send_json_error(array('message' => __('No source fields provided', 'wc-xml-csv-import')));
        }
        
        // WooCommerce field aliases for matching
        $field_aliases = array(
            'sku' => array('sku', 'product_code', 'item_code', 'article', 'artikuls', 'product_sku', 'code', 'item_sku', 'articlecode', 'itemcode', 'productcode', 'id', 'product_id'),
            'name' => array('name', 'title', 'product_name', 'product_title', 'item_name', 'nosaukums', 'productname', 'item', 'productdescription'),
            'description' => array('description', 'desc', 'content', 'product_description', 'full_description', 'apraksts', 'long_description', 'longdescription', 'fulldescription'),
            'short_description' => array('short_description', 'short_desc', 'excerpt', 'summary', 'shortdescription', 'brief', 'intro'),
            'regular_price' => array('price', 'regular_price', 'cena', 'retail_price', 'list_price', 'msrp', 'regularprice', 'listprice', 'baseprice', 'base_price', 'unit_price'),
            'sale_price' => array('sale_price', 'special_price', 'discount_price', 'saleprice', 'discountprice', 'offer_price'),
            'stock_quantity' => array('stock', 'quantity', 'qty', 'stock_quantity', 'inventory', 'daudzums', 'stockqty', 'stock_qty', 'available', 'count', 'amount'),
            'weight' => array('weight', 'svars', 'mass', 'wt', 'productweight', 'product_weight'),
            'length' => array('length', 'garums', 'len', 'productlength'),
            'width' => array('width', 'platums', 'wid', 'productwidth'),
            'height' => array('height', 'augstums', 'hgt', 'productheight'),
            'categories' => array('category', 'categories', 'kategorija', 'cat', 'product_category', 'produkta_kategorija', 'categorypath', 'category_path'),
            'tags' => array('tags', 'tag', 'birkas', 'keywords', 'product_tags'),
            'images' => array('images', 'image', 'attels', 'picture', 'photo', 'img', 'product_image', 'gallery', 'image_url', 'imageurl', 'picture_url', 'pictureurl', 'photos'),
            'featured_image' => array('featured_image', 'main_image', 'primary_image', 'featuredimage', 'mainimage', 'primaryimage', 'thumbnail'),
            'brand' => array('brand', 'manufacturer', 'razotajs', 'make', 'producer', 'vendor'),
            'ean' => array('ean', 'ean13', 'ean_code', 'barcode', 'gtin13'),
            'upc' => array('upc', 'upc_code', 'gtin12'),
            'isbn' => array('isbn', 'isbn13', 'isbn10'),
            'mpn' => array('mpn', 'manufacturer_part_number', 'part_number', 'partnumber'),
            'gtin' => array('gtin', 'gtin14', 'global_trade_item_number'),
            'status' => array('status', 'product_status', 'availability', 'state', 'active'),
            'manage_stock' => array('manage_stock', 'managestock', 'track_stock', 'trackstock'),
            'stock_status' => array('stock_status', 'stockstatus', 'availability_status', 'in_stock', 'instock'),
            'backorders' => array('backorders', 'backorder', 'allow_backorder'),
            'tax_status' => array('tax_status', 'taxstatus', 'taxable'),
            'tax_class' => array('tax_class', 'taxclass', 'tax_rate', 'vat_class'),
            'featured' => array('featured', 'is_featured', 'highlight', 'recommended'),
            'virtual' => array('virtual', 'is_virtual', 'digital'),
            'downloadable' => array('downloadable', 'is_downloadable', 'download'),
            'sold_individually' => array('sold_individually', 'soldindividually', 'single_only'),
            'reviews_allowed' => array('reviews_allowed', 'reviewsallowed', 'enable_reviews', 'allow_reviews'),
            'purchase_note' => array('purchase_note', 'purchasenote', 'order_note'),
            'menu_order' => array('menu_order', 'menuorder', 'sort_order', 'position'),
            'external_url' => array('external_url', 'externalurl', 'affiliate_link', 'product_url'),
            'meta_title' => array('meta_title', 'metatitle', 'seo_title', 'page_title'),
            'meta_description' => array('meta_description', 'metadescription', 'seo_description'),
            'meta_keywords' => array('meta_keywords', 'metakeywords', 'seo_keywords', 'keywords', 'tags_seo', 'search_keywords'),
            'shipping_class' => array('shipping_class', 'shippingclass', 'delivery_class'),
            'upsell_ids' => array('upsell_ids', 'upsell', 'upsells', 'upsell_products', 'upsell_product_ids', 'related_upsell'),
            'cross_sell_ids' => array('cross_sell_ids', 'cross_sell', 'crosssell', 'cross_sells', 'crosssells', 'cross_sell_products'),
            'grouped_products' => array('grouped_products', 'grouped', 'group_products', 'product_group', 'grouped_product_ids'),
            'parent_id' => array('parent_id', 'parent', 'parent_product', 'parent_sku', 'parent_product_id'),
        );
        
        $suggestions = array();
        $matched_woo_fields = array(); // Track already matched WooCommerce fields
        
        // First pass: exact matches
        foreach ($source_fields as $source_field) {
            $source_lower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $source_field));
            
            foreach ($field_aliases as $woo_field => $aliases) {
                if (isset($matched_woo_fields[$woo_field])) {
                    continue; // Skip already matched fields
                }
                
                foreach ($aliases as $alias) {
                    $alias_clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                    
                    if ($source_lower === $alias_clean) {
                        $suggestions[$woo_field] = array(
                            'source_field' => $source_field,
                            'confidence' => 100,
                            'match_type' => 'exact'
                        );
                        $matched_woo_fields[$woo_field] = true;
                        break 2;
                    }
                }
            }
        }
        
        // Second pass: partial matches (contains)
        foreach ($source_fields as $source_field) {
            $source_lower = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $source_field));
            
            foreach ($field_aliases as $woo_field => $aliases) {
                if (isset($matched_woo_fields[$woo_field])) {
                    continue;
                }
                
                foreach ($aliases as $alias) {
                    $alias_clean = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $alias));
                    
                    // Check if source contains alias or alias contains source
                    if (strlen($alias_clean) >= 3 && (
                        strpos($source_lower, $alias_clean) !== false || 
                        strpos($alias_clean, $source_lower) !== false
                    )) {
                        // Calculate confidence based on match quality
                        $confidence = 70;
                        if (strpos($source_lower, $alias_clean) === 0 || strpos($alias_clean, $source_lower) === 0) {
                            $confidence = 85; // Starts with match is better
                        }
                        
                        if (!isset($suggestions[$woo_field]) || $suggestions[$woo_field]['confidence'] < $confidence) {
                            $suggestions[$woo_field] = array(
                                'source_field' => $source_field,
                                'confidence' => $confidence,
                                'match_type' => 'partial'
                            );
                            $matched_woo_fields[$woo_field] = true;
                        }
                        break;
                    }
                }
            }
        }
        
        // Sort suggestions by confidence
        uasort($suggestions, function($a, $b) {
            return $b['confidence'] - $a['confidence'];
        });
        
        $total_fields = count($field_aliases);
        $matched_fields = count($suggestions);
        
        wp_send_json_success(array(
            'suggestions' => $suggestions,
            'matched_count' => $matched_fields,
            'total_fields' => $total_fields,
            'message' => sprintf(
                __('Auto-detected %d of %d fields', 'wc-xml-csv-import'),
                $matched_fields,
                $total_fields
            )
        ));
    }
    
    /**
     * AJAX handler to get products count for an import
     */
    public function ajax_get_products_count() {
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count called - POST: ' . print_r($_POST, true)); }
        
        // Verify nonce - use false to return false instead of die()
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wc_xml_csv_ai_import_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - nonce failed'); }
            wp_send_json_error(array('message' => __('Security check failed', 'wc-xml-csv-import')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - permission denied'); }
            wp_send_json_error(array('message' => __('Permission denied', 'wc-xml-csv-import')));
            return;
        }
        
        $import_id = isset($_POST['import_id']) ? intval($_POST['import_id']) : 0;
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - import_id: ' . $import_id); }
        
        if (!$import_id) {
            wp_send_json_error(array('message' => __('Invalid import ID', 'wc-xml-csv-import')));
            return;
        }
        
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wc_import_id' AND meta_value = %d",
            $import_id
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_get_products_count - count: ' . $count); }
        
        wp_send_json_success(array(
            'count' => intval($count),
            'import_id' => $import_id
        ));
    }
    
    /**
     * AJAX handler to delete products in batches with progress
     */
    public function ajax_delete_products_batch() {
        // Enable error handling to catch fatal errors
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch STARTED'); }
            
            // Verify nonce - use global nonce for consistency
            $import_id = isset($_POST['import_id']) ? intval($_POST['import_id']) : 0;
            
            if (!$import_id) {
                wp_send_json_error(array('message' => __('Invalid import ID', 'wc-xml-csv-import')));
                return;
            }
            
            // Try global nonce first, then import-specific
            $nonce = $_POST['nonce'] ?? '';
            $valid_nonce = wp_verify_nonce($nonce, 'wc_xml_csv_ai_import_nonce') || 
                           wp_verify_nonce($nonce, 'delete_products_' . $import_id);
            
            if (!$valid_nonce) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch - nonce failed'); }
                wp_send_json_error(array('message' => __('Security check failed', 'wc-xml-csv-import')));
                return;
            }
            
            if (!current_user_can('manage_woocommerce')) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch - permission denied'); }
                wp_send_json_error(array('message' => __('Permission denied', 'wc-xml-csv-import')));
                return;
            }
        
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        
        // Increase time limit for deletion
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
        
        global $wpdb;
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Starting for import_id=' . $import_id . ', batch_size=' . $batch_size); }
        
        // Get batch of product IDs
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wc_import_id' AND meta_value = %d LIMIT %d",
            $import_id,
            $batch_size
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Found ' . count($product_ids) . ' products to delete'); }
        
        $deleted_count = 0;
        foreach ($product_ids as $product_id) {
            try {
                if (wp_delete_post($product_id, true)) {
                    $deleted_count++;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Error deleting product ' . $product_id . ': ' . $e->getMessage()); }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('DELETE_PRODUCTS_BATCH: Deleted ' . $deleted_count . ' products'); }
        
        // Get remaining count
        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}postmeta WHERE meta_key = '_wc_import_id' AND meta_value = %d",
            $import_id
        ));
        
        $completed = ($remaining == 0);
        
        // If completed, update import's processed_products count to 0
        if ($completed) {
            $wpdb->update(
                $wpdb->prefix . 'wc_itp_imports',
                array('processed_products' => 0),
                array('id' => $import_id),
                array('%d'),
                array('%d')
            );
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch SUCCESS: deleted=' . $deleted_count . ', remaining=' . $remaining); }
        
        wp_send_json_success(array(
            'deleted' => $deleted_count,
            'remaining' => intval($remaining),
            'completed' => $completed,
            'message' => $completed 
                ? __('All products deleted successfully', 'wc-xml-csv-import')
                : sprintf(__('Deleted %d products, %d remaining...', 'wc-xml-csv-import'), $deleted_count, $remaining)
        ));
        
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch EXCEPTION: ' . $e->getMessage()); }
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        } catch (Error $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('ajax_delete_products_batch ERROR: ' . $e->getMessage()); }
            wp_send_json_error(array('message' => 'Fatal Error: ' . $e->getMessage()));
        }
    }
}
