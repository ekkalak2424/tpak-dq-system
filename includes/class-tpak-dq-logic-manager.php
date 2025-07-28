<?php
/**
 * TPAK DQ Logic Manager
 * 
 * จัดการ Conditional Logic, Skip Logic, Branching และ Piping
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logic Manager Class
 */
class TPAK_DQ_Logic_Manager {
    
    private $survey_data;
    private $response_data;
    private $logic_rules;
    
    public function __construct($survey_data = array(), $response_data = array()) {
        $this->survey_data = $survey_data;
        $this->response_data = $response_data;
        $this->logic_rules = $this->parse_logic_rules();
    }
    
    /**
     * Parse logic rules from survey data
     */
    private function parse_logic_rules() {
        $rules = array();
        
        if (empty($this->survey_data['questions'])) {
            return $rules;
        }
        
        foreach ($this->survey_data['questions'] as $question) {
            if (!empty($question['logic'])) {
                $rules[$question['code']] = $this->parse_question_logic($question);
            }
        }
        
        return $rules;
    }
    
    /**
     * Parse individual question logic
     */
    private function parse_question_logic($question) {
        $logic = array(
            'conditions' => array(),
            'actions' => array(),
            'skip_to' => null,
            'show_if' => array(),
            'hide_if' => array()
        );
        
        if (empty($question['logic'])) {
            return $logic;
        }
        
        $logic_data = $question['logic'];
        
        // Parse conditions
        if (!empty($logic_data['conditions'])) {
            foreach ($logic_data['conditions'] as $condition) {
                $logic['conditions'][] = array(
                    'question' => $condition['question'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                    'type' => $condition['type'] ?? 'exact'
                );
            }
        }
        
        // Parse actions
        if (!empty($logic_data['actions'])) {
            foreach ($logic_data['actions'] as $action) {
                $logic['actions'][] = array(
                    'type' => $action['type'],
                    'target' => $action['target'],
                    'value' => $action['value']
                );
            }
        }
        
        // Parse skip logic
        if (!empty($logic_data['skip_to'])) {
            $logic['skip_to'] = $logic_data['skip_to'];
        }
        
        // Parse show/hide conditions
        if (!empty($logic_data['show_if'])) {
            $logic['show_if'] = $logic_data['show_if'];
        }
        
        if (!empty($logic_data['hide_if'])) {
            $logic['hide_if'] = $logic_data['hide_if'];
        }
        
        return $logic;
    }
    
    /**
     * Check if question should be shown
     */
    public function should_show_question($question_code) {
        if (!isset($this->logic_rules[$question_code])) {
            return true;
        }
        
        $rules = $this->logic_rules[$question_code];
        
        // Check show conditions
        if (!empty($rules['show_if'])) {
            return $this->evaluate_conditions($rules['show_if']);
        }
        
        // Check hide conditions
        if (!empty($rules['hide_if'])) {
            return !$this->evaluate_conditions($rules['hide_if']);
        }
        
        return true;
    }
    
    /**
     * Get next question based on skip logic
     */
    public function get_next_question($current_question_code) {
        if (!isset($this->logic_rules[$current_question_code])) {
            return null;
        }
        
        $rules = $this->logic_rules[$current_question_code];
        
        if (!empty($rules['skip_to'])) {
            return $this->evaluate_skip_logic($rules['skip_to']);
        }
        
        return null;
    }
    
    /**
     * Evaluate conditions
     */
    private function evaluate_conditions($conditions) {
        if (empty($conditions)) {
            return true;
        }
        
        foreach ($conditions as $condition) {
            $result = $this->evaluate_single_condition($condition);
            
            if ($condition['operator'] === 'AND' && !$result) {
                return false;
            }
            
            if ($condition['operator'] === 'OR' && $result) {
                return true;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate single condition
     */
    private function evaluate_single_condition($condition) {
        $question_code = $condition['question'];
        $operator = $condition['operator'];
        $expected_value = $condition['value'];
        $type = $condition['type'] ?? 'exact';
        
        $actual_value = $this->get_response_value($question_code);
        
        switch ($operator) {
            case 'equals':
                return $this->compare_values($actual_value, $expected_value, 'exact');
                
            case 'not_equals':
                return !$this->compare_values($actual_value, $expected_value, 'exact');
                
            case 'contains':
                return $this->compare_values($actual_value, $expected_value, 'contains');
                
            case 'not_contains':
                return !$this->compare_values($actual_value, $expected_value, 'contains');
                
            case 'greater_than':
                return $this->compare_values($actual_value, $expected_value, 'gt');
                
            case 'less_than':
                return $this->compare_values($actual_value, $expected_value, 'lt');
                
            case 'greater_than_or_equal':
                return $this->compare_values($actual_value, $expected_value, 'gte');
                
            case 'less_than_or_equal':
                return $this->compare_values($actual_value, $expected_value, 'lte');
                
            case 'is_empty':
                return empty($actual_value);
                
            case 'is_not_empty':
                return !empty($actual_value);
                
            default:
                return false;
        }
    }
    
    /**
     * Compare values based on type
     */
    private function compare_values($actual, $expected, $type) {
        switch ($type) {
            case 'exact':
                return $actual === $expected;
                
            case 'contains':
                return strpos($actual, $expected) !== false;
                
            case 'gt':
                return floatval($actual) > floatval($expected);
                
            case 'lt':
                return floatval($actual) < floatval($expected);
                
            case 'gte':
                return floatval($actual) >= floatval($expected);
                
            case 'lte':
                return floatval($actual) <= floatval($expected);
                
            default:
                return false;
        }
    }
    
    /**
     * Get response value for question
     */
    private function get_response_value($question_code) {
        // Handle array responses
        if (strpos($question_code, '[') !== false) {
            $parts = explode('[', $question_code);
            $main_question = $parts[0];
            $sub_question = rtrim($parts[1], ']');
            
            return $this->response_data[$main_question][$sub_question] ?? '';
        }
        
        return $this->response_data[$question_code] ?? '';
    }
    
    /**
     * Evaluate skip logic
     */
    private function evaluate_skip_logic($skip_rules) {
        foreach ($skip_rules as $rule) {
            if ($this->evaluate_conditions($rule['conditions'])) {
                return $rule['target'];
            }
        }
        
        return null;
    }
    
    /**
     * Get filtered questions based on logic
     */
    public function get_filtered_questions() {
        $filtered_questions = array();
        
        if (empty($this->survey_data['questions'])) {
            return $filtered_questions;
        }
        
        foreach ($this->survey_data['questions'] as $question) {
            if ($this->should_show_question($question['code'])) {
                $filtered_questions[] = $question;
            }
        }
        
        return $filtered_questions;
    }
    
    /**
     * Apply piping to question text
     */
    public function apply_piping($text, $question_code = '') {
        if (empty($text)) {
            return $text;
        }
        
        // Find all piping placeholders {QUESTION_CODE}
        preg_match_all('/\{([^}]+)\}/', $text, $matches);
        
        if (empty($matches[1])) {
            return $text;
        }
        
        $processed_text = $text;
        
        foreach ($matches[1] as $placeholder) {
            $value = $this->get_piped_value($placeholder);
            $processed_text = str_replace('{' . $placeholder . '}', $value, $processed_text);
        }
        
        return $processed_text;
    }
    
    /**
     * Get piped value for placeholder
     */
    private function get_piped_value($placeholder) {
        // Handle different piping formats
        if (strpos($placeholder, '.') !== false) {
            $parts = explode('.', $placeholder);
            $question_code = $parts[0];
            $attribute = $parts[1];
            
            return $this->get_response_value($question_code);
        }
        
        return $this->get_response_value($placeholder);
    }
    
    /**
     * Get question dependencies
     */
    public function get_question_dependencies($question_code) {
        $dependencies = array();
        
        if (!isset($this->logic_rules[$question_code])) {
            return $dependencies;
        }
        
        $rules = $this->logic_rules[$question_code];
        
        // Get dependencies from conditions
        if (!empty($rules['show_if'])) {
            foreach ($rules['show_if'] as $condition) {
                if (!empty($condition['question'])) {
                    $dependencies[] = $condition['question'];
                }
            }
        }
        
        if (!empty($rules['hide_if'])) {
            foreach ($rules['hide_if'] as $condition) {
                if (!empty($condition['question'])) {
                    $dependencies[] = $condition['question'];
                }
            }
        }
        
        return array_unique($dependencies);
    }
    
    /**
     * Get affected questions when a response changes
     */
    public function get_affected_questions($changed_question_code) {
        $affected = array();
        
        foreach ($this->logic_rules as $question_code => $rules) {
            $dependencies = $this->get_question_dependencies($question_code);
            
            if (in_array($changed_question_code, $dependencies)) {
                $affected[] = $question_code;
            }
        }
        
        return $affected;
    }
    
    /**
     * Validate logic rules
     */
    public function validate_logic_rules() {
        $errors = array();
        
        foreach ($this->logic_rules as $question_code => $rules) {
            // Check for circular dependencies
            if ($this->has_circular_dependency($question_code)) {
                $errors[] = "Circular dependency detected for question: {$question_code}";
            }
            
            // Check for invalid question references
            if ($this->has_invalid_references($question_code, $rules)) {
                $errors[] = "Invalid question reference in logic for: {$question_code}";
            }
        }
        
        return $errors;
    }
    
    /**
     * Check for circular dependencies
     */
    private function has_circular_dependency($question_code, $visited = array()) {
        if (in_array($question_code, $visited)) {
            return true;
        }
        
        $visited[] = $question_code;
        $dependencies = $this->get_question_dependencies($question_code);
        
        foreach ($dependencies as $dependency) {
            if ($this->has_circular_dependency($dependency, $visited)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for invalid references
     */
    private function has_invalid_references($question_code, $rules) {
        $valid_questions = array_keys($this->survey_data['questions']);
        
        if (!empty($rules['show_if'])) {
            foreach ($rules['show_if'] as $condition) {
                if (!empty($condition['question']) && !in_array($condition['question'], $valid_questions)) {
                    return true;
                }
            }
        }
        
        if (!empty($rules['hide_if'])) {
            foreach ($rules['hide_if'] as $condition) {
                if (!empty($condition['question']) && !in_array($condition['question'], $valid_questions)) {
                    return true;
                }
            }
        }
        
        return false;
    }
} 