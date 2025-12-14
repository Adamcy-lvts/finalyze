<!-- /resources/js/components/chapter-editor/AISidebar.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import { Brain, Lightbulb, Quote, Sparkles, Target, Wand2, ChevronDown, Zap, RefreshCw, AlignLeft, Type, CheckCircle2, PenTool, MessageSquarePlus, Undo2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CitationHelper from './CitationHelper.vue';
import CustomPromptDialog from './CustomPromptDialog.vue';
import axios from 'axios';


interface Props {
    project: {
        mode: 'auto' | 'manual';
        id: number;
        slug: string;
    };
    chapter: {
        id: number;
        chapter_number: number;
        title: string;
    };
    isGenerating: boolean;
    selectedText: string;
    isLoadingSuggestions: boolean;
    showCitationHelper: boolean;
    chapterContent: string;
    cursorPosition?: number;
    currentWordCount?: number;
    targetWordCount?: number;
    canUndo?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    startStreamingGeneration: [type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand' | 'custom', options?: { section?: string, mode?: string, selectedText?: string, style?: string, customPrompt?: string }];
    getAISuggestions: [];
    'update:showCitationHelper': [value: boolean];
    'insert-citation': [citation: string];
    checkCitations: [];
    undoLastAction: [];
}>();

// Local state
const writingStyle = ref('Academic Formal');
const aiChapterAnalysis = ref<any>(null);
const isLoadingAISuggestions = ref(false);

// Loading states for individual actions
const actionLoadingStates = ref({
    expand: false,
    improve: false,
    rephrase: false,
    cite: false,
    'generate-full': false,
    'generate-section': false,
    custom: false
});

// Custom prompt dialog state - DISABLED
// const showCustomPromptDialog = ref(false);

// Handle custom prompt execution - DISABLED
// const handleCustomPromptExecute = (prompt: string) => {
//     actionLoadingStates.value.custom = true;
//     emit('startStreamingGeneration', 'custom', { customPrompt: prompt });
//     toast('Executing custom prompt...');
//     setTimeout(() => {
//         actionLoadingStates.value.custom = false;
//     }, 1000);
// };

// Computed
const hasValidChapter = computed(() => !!props.chapter?.id && !!props.chapter?.chapter_number);
const hasContent = computed(() => props.chapterContent && props.chapterContent.trim().length > 0);
const isEmptyChapter = computed(() => !hasContent.value && hasValidChapter.value);
const shouldShowAnalyzeButton = computed(() => !aiChapterAnalysis.value && hasValidChapter.value);


// Additional computed
const canImprove = computed(() => !props.isGenerating && !!props.chapterContent);
const canGetSuggestions = computed(() => !props.isLoadingSuggestions && !!props.selectedText);
const nextSection = computed(() => {
    if (aiChapterAnalysis.value && aiChapterAnalysis.value.section.name !== 'NONE') {
        return aiChapterAnalysis.value.section.name.toLowerCase().replace(/\s+/g, '_');
    }
    return getNextSection();
});
const canGenerateSection = computed(() => !props.isGenerating && !!props.chapter?.id);


// Simple fallback sections for when AI suggestions fail
const getFallbackSections = () => [
    { id: 'introduction', name: 'Introduction', description: 'Overview and objectives of this chapter' },
    { id: 'content', name: 'Main Content', description: 'Chapter main content' },
    { id: 'conclusion', name: 'Conclusion', description: 'Summary and transition to next chapter' }
];


// Methods
const handleGenerateAction = (type: 'progressive' | 'outline' | 'improve') => {
    emit('startStreamingGeneration', type);
};

const handleGetSuggestions = () => {
    emit('getAISuggestions');
};


const handleQuickAction = async (action: string, options?: any) => {
    // Prevent multiple simultaneous actions
    if (Object.values(actionLoadingStates.value).some(loading => loading)) {
        toast('Please wait for the current action to complete');
        return;
    }

    try {
        // Set loading state
        if (action in actionLoadingStates.value) {
            actionLoadingStates.value[action as keyof typeof actionLoadingStates.value] = true;
        }

        switch (action) {
            case 'expand':
                if (props.selectedText) {
                    emit('startStreamingGeneration', 'expand', {
                        selectedText: props.selectedText
                    });
                    toast('Expanding selected text...');
                } else {
                    toast.error('Please select text to expand');
                }
                break;
            case 'improve':
                emit('startStreamingGeneration', 'improve');
                toast('Improving chapter content...');
                break;
            case 'rephrase':
                if (props.selectedText) {
                    emit('startStreamingGeneration', 'rephrase', {
                        selectedText: props.selectedText,
                        style: writingStyle.value
                    });
                    toast('Rephrasing selected text...');
                } else {
                    toast.error('Please select text to rephrase');
                }
                break;
            case 'cite':
                emit('update:showCitationHelper', true);
                toast('Opening citation helper...');
                break;
            case 'generate-full':
                emit('startStreamingGeneration', 'progressive');
                toast('Generating complete chapter...');
                break;
            case 'generate-section':
                const section = options?.section || nextSection.value;
                emit('startStreamingGeneration', 'section', { section, mode: 'progressive' });
                toast(`Generating ${section} section...`);
                break;
            default:
                throw new Error(`Unknown action: ${action}`);
        }
    } catch (error) {
        console.error(`Quick action '${action}' failed:`, error);
        toast.error(`Failed to ${action.replace('-', ' ')}. Please try again.`);
    } finally {
        // Reset loading state after a delay to show feedback
        setTimeout(() => {
            if (action in actionLoadingStates.value) {
                actionLoadingStates.value[action as keyof typeof actionLoadingStates.value] = false;
            }
        }, 1000);
    }
};

const getNextSection = (): string => {
    if (!hasContent.value) return 'introduction';

    const content = props.chapterContent.toLowerCase();
    const fallbackSections = getFallbackSections();

    // Find the first missing section based on content analysis
    for (const section of fallbackSections) {
        const keywords = [section.name.toLowerCase(), section.id.replace('_', ' ')];
        const hasSection = keywords.some(keyword => content.includes(keyword));
        if (!hasSection) {
            return section.id;
        }
    }

    // If all sections exist, suggest conclusion if not present
    return 'conclusion';
};

const getSectionInfo = (sectionId: string) => {
    const fallbackSections = getFallbackSections();
    return fallbackSections.find(s => s.id === sectionId) || fallbackSections[0];
};

const getSubSectionNumber = (sectionId: string): string => {
    const fallbackSections = getFallbackSections();
    const sectionIndex = fallbackSections.findIndex(s => s.id === sectionId);
    const chapterNumber = props.chapter?.chapter_number ?? 1;
    if (sectionIndex === -1) return `${chapterNumber}.1`;
    return `${chapterNumber}.${sectionIndex + 1}`;
};





// Simple analyze chapter function with cache busting
const analyzeChapter = async () => {
    if (isLoadingAISuggestions.value || !hasValidChapter.value) return;

    isLoadingAISuggestions.value = true;
    const startTime = Date.now();

    try {
        // Add cache busting timestamp to force fresh analysis
        const { data } = await axios.post(`/api/projects/${props.project.id}/chapters/${props.chapter.id}/suggest-section?t=${Date.now()}`, {
            current_content: props.chapterContent || ''
        });

        // Ensure minimum loading time for better UX (minimum 800ms)
        const elapsedTime = Date.now() - startTime;
        const remainingTime = Math.max(0, 800 - elapsedTime);

        if (remainingTime > 0) {
            await new Promise(resolve => setTimeout(resolve, remainingTime));
        }

        if (data.success && data.analysis) {
            aiChapterAnalysis.value = data.analysis;

            // Override word count with live data from parent to ensure consistency
            if (aiChapterAnalysis.value.word_count_progress && props.currentWordCount !== undefined) {
                const target = props.targetWordCount || aiChapterAnalysis.value.word_count_progress.target;
                aiChapterAnalysis.value.word_count_progress.current = props.currentWordCount;
                aiChapterAnalysis.value.word_count_progress.percentage = target > 0
                    ? Math.round((props.currentWordCount / target) * 100 * 100) / 100
                    : 0;
            }

            console.log('✅ Fresh chapter analysis complete:', {
                status: data.analysis.status,
                completion: data.analysis.completion_percentage,
                wordProgress: aiChapterAnalysis.value.word_count_progress,
                usingLiveWordCount: props.currentWordCount !== undefined,
                usingStructuredData: data.structured || true,
                cached: false
            });

            toast.success('Chapter analyzed successfully');
        } else {
            toast.error('Failed to analyze chapter');
        }
    } catch (error) {
        console.error('Chapter analysis failed:', error);
        toast.error('Failed to analyze chapter. Please try again.');
    } finally {
        isLoadingAISuggestions.value = false;
    }
};

const handleInsertCitation = (citation: string) => {
    emit('insert-citation', citation);
};

// Watch for chapter changes
watch(
    () => props.chapter,
    (newChapter) => {
        if (!newChapter?.id) return;
        // Reset analysis when chapter changes
        aiChapterAnalysis.value = null;
    },
    { immediate: false }
);

// Watch for content changes
watch(
    () => props.chapterContent,
    (newContent) => {
        // If content becomes empty, reset analysis
        if (aiChapterAnalysis.value && (!newContent || newContent.trim().length === 0)) {
            aiChapterAnalysis.value = null;
        }
    },
    { immediate: false }
);

</script>

<template>
    <div class="flex flex-col gap-4 p-1">
        <!-- Enhanced AI Assistant Panel -->
        <Card class="border-none shadow-none bg-transparent">
            <CardHeader class="px-2 py-3">
                <CardTitle class="flex items-center gap-2 text-sm font-semibold text-foreground/80">
                    <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                        <Brain class="h-4 w-4" />
                    </div>
                    AI Assistant
                </CardTitle>
            </CardHeader>
            <CardContent class="px-2 space-y-6">
                <!-- Quick Actions -->
                <Collapsible :open="true" class="space-y-2">
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between text-xs font-medium text-muted-foreground hover:text-foreground transition-colors group">
                        <span class="flex items-center gap-2">
                            <Zap class="h-3.5 w-3.5 text-amber-500/70 group-hover:text-amber-500 transition-colors" />
                            QUICK ACTIONS
                        </span>
                        <ChevronDown
                            class="h-3 w-3 transition-transform duration-200 group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="space-y-3 pt-1">
                        <!-- Generation Controls -->
                        <div class="space-y-3">
                            <!-- Analyze / Start Button -->
                            <!-- <Button v-if="shouldShowAnalyzeButton" @click="analyzeChapter"
                                :disabled="isGenerating || isLoadingAISuggestions"
                                class="w-full h-auto flex-col gap-1.5 p-4 rounded-xl bg-gradient-to-br from-primary/10 via-primary/5 to-transparent border border-primary/10 hover:border-primary/30 text-primary hover:from-primary/15 transition-all duration-300 shadow-sm">
                                <div class="flex items-center gap-2">
                                    <Brain class="h-4 w-4" />
                                    <span class="font-semibold">
                                        {{ isLoadingAISuggestions ? 'Analyzing...' : (isEmptyChapter ? 'Start Writing' :
                                            'Analyze Chapter') }}
                                    </span>
                                </div>
                                <span class="text-[10px] opacity-70 font-normal">
                                    {{ isEmptyChapter ? 'Get AI suggestions to begin' : 'Generate next steps & suggestions' }}
                                </span>
                            </Button> -->

                            <!-- Next Section Generator -->
                            <Transition enter-active-class="transition-all duration-300 ease-out"
                                enter-from-class="opacity-0 scale-95 translate-y-1"
                                enter-to-class="opacity-100 scale-100 translate-y-0">
                                <div v-if="aiChapterAnalysis && aiChapterAnalysis.show_section_button"
                                    class="space-y-2">
                                    <Button @click="handleQuickAction('generate-section')"
                                        :disabled="!canGenerateSection || isLoadingAISuggestions || isGenerating || actionLoadingStates['generate-section']"
                                        variant="outline"
                                        class="w-full h-auto flex-col items-start gap-2 p-3 rounded-xl border-primary/20 bg-background/50 hover:bg-primary/5 hover:border-primary/40 transition-all duration-300 text-left group relative overflow-hidden">
                                        <div
                                            class="absolute inset-0 bg-gradient-to-r from-primary/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity" />

                                        <div class="flex items-center justify-between w-full relative z-10">
                                            <div class="flex items-center gap-2 text-primary">
                                                <PenTool class="h-3.5 w-3.5" />
                                                <span class="text-xs font-semibold">Write Next Section</span>
                                            </div>
                                            <RefreshCw v-if="actionLoadingStates['generate-section']"
                                                class="h-3 w-3 animate-spin text-primary" />
                                        </div>

                                        <div class="space-y-1 relative z-10">
                                            <div class="text-sm font-medium text-foreground">
                                                {{ aiChapterAnalysis?.section.name || getSectionInfo(nextSection).name
                                                }}
                                            </div>
                                            <div class="text-[10px] text-muted-foreground line-clamp-2 leading-relaxed">
                                                {{ aiChapterAnalysis?.section.description ||
                                                    getSectionInfo(nextSection).description }}
                                            </div>
                                        </div>
                                    </Button>
                                </div>
                            </Transition>

                            <!-- Full Chapter Generator -->
                            <Transition enter-active-class="transition-all duration-300 ease-out"
                                enter-from-class="opacity-0 scale-95 translate-y-1"
                                enter-to-class="opacity-100 scale-100 translate-y-0">
                                <Button v-if="aiChapterAnalysis && aiChapterAnalysis.show_full_chapter_button"
                                    @click="handleQuickAction('generate-full')"
                                    :disabled="isGenerating || isLoadingAISuggestions || actionLoadingStates['generate-full']"
                                    variant="ghost"
                                    class="w-full h-9 justify-start gap-2 px-3 rounded-lg text-xs text-muted-foreground hover:text-primary hover:bg-primary/5">
                                    <Wand2 class="h-3.5 w-3.5" />
                                    <span>Generate Complete Chapter</span>
                                </Button>
                            </Transition>

                            <!-- Completion Status -->
                            <Transition enter-active-class="transition-all duration-500 ease-out"
                                enter-from-class="opacity-0 scale-95 translate-y-2"
                                enter-to-class="opacity-100 scale-100 translate-y-0">
                                <div v-if="aiChapterAnalysis && aiChapterAnalysis.status === 'COMPLETE'"
                                    class="p-4 rounded-xl bg-green-500/10 border border-green-500/20 space-y-3">
                                    <div class="flex items-center gap-2 text-green-600 dark:text-green-400">
                                        <CheckCircle2 class="h-4 w-4" />
                                        <span class="text-sm font-semibold">Chapter Complete</span>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex justify-between text-[10px] font-medium text-muted-foreground">
                                            <span>Progress</span>
                                            <span>{{ aiChapterAnalysis.completion_percentage }}%</span>
                                        </div>
                                        <Progress :model-value="aiChapterAnalysis.completion_percentage"
                                            class="h-1.5" />
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <!-- Action Grid -->
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <Button @click="handleQuickAction('improve')"
                                :disabled="!canImprove || isGenerating || actionLoadingStates.improve" variant="outline"
                                class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300">
                                <div class="p-1.5 rounded-full bg-blue-500/10 text-blue-500">
                                    <Sparkles class="h-4 w-4" />
                                </div>
                                <span class="text-xs font-medium">Improve</span>
                            </Button>

                            <Button @click="handleQuickAction('expand')"
                                :disabled="!selectedText || isGenerating || actionLoadingStates.expand"
                                variant="outline"
                                class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300">
                                <div class="p-1.5 rounded-full bg-purple-500/10 text-purple-500">
                                    <AlignLeft class="h-4 w-4" />
                                </div>
                                <span class="text-xs font-medium">Expand</span>
                            </Button>

                            <Button @click="handleQuickAction('cite')"
                                :disabled="isGenerating || actionLoadingStates.cite" variant="outline"
                                class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300">
                                <div class="p-1.5 rounded-full bg-orange-500/10 text-orange-500">
                                    <Quote class="h-4 w-4" />
                                </div>
                                <span class="text-xs font-medium">Cite</span>
                            </Button>

                            <Button @click="handleQuickAction('rephrase')"
                                :disabled="!selectedText || isGenerating || actionLoadingStates.rephrase"
                                variant="outline"
                                class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300">
                                <div class="p-1.5 rounded-full bg-emerald-500/10 text-emerald-500">
                                    <Type class="h-4 w-4" />
                                </div>
                                <span class="text-xs font-medium">Rephrase</span>
                            </Button>

                            <!-- Custom Prompt Button - DISABLED
                            <Button @click="showCustomPromptDialog = true"
                                :disabled="isGenerating || actionLoadingStates.custom"
                                variant="outline"
                                class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-accent hover:border-accent transition-all duration-300">
                                <div class="p-1.5 rounded-full bg-gradient-to-r from-purple-500/10 to-indigo-500/10 text-purple-500">
                                    <MessageSquarePlus class="h-4 w-4" />
                                </div>
                                <span class="text-xs font-medium">Custom</span>
                            </Button>
                            -->

                            <!-- Undo Button -->
                            <Button v-if="canUndo" 
                                @click="emit('undoLastAction')"
                                variant="outline"
                                class="h-20 flex-col gap-2 rounded-xl border-border/50 bg-background/50 hover:bg-amber-500/10 hover:border-amber-500/30 transition-all duration-300 col-span-2">
                                <div class="p-1.5 rounded-full bg-amber-500/10 text-amber-500">
                                    <Undo2 class="h-4 w-4" />
                                </div>
                                <span class="text-xs font-medium">Undo Last AI Change</span>
                            </Button>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                <Separator class="bg-border/50" />

                <!-- Writing Style -->
                <Collapsible :open="true" class="space-y-2">
                    <CollapsibleTrigger
                        class="flex w-full items-center justify-between text-xs font-medium text-muted-foreground hover:text-foreground transition-colors group">
                        <span class="flex items-center gap-2">
                            <PenTool
                                class="h-3.5 w-3.5 text-indigo-500/70 group-hover:text-indigo-500 transition-colors" />
                            WRITING STYLE
                        </span>
                        <ChevronDown
                            class="h-3 w-3 transition-transform duration-200 group-data-[state=open]:rotate-180" />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="pt-1">
                        <div class="p-1 rounded-xl bg-muted/30 border border-border/50">
                            <select v-model="writingStyle"
                                class="w-full bg-transparent border-none text-xs font-medium focus:ring-0 cursor-pointer py-1.5 px-2">
                                <option value="Academic Formal">Academic Formal</option>
                                <option value="Academic Casual">Academic Casual</option>
                                <option value="Technical">Technical</option>
                                <option value="Analytical">Analytical</option>
                                <option value="Research-Heavy">Research-Heavy</option>
                            </select>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>

        <!-- Citation Helper -->
        <CitationHelper 
            :show-citation-helper="showCitationHelper" 
            :chapter-content="chapterContent"
            :chapter-id="chapter?.id || 0"
            @update:show-citation-helper="emit('update:showCitationHelper', $event)"
            @insert-citation="handleInsertCitation" 
        />

        <!-- Custom Prompt Dialog - DISABLED
        <CustomPromptDialog 
            v-model:open="showCustomPromptDialog"
            :selected-text="selectedText"
            :chapter-content="chapterContent"
            :project-title="project?.slug || ''"
            :chapter-title="chapter?.title || ''"
            @execute-prompt="handleCustomPromptExecute"
        />
        -->
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
