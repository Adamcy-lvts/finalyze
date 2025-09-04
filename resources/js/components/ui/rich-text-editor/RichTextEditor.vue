<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Typography from '@tiptap/extension-typography'
import { TextStyle } from '@tiptap/extension-text-style'
import { Extension } from '@tiptap/core'
import Underline from '@tiptap/extension-underline'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Bold,
  Italic,
  Strikethrough,
  Code,
  Heading1,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  Quote,
  Undo,
  Redo,
  Type,
  Underline as UnderlineIcon,
  Minus,
  Plus,
  RotateCcw
} from 'lucide-vue-next'

interface Props {
  modelValue: string
  placeholder?: string
  readonly?: boolean
  minHeight?: string
  showToolbar?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Start writing...',
  readonly: false,
  minHeight: '200px',
  showToolbar: true
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

// Custom FontSize extension
const FontSize = Extension.create({
  name: 'fontSize',

  addOptions() {
    return {
      types: ['textStyle'],
    }
  },

  addGlobalAttributes() {
    return [
      {
        types: this.options.types,
        attributes: {
          fontSize: {
            default: null,
            parseHTML: element => {
              const fontSize = element.style.fontSize
              return fontSize ? fontSize : null
            },
            renderHTML: attributes => {
              if (!attributes.fontSize) {
                return {}
              }
              return {
                style: `font-size: ${attributes.fontSize}`,
              }
            },
          },
        },
      },
    ]
  },

  addCommands() {
    return {
      setFontSize: fontSize => ({ chain }) => {
        return chain()
          .setMark('textStyle', { fontSize })
          .run()
      },
      unsetFontSize: () => ({ chain }) => {
        return chain()
          .setMark('textStyle', { fontSize: null })
          .removeEmptyTextStyle()
          .run()
      },
    }
  },
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

// Initialize Tiptap editor
const editor = useEditor({
  content: convertTextToHTML(props.modelValue),
  editable: !props.readonly,
  extensions: [
    StarterKit.configure({
      heading: {
        levels: [1, 2, 3]
      }
    }),
    Placeholder.configure({
      placeholder: props.placeholder
    }),
    Typography,
    TextStyle,
    FontSize,
    Underline
  ],
  editorProps: {
    attributes: {
      class: `prose prose-sm dark:prose-invert max-w-none focus:outline-none`,
      style: `min-height: ${props.minHeight}; padding: 12px;`
    }
  },
  onUpdate: ({ editor }) => {
    emit('update:modelValue', editor.getHTML())
    updateCurrentFontSize()
  },
  onSelectionUpdate: () => {
    updateCurrentFontSize()
  }
})

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (editor.value && editor.value.getHTML() !== newValue) {
    const processedContent = convertTextToHTML(newValue)
    editor.value.commands.setContent(processedContent, { emitUpdate: false })
  }
})

// Watch readonly state
watch(() => props.readonly, (readonly) => {
  if (editor.value) {
    editor.value.setEditable(!readonly)
  }
})

// Initialize editor state on mount
onMounted(() => {
  setTimeout(() => {
    if (editor.value) {
      updateCurrentFontSize()
    }
  }, 100)
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
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run()
const toggleCode = () => editor.value?.chain().focus().toggleCode().run()
const toggleUnderline = () => editor.value?.chain().focus().toggleUnderline().run()
const toggleHeading1 = () => editor.value?.chain().focus().toggleHeading({ level: 1 }).run()
const toggleHeading2 = () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run()
const toggleHeading3 = () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run()
const toggleBulletList = () => editor.value?.chain().focus().toggleBulletList().run()
const toggleOrderedList = () => editor.value?.chain().focus().toggleOrderedList().run()
const toggleBlockquote = () => editor.value?.chain().focus().toggleBlockquote().run()
const setParagraph = () => editor.value?.chain().focus().setParagraph().run()
const undo = () => editor.value?.chain().focus().undo().run()
const redo = () => editor.value?.chain().focus().redo().run()

// Font size controls - Enhanced with pixel sizes and labels
const fontSize = ref('16px') // Default 16px
const fontSizes = [
  { value: '10px', label: '10px - Tiny' },
  { value: '12px', label: '12px - Small' },
  { value: '14px', label: '14px - Normal' },
  { value: '16px', label: '16px - Medium' },
  { value: '18px', label: '18px - Large' },
  { value: '20px', label: '20px' },
  { value: '24px', label: '24px - Heading 3' },
  { value: '28px', label: '28px - Heading 2' },
  { value: '32px', label: '32px - Heading 1' },
  { value: '36px', label: '36px - Display' },
  { value: '48px', label: '48px - Extra Large' }
]

// Size progression for increase/decrease buttons
const sizeProgression = ['10px', '12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '36px', '48px']

const changeFontSize = (size: string) => {
  if (!editor.value) return
  
  if (size === 'default') {
    fontSize.value = '16px'
    editor.value.chain().focus().unsetFontSize().run()
  } else {
    fontSize.value = size
    // Apply font size to current selection or cursor position
    editor.value.chain().focus().setFontSize(size).run()
  }
  
  // Small delay to ensure the command has been applied before updating UI
  setTimeout(() => {
    updateCurrentFontSize()
  }, 10)
}

const increaseFontSize = () => {
  const currentSize = getCurrentFontSizeValue()
  const currentIndex = sizeProgression.indexOf(currentSize)
  
  if (currentIndex === -1) {
    // If no size set or unknown size, start from 18px
    editor.value?.chain().focus().setFontSize('18px').run()
  } else if (currentIndex < sizeProgression.length - 1) {
    const newSize = sizeProgression[currentIndex + 1]
    editor.value?.chain().focus().setFontSize(newSize).run()
  }
  updateCurrentFontSize()
}

const decreaseFontSize = () => {
  const currentSize = getCurrentFontSizeValue()
  const currentIndex = sizeProgression.indexOf(currentSize)
  
  if (currentIndex === -1) {
    // If no size set or unknown size, start from 14px
    editor.value?.chain().focus().setFontSize('14px').run()
  } else if (currentIndex > 0) {
    const newSize = sizeProgression[currentIndex - 1]
    editor.value?.chain().focus().setFontSize(newSize).run()
  }
  updateCurrentFontSize()
}

const getCurrentFontSizeValue = () => {
  // Get the font size from the current selection or cursor position
  const { from, to } = editor.value?.state.selection || { from: 0, to: 0 }
  let fontSize = ''
  
  editor.value?.state.doc.nodesBetween(from, to, (node) => {
    if (node.marks) {
      node.marks.forEach(mark => {
        if (mark.type.name === 'textStyle' && mark.attrs.fontSize) {
          fontSize = mark.attrs.fontSize
        }
      })
    }
  })
  
  return fontSize || '16px' // Default to 16px if no size is set
}

const updateCurrentFontSize = () => {
  // Update the displayed current font size
  const currentSize = getCurrentFontSizeValue()
  // If the current size is the default 16px, show it as such
  fontSize.value = currentSize || '16px'
}

const resetFontSize = () => {
  editor.value?.chain().focus().unsetFontSize().run()
  fontSize.value = '16px'
  updateCurrentFontSize()
}

// Check if commands are active
const isActive = (name: string, attrs = {}) => {
  return editor.value?.isActive(name, attrs) ?? false
}

const canUndo = () => editor.value?.can().undo() ?? false
const canRedo = () => editor.value?.can().redo() ?? false

// Word count computed property
const wordCount = computed(() => {
  if (!editor.value) return 0
  const text = editor.value.state.doc.textContent
  return text.split(/\s+/).filter(word => word.length > 0).length
})
</script>

<template>
  <div class="w-full border rounded-md bg-background">
    <!-- Toolbar -->
    <div v-if="showToolbar && !readonly" class="flex items-center gap-1 p-2 border-b bg-muted/30">
      <!-- Text Formatting -->
      <Button
        @click="toggleBold"
        :variant="isActive('bold') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Bold class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleItalic"
        :variant="isActive('italic') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Italic class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleStrike"
        :variant="isActive('strike') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Strikethrough class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleCode"
        :variant="isActive('code') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Code class="w-4 h-4" />
      </Button>

      <Button
        @click="toggleUnderline"
        :variant="isActive('underline') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <UnderlineIcon class="w-4 h-4" />
      </Button>

      <Separator orientation="vertical" class="h-6" />
      
      <!-- Headings -->
      <Button
        @click="setParagraph"
        :variant="isActive('paragraph') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Type class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleHeading1"
        :variant="isActive('heading', { level: 1 }) ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Heading1 class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleHeading2"
        :variant="isActive('heading', { level: 2 }) ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Heading2 class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleHeading3"
        :variant="isActive('heading', { level: 3 }) ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Heading3 class="w-4 h-4" />
      </Button>

      <Separator orientation="vertical" class="h-6" />
      
      <!-- Lists -->
      <Button
        @click="toggleBulletList"
        :variant="isActive('bulletList') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <List class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleOrderedList"
        :variant="isActive('orderedList') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <ListOrdered class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleBlockquote"
        :variant="isActive('blockquote') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Quote class="w-4 h-4" />
      </Button>

      <Separator orientation="vertical" class="h-6" />
      
      <!-- Font Size Controls -->
      <div class="flex items-center gap-1">
        <!-- Font Size Selector - Hidden in preview mode -->
        <Select 
          v-if="!readonly"
          :model-value="fontSize" 
          @update:model-value="changeFontSize"
        >
          <SelectTrigger class="w-32 h-8">
            <SelectValue>
              <span class="text-sm">{{ fontSize || 'Default' }}</span>
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="default">Default (16px)</SelectItem>
            <SelectItem v-for="size in fontSizes" :key="size.value" :value="size.value">
              {{ size.label }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Quick Size Buttons -->
        <Button
          @click="decreaseFontSize"
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0"
          title="Decrease font size"
        >
          <Minus class="w-3 h-3" />
        </Button>
        
        <Button
          @click="increaseFontSize"
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0"
          title="Increase font size"
        >
          <Plus class="w-3 h-3" />
        </Button>

        <Button
          @click="resetFontSize"
          variant="ghost"
          size="sm"
          class="h-8 px-2"
          title="Reset to default size"
        >
          <RotateCcw class="w-3 h-3" />
        </Button>
      </div>

      <Separator orientation="vertical" class="h-6" />
      
      <!-- Undo/Redo -->
      <Button
        @click="undo"
        :disabled="!canUndo()"
        variant="ghost"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Undo class="w-4 h-4" />
      </Button>
      
      <Button
        @click="redo"
        :disabled="!canRedo()"
        variant="ghost"
        size="sm"
        class="h-8 w-8 p-0"
      >
        <Redo class="w-4 h-4" />
      </Button>
    </div>

    <!-- Editor Content -->
    <div class="relative">
      <EditorContent 
        :editor="editor" 
        class="prose-editor"
        :class="{ 'cursor-default': readonly }"
      />
    </div>

    <!-- Status Bar -->
    <div v-if="showToolbar && !readonly" class="flex items-center justify-between px-3 py-2 border-t bg-muted/30 text-xs text-muted-foreground">
      <span>Current font size: {{ fontSize || 'Default (16px)' }}</span>
      <span>Words: {{ wordCount }}</span>
    </div>
  </div>
</template>

<style scoped>
:deep(.ProseMirror) {
  outline: none !important;
  border: none !important;
  min-height: v-bind(minHeight);
  padding: 12px;
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
  content: attr(data-placeholder);
  float: left;
  color: hsl(var(--muted-foreground));
  pointer-events: none;
  height: 0;
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

.prose-editor {
  line-height: 1.6;
  font-family: 'Times New Roman', 'Liberation Serif', serif;
}

/* Academic formatting improvements */
:deep(.ProseMirror p) {
  margin-top: 0;
  margin-bottom: 1rem;
  line-height: 1.6;
  text-align: justify;
  text-indent: 0;
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