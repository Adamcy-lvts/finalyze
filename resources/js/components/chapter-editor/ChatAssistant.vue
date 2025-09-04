<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CompactRichTextEditor from '@/components/ui/rich-text-editor/CompactRichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    AlertCircle,
    BookMarked,
    Bot,
    Brain,
    Copy,
    Maximize2,
    MessageSquare,
    Minimize2,
    PenTool,
    Sparkles,
    Target,
    ThumbsDown,
    ThumbsUp,
    User,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

interface Message {
    id: number;
    type: 'user' | 'ai' | 'system';
    content: string;
    timestamp: Date;
    isStreaming?: boolean;
}

interface Props {
    isMinimized: boolean;
    messages: Message[];
    isTyping: boolean;
    selectedText: string;
    chapterContent: string;
    chapterNumber: number;
    input: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:input': [value: string];
    'send-message': [];
    'quick-action': [action: string];
    'toggle-minimize': [];
}>();

const scrollContainer = ref();
const inputRef = ref();

// Computed
const hasSelectedText = computed(() => props.selectedText.trim().length > 0);
const messageCount = computed(() => props.messages.length);

// Track the total content length for streaming updates
const totalContentLength = computed(() => {
    return props.messages.reduce((total, msg) => total + msg.content.length, 0);
});

// Force scroll to bottom function for chat messages
const scrollChatToBottom = () => {
    nextTick(() => {
        setTimeout(() => {
            try {
                // Get the ScrollArea component
                const scrollAreaComponent = scrollContainer.value;
                if (!scrollAreaComponent) return;

                // Get the actual DOM element
                const scrollAreaEl = scrollAreaComponent.$el || scrollAreaComponent;
                if (!scrollAreaEl) return;

                // For ScrollArea component, look for the viewport specifically
                let viewport = scrollAreaEl.querySelector('[data-radix-scroll-area-viewport]');
                if (!viewport) viewport = scrollAreaEl.querySelector('[data-viewport]');
                if (!viewport) viewport = scrollAreaEl.querySelector('[role="region"]');
                if (!viewport) viewport = scrollAreaEl.querySelector('div[style*="overflow"]');

                // Fallback to the first child div that might be scrollable
                if (!viewport) {
                    const possibleViewports = scrollAreaEl.querySelectorAll('div');
                    for (const div of possibleViewports) {
                        const style = window.getComputedStyle(div);
                        if (style.overflow === 'auto' || style.overflow === 'scroll' || style.overflowY === 'auto' || style.overflowY === 'scroll') {
                            viewport = div;
                            break;
                        }
                    }
                }

                if (viewport) {
                    // Debug info
                    console.log('Scrolling chat to bottom:', {
                        scrollHeight: viewport.scrollHeight,
                        clientHeight: viewport.clientHeight,
                        currentScrollTop: viewport.scrollTop,
                    });

                    // Force immediate scroll to bottom
                    viewport.scrollTop = viewport.scrollHeight;

                    // Also try smooth scroll as backup
                    if (viewport.scrollTo) {
                        viewport.scrollTo({
                            top: viewport.scrollHeight,
                            behavior: 'auto', // Use auto instead of smooth for more reliable scrolling
                        });
                    }
                } else {
                    console.warn('Could not find scrollable viewport in ScrollArea');
                    console.log('ScrollArea element structure:', scrollAreaEl);
                }
            } catch (error) {
                console.warn('Chat scroll to bottom failed:', error);
            }
        }, 100); // Increased delay to ensure ScrollArea is fully rendered
    });
};

// Auto-scroll to bottom when new messages arrive or content changes (for streaming)
watch(
    messageCount,
    () => {
        scrollChatToBottom();
    },
    { flush: 'post' },
);

watch(
    totalContentLength,
    () => {
        // Only scroll during streaming (when there are streaming messages)
        const hasStreamingMessage = props.messages.some((msg) => msg.isStreaming);
        if (hasStreamingMessage) {
            throttledScrollToBottom(); // Use throttled version for streaming
        }
    },
    { flush: 'post' },
);

// Throttled scroll function for performance during streaming
let scrollTimeout: number | null = null;
const throttledScrollToBottom = () => {
    if (scrollTimeout) clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        scrollChatToBottom();
    }, 50) as unknown as number;
};

// Scroll to bottom when component is mounted
onMounted(() => {
    setTimeout(() => {
        scrollChatToBottom();
    }, 300); // Give more time for messages to render
});

// Methods
const handleSendMessage = () => {
    if (props.input.trim()) {
        emit('send-message');
        // Scroll to bottom after sending message
        setTimeout(() => {
            scrollChatToBottom();
        }, 100);
        nextTick(() => {
            try {
                if (inputRef.value && inputRef.value.$el) {
                    inputRef.value.$el.focus();
                } else if (inputRef.value && inputRef.value.focus) {
                    inputRef.value.focus();
                }
            } catch (error) {
                console.warn('Focus failed:', error);
            }
        });
    }
};

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSendMessage();
    }
};

const handleQuickAction = (action: string) => {
    emit('quick-action', action);
};

const formatTimestamp = (date: Date) => {
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
};

const copyMessage = async (content: string) => {
    try {
        await navigator.clipboard.writeText(content);
        // Could show a toast here
    } catch (err) {
        console.error('Failed to copy message:', err);
    }
};

// Note: formatMessageForPresentation function removed - now using Tiptap RichTextViewer

const suggestWithSelectedText = () => {
    if (hasSelectedText.value) {
        emit('update:input', `Can you help me improve this text: "${props.selectedText}"`);
    }
};
</script>

<template>
    <div class="flex h-full flex-col border-l bg-background">
        <!-- Chat Header -->
        <div class="flex flex-shrink-0 items-center justify-between border-b bg-muted/30 p-3">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600">
                    <Bot class="h-4 w-4 text-white" />
                </div>
                <div v-if="!isMinimized">
                    <h3 class="text-sm font-semibold">AI Assistant</h3>
                    <p class="text-xs text-muted-foreground">Chapter {{ chapterNumber }} Helper</p>
                </div>
            </div>

            <Button @click="emit('toggle-minimize')" variant="ghost" size="icon" class="h-8 w-8">
                <Minimize2 v-if="!isMinimized" class="h-4 w-4" />
                <Maximize2 v-else class="h-4 w-4" />
            </Button>
        </div>

        <!-- Minimized State -->
        <div v-if="isMinimized" class="flex flex-1 flex-col items-center justify-center space-y-2 p-2">
            <Button @click="emit('toggle-minimize')" variant="ghost" size="icon" class="h-12 w-12 rounded-full">
                <MessageSquare class="h-6 w-6" />
            </Button>
            <p class="-rotate-90 transform text-center text-xs whitespace-nowrap text-muted-foreground">Chat</p>
        </div>

        <!-- Full Chat Interface -->
        <template v-else>
            <!-- Quick Actions Bar -->
            <div class="flex-shrink-0 border-b bg-muted/20 p-2">
                <div class="grid grid-cols-2 gap-1">
                    <Button @click="handleQuickAction('analyze')" size="sm" variant="ghost" class="h-8 justify-start text-xs">
                        <Brain class="mr-1 h-3 w-3" />
                        Analyze
                    </Button>
                    <Button @click="handleQuickAction('improve')" size="sm" variant="ghost" class="h-8 justify-start text-xs">
                        <PenTool class="mr-1 h-3 w-3" />
                        Improve
                    </Button>
                    <Button @click="handleQuickAction('structure')" size="sm" variant="ghost" class="h-8 justify-start text-xs">
                        <Target class="mr-1 h-3 w-3" />
                        Structure
                    </Button>
                    <Button @click="handleQuickAction('citations')" size="sm" variant="ghost" class="h-8 justify-start text-xs">
                        <BookMarked class="mr-1 h-3 w-3" />
                        Citations
                    </Button>
                </div>
            </div>

            <!-- Chapter Context Panel -->
            <div class="flex-shrink-0 border-b bg-gradient-to-r from-green-50 to-blue-50 p-2 dark:from-green-950/20 dark:to-blue-950/20">
                <div class="flex items-start gap-2">
                    <Brain class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-600 dark:text-green-400" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-green-700 dark:text-green-300">AI Context:</p>
                        <p class="text-xs text-green-600 dark:text-green-400">
                            I can see your full chapter content ({{ Math.round(chapterContent.length / 1000) }}k chars) and will provide specific
                            advice
                        </p>
                    </div>
                </div>
            </div>

            <!-- Selected Text Context -->
            <div v-if="hasSelectedText" class="flex-shrink-0 border-b bg-blue-50 p-2 dark:bg-blue-950/20">
                <div class="flex items-start gap-2">
                    <Sparkles class="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-500" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-blue-700 dark:text-blue-300">Selected text:</p>
                        <p class="truncate text-xs text-blue-600 dark:text-blue-400">
                            "{{ selectedText.substring(0, 60) }}{{ selectedText.length > 60 ? '...' : '' }}"
                        </p>
                    </div>
                    <Button @click="suggestWithSelectedText" size="sm" variant="outline" class="h-6 px-2 text-xs"> Help </Button>
                </div>
            </div>

            <!-- Messages Area -->
            <ScrollArea ref="scrollContainer" class="min-h-0 flex-1">
                <div class="min-h-full space-y-4 p-3">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="['flex gap-3', message.type === 'user' ? 'justify-end' : 'justify-start']"
                    >
                        <!-- AI/System Message -->
                        <div v-if="message.type !== 'user'" class="flex max-w-[85%] gap-2">
                            <div
                                :class="[
                                    'mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full',
                                    message.type === 'ai' ? 'bg-gradient-to-br from-blue-500 to-purple-600' : 'bg-orange-500',
                                ]"
                            >
                                <Bot v-if="message.type === 'ai'" class="h-3 w-3 text-white" />
                                <AlertCircle v-else class="h-3 w-3 text-white" />
                            </div>

                            <div class="flex-1">
                                <div
                                    :class="[
                                        'rounded-lg p-3 text-sm',
                                        message.type === 'ai'
                                            ? 'border bg-muted'
                                            : 'border border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950/20',
                                    ]"
                                >
                                    <RichTextViewer :content="message.content" class="prose prose-sm dark:prose-invert max-w-none" />

                                    <!-- Streaming Indicator -->
                                    <div v-if="message.isStreaming" class="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                                        <div class="flex gap-1">
                                            <div class="h-1 w-1 animate-bounce rounded-full bg-primary"></div>
                                            <div class="h-1 w-1 animate-bounce rounded-full bg-primary" style="animation-delay: 0.1s"></div>
                                            <div class="h-1 w-1 animate-bounce rounded-full bg-primary" style="animation-delay: 0.2s"></div>
                                        </div>
                                        <span>streaming...</span>
                                    </div>

                                    <!-- Message Actions -->
                                    <div v-if="message.type === 'ai'" class="mt-2 flex items-center gap-1 border-t border-border/50 pt-2">
                                        <Button @click="copyMessage(message.content)" size="sm" variant="ghost" class="h-6 px-2 text-xs">
                                            <Copy class="mr-1 h-3 w-3" />
                                            Copy
                                        </Button>
                                        <Button size="sm" variant="ghost" class="h-6 px-2 text-xs">
                                            <ThumbsUp class="h-3 w-3" />
                                        </Button>
                                        <Button size="sm" variant="ghost" class="h-6 px-2 text-xs">
                                            <ThumbsDown class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>

                                <p class="mt-1 px-1 text-xs text-muted-foreground">
                                    {{ formatTimestamp(message.timestamp) }}
                                </p>
                            </div>
                        </div>

                        <!-- User Message -->
                        <div v-else class="flex max-w-[85%] gap-2">
                            <div class="flex-1 text-right">
                                <div class="inline-block rounded-lg bg-primary p-3 text-sm text-primary-foreground">
                                    <RichTextViewer :content="message.content" class="prose prose-sm prose-invert max-w-none" />
                                </div>
                                <p class="mt-1 px-1 text-xs text-muted-foreground">
                                    {{ formatTimestamp(message.timestamp) }}
                                </p>
                            </div>

                            <div class="mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary">
                                <User class="h-3 w-3 text-primary-foreground" />
                            </div>
                        </div>
                    </div>

                    <!-- Typing Indicator -->
                    <div v-if="isTyping" class="flex gap-2">
                        <div
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600"
                        >
                            <Bot class="h-3 w-3 text-white" />
                        </div>
                        <div class="max-w-[85%] rounded-lg border bg-muted p-3">
                            <div class="flex items-center gap-1">
                                <div class="flex gap-1">
                                    <div class="h-2 w-2 animate-bounce rounded-full bg-muted-foreground"></div>
                                    <div class="h-2 w-2 animate-bounce rounded-full bg-muted-foreground" style="animation-delay: 0.1s"></div>
                                    <div class="h-2 w-2 animate-bounce rounded-full bg-muted-foreground" style="animation-delay: 0.2s"></div>
                                </div>
                                <span class="ml-2 text-xs text-muted-foreground">AI is thinking...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </ScrollArea>

            <!-- Input Area -->
            <div class="flex-shrink-0 border-t bg-background p-3">
                <CompactRichTextEditor
                    ref="inputRef"
                    :model-value="input"
                    @update:model-value="emit('update:input', $event)"
                    @submit="handleSendMessage"
                    @keydown="handleKeydown"
                    :disabled="isTyping"
                    :show-toolbar="false"
                    placeholder="Ask about your chapter..."
                />

                <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                    <span>Press Enter to send, Shift+Enter for new line</span>
                    <Badge variant="secondary" class="text-xs"> {{ messages.length }} messages </Badge>
                </div>
            </div>
        </template>
    </div>
</template>
