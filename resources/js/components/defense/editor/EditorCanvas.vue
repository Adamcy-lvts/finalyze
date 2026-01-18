<script setup lang="ts">
/**
 * EditorCanvas - Fabric.js Canvas Wrapper Component
 * Renders the slide canvas with interactive element editing
 */

import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue';
import { FabricObject, Textbox } from 'fabric';
import { useCanvas, type UseCanvasReturn } from '@/composables/editor/useCanvas';
import { useElements, type UseElementsReturn } from '@/composables/editor/useElements';
import type { WysiwygSlide, WysiwygSlideElement } from '@/types/wysiwyg';

interface Props {
  slide: WysiwygSlide | null;
  width?: number;
  height?: number;
  showGrid?: boolean;
  snapToGrid?: boolean;
  gridSize?: number;
  readonly?: boolean;
}

interface Emits {
  (e: 'element:modified', element: WysiwygSlideElement): void;
  (e: 'element:selected', ids: string[]): void;
  (e: 'element:deselected'): void;
  (e: 'elements:change', elements: WysiwygSlideElement[]): void;
  (e: 'canvas:ready', canvas: UseCanvasReturn): void;
  (e: 'canvas:contextmenu', event: { x: number; y: number; elementId?: string }): void;
}

const props = withDefaults(defineProps<Props>(), {
  width: 960,
  height: 540,
  showGrid: false,
  snapToGrid: false,
  gridSize: 20,
  readonly: false,
});

const emit = defineEmits<Emits>();

// Refs
const canvasRef = ref<HTMLCanvasElement | null>(null);
const containerRef = ref<HTMLDivElement | null>(null);

// Slide ref for composables
const slideRef = computed(() => props.slide);

// Canvas composable
const canvasInstance = useCanvas(canvasRef, {
  width: props.width,
  height: props.height,
  backgroundColor: props.slide?.backgroundColor || '#FFFFFF',
  showGrid: props.showGrid,
  snapToGrid: props.snapToGrid,
  gridSize: props.gridSize,
});

// Elements composable
const elementsInstance = useElements({
  canvasInstance,
  slide: slideRef as any,
  onElementsChange: (elements) => {
    emit('elements:change', elements);
  },
});

let resizeObserver: ResizeObserver | null = null;
let lastCanvasSize = { width: 0, height: 0 };
let resizeTimeout: ReturnType<typeof setTimeout> | null = null;

// Initialize canvas on mount
onMounted(async () => {
  await nextTick();

  if (canvasRef.value) {
    canvasInstance.initCanvas(props.width, props.height);

    // Set up event listeners
    setupEventListeners();

    // Load initial elements
    if (props.slide) {
      canvasInstance.setBackgroundColor(props.slide.backgroundColor || '#FFFFFF');
    }

    fitToContainer();
    elementsInstance.loadElements();

    if (containerRef.value) {
      resizeObserver = new ResizeObserver(() => {
        // Debounce resize to prevent flickering during layout changes
        if (resizeTimeout) {
          clearTimeout(resizeTimeout);
        }
        resizeTimeout = setTimeout(() => {
          fitToContainer();
        }, 100);
      });
      resizeObserver.observe(containerRef.value);
    }

    emit('canvas:ready', canvasInstance);
  }
});

// Cleanup on unmount
onUnmounted(() => {
  if (resizeTimeout) {
    clearTimeout(resizeTimeout);
  }
  if (resizeObserver && containerRef.value) {
    resizeObserver.unobserve(containerRef.value);
    resizeObserver.disconnect();
  }
  canvasInstance.dispose();
});

// Watch for slide changes
watch(
  () => props.slide,
  (newSlide, oldSlide) => {
    if (!canvasInstance.canvas.value) return;

    if (newSlide?.id !== oldSlide?.id) {
      // Different slide, reload elements
      elementsInstance.loadElements();
    }

    if (newSlide) {
      canvasInstance.setBackgroundColor(newSlide.backgroundColor || '#FFFFFF');
    }
  },
  { deep: true }
);

// Watch for grid settings changes
watch(
  () => props.showGrid,
  (show) => {
    if (show) {
      canvasInstance.drawGrid();
    } else {
      canvasInstance.clearGrid();
    }
  }
);

watch(
  () => props.snapToGrid,
  (snap) => {
    canvasInstance.state.value.snapEnabled = snap;
  }
);

/**
 * Set up canvas event listeners
 */
function setupEventListeners() {
  // Selection events
  canvasInstance.on('selection:created', (data: { selected: FabricObject[] }) => {
    const ids = data.selected
      .map((obj) => (obj as any).elementId)
      .filter(Boolean);
    emit('element:selected', ids);
  });

  canvasInstance.on('selection:updated', (data: { selected: FabricObject[] }) => {
    const ids = data.selected
      .map((obj) => (obj as any).elementId)
      .filter(Boolean);
    emit('element:selected', ids);
  });

  canvasInstance.on('selection:cleared', () => {
    emit('element:deselected');
  });

  // Object modification
  canvasInstance.on('object:modified', (data: { target: FabricObject }) => {
    const elementId = (data.target as any).elementId;
    if (elementId) {
      elementsInstance.syncFromFabricObject(elementId);
      const element = elementsInstance.getElementById(elementId);
      if (element) {
        emit('element:modified', element);
      }
    }
  });

  // Context menu (right-click)
  canvasInstance.on('mouse:down', (data: any) => {
    if (data.button === 2) {
      // Right click
      const elementId = data.target ? (data.target as any).elementId : undefined;
      emit('canvas:contextmenu', {
        x: data.x,
        y: data.y,
        elementId,
      });
    }
  });

  // Text editing
  canvasInstance.on('mouse:dblclick', (data: { target: FabricObject }) => {
    const target = data.target;
    if (target && (target as any).elementType === 'text') {
      // Enter text editing mode
      if (target instanceof Textbox) {
        target.enterEditing();
        target.selectAll();
      }
    }
  });

  canvasInstance.on('text:changed', (data: { target: FabricObject }) => {
    const elementId = (data.target as any)?.elementId;
    if (elementId) {
      elementsInstance.syncFromFabricObject(elementId);
    }
  });

  canvasInstance.on('text:editing:exited', (data: { target: FabricObject }) => {
    const elementId = (data.target as any)?.elementId;
    if (elementId) {
      elementsInstance.syncFromFabricObject(elementId);
    }
  });
}

/**
 * Resize canvas to fit container
 */
function fitToContainer() {
  if (containerRef.value) {
    const size = canvasInstance.fitToContainer(containerRef.value);
    if (size && (size.width !== lastCanvasSize.width || size.height !== lastCanvasSize.height)) {
      lastCanvasSize = size;
      elementsInstance.loadElements();
    }
  }
}

/**
 * Get canvas instance for parent components
 */
function getCanvas(): UseCanvasReturn {
  return canvasInstance;
}

/**
 * Get elements instance for parent components
 */
function getElements(): UseElementsReturn {
  return elementsInstance;
}

// Expose methods and instances
defineExpose({
  canvasInstance,
  elementsInstance,
  getCanvas,
  getElements,
  fitToContainer,
});
</script>

<template>
  <div
    ref="containerRef"
    class="editor-canvas-container relative flex items-center justify-center bg-zinc-200 dark:bg-zinc-800 overflow-hidden w-full h-full"
    :class="{ 'cursor-not-allowed opacity-75': readonly }"
  >
    <!-- Canvas wrapper with shadow -->
    <div class="canvas-wrapper relative shadow-2xl rounded-sm">
      <canvas ref="canvasRef" :width="width" :height="height" />
    </div>

    <!-- Zoom indicator -->
    <div
      v-if="canvasInstance.state.value.zoom !== 1"
      class="absolute bottom-4 right-4 px-3 py-1.5 bg-zinc-900/80 text-white text-sm font-medium rounded-lg"
    >
      {{ Math.round(canvasInstance.state.value.zoom * 100) }}%
    </div>

    <!-- Loading overlay -->
    <div
      v-if="!slide"
      class="absolute inset-0 flex items-center justify-center bg-zinc-100 dark:bg-zinc-900"
    >
      <p class="text-zinc-500 dark:text-zinc-400">No slide selected</p>
    </div>
  </div>
</template>

<style scoped>
.editor-canvas-container {
  min-height: 0;
}

.canvas-wrapper {
  background-color: white;
}

.canvas-wrapper canvas {
  display: block;
}

/* Fabric.js control styling overrides */
:deep(.canvas-container) {
  margin: 0 auto;
}
</style>
