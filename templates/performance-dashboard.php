<?php
/**
 * Performance Dashboard Template
 * 
 * แสดง Performance Metrics และ Optimization Tools
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get performance data
$cache_manager = new TPAK_DQ_Cache_Manager();
$performance_monitor = new TPAK_DQ_Performance_Monitor();

$cache_stats = $cache_manager->get_cache_stats();
$performance_stats = $performance_monitor->get_overall_stats();
$recommendations = $performance_monitor->get_performance_recommendations();
?>

<div class="wrap tpak-performance-dashboard">
    <h1>Performance Dashboard</h1>
    
    <!-- Performance Overview -->
    <div class="tpak-performance-overview">
        <div class="tpak-performance-card">
            <h3>Cache Statistics</h3>
            <div class="tpak-performance-metrics">
                <div class="tpak-metric">
                    <span class="tpak-metric-label">Total Items:</span>
                    <span class="tpak-metric-value"><?php echo $cache_stats['total_items']; ?></span>
                </div>
                <div class="tpak-metric">
                    <span class="tpak-metric-label">Cache Size:</span>
                    <span class="tpak-metric-value"><?php echo $cache_stats['cache_size_mb']; ?> MB</span>
                </div>
                <div class="tpak-metric">
                    <span class="tpak-metric-label">Expired Items:</span>
                    <span class="tpak-metric-value"><?php echo $cache_stats['expired_items']; ?></span>
                </div>
            </div>
        </div>
        
        <div class="tpak-performance-card">
            <h3>Performance Metrics</h3>
            <div class="tpak-performance-metrics">
                <div class="tpak-metric">
                    <span class="tpak-metric-label">Execution Time:</span>
                    <span class="tpak-metric-value"><?php echo round($performance_stats['total_execution_time'], 3); ?>s</span>
                </div>
                <div class="tpak-metric">
                    <span class="tpak-metric-label">Memory Usage:</span>
                    <span class="tpak-metric-value"><?php echo round($performance_stats['total_memory_usage'] / 1024 / 1024, 2); ?> MB</span>
                </div>
                <div class="tpak-metric">
                    <span class="tpak-metric-label">Database Queries:</span>
                    <span class="tpak-metric-value"><?php echo $performance_stats['total_queries']; ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Recommendations -->
    <div class="tpak-performance-recommendations">
        <h3>Performance Recommendations</h3>
        <?php if (!empty($recommendations)): ?>
            <?php foreach ($recommendations as $recommendation): ?>
                <div class="tpak-recommendation tpak-recommendation-<?php echo $recommendation['type']; ?>">
                    <div class="tpak-recommendation-icon">
                        <?php if ($recommendation['type'] === 'warning'): ?>
                            <span class="dashicons dashicons-warning"></span>
                        <?php elseif ($recommendation['type'] === 'success'): ?>
                            <span class="dashicons dashicons-yes-alt"></span>
                        <?php endif; ?>
                    </div>
                    <div class="tpak-recommendation-content">
                        <div class="tpak-recommendation-message"><?php echo esc_html($recommendation['message']); ?></div>
                        <div class="tpak-recommendation-metric">
                            <?php echo esc_html($recommendation['metric']); ?>: <?php echo esc_html($recommendation['value']); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="tpak-recommendation tpak-recommendation-success">
                <div class="tpak-recommendation-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="tpak-recommendation-content">
                    <div class="tpak-recommendation-message">All performance metrics are within acceptable ranges.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Performance Actions -->
    <div class="tpak-performance-actions">
        <h3>Performance Actions</h3>
        <div class="tpak-action-buttons">
            <button type="button" class="button button-primary" id="tpak-clear-cache">
                <span class="dashicons dashicons-trash"></span>
                Clear Cache
            </button>
            <button type="button" class="button button-secondary" id="tpak-optimize-database">
                <span class="dashicons dashicons-database"></span>
                Optimize Database
            </button>
            <button type="button" class="button button-secondary" id="tpak-clean-logs">
                <span class="dashicons dashicons-clean"></span>
                Clean Old Logs
            </button>
            <button type="button" class="button button-secondary" id="tpak-refresh-stats">
                <span class="dashicons dashicons-update"></span>
                Refresh Stats
            </button>
        </div>
    </div>
    
    <!-- Performance Charts -->
    <div class="tpak-performance-charts">
        <h3>Performance Trends</h3>
        <div class="tpak-chart-container">
            <canvas id="tpak-performance-chart"></canvas>
        </div>
    </div>
    
    <!-- Cache Details -->
    <div class="tpak-cache-details">
        <h3>Cache Details</h3>
        <div class="tpak-cache-groups">
            <div class="tpak-cache-group">
                <h4>Survey Cache</h4>
                <div class="tpak-cache-info">
                    <span>Survey Structures: <strong><?php echo $cache_stats['survey_cache_count'] ?? 0; ?></strong></span>
                    <span>Logic Rules: <strong><?php echo $cache_stats['logic_cache_count'] ?? 0; ?></strong></span>
                </div>
            </div>
            <div class="tpak-cache-group">
                <h4>System Cache</h4>
                <div class="tpak-cache-info">
                    <span>Question Types: <strong><?php echo $cache_stats['system_cache_count'] ?? 0; ?></strong></span>
                    <span>Rendered Questions: <strong><?php echo $cache_stats['rendered_cache_count'] ?? 0; ?></strong></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.tpak-performance-dashboard {
    max-width: 1200px;
    margin: 20px auto;
}

.tpak-performance-overview {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.tpak-performance-card {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-performance-card h3 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}

.tpak-performance-metrics {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tpak-metric {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.tpak-metric-label {
    font-weight: 500;
    color: #666;
}

.tpak-metric-value {
    font-weight: 600;
    color: #007cba;
}

.tpak-performance-recommendations {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-recommendation {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 15px;
    margin-bottom: 10px;
    border-radius: 6px;
    border-left: 4px solid;
}

.tpak-recommendation-warning {
    background: #fff3cd;
    border-left-color: #ffc107;
}

.tpak-recommendation-success {
    background: #d4edda;
    border-left-color: #28a745;
}

.tpak-recommendation-icon {
    flex-shrink: 0;
}

.tpak-recommendation-warning .tpak-recommendation-icon {
    color: #856404;
}

.tpak-recommendation-success .tpak-recommendation-icon {
    color: #155724;
}

.tpak-recommendation-content {
    flex: 1;
}

.tpak-recommendation-message {
    font-weight: 500;
    margin-bottom: 5px;
}

.tpak-recommendation-metric {
    font-size: 0.9rem;
    color: #666;
}

.tpak-performance-actions {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.tpak-action-buttons .button {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
}

.tpak-performance-charts {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-chart-container {
    height: 300px;
    margin-top: 20px;
}

.tpak-cache-details {
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.tpak-cache-groups {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.tpak-cache-group {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
}

.tpak-cache-group h4 {
    margin-top: 0;
    color: #495057;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 10px;
}

.tpak-cache-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.tpak-cache-info span {
    font-size: 0.9rem;
    color: #666;
}

@media (max-width: 768px) {
    .tpak-performance-overview {
        grid-template-columns: 1fr;
    }
    
    .tpak-cache-groups {
        grid-template-columns: 1fr;
    }
    
    .tpak-action-buttons {
        flex-direction: column;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Performance Dashboard functionality
    const dashboard = {
        init: function() {
            this.bindEvents();
            this.initCharts();
        },
        
        bindEvents: function() {
            $('#tpak-clear-cache').on('click', this.clearCache);
            $('#tpak-optimize-database').on('click', this.optimizeDatabase);
            $('#tpak-clean-logs').on('click', this.cleanLogs);
            $('#tpak-refresh-stats').on('click', this.refreshStats);
        },
        
        clearCache: function() {
            if (confirm('Are you sure you want to clear all cache?')) {
                $.ajax({
                    url: tpak_dq.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tpak_clear_cache',
                        nonce: tpak_dq.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Cache cleared successfully!');
                            location.reload();
                        } else {
                            alert('Failed to clear cache: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to clear cache');
                    }
                });
            }
        },
        
        optimizeDatabase: function() {
            $.ajax({
                url: tpak_dq.ajax_url,
                type: 'POST',
                data: {
                    action: 'tpak_optimize_database',
                    nonce: tpak_dq.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Database optimized successfully!');
                    } else {
                        alert('Failed to optimize database: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to optimize database');
                }
            });
        },
        
        cleanLogs: function() {
            if (confirm('Are you sure you want to clean old logs?')) {
                $.ajax({
                    url: tpak_dq.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'tpak_clean_logs',
                        nonce: tpak_dq.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Logs cleaned successfully!');
                        } else {
                            alert('Failed to clean logs: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('Failed to clean logs');
                    }
                });
            }
        },
        
        refreshStats: function() {
            location.reload();
        },
        
        initCharts: function() {
            // Initialize performance charts if Chart.js is available
            if (typeof Chart !== 'undefined') {
                const ctx = document.getElementById('tpak-performance-chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        datasets: [{
                            label: 'Execution Time (ms)',
                            data: [120, 190, 300, 500, 200, 300],
                            borderColor: '#007cba',
                            backgroundColor: 'rgba(0, 124, 186, 0.1)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        }
    };
    
    dashboard.init();
});
</script> 