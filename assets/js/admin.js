/**
 * WooCommerce XML/CSV Smart AI Import - Admin JavaScript
 */

// Global function for onclick fallback
window.addDisplayAttributeRowGlobal = function(targetContainer) {
    targetContainer = targetContainer || 'display-attributes-list';
    console.log('★★★ GLOBAL addDisplayAttributeRowGlobal() CALLED, target:', targetContainer);
    jQuery('#' + targetContainer).append(window.createDisplayAttributeHtml());
    // Populate dropdown
    window.populateFieldSelectorsForRowGlobal(jQuery('#' + targetContainer + ' .display-attribute-row:last'));
};

window.createDisplayAttributeHtml = function() {
    var index = jQuery('.display-attribute-row').length;
    return '<div class="display-attribute-row" data-index="' + index + '" style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px;">' +
        '<div style="flex: 1;">' +
        '<label style="font-size: 12px; color: #666;">Attribute Name</label>' +
        '<input type="text" name="display_attr[' + index + '][name]" placeholder="e.g., Material" style="width: 100%;">' +
        '</div>' +
        '<div style="flex: 2;">' +
        '<label style="font-size: 12px; color: #666;">Values Source</label>' +
        '<select name="display_attr[' + index + '][source]" class="field-source-select" style="width: 100%;">' +
        '<option value="">-- Select Field --</option>' +
        '</select>' +
        '</div>' +
        '<div style="padding-top: 20px;">' +
        '<label style="cursor: pointer;">' +
        '<input type="checkbox" name="display_attr[' + index + '][visible]" value="1" checked>' +
        ' Visible' +
        '</label>' +
        '</div>' +
        '<button type="button" class="button remove-display-attribute" style="padding-top: 18px; color: #d63638;">×</button>' +
        '</div>';
};

window.populateFieldSelectorsForRowGlobal = function($row) {
    $row.find('.field-source-select').each(function() {
        var $select = jQuery(this);
        var currentVal = $select.val();
        $select.find('option:not(:first)').remove();
        if (window.allKnownFieldsOrder && window.allKnownFieldsOrder.length > 0) {
            window.allKnownFieldsOrder.forEach(function(field) {
                $select.append('<option value="' + field + '">' + field + '</option>');
            });
        }
        if (currentVal) {
            $select.val(currentVal);
        }
    });
};

(function($) {
    'use strict';

    // Global variables - make them accessible globally for edit page
    window.currentFileStructure = null;
    window.currentSampleData = null;
    window.allKnownFields = {}; // Store ALL discovered fields from all products (for lookup)
    window.allKnownFieldsOrder = []; // Store field paths in original XML order
    var currentPage = 1;
    var totalPages = 1;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeStep();
    });

    /**
     * Initialize based on current step
     */
    function initializeStep() {
        if ($('.wc-ai-import-step-1').length) {
            initializeStep1();
        } else if ($('.wc-ai-import-step-2').length) {
            initializeStep2();
        } else if ($('.wc-ai-import-step-3').length) {
            initializeStep3();
        }
    }

    /**
     * Step 1: File Upload
     */
    function initializeStep1() {
        console.log('Initializing Step 1: File Upload');

        // Upload method selection
        $('input[name="upload_method"]').on('change', function() {
            var method = $(this).val();
            if (method === 'file') {
                $('#file-upload-section').show();
                $('#url-upload-section').hide();
            } else {
                $('#file-upload-section').hide();
                $('#url-upload-section').show();
            }
        });

        // URL input change - XML wrapper section is now always visible
        // No longer need to show/hide based on extension

        // Drag and drop
        setupDragAndDrop();

        // Remove file - XML wrapper section stays visible
        $('#remove-file').on('click', function() {
            $('#file_upload').val('');
            $('#file-preview').hide();
        });

        // Test URL
        $('#test-url').on('click', testUrl);

        // Form submission
        $('#wc-ai-import-upload-form').on('submit', handleUploadSubmission);
    }

    /**
     * Step 2: Field Mapping
     */
    function initializeStep2() {
        console.log('Initializing Step 2: Field Mapping');
        
        // Detect and set file type
        if (typeof wcAiImportData !== 'undefined' && wcAiImportData.file_type) {
            $('#detected_file_type').val(wcAiImportData.file_type);
            console.log('★★★ Set detected_file_type to:', wcAiImportData.file_type);
        } else if (typeof wcAiImportData !== 'undefined' && wcAiImportData.file_path) {
            // Fallback: detect from file extension
            var ext = wcAiImportData.file_path.split('.').pop().toLowerCase();
            if (ext === 'csv') {
                $('#detected_file_type').val('csv');
            } else {
                $('#detected_file_type').val('xml');
            }
            console.log('★★★ Detected file type from extension:', ext);
        }
        
        // Load saved recipes into dropdown
        loadRecipesList();

        // Load file structure if wcAiImportData.file_path is available (new import or edit mode)
        if (typeof wcAiImportData !== 'undefined' && wcAiImportData.file_path) {
            loadFileStructure();
            // Note: loadSavedMappings is now called inside loadFileStructure success callback
            // after dropdowns are populated
        } else {
            console.log('Skipping loadFileStructure - no file data available');
            // No file structure to load, but still load saved mappings if available
            if (typeof wcAiImportData !== 'undefined' && wcAiImportData.saved_mappings) {
                console.log('★★★ LOADING SAVED MAPPINGS (no file structure) ★★★');
                loadSavedMappings(wcAiImportData.saved_mappings);
            }
        }
        
        // Load saved custom fields if available
        if (typeof wcAiImportData !== 'undefined' && wcAiImportData.saved_custom_fields && wcAiImportData.saved_custom_fields.length > 0) {
            setTimeout(function() {
                loadSavedCustomFields(wcAiImportData.saved_custom_fields);
            }, 1000); // Wait a bit longer after mappings
        }

        // Section toggles
        $('.section-toggle').on('click', function() {
            var target = $(this).data('target');
            var section = $('#section-' + target);
            var icon = $(this).find('.dashicons');

            if (section.is(':visible')) {
                section.slideUp();
                icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
            } else {
                section.slideDown();
                icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
            }
        });

        // Processing mode changes
        $(document).on('change', '.processing-mode-select', function() {
            var mode = $(this).val();
            var row = $(this).closest('.field-mapping-row');
            var configDiv = row.find('.processing-config');
            var panels = configDiv.find('.config-panel');

            // Hide all config panels first
            panels.hide();

            // Auto-show/hide config div based on mode
            if (mode === 'direct') {
                // Direct mode - hide config
                configDiv.slideUp(200);
            } else {
                // Non-direct mode - show config div and appropriate panel
                configDiv.slideDown(200);
                
                // Find the right panel based on mode
                // php_formula -> .php-formula-config
                // ai_processing -> .ai-processing-config
                // hybrid -> .hybrid-config
                var panelClass = '.' + mode.replace('_', '-') + '-config';
                configDiv.find(panelClass).show();
                
                console.log('★ Showing config panel:', panelClass, 'Found:', configDiv.find(panelClass).length);
            }
        });

        // Toggle config buttons
        $(document).on('click', '.toggle-config', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var row = $(this).closest('.field-mapping-row');
            var configDiv = row.find('.processing-config');
            
            configDiv.slideToggle(200);
        });

        // Clear mapping buttons
        $(document).on('click', '.clear-mapping', function() {
            var row = $(this).closest('.field-mapping-row');
            row.find('.field-source-select').val('');
            row.find('.processing-mode-select').val('direct').trigger('change');
            row.find('textarea, input[type="text"]').val('');
        });

        // Quick actions
        $('#clear-all-mapping').on('click', clearAllMapping);
        $('#test-mapping').on('click', testMapping);

        // Import filters - use event delegation for dynamic content
        $(document).on('click', '#add-filter-rule', addFilterRule);
        $(document).on('click', '.remove-filter-rule', removeFilterRule);

        // Custom fields
        $('#add-custom-field').on('click', addCustomField);
        $(document).on('click', '.remove-custom-field', removeCustomField);

        // AI testing
        $(document).on('click', '.test-ai-field', testAiField);

        // PHP formula testing
        $(document).on('click', '.test-php-formula', testPhpFormula);

        // Shipping formula testing
        $(document).on('click', '.test-shipping-formula', testShippingFormula);

        // Boolean field radio buttons (Yes/No/Map)
        $(document).on('change', '.boolean-mode-radio', function() {
            var mode = $(this).val();
            var row = $(this).closest('.field-mapping-row');
            var mapField = row.find('.boolean-map-field');
            
            if (mode === 'map') {
                mapField.slideDown(200);
            } else {
                mapField.slideUp(200);
            }
        });

        // Select-with-map radio buttons (Fixed value / Map from XML)
        $(document).on('change', '.select-mode-radio', function() {
            var mode = $(this).val();
            var row = $(this).closest('.field-mapping-row');
            var fixedSelect = row.find('.select-fixed-value');
            var mapField = row.find('.select-map-field');
            
            if (mode === 'map') {
                fixedSelect.prop('disabled', true).css('opacity', '0.5');
                mapField.slideDown(200);
            } else {
                fixedSelect.prop('disabled', false).css('opacity', '1');
                mapField.slideUp(200);
            }
        });

        // SKU field radio buttons (Map from file / Auto-generate)
        $(document).on('change', '.sku-mode-radio', function() {
            var mode = $(this).val();
            var row = $(this).closest('.field-mapping-row');
            var mapPanel = row.find('.sku-map-panel');
            var generatePanel = row.find('.sku-generate-panel');
            
            if (mode === 'generate') {
                mapPanel.slideUp(200);
                generatePanel.slideDown(200);
                // Update preview
                updateSkuPreview(row);
            } else {
                mapPanel.slideDown(200);
                generatePanel.slideUp(200);
            }
        });
        
        // SKU pattern input - update preview on change
        $(document).on('input', '.sku-pattern-input', function() {
            var row = $(this).closest('.field-mapping-row');
            updateSkuPreview(row);
        });

        // Primary identifier checkbox - only one can be selected
        $(document).on('change', '.primary-identifier-checkbox', function() {
            if ($(this).is(':checked')) {
                // Uncheck all other primary identifier checkboxes
                $('.primary-identifier-checkbox').not(this).prop('checked', false);
                
                // Add visual highlight to selected row
                $('.field-mapping-row').removeClass('primary-identifier-selected');
                $(this).closest('.field-mapping-row').addClass('primary-identifier-selected');
            } else {
                $(this).closest('.field-mapping-row').removeClass('primary-identifier-selected');
            }
        });

        // Auto-enable Manage Stock when Stock Quantity is mapped
        $(document).on('change', '.field-source-select, .processing-mode-select', function() {
            var $row = $(this).closest('.field-mapping-row');
            var fieldName = $row.data('field');
            
            // Only react to stock_quantity field changes
            if (fieldName === 'stock_quantity') {
                var sourceValue = $row.find('.field-source-select').val();
                var processingMode = $row.find('.processing-mode-select').val();
                
                // Check if a source is selected (Direct, PHP, or AI mapping)
                var hasMapping = sourceValue && sourceValue !== '';
                
                if (hasMapping) {
                    // Auto-enable manage_stock
                    autoEnableManageStock();
                }
            }
        });

        // Form submission
        $('#wc-ai-import-mapping-form').on('submit', handleMappingSubmission);

        // Save mapping
        $('#save-mapping').on('click', saveMapping);
        
        // Attributes & Variations
        initializeAttributesAndVariations();
        
        // NEW: Product Type Mode Selection
        initializeProductTypeMode();
        
        // AI Auto-Mapping (PRO tier)
        initializeAiAutoMapping();
    }
    
    /**
     * Initialize AI Auto-Mapping functionality (PRO tier feature)
     */
    function initializeAiAutoMapping() {
        console.log('★★★ initializeAiAutoMapping() CALLED ★★★');
        
        $('#btn-ai-auto-map').on('click', function() {
            var $btn = $(this);
            var $statusContainer = $('#ai-mapping-status');
            var $progress = $('#ai-mapping-progress');
            var $result = $('#ai-mapping-result');
            
            // Check if we have source fields
            var sourceFields = window.allKnownFieldsOrder || [];
            if (sourceFields.length === 0) {
                alert('Please wait for file structure to load first, or there are no fields in the file.');
                return;
            }
            
            // Get selected AI provider
            var provider = $('#ai-mapping-provider').val() || 'openai';
            var fileType = $('#detected_file_type').val() || 'xml';
            
            // Get sample data from first product if available
            var sampleData = {};
            if (window.currentSampleData && window.currentSampleData.length > 0) {
                sampleData = window.currentSampleData[0] || {};
            }
            
            // Show progress
            $btn.prop('disabled', true);
            var originalText = $btn.html();
            $btn.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Analyzing...');
            
            $statusContainer.show();
            $progress.show();
            $result.hide();
            
            console.log('★★★ AI Auto-Mapping - Sending request', {
                sourceFields: sourceFields,
                provider: provider,
                fileType: fileType,
                sampleDataKeys: Object.keys(sampleData)
            });
            
            // Send AJAX request
            $.ajax({
                url: wcAiImportData.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_xml_csv_ai_auto_mapping',
                    nonce: wcAiImportData.nonce,
                    source_fields: sourceFields,
                    provider: provider,
                    file_type: fileType,
                    sample_data: sampleData
                },
                success: function(response) {
                    console.log('★★★ AI Auto-Mapping Response:', response);
                    $progress.hide();
                    
                    if (response.success) {
                        var mappings = response.data.mappings;
                        var confidence = response.data.confidence || {};
                        var unmapped = response.data.unmapped || [];
                        var mappedCount = response.data.mapped_count || 0;
                        
                        // Apply mappings to dropdowns
                        applyAiMappings(mappings, confidence);
                        
                        // Show result
                        var resultHtml = '<div style="color: #90EE90;">';
                        resultHtml += '<strong>✅ ' + response.data.message + '</strong>';
                        resultHtml += '</div>';
                        
                        if (mappedCount > 0) {
                            resultHtml += '<div style="margin-top: 10px; font-size: 12px;">';
                            resultHtml += '<strong>Mapped fields:</strong><br>';
                            $.each(mappings, function(source, target) {
                                var conf = confidence[source] || '?';
                                resultHtml += '<span style="display: inline-block; margin: 2px 5px 2px 0; padding: 2px 8px; background: rgba(255,255,255,0.2); border-radius: 3px;">';
                                resultHtml += source + ' → ' + target;
                                if (conf !== '?') {
                                    resultHtml += ' <span style="opacity: 0.7;">(' + conf + '%)</span>';
                                }
                                resultHtml += '</span>';
                            });
                            resultHtml += '</div>';
                        }
                        
                        if (unmapped.length > 0) {
                            resultHtml += '<div style="margin-top: 8px; font-size: 11px; opacity: 0.8;">';
                            resultHtml += 'Unmapped: ' + unmapped.join(', ');
                            resultHtml += '</div>';
                        }
                        
                        $result.html(resultHtml).show();
                        
                    } else {
                        $result.html('<div style="color: #ffcdd2;">❌ ' + (response.data.message || 'Unknown error') + '</div>').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('★★★ AI Auto-Mapping Error:', error);
                    $progress.hide();
                    $result.html('<div style="color: #ffcdd2;">❌ Request failed: ' + error + '</div>').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        });
        
        // Confirm mapping button (OK button on warning)
        $('#btn-confirm-mapping').on('click', function() {
            window.autoMappingVerified = true;
            $('#auto-mapping-warning').slideUp(300);
            
            // Show success message
            var $msg = $('<div style="background: #e8f5e9; color: #2e7d32; padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">' +
                '<span style="font-size: 20px;">✅</span>' +
                '<span>Mappings verified! You can now proceed with the import.</span>' +
                '</div>');
            $('#auto-mapping-warning').after($msg);
            setTimeout(function() {
                $msg.fadeOut(500, function() { $msg.remove(); });
            }, 3000);
        });
    }
    
    /**
     * Apply AI-suggested mappings to form fields
     */
    function applyAiMappings(mappings, confidence) {
        console.log('★★★ applyAiMappings() called with:', mappings);
        
        var appliedCount = 0;
        var skuMapped = false;
        var stockQuantityMapped = false;
        
        // Iterate through each mapping
        $.each(mappings, function(sourceField, targetWcField) {
            console.log('★★★ Trying to map:', sourceField, '→', targetWcField);
            
            // Find the mapping row for this WooCommerce field
            var $row = $('.field-mapping-row[data-field="' + targetWcField + '"]');
            
            if ($row.length > 0) {
                // Find the source field dropdown in this row
                var $sourceSelect = $row.find('.field-source-select');
                
                if ($sourceSelect.length > 0) {
                    // Check if the source field option exists
                    var optionExists = $sourceSelect.find('option[value="' + sourceField + '"]').length > 0;
                    
                    if (optionExists) {
                        // Set the value
                        $sourceSelect.val(sourceField).trigger('change');
                        
                        // Track if SKU was mapped
                        if (targetWcField === 'sku') {
                            skuMapped = true;
                        }
                        
                        // Track if Stock Quantity was mapped
                        if (targetWcField === 'stock_quantity') {
                            stockQuantityMapped = true;
                        }
                        
                        // Highlight the row briefly
                        $row.css({
                            'background': '#e8f5e9',
                            'transition': 'background 0.3s'
                        });
                        
                        setTimeout(function() {
                            $row.css('background', '');
                        }, 2000);
                        
                        appliedCount++;
                        console.log('★★★ Successfully mapped:', sourceField, '→', targetWcField);
                    } else {
                        console.log('★★★ Source field option not found in dropdown:', sourceField);
                    }
                }
            } else {
                console.log('★★★ WC field row not found:', targetWcField);
            }
        });
        
        // If SKU was not mapped, enable Auto-generate mode
        if (!skuMapped) {
            console.log('★★★ SKU not mapped by AI - enabling Auto-generate mode');
            enableSkuAutoGenerate();
        }
        
        // If Stock Quantity was mapped, auto-enable Manage Stock
        if (stockQuantityMapped) {
            autoEnableManageStock();
        }
        
        console.log('★★★ Applied', appliedCount, 'mappings');
        
        // Update mapped counts in section headers
        updateMappedCounts();
        
        // Show warning that auto-mapping was used
        showAutoMappingWarning();
    }
    
    /**
     * Smart Auto-Mapping patterns (field name → WooCommerce field)
     * Works without AI - pattern matching
     */
    var smartMappingPatterns = {
        // SKU patterns
        'sku': ['sku', 'code', 'product_code', 'product_sku', 'article', 'artikuls', 'kods', 'preces_kods', 'item_code', 'item_number', 'ean', 'upc', 'barcode'],
        // Name patterns  
        'name': ['name', 'title', 'product_name', 'product_title', 'nosaukums', 'produkta_nosaukums', 'prece', 'item_name', 'description_short', 'bezeichnung'],
        // Description patterns
        'description': ['description', 'desc', 'apraksts', 'product_description', 'full_description', 'long_description', 'beschreibung', 'details', 'content'],
        // Short description patterns
        'short_description': ['short_description', 'short_desc', 'excerpt', 'summary', 'iss_apraksts', 'intro', 'brief'],
        // Price patterns
        'regular_price': ['price', 'regular_price', 'cena', 'base_price', 'retail_price', 'msrp', 'preis', 'unit_price'],
        'sale_price': ['sale_price', 'special_price', 'akcijas_cena', 'discount_price', 'offer_price', 'promo_price'],
        // Sale price dates
        'sale_price_dates_from': ['sale_price_dates_from', 'sale_from', 'sale_start', 'sale_start_date', 'sale_date_from', 'special_from_date', 'akcijas_sakums', 'discount_start'],
        'sale_price_dates_to': ['sale_price_dates_to', 'sale_to', 'sale_end', 'sale_end_date', 'sale_date_to', 'special_to_date', 'akcijas_beigas', 'discount_end'],
        // Stock patterns
        'stock_quantity': ['stock', 'qty', 'quantity', 'stock_quantity', 'daudzums', 'skaits', 'noliktava', 'inventory', 'available', 'bestand', 'amount'],
        // Weight/dimensions
        'weight': ['weight', 'svars', 'gross_weight', 'net_weight', 'gewicht', 'mass'],
        'length': ['length', 'garums', 'länge', 'dimension_length', 'package_length'],
        'width': ['width', 'platums', 'breite', 'dimension_width', 'package_width'],
        'height': ['height', 'augstums', 'höhe', 'dimension_height', 'package_height'],
        // Category
        'categories': ['category', 'categories', 'kategorija', 'cat', 'product_category', 'kategorie', 'group'],
        // Brand
        'brand': ['brand', 'manufacturer', 'zīmols', 'ražotājs', 'marke', 'make', 'vendor'],
        // Images
        'images': ['image', 'images', 'attēls', 'photo', 'picture', 'bild', 'gallery', 'image_url', 'img', 'thumbnail'],
        'featured_image': ['featured_image', 'main_image', 'galvenais_attēls', 'primary_image', 'main_photo'],
        // EAN/UPC
        'ean': ['ean', 'ean13', 'ean_code', 'gtin', 'eans'],
        'upc': ['upc', 'upc_code'],
        // Tags
        'tags': ['tags', 'birkas', 'keywords', 'tag'],
        // ID/Slug
        'id': ['id', 'product_id', 'woo_id', 'woocommerce_id', 'external_id'],
        'slug': ['slug', 'url_key', 'permalink', 'handle', 'url_slug', 'seo_url'],
        // Stock management
        'backorders': ['backorders', 'backorder', 'allow_backorders', 'pasūtījumi_rezervēti'],
        'sold_individually': ['sold_individually', 'sold_individual', 'individual_sale', 'limit_one'],
        // Related products
        'upsell_ids': ['upsell_ids', 'upsells', 'upsell', 'upsell_products', 'related_upsell'],
        'cross_sell_ids': ['cross_sell_ids', 'cross_sells', 'crosssell', 'crosssells', 'cross_sell_products'],
        // Reviews
        'reviews_allowed': ['reviews_allowed', 'allow_reviews', 'enable_reviews', 'reviews_enabled'],
        'average_rating': ['average_rating', 'rating', 'avg_rating', 'star_rating', 'vērtējums'],
        'rating_count': ['rating_count', 'review_count', 'reviews_count', 'num_reviews', 'atsauksmju_skaits']
    };
    
    /**
     * Perform smart mapping using pattern matching (used internally by AI mapping fallback)
     */
    function performSmartMapping(sourceFields) {
        var mappings = {};
        
        $.each(sourceFields, function(i, sourceField) {
            var normalizedSource = normalizeFieldName(sourceField);
            var matched = false;
            
            // Check against each WooCommerce field pattern
            $.each(smartMappingPatterns, function(wcField, patterns) {
                if (matched) return;
                
                $.each(patterns, function(j, pattern) {
                    if (matched) return;
                    
                    var normalizedPattern = normalizeFieldName(pattern);
                    
                    // Exact match
                    if (normalizedSource === normalizedPattern) {
                        mappings[sourceField] = wcField;
                        matched = true;
                        console.log('★★★ Smart match (exact):', sourceField, '→', wcField);
                        return false;
                    }
                    
                    // Contains match (for compound names like "product_name" matching "name")
                    if (normalizedSource.indexOf(normalizedPattern) !== -1 || normalizedPattern.indexOf(normalizedSource) !== -1) {
                        mappings[sourceField] = wcField;
                        matched = true;
                        console.log('★★★ Smart match (contains):', sourceField, '→', wcField);
                        return false;
                    }
                });
            });
        });
        
        return mappings;
    }
    
    /**
     * Normalize field name for comparison
     */
    function normalizeFieldName(name) {
        if (!name) return '';
        return name.toString()
            .toLowerCase()
            .replace(/[^a-z0-9āčēģīķļņšūž]/g, '') // Remove special chars, keep latvian letters
            .replace(/ā/g, 'a')
            .replace(/č/g, 'c')
            .replace(/ē/g, 'e')
            .replace(/ģ/g, 'g')
            .replace(/ī/g, 'i')
            .replace(/ķ/g, 'k')
            .replace(/ļ/g, 'l')
            .replace(/ņ/g, 'n')
            .replace(/š/g, 's')
            .replace(/ū/g, 'u')
            .replace(/ž/g, 'z');
    }
    
    /**
     * Apply smart mappings to form fields
     */
    function applySmartMappings(mappings) {
        console.log('★★★ applySmartMappings() called with:', mappings);
        
        var appliedCount = 0;
        var skuMapped = false;
        
        $.each(mappings, function(sourceField, targetWcField) {
            var $row = $('.field-mapping-row[data-field="' + targetWcField + '"]');
            
            if ($row.length > 0) {
                var $sourceSelect = $row.find('.field-source-select');
                
                if ($sourceSelect.length > 0) {
                    var optionExists = $sourceSelect.find('option[value="' + sourceField + '"]').length > 0;
                    
                    if (optionExists) {
                        $sourceSelect.val(sourceField).trigger('change');
                        
                        // Track if SKU was mapped
                        if (targetWcField === 'sku') {
                            skuMapped = true;
                        }
                        
                        // Highlight
                        $row.css({
                            'background': '#e8f5e9',
                            'transition': 'background 0.3s'
                        });
                        setTimeout(function() {
                            $row.css('background', '');
                        }, 2000);
                        
                        appliedCount++;
                    }
                }
            }
        });
        
        // If SKU was not mapped, enable Auto-generate mode
        if (!skuMapped) {
            console.log('★★★ SKU not mapped - enabling Auto-generate mode');
            enableSkuAutoGenerate();
        }
        
        console.log('★★★ Applied', appliedCount, 'smart mappings');
        updateMappedCounts();
    }
    
    /**
     * Enable SKU Auto-generate mode when no SKU field is found in source
     */
    function enableSkuAutoGenerate() {
        var $skuRow = $('.field-mapping-row[data-field="sku"]');
        if ($skuRow.length === 0) {
            console.log('★★★ SKU row not found');
            return;
        }
        
        // Find the Auto-generate radio button
        var $autoGenerateRadio = $skuRow.find('.sku-mode-radio[value="generate"]');
        if ($autoGenerateRadio.length > 0) {
            $autoGenerateRadio.prop('checked', true).trigger('change');
            
            // Set default pattern if empty
            var $patternInput = $skuRow.find('.sku-pattern-input');
            if ($patternInput.length > 0 && !$patternInput.val()) {
                $patternInput.val('PROD-{row}');
            }
            
            // Highlight SKU row
            $skuRow.css({
                'background': '#fff3cd',
                'transition': 'background 0.3s'
            });
            setTimeout(function() {
                $skuRow.css('background', '');
            }, 3000);
            
            console.log('★★★ SKU Auto-generate enabled with pattern:', $patternInput.val());
        } else {
            console.log('★★★ SKU Auto-generate radio not found');
        }
    }
    
    /**
     * Show auto-mapping warning
     */
    function showAutoMappingWarning() {
        window.autoMappingUsed = true;
        window.autoMappingVerified = false;
        $('#auto-mapping-warning').slideDown(300);
        
        // Scroll to warning
        $('html, body').animate({
            scrollTop: $('#auto-mapping-warning').offset().top - 100
        }, 500);
    }
    
    /**
     * Auto-enable Manage Stock when Stock Quantity is mapped
     * This ensures logical consistency - if you have stock quantity, you should manage stock
     */
    function autoEnableManageStock() {
        var $manageStockRow = $('.field-mapping-row[data-field="manage_stock"]');
        if ($manageStockRow.length) {
            var $yesRadio = $manageStockRow.find('.boolean-mode-radio[value="yes"]');
            if ($yesRadio.length && !$yesRadio.prop('checked')) {
                $yesRadio.prop('checked', true).trigger('change');
                
                // Visual feedback - highlight the row briefly
                $manageStockRow.css('background-color', '#d4edda');
                setTimeout(function() {
                    $manageStockRow.css('background-color', '');
                }, 2000);
                
                console.log('Auto-enabled Manage Stock because Stock Quantity is mapped');
            }
        }
    }
    
    /**
     * Update the mapped field counts in section headers
     */
    function updateMappedCounts() {
        $('.mapping-section').each(function() {
            var $section = $(this);
            var total = $section.find('.field-mapping-row').length;
            var mapped = 0;
            
            $section.find('.field-source-select').each(function() {
                if ($(this).val() && $(this).val() !== '') {
                    mapped++;
                }
            });
            
            $section.find('.mapped-count').text(mapped + '/' + total);
        });
    }
    
    /**
     * Initialize Product Type Mode Selection (Simplified 3-Card UI)
     * 1. Simple  2. Attributes  3. Variable
     */
    function initializeProductTypeMode() {
        console.log('★★★ initializeProductTypeMode() CALLED - 3-Card UI ★★★');
        
        // Handle click on mode cards
        $('.mode-card').on('click', function(e) {
            console.log('★★★ Mode card clicked:', $(this).attr('id'));
            var $radio = $(this).find('input[type="radio"]');
            $radio.prop('checked', true).trigger('change');
        });
        
        // Handle mode card selection
        $('input[name="product_mode"]').on('change', function() {
            var mode = $(this).val();
            console.log('★★★ Product mode changed to:', mode);
            
            // Update card styling
            $('.mode-card').each(function() {
                $(this).css({
                    'background': '#f5f5f5',
                    'border-color': '#e0e0e0',
                    'box-shadow': 'none'
                }).find('strong, p').css('color', '');
            });
            
            // Highlight selected card
            var $selectedCard = $(this).closest('.mode-card');
            if (mode === 'simple') {
                $selectedCard.css({
                    'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    'border-color': 'transparent',
                    'box-shadow': '0 4px 15px rgba(102, 126, 234, 0.4)',
                    'color': 'white'
                }).find('strong, p').css('color', 'white');
            } else if (mode === 'attributes') {
                $selectedCard.css({
                    'background': 'linear-gradient(135deg, #43a047 0%, #2e7d32 100%)',
                    'border-color': 'transparent',
                    'box-shadow': '0 4px 15px rgba(46, 125, 50, 0.4)',
                    'color': 'white'
                }).find('strong, p').css('color', 'white');
            } else if (mode === 'variable') {
                $selectedCard.css({
                    'background': 'linear-gradient(135deg, #fb8c00 0%, #ef6c00 100%)',
                    'border-color': 'transparent',
                    'box-shadow': '0 4px 15px rgba(251, 140, 0, 0.4)',
                    'color': 'white'
                }).find('strong, p').css('color', 'white');
            }
            
            // Hide all panels
            $('.mode-panel').hide();
            
            // Show appropriate panel
            $('#panel-' + mode).show();
            
            // Update hidden field
            $('#variation_mode_hidden').val(mode);
        });
        
        // Add attribute button (for Attributes panel)
        $('#btn-add-attribute').on('click', function() {
            addAttributeRow('attributes-list');
        });
        
        // Add variation attribute button
        $('#btn-add-var-attribute').on('click', function() {
            addVariationAttributeRow();
        });
        
        // Add CSV variation attribute button
        $('#btn-add-csv-var-attribute').on('click', function() {
            addCsvVariationAttributeRow();
        });
        
        // Add variation meta button
        $('#btn-add-var-meta').on('click', function() {
            addVariationMetaRow();
        });
        
        // Remove attribute row
        $(document).on('click', '.remove-attr-row', function() {
            $(this).closest('.attr-row').remove();
        });
        
        // Initialize with current selection
        $('input[name="product_mode"]:checked').trigger('change');
    }
    
    /**
     * Add attribute row (for display attributes)
     */
    function addAttributeRow(targetContainer) {
        var index = $('#' + targetContainer + ' .attr-row').length;
        var fields = window.allKnownFieldsOrder || [];
        
        var options = '<option value="">-- Select Source Field --</option>';
        fields.forEach(function(field) {
            options += '<option value="' + field + '">' + field + '</option>';
        });
        
        var html = '<div class="attr-row" style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 12px; align-items: center; padding: 12px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 10px;">' +
            '<input type="text" name="attr[' + index + '][name]" placeholder="Attribute Name (e.g., Material)" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' +
            '<select name="attr[' + index + '][source]" class="field-source-select" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' + options + '</select>' +
            '<button type="button" class="button remove-attr-row" style="color: #d63638; padding: 8px 12px;">✕</button>' +
            '</div>';
        
        $('#' + targetContainer).append(html);
    }
    
    /**
     * Add variation attribute row
     */
    function addVariationAttributeRow() {
        var index = $('#variation-attributes-list .attr-row').length;
        var fields = window.allKnownFieldsOrder || [];
        
        var options = '<option value="">-- Select Source Field --</option>';
        fields.forEach(function(field) {
            options += '<option value="' + field + '">' + field + '</option>';
        });
        
        var html = '<div class="attr-row" style="display: grid; grid-template-columns: 140px 1fr 60px auto; gap: 10px; align-items: start; padding: 12px; background: #fff8e1; border: 1px solid #ffe082; border-radius: 6px; margin-bottom: 10px;">' +
            '<div>' +
                '<label style="font-size: 11px; color: #795548; display: block; margin-bottom: 4px;">Attribute Name *</label>' +
                '<input type="text" name="var_attr[' + index + '][name]" placeholder="e.g., Size" style="width: 100%; padding: 8px; border: 1px solid #ffcc80; border-radius: 4px;">' +
            '</div>' +
            '<div>' +
                '<label style="font-size: 11px; color: #795548; display: block; margin-bottom: 4px;">Value Source (from XML)</label>' +
                '<select name="var_attr[' + index + '][source]" class="field-source-select" style="width: 100%; padding: 8px; border: 1px solid #ffcc80; border-radius: 4px;">' + options + '</select>' +
                '<div style="font-size: 10px; color: #999; margin-top: 4px;">Or use Array Index below if values are in array like attribute[0], attribute[1]</div>' +
            '</div>' +
            '<div>' +
                '<label style="font-size: 11px; color: #795548; display: block; margin-bottom: 4px;">Array Index</label>' +
                '<input type="number" name="var_attr[' + index + '][array_index]" placeholder="0" min="0" style="width: 100%; padding: 8px; border: 1px solid #ffcc80; border-radius: 4px;">' +
            '</div>' +
            '<button type="button" class="button remove-attr-row" style="color: #d63638; padding: 8px 12px; margin-top: 18px;">✕</button>' +
            '</div>';
        
        $('#variation-attributes-list').append(html);
    }
    
    /**
     * Add CSV variation attribute row
     */
    function addCsvVariationAttributeRow() {
        var index = $('#csv-variation-attributes-list .attr-row').length;
        var fields = window.allKnownFieldsOrder || [];
        
        var options = '<option value="">-- Select Column --</option>';
        fields.forEach(function(field) {
            options += '<option value="' + field + '">' + field + '</option>';
        });
        
        var html = '<div class="attr-row" style="display: grid; grid-template-columns: 140px 1fr auto; gap: 10px; align-items: center; padding: 12px; background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 6px; margin-bottom: 10px;">' +
            '<div>' +
                '<label style="font-size: 11px; color: #2e7d32; display: block; margin-bottom: 4px;">Attribute Name *</label>' +
                '<input type="text" name="csv_var_attr[' + index + '][name]" placeholder="e.g., Size" style="width: 100%; padding: 8px; border: 1px solid #81c784; border-radius: 4px;">' +
            '</div>' +
            '<div>' +
                '<label style="font-size: 11px; color: #2e7d32; display: block; margin-bottom: 4px;">Source Column</label>' +
                '<select name="csv_var_attr[' + index + '][source]" class="field-source-select" style="width: 100%; padding: 8px; border: 1px solid #81c784; border-radius: 4px;">' + options + '</select>' +
            '</div>' +
            '<button type="button" class="button remove-attr-row" style="color: #d63638; padding: 8px 12px; margin-top: 18px;">✕</button>' +
            '</div>';
        
        $('#csv-variation-attributes-list').append(html);
    }
    
    /**
     * Add variation meta field row
     */
    function addVariationMetaRow() {
        var index = $('#variation-meta-list .meta-row').length;
        var fields = window.allKnownFieldsOrder || [];
        
        var options = '<option value="">-- Select Source Field --</option>';
        fields.forEach(function(field) {
            options += '<option value="' + field + '">' + field + '</option>';
        });
        
        var html = '<div class="meta-row" style="display: grid; grid-template-columns: 1fr 2fr auto; gap: 12px; align-items: center; padding: 12px; background: #e8eaf6; border: 1px solid #c5cae9; border-radius: 6px; margin-bottom: 10px;">' +
            '<input type="text" name="var_meta[' + index + '][key]" placeholder="Meta key (e.g., _ean)" style="padding: 10px; border: 1px solid #9fa8da; border-radius: 4px; font-family: monospace;">' +
            '<select name="var_meta[' + index + '][source]" class="field-source-select" style="padding: 10px; border: 1px solid #9fa8da; border-radius: 4px;">' + options + '</select>' +
            '<button type="button" class="button remove-attr-row" style="color: #d63638; padding: 8px 12px;">✕</button>' +
            '</div>';
        
        $('#variation-meta-list').append(html);
    }
    
    /**
     * Add a Display Attribute row
     */
    function addDisplayAttributeRow(targetContainer) {
        targetContainer = targetContainer || 'display-attributes-list';
        console.log('★★★ addDisplayAttributeRow() CALLED, target:', targetContainer);
        console.log('window.allKnownFieldsOrder:', window.allKnownFieldsOrder);
        
        var index = $('.display-attribute-row').length;
        console.log('Index:', index);
        
        var html = `
            <div class="display-attribute-row" data-index="${index}" style="display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px; padding: 10px; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px;">
                <div style="flex: 1;">
                    <label style="font-size: 12px; color: #666;">Attribute Name</label>
                    <input type="text" name="display_attr[${index}][name]" placeholder="e.g., Material" style="width: 100%;">
                </div>
                <div style="flex: 2;">
                    <label style="font-size: 12px; color: #666;">Values Source</label>
                    <select name="display_attr[${index}][source]" class="field-source-select" style="width: 100%;">
                        <option value="">-- Select Field --</option>
                    </select>
                </div>
                <div style="padding-top: 20px;">
                    <label style="cursor: pointer;">
                        <input type="checkbox" name="display_attr[${index}][visible]" value="1" checked>
                        Visible
                    </label>
                </div>
                <button type="button" class="button remove-display-attribute" style="padding-top: 18px; color: #d63638;">×</button>
            </div>
        `;
        
        $('#' + targetContainer).append(html);
        
        // Populate dropdown
        populateFieldSelectorsForRow($('#' + targetContainer + ' .display-attribute-row:last'));
    }
    
    // REMOVED: Old addVariationAttributeRow - now using new 3-card UI version (line 440)
    
    /**
     * Update variation count preview
     */
    function updateVariationPreview() {
        var total = 1;
        var descriptions = [];
        
        $('.variation-attribute-row').each(function() {
            var name = $(this).find('.variation-attribute-name').val() || 'Attribute';
            var values = $(this).find('.variation-attribute-values').val();
            
            if (values) {
                var valuesArr = values.split(',').map(function(v) { return v.trim(); }).filter(function(v) { return v; });
                if (valuesArr.length > 0) {
                    total *= valuesArr.length;
                    descriptions.push(valuesArr.length + ' ' + name);
                }
            }
        });
        
        if (descriptions.length > 0) {
            $('#variation-count-preview').html(
                '<strong>' + total + '</strong> variations will be generated (' + descriptions.join(' × ') + ')'
            );
        } else {
            $('#variation-count-preview').text('Add attributes to generate variations');
        }
    }
    
    /**
     * Populate field selectors for a specific row
     */
    function populateFieldSelectorsForRow($row) {
        $row.find('.field-source-select').each(function() {
            var $select = $(this);
            var currentVal = $select.val();
            
            // Keep first option
            $select.find('option:not(:first)').remove();
            
            // Add fields from known fields
            if (window.allKnownFieldsOrder && window.allKnownFieldsOrder.length > 0) {
                window.allKnownFieldsOrder.forEach(function(field) {
                    $select.append('<option value="' + field + '">' + field + '</option>');
                });
            }
            
            // Restore value
            if (currentVal) {
                $select.val(currentVal);
            }
        });
    }
    
    /**
     * Initialize Attributes & Variations functionality
     */
    function initializeAttributesAndVariations() {
        var attributeIndex = 0;
        
        // Add attribute button (legacy - keep for compatibility)
        $('#add-attribute').on('click', function() {
            addAttributeRow();
        });
        
        // Remove attribute button (delegated)
        $(document).on('click', '.remove-attribute', function() {
            $(this).closest('.attribute-row').remove();
            updateVariationModeVisibility();
        });
        
        // Variation mode change (legacy - now handled by product_type_mode)
        $('input[name="variation_mode"]').on('change', function() {
            var mode = $(this).val();
            if (mode === 'auto') {
                $('#auto-variation-config').show();
                $('#map-variation-config').hide();
            } else {
                $('#auto-variation-config').hide();
                $('#map-variation-config').show();
            }
        });
        
        // Variation status field mode change (Yes/No/Map radio buttons)
        $(document).on('change', 'input[name^="variation_"][name$="_mode"]', function() {
            var $row = $(this).closest('.var-status-field-row');
            var mode = $(this).val();
            var $mapField = $row.find('.var-status-map-field');
            
            if (mode === 'map') {
                $mapField.show();
            } else {
                $mapField.hide().val('');
            }
        });
        
        // Variation price mode change
        $('input[name="variation_price_mode"]').on('change', function() {
            var mode = $(this).val();
            $('#variation-price-map-config, #variation-price-formula-config').hide();
            if (mode === 'map') {
                $('#variation-price-map-config').show();
            } else if (mode === 'formula') {
                $('#variation-price-formula-config').show();
            }
        });
        
        // Variation stock mode change
        $('input[name="variation_stock_mode"]').on('change', function() {
            var mode = $(this).val();
            $('#variation-stock-fixed-config, #variation-stock-map-config, #variation-stock-formula-config').hide();
            if (mode === 'fixed') {
                $('#variation-stock-fixed-config').show();
            } else if (mode === 'map') {
                $('#variation-stock-map-config').show();
            } else if (mode === 'formula') {
                $('#variation-stock-formula-config').show();
            }
        });
        
        // Variation shipping class processing mode change
        $(document).on('change', '.var-shipping-class-processing', function() {
            var mode = $(this).val();
            var $container = $(this).closest('.var-shipping-class-field');
            
            // Hide all configs
            $container.find('.var-shipping-class-php-config, .var-shipping-class-ai-config, .var-shipping-class-hybrid-config').hide();
            
            // Show selected config
            if (mode === 'php_formula') {
                $container.find('.var-shipping-class-php-config').show();
            } else if (mode === 'ai_processing') {
                $container.find('.var-shipping-class-ai-config').show();
            } else if (mode === 'hybrid') {
                $container.find('.var-shipping-class-hybrid-config').show();
            }
        });
        
        // Attribute variation checkbox change - show/hide variation settings
        $(document).on('change', '.attribute-variation-checkbox', function() {
            var $checkbox = $(this);
            var $attributeRow = $checkbox.closest('.attribute-row');
            var $variationSettings = $attributeRow.find('.variation-attribute-settings');
            
            if ($checkbox.is(':checked')) {
                $variationSettings.slideDown(300);
                // Populate source field dropdowns in variation settings
                populateVariationSettingsFields($variationSettings);
            } else {
                $variationSettings.slideUp(300);
            }
            
            updateVariationModeVisibility();
        });
        
        // Variation attribute price type change
        $(document).on('change', '.var-price-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-price-operator-config, .var-price-map-config, .var-price-processing-config').hide();
            $settings.find('.var-price-processing-config .config-panel').hide();
            
            if (value === 'operator') {
                $settings.find('.var-price-operator-config').css('display', 'flex');
            } else if (value === 'map') {
                $settings.find('.var-price-map-config').css('display', 'flex');
            }
        });
        
        // Variation price processing mode change (Direct/PHP/AI/Hybrid)
        $(document).on('change', '.var-price-processing-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            var $processingConfig = $settings.find('.var-price-processing-config');
            
            $processingConfig.find('.config-panel').hide();
            
            if (value === 'direct') {
                $processingConfig.hide();
            } else {
                $processingConfig.show();
                if (value === 'php_formula') {
                    $processingConfig.find('.var-price-php-config').show();
                } else if (value === 'ai_processing') {
                    $processingConfig.find('.var-price-ai-config').show();
                } else if (value === 'hybrid') {
                    $processingConfig.find('.var-price-hybrid-config').show();
                }
            }
        });
        
        // Variation attribute stock type change
        $(document).on('change', '.var-stock-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-stock-operator-config, .var-stock-fixed-config, .var-stock-map-config').hide();
            if (value === 'operator') {
                $settings.find('.var-stock-operator-config').css('display', 'flex');
            } else if (value === 'fixed') {
                $settings.find('.var-stock-fixed-config').css('display', 'flex');
            } else if (value === 'map') {
                $settings.find('.var-stock-map-config').css('display', 'flex');
            }
        });
        
        // Variation attribute weight type change
        $(document).on('change', '.var-weight-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-weight-operator-config, .var-weight-map-config').hide();
            if (value === 'operator') {
                $settings.find('.var-weight-operator-config').css('display', 'flex');
            } else if (value === 'map') {
                $settings.find('.var-weight-map-config').css('display', 'flex');
            }
        });
        
        // Variation attribute shipping class type change
        $(document).on('change', '.var-shipping-class-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-shipping-class-map-config').hide();
            if (value === 'map') {
                $settings.find('.var-shipping-class-map-config').css('display', 'flex');
            }
        });
        
        // Variation attribute sale price type change
        $(document).on('change', '.var-sale-price-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-sale-price-fixed-config, .var-sale-price-map-config').hide();
            if (value === 'fixed') {
                $settings.find('.var-sale-price-fixed-config').css('display', 'flex');
            } else if (value === 'map') {
                $settings.find('.var-sale-price-map-config').css('display', 'flex');
            }
        });
        
        // Variation attribute SKU type change
        $(document).on('change', '.var-sku-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-sku-suffix-config, .var-sku-map-config').hide();
            if (value === 'suffix') {
                $settings.find('.var-sku-suffix-config').css('display', 'flex');
            } else if (value === 'map') {
                $settings.find('.var-sku-map-config').css('display', 'flex');
            }
        });
        
        // Variation attribute GTIN type change
        $(document).on('change', '.var-gtin-type-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            
            $settings.find('.var-gtin-map-config').hide();
            if (value === 'map') {
                $settings.find('.var-gtin-map-config').css('display', 'flex');
            }
        });
        
        // Variation description processing mode change (Direct/PHP/AI/Hybrid)
        $(document).on('change', '.var-description-processing-select', function() {
            var $settings = $(this).closest('.variation-attribute-settings');
            var value = $(this).val();
            var $processingConfig = $settings.find('.var-description-processing-config');
            
            $processingConfig.find('.config-panel').hide();
            
            if (value === 'direct') {
                $processingConfig.hide();
            } else {
                $processingConfig.show();
                if (value === 'php_formula') {
                    $processingConfig.find('.var-description-php-config').show();
                } else if (value === 'ai_processing') {
                    $processingConfig.find('.var-description-ai-config').show();
                } else if (value === 'hybrid') {
                    $processingConfig.find('.var-description-hybrid-config').show();
                }
            }
        });
        
        // Attribute values processing mode change
        $(document).on('change', '.attribute-values-processing', function() {
            var $row = $(this).closest('.attribute-row');
            var $configContainer = $row.find('.attribute-values-config');
            var value = $(this).val();
            
            // Hide all config panels first
            $configContainer.find('.config-panel').hide();
            
            if (value === 'direct') {
                $configContainer.hide();
            } else {
                $configContainer.show();
                if (value === 'php_formula') {
                    $configContainer.find('.php-formula-config').show();
                } else if (value === 'ai_processing') {
                    $configContainer.find('.ai-prompt-config').show();
                } else if (value === 'hybrid') {
                    $configContainer.find('.hybrid-config').show();
                }
            }
        });
        
        // Attribute image processing mode change
        $(document).on('change', '.attribute-image-processing', function() {
            var $row = $(this).closest('.attribute-row');
            var $configContainer = $row.find('.attribute-image-config');
            var value = $(this).val();
            
            // Hide all config panels first
            $configContainer.find('.config-panel').hide();
            
            if (value === 'direct') {
                $configContainer.hide();
            } else {
                $configContainer.show();
                if (value === 'php_formula') {
                    $configContainer.find('.php-formula-config').show();
                } else if (value === 'ai_processing') {
                    $configContainer.find('.ai-prompt-config').show();
                } else if (value === 'hybrid') {
                    $configContainer.find('.hybrid-config').show();
                }
            }
        });
        
        /**
         * Populate field source dropdowns in variation settings
         */
        function populateVariationSettingsFields($variationSettings) {
            var options = '<option value="">-- Select Field --</option>';
            
            var structure = Array.isArray(window.currentFileStructure) 
                ? window.currentFileStructure 
                : (window.currentFileStructure ? window.currentFileStructure.structure : null);
            
            if (structure && structure.length > 0) {
                structure.forEach(function(field) {
                    if (field.type !== 'object' && field.type !== 'array') {
                        options += '<option value="' + field.path + '">' + field.path + '</option>';
                    }
                });
            }
            
            $variationSettings.find('.field-source-select').html(options);
        }
        
        // SKU pattern preview update
        $('#variation-sku-pattern').on('input', function() {
            updateSkuPatternPreview();
        });
        
        /**
         * Add attribute row
         */
        function addAttributeRow() {
            var template = $('#attribute-row-template').html();
            var html = template.replace(/\{\{index\}\}/g, attributeIndex);
            $('#attributes-list').append(html);
            
            // Populate source field dropdown for new attribute
            populateAttributeSourceFields(attributeIndex);
            
            attributeIndex++;
            updateVariationModeVisibility();
        }
        
        /**
         * Populate source field dropdown for attribute
         */
        function populateAttributeSourceFields(index) {
            var $selectValues = $('.attribute-row[data-index="' + index + '"] .attribute-values-source');
            var $selectImage = $('.attribute-row[data-index="' + index + '"] .attribute-image-source');
            var options = '<option value="">-- Select Source Field --</option>';
            
            console.log('Populating attribute fields for index:', index);
            console.log('currentFileStructure:', window.currentFileStructure);
            
            // Check if currentFileStructure is directly an array OR has .structure property
            var structure = Array.isArray(window.currentFileStructure) 
                ? window.currentFileStructure 
                : (window.currentFileStructure ? window.currentFileStructure.structure : null);
            
            if (structure && structure.length > 0) {
                console.log('Structure has', structure.length, 'fields');
                structure.forEach(function(field) {
                    // Only show leaf nodes (text fields), not objects or arrays
                    if (field.type !== 'object' && field.type !== 'array') {
                        options += '<option value="' + field.path + '">' + field.path + '</option>';
                    }
                });
            } else {
                console.log('No file structure available yet');
            }
            
            console.log('Setting options for selects:', $selectValues.length, $selectImage.length);
            $selectValues.html(options);
            $selectImage.html(options);
        }
        
        /**
         * Update variation mode visibility based on attributes
         */
        function updateVariationModeVisibility() {
            var hasVariationAttributes = $('.attribute-variation-checkbox:checked').length > 0;
            if (hasVariationAttributes) {
                $('.variation-generation-section').slideDown();
            } else {
                $('.variation-generation-section').slideUp();
            }
        }
        
        /**
         * Update SKU pattern preview
         */
        function updateSkuPatternPreview() {
            var $patternInput = $('#variation-sku-pattern');
            if (!$patternInput.length) return; // Element doesn't exist in new UI
            
            var pattern = $patternInput.val() || '';
            var preview = pattern
                .replace(/\{parent_sku\}/g, 'PROD-123')
                .replace(/\{pa_size\}/g, 'L')
                .replace(/\{pa_color\}/g, 'Red')
                .replace(/\[\-\]/g, '-')
                .replace(/\[/g, '')
                .replace(/\]/g, '');
            $('#sku-pattern-preview').text('Preview: ' + preview);
        }
        
        // Initialize
        updateVariationModeVisibility();
        updateSkuPatternPreview();
    }

    /**
     * Step 3: Import Progress
     */
    function initializeStep3() {
        console.log('Initializing Step 3: Import Progress');

        // Start monitoring progress
        var importId = getImportIdFromUrl();
        if (importId) {
            monitorImportProgress(importId);
        }
    }

    /**
     * Setup drag and drop functionality
     */
    function setupDragAndDrop() {
        var $dropzone = $('#file-upload-area');

        $dropzone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        $dropzone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        $dropzone.on('drop', function(e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $('#file_upload')[0].files = files;
                $('#file_upload').trigger('change');
            }
        });
    }

    /**
     * Show file preview
     */
    function showFilePreview(file) {
        var fileName = file.name;
        var fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';

        $('#file-preview .file-name').text(fileName);
        $('#file-preview .file-size').text(fileSize);
        $('#file-preview').show();
    }

    /**
     * Get file extension from URL
     */
    function getExtensionFromUrl(url) {
        try {
            var path = new URL(url).pathname;
            return path.split('.').pop().toLowerCase();
        } catch (e) {
            return '';
        }
    }

    /**
     * Test URL functionality
     */
    function testUrl() {
        var url = $('#file_url').val();
        var $button = $(this);
        var $result = $('#url-test-result');

        if (!url) {
            showMessage($result, 'Please enter a URL first.', 'error');
            return;
        }

        $button.prop('disabled', true).text(wc_xml_csv_ai_import_ajax.strings.test_ai);

        // Simple URL validation
        try {
            new URL(url);
            showMessage($result, '✅ URL format is valid.', 'success');
        } catch (e) {
            showMessage($result, '❌ Invalid URL format.', 'error');
        }

        setTimeout(function() {
            $button.prop('disabled', false).text('Test URL');
        }, 1000);
    }

    /**
     * Handle upload form submission
     */
    function handleUploadSubmission(e) {
        e.preventDefault();

        // Validation
        if (!$('#import_name').val().trim()) {
            showMessage($('#upload-messages'), 'Please enter an import name.', 'error');
            return;
        }

        var uploadMethod = $('input[name="upload_method"]:checked').val();

        if (uploadMethod === 'file' && !$('#file_upload')[0].files.length) {
            showMessage($('#upload-messages'), 'Please select a file to upload.', 'error');
            return;
        }

        if (uploadMethod === 'url' && !$('#file_url').val().trim()) {
            showMessage($('#upload-messages'), 'Please enter a file URL.', 'error');
            return;
        }

        // Submit form
        var formData = new FormData(this);
        formData.append('action', 'wc_xml_csv_ai_import_upload_file');
        formData.append('nonce', wc_xml_csv_ai_import_ajax.nonce);

        var $button = $('#proceed-mapping');
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + wc_xml_csv_ai_import_ajax.strings.uploading);

        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 600000, // 10 minutes for large file parsing
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                return xhr;
            },
            beforeSend: function() {
                $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>Uploading and analyzing file...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    var message = response.data.message;
                    if (response.data.total_products) {
                        message += ' Found ' + response.data.total_products + ' products.';
                    }
                    showMessage($('#upload-messages'), message, 'success');
                    
                    $button.html('✓ Analysis complete! Redirecting...');
                    
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1000);
                } else {
                    showMessage($('#upload-messages'), response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showMessage($('#upload-messages'), 'File upload and analysis timed out. The file might be too large. Please try with a smaller file.', 'error');
                } else {
                    showMessage($('#upload-messages'), 'An error occurred: ' + error, 'error');
                }
            },
            complete: function() {
                if (!$('#upload-messages .notice-success').length) {
                    $button.prop('disabled', false).html('Proceed to Field Mapping <span class="button-icon">➡️</span>');
                }
            }
        });
    }

    /**
     * Load file structure
     */
    function loadFileStructure() {
        // Show total products immediately if available from upload
        if (wcAiImportData.total_products && wcAiImportData.total_products > 0) {
            $('#total-products-count').text(wcAiImportData.total_products);
            $('#total-products-info').show();
        }
        
        // Show loading message
        var $container = $('#file-structure-browser');
        $container.html('<div class="structure-loader" style="text-align: center; padding: 40px;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div><p style="margin-top: 10px; font-size: 14px; color: #666;">Loading product structure...</p></div>');
        
        var data = {
            action: 'wc_xml_csv_ai_import_parse_structure',
            file_path: wcAiImportData.file_path,
            file_type: wcAiImportData.file_type,
            product_wrapper: wcAiImportData.product_wrapper,
            total_products: wcAiImportData.total_products, // Pass from upload
            page: currentPage,
            per_page: 1, // Show 1 product per page for navigation
            nonce: wcAiImportData.nonce
        };

        console.log('=== AJAX Request ===', data);
        console.log('📤 Requesting page:', data.page, 'per_page:', data.per_page);

        $.ajax({
            url: wcAiImportData.ajax_url,
            type: 'POST',
            data: data,
            timeout: 600000, // 10 minutes timeout (matching upload timeout)
            success: function(response) {
                console.log('=== AJAX Response ===', response);
                console.log('📥 Received page:', response.data.current_page, 'Structure fields:', response.data.structure ? response.data.structure.length : 0);
                if (response.data.structure && response.data.structure.length > 0) {
                    console.log('📥 First field:', response.data.structure[0].path, '=', response.data.structure[0].sample);
                }
                if (response.success) {
                    console.log('Total products from backend:', response.data.total_products);
                    console.log('Total pages from backend:', response.data.total_pages);
                    console.log('Fields scanned from products:', response.data.fields_scanned_from);
                    
                    window.currentFileStructure = response.data.structure;
                    window.currentSampleData = response.data.sample_data;
                    totalPages = response.data.total_pages || 1;

                    // Update total products count in UI
                    if (response.data.total_products) {
                        $('#total-products-count').text(response.data.total_products);
                        $('#total-products-info').css('display', 'flex');
                    }
                    
                    // Show fields scanned info in dedicated container
                    if (response.data.fields_scanned_from) {
                        var fieldCount = response.data.structure ? response.data.structure.length : 0;
                        $('#fields-info-container').html(
                            '<div style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: linear-gradient(135deg, #e7f3ff, #f0f7ff); border-radius: 6px; border: 1px solid #b8daff; margin-bottom: 15px;">' +
                            '<span style="font-size: 18px;">📊</span>' +
                            '<div style="display: flex; flex-direction: column; gap: 1px;">' +
                            '<span style="font-size: 13px; font-weight: 600; color: #0073aa;">' + fieldCount + ' unique fields</span>' +
                            '<span style="font-size: 10px; color: #666;">scanned from ' + response.data.fields_scanned_from + ' products</span>' +
                            '</div>' +
                            '</div>'
                        );
                    }

                    displayFileStructure(response.data);
                    displaySampleData(response.data.sample_data);
                    updatePagination(response.data);
                    populateSourceFields(response.data.structure);
                    
                    // Populate filter dropdowns with file structure fields
                    populateFilterDropdowns();
                    
                    // Populate existing filter dropdowns if function exists (edit page)
                    if (typeof window.populateExistingFilterDropdowns === 'function') {
                        window.populateExistingFilterDropdowns();
                    }
                    
                    // Re-apply saved CSV variation mappings after dropdowns are populated
                    if (typeof wcAiImportData !== 'undefined' && wcAiImportData.saved_mappings && wcAiImportData.saved_mappings.attributes_variations) {
                        var csvConfig = wcAiImportData.saved_mappings.attributes_variations.csv_variation_config;
                        if (csvConfig) {
                            console.log('★★★ Re-applying CSV variation config after dropdowns loaded:', csvConfig);
                            
                            // Restore parent SKU and type columns
                            if (csvConfig.parent_sku_column) {
                                $('#csv-parent-sku-column').val(csvConfig.parent_sku_column);
                            }
                            if (csvConfig.type_column) {
                                $('#csv-type-column').val(csvConfig.type_column);
                            }
                            
                            // Restore CSV variation field mappings
                            if (csvConfig.fields) {
                                Object.keys(csvConfig.fields).forEach(function(fieldKey) {
                                    $('select[name="csv_var_field[' + fieldKey + ']"]').val(csvConfig.fields[fieldKey]);
                                });
                            }
                            
                            // Restore CSV variation attributes (only if not already restored)
                            if (csvConfig.attributes && csvConfig.attributes.length > 0) {
                                // Check if already has correct number of attributes
                                var existingCount = $('#csv-variation-attributes-list .attr-row').length;
                                if (existingCount !== csvConfig.attributes.length) {
                                    // Clear and re-add to ensure correct state
                                    $('#csv-variation-attributes-list').empty();
                                    csvConfig.attributes.forEach(function(attr) {
                                        if (attr.name) {
                                            addCsvVariationAttributeRow();
                                            var $row = $('#csv-variation-attributes-list .attr-row').last();
                                            $row.find('input[name$="[name]"]').val(attr.name);
                                            $row.find('select[name$="[source]"]').val(attr.source || '');
                                        }
                                    });
                                } else {
                                    // Just update values in existing rows
                                    csvConfig.attributes.forEach(function(attr, idx) {
                                        var $row = $('#csv-variation-attributes-list .attr-row').eq(idx);
                                        if ($row.length && attr.name) {
                                            $row.find('select[name$="[source]"]').val(attr.source || '');
                                        }
                                    });
                                }
                            }
                        }
                    }
                    
                    // CRITICAL: Load saved mappings AFTER dropdowns are populated
                    if (typeof wcAiImportData !== 'undefined' && wcAiImportData.saved_mappings) {
                        console.log('★★★ LOADING SAVED MAPPINGS (after dropdowns populated) ★★★');
                        loadSavedMappings(wcAiImportData.saved_mappings);
                    }
                } else {
                    showMessage($('#mapping-messages'), response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showMessage($('#mapping-messages'), 'File scanning timed out. The file might be too large. Please try with a smaller file or contact support.', 'error');
                } else {
                    showMessage($('#mapping-messages'), 'Failed to load file structure: ' + error, 'error');
                }
            },
            complete: function() {
                $('.structure-loader').hide();
            }
        });
    }

    /**
     * Display file structure
     */
    function displayFileStructure(data) {
        var $container = $('#file-structure-browser');
        var html = '';
        
        // Add product navigation if we have any products
        if (data.current_page && data.total_pages) {
            html += '<div class="product-navigation" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e2e4e7;">';
            
            // Row 1: Prev/Next buttons
            html += '<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">';
            html += '<button type="button" class="button button-small" id="prev-product" ' + (data.current_page <= 1 ? 'disabled' : '') + '>← Prev</button>';
            html += '<span style="font-size: 13px; font-weight: 600; color: #1e1e1e;">Product ' + data.current_page + ' of ' + data.total_pages + '</span>';
            html += '<button type="button" class="button button-small" id="next-product" ' + (data.current_page >= data.total_pages ? 'disabled' : '') + '>Next →</button>';
            html += '</div>';
            
            // Row 2: Go to product input
            html += '<div style="display: flex; align-items: center; justify-content: center; gap: 8px; padding-top: 8px; border-top: 1px solid #e2e4e7;">';
            html += '<label style="font-size: 12px; color: #666; margin: 0;">Go to product:</label>';
            html += '<input type="number" id="goto-product-input" min="1" max="' + data.total_pages + '" value="' + data.current_page + '" style="width: 60px; text-align: center; padding: 4px 8px; border: 1px solid #8c8f94; border-radius: 4px;">';
            html += '<button type="button" class="button button-primary button-small" id="goto-product-btn" style="padding: 4px 12px;">Go</button>';
            html += '</div>';
            
            html += '</div>';
        }
        
        // Display product data in clean Sample Data format (not raw field list)
        html += '<div class="product-data-preview" style="background: #fff; border: 1px solid #e2e4e7; border-radius: 6px; padding: 15px; max-height: 500px; overflow-y: auto;">';
        
        if (data.sample_data && data.sample_data.length > 0) {
            var product = data.sample_data[0]; // Show first (current) product
            
            // Group fields by category for better organization
            var basicFields = ['sku', 'name', 'description', 'short_description', 'status'];
            var pricingFields = ['regular_price', 'sale_price', 'sale_price_dates_from', 'sale_price_dates_to', 'tax_status', 'tax_class'];
            var inventoryFields = ['manage_stock', 'stock_quantity', 'stock_status', 'backorders', 'sold_individually'];
            var shippingFields = ['weight', 'length', 'width', 'height', 'shipping_class'];
            var identifierFields = ['ean', 'upc', 'gtin', 'isbn', 'mpn'];
            
            // Render fields in clean format
            var renderedFields = {};
            var expandableId = 0;
            
            // Helper function to render nested object fields
            function renderNestedObject(obj, parentKey, level) {
                var nestedHtml = '';
                var indent = (level || 1) * 15;
                
                if (Array.isArray(obj)) {
                    obj.forEach(function(item, idx) {
                        if (typeof item === 'object' && item !== null) {
                            nestedHtml += '<div style="margin-left: ' + indent + 'px; margin-top: 5px; padding: 5px; background: #f8f9fa; border-radius: 3px; border-left: 2px solid #0073aa;">';
                            nestedHtml += '<div style="font-size: 11px; font-weight: 600; color: #0073aa; margin-bottom: 4px;">[' + idx + ']</div>';
                            for (var k in item) {
                                if (item.hasOwnProperty(k)) {
                                    var val = item[k];
                                    if (typeof val === 'object' && val !== null) {
                                        nestedHtml += '<div style="margin-left: 10px;">';
                                        nestedHtml += '<span style="color: #666; font-size: 11px; font-weight: 500;">' + k + ':</span>';
                                        nestedHtml += renderNestedObject(val, k, (level || 1) + 1);
                                        nestedHtml += '</div>';
                                    } else {
                                        nestedHtml += '<div style="display: flex; gap: 8px; margin-left: 10px; padding: 2px 0;">';
                                        nestedHtml += '<span style="min-width: 100px; color: #666; font-size: 11px;">' + k + '</span>';
                                        nestedHtml += '<span style="color: #1e1e1e; font-size: 11px;">' + truncateText(String(val || '—'), 40) + '</span>';
                                        nestedHtml += '</div>';
                                    }
                                }
                            }
                            nestedHtml += '</div>';
                        } else {
                            nestedHtml += '<div style="margin-left: ' + indent + 'px; font-size: 11px; color: #50575e;">[' + idx + '] ' + truncateText(String(item), 50) + '</div>';
                        }
                    });
                } else if (typeof obj === 'object' && obj !== null) {
                    for (var k in obj) {
                        if (obj.hasOwnProperty(k)) {
                            var val = obj[k];
                            if (typeof val === 'object' && val !== null) {
                                nestedHtml += '<div style="margin-left: ' + indent + 'px; margin-top: 3px;">';
                                nestedHtml += '<span style="color: #666; font-size: 11px; font-weight: 500;">' + k + ':</span>';
                                nestedHtml += renderNestedObject(val, k, (level || 1) + 1);
                                nestedHtml += '</div>';
                            } else {
                                nestedHtml += '<div style="display: flex; gap: 8px; margin-left: ' + indent + 'px; padding: 2px 0;">';
                                nestedHtml += '<span style="min-width: 100px; color: #666; font-size: 11px;">' + k + '</span>';
                                nestedHtml += '<span style="color: #1e1e1e; font-size: 11px;">' + truncateText(String(val || '—'), 40) + '</span>';
                                nestedHtml += '</div>';
                            }
                        }
                    }
                }
                return nestedHtml;
            }
            
            // Helper function to render a field
            function renderField(key, value) {
                if (renderedFields[key]) return '';
                renderedFields[key] = true;
                
                var displayValue = value;
                var isExpandable = false;
                var currentExpandId = '';
                
                if (typeof value === 'object' && value !== null) {
                    isExpandable = true;
                    currentExpandId = 'expand-' + (++expandableId);
                    if (Array.isArray(value)) {
                        displayValue = '<span class="expandable-toggle" data-target="' + currentExpandId + '" style="color: #0073aa; cursor: pointer; font-style: italic; white-space: nowrap;">▶ ' + value.length + ' items <span style="font-size: 10px;">(click to expand)</span></span>';
                    } else {
                        var objKeys = Object.keys(value);
                        displayValue = '<span class="expandable-toggle" data-target="' + currentExpandId + '" style="color: #0073aa; cursor: pointer; font-style: italic; white-space: nowrap;">▶ Object (' + objKeys.length + ' fields) <span style="font-size: 10px;">(click)</span></span>';
                    }
                } else if (value === null || value === undefined || value === '') {
                    displayValue = '<span style="color: #999;">—</span>';
                } else {
                    displayValue = truncateText(String(value), 60);
                }
                
                var fieldHtml = '<div style="display: flex; flex-direction: column; padding: 4px 0; border-bottom: 1px solid #f0f0f0;">' +
                    '<div style="display: flex; gap: 10px;">' +
                    '<span style="min-width: 140px; flex-shrink: 0; color: #1e1e1e; font-weight: 500; font-size: 12px;">' + key + '</span>' +
                    '<span style="color: #50575e; font-size: 12px; word-break: break-word; flex: 1;">' + displayValue + '</span>' +
                    '</div>';
                
                if (isExpandable) {
                    fieldHtml += '<div id="' + currentExpandId + '" class="expandable-content" style="display: none; margin-top: 5px; max-height: 300px; overflow-y: auto;">';
                    fieldHtml += renderNestedObject(value, key, 1);
                    fieldHtml += '</div>';
                }
                
                fieldHtml += '</div>';
                return fieldHtml;
            }
            
            // Render grouped fields
            function renderGroup(title, fields, icon) {
                var groupHtml = '';
                var hasFields = false;
                
                fields.forEach(function(key) {
                    if (product.hasOwnProperty(key) && product[key] !== null && product[key] !== undefined && product[key] !== '') {
                        hasFields = true;
                    }
                });
                
                if (!hasFields) return '';
                
                groupHtml += '<div style="margin-bottom: 12px;">';
                groupHtml += '<div style="font-size: 11px; text-transform: uppercase; color: #666; font-weight: 600; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 2px solid #0073aa;">' + icon + ' ' + title + '</div>';
                
                fields.forEach(function(key) {
                    if (product.hasOwnProperty(key)) {
                        groupHtml += renderField(key, product[key]);
                    }
                });
                
                groupHtml += '</div>';
                return groupHtml;
            }
            
            html += renderGroup('Basic Info', basicFields, '📋');
            html += renderGroup('Pricing', pricingFields, '💰');
            html += renderGroup('Inventory', inventoryFields, '📦');
            html += renderGroup('Shipping', shippingFields, '🚚');
            html += renderGroup('Identifiers', identifierFields, '🏷️');
            
            // Render remaining fields (not in predefined groups)
            var otherFieldsHtml = '';
            for (var key in product) {
                if (product.hasOwnProperty(key) && !renderedFields[key]) {
                    // Skip complex nested objects for cleaner display
                    if (key.indexOf('.') === -1 && key.indexOf('[') === -1) {
                        otherFieldsHtml += renderField(key, product[key]);
                    }
                }
            }
            
            if (otherFieldsHtml) {
                html += '<div style="margin-bottom: 12px;">';
                html += '<div style="font-size: 11px; text-transform: uppercase; color: #666; font-weight: 600; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 2px solid #0073aa;">📁 Other Fields</div>';
                html += otherFieldsHtml;
                html += '</div>';
            }
            
            // Show nested fields count
            var nestedCount = 0;
            for (var key in product) {
                if (product.hasOwnProperty(key) && !renderedFields[key]) {
                    nestedCount++;
                }
            }
            if (nestedCount > 0) {
                html += '<div style="margin-top: 10px; padding: 8px; background: #f0f0f0; border-radius: 4px; font-size: 11px; color: #666;">';
                html += '📂 + ' + nestedCount + ' nested/complex fields (attributes, variations, etc.) available in dropdown';
                html += '</div>';
            }
            
        } else {
            html += '<p style="color: #666; text-align: center; padding: 20px;">No product data available</p>';
        }
        
        html += '</div>';
        
        $container.html(html);
        
        // Expandable toggle handler for Object/Array fields
        $container.find('.expandable-toggle').off('click').on('click', function() {
            var targetId = $(this).data('target');
            var $content = $('#' + targetId);
            if ($content.is(':visible')) {
                $content.slideUp(200);
                $(this).html($(this).html().replace('▼', '▶'));
            } else {
                $content.slideDown(200);
                $(this).html($(this).html().replace('▶', '▼'));
            }
        });
        
        // Product navigation handlers - use event delegation or find within container
        $container.find('#prev-product').off('click').on('click', function() {
            console.log('🔵 Prev Product clicked - currentPage before:', currentPage);
            if (currentPage > 1) {
                currentPage--;
                console.log('🔵 New currentPage:', currentPage, '- Loading structure...');
                loadFileStructure();
            }
        });
        
        $container.find('#next-product').off('click').on('click', function() {
            console.log('🟢 Next Product clicked - currentPage before:', currentPage, 'totalPages:', totalPages);
            if (currentPage < totalPages) {
                currentPage++;
                console.log('🟢 New currentPage:', currentPage, '- Loading structure...');
                loadFileStructure();
            } else {
                console.log('🔴 Already at last page');
            }
        });
        
        // Go to specific product handler
        $container.find('#goto-product-btn').off('click').on('click', function() {
            var targetPage = parseInt($container.find('#goto-product-input').val(), 10);
            console.log('🎯 Go to product clicked - target:', targetPage, 'totalPages:', totalPages);
            if (targetPage >= 1 && targetPage <= totalPages && targetPage !== currentPage) {
                currentPage = targetPage;
                console.log('🎯 Jumping to product:', currentPage);
                loadFileStructure();
            }
        });
        
        // Allow Enter key in input field
        $container.find('#goto-product-input').off('keypress').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $container.find('#goto-product-btn').click();
            }
        });
    }

    /**
     * Display sample data
     */
    function displaySampleData(sampleData) {
        var $container = $('#sample-data-preview');
        var html = '';

        if (sampleData && sampleData.length > 0) {
            sampleData.forEach(function(product, index) {
                html += '<div class="sample-product">';
                html += '<strong>Product ' + (index + 1) + ':</strong><br>';
                
                for (var key in product) {
                    if (product.hasOwnProperty(key)) {
                        html += '<div>';
                        html += '<span class="field-name">' + key + ':</span>';
                        html += '<span class="field-value">' + truncateText(String(product[key]), 50) + '</span>';
                        html += '</div>';
                    }
                }
                
                html += '</div>';
            });
        } else {
            html = '<p>No sample data available.</p>';
        }

        $container.html(html);
    }

    /**
     * Update pagination
     */
    function updatePagination(data) {
        var $pagination = $('#structure-pagination');
        var $pageInput = $('#current-page-input');
        var $totalPagesDisplay = $('#total-pages-display');
        var $prevBtn = $('#prev-page');
        var $nextBtn = $('#next-page');

        // Update display
        $pageInput.val(data.current_page);
        $pageInput.attr('max', data.total_pages);
        $totalPagesDisplay.text(data.total_pages);
        
        // Update button states
        $prevBtn.prop('disabled', data.current_page <= 1);
        $nextBtn.prop('disabled', data.current_page >= data.total_pages);

        $pagination.show();

        // Previous button
        $prevBtn.off('click').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadFileStructure();
            }
        });

        // Next button
        $nextBtn.off('click').on('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                loadFileStructure();
            }
        });

        // Page input - jump to page on Enter
        $pageInput.off('keypress').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                var newPage = parseInt($(this).val());
                if (newPage >= 1 && newPage <= totalPages && newPage !== currentPage) {
                    currentPage = newPage;
                    loadFileStructure();
                } else {
                    // Reset to current page if invalid
                    $(this).val(currentPage);
                }
            }
        });

        // Page input - also trigger on blur (focus out)
        $pageInput.off('blur').on('blur', function() {
            var newPage = parseInt($(this).val());
            if (newPage >= 1 && newPage <= totalPages && newPage !== currentPage) {
                currentPage = newPage;
                loadFileStructure();
            } else {
                // Reset to current page if invalid
                $(this).val(currentPage);
            }
        });
    }

    /**
     * Populate source field dropdowns - MERGES new fields with existing ones
     * This ensures mappings are preserved when switching between products
     */
    function populateSourceFields(structure) {
        // First, save current mapping selections before updating dropdowns
        var savedSelections = {};
        $('.field-source-select').each(function() {
            var $select = $(this);
            var fieldName = $select.attr('name');
            var currentValue = $select.val();
            if (currentValue) {
                savedSelections[fieldName] = currentValue;
            }
        });
        
        // Merge new fields into allKnownFields (don't replace!)
        // Also maintain original order in allKnownFieldsOrder array
        if (structure && structure.length > 0) {
            structure.forEach(function(field) {
                // Add ALL field types including objects and arrays for attribute/variation mapping
                var path = field.path;
                if (!window.allKnownFields[path]) {
                    window.allKnownFields[path] = field;
                    // Add to order array (maintains original XML sequence)
                    window.allKnownFieldsOrder.push(path);
                } else if (!window.allKnownFields[path].sample && field.sample) {
                    // Update sample value if we have one now
                    window.allKnownFields[path].sample = field.sample;
                }
            });
        }
        
        // Build options from ALL known fields (in ORIGINAL XML order, not alphabetically)
        var options = '<option value="">-- Select Source Field --</option>';
        
        window.allKnownFieldsOrder.forEach(function(path) {
            options += '<option value="' + path + '">' + path + '</option>';
        });

        // Update all dropdowns
        $('.field-source-select').html(options);
        $('.attribute-values-source').html(options);
        $('.attribute-image-source').html(options);
        
        // Update ALL variation mapping dropdowns (basic list)
        var variationDropdowns = [
            'variation_price_field', 'variation_stock_field',
            'variation_map_sku', 'variation_map_gtin', 'variation_map_description',
            'variation_map_price', 'variation_map_sale_price', 
            'variation_map_sale_date_from', 'variation_map_sale_date_to',
            'variation_map_stock', 'variation_map_low_stock_amount',
            'variation_map_weight', 'variation_map_length', 'variation_map_width', 'variation_map_height',
            'variation_map_shipping_class', 'variation_map_image',
            // Map from field dropdowns for status fields
            'variation_map_enabled_field', 'variation_map_virtual_field', 
            'variation_map_downloadable_field', 'variation_map_manage_stock_field',
            'variation_map_stock_status_field', 'variation_map_backorders_field'
        ];
        variationDropdowns.forEach(function(name) {
            $('select[name="' + name + '"]').html(options);
        });
        
        // Restore saved selections (CRITICAL - prevents losing mappings!)
        Object.keys(savedSelections).forEach(function(fieldName) {
            var $select = $('select[name="' + fieldName + '"]');
            if ($select.length) {
                $select.val(savedSelections[fieldName]);
            }
        });
        
        // Also restore from existing mappings (edit mode - first load only)
        if (typeof wcAiImportData !== 'undefined' && wcAiImportData.existing_mappings && !window._mappingsRestored) {
            console.log('Restoring existing mappings:', wcAiImportData.existing_mappings);
            $('.field-mapping-row').each(function() {
                var $row = $(this);
                var fieldKey = $row.data('field');
                var mapping = wcAiImportData.existing_mappings[fieldKey];
                
                if (mapping && mapping.source) {
                    var $select = $row.find('.field-source-select');
                    $select.val(mapping.source);
                    console.log('Restored', fieldKey, '→', mapping.source);
                }
            });
            window._mappingsRestored = true;
        }
        
        // Update mapped counts
        updateMappedCounts();
        
        // Show info about discovered fields
        var fieldCount = Object.keys(window.allKnownFields).length;
        console.log('📊 Total unique fields discovered: ' + fieldCount);
    }

    /**
     * Clear all mapping
     */
    function clearAllMapping() {
        if (confirm('Are you sure you want to clear all field mappings?')) {
            $('.field-source-select').val('');
            $('.processing-mode-select').val('direct').trigger('change');
            $('textarea, input[type="text"]').val('');
            updateMappedCounts();
            showMessage($('#mapping-messages'), 'All mappings cleared.', 'info');
        }
    }

    /**
     * Test mapping with sample data
     */
    function testMapping() {
        if (!window.currentSampleData || window.currentSampleData.length === 0) {
            showMessage($('#mapping-messages'), 'No sample data available for testing.', 'warning');
            return;
        }

        // Get first sample product
        var sampleProduct = window.currentSampleData[0];
        var results = [];

        $('.field-mapping-row').each(function() {
            var $row = $(this);
            var fieldKey = $row.data('field');
            var sourceField = $row.find('.field-source-select').val();

            if (sourceField && sampleProduct[sourceField] !== undefined) {
                results.push({
                    field: fieldKey,
                    source: sourceField,
                    value: sampleProduct[sourceField]
                });
            }
        });

        // Display test results
        var html = '<h4>Test Results (using first sample product):</h4>';
        html += '<table class="widefat"><thead><tr><th>WC Field</th><th>Source Field</th><th>Sample Value</th></tr></thead><tbody>';

        results.forEach(function(result) {
            html += '<tr>';
            html += '<td>' + result.field + '</td>';
            html += '<td>' + result.source + '</td>';
            html += '<td>' + truncateText(String(result.value), 50) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        showMessage($('#mapping-messages'), html, 'info', true);
    }

    /**
     * Add filter rule
     */
    function addFilterRule(e) {
        console.log('★★★ addFilterRule() CALLED ★★★');
        if (e) e.preventDefault();
        
        var $container = $('#import-filters-container');
        console.log('Container found:', $container.length);
        
        var $noFilters = $container.find('.no-filters');
        var $logicToggle = $('#filter-logic-toggle');
        var $filterNote = $('#filter-options-note');
        var template = $('#filter-rule-template').html();
        console.log('Template found:', template ? 'yes' : 'no');
        
        var index = Date.now(); // Simple unique index

        var html = template.replace(/\{index\}/g, index);
        $container.append(html);
        console.log('Filter row added');

        if ($noFilters.length) {
            $noFilters.hide();
        }

        // Show filter logic toggle if more than one filter
        if ($container.find('.filter-rule-row').length > 1) {
            $logicToggle.show();
        }

        // Show filter options note when filters exist
        if ($container.find('.filter-rule-row').length > 0) {
            $filterNote.show();
        }

        // Populate field options for new filter
        if (window.currentFileStructure) {
            populateFilterFieldOptions(index);
        }
    }

    /**
     * Remove filter rule
     */
    function removeFilterRule() {
        var $row = $(this).closest('.filter-rule-row');
        var $container = $('#import-filters-container');
        var $logicToggle = $('#filter-logic-toggle');
        var $filterNote = $('#filter-options-note');
        
        $row.remove();

        // Hide filter logic toggle if only one filter remains
        if ($container.find('.filter-rule-row').length <= 1) {
            $logicToggle.hide();
        }

        // Hide filter options note if no filters remain
        if ($container.find('.filter-rule-row').length === 0) {
            $filterNote.hide();
        }

        // Show "no filters" message if all removed
        if ($container.find('.filter-rule-row').length === 0) {
            $container.find('.no-filters').show();
        }
    }

    /**
     * Populate filter field dropdown options
     */
    function populateFilterFieldOptions(index) {
        var options = '<option value="">-- Select Field --</option>';
        
        if (window.currentFileStructure && window.currentFileStructure.length > 0) {
            window.currentFileStructure.forEach(function(field) {
                options += '<option value="' + field.path + '">' + field.path + '</option>';
            });
        }

        $('select[name="import_filters[' + index + '][field]"]').html(options);
    }

    /**
     * Add custom field
     */
    function addCustomField(e) {
        // Stop event from bubbling to section toggle
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        var $container = $('#custom-fields-container');
        var $noFields = $container.find('.no-custom-fields');
        var template = $('#custom-field-template').html();
        var index = Date.now(); // Simple unique index

        var html = template.replace(/\{index\}/g, index);
        $container.append(html);

        if ($noFields.length) {
            $noFields.hide();
        }

        // Populate source fields for new custom field
        if (window.currentFileStructure) {
            populateCustomFieldSources(index);
        }
    }

    /**
     * Remove custom field
     */
    function removeCustomField() {
        var $row = $(this).closest('.custom-field-row');
        $row.remove();

        var $container = $('#custom-fields-container');
        if ($container.find('.custom-field-row').length === 0) {
            $container.find('.no-custom-fields').show();
        }
    }

    /**
     * Populate custom field source dropdown
     */
    function populateCustomFieldSources(index) {
        var options = '<option value="">-- Select Source Field --</option>';
        
        if (window.currentFileStructure && window.currentFileStructure.length > 0) {
            window.currentFileStructure.forEach(function(field) {
                options += '<option value="' + field.path + '">' + field.path + '</option>';
            });
        }

        $('select[name="custom_fields\\[' + index + '\\]\\[source\\]"]').html(options);
    }
    
    /**
     * Load saved field mappings into UI
     */
    function loadSavedMappings(savedMappings) {
        console.log('★★★ loadSavedMappings called with:', savedMappings);
        
        if (!savedMappings || typeof savedMappings !== 'object') {
            console.log('No saved mappings to load');
            return;
        }
        
        // Iterate through saved mappings
        $.each(savedMappings, function(fieldName, fieldConfig) {
            // Skip custom fields (they have 'custom_' prefix)
            if (fieldName.indexOf('custom_') === 0) {
                return; // continue
            }
            
            console.log('Loading mapping for field:', fieldName, fieldConfig);
            
            // Special handling for shipping_class_formula
            if (fieldName === 'shipping_class_formula') {
                if (fieldConfig.formula) {
                    $('textarea[name="field_mapping\\[shipping_class_formula\\]\\[formula\\]"]').val(fieldConfig.formula);
                    console.log('Loaded shipping class formula');
                }
                return;
            }
            
            // Find the dropdown for this field
            var $sourceSelect = $('select[name="field_mapping\\[' + fieldName + '\\]\\[source\\]"]');
            
            // FIRST: Check for boolean field (Yes/No/Map) - these have hidden source select inside .boolean-map-field
            var $booleanRadio = $('input[name="field_mapping\\[' + fieldName + '\\]\\[boolean_mode\\]"]');
            if ($booleanRadio.length > 0 && fieldConfig.boolean_mode) {
                $booleanRadio.filter('[value="' + fieldConfig.boolean_mode + '"]').prop('checked', true).trigger('change');
                
                // If map mode, set the source field
                if (fieldConfig.boolean_mode === 'map' && fieldConfig.source) {
                    var $mapSelect = $booleanRadio.closest('.field-mapping-row').find('.boolean-map-field select');
                    if ($mapSelect.length) {
                        $mapSelect.val(fieldConfig.source);
                    }
                }
                
                // Set update_on_sync checkbox for boolean fields too
                var $checkbox = $booleanRadio.closest('.field-mapping-row').find('input[name$="[update_on_sync]"]');
                if ($checkbox.length) {
                    var shouldCheck = typeof fieldConfig.update_on_sync !== 'undefined' 
                        ? (fieldConfig.update_on_sync === '1' || fieldConfig.update_on_sync === 1 || fieldConfig.update_on_sync === true)
                        : true;
                    $checkbox.prop('checked', shouldCheck);
                }
                
                console.log('Loaded boolean field:', fieldName, fieldConfig.boolean_mode);
                return; // Done with this field
            }
            
            // SECOND: Check for select-with-map field (Fixed/Map) - these have hidden source select inside .select-map-field
            var $selectRadio = $('input[name="field_mapping\\[' + fieldName + '\\]\\[select_mode\\]"]');
            if ($selectRadio.length > 0 && fieldConfig.select_mode) {
                $selectRadio.filter('[value="' + fieldConfig.select_mode + '"]').prop('checked', true).trigger('change');
                
                if (fieldConfig.select_mode === 'fixed' && fieldConfig.fixed_value) {
                    var $fixedSelect = $selectRadio.closest('.field-mapping-row').find('.select-fixed-value');
                    if ($fixedSelect.length) {
                        $fixedSelect.val(fieldConfig.fixed_value);
                    }
                } else if (fieldConfig.select_mode === 'map' && fieldConfig.source) {
                    var $mapSelect = $selectRadio.closest('.field-mapping-row').find('.select-map-field select');
                    if ($mapSelect.length) {
                        $mapSelect.val(fieldConfig.source);
                    }
                }
                
                // Set update_on_sync checkbox for select fields too
                var $checkbox = $selectRadio.closest('.field-mapping-row').find('input[name$="[update_on_sync]"]');
                if ($checkbox.length) {
                    var shouldCheck = typeof fieldConfig.update_on_sync !== 'undefined' 
                        ? (fieldConfig.update_on_sync === '1' || fieldConfig.update_on_sync === 1 || fieldConfig.update_on_sync === true)
                        : true;
                    $checkbox.prop('checked', shouldCheck);
                }
                
                console.log('Loaded select field:', fieldName, fieldConfig.select_mode, fieldConfig.fixed_value);
                return; // Done with this field
            }
            
            // THIRD: Handle regular source select fields
            if ($sourceSelect.length > 0) {
                // Set source
                if (fieldConfig.source) {
                    $sourceSelect.val(fieldConfig.source);
                    console.log('Set source for', fieldName, 'to', fieldConfig.source);
                }
                
                // Set processing mode if available
                if (fieldConfig.processing_mode) {
                    var $modeSelect = $('select[name="field_mapping\\[' + fieldName + '\\]\\[processing_mode\\]"]');
                    if ($modeSelect.length) {
                        $modeSelect.val(fieldConfig.processing_mode).trigger('change');
                        
                        // Load mode-specific configurations
                        if (fieldConfig.php_formula) {
                            $('textarea[name="field_mapping\\[' + fieldName + '\\]\\[php_formula\\]"]').val(fieldConfig.php_formula);
                        }
                        if (fieldConfig.ai_provider) {
                            $('select[name="field_mapping\\[' + fieldName + '\\]\\[ai_provider\\]"]').val(fieldConfig.ai_provider);
                        }
                        if (fieldConfig.ai_prompt) {
                            $('textarea[name="field_mapping\\[' + fieldName + '\\]\\[ai_prompt\\]"]').val(fieldConfig.ai_prompt);
                        }
                        if (fieldConfig.hybrid_php) {
                            $('textarea[name="field_mapping\\[' + fieldName + '\\]\\[hybrid_php\\]"]').val(fieldConfig.hybrid_php);
                        }
                        if (fieldConfig.hybrid_ai_prompt) {
                            $('textarea[name="field_mapping\\[' + fieldName + '\\]\\[hybrid_ai_prompt\\]"]').val(fieldConfig.hybrid_ai_prompt);
                        }
                        if (fieldConfig.hybrid_ai_provider) {
                            $('select[name="field_mapping\\[' + fieldName + '\\]\\[hybrid_ai_provider\\]"]').val(fieldConfig.hybrid_ai_provider);
                        }
                    }
                }
                
                // Set update_on_sync checkbox (default to checked if not set)
                var $checkbox = $('input[name="field_mapping\\[' + fieldName + '\\]\\[update_on_sync\\]"]');
                if ($checkbox.length) {
                    // If update_on_sync exists in saved data, use that value
                    // Otherwise default to checked (update enabled)
                    var shouldCheck = typeof fieldConfig.update_on_sync !== 'undefined' 
                        ? (fieldConfig.update_on_sync === '1' || fieldConfig.update_on_sync === 1 || fieldConfig.update_on_sync === true)
                        : true; // Default to checked
                    $checkbox.prop('checked', shouldCheck);
                }
                
                // IMPORTANT: Also check for SKU mode in fields WITH source select (like sku_with_generate)
                if (fieldConfig.sku_mode) {
                    var $skuModeRadio = $sourceSelect.closest('.field-mapping-row').find('.sku-mode-radio');
                    if ($skuModeRadio.length > 0) {
                        $skuModeRadio.filter('[value="' + fieldConfig.sku_mode + '"]').prop('checked', true).trigger('change');
                        
                        if (fieldConfig.sku_mode === 'generate' && fieldConfig.sku_pattern) {
                            var $patternInput = $sourceSelect.closest('.field-mapping-row').find('.sku-pattern-input');
                            if ($patternInput.length) {
                                $patternInput.val(fieldConfig.sku_pattern);
                            }
                        }
                        console.log('Loaded SKU mode (in source block):', fieldName, 'mode:', fieldConfig.sku_mode, 'pattern:', fieldConfig.sku_pattern);
                    }
                }
            }
        });
        
        // Load Attributes & Variations if present
        if (savedMappings.attributes_variations) {
            console.log('★★★ Loading Attributes & Variations:', savedMappings.attributes_variations);
            loadSavedAttributesVariations(savedMappings.attributes_variations);
        }
        
        console.log('★★★ Finished loading saved mappings');
    }
    
    /**
     * Load saved attributes & variations configuration (NEW 3-CARD UI)
     */
    function loadSavedAttributesVariations(config) {
        if (!config) return;
        
        console.log('★★★ loadSavedAttributesVariations called with:', config);
        
        // Set product mode (simple / attributes / variable)
        var productMode = config.product_mode || 'simple';
        $('input[name="product_mode"][value="' + productMode + '"]').prop('checked', true).trigger('change');
        console.log('★★★ Restored product_mode:', productMode);
        
        // Load display attributes (for "attributes" mode)
        if (config.display_attributes && config.display_attributes.length > 0) {
            console.log('★★★ Loading display_attributes:', config.display_attributes.length);
            
            config.display_attributes.forEach(function(dispAttr) {
                if (dispAttr.name) {
                    // Determine target container based on mode
                    var targetContainer = productMode === 'attributes' ? 'attributes-list' : 'display-attributes-list';
                    
                    // Add row using the appropriate function
                    addAttributeRow(targetContainer);
                    
                    // Get the last added row and populate
                    var $row = $('#' + targetContainer + ' .attr-row').last();
                    $row.find('input[name$="[name]"]').val(dispAttr.name);
                    $row.find('select[name$="[source]"]').val(dispAttr.source || '');
                    
                    console.log('★★★ Restored display attribute:', dispAttr.name, '→', dispAttr.source);
                }
            });
        }
        
        // Load variation path
        if (config.variation_path) {
            $('#variation_path').val(config.variation_path);
            console.log('★★★ Restored variation_path:', config.variation_path);
        }
        
        // Load variation attributes (for "variable" mode)
        if (config.variation_attributes && config.variation_attributes.length > 0) {
            console.log('★★★ Loading variation_attributes:', config.variation_attributes.length);
            
            config.variation_attributes.forEach(function(varAttr) {
                if (varAttr.name) {
                    addVariationAttributeRow();
                    
                    var $row = $('#variation-attributes-list .attr-row').last();
                    $row.find('input[name$="[name]"]').val(varAttr.name);
                    $row.find('select[name$="[source]"]').val(varAttr.source || '');
                    
                    // Restore array_index if present
                    if (varAttr.array_index !== null && varAttr.array_index !== undefined) {
                        $row.find('input[name$="[array_index]"]').val(varAttr.array_index);
                    }
                    
                    console.log('★★★ Restored variation attribute:', varAttr.name, '→', varAttr.source, 'array_index:', varAttr.array_index);
                }
            });
        }
        
        // Load variation field mappings
        if (config.variation_fields) {
            Object.keys(config.variation_fields).forEach(function(fieldKey) {
                var value = config.variation_fields[fieldKey];
                $('select[name="var_field[' + fieldKey + ']"]').val(value);
                console.log('★★★ Restored var_field[' + fieldKey + ']:', value);
            });
        }
        
        // Load variation meta fields
        if (config.variation_meta && config.variation_meta.length > 0) {
            config.variation_meta.forEach(function(metaItem) {
                if (metaItem.key) {
                    addVariationMetaRow();
                    
                    var $row = $('#variation-meta-list .meta-row').last();
                    $row.find('input[name$="[key]"]').val(metaItem.key);
                    $row.find('select[name$="[source]"]').val(metaItem.source || '');
                    
                    console.log('★★★ Restored variation meta:', metaItem.key, '→', metaItem.source);
                }
            });
        }
        
        // Load CSV variation configuration
        if (config.csv_variation_config) {
            var csvConfig = config.csv_variation_config;
            console.log('★★★ Loading csv_variation_config:', csvConfig);
            
            // Restore parent SKU column
            if (csvConfig.parent_sku_column) {
                $('#csv-parent-sku-column').val(csvConfig.parent_sku_column);
                console.log('★★★ Restored CSV parent_sku_column:', csvConfig.parent_sku_column);
            }
            
            // Restore type column
            if (csvConfig.type_column) {
                $('#csv-type-column').val(csvConfig.type_column);
                console.log('★★★ Restored CSV type_column:', csvConfig.type_column);
            }
            
            // Restore CSV variation attributes (clear existing first to avoid duplicates)
            if (csvConfig.attributes && csvConfig.attributes.length > 0) {
                // Clear existing CSV attribute rows first
                $('#csv-variation-attributes-list').empty();
                
                csvConfig.attributes.forEach(function(attr) {
                    if (attr.name) {
                        addCsvVariationAttributeRow();
                        
                        var $row = $('#csv-variation-attributes-list .attr-row').last();
                        $row.find('input[name$="[name]"]').val(attr.name);
                        $row.find('select[name$="[source]"]').val(attr.source || '');
                        
                        console.log('★★★ Restored CSV var attribute:', attr.name, '→', attr.source);
                    }
                });
            }
            
            // Restore CSV variation field mappings
            if (csvConfig.fields) {
                Object.keys(csvConfig.fields).forEach(function(fieldKey) {
                    var value = csvConfig.fields[fieldKey];
                    $('select[name="csv_var_field[' + fieldKey + ']"]').val(value);
                    console.log('★★★ Restored csv_var_field[' + fieldKey + ']:', value);
                });
            }
        }
        
        console.log('★★★ Attributes & Variations loaded');
    }
    
    /**
     * Load saved custom fields from database
     */
    function loadSavedCustomFields(savedFields) {
        console.log('Loading saved custom fields:', savedFields);
        
        var $container = $('#custom-fields-container');
        var $noFields = $container.find('.no-custom-fields');
        
        if (!Array.isArray(savedFields) || savedFields.length === 0) {
            return;
        }
        
        savedFields.forEach(function(fieldData) {
            // Click the "Add Custom Field" button to create a new row
            var $template = $('#custom-field-template');
            if (!$template.length) {
                console.error('Custom field template not found');
                return;
            }
            
            var index = Date.now() + Math.random();
            var html = $template.html().replace(/\{index\}/g, index);
            $container.append(html);
            
            // Populate the newly added row with saved data
            var $newRow = $container.find('.custom-field-row').last();
            
            if (fieldData.name) {
                $newRow.find('input[name*="[name]"]').val(fieldData.name);
            }
            if (fieldData.type) {
                $newRow.find('select[name*="[type]"]').val(fieldData.type);
            }
            if (fieldData.processing_mode) {
                $newRow.find('select[name*="[processing_mode]"]').val(fieldData.processing_mode);
            }
            
            // Populate source field dropdown first
            if (window.currentFileStructure && window.currentFileStructure.length > 0) {
                var options = '<option value="">-- Select Source Field --</option>';
                window.currentFileStructure.forEach(function(field) {
                    options += '<option value="' + field.path + '">' + field.path + '</option>';
                });
                $newRow.find('select[name*="[source]"]').html(options);
                
                // Then set the selected value
                if (fieldData.source) {
                    $newRow.find('select[name*="[source]"]').val(fieldData.source);
                }
            }
            
            if ($noFields.length) {
                $noFields.hide();
            }
        });
        
        console.log('Loaded ' + savedFields.length + ' custom fields');
    }

    /**
     * Test AI field processing
     */
    function testAiField() {
        var $button = $(this);
        var fieldKey = $button.data('field');
        var $row = $button.closest('.field-mapping-row');
        
        var provider = $row.find('select[name$="[ai_provider]"]').val();
        var prompt = $row.find('textarea[name$="[ai_prompt]"]').val();
        var sourceField = $row.find('.field-source-select').val();

        if (!provider || !prompt) {
            alert('Please select an AI provider and enter a prompt.');
            return;
        }

        // Get test value - use sample data if available, otherwise prompt user
        var testValue = 'test value';
        if (window.currentSampleData && window.currentSampleData.length > 0 && sourceField) {
            testValue = window.currentSampleData[0][sourceField] || 'test value';
        } else {
            testValue = prompt('Enter a test value for AI processing:', 'Test product name');
            if (testValue === null) {
                return; // User cancelled
            }
        }

        $button.prop('disabled', true).text(wc_xml_csv_ai_import_ajax.strings.test_ai || 'Testing...');

        // Build sample data for context
        var sampleData = {};
        if (window.currentSampleData && window.currentSampleData.length > 0) {
            var firstRow = window.currentSampleData[0];
            // Try to extract common fields for context
            sampleData.name = firstRow['product_name'] || firstRow['name'] || '';
            sampleData.price = firstRow['price'] || firstRow['regular_price'] || '';
            sampleData.ean = firstRow['ean'] || firstRow['eans.ean'] || '';
            sampleData.brand = firstRow['brand'] || '';
            sampleData.category = firstRow['category'] || '';
        }

        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_test_ai',
                provider: provider,
                test_prompt: prompt,
                test_value: testValue,
                sample_data: sampleData,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Log full result to console
                    console.log('=== AI TEST FULL RESULT ===');
                    console.log('Test Value:', testValue);
                    console.log('Result:', response.data.result);
                    console.log('===========================');
                    
                    // Show result in alert (full text)
                    alert(response.data.result);
                } else {
                    alert('AI Test failed: ' + response.data.message);
                }
            },
            error: function() {
                alert('AI Test request failed.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test AI');
            }
        });
    }

    /**
     * Update SKU preview based on pattern
     */
    function updateSkuPreview($row) {
        var pattern = $row.find('.sku-pattern-input').val() || 'PROD-{row}';
        var $preview = $row.find('.sku-preview');
        
        // Generate example preview
        var preview = pattern
            .replace('{row}', '1')
            .replace('{timestamp}', Math.floor(Date.now() / 1000))
            .replace('{random}', Math.random().toString(36).substring(2, 8).toUpperCase())
            .replace('{name}', 'sample-product-name')
            .replace('{md5}', 'a1b2c3d4');
        
        $preview.text(preview);
    }

    /**
     * Test PHP formula processing
     */
    function testPhpFormula() {
        var $button = $(this);
        var fieldKey = $button.data('field');
        var $row = $button.closest('.field-mapping-row');
        
        var formula = $row.find('textarea[name$="[php_formula]"]').val();
        var sourceField = $row.find('.field-source-select').val();

        if (!formula) {
            alert('Please enter a PHP formula to test.');
            return;
        }

        // Get test value - use sample data if available, otherwise prompt user
        var testValue = '100';
        if (window.currentSampleData && window.currentSampleData.length > 0 && sourceField) {
            testValue = window.currentSampleData[0][sourceField] || '100';
        } else {
            testValue = prompt('Enter a test value for the formula:', '100');
            if (testValue === null) {
                return; // User cancelled
            }
        }

        $button.prop('disabled', true).text('Testing...');

        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_test_php',
                formula: formula,
                test_value: testValue,
                sample_data: (window.currentSampleData && window.currentSampleData.length > 0) ? window.currentSampleData[0] : {},
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Log full details to console
                    console.log('=== PHP Formula Test ===');
                    console.log('Input:', testValue);
                    console.log('Formula:', formula);
                    console.log('Result:', response.data.result);
                    console.log('=======================');
                    
                    // Show short alert with result
                    alert('✓ Formula Test Result: ' + response.data.result + '\n\n(Full details in browser console)');
                } else {
                    alert('PHP Formula Test failed:\n\n' + response.data.message);
                }
            },
            error: function() {
                alert('PHP Formula Test request failed.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test PHP Formula');
            }
        });
    }

    /**
     * Test shipping formula
     */
    function testShippingFormula() {
        var $button = $(this);
        var formula = $('#shipping-class-formula').val();

        if (!formula) {
            alert('Please enter a shipping formula to test.');
            return;
        }

        // Prompt for test dimensions
        var weight = prompt('Enter test weight (kg):', '1.5');
        if (weight === null) return;
        
        var length = prompt('Enter test length (cm):', '30');
        if (length === null) return;
        
        var width = prompt('Enter test width (cm):', '20');
        if (width === null) return;
        
        var height = prompt('Enter test height (cm):', '10');
        if (height === null) return;

        $button.prop('disabled', true).text('Testing...');

        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_test_shipping',
                formula: formula,
                weight: weight,
                length: length,
                width: width,
                height: height,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var message = 'Shipping Formula Test:\n\n' +
                                'Weight: ' + weight + ' kg\n' +
                                'Dimensions: ' + length + ' × ' + width + ' × ' + height + ' cm\n' +
                                'Shipping Class: ' + response.data.result;
                    alert(message);
                } else {
                    alert('Shipping Formula Test failed:\n\n' + response.data.message);
                }
            },
            error: function() {
                alert('Shipping Formula Test request failed.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Shipping Formula');
            }
        });
    }

    /**
     * Handle mapping form submission
     */
    function handleMappingSubmission(e) {
        e.preventDefault();

        // Check if auto-mapping was used but not verified
        if (window.autoMappingUsed && !window.autoMappingVerified) {
            var confirmMapping = confirm(
                '⚠️ AUTO-MAPPING WARNING ⚠️\n\n' +
                'You used automatic field mapping but have not verified the mappings.\n\n' +
                'Auto-mapping is a suggestion and may contain errors. Please review the mapped fields before proceeding.\n\n' +
                'Are you sure you want to continue WITHOUT verifying the mappings?'
            );
            
            if (!confirmMapping) {
                // Scroll to warning
                if ($('#auto-mapping-warning').length) {
                    $('html, body').animate({
                        scrollTop: $('#auto-mapping-warning').offset().top - 100
                    }, 500);
                    $('#auto-mapping-warning').css('animation', 'pulse 0.5s ease-in-out 3');
                }
                return;
            }
        }

        if (!confirm(wc_xml_csv_ai_import_ajax.strings.confirm_import)) {
            return;
        }

        // Get import ID
        var importId = getImportIdFromUrl();
        if (!importId) {
            showMessage($('#mapping-messages'), 'Error: Import ID not found.', 'error');
            return;
        }

        // FIRST: Save mappings to database, THEN start import
        var $startButton = $('#start-import');
        $startButton.prop('disabled', true).html('<span class="spinner is-active"></span> Saving mappings...');
        
        // Collect mapping data properly
        var mappingData = collectMappingData();
        
        // Save mappings first
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_save_mapping',
                import_id: importId,
                nonce: wc_xml_csv_ai_import_ajax.nonce,
                mapping_data: JSON.stringify(mappingData)
            },
            success: function(saveResponse) {
                if (!saveResponse.success) {
                    showMessage($('#mapping-messages'), 'Failed to save mappings: ' + (saveResponse.data.message || 'Unknown error'), 'error');
                    $startButton.prop('disabled', false).html('Start Import <span class="button-icon">🚀</span>');
                    return;
                }
                
                // Mappings saved successfully, now start import
                $startButton.html('<span class="spinner is-active"></span> ' + wc_xml_csv_ai_import_ajax.strings.importing);
                startImportAfterSave(mappingData);
            },
            error: function() {
                showMessage($('#mapping-messages'), 'Failed to save mappings before import.', 'error');
                $startButton.prop('disabled', false).html('Start Import <span class="button-icon">🚀</span>');
            }
        });
    }
    
    /**
     * Start import after mappings have been saved
     */
    function startImportAfterSave(mappingData) {
        var formData = new FormData();
        formData.append('action', 'wc_xml_csv_ai_import_start_import');
        formData.append('nonce', wc_xml_csv_ai_import_ajax.nonce);
        
        // Send field_mapping as JSON string to preserve nested objects (like attributes_variations)
        if (mappingData.field_mapping && Object.keys(mappingData.field_mapping).length > 0) {
            formData.append('field_mapping_json', JSON.stringify(mappingData.field_mapping));
        }
        
        // Send custom fields as JSON string
        if (mappingData.custom_fields && mappingData.custom_fields.length > 0) {
            formData.append('custom_fields_json', JSON.stringify(mappingData.custom_fields));
        }
        
        // Add import filters as JSON string
        if (mappingData.import_filters && mappingData.import_filters.length > 0) {
            formData.append('import_filters_json', JSON.stringify(mappingData.import_filters));
        }
        
        // Add filter logic
        if (mappingData.filter_logic) {
            formData.append('filter_logic', mappingData.filter_logic);
        }
        
        // Add draft_non_matching setting
        if (mappingData.draft_non_matching) {
            formData.append('draft_non_matching', mappingData.draft_non_matching);
        }
        
        // Add import data from wcAiImportData if available
        if (typeof wcAiImportData !== 'undefined') {
            // Add import_id if editing existing import (not new import)
            var importIdFromUrl = new URLSearchParams(window.location.search).get('import_id');
            if (importIdFromUrl) {
                formData.append('import_id', importIdFromUrl);
                console.log('★★★ Adding import_id to Start Import:', importIdFromUrl);
            }
            
            if (wcAiImportData.import_name) {
                formData.append('import_name', wcAiImportData.import_name);
            }
            if (wcAiImportData.schedule_type) {
                formData.append('schedule_type', wcAiImportData.schedule_type);
            }
            if (wcAiImportData.file_path) {
                formData.append('file_path', wcAiImportData.file_path);
            }
            if (wcAiImportData.file_type) {
                formData.append('file_type', wcAiImportData.file_type);
            }
            if (wcAiImportData.product_wrapper) {
                formData.append('product_wrapper', wcAiImportData.product_wrapper);
            }
        }
        
        // CRITICAL: Add update_existing and skip_unchanged from mappingData (current checkbox state)
        // NOT from wcAiImportData (old database value)
        if (mappingData.update_existing) {
            formData.append('update_existing', mappingData.update_existing);
        }
        if (mappingData.skip_unchanged) {
            formData.append('skip_unchanged', mappingData.skip_unchanged);
        }

        var $button = $('#start-import');
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + wc_xml_csv_ai_import_ajax.strings.importing);

        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage($('#mapping-messages'), response.data.message, 'success');
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=wc-xml-csv-import&step=3&import_id=' + response.data.import_id;
                    }, 1000);
                } else {
                    showMessage($('#mapping-messages'), response.data.message, 'error');
                }
            },
            error: function() {
                showMessage($('#mapping-messages'), 'Import start request failed.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html('Start Import <span class="button-icon">🚀</span>');
            }
        });
    }

    /**
     * Save mapping configuration
     */
    function saveMapping() {
        console.log('★★★ SAVE MAPPING CLICKED ★★★');
        
        var $button = $(this);
        $button.prop('disabled', true).text('Saving...');

        // Collect mapping data
        var mappingData = collectMappingData();
        console.log('Collected mapping data:', mappingData);
        
        // Get import ID
        var importId = getImportIdFromUrl();
        console.log('Import ID from URL:', importId);
        
        if (!importId) {
            console.error('ERROR: Import ID not found!');
            showMessage($('#mapping-messages'), 'Error: Import ID not found.', 'error');
            $button.prop('disabled', false).text('Save');
            return;
        }
        
        console.log('Sending AJAX request...');

        // Send AJAX request to save
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_save_mapping',
                import_id: importId,
                mapping_data: JSON.stringify(mappingData),
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    showMessage($('#mapping-messages'), 'Settings saved successfully.', 'success');
                } else {
                    showMessage($('#mapping-messages'), 'Error: ' + (response.data || 'Failed to save settings'), 'error');
                }
                $button.prop('disabled', false).text('Save');
            },
            error: function(xhr, status, error) {
                console.error('Save settings AJAX error:', error, xhr.responseText);
                showMessage($('#mapping-messages'), 'Error saving settings. Please try again.', 'error');
                $button.prop('disabled', false).text('Save');
            }
        });
    }

    /**
     * Collect mapping data from form
     */
    function collectMappingData() {
        var data = {
            field_mapping: {},
            custom_fields: [],
            import_filters: [],
            filter_logic: 'AND'
        };

        // Collect standard field mappings
        $('.field-mapping-row:not(.custom-field-row)').each(function() {
            var $row = $(this);
            var fieldKey = $row.data('field');
            
            // Try to get from select first, then textarea (for fields like images)
            var sourceField = $row.find('.field-source-select').val();
            if (!sourceField) {
                sourceField = $row.find('.field-source-textarea').val();
            }
            
            var processingMode = $row.find('.processing-mode-select').val();
            var updateOnSync = $row.find('input[name$="[update_on_sync]"]').is(':checked') ? '1' : '0';
            
            // Check for boolean field (Yes/No/Map)
            var $booleanRadio = $row.find('.boolean-mode-radio:checked');
            var booleanMode = $booleanRadio.length ? $booleanRadio.val() : null;
            
            // Check for select-with-map field (Fixed/Map)
            var $selectRadio = $row.find('.select-mode-radio:checked');
            var selectMode = $selectRadio.length ? $selectRadio.val() : null;

            // ALWAYS create mapping object to preserve update_on_sync state
            data.field_mapping[fieldKey] = {
                source: sourceField || '',
                processing_mode: processingMode,
                update_on_sync: updateOnSync
            };
            
            // Add boolean mode if present
            if (booleanMode) {
                data.field_mapping[fieldKey].boolean_mode = booleanMode;
                // For boolean fields, if mode is yes/no, source is irrelevant
                if (booleanMode === 'yes' || booleanMode === 'no') {
                    data.field_mapping[fieldKey].source = '';
                } else if (booleanMode === 'map') {
                    // Get source from the hidden map field select
                    var mapSource = $row.find('.boolean-map-field select').val();
                    data.field_mapping[fieldKey].source = mapSource || '';
                }
            }
            
            // Add select mode if present
            if (selectMode) {
                data.field_mapping[fieldKey].select_mode = selectMode;
                if (selectMode === 'fixed') {
                    data.field_mapping[fieldKey].fixed_value = $row.find('.select-fixed-value').val();
                    data.field_mapping[fieldKey].source = '';
                } else if (selectMode === 'map') {
                    // Get source from the map field select
                    var mapSource = $row.find('.select-map-field select').val();
                    data.field_mapping[fieldKey].source = mapSource || '';
                }
            }
            
            // Add SKU mode if present (for sku_with_generate field type)
            var $skuModeRadio = $row.find('.sku-mode-radio:checked');
            if ($skuModeRadio.length) {
                data.field_mapping[fieldKey].sku_mode = $skuModeRadio.val();
                if ($skuModeRadio.val() === 'generate') {
                    data.field_mapping[fieldKey].sku_pattern = $row.find('.sku-pattern-input').val() || 'PROD-{row}';
                    data.field_mapping[fieldKey].source = '';  // Clear source when auto-generating
                }
                console.log('★ SKU mode collected:', data.field_mapping[fieldKey].sku_mode, 'pattern:', data.field_mapping[fieldKey].sku_pattern);
            }

            // Add processing-specific config only if source exists
            if (sourceField) {
                if (processingMode === 'php_formula') {
                    data.field_mapping[fieldKey].php_formula = $row.find('textarea[name$="[php_formula]"]').val();
                } else if (processingMode === 'ai_processing') {
                    data.field_mapping[fieldKey].ai_provider = $row.find('select[name$="[ai_provider]"]').val();
                    data.field_mapping[fieldKey].ai_prompt = $row.find('textarea[name$="[ai_prompt]"]').val();
                } else if (processingMode === 'hybrid') {
                    data.field_mapping[fieldKey].hybrid_php = $row.find('textarea[name$="[hybrid_php]"]').val();
                    data.field_mapping[fieldKey].hybrid_ai_prompt = $row.find('textarea[name$="[hybrid_ai_prompt]"]').val();
                    data.field_mapping[fieldKey].hybrid_ai_provider = $row.find('select[name$="[hybrid_ai_provider]"]').val();
                }
            }
        });
        
        console.log('COLLECTED MAPPING DATA:', data);
        
        // Collect shipping_class_formula if present (separate from standard mapping)
        var shippingFormula = $('textarea[name="field_mapping\\[shipping_class_formula\\]\\[formula\\]"]').val();
        if (shippingFormula && shippingFormula.trim()) {
            data.field_mapping.shipping_class_formula = {
                formula: shippingFormula
            };
            console.log('★ Collected shipping_class_formula:', shippingFormula.substring(0, 50) + '...');
        }

        // Collect import filters
        $('.filter-rule-row').each(function() {
            var $row = $(this);
            var field = $row.find('.filter-field-select').val();
            var operator = $row.find('.filter-operator-select').val();
            var value = $row.find('.filter-value-input').val();

            if (field && operator) {
                data.import_filters.push({
                    field: field,
                    operator: operator,
                    value: value
                });
            }
        });

        // Get filter logic (AND/OR)
        data.filter_logic = $('input[name="filter_logic"]:checked').val() || 'AND';
        
        // Get draft_non_matching checkbox value
        data.draft_non_matching = $('#draft-non-matching-checkbox').is(':checked') ? 1 : 0;
        
        // Get update_existing and skip_unchanged checkbox values (CRITICAL FIX)
        data.update_existing = $('input[name="update_existing"]').is(':checked') ? '1' : '0';
        data.skip_unchanged = $('input[name="skip_unchanged"]').is(':checked') ? '1' : '0';
        data.batch_size = $('input[name="batch_size"]').val() || 50;
        
        // Get schedule_type (for scheduled imports)
        data.schedule_type = $('select[name="schedule_type"]').val() || 'none';
        
        // Get schedule_method (action_scheduler or server_cron)
        data.schedule_method = $('input[name="schedule_method"]:checked').val() || 'action_scheduler';

        console.log('COLLECTED FILTERS:', data.import_filters, 'Logic:', data.filter_logic, 'Draft non-matching:', data.draft_non_matching);
        console.log('★ COLLECTED SETTINGS: update_existing=' + data.update_existing + ', skip_unchanged=' + data.skip_unchanged + ', batch_size=' + data.batch_size + ', schedule_type=' + data.schedule_type + ', schedule_method=' + data.schedule_method);

        // Collect custom fields
        $('.custom-field-row').each(function() {
            var $row = $(this);
            var name = $row.find('.custom-field-name').val();
            var type = $row.find('.custom-field-type').val();
            var sourceField = $row.find('.field-source-select').val();
            var processingMode = $row.find('.processing-mode-select').val();

            if (name && sourceField) {
                data.custom_fields.push({
                    name: name,
                    type: type,
                    source: sourceField,
                    processing_mode: processingMode
                });
            }
        });

        // Collect Attributes & Variations (NEW 3-CARD UI)
        var productMode = $('input[name="product_mode"]:checked').val() || 'simple';
        var variationMode = $('#variation_mode_hidden').val() || productMode;
        
        var attributesData = {
            product_mode: productMode,
            variation_mode: variationMode,
            attributes: [],
            display_attributes: [],
            variation_attributes: [],
            variation_path: '',
            variation_fields: {},
            variation_meta: [],
            // CSV specific fields
            csv_variation_config: {
                parent_sku_column: '',
                type_column: '',
                attributes: [],
                fields: {}
            }
        };
        
        // For "attributes" mode - collect display attributes
        if (productMode === 'attributes') {
            $('#attributes-list .attr-row').each(function() {
                var $row = $(this);
                var name = $row.find('input[name$="[name]"]').val();
                var source = $row.find('select[name$="[source]"]').val();
                if (name) {
                    attributesData.display_attributes.push({
                        name: name,
                        source: source || '',
                        visible: 1,
                        used_for_variations: 0
                    });
                }
            });
        }
        
        // For "variable" mode - collect variation config
        if (productMode === 'variable') {
            // Check if we're in CSV or XML mode
            var fileType = (typeof wcAiImportData !== 'undefined' && wcAiImportData.file_type) || 'xml';
            
            if (fileType === 'csv') {
                // CSV variation config
                attributesData.csv_variation_config.parent_sku_column = $('#csv-parent-sku-column').val() || '';
                attributesData.csv_variation_config.type_column = $('#csv-type-column').val() || '';
                
                // CSV Variation attributes
                $('#csv-variation-attributes-list .attr-row').each(function() {
                    var $row = $(this);
                    var name = $row.find('input[name$="[name]"]').val();
                    var source = $row.find('select[name$="[source]"]').val();
                    if (name) {
                        attributesData.csv_variation_config.attributes.push({
                            name: name,
                            source: source || '',
                            visible: 1,
                            used_for_variations: 1
                        });
                    }
                });
                
                // CSV Variation field mappings
                $('select[name^="csv_var_field["]').each(function() {
                    var name = $(this).attr('name').match(/csv_var_field\[([^\]]+)\]/);
                    if (name && name[1]) {
                        attributesData.csv_variation_config.fields[name[1]] = $(this).val() || '';
                    }
                });
                
                console.log('★★★ CSV VARIATION CONFIG:', attributesData.csv_variation_config);
            } else {
                // XML variation config (existing logic)
                // Variation container path
                attributesData.variation_path = $('#variation_path').val() || '';
                
                // Variation attributes
                $('#variation-attributes-list .attr-row').each(function() {
                    var $row = $(this);
                    var name = $row.find('input[name$="[name]"]').val();
                    var source = $row.find('select[name$="[source]"]').val();
                    var arrayIndex = $row.find('input[name$="[array_index]"]').val();
                    if (name) {
                        attributesData.variation_attributes.push({
                            name: name,
                            source: source || '',
                            array_index: arrayIndex !== '' ? parseInt(arrayIndex) : null,
                            visible: 1,
                            used_for_variations: 1
                        });
                    }
                });
                
                // Variation field mappings
                $('select[name^="var_field["]').each(function() {
                    var name = $(this).attr('name').match(/var_field\[([^\]]+)\]/);
                    if (name && name[1]) {
                        attributesData.variation_fields[name[1]] = $(this).val() || '';
                    }
                });
                
                // Variation custom meta fields
                $('#variation-meta-list .meta-row').each(function() {
                    var $row = $(this);
                    var key = $row.find('input[name$="[key]"]').val();
                    var source = $row.find('select[name$="[source]"]').val();
                    if (key) {
                        attributesData.variation_meta.push({
                            key: key,
                            source: source || ''
                        });
                    }
                });
            }
        }
        
        // Also collect legacy display attributes from any mode
        $('.display-attribute-row').each(function() {
            var $row = $(this);
            var name = $row.find('input[name$="[name]"]').val();
            if (name) {
                attributesData.display_attributes.push({
                    name: name,
                    source: $row.find('select[name$="[source]"]').val() || '',
                    visible: $row.find('input[name$="[visible]"]').is(':checked') ? 1 : 0,
                    used_for_variations: 0
                });
            }
        });
        
        console.log('★★★ PRODUCT MODE:', productMode);
        console.log('★★★ COLLECTED ATTRIBUTES & VARIATIONS:', attributesData);
        
        // Add to field_mapping
        data.field_mapping.attributes_variations = attributesData;

        return data;
    }

    /**
     * Monitor import progress
     */
    function monitorImportProgress(importId) {
        var progressInterval = setInterval(function() {
            $.ajax({
                url: wc_xml_csv_ai_import_ajax.ajax_url,
                type: 'POST',
                timeout: 600000, // 10 minutes timeout for long-running imports
                data: {
                    action: 'wc_xml_csv_ai_import_get_progress',
                    import_id: importId,
                    nonce: wc_xml_csv_ai_import_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateProgressDisplay(response.data);
                        
                        // NOTE: We do NOT trigger next batch from JavaScript anymore!
                        // PHP's process_import_chunk() automatically schedules cron for next chunk
                        // This prevents double-processing (AJAX + Cron running simultaneously)
                        // which was causing processed_products > total_products issue
                        
                        if (response.data.status === 'completed' || response.data.status === 'failed') {
                            clearInterval(progressInterval);
                        }
                    }
                },
                error: function() {
                    console.error('Failed to get import progress');
                }
            });
        }, 2000); // Check every 2 seconds
    }
    
    /**
     * Trigger next batch processing
     */
    function triggerNextBatch(importId, offset) {
        // Prevent multiple simultaneous batch requests
        if (window.batchInProgress) {
            return;
        }
        
        window.batchInProgress = true;
        
        // Get batch size from import data or default to 50
        var batchSize = (typeof wcAiImportData !== 'undefined' && wcAiImportData.batch_size) 
            ? parseInt(wcAiImportData.batch_size) 
            : 50;
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            timeout: 300000, // 5 minutes per batch
            data: {
                action: 'wc_xml_csv_ai_import_process_batch',
                import_id: importId,
                offset: offset,
                limit: batchSize,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                console.log('Batch processed:', response);
            },
            error: function(xhr, status, error) {
                console.error('Batch processing error:', error);
            },
            complete: function() {
                window.batchInProgress = false;
            }
        });
    }

    /**
     * Update progress display
     */
    function updateProgressDisplay(data) {
        // Update progress circle
        var percentage = data.percentage || 0;
        $('.progress-circle').css('background', 'conic-gradient(#0073aa ' + percentage + '%, #f0f0f0 0%)');
        $('.progress-percentage').text(percentage + '%');

        // Update status
        var $status = $('.import-status');
        $status.text(data.status.charAt(0).toUpperCase() + data.status.slice(1));
        $status.removeClass('processing completed failed').addClass(data.status);

        // Update stats
        $('.stat-item').eq(0).find('.stat-value').text(data.total_products || 0);
        $('.stat-item').eq(1).find('.stat-value').text(data.processed_products || 0);
        $('.stat-item').eq(2).find('.stat-value').text(data.total_products - data.processed_products || 0);

        // Update logs
        if (data.logs && data.logs.length > 0) {
            var logsHtml = '';
            data.logs.forEach(function(log) {
                logsHtml += '<div class="log-entry ' + log.log_type + '">';
                logsHtml += '[' + log.created_at + '] ' + log.message;
                logsHtml += '</div>';
            });
            $('.import-logs').html(logsHtml);
        }
    }

    /**
     * Update mapped field counts
     */
    function updateMappedCounts() {
        $('.mapping-section').each(function() {
            var $section = $(this);
            var totalFields = $section.find('.field-mapping-row:not(.custom-field-row)').length;
            var mappedFields = $section.find('.field-source-select').filter(function() {
                return $(this).val() !== '';
            }).length;

            $section.find('.mapped-count').text(mappedFields + '/' + totalFields);
        });
    }

    /**
     * Utility function to show messages
     */
    function showMessage($container, message, type, isHtml) {
        type = type || 'info';
        var alertClass = 'notice-' + (type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'));
        var content = isHtml ? message : '<p>' + message + '</p>';
        
        $container.html('<div class="notice ' + alertClass + '">' + content + '</div>');

        if (type === 'success' || type === 'info') {
            setTimeout(function() {
                $container.fadeOut();
            }, 5000);
        }
    }

    /**
     * Utility function to truncate text
     */
    function truncateText(text, maxLength) {
        if (text.length <= maxLength) {
            return text;
        }
        return text.substring(0, maxLength) + '...';
    }

    /**
     * Get import ID from URL parameters
     */
    function getImportIdFromUrl() {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('import_id');
    }

    /**
     * Update file URL for import
     */
    function updateFileUrl() {
        var newUrl = $('#import_file_url').val().trim();
        var importId = getImportIdFromUrl();
        
        if (!newUrl || !importId) {
            alert('Invalid URL or Import ID');
            return;
        }
        
        $('#update-file-url-btn').prop('disabled', true).text('Updating...');
        $('#url-update-status').html('<span class="spinner is-active"></span>');
        
        $.ajax({
            url: wcAiImportData.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_update_url',
                nonce: wcAiImportData.nonce,
                import_id: importId,
                file_url: newUrl
            },
            success: function(response) {
                if (response.success) {
                    $('#url-update-status').html('<span style="color: green;">✓ URL updated successfully!</span>');
                    setTimeout(function() {
                        $('#url-update-status').html('');
                    }, 3000);
                } else {
                    $('#url-update-status').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $('#url-update-status').html('<span style="color: red;">Failed to update URL</span>');
            },
            complete: function() {
                $('#update-file-url-btn').prop('disabled', false).text('Update URL');
            }
        });
    }

    // Bind URL update button
    $(document).on('click', '#update-file-url-btn', updateFileUrl);

    /**
     * Populate filter dropdowns with file structure fields
     */
    function populateFilterDropdowns() {
        if (!window.currentFileStructure || !window.currentFileStructure.length) {
            console.log('No file structure available for filter dropdowns');
            return;
        }
        
        console.log('Populating filter dropdowns with fields:', window.currentFileStructure);
        
        // Find all filter field dropdowns
        $('.import-filter-field-select').each(function() {
            var $select = $(this);
            var currentValue = $select.val();
            
            // Clear existing options except first
            $select.find('option:not(:first)').remove();
            
            // Add field options - handle both string array and object array
            window.currentFileStructure.forEach(function(field) {
                var fieldName = typeof field === 'string' ? field : (field.name || field.field || field);
                if (fieldName && typeof fieldName === 'string') {
                    $select.append($('<option>', {
                        value: fieldName,
                        text: fieldName
                    }));
                }
            });
            
            // Restore previous value if it exists
            if (currentValue) {
                $select.val(currentValue);
            }
        });
    }

    // Auto-update mapped counts when source fields change
    $(document).on('change', '.field-source-select', updateMappedCounts);

    // Initialize sections as collapsed except first one
    $('.section-fields').first().addClass('active').show();
    $('.section-toggle').first().find('.dashicons').removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');

    // =====================================================
    // MAPPING RECIPES FUNCTIONALITY
    // =====================================================
    
    /**
     * Show recipe status message
     */
    function showRecipeStatus(message, type) {
        var $status = $('#recipe-status-message');
        var bgColor = type === 'success' ? '#d4edda' : (type === 'error' ? '#f8d7da' : '#fff3cd');
        var textColor = type === 'success' ? '#155724' : (type === 'error' ? '#721c24' : '#856404');
        
        $status.css({
            'background-color': bgColor,
            'color': textColor,
            'border': '1px solid ' + (type === 'success' ? '#c3e6cb' : (type === 'error' ? '#f5c6cb' : '#ffeeba'))
        }).html(message).show();
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $status.fadeOut();
        }, 5000);
    }
    
    /**
     * Load recipes list into dropdown
     */
    function loadRecipesList() {
        console.log('loadRecipesList() called');
        
        if (typeof wc_xml_csv_ai_import_ajax === 'undefined') {
            console.error('wc_xml_csv_ai_import_ajax is not defined');
            return;
        }
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_get_recipes',
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                console.log('Recipes response:', response);
                if (response.success && response.data.recipes) {
                    var $select = $('#recipe-select');
                    $select.find('option:not(:first)').remove();
                    
                    response.data.recipes.forEach(function(recipe) {
                        $select.append($('<option>', {
                            value: recipe.id,
                            text: recipe.name + ' (' + recipe.created_at.split(' ')[0] + ')'
                        }));
                    });
                    console.log('Loaded ' + response.data.recipes.length + ' recipes');
                } else {
                    console.warn('No recipes in response:', response);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading recipes:', status, error);
            }
        });
    }
    
    /**
     * Save Recipe button click
     */
    $(document).on('click', '#save-recipe-btn', function() {
        var recipeName = $('#recipe-name-input').val().trim();
        
        if (!recipeName) {
            showRecipeStatus('⚠️ Please enter a recipe name', 'warning');
            $('#recipe-name-input').focus();
            return;
        }
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="margin: 0;"></span>');
        
        // Collect current mapping data
        var mappingData = collectMappingData();
        console.log('Saving recipe with mapping data:', mappingData);
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_save_recipe',
                nonce: wc_xml_csv_ai_import_ajax.nonce,
                recipe_name: recipeName,
                mapping_data: mappingData
            },
            success: function(response) {
                if (response.success) {
                    showRecipeStatus('✅ ' + response.data.message, 'success');
                    $('#recipe-name-input').val('');
                    
                    // Refresh recipes dropdown
                    if (response.data.recipes) {
                        var $select = $('#recipe-select');
                        $select.find('option:not(:first)').remove();
                        response.data.recipes.forEach(function(recipe) {
                            $select.append($('<option>', {
                                value: recipe.id,
                                text: recipe.name + ' (' + recipe.created_at.split(' ')[0] + ')'
                            }));
                        });
                    }
                } else {
                    showRecipeStatus('❌ ' + response.data.message, 'error');
                }
            },
            error: function() {
                showRecipeStatus('❌ Failed to save recipe', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    /**
     * Load Recipe button click
     */
    $(document).on('click', '#load-recipe-btn', function() {
        var recipeId = $('#recipe-select').val();
        
        if (!recipeId) {
            showRecipeStatus('⚠️ Please select a recipe to load', 'warning');
            return;
        }
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="margin: 0;"></span>');
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_load_recipe',
                nonce: wc_xml_csv_ai_import_ajax.nonce,
                recipe_id: recipeId
            },
            success: function(response) {
                console.log('Load recipe response:', response);
                if (response.success && response.data.recipe) {
                    console.log('Applying mapping data:', response.data.recipe.mapping_data);
                    applyMappingData(response.data.recipe.mapping_data);
                    showRecipeStatus('✅ Recipe "' + response.data.recipe.name + '" loaded successfully', 'success');
                } else {
                    showRecipeStatus('❌ ' + (response.data.message || 'Failed to load recipe'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Load recipe error:', error, xhr.responseText);
                showRecipeStatus('❌ Failed to load recipe', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    /**
     * Delete Recipe button click
     */
    $(document).on('click', '#delete-recipe-btn', function() {
        var recipeId = $('#recipe-select').val();
        var recipeName = $('#recipe-select option:selected').text();
        
        if (!recipeId) {
            showRecipeStatus('⚠️ Please select a recipe to delete', 'warning');
            return;
        }
        
        if (!confirm('Are you sure you want to delete recipe "' + recipeName + '"?')) {
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true);
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_delete_recipe',
                nonce: wc_xml_csv_ai_import_ajax.nonce,
                recipe_id: recipeId
            },
            success: function(response) {
                if (response.success) {
                    showRecipeStatus('✅ Recipe deleted', 'success');
                    
                    // Refresh recipes dropdown
                    if (response.data.recipes) {
                        var $select = $('#recipe-select');
                        $select.find('option:not(:first)').remove();
                        response.data.recipes.forEach(function(recipe) {
                            $select.append($('<option>', {
                                value: recipe.id,
                                text: recipe.name + ' (' + recipe.created_at.split(' ')[0] + ')'
                            }));
                        });
                    }
                } else {
                    showRecipeStatus('❌ ' + response.data.message, 'error');
                }
            },
            error: function() {
                showRecipeStatus('❌ Failed to delete recipe', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    /**
     * Auto-detect Mapping button click
     */
    $(document).on('click', '#auto-detect-mapping-btn', function() {
        // allKnownFields is an object, allKnownFieldsOrder is the array of keys
        var sourceFields = window.allKnownFieldsOrder || Object.keys(window.allKnownFields || {});
        
        if (!sourceFields || sourceFields.length === 0) {
            showRecipeStatus('⚠️ Please wait for file structure to load first', 'warning');
            return;
        }
        
        console.log('Auto-detect: Source fields:', sourceFields);
        
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner is-active" style="margin: 0 5px 0 0;"></span> Detecting...');
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_auto_detect_mapping',
                nonce: wc_xml_csv_ai_import_ajax.nonce,
                source_fields: sourceFields
            },
            success: function(response) {
                console.log('Auto-detect response:', response);
                if (response.success && response.data.suggestions) {
                    var appliedCount = 0;
                    var hasStockQuantity = false;
                    
                    // Apply suggestions to mapping fields
                    $.each(response.data.suggestions, function(wooField, suggestion) {
                        // Track if stock_quantity is being mapped
                        if (wooField === 'stock_quantity') {
                            hasStockQuantity = true;
                        }
                        
                        var $row = $('.field-mapping-row[data-field="' + wooField + '"]');
                        if ($row.length) {
                            // Check if this is a boolean field (has YES/NO/MAP radio buttons)
                            var $booleanMapRadio = $row.find('.boolean-mode-radio[value="map"]');
                            if ($booleanMapRadio.length) {
                                // It's a boolean field - select MAP and set the source field
                                $booleanMapRadio.prop('checked', true).trigger('change');
                                var $booleanSelect = $row.find('.boolean-map-field select');
                                if ($booleanSelect.length) {
                                    if ($booleanSelect.find('option[value="' + suggestion.source_field + '"]').length === 0) {
                                        $booleanSelect.append($('<option>', { value: suggestion.source_field, text: suggestion.source_field }));
                                    }
                                    $booleanSelect.val(suggestion.source_field);
                                }
                                appliedCount++;
                                console.log('Applied boolean mapping:', wooField, '->', suggestion.source_field);
                            }
                            // Check if this is a select field (has Fixed/Map radio buttons)
                            else if ($row.find('.select-mode-radio[value="map"]').length) {
                                $row.find('.select-mode-radio[value="map"]').prop('checked', true).trigger('change');
                                var $selectMapField = $row.find('.select-map-field select');
                                if ($selectMapField.length) {
                                    if ($selectMapField.find('option[value="' + suggestion.source_field + '"]').length === 0) {
                                        $selectMapField.append($('<option>', { value: suggestion.source_field, text: suggestion.source_field }));
                                    }
                                    $selectMapField.val(suggestion.source_field);
                                }
                                appliedCount++;
                                console.log('Applied select mapping:', wooField, '->', suggestion.source_field);
                            }
                            // Regular text field
                            else {
                                var $select = $row.find('.field-source-select');
                                if ($select.length && $select.find('option[value="' + suggestion.source_field + '"]').length) {
                                    $select.val(suggestion.source_field).trigger('change');
                                    appliedCount++;
                                    console.log('Applied text mapping:', wooField, '->', suggestion.source_field);
                                }
                            }
                            
                            // Highlight matched field briefly
                            $row.css('background-color', '#d4edda');
                            setTimeout(function() {
                                $row.css('background-color', '');
                            }, 2000);
                        }
                    });
                    
                    // If stock_quantity was mapped, automatically enable manage_stock
                    if (hasStockQuantity) {
                        autoEnableManageStock();
                    }
                    
                    showRecipeStatus('✅ ' + response.data.message + ' (Applied: ' + appliedCount + ')', 'success');
                    updateMappedCounts();
                } else {
                    showRecipeStatus('⚠️ No matching fields found', 'warning');
                }
            },
            error: function() {
                showRecipeStatus('❌ Auto-detect failed', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    /**
     * Apply mapping data from loaded recipe
     */
    function applyMappingData(data) {
        console.log('applyMappingData called with:', data);
        if (!data) {
            console.warn('No data to apply');
            return;
        }
        
        // Apply field mappings
        if (data.field_mapping) {
            console.log('Applying field_mapping:', Object.keys(data.field_mapping));
            $.each(data.field_mapping, function(fieldKey, fieldData) {
                var $row = $('.field-mapping-row[data-field="' + fieldKey + '"]');
                if (!$row.length) {
                    console.log('Row not found for:', fieldKey);
                    return;
                }
                console.log('Applying to field:', fieldKey, fieldData);
                
                // Set source field
                if (fieldData.source) {
                    var $select = $row.find('.field-source-select');
                    var $textarea = $row.find('.field-source-textarea');
                    
                    if ($select.length) {
                        // Check if option exists, if not, try to add it
                        if ($select.find('option[value="' + fieldData.source + '"]').length === 0) {
                            $select.append($('<option>', { value: fieldData.source, text: fieldData.source }));
                        }
                        $select.val(fieldData.source).trigger('change');
                    } else if ($textarea.length) {
                        $textarea.val(fieldData.source);
                    }
                }
                
                // Set processing mode
                if (fieldData.processing_mode) {
                    $row.find('.processing-mode-select').val(fieldData.processing_mode).trigger('change');
                }
                
                // Set update_on_sync
                if (fieldData.update_on_sync !== undefined) {
                    $row.find('input[name$="[update_on_sync]"]').prop('checked', fieldData.update_on_sync === '1');
                }
                
                // Set boolean mode
                if (fieldData.boolean_mode) {
                    $row.find('.boolean-mode-radio[value="' + fieldData.boolean_mode + '"]').prop('checked', true).trigger('change');
                }
                
                // Set select mode
                if (fieldData.select_mode) {
                    $row.find('.select-mode-radio[value="' + fieldData.select_mode + '"]').prop('checked', true).trigger('change');
                    
                    if (fieldData.select_mode === 'fixed' && fieldData.fixed_value) {
                        $row.find('.select-fixed-value').val(fieldData.fixed_value);
                    }
                }
                
                // Set processing-specific fields
                if (fieldData.php_formula) {
                    $row.find('textarea[name$="[php_formula]"]').val(fieldData.php_formula);
                }
                if (fieldData.ai_provider) {
                    $row.find('select[name$="[ai_provider]"]').val(fieldData.ai_provider);
                }
                if (fieldData.ai_prompt) {
                    $row.find('textarea[name$="[ai_prompt]"]').val(fieldData.ai_prompt);
                }
            });
        }
        
        // Apply import filters
        if (data.import_filters && data.import_filters.length > 0) {
            // Clear existing filters first
            $('.filter-rule-row:not(:first)').remove();
            
            data.import_filters.forEach(function(filter, index) {
                if (index > 0) {
                    // Add new filter row
                    $('#add-filter-rule').trigger('click');
                }
                
                var $row = $('.filter-rule-row').eq(index);
                if ($row.length) {
                    $row.find('.filter-field-select').val(filter.field);
                    $row.find('.filter-operator-select').val(filter.operator);
                    $row.find('.filter-value-input').val(filter.value);
                }
            });
        }
        
        // Apply filter logic
        if (data.filter_logic) {
            $('input[name="filter_logic"][value="' + data.filter_logic + '"]').prop('checked', true);
        }
        
        // Apply checkboxes
        if (data.update_existing !== undefined) {
            $('input[name="update_existing"]').prop('checked', data.update_existing === '1');
        }
        if (data.skip_unchanged !== undefined) {
            $('input[name="skip_unchanged"]').prop('checked', data.skip_unchanged === '1');
        }
        if (data.draft_non_matching !== undefined) {
            $('#draft-non-matching-checkbox').prop('checked', data.draft_non_matching === 1);
        }
        
        // Update mapped counts
        updateMappedCounts();
        
        console.log('Mapping data applied from recipe');
    }
    
    /**
     * Delete Products with Progress Modal
     * Handles batch deletion with visual progress feedback
     */
    function initDeleteProductsWithProgress() {
        // Skip if no delete buttons on page
        if ($('.delete-products-ajax').length === 0) {
            return;
        }
        
        // Helper to get i18n string safely
        function getI18n(key, defaultValue) {
            if (typeof wcAiImportData !== 'undefined' && wcAiImportData.i18n && wcAiImportData.i18n[key]) {
                return wcAiImportData.i18n[key];
            }
            return defaultValue;
        }
        
        // Get nonce safely
        function getNonce() {
            if (typeof wcAiImportData !== 'undefined' && wcAiImportData.nonce) {
                return wcAiImportData.nonce;
            }
            return '';
        }
        
        // Create modal HTML if not exists
        if ($('#delete-products-modal').length === 0) {
            var modalHtml = '<div id="delete-products-modal" class="wc-ai-import-modal" style="display:none;">' +
                '<div class="wc-ai-import-modal-content delete-progress-modal">' +
                    '<div class="modal-header">' +
                        '<h2><span class="dashicons dashicons-trash"></span> ' + getI18n('deleting_products', 'Deleting Products') + '</h2>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div class="delete-progress-container">' +
                            '<div class="delete-spinner"><span class="spinner is-active"></span></div>' +
                            '<div class="delete-progress-info">' +
                                '<div class="delete-progress-text"></div>' +
                                '<div class="delete-progress-bar-container">' +
                                    '<div class="delete-progress-bar"></div>' +
                                '</div>' +
                                '<div class="delete-progress-stats">' +
                                    '<span class="deleted-count">0</span> / <span class="total-count">0</span> ' + 
                                    getI18n('products_deleted', 'products deleted') +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="delete-complete-message" style="display:none;">' +
                            '<span class="dashicons dashicons-yes-alt"></span> ' +
                            '<span class="complete-text"></span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="button delete-cancel-btn">' + 
                            getI18n('cancel', 'Cancel') + 
                        '</button>' +
                        '<button type="button" class="button button-primary delete-close-btn" style="display:none;">' + 
                            getI18n('close', 'Close') + 
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
            $('body').append(modalHtml);
        }
        
        // Handle delete button click
        $(document).on('click', '.delete-products-ajax', function(e) {
            e.preventDefault();
            
            var importId = $(this).data('import-id');
            var nonce = $(this).data('nonce');
            var $button = $(this);
            
            // Confirm dialog
            var confirmMsg = getI18n('confirm_delete_products', 'Are you sure you want to delete all products from this import?');
            if (!confirm(confirmMsg)) {
                return;
            }
            
            // Show modal
            var $modal = $('#delete-products-modal');
            $modal.show();
            
            // Reset modal state
            $modal.find('.delete-progress-container').show();
            $modal.find('.delete-complete-message').hide();
            $modal.find('.delete-cancel-btn').show();
            $modal.find('.delete-close-btn').hide();
            $modal.find('.delete-progress-bar').css('width', '0%');
            $modal.find('.deleted-count').text('0');
            $modal.find('.delete-progress-text').text(getI18n('counting_products', 'Counting products...'));
            
            var isDeleting = true;
            var totalProducts = 0;
            var deletedProducts = 0;
            
            // Cancel button handler
            $modal.find('.delete-cancel-btn').off('click').on('click', function() {
                isDeleting = false;
                $modal.hide();
            });
            
            // Close button handler
            $modal.find('.delete-close-btn').off('click').on('click', function() {
                $modal.hide();
                location.reload(); // Refresh to update product counts
            });
            
            // First, get total product count - use nonce from button's data attribute
            var nonceToUse = nonce; // Use the nonce we got from the button's data-nonce attribute
            console.log('Delete Products AJAX - Import ID:', importId, 'Nonce:', nonceToUse);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_xml_csv_ai_import_get_products_count',
                    import_id: importId,
                    nonce: nonceToUse
                },
                success: function(response) {
                    console.log('Get products count response:', response);
                    if (response.success) {
                        totalProducts = response.data.count;
                        $modal.find('.total-count').text(totalProducts);
                        
                        if (totalProducts === 0) {
                            showDeleteComplete($modal, getI18n('no_products_found', 'No products found to delete.'), false);
                            return;
                        }
                        
                        $modal.find('.delete-progress-text').text(getI18n('deleting', 'Deleting...'));
                        
                        // Start batch deletion
                        deleteBatch();
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : 'Error getting product count';
                        console.error('Delete products error:', errorMsg);
                        showDeleteComplete($modal, errorMsg, true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete products AJAX error:', status, error, xhr.responseText);
                    showDeleteComplete($modal, 'Error connecting to server', true);
                }
            });
            
            function deleteBatch() {
                if (!isDeleting) return;
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_xml_csv_ai_import_delete_products_batch',
                        import_id: importId,
                        nonce: nonceToUse, // Use same nonce as get_products_count
                        batch_size: 25
                    },
                    success: function(response) {
                        if (response.success) {
                            deletedProducts += response.data.deleted;
                            
                            // Update progress
                            var percent = totalProducts > 0 ? Math.round((deletedProducts / totalProducts) * 100) : 0;
                            $modal.find('.deleted-count').text(deletedProducts);
                            $modal.find('.delete-progress-bar').css('width', percent + '%');
                            
                            if (response.data.completed) {
                                var completeMsg = getI18n('all_products_deleted', 'All %d products deleted successfully!').replace('%d', deletedProducts);
                                showDeleteComplete($modal, completeMsg, false);
                            } else if (isDeleting) {
                                // Continue with next batch after short delay
                                setTimeout(deleteBatch, 100);
                            }
                        } else {
                            var errorMsg = (response.data && response.data.message) ? response.data.message : 'Error deleting products';
                            showDeleteComplete($modal, errorMsg, true);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('DELETE_BATCH AJAX error:', status, error);
                        console.error('Response:', xhr.responseText);
                        var errorMsg = 'Error connecting to server';
                        if (xhr.responseText) {
                            // Try to extract error from response
                            try {
                                var resp = JSON.parse(xhr.responseText);
                                if (resp.data && resp.data.message) {
                                    errorMsg = resp.data.message;
                                }
                            } catch (e) {
                                // Check for PHP error in response
                                if (xhr.responseText.indexOf('Fatal error') !== -1) {
                                    errorMsg = 'Server error: PHP Fatal Error. Check error logs.';
                                } else if (xhr.responseText.indexOf('error') !== -1) {
                                    errorMsg = 'Server error. Check browser console for details.';
                                }
                            }
                        }
                        showDeleteComplete($modal, errorMsg, true);
                    }
                });
            }
        });
    }
    
    function showDeleteComplete($modal, message, isError) {
        $modal.find('.delete-progress-container').hide();
        var $completeMsg = $modal.find('.delete-complete-message');
        $completeMsg.show();
        
        // Update icon based on success/error
        var $icon = $completeMsg.find('.dashicons');
        if (isError) {
            $icon.removeClass('dashicons-yes-alt').addClass('dashicons-dismiss');
            $icon.css('color', '#d63638');
        } else {
            $icon.removeClass('dashicons-dismiss').addClass('dashicons-yes-alt');
            $icon.css('color', '#00a32a');
        }
        
        $modal.find('.complete-text').text(message);
        $modal.find('.delete-cancel-btn').hide();
        $modal.find('.delete-close-btn').show();
    }
    
    // Initialize delete products functionality on page load
    $(document).ready(function() {
        initDeleteProductsWithProgress();
    });

})(jQuery);/* Cache bust 1769150333 */
