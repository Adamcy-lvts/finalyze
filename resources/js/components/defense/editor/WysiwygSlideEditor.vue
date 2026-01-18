<script setup lang="ts">
/**
 * WysiwygSlideEditor - Main WYSIWYG Editor Component
 * Provides PowerPoint-like editing experience for defense deck slides
 */

import { ref, computed, watch, onMounted, onUnmounted, provide, nextTick } from 'vue';
import { v4 as uuid } from 'uuid';
import {
  ChevronLeft,
  ChevronRight,
  Plus,
  Trash2,
  Maximize2,
  Download,
  Copy,
  Monitor,
} from 'lucide-vue-next';
import EditorCanvas from './EditorCanvas.vue';
import EditorToolbar from './EditorToolbar.vue';
import ElementInspector from './ElementInspector.vue';
import ThemeSelector from './ThemeSelector.vue';
import PresentationMode from './PresentationMode.vue';
import PresenterView from './PresenterView.vue';
import SlideTemplateGallery from './SlideTemplateGallery.vue';
import { useHistory, createDebouncedPush } from '@/composables/editor/useHistory';
import { useSelection } from '@/composables/editor/useSelection';
import { useTheme } from '@/composables/editor/useTheme';
import type {
  WysiwygSlide,
  WysiwygSlideElement,
  HorizontalAlign,
  VerticalAlign,
} from '@/types/wysiwyg';
import type { UseCanvasReturn } from '@/composables/editor/useCanvas';
import type { UseElementsReturn } from '@/composables/editor/useElements';

interface Props {
  slides: WysiwygSlide[];
  activeIndex?: number;
  isSaving?: boolean;
  projectTitle?: string;
}

interface Emits {
  (e: 'update:slides', slides: WysiwygSlide[]): void;
  (e: 'update:activeIndex', index: number): void;
  (e: 'present'): void;
  (e: 'export'): void;
}

const props = withDefaults(defineProps<Props>(), {
  activeIndex: 0,
  isSaving: false,
  projectTitle: 'Untitled',
});

const emit = defineEmits<Emits>();

// Refs
const editorCanvasRef = ref<InstanceType<typeof EditorCanvas> | null>(null);
const slideThumbnailsRef = ref<HTMLDivElement | null>(null);
const showShapeMenu = ref(false);

// State
const localSlides = ref<WysiwygSlide[]>([]);
const currentSlideIndex = ref(0);
const selectedElementIds = ref<string[]>([]);
const gridEnabled = ref(false);
const snapEnabled = ref(false);
const currentThemeId = ref('modern');

// Presentation mode state
const showPresentationMode = ref(false);
const showPresenterView = ref(false);
const presentationStartIndex = ref(0);

// Template gallery state
const showTemplateGallery = ref(false);
const isProduction = import.meta.env.PROD;

// Theme system
const themeManager = useTheme({
  slides: localSlides,
});

// Load themes on mount
onMounted(() => {
  themeManager.loadThemes();
});

// Canvas and elements instances (populated after canvas is ready)
let canvasInstance: UseCanvasReturn | null = null;
let elementsInstance: UseElementsReturn | null = null;

// Selection instance with explicit typing
type SelectionInstance = ReturnType<typeof useSelection>;
const selectionInstanceRef = ref<SelectionInstance | null>(null);
const selectionInstance = computed(() => selectionInstanceRef.value);

// Computed
const currentSlide = computed<WysiwygSlide | null>(() => {
  return localSlides.value[currentSlideIndex.value] || null;
});

const selectedElement = computed<WysiwygSlideElement | null>(() => {
  if (!currentSlide.value || selectedElementIds.value.length !== 1) return null;
  return currentSlide.value.elements.find((el) => el.id === selectedElementIds.value[0]) || null;
});

const selectedElementType = computed(() => selectedElement.value?.type || null);
const hasSelection = computed(() => selectedElementIds.value.length > 0);
const hasMultipleSelection = computed(() => selectedElementIds.value.length > 1);
const hasClipboard = computed(() => {
  const clipboard = selectionInstanceRef.value?.clipboard;
  return clipboard?.value !== null && (clipboard?.value?.length ?? 0) > 0;
});
const isLightColor = (color?: string) => {
  if (!color) return true;
  const hex = color.replace('#', '');
  if (hex.length !== 6) return true;
  const r = parseInt(hex.slice(0, 2), 16);
  const g = parseInt(hex.slice(2, 4), 16);
  const b = parseInt(hex.slice(4, 6), 16);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.7;
};

// History
const historyInstance = useHistory({
  slides: localSlides,
  activeSlideIndex: currentSlideIndex,
  maxSize: 50,
  debounceMs: 500,
  onRestore: (state) => {
    // Reload canvas elements after history restore
    nextTick(() => {
      elementsInstance?.loadElements();
    });
  },
});

const debouncedPushHistory = createDebouncedPush(historyInstance);

// Initialize local state from props
watch(
  () => props.slides,
  (newSlides) => {
    if (JSON.stringify(newSlides) !== JSON.stringify(localSlides.value)) {
      localSlides.value = JSON.parse(JSON.stringify(newSlides));
    }
  },
  { immediate: true, deep: true }
);

watch(
  () => props.activeIndex,
  (index) => {
    if (index !== currentSlideIndex.value) {
      currentSlideIndex.value = index;
    }
  },
  { immediate: true }
);

// Emit changes to parent
watch(
  localSlides,
  (newSlides) => {
    emit('update:slides', newSlides);
    debouncedPushHistory();
  },
  { deep: true }
);

watch(currentSlideIndex, (index) => {
  emit('update:activeIndex', index);
  selectedElementIds.value = [];
  // Reload elements when slide changes
  nextTick(() => {
    if (canvasInstance) {
      canvasInstance.resetZoom();
      canvasInstance.resetPan();
    }
    editorCanvasRef.value?.fitToContainer();
    elementsInstance?.loadElements();
  });
});

// ============================================================================
// Canvas Event Handlers
// ============================================================================

function onCanvasReady(canvas: UseCanvasReturn) {
  canvasInstance = canvas;
  elementsInstance = editorCanvasRef.value?.getElements() || null;

  if (elementsInstance && canvasInstance) {
    selectionInstanceRef.value = useSelection({
      canvasInstance,
      elementsInstance,
      slide: currentSlide as any,
      onSelectionChange: (ids) => {
        selectedElementIds.value = ids;
      },
    });
  }
}

function onElementSelected(ids: string[]) {
  selectedElementIds.value = ids;
}

function onElementDeselected() {
  selectedElementIds.value = [];
}

function onElementsChange(elements: WysiwygSlideElement[]) {
  if (currentSlide.value) {
    currentSlide.value.elements = elements;
  }
}

function onElementModified(element: WysiwygSlideElement) {
  // Element was modified on canvas, data is already synced
}

// ============================================================================
// Toolbar Actions
// ============================================================================

function addText() {
  if (!elementsInstance) return;

  elementsInstance.addText({
    x: 10,
    y: 10,
    width: 40,
    height: 10,
    text: {
      content: 'Double click to edit',
      fontFamily: 'Arial',
      fontSize: 24,
      fontWeight: 'normal',
      fontStyle: 'normal',
      textAlign: 'left',
      color: '#111827',
      lineHeight: 1.4,
      letterSpacing: 0,
    },
  });
}

function addShape(shapeType: string) {
  if (!elementsInstance) return;

  elementsInstance.addShape(shapeType as any, {
    x: 20,
    y: 20,
    width: 20,
    height: 15,
    fill: '#6366F1',
    stroke: '#4F46E5',
    strokeWidth: 2,
  });
}

function addImage() {
  // Create file input for image upload
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async (e) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file || !elementsInstance) return;

    // For now, create object URL (in production, upload to server)
    const url = URL.createObjectURL(file);

    elementsInstance.addImage(url, {
      x: 25,
      y: 20,
      width: 30,
      height: 30,
    });
  };
  input.click();
}

function addChart() {
  if (!elementsInstance) return;

  // Use theme colors if available
  const chartColors = themeManager.getChartColors();

  elementsInstance.addChart({
    x: 15,
    y: 25,
    width: 50,
    height: 40,
    chart: {
      chartType: 'bar',
      title: 'Chart Title',
      labels: ['Category A', 'Category B', 'Category C', 'Category D'],
      datasets: [
        {
          label: 'Series 1',
          data: [65, 59, 80, 81],
          backgroundColor: chartColors,
        },
      ],
      showLegend: true,
      showGrid: true,
    },
  });
}

function addTable() {
  if (!elementsInstance) return;

  const theme = themeManager.currentTheme.value;

  elementsInstance.addTable({
    x: 10,
    y: 30,
    width: 60,
    height: 35,
    table: {
      title: 'Table',
      columns: [
        { key: 'col1', label: 'Column 1' },
        { key: 'col2', label: 'Column 2' },
        { key: 'col3', label: 'Column 3' },
      ],
      rows: [
        { col1: 'Row 1', col2: 'Data', col3: 'Value' },
        { col1: 'Row 2', col2: 'Data', col3: 'Value' },
        { col1: 'Row 3', col2: 'Data', col3: 'Value' },
      ],
      headerBackground: theme?.colors.surface || '#F3F4F6',
      headerColor: theme?.colors.text || '#111827',
      alternateRowColors: true,
    },
  });
}

function formatBold() {
  if (!selectedElement.value?.text) return;
  updateElement({
    text: {
      ...selectedElement.value.text,
      fontWeight: selectedElement.value.text.fontWeight === 'bold' ? 'normal' : 'bold',
    },
  });
}

function formatItalic() {
  if (!selectedElement.value?.text) return;
  updateElement({
    text: {
      ...selectedElement.value.text,
      fontStyle: selectedElement.value.text.fontStyle === 'italic' ? 'normal' : 'italic',
    },
  });
}

function formatUnderline() {
  if (!selectedElement.value?.text) return;
  updateElement({
    text: {
      ...selectedElement.value.text,
      textDecoration: selectedElement.value.text.textDecoration === 'underline' ? 'none' : 'underline',
    },
  });
}

function formatColor(color: string) {
  if (!selectedElement.value?.text) return;
  updateElement({
    text: {
      ...selectedElement.value.text,
      color,
    },
  });
}

function formatFont(fontFamily: string) {
  if (!selectedElement.value?.text) return;
  updateElement({
    text: {
      ...selectedElement.value.text,
      fontFamily,
    },
  });
}

function formatFontSize(fontSize: number) {
  if (!selectedElement.value?.text) return;
  updateElement({
    text: {
      ...selectedElement.value.text,
      fontSize,
    },
  });
}

// Theme handlers
function onThemeSelect(themeId: string) {
  currentThemeId.value = themeId;
  themeManager.setTheme(themeId);
}

function onThemeApply(themeId: string, applyToAll: boolean) {
  currentThemeId.value = themeId;
  themeManager.setTheme(themeId);

  if (applyToAll) {
    localSlides.value = themeManager.applyThemeToAllSlides(localSlides.value, themeId);
  } else if (currentSlide.value) {
    const theme = themeManager.themes.value.find(t => t.id === themeId);
    if (theme) {
      const updatedSlide = themeManager.applyThemeToSlide(currentSlide.value, theme);
      localSlides.value[currentSlideIndex.value] = updatedSlide;
    }
  }

  // Reload canvas elements
  nextTick(() => {
    elementsInstance?.loadElements();
  });
}

function alignHorizontal(alignment: HorizontalAlign) {
  selectionInstance.value?.alignHorizontal(alignment);
}

function alignVertical(alignment: VerticalAlign) {
  selectionInstance.value?.alignVertical(alignment);
}

function bringToFront() {
  if (selectedElementIds.value.length === 1) {
    elementsInstance?.bringToFront(selectedElementIds.value[0]);
  }
}

function sendToBack() {
  if (selectedElementIds.value.length === 1) {
    elementsInstance?.sendToBack(selectedElementIds.value[0]);
  }
}

function bringForward() {
  if (selectedElementIds.value.length === 1) {
    elementsInstance?.bringForward(selectedElementIds.value[0]);
  }
}

function sendBackward() {
  if (selectedElementIds.value.length === 1) {
    elementsInstance?.sendBackward(selectedElementIds.value[0]);
  }
}

function groupElements() {
  selectionInstance.value?.groupElements();
}

function ungroupElements() {
  // Find group ID of selected elements
  const element = selectedElement.value;
  if (element?.groupId) {
    selectionInstance.value?.ungroupElements(element.groupId);
  }
}

function undo() {
  historyInstance.undo();
}

function redo() {
  historyInstance.redo();
}

function duplicateSelected() {
  selectionInstance.value?.duplicate();
}

function deleteSelected() {
  elementsInstance?.removeSelected();
  selectedElementIds.value = [];
}

function toggleGrid() {
  gridEnabled.value = !gridEnabled.value;
}

function toggleSnap() {
  snapEnabled.value = !snapEnabled.value;
}

// ============================================================================
// Element Updates
// ============================================================================

function updateElement(updates: Partial<WysiwygSlideElement>) {
  if (!selectedElement.value || !elementsInstance) return;
  elementsInstance.updateElement(selectedElement.value.id, updates);
}

function updateSpeakerNotes(notes: string) {
  if (currentSlide.value) {
    currentSlide.value.speaker_notes = notes;
  }
}

// ============================================================================
// Slide Management
// ============================================================================

function addSlide() {
  showTemplateGallery.value = true;
}

function addSlideFromTemplate(slide: WysiwygSlide) {
  localSlides.value.push(slide);
  currentSlideIndex.value = localSlides.value.length - 1;
  // Reload elements for new slide
  nextTick(() => {
    elementsInstance?.loadElements();
  });
}

function duplicateSlide(index: number) {
  const slide = localSlides.value[index];
  if (!slide) return;

  const newSlide: WysiwygSlide = {
    ...JSON.parse(JSON.stringify(slide)),
    id: uuid(),
    title: `${slide.title} (Copy)`,
  };

  // Generate new IDs for all elements
  newSlide.elements = newSlide.elements.map((el: WysiwygSlideElement) => ({
    ...el,
    id: uuid(),
  }));

  localSlides.value.splice(index + 1, 0, newSlide);
  currentSlideIndex.value = index + 1;
}

function deleteSlide(index: number) {
  if (localSlides.value.length <= 1) return;

  localSlides.value.splice(index, 1);

  if (currentSlideIndex.value >= localSlides.value.length) {
    currentSlideIndex.value = localSlides.value.length - 1;
  }
}

function selectSlide(index: number) {
  currentSlideIndex.value = index;
}

function previousSlide() {
  if (currentSlideIndex.value > 0) {
    currentSlideIndex.value--;
  }
}

function nextSlide() {
  if (currentSlideIndex.value < localSlides.value.length - 1) {
    currentSlideIndex.value++;
  }
}

// ============================================================================
// Presentation Mode
// ============================================================================

function startPresentation() {
  presentationStartIndex.value = currentSlideIndex.value;
  showPresentationMode.value = true;
}

function startPresenterView() {
  presentationStartIndex.value = currentSlideIndex.value;
  showPresenterView.value = true;
}

function exitPresentation() {
  showPresentationMode.value = false;
  showPresenterView.value = false;
}

// ============================================================================
// Keyboard Shortcuts
// ============================================================================

function handleKeydown(e: KeyboardEvent) {
  const isInputFocused = ['INPUT', 'TEXTAREA', 'SELECT'].includes(
    (e.target as HTMLElement)?.tagName
  );

  if (isInputFocused) return;

  const isMod = e.metaKey || e.ctrlKey;

  // Undo
  if (isMod && e.key === 'z' && !e.shiftKey) {
    e.preventDefault();
    undo();
    return;
  }

  // Redo
  if (isMod && e.key === 'z' && e.shiftKey) {
    e.preventDefault();
    redo();
    return;
  }

  // Delete
  if (e.key === 'Delete' || e.key === 'Backspace') {
    if (hasSelection.value) {
      e.preventDefault();
      deleteSelected();
    }
    return;
  }

  // Copy
  if (isMod && e.key === 'c') {
    e.preventDefault();
    selectionInstance.value?.copy();
    return;
  }

  // Cut
  if (isMod && e.key === 'x') {
    e.preventDefault();
    selectionInstance.value?.cut();
    return;
  }

  // Paste
  if (isMod && e.key === 'v') {
    e.preventDefault();
    selectionInstance.value?.paste();
    return;
  }

  // Duplicate
  if (isMod && e.key === 'd') {
    e.preventDefault();
    duplicateSelected();
    return;
  }

  // Bold
  if (isMod && e.key === 'b') {
    e.preventDefault();
    formatBold();
    return;
  }

  // Italic
  if (isMod && e.key === 'i') {
    e.preventDefault();
    formatItalic();
    return;
  }

  // Underline
  if (isMod && e.key === 'u') {
    e.preventDefault();
    formatUnderline();
    return;
  }

  // Group
  if (isMod && e.key === 'g' && !e.shiftKey) {
    e.preventDefault();
    groupElements();
    return;
  }

  // Ungroup
  if (isMod && e.key === 'g' && e.shiftKey) {
    e.preventDefault();
    ungroupElements();
    return;
  }

  // Select all
  if (isMod && e.key === 'a') {
    e.preventDefault();
    canvasInstance?.selectAll();
    return;
  }

  // Escape
  if (e.key === 'Escape') {
    canvasInstance?.deselectAll();
    return;
  }

  // Arrow key navigation
  if (['ArrowLeft', 'ArrowRight'].includes(e.key) && !hasSelection.value) {
    e.preventDefault();
    if (e.key === 'ArrowLeft') previousSlide();
    if (e.key === 'ArrowRight') nextSlide();
  }
}

// Lifecycle
onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <div v-if="isProduction" class="flex h-full items-center justify-center bg-zinc-100 dark:bg-zinc-950">
    <div class="max-w-xl text-center px-6">
      <div
        class="mx-auto mb-4 inline-flex items-center rounded-full border border-zinc-200/70 bg-white px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.25em] text-zinc-500 dark:border-white/10 dark:bg-white/5 dark:text-white/70"
      >
        Coming Soon
      </div>
      <h3 class="text-2xl md:text-3xl font-display font-bold text-zinc-900 dark:text-white">
        Defense Slide Deck Editor
      </h3>
      <p class="mt-3 text-sm text-zinc-600 dark:text-white/70">
        The editor will let you refine layouts, rearrange slides, apply themes, and export
        presentation-ready decks directly from Finalyze.
      </p>
    </div>
  </div>
  <div v-else class="wysiwyg-editor flex flex-col h-full bg-zinc-100 dark:bg-zinc-950">
    <!-- Toolbar -->
    <EditorToolbar
      v-model:showShapeMenu="showShapeMenu"
      :can-undo="historyInstance.canUndo.value"
      :can-redo="historyInstance.canRedo.value"
      :has-selection="hasSelection"
      :has-multiple-selection="hasMultipleSelection"
      :has-clipboard="hasClipboard"
      :grid-enabled="gridEnabled"
      :snap-enabled="snapEnabled"
      :selected-element-type="selectedElementType"
      :text-color="selectedElement?.text?.color || '#111827'"
      :font-family="selectedElement?.text?.fontFamily || 'Arial'"
      :font-size="selectedElement?.text?.fontSize || 18"
      @add:text="addText"
      @add:shape="addShape"
      @add:image="addImage"
      @add:chart="addChart"
      @add:table="addTable"
      @format:bold="formatBold"
      @format:italic="formatItalic"
      @format:underline="formatUnderline"
      @format:color="formatColor"
      @format:font="formatFont"
      @format:font-size="formatFontSize"
      @align:horizontal="alignHorizontal"
      @align:vertical="alignVertical"
      @arrange:bring-front="bringToFront"
      @arrange:send-back="sendToBack"
      @arrange:bring-forward="bringForward"
      @arrange:send-backward="sendBackward"
      @group="groupElements"
      @ungroup="ungroupElements"
      @undo="undo"
      @redo="redo"
      @duplicate="duplicateSelected"
      @delete="deleteSelected"
      @toggle:grid="toggleGrid"
      @toggle:snap="toggleSnap"
    />

    <div class="flex flex-1 overflow-hidden">
      <!-- Slide Thumbnails Sidebar -->
      <div
        ref="slideThumbnailsRef"
        class="slide-thumbnails w-48 bg-white dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-800 overflow-y-auto p-3"
      >
        <div class="space-y-2">
          <div
            v-for="(slide, index) in localSlides"
            :key="slide.id"
            class="slide-thumbnail group relative cursor-pointer"
            :class="{ 'ring-2 ring-indigo-500': index === currentSlideIndex }"
            @click="selectSlide(index)"
          >
            <!-- Thumbnail preview -->
            <div
              class="aspect-video bg-zinc-50 dark:bg-zinc-950 rounded border border-zinc-200 dark:border-zinc-700 overflow-hidden"
              :style="{ backgroundColor: slide.backgroundColor }"
            >
              <div
                class="p-2 text-[6px] leading-tight truncate"
                :style="{ color: isLightColor(slide.backgroundColor) ? '#475569' : '#F8FAFC' }"
              >
                {{ slide.title }}
              </div>
            </div>

            <!-- Slide number -->
            <div class="absolute bottom-1 left-1 text-[10px] font-medium text-zinc-500 bg-white/80 dark:bg-zinc-800/80 px-1 rounded">
              {{ index + 1 }}
            </div>

            <!-- Actions on hover -->
            <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity flex gap-1">
              <button
                class="p-1 bg-white dark:bg-zinc-700 rounded shadow hover:bg-zinc-100 dark:hover:bg-zinc-600"
                title="Duplicate slide"
                @click.stop="duplicateSlide(index)"
              >
                <Copy class="w-3 h-3" />
              </button>
              <button
                v-if="localSlides.length > 1"
                class="p-1 bg-white dark:bg-zinc-700 rounded shadow hover:bg-red-100 dark:hover:bg-red-900/30 text-red-600"
                title="Delete slide"
                @click.stop="deleteSlide(index)"
              >
                <Trash2 class="w-3 h-3" />
              </button>
            </div>
          </div>
        </div>

        <!-- Add slide button -->
        <button
          class="w-full mt-3 py-2 flex items-center justify-center gap-1 text-sm text-zinc-600 dark:text-zinc-400 border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded hover:border-indigo-500 hover:text-indigo-600 transition-colors"
          @click="addSlide"
        >
          <Plus class="w-4 h-4" />
          Add Slide
        </button>
      </div>

      <!-- Main Canvas Area -->
      <div class="flex-1 flex flex-col">
        <!-- Canvas -->
        <div class="flex-1 p-4 flex">
          <EditorCanvas
            ref="editorCanvasRef"
            class="flex-1"
            :slide="currentSlide"
            :show-grid="gridEnabled"
            :snap-to-grid="snapEnabled"
            @canvas:ready="onCanvasReady"
            @element:selected="onElementSelected"
            @element:deselected="onElementDeselected"
            @elements:change="onElementsChange"
            @element:modified="onElementModified"
          />
        </div>

        <!-- Bottom bar -->
        <div class="flex items-center justify-between px-4 py-2 bg-white dark:bg-zinc-900 border-t border-zinc-200 dark:border-zinc-800">
          <!-- Slide navigation -->
          <div class="flex items-center gap-2">
            <button
              class="p-1.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-40"
              :disabled="currentSlideIndex === 0"
              @click="previousSlide"
            >
              <ChevronLeft class="w-4 h-4" />
            </button>

            <span class="text-sm text-zinc-600 dark:text-zinc-400">
              {{ currentSlideIndex + 1 }} / {{ localSlides.length }}
            </span>

            <button
              class="p-1.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 disabled:opacity-40"
              :disabled="currentSlideIndex === localSlides.length - 1"
              @click="nextSlide"
            >
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>

          <!-- Save status -->
          <div class="text-sm text-zinc-500">
            <span v-if="isSaving">Saving...</span>
            <span v-else>All changes saved</span>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2">
            <ThemeSelector
              :current-theme-id="currentThemeId"
              @select="onThemeSelect"
              @apply="onThemeApply"
            />

            <!-- Present mode pills -->
            <div class="flex items-center bg-zinc-100 dark:bg-zinc-800 rounded-lg p-0.5">
              <button
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md transition-colors hover:bg-white dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-400"
                title="Fullscreen presentation"
                @click="startPresentation"
              >
                <Maximize2 class="w-4 h-4" />
                Present
              </button>
              <button
                class="flex items-center gap-1.5 px-3 py-1.5 text-sm rounded-md transition-colors hover:bg-white dark:hover:bg-zinc-700 text-zinc-600 dark:text-zinc-400"
                title="Presenter view with notes"
                @click="startPresenterView"
              >
                <Monitor class="w-4 h-4" />
                Speaker
              </button>
            </div>

            <button
              class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-indigo-600 text-white hover:bg-indigo-700 rounded transition-colors"
              @click="emit('export')"
            >
              <Download class="w-4 h-4" />
              Export
            </button>
          </div>
        </div>
      </div>

      <!-- Element Inspector -->
      <ElementInspector
        :element="selectedElement"
        :speaker-notes="currentSlide?.speaker_notes"
        @update="updateElement"
        @update:speaker-notes="updateSpeakerNotes"
      />
    </div>

    <!-- Presentation Mode -->
    <PresentationMode
      v-if="showPresentationMode"
      :slides="localSlides"
      :initial-slide-index="presentationStartIndex"
      @close="exitPresentation"
    />

    <!-- Presenter View -->
    <PresenterView
      v-if="showPresenterView"
      :slides="localSlides"
      :initial-slide-index="presentationStartIndex"
      @close="exitPresentation"
    />

    <!-- Slide Template Gallery -->
    <SlideTemplateGallery
      :open="showTemplateGallery"
      @close="showTemplateGallery = false"
      @select="addSlideFromTemplate"
    />
  </div>
</template>

<style scoped>
.wysiwyg-editor {
  min-height: 600px;
}

.slide-thumbnails::-webkit-scrollbar {
  width: 6px;
}

.slide-thumbnails::-webkit-scrollbar-track {
  background: transparent;
}

.slide-thumbnails::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 3px;
}

.dark .slide-thumbnails::-webkit-scrollbar-thumb {
  background: #3f3f46;
}

.slide-thumbnail {
  transition: transform 0.1s ease;
}

.slide-thumbnail:hover {
  transform: scale(1.02);
}
</style>
