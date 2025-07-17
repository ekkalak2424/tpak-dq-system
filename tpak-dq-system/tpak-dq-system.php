<?php
/**
 * Plugin Name: TPAK DQ System
 * Plugin URI: https://example.com/
 * Description: ระบบการตรวจสอบคุณภาพข้อมูล TPAK Survey System แบบไม่ใช้ ACF
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('TPAK_DQ_VERSION', '2.0.0');
define('TPAK_DQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPAK_DQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPAK_DQ_PLUGIN_FILE', __FILE__);

/**
 * Class หลักของ Plugin
 */
class TPAK_DQ_System {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init();
    }
    
    private function load_dependencies() {
        // Load required files
        $files = array(
            'includes/class-tpak-dq-roles.php',
            'includes/class-tpak-dq-post-types.php',
            'includes/class-tpak-dq-meta-boxes.php',
            'includes/class-tpak-dq-import.php',
            'includes/class-tpak-dq-workflow.php',
            'includes/class-tpak-dq-dashboard.php',
            'includes/class-tpak-dq-notifications.php'
        );
        
        foreach ($files as $file) {
            $file_path = TPAK_DQ_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    private function init() {
        // Hook การ Activation
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Hook การ Deactivation
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));
        
        // Admin scripts และ styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public function activate() {
        // สร้าง Roles
        if (class_exists('TPAK_DQ_Roles')) {
            TPAK_DQ_Roles::create_roles();
        }
        
        // สร้างตารางฐานข้อมูล
        $this->create_tables();
        
        // Register post types and flush rules
        if (class_exists('TPAK_DQ_Post_Types')) {
            $post_types = new TPAK_DQ_Post_Types();
            $post_types->register_post_type();
            $post_types->register_taxonomies();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up
        flush_rewrite_rules();
    }
    
    public function init_components() {
        // Initialize Post Types - ทำก่อนอื่น
        if (class_exists('TPAK_DQ_Post_Types')) {
            new TPAK_DQ_Post_Types();
        }
        
        // Initialize other components
        if (class_exists('TPAK_DQ_Meta_Boxes')) {
            new TPAK_DQ_Meta_Boxes();
        }
        
        if (class_exists('TPAK_DQ_Import')) {
            new TPAK_DQ_Import();
        }
        
        if (class_exists('TPAK_DQ_Workflow')) {
            new TPAK_DQ_Workflow();
        }
        
        if (class_exists('TPAK_DQ_Dashboard')) {
            new TPAK_DQ_Dashboard();
        }
        
        if (class_exists('TPAK_DQ_Notifications')) {
            new TPAK_DQ_Notifications();
        }
    }
    
    public function enqueue_admin_assets($hook) {
        // Get current screen
        $screen = get_current_screen();
        
        // Check if we're on our pages
        if (!$screen || (strpos($screen->id, 'tpak') === false && $screen->post_type !== 'tpak_verification')) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'tpak-dq-admin',
            TPAK_DQ_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TPAK_DQ_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'tpak-dq-admin',
            TPAK_DQ_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            TPAK_DQ_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('tpak-dq-admin', 'tpak_dq', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpak_dq_nonce')
        ));
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตารางสำหรับ Audit Trail
        $audit_table = $wpdb->prefix . 'tpak_dq_audit';
        
        $sql = "CREATE TABLE IF NOT EXISTS $audit_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            old_status varchar(50),
            new_status varchar(50),
            comment text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize plugin
function tpak_dq_init() {
    TPAK_DQ_System::get_instance();
}
add_action('plugins_loaded', 'tpak_dq_init', 0);