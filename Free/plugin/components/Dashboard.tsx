
import React, { memo } from 'react';
import type { View, ActivityLog } from '../types';
import { Lightbulb, BookOpen, FileText, Send, CheckCircle, Sparkles, AlertTriangle } from './Icons';
import { ActivityLogList } from './ActivityLog';

interface DashboardProps {
    stats: {
        ideas: number;
        drafts: number;
        published: number;

    };
    lastAnalyzed?: string | undefined;
    activityLogs: ActivityLog[];
    onNavigate: (view: View) => void;
    onGenerateIdeas: () => void;
    onUpdateStyleGuide: () => void;
    isLoadingIdeas: boolean;
    isLoadingStyle: boolean;
}

const ActionButton: React.FC<{
    icon: React.ReactNode;
    title: string;
    description: string;
    onClick: () => void;
    disabled?: boolean;
    isLoading?: boolean;
    loadingTitle?: string;
    accent?: 'blue' | 'purple' | 'green';
}> = memo(({ icon, title, description, onClick, disabled, isLoading, loadingTitle, accent = 'blue' }) => {
    const iconBg = accent === 'purple'
        ? 'linear-gradient(135deg, #8b5cf6, #7c3aed)'
        : accent === 'green'
            ? 'linear-gradient(135deg, #10b981, #059669)'
            : 'linear-gradient(135deg, #3b82f6, #1d4ed8)';

    return (
        <button
            onClick={onClick}
            disabled={disabled || isLoading}
            className="aicoagac-action-button"
            aria-label={isLoading ? loadingTitle : title}
            aria-busy={isLoading ? true : undefined}
            style={{
                display: 'flex',
                flexDirection: 'row',
                alignItems: 'center',
                gap: '12px',
                width: '100%',
                textAlign: 'left',
                background: '#ffffff',
                border: '1px solid #e2e8f0',
                borderRadius: '10px',
                padding: '12px 14px',
                boxShadow: '0 1px 4px rgba(2, 6, 23, 0.04)',
                transition: 'transform .12s ease, box-shadow .12s ease, background .12s ease',
                cursor: disabled || isLoading ? 'not-allowed' : 'pointer',
                opacity: disabled ? 0.65 : 1
            }}
            onMouseEnter={(e) => {
                if (!disabled && !isLoading) {
                    e.currentTarget.style.boxShadow = '0 3px 10px rgba(2, 6, 23, 0.08)';
                    e.currentTarget.style.transform = 'translateY(-1px)';
                    e.currentTarget.style.background = '#f8fafc';
                }
            }}
            onMouseLeave={(e) => {
                if (!disabled && !isLoading) {
                    e.currentTarget.style.boxShadow = '0 1px 4px rgba(2, 6, 23, 0.04)';
                    e.currentTarget.style.transform = 'translateY(0)';
                    e.currentTarget.style.background = '#ffffff';
                }
            }}
        >
            <div style={{
                width: '36px',
                height: '36px',
                borderRadius: '8px',
                background: iconBg,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexShrink: 0,
                color: 'white'
            }}>
                <div style={{ color: 'white', width: '18px', height: '18px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    {icon}
                </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '2px', minWidth: 0 }}>
                    <span style={{ fontSize: '14px', fontWeight: 700, color: '#0f172a', whiteSpace: 'nowrap' }}>
                        {isLoading ? (loadingTitle || 'Loading...') : title}
                    </span>
                    <span style={{ fontSize: '12px', color: '#475569', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                        {description}
                    </span>
                </div>
                {isLoading && <span className="aicoagac-spinner" style={{ width: '14px', height: '14px', marginLeft: 'auto' }}></span>}
            </div>
        </button>
    );
});

const PipelineItem: React.FC<{
    icon: React.ReactNode;
    title: string;
    count: number;
    description: string;
    view: View;
    onNavigate: (view: View) => void;
}> = memo(({ icon, title, count, description, view, onNavigate }) => (
    <div className="aicoagac-stat-item">
        <div className="aicoagac-stat-info">
            <div className="aicoagac-stat-icon">{icon}</div>
            <div>
                <h4 className="aicoagac-stat-title">{title}</h4>
                <p className="aicoagac-stat-count">{count} {description}</p>
            </div>
        </div>
        <button 
            onClick={() => onNavigate(view)} 
            className="aicoagac-button"
            aria-label={`View ${title.toLowerCase()}`}
        >
            View
        </button>
    </div>
));

export const Dashboard: React.FC<DashboardProps> = ({ 
    stats, 
    lastAnalyzed, 
    activityLogs, 
    onNavigate, 
    onGenerateIdeas, 
    onUpdateStyleGuide, 
    isLoadingIdeas, 
    isLoadingStyle 
}) => {
    const isStyleGuideReady = !!lastAnalyzed;

    return (
        <div className="aicoagac-fade-in">
            {/* Modern Welcome Section */}
            <div className="aicoagac-welcome-section" style={{
                background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                borderRadius: '12px',
                padding: '30px',
                marginBottom: '30px',
                color: 'white',
                position: 'relative',
                overflow: 'hidden'
            }}>
                <div style={{ position: 'relative', zIndex: 2 }}>
                    <h1 style={{ 
                        fontSize: '28px', 
                        fontWeight: '700', 
                        marginBottom: '8px',
                        textShadow: '0 2px 4px rgba(0,0,0,0.1)',
                        color: 'white'
                    }}>
                        Welcome to AI Content Agent
                    </h1>
                    <p style={{ 
                        fontSize: '16px', 
                        opacity: 0.9,
                        marginBottom: '20px',
                        maxWidth: '600px'
                    }}>
                        Your intelligent content creation companion powered by Google Gemini AI
                    </p>
                    <div style={{ display: 'flex', gap: '15px', flexWrap: 'wrap' }}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <div style={{ width: '8px', height: '8px', backgroundColor: '#4ade80', borderRadius: '50%' }}></div>
                            <span style={{ fontSize: '14px', opacity: 0.9 }}>AI-Powered</span>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <div style={{ width: '8px', height: '8px', backgroundColor: '#60a5fa', borderRadius: '50%' }}></div>
                            <span style={{ fontSize: '14px', opacity: 0.9 }}>Automated Workflow</span>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                            <div style={{ width: '8px', height: '8px', backgroundColor: '#f59e0b', borderRadius: '50%' }}></div>
                            <span style={{ fontSize: '14px', opacity: 0.9 }}>SEO Optimized</span>
                        </div>
                    </div>
                </div>
                {/* Decorative elements */}
                <div style={{
                    position: 'absolute',
                    top: '-50px',
                    right: '-50px',
                    width: '150px',
                    height: '150px',
                    background: 'rgba(255,255,255,0.1)',
                    borderRadius: '50%',
                    zIndex: 1
                }}></div>
                <div style={{
                    position: 'absolute',
                    bottom: '-30px',
                    left: '-30px',
                    width: '100px',
                    height: '100px',
                    background: 'rgba(255,255,255,0.05)',
                    borderRadius: '50%',
                    zIndex: 1
                }}></div>
            </div>

            {!isStyleGuideReady && (
                <div className="aicoagac-alert info">
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: '15px' }}>
                        <div style={{ display: 'flex', alignItems: 'center' }}>
                            <Lightbulb style={{ width: '24px', height: '24px', marginRight: '12px', flexShrink: 0 }} />
                            <div>
                                <h4 style={{ margin: '0 0 5px 0', fontWeight: '600', color: '#0073aa' }}>Get Started with AI Content Agent (ACA)</h4>
                                <p style={{ margin: 0, fontSize: '13px' }}>Create your Style Guide first to enable all features and generate on-brand content.</p>
                            </div>
                        </div>
                        <button 
                            onClick={() => onNavigate('style-guide')} 
                            className="aicoagac-button large"
                            style={{ flexShrink: 0 }}
                        >
                            Create Style Guide
                        </button>
                    </div>
                </div>
            )}

            <div className="aicoagac-grid aicoagac-grid-2" style={{ marginBottom: '30px' }}>
                <div className="aicoagac-card" style={{ 
                    background: 'linear-gradient(145deg, #f8fafc 0%, #f1f5f9 100%)',
                    border: '1px solid #e2e8f0',
                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                }}>
                    <div className="aicoagac-card-header" style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '15px' }}>
                        <h2 className="aicoagac-card-title" style={{ 
                            display: 'flex', 
                            alignItems: 'center', 
                            gap: '8px',
                            color: '#1e293b'
                        }}>
                            <div style={{ 
                                width: '32px', 
                                height: '32px', 
                                background: 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
                                borderRadius: '8px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center'
                            }}>
                                <span style={{ color: 'white', fontSize: '16px' }}>⚡</span>
                            </div>
                            Quick Actions
                        </h2>
                        <p style={{ margin: '8px 0 0 0', color: '#64748b', fontSize: '14px' }}>
                            Get started with AI-powered content creation
                        </p>
                    </div>
                    <div className="aicoagac-grid aicoagac-grid-2" style={{ marginTop: '20px' }}>
                        <ActionButton
                            icon={<Lightbulb />}
                            title="Generate Ideas"
                            description="Create fresh content ideas based on your style guide"
                            onClick={onGenerateIdeas}
                            disabled={!isStyleGuideReady}
                            isLoading={isLoadingIdeas}
                            loadingTitle="Generating Ideas..."
                            accent="blue"
                        />
                        <ActionButton
                            icon={<BookOpen />}
                            title="Update Style Guide"
                            description="Analyze your site content to refresh your style guide"
                            onClick={onUpdateStyleGuide}
                            isLoading={isLoadingStyle}
                            loadingTitle="Analyzing Style..."
                            accent="purple"
                        />
                    </div>
                </div>

                <div className="aicoagac-card" style={{ 
                    background: 'linear-gradient(145deg, #fefefe 0%, #f8f9fa 100%)',
                    border: '1px solid #e2e8f0',
                    boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)'
                }}>
                    <div className="aicoagac-card-header" style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '15px' }}>
                        <h2 className="aicoagac-card-title" style={{ 
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
                                <span style={{ color: 'white', fontSize: '16px' }}>📊</span>
                            </div>
                            Content Pipeline
                        </h2>
                        <p style={{ margin: '8px 0 0 0', color: '#64748b', fontSize: '14px' }}>
                            Track your content creation progress
                        </p>
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '20px' }}>
                        <PipelineItem
                            icon={<Lightbulb />}
                            title="Ideas"
                            count={stats.ideas}
                            description="content ideas"
                            view="ideas"
                            onNavigate={onNavigate}
                        />
                        <PipelineItem
                            icon={<FileText />}
                            title="Drafts"
                            count={stats.drafts}
                            description="draft posts"
                            view="drafts"
                            onNavigate={onNavigate}
                        />
                        <PipelineItem
                            icon={<Send />}
                            title="Published"
                            count={stats.published}
                            description="published posts"
                            view="published"
                            onNavigate={onNavigate}
                        />
                    </div>
                </div>
            </div>

            {isStyleGuideReady && (
                <div className="aicoagac-card">
                    <div className="aicoagac-card-header">
                        <h2 className="aicoagac-card-title">
                            <CheckCircle style={{ width: '20px', height: '20px', marginRight: '8px', fill: '#00a32a' }} />
                            Style Guide Active
                        </h2>
                    </div>
                    <p style={{ margin: '0 0 15px 0', color: '#646970' }}>
                        Last analyzed: {new Date(lastAnalyzed).toLocaleDateString()} at {new Date(lastAnalyzed).toLocaleTimeString()}
                    </p>
                    <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                        <button 
                            onClick={() => onNavigate('style-guide')} 
                            className="aicoagac-button secondary"
                        >
                            View Style Guide
                        </button>
                        <button 
                            onClick={onUpdateStyleGuide} 
                            className="aicoagac-button secondary"
                            disabled={isLoadingStyle}
                        >
                            {isLoadingStyle ? 'Updating...' : 'Refresh Analysis'}
                        </button>
                    </div>
                </div>
            )}

            <div className="aicoagac-card">
                <div className="aicoagac-card-header">
                    <h2 className="aicoagac-card-title">Recent Activity</h2>
                </div>
                <ActivityLogList logs={activityLogs.slice(0, 10)} />
            </div>
        </div>
    );
};
