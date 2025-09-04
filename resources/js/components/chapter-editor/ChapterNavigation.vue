<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { ArrowRight, BookMarked, CheckCircle, Target, Zap } from 'lucide-vue-next';
import { computed } from 'vue';

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
    allChapters: Chapter[];
    currentChapter: Chapter;
    project: Project;
    currentWordCount: number;
    targetWordCount: number;
    writingQualityScore: number;
    chapterContentLength: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    goToChapter: [chapterNumber: number];
    generateNextChapter: [];
}>();

// Computed
const showGenerateNextChapter = computed(() => {
    const maxChapters = props.project.category?.default_chapter_count || props.allChapters.length;
    return props.currentChapter.chapter_number < maxChapters;
});

const isChapterReadyForProgression = computed(() => {
    // Temporarily always ready for testing
    return true;
});

const nextChapter = computed(() => {
    const nextChapterNumber = props.currentChapter.chapter_number + 1;
    const maxChapters = props.project.category?.default_chapter_count || props.allChapters.length;

    if (nextChapterNumber > maxChapters) {
        return null;
    }

    const found = props.allChapters.find((ch) => ch.chapter_number === nextChapterNumber);
    return found || { chapter_number: nextChapterNumber, title: `Chapter ${nextChapterNumber}` };
});

// Methods
const handleGoToChapter = (chapterNumber: number) => {
    emit('goToChapter', chapterNumber);
};

const handleGenerateNextChapter = () => {
    emit('generateNextChapter');
};
</script>

<template>
    <Card class="border-[0.5px] border-border/50">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-sm">
                <BookMarked class="h-4 w-4" />
                Chapters
            </CardTitle>
        </CardHeader>
        <CardContent class="p-3">
            <ScrollArea class="h-[280px]">
                <div class="space-y-1 pr-2">
                    <Button
                        v-for="ch in allChapters"
                        :key="ch.id"
                        @click="handleGoToChapter(ch.chapter_number)"
                        :variant="ch.chapter_number === currentChapter.chapter_number ? 'default' : 'ghost'"
                        size="sm"
                        class="w-full justify-start text-xs"
                    >
                        <span class="mr-2 font-mono">{{ ch.chapter_number }}.</span>
                        <span class="truncate">{{ ch.title || 'Untitled Chapter' }}</span>
                        <CheckCircle v-if="ch.status === 'approved'" class="ml-auto h-3 w-3 text-green-500" />
                    </Button>
                </div>
            </ScrollArea>

            <!-- Generate Next Chapter Button -->
            <div v-if="showGenerateNextChapter" class="mt-3 border-t border-border/50 pt-3">
                <Button
                    @click="handleGenerateNextChapter"
                    :disabled="!isChapterReadyForProgression"
                    variant="default"
                    size="sm"
                    :class="[
                        'w-full border-0 transition-all duration-200',
                        isChapterReadyForProgression
                            ? 'bg-gradient-to-r from-blue-600 to-purple-600 shadow-lg hover:from-blue-700 hover:to-purple-700'
                            : 'cursor-not-allowed bg-gray-400 opacity-60 hover:bg-gray-500',
                    ]"
                    :title="
                        isChapterReadyForProgression
                            ? `Generate Chapter ${nextChapter?.chapter_number} automatically`
                            : `Complete this chapter first (${Math.round((currentWordCount / targetWordCount) * 100)}% done, ${writingQualityScore}% quality)`
                    "
                >
                    <Zap class="mr-2 h-4 w-4" />
                    Generate Next Chapter
                    <ArrowRight class="ml-2 h-4 w-4" />
                </Button>

                <!-- Chapter Progression Requirements -->
                <div v-if="!isChapterReadyForProgression" class="mt-3 space-y-2">
                    <p class="flex items-center gap-1 text-xs text-muted-foreground">
                        <Target class="h-3 w-3" />
                        Requirements to unlock:
                    </p>

                    <div class="space-y-1">
                        <!-- Word Count Progress -->
                        <div class="flex items-center gap-2 text-xs">
                            <div :class="['h-2 w-2 rounded-full', currentWordCount >= targetWordCount * 0.6 ? 'bg-green-500' : 'bg-gray-300']"></div>
                            <span class="font-medium">{{ Math.round((currentWordCount / targetWordCount) * 100) }}%</span>
                            <span class="text-muted-foreground">word target</span>
                        </div>

                        <!-- Quality Score -->
                        <div class="flex items-center gap-2 text-xs">
                            <div :class="['h-2 w-2 rounded-full', writingQualityScore >= 50 ? 'bg-green-500' : 'bg-gray-300']"></div>
                            <span class="font-medium">{{ writingQualityScore }}%</span>
                            <span class="text-muted-foreground">quality</span>
                        </div>

                        <!-- Content Length -->
                        <div class="flex items-center gap-2 text-xs">
                            <div :class="['h-2 w-2 rounded-full', chapterContentLength >= 200 ? 'bg-green-500' : 'bg-gray-300']"></div>
                            <span class="font-medium">{{ chapterContentLength }}</span>
                            <span class="text-muted-foreground">characters</span>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
