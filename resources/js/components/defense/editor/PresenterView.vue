<script setup lang="ts">
/**
 * PresenterView - Presenter mode with dual display
 * Shows current slide, speaker notes, timer, and next slide preview
 */

import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import {
  Play,
  Pause,
  RotateCcw,
  ChevronLeft,
  ChevronRight,
  X,
  Maximize2,
  Monitor,
  Clock,
  StickyNote,
} from 'lucide-vue-next';
import type { WysiwygSlide } from '@/types/wysiwyg';
import { usePresentation, type TransitionType } from '@/composables/editor/usePresentation';

interface Props {
  slides: WysiwygSlide[];
  initialSlideIndex?: number;
}

interface Emits {
  (e: 'close'): void;
  (e: 'slide-change', index: number): void;
}

const props = withDefaults(defineProps<Props>(), {
  initialSlideIndex: 0,
});

const emit = defineEmits<Emits>();

// Presentation composable
const presentation = usePresentation({
  slides: computed(() => props.slides),
  initialSlideIndex: props.initialSlideIndex,
});

// Local state
const showNotes = ref(true);
const notesExpanded = ref(false);

// Current slide notes
const currentNotes = computed(() => {
  return presentation.currentSlide.value?.speaker_notes || 'No speaker notes for this slide.';
});

// Keyboard navigation
function handleKeydown(e: KeyboardEvent) {
  switch (e.key) {
    case 'ArrowRight':
    case 'ArrowDown':
    case ' ':
    case 'PageDown':
      e.preventDefault();
      presentation.nextSlideNav();
      break;
    case 'ArrowLeft':
    case 'ArrowUp':
    case 'PageUp':
      e.preventDefault();
      presentation.previousSlideNav();
      break;
    case 'Home':
      e.preventDefault();
      presentation.firstSlide();
      break;
    case 'End':
      e.preventDefault();
      presentation.lastSlide();
      break;
    case 'Escape':
      e.preventDefault();
      emit('close');
      break;
  }
}

// Toggle timer
function toggleTimer() {
  if (presentation.isPaused.value) {
    presentation.startTimer();
  } else {
    presentation.pauseTimer();
  }
}

// Emit slide change
watch(
  () => presentation.currentSlideIndex.value,
  (index) => {
    emit('slide-change', index);
  }
);

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  presentation.startTimer();
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  presentation.pauseTimer();
});
</script>

<template>
  <div class="presenter-view fixed inset-0 z-50 bg-zinc-900 flex">
    <!-- Left Panel: Current Slide + Notes -->
    <div class="flex-1 flex flex-col p-4 border-r border-zinc-700">
      <!-- Current Slide -->
      <div class="flex-1 flex items-center justify-center bg-black rounded-lg overflow-hidden">
        <div
          class="slide-container aspect-video bg-white rounded shadow-2xl overflow-hidden"
          :style="{ backgroundColor: presentation.currentSlide.value?.backgroundColor || '#FFFFFF' }"
        >
          <!-- Render current slide elements -->
          <div class="relative w-full h-full">
            <template v-if="presentation.currentSlide.value">
              <div
                v-for="element in presentation.currentSlide.value.elements"
                :key="element.id"
                class="absolute"
                :style="{
                  left: `${element.x}%`,
                  top: `${element.y}%`,
                  width: `${element.width}%`,
                  height: `${element.height}%`,
                  transform: `rotate(${element.rotation || 0}deg)`,
                  opacity: element.opacity ?? 1,
                  zIndex: element.zIndex || 0,
                }"
              >
                <!-- Text element -->
                <div
                  v-if="element.type === 'text' && element.text"
                  class="w-full h-full overflow-hidden"
                  :style="{
                    fontFamily: element.text.fontFamily,
                    fontSize: `${element.text.fontSize * 0.8}px`,
                    fontWeight: element.text.fontWeight,
                    fontStyle: element.text.fontStyle,
                    color: element.text.color,
                    textAlign: element.text.textAlign,
                    lineHeight: element.text.lineHeight,
                  }"
                >
                  {{ element.text.content }}
                </div>

                <!-- Shape element -->
                <div
                  v-else-if="element.type === 'shape'"
                  class="w-full h-full"
                  :style="{
                    backgroundColor: element.fill,
                    border: element.stroke ? `${element.strokeWidth || 1}px solid ${element.stroke}` : 'none',
                    borderRadius: element.shape?.shapeType === 'circle' ? '50%' :
                                  element.shape?.shapeType === 'rounded-rectangle' ? '8px' : '0',
                  }"
                />

                <!-- Image element -->
                <img
                  v-else-if="element.type === 'image' && element.image?.url"
                  :src="element.image.url"
                  class="w-full h-full"
                  :style="{ objectFit: element.image.fit || 'contain' }"
                  alt=""
                />
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Speaker Notes -->
      <div
        v-if="showNotes"
        class="mt-4 bg-zinc-800 rounded-lg overflow-hidden"
        :class="notesExpanded ? 'flex-1' : 'h-32'"
      >
        <div class="flex items-center justify-between px-4 py-2 border-b border-zinc-700">
          <div class="flex items-center gap-2 text-zinc-300">
            <StickyNote class="w-4 h-4" />
            <span class="text-sm font-medium">Speaker Notes</span>
          </div>
          <button
            class="p-1 text-zinc-400 hover:text-white transition-colors"
            @click="notesExpanded = !notesExpanded"
          >
            <Maximize2 class="w-4 h-4" />
          </button>
        </div>
        <div class="p-4 overflow-y-auto h-full text-zinc-300 text-sm whitespace-pre-wrap">
          {{ currentNotes }}
        </div>
      </div>
    </div>

    <!-- Right Panel: Controls + Next Slide -->
    <div class="w-80 flex flex-col p-4">
      <!-- Close button -->
      <div class="flex justify-end mb-4">
        <button
          class="p-2 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded-lg transition-colors"
          title="Close presenter view (Esc)"
          @click="emit('close')"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Timer -->
      <div class="bg-zinc-800 rounded-lg p-4 mb-4">
        <div class="flex items-center gap-2 text-zinc-400 mb-2">
          <Clock class="w-4 h-4" />
          <span class="text-sm">Elapsed Time</span>
        </div>
        <div class="text-4xl font-mono text-white mb-3">
          {{ presentation.formattedTime.value }}
        </div>
        <div class="flex gap-2">
          <button
            class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded transition-colors"
            @click="toggleTimer"
          >
            <Play v-if="presentation.isPaused.value" class="w-4 h-4" />
            <Pause v-else class="w-4 h-4" />
            {{ presentation.isPaused.value ? 'Start' : 'Pause' }}
          </button>
          <button
            class="px-3 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded transition-colors"
            title="Reset timer"
            @click="presentation.resetTimer"
          >
            <RotateCcw class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Slide Navigation -->
      <div class="bg-zinc-800 rounded-lg p-4 mb-4">
        <div class="flex items-center justify-between mb-3">
          <button
            class="p-2 bg-zinc-700 hover:bg-zinc-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded transition-colors"
            :disabled="!presentation.canGoPrevious.value"
            @click="presentation.previousSlideNav"
          >
            <ChevronLeft class="w-5 h-5" />
          </button>

          <div class="text-center">
            <div class="text-2xl font-bold text-white">
              {{ presentation.currentSlideIndex.value + 1 }}
            </div>
            <div class="text-xs text-zinc-400">
              of {{ presentation.totalSlides.value }}
            </div>
          </div>

          <button
            class="p-2 bg-zinc-700 hover:bg-zinc-600 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded transition-colors"
            :disabled="!presentation.canGoNext.value"
            @click="presentation.nextSlideNav"
          >
            <ChevronRight class="w-5 h-5" />
          </button>
        </div>

        <!-- Progress bar -->
        <div class="h-1 bg-zinc-700 rounded-full overflow-hidden">
          <div
            class="h-full bg-indigo-500 transition-all duration-300"
            :style="{ width: `${presentation.progress.value}%` }"
          />
        </div>
      </div>

      <!-- Next Slide Preview -->
      <div class="flex-1 bg-zinc-800 rounded-lg overflow-hidden">
        <div class="px-4 py-2 border-b border-zinc-700 text-zinc-400 text-sm">
          Next Slide
        </div>
        <div class="p-4 flex items-center justify-center h-full">
          <template v-if="presentation.nextSlide.value">
            <div
              class="aspect-video w-full bg-white rounded shadow-lg overflow-hidden"
              :style="{ backgroundColor: presentation.nextSlide.value.backgroundColor || '#FFFFFF' }"
            >
              <!-- Simplified next slide preview -->
              <div class="relative w-full h-full transform scale-50 origin-top-left" style="width: 200%; height: 200%;">
                <div
                  v-for="element in presentation.nextSlide.value.elements"
                  :key="element.id"
                  class="absolute"
                  :style="{
                    left: `${element.x}%`,
                    top: `${element.y}%`,
                    width: `${element.width}%`,
                    height: `${element.height}%`,
                    opacity: element.opacity ?? 1,
                    zIndex: element.zIndex || 0,
                  }"
                >
                  <div
                    v-if="element.type === 'text' && element.text"
                    class="w-full h-full overflow-hidden"
                    :style="{
                      fontFamily: element.text.fontFamily,
                      fontSize: `${element.text.fontSize * 0.4}px`,
                      fontWeight: element.text.fontWeight,
                      color: element.text.color,
                      textAlign: element.text.textAlign,
                    }"
                  >
                    {{ element.text.content }}
                  </div>
                  <div
                    v-else-if="element.type === 'shape'"
                    class="w-full h-full"
                    :style="{
                      backgroundColor: element.fill,
                      borderRadius: element.shape?.shapeType === 'circle' ? '50%' : '0',
                    }"
                  />
                </div>
              </div>
            </div>
          </template>
          <div v-else class="text-zinc-500 text-sm text-center">
            End of presentation
          </div>
        </div>
      </div>

      <!-- Keyboard hints -->
      <div class="mt-4 text-xs text-zinc-500 text-center">
        <span class="inline-block px-1.5 py-0.5 bg-zinc-800 rounded mr-1">&larr;</span>
        <span class="inline-block px-1.5 py-0.5 bg-zinc-800 rounded mr-2">&rarr;</span>
        Navigate
        <span class="mx-2">|</span>
        <span class="inline-block px-1.5 py-0.5 bg-zinc-800 rounded">Esc</span>
        Close
      </div>
    </div>
  </div>
</template>

<style scoped>
.presenter-view {
  font-family: system-ui, -apple-system, sans-serif;
}

.slide-container {
  max-width: 80%;
  max-height: 70vh;
}
</style>
