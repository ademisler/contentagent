
import React, { useState, useEffect, useRef } from 'react';
import type { ContentIdea, IdeaSource } from '../types';
import { Spinner, Lightbulb, Trash, Sparkles, PlusCircle, Edit, Archive } from './Icons';

interface IdeaBoardProps {
    ideas: ContentIdea[];
    onGenerate: () => void;
    onCreateDraft: (idea: ContentIdea) => void;
    onArchive: (id: number) => void;
    onDeleteIdea?: (id: number) => void;
    onRestoreIdea?: (id: number) => void;
    onUpdateTitle: (id: number, newTitle: string) => void;
    onGenerateSimilar: (idea: ContentIdea) => void;
    onAddIdea: (title: string) => void;
    isLoading: boolean;
    isLoadingDraft: { [key: string]: boolean };
    // Toplu işlemler
    onBulkArchive?: (ids: number[]) => void;
    onBulkPermanentDelete?: (ids: number[]) => void;
    // Global draft create lock
    isDraftCreationLocked?: boolean;
    queuedIdeaIds?: number[];
    onDequeueQueuedIdea?: (ideaId: number) => void;
    recentlyRestoredIds?: Set<number>;
}

const sourceStyles: { [key in IdeaSource]: { background: string; color: string; borderColor: string } } = {
    'ai': { background: '#e6f7e6', color: '#0a5d0a', borderColor: '#28a745' },

    'similar': { background: '#f3e5f5', color: '#4a148c', borderColor: '#9c27b0' },
    'manual': { background: '#f6f7f7', color: '#646970', borderColor: '#8c8f94' }
};

const sourceNames: { [key in IdeaSource]: string } = {
    'ai': 'AI Generated',

    'similar': 'Similar Idea',
    'manual': 'Manual Entry'
};

const IdeaCard: React.FC<{
    idea: ContentIdea;
    onCreateDraft: (idea: ContentIdea) => void;
    onArchive: (id: number) => void;
    onUpdateTitle: (id: number, newTitle: string) => void;
    onGenerateSimilar: (idea: ContentIdea) => void;
    isLoading: boolean;
    isGeneratingSimilar: boolean;
    selectable?: boolean;
    selected?: boolean;
    onToggleSelect?: (checked: boolean) => void;
    isDraftCreationLocked?: boolean;
    isQueued?: boolean;
    queuePosition?: number;
    onDequeueQueuedIdea?: (ideaId: number) => void;
}> = ({ idea, onCreateDraft, onArchive, onUpdateTitle, onGenerateSimilar, isLoading, isGeneratingSimilar, selectable = false, selected = false, onToggleSelect, isDraftCreationLocked, isQueued, queuePosition, onDequeueQueuedIdea }) => {
    const [isEditing, setIsEditing] = useState(false);
    const [title, setTitle] = useState(idea.title);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (isEditing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [isEditing]);
    
    const handleSave = () => {
        if (title.trim() && title.trim() !== idea.title) {
            onUpdateTitle(idea.id, title.trim());
        } else {
            setTitle(idea.title); // Reset if empty or unchanged
        }
        setIsEditing(false);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            handleSave();
        } else if (e.key === 'Escape') {
            setTitle(idea.title);
            setIsEditing(false);
        }
    };

    const sourceStyle = sourceStyles[idea.source];

    return (
        <div className={`aca-card ${isLoading ? 'loading' : ''}`} style={{ margin: 0, minHeight: '140px' }}>
            {/* Idea Title */}
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px', marginBottom: '15px' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexGrow: 1 }}>
                    {selectable && (
                        <input
                            type="checkbox"
                            checked={selected}
                            onChange={(e) => onToggleSelect && onToggleSelect(e.target.checked)}
                            aria-label={`Select idea ${idea.title}`}
                        />
                    )}
                    {isEditing ? (
                        <input
                            ref={inputRef}
                            type="text"
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            onBlur={handleSave}
                            onKeyDown={handleKeyDown}
                            className="aca-input"
                            style={{ fontSize: '16px', fontWeight: '500' }}
                        />
                    ) : (
                        <div 
                            onClick={() => setIsEditing(true)} 
                            className="aca-action-button"
                            style={{ 
                                padding: '8px 12px', 
                                margin: '-8px -12px',
                                border: '1px solid transparent',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                                lineHeight: '1.4',
                                fontSize: '16px',
                                fontWeight: '500'
                            }}
                            title="Click to edit title"
                        >
                            <Edit style={{ width: '14px', height: '14px', color: '#0073aa' }} />
                            {idea.title}
                        </div>
                    )}
                </div>
            </div>

            {/* Meta Info and Actions */}
            <div className="aca-list-item" style={{ padding: '15px 0 0 0', margin: 0 }}>
                {/* Source Tag */}
                <div style={{ 
                    fontSize: '11px', 
                    fontWeight: '600', 
                    padding: '6px 12px', 
                    borderRadius: '4px', 
                    border: '1px solid',
                    background: sourceStyle.background,
                    color: sourceStyle.color,
                    borderColor: sourceStyle.borderColor,
                    flexShrink: 0
                }}>
                    {sourceNames[idea.source]}
                </div>

                {/* Action Buttons */}
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexShrink: 0 }}>
                    {isQueued && (
                        <span style={{
                            fontSize: '11px',
                            fontWeight: 700,
                            background: '#eef2ff',
                            color: '#3730a3',
                            border: '1px solid #c7d2fe',
                            padding: '4px 6px',
                            borderRadius: '6px'
                        }}>
                            Queued{typeof queuePosition === 'number' ? ` (#${queuePosition})` : ''}
                        </span>
                    )}
                    <button
                        onClick={() => onGenerateSimilar(idea)}
                        disabled={isGeneratingSimilar || isLoading}
                        className="aca-button secondary"
                        style={{ fontSize: '12px', padding: '6px 12px' }}
                        title="Generate similar ideas"
                    >
                        {isGeneratingSimilar ? (
                            <span className="aca-spinner" style={{ width: '14px', height: '14px' }}></span>
                        ) : (
                            <Sparkles style={{ width: '14px', height: '14px' }} />
                        )}
                        Similar
                    </button>
                    {isQueued && onDequeueQueuedIdea && (
                        <button
                            onClick={() => onDequeueQueuedIdea(idea.id)}
                            className="aca-button secondary"
                            style={{ fontSize: '12px', padding: '6px 10px', minWidth: 'auto', background: '#fff1f2', borderColor: '#fecdd3', color: '#be123c' }}
                            title="Remove from queue"
                        >
                            Cancel
                        </button>
                    )}
                    
                    <button
                        onClick={() => onCreateDraft(idea)}
                        disabled={isLoading || isGeneratingSimilar || isQueued}
                        className={`aca-button ${!isLoading && !isQueued && isDraftCreationLocked ? 'queue-add' : ''}`}
                        style={{ 
                            fontSize: '12px',
                            padding: '6px 16px',
                            minWidth: '120px',
                            position: 'relative',
                            overflow: 'hidden'
                        }}
                        title={
                            isLoading
                                ? 'Creating draft...'
                                : isQueued
                                    ? 'Queued for draft creation'
                                    : isDraftCreationLocked
                                        ? 'Add this idea to the queue'
                                        : 'Create draft from this idea'
                        }
                    >
                        {isLoading ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <span className="aca-spinner" style={{ width: '14px', height: '14px' }}></span>
                                <span style={{ fontSize: '11px' }}>Creating...</span>
                            </div>
                        ) : isQueued ? (
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <Sparkles style={{ width: '14px', height: '14px' }} />
                                Queued
                            </div>
                        ) : (
                            <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                <Sparkles style={{ width: '14px', height: '14px' }} />
                                {isDraftCreationLocked ? 'Add to Queue' : 'Create Draft'}
                            </div>
                        )}
                        
                        {/* Progress indicator overlay only for this card while loading */}
                        {isLoading && (
                            <div style={{
                                position: 'absolute',
                                bottom: 0,
                                left: 0,
                                width: '100%',
                                height: '2px',
                                background: 'rgba(0, 163, 42, 0.3)',
                                overflow: 'hidden'
                            }}>
                                <div style={{
                                    width: '30%',
                                    height: '100%',
                                    background: '#00a32a',
                                    animation: 'aca-progress-slide 2s infinite linear'
                                }} />
                            </div>
                        )}
                    </button>
                    
                    <button 
                        onClick={() => onArchive(idea.id)} 
                        className="aca-button secondary"
                        style={{ fontSize: '12px', padding: '6px', minWidth: 'auto', color: '#646970' }}
                        title="Archive idea"
                    >
                        <Trash style={{ width: '14px', height: '14px' }} />
                    </button>
                </div>
            </div>
        </div>
    );
};

export const IdeaBoard: React.FC<IdeaBoardProps> = ({ 
    ideas, 
    onGenerate, 
    onCreateDraft, 
    onArchive, 
    onDeleteIdea,
    onRestoreIdea,
    onUpdateTitle, 
    onGenerateSimilar, 
    onAddIdea, 
    isLoading, 
    isLoadingDraft,
    onBulkArchive,
    onBulkPermanentDelete,
    isDraftCreationLocked,
    queuedIdeaIds,
    onDequeueQueuedIdea,
    recentlyRestoredIds
}) => {
    const [newIdeaTitle, setNewIdeaTitle] = useState('');
    const [selectedActiveIds, setSelectedActiveIds] = useState<number[]>([]);
    const [selectedArchivedIds, setSelectedArchivedIds] = useState<number[]>([]);

    const handleAddIdeaSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (newIdeaTitle.trim()) {
            onAddIdea(newIdeaTitle);
            setNewIdeaTitle('');
        }
    };

    const activeIdeas = ideas.filter(idea => idea.status === 'active');
    const archivedIdeas = ideas.filter(idea => idea.status === 'archived');

    const allActiveSelected = activeIdeas.length > 0 && selectedActiveIds.length === activeIdeas.length;
    const allArchivedSelected = archivedIdeas.length > 0 && selectedArchivedIds.length === archivedIdeas.length;

    const toggleSelectAllActive = (checked: boolean) => {
        setSelectedActiveIds(checked ? activeIdeas.map(i => i.id) : []);
    };

    const toggleSelectAllArchived = (checked: boolean) => {
        setSelectedArchivedIds(checked ? archivedIdeas.map(i => i.id) : []);
    };

    const handleBulkArchive = () => {
        if (selectedActiveIds.length === 0) return;
        if (onBulkArchive) {
            onBulkArchive(selectedActiveIds);
        } else {
            // Fallback: tek tek arşivle
            selectedActiveIds.forEach(id => onArchive(id));
        }
        setSelectedActiveIds([]);
    };

    const handleBulkPermanentDelete = () => {
        if (selectedArchivedIds.length === 0) return;
        if (!window.confirm(`Delete ${selectedArchivedIds.length} archived ideas permanently? This cannot be undone.`)) {
            return;
        }
        if (onBulkPermanentDelete) {
            onBulkPermanentDelete(selectedArchivedIds);
        } else if (onDeleteIdea) {
            selectedArchivedIds.forEach(id => onDeleteIdea(id));
        }
        setSelectedArchivedIds([]);
    };

    return (
        <div className="aca-fade-in">
            {/* Modern Ideas Header */}
            <div style={{
                background: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                borderRadius: '12px',
                padding: '30px',
                marginBottom: '30px',
                color: 'white',
                position: 'relative',
                overflow: 'hidden'
            }}>
                <div style={{ position: 'relative', zIndex: 2 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '20px' }}>
                        <div>
                            <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px' }}>
                                <div style={{
                                    width: '48px',
                                    height: '48px',
                                    background: 'rgba(255,255,255,0.2)',
                                    borderRadius: '12px',
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    backdropFilter: 'blur(10px)'
                                }}>
                                    <Lightbulb style={{ width: '24px', height: '24px' }} />
                                </div>
                                <div>
                                    <h1 style={{ 
                                        fontSize: '28px', 
                                        fontWeight: '700', 
                                        margin: 0,
                                        textShadow: '0 2px 4px rgba(0,0,0,0.1)',
                                        color: 'white'
                                    }}>
                                        Idea Board
                                    </h1>
                                    <div style={{ fontSize: '16px', opacity: 0.9, marginTop: '4px' }}>
                                        AI-powered content inspiration
                                    </div>
                                </div>
                            </div>
                            <p style={{ 
                                fontSize: '14px', 
                                opacity: 0.85,
                                margin: 0,
                                maxWidth: '600px',
                                lineHeight: '1.5'
                            }}>
                                Generate fresh content ideas and transform your favorites into drafts. Click any title to edit it.
                            </p>
                        </div>
                        <button
                            onClick={onGenerate}
                            disabled={isLoading}
                            style={{
                                background: 'rgba(255,255,255,0.2)',
                                border: '1px solid rgba(255,255,255,0.3)',
                                color: 'white',
                                padding: '12px 24px',
                                borderRadius: '8px',
                                fontSize: '14px',
                                fontWeight: '600',
                                cursor: isLoading ? 'not-allowed' : 'pointer',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '8px',
                                transition: 'all 0.2s ease',
                                backdropFilter: 'blur(10px)',
                                minWidth: '140px',
                                justifyContent: 'center'
                            }}
                            onMouseEnter={(e) => {
                                if (!isLoading) {
                                    e.currentTarget.style.background = 'rgba(255,255,255,0.3)';
                                    e.currentTarget.style.transform = 'translateY(-1px)';
                                }
                            }}
                            onMouseLeave={(e) => {
                                if (!isLoading) {
                                    e.currentTarget.style.background = 'rgba(255,255,255,0.2)';
                                    e.currentTarget.style.transform = 'translateY(0)';
                                }
                            }}
                        >
                            {isLoading && <span className="aca-spinner"></span>}
                            {!isLoading && <Sparkles style={{ width: '16px', height: '16px' }} />}
                            {isLoading ? 'Generating...' : 'Generate Ideas'}
                        </button>
                    </div>
                </div>
                {/* Decorative elements */}
                <div style={{
                    position: 'absolute',
                    top: '-30px',
                    right: '-30px',
                    width: '120px',
                    height: '120px',
                    background: 'rgba(255,255,255,0.1)',
                    borderRadius: '50%',
                    zIndex: 1
                }}></div>
                <div style={{
                    position: 'absolute',
                    bottom: '-20px',
                    left: '-20px',
                    width: '80px',
                    height: '80px',
                    background: 'rgba(255,255,255,0.05)',
                    borderRadius: '50%',
                    zIndex: 1
                }}></div>
            </div>

            {/* Add New Idea */}
            <div className="aca-card">
                <div className="aca-card-header">
                    <h2 className="aca-card-title">
                        <PlusCircle className="aca-nav-item-icon" />
                        Add Your Own Idea
                    </h2>
                </div>
                <form onSubmit={handleAddIdeaSubmit} style={{ display: 'flex', gap: '12px', alignItems: 'flex-end' }}>
                    <div className="aca-form-group" style={{ flexGrow: 1, marginBottom: 0 }}>
                        <label className="aca-label" htmlFor="new-idea-input">Idea Title</label>
                        <input
                            id="new-idea-input"
                            type="text"
                            value={newIdeaTitle}
                            onChange={(e) => setNewIdeaTitle(e.target.value)}
                            placeholder="Enter your content idea..."
                            className="aca-input"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={!newIdeaTitle.trim()}
                        className="aca-button"
                        style={{ 
                            background: '#00a32a',
                            borderColor: '#00a32a',
                            flexShrink: 0,
                            padding: '8px 16px'
                        }}
                    >
                        <PlusCircle style={{ width: '16px', height: '16px' }} />
                        Add Idea
                    </button>
                </form>
            </div>

            {/* Active Ideas */}
            {activeIdeas.length > 0 ? (
                <div className="aca-card">
                    <div className="aca-card-header" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px' }}>
                        <h2 className="aca-card-title" style={{ display: 'flex', alignItems: 'center', gap: '8px', margin: 0 }}>
                            <Lightbulb className="aca-nav-item-icon" />
                            Active Ideas ({activeIdeas.length})
                        </h2>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12px', color: '#475569' }}>
                                <input
                                    type="checkbox"
                                    checked={allActiveSelected}
                                    onChange={(e) => toggleSelectAllActive(e.target.checked)}
                                />
                                Select all
                            </label>
                            <button
                                className="aca-button secondary"
                                onClick={handleBulkArchive}
                                disabled={selectedActiveIds.length === 0}
                                title="Archive selected ideas"
                                style={{ padding: '6px 12px', fontSize: '12px' }}
                            >
                                <Archive style={{ width: '14px', height: '14px' }} />
                                Archive Selected
                            </button>
                            {queuedIdeaIds && queuedIdeaIds.length > 0 && (
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', background: '#f8fafc', border: '1px solid #e2e8f0', padding: '6px 10px', borderRadius: '8px' }}>
                                    <span style={{ fontSize: '12px', color: '#475569' }}>Queue:</span>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' }}>
                                        {queuedIdeaIds.map((id, idx) => (
                                            <span key={id} style={{ fontSize: '12px', fontWeight: 600, color: idx === 0 ? '#0f172a' : '#64748b' }}>#{idx + 1}</span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="aca-grid aca-grid-2">
                        {activeIdeas.map(idea => (
                            <IdeaCard
                                key={idea.id}
                                idea={idea}
                                onCreateDraft={onCreateDraft}
                                onArchive={onArchive}
                                onUpdateTitle={onUpdateTitle}
                                onGenerateSimilar={onGenerateSimilar}
                                isLoading={isLoadingDraft[`draft-${idea.id}`] || false}
                                isGeneratingSimilar={isLoadingDraft[`similar-${idea.id}`] || false}
                                selectable
                                selected={selectedActiveIds.includes(idea.id)}
                                onToggleSelect={(checked) => {
                                    setSelectedActiveIds(prev => {
                                        if (checked) {
                                            return prev.includes(idea.id) ? prev : [...prev, idea.id];
                                        }
                                        return prev.filter(id => id !== idea.id);
                                    });
                                }}
                                isDraftCreationLocked={isDraftCreationLocked}
                                isQueued={queuedIdeaIds?.includes(idea.id)}
                                queuePosition={queuedIdeaIds ? queuedIdeaIds.indexOf(idea.id) + 1 : undefined}
                                onDequeueQueuedIdea={onDequeueQueuedIdea}
                                // restore sonrası kısa süreli draft oluşturmayı engelle
                                // (performCreateDraft içinde de retry var)
                                {...(recentlyRestoredIds && recentlyRestoredIds.has(idea.id) ? { isDraftCreationLocked: true } : {})}
                            />
                        ))}
                    </div>
                </div>
            ) : (
                <div className="aca-card">
                    <div style={{ textAlign: 'center', padding: '60px 20px', color: '#646970' }}>
                        <Lightbulb style={{ margin: '0 auto 20px auto', width: '48px', height: '48px', fill: '#a7aaad' }} />
                        <h3 className="aca-card-title">No Active Ideas Yet</h3>
                        <p className="aca-page-description" style={{ maxWidth: '400px', marginLeft: 'auto', marginRight: 'auto' }}>
                            Get started by generating AI-powered content ideas or adding your own manually.
                        </p>
                        <div style={{ display: 'flex', gap: '10px', justifyContent: 'center', flexWrap: 'wrap' }}>
                            <button
                                onClick={onGenerate}
                                disabled={isLoading}
                                className="aca-button large"
                            >
                                {isLoading ? (
                                    <span className="aca-spinner"></span>
                                ) : (
                                    <Lightbulb style={{ width: '16px', height: '16px' }} />
                                )}
                                {isLoading ? 'Generating...' : 'Generate Ideas'}
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Archived Ideas */}
            {archivedIdeas.length > 0 && (
                <div className="aca-card">
                    <div className="aca-card-header" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px' }}>
                        <div>
                            <h2 className="aca-card-title" style={{ margin: 0 }}>
                                Archived Ideas ({archivedIdeas.length})
                            </h2>
                            <p className="aca-page-description" style={{ margin: 0, fontSize: '14px' }}>
                                Manage your archived ideas - restore them or delete permanently
                            </p>
                        </div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                            <label style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12px', color: '#475569' }}>
                                <input
                                    type="checkbox"
                                    checked={allArchivedSelected}
                                    onChange={(e) => toggleSelectAllArchived(e.target.checked)}
                                />
                                Select all
                            </label>
                            <button
                                className="aca-button secondary"
                                onClick={handleBulkPermanentDelete}
                                disabled={selectedArchivedIds.length === 0}
                                title="Delete selected ideas permanently"
                                style={{ padding: '6px 12px', fontSize: '12px', background: '#ffeaea', borderColor: '#dc3545', color: '#721c24' }}
                            >
                                <Trash style={{ width: '14px', height: '14px' }} />
                                Delete Selected
                            </button>
                        </div>
                    </div>
                    <div className="aca-list">
                        {archivedIdeas.map((idea) => (
                            <div key={idea.id} className="aca-list-item">
                                <div style={{ display: 'flex', alignItems: 'center', gap: '10px', flexGrow: 1 }}>
                                    <input
                                        type="checkbox"
                                        checked={selectedArchivedIds.includes(idea.id)}
                                        onChange={(e) => {
                                            const checked = e.target.checked;
                                            setSelectedArchivedIds(prev => {
                                                if (checked) {
                                                    return prev.includes(idea.id) ? prev : [...prev, idea.id];
                                                }
                                                return prev.filter(id => id !== idea.id);
                                            });
                                        }}
                                        aria-label={`Select archived idea ${idea.title}`}
                                    />
                                    <span style={{ flexGrow: 1, marginRight: '15px' }}>{idea.title}</span>
                                </div>
                                <div style={{ 
                                    fontSize: '11px', 
                                    fontWeight: '600', 
                                    padding: '4px 8px', 
                                    borderRadius: '4px', 
                                    background: sourceStyles[idea.source].background,
                                    color: sourceStyles[idea.source].color,
                                    border: `1px solid ${sourceStyles[idea.source].borderColor}`,
                                    flexShrink: 0,
                                    marginRight: '10px'
                                }}>
                                    {sourceNames[idea.source]}
                                </div>
                                
                                {/* Action buttons */}
                                <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                    {onRestoreIdea && (
                                        <button
                                            onClick={() => onRestoreIdea(idea.id)}
                                            className="aca-button secondary"
                                            style={{ 
                                                fontSize: '11px', 
                                                padding: '4px 8px', 
                                                minWidth: 'auto',
                                                background: '#e6f7e6',
                                                borderColor: '#28a745',
                                                color: '#0a5d0a'
                                            }}
                                            title="Restore to active ideas"
                                        >
                                            <Edit style={{ width: '12px', height: '12px', marginRight: '4px' }} />
                                            Restore
                                        </button>
                                    )}
                                    
                                    {onDeleteIdea && (
                                        <button
                                            onClick={() => {
                                                if (window.confirm(`Are you sure you want to permanently delete "${idea.title}"? This action cannot be undone.`)) {
                                                    onDeleteIdea(idea.id);
                                                }
                                            }}
                                            className="aca-button secondary"
                                            style={{ 
                                                fontSize: '11px', 
                                                padding: '4px 8px', 
                                                minWidth: 'auto',
                                                background: '#ffeaea',
                                                borderColor: '#dc3545',
                                                color: '#721c24'
                                            }}
                                            title="Delete permanently"
                                        >
                                            <Trash style={{ width: '12px', height: '12px' }} />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};
