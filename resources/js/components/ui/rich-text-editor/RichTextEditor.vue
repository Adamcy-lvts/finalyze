<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount, computed, nextTick } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Typography from '@tiptap/extension-typography'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Highlight } from '@tiptap/extension-highlight'
import { Link } from '@tiptap/extension-link'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table'
import { TableHeader } from '@tiptap/extension-table'
import { TableCell } from '@tiptap/extension-table'
import { Gapcursor } from '@tiptap/extension-gapcursor'
import { Extension } from '@tiptap/core'
// import Underline from '@tiptap/extension-underline' // Commented out to avoid duplicate with StarterKit
import { Citation } from '@/tiptap-extensions/CitationExtension.js'
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
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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
  RotateCcw,
  Link as LinkIcon,
  Highlighter,
  Palette,
  AlignLeft,
  AlignCenter,
  AlignRight,
  AlignJustify,
  Table as TableIcon,
  Columns,
  Rows,
  Trash2
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
  'update:selectedText': [value: string]
  'update:selectionRange': [value: { from: number, to: number } | null]
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

// Helper function to convert Markdown tables to HTML tables
const convertMarkdownTablesToHTML = (text: string): string => {
  if (!text) return ''

  // More robust approach: find table blocks line by line
  const lines = text.split('\n')
  let result = []
  let i = 0

  while (i < lines.length) {
    const line = lines[i]

    // Check if this line starts a table (contains pipes)
    if (line.includes('|')) {
      // Try to parse a table starting from this line
      const tableResult = parseMarkdownTable(lines, i)
      if (tableResult.html) {
        result.push(tableResult.html)
        i = tableResult.nextIndex
        continue
      }
    }

    // Not a table line, add as is
    result.push(line)
    i++
  }

  return result.join('\n')
}

// Helper function to parse a Markdown table starting at a given line index
const parseMarkdownTable = (lines: string[], startIndex: number): { html: string | null, nextIndex: number } => {
  let headerLine = lines[startIndex]
  if (!headerLine || !headerLine.includes('|')) {
    return { html: null, nextIndex: startIndex + 1 }
  }

  // Parse header
  const headerCells = headerLine
    .split('|')
    .map(cell => cell.trim())
    .filter(cell => cell.length > 0)

  if (headerCells.length === 0) {
    return { html: null, nextIndex: startIndex + 1 }
  }

  // Check for separator line
  const separatorLine = lines[startIndex + 1]
  if (!separatorLine || !separatorLine.includes('|') || !separatorLine.includes('-')) {
    return { html: null, nextIndex: startIndex + 1 }
  }

  // Parse data rows
  let dataRows = []
  let currentIndex = startIndex + 2 // Skip header and separator

  while (currentIndex < lines.length) {
    const line = lines[currentIndex]
    if (!line || !line.includes('|')) {
      break // End of table
    }

    const cells = line
      .split('|')
      .map(cell => cell.trim())
      .filter(cell => cell.length > 0)

    if (cells.length > 0) {
      dataRows.push(cells)
    }

    currentIndex++
  }

  // Build HTML table
  let tableHTML = '<div class="tableWrapper"><table>'

  // Add header
  tableHTML += '<thead><tr>'
  headerCells.forEach(cell => {
    tableHTML += `<th>${cell}</th>`
  })
  tableHTML += '</tr></thead>'

  // Add body
  if (dataRows.length > 0) {
    tableHTML += '<tbody>'
    dataRows.forEach(row => {
      tableHTML += '<tr>'
      // Ensure we don't exceed header column count
      for (let i = 0; i < headerCells.length; i++) {
        const cellContent = row[i] || ''
        tableHTML += `<td>${cellContent}</td>`
      }
      tableHTML += '</tr>'
    })
    tableHTML += '</tbody>'
  }

  tableHTML += '</table></div>'

  return { html: tableHTML, nextIndex: currentIndex }
}

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

  // Convert Markdown tables to HTML tables
  html = convertMarkdownTablesToHTML(html)

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
    Color,
    Highlight.configure({
      multicolor: true
    }),
    Link.configure({
      openOnClick: false,
      HTMLAttributes: {
        class: 'prose-link text-primary hover:text-primary/80 underline decoration-primary/50'
      }
    }),
    Gapcursor,
    Table.configure({
      resizable: true,
    }),
    TableRow,
    TableHeader,
    TableCell,
    // Underline, // Commented out - StarterKit might include this
    Citation
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
  onSelectionUpdate: ({ editor }) => {
    updateCurrentFontSize()
    
    // Track selected text for AI features (optimized for performance)
    const { from, to, empty } = editor.state.selection
    
    if (!empty) {
      const selectedContent = editor.state.doc.textBetween(from, to)
      const wordCount = selectedContent.split(/\s+/).filter(word => word.length > 0).length
      
      // Only emit for selections under 200 words (rephrase limit)
      if (wordCount <= 200) {
        emit('update:selectedText', selectedContent)
        emit('update:selectionRange', { from, to })
        console.log('✅ RichTextEditor - Selection emitted to parent')
      } else {
        // Clear selection if too long
        emit('update:selectedText', '')
        emit('update:selectionRange', null)
        console.log('❌ RichTextEditor - Selection too long, cleared')
      }
    } else {
      // No selection
      emit('update:selectedText', '')
      emit('update:selectionRange', null)
      console.log('🔄 RichTextEditor - No selection, cleared')
    }
  }
})

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (editor.value && editor.value.getHTML() !== newValue) {
    const processedContent = convertTextToHTML(newValue)
    editor.value.commands.setContent(processedContent, { emitUpdate: false })
    
    // Emit the processed HTML content back to parent after conversion
    nextTick(() => {
      if (editor.value) {
        emit('update:modelValue', editor.value.getHTML())
      }
    })
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

// Dialog states
const linkDialogOpen = ref(false)
const colorDialogOpen = ref(false)

// Form data
const linkForm = ref({
  url: '',
  text: ''
})

const colorForm = ref({
  color: '#000000'
})

// Link functionality
const openLinkDialog = () => {
  const { from, to } = editor.value?.state.selection || { from: 0, to: 0 }
  const selectedText = editor.value?.state.doc.textBetween(from, to) || ''
  
  if (editor.value?.isActive('link')) {
    // Remove link if already active
    editor.value.chain().focus().unsetLink().run()
  } else {
    // Open dialog to add link
    linkForm.value.text = selectedText
    linkForm.value.url = ''
    linkDialogOpen.value = true
  }
}

const applyLink = () => {
  if (!linkForm.value.url) return
  
  if (linkForm.value.text) {
    // Replace selection with link
    const { from, to } = editor.value?.state.selection || { from: 0, to: 0 }
    editor.value?.chain()
      .focus()
      .deleteRange({ from, to })
      .insertContent(`<a href="${linkForm.value.url}">${linkForm.value.text}</a>`)
      .run()
  } else {
    // Just apply link to current selection
    editor.value?.chain().focus().setLink({ href: linkForm.value.url }).run()
  }
  
  linkDialogOpen.value = false
}

// Text color functionality
const openColorDialog = () => {
  colorDialogOpen.value = true
}

const applyColor = () => {
  if (colorForm.value.color) {
    editor.value?.chain().focus().setColor(colorForm.value.color).run()
  }
  colorDialogOpen.value = false
}

const removeTextColor = () => {
  editor.value?.chain().focus().unsetColor().run()
}

// Highlight functionality
const currentHighlight = ref('#ffff00')
const toggleHighlight = () => {
  if (editor.value?.isActive('highlight')) {
    editor.value.chain().focus().unsetHighlight().run()
  } else {
    const color = prompt('Enter highlight color (hex, rgb, or color name):', currentHighlight.value)
    if (color) {
      currentHighlight.value = color
      editor.value?.chain().focus().setHighlight({ color }).run()
    } else {
      editor.value?.chain().focus().setHighlight().run()
    }
  }
}

// Text alignment functionality
const setTextAlign = (alignment: 'left' | 'center' | 'right' | 'justify') => {
  // Note: StarterKit doesn't include text-align by default, but we can add it later
  console.log(`Text alignment ${alignment} would be applied here`)
  // For now, just log - we'd need to add the TextAlign extension
}

// Table functionality
const insertTable = () => {
  editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}

const addColumnBefore = () => {
  editor.value?.chain().focus().addColumnBefore().run()
}

const addColumnAfter = () => {
  editor.value?.chain().focus().addColumnAfter().run()
}

const deleteColumn = () => {
  editor.value?.chain().focus().deleteColumn().run()
}

const addRowBefore = () => {
  editor.value?.chain().focus().addRowBefore().run()
}

const addRowAfter = () => {
  editor.value?.chain().focus().addRowAfter().run()
}

const deleteRow = () => {
  editor.value?.chain().focus().deleteRow().run()
}

const deleteTable = () => {
  editor.value?.chain().focus().deleteTable().run()
}

const mergeCells = () => {
  editor.value?.chain().focus().mergeCells().run()
}

const splitCell = () => {
  editor.value?.chain().focus().splitCell().run()
}

const toggleHeaderColumn = () => {
  editor.value?.chain().focus().toggleHeaderColumn().run()
}

const toggleHeaderRow = () => {
  editor.value?.chain().focus().toggleHeaderRow().run()
}

const toggleHeaderCell = () => {
  editor.value?.chain().focus().toggleHeaderCell().run()
}

// Check if we're inside a table
const isInTable = () => {
  return editor.value?.isActive('table') ?? false
}

// Font size controls - Using points like MS Word
const fontSize = ref('12pt') // Default 12pt (standard Word size)
const fontSizes = [
  { value: '8pt', label: '8' },
  { value: '9pt', label: '9' },
  { value: '10pt', label: '10' },
  { value: '11pt', label: '11' },
  { value: '12pt', label: '12' },
  { value: '14pt', label: '14' },
  { value: '16pt', label: '16' },
  { value: '18pt', label: '18' },
  { value: '20pt', label: '20' },
  { value: '24pt', label: '24' },
  { value: '28pt', label: '28' },
  { value: '36pt', label: '36' },
  { value: '48pt', label: '48' },
  { value: '72pt', label: '72' }
]

// Size progression for increase/decrease buttons
const sizeProgression = ['8pt', '9pt', '10pt', '11pt', '12pt', '14pt', '16pt', '18pt', '20pt', '24pt', '28pt', '36pt', '48pt', '72pt']

const changeFontSize = (size: string | null) => {
  if (!editor.value || !size) return
  
  if (size === 'default') {
    fontSize.value = '12pt'
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
    // If no size set or unknown size, start from 14pt
    editor.value?.chain().focus().setFontSize('14pt').run()
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
    // If no size set or unknown size, start from 11pt
    editor.value?.chain().focus().setFontSize('11pt').run()
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
  
  return fontSize || '12pt' // Default to 12pt if no size is set
}

const updateCurrentFontSize = () => {
  // Update the displayed current font size
  const currentSize = getCurrentFontSizeValue()
  // If the current size is the default 12pt, show it as such
  fontSize.value = currentSize || '12pt'
}

const resetFontSize = () => {
  editor.value?.chain().focus().unsetFontSize().run()
  fontSize.value = '12pt'
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

// Selection management methods for precise text replacement
const getSelectionRange = () => {
  if (!editor.value) return null
  const { from, to, empty } = editor.value.state.selection
  if (empty) return null
  return { from, to }
}

const getSelectedText = () => {
  if (!editor.value) return ''
  const { from, to } = editor.value.state.selection
  return editor.value.state.doc.textBetween(from, to)
}

const replaceSelection = (newText: string, range?: { from: number, to: number }) => {
  if (!editor.value) {
    console.error('❌ RichTextEditor - Cannot replace selection: editor not available')
    return false
  }
  
  try {
    const replaceRange = range || editor.value.state.selection
    
    console.log('🔄 RichTextEditor - Starting text replacement:', {
      range: replaceRange,
      newTextLength: newText.length,
      newTextPreview: newText.substring(0, 100) + (newText.length > 100 ? '...' : '')
    })
    
    // Use insertContentAt to replace the selected text with proper content parsing
    // This ensures headers and formatting are interpreted correctly
    editor.value
      .chain()
      .focus()
      .insertContentAt(
        { from: replaceRange.from, to: replaceRange.to }, 
        newText,
        {
          parseOptions: {
            preserveWhitespace: 'full',
          }
        }
      )
      .run()
    
    console.log('✅ RichTextEditor - Text replacement successful')
    return true
  } catch (error) {
    console.error('❌ RichTextEditor - Error replacing selection:', error)
    return false
  }
}

const replaceTextAt = (position: { from: number, to: number }, newText: string) => {
  if (!editor.value) return false
  
  try {
    editor.value
      .chain()
      .focus()
      .insertContentAt(position, newText)
      .run()
    
    return true
  } catch (error) {
    console.error('Error replacing text at position:', error)
    return false
  }
}

// Get current cursor position
const getCursorPosition = () => {
  if (!editor.value) return 0
  return editor.value.state.selection.anchor
}

// Set cursor to specific position
const setCursorPosition = (position: number) => {
  if (!editor.value) return false
  
  try {
    editor.value.commands.setTextSelection(position)
    return true
  } catch (error) {
    console.error('Error setting cursor position:', error)
    return false
  }
}

// Expose methods to parent components
defineExpose({
  getSelectionRange,
  getSelectedText,
  replaceSelection,
  replaceTextAt,
  getCursorPosition,
  setCursorPosition,
  focus: () => editor.value?.commands.focus(),
  blur: () => editor.value?.commands.blur(),
  getHTML: () => editor.value?.getHTML() || '',
  getText: () => editor.value?.getText() || '',
  wordCount
})
</script>

<template>
  <div class="w-full border rounded-md bg-background">
    <!-- Toolbar -->
    <div v-if="showToolbar && !readonly" class="sticky top-0 z-10 flex items-center gap-1 p-2 border-b bg-muted/30 backdrop-blur-sm">
      <!-- Text Formatting -->
      <Button
        @click="toggleBold"
        :variant="isActive('bold') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Bold (Ctrl+B)"
      >
        <Bold class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleItalic"
        :variant="isActive('italic') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Italic (Ctrl+I)"
      >
        <Italic class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleStrike"
        :variant="isActive('strike') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Strikethrough"
      >
        <Strikethrough class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleCode"
        :variant="isActive('code') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Inline Code"
      >
        <Code class="w-4 h-4" />
      </Button>

      <Button
        @click="toggleUnderline"
        :variant="isActive('underline') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Underline (Ctrl+U)"
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
        title="Normal Text"
      >
        <Type class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleHeading1"
        :variant="isActive('heading', { level: 1 }) ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Heading 1 (Ctrl+Alt+1)"
      >
        <Heading1 class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleHeading2"
        :variant="isActive('heading', { level: 2 }) ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Heading 2 (Ctrl+Alt+2)"
      >
        <Heading2 class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleHeading3"
        :variant="isActive('heading', { level: 3 }) ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Heading 3 (Ctrl+Alt+3)"
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
        title="Bullet List"
      >
        <List class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleOrderedList"
        :variant="isActive('orderedList') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Numbered List"
      >
        <ListOrdered class="w-4 h-4" />
      </Button>
      
      <Button
        @click="toggleBlockquote"
        :variant="isActive('blockquote') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Blockquote"
      >
        <Quote class="w-4 h-4" />
      </Button>

      <Separator orientation="vertical" class="h-6" />

      <!-- Advanced Formatting -->
      <Button
        @click="openLinkDialog"
        :variant="isActive('link') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Insert/Edit Link (Ctrl+K)"
      >
        <LinkIcon class="w-4 h-4" />
      </Button>

      <Button
        @click="openColorDialog"
        variant="ghost"
        size="sm"
        class="h-8 w-8 p-0"
        title="Text Color"
      >
        <Palette class="w-4 h-4" />
      </Button>

      <Button
        @click="toggleHighlight"
        :variant="isActive('highlight') ? 'default' : 'ghost'"
        size="sm"
        class="h-8 w-8 p-0"
        title="Highlight Text"
      >
        <Highlighter class="w-4 h-4" />
      </Button>

      <Separator orientation="vertical" class="h-6" />

      <!-- Table Controls -->
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            size="sm"
            class="h-8 w-8 p-0"
            title="Table Tools"
          >
            <TableIcon class="w-4 h-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-48">
          <DropdownMenuItem @click="insertTable">
            <TableIcon class="w-4 h-4 mr-2" />
            Insert Table
          </DropdownMenuItem>
          
          <template v-if="isInTable()">
            <DropdownMenuSeparator />
            
            <DropdownMenuItem @click="addColumnBefore">
              <Columns class="w-4 h-4 mr-2" />
              Add Column Before
            </DropdownMenuItem>
            <DropdownMenuItem @click="addColumnAfter">
              <Columns class="w-4 h-4 mr-2" />
              Add Column After
            </DropdownMenuItem>
            <DropdownMenuItem @click="deleteColumn">
              <Trash2 class="w-4 h-4 mr-2" />
              Delete Column
            </DropdownMenuItem>
            
            <DropdownMenuSeparator />
            
            <DropdownMenuItem @click="addRowBefore">
              <Rows class="w-4 h-4 mr-2" />
              Add Row Before
            </DropdownMenuItem>
            <DropdownMenuItem @click="addRowAfter">
              <Rows class="w-4 h-4 mr-2" />
              Add Row After
            </DropdownMenuItem>
            <DropdownMenuItem @click="deleteRow">
              <Trash2 class="w-4 h-4 mr-2" />
              Delete Row
            </DropdownMenuItem>
            
            <DropdownMenuSeparator />
            
            <DropdownMenuItem @click="mergeCells">
              Merge Cells
            </DropdownMenuItem>
            <DropdownMenuItem @click="splitCell">
              Split Cell
            </DropdownMenuItem>
            
            <DropdownMenuSeparator />
            
            <DropdownMenuItem @click="toggleHeaderRow">
              Toggle Header Row
            </DropdownMenuItem>
            <DropdownMenuItem @click="toggleHeaderColumn">
              Toggle Header Column
            </DropdownMenuItem>
            
            <DropdownMenuSeparator />
            
            <DropdownMenuItem @click="deleteTable" class="text-destructive">
              <Trash2 class="w-4 h-4 mr-2" />
              Delete Table
            </DropdownMenuItem>
          </template>
        </DropdownMenuContent>
      </DropdownMenu>

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
            <SelectItem value="default">Default (12pt)</SelectItem>
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
          title="Decrease Font Size"
        >
          <Minus class="w-3 h-3" />
        </Button>
        
        <Button
          @click="increaseFontSize"
          variant="ghost"
          size="sm"
          class="h-8 w-8 p-0"
          title="Increase Font Size"
        >
          <Plus class="w-3 h-3" />
        </Button>

        <Button
          @click="resetFontSize"
          variant="ghost"
          size="sm"
          class="h-8 px-2"
          title="Reset Font Size"
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
        title="Undo (Ctrl+Z)"
      >
        <Undo class="w-4 h-4" />
      </Button>
      
      <Button
        @click="redo"
        :disabled="!canRedo()"
        variant="ghost"
        size="sm"
        class="h-8 w-8 p-0"
        title="Redo (Ctrl+Y)"
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
      <span>{{ fontSize || 'Default (12pt)' }}</span>
      <span>Words: {{ wordCount }}</span>
    </div>

    <!-- Link Dialog -->
    <Dialog v-model:open="linkDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Insert Link</DialogTitle>
          <DialogDescription>
            Add a hyperlink to your text.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label for="link-url">URL</Label>
            <Input
              id="link-url"
              v-model="linkForm.url"
              placeholder="https://example.com"
              type="url"
            />
          </div>
          <div class="grid gap-2">
            <Label for="link-text">Link Text</Label>
            <Input
              id="link-text"
              v-model="linkForm.text"
              placeholder="Link text (optional)"
            />
          </div>
        </div>
        <DialogFooter>
          <Button @click="linkDialogOpen = false" variant="outline">Cancel</Button>
          <Button @click="applyLink">Add Link</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Color Dialog -->
    <Dialog v-model:open="colorDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Text Color</DialogTitle>
          <DialogDescription>
            Choose a color for the selected text.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label for="text-color">Color</Label>
            <div class="flex gap-2">
              <Input
                id="text-color"
                v-model="colorForm.color"
                type="color"
                class="w-16 h-10 p-1 border-0"
              />
              <Input
                v-model="colorForm.color"
                placeholder="#000000"
                class="flex-1"
              />
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="removeTextColor" variant="outline">Remove Color</Button>
          <Button @click="colorDialogOpen = false" variant="outline">Cancel</Button>
          <Button @click="applyColor">Apply Color</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
:deep(.ProseMirror) {
  outline: none !important;
  border: none !important;
  min-height: v-bind(minHeight);
  padding: 12px;
  font-size: 14pt; /* Default font size in points like MS Word */
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

/* Link styles */
:deep(.ProseMirror a) {
  color: hsl(var(--primary));
  text-decoration: underline;
  text-decoration-color: hsl(var(--primary) / 0.5);
}

:deep(.ProseMirror a:hover) {
  color: hsl(var(--primary) / 0.8);
  text-decoration-color: hsl(var(--primary) / 0.8);
}

/* Highlight styles */
:deep(.ProseMirror mark) {
  background-color: hsl(var(--warning) / 0.3);
  color: inherit;
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
}

/* Table styles - Enhanced visibility with proper contrast */
:deep(.ProseMirror .tableWrapper) {
  margin: 1.5rem 0;
  overflow-x: auto;
}

:deep(.ProseMirror table) {
  border-collapse: separate;
  border-spacing: 0;
  table-layout: fixed;
  width: 100%;
  margin: 0;
  overflow: hidden;
  border: 1px solid #9ca3af;
  background-color: #ffffff;
}

:deep(.ProseMirror table td),
:deep(.ProseMirror table th) {
  min-width: 1em;
  border-right: 1px solid #9ca3af;
  border-bottom: 1px solid #9ca3af;
  padding: 10px 14px;
  vertical-align: top;
  box-sizing: border-box;
  position: relative;
  background-color: #ffffff;
  color: #1f2937;
}

:deep(.ProseMirror table td:last-child),
:deep(.ProseMirror table th:last-child) {
  border-right: none;
}

:deep(.ProseMirror table tr:last-child td) {
  border-bottom: none;
}

:deep(.ProseMirror table th) {
  background-color: #f3f4f6;
  font-weight: 600;
  text-align: left;
  color: #111827;
}

/* Dark mode table styles with better visibility */
.dark :deep(.ProseMirror table) {
  border: 1px solid #6b7280;
  background-color: #111827;
}

.dark :deep(.ProseMirror table td),
.dark :deep(.ProseMirror table th) {
  border-right: 1px solid #6b7280;
  border-bottom: 1px solid #6b7280;
  background-color: #111827;
  color: #f3f4f6;
}

.dark :deep(.ProseMirror table th) {
  background-color: #374151;
  color: #ffffff;
}

:deep(.ProseMirror table td p),
:deep(.ProseMirror table th p) {
  margin: 0;
}

/* Selected cell highlighting */
:deep(.ProseMirror table .selectedCell:after) {
  background: rgba(59, 130, 246, 0.15);
  content: "";
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  pointer-events: none;
  position: absolute;
  z-index: 2;
}

.dark :deep(.ProseMirror table .selectedCell:after) {
  background: rgba(147, 197, 253, 0.2);
}

/* Column resize handle */
:deep(.ProseMirror table .column-resize-handle) {
  background-color: #3b82f6;
  bottom: -2px;
  position: absolute;
  right: -2px;
  top: 0;
  width: 4px;
  pointer-events: none;
}

:deep(.ProseMirror.resize-cursor) {
  cursor: ew-resize;
  cursor: col-resize;
}

/* Table hover effects */
:deep(.ProseMirror table td:hover) {
  background-color: #f9fafb !important;
}

:deep(.ProseMirror table th:hover) {
  background-color: #d1d5db !important;
}

.dark :deep(.ProseMirror table td:hover) {
  background-color: #1f2937 !important;
}

.dark :deep(.ProseMirror table th:hover) {
  background-color: #374151 !important;
}

/* Better table spacing in academic context */
:deep(.ProseMirror table) {
  margin: 2rem 0;
  font-size: 0.9rem;
  line-height: 1.4;
}

/* Table caption support */
:deep(.ProseMirror table caption) {
  caption-side: bottom;
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: hsl(var(--muted-foreground));
  text-align: left;
  font-style: italic;
}
</style>