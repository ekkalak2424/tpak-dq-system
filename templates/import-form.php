<?php
/**
 * ไฟล์: templates/import-form.php
 * Template สำหรับฟอร์มนำเข้าข้อมูล
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tpak-import-form-wrapper">
    <form id="tpak-import-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('tpak_import', 'tpak_import_nonce'); ?>
        
        <div class="tpak-import-options">
            <h3><?php echo esc_html__('ตัวเลือกการนำเข้า', 'tpak-dq'); ?></h3>
            
            <!-- Import Type -->
            <div class="tpak-form-field">
                <label for="import_source">
                    <?php echo esc_html__('แหล่งข้อมูล', 'tpak-dq'); ?>
                </label>
                <select name="import_source" id="import_source">
                    <option value="limesurvey"><?php echo esc_html__('LimeSurvey API', 'tpak-dq'); ?></option>
                    <option value="csv"><?php echo esc_html__('ไฟล์ CSV', 'tpak-dq'); ?></option>
                    <option value="excel"><?php echo esc_html__('ไฟล์ Excel', 'tpak-dq'); ?></option>
                </select>
            </div>
            
            <!-- LimeSurvey Options -->
            <div id="limesurvey-options" class="import-source-options">
                <div class="tpak-form-field">
                    <label for="survey_id">
                        <?php echo esc_html__('Survey ID', 'tpak-dq'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text" name="survey_id" id="survey_id" class="regular-text" />
                    <p class="description">
                        <?php echo esc_html__('ระบุ Survey ID จาก LimeSurvey', 'tpak-dq'); ?>
                    </p>
                </div>
                
                <div class="tpak-form-field">
                    <label><?php echo esc_html__('ช่วงเวลา', 'tpak-dq'); ?></label>
                    <div class="date-range">
                        <input type="date" name="start_date" id="start_date" />
                        <span><?php echo esc_html__('ถึง', 'tpak-dq'); ?></span>
                        <input type="date" name="end_date" id="end_date" />
                    </div>
                </div>
            </div>
            
            <!-- File Upload Options -->
            <div id="file-options" class="import-source-options" style="display:none;">
                <div class="tpak-form-field">
                    <label for="import_file">
                        <?php echo esc_html__('เลือกไฟล์', 'tpak-dq'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="file" name="import_file" id="import_file" 
                           accept=".csv,.xlsx,.xls" />
                    <p class="description">
                        <?php echo esc_html__('รองรับไฟล์ .csv, .xlsx, .xls', 'tpak-dq'); ?>
                    </p>
                </div>
            </div>
            
            <!-- Advanced Options -->
            <div class="tpak-advanced-options">
                <h4><?php echo esc_html__('ตัวเลือกขั้นสูง', 'tpak-dq'); ?></h4>
                
                <div class="tpak-form-field">
                    <label>
                        <input type="checkbox" name="skip_duplicates" value="1" checked />
                        <?php echo esc_html__('ข้ามรายการที่ซ้ำ', 'tpak-dq'); ?>
                    </label>
                </div>
                
                <div class="tpak-form-field">
                    <label>
                        <input type="checkbox" name="auto_assign" value="1" />
                        <?php echo esc_html__('มอบหมายให้ผู้ใช้อัตโนมัติ', 'tpak-dq'); ?>
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Submit Button -->
        <p class="submit">
            <button type="button" class="button button-primary" id="tpak-import-btn">
                <span class="dashicons dashicons-upload"></span>
                <?php echo esc_html__('เริ่มนำเข้าข้อมูล', 'tpak-dq'); ?>
            </button>
            
            <button type="button" class="button" id="tpak-preview-btn">
                <span class="dashicons dashicons-visibility"></span>
                <?php echo esc_html__('ดูตัวอย่าง', 'tpak-dq'); ?>
            </button>
        </p>
    </form>
    
    <!-- Progress Section -->
    <div id="import-progress" style="display:none;">
        <h3><?php echo esc_html__('กำลังนำเข้าข้อมูล...', 'tpak-dq'); ?></h3>
        <div class="progress-wrapper">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;"></div>
            </div>
            <div class="progress-text">
                <span class="progress-current">0</span> / 
                <span class="progress-total">0</span>
                <?php echo esc_html__('รายการ', 'tpak-dq'); ?>
            </div>
        </div>
        <p class="status-message"></p>
    </div>
    
    <!-- Results Section -->
    <div id="import-results" style="display:none;">
        <h3><?php echo esc_html__('ผลการนำเข้าข้อมูล', 'tpak-dq'); ?></h3>
        <div class="results-content"></div>
        
        <p class="import-actions">
            <a href="<?php echo admin_url('edit.php?post_type=tpak_verification'); ?>" 
               class="button button-primary">
                <?php echo esc_html__('ดูรายการที่นำเข้า', 'tpak-dq'); ?>
            </a>
            
            <button type="button" class="button" id="import-new">
                <?php echo esc_html__('นำเข้าชุดใหม่', 'tpak-dq'); ?>
            </button>
        </p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Toggle import source options
    $('#import_source').on('change', function() {
        $('.import-source-options').hide();
        
        if ($(this).val() === 'limesurvey') {
            $('#limesurvey-options').show();
        } else {
            $('#file-options').show();
        }
    }).trigger('change');
    
    // Import new button
    $('#import-new').on('click', function() {
        $('#import-results').hide();
        $('#tpak-import-form')[0].reset();
        $('#import_source').trigger('change');
    });
});
</script>