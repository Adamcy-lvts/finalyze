<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Typography from '@tiptap/extension-typography'
import { FontSize, TextStyle } from '@tiptap/extension-text-style'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table'
import { TableHeader } from '@tiptap/extension-table'
import { TableCell } from '@tiptap/extension-table'
import { watch, onBeforeUnmount, ref, onMounted, nextTick } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import mermaid from 'mermaid'
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight'
import { common, createLowlight } from 'lowlight'

// Initialize lowlight with common languages
const lowlight = createLowlight(common)

// Initialize Mermaid
mermaid.initialize({
  startOnLoad: false,
  theme: 'default',
  securityLevel: 'loose',
  fontFamily: 'Arial, sans-serif',
})

interface Props {
  content: string
  showFontControls?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showFontControls: true
})

// Helper function to render Mermaid diagrams
const renderMermaidDiagrams = async (element: HTMLElement) => {
  const mermaidBlocks = element.querySelectorAll('pre code.language-mermaid, .mermaid-diagram')

  for (let i = 0; i < mermaidBlocks.length; i++) {
    const block = mermaidBlocks[i] as HTMLElement
    const code = block.textContent || ''

    try {
      const { svg } = await mermaid.render(`mermaid-${Date.now()}-${i}`, code)
      const container = document.createElement('div')
      container.className = 'mermaid-container'
      container.innerHTML = svg
      block.parentElement?.replaceWith(container)
    } catch (error) {
      console.error('Mermaid rendering error:', error)
      block.parentElement?.classList.add('mermaid-error')
    }
  }
}

// Helper function to convert Mermaid markdown code blocks to HTML
const convertMermaidBlocks = (text: string): string => {
  if (!text) return ''

  // Match ```mermaid ... ``` blocks (with proper backticks)
  text = text.replace(/```mermaid\n?([\s\S]*?)```/g, (match, code) => {
    return `<pre><code class="language-mermaid">${code.trim()}</code></pre>`
  })

  // Match malformed mermaid blocks like ``mermaid ... `` (two backticks)
  text = text.replace(/``mermaid\n?([\s\S]*?)``/g, (match, code) => {
    return `<pre><code class="language-mermaid">${code.trim()}</code></pre>`
  })

  // Match inline mermaid text wrapped in backticks
  text = text.replace(/`mermaid\n?([\s\S]*?)`/g, (match, code) => {
    return `<pre><code class="language-mermaid">${code.trim()}</code></pre>`
  })

  return text
}

// Helper function to convert code blocks
const convertCodeBlocks = (text: string): string => {
  if (!text) return ''

  // Match ```language\n code \n``` blocks
  text = text.replace(/```(\w+)\n?([\s\S]*?)```/g, (match, language, code) => {
    return `<pre><code class="language-${language}">${code.trim()}</code></pre>`
  })

  // Match ``` code ``` blocks without language
  text = text.replace(/```\n?([\s\S]*?)```/g, (match, code) => {
    return `<pre><code>${code.trim()}</code></pre>`
  })

  return text
}

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

  // If it's already HTML (contains HTML tags), process it for mermaid and code blocks
  const isHTML = text.includes('<p>') || text.includes('<h1>') || text.includes('<h2>') || text.includes('<div>')

  if (isHTML) {
    // Process Mermaid and code blocks even in HTML content
    let html = convertMermaidBlocks(text)
    html = convertCodeBlocks(html)
    return html
  }

  // Convert markdown/text content to HTML
  let html = text

  // Process Mermaid diagrams FIRST (before other conversions)
  html = convertMermaidBlocks(html)

  // Process code blocks
  html = convertCodeBlocks(html)

  // Convert headings with proper hierarchy - handle #### first
  html = html.replace(/^#### (.*$)/gim, '<h4>$1</h4>')  // H4 first (most specific)
  html = html.replace(/^### (.*$)/gim, '<h3>$1</h3>')   // H3 second
  html = html.replace(/^## (.*$)/gim, '<h2>$1</h2>')    // H2 third
  html = html.replace(/^# (.*$)/gim, '<h1>$1</h1>')     // H1 last (least specific)

  // Convert bold text (handle ** before *)
  html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')

  // Convert italic text (handle remaining * after **)
  html = html.replace(/(?<!\*)\*([^*\n]+?)\*(?!\*)/g, '<em>$1</em>')

  // Convert inline code (but not if already in code blocks)
  html = html.replace(/`([^`]+)`/g, '<code>$1</code>')

  // Convert Markdown tables to HTML tables
  html = convertMarkdownTablesToHTML(html)

  // Split into blocks, but preserve empty lines for better spacing
  const blocks = html.split(/\n\s*\n/).filter(block => block.trim())

  return blocks.map(block => {
    const trimmed = block.trim()

    // If it's already a heading, return as is
    if (trimmed.startsWith('<h1>') || trimmed.startsWith('<h2>') || trimmed.startsWith('<h3>') || trimmed.startsWith('<h4>')) {
      return trimmed
    }

    // Handle numbered/bulleted lists with better parsing
    if (trimmed.includes('\n')) {
      const lines = trimmed.split('\n').map(line => line.trim()).filter(line => line)

      // Check if this looks like a list
      const isNumberedList = lines.some(line => line.match(/^\d+\.\s/))
      const isBulletList = lines.some(line => line.match(/^[-*]\s/))

      if (isNumberedList || isBulletList) {
        const listItems = lines.map(line => {
          if (line.match(/^\d+\.\s/)) {
            return `<li>${line.replace(/^\d+\.\s/, '')}</li>`
          } else if (line.match(/^[-*]\s/)) {
            return `<li>${line.replace(/^[-*]\s/, '')}</li>`
          }
          // Handle continuation lines
          return line
        })

        // Group list items properly
        let processedItems = []
        let currentItem = ''

        for (const item of listItems) {
          if (item.startsWith('<li>')) {
            if (currentItem) {
              processedItems.push(currentItem)
            }
            currentItem = item
          } else if (currentItem) {
            // Continuation of previous item
            currentItem = currentItem.replace('</li>', ` ${item}</li>`)
          }
        }

        if (currentItem) {
          processedItems.push(currentItem)
        }

        if (isNumberedList) {
          return `<ol>${processedItems.join('')}</ol>`
        } else {
          return `<ul>${processedItems.join('')}</ul>`
        }
      }
    }

    // Regular paragraphs with better line handling
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
      },
      codeBlock: false, // Disable default code block to use our custom one
    }),
    CodeBlockLowlight.configure({
      lowlight,
      HTMLAttributes: {
        class: 'code-block-highlighted',
      },
    }),
    Typography,
    TextStyle,
    FontSize,
    Table.configure({
      resizable: true,
    }),
    TableRow,
    TableHeader,
    TableCell
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

// Initialize font size and render Mermaid diagrams on mount
onMounted(() => {
  setTimeout(async () => {
    if (editor.value) {
      // Set default font size for all content
      editor.value.chain().selectAll().setFontSize('12pt').run()

      // Render Mermaid diagrams
      await nextTick()
      const editorElement = document.querySelector('.ProseMirror')
      if (editorElement) {
        await renderMermaidDiagrams(editorElement as HTMLElement)
      }
    }
  }, 100)
})

// Watch for content changes
watch(() => props.content, async (newContent) => {
  if (editor.value && editor.value.getHTML() !== newContent) {
    const processedContent = convertTextToHTML(newContent)
    editor.value.commands.setContent(processedContent)

    // Render Mermaid diagrams after content update
    await nextTick()
    const editorElement = document.querySelector('.ProseMirror')
    if (editorElement) {
      await renderMermaidDiagrams(editorElement as HTMLElement)
    }
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
  <div>
    <!-- Font Size Controls -->
    

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
  font-size: 1.5rem;
  font-weight: 700;
  line-height: 1.3;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror h2) {
  font-size: 1.25rem;
  font-weight: 600;
  line-height: 1.4;
  margin-top: 1.25rem;
  margin-bottom: 0.625rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror h3) {
  font-size: 1.1rem;
  font-weight: 600;
  line-height: 1.4;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror h4) {
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.4;
  margin-top: 0.875rem;
  margin-bottom: 0.375rem;
  color: hsl(var(--foreground));
  page-break-after: avoid;
}

:deep(.ProseMirror ul) {
  list-style: disc;
  padding-left: 1.5rem;
  margin: 0.75rem 0;
}

:deep(.ProseMirror ol) {
  list-style: decimal;
  padding-left: 1.5rem;
  margin: 0.75rem 0;
}

:deep(.ProseMirror li) {
  margin: 0.375rem 0;
  line-height: 1.6;
  padding-left: 0.25rem;
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
  margin-bottom: 0.875rem;
  line-height: 1.6;
  text-align: left;
  text-indent: 0;
  word-spacing: 0.05em;
  letter-spacing: 0.01em;
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
  font-size: 14pt;
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

/* Dark mode support for tables */
@media (prefers-color-scheme: dark) {
  :deep(.ProseMirror table) {
    border-color: #6b7280;
    background-color: #1f2937;
  }

  :deep(.ProseMirror table td),
  :deep(.ProseMirror table th) {
    border-color: #6b7280;
    background-color: #1f2937;
    color: #f9fafb;
  }

  :deep(.ProseMirror table th) {
    background-color: #374151;
    color: #f3f4f6;
  }
}

/* Mermaid diagram container styles */
:deep(.mermaid-container) {
  margin: 1.5rem 0;
  padding: 1rem;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 0.5rem;
  overflow-x: auto;
  display: flex;
  justify-content: center;
  align-items: center;
}

:deep(.mermaid-container svg) {
  max-width: 100%;
  height: auto;
}

:deep(.mermaid-error) {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  padding: 1rem;
  border-radius: 0.5rem;
  margin: 1rem 0;
}

@media (prefers-color-scheme: dark) {
  :deep(.mermaid-container) {
    background: #1f2937;
    border-color: #374151;
  }

  :deep(.mermaid-error) {
    background: #7f1d1d;
    border-color: #991b1b;
    color: #fecaca;
  }
}

/* Code block syntax highlighting styles */
:deep(.ProseMirror .code-block-highlighted) {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 0.5rem;
  padding: 1rem;
  margin: 1rem 0;
  overflow-x: auto;
  font-family: 'Fira Code', 'Courier New', Courier, monospace;
  font-size: 0.875rem;
  line-height: 1.5;
}

:deep(.ProseMirror .code-block-highlighted code) {
  background: none;
  padding: 0;
  border-radius: 0;
  color: inherit;
}

/* Syntax highlighting colors (light mode) */
:deep(.ProseMirror .hljs-comment),
:deep(.ProseMirror .hljs-quote) {
  color: #6a737d;
  font-style: italic;
}

:deep(.ProseMirror .hljs-keyword),
:deep(.ProseMirror .hljs-selector-tag),
:deep(.ProseMirror .hljs-subst) {
  color: #d73a49;
  font-weight: 600;
}

:deep(.ProseMirror .hljs-string),
:deep(.ProseMirror .hljs-doctag),
:deep(.ProseMirror .hljs-title),
:deep(.ProseMirror .hljs-section),
:deep(.ProseMirror .hljs-selector-id) {
  color: #032f62;
}

:deep(.ProseMirror .hljs-function),
:deep(.ProseMirror .hljs-class),
:deep(.ProseMirror .hljs-title) {
  color: #6f42c1;
}

:deep(.ProseMirror .hljs-number),
:deep(.ProseMirror .hljs-literal),
:deep(.ProseMirror .hljs-variable),
:deep(.ProseMirror .hljs-template-variable) {
  color: #005cc5;
}

:deep(.ProseMirror .hljs-meta),
:deep(.ProseMirror .hljs-meta-string) {
  color: #735c0f;
}

@media (prefers-color-scheme: dark) {
  :deep(.ProseMirror .code-block-highlighted) {
    background: #1f2937;
    border-color: #374151;
  }

  :deep(.ProseMirror .hljs-comment),
  :deep(.ProseMirror .hljs-quote) {
    color: #8b949e;
  }

  :deep(.ProseMirror .hljs-keyword),
  :deep(.ProseMirror .hljs-selector-tag),
  :deep(.ProseMirror .hljs-subst) {
    color: #ff7b72;
  }

  :deep(.ProseMirror .hljs-string),
  :deep(.ProseMirror .hljs-doctag) {
    color: #a5d6ff;
  }

  :deep(.ProseMirror .hljs-function),
  :deep(.ProseMirror .hljs-class) {
    color: #d2a8ff;
  }

  :deep(.ProseMirror .hljs-number),
  :deep(.ProseMirror .hljs-literal) {
    color: #79c0ff;
  }
}
</style>