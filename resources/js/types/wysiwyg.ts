/**
 * WYSIWYG Slide Editor Type Definitions
 * Comprehensive types for the defense deck editor system
 */

// ============================================================================
// Element Types
// ============================================================================

export type ElementType = 'text' | 'shape' | 'image' | 'chart' | 'table';
export type ShapeType = 'rectangle' | 'circle' | 'triangle' | 'arrow' | 'line' | 'rounded-rectangle';
export type ImageFit = 'contain' | 'cover' | 'fill';
export type TextAlign = 'left' | 'center' | 'right' | 'justify';
export type FontWeight = 'normal' | 'bold';
export type FontStyle = 'normal' | 'italic';
export type ChartType = 'bar' | 'line' | 'pie' | 'scatter' | 'area' | 'doughnut';

// ============================================================================
// Element Interfaces
// ============================================================================

export interface TextProperties {
  content: string;
  fontFamily: string;
  fontSize: number;
  fontWeight: FontWeight;
  fontStyle: FontStyle;
  textDecoration?: 'none' | 'underline' | 'line-through';
  textAlign: TextAlign;
  verticalAlign?: 'top' | 'middle' | 'bottom';
  color: string;
  lineHeight: number;
  letterSpacing: number;
}

export interface ShapeProperties {
  shapeType: ShapeType;
  cornerRadius?: number;
}

export interface ImageProperties {
  url: string;
  fit: ImageFit;
  filters?: string[];
  originalWidth?: number;
  originalHeight?: number;
}

export interface ChartProperties {
  chartType: ChartType;
  title?: string;
  labels: string[];
  datasets: ChartDataset[];
  showLegend?: boolean;
  showGrid?: boolean;
}

export interface ChartDataset {
  label: string;
  data: number[];
  backgroundColor?: string | string[];
  borderColor?: string;
  borderWidth?: number;
}

export interface TableProperties {
  title?: string;
  columns: TableColumn[];
  rows: TableRow[];
  headerBackground?: string;
  headerColor?: string;
  alternateRowColors?: boolean;
}

export interface TableColumn {
  key: string;
  label: string;
  width?: number;
  align?: TextAlign;
}

export interface TableRow {
  [key: string]: string | number;
}

export interface Shadow {
  color: string;
  blur: number;
  offsetX: number;
  offsetY: number;
}

// ============================================================================
// Main Element Interface
// ============================================================================

export interface WysiwygSlideElement {
  id: string;
  type: ElementType;

  // Position & Transform (all values in percentage 0-100)
  x: number;
  y: number;
  width: number;
  height: number;
  rotation: number;
  zIndex: number;

  // Styling
  fill?: string;
  stroke?: string;
  strokeWidth?: number;
  opacity?: number;
  shadow?: Shadow;

  // Element-specific properties
  text?: TextProperties;
  shape?: ShapeProperties;
  image?: ImageProperties;
  chart?: ChartProperties;
  table?: TableProperties;

  // State
  locked?: boolean;
  hidden?: boolean;
  groupId?: string;
  isThemeOverridden?: boolean;
}

// ============================================================================
// Slide Interface
// ============================================================================

export interface SlideTransition {
  type: 'fade' | 'slide' | 'zoom' | 'flip' | 'none';
  duration: number;
  direction?: 'left' | 'right' | 'up' | 'down';
}

export interface WysiwygSlide {
  id: string;
  title: string;
  elements: WysiwygSlideElement[];
  themeId?: string;
  backgroundColor: string;
  backgroundImage?: string;
  backgroundOpacity?: number;
  speaker_notes?: string;
  transition?: SlideTransition;
  duration?: number;

  // Legacy compatibility
  legacy?: LegacySlideData;
}

export interface LegacySlideData {
  bullets?: string[];
  paragraphs?: string[];
  layout?: string;
  content_type?: string;
  image_url?: string;
  image_position_x?: number;
  image_position_y?: number;
  image_scale?: number;
  image_fit?: string;
  visual_hint?: string;
  chart?: any;
  table?: any;
}

// ============================================================================
// Theme System
// ============================================================================

export interface ThemeColors {
  primary: string;
  secondary: string;
  accent: string;
  background: string;
  text: string;
  textSecondary: string;
}

export interface ThemeFont {
  family: string;
  weight: number;
  size: number;
}

export interface ThemeFonts {
  heading: ThemeFont;
  body: ThemeFont;
}

export interface SlideLayoutTemplate {
  name: string;
  description: string;
  backgroundColor?: string;
  backgroundImage?: string;
  elements: Partial<WysiwygSlideElement>[];
}

export interface Theme {
  id: string;
  name: string;
  description: string;
  thumbnail?: string;
  colors: ThemeColors;
  fonts: ThemeFonts;
  slideLayouts?: {
    title?: SlideLayoutTemplate;
    content?: SlideLayoutTemplate;
    twoColumn?: SlideLayoutTemplate;
    imageLeft?: SlideLayoutTemplate;
    imageRight?: SlideLayoutTemplate;
  };
  elementDefaults?: {
    text?: Partial<WysiwygSlideElement>;
    shape?: Partial<WysiwygSlideElement>;
    image?: Partial<WysiwygSlideElement>;
  };
}

// ============================================================================
// Editor State
// ============================================================================

export interface EditorState {
  slides: WysiwygSlide[];
  activeSlideIndex: number;
  selectedElementIds: string[];
  clipboard: WysiwygSlideElement[] | null;
  zoom: number;
  pan: { x: number; y: number };
  gridEnabled: boolean;
  snapToGrid: boolean;
  gridSize: number;
  isDragging: boolean;
  isResizing: boolean;
  isRotating: boolean;
}

export interface HistoryState {
  slides: WysiwygSlide[];
  activeSlideIndex: number;
  timestamp: number;
}

export interface HistoryManager {
  past: HistoryState[];
  present: HistoryState;
  future: HistoryState[];
  maxSize: number;
}

// ============================================================================
// Toolbar & Tools
// ============================================================================

export interface Tool {
  id: string;
  icon: string;
  label: string;
  shortcut?: string;
  action: () => void;
  disabled?: boolean;
  active?: boolean;
}

export interface ToolbarSection {
  name: string;
  tools: Tool[];
}

export interface ContextMenuItem {
  id: string;
  label: string;
  icon?: string;
  shortcut?: string;
  action: () => void;
  separator?: boolean;
  disabled?: boolean;
  children?: ContextMenuItem[];
}

// ============================================================================
// Inspector Properties
// ============================================================================

export type InspectorPropertyType = 'number' | 'text' | 'color' | 'select' | 'slider' | 'toggle' | 'font';

export interface SelectOption {
  value: string | number;
  label: string;
}

export interface InspectorProperty {
  key: string;
  label: string;
  type: InspectorPropertyType;
  value: any;
  options?: SelectOption[];
  min?: number;
  max?: number;
  step?: number;
  unit?: string;
  onChange: (value: any) => void;
}

export interface InspectorSection {
  title: string;
  icon?: string;
  expanded?: boolean;
  properties: InspectorProperty[];
}

// ============================================================================
// Canvas Events
// ============================================================================

export interface CanvasMouseEvent {
  x: number;
  y: number;
  button: number;
  shiftKey: boolean;
  ctrlKey: boolean;
  altKey: boolean;
  target?: WysiwygSlideElement;
}

export interface CanvasSelectionEvent {
  selected: WysiwygSlideElement[];
  deselected: WysiwygSlideElement[];
}

export interface CanvasModifyEvent {
  element: WysiwygSlideElement;
  changes: Partial<WysiwygSlideElement>;
}

// ============================================================================
// Export Types
// ============================================================================

export interface ExportOptions {
  format: 'pptx' | 'pdf' | 'png';
  includeNotes?: boolean;
  quality?: number;
  slideRange?: { start: number; end: number };
}

export interface ExportProgress {
  status: 'preparing' | 'rendering' | 'finalizing' | 'complete' | 'failed';
  progress: number;
  message?: string;
  error?: string;
}

// ============================================================================
// Alignment & Distribution
// ============================================================================

export type HorizontalAlign = 'left' | 'center' | 'right';
export type VerticalAlign = 'top' | 'middle' | 'bottom';
export type DistributeDirection = 'horizontal' | 'vertical';

// ============================================================================
// Default Values
// ============================================================================

export const DEFAULT_TEXT_PROPERTIES: TextProperties = {
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

export const DEFAULT_SHAPE_PROPERTIES: ShapeProperties = {
  shapeType: 'rectangle',
  cornerRadius: 0,
};

export const DEFAULT_ELEMENT_STYLES = {
  fill: '#E5E7EB',
  stroke: '#9CA3AF',
  strokeWidth: 1,
  opacity: 1,
};

export const DEFAULT_SLIDE: Omit<WysiwygSlide, 'id'> = {
  title: 'New Slide',
  elements: [],
  backgroundColor: '#FFFFFF',
};

// ============================================================================
// Font Options
// ============================================================================

export const FONT_FAMILIES = [
  'Arial',
  'Arial Black',
  'Calibri',
  'Cambria',
  'Comic Sans MS',
  'Courier New',
  'Georgia',
  'Helvetica',
  'Impact',
  'Lucida Console',
  'Palatino Linotype',
  'Tahoma',
  'Times New Roman',
  'Trebuchet MS',
  'Verdana',
];

export const FONT_SIZES = [8, 9, 10, 11, 12, 14, 16, 18, 20, 24, 28, 32, 36, 40, 48, 56, 64, 72];

// ============================================================================
// Utility Types
// ============================================================================

export type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};

export type ElementUpdatePayload = DeepPartial<WysiwygSlideElement> & { id: string };
