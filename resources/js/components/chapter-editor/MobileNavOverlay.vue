<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Lightbulb, X } from 'lucide-vue-next';
import AISidebar from './AISidebar.vue';
import ChapterNavigation from './ChapterNavigation.vue';

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
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:showLeftSidebar': [value: boolean];
    'update:showRightSidebar': [value: boolean];
    goToChapter: [chapterNumber: number];
    generateNextChapter: [];
    startStreamingGeneration: [type: 'progressive' | 'outline' | 'improve'];
    getAISuggestions: [];
    'update:showCitationHelper': [value: boolean];
}>();

// Methods
const handleCloseLeftSidebar = () => emit('update:showLeftSidebar', false);
const handleCloseRightSidebar = () => emit('update:showRightSidebar', false);
const handleGoToChapter = (chapterNumber: number) => emit('goToChapter', chapterNumber);
const handleGenerateNextChapter = () => emit('generateNextChapter');
const handleStartStreamingGeneration = (type: 'progressive' | 'outline' | 'improve') => emit('startStreamingGeneration', type);
const handleGetAISuggestions = () => emit('getAISuggestions');
const handleUpdateShowCitationHelper = (value: boolean) => emit('update:showCitationHelper', value);
</script>

<template>
    <!-- Mobile Left Sidebar Overlay -->
    <div v-if="showLeftSidebar && isMobile" class="fixed inset-0 z-50 lg:hidden">
        <div class="fixed inset-0 bg-black/50" @click="handleCloseLeftSidebar"></div>
        <div class="fixed top-0 left-0 h-full w-80 overflow-y-auto border-r bg-background shadow-xl">
            <div class="border-b p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Navigation</h2>
                    <Button @click="handleCloseLeftSidebar" variant="ghost" size="icon">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
            <div class="space-y-6 p-4">
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
    </div>

    <!-- Mobile Right Sidebar Overlay -->
    <div v-if="showRightSidebar && isMobile" class="fixed inset-0 z-50 lg:hidden">
        <div class="fixed inset-0 bg-black/50" @click="handleCloseRightSidebar"></div>
        <div class="fixed top-0 right-0 h-full w-80 overflow-y-auto border-l bg-background shadow-xl">
            <div class="border-b p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">AI Tools</h2>
                    <Button @click="handleCloseRightSidebar" variant="ghost" size="icon">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
            <div class="space-y-6 p-4">
                <!-- AI Tools Panel -->
                <AISidebar
                    :project="project"
                    :is-generating="isGenerating"
                    :selected-text="selectedText"
                    :is-loading-suggestions="isLoadingSuggestions"
                    :show-citation-helper="showCitationHelper"
                    :chapter-content="chapterContent"
                    @start-streaming-generation="handleStartStreamingGeneration"
                    @get-ai-suggestions="handleGetAISuggestions"
                    @update:show-citation-helper="handleUpdateShowCitationHelper"
                />
            </div>
        </div>
    </div>
</template>
