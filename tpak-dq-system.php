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
            'includes/class-tpak-dq-notifications.php',
            'includes/class-tpak-dq-demo-users.php',
            'includes/class-tpak-dq-survey-renderer.php',
            'includes/class-tpak-dq-question-handlers.php',
            'includes/class-tpak-dq-answer-mapping.php',
            'includes/class-tpak-dq-complex-array-handler.php',
            'includes/class-tpak-dq-survey-structure-manager.php',
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
            TPAK_DQ_PLUGIN_URL . 'assets/admin.css',
            array(),
            TPAK_DQ_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'tpak-dq-admin',
            TPAK_DQ_PLUGIN_URL . 'assets/admin.js',
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

add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=tpak_verification',
        'Fix Workflow Status',
        'Fix Workflow Status',
        'manage_options',
        'tpak-fix-workflow',
        'tpak_render_fix_workflow_page'
    );
});

function tpak_render_fix_workflow_page() {
    $message = '';
    
    if (isset($_POST['fix_workflow']) && wp_verify_nonce($_POST['_wpnonce'], 'fix_workflow')) {
        global $wpdb;
        
        // หา posts ที่ไม่มี workflow status
        $posts_without_status = $wpdb->get_col("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tpak_workflow_status'
            WHERE p.post_type = 'tpak_verification' 
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value = '')
        ");
        
        $fixed = 0;
        foreach ($posts_without_status as $post_id) {
            update_post_meta($post_id, '_tpak_workflow_status', '');
            $fixed++;
        }
        
        $message = '<div class="notice notice-success"><p>แก้ไข ' . $fixed . ' posts เรียบร้อยแล้ว</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>Fix Workflow Status</h1>
        
        <?php echo $message; ?>
        
        <div class="card">
            <h2>ตรวจสอบและแก้ไข Workflow Status</h2>
            <p>Tool นี้จะตรวจสอบ posts ที่ไม่มี workflow status และตั้งค่าเป็น "รอดำเนินการ" (empty string)</p>
            
            <?php
            global $wpdb;
            $count = $wpdb->get_var("
                SELECT COUNT(p.ID) 
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tpak_workflow_status'
                WHERE p.post_type = 'tpak_verification' 
                AND p.post_status = 'publish'
                AND (pm.meta_value IS NULL OR pm.meta_value = '')
            ");
            ?>
            
            <p><strong>พบ <?php echo $count; ?> posts ที่ต้องแก้ไข</strong></p>
            
            <form method="post">
                <?php wp_nonce_field('fix_workflow'); ?>
                <input type="hidden" name="fix_workflow" value="1">
                <p class="submit">
                    <button type="submit" class="button button-primary" <?php echo $count == 0 ? 'disabled' : ''; ?>>
                        แก้ไข Workflow Status
                    </button>
                </p>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>สถานะปัจจุบัน</h2>
            <?php
            $statuses = $wpdb->get_results("
                SELECT 
                    CASE 
                        WHEN pm.meta_value = '' THEN 'รอดำเนินการ (empty)'
                        WHEN pm.meta_value IS NULL THEN 'ไม่มี status (NULL)'
                        ELSE pm.meta_value 
                    END as status,
                    COUNT(p.ID) as count
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tpak_workflow_status'
                WHERE p.post_type = 'tpak_verification' 
                AND p.post_status = 'publish'
                GROUP BY pm.meta_value
                ORDER BY count DESC
            ");
            ?>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>สถานะ</th>
                        <th>จำนวน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statuses as $status): ?>
                    <tr>
                        <td><?php echo esc_html($status->status); ?></td>
                        <td><?php echo esc_html($status->count); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

// Debug code removed for performance

// Debug code removed for performance

// Add menu for reassigning posts
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=tpak_verification',
        'จัดการการมอบหมาย',
        'จัดการการมอบหมาย',
        'manage_options',
        'tpak-reassign-posts',
        'tpak_render_reassign_page'
    );
});

function tpak_render_reassign_page() {
    $message = '';
    
    // Handle form submission
    if (isset($_POST['reassign_posts']) && wp_verify_nonce($_POST['_wpnonce'], 'reassign_posts')) {
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $new_author = isset($_POST['new_author']) ? intval($_POST['new_author']) : 0;
        
        if (!empty($post_ids) && $new_author) {
            $updated = 0;
            foreach ($post_ids as $post_id) {
                $result = wp_update_post(array(
                    'ID' => $post_id,
                    'post_author' => $new_author
                ));
                
                if (!is_wp_error($result)) {
                    $updated++;
                    
                    // Log the reassignment
                    global $wpdb;
                    $table = $wpdb->prefix . 'tpak_dq_audit';
                    $wpdb->insert($table, array(
                        'user_id' => get_current_user_id(),
                        'post_id' => $post_id,
                        'action' => 'reassigned',
                        'old_status' => '',
                        'new_status' => '',
                        'comment' => 'Reassigned to user ID: ' . $new_author,
                        'created_at' => current_time('mysql')
                    ));
                }
            }
            
            $message = '<div class="notice notice-success"><p>มอบหมายข้อมูล ' . $updated . ' รายการเรียบร้อยแล้ว</p></div>';
        }
    }
    
    // Get all unassigned or admin-owned posts
    $args = array(
        'post_type' => 'tpak_verification',
        'posts_per_page' => -1,
        'author__in' => array(1, get_current_user_id()), // Admin or current user
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $posts = get_posts($args);
    
    // Get all interviewers
    $interviewers = get_users(array('role' => 'tpak_interviewer'));
    
    ?>
    <div class="wrap">
        <h1>จัดการการมอบหมายข้อมูล</h1>
        
        <?php echo $message; ?>
        
        <div class="card">
            <h2>มอบหมายข้อมูลให้ Interviewer</h2>
            <p>เลือกข้อมูลที่ต้องการมอบหมายและเลือก Interviewer ที่จะรับผิดชอบ</p>
            
            <form method="post">
                <?php wp_nonce_field('reassign_posts'); ?>
                <input type="hidden" name="reassign_posts" value="1">
                
                <div style="margin-bottom: 20px;">
                    <label for="new_author"><strong>มอบหมายให้:</strong></label>
                    <select name="new_author" id="new_author" required>
                        <option value="">-- เลือก Interviewer --</option>
                        <?php foreach ($interviewers as $user): ?>
                            <option value="<?php echo $user->ID; ?>">
                                <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_login); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="select-all">
                            </td>
                            <th>ชื่อ</th>
                            <th>Survey ID</th>
                            <th>สถานะ</th>
                            <th>ผู้รับผิดชอบปัจจุบัน</th>
                            <th>วันที่นำเข้า</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                            <tr>
                                <td colspan="6">ไม่พบข้อมูลที่สามารถมอบหมายได้</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <?php
                                $survey_id = get_post_meta($post->ID, '_tpak_survey_id', true);
                                $status = get_post_meta($post->ID, '_tpak_workflow_status', true);
                                $author = get_userdata($post->post_author);
                                ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="post_ids[]" value="<?php echo $post->ID; ?>">
                                    </th>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($post->ID); ?>">
                                                <?php echo esc_html($post->post_title); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($survey_id); ?></td>
                                    <td><?php echo tpak_get_status_label($status); ?></td>
                                    <td>
                                        <?php 
                                        if ($author) {
                                            echo esc_html($author->display_name);
                                            if ($author->ID == 1) {
                                                echo ' <em>(Admin)</em>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo get_the_date('d/m/Y H:i', $post); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($posts)): ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            มอบหมายข้อมูลที่เลือก
                        </button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>สรุปการมอบหมาย</h2>
            <?php
            global $wpdb;
            $summary = $wpdb->get_results("
                SELECT 
                    u.display_name,
                    u.ID as user_id,
                    COUNT(p.ID) as post_count
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->users} u ON p.post_author = u.ID
                WHERE p.post_type = 'tpak_verification'
                AND p.post_status = 'publish'
                GROUP BY u.ID
                ORDER BY post_count DESC
            ");
            ?>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>ผู้รับผิดชอบ</th>
                        <th>จำนวนข้อมูล</th>
                        <th>ดูข้อมูล</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->display_name); ?></td>
                            <td><?php echo $row->post_count; ?></td>
                            <td>
                                <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&author=' . $row->user_id); ?>" 
                                   class="button button-small">
                                    ดูข้อมูล
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#select-all').on('change', function() {
            $('input[name="post_ids[]"]').prop('checked', $(this).prop('checked'));
        });
    });
    </script>
    <?php
}

// Helper function
function tpak_get_status_label($status) {
    $labels = array(
        '' => 'รอดำเนินการ',
        'pending_a' => 'รอ Supervisor ตรวจสอบ',
        'pending_b' => 'รอ Examiner ตรวจสอบ',
        'pending_c' => 'รอการอนุมัติ',
        'rejected_by_b' => 'ถูกส่งกลับโดย Supervisor',
        'rejected_by_c' => 'ถูกส่งกลับโดย Examiner',
        'finalized' => 'อนุมัติแล้ว'
    );
    
    return isset($labels[$status]) ? $labels[$status] : 'ไม่ทราบสถานะ';
}

add_action('plugins_loaded', 'tpak_dq_init', 0);