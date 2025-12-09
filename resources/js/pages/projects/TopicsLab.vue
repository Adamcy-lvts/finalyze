<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, reactive, nextTick, watch, onMounted, computed } from 'vue';
import { toast } from 'vue-sonner';
import { useWordBalance } from '@/composables/useWordBalance';
import {
    Send,
    Bot,
    User,
    Sparkles,
    ArrowLeft,
    CheckCircle2,
    MessageSquare,
    PanelLeftClose,
    PanelLeftOpen,
    Plus,
    History,
    Pencil,
    Check,
    X,
    Search,
    Save,
    BookmarkPlus,
    Trash2,
    Copy,
    RefreshCw,
    Square
} from 'lucide-vue-next';

interface Topic {
    id: number;
    title: string;
    description: string;
    difficulty?: string;
    resource_level?: string;
    feasibility_score?: number;
}

interface Project {
    id: number;
    slug: string;
    title: string;
    field_of_study: string;
    type: string;
}

interface Message {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    isTyping?: boolean;
}

const props = defineProps<{
    project: Project;
    topics: Topic[];
    sessionId: string;
    historySessions: Array<{
        id: string;
        title: string;
        date: string;
        message_count: number;
    }>;
    initialMessages?: Message[];
    initialTopic?: Topic;
}>();

const currentTopic = ref<Topic | null>(props.initialTopic || (props.topics && props.topics.length > 0 ? props.topics[0] : null));
const messages = ref<Message[]>(props.initialMessages || []);
const userInput = ref('');
const isSidebarOpen = ref(true);
const isSending = ref(false);
const chatContainer = ref<HTMLElement | null>(null);

// Real-time word balance updates
const { formattedBalance, balance, checkAndPrompt, hasWords } = useWordBalance();

const loadSession = (sessionId: string) => {
    router.visit(route('topics.lab', { project: props.project.slug, session_id: sessionId }));
};

const startNewChat = () => {
    // Navigate without session_id to force a new session
    router.visit(route('topics.lab', { project: props.project.slug, new: 'true' }));
};

// Inline edit state for session names
const editingSessionId = ref<string | null>(null);
const editingSessionName = ref('');

const startEditingSession = (session: { id: string; title: string }, event: Event) => {
    event.stopPropagation();
    editingSessionId.value = session.id;
    editingSessionName.value = session.title;
};

const cancelEditingSession = () => {
    editingSessionId.value = null;
    editingSessionName.value = '';
};

const saveSessionName = async (sessionId: string) => {
    if (!editingSessionName.value.trim()) {
        cancelEditingSession();
        return;
    }

    try {
        const response = await fetch(route('topics.chat.rename', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                session_id: sessionId,
                name: editingSessionName.value.trim(),
            }),
        });

        if (response.ok) {
            toast.success('Session renamed!');
            // Refresh the page to see updated name
            router.reload();
        } else {
            toast.error('Failed to rename session');
        }
    } catch (error) {
        toast.error('Failed to rename session');
    } finally {
        cancelEditingSession();
    }
};

// Delete session state and method
const isDeletingSession = ref(false);
const sessionToDelete = ref<string | null>(null);
const showDeleteDialog = ref(false);

const openDeleteDialog = (sessionId: string) => {
    sessionToDelete.value = sessionId;
    showDeleteDialog.value = true;
};

const closeDeleteDialog = () => {
    showDeleteDialog.value = false;
    sessionToDelete.value = null;
};

const confirmDeleteSession = async () => {
    if (isDeletingSession.value || !sessionToDelete.value) return;

    const sessionId = sessionToDelete.value;
    isDeletingSession.value = true;

    try {
        const response = await fetch(route('topics.chat.delete-session', props.project.slug), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ session_id: sessionId }),
        });

        if (response.ok) {
            toast.success('Conversation deleted');
            closeDeleteDialog();
            // If we deleted the current session, start a new one
            if (sessionId === props.sessionId) {
                startNewChat();
            } else {
                router.reload();
            }
        } else {
            toast.error('Failed to delete conversation');
        }
    } catch (error) {
        toast.error('Failed to delete conversation');
    } finally {
        isDeletingSession.value = false;
    }
};

// Copy message to clipboard
const copyMessage = async (content: string) => {
    try {
        // Strip HTML tags for cleaner copy
        const plainText = content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        await navigator.clipboard.writeText(plainText);
        toast.success('Copied to clipboard');
    } catch (error) {
        toast.error('Failed to copy');
    }
};

// Regenerate last AI response
const isRegenerating = ref(false);

const regenerateResponse = async () => {
    if (isRegenerating.value || !currentTopic.value) return;

    // Find the last user message
    const userMessages = messages.value.filter(m => m.role === 'user');
    if (userMessages.length === 0) {
        toast.error('No message to regenerate');
        return;
    }

    // Remove the last AI response
    const lastUserIndex = messages.value.findIndex(m => m.id === userMessages[userMessages.length - 1].id);
    if (lastUserIndex >= 0 && lastUserIndex < messages.value.length - 1) {
        messages.value = messages.value.slice(0, lastUserIndex + 1);
    }

    isRegenerating.value = true;

    // Placeholder for AI response
    const aiMsgId = (Date.now()).toString();
    messages.value.push({
        id: aiMsgId,
        role: 'assistant',
        content: '',
        isTyping: true
    });
    scrollToBottom();

    try {
        const response = await fetch(route('topics.chat', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                messages: messages.value.filter(m => !m.isTyping).map(m => ({ role: m.role, content: m.content })),
                topic_context: currentTopic.value,
                session_id: props.sessionId,
            }),
        });

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        if (!response.body) throw new Error('No response body');

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let aiContent = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    try {
                        const data = JSON.parse(line.slice(6));
                        if (data.content) {
                            aiContent += data.content;
                            const idx = messages.value.findIndex(m => m.id === aiMsgId);
                            if (idx !== -1) {
                                messages.value[idx].content = aiContent;
                                messages.value[idx].isTyping = false;
                            }
                            scrollToBottom();
                        }
                    } catch (e) {
                        // ignore parse errors
                    }
                }
            }
        }
    } catch (error) {
        console.error('Regenerate error:', error);
        toast.error('Failed to regenerate response');
        // Remove failed message
        const idx = messages.value.findIndex(m => m.id === aiMsgId);
        if (idx !== -1) {
            messages.value.splice(idx, 1);
        }
    } finally {
        isRegenerating.value = false;
        const idx = messages.value.findIndex(m => m.id === aiMsgId);
        if (idx !== -1) {
            messages.value[idx].isTyping = false;
        }
    }
};

// Save refined topic state
const showSaveTopicDialog = ref(false);
const topicToSave = ref({ title: '', description: '' });
const isSavingTopic = ref(false);

// Detect suggested topics in AI responses (quoted text in bullet points)
const extractSuggestedTopics = (content: string): string[] => {
    const topics: string[] = [];
    // Match patterns like: "Topic Title Here" or *"Topic Title"*
    const quotedPattern = /"([^"]{20,200})"/g;
    let match;
    while ((match = quotedPattern.exec(content)) !== null) {
        const topic = match[1].trim();
        // Filter out common non-topic phrases
        if (!topic.toLowerCase().startsWith('how ') &&
            !topic.toLowerCase().startsWith('what ') &&
            !topic.toLowerCase().startsWith('why ') &&
            topic.length > 30) {
            topics.push(topic);
        }
    }
    return topics;
};

// Open save dialog with pre-filled topic
const openSaveTopicDialog = (title: string) => {
    topicToSave.value = { title, description: '' };
    showSaveTopicDialog.value = true;
};

// Save refined topic to database
const saveRefinedTopic = async () => {
    if (!topicToSave.value.title.trim()) {
        toast.error('Please enter a topic title');
        return;
    }

    isSavingTopic.value = true;

    try {
        const response = await fetch(route('topics.chat.save-topic', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                title: topicToSave.value.title.trim(),
                description: topicToSave.value.description.trim(),
                session_id: props.sessionId,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            toast.success('Topic saved successfully!');
            showSaveTopicDialog.value = false;
            // Reload to show the new topic in the sidebar
            router.reload();
        } else {
            toast.error('Failed to save topic');
        }
    } catch (error) {
        toast.error('Failed to save topic');
    } finally {
        isSavingTopic.value = false;
    }
};

const stripHtml = (html: string) => {
    const tmp = document.createElement('DIV');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
};

// Use all topics directly without filtering
const topics = computed(() => props.topics || []);

// Track previous topic to detect topic changes
let isFirstLoad = true;

// Initialize chat with a welcome message when topic changes
watch(currentTopic, (newTopic, oldTopic) => {
    if (!newTopic) return;

    // Skip resetting on initial page load if we have loaded messages from history
    if (isFirstLoad) {
        isFirstLoad = false;
        // Only set welcome message if no messages were loaded
        if (messages.value.length === 0) {
            messages.value = [
                {
                    id: 'system-welcome',
                    role: 'assistant',
                    content: `Hi! I see you're interested in **${stripHtml(newTopic.title || '')}**. How can I help you refine or understand this topic better?`
                }
            ];
        }
        return;
    }

    // When switching topics (not first load), reset messages with new welcome
    messages.value = [
        {
            id: 'system-welcome-' + Date.now(),
            role: 'assistant',
            content: `Hi! I see you're interested in **${stripHtml(newTopic.title || '')}**. How can I help you refine or understand this topic better?`
        }
    ];
}, { immediate: true });

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

// Abort controller for stopping generation
let abortController: AbortController | null = null;

const stopGeneration = () => {
    if (abortController) {
        abortController.abort();
        abortController = null;
        isSending.value = false;
        toast.info('Generation stopped');
    }
};

const sendMessage = async () => {
    if (!userInput.value.trim() || !currentTopic.value || isSending.value) return;

    const userMsg: Message = {
        id: Date.now().toString(),
        role: 'user',
        content: userInput.value.trim()
    };

    messages.value.push(userMsg);
    userInput.value = '';
    isSending.value = true;
    scrollToBottom();

    // Placeholder for AI response
    const aiMsgId = (Date.now() + 1).toString();
    messages.value.push({
        id: aiMsgId,
        role: 'assistant',
        content: '',
        isTyping: true
    });

    // Create abort controller for this request
    abortController = new AbortController();

    try {
        const response = await fetch(route('topics.chat', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                messages: messages.value.filter(m => !m.isTyping).map(m => ({ role: m.role, content: m.content })),
                topic_context: currentTopic.value,
                session_id: props.sessionId,
            }),
            signal: abortController.signal,
        });

        // Handle insufficient balance error
        if (response.status === 402) {
            const errorData = await response.json();
            toast.error(errorData.message || 'Insufficient word balance');
            // Remove the typing indicator
            const idx = messages.value.findIndex(m => m.id === aiMsgId);
            if (idx !== -1) {
                messages.value.splice(idx, 1);
            }
            isSending.value = false;
            return;
        }

        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }

        if (!response.body) throw new Error('No response body');

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let aiContent = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value);
            const lines = chunk.split('\n\n');

            for (const line of lines) {
                if (line.startsWith('data: ')) {
                    try {
                        const data = JSON.parse(line.slice(6));
                        if (data.content) {
                            aiContent += data.content;
                            // Update the last message
                            const idx = messages.value.findIndex(m => m.id === aiMsgId);
                            if (idx !== -1) {
                                messages.value[idx].content = aiContent;
                                messages.value[idx].isTyping = false;
                            }
                            scrollToBottom();
                        }
                    } catch (e) {
                        // ignore parse errors for partial chunks
                    }
                }
            }
        }
    } catch (error: any) {
        if (error.name === 'AbortError') {
            // User cancelled - keep partial content if any
            const idx = messages.value.findIndex(m => m.id === aiMsgId);
            if (idx !== -1) {
                if (!messages.value[idx].content) {
                    messages.value.splice(idx, 1);
                } else {
                    messages.value[idx].isTyping = false;
                }
            }
        } else {
            console.error('Chat error:', error);
            toast.error('Failed to get response');
        }
    } finally {
        isSending.value = false;
        abortController = null;
        // Ensure typing indicator is gone
        const idx = messages.value.findIndex(m => m.id === aiMsgId);
        if (idx !== -1) {
            messages.value[idx].isTyping = false;
        }
    }
};

const selectTopic = async () => {
    if (!currentTopic.value) return;

    // Logic to select this topic for the project
    // This reuses the existing logic from TopicSelection.vue somewhat
    const loadingToast = toast.loading('Setting topic...');

    try {
        const title = stripHtml(currentTopic.value.title);
        const desc = stripHtml(currentTopic.value.description); // Simplified for now, or keep HTML

        const response = await fetch(route('topics.select', props.project.slug), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                topic: title,
                title: title.substring(0, 100), // Enforce limit if any
                description: currentTopic.value.description // keep HTML for storage
            }),
        });

        if (response.ok) {
            toast.dismiss(loadingToast);
            toast.success('Topic selected successfully!');
            router.visit(route('projects.topic-approval', props.project.slug));
        } else {
            throw new Error('Failed');
        }
    } catch (e) {
        toast.dismiss(loadingToast);
        toast.error('Failed to select topic');
    }
};

const formatMessageContent = (content: string) => {
    if (!content) return '';

    // Replace markdown headings with bold/large text
    let formatted = content
        .replace(/^### (.*$)/gim, '<strong>$1</strong>')
        .replace(/^## (.*$)/gim, '<strong class="text-lg">$1</strong>')
        .replace(/^# (.*$)/gim, '<strong class="text-xl">$1</strong>');

    // Bold with **
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Lists - bullets
    formatted = formatted.replace(/^\s*-\s+(.*$)/gim, '<li class="ml-4 list-disc">$1</li>');

    // List - numbered
    formatted = formatted.replace(/^\s*\d+\.\s+(.*$)/gim, '<li class="ml-4 list-decimal">$1</li>');

    // Wrap lists if adjacent
    // This is a simple regex that won't catch everything perfectly but helps
    // Ideally we'd use a real parser, but this regex replacement is safer than full innerHTML replacement without a library

    // Newlines to breaks
    formatted = formatted.replace(/\n/g, '<br>');

    return formatted;
};

// Persist sidebar mode in localStorage
const getSavedSidebarMode = (): 'topics' | 'history' => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('topicsLab_sidebarMode');
        if (saved === 'topics' || saved === 'history') return saved;
    }
    return 'topics';
};

const sidebarMode = ref<'topics' | 'history'>(getSavedSidebarMode());

watch(sidebarMode, (newMode) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem('topicsLab_sidebarMode', newMode);
    }
});
</script>

<template>
    <AppLayout title="Topic Lab">
        <div class="flex h-[calc(100vh-4rem)] bg-background overflow-hidden relative min-h-0">

            <!-- Sidebar -->
            <div
                :class="['w-80 border-r border-border/50 bg-muted/10 flex flex-col transition-all duration-300 min-h-0', isSidebarOpen ? 'translate-x-0' : '-translate-x-full absolute h-full z-20']">
                <div class="p-4 border-b border-border/50">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="p-1.5 bg-primary/10 rounded-md">
                            <component :is="sidebarMode === 'topics' ? Sparkles : History"
                                class="h-4 w-4 text-primary" />
                        </div>
                        <h2 class="font-semibold text-foreground">{{ sidebarMode === 'topics' ? 'Generated Topics' :
                            'Chat History' }}</h2>
                    </div>

                    <div class="flex bg-muted rounded-lg p-1">
                        <button @click="sidebarMode = 'topics'"
                            :class="['flex-1 flex items-center justify-center gap-2 text-xs font-medium py-1.5 rounded-md transition-all', sidebarMode === 'topics' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']">
                            <Sparkles class="h-3 w-3" />
                            Topics
                        </button>
                        <button @click="sidebarMode = 'history'"
                            :class="['flex-1 flex items-center justify-center gap-2 text-xs font-medium py-1.5 rounded-md transition-all', sidebarMode === 'history' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']">
                            <History class="h-3 w-3" />
                            History
                        </button>
                    </div>
                </div>

                <ScrollArea class="flex-1 min-h-0">
                    <div v-if="sidebarMode === 'topics'" class="p-3 space-y-2">
                        <!-- Topics List -->
                        <div v-if="topics.length === 0" class="text-center py-6 text-muted-foreground text-sm">
                            <Sparkles class="h-6 w-6 mx-auto mb-2 opacity-50" />
                            <p>No topics available</p>
                        </div>
                        <div v-for="topic in topics" :key="topic.id" @click="currentTopic = topic"
                            :class="['p-3 rounded-lg cursor-pointer border transition-all text-left hover:border-primary/30',
                                currentTopic?.id === topic.id ? 'bg-primary/5 border-primary/50 shadow-sm' : 'bg-card border-transparent hover:bg-card/80']">
                            <h3
                                :class="['text-sm font-medium mb-1 line-clamp-2', currentTopic?.id === topic.id ? 'text-primary' : 'text-foreground']">
                                {{ stripHtml(topic.title) }}
                            </h3>
                            <div class="flex items-center gap-2">
                                <Badge variant="secondary" class="text-[10px] h-5">{{ topic.difficulty || 'Intermediate'
                                }}</Badge>
                                <span v-if="topic.feasibility_score" class="text-[10px] text-muted-foreground">{{
                                    topic.feasibility_score }}% match</span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-3 space-y-2">
                        <Button variant="outline" class="w-full mb-3" @click="startNewChat">
                            <Plus class="h-4 w-4 mr-2" />
                            New Conversation
                        </Button>
                        <div v-if="historySessions.length === 0" class="text-center py-8 text-muted-foreground text-sm">
                            <History class="h-8 w-8 mx-auto mb-2 opacity-50" />
                            <p>No previous conversations</p>
                        </div>
                        <div v-else>
                            <div v-for="session in historySessions" :key="session.id"
                                @click="editingSessionId !== session.id && loadSession(session.id)"
                                :class="['p-3 rounded-lg cursor-pointer border transition-all text-left hover:border-primary/30 group',
                                    props.sessionId === session.id ? 'bg-primary/5 border-primary/50 shadow-sm' : 'bg-card border-transparent hover:bg-card/80']">

                                <!-- Editing Mode -->
                                <div v-if="editingSessionId === session.id" class="flex items-center gap-2" @click.stop>
                                    <input v-model="editingSessionName"
                                        class="flex-1 text-sm px-2 py-1 bg-background border border-border rounded focus:outline-none focus:ring-1 focus:ring-primary"
                                        @keydown.enter="saveSessionName(session.id)"
                                        @keydown.escape="cancelEditingSession" autofocus />
                                    <button @click="saveSessionName(session.id)"
                                        class="p-1 hover:bg-green-500/20 rounded text-green-500">
                                        <Check class="h-4 w-4" />
                                    </button>
                                    <button @click="cancelEditingSession"
                                        class="p-1 hover:bg-red-500/20 rounded text-red-500">
                                        <X class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Display Mode -->
                                <div v-else class="flex items-center justify-between">
                                    <h3
                                        :class="['text-sm font-medium line-clamp-1 flex-1', props.sessionId === session.id ? 'text-primary' : 'text-foreground']">
                                        {{ session.title }}
                                    </h3>
                                    <div class="flex items-center gap-1">
                                        <button @click="startEditingSession(session, $event)"
                                            class="opacity-0 group-hover:opacity-100 p-1 hover:bg-muted rounded transition-opacity"
                                            title="Rename">
                                            <Pencil class="h-3 w-3 text-muted-foreground" />
                                        </button>
                                        <button @click.stop="openDeleteDialog(session.id)"
                                            :disabled="isDeletingSession && sessionToDelete === session.id"
                                            class="opacity-0 group-hover:opacity-100 p-1 hover:bg-red-500/20 rounded transition-opacity"
                                            title="Delete">
                                            <Trash2 class="h-3 w-3 text-red-500" />
                                        </button>
                                    </div>
                                </div>

                                <div v-if="editingSessionId !== session.id" class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-muted-foreground flex items-center gap-1">
                                        <MessageSquare class="h-3 w-3" /> {{ session.message_count }} msgs
                                    </span>
                                    <span class="text-[10px] text-muted-foreground ml-auto">{{ session.date }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </ScrollArea>

                <div class="p-4 border-t border-border/50 flex justify-between items-center bg-background/50">
                    <Button variant="ghost" size="icon" @click="isSidebarOpen = false" class="md:hidden">
                        <PanelLeftClose class="h-4 w-4" />
                    </Button>
                    <Button variant="outline" class="w-full" @click="router.visit(route('projects.topics.index'))">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Library
                    </Button>
                </div>
            </div>

            <!-- Toggle Button for Sidebar (when closed) -->
            <div v-if="!isSidebarOpen" class="absolute left-4 top-4 z-30">
                <Button variant="outline" size="icon" @click="isSidebarOpen = true" class="shadow-md bg-background">
                    <PanelLeftOpen class="h-4 w-4" />
                </Button>
            </div>

            <!-- Main Chat Area -->
            <div class="flex-1 flex flex-col h-full overflow-hidden bg-background min-h-0">
                <!-- Header -->
                <div
                    class="h-16 border-b border-border/50 flex items-center justify-between px-6 bg-background/50 backdrop-blur-sm z-10">
                    <div class="pl-8 md:pl-0">
                        <Badge variant="outline" class="mb-1 text-xs text-muted-foreground">{{ project.field_of_study }}
                        </Badge>
                        <h1 class="font-semibold text-foreground flex items-center gap-2">
                            Topic Laboratory
                            <span
                                class="text-xs font-normal text-muted-foreground bg-muted px-2 py-0.5 rounded-full hidden sm:inline-flex">AI
                                Assistant</span>
                        </h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="hidden sm:block text-right mr-4">
                            <div class="text-xs text-muted-foreground uppercase tracking-wider font-medium">Current
                                Project</div>
                            <div class="text-sm font-medium">{{ project.title || 'Untitled' }}</div>
                        </div>
                        <Button :disabled="!currentTopic || isSending" @click="selectTopic"
                            class="gap-2 shadow-lg shadow-primary/20">
                            <CheckCircle2 class="h-4 w-4" />
                            Select Topic
                        </Button>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="flex-1 overflow-y-auto px-4 py-6 scroll-smooth" ref="chatContainer">
                    <div class="max-w-3xl mx-auto space-y-6">
                        <!-- Topic Summary Card -->
                        <div v-if="currentTopic" class="mb-8 animate-in fade-in slide-in-from-top-4 duration-700">
                            <div
                                class="rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 to-transparent p-6 shadow-sm">
                                <div class="flex items-start gap-4">
                                    <div class="p-2 bg-primary/10 rounded-lg text-primary mt-1">
                                        <Sparkles class="h-5 w-5" />
                                    </div>
                                    <div class="flex-1 space-y-2">
                                        <SafeHtmlText :content="currentTopic.title" as="h3"
                                            class="text-xl font-bold text-foreground leading-tight" />
                                        <SafeHtmlText :content="currentTopic.description" as="div"
                                            class="text-sm text-muted-foreground leading-relaxed pl-1 border-l-2 border-primary/20" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Messages -->
                        <template v-if="messages.length">
                            <div v-for="msg in messages" :key="msg.id"
                                :class="['flex gap-4 animate-in fade-in slide-in-from-bottom-2', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                                <div v-if="msg.role === 'assistant'"
                                    class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0 border border-primary/20">
                                    <Bot class="h-4 w-4 text-primary" />
                                </div>

                                <div :class="['max-w-[85%] rounded-2xl px-5 py-3 shadow-sm text-sm leading-relaxed',
                                    msg.role === 'user'
                                        ? 'bg-primary text-primary-foreground rounded-tr-none'
                                        : 'bg-muted/50 border border-border/50 text-foreground rounded-tl-none']">
                                    <div v-if="msg.isTyping" class="flex gap-1 items-center h-5 px-1">
                                        <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce"
                                            style="animation-delay: 0ms"></span>
                                        <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce"
                                            style="animation-delay: 150ms"></span>
                                        <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce"
                                            style="animation-delay: 300ms"></span>
                                    </div>
                                    <div v-else class="markdown-body">
                                        <SafeHtmlText :content="formatMessageContent(msg.content)" />

                                        <!-- Clickable Suggested Topics -->
                                        <div v-if="msg.role === 'assistant' && extractSuggestedTopics(msg.content).length > 0"
                                            class="mt-4 pt-3 border-t border-border/30">
                                            <div class="text-xs text-muted-foreground mb-2 flex items-center gap-1">
                                                <BookmarkPlus class="h-3 w-3" />
                                                Click to save as topic:
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button v-for="(topic, idx) in extractSuggestedTopics(msg.content)"
                                                    :key="idx" @click="openSaveTopicDialog(topic)"
                                                    class="text-xs px-3 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary border border-primary/30 rounded-full transition-colors flex items-center gap-1.5 text-left">
                                                    <Save class="h-3 w-3 shrink-0" />
                                                    <span class="line-clamp-1">{{ topic.length > 60 ? topic.slice(0, 60)
                                                        + '...' : topic }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Message Actions -->
                                <div v-if="!msg.isTyping"
                                    :class="['flex items-center gap-1 mt-1', msg.role === 'user' ? 'justify-end mr-12' : 'justify-start ml-12']">
                                    <button @click="copyMessage(msg.content)"
                                        class="p-1.5 hover:bg-muted rounded-md transition-colors text-muted-foreground hover:text-foreground"
                                        title="Copy message">
                                        <Copy class="h-3 w-3" />
                                    </button>
                                    <button
                                        v-if="msg.role === 'assistant' && msg.id === messages.filter(m => m.role === 'assistant' && !m.isTyping).slice(-1)[0]?.id"
                                        @click="regenerateResponse" :disabled="isRegenerating || isSending"
                                        class="p-1.5 hover:bg-muted rounded-md transition-colors text-muted-foreground hover:text-foreground disabled:opacity-50"
                                        title="Regenerate response">
                                        <RefreshCw :class="['h-3 w-3', isRegenerating ? 'animate-spin' : '']" />
                                    </button>
                                </div>

                                <div v-if="msg.role === 'user'"
                                    class="h-8 w-8 rounded-full bg-muted flex items-center justify-center shrink-0 border border-border">
                                    <User class="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-4 border-t border-border/50 bg-background/80 backdrop-blur-md">
                    <div class="max-w-3xl mx-auto relative">
                        <Textarea v-model="userInput"
                            placeholder="Ask the AI to refine this topic, explain methodologies, or suggest improvements..."
                            class="min-h-[3rem] max-h-32 pr-12 py-3 bg-muted/30 focus:bg-background resize-none rounded-xl"
                            @keydown.enter.exact.prevent="sendMessage"
                            @keydown.shift.enter.prevent="userInput += '\n'" />

                        <!-- Stop button during generation -->
                        <Button v-if="isSending"
                            class="absolute right-2 bottom-2 h-8 w-8 rounded-lg bg-red-500 hover:bg-red-600" size="icon"
                            @click="stopGeneration" title="Stop generation">
                            <Square class="h-4 w-4 fill-current" />
                        </Button>

                        <!-- Send button when not generating -->
                        <Button v-else class="absolute right-2 bottom-2 h-8 w-8 rounded-lg" size="icon"
                            :disabled="!userInput.trim() || !currentTopic" @click="sendMessage">
                            <Send class="h-4 w-4" />
                        </Button>
                    </div>
                    <p class="text-center text-[10px] text-muted-foreground mt-2">
                        AI can make mistakes. Review generated content carefully.
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Save Topic Dialog -->
    <Dialog v-model:open="showSaveTopicDialog">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <BookmarkPlus class="h-5 w-5 text-primary" />
                    Save Refined Topic
                </DialogTitle>
                <DialogDescription>
                    Save this topic to your project's topic library for future reference or selection.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Topic Title</label>
                    <Input v-model="topicToSave.title" placeholder="Enter the refined topic title..." class="w-full" />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Description (Optional)</label>
                    <Textarea v-model="topicToSave.description"
                        placeholder="Add a brief description of this topic, its scope, methodology, etc..."
                        class="min-h-[100px]" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showSaveTopicDialog = false" :disabled="isSavingTopic">
                    Cancel
                </Button>
                <Button @click="saveRefinedTopic" :disabled="!topicToSave.title.trim() || isSavingTopic">
                    <Save class="h-4 w-4 mr-2" />
                    {{ isSavingTopic ? 'Saving...' : 'Save Topic' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Delete Session Confirmation Dialog -->
    <AlertDialog :open="showDeleteDialog" @update:open="(val) => { if (!val) closeDeleteDialog() }">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle class="flex items-center gap-2">
                    <Trash2 class="h-5 w-5 text-red-500" />
                    Delete Conversation
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete this conversation? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteDialog" :disabled="isDeletingSession">
                    Cancel
                </AlertDialogCancel>
                <AlertDialogAction @click="confirmDeleteSession" :disabled="isDeletingSession"
                    class="bg-red-500 hover:bg-red-600 text-white">
                    {{ isDeletingSession ? 'Deleting...' : 'Delete' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>
.markdown-body :deep(p) {
    margin-bottom: 0.5em;
}

.markdown-body :deep(ul) {
    list-style-type: disc;
    padding-left: 1.5em;
    margin-bottom: 0.5em;
}
</style>
