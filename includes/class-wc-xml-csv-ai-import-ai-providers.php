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
        $key_name = $provider . '_api_key';
        
        // PRIORITY 1: New format from Settings page (most recent user input)
        $ai_settings = get_option('wc_xml_csv_ai_import_ai_settings', array());
        if (isset($ai_settings[$key_name]) && !empty($ai_settings[$key_name])) {
            return $ai_settings[$key_name];
        }
        
        // PRIORITY 2: Direct key in main settings
        if (isset($this->settings[$key_name]) && !empty($this->settings[$key_name])) {
            return $this->settings[$key_name];
        }
        
        // PRIORITY 3: Legacy format (old imports, will be deprecated)
        if (isset($this->settings['ai_api_keys'][$provider]) && !empty($this->settings['ai_api_keys'][$provider])) {
            return $this->settings['ai_api_keys'][$provider];
        }
        
        return '';
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
        
        // Build the prompt - focused on MAPPING EVERYTHING without skipping
        $prompt = "You are an expert at mapping product data fields for WooCommerce e-commerce import.

=== CRITICAL: MAP ALL {$source_field_count} FIELDS - NO EXCEPTIONS! ===
You MUST map EVERY SINGLE source field. Do NOT skip any field! This is mandatory.

SOURCE FIELDS ({$source_field_count} total):
" . json_encode($source_fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

WOOCOMMERCE TARGET FIELDS:
" . json_encode($wc_fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

=== MAPPING RULES (FOLLOW EXACTLY!) ===

BASIC FIELDS:
- id, product_id → id
- sku, code, article, artikul, product_code, vendor_code → sku
- name, title, product_name, nosaukums, short_name → name
- description, description_lv, description_en, description_ru, apraksts → description
- short_description, short_desc, short_specs → short_description
- price, regular_price, cena → regular_price
- sale_price, discount_price, atlaide → sale_price
- stock, quantity, stock_quantity, daudzums, qty → stock_quantity
- category, categories, kategorija, category_id → categories
- brand, manufacturer, razotajs, zīmols → brand

IMAGES (CRITICAL - map ALL image fields!):
- image → images
- image[0], image[1], image[2], etc. → images
- image[0].#text, image[1].#text → images (text content = URL)
- image[0].@attributes.count → SKIP (metadata, not useful)
- image.#text → images
- images, picture, photo, img → images
- ANY field with 'image' containing URL → images

SKIP THESE FIELDS (metadata, not product data):
- @attributes.count → skip
- ANY .@attributes.* → skip (XML metadata)
- amount_on_pallet → skip (warehouse data)
- package_dimensions (parent object) → skip (use children instead)

WEIGHT & DIMENSIONS (use child fields!):
- weight, svars, gross_weight, net_weight → weight
- package_dimensions.width → width
- package_dimensions.height → height  
- package_dimensions.length → length
- ANY field ending with '.width' → width
- ANY field ending with '.height' → height
- ANY field ending with '.length' → length

BARCODES:
- ean, eans, eans.ean, gtin, upc, barcode → gtin

DATASHEETS & DOCUMENTS (map to custom meta):
- dsheet_lv, dsheet_en, dsheet_ru, dsheet_lt, dsheet_et → meta:datasheet";

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
                $prompt .= "\n\nSample values from first product:\n" . json_encode($sample_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }

        $prompt .= "

=== FOR VARIABLE PRODUCTS (if variations/offers/variants exist) ===
Fields inside variations should be prefixed with 'variation_':
- variation.sku → variation_sku
- variation.price → variation_price
- variation.stock → variation_stock
- variation.size/color → attribute:size, attribute:color

=== OUTPUT FORMAT ===
Return ONLY valid JSON (no markdown, no explanation):
{
  \"mappings\": {
    \"source_field\": \"woocommerce_field\",
    ...MUST have exactly {$source_field_count} entries...
  },
  \"confidence\": {\"field\": 80, ...},
  \"unmapped\": [],
  \"product_structure\": {\"type\": \"simple\", \"has_variations\": false}
}

=== FINAL CHECK ===
Count your mappings - there MUST be exactly {$source_field_count} entries!
Every image field → images
Every weight field → weight
Every dimension field → width/height/length
NO FIELD LEFT UNMAPPED!";

        try {
            $result = $this->process_field('', $prompt, array(
                'provider' => $provider,
                'temperature' => 0.2,  // Slightly higher for better field matching
                'max_tokens' => 4096   // Maximum for gpt-3.5-turbo
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