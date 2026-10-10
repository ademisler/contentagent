<?php
/**
 * Plugin deactivation functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class AICOAGAC_Deactivator {
    
    /**
     * Deactivation tasks
     */
    public static function deactivate() {
        // Clear all scheduled cron events
        wp_clear_scheduled_hook('aicoagac_thirty_minute_event');
        wp_clear_scheduled_hook('aicoagac_fifteen_minute_event');
        
        // Automation system removed from Free version
        
        aicoagac_debug_log('ACA Deactivator: Plugin deactivated');
    }
    
    /**
     * Clean temporary data only (preserved for backward compatibility)
     * Note: This method is no longer called during deactivation
     */
    private static function cleanup_plugin_data() {
        // Only clear temporary/cache data, preserve user settings

        delete_transient('aicoagac_google_access_token');
        delete_transient('aicoagac_token_refresh_lock');
        delete_transient('aicoagac_thirty_minute_task_lock');
        delete_transient('aicoagac_fifteen_minute_task_lock');
        delete_transient('aicoagac_migration_check_done');
        

        
        aicoagac_debug_log('Plugin temporary data cleaned up');
    }
    

}