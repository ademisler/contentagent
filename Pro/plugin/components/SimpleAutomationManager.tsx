import React, { useState, useEffect, useCallback } from 'react';
import { Clock, Play, RefreshCw, AlertCircle, CheckCircle, Spinner, Settings, Lightbulb, FileText, Send, AlertTriangle, Shield } from './Icons';

interface AutomationStatus {
    mode: string;
    idea_generation_scheduled: number | false;
    draft_creation_scheduled?: number | false;
    post_publishing_scheduled?: number | false;
    maintenance_scheduled: number | false;
    last_run: string;
    wp_cron_disabled: boolean;
    system_status: string;
    system_health: string;
    setup_type: 'standard' | 'advanced';
    workflow_info?: {
        ideas_per_generation: number;
        total_ideas_per_day?: number;
        generations_per_day?: number;
        estimated_daily_drafts?: number;
        publishing_enabled?: boolean;
        workflow_optimized: boolean;
        // Semi-automatic specific fields
        idea_frequency?: string;
        draft_creation?: string;
        publishing?: string;
    };
    next_run_times?: {
        idea_generation: string | null;
        draft_creation: string | null;
        post_publishing: string | null;
        maintenance: string | null;
    };
    full_auto_settings?: {
        daily_post_count: number;
        publish_frequency: string;
        auto_publish_enabled: boolean;
    };
    system_requirements?: {
        memory_limit: {
            current: string;
            current_bytes: number;
            required: string;
            required_bytes: number;
            sufficient: boolean;
        };
        php_memory_limit: string;
        memory_usage: {
            current: number;
            current_formatted: string;
            peak: number;
            peak_formatted: string;
        };
        execution_time: {
            current: string;
            recommended: string;
        };
    };
}

interface SimpleAutomationManagerProps {
    onShowToast: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
    refreshTrigger?: number; // AGENTS.MD FIX: Trigger refresh when settings change
    currentSettings?: any; // AGENTS.MD FIX: Pass current frontend settings for consistency
}

export const SimpleAutomationManager: React.FC<SimpleAutomationManagerProps> = ({ onShowToast, refreshTrigger, currentSettings }) => {
    const [status, setStatus] = useState<AutomationStatus | null>(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [testing, setTesting] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Add shimmer animation CSS
    useEffect(() => {
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
        `;
        document.head.appendChild(style);
        return () => {
            document.head.removeChild(style);
        };
    }, []);

    const fetchStatus = async () => {
        try {
            const response = await fetch(`${window.acaData.api_url}automation/status`, {
                headers: {
                    'X-WP-Nonce': window.acaData.nonce,
                    'Content-Type': 'application/json',
                },
                signal: AbortSignal.timeout(10000) // 10 second timeout
            });

            if (!response.ok) {
                // More specific error messages
                if (response.status === 401) {
                    throw new Error('Authentication failed. Please refresh the page.');
                } else if (response.status === 500) {
                    throw new Error('Server error. Please try again later.');
                } else {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            }

            const data = await response.json();
            
            // Validate response data
            if (!data || typeof data !== 'object') {
                throw new Error('Invalid response format');
            }
            
            // AGENTS.MD FIX: Override with current frontend settings if available
            if (currentSettings) {
                data.mode = currentSettings.mode || data.mode;
                
                // Full-automatic settings override
                if (data.full_auto_settings) {
                    data.full_auto_settings.daily_post_count = currentSettings.fullAutoDailyPostCount || data.full_auto_settings.daily_post_count;
                    data.full_auto_settings.publish_frequency = currentSettings.fullAutoPublishFrequency || data.full_auto_settings.publish_frequency;
                    data.full_auto_settings.auto_publish_enabled = currentSettings.autoPublish !== undefined ? currentSettings.autoPublish : data.full_auto_settings.auto_publish_enabled;
                }
                
                // Semi-automatic settings override
                if (data.workflow_info && currentSettings.mode === 'semi-automatic') {
                    data.workflow_info.idea_frequency = currentSettings.semiAutoIdeaFrequency || data.workflow_info.idea_frequency;
                }
            }
            
            setStatus(data);
        } catch (error) {
            console.error('Failed to fetch automation status:', error);
            // More user-friendly error messages
            if (error.name === 'AbortError') {
                onShowToast('Request timed out. Please check your connection.', 'error');
            } else if (error.message.includes('Authentication')) {
                onShowToast(error.message, 'error');
            } else {
                onShowToast('Failed to load automation status. Please try again.', 'error');
            }
            // Don't clear status on error to maintain UI state
        }
    };

    const refreshData = async () => {
        setRefreshing(true);
        try {
            await fetchStatus();
            onShowToast('Automation data refreshed', 'success');
        } catch (error) {
            onShowToast('Failed to refresh automation data', 'error');
        } finally {
            setRefreshing(false);
        }
    };

    // AGENTS.MD FIX: License refresh function for automation activation issues
    const refreshLicense = async () => {
        setRefreshing(true);
        try {
            const response = await fetch(`${window.acaData.api_url}license/refresh`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.acaData.nonce,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                onShowToast(`License refreshed - Status: ${data.pro_status_after ? 'Active' : 'Inactive'}`, 'success');
                
                // Refresh automation data after license refresh
                setTimeout(() => {
                    fetchStatus();
                }, 1000);
            } else {
                throw new Error(data.message || 'Failed to refresh license');
            }
        } catch (error) {
            console.error('Failed to refresh license:', error);
            onShowToast('Failed to refresh license status', 'error');
        } finally {
            setRefreshing(false);
        }
    };

    const testAutomation = async () => {
        setTesting(true);
        try {
            const response = await fetch(`${window.acaData.api_url}automation/test`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.acaData.nonce,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                onShowToast('Test automation completed successfully', 'success');
                await refreshData();
            } else {
                throw new Error(data.message || 'Test failed');
            }
        } catch (error) {
            console.error('Failed to test automation:', error);
            onShowToast('Failed to test automation', 'error');
        } finally {
            setTesting(false);
        }
    };

    // Debounce function to prevent excessive API calls
    const debounce = (func: Function, wait: number) => {
        let timeout: NodeJS.Timeout;
        return (...args: any[]) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    };

    // Debounced refresh function
    const debouncedFetchStatus = useCallback(
        debounce(() => fetchStatus(), 500),
        [currentSettings]
    );

    useEffect(() => {
        const loadData = async () => {
            setLoading(true);
            try {
                await fetchStatus();
            } finally {
                setLoading(false);
            }
        };
        loadData();
    }, []);

    // AGENTS.MD FIX: Refresh when trigger changes or settings change
    useEffect(() => {
        if (refreshTrigger && refreshTrigger > 0) {
            debouncedFetchStatus();
        }
    }, [refreshTrigger, debouncedFetchStatus]);
    
    // AGENTS.MD FIX: Update status when currentSettings change (immediate UI sync)
    useEffect(() => {
        if (currentSettings && status) {
            const updatedStatus = { ...status };
            updatedStatus.mode = currentSettings.mode || status.mode;
            
            // Full-automatic settings immediate update
            if (updatedStatus.full_auto_settings) {
                updatedStatus.full_auto_settings.daily_post_count = currentSettings.fullAutoDailyPostCount || updatedStatus.full_auto_settings.daily_post_count;
                updatedStatus.full_auto_settings.publish_frequency = currentSettings.fullAutoPublishFrequency || updatedStatus.full_auto_settings.publish_frequency;
                updatedStatus.full_auto_settings.auto_publish_enabled = currentSettings.autoPublish !== undefined ? currentSettings.autoPublish : updatedStatus.full_auto_settings.auto_publish_enabled;
            }
            
            // Semi-automatic settings immediate update
            if (updatedStatus.workflow_info && currentSettings.mode === 'semi-automatic') {
                updatedStatus.workflow_info.idea_frequency = currentSettings.semiAutoIdeaFrequency || updatedStatus.workflow_info.idea_frequency;
            }
            
            setStatus(updatedStatus);
        }
    }, [currentSettings]);

    // Auto-refresh every 5 minutes (300 seconds) - reduced frequency for better performance
    useEffect(() => {
        const interval = setInterval(() => {
            // Only refresh if tab is visible and not already refreshing/testing
            if (!document.hidden && !refreshing && !testing) {
                fetchStatus();
            }
        }, 300000); // 5 minutes instead of 30 seconds

        return () => clearInterval(interval);
    }, [refreshing, testing]);

    if (loading) {
        return (
            <div className="aca-card" style={{ textAlign: 'center', padding: '40px' }}>
                <Spinner style={{ width: '24px', height: '24px', color: '#3b82f6', marginBottom: '10px' }} />
                <p className="aca-page-description">Loading automation status...</p>
            </div>
        );
    }

    if (!status) {
        return (
            <div className="aca-card" style={{ textAlign: 'center', padding: '40px' }}>
                <AlertCircle style={{ width: '48px', height: '48px', color: '#ef4444', marginBottom: '15px' }} />
                <h3 className="aca-card-title" style={{ marginBottom: '10px' }}>Failed to Load Automation Status</h3>
                <p className="aca-page-description" style={{ marginBottom: '20px' }}>Unable to fetch automation system status.</p>
                <button
                    onClick={refreshData}
                    className="aca-button aca-button-primary"
                    style={{ display: 'inline-flex', alignItems: 'center', gap: '8px' }}
                >
                    <RefreshCw style={{ width: '16px', height: '16px' }} />
                    Retry
                </button>
            </div>
        );
    }

    const getSystemStatusColor = () => {
        // AGENTS.MD CUSTOMER-FOCUSED FIX: Both setups are valid and should show green
        if (status.system_health === 'healthy') {
            return '#16a34a'; // Green for healthy systems
        }
        return '#f59e0b'; // Yellow/Orange only for actual issues
    };

    const getSystemStatusIcon = () => {
        // AGENTS.MD CUSTOMER-FOCUSED FIX: Both setups are valid and should show green
        if (status.system_health === 'healthy') {
            return <CheckCircle style={{ width: '20px', height: '20px', color: '#16a34a' }} />;
        }
        return <AlertCircle style={{ width: '20px', height: '20px', color: '#f59e0b' }} />;
    };

    // PHASE 3 BUG #7 FIX: Verify that tasks were actually scheduled
    const verifySchedulingSuccess = async (statusData: any): Promise<boolean> => {
        if (!statusData || !statusData.next_run_times) {
            return false;
        }

        const mode = statusData.mode || 'manual';
        const nextRuns = statusData.next_run_times;

        switch (mode) {
            case 'manual':
                // Manual mode should have no scheduled tasks (except maintenance)
                return true; // Manual mode is always "successful"
            
            case 'semi-automatic':
                // Semi-automatic should have idea_generation and maintenance scheduled
                return !!(nextRuns.idea_generation && nextRuns.maintenance);
            
            case 'full-automatic':
                // Full-automatic should have all tasks scheduled
                return !!(nextRuns.idea_generation && nextRuns.draft_creation && 
                         nextRuns.post_publishing && nextRuns.maintenance);
            
            default:
                return false;
        }
    };

    const triggerScheduling = async () => {
        setIsLoading(true);
        try {
            const response = await fetch(`${window.acaData.api_url}trigger-scheduling`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': window.acaData.nonce,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            if (data.success) {
                // PHASE 3 BUG #7 FIX: Verify actual scheduling success before showing success
                const actuallyScheduled = await verifySchedulingSuccess(data.status);
                
                if (actuallyScheduled) {
                    onShowToast('Automation workflow activated successfully', 'success');
                } else {
                    onShowToast('Automation activation completed, but some tasks may not be scheduled. Check debug logs.', 'warning');
                }
                
                // AGENTS.MD FIX: Use fresh data from trigger response immediately
                if (data.status) {
                    // Apply current settings override to fresh data
                    if (currentSettings) {
                        data.status.mode = currentSettings.mode || data.status.mode;
                        if (data.status.full_auto_settings) {
                            data.status.full_auto_settings.daily_post_count = currentSettings.fullAutoDailyPostCount || data.status.full_auto_settings.daily_post_count;
                            data.status.full_auto_settings.publish_frequency = currentSettings.fullAutoPublishFrequency || data.status.full_auto_settings.publish_frequency;
                            data.status.full_auto_settings.auto_publish_enabled = currentSettings.autoPublish !== undefined ? currentSettings.autoPublish : data.status.full_auto_settings.auto_publish_enabled;
                        }
                        if (data.status.workflow_info && currentSettings.mode === 'semi-automatic') {
                            data.status.workflow_info.idea_frequency = currentSettings.semiAutoIdeaFrequency || data.status.workflow_info.idea_frequency;
                        }
                    }
                    setStatus(data.status);
                }
                
                // Also refresh after a delay to ensure consistency
                setTimeout(() => {
                    refreshData();
                }, 1000);
            } else {
                throw new Error(data.message || 'Failed to activate automation');
            }
        } catch (error) {
            console.error('Failed to trigger scheduling:', error);
            onShowToast('Failed to activate automation', 'error');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div style={{ padding: '0' }}>
            {/* Page Header */}
            <div className="aca-card" style={{ marginBottom: '20px' }}>
                <div className="aca-card-header" style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '15px' }}>
                    <h2 className="aca-card-title" style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        gap: '8px',
                        color: '#1e293b'
                    }}>
                        <div style={{ 
                            width: '32px', 
                            height: '32px', 
                            background: 'linear-gradient(135deg, #10b981, #059669)',
                            borderRadius: '8px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center'
                        }}>
                            <Settings style={{ width: '18px', height: '18px', color: 'white' }} />
                        </div>
                        Unified Automation Manager
                    </h2>
                    <p style={{ margin: '8px 0 0 0', color: '#64748b', fontSize: '14px' }}>
                        Monitor your unified, WordPress-native automation system
                    </p>
                </div>
                
                <div style={{ 
                    display: 'flex', 
                    gap: '10px', 
                    flexWrap: 'wrap',
                    marginTop: '15px'
                }}>
                    <button
                        onClick={testAutomation}
                        disabled={testing}
                        className="aca-button aca-button-primary"
                        style={{ 
                            display: 'inline-flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            opacity: testing ? '0.6' : '1'
                        }}
                    >
                        {testing ? (
                            <Spinner style={{ width: '16px', height: '16px' }} />
                        ) : (
                            <Play style={{ width: '16px', height: '16px' }} />
                        )}
                        {testing ? 'Testing...' : 'Test Automation'}
                    </button>
                    <button
                        onClick={refreshData}
                        disabled={refreshing}
                        className="aca-button aca-button-secondary"
                        style={{ 
                            display: 'inline-flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            opacity: refreshing ? '0.6' : '1',
                            marginRight: '10px'
                        }}
                    >
                        <RefreshCw style={{ 
                            width: '16px', 
                            height: '16px',
                            animation: refreshing ? 'spin 1s linear infinite' : 'none'
                        }} />
                        Refresh Data
                    </button>
                    
                    {/* AGENTS.MD FIX: License refresh button for troubleshooting */}
                    <button
                        onClick={refreshLicense}
                        disabled={refreshing}
                        className="aca-button aca-button-secondary"
                        style={{ 
                            display: 'inline-flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            opacity: refreshing ? '0.6' : '1'
                        }}
                    >
                        <Shield style={{ 
                            width: '16px', 
                            height: '16px'
                        }} />
                        Refresh License
                    </button>
                </div>
            </div>

            {/* System Status */}
            <div className="aca-card" style={{ marginBottom: '20px' }}>
                <div style={{
                    padding: '20px',
                    background: status.wp_cron_disabled && status.system_status === 'Unified & Reliable'
                        ? 'linear-gradient(135deg, #d1fae5, #a7f3d0)'
                        : 'linear-gradient(135deg, #fef3c7, #fde68a)',
                    border: `1px solid ${getSystemStatusColor()}`,
                    borderRadius: '8px',
                    marginBottom: '20px'
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '10px' }}>
                        {getSystemStatusIcon()}
                        <h3 style={{ margin: 0, color: '#1f2937' }}>System Status</h3>
                    </div>
                    <p style={{ margin: 0, color: '#4b5563', fontSize: '14px' }}>
                        {status.system_status} - {status.wp_cron_disabled ? 'WP-Cron Disabled ✅' : 'WP-Cron Active ⚠️'}
                    </p>
                </div>

                {/* Memory Limit Warning */}
                {status.system_requirements && !status.system_requirements.memory_limit.sufficient && (
                    <div style={{
                        padding: '16px',
                        background: '#fef2f2',
                        border: '1px solid #fecaca',
                        borderRadius: '8px',
                        marginBottom: '20px',
                        display: 'flex',
                        gap: '12px',
                        alignItems: 'flex-start'
                    }}>
                        <AlertTriangle style={{ 
                            width: '20px', 
                            height: '20px', 
                            color: '#dc2626',
                            flexShrink: 0,
                            marginTop: '2px'
                        }} />
                        <div style={{ flex: 1 }}>
                            <h4 style={{ margin: '0 0 8px 0', color: '#991b1b', fontSize: '16px', fontWeight: '600' }}>
                                Memory Limit Too Low
                            </h4>
                            <p style={{ margin: '0 0 12px 0', color: '#7f1d1d', fontSize: '14px' }}>
                                Your WordPress memory limit is currently set to <strong>{status.system_requirements.memory_limit.current}</strong>. 
                                AI Content Agent requires at least <strong>{status.system_requirements.memory_limit.required}</strong> to function properly.
                            </p>
                            <div style={{ 
                                background: '#fee2e2', 
                                padding: '12px', 
                                borderRadius: '6px',
                                marginBottom: '12px'
                            }}>
                                <p style={{ margin: '0 0 8px 0', fontSize: '14px', fontWeight: '600', color: '#991b1b' }}>
                                    How to fix this:
                                </p>
                                <ol style={{ margin: '0', paddingLeft: '20px', fontSize: '13px', color: '#7f1d1d' }}>
                                    <li style={{ marginBottom: '6px' }}>
                                        Add this line to your <code style={{ 
                                            background: '#fecaca', 
                                            padding: '2px 4px', 
                                            borderRadius: '3px',
                                            fontSize: '12px'
                                        }}>wp-config.php</code> file:
                                        <pre style={{ 
                                            margin: '4px 0 0 0', 
                                            padding: '8px', 
                                            background: '#fee2e2',
                                            borderRadius: '4px',
                                            fontSize: '12px',
                                            overflow: 'auto'
                                        }}>
{`define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');`}
                                        </pre>
                                    </li>
                                    <li style={{ marginBottom: '6px' }}>
                                        Or contact your hosting provider to increase the PHP memory limit
                                    </li>
                                    <li>
                                        After making changes, refresh this page to verify
                                    </li>
                                </ol>
                            </div>
                            <div style={{ fontSize: '13px', color: '#7f1d1d' }}>
                                <strong>Current System Info:</strong>
                                <ul style={{ margin: '4px 0 0 0', paddingLeft: '20px' }}>
                                    <li>PHP Memory Limit: {status.system_requirements.php_memory_limit}</li>
                                    <li>Current Usage: {status.system_requirements.memory_usage.current_formatted}</li>
                                    <li>Peak Usage: {status.system_requirements.memory_usage.peak_formatted}</li>
                                    <li>Max Execution Time: {status.system_requirements.execution_time.current}s (recommended: {status.system_requirements.execution_time.recommended}s)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                )}

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '15px' }}>
                    {/* Automation Mode */}
                    <div style={{ textAlign: 'center', padding: '15px' }}>
                        <div style={{ 
                            fontSize: '14px', 
                            color: '#6b7280', 
                            marginBottom: '8px',
                            fontWeight: '500'
                        }}>
                            Automation Mode
                        </div>
                        <div style={{ 
                            fontSize: '20px', 
                            fontWeight: 'bold', 
                            color: status.mode === 'manual' ? '#6b7280' : 
                                   status.mode === 'semi-automatic' ? '#f59e0b' : '#10b981',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: '8px'
                        }}>
                            {status.mode === 'manual' && <Settings style={{ width: '20px', height: '20px' }} />}
                            {status.mode === 'semi-automatic' && <RefreshCw style={{ width: '20px', height: '20px' }} />}
                            {status.mode === 'full-automatic' && <Play style={{ width: '20px', height: '20px' }} />}
                            {status.mode.charAt(0).toUpperCase() + status.mode.slice(1).replace('-', ' ')}
                        </div>
                        {status.mode === 'manual' && (
                            <div style={{ fontSize: '11px', color: '#9ca3af', marginTop: '4px' }}>
                                Full control - You manage everything
                            </div>
                        )}
                        {status.mode === 'semi-automatic' && (
                            <div style={{ fontSize: '11px', color: '#9ca3af', marginTop: '4px' }}>
                                AI assists - You decide what to publish
                            </div>
                        )}
                        {status.mode === 'full-automatic' && (
                            <div style={{ fontSize: '11px', color: '#9ca3af', marginTop: '4px' }}>
                                Fully automated content pipeline
                            </div>
                        )}
                    </div>

                    {/* Last Run */}
                    <div style={{ textAlign: 'center', padding: '15px' }}>
                        <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#1f2937', marginBottom: '5px' }}>
                            {status.last_run !== 'Never' 
                                ? new Date(status.last_run).toLocaleTimeString([], { 
                                    hour: '2-digit', 
                                    minute: '2-digit' 
                                })
                                : 'Never'
                            }
                        </div>
                        <div style={{ fontSize: '12px', color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                            Last Run
                        </div>
                    </div>

                    {/* Next Scheduled */}
                    <div style={{ textAlign: 'center', padding: '15px' }}>
                        <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#1f2937', marginBottom: '5px' }}>
                            {status.idea_generation_scheduled 
                                ? new Date(status.idea_generation_scheduled * 1000).toLocaleTimeString([], { 
                                    hour: '2-digit', 
                                    minute: '2-digit' 
                                })
                                : 'Not Scheduled'
                            }
                        </div>
                        <div style={{ fontSize: '12px', color: '#6b7280', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
                            Next Scheduled
                        </div>
                    </div>
                </div>
            </div>

            {/* Setup Information - CUSTOMER-FRIENDLY APPROACH */}
            <div className="aca-card" style={{ marginBottom: '20px' }}>
                <div style={{
                    padding: '20px',
                    background: status.setup_type === 'advanced' ? '#f0f9ff' : '#f9fafb',
                    border: `1px solid ${status.setup_type === 'advanced' ? '#0ea5e9' : '#e5e7eb'}`,
                    borderRadius: '8px'
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '15px' }}>
                        <CheckCircle style={{ 
                            width: '20px', 
                            height: '20px', 
                            color: status.setup_type === 'advanced' ? '#0ea5e9' : '#16a34a' 
                        }} />
                        <h3 style={{ margin: 0, color: '#1f2937' }}>
                            {status.setup_type === 'advanced' ? 'Advanced Setup Active' : 'Standard Setup Active'}
                        </h3>
                    </div>
                    
                    {status.setup_type === 'advanced' ? (
                        <div>
                            <p style={{ margin: '0 0 15px 0', color: '#374151', fontSize: '14px' }}>
                                <strong>Server Cron Configuration:</strong> Your automation runs independently of website traffic using server-level cron jobs. This provides optimal reliability and performance.
                            </p>
                            <div style={{ background: '#1f2937', color: '#f9fafb', padding: '10px', borderRadius: '4px', fontSize: '12px', fontFamily: 'monospace', marginBottom: '10px' }}>
                                ✓ define('DISABLE_WP_CRON', true);
                            </div>
                            <p style={{ margin: '0', color: '#6b7280', fontSize: '12px' }}>
                                Make sure your hosting provider has configured the server cron job to run every 5 minutes.
                            </p>
                        </div>
                    ) : (
                        <div>
                            <p style={{ margin: '0 0 15px 0', color: '#374151', fontSize: '14px' }}>
                                <strong>WordPress Cron Configuration:</strong> Your automation runs automatically when visitors access your website. This is the standard setup that works out-of-the-box.
                            </p>
                            <div style={{ 
                                background: '#f3f4f6', 
                                color: '#374151', 
                                padding: '12px', 
                                borderRadius: '6px', 
                                fontSize: '13px',
                                border: '1px solid #d1d5db'
                            }}>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                                    <span style={{ fontSize: '16px' }}>💡</span>
                                    <strong>Want Better Performance?</strong>
                                </div>
                                <p style={{ margin: '0 0 8px 0', fontSize: '12px', color: '#6b7280' }}>
                                    Advanced users can switch to server cron for traffic-independent automation:
                                </p>
                                <div style={{ background: '#1f2937', color: '#f9fafb', padding: '8px', borderRadius: '4px', fontSize: '11px', fontFamily: 'monospace' }}>
                                    define('DISABLE_WP_CRON', true);
                                </div>
                                <p style={{ margin: '8px 0 0 0', fontSize: '11px', color: '#6b7280' }}>
                                    Then configure server cron job with your hosting provider.
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Health Status */}
            <div className="aca-card">
                <h3 className="aca-card-title" style={{ marginBottom: '15px' }}>System Health</h3>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0' }}>
                        <span>WP-Cron Status:</span>
                        <span style={{ 
                            color: status.wp_cron_disabled ? '#16a34a' : '#dc2626',
                            fontWeight: 'bold'
                        }}>
                            {status.wp_cron_disabled ? 'Disabled ✅' : 'Active ⚠️'}
                        </span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0' }}>
                        <span>System Type:</span>
                        <span style={{ color: '#16a34a', fontWeight: 'bold' }}>
                            {status.system_status}
                        </span>
                    </div>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0' }}>
                        <span>Maintenance Scheduled:</span>
                        <span style={{ color: status.maintenance_scheduled ? '#16a34a' : '#6b7280' }}>
                            {status.maintenance_scheduled ? 'Yes' : 'No'}
                        </span>
                    </div>
                </div>
            </div>

            {/* Workflow Status - AGENTS.MD UX ENHANCEMENT */}
            <div className="aca-card" style={{ marginBottom: '20px' }}>
                <div className="aca-card-header" style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '15px', marginBottom: '20px' }}>
                    <h3 className="aca-card-title" style={{ 
                        display: 'flex', 
                        alignItems: 'center', 
                        gap: '8px',
                        color: '#1e293b',
                        margin: 0
                    }}>
                        {getSystemStatusIcon()}
                        Automation Workflow Status
                    </h3>
                    <p style={{ margin: '8px 0 0 0', color: '#64748b', fontSize: '14px' }}>
                        Current mode: <strong>{status.mode.charAt(0).toUpperCase() + status.mode.slice(1)}</strong> • System: <strong>{status.system_status}</strong>
                    </p>
                </div>

                {/* Modern Icon-Based Workflow Status */}
                {status.mode !== 'manual' && (
                    <>
                    {/* Progress Indicator */}
                    <div style={{
                        marginBottom: '20px',
                        padding: '16px',
                        background: 'linear-gradient(to right, #f0f9ff, #e0f2fe)',
                        borderRadius: '8px',
                        border: '1px solid #0ea5e9'
                    }}>
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '12px' }}>
                            <h4 style={{ margin: 0, fontSize: '14px', color: '#0369a1', fontWeight: '600' }}>
                                Workflow Progress
                            </h4>
                            <span style={{ fontSize: '12px', color: '#0369a1' }}>
                                {status.mode === 'semi-automatic' ? '2/4 Steps Automated' : 
                                 status.full_auto_settings?.auto_publish_enabled ? '4/4 Steps Automated' : '3/4 Steps Automated'}
                            </span>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px' }}>
                            {/* Idea Generation */}
                            <div style={{ 
                                flex: 1, 
                                height: '8px', 
                                background: status.idea_generation_scheduled ? '#10b981' : '#e5e7eb',
                                borderRadius: '4px',
                                position: 'relative',
                                overflow: 'hidden'
                            }}>
                                {status.idea_generation_scheduled && (
                                    <div style={{
                                        position: 'absolute',
                                        top: 0,
                                        left: 0,
                                        height: '100%',
                                        width: '100%',
                                        background: 'linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.4) 50%, transparent 100%)',
                                        animation: 'shimmer 2s infinite'
                                    }} />
                                )}
                            </div>
                            {/* Draft Creation */}
                            <div style={{ 
                                flex: 1, 
                                height: '8px', 
                                background: status.mode === 'full-automatic' && status.draft_creation_scheduled ? '#10b981' : '#e5e7eb',
                                borderRadius: '4px'
                            }} />
                            {/* Review */}
                            <div style={{ 
                                flex: 1, 
                                height: '8px', 
                                background: status.mode === 'full-automatic' ? '#10b981' : '#e5e7eb',
                                borderRadius: '4px'
                            }} />
                            {/* Publishing */}
                            <div style={{ 
                                flex: 1, 
                                height: '8px', 
                                background: status.mode === 'full-automatic' && status.full_auto_settings?.auto_publish_enabled && status.post_publishing_scheduled ? '#10b981' : '#e5e7eb',
                                borderRadius: '4px'
                            }} />
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '8px' }}>
                            <span style={{ fontSize: '10px', color: '#64748b' }}>Ideas</span>
                            <span style={{ fontSize: '10px', color: '#64748b' }}>Drafts</span>
                            <span style={{ fontSize: '10px', color: '#64748b' }}>Review</span>
                            <span style={{ fontSize: '10px', color: '#64748b' }}>Publish</span>
                        </div>
                    </div>

                    <div style={{ 
                        display: 'grid', 
                        gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', 
                        gap: '15px',
                        marginBottom: '15px'
                    }}>
                        {/* Active Mode */}
                        <div style={{ 
                            display: 'flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            padding: '12px',
                            background: '#f0f9ff',
                            borderRadius: '8px',
                            border: '1px solid #0ea5e9'
                        }}>
                            <Settings style={{ width: '16px', height: '16px', color: '#0ea5e9' }} />
                            <div>
                                <div style={{ fontSize: '11px', color: '#0ea5e9', fontWeight: '600' }}>Active Mode</div>
                                <div style={{ fontSize: '13px', color: '#1f2937', textTransform: 'capitalize' }}>
                                    {status.mode.replace('-', ' ')}
                                </div>
                            </div>
                        </div>

                        {/* Next Idea Generation */}
                        <div style={{ 
                            display: 'flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            padding: '12px',
                            background: status.next_run_times?.idea_generation ? '#f0fdf4' : '#fef3c7',
                            borderRadius: '8px',
                            border: `1px solid ${status.next_run_times?.idea_generation ? '#16a34a' : '#f59e0b'}`
                        }}>
                            <Lightbulb style={{ 
                                width: '16px', 
                                height: '16px', 
                                color: status.next_run_times?.idea_generation ? '#16a34a' : '#f59e0b' 
                            }} />
                            <div>
                                <div style={{ 
                                    fontSize: '11px', 
                                    color: status.next_run_times?.idea_generation ? '#16a34a' : '#f59e0b', 
                                    fontWeight: '600' 
                                }}>
                                    Next Idea Generation
                                </div>
                                <div style={{ fontSize: '12px', color: '#1f2937' }}>
                                    {status.next_run_times?.idea_generation ? 
                                        new Date(status.next_run_times.idea_generation).toLocaleString() : 
                                        'Not Scheduled'
                                    }
                                </div>
                            </div>
                        </div>

                        {/* Draft Creation */}
                        <div style={{ 
                            display: 'flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            padding: '12px',
                            background: status.draft_creation_scheduled ? '#f0fdf4' : '#f3f4f6',
                            borderRadius: '8px',
                            border: `1px solid ${status.draft_creation_scheduled ? '#16a34a' : '#d1d5db'}`
                        }}>
                            <FileText style={{ 
                                width: '16px', 
                                height: '16px', 
                                color: status.draft_creation_scheduled ? '#16a34a' : '#9ca3af' 
                            }} />
                            <div>
                                <div style={{ 
                                    fontSize: '11px', 
                                    color: status.draft_creation_scheduled ? '#16a34a' : '#9ca3af', 
                                    fontWeight: '600' 
                                }}>
                                    Draft Creation
                                </div>
                                <div style={{ fontSize: '12px', color: '#1f2937' }}>
                                    {status.draft_creation_scheduled ? 
                                        'Scheduled' : 
                                        status.mode === 'full-automatic' ? 'Pending' : 'Manual'
                                    }
                                </div>
                            </div>
                        </div>

                        {/* Auto Publishing */}
                        {status.mode === 'full-automatic' && (
                            <div style={{ 
                                display: 'flex', 
                                alignItems: 'center', 
                                gap: '8px',
                                padding: '12px',
                                background: status.full_auto_settings?.auto_publish_enabled ? 
                                    (status.post_publishing_scheduled ? '#f0fdf4' : '#fef3c7') : 
                                    '#f3f4f6',
                                borderRadius: '8px',
                                border: `1px solid ${
                                    status.full_auto_settings?.auto_publish_enabled ? 
                                        (status.post_publishing_scheduled ? '#16a34a' : '#f59e0b') : 
                                        '#d1d5db'
                                }`
                            }}>
                                <Send style={{ 
                                    width: '16px', 
                                    height: '16px', 
                                    color: status.full_auto_settings?.auto_publish_enabled ? 
                                        (status.post_publishing_scheduled ? '#16a34a' : '#f59e0b') : 
                                        '#9ca3af'
                                }} />
                                <div>
                                    <div style={{ 
                                        fontSize: '11px', 
                                        color: status.full_auto_settings?.auto_publish_enabled ? 
                                            (status.post_publishing_scheduled ? '#16a34a' : '#f59e0b') : 
                                            '#9ca3af', 
                                        fontWeight: '600' 
                                    }}>
                                        Auto Publishing
                                    </div>
                                    <div style={{ fontSize: '12px', color: '#1f2937' }}>
                                        {status.full_auto_settings?.auto_publish_enabled ? 
                                            (status.post_publishing_scheduled ? 'Scheduled' : 'Pending') : 
                                            'Disabled'
                                        }
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Last Activity */}
                        <div style={{ 
                            display: 'flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            padding: '12px',
                            background: '#f8fafc',
                            borderRadius: '8px',
                            border: '1px solid #e2e8f0'
                        }}>
                            <Clock style={{ width: '16px', height: '16px', color: '#64748b' }} />
                            <div>
                                <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '600' }}>Last Activity</div>
                                <div style={{ fontSize: '12px', color: '#1f2937' }}>
                                    {status.last_run && status.last_run !== 'Never' ? status.last_run : 'No activity yet'}
                                </div>
                            </div>
                        </div>
                    </div>
                    </>
                )}

                {/* Manual Trigger Button for Not Scheduled Items */}
                {(!status.idea_generation_scheduled) && status.mode !== 'manual' && (
                    <div style={{ 
                        marginTop: '15px', 
                        padding: '12px', 
                        background: '#fef3c7', 
                        borderRadius: '8px',
                        border: '1px solid #f59e0b'
                    }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                            <AlertTriangle style={{ width: '16px', height: '16px', color: '#f59e0b' }} />
                            <span style={{ fontSize: '13px', color: '#92400e', fontWeight: '600' }}>
                                Scheduling Required
                            </span>
                        </div>
                        <p style={{ fontSize: '12px', color: '#92400e', margin: '0 0 10px 0' }}>
                            Some automation tasks are not scheduled. Click below to activate your automation workflow.
                        </p>
                        <button
                            onClick={triggerScheduling}
                            disabled={isLoading}
                            style={{
                                padding: '8px 16px',
                                background: '#f59e0b',
                                color: 'white',
                                border: 'none',
                                borderRadius: '6px',
                                fontSize: '12px',
                                fontWeight: '600',
                                cursor: isLoading ? 'not-allowed' : 'pointer',
                                opacity: isLoading ? 0.6 : 1,
                                display: 'flex',
                                alignItems: 'center',
                                gap: '6px'
                            }}
                        >
                            <RefreshCw style={{ width: '14px', height: '14px' }} />
                            {isLoading ? 'Activating...' : 'Activate Automation'}
                        </button>
                    </div>
                )}

                {/* Workflow Explanation */}
                {status.mode !== 'manual' && (
                    <div style={{ 
                        marginTop: '20px', 
                        padding: '15px', 
                        background: '#f8fafc', 
                        borderRadius: '8px',
                        border: '1px solid #e2e8f0'
                    }}>
                        <div style={{ fontSize: '13px', color: '#475569', marginBottom: '8px' }}>
                            <strong>Current Workflow:</strong> {' '}
                            {status.mode === 'semi-automatic' ? (
                                'AI generates 5 ideas automatically → You review and create drafts → You publish manually'
                            ) : (
                                `AI generates ideas → AI creates drafts → ${status.full_auto_settings?.auto_publish_enabled ? 'AI publishes automatically' : 'You review and publish'}`
                            )}
                        </div>
                        {status.mode === 'full-automatic' && status.full_auto_settings && (
                            <div>
                                <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '8px' }}>
                                    <strong>Configuration:</strong> {status.full_auto_settings.daily_post_count} posts/day • 
                                    Frequency: {status.full_auto_settings.publish_frequency} • 
                                    Auto-publish: {status.full_auto_settings.auto_publish_enabled ? 'Enabled' : 'Disabled'}
                                </div>
                                <div style={{ 
                                    fontSize: '11px', 
                                    color: '#6b7280', 
                                    padding: '8px', 
                                    background: '#ffffff',
                                    borderRadius: '4px',
                                    border: '1px solid #e5e7eb'
                                }}>
                                    <strong>Smart Timing:</strong> {' '}
                                    {status.full_auto_settings.daily_post_count === 1 && 'Ideas generated daily (10 per batch) → Drafts created every 4 hours → '}
                                    {status.full_auto_settings.daily_post_count === 2 && 'Ideas generated every 12 hours (3 per batch) → Drafts created every 4 hours → '}
                                    {status.full_auto_settings.daily_post_count === 3 && 'Ideas generated every 12 hours (5 per batch) → Drafts created every 4 hours → '}
                                    {status.full_auto_settings.daily_post_count >= 5 && 'Ideas generated every 6 hours (4 per batch) → Drafts created every 30 minutes → '}
                                    {status.full_auto_settings.publish_frequency === 'hourly' && 'Published every hour'}
                                    {status.full_auto_settings.publish_frequency === 'daily' && 'Published daily'}
                                    {status.full_auto_settings.publish_frequency === 'weekly' && 'Published weekly'}
                                </div>
                            </div>
                        )}
                        {status.mode === 'semi-automatic' && status.workflow_info && (
                            <div>
                                <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '8px' }}>
                                    <strong>Configuration:</strong> 5 ideas per generation • 
                                    Frequency: {status.workflow_info.idea_frequency} • 
                                    Draft creation: Manual • Publishing: Manual
                                </div>
                                <div style={{ 
                                    fontSize: '11px', 
                                    color: '#6b7280', 
                                    padding: '8px', 
                                    background: '#ffffff',
                                    borderRadius: '4px',
                                    border: '1px solid #e5e7eb'
                                }}>
                                    <strong>Smart Balance:</strong> AI generates 5 fresh ideas {status.workflow_info.idea_frequency} → You review and select the best ones → You create drafts at your own pace → You publish when ready. Perfect balance of automation and control.
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};