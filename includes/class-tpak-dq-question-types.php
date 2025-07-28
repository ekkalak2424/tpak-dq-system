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
            'A' => 'Array',
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
            '|' => 'Pipe'
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
            'L' => 'TPAK_DQ_List_Handler',           // List (radio)
            'M' => 'TPAK_DQ_Multiple_Handler',       // Multiple choice
            'T' => 'TPAK_DQ_Text_Handler',           // Text input
            'S' => 'TPAK_DQ_Short_Text_Handler',     // Short text
            'U' => 'TPAK_DQ_Long_Text_Handler',      // Long text
            'N' => 'TPAK_DQ_Numeric_Handler',        // Numeric
            'Y' => 'TPAK_DQ_Yes_No_Handler',         // Yes/No
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