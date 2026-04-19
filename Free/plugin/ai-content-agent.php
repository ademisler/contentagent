<?php
/**
 * Plugin Name: AI Content Agent (ACA)
 * Plugin URI: https://wordpress.org/plugins/ai-content-agent/
 * Description: AI-powered content creation and management plugin that generates blog posts, ideas, and manages your content workflow automatically.
 * Version: 1.0.3
 * Author: Adem Isler
 * Author URI: https://ademisler.com/en
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-content-agent
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AICOAGAC_VERSION', '1.0.3');
define('AICOAGAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AICOAGAC_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Pro licensing has been removed from this version

/**
 * Debug logging helper - only logs when WP_DEBUG is enabled
 * 
 * @param string $message Log message
 * @return void
 */
function aicoagac_debug_log($message) {
    // Debug logging disabled in production
    // To enable logging, define WP_DEBUG_LOG in wp-config.php
    return;
}

// Include required files with safety checks
$required_files = array(
    'includes/class-aicoagac-activator.php',
    'includes/class-aicoagac-deactivator.php',
    'includes/class-aicoagac-rest-api.php',
    'includes/class-aicoagac-cache-manager.php'
    // Automation removed from Free version
);

foreach ($required_files as $file) {
    $file_path = AICOAGAC_PLUGIN_PATH . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // Critical file missing - silently skip to avoid breaking site
        // File: $file_path
    }
}

// Automation system removed from Free version

// i18n: WordPress.org handles loading translations automatically for plugins hosted on wp.org

// Activation and deactivation hooks - with safety checks
if (class_exists('AICOAGAC_Activator')) {
    register_activation_hook(__FILE__, array('AICOAGAC_Activator', 'activate'));
}
if (class_exists('AICOAGAC_Deactivator')) {
    register_deactivation_hook(__FILE__, array('AICOAGAC_Deactivator', 'deactivate'));
}

/**
 * Main plugin class
 */
class AICOAGAC_Content_Agent {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Debug: Track WordPress context
        $context = '';
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $context .= 'AJAX ';
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            $context .= 'CRON ';
        }
        if (function_exists('is_admin') && is_admin()) {
            $context .= 'ADMIN ';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context .= 'REST ';
        }
        $context = trim($context) ?: 'FRONTEND';
        
        aicoagac_debug_log("Main Plugin: Constructor called in context: {$context}");
        
        // Note: Suppressed detailed backtrace logging in production builds
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Prevent multiple initialization
        static $initialized = false;
        if ($initialized) {
            aicoagac_debug_log('Main Plugin: init() already called, skipping');
            return;
        }
        $initialized = true;
        
        // Debug: Track WordPress context in init
        $context = '';
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $context .= 'AJAX ';
        }
        if (defined('DOING_CRON') && DOING_CRON) {
            $context .= 'CRON ';
        }
        if (function_exists('is_admin') && is_admin()) {
            $context .= 'ADMIN ';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context .= 'REST ';
        }
        $context = trim($context) ?: 'FRONTEND';
        
        aicoagac_debug_log("Main Plugin: Starting initialization in context: {$context}");
        
        try {
            // Dependency installer removed for WordPress.org compliance
            
            // Run database migrations if needed
            $this->check_and_run_migrations();
            
            // AGENTS.MD ENHANCEMENT: Initialize REST API with error handling
            try {
                AICOAGAC_Rest_Api::get_instance();
                aicoagac_debug_log('Main Plugin: REST API singleton instance initialized');
            } catch (Exception $e) {
                aicoagac_debug_log('Main Plugin: ERROR - Failed to initialize REST API: ' . $e->getMessage());
            }
            
            // UNIFIED AUTOMATION FIX: Simple Automation is initialized in aicoagac_load_automation_system()
            // Removed duplicate initialization to prevent conflicts
            
            // AGENTS.MD CRITICAL FIX: Initialize only the selected automation system
            // This logic is now handled by aicoagac_load_automation_system
            
            // Add admin menu
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Enqueue admin scripts
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // Admin initialization (moved from redundant handler)
            if (is_admin()) {
                // Lower priority to run after OAuth callbacks and other critical tasks
                add_action('admin_init', array($this, 'admin_init_tasks'), 15);
            }
            
            aicoagac_debug_log("Main Plugin: Initialization completed successfully in context: {$context}");
            
        } catch (Exception $e) {
            aicoagac_debug_log("Main Plugin: CRITICAL ERROR during initialization: " . $e->getMessage());
            // Continue execution but log the error for debugging
        }
    }
    
    /**
     * Get unified automation system status
     */
    public function get_automation_system_status() {
        return array(
            'current_system' => 'unified',
            'simple_available' => false,
            'system_status' => __('Unified & Reliable', 'ai-content-agent')
        );
    }
    
    /**
     * Admin initialization tasks (moved from redundant handler)
     */
    public function admin_init_tasks() {
        // Handle database updates (has its own frequency control)
        aicoagac_check_database_updates();
        

    }
    

    
    /**
     * Check and run database migrations if needed
     */
    private function check_and_run_migrations() {
        // Only run migrations in admin context to avoid performance issues
        if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }
        
        // Check if migrations have been checked recently to avoid repeated checks
        if (get_transient('aicoagac_migration_check_done')) {
            return;
        }
        
        require_once AICOAGAC_PLUGIN_PATH . 'includes/class-aicoagac-migration-manager.php';
        $migration_manager = new AICOAGAC_Migration_Manager();
        $result = $migration_manager->run_migrations();
        
        if (is_wp_error($result)) {
            aicoagac_debug_log('Migration error: ' . $result->get_error_message());
            // Set a shorter transient on error to retry sooner
            set_transient('aicoagac_migration_check_done', time(), 300); // 5 minutes
        } else {
            // Set transient to avoid repeated checks for 1 hour
            set_transient('aicoagac_migration_check_done', time(), 3600);
        }
    }
    

    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'AI Content Agent (ACA)', 'ai-content-agent' ),
            __( 'AI Content Agent (ACA)', 'ai-content-agent' ),
            'manage_options',
            'ai-content-agent',
            array($this, 'admin_page'),
            'dashicons-welcome-write-blog',
            30
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        // Dependencies installer removed for WordPress.org compliance
        echo '<div id="root"></div>';
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin page
        if ('toplevel_page_ai-content-agent' != $hook) {
            return;
        }
        
        // Find the latest built JS file in admin/assets
        $assets_dir = AICOAGAC_PLUGIN_PATH . 'admin/assets/';
        $js_files = glob($assets_dir . 'index-*.js');
        
        if (!empty($js_files)) {
            // Get the most recent JS file by modification time
            $latest_file = '';
            $latest_time = 0;
            
            foreach ($js_files as $file) {
                $file_time = filemtime($file);
                if ($file_time > $latest_time) {
                    $latest_time = $file_time;
                    $latest_file = $file;
                }
            }
            
            if ($latest_file) {
                $js_filename = basename($latest_file);
                $js_version = AICOAGAC_VERSION . '-' . $latest_time;
                $script_handle = 'aicoagac-app-' . md5($js_filename . $latest_time);
                
                // Enqueue the compiled React app (CSS is inlined in JS)
                wp_enqueue_script($script_handle, AICOAGAC_PLUGIN_URL . 'admin/assets/' . $js_filename, array(), $js_version, true);
                
                // Pass data to React app
                wp_localize_script($script_handle, 'aicoagacData', array(
                    'nonce' => wp_create_nonce('wp_rest'),
                    'api_url' => rest_url('aicoagac/v1/'),
                    'admin_url' => admin_url(),
                    'plugin_url' => AICOAGAC_PLUGIN_URL,
                    'is_pro' => false, // Pro features removed
                    'i18n' => array(
                        // Pagination and UI strings
                        'Page' => __('Page', 'ai-content-agent'),
                        'of' => __('of', 'ai-content-agent'),
                        'Prev' => __('Prev', 'ai-content-agent'),
                        'Next' => __('Next', 'ai-content-agent'),
                        // Optional related labels used around pagination/header
                        'Published Articles' => __('Published Articles', 'ai-content-agent'),
                        'Your Published Content' => __('Your Published Content', 'ai-content-agent'),
                        'No Published Posts Yet' => __('No Published Posts Yet', 'ai-content-agent'),
                        'Go to Drafts' => __('Go to Drafts', 'ai-content-agent'),
                        'View Live Post' => __('View Live Post', 'ai-content-agent'),
                        'Published Posts' => __('Published Posts', 'ai-content-agent'),
                        'Your successful content publications' => __('Your successful content publications', 'ai-content-agent'),
                    ),
                ));
            }
        } else {
            // No assets found (should not happen in distributed build)
            return;
        }
    }
}

// Initialize the plugin (singleton) - delayed to ensure WordPress is ready
add_action('plugins_loaded', function() {
    global $aicoagac_content_agent;
    $aicoagac_content_agent = AICOAGAC_Content_Agent::get_instance();
}, 1);

// Legacy cron hooks removed - using unified automation system only

// Admin initialization is handled in the main init method to avoid redundancy

// Admin notices - only show on admin pages, not during REST API requests


        // Post view count tracking (optimized to reduce database load)
// Removed aicoagac_track_post_views and its hook to avoid default tracking for privacy

// Admin initialization functions moved to main init method to avoid redundancy

function aicoagac_check_database_updates() {
    // Only run for admins and not during plugin activation or AJAX
    if (!is_admin() || !current_user_can('activate_plugins') || wp_doing_ajax() || defined('DOING_AUTOSAVE')) {
        return;
    }
    
    // Don't run on every admin page load - use transient to limit checks
    if (get_transient('aicoagac_migration_check_done')) {
        return;
    }
    
    // Include migration manager
    $migration_file = AICOAGAC_PLUGIN_PATH . 'includes/class-aicoagac-migration-manager.php';
    if (file_exists($migration_file)) {
        require_once $migration_file;
        
        $migration_manager = new AICOAGAC_Migration_Manager();
        $result = $migration_manager->run_migrations();
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                // Don't show notices during REST API requests or AJAX calls
                if (defined('REST_REQUEST') && REST_REQUEST) {
                    return;
                }
                if (defined('DOING_AJAX') && DOING_AJAX) {
                    return;
                }
                echo '<div class="notice notice-error"><p>' .
                     esc_html__( 'ACA Database Update Failed:', 'ai-content-agent' ) . ' ' .
                     esc_html($result->get_error_message()) . '</p></div>';
            });
        }
    }
    
    // Set transient to prevent running again for 1 hour
    set_transient('aicoagac_migration_check_done', true, HOUR_IN_SECONDS);
}

// Add database migration check hook (optimized)
add_action('admin_init', 'aicoagac_check_database_updates', 20);

// Render FAQ JSON-LD from meta if present (safe output, no duplication)
function aicoagac_render_faq_jsonld() {
    if (!is_single() || is_admin()) {
        return;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return;
    }
    $json = get_post_meta($post->ID, '_aicoagac_faq_jsonld', true);
    if (!empty($json)) {
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['@type']) && strtolower($decoded['@type']) === 'faqpage') {
            echo "\n<!-- AICOAGAC_FAQ_JSONLD -->\n<script type=\"application/ld+json\">" . wp_json_encode($decoded) . "</script>\n<!-- /AICOAGAC_FAQ_JSONLD -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
add_action('wp_head', 'aicoagac_render_faq_jsonld', 20);

// Rank Math: inject our FAQ into its JSON-LD graph if available
function aicoagac_rankmath_inject_faq_graph($data, $jsonld) {
    if (!is_single() || is_admin()) {
        return $data;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return $data;
    }
    $faq_json = get_post_meta($post->ID, '_aicoagac_faq_jsonld', true);
    if (empty($faq_json)) {
        return $data;
    }
    $node = json_decode($faq_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($node)) {
        return $data;
    }
    // Deduplicate if similar node already exists
    $has_faq = false;
    if (is_array($data)) {
        foreach ($data as $piece) {
            if (is_array($piece) && isset($piece['@type']) && strtolower($piece['@type']) === 'faqpage') {
                $has_faq = true; break;
            }
        }
    }
    if (!$has_faq) {
        $data[] = $node;
    }
    return $data;
}
add_filter('rank_math/json_ld', 'aicoagac_rankmath_inject_faq_graph', 20, 2);

// Yoast: inject FAQ as additional piece if not present
function aicoagac_yoast_inject_faq_graph($pieces, $context) {
    if (!is_single() || is_admin()) {
        return $pieces;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return $pieces;
    }
    $faq_json = get_post_meta($post->ID, '_aicoagac_faq_jsonld', true);
    if (empty($faq_json)) {
        return $pieces;
    }
    $node = json_decode($faq_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($node)) {
        return $pieces;
    }
    // Yoast expects graph pieces; we can append raw piece via wpseo_schema_graph_pieces filter by wrapping
    $pieces[] = $node;
    return $pieces;
}
add_filter('wpseo_schema_graph_pieces', 'aicoagac_yoast_inject_faq_graph', 20, 2);

// Yoast: also append into final graph to ensure presence
function aicoagac_yoast_inject_faq_graph_final($graph, $context) {
    if (!is_single() || is_admin()) {
        return $graph;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return $graph;
    }
    $faq_json = get_post_meta($post->ID, '_aicoagac_faq_jsonld', true);
    if (empty($faq_json)) {
        return $graph;
    }
    $node = json_decode($faq_json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($node)) {
        return $graph;
    }
    // Deduplicate
    if (is_array($graph)) {
        foreach ($graph as $piece) {
            if (is_array($piece) && isset($piece['@type']) && strtolower($piece['@type']) === 'faqpage') {
                return $graph; // already exists
            }
        }
    }
    $graph[] = $node;
    return $graph;
}
add_filter('wpseo_schema_graph', 'aicoagac_yoast_inject_faq_graph_final', 20, 2);

// --- Scalable Indexing Hooks (lightweight) ---
function aicoagac_update_post_index_record($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if ($post->post_type !== 'post') {
        return;
    }
    global $wpdb;
    $title = wp_strip_all_tags(get_the_title($post_id));
    $url = get_permalink($post_id) ?: home_url('/?p=' . $post_id);
    $lang = get_locale();
    $cats = wp_get_post_categories($post_id, array('fields' => 'names'));
    $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
    $content = wp_strip_all_tags($post->post_content);
    // Very small summary (first sentence ~160 chars)
    $summary = mb_substr(preg_replace('/\s+/', ' ', $content), 0, 160);
    $keywords = '';
    // Heuristic keywords from title words (fallback)
    $words = preg_split('/[\s\-_:;,.!?]+/', strtolower($title));
    $words = array_values(array_filter($words, function($w){ return mb_strlen($w) > 3; }));
    if (!empty($words)) {
        $keywords = implode(', ', array_slice(array_unique($words), 0, 7));
    }
    $table = $wpdb->prefix . 'aicoagac_post_index';
    $wpdb->replace($table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write for plugin index
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
}
add_action('save_post', 'aicoagac_update_post_index_record', 10, 3);

function aicoagac_update_post_style_record($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if ($post->post_type !== 'post') {
        return;
    }
    global $wpdb;
    $lang = get_locale();
    $content = wp_strip_all_tags($post->post_content);
    $paragraph = mb_substr(preg_replace('/\s+/', ' ', $content), 0, 400);
    // Simple style metrics
    $sentences = preg_split('/[\.\!\?]+\s*/u', $content, -1, PREG_SPLIT_NO_EMPTY);
    $avg_len = 0; $count = count($sentences);
    if ($count > 0) {
        $total = 0; foreach ($sentences as $s) { $total += mb_strlen(trim($s)); }
        $avg_len = round($total / max(1, $count));
    }
    $has_list = (strpos($post->post_content, '<ul') !== false) || (strpos($post->post_content, '<ol') !== false);
    $has_bold = (strpos($post->post_content, '<strong') !== false);
    $metrics = array(
        'avg_sentence_length' => $avg_len,
        'has_list' => (bool)$has_list,
        'has_bold' => (bool)$has_bold,
    );
    $table = $wpdb->prefix . 'aicoagac_post_style';
    $wpdb->replace($table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write for plugin style index
        'post_id' => $post_id,
        'lang' => $lang,
        'summary_1p' => $paragraph,
        'style_metrics' => wp_json_encode($metrics),
        'updated_at' => current_time('mysql')
    ));
}
add_action('save_post', 'aicoagac_update_post_style_record', 11, 3);

// Keep index on status change as well (URL/permalinks may stabilize on publish)
function aicoagac_transition_post_status_index($new_status, $old_status, $post) {
    if ($post->post_type !== 'post') { return; }
    if ($new_status === 'publish' && $old_status !== 'publish') {
        aicoagac_update_post_index_record($post->ID, $post, true);
        aicoagac_update_post_style_record($post->ID, $post, true);
    }
}
add_action('transition_post_status', 'aicoagac_transition_post_status_index', 10, 3);

