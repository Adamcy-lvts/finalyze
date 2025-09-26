<!-- /resources/js/components/chapter-editor/MobileNavOverlay.vue -->
<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Lightbulb, X } from 'lucide-vue-next';
import { ref } from 'vue';
import AISidebar from './AISidebar.vue';
import ChapterNavigation from './ChapterNavigation.vue';
import DefensePreparationPanel from './DefensePreparationPanel.vue';
import DataCollectionPanel from './DataCollectionPanel.vue';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    content: string | null;
    word_count: number;
    status: 'not_started' | 'draft' | 'in_review' | 'approved';
}

interface ProjectCategory {
    id: number;
    name: string;
    slug: string;
    default_chapter_count: number;
    chapter_structure: any[];
    target_word_count: number;
}

interface Project {
    id: number;
    slug: string;
    title: string;
    topic: string;
    type: string;
    status: string;
    mode: 'auto' | 'manual';
    field_of_study: string;
    university: string;
    course: string;
    project_category_id?: number;
    category?: ProjectCategory;
}

interface Props {
    showLeftSidebar: boolean;
    showRightSidebar: boolean;
    isMobile: boolean;
    allChapters: Chapter[];
    currentChapter: Chapter;
    project: Project;
    currentWordCount: number;
    targetWordCount: number;
    writingQualityScore: number;
    chapterContentLength: number;
    isGenerating: boolean;
    selectedText: string;
    isLoadingSuggestions: boolean;
    showCitationHelper: boolean;
    chapterContent: string;
    showDefensePrep?: boolean;
    defenseQuestions?: any[];
    isLoadingDefenseQuestions?: boolean;
    isGeneratingDefenseQuestions?: boolean;
    meetsDefenseThreshold?: boolean;
    shouldShowDefenseProgress?: boolean;
    defenseProgressPercentage?: number;
    defenseWordsRemaining?: number;
    hasTriggeredGeneration?: boolean;
}

const props = defineProps<Props>();

// Local state for collapsible panels
const localShowDefensePrep = ref(props.showDefensePrep ?? true);

const emit = defineEmits<{
    'update:showLeftSidebar': [value: boolean];
    'update:showRightSidebar': [value: boolean];
    goToChapter: [chapterNumber: number];
    generateNextChapter: [];
    startStreamingGeneration: [type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand', options?: { section?: string, mode?: string, selectedText?: string, style?: string }];
    getAISuggestions: [];
    'update:showCitationHelper': [value: boolean];
    checkCitations: [];
    'update:showDefensePrep': [value: boolean];
    'generate-more': [];
    'refresh': [];
    'mark-helpful': [questionId: number, helpful: boolean];
    'hide-question': [questionId: number];
}>();

// Methods
const handleCloseLeftSidebar = () => emit('update:showLeftSidebar', false);
const handleCloseRightSidebar = () => emit('update:showRightSidebar', false);
const handleGoToChapter = (chapterNumber: number) => emit('goToChapter', chapterNumber);
const handleGenerateNextChapter = () => emit('generateNextChapter');
const handleStartStreamingGeneration = (type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand', options?: { section?: string, mode?: string, selectedText?: string, style?: string }) => emit('startStreamingGeneration', type, options);
const handleGetAISuggestions = () => emit('getAISuggestions');
const handleUpdateShowCitationHelper = (value: boolean) => emit('update:showCitationHelper', value);
const handleUpdateShowDefensePrep = (value: boolean) => {
    localShowDefensePrep.value = value;
    emit('update:showDefensePrep', value);
};
const handleGenerateMore = () => emit('generate-more');
const handleRefresh = () => emit('refresh');
const handleMarkHelpful = (questionId: number, helpful: boolean) => emit('mark-helpful', questionId, helpful);
const handleHideQuestion = (questionId: number) => emit('hide-question', questionId);
</script>

<template>
    <div>
        <!-- Mobile Left Sidebar Overlay -->
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showLeftSidebar && isMobile" class="fixed inset-0 z-50 lg:hidden">
                <Transition
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="duration-200 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/50" @click="handleCloseLeftSidebar"></div>
                </Transition>
                <Transition
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="-translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="-translate-x-full"
                >
                    <div class="fixed top-0 left-0 h-full w-80 overflow-y-auto border-r bg-background shadow-xl transform transition-transform">
                <div class="border-b p-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold">Navigation</h2>
                        <Button @click="handleCloseLeftSidebar" variant="ghost" size="sm" class="h-8 w-8">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <div class="space-y-4 p-4 mobile-enhanced">
                    <!-- Chapter Navigation -->
                    <ChapterNavigation
                        :all-chapters="allChapters"
                        :current-chapter="currentChapter"
                        :project="project"
                        :current-word-count="currentWordCount"
                        :target-word-count="targetWordCount"
                        :writing-quality-score="writingQualityScore"
                        :chapter-content-length="chapterContentLength"
                        @go-to-chapter="handleGoToChapter"
                        @generate-next-chapter="handleGenerateNextChapter"
                    />

                    <!-- Writing Tips -->
                    <Alert class="border-border/50">
                        <Lightbulb class="h-4 w-4 text-muted-foreground" />
                        <AlertDescription class="text-xs text-muted-foreground">
                            <strong class="text-foreground">Pro Tips:</strong><br />
                            • Press Ctrl+S to save<br />
                            • Ctrl+Z/Y for undo/redo<br />
                            • Select text for AI suggestions<br />
                            • F11 for distraction-free mode
                        </AlertDescription>
                    </Alert>
                </div>
                    </div>
                </Transition>
            </div>
        </Transition>

        <!-- Mobile Right Sidebar Overlay -->
        <Transition
            enter-active-class="duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showRightSidebar && isMobile" class="fixed inset-0 z-50 lg:hidden">
                <Transition
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="duration-200 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div class="fixed inset-0 bg-black/50" @click="handleCloseRightSidebar"></div>
                </Transition>
                <Transition
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="translate-x-full"
                    enter-to-class="translate-x-0"
                    leave-active-class="duration-200 ease-in"
                    leave-from-class="translate-x-0"
                    leave-to-class="translate-x-full"
                >
                    <div class="fixed top-0 right-0 h-full w-96 overflow-y-auto border-l bg-background shadow-xl transform transition-transform">
                <div class="border-b p-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold">AI Tools</h2>
                        <Button @click="handleCloseRightSidebar" variant="ghost" size="sm" class="h-8 w-8">
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
                <div class="space-y-4 p-4 mobile-enhanced">
                    <!-- AI Tools Panel -->
                    <AISidebar
                        :project="project"
                        :chapter="currentChapter"
                        :is-generating="isGenerating"
                        :selected-text="selectedText"
                        :is-loading-suggestions="isLoadingSuggestions"
                        :show-citation-helper="showCitationHelper"
                        :chapter-content="chapterContent"
                        :current-word-count="currentWordCount"
                        :target-word-count="targetWordCount"
                        @start-streaming-generation="handleStartStreamingGeneration"
                        @get-ai-suggestions="handleGetAISuggestions"
                        @update:show-citation-helper="handleUpdateShowCitationHelper"
                    />

                    <!-- Defense Preparation Panel -->
                    <DefensePreparationPanel
                        :show-defense-prep="localShowDefensePrep"
                        :questions="defenseQuestions ?? []"
                        :is-loading="isLoadingDefenseQuestions ?? false"
                        :is-generating="isGeneratingDefenseQuestions ?? false"
                        :chapter-context="{
                            chapter_number: currentChapter.chapter_number,
                            chapter_title: currentChapter.title,
                            word_count: currentWordCount
                        }"
                        :defense-watcher="{
                            meetsThreshold: meetsDefenseThreshold ?? false,
                            shouldShowProgress: shouldShowDefenseProgress ?? false,
                            progressPercentage: defenseProgressPercentage ?? 0,
                            wordsRemaining: defenseWordsRemaining ?? 0,
                            hasTriggeredGeneration: hasTriggeredGeneration ?? false,
                            threshold: 500,
                            statusMessage: (shouldShowDefenseProgress ?? false) ? `Write ${defenseWordsRemaining ?? 0} more words to generate defense questions` : 'Ready to generate defense questions'
                        }"
                        @update:show-defense-prep="handleUpdateShowDefensePrep"
                        @generate-more="handleGenerateMore"
                        @refresh="handleRefresh"
                        @mark-helpful="handleMarkHelpful"
                        @hide-question="handleHideQuestion"
                    />

                    <!-- Data Collection Panel -->
                    <DataCollectionPanel
                        :chapter-id="currentChapter.id"
                        :content="chapterContent"
                    />
                </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* Mobile-specific enhancements for compact layout */
.mobile-enhanced {
    /* Compact design for better space utilization */
}

/* Make buttons more compact but still touchable */
.mobile-enhanced :deep(button) {
    min-height: 36px; /* Smaller but still touchable */
    padding: 0.5rem 0.75rem;
    font-size: 0.825rem;
}

/* Reduce text sizes to fit more content */
.mobile-enhanced :deep(.text-xs) {
    font-size: 0.7rem; /* Smaller than default */
}

.mobile-enhanced :deep(.text-sm) {
    font-size: 0.8rem; /* Smaller than default */
}

.mobile-enhanced :deep(.text-base) {
    font-size: 0.9rem; /* Smaller base text */
}

/* Reduce spacing for more compact layout */
.mobile-enhanced :deep(.gap-2) {
    gap: 0.375rem; /* Tighter spacing */
}

.mobile-enhanced :deep(.gap-3) {
    gap: 0.5rem; /* Tighter spacing */
}

.mobile-enhanced :deep(.p-2) {
    padding: 0.375rem; /* Tighter padding */
}

.mobile-enhanced :deep(.p-3) {
    padding: 0.5rem; /* Tighter padding */
}

.mobile-enhanced :deep(.p-4) {
    padding: 0.625rem; /* Tighter padding */
}

/* Smaller icons to save space */
.mobile-enhanced :deep(.h-3) {
    height: 0.75rem;
    width: 0.75rem;
}

.mobile-enhanced :deep(.h-4) {
    height: 0.875rem;
    width: 0.875rem;
}

/* Make badges more compact */
.mobile-enhanced :deep(.badge) {
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
    line-height: 1.2;
}

/* Reduce card padding for mobile */
.mobile-enhanced :deep(.card) {
    padding: 0.75rem;
}

.mobile-enhanced :deep(.card-header) {
    padding: 0.75rem;
    padding-bottom: 0.5rem;
}

.mobile-enhanced :deep(.card-content) {
    padding: 0.75rem;
    padding-top: 0;
}

/* Make collapsible triggers more compact */
.mobile-enhanced :deep(.collapsible-trigger) {
    min-height: 40px;
    padding: 0.75rem;
}

/* Reduce spacing in mobile for tighter layout */
.mobile-enhanced :deep(.space-y-1 > * + *) {
    margin-top: 0.25rem;
}

.mobile-enhanced :deep(.space-y-2 > * + *) {
    margin-top: 0.375rem;
}

.mobile-enhanced :deep(.space-y-3 > * + *) {
    margin-top: 0.5rem;
}

.mobile-enhanced :deep(.space-y-4 > * + *) {
    margin-top: 0.75rem;
}

/* Make form elements more compact */
.mobile-enhanced :deep(input),
.mobile-enhanced :deep(select),
.mobile-enhanced :deep(textarea) {
    padding: 0.5rem;
    font-size: 0.875rem;
}

/* Compact grid layouts */
.mobile-enhanced :deep(.grid-cols-2) {
    gap: 0.375rem;
}

/* Reduce alert padding */
.mobile-enhanced :deep(.alert) {
    padding: 0.625rem;
}

/* Tighter line heights for better space utilization */
.mobile-enhanced :deep(*) {
    line-height: 1.4;
}
</style>
