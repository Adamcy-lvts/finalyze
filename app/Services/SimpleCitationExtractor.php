<?php

namespace App\Services;

class SimpleCitationExtractor
{
    /**
     * Extract citations from chapter content
     */
    public function extractCitations(string $content): array
    {
        $citations = [];

        // Pattern for APA style in-text citations like (Author, Year) or (Author et al., Year)
        $patterns = [
            // Single author: (Smith, 2023)
            '/\(([A-Z][a-z]+),\s*(\d{4})\)/',
            // Multiple authors: (Smith & Jones, 2023)
            '/\(([A-Z][a-z]+\s*&\s*[A-Z][a-z]+),\s*(\d{4})\)/',
            // Et al: (Smith et al., 2023)
            '/\(([A-Z][a-z]+\s+et\s+al\.),\s*(\d{4})\)/',
            // With page numbers: (Smith, 2023, p. 45)
            '/\(([A-Z][a-z]+),\s*(\d{4}),\s*p\.\s*\d+\)/',
            // Organizations: (World Health Organization, 2023)
            '/\(([A-Z][a-zA-Z\s]+),\s*(\d{4})\)/',
        ];

        $citationId = 1;
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fullMatch = $match[0];
                    $author = trim($match[1]);
                    $year = $match[2];

                    // Skip if already found (avoid duplicates)
                    $exists = false;
                    foreach ($citations as $existing) {
                        if ($existing['author'] === $author && $existing['year'] === $year) {
                            $exists = true;
                            break;
                        }
                    }

                    if (! $exists) {
                        $citations[] = [
                            'id' => $citationId++,
                            'text' => $fullMatch,
                            'author' => $author,
                            'year' => $year,
                            'status' => 'pending', // pending, verified, failed, unverified
                            'details' => null,
                        ];
                    }
                }
            }
        }

        // Also look for [UNVERIFIED] markers that AI might have added
        if (preg_match_all('/\[UNVERIFIED\]/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $citations[] = [
                    'id' => $citationId++,
                    'text' => '[UNVERIFIED]',
                    'author' => 'Unknown',
                    'year' => 'Unknown',
                    'status' => 'unverified',
                    'details' => 'AI marked this citation as unverified due to uncertainty about the source.',
                ];
            }
        }

        return $citations;
    }

    /**
     * Extract potential reference list from content
     */
    public function extractReferences(string $content): array
    {
        $references = [];

        // Look for reference section
        $patterns = [
            '/References\s*\n(.*?)(?=\n\n|\n[A-Z][a-z]|\Z)/s',
            '/Bibliography\s*\n(.*?)(?=\n\n|\n[A-Z][a-z]|\Z)/s',
            '/Works Cited\s*\n(.*?)(?=\n\n|\n[A-Z][a-z]|\Z)/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $referenceSection = $matches[1];

                // Split by lines and filter non-empty ones
                $lines = array_filter(array_map('trim', explode("\n", $referenceSection)));

                $refId = 1;
                foreach ($lines as $line) {
                    if (strlen($line) > 20) { // Ignore very short lines
                        $references[] = [
                            'id' => $refId++,
                            'text' => $line,
                            'status' => 'pending',
                            'details' => null,
                        ];
                    }
                }
                break; // Only process first found reference section
            }
        }

        return $references;
    }

    /**
     * Get summary of extracted citations
     */
    public function getSummary(array $citations): array
    {
        $total = count($citations);
        $statusCounts = array_count_values(array_column($citations, 'status'));

        return [
            'total' => $total,
            'pending' => $statusCounts['pending'] ?? 0,
            'verified' => $statusCounts['verified'] ?? 0,
            'failed' => $statusCounts['failed'] ?? 0,
            'unverified' => $statusCounts['unverified'] ?? 0,
        ];
    }
}
