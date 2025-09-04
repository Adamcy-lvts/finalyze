<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { ScrollArea } from '@/components/ui/scroll-area';
import axios from 'axios';
import { CheckCircle, Eye, MessageSquare, PenTool, Save, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

import ChatAssistant from '@/components/chapter-editor/ChatAssistant.vue';
import RichTextEditor from '@/components/ui/rich-text-editor/RichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import { route } from 'ziggy-js';

interface Props {
    project: any;
    chapter: any;
    chapterTitle: string;
    chapterContent: string;
    currentWordCount: number;
    targetWordCount: number;
    progressPercentage: number;
    writingQualityScore: number;
    isValid: boolean;
    isSaving: boolean;
    showPreview: boolean;
    isGenerating: boolean;
    generationProgress: string;
    historyIndex: number;
    contentHistoryLength: number;
    selectedText: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:chapterTitle': [value: string];
    'update:chapterContent': [value: string];
    'update:selectedText': [value: string];
    'update:showPreview': [value: boolean];
    save: [autoSave: boolean];
    undo: [];
    redo: [];
    exitChatMode: [];
}>();

// Chat state
const chatMessages = ref([]);
const chatInput = ref('');
const isTyping = ref(false);
const currentSessionId = ref(null);
const isLoadingHistory = ref(false);

// Content editor state (edit mode as default for chat mode)
const showContentPreview = ref(false);

// Default welcome message
const getWelcomeMessage = () => ({
    id: 1,
    type: 'ai',
    content: `Hi! I'm your AI writing assistant. I can help you with:
• Improving your arguments and structure
• Finding gaps in your logic
• Suggesting better phrasing
• Checking citation formats
• Reviewing chapter coherence

What would you like to work on in this chapter?`,
    timestamp: new Date(),
});

// Chat UI state
const isChatMinimized = ref(false);

// Load chat history from backend
const loadChatHistory = async () => {
    isLoadingHistory.value = true;
    try {
        const response = await axios.get(
            route('chapters.chat-history', {
                project: props.project.slug,
                chapter: props.chapter.chapter_number,
            }),
        );

        const { messages, current_session_id } = response.data;

        if (messages && messages.length > 0) {
            // Convert backend messages to frontend format
            chatMessages.value = messages.map((msg) => ({
                id: msg.id,
                type: msg.type,
                content: msg.content,
                timestamp: new Date(msg.timestamp),
            }));
            currentSessionId.value = current_session_id;
        } else {
            // No history, show welcome message
            chatMessages.value = [getWelcomeMessage()];
        }
    } catch (error) {
        console.warn('Failed to load chat history:', error);
        // Fallback to welcome message
        chatMessages.value = [getWelcomeMessage()];
    } finally {
        isLoadingHistory.value = false;
    }
};

// Initialize chat on mount
onMounted(() => {
    loadChatHistory();
});

// Methods
const handleSendMessage = async () => {
    if (!chatInput.value.trim()) return;

    const userMessage = chatInput.value;
    chatInput.value = '';

    // Add user message
    chatMessages.value.push({
        id: Date.now(),
        type: 'user',
        content: userMessage,
        timestamp: new Date(),
    });

    // Show typing indicator
    isTyping.value = true;

    // Prepare AI response placeholder
    const aiMessageId = Date.now() + 1;
    chatMessages.value.push({
        id: aiMessageId,
        type: 'ai',
        content: '',
        timestamp: new Date(),
        isStreaming: true,
    });

    try {
        // Use fetch with POST for streaming instead of EventSource
        const response = await fetch(
            route('chapters.chat-stream', {
                project: props.project.slug,
                chapter: props.chapter.chapter_number,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    message: userMessage,
                    chapter_content: props.chapterContent || '',
                    selected_text: props.selectedText || '',
                    session_id: currentSessionId.value,
                }),
            },
        );

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const reader = response.body?.getReader();
        if (!reader) {
            throw new Error('No response body reader available');
        }

        // Read the streaming response
        const decoder = new TextDecoder();
        let buffer = '';

        const readStream = async () => {
            try {
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() || '';

                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            try {
                                const data = JSON.parse(line.slice(6));
                                handleStreamData(data);
                            } catch (parseError) {
                                console.error('Failed to parse streaming data:', parseError);
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Stream reading error:', error);
                handleStreamError(error);
            }
        };

        const handleStreamData = (data) => {
            switch (data.type) {
                case 'start':
                    isTyping.value = true;
                    if (data.session_id) {
                        currentSessionId.value = data.session_id;
                    }
                    break;

                case 'content':
                    const messageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
                    if (messageIndex !== -1) {
                        chatMessages.value[messageIndex].content += data.content;
                    }
                    break;

                case 'complete':
                    isTyping.value = false;
                    if (data.session_id) {
                        currentSessionId.value = data.session_id;
                    }
                    const completedMessageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
                    if (completedMessageIndex !== -1) {
                        chatMessages.value[completedMessageIndex].isStreaming = false;
                        if (data.ai_message_id) {
                            chatMessages.value[completedMessageIndex].id = data.ai_message_id;
                        }
                    }
                    break;

                case 'error':
                    isTyping.value = false;
                    const errorMessageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
                    if (errorMessageIndex !== -1) {
                        chatMessages.value[errorMessageIndex] = {
                            id: Date.now(),
                            type: 'system',
                            content: data.message || '⚠️ Sorry, I encountered an error processing your message. Please try again.',
                            timestamp: new Date(),
                        };
                    }
                    break;

                case 'heartbeat':
                    // Keep connection alive, no action needed
                    break;
            }
        };

        const handleStreamError = (error) => {
            isTyping.value = false;
            const errorMessageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
            if (errorMessageIndex !== -1) {
                chatMessages.value[errorMessageIndex] = {
                    id: Date.now(),
                    type: 'system',
                    content: '⚠️ Connection error. Please check your internet and try again.',
                    timestamp: new Date(),
                };
            }
        };

        // Start reading the stream
        await readStream();
    } catch (error) {
        console.error('Chat streaming error:', error);
        isTyping.value = false;

        // Replace AI message with error message
        const errorMessageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
        if (errorMessageIndex !== -1) {
            chatMessages.value[errorMessageIndex] = {
                id: Date.now(),
                type: 'system',
                content: '⚠️ Failed to start conversation. Please try again.',
                timestamp: new Date(),
            };
        }
    }
};

const handleQuickAction = (action: string) => {
    const actions: Record<string, string> = {
        analyze: "Can you analyze my chapter content and give me specific feedback on what I've written so far?",
        improve: 'What specific improvements can you suggest for this chapter? Please reference parts of my text.',
        structure: 'How can I improve the structure and flow of this chapter?',
        citations: 'Can you help me check and improve my citations and references?',
    };

    chatInput.value = actions[action] || '';
    handleSendMessage();
};

// Note: Text selection handling for rich text editor may need different implementation
// const handleTextSelection = (event: Event) => {
//   // This was for textarea - RichTextEditor uses Tiptap which has different selection handling
//   const target = event.target as HTMLTextAreaElement
//   const selectedText = target.value.substring(target.selectionStart, target.selectionEnd)
//   emit('update:selectedText', selectedText)
// }

const toggleChatMinimize = () => {
    isChatMinimized.value = !isChatMinimized.value;
};

const toggleContentPreview = () => {
    showContentPreview.value = !showContentPreview.value;
};
</script>

<template>
    <!-- Split Screen Chat Mode Layout -->
    <div class="flex h-screen flex-col overflow-hidden bg-background">
        <!-- Header -->
        <div
            class="flex flex-shrink-0 items-center justify-between border-b bg-background/95 p-3 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="flex items-center gap-4">
                <Button @click="emit('exitChatMode')" variant="ghost" size="icon">
                    <X class="h-4 w-4" />
                </Button>

                <div class="flex items-center gap-2">
                    <MessageSquare class="h-5 w-5 text-primary" />
                    <div>
                        <h1 class="text-lg font-bold">{{ props.project.title }}</h1>
                        <p class="text-sm text-muted-foreground">
                            Chat Mode • Chapter {{ props.chapter.chapter_number }} • {{ currentWordCount }} / {{ targetWordCount }} words
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Badge :variant="chapter.status === 'approved' ? 'default' : 'secondary'">
                    {{ chapter.status.replace('_', ' ') }}
                </Badge>
                <Badge variant="outline" class="text-xs"> {{ writingQualityScore }}% Quality </Badge>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="flex-shrink-0 bg-muted/30 px-3 py-2">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium">Writing Progress</span>
                <span class="text-xs text-muted-foreground">{{ Math.round(progressPercentage) }}%</span>
            </div>
            <Progress :value="progressPercentage" class="h-1" />
        </div>

        <!-- Main Split Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Left Panel - Editor -->
            <div class="flex min-h-0 flex-1 flex-col border-r">
                <Card class="flex min-h-0 flex-1 flex-col rounded-none border-0 bg-transparent shadow-none">
                    <CardHeader class="flex-shrink-0 border-b px-4 py-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-base">Chapter {{ chapter.chapter_number }} - Editor</CardTitle>
                            <div class="flex items-center gap-2">
                                <Button @click="emit('update:showPreview', !showPreview)" variant="outline" size="sm">
                                    <Eye v-if="!showPreview" class="mr-1 h-3 w-3" />
                                    <PenTool v-else class="mr-1 h-3 w-3" />
                                    {{ showPreview ? 'Edit' : 'Preview' }}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent class="flex min-h-0 flex-1 flex-col space-y-3 p-4">
                        <!-- Chapter Title Input -->
                        <div class="flex-shrink-0 space-y-2">
                            <Label for="chapter-title-chat" class="text-sm font-medium">Chapter Title</Label>
                            <Input
                                id="chapter-title-chat"
                                :model-value="chapterTitle"
                                @update:model-value="emit('update:chapterTitle', $event)"
                                placeholder="Enter chapter title..."
                                class="h-9 text-base font-medium"
                            />
                        </div>

                        <!-- Content Editor -->
                        <div class="flex min-h-0 flex-1 flex-col space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="chapter-content-chat" class="text-sm font-medium">Content</Label>
                                <div class="flex items-center gap-2">
                                    <Button @click="toggleContentPreview" :variant="showContentPreview ? 'default' : 'outline'" size="sm">
                                        <PenTool v-if="showContentPreview" class="mr-1 h-3 w-3" />
                                        <Eye v-else class="mr-1 h-3 w-3" />
                                        {{ showContentPreview ? 'Edit Mode' : 'Preview Mode' }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Rich Text Editor -->
                            <ScrollArea class="min-h-0 flex-1" v-show="!showContentPreview">
                                <RichTextEditor
                                    :model-value="chapterContent"
                                    @update:model-value="emit('update:chapterContent', $event)"
                                    placeholder="Start writing your chapter..."
                                    min-height="400px"
                                    class="text-sm leading-relaxed"
                                />
                            </ScrollArea>

                            <!-- Preview Mode -->
                            <ScrollArea class="min-h-0 flex-1" v-show="showContentPreview">
                                <RichTextViewer
                                    :content="chapterContent"
                                    class="p-4"
                                    style="font-family: 'Times New Roman', serif; line-height: 1.6"
                                />
                            </ScrollArea>
                        </div>

                        <!-- Generation Progress -->
                        <div v-if="isGenerating" class="flex flex-shrink-0 items-center gap-2 rounded-lg bg-muted/30 p-2">
                            <div class="h-3 w-3 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                            <span class="text-xs">{{ generationProgress }}</span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-shrink-0 gap-2 pt-2">
                            <Button @click="emit('save', false)" :disabled="!isValid || isSaving" size="sm">
                                <Save class="mr-1 h-3 w-3" />
                                {{ isSaving ? 'Saving...' : 'Save' }}
                            </Button>

                            <Button @click="emit('save', false)" :disabled="!isValid || currentWordCount < targetWordCount * 0.8" size="sm">
                                <CheckCircle class="mr-1 h-3 w-3" />
                                Complete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel - Chat Assistant -->
            <div :class="['flex min-h-0 flex-1 flex-col bg-muted/20', isChatMinimized ? 'w-16' : '']">
                <ChatAssistant
                    :is-minimized="isChatMinimized"
                    :messages="chatMessages"
                    :is-typing="isTyping"
                    :selected-text="selectedText"
                    :chapter-content="chapterContent"
                    :chapter-number="chapter.chapter_number"
                    @send-message="handleSendMessage"
                    @quick-action="handleQuickAction"
                    @toggle-minimize="toggleChatMinimize"
                    v-model:input="chatInput"
                />
            </div>
        </div>
    </div>
</template>
