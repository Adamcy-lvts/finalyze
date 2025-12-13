<!-- /resources/js/pages/projects/ChapterAIGenerationEditor.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import { useAutoSave } from '@/composables/useAutoSave';
import { useAppearance } from '@/composables/useAppearance';
import { useChapterGeneration } from '@/composables/useChapterGeneration';
import { useChapterWordCount } from '@/composables/useChapterWordCount';
import { useWordBalance } from '@/composables/useWordBalance';
import type { ChapterEditorProps } from '@/types/chapter-editor';
import { route } from 'ziggy-js';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { ArrowLeft, Brain, Save } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

const props = defineProps<ChapterEditorProps>();

const { appearance, isDark } = useAppearance();

const chapterTitle = ref(props.chapter.title || '');
const chapterContent = ref(props.chapter.content || '');
const selectedText = ref('');

const richTextEditor = ref<{ editor?: any } | null>(null);
const richTextEditorFullscreen = ref<{ editor?: any } | null>(null);
const isNativeFullscreen = ref(false);

const targetWordCount = computed(() => {
    if (props.facultyChapters && props.facultyChapters.length > 0) {
        const facultyChapter = props.facultyChapters.find((ch) => ch.number === props.chapter.chapter_number);
        if (facultyChapter?.word_count) return facultyChapter.word_count;
    }

    const outlineTarget = props.project?.outlines?.find((o) => o.chapter_number === props.chapter.chapter_number)?.target_word_count;
    if (outlineTarget) return outlineTarget;

    if (props.chapter.target_word_count) return props.chapter.target_word_count;
    return 3000;
});

const { currentWordCount, progressPercentage, countWords } = useChapterWordCount(chapterContent, targetWordCount);

async function saveChapter(autoSave = false) {
    try {
        const response = await fetch(route('chapters.save', { project: props.project.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                project_id: props.project.id,
                chapter_number: props.chapter.chapter_number,
                title: chapterTitle.value,
                content: chapterContent.value,
                auto_save: autoSave,
            }),
        });

        if (!response.ok) {
            throw new Error(`Save failed: ${response.status} ${response.statusText}`);
        }
    } catch (error) {
        if (!autoSave) {
            toast.error('Save failed', { description: 'Please try again.' });
        }
        throw error;
    }
}

const { hasUnsavedChanges, isSaving, triggerAutoSave, save } = useAutoSave({
    delay: 8000,
    onSave: saveChapter,
});

const { balance, showPurchaseModal, requiredWordsForModal, actionDescriptionForModal, checkAndPrompt, closePurchaseModal, estimates } = useWordBalance();
const ensureBalance = (requiredWords: number, action: string): boolean => checkAndPrompt(Math.max(1, Math.round(requiredWords)), action);

const generation = useChapterGeneration({
    props,
    chapterContent,
    targetWordCount,
    estimates,
    ensureBalance,
    save: saveChapter,
    triggerAutoSave,
    calculateWritingStats: () => {},
    countWords,
    selectedText,
    richTextEditor,
    richTextEditorFullscreen,
    isNativeFullscreen,
});

const canGenerate = computed(() => !generation.isGenerating.value && !isSaving.value);

const goBack = () => router.visit(route('projects.show', props.project.slug));
</script>

<template>
    <AppLayout>
        <div class="flex min-h-[calc(100vh-2rem)] flex-col gap-4 bg-background text-foreground">
            <header class="sticky top-0 z-20 border-b border-border/40 bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
                    <Button variant="ghost" size="icon" class="h-9 w-9" @click="goBack" title="Back to Project">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">{{ props.project.title }}</div>
                        <div class="text-xs text-muted-foreground">
                            Chapter {{ props.chapter.chapter_number }} · {{ currentWordCount }} / {{ targetWordCount }} words
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <ThemeToggle />
                        <Button variant="outline" size="sm" class="h-9 gap-2" @click="save(false)" :disabled="isSaving">
                            <Save class="h-4 w-4" />
                            <span class="text-xs font-medium">{{ isSaving ? 'Saving…' : 'Save' }}</span>
                        </Button>
                    </div>
                </div>
            </header>

            <main class="mx-auto grid w-full max-w-7xl flex-1 gap-4 px-4 pb-6 lg:grid-cols-[1fr_380px]">
                <section class="flex min-h-[60vh] flex-col gap-4">
                    <Card class="bg-card text-card-foreground">
                        <CardHeader class="space-y-2">
                            <CardTitle class="text-base">AI Generation Editor</CardTitle>
                            <div class="grid gap-2">
                                <Label for="chapter-title" class="text-xs text-muted-foreground">Title</Label>
                                <Input id="chapter-title" v-model="chapterTitle" class="bg-background" />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Progress :model-value="progressPercentage" />

                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" :disabled="!canGenerate" @click="generation.handleAIGeneration('progressive')">
                                    Generate Chapter
                                </Button>
                                <Button size="sm" variant="outline" :disabled="!canGenerate" @click="generation.handleAIGeneration('outline')">
                                    Generate Outline
                                </Button>
                                <Button size="sm" variant="outline" :disabled="!canGenerate" @click="generation.handleAIGeneration('improve')">
                                    Improve
                                </Button>
                                <Button size="sm" variant="outline" :disabled="!canGenerate" @click="generation.handleAIGeneration('expand')">
                                    Expand
                                </Button>
                                <Button size="sm" variant="outline" :disabled="!canGenerate" @click="generation.handleAIGeneration('rephrase')">
                                    Rephrase
                                </Button>
                                <Button size="sm" variant="outline" :disabled="!canGenerate" @click="generation.getAISuggestions()">
                                    <Brain class="h-4 w-4 mr-2" />
                                    Suggestions
                                </Button>
                            </div>

                            <div v-if="generation.isGenerating" class="text-xs text-muted-foreground">
                                <div class="font-medium text-foreground">{{ generation.generationPhase || 'Generating…' }}</div>
                                <div>{{ generation.generationProgress }}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="flex min-h-[45vh] flex-1 flex-col bg-card text-card-foreground">
                        <CardHeader>
                            <CardTitle class="text-base">Content</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-1 flex-col">
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <RichTextEditor
                                    ref="richTextEditor"
                                    v-model="chapterContent"
                                    :streaming-mode="generation.isStreamingMode"
                                    :is-generating="generation.isGenerating"
                                    :generation-progress="generation.generationProgress"
                                    :generation-percentage="generation.generationPercentage"
                                    :generation-phase="generation.generationPhase"
                                    @update:selectedText="selectedText = $event"
                                    @update:modelValue="triggerAutoSave"
                                />
                            </div>
                        </CardContent>
                    </Card>
                </section>

                <aside class="space-y-4">
                    <Card class="bg-card text-card-foreground">
                        <CardHeader>
                            <CardTitle class="text-base">Theme</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">mode</span>
                                <span class="font-medium">{{ isDark ? 'dark' : 'light' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground">appearance</span>
                                <span class="font-medium">{{ appearance }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="generation.aiSuggestions.length" class="bg-card text-card-foreground">
                        <CardHeader>
                            <CardTitle class="text-base">AI Suggestions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <ul class="list-disc pl-5 space-y-1">
                                <li v-for="(s, idx) in generation.aiSuggestions" :key="idx">{{ s }}</li>
                            </ul>
                        </CardContent>
                    </Card>

                    <Card v-if="showPurchaseModal" class="bg-card text-card-foreground">
                        <CardHeader>
                            <CardTitle class="text-base">Credits Required</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div class="text-muted-foreground">Balance: {{ balance }}</div>
                            <div class="text-muted-foreground">Needed: {{ requiredWordsForModal }}</div>
                            <div class="text-muted-foreground">{{ actionDescriptionForModal }}</div>
                            <Button size="sm" variant="outline" @click="closePurchaseModal">Close</Button>
                        </CardContent>
                    </Card>
                </aside>
            </main>
        </div>
    </AppLayout>
</template>
