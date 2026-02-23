<?php
/**
 * License stub for FREE version
 * Returns 'free' tier and upgrade URL, no API calls
 */
if (!defined('WPINC')) {
    die;
}

class WC_XML_CSV_AI_Import_License {
    public static function get_tier() {
        return 'free';
    }
    
    public static function is_pro() {
        return false;
    }
    
    public static function is_feature_available($feature) {
        $free_features = array('manual_import', 'xml_import', 'csv_import', 'field_mapping', 'variable_products', 'attributes', 'unlimited_products');
        return in_array($feature, $free_features);
    }
    
    public static function get_upgrade_url() {
        return 'https://bootflow.io/woocommerce-xml-csv-importer/';
    }
    
    public static function get_tier_name($tier = null) {
        return 'Free';
    }
    
    public static function activate_license($license_key) {
        return array('success' => false, 'message' => __('License activation is only available in the Pro version.', 'bootflow-woocommerce-xml-csv-importer'));
    }
    
    public static function deactivate_license() {
        return array('success' => false, 'message' => __('License deactivation is only available in the Pro version.', 'bootflow-woocommerce-xml-csv-importer'));
    }
}

function wc_xml_csv_ai_get_tier() {
    return 'free';
}
