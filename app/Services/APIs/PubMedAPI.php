<?php

namespace App\Services\APIs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PubMedAPI
{
    private string $baseUrl = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils';

    private int $timeout = 10;

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
            // Build PubMed search query for topic-based search
            $query = $this->buildTopicSearchQuery($topic, $filters);

            Log::info('PubMed searchWorks request', [
                'topic' => $topic,
                'limit' => $limit,
                'query' => $query,
            ]);

            // Search for papers
            $searchResponse = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/esearch.fcgi", [
                    'db' => 'pubmed',
                    'term' => $query,
                    'retmode' => 'json',
                    'retmax' => $limit,
                    'sort' => 'relevance',
                ]);

            if ($searchResponse->successful()) {
                $searchData = $searchResponse->json();
                $idList = $searchData['esearchresult']['idlist'] ?? [];

                if (! empty($idList)) {
                    $papers = $this->fetchMultiplePapers($idList);

                    Log::info('PubMed searchWorks response', [
                        'total_results' => count($papers),
                        'topic' => $topic,
                    ]);

                    return $papers;
                }
            }

            Log::warning('PubMed searchWorks failed', [
                'topic' => $topic,
                'status' => $searchResponse->status(),
                'response_data' => $searchData ?? null,
                'query' => $query,
            ]);

            return [];

        } catch (\Exception $e) {
            Log::error('PubMed searchWorks exception', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build topic-based search query for PubMed
     */
    private function buildTopicSearchQuery(string $topic, array $filters = []): string
    {
        // Start with topic in title or abstract
        $query = "(\"{$topic}\"[Title/Abstract])";

        // Add publication date filter if specified
        if (isset($filters['year_from'])) {
            $currentYear = date('Y');
            $query .= " AND ({$filters['year_from']}/01/01[Date - Publication] : {$currentYear}/12/31[Date - Publication])";
        }

        // Prefer journal articles
        $query .= ' AND journal article[Publication Type]';

        // Exclude certain publication types that are less valuable
        $query .= ' NOT (editorial[Publication Type] OR comment[Publication Type] OR letter[Publication Type])';

        return $query;
    }

    /**
     * Search PubMed database for citations
     */
    public function search(array $parsed): array
    {
        // Priority search strategy
        if (isset($parsed['pubmed_id'])) {
            return $this->searchByPubMedID($parsed['pubmed_id']);
        }

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
     * Search by PubMed ID
     */
    private function searchByPubMedID(string $pubmedId): array
    {
        $cacheKey = 'pubmed_id_'.md5($pubmedId);

        return Cache::remember($cacheKey, 86400, function () use ($pubmedId) {
            try {
                // Use efetch to get detailed information
                $response = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/efetch.fcgi", [
                        'db' => 'pubmed',
                        'id' => $pubmedId,
                        'retmode' => 'xml',
                        'rettype' => 'abstract',
                    ]);

                if ($response->successful()) {
                    $papers = $this->parsePubMedXML($response->body());

                    return ! empty($papers) ? [$papers[0]] : [];
                }

                Log::warning('PubMed ID search failed', [
                    'pubmed_id' => $pubmedId,
                    'status' => $response->status(),
                ]);

                return [];
            } catch (\Exception $e) {
                Log::error('PubMed ID search exception', [
                    'pubmed_id' => $pubmedId,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Search by DOI
     */
    private function searchByDOI(string $doi): array
    {
        $cacheKey = 'pubmed_doi_'.md5($doi);

        return Cache::remember($cacheKey, 86400, function () use ($doi) {
            try {
                // First, search for the DOI
                $searchResponse = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/esearch.fcgi", [
                        'db' => 'pubmed',
                        'term' => "\"{$doi}\"[DOI]",
                        'retmode' => 'json',
                        'retmax' => 1,
                    ]);

                if ($searchResponse->successful()) {
                    $searchData = $searchResponse->json();
                    $idList = $searchData['esearchresult']['idlist'] ?? [];

                    if (! empty($idList)) {
                        return $this->searchByPubMedID($idList[0]);
                    }
                }

                return [];
            } catch (\Exception $e) {
                Log::error('PubMed DOI search exception', [
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
        $query = $this->buildSearchQuery($title, $authors);
        $cacheKey = 'pubmed_search_'.md5($query);

        return Cache::remember($cacheKey, 3600, function () use ($query, $title, $authors) {
            try {
                // Search for papers
                $searchResponse = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/esearch.fcgi", [
                        'db' => 'pubmed',
                        'term' => $query,
                        'retmode' => 'json',
                        'retmax' => 10,
                    ]);

                if ($searchResponse->successful()) {
                    $searchData = $searchResponse->json();
                    $idList = $searchData['esearchresult']['idlist'] ?? [];

                    if (! empty($idList)) {
                        // Fetch detailed information for all IDs
                        $papers = $this->fetchMultiplePapers($idList);

                        // Filter and score results
                        return $this->filterAndScoreResults($papers, $title, $authors);
                    }
                }

                return [];
            } catch (\Exception $e) {
                Log::error('PubMed title/author search exception', [
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
        $cacheKey = 'pubmed_title_'.md5($title);

        return Cache::remember($cacheKey, 3600, function () use ($title) {
            try {
                $searchResponse = Http::withHeaders($this->headers)
                    ->timeout($this->timeout)
                    ->get("{$this->baseUrl}/esearch.fcgi", [
                        'db' => 'pubmed',
                        'term' => "\"{$title}\"[Title]",
                        'retmode' => 'json',
                        'retmax' => 5,
                    ]);

                if ($searchResponse->successful()) {
                    $searchData = $searchResponse->json();
                    $idList = $searchData['esearchresult']['idlist'] ?? [];

                    if (! empty($idList)) {
                        return $this->fetchMultiplePapers($idList);
                    }
                }

                return [];
            } catch (\Exception $e) {
                Log::error('PubMed title search exception', [
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
        $query = "\"{$title}\"[Title]";

        if (! empty($authors)) {
            $authorQueries = [];
            foreach (array_slice($authors, 0, 2) as $author) { // First 2 authors
                $authorQueries[] = "\"{$author}\"[Author]";
            }
            $query .= ' AND ('.implode(' OR ', $authorQueries).')';
        }

        return $query;
    }

    /**
     * Fetch multiple papers by their PubMed IDs
     */
    private function fetchMultiplePapers(array $idList): array
    {
        try {
            $response = Http::withHeaders($this->headers)
                ->timeout($this->timeout)
                ->get("{$this->baseUrl}/efetch.fcgi", [
                    'db' => 'pubmed',
                    'id' => implode(',', $idList),
                    'retmode' => 'xml',
                    'rettype' => 'abstract',
                ]);

            if ($response->successful()) {
                return $this->parsePubMedXML($response->body());
            }

            return [];
        } catch (\Exception $e) {
            Log::error('PubMed multiple papers fetch exception', [
                'ids' => $idList,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
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
     * Calculate relevance score for a PubMed paper
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

        // Author matching (30% weight)
        if (! empty($searchAuthors) && isset($paper['authors'])) {
            $authorScore = $this->calculateAuthorMatchScore($searchAuthors, $paper['authors']);
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
     * Parse PubMed XML response into structured data
     */
    private function parsePubMedXML(string $xmlContent): array
    {
        try {
            $xml = simplexml_load_string($xmlContent);
            $papers = [];

            if (isset($xml->PubmedArticle)) {
                foreach ($xml->PubmedArticle as $article) {
                    $paper = $this->parsePubMedArticle($article);
                    if ($paper) {
                        $papers[] = $paper;
                    }
                }
            }

            return $papers;
        } catch (\Exception $e) {
            Log::error('PubMed XML parsing error', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Parse a single PubMed article from XML
     */
    private function parsePubMedArticle(\SimpleXMLElement $article): ?array
    {
        try {
            $medlineCitation = $article->MedlineCitation;
            $pubmedData = $article->PubmedData;

            // Extract basic information
            $pmid = (string) $medlineCitation->PMID;
            $articleData = $medlineCitation->Article;

            // Extract title
            $title = (string) $articleData->ArticleTitle;

            // Extract authors
            $authors = [];
            if (isset($articleData->AuthorList->Author)) {
                foreach ($articleData->AuthorList->Author as $author) {
                    if (isset($author->LastName) && isset($author->ForeName)) {
                        $authors[] = (string) $author->ForeName.' '.(string) $author->LastName;
                    } elseif (isset($author->CollectiveName)) {
                        $authors[] = (string) $author->CollectiveName;
                    }
                }
            }

            // Extract journal information
            $journal = (string) $articleData->Journal->Title ?? null;
            $journalAbbrev = (string) $articleData->Journal->ISOAbbreviation ?? null;

            // Extract publication date
            $year = null;
            if (isset($articleData->Journal->JournalIssue->PubDate)) {
                $pubDate = $articleData->Journal->JournalIssue->PubDate;
                $year = (int) ($pubDate->Year ?? $pubDate->MedlineDate ?? null);
                if (! $year && isset($pubDate->MedlineDate)) {
                    // Extract year from MedlineDate format like "2020 Jan-Feb"
                    if (preg_match('/(\d{4})/', (string) $pubDate->MedlineDate, $matches)) {
                        $year = (int) $matches[1];
                    }
                }
            }

            // Extract volume, issue, pages
            $volume = (string) ($articleData->Journal->JournalIssue->Volume ?? null);
            $issue = (string) ($articleData->Journal->JournalIssue->Issue ?? null);
            $pages = (string) ($articleData->Pagination->MedlinePgn ?? null);

            // Extract abstract
            $abstract = null;
            if (isset($articleData->Abstract->AbstractText)) {
                if (is_array($articleData->Abstract->AbstractText)) {
                    $abstractParts = [];
                    foreach ($articleData->Abstract->AbstractText as $part) {
                        $abstractParts[] = (string) $part;
                    }
                    $abstract = implode(' ', $abstractParts);
                } else {
                    $abstract = (string) $articleData->Abstract->AbstractText;
                }
            }

            // Extract DOI
            $doi = null;
            if (isset($pubmedData->ArticleIdList->ArticleId)) {
                foreach ($pubmedData->ArticleIdList->ArticleId as $articleId) {
                    if ((string) $articleId['IdType'] === 'doi') {
                        $doi = (string) $articleId;
                        break;
                    }
                }
            }

            // Extract MeSH terms
            $meshTerms = [];
            if (isset($medlineCitation->MeshHeadingList->MeshHeading)) {
                foreach ($medlineCitation->MeshHeadingList->MeshHeading as $mesh) {
                    $meshTerms[] = (string) $mesh->DescriptorName;
                }
            }

            return [
                'pubmed_id' => $pmid,
                'doi' => $doi,
                'title' => $title,
                'authors' => $authors,
                'year' => $year,
                'journal' => $journal,
                'journal_abbrev' => $journalAbbrev,
                'volume' => $volume,
                'issue' => $issue,
                'pages' => $pages,
                'abstract' => $abstract,
                'mesh_terms' => $meshTerms,
                'url' => "https://pubmed.ncbi.nlm.nih.gov/{$pmid}/",
                'source' => 'pubmed',
                'raw_data' => $article->asXML(),
            ];
        } catch (\Exception $e) {
            Log::error('PubMed article parsing error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Get paper by PubMed ID with full metadata
     */
    public function getPaperByPubMedID(string $pubmedId): ?array
    {
        $results = $this->searchByPubMedID($pubmedId);

        return $results[0] ?? null;
    }

    /**
     * Validate PubMed ID format
     */
    public static function isValidPubMedID(string $pubmedId): bool
    {
        return (bool) preg_match('/^\d{7,8}$/', $pubmedId);
    }
}
