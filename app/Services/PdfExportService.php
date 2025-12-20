<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use setasign\Fpdi\Tcpdf\Fpdi;
use Spatie\Browsershot\Browsershot;

class PdfExportService
{
    public function __construct(
        protected ProjectPrelimService $projectPrelimService
    ) {}

    /**
     * Export entire project to PDF with proper page numbering
     *
     * Page numbering scheme:
     * - Title page: No page number
     * - Frontmatter: Roman numerals (i, ii, iii...)
     * - Main content: Arabic numerals starting from 1
     */
    public function exportProject(Project $project): string
    {
        $startTime = microtime(true);

        Log::info('PdfExportService: Starting project export', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
        ]);

        try {
            // Load project relationships
            $project->load(['user', 'category']);

            // Get chapters with content
            $chapters = $project->chapters()
                ->whereNotNull('content')
                ->where('content', '!=', '')
                ->orderBy('chapter_number')
                ->get();

            if ($chapters->isEmpty()) {
                throw new \Exception('No content available for export');
            }

            // Convert chapter content to HTML
            $chapterContents = [];
            foreach ($chapters as $chapter) {
                Log::info('PdfExportService: Chapter content font sizes (raw)', [
                    'chapter_id' => $chapter->id,
                    'chapter_number' => $chapter->chapter_number,
                    'font_sizes' => $this->extractFontSizesFromRawContent($chapter->content),
                ]);

                $html = $this->convertTiptapToHtml($chapter->content);
                $html = $this->stripInlineFontSizes($html);
                $chapterContents[$chapter->id] = $this->applyInlineFontSizeToHtml($html, '20px');

                Log::info('PdfExportService: Chapter content font sizes (html)', [
                    'chapter_id' => $chapter->id,
                    'chapter_number' => $chapter->chapter_number,
                    'font_sizes' => $this->extractFontSizesFromHtml($chapterContents[$chapter->id]),
                ]);
            }

            // Get preliminary pages
            $preliminaryPages = $this->projectPrelimService->resolve($project);

            // Create temp directory for intermediate PDFs
            $tempDir = storage_path('app/temp/pdf_export_'.uniqid());
            File::makeDirectory($tempDir, 0755, true);

            Log::info('PdfExportService: Created temp directory', ['path' => $tempDir]);

            // Generate each section as separate PDF
            $titlePdf = $this->generateTitlePagePdf($project, $tempDir);
            $frontmatterPdf = $this->generateFrontmatterPdf($project, $chapters, $preliminaryPages, $tempDir);
            $mainContentPdf = $this->generateMainContentPdf($project, $chapters, $chapterContents, $tempDir);

            // Merge all PDFs
            $finalPdf = $this->mergePdfs(
                [$titlePdf, $frontmatterPdf, $mainContentPdf],
                $project,
                $tempDir
            );

            // Move final PDF to exports directory
            $exportDir = storage_path('app/public/project-exports/'.date('Y/m'));
            if (! File::isDirectory($exportDir)) {
                File::makeDirectory($exportDir, 0755, true);
            }

            $fileName = sprintf(
                '%s_full_project_%s.pdf',
                Str::slug($project->title),
                now()->format('Ymd-His')
            );
            $finalPath = $exportDir.'/'.$fileName;

            File::move($finalPdf, $finalPath);

            // Cleanup temp directory
            File::deleteDirectory($tempDir);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('PdfExportService: Export completed', [
                'project_id' => $project->id,
                'final_path' => $finalPath,
                'execution_time_ms' => $executionTime,
            ]);

            return $finalPath;
        } catch (\Exception $e) {
            Log::error('PdfExportService: Export failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Cleanup temp directory on failure
            if (isset($tempDir) && File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }

            throw $e;
        }
    }

    /**
     * Generate title page PDF (no page number)
     */
    private function generateTitlePagePdf(Project $project, string $tempDir): string
    {
        $html = View::make('pdf.sections.title-page', [
            'project' => $project,
        ])->render();

        $outputPath = $tempDir.'/01_title.pdf';

        $this->generatePdfFromHtml($html, $outputPath, null, 1.0); // No footer

        Log::info('PdfExportService: Title page generated', ['path' => $outputPath]);

        return $outputPath;
    }

    /**
     * Generate frontmatter PDF with Roman numeral page numbers
     */
    private function generateFrontmatterPdf(
        Project $project,
        $chapters,
        array $preliminaryPages,
        string $tempDir
    ): string {
        $html = View::make('pdf.sections.frontmatter', [
            'project' => $project,
            'chapters' => $chapters,
            'preliminaryPages' => $preliminaryPages,
        ])->render();

        $tempPath = $tempDir.'/02_frontmatter_temp.pdf';
        $outputPath = $tempDir.'/02_frontmatter.pdf';

        // Generate PDF without page numbers first
        $this->generatePdfFromHtml($html, $tempPath, null, 1.0);

        // Add Roman numeral page numbers using FPDI
        $this->addPageNumbersToPdf($tempPath, $outputPath, 'roman', 1);

        // Cleanup temp file
        File::delete($tempPath);

        Log::info('PdfExportService: Frontmatter generated with Roman numerals', ['path' => $outputPath]);

        return $outputPath;
    }

    /**
     * Generate main content PDF with Arabic page numbers starting from 1
     */
    private function generateMainContentPdf(
        Project $project,
        $chapters,
        array $chapterContents,
        string $tempDir
    ): string {
        $html = View::make('pdf.sections.main-content', [
            'project' => $project,
            'chapters' => $chapters,
            'chapterContents' => $chapterContents,
        ])->render();

        $tempPath = $tempDir.'/03_main_temp.pdf';
        $outputPath = $tempDir.'/03_main.pdf';

        // Generate PDF without page numbers first
        $this->generatePdfFromHtml($html, $tempPath, null, 1.2);

        // Add Arabic page numbers using FPDI (starting from 1)
        $this->addPageNumbersToPdf($tempPath, $outputPath, 'arabic', 1);

        // Cleanup temp file
        File::delete($tempPath);

        Log::info('PdfExportService: Main content generated with Arabic numerals', ['path' => $outputPath]);

        return $outputPath;
    }

    /**
     * Convert number to Roman numerals (lowercase)
     */
    private function toRoman(int $num): string
    {
        $romanNumerals = [
            1000 => 'm', 900 => 'cm', 500 => 'd', 400 => 'cd',
            100 => 'c', 90 => 'xc', 50 => 'l', 40 => 'xl',
            10 => 'x', 9 => 'ix', 5 => 'v', 4 => 'iv', 1 => 'i',
        ];

        $result = '';
        foreach ($romanNumerals as $value => $numeral) {
            while ($num >= $value) {
                $result .= $numeral;
                $num -= $value;
            }
        }

        return $result;
    }

    /**
     * Add page numbers to a PDF using TCPDF/FPDI
     */
    private function addPageNumbersToPdf(
        string $inputPath,
        string $outputPath,
        string $numberStyle,
        int $startNumber = 1
    ): void {
        $pdf = new Fpdi;

        // Disable auto page break to prevent creating new pages
        $pdf->SetAutoPageBreak(false, 0);

        // Set margins to 0 to have full control
        $pdf->SetMargins(0, 0, 0);

        // Disable header and footer lines
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pageCount = $pdf->setSourceFile($inputPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);

            // Calculate page number to display
            $displayNumber = $startNumber + ($pageNo - 1);
            $pageNumberText = ($numberStyle === 'roman')
                ? $this->toRoman($displayNumber)
                : (string) $displayNumber;

            // Set font for page number (Times New Roman, 11pt)
            $pdf->SetFont('times', '', 11);

            // Calculate center position for page number
            // A4 width is 210mm, height is 297mm
            // Place number at bottom center: ~15mm from bottom
            $textWidth = $pdf->GetStringWidth($pageNumberText);
            $xPosition = ($size['width'] - $textWidth) / 2;
            $yPosition = $size['height'] - 15;

            // Use Text method (simpler, no borders)
            $pdf->Text($xPosition, $yPosition, $pageNumberText);
        }

        $pdf->Output($outputPath, 'F');

        Log::debug('PdfExportService: Page numbers added', [
            'input' => $inputPath,
            'output' => $outputPath,
            'style' => $numberStyle,
            'start' => $startNumber,
            'pages' => $pageCount,
        ]);
    }

    /**
     * Generate PDF from HTML using Browsershot (no page numbers - added separately)
     */
    private function generatePdfFromHtml(string $html, string $outputPath, $unused = null, float $scale = 1.0): void
    {
        $chromePath = $this->findChromePath();

        Browsershot::html($html)
            ->setChromePath($chromePath)
            ->format('A4')
            ->windowSize(800, 1131) // A4 proportion at 96dpi
            ->scale($scale) // Adjust print scale per section
            ->deviceScaleFactor(2) // Sharper text and better scale accuracy
            ->margins(0, 0, 0, 0) // Use CSS @page margins from base styles
            ->showBackground()
            ->setDelay(3000) // Wait 3 seconds for Mermaid diagrams to render
            ->timeout(180)
            ->noSandbox()
            ->hideHeader()
            ->hideFooter()
            ->save($outputPath);
    }

    /**
     * Merge multiple PDFs using FPDI
     */
    private function mergePdfs(array $pdfPaths, Project $project, string $tempDir): string
    {
        $outputPath = $tempDir.'/final_merged.pdf';

        // Create new PDF with FPDI
        $pdf = new Fpdi;
        $pdf->SetCreator('Finalyze');
        $pdf->SetAuthor($project->student_name ?: ($project->user->name ?? 'Unknown'));
        $pdf->SetTitle($project->title);

        // Disable header and footer lines
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);

        foreach ($pdfPaths as $pdfPath) {
            if (! file_exists($pdfPath)) {
                Log::warning('PdfExportService: PDF file not found', ['path' => $pdfPath]);

                continue;
            }

            $pageCount = $pdf->setSourceFile($pdfPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);

                // Add page with correct orientation
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);

                // Import the page
                $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height'], true);
            }

            Log::debug('PdfExportService: Merged PDF', [
                'path' => $pdfPath,
                'pages' => $pageCount,
            ]);
        }

        $pdf->Output($outputPath, 'F');

        Log::info('PdfExportService: PDFs merged', [
            'output_path' => $outputPath,
            'source_count' => count($pdfPaths),
        ]);

        return $outputPath;
    }

    /**
     * Find Chrome/Chromium executable path
     */
    private function findChromePath(): string
    {
        $chromePaths = [
            config('app.chrome_path'),
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/snap/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        ];

        foreach ($chromePaths as $path) {
            if ($path && file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        throw new \Exception('Chrome/Chromium browser not found for PDF generation');
    }

    /**
     * Convert Tiptap JSON content to HTML
     */
    private function convertTiptapToHtml(string $content): string
    {
        // If content is already HTML, process it for mermaid blocks and return
        if (str_starts_with(trim($content), '<')) {
            return $this->processMermaidInHtml($content);
        }

        // Try to decode as JSON (Tiptap format)
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return nl2br(e($content));
        }

        return $this->tiptapNodeToHtml($json);
    }

    /**
     * Wrap HTML with inline font sizing for consistent PDF rendering.
     */
    private function applyInlineFontSizeToHtml(string $html, string $fontSize): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return '<div style="font-size: '.$fontSize.'; line-height: 2.0;">'.$html.'</div>';
    }

    /**
     * Remove inline font-size declarations to avoid overriding export sizing.
     */
    private function stripInlineFontSizes(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        return preg_replace_callback(
            '/\sstyle=("|\')([^"\']*)\1/i',
            function ($matches) {
                $style = $matches[2];
                $style = preg_replace('/(^|;)\s*font-size\s*:\s*[^;]+/i', '', $style);
                $style = preg_replace('/;{2,}/', ';', $style);
                $style = trim($style, " \t\n\r\0\x0B;");

                if ($style === '') {
                    return '';
                }

                return ' style="'.$style.'"';
            },
            $html
        );
    }

    /**
     * Extract font-size usage from raw content (HTML or Tiptap JSON).
     */
    private function extractFontSizesFromRawContent(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return ['detected' => []];
        }

        if (str_starts_with($content, '<')) {
            return [
                'detected' => $this->extractFontSizesFromHtml($content),
                'source' => 'html',
            ];
        }

        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($json)) {
            return [
                'detected' => [],
                'source' => 'plain_text',
            ];
        }

        $sizes = [];
        $this->collectFontSizesFromTiptap($json, $sizes);

        $sizes = array_values(array_unique($sizes));
        sort($sizes, SORT_NATURAL);

        return [
            'detected' => $sizes,
            'source' => 'tiptap_json',
        ];
    }

    /**
     * Extract font-size usage from HTML inline styles.
     */
    private function extractFontSizesFromHtml(string $html): array
    {
        $sizes = [];
        if ($html === '') {
            return $sizes;
        }

        if (preg_match_all('/font-size\s*:\s*([^;"\']+)/i', $html, $matches)) {
            foreach ($matches[1] as $size) {
                $sizes[] = trim($size);
            }
        }

        $sizes = array_values(array_unique($sizes));
        sort($sizes, SORT_NATURAL);

        return $sizes;
    }

    /**
     * Recursively collect font sizes from Tiptap JSON marks/attrs.
     */
    private function collectFontSizesFromTiptap(array $node, array &$sizes): void
    {
        $marks = $node['marks'] ?? [];
        foreach ($marks as $mark) {
            if (($mark['type'] ?? '') === 'textStyle') {
                $fontSize = $mark['attrs']['fontSize'] ?? null;
                if ($fontSize) {
                    $sizes[] = $fontSize;
                }
            }
        }

        $content = $node['content'] ?? [];
        foreach ($content as $child) {
            if (is_array($child)) {
                $this->collectFontSizesFromTiptap($child, $sizes);
            }
        }
    }

    /**
     * Process HTML content to convert mermaid data attributes to proper mermaid divs
     * and convert image URLs to base64 for PDF rendering
     */
    private function processMermaidInHtml(string $html): string
    {
        // Convert data-mermaid divs to proper mermaid class divs for Mermaid.js
        $html = preg_replace_callback(
            '/<div[^>]*data-mermaid[^>]*data-mermaid-code="([^"]*)"[^>]*>.*?<\/div>/s',
            function ($matches) {
                $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');

                return '<div class="mermaid">'."\n".$code."\n".'</div>';
            },
            $html
        );

        // Also handle pre/code blocks with language-mermaid class
        $html = preg_replace_callback(
            '/<pre[^>]*>\s*<code[^>]*class="[^"]*language-mermaid[^"]*"[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/s',
            function ($matches) {
                $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                $code = strip_tags($code); // Remove any nested HTML tags

                return '<div class="mermaid">'."\n".trim($code)."\n".'</div>';
            },
            $html
        );

        // Convert image src URLs to base64 for PDF rendering
        $html = $this->processImagesInHtml($html);

        return $html;
    }

    /**
     * Process all images in HTML content and convert local URLs to base64
     */
    private function processImagesInHtml(string $html): string
    {
        // Match all img tags and convert their src to base64 if local
        return preg_replace_callback(
            '/<img([^>]*)\ssrc=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) {
                $beforeSrc = $matches[1];
                $src = $matches[2];
                $afterSrc = $matches[3];

                // Convert the src to base64 if it's a local file
                $newSrc = $this->convertImageToBase64($src);

                return '<img'.$beforeSrc.' src="'.$newSrc.'"'.$afterSrc.'>';
            },
            $html
        );
    }

    /**
     * Convert image URL to base64 data URI for PDF rendering
     */
    private function convertImageToBase64(string $src): string
    {
        Log::debug('PdfExportService: Converting image to base64', ['src' => $src]);

        // If already a data URI, return as-is
        if (str_starts_with($src, 'data:')) {
            return $src;
        }

        // If it's an external URL (http/https), return as-is
        // Browsershot can fetch external images
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $src;
        }

        // Handle relative URLs (starting with /storage/)
        if (str_starts_with($src, '/storage/')) {
            $relativePath = substr($src, 9); // Remove '/storage/'
            $filePath = storage_path('app/public/'.$relativePath);

            Log::debug('PdfExportService: Checking file path', [
                'relativePath' => $relativePath,
                'filePath' => $filePath,
                'exists' => file_exists($filePath),
            ]);

            if (file_exists($filePath)) {
                $mimeType = mime_content_type($filePath);
                $imageData = file_get_contents($filePath);
                $base64 = base64_encode($imageData);

                Log::debug('PdfExportService: Successfully converted image to base64', [
                    'mimeType' => $mimeType,
                    'size' => strlen($imageData),
                ]);

                return "data:{$mimeType};base64,{$base64}";
            }

            Log::warning('PdfExportService: Image file not found', ['path' => $filePath, 'src' => $src]);
        } else {
            Log::warning('PdfExportService: Unrecognized image src format', ['src' => $src]);
        }

        // Return original src if conversion fails
        return $src;
    }

    /**
     * Recursively convert Tiptap nodes to HTML
     */
    private function tiptapNodeToHtml(array $node): string
    {
        $html = '';

        if (! isset($node['type'])) {
            return $html;
        }

        $type = $node['type'];
        $content = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];
        $attrs = $node['attrs'] ?? [];

        if ($type === 'text') {
            $text = htmlspecialchars($node['text'] ?? '', ENT_QUOTES, 'UTF-8');

            foreach ($marks as $mark) {
                $text = match ($mark['type']) {
                    'bold' => "<strong>{$text}</strong>",
                    'italic' => "<em>{$text}</em>",
                    'underline' => "<u>{$text}</u>",
                    'code' => "<code>{$text}</code>",
                    'link' => '<a href="'.htmlspecialchars($mark['attrs']['href'] ?? '#', ENT_QUOTES, 'UTF-8').'">'.$text.'</a>',
                    default => $text,
                };
            }

            return $text;
        }

        $childrenHtml = '';
        foreach ($content as $child) {
            $childrenHtml .= $this->tiptapNodeToHtml($child);
        }

        return match ($type) {
            'doc' => $childrenHtml,
            'paragraph' => "<p>{$childrenHtml}</p>",
            'heading' => '<h'.($attrs['level'] ?? 1).'>'.$childrenHtml.'</h'.($attrs['level'] ?? 1).'>',
            'bulletList' => "<ul>{$childrenHtml}</ul>",
            'orderedList' => "<ol>{$childrenHtml}</ol>",
            'listItem' => "<li>{$childrenHtml}</li>",
            'blockquote' => "<blockquote>{$childrenHtml}</blockquote>",
            'codeBlock' => "<pre><code>{$childrenHtml}</code></pre>",
            'hardBreak' => '<br>',
            'horizontalRule' => '<hr>',
            'mermaid' => '<div class="mermaid">'."\n".($attrs['code'] ?? $childrenHtml)."\n".'</div>',
            'resizableImage' => $this->renderImageForPdf($attrs),
            'table' => "<table>{$childrenHtml}</table>",
            'tableRow' => "<tr>{$childrenHtml}</tr>",
            'tableHeader' => "<th>{$childrenHtml}</th>",
            'tableCell' => "<td>{$childrenHtml}</td>",
            default => $childrenHtml,
        };
    }

    /**
     * Render a resizable image for PDF output with base64 conversion
     */
    private function renderImageForPdf(array $attrs): string
    {
        $src = $attrs['src'] ?? '';
        $alt = htmlspecialchars($attrs['alt'] ?? '', ENT_QUOTES, 'UTF-8');
        $width = $attrs['width'] ?? null;
        $height = $attrs['height'] ?? null;
        $alignment = $attrs['alignment'] ?? 'center';
        $caption = $attrs['caption'] ?? '';

        // Convert local images to base64
        $src = $this->convertImageToBase64($src);

        // Build style for alignment
        $containerStyle = match ($alignment) {
            'left' => 'text-align: left;',
            'right' => 'text-align: right;',
            default => 'text-align: center;',
        };

        // Build image attributes
        $imgAttrs = 'src="'.$src.'" alt="'.$alt.'"';
        if ($width) {
            $imgAttrs .= ' width="'.$width.'"';
        }
        if ($height) {
            $imgAttrs .= ' height="'.$height.'"';
        }
        $imgAttrs .= ' style="max-width: 100%; height: auto;"';

        $html = '<figure style="'.$containerStyle.' margin: 1em 0;">';
        $html .= '<img '.$imgAttrs.'>';

        if ($caption) {
            $html .= '<figcaption style="font-style: italic; font-size: 0.9em; color: #666; margin-top: 0.5em;">'.htmlspecialchars($caption, ENT_QUOTES, 'UTF-8').'</figcaption>';
        }

        $html .= '</figure>';

        return $html;
    }
}
