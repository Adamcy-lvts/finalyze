<?php

namespace App\Services\Topics;

use App\Models\Project;
use App\Models\ProjectTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TopicCacheService
{
    public function __construct(
        private TopicTextService $textService,
    ) {
        //
    }

    public function getCachedTopicsForAcademicContext(Project $project): array
    {
        $context = $this->getProjectAcademicContext($project);
        $faculty = $context['faculty'];
        $department = $context['department'];

        $cachedTopics = ProjectTopic::where('course', $context['course'])
            ->where('academic_level', $context['academic_level'])
            ->where('university', $context['university'])
            ->when($faculty, fn ($q) => $q->where('faculty', $faculty))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->when($context['field_of_study'], fn ($q) => $q->where('field_of_study', $context['field_of_study']))
            ->limit(10)
            ->get()
            ->map(function ($topic) {
                return [
                    'topic' => $topic->title,
                    'title' => $topic->title,
                    'description' => $this->textService->cleanTopicDescription($topic->description ?? 'Research topic in '.$topic->field_of_study),
                    'difficulty' => $topic->difficulty,
                    'timeline' => $topic->timeline,
                    'resource_level' => $topic->resource_level,
                    'feasibility_score' => $topic->feasibility_score,
                    'keywords' => $topic->keywords ?? [],
                    'research_type' => $topic->research_type,
                ];
            })
            ->toArray();

        Log::info('Retrieved cached topics for academic context', [
            'project_id' => $project->id,
            'course' => $context['course'],
            'university' => $context['university'],
            'faculty' => $faculty,
            'department' => $department,
            'cached_topics_count' => count($cachedTopics),
        ]);

        return $cachedTopics;
    }

    public function storeTopicsInDatabase(array $topics, Project $project): void
    {
        try {
            $context = $this->getProjectAcademicContext($project);
            $requiredContextFields = ['faculty', 'department', 'course', 'university', 'academic_level'];
            $missingFields = array_filter($requiredContextFields, fn ($field) => empty($context[$field]));

            if (! empty($missingFields)) {
                Log::warning('Skipping topic storage due to missing academic context', [
                    'project_id' => $project->id,
                    'missing_fields' => array_values($missingFields),
                    'context' => $context,
                ]);

                return;
            }

            foreach ($topics as $topic) {
                $topicData = match (true) {
                    is_string($topic) => [
                        'title' => $topic,
                        'description' => 'Research topic in '.($project->field_of_study ?? $project->course),
                        'difficulty' => 'moderate',
                        'timeline' => '6-9 months',
                        'resource_level' => 'medium',
                        'resourceLevel' => 'medium',
                        'feasibility_score' => 75,
                        'feasibilityScore' => 75,
                        'keywords' => [],
                        'research_type' => 'applied',
                        'researchType' => 'applied',
                    ],
                    is_array($topic) => $topic,
                    default => null,
                };

                if (! $topicData) {
                    continue;
                }

                $difficulty = $topicData['difficulty'] ?? $topicData['difficulty_level'] ?? 'moderate';
                $timeline = $topicData['timeline'] ?? $topicData['duration'] ?? '6-9 months';
                $resourceLevel = $topicData['resource_level'] ?? $topicData['resourceLevel'] ?? 'medium';
                $feasibilityScore = $topicData['feasibility_score'] ?? $topicData['feasibilityScore'] ?? null;
                $feasibilityScore = $feasibilityScore !== null ? (int) $feasibilityScore : 75;
                $researchType = $topicData['research_type'] ?? $topicData['researchType'] ?? 'applied';
                $keywords = $topicData['keywords'] ?? [];

                $titleHtml = $this->textService->convertMarkdownToHtml($topicData['title']);

                $existingTopic = ProjectTopic::where('title', $titleHtml)
                    ->where('course', $context['course'])
                    ->where('academic_level', $context['academic_level'])
                    ->first();

                if (! $existingTopic) {
                    $description = $topicData['description'] ?? 'Research topic in '.($project->field_of_study ?? $project->course);
                    $descriptionHtml = $this->textService->convertMarkdownToHtml($description);

                    ProjectTopic::create([
                        'user_id' => $project->user_id,
                        'project_id' => $project->id,
                        'field_of_study' => $context['field_of_study'],
                        'faculty' => $context['faculty'],
                        'department' => $context['department'],
                        'course' => $context['course'],
                        'university' => $context['university'],
                        'academic_level' => $context['academic_level'],
                        'title' => $titleHtml,
                        'description' => $descriptionHtml,
                        'difficulty' => $difficulty,
                        'timeline' => $timeline,
                        'resource_level' => $resourceLevel,
                        'feasibility_score' => $feasibilityScore,
                        'keywords' => $keywords,
                        'research_type' => $researchType,
                        'selection_count' => 0,
                        'last_selected_at' => null,
                    ]);
                }
            }

            Log::info('Topics stored in database', [
                'project_id' => $project->id,
                'topics_count' => count($topics),
                'course' => $context['course'],
                'university' => $context['university'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store topics in database', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function hasRecentTopicRequest(Project $project): bool
    {
        $userId = auth()->id();
        if (! $userId) {
            Log::info('No authenticated user for recent request check');

            return false;
        }

        $academicContextHash = $this->generateAcademicContextHash($project);
        $now = now();
        $fiveMinutesAgo = $now->copy()->subMinutes(5);
        $ninetySecondsAgo = $now->copy()->subSeconds(90);

        Log::info('Checking for recent topic request', [
            'user_id' => $userId,
            'project_id' => $project->id,
            'academic_context_hash' => $academicContextHash,
            'current_time' => $now->toDateTimeString(),
            'window_start' => $fiveMinutesAgo->toDateTimeString(),
            'window_end' => $ninetySecondsAgo->toDateTimeString(),
        ]);

        $veryRecentRequest = DB::table('user_topic_requests')
            ->where('user_id', $userId)
            ->where('academic_context_hash', $academicContextHash)
            ->where('created_at', '>', $ninetySecondsAgo)
            ->orderBy('created_at', 'desc')
            ->first();

        $olderRequest = DB::table('user_topic_requests')
            ->where('user_id', $userId)
            ->where('academic_context_hash', $academicContextHash)
            ->where('created_at', '<', $fiveMinutesAgo)
            ->orderBy('created_at', 'desc')
            ->first();

        $hasRecentRequest = $olderRequest !== null && $veryRecentRequest === null;

        Log::info('Recent request check result', [
            'has_recent_request' => $hasRecentRequest,
            'very_recent_request' => $veryRecentRequest ? $veryRecentRequest->created_at : null,
            'older_request' => $olderRequest ? $olderRequest->created_at : null,
            'decision_reason' => $hasRecentRequest ? 'User had time to review (>5min) and not spam clicking (<90sec)' : 'No qualifying requests found',
        ]);

        return $hasRecentRequest;
    }

    public function trackTopicRequest(Project $project): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $academicContextHash = $this->generateAcademicContextHash($project);

        DB::table('user_topic_requests')->insert([
            'user_id' => $userId,
            'project_id' => $project->id,
            'academic_context_hash' => $academicContextHash,
            'request_metadata' => json_encode([
                'course' => $project->course,
                'university' => $project->universityRelation?->name,
                'academic_level' => $project->type,
                'field_of_study' => $project->field_of_study,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Tracked topic request', [
            'user_id' => $userId,
            'project_id' => $project->id,
            'academic_context_hash' => $academicContextHash,
        ]);
    }

    public function getProjectAcademicContext(Project $project): array
    {
        $university = $project->universityRelation?->name ?? $project->university;
        $faculty = $project->facultyRelation?->name ?? $project->faculty;
        $department = $project->departmentRelation?->name ?? $project->settings['department'] ?? null;

        return [
            'field_of_study' => $project->field_of_study,
            'course' => $project->course,
            'academic_level' => $project->type,
            'university' => $university,
            'faculty' => $faculty,
            'department' => $department,
        ];
    }

    private function generateAcademicContextHash(Project $project): string
    {
        $contextData = $this->getProjectAcademicContext($project);

        return hash('sha256', json_encode($contextData));
    }
}
