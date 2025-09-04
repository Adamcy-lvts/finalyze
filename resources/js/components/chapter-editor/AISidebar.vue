<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { BookMarked, Brain, ChevronDown, FileText, Lightbulb, MessageSquare, PenTool, Quote, Send, Sparkles, Target, Wand2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    project: {
        mode: 'auto' | 'manual';
    };
    isGenerating: boolean;
    selectedText: string;
    isLoadingSuggestions: boolean;
    showCitationHelper: boolean;
    chapterContent: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    startStreamingGeneration: [type: 'progressive' | 'outline' | 'improve'];
    getAISuggestions: [];
    'update:showCitationHelper': [value: boolean];
}>();

// Local state
const showAIChat = ref(false);

// Computed
const canImprove = computed(() => !props.isGenerating && !!props.chapterContent);
const canGetSuggestions = computed(() => !props.isLoadingSuggestions && !!props.selectedText);

// Methods
const handleGenerateAction = (type: 'progressive' | 'outline' | 'improve') => {
    emit('startStreamingGeneration', type);
};

const handleGetSuggestions = () => {
    emit('getAISuggestions');
};

const handleToggleCitationHelper = () => {
    emit('update:showCitationHelper', !props.showCitationHelper);
};

const handleChatInput = () => {
    toast('Chat feature coming soon!');
};
</script>

<template>
    <div class="space-y-4 sm:space-y-6">
        <!-- AI Tools Panel -->
        <Card v-if="project.mode === 'auto'" class="border-[0.5px] border-border/50">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <Sparkles class="h-4 w-4 text-muted-foreground" />
                    AI Assistant
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <!-- Quick Actions -->
                <div class="space-y-2">
                    <Button
                        @click="handleGenerateAction('progressive')"
                        :disabled="isGenerating"
                        size="sm"
                        class="w-full justify-start"
                        variant="outline"
                    >
                        <Wand2 class="mr-2 h-4 w-4" />
                        Generate Full Chapter
                    </Button>

                    <Button @click="handleGenerateAction('outline')" :disabled="!canImprove" size="sm" class="w-full justify-start" variant="outline">
                        <FileText class="mr-2 h-4 w-4" />
                        Generate Outline First
                    </Button>

                    <Button @click="handleGenerateAction('improve')" :disabled="!canImprove" size="sm" class="w-full justify-start" variant="outline">
                        <Sparkles class="mr-2 h-4 w-4" />
                        Improve Existing
                    </Button>

                    <Button @click="handleGetSuggestions" :disabled="!canGetSuggestions" size="sm" class="w-full justify-start" variant="outline">
                        <Lightbulb class="mr-2 h-4 w-4" />
                        Get Suggestions
                    </Button>

                    <Button @click="handleToggleCitationHelper" size="sm" class="w-full justify-start" variant="outline">
                        <Quote class="mr-2 h-4 w-4" />
                        Citation Helper
                    </Button>
                </div>

                <!-- AI Settings -->
                <Separator />
                <div class="space-y-2">
                    <Label class="text-xs">AI Writing Style</Label>
                    <select class="w-full rounded-md border px-2 py-1 text-xs">
                        <option>Academic Formal</option>
                        <option>Academic Casual</option>
                        <option>Technical</option>
                        <option>Analytical</option>
                    </select>
                </div>
            </CardContent>
        </Card>

        <!-- AI Chapter Assistant Chat -->
        <Card class="border-[0.5px] border-border/50">
            <Collapsible v-model:open="showAIChat">
                <CollapsibleTrigger class="w-full">
                    <CardHeader class="pb-3 transition-colors hover:bg-muted/30">
                        <CardTitle class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2">
                                <MessageSquare class="h-4 w-4 text-muted-foreground" />
                                Chapter Assistant
                            </span>
                            <ChevronDown :class="['h-4 w-4 text-muted-foreground transition-transform', showAIChat ? 'rotate-180' : '']" />
                        </CardTitle>
                    </CardHeader>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <CardContent class="space-y-4 pt-0">
                        <!-- Chat Messages Area -->
                        <ScrollArea class="h-[200px]">
                            <div class="mr-2 space-y-2 rounded-lg bg-muted/30 p-3">
                                <!-- Welcome Message -->
                                <div class="flex gap-2">
                                    <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-500">
                                        <Brain class="h-3 w-3 text-white" />
                                    </div>
                                    <div class="max-w-[85%] rounded-lg bg-background px-3 py-2 text-xs">
                                        Hi! I'm your chapter assistant. I can help you with:
                                        <ul class="mt-1 ml-3 space-y-0.5">
                                            <li>• Improving arguments</li>
                                            <li>• Finding gaps in logic</li>
                                            <li>• Suggesting references</li>
                                            <li>• Restructuring content</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Example User Message -->
                                <div class="flex justify-end gap-2">
                                    <div class="max-w-[85%] rounded-lg bg-primary px-3 py-2 text-xs text-primary-foreground">
                                        How can I make my introduction more compelling?
                                    </div>
                                </div>

                                <!-- Example AI Response -->
                                <div class="flex gap-2">
                                    <div class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-500">
                                        <Brain class="h-3 w-3 text-white" />
                                    </div>
                                    <div class="max-w-[85%] rounded-lg bg-background px-3 py-2 text-xs">
                                        Your introduction could be stronger with:
                                        <br />1. A hook statement about the research gap <br />2. Clear thesis statement in paragraph 2 <br />3. Brief
                                        roadmap of your arguments
                                    </div>
                                </div>
                            </div>
                        </ScrollArea>

                        <!-- Quick Action Buttons -->
                        <div class="grid grid-cols-2 gap-2">
                            <Button size="sm" variant="outline" class="text-xs">
                                <PenTool class="mr-1 h-3 w-3" />
                                Review Structure
                            </Button>
                            <Button size="sm" variant="outline" class="text-xs">
                                <Lightbulb class="mr-1 h-3 w-3" />
                                Get Ideas
                            </Button>
                            <Button size="sm" variant="outline" class="text-xs">
                                <BookMarked class="mr-1 h-3 w-3" />
                                Check Citations
                            </Button>
                            <Button size="sm" variant="outline" class="text-xs">
                                <Target class="mr-1 h-3 w-3" />
                                Find Gaps
                            </Button>
                        </div>

                        <!-- Chat Input -->
                        <div class="flex gap-2">
                            <Input placeholder="Ask about your chapter..." class="text-xs" @keyup.enter="handleChatInput" />
                            <Button size="icon" class="h-8 w-8" @click="handleChatInput">
                                <Send class="h-3 w-3" />
                            </Button>
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    </div>
</template>
