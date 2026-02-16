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
    echo '<div class="notice notice-error"><p>' . __('Invalid import ID. Please start a new import.', 'bootflow-woocommerce-xml-csv-importer') . '</p></div>';
    return;
}

// Get import details from database
global $wpdb;
$import = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wc_itp_imports WHERE id = %d",
    $import_id
));

if (!$import) {
    echo '<div class="notice notice-error"><p>' . __('Import not found.', 'bootflow-woocommerce-xml-csv-importer') . '</p></div>';
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

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.import-status.processing {
    animation: pulse 2s infinite;
}

.progress-circle {
    transition: background 0.5s ease-out;
}

/* Live Progress Section - Fixed */
.live-progress-section {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Product Activity Log */
.import-logs {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 12px;
    scrollbar-width: thin;
    scrollbar-color: #ccc #f5f5f5;
}

.import-logs::-webkit-scrollbar {
    width: 8px;
}

.import-logs::-webkit-scrollbar-track {
    background: #f5f5f5;
    border-radius: 4px;
}

.import-logs::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 4px;
}

.import-logs::-webkit-scrollbar-thumb:hover {
    background: #aaa;
}

.log-entry {
    padding: 8px 12px;
    margin-bottom: 4px;
    border-left: 3px solid #ddd;
    background: #f9f9f9;
    border-radius: 3px;
    transition: all 0.2s ease;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    animation: slideIn 0.3s ease;
}

.log-entry:hover {
    background: #f0f0f0;
    border-left-color: #0073aa;
}

.log-entry .log-icon {
    flex-shrink: 0;
    font-size: 14px;
}

.log-entry .log-time {
    color: #888;
    flex-shrink: 0;
    font-size: 11px;
}

.log-entry .log-message {
    flex: 1;
    word-break: break-word;
}

.log-entry .log-sku {
    color: #0073aa;
    font-weight: 500;
    flex-shrink: 0;
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
        <h2><?php _e('Import Progress', 'bootflow-woocommerce-xml-csv-importer'); ?></h2>
        <p class="description"><?php printf(__('Importing "%s" - Please keep this page open until the import is complete.', 'bootflow-woocommerce-xml-csv-importer'), esc_html($import->name)); ?></p>
        
        <!-- Cron Info Box -->
        <div class="cron-info-box" style="background: linear-gradient(135deg, #e8f4fd 0%, #f0f7ff 100%); border: 1px solid #0073aa; border-radius: 8px; padding: 15px; margin: 15px 0; display: flex; align-items: flex-start; gap: 12px;">
            <span style="font-size: 24px;">💡</span>
            <div>
                <strong style="color: #0073aa;"><?php _e('Want to close this page?', 'bootflow-woocommerce-xml-csv-importer'); ?></strong>
                <p style="margin: 5px 0 10px; color: #555; font-size: 13px;">
                    <?php _e('Set up a cron job to continue imports in the background, even when the browser is closed.', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </p>
                <details style="cursor: pointer;">
                    <summary style="color: #0073aa; font-weight: 500;"><?php _e('Show cron setup instructions', 'bootflow-woocommerce-xml-csv-importer'); ?></summary>
                    <div style="background: #fff; border-radius: 4px; padding: 12px; margin-top: 10px; font-size: 12px;">
                        <p style="margin: 0 0 8px;"><strong><?php _e('cPanel / Plesk:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></p>
                        <p style="margin: 0 0 5px;"><?php _e('Add a new Cron Job with:', 'bootflow-woocommerce-xml-csv-importer'); ?></p>
                        <ul style="margin: 0 0 10px; padding-left: 20px;">
                            <li><?php _e('Schedule: Every minute', 'bootflow-woocommerce-xml-csv-importer'); ?> (<code>* * * * *</code>)</li>
                        </ul>
                        <p style="margin: 0 0 5px;"><strong><?php _e('Command:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></p>
                        <code style="display: block; background: #f5f5f5; padding: 8px; border-radius: 4px; word-break: break-all; font-size: 11px;">wget -q -O /dev/null "<?php echo esc_url(site_url('/wp-cron.php')); ?>" &gt;/dev/null 2&gt;&amp;1</code>
                        <p style="margin: 10px 0 0; color: #666; font-size: 11px;">
                            <?php _e('Once configured, imports will continue automatically even if you close this page.', 'bootflow-woocommerce-xml-csv-importer'); ?>
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
                    _e('Preparing Import...', 'bootflow-woocommerce-xml-csv-importer');
                    break;
                case 'processing':
                    _e('Processing Products...', 'bootflow-woocommerce-xml-csv-importer');
                    break;
                case 'completed':
                    _e('Import Completed Successfully!', 'bootflow-woocommerce-xml-csv-importer');
                    break;
                case 'failed':
                    _e('Import Failed', 'bootflow-woocommerce-xml-csv-importer');
                    break;
                default:
                    _e('Unknown Status', 'bootflow-woocommerce-xml-csv-importer');
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
                <span class="stat-label"><?php _e('Total Products', 'bootflow-woocommerce-xml-csv-importer'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($import->processed_products); ?></span>
                <span class="stat-label"><?php _e('Processed', 'bootflow-woocommerce-xml-csv-importer'); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo number_format($import->total_products - $import->processed_products); ?></span>
                <span class="stat-label"><?php _e('Remaining', 'bootflow-woocommerce-xml-csv-importer'); ?></span>
            </div>
        </div>

        <!-- Import Details -->
        <div class="import-details">
            <table class="wp-list-table widefat">
                <tr>
                    <td><strong><?php _e('Import Name:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></td>
                    <td><?php echo esc_html($import->name); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('File Type:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></td>
                    <td><?php echo strtoupper($import->file_type); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Started:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></td>
                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import->created_at)); ?></td>
                </tr>
                <?php if ($import->last_run): ?>
                <tr>
                    <td><strong><?php _e('Last Activity:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></td>
                    <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($import->last_run)); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?php _e('Schedule:', 'bootflow-woocommerce-xml-csv-importer'); ?></strong></td>
                    <td><?php echo ucfirst($import->schedule_type); ?></td>
                </tr>
            </table>
        </div>

        <!-- Import Controls -->
        <div class="import-controls" style="margin: 20px 0;">
            <?php if ($import->status === 'processing'): ?>
                <button type="button" id="pause-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php _e('Pause Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
                <button type="button" id="stop-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-stop"></span>
                    <?php _e('Stop Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
            <?php elseif ($import->status === 'paused'): ?>
                <button type="button" id="resume-import" class="button button-primary">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Resume Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
                <button type="button" id="stop-import" class="button button-secondary">
                    <span class="dashicons dashicons-controls-stop"></span>
                    <?php _e('Stop Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
            <?php elseif ($import->status === 'failed'): ?>
                <button type="button" id="retry-import" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Retry Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
            <?php endif; ?>

            <button type="button" id="view-logs" class="button button-secondary">
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('View Detailed Logs', 'bootflow-woocommerce-xml-csv-importer'); ?>
            </button>
        </div>

        <!-- Live Progress Stats (Fixed at top) -->
        <div class="live-progress-section" id="live-progress-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; position: sticky; top: 32px; z-index: 100;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <!-- Progress Bar -->
                <div style="flex: 2; min-width: 250px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-weight: 600; color: #495057;"><?php _e('Progress', 'bootflow-woocommerce-xml-csv-importer'); ?></span>
                        <span id="live-progress-text" style="font-weight: 700; color: #0073aa;"><?php echo esc_html($percentage); ?>%</span>
                    </div>
                    <div style="background: #e9ecef; border-radius: 10px; height: 20px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">
                        <div id="live-progress-bar" style="background: linear-gradient(90deg, #0073aa, #00a0d2); height: 100%; width: <?php echo esc_attr($percentage); ?>%; transition: width 0.5s ease; border-radius: 10px;"></div>
                    </div>
                </div>
                
                <!-- Stats -->
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-processed" style="font-size: 24px; font-weight: 700; color: #28a745;"><?php echo esc_html($import->processed_products); ?></div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php _e('Processed', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-total" style="font-size: 24px; font-weight: 700; color: #495057;"><?php echo esc_html($import->total_products); ?></div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php _e('Total', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-speed" style="font-size: 24px; font-weight: 700; color: #17a2b8;">-</div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php _e('prod/min', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
                    </div>
                    <div style="text-align: center; min-width: 80px;">
                        <div id="live-eta" style="font-size: 24px; font-weight: 700; color: #6f42c1;">-</div>
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase;"><?php _e('ETA', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Current chunk info -->
            <div id="live-chunk-info" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #dee2e6; font-size: 13px; color: #6c757d;">
                <span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
                <span id="live-current-action"><?php _e('Waiting for progress...', 'bootflow-woocommerce-xml-csv-importer'); ?></span>
            </div>
        </div>

        <!-- Product Activity Log (Scrollable) - PRO only -->
        <?php if (WC_XML_CSV_AI_Import_Features::is_available('detailed_logs')): ?>
        <div class="import-logs-section">
            <h3 style="display: flex; align-items: center; justify-content: space-between;">
                <span>
                    <span class="dashicons dashicons-admin-page"></span>
                    <?php _e('Product Activity Log', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </span>
                <span style="font-size: 12px; font-weight: normal; color: #6c757d;">
                    <label style="cursor: pointer;">
                        <input type="checkbox" id="auto-scroll-logs" checked style="margin-right: 5px;">
                        <?php _e('Auto-scroll', 'bootflow-woocommerce-xml-csv-importer'); ?>
                    </label>
                </span>
            </h3>
            <div class="import-logs" id="import-logs" style="max-height: 400px; overflow-y: auto;">
                <?php
                // Get recent product logs only (exclude progress logs)
                $logs = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wc_itp_import_logs 
                     WHERE import_id = %d 
                     AND message NOT LIKE 'Chunk %' 
                     AND message NOT LIKE 'Processing chunk %'
                     AND message NOT LIKE 'Processed %/%'
                     ORDER BY id DESC LIMIT 50",
                    $import_id
                ));

                if (!empty($logs)) {
                    foreach ($logs as $log) {
                        $log_class = esc_attr($log->level);
                        $timestamp = date_i18n('H:i:s', strtotime($log->created_at));
                        
                        // Determine icon based on log content
                        $icon = '📝';
                        if (strpos($log->message, 'Created') !== false || strpos($log->message, 'created') !== false) {
                            $icon = '✅';
                        } elseif (strpos($log->message, 'Updated') !== false || strpos($log->message, 'updated') !== false) {
                            $icon = '🔄';
                        } elseif (strpos($log->message, 'Skipped') !== false || strpos($log->message, 'skipped') !== false) {
                            $icon = '⏭️';
                        } elseif (strpos($log->message, 'Error') !== false || $log->level === 'error') {
                            $icon = '❌';
                        } elseif (strpos($log->message, 'Warning') !== false || $log->level === 'warning') {
                            $icon = '⚠️';
                        }
                        
                        echo '<div class="log-entry ' . $log_class . '" data-log-id="' . esc_attr($log->id) . '">';
                        echo '<span class="log-icon">' . $icon . '</span> ';
                        echo '<span class="log-time">[' . $timestamp . ']</span> ';
                        echo '<span class="log-message">' . esc_html($log->message) . '</span>';
                        if (!empty($log->product_sku)) {
                            echo ' <span class="log-sku">(SKU: ' . esc_html($log->product_sku) . ')</span>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<div class="log-entry info">' . __('Waiting for product processing...', 'bootflow-woocommerce-xml-csv-importer') . '</div>';
                }
                ?>
            </div>
        </div>
        <?php endif; // End PRO-only Product Activity Log ?>

        <!-- Performance Metrics -->
        <?php if ($import->status === 'processing' || $import->status === 'completed'): ?>
        <div class="performance-metrics" style="margin-top: 20px;">
            <h3>
                <span class="dashicons dashicons-chart-line"></span>
                <?php _e('Performance Metrics', 'bootflow-woocommerce-xml-csv-importer'); ?>
            </h3>
            <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="metric-item">
                    <div class="metric-label"><?php _e('Processing Speed', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
                    <div class="metric-value" id="processing-speed">
                        <?php
                        if ($import->last_run && $import->created_at) {
                            $elapsed = strtotime($import->last_run) - strtotime($import->created_at);
                            $rate = $elapsed > 0 ? round($import->processed_products / ($elapsed / 60), 2) : 0;
                            echo $rate . ' ' . __('products/min', 'bootflow-woocommerce-xml-csv-importer');
                        } else {
                            echo __('Calculating...', 'bootflow-woocommerce-xml-csv-importer');
                        }
                        ?>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><?php _e('Estimated Time Remaining', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
                    <div class="metric-value" id="time-remaining">
                        <?php
                        if ($import->processed_products > 0 && $import->total_products > $import->processed_products) {
                            $remaining = $import->total_products - $import->processed_products;
                            if (isset($rate) && $rate > 0) {
                                $eta_minutes = round($remaining / $rate);
                                if ($eta_minutes < 60) {
                                    echo $eta_minutes . ' ' . __('minutes', 'bootflow-woocommerce-xml-csv-importer');
                                } else {
                                    $eta_hours = floor($eta_minutes / 60);
                                    $eta_mins = $eta_minutes % 60;
                                    echo $eta_hours . 'h ' . $eta_mins . 'm';
                                }
                            } else {
                                echo __('Calculating...', 'bootflow-woocommerce-xml-csv-importer');
                            }
                        } else {
                            echo __('N/A', 'bootflow-woocommerce-xml-csv-importer');
                        }
                        ?>
                    </div>
                </div>
                <div class="metric-item">
                    <div class="metric-label"><?php _e('Success Rate', 'bootflow-woocommerce-xml-csv-importer'); ?></div>
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
            <h3><?php _e('Import Completed Successfully!', 'bootflow-woocommerce-xml-csv-importer'); ?></h3>
            <p><?php printf(__('Successfully imported %d out of %d products.', 'bootflow-woocommerce-xml-csv-importer'), $import->processed_products, $import->total_products); ?></p>
            
            <div class="action-buttons" style="margin-top: 15px;">
                <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-products"></span>
                    <?php _e('View Products', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Start New Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </a>
                
                <?php if (WC_XML_CSV_AI_Import_Features::is_available('detailed_logs')): ?>
                <button type="button" id="download-report" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Download Report', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
                <?php endif; ?>
                
                <?php if ($import->schedule_type !== 'disabled'): ?>
                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import-settings'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Manage Scheduled Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error information -->
        <?php if ($import->status === 'failed'): ?>
        <div class="error-information" style="margin-top: 20px; padding: 20px; background: #fee; border: 1px solid #dc3545; border-radius: 6px;">
            <h3><?php _e('Import Failed', 'bootflow-woocommerce-xml-csv-importer'); ?></h3>
            <p><?php _e('The import process encountered an error and could not be completed. Please check the logs below for more details.', 'bootflow-woocommerce-xml-csv-importer'); ?></p>
            
            <?php
            $error_logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wc_itp_import_logs WHERE import_id = %d AND level = 'error' ORDER BY created_at DESC LIMIT 5",
                $import_id
            ));
            
            if (!empty($error_logs)): ?>
            <div class="error-logs">
                <h4><?php _e('Recent Errors:', 'bootflow-woocommerce-xml-csv-importer'); ?></h4>
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
                    <?php _e('Retry Import', 'bootflow-woocommerce-xml-csv-importer'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=wc-xml-csv-import'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php _e('Start Over', 'bootflow-woocommerce-xml-csv-importer'); ?>
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
                statusText = '<?php _e('Preparing Import...', 'bootflow-woocommerce-xml-csv-importer'); ?>';
                break;
            case 'processing':
                statusText = '<?php _e('Processing Products...', 'bootflow-woocommerce-xml-csv-importer'); ?>';
                break;
            case 'completed':
                statusText = '<?php _e('Import Completed Successfully!', 'bootflow-woocommerce-xml-csv-importer'); ?>';
                break;
            case 'failed':
                statusText = '<?php _e('Import Failed', 'bootflow-woocommerce-xml-csv-importer'); ?>';
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