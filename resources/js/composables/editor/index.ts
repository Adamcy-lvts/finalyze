/**
 * Editor Composables Index
 * Re-exports all editor composables for convenient imports
 */

export { useCanvas, type UseCanvasReturn, type UseCanvasOptions, type CanvasState } from './useCanvas';
export { useElements, type UseElementsReturn, type UseElementsOptions } from './useElements';
export { useSelection, type UseSelectionReturn, type UseSelectionOptions } from './useSelection';
export { useHistory, createDebouncedPush, type UseHistoryReturn, type UseHistoryOptions } from './useHistory';
export { useTheme, type UseThemeReturn, type UseThemeOptions, type EditorTheme, type ThemeColors, type ThemeFonts } from './useTheme';
export { usePresentation, transitionStyles, type UsePresentationReturn, type UsePresentationOptions, type TransitionType, type TransitionConfig } from './usePresentation';
export { useSlideTemplates } from './useSlideTemplates';
