<!-- /resources/js/components/chapter-editor/ChatModeLayout.vue -->
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
import { onMounted, onUnmounted, ref } from 'vue';

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
    (e: 'update:chapterTitle', value: string): void;
    (e: 'update:chapterContent', value: string): void;
    (e: 'update:selectedText', value: string): void;
    (e: 'update:showPreview', value: boolean): void;
    (e: 'save', autoSave: boolean): void;
    (e: 'undo'): void;
    (e: 'redo'): void;
    (e: 'exitChatMode'): void;
}>();

interface ChatMessage {
    id: number;
    type: 'user' | 'ai' | 'system';
    content: string;
    timestamp: Date;
    isStreaming?: boolean;
    failed?: boolean;
    lastPrompt?: string;
    lastQuickAction?: string;
}

// Chat state
const chatMessages = ref<ChatMessage[]>([]);
const chatInput = ref('');
const isTyping = ref(false);
const currentSessionId = ref<string | null>(null);
const isLoadingHistory = ref(false);
const currentStreamReader = ref<ReadableStreamDefaultReader | null>(null);

// Content editor state (edit mode as default for chat mode)
const showContentPreview = ref(false);

// Chat now starts empty - no auto-generated welcome messages

// Chat UI state
const isChatMinimized = ref(false);
const currentChatMode = ref<'review' | 'assist'>('review');

// Mobile detection
const isMobileView = ref(false);
const screenWidth = ref(0);

const checkMobileView = () => {
    screenWidth.value = window.innerWidth;
    isMobileView.value = window.innerWidth < 768; // md breakpoint

    // Auto-minimize chat on mobile for better UX
    if (isMobileView.value && !isChatMinimized.value) {
        // Don't auto-minimize, let user decide
    }
};

const handleResize = () => {
    checkMobileView();
};

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
            chatMessages.value = messages.map((msg: any) => ({
                id: msg.id,
                type: msg.type,
                content: msg.content,
                timestamp: new Date(msg.timestamp),
            }));
            currentSessionId.value = current_session_id;
        } else {
            // No history, start with empty chat
            chatMessages.value = [];
        }
    } catch (error) {
        console.warn('Failed to load chat history:', error);
        // Fallback to empty chat
        chatMessages.value = [];
    } finally {
        isLoadingHistory.value = false;
    }
};

// Initialize chat on mount
onMounted(() => {
    loadChatHistory();
    checkMobileView();
    window.addEventListener('resize', handleResize);
});

onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});

// Methods
const handleSendMessage = async (quickAction?: string) => {
    if (!chatInput.value.trim() && !quickAction) return;

    const userMessage = quickAction ? '' : chatInput.value;
    const originalInput = chatInput.value; // Store original input for retry
    chatInput.value = '';

    // Add user message (only if not a quick action)
    if (!quickAction) {
        chatMessages.value.push({
            id: Date.now(),
            type: 'user',
            content: userMessage,
            timestamp: new Date(),
        });
    }

    // Show typing indicator
    isTyping.value = true;

    // Prepare AI response placeholder ID but don't add message yet
    const aiMessageId = Date.now() + 1;

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
                    task_type: currentChatMode.value,
                    quick_action: quickAction || null,
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

        // Store reader for potential cancellation
        currentStreamReader.value = reader;

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

        const handleStreamData = (data: any) => {
            switch (data.type) {
                case 'start':
                    isTyping.value = true;
                    if (data.session_id) {
                        currentSessionId.value = data.session_id;
                    }
                    break;

                case 'content':
                    // Stop typing indicator as soon as we start receiving content
                    if (isTyping.value) {
                        isTyping.value = false;
                    }

                    // Find existing message or create it if this is the first content
                    const messageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
                    if (messageIndex === -1) {
                        // First content chunk - add the AI message now
                        chatMessages.value.push({
                            id: aiMessageId,
                            type: 'ai',
                            content: data.content,
                            timestamp: new Date(),
                            isStreaming: true,
                        });
                    } else {
                        // Append to existing message
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
                        // Mark existing AI message as failed
                        chatMessages.value[errorMessageIndex] = {
                            id: chatMessages.value[errorMessageIndex].id,
                            type: 'ai',
                            content: data.message || '⚠️ Sorry, I encountered an error processing your message.',
                            timestamp: new Date(),
                            failed: true,
                            lastPrompt: originalInput,
                            lastQuickAction: quickAction,
                        };
                    } else {
                        // No AI message exists yet, add failed message
                        chatMessages.value.push({
                            id: Date.now(),
                            type: 'ai',
                            content: data.message || '⚠️ Sorry, I encountered an error processing your message.',
                            timestamp: new Date(),
                            failed: true,
                            lastPrompt: originalInput,
                            lastQuickAction: quickAction,
                        });
                    }
                    break;

                case 'heartbeat':
                    // Keep connection alive, no action needed
                    break;
            }
        };

        const handleStreamError = (error: any) => {
            isTyping.value = false;
            const errorMessageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
            if (errorMessageIndex !== -1) {
                // Mark existing AI message as failed
                chatMessages.value[errorMessageIndex] = {
                    id: chatMessages.value[errorMessageIndex].id,
                    type: 'ai',
                    content: '⚠️ Connection error. Please check your internet and try again.',
                    timestamp: new Date(),
                    failed: true,
                    lastPrompt: originalInput,
                    lastQuickAction: quickAction,
                };
            } else {
                // No AI message exists yet, add failed message
                chatMessages.value.push({
                    id: Date.now(),
                    type: 'ai',
                    content: '⚠️ Connection error. Please check your internet and try again.',
                    timestamp: new Date(),
                    failed: true,
                    lastPrompt: originalInput,
                    lastQuickAction: quickAction,
                });
            }
        };

        // Start reading the stream
        try {
            await readStream();
        } finally {
            // Clear the reader reference
            currentStreamReader.value = null;
        }
    } catch (error) {
        console.error('Chat streaming error:', error);
        isTyping.value = false;

        // Add failed message (AI message may not exist yet)
        const errorMessageIndex = chatMessages.value.findIndex((msg) => msg.id === aiMessageId);
        if (errorMessageIndex !== -1) {
            // Mark existing AI message as failed
            chatMessages.value[errorMessageIndex] = {
                id: chatMessages.value[errorMessageIndex].id,
                type: 'ai',
                content: '⚠️ Failed to start conversation. Please try again.',
                timestamp: new Date(),
                failed: true,
                lastPrompt: originalInput,
                lastQuickAction: quickAction,
            };
        } else {
            // No AI message exists yet, add failed message
            chatMessages.value.push({
                id: Date.now(),
                type: 'ai',
                content: '⚠️ Failed to start conversation. Please try again.',
                timestamp: new Date(),
                failed: true,
                lastPrompt: originalInput,
                lastQuickAction: quickAction,
            });
        }
    }
};

const handleQuickAction = (action: string) => {
    // Send the action directly as a quick action
    handleSendMessage(action);
};

const handleModeChange = (mode: 'review' | 'assist') => {
    currentChatMode.value = mode;
    console.log('Chat mode changed to:', mode);

    // No need to update messages when mode changes - let user start conversation naturally
};

const handleCopyMessage = (message: any) => {
    console.log('Message copied:', message);
    // You could show a toast notification here
};

const handleRateMessage = (messageId: number, rating: number) => {
    console.log('Message rated:', messageId, rating);
    // You could send the rating to the backend here
};

const handleNewSession = () => {
    console.log('Starting new chat session...');

    // Clear current chat messages
    chatMessages.value = [];

    // Reset session ID to null so a new one will be generated on next message
    currentSessionId.value = null;

    // Clear input
    chatInput.value = '';

    // Start with empty chat - no auto-generated messages
    console.log('New chat session started');
};

const handleChatDeleted = () => {
    console.log('Chat deleted - refreshing main chat');
    // Reload chat history to reflect the deletion
    loadChatHistory();
};

const handleChatCleared = () => {
    console.log('Chat cleared - refreshing main chat');
    // Clear messages and reload history
    chatMessages.value = [];
    currentSessionId.value = null;
    loadChatHistory();
};

const handleRetryMessage = (message: any) => {
    console.log('Retrying failed message:', message);

    // Remove the failed message
    const messageIndex = chatMessages.value.findIndex(msg => msg.id === message.id);
    if (messageIndex !== -1) {
        chatMessages.value.splice(messageIndex, 1);
    }

    // Retry the original prompt or quick action
    if (message.lastQuickAction) {
        handleSendMessage(message.lastQuickAction);
    } else if (message.lastPrompt) {
        chatInput.value = message.lastPrompt;
        handleSendMessage();
    }
};

const handleStopGeneration = () => {
    console.log('Stopping generation...');

    if (currentStreamReader.value) {
        try {
            currentStreamReader.value.cancel();
        } catch (error) {
            console.warn('Error cancelling stream:', error);
        }
        currentStreamReader.value = null;
    }

    isTyping.value = false;

    // Find the last AI message and mark it as stopped
    const lastMessage = chatMessages.value[chatMessages.value.length - 1];
    if (lastMessage && lastMessage.type === 'ai' && lastMessage.isStreaming) {
        lastMessage.isStreaming = false;
        lastMessage.content += '\n\n*[Generation stopped by user]*';
    }
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
        <!-- Minimal Header -->
        <div
            class="flex flex-shrink-0 items-center justify-between border-b bg-background/95 p-2 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="flex items-center gap-2">
                <Button @click="emit('exitChatMode')" variant="ghost" size="sm" class="gap-1">
                    <X class="h-4 w-4" />
                    Exit Chat
                </Button>
            </div>

            <div class="flex items-center gap-2">
                <Badge variant="outline" class="text-xs"> {{ writingQualityScore }}% Quality </Badge>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="flex-shrink-0 bg-muted/30 px-3 py-2">
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-medium">Writing Progress</span>
                <span class="text-xs text-muted-foreground">{{ Math.round(progressPercentage) }}% ({{ currentWordCount
                    }} / {{ targetWordCount }} words)</span>
            </div>
            <Progress :model-value="progressPercentage" class="h-1" />
        </div>

        <!-- Main Split Content -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Mobile Chat Overlay -->
            <div v-if="isMobileView && !isChatMinimized" class="absolute inset-0 z-50 bg-background md:hidden">
                <ChatAssistant :is-minimized="false" :messages="chatMessages" :is-typing="isTyping"
                    :selected-text="selectedText" :chapter-content="chapterContent"
                    :chapter-number="chapter.chapter_number" :current-mode="currentChatMode"
                    :project-slug="project.slug" :session-id="currentSessionId || 'temp-session'" :is-mobile="true"
                    @send-message="handleSendMessage" @quick-action="handleQuickAction"
                    @retry-message="handleRetryMessage" @stop-generation="handleStopGeneration"
                    @toggle-minimize="toggleChatMinimize" @change-mode="handleModeChange"
                    @copy-message="handleCopyMessage" @rate-message="handleRateMessage" @new-session="handleNewSession"
                    @chat-deleted="handleChatDeleted" @chat-cleared="handleChatCleared" v-model:input="chatInput"
                    class="mobile-chat-overlay" />
            </div>
            <!-- Left Panel - Editor -->
            <div class="flex min-h-0 flex-1 flex-col border-r">
                <Card class="flex min-h-0 flex-1 flex-col rounded-none border-0 bg-transparent shadow-none">
                    <CardContent class="flex min-h-0 flex-1 flex-col space-y-4 p-6">
                        <!-- Content Editor -->
                        <div class="flex min-h-0 flex-1 flex-col space-y-2">
                            <div class="flex items-center justify-end">
                                <Button @click="toggleContentPreview"
                                    :variant="showContentPreview ? 'default' : 'ghost'" size="sm" class="h-7 text-xs">
                                    <PenTool v-if="showContentPreview" class="mr-1.5 h-3 w-3" />
                                    <Eye v-else class="mr-1.5 h-3 w-3" />
                                    {{ showContentPreview ? 'Edit' : 'Preview' }}
                                </Button>
                            </div>

                            <!-- Rich Text Editor -->
                            <ScrollArea class="min-h-0 flex-1" v-show="!showContentPreview">
                                <RichTextEditor :model-value="chapterContent"
                                    @update:model-value="emit('update:chapterContent', $event)"
                                    placeholder="Start writing your chapter..." min-height="400px"
                                    class="text-sm leading-relaxed" />
                            </ScrollArea>

                            <!-- Preview Mode -->
                            <ScrollArea class="min-h-0 flex-1" v-show="showContentPreview">
                                <RichTextViewer :content="chapterContent" class="p-4"
                                    style="font-family: 'Times New Roman', serif; line-height: 1.6" />
                            </ScrollArea>
                        </div>

                        <!-- Generation Progress -->
                        <div v-if="isGenerating"
                            class="flex flex-shrink-0 items-center gap-2 rounded-lg bg-muted/30 p-2">
                            <div class="h-3 w-3 animate-spin rounded-full border-2 border-primary border-t-transparent">
                            </div>
                            <span class="text-xs">{{ generationProgress }}</span>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-shrink-0 gap-2 pt-2">
                            <Button @click="emit('save', false)" :disabled="!isValid || isSaving" size="sm">
                                <Save class="mr-1 h-3 w-3" />
                                {{ isSaving ? 'Saving...' : 'Save' }}
                            </Button>

                            <Button @click="emit('save', false)"
                                :disabled="!isValid || currentWordCount < targetWordCount * 0.8" size="sm">
                                <CheckCircle class="mr-1 h-3 w-3" />
                                Complete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel - Chat Assistant (Desktop) -->
            <div v-if="!isMobileView" :class="[
                'flex min-h-0 flex-col bg-muted/20 border-l transition-all duration-300 ease-in-out',
                isChatMinimized ? 'w-[60px]' : 'w-[450px] xl:w-[500px]'
            ]">
                <ChatAssistant :is-minimized="isChatMinimized" :messages="chatMessages" :is-typing="isTyping"
                    :selected-text="selectedText" :chapter-content="chapterContent"
                    :chapter-number="chapter.chapter_number" :current-mode="currentChatMode"
                    :project-slug="project.slug" :session-id="currentSessionId || 'temp-session'"
                    @send-message="handleSendMessage" @quick-action="handleQuickAction"
                    @retry-message="handleRetryMessage" @stop-generation="handleStopGeneration"
                    @toggle-minimize="toggleChatMinimize" @change-mode="handleModeChange"
                    @copy-message="handleCopyMessage" @rate-message="handleRateMessage" @new-session="handleNewSession"
                    @chat-deleted="handleChatDeleted" @chat-cleared="handleChatCleared" v-model:input="chatInput" />
            </div>
        </div>
    </div>
</template>
