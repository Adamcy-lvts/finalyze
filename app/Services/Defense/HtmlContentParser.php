<?php

namespace App\Services\Defense;

class HtmlContentParser
{
    public function parse(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [
                'headings' => [],
                'paragraphs' => [],
                'lists' => [],
                'tables' => [],
                'statistics' => [],
                'citations' => [],
            ];
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [
            'headings' => $this->extractHeadings($xpath),
            'paragraphs' => $this->extractParagraphs($xpath),
            'lists' => $this->extractLists($xpath),
            'tables' => $this->extractTables($xpath),
            'statistics' => $this->extractStatistics($text),
            'citations' => $this->extractCitations($text),
        ];
    }

    private function extractHeadings(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        if (! $nodes) {
            return [];
        }

        $headings = [];
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text === '') {
                continue;
            }

            $level = (int) substr($node->nodeName, 1);
            $headings[] = [
                'level' => $level,
                'text' => $text,
            ];
        }

        return $headings;
    }

    private function extractParagraphs(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//p');
        if (! $nodes) {
            return [];
        }

        $paragraphs = [];
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return $paragraphs;
    }

    private function extractLists(\DOMXPath $xpath): array
    {
        $nodes = $xpath->query('//ul|//ol');
        if (! $nodes) {
            return [];
        }

        $lists = [];
        foreach ($nodes as $listNode) {
            $items = [];
            foreach ($listNode->childNodes as $child) {
                if ($child->nodeName !== 'li') {
                    continue;
                }
                $text = trim($child->textContent);
                if ($text !== '') {
                    $items[] = $text;
                }
            }

            if ($items) {
                $lists[] = [
                    'type' => $listNode->nodeName,
                    'items' => $items,
                ];
            }
        }

        return $lists;
    }

    private function extractTables(\DOMXPath $xpath): array
    {
        $tables = [];
        $tableNodes = $xpath->query('//table');
        if (! $tableNodes) {
            return [];
        }

        foreach ($tableNodes as $table) {
            $captionNode = $xpath->query('.//caption', $table)?->item(0);
            $title = $captionNode ? trim($captionNode->textContent) : '';

            $rows = [];
            $columns = [];

            $rowNodes = $xpath->query('.//tr', $table);
            if (! $rowNodes) {
                continue;
            }

            $headerCaptured = false;
            foreach ($rowNodes as $rowIndex => $row) {
                $cells = [];
                $hasHeaderCell = false;
                foreach ($row->childNodes as $cell) {
                    if (! in_array($cell->nodeName, ['th', 'td'], true)) {
                        continue;
                    }
                    $text = trim($cell->textContent);
                    $cells[] = $text;
                    if ($cell->nodeName === 'th') {
                        $hasHeaderCell = true;
                    }
                }

                if (! $cells) {
                    continue;
                }

                if (! $headerCaptured && ($hasHeaderCell || $rowIndex === 0)) {
                    $columns = $cells;
                    $headerCaptured = true;

                    continue;
                }

                $rows[] = $cells;
            }

            if (! $columns && $rows) {
                $columns = array_shift($rows);
            }

            if ($columns || $rows) {
                $tables[] = [
                    'title' => $title,
                    'columns' => $columns,
                    'rows' => $rows,
                ];
            }
        }

        return $tables;
    }

    private function extractStatistics(string $text): array
    {
        $patterns = [
            '/\b\d+%/i',
            '/\br\s*=\s*-?\d+(?:\.\d+)?/i',
            '/\bp\s*[<=>]\s*0\.\d+/i',
            '/\bn\s*=\s*\d+/i',
            '/(?:\xCE\xB1|alpha)\s*=\s*0\.\d+/i',
        ];

        $matches = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $found);
            foreach ($found[0] ?? [] as $value) {
                $matches[] = trim($value);
            }
        }

        $matches = array_values(array_unique(array_filter($matches)));

        return $matches;
    }

    private function extractCitations(string $text): array
    {
        $pattern = '/\(([A-Z][^()]*?,\s*\d{4}[a-z]?)\)/';
        preg_match_all($pattern, $text, $matches);

        $citations = [];
        foreach ($matches[1] ?? [] as $citation) {
            $clean = trim($citation);
            if ($clean !== '') {
                $citations[] = $clean;
            }
        }

        return array_values(array_unique($citations));
    }
}
