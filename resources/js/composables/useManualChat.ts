import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import type { Chapter } from '@/types'
import { countWords } from '@/utils/wordCount'
import { recordWordUsage } from '@/composables/useWordBalance'

interface ChatMessage {
  role: 'user' | 'assistant'
  content: string
  timestamp: Date
}

export function useManualChat(chapter: Chapter, initialHistory: ChatMessage[] = []) {
  const isChatOpen = ref(false)
  const messages = ref<ChatMessage[]>(initialHistory)
  const isLoading = ref(false)

  const toggleChat = () => {
    isChatOpen.value = !isChatOpen.value
  }

  const sendMessage = async (content: string) => {
    if (!content.trim()) return

    // Add user message
    messages.value.push({
      role: 'user',
      content,
      timestamp: new Date(),
    })

    isLoading.value = true

    try {
      const response = await axios.post(
        route('projects.manual-editor.chat', {
          project: chapter.project.slug,
          chapter: chapter.chapter_number,
        }),
        {
          message: content,
          history: messages.value,
        },
      )

      // Add AI response
      const aiContent = response.data.message
      messages.value.push({
        role: 'assistant',
        content: aiContent,
        timestamp: new Date(),
      })

      const wordsUsed = countWords(aiContent || '')
      if (wordsUsed > 0) {
        recordWordUsage(wordsUsed, 'Manual editor: Chat', 'chapter', chapter.id).catch((err) =>
          console.error('Failed to record word usage (manual chat):', err),
        )
      }
    } catch (error) {
      console.error('Failed to send chat message:', error)

      // Add error message
      messages.value.push({
        role: 'assistant',
        content: "I'm having trouble responding right now. Please try again.",
        timestamp: new Date(),
      })
    } finally {
      isLoading.value = false
    }
  }

  return {
    isChatOpen,
    messages,
    isLoading,
    toggleChat,
    sendMessage,
  }
}
