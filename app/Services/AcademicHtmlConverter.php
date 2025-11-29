<?php

namespace App\Services;

use Illuminate\Support\Str;

class AcademicHtmlConverter
{
    private int $figureCounter = 0;

    private int $tableCounter = 0;

    private array $headings = [];

    private array $citations = [];

    /**
     * Convert markdown to academic HTML with proper formatting.
     */
    public function convert(string $markdown, int $chapterNumber): string
    {
        // Reset counters for each chapter
        $this->figureCounter = 0;
        $this->tableCounter = 0;
        $this->headings = [];
        $this->citations = [];

        // Extract and process citations first
        $markdown = $this->processCitations($markdown);

        // Convert markdown to HTML
        $html = Str::markdown($markdown);

        // Process academic elements
        $html = $this->processHeadings($html, $chapterNumber);
        $html = $this->processFigures($html);
        $html = $this->processTables($html);
        $html = $this->enhanceCitations($html);

        // Add semantic structure
        $html = $this->addSemanticStructure($html);

        return $html;
    }

    /**
     * Process citations in the markdown.
     */
    private function processCitations(string $markdown): string
    {
        // Match citations in format [Author, Year] or (Author, Year)
        $pattern = '/\[([A-Z][a-zA-Z]+(?:\s+(?:et al\.|&|and)\s+[A-Z][a-zA-Z]+)?,\s*\d{4}[a-z]?)\]/';

        $markdown = preg_replace_callback($pattern, function ($matches) {
            $citation = $matches[1];
            if (! in_array($citation, $this->citations)) {
                $this->citations[] = $citation;
            }
            $index = array_search($citation, $this->citations) + 1;

            return '<cite data-citation="'.htmlspecialchars($citation).'" class="citation">['.$index.']</cite>';
        }, $markdown);

        return $markdown;
    }

    /**
     * Process headings to ensure proper hierarchy.
     */
    private function processHeadings(string $html, int $chapterNumber): string
    {
        // Match all headings
        $pattern = '/<h([1-6])>(.*?)<\/h[1-6]>/';

        $html = preg_replace_callback($pattern, function ($matches) use ($chapterNumber) {
            $level = (int) $matches[1];
            $text = $matches[2];

            // Adjust heading levels (h1 becomes chapter title, content starts at h2)
            $adjustedLevel = min($level + 1, 6);

            // Generate ID for heading
            $id = Str::slug($text);
            $uniqueId = "ch{$chapterNumber}-{$id}";

            // Store heading for potential TOC generation
            $this->headings[] = [
                'level' => $adjustedLevel,
                'text' => $text,
                'id' => $uniqueId,
            ];

            return "<h{$adjustedLevel} id=\"{$uniqueId}\" class=\"academic-heading level-{$adjustedLevel}\">{$text}</h{$adjustedLevel}>";
        }, $html);

        return $html;
    }

    /**
     * Process figures and add proper numbering.
     */
    private function processFigures(string $html): string
    {
        // Match images (potential figures)
        $pattern = '/<img([^>]*?)alt="([^"]*)"([^>]*?)>/';

        $html = preg_replace_callback($pattern, function ($matches) {
            $this->figureCounter++;
            $alt = $matches[2];
            $attributes = $matches[1].$matches[3];

            return '<figure class="academic-figure">'.
                   '<img'.$attributes.'alt="'.htmlspecialchars($alt).'">'.
                   '<figcaption>Figure '.$this->figureCounter.': '.htmlspecialchars($alt).'</figcaption>'.
                   '</figure>';
        }, $html);

        return $html;
    }

    /**
     * Process tables and add proper numbering.
     */
    private function processTables(string $html): string
    {
        // Match tables
        $pattern = '/<table([^>]*)>/';

        $html = preg_replace_callback($pattern, function ($matches) {
            $this->tableCounter++;
            $attributes = $matches[1];

            return '<div class="academic-table-wrapper">'.
                   '<div class="table-number">Table '.$this->tableCounter.'</div>'.
                   '<table'.$attributes.' class="academic-table">';
        }, $html);

        // Close the wrapper div
        $html = str_replace('</table>', '</table></div>', $html);

        return $html;
    }

    /**
     * Enhance citations with academic formatting.
     */
    private function enhanceCitations(string $html): string
    {
        // Add hover tooltips to citations
        $html = preg_replace_callback(
            '/<cite([^>]*)>(.*?)<\/cite>/',
            function ($matches) {
                $attributes = $matches[1];
                $content = $matches[2];

                return '<cite'.$attributes.' class="academic-citation" title="Click to view reference">'.$content.'</cite>';
            },
            $html
        );

        return $html;
    }

    /**
     * Add semantic HTML5 structure.
     */
    private function addSemanticStructure(string $html): string
    {
        // Wrap content in semantic sections
        $html = '<article class="academic-content">'.$html.'</article>';

        // Add bibliography section if citations exist
        if (! empty($this->citations)) {
            $html .= $this->generateBibliography();
        }

        return $html;
    }

    /**
     * Generate bibliography section.
     */
    private function generateBibliography(): string
    {
        $bibliography = '<section class="bibliography">';
        $bibliography .= '<h2 id="references" class="academic-heading level-2">References</h2>';
        $bibliography .= '<ol class="reference-list">';

        foreach ($this->citations as $index => $citation) {
            $bibliography .= '<li id="ref-'.($index + 1).'" class="reference-item">';
            $bibliography .= htmlspecialchars($citation);
            $bibliography .= '</li>';
        }

        $bibliography .= '</ol>';
        $bibliography .= '</section>';

        return $bibliography;
    }

    /**
     * Generate table of contents for the chapter.
     */
    public function generateTableOfContents(): string
    {
        if (empty($this->headings)) {
            return '';
        }

        $toc = '<nav class="chapter-toc">';
        $toc .= '<h2 class="toc-title">Contents</h2>';
        $toc .= '<ul class="toc-list">';

        foreach ($this->headings as $heading) {
            $indent = str_repeat('  ', $heading['level'] - 2);
            $toc .= $indent.'<li class="toc-item level-'.$heading['level'].'">';
            $toc .= '<a href="#'.$heading['id'].'">'.htmlspecialchars($heading['text']).'</a>';
            $toc .= '</li>';
        }

        $toc .= '</ul>';
        $toc .= '</nav>';

        return $toc;
    }

    /**
     * Get citation count for the chapter.
     */
    public function getCitationCount(): int
    {
        return count($this->citations);
    }

    /**
     * Get figure count for the chapter.
     */
    public function getFigureCount(): int
    {
        return $this->figureCounter;
    }

    /**
     * Get table count for the chapter.
     */
    public function getTableCount(): int
    {
        return $this->tableCounter;
    }
}
