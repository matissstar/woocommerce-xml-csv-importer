<?php
/**
 * Plugin Settings Page
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Save settings
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed -- Using wp_unslash below
if (isset($_POST['submit']) && check_admin_referer('wc_xml_csv_ai_import_settings')) {
    // AI Provider Settings
    $ai_settings = array(
        'openai_api_key' => sanitize_text_field(wp_unslash($_POST['openai_api_key'] ?? '')),
        'openai_model' => sanitize_text_field(wp_unslash($_POST['openai_model'] ?? 'gpt-3.5-turbo')),
        'gemini_api_key' => sanitize_text_field(wp_unslash($_POST['gemini_api_key'] ?? '')),
        'gemini_model' => sanitize_text_field(wp_unslash($_POST['gemini_model'] ?? 'gemini-pro')),
        'claude_api_key' => sanitize_text_field(wp_unslash($_POST['claude_api_key'] ?? '')),
        'claude_model' => sanitize_text_field(wp_unslash($_POST['claude_model'] ?? 'claude-opus-4-20250514')),
        'grok_api_key' => sanitize_text_field(wp_unslash($_POST['grok_api_key'] ?? '')),
        'grok_model' => sanitize_text_field(wp_unslash($_POST['grok_model'] ?? 'grok-beta')),
        'copilot_api_key' => sanitize_text_field(wp_unslash($_POST['copilot_api_key'] ?? '')),
        'copilot_model' => sanitize_text_field(wp_unslash($_POST['copilot_model'] ?? 'gpt-4')),
        'default_provider' => sanitize_text_field(wp_unslash($_POST['default_provider'] ?? 'openai')),
        'enable_fallback' => isset($_POST['enable_fallback']) ? 1 : 0,
        'ai_timeout' => absint($_POST['ai_timeout'] ?? 30),
        'ai_max_retries' => absint($_POST['ai_max_retries'] ?? 3),
        'enable_ai_cache' => isset($_POST['enable_ai_cache']) ? 1 : 0,
        'ai_cache_ttl' => absint($_POST['ai_cache_ttl'] ?? 3600),
    );
    
    // Performance Settings
    $performance_settings = array(
        'batch_size' => absint($_POST['batch_size'] ?? 50),
        'memory_limit' => sanitize_text_field(wp_unslash($_POST['memory_limit'] ?? '512M')),
        'max_execution_time' => absint($_POST['max_execution_time'] ?? 300),
        'chunk_size' => absint($_POST['chunk_size'] ?? 1000),
        'enable_background_processing' => isset($_POST['enable_background_processing']) ? 1 : 0,
        'background_batch_size' => absint($_POST['background_batch_size'] ?? 10),
        'background_interval' => absint($_POST['background_interval'] ?? 60),
    );
    
    // Import Settings
    $import_settings = array(
        'default_product_status' => sanitize_text_field(wp_unslash($_POST['default_product_status'] ?? 'draft')),
        'update_existing_products' => isset($_POST['update_existing_products']) ? 1 : 0,
        'duplicate_handling' => sanitize_text_field(wp_unslash($_POST['duplicate_handling'] ?? 'skip')),
        'enable_image_download' => isset($_POST['enable_image_download']) ? 1 : 0,
        'image_timeout' => absint($_POST['image_timeout'] ?? 30),
        'max_image_size' => absint($_POST['max_image_size'] ?? 5) * 1024 * 1024, // Convert MB to bytes
        'enable_variation_import' => isset($_POST['enable_variation_import']) ? 1 : 0,
        'enable_category_creation' => isset($_POST['enable_category_creation']) ? 1 : 0,
        'enable_tag_creation' => isset($_POST['enable_tag_creation']) ? 1 : 0,
        'preserve_html' => isset($_POST['preserve_html']) ? 1 : 0,
    );
    
    // File Settings
    $file_settings = array(
        'max_file_size' => absint($_POST['max_file_size'] ?? 100) * 1024 * 1024, // Convert MB to bytes
        'allowed_file_types' => array_map('sanitize_text_field', array_map('wp_unslash', (array) ($_POST['allowed_file_types'] ?? array('xml', 'csv')))),
        'upload_directory' => sanitize_text_field(wp_unslash($_POST['upload_directory'] ?? 'wc-xml-csv-import')),
        'auto_delete_files' => isset($_POST['auto_delete_files']) ? 1 : 0,
        'file_retention_days' => absint($_POST['file_retention_days'] ?? 30),
    );
    
    // Logging Settings
    $logging_settings = array(
        'enable_logging' => isset($_POST['enable_logging']) ? 1 : 0,
        'log_level' => sanitize_text_field(wp_unslash($_POST['log_level'] ?? 'info')),
        'max_log_entries' => absint($_POST['max_log_entries'] ?? 10000),
        'log_retention_days' => absint($_POST['log_retention_days'] ?? 30),
        'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
        'notification_email' => sanitize_email(wp_unslash($_POST['notification_email'] ?? get_option('admin_email'))),
    );
    
    // Security Settings
    $security_settings = array(
        'allowed_php_functions' => array_map('sanitize_text_field', array_map('wp_unslash', (array) ($_POST['allowed_php_functions'] ?? array('strlen', 'substr', 'trim', 'strtoupper', 'strtolower', 'ucfirst', 'number_format')))),
        'enable_formula_validation' => isset($_POST['enable_formula_validation']) ? 1 : 0,
        'max_formula_length' => absint($_POST['max_formula_length'] ?? 500),
        'enable_sanitization' => isset($_POST['enable_sanitization']) ? 1 : 0,
    );
    
    // Save all settings
    update_option('wc_xml_csv_ai_import_ai_settings', $ai_settings);
    update_option('wc_xml_csv_ai_import_performance_settings', $performance_settings);
    update_option('wc_xml_csv_ai_import_import_settings', $import_settings);
    update_option('wc_xml_csv_ai_import_file_settings', $file_settings);
    update_option('wc_xml_csv_ai_import_logging_settings', $logging_settings);
    update_option('wc_xml_csv_ai_import_security_settings', $security_settings);
    
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'bootflow-product-importer') . '</p></div>';
}

// Get current settings
$ai_settings = get_option('wc_xml_csv_ai_import_ai_settings', array());
$performance_settings = get_option('wc_xml_csv_ai_import_performance_settings', array());
$import_settings = get_option('wc_xml_csv_ai_import_import_settings', array());
$file_settings = get_option('wc_xml_csv_ai_import_file_settings', array());
$logging_settings = get_option('wc_xml_csv_ai_import_logging_settings', array());
$security_settings = get_option('wc_xml_csv_ai_import_security_settings', array());
?>

<div class="wrap">
    <h1><?php esc_html_e('XML/CSV AI Smart Import - Settings', 'bootflow-product-importer'); ?></h1>
    
    <?php $current_tier = WC_XML_CSV_AI_Import_License::get_tier(); ?>
    <nav class="nav-tab-wrapper">
        <a href="#license" class="nav-tab nav-tab-active"><?php esc_html_e('License', 'bootflow-product-importer'); ?></a>
        <?php if ($current_tier === 'pro'): ?>
        <a href="#ai-providers" class="nav-tab"><?php esc_html_e('AI Providers', 'bootflow-product-importer'); ?></a>
        <?php endif; ?>
        <a href="#performance" class="nav-tab"><?php esc_html_e('Performance', 'bootflow-product-importer'); ?></a>
        <a href="#import" class="nav-tab"><?php esc_html_e('Import', 'bootflow-product-importer'); ?></a>
        <a href="#scheduling" class="nav-tab"><?php esc_html_e('Scheduling', 'bootflow-product-importer'); ?></a>
        <a href="#files" class="nav-tab"><?php esc_html_e('Files', 'bootflow-product-importer'); ?></a>
        <a href="#logging" class="nav-tab"><?php esc_html_e('Logging', 'bootflow-product-importer'); ?></a>
        <a href="#security" class="nav-tab"><?php esc_html_e('Security', 'bootflow-product-importer'); ?></a>
    </nav>
    
    <form method="post" action="">
        <?php wp_nonce_field('wc_xml_csv_ai_import_settings'); ?>
        
        <!-- License Tab -->
        <div id="license" class="tab-content active">
            <h2><?php esc_html_e('License Management', 'bootflow-product-importer'); ?></h2>
            
            <?php
            $current_tier = WC_XML_CSV_AI_Import_License::get_tier();
            $plugin_settings = get_option('wc_xml_csv_ai_import_settings', array());
            $license_key = isset($plugin_settings['license_key']) ? $plugin_settings['license_key'] : '';
            $license_status = !empty($license_key) ? 'active' : 'inactive';
            
            // Tier colors and icons (2-tier system: FREE and PRO)
            $tier_configs = array(
                'free' => array('color' => '#6c757d', 'gradient' => 'linear-gradient(135deg, #6c757d 0%, #95a5a6 100%)', 'icon' => '🆓'),
                'pro' => array('color' => '#0d7377', 'gradient' => 'linear-gradient(135deg, #0d7377 0%, #14919b 100%)', 'icon' => '⚡')
            );
            $tier_config = $tier_configs[$current_tier] ?? $tier_configs['free'];
            ?>
            
            <!-- Current Tier Display -->
            <div style="background: <?php echo esc_attr($tier_config['gradient']); ?>; border-radius: 12px; padding: 25px; margin-bottom: 25px; color: white; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                    <div style="font-size: 48px;"><?php echo esc_attr($tier_config['icon']); ?></div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 5px 0; color: white; font-size: 24px; text-transform: uppercase; letter-spacing: 1px;">
                            <?php echo esc_html(strtoupper($current_tier)); ?> <?php esc_html_e('Edition', 'bootflow-product-importer'); ?>
                        </h3>
                        <p style="margin: 0; font-size: 14px; color: rgba(255,255,255,0.9);">
                            <?php if ($current_tier === 'free'): ?>
                                <?php esc_html_e('Full manual import tool. Upgrade to PRO for automation, templates, and AI features!', 'bootflow-product-importer'); ?>
                            <?php else: ?>
                                <?php esc_html_e('All features unlocked including AI Auto-Mapping, templates, and scheduled imports!', 'bootflow-product-importer'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($license_status === 'active' && !empty($license_key)): ?>
                        <div style="text-align: right;">
                            <span style="background: rgba(255,255,255,0.2); padding: 5px 15px; border-radius: 20px; font-size: 12px;">
                                ✓ <?php esc_html_e('License Active', 'bootflow-product-importer'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- License Key Input -->
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 25px; margin-bottom: 25px;">
                <h3 style="margin: 0 0 15px 0; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php esc_html_e('Activate License', 'bootflow-product-importer'); ?>
                </h3>
                
                <p class="description" style="margin-bottom: 15px;">
                    <?php esc_html_e('Enter your license key to unlock PRO features. Purchase a license at', 'bootflow-product-importer'); ?>
                    <a href="https://bootflow.io/pricing" target="_blank">bootflow.io/pricing</a>
                </p>
                
                <div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px;">
                        <input type="text" 
                               id="license_key" 
                               name="license_key" 
                               value="<?php echo esc_attr($license_key); ?>" 
                               placeholder="<?php esc_attr_e('Enter your license key (e.g., PRO-XXXX-XXXX-XXXX)', 'bootflow-product-importer'); ?>" 
                               class="regular-text" 
                               style="width: 100%; height: 40px; font-size: 14px; font-family: monospace;"
                        />
                    </div>
                    <button type="button" id="btn-activate-license" class="button button-primary" style="height: 40px; padding: 0 25px;">
                        <span class="dashicons dashicons-yes" style="margin-top: 7px; margin-right: 3px;"></span>
                        <?php esc_html_e('Activate', 'bootflow-product-importer'); ?>
                    </button>
                    <?php if ($license_status === 'active' && !empty($license_key)): ?>
                        <button type="button" id="btn-deactivate-license" class="button" style="height: 40px; padding: 0 25px; color: #dc3545; border-color: #dc3545;">
                            <span class="dashicons dashicons-no" style="margin-top: 7px; margin-right: 3px;"></span>
                            <?php esc_html_e('Deactivate', 'bootflow-product-importer'); ?>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="license-activation-result" style="margin-top: 15px; display: none;"></div>
            </div>
            
            <!-- Feature Comparison Table -->
            <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 25px;">
                <h3 style="margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-editor-table"></span>
                    <?php esc_html_e('Feature Comparison', 'bootflow-product-importer'); ?>
                </h3>
                
                <table class="widefat striped" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 50%;"><?php esc_html_e('Feature', 'bootflow-product-importer'); ?></th>
                            <th style="text-align: center; background: #f8f9fa;">🆓 FREE</th>
                            <th style="text-align: center; background: #e8f5f5;">⚡ PRO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- FREE tier features (both tiers) -->
                        <tr>
                            <td><?php esc_html_e('Import XML & CSV feeds', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Manual field mapping', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Simple & variable products', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Attributes & variations', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Manual imports', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Unlimited products', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Local file uploads', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Update existing products', 'bootflow-product-importer'); ?></td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        
                        <!-- PRO tier features -->
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Select which fields to update', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Update only price and stock', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Automatic field mapping', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Scheduled imports (cron)', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Import from remote URLs', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Advanced update rules', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Conditional logic', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Import templates', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong><?php esc_html_e('Logs & error reporting', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #e8f5f5;">
                            <td><strong>🤖 <?php esc_html_e('AI-assisted mapping & transformation', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #e8f5f5;">
                            <td><strong>🌍 <?php esc_html_e('Translate product data with AI', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #e8f5f5;">
                            <td><strong>💬 <?php esc_html_e('Custom AI prompts per field', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                        <tr style="background: #f0f9f9;">
                            <td><strong>🎯 <?php esc_html_e('Priority support', 'bootflow-product-importer'); ?></strong></td>
                            <td style="text-align: center; color: #dc3545;">✗</td>
                            <td style="text-align: center; color: #28a745;">✓</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px; display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                    <?php if ($current_tier === 'free'): ?>
                        <a href="https://bootflow.io/pricing" target="_blank" class="button button-primary button-hero" style="background: linear-gradient(135deg, #0d7377 0%, #14919b 100%); border: none;">
                            <?php esc_html_e('Upgrade to PRO', 'bootflow-product-importer'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- AI Providers Tab (PRO only) -->
        <?php if ($current_tier === 'pro'): ?>
        <div id="ai-providers" class="tab-content">
            <h2><?php esc_html_e('AI Provider Configuration', 'bootflow-product-importer'); ?></h2>
            <p class="description"><?php esc_html_e('Configure API keys and settings for AI providers used in smart field processing.', 'bootflow-product-importer'); ?></p>
            
            <table class="form-table">
                <!-- OpenAI -->
                <tr>
                    <th scope="row"><?php esc_html_e('OpenAI Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="openai_api_key"><?php esc_html_e('API Key:', 'bootflow-product-importer'); ?></label><br>
                            <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr($ai_settings['openai_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" class="toggle-password button button-small"><?php esc_html_e('Show', 'bootflow-product-importer'); ?></button><br><br>
                            
                            <label for="openai_model"><?php esc_html_e('Model:', 'bootflow-product-importer'); ?></label><br>
                            <select id="openai_model" name="openai_model">
                                <option value="gpt-3.5-turbo" <?php selected($ai_settings['openai_model'] ?? 'gpt-3.5-turbo', 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                                <option value="gpt-4" <?php selected($ai_settings['openai_model'] ?? 'gpt-3.5-turbo', 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-4-turbo" <?php selected($ai_settings['openai_model'] ?? 'gpt-3.5-turbo', 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                            </select>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- Google Gemini - Coming Soon
                <tr>
                    <th scope="row"><?php esc_html_e('Google Gemini Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="gemini_api_key"><?php esc_html_e('API Key:', 'bootflow-product-importer'); ?></label><br>
                            <input type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo esc_attr($ai_settings['gemini_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" class="toggle-password button button-small"><?php esc_html_e('Show', 'bootflow-product-importer'); ?></button><br><br>
                            
                            <label for="gemini_model"><?php esc_html_e('Model:', 'bootflow-product-importer'); ?></label><br>
                            <select id="gemini_model" name="gemini_model">
                                <option value="gemini-pro" <?php selected($ai_settings['gemini_model'] ?? 'gemini-pro', 'gemini-pro'); ?>>Gemini Pro</option>
                                <option value="gemini-1.5-pro" <?php selected($ai_settings['gemini_model'] ?? 'gemini-pro', 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                            </select>
                        </fieldset>
                    </td>
                </tr>
                -->
                
                <!-- Anthropic Claude -->
                <tr>
                    <th scope="row"><?php esc_html_e('Anthropic Claude Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="claude_api_key"><?php esc_html_e('API Key:', 'bootflow-product-importer'); ?></label><br>
                            <input type="password" id="claude_api_key" name="claude_api_key" value="<?php echo esc_attr($ai_settings['claude_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" class="toggle-password button button-small"><?php esc_html_e('Show', 'bootflow-product-importer'); ?></button><br><br>
                            
                            <label for="claude_model"><?php esc_html_e('Model:', 'bootflow-product-importer'); ?></label><br>
                            <select id="claude_model" name="claude_model">
                                <option value="claude-opus-4-20250514" <?php selected($ai_settings['claude_model'] ?? 'claude-opus-4-20250514', 'claude-opus-4-20250514'); ?>>Claude Opus 4 (Best)</option>
                                <option value="claude-sonnet-4-20250514" <?php selected($ai_settings['claude_model'] ?? 'claude-opus-4-20250514', 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4 (Fast)</option>
                                <option value="claude-3-7-sonnet-20250219" <?php selected($ai_settings['claude_model'] ?? 'claude-opus-4-20250514', 'claude-3-7-sonnet-20250219'); ?>>Claude 3.7 Sonnet</option>
                            </select>
                        </fieldset>
                    </td>
                </tr>
                
                <!-- xAI Grok - Coming Soon
                <tr>
                    <th scope="row"><?php esc_html_e('xAI Grok Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="grok_api_key"><?php esc_html_e('API Key:', 'bootflow-product-importer'); ?></label><br>
                            <input type="password" id="grok_api_key" name="grok_api_key" value="<?php echo esc_attr($ai_settings['grok_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" class="toggle-password button button-small"><?php esc_html_e('Show', 'bootflow-product-importer'); ?></button><br><br>
                            
                            <label for="grok_model"><?php esc_html_e('Model:', 'bootflow-product-importer'); ?></label><br>
                            <select id="grok_model" name="grok_model">
                                <option value="grok-beta" <?php selected($ai_settings['grok_model'] ?? 'grok-beta', 'grok-beta'); ?>>Grok Beta</option>
                            </select>
                        </fieldset>
                    </td>
                </tr>
                -->
                
                <!-- GitHub Copilot - Coming Soon
                <tr>
                    <th scope="row"><?php esc_html_e('GitHub Copilot Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="copilot_api_key"><?php esc_html_e('API Key:', 'bootflow-product-importer'); ?></label><br>
                            <input type="password" id="copilot_api_key" name="copilot_api_key" value="<?php echo esc_attr($ai_settings['copilot_api_key'] ?? ''); ?>" class="regular-text" />
                            <button type="button" class="toggle-password button button-small"><?php esc_html_e('Show', 'bootflow-product-importer'); ?></button><br><br>
                            
                            <label for="copilot_model"><?php esc_html_e('Model:', 'bootflow-product-importer'); ?></label><br>
                            <select id="copilot_model" name="copilot_model">
                                <option value="gpt-4" <?php selected($ai_settings['copilot_model'] ?? 'gpt-4', 'gpt-4'); ?>>GPT-4</option>
                            </select>
                        </fieldset>
                    </td>
                </tr>
                -->
                
                <!-- General AI Settings -->
                <tr>
                    <th scope="row"><?php esc_html_e('General AI Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="default_provider"><?php esc_html_e('Default Provider:', 'bootflow-product-importer'); ?></label><br>
                            <select id="default_provider" name="default_provider">
                                <option value="openai" <?php selected($ai_settings['default_provider'] ?? 'openai', 'openai'); ?>>OpenAI</option>
                                <option value="claude" <?php selected($ai_settings['default_provider'] ?? 'openai', 'claude'); ?>>Anthropic Claude</option>
                                <!-- Coming soon:
                                <option value="gemini">Google Gemini</option>
                                <option value="grok">xAI Grok</option>
                                <option value="copilot">GitHub Copilot</option>
                                -->
                            </select>
                            <button type="button" class="button button-secondary test-ai-connection" style="margin-left: 10px;">
                                <?php esc_html_e('Test Connection', 'bootflow-product-importer'); ?>
                            </button>
                            <span class="test-ai-result" style="margin-left: 10px;"></span>
                            <br><br>
                            
                            <label>
                                <input type="checkbox" name="enable_fallback" value="1" <?php checked($ai_settings['enable_fallback'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Provider Fallback', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="ai_timeout"><?php esc_html_e('API Timeout (seconds):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="ai_timeout" name="ai_timeout" value="<?php echo esc_attr($ai_settings['ai_timeout'] ?? 30); ?>" min="10" max="300" /><br><br>
                            
                            <label for="ai_max_retries"><?php esc_html_e('Max Retries:', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="ai_max_retries" name="ai_max_retries" value="<?php echo esc_attr($ai_settings['ai_max_retries'] ?? 3); ?>" min="1" max="10" /><br><br>
                            
                            <label>
                                <input type="checkbox" name="enable_ai_cache" value="1" <?php checked($ai_settings['enable_ai_cache'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable AI Response Caching', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="ai_cache_ttl"><?php esc_html_e('Cache TTL (seconds):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="ai_cache_ttl" name="ai_cache_ttl" value="<?php echo esc_attr($ai_settings['ai_cache_ttl'] ?? 3600); ?>" min="300" max="86400" />
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Performance Tab -->
        <div id="performance" class="tab-content">
            <h2><?php esc_html_e('Performance Settings', 'bootflow-product-importer'); ?></h2>
            <p class="description"><?php esc_html_e('Configure performance-related settings to optimize import speed and memory usage.', 'bootflow-product-importer'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Memory & Processing', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="batch_size"><?php esc_html_e('Batch Size:', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr($performance_settings['batch_size'] ?? 50); ?>" min="1" max="500" />
                            <p class="description"><?php esc_html_e('Number of products to process in each batch.', 'bootflow-product-importer'); ?></p><br>
                            
                            <label for="memory_limit"><?php esc_html_e('Memory Limit:', 'bootflow-product-importer'); ?></label><br>
                            <select id="memory_limit" name="memory_limit">
                                <option value="256M" <?php selected($performance_settings['memory_limit'] ?? '512M', '256M'); ?>>256MB</option>
                                <option value="512M" <?php selected($performance_settings['memory_limit'] ?? '512M', '512M'); ?>>512MB</option>
                                <option value="1G" <?php selected($performance_settings['memory_limit'] ?? '512M', '1G'); ?>>1GB</option>
                                <option value="2G" <?php selected($performance_settings['memory_limit'] ?? '512M', '2G'); ?>>2GB</option>
                            </select><br><br>
                            
                            <label for="max_execution_time"><?php esc_html_e('Max Execution Time (seconds):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="max_execution_time" name="max_execution_time" value="<?php echo esc_attr($performance_settings['max_execution_time'] ?? 300); ?>" min="60" max="3600" /><br><br>
                            
                            <label for="chunk_size"><?php esc_html_e('File Chunk Size:', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="chunk_size" name="chunk_size" value="<?php echo esc_attr($performance_settings['chunk_size'] ?? 1000); ?>" min="100" max="10000" />
                            <p class="description"><?php esc_html_e('Number of lines to read from file at once.', 'bootflow-product-importer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Background Processing', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_background_processing" value="1" <?php checked($performance_settings['enable_background_processing'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Background Processing (WP-Cron)', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="background_batch_size"><?php esc_html_e('Background Batch Size:', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="background_batch_size" name="background_batch_size" value="<?php echo esc_attr($performance_settings['background_batch_size'] ?? 10); ?>" min="1" max="100" /><br><br>
                            
                            <label for="background_interval"><?php esc_html_e('Background Interval (seconds):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="background_interval" name="background_interval" value="<?php echo esc_attr($performance_settings['background_interval'] ?? 60); ?>" min="30" max="300" />
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Import Tab -->
        <div id="import" class="tab-content">
            <h2><?php esc_html_e('Import Settings', 'bootflow-product-importer'); ?></h2>
            <p class="description"><?php esc_html_e('Configure default settings for product imports and data handling.', 'bootflow-product-importer'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Product Defaults', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="default_product_status"><?php esc_html_e('Default Product Status:', 'bootflow-product-importer'); ?></label><br>
                            <select id="default_product_status" name="default_product_status">
                                <option value="draft" <?php selected($import_settings['default_product_status'] ?? 'draft', 'draft'); ?>>Draft</option>
                                <option value="publish" <?php selected($import_settings['default_product_status'] ?? 'draft', 'publish'); ?>>Published</option>
                                <option value="private" <?php selected($import_settings['default_product_status'] ?? 'draft', 'private'); ?>>Private</option>
                            </select><br><br>
                            
                            <label>
                                <input type="checkbox" name="update_existing_products" value="1" <?php checked($import_settings['update_existing_products'] ?? 0, 1); ?> />
                                <?php esc_html_e('Update Existing Products', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="duplicate_handling"><?php esc_html_e('Duplicate Handling:', 'bootflow-product-importer'); ?></label><br>
                            <select id="duplicate_handling" name="duplicate_handling">
                                <option value="skip" <?php selected($import_settings['duplicate_handling'] ?? 'skip', 'skip'); ?>>Skip Duplicates</option>
                                <option value="update" <?php selected($import_settings['duplicate_handling'] ?? 'skip', 'update'); ?>>Update Duplicates</option>
                                <option value="create_new" <?php selected($import_settings['duplicate_handling'] ?? 'skip', 'create_new'); ?>>Create New</option>
                            </select>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Media & Images', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_image_download" value="1" <?php checked($import_settings['enable_image_download'] ?? 0, 1); ?> />
                                <?php esc_html_e('Download Images from URLs', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="image_timeout"><?php esc_html_e('Image Download Timeout (seconds):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="image_timeout" name="image_timeout" value="<?php echo esc_attr($import_settings['image_timeout'] ?? 30); ?>" min="10" max="120" /><br><br>
                            
                            <label for="max_image_size"><?php esc_html_e('Max Image Size (MB):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="max_image_size" name="max_image_size" value="<?php echo esc_attr(($import_settings['max_image_size'] ?? 5242880) / 1024 / 1024); ?>" min="1" max="50" />
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Product Features', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_variation_import" value="1" <?php checked($import_settings['enable_variation_import'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Product Variations Import', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label>
                                <input type="checkbox" name="enable_category_creation" value="1" <?php checked($import_settings['enable_category_creation'] ?? 0, 1); ?> />
                                <?php esc_html_e('Create Missing Categories', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label>
                                <input type="checkbox" name="enable_tag_creation" value="1" <?php checked($import_settings['enable_tag_creation'] ?? 0, 1); ?> />
                                <?php esc_html_e('Create Missing Tags', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label>
                                <input type="checkbox" name="preserve_html" value="1" <?php checked($import_settings['preserve_html'] ?? 0, 1); ?> />
                                <?php esc_html_e('Preserve HTML in Descriptions', 'bootflow-product-importer'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Scheduling Tab -->
        <div id="scheduling" class="tab-content">
            <h2><?php esc_html_e('Scheduled Imports (Cron)', 'bootflow-product-importer'); ?></h2>
            
            <?php 
            $can_scheduling = WC_XML_CSV_AI_Import_Features::is_available('scheduled_import');
            $cron_secret = $saved_settings['cron_secret_key'] ?? '';
            
            // Generate secret if not exists
            if (empty($cron_secret)) {
                $cron_secret = wp_generate_password(32, false);
            }
            
            $site_url = admin_url('admin-ajax.php');
            $cron_url = $site_url . '?action=wc_xml_csv_ai_import_cron&secret=' . $cron_secret;
            $scheduling_method = $saved_settings['scheduling_method'] ?? 'action_scheduler';
            $action_scheduler_available = class_exists('WC_XML_CSV_AI_Import_Scheduler') ? WC_XML_CSV_AI_Import_Scheduler::is_action_scheduler_available() : false;
            ?>
            
            <?php if ($can_scheduling): ?>
            
            <!-- Scheduling Method Selection -->
            <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">
                    <span class="dashicons dashicons-admin-settings" style="color: #0073aa;"></span>
                    <?php esc_html_e('Scheduling Method', 'bootflow-product-importer'); ?>
                </h3>
                
                <table class="form-table" style="margin: 0;">
                    <tr>
                        <th scope="row"><?php esc_html_e('Choose Method', 'bootflow-product-importer'); ?></th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 15px; padding: 15px; border: 2px solid <?php echo esc_attr($scheduling_method === 'action_scheduler' ? '#0073aa' : '#ddd'); ?>; border-radius: 6px; cursor: pointer; background: <?php echo esc_attr($scheduling_method === 'action_scheduler' ? '#f0f6fc' : '#fff'); ?>;">
                                    <input type="radio" name="scheduling_method" value="action_scheduler" <?php checked($scheduling_method, 'action_scheduler'); ?> <?php echo !$action_scheduler_available ? 'disabled' : ''; ?>>
                                    <strong><?php esc_html_e('Action Scheduler', 'bootflow-product-importer'); ?></strong>
                                    <span style="background: #28a745; color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; margin-left: 8px;"><?php esc_html_e('Recommended', 'bootflow-product-importer'); ?></span>
                                    <?php if (!$action_scheduler_available): ?>
                                        <span style="background: #dc3545; color: white; font-size: 11px; padding: 2px 8px; border-radius: 10px; margin-left: 8px;"><?php esc_html_e('Not Available', 'bootflow-product-importer'); ?></span>
                                    <?php endif; ?>
                                    <p class="description" style="margin: 8px 0 0 24px;">
                                        <?php esc_html_e('Uses WooCommerce Action Scheduler for reliable background processing. Automatically processes all products until complete. Works without server cron configuration.', 'bootflow-product-importer'); ?>
                                        <br><br>
                                        <strong><?php esc_html_e('Pros:', 'bootflow-product-importer'); ?></strong> <?php esc_html_e('No server configuration needed, self-healing, processes full import automatically.', 'bootflow-product-importer'); ?>
                                        <br>
                                        <strong><?php esc_html_e('Cons:', 'bootflow-product-importer'); ?></strong> <?php esc_html_e('Requires some website traffic to trigger (or WP-Cron).', 'bootflow-product-importer'); ?>
                                    </p>
                                </label>
                                
                                <label style="display: block; padding: 15px; border: 2px solid <?php echo esc_attr($scheduling_method === 'server_cron' ? '#0073aa' : '#ddd'); ?>; border-radius: 6px; cursor: pointer; background: <?php echo esc_attr($scheduling_method === 'server_cron' ? '#f0f6fc' : '#fff'); ?>;">
                                    <input type="radio" name="scheduling_method" value="server_cron" <?php checked($scheduling_method, 'server_cron'); ?>>
                                    <strong><?php esc_html_e('Server Cron (URL Trigger)', 'bootflow-product-importer'); ?></strong>
                                    <p class="description" style="margin: 8px 0 0 24px;">
                                        <?php esc_html_e('Use an external server cron job to trigger imports via URL. Processes entire import in one request (with loop until complete).', 'bootflow-product-importer'); ?>
                                        <br><br>
                                        <strong><?php esc_html_e('Pros:', 'bootflow-product-importer'); ?></strong> <?php esc_html_e('100% reliable, does not depend on website traffic.', 'bootflow-product-importer'); ?>
                                        <br>
                                        <strong><?php esc_html_e('Cons:', 'bootflow-product-importer'); ?></strong> <?php esc_html_e('Requires server access to configure crontab.', 'bootflow-product-importer'); ?>
                                    </p>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Server Cron Instructions (shown when server_cron is selected) -->
            <div class="cron-setup-instructions" id="server-cron-instructions" style="background: #f8f9fa; border: 1px solid #e2e4e7; border-radius: 6px; padding: 20px; margin-bottom: 20px; <?php echo esc_attr($scheduling_method !== 'server_cron' ? 'display: none;' : ''); ?>">
                <h3 style="margin-top: 0;">
                    <span class="dashicons dashicons-clock" style="color: #0073aa;"></span>
                    <?php esc_html_e('Server Cron Setup Instructions', 'bootflow-product-importer'); ?>
                </h3>
                
                <p><?php esc_html_e('Add this cron job to your server to trigger scheduled imports. The import will process ALL products until complete in one request.', 'bootflow-product-importer'); ?></p>
                
                <h4><?php esc_html_e('Add this cron job to your server', 'bootflow-product-importer'); ?></h4>
                <p class="description"><?php esc_html_e('Copy and paste this command into your server\'s crontab (via SSH or your hosting control panel):', 'bootflow-product-importer'); ?></p>
                
                <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; overflow-x: auto; margin: 10px 0;">
                    <code style="color: #9cdcfe;">* * * * * curl -s "<?php echo esc_url($cron_url); ?>" > /dev/null 2>&1</code>
                </div>
                <p class="description"><?php esc_html_e('Note: We recommend running every minute. The plugin will only process imports when their scheduled interval has passed.', 'bootflow-product-importer'); ?></p>
                
                <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('*/15 * * * * curl -s \"<?php echo esc_url($cron_url); ?>\" > /dev/null 2>&1'); alert('Copied to clipboard!');">
                    <span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span>
                    <?php esc_html_e('Copy Command', 'bootflow-product-importer'); ?>
                </button>
                
                <h4 style="margin-top: 20px;"><?php esc_html_e('Step 2 (Optional): Disable WP-Cron', 'bootflow-product-importer'); ?></h4>
                <p class="description"><?php esc_html_e('For better reliability, add this to your wp-config.php file:', 'bootflow-product-importer'); ?></p>
                
                <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; margin: 10px 0;">
                    <code style="color: #ce9178;">define('DISABLE_WP_CRON', true);</code>
                </div>
                
                <h4 style="margin-top: 20px;"><?php esc_html_e('Alternative: WP-CLI', 'bootflow-product-importer'); ?></h4>
                <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; margin: 10px 0;">
                    <code style="color: #9cdcfe;">*/15 * * * * cd <?php echo ABSPATH; ?> && wp cron event run --due-now > /dev/null 2>&1</code>
                </div>
            </div>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Cron Secret Key', 'bootflow-product-importer'); ?></th>
                    <td>
                        <input type="text" name="cron_secret_key" value="<?php echo esc_attr($cron_secret); ?>" class="regular-text" readonly />
                        <button type="button" class="button button-small" onclick="this.previousElementSibling.value = '<?php echo wp_generate_password(32, false); ?>'; alert('New secret generated. Save settings to apply.');">
                            <?php esc_html_e('Regenerate', 'bootflow-product-importer'); ?>
                        </button>
                        <p class="description"><?php esc_html_e('This secret key protects your cron endpoint from unauthorized access.', 'bootflow-product-importer'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Cron URL', 'bootflow-product-importer'); ?></th>
                    <td>
                        <code style="background: #f0f0f0; padding: 8px 12px; display: block; word-break: break-all;"><?php echo esc_url($cron_url); ?></code>
                        <p class="description"><?php esc_html_e('This is the URL that the cron job will call to trigger scheduled imports.', 'bootflow-product-importer'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php else: ?>
            <!-- PRO required notice -->
            <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 8px; padding: 30px; text-align: center;">
                <span class="dashicons dashicons-lock" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></span>
                <h3 style="margin: 0 0 10px 0; color: #495057;">
                    <?php esc_html_e('Scheduled Imports', 'bootflow-product-importer'); ?>
                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 12px; padding: 4px 12px; border-radius: 12px; margin-left: 10px;">PRO</span>
                </h3>
                <p style="color: #6c757d; margin-bottom: 20px;">
                    <?php esc_html_e('Automate your imports with scheduled cron jobs. Run imports automatically every 15 minutes, hourly, daily, or weekly.', 'bootflow-product-importer'); ?>
                </p>
                <ul style="text-align: left; display: inline-block; margin: 0 0 20px 0; color: #6c757d;">
                    <li>✓ <?php esc_html_e('Set up automatic import schedules', 'bootflow-product-importer'); ?></li>
                    <li>✓ <?php esc_html_e('Import from remote URLs', 'bootflow-product-importer'); ?></li>
                    <li>✓ <?php esc_html_e('Keep products always up-to-date', 'bootflow-product-importer'); ?></li>
                    <li>✓ <?php esc_html_e('No manual intervention required', 'bootflow-product-importer'); ?></li>
                </ul>
                <br>
                <a href="https://yourwebsite.com/pro" target="_blank" class="button button-primary button-large" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <?php esc_html_e('Upgrade to PRO', 'bootflow-product-importer'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Files Tab -->
        <div id="files" class="tab-content">
            <h2><?php esc_html_e('File Management Settings', 'bootflow-product-importer'); ?></h2>
            <p class="description"><?php esc_html_e('Configure file upload, storage, and management settings.', 'bootflow-product-importer'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Upload Settings', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label for="max_file_size"><?php esc_html_e('Max File Size (MB):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="max_file_size" name="max_file_size" value="<?php echo esc_attr(($file_settings['max_file_size'] ?? 104857600) / 1024 / 1024); ?>" min="1" max="500" /><br><br>
                            
                            <label><?php esc_html_e('Allowed File Types:', 'bootflow-product-importer'); ?></label><br>
                            <label>
                                <input type="checkbox" name="allowed_file_types[]" value="xml" <?php echo in_array('xml', $file_settings['allowed_file_types'] ?? array('xml', 'csv')) ? 'checked' : ''; ?> />
                                XML
                            </label>
                            <label>
                                <input type="checkbox" name="allowed_file_types[]" value="csv" <?php echo in_array('csv', $file_settings['allowed_file_types'] ?? array('xml', 'csv')) ? 'checked' : ''; ?> />
                                CSV
                            </label><br><br>
                            
                            <label for="upload_directory"><?php esc_html_e('Upload Directory:', 'bootflow-product-importer'); ?></label><br>
                            <input type="text" id="upload_directory" name="upload_directory" value="<?php echo esc_attr($file_settings['upload_directory'] ?? 'wc-xml-csv-import'); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Directory name within wp-content/uploads/', 'bootflow-product-importer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('File Retention', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="auto_delete_files" value="1" <?php checked($file_settings['auto_delete_files'] ?? 0, 1); ?> />
                                <?php esc_html_e('Auto-delete Old Files', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="file_retention_days"><?php esc_html_e('File Retention (days):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="file_retention_days" name="file_retention_days" value="<?php echo esc_attr($file_settings['file_retention_days'] ?? 30); ?>" min="1" max="365" />
                            <p class="description"><?php esc_html_e('Files older than this will be automatically deleted.', 'bootflow-product-importer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Logging Tab -->
        <div id="logging" class="tab-content">
            <h2><?php esc_html_e('Logging & Notifications', 'bootflow-product-importer'); ?></h2>
            <p class="description"><?php esc_html_e('Configure logging levels and notification settings.', 'bootflow-product-importer'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Logging Configuration', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_logging" value="1" <?php checked($logging_settings['enable_logging'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Logging', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="log_level"><?php esc_html_e('Log Level:', 'bootflow-product-importer'); ?></label><br>
                            <select id="log_level" name="log_level">
                                <option value="debug" <?php selected($logging_settings['log_level'] ?? 'info', 'debug'); ?>>Debug</option>
                                <option value="info" <?php selected($logging_settings['log_level'] ?? 'info', 'info'); ?>>Info</option>
                                <option value="warning" <?php selected($logging_settings['log_level'] ?? 'info', 'warning'); ?>>Warning</option>
                                <option value="error" <?php selected($logging_settings['log_level'] ?? 'info', 'error'); ?>>Error</option>
                            </select><br><br>
                            
                            <label for="max_log_entries"><?php esc_html_e('Max Log Entries:', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="max_log_entries" name="max_log_entries" value="<?php echo esc_attr($logging_settings['max_log_entries'] ?? 10000); ?>" min="1000" max="100000" /><br><br>
                            
                            <label for="log_retention_days"><?php esc_html_e('Log Retention (days):', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="log_retention_days" name="log_retention_days" value="<?php echo esc_attr($logging_settings['log_retention_days'] ?? 30); ?>" min="7" max="365" />
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Email Notifications', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="email_notifications" value="1" <?php checked($logging_settings['email_notifications'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Email Notifications', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="notification_email"><?php esc_html_e('Notification Email:', 'bootflow-product-importer'); ?></label><br>
                            <input type="email" id="notification_email" name="notification_email" value="<?php echo esc_attr($logging_settings['notification_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Security Tab -->
        <div id="security" class="tab-content">
            <h2><?php esc_html_e('Security Settings', 'bootflow-product-importer'); ?></h2>
            <p class="description"><?php esc_html_e('Configure security settings for PHP formula execution and data validation.', 'bootflow-product-importer'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('PHP Formula Security', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label><?php esc_html_e('Allowed PHP Functions:', 'bootflow-product-importer'); ?></label><br>
                            <?php 
                            // WP.org compliance: preg_replace removed as it can execute code with /e modifier
                            $default_functions = array('strlen', 'substr', 'trim', 'strtoupper', 'strtolower', 'ucfirst', 'number_format', 'round', 'ceil', 'floor', 'abs', 'str_replace');
                            $allowed_functions = $security_settings['allowed_php_functions'] ?? $default_functions;
                            
                            foreach ($default_functions as $func) {
                                echo '<label><input type="checkbox" name="allowed_php_functions[]" value="' . esc_attr($func) . '" ' . (in_array($func, $allowed_functions) ? 'checked' : '') . ' /> ' . esc_html($func) . '</label> ';
                            }
                            ?><br><br>
                            
                            <label>
                                <input type="checkbox" name="enable_formula_validation" value="1" <?php checked($security_settings['enable_formula_validation'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Formula Validation', 'bootflow-product-importer'); ?>
                            </label><br><br>
                            
                            <label for="max_formula_length"><?php esc_html_e('Max Formula Length:', 'bootflow-product-importer'); ?></label><br>
                            <input type="number" id="max_formula_length" name="max_formula_length" value="<?php echo esc_attr($security_settings['max_formula_length'] ?? 500); ?>" min="100" max="2000" />
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Data Sanitization', 'bootflow-product-importer'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_sanitization" value="1" <?php checked($security_settings['enable_sanitization'] ?? 0, 1); ?> />
                                <?php esc_html_e('Enable Advanced Data Sanitization', 'bootflow-product-importer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically sanitize imported data to prevent XSS and other security issues.', 'bootflow-product-importer'); ?></p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');
        
        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Update content
        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        var input = $(this).prev('input');
        var type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).text(type === 'password' ? '<?php esc_html_e('Show', 'bootflow-product-importer'); ?>' : '<?php esc_html_e('Hide', 'bootflow-product-importer'); ?>');
    });
    
    // Test AI connection
    $('.test-ai-connection').on('click', function() {
        var $button = $(this);
        var $result = $('.test-ai-result');
        var provider = $('#default_provider').val();
        var apiKey = $('#' + provider + '_api_key').val();
        var model = $('#' + provider + '_model').val();
        
        if (!apiKey) {
            $result.html('<span style="color: red;">⚠️ Please enter an API key for ' + provider + ' first.</span>');
            return;
        }
        
        $button.prop('disabled', true).text('Testing...');
        $result.html('<span style="color: #666;">⏳ Testing ' + provider + ' connection...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_test_ai',
                provider: provider,
                api_key: apiKey,
                model: model,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: green;">✅ ' + (response.data.message || 'Connection successful!') + '</span>');
                } else {
                    $result.html('<span style="color: red;">❌ ' + (response.data.message || 'Connection failed') + '</span>');
                }
            },
            error: function(xhr) {
                var msg = 'Connection test failed';
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.data && resp.data.message) msg = resp.data.message;
                } catch(e) {}
                $result.html('<span style="color: red;">❌ ' + msg + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
    });
    
    // License Activation
    $('#btn-activate-license').on('click', function() {
        var licenseKey = $('#license_key').val().trim();
        var $btn = $(this);
        var $result = $('#license-activation-result');
        
        if (!licenseKey) {
            $result.html('<div class="notice notice-error"><p>Please enter a license key.</p></div>').show();
            return;
        }
        
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Activating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_activate_license',
                license_key: licenseKey,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                    // Reload page to show updated tier
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Activation failed. Please try again.</p></div>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="margin-top: 7px; margin-right: 3px;"></span> Activate');
            }
        });
    });
    
    // License Deactivation
    $('#btn-deactivate-license').on('click', function() {
        if (!confirm('Are you sure you want to deactivate your license? You will lose access to PRO/ADVANCED features.')) {
            return;
        }
        
        var $btn = $(this);
        var $result = $('#license-activation-result');
        
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Deactivating...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_deactivate_license',
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-info"><p>' + response.data.message + '</p></div>').show();
                    // Reload page to show updated tier
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
                }
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>Deactivation failed. Please try again.</p></div>').show();
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-no" style="margin-top: 7px; margin-right: 3px;"></span> Deactivate');
            }
        });
    });
    
    // Scheduling Method Toggle
    $('input[name="scheduling_method"]').on('change', function() {
        var method = $(this).val();
        
        // Update label styling
        $('input[name="scheduling_method"]').each(function() {
            var $label = $(this).closest('label');
            if ($(this).is(':checked')) {
                $label.css({
                    'border-color': '#0073aa',
                    'background': '#f0f6fc'
                });
            } else {
                $label.css({
                    'border-color': '#ddd',
                    'background': '#fff'
                });
            }
        });
        
        // Show/hide server cron instructions
        if (method === 'server_cron') {
            $('#server-cron-instructions').slideDown();
        } else {
            $('#server-cron-instructions').slideUp();
        }
    });
});
</script>

<style>
.tab-content {
    display: none;
    margin-top: 20px;
}
.tab-content.active {
    display: block;
}
.form-table fieldset {
    margin: 0;
}
.form-table fieldset label {
    display: inline-block;
    margin-right: 15px;
}
.toggle-password {
    margin-left: 10px;
}
</style>