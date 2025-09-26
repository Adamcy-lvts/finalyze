<?php

namespace App\Services\APIs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrossRefAPI
{
    private string $baseUrl = 'https://api.crossref.org';

    private int $timeout = 30;

    private array $headers;

    public function __construct()
    {
        $this->headers = [
            'User-Agent' => 'Finalyze Academic Writing Tool (mailto:'.config('mail.from.address', 'admin@finalyze.com').')',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Search works by topic for paper collection
     */
    public function searchWorks(string $topic, int $limit = 20, array $filters = []): array
    {
        try {
            $params = [
                'query' => $topic,
                'rows' => $limit,
                'sort' => 'relevance',
                'select' => 'DOI,title,author,published-print,published-online,container-title,volume,issue,page,publisher,type,URL,is-referenced-by-count',
            ];

            // Apply filters
            $filterString = [];
            if (isset($filters['from-pub-date'])) {
                $filterString[] = 'from-pub-date:'.$filters['from-pub-date'];
            }
            if (isset($filters['type'])) {
                $filterString[] = 'type:'.$filters['type'];
            }

            if (! empty($filterString)) {
                $params['filter'] = implode(',', $filterString);
            }

            Log::info('CrossRef searchWorks request', [
                'topic' => $topic,
                'limit' => $limit,
                'params' => $params,
            ]);

            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/works", $params);

            if ($response->successful()) {
                $data = $response->json();
                $works = $data['message']['items'] ?? [];

                Log::info('CrossRef searchWorks response', [
                    'total_results' => count($works),
                    'topic' => $topic,
                ]);

                return $works;
            }

            Log::warning('CrossRef searchWorks failed', [
                'topic' => $topic,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('CrossRef searchWorks exception', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Search CrossRef database for citations
     */
    public function search(array $parsed): array
    {
        // Priority search strategy
        if (isset($parsed['doi'])) {
            return $this->searchByDOI($parsed['doi']);
        }

        if (isset($parsed['title']) && isset($parsed['authors'])) {
            return $this->searchByTitleAndAuthor($parsed['title'], $parsed['authors']);
        }

        if (isset($parsed['title'])) {
            return $this->searchByTitle($parsed['title']);
        }

        if (isset($parsed['authors']) && isset($parsed['year'])) {
            return $this->searchByAuthorAndYear($parsed['authors'], $parsed['year']);
        }

        return [];
    }

    /**
     * Search by DOI (most reliable)
     */
    public function searchByDOI(string $doi): array
    {
        $cacheKey = 'crossref_doi_'.md5($doi);

        return Cache::remember($cacheKey, 86400, function () use ($doi) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works/{$doi}");

                if ($response->successful()) {
                    $work = $response->json()['message'] ?? null;

                    return $work ? [$this->formatWork($work)] : [];
                }

                Log::warning('CrossRef DOI search failed', [
                    'doi' => $doi,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('CrossRef DOI search exception', [
                    'doi' => $doi,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by title and author
     */
    public function searchByTitleAndAuthor(string $title, array $authors): array
    {
        $query = $this->buildQuery($title, $authors);
        $cacheKey = 'crossref_search_'.md5($query);

        return Cache::remember($cacheKey, 3600, function () use ($query, $title, $authors) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works", [
                        'query' => $query,
                        'rows' => 10,
                        'select' => 'DOI,title,author,published-print,published-online,container-title,volume,issue,page,publisher,type,URL,abstract',
                        'sort' => 'relevance',
                    ]);

                if ($response->successful()) {
                    $works = $response->json()['message']['items'] ?? [];

                    // Filter and score results
                    return $this->filterAndScoreResults($works, $title, $authors);
                }

                Log::warning('CrossRef title/author search failed', [
                    'query' => $query,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('CrossRef title/author search exception', [
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
    public function searchByTitle(string $title): array
    {
        $cacheKey = 'crossref_title_'.md5($title);

        return Cache::remember($cacheKey, 3600, function () use ($title) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works", [
                        'query.title' => $title,
                        'rows' => 5,
                        'select' => 'DOI,title,author,published-print,published-online,container-title,volume,issue,page,publisher,type,URL',
                        'sort' => 'relevance',
                    ]);

                if ($response->successful()) {
                    $works = $response->json()['message']['items'] ?? [];

                    return array_map([$this, 'formatWork'], array_slice($works, 0, 5));
                }

                Log::warning('CrossRef title search failed', [
                    'title' => $title,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('CrossRef title search exception', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by author and year when no title is available
     */
    private function searchByAuthorAndYear(array $authors, int $year): array
    {
        return Cache::remember('crossref_author_year_'.md5(implode('_', $authors).'_'.$year), 3600, function () use ($authors, $year) {
            try {
                // Clean up "et al." from author names for search
                $cleanAuthors = array_map(function ($author) {
                    return str_replace(' et al.', '', $author);
                }, $authors);

                // Build search query focused on author and year
                $authorQuery = implode(' ', array_slice($cleanAuthors, 0, 2)); // Use first 2 authors
                $query = 'author:'.$authorQuery.' published:'.$year;

                Log::info('CrossRef author+year search', [
                    'query' => $query,
                    'authors' => $authors,
                    'year' => $year,
                ]);

                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->headers)
                    ->get($this->baseUrl.'/works', [
                        'query' => $query,
                        'rows' => 10,
                        'sort' => 'relevance',
                    ]);

                if (! $response->successful()) {
                    Log::warning('CrossRef author+year search failed', [
                        'status' => $response->status(),
                        'query' => $query,
                    ]);

                    return [];
                }

                $data = $response->json();
                $works = $data['message']['items'] ?? [];

                if (empty($works)) {
                    Log::info('No CrossRef results for author+year search', ['query' => $query]);

                    return [];
                }

                // Filter and score results by author and year match
                return $this->filterAndScoreAuthorYearResults($works, $cleanAuthors, $year);

            } catch (\Exception $e) {
                Log::error('CrossRef author+year search exception', [
                    'authors' => $authors,
                    'year' => $year,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Filter and score results for author+year searches
     */
    private function filterAndScoreAuthorYearResults(array $works, array $searchAuthors, int $searchYear): array
    {
        $scored = [];

        foreach ($works as $work) {
            $score = $this->calculateAuthorYearScore($work, $searchAuthors, $searchYear);

            if ($score > 0.2) { // Lower threshold for author-only searches
                $formatted = $this->formatWork($work);
                $formatted['relevance_score'] = $score;
                $scored[] = $formatted;
            }
        }

        // Sort by relevance score
        usort($scored, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, 5); // Return top 5 matches
    }

    /**
     * Calculate relevance score for author+year matching
     */
    private function calculateAuthorYearScore(array $work, array $searchAuthors, int $searchYear): float
    {
        $score = 0;

        // Year matching (very important)
        $workYear = $work['published-print']['date-parts'][0][0] ??
                   $work['published-online']['date-parts'][0][0] ?? null;

        if ($workYear === $searchYear) {
            $score += 0.5; // High weight for exact year match
        } elseif (abs($workYear - $searchYear) <= 1) {
            $score += 0.3; // Partial credit for close years
        }

        // Author matching
        $workAuthors = $work['author'] ?? [];
        $authorScore = $this->calculateAuthorMatchScore($searchAuthors, $workAuthors);
        $score += $authorScore * 0.4; // Author match weight

        // Journal reputation (if available)
        if (! empty($work['container-title'])) {
            $score += 0.1; // Small boost for published works
        }

        return $score;
    }

    /**
     * Get formatted citation string from DOI
     *
     * @param  string  $style  (default: apa)
     */
    public function getCitation(string $doi, string $style = 'apa'): ?string
    {
        try {
            $response = Http::withHeaders([
                'Accept' => "text/x-bibliography; style={$style}",
            ])->get("https://doi.org/{$doi}");

            if ($response->successful()) {
                return trim($response->body());
            }

            Log::warning("Citation request failed for DOI: {$doi}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching citation for DOI: {$doi}", ['exception' => $e]);

            return null;
        }
    }

    /**
     * Build search query from title and authors
     */
    private function buildQuery(string $title, array $authors): string
    {
        $query = '"'.$title.'"';

        if (! empty($authors)) {
            $authorQuery = implode(' ', array_slice($authors, 0, 2)); // First 2 authors
            $query .= ' author:'.$authorQuery;
        }

        return $query;
    }

    /**
     * Filter and score search results based on relevance
     */
    private function filterAndScoreResults(array $works, string $searchTitle, array $searchAuthors): array
    {
        $scored = [];

        foreach ($works as $work) {
            $score = $this->calculateRelevanceScore($work, $searchTitle, $searchAuthors);

            if ($score > 0.3) { // Minimum relevance threshold
                $formatted = $this->formatWork($work);
                $formatted['relevance_score'] = $score;
                $scored[] = $formatted;
            }
        }

        // Sort by relevance score
        usort($scored, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scored, 0, 5);
    }

    /**
     * Calculate relevance score for a CrossRef work
     */
    private function calculateRelevanceScore(array $work, string $searchTitle, array $searchAuthors): float
    {
        $score = 0.0;

        // Title similarity (70% weight)
        if (isset($work['title'][0])) {
            $similarity = $this->calculateStringSimilarity(
                strtolower($searchTitle),
                strtolower($work['title'][0])
            );
            $score += $similarity * 0.7;
        }

        // Author matching (30% weight)
        if (! empty($searchAuthors) && isset($work['author'])) {
            $authorScore = $this->calculateAuthorMatchScore($searchAuthors, $work['author']);
            $score += $authorScore * 0.3;
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
    private function calculateAuthorMatchScore(array $searchAuthors, array $workAuthors): float
    {
        if (empty($searchAuthors) || empty($workAuthors)) {
            return 0;
        }

        $matches = 0;
        $totalSearchAuthors = count($searchAuthors);

        foreach ($searchAuthors as $searchAuthor) {
            foreach ($workAuthors as $workAuthor) {
                $authorName = $this->extractAuthorName($workAuthor);
                if ($this->authorsMatch($searchAuthor, $authorName)) {
                    $matches++;
                    break;
                }
            }
        }

        return $totalSearchAuthors > 0 ? $matches / $totalSearchAuthors : 0;
    }

    /**
     * Extract author name from CrossRef author object
     */
    private function extractAuthorName(array $author): string
    {
        if (isset($author['given']) && isset($author['family'])) {
            return $author['given'].' '.$author['family'];
        }

        return $author['name'] ?? '';
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
     * Format CrossRef work into standard format
     */
    private function formatWork(array $work): array
    {
        $authors = [];
        if (isset($work['author'])) {
            foreach ($work['author'] as $author) {
                $authors[] = $this->extractAuthorName($author);
            }
        }

        // Extract publication year
        $year = null;
        if (isset($work['published-print']['date-parts'][0][0])) {
            $year = $work['published-print']['date-parts'][0][0];
        } elseif (isset($work['published-online']['date-parts'][0][0])) {
            $year = $work['published-online']['date-parts'][0][0];
        }

        // Extract journal/conference name
        $journal = null;
        if (isset($work['container-title'][0])) {
            $journal = $work['container-title'][0];
        }

        return [
            'doi' => $work['DOI'] ?? null,
            'title' => $work['title'][0] ?? 'Untitled',
            'authors' => $authors,
            'year' => $year,
            'journal' => $journal,
            'volume' => $work['volume'] ?? null,
            'issue' => $work['issue'] ?? null,
            'pages' => $work['page'] ?? null,
            'publisher' => $work['publisher'] ?? null,
            'type' => $work['type'] ?? 'journal-article',
            'url' => $work['URL'] ?? null,
            'abstract' => $work['abstract'] ?? null,
            'source' => 'crossref',
            'raw_data' => $work,
        ];
    }

    /**
     * Get work by DOI with full metadata
     */
    public function getWorkByDOI(string $doi): ?array
    {
        $results = $this->searchByDOI($doi);

        return $results[0] ?? null;
    }

    /**
     * Validate DOI format
     */
    public static function isValidDOI(string $doi): bool
    {
        return (bool) preg_match('/^10\.\d{4,}(?:\.\d+)*\/[-._;()\/:A-Za-z0-9]+$/', $doi);
    }

    /**
     * Extract DOI from URL
     */
    public static function extractDOIFromURL(string $url): ?string
    {
        if (preg_match('/doi\.org\/(10\.\d{4,}(?:\.\d+)*\/[-._;()\/:A-Za-z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
