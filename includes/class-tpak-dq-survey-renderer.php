<?php
/**
 * ไฟล์: includes/class-tpak-dq-survey-renderer.php
 * จัดการการแสดงผลคำถามและคำตอบจาก LimeSurvey แบบสมบูรณ์
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Survey_Renderer {
    
    private $api_client;
    private $survey_cache = array();
    private $question_labels = array();
    private $answer_options = array();
    
    public function __construct() {
        // Hook สำหรับ AJAX
        add_action('wp_ajax_tpak_refresh_survey_structure', array($this, 'ajax_refresh_survey_structure'));
        add_action('wp_ajax_tpak_save_answer', array($this, 'ajax_save_answer'));
        add_action('wp_ajax_tpak_save_survey_answers', array($this, 'ajax_save_survey_answers'));
        
        // เพิ่ม Meta Box ใหม่
        add_action('add_meta_boxes', array($this, 'add_survey_preview_metabox'), 15);
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Debug logging removed for performance
    }
    
    // Debug logging removed for performance

    public function enqueue_admin_assets($hook) {
        // เฉพาะหน้า post.php/post-new.php ของ tpak_verification
        global $post;
        if (($hook === 'post.php' || $hook === 'post-new.php') && isset($post) && $post->post_type === 'tpak_verification') {
            wp_enqueue_script('tpak-admin-js', TPAK_DQ_PLUGIN_URL . 'assets/admin.js', array('jquery'), '1.0', true);
            wp_localize_script('tpak-admin-js', 'tpak_dq', array(
                'nonce' => wp_create_nonce('tpak_dq_nonce'),
                'ajax_url' => admin_url('admin-ajax.php')
            ));
        }
    }
    
    /**
     * เพิ่ม Meta Box สำหรับแสดง Survey Preview
     */
    public function add_survey_preview_metabox() {
        add_meta_box(
            'tpak_survey_preview',
            'แสดงแบบสอบถาม (Preview)',
            array($this, 'render_survey_preview_metabox'),
            'tpak_verification',
            'normal',
            'high'
        );
    }
    
    /**
     * Render Survey Preview Meta Box
     */
    public function render_survey_preview_metabox($post) {
        $survey_id = get_post_meta($post->ID, '_tpak_survey_id', true);
        $response_data = get_post_meta($post->ID, '_tpak_import_data', true);
        $survey_structure = get_post_meta($post->ID, '_tpak_survey_structure', true);
        if (!$survey_id) {
            echo '<p>ไม่พบข้อมูล Survey ID</p>';
            return;
        }
        
        ?>
        <div class="tpak-survey-preview-wrapper">
            <?php $refresh_nonce = wp_create_nonce('tpak_survey_nonce'); ?>
            <style>
                .tpak-survey-preview {
                    background: #fff;
                    border-radius: 8px;
                    padding: 20px;
                    margin-top: 15px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                
                .tpak-survey-header {
                    border-bottom: 2px solid #f0f0f1;
                    padding-bottom: 15px;
                    margin-bottom: 20px;
                }
                
                .tpak-survey-title {
                    color: #1d2327;
                    font-size: 1.5em;
                    margin: 0 0 10px 0;
                }
                
                .tpak-info-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .tpak-info-table td {
                    padding: 8px 12px;
                    border-bottom: 1px solid #f0f0f1;
                }
                
                .tpak-info-table td:first-child {
                    font-weight: 600;
                    color: #1d2327;
                    width: 30%;
                }
                
                .tpak-question-group {
                    margin-bottom: 30px;
                }
                
                .tpak-group-title {
                    color: #1d2327;
                    font-size: 1.3em;
                    margin: 0 0 15px 0;
                    padding: 10px 15px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 6px;
                }
                
                .tpak-question-item {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 15px;
                    transition: box-shadow 0.2s ease;
                }
                
                .tpak-question-item:hover {
                    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                }
                
                .tpak-question-code {
                    font-size: 12px;
                    color: #6c757d;
                    margin-bottom: 8px;
                    font-weight: 500;
                }
                
                .tpak-question-type {
                    background: #0073aa;
                    color: white;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 10px;
                    margin-left: 8px;
                }
                
                .tpak-question-text {
                    font-size: 16px;
                    color: #1d2327;
                    margin-bottom: 15px;
                    line-height: 1.5;
                }
                
                .tpak-question-mandatory {
                    color: #dc3545;
                    font-weight: bold;
                }
                
                .tpak-question-help {
                    background: #e3f2fd;
                    border-left: 4px solid #2196f3;
                    padding: 10px 15px;
                    margin: 10px 0;
                    border-radius: 0 4px 4px 0;
                    font-size: 14px;
                    color: #1976d2;
                }
                
                .tpak-answer-display {
                    margin-top: 10px;
                }
                
                .tpak-sub-question {
                    margin-bottom: 15px;
                    padding: 10px;
                    border-left: 3px solid #0073aa;
                    background: #f8f9fa;
                }
                
                .tpak-sub-question-label {
                    font-weight: 600;
                    color: #1d2327;
                    margin-bottom: 8px;
                }
                
                .tpak-sub-question-answer {
                    margin-left: 10px;
                }
                
                .tpak-save-answer-wrapper {
                    margin-top: 15px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                
                .tpak-save-answer-btn {
                    background: #0073aa !important;
                    color: white !important;
                    border: none !important;
                    padding: 8px 16px !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    font-size: 14px !important;
                    transition: background-color 0.2s ease !important;
                }
                
                .tpak-save-answer-btn:hover {
                    background: #005a87 !important;
                }
                
                .tpak-save-answer-btn:disabled {
                    background: #ccc !important;
                    cursor: not-allowed !important;
                }
                
                .tpak-save-status {
                    font-size: 12px;
                    font-style: italic;
                }
                
                .tpak-save-status.success {
                    color: #28a745;
                }
                
                .tpak-save-status.error {
                    color: #dc3545;
                }
                
                .tpak-save-status.loading {
                    color: #0073aa;
                }
                
                .tpak-form-actions {
                    margin-top: 30px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    text-align: center;
                }
                
                .tpak-save-button {
                    background: #0073aa !important;
                    color: white !important;
                    border: none !important;
                    padding: 12px 24px !important;
                    border-radius: 6px !important;
                    cursor: pointer !important;
                    font-size: 16px !important;
                    font-weight: 600 !important;
                    transition: all 0.2s ease !important;
                }
                
                .tpak-save-button:hover {
                    background: #005a87 !important;
                    transform: translateY(-1px);
                }
                
                .tpak-save-button:disabled {
                    background: #ccc !important;
                    cursor: not-allowed !important;
                    transform: none !important;
                }
                
                .tpak-save-button.saved {
                    background: #28a745 !important;
                }
                
                /* ซ่อน hidden fields ไม่ให้แสดงเป็นคำถาม */
                input[type="hidden"] {
                    display: none !important;
                }
                
                /* ซ่อน nonce และ system fields */
                input[name*="nonce"],
                input[name*="_wp_http_referer"],
                input[name="post_id"],
                input[name="tpak_survey_nonce"] {
                    display: none !important;
                }
                
                /* ซ่อน hidden fields ทั้งหมด */
                input[type="hidden"] {
                    display: none !important;
                    visibility: hidden !important;
                    position: absolute !important;
                    left: -9999px !important;
                }
                
                .tpak-radio-options {
                    display: flex;
                    flex-direction: column;
                    gap: 8px;
                }
                
                .tpak-radio-option {
                    display: flex;
                    align-items: center;
                    padding: 10px 15px;
                    border: 2px solid #e9ecef;
                    border-radius: 6px;
                    transition: all 0.2s ease;
                    background: white;
                }
                
                .tpak-radio-option:hover {
                    border-color: #0073aa;
                    background: #f8f9fa;
                }
                
                .tpak-radio-option.selected {
                    border-color: #0073aa;
                    background: #e3f2fd;
                }
                
                .tpak-radio-option input[type="radio"] {
                    margin-right: 10px;
                    transform: scale(1.2);
                }
                
                .tpak-radio-option label {
                    font-size: 14px;
                    color: #1d2327;
                    cursor: default;
                    margin: 0;
                    flex: 1;
                }
                
                .tpak-text-answer {
                    background: white;
                    border: 1px solid #e9ecef;
                    border-radius: 6px;
                    padding: 12px 15px;
                    font-size: 14px;
                    color: #1d2327;
                    line-height: 1.5;
                }
                
                .tpak-answer-empty {
                    color: #6c757d;
                    font-style: italic;
                    padding: 10px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    text-align: center;
                }
                
                .tpak-array-answers {
                    margin-top: 15px;
                }
                
                .tpak-array-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: white;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                }
                
                .tpak-array-table th {
                    background: #f8f9fa;
                    padding: 12px 15px;
                    text-align: left;
                    font-weight: 600;
                    color: #1d2327;
                    border-bottom: 2px solid #e9ecef;
                }
                
                .tpak-array-table td {
                    padding: 12px 15px;
                    border-bottom: 1px solid #f0f0f1;
                    vertical-align: top;
                }
                
                .tpak-array-table tr:hover {
                    background: #f8f9fa;
                }
                
                .tpak-mainquestion-label {
                    font-weight: 600;
                    color: #1d2327;
                }
                
                .tpak-subquestion-label {
                    color: #495057;
                    font-size: 14px;
                }
                
                .tpak-survey-actions {
                    margin-bottom: 20px;
                }
                
                .tpak-refresh-button {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    color: white;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                
                .tpak-refresh-button:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                }
                
                .tpak-text-input {
                    width: 100%;
                    padding: 8px 12px;
                    border: 2px solid #e1e5e9;
                    border-radius: 6px;
                    font-size: 14px;
                    transition: border-color 0.3s ease;
                }
                
                .tpak-text-input:focus {
                    outline: none;
                    border-color: #0073aa;
                    box-shadow: 0 0 0 3px rgba(0, 115, 170, 0.1);
                }
                
                .tpak-radio-input {
                    margin-right: 8px;
                }
                
                .tpak-radio-label {
                    cursor: pointer;
                    font-size: 14px;
                    color: #1d2327;
                }
                
                .tpak-array-item {
                    margin-bottom: 15px;
                    padding: 12px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    border-left: 4px solid #0073aa;
                }
                
                .tpak-item-label {
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #1d2327;
                }
                
                .tpak-array-radio-group,
                .tpak-array-text-group {
                    margin-top: 10px;
                }
                
                .tpak-save-button {
                    background: linear-gradient(135deg, #0073aa 0%, #005a87 100%);
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    margin-top: 20px;
                }
                
                .tpak-save-button:hover {
                    background: linear-gradient(135deg, #005a87 0%, #004466 100%);
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(0, 115, 170, 0.3);
                }
                
                .tpak-save-button:disabled {
                    background: #ccc;
                    cursor: not-allowed;
                    transform: none;
                    box-shadow: none;
                }
                
                @media (max-width: 768px) {
                    .tpak-radio-options {
                        gap: 6px;
                    }
                    
                    .tpak-radio-option {
                        padding: 8px 12px;
                    }
                    
                    .tpak-question-item {
                        padding: 15px;
                    }
                    
                    .tpak-array-table {
                        font-size: 14px;
                    }
                    
                    .tpak-array-table th,
                    .tpak-array-table td {
                        padding: 8px 10px;
                    }
                }
            </style>
            
            <div class="tpak-survey-actions">
                <button type="button" class="button button-primary tpak-refresh-button" 
                        data-survey-id="<?php echo esc_attr($survey_id); ?>"
                        data-post-id="<?php echo esc_attr($post->ID); ?>"
                        data-nonce="<?php echo esc_attr($refresh_nonce); ?>">
                    <span class="dashicons dashicons-update"></span>
                    ดึงคำถามจาก LimeSurvey
                </button>
                
                <?php if (!$survey_structure || !is_array($survey_structure)): ?>
                <p class="description">คลิกปุ่มด้านบนเพื่อดึงข้อความคำถามแบบสมบูรณ์จาก LimeSurvey</p>
                <?php endif; ?>
            </div>
            
            <div class="tpak-survey-preview" id="survey-preview-<?php echo $post->ID; ?>">
                <div class="tpak-survey-header">
                    <h3 class="tpak-survey-title"><?php echo esc_html($post->post_title); ?></h3>
                    <div class="tpak-survey-stats">
                        <span class="tpak-stat-item">
                            <strong>จำนวนคำถาม:</strong> <?php echo is_array($response_data) ? count($response_data) : 0; ?>
                        </span>
                        <span class="tpak-stat-item">
                            <strong>อัพเดทล่าสุด:</strong> <?php echo get_the_modified_date('d/m/Y H:i', $post->ID); ?>
                        </span>
                    </div>
                </div>
                
                <div id="tpak-survey-form">
                    <!-- Hidden fields for AJAX -->
                    <input type="hidden" name="tpak_survey_nonce" value="<?php echo wp_create_nonce('tpak_save_survey_answers'); ?>">
                    <input type="hidden" name="post_id" value="<?php echo $post->ID; ?>">
                    
                <?php 
                if ($survey_structure && is_array($survey_structure)) {
                    $this->render_survey_structure($survey_structure, $response_data);
                } else {
                    // แสดงเฉพาะข้อมูลที่มี
                    $this->render_survey_with_answers($response_data);
                }
                ?>
                    
                    <div class="tpak-form-actions">
                        <button type="button" class="tpak-save-button tpak-save-answers" 
                                data-post-id="<?php echo $post->ID; ?>" 
                                data-nonce="<?php echo wp_create_nonce('tpak_save_survey_answers'); ?>">
                            บันทึกคำตอบ
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($){
            // Refresh survey structure
            $('.tpak-refresh-button').on('click', function(){
                var button = $(this);
                var surveyId = button.data('survey-id');
                var postId = button.data('post-id');
                var nonce = button.data('nonce');
                
                button.prop('disabled', true).text('กำลังดึงข้อมูล...');
                
                $.post(tpak_dq.ajax_url, {
                        action: 'tpak_refresh_survey_structure',
                        survey_id: surveyId,
                        post_id: postId,
                        nonce: nonce
                }, function(response){
                    if(response.success){
                        $('#survey-preview-' + postId).html(response.data.html);
                        button.prop('disabled', false).text('รีเฟรชโครงสร้างแบบสอบถาม');
                        } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data);
                        button.prop('disabled', false).text('รีเฟรชโครงสร้างแบบสอบถาม');
                    }
                });
            });
            
            // Save survey answers
            $('.tpak-save-answers').on('click', function(){
                var button = $(this);
                var postId = button.data('post-id');
                var nonce = button.data('nonce');
                
                // Collect all form data - เฉพาะ input ที่เป็นคำตอบจริงๆ
                var formData = {};
                
                $('.tpak-survey-preview .tpak-answer-input').each(function(){
                    var name = $(this).attr('name');
                    var value = $(this).val();
                    
                    if (name && value !== undefined) {
                        formData[name] = value;
                    }
                });
                
                button.prop('disabled', true).text('กำลังบันทึก...');
                
                var ajaxData = {
                    action: 'tpak_save_survey_answers',
                    post_id: postId,
                    answers: formData,
                    nonce: nonce
                };
                
                $.post(tpak_dq.ajax_url, ajaxData, function(response){
                    if(response.success){
                        button.text('บันทึกแล้ว!').addClass('saved');
                        setTimeout(function(){
                            button.prop('disabled', false).text('บันทึกคำตอบ').removeClass('saved');
                        }, 2000);
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data);
                        button.prop('disabled', false).text('บันทึกคำตอบ');
                    }
                }).fail(function(xhr, status, error) {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
                    button.prop('disabled', false).text('บันทึกคำตอบ');
                });
            });
            
            // Auto-save for matrix inputs
            $('.tpak-matrix-input').on('change', function(){
                var input = $(this);
                var postId = input.data('post-id');
                var nonce = input.data('nonce');
                var name = input.attr('name');
                var value = input.val();
                
                $.post(ajaxurl, {
                    action: 'tpak_auto_save_matrix',
                    post_id: postId,
                    field_name: name,
                    field_value: value,
                    nonce: nonce
                }, function(response){
                    if(response.success){
                        input.addClass('saved');
                        setTimeout(function(){
                            input.removeClass('saved');
                        }, 1000);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler สำหรับบันทึกคำตอบ
     */
    public function ajax_save_answer() {
        // ตรวจสอบ nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_dq_nonce')) {
            wp_die('Security check failed');
        }
        
        // ตรวจสอบข้อมูลที่จำเป็น
        if (!isset($_POST['post_id']) || !isset($_POST['question_code']) || !isset($_POST['answer_value'])) {
            wp_send_json_error('Missing required data');
        }
        
        $post_id = intval($_POST['post_id']);
        $question_code = sanitize_text_field($_POST['question_code']);
        $answer_value = sanitize_text_field($_POST['answer_value']);
        
        // ตรวจสอบสิทธิ์
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        // ดึงข้อมูล response ที่มีอยู่
        $response_data = get_post_meta($post_id, '_tpak_import_data', true);
        if (!is_array($response_data)) {
            $response_data = array();
        }
        
        // อัปเดตคำตอบ
        $response_data[$question_code] = $answer_value;
        
        // บันทึกลงฐานข้อมูล
        $result = update_post_meta($post_id, '_tpak_import_data', $response_data);
        
        if ($result) {
            wp_send_json_success('Answer saved successfully');
        } else {
            wp_send_json_error('Failed to save answer');
        }
    }
    
    /**
     * AJAX handler สำหรับบันทึกคำตอบทั้งหมด
     */
    public function ajax_save_survey_answers() {
        // ตรวจสอบ nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_save_survey_answers')) {
            wp_send_json_error('Security check failed');
        }
        
        // ตรวจสอบข้อมูลที่จำเป็น
        if (!isset($_POST['post_id']) || !isset($_POST['answers'])) {
            wp_send_json_error('Missing required data');
        }
        
        $post_id = intval($_POST['post_id']);
        $answers = $_POST['answers'];
        
        // ตรวจสอบสิทธิ์
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }
        
        // ดึงข้อมูล response ที่มีอยู่
        $response_data = get_post_meta($post_id, '_tpak_import_data', true);
        if (!is_array($response_data)) {
            $response_data = array();
        }
        
        // อัปเดตคำตอบทั้งหมด
        $updated_count = 0;
        
        foreach ($answers as $question_code => $answer_value) {
            if (!empty($answer_value)) {
                $response_data[$question_code] = sanitize_text_field($answer_value);
                $updated_count++;
            }
        }
        
        // บันทึกลงฐานข้อมูล
        update_post_meta($post_id, '_tpak_import_data', $response_data);
        
        // ตรวจสอบว่ามีการอัปเดตหรือไม่
        if ($updated_count > 0) {
            wp_send_json_success("บันทึกคำตอบ $updated_count รายการเรียบร้อย");
        } else {
            wp_send_json_success("ไม่มีคำตอบที่ต้องอัปเดต");
        }
    }
    
    /**
     * AJAX: Refresh Survey Structure
     */
    public function ajax_refresh_survey_structure() {
        // Check nonce
        if (!check_ajax_referer('tpak_survey_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$survey_id || !$post_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Get survey structure from API
        $structure = $this->fetch_survey_structure($survey_id);
        
        if ($structure) {
            // Save to post meta for caching
            update_post_meta($post_id, '_tpak_survey_structure', $structure);
            
            // Get response data
            $response_data = get_post_meta($post_id, '_tpak_import_data', true);
            
            // Render HTML
            ob_start();
            $this->render_survey_structure($structure, $response_data);
            $html = ob_get_clean();
            
            wp_send_json_success(array(
                'html' => $html,
                'message' => 'อัพเดทโครงสร้างแบบสอบถามเรียบร้อย'
            ));
        } else {
            wp_send_json_error('ไม่สามารถดึงข้อมูลแบบสอบถามได้');
        }
    }
    
    /**
     * ดึงโครงสร้างแบบสอบถามจาก LimeSurvey
     */
    private function fetch_survey_structure($survey_id) {
        // Initialize API client
        $api_url = get_option('tpak_api_url');
        $username = get_option('tpak_api_username');
        $password = get_option('tpak_api_password');
        
        if (!$api_url || !$username || !$password) {
            return false;
        }
        
        try {
            // ใช้ไฟล์ backup ถ้าต้องการ
            if (!class_exists('LimeSurveyAPIClient')) {
                require_once TPAK_DQ_PLUGIN_DIR . 'includes/class-tpak-dq-import.php_backup';
            }
            $this->api_client = new LimeSurveyAPIClient($api_url, $username, $password);
            $session_key = $this->api_client->get_session_key();
            
            if (!$session_key) {
                return false;
            }
            
            // Get survey info
            $survey_info = $this->api_client->get_survey_properties($session_key, $survey_id, array('surveyls_title', 'surveyls_description'));
            
            // Get all questions
            $questions = $this->api_client->list_questions($session_key, $survey_id);
            
            // สร้างโครงสร้างคำถาม
            $structure = array(
                'survey_id' => $survey_id,
                'title' => isset($survey_info['surveyls_title']) ? $survey_info['surveyls_title'] : 'Survey ' . $survey_id,
                'description' => isset($survey_info['surveyls_description']) ? $survey_info['surveyls_description'] : '',
                'questions' => array(),
                'subquestions' => array(),
                'answer_options' => array()
            );
            
            if (is_array($questions)) {
                // จัดกลุ่มคำถามตาม parent
                $main_questions = array();
                $sub_questions = array();
                
                foreach ($questions as $question) {
                    $qid = isset($question['qid']) ? $question['qid'] : null;
                    $parent_qid = isset($question['parent_qid']) ? $question['parent_qid'] : 0;
                    
                    if ($parent_qid > 0) {
                        if (!isset($sub_questions[$parent_qid])) {
                            $sub_questions[$parent_qid] = array();
                        }
                        $sub_questions[$parent_qid][] = $question;
                    } else {
                        $main_questions[$qid] = $question;
                    }
                }
                
                // Process main questions
                foreach ($main_questions as $qid => $question) {
                    // Get question properties
                    $q_props = $this->api_client->get_question_properties($session_key, $qid, 
                        array('title', 'question', 'type', 'mandatory', 'help', 'other'));
                    
                    if ($q_props) {
                        $code = isset($q_props['title']) ? $q_props['title'] : '';
                        
                        // Main question
                        $structure['questions'][$code] = array(
                            'code' => $code,
                            'question' => isset($q_props['question']) ? strip_tags($q_props['question']) : '',
                            'type' => isset($q_props['type']) ? $q_props['type'] : '',
                            'mandatory' => isset($q_props['mandatory']) ? $q_props['mandatory'] : 'N',
                            'help' => isset($q_props['help']) ? strip_tags($q_props['help']) : '',
                            'other' => isset($q_props['other']) ? $q_props['other'] : 'N',
                            'qid' => $qid
                        );
                        
                        // Get answer options for list questions
                        if (in_array($q_props['type'], array('L', '!', 'O', 'F', 'H', 'M', 'P'))) {
                            $answers = $this->get_answer_options($session_key, $qid);
                            if ($answers) {
                                $structure['answer_options'][$code] = $answers;
                            }
                        }
                        
                        // Process subquestions
                        if (isset($sub_questions[$qid])) {
                            $structure['subquestions'][$code] = array();
                            
                            foreach ($sub_questions[$qid] as $sq) {
                                $sq_id = $sq['qid'];
                                $sq_props = $this->api_client->get_question_properties($session_key, $sq_id, 
                                    array('title', 'question'));
                                
                                if ($sq_props) {
                                    $sq_code = isset($sq_props['title']) ? $sq_props['title'] : '';
                                    $structure['subquestions'][$code][$sq_code] = array(
                                        'code' => $sq_code,
                                        'question' => isset($sq_props['question']) ? strip_tags($sq_props['question']) : '',
                                        'qid' => $sq_id
                                    );
                                    
                                    // สำหรับ Array questions อาจมี answer options ด้วย
                                    if (in_array($q_props['type'], array('F', 'H', '1', ':'))) {
                                        $sq_answers = $this->get_answer_options($session_key, $sq_id);
                                        if ($sq_answers) {
                                            if (!isset($structure['answer_options'][$code])) {
                                                $structure['answer_options'][$code] = array();
                                            }
                                            // เก็บ answer options สำหรับ subquestion
                                            foreach ($sq_answers as $ans_code => $ans_text) {
                                                $full_code = $sq_code . '_' . $ans_code;
                                                $structure['answer_options'][$code][$full_code] = $ans_text;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            $this->api_client->release_session_key($session_key);
            
            return $structure;
            
        } catch (Exception $e) {
            error_log('TPAK Survey Structure Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ดึงตัวเลือกคำตอบ
     */
    private function get_answer_options($session_key, $qid) {
        try {
            // Method 1: ลองใช้ list_question_answers
            $params = array(
                'method' => 'list_question_answers',
                'params' => array($session_key, intval($qid)),
                'id' => 1
            );
            
            $response = $this->api_client->send_request($params);
            
            if (isset($response['result']) && is_array($response['result'])) {
                $options = array();
                foreach ($response['result'] as $answer) {
                    if (isset($answer['code']) && isset($answer['answer'])) {
                        $options[$answer['code']] = strip_tags($answer['answer']);
                        // เก็บทั้งตัวเลขและ code
                        if (isset($answer['sortorder'])) {
                            $options[$answer['sortorder']] = strip_tags($answer['answer']);
                        }
                        // เก็บ assessment value ด้วย
                        if (isset($answer['assessment_value'])) {
                            $options[$answer['assessment_value']] = strip_tags($answer['answer']);
                        }
                    }
                }
                
                if (!empty($options)) {
                    return $options;
                }
            }
            
            // Method 2: ถ้าไม่ได้ผล ลองดึงจาก question properties
            $q_props = $this->api_client->get_question_properties($session_key, $qid, array('answeroptions'));
            
            if (isset($q_props['answeroptions']) && is_array($q_props['answeroptions'])) {
                $options = array();
                foreach ($q_props['answeroptions'] as $code => $answer_data) {
                    if (is_array($answer_data) && isset($answer_data['answer'])) {
                        $options[$code] = strip_tags($answer_data['answer']);
                    } elseif (is_string($answer_data)) {
                        $options[$code] = strip_tags($answer_data);
                    }
                }
                return $options;
            }
            
        } catch (Exception $e) {
            error_log('TPAK Get Answer Options Error: ' . $e->getMessage());
        }
        
        return array();
    }
    
    /**
     * แสดงโครงสร้างแบบสอบถาม
     */
    private function render_survey_structure($structure, $response_data) {
        if (!is_array($structure) || !isset($structure['questions'])) {
            $this->render_survey_with_answers($response_data);
            return;
        }
        
        // ตรวจสอบว่ามี saved structure หรือไม่
        $survey_id = isset($structure['survey_id']) ? $structure['survey_id'] : '';
        if ($survey_id && class_exists('TPAK_DQ_Survey_Structure_Manager')) {
            $saved_structure = TPAK_DQ_Survey_Structure_Manager::get_survey_structure($survey_id);
            if ($saved_structure) {
                // ใช้ saved structure แทน
                $structure = array_merge($structure, $saved_structure);
            }
        }
        
        // เก็บ structure ไว้ใช้
        $this->question_labels = isset($structure['questions']) ? $structure['questions'] : array();
        $this->answer_options = isset($structure['answers']) ? $structure['answers'] : array();
        
        ?>
        <div class="tpak-survey-header">
            <h2 class="tpak-survey-title"><?php echo esc_html($structure['title']); ?></h2>
            <?php if (!empty($structure['description'])): ?>
                <div class="tpak-survey-description"><?php echo nl2br(esc_html($structure['description'])); ?></div>
            <?php endif; ?>
            <div class="tpak-survey-stats">
                <div class="tpak-stat-item">
                    <span class="dashicons dashicons-editor-help"></span>
                    <span>จำนวนคำถาม: <span class="tpak-stat-number"><?php echo count($structure['questions']); ?></span></span>
                </div>
                <?php if (isset($structure['last_updated'])): ?>
                <div class="tpak-stat-item">
                    <span class="dashicons dashicons-update"></span>
                    <span>อัพเดท: <span class="tpak-stat-number"><?php echo date_i18n('d/m/Y H:i', strtotime($structure['last_updated'])); ?></span></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($structure['groups']) && !empty($structure['groups'])): ?>
            <?php
            // จัดกลุ่มคำถามตาม group
            $questions_by_group = array();
            foreach ($structure['questions'] as $q_code => $q_data) {
                $gid = isset($q_data['gid']) ? $q_data['gid'] : '0';
                if (!isset($questions_by_group[$gid])) {
                    $questions_by_group[$gid] = array();
                }
                $questions_by_group[$gid][$q_code] = $q_data;
            }
            // เรียก group_questions_by_base แค่ครั้งเดียว
            $question_groups = $this->group_questions_by_base($response_data);
            // แสดงตาม group
            foreach ($structure['groups'] as $gid => $group) {
                if (!isset($questions_by_group[$gid])) continue;
                ?>
                <div class="tpak-question-group">
                    <h3 class="tpak-group-title"><?php echo esc_html($group['group_name']); ?></h3>
                    <?php if (!empty($group['description'])): ?>
                        <p class="tpak-group-description"><?php echo nl2br(esc_html($group['description'])); ?></p>
                    <?php endif; ?>
                    <?php
                    foreach ($questions_by_group[$gid] as $q_code => $q_data) {
                        if (isset($question_groups[$q_code])) {
                            $this->render_question_group($q_code, $question_groups[$q_code], $q_data, $response_data);
                        }
                    }
                    ?>
                </div>
                <?php
            }
            ?>
        <?php else: ?>
            <div class="tpak-question-group">
                <h3 class="tpak-group-title">คำถามและคำตอบ</h3>
                
                <?php
                // จัดกลุ่มคำถามที่มี subquestions
                $processed_questions = array();
                $question_groups = $this->group_questions_by_base($response_data);
                
                // แสดงคำถามแบบจัดกลุ่ม
                foreach ($question_groups as $base_code => $group_data) {
                    // หาข้อมูลคำถามหลัก
                    $question_info = $this->find_question_info($base_code, $structure['questions']);
                    
                    // เพิ่ม subquestions ถ้ามี
                    if (isset($structure['subquestions'][$base_code])) {
                        $question_info['subquestions'] = $structure['subquestions'][$base_code];
                    }
                    
                    // เพิ่ม answer options ถ้ามี
                    if (isset($structure['answer_options'][$base_code])) {
                        $question_info['answer_options'] = $structure['answer_options'][$base_code];
                    } elseif (isset($structure['answers'][$base_code])) {
                        // Support both formats
                        $question_info['answer_options'] = array();
                        foreach ($structure['answers'][$base_code] as $code => $answer_data) {
                            if (is_array($answer_data)) {
                                $question_info['answer_options'][$code] = $answer_data['answer'];
                            } else {
                                $question_info['answer_options'][$code] = $answer_data;
                            }
                        }
                    }
                    
                    // แสดงคำถามและคำตอบทั้งกลุ่ม
                    $this->render_question_group($base_code, $group_data, $question_info, $response_data);
                }
                ?>
            </div>
        <?php endif; ?>
        <?php
    }
    
    /**
     * จัดกลุ่มคำถามตาม base code
     */
    private function group_questions_by_base($response_data) {
        
        $groups = array();
        $processed = array();
        
        foreach ($response_data as $key => $value) {
            // ข้ามฟิลด์ระบบ
            if (in_array($key, array('id', 'tid', 'token', 'submitdate', 'lastpage', 'startlanguage', 
                                    'completed', 'startdate', 'datestamp', 'ipaddr', 'refurl', 'seed'))) {
                continue;
            }
            
            // ข้ามถ้าประมวลผลแล้ว
            if (isset($processed[$key])) {
                continue;
            }
            
            // หา base code
            $base_code = $this->extract_base_code($key);
            
            // จัดกลุ่มคำถาม
            if (!isset($groups[$base_code])) {
                $groups[$base_code] = array(
                    'type' => 'single',
                    'items' => array()
                );
            }
            
            // เพิ่มคำถามเข้ากลุ่ม
            if (!empty($value) && $value !== 'N') {
                $groups[$base_code]['items'][$key] = $value;
                $processed[$key] = true;
            }
            
            // หาคำถามที่เกี่ยวข้อง
            foreach ($response_data as $k => $v) {
                if ($k !== $key && !isset($processed[$k])) {
                    // ใช้ regex pattern เดียวกับ extract_base_code เพื่อความสอดคล้อง
                    $k_base_code = $this->extract_base_code($k);
                    if ($k_base_code === $base_code) {
                        if (!empty($v) && $v !== 'N') {
                            $groups[$base_code]['items'][$k] = $v;
                            $groups[$base_code]['type'] = 'array';
                            $processed[$k] = true;
                        }
                    }
                }
            }
        }
        
        // กรองเฉพาะกลุ่มที่มีข้อมูล
        $filtered_groups = array();
        foreach ($groups as $base_code => $group) {
            if (!empty($group['items'])) {
                $filtered_groups[$base_code] = $group;
                // Debug code removed for performance
            }
        }
        return $filtered_groups;
    }
    
    /**
     * แยก base code จาก key
     */
    private function extract_base_code($key) {
        // สำหรับ C2t1, C2t2t2, C2t2t2t1, C2t4 ให้ใช้ key ทั้งหมดเป็น base_code
        if (preg_match('/^C2t\d+.*$/', $key)) {
            return $key;
        }
        
        // ตรวจจับ pattern ต่างๆ - แก้ไขให้ถูกต้อง
        // ตรวจสอบว่าเป็นคำถามที่มีตัวเลขต่อท้ายหรือไม่ (เช่น Q10, Q11, Q12) ก่อน
        if (preg_match('/^([A-Za-z]+)(\d+)(?:\[|_SQ|.*|$)/', $key, $matches)) {
            $base_code = $matches[1] . $matches[2]; // รวมตัวอักษรและตัวเลข
            return $base_code;
        }
        
        // ตรวจสอบ pattern ทั่วไป
        if (preg_match('/^([A-Za-z0-9]+?)(?:\[|_SQ|$)/', $key, $matches)) {
            $base_code = $matches[1];
            return $base_code;
        }
        
        // ถ้าไม่มี pattern พิเศษ ให้ใช้ key ทั้งหมด
        return $key;
    }
    
    /**
     * แสดงกลุ่มคำถาม
     */
    private function render_question_group($base_code, $group_data, $question_info, $response_data) {
        $items = $group_data['items'];
        $type = $group_data['type'];
        // รองรับ complex matrix/array (type ;, F, H, 1, :)
        if ($question_info && isset($question_info['type']) && in_array($question_info['type'], array(';', 'F', 'H', '1', ':'))) {
            $this->render_complex_matrix_question($base_code, $question_info, $response_data);
            return;
        }
        
        // ตรวจสอบว่าเป็น Complex Array หรือไม่
        if (class_exists('TPAK_DQ_Complex_Array_Handler') && 
            TPAK_DQ_Complex_Array_Handler::is_complex_array($base_code, $response_data)) {
            
            $complex_data = TPAK_DQ_Complex_Array_Handler::handle_complex_array($base_code, $response_data, $question_info);
            
            if ($complex_data) {
                ?>
                <div class="tpak-question-item">
                    <div class="tpak-question-code">
                        <?php echo esc_html($base_code); ?>
                        <span class="tpak-question-type">Complex Array</span>
                    </div>
                    
                    <div class="tpak-question-text">
                        <?php if ($question_info && isset($question_info['mandatory']) && $question_info['mandatory'] == 'Y'): ?>
                            <span class="tpak-question-mandatory">*</span>
                        <?php endif; ?>
                        
                        <?php 
                        if ($question_info && !empty($question_info['question'])) {
                            echo nl2br(esc_html(strip_tags($question_info['question'])));
                        } else {
                            echo 'คำถาม ' . esc_html($base_code);
                        }
                        ?>
                    </div>
                    
                    <div class="tpak-answer-wrapper">
                        <?php TPAK_DQ_Complex_Array_Handler::render_complex_array($complex_data); ?>
                    </div>
                </div>
                <?php
                return;
            }
        }
        
        // ตรวจสอบ Special Questions อื่นๆ
        if (class_exists('TPAK_DQ_Question_Handlers')) {
            $handled = TPAK_DQ_Question_Handlers::handle_special_question($base_code, $response_data, $question_info);
            
            if ($handled) {
                ?>
                <div class="tpak-question-item">
                    <div class="tpak-question-code">
                        <?php echo esc_html($base_code); ?>
                        <?php if ($question_info && isset($question_info['type'])): ?>
                            <span class="tpak-question-type"><?php echo $this->get_question_type_label($question_info['type']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tpak-question-text">
                        <?php if ($question_info && isset($question_info['mandatory']) && $question_info['mandatory'] == 'Y'): ?>
                            <span class="tpak-question-mandatory">*</span>
                        <?php endif; ?>
                        
                        <?php 
                        if ($question_info && !empty($question_info['question'])) {
                            echo nl2br(esc_html(strip_tags($question_info['question'])));
                        } else {
                            echo 'คำถาม ' . esc_html($base_code);
                        }
                        ?>
                    </div>
                    
                    <div class="tpak-answer-wrapper">
                        <?php TPAK_DQ_Question_Handlers::render_special_question($handled); ?>
                    </div>
                </div>
                <?php
                return;
            }
        }
        
        // Render แบบปกติ
        ?>
        <div class="tpak-question-item">
            <!-- ซ่อนการแสดงรหัสคำถาม -->
            <?php if ($question_info && isset($question_info['type'])): ?>
                <div class="tpak-question-code" style="display: none;">
                    <span class="tpak-question-type"><?php echo $this->get_question_type_label($question_info['type']); ?></span>
                </div>
            <?php elseif ($type === 'array'): ?>
                <div class="tpak-question-code" style="display: none;">
                    <span class="tpak-question-type">Array</span>
                </div>
            <?php endif; ?>
            
            <div class="tpak-question-text">
                <?php if ($question_info && isset($question_info['mandatory']) && $question_info['mandatory'] == 'Y'): ?>
                    <span class="tpak-question-mandatory">*</span>
                <?php endif; ?>
                
                <?php 
                if ($question_info && !empty($question_info['question'])) {
                    // แสดงคำถามจริง
                    echo nl2br(esc_html(strip_tags($question_info['question'])));
                } else {
                    // ถ้าไม่มีข้อมูลคำถาม แสดง label ที่อ่านได้
                    echo $this->get_readable_question_label($base_code);
                }
                ?>
            </div>
            
            <?php if ($question_info && !empty($question_info['help'])): ?>
                <div class="tpak-question-help" style="font-size: 13px; color: #7f8c8d; margin-top: 5px;">
                    <?php echo nl2br(esc_html($question_info['help'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="tpak-answer-wrapper">
                <?php 
                if ($type === 'array' || count($items) > 1) {
                    // แสดงแต่ละคำถามแยกกัน
                    foreach ($items as $item_key => $item_value) {
                        ?>
                        <div class="tpak-sub-question">
                            <div class="tpak-sub-question-label">
                                <?php echo $this->get_readable_question_label($item_key); ?>
                            </div>
                            <div class="tpak-sub-question-answer">
                                <?php $this->render_single_answer($item_value, $item_key, $question_info); ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    $this->render_single_answer(reset($items), $base_code, $question_info);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * ดึง label ของ item สำหรับ array questions
     */
    private function get_item_label($item_code, $question_info) {
        if ($question_info && isset($question_info['subquestions'])) {
            if (isset($question_info['subquestions'][$item_code])) {
                return $question_info['subquestions'][$item_code]['question'];
            }
        }
        return $item_code;
    }
    
    /**
     * แสดงคำตอบแบบ array ที่จัดกลุ่มแล้ว
     */
    private function render_grouped_array_answers($base_code, $items, $question_info = null) {
        // ตรวจสอบว่าเป็นคำถามแบบ Multiple Choice Array หรือไม่
        $is_multiple_choice = false;
        $all_numeric_values = true;
        
        foreach ($items as $value) {
            if (!is_numeric($value) || $value > 100) {
                $all_numeric_values = false;
                break;
            }
        }
        
        // ถ้าค่าเป็นตัวเลขเล็กๆ (0-20) น่าจะเป็น choice code
        if ($all_numeric_values) {
            $max_value = max(array_values($items));
            if ($max_value <= 20) {
                $is_multiple_choice = true;
            }
        }
        
        if ($is_multiple_choice && $question_info && isset($question_info['answer_options'])) {
            // แสดงเป็น Radio Buttons สำหรับ Multiple Choice Array
            ?>
            <div class="tpak-array-radio-group">
                <?php foreach ($items as $item_code => $selected_value): ?>
                    <div class="tpak-array-item">
                        <div class="tpak-item-label"><?php echo esc_html($this->get_item_label($item_code, $question_info)); ?></div>
                        <div class="tpak-radio-options">
                            <?php foreach ($question_info['answer_options'] as $opt_val => $opt_label): ?>
                                <div class="tpak-radio-option">
                                    <input type="radio" 
                                           id="<?php echo esc_attr($base_code . '_' . $item_code . '_' . $opt_val); ?>" 
                                           name="<?php echo esc_attr($base_code . '_' . $item_code); ?>" 
                                           value="<?php echo esc_attr($opt_val); ?>"
                                           <?php checked($selected_value, $opt_val); ?>
                                           class="tpak-radio-input">
                                    <label for="<?php echo esc_attr($base_code . '_' . $item_code . '_' . $opt_val); ?>" class="tpak-radio-label">
                                        <?php echo esc_html($opt_label); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
                    <?php 
                                } else {
            // แสดงเป็น Text Inputs สำหรับ Array ธรรมดา
            ?>
            <div class="tpak-array-text-group">
                <?php foreach ($items as $item_code => $value): ?>
                    <div class="tpak-array-item">
                        <div class="tpak-item-label"><?php echo esc_html($this->get_item_label($item_code, $question_info)); ?></div>
                        <div class="tpak-text-input-wrapper">
                                <?php 
                            $formatted_value = $this->format_answer_value($value, $base_code . '_' . $item_code, $question_info);
                            ?>
                            <input type="text" 
                                   name="<?php echo esc_attr($base_code . '_' . $item_code); ?>" 
                                   value="<?php echo esc_attr($formatted_value); ?>" 
                                   class="tpak-text-input"
                                   placeholder="กรอกคำตอบ">
                        </div>
                    </div>
                    <?php endforeach; ?>
        </div>
        <?php
        }
    }
    
    /**
     * หาข้อมูลคำถาม
     */
    private function find_question_info($key, $questions) {
        // ตรงกันโดยตรง
        if (isset($questions[$key])) {
            return $questions[$key];
        }
        
        // ลองหา base question (เช่น Q1 จาก Q1[11])
        if (preg_match('/^([A-Za-z0-9]+)[\[\]_]/', $key, $matches)) {
            $base_code = $matches[1];
            if (isset($questions[$base_code])) {
                return $questions[$base_code];
            }
        }
        
        return null;
    }
    
    /**
     * แสดงคำถามพร้อมคำตอบ
     */
    private function render_question_with_answer($key, $value, $question_info, $response_data) {
        ?>
        <div class="tpak-question-item">
            <!-- ซ่อนการแสดงรหัสคำถาม -->
            <?php if ($question_info && isset($question_info['type'])): ?>
                <div class="tpak-question-code" style="display: none;">
                    <span class="tpak-question-type"><?php echo $this->get_question_type_label($question_info['type']); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="tpak-question-text">
                <?php if ($question_info && isset($question_info['mandatory']) && $question_info['mandatory'] == 'Y'): ?>
                    <span class="tpak-question-mandatory">*</span>
                <?php endif; ?>
                
                <?php 
                // ใช้ mapping ที่เราสร้างไว้
                $readable_label = $this->get_readable_question_label($key);
                if ($readable_label && $readable_label !== 'คำถาม ' . $key) {
                    echo nl2br(esc_html($readable_label));
                } elseif ($question_info && !empty($question_info['question'])) {
                    // แสดงคำถามจริง
                    echo nl2br(esc_html(strip_tags($question_info['question'])));
                } else {
                    // ถ้าไม่มีข้อมูลคำถาม แสดง key
                    echo 'คำถาม ' . esc_html($key);
                }
                ?>
            </div>
            
            <?php if ($question_info && !empty($question_info['help'])): ?>
                <div class="tpak-question-help" style="font-size: 13px; color: #7f8c8d; margin-top: 5px;">
                    <?php echo nl2br(esc_html($question_info['help'])); ?>
                </div>
            <?php endif; ?>
            
            <div class="tpak-answer-wrapper">
                <?php 
                // ตรวจสอบว่าเป็นคำถาม array หรือไม่
                $is_array = $this->is_array_question($key, $response_data);
                
                if ($is_array) {
                    $this->render_array_answer_for_question($key, $response_data);
                } else {
                    $this->render_single_answer($value, $key, $question_info);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * ตรวจสอบว่าเป็นคำถาม array หรือไม่
     */
    private function is_array_question($key, $response_data) {
        $base_code = preg_replace('/[\[\]_].+$/', '', $key);
        $count = 0;
        
        foreach ($response_data as $k => $v) {
            // ตรวจสอบว่าเป็น sub-question ของ base_code จริงๆ
            if (preg_match('/^' . preg_quote($base_code, '/') . '(\[.*?\]|_.*?|$)/', $k) && !empty($v) && $v !== 'N') {
                $count++;
            }
        }
        
        return $count > 1;
    }
    
    /**
     * แสดงคำตอบแบบ array สำหรับคำถาม
     */
    private function render_array_answer_for_question($key, $response_data) {
        $base_code = preg_replace('/[\[\]_].+$/', '', $key);
        $answers = array();
        
        // รวบรวมคำตอบที่เกี่ยวข้อง (เฉพาะที่ตรงกับ base_code จริงๆ)
        foreach ($response_data as $k => $v) {
            // ตรวจสอบว่าเป็น sub-question ของ base_code จริงๆ
            if (preg_match('/^' . preg_quote($base_code, '/') . '(\[.*?\]|_.*?|$)/', $k) && !empty($v) && $v !== 'N') {
                $sub_key = str_replace($base_code, '', $k);
                $sub_key = trim($sub_key, '[]_');
                $answers[$sub_key] = $v;
            }
        }
        
        if (empty($answers)) {
            $this->render_single_answer('', $key);
            return;
        }
        
        ?>
        <div class="tpak-array-answers">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>รายการ</th>
                        <th>คำตอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($answers as $sub_key => $value): ?>
                    <tr>
                        <td><?php echo esc_html($sub_key); ?></td>
                        <td><strong><?php echo esc_html($this->format_answer_value($value, $key)); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * แสดงคำถามและคำตอบจากข้อมูลที่มี (fallback)
     */
    private function render_survey_with_answers($response_data) {
        if (!is_array($response_data) || empty($response_data)) {
            echo '<p>ไม่พบข้อมูลแบบสอบถาม</p>';
            return;
        }
        
        // จัดกลุ่มข้อมูล
        $grouped_data = $this->group_response_data($response_data);
        
        // แสดงข้อมูลพื้นฐาน
        $this->render_basic_info($grouped_data['basic']);
        
        // แสดงคำถามและคำตอบ
        $this->render_questions_and_answers($grouped_data['questions'], $response_data);
    }
    
    /**
     * จัดกลุ่มข้อมูลตอบกลับ
     */
    private function group_response_data($response_data) {
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
            'ipaddr' => 'IP Address',
            'refurl' => 'Referrer URL',
            'seed' => 'Seed'
        );
        
        $basic = array();
        $question_groups = $this->group_questions_by_base($response_data);
        
        // แยกข้อมูลพื้นฐาน
        foreach ($response_data as $key => $value) {
            if (in_array($key, array('tpak_survey_nonce', '_wp_http_referer', 'post_id'))) {
                continue;
            }
            if (array_key_exists($key, $basic_fields)) {
                $basic[$key] = array(
                    'label' => $basic_fields[$key],
                    'value' => $value
                );
            }
        }
        
        return array(
            'basic' => $basic,
            'questions' => $question_groups
        );
    }
    
    /**
     * แสดงข้อมูลพื้นฐาน
     */
    private function render_basic_info($basic_info) {
        if (empty($basic_info)) {
            return;
        }
        
        ?>
        <div class="tpak-survey-header">
            <h2 class="tpak-survey-title">ข้อมูลแบบสอบถาม</h2>
            
            <table class="tpak-info-table">
                <?php foreach ($basic_info as $field): ?>
                    <?php if (!empty($field['value']) && $field['value'] !== 'N'): ?>
                    <tr>
                        <td style="width: 30%; padding: 5px; font-weight: bold;">
                            <?php echo esc_html($field['label']); ?>:
                        </td>
                        <td style="padding: 5px;">
                            <?php 
                            $value = $field['value'];
                            if (in_array($field['label'], array('วันที่ส่ง', 'วันที่เริ่ม', 'วันที่บันทึก')) && $value != 'N') {
                                echo date_i18n('d/m/Y H:i:s', strtotime($value));
                            } elseif ($field['label'] == 'สถานะ') {
                                echo ($value == 'Y' || $value == '1') ? 
                                     '<span style="color:green;">✓ สมบูรณ์</span>' : 
                                     '<span style="color:red;">✗ ไม่สมบูรณ์</span>';
                            } else {
                                echo esc_html($value);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }
    
    /**
     * แสดงคำถามและคำตอบ
     */
    private function render_questions_and_answers($questions, $response_data) {
        
        if (empty($questions)) {
            return;
        }
        
        ?>
        <div class="tpak-question-group">
            <h3 class="tpak-group-title">คำถามและคำตอบ</h3>
            
            <?php foreach ($questions as $base_code => $group_data): ?>
                <?php 
                // สร้าง question_info เปล่าเพื่อให้ mapping ทำงาน
                $question_info = array();
                $this->render_question_group($base_code, $group_data, $question_info, $response_data); 
                ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * แสดงคำถามแบบจัดกลุ่ม
     */
    private function render_grouped_question($question_data, $response_data) {
        $code = $question_data['code'];
        $type = $question_data['type'];
        $answers = $question_data['answers'];
        
        // กรองเฉพาะที่มีค่า
        $has_answer = false;
        foreach ($answers as $value) {
            if (!empty($value) && $value !== 'N') {
                $has_answer = true;
                break;
            }
        }
        
        if (!$has_answer) {
            return;
        }
        
        ?>
        <div class="tpak-question-item">
            <div class="tpak-question-code">
                <?php echo esc_html($code); ?>
                <span class="tpak-question-type"><?php echo esc_html($type); ?></span>
            </div>
            
            <div class="tpak-question-text">
                คำถาม <?php echo esc_html($code); ?>
            </div>
            
            <div class="tpak-answer-wrapper">
                <?php 
                if ($type === 'Array' || count($answers) > 1) {
                    $this->render_array_answers($answers);
                } else {
                    $this->render_single_answer(reset($answers), $code);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * แสดงคำตอบเดี่ยว
     */
    private function render_single_answer($value, $question_code = '', $question_info = null) {
        $key = $question_code;
        
        // Debug log
        $this->write_debug_log("TPAK Debug - render_single_answer: code=$question_code, value=$value");
        $this->write_debug_log("TPAK Debug - question_info: " . print_r($question_info, true));
        
        if (empty($value) || $value === 'N') {
            echo '<div class="tpak-answer-empty">ไม่มีคำตอบ</div>';
            return;
        }
        
        // จัดรูปแบบค่าคำตอบ
        $formatted_value = $this->format_answer_value($value, $question_code, $question_info);
        
        // สร้าง answer options แบบ hardcode สำหรับคำถามที่รู้จัก
        $hardcoded_options = $this->get_hardcoded_answer_options($question_code);
        
        // Debug log
        $this->write_debug_log("TPAK Debug - hardcoded_options: " . print_r($hardcoded_options, true));
        
        ?>
        <div class="tpak-answer-display">
            <?php 
            $has_api_options = $question_info && isset($question_info['answer_options']) && is_array($question_info['answer_options']);
            $has_hardcoded_options = !empty($hardcoded_options);
            
            // Debug log
            $this->write_debug_log("TPAK Debug - has_api_options: " . ($has_api_options ? 'true' : 'false'));
            $this->write_debug_log("TPAK Debug - has_hardcoded_options: " . ($has_hardcoded_options ? 'true' : 'false'));
            
            if ($has_api_options || $has_hardcoded_options): 
            ?>
                <!-- แสดงเป็น Radio Buttons สำหรับคำถามที่มีตัวเลือก -->
                <div class="tpak-radio-options">
                    <?php 
                    $options = !empty($hardcoded_options) ? $hardcoded_options : $question_info['answer_options'];
                    foreach ($options as $opt_val => $opt_label): 
                    ?>
                        <div class="tpak-radio-option">
                            <input type="radio" 
                                   id="<?php echo esc_attr($key . '_' . $opt_val); ?>" 
                                   name="<?php echo esc_attr($key); ?>" 
                                   value="<?php echo esc_attr($opt_val); ?>"
                                   <?php checked($value, $opt_val); ?>
                                   class="tpak-radio-input tpak-answer-input"
                                   data-question="<?php echo esc_attr($key); ?>">
                            <label for="<?php echo esc_attr($key . '_' . $opt_val); ?>" class="tpak-radio-label">
                                <?php echo esc_html($opt_label); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- แสดงเป็น Text Input สำหรับคำถามแบบข้อความ -->
                <div class="tpak-text-input-wrapper">
                    <input type="text" 
                           name="<?php echo esc_attr($key); ?>" 
                           value="<?php echo esc_attr($formatted_value); ?>" 
                           class="tpak-text-input tpak-answer-input"
                           data-question="<?php echo esc_attr($key); ?>"
                           placeholder="กรอกคำตอบ">
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * สร้าง answer options แบบ hardcode สำหรับคำถามที่รู้จัก
     */
    private function get_hardcoded_answer_options($question_code) {
        $options = array();
        
        // Debug log
        $this->write_debug_log("TPAK Debug - get_hardcoded_answer_options: code=$question_code");
        
        // Q1 - เพศ (List radio)
        if ($question_code === 'Q1') {
            $this->write_debug_log("TPAK Debug - Found Q1 match (List radio)");
            $options = array(
                '1' => '1. ชาย',
                '2' => '2. หญิง',
                '3' => '3. ผู้มีความหลากหลายทางเพศ'
            );
        }
        // Q1s1 - เพศสภาวะ
        elseif ($question_code === 'Q1s1') {
            $this->write_debug_log("TPAK Debug - Found Q1s1 match");
            $options = array(
                '1' => '1. ชาย',
                '2' => '2. หญิง',
                '3' => '3. ผู้มีความหลากหลายทางเพศ'
            );
        }
        // Q10 - สุขภาพ
        elseif ($question_code === 'Q10') {
            $this->write_debug_log("TPAK Debug - Found Q10 match");
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // Q10s1[D5_SQ001] - ข้อมูลสุขภาพ - เพศ
        elseif ($question_code === 'Q10s1[D5_SQ001]') {
            $options = array(
                '1' => '1. ชาย',
                '2' => '2. หญิง'
            );
        }
        // Q11 - การออกกำลังกาย
        elseif ($question_code === 'Q11') {
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // Q12s1[2] - การบริโภคอาหาร - อาหารหลัก (รายการที่ 2)
        elseif ($question_code === 'Q12s1[2]') {
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // Q12s1[5] - การบริโภคอาหาร - อาหารหลัก (รายการที่ 5)
        elseif ($question_code === 'Q12s1[5]') {
            $this->write_debug_log("TPAK Debug - Found Q12s1[5] match");
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // Q12s1[16] - การบริโภคอาหาร - อาหารหลัก (รายการที่ 16)
        elseif ($question_code === 'Q12s1[16]') {
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // Q12s2[1] - การบริโภคอาหาร - อาหารเสริม (รายการที่ 1)
        elseif ($question_code === 'Q12s2[1]') {
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // Q16[2], Q16[3], Q16[4] - ความต้องการ
        elseif (in_array($question_code, array('Q16[2]', 'Q16[3]', 'Q16[4]'))) {
            $options = array(
                '1' => '1. ใช่',
                '2' => '2. ไม่ใช่'
            );
        }
        // CC2 - การเปลี่ยนแปลงภูมิอากาศ
        elseif ($question_code === 'CC2') {
            $this->write_debug_log("TPAK Debug - Found CC2 match");
            $options = array(
                '1' => '1. อากาศร้อน (อุณหภูมิสูง)',
                '2' => '2. มีแสงแดดที่แรงจัด',
                '3' => '3. ความชื้นสูง',
                '4' => '4. คุณภาพอากาศไม่ดี หรือฝุ่นละอองเกสรดอกไม้',
                '5' => '5. พายุฤดูร้อน ฝนฟ้าคะนอง/ฟ้าผ่า',
                '6' => '6. อากาศเย็น หนาว หมอกจัด',
                '7' => '7. น้ำท่วม',
                '8' => '8. แห้งแล้ง',
                '9' => '9. ภาวะโลกร้อน/ปรากฏการณ์เรือนกระจก',
                '10' => '10. ไม่แน่ใจ/ไม่ทราบ',
                '11' => '11. อื่น ๆ โปรดระบุ'
            );
        }
        // C2 - ข้อมูลที่อยู่
        elseif ($question_code === 'C2') {
            $this->write_debug_log("TPAK Debug - Found C2 match");
            $options = array(
                '1' => '1. ข้อมูลที่อยู่',
                '2' => '2. ข้อมูลที่อยู่'
            );
        }
        // C2t1 - รหัสจังหวัด
        elseif ($question_code === 'C2t1') {
            $this->write_debug_log("TPAK Debug - Found C2t1 match");
            $options = array(
                '1' => '1. กรุงเทพมหานคร',
                '2' => '2. ลำปาง',
                '3' => '3. น่าน',
                '4' => '4. นครราชสีมา',
                '5' => '5. นครพนม',
                '6' => '6. หนองบัวลำภู',
                '7' => '7. ปทุมธานี',
                '8' => '8. ระยอง',
                '9' => '9. เพชรบุรี',
                '10' => '10. ชุมพร',
                '11' => '11. ตรัง',
                '12' => '12. เชียงราย',
                '13' => '13. นครศรีธรรมราช'
            );
        }
        // C2t2 - รหัสอำเภอ
        elseif ($question_code === 'C2t2') {
            $this->write_debug_log("TPAK Debug - Found C2t2 match");
            $options = array(
                '1' => '1. อำเภอเมือง',
                '2' => '2. อำเภอแม่พริก',
                '3' => '3. อำเภอทุ่งช้าง',
                '4' => '4. อำเภอปากช่อง',
                '5' => '5. อำเภอเมือง',
                '6' => '6. อำเภอสุวรรณคูหา',
                '7' => '7. อำเภอเมือง',
                '8' => '8. อำเภอวังจันทร์',
                '9' => '9. อำเภอชะอำ',
                '10' => '10. อำเภอปะทิว',
                '11' => '11. อำเภอย่านตาขาว',
                '12' => '12. อำเภอเมืองเชียงราย',
                '13' => '13. อำเภอเมืองนครศรีธรรมราช'
            );
        }
        // C2t3 - รหัสตำบล
        elseif ($question_code === 'C2t3') {
            $this->write_debug_log("TPAK Debug - Found C2t3 match");
            $options = array(
                '1' => '1. ป้อมปราบศัตรูพ่าย',
                '2' => '2. ตลิ่งชัน',
                '3' => '3. บางรัก',
                '4' => '4. ตำบลแม่พริก',
                '5' => '5. ตำบลทุ่งช้าง',
                '6' => '6. ตำบลปากช่อง',
                '7' => '7. ตำบลเมือง',
                '8' => '8. ตำบลสุวรรณคูหา',
                '9' => '9. ตำบลเมือง',
                '10' => '10. ตำบลวังจันทร์',
                '11' => '11. ตำบลชะอำ',
                '12' => '12. ตำบลปะทิว',
                '13' => '13. ตำบลย่านตาขาว',
                '14' => '14. ตำบลเมืองเชียงราย',
                '15' => '15. ตำบลเมืองนครศรีธรรมราช'
            );
        }
        // C2t4 - หมู่ที่
        elseif ($question_code === 'C2t4') {
            $this->write_debug_log("TPAK Debug - Found C2t4 match");
            $options = array(
                '1' => '1. หมู่ที่ 1',
                '2' => '2. หมู่ที่ 2',
                '3' => '3. หมู่ที่ 3',
                '4' => '4. หมู่ที่ 4',
                '5' => '5. หมู่ที่ 5',
                '6' => '6. หมู่ที่ 6',
                '7' => '7. หมู่ที่ 7',
                '8' => '8. หมู่ที่ 8',
                '9' => '9. หมู่ที่ 9',
                '10' => '10. หมู่ที่ 10'
            );
        }
        // C2t2t2 - รหัสอำเภอ ของจังหวัดลำปาง
        elseif ($question_code === 'C2t2t2') {
            $this->write_debug_log("TPAK Debug - Found C2t2t2 match");
            $options = array(
                '1' => '1. อำเภอเมืองลำปาง',
                '2' => '2. อำเภอแม่พริก',
                '3' => '3. อำเภอเกาะคา',
                '4' => '4. อำเภอแม่ทะ',
                '5' => '5. อำเภอเถิน',
                '6' => '6. อำเภอสบปราบ',
                '7' => '7. อำเภอเสริมงาม',
                '8' => '8. อำเภอแจ้ห่ม',
                '9' => '9. อำเภอวังเหนือ',
                '10' => '10. อำเภอเมืองปาน',
                '11' => '11. อำเภอแม่เมาะ',
                '12' => '12. อำเภอสบปราบ',
                '13' => '13. อำเภอเมืองปาน'
            );
        }
        // C2t2t2t1 - รหัสตำบล ของอำเภอแม่พริก จังหวัดลำปาง
        elseif ($question_code === 'C2t2t2t1') {
            $this->write_debug_log("TPAK Debug - Found C2t2t2t1 match");
            $options = array(
                '1' => '1. ตำบลแม่พริก',
                '2' => '2. ตำบลแม่ปุ',
                '3' => '3. ตำบลแม่พริกเหนือ',
                '4' => '4. ตำบลแม่พริกใต้',
                '5' => '5. ตำบลแม่พริกกลาง',
                '6' => '6. ตำบลแม่พริกตะวันออก',
                '7' => '7. ตำบลแม่พริกตะวันตก',
                '8' => '8. ตำบลแม่พริกเหนือ',
                '9' => '9. ตำบลแม่พริกใต้',
                '10' => '10. ตำบลแม่พริกกลาง'
            );
        }
        // CC2s1 - ผลกระทบต่อกิจกรรมทางกายกลางแจ้ง
        elseif ($question_code === 'CC2s1') {
            $this->write_debug_log("TPAK Debug - Found CC2s1 match");
            $options = array(
                '1' => '1. มี',
                '2' => '2. ไม่มี',
                '9' => '9. ไม่ทราบ/ไม่แน่ใจ'
            );
        }
        
        $this->write_debug_log("TPAK Debug - Final options: " . print_r($options, true));
        return $options;
    }
    
    /**
     * แสดงคำตอบแบบ Array
     */
    private function render_array_answers($answers) {
        ?>
        <div class="tpak-array-answers">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>รายการ</th>
                        <th>คำตอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($answers as $key => $value): ?>
                        <?php if (!empty($value) && $value !== 'N'): ?>
                        <tr>
                            <td><?php echo esc_html($this->clean_answer_key($key)); ?></td>
                            <td><strong><?php echo esc_html($this->format_answer_value($value)); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * ทำความสะอาด key ของคำตอบ
     */
    private function clean_answer_key($key) {
        // ลบ prefix ที่ซ้ำ
        $key = preg_replace('/^[A-Za-z0-9]+[\[_]/', '', $key);
        $key = str_replace(array('[', ']'), '', $key);
        return $key;
    }
    
    /**
     * จัดรูปแบบค่าคำตอบ
     */
    private function format_answer_value($value, $question_code = '', $question_info = null) {
        // ถ้าค่าว่างหรือ N ให้ return ว่าง
        if (empty($value) || $value === 'N') {
            return '';
        }
        
        // Debug log
        error_log("TPAK Debug - format_answer_value: code=$question_code, value=$value");
        
        // ลองหาจาก answer options ก่อน
        if ($question_info && isset($question_info['answer_options'])) {
            // ตรวจสอบ answer options โดยตรง
            if (isset($question_info['answer_options'][$value])) {
                error_log("TPAK Debug - Found direct answer option: " . $question_info['answer_options'][$value]);
                return $question_info['answer_options'][$value];
            }
            
            // ลองหาแบบ case insensitive
            foreach ($question_info['answer_options'] as $code => $text) {
                if (strcasecmp($code, $value) == 0) {
                    error_log("TPAK Debug - Found case-insensitive answer: $text");
                    return $text;
                }
            }
        }
        
        // ลองหาจาก property answer_options
        if (!empty($question_code) && isset($this->answer_options[$question_code])) {
            if (isset($this->answer_options[$question_code][$value])) {
                return $this->answer_options[$question_code][$value];
            }
            
            // ลองหาแบบ case insensitive
            foreach ($this->answer_options[$question_code] as $code => $text) {
                if (strcasecmp($code, $value) == 0) {
                    return $text;
                }
            }
        }
        
        // สำหรับคำถามที่มี subquestion code (เช่น Q2[1])
        if (preg_match('/^([A-Za-z0-9]+)\[([^\]]+)\]/', $question_code, $matches)) {
            $base_code = $matches[1];
            $sub_code = $matches[2];
            
            // ลองหาใน base question's answer options
            if (isset($this->answer_options[$base_code])) {
                if (isset($this->answer_options[$base_code][$value])) {
                    return $this->answer_options[$base_code][$value];
                }
                
                // ลองหาแบบรวม subcode
                $combined_key = $sub_code . '_' . $value;
                if (isset($this->answer_options[$base_code][$combined_key])) {
                    return $this->answer_options[$base_code][$combined_key];
                }
            }
        }
        
        // Map ค่าพื้นฐาน
        $value_map = array(
            'Y' => 'ใช่',
            'N' => 'ไม่ใช่',
            'U' => 'ไม่แน่ใจ',
            'I' => 'เพิ่มขึ้น',
            'S' => 'เท่าเดิม', 
            'D' => 'ลดลง',
            'A1' => 'เห็นด้วยอย่างยิ่ง',
            'A2' => 'เห็นด้วย',
            'A3' => 'ไม่แน่ใจ',
            'A4' => 'ไม่เห็นด้วย',
            'A5' => 'ไม่เห็นด้วยอย่างยิ่ง',
            '1' => 'เลือก',
            '0' => 'ไม่เลือก',
            '-oth-' => 'อื่นๆ'
        );
        
        // Map คำตอบสำหรับหน้าปกแบบสัมภาษณ์
        $cover_page_answers = array(
            // ประเภทผู้ให้สัมภาษณ์ (S3)
            'S3_1' => 'ตัวจริง',
            'S3_2' => 'ตัวสำรอง',
            
            // เขตที่อยู่อาศัย (C6t1)
            'C6t1_1' => 'เมือง',
            'C6t1_2' => 'ชนบท',
            
            // ประวัติการให้ข้อมูล (His) - รอบต่างๆ
            'His_5' => 'รอบที่ 5 (พ.ศ. 2559)',
            'His_6' => 'รอบที่ 6 (พ.ศ. 2560)',
            'His_7' => 'รอบที่ 7 (พ.ศ. 2561)',
            'His_8' => 'รอบที่ 8 (พ.ศ. 2562)',
            'His_11' => 'รอบที่ 11 (พ.ศ. 2565)',
            'His_12' => 'รอบที่ 12 (พ.ศ. 2566)',
            'His_13' => 'รอบที่ 13 (พ.ศ. 2567)',
            'His_14' => 'รอบที่ 14 (พ.ศ. 2568) (สำหรับผู้ให้ข้อมูลรายใหม่)',
            
            // รหัสจังหวัด (C2t1) - ตัวอย่างบางจังหวัด
            'C2t1_53' => 'ลำปาง',
            'C2t1_55' => 'น่าน',
            'C2t1_30' => 'นครราชสีมา',
            'C2t1_47' => 'นครพนม',
            'C2t1_39' => 'หนองบัวลำภู',
            'C2t1_13' => 'ปทุมธานี',
            'C2t1_21' => 'ระยอง',
            'C2t1_76' => 'เพชรบุรี',
            'C2t1_86' => 'ชุมพร',
            'C2t1_92' => 'ตรัง',
            'C2t1_10' => 'กรุงเทพฯ',
            
            // รหัสจังหวัด (C2t1) - ตามภาพ
            'C2t1_1' => 'กรุงเทพมหานคร',
            'C2t1_2' => 'ลำปาง',
            'C2t1_3' => 'น่าน',
            'C2t1_4' => 'นครราชสีมา',
            'C2t1_5' => 'นครพนม',
            'C2t1_6' => 'หนองบัวลำภู',
            'C2t1_7' => 'ปทุมธานี',
            'C2t1_8' => 'ระยอง',
            'C2t1_9' => 'เพชรบุรี',
            'C2t1_10' => 'ชุมพร',
            'C2t1_11' => 'ตรัง',
            'C2t1_12' => 'เชียงราย',
            'C2t1_13' => 'นครศรีธรรมราช',
            
            // รหัสอำเภอ (C2t2) - ตามภาพ
            'C2t2_1' => 'อำเภอเมือง',
            'C2t2_2' => 'อำเภอแม่พริก',
            'C2t2_3' => 'อำเภอทุ่งช้าง',
            'C2t2_4' => 'อำเภอปากช่อง',
            'C2t2_5' => 'อำเภอเมือง',
            'C2t2_6' => 'อำเภอสุวรรณคูหา',
            'C2t2_7' => 'อำเภอเมือง',
            'C2t2_8' => 'อำเภอวังจันทร์',
            'C2t2_9' => 'อำเภอชะอำ',
            'C2t2_10' => 'อำเภอปะทิว',
            'C2t2_11' => 'อำเภอย่านตาขาว',
            'C2t2_12' => 'อำเภอเมืองเชียงราย',
            'C2t2_13' => 'อำเภอเมืองนครศรีธรรมราช',
            
            // รหัสตำบล (C2t3) - ตามภาพ
            'C2t3_1' => 'ป้อมปราบศัตรูพ่าย',
            'C2t3_2' => 'ตลิ่งชัน',
            'C2t3_3' => 'บางรัก',
            'C2t3_4' => 'ตำบลแม่พริก',
            'C2t3_5' => 'ตำบลทุ่งช้าง',
            'C2t3_6' => 'ตำบลปากช่อง',
            'C2t3_7' => 'ตำบลเมือง',
            'C2t3_8' => 'ตำบลสุวรรณคูหา',
            'C2t3_9' => 'ตำบลเมือง',
            'C2t3_10' => 'ตำบลวังจันทร์',
            'C2t3_11' => 'ตำบลชะอำ',
            'C2t3_12' => 'ตำบลปะทิว',
            'C2t3_13' => 'ตำบลย่านตาขาว',
            'C2t3_14' => 'ตำบลเมืองเชียงราย',
            'C2t3_15' => 'ตำบลเมืองนครศรีธรรมราช',
            
            // หมู่ที่ (C2t4) - ตามภาพ
            'C2t4_1' => 'หมู่ที่ 1',
            'C2t4_2' => 'หมู่ที่ 2',
            'C2t4_3' => 'หมู่ที่ 3',
            'C2t4_4' => 'หมู่ที่ 4',
            'C2t4_5' => 'หมู่ที่ 5',
            'C2t4_6' => 'หมู่ที่ 6',
            'C2t4_7' => 'หมู่ที่ 7',
            'C2t4_8' => 'หมู่ที่ 8',
            'C2t4_9' => 'หมู่ที่ 9',
            'C2t4_10' => 'หมู่ที่ 10',
            'C2t1_57' => 'เชียงราย',
            'C2t1_80' => 'นครศรีธรรมราช',
            
            // คำตอบสำหรับเอกสารการยินยอม
            'Consent_1' => 'ข้าพเจ้าให้ความยินยอมในการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคล',
            'Consent_2' => 'ข้าพเจ้าไม่ให้ความยินยอมในการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคล',
            'Consent_3' => 'ข้าพเจ้าไม่ให้ความยินยอมในการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลบางส่วน',
            'PDPA_1' => 'ข้าพเจ้าให้ความยินยอมในการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคล',
            'PDPA_2' => 'ข้าพเจ้าไม่ให้ความยินยอมในการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคล',
            'PDPA_3' => 'ข้าพเจ้าไม่ให้ความยินยอมในการเก็บรวบรวม ใช้ และเปิดเผยข้อมูลส่วนบุคคลบางส่วน',
            
            // คำตอบสำหรับข้อมูลผู้ให้ข้อมูล
            'Q1_1' => '1. ชาย',
            'Q1_2' => '2. หญิง',
            'Q1_3' => '3. ผู้มีความหลากหลายทางเพศ',
            
            'Q3_1' => 'โสด',
            'Q3_2' => 'สมรส',
            'Q3_3' => 'หย่าร้าง',
            'Q3_4' => 'หม้าย',
            'Q3_5' => 'แยกกันอยู่',
            
            'Q4_1' => 'ไม่ได้รับการศึกษา',
            'Q4_2' => 'ประถมศึกษา',
            'Q4_3' => 'มัธยมศึกษาตอนต้น',
            'Q4_4' => 'มัธยมศึกษาตอนปลาย',
            'Q4_5' => 'อนุปริญญา/ปวส.',
            'Q4_6' => 'ปริญญาตรี',
            'Q4_7' => 'สูงกว่าปริญญาตรี',
            
            'Q5_1' => 'รับราชการ/รัฐวิสาหกิจ',
            'Q5_2' => 'พนักงานบริษัท/ลูกจ้างเอกชน',
            'Q5_3' => 'ค้าขาย/ธุรกิจส่วนตัว',
            'Q5_4' => 'เกษตรกร',
            'Q5_5' => 'รับจ้างทั่วไป',
            'Q5_6' => 'นักเรียน/นักศึกษา',
            'Q5_7' => 'ว่างงาน',
            'Q5_8' => 'แม่บ้าน',
            'Q5_9' => 'อื่นๆ',
            
            'Q6_1' => 'ไม่มีรายได้',
            'Q6_2' => 'ต่ำกว่า 5,000 บาท',
            'Q6_3' => '5,001 - 10,000 บาท',
            'Q6_4' => '10,001 - 15,000 บาท',
            'Q6_5' => '15,001 - 20,000 บาท',
            'Q6_6' => '20,001 - 30,000 บาท',
            'Q6_7' => 'มากกว่า 30,000 บาท',
            
            'Q7s4_1' => 'ชั่งวัดเอง',
            'Q7s4_2' => 'ชั่งวัดโดยเจ้าหน้าที่',
            'Q7s5_1' => 'ชั่งวัดก่อนการสัมภาษณ์',
            'Q7s5_2' => 'ชั่งวัดหลังการสัมภาษณ์',
            
            'Q8_1' => 'บ้านตนเอง',
            'Q8_2' => 'บ้านเช่า',
            'Q8_3' => 'บ้านพักสวัสดิการ',
            'Q8_4' => 'อื่นๆ',
            
            'Q9_1' => 'รถยนต์ส่วนตัว',
            'Q9_2' => 'รถจักรยานยนต์',
            'Q9_3' => 'รถโดยสารสาธารณะ',
            'Q9_4' => 'เดิน',
            'Q9_5' => 'อื่นๆ',
            
            // คำตอบสำหรับพฤติกรรมด้านกิจกรรมทางกาย
            'Q10_1' => 'ใช่',
            'Q10_2' => 'ไม่ใช่',
            'Q10s1_D5_SQ001_1' => '1. ชาย',
            'Q10s1_D5_SQ001_2' => '2. หญิง',
            'Q11_1' => 'ใช่',
            'Q11_2' => 'ไม่ใช่',
            
            // คำตอบสำหรับ Q12s1 (การบริโภคอาหาร - อาหารหลัก)
            'Q12s1_1' => 'ใช่',
            'Q12s1_2' => 'ใช่', // สำหรับ Q12s1[2]
            'Q12s1_16' => 'ใช่', // สำหรับ Q12s1[16]
            
            // คำตอบสำหรับ Q12s2 (การบริโภคอาหาร - อาหารเสริม)
            'Q12s2_1' => 'ใช่',
            'Q12s2_2' => 'ไม่ใช่',
            
            'Q16s1_1' => 'ใช่',
            'Q16s1_2' => 'ไม่ใช่',
            'Q16s2_1' => 'ใช่',
            'Q16s2_2' => 'ไม่ใช่',
            'Q16s3_1' => 'ใช่',
            'Q16s3_2' => 'ไม่ใช่',
            'Q16s4_1' => 'ใช่',
            'Q16s4_2' => 'ไม่ใช่',
            
            // คำตอบสำหรับ C2 - ข้อมูลที่อยู่ (ตามภาพ)
            'C2t1_1' => 'กรุงเทพมหานคร',
            'C2t1_2' => 'ลำปาง',
            'C2t1_3' => 'น่าน',
            'C2t1_4' => 'นครราชสีมา',
            'C2t1_5' => 'นครพนม',
            'C2t1_6' => 'หนองบัวลำภู',
            'C2t1_7' => 'ปทุมธานี',
            'C2t1_8' => 'ระยอง',
            'C2t1_9' => 'เพชรบุรี',
            'C2t1_10' => 'ชุมพร',
            'C2t1_11' => 'ตรัง',
            'C2t1_12' => 'เชียงราย',
            'C2t1_13' => 'นครศรีธรรมราช',
            
            'C2t2_1' => 'อำเภอเมือง',
            'C2t2_2' => 'อำเภอแม่พริก',
            'C2t2_3' => 'อำเภอทุ่งช้าง',
            'C2t2_4' => 'อำเภอปากช่อง',
            'C2t2_5' => 'อำเภอเมือง',
            'C2t2_6' => 'อำเภอสุวรรณคูหา',
            'C2t2_7' => 'อำเภอเมือง',
            'C2t2_8' => 'อำเภอวังจันทร์',
            'C2t2_9' => 'อำเภอชะอำ',
            'C2t2_10' => 'อำเภอปะทิว',
            'C2t2_11' => 'อำเภอย่านตาขาว',
            'C2t2_12' => 'อำเภอเมืองเชียงราย',
            'C2t2_13' => 'อำเภอเมืองนครศรีธรรมราช',
            
            'C2t3_1' => 'ป้อมปราบศัตรูพ่าย',
            'C2t3_2' => 'ตลิ่งชัน',
            'C2t3_3' => 'บางรัก',
            'C2t3_4' => 'ตำบลแม่พริก',
            'C2t3_5' => 'ตำบลทุ่งช้าง',
            'C2t3_6' => 'ตำบลปากช่อง',
            'C2t3_7' => 'ตำบลเมือง',
            'C2t3_8' => 'ตำบลสุวรรณคูหา',
            'C2t3_9' => 'ตำบลเมือง',
            'C2t3_10' => 'ตำบลวังจันทร์',
            'C2t3_11' => 'ตำบลชะอำ',
            'C2t3_12' => 'ตำบลปะทิว',
            'C2t3_13' => 'ตำบลย่านตาขาว',
            'C2t3_14' => 'ตำบลเมืองเชียงราย',
            'C2t3_15' => 'ตำบลเมืองนครศรีธรรมราช',
            
            'C2t4_1' => 'หมู่ที่ 1',
            'C2t4_2' => 'หมู่ที่ 2',
            'C2t4_3' => 'หมู่ที่ 3',
            'C2t4_4' => 'หมู่ที่ 4',
            'C2t4_5' => 'หมู่ที่ 5',
            'C2t4_6' => 'หมู่ที่ 6',
            'C2t4_7' => 'หมู่ที่ 7',
            'C2t4_8' => 'หมู่ที่ 8',
            'C2t4_9' => 'หมู่ที่ 9',
            'C2t4_10' => 'หมู่ที่ 10',
            
            // คำตอบสำหรับ C2t2t2 - รหัสอำเภอ ของจังหวัดลำปาง
            'C2t2t2_1' => 'อำเภอเมืองลำปาง',
            'C2t2t2_2' => 'อำเภอแม่พริก',
            'C2t2t2_3' => 'อำเภอเกาะคา',
            'C2t2t2_4' => 'อำเภอแม่ทะ',
            'C2t2t2_5' => 'อำเภอเถิน',
            'C2t2t2_6' => 'อำเภอสบปราบ',
            'C2t2t2_7' => 'อำเภอเสริมงาม',
            'C2t2t2_8' => 'อำเภอแจ้ห่ม',
            'C2t2t2_9' => 'อำเภอวังเหนือ',
            'C2t2t2_10' => 'อำเภอเมืองปาน',
            'C2t2t2_11' => 'อำเภอแม่เมาะ',
            'C2t2t2_12' => 'อำเภอสบปราบ',
            'C2t2t2_13' => 'อำเภอเมืองปาน',
            
            // คำตอบสำหรับ C2t2t2t1 - รหัสตำบล ของอำเภอแม่พริก จังหวัดลำปาง
            'C2t2t2t1_1' => 'ตำบลแม่พริก',
            'C2t2t2t1_2' => 'ตำบลแม่ปุ',
            'C2t2t2t1_3' => 'ตำบลแม่พริกเหนือ',
            'C2t2t2t1_4' => 'ตำบลแม่พริกใต้',
            'C2t2t2t1_5' => 'ตำบลแม่พริกกลาง',
            'C2t2t2t1_6' => 'ตำบลแม่พริกตะวันออก',
            'C2t2t2t1_7' => 'ตำบลแม่พริกตะวันตก',
            'C2t2t2t1_8' => 'ตำบลแม่พริกเหนือ',
            'C2t2t2t1_9' => 'ตำบลแม่พริกใต้',
            'C2t2t2t1_10' => 'ตำบลแม่พริกกลาง',
            
            // คำตอบสำหรับส่วนที่ 3 : พฤติกรรมการใช้เวลาเกี่ยวกับการเคลื่อนไหวตลอด 24 ชั่วโมง
            'SB01_Instruction' => 'กรอกแต่ละช่องเป็นจํานวนนาที เช่น 30 นาที (แต่ไม่เกิน 60 นาที) โดยพฤติกรรมแต่ละแถวในชั่วโมงนั้น ๆ รวมกันได้ไม่เกิน 60 นาที (ไม่ถึง 60 นาทีได้)',
            
            // คำจำกัดความพฤติกรรม
            'SB01_LightActivity' => 'กิจกรรมทางกายระดับเบา: กิจกรรมที่เคลื่อนไหวเล็กน้อย ไม่เหนื่อย ไม่มีเหงื่อออก ร่างกายอุ่นขึ้นเล็กน้อย หัวใจเต้นปกติ พูดคุยเป็นประโยคได้ปกติ เช่น ยืดเหยียดกล้ามเนื้อ, งานบ้าน, งานอาชีพ, ดูแลตนเอง, ขับรถ, เดินป่า, เล่นกีฬา, กิจกรรมศาสนา, อาสาสมัคร',
            'SB01_Sedentary' => 'พฤติกรรมเนือยนิ่ง: พฤติกรรมการนั่งหรือเอนกายที่มีการเคลื่อนไหวน้อย ทั้งที่บ้าน ที่ทำงาน หรือสถานที่ต่างๆ เช่น ทำงานบ้าน, ดูแลตนเอง, นั่งพูดคุย, เดินทางในรถยนต์/รถสาธารณะ/รถไฟ/เครื่องบิน, อ่านหนังสือ, ดูโทรทัศน์, เล่นคอมพิวเตอร์',
            'SB01_Sleep' => 'พฤติกรรมการนอนหลับ: การนอนหลับพักผ่อนระหว่างวัน',
            
            // ข้อความแจ้งเตือนและข้อผิดพลาดใน TimeSummary
            'TimeSummary_Error_Hourly' => 'โปรดตรวจสอบการลงเวลาในตารางด้านบน ในแถว HH.MM - HH.MM น.',
            'TimeSummary_Error_Total' => 'โปรดตรวจสอบจำนวนนาทีรวมทั้งหมดที่บันทึกไว้ ไม่ใช่ 1440 นาที',
            'TimeSummary_Validation_Hourly' => 'ผลรวมกิจกรรมในแต่ละชั่วโมงต้องไม่เกิน 60 นาที',
            'TimeSummary_Validation_Total' => 'ผลรวมกิจกรรมตลอด 24 ชั่วโมงต้องเท่ากับ 1440 นาที',
            
            // รหัสสำหรับการตรวจสอบข้อมูล
            'TimeSummary_Check_Total' => 'การตรวจสอบผลรวมทั้งหมด',
            'TimeSummary_Check_Hourly' => 'การตรวจสอบผลรวมรายชั่วโมง',
            
            // ส่วนที่ 4 : การตระหนักรู้เรื่องกิจกรรมทางกายและพฤติกรรมเนือยนิ่ง
            'Q16' => 'การตระหนักรู้เกี่ยวกับประโยชน์ของการมีกิจกรรมทางกาย',
            'Q16_1' => 'ลดน้ำหนัก',
            'Q16_2' => 'รูปร่างที่สวยงามหุ่นดี',
            'Q16_3' => 'สุขภาพแข็งแรงป้องกันการเจ็บป่วย',
            'Q16_4' => 'บรรเทาโรคและอาการเจ็บป่วย',
            'Q16_5' => 'มีสมรรถภาพทางกายที่ดี พร้อมต่อการเล่นกีฬาและหากิจกรรมอื่น ๆ',
            'Q16_6' => 'คลายเครียด/สนุกสนาน',
            'Q16_7' => 'ป้องกันโรคสมองเสื่อม/ภาวะซึมเศร้า',
            'Q16_8' => 'ไม่เหงา แก้เบื่อ',
            'Q16_9' => 'ออกกำลังกายหรือเล่นกีฬาแล้วจะสามารถกินอาหารต่าง ๆ ได้ตามต้องการ',
            'Q16_10' => 'มีแรงบันดาลใจเอาชนะใจตนเองได้พิชิตเป้าหมายของตนเอง',
            'Q16_11' => 'ทำให้นอนหลับได้ง่าย',
            'Q16_12' => 'ลดค่าใช้จ่ายผ่านการรักษาพยาบาล เพราะร่างกายแข็งแรง ไม่เจ็บป่วย',
            'Q16_13' => 'มีผลการเรียนที่ดีขึ้น',
            'Q16_14' => 'ทำงานมีประสิทธิภาพมากขึ้น/ทำงานได้ดีขึ้น',
            'Q16_15' => 'ได้ทำกิจกรรมร่วมกับครอบครัว/เพื่อน',
            'Q16_16' => 'ได้พบปะ รวมกลุ่มกับเพื่อน',
            'Q16_17' => 'ลดการกระทำความผิดหรือลดความเสี่ยงจากการกระทำความผิด เช่น การใช้สารเสพติด การใช้ความรุนแรง เป็นต้น',
            'Q16_18' => 'คนในชุมชนมีสุขภาพจิต และสภาพการที่ดี',
            'Q16_19' => 'แหล่งพักผ่อนหย่อนใจในชุมชนสะอาดและชุมชนมีขึ้น',
            'Q16_20' => 'ชุมชนเห็นความสำคัญ และสนับสนุนพื้นที่และอุปกรณ์ให้คนในชุมชนมากขึ้น',
            'Q16_21' => 'เกิดการสนับสนุนเงิน ทุน งบประมาณ ในการส่งเสริมกิจกรรมทางกายในชุมชนมากขึ้น',
            'Q16_22' => 'ไม่ทราบ/ไม่แน่ใจ',
            'Q16_23' => 'ตอบไม่ได้',
            'Q16_24' => 'อื่น ๆ โปรดระบุ',
            
            // ข้อคำถามเกี่ยวกับความรอบรู้ทางกาย (Physical Literacy)
            'PL1' => 'ข้อคำถามเกี่ยวกับความรอบรู้ทางกาย',
            'PL1_Instruction' => 'ข้อคำถามในส่วนต่อไปนี้ เป็นการถามความคิดเห็นและความรู้สึกของท่านเกี่ยวกับทักษะ สมรรถนะ ความมั่นใจและการมีส่วนร่วมในการเคลื่อนไหวร่างกาย เล่นกีฬา ออกกำลังกาย และกิจกรรมทางกาย ดังนั้น ขอให้ท่านตอบคำถามให้ตรงกับความคิดเห็นและความรู้สึกของท่านมากที่สุด',
            
            // คำถามย่อยเกี่ยวกับความรอบรู้ทางกาย (Q17s1 ถึง Q17s7)
            'Q17s1' => 'ท่านคิดว่าคนเองมีทักษะเพียงพอที่จะเล่นกีฬาได้ทุกประเภทที่ตนเองต้องการเล่น',
            'Q17s2' => 'ท่านคิดว่าการเคลื่อนไหวร่างกาย หรือออกกำลังกาย สำคัญต่อการมีสุขภาพที่แข็งแรง',
            'Q17s3' => 'ท่านคิดว่าคนเองสามารถเข้าร่วมเล่นกีฬา หรือกิจกรรมการเคลื่อนไหวร่างกายชนิดใดก็ได้ที่ตนเองเลือก',
            'Q17s4' => 'ท่านรู้สึกว่า ร่างกายของตัวเองแข็งแรงพอที่จะเล่นหรือทำกิจกรรมที่ตนเองอยากทำได้',
            'Q17s5' => 'ท่านมีความเข้าใจกฎ กติกาของการออกกำลังกายและเล่นกีฬาประเภทต่าง ๆ',
            'Q17s6' => 'ท่านมีความรู้สึกว่าตนเองสามารถเล่นกีฬาและออกกำลังกายประเภทต่าง ๆ ได้ดี',
            'Q17s7' => 'ท่านมีความสุข สนุกสนาน เมื่อได้ออกกำลังกาย หรือเล่นกีฬา',
            
            // การตระหนักรู้เกี่ยวกับโทษของพฤติกรรมเนือยนิ่ง
            'Q18' => 'การตระหนักรู้เกี่ยวกับโทษของพฤติกรรมเนือยนิ่ง',
            'Q18_0' => 'ไม่มี',
            'Q18_1' => 'มีน้ำหนักตัวเกิน/ภาวะโรคอ้วน',
            'Q18_2' => 'รูปร่างไม่สมส่วน',
            'Q18_3' => 'ร่างกายไม่แข็งแรง',
            'Q18_4' => 'เป็นโรคความดัน/โรคเบาหวาน/โรคหัวใจและหลอดเลือด/โรคมะเร็งต่าง ๆ',
            'Q18_5' => 'ปัญหาการนอนหลับ',
            'Q18_6' => 'เกิดภาวะซึมเศร้า',
            'Q18_7' => 'โรคติดอินเทอร์เน็ต/ติดโซเชียล',
            'Q18_8' => 'ส่งผลเสียต่อประสิทธิภาพในการเรียน',
            'Q18_9' => 'ส่งผลเสียต่อประสิทธิภาพในการทำงาน',
            'Q18_10' => 'ถูกกดดัน/แกล้งผ่านโลกออนไลน์',
            'Q18_11' => 'สายตาเสีย',
            'Q18_12' => 'ปวดหลัง มีอาการในกลุ่มออฟฟิศซินโดรม',
            'Q18_13' => 'อื่น ๆ โปรดระบุ',
            
            // คำตอบสำหรับ Likert Scale (Physical Literacy)
            'PL1_5' => 'เห็นด้วยอย่างยิ่ง',
            'PL1_4' => 'เห็นด้วย',
            'PL1_3' => 'รู้สึกเฉยๆ',
            'PL1_2' => 'ไม่เห็นด้วย',
            'PL1_1' => 'ไม่เห็นด้วยอย่างยิ่ง',
            'PL1_9' => 'ไม่ต้องการตอบ/ตอบไม่ได้',
            
            // ส่วนที่ 5 : การรับรู้ข้อมูลข่าวสาร จากทาง สสส.
            'Q19' => 'ในช่วง 1 ปีที่ผ่านมา ท่านได้ยิน รับทราบ หรือเห็นข้อมูลข่าวสารเกี่ยวกับการส่งเสริมกิจกรรมทางกายจากทาง สสส. หรือไม่',
            'Q19s1' => 'ท่านเคยได้ยิน รับทราบ หรือเห็นข้อมูลข่าวสารเกี่ยวกับการส่งเสริมกิจกรรมทางกายจากทาง สสส. หรือไม่',
            'Q19s2' => 'กิจกรรมลักษณะหรือรูปแบบใดที่ท่านเคยได้ยิน รับทราบ หรือเห็นข้อมูลข่าวสารเกี่ยวกับการส่งเสริมกิจกรรมทางกายจากทาง สสส.',
            'Q20' => 'แล้วใน 1 ปีที่ผ่านมา ท่านเคยเข้าร่วมกิจกรรมการส่งเสริมกิจกรรมทางกายที่จัดโดย สสส. หรือไม่',
            
            // คำตอบสำหรับ Q19 (การรับรู้ข้อมูลข่าวสาร)
            'Q19_1' => 'เคย',
            'Q19_2' => 'ไม่เคย',
            'Q19_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q19s1 (การรับทราบข้อมูล)
            'Q19s1_1' => 'เคย',
            'Q19s1_2' => 'ไม่เคย',
            'Q19s1_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q19s2 (ประเภทกิจกรรมที่รับทราบ)
            'Q19s2_1' => 'การออกกำลังกาย',
            'Q19s2_2' => 'การเล่นกีฬา',
            'Q19s2_3' => 'การเดิน',
            'Q19s2_4' => 'การวิ่ง',
            'Q19s2_5' => 'การปั่นจักรยาน',
            'Q19s2_6' => 'การเต้น',
            'Q19s2_7' => 'การทำสวน',
            'Q19s2_8' => 'การทำงานบ้าน',
            'Q19s2_9' => 'การเล่นกับเด็ก',
            'Q19s2_10' => 'การเดินทางด้วยการเดินหรือจักรยาน',
            'Q19s2_11' => 'การลดพฤติกรรมเนือยนิ่ง',
            'Q19s2_12' => 'การลดการใช้หน้าจอ',
            'Q19s2_13' => 'การส่งเสริมสุขภาพในชุมชน',
            'Q19s2_14' => 'การสร้างพื้นที่สำหรับกิจกรรมทางกาย',
            'Q19s2_15' => 'การจัดกิจกรรมในโรงเรียน',
            'Q19s2_16' => 'การจัดกิจกรรมในที่ทำงาน',
            'Q19s2_17' => 'การจัดกิจกรรมในชุมชน',
            'Q19s2_18' => 'การจัดกิจกรรมในสวนสาธารณะ',
            'Q19s2_19' => 'การจัดกิจกรรมในศูนย์การค้า',
            'Q19s2_20' => 'การจัดกิจกรรมในสถานที่สาธารณะอื่นๆ',
            'Q19s2_21' => 'การรณรงค์ผ่านสื่อต่างๆ',
            'Q19s2_22' => 'การรณรงค์ผ่านโซเชียลมีเดีย',
            'Q19s2_23' => 'การรณรงค์ผ่านป้ายโฆษณา',
            'Q19s2_24' => 'การรณรงค์ผ่านวิทยุ',
            'Q19s2_25' => 'การรณรงค์ผ่านโทรทัศน์',
            'Q19s2_26' => 'การรณรงค์ผ่านหนังสือพิมพ์',
            'Q19s2_27' => 'การรณรงค์ผ่านเว็บไซต์',
            'Q19s2_28' => 'การรณรงค์ผ่านแอปพลิเคชัน',
            'Q19s2_29' => 'การรณรงค์ผ่าน LINE',
            'Q19s2_30' => 'การรณรงค์ผ่าน Facebook',
            'Q19s2_31' => 'การรณรงค์ผ่าน Instagram',
            'Q19s2_32' => 'การรณรงค์ผ่าน YouTube',
            'Q19s2_33' => 'การรณรงค์ผ่าน TikTok',
            'Q19s2_34' => 'การรณรงค์ผ่าน Twitter',
            'Q19s2_35' => 'การรณรงค์ผ่านสื่ออื่นๆ',
            'Q19s2_36' => 'ไม่ทราบ/ไม่แน่ใจ',
            'Q19s2_37' => 'ตอบไม่ได้',
            'Q19s2_38' => 'อื่นๆ โปรดระบุ',
            
            // คำตอบสำหรับ Q20 (การเข้าร่วมกิจกรรม)
            'Q20_1' => 'เคย',
            'Q20_2' => 'ไม่เคย',
            'Q20_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // ส่วนที่ 6 : การเข้าถึงพื้นที่สุขภาวะในชุมชน
            'Q21' => 'ชุมชนของท่านมีพื้นที่สาธารณะ พื้นที่สุขภาวะ หรือสถานที่ในการออกกำลังกายและเล่นกีฬาเพื่อสุขภาพ เช่น สนามกีฬา โรงยิม เครื่องออกกำลังกายกลางแจ้ง สนามเด็กเล่น ฟิตเนส สวนสาธารณะ ลานออกกำลังกายกลางแจ้ง ลานโล่ง/ลานละเล่น สถานที่พักผ่อนหย่อนใจ เป็นต้น เพื่อส่งเสริมการออกกำลังกาย เล่นกีฬา วิ่งเล่น เคลื่อนไหวร่างกาย และนันทนาการ บ้างหรือไม่',
            'Q21s1' => 'แล้วท่านเคยได้มีโอกาสใช้พื้นที่สาธารณะ พื้นที่สุขภาวะ หรือสถานที่ในการออกกำลังกายและเล่นกีฬาเพื่อสุขภาพในชุมชนของท่านบ้างหรือไม่',
            'Q22' => 'ชุมชนของท่าน มีเส้นทางเดินเท้าหรือเส้นทางสัญจรรอบ ๆ ชุมชน สำหรับเดินหรือปั่นจักรยาน บ้างหรือไม่',
            'Q22s1' => 'ท่านมีโอกาสใช้ทางเดินเท้าหรือเส้นทางสัญจรสำหรับเดินหรือปั่นจักรยานในชุมชนของท่านบ้างหรือไม่',
            'Q23' => 'ใน 1 ปีที่ผ่านมา ท่านเคยได้รับข้อมูล คำแนะนำเกี่ยวกับกิจกรรมทางกาย การออกกำลังกาย เล่นกีฬา การเคลื่อนไหวร่างกายและการดูแลสุขภาพ จากเจ้าหน้าที่หรือบุคลากรของสถานบริการทางสุขภาพ หรืออาสาสมัครสาธารณสุขประจำหมู่บ้าน (อสม.) บ้างหรือไม่',
            'Q23s1' => 'บ่อยครั้งเพียงใด',
            'Q24' => 'ชุมชนที่ท่านอาศัยอยู่มีกิจกรรมหรือนโยบาย ในเรื่องการออกกำลังกาย เล่นกีฬา หรือการส่งเสริมการเคลื่อนไหวร่างกายในชีวิตประจำวันให้กับสมาชิกชุมชน บ้างหรือไม่ (หากมี มีในลักษณะใดบ้าง) (ตอบได้มากกว่า 1 ตัวเลือก)',
            'Q25' => 'ที่ทำงานของท่านมีนโยบายการส่งเสริมกิจกรรมทางกาย การออกกำลังกาย หรือการเคลื่อนไหวร่างกายในชีวิตประจำวันให้กับพนักงานบ้างหรือไม่',
            'Q26' => 'ที่โรงเรียนหรือสถานศึกษาของท่านมีกิจกรรมหรือนโยบายการส่งเสริมกิจกรรมทางกาย การออกกำลังกาย หรือการเคลื่อนไหวร่างกายในชีวิตประจำวันให้กับนักเรียน นักศึกษา บ้างหรือไม่',
            'Q26s1' => 'กิจกรรม (ตอบได้มากกว่า 1 ตัวเลือก)',
            'Q26s2' => 'พื้นที่ สถานที่ และอุปกรณ์ (ตอบได้มากกว่า 1 ตัวเลือก)',
            'Q27' => 'โรงเรียนหรือสถานศึกษาของท่านมีพื้นที่ สถานที่ และอุปกรณ์สำหรับการออกกำลังกาย เล่นกีฬา และการเคลื่อนไหวร่างกายบ้างหรือไม่',
            
            // คำตอบสำหรับ Q21 (พื้นที่สาธารณะในชุมชน)
            'Q21_1' => 'มี และเหมาะสมต่อการใช้งาน',
            'Q21_2' => 'มี แต่ยังไม่เหมาะสมต่อการใช้งาน',
            'Q21_3' => 'ไม่มี/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q21s1 (การใช้พื้นที่สาธารณะ)
            'Q21s1_1' => 'เคย',
            'Q21s1_2' => 'ไม่เคย',
            'Q21s1_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q22 (เส้นทางเดินเท้า/จักรยาน)
            'Q22_1' => 'มี และเหมาะสมต่อการใช้งาน',
            'Q22_2' => 'มี แต่ยังไม่เหมาะสมต่อการใช้งาน',
            'Q22_3' => 'ไม่มี/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q22s1 (การใช้เส้นทางเดินเท้า/จักรยาน)
            'Q22s1_1' => 'เคย',
            'Q22s1_2' => 'ไม่เคย',
            'Q22s1_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q23 (ข้อมูลจากเจ้าหน้าที่สุขภาพ)
            'Q23_1' => 'เคย',
            'Q23_2' => 'ไม่เคย',
            'Q23_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q23s1 (ความถี่)
            'Q23s1_1' => 'ทุกวัน',
            'Q23s1_2' => 'สัปดาห์ละ 2-3 ครั้ง',
            'Q23s1_3' => 'สัปดาห์ละ 1 ครั้ง',
            'Q23s1_4' => 'เดือนละ 2-3 ครั้ง',
            'Q23s1_5' => 'เดือนละ 1 ครั้ง',
            'Q23s1_6' => '2-3 เดือนครั้ง',
            'Q23s1_7' => '6 เดือนครั้ง',
            'Q23s1_8' => 'ปีละ 1-2 ครั้ง',
            'Q23s1_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q24 (กิจกรรม/นโยบายในชุมชน)
            'Q24_1' => 'กิจกรรมสร้างการรับรู้ประโยชน์ของกิจกรรมทางกาย/ออกกำลังกายให้แก่คนในชุมชน',
            'Q24_2' => 'กิจกรรมรณรงค์ให้มีการเดินและการใช้จักรยานภายในชุมชน',
            'Q24_3' => 'จัดตารางการใช้พื้นที่สำหรับการมีกิจกรรมทางกาย ออกกำลังกาย เล่นกีฬาอย่างเหมาะสม',
            'Q24_4' => 'จัดกิจกรรมการแข่งขันกีฬาและนันทนาการภายในชุมชน',
            'Q24_5' => 'จัดกิจกรรมการออกกำลังกายแบบเวอร์ชวลออนไลน์ Virtual online (ไม่ว่าจะเป็นการออกกำลังกาย/เล่นเกมกีฬาประเภท Virtual หรือกำหนดเป้าหมายหรือทำกิจกรรมร่วมกันแบบ Virtual)',
            'Q24_6' => 'จัดให้มีการบริการสุขภาพและแนะนำการมีกิจกรรมทางกายและออกกำลังกายอย่างเหมาะสม',
            'Q24_7' => 'ไม่มี',
            'Q24_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q25 (นโยบายที่ทำงาน)
            'Q25_1' => 'มี',
            'Q25_2' => 'ไม่มี',
            'Q25_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q26 (กิจกรรม/นโยบายในโรงเรียน)
            'Q26_1' => 'มี',
            'Q26_2' => 'ไม่มี',
            'Q26_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q26s1 (กิจกรรมในโรงเรียน)
            'Q26s1_1' => 'กิจกรรมพลศึกษา',
            'Q26s1_2' => 'กิจกรรมกีฬาสี',
            'Q26s1_3' => 'กิจกรรมการแข่งขันกีฬา',
            'Q26s1_4' => 'กิจกรรมการออกกำลังกาย',
            'Q26s1_5' => 'กิจกรรมการเต้น',
            'Q26s1_6' => 'กิจกรรมการเดิน',
            'Q26s1_7' => 'กิจกรรมการปั่นจักรยาน',
            'Q26s1_8' => 'กิจกรรมการเล่นเกม',
            'Q26s1_9' => 'กิจกรรมการทำสวน',
            'Q26s1_10' => 'กิจกรรมการทำงานบ้าน',
            'Q26s1_11' => 'กิจกรรมการเล่นกับเพื่อน',
            'Q26s1_12' => 'กิจกรรมการเดินทางด้วยการเดินหรือจักรยาน',
            'Q26s1_13' => 'กิจกรรมการลดพฤติกรรมเนือยนิ่ง',
            'Q26s1_14' => 'กิจกรรมการลดการใช้หน้าจอ',
            'Q26s1_15' => 'กิจกรรมการส่งเสริมสุขภาพ',
            'Q26s1_16' => 'กิจกรรมการสร้างพื้นที่สำหรับกิจกรรมทางกาย',
            'Q26s1_17' => 'กิจกรรมการจัดพื้นที่สำหรับกิจกรรมทางกาย',
            'Q26s1_18' => 'กิจกรรมการจัดอุปกรณ์สำหรับกิจกรรมทางกาย',
            'Q26s1_19' => 'กิจกรรมการจัดสถานที่สำหรับกิจกรรมทางกาย',
            'Q26s1_20' => 'กิจกรรมอื่นๆ',
            'Q26s1_21' => 'ไม่มี',
            'Q26s1_22' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q26s2 (พื้นที่/สถานที่/อุปกรณ์ในโรงเรียน)
            'Q26s2_1' => 'สนามกีฬา',
            'Q26s2_2' => 'โรงยิม',
            'Q26s2_3' => 'เครื่องออกกำลังกายกลางแจ้ง',
            'Q26s2_4' => 'สนามเด็กเล่น',
            'Q26s2_5' => 'ฟิตเนส',
            'Q26s2_6' => 'สวนสาธารณะ',
            'Q26s2_7' => 'ลานออกกำลังกายกลางแจ้ง',
            'Q26s2_8' => 'ลานโล่ง/ลานละเล่น',
            'Q26s2_9' => 'สถานที่พักผ่อนหย่อนใจ',
            'Q26s2_10' => 'เส้นทางเดินเท้า',
            'Q26s2_11' => 'เส้นทางจักรยาน',
            'Q26s2_12' => 'ห้องพลศึกษา',
            'Q26s2_13' => 'ห้องออกกำลังกาย',
            'Q26s2_14' => 'ห้องเต้น',
            'Q26s2_15' => 'ห้องเกม',
            'Q26s2_16' => 'ห้องทำสวน',
            'Q26s2_17' => 'ห้องทำงานบ้าน',
            'Q26s2_18' => 'ห้องเล่นกับเพื่อน',
            'Q26s2_19' => 'ห้องเดินทาง',
            'Q26s2_20' => 'ห้องลดพฤติกรรมเนือยนิ่ง',
            'Q26s2_21' => 'ห้องลดการใช้หน้าจอ',
            'Q26s2_22' => 'ห้องส่งเสริมสุขภาพ',
            'Q26s2_23' => 'ห้องสร้างพื้นที่',
            'Q26s2_24' => 'ห้องจัดพื้นที่',
            'Q26s2_25' => 'ห้องจัดอุปกรณ์',
            'Q26s2_26' => 'ห้องจัดสถานที่',
            'Q26s2_27' => 'ห้องอื่นๆ',
            'Q26s2_28' => 'ไม่มี',
            'Q26s2_29' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ Q27 (พื้นที่/สถานที่/อุปกรณ์ในโรงเรียน)
            'Q27_1' => 'มี และเหมาะสมต่อการใช้งาน',
            'Q27_2' => 'มี แต่ยังไม่เหมาะสมต่อการใช้งาน',
            'Q27_3' => 'ไม่มี/ไม่แน่ใจ',
            'Q27_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // ส่วนที่ 7 : แรงจูงใจกับการมีกิจกรรมทางกาย
            'SCT1' => 'ในส่วนนี้ขอสอบถามความคิดเห็นของท่าน แม้ว่าท่านจะไม่ใช่ผู้ที่มีกิจกรรมทางกาย หรือการออกกำลังกาย เล่นกีฬาเป็นประจำ',
            'Q28' => 'โดยปกติแล้ว การที่บุคคลๆ หนึ่ง จะลุกขึ้นมาปรับเปลี่ยนพฤติกรรมสุขภาพของตนเองได้ โดยเฉพาะเรื่องของการออกกำลังกายไม่ใช่เรื่องที่ทำได้ง่ายนัก ด้วยเหตุนี้ จึงอยากสอบถามท่านว่า ในกรณีของท่านนั้น อะไรคือแรงกระตุ้น กำลังใจ ปัจจัยสนับสนุน หรือแรงจูงใจอะไรบ้างที่ทำให้ท่านมี หรืออยากจะลุกขึ้นมาออกกำลังกายหรือเล่นกีฬาเป็นประจำ (สำหรับท่านที่ไม่ค่อยได้ออกกำลังกายหรือเล่นกีฬาเป็นประจำ อยากขอให้นึกถึงปัจจัย หรือแรงจูงใจที่คิดว่าจะสามารถช่วยให้ท่านปรับเปลี่ยนพฤติกรรมได้) (ตอบได้มากกว่า 1 ตัวเลือก) ไม่ถามนำ',
            
            // คำตอบสำหรับ Q28 (แรงจูงใจกับการมีกิจกรรมทางกาย)
            'Q28_1' => 'เป็นความรู้สึกชอบที่จะทำกิจกรรมด้วยตนเอง',
            'Q28_2' => 'ประโยชน์ที่มีต่อรูปร่าง และน้ำหนักตัว',
            'Q28_3' => 'อยากให้ร่างกายปราศจากโรค',
            'Q28_4' => 'ผ่อนคลายความเครียด/สุขภาพจิตดี',
            'Q28_5' => 'นอนหลับสบาย',
            'Q28_6' => 'เพิ่มประสิทธิภาพในการทำงาน',
            'Q28_7' => 'ทำกิจกรรมร่วมกับสมาชิกในครอบครัว',
            'Q28_8' => 'มีเพื่อนหรือคนรู้จักชวน หรือไปทำกิจกรรมด้วย',
            'Q28_9' => 'สภาพอากาศเอื้ออำนวย',
            'Q28_10' => 'ปราศจากมลภาวะทางอากาศ เช่น ฝุ่น ควัน กลิ่น',
            'Q28_11' => 'เป็นพื้นที่ที่ปลอดภัยจากอุบัติเหตุ',
            'Q28_12' => 'มีสถานที่ที่เหมาะสม และปลอดภัย',
            'Q28_13' => 'แพทย์หรือบุคลากรทางสาธารณสุขให้ข้อแนะนำ',
            'Q28_14' => 'อื่นๆ โปรดระบุ',
            
            // ส่วนที่ 8 : การเปลี่ยนแปลงภูมิอากาศ (Climate change)
            'CC1' => 'ในช่วง 1 ปีที่ผ่านมา ท่านมีความรู้สึกวิตกกังวลในเรื่องฝุ่น PM2.5 มากน้อยเพียงใด',
            'CC1s1' => 'แล้วท่านมีกังวลในเรื่องฝุ่น PM2.5 มากน้อยเพียงใด',
            'CC2' => '30. แล้วนอกจากเรื่องฝุ่น PM 2.5 ท่านมีความกังวลในเรื่องอื่นๆ ที่เกี่ยวข้องกับการเปลี่ยนแปลงสภาพแวดล้อมและภูมิอากาศในเรื่องใดอีกบ้างหรือไม่ (ตอบได้มากกว่า 1 ตัวเลือก)',
            'CC2s1' => 'แล้วผลกระทบการเปลี่ยนแปลงดังกล่าว มีผลกระทบต่อการไปทํากิจกรรมทางกาย การออกกำลังกายกลางแจ้งบ้างหรือไม่',
            'CC2s2' => 'ท่านมีความคิดเห็นหรือข้อเสนอแนะเกี่ยวกับการปรับตัวต่อการเปลี่ยนแปลงสภาพแวดล้อมและภูมิอากาศเพื่อส่งเสริมการมีกิจกรรมทางกายอย่างไรบ้าง',
            'Thx' => 'กด "Submit" เพื่อยืนยันการสัมภาษณ์',
            
            // คำตอบสำหรับ CC1 (ความวิตกกังวลเรื่องฝุ่น PM2.5)
            'CC1_5' => 'มากที่สุด',
            'CC1_4' => 'มาก',
            'CC1_3' => 'ปานกลาง',
            'CC1_2' => 'น้อย',
            'CC1_1' => 'น้อยที่สุด',
            'CC1_0' => 'ไม่เลย',
            
            // คำตอบสำหรับ CC1s1 (ความกังวลเรื่องฝุ่น PM2.5)
            'CC1s1_5' => 'มากที่สุด',
            'CC1s1_4' => 'มาก',
            'CC1s1_3' => 'ปานกลาง',
            'CC1s1_2' => 'น้อย',
            'CC1s1_1' => 'น้อยที่สุด',
            'CC1s1_0' => 'ไม่เลย',
            
            // คำตอบสำหรับ CC2 (ความกังวลเรื่องการเปลี่ยนแปลงสภาพแวดล้อมและภูมิอากาศ)
            'CC2_1' => 'อากาศร้อน (อุณหภูมิสูง)',
            'CC2_2' => 'มีแสงแดดที่แรงจัด',
            'CC2_3' => 'ความชื้นสูง',
            'CC2_4' => 'คุณภาพอากาศไม่ดี หรือฝุ่นละอองเกสรดอกไม้',
            'CC2_5' => 'พายุฤดูร้อน ฝนฟ้าคะนอง/ฟ้าผ่า',
            'CC2_6' => 'อากาศเย็น หนาว หมอกจัด',
            'CC2_7' => 'น้ำท่วม',
            'CC2_8' => 'แห้งแล้ง',
            'CC2_9' => 'ภาวะโลกร้อน/ปรากฏการณ์เรือนกระจก',
            'CC2_10' => 'ไม่แน่ใจ/ไม่ทราบ',
            'CC2_11' => 'อื่น ๆ โปรดระบุ',
            
            // คำตอบสำหรับ CC2s1 (ผลกระทบต่อกิจกรรมทางกายกลางแจ้ง)
            'CC2s1_1' => 'มี',
            'CC2s1_2' => 'ไม่มี',
            'CC2s1_9' => 'ไม่ทราบ/ไม่แน่ใจ',
            
            // คำตอบสำหรับ CC2s2 (ความคิดเห็น/ข้อเสนอแนะ)
            'CC2s2_1' => 'ควรมีการปรับเวลาในการออกกำลังกาย',
            'CC2s2_2' => 'ควรมีการปรับสถานที่ในการออกกำลังกาย',
            'CC2s2_3' => 'ควรมีการปรับประเภทกิจกรรมทางกาย',
            'CC2s2_4' => 'ควรมีการปรับอุปกรณ์ในการออกกำลังกาย',
            'CC2s2_5' => 'ควรมีการปรับเสื้อผ้าในการออกกำลังกาย',
            'CC2s2_6' => 'ควรมีการปรับอาหารและน้ำดื่ม',
            'CC2s2_7' => 'ควรมีการปรับการพักผ่อน',
            'CC2s2_8' => 'ควรมีการปรับการดูแลสุขภาพ',
            'CC2s2_9' => 'ควรมีการปรับการป้องกันโรค',
            'CC2s2_10' => 'ควรมีการปรับการรักษาโรค',
            'CC2s2_11' => 'ควรมีการปรับการฟื้นฟูสุขภาพ',
            'CC2s2_12' => 'ควรมีการปรับการส่งเสริมสุขภาพ',
            'CC2s2_13' => 'ควรมีการปรับการป้องกันโรค',
            'CC2s2_14' => 'ควรมีการปรับการรักษาโรค',
            'CC2s2_15' => 'ควรมีการปรับการฟื้นฟูสุขภาพ',
            'CC2s2_16' => 'ควรมีการปรับการส่งเสริมสุขภาพ',
            'CC2s2_17' => 'ควรมีการปรับการป้องกันโรค',
            'CC2s2_18' => 'ควรมีการปรับการรักษาโรค',
            'CC2s2_19' => 'ควรมีการปรับการฟื้นฟูสุขภาพ',
            'CC2s2_20' => 'ควรมีการปรับการส่งเสริมสุขภาพ',
            'CC2s2_21' => 'ไม่มีข้อเสนอแนะ',
            'CC2s2_22' => 'ไม่ทราบ/ไม่แน่ใจ',
            'CC2s2_23' => 'อื่นๆ โปรดระบุ'
        );
        
        // ลองหาใน cover page answers
        $combined_key = $question_code . '_' . $value;
        if (isset($cover_page_answers[$combined_key])) {
            return $cover_page_answers[$combined_key];
        }
        
        // ลองหาใน cover page answers โดยใช้ value อย่างเดียว
        if (isset($cover_page_answers[$value])) {
            return $cover_page_answers[$value];
        }
        
        // ลองหาใน cover page answers สำหรับ C2 โดยตรง
        if (strpos($question_code, 'C2') === 0) {
            $c2_combined_key = $question_code . '_' . $value;
            if (isset($cover_page_answers[$c2_combined_key])) {
                return $cover_page_answers[$c2_combined_key];
            }
            // ลองหาแบบ value อย่างเดียวสำหรับ C2
            if (isset($cover_page_answers[$value])) {
                return $cover_page_answers[$value];
            }
        }
        
        if (isset($value_map[$value])) {
            return $value_map[$value];
        }
        
        // Map ค่าพื้นฐานสำหรับ C2
        if (strpos($question_code, 'C2') === 0) {
            $c2_value_map = array(
                '1' => '1',
                '2' => '2',
                '3' => '3',
                '4' => '4',
                '5' => '5',
                '6' => '6',
                '7' => '7',
                '8' => '8',
                '9' => '9',
                '10' => '10',
                '11' => '11',
                '12' => '12',
                '13' => '13',
                '14' => '14',
                '15' => '15'
            );
            if (isset($c2_value_map[$value])) {
                return $c2_value_map[$value];
            }
        }
        
        // ตรวจสอบว่าเป็นวันที่หรือไม่
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) && strtotime($value)) {
            return date_i18n('d/m/Y H:i:s', strtotime($value));
        }
        
        // ตรวจสอบว่าเป็นตัวเลขหรือไม่
        if (is_numeric($value)) {
            // ถ้าเป็นเลข 1 อาจหมายถึง "เลือก" สำหรับ checkbox
            if ($value == '1' && strpos($question_code, '_') !== false) {
                return 'เลือก';
            }
            // ถ้าเป็นตัวเลขใหญ่ ให้ใส่ comma
            if (strlen($value) > 3 && strpos($value, '.') === false) {
                return number_format($value);
            }
        }
        
        // ถ้าไม่พบการ map ใดๆ ให้ return ค่าเดิม
        return $value;
    }
    
    /**
     * ตรวจจับประเภทคำถาม
     */
    private function detect_question_type($code, $response_data) {
        $related_count = 0;
        
        foreach ($response_data as $key => $value) {
            if (strpos($key, $code) === 0 && $key !== $code) {
                $related_count++;
            }
        }
        
        if ($related_count > 1) {
            return 'Array';
        } elseif (preg_match('/^A\d+/', $code)) {
            return 'ข้อความ';
        } elseif (preg_match('/^Q\d+/', $code)) {
            return 'คำถาม';
        } else {
            return 'ทั่วไป';
        }
    }
    
    /**
     * Get question type label
     */
    private function get_question_type_label($type) {
        $types = array(
            '5' => '5 ตัวเลือก',
            'A' => 'Array 5 จุด',
            'B' => 'Array 10 จุด',
            'C' => 'Array ใช่/ไม่ใช่',
            'D' => 'วันที่',
            'E' => 'Array เพิ่ม/เท่าเดิม/ลด',
            'F' => 'Array ยืดหยุ่น',
            'G' => 'เพศ',
            'H' => 'Array ยืดหยุ่น (คอลัมน์)',
            'I' => 'ภาษา',
            'K' => 'ตัวเลขหลายช่อง',
            'L' => 'รายการ',
            'M' => 'หลายตัวเลือก',
            'N' => 'ตัวเลข',
            'O' => 'รายการ + ความเห็น',
            'P' => 'หลายตัวเลือก + ความเห็น',
            'Q' => 'ข้อความหลายช่อง',
            'R' => 'อันดับ',
            'S' => 'ข้อความสั้น',
            'T' => 'ข้อความยาว',
            'U' => 'ข้อความยาวมาก',
            'X' => 'Boilerplate',
            'Y' => 'ใช่/ไม่ใช่',
            '!' => 'รายการ (Dropdown)',
            ':' => 'Array ตัวเลข',
            ';' => 'Array ข้อความ',
            '|' => 'อัพโหลดไฟล์',
            '*' => 'สมการ',
            '1' => 'Array คู่'
        );
        
        return isset($types[$type]) ? $types[$type] : $type;
    }

    private function render_complex_matrix_question($base_code, $question_info, $response_data) {
        // เตรียม subquestions (แถว)
        $rows = array();
        if (isset($question_info['subquestions']) && is_array($question_info['subquestions'])) {
            foreach ($question_info['subquestions'] as $sq_code => $sq) {
                $rows[$sq_code] = isset($sq['question']) ? $sq['question'] : $sq_code;
            }
        }
        // เตรียม columns (answer options หรือ scale)
        $columns = array();
        if (isset($question_info['answer_options']) && is_array($question_info['answer_options']) && count($question_info['answer_options']) > 0) {
            foreach ($question_info['answer_options'] as $col_code => $col_label) {
                $columns[$col_code] = $col_label;
            }
        } else {
            $columns = array('SB001' => 'การนอนหลับ', 'SB002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ', 'SB003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ', 'LPA' => 'กิจกรรมทางกายระดับเบา', 'sum' => 'ทั้งหมด');
        }
        // เตรียม original answer (จาก LimeSurvey)
        $original_answers = isset($question_info['original_answers']) ? $question_info['original_answers'] : array();
        // วาดตาราง
        echo '<style>.tpak-original { color: #aaa; }</style>';
        echo '<div class="tpak-array-answers"><table class="tpak-array-table">';
        // หัวตาราง
        echo '<thead><tr><th>ช่วงเวลา</th>';
        foreach ($columns as $col_code => $col_label) {
            $label = ($col_code === 'sum' || $col_label === 'อื่นๆ') ? 'ทั้งหมด' : $col_label;
            echo '<th>' . esc_html(strip_tags($label)) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row_code => $row_label) {
            echo '<tr>';
            echo '<td>' . esc_html(strip_tags($row_label)) . '</td>';
            $row_sum = 0;
            foreach ($columns as $col_code => $col_label) {
                if ($col_code === 'sum' || $col_label === 'อื่นๆ') {
                    echo '<td class="tpak-matrix-sum"><input type="text" value="' . $row_sum . '" readonly style="width:60px; background:#f5f5f5; text-align:right; font-weight:bold;" /></td>';
                } else {
                    $cell_key = $base_code . '[' . $row_code . '][' . $col_code . ']';
                    $original_value = isset($original_answers[$cell_key]) ? $original_answers[$cell_key] : '';
                    $cell_value = isset($response_data[$cell_key]) ? $response_data[$cell_key] : '';
                    $show_value = $cell_value !== '' ? $cell_value : $original_value;
                    $input_class = ($cell_value === '' && $original_value !== '') ? 'tpak-matrix-input tpak-original' : 'tpak-matrix-input';
                    $row_sum += is_numeric($show_value) ? (int)$show_value : 0;
                    echo '<td>';
                    echo '<input type="text" name="tpak_survey_data[' . esc_attr($cell_key) . ']" value="' . esc_attr($show_value) . '" class="' . $input_class . '" data-key="' . esc_attr($cell_key) . '" style="width:60px;" placeholder="' . esc_attr($original_value) . '" />';
                    echo '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        // เพิ่ม JS autosave และอัปเดต sum
        echo '<script>\n'
            . 'function updateMatrixSums() {\n'
            . '  jQuery(".tpak-array-answers tr").each(function(){\n'
            . '    var sum = 0;\n'
            . '    jQuery(this).find(".tpak-matrix-input").each(function(){\n'
            . '      var v = parseInt(jQuery(this).val()) || 0; sum += v;\n'
            . '    });\n'
            . '    jQuery(this).find(".tpak-matrix-sum input").val(sum);\n'
            . '  });\n'
            . '}\n'
            . 'jQuery(document).off("change blur", ".tpak-matrix-input").on("change blur", ".tpak-matrix-input", function(){\n'
            . '  var key = jQuery(this).data("key");\n'
            . '  var value = jQuery(this).val();\n'
            . '  var post_id = jQuery("#post_ID").val();\n'
            . '  var data = {action: "tpak_auto_save_survey_data", nonce: tpak_dq_vars.nonce, post_id: post_id, survey_data: {}};\n'
            . '  data.survey_data[key] = value;\n'
            . '  jQuery.post(ajaxurl, data, function(resp){});\n'
            . '  updateMatrixSums();\n'
            . '});\n'
            . 'jQuery(document).ready(function(){ updateMatrixSums(); });\n'
        . '</script>';
    }

    /**
     * ทำความสะอาดรหัสคำถาม
     */
    private function clean_question_code($code) {
        // ลบส่วนที่ไม่จำเป็นออก
        $code = preg_replace('/\[[^\]]*\]/', '', $code);
        $code = preg_replace('/_SQ\d+/', '', $code);
        $code = preg_replace('/_oth$/i', '', $code);
        $code = preg_replace('/_oth_/i', '', $code);
        $code = preg_replace('/_comment$/i', '', $code);
        $code = preg_replace('/_other$/i', '', $code);
        return trim($code);
    }
    
    /**
     * สร้าง label ที่อ่านได้สำหรับคำถาม
     */
    private function get_readable_question_label($code) {
        // Map รหัสคำถามเป็นข้อความที่อ่านได้
        $question_labels = array(
            // หน้าปกแบบสัมภาษณ์ - คำถามแรกและข้อมูลพื้นฐาน
            'C1' => 'รหัสพนักงานสัมภาษณ์',
            'C2' => 'ข้อมูลที่อยู่',
            'C2t1' => 'รหัสจังหวัด',
            'C2t2' => 'รหัสอำเภอ',
            'C2t2t2' => 'รหัสอำเภอ ของจังหวัดลำปาง',
            'C2t2t2t1' => 'รหัสตำบล ของอำเภอแม่พริก จังหวัดลำปาง',
            'C2t2t3' => 'รหัสอำเภอ ของจังหวัดน่าน',
            'C2t2t3t1' => 'รหัสตำบล ของอำเภอทุ่งช้าง จังหวัดน่าน',
            'C2t2t4' => 'รหัสอำเภอ ของจังหวัดนครราชสีมา',
            'C2t2t4t1' => 'รหัสตำบล ของอำเภอปากช่อง จังหวัดนครราชสีมา',
            'C2t2t5' => 'รหัสอำเภอ ของจังหวัดนครพนม',
            'C2t2t5t1' => 'รหัสตำบล ของอำเภอเมือง จังหวัดนครพนม',
            'C2t2t6' => 'รหัสอำเภอ ของจังหวัดหนองบัวลำภู',
            'C2t2t6t1' => 'รหัสตำบล ของอำเภอสุวรรณคูหา จังหวัดหนองบัวลำภู',
            'C2t2t7' => 'รหัสอำเภอ ของจังหวัดปทุมธานี',
            'C2t2t7t1' => 'รหัสตำบล ของอำเภอเมือง จังหวัดปทุมธานี',
            'C2t2t8' => 'รหัสอำเภอ ของจังหวัดระยอง',
            'C2t2t8t1' => 'รหัสตำบล ของอำเภอวังจันทร์ จังหวัดระยอง',
            'C2t2t9' => 'รหัสอำเภอ ของจังหวัดเพชรบุรี',
            'C2t2t9t1' => 'รหัสตำบล ของอำเภอชะอำ จังหวัดเพชรบุรี',
            'C2t2t10' => 'รหัสอำเภอ ของจังหวัดชุมพร',
            'C2t2t10t1' => 'รหัสตำบล ของอำเภอปะทิว จังหวัดชุมพร',
            'C2t2t12' => 'รหัสอำเภอ ของจังหวัดตรัง',
            'C2t2t12t1' => 'รหัสตำบล ของอำเภอย่านตาขาว จังหวัดตรัง',
            'C2t2t13' => 'รหัสอำเภอ ของจังหวัดกรุงเทพฯ',
            'C2t2t13t1' => 'รหัสตำบล ของป้อมปราบศัตรูพ่าย จังหวัดกรุงเทพฯ',
            'C2t2t13t2' => 'รหัสตำบล ของตลิ่งชัน จังหวัดกรุงเทพฯ',
            'C2t2t13t3' => 'รหัสตำบล ของบางรัก จังหวัดกรุงเทพฯ',
            'C2t2t14' => 'รหัสอำเภอ ของจังหวัดเชียงราย',
            'C2t2t14t1' => 'รหัสตำบล ของอำเภอเมืองเชียงราย จังหวัดเชียงราย',
            'C2t2t15' => 'รหัสอำเภอ ของจังหวัดนครศรีธรรมราช',
            'C2t2t15t1' => 'รหัสตำบล ของอำเภอเมืองนครศรีธรรมราช จังหวัดนครศรีธรรมราช',
            'C2t3' => 'รหัสตำบล',
            'C2t4' => 'หมู่ที่',
            'C3' => 'รหัส รพ.สต./ศูนย์สุขภาพ (ระบุรหัส)',
            'C6t1' => 'เขตที่อยู่อาศัย',
            
            // ประเภทผู้ให้สัมภาษณ์
            'S3' => 'ประเภทผู้ให้สัมภาษณ์',
            'S4' => 'ประเภทตัวสำรอง',
            'S5' => 'ประเภทตัวสำรอง (แทนภายในหมู่บ้าน)',
            'S6' => 'ประเภทตัวสำรอง (แทนภายนอกหมู่บ้าน)',
            'S7' => 'รหัสประจำตัวบุคคล',
            
            // ประวัติการให้ข้อมูล
            'His' => 'ประวัติการให้ข้อมูล (ตอบได้มากกว่า 1 รายการ)',
            
            // คำชี้แจงโครงการสำรวจและการรับรองการให้ข้อมูล
            'Consent' => 'คำชี้แจงโครงการสำรวจและการรับรองการให้ข้อมูล',
            'PDPA' => 'เอกสารการยินยอมการให้และเปิดเผยข้อมูลส่วนบุคคล',
            
            // เอกสารการยินยอม
            'Consent_Project' => 'คำชี้แจงโครงการสำรวจและรับรองการให้ข้อมูล',
            'Consent_PDPA' => 'เอกสารการยินยอมการให้และเปิดเผยข้อมูลส่วนบุคคล โดยได้รับความยินยอมจากเจ้าของข้อมูล (สำหรับผู้ให้สัมภาษณ์)',
            
            // ข้อมูลผู้ให้ข้อมูล
            'S1' => 'ประเภทการให้ข้อมูล',
            'C4' => 'คำนำหน้าชื่อผู้ให้สัมภาษณ์ข้อมูล',
            'C4t1' => 'ชื่อ - สกุล ผู้ให้สัมภาษณ์ข้อมูล',
            'C5' => 'ช่องทางการติดต่อ',
            'C5s1' => 'ช่องทางการติดต่อ',
            'C6' => 'ที่อยู่ปัจจุบัน บ้านเลขที่',
            
            // พฤติกรรมด้านกิจกรรมทางกายและพฤติกรรมเนือยนิ่ง
            'PA1tt1' => 'ส่วนที่ 2 กิจกรรมทางกาย ข้อคำถามในส่วนต่อไปนี้ จะขอสอบถามข้อมูลเกี่ยวกับเวลาที่ท่านใช้ทำกิจกรรมทางกายประเภทต่างๆ ไม่ว่าจะเป็นการเคลื่อนไหวร่างกายในกิจกรรมการทำงาน การเดินทางจากสถานที่หนึ่งไปยังอีกสถานที่หนึ่งด้วยเท้า หรือการทำกิจกรรมนันทนาการ กิจกรรมยามว่างที่เป็นการเคลื่อนไหว การออกกำลังกาย หรือการเล่นกีฬา  ขอความกรุณาในการตอบคำถามต่อไปนี้ แม้ว่าท่านจะคิดว่าตัวท่านเองเป็นผู้ที่ไม่ค่อยได้เคลื่อนไหว ไม่กระฉับกระเฉง หรือไม่ค่อยได้ปฏิบัติกิจกรรมทางกายก็ตาม โดยในการพิจารณานั้น ขอให้ท่านนึกถึงกิจกรรมที่ท่านได้มีการเคลื่อนไหวร่างกายในอิริยาบถต่างๆ ในการทำกิจกรรมตามที่ได้อธิบายข้างต้น โดยนับรวมเวลาที่เป็นการปฏิบัติ ทั้งในลักษณะต่อเนื่องในระยะเวลานาน เช่น ทำต่อเนื่องตั้งแต่ 10 นาที หรือ 30 นาที หรือนานกว่านั้น และการปฏิบัติแบบชั่วครู่ เช่น ทำต่อเนื่อง 3 ถึง 5 นาที หลักสำคัญคือ เมื่อปฏิบัติแล้วท่านรู้สึกถึงระดับความหนักและเหนื่อยหอบตรงตามระดับความหนักนั้น ๆ อย่างไรก็ดีการสอบถามข้อมูลในส่วนนี้จะยังไม่รวมถึงกิจกรรมที่ท่านทำชั่วครั้งคราวเพื่อเปลี่ยนอิริยาบถ ลุกเดินเพื่อเปลี่ยนตำแหน่งในการทำกิจกรรม เช่น เปลี่ยนอิริยาบถจากการนั่ง นั่งทำงาน หรือนอนเอนหลังนาน ๆ การลุกไปหยิบของในบ้านหรือสถานที่ทำงาน เป็นต้น   ส่วนที่ 2.1 กิจกรรมทางกายในการทำงาน ระดับหนัก 12.1 กิจกรรมทางกายในการทำงาน ระดับหนัก        อันดับแรก อยากให้ท่านนึกถึงเวลาที่ใช้สำหรับการมีกิจกรรมการทำงานในช่วง 1 ปีที่ผ่านมา อันประกอบด้วย การทำงานต่างๆ ทั้งที่ได้รับหรือไม่ได้รับค่าจ้าง การศึกษา/ฝึกอบรม, งานบ้าน/กิจกรรมในครัวเรือน, การทำงานเกษตรกรรม, การเพาะปลูกและเก็บเกี่ยว, การประมง, และการหางาน เป็นต้น     งานที่ท่านทำเกี่ยวข้องกับกิจกรรมทางกายระดับหนักที่ต้องเคลื่อนไหว ออกแรง หรือใช้พละกำลังของร่างกายอย่างหนัก จนทำให้หายใจแรง อัตราการเต้นของหัวใจเต้นเร็วขึ้นอย่างมาก จนรู้สึกเหนื่อยหอบ พูดไม่จบประโยค เช่น การยกหรือแบกของหนักๆ การขุดดิน หรืองานก่อสร้าง บ้างหรือไม่ ',
            'PA2tt1' => 'โดยปกติ ใน 1 สัปดาห์ ท่านทำงานที่ถือเป็นกิจกรรมทางกายระดับหนักนี้ กี่วัน ',
            'PA3tt1' => 'โดยปกติ ใน 1 วัน ท่านใช้เวลานานเท่าไร สำหรับการทำงานที่ถือเป็นกิจกรรมทางกายระดับหนักนี้',
            'PA4t2' => 'ส่วนที่ 2.2 กิจกรรมทางกายในการทำงาน ระดับปานกลาง 12.2 กิจกรรมทางกายในการทำงาน ระดับปานกลาง ในส่วนนี้จะเป็นการสอบถามถึงกิจกรรมทางกายระดับปานกลาง (เฉพาะที่เป็นกิจกรรมทางกายในการทำงานบ้าน งานอาชีพ) งานที่ท่านทำเกี่ยวข้องกับกิจกรรมทางกายระดับปานกลาง ซึ่งทำให้หายใจเร็วขึ้นพอควร แต่ไม่ถึงกับมีอาการหอบ บ้างหรือไม่',
            'PA5t2' => 'โดยปกติ ใน 1 สัปดาห์ ท่านทำงานที่ถือเป็นกิจกรรมทางกายระดับปานกลางนี้ กี่วัน   ',
            'PA6t2' => 'โดยปกติ ใน 1 วัน ท่านใช้เวลานานเท่าไร สำหรับการทำงานที่ถือเป็นกิจกรรมทางกายระดับปานกลางนี้  ',
            'PA7t2' => 'ส่วนที่ 2.3 กิจกรรมทางกายในการสัญจรด้วยเท้า 13. กิจกรรมทางกายในเดินทางสัญจรด้วยการเดิน หรือปั่นจักรยาน   กิจกรรมทางกายในการเดินทางจากที่หนึ่งไปยังอีกที่หนึ่ง คำถามที่จะถามต่อไปนี้ จะไม่รวมถึงกิจกรรมทางกายประเภทการทำงานตามที่ท่านได้กล่าวถึงมาแล้วในส่วนที่ผ่านมา      ในส่วนนี้ จะขอสอบถามข้อมูลเกี่ยวกับการเดินทางจากที่หนึ่งไปยังอีกที่หนึ่งด้วยการเดินหรือการปั่นจักรยาน ที่ท่านทำโดยปกติ เช่น การเดินทางไปทำงาน การเดินทางเพื่อไปจับจ่ายใช้สอย/ซื้อเครื่องใช้ต่างๆ ไปตลาด ไปทำบุญ หรือไปศาสนสถาน เป็นต้น ท่านเดินหรือปั่นจักรยานจากที่หนึ่งไปยังอีกที่หนึ่ง บ้างหรือไม่',
            'PA8t2' => 'โดยปกติ ใน 1 สัปดาห์  ท่านเดินหรือปั่นจักรยานจากที่หนึ่งไปยังอีกที่หนึ่ง กี่วัน (ไม่รวมการเดินหรือปั่นจักรยานที่มีวัตถุประสงค์หลักเพื่อการออกกำลังกายหรือนันทนาการ)',
            'PA9t2' => 'โดยปกติ ใน 1 วัน ท่านใช้เวลานานเท่าไร สำหรับการเดินหรือปั่นจักรยานจากที่หนึ่งไปยังอีกที่หนึ่ง',
            'PA10t2' => 'ส่วนที่ 2.4 กิจกรรมทางกายนันทนาการเพื่อความผ่อนคลาย การออกกำลังกาย หรือเล่นกีฬา ระดับหนัก 14.1 กิจกรรมทางกายเพื่อนันทนาการ/กิจกรรมยามว่างเพื่อความผ่อนคลาย ระดับหนัก คำถามที่จะถามต่อไปนี้ จะไม่รวมถึงกิจกรรมทางกายประเภทการทำงานและการเดินทางต่างๆ ตามที่ท่านได้กล่าวถึงมาแล้ว      สำหรับส่วนนี้ จะขอสอบถามข้อมูลเกี่ยวกับการออกกำลังกายและเล่นกีฬาประเภทต่างๆ การเล่นฟิตเนส การเต้นรำ และกิจกรรมนันทนาการ/กิจกรรมยามว่างเพื่อความผ่อนคลายที่ท่านปฏิบัติในเวลาว่างจากการทำงาน      ท่านเล่นกีฬา ออกกำลังกาย หรือทำกิจกรรมนันทนาการ/กิจกรรมยามว่างระดับหนัก จนทำให้หายใจแรง อัตราการเต้นของ หัวใจเต้นเร็วขึ้นอย่างมาก จนมีอาการรู้สึกเหนื่อยหอบ พูดไม่จบประโยค บ้างหรือไม่',
            'PA11t2' => 'โดยปกติ ใน 1 สัปดาห์ ท่านออกกำลังกายหรือเล่นกีฬา หรือทำกิจกรรมนันทนาการ/กิจกรรมยามว่างระดับหนักนี้ กี่วัน   ',
            'PA12t2' => 'โดยปกติ ใน 1 วัน ท่านใช้เวลานานเท่าไร สำหรับการออกกำลังกาย หรือเล่นกีฬา หรือ ทำกิจกรรมนันทนาการ/กิจกรรมยามว่างระดับหนัก   ',
            'PA13t2' => 'ส่วนที่ 2.5 กิจกรรมทางกายนันทนาการเพื่อความผ่อนคลาย การออกกำลังกาย หรือเล่นกีฬา ระดับปานกลาง   14.2 กิจกรรมทางกายเพื่อนันทนาการ/กิจกรรมยามว่างเพื่อความผ่อนคลาย ระดับปานกลาง        ท่านเล่นกีฬา ออกกำลังกาย หรือทำกิจกรรมนันทนาการ/กิจกรรมยามว่างระดับปานกลาง ซึ่งทำให้หายใจเร็วขึ้นพอควร แต่ไม่ถึงกับมีอาการหอบ บ้างหรือไม่',
            'PA14t2' => 'โดยปกติ ใน 1 สัปดาห์ ท่านออกกำลังกายหรือเล่นกีฬา หรือทำกิจกรรมนันทนาการ/กิจกรรมยามว่างระดับปานกลางนี้ กี่วัน  ',
            'PA15t2' => 'โดยปกติ ใน 1 วัน ท่านใช้เวลานานเท่าไร สำหรับการออกกำลังกาย หรือเล่นกีฬา หรือทำกิจกรรมนันทนาการ/กิจกรรมยามว่างระดับปานกลาง  ',
            'PASummary' => 'สรุปข้อมูล เวลารวมพฤติกรรมเนือยนิ่ง',
            
            // ส่วนที่ 3 : พฤติกรรมการใช้เวลาเกี่ยวกับการเคลื่อนไหวตลอด 24 ชั่วโมง
            'SB01' => 'นอกเหนือจากการมีกิจกรรมทางกายในระดับปานกลางและระดับหนักที่ท่านให้ข้อมูลข้างต้นแล้ว ในส่วนนี้ของสอบถามกิจวัตรในแต่ละวันที่ท่านท่าเป็นปกติว่าท่านมีพฤติกรรมเหล่านี้ อันประกอบด้วย การนอนหลับ พฤติกรรมเนือยนิ่ง รวมถึงพฤติกรรมการเคลื่อนไหวร่างกายในระดับเบา ในช่วงเวลาใดบ้าง (โปรดระบุระยะเวลาเป็นนาที)',
            'TimeSummary' => 'สรุปข้อมูลตารางพฤติกรรมการใช้เวลาเกี่ยวกับการเคลื่อนไหวตลอด 24 ชั่วโมง',
            
            // ตารางบันทึกพฤติกรรมตลอด 24 ชั่วโมง - หัวข้อคอลัมน์
            'Q15_Sleep' => 'การนอนหลับ',
            'Q15_SedentaryNoScreen' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ',
            'Q15_SedentaryScreen' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ',
            'Q15_LightPA' => 'กิจกรรมทางกายระดับเบา',
            'Q15_Total' => 'ทั้งหมด',
            
            // สรุปข้อมูลพฤติกรรม
            'Q15_TotalTime' => 'ผลรวมเวลา ทั้งสิ้น',
            'Q15_TotalSleep' => 'การนอนหลับ (Sleep) รวมทั้งสิ้น',
            'Q15_TotalSBNoScreen' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ (SB) รวมทั้งสิ้น',
            'Q15_TotalSBScreen' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ (SB) รวมทั้งสิ้น',
            'Q15_TotalLPA' => 'กิจกรรมทางกายระดับเบา (LPA) รวมทั้งสิ้น',
            
            // ช่วงเวลาตลอด 24 ชั่วโมง
            'Q15_0000' => '00.00-00.59 น.',
            'Q15_0100' => '01.00-01.59 น.',
            'Q15_0200' => '02.00-02.59 น.',
            'Q15_0300' => '03.00-03.59 น.',
            'Q15_0400' => '04.00-04.59 น.',
            'Q15_0500' => '05.00-05.59 น.',
            'Q15_0600' => '06.00-06.59 น.',
            'Q15_0700' => '07.00-07.59 น.',
            'Q15_0800' => '08.00-08.59 น.',
            'Q15_0900' => '09.00-09.59 น.',
            'Q15_1000' => '10.00-10.59 น.',
            'Q15_1100' => '11.00-11.59 น.',
            'Q15_1200' => '12.00-12.59 น.',
            'Q15_1300' => '13.00-13.59 น.',
            'Q15_1400' => '14.00-14.59 น.',
            'Q15_1500' => '15.00-15.59 น.',
            'Q15_1600' => '16.00-16.59 น.',
            'Q15_1700' => '17.00-17.59 น.',
            'Q15_1800' => '18.00-18.59 น.',
            'Q15_1900' => '19.00-19.59 น.',
            'Q15_2000' => '20.00-20.59 น.',
            'Q15_2100' => '21.00-21.59 น.',
            'Q15_2200' => '22.00-22.59 น.',
            'Q15_2300' => '23.00-23.59 น.',
            
            // TimeSummary - รหัส 5801_X_Y สำหรับการตรวจสอบข้อมูลตลอด 24 ชั่วโมง
            // รูปแบบ: 5801_ชั่วโมง_ประเภทกิจกรรม.NAOK
            // ชั่วโมง: 1-24 (1=00.00-00.59, 2=01.00-01.59, ..., 24=23.00-23.59)
            // ประเภทกิจกรรม: 58001=การนอนหลับ, 58002=พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ, 58003=พฤติกรรมเนือยนิ่งใช้หน้าจอ, LPA=กิจกรรมทางกายระดับเบา
            
            // การนอนหลับ (Sleep) - รหัส 58001
            '5801_1_58001' => 'การนอนหลับ 00.00-00.59 น.',
            '5801_2_58001' => 'การนอนหลับ 01.00-01.59 น.',
            '5801_3_58001' => 'การนอนหลับ 02.00-02.59 น.',
            '5801_4_58001' => 'การนอนหลับ 03.00-03.59 น.',
            '5801_5_58001' => 'การนอนหลับ 04.00-04.59 น.',
            '5801_6_58001' => 'การนอนหลับ 05.00-05.59 น.',
            '5801_7_58001' => 'การนอนหลับ 06.00-06.59 น.',
            '5801_8_58001' => 'การนอนหลับ 07.00-07.59 น.',
            '5801_9_58001' => 'การนอนหลับ 08.00-08.59 น.',
            '5801_10_58001' => 'การนอนหลับ 09.00-09.59 น.',
            '5801_11_58001' => 'การนอนหลับ 10.00-10.59 น.',
            '5801_12_58001' => 'การนอนหลับ 11.00-11.59 น.',
            '5801_13_58001' => 'การนอนหลับ 12.00-12.59 น.',
            '5801_14_58001' => 'การนอนหลับ 13.00-13.59 น.',
            '5801_15_58001' => 'การนอนหลับ 14.00-14.59 น.',
            '5801_16_58001' => 'การนอนหลับ 15.00-15.59 น.',
            '5801_17_58001' => 'การนอนหลับ 16.00-16.59 น.',
            '5801_18_58001' => 'การนอนหลับ 17.00-17.59 น.',
            '5801_19_58001' => 'การนอนหลับ 18.00-18.59 น.',
            '5801_20_58001' => 'การนอนหลับ 19.00-19.59 น.',
            '5801_21_58001' => 'การนอนหลับ 20.00-20.59 น.',
            '5801_22_58001' => 'การนอนหลับ 21.00-21.59 น.',
            '5801_23_58001' => 'การนอนหลับ 22.00-22.59 น.',
            '5801_24_58001' => 'การนอนหลับ 23.00-23.59 น.',
            
            // พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ (Sedentary Behavior without Screen) - รหัส 58002
            '5801_1_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 00.00-00.59 น.',
            '5801_2_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 01.00-01.59 น.',
            '5801_3_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 02.00-02.59 น.',
            '5801_4_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 03.00-03.59 น.',
            '5801_5_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 04.00-04.59 น.',
            '5801_6_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 05.00-05.59 น.',
            '5801_7_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 06.00-06.59 น.',
            '5801_8_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 07.00-07.59 น.',
            '5801_9_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 08.00-08.59 น.',
            '5801_10_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 09.00-09.59 น.',
            '5801_11_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 10.00-10.59 น.',
            '5801_12_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 11.00-11.59 น.',
            '5801_13_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 12.00-12.59 น.',
            '5801_14_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 13.00-13.59 น.',
            '5801_15_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 14.00-14.59 น.',
            '5801_16_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 15.00-15.59 น.',
            '5801_17_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 16.00-16.59 น.',
            '5801_18_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 17.00-17.59 น.',
            '5801_19_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 18.00-18.59 น.',
            '5801_20_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 19.00-19.59 น.',
            '5801_21_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 20.00-20.59 น.',
            '5801_22_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 21.00-21.59 น.',
            '5801_23_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 22.00-22.59 น.',
            '5801_24_58002' => 'พฤติกรรมเนือยนิ่งไม่ใช้หน้าจอ 23.00-23.59 น.',
            
            // พฤติกรรมเนือยนิ่งใช้หน้าจอ (Sedentary Behavior with Screen) - รหัส 58003
            '5801_1_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 00.00-00.59 น.',
            '5801_2_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 01.00-01.59 น.',
            '5801_3_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 02.00-02.59 น.',
            '5801_4_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 03.00-03.59 น.',
            '5801_5_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 04.00-04.59 น.',
            '5801_6_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 05.00-05.59 น.',
            '5801_7_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 06.00-06.59 น.',
            '5801_8_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 07.00-07.59 น.',
            '5801_9_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 08.00-08.59 น.',
            '5801_10_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 09.00-09.59 น.',
            '5801_11_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 10.00-10.59 น.',
            '5801_12_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 11.00-11.59 น.',
            '5801_13_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 12.00-12.59 น.',
            '5801_14_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 13.00-13.59 น.',
            '5801_15_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 14.00-14.59 น.',
            '5801_16_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 15.00-15.59 น.',
            '5801_17_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 16.00-16.59 น.',
            '5801_18_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 17.00-17.59 น.',
            '5801_19_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 18.00-18.59 น.',
            '5801_20_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 19.00-19.59 น.',
            '5801_21_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 20.00-20.59 น.',
            '5801_22_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 21.00-21.59 น.',
            '5801_23_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 22.00-22.59 น.',
            '5801_24_58003' => 'พฤติกรรมเนือยนิ่งใช้หน้าจอ 23.00-23.59 น.',
            
            // กิจกรรมทางกายระดับเบา (Light Physical Activity) - รหัส LPA
            '5801_1_LPA' => 'กิจกรรมทางกายระดับเบา 00.00-00.59 น.',
            '5801_2_LPA' => 'กิจกรรมทางกายระดับเบา 01.00-01.59 น.',
            '5801_3_LPA' => 'กิจกรรมทางกายระดับเบา 02.00-02.59 น.',
            '5801_4_LPA' => 'กิจกรรมทางกายระดับเบา 03.00-03.59 น.',
            '5801_5_LPA' => 'กิจกรรมทางกายระดับเบา 04.00-04.59 น.',
            '5801_6_LPA' => 'กิจกรรมทางกายระดับเบา 05.00-05.59 น.',
            '5801_7_LPA' => 'กิจกรรมทางกายระดับเบา 06.00-06.59 น.',
            '5801_8_LPA' => 'กิจกรรมทางกายระดับเบา 07.00-07.59 น.',
            '5801_9_LPA' => 'กิจกรรมทางกายระดับเบา 08.00-08.59 น.',
            '5801_10_LPA' => 'กิจกรรมทางกายระดับเบา 09.00-09.59 น.',
            '5801_11_LPA' => 'กิจกรรมทางกายระดับเบา 10.00-10.59 น.',
            '5801_12_LPA' => 'กิจกรรมทางกายระดับเบา 11.00-11.59 น.',
            '5801_13_LPA' => 'กิจกรรมทางกายระดับเบา 12.00-12.59 น.',
            '5801_14_LPA' => 'กิจกรรมทางกายระดับเบา 13.00-13.59 น.',
            '5801_15_LPA' => 'กิจกรรมทางกายระดับเบา 14.00-14.59 น.',
            '5801_16_LPA' => 'กิจกรรมทางกายระดับเบา 15.00-15.59 น.',
            '5801_17_LPA' => 'กิจกรรมทางกายระดับเบา 16.00-16.59 น.',
            '5801_18_LPA' => 'กิจกรรมทางกายระดับเบา 17.00-17.59 น.',
            '5801_19_LPA' => 'กิจกรรมทางกายระดับเบา 18.00-18.59 น.',
            '5801_20_LPA' => 'กิจกรรมทางกายระดับเบา 19.00-19.59 น.',
            '5801_21_LPA' => 'กิจกรรมทางกายระดับเบา 20.00-20.59 น.',
            '5801_22_LPA' => 'กิจกรรมทางกายระดับเบา 21.00-21.59 น.',
            '5801_23_LPA' => 'กิจกรรมทางกายระดับเบา 22.00-22.59 น.',
            '5801_24_LPA' => 'กิจกรรมทางกายระดับเบา 23.00-23.59 น.',
            
            // คำถามหลัก
            'Q1' => 'คำชี้แจง: ข้อมูลในส่วนนี้ขอให้พนักงานสัมภาษณ์ดูข้อมูลจากฐานข้อมูลที่มีอยู่แล้วตรวจทานกับผู้ให้ข้อมูลให้เป็นปัจจุบัน 1. เพศ',
            'Q1s1' => '1.1 โปรดระบุเพศสภาวะของท่าน',
            'Q2' => '2. อายุเต็มปี (อายุเมื่อครบรอบวันเกิดครั้งล่าสุด)',
            'Q3' => '3. สถานภาพสมรส (สถานภาพตามกฎหมาย)',
            'Q4' => '4. การศึกษาสูงสุด ณ ปัจจุบัน',
            'Q5' => '5. อาชีพหลัก',
            'Q5s1' => 'รับจ้างทั่วไป ระบุ',
            'Q6' => '6. ปัจจุบันรายได้เฉลี่ยต่อเดือนของท่าน',
            'Q6s1' => 'โปรดระบุ',
            'Q7' => 'ข้อมูลร่างกาย',
            'Q8' => 'ที่อยู่อาศัย',
            'Q9' => 'การเดินทาง',
            'Q10' => '10. สุขภาพ',
            'Q10s1[D5_SQ001]' => '10.1 เพศ',
            'Q10s1[D5_SQ002]' => '10.2 น้ำหนัก (กรัม)',
            'Q10s1[D5_SQ003]' => '10.3 ส่วนสูง (เซนติเมตร)',
            'Q11' => '11. การออกกำลังกาย',
            'Q12' => '12. การบริโภคอาหาร',
            'Q13' => 'การพักผ่อน',
            'Q14' => 'ความเครียด',
            'Q15' => 'ความพึงพอใจ',
            'Q16' => 'ความต้องการ',
            'Q17' => 'ข้อเสนอแนะ',
            'Q18' => 'ข้อมูลเพิ่มเติม',
            'Q19' => 'การติดต่อ',
            'Q20' => 'อื่นๆ',
            'Q21' => 'ข้อมูลเพิ่มเติม 1',
            'Q22' => 'ข้อมูลเพิ่มเติม 2',
            'Q23' => 'ข้อมูลเพิ่มเติม 3',
            'Q24' => 'ข้อมูลเพิ่มเติม 4',
            'Q25' => 'ข้อมูลเพิ่มเติม 5',
            
            // Subquestions
            'Q1s1' => 'เพศสภาวะ',
            'Q2s1' => 'อายุเต็มปี',
            'Q3s1' => 'สถานภาพสมรส',
            'Q4s1' => 'การศึกษาสูงสุด',
            'Q5s1' => 'อาชีพหลัก - รับจ้างทั่วไป',
            'Q6s1' => 'รายได้เฉลี่ยต่อเดือน - โปรดระบุ',
            'Q7s1' => '1. หน้าที่หลักปัจจุบันของท่าน ระบุ',
            'Q7s2' => '2. สถานะปัจจุบันของท่าน ระบุ',
            'Q7s3' => '3. รอบเอวปัจจุบันของท่าน ระบุ',
            'Q7s4' => 'การชั่งวัดน้ำหนัก ส่วนสูง',
            'Q7s5' => 'การชั่งวัดน้ำหนัก ส่วนสูง (ต่อ)',
            'Q12s1' => 'การบริโภคอาหาร - อาหารหลัก',
            'Q12s2' => 'การบริโภคอาหาร - อาหารเสริม',
            
            // Mapping สำหรับรูปแบบพิเศษ
            'Q12s1[2]' => '12.1 อาหารหลัก (รายการที่ 2)',
            'Q12s1[5]' => '12.1 อาหารหลัก (รายการที่ 5)',
            'Q12s1[16]' => '12.1 อาหารหลัก (รายการที่ 16)',
            'Q12s2[1]' => 'การบริโภคอาหาร - อาหารเสริม (รายการที่ 1)',
            'Q16[2]' => 'ความต้องการ - รูปร่างที่สวยงามหุ่นดี',
            'Q16[3]' => 'ความต้องการ - สุขภาพแข็งแรงป้องกันการเจ็บป่วย',
            'Q16[4]' => 'ความต้องการ - บรรเทาโรคและอาการเจ็บป่วย',
            'Q16s1' => 'ความต้องการ - ด้านสุขภาพ',
            'Q16s2' => 'ความต้องการ - ด้านการศึกษา',
            'Q16s3' => 'ความต้องการ - ด้านอาชีพ',
            'Q16s4' => 'ความต้องการ - ด้านสังคม',
            
            // คำถามพิเศษ
            'C5s1' => 'ช่องทางการติดต่อ',
            'C6' => 'ที่อยู่ปัจจุบัน',
            'C7' => 'ข้อมูลติดต่อ',
            'C8' => 'ข้อมูลเพิ่มเติม',
            
            // คำถามทั่วไป
            'GENDER' => 'เพศ',
            'AGE' => 'อายุ',
            'EDUCATION' => 'การศึกษา',
            'OCCUPATION' => 'อาชีพ',
            'INCOME' => 'รายได้',
            'MARITAL' => 'สถานภาพสมรส',
            'HEALTH' => 'สุขภาพ',
            'WEIGHT' => 'น้ำหนัก',
            'HEIGHT' => 'ส่วนสูง',
            'WAIST' => 'รอบเอว'
        );
        
        // ลองหาใน map โดยตรง
        $clean_code = $this->clean_question_code($code);
        if (isset($question_labels[$clean_code])) {
            return $question_labels[$clean_code];
        }
        
        // ลองหาแบบ partial match สำหรับ subquestions
        foreach ($question_labels as $pattern => $label) {
            if (strpos($clean_code, $pattern) === 0) {
                // ถ้าเป็น subquestion ให้เพิ่มรายละเอียด
                if (preg_match('/\[([^\]]+)\]/', $code, $matches)) {
                    $sub_detail = $matches[1];
                    return $label . ' - ' . $sub_detail;
                }
                return $label;
            }
        }
        
        // ถ้าไม่พบ ให้สร้าง label จาก code
        $display_code = $this->clean_question_code($code);
        if (preg_match('/^Q(\d+)/', $display_code, $matches)) {
            $qnum = $matches[1];
            return "คำถามที่ " . $qnum;
        }
        
        return 'คำถาม ' . $display_code;
    }
}

// Initialize
new TPAK_DQ_Survey_Renderer();