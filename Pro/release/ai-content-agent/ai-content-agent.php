<?php
/**
 * Plugin Name: AI Content Agent (ACA)
 * Plugin URI: https://wordpress.org/plugins/ai-content-agent/
 * Description: AI-powered content creation and management plugin that generates blog posts, ideas, and manages your content workflow automatically with Pro features.
 * Version: 1.0.1
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
define('ACA_VERSION', '1.0.0');
define('ACA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ACA_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Check if ACA Pro is active with multi-point validation
 * Enhanced with cache busting for automation activation timing issues
 * 
 * @param bool $force_refresh Force refresh of cached license data
 * @return bool True if pro license is active, false otherwise
 */
function is_aca_pro_active($force_refresh = false) {
    // AGENTS.MD FIX: Force cache refresh for automation activation timing issues
    if ($force_refresh) {
        wp_cache_delete('aca_license_status', 'options');
        wp_cache_delete('aca_license_key', 'options');
        wp_cache_delete('aca_license_verified', 'options');
        wp_cache_delete('aca_license_timestamp', 'options');
        
        // Also clear alloptions cache
        wp_cache_delete('alloptions', 'options');
        
        aca_debug_log('ACA Pro Check: Forced cache refresh for license verification');
    }
    
    // Multi-point validation to prevent bypass attempts
    $license_status = get_option('aca_license_status');
    $license_key = get_option('aca_license_key');
    $license_verified = get_option('aca_license_verified');
    $license_timestamp = get_option('aca_license_timestamp', 0);
    
    $checks = array(
        $license_status === 'active',
        $license_verified === wp_hash('verified'),
        (time() - $license_timestamp) < 604800, // Weekly verification (7 days instead of 1 day)
        !empty($license_key)
    );
    
    $is_active = count(array_filter($checks)) === 4;
    
    // AGENTS.MD DEBUG: Enhanced debugging for license verification issues
    if (defined('WP_DEBUG') && WP_DEBUG) {
        aca_debug_log(sprintf(
            'ACA Pro Check: Status=%s, Key=%s, Verified=%s, Timestamp=%s, Age=%ds, Result=%s',
            $license_status ?: 'null',
            $license_key ? 'set' : 'empty',
            $license_verified === wp_hash('verified') ? 'valid' : 'invalid',
            $license_timestamp ?: 'null',
            time() - $license_timestamp,
            $is_active ? 'ACTIVE' : 'INACTIVE'
        ));
    }
    
    return $is_active;
}

/**
 * Debug logging helper - only logs when WP_DEBUG is enabled
 * 
 * @param string $message Log message
 * @return void
 */
function aca_debug_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ACA: ' . $message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Centralized debug logging function
    }
}

// Include required files
require_once ACA_PLUGIN_PATH . 'includes/class-aca-activator.php';
require_once ACA_PLUGIN_PATH . 'includes/class-aca-deactivator.php';
require_once ACA_PLUGIN_PATH . 'includes/class-aca-rest-api.php';
require_once ACA_PLUGIN_PATH . 'includes/class-aca-cache-manager.php';

// Load Simple Automation System (unified automation system)
require_once ACA_PLUGIN_PATH . 'includes/class-aca-simple-automation.php';

/**
 * Initialize unified automation system
 * UNIFIED AUTOMATION FIX: Removed dual system complexity
 */
function aca_load_automation_system() {
    // Always use Simple Automation System (unified approach)
    aca_debug_log('Main Plugin: Loading unified Simple Automation System');
    
    // Simple Automation is always preferred and loaded
    if (class_exists('ACA_Simple_Automation')) {
        ACA_Simple_Automation::get_instance();
        aca_debug_log('Main Plugin: Unified Simple Automation System initialized');
    }
}

// Load automation system after WordPress is fully initialized
add_action('init', 'aca_load_automation_system', 1);

// AGENTS.MD CRITICAL FIX: Prevent dual automation system conflicts
// Determine which automation system to use based on configuration and availability
// This logic is now handled by aca_load_automation_system

// i18n: WordPress.org handles loading translations automatically for plugins hosted on wp.org

// Activation and deactivation hooks
register_activation_hook(__FILE__, array('ACA_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('ACA_Deactivator', 'deactivate'));

/**
 * Main plugin class
 */
class ACA_Content_Agent {
    
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
        if (is_admin()) {
            $context .= 'ADMIN ';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context .= 'REST ';
        }
        $context = trim($context) ?: 'FRONTEND';
        
        aca_debug_log("Main Plugin: Constructor called in context: {$context}");
        
        // Note: Suppressed detailed backtrace logging in production builds
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Prevent multiple initialization
        static $initialized = false;
        if ($initialized) {
            aca_debug_log('Main Plugin: init() already called, skipping');
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
        if (is_admin()) {
            $context .= 'ADMIN ';
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            $context .= 'REST ';
        }
        $context = trim($context) ?: 'FRONTEND';
        
        aca_debug_log("Main Plugin: Starting initialization in context: {$context}");
        
        try {
            // Dependency installer removed for WordPress.org compliance
            
            // Run database migrations if needed
            $this->check_and_run_migrations();
            
            // AGENTS.MD ENHANCEMENT: Initialize REST API with error handling
            try {
                ACA_Rest_Api::get_instance();
                aca_debug_log('Main Plugin: REST API singleton instance initialized');
            } catch (Exception $e) {
                aca_debug_log('Main Plugin: ERROR - Failed to initialize REST API: ' . $e->getMessage());
            }
            
            // UNIFIED AUTOMATION FIX: Initialize Simple Automation System
            try {
                ACA_Simple_Automation::get_instance();
                aca_debug_log('Main Plugin: Unified Simple Automation initialized');
            } catch (Exception $e) {
                aca_debug_log('Main Plugin: ERROR - Failed to initialize Simple Automation: ' . $e->getMessage());
            }
            
            // AGENTS.MD CRITICAL FIX: Initialize only the selected automation system
            // This logic is now handled by aca_load_automation_system
            
            // Add admin menu
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Enqueue admin scripts
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // Admin initialization (moved from redundant handler)
            if (is_admin()) {
                // Lower priority to run after OAuth callbacks and other critical tasks
                add_action('admin_init', array($this, 'admin_init_tasks'), 15);
            }
            
            aca_debug_log("Main Plugin: Initialization completed successfully in context: {$context}");
            
        } catch (Exception $e) {
            aca_debug_log("Main Plugin: CRITICAL ERROR during initialization: " . $e->getMessage());
            // Continue execution but log the error for debugging
        }
    }
    
    /**
     * Get unified automation system status
     */
    public function get_automation_system_status() {
        return array(
            'current_system' => 'unified',
            'simple_available' => class_exists('ACA_Simple_Automation'),
            'system_status' => __('Unified & Reliable', 'ai-content-agent')
        );
    }
    
    /**
     * Admin initialization tasks (moved from redundant handler)
     */
    public function admin_init_tasks() {
        // Handle database updates (has its own frequency control)
        aca_check_database_updates();
        

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
        if (get_transient('aca_migration_check_done')) {
            return;
        }
        
        require_once ACA_PLUGIN_PATH . 'includes/class-aca-migration-manager.php';
        $migration_manager = new ACA_Migration_Manager();
        $result = $migration_manager->run_migrations();
        
        if (is_wp_error($result)) {
            aca_debug_log('Migration error: ' . $result->get_error_message());
            // Set a shorter transient on error to retry sooner
            set_transient('aca_migration_check_done', time(), 300); // 5 minutes
        } else {
            // Set transient to avoid repeated checks for 1 hour
            set_transient('aca_migration_check_done', time(), 3600);
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
        // Display dependency status if needed (only if installer exists)
        if (class_exists('ACA_Dependencies_Installer') && !ACA_Dependencies_Installer::are_dependencies_installed()) {
            echo '<div class="wrap">';
            ACA_Dependencies_Installer::display_dependency_status();
            echo '</div>';
        }
        
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
        $assets_dir = ACA_PLUGIN_PATH . 'admin/assets/';
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
                $js_version = ACA_VERSION . '-' . $latest_time;
                $script_handle = 'aca-app-' . md5($js_filename . $latest_time);
                
                // Enqueue the compiled React app (CSS is inlined in JS)
                wp_enqueue_script($script_handle, ACA_PLUGIN_URL . 'admin/assets/' . $js_filename, array(), $js_version, true);
                
                // Pass data to React app
                wp_localize_script($script_handle, 'acaData', array(
                    'nonce' => wp_create_nonce('wp_rest'),
                    'api_url' => rest_url('aca/v1/'),
                    'admin_url' => admin_url(),
                    'plugin_url' => ACA_PLUGIN_URL,
                    'is_pro' => is_aca_pro_active(),
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

// Initialize the plugin (singleton)
$aca_content_agent = ACA_Content_Agent::get_instance();

// Legacy cron hooks removed - using unified automation system only

// Admin initialization is handled in the main init method to avoid redundancy

// Admin notices - only show on admin pages, not during REST API requests


        // Post view count tracking (optimized to reduce database load)
// Removed aca_track_post_views and its hook to avoid default tracking for privacy

// Admin initialization functions moved to main init method to avoid redundancy

function aca_check_database_updates() {
    // Only run for admins and not during plugin activation or AJAX
    if (!is_admin() || !current_user_can('activate_plugins') || wp_doing_ajax() || defined('DOING_AUTOSAVE')) {
        return;
    }
    
    // Don't run on every admin page load - use transient to limit checks
    if (get_transient('aca_migration_check_done')) {
        return;
    }
    
    // Include migration manager
    $migration_file = ACA_PLUGIN_PATH . 'includes/class-aca-migration-manager.php';
    if (file_exists($migration_file)) {
        require_once $migration_file;
        
        $migration_manager = new ACA_Migration_Manager();
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
    set_transient('aca_migration_check_done', true, HOUR_IN_SECONDS);
}

// Add database migration check hook (optimized)
add_action('admin_init', 'aca_check_database_updates', 20);

// Render FAQ JSON-LD from meta if present (safe output, no duplication)
function aca_render_faq_jsonld() {
    if (!is_single() || is_admin()) {
        return;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return;
    }
    $json = get_post_meta($post->ID, '_aca_faq_jsonld', true);
    if (!empty($json)) {
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && isset($decoded['@type']) && strtolower($decoded['@type']) === 'faqpage') {
            echo "\n<!-- ACA_FAQ_JSONLD -->\n<script type=\"application/ld+json\">" . wp_json_encode($decoded) . "</script>\n<!-- /ACA_FAQ_JSONLD -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }
}
add_action('wp_head', 'aca_render_faq_jsonld', 20);

// Rank Math: inject our FAQ into its JSON-LD graph if available
function aca_rankmath_inject_faq_graph($data, $jsonld) {
    if (!is_single() || is_admin()) {
        return $data;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return $data;
    }
    $faq_json = get_post_meta($post->ID, '_aca_faq_jsonld', true);
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
add_filter('rank_math/json_ld', 'aca_rankmath_inject_faq_graph', 20, 2);

// Yoast: inject FAQ as additional piece if not present
function aca_yoast_inject_faq_graph($pieces, $context) {
    if (!is_single() || is_admin()) {
        return $pieces;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return $pieces;
    }
    $faq_json = get_post_meta($post->ID, '_aca_faq_jsonld', true);
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
add_filter('wpseo_schema_graph_pieces', 'aca_yoast_inject_faq_graph', 20, 2);

// Yoast: also append into final graph to ensure presence
function aca_yoast_inject_faq_graph_final($graph, $context) {
    if (!is_single() || is_admin()) {
        return $graph;
    }
    global $post;
    if (!$post || $post->post_type !== 'post') {
        return $graph;
    }
    $faq_json = get_post_meta($post->ID, '_aca_faq_jsonld', true);
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
add_filter('wpseo_schema_graph', 'aca_yoast_inject_faq_graph_final', 20, 2);

// --- Scalable Indexing Hooks (lightweight) ---
function aca_update_post_index_record($post_id, $post, $update) {
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
    $table = $wpdb->prefix . 'aca_post_index';
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
add_action('save_post', 'aca_update_post_index_record', 10, 3);

function aca_update_post_style_record($post_id, $post, $update) {
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
    $table = $wpdb->prefix . 'aca_post_style';
    $wpdb->replace($table, array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write for plugin style index
        'post_id' => $post_id,
        'lang' => $lang,
        'summary_1p' => $paragraph,
        'style_metrics' => wp_json_encode($metrics),
        'updated_at' => current_time('mysql')
    ));
}
add_action('save_post', 'aca_update_post_style_record', 11, 3);

// Keep index on status change as well (URL/permalinks may stabilize on publish)
function aca_transition_post_status_index($new_status, $old_status, $post) {
    if ($post->post_type !== 'post') { return; }
    if ($new_status === 'publish' && $old_status !== 'publish') {
        aca_update_post_index_record($post->ID, $post, true);
        aca_update_post_style_record($post->ID, $post, true);
    }
}
add_action('transition_post_status', 'aca_transition_post_status_index', 10, 3);

