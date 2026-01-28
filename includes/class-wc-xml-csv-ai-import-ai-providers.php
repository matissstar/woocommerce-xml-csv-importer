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

            // Cache the result
            if (isset($this->settings['enable_ai_caching']) && $this->settings['enable_ai_caching']) {
                self::$response_cache[$cache_key] = $result;
            }

            return $result;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('AI Processing Error: ' . $e->getMessage()); }
            // Return original value on error
            return $value;
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
            'timeout' => $this->settings['ai_request_timeout'] ?? 30
        ));

        if (is_wp_error($response)) {
            throw new Exception('OpenAI API request failed: ' . $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['error'])) {
            throw new Exception('OpenAI API error: ' . $response_body['error']['message']);
        }

        if (!isset($response_body['choices'][0]['message']['content'])) {
            throw new Exception('Invalid OpenAI API response format.');
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
            'virtual' => 'Virtual product: yes/no',
            'downloadable' => 'Downloadable product: yes/no',
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
        );
        
        // Build the prompt
        $prompt = "You are an expert at mapping product data fields for WooCommerce e-commerce import.

I have a {$file_type} file with these column/field names from the source file:
" . json_encode($source_fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

I need to map them to these WooCommerce product fields:
" . json_encode($wc_fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Add sample data context if available
        if (!empty($sample_data)) {
            $prompt .= "\n\nHere are sample values from the first product to help identify field types:\n" . json_encode($sample_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        $prompt .= "

Analyze each source field name and its sample value (if provided) to determine which WooCommerce field it should map to.

Consider multiple languages when matching field names:
- Latvian: Artikuls, Nosaukums, Cena, Daudzums, Apraksts, Kategorija, Attēls, Svars
- English: SKU, Name, Title, Price, Stock, Quantity, Description, Category, Image, Weight
- German: Artikelnummer, Bezeichnung, Preis, Lager, Bestand, Beschreibung, Kategorie, Bild
- Russian: Артикул, Название, Цена, Количество, Описание, Категория, Изображение

Common field patterns:
- Contains 'sku', 'code', 'article', 'artikul', 'product_code' → sku
- Contains 'id', 'product_id', 'woo_id' (numeric ID) → id
- Contains 'slug', 'url_key', 'permalink', 'handle' → slug
- Contains 'name', 'title', 'nosaukums', 'bezeichnung', 'название' → name
- Contains 'price', 'cena', 'preis', 'цена', 'regular' → regular_price
- Contains 'sale', 'discount', 'atlaide' → sale_price
- Contains 'sale_price_dates_from', 'sale_from', 'sale_start' → sale_price_dates_from
- Contains 'sale_price_dates_to', 'sale_to', 'sale_end' → sale_price_dates_to
- Contains 'stock', 'qty', 'quantity', 'daudzums', 'bestand', 'количество', 'inventory' → stock_quantity
- Contains 'backorder' → backorders
- Contains 'sold_individually', 'individual' → sold_individually
- Contains 'description', 'apraksts', 'beschreibung', 'описание', 'desc' → description
- Contains 'short_desc', 'summary', 'īss_apraksts' → short_description
- Contains 'category', 'kategorija', 'kategorie', 'категория', 'cat' → categories
- Contains 'tag', 'birka', 'тег' → tags
- Contains 'image', 'picture', 'photo', 'attēls', 'bild', 'изображение', 'img', 'url' (with image context) → images
- Contains 'brand', 'manufacturer', 'ražotājs', 'hersteller', 'производитель', 'zīmols' → brand
- Contains 'weight', 'svars', 'gewicht', 'вес' → weight
- Contains 'length', 'garums' → length
- Contains 'width', 'platums' → width
- Contains 'height', 'augstums' → height
- Contains 'ean', 'barcode', 'svītrkods' → ean
- Contains 'gtin' → gtin
- Contains 'upsell' → upsell_ids
- Contains 'cross_sell', 'crosssell' → cross_sell_ids
- Contains 'reviews_allowed', 'allow_reviews' → reviews_allowed
- Contains 'average_rating', 'rating' (numeric 0-5) → average_rating
- Contains 'rating_count', 'review_count' → rating_count
- Contains 'type', 'product_type' → type
- Contains 'grouped_products', 'grouped', 'children' → grouped_products

Return ONLY a valid JSON object with this exact structure - no explanations, no markdown, just the JSON:
{
  \"mappings\": {
    \"SourceFieldName1\": \"woocommerce_field\",
    \"SourceFieldName2\": \"woocommerce_field\"
  },
  \"confidence\": {
    \"SourceFieldName1\": 95,
    \"SourceFieldName2\": 80
  },
  \"unmapped\": [\"FieldThatCouldNotBeMatched\"]
}

Only include fields in mappings where you have at least 60% confidence.
The confidence should be a number from 0 to 100.";

        try {
            $result = $this->process_field('', $prompt, array(
                'provider' => $provider,
                'temperature' => 0.1,  // Low temperature for consistent results
                'max_tokens' => 3000
            ));
            
            // Parse JSON response - try to extract JSON from response
            $json_result = $result;
            
            // Try to find JSON object in response (in case AI added extra text)
            if (preg_match('/\{[\s\S]*"mappings"[\s\S]*\}/m', $result, $matches)) {
                $json_result = $matches[0];
            }
            
            $parsed = json_decode($json_result, true);
            
            if (!is_array($parsed) || !isset($parsed['mappings'])) {
                // Fallback: try simpler JSON structure
                $simple_parsed = json_decode($result, true);
                if (is_array($simple_parsed) && !isset($simple_parsed['mappings'])) {
                    // AI returned simple key-value mappings
                    $parsed = array(
                        'mappings' => $simple_parsed,
                        'confidence' => array(),
                        'unmapped' => array()
                    );
                } else {
                    throw new Exception('Failed to parse AI response. Response: ' . substr($result, 0, 500));
                }
            }
            
            // Validate mappings - only keep valid WooCommerce fields
            $valid_mappings = array();
            $valid_confidence = array();
            
            foreach ($parsed['mappings'] as $source => $target) {
                // Check if source exists in our fields and target is valid WC field
                if (in_array($source, $source_fields) && array_key_exists($target, $wc_fields)) {
                    $valid_mappings[$source] = $target;
                    if (isset($parsed['confidence'][$source])) {
                        $valid_confidence[$source] = intval($parsed['confidence'][$source]);
                    }
                }
            }
            
            return array(
                'mappings' => $valid_mappings,
                'confidence' => $valid_confidence,
                'unmapped' => $parsed['unmapped'] ?? array(),
                'provider' => $provider,
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