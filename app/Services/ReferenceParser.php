<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ReferenceParser
{
    /**
     * Parse references section into individual entries
     */
    public function parseReferences(string $content): array
    {
        // Remove HTML tags and get clean text
        $cleanContent = strip_tags($content);

        // Find the references section
        $referencesSection = $this->extractReferencesSection($cleanContent);

        if (empty($referencesSection)) {
            return [];
        }

        // Split into individual reference entries
        $entries = $this->splitIntoEntries($referencesSection);

        // Parse each entry
        $parsedReferences = [];
        foreach ($entries as $index => $entry) {
            $parsed = $this->parseEntry($entry, $index + 1);
            if ($parsed) {
                $parsedReferences[] = $parsed;
            }
        }

        Log::info('Reference parsing completed', [
            'total_found' => count($parsedReferences),
            'raw_entries' => count($entries),
        ]);

        return $parsedReferences;
    }

    /**
     * Extract the references/bibliography section from content
     */
    private function extractReferencesSection(string $content): string
    {
        // Decode HTML entities first
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Look for common reference section headers with more flexible patterns
        $patterns = [
            // Standard format with newlines
            '/(?:References|Bibliography|Works Cited|Literature Cited)\s*\n(.*?)(?:\n\n|\Z)/is',
            // References without newline separation
            '/(?:References|Bibliography|Works Cited|Literature Cited)\s*(.*?)(?:\n\n|\Z)/is',
            // References at the end of content (common case)
            '/(References|Bibliography|Works Cited|Literature Cited)\s*(.{20,}?)$/is',
            // Fallback: detect reference-like content at the end
            '/\.\s*((?:[A-Z][a-z]+(?:,\s*[A-Z]\.)*(?:\s*&\s*[A-Z][a-z]+(?:,\s*[A-Z]\.)*)*\s*\(\d{4}\).*?\.[\s\n]*)+)$/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $referencesText = isset($matches[2]) ? $matches[2] : $matches[1];
                $trimmed = trim($referencesText);

                // Validate that we found actual references (not just a single word)
                if (strlen($trimmed) > 50 &&
                    (strpos($trimmed, '(') !== false || strpos($trimmed, ',') !== false)) {
                    return $trimmed;
                }
            }
        }

        return '';
    }

    /**
     * Split references section into individual entries
     */
    private function splitIntoEntries(string $referencesSection): array
    {
        $entries = [];

        // Method 1: Split by numbered entries (1. 2. 3. etc.)
        if (preg_match_all('/(?:^|\n)(\d+\.\s+.*?)(?=\n\d+\.|$)/s', $referencesSection, $matches)) {
            $entries = array_map('trim', $matches[1]);
        }
        // Method 2: Split by bracketed numbers [1] [2] etc.
        elseif (preg_match_all('/(?:^|\n)(\[\d+\]\s+.*?)(?=\n\[\d+\]|$)/s', $referencesSection, $matches)) {
            $entries = array_map('trim', $matches[1]);
        }
        // Method 3: Split by author-year patterns (Common in academic writing)
        elseif (preg_match_all('/([A-Z][a-z]+(?:,\s*[A-Z]\.)*(?:\s*&\s*[A-Z][a-z]+(?:,\s*[A-Z]\.)*)*\s*\(\d{3,4}\).*?)(?=[A-Z][a-z]+(?:,\s*[A-Z]\.)*(?:\s*&\s*[A-Z][a-z]+(?:,\s*[A-Z]\.)*)*\s*\(\d{3,4}\)|$)/s', $referencesSection, $matches)) {
            $entries = array_map('trim', $matches[1]);
        }
        // Method 4: Split by paragraphs (each reference on new line)
        else {
            $entries = array_filter(array_map('trim', explode("\n", $referencesSection)));
        }

        return $entries;
    }

    /**
     * Parse individual reference entry
     */
    private function parseEntry(string $entry, int $index): ?array
    {
        // Remove leading numbers or brackets
        $cleanEntry = preg_replace('/^(?:\d+\.\s*|\[\d+\]\s*)/', '', $entry);
        $cleanEntry = trim($cleanEntry);

        if (strlen($cleanEntry) < 20) {
            return null; // Too short to be a valid reference
        }

        // Initialize result
        $result = [
            'id' => $index,
            'raw_text' => $cleanEntry,
            'authors' => [],
            'year' => null,
            'title' => null,
            'journal' => null,
            'volume' => null,
            'issue' => null,
            'pages' => null,
            'doi' => null,
            'url' => null,
            'publisher' => null,
            'format' => 'unknown',
        ];

        // Extract DOI
        if (preg_match('/(?:doi:\s*|https?:\/\/doi\.org\/)(10\.\d{4,}[^\s]*)/i', $cleanEntry, $matches)) {
            $result['doi'] = $matches[1];
        }

        // Extract URL
        if (preg_match('/https?:\/\/[^\s]+/i', $cleanEntry, $matches)) {
            $result['url'] = $matches[0];
        }

        // Extract year (4-digit number in parentheses or standalone, handle incomplete years)
        if (preg_match('/\((\d{4})\)|(\d{4})/', $cleanEntry, $matches)) {
            $result['year'] = (int) ($matches[1] ?? $matches[2]);
        } elseif (preg_match('/\((\d{3})\)|(\d{3})/', $cleanEntry, $matches)) {
            // Handle incomplete years like "202" - assume recent years
            $incompleteYear = (int) ($matches[1] ?? $matches[2]);
            if ($incompleteYear >= 200 && $incompleteYear <= 202) {
                // Assume 2020-2029 for years like 202, 201, 200
                $result['year'] = 2000 + $incompleteYear;
                if ($result['year'] > date('Y')) {
                    $result['year'] = 2020 + ($incompleteYear - 200); // 202 -> 2022, 201 -> 2021, etc.
                }
            }
        }

        // Try different parsing strategies based on format
        if ($this->parseAPA($cleanEntry, $result)) {
            $result['format'] = 'apa';
        } elseif ($this->parseIEEE($cleanEntry, $result)) {
            $result['format'] = 'ieee';
        } elseif ($this->parseGeneric($cleanEntry, $result)) {
            $result['format'] = 'generic';
        }

        return $result;
    }

    /**
     * Parse APA style reference
     */
    private function parseAPA(string $entry, array &$result): bool
    {
        // APA: Author, A. A. (Year). Title. Journal Name, Volume(Issue), pages.
        $pattern = '/^([^.]+?)\s*\((\d{4})\)\.\s*([^.]+?)\.\s*([^,]+?)(?:,\s*(\d+)(?:\((\d+)\))?(?:,\s*([\d\-]+))?)?/';

        if (preg_match($pattern, $entry, $matches)) {
            $result['authors'] = $this->parseAuthors($matches[1]);
            $result['year'] = (int) $matches[2];
            $result['title'] = trim($matches[3]);
            $result['journal'] = trim($matches[4]);
            $result['volume'] = $matches[5] ?? null;
            $result['issue'] = $matches[6] ?? null;
            $result['pages'] = $matches[7] ?? null;

            return true;
        }

        return false;
    }

    /**
     * Parse IEEE style reference
     */
    private function parseIEEE(string $entry, array &$result): bool
    {
        // IEEE: A. Author, "Title," Journal Name, vol. X, no. Y, pp. Z-Z, Year.
        $pattern = '/^([^"]+?),?\s*"([^"]+?),"?\s*([^,]+?)(?:,\s*vol\.\s*(\d+))?(?:,\s*no\.\s*(\d+))?(?:,\s*pp\.\s*([\d\-]+))?(?:,\s*(\d{4}))?/i';

        if (preg_match($pattern, $entry, $matches)) {
            $result['authors'] = $this->parseAuthors($matches[1]);
            $result['title'] = trim($matches[2]);
            $result['journal'] = trim($matches[3]);
            $result['volume'] = $matches[4] ?? null;
            $result['issue'] = $matches[5] ?? null;
            $result['pages'] = $matches[6] ?? null;
            $result['year'] = isset($matches[7]) ? (int) $matches[7] : null;

            return true;
        }

        return false;
    }

    /**
     * Generic parsing fallback
     */
    private function parseGeneric(string $entry, array &$result): bool
    {
        // Try to extract title (usually in quotes or between periods)
        if (preg_match('/"([^"]+)"/', $entry, $matches)) {
            $result['title'] = trim($matches[1]);
        } elseif (preg_match('/\.\s*([^.]{20,}?)\.\s*/', $entry, $matches)) {
            $result['title'] = trim($matches[1]);
        }

        // Extract authors (usually at the beginning)
        if (preg_match('/^([^.]{5,50}?)(?:\.|,|\s*\(\d{4}\))/', $entry, $matches)) {
            $result['authors'] = $this->parseAuthors($matches[1]);
        }

        return ! empty($result['title']) || ! empty($result['authors']);
    }

    /**
     * Parse author names from author string
     */
    private function parseAuthors(string $authorString): array
    {
        $authors = [];
        $authorString = trim($authorString);

        // Split by common separators
        $parts = preg_split('/(?:,\s*(?:and\s+|&\s+)?|;\s*|\s+and\s+|\s+&\s+)/', $authorString);

        foreach ($parts as $part) {
            $part = trim($part);
            if (! empty($part) && strlen($part) > 2) {
                // Clean up author name
                $part = preg_replace('/\.$/', '', $part); // Remove trailing period
                $authors[] = $part;
            }
        }

        return $authors;
    }
}
