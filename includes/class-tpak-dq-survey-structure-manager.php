<?php
/**
 * ไฟล์: includes/class-tpak-dq-survey-structure-manager.php
 * จัดการ Survey Structure จาก LimeSurvey
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

$structure = TPAK_DQ_Survey_Structure_Manager::get_survey_structure($survey_id);

if ($structure) {
    // ใช้ saved structure
    $questions = $structure['questions'];
    $answers = $structure['answers'];
    $subquestions = $structure['subquestions'];
}

class TPAK_DQ_Survey_Structure_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_tpak_upload_lss', array($this, 'ajax_upload_lss'));
        add_action('wp_ajax_tpak_get_survey_structure', array($this, 'ajax_get_survey_structure'));
        add_action('wp_ajax_tpak_sync_survey_structure', array($this, 'ajax_sync_survey_structure'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=tpak_verification',
            'Survey Structure',
            'Survey Structure',
            'manage_options',
            'tpak-survey-structure',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>จัดการ Survey Structure</h1>
            
            <div class="tpak-structure-section">
                <h2>วิธีที่ 1: Upload ไฟล์ .lss</h2>
                <form id="tpak-lss-upload-form" enctype="multipart/form-data">
                    <?php wp_nonce_field('tpak_upload_lss', 'tpak_lss_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="survey_id">Survey ID</label></th>
                            <td>
                                <input type="text" name="survey_id" id="survey_id" class="regular-text" required />
                                <p class="description">ระบุ Survey ID ที่ต้องการอัพเดทโครงสร้าง</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="lss_file">ไฟล์ .lss</label></th>
                            <td>
                                <input type="file" name="lss_file" id="lss_file" accept=".lss" required />
                                <p class="description">Export Survey structure จาก LimeSurvey</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">อัพโหลดโครงสร้าง</button>
                    </p>
                </form>
                
                <div id="upload-result" style="display:none;"></div>
            </div>
            
            <hr />
            
            <div class="tpak-structure-section">
                <h2>วิธีที่ 2: Sync จาก LimeSurvey API</h2>
                <p>ดึงโครงสร้างจาก LimeSurvey API โดยตรง</p>
                
                <?php if (class_exists('TPAK_DQ_LimeSurvey_API')): ?>
                    <?php
                    $api = new TPAK_DQ_LimeSurvey_API();
                    $api_url = get_option('tpak_limesurvey_api_url', '');
                    $username = get_option('tpak_limesurvey_username', '');
                    ?>
                    
                    <?php if (!empty($api_url) && !empty($username)): ?>
                        <div class="tpak-api-status">
                            <p><strong>API Status:</strong> 
                                <span class="tpak-status-connected">✅ Connected</span>
                                <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&page=tpak-api-settings'); ?>" class="button button-small">Manage API Settings</a>
                            </p>
                        </div>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="api_survey_id">Survey ID</label></th>
                                <td>
                                    <input type="number" name="api_survey_id" id="api_survey_id" class="regular-text" required />
                                    <p class="description">ระบุ Survey ID ที่ต้องการดึงจาก LimeSurvey API</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <button type="button" class="button button-primary" id="sync-from-api">Sync จาก API</button>
                            <button type="button" class="button button-secondary" id="test-api-connection">Test API Connection</button>
                        </p>
                        
                        <div id="api-sync-result" style="display:none;"></div>
                    <?php else: ?>
                        <div class="tpak-api-status">
                            <p><strong>API Status:</strong> 
                                <span class="tpak-status-disconnected">❌ Not Configured</span>
                            </p>
                            <p>กรุณาตั้งค่า LimeSurvey API ก่อนใช้งาน</p>
                            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification&page=tpak-api-settings'); ?>" class="button button-primary">Configure API Settings</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="tpak-api-status">
                        <p><strong>API Status:</strong> 
                            <span class="tpak-status-error">❌ API Connector Not Available</span>
                        </p>
                        <p>LimeSurvey API Connector ไม่พร้อมใช้งาน</p>
                    </div>
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="sync_survey_id">Survey ID</label></th>
                        <td>
                            <input type="text" id="sync_survey_id" class="regular-text" />
                            <button type="button" class="button" id="sync-structure-btn">Sync โครงสร้าง</button>
                        </td>
                    </tr>
                </table>
                
                <div id="sync-result" style="display:none;"></div>
            </div>
            
            <hr />
            
            <div class="tpak-structure-section">
                <h2>Survey Structures ที่มีอยู่</h2>
                <?php $this->render_existing_structures(); ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Upload LSS file
            $('#tpak-lss-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'tpak_upload_lss');
                
                $('#upload-result').html('<p>กำลังอัพโหลด...</p>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#upload-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $('#upload-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#upload-result').html('<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการอัพโหลด</p></div>');
                    }
                });
            });
            
            // Sync from API
            $('#sync-structure-btn').on('click', function() {
                var surveyId = $('#sync_survey_id').val();
                if (!surveyId) {
                    alert('กรุณาระบุ Survey ID');
                    return;
                }
                
                $('#sync-result').html('<p>กำลัง sync...</p>').show();
                
                $.post(ajaxurl, {
                    action: 'tpak_sync_survey_structure',
                    survey_id: surveyId,
                    nonce: '<?php echo wp_create_nonce('tpak_sync_structure'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#sync-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        location.reload();
                    } else {
                        $('#sync-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Upload LSS file
     */
    public function ajax_upload_lss() {
        // Check nonce
        if (!check_ajax_referer('tpak_upload_lss', 'tpak_lss_nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
        
        if (!$survey_id) {
            wp_send_json_error('Invalid Survey ID');
        }
        
        // Check file upload
        if (!isset($_FILES['lss_file']) || $_FILES['lss_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload failed');
        }
        
        $file = $_FILES['lss_file'];
        
        // Validate file extension
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'lss') {
            wp_send_json_error('กรุณาอัพโหลดไฟล์ .lss เท่านั้น');
        }
        
        // Parse LSS file
        $result = $this->parse_lss_file($file['tmp_name'], $survey_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'อัพโหลดและบันทึกโครงสร้างสำเร็จ',
                'survey_id' => $survey_id
            ));
        } else {
            wp_send_json_error('ไม่สามารถประมวลผลไฟล์ LSS');
        }
    }
    
    /**
     * Parse LSS file (XML format)
     */
    private function parse_lss_file($file_path, $survey_id) {
        // Load XML
        $xml = simplexml_load_file($file_path);
        
        if (!$xml) {
            return false;
        }
        
        $structure = array(
            'survey_id' => $survey_id,
            'groups' => array(),
            'questions' => array(),
            'subquestions' => array(),
            'answers' => array(),
            'attributes' => array()
        );
        
        // Parse groups
        if (isset($xml->groups->rows->row)) {
            foreach ($xml->groups->rows->row as $group) {
                $gid = (string)$group->gid;
                $structure['groups'][$gid] = array(
                    'gid' => $gid,
                    'group_name' => (string)$group->group_name,
                    'group_order' => (int)$group->group_order,
                    'description' => (string)$group->description
                );
            }
        }
        
        // Parse questions
        if (isset($xml->questions->rows->row)) {
            foreach ($xml->questions->rows->row as $question) {
                $qid = (string)$question->qid;
                $parent_qid = (string)$question->parent_qid;
                $gid = (string)$question->gid;
                $type = (string)$question->type;
                $title = (string)$question->title;
                $question_text = (string)$question->question;
                
                if ($parent_qid == '0') {
                    // Main question
                    $structure['questions'][$title] = array(
                        'qid' => $qid,
                        'gid' => $gid,
                        'type' => $type,
                        'title' => $title,
                        'question' => $question_text,
                        'help' => (string)$question->help,
                        'mandatory' => (string)$question->mandatory,
                        'question_order' => (int)$question->question_order,
                        'other' => (string)$question->other
                    );
                } else {
                    // Subquestion
                    // Find parent title
                    $parent_title = $this->find_parent_title($xml->questions->rows->row, $parent_qid);
                    
                    if (!isset($structure['subquestions'][$parent_title])) {
                        $structure['subquestions'][$parent_title] = array();
                    }
                    
                    $structure['subquestions'][$parent_title][$title] = array(
                        'qid' => $qid,
                        'parent_qid' => $parent_qid,
                        'title' => $title,
                        'question' => $question_text,
                        'question_order' => (int)$question->question_order
                    );
                }
            }
        }
        
        // Parse answers
        if (isset($xml->answers->rows->row)) {
            foreach ($xml->answers->rows->row as $answer) {
                $qid = (string)$answer->qid;
                $code = (string)$answer->code;
                
                // Find question title by qid
                $question_title = $this->find_question_title($xml->questions->rows->row, $qid);
                
                if ($question_title) {
                    if (!isset($structure['answers'][$question_title])) {
                        $structure['answers'][$question_title] = array();
                    }
                    
                    $structure['answers'][$question_title][$code] = array(
                        'code' => $code,
                        'answer' => (string)$answer->answer,
                        'sortorder' => (int)$answer->sortorder,
                        'assessment_value' => (int)$answer->assessment_value
                    );
                }
            }
        }
        
        // Parse question attributes
        if (isset($xml->question_attributes->rows->row)) {
            foreach ($xml->question_attributes->rows->row as $attr) {
                $qid = (string)$attr->qid;
                $attribute = (string)$attr->attribute;
                $value = (string)$attr->value;
                
                // Find question title
                $question_title = $this->find_question_title($xml->questions->rows->row, $qid);
                
                if ($question_title) {
                    if (!isset($structure['attributes'][$question_title])) {
                        $structure['attributes'][$question_title] = array();
                    }
                    
                    $structure['attributes'][$question_title][$attribute] = $value;
                }
            }
        }
        
        // Save structure
        return $this->save_survey_structure($survey_id, $structure);
    }
    
    /**
     * Find parent title by qid
     */
    private function find_parent_title($questions, $qid) {
        foreach ($questions as $question) {
            if ((string)$question->qid == $qid) {
                return (string)$question->title;
            }
        }
        return null;
    }
    
    /**
     * Find question title by qid
     */
    private function find_question_title($questions, $qid) {
        foreach ($questions as $question) {
            if ((string)$question->qid == $qid && (string)$question->parent_qid == '0') {
                return (string)$question->title;
            }
        }
        return null;
    }
    
    /**
     * Save survey structure to database
     */
    public function save_survey_structure($survey_id, $structure) {
        $option_name = 'tpak_survey_structure_' . $survey_id;
        $structure['last_updated'] = current_time('mysql');
        
        return update_option($option_name, $structure);
    }
    
    /**
     * Get survey structure from database
     */
    public static function get_survey_structure($survey_id) {
        $option_name = 'tpak_survey_structure_' . $survey_id;
        return get_option($option_name, false);
    }
    
    /**
     * Render existing structures
     */
    private function render_existing_structures() {
        global $wpdb;
        
        // Get all survey structure options
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'tpak_survey_structure_%'
             ORDER BY option_name"
        );
        
        if (empty($options)) {
            echo '<p>ยังไม่มี Survey Structure ที่บันทึกไว้</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Survey ID</th>
                    <th>จำนวนกลุ่ม</th>
                    <th>จำนวนคำถาม</th>
                    <th>อัพเดทล่าสุด</th>
                    <th>การดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($options as $option): ?>
                    <?php
                    $structure = maybe_unserialize($option->option_value);
                    if (!is_array($structure)) continue;
                    
                    $survey_id = str_replace('tpak_survey_structure_', '', $option->option_name);
                    ?>
                    <tr>
                        <td><?php echo esc_html($survey_id); ?></td>
                        <td><?php echo count($structure['groups'] ?? array()); ?></td>
                        <td><?php echo count($structure['questions'] ?? array()); ?></td>
                        <td><?php echo isset($structure['last_updated']) ? date_i18n('d/m/Y H:i', strtotime($structure['last_updated'])) : '-'; ?></td>
                        <td>
                            <button type="button" class="button button-small view-structure" 
                                    data-survey-id="<?php echo esc_attr($survey_id); ?>">
                                ดูโครงสร้าง
                            </button>
                            <button type="button" class="button button-small delete-structure" 
                                    data-survey-id="<?php echo esc_attr($survey_id); ?>">
                                ลบ
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Modal for viewing structure -->
        <div id="structure-modal" style="display:none;">
            <div class="structure-content"></div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // View structure
            $('.view-structure').on('click', function() {
                var surveyId = $(this).data('survey-id');
                
                $.post(ajaxurl, {
                    action: 'tpak_get_survey_structure',
                    survey_id: surveyId,
                    nonce: '<?php echo wp_create_nonce('tpak_get_structure'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Show structure in modal or new window
                        var newWindow = window.open('', 'Survey Structure', 'width=800,height=600');
                        newWindow.document.write('<html><head><title>Survey Structure ' + surveyId + '</title></head><body>');
                        newWindow.document.write('<pre>' + JSON.stringify(response.data, null, 2) + '</pre>');
                        newWindow.document.write('</body></html>');
                    }
                });
            });
            
            // Delete structure
            $('.delete-structure').on('click', function() {
                if (!confirm('ต้องการลบโครงสร้างนี้หรือไม่?')) {
                    return;
                }
                
                var surveyId = $(this).data('survey-id');
                var row = $(this).closest('tr');
                
                // Implementation for delete
                // ...
            });
            
            // Sync from API
            $('#sync-from-api').on('click', function() {
                var surveyId = $('#api_survey_id').val();
                var button = $(this);
                var resultDiv = $('#api-sync-result');
                
                if (!surveyId) {
                    alert('กรุณาระบุ Survey ID');
                    return;
                }
                
                button.prop('disabled', true).text('Syncing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tpak_sync_survey_structure',
                        survey_id: surveyId,
                        nonce: '<?php echo wp_create_nonce('tpak_sync_structure'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            // Reload page after successful sync
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                        resultDiv.show();
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>Failed to sync from API</p></div>');
                        resultDiv.show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Sync จาก API');
                    }
                });
            });
            
            // Test API connection
            $('#test-api-connection').on('click', function() {
                var button = $(this);
                var resultDiv = $('#api-sync-result');
                
                button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tpak_test_api_connection',
                        nonce: '<?php echo wp_create_nonce('tpak_test_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                        resultDiv.show();
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>API connection test failed</p></div>');
                        resultDiv.show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test API Connection');
                    }
                });
            });
        });
        </script>
        
        <style>
        .tpak-structure-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 8px;
        }
        
        .tpak-api-status {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #007cba;
        }
        
        .tpak-status-connected {
            color: #28a745;
            font-weight: 600;
        }
        
        .tpak-status-disconnected {
            color: #dc3545;
            font-weight: 600;
        }
        
        .tpak-status-error {
            color: #ffc107;
            font-weight: 600;
        }
        
        #api-sync-result {
            margin-top: 15px;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Get survey structure
     */
    public function ajax_get_survey_structure() {
        // Check nonce
        if (!check_ajax_referer('tpak_get_structure', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
        
        if (!$survey_id) {
            wp_send_json_error('Invalid Survey ID');
        }
        
        $structure = self::get_survey_structure($survey_id);
        
        if ($structure) {
            wp_send_json_success($structure);
        } else {
            wp_send_json_error('Structure not found');
        }
    }
    
    /**
     * AJAX: Sync survey structure from API
     */
    public function ajax_sync_survey_structure() {
        // Check nonce
        if (!check_ajax_referer('tpak_sync_structure', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
        
        if (!$survey_id) {
            wp_send_json_error('Invalid Survey ID');
        }
        
        // Use LimeSurvey API to fetch structure
        if (class_exists('TPAK_DQ_LimeSurvey_API')) {
            $api = new TPAK_DQ_LimeSurvey_API();
            $structure = $api->get_survey_structure($survey_id);
            
            if ($structure) {
                // Convert to our format
                $converted = array(
                    'survey_id' => $survey_id,
                    'title' => $structure['title'] ?? '',
                    'description' => $structure['description'] ?? '',
                    'groups' => $structure['groups'] ?? array(),
                    'questions' => $structure['questions'] ?? array(),
                    'subquestions' => $structure['subquestions'] ?? array(),
                    'answers' => $structure['answers'] ?? array(),
                    'attributes' => array(),
                    'last_updated' => current_time('mysql')
                );
                
                $this->save_survey_structure($survey_id, $converted);
                
                wp_send_json_success(array(
                    'message' => 'Sync โครงสร้างสำเร็จจาก LimeSurvey API',
                    'survey_id' => $survey_id,
                    'structure' => $converted
                ));
            } else {
                wp_send_json_error('ไม่สามารถดึงโครงสร้างจาก LimeSurvey API - กรุณาตรวจสอบการตั้งค่า API');
            }
        } else {
            wp_send_json_error('LimeSurvey API Connector ไม่พร้อมใช้งาน');
        }
    }
}

// Initialize
TPAK_DQ_Survey_Structure_Manager::get_instance();