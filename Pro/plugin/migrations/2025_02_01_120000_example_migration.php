<?php
/**
 * Example Migration - Add new column to ideas table
 * Version: 2.5.0
 * Date: 2025-02-01
 */

if (!defined('ABSPATH')) {
    exit;
}

return new class {
    
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        $table_name = $wpdb->prefix . 'aca_ideas';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Apply table schema via dbDelta (adds missing columns such as `priority`)
        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            status varchar(20) DEFAULT 'new' NOT NULL,
            priority int DEFAULT 1 NOT NULL,
            source varchar(20) DEFAULT 'ai' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";
        
        dbDelta($sql);
        
        return true;
    }
    
    /**
     * Reverse the migration
     */
    public function down() {
        // No-op: Column removals are not performed to avoid destructive schema changes
        return true;
    }
    
    /**
     * Get migration version
     */
    public function getVersion() {
        return '2.5.0';
    }
    
    /**
     * Get migration description
     */
    public function getDescription() {
        return 'Add priority column to ideas table for better content organization';
    }
};