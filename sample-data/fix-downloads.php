<?php
/**
 * Fix downloadable files - add balticherbs.com to approved directories
 */
require_once '/var/www/html/mobishop.lv/wp-load.php';

echo "=== FIXING DOWNLOADABLE FILES ===" . PHP_EOL . PHP_EOL;

// Add balticherbs.com to approved directories
try {
    $container = wc_get_container();
    $register = $container->get(\Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register::class);
    
    // Add approved directory
    $result = $register->add_approved_directory('https://balticherbs.com/');
    echo "Added https://balticherbs.com/ to approved directories" . PHP_EOL;
    
    // Enable it
    if ($result) {
        $register->set_mode(\Automattic\WooCommerce\Internal\ProductDownloads\ApprovedDirectories\Register::MODE_ENABLED);
        echo "Enabled approved directories mode" . PHP_EOL;
    }
    
    // List all approved
    echo PHP_EOL . "Current approved directories:" . PHP_EOL;
    $dirs = $register->get_all_approved_directories();
    if (empty($dirs)) {
        echo "  (none)" . PHP_EOL;
    }
    foreach ($dirs as $dir) {
        echo "  - " . $dir->get_url() . " (enabled: " . ($dir->get_enabled() ? 'yes' : 'no') . ")" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

// Alternative: Check WooCommerce settings
echo PHP_EOL . "Checking WooCommerce download settings..." . PHP_EOL;

// Force file downloads to work
update_option('woocommerce_file_download_method', 'force');
echo "Set download method to: force" . PHP_EOL;

// Check current order download permissions
echo PHP_EOL . "Checking recent orders with downloadable products..." . PHP_EOL;

$orders = wc_get_orders([
    'limit' => 5,
    'status' => ['completed', 'processing'],
    'orderby' => 'date',
    'order' => 'DESC'
]);

foreach ($orders as $order) {
    echo "Order #" . $order->get_id() . " (" . $order->get_status() . "):" . PHP_EOL;
    
    $downloads = wc_get_customer_available_downloads($order->get_customer_id());
    if (empty($downloads)) {
        echo "  No downloads available for customer" . PHP_EOL;
        
        // Check if order has downloadable items
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->is_downloadable()) {
                echo "  Product " . $product->get_name() . " is downloadable but no permissions set" . PHP_EOL;
                
                // Grant download permissions
                wc_downloadable_file_permission($product->get_id(), $order);
                echo "  -> Granted download permissions" . PHP_EOL;
            }
        }
    } else {
        foreach ($downloads as $dl) {
            echo "  - " . $dl['download_name'] . ": " . $dl['download_url'] . PHP_EOL;
        }
    }
}

echo PHP_EOL . "Done!" . PHP_EOL;
