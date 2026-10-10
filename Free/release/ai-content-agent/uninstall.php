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
$settings = get_option('aicoagac_settings', array());
$delete_data = isset($settings['delete_data_on_uninstall']) && $settings['delete_data_on_uninstall'];

if (!$delete_data) {
    // User chose to preserve data - only clear temporary data

    delete_transient('aicoagac_google_access_token');
    delete_transient('aicoagac_token_refresh_lock');
    delete_transient('aicoagac_thirty_minute_task_lock');
    delete_transient('aicoagac_fifteen_minute_task_lock');
    delete_transient('aicoagac_scheduling_lock');
    delete_transient('aicoagac_scheduling_cooldown');
    delete_transient('aicoagac_force_reschedule');

    // Clear all scheduled cron hooks (legacy)
    wp_clear_scheduled_hook('aicoagac_thirty_minute_event');
    wp_clear_scheduled_hook('aicoagac_fifteen_minute_event');

    // Automation system removed from Free version

    // Log preservation
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('ACA: Plugin uninstalled - user data preserved as requested'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Uninstall logging
    }
    return;
}

// User opted to delete all data - proceed with full cleanup
// Delete all plugin options
delete_option('aicoagac_settings');
delete_option('aicoagac_style_guide');
delete_option('aicoagac_google_auth_token');

// License options removed - plugin is now 100% free

delete_option('aicoagac_db_version');
delete_option('aicoagac_migration_log');
delete_option('aicoagac_last_scheduled_settings_hash');

// Clear all scheduled cron hooks (legacy)
wp_clear_scheduled_hook('aicoagac_thirty_minute_event');
wp_clear_scheduled_hook('aicoagac_fifteen_minute_event');

// Automation system removed from Free version

// Drop custom database tables
$tables_to_drop = array(
    $wpdb->prefix . 'aicoagac_ideas',
    $wpdb->prefix . 'aicoagac_activity_logs',
    $wpdb->prefix . 'aicoagac_content_updates',
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
    '_aicoagac_generated',
    '_aicoagac_faq_jsonld',
    '_aicoagac_view_count',
);
foreach ($meta_keys as $meta_key) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup
}

// Clean up any remaining plugin data (options starting with aicoagac_)
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'aicoagac_%')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Required for uninstall cleanup

// Log uninstall completion
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('ACA: Plugin completely uninstalled and all data removed as requested'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Uninstall logging
}