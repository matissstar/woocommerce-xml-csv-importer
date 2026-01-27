<?php
/**
 * Plugin Name: WooCommerce XML & CSV Importer
 * Plugin URI: https://bootflow.io/woocommerce-xml-csv-importer/
 * Description: Import and update WooCommerce products from XML and CSV files with manual field mapping, product variations support, and a reliable import workflow.
 * Version: 0.9.0
 * Author: BootFlow.io
 * Author URI: https://bootflow.io
 * Text Domain: wc-xml-csv-import
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.3
 * License: GPL v2 or later
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
        <p><?php _e('WooCommerce XML/CSV Smart AI Import requires WooCommerce to be installed and active.', 'wc-xml-csv-import'); ?></p>
    </div>
    <?php
}

/**
 * Suppress debug output on plugin pages
 */
function wc_xml_csv_ai_import_suppress_debug_output() {
    if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'wc-xml-csv-import') !== false) {
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
define('WC_XML_CSV_AI_IMPORT_VERSION', '1.0.1');
define('WC_XML_CSV_AI_IMPORT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_XML_CSV_AI_IMPORT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_XML_CSV_AI_IMPORT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WC_XML_CSV_AI_IMPORT_TEXT_DOMAIN', 'wc-xml-csv-import');

// Ensure clean output for production
if (!defined('WC_XML_CSV_AI_IMPORT_DEBUG')) {
    define('WC_XML_CSV_AI_IMPORT_DEBUG', false);
}

// Load security class early
require_once plugin_dir_path(__FILE__) . 'includes/class-wc-xml-csv-ai-import-security.php';
WC_XML_CSV_AI_Import_Security::init(); // Initialize security measures

// Load license/tier management
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
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-xml-csv-import') . '">' . __('Import', 'wc-xml-csv-import') . '</a>';
    $settings_link .= ' | <a href="' . admin_url('admin.php?page=wc-xml-csv-import-settings') . '">' . __('Settings', 'wc-xml-csv-import') . '</a>';
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