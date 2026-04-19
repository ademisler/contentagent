import React, { useState, useEffect } from 'react';
import type { AppSettings, AutomationMode } from '../types';
import { Zap, Shield } from './Icons';
import { SettingsLayout } from './SettingsLayout';
import { UpgradePrompt } from './UpgradePrompt';
import { licenseApi } from '../services/wordpressApi';
import { SimpleAutomationManager } from './SimpleAutomationManager';




interface SettingsAutomationProps {
    settings: AppSettings;
    onSaveSettings: (settings: AppSettings) => void;
    onShowToast: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
    automationRefreshTrigger?: number; // AGENTS.MD FIX: Trigger automation refresh
}

const RadioCard: React.FC<{
    id: AutomationMode;
    title: string;
    description: string;
    currentSelection: AutomationMode;
    onChange: (mode: AutomationMode) => void;
}> = ({ id, title, description, currentSelection, onChange }) => {
    const isChecked = currentSelection === id;
    return (
        <label 
            htmlFor={id} 
            className="aca-card"
            style={{
                margin: 0,
                border: '2px solid',
                borderColor: isChecked ? '#0073aa' : '#ccd0d4',
                background: isChecked ? '#f0f6fc' : '#ffffff',
                boxShadow: isChecked ? '0 2px 4px rgba(0, 0, 0, 0.1)' : 'none',
                cursor: 'pointer'
            }}
        >
            <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                <input
                    type="radio"
                    id={id}
                    name="automation-mode"
                    checked={isChecked}
                    onChange={() => onChange(id)}
                    style={{
                        marginTop: '2px',
                        width: '18px',
                        height: '18px',
                        accentColor: '#0073aa',
                        flexShrink: 0
                    }}
                />
                <div>
                    <h4 className="aca-card-title" style={{ marginBottom: '8px' }}>
                        {title}
                    </h4>
                    <p className="aca-page-description" style={{ margin: 0 }}>
                        {description}
                    </p>
                </div>
            </div>
        </label>
    );
};

export const SettingsAutomation: React.FC<SettingsAutomationProps> = ({ 
    settings, 
    onSaveSettings, 
    onShowToast,
    automationRefreshTrigger
}) => {
    const [currentSettings, setCurrentSettings] = useState<AppSettings>(settings);
    const [licenseStatus, setLicenseStatus] = useState<{
        status: string, 
        is_active: boolean, 
        verified_at?: string
    }>({status: 'inactive', is_active: false});
    const [isLoadingLicenseStatus, setIsLoadingLicenseStatus] = useState(true);
    const [isDirty, setIsDirty] = useState(false);
    const [isSaving, setIsSaving] = useState(false);

    // Load license status on component mount
    useEffect(() => {
        const loadLicenseStatus = async () => {
            try {
                const data = await licenseApi.getStatus();
                setLicenseStatus({
                    status: data.status || 'inactive',
                    is_active: data.is_active || false,
                    verified_at: data.verified_at || undefined
                });
            } catch (error) {
                console.error('Failed to load license status:', error);
                // Set default inactive state on error
                setLicenseStatus({
                    status: 'inactive',
                    is_active: false,
                    verified_at: undefined
                });
            } finally {
                setIsLoadingLicenseStatus(false);
            }
        };
        
        loadLicenseStatus();
    }, []);
    
    // Sync license status with current settings
    useEffect(() => {
        if (licenseStatus && licenseStatus.is_active !== undefined) {
            // setIsProActive(licenseStatus.is_active); // This line was removed
        }
        }, [licenseStatus]);
    
    // AGENTS.MD FIX: Sync currentSettings when props.settings changes
    useEffect(() => {
        setCurrentSettings(settings);
        setIsDirty(false); // Reset dirty state when new settings come from parent
    }, [settings]);
    
    const isProActive = () => {
        // Keep consistent with Settings.tsx logic
        return currentSettings.is_pro || licenseStatus.is_active;
    };

    const provideModeChangeGuidance = (newMode: AutomationMode, previousMode: AutomationMode) => {
        const pro = isProActive();
        switch (newMode) {
            case 'manual':
                if (previousMode === 'semi-automatic' || previousMode === 'full-automatic') {
                    onShowToast('Switched to Manual mode. Automation will be disabled when settings are saved.', 'info');
                }
                break;
            case 'semi-automatic':
                if (!pro) {
                    onShowToast('Semi-automatic mode requires Pro license. Please upgrade to activate automation.', 'warning');
                } else {
                    onShowToast('Switched to Semi-automatic mode. AI will generate ideas automatically when saved.', 'success');
                }
                break;
            case 'full-automatic':
                if (!pro) {
                    onShowToast('Full-automatic mode requires Pro license. Please upgrade to activate automation.', 'warning');
                } else {
                    onShowToast('Switched to Full-automatic mode. Complete workflow will be automated when saved.', 'success');
                }
                break;
        }
    };

    const handleModeChange = (mode: AutomationMode) => {
        const previousMode = currentSettings.mode;
        handleSettingChange('mode', mode);
        if (mode !== previousMode) {
            provideModeChangeGuidance(mode, previousMode);
        }
    };

    const handleSettingChange = (key: keyof AppSettings, value: any) => {
        const updatedSettings = { ...currentSettings, [key]: value };
        setCurrentSettings(updatedSettings);
        setIsDirty(true);
    };

    const handleSave = async () => {
        if (!isDirty) return;
        
        setIsSaving(true);
        try {
            await onSaveSettings(currentSettings);
            setIsDirty(false);
            onShowToast('Automation settings saved successfully!', 'success');
        } catch (error) {
            onShowToast('Failed to save automation settings', 'error');
        } finally {
            setIsSaving(false);
        }
    };

    if (isLoadingLicenseStatus) {
        return (
            <SettingsLayout
                title="Automation Mode"
                description="Configure how AI Content Agent creates and publishes content automatically"
                icon={<Zap style={{ width: '24px', height: '24px', color: 'white' }} />}
            >
                <div style={{ padding: '40px', textAlign: 'center', color: '#666' }}>
                    Loading license status...
                </div>
            </SettingsLayout>
        );
    }

    // If Pro is not active, show full-page Pro placeholder
    if (!isProActive()) {
        return (
            <SettingsLayout
                title="Automation Mode"
                description="Configure how AI Content Agent creates and publishes content automatically"
                icon={<Zap style={{ width: '24px', height: '24px', color: 'white' }} />}
            >
                <UpgradePrompt 
                    title="Automation Features Require Pro License"
                    description="Unlock powerful automation modes including semi-automatic and full-automatic content generation."
                    features={[
                        'Semi-Automatic Mode - AI generates ideas automatically',
                        'Full-Automatic Mode - Complete hands-off content creation',
                        'Flexible scheduling options',
                        'Auto-publish capabilities'
                    ]}
                />
            </SettingsLayout>
        );
    }

    const saveButton = isDirty ? (
        <button
            onClick={handleSave}
            disabled={isSaving}
            className="aca-button aca-button-primary"
            style={{ minWidth: '120px' }}
        >
            {isSaving ? 'Saving...' : 'Save Changes'}
        </button>
    ) : null;

    return (
        <SettingsLayout
            title="Automation Mode"
            description="Configure how AI Content Agent creates and publishes content automatically"
            icon={<Zap style={{ width: '24px', height: '24px', color: 'white' }} />}
            actions={saveButton}
        >
            <div>
                <p className="aca-page-description" style={{ marginBottom: '20px' }}>
                    Choose how you want the AI Content Agent (ACA) to operate. You can change this at any time.
                </p>
            
                <div style={{ display: 'flex', flexDirection: 'column', gap: '15px' }}>
                    <RadioCard 
                        id="manual" 
                        title="Manual Mode" 
                        description="Complete control over every step. Perfect for content creators who want to craft each post personally. Generate ideas and create drafts only when you choose." 
                        currentSelection={currentSettings.mode} 
                        onChange={handleModeChange} 
                    />
                    
                    <div className="aca-card" style={{
                        margin: 0,
                        border: '2px solid',
                        borderColor: currentSettings.mode === 'semi-automatic' ? '#0073aa' : '#ccd0d4',
                        background: currentSettings.mode === 'semi-automatic' ? '#f0f6fc' : '#ffffff',
                        boxShadow: currentSettings.mode === 'semi-automatic' ? '0 2px 4px rgba(0, 0, 0, 0.1)' : 'none'
                    }}>
                        <label htmlFor="semi-automatic" style={{ display: 'flex', alignItems: 'flex-start', cursor: 'pointer', gap: '12px' }}>
                            <input 
                                type="radio" 
                                id="semi-automatic" 
                                name="automation-mode" 
                                checked={currentSettings.mode === 'semi-automatic'} 
                                onChange={() => handleModeChange('semi-automatic')} 
                                style={{
                                    marginTop: '2px',
                                    width: '18px',
                                    height: '18px',
                                    accentColor: '#0073aa',
                                    flexShrink: 0
                                }}
                            />
                            <div>
                                <h4 className="aca-card-title" style={{ marginBottom: '8px' }}>
                                    Semi-Automatic Mode
                                </h4>
                                <p className="aca-page-description" style={{ margin: 0 }}>
                                    AI generates fresh content ideas automatically on your schedule. You review ideas and choose which ones to turn into drafts. Perfect balance of automation and control.
                                </p>
                            </div>
                        </label>
                        
                        {currentSettings.mode === 'semi-automatic' && (
                            <div className="aca-form-group" style={{ 
                                paddingLeft: '30px', 
                                paddingTop: '20px', 
                                marginTop: '20px', 
                                borderTop: '1px solid #e0e0e0',
                                marginBottom: 0
                            }}>
                                <label className="aca-label" htmlFor="semi-auto-frequency">Idea Generation Schedule</label>
                                <select 
                                    id="semi-auto-frequency"
                                    className="aca-input" 
                                    value={currentSettings.semiAutoIdeaFrequency || 'weekly'} 
                                    onChange={(e) => handleSettingChange('semiAutoIdeaFrequency', e.target.value)}
                                    style={{ marginTop: '5px' }}
                                >
                                    <option value="daily">Daily - New ideas every day (5 ideas each time)</option>
                                    <option value="weekly">Weekly - New ideas every week (5 ideas each time)</option>
                                    <option value="monthly">Monthly - New ideas every month (5 ideas each time)</option>
                                </select>
                                <div style={{ 
                                    marginTop: '10px', 
                                    padding: '10px', 
                                    background: '#f8f9fa', 
                                    borderRadius: '4px',
                                    fontSize: '12px',
                                    color: '#6b7280'
                                }}>
                                    <strong>How it works:</strong> AI automatically generates 5 fresh content ideas based on your chosen schedule. You'll find them in the Ideas section where you can review, edit, and convert your favorites into drafts.
                                </div>
                            </div>
                        )}
                    </div>
                    
                    <div className="aca-card" style={{
                        margin: 0,
                        border: '2px solid',
                        borderColor: currentSettings.mode === 'full-automatic' ? '#0073aa' : '#ccd0d4',
                        background: currentSettings.mode === 'full-automatic' ? '#f0f6fc' : '#ffffff',
                        boxShadow: currentSettings.mode === 'full-automatic' ? '0 2px 4px rgba(0, 0, 0, 0.1)' : 'none'
                    }}>
                        <label htmlFor="full-automatic-radio" style={{ display: 'flex', alignItems: 'flex-start', cursor: 'pointer', gap: '12px' }}>
                            <input 
                                type="radio" 
                                id="full-automatic-radio" 
                                name="automation-mode" 
                                checked={currentSettings.mode === 'full-automatic'} 
                                onChange={() => handleModeChange('full-automatic')} 
                                style={{
                                    marginTop: '2px',
                                    width: '18px',
                                    height: '18px',
                                    accentColor: '#0073aa',
                                    flexShrink: 0
                                }}
                            />
                            <div>
                                <h4 className="aca-card-title" style={{ marginBottom: '8px' }}>
                                    Full-Automatic Mode (Pro) - Complete Automation
                                </h4>
                                <p className="aca-page-description" style={{ margin: 0 }}>
                                    AI handles the entire content workflow: generates ideas, creates high-quality drafts, and can automatically publish them. Perfect for hands-off content creation with intelligent timing.
                                </p>
                            </div>
                        </label>
                        
                        {currentSettings.mode === 'full-automatic' && (
                            <div style={{ 
                                paddingLeft: '30px', 
                                paddingTop: '20px', 
                                marginTop: '20px', 
                                borderTop: '1px solid #e0e0e0',
                                marginBottom: 0
                            }}>
                                <div className="aca-form-group" style={{ marginBottom: '20px' }}>
                                    <label className="aca-label" htmlFor="daily-post-count">Content Production Target</label>
                                    <select 
                                        id="daily-post-count"
                                        className="aca-input" 
                                        value={currentSettings.fullAutoDailyPostCount || 1} 
                                        onChange={(e) => handleSettingChange('fullAutoDailyPostCount', parseInt(e.target.value))}
                                        style={{ marginTop: '5px' }}
                                    >
                                        <option value={1}>1 post per day - Light automation (generates 10 ideas daily)</option>
                                        <option value={2}>2 posts per day - Moderate automation (generates 3 ideas every 12 hours)</option>
                                        <option value={3}>3 posts per day - Active automation (generates 5 ideas every 12 hours)</option>
                                        <option value={5}>5 posts per day - High-volume automation (generates 4 ideas every 6 hours)</option>
                                    </select>
                                    <div style={{ 
                                        marginTop: '8px', 
                                        padding: '8px', 
                                        background: '#e1f5fe', 
                                        borderRadius: '4px',
                                        fontSize: '11px',
                                        color: '#0277bd'
                                    }}>
                                        <strong>Smart Timing:</strong> AI automatically calculates optimal idea generation frequency based on your target. Higher targets = more frequent idea generation to maintain quality variety.
                                    </div>
                                </div>

                                <div className="aca-form-group" style={{ marginBottom: '20px' }}>
                                    <label className="aca-label" htmlFor="publish-frequency">Draft Creation Schedule</label>
                                    <select 
                                        id="publish-frequency"
                                        className="aca-input" 
                                        value={currentSettings.fullAutoPublishFrequency || 'daily'} 
                                        onChange={(e) => handleSettingChange('fullAutoPublishFrequency', e.target.value)}
                                        style={{ marginTop: '5px' }}
                                    >
                                        <option value="hourly">Continuous - Creates drafts every 30 minutes (for hourly publishing)</option>
                                        <option value="daily">Regular - Creates drafts every 4 hours (recommended for daily publishing)</option>
                                        <option value="weekly">Batch - Creates drafts daily (for weekly publishing or manual review)</option>
                                    </select>
                                    <div style={{ 
                                        marginTop: '8px', 
                                        padding: '8px', 
                                        background: '#f3e5f5', 
                                        borderRadius: '4px',
                                        fontSize: '11px',
                                        color: '#7b1fa2'
                                    }}>
                                        <strong>Workflow Timing:</strong> AI creates drafts from available ideas based on this schedule. Drafts are automatically optimized for SEO and ready for review or publishing.
                                    </div>
                                </div>

                                <div className="aca-form-group" style={{ marginBottom: 0 }}>
                                    <label htmlFor="auto-publish" style={{ display: 'flex', alignItems: 'flex-start', cursor: 'pointer', gap: '12px' }}>
                                        <input 
                                            type="checkbox" 
                                            id="auto-publish" 
                                            checked={currentSettings.autoPublish} 
                                            onChange={(e) => handleSettingChange('autoPublish', e.target.checked)} 
                                            style={{
                                                marginTop: '2px',
                                                width: '16px',
                                                height: '16px',
                                                accentColor: '#0073aa'
                                            }}
                                        />
                                        <div>
                                            <span className="aca-label">Enable Auto-Publishing</span>
                                            <p className="aca-page-description" style={{ marginTop: '5px', margin: '5px 0 8px 0' }}>
                                                When enabled, AI will automatically publish the best drafts according to your schedule. Disable to review all drafts before publishing.
                                            </p>
                                            <div style={{ 
                                                padding: '8px', 
                                                background: '#fff3cd', 
                                                borderRadius: '4px',
                                                fontSize: '11px',
                                                color: '#856404',
                                                border: '1px solid #ffeaa7'
                                            }}>
                                                <strong>⚡ Complete Automation:</strong> With auto-publish enabled, your site runs completely hands-free. AI generates ideas → creates drafts → publishes content automatically with optimal timing.
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
            
            <div style={{ marginTop: '32px' }}>
                <SimpleAutomationManager 
                    onShowToast={onShowToast} 
                    refreshTrigger={automationRefreshTrigger}
                    currentSettings={currentSettings}
                />
            </div>
        </SettingsLayout>
    );
};