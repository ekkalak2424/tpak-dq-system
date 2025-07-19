<?php
/**
 * ไฟล์: includes/class-tpak-dq-post-types.php
 * จัดการ Custom Post Types และ Taxonomies
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Post_Types {
    
    public function __construct() {
        // ใช้ priority ต่ำเพื่อให้ทำงานก่อน
        add_action('init', array($this, 'register_post_type'), 0);
        add_action('init', array($this, 'register_taxonomies'), 0);
        
        // Flush rewrite rules on activation
        register_activation_hook(dirname(dirname(__FILE__)) . '/tpak-dq-system.php', array($this, 'flush_rewrite_rules'));
    }
    
    public function register_post_type() {
        $labels = array(
            'name' => 'ชุดข้อมูลตรวจสอบ',
            'singular_name' => 'ชุดข้อมูล',
            'add_new' => 'เพิ่มชุดข้อมูลใหม่',
            'add_new_item' => 'เพิ่มชุดข้อมูลตรวจสอบใหม่',
            'edit_item' => 'แก้ไขชุดข้อมูล',
            'new_item' => 'ชุดข้อมูลใหม่',
            'view_item' => 'ดูชุดข้อมูล',
            'view_items' => 'ดูชุดข้อมูลทั้งหมด',
            'search_items' => 'ค้นหาชุดข้อมูล',
            'not_found' => 'ไม่พบชุดข้อมูล',
            'not_found_in_trash' => 'ไม่พบชุดข้อมูลในถังขยะ',
            'all_items' => 'ข้อมูลทั้งหมด',
            'archives' => 'คลังข้อมูล',
            'attributes' => 'คุณสมบัติ',
            'menu_name' => 'TPAK DQ System',
            'name_admin_bar' => 'ชุดข้อมูล TPAK'
        );
        
        $args = array(
            'labels' => $labels,
            'description' => 'ชุดข้อมูลสำหรับระบบตรวจสอบคุณภาพ TPAK',
            'public' => false,
            'publicly_queryable' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 25,
            'menu_icon' => 'dashicons-chart-bar',
            'supports' => array('title', 'author', 'comments'),
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'capabilities' => array(
                'edit_post' => 'edit_posts',
                'read_post' => 'read',
                'delete_post' => 'delete_posts',
                'edit_posts' => 'edit_posts',
                'edit_others_posts' => 'edit_others_posts',
                'publish_posts' => 'publish_posts',
                'read_private_posts' => 'read_private_posts',
            ),
        );
        
        register_post_type('tpak_verification', $args);
    }
    
    public function register_taxonomies() {
        // สถานะการตรวจสอบ
        $labels = array(
            'name' => 'สถานะการตรวจสอบ',
            'singular_name' => 'สถานะ',
            'search_items' => 'ค้นหาสถานะ',
            'all_items' => 'สถานะทั้งหมด',
            'edit_item' => 'แก้ไขสถานะ',
            'update_item' => 'อัพเดทสถานะ',
            'add_new_item' => 'เพิ่มสถานะใหม่',
            'new_item_name' => 'ชื่อสถานะใหม่',
            'menu_name' => 'สถานะ',
        );
        
        $args = array(
            'labels' => $labels,
            'hierarchical' => true,
            'public' => false,
            'show_ui' => false,
            'show_admin_column' => false,
            'query_var' => false,
            'rewrite' => false,
        );
        
        register_taxonomy('verification_status', array('tpak_verification'), $args);
        
        // เพิ่ม Default Terms
        $this->create_default_terms();
    }
    
    private function create_default_terms() {
        $terms = array(
            'pending_a' => 'รอ Supervisor ตรวจสอบ',
            'pending_b' => 'รอ Examiner ตรวจสอบ',
            'pending_c' => 'รอการอนุมัติ',
            'rejected_by_b' => 'ถูกส่งกลับโดย Supervisor',
            'rejected_by_c' => 'ถูกส่งกลับโดย Examiner',
            'finalized' => 'อนุมัติแล้ว'
        );
        
        foreach ($terms as $slug => $name) {
            if (!term_exists($slug, 'verification_status')) {
                wp_insert_term($name, 'verification_status', array('slug' => $slug));
            }
        }
    }
    
    public function flush_rewrite_rules() {
        $this->register_post_type();
        $this->register_taxonomies();
        flush_rewrite_rules();
    }
}