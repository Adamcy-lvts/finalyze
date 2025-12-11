import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import type { Ref } from 'vue';
import type { ChapterEditorProps, Chapter } from '@/types/chapter-editor';
import { useWordBalance } from '@/composables/useWordBalance';

interface ChapterNavigationDeps {
    props: ChapterEditorProps;
    allChapters: Chapter[];
    targetWordCount: Ref<number>;
    estimates: ReturnType<typeof useWordBalance>['estimates'];
    ensureBalance: (requiredWords: number, action: string) => boolean;
    save: () => Promise<void>;
}

export function useChapterNavigation({ props, allChapters, targetWordCount, estimates, ensureBalance, save }: ChapterNavigationDeps) {
    const generateNextChapter = async () => {
        const requiredWords = estimates.chapter(targetWordCount.value || 0);
        if (!ensureBalance(requiredWords, 'generate the next chapter with AI')) {
            return;
        }

        await save();

        const existingChapterNumbers = allChapters.map(ch => ch.chapter_number).sort((a, b) => a - b);
        const currentIndex = existingChapterNumbers.indexOf(props.chapter.chapter_number);

        let nextChapterNumber;
        if (currentIndex === -1 || currentIndex === existingChapterNumbers.length - 1) {
            const maxChapterNumber = Math.max(...existingChapterNumbers);
            nextChapterNumber = maxChapterNumber + 1;
        } else {
            const nextExistingChapter = existingChapterNumbers[currentIndex + 1];
            if (nextExistingChapter === props.chapter.chapter_number + 1) {
                nextChapterNumber = nextExistingChapter;
            } else {
                nextChapterNumber = props.chapter.chapter_number + 1;
            }
        }

        try {
            const url = route('chapters.write', {
                project: props.project.slug,
                chapter: nextChapterNumber,
            });

            const generateUrl = new URL(url, window.location.origin);
            generateUrl.searchParams.set('ai_generate', 'true');
            generateUrl.searchParams.set('generation_type', 'progressive');

            router.visit(generateUrl.toString());
        } catch (error) {
            console.error('❌ Error generating next chapter:', error);
        }
    };

    return {
        generateNextChapter,
    };
}
