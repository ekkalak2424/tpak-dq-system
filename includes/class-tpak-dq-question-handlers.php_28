<?php
/**
 * ไฟล์: includes/class-tpak-dq-question-handlers.php
 * จัดการคำถามที่มีโครงสร้างพิเศษจาก LimeSurvey
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Question_Handlers {
    
    /**
     * จัดการคำถามตามประเภท
     */
    public static function handle_special_question($question_code, $response_data, $question_info) {
        $question_type = isset($question_info['type']) ? $question_info['type'] : '';
        
        switch ($question_type) {
            case ':': // Array (Numbers)
                return self::handle_array_numbers($question_code, $response_data, $question_info);
                
            case 'F': // Array (Flexible Labels)
            case 'H': // Array (Flexible Labels) by Column
                return self::handle_array_flexible($question_code, $response_data, $question_info);
                
            case 'R': // Ranking
                return self::handle_ranking($question_code, $response_data, $question_info);
                
            case 'M': // Multiple choice
                return self::handle_multiple_choice($question_code, $response_data, $question_info);
                
            case 'P': // Multiple choice with comments
                return self::handle_multiple_choice_comments($question_code, $response_data, $question_info);
                
            case '1': // Array (Flexible Labels) Dual Scale
                return self::handle_dual_scale($question_code, $response_data, $question_info);
                
            default:
                return null;
        }
    }
    
    /**
     * จัดการ Array Numbers (ตารางกรอกตัวเลข)
     */
    private static function handle_array_numbers($question_code, $response_data, $question_info) {
        $result = array(
            'type' => 'array_numbers',
            'question' => $question_info['question'],
            'data' => array()
        );
        
        // รวบรวมคำตอบที่เกี่ยวข้อง
        foreach ($response_data as $key => $value) {
            if (strpos($key, $question_code) === 0 && !empty($value) && $value !== 'N') {
                // แยก subquestion และ scale
                if (preg_match('/^' . preg_quote($question_code) . '\[([^_\]]+)(?:_(\d+))?\]$/', $key, $matches)) {
                    $subquestion = $matches[1];
                    $scale = isset($matches[2]) ? $matches[2] : '';
                    
                    // หา label ของ subquestion
                    $sq_label = $subquestion;
                    if (isset($question_info['subquestions'][$subquestion])) {
                        $sq_label = $question_info['subquestions'][$subquestion]['question'];
                    }
                    
                    // หา label ของ scale (ถ้ามี)
                    $scale_label = $scale;
                    if ($scale && isset($question_info['answer_options'][$scale])) {
                        $scale_label = $question_info['answer_options'][$scale];
                    }
                    
                    $result['data'][] = array(
                        'subquestion' => $sq_label,
                        'scale' => $scale_label,
                        'value' => $value,
                        'raw_key' => $key
                    );
                }
            }
        }
        
        return $result;
    }
    
    /**
     * จัดการ Multiple Choice
     */
    private static function handle_multiple_choice($question_code, $response_data, $question_info) {
        $result = array(
            'type' => 'multiple_choice',
            'question' => $question_info['question'],
            'selected' => array()
        );
        
        foreach ($response_data as $key => $value) {
            if (strpos($key, $question_code) === 0) {
                // ตรวจสอบว่าถูกเลือกหรือไม่
                if ($value == 'Y' || $value == '1') {
                    // แยก option code
                    $option_code = str_replace($question_code, '', $key);
                    $option_code = trim($option_code, '[]');
                    
                    // หา label
                    $label = $option_code;
                    if (isset($question_info['answer_options'][$option_code])) {
                        $label = $question_info['answer_options'][$option_code];
                    } elseif (isset($question_info['subquestions'][$option_code])) {
                        $label = $question_info['subquestions'][$option_code]['question'];
                    }
                    
                    $result['selected'][] = $label;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * จัดการ Multiple Choice with Comments
     */
    private static function handle_multiple_choice_comments($question_code, $response_data, $question_info) {
        $result = array(
            'type' => 'multiple_choice_comments',
            'question' => $question_info['question'],
            'answers' => array()
        );
        
        foreach ($response_data as $key => $value) {
            if (strpos($key, $question_code) === 0 && !empty($value) && $value !== 'N') {
                // ตรวจสอบว่าเป็น comment หรือไม่
                if (strpos($key, 'comment') !== false) {
                    // เป็น comment
                    $option_code = preg_replace('/comment$/', '', $key);
                    $option_code = str_replace($question_code, '', $option_code);
                    $option_code = trim($option_code, '[]');
                    
                    if (!isset($result['answers'][$option_code])) {
                        $result['answers'][$option_code] = array();
                    }
                    $result['answers'][$option_code]['comment'] = $value;
                } else {
                    // เป็นคำตอบ
                    $option_code = str_replace($question_code, '', $key);
                    $option_code = trim($option_code, '[]');
                    
                    if (!isset($result['answers'][$option_code])) {
                        $result['answers'][$option_code] = array();
                    }
                    $result['answers'][$option_code]['selected'] = ($value == 'Y' || $value == '1');
                    
                    // หา label
                    if (isset($question_info['answer_options'][$option_code])) {
                        $result['answers'][$option_code]['label'] = $question_info['answer_options'][$option_code];
                    } else {
                        $result['answers'][$option_code]['label'] = $option_code;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * จัดการ Ranking Questions
     */
    private static function handle_ranking($question_code, $response_data, $question_info) {
        $result = array(
            'type' => 'ranking',
            'question' => $question_info['question'],
            'rankings' => array()
        );
        
        $rankings = array();
        
        foreach ($response_data as $key => $value) {
            if (strpos($key, $question_code) === 0 && !empty($value) && $value !== 'N') {
                // แยกอันดับ
                if (preg_match('/^' . preg_quote($question_code) . '\[(\d+)\]$/', $key, $matches)) {
                    $rank = intval($matches[1]);
                    
                    // หา label ของตัวเลือก
                    $label = $value;
                    if (isset($question_info['answer_options'][$value])) {
                        $label = $question_info['answer_options'][$value];
                    }
                    
                    $rankings[$rank] = array(
                        'rank' => $rank,
                        'code' => $value,
                        'label' => $label
                    );
                }
            }
        }
        
        // เรียงตามอันดับ
        ksort($rankings);
        $result['rankings'] = array_values($rankings);
        
        return $result;
    }
    
    /**
     * จัดการ Dual Scale Array
     */
    private static function handle_dual_scale($question_code, $response_data, $question_info) {
        $result = array(
            'type' => 'dual_scale',
            'question' => $question_info['question'],
            'scales' => array(
                '0' => array('label' => 'Scale 1', 'data' => array()),
                '1' => array('label' => 'Scale 2', 'data' => array())
            )
        );
        
        foreach ($response_data as $key => $value) {
            if (strpos($key, $question_code) === 0 && !empty($value) && $value !== 'N') {
                // แยก subquestion และ scale
                if (preg_match('/^' . preg_quote($question_code) . '\[([^#]+)#(\d)\]$/', $key, $matches)) {
                    $subquestion = $matches[1];
                    $scale = $matches[2];
                    
                    // หา labels
                    $sq_label = $subquestion;
                    if (isset($question_info['subquestions'][$subquestion])) {
                        $sq_label = $question_info['subquestions'][$subquestion]['question'];
                    }
                    
                    $answer_label = $value;
                    if (isset($question_info['answer_options'][$scale][$value])) {
                        $answer_label = $question_info['answer_options'][$scale][$value];
                    }
                    
                    $result['scales'][$scale]['data'][] = array(
                        'subquestion' => $sq_label,
                        'answer' => $answer_label,
                        'value' => $value
                    );
                }
            }
        }
        
        return $result;
    }
    
    /**
     * จัดการ Array Flexible
     */
    private static function handle_array_flexible($question_code, $response_data, $question_info) {
        $result = array(
            'type' => 'array_flexible',
            'question' => $question_info['question'],
            'data' => array()
        );
        
        // จัดกลุ่มข้อมูลตาม subquestion
        $grouped_data = array();
        
        foreach ($response_data as $key => $value) {
            if (strpos($key, $question_code) === 0 && !empty($value) && $value !== 'N') {
                // พยายามแยก pattern ต่างๆ
                $matched = false;
                
                // Pattern 1: Q[SQ][AO] เช่น Q1[1_1][2]
                if (preg_match('/^' . preg_quote($question_code) . '\[([^\]]+)\]\[([^\]]+)\]$/', $key, $matches)) {
                    $subquestion = $matches[1];
                    $answer_option = $matches[2];
                    $matched = true;
                }
                // Pattern 2: Q[SQ_AO] เช่น Q1[1_1]
                elseif (preg_match('/^' . preg_quote($question_code) . '\[([^_]+)_([^\]]+)\]$/', $key, $matches)) {
                    $subquestion = $matches[1];
                    $answer_option = $matches[2];
                    $matched = true;
                }
                // Pattern 3: Q[SQ] เช่น Q1[SQ001]
                elseif (preg_match('/^' . preg_quote($question_code) . '\[([^\]]+)\]$/', $key, $matches)) {
                    $subquestion = $matches[1];
                    $answer_option = '';
                    $matched = true;
                }
                
                if ($matched) {
                    if (!isset($grouped_data[$subquestion])) {
                        $grouped_data[$subquestion] = array();
                    }
                    
                    // หา labels
                    $sq_label = $subquestion;
                    if (isset($question_info['subquestions'][$subquestion])) {
                        $sq_label = $question_info['subquestions'][$subquestion]['question'];
                    }
                    
                    $answer_label = $value;
                    if ($answer_option && isset($question_info['answer_options'][$answer_option])) {
                        $answer_label = $question_info['answer_options'][$answer_option];
                    } elseif (isset($question_info['answer_options'][$value])) {
                        $answer_label = $question_info['answer_options'][$value];
                    }
                    
                    $grouped_data[$subquestion] = array(
                        'label' => $sq_label,
                        'answer' => $answer_label,
                        'value' => $value,
                        'answer_option' => $answer_option
                    );
                }
            }
        }
        
        $result['data'] = $grouped_data;
        return $result;
    }
    
    /**
     * Render คำถามพิเศษ
     */
    public static function render_special_question($handled_data) {
        if (!$handled_data || !isset($handled_data['type'])) {
            return;
        }
        
        switch ($handled_data['type']) {
            case 'array_numbers':
                self::render_array_numbers($handled_data);
                break;
                
            case 'multiple_choice':
                self::render_multiple_choice($handled_data);
                break;
                
            case 'multiple_choice_comments':
                self::render_multiple_choice_comments($handled_data);
                break;
                
            case 'ranking':
                self::render_ranking($handled_data);
                break;
                
            case 'dual_scale':
                self::render_dual_scale($handled_data);
                break;
                
            case 'array_flexible':
                self::render_array_flexible($handled_data);
                break;
        }
    }
    
    /**
     * Render Array Numbers
     */
    private static function render_array_numbers($data) {
        ?>
        <div class="tpak-array-numbers">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>รายการ</th>
                        <?php if (!empty($data['data']) && !empty($data['data'][0]['scale'])): ?>
                        <th>หมวดหมู่</th>
                        <?php endif; ?>
                        <th>จำนวน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['subquestion']); ?></td>
                        <?php if (!empty($item['scale'])): ?>
                        <td><?php echo esc_html($item['scale']); ?></td>
                        <?php endif; ?>
                        <td><strong><?php echo esc_html($item['value']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Multiple Choice
     */
    private static function render_multiple_choice($data) {
        ?>
        <div class="tpak-multiple-choice">
            <p><strong>ตัวเลือกที่เลือก:</strong></p>
            <ul style="margin-left: 20px;">
                <?php foreach ($data['selected'] as $item): ?>
                <li>✓ <?php echo esc_html($item); ?></li>
                <?php endforeach; ?>
                <?php if (empty($data['selected'])): ?>
                <li style="color: #999;">ไม่มีการเลือก</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }
    
    /**
     * Render Multiple Choice with Comments
     */
    private static function render_multiple_choice_comments($data) {
        ?>
        <div class="tpak-multiple-choice-comments">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>ตัวเลือก</th>
                        <th>เลือก</th>
                        <th>ความคิดเห็น</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['answers'] as $code => $answer): ?>
                    <tr>
                        <td><?php echo esc_html($answer['label']); ?></td>
                        <td><?php echo $answer['selected'] ? '✓' : '-'; ?></td>
                        <td><?php echo isset($answer['comment']) ? esc_html($answer['comment']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Ranking
     */
    private static function render_ranking($data) {
        ?>
        <div class="tpak-ranking">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">อันดับ</th>
                        <th>รายการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['rankings'] as $item): ?>
                    <tr>
                        <td style="text-align: center;"><strong><?php echo $item['rank']; ?></strong></td>
                        <td><?php echo esc_html($item['label']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Dual Scale
     */
    private static function render_dual_scale($data) {
        ?>
        <div class="tpak-dual-scale">
            <?php foreach ($data['scales'] as $scale_id => $scale): ?>
                <?php if (!empty($scale['data'])): ?>
                <h5><?php echo esc_html($scale['label']); ?></h5>
                <table class="tpak-array-table" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th>รายการ</th>
                            <th>คำตอบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scale['data'] as $item): ?>
                        <tr>
                            <td><?php echo esc_html($item['subquestion']); ?></td>
                            <td><strong><?php echo esc_html($item['answer']); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render Array Flexible
     */
    private static function render_array_flexible($data) {
        ?>
        <div class="tpak-array-flexible">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>รายการ</th>
                        <th>คำตอบ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $code => $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['label']); ?></td>
                        <td><strong><?php echo esc_html($item['answer']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}