<?php
/**
 * License and Feature Management
 * 
 * Manages plugin tiers (Free/Pro) and feature access.
 * Uses bootflow.io API for license validation.
 * 
 * Tier Philosophy:
 * - FREE: Full manual import tool, no artificial limits
 * - PRO: Automation, advanced rules, AI-assisted processing
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WC_XML_CSV_AI_Import_License {

    /**
     * API URL for license validation
     */
    const API_URL = 'https://bootflow.io/wp-json/bootflow/v1/license/validate';
    
    /**
     * Option key for storing license data
     */
    const OPTION_KEY = 'wc_xml_csv_ai_import_license';
    
    /**
     * Cache key for license status
     */
    const CACHE_KEY = 'wc_xml_csv_ai_import_license_cache';
    
    /**
     * Cache expiry in seconds (24 hours)
     */
    const CACHE_EXPIRY = 86400;

    /**
     * Current tier: 'free' or 'pro'
     *
     * @var string
     */
    private static $current_tier = null;

    /**
     * Feature definitions loaded from config
     *
     * @var array
     */
    private static $features = null;
    
    /**
     * Cached validation result
     *
     * @var array|null
     */
    private static $cached_status = null;

    /**
     * Get the current license tier.
     *
     * @since    1.0.0
     * @return   string 'free' or 'pro'
     */
    public static function get_tier() {
        if (self::$current_tier !== null) {
            return self::$current_tier;
        }

        // Check if this is Free plugin (no license needed)
        if (defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === false) {
            self::$current_tier = 'free';
            return 'free';
        }

        // PRO plugin - for now, skip license validation (development mode)
        // TODO: Enable license validation when bootflow.io API is ready
        if (defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === true) {
            self::$current_tier = 'pro';
            return 'pro';
        }

        // For development/testing: check if explicitly set
        if (defined('WC_XML_CSV_AI_IMPORT_TIER')) {
            self::$current_tier = WC_XML_CSV_AI_IMPORT_TIER;
            return self::$current_tier;
        }

        // Check saved license settings (for future use with bootflow.io)
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $license_key = isset($settings['license_key']) ? $settings['license_key'] : '';

        // If license key is set, validate it
        if (!empty($license_key)) {
            // Check cached validation first
            $cached = self::get_cached_status();
            if ($cached !== null) {
                self::$current_tier = $cached['valid'] ? 'pro' : 'free';
                return self::$current_tier;
            }
            
            // Validate with API (will cache result)
            $validation = self::validate($license_key);
            if ($validation['valid']) {
                self::$current_tier = 'pro';
                return 'pro';
            }
        }

        // Default to FREE
        self::$current_tier = 'free';
        return self::$current_tier;
    }
    
    /**
     * Check if license is valid (Pro active)
     *
     * @since    1.0.0
     * @return   bool
     */
    public static function is_valid() {
        return self::get_tier() === 'pro';
    }
    
    /**
     * Validate license key with bootflow.io API
     *
     * @since    1.0.0
     * @param    string $license_key License key to validate
     * @return   array  Validation result
     */
    public static function validate($license_key = null) {
        // Get license key from settings if not provided
        if ($license_key === null) {
            $settings = get_option('wc_xml_csv_ai_import_settings', array());
            $license_key = isset($settings['license_key']) ? $settings['license_key'] : '';
        }
        
        if (empty($license_key)) {
            return array(
                'valid' => false,
                'message' => __('No license key provided.', 'bootflow-woocommerce-xml-csv-importer'),
                'features' => array(),
                'expires_at' => null,
            );
        }
        
        // Check cache first
        $cached = self::get_cached_status();
        if ($cached !== null && isset($cached['license_key']) && $cached['license_key'] === $license_key) {
            return $cached;
        }
        
        // Make API request
        $result = self::api_request($license_key);
        
        // Cache the result
        self::cache_status($result, $license_key);
        
        return $result;
    }
    
    /**
     * Make API request to validate license
     *
     * @since    1.0.0
     * @param    string $license_key License key
     * @return   array  API response
     */
    private static function api_request($license_key) {
        $response = wp_remote_post(self::API_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'plugin_version' => defined('WC_XML_CSV_AI_IMPORT_VERSION') ? WC_XML_CSV_AI_IMPORT_VERSION : '0.9',
                'product_id' => 'xml-csv-importer-pro',
            )),
        ));
        
        // Handle connection errors - fallback to invalid
        if (is_wp_error($response)) {
            return array(
                'valid' => false,
                'message' => __('Could not connect to license server. Please try again later.', 'bootflow-woocommerce-xml-csv-importer'),
                'features' => array(),
                'expires_at' => null,
                'error' => $response->get_error_message(),
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // API returned error or invalid response
        if ($code !== 200 || !is_array($data)) {
            return array(
                'valid' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Invalid license key.', 'bootflow-woocommerce-xml-csv-importer'),
                'features' => array(),
                'expires_at' => null,
            );
        }
        
        // Valid response from API
        return array(
            'valid' => !empty($data['valid']),
            'message' => isset($data['message']) ? $data['message'] : '',
            'features' => isset($data['features']) ? $data['features'] : array(),
            'expires_at' => isset($data['expires_at']) ? $data['expires_at'] : null,
            'plan' => isset($data['plan']) ? $data['plan'] : 'pro',
        );
    }
    
    /**
     * Get cached license status
     *
     * @since    1.0.0
     * @return   array|null  Cached status or null
     */
    public static function get_cached_status() {
        if (self::$cached_status !== null) {
            return self::$cached_status;
        }
        
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            self::$cached_status = $cached;
            return $cached;
        }
        
        return null;
    }
    
    /**
     * Cache license status
     *
     * @since    1.0.0
     * @param    array  $status Status to cache
     * @param    string $license_key License key
     */
    private static function cache_status($status, $license_key) {
        $status['license_key'] = $license_key;
        $status['cached_at'] = time();
        
        set_transient(self::CACHE_KEY, $status, self::CACHE_EXPIRY);
        self::$cached_status = $status;
    }
    
    /**
     * Clear license cache
     *
     * @since    1.0.0
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
        self::$cached_status = null;
        self::$current_tier = null;
    }
    
    /**
     * Get license key from settings
     *
     * @since    1.0.0
     * @return   string
     */
    public static function get_license_key() {
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        return isset($settings['license_key']) ? $settings['license_key'] : '';
    }
    
    /**
     * Save license key to settings
     *
     * @since    1.0.0
     * @param    string $key License key
     */
    public static function save_license_key($key) {
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $settings['license_key'] = sanitize_text_field($key);
        update_option('wc_xml_csv_ai_import_settings', $settings);
        
        // Clear cache to force re-validation
        self::clear_cache();
    }
    
    /**
     * Get enabled features from license
     *
     * @since    1.0.0
     * @return   array
     */
    public static function get_features() {
        $cached = self::get_cached_status();
        if ($cached !== null && isset($cached['features'])) {
            return $cached['features'];
        }
        
        // If Pro is valid, return all Pro features
        if (self::is_valid()) {
            return array('all'); // All Pro features enabled
        }
        
        return array(); // No Pro features
    }

    /**
     * Check if a specific feature is available in current tier.
     *
     * @since    1.0.0
     * @param    string $feature Feature key to check
     * @return   mixed  true/false for boolean features, or the limit value
     */
    public static function can($feature) {
        self::load_features();

        $tier = self::get_tier();

        if (!isset(self::$features[$tier])) {
            return false;
        }

        if (!isset(self::$features[$tier][$feature])) {
            return false;
        }

        return self::$features[$tier][$feature];
    }

    /**
     * Check if current tier is at least the specified tier.
     *
     * @since    1.0.0
     * @param    string $minimum_tier 'free' or 'pro'
     * @return   bool
     */
    public static function is_at_least($minimum_tier) {
        $tier_order = array('free' => 0, 'pro' => 1);
        $current = self::get_tier();

        $current_level = isset($tier_order[$current]) ? $tier_order[$current] : 0;
        $minimum_level = isset($tier_order[$minimum_tier]) ? $tier_order[$minimum_tier] : 0;

        return $current_level >= $minimum_level;
    }

    /**
     * Get feature limit value (for numeric features like max_products).
     *
     * @since    1.0.0
     * @param    string $feature Feature key
     * @param    mixed  $default Default value if not set
     * @return   mixed
     */
    public static function get_limit($feature, $default = 0) {
        $value = self::can($feature);
        
        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Load features from config file.
     *
     * @since    1.0.0
     */
    private static function load_features() {
        if (self::$features !== null) {
            return;
        }

        $config_file = plugin_dir_path(dirname(__FILE__)) . 'includes/config/features.php';
        
        if (file_exists($config_file)) {
            self::$features = include $config_file;
        } else {
            // Fallback: all features enabled (development mode)
            self::$features = array(
                'free' => array(),
                'pro' => array(),
            );
        }
    }

    /**
     * Get all features for current tier.
     *
     * @since    1.0.0
     * @return   array
     */
    public static function get_all_features() {
        self::load_features();
        $tier = self::get_tier();
        return isset(self::$features[$tier]) ? self::$features[$tier] : array();
    }

    /**
     * Get tier display name with emoji.
     *
     * @since    1.0.0
     * @param    string $tier Optional tier, defaults to current
     * @return   string
     */
    public static function get_tier_name($tier = null) {
        if ($tier === null) {
            $tier = self::get_tier();
        }

        $names = array(
            'free' => '🆓 Free',
            'pro' => '💼 Pro',
        );

        return isset($names[$tier]) ? $names[$tier] : $tier;
    }

    /**
     * Get upgrade URL for bootflow.io
     *
     * @since    1.0.0
     * @return   string
     */
    public static function get_upgrade_url() {
        $base_url = 'https://bootflow.io/pricing/';
        
        return add_query_arg(array(
            'utm_source' => 'plugin',
            'utm_medium' => 'upgrade_button',
            'utm_campaign' => 'wc_xml_csv_ai_import',
        ), $base_url);
    }

    /**
     * Render upgrade notice HTML for PRO feature
     *
     * @since    1.0.0
     * @param    string $feature_name Feature name for context
     * @return   string HTML
     */
    public static function render_upgrade_notice($feature_name = '') {
        $upgrade_url = self::get_upgrade_url();
        
        ob_start();
        ?>
        <div class="wc-xml-csv-import-upgrade-notice" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px; border-radius: 8px; color: white; margin: 10px 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div>
                    <strong style="font-size: 14px;">🔒 <?php esc_html_e('PRO Feature', 'bootflow-woocommerce-xml-csv-importer'); ?></strong>
                    <?php if (!empty($feature_name)): ?>
                        <span style="opacity: 0.8;"> - <?php echo esc_html($feature_name); ?></span>
                    <?php endif; ?>
                    <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 13px;">
                        <?php esc_html_e('Upgrade to PRO to unlock this feature and remove all limits.', 'bootflow-woocommerce-xml-csv-importer'); ?>
                    </p>
                </div>
                <a href="<?php echo esc_url($upgrade_url); ?>" target="_blank" class="button" style="background: white; color: #764ba2; border: none; font-weight: 600; padding: 8px 20px; text-decoration: none;">
                    <?php esc_html_e('Upgrade to PRO', 'bootflow-woocommerce-xml-csv-importer'); ?> →
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Activate license key via bootflow.io API
     *
     * @since    1.0.0
     * @param    string $license_key License key to activate
     * @return   array  Result with 'success' and 'message'
     */
    public static function activate_license($license_key) {
        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('Please enter a license key.', 'bootflow-woocommerce-xml-csv-importer'),
            );
        }

        $license_key = sanitize_text_field(trim($license_key));

        // Call bootflow.io API to activate
        $response = wp_remote_post('https://bootflow.io/api/license/activate', array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'license_key' => $license_key,
                'site_url' => home_url(),
                'site_name' => get_bloginfo('name'),
                'plugin_version' => defined('WC_XML_CSV_AI_IMPORT_VERSION') ? WC_XML_CSV_AI_IMPORT_VERSION : '1.0.0',
            )),
        ));

        // Handle connection errors
        if (is_wp_error($response)) {
            // Fallback: accept key locally for offline activation (will verify later)
            return self::activate_license_offline($license_key);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['valid'])) {
            return array(
                'success' => false,
                'message' => isset($data['message']) ? $data['message'] : __('Invalid license key.', 'bootflow-woocommerce-xml-csv-importer'),
            );
        }

        // Valid license - save data
        $tier = isset($data['plan']) ? $data['plan'] : 'pro';
        $expires = isset($data['expires']) ? $data['expires'] : '';
        $is_lifetime = isset($data['lifetime']) && $data['lifetime'];

        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $settings['license_key'] = $license_key;
        $settings['license_tier'] = $tier;
        $settings['license_expires'] = $expires;
        $settings['license_lifetime'] = $is_lifetime;
        $settings['license_activated'] = current_time('mysql');
        $settings['license_data'] = $data;
        update_option('wc_xml_csv_ai_import_settings', $settings);

        // Reset cached tier
        self::$current_tier = null;

        return array(
            'success' => true,
            'tier' => $tier,
            'expires' => $expires,
            'lifetime' => $is_lifetime,
            'message' => sprintf(
                __('License activated successfully! Your plan: %s', 'bootflow-woocommerce-xml-csv-importer'),
                self::get_tier_name($tier)
            ),
        );
    }

    /**
     * Offline license activation (fallback when API unavailable)
     *
     * @since    1.0.0
     * @param    string $license_key License key
     * @return   array  Result
     */
    private static function activate_license_offline($license_key) {
        // Determine tier based on license key prefix (fallback logic)
        // All paid licenses map to 'pro' (legacy agency keys also become pro)
        $tier = 'free';
        if (stripos($license_key, 'PRO-') === 0 || 
            stripos($license_key, 'LTD-') === 0 ||
            stripos($license_key, 'AGENCY-') === 0 || 
            stripos($license_key, 'AGN-') === 0) {
            $tier = 'pro';
        }

        // Save locally - will verify with API later
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        $settings['license_key'] = $license_key;
        $settings['license_tier'] = $tier;
        $settings['license_activated'] = current_time('mysql');
        $settings['license_pending_verification'] = true;
        update_option('wc_xml_csv_ai_import_settings', $settings);

        // Reset cached tier
        self::$current_tier = null;

        return array(
            'success' => true,
            'tier' => $tier,
            'offline' => true,
            'message' => sprintf(
                __('License saved locally (offline mode). Will verify when connection is available. Tier: %s', 'bootflow-woocommerce-xml-csv-importer'),
                self::get_tier_name($tier)
            ),
        );
    }

    /**
     * Deactivate license.
     *
     * @since    1.0.0
     * @return   array  Result with 'success' and 'message'
     */
    public static function deactivate_license() {
        $settings = get_option('wc_xml_csv_ai_import_settings', array());
        unset($settings['license_key']);
        unset($settings['license_tier']);
        unset($settings['license_activated']);
        update_option('wc_xml_csv_ai_import_settings', $settings);

        // Reset cached tier
        self::$current_tier = null;

        return array(
            'success' => true,
            'message' => __('License deactivated.', 'bootflow-woocommerce-xml-csv-importer'),
        );
    }
}

/**
 * Global helper function to check feature availability.
 *
 * @since    1.0.0
 * @param    string $feature Feature key
 * @return   mixed
 */
function wc_xml_csv_ai_can($feature) {
    return WC_XML_CSV_AI_Import_License::can($feature);
}

/**
 * Global helper function to get current tier.
 *
 * @since    1.0.0
 * @return   string
 */
function wc_xml_csv_ai_get_tier() {
    return WC_XML_CSV_AI_Import_License::get_tier();
}
