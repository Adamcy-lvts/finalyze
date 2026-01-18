<!-- resources/js/pages/projects/Defense.vue -->
<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { toast } from 'vue-sonner';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { debounce } from 'lodash-es';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Shield,
    Presentation,
    Zap,
    ArrowLeft,
    Play,
    Target,
    Brain,
    Users,
    Mic,
    Sparkles,
    History,
    Maximize2,
    ArrowUpDown,
    RotateCcw,
    Download
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';

import { Progress } from '@/components/ui/progress';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import DeckViewer from '@/components/defense/DeckViewer.vue';
import WysiwygSlideEditor from '@/components/defense/editor/WysiwygSlideEditor.vue';
import ExecutiveBriefingDeck from '@/components/defense/ExecutiveBriefingDeck.vue';
import PredictedQuestionsDeck from '@/components/defense/PredictedQuestionsDeck.vue';
import { useDefenseSession } from '@/composables/useDefenseSession';
import { ensureWysiwygFormat, wysiwygToLegacy } from '@/utils/editor/migration';
import type { DefenseMessage } from '@/types/defense';
import type { WysiwygSlide } from '@/types/wysiwyg';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    status: string;
}

interface Project {
    id: number;
    slug: string;
    title: string;
    topic: string;
    type: string;
    field_of_study: string;
    chapters: Chapter[];
}

const props = defineProps<{
    project: Project;
}>();

const currentView = ref<'preparation' | 'simulation'>('preparation');
const isSimulating = ref(false);
const readinessScore = ref(72);
const lowBalanceMessage = ref<string | null>(null);
const isAutoEnding = ref(false);
const totalQuestionsPerSession = 10;
const activeSimulationPersonas = ref([
    { id: 'skeptic', name: 'The Skeptic', role: 'Critical Reviewer', avatar: '🧐', color: 'text-amber-500' },
    { id: 'methodologist', name: 'The Methodologist', role: 'Technical Expert', avatar: '🧪', color: 'text-blue-500' },
    { id: 'generalist', name: 'The Generalist', role: 'Value Reviewer', avatar: '🌍', color: 'text-green-500' }
]);

interface PredictedQuestion {
    id?: number;
    question: string;
    suggested_answer: string;
    category?: string;
}

const predictedQuestions = ref<PredictedQuestion[]>([]);
const isLoadingQuestions = ref(false);
const isGeneratingQuestions = ref(false);
const responseInput = ref('');
const executiveBriefing = ref<string | null>(null);
const briefingSlides = ref<{ title: string; content: string }[]>([]);
const isBriefingLoading = ref(false);

interface PresentationSlide {
    title: string;
    duration: string;
    content: string;
    talking_points: string[];
    visuals: string;
}

interface DefenseDeckSlide {
    title: string;
    bullets: string[];
    layout?: string;
    visuals?: string;
    speaker_notes?: string;
    image_url?: string;
    image_fit?: 'cover' | 'contain';
    image_scale?: number;
    image_position_x?: number;
    image_position_y?: number;
    charts?: unknown[];
    tables?: unknown[];
}

const presentationSlides = ref<PresentationSlide[]>([]);
const activeSlideIndex = ref(0);
const startSlideIndex = ref(0); // For keeping sync between minimized and maximized
const rawPresentationGuide = ref<string | null>(null); // Fallback for old data
const isGuideLoading = ref(false);
const isGuideExpanded = ref(false);
const deckView = ref<'guide' | 'slides'>('slides');
const deckStatus = ref<'idle' | 'queued' | 'outlining' | 'extracting' | 'extracted' | 'generating' | 'outlined' | 'rendering' | 'ready' | 'failed'>('idle');
const deckDownloadUrl = ref<string | null>(null);
const deckError = ref<string | null>(null);
const isDeckGenerating = ref(false);
const deckPollInterval = ref<ReturnType<typeof setInterval> | null>(null);
const deckId = ref<number | null>(null);
const deckSlides = ref<DefenseDeckSlide[]>([]);
const wysiwygSlides = ref<WysiwygSlide[]>([]);
const activeDeckSlideIndex = ref(0);
const isDeckSaving = ref(false);
const openingStatement = ref('');
const openingAnalysis = ref<string | null>(null);
const isOpeningAnalyzing = ref(false);
const isOpeningGenerating = ref(false);
const isFetchingQuestion = ref(false);
const thinkingPersonaId = ref<string | null>(null);
const isProduction = import.meta.env.PROD;

const {
    session,
    messages,
    performanceMetrics,
    startSession,
    loadSession,
    getNextQuestion,
    sendResponse,
    endSession,
    isStarting,
    isSending,
} = useDefenseSession(props.project.id);

const isDeckSwapped = ref(false);
const toggleDeckSwap = () => {
    isDeckSwapped.value = !isDeckSwapped.value;
};

const personaLookup = computed(() => {
    return activeSimulationPersonas.value.reduce<Record<string, string>>((acc, persona) => {
        acc[persona.id] = persona.name;
        return acc;
    }, {});
});

const notifyLowBalance = (error: unknown) => {
    const response = (error as { response?: { data?: { message?: string }, status?: number } })?.response;
    const message = response?.data?.message;
    const status = response?.status;

    if (status === 402 || (message && message.includes('Insufficient credit balance'))) {
        lowBalanceMessage.value = message || 'Insufficient credit balance. Please top up to continue.';
        toast.error(lowBalanceMessage.value);
        return true;
    }

    return false;
};

const dismissLowBalance = () => {
    lowBalanceMessage.value = null;
};

const normalizeDeckSlides = (slides: unknown): DefenseDeckSlide[] => {
    if (!slides) return [];
    if (Array.isArray(slides)) return slides as DefenseDeckSlide[];
    if (typeof slides === 'string') {
        try {
            return normalizeDeckSlides(JSON.parse(slides));
        } catch {
            return [];
        }
    }
    if (typeof slides === 'object') {
        const maybeSlides = (slides as { slides?: unknown }).slides;
        if (maybeSlides) {
            return normalizeDeckSlides(maybeSlides);
        }
        return Object.values(slides as Record<string, DefenseDeckSlide>);
    }
    return [];
};

const startSimulation = async () => {
    if (isStarting.value) return;

    try {
        const activeResponse = await axios.get(`/api/projects/${props.project.id}/defense/sessions/active`);
        if (activeResponse.data.session) {
            await openSession(activeResponse.data.session.id);
            return;
        }

        const selectedPanelists = activeSimulationPersonas.value.map(persona => persona.id);
        await startSession({
            selected_panelists: selectedPanelists,
            difficulty_level: 'undergraduate',
            question_limit: totalQuestionsPerSession,
        });

        isSimulating.value = true;
        currentView.value = 'simulation';

        if (session.value) {
            await fetchNextQuestion();
        }
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to start simulation:', error);
    }
};

const stopSimulation = async (autoEnded = false) => {
    if (isAutoEnding.value) return;
    isAutoEnding.value = autoEnded;
    console.log('[Defense] Finish & Analysis clicked', {
        sessionId: session.value?.id,
        status: session.value?.status,
    });

    try {
        if (session.value) {
            const result = await endSession(session.value.id);
            console.log('[Defense] endSession response', result);
            if (result?.recommendations && result.recommendations.toLowerCase().includes('credit balance')) {
                lowBalanceMessage.value = result.recommendations;
                toast.error(result.recommendations);
            }
            if (autoEnded) {
                toast.success('Simulation completed. Your score is ready.');
            }
        }
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('[Defense] Failed to end simulation:', error);
    } finally {
        await loadSessionHistory();
        isSimulating.value = false;
        currentView.value = 'preparation';
        isAutoEnding.value = false;
    }
};

const submitResponse = async () => {
    if (!session.value || !responseInput.value.trim()) return;

    const response = responseInput.value.trim();
    responseInput.value = '';

    try {
        await sendResponse(session.value.id, response);
        if (session.value?.question_limit && session.value.questions_asked >= session.value.question_limit) {
            await stopSimulation(true);
            return;
        }
        await fetchNextQuestion();
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to submit response:', error);
    }
};

const fetchNextQuestion = async (requestHint = false) => {
    if (!session.value) return;
    isFetchingQuestion.value = true;
    try {
        const message = await getNextQuestion(session.value.id, undefined, requestHint);
        thinkingPersonaId.value = message.panelist_persona ?? null;
    } catch (error) {
        if (notifyLowBalance(error)) return;
        const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
        if (message && message.toLowerCase().includes('question limit')) {
            await stopSimulation(true);
            return;
        }
        console.error('Failed to fetch next question:', error);
    } finally {
        isFetchingQuestion.value = false;
    }
};

const requestHint = async () => {
    if (!session.value || isFetchingQuestion.value) return;
    await fetchNextQuestion(true);
};

const refreshExecutiveBriefing = async () => {
    if (isBriefingLoading.value) return;
    isBriefingLoading.value = true;

    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/executive-briefing`, {
            params: { force_refresh: 1 },
        });

        const briefingData = response.data.briefing;

        // Handle JSON parsing for slides
        if (typeof briefingData === 'string' && briefingData.includes('```json')) {
            try {
                const jsonStr = briefingData.replace(/```json\n?|\n?```/g, '');
                const parsed = JSON.parse(jsonStr);
                briefingSlides.value = parsed.slides || [];
                executiveBriefing.value = null;
            } catch (e) {
                console.warn('Failed to parse briefing JSON', e);
                executiveBriefing.value = briefingData;
                briefingSlides.value = [];
            }
        } else if (typeof briefingData === 'object' && briefingData.slides) {
            briefingSlides.value = briefingData.slides;
            executiveBriefing.value = null;
        } else {
            executiveBriefing.value = briefingData ?? null;
            briefingSlides.value = [];
        }

    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load executive briefing:', error);
    } finally {
        isBriefingLoading.value = false;
    }
};

const processGuideData = (guideData: any) => {
    if (!guideData) return;

    // Try to parse if it's a JSON-string wrapped in markdown code blocks
    if (typeof guideData === 'string' && guideData.includes('```json')) {
        try {
            const jsonStr = guideData.replace(/```json\n?|\n?```/g, '');
            const parsed = JSON.parse(jsonStr);
            presentationSlides.value = parsed.slides || [];
            rawPresentationGuide.value = null;
        } catch (e) {
            console.warn('Failed to parse presentation guide JSON', e);
            rawPresentationGuide.value = guideData; // Fallback
            presentationSlides.value = [];
        }
    } else if (typeof guideData === 'object' && guideData.slides) {
        presentationSlides.value = guideData.slides;
        rawPresentationGuide.value = null;
    } else if (typeof guideData === 'string' && guideData.trim().startsWith('{')) {
        // Try to parse raw JSON string without markdown blocks
        try {
            const parsed = JSON.parse(guideData);
            presentationSlides.value = parsed.slides || [];
            rawPresentationGuide.value = null;
        } catch (e) {
            rawPresentationGuide.value = guideData;
            presentationSlides.value = [];
        }
    } else {
        // Fallback for legacy text data
        rawPresentationGuide.value = guideData;
        presentationSlides.value = [];
    }
};

const loadPresentationGuide = async () => {
    if (isGuideLoading.value) return;
    isGuideLoading.value = true;

    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/presentation-guide`, {
            params: { force_refresh: 1 },
        });

        processGuideData(response.data.guide);
        activeSlideIndex.value = 0;
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load presentation guide:', error);
    } finally {
        isGuideLoading.value = false;
    }
};

const analyzeOpeningStatement = async () => {
    if (isOpeningAnalyzing.value || !openingStatement.value.trim()) return;
    isOpeningAnalyzing.value = true;

    try {
        const response = await axios.post(`/api/projects/${props.project.id}/defense/opening-statement/analyze`, {
            opening_statement: openingStatement.value.trim(),
        });
        openingAnalysis.value = response.data.analysis ?? null;
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to analyze opening statement:', error);
    } finally {
        isOpeningAnalyzing.value = false;
    }
};

const generateOpeningStatement = async () => {
    if (isOpeningGenerating.value) return;
    isOpeningGenerating.value = true;

    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/opening-statement`, {
            params: { force_refresh: 1 },
        });
        openingStatement.value = response.data.opening_statement ?? '';
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to generate opening statement:', error);
    } finally {
        isOpeningGenerating.value = false;
    }
};

const setDeckState = (deck: { id: number; status: string; slides?: DefenseDeckSlide[]; pptx_url?: string | null; error_message?: string | null; is_wysiwyg?: boolean } | null) => {
    if (!deck) {
        deckStatus.value = 'idle';
        deckDownloadUrl.value = null;
        deckError.value = null;
        deckId.value = null;
        deckSlides.value = [];
        wysiwygSlides.value = [];
        return;
    }

    deckStatus.value = (deck.status as typeof deckStatus.value) || 'idle';
    deckDownloadUrl.value = deck.pptx_url ?? null;
    deckError.value = deck.error_message ?? null;
    deckId.value = deck.id ?? null;
    if (deck.is_wysiwyg) {
        wysiwygSlides.value = ensureWysiwygFormat(deck.slides || []);
        deckSlides.value = wysiwygToLegacy(wysiwygSlides.value);
    } else {
        deckSlides.value = normalizeDeckSlides(deck.slides);
        // Convert to WYSIWYG format for the new editor
        wysiwygSlides.value = ensureWysiwygFormat(deckSlides.value);
    }
    if (wysiwygSlides.value.length && activeDeckSlideIndex.value >= wysiwygSlides.value.length) {
        activeDeckSlideIndex.value = 0;
    }
};

const stopDeckPolling = () => {
    if (deckPollInterval.value) {
        clearInterval(deckPollInterval.value);
        deckPollInterval.value = null;
    }
};

const pollDeckStatus = () => {
    stopDeckPolling();
    deckPollInterval.value = setInterval(() => {
        refreshDefenseDeckStatus();
    }, 6000);
};

const refreshDefenseDeckStatus = async () => {
    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/deck`);
        const deck = response.data.deck ?? null;
        setDeckState(deck);

        if (deck?.status === 'ready' || deck?.status === 'outlined' || deck?.status === 'failed' || deck?.status === 'idle') {
            stopDeckPolling();
        }
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to refresh defense deck status:', error);
    }
};

const generateDefenseDeck = async (force = false) => {
    if (isDeckGenerating.value) return;
    isDeckGenerating.value = true;

    try {
        const response = await axios.post(`/api/projects/${props.project.id}/defense/deck`, {
            force_refresh: force ? 1 : 0,
        });
        setDeckState(response.data.deck ?? null);
        if (response.data.deck?.status !== 'ready') {
            pollDeckStatus();
        }
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to generate defense deck:', error);
    } finally {
        isDeckGenerating.value = false;
    }
};

const exportDefenseDeck = async () => {
    if (!deckId.value) return;
    isDeckGenerating.value = true;
    console.log('[DefenseDeck] Export requested', {
        projectId: props.project.id,
        deckId: deckId.value,
    });

    try {
        const response = await axios.post(`/api/projects/${props.project.id}/defense/deck/${deckId.value}/export`);
        console.log('[DefenseDeck] Export response', response.data);
        setDeckState(response.data.deck ?? null);
        pollDeckStatus();
    } catch (error) {
        console.error('[DefenseDeck] Export failed', error);
        if (notifyLowBalance(error)) return;
        console.error('Failed to export defense deck:', error);
    } finally {
        isDeckGenerating.value = false;
    }
};

const downloadPptx = async () => {
    // Always re-render to include latest changes, then download
    if (!deckId.value) return;
    isDeckGenerating.value = true;

    try {
        const response = await axios.post(`/api/projects/${props.project.id}/defense/deck/${deckId.value}/export`);
        setDeckState(response.data.deck ?? null);

        // Poll until ready, then download
        const pollAndDownload = () => {
            stopDeckPolling();
            deckPollInterval.value = setInterval(async () => {
                try {
                    const statusResponse = await axios.get(`/api/projects/${props.project.id}/defense/deck`);
                    const deck = statusResponse.data.deck ?? null;
                    setDeckState(deck);

                    if (deck?.status === 'ready' && deck?.pptx_url) {
                        stopDeckPolling();
                        isDeckGenerating.value = false;
                        // Auto-download
                        const link = document.createElement('a');
                        link.href = deck.pptx_url;
                        link.click();
                    } else if (deck?.status === 'failed') {
                        stopDeckPolling();
                        isDeckGenerating.value = false;
                    }
                } catch (error) {
                    console.error('Poll failed', error);
                }
            }, 3000);
        };

        pollAndDownload();
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to export defense deck:', error);
        isDeckGenerating.value = false;
    }
};
const persistDeckSlides = async (slides: DefenseDeckSlide[]) => {
    if (!deckId.value) return;
    isDeckSaving.value = true;

    try {
        const response = await axios.patch(`/api/projects/${props.project.id}/defense/deck/${deckId.value}`, {
            slides,
        });
        setDeckState(response.data.deck ?? null);
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to save defense deck slides:', error);
    } finally {
        isDeckSaving.value = false;
    }
};

const debouncedPersistDeckSlides = debounce((slides: DefenseDeckSlide[]) => {
    void persistDeckSlides(slides);
}, 800);

const handleDeckSlidesChange = (slides: DefenseDeckSlide[]) => {
    deckSlides.value = slides;
    debouncedPersistDeckSlides(slides);
};

const handleWysiwygSlidesChange = (slides: WysiwygSlide[]) => {
    wysiwygSlides.value = slides;
    // Persist WYSIWYG slides directly (server should accept this format now)
    debouncedPersistWysiwygSlides(slides);
};

const persistWysiwygSlides = async (slides: WysiwygSlide[]) => {
    if (!deckId.value) return;
    isDeckSaving.value = true;

    try {
        const response = await axios.patch(`/api/projects/${props.project.id}/defense/deck/${deckId.value}`, {
            slides,
            is_wysiwyg: true,
        });
        // Don't call setDeckState here to avoid infinite loop, just update status
        if (response.data.deck) {
            deckStatus.value = response.data.deck.status;
            deckDownloadUrl.value = response.data.deck.pptx_url ?? null;
        }
    } catch (error) {
        if (notifyLowBalance(error)) return;
        console.error('Failed to save WYSIWYG slides:', error);
    } finally {
        isDeckSaving.value = false;
    }
};

const debouncedPersistWysiwygSlides = debounce((slides: WysiwygSlide[]) => {
    void persistWysiwygSlides(slides);
}, 800);

const downloadDefenseDeck = () => {
    if (!deckDownloadUrl.value) return;
    const link = document.createElement('a');
    link.href = deckDownloadUrl.value;
    link.click();
};

const generatePredictedQuestions = async () => {
    if (isGeneratingQuestions.value) return;
    isGeneratingQuestions.value = true;

    try {
        const response = await axios.post(`/api/projects/${props.project.id}/defense/questions/generate`, {
            count: 5,
        });
        predictedQuestions.value = response.data.questions || [];
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to generate predicted questions:', error);
    } finally {
        isGeneratingQuestions.value = false;
    }
};

const loadPredictedQuestions = async () => {
    if (isLoadingQuestions.value) return;
    isLoadingQuestions.value = true;

    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/questions`, {
            params: {
                limit: 5,
                skip_generation: 1,
            },
        });

        predictedQuestions.value = response.data.questions || [];
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load predicted questions:', error);
    } finally {
        isLoadingQuestions.value = false;
    }
};

const loadPreparation = async () => {
    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/preparation`);
        const prep = response.data.preparation;
        if (prep) {
            executiveBriefing.value = prep.executive_briefing ?? null;

            // Parse existing briefing if it's JSON
            if (prep.executive_briefing) {
                if (typeof prep.executive_briefing === 'string' && prep.executive_briefing.includes('```json')) {
                    try {
                        const jsonStr = prep.executive_briefing.replace(/```json\n?|\n?```/g, '');
                        const parsed = JSON.parse(jsonStr);
                        briefingSlides.value = parsed.slides || [];
                        executiveBriefing.value = null;
                    } catch (e) {
                        // Keep as raw text
                    }
                } else if (typeof prep.executive_briefing === 'object' && prep.executive_briefing.slides) {
                    briefingSlides.value = prep.executive_briefing.slides;
                    executiveBriefing.value = null;
                } else if (typeof prep.executive_briefing === 'string' && prep.executive_briefing.trim().startsWith('{')) {
                    // Try raw JSON parse
                    try {
                        const parsed = JSON.parse(prep.executive_briefing);
                        briefingSlides.value = parsed.slides || [];
                        executiveBriefing.value = null;
                    } catch (e) {
                        // Keep as raw text
                    }
                }
            }

            // Basic load for prep data (users might need to refresh to get new JSON structure)
            if (prep.presentation_guide) {
                processGuideData(prep.presentation_guide);
            }
            openingStatement.value = prep.opening_statement ?? '';
            openingAnalysis.value = prep.opening_analysis ?? null;
            if (!prep.opening_statement) {
                await generateOpeningStatement();
            }
        }
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load preparation data:', error);
    }
};

const sessionHistory = ref<any[]>([]);
const isHistoryLoading = ref(false);

const loadSessionHistory = async () => {
    if (isHistoryLoading.value) return;
    isHistoryLoading.value = true;

    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/sessions`);
        sessionHistory.value = response.data.sessions || [];
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load session history:', error);
    } finally {
        isHistoryLoading.value = false;
    }
};

const openSession = async (sessionId: number) => {
    await loadSession(sessionId);
    isSimulating.value = true;
    currentView.value = 'simulation';
};

async function loadActiveSession() {
    try {
        const response = await axios.get(`/api/projects/${props.project.id}/defense/sessions/active`);
        if (response.data.session) {
            await openSession(response.data.session.id);
        }
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load active session:', error);
    }
}

// Animation and transition helpers
const containerClass = computed(() => {
    return isSimulating.value ? 'bg-zinc-950 text-white' : 'bg-background';
});

const displayMessages = computed<DefenseMessage[]>(() => messages.value || []);

const formattedReadinessScore = computed(() => {
    return performanceMetrics.value?.readiness_score ?? readinessScore.value;
});

const clarityScore = computed(() => performanceMetrics.value?.clarity ?? 0);
const depthScore = computed(() => performanceMetrics.value?.technical_depth ?? 0);
const coverageScore = computed(() => performanceMetrics.value?.question_coverage ?? 0);
const confidenceScore = computed(() => performanceMetrics.value?.confidence_score ?? 0);
const responseTimeScore = computed(() => performanceMetrics.value?.response_time ?? 0);

const panelistQuestionCounts = computed<Record<string, number>>(() => {
    return displayMessages.value.reduce<Record<string, number>>((acc, message) => {
        if (message.role !== 'panelist' || message.is_follow_up) return acc;
        const key = message.panelist_persona ?? 'panelist';
        acc[key] = (acc[key] || 0) + 1;
        return acc;
    }, {});
});

const totalPanelistQuestions = computed(() => {
    return Object.values(panelistQuestionCounts.value).reduce((sum, count) => sum + count, 0);
});

const plannedQuestionLimit = computed(() => {
    return totalQuestionsPerSession;
});

const panelistQuestionTarget = computed<Record<string, number>>(() => {
    const panelists = activeSimulationPersonas.value;
    if (panelists.length === 0) return {};

    const base = Math.floor(totalQuestionsPerSession / panelists.length);
    const remainder = totalQuestionsPerSession % panelists.length;
    const chairIndex = panelists.findIndex(persona => persona.id === 'generalist');

    return panelists.reduce<Record<string, number>>((acc, persona, index) => {
        const isChair = chairIndex >= 0 ? index === chairIndex : index === 0;
        acc[persona.id] = base + (isChair ? remainder : 0);
        return acc;
    }, {});
});

const questionLimitLabel = computed(() => {
    if (!session.value?.question_limit) return 'Adaptive';
    return `${totalPanelistQuestions.value}/${session.value.question_limit}`;
});

const formatMessageTime = (message: DefenseMessage) => {
    if (!message.created_at) return '';
    return new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const formatSessionDate = (dateValue: string | null) => {
    if (!dateValue) return '';
    return new Date(dateValue).toLocaleDateString();
};

onMounted(() => {
    loadPredictedQuestions();
    loadPreparation();
    loadSessionHistory();
    loadActiveSession();
    refreshDefenseDeckStatus();
});

const chatContainer = ref<HTMLElement | null>(null);

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

watch(displayMessages, () => {
    scrollToBottom();
}, { deep: true });

watch(isSimulating, (newVal) => {
    if (newVal) {
        scrollToBottom();
    }
});

onBeforeUnmount(() => {
    stopDeckPolling();
    debouncedPersistDeckSlides.cancel();
});

</script>

<template>
    <AppLayout :title="`Defense Preparation: ${project.title}`">
        <div class="min-h-screen transition-colors duration-700" :class="containerClass">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

                <!-- Immersive Header -->
                <div class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between mb-12">
                    <div class="space-y-4">
                        <div class="flex items-center gap-2">
                            <Button variant="ghost" size="sm"
                                @click="router.visit(route('projects.writing', project.slug))"
                                class="h-8 w-8 rounded-full p-0"
                                :class="isSimulating ? 'text-zinc-400 hover:text-white' : ''">
                                <ArrowLeft class="h-4 w-4" />
                            </Button>
                            <Badge variant="outline" class="gap-1 border-primary/20 bg-primary/5 text-primary">
                                <Shield class="h-3 w-3" />
                                Defense Readiness
                            </Badge>
                        </div>
                        <h1 class="text-3xl font-bold tracking-tight md:text-6xl font-display"
                            :class="isSimulating ? 'text-white' : 'text-foreground'">
                            Prepare for Victory
                        </h1>
                        <p class="max-w-2xl text-base md:text-lg text-muted-foreground"
                            :class="isSimulating ? 'text-zinc-400' : ''">
                            Master your project defense with AI-predicted questions, structured presentation guides, and
                            high-stakes simulation.
                        </p>
                        <div v-if="lowBalanceMessage"
                            class="flex items-start justify-between gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100/90">
                            <span>{{ lowBalanceMessage }}</span>
                            <Button variant="ghost" size="sm" class="h-7 px-2 text-amber-100/90 hover:text-white"
                                @click="dismissLowBalance">
                                Dismiss
                            </Button>
                        </div>
                    </div>

                    <div v-show="!isSimulating"
                        class="flex flex-row md:flex-col items-center gap-4 md:gap-3 p-4 md:p-6 rounded-3xl border border-primary/10 bg-primary/5 backdrop-blur-sm w-full md:w-auto">
                        <div class="relative flex items-center justify-center shrink-0">
                            <svg class="h-16 w-16 md:h-20 md:w-20 transform -rotate-90">
                                <circle class="text-muted/20 md:hidden" stroke-width="5" stroke="currentColor"
                                    fill="transparent" r="28" cx="32" cy="32" />
                                <circle class="text-muted/20 hidden md:block" stroke-width="6" stroke="currentColor"
                                    fill="transparent" r="34" cx="40" cy="40" />

                                <circle class="text-primary transition-all duration-1000 ease-out md:hidden"
                                    stroke-width="5" :stroke-dasharray="2 * Math.PI * 28"
                                    :stroke-dashoffset="2 * Math.PI * 28 * (1 - formattedReadinessScore / 100)"
                                    stroke-linecap="round" stroke="currentColor" fill="transparent" r="28" cx="32"
                                    cy="32" />
                                <circle class="text-primary transition-all duration-1000 ease-out hidden md:block"
                                    stroke-width="6" :stroke-dasharray="2 * Math.PI * 34"
                                    :stroke-dashoffset="2 * Math.PI * 34 * (1 - formattedReadinessScore / 100)"
                                    stroke-linecap="round" stroke="currentColor" fill="transparent" r="34" cx="40"
                                    cy="40" />
                            </svg>
                            <span class="absolute text-base md:text-xl font-bold">{{ formattedReadinessScore }}%</span>
                        </div>
                        <span
                            class="text-[10px] md:text-xs font-semibold uppercase tracking-wider text-muted-foreground text-center">Readiness
                            Score</span>
                    </div>
                </div>

                <!-- Main View Toggle -->
                <div v-show="!isSimulating"
                    class="flex p-1 rounded-2xl bg-muted/50 border border-border/50 mb-8 w-full sm:w-auto sm:inline-flex">
                    <button @click="currentView = 'preparation'"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 md:px-6 py-2 rounded-xl text-sm font-medium transition-all"
                        :class="currentView === 'preparation' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'">
                        <Zap class="h-4 w-4" />
                        <span class="hidden sm:inline">Preparation Suite</span>
                        <span class="sm:hidden">Prep</span>
                    </button>
                    <button @click="currentView = 'simulation'"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 md:px-6 py-2 rounded-xl text-sm font-medium transition-all"
                        :class="currentView === 'simulation' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground'">
                        <Users class="h-4 w-4" />
                        <span class="hidden sm:inline">Simulation Lab</span>
                        <span class="sm:hidden">Sim</span>
                    </button>
                </div>

                <!-- PREPARATION SUITE -->
                <div v-if="currentView === 'preparation' && !isSimulating"
                    class="grid gap-8 lg:grid-cols-12 animate-in fade-in slide-in-from-bottom-4 duration-700">

                    <!-- Left Column: Core Prep -->
                    <div class="lg:col-span-8 space-y-8 transition-all duration-500">
                        <template v-if="!isDeckSwapped">
                            <!-- AI Executive Summary -->
                            <Card
                                class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <CardHeader class="pb-2">
                                    <div
                                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                                        <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                            <Sparkles class="h-5 w-5 text-indigo-500" />
                                            Executive Briefing
                                        </CardTitle>
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" class="text-xs gap-1.5 h-8 w-fit"
                                                @click="refreshExecutiveBriefing" :disabled="isBriefingLoading">
                                                <History class="h-3.5 w-3.5" />
                                                {{ isBriefingLoading ? 'Loading...' : 'Refresh AI' }}
                                            </Button>
                                            <Button variant="ghost" size="icon"
                                                class="h-8 w-8 text-zinc-500 hover:text-indigo-400"
                                                @click="toggleDeckSwap" title="Swap position">
                                                <ArrowUpDown class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <CardDescription class="text-indigo-600/70 dark:text-indigo-400/70 text-sm">Your
                                        project's core value proposition, synthesized for your defense.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[400px]">
                                    <div v-if="briefingSlides.length > 0" class="h-full">
                                        <ExecutiveBriefingDeck :slides="briefingSlides" :is-loading="isBriefingLoading"
                                            @refresh="refreshExecutiveBriefing" />
                                    </div>
                                    <div v-else-if="executiveBriefing" class="relative z-10 space-y-4">
                                        <div
                                            class="absolute -left-6 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-500/80 via-purple-500/80 to-transparent rounded-full opacity-60">
                                        </div>
                                        <RichTextViewer :content="executiveBriefing" :show-font-controls="false"
                                            viewer-class="prose-sm md:prose-base dark:prose-invert leading-relaxed"
                                            class="!bg-transparent" />
                                    </div>
                                    <div v-else
                                        class="flex flex-col items-center justify-center py-12 text-center space-y-4">
                                        <div
                                            class="h-12 w-12 rounded-full bg-indigo-500/10 flex items-center justify-center">
                                            <Sparkles class="h-6 w-6 text-indigo-500/50" />
                                        </div>
                                        <p class="text-base text-muted-foreground italic max-w-xs">
                                            No executive briefing yet. Click “Refresh AI” to generate a comprehensive
                                            summary of your research.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                            <!-- Predicted Questions -->
                            <div class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500 delay-75">
                                <Card
                                    class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50">
                                    <CardHeader class="pb-2">
                                        <div
                                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                                            <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                                <Target class="h-5 w-5 text-rose-500" />
                                                Predicted Defense Questions
                                            </CardTitle>
                                            <div class="flex items-center gap-2">
                                                <Badge variant="secondary" class="font-mono text-[10px]">{{
                                                    predictedQuestions.length }}
                                                    TOP QUESTIONS</Badge>
                                                <Button variant="ghost" size="sm" class="text-xs h-8"
                                                    @click="generatePredictedQuestions"
                                                    :disabled="isGeneratingQuestions">
                                                    <Sparkles class="h-3.5 w-3.5" />
                                                    {{ isGeneratingQuestions ? 'Generating...' : 'Generate' }}
                                                </Button>
                                                <Button variant="ghost" size="icon"
                                                    class="h-8 w-8 text-zinc-500 hover:text-rose-400"
                                                    @click="toggleDeckSwap" title="Swap position">
                                                    <ArrowUpDown class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <CardDescription class="text-rose-600/70 dark:text-rose-400/70 text-sm">
                                            Identify and prepare for high-probability questions examiners might ask.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[450px]">
                                        <PredictedQuestionsDeck :questions="predictedQuestions"
                                            :is-loading="isGeneratingQuestions" />
                                    </CardContent>
                                </Card>
                            </div>
                        </template>
                        <template v-else>
                            <!-- Predicted Questions First -->
                            <div class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                <Card
                                    class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50">
                                    <CardHeader class="pb-2">
                                        <div
                                            class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                                            <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                                <Target class="h-5 w-5 text-rose-500" />
                                                Predicted Defense Questions
                                            </CardTitle>
                                            <div class="flex items-center gap-2">
                                                <Badge variant="secondary" class="font-mono text-[10px]">{{
                                                    predictedQuestions.length }}
                                                    TOP QUESTIONS</Badge>
                                                <Button variant="ghost" size="sm" class="text-xs h-8"
                                                    @click="generatePredictedQuestions"
                                                    :disabled="isGeneratingQuestions">
                                                    <Sparkles class="h-3.5 w-3.5" />
                                                    {{ isGeneratingQuestions ? 'Generating...' : 'Generate' }}
                                                </Button>
                                                <Button variant="ghost" size="icon"
                                                    class="h-8 w-8 text-zinc-500 hover:text-rose-400"
                                                    @click="toggleDeckSwap" title="Swap position">
                                                    <ArrowUpDown class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                        <CardDescription class="text-rose-600/70 dark:text-rose-400/70 text-sm">
                                            Identify and prepare for high-probability questions examiners might ask.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[450px]">
                                        <PredictedQuestionsDeck :questions="predictedQuestions"
                                            :is-loading="isGeneratingQuestions" />
                                    </CardContent>
                                </Card>
                            </div>
                            <!-- Executive Briefing Second -->
                            <Card
                                class="overflow-hidden border-none bg-zinc-900/30 shadow-none ring-1 ring-border/50 animate-in fade-in slide-in-from-bottom-4 duration-500 delay-75">
                                <CardHeader class="pb-2">
                                    <div
                                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 sm:gap-0">
                                        <CardTitle class="flex items-center gap-2 text-xl md:text-2xl font-display">
                                            <Sparkles class="h-5 w-5 text-indigo-500" />
                                            Executive Briefing
                                        </CardTitle>
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" class="text-xs gap-1.5 h-8 w-fit"
                                                @click="refreshExecutiveBriefing" :disabled="isBriefingLoading">
                                                <History class="h-3.5 w-3.5" />
                                                {{ isBriefingLoading ? 'Loading...' : 'Refresh AI' }}
                                            </Button>
                                            <Button variant="ghost" size="icon"
                                                class="h-8 w-8 text-zinc-500 hover:text-indigo-400"
                                                @click="toggleDeckSwap" title="Swap position">
                                                <ArrowUpDown class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                    <CardDescription class="text-indigo-600/70 dark:text-indigo-400/70 text-sm">Your
                                        project's core value proposition, synthesized for your defense.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent class="relative pb-6 md:pb-10 px-6 md:px-10 min-h-[400px]">
                                    <div v-if="briefingSlides.length > 0" class="h-full">
                                        <ExecutiveBriefingDeck :slides="briefingSlides" :is-loading="isBriefingLoading"
                                            @refresh="refreshExecutiveBriefing" />
                                    </div>
                                    <div v-else-if="executiveBriefing" class="relative z-10 space-y-4">
                                        <div
                                            class="absolute -left-6 top-0 bottom-0 w-1 bg-gradient-to-b from-indigo-500/80 via-purple-500/80 to-transparent rounded-full opacity-60">
                                        </div>
                                        <RichTextViewer :content="executiveBriefing" :show-font-controls="false"
                                            viewer-class="prose-sm md:prose-base dark:prose-invert leading-relaxed"
                                            class="!bg-transparent" />
                                    </div>
                                    <div v-else
                                        class="flex flex-col items-center justify-center py-12 text-center space-y-4">
                                        <div
                                            class="h-12 w-12 rounded-full bg-indigo-500/10 flex items-center justify-center">
                                            <Sparkles class="h-6 w-6 text-indigo-500/50" />
                                        </div>
                                        <p class="text-base text-muted-foreground italic max-w-xs">
                                            No executive briefing yet. Click “Refresh AI” to generate a comprehensive
                                            summary of your research.
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </template>

                        <!-- Quick Pitch Laboratory -->
                        <Card class="border-border/50 shadow-sm rounded-3xl overflow-hidden group">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Mic class="h-4 w-4 text-emerald-500" />
                                    Opening Statement
                                </CardTitle>
                                <CardDescription>Your 60-second "hook" to impress the panel.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="relative">
                                    <textarea v-model="openingStatement" rows="4"
                                        class="w-full rounded-2xl border-border/50 bg-muted/30 p-4 text-sm focus:ring-primary/20 transition-all"
                                        placeholder="Type your opening statement here..."></textarea>
                                    <div class="absolute bottom-3 right-3 flex items-center gap-2">
                                        <Button variant="outline" size="icon" class="h-8 w-8 rounded-full">
                                            <Mic class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <Button variant="outline" class="w-full text-xs font-bold gap-2 rounded-xl"
                                        @click="generateOpeningStatement" :disabled="isOpeningGenerating">
                                        <Sparkles class="h-3.5 w-3.5" />
                                        {{ isOpeningGenerating ? 'Generating...' : 'GENERATE OPENING' }}
                                    </Button>
                                    <Button variant="secondary" class="w-full text-xs font-bold gap-2 rounded-xl"
                                        @click="analyzeOpeningStatement" :disabled="isOpeningAnalyzing">
                                        <Brain class="h-3.5 w-3.5" />
                                        {{ isOpeningAnalyzing ? 'Analyzing...' : 'ANALYZE PITCH FLOW' }}
                                    </Button>
                                </div>
                                <div v-if="openingAnalysis"
                                    class="text-sm text-muted-foreground border-t border-emerald-500/10 pt-4 mt-2">
                                    <RichTextViewer :content="openingAnalysis" :show-font-controls="false"
                                        class="!bg-transparent" viewer-class="prose-xs md:prose-sm" />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Right Column: Tools -->
                    <div class="lg:col-span-4 space-y-8">
                        <!-- Presentation Hub -->
                        <!-- Presentation Hub -->
                        <Card
                            class="border-border/50 shadow-2xl shadow-indigo-500/10 rounded-3xl overflow-hidden bg-zinc-900/20">
                            <CardHeader
                                class="relative overflow-hidden bg-gradient-to-br from-indigo-950 via-zinc-900 to-black text-white pb-6 border-b border-white/5">
                                <!-- Abstract Glows -->
                                <div class="absolute -right-6 -top-6 h-32 w-32 bg-indigo-500/10 rounded-full blur-3xl">
                                </div>
                                <div
                                    class="absolute -left-10 -bottom-10 h-40 w-40 bg-purple-500/10 rounded-full blur-3xl">
                                </div>

                                <div class="relative z-10 flex items-center gap-3 mb-2">
                                    <div
                                        class="p-2 bg-indigo-500/10 rounded-xl backdrop-blur-md border border-indigo-500/20">
                                        <Presentation class="h-5 w-5 text-indigo-400" />
                                    </div>
                                    <CardTitle
                                        class="text-xl md:text-2xl font-display font-bold tracking-tight bg-gradient-to-r from-white to-zinc-400 bg-clip-text text-transparent">
                                        Presentation Guide</CardTitle>
                                </div>
                                <div
                                    class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <CardDescription class="text-zinc-400 text-xs leading-relaxed max-w-[220px]">
                                        Step-by-step structure for high-impact defense slides.
                                    </CardDescription>
                                    <div class="flex flex-col items-end gap-1.5">
                                        <template v-if="deckSlides.length">
                                            <Button variant="outline" size="sm"
                                                class="h-7 text-[10px] gap-1.5 border-white/5 bg-white/5 hover:bg-white/10 text-zinc-400"
                                                @click="generateDefenseDeck" :disabled="isDeckGenerating">
                                                <RotateCcw class="h-3 w-3" />
                                                Regenerate
                                            </Button>
                                            <div class="flex items-center gap-1.5">
                                                <Button variant="secondary" size="sm"
                                                    class="h-7 text-[10px] gap-1.5 px-3 font-bold"
                                                    @click="downloadPptx" :disabled="isDeckGenerating">
                                                    <Download class="h-3 w-3" />
                                                    {{ isDeckGenerating ? 'Generating...' : 'Download PPTX' }}
                                                </Button>
                                                <Button variant="ghost" size="icon"
                                                    class="h-7 w-7 text-zinc-500 hover:text-white"
                                                    @click="isGuideExpanded = true">
                                                    <Maximize2 class="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </template>
                                        <Button v-else variant="secondary" size="sm"
                                            class="h-8 text-xs gap-1.5 font-bold" @click="generateDefenseDeck"
                                            :disabled="isDeckGenerating">
                                            <Sparkles class="h-3.5 w-3.5" />
                                            {{ isDeckGenerating ? 'Generating...' : 'Generate Guide' }}
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="p-4 md:p-6 h-[500px] flex flex-col">
                                <div v-if="deckStatus !== 'idle'" class="mb-3 text-[11px] text-zinc-400">
                                    <span v-if="deckStatus === 'queued'">Deck queued.</span>
                                    <span v-else-if="deckStatus === 'outlining'">Generating slides with GPT-4o...</span>
                                    <span v-else-if="deckStatus === 'extracting'">Extracting chapter data...</span>
                                    <span v-else-if="deckStatus === 'extracted'">Extraction complete. Preparing
                                        slides...</span>
                                    <span v-else-if="deckStatus === 'generating'">Generating slides with
                                        GPT-4o...</span>
                                    <span v-else-if="deckStatus === 'outlined'">Slides ready for review.</span>
                                    <span v-else-if="deckStatus === 'rendering'">Rendering PPTX...</span>
                                    <span v-else-if="deckStatus === 'ready'">PPTX ready for download.</span>
                                    <span v-else-if="deckStatus === 'failed'">Failed to generate deck.</span>
                                </div>
                                <div v-if="deckStatus === 'failed' && deckError" class="mb-3 text-[11px] text-rose-400">
                                    {{ deckError }}
                                </div>
                                <DeckViewer :project="project" :slides="deckSlides"
                                    v-model:active-index="activeDeckSlideIndex" :is-saving="isDeckSaving" compact
                                    :show-pptx="!!deckId" :pptx-url="deckDownloadUrl" :pptx-busy="isDeckGenerating"
                                    @update:slides="handleDeckSlidesChange" @toggle-expand="isGuideExpanded = true"
                                    @download-pptx="downloadPptx" @export-pptx="downloadPptx" />
                            </CardContent>
                        </Card>

                        <!-- Expanded WYSIWYG Editor Dialog -->
                        <Dialog v-model:open="isGuideExpanded">
                            <DialogContent
                                class="max-w-[98vw] w-full lg:max-w-[98vw] h-[95vh] flex flex-col p-0 bg-zinc-100 dark:bg-zinc-950 border-white/10 shadow-2xl overflow-hidden rounded-3xl">
                                <div v-if="isProduction" class="flex-1 flex items-center justify-center">
                                    <div class="max-w-2xl text-center px-6">
                                        <div
                                            class="mx-auto mb-4 inline-flex items-center rounded-full border border-zinc-200/70 bg-white px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:border-white/10 dark:bg-white/5 dark:text-white/70">
                                            Coming Soon
                                        </div>
                                        <h3 class="text-2xl md:text-3xl font-display font-bold text-zinc-900 dark:text-white">
                                            Defense Slide Deck Editor
                                        </h3>
                                        <p class="mt-3 text-sm text-zinc-600 dark:text-white/70">
                                            We are polishing the slide deck editor before release. Once ready, you will
                                            be able to edit layouts, drag-and-drop elements, theme slides, and export
                                            a presentation-ready deck in minutes.
                                        </p>
                                    </div>
                                </div>
                                <WysiwygSlideEditor
                                    v-else
                                    :slides="wysiwygSlides"
                                    :active-index="activeDeckSlideIndex"
                                    :is-saving="isDeckSaving"
                                    :project-title="project.title"
                                    @update:slides="handleWysiwygSlidesChange"
                                    @update:active-index="activeDeckSlideIndex = $event"
                                    @export="downloadPptx"
                                />
                            </DialogContent>
                        </Dialog>

                        <!-- Session History -->
                        <Card class="border-border/50 shadow-sm rounded-3xl overflow-hidden">
                            <CardHeader class="pb-2">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <History class="h-4 w-4 text-amber-500" />
                                    Session History
                                </CardTitle>
                                <CardDescription>Your past defense simulations for this project.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div v-if="isHistoryLoading" class="text-xs text-muted-foreground">
                                    Loading sessions...
                                </div>
                                <div v-else-if="!sessionHistory.length" class="text-xs text-muted-foreground">
                                    No sessions yet. Start a simulation to create your first one.
                                </div>
                                <div v-else class="space-y-3">
                                    <div v-for="sessionItem in sessionHistory" :key="sessionItem.id"
                                        class="flex items-center justify-between gap-3 rounded-2xl border border-border/50 p-3">
                                        <div class="space-y-1">
                                            <div class="text-xs font-semibold text-foreground">
                                                {{ formatSessionDate(sessionItem.started_at || sessionItem.created_at)
                                                }}
                                            </div>
                                            <div class="text-[11px] text-muted-foreground">
                                                {{ sessionItem.status }} • {{ sessionItem.questions_asked }} Qs
                                            </div>
                                        </div>
                                        <Button size="sm" variant="outline" @click="openSession(sessionItem.id)">
                                            {{ sessionItem.status === 'completed' ? 'Review' : 'Resume' }}
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                    </div>
                </div>

                <!-- SIMULATION LAB (INACTIVE) -->
                <div v-if="currentView === 'simulation' && !isSimulating"
                    class="flex flex-col items-center justify-center py-20 animate-in zoom-in-95 duration-700">
                    <div class="relative mb-8">
                        <div
                            class="absolute -inset-4 bg-gradient-to-r from-primary to-purple-500 rounded-full opacity-20 blur-2xl animate-pulse">
                        </div>
                        <Users class="h-24 w-24 text-primary relative z-10" />
                    </div>
                    <h2 class="text-3xl font-bold mb-4">The Mock Defense</h2>
                    <p class="max-w-md text-center text-muted-foreground mb-12">
                        Face a panel of AI examiners who will challenge your research from different perspectives.
                        Record your session for post-defense feedback.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-5xl mb-12">
                        <Card class="border-border/50 bg-card/50 backdrop-blur-sm rounded-3xl overflow-hidden">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base flex items-center gap-2">
                                    <Users class="h-4 w-4 text-primary" />
                                    How the simulation works
                                </CardTitle>
                                <CardDescription>Chair-moderated rotation with real-time feedback.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3 text-sm text-muted-foreground">
                                <p>1) The chair opens, then panelists rotate with targeted questions.</p>
                                <p>2) You respond; the system scores clarity and technical depth.</p>
                                <p>3) Hints guide you before answers are revealed.</p>
                                <p>4) When the question limit is reached, the session auto-completes and scores.</p>
                                <p class="text-xs text-muted-foreground/80">Total questions: {{ plannedQuestionLimit }}.
                                    Each question is worth 10 marks (100 total).</p>
                            </CardContent>
                        </Card>
                        <Card class="border-border/50 bg-card/50 backdrop-blur-sm rounded-3xl overflow-hidden">
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base flex items-center gap-2">
                                    <Target class="h-4 w-4 text-emerald-500" />
                                    Scoring breakdown
                                </CardTitle>
                                <CardDescription>Readiness is a weighted summary of core metrics.</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-3 text-sm text-muted-foreground">
                                <div class="flex items-center justify-between">
                                    <span>Per-question mark</span>
                                    <span class="text-foreground font-semibold">10 marks total</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Derived from</span>
                                    <span class="text-foreground font-semibold">Clarity + Depth</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Coverage, confidence, and response time</span>
                                    <span class="text-foreground font-semibold">Shown as diagnostics</span>
                                </div>
                                <div class="text-xs text-muted-foreground/80">
                                    10 questions x 10 marks each = 100 total marks. Readiness uses clarity/depth
                                    averages.
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-4xl mb-12">
                        <Card v-for="persona in activeSimulationPersonas" :key="persona.id"
                            class="border-border/50 bg-card/50 backdrop-blur-sm rounded-3xl overflow-hidden hover:border-primary/30 transition-all">
                            <CardContent class="p-8 text-center space-y-3">
                                <div class="text-4xl mb-4">{{ persona.avatar }}</div>
                                <h4 class="font-bold">{{ persona.name }}</h4>
                                <Badge variant="secondary" class="text-[10px]">{{ persona.role }}</Badge>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    {{ persona.name === 'The Skeptic' ? 'High pressure, doubts your findings.' :
                                        persona.name === 'The Methodologist' ? 'Strict on research design and ethics.' :
                                            'Focuses on the bigger picture.' }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Button size="lg" @click="startSimulation" :disabled="isStarting"
                        class="px-12 py-8 text-xl font-bold rounded-3xl shadow-2xl shadow-primary/20 gap-4 hover:scale-105 transition-all">
                        <Play class="h-6 w-6 fill-current" />
                        START SIMULATION
                    </Button>
                </div>

                <!-- ACTIVE SIMULATION (IMMERSIVE MODE) -->
                <div v-if="isSimulating"
                    class="fixed inset-0 z-50 bg-zinc-950 flex flex-col items-center p-4 md:p-8 animate-in fade-in duration-500">
                    <!-- Simulation HUD -->
                    <div class="w-full max-w-6xl flex items-center justify-between mb-8">
                        <div class="flex items-center gap-4">
                            <div class="h-3 w-3 rounded-full bg-rose-600 animate-pulse"></div>
                            <span class="text-xs font-bold tracking-[0.2em] text-zinc-500 uppercase">Live
                                Simulation</span>
                            <div
                                class="flex items-center gap-2 bg-zinc-900 px-4 py-2 rounded-full border border-zinc-800">
                                <History class="h-3.5 w-3.5 text-zinc-400" />
                                <span class="text-sm font-mono text-zinc-300">14:22</span>
                            </div>
                            <div
                                class="flex items-center gap-2 bg-zinc-900 px-4 py-2 rounded-full border border-zinc-800">
                                <Target class="h-3.5 w-3.5 text-zinc-400" />
                                <span class="text-xs text-zinc-300">Questions {{ questionLimitLabel }}</span>
                            </div>
                        </div>
                        <Button variant="ghost" @click="stopSimulation"
                            class="rounded-full text-zinc-400 hover:text-white hover:bg-zinc-900 gap-2">
                            Finish & Analysis
                            <ArrowLeft class="h-4 w-4 rotate-180" />
                        </Button>
                    </div>

                    <div class="grid lg:grid-cols-12 gap-8 w-full max-w-6xl flex-grow overflow-hidden">

                        <!-- Panel Visualization -->
                        <div class="lg:col-span-4 space-y-4 md:space-y-6 overflow-y-auto pr-2 custom-scrollbar">
                            <h3 class="text-[10px] md:text-sm font-bold text-zinc-500 uppercase tracking-widest px-2">
                                The Panel</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-4 md:gap-6">
                                <Card v-for="persona in activeSimulationPersonas" :key="persona.id"
                                    class="bg-zinc-900/50 border-zinc-800 hover:border-zinc-700 transition-all rounded-3xl">
                                    <CardContent class="p-4 md:p-6">
                                        <div class="flex items-center gap-3 md:gap-4">
                                            <div
                                                class="h-10 w-10 md:h-12 md:w-12 rounded-2xl bg-zinc-800 flex items-center justify-center text-xl md:text-2xl">
                                                {{ persona.avatar }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span
                                                        class="font-bold text-zinc-200 text-sm md:text-base truncate">{{
                                                            persona.name }}</span>
                                                    <Badge
                                                        v-if="isFetchingQuestion && (!thinkingPersonaId || thinkingPersonaId === persona.id)"
                                                        class="bg-amber-500/10 text-amber-500 border-none text-[8px] h-4 shrink-0">
                                                        THINKING</Badge>
                                                </div>
                                                <span class="text-[10px] md:text-xs text-zinc-500 truncate">{{
                                                    persona.role }}</span>
                                                <div class="text-[10px] md:text-xs text-zinc-600">
                                                    Questions asked: {{ panelistQuestionCounts[persona.id] ?? 0 }} /
                                                    {{ panelistQuestionTarget[persona.id] ?? 0 }}
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- Performance HUD -->
                            <Card
                                class="bg-zinc-900 border-none rounded-3xl p-4 md:p-6 mt-6 md:mt-12 bg-gradient-to-br from-zinc-900 to-black">
                                <h4 class="text-[10px] md:text-xs font-bold text-zinc-500 mb-4 uppercase">Live
                                    Performance</h4>
                                <div class="grid grid-cols-3 lg:grid-cols-1 gap-4 md:gap-6">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-[10px] md:text-xs">
                                            <span class="text-zinc-400">Clarity</span>
                                            <span class="text-zinc-200">{{ clarityScore }}%</span>
                                        </div>
                                        <Progress :model-value="clarityScore" class="h-1 bg-zinc-800"
                                            indicator-class="bg-blue-500" />
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-[10px] md:text-xs">
                                            <span class="text-zinc-400">Depth</span>
                                            <span class="text-zinc-200">{{ depthScore }}%</span>
                                        </div>
                                        <Progress :model-value="depthScore" class="h-1 bg-zinc-800"
                                            indicator-class="bg-emerald-500" />
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between text-[10px] md:text-xs">
                                            <span class="text-zinc-400">Confidence</span>
                                            <span class="text-zinc-200">{{ performanceMetrics?.confidence_score ?? 0
                                            }}%</span>
                                        </div>
                                        <Progress :model-value="performanceMetrics?.confidence_score ?? 0"
                                            class="h-1 bg-zinc-800" indicator-class="bg-rose-500" />
                                    </div>
                                </div>
                            </Card>
                        </div>

                        <!-- Chat Interaction Area -->
                        <div
                            class="lg:col-span-8 flex flex-col bg-zinc-900/40 rounded-[2.5rem] border border-zinc-800/50 backdrop-blur-xl overflow-hidden">
                            <div ref="chatContainer" class="flex-grow p-8 overflow-y-auto custom-scrollbar">
                                <div class="space-y-10 max-w-3xl mx-auto py-8">
                                    <div v-for="(msg, i) in displayMessages" :key="i"
                                        class="group animate-in fade-in slide-in-from-bottom-2 duration-500"
                                        :class="msg.role === 'student' ? 'flex flex-col items-end' : 'flex flex-col items-start'">
                                        <div class="flex items-center gap-2 mb-2 px-1">
                                            <span class="text-[10px] font-bold text-zinc-500 uppercase tracking-widest">
                                                {{ msg.role === 'panelist' ? (personaLookup[msg.panelist_persona || '']
                                                    || 'Panelist') : 'Candidate' }}
                                            </span>
                                            <span class="text-[8px] text-zinc-600">{{ formatMessageTime(msg) }}</span>
                                        </div>
                                        <div class="p-5 rounded-3xl text-sm leading-relaxed max-w-[85%]"
                                            :class="msg.role === 'student'
                                                ? 'bg-zinc-100 text-zinc-950 rounded-tr-none'
                                                : 'bg-zinc-800/80 text-zinc-200 border border-zinc-700/50 rounded-tl-none'">
                                            {{ msg.content }}
                                        </div>
                                        <div v-if="msg.role === 'panelist'" class="mt-3 flex gap-4 px-2">
                                            <button
                                                class="text-[10px] font-bold text-zinc-600 hover:text-zinc-400 uppercase tracking-tighter flex items-center gap-1">
                                                <Target class="h-3 w-3" />
                                                View Strategy
                                            </button>
                                            <button
                                                class="text-[10px] font-bold text-zinc-600 hover:text-zinc-400 uppercase tracking-tighter flex items-center gap-1">
                                                <Brain class="h-3 w-3" />
                                                Analyze Critique
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- User Input Area (Simulation) -->
                            <div class="p-4 md:p-8 border-t border-zinc-800/50 bg-black/20">
                                <div class="relative max-w-3xl mx-auto">
                                    <textarea v-model="responseInput" placeholder="Speak your defense answer..."
                                        class="w-full bg-zinc-950 border-zinc-800 rounded-2xl py-4 md:py-6 px-4 md:px-6 pr-20 md:pr-24 text-zinc-100 focus:ring-primary/40 focus:border-primary/40 placeholder-zinc-700 transition-all resize-none shadow-2xl text-sm md:text-base"
                                        :disabled="isSending" rows="2"></textarea>
                                    <div
                                        class="absolute right-3 md:right-4 top-1/2 -translate-y-1/2 flex items-center gap-2">
                                        <div
                                            class="hidden sm:flex gap-1 h-8 px-2 items-center bg-zinc-900 rounded-full border border-zinc-800">
                                            <div class="h-2 w-0.5 bg-rose-500 animate-pulse"></div>
                                            <div class="h-4 w-0.5 bg-rose-500 animate-pulse delay-75"></div>
                                            <div class="h-2 w-0.5 bg-rose-500 animate-pulse delay-150"></div>
                                        </div>
                                        <Button size="icon" @click="submitResponse" :disabled="isSending"
                                            class="h-9 w-9 md:h-10 md:w-10 rounded-full bg-primary hover:bg-primary/90 text-white shadow-lg shadow-primary/20">
                                            <ArrowLeft class="h-4 w-4 md:h-5 md:w-5 rotate-180" />
                                        </Button>
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-center gap-6">
                                    <button
                                        class="flex items-center gap-2 text-[10px] font-bold text-zinc-500 hover:text-white transition-colors uppercase tracking-widest">
                                        <Mic class="h-3.5 w-3.5" />
                                        Hold Space to Speak
                                    </button>
                                    <button
                                        class="flex items-center gap-2 text-[10px] font-bold text-zinc-500 hover:text-white transition-colors uppercase tracking-widest"
                                        @click="requestHint">
                                        <History class="h-3.5 w-3.5" />
                                        Request Hint
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #27272a;
    border-radius: 10px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #3f3f46;
}

@keyframes progress {
    0% {
        transform: translateX(-100%);
    }

    50% {
        transform: translateX(0);
    }

    100% {
        transform: translateX(100%);
    }
}

.animate-progress {
    animation: progress 2s infinite linear;
}

/* Glassmorphism utility */
.glass {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.05);
}
</style>
