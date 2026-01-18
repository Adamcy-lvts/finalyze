<script setup lang="ts">
/**
 * ColorPicker - Color selection component for the WYSIWYG editor
 * Features: theme colors, custom picker, recent colors, opacity slider
 */
import { ref, computed, watch } from 'vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pipette, Check } from 'lucide-vue-next';

interface Props {
  modelValue: string;
  label?: string;
  showOpacity?: boolean;
  size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
  label: '',
  showOpacity: false,
  size: 'md',
});

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const isOpen = ref(false);
const customColor = ref('#000000');
const opacity = ref(100);

// Parse hex color and opacity from value
function parseColor(value: string): { hex: string; opacity: number } {
  if (value.startsWith('rgba')) {
    const match = value.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
    if (match) {
      const r = parseInt(match[1]).toString(16).padStart(2, '0');
      const g = parseInt(match[2]).toString(16).padStart(2, '0');
      const b = parseInt(match[3]).toString(16).padStart(2, '0');
      const a = match[4] ? Math.round(parseFloat(match[4]) * 100) : 100;
      return { hex: `#${r}${g}${b}`, opacity: a };
    }
  }
  if (value.length === 9 && value.startsWith('#')) {
    // #RRGGBBAA format
    return {
      hex: value.substring(0, 7),
      opacity: Math.round((parseInt(value.substring(7), 16) / 255) * 100),
    };
  }
  return { hex: value || '#000000', opacity: 100 };
}

// Initialize from prop
watch(
  () => props.modelValue,
  (val) => {
    const parsed = parseColor(val);
    customColor.value = parsed.hex;
    opacity.value = parsed.opacity;
  },
  { immediate: true }
);

// Theme color palette
const themeColors = [
  // Row 1 - Primary shades
  ['#1E40AF', '#3B82F6', '#60A5FA', '#93C5FD', '#DBEAFE'],
  // Row 2 - Secondary shades
  ['#7C3AED', '#8B5CF6', '#A78BFA', '#C4B5FD', '#EDE9FE'],
  // Row 3 - Accent shades
  ['#059669', '#10B981', '#34D399', '#6EE7B7', '#D1FAE5'],
  // Row 4 - Warm shades
  ['#DC2626', '#EF4444', '#F87171', '#FCA5A5', '#FEE2E2'],
  // Row 5 - Neutral shades
  ['#111827', '#4B5563', '#9CA3AF', '#D1D5DB', '#F3F4F6'],
];

// Standard colors
const standardColors = [
  '#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF',
  '#FFFF00', '#FF00FF', '#00FFFF', '#FF8000', '#8000FF',
];

// Recent colors (stored in localStorage)
const recentColors = ref<string[]>([]);
const RECENT_COLORS_KEY = 'wysiwyg_recent_colors';

function loadRecentColors() {
  try {
    const stored = localStorage.getItem(RECENT_COLORS_KEY);
    if (stored) {
      recentColors.value = JSON.parse(stored);
    }
  } catch (e) {
    // Ignore localStorage errors
  }
}

function addRecentColor(color: string) {
  const hex = color.toUpperCase();
  recentColors.value = [hex, ...recentColors.value.filter((c) => c !== hex)].slice(0, 10);
  try {
    localStorage.setItem(RECENT_COLORS_KEY, JSON.stringify(recentColors.value));
  } catch (e) {
    // Ignore localStorage errors
  }
}

loadRecentColors();

// Format output color
function formatColor(hex: string, alpha: number): string {
  if (alpha >= 100) {
    return hex;
  }
  // Convert to RGBA
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgba(${r}, ${g}, ${b}, ${(alpha / 100).toFixed(2)})`;
}

// Select color
function selectColor(color: string) {
  customColor.value = color;
  const outputColor = formatColor(color, props.showOpacity ? opacity.value : 100);
  addRecentColor(color);
  emit('update:modelValue', outputColor);
}

// Handle custom color input
function onCustomColorChange() {
  const outputColor = formatColor(customColor.value, props.showOpacity ? opacity.value : 100);
  addRecentColor(customColor.value);
  emit('update:modelValue', outputColor);
}

// Handle opacity change
function onOpacityChange() {
  const outputColor = formatColor(customColor.value, opacity.value);
  emit('update:modelValue', outputColor);
}

// Computed styles
const swatchSize = computed(() => {
  switch (props.size) {
    case 'sm': return 'w-5 h-5';
    case 'lg': return 'w-8 h-8';
    default: return 'w-6 h-6';
  }
});

const currentColor = computed(() => props.modelValue || '#000000');
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-md border border-input bg-background px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
      >
        <span
          :class="swatchSize"
          class="rounded border border-gray-300 shadow-sm"
          :style="{ backgroundColor: currentColor }"
        />
        <span v-if="label" class="text-muted-foreground">{{ label }}</span>
      </button>
    </PopoverTrigger>

    <PopoverContent class="w-64 p-3" align="start">
      <!-- Theme Colors -->
      <div class="mb-3">
        <Label class="mb-2 block text-xs text-muted-foreground">Theme Colors</Label>
        <div class="space-y-1">
          <div v-for="(row, rowIdx) in themeColors" :key="rowIdx" class="flex gap-1">
            <button
              v-for="color in row"
              :key="color"
              type="button"
              class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-ring"
              :style="{ backgroundColor: color }"
              :title="color"
              @click="selectColor(color)"
            >
              <Check
                v-if="customColor.toUpperCase() === color.toUpperCase()"
                class="w-4 h-4 mx-auto"
                :class="rowIdx >= 3 && rowIdx < 4 ? 'text-white' : 'text-gray-800'"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- Standard Colors -->
      <div class="mb-3">
        <Label class="mb-2 block text-xs text-muted-foreground">Standard Colors</Label>
        <div class="flex flex-wrap gap-1">
          <button
            v-for="color in standardColors"
            :key="color"
            type="button"
            class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-ring"
            :style="{ backgroundColor: color }"
            :title="color"
            @click="selectColor(color)"
          >
            <Check
              v-if="customColor.toUpperCase() === color.toUpperCase()"
              class="w-4 h-4 mx-auto"
              :class="color === '#FFFFFF' || color === '#FFFF00' || color === '#00FFFF' ? 'text-gray-800' : 'text-white'"
            />
          </button>
        </div>
      </div>

      <!-- Recent Colors -->
      <div v-if="recentColors.length > 0" class="mb-3">
        <Label class="mb-2 block text-xs text-muted-foreground">Recent Colors</Label>
        <div class="flex flex-wrap gap-1">
          <button
            v-for="color in recentColors"
            :key="color"
            type="button"
            class="w-6 h-6 rounded border border-gray-200 hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-ring"
            :style="{ backgroundColor: color }"
            :title="color"
            @click="selectColor(color)"
          />
        </div>
      </div>

      <!-- Custom Color -->
      <div class="mb-3">
        <Label class="mb-2 block text-xs text-muted-foreground">Custom Color</Label>
        <div class="flex items-center gap-2">
          <div class="relative">
            <input
              type="color"
              v-model="customColor"
              class="w-8 h-8 cursor-pointer rounded border-0 p-0"
              @change="onCustomColorChange"
            />
            <Pipette class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-4 h-4 text-white pointer-events-none drop-shadow-md" />
          </div>
          <Input
            v-model="customColor"
            class="flex-1 h-8 text-xs font-mono uppercase"
            placeholder="#000000"
            maxlength="7"
            @change="onCustomColorChange"
          />
        </div>
      </div>

      <!-- Opacity Slider -->
      <div v-if="showOpacity">
        <Label class="mb-2 block text-xs text-muted-foreground">
          Opacity: {{ opacity }}%
        </Label>
        <div class="flex items-center gap-2">
          <input
            type="range"
            v-model.number="opacity"
            min="0"
            max="100"
            step="1"
            class="flex-1 h-2 rounded-lg appearance-none cursor-pointer bg-gray-200"
            @input="onOpacityChange"
          />
          <Input
            v-model.number="opacity"
            type="number"
            min="0"
            max="100"
            class="w-16 h-8 text-xs text-center"
            @change="onOpacityChange"
          />
        </div>
      </div>

      <!-- Apply Button -->
      <Button
        class="w-full mt-3"
        size="sm"
        @click="isOpen = false"
      >
        Apply
      </Button>
    </PopoverContent>
  </Popover>
</template>

<style scoped>
input[type="color"] {
  -webkit-appearance: none;
  appearance: none;
  border: none;
  width: 32px;
  height: 32px;
  cursor: pointer;
}

input[type="color"]::-webkit-color-swatch-wrapper {
  padding: 0;
}

input[type="color"]::-webkit-color-swatch {
  border: 1px solid #d1d5db;
  border-radius: 4px;
}

input[type="range"] {
  accent-color: #3b82f6;
}
</style>
