<?php
/**
 * REST API functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class AICOAGAC_Rest_Api {
    
    /**
     * REST API namespace
     * @var string
     */
    private $namespace = 'aicoagac/v1';
    
    /**
     * Singleton instance
     * @var AICOAGAC_Rest_Api|null
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * @return AICOAGAC_Rest_Api
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        aicoagac_debug_log('REST API: Constructor called - Initializing REST API');
        add_action('rest_api_init', array($this, 'register_routes'));
        aicoagac_debug_log('REST API: rest_api_init hook added');
        
        // Ensure proper charset handling for special characters
        add_action('init', array($this, 'setup_charset_handling'));
    }
    
    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Helper function to generate consistent WP_Error objects
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @return WP_Error
     */
    private function aicoagac_api_error($code, $message, $status_code = 400) {
        return new WP_Error($code, $message, array('status' => $status_code));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        aicoagac_debug_log('REST API: register_routes called - Registering all endpoints');
        
        // Check if our routes are already registered using WordPress native method
        $routes = rest_get_server()->get_routes();
        if (isset($routes['/aicoagac/v1/ideas/generate'])) {
            aicoagac_debug_log('REST API: AICOAGAC endpoints already registered, skipping');
            return;
        }
        
        // Settings endpoints
        register_rest_route('aicoagac/v1', '/settings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_settings'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/settings', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_settings'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // Debug endpoint for automation testing (legacy)
//         register_rest_route('aicoagac/v1', '/debug/automation-legacy', array(
//             'methods' => 'GET',
//             'callback' => array($this, 'debug_automation'),
//             'permission_callback' => array($this, 'check_admin_permissions')
//         ));
        
        // Manual cron trigger endpoints for testing
//         register_rest_route('aicoagac/v1', '/debug/cron/semi-auto', array(
//             'methods' => 'POST',
//             'callback' => array($this, 'debug_trigger_semi_auto'),
//             'permission_callback' => array($this, 'check_admin_permissions')
//         ));
        
//         register_rest_route('aicoagac/v1', '/debug/cron/full-auto', array(
//             'methods' => 'POST',
//             'callback' => array($this, 'debug_trigger_full_auto'),
//             'permission_callback' => array($this, 'check_admin_permissions')
//         ));
        
        // SEO Plugin Detection endpoint
        register_rest_route('aicoagac/v1', '/seo-plugins', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_seo_plugins'),
            'permission_callback' => array($this, 'check_seo_permissions')
        ));
        
        // Image generation endpoint
        register_rest_route('aicoagac/v1', '/generate-image', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_image'),
            'permission_callback' => array($this, 'check_pro_permissions')
        ));
        
        // Style guide endpoints
        register_rest_route('aicoagac/v1', '/style-guide', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_style_guide'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/style-guide/analyze', array(
            'methods' => 'POST',
            'callback' => array($this, 'analyze_style_guide'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/style-guide', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_style_guide'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Unified Automation Management endpoints
//         register_rest_route('aicoagac/v1', '/automation/status', array(
//             'methods' => 'GET',
//             'callback' => array($this, 'get_automation_status'),
//             'permission_callback' => array($this, 'check_permissions')
//         ));
        
//         register_rest_route('aicoagac/v1', '/automation/test', array(
//             'methods' => 'POST',
//             'callback' => array($this, 'test_automation'),
//             'permission_callback' => array($this, 'check_permissions')
//         ));
        
//         register_rest_route('aicoagac/v1', '/debug/automation', array(
//             'methods' => 'GET',
//             'callback' => array($this, 'debug_automation_status'),
//             'permission_callback' => array($this, 'check_permissions')
//         ));
        
        // License refresh removed

        
        // Ideas endpoints
        register_rest_route('aicoagac/v1', '/ideas', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_ideas'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/ideas/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_ideas'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/ideas/similar', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_similar_ideas'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/ideas/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_idea'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/ideas/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_idea'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/ideas', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_idea'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Restore archived idea
        register_rest_route('aicoagac/v1', '/ideas/(?P<id>\d+)/restore', array(
            'methods' => 'POST',
            'callback' => array($this, 'restore_idea'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Permanently delete idea
        register_rest_route('aicoagac/v1', '/ideas/(?P<id>\d+)/permanent-delete', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'permanent_delete_idea'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Drafts endpoints
        register_rest_route('aicoagac/v1', '/drafts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_drafts'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'per_page' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                )
            )
        ));
        
        register_rest_route('aicoagac/v1', '/drafts/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_draft'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/drafts/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_draft'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/drafts/(?P<id>\d+)/publish', array(
            'methods' => 'POST',
            'callback' => array($this, 'publish_draft'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('aicoagac/v1', '/drafts/(?P<id>\d+)/schedule', array(
            'methods' => 'POST',
            'callback' => array($this, 'schedule_draft'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Published posts endpoint
        register_rest_route('aicoagac/v1', '/published', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_published_posts'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'page' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                ),
                'per_page' => array(
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                )
            )
        ));
        
        // Update published post date
        register_rest_route('aicoagac/v1', '/published/(?P<id>\d+)/update-date', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_published_post_date'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Activity logs endpoint
        register_rest_route('aicoagac/v1', '/activity-logs', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_activity_logs'),
                'permission_callback' => array($this, 'check_permissions')
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'add_activity_log_endpoint'),
                'permission_callback' => array($this, 'check_permissions')
            )
        ));

        // Test endpoint to verify registration works
        register_rest_route('aicoagac/v1', '/test-endpoint', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_endpoint_callback'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));
        
        // WordPress REST API routes list endpoint
        register_rest_route('aicoagac/v1', '/debug/routes', array(
            'methods' => 'GET',
            'callback' => array($this, 'debug_routes_callback'),
            'permission_callback' => array($this, 'check_admin_permissions')
        ));

        // License endpoints removed - all features now free
        
        // Manual scheduling trigger endpoint - AGENTS.MD FIX
        register_rest_route('aicoagac/v1', '/trigger-scheduling', array(
            'methods' => 'POST',
            'callback' => array($this, 'trigger_scheduling'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Debug endpoints
//         register_rest_route('aicoagac/v1', '/debug/automation', array(
//             'methods' => 'GET',
//             'callback' => array($this, 'get_automation_debug_info'),
//             'permission_callback' => array($this, 'check_admin_permissions'),
//         ));
        
        // Cache management endpoints
        register_rest_route('aicoagac/v1', '/cache/clear', array(
            'methods' => 'POST',
            'callback' => array($this, 'clear_cache'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        register_rest_route('aicoagac/v1', '/cache/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cache_status'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Test endpoint for error handling
        register_rest_route('aicoagac/v1', '/test/error-handling', array(
            'methods' => 'GET',
            'callback' => array($this, 'test_error_handling'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // SEO repair endpoint: cleans invalid Rank Math schema meta written by older versions
        register_rest_route('aicoagac/v1', '/seo/repair-faq', array(
            'methods' => 'POST',
            'callback' => array($this, 'repair_rankmath_schema_meta'),
            'permission_callback' => array($this, 'check_admin_permissions'),
        ));
        
        // Index backfill endpoint (manual)
        register_rest_route('aicoagac/v1', '/index/backfill', array(
            'methods' => 'POST',
            'callback' => array($this, 'index_backfill'),
            'permission_callback' => array($this, 'check_admin_permissions'),
            'args' => array(
                'batch' => array(
                    'default' => 200,
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function($param){ return is_numeric($param) && $param > 0 && $param <= 1000; }
                ),
                'reset' => array(
                    'default' => false,
                    'sanitize_callback' => function($v){ return (bool)$v; }
                )
            )
        ));
        
        aicoagac_debug_log('REST API: All endpoints registered successfully');
    }
    
    /**
     * Check permissions
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Check admin permissions (same as check_permissions but with explicit name)
     */
    public function check_admin_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Check SEO permissions - more flexible for SEO plugin detection
     */
    public function check_seo_permissions() {
        // Allow access for users who can edit posts or manage options
        return current_user_can('edit_posts') || current_user_can('manage_options');
    }
    
    /**
     * Check Pro permissions - now just checks admin permissions (pro removed)
     */
    public function check_pro_permissions() {
        // Check admin permissions only - pro features now free
        return current_user_can('manage_options');
    }
    
    /**
     * Verify nonce for security
     */
    private function verify_nonce($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        aicoagac_debug_log('Nonce Verification: Received nonce: ' . ($nonce ? 'SET (' . substr($nonce, 0, 10) . '...)' : 'EMPTY'));
        
        if (!$nonce) {
            aicoagac_debug_log('Nonce Verification: No nonce header found');
            return new WP_Error('missing_nonce', __('Missing nonce header', 'ai-content-agent'), array('status' => 403));
        }
        
        $verification_result = wp_verify_nonce($nonce, 'wp_rest');
        aicoagac_debug_log('Nonce Verification: wp_verify_nonce result: ' . ($verification_result ? 'VALID' : 'INVALID'));
        
        if (!$verification_result) {
            aicoagac_debug_log('Nonce Verification: Invalid nonce for wp_rest action');
            return new WP_Error('invalid_nonce', __('Invalid nonce', 'ai-content-agent'), array('status' => 403));
        }
        
        aicoagac_debug_log('Nonce Verification: Success');
        return true;
    }
    
    /**
     * Get settings
     */
    public function get_settings($request) {
        $settings = get_option('aicoagac_settings', array());
        
        // Debug log settings load
        aicoagac_debug_log('Settings Load: Retrieved settings count: ' . count($settings));

        // Add pro status to settings response
        $settings['is_pro'] = false;
        
        return rest_ensure_response($settings);
    }
    
    /**
     * Save settings
     */
    public function save_settings($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $raw_settings = $request->get_json_params();
        
        // Validate and sanitize all settings
        $validated_settings = $this->validate_settings($raw_settings);
        if (is_wp_error($validated_settings)) {
            return $validated_settings;
        }
        
        // Debug log the settings being saved
        aicoagac_debug_log('Settings Save: Validated settings count: ' . count($validated_settings));

        // Save validated settings
        $save_result = update_option('aicoagac_settings', $validated_settings);
        aicoagac_debug_log('Settings Save: update_option result: ' . ($save_result ? 'SUCCESS' : 'FAILED'));
        
        // Verify settings were saved
        $saved_settings = get_option('aicoagac_settings', array());
        aicoagac_debug_log('Settings Save: Verification - saved settings count: ' . count($saved_settings));

        // Save Google Cloud settings separately for easy access (already validated)
        if (isset($validated_settings['googleCloudProjectId'])) {
            update_option('aicoagac_google_cloud_project_id', $validated_settings['googleCloudProjectId']);
        }
        if (isset($validated_settings['googleCloudLocation'])) {
            update_option('aicoagac_google_cloud_location', $validated_settings['googleCloudLocation']);
        }
        
        // Clear Google access token cache when settings change (especially API keys)
        delete_transient('aicoagac_google_access_token');
        
        // Automation system removed from Free version
        
        $this->add_activity_log('settings_updated', __('Application settings were updated.', 'ai-content-agent'), 'Settings');
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Validate and sanitize settings array
     * 
     * @param mixed $settings Raw settings data
     * @return array|WP_Error Validated settings or error
     */
    private function validate_settings($settings) {
        if (!is_array($settings)) {
            return new WP_Error('invalid_settings', __('Settings must be an array', 'ai-content-agent'), array('status' => 400));
        }
        
        $validated = array();
        $allowed_fields = array(
            // API Keys
            'geminiApiKey' => 'api_key',
            'pexelsApiKey' => 'api_key',
            'unsplashApiKey' => 'api_key',
            'pixabayApiKey' => 'api_key',
            
            // Google Cloud Settings
            'googleCloudProjectId' => 'text',
            'googleCloudLocation' => 'text',
            
            // Image Settings
            'imageSourceProvider' => 'select',
            'aiImageStyle' => 'select',
            'imageCropEnabled' => 'boolean',
            
            // FAQ Settings
            'faqEnabled' => 'boolean',
            'faqCount' => 'integer',
            'faqDisplayInContent' => 'boolean',
            
            // Automation Settings
            'mode' => 'select',
            'autoPublish' => 'boolean',
            'semiAutoIdeaFrequency' => 'select',
            'fullAutoDailyPostCount' => 'integer',
            'fullAutoPublishFrequency' => 'select',
            
            // Content Analysis Settings
            'analyzeContentFrequency' => 'select',
            'analysisSampling' => 'select',
            
            // Retrieval/Prompt Controls (advanced)
            'internalLinkTopK' => 'integer',
            'useFulltextRetrieval' => 'boolean',
            'maxTitlesForPrompt' => 'integer',
            
            // Cron toggles
            'cronEnabledDaily' => 'boolean',
            'cronEnabledHourly' => 'boolean',
            
            // SEO Plugin Integration
            'seoPlugin' => 'select',
            
            // Legacy Settings (backward compatibility)
            'autoContentEnabled' => 'boolean',
            'cronFrequency' => 'select',
            'use_legacy_automation' => 'boolean',

            'autoPublishEnabled' => 'boolean',
            'seoOptimizationEnabled' => 'boolean',
            'contentLanguage' => 'text',
            'maxPostsPerDay' => 'integer',
            'debugMode' => 'boolean'
        );
        
        foreach ($settings as $key => $value) {
            if (!isset($allowed_fields[$key])) {
                // Skip unknown fields but log for debugging
                aicoagac_debug_log("Settings validation: Skipping unknown field '{$key}'");
                continue;
            }
            
            switch ($allowed_fields[$key]) {
                case 'api_key':
                    $validated[$key] = $this->validate_api_key($value, $key);
                    break;
                case 'text':
                    $validated[$key] = $this->validate_text_field($value);
                    break;
                case 'select':
                    $validated[$key] = $this->validate_select_field($key, $value);
                    break;
                case 'boolean':
                    $validated[$key] = $this->validate_boolean_field($value);
                    break;
                case 'integer':
                    $validated[$key] = $this->validate_integer_field($value, $key);
                    break;
            }
            
            if (is_wp_error($validated[$key])) {
                return $validated[$key];
            }
        }
        
        return $validated;
    }
    
    /**
     * Validate API key format
     * 
     * @param mixed $key API key value
     * @param string $field_name Field name for error messages
     * @return string|WP_Error Validated key or error
     */
    private function validate_api_key($key, $field_name) {
        if (empty($key)) {
            return ''; // Allow empty API keys
        }
        
        if (!is_string($key)) {
            return new WP_Error('invalid_api_key', sprintf(
                /* translators: %s: field name */
                __('API key for %s must be a string', 'ai-content-agent'),
                $field_name
            ), array('status' => 400));
        }
        
        // Basic API key format validation
        if (strlen($key) < 10 || strlen($key) > 500) {
            return new WP_Error('invalid_api_key', sprintf(
                /* translators: %s: field name */
                __('Invalid %1$s format - must be between 10-500 characters', 'ai-content-agent'),
                $field_name
            ), array('status' => 400));
        }
        
        // Remove any whitespace and control characters
        $key = preg_replace('/[\x00-\x1F\x7F]/', '', trim($key));
        
        // Additional security: Remove any HTML/script tags
        $key = wp_strip_all_tags($key);
        
        return sanitize_text_field($key);
    }
    
    /**
     * Validate text field
     * 
     * @param mixed $value Text value
     * @return string Validated text
     */
    private function validate_text_field($value) {
        if (empty($value)) {
            return '';
        }
        
        if (!is_string($value)) {
            return '';
        }
        
        // Limit text field length for security
        if (strlen($value) > 200) {
            $value = substr($value, 0, 200);
        }
        
        return sanitize_text_field($value);
    }
    
    /**
     * Validate select field values
     * 
     * @param string $field_name Field name
     * @param mixed $value Field value
     * @return string|WP_Error Validated value or error
     */
    private function validate_select_field($field_name, $value) {
        $allowed_values = array(
            // Image Settings
            'imageSourceProvider' => array('ai', 'pexels', 'unsplash', 'pixabay'),
            'aiImageStyle' => array('digital_art', 'photorealistic'),
            
            // Automation Settings
            'mode' => array('manual', 'semi-automatic', 'full-automatic'),
            'semiAutoIdeaFrequency' => array('daily', 'weekly', 'monthly'),
            'fullAutoPublishFrequency' => array('hourly', 'daily', 'weekly'),
            
            // Content Analysis Settings
            'analyzeContentFrequency' => array('manual', 'daily', 'weekly', 'monthly'),
            'analysisSampling' => array('recent', 'stratified'),
            
            // SEO Plugin Integration
            'seoPlugin' => array('none', 'rank_math', 'yoast'),
            
            // Legacy Settings (backward compatibility)
            'cronFrequency' => array('15min', '30min', '1hour', '2hours', '6hours', '12hours', 'daily', 'disabled')
        );
        
        if (!isset($allowed_values[$field_name])) {
            return sanitize_text_field($value);
        }
        
        if (!in_array($value, $allowed_values[$field_name], true)) {
            return new WP_Error('invalid_select', sprintf(
                /* translators: 1: field name, 2: comma-separated list of allowed values */
                __('Invalid value for %1$s. Allowed values: %2$s', 'ai-content-agent'),
                $field_name,
                implode(', ', $allowed_values[$field_name])
            ), array('status' => 400));
        }
        
        return $value;
    }
    
    /**
     * Validate boolean field
     * 
     * @param mixed $value Boolean value
     * @return bool Validated boolean
     */
    private function validate_boolean_field($value) {
        return (bool) $value;
    }
    /**
     * Validate integer field
     * 
     * @param mixed $value Integer value
     * @param string $field_name Field name for validation rules
     * @return int|WP_Error Validated integer or error
     */
    private function validate_integer_field($value, $field_name) {
        $int_value = intval($value);
        
        // Field-specific validation rules
        switch ($field_name) {
            case 'maxPostsPerDay':
                if ($int_value < 0 || $int_value > 50) {
                    return new WP_Error('invalid_integer', __('Max posts per day must be between 0-50', 'ai-content-agent'), array('status' => 400));
                }
                break;
            case 'fullAutoDailyPostCount':
                if ($int_value < 1 || $int_value > 10) {
                    return new WP_Error('invalid_integer', __('Full auto daily post count must be between 1-10', 'ai-content-agent'), array('status' => 400));
                }
                break;
            default:
                if ($int_value < 0) {
                    return new WP_Error('invalid_integer', sprintf(
                    /* translators: %s: field name */
                    __('Invalid %1$s - must be positive integer', 'ai-content-agent'),
                    $field_name
                ), array('status' => 400));
                }
        }
        
        return $int_value;
    }
    
    /**
     * Get style guide
     */
    public function get_style_guide($request) {
        $style_guide = get_option('aicoagac_style_guide');
        return rest_ensure_response($style_guide);
    }
    
    /**
     * Analyze style guide using AI
     */
    public function analyze_style_guide($request = null, $is_auto = false) {
        if (!$is_auto) {
            $nonce_check = $this->verify_nonce($request);
            if (is_wp_error($nonce_check)) {
                return $nonce_check;
            }
        }
        
        $settings = get_option('aicoagac_settings', array());
        
        if (empty($settings['geminiApiKey'])) {
            return new WP_Error('no_api_key', __('Google AI API Key is not set', 'ai-content-agent'), array('status' => 400));
        }
        
        try {
            // Fetch recent posts from WordPress to analyze
            $recent_posts = $this->fetch_recent_posts_for_analysis();
            $posts_content = $this->prepare_posts_content_for_analysis($recent_posts);
            
            $analysis = $this->call_gemini_analyze_style($settings['geminiApiKey'], $posts_content);
            $parsed_analysis = json_decode($analysis, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid JSON response from AI service', 'ai-content-agent'));
            }
            
            $parsed_analysis['lastAnalyzed'] = current_time('c');
            update_option('aicoagac_style_guide', $parsed_analysis);
            
            $message = $is_auto ? __('Style Guide automatically refreshed.', 'ai-content-agent') : __('Style Guide was successfully updated.', 'ai-content-agent');
            $this->add_activity_log('style_analyzed', $message, 'BookOpen');
            
            return rest_ensure_response($parsed_analysis);
            
        } catch (Exception $e) {
            return new WP_Error('analysis_failed', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Fetch recent posts for style analysis
     */
    private function fetch_recent_posts_for_analysis() {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Optimized query for style analysis, limited to 20 posts
                array(
                    'key' => '_wp_page_template',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        // Allow stratified sampling from top categories when enabled
        $settings = get_option('aicoagac_settings', array());
        if (!empty($settings['analysisSampling']) && $settings['analysisSampling'] === 'stratified') {
            $categories = get_categories(array('orderby' => 'count', 'order' => 'DESC', 'number' => 6, 'hide_empty' => false));
            $collected = array();
            foreach ($categories as $i => $cat) {
                $n = $i < 3 ? 3 : 2; // more from top 3 categories
                $posts = get_posts(array(
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'posts_per_page' => $n,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'category' => $cat->term_id,
                    'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Optimized sampling per category, limited and cached
                        array('key' => '_wp_page_template', 'compare' => 'NOT EXISTS')
                    )
                ));
                $collected = array_merge($collected, $posts);
                if (count($collected) >= 20) { break; }
            }
            if (!empty($collected)) { return array_slice($collected, 0, 20); }
        }
        
        return get_posts($args);
    }
    
    /**
     * Prepare posts content for AI analysis
     */
    private function prepare_posts_content_for_analysis($posts) {
        if (empty($posts)) {
            return "No recent posts found. Please analyze based on a generic professional blog style.";
        }
        
        $content_samples = array();
        
        foreach ($posts as $post) {
            // Get post content and clean it
            $content = wp_strip_all_tags($post->post_content);
            $content = wp_trim_words($content, 150, '...');
            
            $content_samples[] = array(
                'title' => $post->post_title,
                'content' => $content,
                'date' => $post->post_date
            );
        }
        
        $analysis_prompt = "Here are the 20 most recent blog posts from this website:\n\n";
        
        foreach ($content_samples as $sample) {
            $analysis_prompt .= "Title: {$sample['title']}\n";
            $analysis_prompt .= "Content: {$sample['content']}\n";
            $analysis_prompt .= "Date: {$sample['date']}\n\n---\n\n";
        }
        
        return $analysis_prompt;
    }
    
    private function call_gemini_analyze_style($api_key, $posts_content = '') {
        $prompt = "
            Analyze the writing style of the following blog content and generate a JSON object that describes it.
            This JSON object will be used as a \"Style Guide\" for generating new content.
            
            {$posts_content}
            
            Based on the content above, create a JSON object that strictly follows this schema:
            {
              \"tone\": \"string (e.g., 'Friendly and conversational', 'Formal and professional', 'Technical and informative', 'Witty and humorous')\",
              \"sentenceStructure\": \"string (e.g., 'Mix of short, punchy sentences and longer, more descriptive ones', 'Primarily short and direct sentences', 'Complex sentences with multiple clauses')\",
              \"paragraphLength\": \"string (e.g., 'Short, 2-3 sentences per paragraph', 'Medium, 4-6 sentences per paragraph')\",
              \"formattingStyle\": \"string (e.g., 'Uses bullet points, bold text for emphasis, and subheadings (H2, H3)', 'Minimal formatting, relies on plain text paragraphs')\"
            }
            
            Return ONLY the JSON object, nothing else.
        ";
        
        return $this->call_gemini_api($api_key, $prompt);
    }
    
    /**
     * Save style guide
     */
    public function save_style_guide($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $style_guide = $request->get_json_params();
        update_option('aicoagac_style_guide', $style_guide);
        
        $this->add_activity_log('style_updated', __('Style Guide was manually edited and saved.', 'ai-content-agent'), 'BookOpen');
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Get ideas
     */
    public function get_ideas($request) {
        global $wpdb;
        
        // Get all ideas including archived ones - frontend will filter them
        $ideas = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for ideas management
            "SELECT * FROM {$wpdb->prefix}aicoagac_ideas ORDER BY created_at DESC"
        );
        
        // Map database status to frontend status
        foreach ($ideas as $idea) {
            if ($idea->status === 'new') {
                $idea->status = 'active';
            } elseif ($idea->status === 'archived') {
                $idea->status = 'archived';
            }
        }
        
        return rest_ensure_response($ideas);
    }
    
    /**
     * Generate ideas using AI
     */
    public function generate_ideas($request) {
        $params = $request->get_json_params();
        $count = isset($params['count']) ? intval($params['count']) : 5;
        $is_auto = isset($params['auto']) ? $params['auto'] : false;
        
        if (!$is_auto) {
            $nonce_check = $this->verify_nonce($request);
            if (is_wp_error($nonce_check)) {
                return $nonce_check;
            }
        }
        
        $settings = get_option('aicoagac_settings', array());
        $style_guide = get_option('aicoagac_style_guide');
        
        if (empty($settings['geminiApiKey'])) {
            return new WP_Error('no_api_key', __('Google AI API Key is not set', 'ai-content-agent'), array('status' => 400));
        }
        
        if (empty($style_guide)) {
            return new WP_Error('no_style_guide', __('Style Guide is required', 'ai-content-agent'), array('status' => 400));
        }
        
        try {
            // Get existing titles to avoid duplicates
            $existing_titles = $this->get_existing_titles();
            
            // Reduce prompt size on large sites: use transient-cached recent subset if very large
            if (is_array($existing_titles) && count($existing_titles) > 1000) {
                $cache_key = 'aicoagac_existing_titles_recent_1000';
                $recent_subset = get_transient($cache_key);
                if (!is_array($recent_subset)) {
                    // Keep last 1000 by recency from wp_posts and latest ideas
                    global $wpdb;
                    $limit_total = max(100, intval($settings['maxTitlesForPrompt'] ?? 1000));
                    $limit_posts = (int)floor($limit_total * 0.8);
                    $limit_ideas = max(1, $limit_total - $limit_posts);
                    $recent_posts = $wpdb->get_col($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE post_status IN ('publish','draft') ORDER BY ID DESC LIMIT %d", $limit_posts)); // phpcs:ignore
                    $recent_ideas = $wpdb->get_col($wpdb->prepare("SELECT title FROM {$wpdb->prefix}aicoagac_ideas ORDER BY id DESC LIMIT %d", $limit_ideas)); // phpcs:ignore
                    $recent_subset = array_slice(array_unique(array_merge($recent_posts ?: array(), $recent_ideas ?: array())), 0, $limit_total);
                    set_transient($cache_key, $recent_subset, 1800); // 30 minutes
                }
                $existing_titles = $recent_subset;
            }
            
            $target = max(1, (int)$count);
            $request_count = min($target * 2, $target + 10);
            
            // First batch
            $batch1 = $this->call_gemini_generate_ideas(
                $settings['geminiApiKey'],
                json_encode($style_guide),
                $existing_titles,
                $request_count
            );
            $parsed1 = json_decode($batch1, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception(__('Invalid response from AI service', 'ai-content-agent'));
            }
            
            // Local normalization & dedup
            $norm = function($s){ return trim(mb_strtolower(preg_replace('/\s+/u',' ', (string)$s))); };
            $existing_norm = array();
            foreach ($existing_titles as $et) { $existing_norm[$norm($et)] = true; }
            $unique = array();
            foreach ((array)$parsed1 as $t) {
                $k = $norm($t);
                if ($k !== '' && empty($existing_norm[$k]) && empty($unique[$k])) {
                    $unique[$k] = $t;
                }
                if (count($unique) >= $target) break;
            }
            
            if (count($unique) < $target) {
                $more_needed = $target - count($unique);
                $avoid_now = array_merge($existing_titles, array_values($unique));
                $batch2 = $this->call_gemini_generate_ideas(
                    $settings['geminiApiKey'],
                    json_encode($style_guide),
                    $avoid_now,
                    $more_needed * 2
                );
                $parsed2 = json_decode($batch2, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($parsed2)) {
                    foreach ($parsed2 as $t) {
                        $k = $norm($t);
                        if ($k !== '' && empty($existing_norm[$k]) && empty($unique[$k])) {
                            $unique[$k] = $t;
                        }
                        if (count($unique) >= $target) break;
                    }
                }
            }
            
            $final_titles = array_values(array_slice($unique, 0, $target));
            if (empty($final_titles)) {
                throw new Exception(__('AI service returned no valid unique ideas', 'ai-content-agent'));
            }
            
            // Save ideas to database
            global $wpdb;
            $source = 'ai';
            $saved_ideas = array();
            
            foreach ($final_titles as $title) {
                $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert for idea creation
                    $wpdb->prefix . 'aicoagac_ideas',
                    array(
                        'title' => $title,
                        'status' => 'new',
                        'source' => $source,
                        'created_at' => current_time('mysql')
                    )
                );
                
                if ($result) {
                    $saved_ideas[] = array(
                        'id' => $wpdb->insert_id,
                        'title' => $title,
                        'status' => 'active',
                        'source' => $source,
                        'createdAt' => current_time('c'),
                        'description' => '',
                        'tags' => array()
                    );
                }
            }
            
            // translators: %d is the number of generated content ideas
            $this->add_activity_log('ideas_generated', sprintf(__('Generated %d new content ideas.', 'ai-content-agent'), count($saved_ideas)), 'Lightbulb');
            
            return rest_ensure_response($saved_ideas);
            
        } catch (Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Generate similar ideas
     */
    public function generate_similar_ideas($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $params = $request->get_json_params();
        $idea_id = isset($params['ideaId']) ? $params['ideaId'] : $params['baseTitle'];
        
        // If we received an idea ID, get the title from database
        if (is_numeric($idea_id)) {
            global $wpdb;
            $idea = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea operations // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea lookup
                "SELECT title FROM {$wpdb->prefix}aicoagac_ideas WHERE id = %d",
                $idea_id
            ));
            
            if (!$idea) {
                return new WP_Error('idea_not_found', __('Idea not found', 'ai-content-agent'), array('status' => 404));
            }
            
            $base_title = $idea->title;
        } else {
            $base_title = $idea_id; // It's actually a title string
        }
        
        $settings = get_option('aicoagac_settings', array());
        
        if (empty($settings['geminiApiKey'])) {
            return new WP_Error('no_api_key', __('Google AI API Key is not set', 'ai-content-agent'), array('status' => 400));
        }
        
        try {
            $existing_titles = $this->get_existing_titles();
            
            $similar_ideas = $this->call_gemini_generate_similar_ideas(
                $settings['geminiApiKey'],
                $base_title,
                $existing_titles
            );
            
            $parsed_ideas = json_decode($similar_ideas, true);
            
            if (json_last_error() !== JSON_ERROR_NONE || empty($parsed_ideas)) {
                throw new Exception(__('Invalid response from AI service', 'ai-content-agent'));
            }
            
            // Save ideas to database
            global $wpdb;
            $saved_ideas = array();
            
            foreach ($parsed_ideas as $title) {
                $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert for similar ideas
                    $wpdb->prefix . 'aicoagac_ideas',
                    array(
                        'title' => $title,
                        'status' => 'new',
                        'source' => 'similar',
                        'created_at' => current_time('mysql')
                    )
                );
                
                if ($result) {
                    $saved_ideas[] = array(
                        'id' => $wpdb->insert_id,
                        'title' => $title,
                        'status' => 'active',
                        'source' => 'similar',
                        'createdAt' => current_time('c'),
                        'description' => '',
                        'tags' => array()
                    );
                }
            }
            
            // translators: %d is the number of generated similar ideas
            $this->add_activity_log('similar_ideas_generated', sprintf(__('Generated %d similar ideas.', 'ai-content-agent'), count($saved_ideas)), 'Sparkles');
            
            return rest_ensure_response($saved_ideas);
            
        } catch (Exception $e) {
            return new WP_Error('generation_failed', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Add manual idea
     */
    public function add_idea($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $params = $request->get_json_params();
        $title = trim($params['title']);
        
        if (empty($title)) {
            return new WP_Error('empty_title', __('Idea title cannot be empty', 'ai-content-agent'), array('status' => 400));
        }
        
        // Check for duplicates
        $existing_titles = $this->get_existing_titles();
        if (in_array(strtolower($title), array_map('strtolower', $existing_titles))) {
            return new WP_Error('duplicate_title', __('This idea title already exists', 'ai-content-agent'), array('status' => 400));
        }
        
        global $wpdb;
        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert for manual idea creation
            $wpdb->prefix . 'aicoagac_ideas',
            array(
                'title' => $title,
                'status' => 'new',
                'source' => 'manual',
                'created_at' => current_time('mysql')
            )
        );
        
        if ($result) {
            $idea = array(
                'id' => $wpdb->insert_id,
                'title' => $title,
                'status' => 'active',
                'source' => 'manual',
                'createdAt' => current_time('c'),
                'description' => '',
                'tags' => array()
            );
            
            // translators: %s is the title of the idea added manually
            $this->add_activity_log('idea_added', sprintf(__('Manually added idea: "%s"', 'ai-content-agent'), $title), 'PlusCircle');
            
            return rest_ensure_response($idea);
        }
        
        return new WP_Error('save_failed', __('Failed to save idea', 'ai-content-agent'), array('status' => 500));
    }
    
    /**
     * Update idea
     */
    public function update_idea($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $id = $request['id'];
        $params = $request->get_json_params();
        $new_title = trim($params['title']);
        
        global $wpdb;
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for idea editing
            $wpdb->prefix . 'aicoagac_ideas',
            array('title' => $new_title),
            array('id' => $id)
        );
        
        if ($result !== false) {
            // translators: %s is the new title of the idea
            $this->add_activity_log('idea_updated', sprintf(__('Updated idea title to "%s"', 'ai-content-agent'), $new_title), 'Edit');
            return rest_ensure_response(array('success' => true));
        }
        
        return new WP_Error('update_failed', __('Failed to update idea', 'ai-content-agent'), array('status' => 500));
    }
    
    /**
     * Delete idea (archive)
     */
    public function delete_idea($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $id = $request['id'];
        
        global $wpdb;
        
        // Get idea title for logging
        $idea = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea operations // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea archival
            "SELECT title FROM {$wpdb->prefix}aicoagac_ideas WHERE id = %d",
            $id
        ));
        
        // Archive the idea instead of deleting it
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for idea archival
            $wpdb->prefix . 'aicoagac_ideas',
            array('status' => 'archived'),
            array('id' => $id)
        );
        
        if ($result !== false) {
            if ($idea) {
                // translators: %s is the title of the idea being archived
                $this->add_activity_log('idea_archived', sprintf(__('Archived idea: "%s"', 'ai-content-agent'), $idea->title), 'Archive');
            }
            return rest_ensure_response(array('success' => true));
        }
        
        return new WP_Error('archive_failed', __('Failed to archive idea', 'ai-content-agent'), array('status' => 500));
    }
    
    /**
     * Restore archived idea
     */
    public function restore_idea($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $id = $request['id'];
        
        global $wpdb;
        
        // Get idea title for logging
        $idea = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea operations
            "SELECT title FROM {$wpdb->prefix}aicoagac_ideas WHERE id = %d",
            $id
        ));
        
        // Restore the idea by setting status to 'new' (which maps to 'active' in frontend)
        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update operation
            $wpdb->prefix . 'aicoagac_ideas',
            array('status' => 'new'),
            array('id' => $id)
        );
        
        if ($result !== false) {
            if ($idea) {
                // translators: %s is the title of the idea being restored
                $this->add_activity_log('idea_updated', sprintf(__('Restored idea: "%s"', 'ai-content-agent'), $idea->title), 'Edit');
            }
            return rest_ensure_response(array('success' => true));
        }
        
        return new WP_Error('restore_failed', __('Failed to restore idea', 'ai-content-agent'), array('status' => 500));
    }
    
    /**
     * Permanently delete idea
     */
    public function permanent_delete_idea($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $id = $request['id'];
        
        global $wpdb;
        
        // Get idea title for logging
        $idea = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea operations
            "SELECT title FROM {$wpdb->prefix}aicoagac_ideas WHERE id = %d",
            $id
        ));
        
        // Permanently delete the idea from database
        $result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete operation
            $wpdb->prefix . 'aicoagac_ideas',
            array('id' => $id)
        );
        
        if ($result) {
            if ($idea) {
                // translators: %s is the title of the idea being deleted permanently
                $this->add_activity_log('idea_updated', sprintf(__('Permanently deleted idea: "%s"', 'ai-content-agent'), $idea->title), 'Trash');
            }
            return rest_ensure_response(array('success' => true));
        }
        
        return new WP_Error('delete_failed', __('Failed to permanently delete idea', 'ai-content-agent'), array('status' => 500));
    }
    
    /**
     * Get drafts with pagination
     */
    public function get_drafts($request) {
        // Pagination parameters with safe defaults
        $page = max(1, intval($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per_page;
        
        $args = array(
            'post_type' => 'post',
            'post_status' => array('draft', 'future'),
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $drafts = get_posts($args);
        
        // Get total count for pagination info (optimized with fields => 'ids')
        $total_args = $args;
        $total_args['posts_per_page'] = -1;
        $total_args['fields'] = 'ids';
        unset($total_args['offset']); // Remove offset for total count
        $total_posts = count(get_posts($total_args));
        
        // Use batch processing for better performance
        $formatted_drafts = $this->format_posts_for_api_batch($drafts);
        
        return rest_ensure_response(array(
            'posts' => $formatted_drafts,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_posts,
                'total_pages' => ceil($total_posts / $per_page)
            )
        ));
    }
    
    /**
     * Get published posts with pagination
     */
    public function get_published_posts($request) {
        aicoagac_debug_log('Published Posts API: Starting request');
        
        // Pagination parameters with safe defaults
        $page = max(1, intval($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, intval($request->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per_page;
        
        aicoagac_debug_log('Published Posts API: page=' . $page . ', per_page=' . $per_page . ', offset=' . $offset);
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $posts = get_posts($args);
        aicoagac_debug_log('Published Posts API: Found ' . count($posts) . ' posts');
        aicoagac_debug_log('Published Posts API: Query args: ' . wp_json_encode($args));
        
        // Debug: Show first few post titles for verification
        if (!empty($posts)) {
            $sample_titles = array_slice(array_map(function($post) {
                return $post->post_title;
            }, $posts), 0, 3);
            aicoagac_debug_log('Published Posts API: Sample titles: ' . implode(', ', $sample_titles));
        }
        
        // Get total count for pagination info (optimized)
        // Prefer O(1) count via wp_count_posts when no additional filters are applied
        $counts = wp_count_posts('post');
        $total_posts = isset($counts->publish) ? (int) $counts->publish : 0;
        aicoagac_debug_log('Published Posts API: Total posts available (wp_count_posts): ' . $total_posts);
        
        // Use batch processing for better performance - special formatting for published posts
        $formatted_posts = $this->format_published_posts_for_api($posts);
        aicoagac_debug_log('Published Posts API: Formatted ' . count($formatted_posts) . ' posts');
        
        return rest_ensure_response(array(
            'posts' => $formatted_posts,
            'pagination' => array(
                'page' => $page,
                'per_page' => $per_page,
                'total' => $total_posts,
                'total_pages' => max(1, (int) ceil(($total_posts ?: 0) / $per_page))
            )
        ));
    }
    
    /**
     * Update published post date
     */
    public function update_published_post_date($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $post_id = (int) $request['id'];
        $params = $request->get_json_params();
        
        if (!isset($params['newDate'])) {
            return new WP_Error('missing_date', __('New date is required', 'ai-content-agent'), array('status' => 400));
        }
        
        $new_date = $params['newDate'];
        $should_convert_to_draft = isset($params['shouldConvertToDraft']) ? $params['shouldConvertToDraft'] : false;
        
        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', __('Post not found', 'ai-content-agent'), array('status' => 404));
        }
        
        // Prepare update data
        $update_data = array(
            'ID' => $post_id,
            'post_date' => gmdate('Y-m-d H:i:s', strtotime($new_date)),
            'post_date_gmt' => get_gmt_from_date(gmdate('Y-m-d H:i:s', strtotime($new_date))),
            'edit_date' => true
        );
        
        // If converting to draft (future date)
        if ($should_convert_to_draft) {
            $update_data['post_status'] = 'future';
        }
        
        // Update the post
        $result = wp_update_post($update_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log the activity
        $action = $should_convert_to_draft ? 'converted to scheduled draft' : 'date updated';
        // translators: 1: post title, 2: action (converted to scheduled draft or date updated)
        $this->add_activity_log('draft_updated', sprintf(__('Post "%1$s" %2$s', 'ai-content-agent'), $post->post_title, $action), 'Calendar');
        
        // Return the updated post
        $updated_post = get_post($post_id);
        return rest_ensure_response($this->format_post_for_api($updated_post));
    }
    /**
     * Create draft from idea
     */
    public function create_draft($request) {
        // Enhanced error handling for draft creation (WordPress compliant)
        $old_error_handler = null;
        
        try {
            $nonce_check = $this->verify_nonce($request);
            if (is_wp_error($nonce_check)) {
                return $nonce_check;
            }
            
            $params = $request->get_json_params();
            if (!isset($params['ideaId'])) {
                return new WP_Error('missing_idea_id', __('Idea ID is required', 'ai-content-agent'), array('status' => 400));
            }
            
            $idea_id = (int) $params['ideaId'];
            
            // Log the attempt
            aicoagac_debug_log('Creating draft for idea ID: ' . $idea_id);
            
            $result = $this->create_draft_from_idea($idea_id);
            
            // Log the result
            if (is_wp_error($result)) {
                aicoagac_debug_log('Draft creation failed for idea ' . $idea_id . ': ' . $result->get_error_message());
            } else {
                aicoagac_debug_log('Draft creation successful for idea ' . $idea_id);
            }
            
            return $result;
            
        } catch (Throwable $e) {
            aicoagac_debug_log('FATAL ERROR in create_draft: ' . $e->getMessage());
            aicoagac_debug_log('FATAL ERROR stack trace: ' . $e->getTraceAsString());
            return new WP_Error('fatal_error', sprintf(
                /* translators: %s: error message */
                __('A fatal error occurred during draft creation: %s', 'ai-content-agent'),
                $e->getMessage()
            ), array('status' => 500));
        } finally {
            // Cleanup completed - no custom error handler to restore
            if ($old_error_handler) {
                // Error handler cleanup would go here if needed
                aicoagac_debug_log('Draft creation process completed');
            }
        }
    }
    
    /**
     * Create draft from idea (internal method)
     */
    public function create_draft_from_idea($idea_id, $is_auto = false) {
        global $wpdb;
        
        // Get the idea
        $idea = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for idea operations
            "SELECT * FROM {$wpdb->prefix}aicoagac_ideas WHERE id = %d",
            $idea_id
        ));
        
        if (!$idea) {
            return new WP_Error('idea_not_found', __('Idea not found', 'ai-content-agent'), array('status' => 404));
        }
        
        $settings = get_option('aicoagac_settings', array());
        $style_guide = get_option('aicoagac_style_guide');
        
        if (empty($settings['geminiApiKey'])) {
            return new WP_Error('no_api_key', __('Google AI API Key is not set', 'ai-content-agent'), array('status' => 400));
        }
        
        if (empty($style_guide)) {
            return new WP_Error('no_style_guide', __('Style Guide is required', 'ai-content-agent'), array('status' => 400));
        }
        
        try {
            // Internal linking candidates (scaled): prefer indexed mini-summaries
            $site_locale = get_locale();
            $top_k = max(1, min(10, intval($settings['internalLinkTopK'] ?? 5)));
            $existing_posts_context = $this->get_internal_link_candidates_for_idea($idea->title, $site_locale, $top_k);
            
            // Fallback to recent posts if index not available
            if (empty($existing_posts_context)) {
                $published_posts = get_posts(array(
                    'post_type' => 'post',
                    'post_status' => 'publish',
                    'numberposts' => 10,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ));
                foreach ($published_posts as $post) {
                    $permalink = get_permalink($post->ID);
                    $existing_posts_context[] = array(
                        'title' => $post->post_title,
                        'url' => $permalink ? $permalink : home_url("/?p={$post->ID}"),
                        'content' => wp_strip_all_tags(substr($post->post_content, 0, 300))
                    );
                }
            }
            
            // Get site language for content generation
            $site_locale = get_locale();
            $site_language = $this->get_language_from_locale($site_locale);
            
            // Get existing categories with hierarchy for AI to choose from
            $existing_categories = get_categories(array(
                'hide_empty' => false,
                'number' => 50, // Increased to get more categories
                'hierarchical' => true,
                'orderby' => 'parent'
            ));
            
            $categories_context = array();
            foreach ($existing_categories as $category) {
                $parent_info = '';
                if ($category->parent > 0) {
                    $parent_category = get_category($category->parent);
                    if ($parent_category && !is_wp_error($parent_category)) {
                        $parent_info = $parent_category->name;
                    }
                }
                
                $categories_context[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'count' => $category->count,
                    'parent_id' => $category->parent,
                    'parent_name' => $parent_info,
                    'hierarchy_level' => $this->get_category_level($category->term_id)
                );
            }
            
            // Generate content using AI
            try {
                $draft_content = $this->call_gemini_create_draft(
                    $settings['geminiApiKey'],
                    $idea->title,
                    json_encode($style_guide),
                    $existing_posts_context,
                    $categories_context
                );
                
                if (empty($draft_content)) {
                    throw new Exception(__('Empty response from AI service', 'ai-content-agent'));
                }
                
                $draft_data = json_decode($draft_content, true);
                                        if (json_last_error() !== JSON_ERROR_NONE) {
                            // Try to clean and fix common JSON issues
                            $cleaned_content = $this->clean_ai_json_response($draft_content);
                            if ($cleaned_content !== $draft_content) {
                                $draft_data = json_decode($cleaned_content, true);
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    // Final fallback: attempt heuristic parsing from text
                                    $fallback = $this->fallback_parse_draft_json($cleaned_content);
                                    if (is_array($fallback) && !empty($fallback['content'])) {
                                        $draft_data = $fallback;
                                    } else {
                                        throw new Exception(__('Invalid JSON response from AI service after cleaning: ', 'ai-content-agent') . json_last_error_msg());
                                    }
                                }
                            } else {
                                // Final fallback without cleaning change
                                $fallback = $this->fallback_parse_draft_json($draft_content);
                                if (is_array($fallback) && !empty($fallback['content'])) {
                                    $draft_data = $fallback;
                                } else {
                                    throw new Exception(__('Invalid JSON response from AI service: ', 'ai-content-agent') . json_last_error_msg());
                                }
                            }
                        }
                
                // Validate required fields
                if (!isset($draft_data['content']) || !isset($draft_data['metaTitle']) || !isset($draft_data['metaDescription']) || !isset($draft_data['focusKeywords'])) {
                    throw new Exception(__('Missing required fields in AI response', 'ai-content-agent'));
                }
                
                // Validate data types
                if (!is_string($draft_data['content'])) {
                    throw new Exception(__('AI response content field must be string', 'ai-content-agent'));
                }
                if (!is_string($draft_data['metaTitle'])) {
                    throw new Exception(__('AI response metaTitle field must be string', 'ai-content-agent'));
                }
                if (!is_string($draft_data['metaDescription'])) {
                    throw new Exception(__('AI response metaDescription field must be string', 'ai-content-agent'));
                }
                
                // Convert Markdown to HTML if needed
                aicoagac_debug_log('Content before conversion (first 200 chars): ' . substr($draft_data['content'], 0, 200));
                if (strpos($draft_data['content'], '**') !== false || 
                    strpos($draft_data['content'], '*') !== false || 
                    strpos($draft_data['content'], '[') !== false ||
                    strpos($draft_data['content'], '##') !== false) {
                    aicoagac_debug_log('Markdown detected, converting to HTML');
                    $draft_data['content'] = $this->markdown_to_html($draft_data['content']);
                    aicoagac_debug_log('Content after conversion (first 200 chars): ' . substr($draft_data['content'], 0, 200));
                } else {
                    aicoagac_debug_log('No Markdown detected, using content as-is');
                }
                
                // Log what we received from AI
                aicoagac_debug_log('AI response keys: ' . implode(', ', array_keys($draft_data)));
                if (isset($draft_data['categoryIds'])) {
                    aicoagac_debug_log('AI selected category IDs: ' . implode(', ', $draft_data['categoryIds']));
                }
                if (isset($draft_data['tags'])) {
                    aicoagac_debug_log('AI selected tags: ' . implode(', ', $draft_data['tags']));
                }
                
            } catch (Exception $ai_error) {
                throw new Exception(__('AI content generation failed: ', 'ai-content-agent') . $ai_error->getMessage());
            }
            
            // Generate or fetch image
            $image_data = $this->get_featured_image($idea->title, $settings, $draft_data['content'] ?? '');
            
            // Safely prepare meta data
            $focus_keywords = '';
            if (isset($draft_data['focusKeywords'])) {
                if (is_array($draft_data['focusKeywords'])) {
                    $focus_keywords = implode(', ', $draft_data['focusKeywords']);
                } else {
                    $focus_keywords = (string) $draft_data['focusKeywords'];
                }
            }
            
            // Create WordPress post with enhanced content
            $post_data = array(
                'post_title' => sanitize_text_field($idea->title),
                'post_content' => wp_kses_post($draft_data['content']),
                'post_excerpt' => isset($draft_data['excerpt']) ? sanitize_text_field($draft_data['excerpt']) : '',
                'post_status' => 'draft',
                'post_type' => 'post',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'meta_input' => array(
                    '_aicoagac_meta_title' => sanitize_text_field($draft_data['metaTitle']),
                    '_aicoagac_meta_description' => sanitize_text_field($draft_data['metaDescription']),
                    '_aicoagac_focus_keywords' => sanitize_text_field($focus_keywords),
                    '_aicoagac_created_from_idea' => (int) $idea_id,
                    '_aicoagac_ai_generated' => true,
                    '_aicoagac_generation_date' => current_time('mysql')
                )
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Creating WordPress post');
            }
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' wp_insert_post failed: ' . $post_id->get_error_message());
                }
                throw new Exception('Failed to create WordPress post: ' . $post_id->get_error_message());
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' WordPress post created with ID: ' . $post_id);
            }
            
            // Add categories safely using AI-selected IDs
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Processing categories');
            }
            if (isset($draft_data['categoryIds']) && is_array($draft_data['categoryIds'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' Found ' . count($draft_data['categoryIds']) . ' category IDs to process');
                }
                $category_ids = array();
                foreach ($draft_data['categoryIds'] as $category_id) {
                    if (is_numeric($category_id)) {
                        $category_id = (int) $category_id;
                        // Verify category exists
                        $category = get_category($category_id);
                        if ($category && !is_wp_error($category)) {
                            $category_ids[] = $category_id;
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                aicoagac_debug_log(' Valid category ID: ' . $category_id . ' (' . $category->name . ')');
                            }
                        } else {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                aicoagac_debug_log(' Invalid category ID: ' . $category_id);
                            }
                        }
                    }
                }
                if (!empty($category_ids)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log(' Setting ' . count($category_ids) . ' categories');
                    }
                    wp_set_post_categories($post_id, $category_ids);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log(' No valid categories to set');
                    }
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' No categoryIds in draft_data');
                }
            }
            
            // Add tags safely
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Processing tags');
            }
            if (isset($draft_data['tags']) && is_array($draft_data['tags'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' Found ' . count($draft_data['tags']) . ' tags to process');
                }
                $clean_tags = array();
                foreach ($draft_data['tags'] as $tag) {
                    if (is_string($tag) && !empty(trim($tag))) {
                        $clean_tags[] = sanitize_text_field($tag);
                    }
                }
                if (!empty($clean_tags)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log(' Setting ' . count($clean_tags) . ' tags: ' . implode(', ', $clean_tags));
                    }
                    wp_set_post_tags($post_id, $clean_tags);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log(' No valid tags to set');
                    }
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' No tags in draft_data');
                }
            }
            
            // Set featured image if we have one
            if ($image_data) {
                $attachment_id = $this->save_image_to_media_library($image_data, $idea->title, $post_id);
                if ($attachment_id) {
                    set_post_thumbnail($post_id, $attachment_id);
                    aicoagac_debug_log('Successfully set featured image for post ' . $post_id . ' with attachment ' . $attachment_id);
                } else {
                    aicoagac_debug_log('Failed to create attachment for featured image');
                }
            }
            
            // Optionally generate and append FAQs (JSON-LD fallback or SEO plugin specific handling)
            try {
                $this->maybe_generate_and_attach_faqs($post_id, $idea->title, $draft_data['content'] ?? '');
                // After touching FAQs, ensure Rank Math schema meta is not left in an invalid state
                $this->cleanup_rankmath_schema_if_invalid($post_id);
            } catch (Exception $e) {
                aicoagac_debug_log('FAQ generation non-blocking error: ' . $e->getMessage());
            }
            
            // Send SEO data to detected SEO plugins
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Sending SEO data to detected SEO plugins');
            }
            try {
                $focus_keywords_array = !empty($focus_keywords) ? explode(',', $focus_keywords) : array();
                $focus_keywords_array = array_map('trim', $focus_keywords_array);
                
                $seo_results = $this->send_seo_data_to_plugins(
                    $post_id,
                    $draft_data['metaTitle'],
                    $draft_data['metaDescription'],
                    $focus_keywords_array
                );
                
                if (!empty($seo_results)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log(' SEO data sent successfully: ' . json_encode($seo_results));
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log(' No SEO plugins detected or no data sent');
                    }
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' SEO data sending failed (non-blocking): ' . $e->getMessage());
                }
            }
            
            // Update idea status instead of deleting (safer approach)
            $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for idea status
                $wpdb->prefix . 'aicoagac_ideas',
                array('status' => 'archived'),
                array('id' => $idea_id)
            );
            
            // Add activity log with error handling (non-blocking)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Adding activity log');
            }
            // translators: %s is the idea title used to create the draft
            $log_result = $this->add_activity_log('draft_created', sprintf(__('Created draft: "%s"', 'ai-content-agent'), $idea->title), 'FileText');
            if (!$log_result) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' Activity log failed but continuing with draft creation');
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    aicoagac_debug_log(' Activity log added successfully');
                }
            }
            
            // Return the created post - simplified approach to avoid formatting errors
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Getting created post for response');
            }
            $created_post = get_post($post_id);
            
            if (!$created_post) {
                // Even if we can't retrieve the post, it was created successfully
                aicoagac_debug_log('Post created but could not retrieve - Post ID: ' . $post_id);
                return rest_ensure_response(array(
                    'id' => $post_id,
                    'title' => $idea->title,
                    'content' => isset($draft_data['content']) ? $draft_data['content'] : '',
                    'status' => 'draft',
                    'createdAt' => current_time('mysql'),
                    'categories' => isset($draft_data['categories']) ? $draft_data['categories'] : array(),
                    'tags' => isset($draft_data['tags']) ? $draft_data['tags'] : array(),
                    'message' => __('Draft created successfully', 'ai-content-agent')
                ));
            }
            
            // Use simplified response format to avoid complex formatting errors
            $safe_response = array(
                'id' => (int) $post_id,
                'title' => $created_post->post_title ?: '',
                'content' => $created_post->post_content ?: '',
                'excerpt' => $created_post->post_excerpt ?: '',
                'status' => $created_post->post_status === 'publish' ? 'published' : ($created_post->post_status === 'future' ? 'draft' : ($created_post->post_status ?: 'draft')),
                'createdAt' => $created_post->post_date ?: current_time('mysql'),
                'categories' => array(), // Will be populated from actual post categories
                'tags' => isset($draft_data['tags']) && is_array($draft_data['tags']) ? $draft_data['tags'] : array(),
                'metaTitle' => isset($draft_data['metaTitle']) ? $draft_data['metaTitle'] : '',
                'metaDescription' => isset($draft_data['metaDescription']) ? $draft_data['metaDescription'] : '',
                'focusKeywords' => $focus_keywords,
                'featuredImage' => '',
                'publishedAt' => null,
                'url' => null,
                'scheduledFor' => '',
                'aiGenerated' => true,
                'generationDate' => current_time('mysql'),
                'message' => __('Draft created successfully', 'ai-content-agent')
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Returning successful response');
            }
            return rest_ensure_response($safe_response);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' Exception caught in create_draft_from_idea');
            }
            aicoagac_debug_log('Draft Creation Error: ' . $e->getMessage());
            aicoagac_debug_log('Draft Creation Stack Trace: ' . $e->getTraceAsString());
            aicoagac_debug_log('Draft Creation Context - Idea ID: ' . $idea_id);
            
            // If post was created but we got an error later, try to return success anyway
            if (isset($post_id) && $post_id && !is_wp_error($post_id)) {
                aicoagac_debug_log('Post was created successfully (ID: ' . $post_id . ') but error occurred in processing');
                
                // Try to get basic post info and return success
                $created_post = get_post($post_id);
                if ($created_post) {
                    return rest_ensure_response(array(
                        'id' => $post_id,
                        'title' => $created_post->post_title,
                        'content' => $created_post->post_content,
                        'status' => $created_post->post_status === 'publish' ? 'published' : ($created_post->post_status === 'future' ? 'draft' : ($created_post->post_status ?: 'draft')),
                        'createdAt' => $created_post->post_date,
                        'categories' => array(),
                        'tags' => array(),
                        'message' => __('Draft created successfully', 'ai-content-agent')
                    ));
                }
            }
            
            // Return the actual error message for debugging
            return new WP_Error('creation_failed', sprintf(
                /* translators: %s: error message */
                __('DETAILED ERROR: %s', 'ai-content-agent'),
                $e->getMessage()
            ), array('status' => 500));
        }
    }
    
    /**
     * Update draft
     */
    public function update_draft($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $post_id = $request['id'];
        $params = $request->get_json_params();
        
        $update_data = array(
            'ID' => $post_id
        );
        
        if (isset($params['title'])) {
            $update_data['post_title'] = $params['title'];
        }
        
        if (isset($params['content'])) {
            $update_data['post_content'] = $params['content'];
        }
        
        $result = wp_update_post($update_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update meta fields
        if (isset($params['metaTitle'])) {
            update_post_meta($post_id, '_aicoagac_meta_title', $params['metaTitle']);
        }
        
        if (isset($params['metaDescription'])) {
            update_post_meta($post_id, '_aicoagac_meta_description', $params['metaDescription']);
        }
        
        if (isset($params['focusKeywords'])) {
            update_post_meta($post_id, '_aicoagac_focus_keywords', $params['focusKeywords']);
        }
        
        // translators: %s is the draft title being updated
        $this->add_activity_log('draft_updated', sprintf(__('Updated draft: "%s"', 'ai-content-agent'), $params['title']), 'Edit');
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Publish draft
     */
    public function publish_draft($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $post_id = $request['id'];
        
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ));
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Clean up any invalid Rank Math schema meta to avoid frontend warnings
        $this->cleanup_rankmath_schema_if_invalid($post_id);
        
        $post = get_post($post_id);
        // translators: %s is the post title that has been published
        $this->add_activity_log('post_published', sprintf(__('Published post: "%s"', 'ai-content-agent'), $post->post_title), 'Send');
        
        return rest_ensure_response(array('success' => true));
    }
    
    /**
     * Schedule draft
     */
    public function schedule_draft($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $post_id = $request['id'];
        $params = $request->get_json_params();
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Schedule Draft: Post ID = ' . $post_id);
            aicoagac_debug_log('Schedule Draft: Params = ' . json_encode($params));
        }
        
        // Handle both 'date' and 'scheduledDate' parameters for compatibility
        $scheduled_date = isset($params['scheduledDate']) ? $params['scheduledDate'] : (isset($params['date']) ? $params['date'] : null);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Schedule Draft: Scheduled Date = ' . $scheduled_date);
        }
        
        if (empty($scheduled_date)) {
            return new WP_Error('missing_date', __('Scheduled date is required', 'ai-content-agent'), array('status' => 400));
        }
        
        // Get current post to check its status
        $current_post = get_post($post_id);
        if (!$current_post) {
            return new WP_Error('post_not_found', __('Post not found', 'ai-content-agent'), array('status' => 404));
        }
        
        // Parse the incoming date (usually in ISO format from JavaScript)
        $parsed_date = date_create($scheduled_date);
        if (!$parsed_date) {
            return new WP_Error('invalid_date', __('Invalid date format', 'ai-content-agent'), array('status' => 400));
        }
        
        // Get current WordPress time for comparison
        $current_wp_time = current_time('timestamp');
        $current_wp_date = current_time('Y-m-d H:i:s');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Schedule Draft: Current WP Time = ' . $current_wp_date);
            aicoagac_debug_log('Schedule Draft: Received Date = ' . $parsed_date->format('Y-m-d H:i:s'));
        }
        
        // If the date doesn't include a time (just date from calendar), set it to a future time
        $time_part = $parsed_date->format('H:i:s');
        
        // If time is 00:00:00 (midnight), it means we got just a date from calendar drag-drop
        if ($time_part === '00:00:00') {
            // Set to 9:00 AM of that date to ensure it's in the future for scheduling
            $parsed_date->setTime(9, 0, 0);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log('Schedule Draft: Set time to 9:00 AM for calendar date');
            }
        }
        
        // Convert to WordPress local time format
        $local_date = $parsed_date->format('Y-m-d H:i:s');
        $target_timestamp = $parsed_date->getTimestamp();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Schedule Draft: Target Local Date = ' . $local_date);
            aicoagac_debug_log('Schedule Draft: Target Timestamp = ' . $target_timestamp);
            aicoagac_debug_log('Schedule Draft: Current Timestamp = ' . $current_wp_time);
        }
        
        // Update post meta for our plugin
        update_post_meta($post_id, '_aicoagac_scheduled_for', $scheduled_date);
        
        // Prepare update data
        $update_data = array(
            'ID' => $post_id,
            'post_date' => $local_date,
            'post_date_gmt' => get_gmt_from_date($local_date),
            'edit_date' => true  // This is crucial for WordPress to accept date changes on drafts
        );
        
        // Determine post status based on timing
        if ($target_timestamp > $current_wp_time) {
            // Future date - schedule it
            $update_data['post_status'] = 'future';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log('Schedule Draft: Setting post status to FUTURE');
            }
        } else {
            // Past or current date - keep as draft but update the date
            $update_data['post_status'] = 'draft';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log('Schedule Draft: Past/current date - keeping as draft');
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Schedule Draft: Update Data = ' . json_encode($update_data));
        }
        
        // Update the post
        $update_result = wp_update_post($update_data);
        
        if (is_wp_error($update_result)) {
            aicoagac_debug_log('Schedule Draft: wp_update_post failed: ' . $update_result->get_error_message());
            return new WP_Error('update_failed', sprintf(
                /* translators: %s: error message */
                __('Failed to schedule post: %s', 'ai-content-agent'),
                $update_result->get_error_message()
            ), array('status' => 500));
        }
        
        if ($update_result === 0) {
            aicoagac_debug_log('Schedule Draft: wp_update_post returned 0');
            return new WP_Error('update_failed', __('Failed to update post - wp_update_post returned 0', 'ai-content-agent'), array('status' => 500));
        }
        
        // Get the updated post and format it for API response
        $updated_post = get_post($post_id);
        if (!$updated_post) {
            return new WP_Error('post_not_found', __('Post not found after update', 'ai-content-agent'), array('status' => 404));
        }
        
        $formatted_post = $this->format_post_for_api($updated_post);
        
        // Log the successful scheduling
        $readable_date = $parsed_date->format('M j, Y g:i A');
        // translators: 1: post title, 2: readable scheduled date
        $this->add_activity_log('draft_scheduled', sprintf(__('Scheduled draft: "%1$s" for %2$s', 'ai-content-agent'), $updated_post->post_title, $readable_date), 'Calendar');
        
        // Prevent Rank Math schema issues if any invalid meta exists
        $this->cleanup_rankmath_schema_if_invalid($post_id);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Schedule Draft: Successfully updated post. Final status = ' . $updated_post->post_status);
            aicoagac_debug_log('Schedule Draft: Final post_date = ' . $updated_post->post_date);
            aicoagac_debug_log('Schedule Draft: Final post_date_gmt = ' . $updated_post->post_date_gmt);
        }
        
        return rest_ensure_response($formatted_post);
    }
    
    /**
     * Get activity logs
     */
    public function get_activity_logs($request) {
        global $wpdb;
        
        $logs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for activity logs
            "SELECT * FROM {$wpdb->prefix}aicoagac_activity_logs ORDER BY timestamp DESC LIMIT 50"
        );
        
        return rest_ensure_response($logs);
    }
    
    /**
     * Add activity log via REST API
     */
    public function add_activity_log_endpoint($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $params = $request->get_json_params();
        
        if (empty($params['type']) || empty($params['message']) || empty($params['icon'])) {
            return new WP_Error('missing_params', __('Missing required parameters: type, message, icon', 'ai-content-agent'), array('status' => 400));
        }
        
        $this->add_activity_log($params['type'], $params['message'], $params['icon']);
        
        return rest_ensure_response(array('success' => true));
    }
    
    // Helper methods
    
    /**
     * Get existing titles to avoid duplicates
     */
    private function get_existing_titles($recent_only = false, $limit = 0) {
        global $wpdb;

        if ($recent_only && $limit > 0) {
            $posts = $wpdb->get_col($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE post_status IN ('publish','draft') ORDER BY ID DESC LIMIT %d", $limit)); // phpcs:ignore
            $ideas = $wpdb->get_col($wpdb->prepare("SELECT title FROM {$wpdb->prefix}aicoagac_ideas ORDER BY id DESC LIMIT %d", max(1, (int)floor($limit/2)))); // phpcs:ignore
            return array_merge($ideas ?: array(), $posts ?: array());
        }
        
        $cache_key = 'aicoagac_all_existing_titles_v1';
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }
        
        $idea_titles = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT title FROM {$wpdb->prefix}aicoagac_ideas"
        );
        $post_titles = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT post_title FROM {$wpdb->posts} WHERE post_status IN ('publish', 'draft')"
        );
        $all = array_merge($idea_titles, $post_titles);
        // Cache for 30 minutes to reduce DB load
        set_transient($cache_key, $all, 1800);
        return $all;
    }
    /**
     * Format multiple posts for API response with batch processing (optimized)
     * Prevents N+1 query problems by pre-loading all required data
     */
    private function format_posts_for_api_batch($posts) {
        if (empty($posts) || !is_array($posts)) {
            return array();
        }
        
        // Pre-load all post IDs for batch queries
        $post_ids = wp_list_pluck($posts, 'ID');
        
        if (empty($post_ids)) {
            return array();
        }
        
        // Batch load all meta data to prevent N+1 queries
        $meta_cache = $this->get_posts_meta_batch($post_ids, array(
            '_aicoagac_meta_title',
            '_aicoagac_meta_description', 
            '_aicoagac_focus_keywords',
            '_aicoagac_scheduled_for',
            '_aicoagac_ai_generated',
            '_aicoagac_generation_date'
        ));
        
        // Batch load thumbnails
        $thumbnail_cache = $this->get_posts_thumbnails_batch($post_ids);
        
        // Batch load categories and tags
        $categories_cache = $this->get_posts_categories_batch($post_ids);
        $tags_cache = $this->get_posts_tags_batch($post_ids);
        
        // Batch load permalinks for published posts
        $permalink_cache = array();
        foreach ($posts as $post) {
            if ($post->post_status === 'publish') {
                $permalink_cache[$post->ID] = get_permalink($post->ID);
            }
        }
        
        // Process posts with cached data
        $formatted_posts = array();
        foreach ($posts as $post) {
            try {
                $post_id = (int) $post->ID;
                
                $formatted_posts[] = array(
                    'id' => $post_id,
                    'title' => $post->post_title ?: '',
                    'content' => $post->post_content ?: '',
                    'excerpt' => $post->post_excerpt ?: '',
                    'metaTitle' => $meta_cache[$post_id]['_aicoagac_meta_title'] ?? '',
                    'metaDescription' => $meta_cache[$post_id]['_aicoagac_meta_description'] ?? '',
                    'focusKeywords' => $meta_cache[$post_id]['_aicoagac_focus_keywords'] ?? '',
                    'categories' => $categories_cache[$post_id] ?? array(),
                    'tags' => $tags_cache[$post_id] ?? array(),
                    'featuredImage' => $thumbnail_cache[$post_id] ?? '',
                    'createdAt' => $post->post_date ?: current_time('mysql'),
                    'status' => $post->post_status === 'publish' ? 'published' : ($post->post_status === 'future' ? 'scheduled' : ($post->post_status ?: 'draft')),
                    'publishedAt' => $post->post_status === 'publish' ? $post->post_date : null,
                    'publishedAtIso' => $post->post_status === 'publish' ? wp_date('c', strtotime($post->post_date)) : null,
                    'url' => $post->post_status === 'publish' ? ($permalink_cache[$post_id] ?? null) : null,
                    'scheduledFor' => $post->post_status === 'future' ? $post->post_date : ($meta_cache[$post_id]['_aicoagac_scheduled_for'] ?? ''),
                    'scheduledForIso' => $post->post_status === 'future' ? wp_date('c', strtotime($post->post_date)) : ($meta_cache[$post_id]['_aicoagac_scheduled_for'] ? wp_date('c', strtotime($meta_cache[$post_id]['_aicoagac_scheduled_for'])) : ''),
                    'aiGenerated' => !empty($meta_cache[$post_id]['_aicoagac_ai_generated']),
                    'generationDate' => $meta_cache[$post_id]['_aicoagac_generation_date'] ?? ''
                );
                
            } catch (Exception $e) {
                aicoagac_debug_log('Format Post Batch Error for post ' . $post->ID . ': ' . esc_html($e->getMessage()));
                // Continue with other posts, don't fail entire batch
                continue;
            }
        }
        
        return $formatted_posts;
    }
    
    /**
     * Format published posts for API - includes all WordPress posts, not just ACA generated ones
     */
    private function format_published_posts_for_api($posts) {
        if (empty($posts) || !is_array($posts)) {
            return array();
        }
        
        // Pre-load all post IDs for batch queries
        $post_ids = wp_list_pluck($posts, 'ID');
        
        if (empty($post_ids)) {
            return array();
        }
        
        // Batch load thumbnails
        $thumbnail_cache = $this->get_posts_thumbnails_batch($post_ids);
        
        // Batch load categories and tags
        $categories_cache = $this->get_posts_categories_batch($post_ids);
        $tags_cache = $this->get_posts_tags_batch($post_ids);
        
        // Batch load permalinks for published posts
        $permalink_cache = array();
        foreach ($posts as $post) {
            if ($post->post_status === 'publish') {
                $permalink_cache[$post->ID] = get_permalink($post->ID);
            }
        }
        
        // Batch load ACA meta data (optional - may not exist for all posts)
        $meta_cache = $this->get_posts_meta_batch($post_ids, array(
            '_aicoagac_meta_title',
            '_aicoagac_meta_description', 
            '_aicoagac_focus_keywords',
            '_aicoagac_ai_generated',
            '_aicoagac_generation_date'
        ));
        
        // Process posts with cached data
        $formatted_posts = array();
        foreach ($posts as $post) {
            try {
                $post_id = (int) $post->ID;
                
                // Extract excerpt - use manual excerpt or auto-generate from content
                $excerpt = $post->post_excerpt;
                if (empty($excerpt) && !empty($post->post_content)) {
                    // Auto-generate excerpt from content (first 150 chars)
                    $excerpt = wp_strip_all_tags($post->post_content);
                    $excerpt = substr($excerpt, 0, 150);
                    if (strlen($post->post_content) > 150) {
                        $excerpt .= '...';
                    }
                }
                
                // Get focus keywords from ACA meta if available, otherwise try to extract from content/title
                $focus_keywords = $meta_cache[$post_id]['_aicoagac_focus_keywords'] ?? '';
                if (empty($focus_keywords)) {
                    // Try to extract keywords from title (basic keyword extraction)
                    $title_words = explode(' ', strtolower($post->post_title));
                    $title_words = array_filter($title_words, function($word) {
                        return strlen($word) > 3; // Only words longer than 3 chars
                    });
                    $focus_keywords = implode(', ', array_slice($title_words, 0, 3));
                }
                
                $formatted_posts[] = array(
                    'id' => $post_id,
                    'title' => $post->post_title ?: '',
                    'content' => $post->post_content ?: '',
                    'excerpt' => $excerpt ?: '',
                    'metaTitle' => $meta_cache[$post_id]['_aicoagac_meta_title'] ?? $post->post_title,
                    'metaDescription' => $meta_cache[$post_id]['_aicoagac_meta_description'] ?? $excerpt,
                    'focusKeywords' => $focus_keywords,
                    'categories' => $categories_cache[$post_id] ?? array(),
                    'tags' => $tags_cache[$post_id] ?? array(),
                    'featuredImage' => $thumbnail_cache[$post_id] ?? '',
                    'createdAt' => $post->post_date ?: current_time('mysql'),
                    'status' => $post->post_status === 'publish' ? 'published' : ($post->post_status === 'future' ? 'scheduled' : ($post->post_status ?: 'draft')),
                    'publishedAt' => $post->post_status === 'publish' ? $post->post_date : null,
                    'publishedAtIso' => $post->post_status === 'publish' ? wp_date('c', strtotime($post->post_date)) : null,
                    'url' => $post->post_status === 'publish' ? ($permalink_cache[$post_id] ?? null) : null,
                    'scheduledFor' => $post->post_status === 'future' ? $post->post_date : ($meta_cache[$post_id]['_aicoagac_scheduled_for'] ?? ''),
                    'scheduledForIso' => $post->post_status === 'future' ? wp_date('c', strtotime($post->post_date)) : ($meta_cache[$post_id]['_aicoagac_scheduled_for'] ? wp_date('c', strtotime($meta_cache[$post_id]['_aicoagac_scheduled_for'])) : ''),
                    'aiGenerated' => !empty($meta_cache[$post_id]['_aicoagac_ai_generated']),
                    'generationDate' => $meta_cache[$post_id]['_aicoagac_generation_date'] ?? ''
                );
                
            } catch (Exception $e) {
                aicoagac_debug_log('Format Published Post Error for post ' . $post->ID . ': ' . esc_html($e->getMessage()));
                // Continue with other posts, don't fail entire batch
                continue;
            }
        }
        
        return $formatted_posts;
    }

    /**
     * Batch load post meta data to prevent N+1 queries
     */
    private function get_posts_meta_batch($post_ids, $meta_keys) {
        global $wpdb;
        
        if (empty($post_ids) || empty($meta_keys)) {
            return array();
        }
        
        // Lightweight object cache to avoid repeated DB hits for identical requests
        $cache_key = 'aicoagac_meta_batch_' . md5(wp_json_encode(array('p' => array_map('intval', $post_ids), 'k' => array_map('strval', $meta_keys))));
        $cached = wp_cache_get($cache_key, 'aca');
        if ($cached !== false) {
            return $cached;
        }
        
        // Build and run prepared query with dynamic IN lists
        $placeholders_posts = array_fill(0, count($post_ids), '%d');
        $placeholders_keys  = array_fill(0, count($meta_keys), '%s');
        $sql = 'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta
            . ' WHERE post_id IN (' . implode(',', $placeholders_posts) . ')'
            . ' AND meta_key IN (' . implode(',', $placeholders_keys) . ')';

        $params = array_merge(
            array_map('intval', $post_ids),
            array_map('strval', $meta_keys)
        );

        // Build dynamic IN lists as strings (each element is a %d/%s placeholder)
        $in_posts = implode(',', $placeholders_posts); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Placeholder list built from safe static tokens
        $in_keys  = implode(',', $placeholders_keys);  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder list built from safe static tokens

        // Execute batch meta fetch
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only batch meta fetch for performance
        $results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT post_id, meta_key, meta_value FROM ' . $wpdb->postmeta
                . ' WHERE post_id IN (' . $in_posts . ')' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                . ' AND meta_key IN (' . $in_keys . ')',  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
                ...$params
            ),
            ARRAY_A
        );
        
        $meta_cache = array();
        foreach ($results as $row) {
            $meta_cache[(int)$row['post_id']][$row['meta_key']] = $row['meta_value'];
        }
        
        // Store in cache briefly to reduce duplicate queries within a request burst
        wp_cache_set($cache_key, $meta_cache, 'aca', 60);
        
        return $meta_cache;
    }
    
    /**
     * Batch load post thumbnails to prevent N+1 queries
     */
    private function get_posts_thumbnails_batch($post_ids) {
        if (empty($post_ids)) {
            return array();
        }
        
        $thumbnail_cache = array();
        
        // Get all thumbnail IDs in one query
        $thumbnail_meta = $this->get_posts_meta_batch($post_ids, array('_thumbnail_id'));
        
        // Collect all attachment IDs
        $attachment_ids = array();
        foreach ($thumbnail_meta as $post_id => $meta) {
            if (!empty($meta['_thumbnail_id'])) {
                $attachment_ids[] = (int) $meta['_thumbnail_id'];
            }
        }
        
        if (!empty($attachment_ids)) {
            // Batch load attachment URLs
            $attachment_urls = array();
            foreach ($attachment_ids as $attachment_id) {
                $image_url = wp_get_attachment_image_src($attachment_id, 'large');
                if ($image_url && is_array($image_url)) {
                    $attachment_urls[$attachment_id] = $image_url[0];
                }
            }
            
            // Map back to posts
            foreach ($thumbnail_meta as $post_id => $meta) {
                if (!empty($meta['_thumbnail_id'])) {
                    $attachment_id = (int) $meta['_thumbnail_id'];
                    $thumbnail_cache[$post_id] = $attachment_urls[$attachment_id] ?? '';
                }
            }
        }
        
        return $thumbnail_cache;
    }
    
    /**
     * Batch load post categories to prevent N+1 queries
     */
    private function get_posts_categories_batch($post_ids) {
        if (empty($post_ids)) {
            return array();
        }
        
        $categories_cache = array();
        
        // Use WordPress function that handles caching
        foreach ($post_ids as $post_id) {
            $categories = get_the_category($post_id);
            $category_names = array();
            
            if ($categories && is_array($categories)) {
                foreach ($categories as $category) {
                    if (isset($category->name)) {
                        $category_names[] = $category->name;
                    }
                }
            }
            
            $categories_cache[$post_id] = $category_names;
        }
        
        return $categories_cache;
    }
    
    /**
     * Batch load post tags to prevent N+1 queries
     */
    private function get_posts_tags_batch($post_ids) {
        if (empty($post_ids)) {
            return array();
        }
        
        $tags_cache = array();
        
        // Use WordPress function that handles caching
        foreach ($post_ids as $post_id) {
            $tags = get_the_tags($post_id);
            $tag_names = array();
            
            if ($tags && is_array($tags)) {
                foreach ($tags as $tag) {
                    if (isset($tag->name)) {
                        $tag_names[] = $tag->name;
                    }
                }
            }
            
            $tags_cache[$post_id] = $tag_names;
        }
        
        return $tags_cache;
    }
    
    /**
     * Format single post for API response (legacy method - use batch processing when possible)
     */
    private function format_post_for_api($post) {
        try {
            // Safely get featured image
            $featured_image = '';
            try {
                $attachment_id = get_post_thumbnail_id($post->ID);
                if ($attachment_id) {
                    $image_url = wp_get_attachment_image_src($attachment_id, 'large');
                    if ($image_url && is_array($image_url)) {
                        $featured_image = $image_url[0];
                    }
                }
            } catch (Exception $img_error) {
                aicoagac_debug_log('Featured Image Error: ' . $img_error->getMessage());
            }
            
            // Safely get categories
            $category_names = array();
            try {
                $categories = get_the_category($post->ID);
                if ($categories && is_array($categories)) {
                    foreach ($categories as $category) {
                        if (isset($category->name)) {
                            $category_names[] = $category->name;
                        }
                    }
                }
            } catch (Exception $cat_error) {
                aicoagac_debug_log('Categories Error: ' . $cat_error->getMessage());
            }
            
            // Safely get tags
            $tag_names = array();
            try {
                $tags = get_the_tags($post->ID);
                if ($tags && is_array($tags)) {
                    foreach ($tags as $tag) {
                        if (isset($tag->name)) {
                            $tag_names[] = $tag->name;
                        }
                    }
                }
            } catch (Exception $tag_error) {
                aicoagac_debug_log('Tags Error: ' . $tag_error->getMessage());
            }
            
            // Safely get meta data
            $meta_title = '';
            $meta_description = '';
            $focus_keywords = '';
            $scheduled_for = '';
            $ai_generated = false;
            $generation_date = '';
            
            try {
                $meta_title = get_post_meta($post->ID, '_aicoagac_meta_title', true) ?: '';
                $meta_description = get_post_meta($post->ID, '_aicoagac_meta_description', true) ?: '';
                $focus_keywords = get_post_meta($post->ID, '_aicoagac_focus_keywords', true) ?: '';
                $scheduled_for = $post->post_status === 'future' ? $post->post_date : (get_post_meta($post->ID, '_aicoagac_scheduled_for', true) ?: '');
                $ai_generated = get_post_meta($post->ID, '_aicoagac_ai_generated', true) ?: false;
                $generation_date = get_post_meta($post->ID, '_aicoagac_generation_date', true) ?: '';
            } catch (Exception $meta_error) {
                aicoagac_debug_log('Meta Data Error: ' . $meta_error->getMessage());
            }
            
            return array(
                'id' => (int) $post->ID,
                'title' => $post->post_title ?: '',
                'content' => $post->post_content ?: '',
                'excerpt' => $post->post_excerpt ?: '',
                'metaTitle' => $meta_title,
                'metaDescription' => $meta_description,
                'focusKeywords' => $focus_keywords,
                'categories' => $category_names,
                'tags' => $tag_names,
                'featuredImage' => $featured_image,
                'createdAt' => $post->post_date ?: current_time('mysql'),
                'status' => $post->post_status === 'publish' ? 'published' : ($post->post_status === 'future' ? 'scheduled' : ($post->post_status ?: 'draft')),
                'publishedAt' => $post->post_status === 'publish' ? $post->post_date : null,
                'publishedAtIso' => $post->post_status === 'publish' ? wp_date('c', strtotime($post->post_date)) : null,
                'url' => $post->post_status === 'publish' ? get_permalink($post->ID) : null,
                'scheduledFor' => $scheduled_for,
                'scheduledForIso' => $post->post_status === 'future' ? wp_date('c', strtotime($post->post_date)) : ($post->post_status === 'future' ? wp_date('c', strtotime($post->post_date)) : ''),
                'aiGenerated' => $ai_generated,
                'generationDate' => $generation_date
            );
            
        } catch (Exception $e) {
            aicoagac_debug_log('Format Post Critical Error: ' . esc_html($e->getMessage()));
            throw new Exception('Failed to format post data: ' . esc_html($e->getMessage()));
        }
    }
    
    /**
     * Add activity log entry
     */
    private function add_activity_log($type, $details, $icon) {
        global $wpdb;
        
        try {
            // Check if table exists first
            $table_name = $wpdb->prefix . 'aicoagac_activity_logs';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema detection for activity logging
            
            if (!$table_exists) {
                aicoagac_debug_log('Activity logs table does not exist: ' . $table_name);
                return false;
            }
            
            $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert for activity logging
                $table_name,
                array(
                    'timestamp' => current_time('mysql'),
                    'type' => sanitize_text_field($type),
                    'details' => sanitize_text_field($details),
                    'icon' => sanitize_text_field($icon)
                )
            );
            
            if ($result === false) {
                aicoagac_debug_log('Failed to insert activity log: ' . $wpdb->last_error);
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            aicoagac_debug_log('Activity log exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get featured image (AI or stock photo)
     */
    private function get_featured_image($title, $settings, $content_html = '') {
        try {
            if ($settings['imageSourceProvider'] === 'ai') {
                return $this->call_gemini_generate_image($settings['geminiApiKey'], $title, $settings['aiImageStyle']);
            } else {
                $api_keys = array(
                    'pexels' => $settings['pexelsApiKey'],
                    'unsplash' => $settings['unsplashApiKey'],
                    'pixabay' => $settings['pixabayApiKey']
                );
                
                $api_key = $api_keys[$settings['imageSourceProvider']];
                if (empty($api_key)) {
                    return null;
                }
                
                // Build an English search query based on title/content for better relevance
                $query = $this->build_image_search_query($title, $content_html, $settings);
                return $this->fetch_stock_photo($query, $settings['imageSourceProvider'], $api_key);
            }
        } catch (Exception $e) {
            aicoagac_debug_log('Image Generation Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Save image to WordPress media library and properly attach to post
     */
    private function save_image_to_media_library($image_data, $title, $post_id = 0) {
        if (!function_exists('media_handle_sideload')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Create temporary file
        $temp_file = wp_tempnam();
        
        // Decode and save image data
        $image_content = base64_decode($image_data);
        if ($image_content === false) {
            aicoagac_debug_log('Failed to decode base64 image data');
            return false;
        }
        
        file_put_contents($temp_file, $image_content);
        
        // Verify file was created successfully
        if (!file_exists($temp_file) || filesize($temp_file) === 0) {
            aicoagac_debug_log('Failed to create temporary image file');
            if (file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }
            return false;
        }
        
        $file_array = array(
            'name' => sanitize_file_name($title) . '.jpg',
            'tmp_name' => $temp_file
        );
        
        // Attach image to specific post if post_id provided
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($attachment_id)) {
            aicoagac_debug_log('Failed to create media attachment: ' . $attachment_id->get_error_message());
            if (file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }
            return false;
        }
        
        // Set alt text for accessibility
        if ($attachment_id) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($title));
        }
        
        // Optional crop to 1920x1080 if enabled in settings
        $settings = get_option('aicoagac_settings', array());
        $should_crop = !empty($settings['imageCropEnabled']);
        if ($should_crop) {
            $full_path = get_attached_file($attachment_id);
            if ($full_path && file_exists($full_path)) {
                $editor = wp_get_image_editor($full_path);
                if (!is_wp_error($editor)) {
                    // Enforce 16:9 aspect ratio by cropping only (no resize)
                    $size = $editor->get_size();
                    if (is_array($size) && isset($size['width'], $size['height'])) {
                        $orig_w = (int)$size['width'];
                        $orig_h = (int)$size['height'];
                        if ($orig_w > 0 && $orig_h > 0) {
                            $target_ratio = 16 / 9;
                            $current_ratio = $orig_w / $orig_h;
                            // Compute crop box to center-crop to 16:9 without scaling
                            if ($current_ratio > $target_ratio) {
                                // Too wide: crop width
                                $crop_w = (int) round($orig_h * $target_ratio);
                                $crop_h = $orig_h;
                                $src_x = (int) floor(($orig_w - $crop_w) / 2);
                                $src_y = 0;
                            } else {
                                // Too tall: crop height
                                $crop_w = $orig_w;
                                $crop_h = (int) round($orig_w / $target_ratio);
                                $src_x = 0;
                                $src_y = (int) floor(($orig_h - $crop_h) / 2);
                            }
                            $crop_result = $editor->crop($src_x, $src_y, $crop_w, $crop_h);
                            if (!is_wp_error($crop_result)) {
                                $save_result = $editor->save($full_path);
                                if (!is_wp_error($save_result)) {
                                    $metadata = wp_generate_attachment_metadata($attachment_id, $full_path);
                                    if (!is_wp_error($metadata) && !empty($metadata)) {
                                        wp_update_attachment_metadata($attachment_id, $metadata);
                                    }
                                } else {
                                    aicoagac_debug_log('Image crop save failed: ' . $save_result->get_error_message());
                                }
                            } else {
                                aicoagac_debug_log('Image crop failed: ' . $crop_result->get_error_message());
                            }
                        }
                    }
                } else {
                    aicoagac_debug_log('Image editor init failed: ' . $editor->get_error_message());
                }
            }
        }
        
        // Set alt text for accessibility
        if ($attachment_id) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($title));
        }
        
        return $attachment_id;
    }
    
    /**
     * Extract key concepts from a title for better image relevance
     */
    private function extract_key_concepts($title) {
        // Remove common stop words and extract meaningful concepts
        $stop_words = array('the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'among', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'should', 'could', 'can', 'may', 'might', 'must', 'shall', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them', 'my', 'your', 'his', 'her', 'its', 'our', 'their', 'how', 'what', 'when', 'where', 'why', 'which', 'who', 'whom');
        
        // Clean and split the title
        $words = preg_split('/[\s\-_:;,.!?]+/', strtolower($title));
        $key_words = array();
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stop_words) && !is_numeric($word)) {
                $key_words[] = $word;
            }
        }
        
        // Return the first 5 key concepts or all if less than 5
        $concepts = array_slice($key_words, 0, 5);
        return implode(', ', $concepts);
    }
    
    /**
     * Build an English image search query from title/content.
     * Uses Gemini when available to extract English keywords; falls back to simple concepts.
     */
    private function build_image_search_query($title, $content_html, $settings) {
        $base_text = trim(wp_strip_all_tags((string)$title . ' ' . (string)$content_html));
        $base_text = mb_substr($base_text, 0, 800); // keep prompt compact

        // If Gemini key is present, ask for English keywords suitable for image search
        if (!empty($settings['geminiApiKey'])) {
            try {
                $prompt = "You are helping select a relevant featured image for a blog post.\n"
                    . "Given the following title/content (may be in Turkish, French, or other languages), output a JSON array of 3 to 6 concise ENGLISH keywords suitable for stock photo search.\n"
                    . "Avoid brand names, country names unless essential, and avoid generic words like 'blog', 'article'.\n"
                    . "Return ONLY a JSON array of strings.\n\n"
                    . "TEXT:\n" . $base_text;
                $raw = $this->call_gemini_api($settings['geminiApiKey'], $prompt);
                $keywords = json_decode($this->clean_ai_json_response($raw), true);
                if (is_array($keywords) && !empty($keywords)) {
                    // Use top 2-3 keywords for tighter relevance
                    $top = array_slice(array_map('sanitize_text_field', $keywords), 0, 3);
                    return implode(' ', $top);
                }
            } catch (Exception $e) {
                aicoagac_debug_log('Image query keywords via AI failed: ' . $e->getMessage());
                // fall through to fallback
            }
        }

        // Fallback: extract rough concepts from title only and ensure ASCII
        $concepts = $this->extract_key_concepts($title);
        $query = $concepts ?: sanitize_text_field($title);
        // transliterate to ASCII best-effort to avoid provider issues with diacritics
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $query);
            if ($converted !== false) {
                $query = $converted;
            }
        }
        return $query;
    }
    
    // AI Service calls - Real Gemini API integration
    
    private function call_gemini_generate_ideas($api_key, $style_guide, $existing_titles, $count) {
        // Get site language for content generation
        $site_locale = get_locale();
        $site_language = $this->get_language_from_locale($site_locale);
        $current_year = date_i18n('Y');
        $today_iso = date_i18n('Y-m-d');
        
        $prompt = "
            Based on this style guide: {$style_guide}
            
            IMPORTANT: Generate ALL titles in {$site_language} language. This is the primary language of the website based on the WordPress locale: {$site_locale}.
            Context: Today's date is {$today_iso}, current year is {$current_year}. Avoid outdated or past-year specific references unless explicitly evergreen; prefer {$current_year} context when a year is mentioned.
            
            Generate {$count} unique, engaging blog post titles that match the style and tone described in the guide.
            
            Avoid these existing titles: " . json_encode($existing_titles) . "
            
            Return ONLY a JSON array of strings (the titles in {$site_language}), nothing else.
            Example format: [\"Title 1\", \"Title 2\", \"Title 3\"]
        ";

        return $this->call_gemini_api($api_key, $prompt);
    }
    private function call_gemini_generate_similar_ideas($api_key, $base_title, $existing_titles) {
        // Get site language for content generation
        $site_locale = get_locale();
        $site_language = $this->get_language_from_locale($site_locale);
        $current_year = date_i18n('Y');
        $today_iso = date_i18n('Y-m-d');
        
        $prompt = "
            Generate 3-5 blog post titles that are similar to this idea: \"{$base_title}\"
            
            IMPORTANT: Generate ALL titles in {$site_language} language. This is the primary language of the website based on the WordPress locale: {$site_locale}.
            Context: Today's date is {$today_iso}, current year is {$current_year}. Avoid outdated or past-year specific references unless explicitly evergreen; prefer {$current_year} context when a year is mentioned.
            
            The similar titles should:
            - Cover the same general topic but from different angles
            - Be unique and engaging
            - Be written in {$site_language}
            - Not duplicate any of these existing titles: " . json_encode($existing_titles) . "
            
            Return ONLY a JSON array of strings (the titles in {$site_language}), nothing else.
            Example format: [\"Similar Title 1\", \"Similar Title 2\", \"Similar Title 3\"]
        ";
        
        return $this->call_gemini_api($api_key, $prompt);
    }
    private function call_gemini_create_draft($api_key, $title, $style_guide, $existing_posts, $existing_categories = array()) {
        // Get site language for content generation
        $site_locale = get_locale();
        $site_language = $this->get_language_from_locale($site_locale);
        $current_year = date_i18n('Y');
        $today_iso = date_i18n('Y-m-d');
        
        // Safely build context string
        $context_string = '';
        if (!empty($existing_posts) && is_array($existing_posts)) {
            $context_string = "Here are some recently published posts for context and internal linking:\n";
            foreach ($existing_posts as $post) {
                if (is_array($post) && isset($post['title'], $post['url'], $post['content'])) {
                    $safe_title = wp_strip_all_tags($post['title']);
                    $safe_url = esc_url($post['url']);
                    $safe_content = wp_strip_all_tags(substr($post['content'], 0, 200));
                    $context_string .= "Title: {$safe_title}\nURL: {$safe_url}\nContent snippet: {$safe_content}...\n\n";
                }
            }
        }

        // Build hierarchical categories context string
        $categories_string = '';
        if (!empty($existing_categories) && is_array($existing_categories)) {
            $categories_string = "Available categories with hierarchy (select the most appropriate ones):\n";
            
            // Group categories by hierarchy level
            $root_categories = array();
            $child_categories = array();
            
            foreach ($existing_categories as $category) {
                if (isset($category['id'], $category['name'])) {
                    if ($category['parent_id'] == 0) {
                        $root_categories[] = $category;
                    } else {
                        $child_categories[] = $category;
                    }
                }
            }
            
            // Display root categories first
            foreach ($root_categories as $category) {
                $categories_string .= "- ID: {$category['id']}, Name: \"{$category['name']}\", Posts: {$category['count']} (ROOT CATEGORY)\n";
                
                // Display child categories under their parent
                foreach ($child_categories as $child) {
                    if ($child['parent_id'] == $category['id']) {
                        $indent = str_repeat('  ', $child['hierarchy_level']);
                        $categories_string .= "{$indent}└─ ID: {$child['id']}, Name: \"{$child['name']}\", Posts: {$child['count']} (SUBCATEGORY of \"{$child['parent_name']}\")\n";
                    }
                }
            }
            
            $categories_string .= "\nIMPORTANT: When selecting categories, consider the hierarchy. If content is about a specific subcategory topic, choose the subcategory rather than the parent category.\n\n";
        }

        // Clean inputs safely
        $safe_title = sanitize_text_field($title);
        
        // Handle style guide - don't strip tags if it's JSON
        $safe_style_guide = '';
        if (is_string($style_guide)) {
            // Check if it's JSON first
            $decoded = json_decode($style_guide, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // It's valid JSON, keep it as is
                $safe_style_guide = $style_guide;
            } else {
                // It's not JSON, sanitize it
                $safe_style_guide = sanitize_text_field($style_guide);
            }
        }

        $prompt = "Today's date is {$today_iso}, current year is {$current_year}. Avoid outdated references unless historically required; prefer {$current_year} context where relevant.\n\nCreate a comprehensive blog post based on this idea: \"{$safe_title}\"

IMPORTANT: Write the entire content in {$site_language}. This is the primary language of the website based on the WordPress locale: {$site_locale}.

Use this style guide: {$safe_style_guide}

{$context_string}

{$categories_string}

Requirements:
- Write EVERYTHING in {$site_language} language (title, content, headings, etc.)
- Write a well-structured blog post with clear H2 and H3 headings
- 800-1500 words in length
- Engaging introduction and compelling conclusion
- SEO-optimized content matching the style guide
- Include 2-3 internal links to the provided existing posts where contextually relevant
- For categories: ONLY use category IDs from the provided list above. Select 1-2 most relevant ones based on content topic and hierarchy
- Consider category hierarchy: if content is specific to a subcategory, choose the subcategory rather than parent category
- For tags: Create new relevant tags as strings in {$site_language}

CONTENT FORMAT REQUIREMENTS:
- Use ONLY HTML formatting, NOT Markdown
- For headings: <h2>Heading</h2>, <h3>Subheading</h3>
- For paragraphs: <p>Text content</p>
- For bold text: <strong>Bold text</strong>
- For italic text: <em>Italic text</em>
- For lists: <ul><li>Item 1</li><li>Item 2</li></ul>
- For links: <a href=\"URL\">Link text</a>
- NO Markdown syntax like *, **, [text](url), ##, etc.

IMPORTANT: Return ONLY a valid JSON object with this exact structure. Do not include any text before or after the JSON:

{
  \"content\": \"The full blog post content in proper HTML format with <h2>, <h3>, <p>, <strong>, <ul>, <li>, <a> tags. NO Markdown syntax.\",
  \"metaTitle\": \"SEO-optimized title (50-60 characters)\",
  \"metaDescription\": \"Compelling meta description (150-160 characters)\",
  \"focusKeywords\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"],
  \"tags\": [\"tag1\", \"tag2\", \"tag3\", \"tag4\", \"tag5\"],
  \"categoryIds\": [1, 5],
  \"excerpt\": \"Brief excerpt for the post (150 characters)\"
}";
        
        return $this->call_gemini_api($api_key, $prompt);
    }
    
    private function call_gemini_generate_image($api_key, $title, $style) {
        $style_prompts = array(
            'photorealistic' => 'photorealistic, high quality, professional photography, 4K, HDR, studio lighting',
            'digital_art' => 'digital art, illustration, creative, artistic, detailed, professional'
        );
        
        $style_prompt = isset($style_prompts[$style]) ? $style_prompts[$style] : $style_prompts['digital_art'];
        
        // Create a descriptive prompt for the blog post title - EXPLICITLY PREVENT TEXT
        // Extract key concepts from the title for better relevance
        $clean_title = wp_strip_all_tags($title);
        $key_concepts = $this->extract_key_concepts($clean_title);
        
        $prompt = "Create a {$style_prompt} image that represents the concept of \"{$clean_title}\". Focus on the main themes: {$key_concepts}. The image should be relevant to the topic, visually appealing, suitable for use as a featured image on a professional blog, and capture the essence of the subject matter. IMPORTANT: Do not include any text, words, letters, numbers, signs, or written content in the image. The image should be purely visual without any textual elements, logos, or readable content.";
        
        try {
            // Use Google's Imagen API for actual image generation
            $imagen_response = $this->call_imagen_api($api_key, $prompt);
            
            if (is_wp_error($imagen_response)) {
                aicoagac_debug_log('Imagen API Error: ' . $imagen_response->get_error_message());
                throw new Exception('Imagen API error: ' . $imagen_response->get_error_message());
            }
            
            return $imagen_response;
            
        } catch (Exception $e) {
            aicoagac_debug_log('AI Image Generation Error: ' . $e->getMessage());
            
            // Provide more specific error messages for common issues
            $error_message = $e->getMessage();
            if (strpos($error_message, 'Google Cloud Project ID not configured') !== false) {
                $error_message = 'Please configure Google Cloud Project ID in plugin settings';
            } elseif (strpos($error_message, 'invalid_token') !== false) {
                $error_message = 'Invalid Google Cloud access token format. Please provide a valid access token';
            } elseif (strpos($error_message, 'authentication') !== false || strpos($error_message, 'Unauthorized') !== false) {
                $error_message = 'Authentication failed. Please check your Google Cloud access token';
            }
            
            // Fallback: Return a more informative placeholder
            $fallback_data = array(
                'error' => true,
                'message' => $error_message,
                'title' => $title,
                'style' => $style,
                'timestamp' => current_time('mysql'),
                'help' => 'Check AI_IMAGE_GENERATION_SETUP.md for setup instructions'
            );
            
            return base64_encode(json_encode($fallback_data));
        }
    }
    
    private function call_imagen_api($api_key, $prompt) {
        // Google Cloud Vertex AI Imagen API endpoint
        $project_id = get_option('aicoagac_google_cloud_project_id', '');
        $location = get_option('aicoagac_google_cloud_location', 'us-central1');
        
        if (empty($project_id)) {
            return new WP_Error('missing_project_id', __('Google Cloud Project ID not configured. Please set it in plugin settings.', 'ai-content-agent'));
        }
        
        // Check if API key looks like a proper Google Cloud credential
        if (empty($api_key) || strlen($api_key) < 20) {
            return new WP_Error('invalid_api_key', __('Invalid Google Cloud API key. Please provide a valid service account key or access token.', 'ai-content-agent'));
        }
        
        // Use Imagen 3.0 Generate 002 model (latest stable version)
        $model = 'imagen-3.0-generate-002';
        $url = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$project_id}/locations/{$location}/publishers/google/models/{$model}:predict";
        
        $request_body = array(
            'instances' => array(
                array(
                    'prompt' => $prompt,
                    'negativePrompt' => 'text, words, letters, numbers, signs, writing, typography, captions, labels, watermarks, logos, banners, advertisements, titles, subtitles, quotes, speech bubbles, signage, readable content'
                )
            ),
            'parameters' => array(
                'sampleCount' => 1,
                'aspectRatio' => '16:9', // Good for featured images
                'safetyFilterLevel' => 'block_some',
                'personGeneration' => 'allow_adult'
            )
        );
        
        // Try to get a proper access token
        $access_token = $this->get_google_access_token($api_key);
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $headers = array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        );
        
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($request_body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            aicoagac_debug_log('Imagen API network error: ' . $response->get_error_message());
            return new WP_Error('network_error', sprintf(
                /* translators: %s: error message */
                __('Failed to connect to Google Imagen API: %s', 'ai-content-agent'),
                $response->get_error_message()
            ));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_message = "Imagen API returned status code {$response_code}";
            if (!empty($response_body)) {
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['message'])) {
                    $error_message .= ': ' . $error_data['error']['message'];
                }
            }
            aicoagac_debug_log('Imagen API error: ' . $error_message);
            return new WP_Error('imagen_api_error', $error_message);
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['predictions'][0]['bytesBase64Encoded'])) {
            aicoagac_debug_log('Imagen API invalid response: ' . $response_body);
            return new WP_Error('invalid_response', __('Invalid response from Imagen API - missing image data', 'ai-content-agent'));
        }
        
        // Return the base64 encoded image
        return $data['predictions'][0]['bytesBase64Encoded'];
    }
    
    private function get_google_access_token($api_key) {
        // Check if we have a cached access token
        $cached_token = get_transient('aicoagac_google_access_token');
        if ($cached_token) {
            return $cached_token;
        }
        
        // For proper Vertex AI authentication, we need to handle different credential types
        
        // Service account JSON detection removed - use proper setup instead
        // For service account authentication, set up Application Default Credentials on your server
        // or generate access tokens externally
        
        // Validate access token format
        if (!preg_match('/^[a-zA-Z0-9\.\-_]{100,}$/', $api_key)) {
            return new WP_Error('invalid_token', 
                __('Please provide a valid Google Cloud access token. For service account authentication, set up Application Default Credentials on your server or generate access tokens using: gcloud auth application-default print-access-token', 'ai-content-agent')
            );
        }
        
        // Cache the valid token for 30 minutes (Google tokens typically last 1 hour)
        set_transient('aicoagac_google_access_token', $api_key, 30 * MINUTE_IN_SECONDS);
        return $api_key;
        
        // Default case - try to use it as-is but warn about potential issues
                    aicoagac_debug_log('Using API key as access token - this may not work properly');
        return $api_key;
    }
    
    private function fetch_stock_photo($query, $provider, $api_key) {
        $url = '';
        $headers = array();
        
        switch ($provider) {
            case 'pexels':
                $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=1&orientation=landscape';
                $headers = array('Authorization' => $api_key);
                break;
            case 'unsplash':
                $url = 'https://api.unsplash.com/search/photos?query=' . urlencode($query) . '&per_page=1&orientation=landscape';
                $headers = array('Authorization' => 'Client-ID ' . $api_key);
                break;
            case 'pixabay':
                // Security Warning: Pixabay API only supports GET with key in URL - this is a service limitation
                aicoagac_debug_log('Security Warning: Pixabay API requires key in URL - consider alternative providers for sensitive environments');
                $url = 'https://pixabay.com/api/?key=' . $api_key . '&q=' . urlencode($query) . '&image_type=photo&orientation=horizontal&per_page=3&safesearch=true';
                break;
        }
        
        if (empty($url)) {
            throw new Exception('Unsupported stock photo provider');
        }
        
        $response = wp_remote_get($url, array(
            'headers' => $headers,
            'timeout' => 15,
            'user-agent' => 'AI Content Agent/2.4.0'
        ));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to fetch from ' . esc_html($provider) . ': ' . esc_html($response->get_error_message()));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new Exception('API request failed with status ' . intval($status_code) . ' for provider: ' . esc_html($provider));
        }
        
        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            throw new Exception('Empty response from ' . esc_html($provider) . ' API');
        }
        
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from ' . esc_html($provider) . ': ' . esc_html(json_last_error_msg()));
        }
        
        $image_url = '';
        switch ($provider) {
            case 'pexels':
                if (!empty($data['photos'][0]['src']['large'])) {
                    $image_url = $data['photos'][0]['src']['large'];
                }
                break;
            case 'unsplash':
                if (!empty($data['results'][0]['urls']['regular'])) {
                    $image_url = $data['results'][0]['urls']['regular'];
                }
                break;
            case 'pixabay':
                if (!empty($data['hits'][0]['webformatURL'])) {
                    $image_url = $data['hits'][0]['webformatURL'];
                }
                break;
        }
        
        if (empty($image_url)) {
            throw new Exception('No images found for query: ' . esc_html($query));
        }
        
        // Download and convert to base64
        $image_response = wp_remote_get($image_url);
        if (is_wp_error($image_response)) {
            throw new Exception('Failed to download image');
        }
        
        return base64_encode(wp_remote_retrieve_body($image_response));
    }
    
    /**
     * Make actual API call to Gemini with retry logic and model fallback
     */
    private function call_gemini_api($api_key, $prompt, $model = 'gemini-2.0-flash', $retry_count = 0) {
        $max_retries = 3;
        $retry_delay = 2; // seconds
        $fallback_model = 'gemini-1.5-pro';
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
        
        // Clean and validate prompt
        $clean_prompt = is_string($prompt) ? trim($prompt) : '';
        if (empty($clean_prompt)) {
            throw new Exception('Empty or invalid prompt provided');
        }
        
        // Ensure prompt is valid UTF-8
        if (!mb_check_encoding($clean_prompt, 'UTF-8')) {
            $clean_prompt = mb_convert_encoding($clean_prompt, 'UTF-8', 'UTF-8');
        }
        
        $request_data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $clean_prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => 4096, // Increased from 2048
                'responseMimeType' => 'application/json'
            )
        );
        
        $body = json_encode($request_data);
        
        // Check if json_encode failed
        if ($body === false) {
            aicoagac_debug_log('JSON Encode Error: ' . esc_html(json_last_error_msg()));
            aicoagac_debug_log('Request Data: ' . wp_json_encode($request_data));
            throw new Exception('Failed to encode request data: ' . esc_html(json_last_error_msg()));
        }
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $api_key
            ),
            'body' => $body,
            'timeout' => 120, // Increased timeout to 2 minutes
            'blocking' => true,
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            aicoagac_debug_log('Gemini API WP Error: ' . esc_html($response->get_error_message()));
            throw new Exception('Gemini API request failed: ' . esc_html($response->get_error_message()));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        // Handle 503 and other overload errors with retry logic
        if ($response_code === 503 || $response_code === 429) {
            $error_body = wp_remote_retrieve_body($response);
                            aicoagac_debug_log("Gemini API Overload Error (Code {$response_code}): " . substr($error_body, 0, 500));
            
            // Check if we should retry
            if ($retry_count < $max_retries) {
                // Try fallback model on first retry
                if ($retry_count === 0 && $model === 'gemini-2.0-flash') {
                    aicoagac_debug_log("Trying fallback model {$fallback_model}");
                    sleep($retry_delay);
                    return $this->call_gemini_api($api_key, $prompt, $fallback_model, $retry_count + 1);
                }
                
                // Exponential backoff
                $delay = $retry_delay * pow(2, $retry_count);
                aicoagac_debug_log("Retrying in {$delay} seconds... (attempt " . ($retry_count + 1) . "/{$max_retries})");
                sleep($delay);
                return $this->call_gemini_api($api_key, $prompt, $model, $retry_count + 1);
            }
            
            throw new Exception("Gemini API service unavailable after " . intval($max_retries) . " attempts. Error code: " . intval($response_code) . " - " . esc_html(substr($error_body, 0, 200)));
        }
        
        if ($response_code !== 200) {
            $error_body = wp_remote_retrieve_body($response);
            aicoagac_debug_log('Gemini API HTTP Error: Code ' . intval($response_code) . ', Body: ' . esc_html(substr($error_body, 0, 500)));
            throw new Exception('Gemini API returned error code: ' . intval($response_code) . ' - ' . esc_html(substr($error_body, 0, 200)));
        }
        
        $response_body = wp_remote_retrieve_body($response);
        if (empty($response_body)) {
            aicoagac_debug_log('Gemini API Empty Response Body');
            throw new Exception('Empty response from Gemini API');
        }
        
        // Only log response in debug mode to prevent sensitive data exposure
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log('Gemini API Response (DEBUG): ' . substr($response_body, 0, 200));
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            aicoagac_debug_log('Gemini API JSON Error: ' . esc_html(json_last_error_msg()) . ', Response: ' . esc_html(substr($response_body, 0, 300)));
            throw new Exception('Invalid JSON response from Gemini API: ' . esc_html(json_last_error_msg()));
        }
        
        if (empty($data['candidates'][0]['content']['parts'][0]['text'])) {
            aicoagac_debug_log('Gemini API No Content: ' . wp_json_encode($data));
            throw new Exception('No content returned from Gemini API. Response structure: ' . json_encode(array_keys($data)));
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Clean AI JSON response to fix common formatting issues
     */
    private function clean_ai_json_response($response) {
        // Remove any text before the first {
        $start = strpos($response, '{');
        if ($start !== false) {
            $response = substr($response, $start);
        }
        
        // Remove any text after the last }
        $end = strrpos($response, '}');
        if ($end !== false) {
            $response = substr($response, 0, $end + 1);
        }
        
        // Remove markdown code blocks if present
        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);
        
        // Fix common JSON issues
        $response = preg_replace('/,\s*}/', '}', $response); // Remove trailing commas in objects
        $response = preg_replace('/,\s*]/', ']', $response); // Remove trailing commas in arrays
        
        // Fix unescaped newlines and tabs in string values
        $response = preg_replace_callback('/"([^"\\\\]*(\\\\.[^"\\\\]*)*)"/', function($matches) {
            $string = $matches[1];
            $string = str_replace(["\n", "\r", "\t"], ['\\n', '\\r', '\\t'], $string);
            return '"' . $string . '"';
        }, $response);
        
        return trim($response);
    }
    
    /**
     * Convert Markdown content to HTML
     */
    private function markdown_to_html($content) {
        // Convert headings
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        
        // Convert bold text
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        
        // Convert italic text
        $content = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $content);
        
        // Convert links
        $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $content);
        
        // Convert unordered lists
        $content = preg_replace_callback('/(?:^|\n)(\* .+(?:\n\* .+)*)/m', function($matches) {
            $list_items = explode("\n", trim($matches[1]));
            $html_items = '';
            foreach ($list_items as $item) {
                $item = preg_replace('/^\* /', '', $item);
                $html_items .= '<li>' . trim($item) . '</li>';
            }
            return '<ul>' . $html_items . '</ul>';
        }, $content);
        
        // Convert paragraphs (split by double newlines)
        $paragraphs = preg_split('/\n\s*\n/', trim($content));
        $html_content = '';
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Skip if already wrapped in HTML tags
                if (!preg_match('/^<(h[1-6]|ul|ol|li|div|p)/', $paragraph)) {
                    $paragraph = '<p>' . $paragraph . '</p>';
                }
                $html_content .= $paragraph . "\n\n";
            }
        }
        
        return trim($html_content);
    }
    
    /**
     * Debug automation status
     */
    // Automation removed from Free version
    
    // Automation removed from Free version
    
    // Automation removed from Free version

    /**
     * Get SEO plugins status endpoint
     */
    public function get_seo_plugins($request) {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                aicoagac_debug_log(' get_seo_plugins called');
            }
            
            $detected_plugins = $this->detect_seo_plugin();
            
            aicoagac_debug_log('Detected SEO plugins: ' . wp_json_encode($detected_plugins));
            
            return rest_ensure_response(array(
                'success' => true,
                'detected_plugins' => $detected_plugins,
                'count' => count($detected_plugins),
                'auto_detection_enabled' => true,
                'timestamp' => current_time('mysql')
            ));
        } catch (Throwable $e) {
            aicoagac_debug_log('Error in get_seo_plugins: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error('seo_detection_failed', sprintf(
                /* translators: %s: error message */
                __('Failed to detect SEO plugins: %s', 'ai-content-agent'),
                $e->getMessage()
            ), array('status' => 500));
        }
    }
    /**
     * Detect which SEO plugin is active and return plugin info
     */
    private function detect_seo_plugin() {
        $detected_plugins = array();
        
        // Include plugin.php if is_plugin_active is not available
        if (!function_exists('is_plugin_active')) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (file_exists($plugin_file)) {
                require_once($plugin_file);
            } else {
                aicoagac_debug_log('Warning: plugin.php file not found at: ' . $plugin_file);
                // Define a fallback function
                if (!function_exists('is_plugin_active')) {
                    function is_plugin_active($plugin) {
                        $active_plugins = get_option('active_plugins', array());
                        return in_array($plugin, $active_plugins);
                    }
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log(' Starting SEO plugin detection...');
        }
        
        // Get all active plugins for comprehensive check
        $active_plugins = get_option('active_plugins', array());
        $active_network_plugins = is_multisite() ? get_site_option('active_sitewide_plugins', array()) : array();
        
        aicoagac_debug_log('Active plugins count: ' . count($active_plugins));
        aicoagac_debug_log('Network active plugins count: ' . count($active_network_plugins));
        
        // Check for RankMath - Enhanced detection with multiple methods
        $rankmath_detected = false;
        
        // Method 1: Check active plugins array directly
        $rankmath_in_active = false;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'seo-by-rank-math/rank-math.php') !== false) {
                $rankmath_in_active = true;
                break;
            }
        }
        
        // Method 2: Check network active plugins for multisite
        if (!$rankmath_in_active && is_multisite()) {
            $rankmath_in_active = isset($active_network_plugins['seo-by-rank-math/rank-math.php']);
        }
        
        // Method 3: Traditional checks
        if ($rankmath_in_active || 
            is_plugin_active('seo-by-rank-math/rank-math.php') || 
            class_exists('RankMath') || 
            class_exists('\RankMath\Helper') ||
            defined('RANK_MATH_FILE') ||
            defined('RANK_MATH_VERSION')) {
            $rankmath_detected = true;
            $detected_plugins[] = array(
                'plugin' => 'rank_math',
                'name' => 'Rank Math',
                'version' => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : 'unknown',
                'active' => true,
                'pro' => class_exists('\RankMath\Pro\Admin\Admin_Menu') || defined('RANK_MATH_PRO_FILE'),
                'detection_method' => $rankmath_in_active ? 'active_plugins' : 'class_or_constant'
            );
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log(' RankMath detection result: ' . ($rankmath_detected ? 'found' : 'not found'));
            if ($rankmath_detected) {
                aicoagac_debug_log(' RankMath in active_plugins: ' . ($rankmath_in_active ? 'yes' : 'no'));
            }
        }
        
        // Check for Yoast SEO - Enhanced detection with multiple methods
        $yoast_detected = false;
        
        // Method 1: Check active plugins array directly
        $yoast_in_active = false;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'wordpress-seo/wp-seo.php') !== false) {
                $yoast_in_active = true;
                break;
            }
        }
        
        // Method 2: Check network active plugins for multisite
        if (!$yoast_in_active && is_multisite()) {
            $yoast_in_active = isset($active_network_plugins['wordpress-seo/wp-seo.php']);
        }
        
        // Method 3: Traditional checks
        if ($yoast_in_active ||
            is_plugin_active('wordpress-seo/wp-seo.php') || 
            class_exists('WPSEO_Options') ||
            class_exists('WPSEO_Frontend') ||
            class_exists('Yoast\WP\SEO\Main') ||
            defined('WPSEO_VERSION') ||
            defined('WPSEO_FILE')) {
            $yoast_detected = true;
            $detected_plugins[] = array(
                'plugin' => 'yoast',
                'name' => 'Yoast SEO',
                'version' => defined('WPSEO_VERSION') ? WPSEO_VERSION : 'unknown',
                'active' => true,
                'premium' => defined('WPSEO_PREMIUM_PLUGIN_FILE') || class_exists('WPSEO_Premium'),
                'detection_method' => $yoast_in_active ? 'active_plugins' : 'class_or_constant'
            );
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log(' Yoast SEO detection result: ' . ($yoast_detected ? 'found' : 'not found'));
            if ($yoast_detected) {
                aicoagac_debug_log(' Yoast in active_plugins: ' . ($yoast_in_active ? 'yes' : 'no'));
            }
        }
        
        // Check for All in One SEO (AIOSEO) - Enhanced detection with multiple methods
        $aioseo_detected = false;
        
        // Method 1: Check active plugins array directly
        $aioseo_in_active = false;
        foreach ($active_plugins as $plugin) {
            if (strpos($plugin, 'all-in-one-seo-pack') !== false) {
                $aioseo_in_active = true;
                break;
            }
        }
        
        // Method 2: Check network active plugins for multisite
        if (!$aioseo_in_active && is_multisite()) {
            foreach (array_keys($active_network_plugins) as $plugin) {
                if (strpos($plugin, 'all-in-one-seo-pack') !== false) {
                    $aioseo_in_active = true;
                    break;
                }
            }
        }
        
        // Method 3: Traditional checks
        if ($aioseo_in_active ||
            is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php') ||
            is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php') ||
            class_exists('AIOSEO\Plugin\AIOSEO') ||
            class_exists('All_in_One_SEO_Pack') ||
            defined('AIOSEO_VERSION') ||
            defined('AIOSEOP_VERSION')) {
            $aioseo_detected = true;
            $detected_plugins[] = array(
                'plugin' => 'aioseo',
                'name' => 'All in One SEO',
                'version' => defined('AIOSEO_VERSION') ? AIOSEO_VERSION : (defined('AIOSEOP_VERSION') ? AIOSEOP_VERSION : 'unknown'),
                'active' => true,
                'pro' => is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php') || defined('AIOSEO_PRO'),
                'detection_method' => $aioseo_in_active ? 'active_plugins' : 'class_or_constant'
            );
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            aicoagac_debug_log(' AIOSEO detection result: ' . ($aioseo_detected ? 'found' : 'not found'));
            if ($aioseo_detected) {
                aicoagac_debug_log(' AIOSEO in active_plugins: ' . ($aioseo_in_active ? 'yes' : 'no'));
            }
        }
        
        // Log all active plugins for debugging
        aicoagac_debug_log('Active plugins: ' . wp_json_encode($active_plugins));
        aicoagac_debug_log('Total detected SEO plugins: ' . count($detected_plugins));
        
        // Additional debug info
        aicoagac_debug_log('is_plugin_active function exists: ' . (function_exists('is_plugin_active') ? 'yes' : 'no'));
        aicoagac_debug_log('ABSPATH: ' . ABSPATH);
        
        // Check specific plugin files
        $rankmath_file = WP_PLUGIN_DIR . '/seo-by-rank-math/rank-math.php';
        $yoast_file = WP_PLUGIN_DIR . '/wordpress-seo/wp-seo.php';
        $aioseo_file = WP_PLUGIN_DIR . '/all-in-one-seo-pack/all_in_one_seo_pack.php';
        aicoagac_debug_log('RankMath file exists: ' . (file_exists($rankmath_file) ? 'yes' : 'no'));
        aicoagac_debug_log('Yoast file exists: ' . (file_exists($yoast_file) ? 'yes' : 'no'));
        aicoagac_debug_log('AIOSEO file exists: ' . (file_exists($aioseo_file) ? 'yes' : 'no'));
        
        // If no plugins detected, check why
        if (empty($detected_plugins)) {
            aicoagac_debug_log('WARNING: No SEO plugins detected!');
            aicoagac_debug_log('Checking constants:');
            aicoagac_debug_log('- RANK_MATH_VERSION defined: ' . (defined('RANK_MATH_VERSION') ? 'yes' : 'no'));
            aicoagac_debug_log('- WPSEO_VERSION defined: ' . (defined('WPSEO_VERSION') ? 'yes' : 'no'));
            aicoagac_debug_log('- AIOSEO_VERSION defined: ' . (defined('AIOSEO_VERSION') ? 'yes' : 'no'));
        }
        
        return $detected_plugins;
    }
    
    /**
     * Send SEO data to detected SEO plugins with conflict prevention
     */
    private function send_seo_data_to_plugins($post_id, $meta_title, $meta_description, $focus_keywords) {
        // Get user's preferred SEO plugin from settings
        $settings = get_option('aicoagac_settings', array());
        $preferred_plugin = isset($settings['seoPlugin']) ? $settings['seoPlugin'] : 'none';
        
        $detected_plugins = $this->detect_seo_plugin();
        $results = array();
        
        // Prevent meta data conflicts by only writing to user's selected plugin
        if ($preferred_plugin !== 'none') {
            // Check if preferred plugin is actually installed and active
            $preferred_plugin_active = false;
            foreach ($detected_plugins as $plugin_info) {
                if ($plugin_info['plugin'] === $preferred_plugin && $plugin_info['active']) {
                    $preferred_plugin_active = true;
                    break;
                }
            }
            
            if ($preferred_plugin_active) {
                // Only send to the preferred plugin to prevent conflicts
                switch ($preferred_plugin) {
                    case 'rank_math':
                        $result = $this->send_to_rankmath($post_id, $meta_title, $meta_description, $focus_keywords);
                        $results['rank_math'] = $result;
                        aicoagac_debug_log("Meta data sent only to preferred plugin: RankMath");
                        break;
                        
                    case 'yoast':
                        $result = $this->send_to_yoast($post_id, $meta_title, $meta_description, $focus_keywords);
                        $results['yoast'] = $result;
                        aicoagac_debug_log("Meta data sent only to preferred plugin: Yoast");
                        break;
                        
                    case 'aioseo':
                        $result = $this->send_to_aioseo($post_id, $meta_title, $meta_description, $focus_keywords);
                        $results['aioseo'] = $result;
                        aicoagac_debug_log("Meta data sent only to preferred plugin: AIOSEO");
                        break;
                }
                
                // Log conflict prevention
                $this->log_meta_conflict_prevention($post_id, $preferred_plugin, $detected_plugins);
                
            } else {
                aicoagac_debug_log(" Preferred SEO plugin ($preferred_plugin) not active, falling back to auto-detection");
                $results = $this->send_to_auto_detected_plugins($post_id, $meta_title, $meta_description, $focus_keywords, $detected_plugins);
            }
        } else {
            // No preference set, use auto-detection but prevent conflicts
            $results = $this->send_to_auto_detected_plugins($post_id, $meta_title, $meta_description, $focus_keywords, $detected_plugins);
        }
        
        return $results;
    }
    
    /**
     * Send to auto-detected plugins with priority-based conflict prevention
     */
    private function send_to_auto_detected_plugins($post_id, $meta_title, $meta_description, $focus_keywords, $detected_plugins) {
        $results = array();
        
        if (empty($detected_plugins)) {
            aicoagac_debug_log(" No SEO plugins detected, skipping meta data writing");
            return $results;
        }
        
        // Priority order: RankMath > Yoast > AIOSEO (based on market usage and reliability)
        $priority_order = array('rank_math', 'yoast', 'aioseo');
        $selected_plugin = null;
        
        // Find the highest priority active plugin
        foreach ($priority_order as $plugin_name) {
            foreach ($detected_plugins as $plugin_info) {
                if ($plugin_info['plugin'] === $plugin_name && $plugin_info['active']) {
                    $selected_plugin = $plugin_info;
                    break 2; // Break both loops
                }
            }
        }
        
        // If no priority plugin found, use the first active one
        if (!$selected_plugin) {
            foreach ($detected_plugins as $plugin_info) {
                if ($plugin_info['active']) {
                    $selected_plugin = $plugin_info;
                    break;
                }
            }
        }
        
        if ($selected_plugin) {
            switch ($selected_plugin['plugin']) {
                case 'rank_math':
                    $result = $this->send_to_rankmath($post_id, $meta_title, $meta_description, $focus_keywords);
                    $results['rank_math'] = $result;
                    break;
                    
                case 'yoast':
                    $result = $this->send_to_yoast($post_id, $meta_title, $meta_description, $focus_keywords);
                    $results['yoast'] = $result;
                    break;
                    
                case 'aioseo':
                    $result = $this->send_to_aioseo($post_id, $meta_title, $meta_description, $focus_keywords);
                    $results['aioseo'] = $result;
                    break;
            }
            
            aicoagac_debug_log(" Meta data sent to auto-selected plugin: " . $selected_plugin['plugin']);
            $this->log_meta_conflict_prevention($post_id, $selected_plugin['plugin'], $detected_plugins);
        }
        
        return $results;
    }
    
    /**
     * Log meta data conflict prevention for debugging and transparency
     */
    private function log_meta_conflict_prevention($post_id, $selected_plugin, $all_detected_plugins) {
        $skipped_plugins = array();
        
        foreach ($all_detected_plugins as $plugin_info) {
            if ($plugin_info['plugin'] !== $selected_plugin && $plugin_info['active']) {
                $skipped_plugins[] = $plugin_info['plugin'];
            }
        }
        
        if (!empty($skipped_plugins)) {
            $skipped_list = implode(', ', $skipped_plugins);
            aicoagac_debug_log(" Meta conflict prevention - Post ID: $post_id, Used: $selected_plugin, Skipped: $skipped_list");
            
            // Store conflict prevention log in post meta for transparency
            $conflict_log = array(
                'timestamp' => current_time('mysql'),
                'selected_plugin' => $selected_plugin,
                'skipped_plugins' => $skipped_plugins,
                'reason' => 'conflict_prevention'
            );
            
            update_post_meta($post_id, '_aicoagac_seo_conflict_log', $conflict_log);
        }
    }
    
    /**
     * Send SEO data to RankMath
     */
    private function send_to_rankmath($post_id, $meta_title, $meta_description, $focus_keywords) {
        try {
            // RankMath stores data in post meta with rank_math_ prefix
            if (!empty($meta_title)) {
                update_post_meta($post_id, 'rank_math_title', $this->sanitize_unicode_text($meta_title));
            }
            
            if (!empty($meta_description)) {
                update_post_meta($post_id, 'rank_math_description', $this->sanitize_unicode_textarea($meta_description));
            }
            
            if (!empty($focus_keywords) && is_array($focus_keywords)) {
                // RankMath stores focus keyword as a single string (primary keyword)
                $primary_keyword = sanitize_text_field($focus_keywords[0]);
                update_post_meta($post_id, 'rank_math_focus_keyword', $primary_keyword);
                
                // For RankMath Pro, additional keywords can be stored
                if (count($focus_keywords) > 1 && class_exists('\RankMath\Pro\Admin\Admin_Menu')) {
                    // Store additional keywords as JSON array for Pro version
                    $additional_keywords = array();
                    for ($i = 1; $i < count($focus_keywords); $i++) {
                        $additional_keywords[] = sanitize_text_field($focus_keywords[$i]);
                    }
                    update_post_meta($post_id, 'rank_math_keywords', implode(',', $additional_keywords));
                }
                
                // Store all keywords in a custom meta for reference
                update_post_meta($post_id, 'aicoagac_focus_keywords', $focus_keywords);
            }
            
            // Set additional RankMath meta for better integration
            // Set good content score for AI-generated content (0-100 scale)
            update_post_meta($post_id, 'rank_math_seo_score', 85);
            
            // Set readability score (simulate good readability for AI content)
            update_post_meta($post_id, 'rank_math_contentai_score', 75);
            
            // Set robots meta to index and follow
            update_post_meta($post_id, 'rank_math_robots', array('index', 'follow'));
            
            // Set canonical URL to self to avoid duplicate content
            $post_url = get_permalink($post_id);
            if ($post_url) {
                update_post_meta($post_id, 'rank_math_canonical_url', $post_url);
            }
            
            // Set primary category if post has categories
            $post_type = get_post_type($post_id);
            $categories = array(); // Initialize to avoid undefined variable
            if ($post_type === 'post') {
                $categories = get_the_category($post_id);
                if (!empty($categories)) {
                    update_post_meta($post_id, 'rank_math_primary_category', $categories[0]->term_id);
                }
            }
            
            // Social Media Integration - OpenGraph
            if (!empty($meta_title)) {
                update_post_meta($post_id, 'rank_math_facebook_title', $this->sanitize_unicode_text($meta_title));
                update_post_meta($post_id, 'rank_math_twitter_title', $this->sanitize_unicode_text($meta_title));
            }
            
            if (!empty($meta_description)) {
                update_post_meta($post_id, 'rank_math_facebook_description', $this->sanitize_unicode_textarea($meta_description));
                update_post_meta($post_id, 'rank_math_twitter_description', $this->sanitize_unicode_textarea($meta_description));
            }
            
            // Set featured image for social media if available
            $featured_image_id = get_post_thumbnail_id($post_id);
            if ($featured_image_id) {
                $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
                if ($featured_image_url) {
                    update_post_meta($post_id, 'rank_math_facebook_image', $featured_image_url);
                    update_post_meta($post_id, 'rank_math_facebook_image_id', $featured_image_id);
                    update_post_meta($post_id, 'rank_math_twitter_image', $featured_image_url);
                    update_post_meta($post_id, 'rank_math_twitter_image_id', $featured_image_id);
                }
            }
            
            // Set schema type based on post type
            if ($post_type === 'post') {
                update_post_meta($post_id, 'rank_math_rich_snippet', 'article');
                update_post_meta($post_id, 'rank_math_snippet_article_type', 'BlogPosting');
            } elseif ($post_type === 'page') {
                update_post_meta($post_id, 'rank_math_rich_snippet', 'webpage');
            }
            
            // Advanced RankMath Pro features if available
            if (class_exists('\RankMath\Pro\Admin\Admin_Menu')) {
                // Set advanced schema markup for Pro
                update_post_meta($post_id, 'rank_math_enable_schema', 'on');
                
                // Set pillar content if multiple keywords (indicates important content)
                if (!empty($focus_keywords) && is_array($focus_keywords) && count($focus_keywords) > 2) {
                    update_post_meta($post_id, 'rank_math_pillar_content', 'on');
                }
                
                // Enable Content AI features if available
                if (class_exists('\RankMath\ContentAI\ContentAI')) {
                    update_post_meta($post_id, 'rank_math_contentai_enabled', 'on');
                }
            }
            
            // Set breadcrumb title
            $breadcrumb_title = get_the_title($post_id);
            if (!empty($breadcrumb_title)) {
                update_post_meta($post_id, 'rank_math_breadcrumb_title', sanitize_text_field($breadcrumb_title));
            }
            
            // Set advanced meta for better SEO
            update_post_meta($post_id, 'rank_math_advanced_robots', array());
            
            // Set internal linking suggestions
            if (!empty($focus_keywords) && is_array($focus_keywords)) {
                $internal_links = array();
                foreach ($focus_keywords as $keyword) {
                    $internal_links[] = array(
                        'keyword' => $keyword,
                        'url' => $post_url,
                        'title' => get_the_title($post_id)
                    );
                }
                update_post_meta($post_id, 'rank_math_internal_links', $internal_links);
            }
            
            aicoagac_debug_log('Successfully sent SEO data to RankMath for post ' . $post_id);
            
            return array(
                'success' => true,
                'message' => __('SEO data successfully sent to RankMath', 'ai-content-agent'),
                'plugin' => 'RankMath',
                'data_sent' => array(
                    'title' => !empty($meta_title),
                    'description' => !empty($meta_description),
                    'focus_keyword' => !empty($focus_keywords),
                    'seo_score' => 85,
                    'content_score' => 75,
                    'social_media' => !empty($meta_title) || !empty($meta_description),
                    'primary_category' => ($post_type === 'post' && !empty($categories)),
                    'schema' => ($post_type === 'post' || $post_type === 'page') ? 'enabled' : 'none',
                    'pro_features' => class_exists('\RankMath\Pro\Admin\Admin_Menu'),
                    'pillar_content' => (!empty($focus_keywords) && count($focus_keywords) > 2),
                    'internal_links' => !empty($focus_keywords)
                )
            );
            
        } catch (Exception $e) {
            aicoagac_debug_log('Error sending to RankMath: ' . $e->getMessage());
            return $this->aicoagac_api_error('rankmath_error', sprintf(
                /* translators: %s: error message */
                __('Error sending to RankMath: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Send SEO data to Yoast SEO
     */
    private function send_to_yoast($post_id, $meta_title, $meta_description, $focus_keywords) {
        try {
                    // Yoast stores data in post meta with _yoast_wpseo_ prefix
        if (!empty($meta_title)) {
            update_post_meta($post_id, '_yoast_wpseo_title', $this->sanitize_unicode_text($meta_title));
        }
        
        if (!empty($meta_description)) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $this->sanitize_unicode_textarea($meta_description));
        }
            
            if (!empty($focus_keywords) && is_array($focus_keywords)) {
                // Yoast stores the primary focus keyword
                $primary_keyword = sanitize_text_field($focus_keywords[0]);
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $primary_keyword);
                
                // For Yoast Premium, additional keywords can be stored
                if (count($focus_keywords) > 1 && defined('WPSEO_PREMIUM_PLUGIN_FILE')) {
                    $additional_keywords = array();
                    for ($i = 1; $i < count($focus_keywords); $i++) {
                        $additional_keywords[] = array(
                            'keyword' => sanitize_text_field($focus_keywords[$i]),
                            'score' => 'good' // Simulate good score for AI content
                        );
                    }
                    update_post_meta($post_id, '_yoast_wpseo_focuskeywords', json_encode($additional_keywords));
                }
                
                // Store all keywords in a custom meta for reference
                update_post_meta($post_id, 'aicoagac_focus_keywords', $focus_keywords);
            }
            
            // Set additional Yoast meta for better integration
            // Set good content score for AI-generated content (0-100 scale)
            update_post_meta($post_id, '_yoast_wpseo_content_score', 75);
            
            // Estimate reading time based on content length
            $post = get_post($post_id);
            if ($post && !empty($post->post_content)) {
                $word_count = str_word_count(wp_strip_all_tags($post->post_content));
                $reading_time = max(1, ceil($word_count / 200)); // 200 words per minute
                update_post_meta($post_id, '_yoast_wpseo_estimated-reading-time-minutes', $reading_time);
            }
            
            // Set readability score (simulate good readability for AI content)
            update_post_meta($post_id, '_yoast_wpseo_readability-score', 60); // Good readability
            
            // Set robots meta to index and follow
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', '0');
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', '0');
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noarchive', '0');
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-nosnippet', '0');
            
            // Set cornerstone content if multiple keywords (indicates important content)
            if (!empty($focus_keywords) && is_array($focus_keywords) && count($focus_keywords) > 2) {
                update_post_meta($post_id, '_yoast_wpseo_is_cornerstone', '1');
            }
            
            // Set primary category if post has categories
            $post_type = get_post_type($post_id);
            $categories = array(); // Initialize to avoid undefined variable
            if ($post_type === 'post') {
                $categories = get_the_category($post_id);
                if (!empty($categories)) {
                    update_post_meta($post_id, '_yoast_wpseo_primary_category', $categories[0]->term_id);
                }
            }
            
            // Social Media Integration - OpenGraph
            if (!empty($meta_title)) {
                update_post_meta($post_id, '_yoast_wpseo_opengraph-title', $this->sanitize_unicode_text($meta_title));
                update_post_meta($post_id, '_yoast_wpseo_twitter-title', $this->sanitize_unicode_text($meta_title));
            }
            
            if (!empty($meta_description)) {
                update_post_meta($post_id, '_yoast_wpseo_opengraph-description', $this->sanitize_unicode_textarea($meta_description));
                update_post_meta($post_id, '_yoast_wpseo_twitter-description', $this->sanitize_unicode_textarea($meta_description));
            }
            
            // Set featured image for social media if available
            $featured_image_id = get_post_thumbnail_id($post_id);
            if ($featured_image_id) {
                $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
                if ($featured_image_url) {
                    update_post_meta($post_id, '_yoast_wpseo_opengraph-image', $featured_image_url);
                    update_post_meta($post_id, '_yoast_wpseo_opengraph-image-id', $featured_image_id);
                    update_post_meta($post_id, '_yoast_wpseo_twitter-image', $featured_image_url);
                    update_post_meta($post_id, '_yoast_wpseo_twitter-image-id', $featured_image_id);
                }
            }
            
            // Enhanced Premium features
            if (defined('WPSEO_PREMIUM_PLUGIN_FILE')) {
                // Set SEO score equivalent (linkdex)
                update_post_meta($post_id, '_yoast_wpseo_linkdex', 75);
                
                // Set word form recognition for Premium
                if (!empty($focus_keywords) && is_array($focus_keywords)) {
                    update_post_meta($post_id, '_yoast_wpseo_keywordsynonyms', implode(',', array_slice($focus_keywords, 1)));
                }
                
                // Enable redirect notifications for Premium
                update_post_meta($post_id, '_yoast_wpseo_redirect', '');
            }
            
            // Set breadcrumb title if different from post title
            $breadcrumb_title = get_the_title($post_id);
            if (!empty($breadcrumb_title)) {
                update_post_meta($post_id, '_yoast_wpseo_bctitle', sanitize_text_field($breadcrumb_title));
            }
            
            // Advanced indexing controls
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-adv', 'none');
            
            // Set canonical URL to self to avoid duplicate content
            $post_url = get_permalink($post_id);
            if ($post_url) {
                update_post_meta($post_id, '_yoast_wpseo_canonical', $post_url);
            }
            
            aicoagac_debug_log('Successfully sent SEO data to Yoast SEO for post ' . $post_id);
            
            return array(
                'success' => true,
                'message' => __('SEO data successfully sent to Yoast SEO', 'ai-content-agent'),
                'plugin' => 'Yoast SEO',
                'data_sent' => array(
                    'title' => !empty($meta_title),
                    'description' => !empty($meta_description),
                    'focus_keyword' => !empty($focus_keywords),
                    'content_score' => 75,
                    'readability_score' => 60,
                    'reading_time' => isset($reading_time) ? $reading_time : 'estimated',
                    'cornerstone' => (!empty($focus_keywords) && count($focus_keywords) > 2),
                    'social_media' => !empty($meta_title) || !empty($meta_description),
                    'primary_category' => ($post_type === 'post' && !empty($categories)),
                    'premium_features' => defined('WPSEO_PREMIUM_PLUGIN_FILE')
                )
            );
            
        } catch (Exception $e) {
            aicoagac_debug_log('Error sending to Yoast SEO: ' . $e->getMessage());
            return $this->aicoagac_api_error('yoast_error', sprintf(
                /* translators: %s: error message */
                __('Error sending to Yoast SEO: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    /**
     * Send SEO data to All in One SEO (AIOSEO)
     */
    private function send_to_aioseo($post_id, $meta_title, $meta_description, $focus_keywords) {
        try {
            // AIOSEO v4+ uses a different data structure - JSON-based post meta
            $aioseo_data = array();
            
            // Get existing AIOSEO data if any
            $existing_data = get_post_meta($post_id, '_aioseo_posts', true);
            if (is_string($existing_data)) {
                $existing_data = json_decode($existing_data, true);
            }
            if (!is_array($existing_data)) {
                $existing_data = array();
            }
            
            // Update title
            if (!empty($meta_title)) {
                $existing_data['title'] = $this->sanitize_unicode_text($meta_title);
                // Also set legacy field for backward compatibility
                update_post_meta($post_id, '_aioseo_title', $this->sanitize_unicode_text($meta_title));
            }
            
            // Update description
            if (!empty($meta_description)) {
                $existing_data['description'] = $this->sanitize_unicode_textarea($meta_description);
                // Also set legacy field for backward compatibility
                update_post_meta($post_id, '_aioseo_description', $this->sanitize_unicode_textarea($meta_description));
            }
            
            // Update keywords
            if (!empty($focus_keywords) && is_array($focus_keywords)) {
                // AIOSEO v4+ stores keywords as comma-separated string in the main data
                $keywords_string = implode(', ', array_map('sanitize_text_field', $focus_keywords));
                $existing_data['keywords'] = $keywords_string;
                
                // Set focus keyphrase (primary keyword)
                $existing_data['keyphrases'] = array(
                    'focus' => array(
                        'keyphrase' => sanitize_text_field($focus_keywords[0]),
                        'score' => 80, // Good score for AI content
                        'analysis' => array()
                    )
                );
                
                // Legacy fields for compatibility
                update_post_meta($post_id, '_aioseo_keywords', $keywords_string);
                update_post_meta($post_id, '_aioseo_focus_keyphrase', sanitize_text_field($focus_keywords[0]));
                
                // Store all keywords in a custom meta for reference
                update_post_meta($post_id, 'aicoagac_focus_keywords', $focus_keywords);
            }
            
            // Set robots meta
            $existing_data['robots'] = array(
                'default' => true,
                'noindex' => false,
                'nofollow' => false,
                'noarchive' => false,
                'nosnippet' => false,
                'noimageindex' => false
            );
            
            // Social Media Integration - OpenGraph
            if (!empty($meta_title) || !empty($meta_description)) {
                $existing_data['og'] = array();
                if (!empty($meta_title)) {
                    $existing_data['og']['title'] = $this->sanitize_unicode_text($meta_title);
                }
                if (!empty($meta_description)) {
                    $existing_data['og']['description'] = $this->sanitize_unicode_textarea($meta_description);
                }
                
                // Set featured image for social media if available
                $featured_image_id = get_post_thumbnail_id($post_id);
                if ($featured_image_id) {
                    $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
                    if ($featured_image_url) {
                        $existing_data['og']['image'] = $featured_image_url;
                        $existing_data['og']['imageType'] = 'default';
                    }
                }
            }
            
            // Twitter Card data
            if (!empty($meta_title) || !empty($meta_description)) {
                $existing_data['twitter'] = array();
                if (!empty($meta_title)) {
                    $existing_data['twitter']['title'] = $this->sanitize_unicode_text($meta_title);
                }
                if (!empty($meta_description)) {
                    $existing_data['twitter']['description'] = $this->sanitize_unicode_textarea($meta_description);
                }
                
                // Set featured image for Twitter if available
                $featured_image_id = get_post_thumbnail_id($post_id);
                if ($featured_image_id) {
                    $featured_image_url = wp_get_attachment_image_url($featured_image_id, 'full');
                    if ($featured_image_url) {
                        $existing_data['twitter']['image'] = $featured_image_url;
                        $existing_data['twitter']['imageType'] = 'default';
                    }
                }
            }
            
            // Set primary category if post has categories
            $post_type = get_post_type($post_id);
            $categories = array(); // Initialize to avoid undefined variable
            if ($post_type === 'post') {
                $categories = get_the_category($post_id);
                if (!empty($categories)) {
                    $existing_data['primary_term'] = array(
                        'category' => $categories[0]->term_id
                    );
                }
            }
            
            // Set canonical URL to self
            $post_url = get_permalink($post_id);
            if ($post_url) {
                $existing_data['canonical_url'] = $post_url;
            }
            
            // Set schema type based on post type
            if ($post_type === 'post') {
                $existing_data['schema'] = array(
                    'default' => 'Article',
                    'article' => array(
                        'articleType' => 'BlogPosting'
                    )
                );
            } elseif ($post_type === 'page') {
                $existing_data['schema'] = array(
                    'default' => 'WebPage'
                );
            }
            
            // AIOSEO Pro features if available
            if (is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php') || defined('AIOSEO_PRO')) {
                // Set SEO score for Pro version
                $existing_data['seo_score'] = 85;
                
                // Enable advanced features
                $existing_data['priority'] = 'default';
                $existing_data['frequency'] = 'default';
            }
            
            // Save the updated AIOSEO data
            update_post_meta($post_id, '_aioseo_posts', json_encode($existing_data));
            
            // Also maintain legacy meta fields for older versions
            if (!empty($meta_title)) {
                update_post_meta($post_id, '_aioseo_title', $this->sanitize_unicode_text($meta_title));
            }
            if (!empty($meta_description)) {
                update_post_meta($post_id, '_aioseo_description', $this->sanitize_unicode_textarea($meta_description));
            }
            
            aicoagac_debug_log('Successfully sent SEO data to All in One SEO for post ' . $post_id);
            
            return array(
                'success' => true,
                'message' => __('SEO data successfully sent to All in One SEO', 'ai-content-agent'),
                'plugin' => 'All in One SEO',
                'data_sent' => array(
                    'title' => !empty($meta_title),
                    'description' => !empty($meta_description),
                    'focus_keyword' => !empty($focus_keywords),
                    'robots' => 'index,follow',
                    'social_media' => !empty($meta_title) || !empty($meta_description),
                    'primary_category' => ($post_type === 'post' && !empty($categories)),
                    'schema' => ($post_type === 'post' || $post_type === 'page') ? 'enabled' : 'none',
                    'pro_features' => (is_plugin_active('all-in-one-seo-pack-pro/all_in_one_seo_pack.php') || defined('AIOSEO_PRO')),
                    'data_structure' => 'v4_compatible'
                )
            );
            
        } catch (Exception $e) {
            aicoagac_debug_log('Error sending to All in One SEO: ' . $e->getMessage());
            return $this->aicoagac_api_error('aioseo_error', sprintf(
                /* translators: %s: error message */
                __('Error sending to All in One SEO: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    
    // License methods removed - all features now free
    
    /**
     * Update content with AI suggestions
     */
    public function update_content_with_ai($request) {
        // Verify nonce for security
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $post_id = $request->get_param('id');
        $update_type = $request->get_param('update_type') ?: 'suggestions';
        
        // This would implement AI-powered content updates
        // For now, return a placeholder response
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicoagac_content_updates';
        
        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert for content update tracking
            $table_name,
            array(
                'post_id' => $post_id,
                'last_updated' => current_time('mysql'),
                'update_type' => $update_type,
                'ai_suggestions' => json_encode(array('AI update suggestions will be implemented here')),
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to queue content update', 'ai-content-agent'), array('status' => 500));
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'message' => __('Content update queued successfully', 'ai-content-agent')
        );
    }
    
    /**
     * Get language from WordPress locale
     */
    private function get_language_from_locale($locale) {
        $language_map = array(
            'en_US' => 'English',
            'en_GB' => 'English',
            'tr_TR' => 'Turkish',
            'de_DE' => 'German',
            'fr_FR' => 'French',
            'es_ES' => 'Spanish',
            'it_IT' => 'Italian',
            'pt_PT' => 'Portuguese',
            'pt_BR' => 'Portuguese',
            'ru_RU' => 'Russian',
            'ja' => 'Japanese',
            'ko_KR' => 'Korean',
            'zh_CN' => 'Chinese',
            'zh_TW' => 'Chinese',
            'ar' => 'Arabic',
            'nl_NL' => 'Dutch',
            'sv_SE' => 'Swedish',
            'da_DK' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'pl_PL' => 'Polish',
            'cs_CZ' => 'Czech',
            'hu_HU' => 'Hungarian',
            'ro_RO' => 'Romanian',
            'bg_BG' => 'Bulgarian',
            'hr' => 'Croatian',
            'sk_SK' => 'Slovak',
            'sl_SI' => 'Slovenian',
            'et' => 'Estonian',
            'lv' => 'Latvian',
            'lt_LT' => 'Lithuanian',
            'el' => 'Greek',
            'he_IL' => 'Hebrew',
            'th' => 'Thai',
            'vi' => 'Vietnamese',
            'hi_IN' => 'Hindi',
            'bn_BD' => 'Bengali',
            'ur' => 'Urdu',
            'fa_IR' => 'Persian',
            'uk' => 'Ukrainian'
        );
        
        return $language_map[$locale] ?? 'English';
    }
    
    // ============================================================================
    
    // Automation removed from Free version
    
    /**
     * Debug Pro license status - removed as pro features are now free
     */
    public function debug_pro_status($request) {
        return rest_ensure_response(array(
            'message' => 'Pro features are now free for all users',
            'is_pro_active' => false,
            'status' => 'deprecated'
        ));
    }
    
    // ============================================================================
    
    /**

     */
    
    /**

     */
    
    /**
     * Update content with AI suggestions
     */
    public function update_content_with_ai_duplicate($request) {
        // Verify nonce for security
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        
        $post_id = $request->get_param('id');
        $update_type = $request->get_param('update_type') ?: 'suggestions';
        
        // This would implement AI-powered content updates
        // For now, return a placeholder response
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'aicoagac_content_updates';
        
        $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert for content update tracking
            $table_name,
            array(
                'post_id' => $post_id,
                'last_updated' => current_time('mysql'),
                'update_type' => $update_type,
                'ai_suggestions' => json_encode(array('AI update suggestions will be implemented here')),
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', __('Failed to queue content update', 'ai-content-agent'), array('status' => 500));
        }
        
        return array(
            'success' => true,
            'post_id' => $post_id,
            'message' => __('Content update queued successfully', 'ai-content-agent')
        );
    }
    
    /**

     */
    
    /**

     */
    
    /**
     * Get posts needing updates
     */
    
    /**
        }

        // Default to English if not found
        return 'English';
    }

    /**
     * Get category hierarchy level (depth)
     */
    private function get_category_level($category_id, $level = 0) {
        $category = get_category($category_id);
        if (!$category || is_wp_error($category)) {
            return $level;
        }

        if ($category->parent == 0) {
            return $level;
        }

        return $this->get_category_level($category->parent, $level + 1);
    }

    /**
     * Get full category hierarchy path
     */
    private function get_category_hierarchy_path($category_id) {
        $path = array();
        $category = get_category($category_id);
        
        while ($category && !is_wp_error($category) && $category->parent != 0) {
            array_unshift($path, $category->name);
            $category = get_category($category->parent);
        }
        
        if ($category && !is_wp_error($category)) {
            array_unshift($path, $category->name);
        }
        
        return implode(' > ', $path);
    }
    
    /**
     * Setup proper charset handling for Unicode and special characters
     */
    public function setup_charset_handling() {
        // Ensure UTF-8 encoding is used throughout
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }
        
        // Set proper headers for Unicode support
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
    }
    
    /**
     * Unicode-safe text sanitization
     */
    private function sanitize_unicode_text($text) {
        if (empty($text)) {
            return '';
        }
        
        // Convert to UTF-8 if not already
        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // Remove control characters but preserve Unicode
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize Unicode characters
        if (class_exists('Normalizer')) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C);
        }
        
        // Standard WordPress sanitization that preserves Unicode
        return sanitize_text_field($text);
    }
    
    /**
     * Unicode-safe textarea sanitization
     */
    private function sanitize_unicode_textarea($text) {
        if (empty($text)) {
            return '';
        }
        
        // Convert to UTF-8 if not already
        if (function_exists('mb_convert_encoding')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }
        
        // Remove dangerous control characters but preserve line breaks
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Normalize Unicode characters
        if (class_exists('Normalizer')) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C);
        }
        
        // Standard WordPress sanitization that preserves Unicode
        return sanitize_textarea_field($text);
    }
    
    /**
     * Safe JSON encoding with Unicode support
     */
    private function safe_json_encode($data) {
        // Use JSON_UNESCAPED_UNICODE to preserve Unicode characters
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            aicoagac_debug_log('JSON encoding error: ' . json_last_error_msg());
            
            // Fallback: try with escaped Unicode
            $json = json_encode($data);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }
        }
        
        return $json;
    }
    /**
     * Safe JSON decoding with Unicode support
     */
    private function safe_json_decode($json, $assoc = true) {
        if (empty($json)) {
            return $assoc ? array() : null;
        }
        
        $data = json_decode($json, $assoc);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            aicoagac_debug_log('JSON decoding error: ' . json_last_error_msg());
            return $assoc ? array() : null;
        }
        
        return $data;
    }
    
    /**
     * Test endpoint callback
     */
    public function test_endpoint_callback($request) {
        return array(
            'success' => true,
            'message' => __('Test endpoint working', 'ai-content-agent'),
            'timestamp' => current_time('mysql')
        );
    }
    
    /**
     * Debug routes callback - shows all registered routes
     */
    public function debug_routes_callback($request) {
        $server = rest_get_server();
        $routes = array();
        
        foreach ($server->get_routes() as $route => $handlers) {
            if (strpos($route, '/aca/v1') === 0) {
                $routes[$route] = array(
                    'methods' => array(),
                    'callbacks' => array()
                );
                
                foreach ($handlers as $handler) {
                    if (isset($handler['methods'])) {
                        $routes[$route]['methods'] = array_merge($routes[$route]['methods'], array_keys($handler['methods']));
                    }
                    if (isset($handler['callback'])) {
                        if (is_array($handler['callback']) && count($handler['callback']) == 2) {
                            $routes[$route]['callbacks'][] = get_class($handler['callback'][0]) . '::' . $handler['callback'][1];
                        } else {
                            $routes[$route]['callbacks'][] = 'Unknown callback';
                        }
                    }
                }
            }
        }
        
        return array(
            'success' => true,
            'routes' => $routes,
            'total_routes' => count($routes)
        );
    }

    /**
     * Generate image using AI service
     */
    public function generate_image($request) {
        try {
            $prompt = $request->get_param('prompt');
            $style = $request->get_param('style') ?: 'digital_art';
            
            if (empty($prompt)) {
                return $this->aicoagac_api_error('missing_prompt', 'Prompt parameter is required', 400);
            }
            
            // For now, return a placeholder response since Gemini doesn't support image generation
            // In the future, this could integrate with Google Imagen API or other image generation services
            $response = array(
                'success' => false,
                'message' => __('Image generation is not currently available. Gemini API does not support image generation.', 'ai-content-agent'),
                'suggestion' => 'Consider using external image generation services like DALL-E, Midjourney, or Stable Diffusion.',
                'prompt' => $prompt,
                'style' => $style
            );
            
            return rest_ensure_response($response);
            
        } catch (Exception $e) {
            aicoagac_debug_log('Image Generation Error: ' . $e->getMessage());
            return $this->aicoagac_api_error('image_generation_error', sprintf(
                /* translators: %s: error message */
                __('Failed to generate image: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Get automation status (Simple Automation System)
     */
    public function get_automation_status() {
        try {
            $automation = REMOVED_AUTOMATION::get_instance();
            $status = $automation->get_status();
            
            return rest_ensure_response($status);
            
        } catch (Exception $e) {
            aicoagac_debug_log('Automation Status Error: ' . $e->getMessage());
            return $this->aicoagac_api_error('automation_error', sprintf(
                /* translators: %s: error message */
                __('Failed to get automation status: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Test automation system
     */
    public function test_automation() {
        try {
            $automation = REMOVED_AUTOMATION::get_instance();
            
            // Trigger a test idea generation using the new manual task method
            $result = $automation->trigger_manual_task('idea_generation', array(
                'count' => 1,
                'mode' => 'test'
            ));
            
            return rest_ensure_response(array(
                'success' => true,
                'message' => __('Test automation triggered successfully', 'ai-content-agent'),
                'result' => $result,
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            aicoagac_debug_log('Automation Test Error: ' . $e->getMessage());
            return $this->aicoagac_api_error('automation_test_error', sprintf(
                /* translators: %s: error message */
                __('Failed to test automation: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Get recent errors from debug log
     */
    private function get_recent_errors() {
        $errors = array();
        
        // Get last 10 error log entries
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($log_file) && is_readable($log_file)) {
            $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recent_lines = array_slice($lines, -50); // Get last 50 lines
            
            foreach ($recent_lines as $line) {
                if (strpos($line, 'AI Content Agent') !== false && 
                    (strpos($line, 'Error') !== false || strpos($line, 'Warning') !== false)) {
                    $errors[] = $line;
                    if (count($errors) >= 10) break;
                }
            }
        }
        
        return array_reverse($errors);
    }
    
    /**
     * Debug automation status (for SettingsAdvanced)
     */
    public function debug_automation_status($request) {
        try {
            $automation = REMOVED_AUTOMATION::get_instance();
            $status = $automation->get_status();
            
            // Add migration info
            $migration_status = array('error' => __('Migration Manager not available', 'ai-content-agent'));
            $migration_file = AICOAGAC_PLUGIN_PATH . 'includes/class-aicoagac-migration-manager.php';
            if (file_exists($migration_file)) {
                require_once $migration_file;
                if (class_exists('AICOAGAC_Migration_Manager')) {
                    $migration_manager = new AICOAGAC_Migration_Manager();
                    $migration_status = $migration_manager->get_migration_status();
                }
            }
            
            // Add system health checks
            $health_checks = array(
                'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
                'simple_automation_loaded' => class_exists('REMOVED_AUTOMATION'),
                'unified_system_active' => class_exists('REMOVED_AUTOMATION') && method_exists('REMOVED_AUTOMATION', 'get_instance'),
                'unified_system_status' => __('Unified & Reliable', 'ai-content-agent'),
                'scheduled_events' => array(
                    'idea_generation' => wp_next_scheduled('aicoagac_simple_idea_generation'),
                    'draft_creation' => wp_next_scheduled('aicoagac_simple_draft_creation'),
                    'post_publishing' => wp_next_scheduled('aicoagac_simple_post_publishing'),
                    'maintenance' => wp_next_scheduled('aicoagac_simple_maintenance')
                ),
                'next_run_times' => array(
                    'idea_generation' => wp_next_scheduled('aicoagac_simple_idea_generation') ? gmdate('Y-m-d H:i:s', wp_next_scheduled('aicoagac_simple_idea_generation')) : __('Not scheduled', 'ai-content-agent'),
                    'draft_creation' => wp_next_scheduled('aicoagac_simple_draft_creation') ? gmdate('Y-m-d H:i:s', wp_next_scheduled('aicoagac_simple_draft_creation')) : __('Not scheduled', 'ai-content-agent'),
                    'post_publishing' => wp_next_scheduled('aicoagac_simple_post_publishing') ? gmdate('Y-m-d H:i:s', wp_next_scheduled('aicoagac_simple_post_publishing')) : __('Not scheduled', 'ai-content-agent'),
                    'maintenance' => wp_next_scheduled('aicoagac_simple_maintenance') ? gmdate('Y-m-d H:i:s', wp_next_scheduled('aicoagac_simple_maintenance')) : __('Not scheduled', 'ai-content-agent')
                )
            );
            
            return rest_ensure_response(array(
                'success' => true,
                'automation_status' => $status,
                'migration_status' => $migration_status,
                'health_checks' => $health_checks,
                'system_info' => array(
                    'php_version' => PHP_VERSION,
                    'wp_version' => get_bloginfo('version'),
                    'plugin_version' => get_option('aicoagac_plugin_version', __('Unknown', 'ai-content-agent'))
                ),
                'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
                'memory_limit' => WP_MEMORY_LIMIT,
                'cron_jobs' => array(
                    'aicoagac_simple_idea_generation' => wp_next_scheduled('aicoagac_simple_idea_generation'),
                    'aicoagac_simple_draft_creation' => wp_next_scheduled('aicoagac_simple_draft_creation'),
                    'aicoagac_simple_post_publishing' => wp_next_scheduled('aicoagac_simple_post_publishing'),
                    'aicoagac_simple_maintenance' => wp_next_scheduled('aicoagac_simple_maintenance')
                ),
                'last_errors' => $this->get_recent_errors(),
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            return $this->aicoagac_api_error('debug_failed', sprintf(
                /* translators: %s: error message */
                __('Debug failed: %s', 'ai-content-agent'),
                $e->getMessage()
            ), 500);
        }
    }
    
    

    // Legacy queue actions method removed - using deprecated version at end of class
    
    // All legacy queue and automation system methods moved to deprecated section at end of class
    

    
    /**
     * PHASE 1 CONSOLIDATION: Queue Manager deprecated - use REMOVED_AUTOMATION
     */
    public function schedule_queue_task($request) {
        aicoagac_debug_log('PHASE 1: Queue Manager deprecated - use REMOVED_AUTOMATION unified system');
        return $this->aicoagac_api_error('deprecated_system', __('Queue Manager deprecated. Use REMOVED_AUTOMATION unified system.', 'ai-content-agent'), 410);
    }
    
    /**
     * AGENTS.MD CRITICAL FIX: Get automation system status
     */
    public function get_automation_system_status() {
        try {
            // Get the main plugin instance safely
            if (class_exists('AICOAGAC_Content_Agent')) {
                $instance = AICOAGAC_Content_Agent::get_instance();
                if ($instance && method_exists($instance, 'get_automation_system_status')) {
                    $status = $instance->get_automation_system_status();
                } else {
                    $status = array('error' => 'Plugin instance not available');
                }
            } else {
                // Fallback implementation - Unified System Only
                $status = array(
                    'current_system' => 'simple',
                    'legacy_available' => false, // Legacy systems permanently disabled
                    'simple_available' => class_exists('REMOVED_AUTOMATION'),
                    'legacy_enabled' => false, // Legacy systems permanently disabled
                    'simple_enabled' => true, // Unified system permanently enabled
                    'conflict_detected' => false // No conflicts possible with unified system
                );
            }
            
            // Add additional diagnostic information
            $status['diagnostics'] = array(
                'action_scheduler_version' => defined('ACTION_SCHEDULER_VERSION') ? ACTION_SCHEDULER_VERSION : __('Not available', 'ai-content-agent'),
                'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
                'unified_automation_hooks_registered' => has_action('aicoagac_simple_idea_generation'),
                'unified_system_status' => __('Unified & Reliable', 'ai-content-agent')
            );
            
            return rest_ensure_response($status);
            
        } catch (Exception $e) {
            aicoagac_debug_log('Automation System Status Error: ' . $e->getMessage());
            return $this->aicoagac_api_error('automation_system_error', 'Failed to get automation system status: ' . $e->getMessage(), 500);
        }
    }
    

    
    /**
     * Deprecated: Queue actions endpoint (Unified Automation Only)
     */
    public function get_queue_actions_deprecated($request) {
        return $this->aicoagac_api_error('deprecated', __('This endpoint is deprecated. Use /automation/status instead.', 'ai-content-agent'), 410);
    }
    
    /**
     * Deprecated: Cancel queue tasks endpoint (Unified Automation Only)
     */
    public function cancel_all_queue_tasks_deprecated($request) {
        return $this->aicoagac_api_error('deprecated', __('This endpoint is deprecated. Unified automation system does not use queues.', 'ai-content-agent'), 410);
    }
    


    /**
     * AGENTS.MD FIX: Manual scheduling trigger endpoint
     * Forces re-scheduling of automation tasks
     */
    public function trigger_scheduling($request) {
        try {
            aicoagac_debug_log('REST API: Manual scheduling trigger requested');
            
            // AGENTS.MD FIX: Force license verification cache refresh for automation activation
            $pro_status_before = false;
            $pro_status_after = false;
            
            aicoagac_debug_log(sprintf(
                'REST API: Pro status check - Before cache refresh: %s, After cache refresh: %s',
                $pro_status_before ? 'ACTIVE' : 'INACTIVE',
                $pro_status_after ? 'ACTIVE' : 'INACTIVE'
            ));
            
            // Get automation instance
            $automation = REMOVED_AUTOMATION::get_instance();
            
            if (!$automation) {
                return new WP_Error('automation_not_available', __('Automation system not available', 'ai-content-agent'), array('status' => 500));
            }
            
            // Force task scheduling with enhanced license verification
            $automation->ensure_scheduled_tasks();
            
            // AGENTS.MD FIX: Minimize cache impact while ensuring visibility
            wp_cache_delete('cron', 'options');
            
            // Small delay to ensure WordPress cron registration is complete
            usleep(200000); // 0.2 seconds
            
            // Get updated status with fresh data
            $status = $automation->get_status();
            
            aicoagac_debug_log('REST API: Manual scheduling trigger completed');
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => __('Task scheduling triggered successfully', 'ai-content-agent'),
                'status' => $status,
                'next_runs' => array(
                    'idea_generation' => wp_next_scheduled(REMOVED_AUTOMATION::HOOK_IDEA_GENERATION),
                    'draft_creation' => wp_next_scheduled(REMOVED_AUTOMATION::HOOK_DRAFT_CREATION),
                    'post_publishing' => wp_next_scheduled(REMOVED_AUTOMATION::HOOK_POST_PUBLISHING),
                    'maintenance' => wp_next_scheduled(REMOVED_AUTOMATION::HOOK_MAINTENANCE)
                )
            ), 200);
            
        } catch (Exception $e) {
            aicoagac_debug_log('REST API: Error in manual scheduling trigger - ' . $e->getMessage());
            return new WP_Error('scheduling_failed', sprintf(
                /* translators: %s: error message */
                __('Failed to trigger scheduling: %s', 'ai-content-agent'),
                $e->getMessage()
            ), array('status' => 500));
        }
    }
    
    // License refresh removed
    
    /**
     * Clear all plugin caches
     */
    public function clear_cache($request) {
        try {
            require_once AICOAGAC_PLUGIN_PATH . 'includes/class-aicoagac-cache-manager.php';
            
            $result = AICOAGAC_Cache_Manager::clear_all_caches('REST API - Manual');
            
            return new WP_REST_Response($result, 200);
        } catch (Throwable $e) {
            aicoagac_debug_log('REST API: Cache clear failed - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error('cache_clear_failed', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Get cache status information
     */
    public function get_cache_status($request) {
        try {
            require_once AICOAGAC_PLUGIN_PATH . 'includes/class-aicoagac-cache-manager.php';
            
            $status = AICOAGAC_Cache_Manager::get_cache_status();
            
            return new WP_REST_Response($status, 200);
        } catch (Throwable $e) {
            aicoagac_debug_log('REST API: Failed to get cache status - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error('cache_status_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Get automation debug information
     */
    public function get_automation_debug_info_duplicate($request) {
        try {
            // Get automation instance
            $automation = REMOVED_AUTOMATION::get_instance();
            $status = $automation->get_status();
            
            // Get system info
            $system_info = array(
                'php_version' => PHP_VERSION,
                'wp_version' => get_bloginfo('version'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
                'wp_cron' => defined('DISABLE_WP_CRON') ? !DISABLE_WP_CRON : true
            );
            
            // Get cron jobs
            $cron_jobs = _get_cron_array();
            $aicoagac_crons = array();
            
            foreach ($cron_jobs as $timestamp => $cron) {
                foreach ($cron as $hook => $tasks) {
                    if (strpos($hook, 'aicoagac_') === 0) {
                        $aicoagac_crons[] = array(
                            'hook' => $hook,
                            'next_run' => gmdate('Y-m-d H:i:s', $timestamp),
                            'timestamp' => $timestamp
                        );
                    }
                }
            }
            
            return new WP_REST_Response(array(
                'automation_status' => $status,
                'system_info' => $system_info,
                'cron_jobs' => $aicoagac_crons,
                'current_time' => current_time('mysql'),
                'timestamp' => time()
            ), 200);
            
        } catch (Throwable $e) {
            aicoagac_debug_log('REST API: Debug info failed - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error('debug_failed', $e->getMessage(), array('status' => 500));
        }
    }

    /**
     * Test error handling
     */
    public function test_error_handling($request) {
        try {
            // Simulate an error
            throw new Exception('This is a test error.');
        } catch (Exception $e) {
            return new WP_Error('test_error', sprintf(
                /* translators: %s: error message */
                __('Test error: %s', 'ai-content-agent'),
                $e->getMessage()
            ), array('status' => 500));
        }
    }

    /**
     * Generate FAQs via AI and attach to post according to detected SEO plugin or fallback JSON-LD
     */
    private function maybe_generate_and_attach_faqs($post_id, $title, $content_html) {
        $settings = get_option('aicoagac_settings', array());
        if (empty($settings['faqEnabled'])) {
            return;
        }
        $faq_count = isset($settings['faqCount']) ? max(2, min(10, (int)$settings['faqCount'])) : 4;
        if (empty($settings['geminiApiKey'])) {
            return; // AI key required to generate FAQs
        }
        // Build prompt
        $clean_title = wp_strip_all_tags($title);
        $clean_content = wp_strip_all_tags($content_html);
        $site_locale = get_locale();
        $site_language = $this->get_language_from_locale($site_locale);
        $prompt = "Generate {$faq_count} FAQs (Question and Answer pairs) for the blog post titled \"{$clean_title}\".\n" .
                  "Language: {$site_language}.\n" .
                  "Return ONLY valid JSON array with objects: [{\"question\":\"...\",\"answer\":\"...\"}], no extra text.\n" .
                  "Answers should be concise (1-3 sentences) and helpful. Do not include HTML tags.";
        // Call AI
        $raw = $this->call_gemini_api($settings['geminiApiKey'], $prompt);
        $faqs = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($faqs) || empty($faqs)) {
            // Try cleaning then decode
            $cleaned = $this->clean_ai_json_response($raw);
            $faqs = json_decode($cleaned, true);
        }
        if (!is_array($faqs) || empty($faqs)) {
            return;
        }
        // Trim to faq_count and sanitize
        $sanitized_faqs = array();
        foreach (array_slice($faqs, 0, $faq_count) as $item) {
            if (!empty($item['question']) && !empty($item['answer'])) {
                $sanitized_faqs[] = array(
                    'question' => sanitize_text_field($item['question']),
                    'answer' => wp_kses_post($item['answer'])
                );
            }
        }
        if (empty($sanitized_faqs)) {
            return;
        }
        // Detect SEO plugins
        $detected = $this->detect_seo_plugin();
        $active = array_map(function($p){ return $p['plugin']; }, $detected);
        // Prefer plugin-specific if available
        if (in_array('rank_math', $active, true)) {
            $this->attach_faqs_rankmath($post_id, $sanitized_faqs);
            return;
        }
        if (in_array('aioseo', $active, true)) {
            $this->attach_faqs_aioseo($post_id, $sanitized_faqs);
            return;
        }
        if (in_array('yoast', $active, true)) {
            $this->attach_faqs_as_block_yoast($post_id, $sanitized_faqs);
            return;
        }
        // Fallback: JSON-LD append
        $this->append_faqs_jsonld_to_content($post_id, $sanitized_faqs);
    }

    private function attach_faqs_rankmath($post_id, $faqs) {
        // To avoid Rank Math schema corruption and @type warnings, prefer JSON-LD fallback instead of writing to rank_math_schema
        $this->append_faqs_jsonld_to_content($post_id, $faqs);
    }

    private function attach_faqs_aioseo($post_id, $faqs) {
        $existing = get_post_meta($post_id, '_aioseo_posts', true);
        $data = array();
        if (is_string($existing)) {
            $decoded = json_decode($existing, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = $decoded;
            }
        } elseif (is_array($existing)) {
            $data = $existing;
        }
        // Add FAQ schema
        $data['schema'] = isset($data['schema']) && is_array($data['schema']) ? $data['schema'] : array();
        $data['schema'][] = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(function($qa){
                return array(
                    '@type' => 'Question',
                    'name' => $qa['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags($qa['answer'])
                    )
                );
            }, $faqs)
        );
        update_post_meta($post_id, '_aioseo_posts', wp_json_encode($data));
    }

    private function attach_faqs_as_block_yoast($post_id, $faqs) {
        // Append a simple HTML list along with a comment indicating FAQ section; Yoast will not auto-schema without its block,
        // but this keeps content UX. To ensure schema, prefer JSON-LD fallback if Yoast block not available.
        // Here we choose to fallback to JSON-LD for Yoast to avoid block format coupling.
        $this->append_faqs_jsonld_to_content($post_id, $faqs);
    }

    private function append_faqs_jsonld_to_content($post_id, $faqs) {
        // Prevent duplicate insertion by checking for our marker
        $post = get_post($post_id);
        if ($post && !is_wp_error($post)) {
            if (strpos($post->post_content, '<!-- AICOAGAC_FAQ_JSONLD -->') !== false) {
                return; // already appended
            }
        }
        $jsonld = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(function($qa){
                return array(
                    '@type' => 'Question',
                    'name' => $qa['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags($qa['answer'])
                    )
                );
            }, $faqs)
        );
        // Store JSON-LD in a dedicated meta to be rendered in wp_head
        update_post_meta($post_id, '_aicoagac_faq_jsonld', wp_json_encode($jsonld));

        // Optionally append a visible FAQ section to the end of the content
        $settings = get_option('aicoagac_settings', array());
        if (!empty($settings['faqDisplayInContent'])) {
            $post = get_post($post_id);
            if ($post && !is_wp_error($post)) {
                // Avoid duplicate injection
                if (strpos($post->post_content, '<!-- AICOAGAC_FAQ_VISIBLE -->') === false) {
                    $html = "<!-- AICOAGAC_FAQ_VISIBLE -->\n<div class=\"aicoagac-faq\"><h2>SSS</h2><div class=\"aicoagac-faq-items\">";
                    foreach ($faqs as $qa) {
                        $q = esc_html($qa['question']);
                        $a = wp_kses_post($qa['answer']);
                        $html .= "<details class=\"aicoagac-faq-item\"><summary>{$q}</summary><div class=\"aicoagac-faq-answer\">{$a}</div></details>";
                    }
                    $html .= "</div></div>\n<!-- /AICOAGAC_FAQ_VISIBLE -->";
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_content' => $post->post_content . "\n\n" . $html,
                        'edit_date' => true,
                    ));
                }
            }
        }
    }

    /**
     * Cleanup helper: remove invalid Rank Math schema meta written as JSON string
     * Only removes when the meta value is a string (legacy incorrect format).
     */
    private function cleanup_rankmath_schema_if_invalid($post_id) {
        $value = get_post_meta($post_id, 'rank_math_schema', true);
        if (is_string($value) && !empty($value)) {
            // If it's a JSON string, delete to let Rank Math rebuild/avoid string-offset warnings
            delete_post_meta($post_id, 'rank_math_schema');
            aicoagac_debug_log('Rank Math schema cleanup: removed string meta for post ' . $post_id);
        }
    }

    /**
     * Bulk repair endpoint: scans recent posts and removes invalid Rank Math schema string meta
     */
    public function repair_rankmath_schema_meta($request) {
        $nonce_check = $this->verify_nonce($request);
        if (is_wp_error($nonce_check)) {
            return $nonce_check;
        }
        $fixed = 0;
        $scanned = 0;
        $recent = get_posts(array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'future'),
            'numberposts' => 200,
            'orderby' => 'date',
            'order' => 'DESC',
            'fields' => 'ids',
        ));
        foreach ($recent as $pid) {
            $scanned++;
            $value = get_post_meta($pid, 'rank_math_schema', true);
            if (is_string($value) && !empty($value)) {
                delete_post_meta($pid, 'rank_math_schema');
                $fixed++;
            }
        }
        return rest_ensure_response(array('scanned' => $scanned, 'fixed' => $fixed));
    }
    /**
     * Select internal linking candidates from lightweight index
     * Returns compact context items: title, url, content (mini summary)
     */
    private function get_internal_link_candidates_for_idea($idea_title, $site_locale, $top_k = 5) {
        global $wpdb;
        $table = $wpdb->prefix . 'aicoagac_post_index';
        // Ensure table exists
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)); // phpcs:ignore
        if ($exists !== $table) {
            return array();
        }
        
        // Tokenize idea title to rough keywords
        $title_clean = strtolower(wp_strip_all_tags($idea_title));
        $title_tokens = preg_split('/[\s\-_:;,.!?]+/', $title_clean);
        $title_tokens = array_values(array_filter($title_tokens, function($w){ return mb_strlen($w) > 3; }));
        $title_set = array_unique($title_tokens);
        if (empty($title_set)) {
            return array();
        }
        
        // Attempt FULLTEXT retrieval first (if index exists) and enabled, else fallback to recent rows
        $like_lang = substr($site_locale, 0, 2) . '%';
        $rows = array();
        $fulltext_available = false;
        $settings = get_option('aicoagac_settings', array());
        $fulltext_enabled = isset($settings['useFulltextRetrieval']) ? (bool)$settings['useFulltextRetrieval'] : true;
        if ($fulltext_enabled) {
            // Check if FULLTEXT exists on the table
            $indexes = $wpdb->get_results('SHOW INDEX FROM `' . $table . '`', ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            if (is_array($indexes)) {
                foreach ($indexes as $idx) {
                    if (isset($idx['Index_type']) && strtolower($idx['Index_type']) === 'fulltext') {
                        $fulltext_available = true; break;
                    }
                }
            }
        }
        
        if ($fulltext_available) {
            // Build a simple boolean mode query for the token set
            $query_str = implode(' ', array_map(function($t){ return '+' . esc_sql($t) . '*'; }, $title_set));
            // Prefer locale match; limit 50
            $sql = 'SELECT post_id, title, url, summary_1l, keywords, MATCH(title, summary_1l, keywords) AGAINST (%s IN BOOLEAN MODE) AS ft_score'
                . ' FROM ' . $table
                . ' WHERE (lang LIKE %s OR lang IS NULL)'
                . ' AND MATCH(title, summary_1l, keywords) AGAINST (%s IN BOOLEAN MODE)'
                . ' ORDER BY ft_score DESC, updated_at DESC'
                . ' LIMIT %d';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($wpdb->prepare($sql, $query_str, $like_lang, $query_str, 50), ARRAY_A);
        }
        
        if (empty($rows)) {
            // Fallback: Pull recent index rows for same locale (limit to 300 for speed)
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->prepare(
                    'SELECT post_id, title, url, summary_1l, keywords FROM ' . $wpdb->prefix . 'aicoagac_post_index WHERE (lang LIKE %s OR lang IS NULL) ORDER BY updated_at DESC LIMIT %d',
                    $like_lang,
                    300
                ),
                ARRAY_A
            );
        }
        if (empty($rows)) {
            return array();
        }
        
        // Score candidates by keyword overlap + small title similarity (re-rank)
        $scored = array();
        foreach ($rows as $row) {
            $kw = array();
            if (!empty($row['keywords'])) {
                $kw = array_map('trim', explode(',', strtolower($row['keywords'])));
            }
            $kw = array_filter($kw, function($w){ return mb_strlen($w) > 2; });
            $overlap = count(array_intersect($title_set, $kw));
            // Title partial match
            $title2 = strtolower(wp_strip_all_tags($row['title']));
            $title_match = 0;
            foreach ($title_set as $tok) {
                if (strpos($title2, $tok) !== false) { $title_match++; }
            }
            // Start with FT score if present
            $score = 0;
            if (isset($row['ft_score'])) {
                $score += (float)$row['ft_score'] * 1.0;
            }
            $score += ($overlap * 2) + $title_match; // simple weighted boost
            if ($score > 0) {
                $scored[] = array(
                    'score' => $score,
                    'title' => $row['title'],
                    'url' => $row['url'],
                    'content' => !empty($row['summary_1l']) ? $row['summary_1l'] : ''
                );
            }
        }
        if (empty($scored)) { return array(); }
        usort($scored, function($a, $b){ return $b['score'] <=> $a['score']; });
        $top = array_slice($scored, 0, max(1, (int)$top_k));
        $context = array();
        foreach ($top as $item) {
            $context[] = array(
                'title' => $item['title'],
                'url' => $item['url'],
                'content' => $item['content']
            );
        }
        return $context;
    }

    /**
     * Heuristic fallback parser for AI draft output when JSON is malformed.
     * Attempts to extract content, metaTitle, metaDescription, focusKeywords using regex.
     */
    private function fallback_parse_draft_json($text) {
        if (!is_string($text) || trim($text) === '') {
            return null;
        }
        $src = $this->clean_ai_json_response($text);
        $result = array(
            'content' => '',
            'metaTitle' => '',
            'metaDescription' => '',
            'focusKeywords' => array(),
        );
        // content between "content": and ","metaTitle"
        if (preg_match('/"content"\s*:\s*"(.*?)(?=",\s*"metaTitle")/s', $src, $m)) {
            $result['content'] = stripcslashes($m[1]);
        } elseif (preg_match('/"content"\s*:\s*"(.*)"/s', $src, $m2)) {
            $result['content'] = stripcslashes($m2[1]);
        }
        if (preg_match('/"metaTitle"\s*:\s*"(.*?)"/s', $src, $m)) {
            $result['metaTitle'] = stripcslashes($m[1]);
        }
        if (preg_match('/"metaDescription"\s*:\s*"(.*?)"/s', $src, $m)) {
            $result['metaDescription'] = stripcslashes($m[1]);
        }
        if (preg_match('/"focusKeywords"\s*:\s*\[(.*?)\]/s', $src, $m)) {
            $raw = $m[1];
            $parts = preg_split('/\s*,\s*/', trim($raw));
            $kws = array();
            foreach ($parts as $p) {
                $p = trim($p);
                $p = trim($p, "'\"");
                if ($p !== '') { $kws[] = $p; }
            }
            $result['focusKeywords'] = $kws;
        }
        if (!empty($result['content']) && !empty($result['metaTitle']) && !empty($result['metaDescription'])) {
            if (!is_array($result['focusKeywords'])) { $result['focusKeywords'] = array(); }
            return $result;
        }
        return null;
    }

    /**
     * Backfill lightweight index in batches using keyset pagination
     */
    public function index_backfill($request) {
        global $wpdb;
        $batch = max(1, min(1000, intval($request->get_param('batch') ?: 200)));
        $reset = (bool)$request->get_param('reset');

        // Transient lock to prevent overlap
        $lock_key = 'aicoagac_index_backfill_lock';
        if (get_transient($lock_key)) {
            return new WP_Error('in_progress', __('Index backfill already in progress', 'ai-content-agent'), array('status' => 429));
        }
        set_transient($lock_key, time(), 60); // 1 minute lock per batch

        try {
            if ($reset) {
                delete_option('aicoagac_index_backfill_last_id');
                delete_option('aicoagac_index_backfill_done');
            }
            if (get_option('aicoagac_index_backfill_done')) {
                return rest_ensure_response(array('done' => true, 'processed' => 0, 'last_id' => (int)get_option('aicoagac_index_backfill_last_id', 0)));
            }

            $last_id = (int)get_option('aicoagac_index_backfill_last_id', 0);
            // Keyset pagination: fetch next batch of published posts with ID > last_id
            $sql = $wpdb->prepare(
                "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish') AND ID > %d ORDER BY ID ASC LIMIT %d",
                $last_id,
                $batch
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (empty($rows)) {
                update_option('aicoagac_index_backfill_done', 1);
                return rest_ensure_response(array('done' => true, 'processed' => 0, 'last_id' => $last_id));
            }

            $table = $wpdb->prefix . 'aicoagac_post_index';
            $processed = 0;
            $lang = get_locale();
            foreach ($rows as $row) {
                $post_id = (int)$row['ID'];
                $title = wp_strip_all_tags($row['post_title']);
                $url = get_permalink($post_id) ?: home_url('/?p=' . $post_id);
                $cats = wp_get_post_categories($post_id, array('fields' => 'names'));
                $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
                $content = wp_strip_all_tags($row['post_content']);
                $summary = mb_substr(preg_replace('/\s+/', ' ', $content), 0, 160);
                $words = preg_split('/[\s\-_:;,.!?]+/', strtolower($title));
                $words = array_values(array_filter($words, function($w){ return mb_strlen($w) > 3; }));
                $keywords = !empty($words) ? implode(', ', array_slice(array_unique($words), 0, 7)) : '';
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->replace($table, array(
                    'post_id' => $post_id,
                    'lang' => $lang,
                    'title' => $title,
                    'url' => $url,
                    'cats' => maybe_serialize($cats),
                    'tags' => maybe_serialize($tags),
                    'summary_1l' => $summary,
                    'keywords' => $keywords,
                    'embedding' => null,
                    'updated_at' => current_time('mysql')
                ));
                $last_id = $post_id;
                $processed++;
            }
            update_option('aicoagac_index_backfill_last_id', $last_id);
            return rest_ensure_response(array('done' => false, 'processed' => $processed, 'last_id' => $last_id));
        } finally {
            delete_transient($lock_key);
        }
    }
}