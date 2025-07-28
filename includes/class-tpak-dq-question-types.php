<?php
/**
 * ไฟล์: includes/class-tpak-dq-question-types.php
 * จัดการ Question Types ต่างๆ จาก LimeSurvey
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Class สำหรับ Question Type Handler
 */
abstract class TPAK_DQ_Question_Handler {
    protected $question_data;
    protected $response_data;
    protected $question_code;
    
    public function __construct($question_data, $response_data = array()) {
        $this->question_data = $question_data;
        $this->response_data = $response_data;
        $this->question_code = $question_data['code'] ?? '';
    }
    
    /**
     * Render question HTML
     */
    abstract public function render();
    
    /**
     * Get answer value from response data
     */
    protected function get_answer_value($key = null) {
        $search_key = $key ?: $this->question_code;
        return $this->response_data[$search_key] ?? '';
    }
    
    /**
     * Get question title
     */
    protected function get_question_title() {
        return $this->question_data['title'] ?? $this->question_code;
    }
    
    /**
     * Get question help text
     */
    protected function get_question_help() {
        return $this->question_data['help'] ?? '';
    }
    
    /**
     * Get question type label
     */
    protected function get_question_type_label() {
        $type = $this->question_data['type'] ?? '';
        return $this->get_type_label($type);
    }
    
    /**
     * Get type label
     */
    protected function get_type_label($type) {
        $labels = array(
            'L' => 'List (Radio)',
            'M' => 'Multiple Choice',
            'T' => 'Text Input',
            'S' => 'Short Text',
            'U' => 'Long Text',
            'N' => 'Numeric',
            'K' => 'Multiple Numeric',
            'Q' => 'Multiple Short Text',
            'A' => 'Array (Radio)',
            'B' => 'Array Text',
            'C' => 'Array Yes/No',
            'E' => 'Array Increase',
            'F' => 'Array Flexible',
            'H' => 'Array Text by Column',
            '1' => 'Array Dual Scale',
            '5' => '5 Point Choice',
            'D' => 'Date',
            'G' => 'Gender',
            'I' => 'Language',
            'Y' => 'Yes/No',
            '!' => 'List Dropdown',
            'O' => 'List with Comment',
            'R' => 'Ranking',
            'X' => 'Boilerplate',
            '*' => 'Asterisk',
            '|' => 'Pipe',
            'Z' => 'File Upload',
            'W' => 'Date/Time',
            'V' => 'Slider',
            'J' => 'Matrix',
            'K' => 'Matrix Text',
            'P' => 'Matrix Numeric'
        );
        
        return $labels[$type] ?? 'Unknown Type';
    }
    
    /**
     * Generate unique ID for form elements
     */
    protected function get_element_id($suffix = '') {
        $id = 'tpak_' . sanitize_title($this->question_code);
        if ($suffix) {
            $id .= '_' . $suffix;
        }
        return $id;
    }
    
    /**
     * Render question wrapper
     */
    protected function render_question_wrapper($content, $additional_classes = '') {
        $classes = 'tpak-question ' . $additional_classes;
        $type_label = $this->get_question_type_label();
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>" data-question-code="<?php echo esc_attr($this->question_code); ?>">
            <div class="tpak-question-header">
                <h3 class="tpak-question-title"><?php echo esc_html($this->get_question_title()); ?></h3>
                <?php if ($this->get_question_help()): ?>
                    <div class="tpak-question-help"><?php echo esc_html($this->get_question_help()); ?></div>
                <?php endif; ?>
                <span class="tpak-question-type"><?php echo esc_html($type_label); ?></span>
            </div>
            <div class="tpak-question-content">
                <?php echo $content; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

/**
 * List (Radio) Question Handler
 */
class TPAK_DQ_List_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $options = $this->question_data['options'] ?? array();
        
        if (empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบตัวเลือกสำหรับคำถามนี้</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-radio-group">
            <?php foreach ($options as $value => $label): ?>
                <div class="tpak-radio-item">
                    <input type="radio" 
                           id="<?php echo esc_attr($this->get_element_id($value)); ?>"
                           name="<?php echo esc_attr($this->question_code); ?>"
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked($answer_value, $value); ?>
                           class="tpak-radio-input">
                    <label for="<?php echo esc_attr($this->get_element_id($value)); ?>" class="tpak-radio-label">
                        <?php echo esc_html($label); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-radio');
    }
}

/**
 * Multiple Choice Question Handler
 */
class TPAK_DQ_Multiple_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $options = $this->question_data['options'] ?? array();
        
        if (empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบตัวเลือกสำหรับคำถามนี้</p>');
        }
        
        // Convert answer to array if it's a string
        $selected_values = is_array($answer_value) ? $answer_value : array($answer_value);
        
        ob_start();
        ?>
        <div class="tpak-checkbox-group">
            <?php foreach ($options as $value => $label): ?>
                <div class="tpak-checkbox-item">
                    <input type="checkbox" 
                           id="<?php echo esc_attr($this->get_element_id($value)); ?>"
                           name="<?php echo esc_attr($this->question_code); ?>[]"
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked(in_array($value, $selected_values)); ?>
                           class="tpak-checkbox-input">
                    <label for="<?php echo esc_attr($this->get_element_id($value)); ?>" class="tpak-checkbox-label">
                        <?php echo esc_html($label); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-checkbox');
    }
}

/**
 * Text Input Question Handler
 */
class TPAK_DQ_Text_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $max_length = $this->question_data['max_length'] ?? '';
        $placeholder = $this->question_data['placeholder'] ?? '';
        
        ob_start();
        ?>
        <div class="tpak-text-input-group">
            <input type="text" 
                   id="<?php echo esc_attr($this->get_element_id()); ?>"
                   name="<?php echo esc_attr($this->question_code); ?>"
                   value="<?php echo esc_attr($answer_value); ?>"
                   <?php if ($max_length): ?>
                       maxlength="<?php echo esc_attr($max_length); ?>"
                   <?php endif; ?>
                   <?php if ($placeholder): ?>
                       placeholder="<?php echo esc_attr($placeholder); ?>"
                   <?php endif; ?>
                   class="tpak-text-input"
                   <?php if ($max_length): ?>
                       data-max-length="<?php echo esc_attr($max_length); ?>"
                   <?php endif; ?>>
            
            <?php if ($max_length): ?>
                <div class="tpak-char-counter">
                    <span class="tpak-char-count">0</span> / <?php echo esc_html($max_length); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-text');
    }
}

/**
 * Short Text Question Handler
 */
class TPAK_DQ_Short_Text_Handler extends TPAK_DQ_Text_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $max_length = $this->question_data['max_length'] ?? 255;
        $placeholder = $this->question_data['placeholder'] ?? 'กรุณาใส่ข้อความสั้นๆ';
        
        ob_start();
        ?>
        <div class="tpak-text-input-group">
            <input type="text" 
                   id="<?php echo esc_attr($this->get_element_id()); ?>"
                   name="<?php echo esc_attr($this->question_code); ?>"
                   value="<?php echo esc_attr($answer_value); ?>"
                   maxlength="<?php echo esc_attr($max_length); ?>"
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="tpak-text-input tpak-short-text"
                   data-max-length="<?php echo esc_attr($max_length); ?>">
            
            <div class="tpak-char-counter">
                <span class="tpak-char-count"><?php echo strlen($answer_value); ?></span> / <?php echo esc_html($max_length); ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-short-text');
    }
}

/**
 * Long Text Question Handler
 */
class TPAK_DQ_Long_Text_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $max_length = $this->question_data['max_length'] ?? 5000;
        $placeholder = $this->question_data['placeholder'] ?? 'กรุณาใส่ข้อความโดยละเอียด';
        $rows = $this->question_data['rows'] ?? 5;
        
        ob_start();
        ?>
        <div class="tpak-textarea-group">
            <textarea id="<?php echo esc_attr($this->get_element_id()); ?>"
                      name="<?php echo esc_attr($this->question_code); ?>"
                      rows="<?php echo esc_attr($rows); ?>"
                      maxlength="<?php echo esc_attr($max_length); ?>"
                      placeholder="<?php echo esc_attr($placeholder); ?>"
                      class="tpak-textarea tpak-long-text"
                      data-max-length="<?php echo esc_attr($max_length); ?>"><?php echo esc_textarea($answer_value); ?></textarea>
            
            <div class="tpak-char-counter">
                <span class="tpak-char-count"><?php echo strlen($answer_value); ?></span> / <?php echo esc_html($max_length); ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-long-text');
    }
}

/**
 * Numeric Question Handler
 */
class TPAK_DQ_Numeric_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $min_value = $this->question_data['min_value'] ?? '';
        $max_value = $this->question_data['max_value'] ?? '';
        $placeholder = $this->question_data['placeholder'] ?? 'กรุณาใส่ตัวเลข';
        
        ob_start();
        ?>
        <div class="tpak-numeric-input-group">
            <input type="number" 
                   id="<?php echo esc_attr($this->get_element_id()); ?>"
                   name="<?php echo esc_attr($this->question_code); ?>"
                   value="<?php echo esc_attr($answer_value); ?>"
                   <?php if ($min_value !== ''): ?>
                       min="<?php echo esc_attr($min_value); ?>"
                   <?php endif; ?>
                   <?php if ($max_value !== ''): ?>
                       max="<?php echo esc_attr($max_value); ?>"
                   <?php endif; ?>
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="tpak-numeric-input">
            
            <?php if ($min_value !== '' || $max_value !== ''): ?>
                <div class="tpak-numeric-range">
                    <?php if ($min_value !== ''): ?>
                        <span class="tpak-min-value">ค่าต่ำสุด: <?php echo esc_html($min_value); ?></span>
                    <?php endif; ?>
                    <?php if ($max_value !== ''): ?>
                        <span class="tpak-max-value">ค่าสูงสุด: <?php echo esc_html($max_value); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-numeric');
    }
}

/**
 * Yes/No Question Handler
 */
class TPAK_DQ_Yes_No_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        
        ob_start();
        ?>
        <div class="tpak-yesno-group">
            <div class="tpak-radio-item">
                <input type="radio" 
                       id="<?php echo esc_attr($this->get_element_id('Y')); ?>"
                       name="<?php echo esc_attr($this->question_code); ?>"
                       value="Y"
                       <?php checked($answer_value, 'Y'); ?>
                       class="tpak-radio-input">
                <label for="<?php echo esc_attr($this->get_element_id('Y')); ?>" class="tpak-radio-label">
                    ใช่
                </label>
            </div>
            <div class="tpak-radio-item">
                <input type="radio" 
                       id="<?php echo esc_attr($this->get_element_id('N')); ?>"
                       name="<?php echo esc_attr($this->question_code); ?>"
                       value="N"
                       <?php checked($answer_value, 'N'); ?>
                       class="tpak-radio-input">
                <label for="<?php echo esc_attr($this->get_element_id('N')); ?>" class="tpak-radio-label">
                    ไม่ใช่
                </label>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-yesno');
    }
}

/**
 * Array (Radio) Question Handler
 */
class TPAK_DQ_Array_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $subquestions = $this->question_data['subquestions'] ?? array();
        $options = $this->question_data['options'] ?? array();
        
        if (empty($subquestions) || empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบข้อมูลสำหรับ Array Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-array-container">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th class="tpak-array-subquestion-header">คำถาม</th>
                        <?php foreach ($options as $option): ?>
                            <th class="tpak-array-option-header"><?php echo esc_html($option['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subquestions as $subquestion): ?>
                        <tr class="tpak-array-row">
                            <td class="tpak-array-subquestion">
                                <?php echo esc_html($subquestion['label']); ?>
                            </td>
                            <?php foreach ($options as $option): ?>
                                <td class="tpak-array-cell">
                                    <input type="radio" 
                                           id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $option['value'])); ?>"
                                           name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . ']'); ?>"
                                           value="<?php echo esc_attr($option['value']); ?>"
                                           <?php checked($this->get_array_answer_value($subquestion['code']), $option['value']); ?>
                                           class="tpak-array-radio">
                                    <label for="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $option['value'])); ?>" class="tpak-array-label">
                                        <?php echo esc_html($option['label']); ?>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-array');
    }
    
    /**
     * Get array answer value for specific subquestion
     */
    private function get_array_answer_value($subquestion_code) {
        $array_key = $this->question_code . '[' . $subquestion_code . ']';
        return $this->response_data[$array_key] ?? '';
    }
}

/**
 * Array Text Question Handler
 */
class TPAK_DQ_Array_Text_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $subquestions = $this->question_data['subquestions'] ?? array();
        
        if (empty($subquestions)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบข้อมูลสำหรับ Array Text Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-array-text-container">
            <table class="tpak-array-text-table">
                <thead>
                    <tr>
                        <th class="tpak-array-text-subquestion-header">คำถาม</th>
                        <th class="tpak-array-text-input-header">คำตอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subquestions as $subquestion): ?>
                        <tr class="tpak-array-text-row">
                            <td class="tpak-array-text-subquestion">
                                <?php echo esc_html($subquestion['label']); ?>
                            </td>
                            <td class="tpak-array-text-input-cell">
                                <input type="text" 
                                       id="<?php echo esc_attr($this->get_element_id($subquestion['code'])); ?>"
                                       name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . ']'); ?>"
                                       value="<?php echo esc_attr($this->get_array_answer_value($subquestion['code'])); ?>"
                                       class="tpak-array-text-input"
                                       placeholder="กรุณาใส่คำตอบ">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-array-text');
    }
    
    /**
     * Get array answer value for specific subquestion
     */
    private function get_array_answer_value($subquestion_code) {
        $array_key = $this->question_code . '[' . $subquestion_code . ']';
        return $this->response_data[$array_key] ?? '';
    }
}

/**
 * Array Yes/No Question Handler
 */
class TPAK_DQ_Array_Yes_No_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $subquestions = $this->question_data['subquestions'] ?? array();
        
        if (empty($subquestions)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบข้อมูลสำหรับ Array Yes/No Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-array-yesno-container">
            <table class="tpak-array-yesno-table">
                <thead>
                    <tr>
                        <th class="tpak-array-yesno-subquestion-header">คำถาม</th>
                        <th class="tpak-array-yesno-option-header">ใช่</th>
                        <th class="tpak-array-yesno-option-header">ไม่ใช่</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subquestions as $subquestion): ?>
                        <tr class="tpak-array-yesno-row">
                            <td class="tpak-array-yesno-subquestion">
                                <?php echo esc_html($subquestion['label']); ?>
                            </td>
                            <td class="tpak-array-yesno-cell">
                                <input type="radio" 
                                       id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_Y')); ?>"
                                       name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . ']'); ?>"
                                       value="Y"
                                       <?php checked($this->get_array_answer_value($subquestion['code']), 'Y'); ?>
                                       class="tpak-array-yesno-radio">
                                <label for="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_Y')); ?>" class="tpak-array-yesno-label">
                                    ใช่
                                </label>
                            </td>
                            <td class="tpak-array-yesno-cell">
                                <input type="radio" 
                                       id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_N')); ?>"
                                       name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . ']'); ?>"
                                       value="N"
                                       <?php checked($this->get_array_answer_value($subquestion['code']), 'N'); ?>
                                       class="tpak-array-yesno-radio">
                                <label for="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_N')); ?>" class="tpak-array-yesno-label">
                                    ไม่ใช่
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-array-yesno');
    }
    
    /**
     * Get array answer value for specific subquestion
     */
    private function get_array_answer_value($subquestion_code) {
        $array_key = $this->question_code . '[' . $subquestion_code . ']';
        return $this->response_data[$array_key] ?? '';
    }
}

/**
 * Ranking Question Handler
 */
class TPAK_DQ_Ranking_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $options = $this->question_data['options'] ?? array();
        $answer_value = $this->get_answer_value();
        
        if (empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบตัวเลือกสำหรับ Ranking Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-ranking-container">
            <p class="tpak-ranking-instruction">กรุณาจัดอันดับโดยลากและวาง หรือใช้ปุ่มขึ้น/ลง</p>
            
            <div class="tpak-ranking-list" data-question-code="<?php echo esc_attr($this->question_code); ?>">
                <?php 
                $ranked_options = $this->get_ranked_options($options, $answer_value);
                foreach ($ranked_options as $index => $option): 
                ?>
                    <div class="tpak-ranking-item" data-value="<?php echo esc_attr($option['value']); ?>" data-rank="<?php echo $index + 1; ?>">
                        <div class="tpak-ranking-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <div class="tpak-ranking-content">
                            <span class="tpak-ranking-number"><?php echo $index + 1; ?></span>
                            <span class="tpak-ranking-label"><?php echo esc_html($option['label']); ?></span>
                        </div>
                        <div class="tpak-ranking-controls">
                            <button type="button" class="tpak-ranking-up" <?php echo $index === 0 ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                            </button>
                            <button type="button" class="tpak-ranking-down" <?php echo $index === count($ranked_options) - 1 ? 'disabled' : ''; ?>>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </div>
                        <input type="hidden" 
                               name="<?php echo esc_attr($this->question_code . '[' . $option['value'] . ']'); ?>"
                               value="<?php echo $index + 1; ?>"
                               class="tpak-ranking-input">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-ranking');
    }
    
    /**
     * Get ranked options based on answer value
     */
    private function get_ranked_options($options, $answer_value) {
        if (empty($answer_value) || !is_array($answer_value)) {
            return $options;
        }
        
        // Sort options based on ranking
        usort($options, function($a, $b) use ($answer_value) {
            $rank_a = isset($answer_value[$a['value']]) ? intval($answer_value[$a['value']]) : 999;
            $rank_b = isset($answer_value[$b['value']]) ? intval($answer_value[$b['value']]) : 999;
            return $rank_a - $rank_b;
        });
        
        return $options;
    }
}

/**
 * Date/Time Question Handler
 */
class TPAK_DQ_Date_Time_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $date_only = $this->question_data['date_only'] ?? false;
        
        ob_start();
        ?>
        <div class="tpak-datetime-container">
            <div class="tpak-datetime-input-group">
                <input type="date" 
                       id="<?php echo esc_attr($this->get_element_id()); ?>"
                       name="<?php echo esc_attr($this->question_code); ?>"
                       value="<?php echo esc_attr($this->format_date_for_input($answer_value)); ?>"
                       class="tpak-datetime-input"
                       <?php echo $date_only ? '' : 'data-time-enabled="true"'; ?>>
                
                <?php if (!$date_only): ?>
                    <input type="time" 
                           id="<?php echo esc_attr($this->get_element_id('_time')); ?>"
                           name="<?php echo esc_attr($this->question_code . '_time'); ?>"
                           value="<?php echo esc_attr($this->format_time_for_input($answer_value)); ?>"
                           class="tpak-datetime-time-input">
                <?php endif; ?>
            </div>
            
            <div class="tpak-datetime-calendar">
                <button type="button" class="tpak-calendar-toggle">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    เลือกวันที่
                </button>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-datetime');
    }
    
    /**
     * Format date for input field
     */
    private function format_date_for_input($date_string) {
        if (empty($date_string)) return '';
        
        $timestamp = strtotime($date_string);
        if ($timestamp === false) return '';
        
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Format time for input field
     */
    private function format_time_for_input($date_string) {
        if (empty($date_string)) return '';
        
        $timestamp = strtotime($date_string);
        if ($timestamp === false) return '';
        
        return date('H:i', $timestamp);
    }
}

/**
 * File Upload Question Handler
 */
class TPAK_DQ_File_Upload_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $max_size = $this->question_data['max_size'] ?? 5242880; // 5MB default
        $allowed_types = $this->question_data['allowed_types'] ?? array('jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx');
        
        ob_start();
        ?>
        <div class="tpak-file-upload-container">
            <div class="tpak-file-upload-area" data-max-size="<?php echo esc_attr($max_size); ?>" data-allowed-types="<?php echo esc_attr(implode(',', $allowed_types)); ?>">
                <div class="tpak-file-upload-preview">
                    <?php if (!empty($answer_value)): ?>
                        <div class="tpak-file-preview">
                            <span class="dashicons dashicons-paperclip"></span>
                            <span class="tpak-file-name"><?php echo esc_html(basename($answer_value)); ?></span>
                            <button type="button" class="tpak-file-remove">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="tpak-file-upload-placeholder">
                            <span class="dashicons dashicons-upload"></span>
                            <p>คลิกเพื่อเลือกไฟล์ หรือลากไฟล์มาวางที่นี่</p>
                            <p class="tpak-file-upload-info">
                                รองรับไฟล์: <?php echo esc_html(implode(', ', $allowed_types)); ?><br>
                                ขนาดสูงสุด: <?php echo esc_html($this->format_file_size($max_size)); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <input type="file" 
                       id="<?php echo esc_attr($this->get_element_id()); ?>"
                       name="<?php echo esc_attr($this->question_code); ?>"
                       class="tpak-file-input"
                       accept="<?php echo esc_attr('.' . implode(',.', $allowed_types)); ?>"
                       style="display: none;">
                
                <input type="hidden" 
                       name="<?php echo esc_attr($this->question_code . '_current'); ?>"
                       value="<?php echo esc_attr($answer_value); ?>"
                       class="tpak-file-current">
            </div>
            
            <div class="tpak-file-upload-progress" style="display: none;">
                <div class="tpak-progress-bar">
                    <div class="tpak-progress-fill" style="width: 0%"></div>
                </div>
                <span class="tpak-progress-text">0%</span>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-file-upload');
    }
    
    /**
     * Format file size for display
     */
    private function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

/**
 * Matrix Question Handler
 */
class TPAK_DQ_Matrix_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $subquestions = $this->question_data['subquestions'] ?? array();
        $options = $this->question_data['options'] ?? array();
        $matrix_type = $this->question_data['matrix_type'] ?? 'radio'; // radio, checkbox
        
        if (empty($subquestions) || empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบข้อมูลสำหรับ Matrix Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-matrix-container">
            <table class="tpak-matrix-table">
                <thead>
                    <tr>
                        <th class="tpak-matrix-subquestion-header">คำถาม</th>
                        <?php foreach ($options as $option): ?>
                            <th class="tpak-matrix-option-header"><?php echo esc_html($option['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subquestions as $subquestion): ?>
                        <tr class="tpak-matrix-row">
                            <td class="tpak-matrix-subquestion">
                                <?php echo esc_html($subquestion['label']); ?>
                            </td>
                            <?php foreach ($options as $option): ?>
                                <td class="tpak-matrix-cell">
                                    <?php if ($matrix_type === 'checkbox'): ?>
                                        <input type="checkbox" 
                                               id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $option['value'])); ?>"
                                               name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . '][' . $option['value'] . ']'); ?>"
                                               value="1"
                                               <?php checked($this->get_matrix_answer_value($subquestion['code'], $option['value']), '1'); ?>
                                               class="tpak-matrix-checkbox">
                                    <?php else: ?>
                                        <input type="radio" 
                                               id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $option['value'])); ?>"
                                               name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . ']'); ?>"
                                               value="<?php echo esc_attr($option['value']); ?>"
                                               <?php checked($this->get_matrix_answer_value($subquestion['code']), $option['value']); ?>
                                               class="tpak-matrix-radio">
                                    <?php endif; ?>
                                    <label for="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $option['value'])); ?>" class="tpak-matrix-label">
                                        <?php echo esc_html($option['label']); ?>
                                    </label>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-matrix');
    }
    
    /**
     * Get matrix answer value for specific subquestion and option
     */
    private function get_matrix_answer_value($subquestion_code, $option_value = null) {
        if ($option_value) {
            $matrix_key = $this->question_code . '[' . $subquestion_code . '][' . $option_value . ']';
        } else {
            $matrix_key = $this->question_code . '[' . $subquestion_code . ']';
        }
        return $this->response_data[$matrix_key] ?? '';
    }
}

/**
 * Matrix Text Question Handler
 */
class TPAK_DQ_Matrix_Text_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $subquestions = $this->question_data['subquestions'] ?? array();
        $columns = $this->question_data['columns'] ?? array();
        
        if (empty($subquestions) || empty($columns)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบข้อมูลสำหรับ Matrix Text Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-matrix-text-container">
            <table class="tpak-matrix-text-table">
                <thead>
                    <tr>
                        <th class="tpak-matrix-text-subquestion-header">คำถาม</th>
                        <?php foreach ($columns as $column): ?>
                            <th class="tpak-matrix-text-column-header"><?php echo esc_html($column['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subquestions as $subquestion): ?>
                        <tr class="tpak-matrix-text-row">
                            <td class="tpak-matrix-text-subquestion">
                                <?php echo esc_html($subquestion['label']); ?>
                            </td>
                            <?php foreach ($columns as $column): ?>
                                <td class="tpak-matrix-text-cell">
                                    <input type="text" 
                                           id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $column['code'])); ?>"
                                           name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . '][' . $column['code'] . ']'); ?>"
                                           value="<?php echo esc_attr($this->get_matrix_text_answer_value($subquestion['code'], $column['code'])); ?>"
                                           class="tpak-matrix-text-input"
                                           placeholder="<?php echo esc_attr($column['placeholder'] ?? 'กรุณาใส่คำตอบ'); ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-matrix-text');
    }
    
    /**
     * Get matrix text answer value for specific subquestion and column
     */
    private function get_matrix_text_answer_value($subquestion_code, $column_code) {
        $matrix_key = $this->question_code . '[' . $subquestion_code . '][' . $column_code . ']';
        return $this->response_data[$matrix_key] ?? '';
    }
}

/**
 * Matrix Numeric Question Handler
 */
class TPAK_DQ_Matrix_Numeric_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $subquestions = $this->question_data['subquestions'] ?? array();
        $columns = $this->question_data['columns'] ?? array();
        
        if (empty($subquestions) || empty($columns)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบข้อมูลสำหรับ Matrix Numeric Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-matrix-numeric-container">
            <table class="tpak-matrix-numeric-table">
                <thead>
                    <tr>
                        <th class="tpak-matrix-numeric-subquestion-header">คำถาม</th>
                        <?php foreach ($columns as $column): ?>
                            <th class="tpak-matrix-numeric-column-header"><?php echo esc_html($column['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subquestions as $subquestion): ?>
                        <tr class="tpak-matrix-numeric-row">
                            <td class="tpak-matrix-numeric-subquestion">
                                <?php echo esc_html($subquestion['label']); ?>
                            </td>
                            <?php foreach ($columns as $column): ?>
                                <td class="tpak-matrix-numeric-cell">
                                    <input type="number" 
                                           id="<?php echo esc_attr($this->get_element_id($subquestion['code'] . '_' . $column['code'])); ?>"
                                           name="<?php echo esc_attr($this->question_code . '[' . $subquestion['code'] . '][' . $column['code'] . ']'); ?>"
                                           value="<?php echo esc_attr($this->get_matrix_numeric_answer_value($subquestion['code'], $column['code'])); ?>"
                                           class="tpak-matrix-numeric-input"
                                           min="<?php echo esc_attr($column['min'] ?? ''); ?>"
                                           max="<?php echo esc_attr($column['max'] ?? ''); ?>"
                                           step="<?php echo esc_attr($column['step'] ?? '1'); ?>"
                                           placeholder="<?php echo esc_attr($column['placeholder'] ?? '0'); ?>">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-matrix-numeric');
    }
    
    /**
     * Get matrix numeric answer value for specific subquestion and column
     */
    private function get_matrix_numeric_answer_value($subquestion_code, $column_code) {
        $matrix_key = $this->question_code . '[' . $subquestion_code . '][' . $column_code . ']';
        return $this->response_data[$matrix_key] ?? '';
    }
}

/**
 * Slider Question Handler
 */
class TPAK_DQ_Slider_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $min_value = $this->question_data['min_value'] ?? 0;
        $max_value = $this->question_data['max_value'] ?? 100;
        $step = $this->question_data['step'] ?? 1;
        $default_value = $this->question_data['default_value'] ?? $min_value;
        
        $current_value = !empty($answer_value) ? $answer_value : $default_value;
        
        ob_start();
        ?>
        <div class="tpak-slider-container">
            <div class="tpak-slider-track">
                <input type="range" 
                       id="<?php echo esc_attr($this->get_element_id()); ?>"
                       name="<?php echo esc_attr($this->question_code); ?>"
                       min="<?php echo esc_attr($min_value); ?>"
                       max="<?php echo esc_attr($max_value); ?>"
                       step="<?php echo esc_attr($step); ?>"
                       value="<?php echo esc_attr($current_value); ?>"
                       class="tpak-slider-input">
                
                <div class="tpak-slider-labels">
                    <span class="tpak-slider-min"><?php echo esc_html($min_value); ?></span>
                    <span class="tpak-slider-max"><?php echo esc_html($max_value); ?></span>
                </div>
            </div>
            
            <div class="tpak-slider-value">
                <span class="tpak-slider-current"><?php echo esc_html($current_value); ?></span>
                <?php if (!empty($this->question_data['unit'])): ?>
                    <span class="tpak-slider-unit"><?php echo esc_html($this->question_data['unit']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-slider');
    }
}

/**
 * Dropdown Question Handler
 */
class TPAK_DQ_Dropdown_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $options = $this->question_data['options'] ?? array();
        
        if (empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบตัวเลือกสำหรับ Dropdown Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-dropdown-container">
            <select id="<?php echo esc_attr($this->get_element_id()); ?>"
                    name="<?php echo esc_attr($this->question_code); ?>"
                    class="tpak-dropdown-select">
                <option value="">-- เลือกตัวเลือก --</option>
                <?php foreach ($options as $option): ?>
                    <option value="<?php echo esc_attr($option['value']); ?>"
                            <?php selected($answer_value, $option['value']); ?>>
                        <?php echo esc_html($option['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-dropdown');
    }
}

/**
 * List with Comment Question Handler
 */
class TPAK_DQ_List_Comment_Handler extends TPAK_DQ_Question_Handler {
    public function render() {
        $answer_value = $this->get_answer_value();
        $comment_value = $this->response_data[$this->question_code . '_comment'] ?? '';
        $options = $this->question_data['options'] ?? array();
        
        if (empty($options)) {
            return $this->render_question_wrapper('<p class="tpak-error">ไม่พบตัวเลือกสำหรับ List with Comment Question</p>');
        }
        
        ob_start();
        ?>
        <div class="tpak-list-comment-container">
            <div class="tpak-list-comment-options">
                <?php foreach ($options as $option): ?>
                    <div class="tpak-list-comment-item">
                        <input type="radio" 
                               id="<?php echo esc_attr($this->get_element_id($option['value'])); ?>"
                               name="<?php echo esc_attr($this->question_code); ?>"
                               value="<?php echo esc_attr($option['value']); ?>"
                               <?php checked($answer_value, $option['value']); ?>
                               class="tpak-list-comment-radio">
                        <label for="<?php echo esc_attr($this->get_element_id($option['value'])); ?>" class="tpak-list-comment-label">
                            <?php echo esc_html($option['label']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="tpak-list-comment-text">
                <label for="<?php echo esc_attr($this->get_element_id('_comment')); ?>" class="tpak-list-comment-text-label">
                    หมายเหตุหรือความคิดเห็นเพิ่มเติม:
                </label>
                <textarea id="<?php echo esc_attr($this->get_element_id('_comment')); ?>"
                          name="<?php echo esc_attr($this->question_code . '_comment'); ?>"
                          class="tpak-list-comment-textarea"
                          placeholder="กรุณาใส่ความคิดเห็นเพิ่มเติม (ถ้ามี)"
                          rows="3"><?php echo esc_textarea($comment_value); ?></textarea>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $this->render_question_wrapper($content, 'tpak-question-list-comment');
    }
}

/**
 * Main Question Types Manager
 */
class TPAK_DQ_Question_Types {
    private static $instance = null;
    private $type_handlers = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->register_handlers();
    }
    
    /**
     * Register all question type handlers
     */
    private function register_handlers() {
        $this->type_handlers = array(
            // Simple Questions
            'L' => 'TPAK_DQ_List_Handler',           // List (radio)
            'M' => 'TPAK_DQ_Multiple_Handler',       // Multiple choice
            'T' => 'TPAK_DQ_Text_Handler',           // Text input
            'S' => 'TPAK_DQ_Short_Text_Handler',     // Short text
            'U' => 'TPAK_DQ_Long_Text_Handler',      // Long text
            'N' => 'TPAK_DQ_Numeric_Handler',        // Numeric
            'Y' => 'TPAK_DQ_Yes_No_Handler',         // Yes/No
            
            // Complex Questions
            'A' => 'TPAK_DQ_Array_Handler',          // Array (radio)
            'B' => 'TPAK_DQ_Array_Text_Handler',     // Array text
            'C' => 'TPAK_DQ_Array_Yes_No_Handler',   // Array yes/no
            'R' => 'TPAK_DQ_Ranking_Handler',        // Ranking
            'W' => 'TPAK_DQ_Date_Time_Handler',      // Date/Time
            'Z' => 'TPAK_DQ_File_Upload_Handler',    // File upload
            
            // Advanced Questions
            'J' => 'TPAK_DQ_Matrix_Handler',         // Matrix
            'K' => 'TPAK_DQ_Matrix_Text_Handler',    // Matrix text
            'P' => 'TPAK_DQ_Matrix_Numeric_Handler', // Matrix numeric
            'V' => 'TPAK_DQ_Slider_Handler',         // Slider
            '!' => 'TPAK_DQ_Dropdown_Handler',       // Dropdown
            'O' => 'TPAK_DQ_List_Comment_Handler',   // List with comment
        );
    }
    
    /**
     * Get handler for question type
     */
    public function get_handler($question_type, $question_data, $response_data = array()) {
        if (!isset($this->type_handlers[$question_type])) {
            // Return default text handler for unknown types
            return new TPAK_DQ_Text_Handler($question_data, $response_data);
        }
        
        $handler_class = $this->type_handlers[$question_type];
        return new $handler_class($question_data, $response_data);
    }
    
    /**
     * Render question by type
     */
    public function render_question($question_type, $question_data, $response_data = array()) {
        $handler = $this->get_handler($question_type, $question_data, $response_data);
        return $handler->render();
    }
    
    /**
     * Get supported question types
     */
    public function get_supported_types() {
        return array_keys($this->type_handlers);
    }
    
    /**
     * Check if question type is supported
     */
    public function is_supported($question_type) {
        return isset($this->type_handlers[$question_type]);
    }
} 