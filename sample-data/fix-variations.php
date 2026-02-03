<?php
/**
 * Fix variation attributes - convert names to slugs
 */
require_once '/var/www/html/mobishop.lv/wp-load.php';

// Fix all TEST- variable products
$product_ids = [229076, 229081, 229086, 229090, 229093];

foreach ($product_ids as $product_id) {
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'variable') continue;
    
    echo 'Fixing: ' . $product->get_name() . ' (ID: ' . $product_id . ')' . PHP_EOL;
    
    $variations = $product->get_children();
    foreach ($variations as $var_id) {
        $variation = wc_get_product($var_id);
        if (!$variation) continue;
        
        $attrs = $variation->get_attributes();
        $fixed_attrs = [];
        
        foreach ($attrs as $taxonomy => $value) {
            // Convert value to slug
            $slug = sanitize_title($value);
            $fixed_attrs[$taxonomy] = $slug;
            
            // Also make sure term exists with this slug
            if (taxonomy_exists($taxonomy)) {
                $term = get_term_by('slug', $slug, $taxonomy);
                if (!$term) {
                    // Create term with slug matching name
                    wp_insert_term($value, $taxonomy, ['slug' => $slug]);
                    echo "    Created term: $value ($slug) in $taxonomy" . PHP_EOL;
                }
            }
        }
        
        $variation->set_attributes($fixed_attrs);
        $variation->save();
        echo '  Fixed variation: ' . $variation->get_sku() . ' => ' . json_encode($fixed_attrs) . PHP_EOL;
    }
    
    // Sync variations
    WC_Product_Variable::sync($product_id);
    wc_delete_product_transients($product_id);
    echo '  Synced!' . PHP_EOL . PHP_EOL;
}

echo 'Done!' . PHP_EOL;
