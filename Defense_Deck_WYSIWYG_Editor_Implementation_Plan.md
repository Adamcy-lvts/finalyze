# Defense Deck WYSIWYG Editor - Implementation Plan

## Overview

Transform the defense deck system from basic contenteditable fields into a full PowerPoint-like WYSIWYG editor with advanced editing capabilities, professional themes, and complete format preservation in PPTX export.

## Goals

1. **Advanced WYSIWYG editing** - PowerPoint-like experience with drag-drop, shapes, alignment tools, color pickers, font selection, and layering
2. **Professional themes** - 5 pre-designed themes (Academic, Modern, Minimal, Bold, Professional) that apply instantly
3. **Format preservation** - Export to PowerPoint with all formatting, colors, fonts, and positioning exactly as shown
4. **Enhanced presentation mode** - Transitions, presenter view with notes and timer, mobile-responsive controls
5. **Better UX** - Intuitive editing tools, smooth interactions, visual feedback, autosave

## User Requirements

Based on user input:
- **Editing Level**: Advanced (PowerPoint-like) with full WYSIWYG capabilities
- **Export Priority**: Preserve all formatting (bold, italic, colors, fonts, custom styling)
- **Themes**: Yes, multiple themes (3-5 professional options)
- **UX Focus**: Better editing tools, presentation mode polish, mobile responsiveness

## Current Implementation Analysis

### Strengths
- Existing DeckViewer component (2000+ lines) with presentation mode
- Working PPTX export using PptxGenJS library
- Slide data structure supports bullets, paragraphs, images, charts, tables
- Chart.js integration ready (ChartPreview component)
- Debounced autosave pattern established

### Limitations
- Basic contenteditable fields only (no rich formatting toolbar)
- No text boxes, shapes, or advanced positioning
- Charts/tables not rendered in editor
- PPTX export strips all HTML/styling
- No themes or design system
- Limited image controls
- No WYSIWYG editing experience

## Architecture Decision

### Core Technology: Fabric.js

**Chosen**: Canvas-based editor with Fabric.js library

**Why Fabric.js**:
- Mature, battle-tested library for canvas manipulation
- Built-in object model (text boxes, shapes, images, groups, layers)
- Native drag, resize, rotate, selection events
- JSON serialization (matches current slide structure)
- Direct mapping to PptxGenJS for export
- Vue 3 compatible

### Integration Strategy

**Create new WysiwygSlideEditor component** (keep DeckViewer for backward compatibility)

```
defense/
├── DeckViewer.vue          # Legacy editor + presentation mode
├── WysiwygSlideEditor.vue  # New WYSIWYG editor (MAIN)
├── editor/
│   ├── EditorCanvas.vue    # Fabric.js wrapper
│   ├── EditorToolbar.vue   # Formatting tools
│   ├── EditorSidebar.vue   # Layers, themes
│   ├── ElementInspector.vue # Properties panel
│   └── PresenterView.vue   # Enhanced presentation
```

## Implementation Plan

### Phase 1: Foundation (Priority 1)

**Goal**: Set up Fabric.js infrastructure and basic element system

#### Tasks

1. **Install dependencies**
   ```bash
   npm install fabric gsap @vueuse/gesture
   npm install --save-dev @types/fabric
   ```

2. **Create type definitions**
   - File: `resources/js/types/wysiwyg.ts`
   - Define: `WysiwygSlideElement`, `WysiwygSlide`, `Theme`, `Tool`, `HistoryState`
   - Include: Position, transform, styling, text, shape, image, chart, table interfaces

3. **Create core composables**

   **useCanvas.ts**
   - Initialize Fabric.js canvas
   - Set up event handlers (mouse, keyboard, selection, modification)
   - Implement zoom and pan
   - Add grid system with snap-to-grid

   **useElements.ts**
   - CRUD operations for elements
   - Add text box, shape, image, chart, table
   - Update element properties
   - Z-index management (bring forward, send back)

   **useSelection.ts**
   - Single and multi-select
   - Group/ungroup operations
   - Clipboard operations (cut, copy, paste)

4. **Create EditorCanvas component**
   - File: `resources/js/components/defense/editor/EditorCanvas.vue`
   - Wrapper for Fabric.js canvas
   - Render slides with aspect ratio 16:9
   - Handle canvas resize
   - Emit events to parent

5. **Create WysiwygSlideEditor shell**
   - File: `resources/js/components/defense/WysiwygSlideEditor.vue`
   - Layout: Toolbar (top) + Sidebar (left) + Canvas (center) + Inspector (right)
   - Props: `slides`, `activeIndex`, `isSaving`
   - Events: `update:slides`, `update:activeIndex`

**Critical Files**:
- `resources/js/types/wysiwyg.ts`
- `resources/js/composables/editor/useCanvas.ts`
- `resources/js/composables/editor/useElements.ts`
- `resources/js/composables/editor/useSelection.ts`
- `resources/js/components/defense/editor/EditorCanvas.vue`
- `resources/js/components/defense/WysiwygSlideEditor.vue`

### Phase 2: Editing Tools (Priority 1)

**Goal**: Build comprehensive editing toolbar and inspector

#### Tasks

1. **Create EditorToolbar component**
   - File: `resources/js/components/defense/editor/EditorToolbar.vue`
   - Sections: Elements, Format, Alignment, Arrange, Edit
   - Tools:
     - **Elements**: Text box, Shapes (dropdown), Image, Chart, Table
     - **Format**: Bold, Italic, Underline, Text color, Fill color, Font selector, Font size
     - **Alignment**: Align left/center/right, Align top/middle/bottom, Distribute
     - **Arrange**: Bring to front, Send to back, Bring forward, Send backward, Group, Ungroup
     - **Edit**: Undo, Redo, Duplicate, Delete

2. **Create ShapeLibrary component**
   - File: `resources/js/components/defense/editor/ShapeLibrary.vue`
   - Shapes: Rectangle, Circle, Triangle, Arrow, Line, Rounded rectangle
   - Preview grid with click to add

3. **Create ElementInspector component**
   - File: `resources/js/components/defense/editor/ElementInspector.vue`
   - Dynamic sections based on selected element type
   - Common: Position (x, y), Size (width, height), Rotation, Opacity
   - Text: Font family, Font size, Bold, Italic, Text color, Alignment, Line height
   - Shape: Fill color, Border color, Border width, Corner radius
   - Image: Fit mode, Filters

4. **Create ColorPicker component**
   - File: `resources/js/components/defense/editor/ColorPicker.vue`
   - Theme color palette + custom color picker
   - Recent colors
   - Transparency slider

5. **Create FontSelector component**
   - File: `resources/js/components/defense/editor/FontSelector.vue`
   - Common fonts: Arial, Times New Roman, Calibri, Helvetica, Georgia, Verdana
   - Font preview
   - Search functionality

6. **Implement context menu**
   - File: `resources/js/components/defense/editor/ContextMenu.vue`
   - Right-click on element or canvas
   - Actions: Cut, Copy, Paste, Duplicate, Delete, Lock, Bring to front, Send to back

7. **Add keyboard shortcuts**
   - In useCanvas.ts
   - Ctrl+Z (Undo), Ctrl+Shift+Z (Redo)
   - Ctrl+C (Copy), Ctrl+X (Cut), Ctrl+V (Paste), Ctrl+D (Duplicate)
   - Delete/Backspace (Delete selected)
   - Ctrl+B (Bold), Ctrl+I (Italic), Ctrl+U (Underline)
   - Ctrl+G (Group), Ctrl+Shift+G (Ungroup)
   - Arrow keys (Move 1px), Shift+Arrow (Move 10px)

**Critical Files**:
- `resources/js/components/defense/editor/EditorToolbar.vue`
- `resources/js/components/defense/editor/ElementInspector.vue`
- `resources/js/components/defense/editor/ShapeLibrary.vue`
- `resources/js/components/defense/editor/ColorPicker.vue`
- `resources/js/components/defense/editor/FontSelector.vue`
- `resources/js/components/defense/editor/ContextMenu.vue`

### Phase 3: Undo/Redo & State Management (Priority 1)

**Goal**: Implement robust undo/redo and state management

#### Tasks

1. **Create useHistory composable**
   - File: `resources/js/composables/editor/useHistory.ts`
   - Stack-based undo/redo (max 50 states)
   - Track: slides array, active slide index
   - Methods: `pushState`, `undo`, `redo`, `reset`
   - Computed: `canUndo`, `canRedo`

2. **Integrate history with editor**
   - Push state on every element modification
   - Debounce rapid changes (500ms)
   - Visual indicators (undo/redo button states)

3. **Implement autosave**
   - Debounced save (1.5s after last change)
   - Visual feedback: "Saving...", "Saved 3s ago"
   - Optimistic updates

**Critical Files**:
- `resources/js/composables/editor/useHistory.ts`

### Phase 4: Theme System (Priority 2)

**Goal**: Create professional themes with instant application

#### Tasks

1. **Define theme structure**
   - File: `public/themes/themes.json`
   - 5 themes: Academic, Modern, Minimal, Bold, Professional
   - Each theme:
     - Colors: primary, secondary, accent, background, text, textSecondary
     - Fonts: heading (family, weight, size), body (family, weight, size)
     - Slide layouts: title, content, two-column, image-left, image-right
     - Element defaults: text, shape, image styling

2. **Create useTheme composable**
   - File: `resources/js/composables/editor/useTheme.ts`
   - Load themes from JSON
   - Apply theme to slide (update all element colors/fonts)
   - Create slide from layout template

3. **Create ThemeSelector component**
   - File: `resources/js/components/defense/editor/ThemeSelector.vue`
   - Grid of theme cards with previews
   - Click to apply theme to current slide or all slides
   - Preview mode (hover to see changes)

4. **Implement theme application logic**
   - Update text elements: font family, font size, color based on theme
   - Update shape elements: fill and stroke colors
   - Update slide background color
   - Preserve user customizations (add flag `isThemeOverridden`)

**Critical Files**:
- `public/themes/themes.json`
- `resources/js/composables/editor/useTheme.ts`
- `resources/js/components/defense/editor/ThemeSelector.vue`

### Phase 5: Enhanced PPTX Export (Priority 1)

**Goal**: Preserve all WYSIWYG formatting in PowerPoint export

#### Tasks

1. **Update database schema**
   - File: `database/migrations/YYYY_MM_DD_add_wysiwyg_to_defense_slide_decks.php`
   - Add columns:
     - `is_wysiwyg` (boolean, default false)
     - `editor_version` (string, nullable)
     - `theme_config` (json, nullable)

2. **Update DefenseSlideDeck model**
   - File: `app/Models/DefenseSlideDeck.php`
   - Add casts: `is_wysiwyg` => 'boolean', `theme_config` => 'array'
   - Accessor for `slides` that handles both legacy and WYSIWYG formats

3. **Update export script**
   - File: `scripts/pptx/export-defense-deck.mjs`
   - Detect `is_wysiwyg` flag in slide data
   - If true, use element-based rendering:
     - `addTextElement()` - Render text elements with font, size, color, position, rotation
     - `addShapeElement()` - Render shapes with fill, stroke, corner radius
     - `addImageElement()` - Render images with position, size, fit mode, rotation
     - `addChartElement()` - Render Chart.js charts as native PowerPoint charts
     - `addTableElement()` - Render tables with formatting
   - If false, use legacy bullet/paragraph rendering
   - Sort elements by z-index before rendering

4. **Create migration utilities**
   - File: `resources/js/utils/editor/migration.ts`
   - `legacyToWysiwyg()` - Convert old slides to new element-based format
   - `wysiwygToLegacy()` - Fallback conversion for backward compatibility

5. **Update DefenseDeckController**
   - File: `app/Http/Controllers/DefenseDeckController.php`
   - Update `update()` method validation to accept `elements` array
   - Store `is_wysiwyg = true` when saving WYSIWYG slides

**Critical Files**:
- `database/migrations/YYYY_MM_DD_add_wysiwyg_to_defense_slide_decks.php`
- `app/Models/DefenseSlideDeck.php`
- `scripts/pptx/export-defense-deck.mjs`
- `resources/js/utils/editor/migration.ts`
- `app/Http/Controllers/DefenseDeckController.php`

### Phase 6: Enhanced Presentation Mode (Priority 2)

**Goal**: Polish presentation mode with transitions and presenter view

#### Tasks

1. **Create PresenterView component**
   - File: `resources/js/components/defense/editor/PresenterView.vue`
   - Split-screen layout:
     - Left: Current slide (80% scale) + speaker notes
     - Right: Timer + next slide preview (60% scale)
   - Timer: Start, pause, reset
   - Navigation: Keyboard and button controls
   - Open in new window or side-by-side

2. **Implement transitions**
   - File: `resources/js/composables/editor/usePresentation.ts`
   - Transition types: Fade, Slide (left/right), Zoom, Flip, None
   - Use GSAP for smooth animations
   - Configurable duration (300-1000ms)
   - Add transition selector to ElementInspector

3. **Enhance fullscreen mode**
   - Update existing DeckViewer presentation mode
   - Add transition support between slides
   - Show slide counter
   - Touch swipe navigation (already exists)
   - Keyboard navigation (already exists)

4. **Add slide notes editor**
   - In ElementInspector, add "Speaker Notes" section
   - Rich text editor for notes
   - Display in presenter view

**Critical Files**:
- `resources/js/components/defense/editor/PresenterView.vue`
- `resources/js/composables/editor/usePresentation.ts`

### Phase 7: Mobile Responsiveness (Priority 2)

**Goal**: Optimize for tablets and mobile devices

#### Tasks

1. **Responsive layout**
   - Hide inspector panel on tablets, show as modal
   - Collapsible sidebar on mobile
   - Touch-optimized toolbar (larger buttons, more spacing)

2. **Touch gestures**
   - Use `@vueuse/gesture` for swipe, pinch, tap
   - Pinch to zoom canvas
   - Two-finger pan
   - Long press for context menu
   - Double tap to edit text

3. **Mobile-specific controls**
   - Floating action button for add element
   - Bottom sheet for properties
   - Simplified toolbar with most-used tools

**Critical Files**:
- `resources/js/components/defense/WysiwygSlideEditor.vue` (responsive layout)

### Phase 8: Integration & Testing (Priority 1)

**Goal**: Integrate into Defense.vue and test thoroughly

#### Tasks

1. **Integrate into Defense.vue**
   - File: `resources/js/pages/projects/Defense.vue`
   - Add feature flag: `useWysiwygEditor` (default true for new decks)
   - Conditional rendering: Show WysiwygSlideEditor if `is_wysiwyg`, else show DeckViewer
   - Keep existing presentation mode (DeckViewer fullscreen)

2. **Create migration UI**
   - Button in Defense.vue: "Upgrade to Advanced Editor"
   - Confirm dialog explaining changes
   - Run `legacyToWysiwyg()` migration
   - Update `is_wysiwyg` flag in database

3. **Testing**
   - Unit tests for composables (useCanvas, useElements, useSelection, useHistory, useTheme)
   - Component tests for EditorToolbar, ElementInspector, ColorPicker, FontSelector
   - Integration test: Create slide → Add elements → Edit → Export → Verify PPTX
   - Browser test: Fullscreen presentation with transitions
   - Export test: Verify all formatting preserved in PowerPoint

4. **Performance optimization**
   - Enable Fabric.js object caching
   - Lazy load themes
   - Debounce canvas renders
   - Use Web Workers for export

**Critical Files**:
- `resources/js/pages/projects/Defense.vue`

## Technical Specifications

### Element Data Structure

```typescript
interface WysiwygSlideElement {
  id: string; // UUID
  type: 'text' | 'shape' | 'image' | 'chart' | 'table';

  // Position & Transform
  x: number; // Percentage (0-100)
  y: number; // Percentage (0-100)
  width: number; // Percentage
  height: number; // Percentage
  rotation: number; // Degrees
  zIndex: number; // Layer order

  // Styling
  fill?: string; // Background/fill color
  stroke?: string; // Border color
  strokeWidth?: number;
  opacity?: number; // 0-1

  // Element-specific
  text?: {
    content: string;
    fontFamily: string;
    fontSize: number;
    fontWeight: 'normal' | 'bold';
    fontStyle: 'normal' | 'italic';
    textAlign: 'left' | 'center' | 'right' | 'justify';
    color: string;
  };

  shape?: {
    shapeType: 'rectangle' | 'circle' | 'triangle' | 'arrow' | 'line';
    cornerRadius?: number;
  };

  image?: {
    url: string;
    fit: 'contain' | 'cover' | 'fill';
  };
}

interface WysiwygSlide {
  id: string;
  title: string;
  elements: WysiwygSlideElement[];
  themeId?: string;
  backgroundColor: string;
  speaker_notes?: string;
  transition?: {
    type: 'fade' | 'slide' | 'zoom' | 'none';
    duration: number; // ms
  };
}
```

### Theme Structure

```typescript
interface Theme {
  id: string;
  name: string;
  description: string;

  colors: {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    text: string;
    textSecondary: string;
  };

  fonts: {
    heading: { family: string; weight: number; size: number };
    body: { family: string; weight: number; size: number };
  };
}
```

## Critical Files Summary

### New Files to Create

1. `resources/js/types/wysiwyg.ts` - Type definitions
2. `resources/js/composables/editor/useCanvas.ts` - Fabric.js wrapper
3. `resources/js/composables/editor/useElements.ts` - Element CRUD
4. `resources/js/composables/editor/useSelection.ts` - Selection management
5. `resources/js/composables/editor/useHistory.ts` - Undo/redo
6. `resources/js/composables/editor/useTheme.ts` - Theme system
7. `resources/js/composables/editor/usePresentation.ts` - Presentation mode
8. `resources/js/components/defense/WysiwygSlideEditor.vue` - Main editor
9. `resources/js/components/defense/editor/EditorCanvas.vue` - Canvas wrapper
10. `resources/js/components/defense/editor/EditorToolbar.vue` - Toolbar
11. `resources/js/components/defense/editor/ElementInspector.vue` - Properties panel
12. `resources/js/components/defense/editor/ShapeLibrary.vue` - Shape picker
13. `resources/js/components/defense/editor/ColorPicker.vue` - Color picker
14. `resources/js/components/defense/editor/FontSelector.vue` - Font selector
15. `resources/js/components/defense/editor/ContextMenu.vue` - Right-click menu
16. `resources/js/components/defense/editor/ThemeSelector.vue` - Theme picker
17. `resources/js/components/defense/editor/PresenterView.vue` - Presenter mode
18. `resources/js/utils/editor/migration.ts` - Legacy conversion
19. `public/themes/themes.json` - Theme definitions
20. `database/migrations/YYYY_MM_DD_add_wysiwyg_to_defense_slide_decks.php` - DB migration

### Files to Update

1. `resources/js/pages/projects/Defense.vue` - Integrate new editor
2. `app/Models/DefenseSlideDeck.php` - Add wysiwyg support
3. `app/Http/Controllers/DefenseDeckController.php` - Handle element data
4. `scripts/pptx/export-defense-deck.mjs` - Enhanced export

### Files to Keep (No Changes)

1. `resources/js/components/defense/DeckViewer.vue` - Legacy editor + presentation
2. `resources/js/components/defense/ChartPreview.vue` - Chart rendering
3. `resources/js/components/defense/ExecutiveBriefingDeck.vue` - Briefing deck
4. `resources/js/components/defense/PredictedQuestionsDeck.vue` - Questions deck

## Verification Plan

After implementation, verify the system works by:

1. **Create new slide deck**
   - Navigate to Defense page for a project
   - Generate defense deck slides
   - Confirm WysiwygSlideEditor loads

2. **Edit slides**
   - Add text box, type text, format (bold, italic, color, font)
   - Add shape (rectangle), change fill color, border
   - Add image, upload, position, scale, rotate
   - Select multiple elements, group, align
   - Test undo/redo (Ctrl+Z, Ctrl+Shift+Z)
   - Verify autosave indicator

3. **Apply theme**
   - Open theme selector
   - Apply "Modern" theme
   - Verify all text colors/fonts update
   - Switch to "Academic" theme
   - Verify changes apply instantly

4. **Export to PowerPoint**
   - Click "Download PPTX"
   - Wait for generation
   - Download and open in PowerPoint
   - Verify:
     - All text formatting preserved (fonts, sizes, colors, bold, italic)
     - Shapes render with correct colors and positions
     - Images positioned correctly
     - Slide backgrounds match theme
     - Speaker notes present

5. **Presentation mode**
   - Click "PRESENT" button
   - Navigate with arrow keys
   - Verify transitions work
   - Open presenter view
   - Check timer, notes, next slide preview

6. **Mobile responsiveness** (on tablet)
   - Edit slides with touch
   - Pinch to zoom
   - Swipe to navigate
   - Verify toolbar adapts to smaller screen

7. **Legacy compatibility**
   - Open old project with legacy slides
   - Click "Upgrade to Advanced Editor"
   - Verify slides migrate correctly
   - Export to PPTX, verify no data loss

## Success Criteria

- Users can create professional slides without needing PowerPoint
- All formatting exports perfectly to PPTX
- Editing is intuitive and smooth (no lag)
- Themes apply instantly and look professional
- Presentation mode rivals PowerPoint's presenter view
- System works on tablets with touch controls
- Legacy slides migrate without data loss

## Estimated Timeline

- **Phase 1-3** (Foundation + Tools + State): 3-4 weeks
- **Phase 4** (Themes): 1-2 weeks
- **Phase 5** (Export): 2 weeks
- **Phase 6-7** (Presentation + Mobile): 2 weeks
- **Phase 8** (Integration + Testing): 1-2 weeks

**Total**: 9-12 weeks for complete implementation

## Next Steps

1. Get approval on architecture approach (Fabric.js, component structure)
2. Confirm theme designs and color palettes
3. Install dependencies and start Phase 1
4. Create WysiwygSlideEditor shell component
5. Build out composables and core editing functionality
