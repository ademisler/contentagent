<?php
/**
 * Plugin activation functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACA_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Check system requirements first
        self::check_system_requirements();
        
        self::create_tables();
        self::set_default_options();
        self::schedule_cron_jobs();
        
        // Initialize unified Simple Automation System
        if (class_exists('ACA_Simple_Automation')) {
            $automation = ACA_Simple_Automation::get_instance();
            
            // Initialize scheduling hash from current settings
            $settings = get_option('aca_settings', array());
            $initial = array(
                'mode' => $settings['mode'] ?? 'manual',
                'semiAutoIdeaFrequency' => $settings['semiAutoIdeaFrequency'] ?? null,
                'fullAutoDailyPostCount' => intval($settings['fullAutoDailyPostCount'] ?? 1),
                'fullAutoPublishFrequency' => $settings['fullAutoPublishFrequency'] ?? 'daily',
                'autoPublish' => (bool)($settings['autoPublish'] ?? false),
                'pro' => is_aca_pro_active(false),
            );
            update_option('aca_last_scheduled_settings_hash', md5(wp_json_encode($initial)));
            
            $automation->force_reschedule_tasks();
            aca_debug_log('Activator: Unified Simple Automation initialized and scheduled');
        } else {
            aca_debug_log('Activator: Simple Automation class not available during activation');
        }
    }
    
    /**
     * Check system requirements
     */
    private static function check_system_requirements() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(ACA_PLUGIN_PATH . 'ai-content-agent.php'));
            wp_die(
                esc_html( sprintf(
                    /* translators: %s: current PHP version */
                    __( 'AI Content Agent requires PHP 7.4 or higher. Current version: %s', 'ai-content-agent' ),
                    PHP_VERSION
                ) ),
                esc_html__( 'Plugin Activation Error', 'ai-content-agent' ),
                array('back_link' => true)
            );
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            deactivate_plugins(plugin_basename(ACA_PLUGIN_PATH . 'ai-content-agent.php'));
            wp_die(
                esc_html( sprintf(
                    /* translators: %s: current WordPress version */
                    __( 'AI Content Agent requires WordPress 5.0 or higher. Current version: %s', 'ai-content-agent' ),
                    $wp_version
                ) ),
                esc_html__( 'Plugin Activation Error', 'ai-content-agent' ),
                array('back_link' => true)
            );
        }
        
        // Check required PHP extensions
        $required_extensions = array('mbstring', 'curl', 'json', 'zip');
        $missing_extensions = array();
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        
        if (!empty($missing_extensions)) {
            deactivate_plugins(plugin_basename(ACA_PLUGIN_PATH . 'ai-content-agent.php'));
            wp_die(
                esc_html( sprintf(
                    /* translators: %s: comma-separated list of missing PHP extensions */
                    __( 'AI Content Agent requires the following PHP extensions: %s', 'ai-content-agent' ),
                    implode(', ', array_map('sanitize_text_field', $missing_extensions))
                ) ),
                esc_html__( 'Plugin Activation Error', 'ai-content-agent' ),
                array('back_link' => true)
            );
        }
        
        aca_debug_log('Activator: System requirements check passed');
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Ideas table
        $ideas_table_name = $wpdb->prefix . 'aca_ideas';
        $sql_ideas = "CREATE TABLE $ideas_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            status varchar(20) DEFAULT 'new' NOT NULL,
            priority int DEFAULT 1 NOT NULL,
            source varchar(20) DEFAULT 'ai' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Activity logs table
        $logs_table_name = $wpdb->prefix . 'aca_activity_logs';
        $sql_logs = "CREATE TABLE $logs_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            type varchar(50) NOT NULL,
            details text NOT NULL,
            icon varchar(50) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Content updates tracking table
        $content_updates_table_name = $wpdb->prefix . 'aca_content_updates';
        $sql_content_updates = "CREATE TABLE $content_updates_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            last_updated datetime NOT NULL,
            update_type varchar(50) NOT NULL,
            ai_suggestions longtext,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Lightweight post index for internal linking at scale
        $post_index_table = $wpdb->prefix . 'aca_post_index';
        $sql_post_index = "CREATE TABLE $post_index_table (
            post_id BIGINT(20) NOT NULL,
            lang VARCHAR(12) NULL,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            cats TEXT NULL,
            tags TEXT NULL,
            summary_1l TEXT NULL,
            keywords TEXT NULL,
            embedding LONGTEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id),
            KEY lang (lang(10)),
            KEY updated_at (updated_at)
        ) $charset_collate;";

        // Per-post style hints and metrics for cheap style analysis
        $post_style_table = $wpdb->prefix . 'aca_post_style';
        $sql_post_style = "CREATE TABLE $post_style_table (
            post_id BIGINT(20) NOT NULL,
            lang VARCHAR(12) NULL,
            summary_1p TEXT NULL,
            style_metrics LONGTEXT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (post_id),
            KEY lang (lang(10))
        ) $charset_collate;";

        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_ideas);
        dbDelta($sql_logs);
        dbDelta($sql_content_updates);
        dbDelta($sql_post_index);
        dbDelta($sql_post_style);

        
        // Ensure all required columns exist (for existing installations)
        self::ensure_table_columns();
    }
    
    /**
     * Ensure all required table columns exist
     */
    private static function ensure_table_columns() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $ideas_table = $wpdb->prefix . 'aca_ideas';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$ideas_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            status varchar(20) DEFAULT 'new' NOT NULL,
            priority int DEFAULT 1 NOT NULL,
            source varchar(20) DEFAULT 'ai' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
        dbDelta($sql);
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_settings = array(
            'mode' => 'manual',
            'autoPublish' => false,


            'imageSourceProvider' => 'pexels',
            'aiImageStyle' => 'photorealistic',
            'googleCloudProjectId' => '',
            'googleCloudLocation' => 'us-central1',
            'pexelsApiKey' => '',
            'unsplashApiKey' => '',
            'pixabayApiKey' => '',
            'seoPlugin' => 'none',
            'geminiApiKey' => '',
        );
        
        add_option('aca_settings', $default_settings);
        add_option('aca_style_guide', null);
        

    }
    
    /**
     * Clear any legacy cron jobs (Unified Automation System)
     */
    private static function schedule_cron_jobs() {
        // Clear any existing legacy cron events
        wp_clear_scheduled_hook('aca_thirty_minute_event');
        wp_clear_scheduled_hook('aca_fifteen_minute_event');
        aca_debug_log('Activator: Cleared legacy cron events - using unified automation system');
    }
    
    /**
     * Initialize migration system (new method - add at the end of class)
     */
    private static function initialize_migration_system() {
        $migration_file = plugin_dir_path(__FILE__) . 'class-aca-migration-manager.php';
        
        if (file_exists($migration_file)) {
            require_once $migration_file;
            
            $migration_manager = new ACA_Migration_Manager();
            $result = $migration_manager->run_migrations();
            
            if (is_wp_error($result)) {
                aca_debug_log('Migration initialization failed: ' . $result->get_error_message());
                // Don't fail activation - just log the error
            }
        }
    }
    
    /**
     * Initialize Simple Automation on plugin activation
     */
    private static function initialize_simple_automation_on_activation() {
        // Load Simple Automation system
        $simple_automation_file = plugin_dir_path(__FILE__) . 'class-aca-simple-automation.php';
        if (file_exists($simple_automation_file)) {
            require_once $simple_automation_file;
            
            if (class_exists('ACA_Simple_Automation')) {
                $automation = ACA_Simple_Automation::get_instance();
                
                // Initialize default automation settings
                $default_settings = array(
                    'mode' => 'manual',
                    'frequency' => 'hourly',
                    'enabled' => false
                );
                
                $existing_settings = get_option('aca_settings', array());
                $merged_settings = array_merge($default_settings, $existing_settings);
                update_option('aca_settings', $merged_settings);
                
                // AGENTS.MD Step 5: Force schedule automation tasks on activation
                // Plugin activation should always schedule tasks regardless of cache
                $automation = ACA_Simple_Automation::get_instance();
                $automation->force_reschedule_tasks();
                
                aca_debug_log('Activator: Simple Automation initialized and tasks force scheduled on activation');
            } else {
                aca_debug_log('Activator: Simple Automation class not available during activation');
            }
        }
        
    }
}