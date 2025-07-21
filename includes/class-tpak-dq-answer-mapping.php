<?php
/**
 * ไฟล์: includes/class-tpak-dq-answer-mapping.php
 * จัดการ Mapping คำตอบจากรหัสเป็นข้อความ
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_Answer_Mapping {
    
    /**
     * Common answer mappings for Thai surveys
     */
    private static $common_mappings = array(
        // Yes/No/Uncertain
        'Y' => 'ใช่',
        'N' => 'ไม่ใช่',
        'U' => 'ไม่แน่ใจ',
        
        // Increase/Same/Decrease
        'I' => 'เพิ่มขึ้น',
        'S' => 'เท่าเดิม',
        'D' => 'ลดลง',
        
        // Agreement scale
        'A1' => 'เห็นด้วยอย่างยิ่ง',
        'A2' => 'เห็นด้วย',
        'A3' => 'ไม่แน่ใจ',
        'A4' => 'ไม่เห็นด้วย',
        'A5' => 'ไม่เห็นด้วยอย่างยิ่ง',
        
        // Selection
        '1' => 'เลือก',
        '0' => 'ไม่เลือก',
        '-oth-' => 'อื่นๆ',
        
        // Gender
        'M' => 'ชาย',
        'F' => 'หญิง',
        
        // Education levels (common codes)
        '1' => 'ประถมศึกษา',
        '2' => 'มัธยมศึกษาตอนต้น',
        '3' => 'มัธยมศึกษาตอนปลาย',
        '4' => 'อาชีวศึกษา',
        '5' => 'อุดมศึกษา',
        '6' => 'สูงกว่าปริญญาตรี',
        
        // Status
        'C' => 'สมบูรณ์',
        'I' => 'ไม่สมบูรณ์',
        'N' => 'ยังไม่เริ่ม',
    );
    
    /**
     * Question-specific mappings
     */
    private static $question_mappings = array(
        // ตัวอย่าง mapping สำหรับคำถามเฉพาะ
        'Q2' => array(
            '1' => 'นายปัญญา จิตหม่อม',
            '2' => 'นางสาวรัตนา สว่างเดือน',
            '3' => 'นายสมชาย ใจดี',
            '4' => 'นางสมหญิง รักเรียน',
            '5' => 'นายวิชา เก่งการ',
            // เพิ่มตามข้อมูลจริง
        ),
        
        // สถานะโรงเรียน
        'status' => array(
            '1' => 'เปิดดำเนินการปกติ',
            '2' => 'ปิดชั่วคราว',
            '3' => 'ปิดถาวร',
            '4' => 'รวมกับโรงเรียนอื่น',
        ),
        
        // ประเภทโรงเรียน
        'school_type' => array(
            '1' => 'โรงเรียนรัฐบาล',
            '2' => 'โรงเรียนเอกชน',
            '3' => 'โรงเรียนท้องถิ่น',
            '4' => 'โรงเรียนสาธิต',
        ),
    );
    
    /**
     * Get mapped answer value
     */
    public static function get_mapped_answer($value, $question_code = '', $question_info = null) {
        // ถ้าค่าว่าง
        if (empty($value) || $value === 'N') {
            return '';
        }
        
        // ลองหาจาก question info ก่อน
        if ($question_info && isset($question_info['answer_options'])) {
            if (isset($question_info['answer_options'][$value])) {
                return $question_info['answer_options'][$value];
            }
        }
        
        // ลองหาจาก question-specific mappings
        if (!empty($question_code)) {
            // แยก base code
            $base_code = preg_replace('/[\[\]_].*$/', '', $question_code);
            
            if (isset(self::$question_mappings[$base_code])) {
                if (isset(self::$question_mappings[$base_code][$value])) {
                    return self::$question_mappings[$base_code][$value];
                }
            }
        }
        
        // ลองหาจาก common mappings
        if (isset(self::$common_mappings[$value])) {
            return self::$common_mappings[$value];
        }
        
        // สำหรับตัวเลขที่อาจเป็น index
        if (is_numeric($value) && $value >= 1 && $value <= 20) {
            // อาจเป็น index ของคำตอบ ลองหาจาก context
            return self::guess_answer_from_context($value, $question_code);
        }
        
        return $value;
    }
    
    /**
     * Guess answer from context
     */
    private static function guess_answer_from_context($value, $question_code) {
        // ถ้าเป็นคำถามเกี่ยวกับระดับการศึกษา
        if (stripos($question_code, 'edu') !== false || stripos($question_code, 'ระดับ') !== false) {
            $edu_levels = array(
                '1' => 'ประถมศึกษา',
                '2' => 'มัธยมศึกษาตอนต้น',
                '3' => 'มัธยมศึกษาตอนปลาย',
                '4' => 'อาชีวศึกษา',
                '5' => 'อุดมศึกษา'
            );
            
            if (isset($edu_levels[$value])) {
                return $edu_levels[$value];
            }
        }
        
        // ถ้าเป็นคำถามเกี่ยวกับความพึงพอใจ
        if (stripos($question_code, 'satis') !== false || stripos($question_code, 'พอใจ') !== false) {
            $satisfaction = array(
                '1' => 'ไม่พอใจมาก',
                '2' => 'ไม่พอใจ',
                '3' => 'ปานกลาง',
                '4' => 'พอใจ',
                '5' => 'พอใจมาก'
            );
            
            if (isset($satisfaction[$value])) {
                return $satisfaction[$value];
            }
        }
        
        return $value;
    }
    
    /**
     * Add custom mapping
     */
    public static function add_mapping($question_code, $mappings) {
        if (!isset(self::$question_mappings[$question_code])) {
            self::$question_mappings[$question_code] = array();
        }
        
        self::$question_mappings[$question_code] = array_merge(
            self::$question_mappings[$question_code],
            $mappings
        );
    }
    
    /**
     * Import mappings from CSV
     */
    public static function import_mappings_from_csv($csv_file) {
        if (!file_exists($csv_file)) {
            return false;
        }
        
        $handle = fopen($csv_file, 'r');
        if (!$handle) {
            return false;
        }
        
        // Skip header
        fgetcsv($handle);
        
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 3) {
                $question_code = $data[0];
                $answer_code = $data[1];
                $answer_text = $data[2];
                
                if (!isset(self::$question_mappings[$question_code])) {
                    self::$question_mappings[$question_code] = array();
                }
                
                self::$question_mappings[$question_code][$answer_code] = $answer_text;
            }
        }
        
        fclose($handle);
        return true;
    }
    
    /**
     * Get all mappings for a question
     */
    public static function get_question_mappings($question_code) {
        $base_code = preg_replace('/[\[\]_].*$/', '', $question_code);
        
        if (isset(self::$question_mappings[$base_code])) {
            return self::$question_mappings[$base_code];
        }
        
        return array();
    }
    
    /**
     * Save mappings to database
     */
    public static function save_mappings_to_db($survey_id, $mappings) {
        $option_name = 'tpak_answer_mappings_' . $survey_id;
        return update_option($option_name, $mappings);
    }
    
    /**
     * Load mappings from database
     */
    public static function load_mappings_from_db($survey_id) {
        $option_name = 'tpak_answer_mappings_' . $survey_id;
        $mappings = get_option($option_name, array());
        
        if (is_array($mappings)) {
            foreach ($mappings as $question_code => $question_mappings) {
                self::$question_mappings[$question_code] = $question_mappings;
            }
        }
        
        return $mappings;
    }
}