<?php
/**
 * Database Repair Script for AI Content Agent
 * 
 * This script manually creates missing tables and columns.
 * Run this if you're getting "Table doesn't exist" errors.
 */

// Security check
if (!defined('ABSPATH')) {
    // If not in WordPress context, try to load WordPress
    $wp_config_path = dirname(__FILE__) . '/../../../../wp-config.php';
    if (file_exists($wp_config_path)) {
        require_once $wp_config_path;
    } else {
        die( esc_html__( 'WordPress not found. Please run this script from WordPress admin or place in plugin directory.', 'ai-content-agent' ) );
    }
}

// Ensure we have database access
if (!isset($wpdb)) {
    global $wpdb;
}

// Allow only administrators in web context
if (function_exists('is_user_logged_in')) {
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'Insufficient permissions', 'ai-content-agent' ) );
    }
}

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

echo '<h2>' . esc_html__( 'AI Content Agent - Database Repair', 'ai-content-agent' ) . "</h2>\n";
echo "<pre>\n";

$charset_collate = $wpdb->get_charset_collate();
$errors = array();
$success = array();

// 1. Create Ideas Table
$ideas_table_name = $wpdb->prefix . 'aca_ideas';
echo esc_html__( 'Checking ideas table:', 'ai-content-agent' ) . ' ' . esc_html($ideas_table_name) . "\n";

$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $ideas_table_name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Safe existence check during repair tool
if (!$table_exists) {
    echo esc_html__( 'Creating ideas table...', 'ai-content-agent' ) . "\n";
    $sql_ideas = "CREATE TABLE {$ideas_table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title text NOT NULL,
        status varchar(20) DEFAULT 'new' NOT NULL,
        priority int DEFAULT 1 NOT NULL,
        source varchar(20) DEFAULT 'ai' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";
    dbDelta($sql_ideas); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Expected schema change in repair tool
    $success[] = __( '✅ Ideas table created/updated', 'ai-content-agent' );
} else {
    echo esc_html__( 'Ideas table exists, ensuring schema...', 'ai-content-agent' ) . "\n";
    $sql_ideas = "CREATE TABLE {$ideas_table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title text NOT NULL,
        status varchar(20) DEFAULT 'new' NOT NULL,
        priority int DEFAULT 1 NOT NULL,
        source varchar(20) DEFAULT 'ai' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";
    dbDelta($sql_ideas); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Expected schema change in repair tool
    $success[] = __( '✅ Ideas table schema verified', 'ai-content-agent' );
}

// 2. Create Activity Logs Table
$logs_table_name = $wpdb->prefix . 'aca_activity_logs';
echo "\n" . esc_html__( 'Checking activity logs table:', 'ai-content-agent' ) . ' ' . esc_html($logs_table_name) . "\n";

$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $logs_table_name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
if (!$table_exists) {
    echo esc_html__( 'Creating activity logs table...', 'ai-content-agent' ) . "\n";
    $sql_logs = "CREATE TABLE {$logs_table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        type varchar(50) NOT NULL,
        details text NOT NULL,
        icon varchar(50) NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";
    dbDelta($sql_logs); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $success[] = __( '✅ Activity logs table created/updated', 'ai-content-agent' );
} else {
    $success[] = __( '✅ Activity logs table exists', 'ai-content-agent' );
    // Ensure schema
    $sql_logs = "CREATE TABLE {$logs_table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp datetime NOT NULL,
        type varchar(50) NOT NULL,
        details text NOT NULL,
        icon varchar(50) NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";
    dbDelta($sql_logs); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
}

// 3. Create Content Updates Table
$content_updates_table_name = $wpdb->prefix . 'aca_content_updates';
echo "\n" . esc_html__( 'Checking content updates table:', 'ai-content-agent' ) . ' ' . esc_html($content_updates_table_name) . "\n";

$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $content_updates_table_name)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
if (!$table_exists) {
    echo esc_html__( 'Creating content updates table...', 'ai-content-agent' ) . "\n";
    $sql_content_updates = "CREATE TABLE {$content_updates_table_name} (
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
    ) {$charset_collate};";
    dbDelta($sql_content_updates); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
    $success[] = __( '✅ Content updates table created/updated', 'ai-content-agent' );
} else {
    $success[] = __( '✅ Content updates table exists', 'ai-content-agent' );
    // Ensure schema
    $sql_content_updates = "CREATE TABLE {$content_updates_table_name} (
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
    ) {$charset_collate};";
    dbDelta($sql_content_updates); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
}

