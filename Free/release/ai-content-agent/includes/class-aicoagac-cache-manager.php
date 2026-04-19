<?php
/**
 * Cache Manager for AI Content Agent Plugin
 * 
 * Handles all cache operations including automatic and manual clearing
 */

if (!defined('ABSPATH')) {
    exit;
}

class AICOAGAC_Cache_Manager {
    
    /**
     * Cache clear triggers - which settings should trigger automatic cache clear
     */
    private static $cache_clear_triggers = array(

        'aicoagac_gemini_api_key',
        'aicoagac_google_cloud_project_id',
        'aicoagac_pexels_api_key',
        'aicoagac_unsplash_api_key',
        'aicoagac_pixabay_api_key',
        'aicoagac_auto_publish',
        'aicoagac_full_auto_daily_post_count',
        'aicoagac_full_auto_publish_frequency',
        'aicoagac_semi_auto_idea_frequency'
    );
    
    /**
     * Initialize cache manager
     */
    public static function init() {
        // Hook into option updates for automatic cache clearing
        foreach (self::$cache_clear_triggers as $option_name) {
            add_action("update_option_{$option_name}", array(__CLASS__, 'auto_clear_cache'), 10, 3);
        }
        
        // Add admin notices
        add_action('admin_notices', array(__CLASS__, 'show_cache_notices'));
    }
    
    /**
     * Clear all ACA-related caches
     * 
     * @param string $reason Reason for cache clear (for logging)
     * @param bool $full_flush Whether to do a full cache flush (default: false)
     * @return array Result with success status and message
     */
    public static function clear_all_caches($reason = 'Manual', $full_flush = false) {
        $cleared_items = array();
        
        try {
            // Prevent concurrent cache clears
            $lock_key = 'aicoagac_cache_clear_lock';
            $lock_value = get_transient($lock_key);
            
            if ($lock_value) {
                aicoagac_debug_log('Cache clear already in progress, skipping');
                return array(
                    'success' => false,
                    'message' => __( 'Cache clear already in progress. Please wait a moment and try again.', 'ai-content-agent' )
                );
            }
            
            // Set lock for 30 seconds
            set_transient($lock_key, time(), 30);
            
            // Check if Action Scheduler is running to prevent conflicts
            if (defined('AS_RUNNER_TICK') && AS_RUNNER_TICK) {
                delete_transient($lock_key);
                aicoagac_debug_log('Cache clear skipped - Action Scheduler is currently running');
                return array(
                    'success' => false,
                    'message' => __( 'Cache clear skipped while tasks are running. Please try again in a moment.', 'ai-content-agent' )
                );
            }
            
            // 1. Clear WordPress object cache (only if full flush requested)
            if ($full_flush && function_exists('wp_cache_flush')) {
                wp_cache_flush();
                $cleared_items[] = __( 'WordPress object cache (full)', 'ai-content-agent' );
            } else {
                // Selective cache clearing for better performance
                $cleared_items[] = __( 'ACA-specific caches', 'ai-content-agent' );
            }
            
            // 2. Clear specific option caches
            $option_caches = array(
                'aicoagac_license_timestamp',
                'cron',
                'alloptions'
            );
            
            foreach ($option_caches as $cache_key) {
                wp_cache_delete($cache_key, 'options');
            }
            $cleared_items[] = __( 'Option caches', 'ai-content-agent' );
            
            // 3. Clear all ACA transients
            global $wpdb;
            
            // Use separate queries for better performance with indexes
            $transient_count = 0;
            
            // Delete transient values
            $count1 = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Controlled cleanup of options
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                    WHERE option_name LIKE %s",
                    '_transient_aicoagac_%'
                )
            );
            
            // Delete transient timeouts
            $count2 = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Controlled cleanup of options
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                    WHERE option_name LIKE %s",
                    '_transient_timeout_aicoagac_%'
                )
            );
            
            $transient_count = $count1 + $count2;
            
            if ($transient_count > 0) {
                /* translators: %d: number of ACA transients deleted */
                $cleared_items[] = sprintf( __( '%d ACA transients', 'ai-content-agent' ), $transient_count );
            }
            
            // 4. Clear cron cache
            delete_transient('doing_cron');
            
            // REMOVED: Dangerous cron array update that could break scheduled events
            // Just clear the cache, don't manipulate the cron array directly
            wp_cache_delete('cron', 'options');
            $cleared_items[] = __( 'Cron cache', 'ai-content-agent' );
            
            // 5. Update assets version for browser cache busting
            update_option('aicoagac_assets_version', time());
            $cleared_items[] = __( 'Asset version updated', 'ai-content-agent' );
            
            // 6. Clear any external cache plugins if present
            if (function_exists('wp_cache_clear_cache')) {
                wp_cache_clear_cache();
                $cleared_items[] = __( 'External cache plugin', 'ai-content-agent' );
            }
            
            // 7. Update last cache clear time
            update_option('aicoagac_last_cache_clear', current_time('mysql'));
            
            // Log the cache clear
            aicoagac_debug_log("Cache cleared - Reason: {$reason}, Items: " . implode(', ', $cleared_items));
            
            // Set transient for success message
            set_transient('aicoagac_cache_cleared', array(
                'success' => true,
                'message' => __( 'Cache cleared successfully!', 'ai-content-agent' ),
                'items' => $cleared_items,
                'time' => current_time('mysql')
            ), 30);
            
            // Clear the lock
            delete_transient($lock_key);
            
            return array(
                'success' => true,
                'message' => __( 'All caches cleared successfully', 'ai-content-agent' ),
                'cleared_items' => $cleared_items
            );
            
        } catch (Exception $e) {
            // Clear the lock on error
            delete_transient('aicoagac_cache_clear_lock');
            
            aicoagac_debug_log('Cache clear failed: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => sprintf(
                    /* translators: %s: error message */
                    __( 'Cache clear failed: %s', 'ai-content-agent' ),
                    $e->getMessage()
                )
            );
        }
    }
    
    /**
     * Automatic cache clear on critical option updates
     */
    public static function auto_clear_cache($old_value, $new_value, $option) {
        // Only clear if value actually changed
        if ($old_value === $new_value) {
            return;
        }
        
        // Clear cache with specific reason
        self::clear_all_caches("Auto - {$option} updated");
        
        // Set notice for user
        set_transient('aicoagac_cache_auto_cleared', array(
            'option' => $option,
            'time' => current_time('mysql')
        ), 30);
    }
    
    /**
     * Show admin notices for cache operations
     */
    public static function show_cache_notices() {
        // Check for auto-clear notice
        $auto_cleared = get_transient('aicoagac_cache_auto_cleared');
        if ($auto_cleared) {
            delete_transient('aicoagac_cache_auto_cleared');
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php echo esc_html__( 'AI Content Agent', 'ai-content-agent' ); ?>:</strong> 
                    <?php echo esc_html__( 'Cache automatically cleared after updating settings.', 'ai-content-agent' ); ?>
                    <span class="description">(<?php echo esc_html($auto_cleared['time']); ?>)</span>
                </p>
            </div>
            <?php
        }
        
        // Check for manual clear notice
        $cache_cleared = get_transient('aicoagac_cache_cleared');
        if ($cache_cleared && $cache_cleared['success']) {
            delete_transient('aicoagac_cache_cleared');
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php echo esc_html__( 'AI Content Agent', 'ai-content-agent' ); ?>:</strong> 
                    <?php echo esc_html($cache_cleared['message']); ?>
                    <br>
                    <span class="description">
                        <?php echo esc_html__( 'Cleared:', 'ai-content-agent' ); ?> <?php echo esc_html(implode(', ', $cache_cleared['items'])); ?>
                    </span>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get cache status information
     */
    public static function get_cache_status() {
        global $wpdb;
        
        // Count ACA transients
        $transient_count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only status info
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_aicoagac_%',
                '_transient_timeout_aicoagac_%'
            )
        );
        
        // Check object cache status
        $object_cache_enabled = wp_using_ext_object_cache();
        
        // Get last clear time
        $last_clear = get_option('aicoagac_last_cache_clear', 'Never');
        
        return array(
            'transient_count' => $transient_count,
            'object_cache_enabled' => $object_cache_enabled,
            'last_clear' => $last_clear,
            'assets_version' => get_option('aicoagac_assets_version', '1.0')
        );
    }
}

// Initialize the cache manager
add_action('init', array('AICOAGAC_Cache_Manager', 'init'));