
import React, { useState, useCallback, useEffect } from 'react';
import { settingsApi, styleGuideApi, ideasApi, draftsApi, publishedApi, activityApi } from './services/wordpressApi';
import { setGeminiApiKey } from './services/geminiService';
import type { StyleGuide, ContentIdea, Draft, View, AppSettings, ActivityLog, ActivityLogType, IconName } from './types';
import { GeminiApiWarning } from './components/GeminiApiWarning';
import { Sidebar } from './components/Sidebar';
import { Dashboard } from './components/Dashboard';
import { StyleGuideManager } from './components/StyleGuideManager';
import { IdeaBoard } from './components/IdeaBoard';
import { DraftsList } from './components/DraftsList';

import { SettingsIntegrations } from './components/SettingsIntegrations';
import { SettingsContent } from './components/SettingsContent';
import { DraftModal } from './components/DraftModal';
import { Toast, ToastData } from './components/Toast';
import { PublishedList } from './components/PublishedList';
import { Menu } from './components/Icons';
import { ContentCalendar } from './components/ContentCalendar';



declare global {
  interface Window {
    aicoagacData: {
      nonce: string;
      api_url: string;
      admin_url: string;
      plugin_url: string;
    };
  }
}

const App: React.FC = () => {
    const [view, setView] = useState<View>('dashboard');
    const [styleGuide, setStyleGuide] = useState<StyleGuide | null>(null);
    const [ideas, setIdeas] = useState<ContentIdea[]>([]);
    const [posts, setPosts] = useState<Draft[]>([]);
    const [selectedDraft, setSelectedDraft] = useState<Draft | null>(null);
    const [activityLogs, setActivityLogs] = useState<ActivityLog[]>([]);
    const [settings, setSettings] = useState<AppSettings>({
        // Automation removed from Free version
        imageSourceProvider: 'pexels', // Changed default to simplest option
        aiImageStyle: 'photorealistic',
        googleCloudProjectId: '',
        googleCloudLocation: 'us-central1',
        pexelsApiKey: '',
        unsplashApiKey: '',
        pixabayApiKey: '',
        seoPlugin: 'none', // Auto-detected, kept for backward compatibility
        geminiApiKey: '',
    });
    const [isLoading, setIsLoading] = useState<{ [key: string]: boolean }>({});
    const [toasts, setToasts] = useState<ToastData[]>([]);
    const [isSidebarOpen, setIsSidebarOpen] = useState(false);
    const [publishingId, setPublishingId] = useState<number | null>(null);

    const [isCreatingDraftGlobal, setIsCreatingDraftGlobal] = useState<boolean>(false);
    const [draftCreationQueue, setDraftCreationQueue] = useState<number[]>([]);
    const [recentlyRestoredIds, setRecentlyRestoredIds] = useState<Set<number>>(new Set());

    // Published pagination state
    const [publishedPage, setPublishedPage] = useState<number>(1);
    const [publishedPerPage, setPublishedPerPage] = useState<number>(20);
    const [publishedTotal, setPublishedTotal] = useState<number>(0);
    const [publishedTotalPages, setPublishedTotalPages] = useState<number>(1);
    const [publishedItems, setPublishedItems] = useState<Draft[]>([]);
    
    const drafts = posts.filter(p => p.status === 'draft');
    const publishedPosts = publishedItems; // Use paginated list for published view
    
    // Check if Gemini API key is configured
    const isGeminiApiConfigured = !!(settings.geminiApiKey && settings.geminiApiKey.trim());



    const addToast = useCallback((toast: Omit<ToastData, 'id'>) => {
        const id = Date.now();
        setToasts(prev => [...prev, { ...toast, id }]);
    }, []);

    const showToast = useCallback((message: string, type: 'success' | 'error' | 'warning' | 'info') => {
        addToast({ message, type });
    }, [addToast]);
    
    const addLogEntry = useCallback((type: ActivityLogType, details: string, icon: IconName) => {
        const newLog: ActivityLog = {
            id: Date.now(),
            type,
            details,
            timestamp: new Date().toISOString(),
            icon
        };
        setActivityLogs(prev => [newLog, ...prev.slice(0, 49)]);
        
        // Save to WordPress with correct parameter names
        activityApi.create({
            type,
            message: details,
            icon
        }).catch(console.error);
    }, []);

    const removeToast = useCallback((id: number) => {
        setToasts(currentToasts => currentToasts.filter(toast => toast.id !== id));
    }, []);

    const handleAnalyzeStyle = useCallback(async (showToast = true) => {
        setIsLoading(prev => ({ ...prev, style: true }));
        try {
            const updatedStyleGuide = await styleGuideApi.analyze();
            setStyleGuide(updatedStyleGuide);
            if (showToast) {
                addToast({ message: 'Style guide updated successfully!', type: 'success' });
            }
            addLogEntry('style_analyzed', 'Style guide analyzed and updated', 'BookOpen');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to analyze style guide';
            if (showToast) {
                addToast({ message: errorMessage, type: 'error' });
            }
        } finally {
            setIsLoading(prev => ({ ...prev, style: false }));
        }
    }, [addToast, addLogEntry]);

    const handleSaveStyleGuide = useCallback(async (updatedGuide: Partial<StyleGuide>) => {
        try {
            const savedGuide = await styleGuideApi.update(updatedGuide);
            setStyleGuide(savedGuide);
            addToast({ message: 'Style guide saved successfully!', type: 'success' });
            addLogEntry('style_updated', 'Style guide manually updated', 'BookOpen');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to save style guide';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry]);

    const handleGenerateIdeas = useCallback(async (showToast = true, count = 5) => {
        if (!styleGuide) {
            addToast({ message: 'Please create a style guide first', type: 'warning' });
            return;
        }

        setIsLoading(prev => ({ ...prev, ideas: true }));
        try {
            const newIdeas = await ideasApi.generate(count);
            setIdeas(prev => [...newIdeas, ...prev]);
            if (showToast) {
                addToast({ message: `Generated ${newIdeas.length} new ideas!`, type: 'success' });
            }
            addLogEntry('ideas_generated', `Generated ${newIdeas.length} new content ideas`, 'Lightbulb');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to generate ideas';
            if (showToast) {
                addToast({ message: errorMessage, type: 'error' });
            }
        } finally {
            setIsLoading(prev => ({ ...prev, ideas: false }));
        }
    }, [styleGuide, addToast, addLogEntry]);

    const handleGenerateSimilarIdeas = useCallback(async (baseIdea: ContentIdea) => {
        if (!styleGuide) {
            addToast({ message: 'Please create a style guide first', type: 'warning' });
            return;
        }

        setIsLoading(prev => ({ ...prev, [`similar-${baseIdea.id}`]: true }));
        try {
            const similarIdeas = await ideasApi.generateSimilar(baseIdea.id);
            setIdeas(prev => [...similarIdeas, ...prev]);
            addToast({ message: `Generated ${similarIdeas.length} similar ideas!`, type: 'success' });
            addLogEntry('similar_ideas_generated', `Generated ${similarIdeas.length} ideas similar to "${baseIdea.title}"`, 'Lightbulb');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to generate similar ideas';
            addToast({ message: errorMessage, type: 'error' });
        } finally {
            setIsLoading(prev => ({ ...prev, [`similar-${baseIdea.id}`]: false }));
        }
    }, [styleGuide, addToast, addLogEntry]);

    const performCreateDraft = useCallback(async (ideaId: number) => {
        const idea = ideas.find(i => i.id === ideaId);
        if (!idea) {
            addToast({ message: 'Idea not found', type: 'error' });
            return;
        }
        console.log('Creating draft for idea:', idea);
        setIsLoading(prev => ({ ...prev, [`draft-${ideaId}`]: true }));
        setIsCreatingDraftGlobal(true);
        addToast({ message: `🤖 AI is generating draft for "${idea.title}"... This may take a moment.`, type: 'info' });
        try {
            let attempt = 0;
            let createdDraft = null as any;
            while (attempt < 2 && !createdDraft) {
                try {
                    const draft = await draftsApi.createFromIdea(ideaId);
                    createdDraft = draft;
                } catch (err: any) {
                    const msg = (err?.message || '').toLowerCase();
                    if (attempt === 0 && (msg.includes('404') || msg.includes('idea not found'))) {
                        // Retry after short sync
                        await new Promise(res => setTimeout(res, 300));
                        try {
                            const freshIdeas = await ideasApi.get();
                            setIdeas(freshIdeas);
                        } catch {}
                        attempt++;
                        continue;
                    }
                    throw err;
                }
            }
            const draft = createdDraft;
            if (!draft) {
                throw new Error('Failed to create draft');
            }
            setPosts(prev => [draft, ...prev]);
            const updatedIdea = { ...idea, status: 'archived' as const };
            await ideasApi.update(ideaId, updatedIdea);
            setIdeas(prev => prev.map(i => i.id === ideaId ? updatedIdea : i));
            addToast({ message: `Draft "${draft.title}" created successfully! View it in the Drafts page.`, type: 'success' });
            addLogEntry('draft_created', `Created draft: "${draft.title}" with full WordPress integration`, 'FileText');
        } catch (error) {
            console.error('Error creating draft:', error);
            let errorMessage = 'Failed to create draft. Please try again.';
            if (error instanceof Error) {
                const message = error.message.toLowerCase();
                if (message.includes('404') || message.includes('idea not found')) {
                    errorMessage = 'This idea could not be found. Please refresh Ideas and try again.';
                } else if (message.includes('503') || message.includes('overloaded') || message.includes('unavailable')) {
                    errorMessage = '🤖 AI service is temporarily overloaded. Please wait a moment and try again.';
                } else if (message.includes('timeout')) {
                    errorMessage = '⏱️ Request timed out. The AI service might be busy. Please try again.';
                } else if (message.includes('api key')) {
                    errorMessage = '🔑 AI API key is not configured. Please check your settings.';
                } else if (message.includes('style guide')) {
                    errorMessage = '📋 Style guide is required. Please create one first.';
                } else if (message.includes('after') && message.includes('attempts')) {
                    errorMessage = '🔄 AI service is currently unavailable after multiple attempts. Please try again in a few minutes.';
                } else if (message.includes('ai content generation failed')) {
                    errorMessage = '🤖 AI content generation failed. The service might be temporarily unavailable. Please try again.';
                }
            }
            addToast({ message: errorMessage, type: 'error' });
        } finally {
            setIsLoading(prev => ({ ...prev, [`draft-${ideaId}`]: false }));
            // dequeue
            setDraftCreationQueue(prev => prev.filter(id => id !== ideaId));
            setIsCreatingDraftGlobal(false);
        }
    }, [ideas, addToast, addLogEntry]);

    const handleCreateDraft = useCallback((ideaId: number) => {
        if (!styleGuide) {
            addToast({ message: 'Please create a style guide first', type: 'warning' });
            return;
        }
        // Avoid duplicates in queue
        setDraftCreationQueue(prev => {
            if (prev.includes(ideaId)) {
                addToast({ message: 'This idea is already queued for draft creation.', type: 'info' });
                return prev;
            }
            const newQueue = [...prev, ideaId];
            const position = newQueue.length;
            addToast({ message: position === 1 && !isCreatingDraftGlobal ? 'Starting draft creation...' : `Queued for draft creation (position ${position})`, type: 'info' });
            return newQueue;
        });
    }, [styleGuide, addToast, isCreatingDraftGlobal]);

    const handleDequeueDraft = useCallback((ideaId: number) => {
        setDraftCreationQueue(prev => {
            if (prev.length > 0 && prev[0] === ideaId && isCreatingDraftGlobal) {
                addToast({ message: 'Cannot remove: draft creation in progress.', type: 'warning' });
                return prev;
            }
            if (!prev.includes(ideaId)) {
                return prev;
            }
            const next = prev.filter(id => id !== ideaId);
            addToast({ message: 'Removed from queue.', type: 'success' });
            return next;
        });
    }, [isCreatingDraftGlobal, addToast]);

    // Process queue when idle
    React.useEffect(() => {
        if (!isCreatingDraftGlobal && draftCreationQueue.length > 0) {
            const nextId = draftCreationQueue[0];
            performCreateDraft(nextId);
        }
    }, [draftCreationQueue, isCreatingDraftGlobal, performCreateDraft]);

    const handleUpdateDraft = useCallback(async (draftId: number, updates: Partial<Draft>) => {
        try {
            const updatedDraft = await draftsApi.update(draftId, updates);
            setPosts(prev => prev.map(p => p.id === draftId ? updatedDraft : p));
            addToast({ message: 'Draft updated successfully!', type: 'success' });
            addLogEntry('draft_updated', `Updated draft: "${updatedDraft.title}"`, 'FileText');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to update draft';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry]);

    const handlePublishPost = useCallback(async (draftId: number) => {
        setPublishingId(draftId);
        try {
            const publishedPost = await publishedApi.publish(draftId);
            setPosts(prev => prev.map(p => p.id === draftId ? publishedPost : p));
            addToast({ message: 'Post published successfully!', type: 'success' });
            addLogEntry('post_published', `Published post: "${publishedPost.title}"`, 'Send');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to publish post';
            addToast({ message: errorMessage, type: 'error' });
        } finally {
            setPublishingId(null);
        }
    }, [addToast, addLogEntry]);

    const handleUpdatePostDate = useCallback(async (postId: number, newDate: string, shouldConvertToDraft: boolean = false) => {
        try {
            const updatedPost = await publishedApi.updateDate(postId, newDate, shouldConvertToDraft);
            
            // Update the posts state
            setPosts(prev => prev.map(p => p.id === postId ? updatedPost : p));
            
            const action = shouldConvertToDraft ? 'converted to scheduled draft' : 'publish date updated';
            addToast({ message: `Post ${action} successfully!`, type: 'success' });
            addLogEntry('draft_updated', `Post "${updatedPost.title}" ${action}`, 'Calendar');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to update post date';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry]);

    const handleScheduleDraft = useCallback(async (draftId: number, scheduledDate: string) => {
        try {
            const updatedDraft = await draftsApi.schedule(draftId, scheduledDate);
            
            // Update the posts array with the properly scheduled draft
            setPosts(prev => prev.map(p => p.id === draftId ? updatedDraft : p));
            
            const formattedDate = new Date(scheduledDate).toLocaleDateString();
            const draftTitle = updatedDraft.title || `Draft ${draftId}`;
            
            addToast({ message: `Draft "${draftTitle}" scheduled for ${formattedDate}!`, type: 'success' });
            addLogEntry('draft_scheduled', `Scheduled draft: "${draftTitle}" for ${formattedDate}`, 'Calendar');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to schedule draft';
            addToast({ message: errorMessage, type: 'error' });
            console.error('Error scheduling draft:', error);
        }
    }, [addToast, addLogEntry]);

    const handleArchiveIdea = useCallback(async (ideaId: number) => {
        try {
            const idea = ideas.find(i => i.id === ideaId);
            if (!idea) return;

            // Use delete API which now archives the idea
            await ideasApi.delete(ideaId);
            const updatedIdea = { ...idea, status: 'archived' as const };
            setIdeas(prev => prev.map(i => i.id === ideaId ? updatedIdea : i));
            addToast({ message: 'Idea archived successfully!', type: 'success' });
            addLogEntry('idea_archived', `Archived idea: "${idea.title}"`, 'Archive');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to archive idea';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [ideas, addToast, addLogEntry]);

    const handleDeleteIdea = useCallback(async (ideaId: number) => {
        try {
            const idea = ideas.find(i => i.id === ideaId);
            if (!idea) return;

            // Use permanent delete API
            await ideasApi.permanentDelete(ideaId);
            setIdeas(prev => prev.filter(i => i.id !== ideaId));
            addToast({ message: 'Idea deleted permanently!', type: 'success' });
            addLogEntry('idea_updated', `Permanently deleted idea: "${idea.title}"`, 'Trash');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to delete idea';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [ideas, addToast, addLogEntry]);

    // Bulk archive active ideas
    const handleBulkArchive = useCallback(async (ids: number[]) => {
        if (!ids || ids.length === 0) return;
        try {
            // Aşırı eşzamanlı istekleri önlemek için sıralı ve küçük batch halinde işle
            const batchSize = 10;
            for (let i = 0; i < ids.length; i += batchSize) {
                const batch = ids.slice(i, i + batchSize);
                for (const id of batch) {
                    await ideasApi.delete(id);
                }
                // İsteğe bağlı küçük bir bekleme (host limitleri için)
                await new Promise(res => setTimeout(res, 100));
            }
            setIdeas(prev => prev.map(i => ids.includes(i.id) ? { ...i, status: 'archived' as const } : i));
            addToast({ message: `Archived ${ids.length} ideas`, type: 'success' });
            addLogEntry('idea_archived', `Bulk archived ${ids.length} ideas`, 'Archive');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to archive selected ideas';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry]);

    // Bulk permanent delete archived ideas
    const handleBulkPermanentDelete = useCallback(async (ids: number[]) => {
        if (!ids || ids.length === 0) return;
        try {
            addToast({ message: `Deleting ${ids.length} ideas in background...`, type: 'info' });
            // Aşırı eşzamanlı bağlantıları önlemek için sıralı işleme
            const batchSize = 10;
            for (let i = 0; i < ids.length; i += batchSize) {
                const batch = ids.slice(i, i + batchSize);
                for (const id of batch) {
                    await ideasApi.permanentDelete(id);
                }
                await new Promise(res => setTimeout(res, 100));
            }
            setIdeas(prev => prev.filter(i => !ids.includes(i.id)));
            addToast({ message: `Deleted ${ids.length} ideas permanently`, type: 'success' });
            addLogEntry('idea_updated', `Bulk permanently deleted ${ids.length} ideas`, 'Trash');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to delete selected ideas';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry]);

    const handleRestoreIdea = useCallback(async (ideaId: number) => {
        try {
            const idea = ideas.find(i => i.id === ideaId);
            if (!idea) return;

            // Use restore API
            await ideasApi.restore(ideaId);
            const updatedIdea = { ...idea, status: 'active' as const };
            setIdeas(prev => prev.map(i => i.id === ideaId ? updatedIdea : i));
            addToast({ message: 'Idea restored successfully!', type: 'success' });
            addLogEntry('idea_restored', `Restored idea: "${idea.title}"`, 'Edit');
            // kısa bir senkron kilidi (1s)
            setRecentlyRestoredIds(prev => new Set(prev).add(ideaId));
            setTimeout(() => {
                setRecentlyRestoredIds(prev => {
                    const next = new Set(prev);
                    next.delete(ideaId);
                    return next;
                });
            }, 1000);
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to restore idea';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [ideas, addToast, addLogEntry]);

    const handleGenerateIdeasAndNavigate = useCallback(async () => {
        await handleGenerateIdeas(false, 5);
        // Navigate to ideas view after successful generation
        setView('ideas');
    }, [handleGenerateIdeas]);

    const handleUpdateIdeaTitle = useCallback(async (ideaId: number, newTitle: string) => {
        try {
            const idea = ideas.find(i => i.id === ideaId);
            if (!idea) return;

            const updatedIdea = { ...idea, title: newTitle };
            await ideasApi.update(ideaId, updatedIdea);
            setIdeas(prev => prev.map(i => i.id === ideaId ? updatedIdea : i));
            addToast({ message: 'Idea updated successfully!', type: 'success' });
            addLogEntry('idea_updated', `Updated idea title to: "${newTitle}"`, 'Edit');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to update idea';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [ideas, addToast, addLogEntry]);

    const handleSaveSettings = useCallback(async (newSettings: AppSettings) => {
        try {
            await settingsApi.update(newSettings);
            setSettings(newSettings);
            addToast({ message: 'Settings saved successfully!', type: 'success' });
            addLogEntry('settings_updated', 'Plugin settings updated', 'Settings');
            
            // Automation removed from Free version
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to save settings';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry, settings]);



    const handleAddIdea = useCallback(async (title: string, description?: string) => {
        try {
            const newIdea: Omit<ContentIdea, 'id'> = {
                title: title.trim(),
                description: description?.trim() || '',
                status: 'active',
                createdAt: new Date().toISOString(),
                tags: [],
                source: 'manual'
            };
            
            const createdIdea = await ideasApi.create(newIdea);
            setIdeas(prev => [createdIdea, ...prev]);
            addToast({ message: 'Idea added successfully!', type: 'success' });
            addLogEntry('idea_added', `Manually added idea: "${title.trim()}"`, 'PlusCircle');
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Failed to add idea';
            addToast({ message: errorMessage, type: 'error' });
        }
    }, [addToast, addLogEntry]);


    const renderView = () => {
        switch (view) {
            case 'style-guide':
                return <StyleGuideManager styleGuide={styleGuide} onAnalyze={() => handleAnalyzeStyle(false)} onSaveStyleGuide={handleSaveStyleGuide} isLoading={isLoading['style']} />;
            case 'ideas':
                return <IdeaBoard 
                    ideas={ideas} 
                    onGenerate={() => handleGenerateIdeas(false, 5)} 
                    onCreateDraft={(idea) => handleCreateDraft(idea.id)} 
                    onArchive={handleArchiveIdea}
                    onDeleteIdea={handleDeleteIdea}
                    onRestoreIdea={handleRestoreIdea}
                    isLoading={isLoading['ideas']} 
                    isLoadingDraft={isLoading} 
                    onUpdateTitle={handleUpdateIdeaTitle} 
                    onGenerateSimilar={handleGenerateSimilarIdeas} 
                    onAddIdea={handleAddIdea}
                    onBulkArchive={handleBulkArchive}
                    onBulkPermanentDelete={handleBulkPermanentDelete}
                    isDraftCreationLocked={isCreatingDraftGlobal}
                    queuedIdeaIds={draftCreationQueue}
                    onDequeueQueuedIdea={handleDequeueDraft}
                    recentlyRestoredIds={recentlyRestoredIds}
                />;
            case 'drafts':
                return <DraftsList 
                    drafts={drafts} 
                    onSelectDraft={setSelectedDraft} 
                    onPublish={handlePublishPost} 
                    publishingId={publishingId}
                    onNavigateToIdeas={() => setView('ideas')}
                />;
            case 'published':
                return <PublishedList 
                    posts={publishedPosts} 
                    onSelectPost={setSelectedDraft}
                    onNavigateToDrafts={() => setView('drafts')}
                    total={publishedTotal}
                    page={publishedPage}
                    totalPages={publishedTotalPages}
                    onPageChange={(p: number) => setPublishedPage(p)}
                />;
            case 'settings':
            case 'settings_integrations':
                return <SettingsIntegrations 
                    settings={settings} 
                    onSaveSettings={handleSaveSettings} 
                    onShowToast={showToast} 
                    isProActive={settings.is_pro}
                />;
                         case 'settings_content':
                 return <SettingsContent 
                     settings={settings} 
                     onSaveSettings={handleSaveSettings} 
                     onShowToast={showToast} 
                     isProActive={settings.is_pro}
                 />;

case 'calendar':
               return <ContentCalendar 
                   drafts={drafts} 
                   publishedPosts={publishedPosts} 
                   onScheduleDraft={handleScheduleDraft} 
                   onSelectPost={setSelectedDraft}
                   onPublishDraft={handlePublishPost}
                   onUpdatePostDate={handleUpdatePostDate}
               />;
            case 'dashboard':
            default:
                return <Dashboard
                    stats={{ 
                        ideas: ideas.filter(idea => idea.status === 'active').length, 
                        drafts: drafts.length, 
                        published: publishedTotal,

                    }}
                    lastAnalyzed={styleGuide?.lastAnalyzed}
                    activityLogs={activityLogs}
                    onNavigate={setView}
                    onGenerateIdeas={handleGenerateIdeasAndNavigate}
                    onUpdateStyleGuide={() => handleAnalyzeStyle(false)}
                    isLoadingIdeas={isLoading['ideas'] || false}
                    isLoadingStyle={isLoading['style'] || false}
                />;
        }
    };

    useEffect(() => {
        // Check if WordPress localized data is available
        if (!window.aicoagacData) {
            console.error('ACA Error: WordPress localized data not available');
            showToast('Plugin not properly loaded. Please refresh the page.', 'error');
            return;
        }
        
        setGeminiApiKey(settings.geminiApiKey);
    }, [settings.geminiApiKey, showToast]);

    // Load initial data from WordPress
    useEffect(() => {
        const loadInitialData = async () => {
            const results = await Promise.allSettled([
                settingsApi.get(),
                styleGuideApi.get(),
                ideasApi.get(),
                draftsApi.get(),
                publishedApi.get(),
                activityApi.get()
            ]);
            
            // Extract successful results and log failed ones
            const [settingsResult, styleGuideResult, ideasResult, draftsResult, publishedResult, activityResult] = results;
            
            let settingsData = null;
            let styleGuideData = null;
            let ideasData = null;
            let draftsData = null;
            let publishedData = null;
            let activityData = null;
            
            const failedLoads: string[] = [];
            
            if (settingsResult.status === 'fulfilled') {
                settingsData = settingsResult.value;
            } else {
                console.error('Failed to load settings:', settingsResult.reason);
                failedLoads.push('Settings');
            }
            
            if (styleGuideResult.status === 'fulfilled') {
                styleGuideData = styleGuideResult.value;
            } else {
                console.error('Failed to load style guide:', styleGuideResult.reason);
                failedLoads.push('Style Guide');
            }
            
            if (ideasResult.status === 'fulfilled') {
                ideasData = ideasResult.value;
            } else {
                console.error('Failed to load ideas:', ideasResult.reason);
                failedLoads.push('Ideas');
            }
            
            if (draftsResult.status === 'fulfilled') {
                const draftsResponse = draftsResult.value;
                draftsData = draftsResponse?.posts || [];
                console.log('ACA: Drafts loaded successfully:', draftsData.length, 'posts from API response:', draftsResponse);
            } else {
                console.error('Failed to load drafts:', draftsResult.reason);
                failedLoads.push('Drafts');
            }
            
            if (publishedResult.status === 'fulfilled') {
                const publishedResponse = publishedResult.value;
                publishedData = publishedResponse?.posts || [];
                // Initialize pagination state from response if available
                if (publishedResponse?.pagination) {
                    setPublishedTotal(publishedResponse.pagination.total || 0);
                    setPublishedTotalPages(publishedResponse.pagination.total_pages || 1);
                    setPublishedPage(publishedResponse.pagination.page || 1);
                    setPublishedPerPage(publishedResponse.pagination.per_page || 20);
                } else {
                    setPublishedTotal(publishedData.length || 0);
                    setPublishedTotalPages(1);
                    setPublishedPage(1);
                    setPublishedPerPage(20);
                }
                setPublishedItems(publishedData || []);
                console.log('ACA: Published posts loaded successfully:', publishedData.length, 'posts from API response:', publishedResponse);
            } else {
                console.error('Failed to load published posts:', publishedResult.reason);
                failedLoads.push('Published Posts');
            }
            
            if (activityResult.status === 'fulfilled') {
                activityData = activityResult.value;
            } else {
                console.error('Failed to load activity logs:', activityResult.reason);
                failedLoads.push('Activity Logs');
            }
            

            
            // Set data with fallbacks for failed loads
            setSettings(settingsData || {
                // Automation removed from Free version
                imageSourceProvider: 'pexels',
                aiImageStyle: 'photorealistic',
                pexelsApiKey: '',
                unsplashApiKey: '',
                pixabayApiKey: '',
                seoPlugin: 'none', // Auto-detected, kept for backward compatibility
                geminiApiKey: '',
            });
            
            if (styleGuideData) {
                setStyleGuide(styleGuideData);
            }
            
            setIdeas(ideasData || []);
            console.log('ACA: Setting posts - drafts:', draftsData?.length || 0, 'published (first page):', publishedData?.length || 0);
            // Keep global posts for non-published views only with drafts
            setPosts([...(draftsData || [])]);
            setActivityLogs(activityData || []);
            
            // Show warning if some data failed to load
            if (failedLoads.length > 0) {
                const failedItems = failedLoads.join(', ');
                addToast({ 
                    message: `Some data could not be loaded: ${failedItems}. Plugin will work with available data.`, 
                    type: 'warning' 
                });
            }
        };
        
        loadInitialData();
    }, []);

    // Fetch paginated published posts when navigating to published view or page changes
    useEffect(() => {
        const fetchPublished = async () => {
            try {
                const response = await publishedApi.get(publishedPage, publishedPerPage);
                const list = response?.posts || [];
                setPublishedItems(list);
                if (response?.pagination) {
                    setPublishedTotal(response.pagination.total || 0);
                    setPublishedTotalPages(response.pagination.total_pages || 1);
                }
            } catch (err) {
                console.error('Failed to fetch published posts page', publishedPage, err);
            }
        };
        if (view === 'published') {
            fetchPublished();
        }
    }, [view, publishedPage, publishedPerPage]);

    // Handle mobile sidebar body scroll lock and window resize
    useEffect(() => {
        const handleResize = () => {
            // Close sidebar when switching to desktop
            if (window.innerWidth > 782 && isSidebarOpen) {
                setIsSidebarOpen(false);
            }
            
            // Update body class based on current state
            if (isSidebarOpen && window.innerWidth <= 782) {
                document.body.classList.add('aicoagac-sidebar-open');
            } else {
                document.body.classList.remove('aicoagac-sidebar-open');
            }
        };
        
        // Initial check
        handleResize();
        
        // Add resize listener
        window.addEventListener('resize', handleResize);
        
        // Cleanup on unmount
        return () => {
            window.removeEventListener('resize', handleResize);
            document.body.classList.remove('aicoagac-sidebar-open');
        };
    }, [isSidebarOpen]);

    return (
        <>
            <div className="aicoagac-container">
                {/* Mobile hamburger menu button */}
                <button 
                    className="aicoagac-mobile-hamburger"
                    onClick={(e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        setIsSidebarOpen(!isSidebarOpen);
                    }}
                    aria-label="Toggle navigation menu"
                    style={{ display: window.innerWidth <= 782 ? 'block' : 'none' }}
                >
                    ☰
                </button>
                
                {/* Mobile overlay */}
                <div 
                    className={`aicoagac-mobile-overlay ${isSidebarOpen ? 'active' : ''}`}
                    onClick={(e) => {
                        e.stopPropagation();
                        setIsSidebarOpen(false);
                    }}
                />
                
                {/* Sidebar */}
                <Sidebar 
                    currentView={view} 
                    setView={setView} 
                    isOpen={isSidebarOpen} 
                    closeSidebar={() => setIsSidebarOpen(false)} 
                />
                
                {/* Main content */}
                <div className="aicoagac-main">
                    {/* Mobile header */}
                    <div className="aicoagac-mobile-header">
                        <button 
                            onClick={() => setIsSidebarOpen(true)} 
                            className="aicoagac-menu-toggle"
                            aria-label="Open menu"
                        >
                            <Menu className="h-6 w-6" />
                        </button>
                        <span className="font-semibold text-white">AI Content Agent (ACA)</span>
                    </div>
                    
                    {/* Page content */}
                    <div className="aicoagac-fade-in">
                        {/* Gemini API Warning - Show on all pages except Settings if API key is missing */}
                        {!isGeminiApiConfigured && !view.startsWith('settings') && (
                            <GeminiApiWarning onNavigateToSettings={() => {
                                setView('settings_integrations');
                            }} />
                        )}
                        {renderView()}
                    </div>
                </div>
            </div>
            
            {/* Draft modal */}
            {selectedDraft && (
                <DraftModal
                    draft={selectedDraft}
                    onClose={() => setSelectedDraft(null)}
                    onSave={handleUpdateDraft}
                    settings={settings}
                />
            )}
            
            {/* Toast notifications */}
            <div className="aicoagac-toast-container">
                {toasts.map(toast => (
                    <Toast key={toast.id} {...toast} onDismiss={removeToast} />
                ))}
            </div>
        </>
    );
};

export default App;