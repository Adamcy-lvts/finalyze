/**
 * usePresentation - Presentation mode composable
 * Handles presentation state, transitions, timer, and navigation
 */

import { ref, computed, watch, onUnmounted } from 'vue';
import type { WysiwygSlide } from '@/types/wysiwyg';

export type TransitionType = 'none' | 'fade' | 'slide-left' | 'slide-right' | 'slide-up' | 'zoom' | 'flip';

export interface TransitionConfig {
  type: TransitionType;
  duration: number; // in milliseconds
}

export interface UsePresentationOptions {
  slides: { value: WysiwygSlide[] };
  initialSlideIndex?: number;
  defaultTransition?: TransitionConfig;
}

export interface UsePresentationReturn {
  // State
  currentSlideIndex: ReturnType<typeof ref<number>>;
  isPresenting: ReturnType<typeof ref<boolean>>;
  isPresenterView: ReturnType<typeof ref<boolean>>;
  isPaused: ReturnType<typeof ref<boolean>>;
  transition: ReturnType<typeof ref<TransitionConfig>>;
  transitionDirection: ReturnType<typeof ref<'forward' | 'backward'>>;

  // Timer
  elapsedTime: ReturnType<typeof ref<number>>;
  formattedTime: ReturnType<typeof computed<string>>;

  // Computed
  currentSlide: ReturnType<typeof computed<WysiwygSlide | null>>;
  nextSlide: ReturnType<typeof computed<WysiwygSlide | null>>;
  previousSlide: ReturnType<typeof computed<WysiwygSlide | null>>;
  totalSlides: ReturnType<typeof computed<number>>;
  progress: ReturnType<typeof computed<number>>;
  canGoNext: ReturnType<typeof computed<boolean>>;
  canGoPrevious: ReturnType<typeof computed<boolean>>;

  // Methods
  startPresentation: (fromSlide?: number) => void;
  stopPresentation: () => void;
  togglePresenterView: () => void;
  goToSlide: (index: number) => void;
  nextSlideNav: () => void;
  previousSlideNav: () => void;
  firstSlide: () => void;
  lastSlide: () => void;
  startTimer: () => void;
  pauseTimer: () => void;
  resetTimer: () => void;
  setTransition: (config: Partial<TransitionConfig>) => void;

  // CSS transition classes
  getTransitionClasses: () => { enter: string; leave: string };
}

const DEFAULT_TRANSITION: TransitionConfig = {
  type: 'fade',
  duration: 300,
};

export function usePresentation(options: UsePresentationOptions): UsePresentationReturn {
  const { slides, initialSlideIndex = 0, defaultTransition = DEFAULT_TRANSITION } = options;

  // State
  const currentSlideIndex = ref(initialSlideIndex);
  const isPresenting = ref(false);
  const isPresenterView = ref(false);
  const isPaused = ref(true);
  const transition = ref<TransitionConfig>({ ...defaultTransition });
  const transitionDirection = ref<'forward' | 'backward'>('forward');

  // Timer state
  const elapsedTime = ref(0);
  let timerInterval: ReturnType<typeof setInterval> | null = null;

  // Computed
  const currentSlide = computed(() => {
    return slides.value[currentSlideIndex.value] || null;
  });

  const nextSlide = computed(() => {
    const nextIndex = currentSlideIndex.value + 1;
    return slides.value[nextIndex] || null;
  });

  const previousSlide = computed(() => {
    const prevIndex = currentSlideIndex.value - 1;
    return slides.value[prevIndex] || null;
  });

  const totalSlides = computed(() => slides.value.length);

  const progress = computed(() => {
    if (totalSlides.value === 0) return 0;
    return ((currentSlideIndex.value + 1) / totalSlides.value) * 100;
  });

  const canGoNext = computed(() => currentSlideIndex.value < totalSlides.value - 1);
  const canGoPrevious = computed(() => currentSlideIndex.value > 0);

  const formattedTime = computed(() => {
    const totalSeconds = Math.floor(elapsedTime.value / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
      return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  });

  // Methods
  function startPresentation(fromSlide?: number) {
    if (fromSlide !== undefined) {
      currentSlideIndex.value = Math.max(0, Math.min(fromSlide, totalSlides.value - 1));
    }
    isPresenting.value = true;
    startTimer();
  }

  function stopPresentation() {
    isPresenting.value = false;
    isPresenterView.value = false;
    pauseTimer();
  }

  function togglePresenterView() {
    isPresenterView.value = !isPresenterView.value;
  }

  function goToSlide(index: number) {
    const targetIndex = Math.max(0, Math.min(index, totalSlides.value - 1));
    transitionDirection.value = targetIndex > currentSlideIndex.value ? 'forward' : 'backward';
    currentSlideIndex.value = targetIndex;
  }

  function nextSlideNav() {
    if (canGoNext.value) {
      transitionDirection.value = 'forward';
      currentSlideIndex.value++;
    }
  }

  function previousSlideNav() {
    if (canGoPrevious.value) {
      transitionDirection.value = 'backward';
      currentSlideIndex.value--;
    }
  }

  function firstSlide() {
    transitionDirection.value = 'backward';
    currentSlideIndex.value = 0;
  }

  function lastSlide() {
    transitionDirection.value = 'forward';
    currentSlideIndex.value = totalSlides.value - 1;
  }

  function startTimer() {
    if (timerInterval) return;
    isPaused.value = false;
    timerInterval = setInterval(() => {
      elapsedTime.value += 1000;
    }, 1000);
  }

  function pauseTimer() {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
    isPaused.value = true;
  }

  function resetTimer() {
    pauseTimer();
    elapsedTime.value = 0;
  }

  function setTransition(config: Partial<TransitionConfig>) {
    transition.value = { ...transition.value, ...config };
  }

  function getTransitionClasses(): { enter: string; leave: string } {
    const type = transition.value.type;
    const direction = transitionDirection.value;

    switch (type) {
      case 'fade':
        return {
          enter: 'transition-fade-enter',
          leave: 'transition-fade-leave',
        };
      case 'slide-left':
      case 'slide-right':
        return {
          enter: direction === 'forward' ? 'transition-slide-enter-right' : 'transition-slide-enter-left',
          leave: direction === 'forward' ? 'transition-slide-leave-left' : 'transition-slide-leave-right',
        };
      case 'slide-up':
        return {
          enter: 'transition-slide-enter-up',
          leave: 'transition-slide-leave-down',
        };
      case 'zoom':
        return {
          enter: direction === 'forward' ? 'transition-zoom-enter' : 'transition-zoom-enter-reverse',
          leave: direction === 'forward' ? 'transition-zoom-leave' : 'transition-zoom-leave-reverse',
        };
      case 'flip':
        return {
          enter: 'transition-flip-enter',
          leave: 'transition-flip-leave',
        };
      case 'none':
      default:
        return {
          enter: '',
          leave: '',
        };
    }
  }

  // Cleanup
  onUnmounted(() => {
    if (timerInterval) {
      clearInterval(timerInterval);
    }
  });

  return {
    // State
    currentSlideIndex,
    isPresenting,
    isPresenterView,
    isPaused,
    transition,
    transitionDirection,

    // Timer
    elapsedTime,
    formattedTime,

    // Computed
    currentSlide,
    nextSlide,
    previousSlide,
    totalSlides,
    progress,
    canGoNext,
    canGoPrevious,

    // Methods
    startPresentation,
    stopPresentation,
    togglePresenterView,
    goToSlide,
    nextSlideNav,
    previousSlideNav,
    firstSlide,
    lastSlide,
    startTimer,
    pauseTimer,
    resetTimer,
    setTransition,
    getTransitionClasses,
  };
}

/**
 * CSS for transitions - should be added to global styles or component
 */
export const transitionStyles = `
/* Fade transition */
.transition-fade-enter {
  animation: fadeIn var(--transition-duration, 300ms) ease-out;
}
.transition-fade-leave {
  animation: fadeOut var(--transition-duration, 300ms) ease-in;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
@keyframes fadeOut {
  from { opacity: 1; }
  to { opacity: 0; }
}

/* Slide transitions */
.transition-slide-enter-right {
  animation: slideInRight var(--transition-duration, 300ms) ease-out;
}
.transition-slide-enter-left {
  animation: slideInLeft var(--transition-duration, 300ms) ease-out;
}
.transition-slide-leave-left {
  animation: slideOutLeft var(--transition-duration, 300ms) ease-in;
}
.transition-slide-leave-right {
  animation: slideOutRight var(--transition-duration, 300ms) ease-in;
}
.transition-slide-enter-up {
  animation: slideInUp var(--transition-duration, 300ms) ease-out;
}
.transition-slide-leave-down {
  animation: slideOutDown var(--transition-duration, 300ms) ease-in;
}

@keyframes slideInRight {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
@keyframes slideInLeft {
  from { transform: translateX(-100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}
@keyframes slideOutLeft {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(-100%); opacity: 0; }
}
@keyframes slideOutRight {
  from { transform: translateX(0); opacity: 1; }
  to { transform: translateX(100%); opacity: 0; }
}
@keyframes slideInUp {
  from { transform: translateY(100%); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
@keyframes slideOutDown {
  from { transform: translateY(0); opacity: 1; }
  to { transform: translateY(100%); opacity: 0; }
}

/* Zoom transition */
.transition-zoom-enter {
  animation: zoomIn var(--transition-duration, 300ms) ease-out;
}
.transition-zoom-leave {
  animation: zoomOut var(--transition-duration, 300ms) ease-in;
}
.transition-zoom-enter-reverse {
  animation: zoomInReverse var(--transition-duration, 300ms) ease-out;
}
.transition-zoom-leave-reverse {
  animation: zoomOutReverse var(--transition-duration, 300ms) ease-in;
}

@keyframes zoomIn {
  from { transform: scale(0.8); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes zoomOut {
  from { transform: scale(1); opacity: 1; }
  to { transform: scale(1.2); opacity: 0; }
}
@keyframes zoomInReverse {
  from { transform: scale(1.2); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}
@keyframes zoomOutReverse {
  from { transform: scale(1); opacity: 1; }
  to { transform: scale(0.8); opacity: 0; }
}

/* Flip transition */
.transition-flip-enter {
  animation: flipIn var(--transition-duration, 300ms) ease-out;
}
.transition-flip-leave {
  animation: flipOut var(--transition-duration, 300ms) ease-in;
}

@keyframes flipIn {
  from { transform: rotateY(-90deg); opacity: 0; }
  to { transform: rotateY(0); opacity: 1; }
}
@keyframes flipOut {
  from { transform: rotateY(0); opacity: 1; }
  to { transform: rotateY(90deg); opacity: 0; }
}
`;
