<?php

namespace App\Services;

use App\Services\APIs\CrossRefAPI;
use App\Services\APIs\OpenAlexAPI;
use App\Services\APIs\PubMedAPI;
use App\Services\APIs\SemanticScholarAPI;
use Illuminate\Support\Facades\Log;

class ReferenceVerificationService
{
    private array $apis;

    public function __construct(
        private ReferenceParser $parser,
        private CrossRefAPI $crossRefAPI,
        private PubMedAPI $pubMedAPI,
        private SemanticScholarAPI $semanticScholarAPI,
        private OpenAlexAPI $openAlexAPI,
        private CitationService $citationService
    ) {
        $this->apis = [
            'crossref' => $crossRefAPI,
            'pubmed' => $pubMedAPI,
            'semantic_scholar' => $semanticScholarAPI,
            'openalex' => $openAlexAPI,
        ];
    }

    /**
     * Verify all references in content and return enriched data
     */
    public function verifyReferences(string $content): array
    {
        $startTime = microtime(true);

        Log::info('Starting reference verification', [
            'content_length' => strlen($content),
        ]);

        // Step 1: Parse references section
        $references = $this->parser->parseReferences($content);

        if (empty($references)) {
            return [
                'success' => false,
                'message' => 'No references found in content',
                'references' => [],
                'processing_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ];
        }

        Log::info('References parsed', ['count' => count($references)]);

        // Step 2: Verify each reference
        $verifiedReferences = [];
        $successCount = 0;

        foreach ($references as $reference) {
            $verified = $this->verifySingleReference($reference);
            $verifiedReferences[] = $verified;

            if ($verified['status'] === 'verified') {
                $successCount++;
            }
        }

        $processingTime = (int) ((microtime(true) - $startTime) * 1000);

        Log::info('Reference verification completed', [
            'total' => count($references),
            'verified' => $successCount,
            'failed' => count($references) - $successCount,
            'processing_time_ms' => $processingTime,
        ]);

        return [
            'success' => true,
            'references' => $verifiedReferences,
            'summary' => [
                'total' => count($references),
                'verified' => $successCount,
                'failed' => count($references) - $successCount,
                'success_rate' => count($references) > 0 ? round(($successCount / count($references)) * 100, 1) : 0,
            ],
            'processing_time_ms' => $processingTime,
        ];
    }

    /**
     * Verify a single reference entry
     */
    private function verifySingleReference(array $reference): array
    {
        $startTime = microtime(true);

        Log::info('=== STARTING REFERENCE VERIFICATION ===', [
            'reference_id' => $reference['id'],
            'title' => $reference['title'] ?? 'N/A',
            'authors' => $reference['authors'] ?? [],
            'year' => $reference['year'] ?? 'N/A',
            'doi' => $reference['doi'] ?? 'N/A',
            'has_doi' => ! empty($reference['doi']),
            'has_title' => ! empty($reference['title']),
            'has_authors' => ! empty($reference['authors']),
            'available_apis' => array_keys($this->apis),
        ]);

        try {
            // Strategy 1: Search by DOI if available
            if (! empty($reference['doi'])) {
                $result = $this->verifyByDOI($reference);
                if ($result['status'] === 'verified') {
                    return $this->enrichReference($reference, $result, microtime(true) - $startTime);
                }
            }

            // Strategy 2: Search by title + authors + year
            if (! empty($reference['title']) && ! empty($reference['authors'])) {
                $result = $this->verifyByTitleAndAuthors($reference);
                if ($result['status'] === 'verified') {
                    return $this->enrichReference($reference, $result, microtime(true) - $startTime);
                }
            }

            // Strategy 3: Fallback search by title only
            if (! empty($reference['title'])) {
                $result = $this->verifyByTitle($reference);
                if ($result['status'] === 'verified') {
                    return $this->enrichReference($reference, $result, microtime(true) - $startTime);
                }
            }

            // No verification possible
            return $this->markAsUnverified($reference, 'Insufficient data for verification', microtime(true) - $startTime);

        } catch (\Exception $e) {
            Log::error('Reference verification failed', [
                'reference_id' => $reference['id'],
                'error' => $e->getMessage(),
            ]);

            return $this->markAsUnverified($reference, 'Verification error: '.$e->getMessage(), microtime(true) - $startTime);
        }
    }

    /**
     * Verify reference by DOI using multiple APIs
     */
    private function verifyByDOI(array $reference): array
    {
        try {
            // Priority 1: Try CrossRef first for DOI (most reliable for DOIs)
            $formattedCitation = $this->crossRefAPI->getCitation($reference['doi']);

            if ($formattedCitation) {
                $searchResults = $this->crossRefAPI->searchByDOI($reference['doi']);

                return [
                    'status' => 'verified',
                    'method' => 'doi',
                    'confidence' => 1.0,
                    'formatted_citation' => $formattedCitation,
                    'metadata' => $searchResults[0] ?? null,
                    'source' => 'crossref',
                ];
            }

            // Priority 2: Try other APIs if CrossRef fails
            foreach (['openalex', 'semantic_scholar'] as $apiName) {
                try {
                    Log::info("Trying {$apiName} API for DOI search", ['doi' => $reference['doi']]);

                    $searchResults = $this->apis[$apiName]->search(['doi' => $reference['doi']]);

                    Log::info("{$apiName} API DOI search results", [
                        'doi' => $reference['doi'],
                        'results_count' => count($searchResults),
                        'results' => array_map(function ($result) {
                            return [
                                'title' => $result['title'] ?? 'N/A',
                                'authors' => $result['authors'] ?? [],
                                'year' => $result['year'] ?? 'N/A',
                                'doi' => $result['doi'] ?? 'N/A',
                                'source' => $result['source'] ?? 'unknown',
                            ];
                        }, array_slice($searchResults, 0, 3)), // Log first 3 results
                    ]);

                    if (! empty($searchResults)) {
                        Log::info("DOI verification successful via {$apiName}", [
                            'doi' => $reference['doi'],
                            'matched_title' => $searchResults[0]['title'] ?? 'N/A',
                        ]);

                        return [
                            'status' => 'verified',
                            'method' => 'doi',
                            'confidence' => 0.9,
                            'metadata' => $searchResults[0],
                            'source' => $apiName,
                        ];
                    } else {
                        Log::info("No results from {$apiName} for DOI", ['doi' => $reference['doi']]);
                    }
                } catch (\Exception $e) {
                    Log::warning("DOI verification failed for {$apiName}", [
                        'doi' => $reference['doi'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    continue;
                }
            }

            return ['status' => 'failed', 'method' => 'doi', 'reason' => 'DOI not found in any API'];

        } catch (\Exception $e) {
            return ['status' => 'failed', 'method' => 'doi', 'reason' => $e->getMessage()];
        }
    }

    /**
     * Verify reference by title and authors using multiple APIs
     */
    private function verifyByTitleAndAuthors(array $reference): array
    {
        // Skip problematic APIs for now to improve speed
        $apiPriority = ['crossref', 'openalex'];

        foreach ($apiPriority as $apiName) {
            try {
                Log::info("Trying {$apiName} API for title+authors search", [
                    'title' => $reference['title'],
                    'authors' => $reference['authors'],
                ]);

                $searchResults = $this->apis[$apiName]->search([
                    'title' => $reference['title'],
                    'authors' => $reference['authors'],
                ]);

                Log::info("{$apiName} API title+authors search results", [
                    'title' => $reference['title'],
                    'authors' => $reference['authors'],
                    'results_count' => count($searchResults),
                    'results' => array_map(function ($result) {
                        return [
                            'title' => $result['title'] ?? 'N/A',
                            'authors' => is_array($result['authors']) ? array_slice($result['authors'], 0, 3) : ($result['authors'] ?? []),
                            'year' => $result['year'] ?? 'N/A',
                            'doi' => $result['doi'] ?? 'N/A',
                            'journal' => $result['journal'] ?? 'N/A',
                            'confidence' => $result['relevance_score'] ?? 'N/A',
                            'source' => $result['source'] ?? 'unknown',
                        ];
                    }, array_slice($searchResults, 0, 5)), // Log first 5 results
                ]);

                if (! empty($searchResults)) {
                    $bestMatch = $searchResults[0];
                    $similarity = $this->calculateTitleSimilarity($reference['title'], $bestMatch['title'] ?? '');

                    Log::info("{$apiName} title similarity calculation", [
                        'search_title' => $reference['title'],
                        'found_title' => $bestMatch['title'] ?? 'N/A',
                        'similarity' => $similarity,
                        'threshold' => 0.80,
                    ]);

                    if ($similarity >= 0.80) { // Slightly lower threshold for multiple APIs
                        $formattedCitation = null;

                        // Try to get formatted citation if DOI available
                        if (! empty($bestMatch['doi'])) {
                            $formattedCitation = $this->crossRefAPI->getCitation($bestMatch['doi']);
                        }

                        Log::info("Found match via {$apiName}", [
                            'similarity' => $similarity,
                            'has_doi' => ! empty($bestMatch['doi']),
                        ]);

                        return [
                            'status' => 'verified',
                            'method' => 'title_authors',
                            'confidence' => $similarity,
                            'formatted_citation' => $formattedCitation,
                            'metadata' => $bestMatch,
                            'source' => $apiName,
                        ];
                    }

                    Log::info("Low similarity from {$apiName}", ['similarity' => $similarity]);

                    // Store best low-confidence result as fallback
                    if (! isset($fallbackResult) || $similarity > $fallbackResult['confidence']) {
                        $fallbackResult = [
                            'status' => 'failed',
                            'method' => 'title_authors',
                            'reason' => "Best similarity: {$similarity} from {$apiName}",
                            'suggestions' => array_slice($searchResults, 0, 3),
                            'confidence' => $similarity,
                            'source' => $apiName,
                        ];
                    }
                }

            } catch (\Exception $e) {
                Log::warning("Title+authors search failed for {$apiName}", ['error' => $e->getMessage()]);

                continue;
            }
        }

        // Return best low-confidence result if no high-confidence match found
        return $fallbackResult ?? ['status' => 'failed', 'method' => 'title_authors', 'reason' => 'No matches found in any API'];
    }

    /**
     * Verify reference by title only using multiple APIs
     */
    private function verifyByTitle(array $reference): array
    {
        // Skip problematic APIs for now to improve speed
        $apiPriority = ['crossref', 'openalex'];

        foreach ($apiPriority as $apiName) {
            try {
                Log::info("Trying {$apiName} API for title-only search", [
                    'title' => $reference['title'],
                ]);

                $searchResults = $this->apis[$apiName]->search([
                    'title' => $reference['title'],
                ]);

                Log::info("{$apiName} API title-only search results", [
                    'title' => $reference['title'],
                    'results_count' => count($searchResults),
                    'results' => array_map(function ($result) {
                        return [
                            'title' => $result['title'] ?? 'N/A',
                            'authors' => is_array($result['authors']) ? array_slice($result['authors'], 0, 3) : ($result['authors'] ?? []),
                            'year' => $result['year'] ?? 'N/A',
                            'doi' => $result['doi'] ?? 'N/A',
                            'journal' => $result['journal'] ?? 'N/A',
                            'source' => $result['source'] ?? 'unknown',
                        ];
                    }, array_slice($searchResults, 0, 3)), // Log first 3 results for title-only
                ]);

                if (! empty($searchResults)) {
                    $bestMatch = $searchResults[0];
                    $similarity = $this->calculateTitleSimilarity($reference['title'], $bestMatch['title'] ?? '');

                    // Lower threshold for title-only searches across multiple APIs
                    $threshold = ($apiName === 'crossref') ? 0.90 : 0.85;

                    Log::info("{$apiName} title-only similarity calculation", [
                        'search_title' => $reference['title'],
                        'found_title' => $bestMatch['title'] ?? 'N/A',
                        'similarity' => $similarity,
                        'threshold' => $threshold,
                        'passes_threshold' => $similarity >= $threshold,
                    ]);

                    if ($similarity >= $threshold) {
                        $formattedCitation = null;

                        if (! empty($bestMatch['doi'])) {
                            $formattedCitation = $this->crossRefAPI->getCitation($bestMatch['doi']);
                        }

                        Log::info("Found title match via {$apiName}", [
                            'similarity' => $similarity,
                            'threshold' => $threshold,
                        ]);

                        return [
                            'status' => 'verified',
                            'method' => 'title_only',
                            'confidence' => $similarity,
                            'formatted_citation' => $formattedCitation,
                            'metadata' => $bestMatch,
                            'source' => $apiName,
                        ];
                    }

                    // Store best result as fallback
                    if (! isset($fallbackResult) || $similarity > $fallbackResult['confidence']) {
                        $fallbackResult = [
                            'status' => 'failed',
                            'method' => 'title_only',
                            'reason' => "Best similarity: {$similarity} from {$apiName}",
                            'suggestions' => array_slice($searchResults, 0, 3),
                            'confidence' => $similarity,
                            'source' => $apiName,
                        ];
                    }
                }

            } catch (\Exception $e) {
                Log::warning("Title search failed for {$apiName}", ['error' => $e->getMessage()]);

                continue;
            }
        }

        return $fallbackResult ?? ['status' => 'failed', 'method' => 'title_only', 'reason' => 'No matches found in any API'];
    }

    /**
     * Calculate title similarity using Levenshtein distance
     */
    private function calculateTitleSimilarity(string $title1, string $title2): float
    {
        // Normalize titles
        $norm1 = strtolower(preg_replace('/[^\w\s]/', '', $title1));
        $norm2 = strtolower(preg_replace('/[^\w\s]/', '', $title2));

        $distance = levenshtein($norm1, $norm2);
        $maxLength = max(strlen($norm1), strlen($norm2));

        return $maxLength > 0 ? 1 - ($distance / $maxLength) : 0;
    }

    /**
     * Enrich reference with verification data
     */
    private function enrichReference(array $reference, array $verificationResult, float $processingTime): array
    {
        $enriched = $reference;
        $enriched['status'] = 'verified';
        $enriched['verification_method'] = $verificationResult['method'];
        $enriched['confidence'] = $verificationResult['confidence'];
        $enriched['processing_time_ms'] = (int) ($processingTime * 1000);

        // Add metadata from verification
        if (! empty($verificationResult['metadata'])) {
            $metadata = $verificationResult['metadata'];

            // Enrich missing fields
            $enriched['doi'] = $enriched['doi'] ?: ($metadata['doi'] ?? null);
            $enriched['title'] = $enriched['title'] ?: ($metadata['title'] ?? null);
            $enriched['journal'] = $enriched['journal'] ?: ($metadata['journal'] ?? null);
            $enriched['year'] = $enriched['year'] ?: ($metadata['year'] ?? null);
            $enriched['volume'] = $enriched['volume'] ?: ($metadata['volume'] ?? null);
            $enriched['issue'] = $enriched['issue'] ?: ($metadata['issue'] ?? null);
            $enriched['pages'] = $enriched['pages'] ?: ($metadata['pages'] ?? null);
            $enriched['url'] = $enriched['url'] ?: ($metadata['url'] ?? null);

            // Add complete author information if missing
            if (empty($enriched['authors']) && ! empty($metadata['authors'])) {
                $enriched['authors'] = $metadata['authors'];
            }
        }

        // Add formatted citation
        if (! empty($verificationResult['formatted_citation'])) {
            $enriched['formatted_citation'] = $verificationResult['formatted_citation'];
            $enriched['formatting_source'] = $verificationResult['source'];
        }

        return $enriched;
    }

    /**
     * Mark reference as unverified
     */
    private function markAsUnverified(array $reference, string $reason, float $processingTime): array
    {
        $reference['status'] = 'failed';
        $reference['verification_method'] = 'none';
        $reference['confidence'] = 0.0;
        $reference['processing_time_ms'] = (int) ($processingTime * 1000);
        $reference['failure_reason'] = $reason;

        return $reference;
    }
}
