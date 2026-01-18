/**
 * Slide Migration Utilities
 * Converts between legacy slide format and WYSIWYG element-based format
 */

import { v4 as uuid } from 'uuid';
import type { WysiwygSlide, WysiwygSlideElement, TextProperties } from '@/types/wysiwyg';

/**
 * Legacy slide format (bullet/paragraph based)
 */
export interface LegacySlide {
  id?: string;
  title: string;
  bullets?: string[];
  paragraphs?: string[];
  headings?: { heading: string; content: string }[];
  charts?: LegacyChart[];
  tables?: LegacyTable[];
  image_url?: string;
  image_fit?: 'contain' | 'cover';
  visuals?: string;
  speaker_notes?: string;
  layout?: 'bullets' | 'two_column' | 'image_left' | 'image_right';
  content_type?: 'bullets' | 'paragraphs' | 'mixed';
}

export interface LegacyChart {
  type: 'bar' | 'line' | 'pie' | 'scatter' | 'area';
  title?: string;
  x?: string[];
  series?: { name: string; data: number[] }[];
}

export interface LegacyTable {
  title?: string;
  columns: string[];
  rows: string[][];
}

/**
 * Default text properties for converted elements
 */
const defaultTextProps: Omit<TextProperties, 'content'> = {
  fontFamily: 'Arial',
  fontSize: 18,
  fontWeight: 'normal',
  fontStyle: 'normal',
  textAlign: 'left',
  color: '#374151',
  lineHeight: 1.4,
  letterSpacing: 0,
};

const slideLayout = {
  titleY: 4,
  titleHeight: 10,
  contentTop: 16,
  contentBottom: 94,
  contentGap: 2,
  fullWidth: 90,
  leftX: 5,
  rightX: 52,
  columnWidth: 42,
  centeredX: 12,
  centeredWidth: 76,
};

const ptToHeightPercent = (fontSize: number) => (fontSize * 100) / 540;
const lineHeightPercent = (fontSize: number, lineHeight: number) => ptToHeightPercent(fontSize) * lineHeight;

const estimateCharsPerLine = (width: number, fontSize: number) => {
  const base = 80;
  const scaled = base * (width / 90) * (16 / fontSize);
  return Math.max(20, Math.floor(scaled));
};

const estimateLineCount = (text: string, width: number, fontSize: number) => {
  const charsPerLine = estimateCharsPerLine(width, fontSize);
  return text.split('\n').reduce((sum, line) => {
    const clean = line.trim();
    if (!clean) return sum + 1;
    return sum + Math.max(1, Math.ceil(clean.length / charsPerLine));
  }, 0);
};

const splitTextByLines = (text: string, width: number, fontSize: number, maxLines: number) => {
  const words = text.split(/\s+/).filter(Boolean);
  if (!words.length) return [''];
  const charsPerLine = estimateCharsPerLine(width, fontSize);
  const chunks: string[] = [];
  let currentLines: string[] = [];
  let currentLine = '';
  let linesUsed = 0;

  const pushLine = () => {
    if (currentLine.trim()) {
      currentLines.push(currentLine.trim());
      currentLine = '';
      linesUsed += 1;
    }
  };

  for (const word of words) {
    const next = currentLine ? `${currentLine} ${word}` : word;
    if (next.length > charsPerLine) {
      pushLine();
      if (linesUsed >= maxLines) {
        chunks.push(currentLines.join(' '));
        currentLines = [];
        linesUsed = 0;
      }
      currentLine = word;
    } else {
      currentLine = next;
    }
  }

  pushLine();
  if (currentLines.length) {
    chunks.push(currentLines.join(' '));
  }
  return chunks;
};

const chunkBullets = (bullets: string[], width: number, fontSize: number, maxLines: number) => {
  const chunks: string[][] = [];
  let current: string[] = [];
  let usedLines = 0;
  const charsPerLine = estimateCharsPerLine(width, fontSize);

  bullets.forEach((bullet) => {
    const clean = stripHtml(bullet);
    const lines = Math.max(1, Math.ceil((clean.length + 2) / charsPerLine));
    if (current.length && usedLines + lines > maxLines) {
      chunks.push(current);
      current = [];
      usedLines = 0;
    }
    current.push(clean);
    usedLines += lines;
  });

  if (current.length) {
    chunks.push(current);
  }

  return chunks;
};

const addTextElement = (
  elements: WysiwygSlideElement[],
  content: string,
  {
    x,
    y,
    width,
    fontSize,
    fontWeight = 'normal',
    textAlign = 'left',
    lineHeight = 1.4,
    color = '#374151',
    zIndex,
  }: {
    x: number;
    y: number;
    width: number;
    fontSize: number;
    fontWeight?: TextProperties['fontWeight'];
    textAlign?: TextProperties['textAlign'];
    lineHeight?: number;
    color?: string;
    zIndex: number;
  }
) => {
  const lines = estimateLineCount(content, width, fontSize);
  const height = Math.max(lineHeightPercent(fontSize, lineHeight) * lines, lineHeightPercent(fontSize, lineHeight));
  elements.push({
    id: uuid(),
    type: 'text',
    x,
    y,
    width,
    height,
    rotation: 0,
    zIndex,
    opacity: 1,
    text: {
      ...defaultTextProps,
      content,
      fontSize,
      fontWeight,
      textAlign,
      color,
      lineHeight,
    },
  });
  return y + height + slideLayout.contentGap;
};

const centerElementsInSlide = (elements: WysiwygSlideElement[]) => {
  if (!elements.length) return;
  const bounds = elements.reduce(
    (acc, el) => {
      const left = el.x;
      const top = el.y;
      const right = el.x + el.width;
      const bottom = el.y + el.height;
      return {
        minX: Math.min(acc.minX, left),
        minY: Math.min(acc.minY, top),
        maxX: Math.max(acc.maxX, right),
        maxY: Math.max(acc.maxY, bottom),
      };
    },
    { minX: 100, minY: 100, maxX: 0, maxY: 0 }
  );

  const contentWidth = bounds.maxX - bounds.minX;
  const contentHeight = bounds.maxY - bounds.minY;
  if (contentWidth <= 0 || contentHeight <= 0) return;

  const targetCenterX = 50;
  const targetCenterY = 50;
  const currentCenterX = bounds.minX + contentWidth / 2;
  const currentCenterY = bounds.minY + contentHeight / 2;

  let dx = targetCenterX - currentCenterX;
  let dy = targetCenterY - currentCenterY;

  const safeMin = 2;
  const safeMax = 98;
  if (bounds.minX + dx < safeMin) {
    dx = safeMin - bounds.minX;
  }
  if (bounds.maxX + dx > safeMax) {
    dx = safeMax - bounds.maxX;
  }
  if (bounds.minY + dy < safeMin) {
    dy = safeMin - bounds.minY;
  }
  if (bounds.maxY + dy > safeMax) {
    dy = safeMax - bounds.maxY;
  }

  elements.forEach((el) => {
    el.x = Math.max(0, Math.min(100 - el.width, el.x + dx));
    el.y = Math.max(0, Math.min(100 - el.height, el.y + dy));
  });
};

/**
 * Convert legacy slides to WYSIWYG format
 */
export function legacyToWysiwyg(legacySlides: LegacySlide[]): WysiwygSlide[] {
  return legacySlides.map((slide, slideIndex) => {
    const elements: WysiwygSlideElement[] = [];
    let zIndex = 0;
    let yPosition = slideLayout.contentTop;
    const maxY = slideLayout.contentBottom;

    // Add title element
    elements.push({
      id: uuid(),
      type: 'text',
      x: slideLayout.centeredX,
      y: slideLayout.titleY,
      width: slideLayout.centeredWidth,
      height: slideLayout.titleHeight,
      rotation: 0,
      zIndex: zIndex++,
      opacity: 1,
      text: {
        content: slide.title || `Slide ${slideIndex + 1}`,
        fontFamily: 'Arial',
        fontSize: 32,
        fontWeight: 'bold',
        fontStyle: 'normal',
        textAlign: 'center',
        color: '#111827',
        lineHeight: 1.2,
        letterSpacing: 0,
      },
    });

    const layout = slide.layout || 'bullets';
    const contentType = slide.content_type || 'bullets';

    // Handle different layouts
    if (layout === 'two_column') {
      // Two column layout
      const bullets = slide.bullets || [];
      const half = Math.ceil(bullets.length / 2);
      const leftBullets = bullets.slice(0, half);
      const rightBullets = bullets.slice(half);
      const maxLines = Math.max(4, Math.floor((maxY - yPosition) / lineHeightPercent(18, 1.4)));

      chunkBullets(leftBullets, slideLayout.columnWidth, 18, maxLines).forEach((chunk) => {
        yPosition = addTextElement(elements, chunk.map((b) => `• ${stripHtml(b)}`).join('\n'), {
          x: slideLayout.leftX,
          y: yPosition,
          width: slideLayout.columnWidth,
          fontSize: 18,
          zIndex: zIndex++,
        });
      });

      let rightY = slideLayout.contentTop;
      chunkBullets(rightBullets, slideLayout.columnWidth, 18, maxLines).forEach((chunk) => {
        rightY = addTextElement(elements, chunk.map((b) => `• ${stripHtml(b)}`).join('\n'), {
          x: slideLayout.rightX,
          y: rightY,
          width: slideLayout.columnWidth,
          fontSize: 18,
          zIndex: zIndex++,
        });
      });
    } else if (layout === 'image_left' || layout === 'image_right') {
      // Image layout
      const imageX = layout === 'image_left' ? slideLayout.leftX : slideLayout.rightX;
      const textX = layout === 'image_left' ? slideLayout.rightX : slideLayout.leftX;

      if (slide.image_url) {
        elements.push({
          id: uuid(),
          type: 'image',
          x: imageX,
          y: slideLayout.contentTop,
          width: slideLayout.columnWidth,
          height: maxY - slideLayout.contentTop,
          rotation: 0,
          zIndex: zIndex++,
          opacity: 1,
          image: {
            url: slide.image_url,
            fit: slide.image_fit || 'contain',
          },
        });
      }

      if (slide.bullets?.length) {
        const maxLines = Math.max(4, Math.floor((maxY - yPosition) / lineHeightPercent(18, 1.4)));
        chunkBullets(slide.bullets, slideLayout.columnWidth, 18, maxLines).forEach((chunk) => {
          yPosition = addTextElement(elements, chunk.map((b) => `• ${stripHtml(b)}`).join('\n'), {
            x: textX,
            y: yPosition,
            width: slideLayout.columnWidth,
            fontSize: 18,
            zIndex: zIndex++,
          });
        });
      }
    } else {
      // Standard layout
      if (contentType === 'paragraphs' && slide.paragraphs?.length) {
        const fontSize = 16;
        const maxLines = Math.max(4, Math.floor((maxY - yPosition) / lineHeightPercent(fontSize, 1.5)));
        slide.paragraphs.forEach((para) => {
          if (yPosition >= maxY) return;
          const chunks = splitTextByLines(stripHtml(para), slideLayout.centeredWidth, fontSize, maxLines);
          chunks.forEach((chunk) => {
            if (yPosition >= maxY) return;
            yPosition = addTextElement(elements, chunk, {
              x: slideLayout.centeredX,
              y: yPosition,
              width: slideLayout.centeredWidth,
              fontSize,
              textAlign: 'justify',
              lineHeight: 1.5,
              zIndex: zIndex++,
            });
          });
        });
      } else if (contentType === 'mixed') {
        // Mixed content: paragraphs, then headings, then bullets
        if (slide.paragraphs?.length) {
          const fontSize = 16;
          const maxLines = Math.max(4, Math.floor((maxY - yPosition) / lineHeightPercent(fontSize, 1.4)));
          slide.paragraphs.forEach((para) => {
            if (yPosition >= maxY) return;
            splitTextByLines(stripHtml(para), slideLayout.centeredWidth, fontSize, maxLines).forEach((chunk) => {
              if (yPosition >= maxY) return;
              yPosition = addTextElement(elements, chunk, {
                x: slideLayout.centeredX,
                y: yPosition,
                width: slideLayout.centeredWidth,
                fontSize,
                lineHeight: 1.4,
                zIndex: zIndex++,
              });
            });
          });
        }

        if (slide.headings?.length) {
          slide.headings.forEach((item) => {
            if (yPosition >= maxY) return;
            if (item.heading) {
              yPosition = addTextElement(elements, stripHtml(item.heading), {
                x: slideLayout.centeredX,
                y: yPosition,
                width: slideLayout.centeredWidth,
                fontSize: 20,
                fontWeight: 'bold',
                color: '#1F2937',
                zIndex: zIndex++,
              });
            }
            if (item.content) {
              yPosition = addTextElement(elements, stripHtml(item.content), {
                x: slideLayout.centeredX + 2,
                y: yPosition,
                width: slideLayout.centeredWidth - 4,
                fontSize: 15,
                color: '#374151',
                zIndex: zIndex++,
              });
            }
          });
        }

        if (slide.bullets?.length) {
          const maxLines = Math.max(4, Math.floor((maxY - yPosition) / lineHeightPercent(18, 1.4)));
          chunkBullets(slide.bullets, slideLayout.centeredWidth, 18, maxLines).forEach((chunk) => {
            if (yPosition >= maxY) return;
            yPosition = addTextElement(elements, chunk.map((b) => `• ${stripHtml(b)}`).join('\n'), {
              x: slideLayout.centeredX,
              y: yPosition,
              width: slideLayout.centeredWidth,
              fontSize: 18,
              zIndex: zIndex++,
            });
          });
        }
      } else {
        // Default: bullets
        if (slide.bullets?.length) {
          const maxLines = Math.max(4, Math.floor((maxY - yPosition) / lineHeightPercent(18, 1.4)));
          chunkBullets(slide.bullets, slideLayout.centeredWidth, 18, maxLines).forEach((chunk) => {
            if (yPosition >= maxY) return;
            yPosition = addTextElement(elements, chunk.map((b) => `• ${stripHtml(b)}`).join('\n'), {
              x: slideLayout.centeredX,
              y: yPosition,
              width: slideLayout.centeredWidth,
              fontSize: 18,
              zIndex: zIndex++,
            });
          });
        }
      }

      // Add charts
      if (slide.charts?.length) {
        slide.charts.forEach((chart) => {
          if (yPosition >= maxY) return;
          elements.push({
            id: uuid(),
            type: 'chart',
            x: slideLayout.centeredX + 5,
            y: yPosition,
            width: 60,
            height: 35,
            rotation: 0,
            zIndex: zIndex++,
            opacity: 1,
            chart: {
              chartType: chart.type || 'bar',
              title: chart.title || '',
              labels: chart.x || [],
              datasets: (chart.series || []).map((s) => ({
                label: s.name,
                data: s.data,
                backgroundColor: ['#6366F1', '#8B5CF6', '#EC4899', '#F59E0B'],
              })),
              showLegend: true,
              showGrid: true,
            },
          });
          yPosition += 38;
        });
      }

      // Add tables
      if (slide.tables?.length) {
        slide.tables.forEach((table) => {
          if (yPosition >= maxY) return;
          elements.push({
            id: uuid(),
            type: 'table',
            x: slideLayout.centeredX,
            y: yPosition,
            width: 70,
            height: 25,
            rotation: 0,
            zIndex: zIndex++,
            opacity: 1,
            table: {
              title: table.title || '',
              columns: table.columns.map((col, i) => ({ key: `col${i}`, label: col })),
              rows: table.rows.map((row) => {
                const rowObj: Record<string, string> = {};
                row.forEach((cell, i) => {
                  rowObj[`col${i}`] = cell;
                });
                return rowObj;
              }),
              headerBackground: '#F3F4F6',
              headerColor: '#111827',
              alternateRowColors: true,
            },
          });
          yPosition += 28;
        });
      }

      // Add image if present and not in image layout
      if (slide.image_url && layout !== 'image_left' && layout !== 'image_right') {
        elements.push({
          id: uuid(),
          type: 'image',
          x: 60,
          y: slideLayout.contentTop,
          width: 35,
          height: 40,
          rotation: 0,
          zIndex: zIndex++,
          opacity: 1,
          image: {
            url: slide.image_url,
            fit: slide.image_fit || 'contain',
          },
        });
      }
    }

    return {
      id: slide.id || uuid(),
      title: slide.title || `Slide ${slideIndex + 1}`,
      elements: (() => {
        centerElementsInSlide(elements);
        return elements;
      })(),
      backgroundColor: '#FFFFFF',
      speaker_notes: slide.speaker_notes,
    };
  });
}

/**
 * Convert WYSIWYG slides back to legacy format
 * Used for backward compatibility
 */
export function wysiwygToLegacy(wysiwygSlides: WysiwygSlide[]): LegacySlide[] {
  return wysiwygSlides.map((slide) => {
    const legacySlide: LegacySlide = {
      id: slide.id,
      title: slide.title,
      bullets: [],
      paragraphs: [],
      speaker_notes: slide.speaker_notes,
      layout: 'bullets',
    };

    // Extract text content as bullets
    const textElements = slide.elements.filter((el) => el.type === 'text');
    textElements.forEach((el) => {
      if (el.text?.content) {
        // Skip title (first element usually)
        if (el.text.fontSize && el.text.fontSize >= 28) {
          legacySlide.title = el.text.content;
        } else {
          legacySlide.bullets?.push(el.text.content);
        }
      }
    });

    // Extract charts
    const chartElements = slide.elements.filter((el) => el.type === 'chart');
    if (chartElements.length > 0) {
      legacySlide.charts = chartElements.map((el) => ({
        type: el.chart?.chartType || 'bar',
        title: el.chart?.title,
        x: el.chart?.labels || [],
        series: (el.chart?.datasets || []).map((ds) => ({
          name: ds.label || 'Series',
          data: ds.data || [],
        })),
      }));
    }

    // Extract tables
    const tableElements = slide.elements.filter((el) => el.type === 'table');
    if (tableElements.length > 0) {
      legacySlide.tables = tableElements.map((el) => ({
        title: el.table?.title,
        columns: (el.table?.columns || []).map((c) => c.label),
        rows: (el.table?.rows || []).map((row) =>
          (el.table?.columns || []).map((c) => String(row[c.key] || ''))
        ),
      }));
    }

    // Extract first image
    const imageElement = slide.elements.find((el) => el.type === 'image');
    if (imageElement?.image) {
      legacySlide.image_url = imageElement.image.url;
      legacySlide.image_fit = imageElement.image.fit;
    }

    return legacySlide;
  });
}

/**
 * Helper: Create a bullet list element
 */
function createBulletElement(
  bullets: string[],
  x: number,
  y: number,
  width: number,
  zIndex: number
): WysiwygSlideElement {
  const content = bullets.map((b) => `• ${stripHtml(b)}`).join('\n');
  const height = Math.min(bullets.length * 5 + 5, 50);

  return {
    id: uuid(),
    type: 'text',
    x,
    y,
    width,
    height,
    rotation: 0,
    zIndex,
    opacity: 1,
    text: {
      ...defaultTextProps,
      content,
      fontSize: 18,
    },
  };
}

/**
 * Helper: Strip HTML tags from content
 */
function stripHtml(value: string | undefined | null): string {
  if (!value) return '';
  return String(value)
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .trim();
}

/**
 * Check if a slide array appears to be in WYSIWYG format
 */
export function isWysiwygFormat(slides: unknown[]): boolean {
  if (!Array.isArray(slides) || slides.length === 0) return false;
  const firstSlide = slides[0] as Record<string, unknown>;
  return Array.isArray(firstSlide?.elements);
}

/**
 * Ensure slides are in WYSIWYG format, converting if necessary
 */
export function ensureWysiwygFormat(slides: unknown[], forceConvert = false): WysiwygSlide[] {
  if (!Array.isArray(slides)) return [];

  if (isWysiwygFormat(slides) && !forceConvert) {
    return slides as WysiwygSlide[];
  }

  return legacyToWysiwyg(slides as LegacySlide[]);
}
