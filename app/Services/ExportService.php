<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

class ExportService
{
    /**
     * Export entire project to Word document
     */
    public function exportToWord(Project $project): string
    {
        try {
            $phpWord = $this->initializeDocument($project);

            // Add title page
            $this->addTitlePage($phpWord, $project);

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

            return $this->saveDocument($phpWord, $project->slug);
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
     * Export single chapter to Word document
     */
    public function exportChapterToWord(Project $project, $chapter): string
    {
        try {
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

            // Ensure exports directory exists
            $exportDir = storage_path('app/exports');
            if (! is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            // Generate proper filename
            $filename = $exportDir.'/'.$project->slug.'-chapter-'.$chapter->chapter_number.'_'.time().'.docx';

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

            $chaptersString = implode('-', $chapterNumbers);

            return $this->saveDocument($phpWord, "{$project->slug}-chapters-{$chaptersString}");
        } catch (\Exception $e) {
            Log::error('Export multiple chapters failed', [
                'project_id' => $project->id,
                'chapter_numbers' => $chapterNumbers,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Initialize PHPWord document with minimal settings to prevent corruption
     */
    private function initializeDocument(Project $project): PhpWord
    {
        $phpWord = new PhpWord;

        // Only set basic document properties - no complex formatting
        $properties = $phpWord->getDocInfo();
        $properties->setCreator($project->user->name ?? 'Unknown');
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
            strtoupper($project->user->name ?? 'AUTHOR NAME'),
            ['size' => 14, 'bold' => true],
            ['alignment' => 'center', 'spaceAfter' => 400]
        );

        // Abstract
        if ($project->abstract) {
            $section->addText(
                'ABSTRACT',
                ['size' => 14, 'bold' => true],
                ['alignment' => 'center', 'spaceAfter' => 200]
            );

            $section->addText(
                $project->abstract,
                ['size' => 12],
                ['alignment' => 'both', 'lineHeight' => 1.5, 'spaceAfter' => 300]
            );
        }

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
            'Author: '.$project->user->name,
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
