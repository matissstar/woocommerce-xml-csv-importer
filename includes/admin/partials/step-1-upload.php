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
$can_scheduling = WC_XML_CSV_AI_Import_Features::is_available('scheduled_import');
$can_import_url = WC_XML_CSV_AI_Import_Features::is_available('remote_url_import');
?>

<div class="wc-ai-import-step wc-ai-import-step-1">
    <div class="wc-ai-import-card">
        <h2><?php esc_html_e('Step 1: Upload File', 'bootflow-product-importer'); ?></h2>
        <p class="description"><?php esc_html_e('Upload your XML or CSV file, or provide a URL to import products from.', 'bootflow-product-importer'); ?></p>
        
        <form id="wc-ai-import-upload-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wc_xml_csv_ai_import_nonce', 'nonce'); ?>
            
            <!-- Import Name -->
            <div class="form-group">
                <label for="import_name" class="required">
                    <strong><?php esc_html_e('Import Name', 'bootflow-product-importer'); ?></strong>
                    <span class="required-asterisk">*</span>
                </label>
                <input type="text" id="import_name" name="import_name" class="regular-text" required 
                       placeholder="<?php esc_html_e('e.g., Summer Collection 2024', 'bootflow-product-importer'); ?>" />
                <p class="description"><?php esc_html_e('Give this import a descriptive name for easy identification.', 'bootflow-product-importer'); ?></p>
            </div>

            <!-- Upload Method Selection -->
            <div class="form-group">
                <label><strong><?php esc_html_e('Upload Method', 'bootflow-product-importer'); ?></strong></label>
                <div class="upload-method-selection">
                    <label class="upload-method-option">
                        <input type="radio" name="upload_method" value="file" checked />
                        <span class="method-icon">📁</span>
                        <span class="method-title"><?php esc_html_e('Upload File', 'bootflow-product-importer'); ?></span>
                        <span class="method-desc"><?php esc_html_e('Upload XML/CSV file from your computer', 'bootflow-product-importer'); ?></span>
                    </label>
                    <label class="upload-method-option<?php echo !$can_import_url ? ' pro-feature-disabled' : ''; ?>">
                        <input type="radio" name="upload_method" value="url" <?php echo !$can_import_url ? 'disabled' : ''; ?> />
                        <span class="method-icon">🔗</span>
                        <span class="method-title">
                            <?php esc_html_e('From URL', 'bootflow-product-importer'); ?>
                            <?php if (!$can_import_url): ?>
                                <span class="pro-badge">PRO</span>
                            <?php endif; ?>
                        </span>
                        <span class="method-desc"><?php esc_html_e('Import from external URL or FTP server', 'bootflow-product-importer'); ?></span>
                    </label>
                </div>
            </div>

            <!-- File Upload -->
            <div id="file-upload-section" class="form-group upload-section">
                <label for="file_upload"><strong><?php esc_html_e('Select File', 'bootflow-product-importer'); ?></strong></label>
                <div class="file-upload-area" id="file-upload-area">
                    <div class="upload-dropzone">
                        <div class="upload-icon">📤</div>
                        <div class="upload-text">
                            <p class="upload-primary"><?php esc_html_e('Drag & drop your file here', 'bootflow-product-importer'); ?></p>
                            <p class="upload-secondary"><?php esc_html_e('or', 'bootflow-product-importer'); ?></p>
                            <button type="button" class="button button-secondary" id="browse-files">
                                <?php esc_html_e('Browse Files', 'bootflow-product-importer'); ?>
                            </button>
                            <input type="file" id="file_upload" name="file" style="display: none;" />
                        </div>
                        <div class="upload-requirements">
                            <p><?php 
                            // translators: %d is the maximum file size in MB
                            printf(esc_html__('Accepted formats: XML, CSV | Max size: %dMB', 'bootflow-product-importer'), intval($max_file_size)); ?></p>
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
                <label for="file_url"><strong><?php esc_html_e('File URL', 'bootflow-product-importer'); ?></strong></label>
                <input type="url" id="file_url" name="file_url" class="regular-text" 
                       placeholder="<?php esc_html_e('https://example.com/products.xml', 'bootflow-product-importer'); ?>" />
                <p class="description"><?php esc_html_e('Enter the direct URL to your XML or CSV file.', 'bootflow-product-importer'); ?></p>
                <button type="button" id="test-url" class="button button-secondary">
                    <?php esc_html_e('Test URL', 'bootflow-product-importer'); ?>
                </button>
                <div id="url-test-result" class="url-test-result"></div>
            </div>

            <!-- File Type Selection -->
            <div class="form-group">
                <label for="force_file_type"><strong><?php esc_html_e('File Type', 'bootflow-product-importer'); ?></strong></label>
                <select id="force_file_type" name="force_file_type" class="regular-text">
                    <option value="auto"><?php esc_html_e('Auto-detect (from extension or content)', 'bootflow-product-importer'); ?></option>
                    <option value="xml"><?php esc_html_e('Force XML', 'bootflow-product-importer'); ?></option>
                    <option value="csv"><?php esc_html_e('Force CSV', 'bootflow-product-importer'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Select file type manually for URLs without file extension (e.g., API feeds).', 'bootflow-product-importer'); ?></p>
            </div>

            <!-- XML Product Wrapper -->
            <div id="xml-wrapper-section" class="form-group">
                <label for="product_wrapper">
                    <strong><?php esc_html_e('XML Product Element', 'bootflow-product-importer'); ?></strong>
                </label>
                <input type="text" id="product_wrapper" name="product_wrapper" class="regular-text" 
                       value="" placeholder="product" />
                <p class="description"><?php esc_html_e('The XML element name that contains individual product data (e.g., "product", "item", "goods"). Leave as "product" for CSV files.', 'bootflow-product-importer'); ?></p>
            </div>

            <!-- Schedule Configuration -->
            <?php if ($can_scheduling): ?>
            <div class="form-group">
                <label for="schedule_type"><strong><?php esc_html_e('Schedule Type', 'bootflow-product-importer'); ?></strong></label>
                <select id="schedule_type" name="schedule_type" class="regular-text">
                    <option value="disabled"><?php esc_html_e('Manual Import Only', 'bootflow-product-importer'); ?></option>
                    <option value="15min"><?php esc_html_e('Every 15 Minutes', 'bootflow-product-importer'); ?></option>
                    <option value="hourly"><?php esc_html_e('Every Hour', 'bootflow-product-importer'); ?></option>
                    <option value="6hours"><?php esc_html_e('Every 6 Hours', 'bootflow-product-importer'); ?></option>
                    <option value="daily"><?php esc_html_e('Daily Import', 'bootflow-product-importer'); ?></option>
                    <option value="weekly"><?php esc_html_e('Weekly Import', 'bootflow-product-importer'); ?></option>
                    <option value="monthly"><?php esc_html_e('Monthly Import', 'bootflow-product-importer'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Choose how often this import should run automatically. Manual imports can be run at any time.', 'bootflow-product-importer'); ?></p>
            </div>
            <?php else: ?>
            <!-- Schedule Configuration - PRO only -->
            <div class="form-group pro-feature-locked" style="opacity: 0.7;">
                <label><strong><?php esc_html_e('Schedule Type', 'bootflow-product-importer'); ?></strong>
                    <span style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px; font-weight: bold;">PRO</span>
                </label>
                <select disabled class="regular-text" style="background: #f5f5f5;">
                    <option><?php esc_html_e('Manual Import Only', 'bootflow-product-importer'); ?></option>
                </select>
                <input type="hidden" name="schedule_type" value="disabled" />
                <p class="description" style="color: #6c757d;"><?php esc_html_e('Automated scheduled imports are available in the PRO version. Run imports automatically every 15 minutes, hourly, daily, or weekly.', 'bootflow-product-importer'); ?></p>
            </div>
            <?php endif; ?>

            <!-- Advanced Options -->
            <div class="form-group">
                <h3>
                    <?php esc_html_e('Advanced Options', 'bootflow-product-importer'); ?>
                </h3>
                <div id="advanced-options" class="advanced-options">
                    <div class="advanced-grid">
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="update_existing" value="1" />
                                <?php esc_html_e('Update Existing Products', 'bootflow-product-importer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Update products that already exist (matched by SKU).', 'bootflow-product-importer'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="skip_unchanged" value="1" />
                                <?php esc_html_e('Skip products if data unchanged', 'bootflow-product-importer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Skip updating products if mapped data hasn\'t changed.', 'bootflow-product-importer'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="create_categories" value="1" checked />
                                <?php esc_html_e('Auto-create Categories', 'bootflow-product-importer'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Automatically create product categories if they don\'t exist.', 'bootflow-product-importer'); ?></p>
                        </div>
                        <div class="advanced-item">
                            <label for="default_status"><?php esc_html_e('Default Product Status', 'bootflow-product-importer'); ?></label>
                            <select name="default_status" id="default_status">
                                <option value="publish"><?php esc_html_e('Published', 'bootflow-product-importer'); ?></option>
                                <option value="draft"><?php esc_html_e('Draft', 'bootflow-product-importer'); ?></option>
                                <option value="private"><?php esc_html_e('Private', 'bootflow-product-importer'); ?></option>
                            </select>
                        </div>
                        <div class="advanced-item">
                            <label for="batch_size"><?php esc_html_e('Batch Size', 'bootflow-product-importer'); ?></label>
                            <input type="number" name="batch_size" id="batch_size" value="50" min="1" max="500" />
                            <p class="description"><?php esc_html_e('Number of products to process at once.', 'bootflow-product-importer'); ?></p>
                        </div>
                    </div>
                    
                    <!-- Missing Products Handling -->
                    <div class="missing-products-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('Handle Products No Longer in Feed', 'bootflow-product-importer'); ?></h4>
                        <p class="description" style="margin-bottom: 15px;">
                            <?php esc_html_e('What to do with products that were imported before but are no longer present in the XML/CSV file.', 'bootflow-product-importer'); ?>
                        </p>
                        
                        <div class="advanced-item">
                            <label>
                                <input type="checkbox" name="handle_missing" id="handle_missing" value="1" />
                                <?php esc_html_e('Process products that are no longer in feed', 'bootflow-product-importer'); ?>
                            </label>
                        </div>
                        
                        <div id="missing-products-options" style="margin-left: 25px; margin-top: 10px; display: none;">
                            <div class="advanced-item">
                                <label for="missing_action"><?php esc_html_e('Action for missing products:', 'bootflow-product-importer'); ?></label>
                                <select name="missing_action" id="missing_action" class="regular-text">
                                    <option value="draft"><?php esc_html_e('Move to Draft (Recommended)', 'bootflow-product-importer'); ?></option>
                                    <option value="outofstock"><?php esc_html_e('Mark as Out of Stock', 'bootflow-product-importer'); ?></option>
                                    <option value="backorder"><?php esc_html_e('Allow Backorder (stock=0)', 'bootflow-product-importer'); ?></option>
                                    <option value="trash"><?php esc_html_e('Move to Trash (auto-delete after 30 days)', 'bootflow-product-importer'); ?></option>
                                    <option value="delete"><?php esc_html_e('Permanently Delete (⚠️ DANGEROUS)', 'bootflow-product-importer'); ?></option>
                                </select>
                            </div>
                            
                            <div class="advanced-item" style="margin-top: 10px;">
                                <label>
                                    <input type="checkbox" name="delete_variations" id="delete_variations" value="1" checked />
                                    <?php esc_html_e('Also process variations when parent product is missing', 'bootflow-product-importer'); ?>
                                </label>
                            </div>
                            
                            <p class="description" style="margin-top: 10px; color: #666;">
                                <span style="color: #0073aa;">ℹ️</span> 
                                <?php esc_html_e('Action will only affect products that were last updated by THIS import. Products updated by other imports will not be affected.', 'bootflow-product-importer'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-actions">
                <button type="submit" class="button button-primary button-large" id="proceed-mapping">
                    <?php esc_html_e('Proceed to Field Mapping', 'bootflow-product-importer'); ?>
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
                    $submitBtn.prop('disabled', false).html('<?php esc_html_e('Proceed to Field Mapping', 'bootflow-product-importer'); ?> <span class="button-icon">➡️</span>');
                }
            },
            error: function(xhr, status, error) {
                if (status === 'timeout') {
                    showMessage('Upload timed out. The file might be too large.', 'error');
                } else {
                    showMessage('Upload failed: ' + error, 'error');
                }
                $submitBtn.prop('disabled', false).html('<?php esc_html_e('Proceed to Field Mapping', 'bootflow-product-importer'); ?> <span class="button-icon">➡️</span>');
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