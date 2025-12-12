<?php

namespace App\Services\Topics;

use App\Models\Project;
use App\Services\AIContentGenerator;
use Illuminate\Support\Facades\Log;

class TopicGenerationService
{
    public function __construct(
        private AIContentGenerator $aiGenerator,
        private TopicCacheService $cacheService,
        private TopicPromptBuilder $promptBuilder,
        private TopicParser $parser,
    ) {
        //
    }

    /**
     * Generate raw topic strings with caching/fallback.
     * Returns array with keys: topics, from_cache, word_count.
     */
    public function generateTopicsWithAI(Project $project, ?string $geographicFocus = null): array
    {
        $geographicFocus = $geographicFocus ?: 'balanced';

        $cachedTopics = $this->cacheService->getCachedTopicsForAcademicContext($project, $geographicFocus);
        $recentTopicRequest = $this->cacheService->hasRecentTopicRequest($project, $geographicFocus);

        if (! $recentTopicRequest && count($cachedTopics) >= 8) {
            Log::info('Using cached topics for academic context', [
                'project_id' => $project->id,
                'course' => $project->course,
                'university' => $project->universityRelation?->name,
                'cached_count' => count($cachedTopics),
                'geographic_focus' => $geographicFocus,
            ]);

            $this->cacheService->trackTopicRequest($project, $geographicFocus);

            return [
                'topics' => collect($cachedTopics)->pluck('topic')->toArray(),
                'from_cache' => true,
                'word_count' => 0,
            ];
        }

        $academicContext = $this->cacheService->getProjectAcademicContext($project, $geographicFocus);

        if (! $this->aiGenerator->isAvailable()) {
            Log::warning('AI unavailable - skipping new topic generation', [
                'project_id' => $project->id,
                'cached_count' => count($cachedTopics),
                'academic_context' => $academicContext,
            ]);

            if (count($cachedTopics) > 0) {
                return [
                    'topics' => collect($cachedTopics)
                        ->map(fn ($topic) => $topic['topic'] ?? $topic['title'] ?? $topic)
                        ->filter()
                        ->values()
                        ->toArray(),
                    'from_cache' => true,
                    'word_count' => 0,
                ];
            }

            throw new \Exception('AI services are currently unavailable. Please try again when back online.');
        }

        Log::info('Generating fresh topics', [
            'project_id' => $project->id,
            'reason' => $recentTopicRequest ? 'Recent request detected - user wants fresh ideas' : 'Insufficient cached topics',
            'cached_count' => count($cachedTopics),
            'geographic_focus' => $geographicFocus,
        ]);

        $this->cacheService->trackTopicRequest($project, $geographicFocus);

        try {
            $startTime = microtime(true);

            Log::info('AI Topic Generation - Starting with intelligent selection', [
                'project_id' => $project->id,
                'academic_context' => $academicContext,
                'timestamp' => now()->toDateTimeString(),
            ]);

            $systemPrompt = $this->promptBuilder->buildSystemPrompt($project, $geographicFocus);
            $userPrompt = $this->promptBuilder->buildContextualPrompt($project, $geographicFocus);
            $fullPrompt = $systemPrompt."\n\n".$userPrompt;

            $aiStartTime = microtime(true);
            $generatedContent = '';
            $chunkCount = 0;
            $timeout = 180;

            foreach ($this->aiGenerator->generateTopicsOptimized($fullPrompt, $academicContext) as $chunk) {
                $generatedContent .= $chunk;
                $chunkCount++;

                if ((microtime(true) - $aiStartTime) > $timeout) {
                    Log::warning('AI generation timeout, stopping early', [
                        'project_id' => $project->id,
                        'chunks_received' => $chunkCount,
                        'elapsed_time' => microtime(true) - $aiStartTime,
                    ]);
                    break;
                }
            }

            $aiEndTime = microtime(true);
            $aiDuration = ($aiEndTime - $aiStartTime) * 1000;

            Log::info('AI Topic Generation - Intelligent generation completed', [
                'project_id' => $project->id,
                'ai_response_time_ms' => round($aiDuration, 2),
                'active_provider' => $this->aiGenerator->getActiveProvider()?->getName(),
                'academic_context' => $academicContext,
            ]);

            $parseStartTime = microtime(true);
            $newTopics = $this->parser->parseAndValidate($generatedContent, $project);
            $parseEndTime = microtime(true);
            $parseDuration = ($parseEndTime - $parseStartTime) * 1000;

            $wordCount = str_word_count(strip_tags($generatedContent));

            $dbStartTime = microtime(true);
            $this->cacheService->storeTopicsInDatabase($newTopics, $project, $geographicFocus);
            $dbDuration = (microtime(true) - $dbStartTime) * 1000;

            $totalDuration = (microtime(true) - $startTime) * 1000;

            Log::info('AI Topic Generation - Complete Cycle', [
                'project_id' => $project->id,
                'total_time_ms' => round($totalDuration, 2),
                'ai_time_ms' => round($aiDuration, 2),
                'parsing_time_ms' => round($parseDuration, 2),
                'db_storage_time_ms' => round($dbDuration, 2),
                'topics_generated' => count($newTopics),
                'ai_percentage' => round(($aiDuration / $totalDuration) * 100, 1).'%',
                'timestamp' => now()->toDateTimeString(),
                'word_count' => $wordCount,
            ]);

            return [
                'topics' => $newTopics,
                'from_cache' => false,
                'word_count' => $wordCount,
            ];

        } catch (\Exception $e) {
            Log::error('AI Topic Generation Failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            if (count($cachedTopics) > 0) {
                return [
                    'topics' => collect($cachedTopics)->map(function ($topic) {
                        return $topic->title;
                    })->toArray(),
                    'from_cache' => true,
                    'word_count' => 0,
                ];
            }

            return [
                'topics' => $this->parser->generateEnhancedMockTopics($project),
                'from_cache' => true,
                'word_count' => 0,
            ];
        }
    }
}
