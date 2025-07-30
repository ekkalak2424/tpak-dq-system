<?php
/**
 * ไฟล์: includes/class-tpak-dq-limesurvey-question-mapper.php
 * จัดการ Mapping คำถามจาก LimeSurvey อย่างสมบูรณ์
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

class TPAK_DQ_LimeSurvey_Question_Mapper {
    
    private $api_client;
    private $question_cache = array();
    
    /**
     * ดึงโครงสร้างคำถามแบบสมบูรณ์จาก LimeSurvey
     */
    public function get_complete_survey_structure($survey_id) {
        try {
            // Initialize API
            $api_url = get_option('tpak_api_url');
            $username = get_option('tpak_api_username');
            $password = get_option('tpak_api_password');
            
            if (!$api_url || !$username || !$password) {
                return false;
            }
            
            require_once TPAK_DQ_PLUGIN_DIR . 'includes/class-tpak-dq-import.php';
            $this->api_client = new LimeSurveyAPIClient($api_url, $username, $password);
            
            $session_key = $this->api_client->get_session_key();
            if (!$session_key) {
                return false;
            }
            
            // Get survey info
            $survey_info = $this->api_client->get_survey_properties($session_key, $survey_id);
            
            // Get all groups
            $groups = $this->get_survey_groups($session_key, $survey_id);
            
            // Get all questions with complete details
            $questions = $this->get_all_questions_with_details($session_key, $survey_id);
            
            // Build complete structure
            $structure = array(
                'survey_id' => $survey_id,
                'title' => $survey_info['surveyls_title'] ?? 'Survey ' . $survey_id,
                'description' => $survey_info['surveyls_description'] ?? '',
                'groups' => $groups,
                'questions' => $questions['main_questions'],
                'subquestions' => $questions['subquestions'],
                'answer_options' => $questions['answer_options'],
                'question_attributes' => $questions['attributes'],
                'last_updated' => current_time('mysql')
            );
            
            $this->api_client->release_session_key($session_key);
            
            // Save structure to database for caching
            $this->save_survey_structure($survey_id, $structure);
            
            return $structure;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * ดึงข้อมูลกลุ่มคำถาม
     */
    private function get_survey_groups($session_key, $survey_id) {
        $groups = array();
        
        try {
            $result = $this->api_client->list_groups($session_key, $survey_id);
            
            if (is_array($result)) {
                foreach ($result as $group) {
                    $gid = $group['gid'];
                    $groups[$gid] = array(
                        'gid' => $gid,
                        'group_name' => $group['group_name'] ?? '',
                        'group_order' => $group['group_order'] ?? 0,
                        'description' => $group['description'] ?? ''
                    );
                }
            }
        } catch (Exception $e) {
            // Error handled silently for performance
        }
        
        return $groups;
    }
    
    /**
     * ดึงคำถามทั้งหมดพร้อมรายละเอียด
     */
    private function get_all_questions_with_details($session_key, $survey_id) {
        $main_questions = array();
        $subquestions = array();
        $answer_options = array();
        $attributes = array();
        
        try {
            // Get all questions
            $questions = $this->api_client->list_questions($session_key, $survey_id);
            
            if (!is_array($questions)) {
                return compact('main_questions', 'subquestions', 'answer_options', 'attributes');
            }
            
            // Process each question
            foreach ($questions as $question) {
                $qid = $question['qid'];
                $parent_qid = $question['parent_qid'] ?? 0;
                
                // Get full question properties
                $q_props = $this->api_client->get_question_properties($session_key, $qid);
                
                if (!$q_props) continue;
                
                $title = $q_props['title'] ?? '';
                $type = $q_props['type'] ?? '';
                
                if ($parent_qid == 0) {
                    // Main question
                    $main_questions[$title] = array(
                        'qid' => $qid,
                        'gid' => $q_props['gid'] ?? '',
                        'type' => $type,
                        'title' => $title,
                        'question' => $this->clean_html($q_props['question'] ?? ''),
                        'help' => $this->clean_html($q_props['help'] ?? ''),
                        'mandatory' => $q_props['mandatory'] ?? 'N',
                        'other' => $q_props['other'] ?? 'N',
                        'question_order' => $q_props['question_order'] ?? 0,
                        'relevance' => $q_props['relevance'] ?? '1'
                    );
                    
                    // Get answer options based on question type
                    if ($this->needs_answer_options($type)) {
                        $answer_options[$title] = $this->get_question_answer_options($session_key, $qid, $type);
                    }
                    
                    // Get question attributes
                    $attributes[$title] = $this->get_question_attributes($session_key, $qid);
                    
                } else {
                    // Subquestion
                    $parent_title = $this->find_parent_title($questions, $parent_qid);
                    
                    if ($parent_title) {
                        if (!isset($subquestions[$parent_title])) {
                            $subquestions[$parent_title] = array();
                        }
                        
                        $subquestions[$parent_title][$title] = array(
                            'qid' => $qid,
                            'title' => $title,
                            'question' => $this->clean_html($q_props['question'] ?? ''),
                            'question_order' => $q_props['question_order'] ?? 0
                        );
                        
                        // For array questions, subquestions might have answer options
                        if (in_array($type, array('1', 'F', 'H', ':'))) {
                            $sub_answers = $this->get_question_answer_options($session_key, $qid, $type);
                            if (!empty($sub_answers)) {
                                if (!isset($answer_options[$parent_title])) {
                                    $answer_options[$parent_title] = array();
                                }
                                // Store subquestion answers with special key
                                $answer_options[$parent_title]['_subquestion_' . $title] = $sub_answers;
                            }
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            // Error handled silently for performance
        }
        
        return compact('main_questions', 'subquestions', 'answer_options', 'attributes');
    }
    
    /**
     * ดึงตัวเลือกคำตอบของคำถาม
     */
    private function get_question_answer_options($session_key, $qid, $type) {
        $options = array();
        
        try {
            // Method 1: Try list_question_answers
            $params = array(
                'method' => 'list_question_answers',
                'params' => array($session_key, intval($qid)),
                'id' => 1
            );
            
            $response = $this->api_client->send_request($params);
            
            if (isset($response['result']) && is_array($response['result'])) {
                foreach ($response['result'] as $answer) {
                    $code = $answer['code'] ?? '';
                    $text = $this->clean_html($answer['answer'] ?? '');
                    
                    if ($code && $text) {
                        $options[$code] = $text;
                        
                        // Also store by sortorder if available
                        if (isset($answer['sortorder'])) {
                            $options[$answer['sortorder']] = $text;
                        }
                    }
                }
            }
            
            // Method 2: For specific question types, get from properties
            if (empty($options)) {
                $q_props = $this->api_client->get_question_properties($session_key, $qid, array('answeroptions'));
                
                if (isset($q_props['answeroptions']) && is_array($q_props['answeroptions'])) {
                    foreach ($q_props['answeroptions'] as $code => $answer_data) {
                        if (is_array($answer_data) && isset($answer_data['answer'])) {
                            $options[$code] = $this->clean_html($answer_data['answer']);
                        }
                    }
                }
            }
            
            // Add default options for specific types
            if (empty($options)) {
                $options = $this->get_default_answer_options($type);
            }
            
        } catch (Exception $e) {
            // Error handled silently for performance
        }
        
        return $options;
    }
    
    /**
     * ดึง question attributes
     */
    private function get_question_attributes($session_key, $qid) {
        $attributes = array();
        
        try {
            // Get specific attributes
            $important_attrs = array(
                'array_filter', 'array_filter_exclude', 'em_validation_q',
                'em_validation_q_tip', 'hide_tip', 'hidden', 'max_answers',
                'min_answers', 'other_replace_text', 'page_break',
                'public_statistics', 'random_order', 'scale_export'
            );
            
            foreach ($important_attrs as $attr) {
                $result = $this->api_client->get_question_properties($session_key, $qid, array($attr));
                if (isset($result[$attr]) && !empty($result[$attr])) {
                    $attributes[$attr] = $result[$attr];
                }
            }
            
        } catch (Exception $e) {
            // Error handled silently for performance
        }
        
        return $attributes;
    }
    
    /**
     * Map response data with survey structure
     */
    public function map_response_with_structure($response_data, $survey_structure) {
        $mapped_data = array();
        
        if (!is_array($response_data) || !is_array($survey_structure)) {
            return $mapped_data;
        }
        
        $questions = $survey_structure['questions'] ?? array();
        $subquestions = $survey_structure['subquestions'] ?? array();
        $answer_options = $survey_structure['answer_options'] ?? array();
        
        foreach ($response_data as $key => $value) {
            // Skip system fields
            if ($this->is_system_field($key)) {
                $mapped_data['_system'][$key] = $value;
                continue;
            }
            
            // Parse the question code
            $parsed = $this->parse_question_code($key);
            $base_code = $parsed['base'];
            $sub_code = $parsed['sub'];
            $scale = $parsed['scale'];
            
            // Find question info
            $question_info = $questions[$base_code] ?? null;
            
            if (!$question_info) {
                // Try to find in subquestions
                foreach ($subquestions as $parent => $subs) {
                    if (isset($subs[$base_code])) {
                        $question_info = $subs[$base_code];
                        $question_info['parent'] = $parent;
                        break;
                    }
                }
            }
            
            // Build mapped entry
            $entry = array(
                'code' => $key,
                'value' => $value,
                'base_code' => $base_code,
                'question' => $question_info['question'] ?? $base_code,
                'type' => $question_info['type'] ?? 'unknown',
                'mandatory' => $question_info['mandatory'] ?? 'N'
            );
            
            // Add subquestion info if applicable
            if ($sub_code && isset($subquestions[$base_code][$sub_code])) {
                $entry['subquestion'] = $subquestions[$base_code][$sub_code]['question'];
            }
            
            // Map answer value to text
            if (!empty($value) && $value !== 'N') {
                $entry['answer_text'] = $this->get_answer_text($value, $base_code, $sub_code, $answer_options);
            }
            
            // Categorize by question type
            if ($question_info) {
                $category = $this->categorize_question($question_info['type']);
                $mapped_data[$category][$base_code][] = $entry;
            } else {
                $mapped_data['unknown'][$key] = $entry;
            }
        }
        
        return $mapped_data;
    }
    
    /**
     * Helper functions
     */
    
    private function clean_html($text) {
        // Remove HTML tags but keep the text
        $text = strip_tags($text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Clean up whitespace
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return $text;
    }
    
    private function needs_answer_options($type) {
        return in_array($type, array(
            'L', '!', 'O', 'F', 'H', 'M', 'P', 'C', 'E', 
            'A', 'B', '1', ':', 'K', 'R', '5'
        ));
    }
    
    private function get_default_answer_options($type) {
        switch ($type) {
            case 'Y':
                return array('Y' => 'ใช่', 'N' => 'ไม่ใช่');
            case 'G':
                return array('M' => 'ชาย', 'F' => 'หญิง');
            case 'C':
                return array('Y' => 'ใช่', 'N' => 'ไม่ใช่', 'U' => 'ไม่แน่ใจ');
            case 'E':
                return array('I' => 'เพิ่มขึ้น', 'S' => 'เท่าเดิม', 'D' => 'ลดลง');
            case '5':
                return array(
                    '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5'
                );
            default:
                return array();
        }
    }
    
    private function find_parent_title($questions, $parent_qid) {
        foreach ($questions as $question) {
            if ($question['qid'] == $parent_qid) {
                return $question['title'] ?? null;
            }
        }
        return null;
    }
    
    private function is_system_field($key) {
        return in_array($key, array(
            'id', 'tid', 'token', 'submitdate', 'lastpage', 
            'startlanguage', 'completed', 'startdate', 
            'datestamp', 'ipaddr', 'refurl', 'seed'
        ));
    }
    
    private function parse_question_code($code) {
        $result = array(
            'base' => $code,
            'sub' => null,
            'scale' => null
        );
        
        // Pattern: BASE[SUB] or BASE[SUB][SCALE]
        if (preg_match('/^([A-Za-z0-9]+)(?:\[([^\]]+)\])?(?:\[([^\]]+)\])?/', $code, $matches)) {
            $result['base'] = $matches[1];
            $result['sub'] = $matches[2] ?? null;
            $result['scale'] = $matches[3] ?? null;
        }
        
        return $result;
    }
    
    private function get_answer_text($value, $base_code, $sub_code, $answer_options) {
        // Try direct lookup
        if (isset($answer_options[$base_code][$value])) {
            return $answer_options[$base_code][$value];
        }
        
        // Try subquestion specific answers
        if ($sub_code && isset($answer_options[$base_code]['_subquestion_' . $sub_code][$value])) {
            return $answer_options[$base_code]['_subquestion_' . $sub_code][$value];
        }
        
        // Return original value if no mapping found
        return $value;
    }
    
    private function categorize_question($type) {
        $categories = array(
            'text' => array('S', 'T', 'U', 'Q'),
            'choice' => array('L', '!', 'O', 'M', 'P'),
            'array' => array('F', 'H', 'A', 'B', 'C', 'E', ':', ';', '1'),
            'numeric' => array('N', 'K'),
            'date' => array('D'),
            'ranking' => array('R'),
            'other' => array('Y', 'G', 'X', '|', '*')
        );
        
        foreach ($categories as $category => $types) {
            if (in_array($type, $types)) {
                return $category;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Save survey structure to database
     */
    private function save_survey_structure($survey_id, $structure) {
        $option_name = 'tpak_survey_structure_' . $survey_id;
        return update_option($option_name, $structure);
    }
    
    /**
     * Get saved survey structure
     */
    public function get_saved_survey_structure($survey_id) {
        $option_name = 'tpak_survey_structure_' . $survey_id;
        return get_option($option_name, false);
    }
}