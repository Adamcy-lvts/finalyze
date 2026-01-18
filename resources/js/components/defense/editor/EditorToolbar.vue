<script setup lang="ts">
/**
 * EditorToolbar - Main editing toolbar for the WYSIWYG editor
 * Contains tools for adding elements, formatting, alignment, and editing
 */

import { computed, ref } from 'vue';
import {
  Type,
  Image as ImageIcon,
  Bold,
  Italic,
  Underline,
  AlignLeft,
  AlignCenter,
  AlignRight,
  AlignStartVertical,
  AlignCenterVertical,
  AlignEndVertical,
  ArrowUp,
  ArrowDown,
  Layers,
  Group,
  Ungroup,
  Undo2,
  Redo2,
  Copy,
  Trash2,
  Grid3X3,
  BarChart3,
  Table,
} from 'lucide-vue-next';
import ShapeLibrary from './ShapeLibrary.vue';
import ColorPicker from './ColorPicker.vue';
import FontSelector from './FontSelector.vue';
import type { ShapeProperties } from '@/types/wysiwyg';

interface Props {
  canUndo?: boolean;
  canRedo?: boolean;
  hasSelection?: boolean;
  hasMultipleSelection?: boolean;
  hasClipboard?: boolean;
  gridEnabled?: boolean;
  snapEnabled?: boolean;
  selectedElementType?: string | null;
  // Text formatting props
  textColor?: string;
  fontFamily?: string;
  fontSize?: number;
}

interface Emits {
  (e: 'add:text'): void;
  (e: 'add:shape', shape: ShapeProperties['shapeType']): void;
  (e: 'add:image'): void;
  (e: 'add:chart'): void;
  (e: 'add:table'): void;
  (e: 'format:bold'): void;
  (e: 'format:italic'): void;
  (e: 'format:underline'): void;
  (e: 'format:color', color: string): void;
  (e: 'format:font', fontFamily: string): void;
  (e: 'format:fontSize', size: number): void;
  (e: 'align:horizontal', alignment: 'left' | 'center' | 'right'): void;
  (e: 'align:vertical', alignment: 'top' | 'middle' | 'bottom'): void;
  (e: 'arrange:bring-front'): void;
  (e: 'arrange:send-back'): void;
  (e: 'arrange:bring-forward'): void;
  (e: 'arrange:send-backward'): void;
  (e: 'group'): void;
  (e: 'ungroup'): void;
  (e: 'undo'): void;
  (e: 'redo'): void;
  (e: 'duplicate'): void;
  (e: 'delete'): void;
  (e: 'toggle:grid'): void;
  (e: 'toggle:snap'): void;
}

const props = withDefaults(defineProps<Props>(), {
  canUndo: false,
  canRedo: false,
  hasSelection: false,
  hasMultipleSelection: false,
  hasClipboard: false,
  gridEnabled: false,
  snapEnabled: false,
  selectedElementType: null,
  textColor: '#111827',
  fontFamily: 'Arial',
  fontSize: 18,
});

const emit = defineEmits<Emits>();

// Computed
const isTextSelected = computed(() => props.selectedElementType === 'text');
const canAlign = computed(() => props.hasMultipleSelection);

// Font sizes
const fontSizes = [8, 10, 12, 14, 16, 18, 20, 24, 28, 32, 36, 42, 48, 56, 64, 72];

function handleShapeSelect(shapeType: ShapeProperties['shapeType']) {
  emit('add:shape', shapeType);
}

function handleColorChange(color: string) {
  emit('format:color', color);
}

function handleFontChange(font: string) {
  emit('format:font', font);
}

function handleFontSizeChange(event: Event) {
  const target = event.target as HTMLSelectElement;
  emit('format:fontSize', parseInt(target.value));
}
</script>

<template>
  <div class="editor-toolbar flex items-center gap-1 p-2 bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800">
    <!-- Elements Section -->
    <div class="toolbar-section flex items-center gap-1 pr-3 border-r border-zinc-200 dark:border-zinc-700">
      <!-- Add Text -->
      <button
        class="toolbar-btn"
        title="Add Text Box (T)"
        @click="emit('add:text')"
      >
        <Type class="w-4 h-4" />
      </button>

      <!-- Add Shape (using ShapeLibrary component) -->
      <ShapeLibrary @select="handleShapeSelect" />

      <!-- Add Image -->
      <button
        class="toolbar-btn"
        title="Add Image"
        @click="emit('add:image')"
      >
        <ImageIcon class="w-4 h-4" />
      </button>

      <!-- Add Chart -->
      <button
        class="toolbar-btn"
        title="Add Chart"
        @click="emit('add:chart')"
      >
        <BarChart3 class="w-4 h-4" />
      </button>

      <!-- Add Table -->
      <button
        class="toolbar-btn"
        title="Add Table"
        @click="emit('add:table')"
      >
        <Table class="w-4 h-4" />
      </button>
    </div>

    <!-- Format Section (only when text selected) -->
    <div
      v-if="isTextSelected"
      class="toolbar-section flex items-center gap-1 px-3 border-r border-zinc-200 dark:border-zinc-700"
    >
      <!-- Font Family -->
      <FontSelector
        :model-value="fontFamily"
        @update:model-value="handleFontChange"
      />

      <!-- Font Size -->
      <select
        :value="fontSize"
        class="h-8 px-2 text-sm border border-input rounded-md bg-background focus:outline-none focus:ring-2 focus:ring-ring"
        @change="handleFontSizeChange"
      >
        <option v-for="size in fontSizes" :key="size" :value="size">
          {{ size }}
        </option>
      </select>

      <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700 mx-1" />

      <button
        class="toolbar-btn"
        title="Bold (Ctrl+B)"
        @click="emit('format:bold')"
      >
        <Bold class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        title="Italic (Ctrl+I)"
        @click="emit('format:italic')"
      >
        <Italic class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        title="Underline (Ctrl+U)"
        @click="emit('format:underline')"
      >
        <Underline class="w-4 h-4" />
      </button>

      <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700 mx-1" />

      <!-- Text Color -->
      <ColorPicker
        :model-value="textColor"
        label="A"
        size="sm"
        @update:model-value="handleColorChange"
      />
    </div>

    <!-- Alignment Section (only when multiple selected) -->
    <div
      v-if="hasSelection"
      class="toolbar-section flex items-center gap-1 px-3 border-r border-zinc-200 dark:border-zinc-700"
    >
      <button
        class="toolbar-btn"
        :disabled="!canAlign"
        title="Align Left"
        @click="emit('align:horizontal', 'left')"
      >
        <AlignLeft class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        :disabled="!canAlign"
        title="Align Center"
        @click="emit('align:horizontal', 'center')"
      >
        <AlignCenter class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        :disabled="!canAlign"
        title="Align Right"
        @click="emit('align:horizontal', 'right')"
      >
        <AlignRight class="w-4 h-4" />
      </button>

      <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700 mx-1" />

      <button
        class="toolbar-btn"
        :disabled="!canAlign"
        title="Align Top"
        @click="emit('align:vertical', 'top')"
      >
        <AlignStartVertical class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        :disabled="!canAlign"
        title="Align Middle"
        @click="emit('align:vertical', 'middle')"
      >
        <AlignCenterVertical class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        :disabled="!canAlign"
        title="Align Bottom"
        @click="emit('align:vertical', 'bottom')"
      >
        <AlignEndVertical class="w-4 h-4" />
      </button>
    </div>

    <!-- Arrange Section -->
    <div
      v-if="hasSelection"
      class="toolbar-section flex items-center gap-1 px-3 border-r border-zinc-200 dark:border-zinc-700"
    >
      <button
        class="toolbar-btn"
        title="Bring to Front"
        @click="emit('arrange:bring-front')"
      >
        <ArrowUp class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        title="Send to Back"
        @click="emit('arrange:send-back')"
      >
        <ArrowDown class="w-4 h-4" />
      </button>

      <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700 mx-1" />

      <button
        class="toolbar-btn"
        :disabled="!hasMultipleSelection"
        title="Group (Ctrl+G)"
        @click="emit('group')"
      >
        <Group class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        title="Ungroup (Ctrl+Shift+G)"
        @click="emit('ungroup')"
      >
        <Ungroup class="w-4 h-4" />
      </button>
    </div>

    <!-- Edit Section -->
    <div class="toolbar-section flex items-center gap-1 px-3 border-r border-zinc-200 dark:border-zinc-700">
      <button
        class="toolbar-btn"
        :disabled="!canUndo"
        title="Undo (Ctrl+Z)"
        @click="emit('undo')"
      >
        <Undo2 class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        :disabled="!canRedo"
        title="Redo (Ctrl+Shift+Z)"
        @click="emit('redo')"
      >
        <Redo2 class="w-4 h-4" />
      </button>

      <div class="w-px h-4 bg-zinc-200 dark:bg-zinc-700 mx-1" />

      <button
        class="toolbar-btn"
        :disabled="!hasSelection"
        title="Duplicate (Ctrl+D)"
        @click="emit('duplicate')"
      >
        <Copy class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
        :disabled="!hasSelection"
        title="Delete (Del)"
        @click="emit('delete')"
      >
        <Trash2 class="w-4 h-4" />
      </button>
    </div>

    <!-- View Section -->
    <div class="toolbar-section flex items-center gap-1 pl-3">
      <button
        class="toolbar-btn"
        :class="{ 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600': gridEnabled }"
        title="Toggle Grid"
        @click="emit('toggle:grid')"
      >
        <Grid3X3 class="w-4 h-4" />
      </button>

      <button
        class="toolbar-btn"
        :class="{ 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600': snapEnabled }"
        title="Snap to Grid"
        @click="emit('toggle:snap')"
      >
        <Layers class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>

<style scoped>
@reference "tailwindcss";

.toolbar-btn {
  @apply flex items-center justify-center w-8 h-8 rounded-md text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors;
}

.toolbar-btn:disabled {
  @apply opacity-40 cursor-not-allowed hover:bg-transparent;
}

.toolbar-btn.active {
  @apply bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600;
}
</style>
