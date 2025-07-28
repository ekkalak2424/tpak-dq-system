<?php
/**
 * TPAK DQ Lazy Loader
 * 
 * จัดการ Lazy Loading สำหรับ Performance Optimization
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lazy Loader Class
 */
class TPAK_DQ_Lazy_Loader {
    
    private $loaded_components = array();
    private $loading_queue = array();
    private $batch_size = 5;
    
    public function __construct() {
        add_action('wp_ajax_tpak_load_component', array($this, 'ajax_load_component'));
        add_action('wp_ajax_nopriv_tpak_load_component', array($this, 'ajax_load_component'));
        add_action('wp_ajax_tpak_load_batch', array($this, 'ajax_load_batch'));
        add_action('wp_ajax_nopriv_tpak_load_batch', array($this, 'ajax_load_batch'));
    }
    
    /**
     * Register component for lazy loading
     */
    public function register_component($component_id, $callback, $dependencies = array()) {
        $this->loading_queue[$component_id] = array(
            'callback' => $callback,
            'dependencies' => $dependencies,
            'loaded' => false,
            'data' => null
        );
    }
    
    /**
     * Load component
     */
    public function load_component($component_id) {
        if (!isset($this->loading_queue[$component_id])) {
            return false;
        }
        
        $component = $this->loading_queue[$component_id];
        
        // Check dependencies
        if (!empty($component['dependencies'])) {
            foreach ($component['dependencies'] as $dependency) {
                if (!$this->is_component_loaded($dependency)) {
                    $this->load_component($dependency);
                }
            }
        }
        
        // Load component
        if (!$component['loaded']) {
            $data = call_user_func($component['callback']);
            $this->loading_queue[$component_id]['data'] = $data;
            $this->loading_queue[$component_id]['loaded'] = true;
            $this->loaded_components[$component_id] = $data;
        }
        
        return $this->loading_queue[$component_id]['data'];
    }
    
    /**
     * Load batch of components
     */
    public function load_batch($component_ids) {
        $results = array();
        
        foreach ($component_ids as $component_id) {
            $results[$component_id] = $this->load_component($component_id);
        }
        
        return $results;
    }
    
    /**
     * Check if component is loaded
     */
    public function is_component_loaded($component_id) {
        return isset($this->loaded_components[$component_id]) || 
               (isset($this->loading_queue[$component_id]) && $this->loading_queue[$component_id]['loaded']);
    }
    
    /**
     * Get loaded components
     */
    public function get_loaded_components() {
        return $this->loaded_components;
    }
    
    /**
     * Get loading queue
     */
    public function get_loading_queue() {
        return $this->loading_queue;
    }
    
    /**
     * Load survey questions lazily
     */
    public function load_survey_questions($survey_id, $page = 1, $per_page = 10) {
        $cache_key = "survey_questions_{$survey_id}_page_{$page}";
        
        // Try to get from cache first
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            $cached_data = $cache_manager->get_cache($cache_key, 'lazy_loading');
            
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Load from database
        $questions = $this->load_questions_from_database($survey_id, $page, $per_page);
        
        // Cache the result
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            $cache_manager->set_cache($cache_key, $questions, 'lazy_loading', 1800); // 30 minutes
        }
        
        return $questions;
    }
    
    /**
     * Load questions from database
     */
    private function load_questions_from_database($survey_id, $page, $per_page) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpak_dq_questions 
             WHERE survey_id = %s 
             ORDER BY question_order 
             LIMIT %d OFFSET %d",
            $survey_id,
            $per_page,
            $offset
        ));
        
        return $questions;
    }
    
    /**
     * Load question options lazily
     */
    public function load_question_options($question_id) {
        $cache_key = "question_options_{$question_id}";
        
        // Try to get from cache first
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            $cached_data = $cache_manager->get_cache($cache_key, 'lazy_loading');
            
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Load from database
        $options = $this->load_options_from_database($question_id);
        
        // Cache the result
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            $cache_manager->set_cache($cache_key, $options, 'lazy_loading', 3600); // 1 hour
        }
        
        return $options;
    }
    
    /**
     * Load options from database
     */
    private function load_options_from_database($question_id) {
        global $wpdb;
        
        $options = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpak_dq_question_options 
             WHERE question_id = %d 
             ORDER BY option_order",
            $question_id
        ));
        
        return $options;
    }
    
    /**
     * Load logic rules lazily
     */
    public function load_logic_rules($survey_id) {
        $cache_key = "logic_rules_{$survey_id}";
        
        // Try to get from cache first
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            $cached_data = $cache_manager->get_cache($cache_key, 'lazy_loading');
            
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Load from database
        $rules = $this->load_logic_rules_from_database($survey_id);
        
        // Cache the result
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            $cache_manager->set_cache($cache_key, $rules, 'lazy_loading', 1800); // 30 minutes
        }
        
        return $rules;
    }
    
    /**
     * Load logic rules from database
     */
    private function load_logic_rules_from_database($survey_id) {
        global $wpdb;
        
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tpak_dq_logic_rules 
             WHERE survey_id = %s",
            $survey_id
        ));
        
        return $rules;
    }
    
    /**
     * AJAX handler for loading component
     */
    public function ajax_load_component() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        $component_id = sanitize_text_field($_POST['component_id'] ?? '');
        
        if (empty($component_id)) {
            wp_send_json_error('Component ID is required');
        }
        
        $data = $this->load_component($component_id);
        
        if ($data !== false) {
            wp_send_json_success(array(
                'component_id' => $component_id,
                'data' => $data
            ));
        } else {
            wp_send_json_error('Failed to load component');
        }
    }
    
    /**
     * AJAX handler for loading batch
     */
    public function ajax_load_batch() {
        check_ajax_referer('tpak_dq_nonce', 'nonce');
        
        $component_ids = $_POST['component_ids'] ?? array();
        
        if (empty($component_ids)) {
            wp_send_json_error('Component IDs are required');
        }
        
        $results = $this->load_batch($component_ids);
        
        wp_send_json_success(array(
            'results' => $results
        ));
    }
    
    /**
     * Preload critical components
     */
    public function preload_critical_components($survey_id) {
        $critical_components = array(
            'survey_structure',
            'question_types',
            'logic_rules'
        );
        
        foreach ($critical_components as $component) {
            $this->register_component($component, function() use ($survey_id, $component) {
                switch ($component) {
                    case 'survey_structure':
                        return TPAK_DQ_Survey_Structure_Manager::get_survey_structure($survey_id);
                    case 'question_types':
                        return TPAK_DQ_Question_Types::get_instance()->get_supported_types();
                    case 'logic_rules':
                        return $this->load_logic_rules($survey_id);
                }
            });
        }
    }
    
    /**
     * Get loading progress
     */
    public function get_loading_progress() {
        $total = count($this->loading_queue);
        $loaded = count($this->loaded_components);
        
        return array(
            'total' => $total,
            'loaded' => $loaded,
            'progress' => $total > 0 ? ($loaded / $total) * 100 : 0
        );
    }
    
    /**
     * Optimize loading queue
     */
    public function optimize_loading_queue() {
        // Sort by dependencies
        $sorted_queue = array();
        $processed = array();
        
        foreach ($this->loading_queue as $component_id => $component) {
            $this->sort_component_dependencies($component_id, $component, $sorted_queue, $processed);
        }
        
        $this->loading_queue = $sorted_queue;
    }
    
    /**
     * Sort component dependencies
     */
    private function sort_component_dependencies($component_id, $component, &$sorted_queue, &$processed) {
        if (isset($processed[$component_id])) {
            return;
        }
        
        // Process dependencies first
        if (!empty($component['dependencies'])) {
            foreach ($component['dependencies'] as $dependency) {
                if (isset($this->loading_queue[$dependency])) {
                    $this->sort_component_dependencies(
                        $dependency, 
                        $this->loading_queue[$dependency], 
                        $sorted_queue, 
                        $processed
                    );
                }
            }
        }
        
        $sorted_queue[$component_id] = $component;
        $processed[$component_id] = true;
    }
    
    /**
     * Clear lazy loading cache
     */
    public function clear_lazy_cache() {
        if (class_exists('TPAK_DQ_Cache_Manager')) {
            $cache_manager = new TPAK_DQ_Cache_Manager();
            
            // Clear all lazy loading cache
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE %s",
                '_transient_tpak_dq_lazy_loading_%'
            ));
        }
    }
} 