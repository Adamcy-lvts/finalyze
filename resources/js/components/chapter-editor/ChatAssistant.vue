<!-- /resources/js/components/chapter-editor/ChatAssistant.vue -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CompactRichTextEditor from '@/components/ui/rich-text-editor/CompactRichTextEditor.vue';
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue';
import { ScrollArea } from '@/components/ui/scroll-area';
import FileUpload from './FileUpload.vue';
import ChatSearch from './ChatSearch.vue';
import ChatHistory from './ChatHistory.vue';
import {
    AlertCircle,
    BarChart3,
    BookMarked,
    Bot,
    Brain,
    CheckCircle,
    Clock,
    Copy,
    FileText,
    HelpCircle,
    Maximize2,
    MessageSquare,
    Minimize2,
    PenTool,
    Plus,
    RotateCcw as RotateCcwIcon,
    Search,
    Send,
    Square,
    Sparkles,
    Target,
    ThumbsDown,
    ThumbsUp,
    User,
    X,
    Zap,
    BookCheck,
    Building2,
    Type,
    BookOpen,
    Lightbulb,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch, withDefaults } from 'vue';

interface Message {
    id: number;
    type: 'user' | 'ai' | 'system';
    content: string;
    timestamp: Date;
    isStreaming?: boolean;
    failed?: boolean;
    lastPrompt?: string;
    lastQuickAction?: string;
}

interface Props {
    isMinimized: boolean;
    messages: Message[];
    isTyping: boolean;
    selectedText: string;
    chapterContent: string;
    chapterNumber: number;
    input: string;
    currentMode?: 'review' | 'assist';
    projectSlug: string;
    sessionId: string;
    isMobile?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    currentMode: 'assist',
    isMobile: false
});

const emit = defineEmits<{
    'update:input': [value: string];
    'send-message': [];
    'quick-action': [action: string];
    'retry-message': [message: Message];
    'stop-generation': [];
    'toggle-minimize': [];
    'change-mode': [mode: 'review' | 'assist'];
    'copy-message': [message: Message];
    'rate-message': [messageId: number, rating: number];
    'new-session': [];
    'chat-deleted': [];
    'chat-cleared': [];
}>();

const scrollContainer = ref();
const inputRef = ref();
const showFileUpload = ref(false);
const showSearch = ref(false);
const showHistory = ref(false);
const uploadedFiles = ref([]);

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

const copiedMessageId = ref<number | null>(null);

const copyMessage = async (message: Message) => {
    try {
        // Extract plain text from rich content for copying
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = message.content;
        const plainText = tempDiv.textContent || tempDiv.innerText || message.content;

        await navigator.clipboard.writeText(plainText);
        emit('copy-message', message);

        // Show "Copied!" feedback
        copiedMessageId.value = message.id;
        setTimeout(() => {
            copiedMessageId.value = null;
        }, 1500); // Show for 1.5 seconds
    } catch (err) {
        console.error('Failed to copy message:', err);
        // Show error feedback briefly
        copiedMessageId.value = -1; // Use -1 for error state
        setTimeout(() => {
            copiedMessageId.value = null;
        }, 1500);
    }
};

// Note: formatMessageForPresentation function removed - now using Tiptap RichTextViewer

const suggestWithSelectedText = () => {
    if (hasSelectedText.value) {
        emit('update:input', `Can you help me improve this text: "${props.selectedText}"`);
    }
};

const toggleFileUpload = () => {
    showFileUpload.value = !showFileUpload.value;
    if (showFileUpload.value) {
        showSearch.value = false;
        showHistory.value = false;
    }
};

const toggleSearch = () => {
    showSearch.value = !showSearch.value;
    if (showSearch.value) {
        showFileUpload.value = false;
        showHistory.value = false;
    }
};

const toggleHistory = () => {
    showHistory.value = !showHistory.value;
    if (showHistory.value) {
        showFileUpload.value = false;
        showSearch.value = false;
    }
};

const handleFileUploaded = (file: any) => {
    uploadedFiles.value.unshift(file);
    // Files are automatically included in chat context, no need to send a message
    console.log('File uploaded and will be included in chat context:', file);
};

const handleFileDeleted = (fileId: string) => {
    uploadedFiles.value = uploadedFiles.value.filter(f => f.id !== fileId);
};

const handleFilesLoaded = (files: any[]) => {
    uploadedFiles.value = files;
};

const handleSearchMessageSelected = (messageId: number, sessionId: string) => {
    // Could implement navigation to specific message in history
    console.log('Navigate to message:', messageId, 'in session:', sessionId);
    showSearch.value = false;
};

const startNewSession = () => {
    emit('new-session');
};

const handleChatDeleted = () => {
    emit('chat-deleted');
};

const handleChatCleared = () => {
    emit('chat-cleared');
};

const retryMessage = (message: Message) => {
    emit('retry-message', message);
};

const stopGeneration = () => {
    emit('stop-generation');
};
</script>

<template>
    <div :class="[
        'flex h-full flex-col bg-background',
        isMobile ? 'border-0' : 'border-l'
    ]">
        <!-- Chat Header -->
        <div :class="[
            'flex flex-shrink-0 items-center justify-between border-b bg-muted/30',
            isMobile ? 'p-4' : 'p-3'
        ]">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600">
                    <Bot class="h-4 w-4 text-white" />
                </div>
                <div v-if="!isMinimized">
                    <h3 class="text-sm font-semibold">AI Assistant</h3>
                    <p class="text-xs text-muted-foreground">
                        {{ currentMode === 'review' ? 'Academic Reviewer' : 'Writing Helper' }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1">
                <!-- Mode Toggle -->
                <div v-if="!isMinimized" class="flex rounded-md border p-1">
                    <Button
                        @click="emit('change-mode', 'assist')"
                        :variant="currentMode === 'assist' ? 'default' : 'ghost'"
                        :size="isMobile ? 'sm' : 'xs'"
                        :class="isMobile ? 'h-8 px-3 text-sm' : 'h-6 px-2 text-xs'"
                    >
                        ✍️ Assist
                    </Button>
                    <Button
                        @click="emit('change-mode', 'review')"
                        :variant="currentMode === 'review' ? 'default' : 'ghost'"
                        :size="isMobile ? 'sm' : 'xs'"
                        :class="isMobile ? 'h-8 px-3 text-sm' : 'h-6 px-2 text-xs'"
                    >
                        🔍 Review
                    </Button>
                </div>

                <!-- Start New Chat Button -->
                <Button
                    v-if="!isMinimized"
                    @click="startNewSession"
                    variant="outline"
                    :size="isMobile ? 'sm' : 'xs'"
                    :class="[
                        'gap-1',
                        isMobile ? 'h-8 px-3 text-sm' : 'h-6 px-2 text-xs'
                    ]"
                    title="Start a new chat session"
                >
                    <Plus :class="isMobile ? 'h-4 w-4' : 'h-3 w-3'" />
                    <span v-if="!isMobile">New</span>
                </Button>

                <Button
                    @click="toggleSearch"
                    variant="ghost"
                    size="icon"
                    :class="[
                        isMobile ? 'h-10 w-10' : 'h-8 w-8',
                        { 'bg-muted': showSearch }
                    ]"
                >
                    <Search :class="isMobile ? 'h-5 w-5' : 'h-4 w-4'" />
                </Button>

                <Button
                    @click="toggleHistory"
                    variant="ghost"
                    size="icon"
                    :class="[
                        isMobile ? 'h-10 w-10' : 'h-8 w-8',
                        { 'bg-muted': showHistory }
                    ]"
                >
                    <Clock :class="isMobile ? 'h-5 w-5' : 'h-4 w-4'" />
                </Button>

                <Button
                    @click="toggleFileUpload"
                    variant="ghost"
                    size="icon"
                    :class="[
                        isMobile ? 'h-10 w-10' : 'h-8 w-8',
                        { 'bg-muted': showFileUpload }
                    ]"
                >
                    <FileText :class="isMobile ? 'h-5 w-5' : 'h-4 w-4'" />
                </Button>

                <Button
                    v-if="!isMobile"
                    @click="emit('toggle-minimize')"
                    variant="ghost"
                    size="icon"
                    class="h-8 w-8"
                >
                    <Minimize2 v-if="!isMinimized" class="h-4 w-4" />
                    <Maximize2 v-else class="h-4 w-4" />
                </Button>

                <!-- Mobile Close Button -->
                <Button
                    v-if="isMobile"
                    @click="emit('toggle-minimize')"
                    variant="ghost"
                    size="icon"
                    class="h-10 w-10"
                >
                    <X class="h-5 w-5" />
                </Button>
            </div>
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
            <!-- Chat Mode Container with Transitions -->
            <div class="relative flex-1 overflow-hidden">
                <!-- Search Mode -->
                <Transition
                    name="slide-left"
                    mode="out-in"
                    appear
                >
                    <div v-if="showSearch" key="search" class="absolute inset-0 bg-background">
                        <ChatSearch
                            :project-slug="projectSlug"
                            :chapter-number="chapterNumber"
                            :show="showSearch"
                            @close="showSearch = false"
                            @message-selected="handleSearchMessageSelected"
                        />
                    </div>
                </Transition>

                <!-- History Mode -->
                <Transition
                    name="slide-right"
                    mode="out-in"
                    appear
                >
                    <div v-if="showHistory" key="history" class="absolute inset-0 bg-background overflow-y-auto">
                        <div class="p-4">
                            <ChatHistory
                                :project-slug="projectSlug"
                                :chapter-number="chapterNumber"
                                @chat-deleted="handleChatDeleted"
                                @chat-cleared="handleChatCleared"
                            />
                        </div>
                    </div>
                </Transition>

                <!-- Normal Chat Mode -->
                <Transition
                    name="slide-up"
                    mode="out-in"
                    appear
                >
                    <div v-if="!showSearch && !showHistory && !showFileUpload" key="chat" class="flex flex-col h-full">
                        <!-- Enhanced Quick Actions Bar -->
            <div :class="[
                'flex-shrink-0 border-b bg-muted/20',
                isMobile ? 'p-2' : 'p-1.5'
            ]">
                <!-- Review Mode Actions -->
                <div v-if="currentMode === 'review'" :class="[
                    'flex flex-wrap gap-1',
                    isMobile ? 'justify-center' : 'justify-start'
                ]">
                    <Button
                        @click="handleQuickAction('overall-review')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <BarChart3 :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Overall Review
                    </Button>
                    <Button
                        @click="handleQuickAction('test-knowledge')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <HelpCircle :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Test Knowledge
                    </Button>
                    <Button
                        @click="handleQuickAction('find-weaknesses')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <Target :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Find Weaknesses
                    </Button>
                    <Button
                        @click="handleQuickAction('citation-check')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <BookCheck :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Check Citations
                    </Button>
                    <Button
                        @click="handleQuickAction('structure-review')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <Building2 :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Review Structure
                    </Button>
                </div>

                <!-- Assist Mode Actions -->
                <div v-else :class="[
                    'flex flex-wrap gap-1',
                    isMobile ? 'justify-center' : 'justify-start'
                ]">
                    <Button
                        @click="handleQuickAction('improve-writing')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <PenTool :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Improve Writing
                    </Button>
                    <Button
                        @click="handleQuickAction('expand-section')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <Zap :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Expand Section
                    </Button>
                    <Button
                        @click="handleQuickAction('fix-grammar')"
                        size="sm"
                        variant="ghost"
                        :class="isMobile ? 'h-9 px-3 text-xs' : 'h-7 px-2.5 text-xs'"
                    >
                        <Type :class="isMobile ? 'mr-1.5 h-3.5 w-3.5' : 'mr-1 h-3 w-3'" />
                        Fix Grammar
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

                        <!-- File Upload Panel -->
                        <Transition name="slide-down" appear>
                            <div v-if="showFileUpload" class="flex-shrink-0 border-b bg-orange-50 p-3 dark:bg-orange-950/20">
                                <FileUpload
                                    :project-slug="projectSlug"
                                    :chapter-number="chapterNumber"
                                    :session-id="sessionId"
                                    :disabled="isTyping"
                                    @file-uploaded="handleFileUploaded"
                                    @file-deleted="handleFileDeleted"
                                    @files-loaded="handleFilesLoaded"
                                />
                            </div>
                        </Transition>

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
                                    <!-- Show content or streaming indicator -->
                                    <RichTextViewer
                                        v-if="message.content"
                                        :content="message.content"
                                        class="prose prose-sm dark:prose-invert max-w-none chat-message-ai"
                                    />

                                    <!-- Streaming Indicator for empty messages -->
                                    <div v-else-if="message.isStreaming" class="flex items-center gap-2 py-2">
                                        <div class="flex gap-1">
                                            <div class="h-2 w-2 animate-bounce rounded-full bg-muted-foreground/60"></div>
                                            <div class="h-2 w-2 animate-bounce rounded-full bg-muted-foreground/60" style="animation-delay: 0.1s"></div>
                                            <div class="h-2 w-2 animate-bounce rounded-full bg-muted-foreground/60" style="animation-delay: 0.2s"></div>
                                        </div>
                                        <span class="text-xs text-muted-foreground/80">Thinking...</span>
                                    </div>

                                    <!-- Enhanced Message Actions -->
                                    <div v-if="message.type === 'ai'" class="mt-2 flex items-center justify-between border-t border-border/50 pt-2">
                                        <div class="flex items-center gap-1">
                                            <!-- Retry button for failed messages -->
                                            <Button
                                                v-if="message.failed"
                                                @click="retryMessage(message)"
                                                size="sm"
                                                variant="outline"
                                                class="h-6 px-2 text-xs border-orange-300 text-orange-600 hover:bg-orange-50"
                                            >
                                                <RotateCcwIcon class="mr-1 h-3 w-3" />
                                                Try Again
                                            </Button>

                                            <Button
                                                @click="copyMessage(message)"
                                                size="sm"
                                                variant="ghost"
                                                class="h-6 px-2 text-xs"
                                                :disabled="copiedMessageId === message.id || copiedMessageId === -1"
                                            >
                                                <Copy v-if="copiedMessageId !== message.id && copiedMessageId !== -1" class="mr-1 h-3 w-3" />
                                                <CheckCircle v-else-if="copiedMessageId === message.id" class="mr-1 h-3 w-3 text-green-600" />
                                                <AlertCircle v-else class="mr-1 h-3 w-3 text-red-600" />

                                                <span v-if="copiedMessageId === message.id" class="text-green-600">Copied!</span>
                                                <span v-else-if="copiedMessageId === -1">Failed</span>
                                                <span v-else>Copy</span>
                                            </Button>
                                            <Button @click="emit('rate-message', message.id, 1)" size="sm" variant="ghost" class="h-6 px-2 text-xs">
                                                <ThumbsUp class="h-3 w-3" />
                                            </Button>
                                            <Button @click="emit('rate-message', message.id, -1)" size="sm" variant="ghost" class="h-6 px-2 text-xs">
                                                <ThumbsDown class="h-3 w-3" />
                                            </Button>
                                        </div>

                                        <!-- Model indicator -->
                                        <Badge variant="outline" class="text-xs">
                                            {{ currentMode === 'review' ? 'GPT-4o' : 'GPT-4o-mini' }}
                                        </Badge>
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
                                    <RichTextViewer :content="message.content" class="prose prose-sm prose-invert max-w-none chat-message-user" />
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
                <div class="relative">
                    <CompactRichTextEditor
                        ref="inputRef"
                        :model-value="input"
                        @update:model-value="emit('update:input', $event)"
                        @submit="handleSendMessage"
                        @keydown="handleKeydown"
                        :disabled="isTyping"
                        :show-toolbar="false"
                        placeholder="Ask about your chapter..."
                        class="pr-12"
                    />

                    <!-- Send/Stop Button -->
                    <div class="absolute right-2 top-1/2 -translate-y-1/2">
                        <Button
                            v-if="!isTyping"
                            @click="handleSendMessage"
                            size="sm"
                            variant="ghost"
                            class="h-8 w-8 p-0 rounded-full hover:bg-primary hover:text-primary-foreground"
                            :disabled="!input.trim()"
                        >
                            <Send class="h-4 w-4" />
                        </Button>
                        <Button
                            v-else
                            @click="stopGeneration"
                            size="sm"
                            variant="ghost"
                            class="h-8 w-8 p-0 rounded-full hover:bg-destructive hover:text-destructive-foreground"
                        >
                            <Square class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                    <span v-if="!isTyping">Press Enter to send, Shift+Enter for new line</span>
                    <span v-else class="flex items-center gap-1">
                        <div class="h-3 w-3 animate-spin rounded-full border-b border-current"></div>
                        Generating response...
                    </span>
                    <div class="flex items-center gap-2">
                        <Button
                            @click="startNewSession"
                            variant="outline"
                            size="sm"
                            class="h-6 px-2 text-xs"
                            :disabled="isTyping || messages.length === 0"
                        >
                            <Plus class="mr-1 h-3 w-3" />
                            New Session
                        </Button>
                        <Badge variant="secondary" class="text-xs"> {{ messages.length }} messages </Badge>
                    </div>
                </div>
            </div>
                    </div>
                </Transition>
            </div>
        </template>
    </div>
</template>

<style scoped>
/* Chat Message Font Styles */
:deep(.chat-message-ai) {
    font-family: 'Inter', 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
    font-size: 0.875rem;
    line-height: 1.6;
    font-weight: 400;
    letter-spacing: 0.01em;
}

:deep(.chat-message-ai p) {
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    line-height: 1.6;
}

:deep(.chat-message-ai p:last-child) {
    margin-bottom: 0;
}

:deep(.chat-message-ai strong) {
    font-weight: 600;
    color: hsl(var(--foreground));
}

:deep(.chat-message-ai em) {
    font-style: italic;
    color: hsl(var(--muted-foreground));
}

:deep(.chat-message-ai code) {
    font-family: 'JetBrains Mono', 'Fira Code', 'Monaco', 'Consolas', 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.8rem;
    background: hsl(var(--muted));
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-weight: 500;
}

:deep(.chat-message-ai ul, .chat-message-ai ol) {
    padding-left: 1.25rem;
    margin-bottom: 0.75rem;
}

:deep(.chat-message-ai li) {
    margin-bottom: 0.25rem;
    line-height: 1.5;
}

/* User Message Styles */
:deep(.chat-message-user) {
    font-family: 'Inter', 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif;
    font-size: 0.875rem;
    line-height: 1.5;
    font-weight: 400;
    letter-spacing: 0.01em;
}

:deep(.chat-message-user p) {
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
    line-height: 1.5;
}

:deep(.chat-message-user p:last-child) {
    margin-bottom: 0;
}

/* Responsive font sizing */
@media (max-width: 768px) {
    :deep(.chat-message-ai),
    :deep(.chat-message-user) {
        font-size: 0.8rem;
    }

    :deep(.chat-message-ai p),
    :deep(.chat-message-user p) {
        font-size: 0.8rem;
    }
}

/* Chat Input Field Styling */
:deep(.ProseMirror) {
    font-family: 'Inter', 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen', 'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue', sans-serif !important;
    font-size: 0.875rem !important;
    line-height: 1.5 !important;
    font-weight: 400 !important;
    letter-spacing: 0.01em !important;
}

:deep(.ProseMirror p) {
    margin: 0 !important;
    font-size: 0.875rem !important;
    line-height: 1.5 !important;
}

:deep(.ProseMirror p.is-editor-empty:first-child:before) {
    font-family: 'Inter', 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif !important;
    font-size: 0.875rem !important;
    font-weight: 400 !important;
    color: hsl(var(--muted-foreground)) !important;
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
    :deep(.chat-message-ai) {
        color: hsl(var(--foreground));
    }

    :deep(.chat-message-user) {
        color: hsl(var(--primary-foreground));
    }
}

/* Smooth Transitions for Chat Mode Changes */

/* Slide Left (for Search Mode) */
.slide-left-enter-active,
.slide-left-leave-active {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-left-enter-from {
    transform: translateX(-100%);
    opacity: 0;
}

.slide-left-leave-to {
    transform: translateX(-100%);
    opacity: 0;
}

/* Slide Right (for History Mode) */
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-right-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.slide-right-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

/* Slide Up (for Main Chat Mode) */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-up-enter-from {
    transform: translateY(30px);
    opacity: 0;
}

.slide-up-leave-to {
    transform: translateY(-30px);
    opacity: 0;
}

/* Slide Down (for File Upload Panel) */
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}

.slide-down-enter-from {
    transform: translateY(-100%);
    opacity: 0;
    max-height: 0;
}

.slide-down-leave-to {
    transform: translateY(-100%);
    opacity: 0;
    max-height: 0;
}

.slide-down-enter-to,
.slide-down-leave-from {
    transform: translateY(0);
    opacity: 1;
    max-height: 500px;
}

/* Additional easing for smoother motion */
.slide-left-enter-active,
.slide-right-enter-active,
.slide-up-enter-active {
    transition-delay: 0.05s;
}
</style>
