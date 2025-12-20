<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use App\Services\ProjectPrelimService;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

class ExportService
{
    public function __construct(
        protected ProjectPrelimService $projectPrelimService
    ) {}

    /**
     * Check if Pandoc is available on the system
     */
    private function isPandocAvailable(): bool
    {
        $output = [];
        $returnCode = 0;
        exec('pandoc --version 2>&1', $output, $returnCode);

        return $returnCode === 0;
    }

    /**
     * Convert HTML to DOCX using Pandoc for superior quality
     */
    private function convertWithPandoc(string $html, string $outputPath, array $metadata = []): bool
    {
        try {
            // Create temp directory for conversion
            $tempDir = storage_path('app/temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Create temporary HTML file
            $tempHtmlFile = $tempDir.'/export_'.uniqid().'.html';

            // Prepare HTML with metadata and proper structure
            $fullHtml = $this->prepareHtmlForPandoc($html, $metadata);

            // Write HTML to temp file
            file_put_contents($tempHtmlFile, $fullHtml);

            // Get reference template path
            $referenceDoc = resource_path('templates/reference.docx');
            $referenceOption = file_exists($referenceDoc) ? '--reference-doc='.escapeshellarg($referenceDoc) : '';

            // Build Pandoc command with enhanced options
            $command = sprintf(
                'pandoc %s -f html -t docx -o %s %s --standalone 2>&1',
                escapeshellarg($tempHtmlFile),
                escapeshellarg($outputPath),
                $referenceOption
            );

            // Execute Pandoc
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            // Clean up temp file
            if (file_exists($tempHtmlFile)) {
                unlink($tempHtmlFile);
            }

            if ($returnCode !== 0) {
                Log::warning('Pandoc conversion failed', [
                    'command' => $command,
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode,
                ]);

                return false;
            }

            Log::info('Pandoc conversion successful', [
                'output_file' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Pandoc conversion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Prepare HTML for optimal Pandoc conversion
     */
    private function prepareHtmlForPandoc(string $html, array $metadata = []): string
    {
        // Build HTML document with proper structure
        $title = $metadata['title'] ?? 'Document';
        $author = $metadata['author'] ?? '';

        $htmlDocument = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
HTML;

        if ($author) {
            $htmlDocument .= "\n    <meta name=\"author\" content=\"{$author}\">";
        }

        $htmlDocument .= <<<'HTML'

    <style>
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 2.0;
        }
        h1, h2, h3, h4, h5, h6 {
            font-weight: bold;
            margin-top: 1em;
            margin-bottom: 0.5em;
        }
        h1 { font-size: 14pt; text-align: center; }
        h2 { font-size: 13pt; }
        h3 { font-size: 12pt; }
        p {
            text-align: justify;
            margin-bottom: 1em;
        }
        blockquote {
            margin-left: 1in;
            margin-right: 1in;
            font-style: italic;
        }
        pre, code {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            background-color: #f4f4f4;
        }
        table {
            border-collapse: collapse;
            margin: 1em auto;
        }
        table, th, td {
            border: 1px solid black;
            padding: 0.5em;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>
<body>
HTML;

        // Clean and enhance the content HTML
        $cleanedHtml = $this->preprocessHtmlForPandoc($html);

        $htmlDocument .= "\n{$cleanedHtml}\n</body>\n</html>";

        return $htmlDocument;
    }

    /**
     * Preprocess HTML content for better Pandoc conversion
     */
    private function preprocessHtmlForPandoc(string $html): string
    {
        // CRITICAL: Escape HTML entities first to prevent XML corruption
        // This fixes issues with &, <, >, quotes in content
        $html = $this->escapeHtmlEntities($html);

        // Don't strip all styles - Pandoc handles them better than PHPWord
        // Just clean up problematic patterns

        // Remove data-* attributes that might confuse Pandoc
        $html = preg_replace('/\s*data-[a-z-]+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Preserve important inline styles (colors, backgrounds)
        // but remove position/display/width CSS that doesn't translate to Word
        $html = preg_replace_callback('/style\s*=\s*["\']([^"\']*)["\']/', function ($matches) {
            $style = $matches[1];

            // Keep only Word-relevant CSS properties
            $allowedProps = ['color', 'background-color', 'font-size', 'font-weight', 'font-style', 'text-align'];
            $styleParts = explode(';', $style);
            $filteredParts = [];

            foreach ($styleParts as $part) {
                $part = trim($part);
                if (empty($part)) {
                    continue;
                }

                foreach ($allowedProps as $prop) {
                    if (stripos($part, $prop) === 0) {
                        $filteredParts[] = $part;
                        break;
                    }
                }
            }

            if (empty($filteredParts)) {
                return '';
            }

            return 'style="'.implode('; ', $filteredParts).'"';
        }, $html);

        // Convert Tiptap-specific code blocks to standard pre/code
        $html = preg_replace('/<pre[^>]*><code[^>]*class="language-([^"]*)"[^>]*>(.*?)<\/code><\/pre>/is', '<pre><code class="$1">$2</code></pre>', $html);

        // Ensure tables have proper structure
        $html = preg_replace('/<table[^>]*>/', '<table>', $html);

        // Convert <strong> and <em> properly
        $html = str_replace(['<strong>', '</strong>'], ['<b>', '</b>'], $html);
        $html = str_replace(['<em>', '</em>'], ['<i>', '</i>'], $html);

        return $html;
    }

    /**
     * Properly escape HTML entities in text content while preserving HTML tags
     * This prevents XML corruption in DOCX files from special characters like &, <, >, etc.
     */
    private function escapeHtmlEntities(string $html): string
    {
        // Strategy: Only escape text nodes, preserve HTML structure
        // We need to handle & and other special chars WITHOUT breaking HTML tags

        // First, protect HTML tags by temporarily replacing them
        $tagPlaceholders = [];
        $tagCounter = 0;

        // Match and protect all HTML tags (opening, closing, self-closing)
        $html = preg_replace_callback('/<[^>]+>/', function ($matches) use (&$tagPlaceholders, &$tagCounter) {
            $placeholder = '___TAG_PLACEHOLDER_'.$tagCounter.'___';
            $tagPlaceholders[$placeholder] = $matches[0];
            $tagCounter++;

            return $placeholder;
        }, $html);

        // Now escape special characters in the remaining text content
        $html = str_replace('&', '&amp;', $html);
        // Don't escape < and > as they should be protected by tag placeholders

        // Restore HTML tags
        foreach ($tagPlaceholders as $placeholder => $originalTag) {
            $html = str_replace($placeholder, $originalTag, $html);
        }

        return $html;
    }

    /**
     * Export entire project to Word document
     */
    public function exportToWord(Project $project): string
    {
        try {
            $preliminaryPages = $this->projectPrelimService->resolve($project);

            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $filename = $exportDir.'/'.$project->slug.'_'.time().'.docx';

            // Try Pandoc first for superior quality
            if ($this->isPandocAvailable()) {
                Log::info('Using Pandoc for full project export', ['project_id' => $project->id]);

                $fullHtml = $this->buildFullProjectHtml($project, $preliminaryPages);

                $metadata = [
                    'title' => $project->title,
                    'author' => $project->student_name ?: ($project->user->name ?? 'Unknown'),
                ];

                if ($this->convertWithPandoc($fullHtml, $filename, $metadata)) {
                    Log::info('Pandoc full project export successful');

                    return $filename;
                }

                Log::warning('Pandoc failed for full project, falling back to PHPWord');
            }

            // Fallback to PHPWord
            Log::info('Using PHPWord for full project export (fallback)');

            $phpWord = $this->initializeDocument($project);

            // Add title page
            $this->addTitlePage($phpWord, $project);

            // Add preliminary pages
            if (! empty($preliminaryPages)) {
                $this->addPreliminaryPages($phpWord, $preliminaryPages);
            }

            // Add table of contents (simplified approach without TOC field to avoid corruption)
            $this->addTableOfContents($phpWord, $project);

            // Add all chapters
            $chapters = $project->chapters()
                ->orderBy('chapter_number')
                ->get();

            foreach ($chapters as $chapter) {
                if (! empty($chapter->content)) {
                    $this->addChapter($phpWord, $chapter);
                }
            }

            // Add references if available
            if ($project->references) {
                $this->addReferences($phpWord, $project);
            }

            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filename);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Export to Word failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Build complete HTML for full project export (Pandoc)
     */
    private function buildFullProjectHtml(Project $project, array $preliminaryPages = []): string
    {
        $html = '';

        // Title page - escape all text content
        $html .= '<div style="text-align: center; margin-bottom: 3em;">';
        $html .= '<h1>'.htmlspecialchars(strtoupper($project->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
        $html .= '<p>BY</p>';
        $html .= '<p><strong>'.htmlspecialchars(strtoupper($project->student_name ?: ($project->user->name ?? 'AUTHOR NAME')), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong></p>';

        if ($project->abstract) {
            $html .= '<div style="margin-top: 2em;">';
            $html .= '<h2>ABSTRACT</h2>';
            $html .= '<p style="text-align: justify;">'.htmlspecialchars($project->abstract, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            $html .= '</div>';
        }

        if ($project->university) {
            $html .= '<p>'.htmlspecialchars(strtoupper($project->university), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        if ($project->field_of_study) {
            $html .= '<p>Department of '.htmlspecialchars($project->field_of_study, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        $html .= '<p>'.$project->created_at->format('F Y').'</p>';
        $html .= '</div>';
        $html .= '<div style="page-break-after: always;"></div>';

        // Preliminary pages
        foreach ($preliminaryPages as $page) {
            $title = strtoupper($page['title'] ?? '');
            $content = $page['html'] ?? '';

            $html .= '<h1 style="text-align: center;">'.$title.'</h1>';
            $html .= $content;
            $html .= '<div style="page-break-after: always;"></div>';
        }

        // Table of contents
        $html .= '<h1 style="text-align: center;">TABLE OF CONTENTS</h1>';

        $chapters = $project->chapters()->orderBy('chapter_number')->get();
        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<p>CHAPTER '.$chapter->chapter_number.': '.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            }
        }

        if ($project->references) {
            $html .= '<p>REFERENCES</p>';
        }

        $html .= '<div style="page-break-after: always;"></div>';

        // All chapters
        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<h1>CHAPTER '.$this->numberToWords($chapter->chapter_number).'</h1>';
                $html .= '<h1>'.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
                $html .= $chapter->content; // Content is processed by preprocessHtmlForPandoc which handles escaping
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        // References
        if ($project->references) {
            $html .= '<h1>REFERENCES</h1>';
            $references = json_decode($project->references, true) ?? [];

            if (! empty($references)) {
                $refNumber = 1;
                foreach ($references as $reference) {
                    $refText = $reference['citation'] ??
                        $reference['text'] ??
                        $reference['title'] ??
                        'Unknown reference';

                    $html .= '<p>'.$refNumber.'. '.htmlspecialchars($refText, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
                    $refNumber++;
                }
            }
        }

        return $html;
    }

    /**
     * Export single chapter to Word document
     */
    public function exportChapterToWord(Project $project, $chapter): string
    {
        try {
            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            // Generate filename
            $filename = $exportDir.'/'.$project->slug.'-chapter-'.$chapter->chapter_number.'_'.time().'.docx';

            // Try Pandoc first for superior quality
            if ($this->isPandocAvailable() && ! empty($chapter->content)) {
                Log::info('Using Pandoc for chapter export', ['chapter_id' => $chapter->id]);

                // Build chapter HTML with title - escape special characters
                $chapterHtml = '<h1>CHAPTER '.$this->numberToWords($chapter->chapter_number).'</h1>';
                $chapterHtml .= '<h1>'.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
                $chapterHtml .= $chapter->content; // Content is processed by preprocessHtmlForPandoc

                $metadata = [
                    'title' => htmlspecialchars($project->title.' - Chapter '.$chapter->chapter_number, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'author' => htmlspecialchars($project->student_name ?: ($project->user->name ?? 'Unknown'), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ];

                if ($this->convertWithPandoc($chapterHtml, $filename, $metadata)) {
                    Log::info('Pandoc chapter export successful');

                    return $filename;
                }

                Log::warning('Pandoc failed, falling back to PHPWord');
            }

            // Fallback to PHPWord if Pandoc not available or failed
            Log::info('Using PHPWord for chapter export (fallback)', ['chapter_id' => $chapter->id]);

            $phpWord = $this->initializeDocument($project);
            $section = $phpWord->addSection();

            // Add chapter title
            $section->addText('Chapter: '.$chapter->title);

            // Add chapter content
            if (! empty($chapter->content)) {
                $cleanHtml = $this->removeInlineStyles($chapter->content);

                try {
                    Html::addHtml($section, $cleanHtml, false, false);
                } catch (\Exception $e) {
                    Log::error('HTML parsing failed, falling back to plain text', ['error' => $e->getMessage()]);
                    $plainText = strip_tags($cleanHtml);
                    $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $section->addText($plainText);
                }
            } else {
                $section->addText('No content available');
            }

            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filename);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Export chapter to Word failed', [
                'project_id' => $project->id,
                'chapter_id' => $chapter->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Export multiple selected chapters to Word document
     */
    public function exportMultipleChaptersToWord(Project $project, array $chapterNumbers): string
    {
        try {
            // Set memory limits for large documents
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            // Convert to integers and validate
            $chapterNumbers = array_map('intval', $chapterNumbers);
            sort($chapterNumbers); // Ensure proper order

            Log::info('Starting multiple chapters export', [
                'project_id' => $project->id,
                'chapter_numbers' => $chapterNumbers,
            ]);

            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $chaptersString = implode('-', $chapterNumbers);
            $filename = $exportDir.'/'.$project->slug.'-chapters-'.$chaptersString.'_'.time().'.docx';

            // Try Pandoc first
            if ($this->isPandocAvailable()) {
                Log::info('Using Pandoc for multiple chapters export');

                $html = $this->buildMultipleChaptersHtml($project, $chapterNumbers);

                $metadata = [
                    'title' => $project->title.' (Selected Chapters)',
                    'author' => $project->student_name ?: ($project->user->name ?? 'Unknown'),
                ];

                if ($this->convertWithPandoc($html, $filename, $metadata)) {
                    Log::info('Pandoc multiple chapters export successful');

                    return $filename;
                }

                Log::warning('Pandoc failed for multiple chapters, falling back to PHPWord');
            }

            // Fallback to PHPWord
            Log::info('Using PHPWord for multiple chapters export (fallback)');

            $phpWord = $this->initializeDocument($project);

            // Add title page
            $this->addTitlePage($phpWord, $project, $chapterNumbers);

            // Add simplified table of contents
            $this->addSimpleTableOfContents($phpWord, $project, $chapterNumbers);

            // Get and add selected chapters
            $chapters = $project->chapters()
                ->whereIn('chapter_number', $chapterNumbers)
                ->orderBy('chapter_number')
                ->get();

            if ($chapters->isEmpty()) {
                throw new \Exception('No chapters found for the selected chapter numbers');
            }

            foreach ($chapters as $chapter) {
                if (! empty($chapter->content)) {
                    $this->addChapter($phpWord, $chapter);
                }
            }

            // Add references if it's the last chapter set
            $maxChapter = $project->chapters()->max('chapter_number');
            if (in_array($maxChapter, $chapterNumbers) && $project->references) {
                $this->addReferences($phpWord, $project);
            }

            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($filename);

            return $filename;
        } catch (\Exception $e) {
            Log::error('Export multiple chapters failed', [
                'project_id' => $project->id,
                'chapter_numbers' => $chapterNumbers ?? [],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Build HTML for multiple selected chapters export (Pandoc)
     */
    private function buildMultipleChaptersHtml(Project $project, array $chapterNumbers): string
    {
        $html = '';

        // Title page - escape all text content
        $html .= '<div style="text-align: center; margin-bottom: 3em;">';
        $html .= '<h1>'.htmlspecialchars(strtoupper($project->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
        $html .= '<p>(Selected Chapters: '.implode(', ', $chapterNumbers).')</p>';
        $html .= '<p>BY</p>';
        $html .= '<p><strong>'.htmlspecialchars(strtoupper($project->student_name ?: ($project->user->name ?? 'AUTHOR NAME')), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</strong></p>';

        if ($project->university) {
            $html .= '<p>'.htmlspecialchars(strtoupper($project->university), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
        }

        $html .= '<p>'.$project->created_at->format('F Y').'</p>';
        $html .= '</div>';
        $html .= '<div style="page-break-after: always;"></div>';

        // Table of contents
        $html .= '<h1 style="text-align: center;">TABLE OF CONTENTS</h1>';

        $chapters = $project->chapters()
            ->whereIn('chapter_number', $chapterNumbers)
            ->orderBy('chapter_number')
            ->get();

        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<p>CHAPTER '.$chapter->chapter_number.': '.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
            }
        }

        $html .= '<div style="page-break-after: always;"></div>';

        // Selected chapters
        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $html .= '<h1>CHAPTER '.$this->numberToWords($chapter->chapter_number).'</h1>';
                $html .= '<h1>'.htmlspecialchars(strtoupper($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>';
                $html .= $chapter->content; // Content is processed by preprocessHtmlForPandoc
                $html .= '<div style="page-break-after: always;"></div>';
            }
        }

        // Add references if applicable
        $maxChapter = $project->chapters()->max('chapter_number');
        if (in_array($maxChapter, $chapterNumbers) && $project->references) {
            $html .= '<h1>REFERENCES</h1>';
            $references = json_decode($project->references, true) ?? [];

            if (! empty($references)) {
                $refNumber = 1;
                foreach ($references as $reference) {
                    $refText = $reference['citation'] ??
                        $reference['text'] ??
                        $reference['title'] ??
                        'Unknown reference';

                    $html .= '<p>'.$refNumber.'. '.htmlspecialchars($refText, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>';
                    $refNumber++;
                }
            }
        }

        return $html;
    }

    /**
     * Initialize PHPWord document with minimal settings to prevent corruption
     */
    private function initializeDocument(Project $project): PhpWord
    {
        $phpWord = new PhpWord;

        // Only set basic document properties - no complex formatting
        $properties = $phpWord->getDocInfo();
        $properties->setCreator($project->student_name ?: ($project->user->name ?? 'Unknown'));
        $properties->setTitle($project->title);

        // NO font settings, NO styles, NO language settings
        // All of these could potentially cause corruption

        return $phpWord;
    }

    /**
     * Add title page to document
     */
    private function addTitlePage(PhpWord $phpWord, Project $project, array $selectedChapters = []): void
    {
        $section = $phpWord->addSection();

        // Title
        $section->addText(
            strtoupper($project->title),
            ['size' => 16, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 400]
        );

        // If selected chapters, indicate it's a partial export
        if (! empty($selectedChapters)) {
            $section->addText(
                '(Selected Chapters: '.implode(', ', $selectedChapters).')',
                ['size' => 12, 'italic' => true],
                ['alignment' => 'center', 'spaceAfter' => 200]
            );
        }

        // Author
        $section->addText(
            'BY',
            ['size' => 12],
            ['alignment' => 'center', 'spaceAfter' => 100]
        );

        $section->addText(
            strtoupper($project->student_name ?: ($project->user->name ?? 'AUTHOR NAME')),
            ['size' => 14, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 400]
        );

        // University and Department
        if ($project->university) {
            $section->addText(
                strtoupper($project->university),
                ['size' => 12],
                ['alignment' => 'center', 'spaceAfter' => 100]
            );
        }

        if ($project->field_of_study) {
            $section->addText(
                'Department of '.$project->field_of_study,
                ['size' => 12],
                ['alignment' => 'center', 'spaceAfter' => 200]
            );
        }

        // Date
        $section->addText(
            $project->created_at->format('F Y'),
            ['size' => 12],
            ['alignment' => 'center']
        );

        $section->addPageBreak();
    }

    /**
     * Add preliminary pages as simple sections before TOC
     *
     * @param array<int, array{slug:string,title:string,html:string}> $preliminaryPages
     */
    private function addPreliminaryPages(PhpWord $phpWord, array $preliminaryPages): void
    {
        foreach ($preliminaryPages as $page) {
            $section = $phpWord->addSection();

            $section->addText(
                strtoupper($page['title']),
                ['size' => 14, 'bold' => true],
                ['alignment' => 'center', 'spaceAfter' => 200]
            );

            $this->addHtmlContent($section, $page['html'] ?? '');

            $section->addPageBreak();
        }
    }

    /**
     * Add chapter header for single chapter export - NO FORMATTING to prevent corruption
     */
    private function addChapterHeader(PhpWord $phpWord, Project $project, $chapter): void
    {
        $section = $phpWord->addSection();

        $section->addText(
            strtoupper($project->title),
            [], // No font formatting
            ['alignment' => 'center', 'spaceAfter' => 200]
        );

        $section->addText(
            'CHAPTER '.$chapter->chapter_number,
            [], // No font formatting
            ['alignment' => 'center', 'spaceAfter' => 100]
        );

        $section->addText(
            strtoupper($chapter->title),
            [], // No font formatting
            ['alignment' => 'center', 'spaceAfter' => 300]
        );

        $section->addText(
            'Author: '.($project->student_name ?: $project->user->name),
            [], // No font formatting
            ['alignment' => 'center', 'spaceAfter' => 100]
        );

        if ($project->university) {
            $section->addText(
                $project->university,
                [], // No font formatting
                ['alignment' => 'center', 'spaceAfter' => 200]
            );
        }

        $section->addPageBreak();
    }

    /**
     * Add simplified table of contents (without TOC field to avoid corruption)
     */
    private function addTableOfContents(PhpWord $phpWord, Project $project): void
    {
        $section = $phpWord->addSection();

        $section->addText(
            'TABLE OF CONTENTS',
            ['size' => 14, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 300]
        );

        $chapters = $project->chapters()
            ->orderBy('chapter_number')
            ->get();

        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $section->addText(
                    'CHAPTER '.$chapter->chapter_number.': '.strtoupper($chapter->title),
                    ['size' => 12],
                    ['alignment' => 'left', 'indentation' => ['left' => 240], 'spaceAfter' => 100]
                );
            }
        }

        if ($project->references) {
            $section->addText(
                'REFERENCES',
                ['size' => 12],
                ['alignment' => 'left', 'indentation' => ['left' => 240], 'spaceAfter' => 100]
            );
        }

        $section->addPageBreak();
    }

    /**
     * Add simple table of contents for selected chapters
     */
    private function addSimpleTableOfContents(PhpWord $phpWord, Project $project, array $chapterNumbers): void
    {
        $section = $phpWord->addSection();

        $section->addText(
            'TABLE OF CONTENTS',
            ['size' => 14, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 300]
        );

        $chapters = $project->chapters()
            ->whereIn('chapter_number', $chapterNumbers)
            ->orderBy('chapter_number')
            ->get();

        foreach ($chapters as $chapter) {
            if (! empty($chapter->content)) {
                $section->addText(
                    'CHAPTER '.$chapter->chapter_number.': '.strtoupper($chapter->title),
                    ['size' => 12],
                    ['alignment' => 'left', 'indentation' => ['left' => 240], 'spaceAfter' => 100]
                );
            }
        }

        $section->addPageBreak();
    }

    /**
     * Add chapter content to document
     */
    private function addChapter(PhpWord $phpWord, $chapter): void
    {
        try {
            Log::info('Creating new section for chapter content');
            $section = $phpWord->addSection();

            // Add proper centered, bold chapter headers
            $section->addText(
                'CHAPTER '.$this->numberToWords($chapter->chapter_number),
                ['bold' => true, 'size' => 14],
                ['alignment' => 'center', 'spaceAfter' => 200]
            );

            $section->addText(
                strtoupper($chapter->title),
                ['bold' => true, 'size' => 14],
                ['alignment' => 'center', 'spaceAfter' => 300]
            );

            // Process chapter content
            if (! empty($chapter->content)) {
                Log::info('Processing chapter content', ['content_length' => strlen($chapter->content)]);
                $this->addHtmlContent($section, $chapter->content);
            } else {
                Log::info('Chapter has no content, adding placeholder');
                $section->addText(
                    '[This chapter has no content yet]',
                    [],
                    ['alignment' => 'center', 'spaceAfter' => 200]
                );
            }

            Log::info('Adding page break after chapter');
            $section->addPageBreak();
        } catch (\Exception $e) {
            Log::error('Error adding chapter to document', [
                'chapter_id' => $chapter->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Add error placeholder instead of failing - NO FORMATTING
            $section = $phpWord->addSection();
            $section->addText(
                'Chapter '.($chapter->chapter_number ?? '?').': '.($chapter->title ?? 'Unknown'),
                [], // No font formatting
                ['spaceAfter' => 200]
            );
            $section->addText(
                '[Error processing chapter content]',
                [], // No font formatting
                []
            );
            $section->addPageBreak();
        }
    }

    /**
     * Add content to section using only native PHPWord methods
     */
    private function addHtmlContent(Section $section, string $htmlContent): void
    {
        if (empty($htmlContent)) {
            return;
        }

        $cleanHtml = $this->removeInlineStyles($htmlContent);

        try {
            Html::addHtml($section, $cleanHtml, false, false);
        } catch (\Exception $e) {
            Log::error('HTML parsing failed, falling back to plain text', ['error' => $e->getMessage()]);
            $plainText = strip_tags($cleanHtml);
            $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $section->addText($plainText);
        }
    }

    /**
     * Add structured text content preserving formatting using native PHPWord methods
     */
    private function addStructuredTextContent(Section $section, string $htmlContent): void
    {
        // Parse HTML and convert to structured text with formatting
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $this->processNode($section, $dom->documentElement);
    }

    /**
     * Process DOM nodes and add to Word section with appropriate formatting
     */
    private function processNode(Section $section, \DOMNode $node): void
    {
        foreach ($node->childNodes as $child) {
            switch ($child->nodeName) {
                case 'h1':
                case 'h2':
                case 'h3':
                    $section->addText($child->textContent, ['size' => 14, 'bold' => true], ['spaceAfter' => 200]);
                    break;
                case 'p':
                    if (! empty(trim($child->textContent))) {
                        $section->addText($child->textContent, [], ['alignment' => 'both', 'spaceAfter' => 120]);
                    }
                    break;
                case 'strong':
                    $section->addText($child->textContent, ['bold' => true], ['spaceAfter' => 0]);
                    break;
                case 'em':
                    $section->addText($child->textContent, ['italic' => true], ['spaceAfter' => 0]);
                    break;
                case 'ul':
                case 'ol':
                    $this->processListNode($section, $child);
                    break;
                default:
                    if ($child->hasChildNodes()) {
                        $this->processNode($section, $child);
                    } else {
                        $text = trim($child->textContent);
                        if (! empty($text)) {
                            $section->addText($text, [], ['spaceAfter' => 120]);
                        }
                    }
                    break;
            }
        }
    }

    /**
     * Process list nodes
     */
    private function processListNode(Section $section, \DOMNode $listNode): void
    {
        foreach ($listNode->childNodes as $listItem) {
            if ($listItem->nodeName === 'li') {
                $section->addText('• '.$listItem->textContent, [], ['indentation' => ['left' => 360], 'spaceAfter' => 120]);
            }
        }
    }

    /**
     * Add clean text content with minimal structure using only native PHPWord methods
     */
    private function addCleanTextContent(Section $section, string $htmlContent): void
    {
        // Completely strip all HTML and only preserve basic line structure
        $plainText = strip_tags($htmlContent);
        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Split by double line breaks to identify paragraphs
        $paragraphs = array_filter(preg_split('/\n\s*\n/', $plainText), function ($p) {
            return ! empty(trim($p));
        });

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if (empty($paragraph)) {
                continue;
            }

            // Split long paragraphs into lines and handle each line
            $lines = array_filter(explode("\n", $paragraph), function ($line) {
                return ! empty(trim($line));
            });

            foreach ($lines as $line) {
                $line = trim($line);

                if (empty($line)) {
                    continue;
                }

                // Add as simple text with basic paragraph formatting
                // No complex styles that could cause corruption
                $section->addText(
                    $line,
                    [], // No font formatting
                    ['alignment' => 'both', 'lineHeight' => 1.6, 'spaceAfter' => 120] // Basic paragraph style
                );
            }

            // Add extra space between paragraphs
            $section->addText('', [], ['spaceAfter' => 100]);
        }
    }

    /**
     * Remove inline CSS styles but preserve HTML structure tags
     */
    private function removeInlineStyles(string $html): string
    {
        // Enhance semantic tags for better Word formatting
        $htmlWithEnhancedTags = $this->enhanceSemanticTags($html);

        // Remove style attributes from all tags
        $cleanHtml = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/', '', $htmlWithEnhancedTags);

        // Remove class attributes that are only for web styling
        $cleanHtml = preg_replace('/\s*class\s*=\s*["\'][^"\']*["\']/', '', $cleanHtml);

        // Remove empty span tags that were only used for styling
        $cleanHtml = preg_replace('/<span\s*>\s*(.*?)\s*<\/span>/', '$1', $cleanHtml);
        $cleanHtml = preg_replace('/<span\s*\/?>/', '', $cleanHtml);

        // Clean up any malformed tags after removing attributes
        $cleanHtml = preg_replace('/<(\w+)\s+>/', '<$1>', $cleanHtml);

        // Remove any remaining empty attributes
        $cleanHtml = preg_replace('/\s+>/', '>', $cleanHtml);

        return $cleanHtml;
    }

    /**
     * Enhance semantic HTML tags for better Word export formatting
     */
    private function enhanceSemanticTags(string $html): string
    {
        // Wrap all heading content in <strong> tags for better Word formatting
        $html = preg_replace_callback('/<h([1-6])([^>]*)>(.*?)<\/h([1-6])>/is', function ($matches) {
            $level = $matches[1];
            $attributes = $matches[2];
            $content = $matches[3];
            $closingLevel = $matches[4];

            // Only process if opening and closing levels match
            if ($level === $closingLevel) {
                // If content doesn't already have strong tags, add them
                if (strpos($content, '<strong>') === false) {
                    return "<h{$level}{$attributes}><strong>{$content}</strong></h{$level}>";
                }

                return $matches[0]; // Already has strong tags
            }

            // Return original if mismatch
            return $matches[0];
        }, $html);

        // Convert <b> tags to <strong> (properly handle opening/closing)
        $html = preg_replace('/<b\b[^>]*>/i', '<strong>', $html);
        $html = preg_replace('/<\/b>/i', '</strong>', $html);

        // Convert <i> tags to <em> (properly handle opening/closing)
        $html = preg_replace('/<i\b[^>]*>/i', '<em>', $html);
        $html = preg_replace('/<\/i>/i', '</em>', $html);

        // Clean up any doubled tags
        $html = preg_replace('/<\/strong><\/strong>/i', '</strong>', $html);
        $html = preg_replace('/<strong><strong>/i', '<strong>', $html);
        $html = preg_replace('/<\/em><\/em>/i', '</em>', $html);
        $html = preg_replace('/<em><em>/i', '<em>', $html);

        return $html;
    }

    /**
     * Convert chapter number to words (ONE, TWO, THREE, etc.)
     */
    private function numberToWords(int $number): string
    {
        $words = [
            1 => 'ONE',
            2 => 'TWO',
            3 => 'THREE',
            4 => 'FOUR',
            5 => 'FIVE',
            6 => 'SIX',
            7 => 'SEVEN',
            8 => 'EIGHT',
            9 => 'NINE',
            10 => 'TEN',
        ];

        return $words[$number] ?? (string) $number;
    }

    /**
     * Add references section
     */
    private function addReferences(PhpWord $phpWord, Project $project): void
    {
        $section = $phpWord->addSection();

        $section->addText(
            'REFERENCES',
            ['size' => 14, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 300]
        );

        $references = json_decode($project->references, true) ?? [];

        if (empty($references)) {
            $section->addText(
                '[No references available]',
                ['italic' => true],
                ['alignment' => 'center']
            );

            return;
        }

        $refNumber = 1;
        foreach ($references as $reference) {
            // Extract reference text
            $refText = $reference['citation'] ??
                $reference['text'] ??
                $reference['title'] ??
                'Unknown reference';

            $section->addText(
                $refNumber.'. '.$refText,
                [],
                ['alignment' => 'both', 'lineHeight' => 1.5, 'spaceAfter' => 150, 'indentation' => ['left' => 360, 'hanging' => 360]]
            );

            $refNumber++;
        }
    }

    /**
     * Save document to file
     */
    private function saveDocument(PhpWord $phpWord, string $filename): string
    {
        // Ensure exports directory exists
        $exportDir = storage_path('app/exports');
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Clean up old exports (older than 1 hour)
        $this->cleanupOldExports($exportDir);

        // Generate unique filename
        $fullPath = $exportDir.'/'.$filename.'_'.time().'.docx';

        try {
            // Create writer and save
            $writer = IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($fullPath);

            Log::info('Document saved successfully', [
                'filename' => $fullPath,
                'size' => filesize($fullPath),
            ]);

            return $fullPath;
        } catch (\Exception $e) {
            Log::error('Failed to save document', [
                'filename' => $fullPath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to save document: '.$e->getMessage());
        }
    }

    /**
     * Clean up old export files
     */
    private function cleanupOldExports(string $exportDir): void
    {
        try {
            $files = glob($exportDir.'/*.docx');
            $now = time();

            foreach ($files as $file) {
                // Delete files older than 1 hour
                if (is_file($file) && ($now - filemtime($file) > 3600)) {
                    unlink($file);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup old exports', ['error' => $e->getMessage()]);
        }
    }
}
