import React, { useState, useEffect } from 'react';
import type { AppSettings } from '../types';
import { Target, Search, Google } from './Icons';
import { SettingsLayout } from './SettingsLayout';
import { UpgradePrompt } from './UpgradePrompt';
import { __ } from '../services/i18n';


interface SettingsContentProps {
    settings: AppSettings;
    onSaveSettings: (settings: AppSettings) => void;
    onShowToast: (message: string, type: 'success' | 'error' | 'warning' | 'info') => void;
    isProActive?: boolean;
}

declare global {
    interface Window {
        acaData: {
            nonce: string;
            api_url: string;
            admin_url: string;
            plugin_url: string;
        };
    }
}

export const SettingsContent: React.FC<SettingsContentProps> = ({ 
    settings, 
    onSaveSettings, 
    onShowToast,
    isProActive 
}) => {
    const [currentSettings, setCurrentSettings] = useState<AppSettings>(settings);
    const [isDirty, setIsDirty] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [detectedSeoPlugins, setDetectedSeoPlugins] = useState<Array<{plugin: string, name: string, version: string, active: boolean}>>([]);
    const [seoPluginsLoading, setSeoPluginsLoading] = useState(true);
    const [isDetectingSeo, setIsDetectingSeo] = useState(false);
    const [backfillRunning, setBackfillRunning] = useState(false);
    const [backfillProgress, setBackfillProgress] = useState<{processed: number; lastId: number; done: boolean}>({ processed: 0, lastId: 0, done: false });

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
            onShowToast('Content settings saved successfully!', 'success');
        } catch (error) {
            onShowToast('Failed to save content settings', 'error');
        } finally {
            setIsSaving(false);
        }
    };

            // Load SEO plugins on component mount
    useEffect(() => {
        const loadSeoPlugins = async () => {
            if (!window.acaData) return;
            
            try {
                const response = await fetch(window.acaData.api_url + 'seo-plugins', {
                    headers: { 'X-WP-Nonce': window.acaData.nonce }
                });
                const data = await response.json();
                console.log('ACA: SEO plugins data:', data);
                if (data.success) {
                    setDetectedSeoPlugins(data.plugins || data.detected_plugins || []);
                } else {
                    // Try legacy format
                    setDetectedSeoPlugins(data.detected_plugins || []);
                }
            } catch (error) {
                console.error('Failed to load SEO plugins:', error);
            } finally {
                setSeoPluginsLoading(false);
            }
        };



        loadSeoPlugins();

    }, []);

    const handleAutoDetectSeo = async () => {
        if (!window.acaData) return;
        
        setIsDetectingSeo(true);
        try {
            const response = await fetch(window.acaData.api_url + 'seo-plugins', {
                method: 'POST',
                headers: { 'X-WP-Nonce': window.acaData.nonce }
            });
            const data = await response.json();
            console.log('ACA: SEO plugins refresh data:', data);
            if (data.success) {
                setDetectedSeoPlugins(data.plugins || data.detected_plugins || []);
                onShowToast('SEO plugins detection completed!', 'success');
            } else {
                // Try legacy format
                setDetectedSeoPlugins(data.detected_plugins || []);
                if (data.detected_plugins && data.detected_plugins.length > 0) {
                    onShowToast('SEO plugins detection completed!', 'success');
                } else {
                    onShowToast('Failed to detect SEO plugins', 'error');
                }
            }
        } catch (error) {
            console.error('SEO plugin detection error:', error);
            onShowToast('Failed to detect SEO plugins', 'error');
        } finally {
            setIsDetectingSeo(false);
        }
    };

    const runBackfillBatch = async (reset = false) => {
        if (!window.acaData) return;
        const params: any = { batch: 200 };
        if (reset) { params.reset = true; }
        const qs = new URLSearchParams(params).toString();
        const res = await fetch(`${window.acaData.api_url}index/backfill?${qs}`, {
            method: 'POST',
            headers: { 'X-WP-Nonce': window.acaData.nonce },
        });
        if (!res.ok) {
            const msg = await res.text();
            throw new Error(msg || 'Backfill request failed');
        }
        return res.json();
    };

    const handleRebuildIndex = async () => {
        if (backfillRunning) return;
        setBackfillRunning(true);
        setBackfillProgress({ processed: 0, lastId: 0, done: false });
        try {
            // Run up to 10 batches or until done
            let total = 0; let lastId = 0; let done = false;
            for (let i = 0; i < 10; i++) {
                const data = await runBackfillBatch(i === 0 /* reset first batch */);
                total += (data.processed || 0);
                lastId = data.last_id || lastId;
                done = !!data.done;
                setBackfillProgress({ processed: total, lastId, done });
                if (done) break;
            }
            onShowToast(done ? 'Index rebuild completed' : 'Index rebuild progressed (continue to complete)', 'success');
        } catch (e) {
            console.error(e);
            onShowToast('Index rebuild failed', 'error');
        } finally {
            setBackfillRunning(false);
        }
    };


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
            title="Content & SEO"
            description="Configure content analysis and SEO optimization settings"
            icon={<Target style={{ width: '24px', height: '24px', color: 'white' }} />}
            actions={saveButton}
        >
            {/* Content Analysis Settings */}
            <div className="aca-card" style={{ margin: '0 0 24px 0' }}>
                <div className="aca-card-header">
                    <h3 className="aca-card-title" style={{ display: 'flex', alignItems: 'center', gap: '12px', margin: '0 0 16px 0' }}>
                        <div style={{
                            width: '40px',
                            height: '40px',
                            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                            borderRadius: '10px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center'
                        }}>
                            <Search style={{ width: '20px', height: '20px', color: 'white' }} />
                        </div>
                        {__('Content Analysis')}
                    </h3>
                </div>
                <div className="aca-form-group">
                    <label className="aca-label" htmlFor="analyze-frequency">{__('Analysis Frequency')}</label>
                    <select 
                        id="analyze-frequency"
                        className="aca-input" 
                        value={currentSettings.analyzeContentFrequency || 'manual'} 
                        onChange={(e) => handleSettingChange('analyzeContentFrequency', e.target.value)}
                        style={{ marginTop: '5px' }}
                    >
                        <option value="manual">{__('Manual - Only when you click the analyze button')}</option>
                        <option value="daily">{__('Daily - Analyze content automatically every day')}</option>
                        <option value="weekly">{__('Weekly - Analyze content automatically every week')}</option>
                        <option value="monthly">{__('Monthly - Analyze content automatically every month')}</option>
                    </select>
                    <p className="aca-page-description" style={{ marginTop: '5px', margin: '5px 0 0 0' }}>{__('How often should the AI automatically analyze your site content to update the style guide? Manual mode gives you full control.')}</p>
                </div>

                <div className="aca-form-group" style={{ marginTop: '12px' }}>
                    <label className="aca-label" htmlFor="analysis-sampling">{__('Sampling Strategy')}</label>
                    <select
                        id="analysis-sampling"
                        className="aca-input"
                        value={currentSettings.analysisSampling || 'recent'}
                        onChange={(e) => handleSettingChange('analysisSampling', e.target.value as any)}
                        style={{ marginTop: '5px' }}
                    >
                        <option value="recent">{__('Recent posts (default)')}</option>
                        <option value="stratified">{__('Stratified (top categories balanced)')}</option>
                    </select>
                    <div className="aca-help-text">{__('Choose how posts are sampled for style analysis.')}</div>
                </div>

                <div className="aca-form-group" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '12px' }}>
                    <div>
                        <label className="aca-label" htmlFor="internal-link-topk">{__('Internal Link Top‑K')}</label>
                        <input
                            id="internal-link-topk"
                            type="number"
                            className="aca-input"
                            min={1}
                            max={10}
                            value={currentSettings.internalLinkTopK ?? 5}
                            onChange={(e) => handleSettingChange('internalLinkTopK', Math.max(1, Math.min(10, parseInt(e.target.value || '5', 10))))}
                            style={{ marginTop: '5px' }}
                        />
                        <div className="aca-help-text">{__('How many internal link candidates to pass into the AI (1‑10).')}</div>
                    </div>
                    <div>
                        <label className="aca-label" htmlFor="max-titles-prompt">{__('Max Titles For Prompt')}</label>
                        <input
                            id="max-titles-prompt"
                            type="number"
                            className="aca-input"
                            min={100}
                            max={5000}
                            value={currentSettings.maxTitlesForPrompt ?? 1000}
                            onChange={(e) => handleSettingChange('maxTitlesForPrompt', Math.max(100, Math.min(5000, parseInt(e.target.value || '1000', 10))))}
                            style={{ marginTop: '5px' }}
                        />
                        <div className="aca-help-text">{__('Upper bound for titles included to prevent prompt bloat on large sites.')}</div>
                    </div>
                </div>

                <div className="aca-form-group" style={{ marginTop: '12px' }}>
                    <label htmlFor="use-fulltext" style={{ display: 'flex', alignItems: 'flex-start', cursor: 'pointer', gap: '12px' }}>
                        <input
                            id="use-fulltext"
                            type="checkbox"
                            checked={currentSettings.useFulltextRetrieval !== false}
                            onChange={(e) => handleSettingChange('useFulltextRetrieval', e.target.checked)}
                            style={{ marginTop: '2px', width: '16px', height: '16px' }}
                        />
                        <span>
                            {__('Prefer FULLTEXT retrieval when available')}
                            <div className="aca-help-text">{__('If your DB supports FULLTEXT, use it for faster and more relevant internal link candidates.')}</div>
                        </span>
                    </label>
                </div>

                <div className="aca-form-group" style={{ marginTop: '16px' }}>
                    <label className="aca-label">{__('Rebuild Internal Link Index')}</label>
                    <button onClick={handleRebuildIndex} disabled={backfillRunning} className="aca-button secondary">
                        {backfillRunning ? __('Rebuilding...') : __('Rebuild Index')}
                    </button>
                    {backfillRunning || backfillProgress.processed > 0 ? (
                        <div style={{ marginTop: '8px', fontSize: '12px', color: '#374151' }}>{__('Processed:')} {backfillProgress.processed} · {__('Last ID:')} {backfillProgress.lastId} {backfillProgress.done ? '· ' + __('Done') : ''}</div>
                    ) : null}
                </div>
            </div>

            {/* SEO Integration */}
            <div className="aca-card" style={{ margin: '0 0 24px 0' }}>
                <div className="aca-card-header">
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                        <h3 className="aca-card-title" style={{ display: 'flex', alignItems: 'center', gap: '12px', margin: 0 }}>
                            <div style={{
                                width: '40px',
                                height: '40px',
                                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                                borderRadius: '10px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center'
                            }}>
                                <Target style={{ width: '20px', height: '20px', color: 'white' }} />
                            </div>
                            SEO Integration
                        </h3>
                        {detectedSeoPlugins.length > 0 && (
                            <div className="aca-alert success" style={{ 
                                display: 'flex', 
                                alignItems: 'center', 
                                fontSize: '12px', 
                                fontWeight: '600',
                                gap: '6px',
                                padding: '4px 8px',
                                margin: 0
                            }}>
                                <div style={{ 
                                    width: '8px', 
                                    height: '8px', 
                                    borderRadius: '50%', 
                                    background: '#22c55e' 
                                }}></div>
                                {detectedSeoPlugins.length} Plugin{detectedSeoPlugins.length > 1 ? 's' : ''} Detected
                            </div>
                        )}
                    </div>
                </div>
                
                {seoPluginsLoading ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px', padding: '20px 0' }}>
                        <span className="aca-spinner"></span>
                        <span>Detecting SEO plugins...</span>
                    </div>
                ) : detectedSeoPlugins.length > 0 ? (
                    <div>
                        <div style={{ 
                            padding: '12px 16px', 
                            backgroundColor: '#f0f9ff', 
                            borderRadius: '8px', 
                            marginBottom: '20px',
                            border: '1px solid #bae6fd'
                        }}>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                                <span style={{ color: '#0ea5e9', fontSize: '18px' }}>ℹ️</span>
                                <strong style={{ color: '#0c4a6e' }}>Automatic SEO Integration Active</strong>
                            </div>
                            <p style={{ margin: '0', fontSize: '14px', color: '#0c4a6e', lineHeight: '1.4' }}>
                                AI-generated content will automatically include SEO titles, meta descriptions, focus keywords, 
                                social media tags, and schema markup for all detected plugins.
                            </p>
                        </div>

                        <div style={{ marginBottom: '20px' }}>
                            <h4 style={{ margin: '0 0 12px 0', fontSize: '16px', fontWeight: '600', color: '#374151' }}>
                                Detected SEO Plugins ({detectedSeoPlugins.length})
                            </h4>
                            <div style={{ display: 'grid', gap: '12px' }}>
                                {detectedSeoPlugins.map((plugin, index) => {
                                    const getPluginIcon = (pluginType: string) => {
                                        switch (pluginType) {
                                            case 'rank_math': return '🏆';
                                            case 'yoast': return '🟢';
                                            case 'aioseo': return '🔵';
                                            default: return '🔧';
                                        }
                                    };

                                    const getPluginColor = (pluginType: string) => {
                                        switch (pluginType) {
                                            case 'rank_math':
                                                return { bg: '#fef3c7', border: '#f59e0b', text: '#92400e' };
                                            case 'yoast':
                                                return { bg: '#dcfce7', border: '#22c55e', text: '#166534' };
                                            case 'aioseo':
                                                return { bg: '#dbeafe', border: '#3b82f6', text: '#1e40af' };
                                            default:
                                                return { bg: '#f3f4f6', border: '#6b7280', text: '#374151' };
                                        }
                                    };

                                    const colors = getPluginColor(plugin.plugin);

                                    return (
                                        <div key={plugin.plugin} style={{ 
                                            padding: '16px', 
                                            backgroundColor: colors.bg, 
                                            borderRadius: '8px', 
                                            border: `1px solid ${colors.border}`,
                                            position: 'relative'
                                        }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                                                <span style={{ fontSize: '24px' }}>{getPluginIcon(plugin.plugin)}</span>
                                                <div style={{ flex: 1 }}>
                                                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                                                        <strong style={{ color: colors.text, fontSize: '15px' }}>{plugin.name}</strong>
                                                        <span style={{ 
                                                            color: '#6b7280', 
                                                            fontSize: '13px',
                                                            backgroundColor: 'rgba(255,255,255,0.7)',
                                                            padding: '2px 6px',
                                                            borderRadius: '4px'
                                                        }}>
                                                            v{plugin.version}
                                                        </span>
                                                    </div>
                                                    <p style={{ margin: '0', fontSize: '13px', color: colors.text, lineHeight: '1.4' }}>
                                                        Automatic integration includes: SEO titles, meta descriptions, focus keywords, 
                                                        social media tags, and schema markup.
                                                    </p>
                                                </div>
                                                <span style={{ 
                                                    backgroundColor: '#22c55e', 
                                                    color: 'white', 
                                                    padding: '4px 8px', 
                                                    borderRadius: '12px', 
                                                    fontSize: '12px',
                                                    fontWeight: '600',
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: '4px'
                                                }}>
                                                    <span style={{ fontSize: '10px' }}>●</span>
                                                    ACTIVE
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        <button 
                            onClick={handleAutoDetectSeo} 
                            disabled={isDetectingSeo} 
                            className="aca-button secondary"
                            style={{ width: '100%', justifyContent: 'center' }}
                        >
                            {isDetectingSeo && <span className="aca-spinner"></span>}
                            {isDetectingSeo ? "Re-detecting SEO plugins..." : "🔄 Refresh Detection"}
                        </button>
                    </div>
                ) : (
                    <div>
                        <div style={{ 
                            padding: '20px', 
                            backgroundColor: '#fef3c7', 
                            borderRadius: '8px', 
                            marginBottom: '20px',
                            border: '1px solid #f59e0b',
                            textAlign: 'center'
                        }}>
                            <div style={{ fontSize: '48px', marginBottom: '12px' }}>⚠️</div>
                            <h4 style={{ margin: '0 0 8px 0', color: '#92400e', fontSize: '16px' }}>
                                No SEO Plugins Detected
                            </h4>
                            <p style={{ margin: '0 0 16px 0', color: '#92400e', fontSize: '14px', lineHeight: '1.4' }}>
                                Install one of the supported SEO plugins to enable automatic SEO data integration 
                                for your AI-generated content.
                            </p>
                        </div>

                        <button 
                            onClick={handleAutoDetectSeo} 
                            disabled={isDetectingSeo} 
                            className="aca-button primary"
                            style={{ width: '100%', justifyContent: 'center' }}
                        >
                            {isDetectingSeo && <span className="aca-spinner"></span>}
                            {isDetectingSeo ? "Detecting plugins..." : "🔍 Check for SEO Plugins"}
                        </button>
                    </div>
                )}
            </div>

            {/* FAQ Settings */}
            <div className="aca-card" style={{ margin: '0 0 24px 0' }}>
                <div className="aca-card-header">
                    <h3 className="aca-card-title" style={{ display: 'flex', alignItems: 'center', gap: '12px', margin: '0 0 16px 0' }}>
                        <div style={{
                            width: '40px',
                            height: '40px',
                            background: 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                            borderRadius: '10px',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            color: 'white',
                            fontSize: '18px',
                            fontWeight: 700
                        }}>?</div>
                        FAQ (Structured Data)
                    </h3>
                </div>
                <div className="aca-card-body">
                    <div className="aca-form-group">
                        <label htmlFor="faq-enabled" style={{ display: 'flex', alignItems: 'flex-start', cursor: 'pointer', gap: '12px' }}>
                            <input
                                id="faq-enabled"
                                type="checkbox"
                                checked={!!currentSettings.faqEnabled}
                                onChange={(e) => handleSettingChange('faqEnabled', e.target.checked)}
                                style={{ marginTop: '2px', width: '16px', height: '16px', accentColor: '#059669' }}
                            />
                            <span>
                                Enable auto-generated FAQs (JSON-LD schema)
                                <div className="aca-help-text">Adds FAQ structured data for better SERP compatibility.</div>
                            </span>
                        </label>
                    </div>
                    {currentSettings.faqEnabled && (
                        <>
                            <div className="aca-form-group" style={{ marginTop: '10px' }}>
                                <label className="aca-label" htmlFor="faq-count">FAQ Count</label>
                                <select
                                    id="faq-count"
                                    className="aca-input"
                                    value={currentSettings.faqCount ?? 4}
                                    onChange={(e) => handleSettingChange('faqCount', parseInt(e.target.value))}
                                    style={{ marginTop: '5px', maxWidth: '160px' }}
                                >
                                    {[3,4,5,6,7,8].map(n => (
                                        <option key={n} value={n}>{n}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="aca-form-group" style={{ marginTop: '10px' }}>
                                <label htmlFor="faq-display-in-content" style={{ display: 'flex', alignItems: 'flex-start', cursor: 'pointer', gap: '12px' }}>
                                    <input
                                        id="faq-display-in-content"
                                        type="checkbox"
                                        checked={!!currentSettings.faqDisplayInContent}
                                        onChange={(e) => handleSettingChange('faqDisplayInContent', e.target.checked)}
                                        style={{ marginTop: '2px', width: '16px', height: '16px', accentColor: '#059669' }}
                                    />
                                    <span>
                                        Show FAQ at the end of the post (visible section)
                                        <div className="aca-help-text">Displays a styled Q&A section in content while still adding schema.</div>
                                    </span>
                                </label>
                            </div>
                        </>
                    )}
                </div>
            </div>
 
 
        </SettingsLayout>
    );
};