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
    
    public function __construct() {
        // Hook สำหรับ AJAX
        add_action('wp_ajax_tpak_refresh_survey_structure', array($this, 'ajax_refresh_survey_structure'));
        
        // เพิ่ม Meta Box ใหม่
        add_action('add_meta_boxes', array($this, 'add_survey_preview_metabox'), 15);
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
            <style>
                .tpak-survey-preview {
                    background: #f8f9fa;
                    border: 1px solid #e3e4e8;
                    border-radius: 8px;
                    padding: 20px;
                    margin: 10px 0;
                }
                
                .tpak-survey-header {
                    background: #fff;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                
                .tpak-survey-title {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin: 0 0 10px 0;
                }
                
                .tpak-survey-description {
                    color: #7f8c8d;
                    font-size: 14px;
                    line-height: 1.6;
                }
                
                .tpak-question-group {
                    background: #fff;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 15px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                
                .tpak-group-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #34495e;
                    margin: 0 0 15px 0;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #e3e4e8;
                }
                
                .tpak-question-item {
                    margin-bottom: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 6px;
                    border-left: 4px solid #3498db;
                }
                
                .tpak-question-code {
                    font-size: 11px;
                    color: #95a5a6;
                    font-family: monospace;
                    margin-bottom: 5px;
                }
                
                .tpak-question-text {
                    font-size: 15px;
                    font-weight: 500;
                    color: #2c3e50;
                    margin-bottom: 10px;
                }
                
                .tpak-question-mandatory {
                    color: #e74c3c;
                    font-weight: bold;
                }
                
                .tpak-answer-wrapper {
                    margin-top: 10px;
                    padding: 10px;
                    background: #fff;
                    border-radius: 4px;
                }
                
                .tpak-answer-value {
                    font-size: 14px;
                    color: #27ae60;
                    font-weight: 500;
                }
                
                .tpak-answer-empty {
                    color: #bdc3c7;
                    font-style: italic;
                }
                
                .tpak-answer-options {
                    margin-top: 8px;
                    padding-left: 20px;
                }
                
                .tpak-answer-option {
                    display: flex;
                    align-items: center;
                    margin-bottom: 5px;
                    font-size: 13px;
                    color: #7f8c8d;
                }
                
                .tpak-answer-option.selected {
                    color: #27ae60;
                    font-weight: 500;
                }
                
                .tpak-answer-option input {
                    margin-right: 8px;
                }
                
                .tpak-refresh-button {
                    margin-bottom: 15px;
                }
                
                .tpak-loading {
                    text-align: center;
                    padding: 40px;
                    color: #7f8c8d;
                }
                
                .tpak-loading .spinner {
                    border: 3px solid #f3f3f3;
                    border-top: 3px solid #3498db;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 20px;
                }
                
                .tpak-array-answers {
                    margin-top: 10px;
                }
                
                .tpak-array-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 8px;
                }
                
                .tpak-array-table th,
                .tpak-array-table td {
                    padding: 8px;
                    text-align: left;
                    border: 1px solid #e3e4e8;
                }
                
                .tpak-array-table th {
                    background: #f8f9fa;
                    font-weight: 600;
                    color: #2c3e50;
                }
                
                .tpak-array-table td {
                    background: #fff;
                }
                
                .tpak-array-table tr:nth-child(even) td {
                    background: #f8f9fa;
                }
                
                .tpak-array-table strong {
                    color: #27ae60;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .tpak-question-type {
                    display: inline-block;
                    font-size: 11px;
                    padding: 2px 8px;
                    background: #ecf0f1;
                    color: #7f8c8d;
                    border-radius: 3px;
                    margin-left: 10px;
                }
                
                .tpak-survey-stats {
                    display: flex;
                    gap: 20px;
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #e3e4e8;
                }
                
                .tpak-stat-item {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 13px;
                    color: #7f8c8d;
                }
                
                .tpak-stat-number {
                    font-weight: bold;
                    color: #3498db;
                }
                
                .tpak-info-table {
                    width: 100%;
                    margin-top: 15px;
                }
                
                .tpak-info-table td {
                    padding: 5px;
                }
            </style>
            
            <div class="tpak-survey-actions">
                <button type="button" class="button button-primary tpak-refresh-button" 
                        data-survey-id="<?php echo esc_attr($survey_id); ?>"
                        data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <span class="dashicons dashicons-update"></span>
                    ดึงคำถามจาก LimeSurvey
                </button>
                
                <?php if (!$survey_structure || !is_array($survey_structure)): ?>
                <p class="description">คลิกปุ่มด้านบนเพื่อดึงข้อความคำถามแบบสมบูรณ์จาก LimeSurvey</p>
                <?php endif; ?>
            </div>
            
            <div class="tpak-survey-preview" id="survey-preview-<?php echo $post->ID; ?>">
                <?php 
                if ($survey_structure && is_array($survey_structure)) {
                    $this->render_survey_structure($survey_structure, $response_data);
                } else {
                    // แสดงเฉพาะข้อมูลที่มี
                    $this->render_survey_with_answers($response_data);
                }
                ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Refresh survey structure
            $('.tpak-refresh-button').on('click', function() {
                var button = $(this);
                var surveyId = button.data('survey-id');
                var postId = button.data('post-id');
                var container = $('#survey-preview-' + postId);
                
                button.prop('disabled', true).text('กำลังดึงข้อมูล...');
                container.html('<div class="tpak-loading"><div class="spinner"></div>กำลังดึงคำถามจาก LimeSurvey...</div>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tpak_refresh_survey_structure',
                        survey_id: surveyId,
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce('tpak_survey_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            container.html(response.data.html);
                            button.html('<span class="dashicons dashicons-yes"></span> ดึงข้อมูลสำเร็จ');
                            
                            // Reload page after 2 seconds to show new data
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            container.html('<p class="error">เกิดข้อผิดพลาด: ' + response.data + '</p>');
                            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ดึงคำถามจาก LimeSurvey');
                        }
                    },
                    error: function() {
                        container.html('<p class="error">ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์</p>');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> ดึงคำถามจาก LimeSurvey');
                    }
                });
            });
        });
        </script>
        <?php
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
            require_once TPAK_DQ_PLUGIN_DIR . 'includes/class-tpak-dq-import.php';
            
            $this->api_client = new LimeSurveyAPIClient($api_url, $username, $password);
            $session_key = $this->api_client->get_session_key();
            
            if (!$session_key) {
                return false;
            }
            
            // เรียก API เพื่อดึงข้อมูล survey
            $params = array(
                'method' => 'list_questions',
                'params' => array($session_key, intval($survey_id)),
                'id' => 1
            );
            
            $response = $this->api_client->send_request($params);
            $questions = isset($response['result']) ? $response['result'] : array();
            
            // สร้างโครงสร้างคำถาม
            $structure = array(
                'survey_id' => $survey_id,
                'questions' => array()
            );
            
            if (is_array($questions)) {
                foreach ($questions as $question) {
                    // ดึงรายละเอียดคำถาม
                    $qid = isset($question['qid']) ? $question['qid'] : null;
                    if ($qid) {
                        $detail_params = array(
                            'method' => 'get_question_properties',
                            'params' => array($session_key, intval($qid), null),
                            'id' => 1
                        );
                        
                        $detail_response = $this->api_client->send_request($detail_params);
                        if (isset($detail_response['result'])) {
                            $details = $detail_response['result'];
                            
                            // เก็บข้อมูลคำถาม
                            $code = isset($details['title']) ? $details['title'] : '';
                            if ($code) {
                                $structure['questions'][$code] = array(
                                    'code' => $code,
                                    'question' => isset($details['question']) ? $details['question'] : '',
                                    'type' => isset($details['type']) ? $details['type'] : '',
                                    'mandatory' => isset($details['mandatory']) ? $details['mandatory'] : 'N',
                                    'help' => isset($details['help']) ? $details['help'] : '',
                                    'other' => isset($details['other']) ? $details['other'] : 'N'
                                );
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
     * แสดงโครงสร้างแบบสอบถาม
     */
    private function render_survey_structure($structure, $response_data) {
        if (!is_array($structure) || !isset($structure['questions'])) {
            $this->render_survey_with_answers($response_data);
            return;
        }
        
        ?>
        <div class="tpak-survey-header">
            <h2 class="tpak-survey-title">แบบสอบถาม Survey ID: <?php echo esc_html($structure['survey_id']); ?></h2>
            <div class="tpak-survey-stats">
                <div class="tpak-stat-item">
                    <span class="dashicons dashicons-editor-help"></span>
                    <span>จำนวนคำถาม: <span class="tpak-stat-number"><?php echo count($structure['questions']); ?></span></span>
                </div>
            </div>
        </div>
        
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
                
                // แสดงคำถามและคำตอบทั้งกลุ่ม
                $this->render_question_group($base_code, $group_data, $question_info, $response_data);
            }
            ?>
        </div>
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
                if ($k !== $key && strpos($k, $base_code) === 0 && !isset($processed[$k])) {
                    if (!empty($v) && $v !== 'N') {
                        $groups[$base_code]['items'][$k] = $v;
                        $groups[$base_code]['type'] = 'array';
                        $processed[$k] = true;
                    }
                }
            }
        }
        
        // กรองเฉพาะกลุ่มที่มีข้อมูล
        $filtered_groups = array();
        foreach ($groups as $base_code => $group) {
            if (!empty($group['items'])) {
                $filtered_groups[$base_code] = $group;
            }
        }
        
        return $filtered_groups;
    }
    
    /**
     * แยก base code จาก key
     */
    private function extract_base_code($key) {
        // ตรวจจับ pattern ต่างๆ
        if (preg_match('/^([A-Za-z0-9]+?)(?:\[|_SQ|$)/', $key, $matches)) {
            return $matches[1];
        }
        return $key;
    }
    
    /**
     * แสดงกลุ่มคำถาม
     */
    private function render_question_group($base_code, $group_data, $question_info, $response_data) {
        $items = $group_data['items'];
        $type = $group_data['type'];
        
        ?>
        <div class="tpak-question-item">
            <div class="tpak-question-code">
                <?php echo esc_html($base_code); ?>
                <?php if ($question_info && isset($question_info['type'])): ?>
                    <span class="tpak-question-type"><?php echo $this->get_question_type_label($question_info['type']); ?></span>
                <?php elseif ($type === 'array'): ?>
                    <span class="tpak-question-type">Array</span>
                <?php endif; ?>
            </div>
            
            <div class="tpak-question-text">
                <?php if ($question_info && isset($question_info['mandatory']) && $question_info['mandatory'] == 'Y'): ?>
                    <span class="tpak-question-mandatory">*</span>
                <?php endif; ?>
                
                <?php 
                if ($question_info && !empty($question_info['question'])) {
                    // แสดงคำถามจริง
                    echo nl2br(esc_html(strip_tags($question_info['question'])));
                } else {
                    // ถ้าไม่มีข้อมูลคำถาม แสดง key
                    echo 'คำถาม ' . esc_html($base_code);
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
                    $this->render_grouped_array_answers($base_code, $items);
                } else {
                    $this->render_single_answer(reset($items));
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * แสดงคำตอบแบบ array ที่จัดกลุ่มแล้ว
     */
    private function render_grouped_array_answers($base_code, $items) {
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
                    <?php foreach ($items as $key => $value): ?>
                    <tr>
                        <td>
                            <?php 
                            // แสดง sub-key อย่างเดียว
                            $display_key = str_replace($base_code, '', $key);
                            $display_key = trim($display_key, '[]_');
                            echo esc_html($display_key ?: $base_code); 
                            ?>
                        </td>
                        <td><strong><?php echo esc_html($this->format_answer_value($value)); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
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
            <div class="tpak-question-code">
                <?php echo esc_html($key); ?>
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
                    $this->render_single_answer($value);
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
            if (strpos($k, $base_code) === 0 && !empty($v) && $v !== 'N') {
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
        
        // รวบรวมคำตอบที่เกี่ยวข้อง
        foreach ($response_data as $k => $v) {
            if (strpos($k, $base_code) === 0 && !empty($v) && $v !== 'N') {
                $sub_key = str_replace($base_code, '', $k);
                $sub_key = trim($sub_key, '[]_');
                $answers[$sub_key] = $v;
            }
        }
        
        if (empty($answers)) {
            $this->render_single_answer('');
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
                        <td><strong><?php echo esc_html($this->format_answer_value($value)); ?></strong></td>
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
                // ใช้ render_question_group แทน render_grouped_question
                $this->render_question_group($base_code, $group_data, null, $response_data); 
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
                    $this->render_single_answer(reset($answers));
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * แสดงคำตอบเดี่ยว
     */
    private function render_single_answer($value) {
        if (empty($value) || $value === 'N') {
            echo '<div class="tpak-answer-empty">ไม่มีคำตอบ</div>';
            return;
        }
        
        echo '<div class="tpak-answer-value">';
        echo nl2br(esc_html($this->format_answer_value($value)));
        echo '</div>';
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
    private function format_answer_value($value) {
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
            'A5' => 'ไม่เห็นด้วยอย่างยิ่ง'
        );
        
        if (isset($value_map[$value])) {
            return $value_map[$value];
        }
        
        // ตรวจสอบว่าเป็นวันที่หรือไม่
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) && strtotime($value)) {
            return date_i18n('d/m/Y H:i:s', strtotime($value));
        }
        
        // ตรวจสอบว่าเป็นตัวเลขหรือไม่
        if (is_numeric($value) && strlen($value) > 3) {
            return number_format($value);
        }
        
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
}

// Initialize
new TPAK_DQ_Survey_Renderer();