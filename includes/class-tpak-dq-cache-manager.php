<?php
/**
 * TPAK DQ Cache Manager
 * 
 * จัดการ Caching System สำหรับ Performance Optimization
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Manager Class
 */
class TPAK_DQ_Cache_Manager {
    
    private $cache_prefix = 'tpak_dq_';
    private $cache_timeout = 3600; // 1 hour default
    private $transient_timeout = 1800; // 30 minutes for transients
    
    public function __construct() {
        add_action('wp_ajax_tpak_clear_cache', array($this, 'clear_all_cache'));
        add_action('wp_ajax_nopriv_tpak_clear_cache', array($this, 'clear_all_cache'));
    }
    
    /**
     * Get cached data
     */
    public function get_cache($key, $group = 'default') {
        $cache_key = $this->get_cache_key($key, $group);
        
        // Try WordPress transients first
        $transient_data = get_transient($cache_key);
        if ($transient_data !== false) {
            return $transient_data;
        }
        
        // Try object cache
        $object_cache_data = wp_cache_get($cache_key, 'tpak_dq');
        if ($object_cache_data !== false) {
            return $object_cache_data;
        }
        
        // Try database cache
        $db_cache_data = $this->get_db_cache($cache_key);
        if ($db_cache_data !== false) {
            // Store in object cache for faster access
            wp_cache_set($cache_key, $db_cache_data, 'tpak_dq', $this->cache_timeout);
            return $db_cache_data;
        }
        
        return false;
    }
    
    /**
     * Set cached data
     */
    public function set_cache($key, $data, $group = 'default', $timeout = null) {
        $cache_key = $this->get_cache_key($key, $group);
        $timeout = $timeout ?: $this->cache_timeout;
        
        // Store in multiple cache layers
        $this->set_transient_cache($cache_key, $data, $timeout);
        $this->set_object_cache($cache_key, $data, $timeout);
        $this->set_db_cache($cache_key, $data, $timeout);
        
        return true;
    }
    
    /**
     * Delete cached data
     */
    public function delete_cache($key, $group = 'default') {
        $cache_key = $this->get_cache_key($key, $group);
        
        // Delete from all cache layers
        delete_transient($cache_key);
        wp_cache_delete($cache_key, 'tpak_dq');
        $this->delete_db_cache($cache_key);
        
        return true;
    }
    
    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        // Clear transients
        $this->clear_transient_cache();
        
        // Clear object cache
        wp_cache_flush_group('tpak_dq');
        
        // Clear database cache
        $this->clear_db_cache();
        
        return true;
    }
    
    /**
     * Get cache key
     */
    private function get_cache_key($key, $group) {
        return $this->cache_prefix . $group . '_' . md5($key);
    }
    
    /**
     * Set transient cache
     */
    private function set_transient_cache($key, $data, $timeout) {
        set_transient($key, $data, min($timeout, $this->transient_timeout));
    }
    
    /**
     * Set object cache
     */
    private function set_object_cache($key, $data, $timeout) {
        wp_cache_set($key, $data, 'tpak_dq', $timeout);
    }
    
    /**
     * Set database cache
     */
    private function set_db_cache($key, $data, $timeout) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        
        $wpdb->replace(
            $table_name,
            array(
                'cache_key' => $key,
                'cache_data' => maybe_serialize($data),
                'expires_at' => current_time('mysql', 1) + $timeout
            ),
            array('%s', '%s', '%s')
        );
    }
    
    /**
     * Get database cache
     */
    private function get_db_cache($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_data FROM {$table_name} 
             WHERE cache_key = %s AND expires_at > %s",
            $key,
            current_time('mysql', 1)
        ));
        
        if ($result) {
            return maybe_unserialize($result->cache_data);
        }
        
        return false;
    }
    
    /**
     * Delete database cache
     */
    private function delete_db_cache($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        
        $wpdb->delete(
            $table_name,
            array('cache_key' => $key),
            array('%s')
        );
    }
    
    /**
     * Clear transient cache
     */
    private function clear_transient_cache() {
        global $wpdb;
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_' . $this->cache_prefix . '%'
            )
        );
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_timeout_' . $this->cache_prefix . '%'
            )
        );
    }
    
    /**
     * Clear database cache
     */
    private function clear_db_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        
        $wpdb->query("DELETE FROM {$table_name}");
    }
    
    /**
     * Create cache table
     */
    public function create_cache_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_data longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expires_at (expires_at)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean expired cache
     */
    public function clean_expired_cache() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE expires_at < %s",
                current_time('mysql', 1)
            )
        );
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_dq_cache';
        
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $expired_items = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE expires_at < %s",
            current_time('mysql', 1)
        ));
        
        $cache_size = $wpdb->get_var("SELECT SUM(LENGTH(cache_data)) FROM {$table_name}");
        
        return array(
            'total_items' => (int) $total_items,
            'expired_items' => (int) $expired_items,
            'cache_size' => (int) $cache_size,
            'cache_size_mb' => round(($cache_size / 1024 / 1024), 2)
        );
    }
    
    /**
     * Cache survey structure
     */
    public function cache_survey_structure($survey_id, $structure) {
        return $this->set_cache(
            'survey_structure_' . $survey_id,
            $structure,
            'survey',
            7200 // 2 hours
        );
    }
    
    /**
     * Get cached survey structure
     */
    public function get_cached_survey_structure($survey_id) {
        return $this->get_cache('survey_structure_' . $survey_id, 'survey');
    }
    
    /**
     * Cache question types
     */
    public function cache_question_types($types) {
        return $this->set_cache(
            'question_types',
            $types,
            'system',
            86400 // 24 hours
        );
    }
    
    /**
     * Get cached question types
     */
    public function get_cached_question_types() {
        return $this->get_cache('question_types', 'system');
    }
    
    /**
     * Cache logic rules
     */
    public function cache_logic_rules($survey_id, $rules) {
        return $this->set_cache(
            'logic_rules_' . $survey_id,
            $rules,
            'logic',
            3600 // 1 hour
        );
    }
    
    /**
     * Get cached logic rules
     */
    public function get_cached_logic_rules($survey_id) {
        return $this->get_cache('logic_rules_' . $survey_id, 'logic');
    }
    
    /**
     * Cache rendered questions
     */
    public function cache_rendered_questions($survey_id, $response_data, $questions) {
        $cache_key = 'rendered_questions_' . $survey_id . '_' . md5(serialize($response_data));
        return $this->set_cache($cache_key, $questions, 'rendered', 1800); // 30 minutes
    }
    
    /**
     * Get cached rendered questions
     */
    public function get_cached_rendered_questions($survey_id, $response_data) {
        $cache_key = 'rendered_questions_' . $survey_id . '_' . md5(serialize($response_data));
        return $this->get_cache($cache_key, 'rendered');
    }
    
    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->clear_all_cache();
        
        if ($result) {
            wp_send_json_success('Cache cleared successfully');
        } else {
            wp_send_json_error('Failed to clear cache');
        }
    }
    
    /**
     * AJAX handler for getting cache stats
     */
    public function ajax_get_cache_stats() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $stats = $this->get_cache_stats();
        wp_send_json_success($stats);
    }
} 