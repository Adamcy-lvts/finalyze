<?php

namespace App\Services\Citation;

use App\Models\CitationCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class ExternalApiService
{
    protected string $apiName;

    protected string $baseUrl;

    protected array $rateLimits = [];

    protected int $timeout = 30;

    protected int $cacheHours = 720; // 30 days default

    /**
     * Search for citations
     */
    abstract public function search(string $query, array $params = []): array;

    /**
     * Get citation by identifier (DOI, PubMed ID, etc.)
     */
    abstract public function getByIdentifier(string $identifier): ?array;

    /**
     * Make HTTP request with caching and rate limiting
     */
    protected function makeRequest(string $endpoint, array $params = []): ?array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);

        // Check cache first
        $cached = CitationCache::retrieve($this->apiName, $endpoint, $params);
        if ($cached) {
            Log::info('Citation API cache hit', [
                'api' => $this->apiName,
                'endpoint' => $endpoint,
            ]);

            return $cached;
        }

        try {
            // Respect rate limits
            $this->respectRateLimits();

            $response = Http::timeout($this->timeout)
                ->get($this->baseUrl.$endpoint, $params);

            if ($response->successful()) {
                $data = $response->json();

                // Cache successful response
                CitationCache::store($this->apiName, $endpoint, $data, $params, $this->cacheHours);

                Log::info('Citation API request successful', [
                    'api' => $this->apiName,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                ]);

                return $data;
            } else {
                Log::warning('Citation API request failed', [
                    'api' => $this->apiName,
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ]);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('Citation API request exception', [
                'api' => $this->apiName,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Respect rate limits for this API
     */
    protected function respectRateLimits(): void
    {
        if (empty($this->rateLimits)) {
            return;
        }

        // Simple rate limiting implementation
        // In production, you might want to use Redis or more sophisticated rate limiting
        $lastRequestKey = "citation_api_last_request:{$this->apiName}";
        $lastRequest = cache($lastRequestKey);

        if ($lastRequest) {
            $timeSinceLastRequest = microtime(true) - $lastRequest;
            $minInterval = $this->rateLimits['min_interval'] ?? 0;

            if ($timeSinceLastRequest < $minInterval) {
                $sleepTime = $minInterval - $timeSinceLastRequest;
                usleep((int) ($sleepTime * 1000000)); // Convert to microseconds
            }
        }

        cache([$lastRequestKey => microtime(true)], now()->addMinutes(5));
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $endpoint, array $params): string
    {
        return CitationCache::generateKey($this->apiName, $endpoint, $params);
    }

    /**
     * Normalize citation data to standard format
     */
    abstract protected function normalizeCitation(array $data): array;

    /**
     * Extract DOI from various formats
     */
    protected function extractDoi(string $doiString): ?string
    {
        // Remove URL prefix if present
        $doiString = preg_replace('/^https?:\/\/(dx\.)?doi\.org\//', '', $doiString);

        // Validate DOI format
        if (preg_match('/^10\.\d+\/\S+$/', $doiString)) {
            return $doiString;
        }

        return null;
    }

    /**
     * Clean author names
     */
    protected function cleanAuthorName(string $name): string
    {
        // Remove extra whitespace and normalize
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);

        return $name;
    }

    /**
     * Extract year from date string
     */
    protected function extractYear(string $dateString): ?int
    {
        if (preg_match('/(\d{4})/', $dateString, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Get API status
     */
    public function getStatus(): array
    {
        return [
            'api_name' => $this->apiName,
            'base_url' => $this->baseUrl,
            'cache_hours' => $this->cacheHours,
            'rate_limits' => $this->rateLimits,
        ];
    }
}
