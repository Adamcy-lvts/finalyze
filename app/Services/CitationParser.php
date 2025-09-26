<?php

namespace App\Services;

class CitationParser
{
    private array $patterns = [
        'apa' => [
            'inline' => '/\(([A-Za-z\s&]+),?\s*(\d{4})\)/',
            'full' => '/^([A-Za-z,\s&\.]+)\s*\((\d{4})\)\.\s*(.+?)\.\s*(.+?)(?:,\s*(\d+))?(?:\((\d+)\))?(?:,\s*([\d\-]+))?\.?$/',
        ],
        'mla' => [
            'inline' => '/\(([A-Za-z\s]+)\s+(\d+)\)/',
            'full' => '/^([A-Za-z,\s]+)\.\s*"(.+?\.?)"?\s*(.+?),?\s*(?:vol\.\s*(\d+),?\s*)?(?:no\.\s*(\d+),?\s*)?(\d{4}),?\s*(?:pp\.\s*)?([\d\-]+)?/',
        ],
        'doi' => '/10\.\d{4,}(?:\.\d+)*\/[-._;()\/:A-Za-z0-9]+/',
        'pubmed' => '/(?:PMID:?\s*)?(\d{7,8})/',
        'arxiv' => '/(?:arXiv:)?(\d{4}\.\d{4,5}(?:v\d+)?)/i',
    ];

    public function parse(string $citation): array
    {
        $result = [
            'raw' => $citation,
            'format' => null,
            'confidence' => 0,
        ];

        // Check for identifiers first
        if (preg_match($this->patterns['doi'], $citation, $matches)) {
            $result['doi'] = $matches[0];
            $result['confidence'] = 0.9;
        }

        if (preg_match($this->patterns['pubmed'], $citation, $matches)) {
            $result['pubmed_id'] = $matches[1];
            $result['confidence'] = 0.9;
        }

        if (preg_match($this->patterns['arxiv'], $citation, $matches)) {
            $result['arxiv_id'] = $matches[1];
            $result['confidence'] = 0.9;
        }

        // Try to parse as APA format
        if (preg_match($this->patterns['apa']['full'], $citation, $matches)) {
            $result['format'] = 'apa';
            $result['authors'] = $this->parseAuthors($matches[1]);
            $result['year'] = intval($matches[2]);
            $result['title'] = trim($matches[3], '. ');
            $result['journal'] = trim($matches[4] ?? '', '. ');
            $result['volume'] = $matches[5] ?? null;
            $result['issue'] = $matches[6] ?? null;
            $result['pages'] = $matches[7] ?? null;
            $result['confidence'] = max($result['confidence'], 0.7);
        }

        // Try MLA format
        elseif (preg_match($this->patterns['mla']['full'], $citation, $matches)) {
            $result['format'] = 'mla';
            $result['authors'] = $this->parseAuthors($matches[1]);
            $result['title'] = trim($matches[2], '" ');
            $result['journal'] = trim($matches[3] ?? '');
            $result['volume'] = $matches[4] ?? null;
            $result['issue'] = $matches[5] ?? null;
            $result['year'] = intval($matches[6]);
            $result['pages'] = $matches[7] ?? null;
            $result['confidence'] = max($result['confidence'], 0.7);
        }

        // Try inline APA citation patterns
        // Pattern 1: Author et al. (Year) - parentheses around year only (check this first!)
        elseif (preg_match('/^([^(]+)\s*\((\d{4})\)/', $citation, $matches)) {
            $result['format'] = 'apa_inline';
            $authorPart = trim($matches[1]);
            $result['year'] = intval($matches[2]);

            if (! empty($authorPart)) {
                // Handle "et al." specially
                if (strpos($authorPart, 'et al.') !== false) {
                    $mainAuthor = trim(str_replace('et al.', '', $authorPart), ', ');
                    $result['authors'] = [$mainAuthor.' et al.'];
                    $result['has_et_al'] = true;
                } else {
                    $result['authors'] = $this->parseAuthors($authorPart);
                }
            }

            $result['confidence'] = 0.6;
        }
        // Pattern 2: (Author et al., Year) - parentheses around everything
        elseif (preg_match('/\(([^)]+)\)/', $citation, $matches)) {
            $result['format'] = 'apa_inline';
            $inlineContent = $matches[1];
            $this->parseInlineContent($inlineContent, $result);
            $result['confidence'] = 0.6;
        }
        // Fallback: Try to extract whatever we can
        else {
            $result['format'] = 'unknown';
            $result['confidence'] = 0.3;

            // Extract year
            if (preg_match('/\b(19|20)\d{2}\b/', $citation, $matches)) {
                $result['year'] = intval($matches[0]);
            }

            // Extract potential title (text in quotes or italics)
            if (preg_match('/"([^"]+)"/', $citation, $matches)) {
                $result['title'] = $matches[1];
            } elseif (preg_match('/<i>([^<]+)<\/i>/', $citation, $matches)) {
                $result['title'] = $matches[1];
            }

            // Try to extract authors (capitalized words at the beginning)
            if (preg_match('/^([A-Z][a-z]+(?:\s+[A-Z]\.)?,?\s*(?:(?:and|&)\s*)?)+/', $citation, $matches)) {
                $result['authors'] = $this->parseAuthors($matches[0]);
            }
        }

        return $result;
    }

    private function parseAuthors(string $authorString): array
    {
        $authors = [];

        // Split by 'and', '&', or comma
        $parts = preg_split('/\s*(?:,|and|&)\s*/', $authorString);

        foreach ($parts as $part) {
            $part = trim($part, ' .,');
            if (! empty($part)) {
                $authors[] = $part;
            }
        }

        return $authors;
    }

    private function parseInlineContent(string $inlineContent, array &$result): void
    {
        // Extract year
        if (preg_match('/\b(19|20)\d{2}\b/', $inlineContent, $yearMatches)) {
            $result['year'] = intval($yearMatches[0]);
        }

        // Extract authors (everything before the year, removing year and comma)
        $authorPart = preg_replace('/,?\s*(19|20)\d{2}/', '', $inlineContent);
        $authorPart = trim($authorPart, ', ');

        if (! empty($authorPart)) {
            // Handle "et al." specially
            if (strpos($authorPart, 'et al.') !== false) {
                $mainAuthor = trim(str_replace('et al.', '', $authorPart), ', ');
                $result['authors'] = [$mainAuthor.' et al.'];
                $result['has_et_al'] = true;
            } else {
                $result['authors'] = $this->parseAuthors($authorPart);
            }
        }
    }
}
