/**
 * useElements - Element CRUD Composable
 * Handles adding, updating, removing, and managing slide elements
 */

import { ref, computed, type Ref } from 'vue';
import {
  FabricObject,
  Textbox,
  Ellipse,
  Triangle,
  Line,
  Rect,
  Group,
  FabricImage,
  FabricText,
  Shadow,
  ActiveSelection,
} from 'fabric';
import { v4 as uuid } from 'uuid';
import type {
  WysiwygSlide,
  WysiwygSlideElement,
  ElementType,
  TextProperties,
  ShapeProperties,
  ImageProperties,
  ChartProperties,
  TableProperties,
  DEFAULT_TEXT_PROPERTIES,
  DEFAULT_SHAPE_PROPERTIES,
  DEFAULT_ELEMENT_STYLES,
} from '@/types/wysiwyg';
import type { UseCanvasReturn } from './useCanvas';

// Default values
const defaultTextProps: TextProperties = {
  content: 'Text',
  fontFamily: 'Arial',
  fontSize: 18,
  fontWeight: 'normal',
  fontStyle: 'normal',
  textAlign: 'left',
  color: '#111827',
  lineHeight: 1.4,
  letterSpacing: 0,
};

const defaultShapeProps: ShapeProperties = {
  shapeType: 'rectangle',
  cornerRadius: 0,
};

export interface UseElementsOptions {
  canvasInstance: UseCanvasReturn;
  slide: Ref<WysiwygSlide | null>;
  onElementsChange?: (elements: WysiwygSlideElement[]) => void;
}

export function useElements(options: UseElementsOptions) {
  const { canvasInstance, slide, onElementsChange } = options;

  // Element tracking
  const fabricObjectMap = new Map<string, FabricObject>();

  /**
   * Get the next z-index for new elements
   */
  function getNextZIndex(): number {
    if (!slide.value) return 1;
    const maxZ = slide.value.elements.reduce((max, el) => Math.max(max, el.zIndex), 0);
    return maxZ + 1;
  }

  /**
   * Convert percentage position to canvas pixels
   */
  function percentToPixels(percent: number, dimension: number): number {
    return (percent / 100) * dimension;
  }

  /**
   * Convert canvas pixels to percentage
   */
  function pixelsToPercent(pixels: number, dimension: number): number {
    return (pixels / dimension) * 100;
  }

  /**
   * Create Fabric.js object from element data
   */
  function createFabricObject(element: WysiwygSlideElement): FabricObject | null {
    const canvas = canvasInstance.canvas.value;
    if (!canvas) return null;

    const canvasWidth = canvas.width || 960;
    const canvasHeight = canvas.height || 540;

    const x = percentToPixels(element.x, canvasWidth);
    const y = percentToPixels(element.y, canvasHeight);
    const width = percentToPixels(element.width, canvasWidth);
    const height = percentToPixels(element.height, canvasHeight);

    let fabricObj: FabricObject | null = null;

    switch (element.type) {
      case 'text':
        fabricObj = createTextObject(element, x, y, width, height);
        break;
      case 'shape':
        fabricObj = createShapeObject(element, x, y, width, height);
        break;
      case 'image':
        // Images are loaded asynchronously
        createImageObject(element, x, y, width, height);
        return null;
      case 'chart':
        // Charts are rendered asynchronously
        createChartObject(element, x, y, width, height);
        return null;
      case 'table':
        fabricObj = createTableObject(element, x, y, width, height);
        break;
      default:
        console.warn(`Unknown element type: ${element.type}`);
        return null;
    }

    if (fabricObj) {
      applyCommonStyles(fabricObj, element);
      (fabricObj as any).elementId = element.id;
      (fabricObj as any).elementType = element.type;
    }

    return fabricObj;
  }

  /**
   * Create text object
   */
  function createTextObject(
    element: WysiwygSlideElement,
    x: number,
    y: number,
    width: number,
    height: number
  ): Textbox {
    const text = element.text || defaultTextProps;

    const textbox = new Textbox(text.content, {
      left: x,
      top: y,
      width: width,
      fontSize: text.fontSize,
      fontFamily: text.fontFamily,
      fontWeight: text.fontWeight === 'bold' ? 'bold' : 'normal',
      fontStyle: text.fontStyle === 'italic' ? 'italic' : 'normal',
      fill: text.color,
      textAlign: text.textAlign,
      lineHeight: text.lineHeight,
      charSpacing: text.letterSpacing * 10,
      underline: text.textDecoration === 'underline',
      linethrough: text.textDecoration === 'line-through',
      editable: true,
      splitByGrapheme: false,
    });

    return textbox;
  }

  /**
   * Create shape object
   */
  function createShapeObject(
    element: WysiwygSlideElement,
    x: number,
    y: number,
    width: number,
    height: number
  ): FabricObject {
    const shape = element.shape || defaultShapeProps;
    let shapeObj: FabricObject;

    switch (shape.shapeType) {
      case 'circle':
        shapeObj = new Ellipse({
          left: x,
          top: y,
          rx: width / 2,
          ry: height / 2,
          fill: element.fill || '#E5E7EB',
          stroke: element.stroke || '#9CA3AF',
          strokeWidth: element.strokeWidth || 1,
        });
        break;

      case 'triangle':
        shapeObj = new Triangle({
          left: x,
          top: y,
          width: width,
          height: height,
          fill: element.fill || '#E5E7EB',
          stroke: element.stroke || '#9CA3AF',
          strokeWidth: element.strokeWidth || 1,
        });
        break;

      case 'line':
        shapeObj = new Line([x, y, x + width, y + height], {
          stroke: element.stroke || '#9CA3AF',
          strokeWidth: element.strokeWidth || 2,
          fill: '',
        });
        break;

      case 'arrow':
        // Create arrow as a group (line + triangle head)
        const arrowLine = new Line([0, height / 2, width - 15, height / 2], {
          stroke: element.stroke || '#9CA3AF',
          strokeWidth: element.strokeWidth || 2,
        });
        const arrowHead = new Triangle({
          left: width - 15,
          top: height / 2 - 8,
          width: 15,
          height: 16,
          fill: element.stroke || '#9CA3AF',
          angle: 90,
        });
        shapeObj = new Group([arrowLine, arrowHead], {
          left: x,
          top: y,
        });
        break;

      case 'rounded-rectangle':
        shapeObj = new Rect({
          left: x,
          top: y,
          width: width,
          height: height,
          fill: element.fill || '#E5E7EB',
          stroke: element.stroke || '#9CA3AF',
          strokeWidth: element.strokeWidth || 1,
          rx: shape.cornerRadius || 10,
          ry: shape.cornerRadius || 10,
        });
        break;

      case 'rectangle':
      default:
        shapeObj = new Rect({
          left: x,
          top: y,
          width: width,
          height: height,
          fill: element.fill || '#E5E7EB',
          stroke: element.stroke || '#9CA3AF',
          strokeWidth: element.strokeWidth || 1,
        });
        break;
    }

    return shapeObj;
  }

  /**
   * Create image object (async)
   */
  async function createImageObject(
    element: WysiwygSlideElement,
    x: number,
    y: number,
    width: number,
    height: number
  ): Promise<void> {
    const imageProps = element.image;
    if (!imageProps?.url) return;

    try {
      const img = await FabricImage.fromURL(imageProps.url, { crossOrigin: 'anonymous' });
      const canvas = canvasInstance.canvas.value;
      if (!canvas || !img) return;

      // Calculate scaling based on fit mode
      const imgWidth = img.width || 1;
      const imgHeight = img.height || 1;

      let scaleX = width / imgWidth;
      let scaleY = height / imgHeight;

      if (imageProps.fit === 'contain') {
        const scale = Math.min(scaleX, scaleY);
        scaleX = scale;
        scaleY = scale;
      } else if (imageProps.fit === 'cover') {
        const scale = Math.max(scaleX, scaleY);
        scaleX = scale;
        scaleY = scale;
      }
      // 'fill' uses different scale for x and y (stretches)

      img.set({
        left: x,
        top: y,
        scaleX: scaleX,
        scaleY: scaleY,
        angle: element.rotation || 0,
        opacity: element.opacity ?? 1,
      });

      applyCommonStyles(img, element);
      (img as any).elementId = element.id;
      (img as any).elementType = element.type;

      canvas.add(img);
      fabricObjectMap.set(element.id, img);
      canvas.renderAll();
    } catch (error) {
      console.error('Failed to load image:', error);
    }
  }

  /**
   * Create chart object (async - renders Chart.js to canvas then converts to image)
   */
  async function createChartObject(
    element: WysiwygSlideElement,
    x: number,
    y: number,
    width: number,
    height: number
  ): Promise<void> {
    const chartProps = element.chart;
    if (!chartProps) return;

    // Create an offscreen canvas for Chart.js
    const offscreenCanvas = document.createElement('canvas');
    offscreenCanvas.width = width * 2; // Higher resolution
    offscreenCanvas.height = height * 2;
    const ctx = offscreenCanvas.getContext('2d');
    if (!ctx) return;

    try {
      // Import Chart.js dynamically
      const { default: Chart } = await import('chart.js/auto');

      // Create chart
      const chart = new Chart(ctx, {
        type: chartProps.chartType as any,
        data: {
          labels: chartProps.labels,
          datasets: chartProps.datasets.map((ds) => ({
            label: ds.label,
            data: ds.data,
            backgroundColor: ds.backgroundColor || generateChartColors(ds.data.length),
            borderColor: ds.borderColor || '#4F46E5',
            borderWidth: ds.borderWidth || 1,
          })),
        },
        options: {
          responsive: false,
          maintainAspectRatio: false,
          animation: false,
          plugins: {
            legend: {
              display: chartProps.showLegend ?? true,
              position: 'bottom',
            },
            title: {
              display: !!chartProps.title,
              text: chartProps.title || '',
              font: { size: 14, weight: 'bold' },
            },
          },
          scales: ['bar', 'line', 'scatter', 'area'].includes(chartProps.chartType)
            ? {
                x: { grid: { display: chartProps.showGrid ?? true } },
                y: { grid: { display: chartProps.showGrid ?? true } },
              }
            : undefined,
        },
      });

      // Convert to image after rendering
      await new Promise(resolve => setTimeout(resolve, 100));
      const dataUrl = offscreenCanvas.toDataURL('image/png');
      chart.destroy();

      const img = await FabricImage.fromURL(dataUrl);
      const canvas = canvasInstance.canvas.value;
      if (!canvas || !img) return;

      img.set({
        left: x,
        top: y,
        scaleX: width / (img.width || 1),
        scaleY: height / (img.height || 1),
        angle: element.rotation || 0,
        opacity: element.opacity ?? 1,
      });

      applyCommonStyles(img, element);
      (img as any).elementId = element.id;
      (img as any).elementType = element.type;

      canvas.add(img);
      fabricObjectMap.set(element.id, img);
      canvas.renderAll();
    } catch (error) {
      console.error('Failed to create chart:', error);
    }
  }

  /**
   * Generate colors for chart datasets
   */
  function generateChartColors(count: number): string[] {
    const colors = [
      '#6366F1', '#8B5CF6', '#EC4899', '#F43F5E', '#F97316',
      '#EAB308', '#22C55E', '#14B8A6', '#06B6D4', '#3B82F6',
    ];
    return Array.from({ length: count }, (_, i) => colors[i % colors.length]);
  }

  /**
   * Create table object (renders as a fabric Group)
   */
  function createTableObject(
    element: WysiwygSlideElement,
    x: number,
    y: number,
    width: number,
    height: number
  ): Group {
    const tableProps = element.table;
    const columns = tableProps?.columns || [];
    const rows = tableProps?.rows || [];

    const objects: FabricObject[] = [];

    const cellPadding = 8;
    const headerHeight = 32;
    const rowHeight = 28;
    const colWidth = width / Math.max(columns.length, 1);

    // Background
    const bg = new Rect({
      left: 0,
      top: 0,
      width: width,
      height: height,
      fill: '#FFFFFF',
      stroke: '#E5E7EB',
      strokeWidth: 1,
    });
    objects.push(bg);

    // Header background
    const headerBg = new Rect({
      left: 0,
      top: 0,
      width: width,
      height: headerHeight,
      fill: tableProps?.headerBackground || '#F3F4F6',
    });
    objects.push(headerBg);

    // Header cells
    columns.forEach((col, i) => {
      const headerText = new FabricText(col.label, {
        left: i * colWidth + cellPadding,
        top: cellPadding,
        fontSize: 12,
        fontWeight: 'bold',
        fontFamily: 'Arial',
        fill: tableProps?.headerColor || '#111827',
      });
      objects.push(headerText);

      // Vertical divider
      if (i > 0) {
        const divider = new Line([i * colWidth, 0, i * colWidth, height], {
          stroke: '#E5E7EB',
          strokeWidth: 1,
        });
        objects.push(divider);
      }
    });

    // Header bottom border
    const headerBorder = new Line([0, headerHeight, width, headerHeight], {
      stroke: '#D1D5DB',
      strokeWidth: 1,
    });
    objects.push(headerBorder);

    // Data rows
    rows.forEach((row, rowIndex) => {
      const rowY = headerHeight + rowIndex * rowHeight;

      // Alternate row background
      if (tableProps?.alternateRowColors && rowIndex % 2 === 1) {
        const rowBg = new Rect({
          left: 0,
          top: rowY,
          width: width,
          height: rowHeight,
          fill: '#F9FAFB',
        });
        objects.push(rowBg);
      }

      // Row border
      const rowBorder = new Line([0, rowY + rowHeight, width, rowY + rowHeight], {
        stroke: '#E5E7EB',
        strokeWidth: 1,
      });
      objects.push(rowBorder);

      // Cell values
      columns.forEach((col, colIndex) => {
        const cellValue = String(row[col.key] ?? '');
        const cellText = new FabricText(cellValue, {
          left: colIndex * colWidth + cellPadding,
          top: rowY + cellPadding,
          fontSize: 11,
          fontFamily: 'Arial',
          fill: '#374151',
        });
        objects.push(cellText);
      });
    });

    // Create group
    const group = new Group(objects, {
      left: x,
      top: y,
    });

    return group;
  }

  /**
   * Apply common styles to fabric object
   */
  function applyCommonStyles(obj: FabricObject, element: WysiwygSlideElement): void {
    obj.set({
      angle: element.rotation || 0,
      opacity: element.opacity ?? 1,
      selectable: !element.locked,
      evented: !element.locked,
      visible: !element.hidden,
    });

    if (element.shadow) {
      obj.set('shadow', new Shadow({
        color: element.shadow.color,
        blur: element.shadow.blur,
        offsetX: element.shadow.offsetX,
        offsetY: element.shadow.offsetY,
      }));
    }
  }

  /**
   * Add element to slide
   */
  function addElement(element: WysiwygSlideElement): WysiwygSlideElement {
    if (!slide.value) {
      throw new Error('No active slide');
    }

    // Ensure element has an ID
    if (!element.id) {
      element.id = uuid();
    }

    // Add to slide data
    slide.value.elements.push(element);

    // Create and add fabric object
    const fabricObj = createFabricObject(element);
    if (fabricObj) {
      const canvas = canvasInstance.canvas.value;
      if (canvas) {
        canvas.add(fabricObj);
        fabricObjectMap.set(element.id, fabricObj);
        canvas.setActiveObject(fabricObj);
        canvas.renderAll();
      }
    }

    onElementsChange?.(slide.value.elements);
    return element;
  }

  /**
   * Add text element
   */
  function addText(options: Partial<WysiwygSlideElement> = {}): WysiwygSlideElement {
    const element: WysiwygSlideElement = {
      id: uuid(),
      type: 'text',
      x: options.x ?? 10,
      y: options.y ?? 10,
      width: options.width ?? 40,
      height: options.height ?? 10,
      rotation: 0,
      zIndex: getNextZIndex(),
      text: {
        ...defaultTextProps,
        ...options.text,
      },
      ...options,
    };

    return addElement(element);
  }

  /**
   * Add shape element
   */
  function addShape(
    shapeType: ShapeProperties['shapeType'] = 'rectangle',
    options: Partial<WysiwygSlideElement> = {}
  ): WysiwygSlideElement {
    const element: WysiwygSlideElement = {
      id: uuid(),
      type: 'shape',
      x: options.x ?? 20,
      y: options.y ?? 20,
      width: options.width ?? 20,
      height: options.height ?? 15,
      rotation: 0,
      zIndex: getNextZIndex(),
      fill: options.fill ?? '#E5E7EB',
      stroke: options.stroke ?? '#9CA3AF',
      strokeWidth: options.strokeWidth ?? 1,
      shape: {
        shapeType,
        cornerRadius: shapeType === 'rounded-rectangle' ? 10 : 0,
        ...options.shape,
      },
      ...options,
    };

    return addElement(element);
  }

  /**
   * Add image element
   */
  function addImage(url: string, options: Partial<WysiwygSlideElement> = {}): WysiwygSlideElement {
    const element: WysiwygSlideElement = {
      id: uuid(),
      type: 'image',
      x: options.x ?? 25,
      y: options.y ?? 20,
      width: options.width ?? 30,
      height: options.height ?? 30,
      rotation: 0,
      zIndex: getNextZIndex(),
      image: {
        url,
        fit: 'contain',
        ...options.image,
      },
      ...options,
    };

    return addElement(element);
  }

  /**
   * Add chart element
   */
  function addChart(options: Partial<WysiwygSlideElement> = {}): WysiwygSlideElement {
    const element: WysiwygSlideElement = {
      id: uuid(),
      type: 'chart',
      x: options.x ?? 15,
      y: options.y ?? 25,
      width: options.width ?? 50,
      height: options.height ?? 40,
      rotation: 0,
      zIndex: getNextZIndex(),
      chart: {
        chartType: 'bar',
        title: 'Chart Title',
        labels: ['Category A', 'Category B', 'Category C', 'Category D'],
        datasets: [
          {
            label: 'Series 1',
            data: [65, 59, 80, 81],
          },
        ],
        showLegend: true,
        showGrid: true,
        ...options.chart,
      },
      ...options,
    };

    return addElement(element);
  }

  /**
   * Add table element
   */
  function addTable(options: Partial<WysiwygSlideElement> = {}): WysiwygSlideElement {
    const element: WysiwygSlideElement = {
      id: uuid(),
      type: 'table',
      x: options.x ?? 10,
      y: options.y ?? 30,
      width: options.width ?? 60,
      height: options.height ?? 35,
      rotation: 0,
      zIndex: getNextZIndex(),
      table: {
        title: 'Table',
        columns: [
          { key: 'col1', label: 'Column 1' },
          { key: 'col2', label: 'Column 2' },
          { key: 'col3', label: 'Column 3' },
        ],
        rows: [
          { col1: 'Row 1', col2: 'Data', col3: 'Value' },
          { col1: 'Row 2', col2: 'Data', col3: 'Value' },
          { col1: 'Row 3', col2: 'Data', col3: 'Value' },
        ],
        headerBackground: '#F3F4F6',
        headerColor: '#111827',
        alternateRowColors: true,
        ...options.table,
      },
      ...options,
    };

    return addElement(element);
  }

  /**
   * Update element
   */
  function updateElement(id: string, updates: Partial<WysiwygSlideElement>): void {
    if (!slide.value) return;

    const elementIndex = slide.value.elements.findIndex((el) => el.id === id);
    if (elementIndex === -1) return;

    // Update element data
    const element = slide.value.elements[elementIndex];
    Object.assign(element, updates);

    // Update fabric object
    const fabricObj = fabricObjectMap.get(id);
    if (fabricObj) {
      updateFabricObject(fabricObj, element);
    }

    onElementsChange?.(slide.value.elements);
  }

  /**
   * Update fabric object from element data
   */
  function updateFabricObject(obj: FabricObject, element: WysiwygSlideElement): void {
    const canvas = canvasInstance.canvas.value;
    if (!canvas) return;

    const canvasWidth = canvas.width || 960;
    const canvasHeight = canvas.height || 540;

    const x = percentToPixels(element.x, canvasWidth);
    const y = percentToPixels(element.y, canvasHeight);
    const width = percentToPixels(element.width, canvasWidth);
    const height = percentToPixels(element.height, canvasHeight);

    obj.set({
      left: x,
      top: y,
      angle: element.rotation || 0,
      opacity: element.opacity ?? 1,
      selectable: !element.locked,
      evented: !element.locked,
      visible: !element.hidden,
    });

    // Update type-specific properties
    if (element.type === 'text' && element.text && obj instanceof Textbox) {
      obj.set({
        width: width, // Width must be set for text alignment to work
        text: element.text.content,
        fontSize: element.text.fontSize,
        fontFamily: element.text.fontFamily,
        fontWeight: element.text.fontWeight === 'bold' ? 'bold' : 'normal',
        fontStyle: element.text.fontStyle === 'italic' ? 'italic' : 'normal',
        fill: element.text.color,
        textAlign: element.text.textAlign,
        lineHeight: element.text.lineHeight,
        charSpacing: (element.text.letterSpacing || 0) * 10,
        underline: element.text.textDecoration === 'underline',
        linethrough: element.text.textDecoration === 'line-through',
      });
      // Force text re-initialization for alignment to take effect
      obj.initDimensions();
    }

    if (element.type === 'shape') {
      obj.set({
        fill: element.fill,
        stroke: element.stroke,
        strokeWidth: element.strokeWidth,
      });

      // Update dimensions for shapes
      if (obj instanceof Rect || obj instanceof Triangle) {
        obj.set({ width, height });
      }
      if (obj instanceof Ellipse) {
        obj.set({ rx: width / 2, ry: height / 2 });
      }
    }

    obj.setCoords();
    canvas.renderAll();
  }

  /**
   * Remove element
   */
  function removeElement(id: string): void {
    if (!slide.value) return;

    const elementIndex = slide.value.elements.findIndex((el) => el.id === id);
    if (elementIndex === -1) return;

    // Remove from slide data
    slide.value.elements.splice(elementIndex, 1);

    // Remove fabric object
    const fabricObj = fabricObjectMap.get(id);
    if (fabricObj) {
      const canvas = canvasInstance.canvas.value;
      if (canvas) {
        canvas.remove(fabricObj);
        canvas.renderAll();
      }
      fabricObjectMap.delete(id);
    }

    onElementsChange?.(slide.value.elements);
  }

  /**
   * Remove selected elements
   */
  function removeSelected(): void {
    const canvas = canvasInstance.canvas.value;
    if (!canvas) return;

    const activeObj = canvas.getActiveObject();
    if (!activeObj) return;

    if (activeObj instanceof ActiveSelection) {
      // Multiple selection
      const objects = activeObj.getObjects();
      objects.forEach((obj) => {
        const id = (obj as any).elementId;
        if (id) removeElement(id);
      });
      canvas.discardActiveObject();
    } else {
      // Single selection
      const id = (activeObj as any).elementId;
      if (id) removeElement(id);
    }
  }

  /**
   * Duplicate element
   */
  function duplicateElement(id: string): WysiwygSlideElement | null {
    if (!slide.value) return null;

    const element = slide.value.elements.find((el) => el.id === id);
    if (!element) return null;

    const duplicate: WysiwygSlideElement = {
      ...JSON.parse(JSON.stringify(element)),
      id: uuid(),
      x: element.x + 2,
      y: element.y + 2,
      zIndex: getNextZIndex(),
    };

    return addElement(duplicate);
  }

  /**
   * Duplicate selected elements
   */
  function duplicateSelected(): WysiwygSlideElement[] {
    const canvas = canvasInstance.canvas.value;
    if (!canvas) return [];

    const activeObj = canvas.getActiveObject();
    if (!activeObj) return [];

    const duplicated: WysiwygSlideElement[] = [];

    if (activeObj instanceof ActiveSelection) {
      const objects = activeObj.getObjects();
      objects.forEach((obj) => {
        const id = (obj as any).elementId;
        if (id) {
          const dup = duplicateElement(id);
          if (dup) duplicated.push(dup);
        }
      });
    } else {
      const id = (activeObj as any).elementId;
      if (id) {
        const dup = duplicateElement(id);
        if (dup) duplicated.push(dup);
      }
    }

    return duplicated;
  }

  /**
   * Get element by ID
   */
  function getElementById(id: string): WysiwygSlideElement | undefined {
    return slide.value?.elements.find((el) => el.id === id);
  }

  /**
   * Get fabric object by element ID
   */
  function getFabricObject(id: string): FabricObject | undefined {
    return fabricObjectMap.get(id);
  }

  /**
   * Z-index operations
   */
  function bringToFront(id: string): void {
    if (!slide.value) return;

    const element = getElementById(id);
    if (!element) return;

    const maxZ = getNextZIndex();
    element.zIndex = maxZ;

    const fabricObj = fabricObjectMap.get(id);
    if (fabricObj) {
      canvasInstance.canvas.value?.bringObjectToFront(fabricObj);
      canvasInstance.render();
    }

    onElementsChange?.(slide.value.elements);
  }

  function sendToBack(id: string): void {
    if (!slide.value) return;

    const element = getElementById(id);
    if (!element) return;

    // Find minimum z-index and set element below it
    const minZ = Math.min(...slide.value.elements.map((el) => el.zIndex));
    element.zIndex = minZ - 1;

    const fabricObj = fabricObjectMap.get(id);
    if (fabricObj) {
      canvasInstance.canvas.value?.sendObjectToBack(fabricObj);
      canvasInstance.render();
    }

    onElementsChange?.(slide.value.elements);
  }

  function bringForward(id: string): void {
    const fabricObj = fabricObjectMap.get(id);
    if (fabricObj) {
      canvasInstance.canvas.value?.bringObjectForward(fabricObj);
      canvasInstance.render();
    }
  }

  function sendBackward(id: string): void {
    const fabricObj = fabricObjectMap.get(id);
    if (fabricObj) {
      canvasInstance.canvas.value?.sendObjectBackwards(fabricObj);
      canvasInstance.render();
    }
  }

  /**
   * Sync fabric object position back to element data
   */
  function syncFromFabricObject(id: string): void {
    if (!slide.value) return;

    const fabricObj = fabricObjectMap.get(id);
    if (!fabricObj) return;

    const canvas = canvasInstance.canvas.value;
    if (!canvas) return;

    const element = getElementById(id);
    if (!element) return;

    const canvasWidth = canvas.width || 960;
    const canvasHeight = canvas.height || 540;

    // Use object's actual position and dimensions, not bounding rect
    // getBoundingRect() can return incorrect values for text and transformed objects
    const left = fabricObj.left ?? 0;
    const top = fabricObj.top ?? 0;

    // For width/height, we need to account for scaling
    const scaleX = fabricObj.scaleX ?? 1;
    const scaleY = fabricObj.scaleY ?? 1;
    const objWidth = (fabricObj.width ?? 0) * scaleX;
    const objHeight = (fabricObj.height ?? 0) * scaleY;

    element.x = pixelsToPercent(left, canvasWidth);
    element.y = pixelsToPercent(top, canvasHeight);
    element.width = pixelsToPercent(objWidth, canvasWidth);
    element.height = pixelsToPercent(objHeight, canvasHeight);
    element.rotation = fabricObj.angle || 0;

    // Sync text content if it's a textbox
    if (element.type === 'text' && fabricObj instanceof Textbox && element.text) {
      element.text.content = fabricObj.text || '';
    }

    onElementsChange?.(slide.value.elements);
  }

  /**
   * Load elements from slide data
   */
  function loadElements(): void {
    const canvas = canvasInstance.canvas.value;
    if (!canvas || !slide.value) return;

    // Clear existing objects (except background and grid)
    const objectsToRemove = canvas.getObjects().filter(
      (obj) => (obj as any).elementId !== undefined
    );
    objectsToRemove.forEach((obj) => canvas.remove(obj));
    fabricObjectMap.clear();

    // Add elements sorted by z-index
    const sortedElements = [...slide.value.elements].sort((a, b) => a.zIndex - b.zIndex);

    sortedElements.forEach((element) => {
      const fabricObj = createFabricObject(element);
      if (fabricObj) {
        fabricObj.set({
          selectable: element.locked !== true,
          evented: element.locked !== true,
          hasControls: true,
          hasBorders: true,
        });
        canvas.add(fabricObj);
        fabricObjectMap.set(element.id, fabricObj);
      }
    });

    canvas.selection = true;
    canvas.skipTargetFind = false;
    canvas.forEachObject((obj: FabricObject) => {
      obj.set({
        selectable: true,
        evented: true,
      });
    });
    canvas.renderAll();
  }

  /**
   * Clear all elements
   */
  function clearElements(): void {
    if (!slide.value) return;

    slide.value.elements = [];

    const canvas = canvasInstance.canvas.value;
    if (canvas) {
      const objectsToRemove = canvas.getObjects().filter(
        (obj) => (obj as any).elementId !== undefined
      );
      objectsToRemove.forEach((obj) => canvas.remove(obj));
      canvas.renderAll();
    }

    fabricObjectMap.clear();
    onElementsChange?.(slide.value.elements);
  }

  return {
    // Element CRUD
    addElement,
    addText,
    addShape,
    addImage,
    addChart,
    addTable,
    updateElement,
    removeElement,
    removeSelected,
    duplicateElement,
    duplicateSelected,

    // Element queries
    getElementById,
    getFabricObject,
    getNextZIndex,

    // Z-index operations
    bringToFront,
    sendToBack,
    bringForward,
    sendBackward,

    // Sync
    syncFromFabricObject,
    loadElements,
    clearElements,

    // Utilities
    percentToPixels,
    pixelsToPercent,
  };
}

export type UseElementsReturn = ReturnType<typeof useElements>;
