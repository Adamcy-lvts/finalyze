# Word Export Enhancement: Preliminary Pages with Section-Based Page Numbering

## Overview

Enhance the Word document export to match the PDF export quality by implementing:
- Preliminary pages each on their own page
- Section-based page numbering (Roman numerals for frontmatter, Arabic for main content)
- Each chapter starting on a new page

## Architecture Decision

**Approach: LibreOffice/Pandoc + DOCX Post-Processing**

Keep the existing robust conversion pipeline and add a post-processor:

1. **LibreOffice/Pandoc** handles HTML→DOCX conversion (tables, images, special characters work perfectly)
2. **Post-processor** modifies the generated DOCX XML to add section breaks and page numbering

This approach:
- Preserves the reliable content conversion (no PHPWord HTML limitations)
- Uses existing patterns from `repairDocxXmlInPlace()` (ZipArchive + DOMDocument)
- DOCX files are ZIP archives - we can modify `word/document.xml` directly

## How It Works

### DOCX Structure
```
mydocument.docx (ZIP archive)
├── [Content_Types].xml
├── _rels/.rels
├── word/
│   ├── document.xml      ← Main content (we modify this)
│   ├── styles.xml
│   ├── footer1.xml       ← We create these for page numbers
│   ├── footer2.xml
│   ├── footer3.xml
│   └── _rels/
│       └── document.xml.rels  ← We update relationships
└── docProps/
```

### Section Properties XML
```xml
<!-- Section break with Roman numeral page numbers -->
<w:sectPr>
  <w:type w:val="nextPage"/>
  <w:pgNumType w:fmt="lowerRoman" w:start="1"/>
  <w:footerReference w:type="default" r:id="rIdFooter1"/>
  <w:pgSz w:w="12240" w:h="15840"/>
  <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1800"/>
</w:sectPr>

<!-- Section break with Arabic page numbers starting at 1 -->
<w:sectPr>
  <w:type w:val="nextPage"/>
  <w:pgNumType w:fmt="decimal" w:start="1"/>
  <w:footerReference w:type="default" r:id="rIdFooter2"/>
</w:sectPr>
```

## Document Structure

```
┌─────────────────────────────────────────┐
│ SECTION 1: Title Page                   │
│ • No page number (no footer)            │
│ • Project title, student name, etc.     │
└─────────────────────────────────────────┘
           ↓ Section Break (nextPage)
┌─────────────────────────────────────────┐
│ SECTION 2: Frontmatter                  │
│ • Page numbers: i, ii, iii, iv, v...    │
│ • Declaration (page break)              │
│ • Certification (page break)            │
│ • Dedication (page break)               │
│ • Acknowledgements (page break)         │
│ • Abstract (page break)                 │
│ • Table of Contents                     │
└─────────────────────────────────────────┘
           ↓ Section Break (nextPage)
┌─────────────────────────────────────────┐
│ SECTION 3: Main Content                 │
│ • Page numbers: 1, 2, 3, 4, 5...        │
│ • Chapter 1 (page break)                │
│ • Chapter 2 (page break)                │
│ • ...                                   │
│ • Last Chapter + Consolidated References│
└─────────────────────────────────────────┘
```

## Files to Create

### 1. `app/Services/Word/DocxSectionProcessor.php` (New)

Post-processor that modifies DOCX XML to add sections and page numbering.

```php
class DocxSectionProcessor
{
    /**
     * Add section breaks and page numbering to a DOCX file.
     *
     * @param string $docxPath Path to the DOCX file
     * @param array $sectionMarkers Markers indicating where to insert section breaks
     * @return bool Success status
     */
    public function process(string $docxPath, array $sectionMarkers): bool;

    /**
     * Insert section properties at specific paragraph positions.
     */
    private function insertSectionBreaks(DOMDocument $doc, array $sectionMarkers): void;

    /**
     * Create footer XML files for page numbering.
     */
    private function createFooterFiles(ZipArchive $zip, array $sections): array;

    /**
     * Update document.xml.rels with footer relationships.
     */
    private function updateRelationships(ZipArchive $zip, array $footerIds): void;
}
```

### 2. Section Marker Strategy

Add HTML comments or data attributes in the generated HTML to mark section boundaries:

```html
<!-- SECTION_BREAK:title_end -->
<div class="page-break"></div>

<!-- SECTION_BREAK:frontmatter_end -->
<div class="page-break"></div>
```

The post-processor finds these markers in `document.xml` (converted to comments or specific paragraph styles) and inserts `<w:sectPr>` elements.

## Files to Modify

### 1. `app/Services/ExportService.php`

Modify `buildFullProjectHtml()` to add section markers:

```php
private function buildFullProjectHtml(Project $project, array $preliminaryPages = []): string
{
    $html = '';

    // Title page content...
    $html .= '</div>';
    $html .= '<!-- SECTION_BREAK:title_end:no_page_number -->';
    $html .= '<div class="page-break"></div>';

    // Frontmatter (preliminary pages + TOC)...
    $html .= '<!-- SECTION_BREAK:frontmatter_end:roman -->';
    $html .= '<div class="page-break"></div>';

    // Main content (chapters)...
    // (uses arabic numbering starting at 1)
}
```

Add post-processing call after conversion:

```php
public function exportToWord(Project $project): string
{
    // ... existing conversion with LibreOffice/Pandoc ...

    // Post-process to add section breaks and page numbering
    $processor = app(DocxSectionProcessor::class);
    $processor->process($filename, [
        ['marker' => 'title_end', 'pageNumberFormat' => null],
        ['marker' => 'frontmatter_end', 'pageNumberFormat' => 'lowerRoman', 'start' => 1],
        ['marker' => 'document_end', 'pageNumberFormat' => 'decimal', 'start' => 1],
    ]);

    return $filename;
}
```

## Implementation Steps

### Step 1: Update HTML Generation with Section Markers

1. Modify `buildFullProjectHtml()` in ExportService.php
2. Add HTML comments marking section boundaries:
   - After title page: `<!-- SECTION:title_end -->`
   - After frontmatter (TOC): `<!-- SECTION:frontmatter_end -->`
3. Ensure page breaks follow each marker

### Step 2: Create DocxSectionProcessor

1. Create `app/Services/Word/DocxSectionProcessor.php`
2. Implement `process()` method:
   - Open DOCX with ZipArchive
   - Parse `word/document.xml` with DOMDocument
   - Find converted section markers (LibreOffice converts HTML comments)
   - Insert `<w:sectPr>` elements at marker positions

### Step 3: Implement Footer Creation

1. Create footer XML files (`footer1.xml`, `footer2.xml`, `footer3.xml`)
2. Footer with centered page number:
```xml
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:pPr><w:jc w:val="center"/></w:pPr>
    <w:r>
      <w:fldChar w:fldCharType="begin"/>
    </w:r>
    <w:r>
      <w:instrText>PAGE</w:instrText>
    </w:r>
    <w:r>
      <w:fldChar w:fldCharType="end"/>
    </w:r>
  </w:p>
</w:ftr>
```

### Step 4: Update Relationships

1. Modify `word/_rels/document.xml.rels` to include footer references
2. Update `[Content_Types].xml` to register footer content types

### Step 5: Insert Section Properties

For each section boundary, insert before the next paragraph:
```xml
<w:p>
  <w:pPr>
    <w:sectPr>
      <w:type w:val="nextPage"/>
      <w:pgNumType w:fmt="lowerRoman" w:start="1"/>
      <w:footerReference w:type="default" r:id="rId10"/>
      <w:pgSz w:w="12240" w:h="15840"/>
      <w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1800"/>
    </w:sectPr>
  </w:pPr>
</w:p>
```

### Step 6: Handle Final Section

The document's final `<w:sectPr>` (at end of `<w:body>`) defines the last section's properties.

### Step 7: Testing

1. Test with various content types (tables, images, code blocks)
2. Verify section breaks appear correctly in Word
3. Verify page numbering schemes work
4. Run existing export tests

## Key XML Patterns

### Page Number Formats
- `decimal` - Arabic (1, 2, 3...)
- `lowerRoman` - Lowercase Roman (i, ii, iii...)
- `upperRoman` - Uppercase Roman (I, II, III...)

### Section Break Types
- `nextPage` - Start on next page
- `continuous` - No page break
- `evenPage` / `oddPage` - Start on even/odd page

## Critical Files Reference

| File | Purpose |
|------|---------|
| [ExportService.php](app/Services/ExportService.php) | HTML generation, conversion orchestration |
| [ExportService.php:976-1029](app/Services/ExportService.php#L976-L1029) | Existing DOCX XML manipulation pattern |
| [PdfExportService.php](app/Services/PdfExportService.php) | Reference for 3-section structure |
| [ProjectPrelimService.php](app/Services/ProjectPrelimService.php) | Provides preliminary pages |

## Verification Plan

1. **Export full project** and open in Microsoft Word
2. **Check Document Structure** (View → Navigation Pane → Sections)
3. **Verify title page** has no page number
4. **Verify frontmatter** pages have Roman numerals (i, ii, iii...)
5. **Verify main content** has Arabic numbers starting at 1
6. **Verify each preliminary page** is on its own page
7. **Verify each chapter** starts on a new page
8. **Verify tables/images** render correctly (LibreOffice handles these)
9. **Run existing tests**: `php artisan test --filter=Export`

## Fallback Strategy

If section markers don't survive LibreOffice conversion:
- Use specific paragraph styles or formatting as markers
- Or generate the document in parts and merge with section properties
- Or use special Unicode characters that can be detected in the XML

## Backward Compatibility

- Existing `ExportService` methods continue to work
- Post-processing is additive - doesn't break current exports
- Single chapter exports don't need sections (no changes)
- Frontend ExportMenu.vue unchanged (same routes)
