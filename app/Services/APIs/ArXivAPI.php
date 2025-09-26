<?php

namespace App\Services\APIs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArXivAPI
{
    private string $baseUrl = 'http://export.arxiv.org/api/query';

    private int $timeout = 10;

    private array $headers;

    public function __construct()
    {
        $this->headers = [
            'User-Agent' => 'Finalyze Academic Writing Tool',
            'Accept' => 'application/atom+xml',
        ];
    }

    /**
     * Search ArXiv database for citations
     */
    public function search(array $parsed): array
    {
        // Priority search strategy
        if (isset($parsed['arxiv_id'])) {
            return $this->searchByArXivId($parsed['arxiv_id']);
        }

        if (isset($parsed['title']) && isset($parsed['authors'])) {
            return $this->searchByTitleAndAuthor($parsed['title'], $parsed['authors']);
        }

        if (isset($parsed['title'])) {
            return $this->searchByTitle($parsed['title']);
        }

        return [];
    }

    /**
     * Search by ArXiv ID
     */
    private function searchByArXivId(string $arxivId): array
    {
        // Clean arXiv ID format
        $arxivId = $this->cleanArXivId($arxivId);
        $cacheKey = 'arxiv_id_'.md5($arxivId);

        return Cache::remember($cacheKey, 86400, function () use ($arxivId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'id_list' => $arxivId,
                        'max_results' => 1,
                    ]);

                if ($response->successful()) {
                    $papers = $this->parseArXivXML($response->body());

                    return ! empty($papers) ? [$papers[0]] : [];
                }

                Log::warning('ArXiv ID search failed', [
                    'arxiv_id' => $arxivId,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('ArXiv ID search exception', [
                    'arxiv_id' => $arxivId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by title and author
     */
    private function searchByTitleAndAuthor(string $title, array $authors): array
    {
        $query = $this->buildSearchQuery($title, $authors);
        $cacheKey = 'arxiv_search_'.md5($query);

        return Cache::remember($cacheKey, 3600, function () use ($query, $title, $authors) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'search_query' => $query,
                        'max_results' => 10,
                        'sortBy' => 'relevance',
                        'sortOrder' => 'descending',
                    ]);

                if ($response->successful()) {
                    $papers = $this->parseArXivXML($response->body());

                    // Filter and score results
                    return $this->filterAndScoreResults($papers, $title, $authors);
                }

                Log::warning('ArXiv title/author search failed', [
                    'query' => $query,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('ArXiv title/author search exception', [
                    'query' => $query,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by title only
     */
    private function searchByTitle(string $title): array
    {
        $cacheKey = 'arxiv_title_'.md5($title);

        return Cache::remember($cacheKey, 3600, function () use ($title) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'search_query' => "ti:\"{$title}\"",
                        'max_results' => 5,
                        'sortBy' => 'relevance',
                        'sortOrder' => 'descending',
                    ]);

                if ($response->successful()) {
                    $papers = $this->parseArXivXML($response->body());

                    return array_slice($papers, 0, 5);
                }

                Log::warning('ArXiv title search failed', [
                    'title' => $title,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('ArXiv title search exception', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Build search query from title and authors
     */
    private function buildSearchQuery(string $title, array $authors): string
    {
        $query = "ti:\"{$title}\"";

        if (! empty($authors)) {
            $authorQuery = [];
            foreach (array_slice($authors, 0, 2) as $author) { // First 2 authors
                $authorQuery[] = "au:\"{$author}\"";
            }
            $query .= ' AND ('.implode(' OR ', $authorQuery).')';
        }

        return $query;
    }

    /**
     * Filter and score search results based on relevance
     */
    private function filterAndScoreResults(array $papers, string $searchTitle, array $searchAuthors): array
    {
        $scored = [];

        foreach ($papers as $paper) {
            $score = $this->calculateRelevanceScore($paper, $searchTitle, $searchAuthors);

            if ($score > 0.3) { // Minimum relevance threshold
                $paper['relevance_score'] = $score;
                $scored[] = $paper;
            }
        }

        // Sort by relevance score
        usort($scored, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, 5);
    }

    /**
     * Calculate relevance score for an ArXiv paper
     */
    private function calculateRelevanceScore(array $paper, string $searchTitle, array $searchAuthors): float
    {
        $score = 0.0;

        // Title similarity (80% weight - ArXiv has fewer metadata signals)
        if (isset($paper['title'])) {
            $similarity = $this->calculateStringSimilarity(
                strtolower($searchTitle),
                strtolower($paper['title'])
            );
            $score += $similarity * 0.8;
        }

        // Author matching (20% weight)
        if (! empty($searchAuthors) && isset($paper['authors'])) {
            $authorScore = $this->calculateAuthorMatchScore($searchAuthors, $paper['authors']);
            $score += $authorScore * 0.2;
        }

        return $score;
    }

    /**
     * Calculate string similarity using Levenshtein distance
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $distance = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));

        return $maxLength > 0 ? 1 - ($distance / $maxLength) : 0;
    }

    /**
     * Calculate author match score
     */
    private function calculateAuthorMatchScore(array $searchAuthors, array $paperAuthors): float
    {
        if (empty($searchAuthors) || empty($paperAuthors)) {
            return 0;
        }

        $matches = 0;
        $totalSearchAuthors = count($searchAuthors);

        foreach ($searchAuthors as $searchAuthor) {
            foreach ($paperAuthors as $paperAuthor) {
                if ($this->authorsMatch($searchAuthor, $paperAuthor)) {
                    $matches++;
                    break;
                }
            }
        }

        return $totalSearchAuthors > 0 ? $matches / $totalSearchAuthors : 0;
    }

    /**
     * Check if two author names match
     */
    private function authorsMatch(string $author1, string $author2): bool
    {
        $norm1 = $this->normalizeAuthorName($author1);
        $norm2 = $this->normalizeAuthorName($author2);

        // Exact match
        if ($norm1 === $norm2) {
            return true;
        }

        // Last name + first initial match
        $parts1 = explode(' ', $norm1);
        $parts2 = explode(' ', $norm2);

        $lastName1 = end($parts1);
        $lastName2 = end($parts2);

        if ($lastName1 === $lastName2 && ! empty($parts1[0]) && ! empty($parts2[0])) {
            return $parts1[0][0] === $parts2[0][0];
        }

        return false;
    }

    /**
     * Normalize author name for comparison
     */
    private function normalizeAuthorName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return strtolower($name);
    }

    /**
     * Clean and normalize ArXiv ID
     */
    private function cleanArXivId(string $arxivId): string
    {
        // Remove arxiv: prefix if present
        $arxivId = preg_replace('/^arxiv:/i', '', $arxivId);

        // Handle old format (e.g., math/0601001) and new format (e.g., 1501.00001)
        if (preg_match('/^([a-z-]+)\/(\d{7})$/i', $arxivId)) {
            return $arxivId; // Old format, return as-is
        }

        if (preg_match('/^(\d{4}\.\d{4,5})(v\d+)?$/i', $arxivId, $matches)) {
            return $matches[1]; // New format, remove version if present
        }

        return $arxivId; // Return as-is if format not recognized
    }

    /**
     * Parse ArXiv XML response into structured data
     */
    private function parseArXivXML(string $xmlContent): array
    {
        try {
            $xml = simplexml_load_string($xmlContent);
            $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
            $xml->registerXPathNamespace('arxiv', 'http://arxiv.org/schemas/atom');

            $papers = [];
            $entries = $xml->xpath('//atom:entry');

            foreach ($entries as $entry) {
                $paper = $this->parseArXivEntry($entry);
                if ($paper) {
                    $papers[] = $paper;
                }
            }

            return $papers;
        } catch (\Exception $e) {
            Log::error('ArXiv XML parsing error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Parse a single ArXiv entry from XML
     */
    private function parseArXivEntry(\SimpleXMLElement $entry): ?array
    {
        try {
            $entry->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
            $entry->registerXPathNamespace('arxiv', 'http://arxiv.org/schemas/atom');

            // Extract ArXiv ID from URL
            $id = (string) $entry->id;
            $arxivId = null;
            if (preg_match('/arxiv\.org\/abs\/(.+)$/', $id, $matches)) {
                $arxivId = $matches[1];
            }

            // Extract authors
            $authors = [];
            foreach ($entry->xpath('atom:author') as $author) {
                $authors[] = (string) $author->name;
            }

            // Extract categories
            $categories = [];
            foreach ($entry->xpath('atom:category') as $category) {
                $categories[] = (string) $category['term'];
            }

            // Extract year from published date
            $published = (string) $entry->published;
            $year = null;
            if ($published) {
                $year = (int) substr($published, 0, 4);
            }

            // Extract DOI from links if available
            $doi = null;
            foreach ($entry->xpath('atom:link') as $link) {
                $href = (string) $link['href'];
                if (strpos($href, 'doi.org') !== false) {
                    $doi = CrossRefAPI::extractDOIFromURL($href);
                    break;
                }
            }

            return [
                'arxiv_id' => $arxivId,
                'doi' => $doi,
                'title' => (string) $entry->title,
                'authors' => $authors,
                'year' => $year,
                'abstract' => (string) $entry->summary,
                'categories' => $categories,
                'primary_category' => (string) $entry->xpath('arxiv:primary_category')[0]['term'] ?? null,
                'published_date' => $published,
                'updated_date' => (string) $entry->updated,
                'url' => $id,
                'pdf_url' => str_replace('/abs/', '/pdf/', $id).'.pdf',
                'journal_ref' => (string) $entry->xpath('arxiv:journal_ref')[0] ?? null,
                'comment' => (string) $entry->xpath('arxiv:comment')[0] ?? null,
                'source' => 'arxiv',
                'raw_data' => $entry->asXML(),
            ];
        } catch (\Exception $e) {
            Log::error('ArXiv entry parsing error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get paper by ArXiv ID with full metadata
     */
    public function getPaperByArXivId(string $arxivId): ?array
    {
        $results = $this->searchByArXivId($arxivId);

        return $results[0] ?? null;
    }

    /**
     * Search by subject category
     */
    public function searchByCategory(string $category, int $maxResults = 20): array
    {
        $cacheKey = 'arxiv_category_'.md5($category.$maxResults);

        return Cache::remember($cacheKey, 1800, function () use ($category, $maxResults) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'search_query' => "cat:{$category}",
                        'max_results' => $maxResults,
                        'sortBy' => 'lastUpdatedDate',
                        'sortOrder' => 'descending',
                    ]);

                if ($response->successful()) {
                    return $this->parseArXivXML($response->body());
                }

                return [];
            } catch (\Exception $e) {
                Log::error('ArXiv category search exception', [
                    'category' => $category,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Get recent papers in a field
     */
    public function getRecentPapers(array $categories = [], int $maxResults = 10): array
    {
        if (empty($categories)) {
            $categories = ['cs.AI', 'cs.LG', 'cs.CL']; // Default to AI/ML categories
        }

        $categoryQuery = 'cat:'.implode(' OR cat:', $categories);
        $cacheKey = 'arxiv_recent_'.md5($categoryQuery.$maxResults);

        return Cache::remember($cacheKey, 1800, function () use ($categoryQuery, $maxResults) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get($this->baseUrl, [
                        'search_query' => $categoryQuery,
                        'max_results' => $maxResults,
                        'sortBy' => 'submittedDate',
                        'sortOrder' => 'descending',
                    ]);

                if ($response->successful()) {
                    return $this->parseArXivXML($response->body());
                }

                return [];
            } catch (\Exception $e) {
                Log::error('ArXiv recent papers exception', [
                    'categories' => $categoryQuery,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Validate ArXiv ID format
     */
    public static function isValidArXivId(string $arxivId): bool
    {
        // Clean the ID first
        $arxivId = preg_replace('/^arxiv:/i', '', $arxivId);

        // Check old format (e.g., math/0601001)
        if (preg_match('/^[a-z-]+\/\d{7}$/i', $arxivId)) {
            return true;
        }

        // Check new format (e.g., 1501.00001v1)
        if (preg_match('/^\d{4}\.\d{4,5}(v\d+)?$/i', $arxivId)) {
            return true;
        }

        return false;
    }
}
