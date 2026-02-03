<?php
require_once '/var/www/html/mobishop.lv/wp-load.php';

// Manually build downloads like WooCommerce does
global $wpdb;

$customer_id = 1;
$table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';

$sql = "SELECT * FROM $table WHERE user_id = $customer_id AND order_id = 229105 ORDER BY permission_id";
$results = $wpdb->get_results($sql);

echo "All permissions for customer 1, order 229105:\n";
foreach ($results as $r) {
    $product = wc_get_product($r->product_id);
    if (!$product) {
        echo "Product {$r->product_id} not found!\n";
        continue;
    }
    
    $downloads = $product->get_downloads();
    echo "Product: " . $product->get_name() . " (ID: {$r->product_id})\n";
    echo "Download ID from permission: {$r->download_id}\n";
    
    if (isset($downloads[$r->download_id])) {
        $dl = $downloads[$r->download_id];
        echo "Download found: " . $dl->get_name() . "\n";
        echo "File: " . $dl->get_file() . "\n";
    } else {
        echo "Download NOT FOUND in product!\n";
        echo "Available download IDs: " . implode(', ', array_keys($downloads)) . "\n";
    }
    echo "---\n";
}

// Also check what wc_get_customer_available_downloads returns with debug
echo "\n\nWhat WooCommerce returns:\n";
$wc_downloads = wc_get_customer_available_downloads($customer_id);
foreach ($wc_downloads as $dl) {
    if ($dl['order_id'] == 229105) {
        echo "Order 229105: {$dl['product_name']} - {$dl['download_name']}\n";
        echo "  File: {$dl['file']}\n";
    }
}
