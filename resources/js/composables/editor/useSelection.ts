/**
 * useSelection - Selection Management Composable
 * Handles selection, multi-select, grouping, alignment, and clipboard operations
 */

import { ref, computed, type Ref } from 'vue';
import { FabricObject, ActiveSelection } from 'fabric';
import { v4 as uuid } from 'uuid';
import type {
  WysiwygSlide,
  WysiwygSlideElement,
  HorizontalAlign,
  VerticalAlign,
  DistributeDirection,
} from '@/types/wysiwyg';
import type { UseCanvasReturn } from './useCanvas';
import type { UseElementsReturn } from './useElements';

export interface UseSelectionOptions {
  canvasInstance: UseCanvasReturn;
  elementsInstance: UseElementsReturn;
  slide: Ref<WysiwygSlide | null>;
  onSelectionChange?: (ids: string[]) => void;
}

export function useSelection(options: UseSelectionOptions) {
  const { canvasInstance, elementsInstance, slide, onSelectionChange } = options;

  // Selection state
  const selectedIds = ref<string[]>([]);
  const clipboard = ref<WysiwygSlideElement[] | null>(null);

  /**
   * Get selected element IDs from canvas
   */
  function getSelectedIds(): string[] {
    const canvas = canvasInstance.canvas.value;
    if (!canvas) return [];

    const activeObj = canvas.getActiveObject();
    if (!activeObj) return [];

    if (activeObj instanceof ActiveSelection) {
      return activeObj.getObjects()
        .map((obj) => (obj as any).elementId)
        .filter(Boolean);
    }

    const id = (activeObj as any).elementId;
    return id ? [id] : [];
  }

  /**
   * Update selection state from canvas
   */
  function syncSelectionFromCanvas(): void {
    selectedIds.value = getSelectedIds();
    onSelectionChange?.(selectedIds.value);
  }

  /**
   * Select elements by IDs
   */
  function selectElements(ids: string[]): void {
    const canvas = canvasInstance.canvas.value;
    if (!canvas) return;

    canvas.discardActiveObject();

    if (ids.length === 0) {
      selectedIds.value = [];
      canvas.renderAll();
      onSelectionChange?.([]);
      return;
    }

    const objects = ids
      .map((id) => elementsInstance.getFabricObject(id))
      .filter((obj): obj is FabricObject => obj !== undefined);

    if (objects.length === 0) return;

    if (objects.length === 1) {
      canvas.setActiveObject(objects[0]);
    } else {
      const selection = new ActiveSelection(objects, { canvas });
      canvas.setActiveObject(selection);
    }

    selectedIds.value = ids;
    canvas.renderAll();
    onSelectionChange?.(ids);
  }

  /**
   * Add to selection
   */
  function addToSelection(id: string): void {
    const currentIds = [...selectedIds.value];
    if (!currentIds.includes(id)) {
      currentIds.push(id);
      selectElements(currentIds);
    }
  }

  /**
   * Remove from selection
   */
  function removeFromSelection(id: string): void {
    const currentIds = selectedIds.value.filter((i) => i !== id);
    selectElements(currentIds);
  }

  /**
   * Toggle selection
   */
  function toggleSelection(id: string): void {
    if (selectedIds.value.includes(id)) {
      removeFromSelection(id);
    } else {
      addToSelection(id);
    }
  }

  /**
   * Select all elements
   */
  function selectAll(): void {
    if (!slide.value) return;
    const ids = slide.value.elements.map((el) => el.id);
    selectElements(ids);
  }

  /**
   * Deselect all
   */
  function deselectAll(): void {
    selectElements([]);
  }

  /**
   * Get selected elements
   */
  const selectedElements = computed<WysiwygSlideElement[]>(() => {
    if (!slide.value) return [];
    return slide.value.elements.filter((el) => selectedIds.value.includes(el.id));
  });

  // ============================================================================
  // Clipboard Operations
  // ============================================================================

  /**
   * Copy selected elements
   */
  function copy(): void {
    if (selectedElements.value.length === 0) return;

    clipboard.value = selectedElements.value.map((el) => ({
      ...JSON.parse(JSON.stringify(el)),
    }));
  }

  /**
   * Cut selected elements
   */
  function cut(): void {
    copy();
    selectedIds.value.forEach((id) => elementsInstance.removeElement(id));
    deselectAll();
  }

  /**
   * Paste from clipboard
   */
  function paste(): WysiwygSlideElement[] {
    if (!clipboard.value || clipboard.value.length === 0) return [];

    const pastedElements: WysiwygSlideElement[] = [];
    const offset = 2; // Percentage offset for pasted elements

    clipboard.value.forEach((el) => {
      const newElement: WysiwygSlideElement = {
        ...JSON.parse(JSON.stringify(el)),
        id: uuid(),
        x: el.x + offset,
        y: el.y + offset,
        zIndex: elementsInstance.getNextZIndex(),
      };

      elementsInstance.addElement(newElement);
      pastedElements.push(newElement);
    });

    // Select pasted elements
    selectElements(pastedElements.map((el) => el.id));

    return pastedElements;
  }

  /**
   * Duplicate selected elements
   */
  function duplicate(): WysiwygSlideElement[] {
    copy();
    return paste();
  }

  // ============================================================================
  // Grouping Operations
  // ============================================================================

  /**
   * Group selected elements
   */
  function groupElements(): string | null {
    if (selectedIds.value.length < 2) return null;

    const groupId = uuid();

    selectedIds.value.forEach((id) => {
      elementsInstance.updateElement(id, { groupId });
    });

    return groupId;
  }

  /**
   * Ungroup elements
   */
  function ungroupElements(groupId: string): void {
    if (!slide.value) return;

    slide.value.elements
      .filter((el) => el.groupId === groupId)
      .forEach((el) => {
        elementsInstance.updateElement(el.id, { groupId: undefined });
      });
  }

  // ============================================================================
  // Alignment Operations
  // ============================================================================

  /**
   * Align selected elements horizontally
   */
  function alignHorizontal(alignment: HorizontalAlign): void {
    if (selectedElements.value.length < 2) return;

    const elements = selectedElements.value;
    let targetX: number;

    switch (alignment) {
      case 'left':
        targetX = Math.min(...elements.map((el) => el.x));
        break;
      case 'center':
        const minX = Math.min(...elements.map((el) => el.x));
        const maxX = Math.max(...elements.map((el) => el.x + el.width));
        const centerX = (minX + maxX) / 2;
        elements.forEach((el) => {
          elementsInstance.updateElement(el.id, { x: centerX - el.width / 2 });
        });
        return;
      case 'right':
        const rightEdge = Math.max(...elements.map((el) => el.x + el.width));
        elements.forEach((el) => {
          elementsInstance.updateElement(el.id, { x: rightEdge - el.width });
        });
        return;
    }

    elements.forEach((el) => {
      elementsInstance.updateElement(el.id, { x: targetX });
    });
  }

  /**
   * Align selected elements vertically
   */
  function alignVertical(alignment: VerticalAlign): void {
    if (selectedElements.value.length < 2) return;

    const elements = selectedElements.value;

    switch (alignment) {
      case 'top':
        const topEdge = Math.min(...elements.map((el) => el.y));
        elements.forEach((el) => {
          elementsInstance.updateElement(el.id, { y: topEdge });
        });
        break;
      case 'middle':
        const minY = Math.min(...elements.map((el) => el.y));
        const maxY = Math.max(...elements.map((el) => el.y + el.height));
        const centerY = (minY + maxY) / 2;
        elements.forEach((el) => {
          elementsInstance.updateElement(el.id, { y: centerY - el.height / 2 });
        });
        break;
      case 'bottom':
        const bottomEdge = Math.max(...elements.map((el) => el.y + el.height));
        elements.forEach((el) => {
          elementsInstance.updateElement(el.id, { y: bottomEdge - el.height });
        });
        break;
    }
  }

  /**
   * Distribute elements evenly
   */
  function distribute(direction: DistributeDirection): void {
    if (selectedElements.value.length < 3) return;

    const elements = [...selectedElements.value];

    if (direction === 'horizontal') {
      elements.sort((a, b) => a.x - b.x);
      const first = elements[0];
      const last = elements[elements.length - 1];
      const totalWidth = (last.x + last.width) - first.x;
      const elementWidths = elements.reduce((sum, el) => sum + el.width, 0);
      const spacing = (totalWidth - elementWidths) / (elements.length - 1);

      let currentX = first.x;
      elements.forEach((el, index) => {
        if (index > 0) {
          elementsInstance.updateElement(el.id, { x: currentX });
        }
        currentX += el.width + spacing;
      });
    } else {
      elements.sort((a, b) => a.y - b.y);
      const first = elements[0];
      const last = elements[elements.length - 1];
      const totalHeight = (last.y + last.height) - first.y;
      const elementHeights = elements.reduce((sum, el) => sum + el.height, 0);
      const spacing = (totalHeight - elementHeights) / (elements.length - 1);

      let currentY = first.y;
      elements.forEach((el, index) => {
        if (index > 0) {
          elementsInstance.updateElement(el.id, { y: currentY });
        }
        currentY += el.height + spacing;
      });
    }
  }

  // ============================================================================
  // Lock Operations
  // ============================================================================

  /**
   * Lock selected elements
   */
  function lockSelected(): void {
    selectedIds.value.forEach((id) => {
      elementsInstance.updateElement(id, { locked: true });
    });
  }

  /**
   * Unlock selected elements
   */
  function unlockSelected(): void {
    selectedIds.value.forEach((id) => {
      elementsInstance.updateElement(id, { locked: false });
    });
  }

  /**
   * Toggle lock on selected elements
   */
  function toggleLock(): void {
    const anyLocked = selectedElements.value.some((el) => el.locked);
    if (anyLocked) {
      unlockSelected();
    } else {
      lockSelected();
    }
  }

  // ============================================================================
  // Computed
  // ============================================================================

  const hasSelection = computed(() => selectedIds.value.length > 0);
  const hasMultipleSelection = computed(() => selectedIds.value.length > 1);
  const hasClipboard = computed(() => clipboard.value !== null && clipboard.value.length > 0);

  const canGroup = computed(() => selectedIds.value.length >= 2);
  const canUngroup = computed(() => {
    return selectedElements.value.some((el) => el.groupId !== undefined);
  });

  const canAlign = computed(() => selectedIds.value.length >= 2);
  const canDistribute = computed(() => selectedIds.value.length >= 3);

  // Listen to canvas selection events
  canvasInstance.on('selection:created', syncSelectionFromCanvas);
  canvasInstance.on('selection:updated', syncSelectionFromCanvas);
  canvasInstance.on('selection:cleared', syncSelectionFromCanvas);

  return {
    // State
    selectedIds,
    selectedElements,
    clipboard,

    // Computed
    hasSelection,
    hasMultipleSelection,
    hasClipboard,
    canGroup,
    canUngroup,
    canAlign,
    canDistribute,

    // Selection
    selectElements,
    addToSelection,
    removeFromSelection,
    toggleSelection,
    selectAll,
    deselectAll,
    syncSelectionFromCanvas,

    // Clipboard
    copy,
    cut,
    paste,
    duplicate,

    // Grouping
    groupElements,
    ungroupElements,

    // Alignment
    alignHorizontal,
    alignVertical,
    distribute,

    // Lock
    lockSelected,
    unlockSelected,
    toggleLock,
  };
}

export type UseSelectionReturn = ReturnType<typeof useSelection>;
