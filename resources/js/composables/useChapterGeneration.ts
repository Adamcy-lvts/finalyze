import { nextTick, ref } from 'vue';
import { route } from 'ziggy-js';
import { toast } from 'vue-sonner';
import { useSmoothScroller } from '@/utils/smoothScroller';
import { recordWordUsage } from '@/composables/useWordBalance';
import type { ChapterEditorProps } from '@/types/chapter-editor';
import type { Ref, ComputedRef } from 'vue';

export interface UseChapterGenerationDeps {
    props: ChapterEditorProps;
    chapterContent: Ref<string>;
    targetWordCount: ComputedRef<number>;
    estimates: {
        chapter: (targetWords: number) => number;
        suggestion: () => number;
        rephrase: () => number;
        expand: () => number;
        defense: () => number;
    };
    ensureBalance: (requiredWords: number, action: string) => boolean;
    save: (autoSave?: boolean) => Promise<void>;
    triggerAutoSave: () => void;
    calculateWritingStats: () => void;
    countWords: (text: string) => number;
    selectedText: Ref<string>;
    richTextEditor: Ref<{ editor?: any } | null>;
    richTextEditorFullscreen: Ref<{ editor?: any } | null>;
    isNativeFullscreen: Ref<boolean>;
}

export function useChapterGeneration({
    props,
    chapterContent,
    targetWordCount,
    estimates,
    ensureBalance,
    save,
    triggerAutoSave,
    calculateWritingStats,
    countWords,
    selectedText,
    richTextEditor,
    richTextEditorFullscreen,
    isNativeFullscreen,
}: UseChapterGenerationDeps) {
    const isGenerating = ref(false);
    const generationProgress = ref('');
    const generationPercentage = ref(0);
    const generationPhase = ref('');
    const estimatedTotalWords = ref(0);
    const streamWordCount = ref(0);
    const streamBuffer = ref('');
    const lastStreamUpdate = ref(0);
    const originalContentForAppend = ref('');
    const aiSuggestions = ref<string[]>([]);
    const isLoadingSuggestions = ref(false);
    const showCitationHelper = ref(false);
    const showPresentationMode = ref(false);
    const isStreamingMode = ref(false);
    const showRecoveryDialog = ref(false);
    const partialContentSaved = ref(false);
    const savedWordCountOnError = ref(0);

    // Content history for undo/redo functionality
    interface ContentHistoryEntry {
        content: string;
        action: string;
        timestamp: number;
    }
    const contentHistory = ref<ContentHistoryEntry[]>([]);
    const maxHistoryEntries = 10;
    const canUndo = ref(false);
    const lastUndoneContent = ref<string | null>(null);

    const isCollectingPapers = ref(false);
    const paperCollectionProgress = ref('');
    const paperCollectionPhase = ref('');
    const collectedPapersCount = ref(0);
    const paperCollectionPercentage = ref(0);
    const currentSource = ref<string | null>(null);
    const sourcesCompleted = ref<string[]>([]);
    const papersPreview = ref<any[]>([]);
    const paperCollectionData = ref<any>(null);
    const paperCollectionInterval = ref<NodeJS.Timeout | null>(null);

    const reconnectAttempts = ref(0);
    const maxReconnectAttempts = 3;
    const reconnectDelay = ref(2000);
    const isReconnecting = ref(false);
    const currentGenerationId = ref<string | null>(null);
    const currentGenerationType = ref<string>('progressive');
    const eventSource = ref<EventSource | null>(null);

    const editorScrollRef = ref();
    const cachedScrollContainer = ref<HTMLElement | null>(null);
    const {
        attach: attachScroller,
        scrollToBottom: smoothScrollToBottom,
        forceScrollToBottom,
        reset: resetScroller,
        isAutoScrollActive,
        isUserScrolling: isUserScrollingScroller,
    } = useSmoothScroller({
        userScrollTimeout: 2000,
        scrollBehavior: 'smooth',
        bottomThreshold: 100,
    });

    // Find the scroll container - called once at generation start
    const findScrollContainer = (): HTMLElement | null => {
        // Priority 1: Find element with data-editor-scroll-container and get its Radix viewport
        const markedContainer = document.querySelector('[data-editor-scroll-container]') as HTMLElement;
        if (markedContainer) {
            // If it's a ScrollArea, the actual scrollable is the viewport inside
            const viewport = markedContainer.querySelector('[data-radix-scroll-area-viewport]') as HTMLElement;
            if (viewport) {
                console.log('📜 Found Radix viewport inside marked container');
                return viewport;
            }
            // If it has overflow-y-auto directly (fullscreen mode), use it
            if (markedContainer.classList.contains('overflow-y-auto') ||
                getComputedStyle(markedContainer).overflowY === 'auto' ||
                getComputedStyle(markedContainer).overflowY === 'scroll') {
                console.log('📜 Using marked container directly (has overflow)');
                return markedContainer;
            }
        }

        // Priority 2: Direct Radix scroll area viewport in main content area
        const mainContent = document.querySelector('main');
        if (mainContent) {
            const viewport = mainContent.querySelector('[data-radix-scroll-area-viewport]') as HTMLElement;
            if (viewport) {
                console.log('📜 Found Radix viewport in main');
                return viewport;
            }
        }

        // Priority 3: Fullscreen mode specific selectors
        if (isNativeFullscreen.value) {
            const scrollAreaRef = editorScrollRef.value;
            if (scrollAreaRef) {
                const scrollAreaEl = scrollAreaRef.$el || scrollAreaRef;
                const viewport = scrollAreaEl?.querySelector('[data-radix-scroll-area-viewport]') ||
                    scrollAreaEl?.querySelector('[data-viewport]') ||
                    scrollAreaEl?.querySelector('[role="region"]');
                if (viewport) {
                    console.log('📜 Found viewport in fullscreen ref');
                    return viewport as HTMLElement;
                }
            }
            // Fullscreen mode uses direct overflow div
            const fullscreenScroll = document.querySelector('[data-editor-scroll-container].overflow-y-auto') as HTMLElement;
            if (fullscreenScroll) {
                console.log('📜 Found fullscreen scroll container');
                return fullscreenScroll;
            }
        }

        // Priority 4: Common scrollable container patterns
        const containerSelectors = [
            '.custom-scrollbar.overflow-y-auto',
            'main .overflow-y-auto',
        ];

        for (const selector of containerSelectors) {
            const element = document.querySelector(selector) as HTMLElement;
            if (element && element.scrollHeight > element.clientHeight) {
                console.log('📜 Found container via selector:', selector);
                return element;
            }
        }

        // Priority 5: Fallback - search within main content
        if (mainContent) {
            const scrollables = mainContent.querySelectorAll('div');
            for (const div of scrollables) {
                if (div.scrollHeight > div.clientHeight && div.classList.contains('overflow-y-auto')) {
                    console.log('📜 Found fallback scrollable div');
                    return div as HTMLElement;
                }
            }
        }

        return null;
    };

    // Initialize scroller - call once when generation starts
    const initializeAutoScroll = () => {
        nextTick(() => {
            cachedScrollContainer.value = findScrollContainer();
            if (cachedScrollContainer.value) {
                attachScroller(cachedScrollContainer.value);
                console.log('📜 Auto-scroll initialized on container:', cachedScrollContainer.value.className || cachedScrollContainer.value.tagName);
            } else {
                console.warn('📜 No scroll container found for auto-scroll');
            }
        });
    };

    // Scroll to bottom - call on each content update (simple direct approach like TopicsLab.vue)
    const scrollToBottom = () => {
        // Only scroll during active generation or streaming
        if (!isGenerating.value && !isStreamingMode.value) return;

        nextTick(() => {
            // Find container if not cached or if previous container is no longer in DOM
            if (!cachedScrollContainer.value || !cachedScrollContainer.value.isConnected) {
                cachedScrollContainer.value = findScrollContainer();
            }

            // Use requestAnimationFrame to ensure TipTap has finished rendering
            requestAnimationFrame(() => {
                if (cachedScrollContainer.value && cachedScrollContainer.value.isConnected) {
                    // Direct scroll - immediate for real-time follow
                    cachedScrollContainer.value.scrollTop = cachedScrollContainer.value.scrollHeight;
                }
            });
        });
    };

    const checkConnectionQuality = async (): Promise<boolean> => {
        if (!navigator.onLine) {
            toast.error('No Internet Connection', {
                description: 'Please check your internet connection and try again.',
            });
            return false;
        }

        const connection = (navigator as any).connection || (navigator as any).mozConnection || (navigator as any).webkitConnection;
        if (connection) {
            const effectiveType = connection.effectiveType;
            if (effectiveType === 'slow-2g' || effectiveType === '2g') {
                toast.warning('Slow Connection Detected', {
                    description: 'Your connection is slow. Content will be auto-saved during generation.',
                    duration: 4000,
                });
            }
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            const pingStart = performance.now();
            await fetch(route('api.ping'), {
                method: 'HEAD',
                cache: 'no-cache',
                signal: controller.signal,
            });
            clearTimeout(timeoutId);
            const latency = performance.now() - pingStart;
            console.log('📡 Server latency:', latency.toFixed(0), 'ms');
            if (latency > 2000) {
                toast.info('Connection is slow', {
                    description: 'Content will be auto-saved during generation to prevent data loss.',
                    duration: 3000,
                });
            }
        } catch (error) {
            console.warn('Connection check ping failed:', error);
            toast.info('Connection check skipped', {
                description: 'Content will be auto-saved during generation.',
                duration: 2000,
            });
        }

        return true;
    };

    // Content history management for undo functionality
    const pushToHistory = (action: string) => {
        const currentContent = chapterContent.value;
        if (!currentContent) return;

        // Don't push if the content is the same as the last entry
        if (contentHistory.value.length > 0 &&
            contentHistory.value[contentHistory.value.length - 1].content === currentContent) {
            return;
        }

        contentHistory.value.push({
            content: currentContent,
            action,
            timestamp: Date.now(),
        });

        // Limit history size
        if (contentHistory.value.length > maxHistoryEntries) {
            contentHistory.value.shift();
        }

        canUndo.value = contentHistory.value.length > 0;
    };

    const undoLastAction = () => {
        if (contentHistory.value.length === 0) {
            toast.error('Nothing to undo');
            return false;
        }

        const lastEntry = contentHistory.value.pop();
        if (lastEntry) {
            lastUndoneContent.value = chapterContent.value;
            chapterContent.value = lastEntry.content;
            canUndo.value = contentHistory.value.length > 0;

            toast.success('Undone', {
                description: `Reverted "${lastEntry.action}" action`,
                action: {
                    label: 'Redo',
                    onClick: () => {
                        if (lastUndoneContent.value) {
                            pushToHistory('Redo');
                            chapterContent.value = lastUndoneContent.value;
                            lastUndoneContent.value = null;
                        }
                    },
                },
            });

            return true;
        }
        return false;
    };

    const clearHistory = () => {
        contentHistory.value = [];
        canUndo.value = false;
        lastUndoneContent.value = null;
    };

    // === GENERATION PROTECTION: beforeunload warning ===

    // Handler for beforeunload event
    const handleBeforeUnload = (e: BeforeUnloadEvent) => {
        if (isGenerating.value && streamWordCount.value > 0) {
            // Standard way to trigger browser's "Leave site?" dialog
            e.preventDefault();
            // Some browsers require returnValue to be set
            e.returnValue = 'Chapter generation is in progress. Your content may not be fully saved.';
            return e.returnValue;
        }
    };

    // Start beforeunload protection when generation starts
    const enableGenerationProtection = () => {
        if (typeof window !== 'undefined') {
            window.addEventListener('beforeunload', handleBeforeUnload);
        }
    };

    // Remove beforeunload protection when generation completes
    const disableGenerationProtection = () => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeunload', handleBeforeUnload);
        }
    };

    // Cleanup function to be called when composable is unmounted
    const cleanupGenerationProtection = () => {
        disableGenerationProtection();
    };

    const monitorPaperCollection = async (): Promise<boolean> => {
        return new Promise((resolve) => {
            let attempts = 0;
            const maxAttempts = 120;

            const checkStatus = async () => {
                try {
                    const response = await fetch(route('api.projects.paper-collection.status', { project: props.project.slug }));
                    const data = await response.json();

                    if (data.success && data.data) {
                        paperCollectionData.value = data.data;
                        const status = data.data.status;
                        const count = data.data.papers_count || data.data.count || 0;
                        const message = data.data.message;
                        const percentage = data.data.percentage || 0;
                        const current_source = data.data.current_source;
                        const sources_completed = data.data.sources_completed || [];
                        const papers_preview = data.data.papers_preview || [];

                        collectedPapersCount.value = count;
                        paperCollectionPercentage.value = percentage;
                        currentSource.value = current_source;
                        sourcesCompleted.value = sources_completed;
                        papersPreview.value = papers_preview;

                        if (status === 'completed') {
                            paperCollectionPhase.value = 'Complete';
                            paperCollectionProgress.value = `✓ Collected ${count} verified sources from ${sources_completed.length} databases`;
                            generationPercentage.value = 50;
                            isCollectingPapers.value = false;

                            if (paperCollectionInterval.value) {
                                clearInterval(paperCollectionInterval.value);
                            }
                            resolve(true);
                            return;
                        } else if (['collecting_papers', 'initializing', 'processing', 'storing'].includes(status)) {
                            let phaseDisplay = '';
                            if (current_source) {
                                const sourceNames: Record<string, string> = {
                                    semantic_scholar: 'Semantic Scholar',
                                    openalex: 'OpenAlex',
                                    arxiv: 'arXiv',
                                    crossref: 'CrossRef',
                                    pubmed: 'PubMed',
                                };
                                phaseDisplay = sourceNames[current_source] || current_source;
                            }

                            paperCollectionPhase.value = phaseDisplay || 'Collecting Sources';
                            paperCollectionProgress.value = message || 'Collecting sources from academic databases...';
                            generationPercentage.value = Math.min(5 + (percentage * 0.4), 45);
                        } else if (status === 'collection_failed') {
                            paperCollectionPhase.value = 'Error';
                            paperCollectionProgress.value = message || '❌ Source collection failed';
                            isCollectingPapers.value = false;
                            if (paperCollectionInterval.value) {
                                clearInterval(paperCollectionInterval.value);
                            }
                            resolve(false);
                            return;
                        }
                    }

                    attempts++;
                    if (attempts >= maxAttempts) {
                        paperCollectionPhase.value = 'Timeout';
                        paperCollectionProgress.value = '⏱️ Source collection timed out';
                        isCollectingPapers.value = false;

                        if (paperCollectionInterval.value) {
                            clearInterval(paperCollectionInterval.value);
                        }
                        resolve(false);
                    }
                } catch (error) {
                    console.error('Error checking paper collection status:', error);
                    attempts++;
                }
            };

            checkStatus();
            paperCollectionInterval.value = setInterval(checkStatus, 5000);
        });
    };

    const startPaperCollection = async (): Promise<boolean> => {
        isCollectingPapers.value = true;
        paperCollectionPhase.value = 'Starting';
        paperCollectionProgress.value = 'Initializing source collection...';
        generationPercentage.value = 5;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            if (!csrfToken) {
                throw new Error('CSRF token not found - please refresh the page');
            }

            const response = await fetch(route('api.projects.paper-collection.start', { project: props.project.slug }), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error:', response.status, errorText);

                if (response.status === 409) {
                    try {
                        const errorData = JSON.parse(errorText);
                        throw new Error(errorData.message || 'Source collection is already in progress');
                    } catch (parseError) {
                        throw new Error('Source collection is already in progress for this project');
                    }
                }

                throw new Error(`Failed to start source collection: ${response.status}`);
            }

            return await monitorPaperCollection();
        } catch (error) {
            console.error('Paper collection failed:', error);
            paperCollectionPhase.value = 'Error';
            paperCollectionProgress.value = '❌ Source collection failed';
            isCollectingPapers.value = false;
            return false;
        }
    };

    const startStreamingGeneration = async (type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand' | 'custom', options: { section?: string, mode?: string, selectedText?: string, style?: string, customPrompt?: string } = {}) => {
        // Quick action guard: for "improve", only require a minimum balance threshold
        const requiredWords = type === 'improve' ? 300 : estimates.chapter(targetWordCount.value || 0);
        const actionLabel = type === 'improve' ? 'improve this chapter' : 'generate this chapter with AI';
        if (!ensureBalance(requiredWords, actionLabel)) {
            return;
        }

        const connectionOk = await checkConnectionQuality();
        if (!connectionOk) {
            return;
        }

        isGenerating.value = true;
        streamBuffer.value = '';
        streamWordCount.value = 0;
        generationPercentage.value = 5;
        generationPhase.value = 'Papers';
        generationProgress.value = 'Checking for verified sources...';

        isStreamingMode.value = true;
        resetScroller();
        estimatedTotalWords.value = targetWordCount.value || 0;
        showPresentationMode.value = true;

        const papersCollected = await startPaperCollection();

        if (!papersCollected) {
            toast.error('Paper Collection Failed', {
                description: 'Unable to collect verified papers. Please try again.',
            });
            isGenerating.value = false;
            showPresentationMode.value = false;
            return;
        }

        // Enable generation protection (beforeunload warning)
        enableGenerationProtection();

        generationPhase.value = 'Initializing';
        generationProgress.value = 'Starting AI generation with verified sources...';
        generationPercentage.value = 51;

        const initProgressInterval = setInterval(() => {
            if (generationPercentage.value < 52 && isGenerating.value) {
                generationPercentage.value += 0.2;
            } else {
                clearInterval(initProgressInterval);
            }
        }, 100);

        const url = route('chapters.stream', {
            project: props.project.slug,
            chapter: props.chapter.chapter_number,
        });

        let queryParams = `?generation_type=${type}`;
        if (options.section) queryParams += `&section_type=${encodeURIComponent(options.section)}`;
        if (options.selectedText) queryParams += `&selected_text=${encodeURIComponent(options.selectedText)}`;
        if (options.style) queryParams += `&style=${encodeURIComponent(options.style)}`;

        eventSource.value = new EventSource(`${url}${queryParams}`);

        eventSource.value.onmessage = (event) => {
            const data = JSON.parse(event.data);

            switch (data.type) {
                case 'start':
                    generationPhase.value = 'Connecting';
                    generationPercentage.value = 52;
                    generationProgress.value = 'Connecting to AI service...';
                    // Initialize auto-scroll when streaming starts
                    initializeAutoScroll();
                    break;

                case 'content':
                    generationPhase.value = 'Writing';
                    streamBuffer.value += data.content;
                    streamWordCount.value = data.word_count || streamBuffer.value.split(/\s+/).filter((word) => word.length > 0).length;

                    const now = Date.now();
                    if (now - lastStreamUpdate.value > 200) {
                        chapterContent.value = streamBuffer.value;
                        lastStreamUpdate.value = now;

                        const wordProgress = Math.min((streamWordCount.value / Math.max(estimatedTotalWords.value, 1)) * 43, 43);
                        generationPercentage.value = Math.max(52, 52 + wordProgress);
                        generationProgress.value = `Writing chapter content... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;
                        scrollToBottom();
                    }
                    break;

                case 'heartbeat':
                    if (generationPercentage.value < 95) {
                        generationPercentage.value += 0.5;
                    }
                    break;

                case 'complete':
                    generationPhase.value = 'Complete';
                    generationPercentage.value = 100;
                    isGenerating.value = false;
                    isStreamingMode.value = false;
                    cachedScrollContainer.value = null; // Clear scroll container reference
                    chapterContent.value = streamBuffer.value;

                    // Disable protection since generation completed successfully
                    disableGenerationProtection();

                    const finalWords = data.final_word_count || streamWordCount.value;
                    generationProgress.value = `✓ Generated ${finalWords} words successfully`;
                    calculateWritingStats();

                    recordWordUsage(
                        finalWords,
                        `Chapter generation (${props.chapter.chapter_number})`,
                        'chapter',
                        props.chapter.id,
                    ).catch((err) => console.error('Failed to record word usage (chapter generation):', err));

                    setTimeout(() => {
                        triggerAutoSave();
                    }, 500);

                    eventSource.value?.close();
                    eventSource.value = null;
                    break;

                case 'error':
                    generationPhase.value = 'Error';
                    generationPercentage.value = 50;
                    isStreamingMode.value = false;
                    cachedScrollContainer.value = null; // Clear scroll container reference

                    // Disable protection
                    disableGenerationProtection();

                    if (data.partial_saved) {
                        partialContentSaved.value = true;
                        savedWordCountOnError.value = data.saved_word_count || 0;
                    }

                    if (data.code === 'OFFLINE_MODE') {
                        generationProgress.value = '📡 AI services offline';
                        isGenerating.value = false;
                        toast.error('AI Services Offline', {
                            description: 'Please check your internet connection and try again.',
                        });
                    } else if (data.can_resume) {
                        generationProgress.value = `⚠️ Generation interrupted (${data.saved_word_count} words saved)`;
                        isGenerating.value = false;
                        showRecoveryDialog.value = true;
                        toast.warning('Generation Interrupted', {
                            description: `${data.saved_word_count} words were saved. You can resume generation.`,
                            duration: 8000,
                        });
                    } else {
                        generationProgress.value = '❌ Generation failed';
                        isGenerating.value = false;
                        toast.error('Generation Error', {
                            description: data.message || 'Please try again.',
                        });
                    }

                    eventSource.value?.close();
                    eventSource.value = null;
                    break;

                case 'autosave':
                    if (data.word_count && data.generation_id) {
                        currentGenerationId.value = data.generation_id;
                    }
                    break;
            }
        };

        eventSource.value.onerror = () => {
            if (reconnectAttempts.value < maxReconnectAttempts) {
                reconnectAttempts.value++;
                isReconnecting.value = true;
                generationPhase.value = 'Reconnecting';
                generationProgress.value = `🔄 Connection lost. Reconnecting... (${reconnectAttempts.value}/${maxReconnectAttempts})`;

                eventSource.value?.close();
                eventSource.value = null;

                const delay = reconnectDelay.value * Math.pow(2, reconnectAttempts.value - 1);
                setTimeout(() => attemptReconnection(type as any), delay);
            } else {
                generationPhase.value = 'Error';
                generationPercentage.value = 50;
                isGenerating.value = false;
                isReconnecting.value = false;
                generationProgress.value = '❌ Connection failed after multiple attempts';

                // Disable protection
                disableGenerationProtection();

                if (streamWordCount.value > 100) {
                    partialContentSaved.value = true;
                    savedWordCountOnError.value = streamWordCount.value;
                    showRecoveryDialog.value = true;
                    toast.warning('Connection Lost', {
                        description: `${streamWordCount.value} words have been saved. Check and resume if needed.`,
                        duration: 8000,
                    });
                } else {
                    toast.error('Connection Error', {
                        description: 'Please check your internet connection and try again.',
                    });
                }

                eventSource.value?.close();
                eventSource.value = null;
                reconnectAttempts.value = 0;
                reconnectDelay.value = 2000;
            }
        };
    };

    const attemptReconnection = (type: 'progressive' | 'outline' | 'improve') => {
        if (eventSource.value) {
            eventSource.value.close();
            eventSource.value = null;
        }

        const url = route('chapters.stream', {
            project: props.project.slug,
            chapter: props.chapter.chapter_number,
        });

        const resumeParams = new URLSearchParams({
            generation_type: type,
            resume_from: streamWordCount.value.toString(),
        });

        if (currentGenerationId.value) {
            resumeParams.set('generation_id', currentGenerationId.value);
        }

        eventSource.value = new EventSource(`${url}?${resumeParams}`);

        eventSource.value.onmessage = (event) => {
            const data = JSON.parse(event.data);

            if (['content', 'start'].includes(data.type)) {
                isReconnecting.value = false;
                reconnectAttempts.value = 0;
                generationPhase.value = 'Writing';
            }

            switch (data.type) {
                case 'start':
                    generationProgress.value = '✅ Reconnected! Continuing generation...';
                    // Re-initialize auto-scroll after reconnection
                    initializeAutoScroll();
                    toast.success('Reconnected', {
                        description: 'Generation resumed successfully.',
                        duration: 3000,
                    });
                    break;

                case 'content':
                    generationPhase.value = 'Writing';
                    streamBuffer.value += data.content;
                    streamWordCount.value = data.word_count || streamBuffer.value.split(/\s+/).filter((word) => word.length > 0).length;

                    const now = Date.now();
                    if (now - lastStreamUpdate.value > 150) {
                        chapterContent.value = streamBuffer.value;
                        lastStreamUpdate.value = now;

                        const wordProgress = Math.min((streamWordCount.value / Math.max(estimatedTotalWords.value, 1)) * 43, 43);
                        generationPercentage.value = Math.max(52, 52 + wordProgress);
                        generationProgress.value = `Writing chapter content... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;
                        scrollToBottom();
                    }
                    break;

                case 'complete':
                    generationPhase.value = 'Complete';
                    generationPercentage.value = 100;
                    isGenerating.value = false;
                    isReconnecting.value = false;
                    cachedScrollContainer.value = null; // Clear scroll container reference
                    chapterContent.value = streamBuffer.value;

                    const finalWords = data.final_word_count || streamWordCount.value;
                    generationProgress.value = `✓ Generated ${finalWords} words successfully`;

                    recordWordUsage(
                        finalWords,
                        `Chapter generation (${props.chapter.chapter_number})`,
                        'chapter',
                        props.chapter.id,
                    ).catch((err) => console.error('Failed to record word usage:', err));

                    setTimeout(() => {
                        triggerAutoSave();
                    }, 500);

                    eventSource.value?.close();
                    eventSource.value = null;
                    break;

                case 'error':
                    generationPhase.value = 'Error';
                    isGenerating.value = false;
                    isReconnecting.value = false;
                    cachedScrollContainer.value = null; // Clear scroll container reference
                    generationProgress.value = '❌ Generation failed';
                    toast.error('Generation Error', {
                        description: data.message || 'Please try again.',
                    });
                    eventSource.value?.close();
                    eventSource.value = null;
                    break;

                case 'autosave':
                    if (data.generation_id) {
                        currentGenerationId.value = data.generation_id;
                    }
                    break;
            }
        };

        eventSource.value.onerror = () => {
            if (reconnectAttempts.value < maxReconnectAttempts) {
                reconnectAttempts.value++;
                const delay = reconnectDelay.value * Math.pow(2, reconnectAttempts.value - 1);
                generationProgress.value = `🔄 Reconnecting... (${reconnectAttempts.value}/${maxReconnectAttempts})`;

                eventSource.value?.close();
                setTimeout(() => attemptReconnection(type), delay);
            } else {
                generationPhase.value = 'Error';
                isGenerating.value = false;
                isReconnecting.value = false;
                generationProgress.value = '❌ Connection failed';

                if (streamWordCount.value > 100) {
                    showRecoveryDialog.value = true;
                    toast.warning('Connection Lost', {
                        description: `${streamWordCount.value} words may be saved. Refresh to recover.`,
                    });
                } else {
                    toast.error('Connection Error', {
                        description: 'Please check your connection and try again.',
                    });
                }

                eventSource.value?.close();
                eventSource.value = null;
                reconnectAttempts.value = 0;
            }
        };
    };

    const startSectionGeneration = async (sectionType: string) => {
        isGenerating.value = true;
        streamBuffer.value = '';
        streamWordCount.value = 0;
        generationPercentage.value = 5;
        generationPhase.value = 'Papers';
        generationProgress.value = 'Checking for verified sources...';
        estimatedTotalWords.value = 600;
        originalContentForAppend.value = chapterContent.value || props.chapter.content || '';
        showPresentationMode.value = true;

        try {
            generationPercentage.value = 50;
            generationPhase.value = 'Papers';
            generationProgress.value = 'Using existing verified sources...';

            await new Promise((resolve) => setTimeout(resolve, 500));

            generationPhase.value = 'Section';
            generationProgress.value = `Writing ${sectionType} section with verified sources...`;
            generationPercentage.value = 51;

            const url = route('chapters.stream', {
                project: props.project.slug,
                chapter: props.chapter.chapter_number,
            });

            eventSource.value = new EventSource(`${url}?generation_type=section&section_type=${sectionType}`);

            eventSource.value.onmessage = (event) => {
                const data = JSON.parse(event.data);

                switch (data.type) {
                    case 'start':
                        generationPhase.value = 'Writing Section';
                        generationPercentage.value = 52;
                        generationProgress.value = `Starting ${sectionType} section generation...`;
                        break;

                    case 'content':
                        streamBuffer.value += data.content;
                        streamWordCount.value = data.word_count || countWords(streamBuffer.value);

                        const now = Date.now();
                        if (now - lastStreamUpdate.value > 150) {
                            chapterContent.value = originalContentForAppend.value + '\n\n' + streamBuffer.value;
                            lastStreamUpdate.value = now;

                            const progress = Math.min((streamWordCount.value / estimatedTotalWords.value) * 43, 43);
                            generationPercentage.value = Math.max(52, 52 + progress);
                            generationProgress.value = `Writing ${sectionType} section... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;

                            calculateWritingStats();
                            scrollToBottom();
                        }
                        break;

                    case 'complete':
                        generationPercentage.value = 100;
                        generationPhase.value = 'Complete';
                        generationProgress.value = `✓ Generated ${sectionType} section (${streamWordCount.value} words)`;
                        chapterContent.value = originalContentForAppend.value + '\n\n' + streamBuffer.value;
                        save(true);
                        toast.success('✅ Section Generated Successfully', {
                            description: `Added ${sectionType} section with ${streamWordCount.value} words and verified citations.`,
                            duration: 5000,
                        });
                        isGenerating.value = false;
                        eventSource.value?.close();
                        break;

                    case 'error':
                        throw new Error(data.message || 'Section generation failed');

                    case 'heartbeat':
                        if (generationPercentage.value < 95) {
                            generationPercentage.value += 0.5;
                        }
                        break;
                }
            };

            eventSource.value.onerror = () => {
                isGenerating.value = false;
                generationPhase.value = 'Error';
                generationProgress.value = '❌ Section generation failed';
                toast.error('❌ Generation Failed', {
                    description: 'Unable to generate section. Please check your connection and try again.',
                });
                eventSource.value?.close();
            };
        } catch (error) {
            console.error('Section generation failed:', error);
            isGenerating.value = false;
            generationPhase.value = 'Error';
            generationProgress.value = '❌ Section generation error';
            toast.error('❌ Generation Failed', {
                description: 'Section generation encountered an error.',
            });
        }
    };

    const handleSelectionGeneration = async (selectedTextValue: string, action: 'rephrase' | 'expand', style?: string) => {
        const selectedWordCount = selectedTextValue.split(/\s+/).length;
        // Quick action guard: only require a minimum balance threshold for selection actions
        const requiredWords = 300;

        if (!ensureBalance(requiredWords, `${action} selected text`)) {
            return;
        }

        isGenerating.value = true;
        streamBuffer.value = '';
        streamWordCount.value = 0;
        generationPercentage.value = 10;
        generationPhase.value = action === 'rephrase' ? 'Rephrasing' : 'Expanding';
        generationProgress.value = `${action === 'rephrase' ? 'Rephrasing' : 'Expanding'} selected text...`;
        estimatedTotalWords.value = Math.max(selectedWordCount * (action === 'expand' ? 2 : 1), 100);
        const activeEditor = richTextEditorFullscreen.value || richTextEditor.value;
        const selectionRange = activeEditor?.getSelectionRange();
        const context = {
            originalText: selectedTextValue,
            range: selectionRange,
            wordCount: selectedWordCount,
            style: style || 'Academic Formal',
            startTime: Date.now(),
        };

        showPresentationMode.value = true;

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            if (!csrfToken) {
                throw new Error('CSRF token not found - please refresh the page');
            }

            const endpoint =
                action === 'rephrase'
                    ? route('chapters.quick-actions.rephrase', { project: props.project.slug, chapter: props.chapter.chapter_number })
                    : route('chapters.quick-actions.expand', { project: props.project.slug, chapter: props.chapter.chapter_number });

            generationPercentage.value = 35;

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
                body: JSON.stringify({
                    text: selectedTextValue,
                    ...(action === 'rephrase' ? { style: context.style } : {}),
                }),
            });

            if (!response.ok) {
                let message = `Request failed (${response.status})`;
                try {
                    const err = await response.json();
                    message = err?.message || err?.error || err?.data?.message || message;
                } catch {
                    // ignore
                }
                throw new Error(message);
            }

            const data = await response.json();
            const resultText = String(data?.text || '').trim();
            if (!resultText) throw new Error('Empty response from AI service');

            generationPercentage.value = 100;
            generationPhase.value = 'Complete';
            generationProgress.value = `✓ Text ${action === 'rephrase' ? 'rephrased' : 'expanded'} successfully`;

            if (context.range) {
                const success = activeEditor?.replaceSelection(resultText, context.range);
                if (success) {
                    setTimeout(() => {
                        const newContent = activeEditor?.getHTML() || chapterContent.value;
                        chapterContent.value = newContent;
                    }, 50);
                } else {
                    toast.warning('Please manually replace the selected text', {
                        description: 'The generated text is ready but could not be automatically inserted.',
                    });
                }
            }

            save(true);

            toast.success(`✅ Text ${action === 'rephrase' ? 'Rephrased' : 'Expanded'} Successfully`, {
                description: `${action === 'rephrase' ? 'Rephrased' : 'Expanded'} selection (${selectedWordCount} words).`,
            });

            const used = Number(data?.word_count) || countWords(resultText) || (action === 'expand' ? selectedWordCount * 2 : selectedWordCount);
            recordWordUsage(
                used,
                action === 'rephrase' ? `Rephrase (${context.style})` : 'Expand text',
                'chapter',
                props.chapter.id,
            ).catch((err) => console.error(`Failed to record word usage (${action}):`, err));

            isGenerating.value = false;
        } catch (error) {
            console.error('Selection generation failed:', error);
            isGenerating.value = false;
            generationPhase.value = 'Error';
            generationProgress.value = `❌ Text ${action === 'rephrase' ? 'rephrasing' : 'expansion'} error`;
            toast.error(`❌ ${action === 'rephrase' ? 'Rephrasing' : 'Expansion'} Failed`, {
                description: error instanceof Error ? error.message : 'Text generation encountered an error.',
            });
        }
    };

    const handleAIGeneration = (type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand', options?: { section?: string; selectedText?: string; style?: string }) => {
        if (type === 'section' && options?.section) {
            startSectionGeneration(options.section);
        } else if (type === 'rephrase' && options?.selectedText) {
            handleSelectionGeneration(options.selectedText, 'rephrase', options.style);
        } else if (type === 'expand' && options?.selectedText) {
            handleSelectionGeneration(options.selectedText, 'expand');
        } else {
            startStreamingGeneration(type as 'progressive' | 'outline' | 'improve');
        }
    };

    const getAISuggestions = async () => {
        if (!selectedText.value) return;

        const requiredWords = estimates.suggestion();
        if (!ensureBalance(requiredWords, 'get AI suggestions')) {
            return;
        }

        isLoadingSuggestions.value = true;
        try {
            const response = await fetch(
                route('chapters.suggestions', {
                    project: props.project.slug,
                    chapter: props.chapter.chapter_number,
                }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        selected_text: selectedText.value,
                        context: chapterContent.value,
                    }),
                },
            );

            const data = await response.json();
            aiSuggestions.value = data.suggestions || [];

            if (aiSuggestions.value.length) {
                recordWordUsage(
                    estimates.suggestion(),
                    'AI suggestions',
                    'chapter',
                    props.chapter.id,
                ).catch((err) => console.error('Failed to record word usage (suggestions):', err));
            }
        } catch (error) {
            toast.error('Error getting suggestions', { description: 'Please try again.' });
        } finally {
            isLoadingSuggestions.value = false;
        }
    };

    const checkCitations = () => {
        showCitationHelper.value = true;
        toast.success('Citation Manager opened!', {
            description: 'Review and verify your citations in the panel below.',
        });
    };

    const insertCitation = (citation: string) => {
        try {
            const activeEditor = richTextEditorFullscreen.value?.editor || richTextEditor.value?.editor;
            if (!activeEditor) {
                toast.error('Editor not found');
                return;
            }

            const { from } = activeEditor.state.selection;
            activeEditor.chain().focus().insertContentAt(from, ` ${citation} `).run();
            toast.success('Citation inserted successfully');
        } catch (error) {
            console.error('Failed to insert citation:', error);
            toast.error('Failed to insert citation');
        }
    };

    const resumeGeneration = () => {
        showRecoveryDialog.value = false;
        partialContentSaved.value = false;
        window.location.reload();
    };

    const dismissRecovery = () => {
        showRecoveryDialog.value = false;
        partialContentSaved.value = false;
        savedWordCountOnError.value = 0;
    };

    /**
     * Regenerate chapter - clears existing content and generates fresh
     * @param confirmed - Whether user has confirmed the action (skip confirmation dialog handling)
     * @returns Promise<boolean> - Whether regeneration was started
     */
    const handleRegenerateChapter = async (confirmed: boolean = false): Promise<boolean> => {
        // If not confirmed, return false to indicate component should show confirmation dialog
        if (!confirmed) {
            return false;
        }

        // Check word balance first
        const requiredWords = estimates.chapter(targetWordCount.value || 0);
        if (!ensureBalance(requiredWords, 'regenerate this chapter')) {
            return false;
        }

        // Save current content to history for undo capability
        if (chapterContent.value && chapterContent.value.trim().length > 0) {
            pushToHistory('Before regenerate');
            toast.info('Previous content saved', {
                description: 'You can undo to restore it if needed.',
                duration: 3000,
            });
        }

        // Clear current content
        chapterContent.value = '';

        // Start fresh generation
        await startStreamingGeneration('progressive');

        return true;
    };

    const checkForAutoGeneration = () => {
        const urlParams = new URLSearchParams(window.location.search);
        const shouldGenerate = urlParams.get('ai_generate') === 'true';
        const generationType = urlParams.get('generation_type');

        if (!shouldGenerate || !generationType) {
            return;
        }

        setTimeout(() => {
            startStreamingGeneration('progressive');
            const url = new URL(window.location.href);
            url.searchParams.delete('ai_generate');
            url.searchParams.delete('generation_type');
            window.history.replaceState({}, '', url.toString());
            toast.success('🚀 AI Generation Started', {
                description: `Writing ${props.chapter.title} with AI assistance...`,
            });
        }, 1000);
    };

    /**
     * Stop/abort the current generation
     */
    const stopGeneration = () => {
        if (!isGenerating.value && !isCollectingPapers.value) {
            return;
        }

        // Close EventSource connection
        if (eventSource.value) {
            eventSource.value.close();
            eventSource.value = null;
        }

        // Clear paper collection interval
        if (paperCollectionInterval.value) {
            clearInterval(paperCollectionInterval.value);
            paperCollectionInterval.value = null;
        }

        // Save any generated content so far
        if (streamBuffer.value && streamWordCount.value > 0) {
            chapterContent.value = streamBuffer.value;
            toast.info('Generation Stopped', {
                description: `${streamWordCount.value} words have been saved. You can continue editing or regenerate.`,
                duration: 5000,
            });
        } else {
            toast.info('Generation Stopped', {
                description: 'Generation was cancelled.',
                duration: 3000,
            });
        }

        // Reset generation state
        isGenerating.value = false;
        isStreamingMode.value = false;
        isCollectingPapers.value = false;
        isReconnecting.value = false;
        reconnectAttempts.value = 0;
        reconnectDelay.value = 2000;
        generationPhase.value = 'Stopped';
        generationProgress.value = 'Generation stopped by user';
        cachedScrollContainer.value = null;

        // Auto-save if there's content
        if (streamWordCount.value > 0) {
            setTimeout(() => {
                triggerAutoSave();
            }, 500);
        }
    };

    return {
        isGenerating,
        generationProgress,
        generationPercentage,
        generationPhase,
        estimatedTotalWords,
        streamWordCount,
        streamBuffer,
        lastStreamUpdate,
        originalContentForAppend,
        aiSuggestions,
        isLoadingSuggestions,
        showCitationHelper,
        showPresentationMode,
        isStreamingMode,
        showRecoveryDialog,
        partialContentSaved,
        savedWordCountOnError,
        isCollectingPapers,
        paperCollectionProgress,
        paperCollectionPhase,
        collectedPapersCount,
        paperCollectionPercentage,
        currentSource,
        sourcesCompleted,
        papersPreview,
        paperCollectionData,
        paperCollectionInterval,
        reconnectAttempts,
        reconnectDelay,
        isReconnecting,
        currentGenerationId,
        currentGenerationType,
        eventSource,
        editorScrollRef,
        attachScroller,
        smoothScrollToBottom,
        forceScrollToBottom,
        resetScroller,
        isAutoScrollActive,
        isUserScrollingScroller,
        scrollToBottom,
        checkConnectionQuality,
        startPaperCollection,
        startStreamingGeneration,
        attemptReconnection,
        startSectionGeneration,
        handleSelectionGeneration,
        handleAIGeneration,
        handleRegenerateChapter,
        getAISuggestions,
        checkCitations,
        insertCitation,
        resumeGeneration,
        dismissRecovery,
        checkForAutoGeneration,
        stopGeneration,
        // Undo/Redo functionality
        contentHistory,
        canUndo,
        pushToHistory,
        undoLastAction,
        clearHistory,
        // Generation protection (page refresh/navigation)
        cleanupGenerationProtection,
    };
}
