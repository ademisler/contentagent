<?php
/**
 * ACA Migration Manager
 * 
 * Handles database migrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class AICOAGAC_Migration_Manager {
    
    const MIGRATION_VERSION_KEY = 'aicoagac_migration_version';
    const CURRENT_MIGRATION_VERSION = '3.0.0';
    
    /**
     * Run all pending migrations
     */
    public function run_migrations() {
        $current_version = get_option(self::MIGRATION_VERSION_KEY, '0.0.0');
        
        try {
            // Automation system removed from Free version
            
            // Update migration version
            update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_MIGRATION_VERSION);
            
            return true;
            
        } catch (Exception $e) {
            aicoagac_debug_log('Migration Error: ' . $e->getMessage());
            return new WP_Error('migration_failed', sprintf(
                /* translators: %s: error message */
                __('Migration failed: %s', 'ai-content-agent'),
                $e->getMessage()
            ));
        }
    }
    
    // Automation migration methods removed from Free version

    /**
     * Ensure indexes exist on aicoagac_post_index
     */
    private function ensure_post_index_indexes() {
        global $wpdb;
        $table = $wpdb->prefix . 'aicoagac_post_index';
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
                $result = $wpdb->query("ALTER TABLE `{$table}` ADD FULLTEXT `aicoagac_ft_title_summary_keywords` (`title`, `summary_1l`, `keywords`)"); // phpcs:ignore
                if ($result === false) {
                    // Ignore if not supported; fallback remains in code paths
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        aicoagac_debug_log('Migration: FULLTEXT index could not be added on aicoagac_post_index (likely unsupported).');
                    }
                }
            }
        }
    }
    
    // Legacy automation cleanup methods removed from Free version
    
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
            'migration_timestamp' => get_option('aicoagac_migration_timestamp', 'Never'),
            'unified_system_active' => false,
            'system_status' => __('Free Version', 'ai-content-agent')
        );
    }
}