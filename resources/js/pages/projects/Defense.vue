<!-- resources/js/pages/projects/Defense.vue -->
<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { debounce } from 'lodash-es';
import AppLayout from '@/layouts/AppLayout.vue';
import DefenseSimulationHud from '@/components/defense/DefenseSimulationHud.vue';
import DefensePanelVisualization from '@/components/defense/DefensePanelVisualization.vue';
import DefenseChatInterface from '@/components/defense/DefenseChatInterface.vue';
import DefensePrepLeftColumn from '@/components/defense/DefensePrepLeftColumn.vue';
import DefensePrepRightColumn from '@/components/defense/DefensePrepRightColumn.vue';
import DefenseSimulationLabIntro from '@/components/defense/DefenseSimulationLabIntro.vue';
import DefenseHeader from '@/components/defense/DefenseHeader.vue';
import DefenseViewToggle from '@/components/defense/DefenseViewToggle.vue';
import DefenseOnboardingDialog from '@/components/defense/DefenseOnboardingDialog.vue';
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

interface SimulationPersona {
    id: string;
    name: string;
    role: string;
    avatar?: string;
}

interface DefenseConfig {
    default_question_limit: number;
    default_difficulty: 'undergraduate' | 'masters' | 'doctoral';
    personas: SimulationPersona[];
}

const props = defineProps<{
    project: Project;
    defenseConfig?: DefenseConfig;
}>();

const defenseConfig = computed(() => ({
    default_question_limit: props.defenseConfig?.default_question_limit ?? 10,
    default_difficulty: props.defenseConfig?.default_difficulty ?? 'undergraduate',
    personas: props.defenseConfig?.personas ?? [],
}));

const currentView = ref<'preparation' | 'simulation'>('preparation');
const isSimulating = ref(false);
const simulationElapsedSeconds = ref(0);
const simulationTimer = ref<ReturnType<typeof setInterval> | null>(null);
const localSimulationStartMs = ref<number | null>(null);
const readinessScore = ref<number | null>(null);
const overallReadinessScore = ref<number | null>(null);
const lowBalanceMessage = ref<string | null>(null);
const isAutoEnding = ref(false);
const totalQuestionsPerSession = computed(() => defenseConfig.value.default_question_limit);
const personaAvatars: Record<string, string> = {
    skeptic: '🧐',
    methodologist: '🧪',
    generalist: '🌍',
    theorist: '📚',
    practitioner: '🧭',
};
const activeSimulationPersonas = computed(() => {
    return defenseConfig.value.personas.map(persona => ({
        ...persona,
        avatar: persona.avatar ?? personaAvatars[persona.id] ?? '•',
    }));
});
const onboardingKey = 'defense:onboarding:v1';
const showOnboarding = ref(false);
const onboardingStep = ref(0);
const onboardingSteps = [
    {
        title: 'Scan your prep materials',
        description: 'Review the executive briefing and predicted questions before you start. The goal is clarity, not memorization.',
    },
    {
        title: 'Draft a strong opening',
        description: 'Write a concise opening statement and refine it with analysis so you hit the right tone immediately.',
    },
    {
        title: 'Run the mock defense',
        description: 'Start the simulation, answer questions, and request hints when you need momentum.',
    },
];

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
            difficulty_level: defenseConfig.value.default_difficulty,
            question_limit: totalQuestionsPerSession.value,
        });

        localSimulationStartMs.value = session.value?.started_at ? null : Date.now();
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
        localSimulationStartMs.value = null;
        simulationElapsedSeconds.value = 0;
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
        const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message;
        if (message && message.toLowerCase().includes('time limit')) {
            await stopSimulation(true);
            return;
        }
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
        if (message && message.toLowerCase().includes('time limit')) {
            await stopSimulation(true);
            return;
        }
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
        overallReadinessScore.value = response.data.overall_readiness_score ?? null;
    } catch (error) {
        notifyLowBalance(error);
        console.error('Failed to load session history:', error);
    } finally {
        isHistoryLoading.value = false;
    }
};

const openSession = async (sessionId: number) => {
    await loadSession(sessionId);
    localSimulationStartMs.value = session.value?.started_at ? null : Date.now();
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
    return performanceMetrics.value?.readiness_score ?? readinessScore.value ?? null;
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
    return totalQuestionsPerSession.value;
});

const panelistQuestionTarget = computed<Record<string, number>>(() => {
    const panelists = activeSimulationPersonas.value;
    if (panelists.length === 0) return {};

    const base = Math.floor(totalQuestionsPerSession.value / panelists.length);
    const remainder = totalQuestionsPerSession.value % panelists.length;
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

const updateSimulationElapsed = () => {
    const startedAt = session.value?.started_at ? Date.parse(session.value.started_at) : null;
    const startMs = Number.isNaN(startedAt) ? null : startedAt;
    const effectiveStartMs = startMs ?? localSimulationStartMs.value;
    if (!effectiveStartMs) {
        simulationElapsedSeconds.value = 0;
        return;
    }
    simulationElapsedSeconds.value = Math.max(0, Math.floor((Date.now() - effectiveStartMs) / 1000));
};

const startSimulationTimer = () => {
    if (simulationTimer.value) return;
    updateSimulationElapsed();
    simulationTimer.value = setInterval(updateSimulationElapsed, 1000);
};

const stopSimulationTimer = () => {
    if (!simulationTimer.value) return;
    clearInterval(simulationTimer.value);
    simulationTimer.value = null;
};

const formattedSimulationTime = computed(() => {
    const totalSeconds = simulationElapsedSeconds.value;
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const remainingSimulationSeconds = computed(() => {
    const limitMinutes = session.value?.time_limit_minutes;
    if (!limitMinutes) return null;
    const totalLimitSeconds = limitMinutes * 60;
    return Math.max(0, totalLimitSeconds - simulationElapsedSeconds.value);
});

const formattedSimulationCountdown = computed(() => {
    const remaining = remainingSimulationSeconds.value;
    if (remaining === null) return formattedSimulationTime.value;
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const simulationTimeLabel = computed(() => {
    return remainingSimulationSeconds.value === null ? 'Elapsed' : 'Time left';
});

const showElapsedTime = computed(() => remainingSimulationSeconds.value !== null);

onMounted(() => {
    loadPredictedQuestions();
    loadPreparation();
    loadSessionHistory();
    loadActiveSession();
    refreshDefenseDeckStatus();

    if (typeof window !== 'undefined' && !localStorage.getItem(onboardingKey)) {
        onboardingStep.value = 0;
        showOnboarding.value = true;
    }
});

const completeOnboarding = () => {
    if (typeof window !== 'undefined') {
        localStorage.setItem(onboardingKey, '1');
    }
    showOnboarding.value = false;
};

const skipOnboarding = () => {
    if (typeof window !== 'undefined') {
        localStorage.setItem(onboardingKey, '1');
    }
    showOnboarding.value = false;
};

const replayOnboarding = () => {
    onboardingStep.value = 0;
    showOnboarding.value = true;
};

watch([isSimulating, session], ([isActive, currentSession]) => {
    if (isActive) {
        showOnboarding.value = false;
        if (currentSession?.started_at) {
            localSimulationStartMs.value = null;
        } else if (!localSimulationStartMs.value) {
            localSimulationStartMs.value = Date.now();
        }
        startSimulationTimer();
        updateSimulationElapsed();
        return;
    }
    stopSimulationTimer();
}, { immediate: true });

watch([simulationElapsedSeconds, remainingSimulationSeconds, isSimulating], async ([elapsed, remaining, isActive]) => {
    if (!isActive || isAutoEnding.value) return;
    if (remaining !== null && remaining <= 0) {
        await stopSimulation(true);
        return;
    }
    if (session.value?.time_limit_minutes && elapsed >= session.value.time_limit_minutes * 60) {
        await stopSimulation(true);
    }
});

watch([showOnboarding, onboardingStep], ([isVisible, step]) => {
    if (!isVisible || isSimulating.value) return;
    currentView.value = step === 2 ? 'simulation' : 'preparation';
});

onBeforeUnmount(() => {
    stopDeckPolling();
    debouncedPersistDeckSlides.cancel();
    stopSimulationTimer();
});

</script>

<template>
    <AppLayout :title="`Defense Preparation: ${project.title}`">
        <DefenseOnboardingDialog v-model="showOnboarding" v-model:step="onboardingStep"
            :steps="onboardingSteps" @complete="completeOnboarding" @skip="skipOnboarding" />
        <div class="min-h-screen transition-colors duration-700" :class="containerClass">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

                <DefenseHeader :is-simulating="isSimulating" :formatted-readiness-score="formattedReadinessScore"
                    :overall-readiness-score="overallReadinessScore" :low-balance-message="lowBalanceMessage"
                    :class="showOnboarding && onboardingStep === 2 ? 'opacity-40 transition-opacity' : ''"
                    @back="router.visit(route('projects.writing', project.slug))"
                    @dismiss-low-balance="dismissLowBalance" @replay-onboarding="replayOnboarding" />

                <DefenseViewToggle v-model="currentView" :is-simulating="isSimulating"
                    :class="showOnboarding && onboardingStep === 2 ? 'opacity-40 transition-opacity' : ''" />

                <!-- PREPARATION SUITE -->
                <div v-if="currentView === 'preparation' && !isSimulating"
                    class="grid gap-8 lg:grid-cols-12 animate-in fade-in slide-in-from-bottom-4 duration-700">
                    <DefensePrepLeftColumn v-model="openingStatement" :is-deck-swapped="isDeckSwapped"
                        :predicted-questions="predictedQuestions" :is-generating-questions="isGeneratingQuestions"
                        :briefing-slides="briefingSlides" :executive-briefing="executiveBriefing"
                        :is-briefing-loading="isBriefingLoading" :opening-analysis="openingAnalysis"
                        :is-opening-generating="isOpeningGenerating" :is-opening-analyzing="isOpeningAnalyzing"
                        :highlight-prep="showOnboarding && onboardingStep === 0"
                        :highlight-opening="showOnboarding && onboardingStep === 1"
                        @generate-predicted-questions="generatePredictedQuestions"
                        @refresh-executive-briefing="refreshExecutiveBriefing" @toggle-deck-swap="toggleDeckSwap"
                        @generate-opening-statement="generateOpeningStatement"
                        @analyze-opening-statement="analyzeOpeningStatement" />

                    <div class="lg:col-span-4"
                        :class="showOnboarding && onboardingStep < 2 ? 'opacity-40 transition-opacity' : ''">
                        <DefensePrepRightColumn :project="project" :deck-slides="deckSlides" :deck-status="deckStatus"
                            :deck-error="deckError" :deck-download-url="deckDownloadUrl" :deck-id="deckId"
                            :is-deck-generating="isDeckGenerating" :is-deck-saving="isDeckSaving"
                            :is-guide-expanded="isGuideExpanded" :is-production="isProduction"
                            :wysiwyg-slides="wysiwygSlides" :session-history="sessionHistory"
                            :is-history-loading="isHistoryLoading" :active-deck-slide-index="activeDeckSlideIndex"
                            @generate-defense-deck="generateDefenseDeck" @download-pptx="downloadPptx"
                            @update:deck-slides="handleDeckSlidesChange"
                            @update:wysiwyg-slides="handleWysiwygSlidesChange"
                            @update:active-deck-slide-index="activeDeckSlideIndex = $event"
                            @update:is-guide-expanded="isGuideExpanded = $event" @open-session="openSession" />
                    </div>
                </div>

                <!-- SIMULATION LAB (INACTIVE) -->
                <DefenseSimulationLabIntro v-if="currentView === 'simulation' && !isSimulating"
                    :planned-question-limit="plannedQuestionLimit"
                    :active-simulation-personas="activeSimulationPersonas" :is-starting="isStarting"
                    :highlight="showOnboarding && onboardingStep === 2" @start="startSimulation" />

                <!-- ACTIVE SIMULATION (IMMERSIVE MODE) -->
                <div v-if="isSimulating"
                    class="fixed inset-0 z-50 bg-zinc-950 flex flex-col items-center p-4 md:p-8 animate-in fade-in duration-500">
                    <DefenseSimulationHud :formatted-simulation-time="formattedSimulationCountdown"
                        :time-label="simulationTimeLabel"
                        :secondary-time-label="showElapsedTime ? 'Elapsed' : undefined"
                        :secondary-time="showElapsedTime ? formattedSimulationTime : undefined"
                        :question-limit-label="questionLimitLabel"
                        @stop="stopSimulation" />

                    <div class="grid lg:grid-cols-12 gap-8 w-full max-w-6xl flex-grow overflow-hidden">

                        <!-- Panel Visualization -->
                        <DefensePanelVisualization :active-simulation-personas="activeSimulationPersonas"
                            :is-fetching-question="isFetchingQuestion" :thinking-persona-id="thinkingPersonaId"
                            :panelist-question-counts="panelistQuestionCounts"
                            :panelist-question-target="panelistQuestionTarget" :clarity-score="clarityScore"
                            :depth-score="depthScore" :confidence-score="confidenceScore" />

                        <!-- Chat Interaction Area -->
                        <DefenseChatInterface v-model="responseInput" :messages="displayMessages"
                            :persona-lookup="personaLookup" :is-sending="isSending" @submit="submitResponse"
                            @request-hint="requestHint" />
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
