/**
 * useTheme - Theme System Composable
 * Manages loading, applying, and switching themes for the slide editor
 */

import { ref, computed, type Ref } from 'vue';
import type { WysiwygSlide, WysiwygSlideElement } from '@/types/wysiwyg';

// ============================================================================
// Theme Types (matches themes.json structure)
// ============================================================================

export interface ThemeColors {
  primary: string;
  secondary: string;
  accent: string;
  background: string;
  surface: string;
  text: string;
  textSecondary: string;
  textInverse: string;
}

export interface ThemeFontSizes {
  title?: number;
  h1?: number;
  h2?: number;
  h3?: number;
  large?: number;
  normal?: number;
  small?: number;
}

export interface ThemeFontConfig {
  family: string;
  weight: number;
  sizes: ThemeFontSizes;
}

export interface ThemeFonts {
  heading: ThemeFontConfig;
  body: ThemeFontConfig;
}

export interface ThemeSlideStyles {
  titleColor: string;
  subtitleColor?: string;
  headingColor?: string;
  bodyColor?: string;
  backgroundColor: string;
}

export interface ThemeShapeStyles {
  defaultFill: string;
  defaultStroke: string;
  accentFill: string;
}

export interface ThemeChartStyles {
  colors: string[];
}

export interface ThemeElements {
  titleSlide: ThemeSlideStyles;
  contentSlide: ThemeSlideStyles;
  shapes: ThemeShapeStyles;
  charts: ThemeChartStyles;
}

export interface EditorTheme {
  id: string;
  name: string;
  description: string;
  colors: ThemeColors;
  fonts: ThemeFonts;
  elements: ThemeElements;
}

export interface ThemesConfig {
  themes: EditorTheme[];
  defaultThemeId: string;
}

// ============================================================================
// Composable Options
// ============================================================================

export interface UseThemeOptions {
  slides?: Ref<WysiwygSlide[]>;
  onThemeChange?: (theme: EditorTheme) => void;
}

// ============================================================================
// Composable
// ============================================================================

export function useTheme(options: UseThemeOptions = {}) {
  const { slides, onThemeChange } = options;

  // State
  const themes = ref<EditorTheme[]>([]);
  const currentThemeId = ref<string>('modern');
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  /**
   * Load themes from JSON file
   */
  async function loadThemes(): Promise<void> {
    if (themes.value.length > 0) return; // Already loaded

    isLoading.value = true;
    error.value = null;

    try {
      const response = await fetch('/themes/themes.json');
      if (!response.ok) {
        throw new Error(`Failed to load themes: ${response.statusText}`);
      }

      const config: ThemesConfig = await response.json();
      themes.value = config.themes;
      currentThemeId.value = config.defaultThemeId;
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Failed to load themes';
      console.error('Error loading themes:', e);

      // Load fallback theme
      themes.value = [getDefaultTheme()];
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Get default fallback theme
   */
  function getDefaultTheme(): EditorTheme {
    return {
      id: 'default',
      name: 'Default',
      description: 'Default theme',
      colors: {
        primary: '#3B82F6',
        secondary: '#6366F1',
        accent: '#10B981',
        background: '#FFFFFF',
        surface: '#F3F4F6',
        text: '#111827',
        textSecondary: '#6B7280',
        textInverse: '#FFFFFF',
      },
      fonts: {
        heading: {
          family: 'Arial',
          weight: 700,
          sizes: { title: 44, h1: 36, h2: 28, h3: 22 },
        },
        body: {
          family: 'Arial',
          weight: 400,
          sizes: { large: 18, normal: 16, small: 14 },
        },
      },
      elements: {
        titleSlide: {
          titleColor: '#111827',
          subtitleColor: '#6B7280',
          backgroundColor: '#FFFFFF',
        },
        contentSlide: {
          headingColor: '#111827',
          bodyColor: '#374151',
          backgroundColor: '#FFFFFF',
        },
        shapes: {
          defaultFill: '#E5E7EB',
          defaultStroke: '#9CA3AF',
          accentFill: '#3B82F6',
        },
        charts: {
          colors: ['#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#10B981', '#F59E0B'],
        },
      },
    };
  }

  /**
   * Get current theme
   */
  const currentTheme = computed<EditorTheme | null>(() => {
    return themes.value.find((t) => t.id === currentThemeId.value) || themes.value[0] || null;
  });

  /**
   * Set current theme by ID
   */
  function setTheme(themeId: string): void {
    const theme = themes.value.find((t) => t.id === themeId);
    if (theme) {
      currentThemeId.value = themeId;
      onThemeChange?.(theme);
    }
  }

  /**
   * Apply theme to a single slide
   */
  function applyThemeToSlide(
    slide: WysiwygSlide,
    theme: EditorTheme,
    options: { preserveCustomizations?: boolean } = {}
  ): WysiwygSlide {
    const { preserveCustomizations = true } = options;

    // Update slide background
    const updatedSlide: WysiwygSlide = {
      ...slide,
      themeId: theme.id,
      backgroundColor: theme.elements.contentSlide.backgroundColor,
    };

    // Update elements
    updatedSlide.elements = slide.elements.map((element) => {
      // Skip if element has custom overrides and we want to preserve them
      if (preserveCustomizations && element.isThemeOverridden) {
        return element;
      }

      return applyThemeToElement(element, theme);
    });

    return updatedSlide;
  }

  /**
   * Apply theme to a single element
   */
  function applyThemeToElement(
    element: WysiwygSlideElement,
    theme: EditorTheme
  ): WysiwygSlideElement {
    const updated = { ...element };

    switch (element.type) {
      case 'text':
        if (updated.text) {
          // Determine if this is a heading or body text based on font size
          const isHeading = (updated.text.fontSize || 18) >= 24;

          updated.text = {
            ...updated.text,
            fontFamily: isHeading ? theme.fonts.heading.family : theme.fonts.body.family,
            color: isHeading
              ? theme.elements.contentSlide.headingColor || theme.colors.primary
              : theme.elements.contentSlide.bodyColor || theme.colors.text,
          };
        }
        break;

      case 'shape':
        updated.fill = theme.elements.shapes.defaultFill;
        updated.stroke = theme.elements.shapes.defaultStroke;
        break;

      case 'chart':
        if (updated.chart) {
          updated.chart = {
            ...updated.chart,
            datasets: updated.chart.datasets.map((ds, index) => ({
              ...ds,
              backgroundColor: theme.elements.charts.colors[index % theme.elements.charts.colors.length],
              borderColor: theme.elements.charts.colors[index % theme.elements.charts.colors.length],
            })),
          };
        }
        break;

      case 'table':
        if (updated.table) {
          updated.table = {
            ...updated.table,
            headerBackground: theme.colors.surface,
            headerColor: theme.colors.text,
          };
        }
        break;
    }

    return updated;
  }

  /**
   * Apply theme to all slides
   */
  function applyThemeToAllSlides(
    slideList: WysiwygSlide[],
    themeId: string,
    options: { preserveCustomizations?: boolean } = {}
  ): WysiwygSlide[] {
    const theme = themes.value.find((t) => t.id === themeId);
    if (!theme) return slideList;

    return slideList.map((slide) => applyThemeToSlide(slide, theme, options));
  }

  /**
   * Apply current theme to provided slides ref
   */
  function applyCurrentTheme(options: { preserveCustomizations?: boolean } = {}): void {
    if (!slides?.value || !currentTheme.value) return;

    slides.value = applyThemeToAllSlides(slides.value, currentThemeId.value, options);
  }

  /**
   * Get theme colors for use in components
   */
  function getThemeColors(): ThemeColors | null {
    return currentTheme.value?.colors || null;
  }

  /**
   * Get theme fonts for use in components
   */
  function getThemeFonts(): ThemeFonts | null {
    return currentTheme.value?.fonts || null;
  }

  /**
   * Create a new text element with theme styling
   */
  function createThemedTextElement(
    content: string,
    variant: 'title' | 'heading' | 'body' = 'body'
  ): Partial<WysiwygSlideElement> {
    const theme = currentTheme.value;
    if (!theme) {
      return {
        type: 'text',
        text: { content, fontFamily: 'Arial', fontSize: 18, fontWeight: 'normal', fontStyle: 'normal', textAlign: 'left', color: '#111827', lineHeight: 1.4, letterSpacing: 0 },
      };
    }

    const fontConfig = variant === 'body' ? theme.fonts.body : theme.fonts.heading;
    const fontSize = variant === 'title'
      ? fontConfig.sizes.title || 44
      : variant === 'heading'
        ? fontConfig.sizes.h1 || 36
        : fontConfig.sizes.normal || 16;

    const color = variant === 'body'
      ? theme.elements.contentSlide.bodyColor || theme.colors.text
      : theme.elements.contentSlide.headingColor || theme.colors.primary;

    return {
      type: 'text',
      text: {
        content,
        fontFamily: fontConfig.family,
        fontSize,
        fontWeight: variant === 'body' ? 'normal' : 'bold',
        fontStyle: 'normal',
        textAlign: variant === 'title' ? 'center' : 'left',
        color,
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    };
  }

  /**
   * Create a new shape element with theme styling
   */
  function createThemedShapeElement(
    shapeType: 'rectangle' | 'circle' | 'triangle' | 'arrow' | 'line' | 'rounded-rectangle' = 'rectangle',
    useAccent = false
  ): Partial<WysiwygSlideElement> {
    const theme = currentTheme.value;
    if (!theme) {
      return {
        type: 'shape',
        fill: '#E5E7EB',
        stroke: '#9CA3AF',
        shape: { shapeType },
      };
    }

    return {
      type: 'shape',
      fill: useAccent ? theme.elements.shapes.accentFill : theme.elements.shapes.defaultFill,
      stroke: theme.elements.shapes.defaultStroke,
      shape: { shapeType, cornerRadius: shapeType === 'rounded-rectangle' ? 10 : 0 },
    };
  }

  /**
   * Get chart colors from theme
   */
  function getChartColors(): string[] {
    return currentTheme.value?.elements.charts.colors || [
      '#3B82F6', '#6366F1', '#8B5CF6', '#EC4899', '#10B981', '#F59E0B',
    ];
  }

  return {
    // State
    themes,
    currentThemeId,
    currentTheme,
    isLoading,
    error,

    // Actions
    loadThemes,
    setTheme,
    applyThemeToSlide,
    applyThemeToElement,
    applyThemeToAllSlides,
    applyCurrentTheme,

    // Utilities
    getThemeColors,
    getThemeFonts,
    getChartColors,
    createThemedTextElement,
    createThemedShapeElement,
    getDefaultTheme,
  };
}

export type UseThemeReturn = ReturnType<typeof useTheme>;
