import type { Ref, ComputedRef } from 'vue';
import type { Chapter } from './chapter-editor';

export interface ChapterEditorPaperCardContext {
    chapter: Chapter;
    chapterTitle: Ref<string>;
    chapterContent: Ref<string>;
    showPresentationMode: Ref<boolean>;
    togglePresentationMode: () => void;
    isStreamingMode: Ref<boolean>;
    isGenerating: Ref<boolean>;
    generationProgress: Ref<string>;
    generationPercentage: Ref<number>;
    generationPhase: Ref<string>;
    aiChapterAnalysis: any;
    nextSection: string | null;
    getSectionInfo: (sectionId: string | null) => { name: string; description: string };
    writingStats: any;
    latestAnalysis: Ref<any>;
    isAnalyzing: Ref<boolean>;
    writingQualityScore: Ref<number>;
    isSaving: Ref<boolean>;
    isValid: ComputedRef<boolean>;
    save: (autoSave?: boolean) => Promise<void>;
    goToBulkAnalysis: () => void;
    markAsComplete: () => Promise<void>;
    richTextEditor: Ref<{ editor?: any } | null>;
    richTextEditorFullscreen: Ref<{ editor?: any } | null>;
    selectedText: Ref<string>;
    setSelectedText: (value: string) => void;
}
