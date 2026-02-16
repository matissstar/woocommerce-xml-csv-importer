<?php
/**
 * Plugin Name: Bootflow – WooCommerce XML & CSV Importer
 * Plugin URI:  https://bootflow.io/woocommerce-xml-csv-importer/
 * Description: Import and update WooCommerce products from XML and CSV feeds with manual field mapping, product variations support, and a reliable import workflow.
 * Version:     0.9.0
 * Author:      Bootflow
 * Author URI:  https://bootflow.io
 * Text Domain: bootflow-woocommerce-xml-csv-importer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'wc_xml_csv_ai_import_woocommerce_missing_notice');
    return;
}

function wc_xml_csv_ai_import_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('WooCommerce XML/CSV Smart Import requires WooCommerce to be installed and active.', 'bootflow-woocommerce-xml-csv-importer'); ?></p>
    </div>
    <?php
}

/**
 * Suppress debug output on plugin pages
 */
function wc_xml_csv_ai_import_suppress_debug_output() {
    // WP.org compliance: sanitize input
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if (is_admin() && strpos($page, 'bootflow-woocommerce-xml-csv-importer') !== false) {
        if (!WC_XML_CSV_AI_IMPORT_DEBUG) {
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            
            // Clean any existing output
            if (ob_get_level() && !headers_sent()) {
                ob_clean();
            }
        }
    }
}
add_action('admin_init', 'wc_xml_csv_ai_import_suppress_debug_output', 1);

/**
 * Currently plugin version.
 */
define('WC_XML_CSV_AI_IMPORT_VERSION', '0.9.1-test-2033');
define('WC_XML_CSV_AI_IMPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_XML_CSV_AI_IMPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_XML_CSV_AI_IMPORT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WC_XML_CSV_AI_IMPORT_TEXT_DOMAIN', 'bootflow-woocommerce-xml-csv-importer'); // WP.org compliance: text domain must match plugin slug

/**
 * Pro/Free edition flag
 * This constant is set by the build script:
 * - true = Pro version (with license validation)
 * - false = Free version (WordPress.org compliant)
 */
if (!defined('WC_XML_CSV_AI_IMPORT_IS_PRO')) {
    define('WC_XML_CSV_AI_IMPORT_IS_PRO', true); // BUILD_SCRIPT_WILL_CHANGE_THIS
}

// Ensure clean output for production
if (!defined('WC_XML_CSV_AI_IMPORT_DEBUG')) {
    define('WC_XML_CSV_AI_IMPORT_DEBUG', false);
}

// Load security class early
require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-security.php';
WC_XML_CSV_AI_Import_Security::init(); // Initialize security measures

// Load features class (Pro/Free feature detection)
require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-features.php';

// Load license/tier management (Pro only, but class exists in both for compatibility)
require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-license.php';

// Load logger class (respects WP_DEBUG setting)
require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-logger.php';

/**
 * The code that runs during plugin activation.
 */
function activate_wc_xml_csv_ai_import() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-activator.php';
    WC_XML_CSV_AI_Import_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wc_xml_csv_ai_import() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-deactivator.php';
    WC_XML_CSV_AI_Import_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_wc_xml_csv_ai_import');
register_deactivation_hook(__FILE__, 'deactivate_wc_xml_csv_ai_import');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import.php';

/**
 * Begins execution of the plugin.
 */
function run_wc_xml_csv_ai_import() {
    $plugin = new WC_XML_CSV_AI_Import();
    $plugin->run();
}

run_wc_xml_csv_ai_import();

/**
 * Check and update database schema on every load (for existing installations)
 */
add_action('plugins_loaded', 'wc_xml_csv_ai_import_check_db_version');

function wc_xml_csv_ai_import_check_db_version() {
    $current_db_version = get_option('wc_xml_csv_ai_import_db_version', '1.0.0');
    $required_db_version = '1.2.0';
    
    if (version_compare($current_db_version, $required_db_version, '<')) {
        // Run migration
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-activator.php';
        WC_XML_CSV_AI_Import_Activator::activate();
    }
}

/**
 * Add plugin action links
 */
add_filter('plugin_action_links_' . WC_XML_CSV_AI_IMPORT_PLUGIN_BASENAME, 'wc_xml_csv_ai_import_action_links');

function wc_xml_csv_ai_import_action_links($links) {
    // WP.org compliance: proper escaping for URLs and text
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-xml-csv-import')) . '">' . esc_html__('Import', 'bootflow-woocommerce-xml-csv-importer') . '</a>';
    $settings_link .= ' | <a href="' . esc_url(admin_url('admin.php?page=wc-xml-csv-import-settings')) . '">' . esc_html__('Settings', 'bootflow-woocommerce-xml-csv-importer') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * HPOS compatibility declaration
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});