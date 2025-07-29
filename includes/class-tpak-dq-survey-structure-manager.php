<?php
/**
 * ไฟล์: includes/class-tpak-dq-survey-structure-manager.php
 * จัดการ Survey Structure จาก LimeSurvey
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
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
        add_action('wp_ajax_tpak_delete_survey_structure', array($this, 'ajax_delete_survey_structure'));
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
                <h2>วิธีที่ 1: Upload ไฟล์ .lss จาก LimeSurvey</h2>
                <div class="notice notice-info">
                    <p><strong>วิธี Export ไฟล์ .lss จาก LimeSurvey:</strong></p>
                    <ol>
                        <li>เข้าไปที่ Survey ที่ต้องการใน LimeSurvey</li>
                        <li>ไปที่เมนู "Survey properties" > "Survey menu" > "Export"</li>
                        <li>เลือก "Export format" เป็น "LimeSurvey Survey Archive (.lss)"</li>
                        <li>คลิก "Export" เพื่อดาวน์โหลดไฟล์ .lss</li>
                    </ol>
                </div>
                <form id="lss-upload-form" enctype="multipart/form-data">
                    <input type="file" name="lss_file" accept=".lss" required>
                    <button type="submit" class="button button-primary">Upload และ Import Structure</button>
                </form>
                <div id="upload-result"></div>
            </div>
            
            <div class="tpak-structure-section">
                <h2>วิธีที่ 2: Sync จาก Survey ID</h2>
                <p class="description">ดึงโครงสร้างจาก API โดยตรง (ต้องมีการตั้งค่า API ที่ถูกต้อง)</p>
                <form id="sync-form">
                    <input type="number" name="survey_id" placeholder="Survey ID" required>
                    <button type="submit" class="button button-primary">Sync Structure</button>
                </form>
                <div id="sync-result"></div>
            </div>
            
            <div class="tpak-structure-section">
                <h2>Survey Structures ที่มีอยู่</h2>
                <?php $this->render_existing_structures(); ?>
            </div>
        </div>
        
        <style>
        .tpak-structure-section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .tpak-structure-section h2 {
            margin-top: 0;
        }
        .tpak-structure-section form {
            margin: 15px 0;
        }
        .tpak-structure-section input[type="file"],
        .tpak-structure-section input[type="number"] {
            margin-right: 10px;
        }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Upload LSS file
            $('#lss-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = new FormData(this);
                formData.append('action', 'tpak_upload_lss');
                formData.append('nonce', '<?php echo wp_create_nonce('tpak_upload_lss'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#upload-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            location.reload();
                        } else {
                            $('#upload-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                    }
                });
            });
            
            // Sync from API
            $('#sync-form').on('submit', function(e) {
                e.preventDefault();
                
                var surveyId = $(this).find('input[name="survey_id"]').val();
                
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
        if (!check_ajax_referer('tpak_upload_lss', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        if (!isset($_FILES['lss_file'])) {
            wp_send_json_error('ไม่พบไฟล์ที่ upload');
        }
        
        $file = $_FILES['lss_file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Upload failed with error code: ' . $file['error']);
        }
        
        // Check file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'lss') {
            wp_send_json_error('กรุณาเลือกไฟล์ .lss เท่านั้น (ไฟล์ที่เลือก: .' . $ext . ')');
        }
        
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            wp_send_json_error('ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 10MB)');
        }
        
        // Read file content
        $content = file_get_contents($file['tmp_name']);
        
        if ($content === false) {
            wp_send_json_error('ไม่สามารถอ่านไฟล์ได้');
        }
        
        error_log('TPAK Debug: LSS file size: ' . strlen($content) . ' bytes');
        error_log('TPAK Debug: First 50 chars: ' . substr($content, 0, 50));
        
        // Parse LSS
        $structure = $this->parse_lss_file($content);
        
        if ($structure) {
            wp_send_json_success(array(
                'message' => 'Import structure สำเร็จสำหรับ Survey ID: ' . $structure['survey_id'],
                'survey_id' => $structure['survey_id']
            ));
        } else {
            wp_send_json_error('ไม่สามารถ parse ไฟล์ LSS - กรุณาตรวจสอบว่าเป็นไฟล์ LSS ที่ถูกต้องจาก LimeSurvey');
        }
    }
    
    /**
     * Parse LSS file
     */
    private function parse_lss_file($content) {
        // Remove BOM if present
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        
        // Clean content - remove any whitespace or line breaks
        $content = trim($content);
        
        // LSS is base64 encoded XML
        $decoded = base64_decode($content, true);
        
        if (!$decoded) {
            error_log('TPAK Debug: Failed to base64 decode LSS file');
            
            // Try to decode without strict mode
            $decoded = base64_decode($content);
            if (!$decoded) {
                error_log('TPAK Debug: Failed to decode even without strict mode');
                return false;
            }
        }
        
        // Remove any BOM from decoded content
        $decoded = str_replace("\xEF\xBB\xBF", '', $decoded);
        
        // Log first 100 chars to debug
        error_log('TPAK Debug: First 100 chars of decoded: ' . substr($decoded, 0, 100));
        
        // Try to parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($decoded);
        
        if (!$xml) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                error_log('TPAK Debug XML Error: ' . $error->message);
            }
            libxml_clear_errors();
            
            // Try alternative parsing - maybe it's not base64 encoded
            error_log('TPAK Debug: Trying to parse as direct XML');
            $xml = simplexml_load_string($content);
            
            if (!$xml) {
                error_log('TPAK Debug: Failed to parse as direct XML too');
                return false;
            }
        }
        
        // Extract survey info
        $survey_info = null;
        
        // Try different XML structures
        if (isset($xml->surveys->rows->row)) {
            $survey_info = $xml->surveys->rows->row;
        } elseif (isset($xml->survey)) {
            $survey_info = $xml->survey;
        } elseif (isset($xml->rows->row)) {
            $survey_info = $xml->rows->row;
        }
        
        if (!$survey_info) {
            error_log('TPAK Debug: Cannot find survey info in XML structure');
            error_log('TPAK Debug: XML structure: ' . print_r($xml, true));
            return false;
        }
        
        $survey_id = isset($survey_info->sid) ? (string)$survey_info->sid : 
                    (isset($survey_info->surveyid) ? (string)$survey_info->surveyid : '');
        
        if (!$survey_id) {
            error_log('TPAK Debug: Cannot find survey ID');
            return false;
        }
        
        $structure = array(
            'survey_id' => $survey_id,
            'title' => isset($survey_info->surveyls_title) ? (string)$survey_info->surveyls_title : 'Survey ' . $survey_id,
            'description' => isset($survey_info->surveyls_description) ? (string)$survey_info->surveyls_description : '',
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
        $saved = $this->save_survey_structure($survey_id, $structure);
        
        if ($saved) {
            return $structure;
        }
        
        return false;
    }
    
    /**
     * Alternative method to parse LSS if it's compressed
     */
    private function parse_compressed_lss($content) {
        // Try to decompress if it's gzipped
        if (substr($content, 0, 2) === "\x1f\x8b") {
            $decompressed = gzdecode($content);
            if ($decompressed !== false) {
                return $this->parse_lss_file($decompressed);
            }
        }
        
        // Try to unzip if it's a zip file
        $temp_file = tempnam(sys_get_temp_dir(), 'lss_');
        file_put_contents($temp_file, $content);
        
        $zip = new ZipArchive();
        if ($zip->open($temp_file) === TRUE) {
            $xml_content = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'xml') {
                    $xml_content = $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();
            unlink($temp_file);
            
            if ($xml_content) {
                return $this->parse_lss_file($xml_content);
            }
        }
        
        unlink($temp_file);
        return false;
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
     * Delete survey structure
     */
    public function ajax_delete_survey_structure() {
        // Check nonce
        if (!check_ajax_referer('tpak_delete_structure', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        $survey_id = isset($_POST['survey_id']) ? intval($_POST['survey_id']) : 0;
        
        if (!$survey_id) {
            wp_send_json_error('Invalid Survey ID');
        }
        
        $option_name = 'tpak_survey_structure_' . $survey_id;
        
        if (delete_option($option_name)) {
            wp_send_json_success(array(
                'message' => 'ลบ Survey Structure สำเร็จ'
            ));
        } else {
            wp_send_json_error('ไม่สามารถลบ Survey Structure');
        }
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
                
                $.post(ajaxurl, {
                    action: 'tpak_delete_survey_structure',
                    survey_id: surveyId,
                    nonce: '<?php echo wp_create_nonce('tpak_delete_structure'); ?>'
                }, function(response) {
                    if (response.success) {
                        row.fadeOut(function() {
                            row.remove();
                        });
                        alert(response.data.message);
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
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
        
        // Use existing survey renderer to fetch structure
        $renderer = new TPAK_DQ_Survey_Renderer();
        $structure = $renderer->fetch_survey_structure($survey_id);
        
        if ($structure) {
            // Convert to our format
            $converted = array(
                'survey_id' => $survey_id,
                'groups' => array(),
                'questions' => $structure['questions'] ?? array(),
                'subquestions' => $structure['subquestions'] ?? array(),
                'answers' => $structure['answer_options'] ?? array(),
                'attributes' => array(),
                'last_updated' => current_time('mysql')
            );
            
            $this->save_survey_structure($survey_id, $converted);
            
            wp_send_json_success(array(
                'message' => 'Sync โครงสร้างสำเร็จ',
                'survey_id' => $survey_id
            ));
        } else {
            wp_send_json_error('ไม่สามารถดึงโครงสร้างจาก API');
        }
    }
}

// Initialize
TPAK_DQ_Survey_Structure_Manager::get_instance();