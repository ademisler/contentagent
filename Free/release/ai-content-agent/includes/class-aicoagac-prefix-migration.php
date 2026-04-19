<?php
/**
 * AI Content Agent - Prefix Migration
 * Migrates old aca_ prefixed data to new aicoagac_ prefix
 * 
 * @package AI_Content_Agent
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AICOAGAC_Prefix_Migration {
    
    /**
     * Run the prefix migration
     */
    public static function migrate() {
        global $wpdb;
        
        // Migrate options
        self::migrate_options();
        
        // Migrate database tables
        self::migrate_tables();
        
        // Migrate post meta
        self::migrate_post_meta();
        
        // Clear old cron hooks
        self::clear_old_cron_hooks();
        
        // Mark migration as complete
        update_option('aicoagac_prefix_migration_completed', true);
    }
    
    /**
     * Migrate options from aca_ to aicoagac_
     */
    private static function migrate_options() {
        $options_map = array(
            // Settings
            'aca_gemini_api_key' => 'aicoagac_gemini_api_key',
            'aca_pexels_api_key' => 'aicoagac_pexels_api_key',
            'aca_automation_enabled' => 'aicoagac_automation_enabled',
            'aca_automation_mode' => 'aicoagac_automation_mode',
            'aca_idea_generation_enabled' => 'aicoagac_idea_generation_enabled',
            'aca_draft_creation_enabled' => 'aicoagac_draft_creation_enabled',
            'aca_post_publishing_enabled' => 'aicoagac_post_publishing_enabled',
            'aca_idea_generation_interval' => 'aicoagac_idea_generation_interval',
            'aca_draft_creation_interval' => 'aicoagac_draft_creation_interval',
            'aca_post_publishing_interval' => 'aicoagac_post_publishing_interval',
            'aca_maintenance_interval' => 'aicoagac_maintenance_interval',
            'aca_idea_generation_batch_size' => 'aicoagac_idea_generation_batch_size',
            'aca_draft_creation_batch_size' => 'aicoagac_draft_creation_batch_size',
            'aca_post_publishing_batch_size' => 'aicoagac_post_publishing_batch_size',
            'aca_content_language' => 'aicoagac_content_language',
            'aca_content_tone' => 'aicoagac_content_tone',
            'aca_content_length' => 'aicoagac_content_length',
            'aca_content_structure' => 'aicoagac_content_structure',
            'aca_include_images' => 'aicoagac_include_images',
            'aca_include_videos' => 'aicoagac_include_videos',
            'aca_include_faq' => 'aicoagac_include_faq',
            'aca_include_toc' => 'aicoagac_include_toc',
            'aca_include_meta_description' => 'aicoagac_include_meta_description',
            'aca_include_focus_keyword' => 'aicoagac_include_focus_keyword',
            'aca_include_internal_links' => 'aicoagac_include_internal_links',
            'aca_include_external_links' => 'aicoagac_include_external_links',
            'aca_categories' => 'aicoagac_categories',
            'aca_tags' => 'aicoagac_tags',
            'aca_author' => 'aicoagac_author',
            'aca_post_status' => 'aicoagac_post_status',
            'aca_comment_status' => 'aicoagac_comment_status',
            'aca_ping_status' => 'aicoagac_ping_status',
            'aca_post_format' => 'aicoagac_post_format',
            'aca_enable_logging' => 'aicoagac_enable_logging',
            'aca_log_retention_days' => 'aicoagac_log_retention_days',
            'aca_debug_mode' => 'aicoagac_debug_mode',
            'aca_cache_enabled' => 'aicoagac_cache_enabled',
            'aca_cache_duration' => 'aicoagac_cache_duration',
            'aca_max_retries' => 'aicoagac_max_retries',
            'aca_retry_delay' => 'aicoagac_retry_delay',
            'aca_timeout' => 'aicoagac_timeout',
            'aca_migration_version' => 'aicoagac_migration_version',
            'aca_last_scheduled_settings_hash' => 'aicoagac_last_scheduled_settings_hash',
            'aca_index_backfill_last_id' => 'aicoagac_index_backfill_last_id',
            
            // License options (to be deleted)
            'aca_license_status' => null,
            'aca_license_data' => null,
            'aca_license_site_hash' => null,
            'aca_license_key' => null,
            'aca_license_verified' => null,
            'aca_license_timestamp' => null,
        );
        
        foreach ($options_map as $old_key => $new_key) {
            $value = get_option($old_key);
            if ($value !== false) {
                if ($new_key !== null) {
                    update_option($new_key, $value);
                }
                delete_option($old_key);
            }
        }
    }
    
    /**
     * Migrate database tables
     */
    private static function migrate_tables() {
        global $wpdb;
        
        $tables_map = array(
            'aicoagac_ideas' => 'aicoagac_ideas',
            'aicoagac_activity_logs' => 'aicoagac_activity_logs',
            'aca_content_updates' => 'aicoagac_content_updates',
            'aca_post_index' => 'aicoagac_post_index',
            'aca_post_style' => 'aicoagac_post_style',
        );
        
        foreach ($tables_map as $old_suffix => $new_suffix) {
            $old_table = $wpdb->prefix . $old_suffix;
            $new_table = $wpdb->prefix . $new_suffix;
            
            // Check if old table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $old_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $old_table));
            
            if ($old_exists) {
                // Check if new table exists
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $new_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $new_table));
                
                if (!$new_exists) {
                    // Rename table
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("RENAME TABLE `{$wpdb->_escape($old_table)}` TO `{$wpdb->_escape($new_table)}`");
                } else {
                    // If new table already exists, merge data
                    // This is a safety measure in case of partial migration
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("INSERT IGNORE INTO `{$wpdb->_escape($new_table)}` SELECT * FROM `{$wpdb->_escape($old_table)}`");
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
                    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->_escape($old_table)}`");
                }
            }
        }
    }
    
    /**
     * Migrate post meta keys
     */
    private static function migrate_post_meta() {
        global $wpdb;
        
        $meta_keys_map = array(
            '_aca_generated' => '_aicoagac_generated',
            '_aca_faq_jsonld' => '_aicoagac_faq_jsonld',
            '_aca_view_count' => '_aicoagac_view_count',
        );
        
        foreach ($meta_keys_map as $old_key => $new_key) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
                $new_key,
                $old_key
            ));
        }
    }
    
    /**
     * Clear old cron hooks
     */
    private static function clear_old_cron_hooks() {
        // Legacy hooks
        wp_clear_scheduled_hook('aca_thirty_minute_event');
        wp_clear_scheduled_hook('aca_fifteen_minute_event');
        
        // Simple automation hooks
        wp_clear_scheduled_hook('aca_simple_idea_generation');
        wp_clear_scheduled_hook('aca_simple_draft_creation');
        wp_clear_scheduled_hook('aca_simple_post_publishing');
        wp_clear_scheduled_hook('aca_simple_maintenance');
    }
    
    /**
     * Check if migration is needed
     */
    public static function is_migration_needed() {
        // Check if migration has been completed
        if (get_option('aicoagac_prefix_migration_completed')) {
            return false;
        }
        
        // Check if any old options exist
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $old_options = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'aca_%'"
        );
        
        if ($old_options > 0) {
            return true;
        }
        
        // Check if any old tables exist
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $old_tables = $wpdb->get_var(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = DATABASE() 
             AND table_name LIKE '{$wpdb->prefix}aca_%'"
        );
        
        if ($old_tables > 0) {
            return true;
        }
        
        // Check if any old post meta exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $old_meta = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aca_%'"
        );
        
        if ($old_meta > 0) {
            return true;
        }
        
        return false;
    }
}