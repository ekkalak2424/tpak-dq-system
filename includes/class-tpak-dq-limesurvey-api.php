<?php
/**
 * TPAK DQ LimeSurvey API Connector
 * 
 * เชื่อมต่อกับ LimeSurvey API เพื่อดึงข้อมูล Survey
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LimeSurvey API Connector Class
 */
class TPAK_DQ_LimeSurvey_API {
    
    private $api_url;
    private $username;
    private $password;
    private $session_key;
    
    public function __construct() {
        // Get API settings from WordPress options
        $this->api_url = get_option('tpak_limesurvey_api_url', '');
        $this->username = get_option('tpak_limesurvey_username', '');
        $this->password = get_option('tpak_limesurvey_password', '');
        
        add_action('admin_menu', array($this, 'add_api_settings_page'));
        add_action('wp_ajax_tpak_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_tpak_sync_survey_from_api', array($this, 'ajax_sync_survey_from_api'));
    }
    
    /**
     * Add API settings page
     */
    public function add_api_settings_page() {
        add_submenu_page(
            'edit.php?post_type=tpak_verification',
            'LimeSurvey API Settings',
            'API Settings',
            'manage_options',
            'tpak-api-settings',
            array($this, 'render_api_settings_page')
        );
    }
    
    /**
     * Render API settings page
     */
    public function render_api_settings_page() {
        // Save settings if form is submitted
        if (isset($_POST['submit'])) {
            check_admin_referer('tpak_api_settings', 'tpak_api_nonce');
            
            update_option('tpak_limesurvey_api_url', sanitize_text_field($_POST['api_url']));
            update_option('tpak_limesurvey_username', sanitize_text_field($_POST['username']));
            update_option('tpak_limesurvey_password', sanitize_text_field($_POST['password']));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $api_url = get_option('tpak_limesurvey_api_url', '');
        $username = get_option('tpak_limesurvey_username', '');
        $password = get_option('tpak_limesurvey_password', '');
        ?>
        <div class="wrap">
            <h1>LimeSurvey API Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('tpak_api_settings', 'tpak_api_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="api_url">API URL</label></th>
                        <td>
                            <input type="url" name="api_url" id="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text" required />
                            <p class="description">Example: https://your-limesurvey.com/admin/remotecontrol</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="username">Username</label></th>
                        <td>
                            <input type="text" name="username" id="username" value="<?php echo esc_attr($username); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="password">Password</label></th>
                        <td>
                            <input type="password" name="password" id="password" value="<?php echo esc_attr($password); ?>" class="regular-text" required />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Save Settings" />
                    <button type="button" class="button button-secondary" id="test-connection">Test Connection</button>
                </p>
            </form>
            
            <div id="test-result" style="display:none;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                var button = $(this);
                var resultDiv = $('#test-result');
                
                button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tpak_test_api_connection',
                        nonce: '<?php echo wp_create_nonce('tpak_test_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                        }
                        resultDiv.show();
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p>Connection test failed</p></div>');
                        resultDiv.show();
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Test API connection
     */
    public function ajax_test_api_connection() {
        check_ajax_referer('tpak_test_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $result = $this->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'API connection successful! Session key: ' . $result['session_key']));
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * Test connection to LimeSurvey API
     */
    public function test_connection() {
        if (empty($this->api_url) || empty($this->username) || empty($this->password)) {
            return array('success' => false, 'message' => 'API settings not configured');
        }
        
        try {
            $session_key = $this->get_session_key();
            
            if ($session_key) {
                return array('success' => true, 'session_key' => $session_key);
            } else {
                return array('success' => false, 'message' => 'Failed to authenticate with LimeSurvey API');
            }
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'API Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get session key from LimeSurvey API
     */
    private function get_session_key() {
        if ($this->session_key) {
            return $this->session_key;
        }
        
        $request_data = array(
            'method' => 'get_session_key',
            'params' => array(
                'username' => $this->username,
                'password' => $this->password
            ),
            'id' => 1
        );
        
        $response = $this->make_api_request($request_data);
        
        if ($response && isset($response['result'])) {
            $this->session_key = $response['result'];
            return $this->session_key;
        }
        
        return false;
    }
    
    /**
     * Get survey list from API
     */
    public function get_survey_list() {
        $session_key = $this->get_session_key();
        
        if (!$session_key) {
            return false;
        }
        
        $request_data = array(
            'method' => 'list_surveys',
            'params' => array(
                'sSessionKey' => $session_key,
                'iUserID' => 1
            ),
            'id' => 1
        );
        
        $response = $this->make_api_request($request_data);
        
        if ($response && isset($response['result'])) {
            return $response['result'];
        }
        
        return false;
    }
    
    /**
     * Get survey structure from API
     */
    public function get_survey_structure($survey_id) {
        $session_key = $this->get_session_key();
        
        if (!$session_key) {
            return false;
        }
        
        // Get survey properties
        $properties_request = array(
            'method' => 'get_survey_properties',
            'params' => array(
                'sSessionKey' => $session_key,
                'iSurveyID' => $survey_id,
                'aSurveySettings' => array('title', 'description', 'language')
            ),
            'id' => 1
        );
        
        $properties_response = $this->make_api_request($properties_request);
        
        // Get survey questions
        $questions_request = array(
            'method' => 'list_questions',
            'params' => array(
                'sSessionKey' => $session_key,
                'iSurveyID' => $survey_id,
                'iGroupID' => null,
                'sLanguage' => 'en'
            ),
            'id' => 1
        );
        
        $questions_response = $this->make_api_request($questions_request);
        
        // Get question properties
        $question_properties = array();
        if ($questions_response && isset($questions_response['result'])) {
            foreach ($questions_response['result'] as $question) {
                $qid = $question['qid'];
                
                $question_prop_request = array(
                    'method' => 'get_question_properties',
                    'params' => array(
                        'sSessionKey' => $session_key,
                        'iQuestionID' => $qid,
                        'aQuestionSettings' => array('question', 'help', 'type', 'mandatory', 'other')
                    ),
                    'id' => 1
                );
                
                $question_prop_response = $this->make_api_request($question_prop_request);
                
                if ($question_prop_response && isset($question_prop_response['result'])) {
                    $question_properties[$qid] = $question_prop_response['result'];
                }
            }
        }
        
        // Get answer options
        $answers_request = array(
            'method' => 'list_questions',
            'params' => array(
                'sSessionKey' => $session_key,
                'iSurveyID' => $survey_id,
                'iGroupID' => null,
                'sLanguage' => 'en'
            ),
            'id' => 1
        );
        
        $answers_response = $this->make_api_request($answers_request);
        
        // Convert to our format
        $structure = array(
            'survey_id' => $survey_id,
            'title' => $properties_response['result']['title'] ?? '',
            'description' => $properties_response['result']['description'] ?? '',
            'questions' => array(),
            'answers' => array(),
            'subquestions' => array(),
            'groups' => array()
        );
        
        // Process questions
        if ($questions_response && isset($questions_response['result'])) {
            foreach ($questions_response['result'] as $question) {
                $qid = $question['qid'];
                $question_data = $question_properties[$qid] ?? array();
                
                $structure['questions'][] = array(
                    'qid' => $qid,
                    'gid' => $question['gid'],
                    'type' => $question['type'],
                    'title' => $question['title'],
                    'question' => $question_data['question'] ?? '',
                    'help' => $question_data['help'] ?? '',
                    'mandatory' => $question_data['mandatory'] ?? 'N',
                    'other' => $question_data['other'] ?? 'N',
                    'question_order' => $question['question_order']
                );
            }
        }
        
        return $structure;
    }
    
    /**
     * Make API request to LimeSurvey
     */
    private function make_api_request($data) {
        if (empty($this->api_url)) {
            throw new Exception('API URL not configured');
        }
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode($data),
            'cookies' => array()
        );
        
        $response = wp_remote_post($this->api_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('HTTP Error: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response');
        }
        
        if (isset($result['error'])) {
            throw new Exception('API Error: ' . $result['error']['message']);
        }
        
        return $result;
    }
    
    /**
     * Sync survey from API
     */
    public function ajax_sync_survey_from_api() {
        check_ajax_referer('tpak_sync_api', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $survey_id = intval($_POST['survey_id'] ?? 0);
        
        if (!$survey_id) {
            wp_send_json_error('Invalid Survey ID');
        }
        
        try {
            $structure = $this->get_survey_structure($survey_id);
            
            if ($structure) {
                // Save to database
                $structure_manager = new TPAK_DQ_Survey_Structure_Manager();
                $structure_manager->save_survey_structure($survey_id, $structure);
                
                wp_send_json_success(array(
                    'message' => 'Survey synced successfully from API',
                    'survey_id' => $survey_id,
                    'structure' => $structure
                ));
            } else {
                wp_send_json_error('Failed to get survey structure from API');
            }
        } catch (Exception $e) {
            wp_send_json_error('API Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Release session key
     */
    public function release_session() {
        if ($this->session_key) {
            $request_data = array(
                'method' => 'release_session_key',
                'params' => array(
                    'sSessionKey' => $this->session_key
                ),
                'id' => 1
            );
            
            $this->make_api_request($request_data);
            $this->session_key = null;
        }
    }
    
    /**
     * Destructor to release session
     */
    public function __destruct() {
        $this->release_session();
    }
} 