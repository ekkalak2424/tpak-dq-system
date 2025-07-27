<?php
/**
 * ไฟล์: includes/class-tpak-dq-meta-boxes.php
 * จัดการ Meta Boxes สำหรับ Post Type
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Meta_Boxes {
    
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'));
        
        // AJAX handlers
        add_action('wp_ajax_tpak_auto_save_survey_data', array($this, 'ajax_auto_save_survey_data'));
    }
    
    public function add_meta_boxes() {
        // Meta box สำหรับข้อมูลการตรวจสอบ
        add_meta_box(
            'tpak_verification_data',
            'ข้อมูลการตรวจสอบ',
            array($this, 'render_verification_data'),
            'tpak_verification',
            'normal',
            'high'
        );
        
        // Meta box สำหรับ Workflow Status
        add_meta_box(
            'tpak_workflow_status',
            'สถานะ Workflow',
            array($this, 'render_workflow_status'),
            'tpak_verification',
            'side',
            'high'
        );
        
        // Meta box สำหรับ Audit Trail
        add_meta_box(
            'tpak_audit_trail',
            'ประวัติการดำเนินการ',
            array($this, 'render_audit_trail'),
            'tpak_verification',
            'normal',
            'low'
        );
    }
    
    public function render_verification_data($post) {
        wp_nonce_field('tpak_save_meta', 'tpak_meta_nonce');
        
        // Get saved data
        $survey_id = get_post_meta($post->ID, '_tpak_survey_id', true);
        $response_id = get_post_meta($post->ID, '_tpak_response_id', true);
        $import_date = get_post_meta($post->ID, '_tpak_import_date', true);
        $verification_notes = get_post_meta($post->ID, '_tpak_verification_notes', true);
        
        ?>
        <div class="tpak-meta-box">
            <table class="form-table">
                <tr>
                    <th><label>Survey ID:</label></th>
                    <td><strong><?php echo esc_html($survey_id); ?></strong></td>
                </tr>
                <tr>
                    <th><label>Response ID:</label></th>
                    <td><strong><?php echo esc_html($response_id); ?></strong></td>
                </tr>
                <tr>
                    <th><label>วันที่นำเข้า:</label></th>
                    <td><?php echo $import_date ? date_i18n('d/m/Y H:i:s', strtotime($import_date)) : '-'; ?></td>
                </tr>
            </table>
            
            <p>
                <label for="tpak_verification_notes"><strong>หมายเหตุการตรวจสอบ:</strong></label><br>
                <textarea id="tpak_verification_notes" name="tpak_verification_notes" 
                          class="widefat" rows="5"><?php echo esc_textarea($verification_notes); ?></textarea>
            </p>
        </div>
        <?php
    }
    
    public function render_survey_data($post) {
        // Get import data
        $import_data = get_post_meta($post->ID, '_tpak_import_data', true);
        
        if (!$import_data || !is_array($import_data)) {
            echo '<p>ไม่พบข้อมูลแบบสอบถาม</p>';
            return;
        }
        
        ?>
        <div class="tpak-survey-data">
            <style>
                .tpak-survey-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                .tpak-survey-table th,
                .tpak-survey-table td {
                    padding: 8px 12px;
                    text-align: left;
                    border: 1px solid #ddd;
                }
                .tpak-survey-table th {
                    background-color: #f5f5f5;
                    font-weight: bold;
                    width: 30%;
                }
                .tpak-survey-table td {
                    background-color: #fff;
                }
                .tpak-survey-table tr:nth-child(even) td {
                    background-color: #f9f9f9;
                }
                .tpak-data-section {
                    margin-bottom: 20px;
                }
                .tpak-data-section h4 {
                    margin: 15px 0 10px 0;
                    padding: 5px 10px;
                    background: #23282d;
                    color: #fff;
                    font-size: 14px;
                }
                .tpak-value-long {
                    max-width: 400px;
                    word-wrap: break-word;
                }
                .tpak-empty {
                    color: #999;
                    font-style: italic;
                }
            </style>
            
            <?php
            // แสดงข้อมูลพื้นฐาน
            $basic_fields = array(
                'id' => 'ID',
                'tid' => 'Token ID', 
                'token' => 'Token',
                'submitdate' => 'วันที่ส่ง',
                'lastpage' => 'หน้าสุดท้าย',
                'startlanguage' => 'ภาษา',
                'completed' => 'สถานะ',
                'startdate' => 'วันที่เริ่ม',
                'datestamp' => 'วันที่บันทึก',
                'ipaddr' => 'IP Address'
            );
            
            echo '<div class="tpak-data-section">';
            echo '<h4>ข้อมูลพื้นฐาน</h4>';
            echo '<table class="tpak-survey-table">';
            
            $has_basic_info = false;
            foreach ($basic_fields as $field => $label) {
                if (isset($import_data[$field]) && !empty($import_data[$field]) && $import_data[$field] != 'N') {
                    $has_basic_info = true;
                    $value = $import_data[$field];
                    
                    // Format dates
                    if (in_array($field, array('submitdate', 'startdate', 'datestamp'))) {
                        $value = date_i18n('d/m/Y H:i:s', strtotime($value));
                    }
                    
                    // Format completed status
                    if ($field == 'completed') {
                        $value = ($value == 'Y' || $value == '1') ? '<span style="color:green;">✓ สมบูรณ์</span>' : '<span style="color:red;">✗ ไม่สมบูรณ์</span>';
                    }
                    
                    echo '<tr>';
                    echo '<th>' . esc_html($label) . '</th>';
                    echo '<td>' . wp_kses_post($value) . '</td>';
                    echo '</tr>';
                }
            }
            
            if (!$has_basic_info) {
                echo '<tr><td colspan="2" class="tpak-empty">ไม่มีข้อมูลพื้นฐาน</td></tr>';
            }
            
            echo '</table>';
            echo '</div>';
            
            // แสดงคำถามและคำตอบ
            echo '<div class="tpak-data-section">';
            echo '<h4>คำถามและคำตอบ</h4>';
            echo '<table class="tpak-survey-table">';
            
            $question_count = 0;
            $skip_fields = array_merge(array_keys($basic_fields), array('refurl', 'seed'));
            
            foreach ($import_data as $key => $value) {
                // ข้ามฟิลด์พื้นฐานและฟิลด์ระบบ
                if (in_array($key, $skip_fields)) {
                    continue;
                }
                
                // แสดงเฉพาะที่มีค่า
                if (!empty($value) && $value != 'N' && $value != '') {
                    $question_count++;
                    
                    // ตรวจสอบว่าเป็นคำถามแบบไหน
                    $field_label = $this->get_field_label($key);
                    
                    echo '<tr>';
                    echo '<th>' . esc_html($field_label) . '</th>';
                    echo '<td class="tpak-value-long">';
                    
                    // ถ้าเป็น array (multiple choice)
                    if (is_array($value)) {
                        echo esc_html(implode(', ', $value));
                    } else {
                        // แสดงข้อความยาว
                        echo nl2br(esc_html($value));
                    }
                    
                    echo '</td>';
                    echo '</tr>';
                }
            }
            
            if ($question_count == 0) {
                echo '<tr><td colspan="2" class="tpak-empty">ไม่พบข้อมูลคำตอบ</td></tr>';
            }
            
            echo '</table>';
            echo '</div>';
            
            // สรุปจำนวนข้อมูล
            echo '<div class="tpak-data-section">';
            echo '<p><strong>สรุป:</strong> พบข้อมูลทั้งหมด ' . count($import_data) . ' ฟิลด์ (แสดง ' . $question_count . ' คำถาม)</p>';
            echo '</div>';
            
            // แสดง Raw Data สำหรับ Debug (ซ่อนไว้)
            ?>
            <div class="tpak-data-section">
                <h4 style="cursor: pointer;" onclick="jQuery('#tpak-raw-data').toggle();">
                    ข้อมูลดิบ (คลิกเพื่อแสดง/ซ่อน) ▼
                </h4>
                <div id="tpak-raw-data" style="display: none;">
                    <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px; font-size: 11px;">
<?php print_r($import_data); ?>
                    </pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function render_workflow_status($post) {
        $current_status = get_post_meta($post->ID, '_tpak_workflow_status', true);
        $current_role = $this->get_user_tpak_role();
        $current_user = wp_get_current_user();
        $is_post_author = ($post->post_author == $current_user->ID);
        
        ?>
        <div class="tpak-workflow-status">
            <p><strong>สถานะปัจจุบัน:</strong></p>
            <p style="font-size: 16px; margin: 10px 0;">
                <span class="status-<?php echo esc_attr($current_status); ?>">
                    <?php echo $this->get_status_label($current_status); ?>
                </span>
            </p>
            
            <?php 
            // สำหรับ Interviewer ต้องเป็นเจ้าของ post เท่านั้น
            $can_update = false;
            if ($current_role === 'interviewer') {
                if ($is_post_author && $this->can_update_status($current_role, $current_status)) {
                    $can_update = true;
                }
            } else {
                $can_update = $this->can_update_status($current_role, $current_status);
            }
            ?>
            
            <?php if ($can_update): ?>
            <div class="tpak-status-actions" style="margin-top: 20px;">
                <h4>อัพเดทสถานะ:</h4>
                <?php $this->render_status_buttons($current_role, $current_status); ?>
            </div>
            <?php else: ?>
            <p style="color: #666; font-style: italic; font-size: 13px;">
                <?php 
                if ($current_role === 'interviewer' && !$is_post_author) {
                    echo 'คุณสามารถแก้ไขได้เฉพาะข้อมูลที่คุณสร้างเท่านั้น';
                } else {
                    echo 'คุณไม่มีสิทธิ์เปลี่ยนสถานะในขั้นตอนนี้';
                }
                ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_audit_trail($post) {
        global $wpdb;
        
        $audit_table = $wpdb->prefix . 'tpak_dq_audit';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$audit_table'") == $audit_table;
        
        if (!$table_exists) {
            echo '<p>ยังไม่มีประวัติการดำเนินการ</p>';
            return;
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $audit_table WHERE post_id = %d ORDER BY created_at DESC LIMIT 20",
            $post->ID
        ));
        
        if ($results) {
            ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>ผู้ดำเนินการ</th>
                        <th>การดำเนินการ</th>
                        <th>สถานะเดิม</th>
                        <th>สถานะใหม่</th>
                        <th>หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $log): ?>
                    <?php $user = get_userdata($log->user_id); ?>
                    <tr>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($log->created_at)); ?></td>
                        <td><?php echo $user ? esc_html($user->display_name) : 'Unknown'; ?></td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo $this->get_status_label($log->old_status); ?></td>
                        <td><?php echo $this->get_status_label($log->new_status); ?></td>
                        <td><?php echo esc_html($log->comment); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        } else {
            echo '<p>ยังไม่มีประวัติการดำเนินการ</p>';
        }
    }
    
    public function save_meta_data($post_id) {
        // Verify nonce for basic meta
        if (isset($_POST['tpak_meta_nonce']) && 
            wp_verify_nonce($_POST['tpak_meta_nonce'], 'tpak_save_meta')) {
            
            // Check autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }
            
            // Check permissions
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
            
            // Save verification notes
            if (isset($_POST['tpak_verification_notes'])) {
                update_post_meta($post_id, '_tpak_verification_notes', 
                                sanitize_textarea_field($_POST['tpak_verification_notes']));
            }
        }
        
        // Save survey data if user has permission
        if (isset($_POST['tpak_survey_data_nonce']) && 
            wp_verify_nonce($_POST['tpak_survey_data_nonce'], 'tpak_save_survey_data')) {
            
            // Check permissions
            $can_edit = false;
            $current_user = wp_get_current_user();
            
            if (current_user_can('manage_options')) {
                $can_edit = true;
            } elseif (in_array('tpak_interviewer', $current_user->roles)) {
                $post = get_post($post_id);
                if ($post && $post->post_author == $current_user->ID) {
                    $can_edit = true;
                }
            }
            
            if ($can_edit && isset($_POST['tpak_survey_data'])) {
                // Sanitize all data
                $survey_data = array();
                foreach ($_POST['tpak_survey_data'] as $key => $value) {
                    $survey_data[sanitize_key($key)] = sanitize_textarea_field($value);
                }
                
                // Update the data
                update_post_meta($post_id, '_tpak_import_data', $survey_data);
                
                // Log the change
                global $wpdb;
                $audit_table = $wpdb->prefix . 'tpak_dq_audit';
                $wpdb->insert($audit_table, array(
                    'user_id' => get_current_user_id(),
                    'post_id' => $post_id,
                    'action' => 'survey_data_updated',
                    'old_status' => '',
                    'new_status' => '',
                    'comment' => 'อัพเดทข้อมูลแบบสอบถาม',
                    'created_at' => current_time('mysql')
                ));
            }
        }
    }
    
    // AJAX handler for auto-save
    public function ajax_auto_save_survey_data() {
        // Check nonce
        if (!check_ajax_referer('tpak_dq_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $survey_data = isset($_POST['survey_data']) ? $_POST['survey_data'] : array();
        
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }
        
        // Check permissions
        $can_edit = false;
        $current_user = wp_get_current_user();
        
        if (current_user_can('manage_options')) {
            $can_edit = true;
        } elseif (in_array('tpak_interviewer', $current_user->roles)) {
            $post = get_post($post_id);
            if ($post && $post->post_author == $current_user->ID) {
                $can_edit = true;
            }
        }
        
        if (!$can_edit) {
            wp_send_json_error('คุณไม่มีสิทธิ์แก้ไขข้อมูลนี้');
        }
        
        // Get existing data
        $existing_data = get_post_meta($post_id, '_tpak_import_data', true);
        if (!is_array($existing_data)) {
            $existing_data = array();
        }
        
        // Merge with new data
        foreach ($survey_data as $key => $value) {
            $norm_key = $this->normalize_survey_key($key);
            $existing_data[$norm_key] = sanitize_textarea_field($value);
        }
        
        // Save data
        update_post_meta($post_id, '_tpak_import_data', $existing_data);
        
        // Log the change
        global $wpdb;
        $audit_table = $wpdb->prefix . 'tpak_dq_audit';
        $wpdb->insert($audit_table, array(
            'user_id' => get_current_user_id(),
            'post_id' => $post_id,
            'action' => 'auto_save_survey_data',
            'old_status' => '',
            'new_status' => '',
            'comment' => 'บันทึกอัตโนมัติข้อมูลแบบสอบถาม',
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success('บันทึกข้อมูลเรียบร้อย');
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
    
    private function can_update_status($role, $current_status) {
        $permissions = array(
            'interviewer' => array('', 'rejected_by_b', 'rejected_by_c'),
            'supervisor' => array('pending_a'),
            'examiner' => array('pending_b'),
            'admin' => array('', 'pending_a', 'pending_b', 'pending_c', 
                           'rejected_by_b', 'rejected_by_c', 'finalized')
        );
        
        // แก้ไขให้ map role ที่ถูกต้อง
        $mapped_role = $role;
        if ($role === 'interviewer') $mapped_role = 'interviewer';
        if ($role === 'supervisor') $mapped_role = 'supervisor';
        if ($role === 'examiner') $mapped_role = 'examiner';
        if ($role === 'admin') $mapped_role = 'admin';
        
        return isset($permissions[$mapped_role]) && in_array($current_status, $permissions[$mapped_role]);
    }
    
    private function render_status_buttons($role, $current_status) {
        $buttons = array();
        
        // แก้ไขให้ map role ที่ถูกต้อง
        $mapped_role = $role;
        if ($role === 'interviewer') $mapped_role = 'interviewer';
        if ($role === 'supervisor') $mapped_role = 'supervisor';
        if ($role === 'examiner') $mapped_role = 'examiner';
        if ($role === 'admin') $mapped_role = 'admin';
        
        switch ($mapped_role) {
            case 'interviewer':
                if (in_array($current_status, array('', 'rejected_by_b', 'rejected_by_c'))) {
                    $buttons['pending_a'] = 'ส่งตรวจสอบไปยัง Supervisor';
                }
                break;
                
            case 'supervisor':
                if ($current_status === 'pending_a') {
                    $buttons['pending_b'] = 'ส่งต่อให้ Examiner';
                    $buttons['rejected_by_b'] = 'ส่งกลับให้แก้ไข';
                }
                break;
                
            case 'examiner':
                if ($current_status === 'pending_b') {
                    $buttons['finalized'] = 'อนุมัติ';
                    $buttons['rejected_by_c'] = 'ส่งกลับให้แก้ไข';
                }
                break;
                
            case 'admin':
                // Admin can change to any status
                if ($current_status !== 'pending_a') {
                    $buttons['pending_a'] = 'ส่งให้ Supervisor';
                }
                if ($current_status !== 'pending_b') {
                    $buttons['pending_b'] = 'ส่งให้ Examiner';
                }
                if ($current_status !== 'finalized') {
                    $buttons['finalized'] = 'อนุมัติ';
                }
                if (!in_array($current_status, array('rejected_by_b', 'rejected_by_c'))) {
                    $buttons['rejected_by_b'] = 'ส่งกลับ (Supervisor)';
                    $buttons['rejected_by_c'] = 'ส่งกลับ (Examiner)';
                }
                break;
        }
        
        if (empty($buttons)) {
            echo '<p style="color: #999;">ไม่มีการดำเนินการที่สามารถทำได้ในสถานะนี้</p>';
            return;
        }
        
        foreach ($buttons as $status => $label) {
            ?>
            <button type="button" 
                    class="button button-primary tpak-update-status" 
                    data-status="<?php echo esc_attr($status); ?>"
                    style="margin-bottom: 5px; width: 100%; cursor: pointer;">
                <?php echo esc_html($label); ?>
            </button>
            <?php
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

    // Utility: normalize survey key (copy from renderer)
    private function normalize_survey_key($key) {
        $key = trim($key);
        $key = preg_replace('/\s+/', '', $key);
        $key = preg_replace('/\[([0-9_]+)\]/', '[$1]', $key);
        $key = rtrim($key, '_');
        return $key;
    }
}