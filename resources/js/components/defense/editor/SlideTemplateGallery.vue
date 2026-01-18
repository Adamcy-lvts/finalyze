<script setup lang="ts">
/**
 * SlideTemplateGallery - Template picker for new slides
 * Displays pre-built slide layouts organized by category
 */

import { ref, computed } from 'vue';
import {
  Layers,
  FileText,
  BarChart3,
  GitCompare,
  ImageIcon,
  PartyPopper,
  X,
  Plus,
  Layout,
} from 'lucide-vue-next';
import { useSlideTemplates } from '@/composables/editor/useSlideTemplates';
import type { SlideTemplate, TemplateCategory } from '@/types/slideTemplates';
import type { WysiwygSlide } from '@/types/wysiwyg';

interface Props {
  open: boolean;
}

interface Emits {
  (e: 'close'): void;
  (e: 'select', slide: WysiwygSlide): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { templateGroups, createSlideFromTemplate, createBlankSlide } = useSlideTemplates();

const activeCategory = ref<TemplateCategory | 'all'>('all');

const categoryIcons: Record<TemplateCategory, any> = {
  title: Layers,
  content: FileText,
  comparison: GitCompare,
  data: BarChart3,
  media: ImageIcon,
  ending: PartyPopper,
};

const filteredGroups = computed(() => {
  if (activeCategory.value === 'all') {
    return templateGroups.value;
  }
  return templateGroups.value.filter(g => g.category === activeCategory.value);
});

function selectTemplate(template: SlideTemplate) {
  const slide = createSlideFromTemplate(template.id);
  if (slide) {
    emit('select', slide);
    emit('close');
  }
}

function selectBlank() {
  emit('select', createBlankSlide());
  emit('close');
}

function getPreviewStyle(template: SlideTemplate) {
  return {
    backgroundColor: template.backgroundColor || '#FFFFFF',
  };
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
        @click.self="emit('close')"
      >
        <div
          class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[85vh] flex flex-col overflow-hidden animate-in zoom-in-95 duration-200"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-200 dark:border-zinc-800">
            <div class="flex items-center gap-3">
              <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                <Layout class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
              </div>
              <div>
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                  Choose a Layout
                </h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                  Select a template to start with
                </p>
              </div>
            </div>
            <button
              class="p-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors"
              @click="emit('close')"
            >
              <X class="w-5 h-5 text-zinc-500" />
            </button>
          </div>

          <!-- Category Tabs -->
          <div class="flex items-center gap-2 px-6 py-3 border-b border-zinc-200 dark:border-zinc-800 overflow-x-auto">
            <button
              class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors whitespace-nowrap"
              :class="activeCategory === 'all'
                ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
              @click="activeCategory = 'all'"
            >
              All Templates
            </button>
            <button
              v-for="group in templateGroups"
              :key="group.category"
              class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg transition-colors whitespace-nowrap"
              :class="activeCategory === group.category
                ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-800'"
              @click="activeCategory = group.category"
            >
              <component :is="categoryIcons[group.category]" class="w-4 h-4" />
              {{ group.label }}
            </button>
          </div>

          <!-- Template Grid -->
          <div class="flex-1 overflow-y-auto p-6">
            <!-- Blank Slide Option -->
            <div class="mb-8">
              <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-3">
                Start Fresh
              </h3>
              <button
                class="group relative w-48 aspect-video rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-700 hover:border-indigo-500 dark:hover:border-indigo-500 transition-all overflow-hidden"
                @click="selectBlank"
              >
                <div class="absolute inset-0 flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-800/50 group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20 transition-colors">
                  <Plus class="w-8 h-8 text-zinc-400 group-hover:text-indigo-500 transition-colors mb-2" />
                  <span class="text-sm font-medium text-zinc-500 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                    Blank Slide
                  </span>
                </div>
              </button>
            </div>

            <!-- Template Categories -->
            <div v-for="group in filteredGroups" :key="group.category" class="mb-8 last:mb-0">
              <div class="flex items-center gap-2 mb-3">
                <component :is="categoryIcons[group.category]" class="w-4 h-4 text-zinc-500" />
                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                  {{ group.label }}
                </h3>
                <span class="text-xs text-zinc-400">
                  {{ group.description }}
                </span>
              </div>

              <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                <button
                  v-for="template in group.templates"
                  :key="template.id"
                  class="group relative aspect-video rounded-xl border border-zinc-200 dark:border-zinc-700 hover:border-indigo-500 dark:hover:border-indigo-500 hover:ring-2 hover:ring-indigo-500/20 transition-all overflow-hidden"
                  @click="selectTemplate(template)"
                >
                  <!-- Template Preview -->
                  <div
                    class="absolute inset-0 p-2"
                    :style="getPreviewStyle(template)"
                  >
                    <!-- Simplified preview of template elements -->
                    <div
                      v-for="(el, idx) in template.elements.slice(0, 5)"
                      :key="idx"
                      class="absolute"
                      :style="{
                        left: `${el.x}%`,
                        top: `${el.y}%`,
                        width: `${el.width}%`,
                        height: `${el.height}%`,
                      }"
                    >
                      <div
                        v-if="el.type === 'text'"
                        class="w-full h-full rounded-sm"
                        :style="{
                          backgroundColor: el.text?.color ? `${el.text.color}20` : '#00000010',
                        }"
                      />
                      <div
                        v-else-if="el.type === 'shape'"
                        class="w-full h-full rounded-sm"
                        :style="{
                          backgroundColor: el.fill || '#E5E7EB',
                          borderRadius: el.shapeType === 'circle' ? '50%' : undefined,
                        }"
                      />
                      <div
                        v-else
                        class="w-full h-full bg-zinc-200 dark:bg-zinc-700 rounded-sm"
                      />
                    </div>
                  </div>

                  <!-- Hover Overlay -->
                  <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="absolute bottom-0 left-0 right-0 p-2">
                      <p class="text-xs font-medium text-white truncate">
                        {{ template.name }}
                      </p>
                    </div>
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
