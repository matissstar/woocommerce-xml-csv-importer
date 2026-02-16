<?php
/**
 * Feature Flags Configuration
 * 
 * This file defines which features are available in each tier:
 * - FREE: Full manual import tool, no artificial limits
 * - PRO: Automation, advanced rules and AI-assisted processing
 *
 * Philosophy:
 * - No product count limits
 * - No artificial restrictions
 * - Monetization based on saving time and adding intelligence
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/config
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Edition Flag
 * Set to true for PRO version, false for FREE version
 * This is the MASTER switch for all PRO features
 * NOTE: This constant is now defined in the main plugin file only
 */
if (!defined('WC_XML_CSV_AI_IMPORT_IS_PRO')) {
    define('WC_XML_CSV_AI_IMPORT_IS_PRO', true);
}

/**
 * Check if running PRO version
 * 
 * @return bool True if PRO version, false if FREE
 */
function wc_xml_csv_ai_import_is_pro() {
    return defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO;
}

/**
 * Check if a specific feature is available
 * 
 * @param string $feature Feature key to check
 * @return bool True if feature is available
 */
function wc_xml_csv_ai_import_has_feature($feature) {
    static $features = null;
    
    if ($features === null) {
        $features = include(__FILE__);
    }
    
    $tier = wc_xml_csv_ai_import_is_pro() ? 'pro' : 'free';
    
    return isset($features[$tier][$feature]) && $features[$tier][$feature];
}

/**
 * Feature definitions per tier
 */
return array(
    
    // ═══════════════════════════════════════════════════════════
    // 🆓 FREE TIER - WordPress.org version
    // ═══════════════════════════════════════════════════════════
    // 
    // Purpose: Reliable, deterministic and fully usable manual import tool
    // No external services, no data sent outside WordPress
    //
    'free' => array(
        // ─────────────────────────────────────────────────────────
        // Import Sources
        // ─────────────────────────────────────────────────────────
        'import_xml'                => true,         // XML file import
        'import_csv'                => true,         // CSV file import
        'import_url'                => false,        // ❌ Remote URL feeds (PRO)
        'local_files_only'          => true,         // Only local file uploads
        
        // ─────────────────────────────────────────────────────────
        // Product Types
        // ─────────────────────────────────────────────────────────
        'simple_products'           => true,         // Simple products
        'variable_products'         => true,         // Variable products
        'variations'                => true,         // Variations handling
        'attributes'                => true,         // Attributes handling
        
        // ─────────────────────────────────────────────────────────
        // Mapping
        // ─────────────────────────────────────────────────────────
        'manual_mapping'            => true,         // Manual field mapping
        'auto_mapping'              => false,        // ❌ Auto-mapping (PRO)
        'templates'                 => false,        // ❌ Reusable templates (PRO)
        
        // ─────────────────────────────────────────────────────────
        // Processing Modes
        // ─────────────────────────────────────────────────────────
        'mode_direct'               => true,         // Direct value copy
        'mode_static'               => true,         // Static value assignment
        'mode_mapping'              => true,         // Value mapping tables
        'mode_php_formula'          => false,        // ❌ PHP formulas (PRO)
        'mode_ai_processing'        => false,        // ❌ AI processing (PRO)
        'mode_hybrid'               => false,        // ❌ Hybrid logic (PRO)
        
        // ─────────────────────────────────────────────────────────
        // Filters
        // ─────────────────────────────────────────────────────────
        'filters_basic'             => true,         // Basic filters (equals, contains, gt, lt)
        'filters_advanced'          => false,        // ❌ Advanced filters (PRO)
        'filters_regex'             => false,        // ❌ Regex filters (PRO)
        'conditional_logic'         => false,        // ❌ Conditional logic (PRO)
        
        // ─────────────────────────────────────────────────────────
        // Pricing Engine
        // ─────────────────────────────────────────────────────────
        'pricing_engine'            => true,         // Basic pricing engine (global markup)
        'pricing_global_markup'     => true,         // Global markup %
        'pricing_fixed_amount'      => true,         // Fixed amount addition
        'pricing_rounding'          => true,         // Price rounding
        'pricing_price_ranges'      => false,        // ❌ Price range rules (PRO)
        'pricing_by_category'       => false,        // ❌ Pricing by category (PRO)
        'pricing_by_brand'          => false,        // ❌ Pricing by brand/attribute (PRO)
        'pricing_by_supplier'       => false,        // ❌ Pricing by supplier (PRO)
        'pricing_multiple_rules'    => false,        // ❌ Multiple pricing rules (PRO)
        'pricing_conditions'        => false,        // ❌ Conditional pricing (PRO)
        'pricing_min_max'           => false,        // ❌ Min/max price limits (PRO)
        
        // ─────────────────────────────────────────────────────────
        // Update Behavior
        // ─────────────────────────────────────────────────────────
        'update_existing'           => true,         // Update existing products (all fields)
        'selective_update'          => false,        // ❌ Choose which fields to update (PRO)
        'skip_unchanged'            => true,         // Skip unchanged products
        
        // ─────────────────────────────────────────────────────────
        // Automation
        // ─────────────────────────────────────────────────────────
        'scheduling'                => false,        // ❌ Scheduled imports (PRO)
        'cron_imports'              => false,        // ❌ Cron-based imports (PRO)
        
        // ─────────────────────────────────────────────────────────
        // Logging & Debugging
        // ─────────────────────────────────────────────────────────
        'import_logs'               => false,        // ❌ Detailed import logs (PRO)
        'error_reporting'           => false,        // ❌ Error reporting (PRO)
        
        // ─────────────────────────────────────────────────────────
        // AI Features (none in FREE)
        // ─────────────────────────────────────────────────────────
        'ai_mapping'                => false,        // ❌ AI-assisted mapping (PRO)
        'ai_translation'            => false,        // ❌ AI translation (PRO)
        'ai_custom_prompts'         => false,        // ❌ Custom AI prompts (PRO)
        
        // ─────────────────────────────────────────────────────────
        // Support
        // ─────────────────────────────────────────────────────────
        'priority_support'          => false,        // ❌ Priority support (PRO)
    ),
    
    // ═══════════════════════════════════════════════════════════
    // 💼 PRO TIER
    // ═══════════════════════════════════════════════════════════
    // 
    // Purpose: Reduce manual work, automate imports, add intelligent processing
    // AI features are optional and require explicit user configuration
    //
    'pro' => array(
        // ─────────────────────────────────────────────────────────
        // Import Sources
        // ─────────────────────────────────────────────────────────
        'import_xml'                => true,         // XML file import
        'import_csv'                => true,         // CSV file import
        'import_url'                => true,         // ✅ Remote URL feeds
        'local_files_only'          => false,        // Remote feeds allowed
        
        // ─────────────────────────────────────────────────────────
        // Product Types
        // ─────────────────────────────────────────────────────────
        'simple_products'           => true,         // Simple products
        'variable_products'         => true,         // Variable products
        'grouped_products'          => true,         // ✅ Grouped products
        'external_products'         => true,         // ✅ External/Affiliate products
        'variations'                => true,         // Variations handling
        'attributes'                => true,         // Attributes handling
        
        // ─────────────────────────────────────────────────────────
        // Product Limits - REMOVED
        // ─────────────────────────────────────────────────────────
        // Note: No product count limits - both FREE and PRO have unlimited products
        // (Removed in v1.0.0 - monetization based on features, not artificial limits)
        
        // ─────────────────────────────────────────────────────────
        // Mapping
        // ─────────────────────────────────────────────────────────
        'manual_mapping'            => true,         // Manual field mapping
        'auto_mapping'              => true,         // ✅ Auto-mapping (rule-based + hybrid)
        'mapping_auto_php_js'       => true,         // ✅ Smart mapping (PHP/JS based)
        'mapping_auto_ai'           => true,         // ✅ AI-assisted mapping
        'templates'                 => true,         // ✅ Reusable templates
        'smart_mapping'             => true,         // ✅ Smart mapping feature
        
        // ─────────────────────────────────────────────────────────
        // Processing Modes
        // ─────────────────────────────────────────────────────────
        'mode_direct'               => true,         // Direct value copy
        'mode_static'               => true,         // Static value assignment
        'mode_mapping'              => true,         // Value mapping tables
        'mode_php_formula'          => true,         // ✅ PHP formulas
        'mode_ai_processing'        => true,         // ✅ AI processing
        'mode_hybrid'               => true,         // ✅ Hybrid logic
        'ai_processing'             => true,         // ✅ AI processing (alias)
        
        // ─────────────────────────────────────────────────────────
        // Filters
        // ─────────────────────────────────────────────────────────
        'filters_basic'             => true,         // Basic filters
        'filters_advanced'          => true,         // ✅ Advanced filters
        'filters_regex'             => true,         // ✅ Regex filters
        'conditional_logic'         => true,         // ✅ Conditional logic
        
        // ─────────────────────────────────────────────────────────
        // Pricing Engine
        // ─────────────────────────────────────────────────────────
        'pricing_engine'            => true,         // ✅ Full pricing engine
        'pricing_global_markup'     => true,         // ✅ Global markup %
        'pricing_fixed_amount'      => true,         // ✅ Fixed amount addition
        'pricing_rounding'          => true,         // ✅ Price rounding
        'pricing_price_ranges'      => true,         // ✅ Price range rules
        'pricing_by_category'       => true,         // ✅ Pricing by category
        'pricing_by_brand'          => true,         // ✅ Pricing by brand/attribute
        'pricing_by_supplier'       => true,         // ✅ Pricing by supplier
        'pricing_multiple_rules'    => true,         // ✅ Multiple pricing rules
        'pricing_conditions'        => true,         // ✅ Conditional pricing
        'pricing_min_max'           => true,         // ✅ Min/max price limits
        
        // ─────────────────────────────────────────────────────────
        // Update Behavior
        // ─────────────────────────────────────────────────────────
        'update_existing'           => true,         // Update existing products
        'selective_update'          => true,         // ✅ Choose which fields to update
        'skip_unchanged'            => true,         // Skip unchanged products
        
        // ─────────────────────────────────────────────────────────
        // Automation
        // ─────────────────────────────────────────────────────────
        'scheduling'                => true,         // ✅ Scheduled imports
        'cron_imports'              => true,         // ✅ Cron-based imports
        
        // ─────────────────────────────────────────────────────────
        // Logging & Debugging
        // ─────────────────────────────────────────────────────────
        'import_logs'               => true,         // ✅ Detailed import logs
        'error_reporting'           => true,         // ✅ Error reporting
        
        // ─────────────────────────────────────────────────────────
        // AI Features
        // ─────────────────────────────────────────────────────────
        'ai_mapping'                => true,         // ✅ AI-assisted mapping
        'ai_translation'            => true,         // ✅ AI translation
        'ai_custom_prompts'         => true,         // ✅ Custom AI prompts
        
        // ─────────────────────────────────────────────────────────
        // Support
        // ─────────────────────────────────────────────────────────
        'priority_support'          => true,         // ✅ Priority support
    ),
);
