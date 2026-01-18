<script setup lang="ts">
/**
 * ElementInspector - Properties panel for selected elements
 * Shows position, size, styling, and element-specific properties
 */

import { computed, ref } from 'vue';
import { ChevronDown, ChevronRight, Type, Palette, Move, RotateCw, StickyNote } from 'lucide-vue-next';
import type { WysiwygSlideElement, TextAlign } from '@/types/wysiwyg';
import ColorPicker from './ColorPicker.vue';
import FontSelector from './FontSelector.vue';

interface Props {
  element: WysiwygSlideElement | null;
  speakerNotes?: string;
}

interface Emits {
  (e: 'update', updates: Partial<WysiwygSlideElement>): void;
  (e: 'update:speakerNotes', notes: string): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Section expand state
const expandedSections = ref({
  position: true,
  appearance: true,
  text: true,
  notes: false,
});

const fontSizes = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72];

const textAlignOptions: { value: TextAlign; label: string }[] = [
  { value: 'left', label: 'Left' },
  { value: 'center', label: 'Center' },
  { value: 'right', label: 'Right' },
  { value: 'justify', label: 'Justify' },
];

// Computed
const hasElement = computed(() => props.element !== null);
const isText = computed(() => props.element?.type === 'text');
const isShape = computed(() => props.element?.type === 'shape');

// Update helpers
function updatePosition(key: 'x' | 'y', value: number) {
  emit('update', { [key]: value });
}

function updateSize(key: 'width' | 'height', value: number) {
  emit('update', { [key]: value });
}

function updateRotation(value: number) {
  emit('update', { rotation: value });
}

function updateOpacity(value: number) {
  emit('update', { opacity: value });
}

function updateFill(value: string) {
  emit('update', { fill: value });
}

function updateStroke(value: string) {
  emit('update', { stroke: value });
}

function updateStrokeWidth(value: number) {
  emit('update', { strokeWidth: value });
}

function updateText(key: string, value: any) {
  if (!props.element?.text) return;
  emit('update', {
    text: {
      ...props.element.text,
      [key]: value,
    },
  });
}

function toggleSection(section: keyof typeof expandedSections.value) {
  expandedSections.value[section] = !expandedSections.value[section];
}
</script>

<template>
  <div class="element-inspector w-64 bg-white dark:bg-zinc-900 border-l border-zinc-200 dark:border-zinc-800 overflow-y-auto">
    <!-- No selection state -->
    <div v-if="!hasElement" class="p-4 text-center text-zinc-500 dark:text-zinc-400">
      <p class="text-sm">Select an element to edit its properties</p>
    </div>

    <template v-else>
      <!-- Position & Size Section -->
      <div class="inspector-section">
        <button
          class="section-header"
          @click="toggleSection('position')"
        >
          <Move class="w-4 h-4" />
          <span>Position & Size</span>
          <ChevronDown v-if="expandedSections.position" class="w-4 h-4 ml-auto" />
          <ChevronRight v-else class="w-4 h-4 ml-auto" />
        </button>

        <div v-if="expandedSections.position" class="section-content">
          <!-- Position -->
          <div class="grid grid-cols-2 gap-2">
            <div class="input-group">
              <label class="input-label">X</label>
              <input
                type="number"
                class="input-field"
                :value="element?.x?.toFixed(1)"
                step="0.1"
                min="0"
                max="100"
                @input="updatePosition('x', parseFloat(($event.target as HTMLInputElement).value))"
              />
              <span class="input-unit">%</span>
            </div>

            <div class="input-group">
              <label class="input-label">Y</label>
              <input
                type="number"
                class="input-field"
                :value="element?.y?.toFixed(1)"
                step="0.1"
                min="0"
                max="100"
                @input="updatePosition('y', parseFloat(($event.target as HTMLInputElement).value))"
              />
              <span class="input-unit">%</span>
            </div>
          </div>

          <!-- Size -->
          <div class="grid grid-cols-2 gap-2 mt-2">
            <div class="input-group">
              <label class="input-label">Width</label>
              <input
                type="number"
                class="input-field"
                :value="element?.width?.toFixed(1)"
                step="0.1"
                min="1"
                max="100"
                @input="updateSize('width', parseFloat(($event.target as HTMLInputElement).value))"
              />
              <span class="input-unit">%</span>
            </div>

            <div class="input-group">
              <label class="input-label">Height</label>
              <input
                type="number"
                class="input-field"
                :value="element?.height?.toFixed(1)"
                step="0.1"
                min="1"
                max="100"
                @input="updateSize('height', parseFloat(($event.target as HTMLInputElement).value))"
              />
              <span class="input-unit">%</span>
            </div>
          </div>

          <!-- Rotation -->
          <div class="mt-2">
            <div class="input-group">
              <label class="input-label flex items-center gap-1">
                <RotateCw class="w-3 h-3" />
                Rotation
              </label>
              <input
                type="number"
                class="input-field"
                :value="element?.rotation?.toFixed(0)"
                step="1"
                min="0"
                max="360"
                @input="updateRotation(parseFloat(($event.target as HTMLInputElement).value))"
              />
              <span class="input-unit">deg</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Appearance Section -->
      <div class="inspector-section">
        <button
          class="section-header"
          @click="toggleSection('appearance')"
        >
          <Palette class="w-4 h-4" />
          <span>Appearance</span>
          <ChevronDown v-if="expandedSections.appearance" class="w-4 h-4 ml-auto" />
          <ChevronRight v-else class="w-4 h-4 ml-auto" />
        </button>

        <div v-if="expandedSections.appearance" class="section-content">
          <!-- Opacity -->
          <div class="input-group">
            <label class="input-label">Opacity</label>
            <input
              type="range"
              class="w-full"
              :value="element?.opacity ?? 1"
              min="0"
              max="1"
              step="0.05"
              @input="updateOpacity(parseFloat(($event.target as HTMLInputElement).value))"
            />
            <span class="text-xs text-zinc-500">{{ ((element?.opacity ?? 1) * 100).toFixed(0) }}%</span>
          </div>

          <!-- Fill Color (for shapes) -->
          <div v-if="isShape" class="input-group mt-2">
            <label class="input-label">Fill Color</label>
            <ColorPicker
              :model-value="element?.fill || '#E5E7EB'"
              show-opacity
              @update:model-value="updateFill"
            />
          </div>

          <!-- Stroke Color (for shapes) -->
          <div v-if="isShape" class="input-group mt-2">
            <label class="input-label">Border Color</label>
            <ColorPicker
              :model-value="element?.stroke || '#9CA3AF'"
              @update:model-value="updateStroke"
            />
          </div>

          <!-- Stroke Width (for shapes) -->
          <div v-if="isShape" class="input-group mt-2">
            <label class="input-label">Border Width</label>
            <input
              type="number"
              class="input-field"
              :value="element?.strokeWidth ?? 1"
              min="0"
              max="20"
              step="1"
              @input="updateStrokeWidth(parseInt(($event.target as HTMLInputElement).value))"
            />
            <span class="input-unit">px</span>
          </div>
        </div>
      </div>

      <!-- Text Section (only for text elements) -->
      <div v-if="isText && element?.text" class="inspector-section">
        <button
          class="section-header"
          @click="toggleSection('text')"
        >
          <Type class="w-4 h-4" />
          <span>Text</span>
          <ChevronDown v-if="expandedSections.text" class="w-4 h-4 ml-auto" />
          <ChevronRight v-else class="w-4 h-4 ml-auto" />
        </button>

        <div v-if="expandedSections.text" class="section-content">
          <!-- Font Family -->
          <div class="input-group">
            <label class="input-label">Font</label>
            <FontSelector
              :model-value="element.text.fontFamily"
              @update:model-value="(val) => updateText('fontFamily', val)"
            />
          </div>

          <!-- Font Size -->
          <div class="input-group mt-2">
            <label class="input-label">Size</label>
            <select
              class="input-field"
              :value="element.text.fontSize"
              @change="updateText('fontSize', parseInt(($event.target as HTMLSelectElement).value))"
            >
              <option v-for="size in fontSizes" :key="size" :value="size">
                {{ size }}px
              </option>
            </select>
          </div>

          <!-- Text Color -->
          <div class="input-group mt-2">
            <label class="input-label">Color</label>
            <ColorPicker
              :model-value="element.text.color"
              @update:model-value="(val) => updateText('color', val)"
            />
          </div>

          <!-- Text Align -->
          <div class="input-group mt-2">
            <label class="input-label">Alignment</label>
            <div class="flex gap-1">
              <button
                v-for="option in textAlignOptions"
                :key="option.value"
                class="flex-1 px-2 py-1 text-xs rounded border"
                :class="{
                  'bg-indigo-100 border-indigo-500 text-indigo-700': element.text.textAlign === option.value,
                  'bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600': element.text.textAlign !== option.value
                }"
                @click="updateText('textAlign', option.value)"
              >
                {{ option.label }}
              </button>
            </div>
          </div>

          <!-- Font Style -->
          <div class="input-group mt-2">
            <label class="input-label">Style</label>
            <div class="flex gap-1">
              <button
                class="flex-1 px-2 py-1 text-xs rounded border font-bold"
                :class="{
                  'bg-indigo-100 border-indigo-500 text-indigo-700': element.text.fontWeight === 'bold',
                  'bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600': element.text.fontWeight !== 'bold'
                }"
                @click="updateText('fontWeight', element.text.fontWeight === 'bold' ? 'normal' : 'bold')"
              >
                B
              </button>
              <button
                class="flex-1 px-2 py-1 text-xs rounded border italic"
                :class="{
                  'bg-indigo-100 border-indigo-500 text-indigo-700': element.text.fontStyle === 'italic',
                  'bg-white dark:bg-zinc-800 border-zinc-300 dark:border-zinc-600': element.text.fontStyle !== 'italic'
                }"
                @click="updateText('fontStyle', element.text.fontStyle === 'italic' ? 'normal' : 'italic')"
              >
                I
              </button>
            </div>
          </div>

          <!-- Line Height -->
          <div class="input-group mt-2">
            <label class="input-label">Line Height</label>
            <input
              type="number"
              class="input-field"
              :value="element.text.lineHeight"
              min="0.5"
              max="3"
              step="0.1"
              @input="updateText('lineHeight', parseFloat(($event.target as HTMLInputElement).value))"
            />
          </div>
        </div>
      </div>

      <!-- Speaker Notes Section -->
      <div class="inspector-section">
        <button
          class="section-header"
          @click="toggleSection('notes')"
        >
          <StickyNote class="w-4 h-4" />
          <span>Speaker Notes</span>
          <ChevronDown v-if="expandedSections.notes" class="w-4 h-4 ml-auto" />
          <ChevronRight v-else class="w-4 h-4 ml-auto" />
        </button>

        <div v-if="expandedSections.notes" class="section-content">
          <textarea
            class="w-full h-32 px-2 py-1.5 text-sm border border-zinc-300 dark:border-zinc-600 rounded bg-white dark:bg-zinc-800 resize-none"
            placeholder="Add speaker notes for this slide..."
            :value="speakerNotes || ''"
            @input="emit('update:speakerNotes', ($event.target as HTMLTextAreaElement).value)"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
@reference "tailwindcss";

.inspector-section {
  @apply border-b border-zinc-200 dark:border-zinc-800;
}

.section-header {
  @apply w-full flex items-center gap-2 px-3 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors;
}

.section-content {
  @apply px-3 pb-3;
}

.input-group {
  @apply relative;
}

.input-label {
  @apply block text-xs font-medium text-zinc-500 dark:text-zinc-400 mb-1;
}

.input-field {
  @apply w-full px-2 py-1.5 text-sm border border-zinc-300 dark:border-zinc-600 rounded bg-white dark:bg-zinc-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500;
}

.input-unit {
  @apply absolute right-2 top-1/2 transform -translate-y-1/2 text-xs text-zinc-400 mt-2.5;
}
</style>
