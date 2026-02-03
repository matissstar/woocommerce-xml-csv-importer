<?php
/**
 * Step 2: Field Mapping Interface
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current tier and feature availability
$current_tier = WC_XML_CSV_AI_Import_License::get_tier();
$can_smart_mapping = WC_XML_CSV_AI_Import_License::can('mapping_auto_php_js');
$can_ai_mapping = WC_XML_CSV_AI_Import_License::can('mapping_auto_ai');
$can_templates = WC_XML_CSV_AI_Import_License::can('templates');
$can_selective_update = WC_XML_CSV_AI_Import_License::can('selective_update');
$can_filters_advanced = WC_XML_CSV_AI_Import_License::can('filters_advanced');
// Note: No product count limits - both FREE and PRO have unlimited products

// Get parameters from URL
$import_id = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

// Load import data from database
global $wpdb;
$table_name = $wpdb->prefix . 'wc_itp_imports';
$import = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $import_id), ARRAY_A);

if (!$import) {
    echo '<div class="notice notice-error"><p>' . __('Import not found. Please start over.', 'wc-xml-csv-import') . '</p></div>';
    return;
}

// Extract variables from import record
$file_path = $import['file_path'];
$file_url = $import['file_url'] ?? '';
$file_type = $import['file_type'];
$import_name = $import['name'];
$schedule_type = $import['schedule_type'] ?? 'disabled';
$product_wrapper = $import['product_wrapper'];
$update_existing = $import['update_existing'];
$skip_unchanged = $import['skip_unchanged'];
$total_products_from_session = $import['total_products'];

// Check if scheduling is available (PRO feature + URL source)
$can_scheduling = WC_XML_CSV_AI_Import_License::can('scheduling');
$is_url_source = !empty($file_url);

// Load saved mappings if exists
$saved_mappings = array();
$saved_custom_fields = array();
if (!empty($import['field_mappings'])) {
    $saved_mappings = json_decode($import['field_mappings'], true);
    if (!is_array($saved_mappings)) {
        $saved_mappings = array();
    }
}

// Extract custom fields from saved mappings
// Custom fields are stored with 'custom_' prefix in field_mappings
foreach ($saved_mappings as $field_key => $field_data) {
    if (strpos($field_key, 'custom_') === 0 && is_array($field_data)) {
        $saved_custom_fields[] = $field_data;
    }
}

if (empty($file_path) || !file_exists($file_path)) {
    echo '<div class="notice notice-error"><p>' . __('Invalid file path. Please start over.', 'wc-xml-csv-import') . '</p></div>';
    return;
}

// WooCommerce target fields
$woocommerce_fields = array(
    'basic' => array(
        'title' => __('Basic Product Fields', 'wc-xml-csv-import'),
        'fields' => array(
            'sku' => array(
                'label' => __('Product Code (SKU)', 'wc-xml-csv-import'), 
                'required' => true, 
                'type' => 'sku_with_generate',
                'description' => __('Unique product identifier. Can be mapped from file or auto-generated.', 'wc-xml-csv-import')
            ),
            'name' => array('label' => __('Product Name', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'type' => array(
                'label' => __('Product Type', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'product_type_select',
                'options' => array(
                    'simple' => __('Simple', 'wc-xml-csv-import'),
                    'variable' => __('Variable', 'wc-xml-csv-import'),
                    'grouped' => __('Grouped', 'wc-xml-csv-import'),
                    'external' => __('External/Affiliate', 'wc-xml-csv-import'),
                ),
                'description' => __('Product type. Auto-detected if grouped_products or external_url is mapped.', 'wc-xml-csv-import')
            ),
            'description' => array('label' => __('Description', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'short_description' => array('label' => __('Short Description', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'status' => array(
                'label' => __('Product Status', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'status_select',
                'options' => array(
                    'publish' => __('Published', 'wc-xml-csv-import'),
                    'draft' => __('Draft', 'wc-xml-csv-import'),
                    'pending' => __('Pending Review', 'wc-xml-csv-import'),
                    'private' => __('Private', 'wc-xml-csv-import'),
                )
            ),
        )
    ),
    'pricing_engine' => array(
        'title' => __('Price Markup', 'wc-xml-csv-import'),
        'fields' => array(),
        'custom_content' => true, // Flag for custom rendering
    ),
    'pricing' => array(
        'title' => __('Pricing Fields', 'wc-xml-csv-import'),
        'fields' => array(
            'regular_price' => array('label' => __('Regular Price', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'sale_price' => array('label' => __('Sale Price', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'sale_price_dates_from' => array('label' => __('Sale Price From Date', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text', 'description' => 'Format: YYYY-MM-DD'),
            'sale_price_dates_to' => array('label' => __('Sale Price To Date', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text', 'description' => 'Format: YYYY-MM-DD'),
            'tax_status' => array(
                'label' => __('Tax Status', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'tax_status_select',
                'options' => array(
                    'taxable' => __('Taxable', 'wc-xml-csv-import'),
                    'shipping' => __('Shipping only', 'wc-xml-csv-import'),
                    'none' => __('None', 'wc-xml-csv-import'),
                )
            ),
            'tax_class' => array(
                'label' => __('Tax Class', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'tax_class_select'
            ),
        )
    ),
    'inventory' => array(
        'title' => __('Inventory Fields', 'wc-xml-csv-import'),
        'fields' => array(
            'manage_stock' => array(
                'label' => __('Manage Stock', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'stock_quantity' => array('label' => __('Stock Quantity', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'stock_status' => array(
                'label' => __('Stock Status', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'stock_status_select',
                'options' => array(
                    'instock' => __('In stock', 'wc-xml-csv-import'),
                    'outofstock' => __('Out of stock', 'wc-xml-csv-import'),
                    'onbackorder' => __('On backorder', 'wc-xml-csv-import'),
                )
            ),
            'backorders' => array(
                'label' => __('Allow Backorders', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'backorders_select',
                'options' => array(
                    'no' => __('Do not allow', 'wc-xml-csv-import'),
                    'notify' => __('Allow, but notify customer', 'wc-xml-csv-import'),
                    'yes' => __('Allow', 'wc-xml-csv-import'),
                )
            ),
        )
    ),
    'physical' => array(
        'title' => __('Physical Properties', 'wc-xml-csv-import'),
        'fields' => array(
            'weight' => array('label' => __('Weight', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'length' => array('label' => __('Length', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'width' => array('label' => __('Width', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'height' => array('label' => __('Height', 'wc-xml-csv-import'), 'required' => false, 'type' => 'number'),
            'shipping_class' => array('label' => __('Shipping Class', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text', 'description' => 'Slug of shipping class (e.g., "fragile", "heavy")'),
        )
    ),
    'shipping_class_assignment' => array(
        'title' => __('Shipping Class Assignment', 'wc-xml-csv-import'),
        'fields' => array(
            'shipping_class_formula' => array('label' => __('Shipping Class Formula', 'wc-xml-csv-import'), 'required' => false, 'type' => 'formula'),
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
            'featured_image' => array('label' => __('Featured Image', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
        )
    ),
    'taxonomy' => array(
        'title' => __('Categories & Tags', 'wc-xml-csv-import'),
        'fields' => array(
            'categories' => array('label' => __('Product Categories', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'tags' => array('label' => __('Product Tags', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'brand' => array('label' => __('Brand', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
        )
    ),
    'product_options' => array(
        'title' => __('Product Options', 'wc-xml-csv-import'),
        'fields' => array(
            'featured' => array(
                'label' => __('Featured Product', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'virtual' => array(
                'label' => __('Virtual Product', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'downloadable' => array(
                'label' => __('Downloadable', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'sold_individually' => array(
                'label' => __('Sold Individually', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'boolean'
            ),
            'reviews_allowed' => array(
                'label' => __('Reviews Allowed', 'wc-xml-csv-import'), 
                'required' => false, 
                'type' => 'boolean'
            ),
        )
    ),
    'download_settings' => array(
        'title' => __('Download Settings', 'wc-xml-csv-import'),
        'fields' => array(
            'download_limit' => array('label' => __('Download Limit', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'download_expiry' => array('label' => __('Download Expiry (days)', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
        )
    ),
    'product_identifiers' => array(
        'title' => __('Product Identifiers', 'wc-xml-csv-import'),
        'fields' => array(
            'ean' => array('label' => __('EAN', 'wc-xml-csv-import'), 'required' => false, 'type' => 'identifier'),
            'upc' => array('label' => __('UPC', 'wc-xml-csv-import'), 'required' => false, 'type' => 'identifier'),
            'isbn' => array('label' => __('ISBN', 'wc-xml-csv-import'), 'required' => false, 'type' => 'identifier'),
            'mpn' => array('label' => __('MPN', 'wc-xml-csv-import'), 'required' => false, 'type' => 'identifier'),
            'gtin' => array('label' => __('GTIN', 'wc-xml-csv-import'), 'required' => false, 'type' => 'identifier'),
        )
    ),
    'linked_products' => array(
        'title' => __('Linked Products', 'wc-xml-csv-import'),
        'fields' => array(
            'upsell_ids' => array('label' => __('Upsell Product IDs', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'cross_sell_ids' => array('label' => __('Cross-sell Product IDs', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'grouped_products' => array('label' => __('Grouped Products', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'parent_id' => array('label' => __('Parent Product ID', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
        )
    ),

    'advanced' => array(
        'title' => __('Advanced Fields', 'wc-xml-csv-import'),
        'fields' => array(
            'purchase_note' => array('label' => __('Purchase Note', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'menu_order' => array('label' => __('Menu Order', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'button_text' => array('label' => __('Button Text', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'external_url' => array('label' => __('External URL', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
        )
    ),
    'seo' => array(
        'title' => __('SEO Fields', 'wc-xml-csv-import'),
        'fields' => array(
            'meta_title' => array('label' => __('Meta Title', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'meta_description' => array('label' => __('Meta Description', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
            'meta_keywords' => array('label' => __('Meta Keywords', 'wc-xml-csv-import'), 'required' => false, 'type' => 'text'),
        )
    ),
    'attributes_variations' => array(
        'title' => __('Attributes & Variations', 'wc-xml-csv-import'),
        'fields' => array() // Will be handled separately with custom UI
    )
);

$settings = get_option('wc_xml_csv_ai_import_settings', array());
$ai_providers = array(
    'openai' => 'OpenAI GPT',
    'gemini' => 'Google Gemini',
    'claude' => 'Anthropic Claude',
    'grok' => 'xAI Grok',
    'copilot' => 'Microsoft Copilot'
);
?>

<div class="wc-ai-import-step wc-ai-import-step-2">
    <div class="wc-ai-import-layout">
        <!-- Left Sidebar - File Structure -->
        <div class="wc-ai-import-sidebar">
            <div class="wc-ai-import-card">
                <h3><?php _e('File Structure', 'wc-xml-csv-import'); ?></h3>
                
                <!-- File Info Grid -->
                <div class="file-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 15px; padding: 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e2e4e7;">
                    <div class="file-info-item" style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">📄 <?php _e('File', 'wc-xml-csv-import'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e; word-break: break-all;" title="<?php echo esc_attr(basename($file_path)); ?>"><?php 
                            $filename = basename($file_path);
                            echo strlen($filename) > 25 ? substr($filename, 0, 22) . '...' : esc_html($filename); 
                        ?></span>
                    </div>
                    <div class="file-info-item" style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">📦 <?php _e('Type', 'wc-xml-csv-import'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e;"><?php echo strtoupper($file_type); ?></span>
                    </div>
                    <div class="file-info-item" style="display: flex; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">🏷️ <?php _e('Import', 'wc-xml-csv-import'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e;" title="<?php echo esc_attr($import_name); ?>"><?php 
                            echo strlen($import_name) > 20 ? substr($import_name, 0, 17) . '...' : esc_html($import_name); 
                        ?></span>
                    </div>
                    <div class="file-info-item" id="total-products-info" style="display: none; flex-direction: column; gap: 2px;">
                        <span style="font-size: 10px; text-transform: uppercase; color: #666; font-weight: 600;">🛒 <?php _e('Products', 'wc-xml-csv-import'); ?></span>
                        <span style="font-size: 12px; color: #1e1e1e; font-weight: 600;" id="total-products-count">-</span>
                    </div>
                </div>
                
                <!-- Unique Fields Info (injected by JS) -->
                <div id="fields-info-container"></div>
                
                <div id="file-structure-browser">
                    <div class="structure-loader">
                        <div class="spinner is-active"></div>
                        <p><?php _e('Loading file structure...', 'wc-xml-csv-import'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Hidden sample data container for JS compatibility -->
            <div id="sample-data-preview" style="display: none;"></div>
        </div>

        <!-- Main Content - Field Mapping -->
        <div class="wc-ai-import-main">
            <!-- Import Behavior Options (moved up for visibility) -->
            <div class="wc-ai-import-card" style="margin-bottom: 20px;">
                <h2>⚙️ <?php _e('Import Behavior', 'wc-xml-csv-import'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Update Existing Products', 'wc-xml-csv-import'); ?></th>
                        <td>
                            <label style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" name="update_existing" id="update_existing" value="1" <?php checked($update_existing, '1'); ?> style="margin-top: 3px;" />
                                <div>
                                    <strong><?php _e('Update products that already exist (matched by SKU)', 'wc-xml-csv-import'); ?></strong>
                                    <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                        <?php _e('When enabled, existing products with matching SKUs will be updated instead of creating duplicates.', 'wc-xml-csv-import'); ?>
                                    </p>
                                </div>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Skip Unchanged Products', 'wc-xml-csv-import'); ?></th>
                        <td>
                            <label style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" name="skip_unchanged" id="skip_unchanged" value="1" <?php checked($skip_unchanged, '1'); ?> style="margin-top: 3px;" />
                                <div>
                                    <strong><?php _e('Skip products if data unchanged', 'wc-xml-csv-import'); ?></strong>
                                    <p class="description" style="margin-top: 5px; margin-bottom: 0;">
                                        <?php _e('Reduces import time by skipping products that haven\'t changed.', 'wc-xml-csv-import'); ?>
                                    </p>
                                </div>
                            </label>
                        </td>
                    </tr>
                    
                    <?php if ($is_url_source): ?>
                    <!-- Scheduled Import - only for URL sources -->
                    <tr>
                        <th scope="row">
                            <?php _e('Scheduled Import', 'wc-xml-csv-import'); ?>
                            <?php if (!$can_scheduling): ?>
                            <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: bold;">PRO</span>
                            <?php endif; ?>
                        </th>
                        <td>
                            <?php if ($can_scheduling): ?>
                            <select name="schedule_type" id="schedule_type" class="regular-text">
                                <option value="disabled" <?php selected($schedule_type, 'disabled'); ?>><?php _e('Manual Import Only', 'wc-xml-csv-import'); ?></option>
                                <option value="15min" <?php selected($schedule_type, '15min'); ?>><?php _e('Every 15 Minutes', 'wc-xml-csv-import'); ?></option>
                                <option value="hourly" <?php selected($schedule_type, 'hourly'); ?>><?php _e('Every Hour', 'wc-xml-csv-import'); ?></option>
                                <option value="6hours" <?php selected($schedule_type, '6hours'); ?>><?php _e('Every 6 Hours', 'wc-xml-csv-import'); ?></option>
                                <option value="daily" <?php selected($schedule_type, 'daily'); ?>><?php _e('Daily Import', 'wc-xml-csv-import'); ?></option>
                                <option value="weekly" <?php selected($schedule_type, 'weekly'); ?>><?php _e('Weekly Import', 'wc-xml-csv-import'); ?></option>
                                <option value="monthly" <?php selected($schedule_type, 'monthly'); ?>><?php _e('Monthly Import', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <p class="description">
                                <?php _e('Automatically re-import from URL on a schedule. Requires server cron to be configured.', 'wc-xml-csv-import'); ?>
                                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import-settings&tab=scheduling'); ?>" target="_blank"><?php _e('View cron setup instructions', 'wc-xml-csv-import'); ?> →</a>
                            </p>
                            <?php else: ?>
                            <select disabled class="regular-text" style="background: #f5f5f5;">
                                <option><?php echo ($schedule_type && $schedule_type !== 'disabled') ? esc_html($schedule_type) : __('Manual Import Only', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <p class="description" style="color: #6c757d;">
                                <?php _e('Automated scheduled imports are available in the PRO version.', 'wc-xml-csv-import'); ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Schedule Method (PRO only, shown when schedule is enabled) -->
                    <?php if ($can_scheduling): ?>
                    <tr id="schedule_method_row_new" style="<?php echo ($schedule_type === 'disabled' || empty($schedule_type)) ? 'display:none;' : ''; ?>">
                        <th scope="row"><?php _e('Schedule Method', 'wc-xml-csv-import'); ?></th>
                        <td>
                            <?php 
                            $schedule_method = $import['schedule_method'] ?? 'action_scheduler';
                            $global_settings = get_option('wc_xml_csv_ai_import_settings', array());
                            $cron_secret = $global_settings['cron_secret_key'] ?? '';
                            $cron_url_new = admin_url('admin-ajax.php') . '?action=wc_xml_csv_ai_import_cron&secret=' . $cron_secret;
                            ?>
                            <fieldset>
                                <label style="display: block; margin-bottom: 10px; padding: 10px; border: 2px solid <?php echo $schedule_method === 'action_scheduler' ? '#0073aa' : '#ddd'; ?>; border-radius: 6px; cursor: pointer; background: <?php echo $schedule_method === 'action_scheduler' ? '#f0f6fc' : '#fff'; ?>;">
                                    <input type="radio" name="schedule_method" value="action_scheduler" <?php checked($schedule_method, 'action_scheduler'); ?>>
                                    <strong><?php _e('Action Scheduler', 'wc-xml-csv-import'); ?></strong>
                                    <span style="background: #28a745; color: white; font-size: 10px; padding: 2px 6px; border-radius: 8px; margin-left: 6px;"><?php _e('Recommended', 'wc-xml-csv-import'); ?></span>
                                    <p class="description" style="margin: 5px 0 0 20px;">
                                        <?php _e('Automatically continues until complete. No server cron needed.', 'wc-xml-csv-import'); ?>
                                    </p>
                                </label>
                                
                                <label style="display: block; padding: 10px; border: 2px solid <?php echo $schedule_method === 'server_cron' ? '#0073aa' : '#ddd'; ?>; border-radius: 6px; cursor: pointer; background: <?php echo $schedule_method === 'server_cron' ? '#f0f6fc' : '#fff'; ?>;">
                                    <input type="radio" name="schedule_method" value="server_cron" <?php checked($schedule_method, 'server_cron'); ?>>
                                    <strong><?php _e('Server Cron', 'wc-xml-csv-import'); ?></strong>
                                    <p class="description" style="margin: 5px 0 0 20px;">
                                        <?php _e('Processes entire import in one request. Requires server cron setup.', 'wc-xml-csv-import'); ?>
                                    </p>
                                </label>
                            </fieldset>
                            
                            <!-- Server Cron URL (shown when server_cron selected) -->
                            <div id="server_cron_url_new" style="<?php echo $schedule_method !== 'server_cron' ? 'display:none;' : ''; ?> margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                <strong><?php _e('Cron URL:', 'wc-xml-csv-import'); ?></strong>
                                <input type="text" value="<?php echo esc_attr($cron_url_new); ?>" readonly class="large-text" style="font-size: 11px; margin-top: 5px;" />
                                <button type="button" class="button button-small" style="margin-top: 5px;" onclick="navigator.clipboard.writeText('<?php echo esc_js($cron_url_new); ?>'); alert('Copied!');">
                                    <?php _e('Copy URL', 'wc-xml-csv-import'); ?>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Batch Size -->
                    <tr>
                        <th scope="row"><?php _e('Batch Size', 'wc-xml-csv-import'); ?></th>
                        <td>
                            <?php $batch_size = $import['batch_size'] ?? 50; ?>
                            <input type="number" name="batch_size" id="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1" max="500" style="width: 100px;">
                            <span class="description"><?php _e('Products per chunk (1-500). Higher = faster, but more memory.', 'wc-xml-csv-import'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="wc-ai-import-card">
                <h2><?php _e('Step 2: Field Mapping & Processing', 'wc-xml-csv-import'); ?></h2>
                <p class="description"><?php _e('Map your file fields to WooCommerce product fields and configure processing modes.', 'wc-xml-csv-import'); ?></p>
                
                <form id="wc-ai-import-mapping-form" method="post">
                    <?php wp_nonce_field('wc_xml_csv_ai_import_nonce', 'nonce'); ?>
                    
                    <!-- Hidden fields -->
                    <input type="hidden" name="file_path" value="<?php echo esc_attr($file_path); ?>" />
                    <input type="hidden" name="file_type" value="<?php echo esc_attr($file_type); ?>" />
                    <input type="hidden" name="product_wrapper" value="<?php echo esc_attr($product_wrapper); ?>" />
                    <?php if (!$is_url_source): ?>
                    <input type="hidden" name="schedule_type" value="<?php echo esc_attr($schedule_type); ?>" />
                    <?php endif; ?>
                    <input type="hidden" name="import_name" value="<?php echo esc_attr($import_name); ?>" />
                    
                    <!-- Mapping Templates & Auto-detect -->
                    <?php if ($can_templates): ?>
                    <div class="mapping-recipes-section" style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 15px; margin-bottom: 20px;">
                        <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #1e1e1e;">
                            <span class="dashicons dashicons-saved" style="color: #0073aa;"></span>
                            <?php _e('Mapping Templates', 'wc-xml-csv-import'); ?>
                        </h4>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                            <!-- Save Template -->
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 4px;">
                                    <?php _e('Save current mapping as template:', 'wc-xml-csv-import'); ?>
                                </label>
                                <div style="display: flex; gap: 5px;">
                                    <input type="text" 
                                           id="recipe-name-input" 
                                           placeholder="<?php esc_attr_e('Enter template name...', 'wc-xml-csv-import'); ?>"
                                           style="flex: 1; min-width: 150px; height: 32px;">
                                    <button type="button" class="button" id="save-recipe-btn" title="<?php esc_attr_e('Save Template', 'wc-xml-csv-import'); ?>">
                                        <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                                        <?php _e('Save', 'wc-xml-csv-import'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Load Template -->
                            <div style="flex: 1; min-width: 200px;">
                                <label style="display: block; font-size: 11px; color: #666; margin-bottom: 4px;">
                                    <?php _e('Load saved template:', 'wc-xml-csv-import'); ?>
                                </label>
                                <div style="display: flex; gap: 5px;">
                                    <select id="recipe-select" style="flex: 1; min-width: 150px; height: 32px;">
                                        <option value=""><?php _e('-- Select template --', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <button type="button" class="button" id="load-recipe-btn" title="<?php esc_attr_e('Load Template', 'wc-xml-csv-import'); ?>">
                                        <span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
                                        <?php _e('Load', 'wc-xml-csv-import'); ?>
                                    </button>
                                    <button type="button" class="button" id="delete-recipe-btn" title="<?php esc_attr_e('Delete Template', 'wc-xml-csv-import'); ?>" style="color: #a00;">
                                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status message -->
                        <div id="recipe-status-message" style="display: none; margin-top: 10px; padding: 8px 12px; border-radius: 4px; font-size: 13px;"></div>
                    </div>
                    <?php else: ?>
                    <!-- FREE version: Show PRO upgrade notice for Mapping Templates -->
                    <div class="mapping-recipes-section pro-feature-notice" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 6px; padding: 15px; margin-bottom: 20px; opacity: 0.85;">
                        <h4 style="margin: 0 0 8px 0; font-size: 14px; color: #6c757d;">
                            <span class="dashicons dashicons-saved" style="color: #adb5bd;"></span>
                            <?php _e('Mapping Templates', 'wc-xml-csv-import'); ?>
                            <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: bold;">PRO</span>
                        </h4>
                        <p style="margin: 0; color: #6c757d; font-size: 13px;">
                            <?php _e('Save and reuse mapping configurations across imports. Available in PRO version.', 'wc-xml-csv-import'); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // Check if AI API key is configured for AI auto-mapping
                    $ai_settings = get_option('wc_xml_csv_ai_import_settings', array());
                    $has_openai = !empty($ai_settings['ai_api_keys']['openai']);
                    $has_claude = !empty($ai_settings['ai_api_keys']['claude']);
                    $has_gemini = !empty($ai_settings['ai_api_keys']['gemini']);
                    $has_any_ai = $has_openai || $has_claude || $has_gemini;
                    ?>
                    
                    <!-- ═══════════════════════════════════════════════════════════════ -->
                    <!-- AI Auto-Mapping - Minimal, non-intrusive block                -->
                    <!-- ═══════════════════════════════════════════════════════════════ -->
                    <?php if ($can_ai_mapping): ?>
                    <div class="smart-mapping-options" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 15px 20px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                            <div>
                                <h4 style="margin: 0 0 5px 0; font-size: 14px; color: #495057; font-weight: 600;">
                                    <?php _e('AI Auto-Mapping', 'wc-xml-csv-import'); ?>
                                </h4>
                                <p style="margin: 0; font-size: 13px; color: #6c757d;">
                                    <?php _e('Let AI automatically match your file columns to WooCommerce fields.', 'wc-xml-csv-import'); ?>
                                </p>
                            </div>
                            
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if ($has_any_ai): ?>
                                <!-- AI Auto-Map Button -->
                                <button type="button" id="btn-ai-auto-map" class="button button-primary" style="display: flex; align-items: center; gap: 5px;">
                                    <span class="dashicons dashicons-lightbulb" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    <?php _e('AI Auto-Map', 'wc-xml-csv-import'); ?>
                                </button>
                                <select id="ai-mapping-provider" style="height: 30px; border-radius: 4px; padding: 0 8px; font-size: 12px;">
                                    <?php if ($has_openai): ?><option value="openai">OpenAI</option><?php endif; ?>
                                    <?php if ($has_claude): ?><option value="claude">Claude</option><?php endif; ?>
                                    <?php if ($has_gemini): ?><option value="gemini">Gemini</option><?php endif; ?>
                                </select>
                                <?php else: ?>
                                <span style="color: #6c757d; font-size: 12px;">
                                    <?php _e('Configure AI provider in Settings to enable auto-mapping', 'wc-xml-csv-import'); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- AI Mapping Status container (hidden by default) -->
                        <div id="ai-mapping-status" style="display: none; margin-top: 12px; padding: 10px; background: #e9ecef; border-radius: 4px; font-size: 13px;">
                            <div id="ai-mapping-progress" style="display: none;">
                                <span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>
                                <span id="ai-mapping-progress-text"><?php _e('AI analyzing fields...', 'wc-xml-csv-import'); ?></span>
                            </div>
                            <div id="ai-mapping-result" style="display: none;"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Auto-Mapping Warning (shown after any auto-mapping) -->
                    <div id="auto-mapping-warning" style="display: none; background: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 12px 15px; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 20px;">⚠️</span>
                            <div style="flex: 1;">
                                <strong style="color: #856404;"><?php _e('Please verify mapped fields before importing.', 'wc-xml-csv-import'); ?></strong>
                            </div>
                            <button type="button" id="btn-confirm-mapping" class="button button-small">
                                <?php _e('OK', 'wc-xml-csv-import'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="mapping-actions">
                        <button type="button" class="button button-secondary" id="clear-all-mapping">
                            <?php _e('Clear All', 'wc-xml-csv-import'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="test-mapping">
                            <?php _e('Test Mapping', 'wc-xml-csv-import'); ?>
                        </button>
                    </div>
                    
                    <!-- Field Mapping Sections -->
                    <div class="field-mapping-sections">
                        <?php foreach ($woocommerce_fields as $section_key => $section): ?>
                            <div class="mapping-section" data-section="<?php echo $section_key; ?>">
                                <h3 class="section-toggle" data-target="<?php echo $section_key; ?>">
                                    <span class="dashicons dashicons-arrow-down"></span>
                                    <?php echo $section['title']; ?>
                                    <span class="mapped-count">0/<?php echo count($section['fields']); ?></span>
                                </h3>
                                
                                <div class="section-fields" id="section-<?php echo $section_key; ?>">
                                    <?php if ($section_key === 'pricing_engine'): ?>
                                        <?php 
                                        // Load features config for PRO check
                                        if (!function_exists('wc_xml_csv_ai_import_is_pro')) {
                                            require_once dirname(dirname(dirname(__FILE__))) . '/config/features.php';
                                        }
                                        $is_pro = wc_xml_csv_ai_import_is_pro();
                                        ?>
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <!-- PRICING ENGINE - Calculate prices before mapping                 -->
                                        <!-- Pipeline: XML Base Price → Price Markup → Regular Price       -->
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <div class="pricing-engine-container" style="padding: 20px;">
                                            
                                            <!-- Enable/Disable Toggle -->
                                            <div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-radius: 8px; border-left: 4px solid #ff9800;">
                                                <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                                                    <input type="checkbox" id="pricing_engine_enabled" name="pricing_engine_enabled" value="1" style="width: 20px; height: 20px;">
                                                    <span>
                                                        <strong style="font-size: 15px; color: #e65100;"><?php _e('Enable Price Markup', 'wc-xml-csv-import'); ?></strong>
                                                        <small style="display: block; color: #bf360c; margin-top: 3px;">
                                                            <?php _e('Calculate final prices by applying markup rules to the base price from XML', 'wc-xml-csv-import'); ?>
                                                        </small>
                                                    </span>
                                                </label>
                                            </div>
                                            
                                            <!-- Price Markup Settings (shown when enabled) -->
                                            <div id="pricing-engine-settings" style="display: none;">
                                                
                                                <!-- Info Box -->
                                                <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                                        <span style="font-size: 24px;">💡</span>
                                                        <div>
                                                            <strong style="color: #1565c0;"><?php _e('How it works:', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #1976d2; font-size: 13px; line-height: 1.6;">
                                                                <?php _e('XML Base Price → Apply Matching Rules → Apply Rounding → Final Price', 'wc-xml-csv-import'); ?><br>
                                                                <?php _e('Rules are evaluated from top to bottom. First matching rule wins (or use "Apply All" mode).', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Base Price Source -->
                                                <div style="margin-bottom: 20px; padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px;">
                                                    <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333; font-size: 14px;">
                                                        <span class="dashicons dashicons-tag" style="color: #ff9800;"></span>
                                                        <?php _e('Base Price Source (from XML):', 'wc-xml-csv-import'); ?>
                                                    </label>
                                                    <select id="pricing_engine_base_price" name="pricing_engine_base_price" class="wc-xml-field-select" style="width: 100%; max-width: 400px; padding: 10px;">
                                                        <option value=""><?php _e('-- Select XML field with base price --', 'wc-xml-csv-import'); ?></option>
                                                    </select>
                                                    <p class="description" style="margin-top: 8px; color: #666;">
                                                        <?php _e('Select the XML field that contains the supplier/wholesale price', 'wc-xml-csv-import'); ?>
                                                    </p>
                                                </div>
                                                
                                                <!-- ═══════════════════════════════════════════════════════════════ -->
                                                <!-- PRICING RULES - Multiple conditional rules                      -->
                                                <!-- ═══════════════════════════════════════════════════════════════ -->
                                                <div style="margin-bottom: 20px;">
                                                    
                                                    <!-- Rule Priority Info Box -->
                                                    <div style="margin-bottom: 15px; padding: 12px 15px; background: #fff8e1; border-radius: 6px; border-left: 4px solid #ffc107; display: flex; align-items: center; gap: 10px;">
                                                        <span style="font-size: 18px;">⚠️</span>
                                                        <div style="font-size: 13px; color: #795548;">
                                                            <strong><?php _e('Rule Priority:', 'wc-xml-csv-import'); ?></strong>
                                                            <?php _e('Rules are evaluated top to bottom. First matching rule applies. Avoid overlapping conditions.', 'wc-xml-csv-import'); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e0e0e0;">
                                                        <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                                            <span class="dashicons dashicons-chart-line" style="color: #4caf50;"></span>
                                                            <?php _e('Pricing Rules', 'wc-xml-csv-import'); ?>
                                                            <span style="font-size: 12px; font-weight: normal; color: #666; margin-left: 10px;">
                                                                <?php _e('(First matching rule wins)', 'wc-xml-csv-import'); ?>
                                                            </span>
                                                        </h4>
                                                        <button type="button" id="btn-add-pricing-rule" class="button button-primary" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php _e('Add Rule', 'wc-xml-csv-import'); ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Rules Container -->
                                                    <div id="pricing-rules-list">
                                                        
                                                        <!-- Default/Fallback Rule (always present) -->
                                                        <div class="pricing-rule-row pricing-rule-default" data-rule-id="default" style="padding: 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border: 2px solid #4caf50; border-radius: 8px; margin-bottom: 15px;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span style="font-size: 20px;">🏠</span>
                                                                    <strong style="color: #2e7d32; font-size: 14px;"><?php _e('Default Rule (Fallback)', 'wc-xml-csv-import'); ?></strong>
                                                                    <span style="font-size: 11px; background: #4caf50; color: white; padding: 2px 8px; border-radius: 10px;">
                                                                        <?php _e('Always applies if no other rule matches', 'wc-xml-csv-import'); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Markup %', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][markup_percent]" value="0" min="-100" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php _e('+ Fixed €', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][fixed_amount]" value="0" min="-10000" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Round to', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <select name="pricing_rule[default][rounding]" style="padding: 8px; border: 2px solid #81c784; border-radius: 4px; min-width: 130px;">
                                                                        <option value="none"><?php _e('No rounding', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.01"><?php _e('0.01', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.05"><?php _e('0.05', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.10"><?php _e('0.10', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.50"><?php _e('0.50', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="1.00"><?php _e('1.00', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.99"><?php _e('.99', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.95"><?php _e('.95', 'wc-xml-csv-import'); ?></option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Min Price €', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][min_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Max Price €', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[default][max_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 2px solid #81c784; border-radius: 4px;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Dynamic rules will be added here -->
                                                        
                                                    </div>
                                                    
                                                    <!-- Rule Template (hidden, cloned by JS) -->
                                                    <template id="pricing-rule-template">
                                                        <div class="pricing-rule-row pricing-rule-conditional" data-rule-id="" style="padding: 20px; background: #fff; border: 2px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; transition: all 0.2s;">
                                                            
                                                            <!-- Rule Header -->
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <span class="rule-drag-handle" style="cursor: move; color: #999; font-size: 18px;">⋮⋮</span>
                                                                    <span class="rule-number" style="background: #ff9800; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600;">1</span>
                                                                    <input type="text" name="pricing_rule[{id}][name]" placeholder="<?php _e('Rule name (optional)', 'wc-xml-csv-import'); ?>" 
                                                                           style="border: none; border-bottom: 1px dashed #ccc; padding: 5px; font-weight: 500; width: 200px;">
                                                                </div>
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <label style="display: flex; align-items: center; gap: 5px; font-size: 12px; color: #666;">
                                                                        <input type="checkbox" name="pricing_rule[{id}][enabled]" checked>
                                                                        <?php _e('Enabled', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <button type="button" class="button-link remove-pricing-rule" style="color: #d63638;">
                                                                        <span class="dashicons dashicons-trash"></span>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Conditions -->
                                                            <div class="rule-conditions" style="margin-bottom: 15px; padding: 15px; background: #fafafa; border-radius: 6px;">
                                                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                                                    <strong style="font-size: 13px; color: #333;">
                                                                        <span class="dashicons dashicons-filter" style="color: #2196f3;"></span>
                                                                        <?php _e('Apply when:', 'wc-xml-csv-import'); ?>
                                                                    </strong>
                                                                    <select name="pricing_rule[{id}][condition_logic]" style="padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                                        <option value="AND"><?php _e('ALL conditions match', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="OR"><?php _e('ANY condition matches', 'wc-xml-csv-import'); ?></option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="conditions-list" style="display: flex; flex-direction: column; gap: 8px;">
                                                                    <!-- Condition row template -->
                                                                    <div class="condition-row" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                                                        <select name="pricing_rule[{id}][conditions][0][type]" class="condition-type" style="padding: 6px; border-radius: 4px; min-width: 140px;">
                                                                            <option value="price_range"><?php _e('Price Range', 'wc-xml-csv-import'); ?></option>
                                                                            <option value="category"><?php _e('📁 Category', 'wc-xml-csv-import'); ?></option>
                                                                            <option value="brand"><?php _e('🏷️ Brand', 'wc-xml-csv-import'); ?></option>
                                                                            <option value="supplier"><?php _e('🏭 Supplier', 'wc-xml-csv-import'); ?></option>
                                                                            <option value="xml_field"><?php _e('📄 XML Field', 'wc-xml-csv-import'); ?></option>
                                                                            <option value="sku_pattern"><?php _e('🔢 SKU Pattern', 'wc-xml-csv-import'); ?></option>
                                                                        </select>
                                                                        
                                                                        <!-- Price Range condition fields -->
                                                                        <div class="condition-fields condition-price_range" style="display: flex; gap: 8px; align-items: center;">
                                                                            <span style="color: #666;"><?php _e('from', 'wc-xml-csv-import'); ?></span>
                                                                            <input type="number" name="pricing_rule[{id}][conditions][0][price_from]" placeholder="0" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #666;"><?php _e('to', 'wc-xml-csv-import'); ?></span>
                                                                            <input type="number" name="pricing_rule[{id}][conditions][0][price_to]" placeholder="∞" min="0" step="0.01" style="width: 80px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                            <span style="color: #888; font-size: 11px;">€</span>
                                                                        </div>
                                                                        
                                                                        <!-- Category condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-category" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][category_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php _e('equals', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="contains"><?php _e('contains', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="not_equals"><?php _e('not equals', 'wc-xml-csv-import'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][category_value]" placeholder="<?php _e('Category name or slug', 'wc-xml-csv-import'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- Brand condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-brand" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][brand_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php _e('equals', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="contains"><?php _e('contains', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="not_equals"><?php _e('not equals', 'wc-xml-csv-import'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][brand_value]" placeholder="<?php _e('Brand name', 'wc-xml-csv-import'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- Supplier condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-supplier" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][supplier_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals"><?php _e('equals', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="contains"><?php _e('contains', 'wc-xml-csv-import'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][supplier_value]" placeholder="<?php _e('Supplier name', 'wc-xml-csv-import'); ?>" style="width: 200px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- XML Field condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-xml_field" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][xml_field_name]" class="xml-field-select" style="padding: 6px; border-radius: 4px; min-width: 120px;">
                                                                                <option value=""><?php _e('-- Field --', 'wc-xml-csv-import'); ?></option>
                                                                            </select>
                                                                            <select name="pricing_rule[{id}][conditions][0][xml_field_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="equals">=</option>
                                                                                <option value="not_equals">≠</option>
                                                                                <option value="contains"><?php _e('contains', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="gt">></option>
                                                                                <option value="lt"><</option>
                                                                                <option value="gte">≥</option>
                                                                                <option value="lte">≤</option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][xml_field_value]" placeholder="<?php _e('Value', 'wc-xml-csv-import'); ?>" style="width: 150px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <!-- SKU Pattern condition fields (hidden by default) -->
                                                                        <div class="condition-fields condition-sku_pattern" style="display: none; gap: 8px; align-items: center;">
                                                                            <select name="pricing_rule[{id}][conditions][0][sku_operator]" style="padding: 6px; border-radius: 4px;">
                                                                                <option value="starts_with"><?php _e('starts with', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="ends_with"><?php _e('ends with', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="contains"><?php _e('contains', 'wc-xml-csv-import'); ?></option>
                                                                                <option value="regex"><?php _e('matches regex', 'wc-xml-csv-import'); ?></option>
                                                                            </select>
                                                                            <input type="text" name="pricing_rule[{id}][conditions][0][sku_value]" placeholder="<?php _e('Pattern', 'wc-xml-csv-import'); ?>" style="width: 150px; padding: 6px; border-radius: 4px; border: 1px solid #ccc;">
                                                                        </div>
                                                                        
                                                                        <button type="button" class="button-link remove-condition" style="color: #999; padding: 5px;" title="<?php _e('Remove condition', 'wc-xml-csv-import'); ?>">✕</button>
                                                                    </div>
                                                                </div>
                                                                
                                                                <button type="button" class="button button-small add-condition" style="margin-top: 10px;">
                                                                    <span class="dashicons dashicons-plus" style="font-size: 14px; line-height: 1.4;"></span>
                                                                    <?php _e('Add Condition', 'wc-xml-csv-import'); ?>
                                                                </button>
                                                            </div>
                                                            
                                                            <!-- Pricing Values -->
                                                            <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Markup %', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][markup_percent]" value="0" min="-100" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php _e('+ Fixed €', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][fixed_amount]" value="0" min="-10000" max="10000" step="0.01"
                                                                           style="width: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Round to', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <select name="pricing_rule[{id}][rounding]" style="padding: 8px; border: 1px solid #ccc; border-radius: 4px; min-width: 130px;">
                                                                        <option value="inherit"><?php _e('Use default', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="none"><?php _e('No rounding', 'wc-xml-csv-import'); ?></option>
                                                                        <option value="0.01">0.01</option>
                                                                        <option value="0.05">0.05</option>
                                                                        <option value="0.10">0.10</option>
                                                                        <option value="0.50">0.50</option>
                                                                        <option value="1.00">1.00</option>
                                                                        <option value="0.99">.99</option>
                                                                        <option value="0.95">.95</option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Min €', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][min_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                                <div>
                                                                    <label style="font-size: 12px; color: #666; display: block; margin-bottom: 4px;">
                                                                        <?php _e('Max €', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <input type="number" name="pricing_rule[{id}][max_price]" value="" min="0" step="0.01" placeholder="—"
                                                                           style="width: 70px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                                                </div>
                                                            </div>
                                                            
                                                        </div>
                                                    </template>
                                                    
                                                </div>
                                                
                                                <!-- Live Preview Calculator -->
                                                <div style="padding: 20px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 8px; border: 2px solid #4caf50; margin-top: 20px;">
                                                    <h4 style="margin: 0 0 15px 0; color: #2e7d32; display: flex; align-items: center; gap: 8px;">
                                                        <span class="dashicons dashicons-calculator" style="color: #4caf50;"></span>
                                                        <?php _e('Live Preview', 'wc-xml-csv-import'); ?>
                                                    </h4>
                                                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                <?php _e('Test with Rule:', 'wc-xml-csv-import'); ?>
                                                            </label>
                                                            <select id="pricing_engine_test_rule" style="padding: 8px 12px; border: 2px solid #81c784; border-radius: 4px; font-size: 14px; min-width: 150px;">
                                                                <option value="default"><?php _e('Default Rule', 'wc-xml-csv-import'); ?></option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                <?php _e('Test Base Price:', 'wc-xml-csv-import'); ?>
                                                            </label>
                                                            <input type="number" id="pricing_engine_test_input" value="100" step="0.01" min="0"
                                                                   style="width: 120px; padding: 8px; border: 2px solid #81c784; border-radius: 4px; font-size: 16px; font-weight: 600;">
                                                        </div>
                                                        <div style="font-size: 24px; color: #4caf50;">→</div>
                                                        <div>
                                                            <label style="font-size: 12px; color: #558b2f; display: block; margin-bottom: 4px;">
                                                                <?php _e('Final Price:', 'wc-xml-csv-import'); ?>
                                                            </label>
                                                            <div id="pricing_engine_test_output" style="padding: 8px 15px; background: #fff; border: 2px solid #4caf50; border-radius: 4px; font-size: 18px; font-weight: 700; color: #2e7d32; min-width: 100px;">
                                                                €100.00
                                                            </div>
                                                        </div>
                                                        <div style="margin-left: 10px; padding: 8px 12px; background: #fff; border-radius: 4px; font-size: 12px; color: #666;">
                                                            <span id="pricing_engine_matched_rule" style="color: #4caf50; font-weight: 600;"><?php _e('Default Rule', 'wc-xml-csv-import'); ?></span><br>
                                                            <span id="pricing_engine_formula">100 × 1.00 + 0 = 100.00</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                            </div>
                                            
                                            <!-- Disabled State Message -->
                                            <div id="pricing-engine-disabled-msg" style="padding: 25px; background: #f5f5f5; border-radius: 8px; text-align: center;">
                                                <span style="font-size: 36px; opacity: 0.5;">⚡</span>
                                                <p style="margin: 10px 0 0 0; color: #999;">
                                                    <?php _e('Enable the Price Markup above to configure automatic price calculations', 'wc-xml-csv-import'); ?>
                                                </p>
                                            </div>
                                            
                                        </div>
                                    <?php elseif ($section_key === 'attributes_variations'): ?>
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <!-- SIMPLIFIED UI - 3 Clear Options                                  -->
                                        <!-- 1. Simple (default)  2. Attributes  3. Variations               -->
                                        <!-- ═══════════════════════════════════════════════════════════════ -->
                                        <div class="attributes-variations-container">
                                            
                                            <input type="hidden" name="variation_mode" id="variation_mode_hidden" value="simple">
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PRODUCT MODE SELECTION - 3 Cards                                -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div class="product-mode-selection" style="margin-bottom: 25px;">
                                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                                                    
                                                    <!-- OPTION 1: Simple Products -->
                                                    <label class="mode-card" id="card-simple" style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; cursor: pointer; transition: all 0.3s; border: 3px solid transparent; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                                        <input type="radio" name="product_mode" value="simple" style="position: absolute; opacity: 0; pointer-events: none;" checked>
                                                        <div style="text-align: center;">
                                                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">🛍️</span>
                                                            <strong style="font-size: 16px; display: block;"><?php _e('Simple Products', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 10px 0 0 0; font-size: 12px; opacity: 0.9;">
                                                                <?php _e('Regular products without variations or attributes', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </label>
                                                    
                                                    <!-- OPTION 2: With Attributes -->
                                                    <label class="mode-card" id="card-attributes" style="padding: 20px; background: #f5f5f5; border-radius: 12px; cursor: pointer; transition: all 0.3s; border: 3px solid #e0e0e0;">
                                                        <input type="radio" name="product_mode" value="attributes" style="position: absolute; opacity: 0; pointer-events: none;">
                                                        <div style="text-align: center;">
                                                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">🏷️</span>
                                                            <strong style="font-size: 16px; display: block; color: #333;"><?php _e('With Attributes', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                                                                <?php _e('Add display attributes like Material, Brand, Color', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </label>
                                                    
                                                    <!-- OPTION 3: Variable Products -->
                                                    <label class="mode-card" id="card-variable" style="padding: 20px; background: #f5f5f5; border-radius: 12px; cursor: pointer; transition: all 0.3s; border: 3px solid #e0e0e0;">
                                                        <input type="radio" name="product_mode" value="variable" style="position: absolute; opacity: 0; pointer-events: none;">
                                                        <div style="text-align: center;">
                                                            <span style="font-size: 36px; display: block; margin-bottom: 10px;">📦</span>
                                                            <strong style="font-size: 16px; display: block; color: #333;"><?php _e('Variable Products', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                                                                <?php _e('Products with variations like Size, Color', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </label>
                                                    
                                                </div>
                                            </div>
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PANEL: SIMPLE (nothing to configure)                            -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div id="panel-simple" class="mode-panel" style="display: block;">
                                                <div style="padding: 30px; background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 8px; text-align: center;">
                                                    <span style="font-size: 48px;">✅</span>
                                                    <h4 style="margin: 15px 0 10px 0; color: #2e7d32;"><?php _e('Simple Products Mode', 'wc-xml-csv-import'); ?></h4>
                                                    <p style="color: #558b2f; margin: 0;">
                                                        <?php _e('No additional configuration needed. Products will be imported as simple products.', 'wc-xml-csv-import'); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PANEL: ATTRIBUTES                                                -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div id="panel-attributes" class="mode-panel" style="display: none;">
                                                <div style="padding: 25px; background: #fff; border-radius: 8px; border: 2px solid #e0e0e0;">
                                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                                        <span style="font-size: 32px;">🏷️</span>
                                                        <div>
                                                            <h4 style="margin: 0; color: #333;"><?php _e('Display Attributes', 'wc-xml-csv-import'); ?></h4>
                                                            <p class="description" style="margin: 5px 0 0 0;">
                                                                <?php _e('Add attributes that will be shown on product pages. These are informational only.', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Attribute Mode Selection -->
                                                    <div style="margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 12px; color: #333;">
                                                            <?php _e('Attribute Input Mode:', 'wc-xml-csv-import'); ?>
                                                        </label>
                                                        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; background: #fff; border: 2px solid #e0e0e0; border-radius: 6px; transition: all 0.2s;">
                                                                <input type="radio" name="attr_input_mode" value="standard" checked style="margin: 0;">
                                                                <span>
                                                                    <strong><?php _e('Standard', 'wc-xml-csv-import'); ?></strong>
                                                                    <small style="display: block; color: #666; font-size: 11px;"><?php _e('Fixed name + dynamic value', 'wc-xml-csv-import'); ?></small>
                                                                </span>
                                                            </label>
                                                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 15px; background: #fff; border: 2px solid #e0e0e0; border-radius: 6px; transition: all 0.2s;">
                                                                <input type="radio" name="attr_input_mode" value="key_value" style="margin: 0;">
                                                                <span>
                                                                    <strong><?php _e('Key-Value Pairs', 'wc-xml-csv-import'); ?></strong>
                                                                    <small style="display: block; color: #666; font-size: 11px;"><?php _e('BigBuy format: {attribute1}→{value1}', 'wc-xml-csv-import'); ?></small>
                                                                </span>
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Standard Mode: Attributes List -->
                                                    <div id="attr-mode-standard">
                                                        <div id="attributes-list" style="margin-bottom: 15px;"></div>
                                                        
                                                        <button type="button" class="button button-primary" id="btn-add-attribute" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php _e('Add Attribute', 'wc-xml-csv-import'); ?>
                                                        </button>
                                                        
                                                        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 6px; border-left: 4px solid #2196f3;">
                                                            <strong style="color: #1565c0;">💡 <?php _e('Example:', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #555;">
                                                                <?php _e('Attribute Name: "Material" → Source: select the XML/CSV field containing material values', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Key-Value Mode: Attribute Pairs List -->
                                                    <div id="attr-mode-key-value" style="display: none;">
                                                        <div style="margin-bottom: 15px; padding: 12px; background: #fff3e0; border-radius: 6px; border-left: 4px solid #ff9800;">
                                                            <strong style="color: #e65100;">📋 <?php _e('Key-Value Pair Mode', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #555; font-size: 13px;">
                                                                <?php _e('For XML files where attribute name and value are in separate fields (e.g., BigBuy format with attribute1/value1, attribute2/value2).', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <div id="attribute-pairs-list" style="margin-bottom: 15px;"></div>
                                                        
                                                        <button type="button" class="button button-primary" id="btn-add-attribute-pair" style="display: flex; align-items: center; gap: 5px;">
                                                            <span class="dashicons dashicons-plus-alt2" style="margin-top: 3px;"></span>
                                                            <?php _e('Add Key-Value Pair', 'wc-xml-csv-import'); ?>
                                                        </button>
                                                        
                                                        <div style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 6px; border-left: 4px solid #4caf50;">
                                                            <strong style="color: #2e7d32;">💡 <?php _e('Example for BigBuy XML:', 'wc-xml-csv-import'); ?></strong>
                                                            <div style="margin-top: 8px; font-size: 13px; color: #555;">
                                                                <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">&lt;attribute1&gt;Colour&lt;/attribute1&gt; &lt;value1&gt;Blue&lt;/value1&gt;</code><br><br>
                                                                <?php _e('Name Field:', 'wc-xml-csv-import'); ?> <code>{attribute1}</code> → <?php _e('Value Field:', 'wc-xml-csv-import'); ?> <code>{value1}</code><br>
                                                                <?php _e('Result: Attribute "Colour" with value "Blue"', 'wc-xml-csv-import'); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <!-- PANEL: VARIABLE PRODUCTS                                         -->
                                            <!-- ═══════════════════════════════════════════════════════════════ -->
                                            <div id="panel-variable" class="mode-panel" style="display: none;">
                                                <div style="padding: 25px; background: #fff; border-radius: 8px; border: 2px solid #e0e0e0;">
                                                    
                                                    <!-- Header -->
                                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;">
                                                        <span style="font-size: 32px;">📦</span>
                                                        <div>
                                                            <h4 style="margin: 0; color: #333;"><?php _e('Variable Products Configuration', 'wc-xml-csv-import'); ?></h4>
                                                            <p class="description" style="margin: 5px 0 0 0;">
                                                                <?php _e('Configure how variations are structured in your file.', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($file_type === 'csv'): ?>
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    <!-- CSV VARIATION MODE - Parent/Child Rows                          -->
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    <div class="csv-variation-config">
                                                        
                                                        <!-- Info Box -->
                                                        <div style="margin-bottom: 20px; padding: 15px; background: #e3f2fd; border-radius: 8px; border-left: 4px solid #2196f3;">
                                                            <strong style="color: #1565c0;">💡 <?php _e('CSV Variable Products', 'wc-xml-csv-import'); ?></strong>
                                                            <p style="margin: 8px 0 0 0; color: #555; font-size: 13px;">
                                                                <?php _e('For CSV files, variable products require Parent SKU and Type columns to link variations to parent products.', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <!-- CSV Grouping Fields -->
                                                        <div style="margin-bottom: 25px; padding: 20px; background: #fff3e0; border-radius: 8px; border: 1px solid #ffcc80;">
                                                            <label style="font-weight: 600; display: block; margin-bottom: 15px; color: #e65100; font-size: 14px;">
                                                                🔗 <?php _e('CSV Grouping Fields', 'wc-xml-csv-import'); ?>
                                                            </label>
                                                            
                                                            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center;">
                                                                <!-- Parent SKU Column -->
                                                                <label style="font-weight: 500;"><?php _e('Parent SKU Column:', 'wc-xml-csv-import'); ?> <span style="color: #e53e3e;">*</span></label>
                                                                <select name="csv_var[parent_sku_column]" id="csv-parent-sku-column" class="field-source-select" style="max-width: 320px;">
                                                                    <option value=""><?php _e('-- Select Column --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                                
                                                                <!-- Type Column -->
                                                                <label style="font-weight: 500;"><?php _e('Type Column:', 'wc-xml-csv-import'); ?></label>
                                                                <select name="csv_var[type_column]" id="csv-type-column" class="field-source-select" style="max-width: 320px;">
                                                                    <option value=""><?php _e('-- Select Column (optional) --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                            </div>
                                                            
                                                            <p class="description" style="margin-top: 12px; font-size: 12px; color: #666;">
                                                                <?php _e('Parent SKU links variations to parent. Type column (values: "variable" or "variation") helps identify row types.', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                        
                                                        <!-- CSV Variation Attributes -->
                                                        <div style="margin-bottom: 25px; padding: 20px; background: #e8f5e9; border-radius: 8px; border: 1px solid #a5d6a7;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                                <label style="font-weight: 600; color: #2e7d32; font-size: 14px;">
                                                                    🏷️ <?php _e('Variation Attributes (CSV Columns)', 'wc-xml-csv-import'); ?>
                                                                </label>
                                                                <button type="button" class="button" id="btn-add-csv-var-attribute">
                                                                    <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                    <?php _e('Add Attribute', 'wc-xml-csv-import'); ?>
                                                                </button>
                                                            </div>
                                                            <p class="description" style="margin-bottom: 10px; font-size: 12px;">
                                                                <?php _e('Map CSV columns that contain attribute values (e.g., "Attribute:Color", "Attribute:Size").', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                            
                                                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #c8e6c9; margin-bottom: 15px;">
                                                                <strong style="font-size: 12px; color: #2e7d32;">💡 <?php _e('Example for CSV structure:', 'wc-xml-csv-import'); ?></strong>
                                                                <div style="font-size: 11px; color: #666; margin-top: 8px;">
                                                                    <?php _e('If your CSV has columns:', 'wc-xml-csv-import'); ?><br>
                                                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 10px;">Attribute:Color, Attribute:Size</code><br><br>
                                                                    <?php _e('Add 2 attributes:', 'wc-xml-csv-import'); ?><br>
                                                                    • <strong>Color</strong> → Source Column: <code>Attribute:Color</code><br>
                                                                    • <strong>Size</strong> → Source Column: <code>Attribute:Size</code>
                                                                </div>
                                                            </div>
                                                            <div id="csv-variation-attributes-list"></div>
                                                        </div>
                                                        
                                                        <!-- CSV Variation Field Mapping -->
                                                        <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; border: 1px solid #e0e0e0;">
                                                            <label style="font-weight: 600; display: block; margin-bottom: 15px; color: #333; font-size: 14px;">
                                                                📦 <?php _e('Variation Field Mapping (CSV Columns)', 'wc-xml-csv-import'); ?>
                                                            </label>
                                                            <p class="description" style="margin-bottom: 15px; font-size: 12px;">
                                                                <?php _e('Map CSV columns to WooCommerce variation fields. These are read from variation rows.', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                            
                                                            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center;">
                                                                
                                                                <!-- Variation SKU -->
                                                                <label style="font-weight: 500;"><?php _e('Variation SKU:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sku]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- GTIN/EAN/UPC/ISBN -->
                                                                <label style="font-weight: 500;"><?php _e('GTIN/EAN/UPC/ISBN:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[gtin]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Regular Price -->
                                                                <label style="font-weight: 500;"><?php _e('Regular Price:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[regular_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Sale Price -->
                                                                <label style="font-weight: 500;"><?php _e('Sale Price:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sale_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Sale Price Dates From -->
                                                                <label style="font-weight: 500;"><?php _e('Sale Date From:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sale_price_dates_from]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Sale Price Dates To -->
                                                                <label style="font-weight: 500;"><?php _e('Sale Date To:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[sale_price_dates_to]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Stock Quantity -->
                                                                <label style="font-weight: 500;"><?php _e('Stock Quantity:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[stock_quantity]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Stock Status -->
                                                                <label style="font-weight: 500;"><?php _e('Stock Status:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[stock_status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Manage Stock -->
                                                                <label style="font-weight: 500;"><?php _e('Manage Stock:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[manage_stock]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Low Stock Amount -->
                                                                <label style="font-weight: 500;"><?php _e('Low Stock Threshold:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[low_stock_amount]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Weight -->
                                                                <label style="font-weight: 500;"><?php _e('Weight:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[weight]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Dimensions -->
                                                                <label style="font-weight: 500;"><?php _e('Length:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[length]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <label style="font-weight: 500;"><?php _e('Width:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[width]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <label style="font-weight: 500;"><?php _e('Height:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[height]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Image -->
                                                                <label style="font-weight: 500;"><?php _e('Image URL:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[image]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Description -->
                                                                <label style="font-weight: 500;"><?php _e('Description:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[description]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Virtual -->
                                                                <label style="font-weight: 500;"><?php _e('Virtual:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[virtual]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Downloadable -->
                                                                <label style="font-weight: 500;"><?php _e('Downloadable:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[downloadable]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Shipping Class -->
                                                                <label style="font-weight: 500;"><?php _e('Shipping Class:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[shipping_class]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Status -->
                                                                <label style="font-weight: 500;"><?php _e('Status:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                                <!-- Menu Order -->
                                                                <label style="font-weight: 500;"><?php _e('Menu Order:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper"><textarea name="csv_var_field[menu_order]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see columns', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                                
                                                            </div>
                                                            
                                                            <!-- Custom Meta Fields for CSV Variations -->
                                                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                                    <label style="font-weight: 600; color: #555;">
                                                                        🔧 <?php _e('Custom Meta Fields (EAN, GTIN, UPC, etc.)', 'wc-xml-csv-import'); ?>
                                                                    </label>
                                                                    <button type="button" class="button" id="btn-add-csv-var-meta">
                                                                        <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                        <?php _e('Add Meta Field', 'wc-xml-csv-import'); ?>
                                                                    </button>
                                                                </div>
                                                                <div id="csv-variation-meta-list"></div>
                                                            </div>
                                                        </div>
                                                        
                                                    </div><!-- /csv-variation-config -->
                                                    <?php else: ?>
                                                    
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    <!-- XML VARIATION MODE - Nested Elements                            -->
                                                    <!-- ═══════════════════════════════════════════════════════════════ -->
                                                    
                                                    <!-- SECTION 1: Variation Path -->
                                                    <div style="margin-bottom: 25px; padding: 20px; background: #e3f2fd; border-radius: 8px; border: 1px solid #90caf9;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 10px; color: #1565c0; font-size: 14px;">
                                                            📍 <?php _e('Variation Container Path', 'wc-xml-csv-import'); ?>
                                                            <span style="color: #e53e3e;">*</span>
                                                        </label>
                                                        <input type="text" 
                                                               name="variation_path" 
                                                               id="variation_path"
                                                               value=""
                                                               placeholder="e.g., variations.variation or attributes.attribute"
                                                               style="width: 100%; max-width: 500px; padding: 12px; border: 2px solid #64b5f6; border-radius: 6px; font-family: monospace; font-size: 14px;">
                                                        <p class="description" style="margin-top: 10px;">
                                                            <?php _e('Path to variation/attribute container. Examples:', 'wc-xml-csv-import'); ?><br>
                                                            <code style="cursor: pointer; background: #bbdefb; padding: 3px 8px; border-radius: 4px; margin: 5px 5px 0 0; display: inline-block;" onclick="document.getElementById('variation_path').value='variations.variation'">variations.variation</code>
                                                            <code style="cursor: pointer; background: #bbdefb; padding: 3px 8px; border-radius: 4px; margin: 5px 5px 0 0; display: inline-block;" onclick="document.getElementById('variation_path').value='attributes.attribute'">attributes.attribute</code>
                                                            <code style="cursor: pointer; background: #bbdefb; padding: 3px 8px; border-radius: 4px; margin: 5px 5px 0 0; display: inline-block;" onclick="document.getElementById('variation_path').value='variants.variant'">variants.variant</code>
                                                        </p>
                                                    </div>
                                                    
                                                    <!-- SECTION 2: Variation Attributes -->
                                                    <div style="margin-bottom: 25px; padding: 20px; background: #fff3e0; border-radius: 8px; border: 1px solid #ffcc80;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                                            <label style="font-weight: 600; color: #e65100; font-size: 14px;">
                                                                🏷️ <?php _e('Variation Attributes', 'wc-xml-csv-import'); ?>
                                                            </label>
                                                        </div>
                                                        <p class="description" style="margin-bottom: 15px;">
                                                            <?php _e('Define attributes used for variations (e.g., Size, Color). Each unique combination creates a product variation.', 'wc-xml-csv-import'); ?>
                                                        </p>
                                                        
                                                        <!-- Attribute Mode Selection -->
                                                        <div style="background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #ffe0b2; margin-bottom: 15px;">
                                                            <div style="display: flex; gap: 25px; flex-wrap: wrap;">
                                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                                                    <input type="radio" name="attribute_detection_mode" value="manual" checked class="attribute-mode-radio">
                                                                    <span>📝 <?php _e('Manual', 'wc-xml-csv-import'); ?></span>
                                                                    <span style="font-weight: normal; color: #666; font-size: 12px;">— <?php _e('Define each attribute manually', 'wc-xml-csv-import'); ?></span>
                                                                </label>
                                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                                                    <input type="radio" name="attribute_detection_mode" value="auto" class="attribute-mode-radio">
                                                                    <span>🔍 <?php _e('Auto-detect', 'wc-xml-csv-import'); ?></span>
                                                                    <span style="font-weight: normal; color: #666; font-size: 12px;">— <?php _e('Read all attributes from XML automatically', 'wc-xml-csv-import'); ?></span>
                                                                </label>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- AUTO-DETECT Settings (hidden by default) -->
                                                        <div id="auto-detect-attributes-settings" style="display: none; background: #e8f5e9; padding: 15px; border-radius: 6px; border: 1px solid #a5d6a7; margin-bottom: 15px;">
                                                            <p style="margin: 0 0 12px 0; font-weight: 500; color: #2e7d32;">
                                                                ✨ <?php _e('Auto-detect Settings', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                            <div style="display: grid; grid-template-columns: 180px 1fr; gap: 10px; align-items: center;">
                                                                <label style="font-weight: 500;"><?php _e('Attributes path:', 'wc-xml-csv-import'); ?></label>
                                                                <div class="textarea-mapping-wrapper">
                                                                    <textarea name="auto_attributes_path" id="auto-attributes-path" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('e.g., variations.variation.attributes', 'wc-xml-csv-import'); ?>" style="max-width: 350px;"></textarea>
                                                                    <p class="description" style="font-size: 10px; margin-top: 2px; color: #666;">
                                                                        <?php _e('Path to attributes container in each variation (without { })', 'wc-xml-csv-import'); ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div style="margin-top: 12px;">
                                                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                                                    <input type="checkbox" name="auto_create_attributes" id="auto-create-attributes" checked>
                                                                    <span><?php _e('Create WooCommerce attributes if they don\'t exist', 'wc-xml-csv-import'); ?></span>
                                                                </label>
                                                            </div>
                                                            <div style="background: #fff; padding: 10px; border-radius: 4px; margin-top: 12px; font-size: 11px; color: #555;">
                                                                <strong>💡 <?php _e('How it works:', 'wc-xml-csv-import'); ?></strong><br>
                                                                <?php _e('For XML like:', 'wc-xml-csv-import'); ?> <code>&lt;attributes&gt;&lt;size&gt;L&lt;/size&gt;&lt;color&gt;Black&lt;/color&gt;&lt;/attributes&gt;</code><br>
                                                                <?php _e('Plugin will automatically create attributes: pa_size=L, pa_color=Black', 'wc-xml-csv-import'); ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- MANUAL Attributes Settings -->
                                                        <div id="manual-attributes-settings">
                                                            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
                                                                <button type="button" class="button" id="btn-add-var-attribute">
                                                                    <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                    <?php _e('Add Attribute', 'wc-xml-csv-import'); ?>
                                                                </button>
                                                            </div>
                                                            <div style="background: #fff; padding: 12px; border-radius: 4px; border: 1px solid #ffe0b2; margin-bottom: 15px;">
                                                                <strong style="font-size: 12px; color: #e65100;">💡 <?php _e('Example for your XML structure:', 'wc-xml-csv-import'); ?></strong>
                                                                <div style="font-size: 11px; color: #666; margin-top: 8px;">
                                                                    <?php _e('If your XML has:', 'wc-xml-csv-import'); ?><br>
                                                                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 10px;">&lt;attributes&gt;&lt;attribute&gt;S&lt;/attribute&gt;&lt;attribute&gt;Red&lt;/attribute&gt;&lt;/attributes&gt;</code><br><br>
                                                                    <?php _e('Add 2 attributes:', 'wc-xml-csv-import'); ?><br>
                                                                    • <strong>Size</strong> → Source: <code>attributes.attribute</code> + Array Index: <code>0</code><br>
                                                                    • <strong>Color</strong> → Source: <code>attributes.attribute</code> + Array Index: <code>1</code>
                                                                </div>
                                                            </div>
                                                            <div id="variation-attributes-list"></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- SECTION 3: Variation Field Mapping -->
                                                    <div style="padding: 20px; background: #f5f5f5; border-radius: 8px; border: 1px solid #e0e0e0;">
                                                        <label style="font-weight: 600; display: block; margin-bottom: 15px; color: #333; font-size: 14px;">
                                                            📦 <?php _e('Variation Field Mapping', 'wc-xml-csv-import'); ?>
                                                        </label>
                                                        <p class="description" style="margin-bottom: 15px;">
                                                            <?php _e('Map fields from your source file to WooCommerce variation fields.', 'wc-xml-csv-import'); ?>
                                                        </p>
                                                        
                                                        <div style="display: grid; grid-template-columns: 180px 1fr; gap: 12px; align-items: center;">
                                                            
                                                            <!-- Parent SKU (for linking variations to parent) -->
                                                            <label style="font-weight: 500;"><?php _e('Parent SKU:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper">
                                                                <textarea name="var_field[parent_sku]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea>
                                                                <p class="description" style="font-size: 10px; margin-top: 2px; color: #666;">
                                                                    <?php _e('Optional: Link variation to parent by SKU', 'wc-xml-csv-import'); ?>
                                                                </p>
                                                            </div>
                                                            
                                                            <!-- SKU -->
                                                            <label style="font-weight: 500;"><?php _e('Variation SKU:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sku]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- GTIN/EAN/UPC/ISBN -->
                                                            <label style="font-weight: 500;"><?php _e('GTIN/EAN/UPC/ISBN:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[gtin]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Regular Price -->
                                                            <label style="font-weight: 500;"><?php _e('Regular Price:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[regular_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Sale Price -->
                                                            <label style="font-weight: 500;"><?php _e('Sale Price:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sale_price]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Sale Price Dates From -->
                                                            <label style="font-weight: 500;"><?php _e('Sale Date From:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sale_price_dates_from]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Sale Price Dates To -->
                                                            <label style="font-weight: 500;"><?php _e('Sale Date To:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[sale_price_dates_to]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Stock Quantity -->
                                                            <label style="font-weight: 500;"><?php _e('Stock Quantity:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[stock_quantity]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Stock Status -->
                                                            <label style="font-weight: 500;"><?php _e('Stock Status:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[stock_status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Manage Stock -->
                                                            <label style="font-weight: 500;"><?php _e('Manage Stock:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[manage_stock]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Low Stock Amount -->
                                                            <label style="font-weight: 500;"><?php _e('Low Stock Threshold:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[low_stock_amount]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Backorders -->
                                                            <label style="font-weight: 500;"><?php _e('Backorders:', 'wc-xml-csv-import'); ?></label>
                                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                                <textarea name="var_field[backorders]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 200px;"></textarea>
                                                                <select name="var_field[backorders_default]" style="max-width: 120px;">
                                                                    <option value=""><?php _e('From source', 'wc-xml-csv-import'); ?></option>
                                                                    <option value="no"><?php _e('No', 'wc-xml-csv-import'); ?></option>
                                                                    <option value="notify"><?php _e('Allow, notify', 'wc-xml-csv-import'); ?></option>
                                                                    <option value="yes"><?php _e('Allow', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Weight with inheritance option -->
                                                            <label style="font-weight: 500;"><?php _e('Weight:', 'wc-xml-csv-import'); ?></label>
                                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                                <textarea name="var_field[weight]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 200px;"></textarea>
                                                                <label style="font-size: 11px; display: flex; align-items: center; gap: 4px;">
                                                                    <input type="checkbox" name="var_field[weight_inherit]" value="1">
                                                                    <?php _e('Inherit from parent', 'wc-xml-csv-import'); ?>
                                                                </label>
                                                            </div>
                                                            
                                                            <!-- Dimensions with inheritance option -->
                                                            <label style="font-weight: 500;"><?php _e('Length:', 'wc-xml-csv-import'); ?></label>
                                                            <div style="display: flex; gap: 10px; align-items: center;">
                                                                <textarea name="var_field[length]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 200px;"></textarea>
                                                                <label style="font-size: 11px; display: flex; align-items: center; gap: 4px;">
                                                                    <input type="checkbox" name="var_field[dimensions_inherit]" value="1">
                                                                    <?php _e('Inherit L/W/H from parent', 'wc-xml-csv-import'); ?>
                                                                </label>
                                                            </div>
                                                            
                                                            <label style="font-weight: 500;"><?php _e('Width:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[width]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <label style="font-weight: 500;"><?php _e('Height:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[height]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Image -->
                                                            <label style="font-weight: 500;"><?php _e('Image URL:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[image]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Description -->
                                                            <label style="font-weight: 500;"><?php _e('Description:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[description]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Virtual -->
                                                            <label style="font-weight: 500;"><?php _e('Virtual:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[virtual]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Downloadable -->
                                                            <label style="font-weight: 500;"><?php _e('Downloadable:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[downloadable]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Download Limit -->
                                                            <label style="font-weight: 500;"><?php _e('Download Limit:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[download_limit]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Download Expiry -->
                                                            <label style="font-weight: 500;"><?php _e('Download Expiry (days):', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[download_expiry]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Downloads (file URLs) -->
                                                            <label style="font-weight: 500;"><?php _e('Download Files:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper">
                                                                <textarea name="var_field[downloads]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea>
                                                                <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                    <?php _e('Format: Name|URL or just URL. Multiple: Name1|URL1,Name2|URL2', 'wc-xml-csv-import'); ?>
                                                                </p>
                                                            </div>
                                                            
                                                            <!-- Tax Class -->
                                                            <label style="font-weight: 500;"><?php _e('Tax Class:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[tax_class]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Shipping Class -->
                                                            <label style="font-weight: 500;"><?php _e('Shipping Class:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[shipping_class]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Status -->
                                                            <label style="font-weight: 500;"><?php _e('Status:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[status]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                            <!-- Menu Order -->
                                                            <label style="font-weight: 500;"><?php _e('Menu Order:', 'wc-xml-csv-import'); ?></label>
                                                            <div class="textarea-mapping-wrapper"><textarea name="var_field[menu_order]" class="field-mapping-textarea field-mapping-textarea-small" rows="1" placeholder="<?php esc_attr_e('Type { to see fields', 'wc-xml-csv-import'); ?>" style="max-width: 320px;"></textarea></div>
                                                            
                                                        </div>
                                                        
                                                        <!-- Custom Meta Fields for Variations -->
                                                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                                <label style="font-weight: 600; color: #555;">
                                                                    🔧 <?php _e('Custom Meta Fields (EAN, GTIN, UPC, etc.)', 'wc-xml-csv-import'); ?>
                                                                </label>
                                                                <button type="button" class="button" id="btn-add-var-meta">
                                                                    <span class="dashicons dashicons-plus" style="margin-top: 3px;"></span>
                                                                    <?php _e('Add Meta Field', 'wc-xml-csv-import'); ?>
                                                                </button>
                                                            </div>
                                                            
                                                            <!-- Common meta field presets -->
                                                            <div style="margin-bottom: 15px; padding: 10px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px;">
                                                                <label style="font-size: 11px; font-weight: 600; color: #666; display: block; margin-bottom: 8px;">
                                                                    <?php _e('Quick Add Common Fields:', 'wc-xml-csv-import'); ?>
                                                                </label>
                                                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_supplier_id" data-label="Supplier ID">Supplier ID</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_warehouse_code" data-label="Warehouse">Warehouse</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_lead_time_days" data-label="Lead Time">Lead Time</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_cost_price" data-label="Cost Price">Cost Price</button>
                                                                    <button type="button" class="button button-small var-meta-preset" data-key="_barcode" data-label="Barcode">Barcode</button>
                                                                </div>
                                                            </div>
                                                            
                                                            <div id="variation-meta-list"></div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?><!-- /file_type check -->
                                                    
                                                </div>
                                            </div>
                                            
                                        </div><!-- /attributes-variations-container -->
                                    <?php else: ?>
                                    <?php foreach ($section['fields'] as $field_key => $field): ?>
                                        <?php if ($field_key === 'shipping_class_formula'): ?>
                                            <!-- Shipping Class Formula - separate from regular mapping -->
                                            <div class="shipping-class-formula-section" style="padding: 20px; background: #f9f9f9; border-radius: 4px; margin-bottom: 20px;">
                                                <h4 style="margin-top: 0;">🧮 <?php _e('Auto Shipping Class Formula', 'wc-xml-csv-import'); ?></h4>
                                                <p class="description" style="margin-bottom: 15px;">
                                                    <?php _e('Optional: Calculate shipping class based on dimensions/weight when no direct mapping is set.', 'wc-xml-csv-import'); ?>
                                                </p>
                                                
                                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">
                                                    <?php _e('PHP Formula (return shipping class slug):', 'wc-xml-csv-import'); ?>
                                                </label>
                                                
                                                <textarea name="field_mapping[shipping_class_formula][formula]" 
                                                          id="shipping-class-formula" 
                                                          rows="12" 
                                                          style="width: 100%; font-family: 'Courier New', monospace; font-size: 13px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                                                          placeholder="// Available variables: $weight, $length, $width, $height&#10;&#10;if ($weight > 30) {&#10;    return 'Smags';&#10;}&#10;&#10;if ($height <= 8 && $length <= 38 && $width <= 64) {&#10;    return 'S';&#10;}&#10;&#10;if ($height <= 39 && $length <= 38 && $width <= 64) {&#10;    return 'M';&#10;}&#10;&#10;return 'L';"></textarea>
                                                
                                                <button type="button" class="button button-small test-shipping-formula" style="margin-top: 10px;">
                                                    <?php _e('Test Shipping Formula', 'wc-xml-csv-import'); ?>
                                                </button>
                                                
                                                <div style="margin-top: 10px; padding: 10px; background: #fff; border-left: 3px solid #0073aa; border-radius: 3px;">
                                                    <strong><?php _e('Available Variables:', 'wc-xml-csv-import'); ?></strong><br>
                                                    <code>$weight</code>, <code>$length</code>, <code>$width</code>, <code>$height</code>
                                                    <br><br>
                                                    <strong><?php _e('Available Shipping Classes:', 'wc-xml-csv-import'); ?></strong><br>
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
                                                        _e('No shipping classes found. Create them in WooCommerce → Settings → Shipping', 'wc-xml-csv-import');
                                                    endif;
                                                    ?>
                                                    <br><br>
                                                    <em><?php _e('This formula is used only when no direct mapping is set above. Leave empty to skip.', 'wc-xml-csv-import'); ?></em>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                        <div class="field-mapping-row" data-field="<?php echo $field_key; ?>" data-field-type="<?php echo esc_attr($field['type']); ?>">
                                            <div class="field-target">
                                                <label class="field-label <?php echo $field['required'] ? 'required' : ''; ?>">
                                                    <?php echo $field['label']; ?>
                                                    <?php if ($field['required']): ?>
                                                        <span class="required-asterisk">*</span>
                                                    <?php endif; ?>
                                                </label>
                                                <span class="field-type"><?php echo $field['type']; ?></span>
                                            </div>
                                            
                                            <div class="field-source">
                                                <?php 
                                                // Get Tax Classes for tax_class_select
                                                $tax_classes = array();
                                                if ($field['type'] === 'tax_class_select') {
                                                    $tax_classes = WC_Tax::get_tax_classes();
                                                    array_unshift($tax_classes, ''); // Standard rate
                                                }
                                                
                                                // Render based on field type
                                                switch ($field['type']):
                                                    case 'boolean': ?>
                                                        <!-- Boolean Field: Yes / No / Map from XML -->
                                                        <div class="boolean-field-options">
                                                            <label class="boolean-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][boolean_mode]" 
                                                                       value="yes" 
                                                                       class="boolean-mode-radio">
                                                                <span class="boolean-label boolean-yes"><?php _e('Yes', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <label class="boolean-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][boolean_mode]" 
                                                                       value="no" 
                                                                       class="boolean-mode-radio"
                                                                       checked>
                                                                <span class="boolean-label boolean-no"><?php _e('No', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <label class="boolean-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][boolean_mode]" 
                                                                       value="map" 
                                                                       class="boolean-mode-radio">
                                                                <span class="boolean-label boolean-map"><?php _e('Map', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                        </div>
                                                        <div class="boolean-map-field" style="display: none; margin-top: 8px;">
                                                            <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                <option value=""><?php _e('-- Select XML Field --', 'wc-xml-csv-import'); ?></option>
                                                            </select>
                                                            <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                <?php _e('XML values: yes/no, true/false, 1/0', 'wc-xml-csv-import'); ?>
                                                            </p>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'product_type_select': ?>
                                                        <!-- Product Type Select: Dropdown + Map option -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo $field_key; ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, 'simple'); ?>>
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php _e('Map from XML:', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php _e('-- Select XML Field --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                                <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                    <?php _e('Values: simple, variable, grouped, external', 'wc-xml-csv-import'); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'status_select': ?>
                                                        <!-- Status Select: Dropdown + Map option -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo $field_key; ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, 'publish'); ?>>
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php _e('Map from XML:', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php _e('-- Select XML Field --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'tax_status_select': ?>
                                                        <!-- Tax Status Select -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo $field_key; ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($opt_value, 'taxable'); ?>>
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php _e('Map from XML:', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php _e('-- Select XML Field --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                                <p class="description" style="font-size: 11px; margin-top: 4px;">
                                                                    <?php _e('Expected values: taxable, shipping, none', 'wc-xml-csv-import'); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'tax_class_select': ?>
                                                        <!-- Tax Class Select (dynamic from WooCommerce) -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo $field_key; ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <option value=""><?php _e('Standard', 'wc-xml-csv-import'); ?></option>
                                                                    <?php 
                                                                    foreach (WC_Tax::get_tax_classes() as $tax_class):
                                                                        $slug = sanitize_title($tax_class);
                                                                    ?>
                                                                        <option value="<?php echo esc_attr($slug); ?>">
                                                                            <?php echo esc_html($tax_class); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php _e('Map from XML:', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php _e('-- Select XML Field --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'stock_status_select':
                                                    case 'backorders_select': ?>
                                                        <!-- Stock Status / Backorders Select -->
                                                        <div class="select-with-map-options">
                                                            <label class="select-map-option">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="fixed" 
                                                                       class="select-mode-radio"
                                                                       checked>
                                                                <select name="field_mapping[<?php echo $field_key; ?>][fixed_value]" class="fixed-value-select select-fixed-value">
                                                                    <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                                                        <option value="<?php echo esc_attr($opt_value); ?>">
                                                                            <?php echo esc_html($opt_label); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </label>
                                                            <label class="select-map-option" style="margin-top: 8px; display: block;">
                                                                <input type="radio" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][select_mode]" 
                                                                       value="map" 
                                                                       class="select-mode-radio">
                                                                <span><?php _e('Map from XML:', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                            <div class="select-map-field" style="display: none; margin-top: 8px; margin-left: 24px;">
                                                                <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php _e('-- Select XML Field --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'identifier': ?>
                                                        <!-- Product Identifier Field with Primary checkbox -->
                                                        <div class="identifier-field-wrapper">
                                                            <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 60%;">
                                                                <option value=""><?php _e('-- Select Source Field --', 'wc-xml-csv-import'); ?></option>
                                                            </select>
                                                            <label class="primary-identifier-label" style="margin-left: 10px; display: inline-flex; align-items: center; gap: 5px;">
                                                                <input type="checkbox" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][is_primary]" 
                                                                       value="1" 
                                                                       class="primary-identifier-checkbox"
                                                                       data-identifier="<?php echo $field_key; ?>">
                                                                <span style="font-size: 11px; color: #666;"><?php _e('Use as primary identifier (WC UI)', 'wc-xml-csv-import'); ?></span>
                                                            </label>
                                                        </div>
                                                    <?php break;
                                                    
                                                    case 'textarea': 
                                                        $saved_textarea_value = isset($saved_mappings[$field_key]['source']) ? $saved_mappings[$field_key]['source'] : '';
                                                        ?>
                                                        <textarea name="field_mapping[<?php echo $field_key; ?>][source]" 
                                                                  class="field-source-textarea" 
                                                                  rows="3" 
                                                                  style="width:100%;" 
                                                                  placeholder="<?php echo esc_attr($field['description'] ?? ''); ?>"><?php echo esc_textarea($saved_textarea_value); ?></textarea>
                                                        <?php if (!empty($field['description'])): ?>
                                                            <p class="description" style="margin-top: 5px; font-size: 11px;"><?php echo esc_html($field['description']); ?></p>
                                                        <?php endif; ?>
                                                    <?php break;
                                                    
                                                    case 'sku_with_generate': ?>
                                                        <!-- SKU Field with Generate option -->
                                                        <div class="sku-field-options">
                                                            <div class="sku-mode-selector" style="margin-bottom: 10px;">
                                                                <label class="sku-mode-option" style="display: inline-flex; align-items: center; gap: 5px; margin-right: 15px; cursor: pointer;">
                                                                    <input type="radio" 
                                                                           name="field_mapping[<?php echo $field_key; ?>][sku_mode]" 
                                                                           value="map" 
                                                                           class="sku-mode-radio"
                                                                           checked>
                                                                    <span style="font-weight: 500;"><?php _e('Map from file', 'wc-xml-csv-import'); ?></span>
                                                                </label>
                                                                <label class="sku-mode-option" style="display: inline-flex; align-items: center; gap: 5px; cursor: pointer;">
                                                                    <input type="radio" 
                                                                           name="field_mapping[<?php echo $field_key; ?>][sku_mode]" 
                                                                           value="generate" 
                                                                           class="sku-mode-radio">
                                                                    <span style="font-weight: 500;"><?php _e('Auto-generate', 'wc-xml-csv-import'); ?></span>
                                                                </label>
                                                            </div>
                                                            
                                                            <!-- Map from file panel -->
                                                            <div class="sku-map-panel" style="display: block;">
                                                                <select name="field_mapping[<?php echo $field_key; ?>][source]" class="field-source-select" style="width: 100%;">
                                                                    <option value=""><?php _e('-- Select Source Field --', 'wc-xml-csv-import'); ?></option>
                                                                </select>
                                                            </div>
                                                            
                                                            <!-- Auto-generate panel -->
                                                            <div class="sku-generate-panel" style="display: none; background: #f0f7ff; padding: 12px; border-radius: 6px; border: 1px solid #c3d9f3;">
                                                                <label style="display: block; font-weight: 500; margin-bottom: 8px;">
                                                                    <?php _e('SKU Pattern:', 'wc-xml-csv-import'); ?>
                                                                </label>
                                                                <input type="text" 
                                                                       name="field_mapping[<?php echo $field_key; ?>][sku_pattern]" 
                                                                       class="sku-pattern-input" 
                                                                       value="PROD-{row}" 
                                                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                                                       placeholder="PROD-{row}">
                                                                <p class="description" style="font-size: 11px; margin-top: 8px; color: #666;">
                                                                    <strong><?php _e('Available placeholders:', 'wc-xml-csv-import'); ?></strong><br>
                                                                    <code>{row}</code> - <?php _e('Row number (1, 2, 3...)', 'wc-xml-csv-import'); ?><br>
                                                                    <code>{timestamp}</code> - <?php _e('Unix timestamp', 'wc-xml-csv-import'); ?><br>
                                                                    <code>{random}</code> - <?php _e('Random 6-char string', 'wc-xml-csv-import'); ?><br>
                                                                    <code>{name}</code> - <?php _e('Product name slug (first 20 chars)', 'wc-xml-csv-import'); ?><br>
                                                                    <code>{md5}</code> - <?php _e('MD5 hash from name+row (8 chars)', 'wc-xml-csv-import'); ?><br>
                                                                </p>
                                                                <div style="margin-top: 10px; padding: 8px; background: #fff; border-radius: 4px; border: 1px solid #ddd;">
                                                                    <span style="font-size: 11px; color: #666;"><?php _e('Preview:', 'wc-xml-csv-import'); ?></span>
                                                                    <code class="sku-preview" style="display: block; margin-top: 4px; font-size: 13px; color: #0073aa;">PROD-1</code>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php break;
                                                    
                                                    default: 
                                                        // Determine textarea size: large for description fields
                                                        $is_large_textarea = in_array($field_key, array('description', 'short_description', 'purchase_note', 'meta_description'));
                                                        $textarea_rows = $is_large_textarea ? 4 : 1;
                                                        $textarea_class = $is_large_textarea ? 'field-mapping-textarea field-mapping-textarea-large' : 'field-mapping-textarea field-mapping-textarea-small';
                                                        ?>
                                                        <div class="textarea-mapping-wrapper" data-field="<?php echo esc_attr($field_key); ?>">
                                                            <textarea name="field_mapping[<?php echo $field_key; ?>][source]" 
                                                                      class="<?php echo $textarea_class; ?>" 
                                                                      rows="<?php echo $textarea_rows; ?>"
                                                                      data-field-name="<?php echo esc_attr($field_key); ?>"
                                                                      placeholder="<?php echo esc_attr(sprintf(__('Type { to see fields or drag field here. E.g. {%s}', 'wc-xml-csv-import'), strtolower(str_replace('_', '', $field_key)))); ?>"
                                                            ></textarea>
                                                            <?php if (!empty($field['description'])): ?>
                                                                <p class="description" style="margin-top: 4px; font-size: 11px;"><?php echo esc_html($field['description']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                <?php endswitch; ?>
                                            </div>
                                            
                                            <?php if ($current_tier === 'pro'): ?>
                                            <div class="processing-mode">
                                                <select name="field_mapping[<?php echo $field_key; ?>][processing_mode]" class="processing-mode-select">
                                                    <option value="direct"><?php _e('Direct Mapping', 'wc-xml-csv-import'); ?></option>
                                                    <option value="php_formula"><?php _e('PHP Formula', 'wc-xml-csv-import'); ?></option>
                                                    <option value="ai_processing"><?php _e('AI Processing', 'wc-xml-csv-import'); ?></option>
                                                    <option value="hybrid"><?php _e('Hybrid (PHP + AI)', 'wc-xml-csv-import'); ?></option>
                                                </select>
                                            </div>
                                            <?php else: ?>
                                            <input type="hidden" name="field_mapping[<?php echo $field_key; ?>][processing_mode]" value="direct">
                                            <?php endif; ?>
                                            
                                            <?php if ($current_tier === 'pro'): ?>
                                            <div class="processing-config">
                                                <div class="config-content">
                                                    <!-- PHP Formula Config -->
                                                    <div class="php-formula-config config-panel">
                                                        <label><?php _e('PHP Formula:', 'wc-xml-csv-import'); ?></label>
                                                        <textarea name="field_mapping[<?php echo $field_key; ?>][php_formula]" 
                                                                  placeholder="<?php _e('e.g., $value * 1.2', 'wc-xml-csv-import'); ?>" 
                                                                  rows="3"></textarea>
                                                        <p class="description">
                                                            <?php _e('✅ Simple formulas work directly: <code>$value * 1.2</code>', 'wc-xml-csv-import'); ?><br>
                                                            <?php _e('✅ Complex formulas need return: <code>if ($value > 100) { return $value * 0.9; } return $value;</code>', 'wc-xml-csv-import'); ?><br>
                                                            <?php _e('✅ Access any XML field: <code>$value . " - " . $brand</code>', 'wc-xml-csv-import'); ?>
                                                        </p>
                                                        <button type="button" class="button button-small test-php-formula" 
                                                                data-field="<?php echo $field_key; ?>">
                                                            <?php _e('Test PHP Formula', 'wc-xml-csv-import'); ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- AI Processing Config -->
                                                    <div class="ai-processing-config config-panel">
                                                        <div class="ai-provider-selection">
                                                            <label><?php _e('AI Provider:', 'wc-xml-csv-import'); ?></label>
                                                            <select name="field_mapping[<?php echo $field_key; ?>][ai_provider]">
                                                                <?php foreach ($ai_providers as $provider_key => $provider_name): ?>
                                                                    <option value="<?php echo $provider_key; ?>" 
                                                                            <?php selected($settings['default_ai_provider'] ?? 'openai', $provider_key); ?>>
                                                                        <?php echo $provider_name; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <label><?php _e('AI Prompt:', 'wc-xml-csv-import'); ?></label>
                                                        <textarea name="field_mapping[<?php echo $field_key; ?>][ai_prompt]" 
                                                                  placeholder="<?php _e('e.g., Translate this product name to English and make it SEO-friendly', 'wc-xml-csv-import'); ?>" 
                                                                  rows="3"></textarea>
                                                        <button type="button" class="button button-small test-ai-field" 
                                                                data-field="<?php echo $field_key; ?>">
                                                            <?php _e('Test AI', 'wc-xml-csv-import'); ?>
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Hybrid Config -->
                                                    <div class="hybrid-config config-panel">
                                                        <label><?php _e('AI Prompt (executed first):', 'wc-xml-csv-import'); ?></label>
                                                        <textarea name="field_mapping[<?php echo $field_key; ?>][hybrid_ai_prompt]" 
                                                                  placeholder="<?php _e('e.g., Enhance this text for better readability', 'wc-xml-csv-import'); ?>" 
                                                                  rows="2"></textarea>
                                                        
                                                        <div class="ai-provider-selection" style="margin: 10px 0;">
                                                            <label><?php _e('AI Provider:', 'wc-xml-csv-import'); ?></label>
                                                            <select name="field_mapping[<?php echo $field_key; ?>][hybrid_ai_provider]">
                                                                <?php foreach ($ai_providers as $provider_key => $provider_name): ?>
                                                                    <option value="<?php echo $provider_key; ?>" 
                                                                        <?php selected($provider_key, 'openai'); ?>>
                                                                        <?php echo $provider_name; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <p class="description">
                                                            <strong><?php _e('Use {value} as input', 'wc-xml-csv-import'); ?></strong> - 
                                                            <?php _e('AI result will be passed to PHP formula', 'wc-xml-csv-import'); ?>
                                                        </p>
                                                        
                                                        <label style="margin-top: 15px; display: block;"><?php _e('PHP Formula (applied to AI result):', 'wc-xml-csv-import'); ?></label>
                                                        <textarea name="field_mapping[<?php echo $field_key; ?>][hybrid_php]" 
                                                                  placeholder="<?php _e('e.g., trim(strtolower($value))', 'wc-xml-csv-import'); ?>" 
                                                                  rows="2"></textarea>
                                                        <p class="description">
                                                            <strong><?php _e('Use $value as input', 'wc-xml-csv-import'); ?></strong> - 
                                                            <?php _e('Final technical adjustments (trim, lowercase, etc)', 'wc-xml-csv-import'); ?>
                                                        <p class="description">
                                                            <strong><?php _e('Use $value as input', 'wc-xml-csv-import'); ?></strong> - 
                                                            <?php _e('Final technical adjustments (trim, lowercase, etc)', 'wc-xml-csv-import'); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <!-- Update on Sync Checkbox - Controls whether this field updates in edit mode -->
                                            <!-- Only visible for PRO users (selective_update feature) -->
                                            <?php if ($can_selective_update): ?>
                                            <div class="update-on-sync-wrapper">
                                                <label>
                                                    <input type="checkbox" 
                                                           name="field_mapping[<?php echo $field_key; ?>][update_on_sync]" 
                                                           value="1" 
                                                           checked>
                                                    <span>
                                                        <?php _e('Update this field on re-import?', 'wc-xml-csv-import'); ?>
                                                    </span>
                                                </label>
                                                <p class="description">
                                                    <?php _e('Uncheck to prevent this field from being updated when re-importing existing products', 'wc-xml-csv-import'); ?>
                                                </p>
                                            </div>
                                            <?php else: ?>
                                            <!-- FREE version: hidden checkbox always checked (all fields update) -->
                                            <input type="hidden" name="field_mapping[<?php echo $field_key; ?>][update_on_sync]" value="1">
                                            <?php endif; ?>
                                            
                                            <?php if ($current_tier === 'pro'): ?>
                                            <div class="field-actions">
                                                <button type="button" class="button button-small toggle-config" title="<?php _e('Configure Processing', 'wc-xml-csv-import'); ?>">
                                                    <span class="dashicons dashicons-admin-generic"></span>
                                                </button>
                                                <button type="button" class="button button-small clear-mapping" title="<?php _e('Clear Mapping', 'wc-xml-csv-import'); ?>">
                                                    <span class="dashicons dashicons-no-alt"></span>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php endif; // End attributes_variations special handling ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Import Filters Section -->
                    <?php if ($can_filters_advanced): ?>
                    <div class="mapping-section import-filters-section">
                        <h3 style="display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; margin: 0; background: #f7f7f7;">
                            <span>
                                <span class="dashicons dashicons-filter"></span>
                                <?php _e('Import Filters', 'wc-xml-csv-import'); ?>
                            </span>
                            <button type="button" class="button button-small" id="add-filter-rule">
                                <span class="dashicons dashicons-plus" style="vertical-align: middle;"></span>
                                <?php _e('Add Filter', 'wc-xml-csv-import'); ?>
                            </button>
                        </h3>
                        <p class="description" style="margin: 10px 0 15px 0; padding: 0 15px;">
                            <?php _e('Filter which products to import based on field values. Products that don\'t match will be skipped.', 'wc-xml-csv-import'); ?>
                        </p>
                        
                        <div class="section-fields" id="section-import-filters" style="display: block;">
                            <div id="import-filters-container">
                                <p class="no-filters" style="padding: 15px; color: #666;">
                                    <?php _e('No filters added. All products will be imported.', 'wc-xml-csv-import'); ?>
                                </p>
                            </div>
                            
                            <div class="filter-logic-toggle" id="filter-logic-toggle" style="display: none; margin: 15px; padding: 12px; background: #f5f5f5; border-radius: 4px;">
                                <label style="font-weight: 600; margin-right: 10px;">
                                    <?php _e('Filter Logic:', 'wc-xml-csv-import'); ?>
                                </label>
                                <label style="margin-right: 20px;">
                                    <input type="radio" name="filter_logic" value="AND" checked />
                                    <strong>AND</strong> <?php _e('(all conditions must match)', 'wc-xml-csv-import'); ?>
                                </label>
                                <label>
                                    <input type="radio" name="filter_logic" value="OR" />
                                    <strong>OR</strong> <?php _e('(any condition can match)', 'wc-xml-csv-import'); ?>
                                </label>
                            </div>
                            
                            <!-- Filter Options Note -->
                            <div id="filter-options-note" style="display: none; margin: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">
                                    <span class="dashicons dashicons-info" style="color: #ffc107;"></span>
                                    <?php _e('Filter Behavior', 'wc-xml-csv-import'); ?>
                                </p>
                                <p style="margin: 0 0 10px 0; font-size: 13px; color: #856404;">
                                    <?php _e('Filters will be applied during import. Products that don\'t match will be skipped.', 'wc-xml-csv-import'); ?>
                                </p>
                                <label style="display: block; margin-top: 10px;">
                                    <input type="checkbox" name="draft_non_matching" value="1" id="draft-non-matching-checkbox" />
                                    <strong><?php _e('Move non-matching products to Draft', 'wc-xml-csv-import'); ?></strong>
                                    <br>
                                    <span style="font-size: 12px; color: #666; margin-left: 20px;">
                                        <?php _e('If re-running import, existing products that no longer match filters will be set to Draft status.', 'wc-xml-csv-import'); ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- Import Filters - FREE version (disabled with PRO badge) -->
                    <div class="mapping-section import-filters-section pro-feature-locked" style="opacity: 0.7;">
                        <h3>
                            <span class="dashicons dashicons-filter" style="color: #adb5bd;"></span>
                            <?php _e('Import Filters', 'wc-xml-csv-import'); ?>
                            <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: bold;">PRO</span>
                        </h3>
                        <div style="padding: 20px; background: #f8f9fa; border-radius: 4px; margin: 0 15px 15px 15px;">
                            <p style="margin: 0 0 15px 0; color: #6c757d;">
                                <?php _e('Filter products during import based on field values, regex patterns, and conditional logic. Only import products that match your criteria.', 'wc-xml-csv-import'); ?>
                            </p>
                            <ul style="margin: 0 0 15px 20px; color: #6c757d; font-size: 13px;">
                                <li><?php _e('Filter by price, stock, category, or any field', 'wc-xml-csv-import'); ?></li>
                                <li><?php _e('Use regex patterns for advanced matching', 'wc-xml-csv-import'); ?></li>
                                <li><?php _e('Combine multiple filters with AND/OR logic', 'wc-xml-csv-import'); ?></li>
                                <li><?php _e('Auto-draft products that no longer match filters', 'wc-xml-csv-import'); ?></li>
                            </ul>
                            <a href="<?php echo esc_url(WC_XML_CSV_AI_Import_License::get_upgrade_url()); ?>" target="_blank" class="button button-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <?php _e('Upgrade to PRO', 'wc-xml-csv-import'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Custom Fields Section -->
                    <div class="mapping-section custom-fields-section">
                        <h3 class="section-toggle" data-target="custom-fields">
                            <span class="dashicons dashicons-arrow-down"></span>
                            <?php _e('Custom Fields', 'wc-xml-csv-import'); ?>
                            <button type="button" class="button button-small add-custom-field" id="add-custom-field">
                                <span class="dashicons dashicons-plus"></span>
                                <?php _e('Add Custom Field', 'wc-xml-csv-import'); ?>
                            </button>
                        </h3>
                        
                        <div class="section-fields" id="section-custom-fields">
                            <div id="custom-fields-container">
                                <p class="no-custom-fields"><?php _e('No custom fields added yet. Click "Add Custom Field" to create one.', 'wc-xml-csv-import'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import&step=1'); ?>" class="button button-secondary">
                            <span class="button-icon">⬅️</span>
                            <?php _e('Back to Upload', 'wc-xml-csv-import'); ?>
                        </a>
                        
                        <div class="actions-right">
                            <button type="button" class="button button-secondary" id="save-mapping">
                                <?php _e('Save', 'wc-xml-csv-import'); ?>
                            </button>
                            
                            <button type="submit" class="button button-primary button-large" id="start-import">
                                <?php _e('Start Import', 'wc-xml-csv-import'); ?>
                                <span class="button-icon">🚀</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div id="mapping-messages" class="mapping-messages"></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Filter Rule Template -->
<script type="text/template" id="filter-rule-template">
    <div class="filter-rule-row" data-filter-index="{index}" style="display: flex; gap: 10px; align-items: center; padding: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px;">
        <div style="flex: 1;">
            <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php _e('Field', 'wc-xml-csv-import'); ?></label>
            <select name="import_filters[{index}][field]" class="filter-field-select" style="width: 100%;">
                <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
            </select>
        </div>
        
        <div style="flex: 0 0 150px;">
            <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php _e('Operator', 'wc-xml-csv-import'); ?></label>
            <select name="import_filters[{index}][operator]" class="filter-operator-select" style="width: 100%;">
                <option value="=">=</option>
                <option value="!=">!=</option>
                <option value=">">></option>
                <option value="<"><</option>
                <option value=">=">>=</option>
                <option value="<="><=</option>
                <option value="contains"><?php _e('contains', 'wc-xml-csv-import'); ?></option>
                <option value="not_contains"><?php _e('not contains', 'wc-xml-csv-import'); ?></option>
                <option value="empty"><?php _e('is empty', 'wc-xml-csv-import'); ?></option>
                <option value="not_empty"><?php _e('not empty', 'wc-xml-csv-import'); ?></option>
                <option value="regex_match"><?php _e('regex match', 'wc-xml-csv-import'); ?></option>
                <option value="regex_not_match"><?php _e('regex not match', 'wc-xml-csv-import'); ?></option>
            </select>
        </div>
        
        <div style="flex: 1;">
            <label style="display: block; font-size: 11px; color: #666; margin-bottom: 3px;"><?php _e('Value', 'wc-xml-csv-import'); ?></label>
            <input type="text" name="import_filters[{index}][value]" class="filter-value-input" placeholder="<?php _e('Comparison value', 'wc-xml-csv-import'); ?>" style="width: 100%;" />
        </div>
        
        <div style="flex: 0 0 40px;">
            <label style="display: block; font-size: 11px; color: transparent; margin-bottom: 3px;">.</label>
            <button type="button" class="button button-small remove-filter-rule" title="<?php _e('Remove Filter', 'wc-xml-csv-import'); ?>" style="padding: 6px 10px;">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
</script>

<!-- Custom Field Template -->
<script type="text/template" id="custom-field-template">
    <div class="field-mapping-row custom-field-row" data-field="custom-{index}">
        <div class="field-target">
            <input type="text" name="custom_fields[{index}][name]" placeholder="<?php _e('Custom Field Name', 'wc-xml-csv-import'); ?>" class="custom-field-name" />
            <select name="custom_fields[{index}][type]" class="custom-field-type">
                <option value="text"><?php _e('Text', 'wc-xml-csv-import'); ?></option>
                <option value="number"><?php _e('Number', 'wc-xml-csv-import'); ?></option>
                <option value="textarea"><?php _e('Textarea', 'wc-xml-csv-import'); ?></option>
                <option value="checkbox"><?php _e('Checkbox', 'wc-xml-csv-import'); ?></option>
                <option value="date"><?php _e('Date', 'wc-xml-csv-import'); ?></option>
                <option value="url"><?php _e('URL', 'wc-xml-csv-import'); ?></option>
            </select>
        </div>
        
        <div class="field-source">
            <select name="custom_fields[{index}][source]" class="field-source-select">
                <option value=""><?php _e('-- Select Source Field --', 'wc-xml-csv-import'); ?></option>
            </select>
        </div>
        
        <?php if ($current_tier === 'pro'): ?>
        <div class="processing-mode">
            <select name="custom_fields[{index}][processing_mode]" class="processing-mode-select">
                <option value="direct"><?php _e('Direct Mapping', 'wc-xml-csv-import'); ?></option>
                <option value="php_formula"><?php _e('PHP Formula', 'wc-xml-csv-import'); ?></option>
                <option value="ai_processing"><?php _e('AI Processing', 'wc-xml-csv-import'); ?></option>
                <option value="hybrid"><?php _e('Hybrid (PHP + AI)', 'wc-xml-csv-import'); ?></option>
            </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="custom_fields[{index}][processing_mode]" value="direct">
        <?php endif; ?>
        
        <!-- Update on Sync Checkbox - Controls whether this field updates in edit mode -->
        <!-- Only visible for PRO users (selective_update feature) -->
        <?php if ($can_selective_update): ?>
        <div class="update-on-sync-wrapper">
            <label>
                <input type="checkbox" 
                       name="custom_fields[{index}][update_on_sync]" 
                       value="1" 
                       checked>
                <span>
                    <?php _e('Update this field on re-import?', 'wc-xml-csv-import'); ?>
                </span>
            </label>
            <p class="description">
                <?php _e('Uncheck to prevent this field from being updated when re-importing existing products', 'wc-xml-csv-import'); ?>
            </p>
        </div>
        <?php else: ?>
        <!-- FREE version: hidden input always checked (all fields update) -->
        <input type="hidden" name="custom_fields[{index}][update_on_sync]" value="1">
        <?php endif; ?>
        
        <div class="field-actions">
            <button type="button" class="button button-small remove-custom-field" title="<?php _e('Remove Custom Field', 'wc-xml-csv-import'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>
</script>

<!-- Attribute Template -->
<script type="text/template" id="attribute-row-template">
    <div class="attribute-row" data-index="{{index}}" style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: start;">
            <div style="flex: 1;">
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Attribute Name (WooCommerce):', 'wc-xml-csv-import'); ?>
                    </label>
                    <input type="text" 
                           name="attributes[{{index}}][name]" 
                           class="attribute-name"
                           placeholder="<?php esc_attr_e('e.g., Izmērs, Krāsa, Material', 'wc-xml-csv-import'); ?>"
                           style="width: 100%; max-width: 300px;">
                    <p class="description"><?php _e('Display name in WooCommerce (any language). Auto-adds pa_ prefix.', 'wc-xml-csv-import'); ?></p>
                </div>
                
                <div style="margin-bottom: 15px; padding: 10px; background: #fff8e1; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('XML Attribute Key:', 'wc-xml-csv-import'); ?>
                        <span style="color: #d63638; font-weight: normal;">*</span>
                    </label>
                    <input type="text" 
                           name="attributes[{{index}}][xml_attribute_key]" 
                           class="attribute-xml-key"
                           placeholder="<?php esc_attr_e('e.g., size, color, material', 'wc-xml-csv-import'); ?>"
                           style="width: 100%; max-width: 300px;">
                    <p class="description" style="margin-top: 5px;">
                        <?php _e('<strong>Required for Map mode!</strong> The XML element name inside &lt;attributes&gt;.', 'wc-xml-csv-import'); ?><br>
                        <?php _e('Example: if XML has <code>&lt;attributes&gt;&lt;size&gt;S&lt;/size&gt;&lt;/attributes&gt;</code> → enter <code>size</code>', 'wc-xml-csv-import'); ?>
                    </p>
                </div>
                
                <div style="margin-bottom: 15px; display: none;" class="xml-attribute-name-field">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('XML Attribute Name (for name/value structure):', 'wc-xml-csv-import'); ?>
                    </label>
                    <input type="text" 
                           name="attributes[{{index}}][xml_attribute_name]" 
                           class="attribute-xml-name"
                           placeholder="<?php esc_attr_e('e.g., Material', 'wc-xml-csv-import'); ?>"
                           style="width: 100%; max-width: 300px;">
                    <p class="description"><?php _e('Only for XML with &lt;attribute&gt;&lt;name&gt;...&lt;/name&gt;&lt;value&gt;...&lt;/value&gt;&lt;/attribute&gt; structure', 'wc-xml-csv-import'); ?></p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Attribute Values (optional override):', 'wc-xml-csv-import'); ?>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: start; flex-wrap: wrap;">
                        <select name="attributes[{{index}}][values_source]" class="field-source-select attribute-values-source" style="flex: 1; max-width: 300px;">
                            <option value=""><?php _e('-- Select Source Field --', 'wc-xml-csv-import'); ?></option>
                        </select>
                        <?php if ($current_tier === 'pro'): ?>
                        <select name="attributes[{{index}}][values_processing_mode]" class="processing-mode-select attribute-values-processing" style="width: 150px;">
                            <option value="direct"><?php _e('Direct', 'wc-xml-csv-import'); ?></option>
                            <option value="php_formula"><?php _e('PHP Formula', 'wc-xml-csv-import'); ?></option>
                            <option value="ai_processing"><?php _e('AI Processing', 'wc-xml-csv-import'); ?></option>
                            <option value="hybrid"><?php _e('Hybrid', 'wc-xml-csv-import'); ?></option>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="attributes[{{index}}][values_processing_mode]" value="direct">
                        <?php endif; ?>
                    </div>
                    <?php if ($current_tier === 'pro'): ?>
                    <div class="attribute-values-config" style="margin-top: 10px; display: none;">
                        <div class="php-formula-config config-panel" style="display: none;">
                            <label style="font-size: 12px; font-weight: 600;"><?php _e('PHP Formula:', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][values_php_formula]" placeholder="<?php _e('e.g., explode(\",\", $value)', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                        </div>
                        <div class="ai-prompt-config config-panel" style="display: none;">
                            <label style="font-size: 12px; font-weight: 600;"><?php _e('AI Prompt:', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][values_ai_prompt]" placeholder="<?php _e('e.g., Extract color names from this text', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                        </div>
                        <div class="hybrid-config config-panel" style="display: none;">
                            <label style="font-size: 12px; font-weight: 600;"><?php _e('PHP Formula (runs first):', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][values_hybrid_php]" placeholder="<?php _e('Pre-process with PHP', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                            <label style="font-size: 12px; font-weight: 600; margin-top: 8px;"><?php _e('AI Prompt (runs after):', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][values_hybrid_ai]" placeholder="<?php _e('Then process with AI', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                    <p class="description"><?php _e('Leave empty for XML &lt;attributes&gt; auto-mapping, or select field for direct mapping', 'wc-xml-csv-import'); ?></p>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Attribute Image (optional):', 'wc-xml-csv-import'); ?>
                    </label>
                    <div style="display: flex; gap: 10px; align-items: start; flex-wrap: wrap;">
                        <select name="attributes[{{index}}][image_source]" class="field-source-select attribute-image-source" style="flex: 1; max-width: 300px;">
                            <option value=""><?php _e('-- Select Source Field --', 'wc-xml-csv-import'); ?></option>
                        </select>
                        <?php if ($current_tier === 'pro'): ?>
                        <select name="attributes[{{index}}][image_processing_mode]" class="processing-mode-select attribute-image-processing" style="width: 150px;">
                            <option value="direct"><?php _e('Direct', 'wc-xml-csv-import'); ?></option>
                            <option value="php_formula"><?php _e('PHP Formula', 'wc-xml-csv-import'); ?></option>
                            <option value="ai_processing"><?php _e('AI Processing', 'wc-xml-csv-import'); ?></option>
                            <option value="hybrid"><?php _e('Hybrid', 'wc-xml-csv-import'); ?></option>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="attributes[{{index}}][image_processing_mode]" value="direct">
                        <?php endif; ?>
                    </div>
                    <?php if ($current_tier === 'pro'): ?>
                    <div class="attribute-image-config" style="margin-top: 10px; display: none;">
                        <div class="php-formula-config config-panel" style="display: none;">
                            <label style="font-size: 12px; font-weight: 600;"><?php _e('PHP Formula:', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][image_php_formula]" placeholder="<?php _e('e.g., str_replace(\"small\", \"large\", $value)', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                        </div>
                        <div class="ai-prompt-config config-panel" style="display: none;">
                            <label style="font-size: 12px; font-weight: 600;"><?php _e('AI Prompt:', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][image_ai_prompt]" placeholder="<?php _e('e.g., Extract image URL from HTML', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                        </div>
                        <div class="hybrid-config config-panel" style="display: none;">
                            <label style="font-size: 12px; font-weight: 600;"><?php _e('PHP Formula (runs first):', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][image_hybrid_php]" placeholder="<?php _e('Pre-process with PHP', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                            <label style="font-size: 12px; font-weight: 600; margin-top: 8px;"><?php _e('AI Prompt (runs after):', 'wc-xml-csv-import'); ?></label>
                            <textarea name="attributes[{{index}}][image_hybrid_ai]" placeholder="<?php _e('Then process with AI', 'wc-xml-csv-import'); ?>" rows="2" style="width: 100%; max-width: 500px;"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                    <p class="description"><?php _e('Image URL for this attribute (e.g., color swatch, size chart). For variations, use variation image mapping below.', 'wc-xml-csv-import'); ?></p>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <label style="display: inline-block; margin-right: 20px;">
                        <input type="checkbox" name="attributes[{{index}}][visible]" value="1" class="attribute-visible">
                        <?php _e('Visible on product page', 'wc-xml-csv-import'); ?>
                    </label>
                    
                    <label style="display: inline-block;">
                        <input type="checkbox" name="attributes[{{index}}][used_for_variations]" value="1" class="attribute-variation-checkbox attribute-variations">
                        <strong><?php _e('Used for variations', 'wc-xml-csv-import'); ?></strong>
                    </label>
                </div>
                
                <!-- Variation Settings (shown when "Used for variations" is checked) -->
                <div class="variation-attribute-settings" style="display: none; margin-top: 15px; padding: 15px; background: #f0f8ff; border: 2px solid #2271b1; border-radius: 6px;">
                    <h4 style="margin: 0 0 15px 0; color: #2271b1;">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Variation Adjustments for this Attribute', 'wc-xml-csv-import'); ?>
                    </h4>
                    <p class="description" style="margin-bottom: 15px;">
                        <?php _e('These settings apply to ALL variations created from this attribute. Leave as "No change" to use parent product values.', 'wc-xml-csv-import'); ?>
                    </p>
                    
                    <!-- Price Adjustment -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-tag" style="color: #2271b1;"></span>
                            <?php _e('Price Adjustment:', 'wc-xml-csv-import'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_price_type]" class="var-price-type-select" style="width: 130px;">
                                <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                <option value="operator"><?php _e('Calculate', 'wc-xml-csv-import'); ?></option>
                                <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <!-- Calculate config -->
                            <div class="var-price-operator-config" style="display: none; gap: 5px; align-items: center;">
                                <select name="attributes[{{index}}][var_price_operator]" style="width: 60px;">
                                    <option value="+">+</option>
                                    <option value="-">−</option>
                                    <option value="*">×</option>
                                    <option value="/">÷</option>
                                </select>
                                <input type="number" step="0.01" name="attributes[{{index}}][var_price_value]" placeholder="0" style="width: 100px;">
                            </div>
                            <!-- Map field config -->
                            <div class="var-price-map-config" style="display: none; gap: 10px; align-items: center; flex: 1;">
                                <select name="attributes[{{index}}][var_price_source]" class="field-source-select" style="min-width: 200px;">
                                    <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                </select>
                                <?php if ($current_tier === 'pro'): ?>
                                <select name="attributes[{{index}}][var_price_processing]" class="var-price-processing-select" style="width: 130px;">
                                    <option value="direct"><?php _e('Direct Mapping', 'wc-xml-csv-import'); ?></option>
                                    <option value="php_formula"><?php _e('PHP Formula', 'wc-xml-csv-import'); ?></option>
                                    <option value="ai_processing"><?php _e('AI Processing', 'wc-xml-csv-import'); ?></option>
                                    <option value="hybrid"><?php _e('Hybrid', 'wc-xml-csv-import'); ?></option>
                                </select>
                                <?php else: ?>
                                <input type="hidden" name="attributes[{{index}}][var_price_processing]" value="direct">
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($current_tier === 'pro'): ?>
                        <!-- Processing configs for mapped price -->
                        <div class="var-price-processing-config" style="display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                            <!-- PHP Formula -->
                            <div class="var-price-php-config config-panel" style="display: none;">
                                <textarea name="attributes[{{index}}][var_price_php_formula]" 
                                          placeholder="<?php esc_attr_e('e.g., $value * 1.21', 'wc-xml-csv-import'); ?>" 
                                          rows="2" style="width: 100%; max-width: 500px;"></textarea>
                                <p class="description" style="font-size: 11px;"><?php _e('Variables: $value, $parent_price, $sku, etc.', 'wc-xml-csv-import'); ?></p>
                            </div>
                            <!-- AI Processing -->
                            <div class="var-price-ai-config config-panel" style="display: none;">
                                <textarea name="attributes[{{index}}][var_price_ai_prompt]" 
                                          placeholder="<?php esc_attr_e('e.g., Convert price and add 21% VAT', 'wc-xml-csv-import'); ?>" 
                                          rows="2" style="width: 100%; max-width: 500px;"></textarea>
                            </div>
                            <!-- Hybrid -->
                            <div class="var-price-hybrid-config config-panel" style="display: none;">
                                <div style="margin-bottom: 8px;">
                                    <label style="font-size: 11px; color: #666;"><?php _e('PHP Pre-processing:', 'wc-xml-csv-import'); ?></label>
                                    <textarea name="attributes[{{index}}][var_price_hybrid_php]" 
                                              placeholder="<?php esc_attr_e('$value * 1.21', 'wc-xml-csv-import'); ?>" 
                                              rows="1" style="width: 100%; max-width: 500px;"></textarea>
                                </div>
                                <div>
                                    <label style="font-size: 11px; color: #666;"><?php _e('AI Prompt:', 'wc-xml-csv-import'); ?></label>
                                    <textarea name="attributes[{{index}}][var_price_hybrid_ai]" 
                                              placeholder="<?php esc_attr_e('Round to nearest .99', 'wc-xml-csv-import'); ?>" 
                                              rows="1" style="width: 100%; max-width: 500px;"></textarea>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stock Adjustment -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-products" style="color: #2271b1;"></span>
                            <?php _e('Stock Adjustment:', 'wc-xml-csv-import'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_stock_type]" class="var-stock-type-select" style="width: 130px;">
                                <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                <option value="operator"><?php _e('Calculate', 'wc-xml-csv-import'); ?></option>
                                <option value="fixed"><?php _e('Fixed value', 'wc-xml-csv-import'); ?></option>
                                <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <div class="var-stock-operator-config" style="display: none; flex: 1; gap: 5px; align-items: center;">
                                <select name="attributes[{{index}}][var_stock_operator]" style="width: 60px;">
                                    <option value="+">+</option>
                                    <option value="-">−</option>
                                </select>
                                <input type="number" step="1" name="attributes[{{index}}][var_stock_value]" placeholder="0" style="width: 100px;">
                            </div>
                            <div class="var-stock-fixed-config" style="display: none; flex: 1;">
                                <input type="number" step="1" name="attributes[{{index}}][var_stock_fixed]" placeholder="10" style="width: 100px;">
                            </div>
                            <div class="var-stock-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_stock_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sale Price -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-money-alt" style="color: #d63638;"></span>
                            <?php _e('Sale Price:', 'wc-xml-csv-import'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_sale_price_type]" class="var-sale-price-type-select" style="width: 130px;">
                                <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                <option value="fixed"><?php _e('Fixed value', 'wc-xml-csv-import'); ?></option>
                                <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <div class="var-sale-price-fixed-config" style="display: none; flex: 1;">
                                <input type="number" step="0.01" name="attributes[{{index}}][var_sale_price_fixed]" placeholder="0.00" style="width: 120px;">
                            </div>
                            <div class="var-sale-price-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_sale_price_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SKU -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-tag" style="color: #135e96;"></span>
                            <?php _e('SKU:', 'wc-xml-csv-import'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_sku_type]" class="var-sku-type-select" style="width: 130px;">
                                <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                <option value="suffix"><?php _e('Suffix', 'wc-xml-csv-import'); ?></option>
                                <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <div class="var-sku-suffix-config" style="display: none; flex: 1;">
                                <input type="text" name="attributes[{{index}}][var_sku_suffix]" placeholder="<?php esc_attr_e('e.g., -red, -xl', 'wc-xml-csv-import'); ?>" style="width: 150px;">
                                <span style="color: #666; font-size: 11px;"><?php _e('Added to parent SKU', 'wc-xml-csv-import'); ?></span>
                            </div>
                            <div class="var-sku-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_sku_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- GTIN/UPC/EAN/ISBN -->
                    <div class="setting-group" style="margin-bottom: 15px;">
                        <label style="font-weight: 600; display: block; margin-bottom: 8px;">
                            <span class="dashicons dashicons-barcode" style="color: #135e96;"></span>
                            <?php _e('GTIN/UPC/EAN/ISBN:', 'wc-xml-csv-import'); ?>
                        </label>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <select name="attributes[{{index}}][var_gtin_type]" class="var-gtin-type-select" style="width: 130px;">
                                <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                            </select>
                            <div class="var-gtin-map-config" style="display: none; flex: 1;">
                                <select name="attributes[{{index}}][var_gtin_source]" class="field-source-select" style="flex: 1;">
                                    <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Settings (collapsed) -->
                    <details style="margin-top: 15px;">
                        <summary style="cursor: pointer; font-weight: 600; padding: 8px; background: #e8f4fc; border-radius: 3px;">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                            <?php _e('Advanced (Status, Shipping, Dimensions)', 'wc-xml-csv-import'); ?>
                        </summary>
                        <div style="padding: 15px; background: #fafafa; border-radius: 3px; margin-top: 5px;">
                            
                            <!-- Status Fields Row -->
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                                <!-- Enabled -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Whether the variation is available for purchase on the frontend', 'wc-xml-csv-import'); ?>">
                                        <?php _e('Enabled:', 'wc-xml-csv-import'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_enabled]" style="width: 100%;">
                                        <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                        <option value="yes"><?php _e('Yes', 'wc-xml-csv-import'); ?></option>
                                        <option value="no"><?php _e('No', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php _e('Show on frontend', 'wc-xml-csv-import'); ?></p>
                                </div>
                                
                                <!-- Virtual -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Virtual products have no shipping (e.g., services, consultations)', 'wc-xml-csv-import'); ?>">
                                        <?php _e('Virtual:', 'wc-xml-csv-import'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_virtual]" style="width: 100%;">
                                        <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                        <option value="yes"><?php _e('Yes', 'wc-xml-csv-import'); ?></option>
                                        <option value="no"><?php _e('No', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php _e('No shipping needed', 'wc-xml-csv-import'); ?></p>
                                </div>
                                
                                <!-- Downloadable -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Downloadable products give access to files after purchase (e.g., ebooks, software)', 'wc-xml-csv-import'); ?>">
                                        <?php _e('Downloadable:', 'wc-xml-csv-import'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_downloadable]" style="width: 100%;">
                                        <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                        <option value="yes"><?php _e('Yes', 'wc-xml-csv-import'); ?></option>
                                        <option value="no"><?php _e('No', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php _e('Has file downloads', 'wc-xml-csv-import'); ?></p>
                                </div>
                                
                                <!-- Manage Stock -->
                                <div class="setting-group">
                                    <label style="font-weight: 600; display: block; margin-bottom: 4px;" title="<?php esc_attr_e('Enable stock management at variation level (track inventory for each variation separately)', 'wc-xml-csv-import'); ?>">
                                        <?php _e('Manage Stock:', 'wc-xml-csv-import'); ?>
                                        <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px; color: #666; cursor: help;"></span>
                                    </label>
                                    <select name="attributes[{{index}}][var_manage_stock]" style="width: 100%;">
                                        <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                        <option value="yes"><?php _e('Yes', 'wc-xml-csv-import'); ?></option>
                                        <option value="no"><?php _e('No', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <p class="description" style="font-size: 10px; color: #888; margin: 4px 0 0 0;"><?php _e('Track inventory', 'wc-xml-csv-import'); ?></p>
                                </div>
                            </div>
                            
                            <!-- Weight -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php _e('Weight:', 'wc-xml-csv-import'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select name="attributes[{{index}}][var_weight_type]" class="var-weight-type-select" style="width: 130px;">
                                        <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                        <option value="operator"><?php _e('Calculate', 'wc-xml-csv-import'); ?></option>
                                        <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <div class="var-weight-operator-config" style="display: none; flex: 1; gap: 5px;">
                                        <select name="attributes[{{index}}][var_weight_operator]" style="width: 60px;">
                                            <option value="+">+</option>
                                            <option value="-">−</option>
                                            <option value="*">×</option>
                                        </select>
                                        <input type="number" step="0.01" name="attributes[{{index}}][var_weight_value]" placeholder="0" style="width: 100px;">
                                        <span style="color: #666; font-size: 12px;">kg</span>
                                    </div>
                                    <div class="var-weight-map-config" style="display: none; flex: 1;">
                                        <select name="attributes[{{index}}][var_weight_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Dimensions -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php _e('Dimensions (L×W×H):', 'wc-xml-csv-import'); ?></label>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php _e('Length:', 'wc-xml-csv-import'); ?></label>
                                        <select name="attributes[{{index}}][var_length_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php _e('Width:', 'wc-xml-csv-import'); ?></label>
                                        <select name="attributes[{{index}}][var_width_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="font-size: 11px; color: #666;"><?php _e('Height:', 'wc-xml-csv-import'); ?></label>
                                        <select name="attributes[{{index}}][var_height_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Variation Image -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php _e('Variation Image:', 'wc-xml-csv-import'); ?></label>
                                <select name="attributes[{{index}}][var_image_source]" class="field-source-select" style="width: 100%;">
                                    <option value=""><?php _e('-- Select Image Field --', 'wc-xml-csv-import'); ?></option>
                                </select>
                            </div>
                            
                            <!-- Shipping Class -->
                            <div class="setting-group" style="margin-bottom: 15px;">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php _e('Shipping Class:', 'wc-xml-csv-import'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <select name="attributes[{{index}}][var_shipping_class_type]" class="var-shipping-class-type-select" style="width: 130px;">
                                        <option value="none"><?php _e('No change', 'wc-xml-csv-import'); ?></option>
                                        <option value="map"><?php _e('Map field', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <div class="var-shipping-class-map-config" style="display: none; flex: 1;">
                                        <select name="attributes[{{index}}][var_shipping_class_source]" class="field-source-select" style="width: 100%;">
                                            <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="setting-group">
                                <label style="font-weight: 600; display: block; margin-bottom: 8px;"><?php _e('Description:', 'wc-xml-csv-import'); ?></label>
                                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                    <select name="attributes[{{index}}][var_description_source]" class="field-source-select var-description-source-select" style="min-width: 200px;">
                                        <option value=""><?php _e('-- Select Field --', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <?php if ($current_tier === 'pro'): ?>
                                    <select name="attributes[{{index}}][var_description_processing]" class="var-description-processing-select" style="width: 130px;">
                                        <option value="direct"><?php _e('Direct Mapping', 'wc-xml-csv-import'); ?></option>
                                        <option value="php_formula"><?php _e('PHP Formula', 'wc-xml-csv-import'); ?></option>
                                        <option value="ai_processing"><?php _e('AI Processing', 'wc-xml-csv-import'); ?></option>
                                        <option value="hybrid"><?php _e('Hybrid', 'wc-xml-csv-import'); ?></option>
                                    </select>
                                    <?php else: ?>
                                    <input type="hidden" name="attributes[{{index}}][var_description_processing]" value="direct">
                                    <?php endif; ?>
                                </div>
                                <?php if ($current_tier === 'pro'): ?>
                                <!-- Processing configs for description -->
                                <div class="var-description-processing-config" style="display: none; margin-top: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                                    <!-- PHP Formula -->
                                    <div class="var-description-php-config config-panel" style="display: none;">
                                        <textarea name="attributes[{{index}}][var_description_php_formula]" 
                                                  placeholder="<?php esc_attr_e('e.g., \"Size: \" . $value . \" - \" . $parent_description', 'wc-xml-csv-import'); ?>" 
                                                  rows="2" style="width: 100%; max-width: 500px;"></textarea>
                                        <p class="description" style="font-size: 11px;"><?php _e('Variables: $value, $parent_description, $sku, $name, etc.', 'wc-xml-csv-import'); ?></p>
                                    </div>
                                    <!-- AI Processing -->
                                    <div class="var-description-ai-config config-panel" style="display: none;">
                                        <textarea name="attributes[{{index}}][var_description_ai_prompt]" 
                                                  placeholder="<?php esc_attr_e('e.g., Generate unique variation description', 'wc-xml-csv-import'); ?>" 
                                                  rows="2" style="width: 100%; max-width: 500px;"></textarea>
                                    </div>
                                    <!-- Hybrid -->
                                    <div class="var-description-hybrid-config config-panel" style="display: none;">
                                        <div style="margin-bottom: 8px;">
                                            <label style="font-size: 11px; color: #666;"><?php _e('PHP Pre-processing:', 'wc-xml-csv-import'); ?></label>
                                            <textarea name="attributes[{{index}}][var_description_hybrid_php]" 
                                                      placeholder="<?php esc_attr_e('$parent_description . \" | \" . $value', 'wc-xml-csv-import'); ?>" 
                                                      rows="1" style="width: 100%; max-width: 500px;"></textarea>
                                        </div>
                                        <div>
                                            <label style="font-size: 11px; color: #666;"><?php _e('AI Prompt:', 'wc-xml-csv-import'); ?></label>
                                            <textarea name="attributes[{{index}}][var_description_hybrid_ai]" 
                                                      placeholder="<?php esc_attr_e('Improve and make unique', 'wc-xml-csv-import'); ?>" 
                                                      rows="1" style="width: 100%; max-width: 500px;"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
            
            <button type="button" class="button button-link-delete remove-attribute" data-index="{{index}}" style="margin-left: 10px;">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
    </div>
</script>

<script>
// Pass data to JavaScript
var wcAiImportData = {
    file_path: '<?php echo esc_js($file_path); ?>',
    file_type: '<?php echo esc_js($file_type); ?>',
    import_name: '<?php echo esc_js($import_name); ?>',
    schedule_type: '<?php echo esc_js($schedule_type); ?>',
    product_wrapper: '<?php echo esc_js($product_wrapper); ?>',
    update_existing: '<?php echo esc_js($update_existing); ?>',
    skip_unchanged: '<?php echo esc_js($skip_unchanged); ?>',
    ajax_url: wc_xml_csv_ai_import_ajax.ajax_url,
    nonce: wc_xml_csv_ai_import_ajax.nonce,
    total_products: <?php echo intval($total_products_from_session); ?>,
    saved_mappings: <?php echo json_encode($saved_mappings); ?>,
    saved_custom_fields: <?php echo json_encode($saved_custom_fields); ?>
};

// Show product count if available
jQuery(document).ready(function($) {
    if (wcAiImportData.total_products > 0) {
        $('#total-products-count').text(wcAiImportData.total_products.toLocaleString());
        $('#total-products-info').show();
    }
    
    // Schedule Type change - show/hide schedule_method_row
    $('select[name="schedule_type"]').on('change', function() {
        var selectedValue = $(this).val();
        if (selectedValue && selectedValue !== 'disabled') {
            $('#schedule_method_row_new').show();
        } else {
            $('#schedule_method_row_new').hide();
        }
    });
    
    // Schedule Method change - show/hide server cron URL
    $('input[name="schedule_method"]').on('change', function() {
        var selectedMethod = $(this).val();
        
        // Update radio button styles
        $('input[name="schedule_method"]').each(function() {
            var $label = $(this).closest('label');
            if ($(this).is(':checked')) {
                $label.css({'border-color': '#0073aa', 'background': '#f0f6fc'});
            } else {
                $label.css({'border-color': '#ddd', 'background': '#fff'});
            }
        });
        
        // Show/hide server cron URL
        if (selectedMethod === 'server_cron') {
            $('#server_cron_url_new').show();
        } else {
            $('#server_cron_url_new').hide();
        }
    });
});
</script>