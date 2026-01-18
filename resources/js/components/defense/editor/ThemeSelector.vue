<script setup lang="ts">
/**
 * ThemeSelector - Theme selection component for the WYSIWYG editor
 * Shows a grid of theme cards with previews and allows theme application
 */
import { ref, computed, onMounted } from 'vue';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Palette, Check, Loader2 } from 'lucide-vue-next';
import { useTheme, type EditorTheme } from '@/composables/editor';

interface Props {
  currentThemeId?: string;
}

const props = withDefaults(defineProps<Props>(), {
  currentThemeId: 'modern',
});

const emit = defineEmits<{
  select: [themeId: string];
  apply: [themeId: string, applyToAll: boolean];
}>();

const isOpen = ref(false);
const hoveredTheme = ref<string | null>(null);
const applyToAllSlides = ref(true);

// Use theme composable
const themeManager = useTheme();

// Load themes on mount
onMounted(() => {
  themeManager.loadThemes();
});

// Computed
const themes = computed(() => themeManager.themes.value);
const isLoading = computed(() => themeManager.isLoading.value);

function selectTheme(themeId: string) {
  emit('select', themeId);
}

function applyTheme(themeId: string) {
  emit('apply', themeId, applyToAllSlides.value);
  isOpen.value = false;
}

function getThemePreviewStyle(theme: EditorTheme) {
  return {
    backgroundColor: theme.colors.background,
    borderColor: theme.colors.primary,
  };
}
</script>

<template>
  <Popover v-model:open="isOpen">
    <PopoverTrigger as-child>
      <Button variant="outline" size="sm" class="gap-2">
        <Palette class="w-4 h-4" />
        <span>Themes</span>
      </Button>
    </PopoverTrigger>

    <PopoverContent class="w-96 p-4" align="end">
      <div class="mb-3">
        <Label class="text-sm font-medium">Choose a Theme</Label>
        <p class="text-xs text-muted-foreground mt-1">
          Select a professional theme for your presentation
        </p>
      </div>

      <!-- Loading State -->
      <div v-if="isLoading" class="flex items-center justify-center py-8">
        <Loader2 class="w-6 h-6 animate-spin text-muted-foreground" />
      </div>

      <!-- Theme Grid -->
      <div v-else class="grid grid-cols-2 gap-3">
        <button
          v-for="theme in themes"
          :key="theme.id"
          type="button"
          class="relative group rounded-lg border-2 p-2 transition-all hover:shadow-md focus:outline-none focus:ring-2 focus:ring-ring"
          :class="{
            'border-primary ring-2 ring-primary/20': props.currentThemeId === theme.id,
            'border-gray-200 hover:border-gray-300': props.currentThemeId !== theme.id
          }"
          @mouseenter="hoveredTheme = theme.id"
          @mouseleave="hoveredTheme = null"
          @click="selectTheme(theme.id)"
        >
          <!-- Theme Preview -->
          <div
            class="aspect-video rounded overflow-hidden mb-2"
            :style="getThemePreviewStyle(theme)"
          >
            <!-- Mini slide preview -->
            <div class="w-full h-full p-2 flex flex-col">
              <!-- Title bar -->
              <div
                class="h-1.5 w-2/3 rounded mb-1"
                :style="{ backgroundColor: theme.colors.primary }"
              />
              <!-- Subtitle -->
              <div
                class="h-1 w-1/2 rounded mb-2"
                :style="{ backgroundColor: theme.colors.textSecondary }"
              />
              <!-- Content lines -->
              <div class="flex-1 space-y-1">
                <div
                  class="h-0.5 w-full rounded"
                  :style="{ backgroundColor: theme.colors.text + '40' }"
                />
                <div
                  class="h-0.5 w-4/5 rounded"
                  :style="{ backgroundColor: theme.colors.text + '40' }"
                />
                <div
                  class="h-0.5 w-3/4 rounded"
                  :style="{ backgroundColor: theme.colors.text + '40' }"
                />
              </div>
              <!-- Accent shape -->
              <div class="flex justify-end mt-1">
                <div
                  class="w-3 h-3 rounded-sm"
                  :style="{ backgroundColor: theme.colors.accent }"
                />
              </div>
            </div>
          </div>

          <!-- Theme Info -->
          <div class="text-left">
            <div class="flex items-center gap-1">
              <span class="text-sm font-medium">{{ theme.name }}</span>
              <Check
                v-if="props.currentThemeId === theme.id"
                class="w-3.5 h-3.5 text-primary"
              />
            </div>
            <p class="text-xs text-muted-foreground line-clamp-1">
              {{ theme.description }}
            </p>
          </div>

          <!-- Color Swatches -->
          <div class="flex gap-1 mt-2">
            <span
              class="w-4 h-4 rounded-full border border-white shadow-sm"
              :style="{ backgroundColor: theme.colors.primary }"
              :title="'Primary: ' + theme.colors.primary"
            />
            <span
              class="w-4 h-4 rounded-full border border-white shadow-sm"
              :style="{ backgroundColor: theme.colors.secondary }"
              :title="'Secondary: ' + theme.colors.secondary"
            />
            <span
              class="w-4 h-4 rounded-full border border-white shadow-sm"
              :style="{ backgroundColor: theme.colors.accent }"
              :title="'Accent: ' + theme.colors.accent"
            />
            <span
              class="w-4 h-4 rounded-full border border-gray-200 shadow-sm"
              :style="{ backgroundColor: theme.colors.background }"
              :title="'Background: ' + theme.colors.background"
            />
          </div>
        </button>
      </div>

      <!-- Apply Options -->
      <div class="mt-4 pt-4 border-t">
        <label class="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            v-model="applyToAllSlides"
            class="rounded border-gray-300 text-primary focus:ring-primary"
          />
          <span>Apply to all slides</span>
        </label>

        <div class="flex justify-end mt-3">
          <Button
            size="sm"
            :disabled="!props.currentThemeId"
            @click="applyTheme(props.currentThemeId)"
          >
            Apply Theme
          </Button>
        </div>
      </div>

      <!-- Theme Details (on hover) -->
      <div
        v-if="hoveredTheme && themes.find(t => t.id === hoveredTheme)"
        class="mt-3 pt-3 border-t text-xs text-muted-foreground"
      >
        <div class="flex items-center justify-between mb-1">
          <span class="font-medium">Fonts</span>
        </div>
        <div class="space-y-0.5">
          <div>
            Headings: {{ themes.find(t => t.id === hoveredTheme)?.fonts.heading.family }}
          </div>
          <div>
            Body: {{ themes.find(t => t.id === hoveredTheme)?.fonts.body.family }}
          </div>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
