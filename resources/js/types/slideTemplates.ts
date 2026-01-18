/**
 * Slide Template Type Definitions
 * Pre-built layouts for defense deck presentations
 */

import type { WysiwygSlide, WysiwygSlideElement } from './wysiwyg';

export type TemplateCategory =
  | 'title'
  | 'content'
  | 'comparison'
  | 'data'
  | 'media'
  | 'ending';

export interface SlideTemplate {
  id: string;
  name: string;
  description: string;
  category: TemplateCategory;
  thumbnail?: string;
  elements: Omit<WysiwygSlideElement, 'id'>[];
  backgroundColor?: string;
  backgroundImage?: string;
}

export interface TemplateGroup {
  category: TemplateCategory;
  label: string;
  description: string;
  templates: SlideTemplate[];
}

// Helper to create unique IDs for template elements
export function instantiateTemplate(template: SlideTemplate): WysiwygSlide {
  const crypto = window.crypto || (window as any).msCrypto;
  const generateId = () => crypto.randomUUID();

  return {
    id: generateId(),
    title: template.name,
    elements: template.elements.map(el => ({
      ...el,
      id: generateId(),
    })),
    backgroundColor: template.backgroundColor || '#FFFFFF',
    backgroundImage: template.backgroundImage,
  };
}
