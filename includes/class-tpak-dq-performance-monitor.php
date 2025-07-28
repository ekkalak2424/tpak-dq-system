<?php
/**
 * TPAK DQ Performance Monitor
 * 
 * จัดการ Performance Monitoring และ Optimization
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Monitor Class
 */
class TPAK_DQ_Performance_Monitor {
    
    private $start_time;
    private $memory_start;
    private $queries_start;
    private $performance_data = array();
    
    public function __construct() {
        $this->start_time = microtime(true);
        $this->memory_start = memory_get_usage();
        $this->queries_start = $this->get_query_count();
        
        add_action('wp_ajax_tpak_get_performance_stats', array($this, 'ajax_get_performance_stats'));
        add_action('wp_ajax_nopriv_tpak_get_performance_stats', array($this, 'ajax_get_performance_stats'));
    }
    
    /**
     * Start performance monitoring
     */
    public function start_monitoring($operation = 'default') {
        $this->performance_data[$operation] = array(
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'start_queries' => $this->get_query_count(),
            'start_peak_memory' => memory_get_peak_usage()
        );
    }
    
    /**
     * End performance monitoring
     */
    public function end_monitoring($operation = 'default') {
        if (!isset($this->performance_data[$operation])) {
            return false;
        }
        
        $start_data = $this->performance_data[$operation];
        
        $this->performance_data[$operation]['end_time'] = microtime(true);
        $this->performance_data[$operation]['end_memory'] = memory_get_usage();
        $this->performance_data[$operation]['end_queries'] = $this->get_query_count();
        $this->performance_data[$operation]['end_peak_memory'] = memory_get_peak_usage();
        
        // Calculate metrics
        $this->performance_data[$operation]['execution_time'] = 
            $this->performance_data[$operation]['end_time'] - $start_data['start_time'];
        
        $this->performance_data[$operation]['memory_usage'] = 
            $this->performance_data[$operation]['end_memory'] - $start_data['start_memory'];
        
        $this->performance_data[$operation]['peak_memory'] = 
            $this->performance_data[$operation]['end_peak_memory'] - $start_data['start_peak_memory'];
        
        $this->performance_data[$operation]['queries_count'] = 
            $this->performance_data[$operation]['end_queries'] - $start_data['start_queries'];
        
        return $this->performance_data[$operation];
    }
    
    /**
     * Get query count
     */
    private function get_query_count() {
        global $wpdb;
        return $wpdb->num_queries;
    }
    
    /**
     * Get overall performance stats
     */
    public function get_overall_stats() {
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        $end_queries = $this->get_query_count();
        $end_peak_memory = memory_get_peak_usage();
        
        return array(
            'total_execution_time' => $end_time - $this->start_time,
            'total_memory_usage' => $end_memory - $this->memory_start,
            'total_peak_memory' => $end_peak_memory - $this->memory_start,
            'total_queries' => $end_queries - $this->queries_start,
            'operations' => $this->performance_data
        );
    }
    
    /**
     * Get performance data for specific operation
     */
    public function get_operation_stats($operation) {
        if (!isset($this->performance_data[$operation])) {
            return false;
        }
        
        return $this->performance_data[$operation];
    }
    
    /**
     * Check if performance is acceptable
     */
    public function is_performance_acceptable($operation = 'default') {
        $stats = $this->get_operation_stats($operation);
        
        if (!$stats) {
            return true;
        }
        
        // Define thresholds
        $thresholds = array(
            'execution_time' => 2.0, // 2 seconds
            'memory_usage' => 50 * 1024 * 1024, // 50MB
            'queries_count' => 100
        );
        
        $issues = array();
        
        if ($stats['execution_time'] > $thresholds['execution_time']) {
            $issues[] = 'Slow execution time: ' . round($stats['execution_time'], 3) . 's';
        }
        
        if ($stats['memory_usage'] > $thresholds['memory_usage']) {
            $issues[] = 'High memory usage: ' . $this->format_bytes($stats['memory_usage']);
        }
        
        if ($stats['queries_count'] > $thresholds['queries_count']) {
            $issues[] = 'Too many queries: ' . $stats['queries_count'];
        }
        
        return array(
            'acceptable' => empty($issues),
            'issues' => $issues
        );
    }
    
    /**
     * Format bytes to human readable
     */
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get database performance stats
     */
    public function get_database_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Get slow queries
        $slow_queries = $wpdb->get_results("
            SELECT 
                query,
                COUNT(*) as count,
                AVG(duration) as avg_duration,
                MAX(duration) as max_duration
            FROM {$wpdb->prefix}tpak_dq_performance_log
            WHERE duration > 1.0
            GROUP BY query
            ORDER BY avg_duration DESC
            LIMIT 10
        ");
        
        $stats['slow_queries'] = $slow_queries;
        
        // Get query count by type
        $query_types = $wpdb->get_results("
            SELECT 
                SUBSTRING(query, 1, 20) as query_type,
                COUNT(*) as count,
                AVG(duration) as avg_duration
            FROM {$wpdb->prefix}tpak_dq_performance_log
            GROUP BY query_type
            ORDER BY count DESC
        ");
        
        $stats['query_types'] = $query_types;
        
        return $stats;
    }
    
    /**
     * Log database query performance
     */
    public function log_query_performance($query, $duration) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_performance_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'query' => $query,
                'duration' => $duration,
                'timestamp' => current_time('mysql', 1)
            ),
            array('%s', '%f', '%s')
        );
    }
    
    /**
     * Create performance log table
     */
    public function create_performance_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_performance_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            query text NOT NULL,
            duration float NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY duration (duration)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean old performance logs
     */
    public function clean_old_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_performance_log';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Get performance recommendations
     */
    public function get_performance_recommendations() {
        $recommendations = array();
        $stats = $this->get_overall_stats();
        
        // Check execution time
        if ($stats['total_execution_time'] > 1.0) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => 'Slow execution time detected. Consider implementing caching.',
                'metric' => 'execution_time',
                'value' => round($stats['total_execution_time'], 3) . 's'
            );
        }
        
        // Check memory usage
        if ($stats['total_memory_usage'] > 50 * 1024 * 1024) { // 50MB
            $recommendations[] = array(
                'type' => 'warning',
                'message' => 'High memory usage detected. Consider optimizing data structures.',
                'metric' => 'memory_usage',
                'value' => $this->format_bytes($stats['total_memory_usage'])
            );
        }
        
        // Check query count
        if ($stats['total_queries'] > 50) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => 'High query count detected. Consider implementing query optimization.',
                'metric' => 'queries_count',
                'value' => $stats['total_queries']
            );
        }
        
        // Add positive recommendations
        if ($stats['total_execution_time'] < 0.5) {
            $recommendations[] = array(
                'type' => 'success',
                'message' => 'Excellent performance! Execution time is optimal.',
                'metric' => 'execution_time',
                'value' => round($stats['total_execution_time'], 3) . 's'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * AJAX handler for getting performance stats
     */
    public function ajax_get_performance_stats() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $stats = $this->get_overall_stats();
        $recommendations = $this->get_performance_recommendations();
        $db_stats = $this->get_database_stats();
        
        wp_send_json_success(array(
            'stats' => $stats,
            'recommendations' => $recommendations,
            'database_stats' => $db_stats
        ));
    }
    
    /**
     * Monitor survey loading performance
     */
    public function monitor_survey_loading($survey_id) {
        $this->start_monitoring('survey_loading');
        
        // This will be called after survey loading is complete
        return function() use ($survey_id) {
            $stats = $this->end_monitoring('survey_loading');
            
            // Log performance data
            if ($stats) {
                $this->log_survey_performance($survey_id, $stats);
            }
        };
    }
    
    /**
     * Log survey performance
     */
    private function log_survey_performance($survey_id, $stats) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_survey_performance';
        
        $wpdb->insert(
            $table_name,
            array(
                'survey_id' => $survey_id,
                'execution_time' => $stats['execution_time'],
                'memory_usage' => $stats['memory_usage'],
                'queries_count' => $stats['queries_count'],
                'timestamp' => current_time('mysql', 1)
            ),
            array('%s', '%f', '%d', '%d', '%s')
        );
    }
    
    /**
     * Create survey performance table
     */
    public function create_survey_performance_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_survey_performance';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            survey_id varchar(255) NOT NULL,
            execution_time float NOT NULL,
            memory_usage bigint(20) NOT NULL,
            queries_count int(11) NOT NULL,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY timestamp (timestamp)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
} 