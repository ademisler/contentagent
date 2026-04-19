import React, { useState, useEffect } from 'react';
import { Brain, AlertCircle, CheckCircle, Info, Play, RefreshCw, Clock, Server, Database, Activity } from './Icons';
import { SettingsLayout } from './SettingsLayout';
import { UpgradePrompt } from './UpgradePrompt';

interface SettingsAdvancedProps {
    onShowToast: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
    isProActive?: boolean;
}

interface DebugInfo {
    automation_status?: any;
    system_info?: any;
    cron_jobs?: any;
    last_errors?: any[];
}

interface CacheStatus {
    transient_count: number;
    object_cache_enabled: boolean;
    last_clear: string;
    assets_version: string;
}

declare global {
    interface Window {
        acaData: {
            nonce: string;
            api_url: string;
            admin_url: string;
            plugin_url: string;
            is_pro?: boolean;
        };
    }
}

export const SettingsAdvanced: React.FC<SettingsAdvancedProps> = ({ 
    onShowToast,
    isProActive
}) => {
    const [debugInfo, setDebugInfo] = useState<DebugInfo | null>(null);
    const [loading, setLoading] = useState(false);
    const [testingCron, setTestingCron] = useState<string | null>(null);
    const [clearingCache, setClearingCache] = useState(false);
    const [cacheStatus, setCacheStatus] = useState<CacheStatus | null>(null);

    const fetchDebugInfo = async () => {
        setLoading(true);
        try {
            const response = await fetch(`${window.acaData.api_url}debug/automation`, {
                headers: { 'X-WP-Nonce': window.acaData.nonce }
            });
            const data = await response.json();
            setDebugInfo(data);
            console.log('Automation Debug Info:', data);
        } catch (error) {
            console.error('Error fetching debug info:', error);
            onShowToast('Failed to fetch debug information', 'error');
        } finally {
            setLoading(false);
        }
    };

    const clearCache = async () => {
        // Confirm before clearing
        if (!window.confirm('This will clear all plugin caches and refresh the page. Any unsaved changes will be lost. Continue?')) {
            return;
        }
        
        setClearingCache(true);
        try {
            const response = await fetch(`${window.acaData.api_url}cache/clear`, {
                method: 'POST',
                headers: { 
                    'X-WP-Nonce': window.acaData.nonce,
                    'Content-Type': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error('Failed to clear cache');
            }
            
            const result = await response.json();
            
            if (result.success) {
                onShowToast('Cache cleared successfully! All data will be refreshed.', 'success');
                // Reload the page after a short delay to ensure fresh data
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(result.message || 'Failed to clear cache');
            }
        } catch (error) {
            console.error('Error clearing cache:', error);
            onShowToast('Failed to clear cache: ' + (error instanceof Error ? error.message : 'Unknown error'), 'error');
        } finally {
            setClearingCache(false);
        }
    };

    const fetchCacheStatus = async () => {
        try {
            const response = await fetch(`${window.acaData.api_url}cache/status`, {
                headers: { 'X-WP-Nonce': window.acaData.nonce }
            });
            const data = await response.json();
            setCacheStatus(data);
        } catch (error) {
            console.error('Error fetching cache status:', error);
        }
    };

    useEffect(() => {
        if (isProActive) {
            fetchDebugInfo();
            fetchCacheStatus();
        }
    }, [isProActive]);

    if (!isProActive) {
        return (
            <SettingsLayout
                title="Advanced & Debug"
                description="Developer tools and advanced debugging features for automation testing"
                icon={<Brain style={{ width: '24px', height: '24px', color: 'white' }} />}
            >
                <UpgradePrompt 
                    title="Advanced & Debug is a Pro Feature"
                    description="Unlock advanced diagnostics, cache management, and cron testing tools."
                    features={[
                        'Automation debug console',
                        'Cache management controls',
                        'Cron scheduling test triggers',
                        'System status and diagnostics'
                    ]}
                />
            </SettingsLayout>
        );
    }

    const handleCheckAutomationStatus = () => {
        fetchDebugInfo();
        onShowToast('Debug info refreshed and logged to console', 'info');
    };

    const handleTestCron = async (type: 'semi-auto' | 'full-auto') => {
        setTestingCron(type);
        try {
            const response = await fetch(`${window.acaData.api_url}debug/cron/${type}`, {
                method: 'POST',
                headers: { 'X-WP-Nonce': window.acaData.nonce }
            });
            const data = await response.json();
            
            if (data.success) {
                onShowToast(data.message || `${type} cron triggered successfully`, 'success');
                // Show detailed result
                if (data.result) {
                    console.log(`${type} cron result:`, data.result);
                }
                // Refresh debug info after test
                setTimeout(() => fetchDebugInfo(), 2000);
            } else {
                throw new Error(data.message || 'Test failed');
            }
        } catch (error) {
            console.error(`Error testing ${type} cron:`, error);
            onShowToast(`Failed to test ${type} cron`, 'error');
        } finally {
            setTestingCron(null);
        }
    };

    const formatNextRun = (timestamp: number | false) => {
        if (!timestamp) return 'Not scheduled';
        const date = new Date(timestamp * 1000);
        return date.toLocaleString();
    };

    return (
        <SettingsLayout
            title="Advanced & Debug"
            description="Developer tools and advanced debugging features for automation testing"
            icon={<Brain style={{ width: '24px', height: '24px', color: 'white' }} />}
        >
            <div className="aca-alert info" style={{ marginBottom: '20px' }}>
                <p style={{ margin: 0, fontSize: '14px' }}>
                    <strong>🛠️ For Developers & Advanced Users:</strong> This panel is designed for testing and debugging automation features. 
                    Use these tools to manually trigger automation tasks, check cron job status, and troubleshoot issues. 
                    Regular users typically don't need to use this panel.
                </p>
            </div>
            
            {/* Quick Actions */}
            <div className="aca-card" style={{ marginBottom: '20px' }}>
                <h3 className="aca-card-title" style={{ marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <Activity style={{ width: '20px', height: '20px', color: '#3b82f6' }} />
                    Quick Actions
                </h3>
                <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                    <button 
                        className="aca-action-button"
                        onClick={handleCheckAutomationStatus}
                        disabled={loading}
                        style={{
                            padding: '12px 20px',
                            backgroundColor: '#3b82f6',
                            color: 'white',
                            border: 'none',
                            borderRadius: '8px',
                            cursor: loading ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '500',
                            transition: 'background-color 0.2s',
                            opacity: loading ? 0.6 : 1,
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px'
                        }}
                        onMouseEnter={(e) => !loading && (e.currentTarget.style.backgroundColor = '#2563eb')}
                        onMouseLeave={(e) => !loading && (e.currentTarget.style.backgroundColor = '#3b82f6')}
                    >
                        <RefreshCw style={{ width: '16px', height: '16px', animation: loading ? 'spin 1s linear infinite' : 'none' }} />
                        {loading ? 'Loading...' : 'Refresh Debug Info'}
                    </button>
                    
                    <button 
                        className="aca-action-button"
                        onClick={() => handleTestCron('semi-auto')}
                        disabled={testingCron !== null}
                        style={{
                            padding: '12px 20px',
                            backgroundColor: '#10b981',
                            color: 'white',
                            border: 'none',
                            borderRadius: '8px',
                            cursor: testingCron !== null ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '500',
                            transition: 'background-color 0.2s',
                            opacity: testingCron === 'semi-auto' ? 0.6 : 1,
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px'
                        }}
                        onMouseEnter={(e) => !testingCron && (e.currentTarget.style.backgroundColor = '#059669')}
                        onMouseLeave={(e) => !testingCron && (e.currentTarget.style.backgroundColor = '#10b981')}
                    >
                        <Play style={{ width: '16px', height: '16px' }} />
                        {testingCron === 'semi-auto' ? 'Testing...' : 'Test Semi-Auto (5 Ideas)'}
                    </button>
                    
                    <button 
                        className="aca-action-button"
                        onClick={() => handleTestCron('full-auto')}
                        disabled={testingCron !== null}
                        style={{
                            padding: '12px 20px',
                            backgroundColor: '#f59e0b',
                            color: 'white',
                            border: 'none',
                            borderRadius: '8px',
                            cursor: testingCron !== null ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '500',
                            transition: 'background-color 0.2s',
                            opacity: testingCron === 'full-auto' ? 0.6 : 1,
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px'
                        }}
                        onMouseEnter={(e) => !testingCron && (e.currentTarget.style.backgroundColor = '#d97706')}
                        onMouseLeave={(e) => !testingCron && (e.currentTarget.style.backgroundColor = '#f59e0b')}
                    >
                        <Play style={{ width: '16px', height: '16px' }} />
                        {testingCron === 'full-auto' ? 'Testing...' : 'Test Full-Auto (1 Idea)'}
                    </button>
                </div>
            </div>
            
            {/* Cache Management */}
            <div className="aca-card" style={{ marginBottom: '20px' }}>
                <h3 className="aca-card-title" style={{ marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                    <Database style={{ width: '20px', height: '20px', color: '#10b981' }} />
                    Cache Management
                </h3>
                
                <div className="aca-alert info" style={{ marginBottom: '15px' }}>
                    <p style={{ margin: 0, fontSize: '14px' }}>
                        <strong>ℹ️ About Cache:</strong> The plugin uses caching to improve performance. 
                        If you're not seeing updated data after making changes, clearing the cache will force a refresh of all stored data.
                    </p>
                </div>
                
                {cacheStatus && (
                    <div style={{ 
                        backgroundColor: '#f3f4f6', 
                        padding: '15px', 
                        borderRadius: '8px',
                        marginBottom: '15px',
                        fontSize: '14px'
                    }}>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '15px' }}>
                            <div>
                                <strong style={{ color: '#6b7280' }}>Transients:</strong>
                                <div style={{ fontSize: '16px', color: '#111827', marginTop: '4px' }}>
                                    {cacheStatus.transient_count} cached items
                                </div>
                            </div>
                            <div>
                                <strong style={{ color: '#6b7280' }}>Object Cache:</strong>
                                <div style={{ fontSize: '16px', color: '#111827', marginTop: '4px' }}>
                                    {cacheStatus.object_cache_enabled ? (
                                        <span style={{ color: '#10b981' }}>✓ Enabled</span>
                                    ) : (
                                        <span style={{ color: '#6b7280' }}>Disabled</span>
                                    )}
                                </div>
                            </div>
                            <div>
                                <strong style={{ color: '#6b7280' }}>Last Cleared:</strong>
                                <div style={{ fontSize: '16px', color: '#111827', marginTop: '4px' }}>
                                    {cacheStatus.last_clear}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
                
                <div style={{ display: 'flex', alignItems: 'center', gap: '15px' }}>
                    <button 
                        className="aca-action-button"
                        onClick={clearCache}
                        disabled={clearingCache}
                        style={{
                            padding: '12px 24px',
                            backgroundColor: '#ef4444',
                            color: 'white',
                            border: 'none',
                            borderRadius: '8px',
                            cursor: clearingCache ? 'not-allowed' : 'pointer',
                            fontSize: '14px',
                            fontWeight: '500',
                            transition: 'background-color 0.2s',
                            opacity: clearingCache ? 0.6 : 1,
                            display: 'flex',
                            alignItems: 'center',
                            gap: '8px'
                        }}
                        onMouseEnter={(e) => !clearingCache && (e.currentTarget.style.backgroundColor = '#dc2626')}
                        onMouseLeave={(e) => !clearingCache && (e.currentTarget.style.backgroundColor = '#ef4444')}
                    >
                        <RefreshCw style={{ 
                            width: '16px', 
                            height: '16px', 
                            animation: clearingCache ? 'spin 1s linear infinite' : 'none' 
                        }} />
                        {clearingCache ? 'Clearing Cache...' : 'Clear All Cache'}
                    </button>
                    
                    <span style={{ fontSize: '13px', color: '#6b7280' }}>
                        This will clear all plugin caches and refresh the page
                    </span>
                </div>
            </div>
            
            {/* System Status */}
            {debugInfo && (
                <>
                    <div className="aca-card" style={{ marginBottom: '20px' }}>
                        <h3 className="aca-card-title" style={{ marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <Server style={{ width: '20px', height: '20px', color: '#10b981' }} />
                            System Status
                        </h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(250px, 1fr))', gap: '15px' }}>
                            <div style={{ padding: '15px', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                                <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: '4px' }}>Automation System</div>
                                <div style={{ fontSize: '16px', fontWeight: '600', color: '#1f2937' }}>
                                    {debugInfo.automation_status?.system_status || 'Unknown'}
                                </div>
                            </div>
                            <div style={{ padding: '15px', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                                <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: '4px' }}>Current Mode</div>
                                <div style={{ fontSize: '16px', fontWeight: '600', color: '#1f2937', textTransform: 'capitalize' }}>
                                    {debugInfo.automation_status?.mode?.replace('-', ' ') || 'Manual'}
                                </div>
                            </div>
                            <div style={{ padding: '15px', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                                <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: '4px' }}>WP-Cron Status</div>
                                <div style={{ fontSize: '16px', fontWeight: '600', color: debugInfo.wp_cron_disabled ? '#dc2626' : '#16a34a' }}>
                                    {debugInfo.wp_cron_disabled ? 'Disabled' : 'Active'}
                                </div>
                            </div>
                            <div style={{ padding: '15px', background: '#f9fafb', borderRadius: '8px', border: '1px solid #e5e7eb' }}>
                                <div style={{ fontSize: '12px', color: '#6b7280', marginBottom: '4px' }}>Memory Limit</div>
                                <div style={{ fontSize: '16px', fontWeight: '600', color: '#1f2937' }}>
                                    {debugInfo.memory_limit || 'Unknown'}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Scheduled Tasks */}
                    <div className="aca-card" style={{ marginBottom: '20px' }}>
                        <h3 className="aca-card-title" style={{ marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <Clock style={{ width: '20px', height: '20px', color: '#f59e0b' }} />
                            Scheduled Tasks
                        </h3>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                            {debugInfo.cron_jobs && Object.entries(debugInfo.cron_jobs).map(([hook, timestamp]) => (
                                <div key={hook} style={{ 
                                    padding: '12px', 
                                    background: '#f9fafb', 
                                    borderRadius: '6px', 
                                    border: '1px solid #e5e7eb',
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center'
                                }}>
                                    <div>
                                        <div style={{ fontSize: '14px', fontWeight: '500', color: '#1f2937' }}>{hook}</div>
                                        <div style={{ fontSize: '12px', color: '#6b7280' }}>
                                            {formatNextRun(timestamp as number | false)}
                                        </div>
                                    </div>
                                    <div>
                                        {timestamp ? (
                                            <CheckCircle style={{ width: '20px', height: '20px', color: '#16a34a' }} />
                                        ) : (
                                            <AlertCircle style={{ width: '20px', height: '20px', color: '#dc2626' }} />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Debug Console */}
                    <div className="aca-card">
                        <h3 className="aca-card-title" style={{ marginBottom: '15px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <Database style={{ width: '20px', height: '20px', color: '#6366f1' }} />
                            Debug Console
                        </h3>
                        <div style={{ 
                            background: '#1f2937', 
                            color: '#f9fafb', 
                            padding: '15px', 
                            borderRadius: '8px', 
                            fontSize: '12px', 
                            fontFamily: 'monospace',
                            maxHeight: '300px',
                            overflow: 'auto'
                        }}>
                            <pre style={{ margin: 0 }}>
                                {JSON.stringify(debugInfo, null, 2)}
                            </pre>
                        </div>
                        <p style={{ marginTop: '10px', fontSize: '12px', color: '#6b7280' }}>
                            <Info style={{ width: '14px', height: '14px', display: 'inline', marginRight: '4px' }} />
                            Full debug information is also logged to browser console (F12)
                        </p>
                    </div>
                </>
            )}

            <div className="aca-alert warning" style={{ marginTop: '20px' }}>
                <p style={{ margin: 0, fontSize: '13px' }}>
                    <strong>⚠️ Important:</strong> These debug functions are for testing purposes only. 
                    They may trigger content generation or publishing actions. Use with caution on production sites.
                </p>
            </div>
        </SettingsLayout>
    );
};