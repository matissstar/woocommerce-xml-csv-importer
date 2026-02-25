<?php
/**
 * Features Management Class
 *
 * Handles Pro/Free feature detection and availability checks.
 * This class determines which features are available based on license status.
 *
 * @package WC_XML_CSV_AI_Import
 * @since 0.9
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WC_XML_CSV_AI_Import_Features
 *
 * Centralized feature flag management for Free vs Pro editions.
 */
class WC_XML_CSV_AI_Import_Features {

    /**
     * Feature definitions with required edition level
     * 
     * @var array Feature ID => required edition ('free' or 'pro')
     */
    const FEATURES = array(
        // === PRO FEATURES ===
        
        // AI & Processing
        'ai_auto_mapping'       => 'pro',  // AI-powered field auto-mapping
        'php_processing'        => 'pro',  // PHP formula processing mode
        'hybrid_processing'     => 'pro',  // Hybrid (PHP + AI) processing mode
        'ai_processing'         => 'pro',  // AI-only processing mode
        
        // Templates & Automation
        'mapping_templates'     => 'pro',  // Save/load mapping templates
        'scheduled_import'      => 'pro',  // Cron-based scheduled imports
        'remote_url_import'     => 'pro',  // Import from remote URLs
        
        // Product Types
        'variable_products'     => 'pro',  // Variable products with variations
        'attributes_automation' => 'pro',  // Automatic attribute creation
        
        // Advanced Features
        'import_filters'        => 'pro',  // Filter products during import
        'advanced_formulas'     => 'pro',  // Advanced field transformation formulas
        'multi_supplier'        => 'pro',  // Multi-supplier/warehouse logic
        'price_formulas'        => 'pro',  // Smart price calculation formulas
        'rule_engine'           => 'pro',  // First-match-wins rule engine
        
        // Data & Performance
        'detailed_logs'         => 'pro',  // Detailed import logs & diagnostics
        'batch_optimization'    => 'pro',  // Batch import performance optimization
        'large_feed_support'    => 'pro',  // Support for large feeds (1000+ products)
        'custom_meta_fields'    => 'pro',  // WooCommerce custom meta fields
        
        // === FREE FEATURES ===
        
        'simple_products'       => 'free', // Simple product import
        'basic_mapping'         => 'free', // Manual field mapping
        'manual_import'         => 'free', // Manual "Import Now" button
        'file_upload'           => 'free', // XML/CSV file upload
        'basic_fields'          => 'free', // Basic product fields (name, price, etc.)
        'categories_tags'       => 'free', // Categories and tags mapping
        'update_existing'       => 'free', // Update existing products by SKU
    );

    /**
     * Feature display information for Pro modal
     * 
     * @var array Feature ID => display info
     */
    const FEATURE_INFO = array(
        'ai_auto_mapping' => array(
            'title'       => 'AI Auto-Mapping',
            'description' => 'Automatically map XML/CSV fields to WooCommerce product fields using artificial intelligence. Save hours of manual mapping work.',
            'icon'        => '🤖',
        ),
        'variable_products' => array(
            'title'       => 'Variable Products',
            'description' => 'Import products with variations - sizes, colors, materials, and more. Full support for WooCommerce variable product structure.',
            'icon'        => '📦',
        ),
        'scheduled_import' => array(
            'title'       => 'Scheduled Imports',
            'description' => 'Set up automatic imports on a schedule. Keep your products updated from supplier feeds without manual intervention.',
            'icon'        => '⏰',
        ),
        'remote_url_import' => array(
            'title'       => 'Remote URL Import',
            'description' => 'Import directly from remote URLs. Perfect for supplier feeds that are hosted online.',
            'icon'        => '🌐',
        ),
        'mapping_templates' => array(
            'title'       => 'Mapping Templates',
            'description' => 'Save your field mappings as reusable templates. Perfect for recurring imports from the same source.',
            'icon'        => '💾',
        ),
        'import_filters' => array(
            'title'       => 'Import Filters',
            'description' => 'Filter products during import based on field values, price ranges, stock status, and more.',
            'icon'        => '🔍',
        ),
        'php_processing' => array(
            'title'       => 'PHP Processing',
            'description' => 'Use PHP formulas to transform field values during import. Full flexibility for complex transformations.',
            'icon'        => '⚙️',
        ),
        'hybrid_processing' => array(
            'title'       => 'Hybrid Processing',
            'description' => 'Combine PHP and AI processing for maximum flexibility. Best of both worlds.',
            'icon'        => '🔄',
        ),
        'ai_processing' => array(
            'title'       => 'AI Processing',
            'description' => 'Let AI handle complex field transformations. Describe what you want in plain language.',
            'icon'        => '✨',
        ),
        'advanced_formulas' => array(
            'title'       => 'Advanced Formulas',
            'description' => 'Create complex field transformation formulas. String manipulation, conditionals, and more.',
            'icon'        => '📐',
        ),
        'price_formulas' => array(
            'title'       => 'Price Formulas',
            'description' => 'Smart price calculation with markup, margins, rounding rules, and currency conversion.',
            'icon'        => '💰',
        ),
        'detailed_logs' => array(
            'title'       => 'Detailed Logs',
            'description' => 'Comprehensive import logs with error diagnostics. Track every product and field mapping.',
            'icon'        => '📋',
        ),
    );

    /**
     * Cached edition status
     * 
     * @var string|null
     */
    private static $cached_edition = null;

    /**
     * Check if a specific feature is available
     *
     * @param string $feature Feature ID to check
     * @return bool True if feature is available
     */
    public static function is_available($feature) {
        // Unknown feature = not available
        if (!isset(self::FEATURES[$feature])) {
            return false;
        }

        $required_edition = self::FEATURES[$feature];

        // Free features are always available
        if ($required_edition === 'free') {
            return true;
        }

        // Pro features require Pro edition
        return self::is_pro();
    }

    /**
     * Get current edition (free or pro)
     *
     * @return string 'free' or 'pro'
     */
    public static function get_edition() {
        if (self::$cached_edition !== null) {
            return self::$cached_edition;
        }

        // Check if this is the Pro version constant
        if (defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === true) {
            // Pro version - for now, skip license check (development mode)
            // TODO: Enable license validation when bootflow.io API is ready
            // if (class_exists('WC_XML_CSV_AI_Import_License')) {
            //     $license = new WC_XML_CSV_AI_Import_License();
            //     if ($license->is_valid()) {
            //         self::$cached_edition = 'pro';
            //         return 'pro';
            //     }
            // }
            
            // Temporarily return 'pro' if IS_PRO constant is true
            self::$cached_edition = 'pro';
            return 'pro';
        }

        self::$cached_edition = 'free';
        return 'free';
    }

    /**
     * Check if Pro edition is active
     *
     * @return bool True if Pro with valid license
     */
    public static function is_pro() {
        return self::get_edition() === 'pro';
    }

    /**
     * Check if this is the Pro plugin (regardless of license)
     *
     * @return bool True if Pro plugin
     */
    public static function is_pro_plugin() {
        return defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === true;
    }

    /**
     * Get list of all Pro features
     *
     * @return array Feature IDs
     */
    public static function get_pro_features() {
        return array_keys(array_filter(self::FEATURES, function($edition) {
            return $edition === 'pro';
        }));
    }

    /**
     * Get list of all Free features
     *
     * @return array Feature IDs
     */
    public static function get_free_features() {
        return array_keys(array_filter(self::FEATURES, function($edition) {
            return $edition === 'free';
        }));
    }

    /**
     * Get feature display information
     *
     * @param string $feature Feature ID
     * @return array|null Feature info or null if not found
     */
    public static function get_feature_info($feature) {
        return isset(self::FEATURE_INFO[$feature]) ? self::FEATURE_INFO[$feature] : null;
    }

    /**
     * Get all feature info for Pro modal
     *
     * @return array All feature info
     */
    public static function get_all_feature_info() {
        return self::FEATURE_INFO;
    }

    /**
     * Clear cached edition (useful after license change)
     */
    public static function clear_cache() {
        self::$cached_edition = null;
    }

    /**
     * Output Pro badge HTML
     *
     * @param string $feature Optional feature ID for specific modal
     * @param bool $clickable Whether badge should be clickable
     * @return string HTML
     */
    public static function pro_badge($feature = '', $clickable = true) {
        $class = 'pro-badge';
        $attrs = '';
        
        if ($clickable) {
            $class .= ' pro-feature-trigger';
            if ($feature) {
                $attrs = ' data-feature="' . esc_attr($feature) . '"';
            }
        }
        
        return '<span class="' . $class . '"' . $attrs . '>PRO</span>';
    }

    /**
     * Check feature and optionally show Pro badge
     * 
     * Returns true if feature available, or outputs Pro badge and returns false
     *
     * @param string $feature Feature ID
     * @param bool $echo_badge Whether to echo Pro badge if not available
     * @return bool True if available
     */
    public static function check($feature, $echo_badge = false) {
        if (self::is_available($feature)) {
            return true;
        }
        
        if ($echo_badge) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pro_badge returns safe HTML
            echo self::pro_badge($feature);
        }
        
        return false;
    }
}
