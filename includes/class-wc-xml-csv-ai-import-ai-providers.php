<?php
/**
 * AI Providers for field processing.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

/**
 * AI Providers class.
 */
class WC_XML_CSV_AI_Import_AI_Providers {

    /**
     * Settings
     *
     * @var array
     */
    private $settings;

    /**
     * Cache for API responses
     *
     * @var array
     */
    private static $response_cache = array();

    /**
     * Constructor.
     */
    public function __construct() {
        $this->settings = get_option('wc_xml_csv_ai_import_settings', array());
    }

    /**
     * Process field value using specified AI provider.
     *
     * @since    1.0.0
     * @param    string $value Original field value
     * @param    string $prompt AI prompt
     * @param    array $config Processing configuration
     * @param    array $context Additional context data
     * @return   string Processed value
     */
    public function process_field($value, $prompt, $config = array(), $context = array()) {
        try {
            $provider = isset($config['provider']) ? $config['provider'] : $this->settings['default_ai_provider'] ?? 'openai';
            
            // Check cache first
            if (isset($this->settings['enable_ai_caching']) && $this->settings['enable_ai_caching']) {
                $cache_key = md5($provider . $prompt . $value);
                if (isset(self::$response_cache[$cache_key])) {
                    return self::$response_cache[$cache_key];
                }
            }

            // Build context-aware prompt
            $full_prompt = $this->build_context_prompt($prompt, $value, $context);

            $result = '';
            switch ($provider) {
                case 'openai':
                    $result = $this->process_with_openai($full_prompt, $config);
                    break;
                case 'gemini':
                    $result = $this->process_with_gemini($full_prompt, $config);
                    break;
                case 'claude':
                    $result = $this->process_with_claude($full_prompt, $config);
                    break;
                case 'grok':
                    $result = $this->process_with_grok($full_prompt, $config);
                    break;
                case 'copilot':
                    $result = $this->process_with_copilot($full_prompt, $config);
                    break;
                default:
                    throw new Exception(sprintf(__('Unknown AI provider: %s', 'wc-xml-csv-import'), $provider));
            }

            // Check if result is empty
            if (empty($result)) {
                throw new Exception('AI provider returned empty response');
            }

            // Cache the result
            if (isset($this->settings['enable_ai_caching']) && $this->settings['enable_ai_caching']) {
                self::$response_cache[$cache_key] = $result;
            }

            return $result;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('AI Processing Error: ' . $e->getMessage()); }
            // Re-throw the exception so calling code can handle it
            throw $e;
        }
    }

    /**
     * Process with OpenAI GPT.
     *
     * @since    1.0.0
     * @param    string $prompt Full prompt
     * @param    array $config Configuration
     * @return   string Processed value
     */
    private function process_with_openai($prompt, $config = array()) {
        $api_key = $this->get_api_key('openai');
        if (empty($api_key)) {
            throw new Exception(__('OpenAI API key not configured.', 'wc-xml-csv-import'));
        }

        $model = isset($config['model']) ? $config['model'] : 'gpt-3.5-turbo';
        $max_tokens = isset($config['max_tokens']) ? $config['max_tokens'] : 1000;
        $temperature = isset($config['temperature']) ? $config['temperature'] : 0.7;

        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a helpful assistant for e-commerce product data processing. Provide concise, accurate responses.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => $this->settings['ai_request_timeout'] ?? 60
        ));

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('OpenAI WP Error: ' . $error_msg);
            }
            throw new Exception('OpenAI API request failed: ' . $error_msg);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_raw = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('OpenAI HTTP Code: ' . $http_code);
            error_log('OpenAI Response: ' . substr($response_raw, 0, 500));
        }

        $response_body = json_decode($response_raw, true);

        if (isset($response_body['error'])) {
            $error_msg = $response_body['error']['message'] ?? 'Unknown error';
            $error_type = $response_body['error']['type'] ?? 'unknown';
            throw new Exception('OpenAI API error (' . $error_type . '): ' . $error_msg);
        }

        if (!isset($response_body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid OpenAI API response format. HTTP: ' . $http_code . ', Response: ' . substr($response_raw, 0, 200));
        }

        return trim($response_body['choices'][0]['message']['content']);
    }

    /**
     * Process with Google Gemini.
     *
     * @since    1.0.0
     * @param    string $prompt Full prompt
     * @param    array $config Configuration
     * @return   string Processed value
     */
    private function process_with_gemini($prompt, $config = array()) {
        $api_key = $this->get_api_key('gemini');
        if (empty($api_key)) {
            throw new Exception(__('Google Gemini API key not configured.', 'wc-xml-csv-import'));
        }

        $model = isset($config['model']) ? $config['model'] : 'gemini-pro';

        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => isset($config['temperature']) ? $config['temperature'] : 0.7,
                'maxOutputTokens' => isset($config['max_tokens']) ? $config['max_tokens'] : 1000
            )
        );

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => $this->settings['ai_request_timeout'] ?? 30
        ));

        if (is_wp_error($response)) {
            throw new Exception('Gemini API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            throw new Exception('Gemini API error: ' . $response_body['error']['message']);
        }

        if (!isset($response_body['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Invalid Gemini API response format.');
        }

        return trim($response_body['candidates'][0]['content']['parts'][0]['text']);
    }

    /**
     * Process with Anthropic Claude.
     *
     * @since    1.0.0
     * @param    string $prompt Full prompt
     * @param    array $config Configuration
     * @return   string Processed value
     */
    private function process_with_claude($prompt, $config = array()) {
        $api_key = $this->get_api_key('claude');
        if (empty($api_key)) {
            throw new Exception(__('Anthropic Claude API key not configured.', 'wc-xml-csv-import'));
        }

        $model = isset($config['model']) ? $config['model'] : 'claude-3-haiku-20240307';
        $max_tokens = isset($config['max_tokens']) ? $config['max_tokens'] : 1000;

        $data = array(
            'model' => $model,
            'max_tokens' => $max_tokens,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01'
            ),
            'body' => json_encode($data),
            'timeout' => $this->settings['ai_request_timeout'] ?? 30
        ));

        if (is_wp_error($response)) {
            throw new Exception('Claude API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            throw new Exception('Claude API error: ' . $response_body['error']['message']);
        }

        if (!isset($response_body['content'][0]['text'])) {
            throw new Exception('Invalid Claude API response format.');
        }

        return trim($response_body['content'][0]['text']);
    }

    /**
     * Process with xAI Grok.
     *
     * @since    1.0.0
     * @param    string $prompt Full prompt
     * @param    array $config Configuration
     * @return   string Processed value
     */
    private function process_with_grok($prompt, $config = array()) {
        $api_key = $this->get_api_key('grok');
        if (empty($api_key)) {
            throw new Exception(__('xAI Grok API key not configured.', 'wc-xml-csv-import'));
        }

        // Note: Grok API might have different endpoints/structure
        // This is a placeholder implementation
        $model = isset($config['model']) ? $config['model'] : 'grok-1';

        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => isset($config['max_tokens']) ? $config['max_tokens'] : 1000,
            'temperature' => isset($config['temperature']) ? $config['temperature'] : 0.7
        );

        // This URL is hypothetical - replace with actual Grok API endpoint when available
        $response = wp_remote_post('https://api.x.ai/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => $this->settings['ai_request_timeout'] ?? 30
        ));

        if (is_wp_error($response)) {
            throw new Exception('Grok API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            throw new Exception('Grok API error: ' . $response_body['error']['message']);
        }

        // Adjust according to actual Grok API response format
        if (!isset($response_body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid Grok API response format.');
        }

        return trim($response_body['choices'][0]['message']['content']);
    }

    /**
     * Process with Microsoft Copilot (Azure OpenAI).
     *
     * @since    1.0.0
     * @param    string $prompt Full prompt
     * @param    array $config Configuration
     * @return   string Processed value
     */
    private function process_with_copilot($prompt, $config = array()) {
        $api_key = $this->get_api_key('copilot');
        $endpoint = $this->settings['copilot_endpoint'] ?? '';
        
        if (empty($api_key) || empty($endpoint)) {
            throw new Exception(__('Microsoft Copilot API key or endpoint not configured.', 'wc-xml-csv-import'));
        }

        $deployment_name = isset($config['deployment']) ? $config['deployment'] : 'gpt-35-turbo';
        $api_version = isset($config['api_version']) ? $config['api_version'] : '2023-12-01-preview';

        $data = array(
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a helpful assistant for e-commerce product data processing. Provide concise, accurate responses.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => isset($config['max_tokens']) ? $config['max_tokens'] : 1000,
            'temperature' => isset($config['temperature']) ? $config['temperature'] : 0.7
        );

        $url = rtrim($endpoint, '/') . "/openai/deployments/{$deployment_name}/chat/completions?api-version={$api_version}";

        $response = wp_remote_post($url, array(
            'headers' => array(
                'api-key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => $this->settings['ai_request_timeout'] ?? 30
        ));

        if (is_wp_error($response)) {
            throw new Exception('Copilot API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            throw new Exception('Copilot API error: ' . $response_body['error']['message']);
        }

        if (!isset($response_body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid Copilot API response format.');
        }

        return trim($response_body['choices'][0]['message']['content']);
    }

    /**
     * Build context-aware prompt.
     *
     * @since    1.0.0
     * @param    string $user_prompt User-provided prompt
     * @param    string $value Field value
     * @param    array $context Product context
     * @return   string Full prompt with context
     */
    private function build_context_prompt($user_prompt, $value, $context = array()) {
        $prompt = $user_prompt . "\n\n";
        $prompt .= "Value to process: \"" . $value . "\"\n\n";

        if (!empty($context)) {
            $prompt .= "Product context:\n";
            
            if (isset($context['name'])) {
                $prompt .= "- Product name (processed): " . $context['name'] . "\n";
            }
            if (isset($context['category'])) {
                $prompt .= "- Category: " . $context['category'] . "\n";
            }
            if (isset($context['brand'])) {
                $prompt .= "- Brand: " . $context['brand'] . "\n";
            }
            if (isset($context['price'])) {
                $prompt .= "- Price: " . $context['price'] . "€\n";
            }
            if (isset($context['ean'])) {
                $prompt .= "- EAN: " . $context['ean'] . "\n";
            }
            if (isset($context['gtin'])) {
                $prompt .= "- GTIN: " . $context['gtin'] . "\n";
            }
            
            $prompt .= "\n";
        }

        $prompt .= "Please provide only the processed value without additional explanation or formatting.";

        return $prompt;
    }

    /**
     * Get API key for provider.
     *
     * @since    1.0.0
     * @param    string $provider Provider name
     * @return   string API key
     */
    private function get_api_key($provider) {
        return isset($this->settings['ai_api_keys'][$provider]) ? $this->settings['ai_api_keys'][$provider] : '';
    }

    /**
     * Test AI provider configuration.
     *
     * @since    1.0.0
     * @param    string $provider Provider name
     * @param    string $test_prompt Test prompt
     * @param    string $test_value Test value
     * @return   array Test result
     */
    public function test_provider($provider, $test_prompt = '', $test_value = 'test product') {
        try {
            if (empty($test_prompt)) {
                $test_prompt = 'Make this text more descriptive and engaging for e-commerce';
            }

            $result = $this->process_field($test_value, $test_prompt, array('provider' => $provider));

            return array(
                'success' => true,
                'result' => $result,
                'message' => sprintf(__('%s API connection successful.', 'wc-xml-csv-import'), ucfirst($provider))
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'result' => '',
                'message' => sprintf(__('%s API test failed: %s', 'wc-xml-csv-import'), ucfirst($provider), $e->getMessage())
            );
        }
    }

    /**
     * Get available AI providers.
     *
     * @since    1.0.0
     * @return   array Available providers
     */
    public function get_available_providers() {
        return array(
            'openai' => array(
                'name' => 'OpenAI GPT',
                'models' => array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo-preview'),
                'configured' => !empty($this->get_api_key('openai'))
            ),
            'gemini' => array(
                'name' => 'Google Gemini',
                'models' => array('gemini-pro', 'gemini-pro-vision'),
                'configured' => !empty($this->get_api_key('gemini'))
            ),
            'claude' => array(
                'name' => 'Anthropic Claude',
                'models' => array('claude-3-haiku-20240307', 'claude-3-sonnet-20240229', 'claude-3-opus-20240229'),
                'configured' => !empty($this->get_api_key('claude'))
            ),
            'grok' => array(
                'name' => 'xAI Grok',
                'models' => array('grok-1', 'grok-1.5'),
                'configured' => !empty($this->get_api_key('grok'))
            ),
            'copilot' => array(
                'name' => 'Microsoft Copilot',
                'models' => array('gpt-35-turbo', 'gpt-4', 'gpt-4-32k'),
                'configured' => !empty($this->get_api_key('copilot')) && !empty($this->settings['copilot_endpoint'])
            )
        );
    }

    /**
     * Merge sample data from multiple products to capture ALL unique fields and attributes.
     * This ensures AI sees all possible fields across all products.
     *
     * @since    1.0.0
     * @param    array  $products Array of product data arrays
     * @return   array  Merged sample data with all unique fields
     */
    private function merge_sample_products($products) {
        $merged = array();
        
        foreach ($products as $product) {
            if (!is_array($product)) {
                continue;
            }
            
            foreach ($product as $key => $value) {
                // Handle variations specially - collect ALL unique variation fields and attributes
                if ($key === 'variations' && is_array($value)) {
                    if (!isset($merged['variations'])) {
                        $merged['variations'] = array('variation' => array(array()));
                    }
                    $this->deep_merge_variations($value, $merged['variations']);
                }
                // For attributes at root level - collect all unique
                elseif ($key === 'attributes' && is_array($value)) {
                    if (!isset($merged['attributes'])) {
                        $merged['attributes'] = array();
                    }
                    foreach ($value as $attr_key => $attr_val) {
                        if (!isset($merged['attributes'][$attr_key])) {
                            $merged['attributes'][$attr_key] = $attr_val;
                        }
                    }
                }
                // For regular fields - add if not exists yet
                elseif (!isset($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }
        
        return $merged;
    }
    
    /**
     * Deep merge variation data - adds ALL unique fields from all variations.
     *
     * @param array $source Source variations to merge from
     * @param array &$target Target merged variations (by reference)
     */
    private function deep_merge_variations($source, &$target) {
        // Get variations array
        $variations = isset($source['variation']) ? $source['variation'] : $source;
        if (!is_array($variations)) {
            return;
        }
        
        // Normalize to indexed array
        $var_list = isset($variations[0]) ? $variations : array($variations);
        
        // Ensure target structure exists
        if (!isset($target['variation']) || !is_array($target['variation'])) {
            $target['variation'] = array(array());
        }
        if (!isset($target['variation'][0]) || !is_array($target['variation'][0])) {
            $target['variation'][0] = array();
        }
        
        foreach ($var_list as $variation) {
            if (!is_array($variation)) {
                continue;
            }
            
            foreach ($variation as $var_key => $var_val) {
                // Handle nested attributes
                if ($var_key === 'attributes' && is_array($var_val)) {
                    if (!isset($target['variation'][0]['attributes'])) {
                        $target['variation'][0]['attributes'] = array();
                    }
                    foreach ($var_val as $attr_key => $attr_val) {
                        // ADD all unique attributes
                        if (!isset($target['variation'][0]['attributes'][$attr_key])) {
                            $target['variation'][0]['attributes'][$attr_key] = $attr_val;
                        }
                    }
                }
                // ADD all unique fields - this is the key fix!
                elseif (!isset($target['variation'][0][$var_key])) {
                    $target['variation'][0][$var_key] = $var_val;
                }
            }
        }
    }

    /**
     * Merge variation data from multiple products/variations.
     * @deprecated Use deep_merge_variations instead
     *
     * @param array $new_variations New variations to merge
     * @param array $existing Existing merged variations
     * @return array Merged variations with all unique attributes
     */
    private function merge_variations($new_variations, $existing) {
        // Normalize to array
        if (!is_array($existing)) {
            $existing = array();
        }
        
        // Get the first variation structure as base
        $variations = isset($new_variations['variation']) ? $new_variations['variation'] : $new_variations;
        if (!is_array($variations)) {
            return $existing;
        }
        
        // If indexed array, iterate
        $var_list = isset($variations[0]) ? $variations : array($variations);
        
        foreach ($var_list as $variation) {
            if (!is_array($variation)) {
                continue;
            }
            
            // Merge this variation's fields and attributes into the first slot
            if (!isset($existing['variation']) || !is_array($existing['variation'])) {
                $existing['variation'] = array(array());
            }
            
            foreach ($variation as $var_key => $var_val) {
                // Merge attributes specially
                if ($var_key === 'attributes' && is_array($var_val)) {
                    if (!isset($existing['variation'][0]['attributes'])) {
                        $existing['variation'][0]['attributes'] = array();
                    }
                    foreach ($var_val as $attr_key => $attr_val) {
                        if (!isset($existing['variation'][0]['attributes'][$attr_key])) {
                            $existing['variation'][0]['attributes'][$attr_key] = $attr_val;
                        }
                    }
                }
                // Keep first example value for each field
                elseif (!isset($existing['variation'][0][$var_key]) || 
                        $existing['variation'][0][$var_key] === '' || 
                        $existing['variation'][0][$var_key] === null) {
                    $existing['variation'][0][$var_key] = $var_val;
                }
            }
        }
        
        return $existing;
    }

    /**
     * Auto-map source fields to WooCommerce fields using AI.
     * ADVANCED tier only feature.
     *
     * @since    1.0.0
     * @param    array  $source_fields Array of field names from file
     * @param    string $provider AI provider to use
     * @param    string $file_type 'xml' or 'csv'
     * @param    array  $sample_data Optional sample data for context
     * @return   array  Mapping array: source_field => wc_field
     */
    public function auto_map_fields($source_fields, $provider = 'openai', $file_type = 'xml', $sample_data = array()) {
        
        // WooCommerce target fields that can be mapped
        $wc_fields = array(
            'id' => 'Product ID - WordPress post ID for updating existing products',
            'sku' => 'Product SKU/Code - unique identifier',
            'slug' => 'Product URL slug/permalink',
            'name' => 'Product name/title',
            'description' => 'Full product description (HTML allowed)',
            'short_description' => 'Short product description/summary',
            'regular_price' => 'Regular/normal price (number)',
            'sale_price' => 'Discounted/sale price (number)',
            'sale_price_dates_from' => 'Sale start date (YYYY-MM-DD format)',
            'sale_price_dates_to' => 'Sale end date (YYYY-MM-DD format)',
            'stock_quantity' => 'Stock/inventory quantity (integer)',
            'stock_status' => 'Stock status: instock, outofstock, onbackorder',
            'manage_stock' => 'Whether to manage stock: yes/no, true/false, 1/0',
            'backorders' => 'Allow backorders: no, notify, yes',
            'sold_individually' => 'Sold individually (limit 1 per order): yes/no',
            'weight' => 'Product weight (number)',
            'length' => 'Product length (number)',
            'width' => 'Product width (number)', 
            'height' => 'Product height (number)',
            'categories' => 'Product categories (comma separated or hierarchy with >)',
            'tags' => 'Product tags (comma separated)',
            'images' => 'Product images - URLs separated by comma or |',
            'featured_image' => 'Main/featured image URL',
            'ean' => 'EAN barcode (13 digits)',
            'gtin' => 'GTIN code',
            'upc' => 'UPC code (12 digits)',
            'isbn' => 'ISBN for books',
            'mpn' => 'Manufacturer Part Number',
            'brand' => 'Product brand/manufacturer name',
            'tax_status' => 'Tax status: taxable, shipping, none',
            'tax_class' => 'Tax class slug',
            'shipping_class' => 'Shipping class slug',
            'status' => 'Product status: publish, draft, pending, private',
            'featured' => 'Featured product: yes/no, true/false, 1/0',
            'virtual' => 'Virtual product (no shipping): yes/no',
            'downloadable' => 'Downloadable product: yes/no',
            'download_limit' => 'Download limit per customer (-1 for unlimited)',
            'download_expiry' => 'Download expiry days (-1 for never)',
            'downloadable_files' => 'Downloadable files (URLs separated by | or in nested structure)',
            'meta_title' => 'SEO meta title',
            'meta_description' => 'SEO meta description',
            'purchase_note' => 'Note shown after purchase',
            'menu_order' => 'Menu order (integer)',
            'external_url' => 'External/affiliate product URL',
            'button_text' => 'Add to cart button text',
            'upsell_ids' => 'Upsell product SKUs (comma separated)',
            'cross_sell_ids' => 'Cross-sell product SKUs (comma separated)',
            'reviews_allowed' => 'Allow reviews: yes/no, true/false, 1/0',
            'average_rating' => 'Average product rating (0-5)',
            'rating_count' => 'Number of ratings/reviews (integer)',
            'type' => 'Product type: simple, variable, grouped, external',
            'grouped_products' => 'Grouped product child SKUs (comma separated)',
            // Variable product specific fields - ALL variation fields
            'variations' => 'Container for product variations (variable products)',
            'variation_parent_sku' => 'Parent product SKU to link variation to',
            'variation_sku' => 'Variation SKU (unique per variation)',
            'variation_price' => 'Variation regular price',
            'variation_regular_price' => 'Variation regular price (alias)',
            'variation_sale_price' => 'Variation sale price',
            'variation_stock' => 'Variation stock quantity',
            'variation_stock_quantity' => 'Variation stock quantity (alias)',
            'variation_stock_status' => 'Variation stock status: instock, outofstock, onbackorder',
            'variation_manage_stock' => 'Variation manage stock: yes/no',
            'variation_weight' => 'Variation weight',
            'variation_length' => 'Variation length dimension',
            'variation_width' => 'Variation width dimension',
            'variation_height' => 'Variation height dimension',
            'variation_image' => 'Variation specific image URL',
            'variation_description' => 'Variation description text',
            'variation_gtin' => 'Variation GTIN/EAN/UPC barcode',
            'variation_backorders' => 'Variation backorders: yes, no, notify',
            'variation_virtual' => 'Variation is virtual (no shipping): yes/no',
            'variation_downloadable' => 'Variation is downloadable: yes/no',
            'variation_download_limit' => 'Variation download limit (-1 unlimited)',
            'variation_download_expiry' => 'Variation download expiry days (-1 never)',
            'variation_low_stock_amount' => 'Variation low stock threshold',
            // Attributes (will be auto-detected)
            'attributes' => 'Product attributes container (size, color, etc.)',
            'attribute:size' => 'Size attribute (S, M, L, XL, etc.)',
            'attribute:color' => 'Color attribute (Red, Blue, Green, etc.)',
            'attribute:material' => 'Material attribute (Cotton, Leather, etc.)',
        );
        
        // Count source fields for the prompt
        $source_field_count = count($source_fields);
        
        // Build the prompt
        $prompt = "You are an expert at mapping product data fields for WooCommerce e-commerce import.
You specialize in recognizing VARIABLE PRODUCTS with VARIATIONS and ATTRIBUTES.

=== MANDATORY REQUIREMENT ===
YOU MUST MAP EVERY SINGLE FIELD! There are exactly {$source_field_count} source fields.
Your response MUST contain exactly {$source_field_count} mappings in the 'mappings' object.
DO NOT SKIP ANY FIELD! If you cannot determine the mapping, map it to the closest match or use a custom field.

I have a {$file_type} file with these {$source_field_count} column/field names from the source file:
" . json_encode($source_fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

I need to map them to these WooCommerce product fields:
" . json_encode($wc_fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Add sample data context if available - now supports multiple products
        if (!empty($sample_data)) {
            // Check if we have multiple products (array of products)
            if (is_array($sample_data) && isset($sample_data[0]) && is_array($sample_data[0])) {
                // Multiple products - merge all unique fields and values
                $merged_sample = $this->merge_sample_products($sample_data);
                $product_count = count($sample_data);
                $prompt .= "\n\nI analyzed {$product_count} products and found these fields with sample values (merged from all products to show ALL possible fields and attributes):\n" . json_encode($merged_sample, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $prompt .= "\n\nIMPORTANT: Different products may have different attributes. Map ALL attributes you see from any product.";
            } else {
                // Single product (backwards compatibility)
                $prompt .= "\n\nHere are sample values from the first product to help identify field types:\n" . json_encode($sample_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        $prompt .= "

Analyze each source field name and its sample value (if provided) to determine which WooCommerce field it should map to.

=== VARIABLE PRODUCTS & VARIATIONS ===
Look for these patterns that indicate VARIABLE products with VARIATIONS:
- Fields containing: variations, variants, options, sizes, colors, combinations, offers
- Nested structures like: variations/variation, variants/variant, offers/offer
- XML paths like: /good/offers/offer, /product/variations/variation
- Fields with multiple values: sizes, colors, materials (comma-separated or pipe-separated)

When you detect variation structures:
- Map the container to 'variations' field
- Look for variation-specific fields: sku, price, stock inside variations
- Identify ATTRIBUTES inside variations (size, color, material, etc.)

=== VARIATION FIELD MAPPING (CRITICAL!) ===
For EACH field found inside variations, map them with 'variation_' prefix:
- sku inside variation → variation_sku
- price, regular_price inside variation → variation_price  
- sale_price inside variation → variation_sale_price
- stock, stock_quantity, quantity inside variation → variation_stock
- stock_status inside variation → variation_stock_status
- manage_stock inside variation → variation_manage_stock
- weight inside variation → variation_weight
- length inside variation → variation_length
- width inside variation → variation_width
- height inside variation → variation_height
- image, images inside variation → variation_image
- description inside variation → variation_description
- parent_sku → variation_parent_sku (to link variation to parent)
- gtin, ean, upc, barcode inside variation → variation_gtin
- backorders inside variation → variation_backorders
- low_stock_amount, low_stock inside variation → variation_low_stock_amount
- virtual, is_virtual inside variation → variation_virtual
- downloadable, is_downloadable inside variation → variation_downloadable
- download_limit inside variation → variation_download_limit
- download_expiry, expiry_days inside variation → variation_download_expiry

YOU MUST MAP ALL OF THESE IF THEY EXIST IN THE SOURCE FILE - CHECK EVERY FIELD:
1. variation_parent_sku (if parent_sku exists in variation)
2. variation_sku (if sku exists in variation)
3. variation_price OR variation_regular_price (if price/regular_price exists)
4. variation_sale_price (if sale_price exists)
5. variation_stock OR variation_stock_quantity (if stock/stock_quantity/quantity exists)
6. variation_stock_status (if stock_status exists)
7. variation_manage_stock (if manage_stock exists)
8. variation_weight (if weight exists)
9. variation_image (if image exists)
10. variation_backorders (if backorders exists) - IMPORTANT!
11. variation_low_stock_amount (if low_stock_amount exists) - IMPORTANT!
12. variation_length, variation_width, variation_height (if dimensions exist)
13. variation_virtual (if virtual exists) - IMPORTANT!
14. variation_downloadable (if downloadable exists) - IMPORTANT!
15. variation_download_limit (if download_limit exists) - IMPORTANT!
16. variation_download_expiry (if download_expiry exists) - IMPORTANT!
17. variation_description (if description exists)
11. variation_low_stock_amount (if low_stock_amount exists) - IMPORTANT!
12. variation_length, variation_width, variation_height (if dimensions exist)

=== ATTRIBUTE MAPPING (CRITICAL!) ===
When you find attribute fields inside variations (size, color, etc.):
- Map them as 'attribute:ATTRIBUTE_NAME'
- Example: variations.variation.attributes.size → attribute:size
- Example: variations.variation.attributes.color → attribute:color
- Example: variations.variation.color → attribute:color  
- Example: offer.param[name=Размер] → attribute:size
- ALL attributes found in variations MUST be mapped!

=== ATTRIBUTE DETECTION ===
Attributes are properties used for product variations. Look for:
- Fields named: size, color, colour, material, style, format, capacity, volume
- Latvian: izmērs, krāsa, materiāls, formāts
- German: größe, farbe, material
- Russian: размер, цвет, материал
- Fields inside variation containers with values like: S, M, L, XL, Red, Blue, 42, 44

Map attributes as 'attribute:ATTRIBUTE_NAME' format, e.g.:
- size, sizes, izmērs → attribute:size
- color, colour, krāsa → attribute:color
- material, materiāls → attribute:material

=== DOWNLOADABLE & VIRTUAL PRODUCTS ===
Look for:
- virtual, is_virtual, digital → virtual
- downloadable, is_downloadable → downloadable
- download_url, file_url, download_link → downloadable_files
- download_limit, max_downloads → download_limit
- download_expiry, expiry_days → download_expiry

=== MULTI-LANGUAGE FIELD PATTERNS ===
Consider multiple languages when matching field names:
- Latvian: Artikuls, Nosaukums, Cena, Daudzums, Apraksts, Kategorija, Attēls, Svars, Izmērs, Krāsa
- English: SKU, Name, Title, Price, Stock, Quantity, Description, Category, Image, Weight, Size, Color
- German: Artikelnummer, Bezeichnung, Preis, Lager, Bestand, Beschreibung, Kategorie, Bild, Größe, Farbe
- Russian: Артикул, Название, Цена, Количество, Описание, Категория, Изображение, Размер, Цвет

=== COMMON FIELD PATTERNS ===
- Contains 'sku', 'code', 'article', 'artikul', 'product_code' → sku
- Contains 'id', 'product_id', 'woo_id' (numeric ID) → id
- Contains 'slug', 'url_key', 'permalink', 'handle' → slug
- Contains 'name', 'title', 'nosaukums', 'bezeichnung', 'название' → name
- Contains 'price', 'cena', 'preis', 'цена', 'regular' → regular_price
- Contains 'sale', 'discount', 'atlaide' → sale_price
- Contains 'stock', 'qty', 'quantity', 'daudzums', 'bestand', 'количество', 'inventory' → stock_quantity
- Contains 'description', 'apraksts', 'beschreibung', 'описание', 'desc' → description
- Contains 'short_desc', 'summary', 'īss_apraksts' → short_description
- Contains 'category', 'kategorija', 'kategorie', 'категория', 'cat' → categories
- Contains 'tag', 'birka', 'тег' → tags
- Contains 'image', 'picture', 'photo', 'attēls', 'bild', 'изображение', 'img' → images
- Contains 'brand', 'manufacturer', 'ražotājs', 'hersteller', 'производитель', 'zīmols' → brand
- Contains 'weight', 'svars', 'gewicht', 'вес' → weight
- Contains 'type', 'product_type' → type
- Contains 'variations', 'variants', 'offers' → variations

=== SPECIAL XML STRUCTURE PATTERNS ===
For XML files, recognize these common structures:
1. Mobilux format: <xml><good>...<offers><offer>...</offer></offers></good></xml>
2. Standard: <products><product>...<variations><variation>...</variation></variations></product></products>
3. WooCommerce export: similar to CSV with flat structure
4. Prestashop: <product><combinations>...</combinations></product>

Return ONLY a valid JSON object with this exact structure - no explanations, no markdown, just the JSON:
{
  \"mappings\": {
    \"parent_sku\": \"sku\",
    \"name\": \"name\",
    \"description\": \"description\",
    \"short_description\": \"short_description\",
    \"regular_price\": \"regular_price\",
    \"sale_price\": \"sale_price\",
    \"categories\": \"categories\",
    \"tags\": \"tags\",
    \"images.image\": \"images\",
    \"brand\": \"brand\",
    \"weight\": \"weight\",
    \"variations.variation.parent_sku\": \"variation_parent_sku\",
    \"variations.variation.sku\": \"variation_sku\",
    \"variations.variation.regular_price\": \"variation_price\",
    \"variations.variation.sale_price\": \"variation_sale_price\",
    \"variations.variation.stock_quantity\": \"variation_stock\",
    \"variations.variation.stock_status\": \"variation_stock_status\",
    \"variations.variation.manage_stock\": \"variation_manage_stock\",
    \"variations.variation.weight\": \"variation_weight\",
    \"variations.variation.image\": \"variation_image\",
    \"variations.variation.description\": \"variation_description\",
    \"variations.variation.backorders\": \"variation_backorders\",
    \"variations.variation.low_stock_amount\": \"variation_low_stock_amount\",
    \"variations.variation.virtual\": \"variation_virtual\",
    \"variations.variation.downloadable\": \"variation_downloadable\",
    \"variations.variation.download_limit\": \"variation_download_limit\",
    \"variations.variation.download_expiry\": \"variation_download_expiry\",
    \"variations.variation.attributes.size\": \"attribute:size\",
    \"variations.variation.attributes.color\": \"attribute:color\"
  },
  \"confidence\": {
    \"parent_sku\": 95,
    \"name\": 98
  },
  \"unmapped\": [],
  \"product_structure\": {
    \"type\": \"variable\",
    \"has_variations\": true,
    \"variation_path\": \"variations.variation\",
    \"detected_attributes\": [\"size\", \"color\"],
    \"variation_fields\": [\"parent_sku\", \"sku\", \"regular_price\", \"sale_price\", \"stock_quantity\", \"stock_status\", \"manage_stock\", \"weight\", \"image\", \"backorders\", \"low_stock_amount\", \"virtual\", \"downloadable\", \"download_limit\", \"download_expiry\", \"description\"]
  }
}

=== ABSOLUTE REQUIREMENTS - FOLLOW EXACTLY ===

1. MAP EVERY SINGLE SOURCE FIELD! Count: {$source_field_count} fields. Your mappings object MUST have {$source_field_count} entries!

2. For fields inside 'variations.variation.*' - use these EXACT mappings:
   - .virtual → variation_virtual
   - .downloadable → variation_downloadable
   - .download_limit → variation_download_limit
   - .download_expiry → variation_download_expiry
   - .sale_price → variation_sale_price
   - .regular_price → variation_price
   - .price → variation_price
   - .sku → variation_sku
   - .parent_sku → variation_parent_sku
   - .stock_quantity → variation_stock
   - .stock_status → variation_stock_status
   - .manage_stock → variation_manage_stock
   - .backorders → variation_backorders
   - .low_stock_amount → variation_low_stock_amount
   - .weight → variation_weight
   - .image → variation_image
   - .description → variation_description
   - .gtin or .ean or .upc → variation_gtin

3. For fields inside 'variations.variation[0].attributes.*' - use 'attribute:FIELD_NAME' format
   Example: variations.variation[0].attributes.license_type → attribute:license_type

4. 'unmapped' array should be EMPTY if you mapped everything correctly!

5. DO NOT SKIP virtual, downloadable, download_limit, download_expiry - these are CRITICAL!

6. Set confidence to at least 60 for all mapped fields";

        try {
            $result = $this->process_field('', $prompt, array(
                'provider' => $provider,
                'temperature' => 0.1,  // Low temperature for consistent results
                'max_tokens' => 4000  // Increased for more fields
            ));
            
            // Parse JSON response - try to extract JSON from response
            $json_result = $result;
            
            // Log original response for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('AI Auto-Map Raw Response: ' . substr($result, 0, 1000));
            }
            
            // Clean the result - remove markdown code blocks if present
            $cleaned_result = $result;
            $cleaned_result = preg_replace('/^```json\s*/i', '', $cleaned_result);
            $cleaned_result = preg_replace('/^```\s*/i', '', $cleaned_result);
            $cleaned_result = preg_replace('/\s*```$/i', '', $cleaned_result);
            $cleaned_result = trim($cleaned_result);
            
            // Try to find JSON object in response (in case AI added extra text)
            if (preg_match('/\{[\s\S]*"mappings"\s*:\s*\{[\s\S]*\}[\s\S]*\}/m', $cleaned_result, $matches)) {
                $json_result = $matches[0];
            } else {
                $json_result = $cleaned_result;
            }
            
            $parsed = json_decode($json_result, true);
            $json_error = json_last_error();
            
            if (!is_array($parsed) || !isset($parsed['mappings'])) {
                // Fallback: try simpler JSON structure
                $simple_parsed = json_decode($cleaned_result, true);
                
                if (is_array($simple_parsed) && count($simple_parsed) > 0) {
                    // Check if it's already a mappings object
                    if (isset($simple_parsed['mappings'])) {
                        $parsed = $simple_parsed;
                    } else {
                        // AI returned simple key-value mappings
                        $parsed = array(
                            'mappings' => $simple_parsed,
                            'confidence' => array(),
                            'unmapped' => array(),
                            'product_structure' => array()
                        );
                    }
                } else {
                    // Log detailed error
                    $error_msg = 'JSON parse error: ' . json_last_error_msg();
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('AI Auto-Map Parse Error: ' . $error_msg);
                        error_log('AI Auto-Map Cleaned Result: ' . substr($cleaned_result, 0, 500));
                    }
                    throw new Exception('Failed to parse AI response. ' . $error_msg . ' Response: ' . substr($cleaned_result, 0, 300));
                }
            }
            
            // Validate mappings - only keep valid WooCommerce fields (including attribute: prefix)
            $valid_mappings = array();
            $valid_confidence = array();
            $detected_attributes = array();
            
            foreach ($parsed['mappings'] as $source => $target) {
                // Check if source exists in our fields
                if (!in_array($source, $source_fields)) {
                    continue;
                }
                
                // Allow attribute: prefix for detected attributes
                if (strpos($target, 'attribute:') === 0) {
                    $attr_name = substr($target, 10); // Remove 'attribute:' prefix
                    $valid_mappings[$source] = $target;
                    $detected_attributes[] = $attr_name;
                    if (isset($parsed['confidence'][$source])) {
                        $valid_confidence[$source] = intval($parsed['confidence'][$source]);
                    }
                    continue;
                }
                
                // Check if target is valid WC field
                if (array_key_exists($target, $wc_fields)) {
                    $valid_mappings[$source] = $target;
                    if (isset($parsed['confidence'][$source])) {
                        $valid_confidence[$source] = intval($parsed['confidence'][$source]);
                    }
                }
            }
            
            // AUTO-FILL: Map any remaining unmapped variation fields based on path patterns
            $unmapped_fields = array_diff($source_fields, array_keys($valid_mappings));
            $auto_filled = array();
            
            // Variation field patterns - last part of path to WC field
            $variation_auto_map = array(
                'virtual' => 'variation_virtual',
                'is_virtual' => 'variation_virtual',
                'downloadable' => 'variation_downloadable',
                'is_downloadable' => 'variation_downloadable',
                'download_limit' => 'variation_download_limit',
                'download_expiry' => 'variation_download_expiry',
                'sale_price' => 'variation_sale_price',
                'regular_price' => 'variation_price',
                'price' => 'variation_price',
                'sku' => 'variation_sku',
                'parent_sku' => 'variation_parent_sku',
                'stock_quantity' => 'variation_stock',
                'stock' => 'variation_stock',
                'quantity' => 'variation_stock',
                'stock_status' => 'variation_stock_status',
                'manage_stock' => 'variation_manage_stock',
                'backorders' => 'variation_backorders',
                'low_stock_amount' => 'variation_low_stock_amount',
                'weight' => 'variation_weight',
                'length' => 'variation_length',
                'width' => 'variation_width',
                'height' => 'variation_height',
                'image' => 'variation_image',
                'description' => 'variation_description',
                'gtin' => 'variation_gtin',
                'ean' => 'variation_gtin',
                'upc' => 'variation_gtin',
                'barcode' => 'variation_gtin',
            );
            
            foreach ($unmapped_fields as $source) {
                // Check if this is a variation field (contains variation path)
                if (preg_match('/variation|variant|offer/i', $source)) {
                    // Check for attributes path
                    if (preg_match('/attributes?\.(\w+)$/i', $source, $attr_match)) {
                        // This is an attribute
                        $attr_name = strtolower($attr_match[1]);
                        $valid_mappings[$source] = 'attribute:' . $attr_name;
                        $valid_confidence[$source] = 85;
                        $detected_attributes[] = $attr_name;
                        $auto_filled[] = $source;
                        continue;
                    }
                    
                    // Get the last part of the path
                    $path_parts = preg_split('/[\.\[\]]+/', $source);
                    $last_part = strtolower(end($path_parts));
                    
                    // Check if we have an auto-mapping for this
                    if (isset($variation_auto_map[$last_part])) {
                        $valid_mappings[$source] = $variation_auto_map[$last_part];
                        $valid_confidence[$source] = 80;
                        $auto_filled[] = $source;
                    }
                }
            }
            
            // Extract product structure info
            $product_structure = isset($parsed['product_structure']) ? $parsed['product_structure'] : array();
            
            // Merge detected attributes from mappings and product_structure
            if (!empty($product_structure['detected_attributes'])) {
                $detected_attributes = array_unique(array_merge($detected_attributes, $product_structure['detected_attributes']));
            }
            $product_structure['detected_attributes'] = array_values($detected_attributes);
            
            // Calculate final unmapped count
            $final_unmapped = array_diff($source_fields, array_keys($valid_mappings));
            
            return array(
                'mappings' => $valid_mappings,
                'confidence' => $valid_confidence,
                'unmapped' => array_values($final_unmapped),
                'auto_filled' => $auto_filled,
                'provider' => $provider,
                'product_structure' => $product_structure,
                'stats' => array(
                    'total_fields' => count($source_fields),
                    'ai_mapped' => count($valid_mappings) - count($auto_filled),
                    'auto_filled' => count($auto_filled),
                    'unmapped' => count($final_unmapped)
                ),
                'success' => true
            );
            
        } catch (Exception $e) {
            throw new Exception('AI auto-mapping failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear response cache.
     *
     * @since    1.0.0
     */
    public static function clear_cache() {
        self::$response_cache = array();
    }

    /**
     * Get cache statistics.
     *
     * @since    1.0.0
     * @return   array Cache stats
     */
    public static function get_cache_stats() {
        return array(
            'cached_responses' => count(self::$response_cache),
            'memory_usage' => strlen(serialize(self::$response_cache))
        );
    }
}