<?php
/**
 * Import Edit/View Page
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// TEMP DEBUG: Log what we have
error_log('=== IMPORT-EDIT DEBUG ===');
error_log('saved_custom_fields exists: ' . (isset($saved_custom_fields) ? 'YES' : 'NO'));
error_log('saved_custom_fields type: ' . gettype($saved_custom_fields ?? null));
error_log('saved_custom_fields count: ' . (isset($saved_custom_fields) && is_array($saved_custom_fields) ? count($saved_custom_fields) : 'N/A'));
if (isset($saved_custom_fields) && is_array($saved_custom_fields) && !empty($saved_custom_fields)) {
    error_log('saved_custom_fields content: ' . print_r($saved_custom_fields, true));
}

// Debug: Check if form submitted
if (defined('WP_DEBUG') && WP_DEBUG) { error_log('=== IMPORT-EDIT.PHP LOADED ==='); }
if (defined('WP_DEBUG') && WP_DEBUG) { error_log('Request method: ' . $_SERVER['REQUEST_METHOD']); }
if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST empty: ' . (empty($_POST) ? 'YES' : 'NO')); }
if (!empty($_POST)) {
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('POST keys: ' . implode(', ', array_keys($_POST))); }
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('update_import present: ' . (isset($_POST['update_import']) ? 'YES' : 'NO')); }
}

// Ensure file_path is set for AJAX - use latest XML if missing
if (empty($import['file_url']) || !file_exists($import['file_url'])) {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/wc-xml-csv-import/';
    if (is_dir($plugin_upload_dir)) {
        $files = glob($plugin_upload_dir . '*.xml');
        if ($files && count($files) > 0) {
            // Use the most recently modified XML file
            usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
            $import['file_url'] = $files[0];
        }
    }
}

// For backwards compatibility, also set file_path
$import['file_path'] = $import['file_url'] ?? '';
$import['file_type'] = $import['file_type'] ?? 'xml';
$import['product_wrapper'] = $import['product_wrapper'] ?? 'product';
$import['schedule_type'] = $import['schedule_type'] ?? 'disabled';
$import['update_existing'] = $import['update_existing'] ?? '0';

// Load file_fields from file structure for filter dropdowns
$file_fields = array();
$debug_file_path = $import['file_path'] ?? 'NOT SET';
$debug_file_exists = (!empty($import['file_path']) && file_exists($import['file_path'])) ? 'YES' : 'NO';

if (!empty($import['file_path']) && file_exists($import['file_path'])) {
    try {
        if ($import['file_type'] === 'xml') {
            $xml_parser = new WC_XML_CSV_AI_Import_XML_Parser();
            $parsed = $xml_parser->parse_structure($import['file_path'], $import['product_wrapper'] ?? 'product');
            // parse_structure returns 'structure' key, not 'sample_fields'
            $structure_data = $parsed['structure'] ?? array();
            // Extract field paths from structure
            foreach ($structure_data as $field_info) {
                if (isset($field_info['path'])) {
                    $file_fields[] = $field_info['path'];
                }
            }
        }
    } catch (Exception $e) {
        $debug_error = $e->getMessage();
    }
}
?>
<!-- DEBUG: file_path=<?php echo esc_html($debug_file_path); ?>, file_exists=<?php echo esc_html($debug_file_exists); ?>, fields_count=<?php echo esc_html(count($file_fields)); ?>, first_5=<?php echo esc_html(implode(', ', array_slice($file_fields, 0, 5))); ?> -->
<div class="wrap wc-ai-import-step wc-ai-import-step-2">
    <h1><?php echo esc_html__('Import Details:', 'bootflow-product-importer') . ' ' . esc_html($import['name']); ?></h1>
    
    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-xml-csv-import-history')); ?>" class="button">
            ⬅️ <?php esc_html_e('Back to Import History', 'bootflow-product-importer'); ?>
        </a>
    </p>
    
    <div class="wc-ai-import-card">
        <h2><?php esc_html_e('Import Information', 'bootflow-product-importer'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Name', 'bootflow-product-importer'); ?></th>
                <td><strong><?php echo esc_html($import['name']); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('File Type', 'bootflow-product-importer'); ?></th>
                <td><?php echo esc_html(strtoupper($import['file_type'])); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Status', 'bootflow-product-importer'); ?></th>
                <td><strong><?php echo esc_html(ucfirst($import['status'])); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Products', 'bootflow-product-importer'); ?></th>
                <td><?php echo esc_html($import['processed_products'] . '/' . $import['total_products']); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Created', 'bootflow-product-importer'); ?></th>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import['created_at']))); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Last Run', 'bootflow-product-importer'); ?></th>
                <td><?php echo $import['last_run'] ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import['last_run']))) : esc_html__('Never', 'bootflow-product-importer'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Batch Size', 'bootflow-product-importer'); ?></th>
                <td><strong><?php echo intval($import['batch_size'] ?? 50); ?></strong> <?php esc_html_e('products per chunk', 'bootflow-product-importer'); ?></td>
            </tr>
            <?php if (!empty($import['original_file_url'])): ?>
            <tr>
                <th><?php esc_html_e('File URL', 'bootflow-product-importer'); ?></th>
                <td>
                    <input type="text" id="import_file_url" name="import_file_url" value="<?php echo esc_attr($import['original_file_url']); ?>" class="regular-text" style="width: 500px;">
                    <button type="button" id="update-file-url-btn" class="button"><?php esc_html_e('Update URL', 'bootflow-product-importer'); ?></button>
                    <span id="url-update-status"></span>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <form method="post" id="import-edit-form" action="">
        
    <!-- Import Behavior (moved INSIDE form to fix save issue) -->
    <div class="wc-ai-import-card" style="margin-top: 20px;">
        <h2>⚙️ <?php esc_html_e('Import Behavior', 'bootflow-product-importer'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Update Existing Products', 'bootflow-product-importer'); ?></th>
                <td>
                    <label style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" name="update_existing" value="1" <?php checked($import['update_existing'], '1'); ?> style="margin-top: 3px;" />
                        <div>
                            <strong><?php esc_html_e('Update products that already exist (matched by SKU)', 'bootflow-product-importer'); ?></strong>
                            <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                <?php esc_html_e('When enabled, existing products with matching SKUs will be updated instead of creating duplicates.', 'bootflow-product-importer'); ?>
                            </p>
                        </div>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Skip Unchanged Products', 'bootflow-product-importer'); ?></th>
                <td>
                    <label style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" name="skip_unchanged" value="1" <?php checked(($import['skip_unchanged'] ?? '0') == '1', true); ?> style="margin-top: 3px;" />
                        <div>
                            <strong><?php esc_html_e('Skip products if data unchanged', 'bootflow-product-importer'); ?></strong>
                            <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                <?php esc_html_e('Reduces import time by skipping products that haven\'t changed.', 'bootflow-product-importer'); ?>
                            </p>
                        </div>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Handle Missing Products', 'bootflow-product-importer'); ?></th>
                <td>
                    <label style="display: flex; align-items: flex-start; gap: 10px;">
                        <input type="checkbox" name="handle_missing" id="handle_missing_edit" value="1" <?php checked(($import['handle_missing'] ?? '0') == '1', true); ?> style="margin-top: 3px;" />
                        <div>
                            <strong><?php esc_html_e('Process products no longer in feed', 'bootflow-product-importer'); ?></strong>
                            <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                <?php esc_html_e('When enabled, products that were imported before but are no longer in the XML/CSV file will be processed.', 'bootflow-product-importer'); ?>
                            </p>
                        </div>
                    </label>
                    
                    <div id="missing-products-options-edit" style="margin-left: 25px; margin-top: 15px; <?php echo (($import['handle_missing'] ?? '0') != '1') ? 'display: none;' : ''; ?>">
                        <div style="margin-bottom: 10px;">
                            <label for="missing_action_edit"><?php esc_html_e('Action for missing products:', 'bootflow-product-importer'); ?></label><br>
                            <select name="missing_action" id="missing_action_edit" class="regular-text" style="margin-top: 5px;">
                                <option value="draft" <?php selected($import['missing_action'] ?? 'draft', 'draft'); ?>><?php esc_html_e('Move to Draft (Recommended)', 'bootflow-product-importer'); ?></option>
                                <option value="outofstock" <?php selected($import['missing_action'] ?? '', 'outofstock'); ?>><?php esc_html_e('Mark as Out of Stock', 'bootflow-product-importer'); ?></option>
                                <option value="backorder" <?php selected($import['missing_action'] ?? '', 'backorder'); ?>><?php esc_html_e('Allow Backorder (stock=0)', 'bootflow-product-importer'); ?></option>
                                <option value="trash" <?php selected($import['missing_action'] ?? '', 'trash'); ?>><?php esc_html_e('Move to Trash (auto-delete after 30 days)', 'bootflow-product-importer'); ?></option>
                                <option value="delete" <?php selected($import['missing_action'] ?? '', 'delete'); ?>><?php esc_html_e('Permanently Delete (⚠️ DANGEROUS)', 'bootflow-product-importer'); ?></option>
                            </select>
                        </div>
                        
                        <label style="display: flex; align-items: flex-start; gap: 10px;">
                            <input type="checkbox" name="delete_variations" value="1" <?php checked(($import['delete_variations'] ?? '1') == '1', true); ?> style="margin-top: 3px;" />
                            <span><?php esc_html_e('Also process variations when parent product is missing', 'bootflow-product-importer'); ?></span>
                        </label>
                        
                        <p class="description" style="margin-top: 10px; color: #666;">
                            <span style="color: #0073aa;">ℹ️</span> 
                            <?php esc_html_e('Action will only affect products last updated by THIS import.', 'bootflow-product-importer'); ?>
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </div>
        <script type="text/javascript">
        // Pass import data for AJAX structure loading (edit mode)
        var wcAiImportData = {
            file_path: '<?php echo esc_js($import['file_path']); ?>',
            file_type: '<?php echo esc_js($import['file_type']); ?>',
            import_name: '<?php echo esc_js($import['name']); ?>',
            schedule_type: '<?php echo esc_js($import['schedule_type']); ?>',
            product_wrapper: '<?php echo esc_js($import['product_wrapper']); ?>',
            update_existing: '<?php echo esc_js($import['update_existing']); ?>',
            batch_size: <?php echo intval($import['batch_size'] ?? 50); ?>,
            existing_mappings: <?php echo json_encode($existing_mappings); ?>,
            saved_mappings: <?php echo json_encode($existing_mappings); ?>,  // For loadSavedMappings() compatibility
            ajax_url: wc_xml_csv_ai_import_ajax.ajax_url,
            nonce: wc_xml_csv_ai_import_ajax.nonce
        };
        </script>
        <?php wp_nonce_field('update_import_' . $import_id); ?>
        
        <!-- Hidden fields for schedule (always present, even if PRO section is hidden) -->
        <input type="hidden" name="schedule_type_hidden" value="<?php echo esc_attr($import['schedule_type'] ?? 'none'); ?>" />
        <input type="hidden" name="schedule_method_hidden" value="<?php echo esc_attr($import['schedule_method'] ?? 'action_scheduler'); ?>" />
        
        <!-- Import Settings -->
        <div class="wc-ai-import-card" style="margin-bottom: 20px;">
            <h3><?php esc_html_e('Import Settings', 'bootflow-product-importer'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Batch Size', 'bootflow-product-importer'); ?></th>
                    <td>
                        <input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr($import['batch_size'] ?? 50); ?>" min="1" max="500" style="width: 100px;">
                        <span class="description"><?php esc_html_e('Products to process per chunk (1-500). Higher = faster, but more memory. Recommended: 50-200 for updates.', 'bootflow-product-importer'); ?></span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="wc-ai-import-layout">
            <!-- Left Sidebar - File Structure -->
            <div class="wc-ai-import-sidebar">
                <div class="wc-ai-import-card">
                    <h3><?php esc_html_e('File Structure', 'bootflow-product-importer'); ?></h3>
                    <div class="file-info">
                        <p><strong><?php esc_html_e('File:', 'bootflow-product-importer'); ?></strong> <?php echo esc_html(basename($import['file_path'])); ?></p>
                        <p><strong><?php esc_html_e('Type:', 'bootflow-product-importer'); ?></strong> <?php echo esc_html(strtoupper($import['file_type'])); ?></p>
                        <p><strong><?php esc_html_e('Import:', 'bootflow-product-importer'); ?></strong> <?php echo esc_html($import['name']); ?></p>
                    </div>
                    <div id="file-structure-browser">
                        <div class="structure-loader">
                            <div class="spinner is-active"></div>
                            <p><?php esc_html_e('Loading file structure...', 'bootflow-product-importer'); ?></p>
                        </div>
                    </div>
                    <div class="structure-pagination" id="structure-pagination" style="display: none; margin-top: 15px; text-align: center;">
                        <button type="button" class="button" id="prev-page"><?php esc_html_e('Previous', 'bootflow-product-importer'); ?></button>
                        <span class="pagination-info" style="display: inline-block; vertical-align: middle;">
                            Page <input type="number" id="current-page-input" min="1" style="width: 50px; text-align: center; display: inline-block; vertical-align: middle;" /> 
                            of <span id="total-pages-display">1</span>
                        </span>
                        <button type="button" class="button" id="next-page"><?php esc_html_e('Next', 'bootflow-product-importer'); ?></button>
                    </div>
                </div>
                <div class="wc-ai-import-card">
                    <h3><?php esc_html_e('Sample Data', 'bootflow-product-importer'); ?></h3>
                    <div id="sample-data-preview">
                        <p class="description"><?php esc_html_e('Sample product data will appear here after loading the file structure.', 'bootflow-product-importer'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Main Content - Field Mapping -->
            <div class="wc-ai-import-main">
                <div class="wc-ai-import-card">
                    <h2><?php esc_html_e('Field Mappings', 'bootflow-product-importer'); ?></h2>
                    <p class="description"><?php esc_html_e('Map your file fields to WooCommerce product fields and configure processing modes.', 'bootflow-product-importer'); ?></p>
                    
                    <?php if (empty($existing_mappings)): ?>
                    <div class="notice notice-warning inline" style="margin: 15px 0;">
                        <p><strong><?php esc_html_e('Configuration Required:', 'bootflow-product-importer'); ?></strong> <?php esc_html_e('This import needs to be configured. Please select source fields from the dropdowns below, configure processing modes, and click "Save Changes" to activate this import.', 'bootflow-product-importer'); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="mapping-actions" style="margin-bottom: 15px;">
                        <button type="button" class="button button-secondary" onclick="clearAllMapping()">
                            <?php esc_html_e('Clear All', 'bootflow-product-importer'); ?>
                        </button>
                        <button type="button" class="button button-secondary" onclick="alert('Test mapping feature coming soon')">
                            <?php esc_html_e('Test Mapping', 'bootflow-product-importer'); ?>
                        </button>
                    </div>
            
                    <!-- Field Mapping Sections -->
                    <div class="field-mapping-sections">
                <?php foreach ($woocommerce_fields as $section_key => $section): ?>
                    <div class="mapping-section" data-section="<?php echo esc_attr($section_key); ?>">
                        <h3 class="section-toggle">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                            <?php echo esc_attr($section['title']); ?>
                            <?php 
                            $mapped = 0;
                            foreach ($section['fields'] as $fk => $fv) {
                                if (isset($existing_mappings[$fk]['source']) && !empty($existing_mappings[$fk]['source'])) {
                                    $mapped++;
                                }
                            }
                            ?>
                            <span class="mapped-count"><?php echo esc_html($mapped . '/' . count($section['fields'])); ?></span>
                        </h3>
                        
                        <div class="section-fields" id="section-<?php echo esc_attr($section_key); ?>">
                            <?php foreach ($section['fields'] as $field_key => $field): 
                                $current_mapping = $existing_mappings[$field_key] ?? array();
                                $current_source = $current_mapping['source'] ?? '';
                                $current_mode = $current_mapping['processing_mode'] ?? 'direct';
                            ?>
                                <?php if ($field_key === 'shipping_class_formula'): ?>
                                    <!-- Special handling for Shipping Class Formula -->
                                    <div class="shipping-class-formula-section" style="padding: 20px; background: #f9f9f9; border-radius: 4px; margin: 10px 0;">
                                        <h4 style="margin-top: 0;"><?php esc_html_e('Shipping Class Assignment', 'bootflow-product-importer'); ?></h4>
                                        <p class="description" style="margin-bottom: 15px;">
                                            <?php esc_html_e('Write a PHP formula to automatically assign shipping classes based on product dimensions and weight. The formula should return the shipping class slug (e.g., "S", "M", "L", "Smags").', 'bootflow-product-importer'); ?>
                                        </p>
                                        
                                        <label style="font-weight: bold; display: block; margin-bottom: 8px;">
                                            <?php esc_html_e('PHP Formula (return shipping class slug):', 'bootflow-product-importer'); ?>
                                        </label>
                                        
                                        <textarea name="field_mapping[shipping_class_formula][formula]" 
                                                  id="shipping-class-formula" 
                                                  rows="12" 
                                                  style="width: 100%; font-family: 'Courier New', monospace; font-size: 13px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                                                  placeholder="// Available variables: $weight, $length, $width, $height&#10;&#10;if ($weight > 30) {&#10;    return 'Smags';&#10;}&#10;&#10;if ($height <= 8 && $length <= 38 && $width <= 64) {&#10;    return 'S';&#10;}&#10;&#10;if ($height <= 39 && $length <= 38 && $width <= 64) {&#10;    return 'M';&#10;}&#10;&#10;return 'L';"><?php echo esc_textarea($existing_mappings['shipping_class_formula']['formula'] ?? ''); ?></textarea>
                                        
                                        <button type="button" class="button button-small test-shipping-formula" style="margin-top: 10px;">
                                            <?php esc_html_e('Test Shipping Formula', 'bootflow-product-importer'); ?>
                                        </button>
                                        
                                        <div style="margin-top: 10px; padding: 10px; background: #fff; border-left: 3px solid #0073aa; border-radius: 3px;">
                                            <strong><?php esc_html_e('Available Variables:', 'bootflow-product-importer'); ?></strong><br>
                                            <code>$weight</code>, <code>$length</code>, <code>$width</code>, <code>$height</code>
                                            <br><br>
                                            <strong><?php esc_html_e('Available Shipping Classes:', 'bootflow-product-importer'); ?></strong><br>
                                            <?php
                                            $shipping_classes = get_terms(array(
                                                'taxonomy' => 'product_shipping_class',
                                                'hide_empty' => false,
                                            ));
                                            if (!empty($shipping_classes) && !is_wp_error($shipping_classes)):
                                                foreach ($shipping_classes as $class):
                                                    echo '<code>' . esc_html($class->slug) . '</code> (' . esc_html($class->name) . ') ';
                                                endforeach;
                                            else:
                                                esc_html_e('No shipping classes found. Please create them in WooCommerce → Settings → Shipping → Shipping classes', 'bootflow-product-importer');
                                            endif;
                                            ?>
                                            <br><br>
                                            <em><?php esc_html_e('Leave empty to skip automatic shipping class assignment.', 'bootflow-product-importer'); ?></em>
                                        </div>
                                    </div>
                                <?php else: ?>
                                <div class="field-mapping-row" data-field="<?php echo esc_attr($field_key); ?>">
                                    <div class="field-target">
                                        <label class="field-label <?php echo esc_attr($field['required'] ? 'required' : ''); ?>">
                                            <?php echo esc_attr($field['label']); ?>
                                            <?php if ($field['required']): ?>
                                                <span class="required-asterisk">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <span class="field-type"><?php echo esc_attr($field['type'] ?? 'text'); ?></span>
                                        <label class="update-field-checkbox" style="margin-top: 8px; font-weight: normal; font-size: 12px; display: flex; align-items: center; gap: 5px;">
                                            <!-- Hidden input sends '0' if checkbox unchecked, checkbox overrides with '1' if checked -->
                                            <input type="hidden" 
                                                   name="field_mapping[<?php echo esc_attr($field_key); ?>][update_on_sync]" 
                                                   value="0">
                                            <input type="checkbox" 
                                                   name="field_mapping[<?php echo esc_attr($field_key); ?>][update_on_sync]" 
                                                   value="1"
                                                   <?php checked(!isset($current_mapping['update_on_sync']) || $current_mapping['update_on_sync'] !== '0'); ?>
                                                   style="margin: 0;">
                                            <span style="color: #646970;"><?php esc_html_e('Update this field?', 'bootflow-product-importer'); ?></span>
                                        </label>
                                    </div>
                                    
                                    <div class="field-source">
                                        <?php
                                        // Use new textarea UI for all fields (with drag & drop support)
                                        $is_large_textarea = in_array($field_key, array('description', 'short_description', 'purchase_note', 'meta_description'));
                                        $textarea_rows = $is_large_textarea ? 4 : 1;
                                        $textarea_class = $is_large_textarea ? 'field-mapping-textarea field-mapping-textarea-large' : 'field-mapping-textarea field-mapping-textarea-small';
                                        ?>
                                        <div class="textarea-mapping-wrapper" data-field="<?php echo esc_attr($field_key); ?>">
                                            <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][source]" 
                                                      class="<?php echo esc_attr($textarea_class); ?>" 
                                                      rows="<?php echo esc_attr($textarea_rows); ?>"
                                                      data-field-name="<?php echo esc_attr($field_key); ?>"
                                                      placeholder="<?php 
                                                      // translators: %s is the field key example
                                                      echo esc_attr(sprintf(__('Type { to see fields or drag field here. E.g. {%s}', 'bootflow-product-importer'), strtolower(str_replace('_', '', $field_key)))); ?>"
                                            ><?php echo esc_textarea($current_source); ?></textarea>
                                            <?php if (!empty($field['description'])): ?>
                                                <p class="description" style="margin-top: 4px; font-size: 11px;"><?php echo esc_html($field['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="processing-mode">
                                        <select name="field_mapping[<?php echo esc_attr($field_key); ?>][processing_mode]" class="processing-mode-select" data-field="<?php echo esc_attr($field_key); ?>">
                                            <option value="direct" <?php selected($current_mode, 'direct'); ?>><?php esc_html_e('Direct Mapping', 'bootflow-product-importer'); ?></option>
                                            <option value="php_formula" <?php selected($current_mode, 'php_formula'); ?>><?php esc_html_e('PHP Formula', 'bootflow-product-importer'); ?></option>
                                            <option value="ai_processing" <?php selected($current_mode, 'ai_processing'); ?>><?php esc_html_e('AI Processing', 'bootflow-product-importer'); ?></option>
                                            <option value="hybrid" <?php selected($current_mode, 'hybrid'); ?>><?php esc_html_e('Hybrid (PHP + AI)', 'bootflow-product-importer'); ?></option>
                                        </select>
                                    </div>
                                    
                                    <div class="processing-config" style="<?php echo ($current_mode !== 'direct') ? '' : 'display: none;'; ?>">
                                        <div class="config-content">
                                            <!-- PHP Formula Config -->
                                            <div class="php-formula-config config-panel" style="<?php echo ($current_mode === 'php_formula') ? '' : 'display: none;'; ?>">
                                                <label><?php esc_html_e('PHP Formula:', 'bootflow-product-importer'); ?></label>
                                                <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][php_formula]" 
                                                          placeholder="<?php esc_html_e('e.g., $value * 1.2', 'bootflow-product-importer'); ?>" 
                                                          rows="3"><?php echo esc_textarea($current_mapping['php_formula'] ?? ''); ?></textarea>
                                                <p class="description">
                                                    <?php if (in_array($field_key, ['regular_price', 'sale_price'])): ?>
                                                        <strong><?php esc_html_e('⚠️ Always use $value as input (not field names like $price or $product_price)', 'bootflow-product-importer'); ?></strong><br>
                                                    <?php endif; ?>
                                                    <?php esc_html_e('Example: $value * 1.2 (adds 20% markup)', 'bootflow-product-importer'); ?>
                                                </p>
                                                <button type="button" class="button button-small test-php-formula" 
                                                        data-field="<?php echo esc_attr($field_key); ?>">
                                                    <?php esc_html_e('Test PHP Formula', 'bootflow-product-importer'); ?>
                                                </button>
                                            </div>
                                            
                                            <!-- AI Processing Config -->
                                            <div class="ai-processing-config config-panel" style="<?php echo ($current_mode === 'ai_processing') ? '' : 'display: none;'; ?>">
                                                <div class="ai-provider-selection">
                                                    <label><?php esc_html_e('AI Provider:', 'bootflow-product-importer'); ?></label>
                                                    <select name="field_mapping[<?php echo esc_attr($field_key); ?>][ai_provider]">
                                                        <?php foreach ($ai_providers as $provider_key => $provider_name): ?>
                                                            <option value="<?php echo esc_attr($provider_key); ?>" 
                                                                    <?php selected($current_mapping['ai_provider'] ?? 'openai', $provider_key); ?>>
                                                                <?php echo esc_attr($provider_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <label><?php esc_html_e('AI Prompt:', 'bootflow-product-importer'); ?></label>
                                                <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][ai_prompt]" 
                                                          placeholder="<?php esc_html_e('e.g., Translate this product name to English and make it SEO-friendly', 'bootflow-product-importer'); ?>" 
                                                          rows="3"><?php echo esc_textarea($current_mapping['ai_prompt'] ?? ''); ?></textarea>
                                                <button type="button" class="button button-small test-ai-field" 
                                                        data-field="<?php echo esc_attr($field_key); ?>">
                                                    <?php esc_html_e('Test AI', 'bootflow-product-importer'); ?>
                                                </button>
                                            </div>
                                            
                                            <!-- Hybrid Config -->
                                            <div class="hybrid-config config-panel" style="<?php echo ($current_mode === 'hybrid') ? '' : 'display: none;'; ?>">
                                                <label><?php esc_html_e('PHP Formula (executed first):', 'bootflow-product-importer'); ?></label>
                                                <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][hybrid_php]" 
                                                          placeholder="<?php esc_html_e('e.g., trim(strtolower($value))', 'bootflow-product-importer'); ?>" 
                                                          rows="2"><?php echo esc_textarea($current_mapping['hybrid_php'] ?? ''); ?></textarea>
                                                <p class="description">
                                                    <strong><?php esc_html_e('Use $value as input', 'bootflow-product-importer'); ?></strong> - 
                                                    <?php esc_html_e('Result will be passed to AI', 'bootflow-product-importer'); ?>
                                                </p>
                                                
                                                <label><?php esc_html_e('AI Prompt (applied to PHP result):', 'bootflow-product-importer'); ?></label>
                                                <textarea name="field_mapping[<?php echo esc_attr($field_key); ?>][hybrid_ai_prompt]" 
                                                          placeholder="<?php esc_html_e('e.g., Enhance this processed text for better readability', 'bootflow-product-importer'); ?>" 
                                                          rows="2"><?php echo esc_textarea($current_mapping['hybrid_ai_prompt'] ?? ''); ?></textarea>
                                                
                                                <div class="ai-provider-selection">
                                                    <label><?php esc_html_e('AI Provider:', 'bootflow-product-importer'); ?></label>
                                                    <select name="field_mapping[<?php echo esc_attr($field_key); ?>][hybrid_ai_provider]">
                                                        <?php foreach ($ai_providers as $provider_key => $provider_name): ?>
                                                            <option value="<?php echo esc_attr($provider_key); ?>" 
                                                                    <?php selected($current_mapping['hybrid_ai_provider'] ?? 'openai', $provider_key); ?>>
                                                                <?php echo esc_attr($provider_name); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="field-actions">
                                        <button type="button" class="button button-small toggle-config" title="<?php esc_html_e('Configure Processing', 'bootflow-product-importer'); ?>">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                        </button>
                                        <button type="button" class="button button-small clear-mapping" title="<?php esc_html_e('Clear Mapping', 'bootflow-product-importer'); ?>">
                                            <span class="dashicons dashicons-no-alt"></span>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Import Filters -->
            <div class="mapping-section" data-section="filters">
                <h3 class="section-toggle">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <?php esc_html_e('Import Filters', 'bootflow-product-importer'); ?>
                    <button type="button" class="button button-small" onclick="addFilterRule(event)" style="margin-left: 10px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Add Filter', 'bootflow-product-importer'); ?>
                    </button>
                </h3>
                <p class="description" style="margin: 10px 15px; color: #666;">
                    <?php esc_html_e('Filter which products to import based on field values. Products that don\'t match will be skipped.', 'bootflow-product-importer'); ?>
                </p>
                
                <div class="section-fields">
                    <div id="import-filters-container">
                        <?php 
                        $existing_filters = isset($import['import_filters']) ? json_decode($import['import_filters'], true) : array();
                        if (!empty($existing_filters) && is_array($existing_filters)):
                            $total_filters = count($existing_filters);
                            foreach ($existing_filters as $filter_index => $filter):
                                $is_last = ($filter_index === $total_filters - 1);
                        ?>
                            <div class="filter-rule-row" style="display: flex; gap: 10px; align-items: center; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Field', 'bootflow-product-importer'); ?></label>
                                    <select name="import_filters[<?php echo esc_attr($filter_index); ?>][field]" class="filter-field-select import-filter-field-select" data-selected="<?php echo esc_attr($filter['field'] ?? ''); ?>" style="width: 100%;">
                                        <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-importer'); ?></option>
                                        <?php foreach ($file_fields as $ff): ?>
                                            <option value="<?php echo esc_attr($ff); ?>" <?php selected($filter['field'] ?? '', $ff); ?>><?php echo esc_html($ff); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div style="flex: 0 0 150px;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Operator', 'bootflow-product-importer'); ?></label>
                                    <select name="import_filters[<?php echo esc_attr($filter_index); ?>][operator]" style="width: 100%;">
                                        <option value="=" <?php selected($filter['operator'] ?? '', '='); ?>>=</option>
                                        <option value="!=" <?php selected($filter['operator'] ?? '', '!='); ?>>!=</option>
                                        <option value=">" <?php selected($filter['operator'] ?? '', '>'); ?>>></option>
                                        <option value="<" <?php selected($filter['operator'] ?? '', '<'); ?>><</option>
                                        <option value=">=" <?php selected($filter['operator'] ?? '', '>='); ?>>>=</option>
                                        <option value="<=" <?php selected($filter['operator'] ?? '', '<='); ?>><=</option>
                                        <option value="contains" <?php selected($filter['operator'] ?? '', 'contains'); ?>><?php esc_html_e('contains', 'bootflow-product-importer'); ?></option>
                                        <option value="not_contains" <?php selected($filter['operator'] ?? '', 'not_contains'); ?>><?php esc_html_e('not contains', 'bootflow-product-importer'); ?></option>
                                        <option value="empty" <?php selected($filter['operator'] ?? '', 'empty'); ?>><?php esc_html_e('is empty', 'bootflow-product-importer'); ?></option>
                                        <option value="not_empty" <?php selected($filter['operator'] ?? '', 'not_empty'); ?>><?php esc_html_e('not empty', 'bootflow-product-importer'); ?></option>
                                    </select>
                                </div>
                                
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Value', 'bootflow-product-importer'); ?></label>
                                    <input type="text" name="import_filters[<?php echo esc_attr($filter_index); ?>][value]" value="<?php echo esc_attr($filter['value'] ?? ''); ?>" placeholder="<?php esc_html_e('Comparison value', 'bootflow-product-importer'); ?>" style="width: 100%;" />
                                </div>
                                
                                <?php if (!$is_last): ?>
                                <div style="flex: 0 0 100px;">
                                    <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Condition', 'bootflow-product-importer'); ?></label>
                                    <div style="display: flex; gap: 8px;">
                                        <label style="margin: 0; display: flex; align-items: center;">
                                            <input type="radio" name="import_filters[<?php echo esc_attr($filter_index); ?>][logic]" value="AND" <?php checked($filter['logic'] ?? 'AND', 'AND'); ?> style="margin: 0 4px 0 0;" />
                                            <span style="font-size: 12px;">AND</span>
                                        </label>
                                        <label style="margin: 0; display: flex; align-items: center;">
                                            <input type="radio" name="import_filters[<?php echo esc_attr($filter_index); ?>][logic]" value="OR" <?php checked($filter['logic'] ?? 'AND', 'OR'); ?> style="margin: 0 4px 0 0;" />
                                            <span style="font-size: 12px;">OR</span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div style="flex: 0 0 40px;">
                                    <label style="display: block; font-size: 11px; color: transparent; margin-bottom: 3px;">.</label>
                                    <button type="button" class="button button-small remove-filter-rule" onclick="removeFilterRule(event)" style="padding: 6px 10px;">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <p class="no-filters" style="padding: 15px; color: #666; text-align: center;">
                                <?php esc_html_e('No filters added. All products will be imported.', 'bootflow-product-importer'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Filter Warning and Options -->
                    <?php if (!empty($existing_filters)): ?>
                    <div style="margin: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                        <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
                            <span class="dashicons dashicons-warning" style="color: #ffc107;"></span>
                            <?php esc_html_e('Filter Behavior', 'bootflow-product-importer'); ?>
                        </p>
                        <p style="margin: 0 0 10px 0; font-size: 13px; color: #856404;">
                            <?php esc_html_e('⚠️ Changing filters will affect future imports. Existing products won\'t be modified automatically.', 'bootflow-product-importer'); ?>
                        </p>
                        <label style="display: block; margin-top: 10px;">
                            <input type="checkbox" name="draft_non_matching" value="1" <?php checked($import['draft_non_matching'] ?? '0', '1'); ?> />
                            <strong><?php esc_html_e('Move non-matching products to Draft', 'bootflow-product-importer'); ?></strong>
                            <br>
                            <span style="font-size: 12px; color: #666; margin-left: 20px;">
                                <?php esc_html_e('When re-running, products that no longer match filters will be set to Draft status (not deleted).', 'bootflow-product-importer'); ?>
                            </span>
                            <br>
                            <span style="font-size: 12px; color: #d63638; margin-left: 20px; margin-top: 5px; display: block;">
                                <?php esc_html_e('⚠️ If unchecked: Products not matching filters will be skipped during import, but existing products will remain Published.', 'bootflow-product-importer'); ?>
                            </span>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Custom Fields -->
            <div class="mapping-section" data-section="custom">
                <h3 class="section-toggle">
                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                    <?php esc_html_e('Custom Fields', 'bootflow-product-importer'); ?>
                    <button type="button" class="button button-small" onclick="addCustomField(event)" style="margin-left: 10px;">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        <?php esc_html_e('Add Custom Field', 'bootflow-product-importer'); ?>
                    </button>
                </h3>
                
                <div class="section-fields">
                    <div id="custom-fields-container">
                        <!-- DEBUG: saved_custom_fields = <?php echo isset($saved_custom_fields) ? 'SET(' . (is_array($saved_custom_fields) ? count($saved_custom_fields) : 'not-array') . ')' : 'NOT SET'; ?> -->
                        <?php 
                        $custom_field_index = 0;
                        // Use $saved_custom_fields array (populated by admin class from both sources)
                        if (!empty($saved_custom_fields) && is_array($saved_custom_fields)):
                            foreach ($saved_custom_fields as $mapping):
                                $cf_name = $mapping['name'] ?? '';
                                if (empty($cf_name)) continue;
                                ?>
                                <div class="custom-field-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-left: 3px solid #2271b1;">
                                    <table class="form-table" style="margin: 0;">
                                        <tr>
                                            <td style="width: 20%;">
                                                <input type="text" name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][name]" value="<?php echo esc_attr($cf_name); ?>" placeholder="<?php esc_html_e('Field Name', 'bootflow-product-importer'); ?>" class="widefat" />
                                            </td>
                                            <td style="width: 20%;">
                                                <select name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][source]" class="widefat">
                                                    <option value=""><?php esc_html_e('-- Select Source --', 'bootflow-product-importer'); ?></option>
                                                    <?php foreach ($file_fields as $ff): ?>
                                                        <option value="<?php echo esc_attr($ff); ?>" <?php selected($mapping['source'] ?? '', $ff); ?>><?php echo esc_html($ff); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td style="width: 15%;">
                                                <select name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][type]" class="widefat">
                                                    <option value="text" <?php selected($mapping['type'] ?? 'text', 'text'); ?>><?php esc_html_e('Text', 'bootflow-product-importer'); ?></option>
                                                    <option value="number" <?php selected($mapping['type'] ?? '', 'number'); ?>><?php esc_html_e('Number', 'bootflow-product-importer'); ?></option>
                                                    <option value="textarea" <?php selected($mapping['type'] ?? '', 'textarea'); ?>><?php esc_html_e('Textarea', 'bootflow-product-importer'); ?></option>
                                                </select>
                                            </td>
                                            <td style="width: 35%;">
                                                <select name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][processing_mode]" class="widefat custom-field-processing-mode" onchange="toggleCustomFieldConfig(this)">
                                                    <option value="direct" <?php selected($mapping['processing_mode'] ?? 'direct', 'direct'); ?>><?php esc_html_e('Direct', 'bootflow-product-importer'); ?></option>
                                                    <option value="php_formula" <?php selected($mapping['processing_mode'] ?? '', 'php_formula'); ?>><?php esc_html_e('PHP Formula', 'bootflow-product-importer'); ?></option>
                                                    <option value="ai_processing" <?php selected($mapping['processing_mode'] ?? '', 'ai_processing'); ?>><?php esc_html_e('AI Processing', 'bootflow-product-importer'); ?></option>
                                                </select>
                                            </td>
                                            <td style="width: 10%; text-align: center;">
                                                <button type="button" class="button" onclick="this.closest('.custom-field-row').remove();" title="<?php esc_html_e('Remove', 'bootflow-product-importer'); ?>">
                                                    <span class="dashicons dashicons-trash"></span>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="custom-field-config-row" style="<?php echo ($mapping['processing_mode'] ?? 'direct') === 'direct' ? 'display:none;' : ''; ?>">
                                            <td colspan="5" style="padding-top: 10px;">
                                                <!-- PHP Formula Config -->
                                                <div class="php-formula-config" style="<?php echo ($mapping['processing_mode'] ?? '') !== 'php_formula' ? 'display:none;' : ''; ?>">
                                                    <label><strong><?php esc_html_e('PHP Formula:', 'bootflow-product-importer'); ?></strong></label>
                                                    <textarea name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][php_formula]" 
                                                              placeholder="<?php esc_html_e('e.g., return $value + 20;', 'bootflow-product-importer'); ?>" 
                                                              rows="2" style="width:100%;"><?php echo esc_textarea($mapping['php_formula'] ?? ''); ?></textarea>
                                                </div>
                                                <!-- AI Processing Config -->
                                                <div class="ai-processing-config" style="<?php echo ($mapping['processing_mode'] ?? '') !== 'ai_processing' ? 'display:none;' : ''; ?>">
                                                    <label><strong><?php esc_html_e('AI Provider:', 'bootflow-product-importer'); ?></strong></label>
                                                    <select name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][ai_provider]" style="width:200px; margin-bottom:5px;">
                                                        <option value="openai" <?php selected($mapping['ai_provider'] ?? 'openai', 'openai'); ?>>OpenAI GPT</option>
                                                        <option value="claude" <?php selected($mapping['ai_provider'] ?? '', 'claude'); ?>>Anthropic Claude</option>
                                                    </select>
                                                    <br>
                                                    <label><strong><?php esc_html_e('AI Prompt:', 'bootflow-product-importer'); ?></strong></label>
                                                    <textarea name="custom_fields[<?php echo esc_attr($custom_field_index); ?>][ai_prompt]" 
                                                              placeholder="<?php esc_html_e('e.g., Add 20 to this number: {value}. Return only the result number.', 'bootflow-product-importer'); ?>" 
                                                              rows="2" style="width:100%;"><?php echo esc_textarea($mapping['ai_prompt'] ?? ''); ?></textarea>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <?php
                                $custom_field_index++;
                            endforeach;
                        endif;
                        
                        // Show message if no custom fields
                        if ($custom_field_index === 0):
                        ?>
                        <p class="no-custom-fields" style="color: #666; font-style: italic;">
                            <?php esc_html_e('No custom fields added yet. Click "Add Custom Field" to create one.', 'bootflow-product-importer'); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
                </div>
            </div>
        </div>
        
        <!-- Automated Schedule (PRO only) -->
        <?php 
        if (WC_XML_CSV_AI_Import_Features::is_available('scheduled_import')): 
            $schedule_method = $import['schedule_method'] ?? 'action_scheduler';
            $global_settings = get_option('wc_xml_csv_ai_import_settings', array());
            $cron_secret = $global_settings['cron_secret_key'] ?? '';
            $cron_url = admin_url('admin-ajax.php') . '?action=wc_xml_csv_ai_import_cron&secret=' . $cron_secret;
        ?>
        <div class="wc-ai-import-card" style="margin-top: 20px;">
            <h2><?php esc_html_e('Automated Schedule', 'bootflow-product-importer'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Schedule Interval', 'bootflow-product-importer'); ?></th>
                    <td>
                        <select name="schedule_type" id="schedule_type_edit" class="regular-text">
                            <option value="none" <?php selected($import['schedule_type'], 'none'); ?>><?php esc_html_e('Disabled', 'bootflow-product-importer'); ?></option>
                            <option value="15min" <?php selected($import['schedule_type'], '15min'); ?>><?php esc_html_e('Every 15 minutes', 'bootflow-product-importer'); ?></option>
                            <option value="hourly" <?php selected($import['schedule_type'], 'hourly'); ?>><?php esc_html_e('Hourly', 'bootflow-product-importer'); ?></option>
                            <option value="6hours" <?php selected($import['schedule_type'], '6hours'); ?>><?php esc_html_e('Every 6 hours', 'bootflow-product-importer'); ?></option>
                            <option value="daily" <?php selected($import['schedule_type'], 'daily'); ?>><?php esc_html_e('Daily', 'bootflow-product-importer'); ?></option>
                            <option value="weekly" <?php selected($import['schedule_type'], 'weekly'); ?>><?php esc_html_e('Weekly', 'bootflow-product-importer'); ?></option>
                            <option value="monthly" <?php selected($import['schedule_type'], 'monthly'); ?>><?php esc_html_e('Monthly', 'bootflow-product-importer'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="schedule_method_row" style="<?php echo ($import['schedule_type'] === 'none' || empty($import['schedule_type'])) ? 'display:none;' : ''; ?>">
                    <th scope="row"><?php esc_html_e('Schedule Method', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label style="display: block; margin-bottom: 12px; padding: 12px; border: 2px solid <?php echo esc_attr($schedule_method === 'action_scheduler' ? '#0073aa' : '#ddd'); ?>; border-radius: 6px; cursor: pointer; background: <?php echo esc_attr($schedule_method === 'action_scheduler' ? '#f0f6fc' : '#fff'); ?>;">
                                <input type="radio" name="schedule_method" value="action_scheduler" <?php checked($schedule_method, 'action_scheduler'); ?>>
                                <strong><?php esc_html_e('Action Scheduler', 'bootflow-product-importer'); ?></strong>
                                <span style="background: #28a745; color: white; font-size: 10px; padding: 2px 6px; border-radius: 8px; margin-left: 6px;"><?php esc_html_e('Recommended', 'bootflow-product-importer'); ?></span>
                                <p class="description" style="margin: 6px 0 0 22px;">
                                    <?php esc_html_e('Automatically continues until complete. No server cron needed. Requires website traffic.', 'bootflow-product-importer'); ?>
                                </p>
                            </label>
                            
                            <label style="display: block; padding: 12px; border: 2px solid <?php echo esc_attr($schedule_method === 'server_cron' ? '#0073aa' : '#ddd'); ?>; border-radius: 6px; cursor: pointer; background: <?php echo esc_attr($schedule_method === 'server_cron' ? '#f0f6fc' : '#fff'); ?>;">
                                <input type="radio" name="schedule_method" value="server_cron" <?php checked($schedule_method, 'server_cron'); ?>>
                                <strong><?php esc_html_e('Server Cron', 'bootflow-product-importer'); ?></strong>
                                <p class="description" style="margin: 6px 0 0 22px;">
                                    <?php esc_html_e('Processes entire import in one request. 100% reliable but requires server cron setup.', 'bootflow-product-importer'); ?>
                                </p>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <!-- Server Cron Setup Instructions (only shown when server_cron is selected) -->
            <div id="server_cron_instructions" style="<?php echo esc_attr($schedule_method !== 'server_cron' ? 'display:none;' : ''); ?> margin-top: 15px; background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 15px;">
                <h4 style="margin-top: 0;">
                    <span class="dashicons dashicons-clock" style="color: #0073aa;"></span>
                    <?php esc_html_e('Server Cron Setup', 'bootflow-product-importer'); ?>
                </h4>
                
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th style="padding: 8px 10px 8px 0; width: 120px;"><?php esc_html_e('Cron URL', 'bootflow-product-importer'); ?></th>
                        <td style="padding: 8px 0;">
                            <input type="text" value="<?php echo esc_attr($cron_url); ?>" readonly class="large-text" style="font-size: 12px;" />
                            <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($cron_url); ?>'); alert('<?php esc_html_e('Copied!', 'bootflow-product-importer'); ?>');">
                                <?php esc_html_e('Copy', 'bootflow-product-importer'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <th style="padding: 8px 10px 8px 0;"><?php esc_html_e('cPanel Command', 'bootflow-product-importer'); ?></th>
                        <td style="padding: 8px 0;">
                            <?php 
                            $cron_patterns = array('15min'=>'*/15 * * * *','hourly'=>'0 * * * *','6hours'=>'0 */6 * * *','daily'=>'0 0 * * *','weekly'=>'0 0 * * 0','monthly'=>'0 0 1 * *');
                            $pattern = $cron_patterns[$import['schedule_type']] ?? '* * * * *';
                            $cmd = $pattern . ' curl -s "' . $cron_url . '" > /dev/null 2>&1';
                            ?>
                            <code style="display: block; padding: 8px; background: #1e1e1e; color: #9cdcfe; border-radius: 4px; font-size: 11px; word-break: break-all;"><?php echo esc_html($cmd); ?></code>
                            <button type="button" class="button button-small" style="margin-top: 5px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($cmd); ?>'); alert('<?php esc_html_e('Copied!', 'bootflow-product-importer'); ?>');">
                                <?php esc_html_e('Copy Command', 'bootflow-product-importer'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
                <p class="description" style="margin-top: 10px; margin-bottom: 0;">
                    <span style="color: #0073aa;">ℹ️</span> 
                    <?php esc_html_e('We recommend running cron every minute. The plugin will only process when the scheduled interval has passed.', 'bootflow-product-importer'); ?>
                </p>
            </div>
        </div>
        <?php else: ?>
        <!-- PRO Required Notice -->
        <div class="wc-ai-import-card" style="margin-top: 20px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <h2>
                <?php esc_html_e('Automated Schedule', 'bootflow-product-importer'); ?>
                <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 12px; padding: 4px 12px; border-radius: 12px; margin-left: 10px;">PRO</span>
            </h2>
            <p style="color: #6c757d;">
                <?php esc_html_e('Schedule automatic imports to run at regular intervals. Keep your products always up-to-date without manual intervention.', 'bootflow-product-importer'); ?>
            </p>
            <ul style="color: #6c757d; margin-left: 20px;">
                <li>✓ <?php esc_html_e('Every 15 minutes, hourly, daily, weekly, or monthly', 'bootflow-product-importer'); ?></li>
                <li>✓ <?php esc_html_e('Automatic product updates', 'bootflow-product-importer'); ?></li>
                <li>✓ <?php esc_html_e('Action Scheduler or Server Cron support', 'bootflow-product-importer'); ?></li>
            </ul>
            <a href="https://yourwebsite.com/pro" target="_blank" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <?php esc_html_e('Upgrade to PRO', 'bootflow-product-importer'); ?>
            </a>
        </div>
        <?php endif; ?>
        
        <p class="submit">
            <input type="submit" name="update_import" class="button button-primary button-large" value="<?php esc_html_e('Save Changes', 'bootflow-product-importer'); ?>" />
            
            <input type="submit" name="run_import_now" class="button button-hero" value="<?php esc_html_e('▶ Run Import Now', 'bootflow-product-importer'); ?>" style="background: #00a32a; border-color: #00a32a; color: #fff; margin-left: 10px;" />
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=wc-xml-csv-import-history')); ?>" class="button button-secondary">
                <?php esc_html_e('Cancel', 'bootflow-product-importer'); ?>
            </a>
        </p>
    </form>
</div>

<style>
.wc-ai-import-layout {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}
.wc-ai-import-sidebar {
    width: 320px;
    flex-shrink: 0;
}
.wc-ai-import-main {
    flex: 1;
    min-width: 0;
}
.wc-ai-import-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}
.wc-ai-import-card h2,
.wc-ai-import-card h3 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.wc-ai-import-sidebar .wc-ai-import-card h3 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
}
.file-info p {
    margin: 5px 0;
    font-size: 12px;
}
.file-info strong {
    display: inline-block;
    width: 60px;
}
.structure-content {
    max-height: 400px;
    overflow-y: auto;
    background: #f9f9f9;
    padding: 10px;
    border-radius: 3px;
    margin-top: 10px;
}
.mapping-section {
    margin-bottom: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
}
.mapping-section.collapsed .section-fields {
    display: none !important;
}
.mapping-section:not(.collapsed) .section-fields {
    display: block !important;
}
.mapping-section.collapsed .dashicons-arrow-down-alt2:before {
    content: "\f345" !important;
}
.mapping-section:not(.collapsed) .dashicons-arrow-down-alt2:before {
    content: "\f347" !important;
}
.section-toggle {
    background: #f6f7f7;
    padding: 12px 15px;
    margin: 0;
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #ddd;
}
.section-toggle:hover {
    background: #f0f0f1;
}
.section-toggle .dashicons {
    margin-right: 8px;
}
.section-toggle .mapped-count {
    margin-left: auto;
    background: #2271b1;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
}
.section-fields {
    padding: 15px;
}
.processing-mode-select {
    font-size: 13px;
}
.mapping-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}
.field-mapping-row {
    display: grid;
    grid-template-columns: 200px 1fr 180px 40px;
    gap: 15px;
    align-items: start;
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    background: #fff;
}
.field-mapping-row:hover {
    background: #f9f9f9;
}
.field-target {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.field-label {
    font-weight: 600;
    font-size: 13px;
    color: #1d2327;
}
.field-label.required {
    position: relative;
}
.required-asterisk {
    color: #d63638;
    margin-left: 3px;
}
.field-type {
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
}
.field-source select,
.processing-mode select {
    width: 100%;
}
.processing-config {
    grid-column: 1 / -1;
    padding: 15px;
    background: #f6f7f7;
    border-radius: 4px;
    margin-top: 10px;
}
.config-panel {
    margin-bottom: 15px;
}
.config-panel:last-child {
    margin-bottom: 0;
}
.config-panel label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 12px;
}
.config-panel textarea {
    width: 100%;
    font-family: monospace;
    font-size: 12px;
}
.config-panel .description {
    margin-top: 5px;
    font-size: 11px;
    color: #646970;
}
.ai-provider-selection {
    margin-bottom: 10px;
}
.ai-provider-selection select {
    width: 100%;
    max-width: 300px;
}
.field-actions {
    display: flex;
    gap: 5px;
    align-items: start;
}
.field-actions .button {
    padding: 4px 8px;
    min-width: auto;
}
.field-actions .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}
</style>

<script>
let customFieldCounter = <?php echo esc_attr($custom_field_index); ?>;

// Toggle custom field config visibility based on processing mode
function toggleCustomFieldConfig(selectElement) {
    const row = selectElement.closest('.custom-field-row');
    const configRow = row.querySelector('.custom-field-config-row');
    const phpConfig = row.querySelector('.php-formula-config');
    const aiConfig = row.querySelector('.ai-processing-config');
    const mode = selectElement.value;
    
    if (mode === 'direct') {
        configRow.style.display = 'none';
    } else {
        configRow.style.display = '';
        phpConfig.style.display = mode === 'php_formula' ? '' : 'none';
        aiConfig.style.display = mode === 'ai_processing' ? '' : 'none';
    }
}

function addCustomField(e) {
    e.preventDefault();
    const container = document.getElementById('custom-fields-container');
    const html = `
        <div class="custom-field-row" style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border-left: 3px solid #2271b1;">
            <table class="form-table" style="margin: 0;">
                <tr>
                    <td style="width: 20%;">
                        <input type="text" name="custom_fields[${customFieldCounter}][name]" placeholder="<?php esc_html_e('Field Name', 'bootflow-product-importer'); ?>" class="widefat" />
                    </td>
                    <td style="width: 20%;">
                        <select name="custom_fields[${customFieldCounter}][source]" class="widefat">
                            <option value=""><?php esc_html_e('-- Select Source --', 'bootflow-product-importer'); ?></option>
                            <?php foreach ($file_fields as $ff): ?>
                                <option value="<?php echo esc_attr($ff); ?>"><?php echo esc_html($ff); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="width: 15%;">
                        <select name="custom_fields[${customFieldCounter}][type]" class="widefat">
                            <option value="text"><?php esc_html_e('Text', 'bootflow-product-importer'); ?></option>
                            <option value="number"><?php esc_html_e('Number', 'bootflow-product-importer'); ?></option>
                            <option value="textarea"><?php esc_html_e('Textarea', 'bootflow-product-importer'); ?></option>
                        </select>
                    </td>
                    <td style="width: 35%;">
                        <select name="custom_fields[${customFieldCounter}][processing_mode]" class="widefat custom-field-processing-mode" onchange="toggleCustomFieldConfig(this)">
                            <option value="direct"><?php esc_html_e('Direct', 'bootflow-product-importer'); ?></option>
                            <option value="php_formula"><?php esc_html_e('PHP Formula', 'bootflow-product-importer'); ?></option>
                            <option value="ai_processing"><?php esc_html_e('AI Processing', 'bootflow-product-importer'); ?></option>
                        </select>
                    </td>
                    <td style="width: 10%; text-align: center;">
                        <button type="button" class="button" onclick="this.closest('.custom-field-row').remove();" title="<?php esc_html_e('Remove', 'bootflow-product-importer'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </td>
                </tr>
                <tr class="custom-field-config-row" style="display:none;">
                    <td colspan="5" style="padding-top: 10px;">
                        <!-- PHP Formula Config -->
                        <div class="php-formula-config" style="display:none;">
                            <label><strong><?php esc_html_e('PHP Formula:', 'bootflow-product-importer'); ?></strong></label>
                            <textarea name="custom_fields[${customFieldCounter}][php_formula]" 
                                      placeholder="<?php esc_html_e('e.g., return $value + 20;', 'bootflow-product-importer'); ?>" 
                                      rows="2" style="width:100%;"></textarea>
                        </div>
                        <!-- AI Processing Config -->
                        <div class="ai-processing-config" style="display:none;">
                            <label><strong><?php esc_html_e('AI Provider:', 'bootflow-product-importer'); ?></strong></label>
                            <select name="custom_fields[${customFieldCounter}][ai_provider]" style="width:200px; margin-bottom:5px;">
                                <option value="openai">OpenAI GPT</option>
                                <option value="claude">Anthropic Claude</option>
                            </select>
                            <br>
                            <label><strong><?php esc_html_e('AI Prompt:', 'bootflow-product-importer'); ?></strong></label>
                            <textarea name="custom_fields[${customFieldCounter}][ai_prompt]" 
                                      placeholder="<?php esc_html_e('e.g., Add 20 to this number: {value}. Return only the result number.', 'bootflow-product-importer'); ?>" 
                                      rows="2" style="width:100%;"></textarea>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    customFieldCounter++;
}

// Filter rule functions
let filterRuleCounter = <?php echo !empty($existing_filters) ? count($existing_filters) : 0; ?>;

function addFilterRule(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const container = document.getElementById('import-filters-container');
    const noFilters = container.querySelector('.no-filters');
    if (noFilters) noFilters.remove();
    
    const html = `
        <div class="filter-rule-row" style="display: flex; gap: 10px; align-items: center; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
            <div style="flex: 1;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Field', 'bootflow-product-importer'); ?></label>
                <select name="import_filters[${filterRuleCounter}][field]" style="width: 100%;">
                    <option value=""><?php esc_html_e('-- Select Field --', 'bootflow-product-importer'); ?></option>
                    <?php foreach ($file_fields as $ff): ?>
                        <option value="<?php echo esc_attr($ff); ?>"><?php echo esc_html($ff); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 0 0 150px;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Operator', 'bootflow-product-importer'); ?></label>
                <select name="import_filters[${filterRuleCounter}][operator]" style="width: 100%;">
                    <option value="=">=</option>
                    <option value="!=">!=</option>
                    <option value=">">></option>
                    <option value="<"><</option>
                    <option value=">=">>=</option>
                    <option value="<="><=</option>
                    <option value="contains"><?php esc_html_e('contains', 'bootflow-product-importer'); ?></option>
                    <option value="not_contains"><?php esc_html_e('not contains', 'bootflow-product-importer'); ?></option>
                    <option value="empty"><?php esc_html_e('is empty', 'bootflow-product-importer'); ?></option>
                    <option value="not_empty"><?php esc_html_e('not empty', 'bootflow-product-importer'); ?></option>
                </select>
            </div>
            
            <div style="flex: 1;">
                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php esc_html_e('Value', 'bootflow-product-importer'); ?></label>
                <input type="text" name="import_filters[${filterRuleCounter}][value]" placeholder="<?php esc_html_e('Comparison value', 'bootflow-product-importer'); ?>" style="width: 100%;" />
            </div>
            
            <div style="flex: 0 0 40px;">
                <label style="display: block; font-size: 11px; color: transparent; margin-bottom: 3px;">.</label>
                <button type="button" class="button button-small" onclick="removeFilterRule(event)" style="padding: 6px 10px;">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    filterRuleCounter++;
    
    // Show logic toggle if more than one filter
    const filterCount = container.querySelectorAll('.filter-rule-row').length;
    const logicToggle = document.getElementById('filter-logic-toggle');
    if (filterCount > 1) {
        logicToggle.style.display = '';
    }
}

function removeFilterRule(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const row = e.target.closest('.filter-rule-row');
    const container = document.getElementById('import-filters-container');
    
    row.remove();
    
    const filterCount = container.querySelectorAll('.filter-rule-row').length;
    const logicToggle = document.getElementById('filter-logic-toggle');
    
    if (filterCount <= 1) {
        logicToggle.style.display = 'none';
    }
    
    if (filterCount === 0) {
        container.innerHTML = '<p class="no-filters" style="padding: 15px; color: #666; text-align: center;"><?php esc_html_e('No filters added. All products will be imported.', 'bootflow-product-importer'); ?></p>';
    }
}

function clearAllMapping() {
    if (confirm('<?php esc_html_e('Are you sure you want to clear all field mappings?', 'bootflow-product-importer'); ?>')) {
        // Clear all source dropdowns
        document.querySelectorAll('select[name^="field_mapping"]').forEach(select => {
            if (select.name.includes('[source]')) {
                select.value = '';
            }
        });
        // Clear custom fields
        document.getElementById('custom-fields-container').innerHTML = '';
    }
}

// Collapse all sections by default except first
document.addEventListener('DOMContentLoaded', function() {
    console.log('Import edit page loaded');
    
    const sections = document.querySelectorAll('.mapping-section');
    console.log('Found sections:', sections.length);
    
    sections.forEach((section, index) => {
        if (index > 0) {
            section.classList.add('collapsed');
        }
    });
    
    // Add click handlers to section toggles
    document.querySelectorAll('.section-toggle').forEach((toggle, idx) => {
        console.log('Adding listener to toggle', idx, toggle);
        
        toggle.addEventListener('click', function(e) {
            console.log('Section toggle clicked', e.target);
            
            // Don't toggle if clicking on button inside toggle
            if (e.target.tagName === 'BUTTON' || e.target.closest('button')) {
                console.log('Button clicked, ignoring toggle');
                return;
            }
            
            const section = this.closest('.mapping-section');
            if (!section) {
                console.log('ERROR: No .mapping-section found!');
                return;
            }
            
            const wasCollapsed = section.classList.contains('collapsed');
            section.classList.toggle('collapsed');
            
            console.log('Section toggled. Was collapsed:', wasCollapsed, 'Now collapsed:', section.classList.contains('collapsed'));
            
            // Force visibility check and update
            const fieldsDiv = section.querySelector('.section-fields');
            if (fieldsDiv) {
                if (section.classList.contains('collapsed')) {
                    fieldsDiv.style.display = 'none';
                } else {
                    fieldsDiv.style.display = 'block';
                }
                console.log('Fields div display:', window.getComputedStyle(fieldsDiv).display);
            }
        });
        
        // Make it clear it's clickable
        toggle.style.cursor = 'pointer';
    });
    
    // Handle processing mode changes
    document.querySelectorAll('.processing-mode-select').forEach(select => {
        select.addEventListener('change', function() {
            const row = this.closest('.field-mapping-row');
            if (!row) return;
            
            const configDiv = row.querySelector('.processing-config');
            if (!configDiv) return;
            
            const mode = this.value;
            
            if (mode === 'direct') {
                configDiv.style.display = 'none';
            } else {
                configDiv.style.display = 'block';
                
                // Show/hide relevant config panels
                configDiv.querySelectorAll('.config-panel').forEach(panel => {
                    panel.style.display = 'none';
                });
                
                const activePanel = configDiv.querySelector('.' + mode.replace('_', '-') + '-config');
                if (activePanel) {
                    activePanel.style.display = 'block';
                }
            }
        });
    });
    
    // Handle toggle config button
    document.querySelectorAll('.toggle-config').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const row = this.closest('.field-mapping-row');
            if (!row) return;
            
            const configDiv = row.querySelector('.processing-config');
            if (!configDiv) return;
            
            if (configDiv.style.display === 'none' || !configDiv.style.display) {
                configDiv.style.display = 'block';
            } else {
                configDiv.style.display = 'none';
            }
        });
    });
    
    // Handle clear mapping button
    document.querySelectorAll('.clear-mapping').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('<?php esc_html_e('Clear this field mapping?', 'bootflow-product-importer'); ?>')) {
                const row = this.closest('.field-mapping-row');
                if (!row) return;
                
                // Support both old select and new textarea UI
                const sourceSelect = row.querySelector('.field-source-select');
                const sourceTextarea = row.querySelector('.field-mapping-textarea');
                const modeSelect = row.querySelector('.processing-mode-select');
                const configDiv = row.querySelector('.processing-config');
                
                if (sourceSelect) sourceSelect.value = '';
                if (sourceTextarea) {
                    sourceTextarea.value = '';
                    sourceTextarea.dispatchEvent(new Event('input')); // Trigger input to update preview
                }
                if (modeSelect) modeSelect.value = 'direct';
                if (configDiv) configDiv.style.display = 'none';
                
                // Clear all config inputs
                row.querySelectorAll('textarea:not(.field-mapping-textarea)').forEach(ta => ta.value = '');
            }
        });
    });
    
    // Populate existing filter field dropdowns when file structure loads
    // This is triggered by admin.js after AJAX loads the structure
    window.populateExistingFilterDropdowns = function() {
        document.querySelectorAll('.filter-field-select').forEach(select => {
            if (select.options.length <= 1 && window.currentFileStructure) {
                const selectedValue = select.getAttribute('data-selected') || '';
                let options = '<option value="">-- Select Field --</option>';
                window.currentFileStructure.forEach(field => {
                    const selected = field.path === selectedValue ? ' selected' : '';
                    options += `<option value="${field.path}"${selected}>${field.path}</option>`;
                });
                select.innerHTML = options;
            }
        });
    };
    
    // Toggle missing products options visibility in edit mode
    const handleMissingCheckbox = document.getElementById('handle_missing_edit');
    if (handleMissingCheckbox) {
        handleMissingCheckbox.addEventListener('change', function() {
            const optionsDiv = document.getElementById('missing-products-options-edit');
            if (optionsDiv) {
                optionsDiv.style.display = this.checked ? 'block' : 'none';
            }
        });
    }
    
    // Toggle schedule method row visibility based on schedule type
    const scheduleTypeSelect = document.getElementById('schedule_type_edit');
    if (scheduleTypeSelect) {
        scheduleTypeSelect.addEventListener('change', function() {
            const methodRow = document.getElementById('schedule_method_row');
            const cronInstructions = document.getElementById('server_cron_instructions');
            if (methodRow) {
                methodRow.style.display = (this.value === 'none' || this.value === '') ? 'none' : '';
            }
            if (cronInstructions && this.value === 'none') {
                cronInstructions.style.display = 'none';
            }
            // Update hidden field
            const hiddenField = document.querySelector('input[name="schedule_type_hidden"]');
            if (hiddenField) {
                hiddenField.value = this.value;
            }
        });
    }
    
    // Toggle server cron instructions and update label styling
    document.querySelectorAll('input[name="schedule_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const cronInstructions = document.getElementById('server_cron_instructions');
            
            // Update label styling
            document.querySelectorAll('input[name="schedule_method"]').forEach(r => {
                const label = r.closest('label');
                if (label) {
                    if (r.checked) {
                        label.style.borderColor = '#0073aa';
                        label.style.background = '#f0f6fc';
                    } else {
                        label.style.borderColor = '#ddd';
                        label.style.background = '#fff';
                    }
                }
            });
            
            // Show/hide server cron instructions
            if (cronInstructions) {
                cronInstructions.style.display = (this.value === 'server_cron') ? 'block' : 'none';
            }
            
            // Update hidden field
            const hiddenField = document.querySelector('input[name="schedule_method_hidden"]');
            if (hiddenField) {
                hiddenField.value = this.value;
            }
        });
    });
});
</script>
```
