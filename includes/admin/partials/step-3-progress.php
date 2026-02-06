<?php
/**
 * Step 3: Import Progress Interface
 *
 * @since      1.0.0
 * @package    WC_XML_CSV_AI_Import
 * @subpackage WC_XML_CSV_AI_Import/includes/admin/partials
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get import ID from URL
$import_id = isset($_GET['import_id']) ? intval($_GET['import_id']) : 0;

if (!$import_id) {
    echo '<div class="notice notice-error"><p>' . __('Invalid import ID. Please start a new import.', 'wc-xml-csv-import') . '</p></div>';
    return;
}

// Get import details from database
global $wpdb;
$import = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
    $import_id
));

if (!$import) {
    echo '<div class="notice notice-error"><p>' . __('Import not found.', 'wc-xml-csv-import') . '</p></div>';
    return;
}

$percentage = $import->total_products > 0 ? round(($import->processed_products / $import->total_products) * 100) : 0;
?>

<style>
/* Live progress animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.import-status.processing {
    animation: pulse 2s infinite;
}

.progress-circle {
    transition: background 0.5s ease-out;
}

.log-entry {
    padding: 8px 12px;
    margin-bottom: 5px;
    border-left: 3px solid #ddd;
    background: #f9f9f9;
    border-radius: 3px;
    transition: all 0.3s ease;
}

.log-entry:hover {
    background: #f0f0f0;
    border-left-color: #0073aa;
}

.log-entry.error {
    border-left-color: #dc3232;
    background: #fee;
}

.log-entry.success {
    border-left-color: #46b450;
    background: #efe;
}

.log-entry.warning {
    border-left-color: #ffb900;
    background: #fff8e5;
}

.log-entry.info {
    border-left-color: #0073aa;
    background: #f0f6fc;
}

/* Smooth number counter animation */
.stat-value {
    transition: all 0.3s ease;
}
</style>

<div class="wc-ai-import-step wc-ai-import-step-3" data-import-id="<?php echo esc_attr($import_id); ?>" data-batch-size="<?php echo esc_attr($import->batch_size ?? 50); ?>">
    <div class="import-progress-card">
        <h2><?php _e('Import Progress', 'wc-xml-csv-import'); ?></h2>
        <p class="description"><?php printf(__('Importing "%s" - Please keep this page open until the import is complete.', 'wc-xml-csv-import'), esc_html($import->name)); ?></p>
        
        <!-- Cron Info Box -->
        <div class="cron-info-box" style="background: linear-gradient(135deg, #e8f4fd 0%, #f0f7ff 100%); border: 1px solid #0073aa; border-radius: 8px; padding: 15px; margin: 15px 0; display: flex; align-items: flex-start; gap: 12px;">
            <span style="font-size: 24px;">💡</span>
            <div>
                <strong style="color: #0073aa;"><?php _e('Want to close this page?', 'wc-xml-csv-import'); ?></strong>
                <p style="margin: 5px 0 10px; color: #555; font-size: 13px;">
                    <?php _e('Set up a cron job to continue imports in the background, even when the browser is closed.', 'wc-xml-csv-import'); ?>
                </p>
                <details style="cursor: pointer;">
                    <summary style="color: #0073aa; font-weight: 500;"><?php _e('Show cron setup instructions', 'wc-xml-csv-import'); ?></summary>
                    <div style="background: #fff; border-radius: 4px; padding: 12px; margin-top: 10px; font-size: 12px;">
                        <p style="margin: 0 0 8px;"><strong><?php _e('cPanel / Plesk:', 'wc-xml-csv-import'); ?></strong></p>
                        <p style="margin: 0 0 5px;"><?php _e('Add a new Cron Job with:', 'wc-xml-csv-import'); ?></p>
                        <ul style="margin: 0 0 10px; padding-left: 20px;">
                            <li><?php _e('Schedule: Every minute', 'wc-xml-csv-import'); ?> (<code>* * * * *</code>)</li>
                        </ul>
                        <p style="margin: 0 0 5px;"><strong><?php _e('Command:', 'wc-xml-csv-import'); ?></strong></p>
                        <code style="display: block; background: #f5f5f5; padding: 8px; border-radius: 4px; word-break: break-all; font-size: 11px;">wget -q -O /dev/null "<?php echo esc_url(site_url('/wp-cron.php')); ?>" &gt;/dev/null 2&gt;&amp;1</code>
                        <p style="margin: 10px 0 0; color: #666; font-size: 11px;">
                            <?php _e('Once configured, imports will continue automatically even if you close this page.', 'wc-xml-csv-import'); ?>
                        </p>
                    </div>
                </details>
            </div>
        </div>
        
        <!-- Import Status -->
        <div class="import-status <?php echo esc_attr($import->status); ?>">
            <?php 
            switch ($import->status) {
                case 'preparing':
                    _e('Preparing Import...', 'wc-xml-csv-import');
                    break;
                case 'processing':
                    _e('Processing Products...', 'wc-xml-csv-import');
                    break;
                case 'completed':
                    _e('Import Completed Successfully!', 'wc-xml-csv-import');
                    break;
                case 'failed':
                    _e('Import Failed', 'wc-xml-csv-import');
                    break;
                default:
                    _e('Unknown Status', 'wc-xml-csv-import');
                    break;
            }
            ?>
        </div>

        <!-- Progress Circle -->
        <div class="progress-circle" style="background: conic-gradient(#0073aa <?php echo $percentage; ?>%, #f0f0f0 0%);">
            <div class="progress-percentage"><?php echo $percentage; ?>%</div>
        </div>

        <!-- Import Statistics -->
        <div class="import-stats">
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($import->total_products); ?></span>
                <span class="stat-label"><?php _e('Total Products', 'wc-xml-csv-import'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($import->processed_products); ?></span>
                <span class="stat-label"><?php _e('Processed', 'wc-xml-csv-import'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($import->total_products - $import->processed_products); ?></span>
                <span class="stat-label"><?php _e('Remaining', 'wc-xml-csv-import'); ?></span>
            </div>
        </div>

        <!-- Import Details -->
        <div class="import-details">
            <table class="wp-list-table widefat">
                <tr>
                    <td><strong><?php _e('Import Name:', 'wc-xml-csv-import'); ?></strong></td>
                    <td><?php echo esc_html($import->name); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('File Type:', 'wc-xml-csv-import'); ?></strong></td>
                    <td><?php echo strtoupper($import->file_type); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Started:', 'wc-xml-csv-import'); ?></strong></td>
                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import->created_at)); ?></td>
                </tr>
                <?php if ($import->last_run): ?>
                <tr>
                    <td><strong><?php _e('Last Activity:', 'wc-xml-csv-import'); ?></strong></td>
                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import->last_run)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?php _e('Schedule:', 'wc-xml-csv-import'); ?></strong></td>
                    <td><?php echo ucfirst($import->schedule_type); ?></td>
                </tr>
            </table>
        </div>

        <!-- Import Controls -->
        <div class="import-controls" style="margin: 20px 0;">
            <?php if ($import->status === 'processing'): ?>
                <button type="button" id="pause-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php _e('Pause Import', 'wc-xml-csv-import'); ?>
                </button>
                <button type="button" id="stop-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-stop"></span>
                    <?php _e('Stop Import', 'wc-xml-csv-import'); ?>
                </button>
            <?php elseif ($import->status === 'paused'): ?>
                <button type="button" id="resume-import" class="button button-primary">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Resume Import', 'wc-xml-csv-import'); ?>
                </button>
                <button type="button" id="stop-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-stop"></span>
                    <?php _e('Stop Import', 'wc-xml-csv-import'); ?>
                </button>
            <?php elseif ($import->status === 'failed'): ?>
                <button type="button" id="retry-import" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Retry Import', 'wc-xml-csv-import'); ?>
                </button>
            <?php endif; ?>

            <button type="button" id="view-logs" class="button button-secondary">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('View Detailed Logs', 'wc-xml-csv-import'); ?>
            </button>
        </div>

        <!-- Real-time Logs -->
        <div class="import-logs-section">
            <h3>
                <span class="dashicons dashicons-admin-page"></span>
                <?php _e('Recent Activity', 'wc-xml-csv-import'); ?>
            </h3>
            <div class="import-logs" id="import-logs">
                <?php
                // Get recent logs - ordered by ID for consistency (no jumping)
                $logs = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wc_itp_import_logs WHERE import_id = %d ORDER BY id DESC LIMIT 15",
                    $import_id
                ));

                if (!empty($logs)) {
                    foreach ($logs as $log) {
                        $log_class = esc_attr($log->level);
                        $timestamp = date_i18n('H:i:s', strtotime($log->created_at));
                        echo '<div class="log-entry ' . $log_class . '">';
                        echo '[' . $timestamp . '] ' . esc_html($log->message);
                        if (!empty($log->product_sku)) {
                            echo ' (SKU: ' . esc_html($log->product_sku) . ')';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<div class="log-entry info">' . __('No logs available yet.', 'wc-xml-csv-import') . '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Performance Metrics -->
        <?php if ($import->status === 'processing' || $import->status === 'completed'): ?>
        <div class="performance-metrics" style="margin-top: 20px;">
            <h3>
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Performance Metrics', 'wc-xml-csv-import'); ?>
            </h3>
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="metric-item">
                    <div class="metric-label"><?php _e('Processing Speed', 'wc-xml-csv-import'); ?></div>
                    <div class="metric-value" id="processing-speed">
                        <?php
                        if ($import->last_run && $import->created_at) {
                            $elapsed = strtotime($import->last_run) - strtotime($import->created_at);
                            $rate = $elapsed > 0 ? round($import->processed_products / ($elapsed / 60), 2) : 0;
                            echo $rate . ' ' . __('products/min', 'wc-xml-csv-import');
                        } else {
                            echo __('Calculating...', 'wc-xml-csv-import');
                        }
                        ?>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><?php _e('Estimated Time Remaining', 'wc-xml-csv-import'); ?></div>
                    <div class="metric-value" id="time-remaining">
                        <?php
                        if ($import->processed_products > 0 && $import->total_products > $import->processed_products) {
                            $remaining = $import->total_products - $import->processed_products;
                            if (isset($rate) && $rate > 0) {
                                $eta_minutes = round($remaining / $rate);
                                if ($eta_minutes < 60) {
                                    echo $eta_minutes . ' ' . __('minutes', 'wc-xml-csv-import');
                                } else {
                                    $eta_hours = floor($eta_minutes / 60);
                                    $eta_mins = $eta_minutes % 60;
                                    echo $eta_hours . 'h ' . $eta_mins . 'm';
                                }
                            } else {
                                echo __('Calculating...', 'wc-xml-csv-import');
                            }
                        } else {
                            echo __('N/A', 'wc-xml-csv-import');
                        }
                        ?>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><?php _e('Success Rate', 'wc-xml-csv-import'); ?></div>
                    <div class="metric-value" id="success-rate">
                        <?php
                        $error_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_itp_import_logs WHERE import_id = %d AND level = 'error'",
                            $import_id
                        ));
                        $success_rate = $import->processed_products > 0 ? round((($import->processed_products - $error_count) / $import->processed_products) * 100, 1) : 100;
                        echo $success_rate . '%';
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions after completion -->
        <?php if ($import->status === 'completed'): ?>
        <div class="completion-actions" style="margin-top: 30px; padding: 20px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 6px;">
            <h3><?php _e('Import Completed Successfully!', 'wc-xml-csv-import'); ?></h3>
            <p><?php printf(__('Successfully imported %d out of %d products.', 'wc-xml-csv-import'), $import->processed_products, $import->total_products); ?></p>
            
            <div class="action-buttons" style="margin-top: 15px;">
                <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-products"></span>
                    <?php _e('View Products', 'wc-xml-csv-import'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Start New Import', 'wc-xml-csv-import'); ?>
                </a>
                
                <button type="button" id="download-report" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Download Report', 'wc-xml-csv-import'); ?>
                </button>
                
                <?php if ($import->schedule_type !== 'disabled'): ?>
                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import-settings'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Manage Scheduled Import', 'wc-xml-csv-import'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error information -->
        <?php if ($import->status === 'failed'): ?>
        <div class="error-information" style="margin-top: 20px; padding: 20px; background: #fee; border: 1px solid #dc3545; border-radius: 6px;">
            <h3><?php _e('Import Failed', 'wc-xml-csv-import'); ?></h3>
            <p><?php _e('The import process encountered an error and could not be completed. Please check the logs below for more details.', 'wc-xml-csv-import'); ?></p>
            
            <?php
            $error_logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_import_logs WHERE import_id = %d AND level = 'error' ORDER BY created_at DESC LIMIT 5",
                $import_id
            ));
            
            if (!empty($error_logs)): ?>
            <div class="error-logs">
                <h4><?php _e('Recent Errors:', 'wc-xml-csv-import'); ?></h4>
                <?php foreach ($error_logs as $error_log): ?>
                <div class="error-log-entry">
                    <strong><?php echo date_i18n('H:i:s', strtotime($error_log->created_at)); ?></strong>: 
                    <?php echo esc_html($error_log->message); ?>
                    <?php if (!empty($error_log->product_sku)): ?>
                        (SKU: <?php echo esc_html($error_log->product_sku); ?>)
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="error-actions" style="margin-top: 15px;">
                <button type="button" id="retry-import" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Retry Import', 'wc-xml-csv-import'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php _e('Start Over', 'wc-xml-csv-import'); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var importId = $('.wc-ai-import-step-3').data('import-id');
    var batchSize = parseInt($('.wc-ai-import-step-3').data('batch-size')) || 50;
    var progressInterval;
    var importKickstarted = false;
    var currentProcessed = 0; // Track current processed count for animation
    var currentTotal = 0; // Track total
    var lastLogId = 0; // Track last log ID to avoid duplicates
    
    // Start monitoring if import is still processing
    var importStatus = '<?php echo esc_js($import->status); ?>';
    var processedProducts = <?php echo intval($import->processed_products); ?>;
    var totalProducts = <?php echo intval($import->total_products); ?>;
    
    console.log('Import Progress Monitor initialized:', {
        importId: importId,
        batchSize: batchSize,
        status: importStatus,
        processed: processedProducts,
        total: totalProducts
    });
    
    // Initialize current percentage and counts from server
    currentPercentage = totalProducts > 0 ? Math.round((processedProducts / totalProducts) * 100) : 0;
    currentProcessed = processedProducts;
    currentTotal = totalProducts;
    
    // Kickstart import if it's stuck at 0% (happens after re-run or pending status)
    if ((importStatus === 'processing' || importStatus === 'pending') && processedProducts === 0 && totalProducts > 0 && !importKickstarted) {
        console.log('Import appears stuck at 0% - kickstarting...');
        importKickstarted = true;
        
        // Reset progress to 0% before kickstart
        updateProgressBar(0);
        
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_kickstart',
                import_id: importId,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                console.log('Kickstart response:', response);
                // Start monitoring after kickstart
                setTimeout(function() {
                    startProgressMonitoring();
                }, 500); // Reduced delay for faster response
            },
            error: function() {
                console.error('Failed to kickstart import');
                // Start monitoring anyway
                startProgressMonitoring();
            }
        });
    } else if (importStatus === 'processing' || importStatus === 'preparing' || importStatus === 'pending') {
        // Start monitoring immediately for active imports
        startProgressMonitoring();
    }
    
    function startProgressMonitoring() {
        console.log('Starting progress monitoring...');
        
        // Initial update
        updateProgressImmediately();
        
        // Poll every 1 second for more responsive UI
        progressInterval = setInterval(function() {
            updateProgressImmediately();
        }, 1000); // Update every 1 second (was 3 seconds)
        
        // CRITICAL: Ping WP-Cron every 2 seconds to keep it running
        // This also reschedules stuck imports automatically
        setInterval(function() {
            pingCron();
        }, 2000); // Ping every 2 seconds
        
        // Initial cron ping
        pingCron();
    }
    
    function pingCron() {
        // Use AJAX POST for reliability instead of Image GET
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_ping_cron',
                import_id: importId
            },
            timeout: 5000, // 5 second timeout
            success: function(response) {
                // Silent success - cron was triggered
            },
            error: function() {
                // Silent fail - will retry in 2 seconds
            }
        });
    }
    
    function updateProgressImmediately() {
        $.ajax({
            url: wc_xml_csv_ai_import_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_xml_csv_ai_import_get_progress',
                import_id: importId,
                nonce: wc_xml_csv_ai_import_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Progress update:', response.data);
                    updateProgress(response.data);
                    
                    // NOTE: Batch processing happens via WP-Cron in background
                    // DO NOT trigger AJAX batch processing - it causes timeouts with AI processing
                    // Just monitor progress and let WP-Cron handle the actual work
                    
                    if (response.data.status === 'completed' || response.data.status === 'failed') {
                        clearInterval(progressInterval);
                        console.log('Import finished with status:', response.data.status);
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to get progress update:', error);
            }
        });
    }
    
    function updateProgressBar(targetPercentage) {
        // Smooth animation 1% at a time
        if (currentPercentage === targetPercentage) {
            return; // Already at target
        }
        
        // Clear any existing animation
        if (window.progressAnimationInterval) {
            clearInterval(window.progressAnimationInterval);
        }
        
        // Animate 1% per step, 100ms per step = smooth visible progress
        window.progressAnimationInterval = setInterval(function() {
            if (currentPercentage < targetPercentage) {
                currentPercentage++;
            } else if (currentPercentage > targetPercentage) {
                currentPercentage--;
            }
            
            // Update progress circle
            $('.progress-circle').css('background', 'conic-gradient(#0073aa ' + currentPercentage + '%, #f0f0f0 0%)');
            $('.progress-percentage').text(currentPercentage + '%');
            
            // Stop when reached target
            if (currentPercentage === targetPercentage) {
                clearInterval(window.progressAnimationInterval);
            }
        }, 100); // 100ms per 1% = smooth visible animation
    }
    
    function updateProductCounts(targetProcessed, targetTotal) {
        // Smooth animation for product counts
        if (currentProcessed === targetProcessed && currentTotal === targetTotal) {
            return; // Already at target
        }
        
        // Clear any existing animation
        if (window.countAnimationInterval) {
            clearInterval(window.countAnimationInterval);
        }
        
        // Animate counts one by one, faster than percentage (50ms per product)
        window.countAnimationInterval = setInterval(function() {
            var changed = false;
            
            if (currentProcessed < targetProcessed) {
                currentProcessed++;
                changed = true;
            } else if (currentProcessed > targetProcessed) {
                currentProcessed--;
                changed = true;
            }
            
            if (currentTotal < targetTotal) {
                currentTotal++;
                changed = true;
            } else if (currentTotal > targetTotal) {
                currentTotal--;
                changed = true;
            }
            
            // Update displayed values
            $('.stat-item').eq(0).find('.stat-value').text(Number(currentTotal).toLocaleString());
            $('.stat-item').eq(1).find('.stat-value').text(Number(currentProcessed).toLocaleString());
            $('.stat-item').eq(2).find('.stat-value').text(Number(currentTotal - currentProcessed).toLocaleString());
            
            // Stop when reached target
            if (!changed || (currentProcessed === targetProcessed && currentTotal === targetTotal)) {
                clearInterval(window.countAnimationInterval);
            }
        }, 50); // 50ms per product = faster than percentage
    }
    
    function updateProgress(data) {
        var percentage = data.percentage || 0;
        
        // Smooth progress bar update (1% at a time)
        updateProgressBar(percentage);
        
        // Smooth product count update (1 product at a time)
        updateProductCounts(data.processed_products || 0, data.total_products || 0);
        
        // Update status text and styling
        var statusText = '';
        switch(data.status) {
            case 'preparing':
                statusText = '<?php _e('Preparing Import...', 'wc-xml-csv-import'); ?>';
                break;
            case 'processing':
                statusText = '<?php _e('Processing Products...', 'wc-xml-csv-import'); ?>';
                break;
            case 'completed':
                statusText = '<?php _e('Import Completed Successfully!', 'wc-xml-csv-import'); ?>';
                break;
            case 'failed':
                statusText = '<?php _e('Import Failed', 'wc-xml-csv-import'); ?>';
                break;
        }
        $('.import-status').removeClass('preparing processing completed failed').addClass(data.status).text(statusText);
        
        // Update logs with live feed - prepend new logs, keep only latest
        if (data.logs && data.logs.length > 0) {
            updateLiveLogs(data.logs);
        }
    }
    
    function updateLiveLogs(logs) {
        var $logsContainer = $('#import-logs');
        
        // Logs come sorted by ID DESC (newest first)
        // Simply replace all logs with fresh data to avoid jumping/flickering
        // This is cleaner and more reliable than trying to merge
        
        if (!logs || logs.length === 0) {
            return;
        }
        
        // Check if we have new logs by comparing the newest log ID
        var newestLogId = parseInt(logs[0].id);
        if (newestLogId <= lastLogId) {
            return; // No new logs, don't update
        }
        lastLogId = newestLogId;
        
        // Build all log entries HTML at once
        var logsHtml = '';
        logs.forEach(function(log) {
            var timestamp = new Date(log.created_at).toLocaleTimeString();
            logsHtml += '<div class="log-entry ' + log.level + '" data-log-id="' + log.id + '">';
            logsHtml += '[' + timestamp + '] ' + escapeHtml(log.message);
            if (log.product_sku) {
                logsHtml += ' (SKU: ' + escapeHtml(log.product_sku) + ')';
            }
            logsHtml += '</div>';
        });
        
        // Replace all content at once (no flickering, no jumping)
        $logsContainer.html(logsHtml);
    }
    
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Old updateProgress function - remove/replace above
    /*
    function updateProgress(data) {
        // Update progress circle
        var percentage = data.percentage || 0;
        $('.progress-circle').css('background', 'conic-gradient(#0073aa ' + percentage + '%, #f0f0f0 0%)');
        $('.progress-percentage').text(percentage + '%');
        
        // Update status
        $('.import-status').removeClass('preparing processing completed failed').addClass(data.status);
        
        // Update stats
        $('.stat-item').eq(0).find('.stat-value').text(data.total_products || 0);
        $('.stat-item').eq(1).find('.stat-value').text(data.processed_products || 0);
        $('.stat-item').eq(2).find('.stat-value').text((data.total_products - data.processed_products) || 0);
        
        // Update logs
        if (data.logs && data.logs.length > 0) {
            var logsHtml = '';
            data.logs.slice(0, 10).forEach(function(log) {
                var timestamp = new Date(log.created_at).toLocaleTimeString();
                logsHtml += '<div class="log-entry ' + log.level + '">';
                logsHtml += '[' + timestamp + '] ' + log.message;
                if (log.product_sku) {
                    logsHtml += ' (SKU: ' + log.product_sku + ')';
                }
                logsHtml += '</div>';
            });
            $('#import-logs').html(logsHtml);
        }
    }
    */
    
    // Import control buttons
    $('#pause-import, #resume-import, #stop-import, #retry-import').on('click', function() {
        var action = $(this).attr('id').replace('-import', '');
        
        if (confirm('Are you sure you want to ' + action + ' this import?')) {
            $.ajax({
                url: wc_xml_csv_ai_import_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_xml_csv_ai_import_control_import',
                    import_id: importId,
                    control_action: action,
                    nonce: wc_xml_csv_ai_import_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to ' + action + ' import: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Failed to ' + action + ' import.');
                }
            });
        }
    });
    
    // Download report
    $('#download-report').on('click', function() {
        window.open(
            wc_xml_csv_ai_import_ajax.ajax_url + 
            '?action=wc_xml_csv_ai_import_download_report&import_id=' + importId + 
            '&nonce=' + wc_xml_csv_ai_import_ajax.nonce,
            '_blank'
        );
    });
    
    // View detailed logs
    $('#view-logs').on('click', function() {
        window.open(
            '<?php echo admin_url("admin.php?page=wc-xml-csv-import-logs&import_id="); ?>' + importId,
            '_blank'
        );
    });
    
    // Auto-refresh page title with progress
    function updatePageTitle() {
        var percentage = $('.progress-percentage').text();
        var originalTitle = document.title.split(' - ')[0];
        document.title = originalTitle + ' - ' + percentage + ' Complete';
    }
    
    if (importStatus === 'processing') {
        setInterval(updatePageTitle, 5000);
    }
});
</script>