<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import { ScrollArea } from '@/components/ui/scroll-area';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogFooter, 
    DialogDescription 
} from '@/components/ui/dialog';
import { 
    AlertDialog, 
    AlertDialogAction, 
    AlertDialogCancel, 
    AlertDialogContent, 
    AlertDialogDescription, 
    AlertDialogFooter, 
    AlertDialogHeader, 
    AlertDialogTitle 
} from '@/components/ui/alert-dialog';
import { 
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger
} from '@/components/ui/tooltip';
import SafeHtmlText from '@/components/SafeHtmlText.vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, watch, onMounted, onUnmounted, computed, nextTick } from 'vue';
import { toast } from 'vue-sonner';
import { useWordBalance } from '@/composables/useWordBalance';
import {
    Send, Bot, User, Sparkles, ArrowLeft, CheckCircle2, MessageSquare,
    PanelLeftClose, PanelLeftOpen, Plus, History, Pencil, Check, X,
    Save, BookmarkPlus, Trash2, Copy, RefreshCw, Square
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
    returnToSelection?: boolean;
    historySessions: Array<{
        id: string;
        title: string;
        date: string;
        message_count: number;
    }>;
    initialMessages?: Message[];
    initialTopic?: Topic;
}>();

// --- State ---
const currentTopic = ref<Topic | null>(props.initialTopic || (props.topics && props.topics.length > 0 ? props.topics[0] : null));
const messages = ref<Message[]>(props.initialMessages || []);
const userInput = ref('');
const isSending = ref(false);
const chatContainer = ref<HTMLElement | null>(null);
const isMobile = ref(false);
const isSidebarOpen = ref(true);

// Word balance
const { formattedBalance, balance, checkAndPrompt, hasWords } = useWordBalance();

const suppressWelcomeReset = ref(false);

// Input resize monitoring
const checkMobile = () => {
    if (typeof window === 'undefined') return;
    isMobile.value = window.innerWidth < 1024; // Treat lg and below as mobile-ish for sidebar layout or just md? Let's stick to lg for overlay sidebar
    // Actually let's stick to md (768) as breakpoint for overlay vs side-by-side
    // But typically 1024 is where sidebars might start colliding if chat is wide.
    // Let's use 1024 (lg) as the breakpoint where sidebar becomes absolute overlay or hidden.
    // The previous code had 80rem fixed width sidebar.
    
    // Let's align with the template logic
    isMobile.value = window.innerWidth < 1024; 
    
    if (window.innerWidth < 1024) {
        // On mobile/tablet, default closed
        // Only close if we just resized into mobile
         // logic can be simpler: just reactive isMobile
    } else {
        isSidebarOpen.value = true;
    }
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    // Initial scroll
    scrollToBottom();
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
});

// Sidebar Mode
const getSavedSidebarMode = (): 'topics' | 'history' => {
    if (typeof window !== 'undefined') {
        const saved = localStorage.getItem('topicsLab_sidebarMode');
        if (saved === 'topics' || saved === 'history') return saved;
    }
    return 'topics';
};
const sidebarMode = ref<'topics' | 'history'>(getSavedSidebarMode());
watch(sidebarMode, (newMode) => {
    if (typeof window !== 'undefined') localStorage.setItem('topicsLab_sidebarMode', newMode);
});

// --- Navigation & Sessions ---
const loadSession = (sessionId: string) => {
    router.visit(route('topics.lab', { project: props.project.slug, session_id: sessionId }));
};

const startNewChat = () => {
    router.visit(route('topics.lab', { project: props.project.slug, new: 'true' }));
};

const backToLibrary = () => {
    router.visit(route('projects.topics.index'));
};

const backToSelection = () => {
    router.visit(route('projects.topic-selection', props.project.slug));
};

// --- Renaming Session ---
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
            body: JSON.stringify({ session_id: sessionId, name: editingSessionName.value.trim() }),
        });
        if (response.ok) {
            toast.success('Session renamed!');
            router.reload();
        } else {
            toast.error('Failed to rename session');
        }
    } catch (e) {
        toast.error('Failed to rename session');
    } finally {
        cancelEditingSession();
    }
};

// --- Deleting Session ---
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
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            body: JSON.stringify({ session_id: sessionId }),
        });
        if (response.ok) {
            toast.success('Conversation deleted');
            closeDeleteDialog();
            if (sessionId === props.sessionId) startNewChat();
            else router.reload();
        } else {
            toast.error('Failed to delete conversation');
        }
    } catch {
        toast.error('Failed to delete conversation');
    } finally {
        isDeletingSession.value = false;
    }
};

// --- Copy & Regenerate ---
const copyMessage = async (content: string) => {
    try {
        const plainText = content.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        await navigator.clipboard.writeText(plainText);
        toast.success('Copied to clipboard');
    } catch {
        toast.error('Failed to copy');
    }
};

const isRegenerating = ref(false);
const regenerateResponse = async () => {
    if (isRegenerating.value || !currentTopic.value) return;
    const userMessages = messages.value.filter(m => m.role === 'user');
    if (userMessages.length === 0) {
        toast.error('No message to regenerate');
        return;
    }
    // Remove last AI response if exists
    const lastUserIndex = messages.value.findIndex(m => m.id === userMessages[userMessages.length - 1].id);
    if (lastUserIndex >= 0 && lastUserIndex < messages.value.length - 1) {
        messages.value = messages.value.slice(0, lastUserIndex + 1);
    }
    
    isRegenerating.value = true;
    const aiMsgId = Date.now().toString();
    messages.value.push({ id: aiMsgId, role: 'assistant', content: '', isTyping: true });
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

        if (!response.ok) throw new Error(`Server error: ${response.status}`);
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
                    } catch {}
                }
            }
        }
    } catch (error) {
        console.error('Regenerate error:', error);
        toast.error('Failed to regenerate response');
        messages.value = messages.value.filter(m => m.id !== aiMsgId);
    } finally {
        isRegenerating.value = false;
        const idx = messages.value.findIndex(m => m.id === aiMsgId);
        if (idx !== -1) messages.value[idx].isTyping = false;
    }
};

// --- Chat Logic ---
let abortController: AbortController | null = null;
const stopGeneration = () => {
    if (abortController) {
        abortController.abort();
        abortController = null;
        isSending.value = false;
        toast.info('Generation stopped');
    }
};

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
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

    const aiMsgId = (Date.now() + 1).toString();
    messages.value.push({ id: aiMsgId, role: 'assistant', content: '', isTyping: true });

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

        if (response.status === 402) {
            const errorData = await response.json();
            toast.error(errorData.message || 'Insufficient word balance');
            messages.value = messages.value.filter(m => m.id !== aiMsgId);
            isSending.value = false;
            return;
        }

        if (!response.ok) throw new Error(`Server error: ${response.status}`);
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
                    } catch {}
                }
            }
        }

        // If the AI included a refined topic block, extract it and set it as the current topic.
        const refined = extractRefinedTopic(aiContent);
        if (refined) {
            refinedTopicCandidate.value = refined;
            setCurrentTopicWithoutReset(refined);
            toast.success('Refined topic detected', { description: 'The refined topic is ready to select.' });
        }
    } catch (error: any) {
        if (error.name === 'AbortError') {
            const idx = messages.value.findIndex(m => m.id === aiMsgId);
            if (idx !== -1) {
                if (!messages.value[idx].content) messages.value.splice(idx, 1);
                else messages.value[idx].isTyping = false;
            }
        } else {
            console.error('Chat error:', error);
            toast.error('Failed to get response');
        }
    } finally {
        isSending.value = false;
        abortController = null;
        const idx = messages.value.findIndex(m => m.id === aiMsgId);
        if (idx !== -1) messages.value[idx].isTyping = false;
    }
};

// --- Topic Handling ---
const stripHtml = (html: string) => {
    const tmp = document.createElement('DIV');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
};

// Auto welcome message
let isFirstLoad = true;
watch(currentTopic, (newTopic) => {
    if (!newTopic) return;
    if (suppressWelcomeReset.value) return;
    if (isFirstLoad) {
        isFirstLoad = false;
        if (messages.value.length === 0) {
            messages.value = [{
                id: 'system-welcome',
                role: 'assistant',
                content: `Hi! I see you're interested in **${stripHtml(newTopic.title || '')}**. How can I help you refine or understand this topic better?`
            }];
        }
        return;
    }
    messages.value = [{
        id: 'system-welcome-' + Date.now(),
        role: 'assistant',
        content: `Hi! I see you're interested in **${stripHtml(newTopic.title || '')}**. How can I help you refine or understand this topic better?`
    }];
}, { immediate: true });

// --- Save Refined Topic ---
const showSaveTopicDialog = ref(false);
const topicToSave = ref({ title: '', description: '' });
const isSavingTopic = ref(false);

const extractSuggestedTopics = (content: string): string[] => {
    const topics: string[] = [];
    const quotedPattern = /"([^"]{20,200})"/g;
    let match;
    while ((match = quotedPattern.exec(content)) !== null) {
        const topic = match[1].trim();
        if (!topic.toLowerCase().startsWith('how ') &&
            !topic.toLowerCase().startsWith('what ') &&
            !topic.toLowerCase().startsWith('why ') &&
            topic.length > 30) {
            topics.push(topic);
        }
    }
    return topics;
};

const refinedTopicCandidate = ref<Topic | null>(null);

const setCurrentTopicWithoutReset = (topic: Topic) => {
    suppressWelcomeReset.value = true;
    currentTopic.value = topic;
    void nextTick(() => {
        suppressWelcomeReset.value = false;
    });
};

const extractRefinedTopic = (content: string): Topic | null => {
    if (!content) return null;
    const match = content.match(/<REFINED_TOPIC_JSON>\s*([\s\S]*?)\s*<\/REFINED_TOPIC_JSON>/i);
    if (!match) return null;

    const raw = match[1].trim();
    try {
        const parsed = JSON.parse(raw);
        const title = typeof parsed?.title === 'string' ? parsed.title.trim() : '';
        const description = typeof parsed?.description === 'string' ? parsed.description.trim() : '';
        if (!title) return null;

        // Keep description as plain text; render via SafeHtmlText as-is (it can handle plain).
        return {
            id: 0,
            title,
            description,
            difficulty: 'Intermediate',
            resource_level: 'Medium',
            feasibility_score: 75,
        };
    } catch {
        return null;
    }
};

const applyRefinedTopic = () => {
    if (!refinedTopicCandidate.value) return;
    setCurrentTopicWithoutReset(refinedTopicCandidate.value);
    toast.success('Refined topic applied');
};

const applyRefinedTopicFromContent = async (content: string, selectAfter = false) => {
    const refined = extractRefinedTopic(content);
    if (!refined) {
        toast.error('No refined topic found in this message');
        return;
    }
    refinedTopicCandidate.value = refined;
    setCurrentTopicWithoutReset(refined);
    if (selectAfter) await selectTopic();
};

const openSaveTopicDialog = (title: string) => {
    topicToSave.value = { title, description: '' };
    showSaveTopicDialog.value = true;
};

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
            toast.success('Topic saved successfully!');
            showSaveTopicDialog.value = false;
            router.reload();
        } else {
            toast.error('Failed to save topic');
        }
    } catch {
        toast.error('Failed to save topic');
    } finally {
        isSavingTopic.value = false;
    }
};

const selectTopic = async () => {
    if (!currentTopic.value) return;
    const loadingToast = toast.loading('Setting topic...');
    try {
        const title = stripHtml(currentTopic.value.title);
        const response = await fetch(route('topics.select', props.project.slug), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            body: JSON.stringify({
                topic: title,
                title: title.substring(0, 100),
                description: currentTopic.value.description
            }),
        });
        if (response.ok) {
            toast.dismiss(loadingToast);
            toast.success('Topic selected successfully!');
            router.visit(route('projects.topic-approval', props.project.slug));
        } else {
            throw new Error('Failed');
        }
    } catch {
        toast.dismiss(loadingToast);
        toast.error('Failed to select topic');
    }
};

const formatMessageContent = (content: string) => {
    if (!content) return '';
    // Remove machine-readable block from display.
    content = content.replace(/<REFINED_TOPIC_JSON>[\s\S]*?<\/REFINED_TOPIC_JSON>/gi, '').trim();
    let formatted = content
        .replace(/^### (.*$)/gim, '<strong>$1</strong>')
        .replace(/^## (.*$)/gim, '<strong class="text-lg">$1</strong>')
        .replace(/^# (.*$)/gim, '<strong class="text-xl">$1</strong>');
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/^\s*-\s+(.*$)/gim, '<li class="ml-4 list-disc">$1</li>');
    formatted = formatted.replace(/^\s*\d+\.\s+(.*$)/gim, '<li class="ml-4 list-decimal">$1</li>');
    formatted = formatted.replace(/\n/g, '<br>');
    return formatted;
};
</script>

<template>
    <AppLayout title="Topic Lab">
        <!-- Main Layout Container -->
        <div class="flex h-[calc(100vh-4rem)] min-h-0 overflow-hidden relative bg-background w-full">
            
            <!-- Mobile Sidebar Overlay -->
            <div 
                v-if="isSidebarOpen && isMobile" 
                class="absolute inset-0 bg-background/80 backdrop-blur-sm z-30 animate-in fade-in duration-200 lg:hidden"
                @click="isSidebarOpen = false"
            />

            <!-- Sidebar -->
            <aside 
                :class="[
                    'flex flex-col border-r border-border/50 bg-muted/10 transition-all duration-300 ease-in-out z-40 h-full',
                    'lg:relative absolute inset-y-0 left-0', // Mobile absolute, Desktop relative
                    isSidebarOpen ? 'translate-x-0 w-80 shadow-2xl lg:shadow-none' : '-translate-x-full lg:translate-x-0 lg:w-0 lg:border-none lg:overflow-hidden'
                ]"
            >
                <div class="min-w-[20rem] h-full flex flex-col">
                    <!-- Sidebar Header -->
                    <div class="p-4 border-b border-border/50">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="p-1.5 bg-primary/10 rounded-md">
                                    <component :is="sidebarMode === 'topics' ? Sparkles : History" class="h-4 w-4 text-primary" />
                                </div>
                                <h2 class="font-semibold text-foreground tracking-tight">
                                    {{ sidebarMode === 'topics' ? 'Generated Topics' : 'History' }}
                                </h2>
                            </div>
                            <Button v-if="isMobile" variant="ghost" size="icon" @click="isSidebarOpen = false" class="h-8 w-8 lg:hidden">
                                <PanelLeftClose class="h-4 w-4" />
                            </Button>
                        </div>
                        
                        <div class="flex bg-muted rounded-lg p-1">
                            <button @click="sidebarMode = 'topics'" :class="['flex-1 flex items-center justify-center gap-2 text-xs font-medium py-1.5 rounded-md transition-all', sidebarMode === 'topics' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']">
                                <Sparkles class="h-3 w-3" /> Topics
                            </button>
                            <button @click="sidebarMode = 'history'" :class="['flex-1 flex items-center justify-center gap-2 text-xs font-medium py-1.5 rounded-md transition-all', sidebarMode === 'history' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground hover:text-foreground']">
                                <History class="h-3 w-3" /> History
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar Content -->
                    <ScrollArea class="flex-1 min-h-0">
                        <!-- Topics List -->
                        <div v-if="sidebarMode === 'topics'" class="p-3 space-y-2">
                            <div v-if="topics.length === 0" class="text-center py-10 text-muted-foreground text-sm flex flex-col items-center">
                                <Sparkles class="h-8 w-8 mb-3 opacity-20" />
                                <p>No topics available</p>
                            </div>
                            <div v-for="topic in topics" :key="topic.id" 
                                @click="currentTopic = topic; if(isMobile) isSidebarOpen = false;"
                                :class="['p-3 rounded-lg cursor-pointer border transition-all text-left hover:border-primary/30 active:scale-[0.98]',
                                    currentTopic?.id === topic.id ? 'bg-primary/5 border-primary/50 shadow-sm' : 'bg-card border-transparent hover:bg-card/80']"
                            >
                                <h3 :class="['text-sm font-medium mb-1.5 line-clamp-2 leading-snug', currentTopic?.id === topic.id ? 'text-primary' : 'text-foreground']">
                                    {{ stripHtml(topic.title) }}
                                </h3>
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-[10px] h-5 px-1.5 bg-background/50 font-normal border-primary/20">
                                        {{ topic.difficulty || 'Intermediate' }}
                                    </Badge>
                                    <span v-if="topic.feasibility_score" class="text-[10px] text-muted-foreground font-medium">
                                        {{ topic.feasibility_score }}% match
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- History List -->
                        <div v-else class="p-3 space-y-2">
                            <Button variant="outline" class="w-full mb-3 justify-start overflow-hidden bg-background/50 hover:bg-background border-dashed" @click="startNewChat">
                                <Plus class="h-4 w-4 mr-2" />
                                New Conversation
                            </Button>
                            
                            <div v-if="historySessions.length === 0" class="text-center py-10 text-muted-foreground text-sm flex flex-col items-center">
                                <History class="h-8 w-8 mb-3 opacity-20" />
                                <p>No previous conversations</p>
                            </div>

                            <div v-for="session in historySessions" :key="session.id"
                                @click="editingSessionId !== session.id && (loadSession(session.id), isMobile ? isSidebarOpen = false : null)"
                                :class="['p-3 rounded-lg cursor-pointer border transition-all text-left hover:border-primary/30 group relative',
                                    props.sessionId === session.id ? 'bg-primary/5 border-primary/50 shadow-sm' : 'bg-card border-transparent hover:bg-card/80']"
                            >
                                <!-- Edit Mode -->
                                <div v-if="editingSessionId === session.id" class="flex items-center gap-1" @click.stop>
                                    <input v-model="editingSessionName"
                                        class="flex-1 text-sm px-2 py-1 h-7 bg-background border border-primary/50 rounded focus:outline-none focus:ring-1 focus:ring-primary w-full"
                                        @keydown.enter="saveSessionName(session.id)"
                                        @keydown.escape="cancelEditingSession"
                                        autofocus 
                                    />
                                    <button @click="saveSessionName(session.id)" class="p-1 hover:bg-green-500/20 rounded text-green-500 shrink-0">
                                        <Check class="h-3.5 w-3.5" />
                                    </button>
                                    <button @click="cancelEditingSession" class="p-1 hover:bg-red-500/20 rounded text-red-500 shrink-0">
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </div>

                                <!-- Display Mode -->
                                <div v-else class="flex flex-col gap-1">
                                    <div class="flex items-start justify-between">
                                        <h3 :class="['text-sm font-medium line-clamp-1 break-all', props.sessionId === session.id ? 'text-primary' : 'text-foreground']">
                                            {{ session.title }}
                                        </h3>
                                        <div class="flex items-center opacity-0 group-hover:opacity-100 transition-opacity absolute right-2 top-2 bg-card/80 backdrop-blur-sm rounded shadow-sm border border-border/50 p-0.5" v-if="props.sessionId === session.id || !isMobile">
                                            <button @click.stop="startEditingSession(session, $event)" class="p-1.5 hover:bg-muted rounded text-muted-foreground hover:text-primary transition-colors">
                                                <Pencil class="h-3 w-3" />
                                            </button>
                                            <button @click.stop="openDeleteDialog(session.id)" class="p-1.5 hover:bg-red-500/10 rounded text-muted-foreground hover:text-red-500 transition-colors">
                                                <Trash2 class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-muted-foreground">
                                        <span class="flex items-center gap-1">
                                            <MessageSquare class="h-3 w-3 opacity-70" /> {{ session.message_count }}
                                        </span>
                                        <span>{{ session.date }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>

                    <!-- Sidebar Footer -->
                    <div class="p-4 border-t border-border/50 bg-background/50 backdrop-blur-sm mt-auto">
                        <Button
                            v-if="props.returnToSelection"
                            variant="outline"
                            class="w-full justify-start overflow-hidden bg-background/50 hover:bg-background border-border/60 mb-2"
                            @click="backToSelection"
                        >
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Topic Selection
                        </Button>
                        <Button variant="ghost" class="w-full justify-start text-muted-foreground hover:text-foreground" @click="backToLibrary">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Library
                        </Button>
                    </div>
                </div>
            </aside>

            <!-- Main Chat Area -->
            <main class="flex-1 flex flex-col min-w-0 bg-background h-full relative z-0">
                <!-- Header -->
                <header class="h-16 flex-none border-b border-border/50 flex items-center justify-between px-4 sticky top-0 bg-background/80 backdrop-blur-md z-10 transition-all">
                    <div class="flex items-center gap-3 min-w-0">
                        <Button 
                            variant="ghost" 
                            size="icon" 
                            class="shrink-0 -ml-2"
                            @click="isSidebarOpen = !isSidebarOpen"
                            :title="isSidebarOpen ? 'Close sidebar' : 'Open sidebar'"
                        >
                            <component :is="isSidebarOpen ? PanelLeftClose : PanelLeftOpen" class="h-5 w-5 text-muted-foreground" />
                        </Button>
                        
                        <div class="flex flex-col min-w-0">
                            <div class="flex items-center gap-2">
                                <h1 class="font-semibold text-foreground truncate text-sm md:text-base">
                                    Topic Laboratory
                                </h1>
                                <Badge variant="secondary" class="font-normal text-[10px] hidden sm:inline-flex bg-primary/10 text-primary border-transparent">
                                    AI Assistant
                                </Badge>
                            </div>
                            <span class="text-[10px] text-muted-foreground truncate hidden sm:block">
                                {{ project.title }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <div class="hidden md:flex flex-col items-end mr-2 cursor-help">
                                        <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Credits</span>
                                        <span :class="['text-xs font-bold', hasWords ? 'text-primary' : 'text-red-500']">{{ formattedBalance }}</span>
                                    </div>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Your word balance</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>

                         <Button :disabled="!currentTopic || isSending" @click="selectTopic" class="shadow-lg shadow-primary/20 h-9 px-4 text-xs md:text-sm">
                            <CheckCircle2 class="h-4 w-4 mr-2" />
                            <span class="hidden sm:inline">Select Topic</span>
                            <span class="sm:hidden">Select</span>
                        </Button>
                    </div>
                </header>

                <!-- Chat Feed -->
                <div class="flex-1 overflow-y-auto px-4 py-6 scroll-smooth w-full" ref="chatContainer">
                    <div class="max-w-3xl mx-auto space-y-6 pb-4">
                        <!-- Topic Summary Card -->
                        <div v-if="currentTopic" class="mb-8 animate-in fade-in slide-in-from-top-4 duration-700">
                            <div class="rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 via-background to-background p-4 md:p-6 shadow-sm relative overflow-hidden group">
                                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                                    <Sparkles class="w-12 h-12 text-primary rotate-12" />
                                </div>
                                <div class="flex items-start gap-4 relative z-10">
                                    <div class="p-2 bg-primary/10 rounded-lg text-primary mt-1 shrink-0">
                                        <Sparkles class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-2">
                                        <SafeHtmlText :content="currentTopic.title" as="h3" class="text-base md:text-lg font-bold text-foreground leading-tight" />
                                        <SafeHtmlText :content="currentTopic.description" as="div" class="text-xs md:text-sm text-muted-foreground leading-relaxed pl-3 border-l-2 border-primary/20" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Messages -->
                        <template v-if="messages.length">
                            <div v-for="msg in messages" :key="msg.id" :class="['flex gap-3 animate-in fade-in slide-in-from-bottom-2', msg.role === 'user' ? 'justify-end' : 'justify-start']">
                                <!-- Bot Avatar -->
                                <div v-if="msg.role === 'assistant'" class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0 border border-primary/20 self-start mt-1">
                                    <Bot class="h-4 w-4 text-primary" />
                                </div>

                                <div class="flex flex-col gap-1 max-w-[85%] md:max-w-[80%] min-w-0">
                                    <div :class="['rounded-2xl px-4 md:px-5 py-3 shadow-sm text-sm leading-relaxed overflow-hidden break-words', 
                                        msg.role === 'user' ? 'bg-primary text-primary-foreground rounded-tr-none' : 'bg-muted/50 border border-border/50 text-foreground rounded-tl-none']"
                                    >
                                        <div v-if="msg.isTyping" class="flex gap-1 items-center h-5 px-1">
                                            <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                            <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                            <span class="w-1.5 h-1.5 bg-current rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                                        </div>
                                        <div v-else class="markdown-body">
                                            <SafeHtmlText :content="formatMessageContent(msg.content)" />
                                            
                                            <!-- Suggested Topics Chips -->
                                            <div v-if="msg.role === 'assistant' && extractSuggestedTopics(msg.content).length > 0" class="mt-4 pt-3 border-t border-border/30">
                                                <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-2 flex items-center gap-1 font-semibold opacity-70">
                                                    <BookmarkPlus class="h-3 w-3" />
                                                    Suggested Topics
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <button v-for="(topic, idx) in extractSuggestedTopics(msg.content)" :key="idx" 
                                                        @click="openSaveTopicDialog(topic)"
                                                        class="text-xs px-3 py-1.5 bg-background hover:bg-primary/10 text-primary border border-primary/20 hover:border-primary/50 rounded-full transition-all flex items-center gap-1.5 shadow-sm active:scale-95 text-left max-w-full"
                                                    >
                                                        <Save class="h-3 w-3 shrink-0" />
                                                        <span class="truncate">{{ topic }}</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Refined Topic Detected -->
                                            <div
                                                v-if="msg.role === 'assistant' && extractRefinedTopic(msg.content)"
                                                class="mt-4 pt-3 border-t border-border/30"
                                            >
                                                <div class="text-[10px] uppercase tracking-wider text-muted-foreground mb-2 flex items-center gap-1 font-semibold opacity-70">
                                                    <CheckCircle2 class="h-3 w-3" />
                                                    Refined Topic
                                                </div>
                                                <div class="flex flex-wrap gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        class="h-8"
                                                        @click="applyRefinedTopicFromContent(msg.content, false)"
                                                    >
                                                        Use refined topic
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        class="h-8"
                                                        @click="applyRefinedTopicFromContent(msg.content, true)"
                                                    >
                                                        Select refined topic
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div v-if="!msg.isTyping" :class="['flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity', msg.role === 'user' ? 'justify-end' : 'justify-start ml-1']">
                                        <button @click="copyMessage(msg.content)" class="text-[10px] text-muted-foreground hover:text-foreground p-1 transition-colors" title="Copy">
                                            Copy
                                        </button>
                                        <button v-if="msg.role === 'assistant' && msg.id === messages.filter(m => m.role === 'assistant' && !m.isTyping).slice(-1)[0]?.id"
                                            @click="regenerateResponse" 
                                            :disabled="isRegenerating || isSending"
                                            class="text-[10px] text-muted-foreground hover:text-foreground p-1 transition-colors flex items-center gap-1 disabled:opacity-50"
                                            title="Regenerate"
                                        >
                                            Regenerate
                                        </button>
                                    </div>
                                </div>

                                <!-- User Avatar -->
                                <div v-if="msg.role === 'user'" class="h-8 w-8 rounded-full bg-muted flex items-center justify-center shrink-0 border border-border self-start mt-1">
                                    <User class="h-4 w-4 text-muted-foreground" />
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-3 md:p-4 border-t border-border/50 bg-background/80 backdrop-blur-md sticky bottom-0 z-20">
                    <div class="max-w-3xl mx-auto relative flex flex-col gap-2">
                         <div class="relative w-full">
                            <Textarea v-model="userInput" 
                                placeholder="Refine this topic, ask about methodologies, or check feasibility..." 
                                class="min-h-[3rem] md:min-h-[3.5rem] max-h-32 pr-12 pl-4 py-3 bg-muted/40 hover:bg-muted/60 focus:bg-background focus:ring-1 focus:ring-primary/50 resize-none rounded-xl border-border/50 shadow-sm transition-all"
                                @keydown.enter.exact.prevent="sendMessage"
                                @keydown.shift.enter.prevent="userInput += '\n'"
                            />
                            
                            <Button v-if="isSending" 
                                class="absolute right-2 bottom-2 h-8 w-8 rounded-lg bg-red-500 hover:bg-red-600 shadow-sm transition-transform hover:scale-105" 
                                size="icon" 
                                @click="stopGeneration" 
                                title="Stop"
                            >
                                <Square class="h-4 w-4 fill-current text-white" />
                            </Button>
                            
                            <Button v-else 
                                class="absolute right-2 bottom-2 h-8 w-8 rounded-lg shadow-sm transition-transform hover:scale-105" 
                                size="icon"
                                :disabled="!userInput.trim() || !currentTopic" 
                                @click="sendMessage"
                            >
                                <Send class="h-4 w-4" />
                            </Button>
                        </div>
                        <p class="text-center text-[10px] text-muted-foreground/70 select-none">
                            AI may display inaccurate info.
                        </p>
                    </div>
                </div>
            </main>
        </div>
    </AppLayout>

    <!-- Dialogs -->
    <Dialog v-model:open="showSaveTopicDialog">
        <DialogContent class="sm:max-w-[500px] gap-6">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <div class="p-2 bg-primary/10 rounded-lg">
                        <BookmarkPlus class="h-5 w-5 text-primary" />
                    </div>
                    Save Refined Topic
                </DialogTitle>
                <DialogDescription>
                    Save this topic to your project's library.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Title</label>
                    <Input v-model="topicToSave.title" placeholder="Topic title..." class="w-full" />
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Description</label>
                    <Textarea v-model="topicToSave.description" placeholder="Optional description..." class="resize-none" rows="4" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showSaveTopicDialog = false">Cancel</Button>
                <Button @click="saveRefinedTopic" :disabled="!topicToSave.title.trim() || isSavingTopic">
                    <Save class="h-4 w-4 mr-2" />
                    {{ isSavingTopic ? 'Saving...' : 'Save' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <AlertDialog :open="showDeleteDialog" @update:open="(val) => { if (!val) closeDeleteDialog() }">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle class="flex items-center gap-2 text-destructive">
                    <Trash2 class="h-5 w-5" />
                    Delete Conversation
                </AlertDialogTitle>
                <AlertDialogDescription>
                    Permanent action. Cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteDialog">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDeleteSession" class="bg-destructive hover:bg-destructive/90 text-destructive-foreground">
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>
.markdown-body :deep(p) { margin-bottom: 0.5em; }
.markdown-body :deep(ul) { list-style-type: disc; padding-left: 1.5em; margin-bottom: 0.5em; }
/* Custom Scrollbar for subtle look */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.1); border-radius: 10px; }
.dark ::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); }
::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.2); }
</style>
