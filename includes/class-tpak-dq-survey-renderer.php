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
                
                .tpak-subquestion-label {
                    font-weight: normal;
                    color: #7f8c8d;
                    font-size: 13px;
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
                error_log('TPAK Debug: Using saved survey structure for survey ' . $survey_id);
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
                        $question_groups = $this->group_questions_by_base($response_data);
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
                    $this->render_grouped_array_answers($base_code, $items, $question_info);
                } else {
                    $this->render_single_answer(reset($items), $base_code, $question_info);
                }
                ?>
            </div>
        </div>
        <?php
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
                            // แยกส่วนประกอบของ key
                            $display_key = str_replace($base_code, '', $key);
                            $display_key = trim($display_key, '[]_');
                            
                            // พยายามแยก subquestion code และ answer code
                            $parts = explode('_', $display_key);
                            $sq_code = $parts[0];
                            $ans_code = isset($parts[1]) ? $parts[1] : '';
                            
                            // แสดง subquestion text
                            $displayed = false;
                            if ($question_info && isset($question_info['subquestions'])) {
                                // ลองหา subquestion ด้วย code เต็ม
                                if (isset($question_info['subquestions'][$display_key])) {
                                    echo '<span class="tpak-subquestion-label">' . 
                                         esc_html($question_info['subquestions'][$display_key]['question']) . 
                                         '</span>';
                                    $displayed = true;
                                } 
                                // ลองหาด้วย sq_code
                                elseif (isset($question_info['subquestions'][$sq_code])) {
                                    echo '<span class="tpak-subquestion-label">' . 
                                         esc_html($question_info['subquestions'][$sq_code]['question']) . 
                                         '</span>';
                                    if ($ans_code) {
                                        echo ' <small>(' . esc_html($ans_code) . ')</small>';
                                    }
                                    $displayed = true;
                                }
                            }
                            
                            if (!$displayed) {
                                // ถ้าไม่พบ subquestion text ให้แสดง code
                                echo esc_html($display_key ?: $base_code);
                            }
                            ?>
                        </td>
                        <td>
                            <strong>
                                <?php 
                                // แสดงคำตอบ
                                if ($is_multiple_choice && $question_info && isset($question_info['answer_options'])) {
                                    // ลองหา answer text จาก answer options
                                    $answer_text = '';
                                    
                                    // ลองหาด้วย value โดยตรง
                                    if (isset($question_info['answer_options'][$value])) {
                                        $answer_text = $question_info['answer_options'][$value];
                                    }
                                    // ลองหาด้วย combination key
                                    elseif (isset($question_info['answer_options'][$display_key . '_' . $value])) {
                                        $answer_text = $question_info['answer_options'][$display_key . '_' . $value];
                                    }
                                    // ลองหาด้วย sq_code + value
                                    elseif (isset($question_info['answer_options'][$sq_code . '_' . $value])) {
                                        $answer_text = $question_info['answer_options'][$sq_code . '_' . $value];
                                    }
                                    
                                    if ($answer_text) {
                                        echo esc_html($answer_text);
                                    } else {
                                        // ถ้าไม่พบ answer text ให้ format ตามปกติ
                                        echo esc_html($this->format_answer_value($value, $base_code, $question_info));
                                    }
                                } else {
                                    // ไม่ใช่ multiple choice หรือไม่มี answer options
                                    echo esc_html($this->format_answer_value($value, $base_code, $question_info));
                                }
                                ?>
                            </strong>
                        </td>
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
        if (empty($value) || $value === 'N') {
            echo '<div class="tpak-answer-empty">ไม่มีคำตอบ</div>';
            return;
        }
        
        echo '<div class="tpak-answer-value">';
        echo nl2br(esc_html($this->format_answer_value($value, $question_code, $question_info)));
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
        
        if (isset($value_map[$value])) {
            return $value_map[$value];
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
}

// Initialize
new TPAK_DQ_Survey_Renderer();