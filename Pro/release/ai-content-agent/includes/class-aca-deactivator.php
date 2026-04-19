<?php
/**
 * Plugin deactivation functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACA_Deactivator {
    
    /**
     * Deactivation tasks
     */
    public static function deactivate() {
        // Clear all scheduled cron events
        wp_clear_scheduled_hook('aca_thirty_minute_event');
        wp_clear_scheduled_hook('aca_fifteen_minute_event');
        
        // Clear Simple Automation System hooks
        wp_clear_scheduled_hook('aca_simple_idea_generation');
        wp_clear_scheduled_hook('aca_simple_draft_creation');
        wp_clear_scheduled_hook('aca_simple_post_publishing');
        wp_clear_scheduled_hook('aca_simple_maintenance');
        
        // Clear any legacy Action Scheduler tasks if they exist
        if (function_exists('as_unschedule_all_actions')) {
            $legacy_hooks = array(
                'aca_generate_idea',
                'aca_create_draft', 
                'aca_publish_post',
                'aca_auto_style_guide',
                'aca_automation_scheduler'
            );
            
            foreach ($legacy_hooks as $hook) {
                as_unschedule_all_actions($hook);
            }
        }
        
        // Clean up automation-related options
        delete_option('aca_use_legacy_automation');
        delete_option('aca_last_scheduled_settings_hash');
        delete_option('aca_simple_automation_enabled');
        delete_transient('aca_last_scheduled_mode');
        delete_transient('aca_last_scheduled_settings_hash');
        
        aca_debug_log('ACA Deactivator: All automation schedules and options cleared');
    }
    
    /**
     * Clean temporary data only (preserved for backward compatibility)
     * Note: This method is no longer called during deactivation
     */
    private static function cleanup_plugin_data() {
        // Only clear temporary/cache data, preserve user settings

        delete_transient('aca_google_access_token');
        delete_transient('aca_token_refresh_lock');
        delete_transient('aca_thirty_minute_task_lock');
        delete_transient('aca_fifteen_minute_task_lock');
        delete_transient('aca_migration_check_done');
        

        
        aca_debug_log('Plugin temporary data cleaned up');
    }
    

}