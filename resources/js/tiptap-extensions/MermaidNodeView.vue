<template>
  <NodeViewWrapper
    class="mermaid-node-wrapper my-4 rounded-lg border border-border bg-card overflow-hidden"
    :class="{
      'ring-2 ring-primary ring-offset-2': selected,
      'border-destructive': hasError,
    }"
  >
    <!-- Header Bar -->
    <div
      class="flex items-center justify-between gap-2 px-3 py-2 bg-muted/50 border-b border-border"
    >
      <div class="flex items-center gap-2">
        <div
          class="flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-medium"
          :class="diagramTypeBadgeClass"
        >
          <component :is="diagramTypeIcon" class="w-3.5 h-3.5" />
          <span>{{ diagramTypeLabel }}</span>
        </div>

        <span v-if="hasError" class="text-xs text-destructive flex items-center gap-1">
          <AlertCircle class="w-3.5 h-3.5" />
          Syntax Error
        </span>
      </div>

      <div class="flex items-center gap-1">
        <!-- View Mode Toggle -->
        <Button
          variant="ghost"
          size="sm"
          class="h-7 px-2 text-xs"
          @click="toggleViewMode"
          :title="isCodeView ? 'Show Diagram' : 'Edit Code'"
        >
          <Code2 v-if="!isCodeView" class="w-3.5 h-3.5 mr-1" />
          <Eye v-else class="w-3.5 h-3.5 mr-1" />
          {{ isCodeView ? 'Preview' : 'Edit' }}
        </Button>

        <!-- Scale Controls (only in diagram view) -->
        <div v-if="!isCodeView && !hasError && svgContent" class="flex items-center gap-1 px-1 border-l border-border ml-1">
          <Button
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            @click="decreaseScale"
            title="Zoom out"
            :disabled="scale <= 25"
          >
            <ZoomOut class="w-3.5 h-3.5" />
          </Button>
          <span class="text-xs font-medium w-10 text-center">{{ scale }}%</span>
          <Button
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            @click="increaseScale"
            title="Zoom in"
            :disabled="scale >= 200"
          >
            <ZoomIn class="w-3.5 h-3.5" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="h-7 w-7 p-0"
            @click="resetScale"
            title="Reset zoom"
            :disabled="scale === 100"
          >
            <RotateCcw class="w-3.5 h-3.5" />
          </Button>
        </div>

        <!-- Copy Button -->
        <Button
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          @click="copyCode"
          title="Copy code"
        >
          <Check v-if="copied" class="w-3.5 h-3.5 text-green-500" />
          <Copy v-else class="w-3.5 h-3.5" />
        </Button>

        <!-- Download PNG -->
        <Button
          v-if="!isCodeView && !hasError && svgContent"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          @click="downloadPng"
          :disabled="isExporting"
          title="Download PNG"
        >
          <Loader2 v-if="isExporting" class="w-3.5 h-3.5 animate-spin" />
          <ImageIcon v-else class="w-3.5 h-3.5" />
        </Button>

        <!-- Download SVG -->
        <Button
          v-if="!isCodeView && !hasError && svgContent"
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0"
          @click="downloadSvg"
          title="Download SVG"
        >
          <Download class="w-3.5 h-3.5" />
        </Button>

        <!-- Delete -->
        <Button
          variant="ghost"
          size="sm"
          class="h-7 w-7 p-0 text-destructive hover:text-destructive"
          @click="deleteNode"
          title="Delete diagram"
        >
          <Trash2 class="w-3.5 h-3.5" />
        </Button>
      </div>
    </div>

    <!-- Content Area -->
    <div class="relative">
      <!-- Code Editor View -->
      <div v-if="isCodeView" class="p-3">
        <textarea
          ref="codeTextarea"
          v-model="localCode"
          class="w-full min-h-[200px] max-h-[400px] p-3 font-mono text-sm bg-muted/30 rounded-md border border-border focus:outline-none focus:ring-2 focus:ring-primary resize-y"
          placeholder="Enter Mermaid diagram code..."
          spellcheck="false"
          @input="handleCodeChange"
          @blur="saveCode"
          @keydown.tab.prevent="insertTab"
        ></textarea>

        <!-- Error Display -->
        <div
          v-if="hasError"
          class="mt-2 p-3 rounded-md bg-destructive/10 border border-destructive/30"
        >
          <div class="flex items-start gap-2">
            <AlertCircle class="w-4 h-4 text-destructive mt-0.5 shrink-0" />
            <div class="text-sm">
              <p class="font-medium text-destructive">Diagram Error</p>
              <p class="text-muted-foreground mt-1 font-mono text-xs whitespace-pre-wrap">
                {{ errorMessage }}
              </p>
            </div>
          </div>
        </div>

        <!-- Syntax Help -->
        <div class="mt-3 p-3 rounded-md bg-muted/30 text-xs text-muted-foreground">
          <p class="font-medium mb-2">Quick Reference:</p>
          <div class="grid grid-cols-2 gap-2">
            <code class="bg-muted px-1.5 py-0.5 rounded">flowchart TD</code>
            <span>Top-down flowchart</span>
            <code class="bg-muted px-1.5 py-0.5 rounded">sequenceDiagram</code>
            <span>Sequence diagram</span>
            <code class="bg-muted px-1.5 py-0.5 rounded">classDiagram</code>
            <span>Class diagram</span>
            <code class="bg-muted px-1.5 py-0.5 rounded">pie title "Title"</code>
            <span>Pie chart</span>
          </div>
        </div>
      </div>

      <!-- Diagram View -->
      <div v-else class="p-4">
        <!-- Loading State -->
        <div
          v-if="isRendering"
          class="flex items-center justify-center min-h-[200px]"
        >
          <div class="flex flex-col items-center gap-3">
            <Loader2 class="w-8 h-8 animate-spin text-primary" />
            <span class="text-sm text-muted-foreground">Rendering diagram...</span>
          </div>
        </div>

        <!-- Error State (show last valid diagram or placeholder) -->
        <div
          v-else-if="hasError && !svgContent"
          class="flex flex-col items-center justify-center min-h-[200px] text-center"
        >
          <AlertCircle class="w-12 h-12 text-destructive mb-3" />
          <p class="font-medium text-destructive">Unable to render diagram</p>
          <p class="text-sm text-muted-foreground mt-1 max-w-md">
            {{ errorMessage }}
          </p>
          <Button variant="outline" size="sm" class="mt-4" @click="toggleViewMode">
            <Code2 class="w-4 h-4 mr-2" />
            Edit Code
          </Button>
        </div>

        <!-- Rendered Diagram -->
        <div
          v-else
          ref="diagramContainer"
          class="mermaid-diagram flex justify-center overflow-auto"
          :class="{ 'opacity-50': hasError }"
        >
          <div
            class="diagram-scale-wrapper transition-transform duration-200 origin-center"
            :style="{ transform: `scale(${scale / 100})` }"
            v-html="svgContent"
          ></div>
        </div>
      </div>
    </div>
  </NodeViewWrapper>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { NodeViewWrapper } from '@tiptap/vue-3'
import { Button } from '@/components/ui/button'
import {
  Code2,
  Eye,
  Copy,
  Check,
  Download,
  Trash2,
  AlertCircle,
  Loader2,
  GitBranch,
  Users,
  Box,
  PieChart,
  BarChart3,
  Network,
  CircleDot,
  Timer,
  Brain,
  Calendar,
  ZoomIn,
  ZoomOut,
  RotateCcw,
  ImageIcon,
} from 'lucide-vue-next'
import mermaid from 'mermaid'

let mermaidInitialized = false

const props = defineProps({
  node: {
    type: Object,
    required: true,
  },
  editor: {
    type: Object,
    required: true,
  },
  selected: {
    type: Boolean,
    default: false,
  },
  getPos: {
    type: Function,
    required: true,
  },
  updateAttributes: {
    type: Function,
    required: true,
  },
  deleteNode: {
    type: Function,
    required: true,
  },
})

// State
const localCode = ref(props.node.attrs.code || '')
const svgContent = ref('')
const errorMessage = ref('')
const hasError = ref(false)
const isRendering = ref(false)
const isExporting = ref(false)
const copied = ref(false)
const codeTextarea = ref<HTMLTextAreaElement | null>(null)
const diagramContainer = ref<HTMLElement | null>(null)
const renderCounter = ref(0)
const scale = ref(props.node.attrs.scale || 100)

// Computed
const isCodeView = computed(() => props.node.attrs.viewMode === 'code')

const diagramType = computed(() => props.node.attrs.diagramType || 'flowchart')

const diagramTypeLabel = computed(() => {
  const labels: Record<string, string> = {
    flowchart: 'Flowchart',
    sequence: 'Sequence',
    class: 'Class',
    state: 'State',
    er: 'ER Diagram',
    gantt: 'Gantt',
    pie: 'Pie Chart',
    journey: 'Journey',
    git: 'Git Graph',
    mindmap: 'Mind Map',
    timeline: 'Timeline',
    quadrant: 'Quadrant',
    requirement: 'Requirement',
    c4: 'C4 Diagram',
    unknown: 'Diagram',
  }
  return labels[diagramType.value] || 'Diagram'
})

const diagramTypeIcon = computed(() => {
  const icons: Record<string, unknown> = {
    flowchart: GitBranch,
    sequence: Users,
    class: Box,
    state: CircleDot,
    er: Network,
    gantt: BarChart3,
    pie: PieChart,
    journey: Timer,
    git: GitBranch,
    mindmap: Brain,
    timeline: Calendar,
    quadrant: BarChart3,
    requirement: Box,
    c4: Network,
    unknown: Box,
  }
  return icons[diagramType.value] || Box
})

const diagramTypeBadgeClass = computed(() => {
  if (hasError.value) {
    return 'bg-destructive/10 text-destructive'
  }
  return 'bg-primary/10 text-primary'
})

// Initialize mermaid once with theme variables that follow the app's CSS variables.
// This avoids re-initializing on theme toggle (which caused flicker) while still
// allowing the rendered SVG to adapt via `var(--*)`.
const initMermaid = () => {
  if (mermaidInitialized) return
  mermaidInitialized = true

  mermaid.initialize({
    startOnLoad: false,
    theme: 'base',
    securityLevel: 'loose',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    themeVariables: {
      fontFamily: 'ui-sans-serif, system-ui, sans-serif',
      background: 'hsl(var(--background))',
      textColor: 'hsl(var(--foreground))',
      mainBkg: 'hsl(var(--card))',
      lineColor: 'hsl(var(--border))',
      nodeBorder: 'hsl(var(--border))',
      clusterBkg: 'hsl(var(--muted))',
      clusterBorder: 'hsl(var(--border))',
      titleColor: 'hsl(var(--foreground))',
      primaryColor: 'hsl(var(--primary))',
      primaryTextColor: 'hsl(var(--primary-foreground))',
      secondaryColor: 'hsl(var(--secondary))',
      tertiaryColor: 'hsl(var(--muted))',
      edgeLabelBackground: 'hsl(var(--background))',
    },
    flowchart: {
      useMaxWidth: true,
      htmlLabels: true,
      curve: 'basis',
    },
    sequence: {
      useMaxWidth: true,
      wrap: true,
    },
    gantt: {
      useMaxWidth: true,
    },
  })
}

// Render the mermaid diagram
const renderDiagram = async () => {
  if (!localCode.value.trim()) {
    hasError.value = true
    errorMessage.value = 'No diagram code provided'
    svgContent.value = ''
    return
  }

  isRendering.value = true
  hasError.value = false
  errorMessage.value = ''

  const currentRender = ++renderCounter.value

  try {
    // Validate the syntax first
    await mermaid.parse(localCode.value)

    // Generate unique ID for this render
    const id = `mermaid-${Date.now()}-${currentRender}`

    // Render the diagram
    const { svg } = await mermaid.render(id, localCode.value, diagramContainer.value ?? undefined)

    // Only update if this is still the latest render request
    if (currentRender === renderCounter.value) {
      svgContent.value = svg
      hasError.value = false
      errorMessage.value = ''

      // Update the error attribute in the node
      props.updateAttributes({ error: null })
    }
  } catch (error: unknown) {
    // Only update if this is still the latest render request
    if (currentRender === renderCounter.value) {
      hasError.value = true
      const err = error as Error
      errorMessage.value = formatErrorMessage(err.message || 'Unknown error')

      // Update the error attribute in the node
      props.updateAttributes({ error: errorMessage.value })
    }
  } finally {
    if (currentRender === renderCounter.value) {
      isRendering.value = false
    }
  }
}

// Format error message for better readability
const formatErrorMessage = (message: string): string => {
  // Remove mermaid error prefixes
  let formatted = message
    .replace(/^Error: /, '')
    .replace(/^Syntax error in text/, 'Syntax error')
    .replace(/mermaid version [\d.]+/, '')
    .trim()

  // Limit length
  if (formatted.length > 200) {
    formatted = formatted.substring(0, 200) + '...'
  }

  return formatted
}

// Handle code changes with debounce
let debounceTimer: ReturnType<typeof setTimeout> | null = null

const handleCodeChange = () => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }

  debounceTimer = setTimeout(() => {
    // Detect diagram type from code
    const newType = detectDiagramType(localCode.value)
    props.updateAttributes({
      code: localCode.value,
      diagramType: newType,
    })

    // Re-render if in diagram view
    if (!isCodeView.value) {
      renderDiagram()
    }
  }, 500)
}

// Save code immediately on blur
const saveCode = () => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
    debounceTimer = null
  }

  const newType = detectDiagramType(localCode.value)
  props.updateAttributes({
    code: localCode.value,
    diagramType: newType,
  })
}

// Toggle between code and diagram view
const toggleViewMode = () => {
  const newMode = isCodeView.value ? 'diagram' : 'code'
  props.updateAttributes({ viewMode: newMode })

  if (newMode === 'diagram') {
    nextTick(() => renderDiagram())
  } else {
    nextTick(() => {
      codeTextarea.value?.focus()
    })
  }
}

// Copy code to clipboard
const copyCode = async () => {
  try {
    await navigator.clipboard.writeText(localCode.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}

// Download SVG
const downloadSvg = () => {
  if (!svgContent.value) return

  const blob = new Blob([svgContent.value], { type: 'image/svg+xml' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `diagram-${diagramType.value}-${Date.now()}.svg`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

// Download PNG
const downloadPng = async () => {
  if (!svgContent.value || isExporting.value) return

  isExporting.value = true

  try {
    // Create a temporary container for the SVG
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = svgContent.value
    const svgElement = tempDiv.querySelector('svg')

    if (!svgElement) {
      throw new Error('SVG element not found')
    }

    // Get SVG dimensions
    const bbox = svgElement.getBBox()
    const width = Math.ceil(bbox.width + bbox.x * 2) || 800
    const height = Math.ceil(bbox.height + bbox.y * 2) || 600

    // Apply scale to export size
    const exportScale = scale.value / 100
    const exportWidth = Math.ceil(width * exportScale * 2) // 2x for better quality
    const exportHeight = Math.ceil(height * exportScale * 2)

    // Set explicit dimensions on SVG
    svgElement.setAttribute('width', String(width))
    svgElement.setAttribute('height', String(height))

    // Serialize SVG to string
    const serializer = new XMLSerializer()
    let svgString = serializer.serializeToString(svgElement)

    // Add XML declaration and ensure proper encoding
    if (!svgString.startsWith('<?xml')) {
      svgString = '<?xml version="1.0" encoding="UTF-8"?>' + svgString
    }

    // Create blob and image
    const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' })
    const svgUrl = URL.createObjectURL(svgBlob)

    const img = new Image()
    img.crossOrigin = 'anonymous'

    await new Promise<void>((resolve, reject) => {
      img.onload = () => {
        // Create canvas
        const canvas = document.createElement('canvas')
        canvas.width = exportWidth
        canvas.height = exportHeight
        const ctx = canvas.getContext('2d')

        if (!ctx) {
          reject(new Error('Could not get canvas context'))
          return
        }

        // Fill with white background
        ctx.fillStyle = '#ffffff'
        ctx.fillRect(0, 0, exportWidth, exportHeight)

        // Draw image scaled
        ctx.drawImage(img, 0, 0, exportWidth, exportHeight)

        // Convert to PNG and download
        canvas.toBlob((blob) => {
          if (blob) {
            const pngUrl = URL.createObjectURL(blob)
            const link = document.createElement('a')
            link.href = pngUrl
            link.download = `diagram-${diagramType.value}-${Date.now()}.png`
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            URL.revokeObjectURL(pngUrl)
          }
          resolve()
        }, 'image/png', 1.0)
      }

      img.onerror = () => {
        reject(new Error('Failed to load SVG image'))
      }

      img.src = svgUrl
    })

    URL.revokeObjectURL(svgUrl)
  } catch (err) {
    console.error('Failed to export PNG:', err)
  } finally {
    isExporting.value = false
  }
}

// Scale controls
const increaseScale = () => {
  if (scale.value < 200) {
    scale.value = Math.min(200, scale.value + 25)
    props.updateAttributes({ scale: scale.value })
  }
}

const decreaseScale = () => {
  if (scale.value > 25) {
    scale.value = Math.max(25, scale.value - 25)
    props.updateAttributes({ scale: scale.value })
  }
}

const resetScale = () => {
  scale.value = 100
  props.updateAttributes({ scale: 100 })
}

// Insert tab character in textarea
const insertTab = (event: KeyboardEvent) => {
  const textarea = event.target as HTMLTextAreaElement
  const start = textarea.selectionStart
  const end = textarea.selectionEnd

  localCode.value =
    localCode.value.substring(0, start) + '    ' + localCode.value.substring(end)

  nextTick(() => {
    textarea.selectionStart = textarea.selectionEnd = start + 4
  })
}

// Detect diagram type from code
const detectDiagramType = (code: string): string => {
  const trimmedCode = code.trim().toLowerCase()

  if (trimmedCode.startsWith('flowchart') || trimmedCode.startsWith('graph')) {
    return 'flowchart'
  }
  if (trimmedCode.startsWith('sequencediagram') || trimmedCode.match(/^sequence\s/)) {
    return 'sequence'
  }
  if (trimmedCode.startsWith('classdiagram') || trimmedCode.match(/^class\s/)) {
    return 'class'
  }
  if (trimmedCode.startsWith('statediagram') || trimmedCode.match(/^state\s/)) {
    return 'state'
  }
  if (trimmedCode.startsWith('erdiagram') || trimmedCode.match(/^er\s/)) {
    return 'er'
  }
  if (trimmedCode.startsWith('gantt')) {
    return 'gantt'
  }
  if (trimmedCode.startsWith('pie')) {
    return 'pie'
  }
  if (trimmedCode.startsWith('journey')) {
    return 'journey'
  }
  if (trimmedCode.startsWith('gitgraph')) {
    return 'git'
  }
  if (trimmedCode.startsWith('mindmap')) {
    return 'mindmap'
  }
  if (trimmedCode.startsWith('timeline')) {
    return 'timeline'
  }
  if (trimmedCode.startsWith('quadrantchart')) {
    return 'quadrant'
  }
  if (trimmedCode.startsWith('requirementdiagram')) {
    return 'requirement'
  }
  if (
    trimmedCode.startsWith('c4context') ||
    trimmedCode.startsWith('c4container') ||
    trimmedCode.startsWith('c4component')
  ) {
    return 'c4'
  }

  return 'unknown'
}

// Watch for external code changes
watch(
  () => props.node.attrs.code,
  (newCode) => {
    if (newCode !== localCode.value) {
      localCode.value = newCode
      if (!isCodeView.value) {
        renderDiagram()
      }
    }
  }
)

// Watch for view mode changes
watch(
  () => props.node.attrs.viewMode,
  (newMode) => {
    if (newMode === 'diagram') {
      nextTick(() => renderDiagram())
    }
  }
)

// Watch for scale attribute changes
watch(
  () => props.node.attrs.scale,
  (newScale) => {
    if (newScale && newScale !== scale.value) {
      scale.value = newScale
    }
  }
)

// NOTE: We intentionally do NOT observe dark mode class changes here.
// Mermaid is configured with CSS-variable based theme variables, so the SVG
// adapts automatically without re-initialization.

onMounted(() => {
  initMermaid()

  // Initial render if in diagram mode
  if (!isCodeView.value) {
    renderDiagram()
  }
})

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
})
</script>

<style scoped>
.mermaid-node-wrapper {
  user-select: none;
}

.mermaid-diagram {
  max-height: 500px;
  min-height: 100px;
}

.mermaid-diagram :deep(svg) {
  max-width: 100%;
  height: auto;
}

.diagram-scale-wrapper {
  display: inline-block;
}

.diagram-scale-wrapper :deep(svg) {
  display: block;
}

/* Ensure text in code textarea is selectable */
textarea {
  user-select: text;
}

</style>
