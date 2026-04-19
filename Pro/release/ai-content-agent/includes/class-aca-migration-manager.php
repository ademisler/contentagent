<?php
/**
 * ACA Migration Manager
 * 
 * Handles migration from legacy Queue Manager to Simple Automation system
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACA_Migration_Manager {
    
    const MIGRATION_VERSION_KEY = 'aca_migration_version';
    const CURRENT_MIGRATION_VERSION = '3.0.0';
    
    /**
     * Run all pending migrations
     */
    public function run_migrations() {
        $current_version = get_option(self::MIGRATION_VERSION_KEY, '0.0.0');
        
        try {
            // Migration from legacy system to Simple Automation
            if (version_compare($current_version, '3.0.0', '<')) {
                $this->migrate_to_simple_automation();
                aca_debug_log('Migration: Successfully migrated to Simple Automation system');
            }
            
            // Update migration version
            update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION);
            
            return true;
            
        } catch (Exception $e) {
            aca_debug_log('Migration Error: ' . $e->getMessage());
            return new WP_Error('migration_failed', sprintf(
                /* translators: %s: error message */
                __('Migration failed: %s', 'ai-content-agent'),
                $e->getMessage()
            ));
        }
    }
    
    /**
     * Migrate from legacy Queue Manager to Simple Automation
     */
    private function migrate_to_simple_automation() {
        // 1. Migrate existing settings
        $this->migrate_automation_settings();
        
        // 2. Clear legacy cron jobs
        $this->clear_legacy_cron_jobs();
        
        // 3. Clear Action Scheduler tasks
        $this->clear_action_scheduler_tasks();
        
        // 4. Set migration flags
        $this->set_migration_flags();

        // 5. Add/ensure indexes on aca_post_index for scale
        $this->ensure_post_index_indexes();
    }

    /**
     * Ensure indexes exist on aca_post_index
     */
    private function ensure_post_index_indexes() {
        global $wpdb;
        $table = $wpdb->prefix . 'aca_post_index';
        // Ensure table exists first
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($exists !== $table) {
            return;
        }

        // Fetch existing indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $have_updated_at = false;
        $have_fulltext = false;
        if (is_array($indexes)) {
            foreach ($indexes as $idx) {
                $key_name = isset($idx['Key_name']) ? strtolower($idx['Key_name']) : '';
                $index_type = isset($idx['Index_type']) ? strtolower($idx['Index_type']) : '';
                if ($key_name === 'updated_at') { $have_updated_at = true; }
                if ($index_type === 'fulltext') { $have_fulltext = true; }
            }
        }

                // Add updated_at index if missing
        if (!$have_updated_at) {
            $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `updated_at` (`updated_at`)"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange
        }

        // Try to add FULLTEXT if supported and not present
        if (!$have_fulltext) {
            // Check storage engine and collation support heuristically
            $eng = $wpdb->get_var($wpdb->prepare("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s", $table)); // phpcs:ignore
            if (is_string($eng) && in_array(strtolower($eng), array('innodb','myisam'), true)) {
                // Attempt to add FULLTEXT on title, summary_1l, keywords (all TEXT)
                $result = $wpdb->query("ALTER TABLE `{$table}` ADD FULLTEXT `aca_ft_title_summary_keywords` (`title`, `summary_1l`, `keywords`)"); // phpcs:ignore
                if ($result === false) {
                    // Ignore if not supported; fallback remains in code paths
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aca_debug_log('Migration: FULLTEXT index could not be added on aca_post_index (likely unsupported).');
                    }
                }
            }
        }
    }
    
    /**
     * Migrate automation settings
     */
    private function migrate_automation_settings() {
        $existing_settings = get_option('aca_settings', array());
        
        // Preserve existing automation mode if set
        if (!isset($existing_settings['mode'])) {
            $existing_settings['mode'] = 'manual'; // Safe default
        }
        
        // Preserve other important settings
        $migration_settings = array_merge($existing_settings, array(
            'simple_automation_enabled' => true,
            'legacy_system_disabled' => true,
            'migration_completed' => current_time('mysql'),
            'migration_version' => self::CURRENT_MIGRATION_VERSION
        ));
        
        update_option('aca_settings', $migration_settings);
        aca_debug_log('Migration: Settings migrated successfully');
        
        // AGENTS.MD Step 5: Force reschedule automation tasks after migration
        // Migration always changes settings, so force rescheduling is appropriate
        if (class_exists('ACA_Simple_Automation')) {
            $automation = ACA_Simple_Automation::get_instance();
            $automation->force_reschedule_tasks();
            aca_debug_log('Migration: Forced automation task rescheduling after settings migration');
        }
    }
    
    /**
     * Clear legacy WordPress cron jobs
     */
    private function clear_legacy_cron_jobs() {
        // Clear legacy cron events
        wp_clear_scheduled_hook('aca_thirty_minute_event');
        wp_clear_scheduled_hook('aca_fifteen_minute_event');
        
        aca_debug_log('Migration: Legacy cron jobs cleared');
    }
    
    /**
     * Clear Action Scheduler tasks
     */
    private function clear_action_scheduler_tasks() {
        if (function_exists('as_unschedule_all_actions')) {
            // Clear ACA-related Action Scheduler tasks
            $aca_hooks = array(
                'aca_queue_idea_generation',
                'aca_queue_draft_creation',
                'aca_queue_post_publishing',
                'aca_queue_maintenance'
            );
            
            foreach ($aca_hooks as $hook) {
                as_unschedule_all_actions($hook);
            }
            
            aca_debug_log('Migration: Action Scheduler tasks cleared');
        }
    }
    
    /**
     * Clean up legacy migration flags (Unified System)
     */
    private function set_migration_flags() {
        // Clean up any legacy system options
        delete_option('aca_use_legacy_cron');
        delete_option('aca_use_action_scheduler');
        delete_option('aca_use_legacy_automation');
        delete_option('aca_simple_automation_enabled');
        
        // Set migration timestamp for reference
        update_option('aca_migration_timestamp', current_time('mysql'));
        
        aca_debug_log('Migration: Legacy options cleaned up for unified system');
    }
    
    /**
     * Check if migration is needed
     */
    public function is_migration_needed() {
        $current_version = get_option(self::MIGRATION_VERSION_KEY, '0.0.0');
        return version_compare($current_version, self::CURRENT_MIGRATION_VERSION, '<');
    }
    
    /**
     * Get migration status
     */
    public function get_migration_status() {
        return array(
            'current_version' => get_option(self::MIGRATION_VERSION_KEY, '0.0.0'),
            'target_version' => self::CURRENT_MIGRATION_VERSION,
            'migration_needed' => $this->is_migration_needed(),
            'migration_timestamp' => get_option('aca_migration_timestamp', 'Never'),
            'unified_system_active' => class_exists('ACA_Simple_Automation'),
            'system_status' => __('Unified & Reliable', 'ai-content-agent')
        );
    }
}