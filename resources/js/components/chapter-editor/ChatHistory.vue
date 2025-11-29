<template>
  <div class="chat-history">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <ClockIcon class="h-5 w-5 text-muted-foreground" />
        <h3 class="text-lg font-semibold">Chat History</h3>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          @click="refreshHistory"
          :disabled="loading"
        >
          <RotateCcwIcon class="h-4 w-4" />
          Refresh
        </Button>
        <Button
          variant="destructive"
          size="sm"
          @click="showClearConfirm = true"
          :disabled="sessions.length === 0 || loading"
        >
          <TrashIcon class="h-4 w-4" />
          Clear All
        </Button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <div class="flex items-center gap-2 text-muted-foreground">
        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-current"></div>
        Loading chat history...
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="sessions.length === 0" class="text-center py-8">
      <div class="text-muted-foreground">
        <MessageCircleIcon class="h-12 w-12 mx-auto mb-2 opacity-50" />
        <p class="text-lg">No chat history yet</p>
        <p class="text-sm">Start a conversation to see your chat history here.</p>
      </div>
    </div>

    <!-- Sessions List -->
    <div v-else class="space-y-4">
      <div v-for="session in sessions" :key="session.session_id" class="border rounded-lg">
        <!-- Session Header -->
        <div class="flex items-center justify-between p-4 border-b bg-muted/30">
          <div class="flex-1">
            <div class="font-medium text-sm truncate">
              <RichTextViewer
                :content="session.title"
                class="prose prose-sm max-w-none prose-headings:text-sm prose-headings:m-0 prose-p:m-0 prose-p:text-sm"
              />
            </div>
            <div class="flex items-center gap-4 text-xs text-muted-foreground mt-1">
              <span>{{ session.started_at }}</span>
              <span>{{ session.message_count }} messages</span>
              <span>{{ session.duration }}</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <Button
              variant="ghost"
              size="sm"
              @click="toggleSession(session.session_id)"
            >
              <ChevronDownIcon
                :class="['h-4 w-4 transition-transform', { 'rotate-180': expandedSessions.has(session.session_id) }]"
              />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              @click="showDeleteSessionConfirm(session)"
            >
              <TrashIcon class="h-4 w-4 text-destructive" />
            </Button>
          </div>
        </div>

        <!-- Session Messages -->
        <div
          v-if="expandedSessions.has(session.session_id)"
          class="p-4 space-y-3 max-h-96 overflow-y-auto"
        >
          <div
            v-for="message in session.messages"
            :key="message.id"
            :class="[
              'flex gap-3 group',
              message.message_type === 'user' ? 'justify-end' : 'justify-start'
            ]"
          >
            <div
              :class="[
                'max-w-[80%] rounded-lg p-3 text-sm',
                message.message_type === 'user'
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted'
              ]"
            >
              <RichTextViewer
                :content="message.content"
                :class="[
                  'prose prose-sm max-w-none',
                  message.message_type === 'user'
                    ? 'chat-message-user prose-invert'
                    : 'chat-message-ai'
                ]"
              />
              <div class="flex items-center justify-between mt-2 pt-2 border-t border-current/20">
                <div class="text-xs opacity-70">
                  {{ message.formatted_time }}
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  class="opacity-0 group-hover:opacity-100 transition-opacity h-6 w-6 p-0"
                  @click="showDeleteMessageConfirm(message, session)"
                >
                  <TrashIcon class="h-3 w-3" />
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex items-center justify-between mt-6">
      <Button
        variant="outline"
        size="sm"
        @click="loadPage(currentPage - 1)"
        :disabled="currentPage === 1 || loading"
      >
        <ChevronLeftIcon class="h-4 w-4" />
        Previous
      </Button>

      <span class="text-sm text-muted-foreground">
        Page {{ currentPage }} of {{ totalPages }}
      </span>

      <Button
        variant="outline"
        size="sm"
        @click="loadPage(currentPage + 1)"
        :disabled="currentPage === totalPages || loading"
      >
        Next
        <ChevronRightIcon class="h-4 w-4" />
      </Button>
    </div>

    <!-- Delete Session Confirmation Dialog -->
    <Dialog v-model:open="showDeleteSession">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Chat Session</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete this chat session? This will permanently remove
            {{ selectedSession?.message_count }} messages. This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="showDeleteSession = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteSession" :disabled="deleting">
            <TrashIcon v-if="!deleting" class="h-4 w-4 mr-2" />
            <div v-else class="animate-spin rounded-full h-4 w-4 border-b-2 border-current mr-2"></div>
            {{ deleting ? 'Deleting...' : 'Delete Session' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete Message Confirmation Dialog -->
    <Dialog v-model:open="showDeleteMessage">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Message</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete this message? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="showDeleteMessage = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteMessage" :disabled="deleting">
            <TrashIcon v-if="!deleting" class="h-4 w-4 mr-2" />
            <div v-else class="animate-spin rounded-full h-4 w-4 border-b-2 border-current mr-2"></div>
            {{ deleting ? 'Deleting...' : 'Delete Message' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Clear All Confirmation Dialog -->
    <Dialog v-model:open="showClearConfirm">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Clear All Chat History</DialogTitle>
          <DialogDescription>
            Are you sure you want to clear all chat history for this chapter? This will permanently
            delete all {{ sessions.reduce((total, session) => total + session.message_count, 0) }} messages
            across {{ sessions.length }} sessions. This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="showClearConfirm = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="clearAllHistory" :disabled="deleting">
            <TrashIcon v-if="!deleting" class="h-4 w-4 mr-2" />
            <div v-else class="animate-spin rounded-full h-4 w-4 border-b-2 border-current mr-2"></div>
            {{ deleting ? 'Clearing...' : 'Clear All History' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import RichTextViewer from '@/components/ui/rich-text-editor/RichTextViewer.vue'
import { ClockIcon, MessageCircleIcon, TrashIcon, RotateCcwIcon, ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next'
import { toast } from 'sonner'

interface Message {
  id: number
  content: string
  message_type: 'user' | 'ai' | 'system'
  timestamp: string
  formatted_time: string
}

interface Session {
  session_id: string
  title: string
  message_count: number
  session_start: string
  session_end: string
  started_at: string
  duration: string
  messages: Message[]
}

interface Props {
  projectSlug: string
  chapterNumber: number
}

const props = defineProps<Props>()

// Define emits for parent communication
const emit = defineEmits<{
  'chat-deleted': []
  'chat-cleared': []
}>()

// Reactive state
const sessions = ref<Session[]>([])
const loading = ref(false)
const deleting = ref(false)
const currentPage = ref(1)
const totalPages = ref(1)
const totalSessions = ref(0)
const expandedSessions = reactive(new Set<string>())

// Dialog states
const showDeleteSession = ref(false)
const showDeleteMessage = ref(false)
const showClearConfirm = ref(false)
const selectedSession = ref<Session | null>(null)
const selectedMessage = ref<Message | null>(null)

// Load chat history
const loadHistory = async (page = 1) => {
  loading.value = true
  try {
    const response = await fetch(`/projects/${props.projectSlug}/chapters/${props.chapterNumber}/chat/sessions?page=${page}&per_page=10`)
    const data = await response.json()

    if (data.success) {
      sessions.value = data.sessions
      currentPage.value = data.page
      totalPages.value = data.total_pages
      totalSessions.value = data.total_sessions
    } else {
      throw new Error(data.error || 'Failed to load chat history')
    }
  } catch (error) {
    console.error('Failed to load chat history:', error)
    toast.error('Failed to load chat history')
  } finally {
    loading.value = false
  }
}

// Load specific page
const loadPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    loadHistory(page)
  }
}

// Refresh history
const refreshHistory = () => {
  loadHistory(currentPage.value)
}

// Toggle session expansion
const toggleSession = (sessionId: string) => {
  if (expandedSessions.has(sessionId)) {
    expandedSessions.delete(sessionId)
  } else {
    expandedSessions.add(sessionId)
  }
}

// Show delete session confirmation
const showDeleteSessionConfirm = (session: Session) => {
  selectedSession.value = session
  showDeleteSession.value = true
}

// Show delete message confirmation
const showDeleteMessageConfirm = (message: Message, session: Session) => {
  selectedMessage.value = message
  selectedSession.value = session
  showDeleteMessage.value = true
}

// Delete session
const deleteSession = async () => {
  if (!selectedSession.value) return

  deleting.value = true
  try {
    const response = await fetch(`/projects/${props.projectSlug}/chapters/${props.chapterNumber}/chat/sessions/${selectedSession.value.session_id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      }
    })

    const data = await response.json()

    if (data.success) {
      toast.success(data.message)
      showDeleteSession.value = false
      selectedSession.value = null
      // Notify parent to refresh main chat
      emit('chat-deleted')
      // Reload current page
      loadHistory(currentPage.value)
    } else {
      throw new Error(data.error || 'Failed to delete session')
    }
  } catch (error) {
    console.error('Failed to delete session:', error)
    toast.error('Failed to delete chat session')
  } finally {
    deleting.value = false
  }
}

// Delete message
const deleteMessage = async () => {
  if (!selectedMessage.value) return

  deleting.value = true
  try {
    const response = await fetch(`/projects/${props.projectSlug}/chapters/${props.chapterNumber}/chat/messages/${selectedMessage.value.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      }
    })

    const data = await response.json()

    if (data.success) {
      toast.success(data.message)
      showDeleteMessage.value = false
      selectedMessage.value = null
      // Reload current page to refresh the session
      loadHistory(currentPage.value)
    } else {
      throw new Error(data.error || 'Failed to delete message')
    }
  } catch (error) {
    console.error('Failed to delete message:', error)
    toast.error('Failed to delete message')
  } finally {
    deleting.value = false
  }
}

// Clear all history
const clearAllHistory = async () => {
  deleting.value = true
  try {
    const response = await fetch(`/projects/${props.projectSlug}/chapters/${props.chapterNumber}/chat/clear`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
      }
    })

    const data = await response.json()

    if (data.success) {
      toast.success(data.message)
      showClearConfirm.value = false
      // Notify parent to refresh main chat
      emit('chat-cleared')
      sessions.value = []
      totalSessions.value = 0
      totalPages.value = 1
      currentPage.value = 1
      expandedSessions.clear()
    } else {
      throw new Error(data.error || 'Failed to clear history')
    }
  } catch (error) {
    console.error('Failed to clear history:', error)
    toast.error('Failed to clear chat history')
  } finally {
    deleting.value = false
  }
}

// Load history on mount
onMounted(() => {
  loadHistory()
})
</script>

<style scoped>
@reference "../../../css/app.css";

.chat-history {
  @apply w-full;
}
</style>