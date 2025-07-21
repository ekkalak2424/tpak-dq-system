<?php
/**
 * ไฟล์: includes/class-tpak-dq-notifications.php
 * จัดการระบบแจ้งเตือน
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Notifications {
    
    public function __construct() {
        // Hook สำหรับส่งแจ้งเตือนเมื่อสถานะเปลี่ยน
        add_action('tpak_workflow_status_changed', array($this, 'send_status_notification'), 10, 3);
        
        // Admin notices
        add_action('admin_notices', array($this, 'display_admin_notices'));
        
        // Email settings
        add_action('admin_init', array($this, 'register_notification_settings'));
    }
    
    public function send_status_notification($post_id, $old_status, $new_status) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        // Get notification recipients based on new status
        $recipients = $this->get_notification_recipients($new_status, $post);
        
        if (empty($recipients)) {
            return;
        }
        
        // Prepare email content
        $subject = $this->get_email_subject($new_status, $post);
        $message = $this->get_email_message($new_status, $post, $old_status);
        
        // Send emails
        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $message, $this->get_email_headers());
        }
        
        // Log notification
        $this->log_notification($post_id, $recipients, $new_status);
    }
    
    private function get_notification_recipients($status, $post) {
        $recipients = array();
        
        switch ($status) {
            case 'pending_a':
                // Notify all supervisors
                $users = get_users(array('role' => 'tpak_supervisor'));
                foreach ($users as $user) {
                    $recipients[] = $user->user_email;
                }
                break;
                
            case 'pending_b':
                // Notify all examiners
                $users = get_users(array('role' => 'tpak_examiner'));
                foreach ($users as $user) {
                    $recipients[] = $user->user_email;
                }
                break;
                
            case 'rejected_by_b':
            case 'rejected_by_c':
                // Notify the original author
                $author = get_userdata($post->post_author);
                if ($author) {
                    $recipients[] = $author->user_email;
                }
                break;
                
            case 'finalized':
                // Notify author and admin
                $author = get_userdata($post->post_author);
                if ($author) {
                    $recipients[] = $author->user_email;
                }
                
                // Also notify admin
                $admin_email = get_option('admin_email');
                if ($admin_email) {
                    $recipients[] = $admin_email;
                }
                break;
        }
        
        // Filter out duplicates
        $recipients = array_unique($recipients);
        
        // Allow filtering
        return apply_filters('tpak_notification_recipients', $recipients, $status, $post);
    }
    
    private function get_email_subject($status, $post) {
        $site_name = get_bloginfo('name');
        
        $subjects = array(
            'pending_a' => '[%s] มีรายการใหม่รอตรวจสอบ',
            'pending_b' => '[%s] มีรายการรอตรวจสอบขั้นสุดท้าย',
            'rejected_by_b' => '[%s] รายการของคุณถูกส่งกลับแก้ไข',
            'rejected_by_c' => '[%s] รายการของคุณถูกส่งกลับแก้ไข',
            'finalized' => '[%s] รายการของคุณได้รับการอนุมัติ'
        );
        
        $subject = isset($subjects[$status]) ? 
                  sprintf($subjects[$status], $site_name) : 
                  sprintf('[%s] การแจ้งเตือนจาก TPAK DQ System', $site_name);
        
        return apply_filters('tpak_email_subject', $subject, $status, $post);
    }
    
    private function get_email_message($status, $post, $old_status) {
        $post_title = $post->post_title;
        $post_link = get_edit_post_link($post->ID);
        $current_user = wp_get_current_user();
        
        $message = "สวัสดีครับ/ค่ะ,\n\n";
        
        switch ($status) {
            case 'pending_a':
                $message .= sprintf(
                    "มีรายการใหม่ '%s' ที่รอการตรวจสอบจาก Supervisor\n\n" .
                    "ส่งโดย: %s\n" .
                    "วันที่: %s\n\n",
                    $post_title,
                    $current_user->display_name,
                    current_time('mysql')
                );
                break;
                
            case 'pending_b':
                $message .= sprintf(
                    "รายการ '%s' ได้ผ่านการตรวจสอบจาก Supervisor แล้ว\n" .
                    "และรอการตรวจสอบขั้นสุดท้ายจาก Examiner\n\n" .
                    "ตรวจสอบโดย: %s\n" .
                    "วันที่: %s\n\n",
                    $post_title,
                    $current_user->display_name,
                    current_time('mysql')
                );
                break;
                
            case 'rejected_by_b':
            case 'rejected_by_c':
                $role = ($status === 'rejected_by_b') ? 'Supervisor' : 'Examiner';
                $message .= sprintf(
                    "รายการ '%s' ถูกส่งกลับเพื่อแก้ไขโดย %s\n\n" .
                    "ผู้ตรวจสอบ: %s\n" .
                    "วันที่: %s\n\n" .
                    "กรุณาตรวจสอบความคิดเห็นและแก้ไขตามที่แนะนำ\n\n",
                    $post_title,
                    $role,
                    $current_user->display_name,
                    current_time('mysql')
                );
                break;
                
            case 'finalized':
                $message .= sprintf(
                    "ยินดีด้วย! รายการ '%s' ได้รับการอนุมัติเรียบร้อยแล้ว\n\n" .
                    "อนุมัติโดย: %s\n" .
                    "วันที่: %s\n\n",
                    $post_title,
                    $current_user->display_name,
                    current_time('mysql')
                );
                break;
        }
        
        $message .= sprintf(
            "คลิกที่ลิงก์ด้านล่างเพื่อดูรายละเอียด:\n%s\n\n" .
            "---\n" .
            "ข้อความนี้ส่งโดยอัตโนมัติจาก TPAK DQ System\n" .
            "กรุณาอย่าตอบกลับอีเมลนี้",
            $post_link
        );
        
        return apply_filters('tpak_email_message', $message, $status, $post, $old_status);
    }
    
    private function get_email_headers() {
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );
        
        return apply_filters('tpak_email_headers', $headers);
    }
    
    private function log_notification($post_id, $recipients, $status) {
        // Log to database or file
        $log_entry = array(
            'post_id' => $post_id,
            'recipients' => $recipients,
            'status' => $status,
            'sent_at' => current_time('mysql'),
            'sent_by' => get_current_user_id()
        );
        
        // Store in post meta for reference
        add_post_meta($post_id, '_tpak_notification_log', $log_entry);
    }
    
    public function display_admin_notices() {
        // Check if there are any pending items for current user
        $user_role = $this->get_user_tpak_role();
        $pending_count = 0;
        
        switch ($user_role) {
            case 'supervisor':
                $pending_count = $this->get_pending_count('pending_a');
                $message = 'คุณมี %d รายการรอตรวจสอบ';
                break;
                
            case 'examiner':
                $pending_count = $this->get_pending_count('pending_b');
                $message = 'คุณมี %d รายการรอตรวจสอบขั้นสุดท้าย';
                break;
                
            case 'interviewer':
                $pending_count = $this->get_rejected_count(get_current_user_id());
                $message = 'คุณมี %d รายการที่ถูกส่งกลับให้แก้ไข';
                break;
        }
        
        if ($pending_count > 0) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php printf($message, $pending_count); ?>
                    <a href="<?php echo admin_url('edit.php?post_type=tpak_verification'); ?>">
                        ดูรายการ
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    public function register_notification_settings() {
        register_setting('tpak_notification_settings', 'tpak_enable_notifications');
        register_setting('tpak_notification_settings', 'tpak_notification_emails');
        
        add_settings_section(
            'tpak_notification_section',
            'การตั้งค่าการแจ้งเตือน',
            array($this, 'render_notification_section'),
            'tpak_notification_settings'
        );
        
        add_settings_field(
            'tpak_enable_notifications',
            'เปิดใช้งานการแจ้งเตือนทางอีเมล',
            array($this, 'render_enable_field'),
            'tpak_notification_settings',
            'tpak_notification_section'
        );
        
        add_settings_field(
            'tpak_notification_emails',
            'อีเมลเพิ่มเติมสำหรับรับการแจ้งเตือน',
            array($this, 'render_emails_field'),
            'tpak_notification_settings',
            'tpak_notification_section'
        );
    }
    
    public function render_notification_section() {
        echo '<p>ตั้งค่าการแจ้งเตือนทางอีเมลเมื่อมีการเปลี่ยนแปลงสถานะ</p>';
    }
    
    public function render_enable_field() {
        $enabled = get_option('tpak_enable_notifications', '1');
        ?>
        <label>
            <input type="checkbox" name="tpak_enable_notifications" value="1" 
                   <?php checked($enabled, '1'); ?> />
            เปิดใช้งานการส่งอีเมลแจ้งเตือน
        </label>
        <?php
    }
    
    public function render_emails_field() {
        $emails = get_option('tpak_notification_emails', '');
        ?>
        <textarea name="tpak_notification_emails" class="large-text" rows="3"
                  placeholder="email1@example.com, email2@example.com"><?php 
            echo esc_textarea($emails); 
        ?></textarea>
        <p class="description">
            ใส่อีเมลเพิ่มเติมที่ต้องการรับการแจ้งเตือน คั่นด้วยเครื่องหมายคอมมา
        </p>
        <?php
    }
    
    private function get_pending_count($status) {
        $args = array(
            'post_type' => 'tpak_verification',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_tpak_workflow_status',
                    'value' => $status,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    private function get_rejected_count($user_id) {
        $args = array(
            'post_type' => 'tpak_verification',
            'post_status' => 'publish',
            'author' => $user_id,
            'meta_query' => array(
                array(
                    'key' => '_tpak_workflow_status',
                    'value' => array('rejected_by_b', 'rejected_by_c'),
                    'compare' => 'IN'
                )
            ),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    private function get_user_tpak_role() {
        $user = wp_get_current_user();
        
        if (in_array('tpak_examiner', $user->roles)) {
            return 'examiner';
        } elseif (in_array('tpak_supervisor', $user->roles)) {
            return 'supervisor';
        } elseif (in_array('tpak_interviewer', $user->roles)) {
            return 'interviewer';
        } elseif (in_array('administrator', $user->roles)) {
            return 'admin';
        }
        
        return '';
    }
}