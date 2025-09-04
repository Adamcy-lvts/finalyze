<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Typography from '@tiptap/extension-typography'
import { FontSize, TextStyle } from '@tiptap/extension-text-style'
import { watch, onBeforeUnmount, ref, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

interface Props {
  content: string
  class?: string
  showFontControls?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  class: '',
  showFontControls: true
})

// Helper function to convert markdown/plain text to proper HTML
const convertTextToHTML = (text: string): string => {
  if (!text) return ''
  
  // If it's already HTML (contains HTML tags), return as is
  if (text.includes('<p>') || text.includes('<h1>') || text.includes('<h2>') || text.includes('<div>')) {
    return text
  }
  
  // Convert markdown/text content to HTML
  let html = text
  
  // Convert headings with proper hierarchy
  html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>')  // H3 first (most specific)
  html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>')   // H2 second
  html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>')    // H1 last (least specific)
  
  // Convert bold text
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
  
  // Convert italic text
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>')
  
  // Convert inline code
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')
  
  // Split into blocks (paragraphs/sections)
  const blocks = html.split('\n\n').filter(block => block.trim())
  
  return blocks.map(block => {
    const trimmed = block.trim()
    
    // If it's already a heading, return as is
    if (trimmed.startsWith('<h1>') || trimmed.startsWith('<h2>') || trimmed.startsWith('<h3>')) {
      return trimmed
    }
    
    // Handle numbered/bulleted lists
    if (trimmed.includes('\n') && (trimmed.match(/^\d+\./) || trimmed.match(/^-\s/) || trimmed.match(/^\*\s/))) {
      const listItems = trimmed.split('\n').map(line => {
        const cleanLine = line.trim()
        if (cleanLine.match(/^\d+\.\s/)) {
          return `<li>${cleanLine.replace(/^\d+\.\s/, '')}</li>`
        } else if (cleanLine.match(/^[-*]\s/)) {
          return `<li>${cleanLine.replace(/^[-*]\s/, '')}</li>`
        }
        return cleanLine
      }).filter(item => item.startsWith('<li>'))
      
      if (trimmed.match(/^\d+\./)) {
        return `<ol>${listItems.join('')}</ol>`
      } else {
        return `<ul>${listItems.join('')}</ul>`
      }
    }
    
    // Regular paragraphs
    const content = trimmed.replace(/\n/g, '<br>')
    return `<p>${content}</p>`
  }).join('')
}

// Initialize Tiptap editor for readonly display
const editor = useEditor({
  content: convertTextToHTML(props.content),
  editable: false,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3]
      }
    }),
    Typography,
    TextStyle,
    FontSize
  ],
  editorProps: {
    attributes: {
      class: 'prose prose-sm dark:prose-invert max-w-none focus:outline-none'
    }
  }
})

// Font size controls - Microsoft Word standard sizes
const fontSize = ref('12') // Default 12pt
const fontSizes = ['8', '9', '10', '11', '12', '14', '16', '18', '20', '22', '24', '26', '28', '36', '48', '72']

const changeFontSize = (size: string) => {
  fontSize.value = size
  if (editor.value) {
    // Apply font size to all content since this is a viewer
    editor.value.chain().selectAll().setFontSize(`${size}pt`).run()
  }
}

// Initialize font size on mount
onMounted(() => {
  setTimeout(() => {
    if (editor.value) {
      // Set default font size for all content
      editor.value.chain().selectAll().setFontSize('12pt').run()
    }
  }, 100)
})

// Watch for content changes
watch(() => props.content, (newContent) => {
  if (editor.value && editor.value.getHTML() !== newContent) {
    const processedContent = convertTextToHTML(newContent)
    editor.value.commands.setContent(processedContent, false)
  }
})

// Cleanup
onBeforeUnmount(() => {
  if (editor.value) {
    editor.value.destroy()
  }
})
</script>

<template>
  <div :class="props.class">
    <!-- Font Size Controls -->
    <div v-if="showFontControls" class="flex items-center gap-2 mb-3 p-2 bg-muted/30 rounded-md">
      <span class="text-sm font-medium">Font Size:</span>
      <Select :value="fontSize" @update:value="changeFontSize">
        <SelectTrigger class="w-16 h-8">
          <SelectValue>
            <span class="text-sm">{{ fontSize }}</span>
          </SelectValue>
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="size in fontSizes" :key="size" :value="size">
            {{ size }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <EditorContent :editor="editor" />
  </div>
</template>

<style scoped>
:deep(.ProseMirror) {
  outline: none !important;
  border: none !important;
  padding: 0;
}

:deep(.ProseMirror h1) {
  font-size: 1.8rem;
  font-weight: 700;
  line-height: 1.2;
  margin-top: 2rem;
  margin-bottom: 1rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror h2) {
  font-size: 1.4rem;
  font-weight: 700;
  line-height: 1.3;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror h3) {
  font-size: 1.2rem;
  font-weight: 600;
  line-height: 1.4;
  margin-top: 1.25rem;
  margin-bottom: 0.5rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror ul) {
  list-style: disc;
  padding-left: 1.5rem;
  margin: 1rem 0;
}

:deep(.ProseMirror ol) {
  list-style: decimal;
  padding-left: 1.5rem;
  margin: 1rem 0;
}

:deep(.ProseMirror li) {
  margin: 0.25rem 0;
}

:deep(.ProseMirror blockquote) {
  border-left: 4px solid hsl(var(--border));
  padding-left: 1rem;
  margin: 1rem 0;
  font-style: italic;
}

:deep(.ProseMirror code) {
  background: hsl(var(--muted));
  padding: 0.25rem 0.375rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-family: ui-monospace, SFMono-Regular, 'SF Mono', Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
}

:deep(.ProseMirror pre) {
  background: hsl(var(--muted));
  border-radius: 0.5rem;
  padding: 1rem;
  margin: 1rem 0;
  overflow-x: auto;
}

:deep(.ProseMirror pre code) {
  background: none;
  padding: 0;
  border-radius: 0;
  font-size: inherit;
}

:deep(.ProseMirror p) {
  margin-top: 0;
  margin-bottom: 1rem;
  line-height: 1.6;
  text-align: justify;
  text-indent: 0;
}

:deep(.ProseMirror p:first-child) {
  margin-top: 0;
}

:deep(.ProseMirror p:last-child) {
  margin-bottom: 0;
}

/* Academic formatting improvements */
:deep(.ProseMirror) {
  font-family: 'Times New Roman', 'Liberation Serif', serif;
  font-size: 12pt;
  line-height: 1.6;
}

/* Better spacing for academic structure */
:deep(.ProseMirror h1 + p),
:deep(.ProseMirror h2 + p),
:deep(.ProseMirror h3 + p) {
  margin-top: 0.5rem;
}

/* Ensure proper spacing between sections */
:deep(.ProseMirror h1) {
  border-bottom: none;
  margin-top: 2.5rem;
}

:deep(.ProseMirror h1:first-child) {
  margin-top: 0;
}

/* Better list formatting */
:deep(.ProseMirror ol),
:deep(.ProseMirror ul) {
  margin-bottom: 1rem;
}

:deep(.ProseMirror li) {
  margin-bottom: 0.25rem;
  line-height: 1.6;
}
</style>