<?php
/**
 * Plugin uninstall functionality
 * Fired when the plugin is deleted from WordPress admin
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Check if user opted to delete data on uninstall
$settings = get_option('aca_settings', array());
$delete_data = isset($settings['delete_data_on_uninstall']) && $settings['delete_data_on_uninstall'];

if (!$delete_data) {
    // User chose to preserve data - only clear temporary data

    delete_transient('aca_google_access_token');
    delete_transient('aca_token_refresh_lock');
    delete_transient('aca_thirty_minute_task_lock');
    delete_transient('aca_fifteen_minute_task_lock');
    delete_transient('aca_scheduling_lock');
    delete_transient('aca_scheduling_cooldown');
    delete_transient('aca_force_reschedule');

    // Clear all scheduled cron hooks (legacy)
    wp_clear_scheduled_hook('aca_thirty_minute_event');
    wp_clear_scheduled_hook('aca_fifteen_minute_event');

    // Clear unified simple automation hooks (no data deletion)
    if (class_exists('ACA_Simple_Automation')) {
        wp_clear_scheduled_hook(ACA_Simple_Automation::HOOK_IDEA_GENERATION);
        wp_clear_scheduled_hook(ACA_Simple_Automation::HOOK_DRAFT_CREATION);
        wp_clear_scheduled_hook(ACA_Simple_Automation::HOOK_POST_PUBLISHING);
        wp_clear_scheduled_hook(ACA_Simple_Automation::HOOK_MAINTENANCE);
    } else {
        // Fallback by hook names
        wp_clear_scheduled_hook('aca_simple_idea_generation');
        wp_clear_scheduled_hook('aca_simple_draft_creation');
        wp_clear_scheduled_hook('aca_simple_post_publishing');
        wp_clear_scheduled_hook('aca_simple_maintenance');
    }

    // Log preservation
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ACA: Plugin uninstalled - user data preserved as requested'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Uninstall logging
    }
    return;
}

// User opted to delete all data - proceed with full cleanup
// Delete all plugin options
delete_option('aca_settings');
delete_option('aca_style_guide');
delete_option('aca_google_auth_token');

delete_option('aca_license_status');
delete_option('aca_license_data');
delete_option('aca_license_site_hash');
delete_option('aca_license_key');
delete_option('aca_license_verified');

delete_option('aca_db_version');
delete_option('aca_migration_log');
delete_option('aca_last_scheduled_settings_hash');

// Clear all scheduled cron hooks (legacy)
wp_clear_scheduled_hook('aca_thirty_minute_event');
wp_clear_scheduled_hook('aca_fifteen_minute_event');

// Clear unified simple automation hooks
wp_clear_scheduled_hook('aca_simple_idea_generation');
wp_clear_scheduled_hook('aca_simple_draft_creation');
wp_clear_scheduled_hook('aca_simple_post_publishing');
wp_clear_scheduled_hook('aca_simple_maintenance');

// Drop custom database tables
$tables_to_drop = array(
    $wpdb->prefix . 'aca_ideas',
    $wpdb->prefix . 'aca_activity_logs',
    $wpdb->prefix . 'aca_content_updates',
);

// Drop custom tables (direct queries required for uninstall)
foreach ($tables_to_drop as $table) {
    // Tables listed above are plugin-owned; safe to drop
    $safe_table = esc_sql($table);
    $drop = 'DROP TABLE IF EXISTS `' . $safe_table . '`';
    $wpdb->query($drop); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared -- Identifier only; uninstall schema cleanup
}

// Optionally delete plugin post meta keys
$meta_keys = array(
    '_aca_generated',
    '_aca_faq_jsonld',
    '_aca_view_count',
);
foreach ($meta_keys as $meta_key) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup
}

// Clean up any remaining plugin data (options starting with aca_)
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'aca_%')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup

// Log uninstall completion
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('ACA: Plugin completely uninstalled and all data removed as requested'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Uninstall logging
}