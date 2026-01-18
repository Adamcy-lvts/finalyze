import fs from 'node:fs/promises';
import pptxgen from 'pptxgenjs';

const args = process.argv.slice(2);
const argValue = (key) => {
  const index = args.indexOf(key);
  return index !== -1 ? args[index + 1] : null;
};

const inputPath = argValue('--input');
const outputPath = argValue('--output');

if (!inputPath || !outputPath) {
  console.error('Usage: node export-defense-deck.mjs --input <json> --output <pptx>');
  process.exit(1);
}

const payload = JSON.parse(await fs.readFile(inputPath, 'utf8'));
const slides = Array.isArray(payload.slides) ? payload.slides : [];
const title = payload.title || 'Defense Deck';
const isWysiwyg = payload.is_wysiwyg === true;

const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Finalyze';
pptx.title = title;

// Slide dimensions in inches (LAYOUT_WIDE = 13.333" x 7.5")
const SLIDE_WIDTH = 13.333;
const SLIDE_HEIGHT = 7.5;

const chartTypeMap = {
  bar: pptx.ChartType.bar,
  line: pptx.ChartType.line,
  pie: pptx.ChartType.pie,
  scatter: pptx.ChartType.scatter,
  area: pptx.ChartType.area,
  doughnut: pptx.ChartType.doughnut,
};

const stripMarkup = (value) => {
  if (!value) return '';
  return String(value)
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/g, ' ')
    .trim();
};

const addBullets = (slide, bullets, x, y, w, h) => {
  if (!bullets?.length) return y;
  const text = bullets.map((bullet) => `• ${stripMarkup(bullet)}`).join('\n');
  slide.addText(text, {
    x,
    y,
    w,
    h,
    fontSize: 18,
    color: '2D2D2D',
    valign: 'top',
  });
  return y + h;
};

const addParagraphs = (slide, paragraphs, x, y, w) => {
  if (!paragraphs?.length) return y;
  paragraphs.forEach((para) => {
    const text = stripMarkup(para);
    if (!text) return;
    const lineCount = Math.ceil(text.length / 100);
    const h = Math.max(0.6, lineCount * 0.35);
    slide.addText(text, {
      x,
      y,
      w,
      h,
      fontSize: 16,
      color: '2D2D2D',
      valign: 'top',
      align: 'justify',
    });
    y += h + 0.15;
  });
  return y;
};

const addHeadings = (slide, headings, x, y, w) => {
  if (!headings?.length) return y;
  headings.forEach((item) => {
    const heading = stripMarkup(item?.heading);
    const content = stripMarkup(item?.content);
    if (!heading && !content) return;

    if (heading) {
      slide.addText(heading, {
        x,
        y,
        w,
        h: 0.4,
        fontSize: 18,
        bold: true,
        color: '1F2937',
        valign: 'top',
      });
      y += 0.45;
    }

    if (content) {
      const lineCount = Math.ceil(content.length / 100);
      const h = Math.max(0.5, lineCount * 0.32);
      slide.addText(content, {
        x: x + 0.1,
        y,
        w: w - 0.1,
        h,
        fontSize: 15,
        color: '374151',
        valign: 'top',
        align: 'justify',
      });
      y += h + 0.2;
    }
  });
  return y;
};

const addVisualHint = (slide, visuals, x, y, w) => {
  if (!visuals) return y;
  slide.addText(`Visual: ${stripMarkup(visuals)}`, {
    x,
    y,
    w,
    h: 0.5,
    fontSize: 12,
    color: '6B7280',
    italic: true,
  });
  return y + 0.6;
};

const addCharts = (slide, charts, x, y, w) => {
  if (!charts?.length) return y;
  charts.forEach((chart) => {
    const labels = Array.isArray(chart.x) ? chart.x : [];
    const series = Array.isArray(chart.series) ? chart.series : [];
    const data = series.map((entry) => ({
      name: entry?.name || 'Series',
      labels,
      values: Array.isArray(entry?.data) ? entry.data : [],
    }));
    const typeKey = typeof chart?.type === 'string' ? chart.type : 'bar';
    const chartType = chartTypeMap[typeKey] ?? pptx.ChartType.bar;

    slide.addChart(chartType, data, {
      x,
      y,
      w,
      h: 3.0,
      chartTitle: chart?.title || '',
      showLegend: true,
    });
    y += 3.2;
  });
  return y;
};

const addTables = (slide, tables, x, y, w) => {
  if (!tables?.length) return y;
  tables.forEach((table) => {
    if (table?.title) {
      slide.addText(table.title, {
        x,
        y,
        w,
        h: 0.4,
        fontSize: 14,
        bold: true,
        color: '111827',
      });
      y += 0.5;
    }

    const columns = Array.isArray(table?.columns) ? table.columns : [];
    const rows = Array.isArray(table?.rows) ? table.rows : [];
    const tableRows = [columns, ...rows].filter((row) => row.length);
    if (tableRows.length) {
      slide.addTable(tableRows, {
        x,
        y,
        w,
        h: 1.8,
        fontSize: 12,
        border: { type: 'solid', color: 'CBD5F5', pt: 1 },
      });
      y += 2.0;
    }
  });
  return y;
};

const resolveImagePath = async (url) => {
  if (!url || typeof url !== 'string') return null;
  if (url.startsWith('/storage/')) {
    const localPath = `.${url}`.replace('/storage/', '/storage/app/public/');
    try {
      await fs.access(localPath);
      return localPath;
    } catch {
      return null;
    }
  }
  return null;
};

const addSlideImage = async (slide, slideData, x, y, w, h) => {
  if (!slideData?.image_url) return false;
  const imagePath = await resolveImagePath(slideData.image_url);
  if (!imagePath) return false;
  const sizingType = slideData.image_fit === 'contain' ? 'contain' : 'cover';
  slide.addImage({
    path: imagePath,
    x,
    y,
    w,
    h,
    sizing: { type: sizingType, x, y, w, h },
  });
  return true;
};

// ============================================================================
// WYSIWYG Element Rendering Functions
// ============================================================================

/**
 * Convert percentage position to inches
 */
const percentToInches = (percent, dimension) => {
  return (percent / 100) * dimension;
};

/**
 * Convert hex color to PPTX color format (without #)
 */
const formatColor = (color) => {
  if (!color) return '111827';
  return color.replace('#', '');
};

/**
 * Map text alignment to PPTX alignment
 */
const mapTextAlign = (align) => {
  const alignMap = {
    left: 'left',
    center: 'center',
    right: 'right',
    justify: 'justify',
  };
  return alignMap[align] || 'left';
};

/**
 * Render a text element to PPTX
 */
const renderTextElement = (slide, element) => {
  if (!element.text?.content) return;

  const x = percentToInches(element.x, SLIDE_WIDTH);
  const y = percentToInches(element.y, SLIDE_HEIGHT);
  const w = percentToInches(element.width, SLIDE_WIDTH);
  const h = percentToInches(element.height, SLIDE_HEIGHT);

  const textOptions = {
    x,
    y,
    w,
    h,
    fontSize: element.text.fontSize || 18,
    fontFace: element.text.fontFamily || 'Arial',
    color: formatColor(element.text.color),
    bold: element.text.fontWeight === 'bold',
    italic: element.text.fontStyle === 'italic',
    underline: element.text.textDecoration === 'underline' ? { style: 'sng' } : undefined,
    align: mapTextAlign(element.text.textAlign),
    valign: 'top',
    lineSpacing: element.text.lineHeight ? element.text.lineHeight * element.text.fontSize : undefined,
    rotate: element.rotation || 0,
  };

  // Handle opacity
  if (element.opacity !== undefined && element.opacity < 1) {
    textOptions.transparency = Math.round((1 - element.opacity) * 100);
  }

  slide.addText(element.text.content, textOptions);
};

/**
 * Render a shape element to PPTX
 */
const renderShapeElement = (slide, element) => {
  const x = percentToInches(element.x, SLIDE_WIDTH);
  const y = percentToInches(element.y, SLIDE_HEIGHT);
  const w = percentToInches(element.width, SLIDE_WIDTH);
  const h = percentToInches(element.height, SLIDE_HEIGHT);

  const shapeType = element.shape?.shapeType || 'rectangle';

  // Map shape types to PPTX shapes
  const shapeMap = {
    rectangle: pptx.ShapeType.rect,
    'rounded-rectangle': pptx.ShapeType.roundRect,
    circle: pptx.ShapeType.ellipse,
    ellipse: pptx.ShapeType.ellipse,
    triangle: pptx.ShapeType.triangle,
    arrow: pptx.ShapeType.rightArrow,
    line: pptx.ShapeType.line,
    star: pptx.ShapeType.star5,
    diamond: pptx.ShapeType.diamond,
    pentagon: pptx.ShapeType.pentagon,
    hexagon: pptx.ShapeType.hexagon,
  };

  const pptxShape = shapeMap[shapeType] || pptx.ShapeType.rect;

  const shapeOptions = {
    x,
    y,
    w,
    h,
    fill: { color: formatColor(element.fill || '#E5E7EB') },
    line: element.stroke
      ? { color: formatColor(element.stroke), width: element.strokeWidth || 1 }
      : undefined,
    rotate: element.rotation || 0,
  };

  // Handle opacity
  if (element.opacity !== undefined && element.opacity < 1) {
    shapeOptions.fill.transparency = Math.round((1 - element.opacity) * 100);
  }

  slide.addShape(pptxShape, shapeOptions);
};

/**
 * Render an image element to PPTX
 */
const renderImageElement = async (slide, element) => {
  if (!element.image?.url) return;

  const x = percentToInches(element.x, SLIDE_WIDTH);
  const y = percentToInches(element.y, SLIDE_HEIGHT);
  const w = percentToInches(element.width, SLIDE_WIDTH);
  const h = percentToInches(element.height, SLIDE_HEIGHT);

  const imagePath = await resolveImagePath(element.image.url);

  const imageOptions = {
    x,
    y,
    w,
    h,
    rotate: element.rotation || 0,
  };

  if (imagePath) {
    imageOptions.path = imagePath;
    if (element.image.fit) {
      imageOptions.sizing = {
        type: element.image.fit === 'contain' ? 'contain' : 'cover',
        x,
        y,
        w,
        h,
      };
    }
  } else if (element.image.url.startsWith('http')) {
    // Try to use URL directly for remote images
    imageOptions.path = element.image.url;
  } else {
    // Placeholder for missing images
    slide.addShape(pptx.ShapeType.rect, {
      x,
      y,
      w,
      h,
      fill: { color: 'F3F4F6' },
      line: { color: 'D1D5DB', width: 1, dashType: 'dash' },
    });
    slide.addText('Image not found', {
      x,
      y: y + h / 2 - 0.15,
      w,
      h: 0.3,
      fontSize: 10,
      color: '9CA3AF',
      align: 'center',
    });
    return;
  }

  try {
    slide.addImage(imageOptions);
  } catch (e) {
    console.warn(`Failed to add image: ${element.image.url}`, e.message);
  }
};

/**
 * Render a chart element to PPTX
 */
const renderChartElement = (slide, element) => {
  if (!element.chart) return;

  const x = percentToInches(element.x, SLIDE_WIDTH);
  const y = percentToInches(element.y, SLIDE_HEIGHT);
  const w = percentToInches(element.width, SLIDE_WIDTH);
  const h = percentToInches(element.height, SLIDE_HEIGHT);

  const chartData = element.chart;
  const chartType = chartTypeMap[chartData.chartType] || pptx.ChartType.bar;

  // Build chart data series
  const data = (chartData.datasets || []).map((dataset) => ({
    name: dataset.label || 'Series',
    labels: chartData.labels || [],
    values: dataset.data || [],
  }));

  if (data.length === 0 || data[0].values.length === 0) return;

  const chartOptions = {
    x,
    y,
    w,
    h,
    showTitle: !!chartData.title,
    title: chartData.title || '',
    showLegend: chartData.showLegend !== false,
    legendPos: 'b',
  };

  // Add chart colors
  if (chartData.datasets?.[0]?.backgroundColor) {
    const colors = Array.isArray(chartData.datasets[0].backgroundColor)
      ? chartData.datasets[0].backgroundColor.map(formatColor)
      : [formatColor(chartData.datasets[0].backgroundColor)];
    chartOptions.chartColors = colors;
  }

  slide.addChart(chartType, data, chartOptions);
};

/**
 * Render a table element to PPTX
 */
const renderTableElement = (slide, element) => {
  if (!element.table) return;

  const x = percentToInches(element.x, SLIDE_WIDTH);
  const y = percentToInches(element.y, SLIDE_HEIGHT);
  const w = percentToInches(element.width, SLIDE_WIDTH);
  const h = percentToInches(element.height, SLIDE_HEIGHT);

  const tableData = element.table;

  // Add title if present
  if (tableData.title) {
    slide.addText(tableData.title, {
      x,
      y: y - 0.4,
      w,
      h: 0.35,
      fontSize: 14,
      bold: true,
      color: formatColor(tableData.headerColor || '#111827'),
    });
  }

  // Build table rows
  const columns = tableData.columns || [];
  const headerRow = columns.map((col) => ({
    text: col.label,
    options: {
      bold: true,
      fill: { color: formatColor(tableData.headerBackground || '#F3F4F6') },
      color: formatColor(tableData.headerColor || '#111827'),
    },
  }));

  const dataRows = (tableData.rows || []).map((row, rowIndex) =>
    columns.map((col) => ({
      text: String(row[col.key] || ''),
      options: {
        fill:
          tableData.alternateRowColors && rowIndex % 2 === 1
            ? { color: 'F9FAFB' }
            : undefined,
      },
    }))
  );

  const tableRows = [headerRow, ...dataRows];

  if (tableRows.length > 0) {
    slide.addTable(tableRows, {
      x,
      y: tableData.title ? y : y,
      w,
      autoPage: false,
      fontSize: 11,
      border: { type: 'solid', color: 'E5E7EB', pt: 0.5 },
      colW: columns.map(() => w / columns.length),
    });
  }
};

/**
 * Render all elements of a WYSIWYG slide
 */
const renderWysiwygSlide = async (pptxSlide, slideData) => {
  // Set background color
  if (slideData.backgroundColor) {
    pptxSlide.background = { color: formatColor(slideData.backgroundColor) };
  }

  // Sort elements by z-index
  const elements = [...(slideData.elements || [])].sort(
    (a, b) => (a.zIndex || 0) - (b.zIndex || 0)
  );

  // Render each element
  for (const element of elements) {
    switch (element.type) {
      case 'text':
        renderTextElement(pptxSlide, element);
        break;
      case 'shape':
        renderShapeElement(pptxSlide, element);
        break;
      case 'image':
        await renderImageElement(pptxSlide, element);
        break;
      case 'chart':
        renderChartElement(pptxSlide, element);
        break;
      case 'table':
        renderTableElement(pptxSlide, element);
        break;
    }
  }

  // Add speaker notes
  if (slideData.speaker_notes) {
    pptxSlide.addNotes(slideData.speaker_notes);
  }
};

// ============================================================================
// Main Slide Generation
// ============================================================================

for (let index = 0; index < slides.length; index += 1) {
  const slideData = slides[index];
  const slide = pptx.addSlide();

  // Check if this is a WYSIWYG slide (has elements array)
  const isElementBased = isWysiwyg || Array.isArray(slideData?.elements);

  if (isElementBased) {
    // WYSIWYG element-based rendering
    await renderWysiwygSlide(slide, slideData);
  } else {
    // Legacy bullet/paragraph rendering
    slide.addText(slideData?.title || `Slide ${index + 1}`, {
      x: 0.6,
      y: 0.3,
      w: 12.5,
      h: 0.6,
      fontSize: 28,
      bold: true,
      color: '111827',
    });

    const layout = slideData?.layout || 'bullets';
    const contentX = 0.7;
    const contentY = 1.1;
    const contentW = 12.0;

    const contentType = slideData?.content_type || 'bullets';

    if (layout === 'two_column') {
      const half = Math.ceil((slideData?.bullets || []).length / 2);
      const left = (slideData?.bullets || []).slice(0, half);
      const right = (slideData?.bullets || []).slice(half);
      addBullets(slide, left, contentX, contentY, 5.8, 4.5);
      addBullets(slide, right, contentX + 6.2, contentY, 5.8, 4.5);
    } else if (layout === 'image_left' || layout === 'image_right') {
      const imageBoxX = layout === 'image_left' ? contentX : contentX + 6.2;
      const textBoxX = layout === 'image_left' ? contentX + 6.2 : contentX;
      const imageAdded = await addSlideImage(slide, slideData, imageBoxX, contentY, 5.8, 4.5);
      addBullets(slide, slideData?.bullets || [], textBoxX, contentY, 5.8, 4.5);
      if (!imageAdded) {
        addVisualHint(slide, slideData?.visuals, imageBoxX, contentY + 4.6, 5.8);
      }
    } else {
      let cursor = contentY;

      if (contentType === 'paragraphs') {
        cursor = addParagraphs(slide, slideData?.paragraphs || [], contentX, cursor, contentW);
      } else if (contentType === 'mixed') {
        cursor = addParagraphs(slide, slideData?.paragraphs || [], contentX, cursor, contentW);
        cursor = addHeadings(slide, slideData?.headings || [], contentX, cursor, contentW);
        cursor = addBullets(slide, slideData?.bullets || [], contentX, cursor, contentW, 2.0);
      } else {
        cursor = addBullets(slide, slideData?.bullets || [], contentX, cursor, contentW, 2.8);
      }

      cursor = addVisualHint(slide, slideData?.visuals, contentX, cursor, contentW);
      cursor = addCharts(slide, slideData?.charts || [], contentX, cursor, contentW);
      cursor = addTables(slide, slideData?.tables || [], contentX, cursor, contentW);
    }

    if (slideData?.speaker_notes) {
      slide.addNotes(slideData.speaker_notes);
    }
  }
}

await pptx.writeFile({ fileName: outputPath });
