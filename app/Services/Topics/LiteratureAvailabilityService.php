<?php

namespace App\Services\Topics;

use App\Services\PaperCollectionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service to check literature availability for research topics.
 * 
 * Leverages existing PaperCollectionService to search academic databases
 * and calculate a literature availability score for topics.
 */
class LiteratureAvailabilityService
{
    /**
     * Minimum papers needed for "moderate" availability
     */
    protected const MIN_MODERATE = 5;

    /**
     * Minimum papers needed for "good" availability
     */
    protected const MIN_GOOD = 15;

    /**
     * Cache duration in hours
     */
    protected const CACHE_HOURS = 24;

    public function __construct(
        protected PaperCollectionService $paperCollectionService,
    ) {}

    /**
     * Check literature availability for a given topic.
     *
     * @param string $topic The research topic to check
     * @param string|null $field Optional field of study for context
     * @return array{score: int, count: int, quality: string, sources: array, cached: bool}
     */
    public function checkAvailability(string $topic, ?string $field = null): array
    {
        $cacheKey = $this->buildCacheKey($topic, $field);
        
        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            Log::debug("Literature availability cache hit for topic: {$topic}");
            return array_merge($cached, ['cached' => true]);
        }

        Log::info("Checking literature availability for topic: {$topic}", [
            'field' => $field,
        ]);

        try {
            // Collect papers using existing service
            $papers = $this->collectPapersForTopic($topic, $field);
            
            // Calculate availability metrics
            $count = $papers->count();
            $score = $this->calculateScore($papers);
            $quality = $this->determineQuality($count, $score);
            $sources = $this->countSources($papers);

            $result = [
                'score' => $score,
                'count' => $count,
                'quality' => $quality,
                'sources' => $sources,
                'cached' => false,
            ];

            // Cache the result
            Cache::put($cacheKey, $result, now()->addHours(self::CACHE_HOURS));

            Log::info("Literature availability check complete for: {$topic}", [
                'score' => $score,
                'count' => $count,
                'quality' => $quality,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Literature availability check failed for: {$topic}", [
                'error' => $e->getMessage(),
            ]);

            // Return fallback values on error
            return [
                'score' => 50, // Neutral score
                'count' => 0,
                'quality' => 'unknown',
                'sources' => [],
                'cached' => false,
                'error' => true,
            ];
        }
    }

    /**
     * Check availability for multiple topics in batch.
     *
     * @param array $topics Array of topic strings
     * @param string|null $field Optional field of study
     * @param callable|null $progressCallback Optional callback for progress updates
     * @return array<string, array> Map of topic => availability data
     */
    public function checkMultiple(array $topics, ?string $field = null, ?callable $progressCallback = null): array
    {
        $results = [];
        $total = count($topics);

        foreach ($topics as $index => $topic) {
            $results[$topic] = $this->checkAvailability($topic, $field);

            if ($progressCallback) {
                $progressCallback($index + 1, $total, $topic);
            }
        }

        return $results;
    }

    /**
     * Collect papers for a topic using the paper collection service.
     */
    protected function collectPapersForTopic(string $topic, ?string $field = null): Collection
    {
        $papers = collect();

        try {
            // Collect from OpenAlex (broad coverage, reliable)
            $openAlexPapers = $this->paperCollectionService->collectFromOpenAlex($topic);
            $papers = $papers->merge($openAlexPapers);
        } catch (\Exception $e) {
            Log::warning("OpenAlex collection failed for literature check: {$e->getMessage()}");
        }

        try {
            // Collect from Semantic Scholar
            $semanticPapers = $this->paperCollectionService->collectFromSemanticScholar($topic);
            $papers = $papers->merge($semanticPapers);
        } catch (\Exception $e) {
            Log::warning("Semantic Scholar collection failed for literature check: {$e->getMessage()}");
        }

        try {
            // Collect from CrossRef
            $crossRefPapers = $this->paperCollectionService->collectFromCrossRef($topic);
            $papers = $papers->merge($crossRefPapers);
        } catch (\Exception $e) {
            Log::warning("CrossRef collection failed for literature check: {$e->getMessage()}");
        }

        // Deduplicate
        return $this->paperCollectionService->deduplicateAndRank($papers);
    }

    /**
     * Calculate an availability score (0-100) based on collected papers.
     */
    protected function calculateScore(Collection $papers): int
    {
        $count = $papers->count();
        
        if ($count === 0) {
            return 0;
        }

        // Base score from paper count (up to 50 points)
        // 20+ papers = full 50 points
        $countScore = min(50, ($count / 20) * 50);

        // Quality score from average paper quality (up to 30 points)
        $avgQuality = $papers->avg('quality_score') ?? 0;
        $qualityScore = $avgQuality * 30;

        // Recency bonus (up to 10 points) - papers from last 5 years
        $currentYear = (int) date('Y');
        $recentPapers = $papers->filter(function ($paper) use ($currentYear) {
            $year = $paper['year'] ?? 0;
            return $year >= $currentYear - 5;
        })->count();
        $recencyScore = min(10, ($recentPapers / max(1, $count)) * 10);

        // Open access bonus (up to 10 points)
        $openAccessPapers = $papers->filter(function ($paper) {
            return $paper['is_open_access'] ?? false;
        })->count();
        $openAccessScore = min(10, ($openAccessPapers / max(1, $count)) * 10);

        $totalScore = $countScore + $qualityScore + $recencyScore + $openAccessScore;

        return (int) round(min(100, max(0, $totalScore)));
    }

    /**
     * Determine the quality level based on count and score.
     */
    protected function determineQuality(int $count, int $score): string
    {
        if ($count === 0) {
            return 'none';
        }

        if ($count < self::MIN_MODERATE || $score < 30) {
            return 'low';
        }

        if ($count < self::MIN_GOOD || $score < 60) {
            return 'moderate';
        }

        return 'good';
    }

    /**
     * Count papers by source API.
     */
    protected function countSources(Collection $papers): array
    {
        return $papers->groupBy('source')
            ->map(fn ($group) => $group->count())
            ->toArray();
    }

    /**
     * Build a cache key for the topic/field combination.
     */
    protected function buildCacheKey(string $topic, ?string $field = null): string
    {
        $normalized = strtolower(trim($topic));
        $fieldPart = $field ? '_' . strtolower(trim($field)) : '';
        
        return 'literature_availability_' . md5($normalized . $fieldPart);
    }

    /**
     * Clear cached literature data for a specific topic.
     */
    public function clearCache(string $topic, ?string $field = null): void
    {
        Cache::forget($this->buildCacheKey($topic, $field));
    }

    /**
     * Get a human-readable description of the quality level.
     */
    public static function getQualityDescription(string $quality): string
    {
        return match ($quality) {
            'none' => 'No literature found',
            'low' => 'Limited literature available - topic may need refinement',
            'moderate' => 'Moderate literature available - viable topic',
            'good' => 'Excellent literature coverage - strong topic choice',
            default => 'Literature availability unknown',
        };
    }
}
