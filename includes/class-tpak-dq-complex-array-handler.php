<?php
/**
 * ไฟล์: includes/class-tpak-dq-complex-array-handler.php
 * จัดการคำถาม Array ที่ซับซ้อนจาก LimeSurvey
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Complex_Array_Handler {
    
    /**
     * ตรวจสอบและจัดการคำถาม Array ที่ซับซ้อน
     */
    public static function handle_complex_array($question_code, $response_data, $question_info) {
        // รวบรวมข้อมูลที่เกี่ยวข้องกับคำถามนี้
        $array_data = array();
        $pattern = '/^' . preg_quote($question_code) . '\[([^\]]+)\]$/';
        
        foreach ($response_data as $key => $value) {
            if (preg_match($pattern, $key, $matches) && !empty($value) && $value !== 'N') {
                $subkey = $matches[1];
                $array_data[$subkey] = $value;
            }
        }
        
        if (empty($array_data)) {
            return null;
        }
        
        // วิเคราะห์โครงสร้าง
        $structure = self::analyze_array_structure($array_data, $question_info);
        
        return array(
            'type' => 'complex_array',
            'question' => isset($question_info['question']) ? $question_info['question'] : $question_code,
            'structure' => $structure,
            'data' => $array_data
        );
    }
    
    /**
     * วิเคราะห์โครงสร้างของ Array
     */
    private static function analyze_array_structure($array_data, $question_info) {
        $structure = array(
            'rows' => array(),
            'columns' => array(),
            'type' => 'unknown'
        );
        
        // ตรวจสอบ pattern ของ keys
        $keys = array_keys($array_data);
        
        // Pattern 1: row_column (e.g., 1_1, 2_3)
        $row_col_pattern = true;
        $rows = array();
        $cols = array();
        
        foreach ($keys as $key) {
            if (preg_match('/^(\d+)_(\d+)$/', $key, $matches)) {
                $rows[$matches[1]] = true;
                $cols[$matches[2]] = true;
            } else {
                $row_col_pattern = false;
                break;
            }
        }
        
        if ($row_col_pattern) {
            $structure['type'] = 'row_column';
            $structure['rows'] = array_keys($rows);
            $structure['columns'] = array_keys($cols);
            
            // พยายามหา labels
            if (isset($question_info['subquestions'])) {
                foreach ($question_info['subquestions'] as $code => $sq_data) {
                    if (in_array($code, $structure['rows'])) {
                        $structure['row_labels'][$code] = $sq_data['question'];
                    }
                }
            }
            
            if (isset($question_info['answer_options'])) {
                foreach ($question_info['answer_options'] as $code => $label) {
                    if (in_array($code, $structure['columns'])) {
                        $structure['column_labels'][$code] = $label;
                    }
                }
            }
        }
        
        // Pattern 2: SQxxx format
        elseif (preg_match('/^SQ\d+/', $keys[0])) {
            $structure['type'] = 'subquestion';
            foreach ($keys as $key) {
                if (isset($question_info['subquestions'][$key])) {
                    $structure['items'][$key] = $question_info['subquestions'][$key]['question'];
                } else {
                    $structure['items'][$key] = $key;
                }
            }
        }
        
        return $structure;
    }
    
    /**
     * Render Complex Array
     */
    public static function render_complex_array($data) {
        if ($data['structure']['type'] === 'row_column') {
            self::render_row_column_array($data);
        } elseif ($data['structure']['type'] === 'subquestion') {
            self::render_subquestion_array($data);
        } else {
            self::render_generic_array($data);
        }
    }
    
    /**
     * Render Row-Column Array (เช่น ตารางอายุ x ประเภทบุคลากร)
     */
    private static function render_row_column_array($data) {
        $structure = $data['structure'];
        $values = $data['data'];
        
        // กำหนด labels เริ่มต้นสำหรับช่วงอายุ
        $age_ranges = array(
            '1' => '06.00 - 06.59 น.',
            '2' => '07.00 น.',
            '3' => '08.00 น.',
            '4' => '09.00 น.',
            '5' => '10.00 น.',
            '6' => '11.00 น.',
            '7' => '12.00 - 12.59 น.',
            '8' => '13.00 น.',
            '9' => '14.00 น.',
            '10' => '15.00 น.',
            '11' => '16.00 น.',
            '12' => '17.00 น.'
        );
        
        // กำหนด labels สำหรับประเภทบุคลากร
        $staff_types = array(
            '1' => 'หลักสูตรแก้ไขปัญหาฯ (ไม่ได้กำหนด)',
            '2' => 'เพื่อพัฒนาอาชีพ (1-สาขาเดียว, 2=หลายสาขา)',
            '3' => 'ระยะสั้น (รวม 1 ใน 3 รูปแบบ ปฏิบัติ)',
            '4' => 'หลักสูตรเพื่อพัฒนาทักษะ (ไม่ได้กำหนด)',
            '5' => 'การบริการชุมชน บ้าน-โรงเรียน-วัด 2=ไม่บ้าน-โรงเรียน',
            '6' => 'การบริการเพื่อพัฒนาทักษะ บ้าน-โรงเรียน-วัด 2=ไม่บ้าน-โรงเรียน'
        );
        
        ?>
        <div class="tpak-complex-array">
            <style>
                .tpak-complex-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 12px;
                    margin-top: 10px;
                }
                .tpak-complex-table th,
                .tpak-complex-table td {
                    border: 1px solid #ddd;
                    padding: 5px;
                    text-align: center;
                }
                .tpak-complex-table th {
                    background-color: #f5f5f5;
                    font-weight: bold;
                    font-size: 11px;
                }
                .tpak-complex-table td {
                    min-width: 50px;
                }
                .tpak-complex-table .row-header {
                    text-align: left;
                    background-color: #f9f9f9;
                    font-weight: 600;
                }
                .tpak-complex-table .group-header {
                    background-color: #e8e8e8;
                    font-weight: bold;
                }
                .tpak-complex-table .sub-header {
                    background-color: #f0f0f0;
                }
                .tpak-complex-table input[type="number"] {
                    width: 100%;
                    border: none;
                    text-align: center;
                    padding: 2px;
                }
                .gender-male {
                    color: #1e88e5;
                }
                .gender-female {
                    color: #e91e63;
                }
            </style>
            
            <table class="tpak-complex-table">
                <thead>
                    <tr>
                        <th rowspan="3" style="width: 150px;">ช่วงเวลาของแต่ละ<br/>(รวม 1 ใน บทดับใหม่ เดียว)</th>
                        <?php 
                        // Header สำหรับแต่ละประเภท
                        $col_count = count($structure['columns']);
                        $types_count = $col_count / 2; // สมมติว่าแบ่งเป็นชาย/หญิง
                        
                        for ($i = 0; $i < $types_count; $i++) {
                            echo '<th colspan="2" class="group-header">';
                            echo isset($staff_types[$i+1]) ? esc_html($staff_types[$i+1]) : 'ประเภท ' . ($i+1);
                            echo '</th>';
                        }
                        ?>
                    </tr>
                    <tr>
                        <?php 
                        // Sub-header สำหรับข้อมูลย่อย
                        for ($i = 0; $i < $types_count; $i++) {
                            echo '<th colspan="2" class="sub-header">ระยะเวลาสะสม บ้าน-โรงเรียน-วัด ข้าน บ้าน-โรงเรียน</th>';
                        }
                        ?>
                    </tr>
                    <tr>
                        <?php 
                        // Gender headers
                        for ($i = 0; $i < $types_count; $i++) {
                            echo '<th class="gender-male">ชาย</th>';
                            echo '<th class="gender-female">หญิง</th>';
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // แสดงข้อมูลแต่ละแถว
                    foreach ($structure['rows'] as $row) {
                        echo '<tr>';
                        
                        // Row header
                        echo '<td class="row-header">';
                        if (isset($structure['row_labels'][$row])) {
                            echo esc_html($structure['row_labels'][$row]);
                        } elseif (isset($age_ranges[$row])) {
                            echo esc_html($age_ranges[$row]);
                        } else {
                            echo 'แถว ' . esc_html($row);
                        }
                        echo '</td>';
                        
                        // Data cells
                        foreach ($structure['columns'] as $col) {
                            $key = $row . '_' . $col;
                            $value = isset($values[$key]) ? $values[$key] : '';
                            
                            $gender_class = (intval($col) % 2 == 1) ? 'gender-male' : 'gender-female';
                            
                            echo '<td class="' . $gender_class . '">';
                            if ($value !== '') {
                                echo '<strong>' . esc_html($value) . '</strong>';
                            } else {
                                echo '-';
                            }
                            echo '</td>';
                        }
                        
                        echo '</tr>';
                    }
                    ?>
                </tbody>
                
                <?php
                // คำนวณผลรวม
                $totals = array();
                foreach ($structure['columns'] as $col) {
                    $total = 0;
                    foreach ($structure['rows'] as $row) {
                        $key = $row . '_' . $col;
                        if (isset($values[$key]) && is_numeric($values[$key])) {
                            $total += intval($values[$key]);
                        }
                    }
                    $totals[$col] = $total;
                }
                
                if (array_sum($totals) > 0) {
                    ?>
                    <tfoot>
                        <tr>
                            <td class="row-header"><strong>รวม</strong></td>
                            <?php
                            foreach ($structure['columns'] as $col) {
                                $gender_class = (intval($col) % 2 == 1) ? 'gender-male' : 'gender-female';
                                echo '<td class="' . $gender_class . '"><strong>' . $totals[$col] . '</strong></td>';
                            }
                            ?>
                        </tr>
                    </tfoot>
                    <?php
                }
                ?>
            </table>
            
            <div style="margin-top: 10px; font-size: 11px; color: #666;">
                <p><strong>หมายเหตุ:</strong> ตารางแสดงจำนวนบุคลากรแยกตามช่วงเวลาและประเภท</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Subquestion Array
     */
    private static function render_subquestion_array($data) {
        ?>
        <div class="tpak-subquestion-array">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>รายการ</th>
                        <th>ค่า</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['structure']['items'] as $code => $label): ?>
                        <?php if (isset($data['data'][$code])): ?>
                        <tr>
                            <td><?php echo esc_html($label); ?></td>
                            <td><strong><?php echo esc_html($data['data'][$code]); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Generic Array
     */
    private static function render_generic_array($data) {
        ?>
        <div class="tpak-generic-array">
            <table class="tpak-array-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $key => $value): ?>
                    <tr>
                        <td><?php echo esc_html($key); ?></td>
                        <td><strong><?php echo esc_html($value); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * ตรวจสอบว่าเป็น Complex Array หรือไม่
     */
    public static function is_complex_array($question_code, $response_data) {
        $count = 0;
        $pattern = '/^' . preg_quote($question_code) . '\[([^\]]+)\]$/';
        
        foreach ($response_data as $key => $value) {
            if (preg_match($pattern, $key) && !empty($value) && $value !== 'N') {
                $count++;
            }
        }
        
        // ถ้ามีมากกว่า 10 items น่าจะเป็น complex array
        return $count > 10;
    }
}