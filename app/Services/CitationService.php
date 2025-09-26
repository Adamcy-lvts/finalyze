<?php

namespace App\Services;

use App\Jobs\PreVerifyCitations;
use App\Models\Citation;
use App\Models\CitationVerification;
use App\Services\APIs\CrossRefAPI;
use App\Services\APIs\OpenAlexAPI;
use App\Services\APIs\PubMedAPI;
use App\Services\APIs\SemanticScholarAPI;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CitationService
{
    private array $apis;

    private CitationParser $parser;

    private CitationFormatter $formatter;

    private CitationCacheService $cacheService;

    public function __construct(
        CrossRefAPI $crossRef,
        PubMedAPI $pubMed,
        SemanticScholarAPI $semanticScholar,
        OpenAlexAPI $openAlex,
        CitationParser $parser,
        CitationFormatter $formatter,
        CitationCacheService $cacheService
    ) {
        $this->apis = [
            'crossref' => $crossRef,
            'pubmed' => $pubMed,
            'semantic_scholar' => $semanticScholar,
            'openalex' => $openAlex,
        ];
        $this->parser = $parser;
        $this->formatter = $formatter;
        $this->cacheService = $cacheService;
    }

    /**
     * Verify a citation with multiple fallback strategies
     */
    public function verifyCitation(string $rawCitation, array $options = []): CitationVerificationResult
    {
        $startTime = microtime(true);

        // Start verification tracking
        $verification = CitationVerification::create([
            'raw_citation' => $rawCitation,
            'status' => 'processing',
            'session_id' => $options['session_id'] ?? null,
        ]);

        try {
            // Step 1: Parse the citation
            $parsed = $this->parser->parse($rawCitation);
            $verification->update(['detected_format' => $parsed['format'] ?? 'unknown']);

            // Step 2: Check advanced cache first
            $cacheKey = $this->getCacheKey($parsed);
            if ($cached = $this->cacheService->get($cacheKey)) {
                $citation = Citation::find($cached['citation_id']);
                if ($citation) {
                    $verification->update([
                        'status' => 'verified',
                        'matched_citation_id' => $citation->id,
                        'match_confidence' => 1.00,
                        'verification_time_ms' => (microtime(true) - $startTime) * 1000,
                    ]);

                    return CitationVerificationResult::success(
                        $citation,
                        1.0,
                        'cache',
                        [],
                        (int) ((microtime(true) - $startTime) * 1000)
                    );
                }
            }

            // Step 3: Search across multiple APIs
            $results = $this->searchAcrossAPIs($parsed);

            // Step 4: Score and rank results
            $bestMatch = $this->findBestMatch($parsed, $results);

            // Adjust confidence threshold based on citation type
            $threshold = $this->getConfidenceThreshold($parsed);

            if ($bestMatch && $bestMatch['confidence'] >= $threshold) {
                // High confidence match found
                $citationData = $bestMatch['data'];

                // Try to get formatted citation if DOI is available
                if (! empty($citationData['doi'])) {
                    $crossRefAPI = app(\App\Services\APIs\CrossRefAPI::class);
                    $formattedCitation = $crossRefAPI->getCitation($citationData['doi'], 'apa');

                    if ($formattedCitation) {
                        $citationData['formatted_citation'] = $formattedCitation;
                        $citationData['formatting_source'] = 'crossref';
                    }
                }

                $citation = $this->createOrUpdateCitation($citationData);

                $verification->update([
                    'status' => 'verified',
                    'matched_citation_id' => $citation->id,
                    'match_confidence' => $bestMatch['confidence'],
                    'api_responses' => $results,
                    'verification_time_ms' => (microtime(true) - $startTime) * 1000,
                ]);

                // Cache the result in advanced cache
                $this->cacheService->put($cacheKey, [
                    'citation_id' => $citation->id,
                    'data' => $citation->toArray(),
                ], 86400 * 7); // 7 days

                return CitationVerificationResult::success(
                    $citation,
                    $bestMatch['confidence'],
                    $bestMatch['source'],
                    $results,
                    (int) ((microtime(true) - $startTime) * 1000)
                );
            }

            // No high-confidence match found
            $verification->update([
                'status' => 'failed',
                'api_responses' => $results,
                'verification_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            return CitationVerificationResult::failed(
                $this->generateSuggestions($parsed, $results),
                [],
                $results,
                (int) ((microtime(true) - $startTime) * 1000)
            );
        } catch (\Exception $e) {
            Log::error('Citation verification failed', [
                'citation' => $rawCitation,
                'error' => $e->getMessage(),
            ]);

            $verification->update([
                'status' => 'failed',
                'verification_time_ms' => (microtime(true) - $startTime) * 1000,
            ]);

            return CitationVerificationResult::failed(
                [],
                ['Verification failed: '.$e->getMessage()],
                [],
                (int) ((microtime(true) - $startTime) * 1000)
            );
        }
    }

    /**
     * Search across multiple academic APIs with fallback
     */
    private function searchAcrossAPIs(array $parsed): array
    {
        $results = [];

        // Priority order based on parsed identifiers
        $apiOrder = $this->determineAPIOrder($parsed);

        foreach ($apiOrder as $apiName) {
            if (! isset($this->apis[$apiName])) {
                continue;
            }

            try {
                $apiResults = $this->apis[$apiName]->search($parsed);
                if (! empty($apiResults)) {
                    $results[$apiName] = $apiResults;
                }
            } catch (\Exception $e) {
                Log::warning("API search failed: {$apiName}", ['error' => $e->getMessage()]);

                continue;
            }

            // Early return if we have high-confidence results
            if ($this->hasHighConfidenceResult($results)) {
                break;
            }
        }

        return $results;
    }

    /**
     * Find the best matching citation from API results
     */
    private function findBestMatch(array $parsed, array $apiResults): ?array
    {
        $candidates = [];

        foreach ($apiResults as $source => $results) {
            foreach ($results as $result) {
                $score = $this->calculateMatchScore($parsed, $result);
                $candidates[] = [
                    'data' => $result,
                    'source' => $source,
                    'confidence' => $score,
                ];
            }
        }

        // Sort by confidence score
        usort($candidates, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $candidates[0] ?? null;
    }

    /**
     * Calculate match score between parsed citation and API result
     */
    private function calculateMatchScore(array $parsed, array $result): float
    {
        $score = 0.0;
        $weights = [
            'doi' => 1.0,        // Perfect match on DOI
            'title' => 0.3,      // Title similarity
            'authors' => 0.25,   // Author match
            'year' => 0.15,      // Year match
            'journal' => 0.15,   // Journal/venue match
            'pages' => 0.10,     // Page numbers
            'volume' => 0.05,    // Volume/issue
        ];

        // DOI match is definitive
        if (isset($parsed['doi']) && isset($result['doi'])) {
            if (strcasecmp($parsed['doi'], $result['doi']) === 0) {
                return 1.0;
            }
        }

        // Title similarity (using Levenshtein distance)
        if (isset($parsed['title']) && isset($result['title'])) {
            $similarity = $this->calculateStringSimilarity(
                $this->normalizeTitle($parsed['title']),
                $this->normalizeTitle($result['title'])
            );
            $score += $similarity * $weights['title'];
        }

        // Author matching
        if (isset($parsed['authors']) && isset($result['authors'])) {
            $authorScore = $this->calculateAuthorMatch($parsed['authors'], $result['authors']);
            $score += $authorScore * $weights['authors'];
        }

        // Year match
        if (isset($parsed['year']) && isset($result['year'])) {
            if ($parsed['year'] == $result['year']) {
                $score += $weights['year'];
            } elseif (abs($parsed['year'] - $result['year']) <= 1) {
                $score += $weights['year'] * 0.5; // Partial credit for close years
            }
        }

        // Journal/Conference match
        if (isset($parsed['journal']) && isset($result['journal'])) {
            $journalSimilarity = $this->calculateStringSimilarity(
                strtolower($parsed['journal']),
                strtolower($result['journal'])
            );
            $score += $journalSimilarity * $weights['journal'];
        }

        // Pages and volume (exact match only)
        if (
            isset($parsed['pages']) && isset($result['pages'])
            && $parsed['pages'] === $result['pages']
        ) {
            $score += $weights['pages'];
        }

        if (
            isset($parsed['volume']) && isset($result['volume'])
            && $parsed['volume'] === $result['volume']
        ) {
            $score += $weights['volume'];
        }

        return min($score, 1.0); // Cap at 1.0
    }

    /**
     * Generate placeholder for unverified citations
     */
    public function generatePlaceholder(array $parsed): string
    {
        $author = $parsed['authors'][0] ?? '[Author needed]';
        $year = $parsed['year'] ?? '[Year needed]';
        $title = $parsed['title'] ?? '[Title needed]';

        return sprintf(
            '%s (%s). %s. [UNVERIFIED - REQUIRES MANUAL REVIEW]',
            $author,
            $year,
            $title
        );
    }

    private function normalizeTitle(string $title): string
    {
        // Remove punctuation, lowercase, remove articles
        $title = preg_replace('/[^\w\s]/', '', strtolower($title));
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for'];
        $words = array_diff(explode(' ', $title), $stopWords);

        return implode(' ', $words);
    }

    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        $distance = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));

        return $maxLength > 0 ? 1 - ($distance / $maxLength) : 0;
    }

    private function calculateAuthorMatch(array $authors1, array $authors2): float
    {
        if (empty($authors1) || empty($authors2)) {
            return 0;
        }

        $matches = 0;
        $total = max(count($authors1), count($authors2));

        foreach ($authors1 as $author1) {
            foreach ($authors2 as $author2) {
                if ($this->authorsMatch($author1, $author2)) {
                    $matches++;
                    break;
                }
            }
        }

        return $matches / $total;
    }

    private function authorsMatch(string $author1, string $author2): bool
    {
        // Normalize author names
        $norm1 = $this->normalizeAuthorName($author1);
        $norm2 = $this->normalizeAuthorName($author2);

        // Check for exact match
        if ($norm1 === $norm2) {
            return true;
        }

        // Check for last name match with initial
        $parts1 = explode(' ', $norm1);
        $parts2 = explode(' ', $norm2);

        $lastName1 = end($parts1);
        $lastName2 = end($parts2);

        if ($lastName1 === $lastName2) {
            // Check if first names match or are initials
            $firstName1 = $parts1[0] ?? '';
            $firstName2 = $parts2[0] ?? '';

            if (strlen($firstName1) === 1 || strlen($firstName2) === 1) {
                return $firstName1[0] === $firstName2[0];
            }

            return $firstName1 === $firstName2;
        }

        return false;
    }

    private function normalizeAuthorName(string $name): string
    {
        // Remove punctuation, normalize spacing
        $name = preg_replace('/[^\w\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return strtolower($name);
    }

    /**
     * Determine API search order based on parsed identifiers
     */
    private function determineAPIOrder(array $parsed): array
    {
        $order = [];

        // Prioritize based on available identifiers
        if (isset($parsed['doi'])) {
            $order[] = 'crossref';
            $order[] = 'semantic_scholar';
            $order[] = 'openalex';
        }

        if (isset($parsed['pubmed_id'])) {
            $order[] = 'pubmed';
            $order[] = 'semantic_scholar';
        }

        if (isset($parsed['arxiv_id'])) {
            $order[] = 'semantic_scholar';
            $order[] = 'openalex';
        }

        // Default order for text-based searches
        $defaultOrder = ['crossref', 'semantic_scholar', 'openalex', 'pubmed'];

        // Merge and deduplicate
        $order = array_unique(array_merge($order, $defaultOrder));

        return $order;
    }

    /**
     * Queue citations for background verification
     */
    public function queueVerification(array $citations, string $sessionId): void
    {
        PreVerifyCitations::dispatch($citations, $sessionId);
    }

    /**
     * Get pre-verified citation from background job
     */
    public function getPreVerifiedCitation(string $citation, string $sessionId): ?CitationVerificationResult
    {
        $cached = Cache::get("citation:verified:{$sessionId}:".md5($citation));

        if ($cached && is_array($cached)) {
            return CitationVerificationResult::fromArray($cached);
        }

        return null;
    }

    /**
     * Check if results contain high confidence match
     */
    private function hasHighConfidenceResult(array $results): bool
    {
        foreach ($results as $apiResults) {
            foreach ($apiResults as $result) {
                if (isset($result['confidence']) && $result['confidence'] >= 0.9) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get cache key for parsed citation
     */
    private function getCacheKey(array $parsed): string
    {
        // Create a unique key based on identifiers or title+authors
        if (isset($parsed['doi'])) {
            return 'citation_doi_'.md5($parsed['doi']);
        }

        if (isset($parsed['pubmed_id'])) {
            return 'citation_pmid_'.md5($parsed['pubmed_id']);
        }

        if (isset($parsed['arxiv_id'])) {
            return 'citation_arxiv_'.md5($parsed['arxiv_id']);
        }

        $keyData = ($parsed['title'] ?? '').implode('', $parsed['authors'] ?? []).($parsed['year'] ?? '');

        return 'citation_text_'.md5($keyData);
    }

    /**
     * Create or update citation in database
     */
    private function createOrUpdateCitation(array $citationData): Citation
    {
        // Check if citation already exists by DOI or other identifiers
        $existing = null;

        if (! empty($citationData['doi'])) {
            $existing = Citation::where('doi', $citationData['doi'])->first();
        }

        if (! $existing && ! empty($citationData['pubmed_id'])) {
            $existing = Citation::where('pubmed_id', $citationData['pubmed_id'])->first();
        }

        if (! $existing && ! empty($citationData['arxiv_id'])) {
            $existing = Citation::where('arxiv_id', $citationData['arxiv_id'])->first();
        }

        if ($existing) {
            // Update existing citation with new data
            $existing->update($this->prepareCitationData($citationData));

            return $existing;
        }

        // Create new citation
        return Citation::create($this->prepareCitationData($citationData));
    }

    /**
     * Prepare citation data for database storage
     */
    private function prepareCitationData(array $data): array
    {
        return [
            'title' => $data['title'] ?? null,
            'authors' => $data['authors'] ?? [],
            'journal' => $data['journal'] ?? null,
            'year' => $data['year'] ?? null,
            'volume' => $data['volume'] ?? null,
            'issue' => $data['issue'] ?? null,
            'pages' => $data['pages'] ?? null,
            'doi' => $data['doi'] ?? null,
            'pubmed_id' => $data['pubmed_id'] ?? null,
            'arxiv_id' => $data['arxiv_id'] ?? null,
            'abstract' => $data['abstract'] ?? null,
            'url' => $data['url'] ?? null,
            'verification_status' => 'verified',
            'confidence_score' => 1.0,
            'source_api' => $data['source'] ?? 'unknown',
            'raw_data' => $data['raw_data'] ?? [],
            'formatted_citation' => $data['formatted_citation'] ?? null,
            'formatting_source' => $data['formatting_source'] ?? null,
        ];
    }

    /**
     * Generate suggestions for unverified citations
     */
    private function generateSuggestions(array $parsed, array $apiResults): array
    {
        $suggestions = [];

        foreach ($apiResults as $source => $results) {
            foreach (array_slice($results, 0, 3) as $result) { // Top 3 from each source
                $suggestions[] = [
                    'title' => $result['title'] ?? 'Unknown',
                    'authors' => $result['authors'] ?? [],
                    'year' => $result['year'] ?? null,
                    'journal' => $result['journal'] ?? null,
                    'doi' => $result['doi'] ?? null,
                    'source' => $source,
                    'confidence' => $this->calculateMatchScore($parsed, $result),
                ];
            }
        }

        // Sort by confidence and return top 5
        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Get confidence threshold based on citation type and available data
     */
    private function getConfidenceThreshold(array $parsed): float
    {
        // High confidence for citations with strong identifiers
        if (isset($parsed['doi']) || isset($parsed['pubmed_id']) || isset($parsed['arxiv_id'])) {
            return 0.85;
        }

        // Medium-high confidence for full citations with title
        if (isset($parsed['title']) && isset($parsed['authors'])) {
            return 0.70;
        }

        // Medium confidence for title-only searches
        if (isset($parsed['title'])) {
            return 0.60;
        }

        // Lower confidence for "et al." citations with only author + year
        if (isset($parsed['has_et_al']) && $parsed['has_et_al'] && isset($parsed['year'])) {
            return 0.40; // More lenient for et al. citations
        }

        // Lower confidence for author + year only (no et al.)
        if (isset($parsed['authors']) && isset($parsed['year'])) {
            return 0.50;
        }

        // Default threshold for other cases
        return 0.60;
    }
}
