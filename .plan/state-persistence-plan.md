# State Persistence Plan for Bulk Generation

## Problem Statement

When returning to the bulk generation page (refresh, navigation, or resuming):
1. **Generation Stages** are empty - shows "Waiting to start..." instead of actual progress
2. **System Activity** log is empty - loses all history
3. **Completed/Failed states** don't show their context (what was completed, what failed)

## Root Cause Analysis

Currently in `BulkGeneration.vue` onMounted:
```javascript
// Only restores basic state:
state.value.status = response.data.status
state.value.progress = response.data.progress
state.value.currentStage = response.data.current_stage
state.value.message = response.data.message

// BUT does NOT restore:
// - stages array (generation stages UI)
// - activityLog array (system activity)
// - metadata (chapter timings, word counts)
```

The API already returns all needed data:
- `details` - Activity log entries (timestamp, type, message, chapter, etc.)
- `chapter_statuses` - Completed chapters with word counts
- `metadata` - Chapter timings, progress info

## Proposed Solution

### 1. Add `restoreFromApiData()` function to useGenerationWebSocket.ts

This function will:
- Accept API response data
- Reconstruct stages based on chapter_statuses
- Restore activity log from details
- Set proper stage statuses (completed, active, pending, error)

```typescript
const restoreFromApiData = (apiData: {
    status: string
    progress: number
    current_stage: string
    message: string
    details: any[]
    metadata: any
    chapter_statuses: any[]
    download_links?: { docx: string; pdf: string } | null
}) => {
    // 1. Set basic state
    state.value.status = apiData.status
    state.value.progress = apiData.progress
    state.value.currentStage = apiData.current_stage
    state.value.message = apiData.message

    // 2. Restore metadata
    metadata.value = apiData.metadata || {}

    // 3. Initialize stages based on total chapters
    const totalChapters = apiData.chapter_statuses?.length ||
                         apiData.metadata?.total_chapters || 5
    initializeStages(totalChapters)

    // 4. Restore stage statuses from chapter_statuses
    apiData.chapter_statuses?.forEach(chapter => {
        const stage = stages.value.find(s => s.id === `chapter_generation_${chapter.chapter_number}`)
        if (stage) {
            stage.description = chapter.title
            stage.targetWordCount = chapter.target_word_count

            if (chapter.is_completed) {
                stage.status = 'completed'
                stage.progress = 100
                stage.chapterProgress = 100
                stage.wordCount = chapter.word_count
                stage.generationTime = metadata.value?.chapter_timings?.[chapter.chapter_number]
            }
        }
    })

    // 5. Set current stage status based on generation status
    if (apiData.status === 'processing' && apiData.current_stage) {
        const currentStage = stages.value.find(s => s.id === apiData.current_stage)
        if (currentStage) {
            currentStage.status = 'active'
            currentStage.chapterProgress = apiData.metadata?.chapter_progress || 0
        }

        // Mark literature mining as completed if we're past it
        if (apiData.current_stage.includes('chapter_generation')) {
            const miningStage = stages.value.find(s => s.id === 'literature_mining')
            if (miningStage) {
                miningStage.status = 'completed'
                miningStage.progress = 100                                                                                                                                  
            }
        }
    }

    // 6. Handle completed status
    if (apiData.status === 'completed') {
        stages.value.forEach(s => {
            s.status = 'completed'
            s.progress = 100
            if (s.chapterProgress !== undefined) s.chapterProgress = 100
        })
        downloadLinks.value = apiData.download_links || null
    }

    // 7. Handle failed status - mark failed stage as error
    if (apiData.status === 'failed' && apiData.current_stage) {
        const failedStage = stages.value.find(s => s.id === apiData.current_stage)
        if (failedStage) {
            failedStage.status = 'error'
        }
    }

    // 8. Restore activity log from details
    if (apiData.details?.length > 0) {
        activityLog.value = apiData.details.map(detail => ({
            timestamp: detail.timestamp,
            type: mapDetailTypeToLogType(detail.type),
            message: detail.message,
            chapter: detail.chapter,
            generationTime: detail.generation_time,
        })).reverse() // Most recent first
    }

    // 9. Start polling if still generating
    if (['processing', 'pending'].includes(apiData.status)) {
        startPolling()
    }
}
```

### 2. Update BulkGeneration.vue onMounted

```javascript
onMounted(async () => {
    try {
        const response = await axios.get(
            route('api.projects.bulk-generate.status', props.project.slug)
        )

        if (response.data.status !== 'not_started') {
            // Use new restore function to fully restore state
            restoreFromApiData(response.data)
        }
    } catch (error) {
        console.error('Failed to check generation status:', error)
    }
})
```

### 3. Export restoreFromApiData from composable

Add to the return object:
```typescript
return {
    // ... existing exports
    restoreFromApiData,
}
```

## Files to Modify

1. **`resources/js/composables/useGenerationWebSocket.ts`**
   - Add `restoreFromApiData()` function
   - Add `mapDetailTypeToLogType()` helper
   - Export the new function

2. **`resources/js/pages/projects/BulkGeneration.vue`**
   - Update onMounted to use `restoreFromApiData()`
   - Remove redundant individual state assignments

## Edge Cases to Handle

1. **Generation in progress** - Start progress animation for current chapter
2. **Generation completed** - Mark all stages complete, show download links
3. **Generation failed** - Mark failed stage as error, allow resume
4. **Resuming** - Don't reset state, just continue from current point
5. **No generation** - Keep default empty state

## Testing Scenarios

1. Start generation, refresh page mid-chapter - should show progress
2. Complete generation, refresh page - should show completed state
3. Generation fails, refresh page - should show failed state with resume option
4. Click Resume Generation - should continue from where it stopped
5. Navigate away and back - should restore full state

## Benefits

- Full state restoration on page load
- Consistent user experience across refreshes
- Resume works correctly from last checkpoint
- Activity log is preserved for debugging/visibility
- No lost context when returning to the page
