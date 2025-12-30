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

const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Finalyze';
pptx.title = title;

const chartTypeMap = {
  bar: pptx.ChartType.bar,
  line: pptx.ChartType.line,
  pie: pptx.ChartType.pie,
  scatter: pptx.ChartType.scatter,
  area: pptx.ChartType.area,
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

for (let index = 0; index < slides.length; index += 1) {
  const slideData = slides[index];
  const slide = pptx.addSlide();
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

await pptx.writeFile({ fileName: outputPath });
