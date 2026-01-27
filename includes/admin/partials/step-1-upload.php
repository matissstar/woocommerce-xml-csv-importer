<?php
/**
 * Step 1: File Upload Interface
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('wc_xml_csv_ai_import_settings', array());
$max_file_size = isset($settings['max_file_size']) ? $settings['max_file_size'] : 100;

// License checks
$can_scheduling = WC_XML_CSV_AI_Import_License::can('scheduling');
$can_import_url = WC_XML_CSV_AI_Import_License::can('import_url');
?>

<div class="wc-ai-import-step wc-ai-import-step-1">
    <div class="wc-ai-import-card">
        <h2><?php _e('Step 1: Upload File', 'wc-xml-csv-import'); ?></h2>
        <p class="description"><?php _e('Upload your XML or CSV file, or provide a URL to import products from.', 'wc-xml-csv-import'); ?></p>
        
        <form id="wc-ai-import-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wc_xml_csv_ai_import_nonce', 'nonce'); ?>
            
            <!-- Import Name -->
            <div class="form-group">
                <label for="import_name" class="required">
                    <strong><?php _e('Import Name', 'wc-xml-csv-import'); ?></strong>
                    <span class="required-asterisk">*</span>
                </label>
                <input type="text" id="import_name" name="import_name" class="regular-text" required 
                       placeholder="<?php _e('e.g., Summer Collection 2024', 'wc-xml-csv-import'); ?>" />
                <p class="description"><?php _e('Give this import a descriptive name for easy identification.', 'wc-xml-csv-import'); ?></p>
            </div>

            <!-- Upload Method Selection -->
            <div class="form-group">
                <label><strong><?php _e('Upload Method', 'wc-xml-csv-import'); ?></strong></label>
                <div class="upload-method-selection">
                    <label class="upload-method-option">
                        <input type="radio" name="upload_method" value="file" checked />
                        <span class="method-icon">📁</span>
                        <span class="method-title"><?php _e('Upload File', 'wc-xml-csv-import'); ?></span>
                        <span class="method-desc"><?php _e('Upload XML/CSV file from your computer', 'wc-xml-csv-import'); ?></span>
                    </label>
                    <label class="upload-method-option">
                        <input type="radio" name="upload_method" value="url" />
                        <span class="method-icon">🔗</span>
                        <span class="method-title"><?php _e('From URL', 'wc-xml-csv-import'); ?></span>
                        <span class="method-desc"><?php _e('Import from external URL or FTP server', 'wc-xml-csv-import'); ?></span>
                    </label>
                </div>
            </div>

            <!-- File Upload -->
            <div id="file-upload-section" class="form-group upload-section">
                <label for="file_upload"><strong><?php _e('Select File', 'wc-xml-csv-import'); ?></strong></label>
                <div class="file-upload-area" id="file-upload-area">
                    <div class="upload-dropzone">
                        <div class="upload-icon">📤</div>
                        <div class="upload-text">
                            <p class="upload-primary"><?php _e('Drag & drop your file here', 'wc-xml-csv-import'); ?></p>
                            <p class="upload-secondary"><?php _e('or', 'wc-xml-csv-import'); ?></p>
                            <button type="button" class="button button-secondary" id="browse-files">
                                <?php _e('Browse Files', 'wc-xml-csv-import'); ?>
                            </button>
                            <input type="file" id="file_upload" name="file" style="display: none;" />
                        </div>
                        <div class="upload-requirements">
                            <p><?php printf(__('Accepted formats: XML, CSV | Max size: %dMB', 'wc-xml-csv-import'), $max_file_size); ?></p>
                        </div>
                    </div>
                    <div class="file-preview" id="file-preview" style="display: none;">
                        <div class="file-info">
                            <span class="file-name"></span>
                            <span class="file-size"></span>
                            <button type="button" class="remove-file" id="remove-file">❌</button>
                        </div>
                        <div class="upload-progress" id="upload-progress" style="display: none;">
                            <div class="progress-bar">
                                <div class="progress-fill"></div>
                            </div>
                            <span class="progress-text">0%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- URL Input -->
            <div id="url-upload-section" class="form-group upload-section" style="display: none;">
                <label for="file_url"><strong><?php _e('File URL', 'wc-xml-csv-import'); ?></strong></label>
                <input type="url" id="file_url" name="file_url" class="regular-text" 
                       placeholder="<?php _e('https://example.com/products.xml', 'wc-xml-csv-import'); ?>" />
                <p class="description"><?php _e('Enter the direct URL to your XML or CSV file.', 'wc-xml-csv-import'); ?></p>
                <button type="button" id="test-url" class="button button-secondary">
                    <?php _e('Test URL', 'wc-xml-csv-import'); ?>
                </button>
                <div id="url-test-result" class="url-test-result"></div>
            </div>

            <!-- File Type Selection -->
            <div class="form-group">
                <label for="force_file_type"><strong><?php _e('File Type', 'wc-xml-csv-import'); ?></strong></label>
                <select id="force_file_type" name="force_file_type" class="regular-text">
                    <option value="auto"><?php _e('Auto-detect (from extension or content)', 'wc-xml-csv-import'); ?></option>
                    <option value="xml"><?php _e('Force XML', 'wc-xml-csv-import'); ?></option>
                    <option value="csv"><?php _e('Force CSV', 'wc-xml-csv-import'); ?></option>
                </select>
                <p class="description"><?php _e('Select file type manually for URLs without file extension (e.g., API feeds).', 'wc-xml-csv-import'); ?></p>
            </div>

            <!-- XML Product Wrapper -->
            <div id="xml-wrapper-section" class="form-group">
                <label for="product_wrapper">
                    <strong><?php _e('XML Product Element', 'wc-xml-csv-import'); ?></strong>
                </label>
                <input type="text" id="product_wrapper" name="product_wrapper" class="regular-text" 
                       value="" placeholder="product" />
                <p class="description"><?php _e('The XML element name that contains individual product data (e.g., "product", "item", "goods"). Leave as "product" for CSV files.', 'wc-xml-csv-import'); ?></p>
            </div>

            <!-- Schedule Configuration -->
            <?php if ($can_scheduling): ?>
            <div class="form-group">
                <label for="schedule_type"><strong><?php _e('Schedule Type', 'wc-xml-csv-import'); ?></strong></label>
                <select id="schedule_type" name="schedule_type" class="regular-text">
                    <option value="disabled"><?php _e('Manual Import Only', 'wc-xml-csv-import'); ?></option>
                    <option value="15min"><?php _e('Every 15 Minutes', 'wc-xml-csv-import'); ?></option>
                    <option value="hourly"><?php _e('Every Hour', 'wc-xml-csv-import'); ?></option>
                    <option value="6hours"><?php _e('Every 6 Hours', 'wc-xml-csv-import'); ?></option>
                    <option value="daily"><?php _e('Daily Import', 'wc-xml-csv-import'); ?></option>
                    <option value="weekly"><?php _e('Weekly Import', 'wc-xml-csv-import'); ?></option>
                    <option value="monthly"><?php _e('Monthly Import', 'wc-xml-csv-import'); ?></option>
                </select>
                <p class="description"><?php _e('Choose how often this import should run automatically. Manual imports can be run at any time.', 'wc-xml-csv-import'); ?></p>
            </div>
            <?php else: ?>
            <!-- Schedule Configuration - PRO only -->
            <div class="form-group pro-feature-locked" style="opacity: 0.7;">
                <label><strong><?php _e('Schedule Type', 'wc-xml-csv-import'); ?></strong>
                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: bold;">PRO</span>
                </label>
                <select disabled class="regular-text" style="background: #f5f5f5;">
                    <option><?php _e('Manual Import Only', 'wc-xml-csv-import'); ?></option>
                </select>
                <input type="hidden" name="schedule_type" value="disabled" />
                <p class="description" style="color: #6c757d;"><?php _e('Automated scheduled imports are available in the PRO version. Run imports automatically every 15 minutes, hourly, daily, or weekly.', 'wc-xml-csv-import'); ?></p>
            </div>
            <?php endif; ?>

            <!-- Advanced Options -->
            <div class="form-group">
                <h3>
                    <?php _e('Advanced Options', 'wc-xml-csv-import'); ?>
                </h3>
                <div id="advanced-options" class="advanced-options">
                    <div class="advanced-grid">
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="update_existing" value="1" />
                                <?php _e('Update Existing Products', 'wc-xml-csv-import'); ?>
                            </label>
                            <p class="description"><?php _e('Update products that already exist (matched by SKU).', 'wc-xml-csv-import'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="skip_unchanged" value="1" />
                                <?php _e('Skip products if data unchanged', 'wc-xml-csv-import'); ?>
                            </label>
                            <p class="description"><?php _e('Skip updating products if mapped data hasn\'t changed.', 'wc-xml-csv-import'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="create_categories" value="1" checked />
                                <?php _e('Auto-create Categories', 'wc-xml-csv-import'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically create product categories if they don\'t exist.', 'wc-xml-csv-import'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label for="default_status"><?php _e('Default Product Status', 'wc-xml-csv-import'); ?></label>
                            <select name="default_status" id="default_status">
                                <option value="publish"><?php _e('Published', 'wc-xml-csv-import'); ?></option>
                                <option value="draft"><?php _e('Draft', 'wc-xml-csv-import'); ?></option>
                                <option value="private"><?php _e('Private', 'wc-xml-csv-import'); ?></option>
                            </select>
                        </div>
                        <div class="advanced-item">
                            <label for="batch_size"><?php _e('Batch Size', 'wc-xml-csv-import'); ?></label>
                            <input type="number" name="batch_size" id="batch_size" value="50" min="1" max="500" />
                            <p class="description"><?php _e('Number of products to process at once.', 'wc-xml-csv-import'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Missing Products Handling -->
                    <div class="missing-products-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h4 style="margin-top: 0;"><?php _e('Handle Products No Longer in Feed', 'wc-xml-csv-import'); ?></h4>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php _e('What to do with products that were imported before but are no longer present in the XML/CSV file.', 'wc-xml-csv-import'); ?>
                        </p>
                        
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="handle_missing" id="handle_missing" value="1" />
                                <?php _e('Process products that are no longer in feed', 'wc-xml-csv-import'); ?>
                            </label>
                        </div>
                        
                        <div id="missing-products-options" style="margin-left: 25px; margin-top: 10px; display: none;">
                            <div class="advanced-item">
                                <label for="missing_action"><?php _e('Action for missing products:', 'wc-xml-csv-import'); ?></label>
                                <select name="missing_action" id="missing_action" class="regular-text">
                                    <option value="draft"><?php _e('Move to Draft (Recommended)', 'wc-xml-csv-import'); ?></option>
                                    <option value="outofstock"><?php _e('Mark as Out of Stock', 'wc-xml-csv-import'); ?></option>
                                    <option value="backorder"><?php _e('Allow Backorder (stock=0)', 'wc-xml-csv-import'); ?></option>
                                    <option value="trash"><?php _e('Move to Trash (auto-delete after 30 days)', 'wc-xml-csv-import'); ?></option>
                                    <option value="delete"><?php _e('Permanently Delete (⚠️ DANGEROUS)', 'wc-xml-csv-import'); ?></option>
                                </select>
                            </div>
                            
                            <div class="advanced-item" style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox" name="delete_variations" id="delete_variations" value="1" checked />
                                    <?php _e('Also process variations when parent product is missing', 'wc-xml-csv-import'); ?>
                                </label>
                            </div>
                            
                            <p class="description" style="margin-top: 10px; color: #666;">
                                <span style="color: #0073aa;">ℹ️</span> 
                                <?php _e('Action will only affect products that were last updated by THIS import. Products updated by other imports will not be affected.', 'wc-xml-csv-import'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="button button-primary button-large" id="proceed-mapping">
                    <?php _e('Proceed to Field Mapping', 'wc-xml-csv-import'); ?>
                    <span class="button-icon">➡️</span>
                </button>
            </div>

            <!-- Messages -->
            <div id="upload-messages" class="upload-messages"></div>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const uploadForm = $('#wc-ai-import-upload-form');
    const fileInput = $('#file_upload');
    const fileUploadArea = $('#file-upload-area');
    const filePreview = $('#file-preview');
    const uploadProgress = $('#upload-progress');
    const messagesDiv = $('#upload-messages');
    
    // Upload method selection
    $('input[name="upload_method"]').on('change', function() {
        const method = $(this).val();
        if (method === 'file') {
            $('#file-upload-section').show();
            $('#url-upload-section').hide();
        } else {
            $('#file-upload-section').hide();
            $('#url-upload-section').show();
        }
    });
    
    // File type detection - XML wrapper is now always visible
    fileInput.off('change').on('change', function() {
        const file = this.files[0];
        if (file) {
            showFilePreview(file);
        }
    });
    
    // URL input - XML wrapper is now always visible
    // No longer need to show/hide based on URL extension
    
    // Browse files button
    $('#browse-files').off('click').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    // Drag and drop functionality
    fileUploadArea.on({
        dragover: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        },
        dragleave: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        },
        drop: function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                fileInput[0].files = files;
                fileInput.trigger('change');
            }
        }
    });
    
    // Remove file
    $('#remove-file').on('click', function() {
        fileInput.val('');
        filePreview.hide();
        $('#xml-wrapper-section').hide();
    });
    
    // Test URL
    $('#test-url').on('click', function() {
        const url = $('#file_url').val();
        const resultDiv = $('#url-test-result');
        
        if (!url) {
            resultDiv.html('<p class="error">Please enter a URL first.</p>').show();
            return;
        }
        
        $(this).prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_test_url',
                url: url,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<p class="success">✅ URL is accessible and valid.</p>').show();
                } else {
                    resultDiv.html('<p class="error">❌ ' + response.data.message + '</p>').show();
                }
            },
            error: function() {
                resultDiv.html('<p class="error">❌ Failed to test URL.</p>').show();
            },
            complete: function() {
                $('#test-url').prop('disabled', false).text('Test URL');
            }
        });
    });
    
    // Toggle advanced options (backup if admin.js fails)
    $('#toggle-advanced').off('click').on('click', function() {
        console.log('Step-1 toggle clicked');
        const icon = $(this).find('.dashicons');
        const options = $('#advanced-options');
        
        if (options.is(':visible')) {
            options.slideUp();
            icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
        } else {
            options.slideDown();
            icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
        }
    });
    
    // Form submission
    // Prevent duplicate event handlers
    uploadForm.off('submit').on('submit', function(e) {
        e.preventDefault();
        
        // Validation
        if (!$('#import_name').val().trim()) {
            showMessage('Please enter an import name.', 'error');
            return;
        }
        
        const uploadMethod = $('input[name="upload_method"]:checked').val();
        
        if (uploadMethod === 'file' && !fileInput[0].files.length) {
            showMessage('Please select a file to upload.', 'error');
            return;
        }
        
        if (uploadMethod === 'url' && !$('#file_url').val().trim()) {
            showMessage('Please enter a file URL.', 'error');
            return;
        }
        
        // Submit form via AJAX
        const formData = new FormData(this);
        formData.append('action', 'wc_xml_csv_ai_import_upload_file');
        
        const $submitBtn = $('#proceed-mapping');
        $submitBtn.prop('disabled', true).html('<span class="spinner is-active"></span> Uploading and scanning file...');
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 600000, // 10 minutes for large files
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                // Upload progress (only for file uploads, not URL downloads)
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable && uploadMethod === 'file') {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        $submitBtn.html('<span class="spinner is-active"></span> Uploading: ' + Math.round(percentComplete) + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                console.log('=== UPLOAD RESPONSE ===', response);
                if (response.success) {
                    const totalProducts = response.data.total_products || 0;
                    console.log('Total products from response:', totalProducts);
                    showMessage(response.data.message + ' Found ' + totalProducts + ' products.', 'success');
                    
                    // Show product count in button
                    $submitBtn.html('<span class="spinner is-active"></span> Processing ' + totalProducts + ' products. Redirecting...');
                    
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                } else {
                    console.error('Upload failed:', response.data);
                    showMessage(response.data.message, 'error');
                    $submitBtn.prop('disabled', false).html('<?php _e('Proceed to Field Mapping', 'wc-xml-csv-import'); ?> <span class="button-icon">➡️</span>');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showMessage('Upload timed out. The file might be too large.', 'error');
                } else {
                    showMessage('Upload failed: ' + error, 'error');
                }
                $submitBtn.prop('disabled', false).html('<?php _e('Proceed to Field Mapping', 'wc-xml-csv-import'); ?> <span class="button-icon">➡️</span>');
            }
        });
    });
    
    function showFilePreview(file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        
        filePreview.find('.file-name').text(fileName);
        filePreview.find('.file-size').text(fileSize);
        filePreview.show();
    }
    
    function showMessage(message, type) {
        const alertClass = type === 'error' ? 'notice-error' : 'notice-success';
        messagesDiv.html('<div class="notice ' + alertClass + '"><p>' + message + '</p></div>');
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(function() {
                messagesDiv.fadeOut();
            }, 3000);
        }
    }
    
    // Toggle missing products options visibility
    $('#handle_missing').on('change', function() {
        if ($(this).is(':checked')) {
            $('#missing-products-options').slideDown();
        } else {
            $('#missing-products-options').slideUp();
        }
    });
});
</script>