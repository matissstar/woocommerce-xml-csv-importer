<?php
/**
 * CSV Parser for handling CSV file imports.
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * CSV Parser class.
 */
class WC_XML_CSV_AI_Import_CSV_Parser {

    /**
     * Parse CSV structure for field mapping interface.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @param    int $page Current page for pagination
     * @param    int $per_page Items per page
     * @return   array Parsed structure and sample data
     */
    public function parse_structure($file_path, $page = 1, $per_page = 5) {
        try {
            if (!file_exists($file_path)) {
                throw new Exception(__('CSV file not found.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            // Auto-detect CSV format
            $csv_format = $this->detect_csv_format($file_path);
            
            $handle = fopen($file_path, 'r');
            if (!$handle) {
                throw new Exception(__('Unable to open CSV file.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            // Get headers
            $headers = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape']);
            if (!$headers) {
                throw new Exception(__('Unable to read CSV headers.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $headers = array_map('trim', $headers);
            
            // Build structure from headers
            $structure = array();
            foreach ($headers as $index => $header) {
                $structure[] = array(
                    'path' => !empty($header) ? $header : 'column_' . ($index + 1),
                    'label' => !empty($header) ? $header : 'Column ' . ($index + 1),
                    'type' => 'text',
                    'column_index' => $index
                );
            }

            // Get sample data
            $sample_data = array();
            $row_count = 0;
            $start_index = ($page - 1) * $per_page;

            while (($row = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape'])) !== false) {
                $row_count++;
                
                if ($row_count > $start_index && count($sample_data) < $per_page) {
                    $product_data = array();
                    foreach ($headers as $index => $header) {
                        $field_name = !empty($header) ? $header : 'column_' . ($index + 1);
                        $product_data[$field_name] = isset($row[$index]) ? trim($row[$index]) : '';
                    }
                    $sample_data[] = $product_data;
                }
            }

            fclose($handle);

            // Count total rows
            $total_products = $this->count_products($file_path) - 1; // Subtract header row

            return array(
                'structure' => $structure,
                'sample_data' => $sample_data,
                'total_products' => $total_products,
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => ceil($total_products / $per_page),
                'csv_format' => $csv_format,
                'headers' => $headers
            );

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('CSV Parser Error: ' . $e->getMessage()); }
            return array(
                'error' => $e->getMessage(),
                'structure' => array(),
                'sample_data' => array(),
                'total_products' => 0
            );
        }
    }

    /**
     * Extract products from CSV file for import processing.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @param    int $offset Starting position
     * @param    int $limit Number of products to extract
     * @return   array Array of product data
     */
    public function extract_products($file_path, $offset = 0, $limit = 50) {
        try {
            $products = array();
            
            if (!file_exists($file_path)) {
                throw new Exception(__('CSV file not found.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $csv_format = $this->detect_csv_format($file_path);
            $handle = fopen($file_path, 'r');
            
            if (!$handle) {
                throw new Exception(__('Unable to open CSV file.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            // Get headers
            $headers = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape']);
            $headers = array_map('trim', $headers);

            $row_count = 0;
            $extracted_count = 0;

            while (($row = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape'])) !== false && $extracted_count < $limit) {
                if ($row_count >= $offset) {
                    $product_data = array();
                    foreach ($headers as $index => $header) {
                        $field_name = !empty($header) ? $header : 'column_' . ($index + 1);
                        $product_data[$field_name] = isset($row[$index]) ? trim($row[$index]) : '';
                    }
                    $products[] = $product_data;
                    $extracted_count++;
                }
                $row_count++;
            }

            fclose($handle);
            return $products;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('CSV Extract Error: ' . $e->getMessage()); }
            return array();
        }
    }

    /**
     * Extract field value from product data.
     *
     * @since    1.0.0
     * @param    array $product_data Product data array
     * @param    string $field_path Field name/path
     * @return   mixed Field value or null
     */
    public function extract_field_value($product_data, $field_path) {
        return isset($product_data[$field_path]) ? $product_data[$field_path] : null;
    }

    /**
     * Count total products in CSV file.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @return   int Total row count
     */
    public function count_products($file_path) {
        try {
            if (!file_exists($file_path)) {
                return 0;
            }

            $count = 0;
            $handle = fopen($file_path, 'r');
            
            if ($handle) {
                while (fgets($handle) !== false) {
                    $count++;
                }
                fclose($handle);
            }

            return $count;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('CSV Count Error: ' . $e->getMessage()); }
            return 0;
        }
    }

    /**
     * Auto-detect CSV format (delimiter, enclosure, escape).
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @return   array CSV format settings
     */
    private function detect_csv_format($file_path) {
        $delimiters = array(',', ';', "\t", '|');
        $enclosures = array('"', "'");
        $escape = "\\";

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return array(
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => $escape
            );
        }

        // Read first few lines for analysis
        $sample_lines = array();
        for ($i = 0; $i < 5; $i++) {
            $line = fgets($handle);
            if ($line === false) break;
            $sample_lines[] = trim($line);
        }
        fclose($handle);

        if (empty($sample_lines)) {
            return array(
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => $escape
            );
        }

        $sample_text = implode("\n", $sample_lines);

        // Detect delimiter
        $best_delimiter = ',';
        $max_columns = 0;
        
        foreach ($delimiters as $delimiter) {
            $columns = 0;
            foreach ($sample_lines as $line) {
                $fields = str_getcsv($line, $delimiter);
                $columns = max($columns, count($fields));
            }
            
            if ($columns > $max_columns) {
                $max_columns = $columns;
                $best_delimiter = $delimiter;
            }
        }

        // Detect enclosure
        $best_enclosure = '"';
        foreach ($enclosures as $enclosure) {
            if (strpos($sample_text, $enclosure) !== false) {
                $best_enclosure = $enclosure;
                break;
            }
        }

        return array(
            'delimiter' => $best_delimiter,
            'enclosure' => $best_enclosure,
            'escape' => $escape
        );
    }

    /**
     * Detect CSV encoding.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @return   string Detected encoding
     */
    private function detect_encoding($file_path) {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return 'UTF-8';
        }

        $sample = fread($handle, 1024);
        fclose($handle);

        $encodings = array('UTF-8', 'UTF-16', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252');
        
        foreach ($encodings as $encoding) {
            if (mb_check_encoding($sample, $encoding)) {
                return $encoding;
            }
        }

        return 'UTF-8';
    }

    /**
     * Convert CSV file to UTF-8 if needed.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @return   string Path to converted file (or original if no conversion needed)
     */
    public function convert_to_utf8($file_path) {
        $encoding = $this->detect_encoding($file_path);
        
        if ($encoding === 'UTF-8') {
            return $file_path;
        }

        $content = file_get_contents($file_path);
        $utf8_content = mb_convert_encoding($content, 'UTF-8', $encoding);
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wc-xml-csv-import/temp/';
        $temp_file = $temp_dir . 'converted_' . basename($file_path);
        
        if (file_put_contents($temp_file, $utf8_content)) {
            return $temp_file;
        }

        return $file_path;
    }

    /**
     * Validate CSV file.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @return   array Validation result
     */
    public function validate_csv_file($file_path) {
        try {
            if (!file_exists($file_path)) {
                throw new Exception(__('File does not exist.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $file_size = filesize($file_path);
            $settings = get_option('wc_xml_csv_ai_import_settings', array());
            $max_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 100;
            
            if ($file_size > ($max_size * 1024 * 1024)) {
                throw new Exception(sprintf(__('File size exceeds maximum limit of %dMB.', 'bootflow-woocommerce-xml-csv-importer'), $max_size));
            }

            // Try to read first line
            $handle = fopen($file_path, 'r');
            if (!$handle) {
                throw new Exception(__('Unable to open CSV file.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $first_line = fgets($handle);
            if ($first_line === false) {
                throw new Exception(__('CSV file appears to be empty.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            // Check if we can parse the CSV
            $csv_format = $this->detect_csv_format($file_path);
            rewind($handle);
            
            $headers = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape']);
            if (!$headers || empty($headers)) {
                throw new Exception(__('Unable to parse CSV headers.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            fclose($handle);

            return array(
                'valid' => true,
                'message' => sprintf(__('CSV file is valid with %d columns.', 'bootflow-woocommerce-xml-csv-importer'), count($headers)),
                'file_size' => $file_size,
                'column_count' => count($headers),
                'csv_format' => $csv_format
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
     * Count total rows in CSV file.
     * Used during upload to determine total products.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @return   array Total row count and structure
     */
    public function count_rows_and_extract_structure($file_path) {
        try {
            set_time_limit(600);
            ini_set('memory_limit', '512M');
            @ignore_user_abort(true);

            if (!file_exists($file_path)) {
                throw new Exception(__('CSV file not found.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $handle = fopen($file_path, 'r');
            if (!$handle) {
                throw new Exception(__('Unable to open CSV file.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            // Detect delimiter
            $csv_format = $this->detect_csv_format($file_path);
            
            // Read headers
            $headers = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure']);
            if (!$headers) {
                fclose($handle);
                throw new Exception(__('CSV file has no headers.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $structure = array();
            foreach ($headers as $header) {
                $structure[] = array(
                    'path' => trim($header),
                    'sample' => ''
                );
            }

            // Count all rows (excluding header)
            $row_count = 0;
            while (fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure']) !== false) {
                $row_count++;
                
                if ($row_count % 10000 == 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) { error_log("WC CSV Import: Counted $row_count rows so far..."); }
                }
            }

            fclose($handle);

            return array(
                'success' => true,
                'total_products' => $row_count,
                'structure' => $structure
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Extract products grouped by parent for variable products.
     * Used when CSV contains parent products with their variations.
     *
     * @since    1.0.0
     * @param    string $file_path Path to CSV file
     * @param    string $parent_sku_column Column name for parent SKU
     * @param    string $type_column Column name for product type (variable/variation)
     * @param    int $offset Starting position
     * @param    int $limit Number of PARENT products to extract
     * @return   array Array of grouped product data
     */
    public function extract_products_grouped($file_path, $parent_sku_column, $type_column = '', $offset = 0, $limit = 50) {
        try {
            if (!file_exists($file_path)) {
                throw new Exception(__('CSV file not found.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            $csv_format = $this->detect_csv_format($file_path);
            $handle = fopen($file_path, 'r');
            
            if (!$handle) {
                throw new Exception(__('Unable to open CSV file.', 'bootflow-woocommerce-xml-csv-importer'));
            }

            // Get headers
            $headers = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape']);
            $headers = array_map('trim', $headers);
            
            // Find column indices
            $parent_sku_index = array_search($parent_sku_column, $headers);
            $type_index = !empty($type_column) ? array_search($type_column, $headers) : false;
            
            if ($parent_sku_index === false) {
                fclose($handle);
                throw new Exception(sprintf(__('Parent SKU column "%s" not found in CSV.', 'bootflow-woocommerce-xml-csv-importer'), $parent_sku_column));
            }
            
            // Read all rows and group by parent SKU
            $all_rows = array();
            $parent_rows = array();
            $variation_rows = array();
            
            while (($row = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape'])) !== false) {
                $product_data = array();
                foreach ($headers as $index => $header) {
                    $field_name = !empty($header) ? $header : 'column_' . ($index + 1);
                    $product_data[$field_name] = isset($row[$index]) ? trim($row[$index]) : '';
                }
                
                $parent_sku = $product_data[$parent_sku_column] ?? '';
                $product_type = $type_index !== false ? strtolower(trim($product_data[$type_column] ?? '')) : '';
                
                // Determine if this is a parent or variation
                $is_parent = false;
                if ($type_index !== false) {
                    // Use type column if available
                    $is_parent = ($product_type === 'variable' || $product_type === 'parent');
                } else {
                    // Fallback: if SKU column is empty but parent SKU exists, it's likely a parent
                    $sku_column = $this->find_sku_column($headers);
                    if ($sku_column !== false) {
                        $sku_value = $product_data[$headers[$sku_column]] ?? '';
                        $is_parent = (empty($sku_value) && !empty($parent_sku));
                    }
                }
                
                if ($is_parent) {
                    $parent_rows[$parent_sku] = $product_data;
                } else if (!empty($parent_sku)) {
                    if (!isset($variation_rows[$parent_sku])) {
                        $variation_rows[$parent_sku] = array();
                    }
                    $variation_rows[$parent_sku][] = $product_data;
                } else {
                    // Simple product (no parent SKU, not marked as parent)
                    $all_rows[] = array(
                        'type' => 'simple',
                        'data' => $product_data,
                        'variations' => array()
                    );
                }
            }
            
            fclose($handle);
            
            // Combine parents with their variations
            $grouped_products = array();
            foreach ($parent_rows as $parent_sku => $parent_data) {
                $grouped_products[] = array(
                    'type' => 'variable',
                    'data' => $parent_data,
                    'variations' => $variation_rows[$parent_sku] ?? array()
                );
            }
            
            // Add any orphan variations (variations without parent row in CSV)
            foreach ($variation_rows as $parent_sku => $variations) {
                if (!isset($parent_rows[$parent_sku]) && !empty($variations)) {
                    // Create a synthetic parent from first variation
                    $first_var = $variations[0];
                    $grouped_products[] = array(
                        'type' => 'variable',
                        'data' => $first_var, // Use first variation as parent data
                        'variations' => $variations,
                        'synthetic_parent' => true
                    );
                }
            }
            
            // Also add simple products
            $grouped_products = array_merge($grouped_products, $all_rows);
            
            // Apply pagination
            $total_products = count($grouped_products);
            $paginated = array_slice($grouped_products, $offset, $limit);
            
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log("CSV Parser: Extracted " . count($paginated) . " grouped products (offset: $offset, limit: $limit, total: $total_products)"); }
            
            return array(
                'products' => $paginated,
                'total' => $total_products
            );

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('CSV Extract Grouped Error: ' . $e->getMessage()); }
            return array(
                'products' => array(),
                'total' => 0,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Find SKU column in headers.
     *
     * @param array $headers CSV headers
     * @return int|false Column index or false if not found
     */
    private function find_sku_column($headers) {
        $sku_patterns = array('sku', 'product_sku', 'article', 'artikuls', 'code', 'product code');
        foreach ($headers as $index => $header) {
            $header_lower = strtolower(trim($header));
            if (in_array($header_lower, $sku_patterns)) {
                return $index;
            }
        }
        return false;
    }
    
    /**
     * Count unique parent products in CSV with variations.
     *
     * @param string $file_path Path to CSV file
     * @param string $parent_sku_column Column name for parent SKU
     * @param string $type_column Column name for product type
     * @return int Number of unique parent products
     */
    public function count_parent_products($file_path, $parent_sku_column, $type_column = '') {
        try {
            if (!file_exists($file_path)) {
                return 0;
            }

            $csv_format = $this->detect_csv_format($file_path);
            $handle = fopen($file_path, 'r');
            
            if (!$handle) {
                return 0;
            }

            // Get headers
            $headers = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape']);
            $headers = array_map('trim', $headers);
            
            $parent_sku_index = array_search($parent_sku_column, $headers);
            $type_index = !empty($type_column) ? array_search($type_column, $headers) : false;
            
            if ($parent_sku_index === false) {
                fclose($handle);
                return 0;
            }
            
            $parent_skus = array();
            $simple_count = 0;
            
            while (($row = fgetcsv($handle, 0, $csv_format['delimiter'], $csv_format['enclosure'], $csv_format['escape'])) !== false) {
                $parent_sku = isset($row[$parent_sku_index]) ? trim($row[$parent_sku_index]) : '';
                $product_type = $type_index !== false && isset($row[$type_index]) ? strtolower(trim($row[$type_index])) : '';
                
                if ($product_type === 'variable' || $product_type === 'parent') {
                    $parent_skus[$parent_sku] = true;
                } else if (empty($parent_sku)) {
                    // Simple product
                    $simple_count++;
                }
            }
            
            fclose($handle);
            
            return count($parent_skus) + $simple_count;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('CSV Count Parents Error: ' . $e->getMessage()); }
            return 0;
        }
    }
}