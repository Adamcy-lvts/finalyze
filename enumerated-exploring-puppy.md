# Fix Mobile Generation Restart & Smooth Text Streaming

## Problem Summary

Two issues with chapter generation on mobile devices:

1. **Generation restarts from beginning on app switch** - When user minimizes the app or switches to another app and returns, the generation stops briefly then restarts from the beginning instead of resuming
2. **Jumpy/blocky text streaming** - Text appears in large blocks rather than streaming smoothly

## Root Causes

### Issue 1: No Page Visibility Handling
- **No `visibilitychange` event handlers** in `useChapterGeneration.ts`
- Only `beforeunload` is handled (lines 335-362)
- When app goes to background, the browser may suspend/close the EventSource connection
- On return, the dead connection triggers reconnection which starts over

### Issue 2: Double Throttling + Full Replacement
- **Two-layer throttling** creates irregular update bursts:
  - `useChapterGeneration.ts` line 581: 200ms throttle
  - `RichTextEditor.vue` line 134: 300ms throttle (`STREAMING_THROTTLE_MS`)
- **Full content replacement** via `setContent()` causes entire editor re-render
- **Direct `scrollTop` assignment** without smooth animation

---

## Implementation Plan

### Phase 1: Add Visibility Change Handling

**File: [useChapterGeneration.ts](resources/js/composables/useChapterGeneration.ts)**

#### 1.1 Add new state variables (around line 92)
```typescript
const isPageHidden = ref(false);
const generationPausedByVisibility = ref(false);
const generationPausedAt = ref<number | null>(null);
```

#### 1.2 Add visibility change handler
```typescript
const handleVisibilityChange = () => {
    isPageHidden.value = document.hidden;

    if (document.hidden) {
        handlePageHidden();
    } else {
        handlePageVisible();
    }
};

const handlePageHidden = () => {
    if (!isGenerating.value || !eventSource.value) return;

    // Save pause state
    generationPausedAt.value = Date.now();
    generationPausedByVisibility.value = true;

    // Persist to localStorage for crash recovery
    try {
        localStorage.setItem(`generation_pause_${props.chapter.id}`, JSON.stringify({
            generationId: currentGenerationId.value,
            streamBuffer: streamBuffer.value,
            streamWordCount: streamWordCount.value,
            pausedAt: Date.now(),
        }));
    } catch (e) { /* ignore */ }

    // Close EventSource gracefully
    eventSource.value?.close();
    eventSource.value = null;

    // Update UI
    generationPhase.value = 'Paused';
    generationProgress.value = `Paused (${streamWordCount.value} words saved)`;
};

const handlePageVisible = async () => {
    if (!generationPausedByVisibility.value) return;

    const pauseDuration = Date.now() - (generationPausedAt.value || 0);
    const MAX_PAUSE = 5 * 60 * 1000; // 5 minutes

    if (pauseDuration > MAX_PAUSE) {
        // Too long - show recovery dialog instead of auto-resume
        generationPausedByVisibility.value = false;
        showRecoveryDialog.value = true;
        isGenerating.value = false;
        return;
    }

    if (!navigator.onLine) {
        toast.warning('No connection', { description: 'Reconnect to resume.' });
        return;
    }

    // Resume generation
    generationPhase.value = 'Resuming';
    generationPausedByVisibility.value = false;
    attemptReconnection(currentGenerationType.value as any);
};
```

#### 1.3 Update protection functions (lines 346-362)
```typescript
const enableGenerationProtection = () => {
    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', handleBeforeUnload);
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }
};

const disableGenerationProtection = () => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('beforeunload', handleBeforeUnload);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    }
};

const cleanupGenerationProtection = () => {
    disableGenerationProtection();
    try {
        localStorage.removeItem(`generation_pause_${props.chapter.id}`);
    } catch (e) { /* ignore */ }
};
```

---

### Phase 2: Fix Jumpy Text Streaming

#### 2.1 Remove composable throttle layer

**File: [useChapterGeneration.ts](resources/js/composables/useChapterGeneration.ts)** (lines 575-589)

Change from:
```typescript
case 'content':
    generationPhase.value = 'Writing';
    streamBuffer.value += data.content;
    streamWordCount.value = data.word_count || ...;

    const now = Date.now();
    if (now - lastStreamUpdate.value > 200) {  // <-- REMOVE THIS THROTTLE
        chapterContent.value = streamBuffer.value;
        lastStreamUpdate.value = now;
        // ...
    }
    break;
```

To:
```typescript
case 'content':
    generationPhase.value = 'Writing';
    streamBuffer.value += data.content;
    streamWordCount.value = data.word_count || ...;

    // Update immediately - let RichTextEditor handle throttling
    chapterContent.value = streamBuffer.value;

    const wordProgress = Math.min((streamWordCount.value / Math.max(estimatedTotalWords.value, 1)) * 43, 43);
    generationPercentage.value = Math.max(52, 52 + wordProgress);
    generationProgress.value = `Writing chapter content... (${streamWordCount.value} / ${estimatedTotalWords.value} words)`;
    scrollToBottom();
    break;
```

#### 2.2 Reduce RichTextEditor throttle

**File: [RichTextEditor.vue](resources/js/components/ui/rich-text-editor/RichTextEditor.vue)** (line 134)

Change:
```typescript
const STREAMING_THROTTLE_MS = 300
```
To:
```typescript
const STREAMING_THROTTLE_MS = 100  // Smoother updates
```

#### 2.3 Add smooth scroll animation

**File: [useChapterGeneration.ts](resources/js/composables/useChapterGeneration.ts)** - `scrollToBottom` function

Change direct `scrollTop` assignment to use smooth scrolling:
```typescript
const scrollToBottom = () => {
    if (!isGenerating.value && !isStreamingMode.value) return;

    nextTick(() => {
        requestAnimationFrame(() => {
            if (cachedScrollContainer.value?.isConnected) {
                cachedScrollContainer.value.scrollTo({
                    top: cachedScrollContainer.value.scrollHeight,
                    behavior: 'smooth'
                });
            }
        });
    });
};
```

---

## Files to Modify

| File | Changes |
|------|---------|
| [useChapterGeneration.ts](resources/js/composables/useChapterGeneration.ts) | Add visibility handlers, remove 200ms throttle, smooth scroll |
| [RichTextEditor.vue](resources/js/components/ui/rich-text-editor/RichTextEditor.vue) | Reduce throttle from 300ms to 100ms |

---

## Verification Plan

### Manual Testing (Required)
1. **Mobile App Switch Test**
   - Start chapter generation on mobile
   - Switch to another app, wait 10-30 seconds
   - Return - verify generation shows "Resuming" then continues (not restarts)
   - Check word count continues from where it paused

2. **Long Pause Test**
   - Start generation, switch apps for >5 minutes
   - Return - verify recovery dialog appears with saved word count

3. **Smooth Streaming Test**
   - Start generation and observe text appearing
   - Text should stream more smoothly, not in large blocks
   - Scroll should be smooth, not jumpy

4. **Network Change Test**
   - Start generation, toggle airplane mode briefly
   - Verify reconnection works properly

### Browser Testing
- Test on iOS Safari (iPhone)
- Test on Android Chrome
- Test in PWA installed mode
