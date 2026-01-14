<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Citation;
use App\Models\CollectedPaper;
use App\Models\DocumentCitation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CitationWhitelistService
{
    /**
     * Prepare and persist citation whitelist for a chapter.
     *
     * @return array<int, array<string, mixed>>
     */
    public function prepareWhitelistForChapter(Chapter $chapter, Collection $injectedPapers): array
    {
        if ($injectedPapers->isEmpty()) {
            $chapter->update([
                'injected_paper_ids' => [],
                'citation_whitelist' => [],
            ]);

            return [];
        }

        $project = $chapter->project()->first();
        $userId = $project?->user_id;

        if (! $project || ! $userId) {
            Log::warning('Citation whitelist skipped: missing project/user', [
                'chapter_id' => $chapter->id,
            ]);

            return [];
        }

        $whitelist = [];
        $injectedIds = [];

        foreach ($injectedPapers as $paper) {
            $citation = $this->syncCollectedPaperToCitation($paper);
            $inlineText = $this->formatInlineCitation($paper);
            $whitelistKey = $this->buildWhitelistKey($paper);

            $whitelist[] = [
                'key' => $whitelistKey,
                'inline_text' => $inlineText,
                'author_last' => $this->extractFirstAuthorLastName((string) $paper->authors),
                'year' => is_numeric($paper->year) ? (int) $paper->year : null,
                'title' => $paper->title,
                'doi' => $paper->doi,
                'citation_id' => $citation->id,
                'collected_paper_id' => $paper->id,
            ];

            $injectedIds[] = $paper->id;

            DocumentCitation::updateOrCreate(
                [
                    'chapter_id' => $chapter->id,
                    'collected_paper_id' => $paper->id,
                    'source' => 'suggested',
                ],
                [
                    'document_id' => $project->id,
                    'user_id' => $userId,
                    'citation_id' => $citation->id,
                    'inline_text' => $inlineText ?? '[Citation needed]',
                    'format_style' => 'apa',
                    'position' => null,
                    'user_approved' => false,
                    'needs_review' => false,
                    'is_whitelisted' => true,
                    'whitelist_key' => $whitelistKey,
                    'raw_citation' => null,
                    'placeholder_data' => [
                        'type' => 'whitelist_placeholder',
                        'inline_text' => $inlineText,
                    ],
                ]
            );
        }

        $chapter->update([
            'injected_paper_ids' => $injectedIds,
            'citation_whitelist' => $whitelist,
        ]);

        return $whitelist;
    }

    public function syncCollectedPaperToCitation(CollectedPaper $paper): Citation
    {
        if ($paper->citation_id) {
            $existing = Citation::find($paper->citation_id);
            if ($existing) {
                return $existing;
            }
        }

        $doi = $paper->doi ? trim((string) $paper->doi) : null;
        $citationKey = $doi ? 'doi:'.$doi : $this->buildCitationKey($paper);

        $existing = null;
        if ($doi) {
            $existing = Citation::where('doi', $doi)->first();
        }
        if (! $existing) {
            $existing = Citation::where('citation_key', $citationKey)->first();
        }

        $authors = $this->normalizeAuthors($paper->authors);
        $year = is_numeric($paper->year) ? (int) $paper->year : 0;

        $data = [
            'citation_key' => $citationKey,
            'doi' => $doi,
            'authors' => $authors,
            'title' => $paper->title ?? 'Unknown title',
            'journal' => $paper->venue,
            'conference' => null,
            'year' => $year,
            'volume' => null,
            'issue' => null,
            'pages' => null,
            'publisher' => null,
            'verification_status' => 'verified',
            'confidence_score' => 1.00,
            'verification_sources' => ['collected_papers'],
            'last_verified_at' => now(),
            'abstract' => $paper->abstract,
            'keywords' => [],
            'url' => $paper->url,
        ];

        if ($existing) {
            $existing->update($data);
            $citation = $existing;
        } else {
            $citation = Citation::create($data);
        }

        if ($paper->citation_id !== $citation->id) {
            $paper->update(['citation_id' => $citation->id]);
        }

        return $citation;
    }

    /**
     * Validate inline citations in chapter content against whitelist.
     */
    public function validateChapterCitations(Chapter $chapter, string $content): array
    {
        $project = $chapter->project()->first();
        if (! $project) {
            return [
                'total' => 0,
                'matched' => 0,
                'violations' => 0,
            ];
        }

        $inlineCitations = $this->extractInlineCitations($content);
        $whitelist = $chapter->citation_whitelist ?? [];
        $whitelistMap = $this->buildWhitelistMap($whitelist);

        $matched = 0;
        $violations = 0;

        foreach ($inlineCitations as $citation) {
            $normalized = $this->normalizeInlineText($citation['text']);
            $matches = $whitelistMap[$normalized] ?? [];

            if (count($matches) === 1) {
                $entry = $matches[0];
                $this->upsertDocumentCitation($chapter, $project->user_id, $citation, $entry, true, false);
                $matched++;
                continue;
            }

            if (count($matches) > 1) {
                $this->upsertDocumentCitation($chapter, $project->user_id, $citation, null, true, true, [
                    'reason' => 'ambiguous_inline_citation',
                    'matches' => $matches,
                ]);
                $violations++;
                continue;
            }

            $this->upsertDocumentCitation($chapter, $project->user_id, $citation, null, false, true, [
                'reason' => 'not_in_whitelist',
            ]);
            $violations++;
        }

        $chapter->update([
            'citations_validated_at' => now(),
            'citation_violations_count' => $violations,
        ]);

        return [
            'total' => count($inlineCitations),
            'matched' => $matched,
            'violations' => $violations,
        ];
    }

    /**
     * Extract inline citations from HTML content.
     *
     * @return array<int, array{text: string, position: int, author: string|null, year: int|null}>
     */
    public function extractInlineCitations(string $content): array
    {
        $citations = [];
        $text = strip_tags($content);
        $pattern = '/\(([A-Z][A-Za-z\\-\'\\s]+?(?:et\\s+al\\.)?),?\\s*(\\d{4}[a-z]?)\)/';

        if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $citations[] = [
                    'text' => $match[0],
                    'position' => $match[1],
                    'author' => $matches[1][$index][0] ?? null,
                    'year' => isset($matches[2][$index][0]) ? (int) $matches[2][$index][0] : null,
                ];
            }
        }

        return $citations;
    }

    private function upsertDocumentCitation(
        Chapter $chapter,
        int $userId,
        array $citation,
        ?array $entry,
        bool $isWhitelisted,
        bool $needsReview,
        ?array $placeholderData = null
    ): void {
        $attributes = [
            'chapter_id' => $chapter->id,
            'position' => $citation['position'],
            'inline_text' => $citation['text'],
            'source' => 'ai_generated',
        ];

        DocumentCitation::updateOrCreate($attributes, [
            'document_id' => $chapter->project_id,
            'user_id' => $userId,
            'citation_id' => $entry['citation_id'] ?? null,
            'collected_paper_id' => $entry['collected_paper_id'] ?? null,
            'format_style' => 'apa',
            'user_approved' => false,
            'needs_review' => $needsReview,
            'is_whitelisted' => $isWhitelisted,
            'whitelist_key' => $entry['key'] ?? null,
            'raw_citation' => $needsReview ? $citation['text'] : null,
            'placeholder_data' => $placeholderData,
        ]);
    }

    private function buildWhitelistMap(array $whitelist): array
    {
        $map = [];
        foreach ($whitelist as $entry) {
            $inline = $entry['inline_text'] ?? null;
            if (! $inline) {
                continue;
            }
            $normalized = $this->normalizeInlineText($inline);
            $map[$normalized][] = $entry;
        }

        return $map;
    }

    private function buildWhitelistKey(CollectedPaper $paper): string
    {
        if (! empty($paper->doi)) {
            return 'doi:'.trim((string) $paper->doi);
        }

        return $this->buildCitationKey($paper);
    }

    private function buildCitationKey(CollectedPaper $paper): string
    {
        $authors = is_array($paper->authors) ? implode(',', $paper->authors) : (string) $paper->authors;
        $title = (string) $paper->title;
        $year = is_numeric($paper->year) ? (int) $paper->year : 0;

        return 'hash:'.md5(strtolower(trim($title.'|'.$authors.'|'.$year)));
    }

    private function normalizeAuthors(mixed $authors): array
    {
        if (is_array($authors)) {
            return array_values(array_filter(array_map('trim', $authors)));
        }

        $authors = trim((string) $authors);
        if ($authors === '') {
            return ['Unknown'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $authors))));
    }

    private function formatInlineCitation(CollectedPaper $paper): ?string
    {
        $lastName = $this->extractFirstAuthorLastName((string) $paper->authors);
        $year = is_numeric($paper->year) ? (int) $paper->year : null;

        if (! $lastName || ! $year) {
            return null;
        }

        return "({$lastName}, {$year})";
    }

    private function extractFirstAuthorLastName(string $authors): ?string
    {
        $authors = trim($authors);
        if ($authors === '' || strcasecmp($authors, 'Unknown Authors') === 0) {
            return null;
        }

        $first = trim(explode(',', $authors)[0] ?? '');
        if ($first === '') {
            return null;
        }

        $first = preg_replace('/\\s+/', ' ', $first);
        $parts = array_values(array_filter(explode(' ', $first), fn ($p) => trim($p) !== ''));
        if (empty($parts)) {
            return null;
        }

        $lastName = preg_replace("/[^A-Za-z\\-']+/", '', end($parts));
        $lastName = trim((string) $lastName);

        return $lastName !== '' ? $lastName : null;
    }

    private function normalizeInlineText(string $inline): string
    {
        $inline = preg_replace('/\\s+/', ' ', trim($inline));

        return strtolower($inline);
    }
}
