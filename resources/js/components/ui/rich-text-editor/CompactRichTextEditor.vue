<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import { Button } from '@/components/ui/button'
import {
  Bold,
  Italic,
  Code,
  Send
} from 'lucide-vue-next'

interface Props {
  modelValue: string
  placeholder?: string
  disabled?: boolean
  showToolbar?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Type your message...',
  disabled: false,
  showToolbar: true
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'submit': []
  'keydown': [event: KeyboardEvent]
}>()

// Initialize compact Tiptap editor
const editor = useEditor({
  content: props.modelValue,
  editable: !props.disabled,
  extensions: [
    StarterKit.configure({
      // Disable some features for chat
      heading: false,
      bulletList: false,
      orderedList: false,
      blockquote: false,
      horizontalRule: false,
      codeBlock: false
    }),
    Placeholder.configure({
      placeholder: props.placeholder
    })
  ],
  editorProps: {
    attributes: {
      class: 'prose prose-sm dark:prose-invert max-w-none focus:outline-none min-h-[2rem] max-h-32 overflow-y-auto',
      style: 'padding: 8px; line-height: 1.5;'
    },
    handleKeyDown: (view, event) => {
      emit('keydown', event)
      
      // Handle Enter key
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault()
        emit('submit')
        return true
      }
      return false
    }
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
  }
})

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (editor.value && editor.value.getHTML() !== newValue) {
    editor.value.commands.setContent(newValue, false)
  }
})

// Watch disabled state
watch(() => props.disabled, (disabled) => {
  if (editor.value) {
    editor.value.setEditable(!disabled)
  }
})

// Cleanup
onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
  }
})

// Toolbar actions
const toggleBold = () => editor.value?.chain().focus().toggleBold().run()
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run()
const toggleCode = () => editor.value?.chain().focus().toggleCode().run()

// Check if commands are active
const isActive = (name: string) => {
  return editor.value?.isActive(name) ?? false
}

// Focus the editor
const focus = () => {
  editor.value?.commands.focus()
}

// Get text content (without HTML)
const getTextContent = () => {
  return editor.value?.getText() ?? ''
}

// Clear content
const clear = () => {
  editor.value?.commands.clearContent()
}

// Expose methods
defineExpose({
  focus,
  clear,
  getTextContent,
  editor
})
</script>

<template>
  <div class="w-full border rounded-md bg-background">
    <!-- Compact Toolbar -->
    <div v-if="showToolbar" class="flex items-center gap-1 p-1 border-b bg-muted/20">
      <Button
        @click="toggleBold"
        :variant="isActive('bold') ? 'default' : 'ghost'"
        size="sm"
        class="h-6 w-6 p-0"
      >
        <Bold class="w-3 h-3" />
      </Button>
      
      <Button
        @click="toggleItalic"
        :variant="isActive('italic') ? 'default' : 'ghost'"
        size="sm"
        class="h-6 w-6 p-0"
      >
        <Italic class="w-3 h-3" />
      </Button>
      
      <Button
        @click="toggleCode"
        :variant="isActive('code') ? 'default' : 'ghost'"
        size="sm"
        class="h-6 w-6 p-0"
      >
        <Code class="w-3 h-3" />
      </Button>

      <!-- Send Button -->
      <div class="flex-1"></div>
      <Button
        @click="emit('submit')"
        :disabled="disabled || !getTextContent().trim()"
        size="sm"
        class="h-6 px-2"
      >
        <Send class="w-3 h-3" />
      </Button>
    </div>

    <!-- Editor Content -->
    <div class="relative">
      <EditorContent 
        :editor="editor" 
        class="compact-prose-editor"
      />
    </div>
  </div>
</template>

<style scoped>
:deep(.ProseMirror) {
  outline: none !important;
  border: none !important;
  min-height: 2rem;
  max-height: 8rem;
  overflow-y: auto;
  padding: 8px;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  color: hsl(var(--muted-foreground));
  pointer-events: none;
  height: 0;
}

:deep(.ProseMirror p) {
  margin: 0;
  line-height: 1.5;
}

:deep(.ProseMirror p + p) {
  margin-top: 0.5rem;
}

:deep(.ProseMirror code) {
  background: hsl(var(--muted));
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  font-size: 0.875rem;
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

:deep(.ProseMirror strong) {
  font-weight: 600;
}

:deep(.ProseMirror em) {
  font-style: italic;
}

.compact-prose-editor {
  font-size: 0.875rem;
  line-height: 1.5;
}
</style>