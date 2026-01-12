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
        $citations = $this->getChapterCitations($chapter, $style);

        if ($citations->isEmpty()) {
            return '';
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
     * Format full project references section for export.
     * Returns HTML-formatted references list with all unique citations.
     */
    public function formatProjectReferencesSection(Project $project, string $style = 'APA'): string
    {
        $references = $this->collectProjectReferences($project, $style);

        if ($references->isEmpty()) {
            // Also check project-level references as fallback
            return $this->formatProjectLevelReferences($project);
        }

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
