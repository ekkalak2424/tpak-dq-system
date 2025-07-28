<?php
/**
 * ไฟล์: templates/survey-display.php
 * Template สำหรับแสดงผล Survey แบบใหม่
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// รับข้อมูลจาก parameters
$survey_id = isset($args['survey_id']) ? $args['survey_id'] : '';
$response_data = isset($args['response_data']) ? $args['response_data'] : array();
$post_id = isset($args['post_id']) ? $args['post_id'] : 0;

// ถ้าไม่มี survey_id ให้ดึงจาก post meta
if (!$survey_id && $post_id) {
    $survey_id = get_post_meta($post_id, '_tpak_survey_id', true);
}

// ถ้าไม่มี response_data ให้ดึงจาก post meta
if (empty($response_data) && $post_id) {
    $response_data = get_post_meta($post_id, '_tpak_import_data', true);
    if (!is_array($response_data)) {
        $response_data = array();
    }
}

// ดึงข้อมูล survey structure
$survey_structure = null;
if ($survey_id) {
    $survey_structure = TPAK_DQ_Survey_Structure_Manager::get_survey_structure($survey_id);
}

// ดึงข้อมูล post
$post = null;
if ($post_id) {
    $post = get_post($post_id);
}
?>

<div class="wrap tpak-survey-display-wrap">
    <?php if ($post): ?>
        <div class="tpak-survey-header">
            <h1 class="wp-heading-inline">
                <?php echo esc_html($post->post_title); ?>
            </h1>
            <hr class="wp-header-end">
        </div>
    <?php endif; ?>
    
    <?php if ($survey_id && $survey_structure): ?>
        <!-- Survey Display Container -->
        <div class="tpak-survey-container" 
             data-survey-id="<?php echo esc_attr($survey_id); ?>"
             data-response-data='<?php echo json_encode($response_data); ?>'>
            
            <!-- Loading Indicator -->
            <div class="tpak-loading" style="text-align: center; padding: 40px;">
                <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                <p>กำลังโหลดแบบสอบถาม...</p>
            </div>
            
            <!-- Survey Content will be loaded here by JavaScript -->
        </div>
        
        <!-- Survey Controls -->
        <div class="tpak-survey-controls" style="margin-top: 20px;">
            <div class="tpak-control-buttons">
                <button type="button" class="button button-secondary tpak-refresh-survey">
                    <span class="dashicons dashicons-update"></span>
                    รีเฟรชแบบสอบถาม
                </button>
                
                <button type="button" class="button button-primary tpak-save-survey">
                    <span class="dashicons dashicons-saved"></span>
                    บันทึกคำตอบ
                </button>
                
                <button type="button" class="button button-primary tpak-submit-survey">
                    <span class="dashicons dashicons-yes-alt"></span>
                    ส่งคำตอบ
                </button>
            </div>
            
            <div class="tpak-survey-status">
                <span class="tpak-status-indicator">
                    <span class="tpak-status-dot"></span>
                    <span class="tpak-status-text">พร้อมใช้งาน</span>
                </span>
            </div>
        </div>
        
        <!-- Survey Information -->
        <div class="tpak-survey-info" style="margin-top: 20px;">
            <div class="card">
                <h3>ข้อมูลแบบสอบถาม</h3>
                <table class="form-table">
                    <tr>
                        <th>Survey ID:</th>
                        <td><?php echo esc_html($survey_id); ?></td>
                    </tr>
                    <tr>
                        <th>จำนวนคำถาม:</th>
                        <td>
                            <?php 
                            $question_count = isset($survey_structure['questions']) ? count($survey_structure['questions']) : 0;
                            echo esc_html($question_count);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>สถานะ:</th>
                        <td>
                            <?php 
                            $status = get_post_meta($post_id, '_tpak_workflow_status', true);
                            $status_labels = array(
                                '' => 'รอดำเนินการ',
                                'pending_a' => 'รอ Supervisor ตรวจสอบ',
                                'pending_b' => 'รอ Examiner ตรวจสอบ',
                                'pending_c' => 'รอการอนุมัติ',
                                'rejected_by_b' => 'ถูกส่งกลับโดย Supervisor',
                                'rejected_by_c' => 'ถูกส่งกลับโดย Examiner',
                                'finalized' => 'อนุมัติแล้ว'
                            );
                            echo esc_html($status_labels[$status] ?? 'ไม่ทราบสถานะ');
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>วันที่นำเข้า:</th>
                        <td><?php echo $post ? get_the_date('d/m/Y H:i', $post) : '-'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Error Message -->
        <div class="notice notice-error">
            <p>
                <strong>ไม่สามารถแสดงแบบสอบถามได้</strong><br>
                <?php if (!$survey_id): ?>
                    ไม่พบ Survey ID
                <?php elseif (!$survey_structure): ?>
                    ไม่พบโครงสร้างแบบสอบถามสำหรับ Survey ID: <?php echo esc_html($survey_id); ?>
                <?php else: ?>
                    เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Debug Information -->
        <?php if (current_user_can('manage_options')): ?>
            <div class="card">
                <h3>ข้อมูล Debug</h3>
                <table class="form-table">
                    <tr>
                        <th>Post ID:</th>
                        <td><?php echo esc_html($post_id); ?></td>
                    </tr>
                    <tr>
                        <th>Survey ID:</th>
                        <td><?php echo esc_html($survey_id); ?></td>
                    </tr>
                    <tr>
                        <th>Response Data:</th>
                        <td>
                            <pre style="max-height: 200px; overflow: auto;"><?php echo esc_html(print_r($response_data, true)); ?></pre>
                        </td>
                    </tr>
                    <tr>
                        <th>Survey Structure:</th>
                        <td>
                            <pre style="max-height: 200px; overflow: auto;"><?php echo esc_html(print_r($survey_structure, true)); ?></pre>
                        </td>
                    </tr>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* Additional styles for survey display */
.tpak-survey-display-wrap {
    max-width: 1200px;
    margin: 0 auto;
}

.tpak-survey-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-top: 20px;
}

.tpak-control-buttons {
    display: flex;
    gap: 10px;
}

.tpak-control-buttons .button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.tpak-survey-status {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tpak-status-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.9rem;
    color: #666;
}

.tpak-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #28a745;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.tpak-survey-info .card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.tpak-survey-info h3 {
    margin-top: 0;
    color: #333;
}

.tpak-survey-info .form-table th {
    width: 150px;
    font-weight: 600;
}

/* Loading styles */
.tpak-loading {
    text-align: center;
    padding: 40px;
    color: #666;
}

.tpak-loading .spinner {
    margin: 0 auto 10px;
}

/* Error styles */
.notice-error {
    border-left-color: #dc3232;
}

.notice-error p {
    margin: 0;
}

/* Debug styles */
.card pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    font-size: 12px;
    line-height: 1.4;
}

/* Responsive design */
@media (max-width: 768px) {
    .tpak-survey-controls {
        flex-direction: column;
        gap: 15px;
    }
    
    .tpak-control-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .tpak-control-buttons .button {
        flex: 1;
        min-width: 120px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize survey display
    if ($('.tpak-survey-container').length) {
        const surveyContainer = $('.tpak-survey-container')[0];
        const surveyDisplay = new TPAKSurveyDisplay(surveyContainer, {
            surveyId: surveyContainer.dataset.surveyId,
            responseData: JSON.parse(surveyContainer.dataset.responseData || '{}'),
            autoSave: true,
            showProgress: true,
            showNavigation: true
        });
        
        // Handle control buttons
        $('.tpak-refresh-survey').on('click', function() {
            location.reload();
        });
        
        $('.tpak-save-survey').on('click', function() {
            const answers = surveyDisplay.getAnswers();
            // Save answers via AJAX
            $.post(ajaxurl, {
                action: 'tpak_save_survey_answers',
                survey_id: surveyContainer.dataset.surveyId,
                answers: answers,
                nonce: tpak_dq.nonce
            }, function(response) {
                if (response.success) {
                    alert('บันทึกคำตอบเรียบร้อยแล้ว');
                } else {
                    alert('เกิดข้อผิดพลาดในการบันทึก: ' + response.data);
                }
            });
        });
        
        $('.tpak-submit-survey').on('click', function() {
            if (confirm('คุณต้องการส่งคำตอบหรือไม่? การส่งคำตอบจะทำให้สถานะเป็น "อนุมัติแล้ว"')) {
                surveyDisplay.submitSurvey();
            }
        });
        
        // Update status indicator
        surveyDisplay.container.on('answerChanged', function() {
            $('.tpak-status-text').text('มีการเปลี่ยนแปลง');
            $('.tpak-status-dot').css('background', '#ffc107');
        });
        
        surveyDisplay.container.on('surveySubmitted', function() {
            $('.tpak-status-text').text('ส่งคำตอบแล้ว');
            $('.tpak-status-dot').css('background', '#28a745');
        });
    }
});
</script> 