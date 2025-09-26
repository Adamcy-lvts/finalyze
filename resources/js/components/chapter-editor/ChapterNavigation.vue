<!-- /resources/js/components/chapter-editor/ChapterNavigation.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { ArrowRight, BookMarked, CheckCircle, Target, Zap, ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { toast } from 'vue-sonner';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    slug: string;
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
    deleteChapter: [chapterId: number];
}>();

const page = usePage();

// Computed
const showGenerateNextChapter = computed(() => {
    // Use project category default, or fall back to a reasonable max (6 chapters for most academic work)
    const maxChapters = props.project.category?.default_chapter_count || 6;
    return props.currentChapter.chapter_number < maxChapters;
});

const isChapterReadyForProgression = computed(() => {
    // Temporarily always ready for testing
    return true;
});

const nextChapter = computed(() => {
    // Find the actual next chapter number by looking at existing chapters
    const existingChapterNumbers = props.allChapters.map(ch => ch.chapter_number).sort((a, b) => a - b);
    const currentIndex = existingChapterNumbers.indexOf(props.currentChapter.chapter_number);

    let nextChapterNumber;
    if (currentIndex === -1 || currentIndex === existingChapterNumbers.length - 1) {
        // If current chapter not found or is the last chapter, generate the next sequential number
        const maxChapterNumber = Math.max(...existingChapterNumbers);
        nextChapterNumber = maxChapterNumber + 1;
    } else {
        // If there are gaps, find the next available number
        nextChapterNumber = props.currentChapter.chapter_number + 1;
    }

    // Check if this would exceed max chapters
    const maxChapters = props.project.category?.default_chapter_count || 6;
    if (nextChapterNumber > maxChapters) {
        return null;
    }

    // Check if this chapter already exists
    const found = props.allChapters.find((ch) => ch.chapter_number === nextChapterNumber);
    return found || { chapter_number: nextChapterNumber, title: `Chapter ${nextChapterNumber}` };
});

const previousChapter = computed(() => {
    const prevChapterNumber = props.currentChapter.chapter_number - 1;
    
    if (prevChapterNumber < 1) {
        return null;
    }
    
    return props.allChapters.find((ch) => ch.chapter_number === prevChapterNumber) || null;
});

const existingNextChapter = computed(() => {
    // Find the actual next chapter by sorting and looking for the next in sequence
    const existingChapterNumbers = props.allChapters.map(ch => ch.chapter_number).sort((a, b) => a - b);
    const currentIndex = existingChapterNumbers.indexOf(props.currentChapter.chapter_number);

    if (currentIndex === -1 || currentIndex === existingChapterNumbers.length - 1) {
        // Current chapter not found or is the last chapter
        return null;
    }

    // Return the next existing chapter
    const nextChapterNumber = existingChapterNumbers[currentIndex + 1];
    return props.allChapters.find((ch) => ch.chapter_number === nextChapterNumber) || null;
});

// Methods
const handleGoToChapter = (chapterNumber: number) => {
    emit('goToChapter', chapterNumber);
};

const handleGenerateNextChapter = () => {
    emit('generateNextChapter');
};

const handleDeleteChapter = (chapter: Chapter) => {
    console.log('🗑️ DELETE CHAPTER CALLED:', chapter);
    console.log('🔗 Route being called:', route('chapters.destroy', {
        project: props.project.slug,
        chapter: chapter.slug
    }));

    router.delete(route('chapters.destroy', {
        project: props.project.slug,
        chapter: chapter.slug
    }), {
        preserveState: true,  // Keep current state
        preserveScroll: true, // Keep scroll position
        only: ['allChapters', 'chapter'], // Refresh chapter list and current chapter data
        onSuccess: (page) => {
            console.log('✅ Delete successful:', page);

            // Show success toast
            const flashMessage = page.props.flash?.message || 'Chapter deleted successfully';
            toast.success(flashMessage);

            console.log('📝 Chapter deleted successfully');
            // Backend will handle redirect if we're viewing the deleted chapter
            // Otherwise, navigation will automatically update with refreshed data
        },
        onError: (errors) => {
            console.error('❌ Delete failed:', errors);
            // Show error toast
            const errorMessage = typeof errors === 'string' ? errors : 'Failed to delete chapter. Please try again.';
            toast.error(errorMessage);
        },
        onFinish: () => {
            console.log('🏁 Delete request finished');
        }
    });
};
</script>

<template>
    <Card class="border-[0.5px] border-border/50">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <BookMarked class="h-4 w-4" />
                    Chapters
                </div>
                <div class="text-xs text-muted-foreground font-normal" title="Use Ctrl+← → to navigate between chapters">
                    ⌘←→
                </div>
            </CardTitle>
        </CardHeader>
        <CardContent class="p-3">
            <ScrollArea class="h-[280px]">
                <div class="space-y-1 pr-2">
                    <div
                        v-for="ch in allChapters"
                        :key="ch.id"
                        class="flex items-center gap-1 group"
                    >
                        <Button
                            @click="handleGoToChapter(ch.chapter_number)"
                            :variant="ch.chapter_number === currentChapter.chapter_number ? 'default' : 'ghost'"
                            size="sm"
                            class="flex-1 justify-start text-xs relative"
                        >
                            <span class="mr-2 font-mono">{{ ch.chapter_number }}.</span>
                            <span class="truncate">{{ ch.title || 'Untitled Chapter' }}</span>
                            <div class="ml-auto flex items-center gap-1">
                                <!-- Status indicator -->
                                <CheckCircle v-if="ch.status === 'approved'" class="h-3 w-3 text-green-500" />
                            </div>
                        </Button>

                        <!-- Delete Chapter Button -->
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-950"
                                    @click="() => console.log('🔥 TRASH ICON CLICKED for chapter:', ch.id)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Delete Chapter</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Are you sure you want to delete "Chapter {{ ch.chapter_number }}: {{ ch.title || 'Untitled Chapter' }}"?
                                        This action cannot be undone and all chapter content will be permanently removed.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                                        @click="() => {
                                            console.log('🎯 DELETE BUTTON CLICKED for chapter:', ch.id, ch.slug);
                                            handleDeleteChapter(ch);
                                        }"
                                    >
                                        Delete Chapter
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>
            </ScrollArea>

            <!-- Chapter Navigation Section -->
            <div class="mt-3 border-t border-border/50 pt-3 space-y-2">
                <!-- Previous/Next Navigation -->
                <div class="flex flex-col gap-2">
                    <Button
                        v-if="previousChapter"
                        @click="handleGoToChapter(previousChapter.chapter_number)"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        :title="`Go to ${previousChapter.title}`"
                    >
                        <ChevronLeft class="mr-1 h-3 w-3" />
                        <span class="truncate">{{ previousChapter.chapter_number }}. {{ previousChapter.title }}</span>
                    </Button>
                    
                    <Button
                        v-if="existingNextChapter"
                        @click="handleGoToChapter(existingNextChapter.chapter_number)"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        :title="`Go to ${existingNextChapter.title}`"
                    >
                        <span class="truncate">{{ existingNextChapter.chapter_number }}. {{ existingNextChapter.title }}</span>
                        <ChevronRight class="ml-1 h-3 w-3" />
                    </Button>
                </div>

                <!-- Generate Next Chapter Button -->
                <Button
                    v-if="showGenerateNextChapter && !existingNextChapter"
                    @click="handleGenerateNextChapter"
                    :disabled="!isChapterReadyForProgression"
                    variant="default"
                    size="sm"
                    :class="[
                        'w-full border-0 transition-all duration-200',
                        isChapterReadyForProgression
                            ? 'bg-gradient-to-r from-green-600 to-blue-600 shadow-lg hover:from-green-700 hover:to-blue-700'
                            : 'cursor-not-allowed bg-gray-400 opacity-60 hover:bg-gray-500',
                    ]"
                    :title="
                        isChapterReadyForProgression
                            ? `Generate Chapter ${nextChapter?.chapter_number} automatically`
                            : `Complete this chapter first (${Math.round((currentWordCount / targetWordCount) * 100)}% done, ${writingQualityScore}% quality)`
                    "
                >
                    <Plus class="mr-2 h-4 w-4" />
                    Generate Chapter {{ nextChapter?.chapter_number }}
                    <Zap class="ml-2 h-4 w-4" />
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
