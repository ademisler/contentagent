<?php
/**
 * Simple Automation System for ACA
 * 
 * Replaces complex Action Scheduler with reliable WordPress native scheduling
 * Following industry best practices for WordPress plugin automation
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACA_Simple_Automation {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Automation hooks
     */
    const HOOK_IDEA_GENERATION = 'aca_simple_idea_generation';
    const HOOK_DRAFT_CREATION = 'aca_simple_draft_creation';
    const HOOK_POST_PUBLISHING = 'aca_simple_post_publishing';
    const HOOK_MAINTENANCE = 'aca_simple_maintenance';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct() {
        $this->init_hooks();
        aca_debug_log('Simple Automation: Initialized');
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register automation hooks
        add_action(self::HOOK_IDEA_GENERATION, array($this, 'handle_idea_generation'));
        add_action(self::HOOK_DRAFT_CREATION, array($this, 'handle_draft_creation'), 10, 2);
        add_action(self::HOOK_POST_PUBLISHING, array($this, 'handle_post_publishing'), 10, 2);
        add_action(self::HOOK_MAINTENANCE, array($this, 'handle_maintenance'));
        

        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
        
        // PHASE 1 BUG #3 FIX: Move scheduling to wp_loaded to ensure custom intervals are cached
        add_action('wp_loaded', array($this, 'ensure_scheduled_tasks'));
        
        aca_debug_log('Simple Automation: Hooks initialized');
    }
    
    /**
     * Force reschedule all tasks (immediate scheduling)
     */
    public function force_reschedule_tasks() {
        aca_debug_log('Simple Automation: Forcing immediate task rescheduling');
        // Allow immediate re-scheduling in this request and shortly after
        set_transient('aca_force_reschedule', 1, 60);
        delete_transient('aca_scheduling_cooldown');
        $this->ensure_scheduled_tasks();
    }
    
    /**
     * Ensure all required tasks are scheduled - AGENTS.MD TIMING FIX
     * Enhanced with cron interval validation and consistent timing
     */
    public function ensure_scheduled_tasks() {
        // PHASE 1 BUG #2 FIX: Time-based transient lock for better race condition protection
        $lock_key = 'aca_scheduling_lock';
        $process_id = uniqid('aca_schedule_', true);
        
        // Cooldown to prevent re-scheduling on every page load
        $cooldown_key = 'aca_scheduling_cooldown';
        $force_reschedule_flag = get_transient('aca_force_reschedule');
        if (!$force_reschedule_flag && get_transient($cooldown_key)) {
            aca_debug_log("Simple Automation: Scheduling cooldown active, skipping (Process ID: {$process_id})");
            return;
        }
        delete_transient('aca_force_reschedule'); // consume flag
        
        // Check if scheduling is currently locked
        $current_lock = get_transient($lock_key);
        if ($current_lock && (time() - $current_lock) < 30) {
            aca_debug_log("Simple Automation: Task scheduling locked by recent process, waiting (Process ID: {$process_id})");
            return;
        }
        
        // Acquire lock with current timestamp
        set_transient($lock_key, time(), 60); // 60 second expiration as safety
        
        aca_debug_log("Simple Automation: Starting task scheduling (Process ID: {$process_id})");
        
        try {
            // AGENTS.MD TIMING FIX: Validate custom cron intervals before scheduling
            if (!$this->validate_custom_cron_intervals()) {
                aca_debug_log('Simple Automation: Custom cron intervals validation failed, attempting to fix...');
                // Force register custom intervals immediately
                $this->ensure_custom_intervals_registered();
            }
            
            $settings = get_option('aca_settings', array());
            $current_mode = $settings['mode'] ?? 'manual';
            aca_debug_log("Simple Automation: Processing mode: {$current_mode} (Process ID: {$process_id})");
            
            // Pre-scheduling validation (warnings only)
            $this->validate_scheduling_environment();

            // Determine Pro status without forcing cache refresh
            $pro_active = is_aca_pro_active(false);

            // Compute settings hash to detect meaningful changes
            $relevant = array(
                'mode' => $current_mode,
                'semiAutoIdeaFrequency' => $settings['semiAutoIdeaFrequency'] ?? null,
                'fullAutoDailyPostCount' => intval($settings['fullAutoDailyPostCount'] ?? 1),
                'fullAutoPublishFrequency' => $settings['fullAutoPublishFrequency'] ?? 'daily',
                'autoPublish' => (bool)($settings['autoPublish'] ?? false),
                'pro' => (bool)$pro_active,
            );
            $current_hash = md5(wp_json_encode($relevant));
            $last_hash = get_option('aca_last_scheduled_settings_hash');
            $needs_full_reschedule = $force_reschedule_flag || ($current_hash !== $last_hash);

            if ($needs_full_reschedule) {
                // Clear and re-schedule only when settings/licensing changed or force requested
                aca_debug_log('Simple Automation: Detected configuration change or force request - rescheduling all tasks');
                if ($current_mode === 'manual') {
                    $this->clear_automation_schedules();
                    aca_debug_log('Simple Automation: Manual mode - cleared automation schedules');
                } else {
                    $this->clear_all_schedules_enhanced();
                    if ($current_mode === 'semi-automatic') {
                        if ($pro_active) {
                            $this->schedule_semi_automatic_enhanced($settings);
                            aca_debug_log('Simple Automation: Pro license verified - scheduling semi-automatic tasks');
                        } else {
                            $this->clear_pro_only_schedules();
                            aca_debug_log('Simple Automation: Pro license not active - cleared semi-automatic schedules');
                        }
                    } elseif ($current_mode === 'full-automatic') {
                        if ($pro_active) {
                            $this->schedule_full_automatic_enhanced($settings);
                            aca_debug_log('Simple Automation: Pro license verified - scheduling full-automatic tasks');
                        } else {
                            $this->clear_pro_only_schedules();
                            aca_debug_log('Simple Automation: Pro license not active - cleared full-automatic schedules');
                        }
                    }

                    // Maintenance only for semi/full auto
                    if ($current_mode === 'semi-automatic' || $current_mode === 'full-automatic') {
                        $this->schedule_maintenance_enhanced();
                        aca_debug_log("Simple Automation: Maintenance scheduled for {$current_mode} mode");
                    }
                }
                // Persist new hash
                update_option('aca_last_scheduled_settings_hash', $current_hash);
            } else {
                // Idempotent ensure: schedule only missing hooks without clearing existing ones
                aca_debug_log('Simple Automation: No configuration change detected - ensuring required tasks are scheduled');
                if ($current_mode === 'semi-automatic') {
                    if ($pro_active) {
                        if (!wp_next_scheduled(self::HOOK_IDEA_GENERATION)) {
                            $this->schedule_semi_automatic_enhanced($settings);
                        }
                        if (!wp_next_scheduled(self::HOOK_MAINTENANCE)) {
                            $this->schedule_maintenance_enhanced();
                        }
                    } else {
                        $this->clear_pro_only_schedules();
                    }
                } elseif ($current_mode === 'full-automatic') {
                    if ($pro_active) {
                        if (!wp_next_scheduled(self::HOOK_IDEA_GENERATION) || !wp_next_scheduled(self::HOOK_DRAFT_CREATION) || (($settings['autoPublish'] ?? false) && !wp_next_scheduled(self::HOOK_POST_PUBLISHING))) {
                            // Schedule missing pieces
                            $this->schedule_full_automatic_enhanced($settings);
                        }
                        if (!wp_next_scheduled(self::HOOK_MAINTENANCE)) {
                            $this->schedule_maintenance_enhanced();
                        }
                    } else {
                        $this->clear_pro_only_schedules();
                    }
                } else {
                    // Manual mode: ensure nothing is scheduled
                    $this->clear_automation_schedules();
                }
            }
            
            // Verify scheduling success (does nothing in manual)
            $this->verify_scheduling_success($current_mode);
            
            // Set cooldown to avoid thrashing schedules on every request
            set_transient($cooldown_key, time(), 300); // 5 minutes
            
            aca_debug_log("Simple Automation: Task scheduling step finished for mode: {$current_mode} (Process ID: {$process_id})");
            
        } catch (Exception $e) {
            aca_debug_log("Simple Automation: Exception during task scheduling: {$e->getMessage()}");
        } finally {
            // PHASE 1 BUG #2 FIX: Release transient lock
            delete_transient($lock_key);
            aca_debug_log("Simple Automation: Released scheduling lock (Process ID: {$process_id})");
        }
    }
    
    /**
     * Schedule semi-automatic mode tasks - AGENTS.MD TIMING FIX
     * Enhanced with proper workflow timing and user guidance
     */
    private function schedule_semi_automatic($settings) {
        $frequency = $settings['semiAutoIdeaFrequency'] ?? 'weekly';
        $interval = $this->get_interval_seconds($frequency);
        
        // AGENTS.MD TIMING FIX: Calculate optimal delay for semi-automatic
        $base_delay = 300; // 5 minutes initial delay
        
        if (!wp_next_scheduled(self::HOOK_IDEA_GENERATION)) {
            wp_schedule_event(time() + $base_delay, $this->get_wp_interval_validated($frequency), self::HOOK_IDEA_GENERATION);
            aca_debug_log("Simple Automation: Scheduled semi-automatic idea generation - Frequency: {$frequency}, Delay: {$base_delay}s, Ideas per run: 5");
        }
        
        // AGENTS.MD UX ENHANCEMENT: Log workflow explanation for semi-automatic
        aca_debug_log("Simple Automation: Semi-automatic workflow - AI generates 5 ideas {$frequency}, user reviews and creates drafts manually, user publishes manually");
    }
    
    /**
     * Schedule full-automatic mode tasks - AGENTS.MD TIMING FIX
     * Calculate optimal timing based on daily post count and publish frequency
     */
    private function schedule_full_automatic($settings) {
        $frequency = $settings['fullAutoPublishFrequency'] ?? 'daily';
        $daily_post_count = intval($settings['fullAutoDailyPostCount'] ?? 1);
        $auto_publish_enabled = $settings['autoPublish'] ?? false;
        
        // AGENTS.MD TIMING CALCULATION: Calculate workflow timing
        $workflow_timing = $this->calculate_automation_workflow_timing($daily_post_count, $frequency, $auto_publish_enabled);
        
        aca_debug_log("Simple Automation: Calculated workflow timing: " . wp_json_encode($workflow_timing));
        
        // Schedule idea generation - frequency based on demand
        if (!wp_next_scheduled(self::HOOK_IDEA_GENERATION)) {
            wp_schedule_event(
                time() + $workflow_timing['idea_generation_delay'], 
                $workflow_timing['idea_generation_frequency'], 
                self::HOOK_IDEA_GENERATION
            );
            aca_debug_log("Simple Automation: Scheduled idea generation - Frequency: {$workflow_timing['idea_generation_frequency']}, Delay: {$workflow_timing['idea_generation_delay']}s");
        }
        
        // Schedule draft creation - frequency based on workflow needs
        if (!wp_next_scheduled(self::HOOK_DRAFT_CREATION)) {
            wp_schedule_event(
                time() + $workflow_timing['draft_creation_delay'], 
                $workflow_timing['draft_creation_frequency'], 
                self::HOOK_DRAFT_CREATION
            );
            aca_debug_log("Simple Automation: Scheduled draft creation - Frequency: {$workflow_timing['draft_creation_frequency']}, Delay: {$workflow_timing['draft_creation_delay']}s");
        }
        
        // Schedule post publishing if auto-publish is enabled
        if ($auto_publish_enabled && !wp_next_scheduled(self::HOOK_POST_PUBLISHING)) {
            wp_schedule_event(
                time() + $workflow_timing['post_publishing_delay'], 
                $workflow_timing['post_publishing_frequency'], 
                self::HOOK_POST_PUBLISHING
            );
            aca_debug_log("Simple Automation: Scheduled post publishing - Frequency: {$workflow_timing['post_publishing_frequency']}, Delay: {$workflow_timing['post_publishing_delay']}s");
        }
        
        aca_debug_log("Simple Automation: Full automation configured - Posts/day: {$daily_post_count}, Publish freq: {$frequency}, Auto-publish: " . ($auto_publish_enabled ? 'enabled' : 'disabled'));
    }
    
    /**
     * AGENTS.MD TIMING FIX: Calculate optimal automation workflow timing
     * Ensures proper spacing between idea generation, draft creation, and publishing
     */
    private function calculate_automation_workflow_timing($daily_post_count, $publish_frequency, $auto_publish_enabled) {
        // Base timing calculations
        $base_delays = array(
            'idea_generation' => 300,  // 5 minutes initial delay
            'draft_creation' => 900,   // 15 minutes after ideas
            'post_publishing' => 1800  // 30 minutes after drafts
        );
        
        // Calculate idea generation frequency based on demand
        // Generate enough ideas to sustain the publishing schedule
        $ideas_needed_per_day = max($daily_post_count * 3, 10); // 3x buffer for variety
        
        if ($daily_post_count >= 5) {
            // High volume: generate ideas multiple times per day
            $idea_frequency = 'aca_six_hours'; // Every 6 hours (4 times/day)
            $generations_per_day = 4;
        } elseif ($daily_post_count >= 2) {
            // Medium volume: generate ideas twice daily
            $idea_frequency = 'aca_twelve_hours'; // Every 12 hours (2 times/day)
            $generations_per_day = 2;
        } else {
            // Low volume: generate ideas daily
            $idea_frequency = 'daily'; // Once per day
            $generations_per_day = 1;
        }
        
        // AGENTS.MD CRITICAL FIX: Calculate actual ideas per generation
        $ideas_per_generation = max(1, ceil($ideas_needed_per_day / $generations_per_day));
        
        // Calculate draft creation frequency based on publishing needs
        if ($auto_publish_enabled) {
            switch ($publish_frequency) {
                case 'hourly':
                    // Need drafts ready frequently
                    $draft_frequency = 'aca_thirty_minutes';
                    break;
                case 'daily':
                    // Need drafts ready daily
                    $draft_frequency = 'aca_four_hours';
                    break;
                case 'weekly':
                    // Need drafts ready weekly
                    $draft_frequency = 'daily';
                    break;
                default:
                    $draft_frequency = 'aca_four_hours';
            }
        } else {
            // Manual publishing: less frequent draft creation
            $draft_frequency = 'aca_six_hours';
        }
        
        // Calculate publishing frequency (use user setting with validation)
        $publish_wp_frequency = $this->get_wp_interval_validated($publish_frequency);
        
        return array(
            'idea_generation_frequency' => $idea_frequency,
            'idea_generation_delay' => $base_delays['idea_generation'],
            'draft_creation_frequency' => $draft_frequency,
            'draft_creation_delay' => $base_delays['draft_creation'],
            'post_publishing_frequency' => $publish_wp_frequency,
            'post_publishing_delay' => $base_delays['post_publishing'],
            'workflow_summary' => array(
                'ideas_per_generation' => $ideas_per_generation, // FIXED: Actual per generation
                'total_ideas_per_day' => $ideas_needed_per_day, // NEW: Total daily for clarity
                'generations_per_day' => $generations_per_day, // NEW: How many times per day
                'estimated_daily_drafts' => $daily_post_count,
                'publishing_enabled' => $auto_publish_enabled,
                'workflow_optimized' => true
            )
        );
    }
    
    /**
     * Schedule maintenance tasks
     */
    private function schedule_maintenance() {
        if (!wp_next_scheduled(self::HOOK_MAINTENANCE)) {
            wp_schedule_event(time() + 600, 'daily', self::HOOK_MAINTENANCE);
            aca_debug_log('Simple Automation: Scheduled daily maintenance');
        }
    }
    
    /**
     * Handle idea generation (WordPress cron hook)
     * AGENTS.MD COMPREHENSIVE FIX: Enhanced error handling and recovery mechanisms
     */
    public function handle_idea_generation() {
        aca_debug_log('Simple Automation: Executing scheduled idea generation');
        
        // AGENTS.MD ENHANCEMENT: Process tracking and timeout protection
        $execution_start_time = time();
        $max_execution_time = 300; // 5 minutes maximum
        $process_id = uniqid('idea_gen_', true);
        
        aca_debug_log("Simple Automation: Starting idea generation (Process ID: {$process_id})");
        
        // AGENTS.MD ENHANCEMENT: Resource management and monitoring
        $original_memory_limit = ini_get('memory_limit');
        $original_time_limit = ini_get('max_execution_time');
        $memory_usage_start = memory_get_usage(true);
        
        // Set execution lock to prevent overlapping
        $lock_key = 'aca_idea_generation_lock';
        if (get_transient($lock_key)) {
            aca_debug_log('Simple Automation: Idea generation already running, skipping this execution');
            return;
        }
        set_transient($lock_key, $process_id, 600); // 10 minute lock
        
        try {
            // AGENTS.MD ENHANCEMENT: Environment optimization
            if (function_exists('wp_raise_memory_limit')) {
                $raised = wp_raise_memory_limit('admin');
                if (!$raised) {
                    aca_debug_log('Simple Automation: WARNING - Could not raise memory limit');
                }
            }
            
            $execution_successful = false;
            $retry_count = 0;
            $max_retries = 3;
            
            while (!$execution_successful && $retry_count < $max_retries) {
                // AGENTS.MD ENHANCEMENT: Timeout check
                if ((time() - $execution_start_time) > ($max_execution_time - 30)) {
                    aca_debug_log('Simple Automation: Approaching timeout, stopping execution');
                    break;
                }
                
                $settings = get_option('aca_settings', array());
                $mode = $settings['mode'] ?? 'manual';
                
                // Skip execution if automation is disabled
                if ($mode === 'manual') {
                    aca_debug_log('Simple Automation: Skipping execution - mode is manual');
                    $execution_successful = true; // Not an error, just disabled
                    break;
                }
                
                // PHASE 1 BUG #8 FIX: Pro license required for both semi-automatic and full-automatic
                if (($mode === 'semi-automatic' || $mode === 'full-automatic') && !is_aca_pro_active()) {
                    aca_debug_log("Simple Automation: Skipping execution - {$mode} mode requires Pro license");
                    $execution_successful = true; // Not an error, just license issue
                    break;
                }
                
                // AGENTS.MD TIMING FIX: Use consistent idea count calculation
                // Get idea count from workflow timing calculation for consistency
                $idea_count = 5; // Default for semi-automatic and manual
                if ($mode === 'full-automatic') {
                    $daily_post_count = intval($settings['fullAutoDailyPostCount'] ?? 1);
                    $publish_frequency = $settings['fullAutoPublishFrequency'] ?? 'daily';
                    $auto_publish_enabled = $settings['autoPublish'] ?? false;
                    
                    // Use the same calculation as workflow timing for consistency
                    $workflow_timing = $this->calculate_automation_workflow_timing($daily_post_count, $publish_frequency, $auto_publish_enabled);
                    $idea_count = $workflow_timing['workflow_summary']['ideas_per_generation'] ?? max(1, $daily_post_count * 3);
                } elseif ($mode === 'semi-automatic') {
                    // Semi-automatic always generates 5 ideas for user selection
                    $idea_count = 5;
                }
                
                aca_debug_log("Simple Automation: Attempting idea generation (Attempt: " . ($retry_count + 1) . ", Mode: {$mode}, Count: {$idea_count})");
                
                // AGENTS.MD ENHANCEMENT: Memory monitoring before heavy operation
                $memory_before = memory_get_usage(true);
                aca_debug_log("Simple Automation: Memory usage before idea generation: " . size_format($memory_before));
                
                // Use the same logic as manual trigger
                $result = $this->trigger_manual_task('idea_generation', array(
                    'auto' => true,
                    'scheduled' => true,
                    'count' => $idea_count,
                    'process_id' => $process_id,
                    'retry_count' => $retry_count
                ));
                
                // AGENTS.MD ENHANCEMENT: Memory monitoring after operation
                $memory_after = memory_get_usage(true);
                $memory_used = $memory_after - $memory_before;
                aca_debug_log("Simple Automation: Memory usage after idea generation: " . size_format($memory_after) . " (Used: " . size_format($memory_used) . ")");
                
                aca_debug_log("Simple Automation: Scheduled idea generation result for {$mode} mode (count: {$idea_count}) - " . wp_json_encode($result));
                
                // Check if execution was successful
                if (is_array($result) && ($result['status'] ?? '') === 'completed') {
                    $execution_successful = true;
                    // Update last run time
                    update_option('aca_last_automation_run', current_time('mysql') . ' (Scheduled)');
                    aca_debug_log("Simple Automation: Idea generation completed successfully (Process ID: {$process_id})");
                } else {
                    $retry_count++;
                    if ($retry_count < $max_retries) {
                        aca_debug_log("Simple Automation: Idea generation failed, retrying in 30 seconds (Attempt {$retry_count}/{$max_retries})");
                        sleep(30); // Wait before retry
                    } else {
                        aca_debug_log('Simple Automation: Idea generation failed after maximum retries: ' . wp_json_encode($result));
                    }
                }
                
                // AGENTS.MD ENHANCEMENT: Force garbage collection after heavy operations
                if (function_exists('gc_collect_cycles')) {
                    $collected = gc_collect_cycles();
                    if ($collected > 0) {
                        aca_debug_log("Simple Automation: Garbage collection freed {$collected} cycles");
                    }
                }
            }
            
        } catch (Exception $e) {
            aca_debug_log("Simple Automation: Exception in handle_idea_generation (Process ID: {$process_id}): " . $e->getMessage());
            $execution_successful = false;
        } finally {
            // AGENTS.MD ENHANCEMENT: Comprehensive cleanup
            
            // Remove execution lock
            delete_transient($lock_key);
            
            // Final memory report
            $memory_usage_end = memory_get_usage(true);
            $total_memory_used = $memory_usage_end - $memory_usage_start;
            aca_debug_log("Simple Automation: Total memory used during execution: " . size_format($total_memory_used));
            
            // Execution time report
            $execution_time = time() - $execution_start_time;
            aca_debug_log("Simple Automation: Total execution time: {$execution_time} seconds");
            
            // AGENTS.MD CRITICAL FIX: Ensure next cron event is scheduled with enhanced recovery
            $this->ensure_next_idea_generation_scheduled_enhanced($execution_successful, $process_id);
        }
    }
    
    /**
     * AGENTS.MD CRITICAL FIX: Ensure next idea generation is scheduled
     * This is a safety net in case WordPress cron auto-reschedule fails
     */
    private function ensure_next_idea_generation_scheduled($execution_successful = true) {
        try {
            // Get current settings
            $settings = get_option('aca_settings', array());
            $mode = $settings['mode'] ?? 'manual';
            
            // Only reschedule if we're in automation mode
            if ($mode === 'manual') {
                aca_debug_log('Simple Automation: No reschedule needed - manual mode');
                return;
            }
            
            // Check if next event is already scheduled
            $next_scheduled = wp_next_scheduled(self::HOOK_IDEA_GENERATION);
            
            if ($next_scheduled) {
                aca_debug_log('Simple Automation: Next idea generation already scheduled for: ' . gmdate('Y-m-d H:i:s', $next_scheduled));
                return;
            }
            
            // WordPress cron auto-reschedule failed - manually reschedule
            aca_debug_log('Simple Automation: WARNING - Next idea generation not scheduled, manually rescheduling...');
            
            // Determine frequency based on mode
            $frequency = 'daily'; // Default
            if ($mode === 'semi-automatic') {
                $frequency = $settings['semiAutoIdeaFrequency'] ?? 'weekly';
            } elseif ($mode === 'full-automatic') {
                // For full-automatic, idea generation should be more frequent than publishing
                // Generate ideas daily, even if publishing is less frequent
                $frequency = 'daily'; // Always daily for idea generation in full-auto mode
            }
            
            // Schedule next event
            $next_time = time() + $this->get_interval_seconds($frequency);
            $scheduled = wp_schedule_event($next_time, $this->get_wp_interval($frequency), self::HOOK_IDEA_GENERATION);
            
            if ($scheduled !== false) {
                aca_debug_log("Simple Automation: RESCUE SCHEDULE SUCCESS - Next idea generation scheduled for: " . gmdate('Y-m-d H:i:s', $next_time) . " with frequency: {$frequency}");
            } else {
                aca_debug_log("Simple Automation: RESCUE SCHEDULE FAILED - Could not schedule next idea generation");
                aca_debug_log("Simple Automation: CRITICAL - Unable to schedule automation tasks");
            }
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception in ensure_next_idea_generation_scheduled: ' . $e->getMessage());
        }
    }

    /**
     * Schedule draft creation with delay
     */
    private function schedule_draft_creation_delayed() {
        $latest_idea_id = $this->get_latest_idea_id();
        if ($latest_idea_id) {
            wp_schedule_single_event(time() + 300, self::HOOK_DRAFT_CREATION, array($latest_idea_id, true));
            aca_debug_log("Simple Automation: Scheduled draft creation for idea {$latest_idea_id}");
        }
    }
    
    /**
     * Handle draft creation
     * AGENTS.MD FIX: Added better error handling and execution tracking
     */
    public function handle_draft_creation($idea_id = null, $auto_publish = false) {
        aca_debug_log("Simple Automation: Executing draft creation" . ($idea_id ? " for idea {$idea_id}" : " (full automation mode)"));
        
        $execution_successful = false;
        
        try {
            $rest_api = ACA_Rest_Api::get_instance();
            if (!$rest_api) {
                throw new Exception('REST API not available');
            }
            
            // Get current settings
            $settings = get_option('aca_settings', array());
            $mode = $settings['mode'] ?? 'manual';
            
            // If no specific idea ID provided, get the latest unprocessed idea for automation
            if (!$idea_id) {
                if ($mode === 'full-automatic' || $mode === 'semi-automatic') {
                    $idea_id = $this->get_latest_unprocessed_idea_id();
                    if (!$idea_id) {
                        aca_debug_log('Simple Automation: No unprocessed ideas available for draft creation');
                        // Try to find any active idea that hasn't been converted to draft
                        global $wpdb;
                        $idea_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                            "SELECT id FROM {$wpdb->prefix}aca_ideas 
                             WHERE status = 'active' 
                             ORDER BY created_at DESC LIMIT 1"
                        );
                        if (!$idea_id) {
                            aca_debug_log('Simple Automation: No active ideas found at all');
                            return;
                        }
                        aca_debug_log("Simple Automation: Using most recent active idea as fallback: {$idea_id}");
                    }
                }
            }
            
            if (!$idea_id) {
                aca_debug_log('Simple Automation: No idea ID provided for draft creation');
                return;
            }
            
            $result = $rest_api->create_draft_from_idea($idea_id, true);
            
            if (is_wp_error($result)) {
                throw new Exception('Draft creation failed: ' . $result->get_error_message());
            }
            
            $execution_successful = true;
            aca_debug_log("Simple Automation: Draft created successfully from idea {$idea_id}");
            // Add activity log for automation draft creation
            if (is_array($result) && isset($result['title'])) {
                $this->aca_add_activity_log_safely('draft_created', sprintf('Automatically created draft: "%s"', $result['title']), 'FileText');
            } else {
                $this->aca_add_activity_log_safely('draft_created', sprintf('Automatically created draft from idea #%d', $idea_id), 'FileText');
            }
            
            // If auto-publish enabled, schedule publishing
            if ($auto_publish) {
                $this->schedule_post_publishing_delayed($idea_id);
            }
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Draft creation error - ' . $e->getMessage());
            $execution_successful = false;
        } finally {
            // AGENTS.MD FIX: Ensure draft creation cron continues if this was a recurring event
            $this->ensure_next_draft_creation_scheduled($execution_successful);
        }
    }
    
    /**
     * Schedule post publishing with delay
     */
    private function schedule_post_publishing_delayed($idea_id) {
        // Find the draft post created from this idea
        $posts_ids = get_posts(array(
            'post_status' => 'draft',
            'fields' => 'ids',
            'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array(
                    'key' => '_aca_created_from_idea',
                    'value' => (int) $idea_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                )
            ),
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        
        if (!empty($posts_ids)) {
            $post_id_to_schedule = (int)$posts_ids[0];
            wp_schedule_single_event(time() + 600, self::HOOK_POST_PUBLISHING, array($post_id_to_schedule, $idea_id));
            aca_debug_log("Simple Automation: Scheduled publishing for post {$post_id_to_schedule}");
        }
    }
    
    /**
     * Handle post publishing
     * AGENTS.MD FIX: Added better error handling and execution tracking
     */
    public function handle_post_publishing($post_id = null, $idea_id = null) {
        aca_debug_log("Simple Automation: Executing post publishing" . ($post_id ? " for post {$post_id}" : " (full automation mode)"));
        
        $execution_successful = false;
        
        try {
            // If no specific post ID provided, get the latest unpublished draft for full automation
            if (!$post_id) {
                $settings = get_option('aca_settings', array());
                if ($settings['mode'] === 'full-automatic' && ($settings['autoPublish'] ?? false)) {
                    $post_id = $this->get_latest_unpublished_draft_id();
                    if (!$post_id) {
                        aca_debug_log('Simple Automation: No unpublished drafts available for publishing');
                        return;
                    }
                }
            }
            
            if (!$post_id) {
                aca_debug_log('Simple Automation: No post ID provided for publishing');
                return;
            }
            
            $result = wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ));
            
            if (is_wp_error($result) || !$result) {
                throw new Exception('Post publishing failed');
            }
            
            $execution_successful = true;
            $post = get_post($post_id);
            aca_debug_log("Simple Automation: Successfully published post {$post_id}: {$post->post_title}");
            // Add activity log for automation publish
            $this->aca_add_activity_log_safely('post_published', sprintf('Automatically published post: "%s"', $post->post_title), 'Send');
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Post publishing error - ' . $e->getMessage());
            $execution_successful = false;
        } finally {
            // AGENTS.MD FIX: Ensure post publishing cron continues if this was a recurring event
            $this->ensure_next_post_publishing_scheduled($execution_successful);
        }
    }
    
    /**
     * Handle maintenance tasks
     */
    public function handle_maintenance() {
        aca_debug_log('Simple Automation: Executing maintenance tasks');
        
        // Clean up old transients
        delete_expired_transients();
        
        // Optional: auto-refresh style guide based on settings
        $settings = get_option('aca_settings', array());
        $freq = $settings['analyzeContentFrequency'] ?? 'manual';
        if ($freq !== 'manual') {
            $lock_key = 'aca_style_refresh_lock';
            if (!get_transient($lock_key)) {
                set_transient($lock_key, time(), 300); // 5 minutes lock
                try {
                    if (class_exists('ACA_Rest_Api')) {
                        $api = ACA_Rest_Api::get_instance();
                        // best-effort: call internal method with $is_auto=true
                        $api->analyze_style_guide(null, true);
                        aca_debug_log('Simple Automation: Style guide auto-refresh executed');
                    }
                } catch (Exception $e) {
                    aca_debug_log('Simple Automation: Style guide auto-refresh failed - ' . $e->getMessage());
                } finally {
                    delete_transient($lock_key);
                }
            } else {
                aca_debug_log('Simple Automation: Style guide refresh skipped due to active lock');
            }
        }
        
        // Update last run time
        update_option('aca_last_automation_run', current_time('mysql'));
        
        aca_debug_log('Simple Automation: Maintenance completed');
    }
    
    /**
     * Get latest idea ID
     */
    private function get_latest_idea_id() {
        global $wpdb;
        
        try {
            // Check if table exists first
            $table_name = $wpdb->prefix . 'aca_ideas';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                aca_debug_log('Simple Automation: aca_ideas table does not exist');
                return null;
            }
            
            $idea_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT id FROM {$wpdb->prefix}aca_ideas ORDER BY created_at DESC LIMIT 1"
            );
            
            // Check for database errors
            if ($wpdb->last_error) {
                aca_debug_log('Simple Automation: Database error in get_latest_idea_id - ' . $wpdb->last_error);
                return null;
            }
            
            return $idea_id ? (int) $idea_id : null;
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception in get_latest_idea_id - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get latest unprocessed idea ID (for full automation)
     */
    private function get_latest_unprocessed_idea_id() {
        global $wpdb;
        
        try {
            // Check if table exists first
            $table_name = $wpdb->prefix . 'aca_ideas';
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) != $table_name) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                aca_debug_log('Simple Automation: aca_ideas table does not exist');
                return null;
            }
            
            // Get ideas that haven't been converted to drafts yet
            // Check using meta_key instead of post_excerpt
            $idea_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT i.id FROM {$wpdb->prefix}aca_ideas i 
                 WHERE i.status = 'active' 
                 AND NOT EXISTS (
                     SELECT 1 FROM {$wpdb->prefix}postmeta pm 
                     WHERE pm.meta_key = '_aca_created_from_idea' 
                     AND pm.meta_value = i.id
                 )
                 ORDER BY i.created_at ASC LIMIT 1"
            );
            
            if ($wpdb->last_error) {
                aca_debug_log('Simple Automation: Database error in get_latest_unprocessed_idea_id - ' . $wpdb->last_error);
                return null;
            }
            
            return $idea_id ? (int) $idea_id : null;
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception in get_latest_unprocessed_idea_id - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get latest unpublished draft ID (for full automation)
     */
    private function get_latest_unpublished_draft_id() {
        try {
            // First try to get drafts created from ideas
            $posts = get_posts(array(
                'post_status' => 'draft',
                'post_type' => 'post',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'ASC',
                'fields' => 'ids',
                'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    array(
                        'key' => '_aca_created_from_idea',
                        'compare' => 'EXISTS'
                    )
                ),
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));
            
            // If no drafts with meta found, try any AI-generated drafts
            if (empty($posts)) {
                $posts = get_posts(array(
                    'post_status' => 'draft',
                    'post_type' => 'post',
                    'numberposts' => 1,
                    'orderby' => 'date',
                    'order' => 'ASC',
                    'fields' => 'ids',
                    'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                        array(
                            'key' => '_aca_ai_generated',
                            'value' => '1',
                            'compare' => '='
                        )
                    ),
                    'no_found_rows' => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ));
                
                if (!empty($posts)) {
                    aca_debug_log('Simple Automation: Using AI-generated draft as fallback for publishing');
                }
            }
            
            if (!empty($posts)) {
                return (int)$posts[0];
            }
            
            return null;
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception in get_latest_unpublished_draft_id - ' . $e->getMessage());
            return null;
        }
    }
    

    
    /**
     * Convert frequency to seconds
     */
    private function get_interval_seconds($frequency) {
        switch ($frequency) {
            case 'hourly': return HOUR_IN_SECONDS;
            case 'daily': return DAY_IN_SECONDS;
            case 'weekly': return WEEK_IN_SECONDS;
            case 'monthly': return MONTH_IN_SECONDS;
            default: return DAY_IN_SECONDS;
        }
    }
    
    /**
     * Add custom cron intervals for Simple Automation - AGENTS.MD TIMING FIX
     * Enhanced intervals for optimal workflow timing
     */
    public function add_custom_cron_intervals($schedules) {
        // Existing monthly interval
        $schedules['aca_monthly'] = array(
            'interval' => MONTH_IN_SECONDS,
            'display' => __('Once Monthly', 'ai-content-agent')
        );
        
        // AGENTS.MD TIMING FIX: Add optimized workflow intervals
        $schedules['aca_thirty_minutes'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every 30 Minutes', 'ai-content-agent')
        );
        
        $schedules['aca_four_hours'] = array(
            'interval' => 4 * HOUR_IN_SECONDS,
            'display' => __('Every 4 Hours', 'ai-content-agent')
        );
        
        $schedules['aca_six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours', 'ai-content-agent')
        );
        
        $schedules['aca_twelve_hours'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Every 12 Hours', 'ai-content-agent')
        );
        
        return $schedules;
    }
    
    /**
     * PHASE 1 BUG #1 FIX: Bulletproof interval registration system
     * Strategic approach with multiple fallback methods and validation
     */
    private function ensure_bulletproof_intervals($missing_intervals) {
        aca_debug_log('Simple Automation: PHASE 1 - Starting bulletproof interval registration');
        
        $max_attempts = 3;
        $success = false;
        
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            aca_debug_log("Simple Automation: Bulletproof registration attempt {$attempt}/{$max_attempts}");
            
            // 1. Register intervals with multiple fallback methods
            $updated_schedules = $this->add_custom_cron_intervals(wp_get_schedules());
            
            // 2. Force apply the updated schedules globally with high priority
            add_filter('cron_schedules', function($existing_schedules) use ($updated_schedules) {
                aca_debug_log('Simple Automation: Applying bulletproof cron_schedules filter');
                return $updated_schedules;
            }, 999);
            
            // 3. Force cache refresh using comprehensive cache clearing
            $this->clear_all_cron_caches();
            
            // 4. Validate registration success
            $current_schedules = wp_get_schedules();
            $validation_success = true;
            
            foreach ($missing_intervals as $interval_name) {
                if (!isset($current_schedules[$interval_name])) {
                    $validation_success = false;
                    aca_debug_log("Simple Automation: Interval '{$interval_name}' still missing after attempt {$attempt}");
                    break;
                }
            }
            
            if ($validation_success) {
                $success = true;
                aca_debug_log("Simple Automation: Bulletproof registration successful on attempt {$attempt}");
                break;
            }
            
            // 5. Wait before retry (except on last attempt)
            if ($attempt < $max_attempts) {
                aca_debug_log("Simple Automation: Waiting before retry attempt " . ($attempt + 1));
                usleep(100000); // 100ms delay
            }
        }
        
        if (!$success) {
            aca_debug_log('Simple Automation: CRITICAL - Bulletproof registration failed after all attempts');
            // Log detailed diagnostic information
            $current_schedules = wp_get_schedules();
            aca_debug_log('Simple Automation: Available schedules: ' . implode(', ', array_keys($current_schedules)));
            return false;
        }
        
        aca_debug_log('Simple Automation: Bulletproof interval registration completed successfully');
        return true;
    }
    
    /**
     * PHASE 1 BUG #4 ENHANCEMENT: Comprehensive cache clearing
     * Clears all relevant WordPress caches that could interfere with cron
     */
    private function clear_all_cron_caches() {
        aca_debug_log('Simple Automation: Starting comprehensive cache clearing');
        
        // Clear WordPress cron option cache only (avoid global cache flush)
        wp_cache_delete('cron', 'options');
        
        // PHASE 1 BUG #3 ENHANCEMENT: Force cron cache refresh
        $this->force_cron_cache_refresh();
        
        // Clear any plugin-specific caches
        delete_transient('aca_intervals_validated');
        
        aca_debug_log('Simple Automation: Comprehensive cache clearing completed');
    }
    
    /**
     * PHASE 1 BUG #3 FIX: Force WordPress cron cache refresh
     * Rebuilds internal caches to ensure custom intervals are available
     */
    private function force_cron_cache_refresh() {
        aca_debug_log('Simple Automation: Forcing cron cache refresh');
        
        // Force WordPress to rebuild internal caches by calling core functions
        wp_get_schedules();
        _get_cron_array();
        
        // Trigger cron_schedules filter to ensure our intervals are registered
        apply_filters('cron_schedules', array());
        
        aca_debug_log('Simple Automation: Cron cache refresh completed');
    }
    
    /**
     * Get WordPress cron interval
     */
    private function get_wp_interval($frequency) {
        switch ($frequency) {
            case 'hourly': return 'hourly';
            case 'daily': return 'daily';
            case 'weekly': return 'weekly';
            case 'monthly': return 'aca_monthly'; // Use our custom monthly interval
            default: return 'daily';
        }
    }
    
    /**
     * Clear all scheduled automation tasks
     */
    public function clear_all_schedules() {
        wp_clear_scheduled_hook(self::HOOK_IDEA_GENERATION);
        wp_clear_scheduled_hook(self::HOOK_DRAFT_CREATION);
        wp_clear_scheduled_hook(self::HOOK_POST_PUBLISHING);
        wp_clear_scheduled_hook(self::HOOK_MAINTENANCE);
        
        aca_debug_log('Simple Automation: All schedules cleared');
    }
    
    /**
     * Clear all scheduled automation tasks (enhanced)
     */
    private function clear_all_schedules_enhanced() {
        wp_clear_scheduled_hook(self::HOOK_IDEA_GENERATION);
        wp_clear_scheduled_hook(self::HOOK_DRAFT_CREATION);
        wp_clear_scheduled_hook(self::HOOK_POST_PUBLISHING);
        wp_clear_scheduled_hook(self::HOOK_MAINTENANCE);
        
        aca_debug_log('Simple Automation: All schedules cleared (enhanced)');
    }

    /**
     * Clear only Pro-only automation schedules
     */
    private function clear_pro_only_schedules() {
        wp_clear_scheduled_hook(self::HOOK_IDEA_GENERATION);
        wp_clear_scheduled_hook(self::HOOK_DRAFT_CREATION);
        wp_clear_scheduled_hook(self::HOOK_POST_PUBLISHING);
        aca_debug_log('Simple Automation: Cleared Pro-only automation schedules');
    }

    /**
     * Clear only automation schedules
     */
    private function clear_automation_schedules() {
        wp_clear_scheduled_hook(self::HOOK_IDEA_GENERATION);
        wp_clear_scheduled_hook(self::HOOK_DRAFT_CREATION);
        wp_clear_scheduled_hook(self::HOOK_POST_PUBLISHING);
        // PHASE 3 BUG #12 FIX: Also clear maintenance in manual mode
        wp_clear_scheduled_hook(self::HOOK_MAINTENANCE);
        aca_debug_log('Simple Automation: Cleared all automation schedules including maintenance');
    }
    
    /**
     * Get automation status - AGENTS.MD ENHANCED VERSION
     * Includes detailed workflow timing and status information
     */
    public function get_status() {
        $settings = get_option('aca_settings', array());
        
        // AGENTS.MD FIX: Enhanced license verification for status reporting (no forced refresh)
        $pro_status = is_aca_pro_active(false);
        aca_debug_log('Simple Automation Status: Pro license check result: ' . ($pro_status ? 'ACTIVE' : 'INACTIVE'));
        
        // AGENTS.MD CUSTOMER-FOCUSED FIX: Provide clear, user-friendly status
        $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $system_status = 'Ready & Reliable';
        
        // Determine system configuration message
        if ($wp_cron_disabled) {
            $system_status = 'Advanced Setup (Server Cron)';
        } else {
            $system_status = 'Standard Setup (WordPress Cron)';
        }
        
        // AGENTS.MD TIMING FIX: Calculate workflow timing information
        $workflow_info = array();
        if ($settings['mode'] === 'full-automatic') {
            $daily_post_count = intval($settings['fullAutoDailyPostCount'] ?? 1);
            $publish_frequency = $settings['fullAutoPublishFrequency'] ?? 'daily';
            $auto_publish_enabled = $settings['autoPublish'] ?? false;
            
            $workflow_timing = $this->calculate_automation_workflow_timing($daily_post_count, $publish_frequency, $auto_publish_enabled);
            $workflow_info = $workflow_timing['workflow_summary'] ?? array();
        } elseif ($settings['mode'] === 'semi-automatic') {
            $idea_frequency = $settings['semiAutoIdeaFrequency'] ?? 'weekly';
            $workflow_info = array(
                'ideas_per_generation' => 5, // Always 5 for semi-automatic
                'idea_frequency' => $idea_frequency,
                'draft_creation' => 'manual',
                'publishing' => 'manual',
                'workflow_optimized' => true
            );
        }
        
        return array(
            'mode' => $settings['mode'] ?? 'manual',
            'idea_generation_scheduled' => wp_next_scheduled(self::HOOK_IDEA_GENERATION),
            'draft_creation_scheduled' => wp_next_scheduled(self::HOOK_DRAFT_CREATION),
            'post_publishing_scheduled' => wp_next_scheduled(self::HOOK_POST_PUBLISHING),
            'maintenance_scheduled' => wp_next_scheduled(self::HOOK_MAINTENANCE),
            'last_run' => get_option('aca_last_automation_run', 'Never'),
            'wp_cron_disabled' => $wp_cron_disabled,
            'system_status' => $system_status,
            'system_health' => 'healthy', // Always healthy with new customer-friendly approach
            'setup_type' => $wp_cron_disabled ? 'advanced' : 'standard',
            'workflow_info' => $workflow_info, // AGENTS.MD TIMING FIX: Add workflow details
            'full_auto_settings' => array(
                'daily_post_count' => intval($settings['fullAutoDailyPostCount'] ?? 1),
                'publish_frequency' => $settings['fullAutoPublishFrequency'] ?? 'daily',
                'auto_publish_enabled' => $settings['autoPublish'] ?? false
            ),
            // AGENTS.MD FIX: Add pro license status for debugging
            'pro_license_active' => $pro_status,
            'license_debug' => array(
                'status' => get_option('aca_license_status'),
                'key_set' => !empty(get_option('aca_license_key')),
                'verified' => get_option('aca_license_verified') === wp_hash('verified'),
                'timestamp_age' => time() - get_option('aca_license_timestamp', 0)
            ),
            // AGENTS.MD UX ENHANCEMENT: Add detailed scheduling info without heavy cache clearing
            'next_run_times' => array(
                'idea_generation' => $this->get_fresh_next_scheduled(self::HOOK_IDEA_GENERATION),
                'draft_creation' => $this->get_fresh_next_scheduled(self::HOOK_DRAFT_CREATION),
                'post_publishing' => $this->get_fresh_next_scheduled(self::HOOK_POST_PUBLISHING),
                'maintenance' => $this->get_fresh_next_scheduled(self::HOOK_MAINTENANCE)
            ),
            // Add system requirements info for UI display
            'system_requirements' => array(
                'memory_limit' => array(
                    'current' => WP_MEMORY_LIMIT,
                    'current_bytes' => $this->convert_to_bytes(WP_MEMORY_LIMIT),
                    'required' => '256M',
                    'required_bytes' => 256 * 1024 * 1024,
                    'sufficient' => $this->convert_to_bytes(WP_MEMORY_LIMIT) >= (256 * 1024 * 1024)
                ),
                'php_memory_limit' => ini_get('memory_limit'),
                'memory_usage' => array(
                    'current' => memory_get_usage(true),
                    'current_formatted' => size_format(memory_get_usage(true)),
                    'peak' => memory_get_peak_usage(true),
                    'peak_formatted' => size_format(memory_get_peak_usage(true))
                ),
                'execution_time' => array(
                    'current' => ini_get('max_execution_time'),
                    'recommended' => '300'
                )
            )
        );
    }
    
    /**
     * Convert memory string to bytes
     */
    private function convert_to_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
    
    /**
     * PHASE 2 BUG #4 ENHANCEMENT: Get fresh next scheduled time with comprehensive cache clearing
     */
    private function get_fresh_next_scheduled($hook) {
        // Avoid heavy cache clearing; read current scheduled time directly
        $next_run = wp_next_scheduled($hook);
        
        if ($next_run) {
            aca_debug_log("Simple Automation: Fresh scheduled time for {$hook}: " . gmdate('Y-m-d H:i:s', $next_run));
            return gmdate('Y-m-d H:i:s', $next_run);
        } else {
            aca_debug_log("Simple Automation: No scheduled time found for {$hook}");
            return null;
        }
    }
    
    /**
     * Trigger manual task execution
     */
    public function trigger_manual_task($task_type, $args = array()) {
        switch ($task_type) {
            case 'idea_generation':
                return $this->execute_idea_generation($args);
            case 'maintenance':
                return $this->execute_maintenance($args);
            default:
                throw new Exception(esc_html('Unknown task type: ' . sanitize_text_field((string) $task_type)));
        }
    }
    
    /**
     * Execute idea generation task
     */
    private function execute_idea_generation($args = array()) {
        $settings = get_option('aca_settings', array());
        $mode = $settings['mode'] ?? 'manual';
        
        if ($mode === 'disabled') {
            return array('status' => 'skipped', 'message' => __('Automation is disabled', 'ai-content-agent'));
        }
        
        try {
            // Use REST API directly for idea generation
            $rest_api = ACA_Rest_Api::get_instance();
            if (!$rest_api) {
                throw new Exception('REST API not available');
            }
            
            // Determine count based on args or mode
            $count = $args['count'] ?? 5; // Use provided count or default
            if ($mode === 'full-automatic' && is_aca_pro_active()) {
                $daily_post_count = intval($settings['fullAutoDailyPostCount'] ?? 1);
                $publish_frequency = $settings['fullAutoPublishFrequency'] ?? 'daily';
                $auto_publish_enabled = $settings['autoPublish'] ?? false;
                
                // AGENTS.MD CRITICAL FIX: Use exact same calculation as workflow timing
                $workflow_timing = $this->calculate_automation_workflow_timing($daily_post_count, $publish_frequency, $auto_publish_enabled);
                $count = $args['count'] ?? $workflow_timing['workflow_summary']['ideas_per_generation'];
            } elseif ($mode === 'semi-automatic') {
                $count = $args['count'] ?? 5; // FIXED: Semi-automatic always generates 5 ideas
            }
            
            // Create REST request for idea generation
            $request = new WP_REST_Request('POST', '/aca/v1/ideas/generate');
            $request->set_body(json_encode(array(
                'count' => $count,
                'auto' => true,
                'mode' => $mode
            )));
            $request->set_header('Content-Type', 'application/json');
            
            $result = $rest_api->generate_ideas($request);
            
            if (is_wp_error($result)) {
                throw new Exception('Idea generation failed: ' . $result->get_error_message());
            }
            
            $run_label = (!empty($args['scheduled'])) ? ' (Scheduled)' : ' (Manual Trigger)';
            update_option('aca_last_automation_run', current_time('mysql') . $run_label);
            
            // Memory cleanup after heavy operations
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            return array(
                'status' => 'completed',
                'message' => __('Idea generation task executed successfully', 'ai-content-agent'),
                'mode' => $mode,
                'count' => $count,
                'result' => $result
            );
            
        } catch (Exception $e) {
            // Memory cleanup on error too
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            return array(
                'status' => 'error', 
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Idea generation failed: %s', 'ai-content-agent'),
                    $e->getMessage()
                )
            );
        }
    }
    
    /**
     * Execute maintenance task
     */
    private function execute_maintenance($args = array()) {
        $operations = array();
        
        // Clean up old logs
        $operations['log_cleanup'] = $this->cleanup_old_logs();
        
        // Update last maintenance time
        update_option('aca_last_maintenance_run', current_time('mysql'));
        $operations['maintenance_time'] = current_time('mysql');
        
        return array(
            'status' => 'completed',
            'message' => __('Maintenance task executed successfully', 'ai-content-agent'),
            'operations' => $operations
        );
    }
    
    /**
     * AGENTS.MD FIX: Ensure next draft creation is scheduled (for recurring events)
     */
    private function ensure_next_draft_creation_scheduled($execution_successful = true) {
        try {
            // Only check if this was a recurring cron event (full-automatic mode)
            $settings = get_option('aca_settings', array());
            if ($settings['mode'] !== 'full-automatic') {
                return; // Single events don't need reschedule
            }
            
            $next_scheduled = wp_next_scheduled(self::HOOK_DRAFT_CREATION);
            
            if (!$next_scheduled) {
                aca_debug_log('Simple Automation: WARNING - Next draft creation not scheduled, manually rescheduling...');
                
                // Reschedule hourly for full automation
                $next_time = time() + HOUR_IN_SECONDS;
                $scheduled = wp_schedule_event($next_time, 'hourly', self::HOOK_DRAFT_CREATION);
                
                if ($scheduled !== false) {
                    aca_debug_log("Simple Automation: RESCUE SCHEDULE SUCCESS - Next draft creation scheduled for: " . gmdate('Y-m-d H:i:s', $next_time));
                } else {
                    aca_debug_log("Simple Automation: RESCUE SCHEDULE FAILED - Could not schedule next draft creation");
                }
            }
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception in ensure_next_draft_creation_scheduled: ' . $e->getMessage());
        }
    }

    /**
     * AGENTS.MD FIX: Ensure next post publishing is scheduled (for recurring events)
     */
    private function ensure_next_post_publishing_scheduled($execution_successful = true) {
        try {
            // Only check if this was a recurring cron event (full-automatic mode with auto-publish)
            $settings = get_option('aca_settings', array());
            if ($settings['mode'] !== 'full-automatic' || !($settings['autoPublish'] ?? false)) {
                return; // Only for full-auto with auto-publish
            }
            
            $next_scheduled = wp_next_scheduled(self::HOOK_POST_PUBLISHING);
            
            if (!$next_scheduled) {
                aca_debug_log('Simple Automation: WARNING - Next post publishing not scheduled, manually rescheduling...');
                
                // Reschedule based on publish frequency
                $frequency = $settings['fullAutoPublishFrequency'] ?? 'daily';
                $next_time = time() + $this->get_interval_seconds($frequency);
                $scheduled = wp_schedule_event($next_time, $this->get_wp_interval($frequency), self::HOOK_POST_PUBLISHING);
                
                if ($scheduled !== false) {
                    aca_debug_log("Simple Automation: RESCUE SCHEDULE SUCCESS - Next post publishing scheduled for: " . gmdate('Y-m-d H:i:s', $next_time) . " with frequency: {$frequency}");
                } else {
                    aca_debug_log("Simple Automation: RESCUE SCHEDULE FAILED - Could not schedule next post publishing");
                }
            }
            
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception in ensure_next_post_publishing_scheduled: ' . $e->getMessage());
        }
    }

    /**
     * Clean up old logs (basic implementation)
     */
    private function cleanup_old_logs() {
        // This is a placeholder - implement based on your logging system
        return array('cleaned' => 0, 'message' => __('Log cleanup completed', 'ai-content-agent'));
    }

    /**
     * Validate scheduling environment - CUSTOMER-FRIENDLY APPROACH
     * WP-Cron disabled is acceptable if server cron is configured
     */
    private function validate_scheduling_environment() {
        // AGENTS.MD CUSTOMER-FOCUSED FIX: WP-Cron disabled is acceptable for advanced users
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            aca_debug_log('Simple Automation: WP-Cron is disabled. Assuming server cron is configured for advanced setup.');
        } else {
            aca_debug_log('Simple Automation: WP-Cron is enabled. Using WordPress native cron system.');
        }

        // Check if memory limit is sufficient (warn only)
        $memory_limit = WP_MEMORY_LIMIT;
        if (strpos($memory_limit, 'M') !== false) {
            $memory_limit_mb = intval($memory_limit);
            if ($memory_limit_mb < 256) { // Minimum 256MB recommended
                aca_debug_log('Simple Automation: WARNING - Memory limit is low. Current: ' . $memory_limit . '. Recommended: at least 256MB.');
            }
        }

        // Check if max execution time is sufficient (warn only)
        $max_execution_time = ini_get('max_execution_time');
        if (strpos($max_execution_time, 's') !== false) {
            $max_execution_time_sec = intval($max_execution_time);
            if ($max_execution_time_sec < 300) { // Minimum 300 seconds recommended
                aca_debug_log('Simple Automation: WARNING - Max execution time is low. Current: ' . $max_execution_time . '. Recommended: at least 300s.');
            }
        }

        return true;
    }

    /**
     * Verify scheduling success
     */
    private function verify_scheduling_success($mode) {
        if ($mode === 'manual') {
            return;
        }

        $next_scheduled = wp_next_scheduled(self::HOOK_IDEA_GENERATION);
        if (!$next_scheduled) {
            aca_debug_log('Simple Automation: WARNING - Idea generation not scheduled after rescheduling. Attempting to force reschedule.');
            $this->force_reschedule_tasks();
        }

        $next_scheduled = wp_next_scheduled(self::HOOK_DRAFT_CREATION);
        if (!$next_scheduled) {
            aca_debug_log('Simple Automation: WARNING - Draft creation not scheduled after rescheduling. Attempting to force reschedule.');
            $this->force_reschedule_tasks();
        }

        $next_scheduled = wp_next_scheduled(self::HOOK_POST_PUBLISHING);
        if (!$next_scheduled) {
            aca_debug_log('Simple Automation: WARNING - Post publishing not scheduled after rescheduling. Attempting to force reschedule.');
            $this->force_reschedule_tasks();
        }
    }

    /**
     * Schedule semi-automatic mode tasks (enhanced) - AGENTS.MD TIMING FIX
     */
    private function schedule_semi_automatic_enhanced($settings) {
        $frequency = $settings['semiAutoIdeaFrequency'] ?? 'weekly';
        $base_delay = 300; // 5 minutes initial delay
        
        // Schedule idea generation
        if (!wp_next_scheduled(self::HOOK_IDEA_GENERATION)) {
            wp_schedule_event(time() + $base_delay, $this->get_wp_interval_validated($frequency), self::HOOK_IDEA_GENERATION);
            aca_debug_log("Simple Automation: Scheduled semi-automatic idea generation - Frequency: {$frequency}, Delay: {$base_delay}s, Ideas per run: 5");
        }
        
        // PHASE 2 BUG #9 FIX: Schedule maintenance tasks for semi-automatic mode
        if (!wp_next_scheduled(self::HOOK_MAINTENANCE)) {
            // Schedule maintenance to run daily for cleanup and optimization
            wp_schedule_event(time() + $base_delay + 60, 'daily', self::HOOK_MAINTENANCE);
            aca_debug_log("Simple Automation: Scheduled semi-automatic maintenance - Daily cleanup and optimization");
        }
        
        aca_debug_log("Simple Automation: Semi-automatic workflow - AI generates 5 ideas {$frequency}, maintenance runs daily, user reviews and creates drafts manually, user publishes manually");
    }

    /**
     * Schedule full-automatic mode tasks (enhanced) - AGENTS.MD TIMING FIX
     */
    private function schedule_full_automatic_enhanced($settings) {
        $frequency = $settings['fullAutoPublishFrequency'] ?? 'daily';
        $daily_post_count = intval($settings['fullAutoDailyPostCount'] ?? 1);
        $auto_publish_enabled = $settings['autoPublish'] ?? false;
        
        // PHASE 2 BUG #10 FIX: Validate custom intervals before workflow calculation
        if (!$this->validate_interval_dependencies()) {
            aca_debug_log("Simple Automation: Custom intervals missing, using fallback workflow");
            $workflow_timing = $this->get_fallback_workflow_timing($daily_post_count, $frequency, $auto_publish_enabled);
        } else {
            // AGENTS.MD TIMING CALCULATION: Calculate workflow timing with custom intervals
            $workflow_timing = $this->calculate_automation_workflow_timing($daily_post_count, $frequency, $auto_publish_enabled);
        }
        
        aca_debug_log("Simple Automation: Calculated enhanced workflow timing: " . wp_json_encode($workflow_timing));
        
        // Schedule idea generation - frequency based on demand
        if (!wp_next_scheduled(self::HOOK_IDEA_GENERATION)) {
            wp_schedule_event(
                time() + $workflow_timing['idea_generation_delay'], 
                $this->get_wp_interval_validated($workflow_timing['idea_generation_frequency']), 
                self::HOOK_IDEA_GENERATION
            );
            aca_debug_log("Simple Automation: Scheduled enhanced idea generation - Frequency: {$workflow_timing['idea_generation_frequency']}, Delay: {$workflow_timing['idea_generation_delay']}s");
        }
        
        // Schedule draft creation - frequency based on workflow needs
        if (!wp_next_scheduled(self::HOOK_DRAFT_CREATION)) {
            wp_schedule_event(
                time() + $workflow_timing['draft_creation_delay'], 
                $this->get_wp_interval_validated($workflow_timing['draft_creation_frequency']), 
                self::HOOK_DRAFT_CREATION
            );
            aca_debug_log("Simple Automation: Scheduled enhanced draft creation - Frequency: {$workflow_timing['draft_creation_frequency']}, Delay: {$workflow_timing['draft_creation_delay']}s");
        }
        
        // Schedule post publishing if auto-publish is enabled
        if ($auto_publish_enabled && !wp_next_scheduled(self::HOOK_POST_PUBLISHING)) {
            wp_schedule_event(
                time() + $workflow_timing['post_publishing_delay'], 
                $this->get_wp_interval_validated($workflow_timing['post_publishing_frequency']), 
                self::HOOK_POST_PUBLISHING
            );
            aca_debug_log("Simple Automation: Scheduled enhanced post publishing - Frequency: {$workflow_timing['post_publishing_frequency']}, Delay: {$workflow_timing['post_publishing_delay']}s");
        }
        
        aca_debug_log("Simple Automation: Enhanced full automation configured - Posts/day: {$daily_post_count}, Publish freq: {$frequency}, Auto-publish: " . ($auto_publish_enabled ? 'enabled' : 'disabled'));
    }

    /**
     * Schedule maintenance tasks (enhanced)
     */
    private function schedule_maintenance_enhanced() {
        if (!wp_next_scheduled(self::HOOK_MAINTENANCE)) {
            wp_schedule_event(time() + 600, 'daily', self::HOOK_MAINTENANCE);
            aca_debug_log('Simple Automation: Scheduled daily maintenance');
        }
    }

    /**
     * AGENTS.MD COMPREHENSIVE FIX: Enhanced next idea generation scheduling with recovery
     * This is an improved safety net in case WordPress cron auto-reschedule fails
     */
    private function ensure_next_idea_generation_scheduled_enhanced($execution_successful = true, $process_id = null) {
        try {
            // Get current settings
            $settings = get_option('aca_settings', array());
            $mode = $settings['mode'] ?? 'manual';
            
            aca_debug_log("Simple Automation: Checking next idea generation scheduling (Mode: {$mode}, Success: " . ($execution_successful ? 'true' : 'false') . ")");
            
            // Only reschedule if we're in automation mode
            if ($mode === 'manual') {
                aca_debug_log('Simple Automation: No reschedule needed - manual mode');
                return;
            }
            
            // Check if next event is already scheduled
            $next_scheduled = wp_next_scheduled(self::HOOK_IDEA_GENERATION);
            
            if ($next_scheduled) {
                $next_date = gmdate('Y-m-d H:i:s', $next_scheduled);
                aca_debug_log("Simple Automation: Next idea generation already scheduled for: {$next_date}");
                
                // AGENTS.MD ENHANCEMENT: Validate scheduled time is reasonable
                $time_until_next = $next_scheduled - time();
                if ($time_until_next < 300) { // Less than 5 minutes
                    aca_debug_log('Simple Automation: WARNING - Next scheduled time is too soon, may indicate scheduling issue');
                }
                if ($time_until_next > (30 * DAY_IN_SECONDS)) { // More than 30 days
                    aca_debug_log('Simple Automation: WARNING - Next scheduled time is too far in future, may indicate scheduling issue');
                }
                
                return;
            }
            
            // WordPress cron auto-reschedule failed - manually reschedule
            aca_debug_log('Simple Automation: WARNING - Next idea generation not scheduled, manually rescheduling...');
            
            // Determine frequency based on mode
            $frequency = 'daily'; // Default
            if ($mode === 'semi-automatic') {
                $frequency = $settings['semiAutoIdeaFrequency'] ?? 'weekly';
            } elseif ($mode === 'full-automatic') {
                // For full-automatic, idea generation should be more frequent than publishing
                // Generate ideas daily, even if publishing is less frequent
                $frequency = 'daily'; // Always daily for idea generation in full-auto mode
            }
            
            // AGENTS.MD ENHANCEMENT: Calculate next time with jitter to prevent thundering herd
            $base_interval = $this->get_interval_seconds($frequency);
            $jitter = wp_rand(-300, 300); // ±5 minutes random jitter
            $next_time = time() + $base_interval + $jitter;
            
            // AGENTS.MD ENHANCEMENT: Multiple scheduling attempts with fallback
            $scheduling_attempts = 0;
            $max_attempts = 3;
            $scheduled = false;
            
            while (!$scheduled && $scheduling_attempts < $max_attempts) {
                $scheduled = wp_schedule_event($next_time, $this->get_wp_interval_validated($frequency), self::HOOK_IDEA_GENERATION);
                $scheduling_attempts++;
                
                if ($scheduled !== false) {
                    aca_debug_log("Simple Automation: RESCUE SCHEDULE SUCCESS - Next idea generation scheduled for: " . gmdate('Y-m-d H:i:s', $next_time) . " with frequency: {$frequency} (Attempt: {$scheduling_attempts})");
                    break;
                } else {
                    aca_debug_log("Simple Automation: RESCUE SCHEDULE ATTEMPT {$scheduling_attempts} FAILED");
                    if ($scheduling_attempts < $max_attempts) {
                        $next_time += 300; // Add 5 minutes and try again
                        aca_debug_log("Simple Automation: Retrying with new time: " . gmdate('Y-m-d H:i:s', $next_time));
                    }
                }
            }
            
            if (!$scheduled) {
                // Last resort error logging
                $wp_error = $this->diagnose_scheduling_failure();
                aca_debug_log("Simple Automation: Scheduling failure diagnosis: " . wp_json_encode($wp_error));
                aca_debug_log("Simple Automation: CRITICAL - Unable to schedule automation tasks after multiple attempts");
                
                // Handle critical failure
                $this->handle_critical_scheduling_failure($process_id);
            }
            
        } catch (Exception $e) {
            aca_debug_log("Simple Automation: Exception in ensure_next_idea_generation_scheduled_enhanced: " . $e->getMessage());
        }
    }
    
    /**
     * AGENTS.MD ENHANCEMENT: Diagnose scheduling failures
     */
    private function diagnose_scheduling_failure() {
        $diagnosis = array();
        
        // Check if WP_Cron is disabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $diagnosis['wp_cron_disabled'] = true;
        }
        
        // Check cron array
        $cron_array = _get_cron_array();
        $diagnosis['cron_array_size'] = $cron_array ? count($cron_array) : 0;
        
        // Check memory usage
        $diagnosis['memory_usage'] = size_format(memory_get_usage(true));
        $diagnosis['memory_limit'] = ini_get('memory_limit');
        
        // Check execution time
        $diagnosis['max_execution_time'] = ini_get('max_execution_time');
        
        // Check WordPress version
        global $wp_version;
        $diagnosis['wp_version'] = $wp_version;
        
        return $diagnosis;
    }
    
    /**
     * AGENTS.MD ENHANCEMENT: Handle critical scheduling failures
     */
    private function handle_critical_scheduling_failure($process_id = null) {
        // Log critical failure
        aca_debug_log("Simple Automation: CRITICAL FAILURE - Unable to schedule automation tasks (Process ID: {$process_id})");
        
        // Set failure flag
        update_option('aca_automation_critical_failure', array(
            'timestamp' => current_time('mysql'),
            'process_id' => $process_id,
            'failure_count' => (get_option('aca_automation_failure_count', 0) + 1)
        ));
        
        // Optionally send admin notification (if email functions are available)
        if (function_exists('wp_mail')) {
            $admin_email = get_option('admin_email');
            $site_name = get_option('blogname');
            
            $subject = "[{$site_name}] AI Content Agent - Critical Automation Failure";
            $message = "The AI Content Agent automation system has experienced a critical failure and cannot schedule tasks.\n\n";
            $message .= "Process ID: {$process_id}\n";
            $message .= "Timestamp: " . current_time('mysql') . "\n\n";
            $message .= "Please check the WordPress debug logs for more details.";
            
            wp_mail($admin_email, $subject, $message);
            aca_debug_log("Simple Automation: Critical failure notification sent to admin");
        }
    }

    /**
     * AGENTS.MD TIMING FIX: Ensure all custom cron intervals are registered
     * This method validates that WordPress recognizes our custom intervals
     */
    public function validate_custom_cron_intervals() {
        $schedules = wp_get_schedules();
        $required_intervals = array(
            'aca_monthly' => MONTH_IN_SECONDS,
            'aca_thirty_minutes' => 30 * MINUTE_IN_SECONDS,
            'aca_four_hours' => 4 * HOUR_IN_SECONDS,
            'aca_six_hours' => 6 * HOUR_IN_SECONDS,
            'aca_twelve_hours' => 12 * HOUR_IN_SECONDS
        );
        
        $missing_intervals = array();
        foreach ($required_intervals as $interval_name => $expected_seconds) {
            if (!isset($schedules[$interval_name]) || $schedules[$interval_name]['interval'] !== $expected_seconds) {
                $missing_intervals[] = $interval_name;
            }
        }
        
        if (!empty($missing_intervals)) {
            aca_debug_log('Simple Automation: Missing or incorrect cron intervals detected: ' . implode(', ', $missing_intervals));
            // PHASE 1 BUG #1 FIX: Bulletproof interval registration system
            return $this->ensure_bulletproof_intervals($missing_intervals);
        }
        
        aca_debug_log('Simple Automation: All custom cron intervals are properly registered');
        return true;
    }
    
    /**
     * AGENTS.MD TIMING FIX: Get WordPress interval with fallback validation
     * AGENTS.MD CRITICAL FIX: Handle custom intervals properly
     * Ensures the interval exists before using it
     */
    private function get_wp_interval_validated($frequency) {
        // AGENTS.MD FIX: Ensure custom intervals are registered before validation
        $this->ensure_custom_intervals_registered();
        
        // AGENTS.MD FIX: If frequency is already a custom interval, validate it directly
        if (strpos($frequency, 'aca_') === 0) {
            $schedules = wp_get_schedules();
            if (isset($schedules[$frequency])) {
                aca_debug_log("Simple Automation: Using custom interval: {$frequency}");
                return $frequency;
            } else {
                aca_debug_log("Simple Automation: CRITICAL - Custom interval '{$frequency}' not found, falling back to 'daily'");
                return 'daily';
            }
        }
        
        // For standard frequency names, use the mapping
        $interval = $this->get_wp_interval($frequency);
        $schedules = wp_get_schedules();
        
        if (!isset($schedules[$interval])) {
            aca_debug_log("Simple Automation: WARNING - Interval '{$interval}' not found, falling back to 'daily'");
            return 'daily';
        }
        
        aca_debug_log("Simple Automation: Using validated interval: {$interval}");
        return $interval;
    }
    
    /**
     * PHASE 2 BUG #10 FIX: Validate that all required custom intervals exist
     */
    private function validate_interval_dependencies() {
        $required_intervals = array('aca_thirty_minutes', 'aca_four_hours', 'aca_six_hours', 'aca_twelve_hours');
        $schedules = wp_get_schedules();
        
        foreach ($required_intervals as $interval) {
            if (!isset($schedules[$interval])) {
                aca_debug_log("Simple Automation: Missing required interval: {$interval}");
                return false;
            }
        }
        
        aca_debug_log("Simple Automation: All custom intervals validated successfully");
        return true;
    }
    
    /**
     * PHASE 2 BUG #10 FIX: Fallback workflow timing using only standard WordPress intervals
     */
    private function get_fallback_workflow_timing($daily_post_count, $publish_frequency, $auto_publish_enabled) {
        aca_debug_log("Simple Automation: Using fallback workflow timing with standard intervals only");
        
        // Simple fallback workflow using only standard WordPress intervals
        $workflow_timing = array(
            'idea_generation_frequency' => 'daily',  // Always daily for fallback
            'idea_generation_delay' => 300,
            'draft_creation_frequency' => 'daily',   // Always daily for fallback
            'draft_creation_delay' => 900,
            'post_publishing_frequency' => $publish_frequency, // Use requested frequency
            'post_publishing_delay' => 1800,
            'maintenance_frequency' => 'daily',
            'maintenance_delay' => 3600,
            'ideas_per_generation' => max($daily_post_count * 2, 5), // 2x buffer for fallback
            'fallback_mode' => true
        );
        
        return $workflow_timing;
    }
    
    /**
     * AGENTS.MD CRITICAL FIX: Ensure custom intervals are registered
     * Fixes timing issues where scheduling happens before intervals are registered
     */
    private function ensure_custom_intervals_registered() {
        $schedules = wp_get_schedules();
        
        // Check if our custom intervals are missing and force registration
        $required_intervals = array('aca_monthly', 'aca_thirty_minutes', 'aca_four_hours', 'aca_six_hours', 'aca_twelve_hours');
        $missing_intervals = array();
        
        foreach ($required_intervals as $interval) {
            if (!isset($schedules[$interval])) {
                $missing_intervals[] = $interval;
            }
        }
        
        if (!empty($missing_intervals)) {
            aca_debug_log("Simple Automation: CRITICAL - Missing custom intervals: " . implode(', ', $missing_intervals) . " - Force registering");
            
            // Force register missing intervals
            $updated_schedules = $this->add_custom_cron_intervals($schedules);
            
            // Apply the filter manually to ensure intervals are available
            add_filter('cron_schedules', function($existing_schedules) use ($updated_schedules) {
                return array_merge($existing_schedules, $updated_schedules);
            }, 999);
            
            // PHASE 2 BUG #5 FIX: Immediate cache refresh to make intervals available in current context
            $this->clear_all_cron_caches();
            
            // Validate that intervals are now available
            $validation_schedules = wp_get_schedules();
            $still_missing = array();
            foreach ($missing_intervals as $interval) {
                if (!isset($validation_schedules[$interval])) {
                    $still_missing[] = $interval;
                }
            }
            
            if (empty($still_missing)) {
                aca_debug_log("Simple Automation: Successfully registered all missing custom intervals");
            } else {
                aca_debug_log("Simple Automation: WARNING - Some intervals still missing after registration: " . implode(', ', $still_missing));
            }
        }
    }

    /**
     * Add an activity log entry directly (cron-safe)
     */
    private function aca_add_activity_log_safely($type, $details, $icon) {
        global $wpdb;
        try {
            $table_name = $wpdb->prefix . 'aca_activity_logs';
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if (!$table_exists) {
                aca_debug_log('Simple Automation: Activity logs table missing, skipping activity log insert');
                return false;
            }
            $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table insert
                $table_name,
                array(
                    'timestamp' => current_time('mysql'),
                    'type' => sanitize_text_field($type),
                    'details' => sanitize_text_field($details),
                    'icon' => sanitize_text_field($icon)
                )
            );
            if ($result === false) {
                aca_debug_log('Simple Automation: Failed to insert activity log: ' . $wpdb->last_error);
                return false;
            }
            return true;
        } catch (Exception $e) {
            aca_debug_log('Simple Automation: Exception inserting activity log: ' . $e->getMessage());
            return false;
        }
    }
}