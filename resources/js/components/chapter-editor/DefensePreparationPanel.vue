<!-- DefensePreparationPanel.vue -->
<script setup lang="ts">
import { computed, ref, watch, withDefaults } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import {
    AlertCircle,
    ChevronDown,
    Download,
    HelpCircle,
    Lightbulb,
    Loader2,
    RefreshCw,
    Shield,
    ThumbsDown,
    ThumbsUp,
    Wand2,
    X
} from 'lucide-vue-next';

interface DefenseQuestion {
    id: number;
    question: string;
    suggested_answer: string;
    key_points: string[];
    difficulty: 'easy' | 'medium' | 'hard';
    category: string;
    times_viewed: number;
    user_marked_helpful: boolean | null;
}

interface DefenseWatcher {
    meetsThreshold: boolean;
    shouldShowProgress: boolean;
    progressPercentage: number;
    wordsRemaining: number;
    hasTriggeredGeneration: boolean;
    threshold: number;
    statusMessage: string;
}

interface Props {
    showDefensePrep?: boolean;
    questions: DefenseQuestion[];
    isLoading: boolean;
    isGenerating: boolean;
    chapterContext: {
        chapter_number: number;
        chapter_title: string;
        word_count: number;
    };
    defenseWatcher?: DefenseWatcher;
}

const props = withDefaults(defineProps<Props>(), {
    showDefensePrep: true
});

const emit = defineEmits<{
    'update:showDefensePrep': [value: boolean];
    'generate-more': [];
    'refresh': [];
    'mark-helpful': [questionId: number, helpful: boolean];
    'hide-question': [questionId: number];
}>();

// Local state
const selectedDifficulty = ref<'all' | 'easy' | 'medium' | 'hard'>('all');
const selectedCategory = ref<string>('all');
const expandedQuestion = ref<number | null>(null);

// Computed
const filteredQuestions = computed(() => {
    let filtered = [...props.questions];

    if (selectedDifficulty.value !== 'all') {
        filtered = filtered.filter(q => q.difficulty === selectedDifficulty.value);
    }

    if (selectedCategory.value !== 'all') {
        filtered = filtered.filter(q => q.category === selectedCategory.value);
    }

    return filtered.slice(0, 5); // Show max 5 questions
});

const categories = computed(() => {
    const cats = new Set(props.questions.map(q => q.category));
    return Array.from(cats);
});

const difficultyColors = {
    easy: 'text-green-700 bg-green-100 border-green-300 hover:bg-green-200 transition-colors',
    medium: 'text-amber-700 bg-amber-100 border-amber-300 hover:bg-amber-200 transition-colors',
    hard: 'text-red-700 bg-red-100 border-red-300 hover:bg-red-200 transition-colors'
};

// Methods
const handleToggle = (isOpen: boolean) => {
    emit('update:showDefensePrep', isOpen);
};

const toggleQuestionExpand = (questionId: number) => {
    expandedQuestion.value = expandedQuestion.value === questionId ? null : questionId;
};

const exportQuestions = () => {
    const content = filteredQuestions.value.map((q, i) =>
        `Q${i + 1}: ${q.question}\n\nSuggested Answer: ${q.suggested_answer}\n\nKey Points:\n${q.key_points.map(p => `• ${p}`).join('\n')}\n\n---\n`
    ).join('\n');

    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `defense-questions-chapter-${props.chapterContext.chapter_number}.txt`;
    a.click();
    URL.revokeObjectURL(url);
};

// Auto-collapse when questions update
watch(() => props.questions, () => {
    expandedQuestion.value = null;
});
</script>

<template>
    <Card class="border-[0.5px] border-border/50">
        <Collapsible :open="showDefensePrep" @update:open="handleToggle">
            <CollapsibleTrigger class="w-full">
                <CardHeader class="pb-3 transition-colors hover:bg-muted/30">
                    <CardTitle class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2">
                            <Shield class="h-4 w-4 text-muted-foreground" />
                            Defense Preparation
                            <Badge variant="secondary" class="ml-1 text-xs">
                                Ch. {{ chapterContext.chapter_number }}
                            </Badge>
                            <Badge v-if="questions.length" variant="outline" class="ml-1 text-xs">
                                {{ questions.length }} questions
                            </Badge>
                        </span>
                        <ChevronDown :class="[
                            'h-4 w-4 text-muted-foreground transition-transform',
                            showDefensePrep ? 'rotate-180' : ''
                        ]" />
                    </CardTitle>
                </CardHeader>
            </CollapsibleTrigger>

            <CollapsibleContent>
                <CardContent class="space-y-4 pt-0">
                    <!-- Info Footer -->
                    <div class="space-y-3 p-3 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-950/30 dark:border-blue-800">
                        <!-- Info Header -->
                        <div class="flex items-center justify-center gap-2">
                            <Shield class="h-4 w-4 text-blue-600 flex-shrink-0" />
                            <div class="text-center">
                                <div class="text-sm font-medium text-blue-800 dark:text-blue-300">Smart Defense Preparation</div>
                                <div class="text-xs text-blue-600 dark:text-blue-400">Questions are AI-generated and tailored to Chapter {{ chapterContext.chapter_number }} content.</div>
                            </div>
                        </div>
                        <!-- Additional Info -->
                        <div class="text-center">
                            <p class="text-xs text-blue-700 dark:text-blue-300">💡 Click any question to reveal suggested answers and key talking points.</p>
                        </div>
                    </div>

                    <!-- Loading State (First Load) -->
                    <div v-if="isLoading && !questions.length" class="py-8">
                        <div class="flex flex-col items-center justify-center space-y-2">
                            <Loader2 class="h-8 w-8 animate-spin text-muted-foreground" />
                            <p class="text-xs text-muted-foreground text-center">
                                Analyzing Chapter {{ chapterContext.chapter_number }} content<br />
                                to generate defense questions...
                            </p>
                        </div>
                    </div>

                    <!-- Questions Display -->
                    <template v-else-if="questions.length">
                        <!-- Filters -->
                        <div class="space-y-3">
                            <!-- Difficulty Filter -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <HelpCircle class="h-3 w-3" />
                                    Filter by Difficulty
                                </h4>
                                <div class="flex gap-1 flex-wrap">
                                    <Badge v-for="level in (['all', 'easy', 'medium', 'hard'] as const)" :key="level"
                                        :variant="selectedDifficulty === level ? 'default' : 'secondary'"
                                        @click="selectedDifficulty = level" 
                                        class="cursor-pointer text-xs capitalize hover:scale-105 transition-all duration-200 hover:shadow-sm">
                                        {{ level === 'all' ? 'All Levels' : level }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Category Filter (if multiple categories) -->
                            <div v-if="categories.length > 1" class="space-y-2">
                                <h4 class="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Lightbulb class="h-3 w-3" />
                                    Filter by Topic
                                </h4>
                                <div class="flex flex-wrap gap-1">
                                    <Badge variant="secondary" @click="selectedCategory = 'all'"
                                        :class="selectedCategory === 'all' ? 'bg-primary text-primary-foreground shadow-sm' : 'hover:bg-muted'"
                                        class="cursor-pointer text-xs hover:scale-105 transition-all duration-200">
                                        All Topics
                                    </Badge>
                                    <Badge v-for="cat in categories" :key="cat" variant="secondary"
                                        @click="selectedCategory = cat"
                                        :class="selectedCategory === cat ? 'bg-primary text-primary-foreground shadow-sm' : 'hover:bg-muted'"
                                        class="cursor-pointer text-xs capitalize hover:scale-105 transition-all duration-200">
                                        {{ cat }}
                                    </Badge>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <!-- Questions List -->
                        <ScrollArea class="h-[420px]">
                            <div class="space-y-3 pr-3">
                                <div v-for="(question, index) in filteredQuestions" :key="question.id"
                                    class="group relative">
                                    <Alert class="cursor-pointer transition-all duration-200 hover:shadow-md hover:border-primary/50"
                                        :class="[
                                            expandedQuestion === question.id 
                                                ? 'ring-2 ring-primary shadow-lg border-primary/50 bg-primary/5' 
                                                : 'hover:bg-muted/30'
                                        ]"
                                        @click="toggleQuestionExpand(question.id)">
                                        <div class="absolute top-2 left-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                            <HelpCircle class="h-3 w-3" />
                                        </div>
                                        <AlertDescription class="ml-5 text-sm">
                                            <!-- Question Header -->
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex-1 space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-xs font-bold text-primary bg-primary/10 px-2 py-0.5 rounded-full">
                                                            Q{{ index + 1 }}
                                                        </span>
                                                        <Badge :class="difficultyColors[question.difficulty]"
                                                            class="text-xs px-2 py-0.5 font-medium border cursor-pointer">
                                                            {{ question.difficulty }}
                                                        </Badge>
                                                        <Badge variant="outline" class="text-xs px-2 py-0.5 bg-muted/50">
                                                            {{ question.category }}
                                                        </Badge>
                                                    </div>
                                                    <p class="font-medium text-foreground leading-relaxed">{{ question.question }}</p>
                                                </div>

                                                <!-- Question Actions -->
                                                <div class="flex items-center gap-1 opacity-70 group-hover:opacity-100 transition-opacity">
                                                    <Button @click.stop="emit('mark-helpful', question.id, true)"
                                                        variant="ghost" size="icon" class="h-7 w-7 hover:scale-110 transition-transform"
                                                        :class="question.user_marked_helpful === true 
                                                            ? 'text-green-600 bg-green-100 hover:bg-green-200' 
                                                            : 'hover:text-green-600 hover:bg-green-50'">
                                                        <ThumbsUp class="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button @click.stop="emit('mark-helpful', question.id, false)"
                                                        variant="ghost" size="icon" class="h-7 w-7 hover:scale-110 transition-transform"
                                                        :class="question.user_marked_helpful === false 
                                                            ? 'text-red-600 bg-red-100 hover:bg-red-200' 
                                                            : 'hover:text-red-600 hover:bg-red-50'">
                                                        <ThumbsDown class="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button @click.stop="emit('hide-question', question.id)"
                                                        variant="ghost" size="icon" class="h-7 w-7 hover:scale-110 transition-transform hover:text-red-500 hover:bg-red-50">
                                                        <X class="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </div>

                                            <!-- Expanded Content -->
                                            <div v-if="expandedQuestion === question.id"
                                                class="mt-4 space-y-4 border-t border-dashed pt-4 animate-in slide-in-from-top-2 duration-200">
                                                <div class="bg-muted/30 rounded-lg p-3 space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <Lightbulb class="h-4 w-4 text-amber-600" />
                                                        <strong class="text-sm font-semibold text-foreground">Suggested Approach</strong>
                                                    </div>
                                                    <p class="text-sm text-muted-foreground leading-relaxed pl-6">
                                                        {{ question.suggested_answer }}
                                                    </p>
                                                </div>

                                                <div v-if="question.key_points.length" class="bg-primary/5 rounded-lg p-3 space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <AlertCircle class="h-4 w-4 text-primary" />
                                                        <strong class="text-sm font-semibold text-foreground">Key Points to Cover</strong>
                                                    </div>
                                                    <ul class="space-y-1.5 pl-6">
                                                        <li v-for="point in question.key_points" :key="point"
                                                            class="text-sm text-muted-foreground flex items-start gap-2">
                                                            <span class="text-primary font-bold text-xs mt-1">•</span>
                                                            <span>{{ point }}</span>
                                                        </li>
                                                    </ul>
                                                </div>

                                                <div class="flex items-center justify-between text-xs text-muted-foreground/70 border-t border-dashed pt-2">
                                                    <span class="flex items-center gap-1">
                                                        <RefreshCw class="h-3 w-3" />
                                                        Viewed {{ question.times_viewed }} time{{ question.times_viewed !== 1 ? 's' : '' }}
                                                    </span>
                                                    <span class="text-xs text-primary/70">
                                                        Click to collapse
                                                    </span>
                                                </div>
                                            </div>
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </div>
                        </ScrollArea>
                    </template>

                    <!-- Empty State -->
                    <div v-else class="py-12 text-center">
                        <!-- Progress indicator if content is below threshold -->
                        <div v-if="defenseWatcher?.shouldShowProgress" class="space-y-4">
                            <div class="relative">
                                <div class="absolute inset-0 flex items-center justify-center opacity-10">
                                    <Shield class="h-24 w-24" />
                                </div>
                                <div class="relative">
                                    <Shield class="mx-auto h-12 w-12 text-muted-foreground/40 mb-4" />
                                    <h3 class="text-sm font-medium text-foreground mb-2">
                                        Keep Writing to Unlock Defense Questions
                                    </h3>
                                    <p class="text-xs text-muted-foreground mb-4">
                                        {{ defenseWatcher.statusMessage }}
                                    </p>

                                    <!-- Progress bar -->
                                    <div class="max-w-md mx-auto space-y-2">
                                        <Progress :value="defenseWatcher.progressPercentage" class="h-2" />
                                        <div class="flex justify-between text-xs text-muted-foreground">
                                            <span>{{ chapterContext.word_count }} words</span>
                                            <span>{{ defenseWatcher.threshold }} words needed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Default empty state -->
                        <div v-else class="relative">
                            <div class="absolute inset-0 flex items-center justify-center opacity-10">
                                <Shield class="h-24 w-24" />
                            </div>
                            <div class="relative">
                                <Shield class="mx-auto h-12 w-12 text-muted-foreground/40 mb-4" />
                                <h3 class="text-sm font-medium text-foreground mb-2">
                                    Ready to Practice?
                                </h3>
                                <p class="text-xs text-muted-foreground mb-1">
                                    No defense questions generated yet for this chapter
                                </p>
                                <p class="text-xs text-muted-foreground/80">
                                    {{ defenseWatcher?.statusMessage || 'Click "Generate Questions" below to create tailored practice questions' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-3">
                        <Button @click="emit('generate-more')"
                            :disabled="isGenerating || (defenseWatcher && !defenseWatcher.meetsThreshold)"
                            size="sm"
                            :variant="questions.length ? 'outline' : 'default'"
                            class="text-xs font-medium hover:scale-105 transition-all duration-200 shadow-sm hover:shadow-md">
                            <Wand2 :class="[
                                'mr-2 h-4 w-4',
                                isGenerating ? 'animate-spin' : ''
                            ]" />
                            {{
                                isGenerating ? 'Generating...' :
                                (defenseWatcher && !defenseWatcher.meetsThreshold) ? `Need ${defenseWatcher.wordsRemaining} More Words` :
                                (questions.length ? 'Generate More' : 'Generate Questions')
                            }}
                        </Button>

                        <Button @click="emit('refresh')" :disabled="isLoading || !questions.length" size="sm"
                            variant="outline" class="text-xs font-medium hover:scale-105 transition-all duration-200 shadow-sm hover:shadow-md">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Shuffle
                        </Button>
                    </div>

                    <!-- Export Button (if questions exist) -->
                    <Button v-if="questions.length" @click="exportQuestions" size="sm" variant="ghost"
                        class="w-full text-xs font-medium hover:bg-primary/10 hover:text-primary transition-all duration-200">
                        <Download class="mr-2 h-4 w-4" />
                        Export Questions
                    </Button>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>