<?php

namespace App\Services\Topics;

use App\Models\Project;
use App\Services\AIContentGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class TopicEnrichmentService
{
    public function __construct(
        private AIContentGenerator $aiGenerator,
        private TopicPromptBuilder $promptBuilder,
        private TopicCacheService $cacheService,
        private TopicTextService $textService,
    ) {
        //
    }

    public function enrich(array $topics, Project $project, ?string $geographicFocus = null, ?callable $progressCallback = null): array
    {
        $geographicFocus = $geographicFocus ?: 'balanced';
        $enrichedTopics = [];

        foreach ($topics as $index => $topic) {
            $titleHtml = $this->textService->convertMarkdownToHtml($topic);

            $metadata = $this->analyzeTopicMetadata($topic, $project);
            $description = $this->generateTopicDescription($topic, $project);

            $enrichedTopics[] = [
                'id' => $index + 1,
                'title' => $titleHtml,
                'description' => $this->textService->convertMarkdownToHtml($description),
                'difficulty' => $metadata['difficulty'],
                'timeline' => $metadata['timeline'],
                'resource_level' => $metadata['resource_level'],
                'feasibility_score' => $metadata['feasibility_score'],
                'keywords' => $metadata['keywords'],
                'research_type' => $metadata['research_type'],
            ];

            if ($progressCallback) {
                $progressCallback([
                    'current' => $index + 1,
                    'title' => $topic,
                ]);
            }
        }

        $this->cacheService->storeTopicsInDatabase($enrichedTopics, $project, $geographicFocus);

        return $enrichedTopics;
    }

    private function analyzeTopicMetadata(string $topic, Project $project): array
    {
        try {
            return $this->analyzeTopicMetadataWithAI($topic, $project);
        } catch (\Exception $e) {
            Log::warning('AI Topic Metadata Analysis Failed - Using Fallback', [
                'topic' => $topic,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            return $this->analyzeTopicMetadataRuleBased($topic, $project);
        }
    }

    private function analyzeTopicMetadataWithAI(string $topic, Project $project): array
    {
        $startTime = microtime(true);

        Log::info('AI Topic Metadata Analysis - Starting', [
            'topic' => Str::limit($topic, 50).'...',
            'project_id' => $project->id,
            'timestamp' => now()->toDateTimeString(),
        ]);

        $academicLevel = $this->promptBuilder->getAcademicLevelDescription($project->type);
        $categoryName = $project->category->name ?? 'Final Year Project';

        $analysisPrompt = "Analyze this research topic for academic feasibility and requirements:

TOPIC: {$topic}

CONTEXT:
- Academic Level: {$academicLevel}
- Project Type: {$categoryName}
- Field of Study: {$project->field_of_study}
- University Setting: Nigerian university environment

ANALYSIS REQUIRED:
Assess difficulty, timeline, resource requirements, feasibility score (60-100), research type, and extract 3-5 key terms.

Respond with ONLY this JSON format (no additional text):
{
    \"difficulty\": \"Beginner Friendly|Intermediate|Advanced\",
    \"timeline\": \"6-9 months|9-12 months|12+ months\",
    \"resource_level\": \"Low|Medium|High\",
    \"feasibility_score\": 85,
    \"research_type\": \"Applied Research|Theoretical Research|Analytical Study|Comparative Study\",
    \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"]
}";

        $aiStartTime = microtime(true);
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert academic research advisor specializing in project feasibility analysis. Return only valid JSON with no additional text or formatting.'],
                ['role' => 'user', 'content' => $analysisPrompt],
            ],
            'temperature' => 0.2,
            'max_tokens' => 300,
        ]);
        $aiEndTime = microtime(true);
        $aiDuration = ($aiEndTime - $aiStartTime) * 1000;

        $generatedContent = trim($response->choices[0]->message->content);
        $generatedContent = preg_replace('/```json|```/', '', $generatedContent);
        $generatedContent = trim($generatedContent);

        $analysisData = json_decode($generatedContent, true);

        if (! $analysisData || json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from AI analysis: '.json_last_error_msg());
        }

        foreach (['difficulty', 'timeline', 'resource_level', 'feasibility_score', 'research_type', 'keywords'] as $field) {
            if (! isset($analysisData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        $analysisData['feasibility_score'] = max(60, min(100, (int) $analysisData['feasibility_score']));

        if (! is_array($analysisData['keywords'])) {
            $analysisData['keywords'] = [];
        }

        $totalDuration = (microtime(true) - $startTime) * 1000;

        Log::info('AI Topic Metadata Analysis Success', [
            'topic' => substr($topic, 0, 50).'...',
            'project_id' => $project->id,
            'analysis' => $analysisData,
            'total_time_ms' => round($totalDuration, 2),
            'ai_time_ms' => round($aiDuration, 2),
            'processing_time_ms' => round($totalDuration - $aiDuration, 2),
            'ai_percentage' => round(($aiDuration / $totalDuration) * 100, 1).'%',
            'tokens_used' => $response->usage->totalTokens ?? 'unknown',
            'prompt_tokens' => $response->usage->promptTokens ?? 'unknown',
            'completion_tokens' => $response->usage->completionTokens ?? 'unknown',
            'timestamp' => now()->toDateTimeString(),
        ]);

        return $analysisData;
    }

    private function analyzeTopicMetadataRuleBased(string $topic, Project $project): array
    {
        $topicLower = strtolower($topic);

        $difficulty = $this->analyzeDifficulty($topicLower, $project);
        $timeline = $this->analyzeTimeline($topicLower, $difficulty);
        $resourceLevel = $this->analyzeResourceRequirements($topicLower);
        $feasibilityScore = $this->calculateFeasibilityScore($difficulty, $resourceLevel, $project);
        $keywords = $this->extractKeywords($topic);
        $researchType = $this->determineResearchType($topicLower);

        Log::info('Rule-Based Topic Metadata Analysis Used', [
            'topic' => substr($topic, 0, 50).'...',
            'project_id' => $project->id,
            'method' => 'fallback',
        ]);

        return [
            'difficulty' => $difficulty,
            'timeline' => $timeline,
            'resource_level' => $resourceLevel,
            'feasibility_score' => $feasibilityScore,
            'keywords' => $keywords,
            'research_type' => $researchType,
        ];
    }

    private function analyzeDifficulty(string $topicLower, Project $project): string
    {
        $complexIndicators = ['machine learning', 'artificial intelligence', 'blockchain', 'neural network',
            'deep learning', 'quantum', 'advanced', 'complex', 'sophisticated'];
        $moderateIndicators = ['development', 'implementation', 'analysis', 'design', 'system'];
        $basicIndicators = ['study', 'survey', 'review', 'basic', 'simple'];

        foreach ($complexIndicators as $indicator) {
            if (str_contains($topicLower, $indicator)) {
                return strtolower($project->type) === 'phd' ? 'Advanced' : 'Intermediate';
            }
        }

        foreach ($moderateIndicators as $indicator) {
            if (str_contains($topicLower, $indicator)) {
                return 'Intermediate';
            }
        }

        foreach ($basicIndicators as $indicator) {
            if (str_contains($topicLower, $indicator)) {
                return 'Beginner Friendly';
            }
        }

        return 'Intermediate';
    }

    private function analyzeTimeline(string $topicLower, string $difficulty): string
    {
        $longTermIndicators = ['comprehensive', 'development', 'implementation', 'framework'];

        if ($difficulty === 'Advanced') {
            return '12+ months';
        }

        foreach ($longTermIndicators as $indicator) {
            if (str_contains($topicLower, $indicator)) {
                return '9-12 months';
            }
        }

        return '6-9 months';
    }

    private function analyzeResourceRequirements(string $topicLower): string
    {
        $highResourceIndicators = ['system', 'platform', 'infrastructure', 'hardware', 'equipment'];
        $mediumResourceIndicators = ['software', 'application', 'tool', 'prototype'];

        foreach ($highResourceIndicators as $indicator) {
            if (str_contains($topicLower, $indicator)) {
                return 'High';
            }
        }

        foreach ($mediumResourceIndicators as $indicator) {
            if (str_contains($topicLower, $indicator)) {
                return 'Medium';
            }
        }

        return 'Low';
    }

    private function calculateFeasibilityScore(string $difficulty, string $resourceLevel, Project $project): int
    {
        $score = 100;

        if ($difficulty === 'Advanced') {
            $score -= 20;
        }
        if ($difficulty === 'Intermediate') {
            $score -= 10;
        }

        if ($resourceLevel === 'High') {
            $score -= 15;
        }
        if ($resourceLevel === 'Medium') {
            $score -= 5;
        }

        $academicLevel = strtolower($project->type);
        if ($academicLevel === 'undergraduate' && $difficulty === 'Advanced') {
            $score -= 25;
        }

        return max(60, min(100, $score));
    }

    private function extractKeywords(string $topic): array
    {
        $commonWords = ['a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $words = array_filter(
            array_map('trim', explode(' ', strtolower($topic))),
            fn ($word) => strlen($word) > 3 && ! in_array($word, $commonWords)
        );

        return array_slice(array_unique($words), 0, 5);
    }

    private function determineResearchType(string $topicLower): string
    {
        if (str_contains($topicLower, 'development') || str_contains($topicLower, 'implementation') || str_contains($topicLower, 'design')) {
            return 'Applied Research';
        }

        if (str_contains($topicLower, 'analysis') || str_contains($topicLower, 'evaluation') || str_contains($topicLower, 'assessment')) {
            return 'Analytical Study';
        }

        if (str_contains($topicLower, 'comparative') || str_contains($topicLower, 'comparison')) {
            return 'Comparative Study';
        }

        return 'Theoretical Research';
    }

    private function generateTopicDescription(string $topic, Project $project): string
    {
        $startTime = microtime(true);

        Log::info('AI Topic Description Generation - Starting', [
            'topic' => Str::limit($topic, 50).'...',
            'project_id' => $project->id,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            $systemPrompt = "You are an expert academic advisor. Generate a clear, concise description (2-3 sentences) that helps students understand what this research topic involves and why it's valuable.

Requirements:
- Explain what the research would involve in simple terms
- Highlight the practical applications or benefits
- Keep it student-friendly and motivating
- Focus on learning outcomes and real-world relevance
- Write entirely in the third-person perspective (e.g., \"the student will\", \"this project explores\"). Never address the reader as \"you\" or \"your\".
- Maximum 150 words
- DO NOT use any headers, titles, or prefixes (e.g., \"Description:\", \"## Research Topic\"). Return ONLY the paragraph text.";

            $userPrompt = "Generate a description for this research topic:

TOPIC: {$topic}

CONTEXT:
- Field of Study: {$project->field_of_study}
- Course: {$project->course}
- University: {$project->full_university_name}
- Academic Level: ".ucfirst($project->type).'

The description should help a student understand what this topic involves and why they should consider choosing it.';

            $fullPrompt = $systemPrompt."\n\n".$userPrompt;

            $academicContext = [
                'field_of_study' => $project->field_of_study,
                'academic_level' => $project->type,
                'faculty' => $project->facultyRelation?->name ?? '',
            ];

            $aiStartTime = microtime(true);
            $description = $this->aiGenerator->generateTopicDescriptionOptimized($fullPrompt, $academicContext);
            $aiEndTime = microtime(true);
            $aiDuration = ($aiEndTime - $aiStartTime) * 1000;

            $description = $this->enforceThirdPersonPerspective(trim($description));
            $description = preg_replace('/^#+\s*Description.*$/m', '', $description);
            $description = preg_replace('/^Description:?\s*/i', '', $description);
            $description = preg_replace('/^Research Topic Description:?\s*/i', '', $description);

            $totalDuration = (microtime(true) - $startTime) * 1000;

            Log::info('AI Topic Description Generation - Success', [
                'topic' => Str::limit($topic, 50).'...',
                'project_id' => $project->id,
                'description_length' => strlen($description),
                'description_preview' => Str::limit($description, 100).'...',
                'total_time_ms' => round($totalDuration, 2),
                'ai_time_ms' => round($aiDuration, 2),
                'ai_percentage' => round(($aiDuration / $totalDuration) * 100, 1).'%',
                'timestamp' => now()->toDateTimeString(),
            ]);

            return $description;

        } catch (\Exception $e) {
            $failedDuration = (microtime(true) - $startTime) * 1000;

            Log::warning('AI Topic Description Generation Failed', [
                'topic' => $topic,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'failed_after_ms' => round($failedDuration, 2),
                'timestamp' => now()->toDateTimeString(),
            ]);

            $fallback = "This research topic focuses on {$project->field_of_study} and involves investigating current trends, methodologies, and practical applications in the field. The study provides valuable insights and contributes to academic knowledge while helping the student strengthen research and analytical skills.";

            return $this->enforceThirdPersonPerspective($fallback);
        }
    }

    private function enforceThirdPersonPerspective(string $text): string
    {
        $replacements = [
            'you are' => 'the student is',
            'you were' => 'the student was',
            'you have' => 'the student has',
            'you will' => 'the student will',
            "you're" => 'the student is',
            "you've" => 'the student has',
            "you'll" => 'the student will',
            'yourself' => 'the student',
            'yourselves' => 'the students',
            'your' => "the student's",
            'yours' => "the student's",
            'you' => 'the student',
        ];

        foreach ($replacements as $search => $replacement) {
            $pattern = '/\b'.preg_quote($search, '/').'\b/i';
            $text = preg_replace_callback($pattern, function ($matches) use ($replacement) {
                $matchedText = $matches[0];

                if (mb_strtoupper($matchedText) === $matchedText) {
                    return mb_strtoupper($replacement);
                }

                if (mb_substr($matchedText, 0, 1) === mb_strtoupper(mb_substr($matchedText, 0, 1))) {
                    return ucfirst($replacement);
                }

                return $replacement;
            }, $text);
        }

        return preg_replace('/\s+/', ' ', $text ?? '') ?? '';
    }
}
