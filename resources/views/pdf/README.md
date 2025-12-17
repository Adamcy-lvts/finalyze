# PDF Views

These Blade templates are actively used by the current PDF export flows:

- `resources/views/pdf/sections/title-page.blade.php` (full project export title page)
- `resources/views/pdf/sections/frontmatter.blade.php` (full project export preliminary pages + TOC)
- `resources/views/pdf/sections/main-content.blade.php` (full project export chapters + references/appendices)
- `resources/views/pdf/sections/base-styles.blade.php` (shared CSS for the section-based exporter)
- `resources/views/pdf/chapter.blade.php` (single-chapter export)
- `resources/views/pdf/topic-proposal.blade.php` (topic proposal export)

Code references:

- `app/Services/PdfExportService.php` uses `pdf.sections.*`
- `app/Http/Controllers/ChapterController.php` uses `pdf.chapter`
- `app/Actions/Topics/ExportTopicPdfAction.php` uses `pdf.topic-proposal`

