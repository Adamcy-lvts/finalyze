<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Wand2,
    Save,
    Trash2,
    Sparkles,
    ChevronDown,
    BookOpen,
} from 'lucide-vue-next';

interface SavedPrompt {
    id: string;
    name: string;
    prompt: string;
    createdAt: string;
}

interface Props {
    open: boolean;
    selectedText?: string;
    chapterContent?: string;
    projectTitle?: string;
    chapterTitle?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'execute-prompt': [prompt: string];
}>();

const customPrompt = ref('');
const promptName = ref('');
const savedPrompts = ref<SavedPrompt[]>([]);
const showSavedPrompts = ref(false);
const isExecuting = ref(false);

const templateVariables = [
    { name: '{selectedText}', description: 'Currently selected text' },
    { name: '{chapterContent}', description: 'Full chapter content' },
    { name: '{projectTitle}', description: 'Project title' },
    { name: '{chapterTitle}', description: 'Chapter title' },
];

const examplePrompts = [
    { name: 'Academic Tone', prompt: 'Rewrite in formal academic tone:\n\n{selectedText}' },
    { name: 'Add Citations', prompt: 'Identify claims needing citations:\n\n{selectedText}' },
    { name: 'Simplify', prompt: 'Simplify while keeping key concepts:\n\n{selectedText}' },
    { name: 'Expand', prompt: 'Expand with more details and examples:\n\n{selectedText}' },
    { name: 'Critical Analysis', prompt: 'Add critical analysis and evaluation:\n\n{selectedText}' },
];

const loadSavedPrompts = () => {
    try {
        const stored = localStorage.getItem('customAIPrompts');
        if (stored) savedPrompts.value = JSON.parse(stored);
    } catch (e) { console.error('Failed to load prompts:', e); }
};

const saveSavedPrompts = () => {
    try {
        localStorage.setItem('customAIPrompts', JSON.stringify(savedPrompts.value));
    } catch (e) { console.error('Failed to save prompts:', e); }
};

const savePrompt = () => {
    if (!customPrompt.value.trim() || !promptName.value.trim()) return;
    savedPrompts.value.unshift({
        id: `prompt_${Date.now()}`,
        name: promptName.value.trim(),
        prompt: customPrompt.value.trim(),
        createdAt: new Date().toISOString(),
    });
    saveSavedPrompts();
    promptName.value = '';
};

const deletePrompt = (id: string) => {
    savedPrompts.value = savedPrompts.value.filter(p => p.id !== id);
    saveSavedPrompts();
};

const loadPrompt = (prompt: SavedPrompt) => {
    customPrompt.value = prompt.prompt;
    showSavedPrompts.value = false;
};

const loadExample = (example: { name: string; prompt: string }) => {
    customPrompt.value = example.prompt;
};

const insertVariable = (variable: string) => {
    customPrompt.value += variable;
};

const processPrompt = (prompt: string): string => {
    return prompt
        .replace(/\{selectedText\}/g, props.selectedText || '[No text selected]')
        .replace(/\{chapterContent\}/g, props.chapterContent || '[No content]')
        .replace(/\{projectTitle\}/g, props.projectTitle || '[Untitled]')
        .replace(/\{chapterTitle\}/g, props.chapterTitle || '[Untitled]');
};

const executePrompt = () => {
    if (!customPrompt.value.trim()) return;
    isExecuting.value = true;
    emit('execute-prompt', processPrompt(customPrompt.value));
    emit('update:open', false);
    setTimeout(() => { isExecuting.value = false; }, 500);
};

const hasSelectedTextVar = computed(() => 
    customPrompt.value.includes('{selectedText}') && !props.selectedText
);

onMounted(() => { loadSavedPrompts(); });
</script>

<template>
    <Dialog :open="open" @update:open="val => emit('update:open', val)">
        <DialogContent class="sm:max-w-[600px] max-h-[80vh] flex flex-col">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <div class="p-1.5 rounded-lg bg-gradient-to-r from-purple-500/20 to-indigo-500/20">
                        <Wand2 class="h-4 w-4 text-purple-500" />
                    </div>
                    Custom AI Prompt
                </DialogTitle>
                <DialogDescription>
                    Create custom prompts with template variables like {selectedText}.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 overflow-hidden flex flex-col gap-3">
                <!-- Template Variables -->
                <div class="space-y-1">
                    <Label class="text-xs text-muted-foreground">Insert Variable</Label>
                    <div class="flex flex-wrap gap-1">
                        <Button v-for="v in templateVariables" :key="v.name" variant="outline" size="sm" 
                            class="h-6 text-xs" @click="insertVariable(v.name)" :title="v.description">
                            {{ v.name }}
                        </Button>
                    </div>
                </div>

                <!-- Prompt Input -->
                <div class="space-y-2 flex-1 min-h-0">
                    <div class="flex items-center justify-between">
                        <Label>Your Prompt</Label>
                        <button @click="showSavedPrompts = !showSavedPrompts"
                            class="text-xs text-muted-foreground hover:text-foreground flex items-center gap-1">
                            <BookOpen class="h-3 w-3" />
                            {{ savedPrompts.length > 0 ? `Saved (${savedPrompts.length})` : 'Examples' }}
                            <ChevronDown :class="['h-3 w-3 transition', showSavedPrompts ? 'rotate-180' : '']" />
                        </button>
                    </div>

                    <div v-if="showSavedPrompts" class="border rounded-lg p-2 space-y-2 bg-muted/30 max-h-40 overflow-y-auto">
                        <div v-if="savedPrompts.length > 0">
                            <p class="text-xs font-medium text-muted-foreground mb-1">Saved</p>
                            <div v-for="p in savedPrompts" :key="p.id" 
                                class="flex items-center justify-between p-1.5 rounded hover:bg-muted cursor-pointer group"
                                @click="loadPrompt(p)">
                                <span class="text-xs truncate flex-1">{{ p.name }}</span>
                                <Button variant="ghost" size="sm" class="h-5 w-5 p-0 opacity-0 group-hover:opacity-100"
                                    @click.stop="deletePrompt(p.id)">
                                    <Trash2 class="h-3 w-3 text-destructive" />
                                </Button>
                            </div>
                            <Separator class="my-1" />
                        </div>
                        <p class="text-xs font-medium text-muted-foreground mb-1">Examples</p>
                        <div v-for="ex in examplePrompts" :key="ex.name" 
                            class="p-1.5 rounded hover:bg-muted cursor-pointer text-xs" @click="loadExample(ex)">
                            {{ ex.name }}
                        </div>
                    </div>

                    <Textarea v-model="customPrompt" placeholder="Enter your custom prompt..." class="min-h-[120px] resize-none" />
                    <p v-if="hasSelectedTextVar" class="text-xs text-amber-500">⚠️ Uses {selectedText} but no text selected</p>
                </div>

                <!-- Save -->
                <div class="flex gap-2">
                    <Input v-model="promptName" placeholder="Prompt name (to save)" class="flex-1" />
                    <Button variant="outline" size="sm" :disabled="!customPrompt.trim() || !promptName.trim()" @click="savePrompt">
                        <Save class="h-4 w-4 mr-1" /> Save
                    </Button>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="emit('update:open', false)">Cancel</Button>
                <Button @click="executePrompt" :disabled="!customPrompt.trim() || isExecuting"
                    class="bg-gradient-to-r from-purple-500 to-indigo-500 hover:from-purple-600 hover:to-indigo-600">
                    <Sparkles class="h-4 w-4 mr-1" />
                    {{ isExecuting ? 'Executing...' : 'Execute' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
