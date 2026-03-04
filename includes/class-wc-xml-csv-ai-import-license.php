<?php
/**
 * License and Feature Management
 * 
 * Manages plugin tiers (Free/Pro) and feature access.
 * Uses LemonSqueezy API for license validation.
 * 
 * Tier Philosophy:
 * - FREE: Full manual import tool, no artificial limits
 * - PRO: Automation, advanced rules, AI-assisted processing (requires valid license)
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
     * LemonSqueezy License API endpoints
     */
    const LS_ACTIVATE_URL   = 'https://api.lemonsqueezy.com/v1/licenses/activate';
    const LS_DEACTIVATE_URL = 'https://api.lemonsqueezy.com/v1/licenses/deactivate';
    const LS_VALIDATE_URL   = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    
    /**
     * Option key for storing license data
     */
    const OPTION_KEY = 'wc_xml_csv_ai_import_license';
    
    /**
     * Cache key for license validation status (transient)
     */
    const CACHE_KEY = 'wc_xml_csv_ai_import_license_cache';
    
    /**
     * Cache expiry: re-validate every 24 hours
     */
    const CACHE_EXPIRY = 86400;
    
    /**
     * Grace period: if API unreachable, keep valid state for 72 hours
     */
    const GRACE_PERIOD = 259200;

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
     * Get the current license tier.
     * 
     * FREE plugin (IS_PRO=false): always returns 'free'
     * PRO plugin (IS_PRO=true): returns 'pro' only if license is activated and valid
     *
     * @since    1.0.0
     * @return   string 'free' or 'pro'
     */
    public static function get_tier() {
        if (self::$current_tier !== null) {
            return self::$current_tier;
        }

        // FREE plugin — no license needed, always free
        if (defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === false) {
            self::$current_tier = 'free';
            return 'free';
        }

        // PRO plugin — check license activation status
        if (defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === true) {
            $license_data = get_option(self::OPTION_KEY, array());
            
            // No license key stored → free (need to activate)
            if (empty($license_data['license_key'])) {
                self::$current_tier = 'free';
                return 'free';
            }
            
            // License key exists — check status
            if (!empty($license_data['status']) && $license_data['status'] === 'active') {
                // Check transient cache for periodic re-validation
                $cached = get_transient(self::CACHE_KEY);
                if ($cached !== false && is_array($cached) && !empty($cached['valid'])) {
                    // Cache still valid
                    self::$current_tier = 'pro';
                    return 'pro';
                }
                
                // Cache expired — try background re-validation
                // But don't block page load: use last known state + schedule re-check
                $instance_id = isset($license_data['instance_id']) ? $license_data['instance_id'] : '';
                $validation = self::validate_with_lemonsqueezy($license_data['license_key'], $instance_id);
                
                if ($validation['valid']) {
                    // Still valid — cache it and update last_valid_at
                    set_transient(self::CACHE_KEY, array('valid' => true, 'checked_at' => time()), self::CACHE_EXPIRY);
                    $license_data['last_valid_at'] = time();
                    update_option(self::OPTION_KEY, $license_data);
                    self::$current_tier = 'pro';
                    return 'pro';
                } elseif ($validation['error_type'] === 'connection') {
                    // API unreachable — check grace period
                    $last_valid = isset($license_data['last_valid_at']) ? (int) $license_data['last_valid_at'] : 0;
                    if ($last_valid > 0 && (time() - $last_valid) < self::GRACE_PERIOD) {
                        // Within grace period — keep pro
                        self::$current_tier = 'pro';
                        return 'pro';
                    }
                    // Grace period expired
                    self::$current_tier = 'free';
                    return 'free';
                } else {
                    // License is actually invalid (expired, revoked, etc.)
                    $license_data['status'] = 'invalid';
                    update_option(self::OPTION_KEY, $license_data);
                    delete_transient(self::CACHE_KEY);
                    self::$current_tier = 'free';
                    return 'free';
                }
            }
            
            // Status not 'active'
            self::$current_tier = 'free';
            return 'free';
        }

        // Default to FREE
        self::$current_tier = 'free';
        return self::$current_tier;
    }
    
    /**
     * Check if PRO plugin is installed (regardless of license status).
     *
     * @since    1.0.0
     * @return   bool
     */
    public static function is_pro_plugin() {
        return defined('WC_XML_CSV_AI_IMPORT_IS_PRO') && WC_XML_CSV_AI_IMPORT_IS_PRO === true;
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
     * Validate license key with LemonSqueezy API
     *
     * @since    1.0.0
     * @param    string $license_key  License key
     * @param    string $instance_id  Instance ID from activation
     * @return   array  ['valid' => bool, 'error_type' => string|null, 'message' => string]
     */
    private static function validate_with_lemonsqueezy($license_key, $instance_id = '') {
        $body = array('license_key' => $license_key);
        if (!empty($instance_id)) {
            $body['instance_id'] = $instance_id;
        }
        
        $response = wp_remote_post(self::LS_VALIDATE_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($body),
        ));
        
        // Connection error
        if (is_wp_error($response)) {
            return array(
                'valid'      => false,
                'error_type' => 'connection',
                'message'    => $response->get_error_message(),
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        // Check LemonSqueezy response
        if ($code === 200 && is_array($data) && !empty($data['valid'])) {
            // Check license status — only 'active' means valid
            $ls_status = isset($data['license_key']['status']) ? $data['license_key']['status'] : '';
            if ($ls_status === 'active') {
                return array(
                    'valid'      => true,
                    'error_type' => null,
                    'message'    => 'License is valid.',
                    'data'       => $data,
                );
            }
            // License exists but not active (expired, disabled, etc.)
            return array(
                'valid'      => false,
                'error_type' => 'invalid',
                'message'    => sprintf('License status: %s', $ls_status),
            );
        }
        
        // Invalid license key or other API error
        $error_msg = isset($data['error']) ? $data['error'] : 'Invalid license key.';
        return array(
            'valid'      => false,
            'error_type' => 'invalid',
            'message'    => $error_msg,
        );
    }
    
    /**
     * Clear license cache
     *
     * @since    1.0.0
     */
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
        self::$current_tier = null;
    }
    
    /**
     * Get license key from stored data
     *
     * @since    1.0.0
     * @return   string
     */
    public static function get_license_key() {
        $data = get_option(self::OPTION_KEY, array());
        return isset($data['license_key']) ? $data['license_key'] : '';
    }
    
    /**
     * Get full license data
     *
     * @since    1.0.0
     * @return   array
     */
    public static function get_license_data() {
        return get_option(self::OPTION_KEY, array());
    }
    
    /**
     * Get license status string
     *
     * @since    1.0.0
     * @return   string 'active', 'invalid', 'inactive', or ''
     */
    public static function get_license_status() {
        $data = get_option(self::OPTION_KEY, array());
        return isset($data['status']) ? $data['status'] : '';
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
            'pro' => '⚡ Pro',
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
        $is_pro_plugin = self::is_pro_plugin();
        
        ob_start();
        ?>
        <div class="wc-xml-csv-import-upgrade-notice" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 15px 20px; border-radius: 8px; color: white; margin: 10px 0;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div>
                    <strong style="font-size: 14px;">🔒 <?php esc_html_e('PRO Feature', 'bootflow-product-importer'); ?></strong>
                    <?php if (!empty($feature_name)): ?>
                        <span style="opacity: 0.8;"> - <?php echo esc_html($feature_name); ?></span>
                    <?php endif; ?>
                    <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 13px;">
                        <?php if ($is_pro_plugin): ?>
                            <?php esc_html_e('Activate your license key in Settings → License to unlock PRO features.', 'bootflow-product-importer'); ?>
                        <?php else: ?>
                            <?php esc_html_e('Upgrade to PRO to unlock this feature and remove all limits.', 'bootflow-product-importer'); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if (!$is_pro_plugin): ?>
                <a href="<?php echo esc_url(self::get_upgrade_url()); ?>" target="_blank" class="button" style="background: white; color: #764ba2; border: none; font-weight: 600; padding: 8px 20px; text-decoration: none;">
                    <?php esc_html_e('Upgrade to PRO', 'bootflow-product-importer'); ?> →
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Activate license key via LemonSqueezy API
     *
     * @since    1.0.0
     * @param    string $license_key License key to activate
     * @return   array  Result with 'success' and 'message'
     */
    public static function activate_license($license_key) {
        if (empty($license_key)) {
            return array(
                'success' => false,
                'message' => __('Please enter a license key.', 'bootflow-product-importer'),
            );
        }

        $license_key = sanitize_text_field(trim($license_key));

        // Call LemonSqueezy activate API
        $response = wp_remote_post(self::LS_ACTIVATE_URL, array(
            'timeout' => 20,
            'headers' => array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'license_key'   => $license_key,
                'instance_name' => home_url(),
            )),
        ));

        // Handle connection errors
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('Could not connect to the license server. Please check your internet connection and try again.', 'bootflow-product-importer'),
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check if activation was successful
        if (!is_array($data) || empty($data['activated'])) {
            $error = isset($data['error']) ? $data['error'] : __('Invalid license key.', 'bootflow-product-importer');
            return array(
                'success' => false,
                'message' => $error,
            );
        }

        // Activation successful — store license data
        $instance_id = isset($data['instance']['id']) ? $data['instance']['id'] : '';
        $ls_key_data = isset($data['license_key']) ? $data['license_key'] : array();
        $expires_at = isset($ls_key_data['expires_at']) ? $ls_key_data['expires_at'] : null;
        
        $license_data = array(
            'license_key'   => $license_key,
            'instance_id'   => $instance_id,
            'status'        => 'active',
            'activated_at'  => time(),
            'last_valid_at' => time(),
            'expires_at'    => $expires_at,
            'ls_data'       => $ls_key_data,
        );
        
        update_option(self::OPTION_KEY, $license_data);
        
        // Set validation cache
        set_transient(self::CACHE_KEY, array('valid' => true, 'checked_at' => time()), self::CACHE_EXPIRY);
        
        // Reset cached tier
        self::$current_tier = null;

        return array(
            'success' => true,
            'tier'    => 'pro',
            'message' => __('License activated successfully! PRO features are now unlocked.', 'bootflow-product-importer'),
        );
    }

    /**
     * Deactivate license via LemonSqueezy API.
     *
     * @since    1.0.0
     * @return   array  Result with 'success' and 'message'
     */
    public static function deactivate_license() {
        $license_data = get_option(self::OPTION_KEY, array());
        $license_key = isset($license_data['license_key']) ? $license_data['license_key'] : '';
        $instance_id = isset($license_data['instance_id']) ? $license_data['instance_id'] : '';
        
        // Try to deactivate on LemonSqueezy (best effort)
        if (!empty($license_key) && !empty($instance_id)) {
            wp_remote_post(self::LS_DEACTIVATE_URL, array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'license_key' => $license_key,
                    'instance_id' => $instance_id,
                )),
            ));
        }
        
        // Clear local license data regardless of API result
        delete_option(self::OPTION_KEY);
        delete_transient(self::CACHE_KEY);
        
        // Reset cached tier
        self::$current_tier = null;

        return array(
            'success' => true,
            'message' => __('License deactivated. PRO features are now locked.', 'bootflow-product-importer'),
        );
    }
    
    /**
     * Show admin notice when PRO plugin is installed but no license is active.
     *
     * @since    1.0.0
     */
    public static function maybe_show_license_notice() {
        // Only for PRO plugin without active license
        if (!self::is_pro_plugin() || self::is_valid()) {
            return;
        }
        
        // Don't show on the settings page (where they can already activate)
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'wc-xml-csv-import') !== false) {
            // Check if we're specifically on the settings page
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
            if ($page === 'wc-xml-csv-import-settings') {
                return;
            }
        }
        
        $settings_url = admin_url('admin.php?page=wc-xml-csv-import-settings');
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Bootflow PRO:</strong>
                <?php
                printf(
                    /* translators: %s: settings page URL */
                    esc_html__('Your PRO license is not activated. Please %1$sactivate your license%2$s to unlock PRO features, or use the free version from WordPress.org.', 'bootflow-product-importer'),
                    '<a href="' . esc_url($settings_url) . '">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <?php
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
