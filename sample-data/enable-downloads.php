<?php
/**
 * Fix disabled downloads - enable all download files
 */
require_once '/var/www/html/mobishop.lv/wp-load.php';

echo "=== FIXING DISABLED DOWNLOADS ===" . PHP_EOL . PHP_EOL;

// Products with downloadable files
$product_ids = [229074, 229075, 229098, 229090, 229091, 229092];

foreach ($product_ids as $product_id) {
    $product = wc_get_product($product_id);
    if (!$product) continue;
    
    echo "Product: " . $product->get_name() . " (ID: $product_id)" . PHP_EOL;
    
    $downloads = $product->get_downloads();
    $updated = false;
    
    foreach ($downloads as $key => $download) {
        $enabled = $download->get_enabled();
        echo "  - " . $download->get_name() . ": " . ($enabled ? "enabled" : "DISABLED");
        
        if (!$enabled) {
            // Enable the download by updating meta directly
            $download->set_enabled(true);
            $updated = true;
            echo " -> ENABLING";
        }
        echo PHP_EOL;
    }
    
    if ($updated) {
        // Re-save downloads
        $product->set_downloads($downloads);
        $product->save();
        echo "  SAVED!" . PHP_EOL;
    }
    echo PHP_EOL;
}

// Also add balticherbs.com to approved directories via database
global $wpdb;

// Check if table exists
$table = $wpdb->prefix . 'wc_download_directories';
$exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");

if (!$exists) {
    echo "Creating wc_download_directories table..." . PHP_EOL;
    
    // Create the table
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        url_id BIGINT UNSIGNED NOT NULL auto_increment,
        url varchar(256) NOT NULL,
        enabled tinyint(1) NOT NULL DEFAULT '0',
        PRIMARY KEY  (url_id),
        KEY url (url(191))
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    
    echo "Table created!" . PHP_EOL;
}

// Add balticherbs.com if not exists
$existing = $wpdb->get_var($wpdb->prepare(
    "SELECT url_id FROM $table WHERE url LIKE %s",
    '%balticherbs.com%'
));

if (!$existing) {
    $wpdb->insert($table, [
        'url' => 'https://balticherbs.com/',
        'enabled' => 1
    ]);
    echo "Added https://balticherbs.com/ to approved directories" . PHP_EOL;
} else {
    $wpdb->update($table, ['enabled' => 1], ['url_id' => $existing]);
    echo "Enabled https://balticherbs.com/ in approved directories" . PHP_EOL;
}

// List all directories
$dirs = $wpdb->get_results("SELECT * FROM $table");
echo PHP_EOL . "Approved directories:" . PHP_EOL;
foreach ($dirs as $dir) {
    echo "  - " . $dir->url . " (enabled: " . $dir->enabled . ")" . PHP_EOL;
}

echo PHP_EOL . "Done!" . PHP_EOL;
