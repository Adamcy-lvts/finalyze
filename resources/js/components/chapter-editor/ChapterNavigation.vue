<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    BookMarked,
    CheckCircle,
    Target,
    Zap,
    ChevronLeft,
    ChevronRight,
    Plus,
    Trash2,
    Lock,
    FileText,
    MoreVertical
} from 'lucide-vue-next';
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { toast } from 'vue-sonner';
import { cn } from '@/lib/utils';

interface Chapter {
    id: number;
    chapter_number: number;
    title: string;
    slug: string;
    content: string | null;
    word_count: number;
    status: 'not_started' | 'draft' | 'in_review' | 'approved' | 'completed';
}

interface ChapterSection {
    id: number;
    section_number: string;
    section_title: string;
    section_description: string;
    target_word_count: number;
    current_word_count: number;
    is_completed: boolean;
    is_required: boolean;
}

interface ProjectOutline {
    id: number;
    chapter_number: number;
    chapter_title: string;
    target_word_count: number;
    completion_threshold: number;
    description: string;
    sections: ChapterSection[];
}

interface FacultyChapter {
    number: number;
    title: string;
    word_count: number;
    completion_threshold: number;
    description: string;
    is_required: boolean;
    sections: Array<{
        number: string;
        title: string;
        description: string;
        word_count: number;
        is_required: boolean;
        tips?: string[];
    }>;
}

interface Project {
    id: number;
    slug: string;
    title: string;
    // ... other fields
}

interface Props {
    allChapters: Chapter[];
    currentChapter: Chapter;
    project: Project;
    outlines: ProjectOutline[];
    facultyChapters: FacultyChapter[];
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

// Merge faculty chapters, outlines, and existing chapters to get full status
const unifiedChapters = computed(() => {
    // Priority 1: Use faculty chapters if available (most accurate, faculty-specific names)
    if (props.facultyChapters && props.facultyChapters.length > 0) {
        return props.facultyChapters.map(facultyChapter => {
            const existingChapter = props.allChapters.find(ch => ch.chapter_number === facultyChapter.number);
            const outline = props.outlines?.find(o => o.chapter_number === facultyChapter.number);

            return {
                chapter_number: facultyChapter.number,
                title: facultyChapter.title, // Faculty-specific chapter name
                target_word_count: facultyChapter.word_count,
                status: existingChapter ? existingChapter.status : 'not_started',
                exists: !!existingChapter,
                id: existingChapter?.id,
                slug: existingChapter?.slug,
                outline: outline || null,
                facultyChapter: facultyChapter
            };
        });
    }

    // Priority 2: Use outlines if faculty chapters not available
    if (props.outlines && props.outlines.length > 0) {
        return props.outlines.map(outline => {
            const existingChapter = props.allChapters.find(ch => ch.chapter_number === outline.chapter_number);
            return {
                chapter_number: outline.chapter_number,
                title: outline.chapter_title,
                target_word_count: outline.target_word_count,
                status: existingChapter ? existingChapter.status : 'not_started',
                exists: !!existingChapter,
                id: existingChapter?.id,
                slug: existingChapter?.slug,
                outline: outline,
                facultyChapter: null
            };
        });
    }

    // Priority 3: Fallback to allChapters if neither available
    if (props.allChapters && props.allChapters.length > 0) {
        return props.allChapters.map(ch => ({
            chapter_number: ch.chapter_number,
            title: ch.title,
            target_word_count: 0, // Unknown
            status: ch.status,
            exists: true,
            id: ch.id,
            slug: ch.slug,
            outline: null,
            facultyChapter: null
        }));
    }

    return [];
});

const isChapterReadyForProgression = computed(() => {
    // Logic to determine if user can move to next chapter
    // For now, keeping it permissive for better UX, but can be strict based on requirements
    return true;
});

const nextChapter = computed(() => {
    const currentNum = props.currentChapter.chapter_number;
    const next = unifiedChapters.value.find(ch => ch.chapter_number === currentNum + 1);
    return next;
});

const previousChapter = computed(() => {
    const currentNum = props.currentChapter.chapter_number;
    const prev = unifiedChapters.value.find(ch => ch.chapter_number === currentNum - 1);
    return prev;
});

// Methods
const handleGoToChapter = (chapterNumber: number) => {
    emit('goToChapter', chapterNumber);
};

const handleGenerateNextChapter = () => {
    emit('generateNextChapter');
};

const handleDeleteChapter = (chapterId: number, chapterSlug: string) => {
    if (!chapterSlug) return;

    router.delete(route('chapters.destroy', {
        project: props.project.slug,
        chapter: chapterSlug
    }), {
        preserveState: true,
        preserveScroll: true,
        only: ['allChapters', 'chapter'],
        onSuccess: (page) => {
            const flashMessage = page.props.flash?.message || 'Chapter deleted successfully';
            toast.success(flashMessage);
        },
        onError: (errors) => {
            const errorMessage = typeof errors === 'string' ? errors : 'Failed to delete chapter.';
            toast.error(errorMessage);
        }
    });
};
</script>

<template>
    <div class="flex h-full flex-col bg-transparent">
        <div class="px-4 py-3 border-b border-border/40">
            <h2 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                <BookMarked class="h-3.5 w-3.5" />
                Table of Contents
            </h2>
            <div class="mt-2 flex items-center justify-between">
                <span class="text-xs font-medium text-foreground">{{ unifiedChapters.length }} Chapters</span>
                <Badge variant="secondary" class="text-[10px] h-5 px-1.5 font-normal">
                    {{ Math.round(currentWordCount / (targetWordCount || 1) * 100) }}% Complete
                </Badge>
            </div>
        </div>

        <ScrollArea class="flex-1">
            <div class="p-2 space-y-1">
                <div v-for="item in unifiedChapters" :key="item.chapter_number" class="group relative">
                    <button @click="handleGoToChapter(item.chapter_number)" :class="cn(
                        'flex w-full items-start gap-3 rounded-lg px-3 py-3 text-sm transition-all duration-200 border border-transparent',
                        item.chapter_number === currentChapter.chapter_number
                            ? 'bg-primary/5 border-primary/10 shadow-sm'
                            : 'hover:bg-muted/50 hover:border-border/50'
                    )">
                        <!-- Status Icon / Number -->
                        <div class="flex-shrink-0 mt-0.5">
                            <div v-if="item.status === 'approved' || item.status === 'completed'"
                                :class="cn(
                                    'h-6 w-6 rounded-md flex items-center justify-center',
                                    item.chapter_number === currentChapter.chapter_number
                                        ? 'bg-green-500 text-white shadow-sm ring-2 ring-green-500/20'
                                        : 'bg-green-500/10 text-green-600 border border-green-500/20'
                                )">
                                <CheckCircle class="h-3.5 w-3.5" />
                            </div>
                            <div v-else-if="item.chapter_number === currentChapter.chapter_number"
                                class="h-6 w-6 rounded-md bg-primary text-primary-foreground flex items-center justify-center shadow-sm ring-2 ring-primary/20">
                                <span class="text-xs font-bold">{{ item.chapter_number }}</span>
                            </div>
                            <div v-else-if="item.exists"
                                class="h-6 w-6 rounded-md bg-muted text-muted-foreground flex items-center justify-center border border-border">
                                <span class="text-xs font-medium">{{ item.chapter_number }}</span>
                            </div>
                            <div v-else class="h-6 w-6 rounded-md bg-muted/50 text-muted-foreground/30 flex items-center justify-center border border-border/50">
                                <Lock class="h-3 w-3" />
                            </div>
                        </div>

                        <!-- Title & Info -->
                        <div class="flex-1 text-left min-w-0">
                            <p :class="cn(
                                'truncate font-medium leading-tight',
                                item.chapter_number === currentChapter.chapter_number ? 'text-foreground' : 'text-muted-foreground group-hover:text-foreground'
                            )">
                                {{ item.title }}
                            </p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <span class="text-[10px] text-muted-foreground/70 flex items-center gap-1">
                                    <Target class="h-3 w-3" />
                                    {{ item.target_word_count }} words
                                </span>
                                <Badge v-if="!item.exists" variant="outline" class="h-4 px-1 text-[9px] border-dashed text-muted-foreground/60">
                                    Not Started
                                </Badge>
                            </div>
                        </div>
                    </button>

                    <!-- Actions (Delete) -->
                    <div v-if="item.exists && item.chapter_number !== currentChapter.chapter_number"
                        class="absolute right-2 top-3 opacity-0 group-hover:opacity-100 transition-opacity">
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button variant="ghost" size="icon"
                                    class="h-6 w-6 text-muted-foreground/50 hover:text-destructive hover:bg-destructive/10 rounded-md">
                                    <Trash2 class="h-3 w-3" />
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>Delete Chapter {{ item.chapter_number }}?</AlertDialogTitle>
                                    <AlertDialogDescription>
                                        This will permanently delete "{{ item.title }}" and all its content.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                        @click="handleDeleteChapter(item.id!, item.slug!)">
                                        Delete
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                </div>
            </div>
        </ScrollArea>

        <!-- Bottom Actions -->
        <div class="p-3 border-t border-border/40 bg-muted/5">
            <Button v-if="nextChapter && !nextChapter.exists" @click="handleGenerateNextChapter"
                class="w-full bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 transition-all h-9 text-xs font-medium"
                size="sm">
                <Plus class="mr-2 h-3.5 w-3.5" />
                Start Chapter {{ nextChapter.chapter_number }}
            </Button>

            <div v-else-if="nextChapter" class="flex gap-2">
                <Button variant="outline" class="w-full justify-between bg-background hover:bg-accent/50 h-9 text-xs" size="sm"
                    @click="handleGoToChapter(nextChapter.chapter_number)">
                    <span class="truncate max-w-[120px]">Next: {{ nextChapter.title }}</span>
                    <ChevronRight class="h-3.5 w-3.5 ml-2 flex-shrink-0" />
                </Button>
            </div>
        </div>
    </div>
</template>
