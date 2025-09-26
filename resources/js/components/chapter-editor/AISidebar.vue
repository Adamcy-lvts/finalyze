<!-- /resources/js/components/chapter-editor/AISidebar.vue -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Brain, Lightbulb, Quote, Sparkles, Target, Wand2, ChevronDown, Zap, RefreshCw, AlignLeft, Type, CheckCircle2, PenTool } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CitationHelper from './CitationHelper.vue';
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
    currentWordCount?: number; // Add current word count from parent
    targetWordCount?: number;  // Add target word count from parent
}

const props = defineProps<Props>();

const emit = defineEmits<{
    startStreamingGeneration: [type: 'progressive' | 'outline' | 'improve' | 'section' | 'rephrase' | 'expand', options?: { section?: string, mode?: string, selectedText?: string, style?: string }];
    getAISuggestions: [];
    'update:showCitationHelper': [value: boolean];
    'insert-citation': [citation: string];
    checkCitations: [];
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
    'generate-section': false
});

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

// Legacy method for backward compatibility
const generateContextualSuggestions = async () => {
    if (!props.isGenerating) {
        await generateSuggestions(true);
    }
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
    <div class="space-y-4 sm:space-y-6">

        <!-- Enhanced AI Assistant Panel -->
        <Card v-if="project.mode === 'auto'" class="border-[0.5px] border-border/50">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <Brain class="h-4 w-4 text-blue-500" />
                    AI Assistant
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Quick Actions -->
                <Collapsible :open="true">
                    <CollapsibleTrigger class="flex w-full items-center justify-between text-sm font-medium">
                        <span class="flex items-center gap-2">
                            <Zap class="h-3 w-3" />
                            Quick Actions
                        </span>
                        <ChevronDown class="h-3 w-3" />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="space-y-3 pt-2">
                        <!-- Progressive Generation Options -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-muted-foreground">Generation Mode</span>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-2">
                                <!-- Manual AI Analysis Button -->
                                <Button
                                    v-if="shouldShowAnalyzeButton"
                                    @click="analyzeChapter"
                                    :disabled="isGenerating || isLoadingAISuggestions"
                                    size="sm"
                                    variant="default"
                                    class="h-auto w-full flex-col gap-1 p-3 bg-accent text-accent-foreground hover:bg-accent/80 cursor-pointer transition-colors border-accent/50 hover:border-accent"
                                    :aria-label="isLoadingAISuggestions ? 'Analyzing chapter content...' : (isEmptyChapter ? 'Get AI suggestions to start writing' : 'Analyze chapter to get AI-powered suggestions')"
                                >
                                    <div class="flex items-center justify-center gap-2 w-full">
                                        <Brain class="h-4 w-4 flex-shrink-0" />
                                        <RefreshCw v-if="isLoadingAISuggestions" class="h-3 w-3 animate-spin flex-shrink-0" />
                                        <span class="text-sm font-medium text-center">
                                            <template v-if="isLoadingAISuggestions">
                                                Analyzing...
                                            </template>
                                            <template v-else-if="isEmptyChapter">
                                                Start Writing with AI
                                            </template>
                                            <template v-else>
                                                Analyze Chapter
                                            </template>
                                        </span>
                                    </div>
                                    <span class="text-xs text-muted-foreground text-center">
                                        <template v-if="isEmptyChapter">
                                            Get AI suggestions to begin your chapter
                                        </template>
                                        <template v-else>
                                            Get AI-powered suggestions for next steps
                                        </template>
                                    </span>
                                </Button>
                                
                                <!-- Generate Next Sub-Section -->
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-out"
                                    enter-from-class="opacity-0 scale-95 translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                >
                                    <Button
                                        v-if="aiChapterAnalysis && aiChapterAnalysis.show_section_button"
                                    @click="handleQuickAction('generate-section')"
                                    :disabled="!canGenerateSection || isLoadingAISuggestions || isGenerating || actionLoadingStates['generate-section']"
                                    size="sm"
                                    variant="outline"
                                    class="h-auto w-full flex-col gap-1 p-3 bg-primary/10 text-primary hover:bg-primary/20 cursor-pointer transition-colors border-primary/30 hover:border-primary/50 min-h-[4rem] overflow-hidden"
                                    :aria-label="actionLoadingStates['generate-section'] ? 'Generating section...' : `Generate ${aiChapterAnalysis?.section.name || 'next section'}`"
                                >
                                    <div class="flex items-center justify-center gap-2 w-full">
                                        <Brain class="h-4 w-4 flex-shrink-0" />
                                        <RefreshCw v-if="isLoadingAISuggestions || actionLoadingStates['generate-section']" class="h-3 w-3 animate-spin flex-shrink-0" />
                                        <span class="text-sm font-medium text-center break-words line-clamp-2 leading-tight">
                                            <template v-if="isLoadingAISuggestions">
                                                Analyzing chapter...
                                            </template>
                                            <template v-else-if="actionLoadingStates['generate-section']">
                                                Generating section...
                                            </template>
                                            <template v-else-if="aiChapterAnalysis?.section.name && aiChapterAnalysis.section.name !== 'NONE'">
                                                Generate {{ aiChapterAnalysis.section.number }} {{ aiChapterAnalysis.section.name }}
                                            </template>
                                            <template v-else>
                                                Generate {{ hasValidChapter ? getSubSectionNumber(nextSection) : '1.1' }} {{ getSectionInfo(nextSection).name }}
                                            </template>
                                        </span>
                                    </div>
                                    <span class="text-xs text-muted-foreground text-center break-words line-clamp-2 w-full">
                                        <template v-if="isLoadingAISuggestions">
                                            AI is determining the best next section...
                                        </template>
                                        <template v-else-if="actionLoadingStates['generate-section']">
                                            Creating section content...
                                        </template>
                                        <template v-else-if="aiChapterAnalysis?.section.description && aiChapterAnalysis.section.description !== 'NONE'">
                                            <div>{{ aiChapterAnalysis.section.description }}</div>
                                            <div v-if="aiChapterAnalysis.completion_percentage !== undefined" class="mt-1 font-medium">
                                                Progress: {{ aiChapterAnalysis.completion_percentage }}% complete
                                            </div>
                                        </template>
                                        <template v-else>
                                            {{ getSectionInfo(nextSection).description }}
                                        </template>
                                    </span>
                                    </Button>
                                </Transition>
                                
                                <!-- Generate Full Chapter -->
                                <Transition
                                    enter-active-class="transition-all duration-300 ease-out"
                                    enter-from-class="opacity-0 scale-95 translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                >
                                    <Button
                                        v-if="aiChapterAnalysis && aiChapterAnalysis.show_full_chapter_button"
                                    @click="handleQuickAction('generate-full')"
                                    :disabled="isGenerating || isLoadingAISuggestions || actionLoadingStates['generate-full']"
                                    size="sm"
                                    variant="outline"
                                    class="h-auto flex-col gap-1 p-2 hover:bg-muted cursor-pointer transition-colors"
                                    :aria-label="actionLoadingStates['generate-full'] ? 'Generating full chapter...' : 'Generate complete chapter content'"
                                >
                                    <RefreshCw v-if="actionLoadingStates['generate-full']" class="h-3 w-3 animate-spin" />
                                    <Wand2 v-else class="h-3 w-3" />
                                    <span class="text-xs">{{ actionLoadingStates['generate-full'] ? 'Generating...' : 'Generate Full Chapter' }}</span>
                                    </Button>
                                </Transition>
                                
                                <!-- Chapter Complete Message -->
                                <Transition
                                    enter-active-class="transition-all duration-500 ease-out"
                                    enter-from-class="opacity-0 scale-95 translate-y-2"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                >
                                    <div 
                                        v-if="aiChapterAnalysis && aiChapterAnalysis.status === 'COMPLETE'"
                                        class="space-y-3 p-3 bg-green-50 border border-green-200 rounded-lg dark:bg-green-950/30 dark:border-green-800"
                                    >
                                    <!-- Success Header -->
                                    <div class="flex items-center justify-center gap-2">
                                        <CheckCircle2 class="h-4 w-4 text-green-600 flex-shrink-0" />
                                        <div class="text-center">
                                            <div class="text-sm font-medium text-green-800 dark:text-green-300">Chapter Complete!</div>
                                            <div class="text-xs text-green-600 dark:text-green-400">All required sections completed successfully</div>
                                        </div>
                                    </div>

                                    <!-- Structured Progress Info -->
                                    <div v-if="aiChapterAnalysis.completion_percentage !== undefined" class="space-y-2">
                                        <!-- Section Progress -->
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-green-700 dark:text-green-400">Section Progress</span>
                                            <span class="font-medium text-green-800 dark:text-green-300">{{ aiChapterAnalysis.completion_percentage }}%</span>
                                        </div>
                                        
                                        <!-- Progress Bar -->
                                        <div class="w-full bg-green-100 rounded-full h-1.5 dark:bg-green-900/50">
                                            <div 
                                                class="bg-green-600 h-1.5 rounded-full transition-all duration-300"
                                                :style="`width: ${aiChapterAnalysis.completion_percentage}%`"
                                            ></div>
                                        </div>

                                        <!-- Word Count Progress -->
                                        <div v-if="aiChapterAnalysis.word_count_progress" class="flex items-center justify-between text-xs">
                                            <span class="text-green-700 dark:text-green-400">Word Count</span>
                                            <span class="font-medium text-green-800 dark:text-green-300">
                                                {{ aiChapterAnalysis.word_count_progress.current }}/{{ aiChapterAnalysis.word_count_progress.target }} words
                                                ({{ Math.round(aiChapterAnalysis.word_count_progress.percentage || 0) }}%)
                                            </span>
                                        </div>
                                    </div>
                                    </div>
                                </Transition>
                                
                            </div>
                        </div>
                        
                        <!-- Quick Actions -->
                        <Separator />
                        <div class="grid grid-cols-2 gap-2">
                            <Button
                                @click="handleQuickAction('improve')"
                                :disabled="!canImprove || isGenerating || actionLoadingStates.improve"
                                size="sm"
                                variant="outline"
                                class="h-auto flex-col gap-1 p-2 hover:bg-muted cursor-pointer transition-colors"
                                :aria-label="actionLoadingStates.improve ? 'Improving content...' : 'Improve chapter content'"
                            >
                                <RefreshCw v-if="actionLoadingStates.improve" class="h-3 w-3 animate-spin" />
                                <Sparkles v-else class="h-3 w-3" />
                                <span class="text-xs">{{ actionLoadingStates.improve ? 'Improving...' : 'Improve' }}</span>
                            </Button>
                            
                            <Button
                                @click="handleQuickAction('expand')"
                                :disabled="!selectedText || isGenerating || actionLoadingStates.expand"
                                size="sm"
                                variant="outline"
                                class="h-auto flex-col gap-1 p-2 hover:bg-muted cursor-pointer transition-colors"
                                :aria-label="actionLoadingStates.expand ? 'Expanding selected text...' : 'Expand selected text'"
                            >
                                <RefreshCw v-if="actionLoadingStates.expand" class="h-3 w-3 animate-spin" />
                                <AlignLeft v-else class="h-3 w-3" />
                                <span class="text-xs">{{ actionLoadingStates.expand ? 'Expanding...' : 'Expand' }}</span>
                            </Button>
                            
                            <Button
                                @click="handleQuickAction('cite')"
                                :disabled="isGenerating || actionLoadingStates.cite"
                                size="sm"
                                variant="outline"
                                class="h-auto flex-col gap-1 p-2 hover:bg-muted cursor-pointer transition-colors"
                                :aria-label="actionLoadingStates.cite ? 'Opening citation helper...' : 'Add citations to content'"
                            >
                                <RefreshCw v-if="actionLoadingStates.cite" class="h-3 w-3 animate-spin" />
                                <Quote v-else class="h-3 w-3" />
                                <span class="text-xs">{{ actionLoadingStates.cite ? 'Opening...' : 'Cite' }}</span>
                            </Button>
                            
                            <Button
                                @click="handleQuickAction('rephrase')"
                                :disabled="!selectedText || isGenerating || actionLoadingStates.rephrase"
                                size="sm"
                                variant="outline"
                                class="h-auto flex-col gap-1 p-2 hover:bg-muted cursor-pointer transition-colors"
                                :aria-label="actionLoadingStates.rephrase ? 'Rephrasing selected text...' : 'Rephrase selected text'"
                            >
                                <RefreshCw v-if="actionLoadingStates.rephrase" class="h-3 w-3 animate-spin" />
                                <Type v-else class="h-3 w-3" />
                                <span class="text-xs">{{ actionLoadingStates.rephrase ? 'Rephrasing...' : 'Rephrase' }}</span>
                            </Button>
                        </div>
                        
                        <!-- Traditional Actions -->
                        <Separator />
                        <div class="space-y-1">
                            <Button
                                @click="handleGetSuggestions"
                                :disabled="!canGetSuggestions"
                                size="sm"
                                class="w-full justify-start text-xs hover:bg-muted cursor-pointer transition-colors"
                                variant="ghost"
                                aria-label="Get AI suggestions for selected text"
                            >
                                <Lightbulb class="mr-2 h-3 w-3" />
                                Get Suggestions
                            </Button>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
                
                <!-- Smart Suggestions (Temporarily Disabled) -->
                <Collapsible :open="false" class="opacity-50">
                    <CollapsibleTrigger class="flex w-full items-center justify-between text-sm font-medium cursor-not-allowed">
                        <span class="flex items-center gap-2">
                            <Target class="h-3 w-3" />
                            Smart Suggestions (Coming Soon)
                        </span>
                    </CollapsibleTrigger>
                    <CollapsibleContent class="space-y-2 pt-2">
                        <div class="text-center py-4">
                            <Target class="h-8 w-8 mx-auto text-muted-foreground/30" />
                            <p class="text-xs text-muted-foreground mt-2">Smart suggestions are being improved and will be available soon.</p>
                            <p class="text-xs text-muted-foreground/70 mt-1">Use the "Analyze Chapter" button above for chapter analysis.</p>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
                
                <!-- Enhanced Writing Style Settings -->
                <Collapsible :open="true">
                    <CollapsibleTrigger class="flex w-full items-center justify-between text-sm font-medium">
                        <span class="flex items-center gap-2">
                            <Type class="h-3 w-3" />
                            Writing Style
                        </span>
                        <ChevronDown class="h-3 w-3" />
                    </CollapsibleTrigger>
                    <CollapsibleContent class="space-y-3 pt-2">
                        <div class="space-y-2">
                            <Label class="text-xs font-medium">Academic Tone</Label>
                            <select v-model="writingStyle" class="w-full rounded-md border border-input bg-background px-2 py-1.5 text-xs focus:border-ring focus:outline-none focus:ring-1 focus:ring-ring">
                                <option value="Academic Formal">Academic Formal</option>
                                <option value="Academic Casual">Academic Casual</option>
                                <option value="Technical">Technical</option>
                                <option value="Analytical">Analytical</option>
                                <option value="Research-Heavy">Research-Heavy</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                            <span class="flex items-center gap-1">
                                <CheckCircle2 class="h-3 w-3 text-green-500" />
                                Style: {{ writingStyle }}
                            </span>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </CardContent>
        </Card>

        <!-- Citation Helper -->
        <CitationHelper 
            :show-citation-helper="showCitationHelper"
            :chapter-content="chapterContent"
            @update:show-citation-helper="emit('update:showCitationHelper', $event)"
            @insert-citation="handleInsertCitation"
        />

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
