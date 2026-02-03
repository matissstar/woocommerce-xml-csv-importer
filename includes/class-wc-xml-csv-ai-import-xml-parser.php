<?php
/**
 * XML Parser for handling XML file imports.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

/**
 * XML Parser class.
 */
class WC_XML_CSV_AI_Import_XML_Parser {

    /**
     * Parse XML structure for field mapping interface.
     *
     * @since    1.0.0
     * @param    string $file_path Path to XML file
     * @param    string $product_wrapper XML element containing products
     * @param    int $page Current page for pagination
     * @param    int $per_page Items per page
     * @return   array Parsed structure and sample data
     */
    public function parse_structure($file_path, $product_wrapper, $page = 1, $per_page = 5, $scan_for_fields = 0) {
        try {
            // Set time and memory limits for large files
            set_time_limit(300); // 5 minutes
            ini_set('memory_limit', '512M');
            @ignore_user_abort(true); // Continue even if browser disconnects
            
            $structure = array();
            $all_fields = array(); // Collect ALL unique fields from scanned products
            $sample_data = array();

            // Check if file exists
            if (!file_exists($file_path)) {
                throw new Exception(__('XML file not found.', 'wc-xml-csv-import'));
            }

            // Use XMLReader for memory-efficient parsing
            $reader = new XMLReader();
            if (!$reader->open($file_path)) {
                throw new Exception(__('Unable to open XML file.', 'wc-xml-csv-import'));
            }

            $product_count = 0;
            $collected_samples = 0;
            $fields_scanned = 0;
            $start_index = ($page - 1) * $per_page;
            
            // Determine how many products to scan for fields (default: first 100)
            $scan_limit = $scan_for_fields > 0 ? $scan_for_fields : 100;

            // Find product wrapper elements - count ALL products
            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == $product_wrapper) {
                    $product_count++;
                    
                    // Scan first N products for unique field names
                    if ($fields_scanned < $scan_limit) {
                        $product_node = $reader->expand();
                        if ($product_node) {
                            $product_dom = new DOMDocument();
                            $imported_node = $product_dom->importNode($product_node, true);
                            $product_dom->appendChild($imported_node);
                            
                            $sample_product = $this->extract_structure_from_element($product_dom->documentElement);
                            
                            // Merge fields from this product into all_fields
                            $product_fields = $this->build_field_structure($sample_product);
                            foreach ($product_fields as $field) {
                                $field_path = $field['path'];
                                if (!isset($all_fields[$field_path])) {
                                    $all_fields[$field_path] = $field;
                                } elseif (empty($all_fields[$field_path]['sample']) && !empty($field['sample'])) {
                                    // Update sample if we have a value now but didn't before
                                    $all_fields[$field_path]['sample'] = $field['sample'];
                                }
                            }
                            
                            // Collect sample data for current page display
                            if ($product_count > $start_index && $collected_samples < $per_page) {
                                $sample_data[] = $sample_product;
                                $collected_samples++;
                            }
                            
                            $fields_scanned++;
                        }
                    } elseif ($product_count > $start_index && $collected_samples < $per_page) {
                        // After scan_limit, only collect sample data for current page
                        $product_node = $reader->expand();
                        if ($product_node) {
                            $product_dom = new DOMDocument();
                            $imported_node = $product_dom->importNode($product_node, true);
                            $product_dom->appendChild($imported_node);
                            
                            $sample_product = $this->extract_structure_from_element($product_dom->documentElement);
                            $sample_data[] = $sample_product;
                            $collected_samples++;
                            
                            // Also add any NEW fields found in this product
                            $product_fields = $this->build_field_structure($sample_product);
                            foreach ($product_fields as $field) {
                                $field_path = $field['path'];
                                if (!isset($all_fields[$field_path])) {
                                    $all_fields[$field_path] = $field;
                                }
                            }
                        }
                    }
                    
                    // Reset timeout every 1000 products to prevent timeout
                    if ($product_count % 1000 == 0) {
                        @set_time_limit(300);
                    }
                }
            }

            $reader->close();
            
            // Convert all_fields back to indexed array (preserve original XML order)
            $structure = array_values($all_fields);
            // Note: No sorting - keep fields in the order they appear in XML/CSV file

            return array(
                'structure' => $structure,
                'sample_data' => $sample_data,
                'total_products' => $product_count,
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($product_count / $per_page),
                'fields_scanned_from' => min($fields_scanned, $product_count) // How many products were scanned for fields
            );

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('XML Parser Error: ' . $e->getMessage()); }
            return array(
                'error' => $e->getMessage(),
                'structure' => array(),
                'sample_data' => array(),
                'total_products' => 0
            );
        }
    }

    /**
     * Extract products from XML file for import processing.
     *
     * @since    1.0.0
     * @param    string $file_path Path to XML file
     * @param    string $product_wrapper XML element containing products
     * @param    int $offset Starting position
     * @param    int $limit Number of products to extract
     * @return   array Array of product data
     */
    public function extract_products($file_path, $product_wrapper, $offset = 0, $limit = 50) {
        try {
            $products = array();
            
            if (!file_exists($file_path)) {
                throw new Exception(__('XML file not found.', 'wc-xml-csv-import'));
            }

            $reader = new XMLReader();
            if (!$reader->open($file_path)) {
                throw new Exception(__('Unable to open XML file.', 'wc-xml-csv-import'));
            }

            $product_count = 0;
            $extracted_count = 0;

            while ($reader->read() && $extracted_count < $limit) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == $product_wrapper) {
                    if ($product_count >= $offset) {
                        $product_xml = $reader->readOuterXML();
                        $product_dom = new DOMDocument();
                        $product_dom->loadXML($product_xml);
                        
                        $product_data = $this->extract_structure_from_element($product_dom->documentElement);
                        $products[] = $product_data;
                        $extracted_count++;
                    }
                    $product_count++;
                }
            }

            $reader->close();
            return $products;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('XML Extract Error: ' . $e->getMessage()); }
            return array();
        }
    }

    /**
     * Extract field value from product data using dot notation.
     *
     * @since    1.0.0
     * @param    array $product_data Product data array
     * @param    string $field_path Field path in dot notation (e.g., 'name', 'price.value')
     * @return   mixed Field value or null
     */
    public function extract_field_value($product_data, $field_path) {
        $keys = explode('.', $field_path);
        $value = $product_data;

        foreach ($keys as $key) {
            // Handle array index notation like "attribute[0]"
            if (preg_match('/^(.+)\[(\d+)\]$/', $key, $matches)) {
                $array_key = $matches[1];
                $index = (int) $matches[2];
                
                if (is_array($value) && isset($value[$array_key])) {
                    $arr = $value[$array_key];
                    // Normalize single item to array
                    if (!isset($arr[0]) && is_array($arr)) {
                        $arr = array($arr);
                    }
                    if (isset($arr[$index])) {
                        $value = $arr[$index];
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
            } elseif (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_array($value) && isset($value['@attributes'][$key])) {
                $value = $value['@attributes'][$key];
            } else {
                return null;
            }
        }

        // Handle array values
        if (is_array($value)) {
            if (isset($value['@content'])) {
                return $value['@content'];
            } elseif (count($value) == 1 && isset($value[0])) {
                return is_array($value[0]) && isset($value[0]['@content']) ? $value[0]['@content'] : $value[0];
            } else {
                return implode(', ', array_filter($value, 'is_scalar'));
            }
        }

        return $value;
    }

    /**
     * Count total products in XML file.
     *
     * @since    1.0.0
     * @param    string $file_path Path to XML file
     * @param    string $product_wrapper XML element containing products
     * @return   int Total product count
     */
    public function count_products($file_path, $product_wrapper) {
        try {
            // Debug info
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - XML Parser count_products called with: ' . $file_path . ', wrapper: ' . $product_wrapper); }
            if (defined('WP_DEBUG') && WP_DEBUG) { 
                file_put_contents('/tmp/xml_debug.log', 
                    "=== XML PARSER DEBUG ===\n" . 
                    "File: " . $file_path . "\n" .
                    "Wrapper: " . $product_wrapper . "\n" .
                    "File exists: " . (file_exists($file_path) ? 'YES' : 'NO') . "\n",
                    FILE_APPEND
                ); 
            }
            
            if (!file_exists($file_path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - XML file does not exist: ' . $file_path); }
                return 0;
            }

            $reader = new XMLReader();
            if (!$reader->open($file_path)) {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Failed to open XML file: ' . $file_path); }
                return 0;
            }

            $count = 0;
            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == $product_wrapper) {
                    $count++;
                    if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents('/tmp/xml_debug.log', "Found element: " . $reader->localName . "\n", FILE_APPEND); }
                }
            }

            $reader->close();
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('WC XML CSV AI Import - Found ' . $count . ' products in XML'); }
            if (defined('WP_DEBUG') && WP_DEBUG) { file_put_contents('/tmp/xml_debug.log', "Final count: " . $count . "\n", FILE_APPEND); }
            return $count;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('XML Count Error: ' . $e->getMessage()); }
            return 0;
        }
    }

    /**
     * Extract structure from XML element recursively.
     *
     * @since    1.0.0
     * @param    DOMElement $element XML element
     * @return   array Structured array
     */
    private function extract_structure_from_element($element) {
        $result = array();

        // Add attributes
        if ($element->hasAttributes()) {
            $attributes = array();
            foreach ($element->attributes as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue;
            }
            if (!empty($attributes)) {
                $result['@attributes'] = $attributes;
            }
        }

        // Process child elements
        $children = array();
        $has_element_children = false;
        
        foreach ($element->childNodes as $child) {
            if ($child->nodeType == XML_ELEMENT_NODE) {
                $has_element_children = true;
                $child_data = $this->extract_structure_from_element($child);
                
                if (isset($children[$child->nodeName])) {
                    // Multiple elements with same name - convert to array
                    if (!is_array($children[$child->nodeName]) || !isset($children[$child->nodeName][0])) {
                        $children[$child->nodeName] = array($children[$child->nodeName]);
                    }
                    $children[$child->nodeName][] = $child_data;
                } else {
                    $children[$child->nodeName] = $child_data;
                }
            } elseif ($child->nodeType == XML_TEXT_NODE || $child->nodeType == XML_CDATA_SECTION_NODE) {
                $text = trim($child->nodeValue);
                if (!empty($text)) {
                    if (empty($children) && !$has_element_children) {
                        // Element has only text content
                        // BUT if it has attributes, we need to keep them!
                        if (isset($result['@attributes'])) {
                            $result['#text'] = $text;
                        } else {
                            return $text;
                        }
                    } else {
                        // Element has both text and child elements
                        $result['@content'] = $text;
                    }
                }
            }
        }

        // If element has no children and no text, return empty string (not empty array)
        // This ensures empty elements like <price /> are still recognized as fields
        if (empty($children) && empty($result)) {
            return '';
        }

        return empty($children) ? $result : array_merge($result, $children);
    }

    /**
     * Check if array is a simple text element with attributes.
     * XML like <image count="1">https://url</image> becomes:
     * ['@attributes' => ['count' => 1], '#text' => 'https://url']
     * This should be treated as a simple text field, not a complex object.
     *
     * @param array $arr Array to check
     * @return bool|string False if not simple text, or the text value if it is
     */
    private function is_simple_text_element($arr) {
        if (!is_array($arr)) {
            return false;
        }
        
        // Check if array only contains @attributes and/or #text/@content
        $keys = array_keys($arr);
        $allowed_keys = ['@attributes', '#text', '@content', '0'];
        
        foreach ($keys as $key) {
            if (!in_array($key, $allowed_keys, true)) {
                return false;
            }
        }
        
        // Must have text content
        if (isset($arr['#text'])) {
            return $arr['#text'];
        }
        if (isset($arr['@content'])) {
            return $arr['@content'];
        }
        if (isset($arr[0]) && is_string($arr[0])) {
            return $arr[0];
        }
        
        return false;
    }

    /**
     * Merge nested structures to collect all unique fields.
     * Used when scanning multiple variations/items to find ALL possible fields.
     *
     * @param array $existing Existing structure
     * @param array $new New structure to merge
     * @return array Merged structure with all unique fields
     */
    private function merge_nested_structures($existing, $new) {
        if (!is_array($existing)) {
            return $new;
        }
        if (!is_array($new)) {
            return $existing;
        }
        
        foreach ($new as $key => $value) {
            if (!isset($existing[$key])) {
                $existing[$key] = $value;
            } elseif (is_array($value) && is_array($existing[$key])) {
                $existing[$key] = $this->merge_nested_structures($existing[$key], $value);
            }
        }
        
        return $existing;
    }

    /**
     * Build field structure for mapping interface.
     *
     * @since    1.0.0
     * @param    array $data Product data array
     * @param    string $prefix Field path prefix
     * @return   array Field structure
     */
    /**
     * Build field structure from parsed data.
     * - Expands simple nested objects (like package_dimensions with width/height/length)
     * - Does NOT expand complex arrays (like specifications with name/value pairs)
     *
     * @param array $data Parsed XML data
     * @param string $prefix Path prefix for nested fields
     * @return array Field structure
     */
    private function build_field_structure($data, $prefix = '') {
        $structure = array();

        // If data is not an array, it's a simple text value - return empty structure
        if (!is_array($data)) {
            return $structure;
        }

        foreach ($data as $key => $value) {
            $field_path = empty($prefix) ? $key : $prefix . '.' . $key;
            
            // Skip @attributes and #text/@content keys
            if ($key === '@attributes' || $key === '#text' || $key === '@content') {
                continue;
            }
            
            if (is_array($value)) {
                // Check if this is a simple text element with attributes
                $simple_text = $this->is_simple_text_element($value);
                if ($simple_text !== false) {
                    $structure[] = array(
                        'path' => $field_path,
                        'label' => $field_path,
                        'type' => 'text',
                        'sample' => is_string($simple_text) ? substr($simple_text, 0, 50) : (string)$simple_text
                    );
                } elseif (isset($value[0])) {
                    // ARRAY of elements - check if simple or complex
                    $all_simple = true;
                    $sample_values = array();
                    foreach ($value as $i => $item) {
                        if (!is_numeric($i)) {
                            $all_simple = false;
                            break;
                        }
                        $item_text = is_string($item) ? $item : $this->is_simple_text_element($item);
                        if ($item_text === false && is_array($item)) {
                            $all_simple = false;
                            break;
                        }
                        if ($item_text && count($sample_values) < 2) {
                            $sample_values[] = substr($item_text, 0, 30);
                        }
                    }
                    
                    if ($all_simple) {
                        // Array of simple text values (like multiple images)
                        $structure[] = array(
                            'path' => $field_path,
                            'label' => $field_path . ' (' . count($value) . ' items)',
                            'type' => 'array',
                            'sample' => implode(', ', $sample_values) . (count($value) > 2 ? '...' : '')
                        );
                    } else {
                        // Complex array (like attributes, variations) - add parent and extract child field names
                        $structure[] = array(
                            'path' => $field_path,
                            'label' => $field_path . ' (' . count($value) . ' items)',
                            'type' => 'array',
                            'sample' => count($value) . ' nested items'
                        );
                        
                        // For complex arrays, extract ALL unique fields from ALL items
                        // This handles structures like: [0 => {sku, desc, attrs}, 1 => {sku, desc, attrs, sale_price}]
                        $all_sub_fields = array();
                        $all_nested_structures = array();
                        
                        // Scan up to 20 items to find all unique fields
                        $scan_items = array_slice($value, 0, 20);
                        foreach ($scan_items as $item_index => $item) {
                            if (!is_array($item)) continue;
                            
                            foreach ($item as $sub_key => $sub_value) {
                                if ($sub_key === '@attributes' || $sub_key === '#text' || is_numeric($sub_key)) {
                                    continue;
                                }
                                
                                // Check if sub_value is simple text
                                if (is_string($sub_value)) {
                                    if (!isset($all_sub_fields[$sub_key])) {
                                        $all_sub_fields[$sub_key] = array(
                                            'type' => 'text',
                                            'sample' => substr($sub_value, 0, 50)
                                        );
                                    }
                                } elseif (is_array($sub_value)) {
                                    $simple_text = $this->is_simple_text_element($sub_value);
                                    if ($simple_text !== false) {
                                        if (!isset($all_sub_fields[$sub_key])) {
                                            $all_sub_fields[$sub_key] = array(
                                                'type' => 'text',
                                                'sample' => substr($simple_text, 0, 50)
                                            );
                                        }
                                    } else {
                                        // Nested structure - store for later processing
                                        if (!isset($all_nested_structures[$sub_key])) {
                                            $all_nested_structures[$sub_key] = $sub_value;
                                        } else {
                                            // Merge nested structures to find all unique nested fields
                                            $all_nested_structures[$sub_key] = $this->merge_nested_structures(
                                                $all_nested_structures[$sub_key], 
                                                $sub_value
                                            );
                                        }
                                    }
                                } else {
                                    if (!isset($all_sub_fields[$sub_key])) {
                                        $all_sub_fields[$sub_key] = array(
                                            'type' => 'text',
                                            'sample' => (string)$sub_value
                                        );
                                    }
                                }
                            }
                        }
                        
                        // Add all simple sub-fields as template paths (no indices)
                        foreach ($all_sub_fields as $sub_key => $field_info) {
                            $sub_path = $field_path . '.' . $sub_key;
                            $structure[] = array(
                                'path' => $sub_path,
                                'label' => $sub_path,
                                'type' => $field_info['type'],
                                'sample' => $field_info['sample']
                            );
                        }
                        
                        // Process nested structures (like attributes inside variations)
                        foreach ($all_nested_structures as $nested_key => $nested_value) {
                            $nested_path = $field_path . '.' . $nested_key;
                            $nested_structure = $this->build_field_structure(array($nested_key => $nested_value), $field_path);
                            $structure = array_merge($structure, $nested_structure);
                        }
                    }
                } else {
                    // OBJECT (not array) - check if it's a simple object we should expand
                    $meaningful_keys = array_filter(array_keys($value), function($k) {
                        return $k !== '@attributes' && $k !== '#text' && $k !== '@content';
                    });
                    
                    if (!empty($meaningful_keys)) {
                        // Check if all children are simple values (not nested objects/arrays)
                        $all_children_simple = true;
                        foreach ($meaningful_keys as $child_key) {
                            $child_val = $value[$child_key];
                            if (is_array($child_val)) {
                                $child_text = $this->is_simple_text_element($child_val);
                                if ($child_text === false) {
                                    $all_children_simple = false;
                                    break;
                                }
                            }
                        }
                        
                        if ($all_children_simple) {
                            // Simple object like package_dimensions - EXPAND it
                            // Add parent for reference
                            $structure[] = array(
                                'path' => $field_path,
                                'label' => $field_path,
                                'type' => 'object',
                                'sample' => count($meaningful_keys) . ' sub-fields'
                            );
                            // Recurse to add children (width, height, length, etc.)
                            $sub_structure = $this->build_field_structure($value, $field_path);
                            $structure = array_merge($structure, $sub_structure);
                        } else {
                            // Complex nested object (like attributes with nested arrays) - expand it anyway
                            $structure[] = array(
                                'path' => $field_path,
                                'label' => $field_path,
                                'type' => 'object',
                                'sample' => count($meaningful_keys) . ' sub-fields'
                            );
                            
                            // For objects containing arrays (like attributes.attribute), recurse to get child paths
                            $sub_structure = $this->build_field_structure($value, $field_path);
                            $structure = array_merge($structure, $sub_structure);
                        }
                    } else {
                        // Object with only metadata - treat as text
                        $text_val = $value['#text'] ?? $value['@content'] ?? '';
                        $structure[] = array(
                            'path' => $field_path,
                            'label' => $field_path,
                            'type' => 'text',
                            'sample' => is_string($text_val) ? substr($text_val, 0, 50) : ''
                        );
                    }
                }
            } else {
                $structure[] = array(
                    'path' => $field_path,
                    'label' => $field_path,
                    'type' => 'text',
                    'sample' => is_string($value) ? substr($value, 0, 50) : (string)$value
                );
            }
        }

        return $structure;
    }

    /**
     * Validate XML file.
     *
     * @since    1.0.0
     * @param    string $file_path Path to XML file
     * @return   array Validation result
     */
    public function validate_xml_file($file_path) {
        try {
            if (!file_exists($file_path)) {
                throw new Exception(__('File does not exist.', 'wc-xml-csv-import'));
            }

            $file_size = filesize($file_path);
            $max_size = get_option('wc_xml_csv_ai_import_settings', array())['max_file_size'] ?? 100;
            
            if ($file_size > ($max_size * 1024 * 1024)) {
                throw new Exception(sprintf(__('File size exceeds maximum limit of %dMB.', 'wc-xml-csv-import'), $max_size));
            }

            // Try to parse XML
            $reader = new XMLReader();
            if (!$reader->open($file_path)) {
                throw new Exception(__('Invalid XML file or unable to open file.', 'wc-xml-csv-import'));
            }

            // Check for well-formed XML
            libxml_use_internal_errors(true);
            while ($reader->read()) {
                // Just reading through to check validity
            }
            
            $errors = libxml_get_errors();
            if (!empty($errors)) {
                $error_messages = array();
                foreach ($errors as $error) {
                    $error_messages[] = trim($error->message);
                }
                throw new Exception(__('XML validation errors: ', 'wc-xml-csv-import') . implode(', ', $error_messages));
            }

            $reader->close();

            return array(
                'valid' => true,
                'message' => __('XML file is valid.', 'wc-xml-csv-import'),
                'file_size' => $file_size
            );

        } catch (Exception $e) {
            return array(
                'valid' => false,
                'message' => $e->getMessage(),
                'file_size' => file_exists($file_path) ? filesize($file_path) : 0
            );
        }
    }

    /**
     * Count total products and extract basic structure from XML file.
     * Used during upload to determine total products before full parsing.
     *
     * @since    1.0.0
     * @param    string $file_path Path to XML file
     * @param    string $product_wrapper XML element containing products
     * @return   array Total product count and basic structure
     */
    public function count_products_and_extract_structure($file_path, $product_wrapper) {
        try {
            // Set time and memory limits for large files
            set_time_limit(600); // 10 minutes for counting
            ini_set('memory_limit', '512M');
            @ignore_user_abort(true);
            
            if (!file_exists($file_path)) {
                throw new Exception(__('XML file not found.', 'wc-xml-csv-import'));
            }

            $reader = new XMLReader();
            if (!$reader->open($file_path)) {
                throw new Exception(__('Unable to open XML file.', 'wc-xml-csv-import'));
            }

            $product_count = 0;
            $structure = array();
            $structure_extracted = false;

            // Iterate through ALL products to count them
            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->localName == $product_wrapper) {
                    $product_count++;
                    
                    // Extract structure from FIRST product only
                    if (!$structure_extracted) {
                        $product_node = $reader->expand();
                        if ($product_node) {
                            $product_dom = new DOMDocument();
                            $imported_node = $product_dom->importNode($product_node, true);
                            $product_dom->appendChild($imported_node);
                            
                            $sample_product = $this->extract_structure_from_element($product_dom->documentElement);
                            $structure = $this->build_field_structure($sample_product);
                            $structure_extracted = true;
                        }
                    }
                    
                    // For very large files, send progress feedback every 1000 products
                    if ($product_count % 1000 == 0) {
                        if (defined('WP_DEBUG') && WP_DEBUG) { error_log("WC XML Import: Counted $product_count products so far..."); }
                    }
                }
            }

            $reader->close();

            return array(
                'success' => true,
                'total_products' => $product_count,
                'structure' => $structure
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}