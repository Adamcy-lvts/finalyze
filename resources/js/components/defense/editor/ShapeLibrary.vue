<script setup lang="ts">
/**
 * ShapeLibrary - Shape selection component for the WYSIWYG editor
 * Shows available shapes in a preview grid with click to add
 */
import { computed } from 'vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Shapes } from 'lucide-vue-next';
import type { ShapeProperties } from '@/types/wysiwyg';

type ShapeType = ShapeProperties['shapeType'];

interface ShapeDefinition {
  type: ShapeType;
  name: string;
  icon: string; // SVG path or component name
}

const emit = defineEmits<{
  select: [shapeType: ShapeType];
}>();

// Shape definitions with SVG icons
const shapes: ShapeDefinition[] = [
  { type: 'rectangle', name: 'Rectangle', icon: 'rectangle' },
  { type: 'rounded-rectangle', name: 'Rounded Rectangle', icon: 'rounded-rectangle' },
  { type: 'circle', name: 'Circle', icon: 'circle' },
  { type: 'triangle', name: 'Triangle', icon: 'triangle' },
  { type: 'arrow', name: 'Arrow', icon: 'arrow' },
  { type: 'line', name: 'Line', icon: 'line' },
];

// Basic shapes category
const basicShapes = computed(() => shapes);

function selectShape(shapeType: ShapeType) {
  emit('select', shapeType);
}
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="ghost" size="sm" title="Add Shape">
        <Shapes class="w-4 h-4" />
        <span class="sr-only">Add Shape</span>
      </Button>
    </PopoverTrigger>

    <PopoverContent class="w-56 p-3" align="start">
      <Label class="mb-3 block text-sm font-medium">Basic Shapes</Label>

      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="shape in basicShapes"
          :key="shape.type"
          type="button"
          class="flex flex-col items-center justify-center p-2 rounded-md border border-gray-200 hover:border-primary hover:bg-accent transition-colors focus:outline-none focus:ring-2 focus:ring-ring"
          :title="shape.name"
          @click="selectShape(shape.type)"
        >
          <!-- SVG Icons for each shape -->
          <svg
            class="w-8 h-8 text-muted-foreground"
            viewBox="0 0 32 32"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
          >
            <!-- Rectangle -->
            <template v-if="shape.icon === 'rectangle'">
              <rect x="4" y="8" width="24" height="16" rx="0" fill="currentColor" fill-opacity="0.1" />
            </template>

            <!-- Rounded Rectangle -->
            <template v-else-if="shape.icon === 'rounded-rectangle'">
              <rect x="4" y="8" width="24" height="16" rx="4" fill="currentColor" fill-opacity="0.1" />
            </template>

            <!-- Circle -->
            <template v-else-if="shape.icon === 'circle'">
              <ellipse cx="16" cy="16" rx="12" ry="10" fill="currentColor" fill-opacity="0.1" />
            </template>

            <!-- Triangle -->
            <template v-else-if="shape.icon === 'triangle'">
              <polygon points="16,4 28,28 4,28" fill="currentColor" fill-opacity="0.1" />
            </template>

            <!-- Arrow -->
            <template v-else-if="shape.icon === 'arrow'">
              <line x1="4" y1="16" x2="22" y2="16" />
              <polyline points="18,10 24,16 18,22" fill="none" />
            </template>

            <!-- Line -->
            <template v-else-if="shape.icon === 'line'">
              <line x1="4" y1="28" x2="28" y2="4" />
            </template>
          </svg>
          <span class="text-xs text-muted-foreground mt-1">{{ shape.name }}</span>
        </button>
      </div>

      <!-- Tip -->
      <p class="mt-3 text-xs text-muted-foreground">
        Click a shape to add it to the slide
      </p>
    </PopoverContent>
  </Popover>
</template>
