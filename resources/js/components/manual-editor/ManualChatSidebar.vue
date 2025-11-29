<script setup lang="ts">
import { ref, nextTick, watch } from 'vue'
import { Loader2, Send, X, Sparkles, Bot } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Badge } from '@/components/ui/badge'
import type { ContentAnalysis } from '@/types'

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  timestamp: Date
}

const props = defineProps<{
  messages: ChatMessage[]
  isLoading: boolean
  analysis?: ContentAnalysis | null
}>()

const emit = defineEmits<{
  send: [message: string]
  close: []
}>()

const input = ref('')
const messagesContainer = ref<any>(null)

const handleSend = () => {
  if (input.value.trim() && !props.isLoading) {
    emit('send', input.value)
    input.value = ''
  }
}

const handleKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    handleSend()
  }
}

// Auto-scroll to bottom when new messages arrive
watch(
  () => props.messages.length,
  async () => {
    await nextTick()
    if (messagesContainer.value) {
      const scrollEl = messagesContainer.value.$el.querySelector('[data-radix-scroll-area-viewport]')
      if (scrollEl) {
        scrollEl.scrollTop = scrollEl.scrollHeight
      }
    }
  },
)
</script>

<template>
  <div class="flex flex-col h-full bg-transparent">
    <!-- Chat Header -->
    <div class="p-4 border-b border-border/50 flex justify-between items-start bg-background/50 backdrop-blur-sm">
      <div>
        <div class="flex items-center gap-2">
          <div class="p-1.5 rounded-md bg-primary/10 text-primary">
            <Bot class="w-4 h-4" />
          </div>
          <h3 class="font-semibold text-foreground">AI Assistant</h3>
        </div>
        <p class="text-xs text-muted-foreground mt-1 ml-1">Context-aware writing companion</p>
      </div>
      
      <Button variant="ghost" size="icon" class="h-8 w-8 -mr-2" @click="$emit('close')">
        <X class="w-4 h-4" />
      </Button>
    </div>

    <!-- Analysis Summary (Optional - collapsible or small) -->
    <div v-if="analysis" class="px-4 py-2 bg-muted/30 border-b border-border/50 text-xs flex gap-3 text-muted-foreground">
      <span>{{ analysis.word_count }} words</span>
      <span>•</span>
      <span>{{ analysis.citation_count }} citations</span>
      <span v-if="analysis.detected_issues.length > 0" class="ml-auto text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
        {{ analysis.detected_issues.length }} issues
      </span>
    </div>

    <!-- Messages -->
    <ScrollArea ref="messagesContainer" class="flex-1 p-4">
      <div class="space-y-6">
        <!-- Welcome Message -->
        <div v-if="messages.length === 0" class="flex flex-col items-center justify-center text-center py-12 px-4 space-y-4 opacity-0 animate-in fade-in slide-in-from-bottom-4 duration-700 fill-mode-forwards">
          <div class="w-12 h-12 rounded-full bg-primary/5 flex items-center justify-center mb-2">
            <Sparkles class="w-6 h-6 text-primary/60" />
          </div>
          <div class="space-y-1">
            <h4 class="font-medium text-foreground">How can I help?</h4>
            <p class="text-sm text-muted-foreground max-w-[240px]">
              I can help you structure your arguments, find citations, or rephrase complex sentences.
            </p>
          </div>
          <div class="grid grid-cols-1 gap-2 w-full max-w-xs mt-4">
            <Button variant="outline" size="sm" class="justify-start h-auto py-2 text-xs font-normal" @click="input = 'Analyze the structure of this chapter'; handleSend()">
              "Analyze the structure of this chapter"
            </Button>
            <Button variant="outline" size="sm" class="justify-start h-auto py-2 text-xs font-normal" @click="input = 'Suggest some relevant citations'; handleSend()">
              "Suggest some relevant citations"
            </Button>
          </div>
        </div>

        <div
          v-for="(message, idx) in messages"
          :key="idx"
          :class="[
            'flex gap-3 max-w-[90%]',
            message.role === 'user' ? 'ml-auto flex-row-reverse' : ''
          ]"
        >
          <!-- Avatar -->
          <div 
            class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-medium"
            :class="message.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
          >
            {{ message.role === 'user' ? 'You' : 'AI' }}
          </div>

          <!-- Bubble -->
          <div class="space-y-1">
            <div 
              :class="[
                'p-3 rounded-2xl text-sm shadow-sm',
                message.role === 'user' 
                  ? 'bg-primary text-primary-foreground rounded-tr-sm' 
                  : 'bg-card border text-card-foreground rounded-tl-sm'
              ]"
            >
              <div v-html="message.content" class="prose prose-sm dark:prose-invert max-w-none break-words" />
            </div>
            <p class="text-[10px] text-muted-foreground opacity-70 px-1" :class="message.role === 'user' ? 'text-right' : ''">
              {{ new Date(message.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
            </p>
          </div>
        </div>

        <div v-if="isLoading" class="flex gap-3">
          <div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center shrink-0">
            <Loader2 class="w-4 h-4 animate-spin text-muted-foreground" />
          </div>
          <div class="bg-card border px-4 py-3 rounded-2xl rounded-tl-sm text-sm text-muted-foreground shadow-sm">
            Thinking...
          </div>
        </div>
      </div>
    </ScrollArea>

    <!-- Input -->
    <div class="p-4 border-t border-border/50 bg-background/50 backdrop-blur-sm">
      <div class="relative">
        <Input
          v-model="input"
          placeholder="Ask anything about your chapter..."
          @keydown="handleKeydown"
          :disabled="isLoading"
          class="pr-10 py-6 bg-background/80 border-border/60 focus-visible:ring-primary/20 shadow-sm"
        />
        <Button 
          @click="handleSend" 
          :disabled="isLoading || !input.trim()" 
          size="icon"
          class="absolute right-1.5 top-1.5 h-9 w-9 transition-all duration-200"
          :class="input.trim() ? 'opacity-100 scale-100' : 'opacity-70 scale-90'"
        >
          <Send class="w-4 h-4" />
        </Button>
      </div>
      <p class="text-[10px] text-center text-muted-foreground mt-2">
        AI can make mistakes. Review generated content.
      </p>
    </div>
  </div>
</template>
