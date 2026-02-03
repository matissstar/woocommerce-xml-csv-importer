<?php
/**
 * Comprehensive Import Test Script
 * Imports 10 products with different structures
 */

// Load WordPress
$wp_load_paths = [
    dirname(__FILE__, 6) . '/wp-load.php',
    '/var/www/html/mobishop.lv/wp-load.php',
];

$loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    die("Could not load WordPress\n");
}

echo "=== COMPREHENSIVE IMPORT TEST ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Load the XML file
$xml_file = __DIR__ . '/comprehensive-test.xml';
if (!file_exists($xml_file)) {
    die("XML file not found: $xml_file\n");
}

$xml = simplexml_load_file($xml_file);
if (!$xml) {
    die("Failed to parse XML\n");
}

echo "Loaded XML with " . count($xml->product) . " products\n\n";

// Import settings
$default_image = 'https://balticherbs.com/email.png';

// Track results
$results = [
    'success' => [],
    'failed' => [],
    'updated' => []
];

$product_index = 0;
foreach ($xml->product as $product_data) {
    $product_index++;
    $sku = (string) $product_data->sku;
    $name = (string) $product_data->name;
    $type = (string) $product_data->type ?: 'simple';
    
    echo "----------------------------------------\n";
    echo "Product $product_index: $name\n";
    echo "SKU: $sku | Type: $type\n";
    
    // Check if product exists
    $existing_id = wc_get_product_id_by_sku($sku);
    
    if ($existing_id) {
        echo "  -> Product already exists (ID: $existing_id), updating...\n";
        $product = wc_get_product($existing_id);
        $is_update = true;
    } else {
        echo "  -> Creating new product...\n";
        $is_update = false;
        
        // Create product based on type
        if ($type === 'variable') {
            $product = new WC_Product_Variable();
        } else {
            $product = new WC_Product_Simple();
        }
    }
    
    // Set basic data
    $product->set_name($name);
    $product->set_sku($sku);
    $product->set_description((string) $product_data->description);
    $product->set_short_description((string) $product_data->short_description);
    
    // Price
    if (!empty((string) $product_data->price)) {
        $product->set_regular_price((string) $product_data->price);
    }
    if (!empty((string) $product_data->sale_price)) {
        $product->set_sale_price((string) $product_data->sale_price);
    }
    
    // Stock
    $manage_stock = strtolower((string) $product_data->manage_stock);
    if ($manage_stock === 'yes' || $manage_stock === '1' || $manage_stock === 'true') {
        $product->set_manage_stock(true);
        $product->set_stock_quantity((int) $product_data->stock_quantity);
    } else {
        $product->set_manage_stock(false);
    }
    
    $stock_status = (string) $product_data->stock_status ?: 'instock';
    $product->set_stock_status($stock_status);
    
    // Virtual & Downloadable
    $virtual = strtolower((string) $product_data->virtual);
    $is_virtual = ($virtual === 'yes' || $virtual === '1' || $virtual === 'true');
    $product->set_virtual($is_virtual);
    
    $downloadable = strtolower((string) $product_data->downloadable);
    $is_downloadable = ($downloadable === 'yes' || $downloadable === '1' || $downloadable === 'true');
    $product->set_downloadable($is_downloadable);
    
    echo "  Virtual: " . ($is_virtual ? 'YES' : 'NO') . " | Downloadable: " . ($is_downloadable ? 'YES' : 'NO') . "\n";
    
    // Dimensions (only for non-virtual)
    if (!$is_virtual) {
        if (!empty((string) $product_data->weight)) {
            $product->set_weight((string) $product_data->weight);
        }
        if (!empty((string) $product_data->length)) {
            $product->set_length((string) $product_data->length);
        }
        if (!empty((string) $product_data->width)) {
            $product->set_width((string) $product_data->width);
        }
        if (!empty((string) $product_data->height)) {
            $product->set_height((string) $product_data->height);
        }
    }
    
    // Downloads - store as post meta directly to bypass WooCommerce validation
    if ($is_downloadable && isset($product_data->downloads)) {
        $downloads_array = [];
        foreach ($product_data->downloads->download as $dl) {
            $file_url = (string) $dl->file;
            $file_name = (string) $dl->name;
            $file_id = md5($file_url);
            
            $downloads_array[$file_id] = [
                'id'   => $file_id,
                'name' => $file_name,
                'file' => $file_url,
            ];
            echo "  + Download: $file_name\n";
        }
        
        // Save product first to get ID, then update meta directly
        $product_id = $product->save();
        
        // Update downloads directly in post meta (bypasses validation)
        update_post_meta($product_id, '_downloadable_files', $downloads_array);
        
        // Download limits
        $limit = (string) $product_data->download_limit;
        $expiry = (string) $product_data->download_expiry;
        if ($limit !== '') {
            $product->set_download_limit((int) $limit);
        }
        if ($expiry !== '') {
            $product->set_download_expiry((int) $expiry);
        }
    }
    
    // Categories
    if (!empty((string) $product_data->categories)) {
        $cat_string = (string) $product_data->categories;
        $cat_parts = explode('/', $cat_string);
        $cat_ids = [];
        $parent_id = 0;
        
        foreach ($cat_parts as $cat_name) {
            $cat_name = trim($cat_name);
            if (empty($cat_name)) continue;
            
            // Check if category exists
            $term = get_term_by('name', $cat_name, 'product_cat');
            if (!$term) {
                // Create category
                $result = wp_insert_term($cat_name, 'product_cat', ['parent' => $parent_id]);
                if (!is_wp_error($result)) {
                    $cat_ids[] = $result['term_id'];
                    $parent_id = $result['term_id'];
                }
            } else {
                $cat_ids[] = $term->term_id;
                $parent_id = $term->term_id;
            }
        }
        
        if (!empty($cat_ids)) {
            $product->set_category_ids($cat_ids);
        }
    }
    
    // Save the product first
    $product_id = $product->save();
    echo "  Product saved: ID $product_id\n";
    
    // Set image (skip if upload fails)
    $image_url = $default_image;
    if (isset($product_data->images->image)) {
        $image_url = (string) $product_data->images->image;
    }
    
    // Import image - continue even if it fails
    try {
        $image_id = import_image($image_url, $product_id, $name);
        if ($image_id) {
            $product->set_image_id($image_id);
            $product->save();
            echo "  Image set: ID $image_id\n";
        } else {
            echo "  Image skipped (upload issue)\n";
        }
    } catch (Exception $e) {
        echo "  Image skipped: " . $e->getMessage() . "\n";
    }
    
    // Handle variations for variable products
    if ($type === 'variable' && isset($product_data->variations)) {
        echo "  Processing variations...\n";
        
        // Collect all attributes from variations
        $all_attributes = [];
        $variation_count = 0;
        
        foreach ($product_data->variations->variation as $var_data) {
            $variation_count++;
            
            // Get attributes from variation
            $var_attrs = [];
            
            // Check nested <attributes> element
            if (isset($var_data->attributes)) {
                foreach ($var_data->attributes->children() as $attr_name => $attr_value) {
                    $var_attrs[$attr_name] = (string) $attr_value;
                    if (!isset($all_attributes[$attr_name])) {
                        $all_attributes[$attr_name] = [];
                    }
                    $all_attributes[$attr_name][] = (string) $attr_value;
                }
            }
            
            // Check inline attributes (not in <attributes> container)
            $system_fields = ['sku', 'price', 'sale_price', 'stock_quantity', 'stock_status', 
                              'manage_stock', 'weight', 'length', 'width', 'height', 
                              'virtual', 'downloadable', 'download_limit', 'download_expiry',
                              'downloads', 'image', 'attributes'];
            
            foreach ($var_data->children() as $field_name => $field_value) {
                if (!in_array($field_name, $system_fields) && !isset($var_attrs[$field_name])) {
                    $var_attrs[$field_name] = (string) $field_value;
                    if (!isset($all_attributes[$field_name])) {
                        $all_attributes[$field_name] = [];
                    }
                    $all_attributes[$field_name][] = (string) $field_value;
                }
            }
        }
        
        echo "  Found $variation_count variations with " . count($all_attributes) . " attribute(s)\n";
        
        // Create product attributes
        $product_attributes = [];
        foreach ($all_attributes as $attr_name => $values) {
            $unique_values = array_unique($values);
            $taxonomy = 'pa_' . sanitize_title($attr_name);
            
            // Create or get attribute taxonomy
            $attribute_id = wc_attribute_taxonomy_id_by_name($attr_name);
            if (!$attribute_id) {
                $attribute_id = wc_create_attribute([
                    'name' => ucfirst($attr_name),
                    'slug' => sanitize_title($attr_name),
                    'type' => 'select',
                    'order_by' => 'menu_order',
                    'has_archives' => false,
                ]);
                
                if (!is_wp_error($attribute_id)) {
                    // Register the taxonomy
                    register_taxonomy($taxonomy, 'product', [
                        'hierarchical' => false,
                        'labels' => ['name' => ucfirst($attr_name)],
                        'show_ui' => false,
                        'query_var' => true,
                        'rewrite' => false,
                    ]);
                    echo "  + Created attribute: $attr_name (ID: $attribute_id)\n";
                }
            }
            
            // Create terms
            foreach ($unique_values as $value) {
                if (!term_exists($value, $taxonomy)) {
                    wp_insert_term($value, $taxonomy);
                }
            }
            
            // Set up product attribute
            $attribute = new WC_Product_Attribute();
            $attribute->set_id($attribute_id);
            $attribute->set_name($taxonomy);
            $attribute->set_options($unique_values);
            $attribute->set_position(count($product_attributes));
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            
            $product_attributes[] = $attribute;
            echo "  + Attribute: $attr_name = " . implode(', ', $unique_values) . "\n";
        }
        
        $product->set_attributes($product_attributes);
        $product->save();
        
        // Create variations
        foreach ($product_data->variations->variation as $var_data) {
            $var_sku = (string) $var_data->sku;
            
            // Check if variation exists
            $existing_var_id = wc_get_product_id_by_sku($var_sku);
            
            if ($existing_var_id) {
                $variation = wc_get_product($existing_var_id);
            } else {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
            }
            
            $variation->set_sku($var_sku);
            
            if (!empty((string) $var_data->price)) {
                $variation->set_regular_price((string) $var_data->price);
            }
            if (!empty((string) $var_data->sale_price)) {
                $variation->set_sale_price((string) $var_data->sale_price);
            }
            
            // Stock
            $var_manage = strtolower((string) $var_data->manage_stock);
            if ($var_manage === 'yes' || $var_manage === '1' || $var_manage === 'true') {
                $variation->set_manage_stock(true);
                $variation->set_stock_quantity((int) $var_data->stock_quantity);
            }
            $variation->set_stock_status((string) $var_data->stock_status ?: 'instock');
            
            // Virtual/Downloadable for variation
            $var_virtual = strtolower((string) $var_data->virtual);
            if ($var_virtual === 'yes' || $var_virtual === '1' || $var_virtual === 'true') {
                $variation->set_virtual(true);
            } else {
                $variation->set_virtual($is_virtual); // Inherit from parent
            }
            
            $var_downloadable = strtolower((string) $var_data->downloadable);
            $var_has_downloads = ($var_downloadable === 'yes' || $var_downloadable === '1' || $var_downloadable === 'true');
            if ($var_has_downloads) {
                $variation->set_downloadable(true);
            }
            
            // Weight
            if (!empty((string) $var_data->weight)) {
                $variation->set_weight((string) $var_data->weight);
            }
            
            // Set variation attributes (use slugs, not display names!)
            $var_attrs = [];
            
            // From nested <attributes>
            if (isset($var_data->attributes)) {
                foreach ($var_data->attributes->children() as $attr_name => $attr_value) {
                    $taxonomy = 'pa_' . sanitize_title($attr_name);
                    $var_attrs[$taxonomy] = sanitize_title((string) $attr_value); // Use slug!
                }
            }
            
            // From inline
            foreach ($var_data->children() as $field_name => $field_value) {
                if (!in_array($field_name, $system_fields)) {
                    $taxonomy = 'pa_' . sanitize_title($field_name);
                    if (!isset($var_attrs[$taxonomy])) {
                        $var_attrs[$taxonomy] = sanitize_title((string) $field_value); // Use slug!
                    }
                }
            }
            
            $variation->set_attributes($var_attrs);
            
            $var_id = $variation->save();
            
            // Handle variation downloads after save (bypass validation)
            if ($var_has_downloads && isset($var_data->downloads)) {
                $var_downloads_array = [];
                foreach ($var_data->downloads->download as $dl) {
                    $file_url = (string) $dl->file;
                    $file_name = (string) $dl->name;
                    $file_id = md5($file_url . $var_sku);
                    
                    $var_downloads_array[$file_id] = [
                        'id'   => $file_id,
                        'name' => $file_name,
                        'file' => $file_url,
                    ];
                }
                update_post_meta($var_id, '_downloadable_files', $var_downloads_array);
                
                $var_limit = (string) $var_data->download_limit;
                if ($var_limit !== '') {
                    update_post_meta($var_id, '_download_limit', (int) $var_limit);
                }
            }
            
            echo "    - Variation: $var_sku (ID: $var_id)\n";
        }
        
        // Sync variations
        WC_Product_Variable::sync($product_id);
    }
    
    // Record result
    if ($is_update) {
        $results['updated'][] = ['id' => $product_id, 'sku' => $sku, 'name' => $name];
    } else {
        $results['success'][] = ['id' => $product_id, 'sku' => $sku, 'name' => $name];
    }
}

echo "\n========================================\n";
echo "IMPORT COMPLETE\n";
echo "========================================\n";
echo "New products: " . count($results['success']) . "\n";
echo "Updated: " . count($results['updated']) . "\n";
echo "Failed: " . count($results['failed']) . "\n";
echo "\n";

echo "Created Products:\n";
foreach ($results['success'] as $p) {
    echo "  [NEW] ID: {$p['id']} | SKU: {$p['sku']} | {$p['name']}\n";
}
foreach ($results['updated'] as $p) {
    echo "  [UPD] ID: {$p['id']} | SKU: {$p['sku']} | {$p['name']}\n";
}

echo "\n";

/**
 * Import image from URL
 */
function import_image($url, $post_id, $title) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    
    // Check if we already have this image
    global $wpdb;
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_source_url' AND meta_value = %s LIMIT 1",
        $url
    ));
    
    if ($existing) {
        return $existing;
    }
    
    // Download image
    $tmp = download_url($url);
    if (is_wp_error($tmp)) {
        echo "  ! Image download failed: " . $tmp->get_error_message() . "\n";
        return false;
    }
    
    $file_array = [
        'name' => basename(parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp
    ];
    
    $id = media_handle_sideload($file_array, $post_id, $title);
    
    if (is_wp_error($id)) {
        @unlink($tmp);
        echo "  ! Image sideload failed: " . $id->get_error_message() . "\n";
        return false;
    }
    
    // Store source URL for future reference
    update_post_meta($id, '_source_url', $url);
    
    return $id;
}
