<?php

namespace App\Services\APIs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAlexAPI
{
    private string $baseUrl = 'https://api.openalex.org';

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
     * Search OpenAlex database for citations
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

        return [];
    }

    /**
     * Search by DOI
     */
    private function searchByDOI(string $doi): array
    {
        $cacheKey = 'openalex_doi_'.md5($doi);

        return Cache::remember($cacheKey, 86400, function () use ($doi) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works", [
                        'filter' => "doi:{$doi}",
                        'per-page' => 1,
                    ]);

                if ($response->successful()) {
                    $works = $response->json()['results'] ?? [];

                    return ! empty($works) ? [$this->formatWork($works[0])] : [];
                }

                Log::warning('OpenAlex DOI search failed', [
                    'doi' => $doi,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('OpenAlex DOI search exception', [
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
    private function searchByTitleAndAuthor(string $title, array $authors): array
    {
        $cacheKey = 'openalex_search_'.md5($title.implode('', $authors));

        return Cache::remember($cacheKey, 3600, function () use ($title, $authors) {
            try {
                // Build search query
                $searchQuery = $this->buildSearchQuery($title, $authors);

                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works", [
                        'search' => $searchQuery,
                        'per-page' => 10,
                        'sort' => 'relevance_score:desc',
                    ]);

                if ($response->successful()) {
                    $works = $response->json()['results'] ?? [];

                    // Filter and score results
                    return $this->filterAndScoreResults($works, $title, $authors);
                }

                Log::warning('OpenAlex title/author search failed', [
                    'query' => $searchQuery,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('OpenAlex title/author search exception', [
                    'title' => $title,
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
        $cacheKey = 'openalex_title_'.md5($title);

        return Cache::remember($cacheKey, 3600, function () use ($title) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works", [
                        'search' => $title,
                        'per-page' => 5,
                        'sort' => 'relevance_score:desc',
                    ]);

                if ($response->successful()) {
                    $works = $response->json()['results'] ?? [];

                    return array_map([$this, 'formatWork'], array_slice($works, 0, 5));
                }

                Log::warning('OpenAlex title search failed', [
                    'title' => $title,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('OpenAlex title search exception', [
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
        $query = $title;

        if (! empty($authors)) {
            // Add first author for better matching
            $firstAuthor = $authors[0];
            $query .= " {$firstAuthor}";
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

        // Sort by relevance score, then by citation count
        usort($scored, function ($a, $b) {
            if ($a['relevance_score'] !== $b['relevance_score']) {
                return $b['relevance_score'] <=> $a['relevance_score'];
            }

            return ($b['citation_count'] ?? 0) <=> ($a['citation_count'] ?? 0);
        });

        return array_slice($scored, 0, 5);
    }

    /**
     * Calculate relevance score for an OpenAlex work
     */
    private function calculateRelevanceScore(array $work, string $searchTitle, array $searchAuthors): float
    {
        $score = 0.0;

        // Title similarity (70% weight)
        if (isset($work['title'])) {
            $similarity = $this->calculateStringSimilarity(
                strtolower($searchTitle),
                strtolower($work['title'])
            );
            $score += $similarity * 0.7;
        }

        // Author matching (25% weight)
        if (! empty($searchAuthors) && isset($work['authorships'])) {
            $authorScore = $this->calculateAuthorMatchScore($searchAuthors, $work['authorships']);
            $score += $authorScore * 0.25;
        }

        // Citation count bonus (5% weight) - favor highly cited papers
        if (isset($work['cited_by_count']) && $work['cited_by_count'] > 10) {
            $citationBonus = min(0.05, log($work['cited_by_count']) / 100);
            $score += $citationBonus;
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
    private function calculateAuthorMatchScore(array $searchAuthors, array $authorships): float
    {
        if (empty($searchAuthors) || empty($authorships)) {
            return 0;
        }

        $matches = 0;
        $totalSearchAuthors = count($searchAuthors);

        foreach ($searchAuthors as $searchAuthor) {
            foreach ($authorships as $authorship) {
                $authorName = $authorship['author']['display_name'] ?? '';
                if ($this->authorsMatch($searchAuthor, $authorName)) {
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
     * Format OpenAlex work into standard format
     */
    private function formatWork(array $work): array
    {
        $authors = [];
        if (isset($work['authorships'])) {
            foreach ($work['authorships'] as $authorship) {
                if (isset($authorship['author']['display_name'])) {
                    $authors[] = $authorship['author']['display_name'];
                }
            }
        }

        // Extract DOI
        $doi = null;
        if (isset($work['ids']['doi'])) {
            $doi = str_replace('https://doi.org/', '', $work['ids']['doi']);
        }

        // Extract PubMed ID
        $pubmedId = null;
        if (isset($work['ids']['pmid'])) {
            $pubmedId = str_replace('https://pubmed.ncbi.nlm.nih.gov/', '', $work['ids']['pmid']);
        }

        // Extract journal/venue information
        $journal = null;
        if (isset($work['primary_location']['source']['display_name'])) {
            $journal = $work['primary_location']['source']['display_name'];
        } elseif (isset($work['host_venue']['display_name'])) {
            $journal = $work['host_venue']['display_name'];
        }

        // Extract publication year
        $year = null;
        if (isset($work['publication_year'])) {
            $year = $work['publication_year'];
        } elseif (isset($work['publication_date'])) {
            $year = (int) substr($work['publication_date'], 0, 4);
        }

        $abstractText = $work['abstract'] ?? null;
        if (empty($abstractText) && ! empty($work['abstract_inverted_index']) && is_array($work['abstract_inverted_index'])) {
            $abstractText = $this->reconstructAbstractFromIndex($work['abstract_inverted_index']);
        }

        return [
            'openalex_id' => $work['id'] ?? null,
            'doi' => $doi,
            'pubmed_id' => $pubmedId,
            'title' => $work['title'] ?? 'Untitled',
            'authors' => $authors,
            'year' => $year,
            'journal' => $journal,
            'abstract' => $abstractText,
            'url' => $work['id'] ?? null,
            'citation_count' => $work['cited_by_count'] ?? 0,
            'type' => $work['type'] ?? null,
            'is_oa' => $work['open_access']['is_oa'] ?? false,
            'oa_url' => $work['open_access']['oa_url'] ?? null,
            'concepts' => $this->extractConcepts($work['concepts'] ?? []),
            'mesh_terms' => $work['mesh'] ?? [],
            'publication_date' => $work['publication_date'] ?? null,
            'biblio' => [
                'volume' => $work['biblio']['volume'] ?? null,
                'issue' => $work['biblio']['issue'] ?? null,
                'first_page' => $work['biblio']['first_page'] ?? null,
                'last_page' => $work['biblio']['last_page'] ?? null,
            ],
            'source' => 'openalex',
            'raw_data' => $work,
        ];
    }

    /**
     * Extract concept names from OpenAlex concepts
     */
    private function extractConcepts(array $concepts): array
    {
        return array_map(function ($concept) {
            return [
                'name' => $concept['display_name'] ?? '',
                'score' => $concept['score'] ?? 0,
                'level' => $concept['level'] ?? 0,
            ];
        }, $concepts);
    }

    /**
     * Get work by OpenAlex ID
     */
    public function getWorkById(string $workId): ?array
    {
        $cacheKey = 'openalex_work_'.md5($workId);

        return Cache::remember($cacheKey, 86400, function () use ($workId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works/{$workId}");

                if ($response->successful()) {
                    $work = $response->json();

                    return $work ? $this->formatWork($work) : null;
                }

                return null;
            } catch (\Exception $e) {
                Log::error('OpenAlex work fetch exception', [
                    'work_id' => $workId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get related works based on concepts
     */
    public function getRelatedWorks(array $concepts, int $limit = 10): array
    {
        if (empty($concepts)) {
            return [];
        }

        $conceptIds = array_map(function ($concept) {
            return $concept['id'] ?? '';
        }, array_slice($concepts, 0, 3)); // Use top 3 concepts

        $conceptIds = array_filter($conceptIds);

        if (empty($conceptIds)) {
            return [];
        }

        $cacheKey = 'openalex_related_'.md5(implode('', $conceptIds));

        return Cache::remember($cacheKey, 3600, function () use ($conceptIds, $limit) {
            try {
                $conceptFilter = implode('|', $conceptIds);

                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/works", [
                        'filter' => "concepts.id:{$conceptFilter}",
                        'sort' => 'cited_by_count:desc',
                        'per-page' => $limit,
                    ]);

                if ($response->successful()) {
                    $works = $response->json()['results'] ?? [];

                    return array_map([$this, 'formatWork'], $works);
                }

                return [];
            } catch (\Exception $e) {
                Log::error('OpenAlex related works fetch exception', [
                    'concepts' => $conceptIds,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Get author information by OpenAlex ID
     */
    public function getAuthor(string $authorId): ?array
    {
        $cacheKey = 'openalex_author_'.md5($authorId);

        return Cache::remember($cacheKey, 86400, function () use ($authorId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/authors/{$authorId}");

                if ($response->successful()) {
                    return $response->json();
                }

                return null;
            } catch (\Exception $e) {
                Log::error('OpenAlex author fetch exception', [
                    'author_id' => $authorId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Search for papers by topic/keywords for pre-generation collection
     */
    public function searchWorks(string $topic, int $limit = 20, array $filters = []): array
    {
        // Temporarily disable caching for debugging like SemanticScholar
        try {
            $params = [
                'search' => $topic,
                'per-page' => $limit,
                'sort' => 'cited_by_count:desc', // Sort by citation count
            ];

            // Add filters (OpenAlex uses different syntax)
            $filterParts = [];
            if (isset($filters['year_from']) && is_numeric($filters['year_from'])) {
                $from = (int) $filters['year_from'];
                $filterParts[] = "from_publication_date:{$from}-01-01";
            }
            if (isset($filters['year_to']) && is_numeric($filters['year_to'])) {
                $to = (int) $filters['year_to'];
                $filterParts[] = "to_publication_date:{$to}-12-31";
            }
            if (isset($filters['min_citations']) && is_numeric($filters['min_citations'])) {
                $minCitations = (int) $filters['min_citations'];
                $filterParts[] = "cited_by_count:>{$minCitations}";
            }
            if (! empty($filters['open_access'])) {
                $filterParts[] = 'open_access.is_oa:true';
            }

            if (! empty($filterParts)) {
                $params['filter'] = implode(',', $filterParts);
            }

            Log::info('OpenAlex topic search', [
                'topic' => $topic,
                'params' => $params,
            ]);

            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/works", $params);

            if ($response->successful()) {
                $data = $response->json();
                $works = $data['results'] ?? [];

                Log::info('OpenAlex topic search results', [
                    'topic' => $topic,
                    'total_found' => $data['meta']['count'] ?? 0,
                    'returned_count' => count($works),
                ]);

                // Filter and rank works for quality
                return $this->rankWorksForGeneration($works, $topic);
            }

            Log::warning('OpenAlex topic search failed', [
                'topic' => $topic,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('OpenAlex topic search exception', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Rank works specifically for AI generation quality
     */
    private function rankWorksForGeneration(array $works, string $topic): array
    {
        $ranked = [];

        foreach ($works as $work) {
            // Skip works without essential data
            if (empty($work['title']) || empty($work['abstract_inverted_index'])) {
                continue;
            }

            $formatted = $this->formatWork($work);
            $formatted['generation_score'] = $this->calculateGenerationScore($work, $topic);

            $ranked[] = $formatted;
        }

        // Sort by generation score (quality for AI use)
        usort($ranked, function ($a, $b) {
            return $b['generation_score'] <=> $a['generation_score'];
        });

        return $ranked;
    }

    /**
     * Calculate how good a work is for AI generation
     */
    private function calculateGenerationScore(array $work, string $topic): float
    {
        $score = 0.0;

        // Topic relevance (40% weight)
        if (isset($work['title'])) {
            $topicWords = explode(' ', strtolower($topic));
            $titleWords = explode(' ', strtolower($work['title']));
            $abstractText = $this->reconstructAbstractFromIndex($work['abstract_inverted_index'] ?? []);
            $abstractWords = explode(' ', strtolower($abstractText));

            $relevance = $this->calculateTopicRelevance($topicWords, array_merge($titleWords, $abstractWords));
            $score += $relevance * 0.4;
        }

        // Citation count (30% weight) - more cited = more authoritative
        if (isset($work['cited_by_count'])) {
            $citationScore = min(1.0, $work['cited_by_count'] / 100); // Cap at 100 citations
            $score += $citationScore * 0.3;
        }

        // Recency (20% weight) - prefer recent works
        if (isset($work['publication_year'])) {
            $currentYear = date('Y');
            $yearsDiff = max(0, $currentYear - $work['publication_year']);
            $recencyScore = max(0, 1 - ($yearsDiff / 20)); // Penalize works older than 20 years
            $score += $recencyScore * 0.2;
        }

        // Abstract quality (10% weight) - good abstracts help AI understand
        $abstractText = $this->reconstructAbstractFromIndex($work['abstract_inverted_index'] ?? []);
        if (! empty($abstractText)) {
            $abstractScore = min(1.0, strlen($abstractText) / 1000); // Good abstracts are substantial
            $score += $abstractScore * 0.1;
        }

        return $score;
    }

    /**
     * Calculate topic relevance score (same as SemanticScholar)
     */
    private function calculateTopicRelevance(array $topicWords, array $textWords): float
    {
        if (empty($topicWords) || empty($textWords)) {
            return 0.0;
        }

        $matches = 0;
        foreach ($topicWords as $topicWord) {
            if (strlen($topicWord) < 3) {
                continue;
            } // Skip short words

            foreach ($textWords as $textWord) {
                if (strpos($textWord, $topicWord) !== false || strpos($topicWord, $textWord) !== false) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / count($topicWords);
    }

    /**
     * Reconstruct abstract text from OpenAlex inverted index
     */
    private function reconstructAbstractFromIndex(array $invertedIndex): string
    {
        if (empty($invertedIndex)) {
            return '';
        }

        $words = [];
        foreach ($invertedIndex as $word => $positions) {
            foreach ($positions as $pos) {
                $words[$pos] = $word;
            }
        }

        ksort($words);

        return implode(' ', $words);
    }
}
