<script setup lang="ts">
/**
 * PresentationMode - Fullscreen presentation with transitions
 * Supports keyboard navigation, touch gestures, and slide transitions
 */

import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import {
  ChevronLeft,
  ChevronRight,
  X,
  Monitor,
  Settings,
} from 'lucide-vue-next';
import type { WysiwygSlide } from '@/types/wysiwyg';
import { usePresentation, transitionStyles, type TransitionType } from '@/composables/editor/usePresentation';

interface Props {
  slides: WysiwygSlide[];
  initialSlideIndex?: number;
  showControls?: boolean;
}

interface Emits {
  (e: 'close'): void;
  (e: 'slide-change', index: number): void;
  (e: 'open-presenter-view'): void;
}

const props = withDefaults(defineProps<Props>(), {
  initialSlideIndex: 0,
  showControls: true,
});

const emit = defineEmits<Emits>();

// Presentation composable
const presentation = usePresentation({
  slides: computed(() => props.slides),
  initialSlideIndex: props.initialSlideIndex,
  defaultTransition: { type: 'fade', duration: 300 },
});

// UI state
const showUI = ref(true);
const showSettings = ref(false);
const isTransitioning = ref(false);
let hideUITimeout: ReturnType<typeof setTimeout> | null = null;

// Slide container ref
const slideContainerRef = ref<HTMLDivElement | null>(null);

// Available transitions
const transitions: { value: TransitionType; label: string }[] = [
  { value: 'none', label: 'None' },
  { value: 'fade', label: 'Fade' },
  { value: 'slide-left', label: 'Slide' },
  { value: 'zoom', label: 'Zoom' },
  { value: 'flip', label: 'Flip' },
];

// Touch handling
let touchStartX = 0;
let touchStartY = 0;

function handleTouchStart(e: TouchEvent) {
  touchStartX = e.touches[0].clientX;
  touchStartY = e.touches[0].clientY;
}

function handleTouchEnd(e: TouchEvent) {
  const touchEndX = e.changedTouches[0].clientX;
  const touchEndY = e.changedTouches[0].clientY;
  const deltaX = touchEndX - touchStartX;
  const deltaY = touchEndY - touchStartY;

  // Require minimum swipe distance and more horizontal than vertical
  if (Math.abs(deltaX) > 50 && Math.abs(deltaX) > Math.abs(deltaY)) {
    if (deltaX < 0) {
      navigateNext();
    } else {
      navigatePrevious();
    }
  }
}

// Navigation with transitions
async function navigateNext() {
  if (!presentation.canGoNext.value || isTransitioning.value) return;

  isTransitioning.value = true;
  presentation.nextSlideNav();

  await new Promise(resolve => setTimeout(resolve, presentation.transition.value.duration));
  isTransitioning.value = false;
}

async function navigatePrevious() {
  if (!presentation.canGoPrevious.value || isTransitioning.value) return;

  isTransitioning.value = true;
  presentation.previousSlideNav();

  await new Promise(resolve => setTimeout(resolve, presentation.transition.value.duration));
  isTransitioning.value = false;
}

// Keyboard navigation
function handleKeydown(e: KeyboardEvent) {
  // Reset UI visibility
  showUI.value = true;
  scheduleHideUI();

  switch (e.key) {
    case 'ArrowRight':
    case 'ArrowDown':
    case ' ':
    case 'PageDown':
    case 'Enter':
      e.preventDefault();
      navigateNext();
      break;
    case 'ArrowLeft':
    case 'ArrowUp':
    case 'PageUp':
    case 'Backspace':
      e.preventDefault();
      navigatePrevious();
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
    case 'p':
    case 'P':
      e.preventDefault();
      emit('open-presenter-view');
      break;
  }
}

// Mouse movement shows UI
function handleMouseMove() {
  showUI.value = true;
  scheduleHideUI();
}

function scheduleHideUI() {
  if (hideUITimeout) {
    clearTimeout(hideUITimeout);
  }
  hideUITimeout = setTimeout(() => {
    showUI.value = false;
    showSettings.value = false;
  }, 3000);
}

// Request fullscreen
async function enterFullscreen() {
  try {
    await document.documentElement.requestFullscreen();
  } catch {
    // Fullscreen not supported or denied
  }
}

// Exit fullscreen on close
async function exitFullscreen() {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
    }
  } catch {
    // Already exited or not in fullscreen
  }
}

// Update transition
function setTransitionType(type: TransitionType) {
  presentation.setTransition({ type });
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
  presentation.startPresentation(props.initialSlideIndex);
  enterFullscreen();
  scheduleHideUI();
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
  if (hideUITimeout) {
    clearTimeout(hideUITimeout);
  }
  exitFullscreen();
});
</script>

<template>
  <div
    class="presentation-mode fixed inset-0 z-50 bg-black"
    @mousemove="handleMouseMove"
    @touchstart="handleTouchStart"
    @touchend="handleTouchEnd"
  >
    <!-- Transition styles -->
    <component :is="'style'" v-html="transitionStyles" />

    <!-- Slide Display -->
    <div
      ref="slideContainerRef"
      class="absolute inset-0 flex items-center justify-center"
      :style="{ '--transition-duration': `${presentation.transition.value.duration}ms` }"
    >
      <div
        v-if="presentation.currentSlide.value"
        :key="presentation.currentSlideIndex.value"
        class="slide-display aspect-video bg-white shadow-2xl overflow-hidden"
        :class="presentation.getTransitionClasses().enter"
        :style="{
          backgroundColor: presentation.currentSlide.value.backgroundColor || '#FFFFFF',
          maxWidth: '95vw',
          maxHeight: '95vh',
        }"
      >
        <!-- Render slide elements -->
        <div class="relative w-full h-full">
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
              class="w-full h-full overflow-hidden whitespace-pre-wrap"
              :style="{
                fontFamily: element.text.fontFamily,
                fontSize: `${element.text.fontSize}px`,
                fontWeight: element.text.fontWeight,
                fontStyle: element.text.fontStyle,
                textDecoration: element.text.textDecoration || 'none',
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
                              element.shape?.shapeType === 'rounded-rectangle' ? '12px' : '0',
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
        </div>
      </div>
    </div>

    <!-- Controls Overlay -->
    <transition name="fade">
      <div v-if="showUI && showControls" class="controls-overlay">
        <!-- Top bar -->
        <div class="absolute top-0 left-0 right-0 p-4 flex items-center justify-between bg-gradient-to-b from-black/50 to-transparent">
          <!-- Slide counter -->
          <div class="text-white/80 text-sm font-medium">
            {{ presentation.currentSlideIndex.value + 1 }} / {{ presentation.totalSlides.value }}
          </div>

          <!-- Right controls -->
          <div class="flex items-center gap-2">
            <!-- Settings dropdown -->
            <div class="relative">
              <button
                class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                title="Settings"
                @click="showSettings = !showSettings"
              >
                <Settings class="w-5 h-5" />
              </button>

              <!-- Settings panel -->
              <div
                v-if="showSettings"
                class="absolute right-0 top-full mt-2 w-48 bg-zinc-800 rounded-lg shadow-xl overflow-hidden"
              >
                <div class="px-3 py-2 border-b border-zinc-700 text-xs text-zinc-400 uppercase">
                  Transition
                </div>
                <div class="p-2">
                  <button
                    v-for="t in transitions"
                    :key="t.value"
                    class="w-full px-3 py-2 text-left text-sm rounded hover:bg-zinc-700 transition-colors"
                    :class="presentation.transition.value.type === t.value ? 'text-indigo-400 bg-zinc-700' : 'text-white'"
                    @click="setTransitionType(t.value)"
                  >
                    {{ t.label }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Presenter view button -->
            <button
              class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
              title="Presenter View (P)"
              @click="emit('open-presenter-view')"
            >
              <Monitor class="w-5 h-5" />
            </button>

            <!-- Close button -->
            <button
              class="p-2 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
              title="Exit (Esc)"
              @click="emit('close')"
            >
              <X class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Bottom bar -->
        <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/50 to-transparent">
          <!-- Progress bar -->
          <div class="h-1 bg-white/20 rounded-full overflow-hidden mb-4">
            <div
              class="h-full bg-white transition-all duration-300"
              :style="{ width: `${presentation.progress.value}%` }"
            />
          </div>

          <!-- Navigation -->
          <div class="flex items-center justify-center gap-4">
            <button
              class="p-3 text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
              :disabled="!presentation.canGoPrevious.value"
              @click="navigatePrevious"
            >
              <ChevronLeft class="w-8 h-8" />
            </button>

            <button
              class="p-3 text-white/80 hover:text-white hover:bg-white/10 rounded-full transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
              :disabled="!presentation.canGoNext.value"
              @click="navigateNext"
            >
              <ChevronRight class="w-8 h-8" />
            </button>
          </div>
        </div>

        <!-- Side navigation hints -->
        <button
          v-if="presentation.canGoPrevious.value"
          class="absolute left-0 top-1/2 -translate-y-1/2 w-20 h-full opacity-0 hover:opacity-100 transition-opacity bg-gradient-to-r from-black/30 to-transparent flex items-center justify-start pl-4"
          @click="navigatePrevious"
        >
          <ChevronLeft class="w-8 h-8 text-white/60" />
        </button>

        <button
          v-if="presentation.canGoNext.value"
          class="absolute right-0 top-1/2 -translate-y-1/2 w-20 h-full opacity-0 hover:opacity-100 transition-opacity bg-gradient-to-l from-black/30 to-transparent flex items-center justify-end pr-4"
          @click="navigateNext"
        >
          <ChevronRight class="w-8 h-8 text-white/60" />
        </button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.presentation-mode {
  cursor: none;
}

.presentation-mode:hover {
  cursor: default;
}

.slide-display {
  width: 100%;
  height: 100%;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
