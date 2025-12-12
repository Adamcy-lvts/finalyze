# AI Writing Assistant Architecture

## Overview

This document outlines the architecture for three AI-powered writing assistant features:

1. **Ghost Text Autocomplete** - Tab-to-insert predictions as you type
2. **Smart Chapter Starter** - AI-generated opening for empty chapters
3. **Slash Commands** - Quick AI actions via `/` menu

---

## Feature 1: Ghost Text Autocomplete

### User Experience
- As user types, gray "ghost text" appears after cursor showing AI prediction
- **Tab** to accept the suggestion
- **Escape** or keep typing to dismiss
- **Ctrl+Space** to manually trigger completion
- Triggers automatically after 500ms typing pause

### Architecture

#### Frontend Components

```
resources/js/
├── tiptap-extensions/
│   └── GhostTextExtension.ts          # Tiptap extension for ghost text rendering
├── composables/
│   └── useAIAutocomplete.ts           # State management for autocomplete
├── components/ui/rich-text-editor/
│   └── RichTextEditor.vue             # Updated to include ghost text extension
```

#### 1.1 GhostTextExtension.ts (Tiptap Extension)
```typescript
// Key responsibilities:
// - Renders ghost text as a decoration after cursor
// - Handles Tab key to accept suggestion
// - Handles Escape to dismiss
// - Handles Ctrl+Space to trigger manually
// - Clears ghost text when user types

interface GhostTextState {
  text: string | null
  position: number
  isVisible: boolean
}
```

#### 1.2 useAIAutocomplete.ts (Composable)
```typescript
export function useAIAutocomplete(
  projectSlug: string,
  chapterNumber: number,
  editorRef: Ref<Editor | null>
) {
  // State
  const ghostText = ref<string | null>(null)
  const isLoading = ref(false)
  const lastRequestId = ref(0)

  // Debounced request (500ms)
  const debouncedRequest = useDebounceFn(requestCompletion, 500)

  // Methods
  function requestCompletion(context: CompletionContext): Promise<void>
  function acceptSuggestion(): void
  function dismissSuggestion(): void
  function triggerManually(): void

  return { ghostText, isLoading, debouncedRequest, acceptSuggestion, dismissSuggestion, triggerManually }
}

interface CompletionContext {
  textBefore: string      // ~500 chars before cursor
  textAfter: string       // ~200 chars after cursor (for context)
  chapterTitle: string
  chapterOutline: string
  projectTopic: string
}
```

#### Backend

```
app/
├── Http/Controllers/
│   └── AIAutocompleteController.php   # New controller for completions
├── Services/
│   └── AIAutocompleteService.php      # Completion logic
routes/
└── web.php                            # New route
```

#### 1.3 API Endpoint
```
POST /api/projects/{project}/chapters/{chapter}/autocomplete

Request:
{
  "text_before": "This study demonstrates that the implementation of...",
  "text_after": "",
  "chapter_title": "Methodology",
  "chapter_outline": "Research design, data collection, analysis methods",
  "project_topic": "Impact of AI on Education"
}

Response:
{
  "completion": " machine learning algorithms significantly improves student engagement metrics.",
  "confidence": 0.85
}
```

#### 1.4 AIAutocompleteService.php
```php
class AIAutocompleteService
{
    public function generateCompletion(
        string $textBefore,
        string $textAfter,
        string $chapterTitle,
        string $chapterOutline,
        string $projectTopic
    ): array {
        // Build prompt with context
        // Call AI with low temperature (0.3) for consistency
        // Return short completion (50-100 tokens max)
        // Include confidence score
    }
}
```

---

## Feature 2: Smart Chapter Starter

### User Experience
- When user opens an empty chapter, ghost text appears with suggested opening
- Shows entire first paragraph as ghost text
- **Tab** to accept and start editing
- **Regenerate** button to get alternative
- **Dismiss** (X) to start from scratch
- Small floating UI: "AI suggests an opening. Tab to accept | [Regenerate] [X]"

### Architecture

#### Frontend

```
resources/js/
├── composables/
│   └── useChapterStarter.ts           # Manages starter state
├── components/manual-editor/
│   └── ChapterStarterOverlay.vue      # UI for starter options
```

#### 2.1 useChapterStarter.ts
```typescript
export function useChapterStarter(
  projectSlug: string,
  chapter: Chapter,
  content: Ref<string>,
  editorRef: Ref<any>
) {
  const starterText = ref<string | null>(null)
  const isGenerating = ref(false)
  const showStarter = ref(false)

  // Check if chapter is empty on mount
  onMounted(() => {
    if (isChapterEmpty(content.value)) {
      generateStarter()
    }
  })

  async function generateStarter(): Promise<void>
  function acceptStarter(): void
  function dismissStarter(): void

  return { starterText, isGenerating, showStarter, generateStarter, acceptStarter, dismissStarter }
}
```

#### 2.2 ChapterStarterOverlay.vue
```vue
<!-- Floating bar shown when starter is available -->
<template>
  <Transition name="slide-up">
    <div v-if="showStarter && starterText" class="fixed bottom-20 left-1/2 -translate-x-1/2 ...">
      <div class="flex items-center gap-3 px-4 py-2 rounded-full bg-background/95 border shadow-lg">
        <Sparkles class="w-4 h-4 text-primary" />
        <span class="text-sm">AI suggests an opening</span>
        <kbd class="px-1.5 py-0.5 text-xs bg-muted rounded">Tab</kbd>
        <span class="text-xs text-muted-foreground">to accept</span>
        <Button variant="ghost" size="sm" @click="regenerate" :disabled="isGenerating">
          <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': isGenerating }" />
        </Button>
        <Button variant="ghost" size="icon" class="h-6 w-6" @click="dismiss">
          <X class="w-3 h-3" />
        </Button>
      </div>
    </div>
  </Transition>
</template>
```

#### Backend

#### 2.3 API Endpoint
```
POST /api/projects/{project}/chapters/{chapter}/generate-starter

Request:
{
  "chapter_title": "Introduction",
  "chapter_number": 1,
  "project_title": "AI in Modern Education",
  "project_topic": "Impact of artificial intelligence on student learning outcomes",
  "outline": "Background, problem statement, objectives, significance",
  "previous_chapter_summary": null  // or summary of previous chapter
}

Response:
{
  "starter": "The rapid advancement of artificial intelligence technologies has fundamentally transformed numerous sectors of society, with education emerging as one of the most significantly impacted domains. As educational institutions worldwide grapple with the integration of AI-powered tools and methodologies, understanding the implications of these technologies on student learning outcomes has become increasingly critical. This chapter examines the foundational concepts underlying AI in education, establishing the theoretical framework that guides this investigation.",
  "word_count": 67
}
```

---

## Feature 3: Slash Commands

### User Experience
- Type `/` at start of line or after space to open command menu
- Arrow keys to navigate, Enter to select
- Available commands:
  - `/continue` - Continue writing from cursor position
  - `/expand` - Expand the current paragraph with more detail
  - `/improve` - Rewrite current paragraph for clarity
  - `/cite` - Suggest citations for claims in paragraph
  - `/outline` - Generate outline for next section
- ESC to close menu
- Type to filter commands

### Architecture

#### Frontend

```
resources/js/
├── tiptap-extensions/
│   └── SlashCommandExtension.ts       # Detects / and triggers menu
├── components/ui/rich-text-editor/
│   ├── SlashCommandMenu.vue           # Dropdown command menu
│   └── RichTextEditor.vue             # Updated with slash commands
├── composables/
│   └── useSlashCommands.ts            # Command definitions and execution
```

#### 3.1 SlashCommandExtension.ts
```typescript
// Tiptap extension that:
// - Detects "/" typed at start of line or after whitespace
// - Shows command menu as suggestion popup
// - Handles keyboard navigation (up/down/enter/escape)
// - Filters commands as user types
// - Executes selected command

export const SlashCommand = Extension.create({
  name: 'slashCommand',

  addProseMirrorPlugins() {
    return [
      Suggestion({
        editor: this.editor,
        char: '/',
        startOfLine: false, // Allow anywhere after space
        command: ({ editor, range, props }) => {
          // Delete the /command text and execute
          editor.chain().focus().deleteRange(range).run()
          props.command(editor)
        },
        items: ({ query }) => {
          return filterCommands(query)
        },
        render: () => {
          // Return tippy.js popup with SlashCommandMenu
        }
      })
    ]
  }
})
```

#### 3.2 SlashCommandMenu.vue
```vue
<template>
  <div class="slash-command-menu w-72 rounded-lg border bg-popover p-1 shadow-lg">
    <div v-for="(command, index) in filteredCommands" :key="command.name"
         class="flex items-center gap-3 px-3 py-2 rounded-md cursor-pointer"
         :class="{ 'bg-accent': index === selectedIndex }"
         @click="selectCommand(command)">
      <component :is="command.icon" class="w-4 h-4 text-muted-foreground" />
      <div class="flex-1">
        <div class="text-sm font-medium">{{ command.label }}</div>
        <div class="text-xs text-muted-foreground">{{ command.description }}</div>
      </div>
      <kbd class="text-xs text-muted-foreground">/{{ command.name }}</kbd>
    </div>
    <div v-if="filteredCommands.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
      No commands found
    </div>
  </div>
</template>
```

#### 3.3 useSlashCommands.ts
```typescript
export interface SlashCommandDef {
  name: string
  label: string
  description: string
  icon: Component
  execute: (editor: Editor, context: CommandContext) => Promise<void>
}

export const slashCommands: SlashCommandDef[] = [
  {
    name: 'continue',
    label: 'Continue Writing',
    description: 'AI continues from cursor position',
    icon: Wand2,
    execute: async (editor, context) => {
      // Get text before cursor
      // Call API to continue
      // Stream result into editor
    }
  },
  {
    name: 'expand',
    label: 'Expand Paragraph',
    description: 'Add more detail to current paragraph',
    icon: Maximize2,
    execute: async (editor, context) => { ... }
  },
  {
    name: 'improve',
    label: 'Improve Writing',
    description: 'Rewrite for clarity and flow',
    icon: Sparkles,
    execute: async (editor, context) => { ... }
  },
  {
    name: 'cite',
    label: 'Suggest Citations',
    description: 'Find sources for claims',
    icon: BookOpen,
    execute: async (editor, context) => { ... }
  },
  {
    name: 'outline',
    label: 'Generate Outline',
    description: 'Create outline for next section',
    icon: ListTree,
    execute: async (editor, context) => { ... }
  }
]
```

#### Backend

#### 3.4 API Endpoints
```
POST /api/projects/{project}/chapters/{chapter}/ai/continue
POST /api/projects/{project}/chapters/{chapter}/ai/expand
POST /api/projects/{project}/chapters/{chapter}/ai/improve
POST /api/projects/{project}/chapters/{chapter}/ai/cite
POST /api/projects/{project}/chapters/{chapter}/ai/outline
```

These can reuse existing endpoints from ManualEditorController (improveText, expandText, suggestCitations) plus new ones for continue and outline.

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         ManualEditor.vue                             │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    RichTextEditor.vue                        │   │
│  │  ┌─────────────────────────────────────────────────────┐   │   │
│  │  │                  Tiptap Editor                       │   │   │
│  │  │  ┌───────────────┐  ┌───────────────┐              │   │   │
│  │  │  │ GhostText     │  │ SlashCommand  │              │   │   │
│  │  │  │ Extension     │  │ Extension     │              │   │   │
│  │  │  └───────┬───────┘  └───────┬───────┘              │   │   │
│  │  └──────────┼──────────────────┼───────────────────────┘   │   │
│  └─────────────┼──────────────────┼────────────────────────────┘   │
│                │                  │                                 │
│  ┌─────────────▼──────┐  ┌───────▼────────┐  ┌──────────────────┐ │
│  │ useAIAutocomplete  │  │ useSlashCommands│  │ useChapterStarter│ │
│  └─────────────┬──────┘  └───────┬────────┘  └────────┬─────────┘ │
└────────────────┼─────────────────┼────────────────────┼────────────┘
                 │                 │                    │
                 ▼                 ▼                    ▼
         ┌───────────────────────────────────────────────────┐
         │                   Backend API                      │
         │  /autocomplete  /ai/continue  /generate-starter   │
         │  /ai/expand     /ai/improve   /ai/cite  /ai/outline│
         └───────────────────────────────────────────────────┘
                              │
                              ▼
         ┌───────────────────────────────────────────────────┐
         │              AIContentGenerator                    │
         │         (OpenAI / Anthropic / etc.)               │
         └───────────────────────────────────────────────────┘
```

---

## Implementation Order

### Phase 1: Ghost Text Autocomplete (Core)
1. Create `GhostTextExtension.ts` - Tiptap extension
2. Create `useAIAutocomplete.ts` - composable
3. Create `AIAutocompleteController.php` - backend
4. Create `AIAutocompleteService.php` - service
5. Integrate into `RichTextEditor.vue`
6. Add keyboard handlers (Tab, Escape, Ctrl+Space)

### Phase 2: Smart Chapter Starter
1. Create `useChapterStarter.ts` - composable
2. Create `ChapterStarterOverlay.vue` - UI component
3. Add backend endpoint for starter generation
4. Integrate into `ManualEditor.vue`
5. Connect with ghost text for display

### Phase 3: Slash Commands
1. Create `SlashCommandExtension.ts` - Tiptap extension
2. Create `SlashCommandMenu.vue` - dropdown UI
3. Create `useSlashCommands.ts` - command definitions
4. Add/update backend endpoints
5. Integrate into `RichTextEditor.vue`

---

## Technical Considerations

### Performance
- Autocomplete requests are debounced (500ms)
- Cancel in-flight requests when new ones are made
- Limit context sent to API (~500 chars before, ~200 after)
- Cache recent completions for identical context

### User Experience
- Ghost text should be clearly distinguishable (gray/faded)
- Smooth transitions for showing/hiding suggestions
- Loading indicators for slash commands
- Error handling with toast notifications

### Accessibility
- Keyboard-only navigation for all features
- Screen reader announcements for suggestions
- Clear visual focus indicators

### Security
- Rate limiting on all AI endpoints
- Validate project/chapter ownership
- Sanitize all user input before sending to AI

---

## File Changes Summary

### New Files
```
resources/js/tiptap-extensions/GhostTextExtension.ts
resources/js/tiptap-extensions/SlashCommandExtension.ts
resources/js/composables/useAIAutocomplete.ts
resources/js/composables/useChapterStarter.ts
resources/js/composables/useSlashCommands.ts
resources/js/components/ui/rich-text-editor/SlashCommandMenu.vue
resources/js/components/manual-editor/ChapterStarterOverlay.vue
app/Http/Controllers/AIAutocompleteController.php
app/Services/AIAutocompleteService.php
```

### Modified Files
```
resources/js/components/ui/rich-text-editor/RichTextEditor.vue
resources/js/pages/projects/ManualEditor.vue
app/Http/Controllers/ManualEditorController.php
routes/web.php
```
