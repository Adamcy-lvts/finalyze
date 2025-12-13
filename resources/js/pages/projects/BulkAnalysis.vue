<!-- /resources/js/pages/projects/BulkAnalysis.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import axios from 'axios';
import { ArrowLeft, Brain, CheckCircle2, Loader2, Play, RefreshCcw, ShieldAlert } from 'lucide-vue-next';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import PurchaseModal from '@/components/PurchaseModal.vue';
import { recordWordUsage, useWordBalance } from '@/composables/useWordBalance';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    word_count: number;
    latest_analysis?: {
        id: number;
        total_score: number;
        completion_percentage: number;
        analyzed_at: string;
    } | null;
}

interface Project {
    id: number;
    slug: string;
    title: string;
    topic: string;
    chapters: Chapter[];
}

const props = defineProps<{
    project: Project;
}>();

const normalizeChapter = (ch: any): Chapter => {
    const latest = ch.latest_analysis || ch.latestAnalysis || null;
    return {
        id: Number(ch.id),
        chapter_number: Number(ch.chapter_number),
        title: ch.title,
        word_count: Number(ch.word_count || 0),
        latest_analysis: latest
            ? {
                  ...latest,
                  total_score: Number(latest.total_score ?? 0),
                  completion_percentage: Number(latest.completion_percentage ?? 0),
              }
            : null,
    };
};

const chapters = ref<Chapter[]>(props.project.chapters?.map(normalizeChapter) || []);
const selectedChapterIds = ref<number[]>(chapters.value.map((c) => c.id));
const isStarting = ref(false);
const isPolling = ref(false);
const batchId = ref<number | null>(null);
const batchStatus = ref<any>(null);
const pollTimer = ref<number | null>(null);

const {
    showPurchaseModal,
    requiredWordsForModal,
    actionDescriptionForModal,
    checkAndPrompt,
    closePurchaseModal,
    estimates,
    balance,
    refreshBalance,
} = useWordBalance();
const readChargedBatchIds = (): number[] => {
    if (typeof window === 'undefined') return [];
    try {
        return JSON.parse(localStorage.getItem('chargedAnalysisBatches') || '[]');
    } catch {
        return [];
    }
};

const chargedBatchIds = ref<Set<number>>(new Set<number>(readChargedBatchIds()));

const persistChargedBatches = () => {
    if (typeof window === 'undefined') return;
    localStorage.setItem('chargedAnalysisBatches', JSON.stringify([...chargedBatchIds.value]));
};

const markBatchCharged = (id: number) => {
    chargedBatchIds.value.add(id);
    persistChargedBatches();
};

const hasChargedBatch = (id: number | null | undefined) => {
    if (!id) return false;
    return chargedBatchIds.value.has(Number(id));
};

const totalSelected = computed(() => selectedChapterIds.value.length);
const progressPercent = computed(() => {
    if (!batchStatus.value?.batch?.total_chapters) return 0;
    const completed = batchStatus.value.batch.completed_chapters || 0;
    const failed = batchStatus.value.batch.failed_chapters || 0;
    return Math.round(((completed + failed) / batchStatus.value.batch.total_chapters) * 100);
});

const loadResults = async () => {
    try {
        const { data } = await axios.get(route('api.projects.analysis.results', props.project.slug));
        if (data.success && data.chapters) {
            chapters.value = data.chapters.map(normalizeChapter);
            // Preserve selection after refresh
            const availableIds = new Set(chapters.value.map((c) => c.id));
            selectedChapterIds.value = selectedChapterIds.value.filter((id) => availableIds.has(id));
            if (selectedChapterIds.value.length === 0) {
                selectedChapterIds.value = chapters.value.map((c) => c.id);
            }
        }
    } catch (error) {
        console.error('Failed to load analysis results', error);
        toast.error('Could not load analysis results');
        // Fallback to existing project chapters to keep UI usable
        if (!chapters.value.length && props.project.chapters?.length) {
            chapters.value = props.project.chapters.map(normalizeChapter);
            selectedChapterIds.value = chapters.value.map((c) => c.id);
        }
    }
};

const pollBatch = async () => {
    if (!batchId.value || isPolling.value) return;
    isPolling.value = true;

    try {
        const { data } = await axios.get(
            route('api.projects.analysis.batch', { project: props.project.slug, batch: batchId.value })
        );

        if (data.success) {
            batchStatus.value = data;
            if (['completed', 'failed', 'cancelled'].includes(data.batch.status)) {
                stopPolling();
                toast.success('Analysis finished');
                if (data.batch.status === 'completed') {
                    await maybeRecordUsage(data.batch);
                }
                await loadResults();
            }
        }
    } catch (error) {
        console.error('Polling failed', error);
        toast.error('Failed to poll analysis status');
        stopPolling();
    } finally {
        isPolling.value = false;
    }
};

const startPolling = () => {
    stopPolling();
    pollBatch();
    pollTimer.value = window.setInterval(pollBatch, 3000);
};

const stopPolling = () => {
    if (pollTimer.value) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
};

onBeforeUnmount(() => stopPolling());

const maybeRecordUsage = async (batch: any) => {
    const batchIdValue = Number(batch?.id);
    if (!batchIdValue || hasChargedBatch(batchIdValue)) return;

    const wordsUsed = Number(batch?.consumed_words ?? batch?.required_words ?? 0);
    if (!wordsUsed || Number.isNaN(wordsUsed)) return;

    try {
        await recordWordUsage(wordsUsed, 'Bulk chapter analysis', 'analysis_batch', batchIdValue);
        markBatchCharged(batchIdValue);
        await refreshBalance();
    } catch (err) {
        console.error('Failed to record bulk analysis usage', err);
    }
};

const startAnalysis = async () => {
    if (selectedChapterIds.value.length === 0) {
        toast.error('Select at least one chapter to analyze');
        return;
    }

    // Rough cost estimate using average per chapter
    const avgWords = chapters.value.length
        ? Math.round(chapters.value.reduce((sum, ch) => sum + Math.max(500, ch.word_count || 0), 0) / chapters.value.length)
        : 1500;
    const requiredWords = estimates.chapter(avgWords) * selectedChapterIds.value.length;

    if (!checkAndPrompt(requiredWords, 'run bulk analysis')) {
        return;
    }

    isStarting.value = true;
    try {
        const { data } = await axios.post(route('api.projects.analysis.start', props.project.slug), {
            chapter_ids: selectedChapterIds.value,
        });

        if (data.success) {
            batchId.value = data.batch_id;
            batchStatus.value = null;
            startPolling();
            toast.success('Analysis started');
        } else {
            toast.error(data.error || 'Unable to start analysis');
        }
    } catch (error: any) {
        console.error('Failed to start analysis', error);
        toast.error(error.response?.data?.error || 'Failed to start analysis');
    } finally {
        isStarting.value = false;
    }
};

const setSelection = (id: number, checked: boolean | 'indeterminate') => {
    const chapterId = Number(id);
    if (checked === true) {
        if (!selectedChapterIds.value.includes(chapterId)) {
            selectedChapterIds.value = [...selectedChapterIds.value, chapterId];
        }
        return;
    }

    selectedChapterIds.value = selectedChapterIds.value.filter((cid) => cid !== chapterId);
};

const selectAll = () => {
    selectedChapterIds.value = chapters.value.map((c) => c.id);
};

const clearSelection = () => {
    selectedChapterIds.value = [];
};

onMounted(async () => {
    await loadResults();
});
</script>

<template>
    <AppLayout title="Bulk Analysis">
        <TooltipProvider>
            <div class="max-w-6xl mx-auto py-6 px-4 space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <Button variant="ghost" size="icon" @click="router.visit(route('projects.show', props.project.slug))">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                        <div>
                            <SafeHtmlText as="h1" class="text-xl font-semibold text-foreground" :content="props.project.title" />
                            <p class="text-sm text-muted-foreground">Run quality analysis across selected chapters</p>
                        </div>
                    </div>
                    <Badge variant="outline" class="gap-2">
                        <Brain class="h-4 w-4" />
                        Bulk Analysis
                    </Badge>
                </div>

                <Card>
                    <CardHeader class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <CardTitle class="text-lg">Chapters</CardTitle>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button size="sm" variant="outline" @click="selectAll">Select All</Button>
                            <Button size="sm" variant="ghost" @click="clearSelection">Clear</Button>
                            <Button :disabled="isStarting" size="sm" class="gap-2" @click="startAnalysis">
                                <Loader2 v-if="isStarting" class="h-4 w-4 animate-spin" />
                                <Play v-else class="h-4 w-4" />
                                Start Analysis ({{ totalSelected }})
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="max-h-[480px]">
                            <div class="divide-y divide-border">
                                <div v-for="chapter in chapters" :key="chapter.id" class="flex items-center gap-3 py-3">
                                    <Checkbox
                                        :model-value="selectedChapterIds.includes(chapter.id)"
                                        @update:model-value="(val) => setSelection(chapter.id, val)"
                                    />
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 text-sm font-medium">
                                            <span class="text-muted-foreground">Chapter {{ chapter.chapter_number }}</span>
                                            <SafeHtmlText as="span" :content="chapter.title" />
                                        </div>
                                        <div class="text-xs text-muted-foreground flex items-center gap-3">
                                            <span>{{ chapter.word_count || 0 }} words</span>
                                            <span v-if="chapter.latest_analysis">
                                                Last score: {{ chapter.latest_analysis.total_score }} ({{ chapter.latest_analysis.completion_percentage }}%)
                                            </span>
                                            <span v-else class="text-amber-500 flex items-center gap-1">
                                                <ShieldAlert class="h-3 w-3" /> Not analyzed yet
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>
                    </CardContent>
                </Card>

                <Card v-if="batchId">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <RefreshCcw class="h-4 w-4" />
                            Analysis Progress
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span>Status</span>
                            <span class="font-medium">
                                {{ batchStatus?.batch?.status || 'running' }}
                            </span>
                        </div>
                        <Progress :model-value="progressPercent" />
                        <div class="text-xs text-muted-foreground">
                            Completed: {{ batchStatus?.batch?.completed_chapters || 0 }} /
                            {{ batchStatus?.batch?.total_chapters || totalSelected }}
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="chapters.some((c) => c.latest_analysis)">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle2 class="h-4 w-4 text-green-500" />
                            Latest Results
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div v-for="chapter in chapters" :key="`result-${chapter.id}`" class="p-3 border rounded-lg">
                                <div class="flex items-center justify-between text-sm font-medium mb-1">
                                    <span>Chapter {{ chapter.chapter_number }}</span>
                                    <span v-if="chapter.latest_analysis">
                                        {{ chapter.latest_analysis.total_score }} pts
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground line-clamp-2">
                                    <SafeHtmlText as="span" :content="chapter.title" />
                                </p>
                                <div v-if="chapter.latest_analysis" class="mt-2">
                                    <Progress :model-value="chapter.latest_analysis.completion_percentage" />
                                    <div class="text-[11px] text-muted-foreground mt-1">
                                        Analyzed at {{ new Date(chapter.latest_analysis.analyzed_at).toLocaleString() }}
                                    </div>
                                </div>
                                <div v-else class="text-xs text-muted-foreground mt-2">No analysis yet</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <PurchaseModal
                :open="showPurchaseModal"
                :current-balance="balance"
                :required-words="requiredWordsForModal"
                :action="actionDescriptionForModal"
                @close="closePurchaseModal"
            />
        </TooltipProvider>
    </AppLayout>
</template>
