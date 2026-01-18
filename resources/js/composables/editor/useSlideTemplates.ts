/**
 * useSlideTemplates - Pre-built slide layouts for defense decks
 * Provides a collection of professional templates for common slide types
 */

import { ref, computed } from 'vue';
import type {
  SlideTemplate,
  TemplateGroup,
  TemplateCategory,
} from '@/types/slideTemplates';
import { instantiateTemplate } from '@/types/slideTemplates';
import type { WysiwygSlide } from '@/types/wysiwyg';

// ============================================================================
// Title Slides
// ============================================================================

const titleSlideTemplate: SlideTemplate = {
  id: 'title-centered',
  name: 'Title Slide',
  description: 'Centered title with subtitle',
  category: 'title',
  backgroundColor: '#1E1B4B',
  elements: [
    {
      type: 'text',
      x: 10,
      y: 35,
      width: 80,
      height: 15,
      text: {
        content: 'Presentation Title',
        fontFamily: 'Inter',
        fontSize: 48,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#FFFFFF',
        lineHeight: 1.2,
        letterSpacing: -0.5,
      },
    },
    {
      type: 'text',
      x: 15,
      y: 52,
      width: 70,
      height: 8,
      text: {
        content: 'Subtitle or Author Name',
        fontFamily: 'Inter',
        fontSize: 24,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#A5B4FC',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
    {
      type: 'shape',
      x: 35,
      y: 48,
      width: 30,
      height: 0.5,
      shapeType: 'rectangle',
      fill: '#6366F1',
      stroke: 'transparent',
      strokeWidth: 0,
    },
  ],
};

const titleWithImageTemplate: SlideTemplate = {
  id: 'title-with-image',
  name: 'Title with Image',
  description: 'Title slide with background image area',
  category: 'title',
  backgroundColor: '#0F172A',
  elements: [
    {
      type: 'shape',
      x: 50,
      y: 0,
      width: 50,
      height: 100,
      shapeType: 'rectangle',
      fill: '#1E293B',
      stroke: 'transparent',
      strokeWidth: 0,
    },
    {
      type: 'text',
      x: 5,
      y: 30,
      width: 42,
      height: 20,
      text: {
        content: 'Your Title Here',
        fontFamily: 'Inter',
        fontSize: 42,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#FFFFFF',
        lineHeight: 1.2,
        letterSpacing: -0.5,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 52,
      width: 42,
      height: 10,
      text: {
        content: 'Supporting description text goes here',
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#94A3B8',
        lineHeight: 1.5,
        letterSpacing: 0,
      },
    },
  ],
};

// ============================================================================
// Content Slides
// ============================================================================

const bulletPointsTemplate: SlideTemplate = {
  id: 'bullet-points',
  name: 'Bullet Points',
  description: 'Title with bullet list',
  category: 'content',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 8,
      width: 90,
      height: 12,
      text: {
        content: 'Section Title',
        fontFamily: 'Inter',
        fontSize: 36,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 25,
      width: 90,
      height: 60,
      text: {
        content: '• First key point about your topic\n\n• Second important insight or finding\n\n• Third supporting argument\n\n• Fourth conclusion or recommendation',
        fontFamily: 'Inter',
        fontSize: 22,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#334155',
        lineHeight: 1.6,
        letterSpacing: 0,
      },
    },
  ],
};

const contentWithImageTemplate: SlideTemplate = {
  id: 'content-with-image',
  name: 'Content + Image',
  description: 'Text content with image on right',
  category: 'content',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 8,
      width: 50,
      height: 10,
      text: {
        content: 'Section Title',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 22,
      width: 48,
      height: 65,
      text: {
        content: 'Add your detailed explanation here. This layout is perfect for when you need to combine textual content with a visual element.\n\n• Supporting point one\n• Supporting point two\n• Supporting point three',
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#475569',
        lineHeight: 1.6,
        letterSpacing: 0,
      },
    },
    {
      type: 'shape',
      x: 55,
      y: 15,
      width: 40,
      height: 70,
      shapeType: 'rectangle',
      fill: '#F1F5F9',
      stroke: '#E2E8F0',
      strokeWidth: 1,
    },
    {
      type: 'text',
      x: 55,
      y: 45,
      width: 40,
      height: 10,
      text: {
        content: 'Image Placeholder',
        fontFamily: 'Inter',
        fontSize: 14,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#94A3B8',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
  ],
};

const twoColumnTemplate: SlideTemplate = {
  id: 'two-column',
  name: 'Two Columns',
  description: 'Split content in two columns',
  category: 'content',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 8,
      width: 90,
      height: 10,
      text: {
        content: 'Two-Column Layout',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 22,
      width: 43,
      height: 8,
      text: {
        content: 'Left Column',
        fontFamily: 'Inter',
        fontSize: 20,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#6366F1',
        lineHeight: 1.3,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 32,
      width: 43,
      height: 55,
      text: {
        content: '• Point one\n\n• Point two\n\n• Point three',
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#475569',
        lineHeight: 1.5,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 52,
      y: 22,
      width: 43,
      height: 8,
      text: {
        content: 'Right Column',
        fontFamily: 'Inter',
        fontSize: 20,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#6366F1',
        lineHeight: 1.3,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 52,
      y: 32,
      width: 43,
      height: 55,
      text: {
        content: '• Point one\n\n• Point two\n\n• Point three',
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#475569',
        lineHeight: 1.5,
        letterSpacing: 0,
      },
    },
  ],
};

// ============================================================================
// Comparison Slides
// ============================================================================

const comparisonTemplate: SlideTemplate = {
  id: 'comparison',
  name: 'Comparison',
  description: 'Side-by-side comparison',
  category: 'comparison',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 8,
      width: 90,
      height: 10,
      text: {
        content: 'Comparison',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'shape',
      x: 5,
      y: 22,
      width: 43,
      height: 70,
      shapeType: 'rectangle',
      fill: '#DCFCE7',
      stroke: '#22C55E',
      strokeWidth: 2,
      cornerRadius: 8,
    },
    {
      type: 'text',
      x: 7,
      y: 25,
      width: 39,
      height: 8,
      text: {
        content: 'Option A',
        fontFamily: 'Inter',
        fontSize: 22,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#166534',
        lineHeight: 1.3,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 7,
      y: 36,
      width: 39,
      height: 50,
      text: {
        content: '• Advantage 1\n• Advantage 2\n• Advantage 3',
        fontFamily: 'Inter',
        fontSize: 16,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#15803D',
        lineHeight: 1.8,
        letterSpacing: 0,
      },
    },
    {
      type: 'shape',
      x: 52,
      y: 22,
      width: 43,
      height: 70,
      shapeType: 'rectangle',
      fill: '#FEE2E2',
      stroke: '#EF4444',
      strokeWidth: 2,
      cornerRadius: 8,
    },
    {
      type: 'text',
      x: 54,
      y: 25,
      width: 39,
      height: 8,
      text: {
        content: 'Option B',
        fontFamily: 'Inter',
        fontSize: 22,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#991B1B',
        lineHeight: 1.3,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 54,
      y: 36,
      width: 39,
      height: 50,
      text: {
        content: '• Disadvantage 1\n• Disadvantage 2\n• Disadvantage 3',
        fontFamily: 'Inter',
        fontSize: 16,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#B91C1C',
        lineHeight: 1.8,
        letterSpacing: 0,
      },
    },
  ],
};

const prosConsTemplate: SlideTemplate = {
  id: 'pros-cons',
  name: 'Pros & Cons',
  description: 'Advantages vs disadvantages',
  category: 'comparison',
  backgroundColor: '#F8FAFC',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 8,
      width: 90,
      height: 10,
      text: {
        content: 'Pros & Cons Analysis',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 22,
      width: 43,
      height: 8,
      text: {
        content: '✓ Pros',
        fontFamily: 'Inter',
        fontSize: 24,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#16A34A',
        lineHeight: 1.3,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 33,
      width: 43,
      height: 55,
      text: {
        content: '• First advantage\n\n• Second advantage\n\n• Third advantage',
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#334155',
        lineHeight: 1.5,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 52,
      y: 22,
      width: 43,
      height: 8,
      text: {
        content: '✗ Cons',
        fontFamily: 'Inter',
        fontSize: 24,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#DC2626',
        lineHeight: 1.3,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 52,
      y: 33,
      width: 43,
      height: 55,
      text: {
        content: '• First disadvantage\n\n• Second disadvantage\n\n• Third disadvantage',
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#334155',
        lineHeight: 1.5,
        letterSpacing: 0,
      },
    },
  ],
};

// ============================================================================
// Data Slides
// ============================================================================

const chartPlaceholderTemplate: SlideTemplate = {
  id: 'chart-placeholder',
  name: 'Chart Slide',
  description: 'Title with chart area',
  category: 'data',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 5,
      width: 90,
      height: 10,
      text: {
        content: 'Data Visualization',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'chart',
      x: 10,
      y: 20,
      width: 80,
      height: 65,
      chart: {
        chartType: 'bar',
        title: '',
        labels: ['Q1', 'Q2', 'Q3', 'Q4'],
        datasets: [
          {
            label: 'Series A',
            data: [65, 78, 90, 81],
            backgroundColor: ['#6366F1', '#8B5CF6', '#A855F7', '#D946EF'],
          },
        ],
        showLegend: true,
        showGrid: true,
      },
    },
  ],
};

const statsTemplate: SlideTemplate = {
  id: 'stats',
  name: 'Key Statistics',
  description: 'Highlight key numbers',
  category: 'data',
  backgroundColor: '#0F172A',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 8,
      width: 90,
      height: 10,
      text: {
        content: 'Key Findings',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#F8FAFC',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    // Stat 1
    {
      type: 'text',
      x: 5,
      y: 30,
      width: 28,
      height: 15,
      text: {
        content: '85%',
        fontFamily: 'Inter',
        fontSize: 56,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#6366F1',
        lineHeight: 1,
        letterSpacing: -1,
      },
    },
    {
      type: 'text',
      x: 5,
      y: 48,
      width: 28,
      height: 10,
      text: {
        content: 'Success Rate',
        fontFamily: 'Inter',
        fontSize: 16,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#94A3B8',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
    // Stat 2
    {
      type: 'text',
      x: 36,
      y: 30,
      width: 28,
      height: 15,
      text: {
        content: '2.5x',
        fontFamily: 'Inter',
        fontSize: 56,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#22C55E',
        lineHeight: 1,
        letterSpacing: -1,
      },
    },
    {
      type: 'text',
      x: 36,
      y: 48,
      width: 28,
      height: 10,
      text: {
        content: 'Improvement',
        fontFamily: 'Inter',
        fontSize: 16,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#94A3B8',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
    // Stat 3
    {
      type: 'text',
      x: 67,
      y: 30,
      width: 28,
      height: 15,
      text: {
        content: '500+',
        fontFamily: 'Inter',
        fontSize: 56,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#F59E0B',
        lineHeight: 1,
        letterSpacing: -1,
      },
    },
    {
      type: 'text',
      x: 67,
      y: 48,
      width: 28,
      height: 10,
      text: {
        content: 'Participants',
        fontFamily: 'Inter',
        fontSize: 16,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#94A3B8',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
  ],
};

const tableTemplate: SlideTemplate = {
  id: 'table',
  name: 'Table Layout',
  description: 'Data table presentation',
  category: 'data',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 5,
      y: 5,
      width: 90,
      height: 10,
      text: {
        content: 'Data Overview',
        fontFamily: 'Inter',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'left',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'table',
      x: 5,
      y: 20,
      width: 90,
      height: 65,
      table: {
        title: '',
        columns: [
          { key: 'category', label: 'Category' },
          { key: 'value1', label: 'Value 1' },
          { key: 'value2', label: 'Value 2' },
          { key: 'change', label: 'Change' },
        ],
        rows: [
          { category: 'Item A', value1: '100', value2: '120', change: '+20%' },
          { category: 'Item B', value1: '85', value2: '95', change: '+12%' },
          { category: 'Item C', value1: '92', value2: '88', change: '-4%' },
          { category: 'Item D', value1: '78', value2: '105', change: '+35%' },
        ],
        headerBackground: '#F1F5F9',
        headerColor: '#1E293B',
        alternateRowColors: true,
      },
    },
  ],
};

// ============================================================================
// Ending Slides
// ============================================================================

const thankYouTemplate: SlideTemplate = {
  id: 'thank-you',
  name: 'Thank You',
  description: 'Closing slide',
  category: 'ending',
  backgroundColor: '#1E1B4B',
  elements: [
    {
      type: 'text',
      x: 10,
      y: 35,
      width: 80,
      height: 15,
      text: {
        content: 'Thank You',
        fontFamily: 'Inter',
        fontSize: 64,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#FFFFFF',
        lineHeight: 1.1,
        letterSpacing: -1,
      },
    },
    {
      type: 'text',
      x: 15,
      y: 55,
      width: 70,
      height: 10,
      text: {
        content: 'Questions & Discussion',
        fontFamily: 'Inter',
        fontSize: 24,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#A5B4FC',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
  ],
};

const questionsTemplate: SlideTemplate = {
  id: 'questions',
  name: 'Q&A Slide',
  description: 'Questions and answers',
  category: 'ending',
  backgroundColor: '#0F172A',
  elements: [
    {
      type: 'shape',
      x: 35,
      y: 25,
      width: 30,
      height: 30,
      shapeType: 'circle',
      fill: '#312E81',
      stroke: '#6366F1',
      strokeWidth: 3,
    },
    {
      type: 'text',
      x: 35,
      y: 32,
      width: 30,
      height: 15,
      text: {
        content: '?',
        fontFamily: 'Inter',
        fontSize: 72,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#A5B4FC',
        lineHeight: 1,
        letterSpacing: 0,
      },
    },
    {
      type: 'text',
      x: 10,
      y: 65,
      width: 80,
      height: 10,
      text: {
        content: 'Questions?',
        fontFamily: 'Inter',
        fontSize: 36,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#F8FAFC',
        lineHeight: 1.2,
        letterSpacing: -0.3,
      },
    },
    {
      type: 'text',
      x: 15,
      y: 78,
      width: 70,
      height: 8,
      text: {
        content: "I'm happy to answer any questions",
        fontFamily: 'Inter',
        fontSize: 18,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#64748B',
        lineHeight: 1.4,
        letterSpacing: 0,
      },
    },
  ],
};

const contactTemplate: SlideTemplate = {
  id: 'contact',
  name: 'Contact Info',
  description: 'Contact details slide',
  category: 'ending',
  backgroundColor: '#FFFFFF',
  elements: [
    {
      type: 'text',
      x: 10,
      y: 20,
      width: 80,
      height: 12,
      text: {
        content: 'Get in Touch',
        fontFamily: 'Inter',
        fontSize: 40,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#1E293B',
        lineHeight: 1.2,
        letterSpacing: -0.5,
      },
    },
    {
      type: 'text',
      x: 20,
      y: 40,
      width: 60,
      height: 40,
      text: {
        content: 'email@example.com\n\nlinkedin.com/in/yourname\n\nyourwebsite.com',
        fontFamily: 'Inter',
        fontSize: 20,
        fontWeight: 'normal',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#6366F1',
        lineHeight: 2,
        letterSpacing: 0,
      },
    },
  ],
};

// ============================================================================
// Composable
// ============================================================================

export function useSlideTemplates() {
  const allTemplates = ref<SlideTemplate[]>([
    // Title
    titleSlideTemplate,
    titleWithImageTemplate,
    // Content
    bulletPointsTemplate,
    contentWithImageTemplate,
    twoColumnTemplate,
    // Comparison
    comparisonTemplate,
    prosConsTemplate,
    // Data
    chartPlaceholderTemplate,
    statsTemplate,
    tableTemplate,
    // Ending
    thankYouTemplate,
    questionsTemplate,
    contactTemplate,
  ]);

  const templateGroups = computed<TemplateGroup[]>(() => [
    {
      category: 'title',
      label: 'Title Slides',
      description: 'Opening and section headers',
      templates: allTemplates.value.filter(t => t.category === 'title'),
    },
    {
      category: 'content',
      label: 'Content',
      description: 'Main content layouts',
      templates: allTemplates.value.filter(t => t.category === 'content'),
    },
    {
      category: 'comparison',
      label: 'Comparison',
      description: 'Compare options or data',
      templates: allTemplates.value.filter(t => t.category === 'comparison'),
    },
    {
      category: 'data',
      label: 'Data & Charts',
      description: 'Visualize data and statistics',
      templates: allTemplates.value.filter(t => t.category === 'data'),
    },
    {
      category: 'ending',
      label: 'Ending',
      description: 'Closing and Q&A slides',
      templates: allTemplates.value.filter(t => t.category === 'ending'),
    },
  ]);

  function getTemplateById(id: string): SlideTemplate | undefined {
    return allTemplates.value.find(t => t.id === id);
  }

  function getTemplatesByCategory(category: TemplateCategory): SlideTemplate[] {
    return allTemplates.value.filter(t => t.category === category);
  }

  function createSlideFromTemplate(templateId: string): WysiwygSlide | null {
    const template = getTemplateById(templateId);
    if (!template) return null;
    return instantiateTemplate(template);
  }

  function createBlankSlide(): WysiwygSlide {
    const crypto = window.crypto || (window as any).msCrypto;
    return {
      id: crypto.randomUUID(),
      title: 'Blank Slide',
      elements: [],
      backgroundColor: '#FFFFFF',
    };
  }

  return {
    allTemplates,
    templateGroups,
    getTemplateById,
    getTemplatesByCategory,
    createSlideFromTemplate,
    createBlankSlide,
    instantiateTemplate,
  };
}
