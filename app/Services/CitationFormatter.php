<?php

namespace App\Services;

class CitationFormatter
{
    /**
     * Format citation in specified style
     */
    public function format(array $citationData, string $style = 'apa'): string
    {
        switch (strtolower($style)) {
            case 'apa':
                return $this->formatAPA($citationData);
            case 'mla':
                return $this->formatMLA($citationData);
            case 'chicago':
                return $this->formatChicago($citationData);
            case 'harvard':
                return $this->formatHarvard($citationData);
            default:
                return $this->formatAPA($citationData); // Default to APA
        }
    }

    /**
     * Format citation in APA style
     */
    private function formatAPA(array $data): string
    {
        $parts = [];

        // Authors
        if (! empty($data['authors'])) {
            $authors = $this->formatAuthorsAPA($data['authors']);
            $parts[] = $authors;
        }

        // Year
        if (! empty($data['year'])) {
            $parts[] = "({$data['year']})";
        }

        // Title
        if (! empty($data['title'])) {
            $title = trim($data['title'], '.');
            $parts[] = "{$title}.";
        }

        // Journal
        if (! empty($data['journal'])) {
            $journal = $data['journal'];

            // Add volume and issue
            if (! empty($data['volume'])) {
                $journal .= ", {$data['volume']}";
                if (! empty($data['issue'])) {
                    $journal .= "({$data['issue']})";
                }
            }

            // Add pages
            if (! empty($data['pages'])) {
                $journal .= ", {$data['pages']}";
            }

            $parts[] = "{$journal}.";
        }

        // DOI
        if (! empty($data['doi'])) {
            $parts[] = "https://doi.org/{$data['doi']}";
        }

        return implode(' ', $parts);
    }

    /**
     * Format citation in MLA style
     */
    private function formatMLA(array $data): string
    {
        $parts = [];

        // Authors (Last, First format for first author)
        if (! empty($data['authors'])) {
            $authors = $this->formatAuthorsMLA($data['authors']);
            $parts[] = $authors;
        }

        // Title (in quotes)
        if (! empty($data['title'])) {
            $title = trim($data['title'], '."');
            $parts[] = "\"{$title}.\"";
        }

        // Journal (italicized)
        if (! empty($data['journal'])) {
            $journal = $data['journal'];

            // Add volume
            if (! empty($data['volume'])) {
                $journal .= " vol. {$data['volume']}";

                // Add issue
                if (! empty($data['issue'])) {
                    $journal .= ", no. {$data['issue']}";
                }
            }

            $parts[] = $journal.',';
        }

        // Year
        if (! empty($data['year'])) {
            $yearPart = (string) $data['year'];

            // Add pages
            if (! empty($data['pages'])) {
                $yearPart .= ", pp. {$data['pages']}";
            }

            $parts[] = $yearPart.'.';
        }

        return implode(' ', $parts);
    }

    /**
     * Format citation in Chicago style
     */
    private function formatChicago(array $data): string
    {
        $parts = [];

        // Authors
        if (! empty($data['authors'])) {
            $authors = $this->formatAuthorsChicago($data['authors']);
            $parts[] = $authors;
        }

        // Title (in quotes)
        if (! empty($data['title'])) {
            $title = trim($data['title'], '."');
            $parts[] = "\"{$title}.\"";
        }

        // Journal
        if (! empty($data['journal'])) {
            $journal = $data['journal'];

            // Add volume and issue
            if (! empty($data['volume'])) {
                $journal .= " {$data['volume']}";
                if (! empty($data['issue'])) {
                    $journal .= ", no. {$data['issue']}";
                }
            }

            // Add year and pages
            if (! empty($data['year'])) {
                $journal .= " ({$data['year']})";
                if (! empty($data['pages'])) {
                    $journal .= ": {$data['pages']}";
                }
            }

            $parts[] = $journal.'.';
        }

        // DOI or URL
        if (! empty($data['doi'])) {
            $parts[] = "https://doi.org/{$data['doi']}.";
        } elseif (! empty($data['url'])) {
            $parts[] = $data['url'].'.';
        }

        return implode(' ', $parts);
    }

    /**
     * Format citation in Harvard style
     */
    private function formatHarvard(array $data): string
    {
        $parts = [];

        // Authors
        if (! empty($data['authors'])) {
            $authors = $this->formatAuthorsHarvard($data['authors']);
            $parts[] = $authors;
        }

        // Year
        if (! empty($data['year'])) {
            $parts[] = $data['year'].',';
        }

        // Title (in quotes)
        if (! empty($data['title'])) {
            $title = trim($data['title'], '."');
            $parts[] = "'{$title}',";
        }

        // Journal
        if (! empty($data['journal'])) {
            $journal = $data['journal'];

            // Add volume and issue
            if (! empty($data['volume'])) {
                $journal .= ", vol. {$data['volume']}";
                if (! empty($data['issue'])) {
                    $journal .= ", no. {$data['issue']}";
                }
            }

            // Add pages
            if (! empty($data['pages'])) {
                $journal .= ", pp. {$data['pages']}";
            }

            $parts[] = $journal.'.';
        }

        return implode(' ', $parts);
    }

    /**
     * Format authors for APA style
     */
    private function formatAuthorsAPA(array $authors): string
    {
        if (empty($authors)) {
            return '';
        }

        $formatted = [];

        foreach ($authors as $index => $author) {
            if ($index === 0) {
                // First author: Last, F. M.
                $formatted[] = $this->formatAuthorLastFirst($author);
            } else {
                // Other authors: F. M. Last
                $formatted[] = $this->formatAuthorFirstLast($author);
            }
        }

        if (count($formatted) === 1) {
            return $formatted[0];
        } elseif (count($formatted) === 2) {
            return implode(' & ', $formatted);
        } else {
            $lastAuthor = array_pop($formatted);

            return implode(', ', $formatted).', & '.$lastAuthor;
        }
    }

    /**
     * Format authors for MLA style
     */
    private function formatAuthorsMLA(array $authors): string
    {
        if (empty($authors)) {
            return '';
        }

        $formatted = [];

        foreach ($authors as $index => $author) {
            if ($index === 0) {
                // First author: Last, First
                $formatted[] = $this->formatAuthorLastFirst($author, false);
            } else {
                // Other authors: First Last
                $formatted[] = $author;
            }
        }

        if (count($formatted) === 1) {
            return $formatted[0].'.';
        } elseif (count($formatted) === 2) {
            return implode(' and ', $formatted).'.';
        } else {
            $lastAuthor = array_pop($formatted);

            return implode(', ', $formatted).', and '.$lastAuthor.'.';
        }
    }

    /**
     * Format authors for Chicago style
     */
    private function formatAuthorsChicago(array $authors): string
    {
        if (empty($authors)) {
            return '';
        }

        $formatted = [];

        foreach ($authors as $index => $author) {
            if ($index === 0) {
                // First author: Last, First
                $formatted[] = $this->formatAuthorLastFirst($author, false);
            } else {
                // Other authors: First Last
                $formatted[] = $author;
            }
        }

        if (count($formatted) === 1) {
            return $formatted[0].'.';
        } elseif (count($formatted) === 2) {
            return implode(' and ', $formatted).'.';
        } else {
            $lastAuthor = array_pop($formatted);

            return implode(', ', $formatted).', and '.$lastAuthor.'.';
        }
    }

    /**
     * Format authors for Harvard style
     */
    private function formatAuthorsHarvard(array $authors): string
    {
        if (empty($authors)) {
            return '';
        }

        $formatted = [];

        foreach ($authors as $author) {
            $formatted[] = $this->formatAuthorLastFirst($author, false);
        }

        if (count($formatted) === 1) {
            return $formatted[0];
        } elseif (count($formatted) === 2) {
            return implode(' & ', $formatted);
        } else {
            $lastAuthor = array_pop($formatted);

            return implode(', ', $formatted).' & '.$lastAuthor;
        }
    }

    /**
     * Format author name as "Last, F. M." or "Last, First Middle"
     */
    private function formatAuthorLastFirst(string $author, bool $initials = true): string
    {
        $parts = explode(' ', trim($author));

        if (count($parts) < 2) {
            return $author; // Can't format properly, return as-is
        }

        $lastName = array_pop($parts);
        $firstNames = $parts;

        if ($initials) {
            // Convert first names to initials
            $initials = array_map(function ($name) {
                return strtoupper(substr($name, 0, 1)).'.';
            }, $firstNames);

            return $lastName.', '.implode(' ', $initials);
        } else {
            // Use full first names
            return $lastName.', '.implode(' ', $firstNames);
        }
    }

    /**
     * Format author name as "F. M. Last"
     */
    private function formatAuthorFirstLast(string $author): string
    {
        $parts = explode(' ', trim($author));

        if (count($parts) < 2) {
            return $author; // Can't format properly, return as-is
        }

        $lastName = array_pop($parts);
        $firstNames = $parts;

        // Convert first names to initials
        $initials = array_map(function ($name) {
            return strtoupper(substr($name, 0, 1)).'.';
        }, $firstNames);

        return implode(' ', $initials).' '.$lastName;
    }

    /**
     * Generate inline citation
     */
    public function formatInline(array $citationData, string $style = 'apa'): string
    {
        switch (strtolower($style)) {
            case 'apa':
            case 'harvard':
                return $this->formatInlineAPA($citationData);
            case 'mla':
                return $this->formatInlineMLA($citationData);
            case 'chicago':
                return $this->formatInlineChicago($citationData);
            default:
                return $this->formatInlineAPA($citationData);
        }
    }

    /**
     * Format inline citation for APA/Harvard style
     */
    private function formatInlineAPA(array $data): string
    {
        if (empty($data['authors']) || empty($data['year'])) {
            return '[Citation needed]';
        }

        $authors = $data['authors'];
        $year = $data['year'];

        if (count($authors) === 1) {
            return "({$authors[0]}, {$year})";
        } elseif (count($authors) === 2) {
            return "({$authors[0]} & {$authors[1]}, {$year})";
        } else {
            return "({$authors[0]} et al., {$year})";
        }
    }

    /**
     * Format inline citation for MLA style
     */
    private function formatInlineMLA(array $data): string
    {
        if (empty($data['authors'])) {
            return '[Citation needed]';
        }

        $authors = $data['authors'];
        $pages = $data['pages'] ?? null;

        $citation = '';
        if (count($authors) === 1) {
            $citation = "({$authors[0]}";
        } elseif (count($authors) === 2) {
            $citation = "({$authors[0]} and {$authors[1]}";
        } else {
            $citation = "({$authors[0]} et al.";
        }

        if ($pages) {
            $citation .= " {$pages}";
        }

        return $citation.')';
    }

    /**
     * Format inline citation for Chicago style
     */
    private function formatInlineChicago(array $data): string
    {
        if (empty($data['authors']) || empty($data['year'])) {
            return '[Citation needed]';
        }

        $authors = $data['authors'];
        $year = $data['year'];
        $pages = $data['pages'] ?? null;

        $citation = '';
        if (count($authors) === 1) {
            $citation = "({$authors[0]} {$year}";
        } elseif (count($authors) === 2) {
            $citation = "({$authors[0]} and {$authors[1]} {$year}";
        } else {
            $citation = "({$authors[0]} et al. {$year}";
        }

        if ($pages) {
            $citation .= ", {$pages}";
        }

        return $citation.')';
    }
}
