<?php
/**
 * ไฟล์: includes/class-tpak-dq-workflow.php
 * จัดการระบบ Workflow และการเปลี่ยนสถานะ
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Workflow {
    
    public function __construct() {
        add_action('wp_ajax_tpak_update_workflow_status', array($this, 'ajax_update_status'));
        add_action('transition_post_status', array($this, 'log_status_change'), 10, 3);
    }
    
public function ajax_update_status() {
    // Check nonce
    if (!check_ajax_referer('tpak_dq_nonce', 'nonce', false)) {
        wp_die('Security check failed');
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
    
    if (!$post_id || !$new_status) {
        wp_send_json_error('Invalid parameters');
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        wp_send_json_error('Permission denied');
    }
    
    // Get current status
    $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
    
    // Update status
    $updated = update_post_meta($post_id, '_tpak_workflow_status', $new_status);
    
    // Log the change
    $this->log_workflow_change($post_id, $current_status, $new_status, $comment);
    
    // Send notification
    do_action('tpak_workflow_status_changed', $post_id, $current_status, $new_status);
    
    wp_send_json_success(array(
        'message' => 'Status updated successfully',
        'new_status' => $new_status,
        'status_label' => $this->get_status_label($new_status)
    ));
}
    
    private function log_workflow_change($post_id, $old_status, $new_status, $comment = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'tpak_dq_audit';
        
        $wpdb->insert($table, array(
            'user_id' => get_current_user_id(),
            'post_id' => $post_id,
            'action' => 'status_change',
            'old_status' => $old_status,
            'new_status' => $new_status,
            'comment' => $comment,
            'created_at' => current_time('mysql')
        ));
    }
    
    public function log_status_change($new_status, $old_status, $post) {
        if ($post->post_type !== 'tpak_verification') {
            return;
        }
        
        if ($new_status !== $old_status) {
            $this->log_workflow_change($post->ID, $old_status, $new_status, 'Post status changed');
        }
    }
    
    private function get_status_label($status) {
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
}