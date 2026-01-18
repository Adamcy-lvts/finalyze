<script setup lang="ts">
/**
 * FontSelector - Font family selection component for the WYSIWYG editor
 * Features: common fonts, font preview, search functionality
 */
import { ref, computed, watch } from 'vue';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-vue-next';

interface Props {
  modelValue: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const searchQuery = ref('');

// Common font families grouped by category
const fontGroups = {
  'Sans Serif': [
    'Arial',
    'Helvetica',
    'Verdana',
    'Tahoma',
    'Trebuchet MS',
    'Segoe UI',
    'Open Sans',
    'Roboto',
    'Lato',
    'Montserrat',
    'Source Sans Pro',
    'Nunito',
  ],
  'Serif': [
    'Times New Roman',
    'Georgia',
    'Garamond',
    'Palatino Linotype',
    'Book Antiqua',
    'Cambria',
    'Merriweather',
    'Playfair Display',
    'Libre Baskerville',
  ],
  'Monospace': [
    'Courier New',
    'Consolas',
    'Monaco',
    'Menlo',
    'Source Code Pro',
    'Fira Code',
    'JetBrains Mono',
  ],
  'Display': [
    'Impact',
    'Comic Sans MS',
    'Brush Script MT',
    'Pacifico',
    'Lobster',
    'Oswald',
    'Anton',
  ],
};

// Flatten all fonts for search
const allFonts = Object.values(fontGroups).flat();

// Filter fonts based on search
const filteredFontGroups = computed(() => {
  if (!searchQuery.value.trim()) {
    return fontGroups;
  }

  const query = searchQuery.value.toLowerCase();
  const result: Record<string, string[]> = {};

  for (const [group, fonts] of Object.entries(fontGroups)) {
    const filtered = fonts.filter((font) =>
      font.toLowerCase().includes(query)
    );
    if (filtered.length > 0) {
      result[group] = filtered;
    }
  }

  return result;
});

// Handle selection
function handleSelect(value: string) {
  emit('update:modelValue', value);
}

// Current value for display
const currentValue = computed(() => props.modelValue || 'Arial');
</script>

<template>
  <Select :model-value="currentValue" @update:model-value="handleSelect">
    <SelectTrigger class="w-40 h-8 text-sm">
      <SelectValue :placeholder="currentValue">
        <span :style="{ fontFamily: currentValue }">{{ currentValue }}</span>
      </SelectValue>
    </SelectTrigger>

    <SelectContent class="max-h-80">
      <!-- Search Input -->
      <div class="p-2 border-b">
        <div class="relative">
          <Search class="absolute left-2 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            placeholder="Search fonts..."
            class="h-8 pl-8 text-sm"
            @click.stop
          />
        </div>
      </div>

      <!-- Font Groups -->
      <div class="max-h-60 overflow-y-auto">
        <template v-for="(fonts, groupName) in filteredFontGroups" :key="groupName">
          <SelectGroup>
            <SelectLabel class="text-xs text-muted-foreground px-2 py-1.5">
              {{ groupName }}
            </SelectLabel>
            <SelectItem
              v-for="font in fonts"
              :key="font"
              :value="font"
              class="py-2"
            >
              <span :style="{ fontFamily: font }">{{ font }}</span>
            </SelectItem>
          </SelectGroup>
        </template>

        <!-- No results -->
        <div
          v-if="Object.keys(filteredFontGroups).length === 0"
          class="py-6 text-center text-sm text-muted-foreground"
        >
          No fonts found
        </div>
      </div>
    </SelectContent>
  </Select>
</template>
