<?php

namespace App\Services\APIs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SemanticScholarAPI
{
    private string $baseUrl = 'https://api.semanticscholar.org/graph/v1';

    private int $timeout = 15;

    private array $headers;

    private array $paperFields = [
        'paperId',
        'title',
        'authors',
        'year',
        'venue',
        'journal',
        'citationCount',
        'influentialCitationCount',
        'publicationTypes',
        'publicationDate',
        'abstract',
        'url',
        'externalIds',
        'fieldsOfStudy',
        'categories',
        'citations.title',
        'citations.year',
        'citations.authors',
    ];

    public function __construct()
    {
        $this->headers = [
            'User-Agent' => 'Finalyze Academic Writing Tool',
            'Accept' => 'application/json',
        ];

        // Add API key if configured
        if ($apiKey = config('services.semantic_scholar.api_key')) {
            $this->headers['x-api-key'] = $apiKey;
        }
    }

    /**
     * Search Semantic Scholar database for citations
     */
    public function search(array $parsed): array
    {
        // Priority search strategy
        if (isset($parsed['doi'])) {
            return $this->searchByDOI($parsed['doi']);
        }

        if (isset($parsed['pubmed_id'])) {
            return $this->searchByPubMedID($parsed['pubmed_id']);
        }

        if (isset($parsed['arxiv_id'])) {
            return $this->searchByArXivID($parsed['arxiv_id']);
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
        $cacheKey = 'semantic_scholar_doi_'.md5($doi);

        return Cache::remember($cacheKey, 86400, function () use ($doi) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/DOI:{$doi}", [
                        'fields' => implode(',', $this->paperFields),
                    ]);

                if ($response->successful()) {
                    $paper = $response->json();

                    return $paper ? [$this->formatPaper($paper)] : [];
                }

                if ($response->status() === 404) {
                    // DOI not found, this is normal
                    return [];
                }

                Log::warning('Semantic Scholar DOI search failed', [
                    'doi' => $doi,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar DOI search exception', [
                    'doi' => $doi,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by PubMed ID
     */
    private function searchByPubMedID(string $pubmedId): array
    {
        $cacheKey = 'semantic_scholar_pmid_'.md5($pubmedId);

        return Cache::remember($cacheKey, 86400, function () use ($pubmedId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/PMID:{$pubmedId}", [
                        'fields' => implode(',', $this->paperFields),
                    ]);

                if ($response->successful()) {
                    $paper = $response->json();

                    return $paper ? [$this->formatPaper($paper)] : [];
                }

                if ($response->status() === 404) {
                    return [];
                }

                Log::warning('Semantic Scholar PubMed search failed', [
                    'pubmed_id' => $pubmedId,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar PubMed search exception', [
                    'pubmed_id' => $pubmedId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by ArXiv ID
     */
    private function searchByArXivID(string $arxivId): array
    {
        $cacheKey = 'semantic_scholar_arxiv_'.md5($arxivId);

        return Cache::remember($cacheKey, 86400, function () use ($arxivId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/ARXIV:{$arxivId}", [
                        'fields' => implode(',', $this->paperFields),
                    ]);

                if ($response->successful()) {
                    $paper = $response->json();

                    return $paper ? [$this->formatPaper($paper)] : [];
                }

                if ($response->status() === 404) {
                    return [];
                }

                Log::warning('Semantic Scholar ArXiv search failed', [
                    'arxiv_id' => $arxivId,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar ArXiv search exception', [
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
        $query = $this->buildQuery($title, $authors);
        $cacheKey = 'semantic_scholar_search_'.md5($query);

        return Cache::remember($cacheKey, 3600, function () use ($query, $title, $authors) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/search", [
                        'query' => $query,
                        'limit' => 10,
                        'fields' => implode(',', $this->paperFields),
                    ]);

                if ($response->successful()) {
                    $papers = $response->json()['data'] ?? [];

                    // Filter and score results
                    return $this->filterAndScoreResults($papers, $title, $authors);
                }

                Log::warning('Semantic Scholar title/author search failed', [
                    'query' => $query,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar title/author search exception', [
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
        $cacheKey = 'semantic_scholar_title_'.md5($title);

        return Cache::remember($cacheKey, 3600, function () use ($title) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/search", [
                        'query' => $title,
                        'limit' => 5,
                        'fields' => implode(',', $this->paperFields),
                    ]);

                if ($response->successful()) {
                    $papers = $response->json()['data'] ?? [];

                    return array_map([$this, 'formatPaper'], array_slice($papers, 0, 5));
                }

                Log::warning('Semantic Scholar title search failed', [
                    'title' => $title,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar title search exception', [
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
    private function buildQuery(string $title, array $authors): string
    {
        $query = $title;

        if (! empty($authors)) {
            $authorQuery = implode(' ', array_slice($authors, 0, 2)); // First 2 authors
            $query .= ' '.$authorQuery;
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
                $formatted = $this->formatPaper($paper);
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
     * Calculate relevance score for a Semantic Scholar paper
     */
    private function calculateRelevanceScore(array $paper, string $searchTitle, array $searchAuthors): float
    {
        $score = 0.0;

        // Title similarity (70% weight)
        if (isset($paper['title'])) {
            $similarity = $this->calculateStringSimilarity(
                strtolower($searchTitle),
                strtolower($paper['title'])
            );
            $score += $similarity * 0.7;
        }

        // Author matching (25% weight)
        if (! empty($searchAuthors) && isset($paper['authors'])) {
            $authorScore = $this->calculateAuthorMatchScore($searchAuthors, $paper['authors']);
            $score += $authorScore * 0.25;
        }

        // Citation count bonus (5% weight) - favor highly cited papers
        if (isset($paper['citationCount']) && $paper['citationCount'] > 10) {
            $citationBonus = min(0.05, log($paper['citationCount']) / 100);
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
    private function calculateAuthorMatchScore(array $searchAuthors, array $paperAuthors): float
    {
        if (empty($searchAuthors) || empty($paperAuthors)) {
            return 0;
        }

        $matches = 0;
        $totalSearchAuthors = count($searchAuthors);

        foreach ($searchAuthors as $searchAuthor) {
            foreach ($paperAuthors as $paperAuthor) {
                $authorName = $paperAuthor['name'] ?? '';
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
     * Format Semantic Scholar paper into standard format
     */
    private function formatPaper(array $paper): array
    {
        $authors = [];
        if (isset($paper['authors'])) {
            foreach ($paper['authors'] as $author) {
                $authors[] = $author['name'] ?? 'Unknown';
            }
        }

        // Extract DOI from external IDs
        $doi = null;
        if (isset($paper['externalIds']['DOI'])) {
            $doi = $paper['externalIds']['DOI'];
        }

        // Extract PubMed ID
        $pubmedId = null;
        if (isset($paper['externalIds']['PubMed'])) {
            $pubmedId = $paper['externalIds']['PubMed'];
        }

        // Extract ArXiv ID
        $arxivId = null;
        if (isset($paper['externalIds']['ArXiv'])) {
            $arxivId = $paper['externalIds']['ArXiv'];
        }

        return [
            'semantic_scholar_id' => $paper['paperId'] ?? null,
            'doi' => $doi,
            'pubmed_id' => $pubmedId,
            'arxiv_id' => $arxivId,
            'title' => $paper['title'] ?? 'Untitled',
            'authors' => $authors,
            'year' => $paper['year'],
            'journal' => $paper['venue'] ?? $paper['journal']['name'] ?? null,
            'abstract' => $paper['abstract'] ?? null,
            'url' => $paper['url'] ?? null,
            'citation_count' => $paper['citationCount'] ?? 0,
            'influential_citation_count' => $paper['influentialCitationCount'] ?? 0,
            'publication_types' => $paper['publicationTypes'] ?? [],
            'publication_date' => $paper['publicationDate'] ?? null,
            'fields_of_study' => $paper['fieldsOfStudy'] ?? [],
            'categories' => $paper['categories'] ?? [],
            'source' => 'semantic_scholar',
            'raw_data' => $paper,
        ];
    }

    /**
     * Get paper by Semantic Scholar ID
     */
    public function getPaperById(string $paperId): ?array
    {
        $cacheKey = 'semantic_scholar_paper_'.md5($paperId);

        return Cache::remember($cacheKey, 86400, function () use ($paperId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/{$paperId}", [
                        'fields' => implode(',', $this->paperFields),
                    ]);

                if ($response->successful()) {
                    $paper = $response->json();

                    return $paper ? $this->formatPaper($paper) : null;
                }

                return null;
            } catch (\Exception $e) {
                Log::error('Semantic Scholar paper fetch exception', [
                    'paper_id' => $paperId,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        });
    }

    /**
     * Get citations for a paper
     */
    public function getCitations(string $paperId, int $limit = 100): array
    {
        $cacheKey = 'semantic_scholar_citations_'.md5("{$paperId}_{$limit}");

        return Cache::remember($cacheKey, 3600, function () use ($paperId, $limit) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/paper/{$paperId}/citations", [
                        'fields' => 'title,year,authors',
                        'limit' => $limit,
                    ]);

                if ($response->successful()) {
                    $citations = $response->json()['data'] ?? [];

                    return array_map(function ($citation) {
                        return $this->formatPaper($citation['citingPaper']);
                    }, $citations);
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar citations fetch exception', [
                    'paper_id' => $paperId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Get recommendations based on a paper
     */
    public function getRecommendations(string $paperId, int $limit = 10): array
    {
        $cacheKey = 'semantic_scholar_recommendations_'.md5("{$paperId}_{$limit}");

        return Cache::remember($cacheKey, 3600, function () use ($paperId, $limit) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/recommendations/paper/{$paperId}", [
                        'fields' => implode(',', $this->paperFields),
                        'limit' => $limit,
                    ]);

                if ($response->successful()) {
                    $recommendations = $response->json()['recommendedPapers'] ?? [];

                    return array_map([$this, 'formatPaper'], $recommendations);
                }

                return [];
            } catch (\Exception $e) {
                Log::error('Semantic Scholar recommendations fetch exception', [
                    'paper_id' => $paperId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search for papers by topic/keywords for pre-generation collection
     */
    public function searchByTopic(string $topic, int $limit = 20, array $filters = []): array
    {
        // Temporarily disable caching for debugging
        // $cacheKey = 'semantic_scholar_topic_'.md5($topic.'_'.$limit.'_'.serialize($filters));
        // return Cache::remember($cacheKey, 3600, function () use ($topic, $limit, $filters) {
        try {
            $params = [
                'query' => $topic,
                'limit' => $limit,
                'fields' => implode(',', $this->paperFields),
            ];

            // Add filters
            if (isset($filters['year_from'])) {
                $params['year'] = $filters['year_from'].'-';
            }
            if (isset($filters['year_to'])) {
                $params['year'] = ($params['year'] ?? '').$filters['year_to'];
            }
            if (isset($filters['fields_of_study'])) {
                $params['fieldsOfStudy'] = implode(',', (array) $filters['fields_of_study']);
            }
            if (isset($filters['publication_types'])) {
                $params['publicationTypes'] = implode(',', (array) $filters['publication_types']);
            }

            Log::info('Semantic Scholar topic search', [
                'topic' => $topic,
                'params' => $params,
            ]);

            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/paper/search", $params);

            if ($response->successful()) {
                $data = $response->json();
                $papers = $data['data'] ?? [];

                Log::info('Semantic Scholar topic search results', [
                    'topic' => $topic,
                    'total_found' => $data['total'] ?? 0,
                    'returned_count' => count($papers),
                ]);

                // Filter and rank papers for quality
                return $this->rankPapersForGeneration($papers, $topic);
            }

            Log::warning('Semantic Scholar topic search failed', [
                'topic' => $topic,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Semantic Scholar topic search exception', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
        // });
    }

    /**
     * Rank papers specifically for AI generation quality
     */
    private function rankPapersForGeneration(array $papers, string $topic): array
    {
        $ranked = [];

        foreach ($papers as $paper) {
            // Skip papers without essential data
            if (empty($paper['title']) || empty($paper['abstract'])) {
                continue;
            }

            $formatted = $this->formatPaper($paper);
            $formatted['generation_score'] = $this->calculateGenerationScore($paper, $topic);

            $ranked[] = $formatted;
        }

        // Sort by generation score (quality for AI use)
        usort($ranked, function ($a, $b) {
            return $b['generation_score'] <=> $a['generation_score'];
        });

        return $ranked;
    }

    /**
     * Calculate how good a paper is for AI generation
     */
    private function calculateGenerationScore(array $paper, string $topic): float
    {
        $score = 0.0;

        // Topic relevance (40% weight)
        if (isset($paper['title'])) {
            $topicWords = explode(' ', strtolower($topic));
            $titleWords = explode(' ', strtolower($paper['title']));
            $abstractWords = isset($paper['abstract']) ? explode(' ', strtolower($paper['abstract'])) : [];

            $relevance = $this->calculateTopicRelevance($topicWords, array_merge($titleWords, $abstractWords));
            $score += $relevance * 0.4;
        }

        // Citation count (30% weight) - more cited = more authoritative
        if (isset($paper['citationCount'])) {
            $citationScore = min(1.0, $paper['citationCount'] / 100); // Cap at 100 citations
            $score += $citationScore * 0.3;
        }

        // Recency (20% weight) - prefer recent papers
        if (isset($paper['year'])) {
            $currentYear = date('Y');
            $yearsDiff = max(0, $currentYear - $paper['year']);
            $recencyScore = max(0, 1 - ($yearsDiff / 20)); // Penalize papers older than 20 years
            $score += $recencyScore * 0.2;
        }

        // Abstract quality (10% weight) - good abstracts help AI understand
        if (isset($paper['abstract'])) {
            $abstractLength = strlen($paper['abstract']);
            $abstractScore = min(1.0, $abstractLength / 1000); // Good abstracts are substantial
            $score += $abstractScore * 0.1;
        }

        return $score;
    }

    /**
     * Calculate topic relevance score
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
}
