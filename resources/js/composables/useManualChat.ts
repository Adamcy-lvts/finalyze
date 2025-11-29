import { ref, type Ref } from 'vue'
import type { Chapter } from '@/types'

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
          chapter: chapter.id,
        }),
        {
          message: content,
          history: messages.value,
        },
      )

      // Add AI response
      messages.value.push({
        role: 'assistant',
        content: response.data.message,
        timestamp: new Date(),
      })
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
