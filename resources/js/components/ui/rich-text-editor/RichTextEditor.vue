<script setup lang="ts">
import 'katex/dist/katex.min.css'
import { ref, watch, onMounted, onBeforeUnmount, computed, nextTick } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Placeholder from '@tiptap/extension-placeholder'
import Typography from '@tiptap/extension-typography'
import { Mathematics } from '@tiptap/extension-mathematics'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import { Highlight } from '@tiptap/extension-highlight'
import { Link } from '@tiptap/extension-link'
import { Progress } from '@/components/ui/progress'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table'
import { TableHeader } from '@tiptap/extension-table'
import { TableCell } from '@tiptap/extension-table'
// import { Gapcursor } from '@tiptap/extension-gapcursor' // Removed to avoid duplicate with StarterKit
import { Extension } from '@tiptap/core'
// import Underline from '@tiptap/extension-underline' // Commented out to avoid duplicate with StarterKit
import { Citation } from '@/tiptap-extensions/CitationExtension.js'
import { Mermaid } from '@/tiptap-extensions/MermaidExtension'
import { ResizableImage } from '@/tiptap-extensions/ImageExtension'
import axios from 'axios'
import { GhostTextExtension } from '@/tiptap-extensions/GhostTextExtension'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import TextAlign from '@tiptap/extension-text-align'
import { common, createLowlight } from 'lowlight'
// Initialize lowlight with common languages
const lowlight = createLowlight(common)
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
  Trash2,
  Loader2,
  Image as ImageIcon,
  Upload
} from 'lucide-vue-next'

interface Props {
  modelValue: string
  placeholder?: string
  readonly?: boolean
  minHeight?: string
  showToolbar?: boolean
  toolbarTeleportTarget?: string
  isGenerating?: boolean
  generationProgress?: string
  generationPercentage?: number
  generationPhase?: string
  /** Enable streaming mode for flicker-free append-only updates */
  streamingMode?: boolean
  /** Ghost text shown after cursor (Tab to accept) */
  ghostText?: string | null
  /** Ghost text format */
  ghostTextFormat?: 'text' | 'html' | null
  /** Optional explicit render position (doc pos) for ghost text */
  ghostTextPosition?: number | null
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Start writing...',
  readonly: false,
  minHeight: '200px',
  showToolbar: true,
  toolbarTeleportTarget: '',
  isGenerating: false,
  generationProgress: '',
  generationPercentage: 0,
  generationPhase: '',
  streamingMode: false,
  ghostText: null,
  ghostTextFormat: 'text',
  ghostTextPosition: null,
})

// Streaming mode state - uses throttled updates instead of append-only
const isStreamingActive = ref(false)
const lastStreamingUpdate = ref(0)
const pendingStreamingContent = ref('')
const streamingUpdateTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const STREAMING_THROTTLE_MS = 100 // Update editor at most every 100ms during streaming

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:selectedText': [value: string]
  'update:selectionRange': [value: { from: number, to: number } | null]
  'ghost-manual-trigger': []
  'ghost-accepted': [text: string]
  'ghost-dismissed': []
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

// Helper function to convert code blocks
const convertCodeBlocks = (text: string): string => {
  if (!text) return ''

  // Match ```language\n code \n``` blocks
  text = text.replace(/```(\w+)\n?([\s\S]*?)```/g, (_match, language, code) => {
    return `<pre><code class="language-${language}">${code.trim()}</code></pre>`
  })

  // Match ``` code ``` blocks without language
  text = text.replace(/```\n?([\s\S]*?)```/g, (_match, code) => {
    return `<pre><code>${code.trim()}</code></pre>`
  })

  return text
}

// Helper function to convert markdown/plain text to proper HTML
const convertTextToHTML = (text: string): string => {
  if (!text) return ''

  // If it's already HTML (contains HTML tags), return as is
  // Check for both self-closing divs <div> and divs with attributes <div ...>
  if (text.includes('<p>') || text.includes('<h1>') || text.includes('<h2>') || text.includes('<div>') || text.includes('<div ')) {
    return text
  }

  // Convert markdown/text content to HTML
  let html = text

  // Store Mermaid blocks as placeholders to protect them from block splitting
  // (Mermaid code may contain \n\n which would break the paragraph split)
  const mermaidPlaceholders: string[] = []
  html = html.replace(/```mermaid\n?([\s\S]*?)```/g, (_match, code) => {
    const trimmedCode = code.trim()
    const escapedCode = trimmedCode
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
    const mermaidHtml = `<div data-mermaid data-mermaid-code="${escapedCode}" data-view-mode="diagram"><pre><code class="language-mermaid">${trimmedCode}</code></pre></div>`
    const placeholderIndex = mermaidPlaceholders.length
    mermaidPlaceholders.push(mermaidHtml)
    return `__MERMAID_PLACEHOLDER_${placeholderIndex}__`
  })

  // Also handle malformed mermaid blocks (two backticks)
  html = html.replace(/``mermaid\n?([\s\S]*?)``/g, (_match, code) => {
    const trimmedCode = code.trim()
    const escapedCode = trimmedCode
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
    const mermaidHtml = `<div data-mermaid data-mermaid-code="${escapedCode}" data-view-mode="diagram"><pre><code class="language-mermaid">${trimmedCode}</code></pre></div>`
    const placeholderIndex = mermaidPlaceholders.length
    mermaidPlaceholders.push(mermaidHtml)
    return `__MERMAID_PLACEHOLDER_${placeholderIndex}__`
  })

  // Handle inline mermaid text wrapped in single backticks (less common)
  html = html.replace(/`mermaid\n([\s\S]*?)`/g, (_match, code) => {
    const trimmedCode = code.trim()
    const escapedCode = trimmedCode
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
    const mermaidHtml = `<div data-mermaid data-mermaid-code="${escapedCode}" data-view-mode="diagram"><pre><code class="language-mermaid">${trimmedCode}</code></pre></div>`
    const placeholderIndex = mermaidPlaceholders.length
    mermaidPlaceholders.push(mermaidHtml)
    return `__MERMAID_PLACEHOLDER_${placeholderIndex}__`
  })

  // Process code blocks (but not mermaid which is already handled)
  html = convertCodeBlocks(html)

  // Convert headings with proper hierarchy
  html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>')  // H3 first (most specific)
  html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>')   // H2 second
  html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>')    // H1 last (least specific)

  // Convert bold text
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // Convert italic text
  html = html.replace(/\*(.*?)\*/g, '<em>$1</em>')

  // Convert inline code (but not if already in code blocks)
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')

  // Convert Markdown tables to HTML tables
  html = convertMarkdownTablesToHTML(html)

  // Split into blocks (paragraphs/sections)
  const blocks = html.split('\n\n').filter(block => block.trim())

  let result = blocks.map(block => {
    const trimmed = block.trim()

    // If it's already a heading, return as is
    if (trimmed.startsWith('<h1>') || trimmed.startsWith('<h2>') || trimmed.startsWith('<h3>')) {
      return trimmed
    }

    // If it's already an HTML block element (div, table, pre, etc.), return as is
    // This preserves Mermaid diagrams, tables, and code blocks
    if (trimmed.startsWith('<div') || trimmed.startsWith('<table') || trimmed.startsWith('<pre') ||
      trimmed.startsWith('<ul') || trimmed.startsWith('<ol') || trimmed.startsWith('<blockquote') ||
      trimmed.startsWith('__MERMAID_PLACEHOLDER_')) {
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

  // Restore Mermaid placeholders with actual HTML
  mermaidPlaceholders.forEach((mermaidHtml, index) => {
    result = result.replace(`__MERMAID_PLACEHOLDER_${index}__`, mermaidHtml)
  })

  return result
}

// Base extensions (deduped by name before initializing editor)
const baseExtensions = [
  StarterKit.configure({
    heading: {
      levels: [1, 2, 3]
    },
    codeBlock: false, // Disable default code block to use our custom one
    link: false, // Disable built-in Link to avoid duplicate with custom Link below
  }),
  CodeBlockLowlight.configure({
    lowlight,
    HTMLAttributes: {
      class: 'code-block-highlighted',
    },
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
  // Gapcursor, // Removed duplicate
  Table.configure({
    resizable: true,
  }),
  TableRow,
  TableHeader,
  TableCell,
  // Underline, // Commented out - StarterKit might include this
  Mathematics.configure({
    inlineOptions: {
      onClick: (node, pos) => {
        const currentEditor = editor.value
        if (!currentEditor) return
        const nextLatex = window.prompt('Edit inline math (LaTeX):', node.attrs.latex)
        if (!nextLatex) return
        currentEditor.chain().setNodeSelection(pos).updateInlineMath({ latex: nextLatex }).focus().run()
      },
    },
    blockOptions: {
      onClick: (node, pos) => {
        const currentEditor = editor.value
        if (!currentEditor) return
        const nextLatex = window.prompt('Edit block math (LaTeX):', node.attrs.latex)
        if (!nextLatex) return
        currentEditor.chain().setNodeSelection(pos).updateBlockMath({ latex: nextLatex }).focus().run()
      },
    },
    katexOptions: {
      throwOnError: false,
    },
  }),
  Citation,
  Mermaid,
  ResizableImage.configure({
    maxWidth: 800,
  }),
  TextAlign.configure({
    types: ['heading', 'paragraph'],
  }),
  GhostTextExtension.configure({
    onManualTrigger: () => emit('ghost-manual-trigger'),
    onAccepted: (text: string) => emit('ghost-accepted', text),
    onDismissed: () => emit('ghost-dismissed'),
  }),
]

// Filter out any duplicate extension names to avoid tiptap warnings
const extensions = baseExtensions.filter((ext, index, arr) =>
  index === arr.findIndex(e => e.name === ext.name)
)

// Initialize Tiptap editor
const editor = useEditor({
  content: convertTextToHTML(props.modelValue),
  editable: !props.readonly,
  extensions,
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
      if (!editor.state.doc) return
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

// Watch for external changes - handles both normal and streaming mode
watch(() => props.modelValue, (newValue) => {
  if (!editor.value) return

  // In streaming mode, use throttled updates to prevent browser freeze
  if (props.streamingMode && isStreamingActive.value) {
    throttledStreamingUpdate(newValue)
    return
  }

  // Normal mode: full content replacement
  if (editor.value.getHTML() !== newValue) {
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

watch(
  [() => props.ghostText, () => props.ghostTextFormat, () => props.ghostTextPosition],
  ([text, format, position]) => {
    if (!editor.value) return
    const cleaned = (text ?? '').toString()
    if (!cleaned) {
      editor.value.commands.clearGhostText?.()
      return
    }
    editor.value.commands.setGhostText?.({
      text: cleaned,
      format: (format ?? 'text') as any,
      position: typeof position === 'number' ? position : undefined,
    })
  },
  { immediate: true },
)

// Watch streamingMode prop to initialize/cleanup streaming state
watch(() => props.streamingMode, (isStreaming) => {
  if (isStreaming) {
    startStreamingMode()
  } else {
    endStreamingMode()
  }
})

/**
 * Start streaming mode - prepares editor for throttled updates
 */
function startStreamingMode(): void {
  isStreamingActive.value = true
  lastStreamingUpdate.value = 0
  pendingStreamingContent.value = ''

  console.log('RichTextEditor: Streaming mode started (throttled)')
}

/**
 * End streaming mode - finalizes content and cleans up
 */
function endStreamingMode(): void {
  // Clear any pending timer
  if (streamingUpdateTimer.value) {
    clearTimeout(streamingUpdateTimer.value)
    streamingUpdateTimer.value = null
  }

  // Apply any pending content
  if (pendingStreamingContent.value && editor.value) {
    applyStreamingContent(pendingStreamingContent.value)
  }

  isStreamingActive.value = false
  pendingStreamingContent.value = ''
  lastStreamingUpdate.value = 0

  console.log('RichTextEditor: Streaming mode ended')
}

/**
 * Throttled streaming update - limits DOM operations to prevent browser freeze
 */
function throttledStreamingUpdate(content: string): void {
  if (!editor.value || !isStreamingActive.value) return

  pendingStreamingContent.value = content
  const now = Date.now()
  const timeSinceLastUpdate = now - lastStreamingUpdate.value

  // If enough time has passed, update immediately
  if (timeSinceLastUpdate >= STREAMING_THROTTLE_MS) {
    applyStreamingContent(content)
    return
  }

  // Otherwise, schedule an update if not already scheduled
  if (!streamingUpdateTimer.value) {
    const delay = STREAMING_THROTTLE_MS - timeSinceLastUpdate
    streamingUpdateTimer.value = setTimeout(() => {
      streamingUpdateTimer.value = null
      if (isStreamingActive.value && pendingStreamingContent.value) {
        applyStreamingContent(pendingStreamingContent.value)
      }
    }, delay)
  }
}

/**
 * Apply streaming content to editor - simple full replacement
 */
function applyStreamingContent(content: string): void {
  if (!editor.value) return

  lastStreamingUpdate.value = Date.now()

  // Simple approach: just set the content if it's different
  // This is much cheaper than insertContentAt operations
  const processedContent = convertTextToHTML(content)
  const currentHtml = editor.value.getHTML()

  if (currentHtml !== processedContent) {
    // Use setContent without emitting to avoid feedback loops
    editor.value.commands.setContent(processedContent, { emitUpdate: false })
  }
}

/**
 * Force a full content refresh (use sparingly, e.g., on stream complete)
 */
function refreshContent(): void {
  if (!editor.value) return

  const currentContent = pendingStreamingContent.value || props.modelValue
  const processedContent = convertTextToHTML(currentContent)
  editor.value.commands.setContent(processedContent, { emitUpdate: false })
}

/**
 * Get current streaming position info
 */
function getStreamingInfo(): { length: number; wordCount: number } {
  const content = pendingStreamingContent.value || props.modelValue
  return {
    length: content.length,
    wordCount: content.split(/\s+/).filter((w: string) => w.length > 0).length
  }
}

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

  // Add image event listeners
  document.addEventListener('tiptap-image-drop', handleImageDrop as EventListener)
  document.addEventListener('tiptap-image-paste', handleImagePaste as EventListener)
})

// Cleanup
onBeforeUnmount(() => {
  // Clear streaming timer
  if (streamingUpdateTimer.value) {
    clearTimeout(streamingUpdateTimer.value)
    streamingUpdateTimer.value = null
  }

  if (editor.value) {
    editor.value.destroy()
  }

  // Remove image event listeners
  document.removeEventListener('tiptap-image-drop', handleImageDrop as EventListener)
  document.removeEventListener('tiptap-image-paste', handleImagePaste as EventListener)

  stopTeleportObserver()
})

// Toolbar actions
const toggleBold = () => editor.value?.chain().focus().toggleBold().run()
const toggleItalic = () => editor.value?.chain().focus().toggleItalic().run()
const toggleStrike = () => editor.value?.chain().focus().toggleStrike().run()
const toggleCode = () => editor.value?.chain().focus().toggleCode().run()

const insertInlineMath = () => {
  const currentEditor = editor.value
  if (!currentEditor) return
  if (!currentEditor.state.selection.empty) {
    currentEditor.chain().focus().setInlineMath().run()
    return
  }
  const latex = window.prompt('Enter inline math (LaTeX):', '')
  if (!latex) return
  currentEditor.chain().focus().insertInlineMath({ latex }).run()
}

const insertBlockMath = () => {
  const currentEditor = editor.value
  if (!currentEditor) return
  if (!currentEditor.state.selection.empty) {
    currentEditor.chain().focus().setBlockMath().run()
    return
  }
  const latex = window.prompt('Enter block math (LaTeX):', '')
  if (!latex) return
  currentEditor.chain().focus().insertBlockMath({ latex }).run()
}
const insertMermaidDiagram = () => {
  editor.value?.chain().focus().insertMermaid().run()
}

const teleportTargetExists = ref(false)
let teleportObserver: MutationObserver | null = null

const stopTeleportObserver = () => {
  if (teleportObserver) {
    teleportObserver.disconnect()
    teleportObserver = null
  }
}

const refreshTeleportTarget = () => {
  if (typeof document === 'undefined') {
    teleportTargetExists.value = false
    return
  }

  teleportTargetExists.value = !!(props.toolbarTeleportTarget && document.querySelector(props.toolbarTeleportTarget))
}

if (typeof document !== 'undefined') {
  refreshTeleportTarget()
}

const teleportDisabled = computed(() => !props.toolbarTeleportTarget || !teleportTargetExists.value)
// Backwards-compatible alias (older HMR builds referenced this name).
const teleportEnabled = computed(() => !teleportDisabled.value)
const teleportTo = computed(() => (teleportDisabled.value ? 'body' : (props.toolbarTeleportTarget || 'body')))

const toolbarWrapperClass = computed(() => {
  const base =
    'z-20 flex items-center gap-1 border-border/40 bg-background/95 p-1.5 shadow-sm backdrop-blur-md transition-all duration-200 supports-[backdrop-filter]:bg-background/80 overflow-x-auto no-scrollbar mask-gradient-right'

  if (!teleportDisabled.value) {
    return `w-full ${base} rounded-lg border`
  }

  return `sticky top-0 mx-0 sm:mx-2 md:mx-4 mt-0 sm:mt-1 md:mt-2 mb-2 md:mb-4 rounded-none sm:rounded-lg md:rounded-xl border-y sm:border ${base}`
})

watch(
  () => props.toolbarTeleportTarget,
  async () => {
    await nextTick()
    refreshTeleportTarget()
  },
  { flush: 'post' }
)

onMounted(async () => {
  await nextTick()
  refreshTeleportTarget()

  if (
    !teleportTargetExists.value &&
    props.toolbarTeleportTarget &&
    typeof MutationObserver !== 'undefined' &&
    typeof document !== 'undefined' &&
    document.body
  ) {
    stopTeleportObserver()
    teleportObserver = new MutationObserver(() => {
      refreshTeleportTarget()
      if (teleportTargetExists.value) {
        stopTeleportObserver()
      }
    })

    teleportObserver.observe(document.body, { childList: true, subtree: true })
  }
})
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

// Image dialog state
const imageDialogOpen = ref(false)
const imageUrl = ref('')
const imageUploading = ref(false)
const imageUploadProgress = ref(0)
const imageUploadError = ref('')
const imageFileInput = ref<HTMLInputElement | null>(null)

// Image functionality
const openImageDialog = () => {
  imageUrl.value = ''
  imageUploadError.value = ''
  imageDialogOpen.value = true
}

const insertImageFromUrl = () => {
  if (!imageUrl.value) return

  editor.value?.chain().focus().setImage({ src: imageUrl.value }).run()
  imageDialogOpen.value = false
}

const handleImageFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    uploadImage(file)
  }
}

const uploadImage = async (file: File) => {
  if (!file.type.startsWith('image/')) {
    imageUploadError.value = 'Please select an image file'
    return
  }

  imageUploading.value = true
  imageUploadError.value = ''
  imageUploadProgress.value = 0

  try {
    const formData = new FormData()
    formData.append('image', file)

    const response = await axios.post('/editor/images', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          imageUploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total)
        }
      },
    })

    if (response.data.success) {
      editor.value?.chain().focus().setImage({
        src: response.data.url,
        width: response.data.width,
        height: response.data.height,
      }).run()
      imageDialogOpen.value = false
    }
  } catch (error: any) {
    imageUploadError.value = error.response?.data?.message || 'Upload failed'
  } finally {
    imageUploading.value = false
    imageUploadProgress.value = 0
    // Reset file input
    if (imageFileInput.value) {
      imageFileInput.value.value = ''
    }
  }
}

// Handle image drop/paste events from the extension
const handleImageDrop = (event: CustomEvent) => {
  const { file } = event.detail
  uploadImage(file)
}

const handleImagePaste = (event: CustomEvent) => {
  const { file } = event.detail
  uploadImage(file)
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
// Text alignment functionality
const setTextAlign = (alignment: 'left' | 'center' | 'right' | 'justify') => {
  editor.value?.chain().focus().setTextAlign(alignment).run()
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
  // Selection methods
  getSelectionRange,
  getSelectedText,
  replaceSelection,
  replaceTextAt,
  getCursorPosition,
  setCursorPosition,

  // Basic editor methods
  focus: () => editor.value?.commands.focus(),
  blur: () => editor.value?.commands.blur(),
  getHTML: () => editor.value?.getHTML() || '',
  getText: () => editor.value?.getText() || '',
  wordCount,

  // Streaming methods for throttled updates
  startStreamingMode,
  endStreamingMode,
  refreshContent,
  getStreamingInfo,
  isStreamingActive: () => isStreamingActive.value,

  // Editor instance access (use carefully)
  editor: () => editor.value,
})
</script>

<template>
  <div class="relative flex flex-col w-full h-full group bg-background">
    <!-- Floating Toolbar - Responsive & Scrollable -->
    <Teleport v-if="showToolbar && !readonly" :to="teleportTo" :disabled="teleportDisabled">
      <div :class="toolbarWrapperClass">

        <!-- History Controls -->
        <div class="flex items-center gap-0.5 border-r border-border/40 pr-1.5 mr-1.5 flex-shrink-0">
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg hover:bg-muted/80 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
            @click="undo" :disabled="!editor?.can().undo()">
            <Undo class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg hover:bg-muted/80 text-zinc-700 dark:text-zinc-300 hover:text-foreground"
            @click="redo" :disabled="!editor?.can().redo()">
            <Redo class="h-4 w-4" />
          </Button>
        </div>

        <!-- Text Style -->
        <div class="flex items-center gap-0.5 border-r border-border/40 pr-1.5 mr-1.5 flex-shrink-0">
          <!-- Heading Levels -->
          <!-- Heading Levels -->
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="sm"
                class="h-8 gap-1 px-2 font-medium text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80">
                <span class="text-xs text-zinc-700 dark:text-zinc-100">
                  {{ editor?.isActive('heading', { level: 1 }) ? 'H1' :
                    editor?.isActive('heading', { level: 2 }) ? 'H2' :
                      editor?.isActive('heading', { level: 3 }) ? 'H3' : 'Paragraph' }}
                </span>
                <Type class="h-3.5 w-3.5 opacity-70" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              <DropdownMenuItem @click="setParagraph" :class="{ 'bg-accent': editor?.isActive('paragraph') }">
                <span class="text-sm">Paragraph</span>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="toggleHeading1"
                :class="{ 'bg-accent': editor?.isActive('heading', { level: 1 }) }">
                <span class="text-lg font-bold">Heading 1</span>
              </DropdownMenuItem>
              <DropdownMenuItem @click="toggleHeading2"
                :class="{ 'bg-accent': editor?.isActive('heading', { level: 2 }) }">
                <span class="text-base font-bold">Heading 2</span>
              </DropdownMenuItem>
              <DropdownMenuItem @click="toggleHeading3"
                :class="{ 'bg-accent': editor?.isActive('heading', { level: 3 }) }">
                <span class="text-sm font-bold">Heading 3</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <!-- Font Size Controls -->
          <div class="flex items-center gap-0.5">
            <Button variant="ghost" size="icon"
              class="h-8 w-6 rounded-l-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
              @click="decreaseFontSize">
              <Minus class="h-3 w-3" />
            </Button>
            <div
              class="flex h-8 w-9 items-center justify-center border-y border-border/20 bg-muted/20 text-xs font-medium text-foreground">
              {{ fontSize.replace('pt', '') }}
            </div>
            <Button variant="ghost" size="icon"
              class="h-8 w-6 rounded-r-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
              @click="increaseFontSize">
              <Plus class="h-3 w-3" />
            </Button>
          </div>
        </div>

        <!-- Basic Formatting -->
        <div class="flex items-center gap-0.5 border-r border-border/40 pr-1.5 mr-1.5 flex-shrink-0">
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('bold') }" @click="toggleBold">
            <Bold class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('italic') }" @click="toggleItalic">
            <Italic class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('underline') }" @click="toggleUnderline">
            <UnderlineIcon class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('strike') }" @click="toggleStrike">
            <Strikethrough class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('code') }" @click="toggleCode">
            <Code class="h-4 w-4" />
          </Button>
        </div>

        <!-- Lists & Alignment -->
        <div class="flex items-center gap-0.5 border-r border-border/40 pr-1.5 mr-1.5 flex-shrink-0">
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('bulletList') }" @click="toggleBulletList">
            <List class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('orderedList') }" @click="toggleOrderedList">
            <ListOrdered class="h-4 w-4" />
          </Button>
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('blockquote') }" @click="toggleBlockquote">
            <Quote class="h-4 w-4" />
          </Button>

          <!-- Text Align Group -->
          <div class="flex items-center gap-0.5 border-l border-border/20 ml-0.5 pl-0.5">
            <Button variant="ghost" size="icon"
              class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
              :class="{ 'bg-primary/10 text-primary': editor?.isActive({ textAlign: 'left' }) }"
              @click="setTextAlign('left')">
              <AlignLeft class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon"
              class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
              :class="{ 'bg-primary/10 text-primary': editor?.isActive({ textAlign: 'center' }) }"
              @click="setTextAlign('center')">
              <AlignCenter class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon"
              class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
              :class="{ 'bg-primary/10 text-primary': editor?.isActive({ textAlign: 'right' }) }"
              @click="setTextAlign('right')">
              <AlignRight class="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon"
              class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
              :class="{ 'bg-primary/10 text-primary': editor?.isActive({ textAlign: 'justify' }) }"
              @click="setTextAlign('justify')">
              <AlignJustify class="h-4 w-4" />
            </Button>
          </div>
        </div>

        <!-- Insert & Extras -->
        <div class="flex items-center gap-0.5 flex-shrink-0">
          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            title="Insert Mermaid diagram (Ctrl/Cmd+Alt+M)" @click="insertMermaidDiagram">
            <span class="text-[11px] font-semibold">M</span>
          </Button>

          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            title="Insert inline math" @click="insertInlineMath">
            <span class="text-[11px] font-semibold">fx</span>
          </Button>

          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            title="Insert block math" @click="insertBlockMath">
            <span class="text-[11px] font-semibold">FX</span>
          </Button>

          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('link') }" @click="openLinkDialog">
            <LinkIcon class="h-4 w-4" />
          </Button>

          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            title="Insert image" @click="openImageDialog">
            <ImageIcon class="h-4 w-4" />
          </Button>

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button variant="ghost" size="icon"
                class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
                :class="{ 'bg-primary/10 text-primary': editor?.isActive('table') }">
                <TableIcon class="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuItem @click="insertTable">Insert Table (3x3)</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="addColumnBefore" :disabled="!isInTable()">Add Column Before</DropdownMenuItem>
              <DropdownMenuItem @click="addColumnAfter" :disabled="!isInTable()">Add Column After</DropdownMenuItem>
              <DropdownMenuItem @click="deleteColumn" :disabled="!isInTable()">Delete Column</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="addRowBefore" :disabled="!isInTable()">Add Row Before</DropdownMenuItem>
              <DropdownMenuItem @click="addRowAfter" :disabled="!isInTable()">Add Row After</DropdownMenuItem>
              <DropdownMenuItem @click="deleteRow" :disabled="!isInTable()">Delete Row</DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="deleteTable" :disabled="!isInTable()" class="text-destructive">Delete Table
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>

          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.isActive('highlight') }" @click="toggleHighlight">
            <Highlighter class="h-4 w-4" />
          </Button>

          <Button variant="ghost" size="icon"
            class="h-8 w-8 rounded-lg text-zinc-700 dark:text-zinc-300 hover:text-foreground hover:bg-muted/80"
            :class="{ 'bg-primary/10 text-primary': editor?.getAttributes('textStyle').color }"
            @click="openColorDialog">
            <Palette class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </Teleport>

    <!-- Editor Content -->
    <div class="relative flex-1 min-h-0">
      <EditorContent :editor="editor" class="h-full w-full outline-none" />

      <!-- AI Generation Dynamic Island -->
      <Transition
        enter-active-class="transition-all duration-300 cubic-bezier(0.34, 1.56, 0.64, 1)"
        enter-from-class="opacity-0 -translate-y-4 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 cubic-bezier(0.4, 0, 0.2, 1)"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-4 scale-95"
      >
        <div v-if="isGenerating" 
             class="absolute top-4 left-1/2 -translate-x-1/2 z-20 pointer-events-none">
          <div class="flex items-center gap-3 pl-3 pr-4 py-2 bg-indigo-950/90 text-white dark:bg-indigo-500/10 dark:text-indigo-100 dark:border-indigo-400/20 backdrop-blur-md border border-indigo-200/20 rounded-full shadow-[0_8px_32px_rgba(79,70,229,0.25)] pointer-events-auto ring-1 ring-white/10">
            
            <!-- Animated Icon -->
            <div class="relative flex items-center justify-center w-5 h-5">
              <div class="absolute inset-0 rounded-full bg-indigo-400/30 animate-ping"></div>
              <Sparkles class="w-4 h-4 text-indigo-300 animate-pulse relative z-10" />
            </div>

            <div class="flex flex-col gap-0.5 min-w-[140px]">
              <div class="flex items-center justify-between gap-4">
                <span class="text-[11px] font-semibold tracking-wide uppercase text-indigo-100/90 dark:text-indigo-200">
                  {{ generationPhase || 'Writing...' }}
                </span>
                <span class="text-[10px] font-mono tabular-nums opacity-80">
                  {{ generationPercentage }}%
                </span>
              </div>
              
              <!-- Mini Progress Bar -->
              <div class="h-1 w-full bg-indigo-900/50 dark:bg-indigo-950/50 rounded-full overflow-hidden">
                <div class="h-full bg-gradient-to-r from-indigo-400 to-purple-400 transition-all duration-300 ease-out"
                     :style="{ width: `${generationPercentage}%` }">
                   <div class="absolute inset-0 bg-white/20 animate-[shimmer_2s_infinite]"></div>
                </div>
              </div>
            </div>

            <!-- Vertical Separator -->
            <div class="w-px h-6 bg-white/10 mx-1"></div>

            <!-- Word Count Badge -->
            <div class="flex flex-col items-end">
               <span class="text-[10px] font-medium text-indigo-200/80">
                 Word Count
               </span>
               <span class="text-xs font-bold font-mono tracking-tight leading-none text-white dark:text-indigo-50">
                 {{ generationProgress?.match(/\d+/)?.[0] || '...' }}
               </span>
            </div>
            
          </div>
        </div>
      </Transition>
    </div>

    <!-- Dialogs -->
    <Dialog :open="linkDialogOpen" @update:open="linkDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Insert Link</DialogTitle>
          <DialogDescription>Enter the URL for the link.</DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label htmlFor="link-text">Text</Label>
            <Input id="link-text" v-model="linkForm.text" placeholder="Link text" />
          </div>
          <div class="grid gap-2">
            <Label htmlFor="link-url">URL</Label>
            <Input id="link-url" v-model="linkForm.url" placeholder="https://example.com" />
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="linkDialogOpen = false">Cancel</Button>
          <Button @click="applyLink">Insert</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog :open="colorDialogOpen" @update:open="colorDialogOpen = $event">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Text Color</DialogTitle>
          <DialogDescription>Choose a color for the selected text.</DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="flex items-center gap-4">
            <Input type="color" v-model="colorForm.color" class="h-12 w-12 p-1" />
            <Input v-model="colorForm.color" placeholder="#000000" class="flex-1" />
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="color in ['#000000', '#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#a855f7', '#ec4899']"
              :key="color"
              class="h-8 w-8 rounded-full border border-border shadow-sm transition-transform hover:scale-110 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
              :style="{ backgroundColor: color }" @click="colorForm.color = color"></button>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="removeTextColor">Reset</Button>
          <Button @click="applyColor">Apply</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Image Dialog -->
    <Dialog :open="imageDialogOpen" @update:open="imageDialogOpen = $event">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Insert Image</DialogTitle>
          <DialogDescription>Upload an image or enter a URL.</DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <!-- File Upload -->
          <div class="grid gap-2">
            <Label>Upload from computer</Label>
            <div class="flex items-center gap-2">
              <input ref="imageFileInput" type="file" accept="image/*" class="hidden" @change="handleImageFileSelect" />
              <Button variant="outline" class="w-full" :disabled="imageUploading" @click="imageFileInput?.click()">
                <Upload class="w-4 h-4 mr-2" />
                {{ imageUploading ? 'Uploading...' : 'Choose File' }}
              </Button>
            </div>
            <Progress v-if="imageUploading" :model-value="imageUploadProgress" class="h-2" />
            <p v-if="imageUploadError" class="text-sm text-destructive">{{ imageUploadError }}</p>
          </div>

          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <span class="w-full border-t" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
              <span class="bg-background px-2 text-muted-foreground">Or</span>
            </div>
          </div>

          <!-- URL Input -->
          <div class="grid gap-2">
            <Label for="image-url">Image URL</Label>
            <div class="flex gap-2">
              <Input id="image-url" v-model="imageUrl" placeholder="https://example.com/image.jpg"
                @keydown.enter="insertImageFromUrl" />
              <Button @click="insertImageFromUrl" :disabled="!imageUrl">
                Insert
              </Button>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="imageDialogOpen = false">Cancel</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<style scoped>
@reference "../../../../css/app.css";

:deep(.ProseMirror) {
  @apply min-h-full outline-none;
  background-color: var(--background);
  color: var(--foreground);

  /* Academic Typography Improvements */
  p {
    @apply mb-4 leading-relaxed text-foreground/90;
    text-align: justify;
    hyphens: auto;
  }

  h1,
  h2,
  h3,
  h4 {
    @apply font-bold tracking-tight text-foreground mt-8 mb-4;
  }

  h1 {
    @apply text-3xl border-b pb-2;
  }

  h2 {
    @apply text-2xl;
  }

  h3 {
    @apply text-xl;
  }

  ul,
  ol {
    @apply pl-6 mb-4 space-y-1;
  }

  ul {
    @apply list-disc;
  }

  ol {
    @apply list-decimal;
  }

  blockquote {
    @apply border-l-4 border-primary/30 pl-4 italic text-muted-foreground my-6 bg-muted/10 py-2 pr-2 rounded-r-lg;
  }

  code {
    @apply bg-muted px-1.5 py-0.5 rounded text-sm font-mono text-primary;
  }

  pre {
    @apply bg-muted/50 p-4 rounded-lg overflow-x-auto my-6 border border-border/50;

    code {
      @apply bg-transparent p-0 text-foreground;
    }
  }

  /* Table Styles */
  table {
    @apply w-full border-collapse my-6 text-sm;

    th,
    td {
      @apply border border-border p-2 relative;
    }

    th {
      @apply bg-muted/30 font-bold text-left;
    }

    .selectedCell:after {
      @apply absolute inset-0 bg-primary/10 pointer-events-none content-[''];
    }
  }

  /* Link Styles */
  a {
    @apply text-primary underline decoration-primary/30 underline-offset-4 transition-colors hover:text-primary/80 hover:decoration-primary;
    cursor: pointer;
  }

  /* Selection Color */
  ::selection {
    @apply bg-primary/20 text-foreground;
  }
}

/* Dark mode specific adjustments - follow the app's `html.dark` root class */
:global(html.dark) :deep(.ProseMirror) {
  blockquote {
    @apply border-primary/50 bg-primary/5;
  }
}

:deep(.tiptap-ghost-text) {
  opacity: 0.45;
  color: currentColor;
  pointer-events: none;
  user-select: none;
  white-space: pre-wrap;
}

:deep(.tiptap-mathematics-render) {
  @apply rounded-md bg-muted/30 px-1 py-0.5;
}

:deep(.tiptap-mathematics-render--editable) {
  @apply cursor-pointer transition-colors;
}

:deep(.tiptap-mathematics-render--editable:hover) {
  @apply bg-muted/60;
}

:deep(.tiptap-mathematics-render[data-type='block-math']) {
  @apply block my-4 px-4 py-3 text-center;
}
</style>
