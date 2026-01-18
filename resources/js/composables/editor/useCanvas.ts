/**
 * useCanvas - Fabric.js Canvas Composable
 * Handles canvas initialization, events, zoom, pan, and grid
 */

import { ref, shallowRef, computed, type Ref, type ShallowRef } from 'vue';
import { Canvas, FabricObject, Line, Point, FabricImage, ActiveSelection, type TPointerEvent, type TPointerEventInfo } from 'fabric';
import type { CanvasMouseEvent } from '@/types/wysiwyg';

export interface UseCanvasOptions {
  width?: number;
  height?: number;
  backgroundColor?: string;
  gridSize?: number;
  snapToGrid?: boolean;
  showGrid?: boolean;
}

export interface CanvasState {
  zoom: number;
  panX: number;
  panY: number;
  gridEnabled: boolean;
  snapEnabled: boolean;
  gridSize: number;
}

// Canvas aspect ratio (16:9)
const CANVAS_ASPECT_RATIO = 16 / 9;
const DEFAULT_CANVAS_WIDTH = 960;
const DEFAULT_CANVAS_HEIGHT = 540;

export function useCanvas(
  canvasRef: Ref<HTMLCanvasElement | null>,
  options: UseCanvasOptions = {}
) {
  // Canvas instance (using shallowRef to avoid reactivity issues)
  const canvas: ShallowRef<Canvas | null> = shallowRef(null);

  // Canvas state
  const state = ref<CanvasState>({
    zoom: 1,
    panX: 0,
    panY: 0,
    gridEnabled: options.showGrid ?? false,
    snapEnabled: options.snapToGrid ?? false,
    gridSize: options.gridSize ?? 20,
  });

  // Selection state
  const selectedObjects = ref<FabricObject[]>([]);
  const isSelecting = ref(false);
  const isDragging = ref(false);
  const isResizing = ref(false);
  const isRotating = ref(false);

  // Grid objects
  const gridObjects: Line[] = [];

  /**
   * Initialize the canvas
   */
  function initCanvas(
    width = options.width ?? DEFAULT_CANVAS_WIDTH,
    height = options.height ?? DEFAULT_CANVAS_HEIGHT
  ) {
    if (!canvasRef.value) return;

    // Dispose existing canvas
    if (canvas.value) {
      canvas.value.dispose();
    }

    // Create new canvas
    canvas.value = new Canvas(canvasRef.value, {
      width,
      height,
      backgroundColor: options.backgroundColor ?? '#FFFFFF',
      selection: true,
      preserveObjectStacking: true,
      renderOnAddRemove: true,
      stopContextMenu: true,
      fireRightClick: true,
      enableRetinaScaling: true,
    });
    canvas.value.selection = true;
    canvas.value.skipTargetFind = false;

    // Configure selection styling
    canvas.value.selectionColor = 'rgba(99, 102, 241, 0.1)';
    canvas.value.selectionBorderColor = '#6366F1';
    canvas.value.selectionLineWidth = 1;

    // Enable object caching for performance
    FabricObject.prototype.objectCaching = true;

    // Set default controls styling
    FabricObject.ownDefaults.borderColor = '#6366F1';
    FabricObject.ownDefaults.cornerColor = '#6366F1';
    FabricObject.ownDefaults.cornerStrokeColor = '#FFFFFF';
    FabricObject.ownDefaults.cornerStyle = 'circle';
    FabricObject.ownDefaults.cornerSize = 10;
    FabricObject.ownDefaults.transparentCorners = false;
    FabricObject.ownDefaults.borderScaleFactor = 1.5;
    FabricObject.ownDefaults.padding = 4;

    // Bind events
    bindCanvasEvents();

    // Draw grid if enabled
    if (state.value.gridEnabled) {
      drawGrid();
    }

    return canvas.value;
  }

  /**
   * Bind canvas events
   */
  function bindCanvasEvents() {
    if (!canvas.value) return;

    // Selection events
    canvas.value.on('selection:created', handleSelectionCreated);
    canvas.value.on('selection:updated', handleSelectionUpdated);
    canvas.value.on('selection:cleared', handleSelectionCleared);

    // Object modification events
    canvas.value.on('object:modified', handleObjectModified);
    canvas.value.on('object:moving', handleObjectMoving);
    canvas.value.on('object:scaling', handleObjectScaling);
    canvas.value.on('object:rotating', handleObjectRotating);

    // Mouse events
    canvas.value.on('mouse:down', handleMouseDown);
    canvas.value.on('mouse:move', handleMouseMove);
    canvas.value.on('mouse:up', handleMouseUp);
    canvas.value.on('mouse:wheel', handleMouseWheel);
    canvas.value.on('mouse:dblclick', handleDoubleClick);
  }

  /**
   * Selection event handlers
   */
  function handleSelectionCreated(e: { selected?: FabricObject[]; deselected?: FabricObject[] }) {
    if (e.selected) {
      selectedObjects.value = e.selected;
      isSelecting.value = true;
    }
    emitEvent('selection:created', { selected: e.selected || [] });
  }

  function handleSelectionUpdated(e: { selected?: FabricObject[]; deselected?: FabricObject[] }) {
    if (e.selected) {
      selectedObjects.value = e.selected;
    }
    emitEvent('selection:updated', { selected: e.selected || [], deselected: e.deselected || [] });
  }

  function handleSelectionCleared(e: { selected?: FabricObject[]; deselected?: FabricObject[] }) {
    selectedObjects.value = [];
    isSelecting.value = false;
    emitEvent('selection:cleared', { deselected: e.deselected || [] });
  }

  /**
   * Object modification handlers
   */
  function handleObjectModified(e: { target?: FabricObject; transform?: any }) {
    const target = e.target;
    if (!target) return;

    // Snap to grid if enabled
    if (state.value.snapEnabled && target.left !== undefined && target.top !== undefined) {
      target.set({
        left: snapToGrid(target.left),
        top: snapToGrid(target.top),
      });
      target.setCoords();
      canvas.value?.renderAll();
    }

    isDragging.value = false;
    isResizing.value = false;
    isRotating.value = false;

    emitEvent('object:modified', { target, transform: e.transform });
  }

  function handleObjectMoving(e: { target?: FabricObject }) {
    isDragging.value = true;
    const target = e.target;
    if (!target) return;

    // Constrain to canvas bounds
    constrainToCanvas(target);

    emitEvent('object:moving', { target });
  }

  function handleObjectScaling(e: { target?: FabricObject }) {
    isResizing.value = true;
    emitEvent('object:scaling', { target: e.target });
  }

  function handleObjectRotating(e: { target?: FabricObject }) {
    isRotating.value = true;
    emitEvent('object:rotating', { target: e.target });
  }

  /**
   * Mouse event handlers
   */
  function handleMouseDown(e: TPointerEventInfo<TPointerEvent>) {
    const pointer = canvas.value?.getScenePoint(e.e);
    emitEvent('mouse:down', {
      x: pointer?.x ?? 0,
      y: pointer?.y ?? 0,
      button: (e.e as MouseEvent).button ?? 0,
      shiftKey: e.e.shiftKey ?? false,
      ctrlKey: e.e.ctrlKey ?? false,
      altKey: e.e.altKey ?? false,
      target: e.target,
    } as CanvasMouseEvent);
  }

  function handleMouseMove(e: TPointerEventInfo<TPointerEvent>) {
    const pointer = canvas.value?.getScenePoint(e.e);
    emitEvent('mouse:move', {
      x: pointer?.x ?? 0,
      y: pointer?.y ?? 0,
      button: (e.e as MouseEvent).button ?? 0,
      shiftKey: e.e.shiftKey ?? false,
      ctrlKey: e.e.ctrlKey ?? false,
      altKey: e.e.altKey ?? false,
      target: e.target,
    } as CanvasMouseEvent);
  }

  function handleMouseUp(e: TPointerEventInfo<TPointerEvent>) {
    const pointer = canvas.value?.getScenePoint(e.e);
    emitEvent('mouse:up', {
      x: pointer?.x ?? 0,
      y: pointer?.y ?? 0,
      button: (e.e as MouseEvent).button ?? 0,
      shiftKey: e.e.shiftKey ?? false,
      ctrlKey: e.e.ctrlKey ?? false,
      altKey: e.e.altKey ?? false,
      target: e.target,
    } as CanvasMouseEvent);
  }

  function handleMouseWheel(e: TPointerEventInfo<WheelEvent>) {
    if (e.e.ctrlKey) {
      // Zoom with Ctrl + wheel
      e.e.preventDefault();
      const delta = e.e.deltaY > 0 ? -0.1 : 0.1;
      zoomBy(delta, e.e.offsetX, e.e.offsetY);
    }
    emitEvent('mouse:wheel', { deltaY: e.e.deltaY, ctrlKey: e.e.ctrlKey });
  }

  function handleDoubleClick(e: TPointerEventInfo<TPointerEvent>) {
    emitEvent('mouse:dblclick', { target: e.target });
  }

  /**
   * Event emitter
   */
  const eventListeners = new Map<string, Set<Function>>();

  function emitEvent(event: string, data: any) {
    const listeners = eventListeners.get(event);
    if (listeners) {
      listeners.forEach((fn) => fn(data));
    }
  }

  function on(event: string, callback: Function) {
    if (!eventListeners.has(event)) {
      eventListeners.set(event, new Set());
    }
    eventListeners.get(event)!.add(callback);
    return () => off(event, callback);
  }

  function off(event: string, callback: Function) {
    eventListeners.get(event)?.delete(callback);
  }

  /**
   * Grid system
   */
  function drawGrid() {
    if (!canvas.value) return;

    clearGrid();

    const { gridSize } = state.value;
    const width = canvas.value.width || DEFAULT_CANVAS_WIDTH;
    const height = canvas.value.height || DEFAULT_CANVAS_HEIGHT;

    // Vertical lines
    for (let i = 0; i <= width / gridSize; i++) {
      const line = new Line([i * gridSize, 0, i * gridSize, height], {
        stroke: '#E5E7EB',
        strokeWidth: 1,
        selectable: false,
        evented: false,
        excludeFromExport: true,
      });
      gridObjects.push(line);
      canvas.value.add(line);
    }

    // Horizontal lines
    for (let i = 0; i <= height / gridSize; i++) {
      const line = new Line([0, i * gridSize, width, i * gridSize], {
        stroke: '#E5E7EB',
        strokeWidth: 1,
        selectable: false,
        evented: false,
        excludeFromExport: true,
      });
      gridObjects.push(line);
      canvas.value.add(line);
    }

    // Send grid to back
    gridObjects.forEach((line) => canvas.value?.sendObjectToBack(line));
    canvas.value.renderAll();
  }

  function clearGrid() {
    if (!canvas.value) return;

    gridObjects.forEach((line) => canvas.value?.remove(line));
    gridObjects.length = 0;
  }

  function toggleGrid() {
    state.value.gridEnabled = !state.value.gridEnabled;
    if (state.value.gridEnabled) {
      drawGrid();
    } else {
      clearGrid();
    }
  }

  function toggleSnapToGrid() {
    state.value.snapEnabled = !state.value.snapEnabled;
  }

  function snapToGrid(value: number): number {
    const { gridSize } = state.value;
    return Math.round(value / gridSize) * gridSize;
  }

  /**
   * Zoom and Pan
   */
  function setZoom(zoom: number, centerX?: number, centerY?: number) {
    if (!canvas.value) return;

    const newZoom = Math.max(0.1, Math.min(3, zoom));
    state.value.zoom = newZoom;

    const center = centerX !== undefined && centerY !== undefined
      ? new Point(centerX, centerY)
      : new Point(canvas.value.width! / 2, canvas.value.height! / 2);

    canvas.value.zoomToPoint(center, newZoom);
    canvas.value.renderAll();
  }

  function zoomBy(delta: number, centerX?: number, centerY?: number) {
    const newZoom = state.value.zoom + delta;
    setZoom(newZoom, centerX, centerY);
  }

  function zoomIn() {
    zoomBy(0.1);
  }

  function zoomOut() {
    zoomBy(-0.1);
  }

  function resetZoom() {
    setZoom(1);
    resetPan();
  }

  function setPan(x: number, y: number) {
    if (!canvas.value) return;

    state.value.panX = x;
    state.value.panY = y;

    const vpt = canvas.value.viewportTransform;
    if (vpt) {
      vpt[4] = x;
      vpt[5] = y;
      canvas.value.setViewportTransform(vpt);
      canvas.value.renderAll();
    }
  }

  function resetPan() {
    setPan(0, 0);
  }

  /**
   * Canvas utilities
   */
  function constrainToCanvas(obj: FabricObject) {
    if (!canvas.value || obj.left === undefined || obj.top === undefined) return;

    const canvasWidth = canvas.value.width || DEFAULT_CANVAS_WIDTH;
    const canvasHeight = canvas.value.height || DEFAULT_CANVAS_HEIGHT;

    // Use scaled dimensions instead of expensive getBoundingRect()
    const scaleX = obj.scaleX ?? 1;
    const scaleY = obj.scaleY ?? 1;
    const objWidth = (obj.width ?? 0) * scaleX;
    const objHeight = (obj.height ?? 0) * scaleY;

    let needsUpdate = false;
    let newLeft = obj.left;
    let newTop = obj.top;

    // Keep object within canvas bounds (allow partial overflow for better UX)
    const minVisible = 20; // Minimum pixels that must stay visible

    if (newLeft + objWidth < minVisible) {
      newLeft = minVisible - objWidth;
      needsUpdate = true;
    } else if (newLeft > canvasWidth - minVisible) {
      newLeft = canvasWidth - minVisible;
      needsUpdate = true;
    }

    if (newTop + objHeight < minVisible) {
      newTop = minVisible - objHeight;
      needsUpdate = true;
    } else if (newTop > canvasHeight - minVisible) {
      newTop = canvasHeight - minVisible;
      needsUpdate = true;
    }

    // Only update if position actually changed to avoid unnecessary re-renders
    if (needsUpdate) {
      obj.set({ left: newLeft, top: newTop });
    }
  }

  function resizeCanvas(width: number, height: number) {
    if (!canvas.value) return;

    canvas.value.setDimensions({ width, height });

    if (state.value.gridEnabled) {
      drawGrid();
    }

    canvas.value.renderAll();
  }

  function fitToContainer(container: HTMLElement) {
    if (!canvas.value) return;

    const containerWidth = container.clientWidth;
    const containerHeight = container.clientHeight;

    // Calculate dimensions maintaining aspect ratio
    let width = containerWidth;
    let height = width / CANVAS_ASPECT_RATIO;

    if (height > containerHeight) {
      height = containerHeight;
      width = height * CANVAS_ASPECT_RATIO;
    }

    resizeCanvas(width, height);
    return { width, height };
  }

  function setBackgroundColor(color: string) {
    if (!canvas.value) return;

    canvas.value.backgroundColor = color;
    canvas.value.renderAll();
  }

  async function setBackgroundImage(url: string, options: Partial<FabricImage> = {}) {
    if (!canvas.value) return;

    try {
      const img = await FabricImage.fromURL(url, { crossOrigin: 'anonymous' });
      if (!canvas.value || !img) return;

      const canvasWidth = canvas.value.width || DEFAULT_CANVAS_WIDTH;
      const canvasHeight = canvas.value.height || DEFAULT_CANVAS_HEIGHT;

      img.set({
        originX: 'left',
        originY: 'top',
        scaleX: canvasWidth / (img.width || 1),
        scaleY: canvasHeight / (img.height || 1),
        ...options,
      });

      canvas.value.backgroundImage = img;
      canvas.value.renderAll();
    } catch (error) {
      console.error('Failed to load background image:', error);
    }
  }

  function clearBackgroundImage() {
    if (!canvas.value) return;

    canvas.value.backgroundImage = undefined;
    canvas.value.renderAll();
  }

  /**
   * Selection helpers
   */
  function selectAll() {
    if (!canvas.value) return;

    const objects = canvas.value.getObjects().filter((obj: FabricObject) => obj.selectable);
    if (objects.length === 0) return;

    canvas.value.discardActiveObject();
    const selection = new ActiveSelection(objects, { canvas: canvas.value });
    canvas.value.setActiveObject(selection);
    canvas.value.renderAll();
  }

  function deselectAll() {
    if (!canvas.value) return;

    canvas.value.discardActiveObject();
    canvas.value.renderAll();
  }

  function selectObject(obj: FabricObject) {
    if (!canvas.value) return;

    canvas.value.setActiveObject(obj);
    canvas.value.renderAll();
  }

  function selectObjectById(id: string) {
    if (!canvas.value) return;

    const obj = canvas.value.getObjects().find((o: FabricObject) => (o as any).elementId === id);
    if (obj) {
      selectObject(obj);
    }
  }

  /**
   * Export
   */
  function toJSON() {
    return canvas.value?.toObject(['elementId', 'elementType', 'locked']);
  }

  function toDataURL(format: 'png' | 'jpeg' = 'png', quality = 1) {
    return canvas.value?.toDataURL({ multiplier: 1, format, quality });
  }

  function toSVG() {
    return canvas.value?.toSVG();
  }

  /**
   * Render
   */
  function render() {
    canvas.value?.renderAll();
  }

  function requestRender() {
    canvas.value?.requestRenderAll();
  }

  /**
   * Cleanup
   */
  function dispose() {
    if (canvas.value) {
      canvas.value.dispose();
      canvas.value = null;
    }
    eventListeners.clear();
    gridObjects.length = 0;
  }

  // Computed
  const hasSelection = computed(() => selectedObjects.value.length > 0);
  const multipleSelected = computed(() => selectedObjects.value.length > 1);
  const canvasWidth = computed(() => canvas.value?.width ?? DEFAULT_CANVAS_WIDTH);
  const canvasHeight = computed(() => canvas.value?.height ?? DEFAULT_CANVAS_HEIGHT);

  return {
    // Canvas instance
    canvas,

    // State
    state,
    selectedObjects,
    isSelecting,
    isDragging,
    isResizing,
    isRotating,

    // Computed
    hasSelection,
    multipleSelected,
    canvasWidth,
    canvasHeight,

    // Initialization
    initCanvas,
    dispose,

    // Grid
    toggleGrid,
    toggleSnapToGrid,
    drawGrid,
    clearGrid,

    // Zoom & Pan
    setZoom,
    zoomBy,
    zoomIn,
    zoomOut,
    resetZoom,
    setPan,
    resetPan,

    // Canvas operations
    resizeCanvas,
    fitToContainer,
    setBackgroundColor,
    setBackgroundImage,
    clearBackgroundImage,

    // Selection
    selectAll,
    deselectAll,
    selectObject,
    selectObjectById,

    // Events
    on,
    off,

    // Export
    toJSON,
    toDataURL,
    toSVG,

    // Render
    render,
    requestRender,
  };
}

export type UseCanvasReturn = ReturnType<typeof useCanvas>;
