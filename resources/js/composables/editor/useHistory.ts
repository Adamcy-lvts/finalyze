/**
 * useHistory - Undo/Redo Composable
 * Implements stack-based history management for the slide editor
 */

import { ref, computed, watch, type Ref } from 'vue';
import type { WysiwygSlide, HistoryState, HistoryManager } from '@/types/wysiwyg';

export interface UseHistoryOptions {
  slides: Ref<WysiwygSlide[]>;
  activeSlideIndex: Ref<number>;
  maxSize?: number;
  debounceMs?: number;
  onRestore?: (state: HistoryState) => void;
}

export function useHistory(options: UseHistoryOptions) {
  const {
    slides,
    activeSlideIndex,
    maxSize = 50,
    debounceMs = 500,
    onRestore,
  } = options;

  // History stacks
  const past = ref<HistoryState[]>([]);
  const future = ref<HistoryState[]>([]);

  // State tracking
  const isRestoring = ref(false);
  const lastPushTime = ref(0);

  /**
   * Create a snapshot of current state
   */
  function createSnapshot(): HistoryState {
    return {
      slides: JSON.parse(JSON.stringify(slides.value)),
      activeSlideIndex: activeSlideIndex.value,
      timestamp: Date.now(),
    };
  }

  /**
   * Push current state to history
   * Debounced to prevent excessive history entries
   */
  function pushState(force = false): void {
    // Don't push state during restoration
    if (isRestoring.value) return;

    // Debounce rapid changes
    const now = Date.now();
    if (!force && now - lastPushTime.value < debounceMs) {
      return;
    }

    const snapshot = createSnapshot();

    // Don't push if state hasn't changed
    if (past.value.length > 0) {
      const lastState = past.value[past.value.length - 1];
      if (JSON.stringify(lastState.slides) === JSON.stringify(snapshot.slides)) {
        return;
      }
    }

    // Add to past stack
    past.value.push(snapshot);

    // Limit history size
    if (past.value.length > maxSize) {
      past.value.shift();
    }

    // Clear future on new action
    future.value = [];

    lastPushTime.value = now;
  }

  /**
   * Undo last action
   */
  function undo(): boolean {
    if (!canUndo.value) return false;

    isRestoring.value = true;

    try {
      // Save current state to future
      future.value.unshift(createSnapshot());

      // Pop from past
      const previousState = past.value.pop()!;

      // Restore state
      slides.value = JSON.parse(JSON.stringify(previousState.slides));
      activeSlideIndex.value = previousState.activeSlideIndex;

      onRestore?.(previousState);

      return true;
    } finally {
      isRestoring.value = false;
    }
  }

  /**
   * Redo last undone action
   */
  function redo(): boolean {
    if (!canRedo.value) return false;

    isRestoring.value = true;

    try {
      // Save current state to past
      past.value.push(createSnapshot());

      // Pop from future
      const nextState = future.value.shift()!;

      // Restore state
      slides.value = JSON.parse(JSON.stringify(nextState.slides));
      activeSlideIndex.value = nextState.activeSlideIndex;

      onRestore?.(nextState);

      return true;
    } finally {
      isRestoring.value = false;
    }
  }

  /**
   * Clear all history
   */
  function clearHistory(): void {
    past.value = [];
    future.value = [];
    lastPushTime.value = 0;
  }

  /**
   * Reset history with a new initial state
   */
  function resetHistory(): void {
    clearHistory();
    pushState(true);
  }

  /**
   * Get current state
   */
  function getCurrentState(): HistoryState {
    return createSnapshot();
  }

  /**
   * Restore to a specific state
   */
  function restoreState(state: HistoryState): void {
    isRestoring.value = true;

    try {
      // Save current state to past
      past.value.push(createSnapshot());

      // Clear future
      future.value = [];

      // Restore
      slides.value = JSON.parse(JSON.stringify(state.slides));
      activeSlideIndex.value = state.activeSlideIndex;

      onRestore?.(state);
    } finally {
      isRestoring.value = false;
    }
  }

  // Computed
  const canUndo = computed(() => past.value.length > 0);
  const canRedo = computed(() => future.value.length > 0);
  const historyLength = computed(() => past.value.length);
  const futureLength = computed(() => future.value.length);

  // Initialize with current state
  if (slides.value.length > 0) {
    pushState(true);
  }

  return {
    // State
    past,
    future,
    isRestoring,

    // Computed
    canUndo,
    canRedo,
    historyLength,
    futureLength,

    // Actions
    pushState,
    undo,
    redo,
    clearHistory,
    resetHistory,
    getCurrentState,
    restoreState,
  };
}

export type UseHistoryReturn = ReturnType<typeof useHistory>;

/**
 * Utility function to create a debounced history push
 */
export function createDebouncedPush(
  historyInstance: UseHistoryReturn,
  debounceMs = 500
): () => void {
  let timeoutId: ReturnType<typeof setTimeout> | null = null;

  return () => {
    if (timeoutId) {
      clearTimeout(timeoutId);
    }

    timeoutId = setTimeout(() => {
      historyInstance.pushState();
      timeoutId = null;
    }, debounceMs);
  };
}
