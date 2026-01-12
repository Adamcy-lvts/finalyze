<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Collection;

class ChapterReferenceService
{
    /**
     * Get all citations for a chapter with their formatted references.
     *
     * @return Collection<int, array{inline_text: string, reference: string, citation_key: string|null}>
     */
    public function getChapterCitations(Chapter $chapter, string $style = 'APA'): Collection
    {
        $citations = $chapter->documentCitations()
            ->with('citation')
            ->orderBy('position')
            ->get();

        return $citations->map(function ($docCitation) use ($style) {
            $formattedRef = $docCitation->getFormattedText(strtolower($style));

            return [
                'inline_text' => $docCitation->inline_text,
                'reference' => $formattedRef,
                'citation_key' => $docCitation->citation?->citation_key,
                'authors' => $docCitation->citation?->authors,
                'year' => $docCitation->citation?->year,
                'title' => $docCitation->citation?->title,
            ];
        });
    }

    /**
     * Format references section for a single chapter export.
     * Returns HTML-formatted references list.
     */
    public function formatChapterReferencesSection(Chapter $chapter, string $style = 'APA'): string
    {
        // First try to get citations from database
        $citations = $this->getChapterCitations($chapter, $style);

        if ($citations->isEmpty()) {
            // If no database citations, try extracting from chapter HTML content
            $referencesFromHtml = $this->extractReferencesFromHtml($chapter->content);

            if (empty($referencesFromHtml)) {
                return '';
            }

            // Convert array of references to collection format
            $citations = collect($referencesFromHtml)->map(function ($reference) {
                return ['reference' => $reference];
            });
        }

        // Get unique references (dedupe by reference text)
        $uniqueRefs = $citations
            ->unique('reference')
            ->filter(fn ($c) => ! empty($c['reference']) && $c['reference'] !== '[CITATION NEEDED - REQUIRES VERIFICATION]')
            ->values();

        if ($uniqueRefs->isEmpty()) {
            return '';
        }

        // Sort alphabetically by the reference text (which starts with author name in APA)
        $sortedRefs = $uniqueRefs->sortBy('reference');

        $html = '<div class="references-section" style="margin-top: 2em; page-break-before: always; font-size: 14px;">';
        $html .= '<h2 style="text-align: center; font-weight: bold; margin-bottom: 1em; font-size: 16px;">REFERENCES</h2>';

        foreach ($sortedRefs as $ref) {
            $html .= '<p style="font-size: 14px; text-indent: -0.5in; margin-left: 0.5in; margin-bottom: 0.5em; text-align: justify;">'.
                htmlspecialchars($ref['reference'], ENT_QUOTES | ENT_HTML5, 'UTF-8').
                '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Collect all references from all chapters of a project.
     * Returns unique, alphabetically sorted references.
     *
     * @return Collection<int, array{reference: string, chapters: array<int>}>
     */
    public function collectProjectReferences(Project $project, string $style = 'APA'): Collection
    {
        $chapters = $project->chapters()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderBy('chapter_number')
            ->get();

        $allReferences = collect();

        foreach ($chapters as $chapter) {
            $chapterCitations = $this->getChapterCitations($chapter, $style);

            foreach ($chapterCitations as $citation) {
                if (empty($citation['reference']) || $citation['reference'] === '[CITATION NEEDED - REQUIRES VERIFICATION]') {
                    continue;
                }

                $refKey = $citation['reference'];

                if ($allReferences->has($refKey)) {
                    // Add chapter to existing reference
                    $existing = $allReferences->get($refKey);
                    if (! in_array($chapter->chapter_number, $existing['chapters'])) {
                        $existing['chapters'][] = $chapter->chapter_number;
                        $allReferences->put($refKey, $existing);
                    }
                } else {
                    $allReferences->put($refKey, [
                        'reference' => $citation['reference'],
                        'chapters' => [$chapter->chapter_number],
                        'citation_key' => $citation['citation_key'],
                        'authors' => $citation['authors'],
                        'year' => $citation['year'],
                        'title' => $citation['title'],
                    ]);
                }
            }
        }

        // Sort alphabetically by reference text
        return $allReferences->values()->sortBy('reference')->values();
    }

    /**
     * Extract references from HTML content (for AI-generated chapters).
     * Parses the References section from HTML and returns array of individual references.
     */
    public function extractReferencesFromHtml(string $html): array
    {
        $references = [];

        // Match References section: <h1>REFERENCES</h1> or <h2>REFERENCES</h2>
        // Followed by content until next heading or end
        if (preg_match('/<h[12][^>]*>\s*REFERENCES?\s*<\/h[12]>(.*?)(?=<h[12]|$)/is', $html, $matches)) {
            $referencesContent = $matches[1];

            // Extract individual references (each in a <p> tag)
            if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $referencesContent, $paragraphs)) {
                foreach ($paragraphs[1] as $paragraph) {
                    // Clean up HTML tags and decode entities
                    $reference = strip_tags($paragraph);
                    $reference = html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $reference = trim($reference);

                    // Skip empty references or placeholders
                    if (empty($reference) ||
                        stripos($reference, '[citation needed]') !== false ||
                        stripos($reference, 'citation needed') !== false) {
                        continue;
                    }

                    $references[] = $reference;
                }
            }

            // Also try to match references in div.references-section
            if (preg_match('/<div[^>]*class="references-section"[^>]*>(.*?)<\/div>/is', $html, $divMatches)) {
                if (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $divMatches[1], $divParagraphs)) {
                    foreach ($divParagraphs[1] as $paragraph) {
                        $reference = strip_tags($paragraph);
                        $reference = html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $reference = trim($reference);

                        if (empty($reference) ||
                            stripos($reference, '[citation needed]') !== false ||
                            stripos($reference, 'citation needed') !== false) {
                            continue;
                        }

                        $references[] = $reference;
                    }
                }
            }
        }

        return array_unique($references);
    }

    /**
     * Collect references from all chapters by parsing their HTML content.
     * This is used for full project exports where references are in the chapter HTML.
     */
    public function collectReferencesFromChapterHtml(Project $project): Collection
    {
        $chapters = $project->chapters()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderBy('chapter_number')
            ->get();

        $allReferences = collect();

        foreach ($chapters as $chapter) {
            $chapterRefs = $this->extractReferencesFromHtml($chapter->content);

            \Log::info('Extracted references from chapter HTML', [
                'chapter_id' => $chapter->id,
                'chapter_number' => $chapter->chapter_number,
                'references_count' => count($chapterRefs),
                'references' => $chapterRefs,
            ]);

            foreach ($chapterRefs as $reference) {
                // Use the reference text as the key for deduplication
                $refKey = $reference;

                if (! $allReferences->has($refKey)) {
                    $allReferences->put($refKey, [
                        'reference' => $reference,
                        'chapters' => [$chapter->chapter_number],
                    ]);
                } else {
                    // Add chapter number to existing reference
                    $existing = $allReferences->get($refKey);
                    if (! in_array($chapter->chapter_number, $existing['chapters'])) {
                        $existing['chapters'][] = $chapter->chapter_number;
                        $allReferences->put($refKey, $existing);
                    }
                }
            }
        }

        \Log::info('Total references collected from HTML', [
            'project_id' => $project->id,
            'total_references' => $allReferences->count(),
        ]);

        // Sort alphabetically by reference text
        return $allReferences->values()->sortBy('reference')->values();
    }

    /**
     * Format full project references section for export.
     * Returns HTML-formatted references list with all unique citations.
     */
    public function formatProjectReferencesSection(Project $project, string $style = 'APA'): string
    {
        // First try to collect from database citations
        $references = $this->collectProjectReferences($project, $style);

        \Log::info('Formatting project references section', [
            'project_id' => $project->id,
            'database_citations_count' => $references->count(),
        ]);

        // If no database citations, parse from chapter HTML content
        if ($references->isEmpty()) {
            \Log::info('No database citations found, parsing from chapter HTML');
            $references = $this->collectReferencesFromChapterHtml($project);
        }

        // If still empty, fall back to project-level references
        if ($references->isEmpty()) {
            \Log::info('No HTML references found, using project-level references fallback');

            return $this->formatProjectLevelReferences($project);
        }

        \Log::info('Building consolidated references HTML', [
            'total_references' => $references->count(),
        ]);

        $html = '<div class="references-section" style="margin-top: 2em; font-size: 14px;">';
        $html .= '<h1 style="text-align: center; font-weight: bold; margin-bottom: 1em; font-size: 16px;">REFERENCES</h1>';

        foreach ($references as $ref) {
            $html .= '<p style="font-size: 14px; text-indent: -0.5in; margin-left: 0.5in; margin-bottom: 0.5em; text-align: justify;">'.
                htmlspecialchars($ref['reference'], ENT_QUOTES | ENT_HTML5, 'UTF-8').
                '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Format project-level references (from project.references JSON field) as fallback.
     */
    public function formatProjectLevelReferences(Project $project): string
    {
        if (empty($project->references)) {
            return '';
        }

        $references = json_decode($project->references, true) ?? [];

        if (empty($references)) {
            return '';
        }

        // Sort alphabetically by citation text
        usort($references, function ($a, $b) {
            $textA = $a['citation'] ?? $a['text'] ?? $a['title'] ?? '';
            $textB = $b['citation'] ?? $b['text'] ?? $b['title'] ?? '';

            return strcasecmp($textA, $textB);
        });

        $html = '<div class="references-section" style="margin-top: 2em; font-size: 14px;">';
        $html .= '<h1 style="text-align: center; font-weight: bold; margin-bottom: 1em; font-size: 16px;">REFERENCES</h1>';

        foreach ($references as $reference) {
            $refText = $reference['citation'] ??
                $reference['text'] ??
                $reference['title'] ??
                'Unknown reference';

            $html .= '<p style="font-size: 14px; text-indent: -0.5in; margin-left: 0.5in; margin-bottom: 0.5em; text-align: justify;">'.
                htmlspecialchars($refText, ENT_QUOTES | ENT_HTML5, 'UTF-8').
                '</p>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Extract inline citations from chapter content using regex.
     * Matches patterns like (AuthorName, Year) or (Author et al., Year).
     *
     * @return array<int, array{text: string, position: int}>
     */
    public function extractInlineCitationsFromContent(string $content): array
    {
        $citations = [];

        // Pattern for APA-style inline citations: (Author, Year) or (Author et al., Year)
        $pattern = '/\(([A-Z][a-zA-Z\'\-]+(?:\s+(?:et\s+al\.|&|and)\s*[A-Z][a-zA-Z\'\-]+)*),?\s*(\d{4}[a-z]?)\)/';

        if (preg_match_all($pattern, strip_tags($content), $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $citations[] = [
                    'text' => $match[0],
                    'position' => $match[1],
                    'author' => $matches[1][$index][0] ?? '',
                    'year' => $matches[2][$index][0] ?? '',
                ];
            }
        }

        return $citations;
    }

    /**
     * Count references for a chapter.
     */
    public function countChapterReferences(Chapter $chapter): int
    {
        return $chapter->documentCitations()->count();
    }

    /**
     * Count total unique references for a project.
     */
    public function countProjectReferences(Project $project, string $style = 'APA'): int
    {
        return $this->collectProjectReferences($project, $style)->count();
    }
}
