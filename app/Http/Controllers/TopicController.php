<?php

namespace App\Http\Controllers;

use App\Actions\Topics\ExportTopicPdfAction;
use App\Http\Controllers\Concerns\TopicTextHelpers;
use App\Jobs\GenerateProjectOutline;
use App\Models\Project;
use App\Models\ProjectTopic;
use App\Models\User;
use App\Services\AIContentGenerator;
use App\Services\ProjectOutlineService;
use App\Services\Topics\TopicCacheService;
use App\Services\Topics\TopicEnrichmentService;
use App\Services\Topics\TopicGenerationService;
use App\Services\Topics\TopicLibraryService;
use App\Services\Topics\TopicParser;
use App\Services\Topics\TopicPromptBuilder;
use App\Services\WordBalanceService;
use App\Transformers\TopicTransformer;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

// Add request logging
if (! app()->isProduction()) {
    \Illuminate\Support\Facades\Log::info('TopicController loaded', [
        'time' => now()->toDateTimeString(),
        'url' => request()->fullUrl(),
        'method' => request()->method(),
        'route' => request()->route()?->getName(),
    ]);
}
use App\Http\Requests\Topics\ApproveTopicRequest;
use App\Http\Requests\Topics\GenerateTopicsRequest;
use App\Http\Requests\Topics\SelectTopicRequest;
use App\Http\Requests\Topics\StreamTopicsRequest;
use App\Http\Requests\Topics\TopicIndexRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    use TopicTextHelpers;

    public function __construct(
        private AIContentGenerator $aiGenerator,
        private ProjectOutlineService $outlineService,
        private TopicLibraryService $topicLibraryService,
        private WordBalanceService $wordBalanceService,
        private TopicGenerationService $topicGenerationService,
        private TopicEnrichmentService $topicEnrichmentService,
        private TopicCacheService $topicCacheService,
        private TopicPromptBuilder $topicPromptBuilder,
        private TopicParser $topicParser,
        private ExportTopicPdfAction $exportTopicPdfAction,
    ) {
        //
    }

    /**
     * Topic library for the authenticated user - shows ALL generated topics.
     */
    public function topicsIndex(TopicIndexRequest $request)
    {
        $user = $request->user();
        $limit = $request->integer('limit') ?: 100;
        $page = $request->integer('page') ?: 1;

        $projects = Project::where('user_id', $user->id)
            ->select([
                'id',
                'slug',
                'title',
                'topic',
                'topic_status',
                'status',
                'type',
                'course',
                'field_of_study',
                'created_at',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $allTopics = $this->topicLibraryService
            ->getAllTopics($limit, $page) // cap to keep payload reasonable
            ->map(function (ProjectTopic $topic) {
                $payload = TopicTransformer::toArray($topic);
                $payload['description'] = $this->cleanTopicDescription(
                    $this->convertMarkdownToHtml(
                        $payload['description'] ?? 'Research topic in '.$payload['field_of_study']
                    )
                );

                return $payload;
            })
            ->toArray();

        $totalTopics = $this->topicLibraryService->countAllTopics();

        // Build projectTopics structure for UI compatibility
        $projectTopics = $projects->map(function (Project $project) use ($allTopics) {
            return [
                'project' => [
                    'id' => $project->id,
                    'slug' => $project->slug,
                    'title' => $project->title ?? 'Untitled Project',
                    'topic' => $project->topic,
                    'topic_status' => $project->topic_status,
                    'status' => $project->status,
                    'type' => $project->type,
                    'course' => $project->course,
                    'field_of_study' => $project->field_of_study,
                    'created_at' => optional($project->created_at)->toIso8601String(),
                ],
                'topics' => $allTopics, // All topics shown under each project
            ];
        });

        // Only keep first project with all topics to avoid duplication in UI
        // or return all topics flat if preferred
        $firstProject = $projectTopics->first();

        // Fetch active faculties for filter tabs
        $facultyQuery = \App\Models\Faculty::query()->orderBy('name');

        // Only apply the active scope when the column exists to avoid hidden tabs in environments missing the field
        if (Schema::hasColumn('faculties', 'is_active')) {
            $facultyQuery->active();
        }

        $faculties = $facultyQuery->get()->map(function ($faculty) {
            return [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'slug' => $faculty->slug,
            ];
        });

        return Inertia::render('projects/TopicsIndex', [
            'projectTopics' => $firstProject ? [$firstProject] : [],
            'allTopics' => $allTopics, // Pass all topics separately for the library
            'faculties' => $faculties,
            'meta' => [
                'totalProjects' => $projects->count(),
                'totalTopics' => $totalTopics,
                'page' => $page,
                'limit' => $limit,
            ],
        ]);
    }

    public function generate(GenerateTopicsRequest $request, Project $project)
    {
        // Load the category relationship
        $project->load('category');
        $geographicFocus = $request->input('geographic_focus') ?: 'balanced';

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Authentication required',
            ], 401);
        }
        if ($user->word_balance < $this->getMinimumTopicBalance()) {
            return response()->json([
                'error' => 'Insufficient word balance',
                'message' => "You need at least {$this->getMinimumTopicBalance()} words to generate topics.",
                'balance' => $user->word_balance,
                'required' => $this->getMinimumTopicBalance(),
                'shortage' => max(0, $this->getMinimumTopicBalance() - $user->word_balance),
            ], 402);
        }

        // Short-circuit when AI is offline to avoid futile generation attempts
        if (! $this->aiGenerator->isAvailable()) {
            $savedTopics = $this->getProjectGeneratedTopics($project);

            if (count($savedTopics) > 0) {
                return response()->json([
                    'topics' => $savedTopics,
                    'from_cache' => true,
                    'message' => 'AI services are currently unavailable. Showing your saved topics instead.',
                ]);
            }

            return response()->json([
                'error' => 'AI services are currently unavailable.',
                'message' => 'Please check your connection and try again. We could not generate new topics while offline.',
            ], 503);
        }

        // Set a longer execution time limit for topic generation
        set_time_limit(300); // 5 minutes

        try {
            $result = $this->topicGenerationService->generateTopicsWithAI($project, $geographicFocus);
            $topics = $result['topics'] ?? [];

            // Add metadata to topics
            $enrichedTopics = $this->topicEnrichmentService->enrich($topics, $project, $geographicFocus);

            // Deduct word balance when we actually generated fresh topics
            if (empty($result['from_cache']) && count($enrichedTopics) > 0) {
                $this->deductTopicGenerationWords(
                    $user,
                    $project,
                    $result['word_count'] ?? $this->calculateTopicWordCount($topics),
                    count($enrichedTopics),
                    'sync'
                );
            }

            return response()->json([
                'topics' => $enrichedTopics,
                'from_cache' => $result['from_cache'] ?? false,
                'word_count' => $result['word_count'] ?? 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Topic generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to generate topics',
                'message' => 'Please try again in a moment. If the problem persists, try refreshing the page.',
            ], 500);
        }
    }

    public function stream(StreamTopicsRequest $request, Project $project)
    {
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ];

        $user = $request->user();
        $geographicFocus = $request->input('geographic_focus') ?: 'balanced';

        if (! $user) {
            return response()->stream(function () {
                $this->sendSSEMessage('error', [
                    'message' => 'Authentication required',
                    'error_code' => 'UNAUTHENTICATED',
                    'status_code' => 401,
                ]);
                $this->sendSSEMessage('end', []);
            }, 200, $headers);
        }

        if ($user->word_balance < $this->getMinimumTopicBalance()) {
            return response()->stream(function () use ($user) {
                $this->sendSSEMessage('error', [
                    'message' => 'Insufficient word balance to generate topics.',
                    'balance' => $user->word_balance,
                    'required' => $this->getMinimumTopicBalance(),
                    'shortage' => max(0, $this->getMinimumTopicBalance() - $user->word_balance),
                    'error_code' => 'INSUFFICIENT_BALANCE',
                    'status_code' => 402,
                ]);
                $this->sendSSEMessage('end', []);
            }, 200, $headers);
        }

        try {
            // Load the category relationship
            $project->load('category');
        } catch (\Exception $e) {
            Log::error('💥 TOPIC STREAM - Early validation failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }

        try {
            // Increase execution time limit for AI generation
            set_time_limit(300); // 5 minutes
            ini_set('max_execution_time', 300);

            $streamingResponse = response()->stream(function () use ($project, $request, $geographicFocus) {
                // Disable output buffering
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }

                // Set proper headers for SSE in the stream
                header('Content-Type: text/event-stream');
                header('Cache-Control: no-cache');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');

                // Send connection established event
                $this->sendSSEMessage('start', [
                    'message' => 'Connected to AI service - preparing topic generation...',
                ]);

                // Send a test message immediately
                $this->sendSSEMessage('progress', [
                    'message' => 'Stream connection established successfully!',
                ]);

                try {
                    // Check for cached topics first
                    $cachedTopics = $this->topicCacheService->getCachedTopicsForAcademicContext($project, $geographicFocus);
                    $recentTopicRequest = $this->topicCacheService->hasRecentTopicRequest($project, $geographicFocus);

                    if (! $recentTopicRequest && count($cachedTopics) >= 8 && ! $request->boolean('regenerate')) {
                        // Convert cached topics to enriched format (preserve stored metadata when available)
                        $enrichedCachedTopics = collect($cachedTopics)
                            ->map(function ($topic, $index) {
                                $title = $topic['title'] ?? $topic['topic'] ?? $topic;
                                $description = $topic['description'] ?? 'Research topic in your field of study';

                                return [
                                    'id' => $index + 1,
                                    'title' => $title,
                                    'description' => $this->convertMarkdownToHtml($description),
                                    'difficulty' => $topic['difficulty'] ?? 'Intermediate',
                                    'timeline' => $topic['timeline'] ?? '6-9 months',
                                    'resource_level' => $topic['resource_level'] ?? 'Medium',
                                    'feasibility_score' => $topic['feasibility_score'] ?? 75,
                                    'keywords' => $topic['keywords'] ?? [],
                                    'research_type' => $topic['research_type'] ?? 'Applied Research',
                                ];
                            })
                            ->toArray();

                        $this->sendSSEMessage('content', [
                            'message' => 'Using cached topics for faster response...',
                            'topics' => $enrichedCachedTopics,
                            'from_cache' => true,
                        ]);

                        $this->sendSSEMessage('complete', [
                            'message' => 'Topics loaded from cache',
                            'topics' => $enrichedCachedTopics,
                            'total_topics' => count($enrichedCachedTopics),
                        ]);

                        $this->topicCacheService->trackTopicRequest($project);

                        // Send end event before returning
                        $this->sendSSEMessage('end', []);

                        return;
                    }

                    // Abort early if AI is offline; fall back to cached topics when possible
                    if (! $this->aiGenerator->isAvailable()) {
                        $offlineMessage = 'AI services are currently unavailable. Please check your connection and try again.';

                        if (count($cachedTopics) > 0) {
                            $enrichedCachedTopics = collect($cachedTopics)
                                ->map(function ($topic, $index) {
                                    return [
                                        'id' => $index + 1,
                                        'title' => $topic['topic'] ?? $topic['title'] ?? $topic,
                                        'description' => $this->convertMarkdownToHtml(
                                            $topic['description'] ?? 'Research topic in your field of study'
                                        ),
                                        'difficulty' => 'Intermediate',
                                        'timeline' => '6-9 months',
                                        'resource_level' => 'Medium',
                                        'feasibility_score' => 75,
                                        'keywords' => [],
                                        'research_type' => 'Applied Research',
                                    ];
                                })
                                ->toArray();

                            $this->sendSSEMessage('content', [
                                'message' => 'AI is offline. Showing your saved topics instead.',
                                'topics' => $enrichedCachedTopics,
                                'from_cache' => true,
                            ]);

                            $this->sendSSEMessage('complete', [
                                'message' => 'Topics loaded from cache while AI is offline.',
                                'topics' => $enrichedCachedTopics,
                                'total_topics' => count($enrichedCachedTopics),
                            ]);

                            $this->topicCacheService->trackTopicRequest($project);
                        } else {
                            $this->sendSSEMessage('error', [
                                'message' => $offlineMessage,
                            ]);
                        }

                        $this->sendSSEMessage('end', []);
                        Log::warning('🤖 TOPIC STREAM - AI unavailable, skipping generation', [
                            'project_id' => $project->id,
                            'cached_topics' => count($cachedTopics),
                        ]);

                        return;
                    }

                    // Generate fresh topics with streaming
                    $this->sendSSEMessage('progress', [
                        'message' => 'Generating fresh topics with AI...',
                    ]);

                    // Build academic context for intelligent model selection
                    $academicContext = $this->topicCacheService->getProjectAcademicContext($project, $geographicFocus);

                    $this->sendSSEMessage('progress', [
                        'message' => 'Using intelligent model selection based on your academic context...',
                        'context' => $academicContext,
                    ]);

                    $systemPrompt = $this->topicPromptBuilder->buildSystemPrompt($project, $geographicFocus);
                    $userPrompt = $this->topicPromptBuilder->buildContextualPrompt($project, $geographicFocus);
                    $fullPrompt = $systemPrompt."\n\n".$userPrompt;

                    // Stream the AI generation
                    $generatedContent = '';
                    $wordCount = 0;
                    $chunkCount = 0;

                    foreach ($this->aiGenerator->generateTopicsOptimized($fullPrompt, $academicContext) as $chunk) {
                        $chunkCount++;
                        $generatedContent .= $chunk;
                        $wordCount = str_word_count($generatedContent);

                        // Send content chunks to client
                        $this->sendSSEMessage('content', [
                            'chunk' => $chunk,
                            'content' => $generatedContent,
                            'word_count' => $wordCount,
                        ]);

                        // Small delay to ensure smooth streaming
                        usleep(10000); // 10ms delay
                    }

                    // Parse and process the generated topics
                    $this->sendSSEMessage('progress', [
                        'message' => 'Processing and enriching generated topics...',
                    ]);

                    $topics = $this->topicParser->parseAndValidate($generatedContent, $project);
                    $totalTopics = count($topics);

                    // Enrich topics while streaming heartbeat progress to keep the SSE connection alive
                    $enrichedTopics = $this->topicEnrichmentService->enrich($topics, $project, $geographicFocus, function (array $progress) use ($totalTopics) {
                        $current = $progress['current'] ?? 0;
                        $title = $progress['title'] ?? 'Topic';

                        $this->sendSSEMessage('progress', [
                            'message' => "Enriching topic {$current}/{$totalTopics}: ".Str::limit($title, 80),
                            'current' => $current,
                            'total' => $totalTopics,
                        ]);
                    });

                    // Cache request tracking after enrichment has persisted topics.
                    $this->topicCacheService->trackTopicRequest($project, $geographicFocus);

                    if ($wordCount > 0) {
                        $this->deductTopicGenerationWords(
                            $request->user(),
                            $project,
                            $wordCount,
                            count($enrichedTopics),
                            'stream'
                        );
                    }

                    // Send final result
                    $this->sendSSEMessage('complete', [
                        'message' => 'Topics generated successfully!',
                        'topics' => $enrichedTopics,
                        'total_topics' => count($enrichedTopics),
                        'word_count' => $wordCount,
                    ]);

                } catch (\Exception $e) {
                    Log::error('💥 TOPIC STREAM - Exception in generation', [
                        'project_id' => $project->id,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $this->sendSSEMessage('error', [
                        'message' => 'Failed to generate topics: '.$e->getMessage(),
                    ]);
                }

                // Send end event
                $this->sendSSEMessage('end', []);

            }, 200, $headers);

            return $streamingResponse;

        } catch (\Exception $e) {
            Log::error('💥 TOPIC STREAM - Exception occurred', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return error response with proper headers
            return response()->stream(function () use ($e) {
                echo 'data: '.json_encode([
                    'type' => 'error',
                    'message' => 'Stream failed: '.$e->getMessage(),
                ])."\n\n";
                flush();
            }, 500, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        }
    }

    private function sendSSEMessage(string $type, array $data = []): void
    {
        $message = [
            'type' => $type,
            'timestamp' => now()->toISOString(),
            ...$data,
        ];

        $jsonMessage = json_encode($message);
        $sseData = 'data: '.$jsonMessage."\n\n";

        echo $sseData;

        // Ensure data is sent immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    public function select(SelectTopicRequest $request, Project $project)
    {
        Log::info('🚀 TOPIC SELECTION - Request received', [
            'user_id' => auth()->id(),
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'project_status' => $project->status,
            'topic_status' => $project->topic_status,
            'request_data' => $request->all(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'timestamp' => now()->toDateTimeString(),
        ]);

        $validated = $request->validated();

        Log::info('✅ TOPIC SELECTION - Validation passed', [
            'validated_data' => $validated,
            'user_id' => auth()->id(),
        ]);

        Log::info('🔍 TOPIC SELECTION - Project found and authorized', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'current_status' => $project->status,
            'user_id' => auth()->id(),
            'project_owner_id' => $project->user_id,
        ]);

        Log::info('📝 TOPIC SELECTION - Preparing to update project', [
            'project_id' => $project->id,
            'before_topic' => $project->topic,
            'before_title' => $project->title,
            'before_description' => $project->description ? substr($project->description, 0, 50).'...' : null,
            'before_status' => $project->status,
            'new_topic' => $validated['topic'],
            'new_title' => $validated['title'],
            'new_description' => $validated['description'] ? substr($validated['description'], 0, 50).'...' : null,
        ]);

        try {
            // Don't update slug yet - wait until topic is approved to avoid route conflicts
            $project->update([
                'topic' => $validated['topic'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'status' => 'topic_pending_approval',
                'topic_status' => 'topic_pending_approval',
            ]);

            Log::info('✅ TOPIC SELECTION - Project updated successfully', [
                'project_id' => $project->id,
                'updated_fields' => [
                    'topic' => $validated['topic'],
                    'title' => $validated['title'],
                    'description' => $validated['description'] ? 'Updated' : 'Null',
                    'status' => 'topic_pending_approval',
                    'topic_status' => 'topic_pending_approval',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('❌ TOPIC SELECTION - Project update failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        $updatedProject = $project->fresh();

        Log::info('🔄 TOPIC SELECTION - After Update', [
            'project_id' => $project->id,
            'after_topic' => $updatedProject->topic,
            'after_title' => $updatedProject->title,
            'after_description' => $updatedProject->description ? substr($updatedProject->description, 0, 50).'...' : null,
            'after_slug' => $updatedProject->slug,
            'after_status' => $updatedProject->status,
            'slug_unchanged' => 'Slug will be updated only when topic is approved',
        ]);

        $responseData = [
            'success' => true,
            'message' => 'Topic selected successfully',
            'project' => [
                'id' => $updatedProject->id,
                'slug' => $updatedProject->slug,
                'status' => $updatedProject->status,
                'title' => $updatedProject->title,
                'topic' => $updatedProject->topic,
            ],
        ];

        Log::info('📤 TOPIC SELECTION - Sending response', [
            'response_data' => $responseData,
            'timestamp' => now()->toDateTimeString(),
        ]);

        return response()->json($responseData);
    }

    public function approve(ApproveTopicRequest $request, Project $project)
    {
        Log::info('🏁 TOPIC APPROVAL - Starting approval process', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'user_id' => auth()->id(),
            'request_data' => $request->all(),
            'request_method' => $request->method(),
            'request_url' => $request->url(),
        ]);

        try {
            $validated = $request->validated();

            Log::info('✅ TOPIC APPROVAL - Validation passed', [
                'project_id' => $project->id,
                'validated_data' => $validated,
            ]);

            Log::info('🔍 TOPIC APPROVAL - Processing approval', [
                'project_id' => $project->id,
                'approved' => $validated['approved'],
                'before_status' => $project->status,
                'before_topic_status' => $project->topic_status,
                'before_title' => $project->title,
                'before_slug' => $project->slug,
                'project_user_id' => $project->user_id,
                'auth_user_id' => auth()->id(),
            ]);

            if ($validated['approved']) {
                Log::info('👍 TOPIC APPROVAL - Approving topic', [
                    'project_id' => $project->id,
                ]);

                // Both auto and manual modes go directly to writing (skip guidance)
                $nextStatus = 'writing';

                try {
                    $project->update([
                        'topic_status' => 'topic_approved',
                        'status' => $nextStatus,
                    ]);

                    Log::info('✅ TOPIC APPROVAL - Status update successful', [
                        'project_id' => $project->id,
                        'new_status' => 'writing',
                        'new_topic_status' => 'topic_approved',
                        'next_status' => $nextStatus,
                    ]);

                } catch (\Exception $e) {
                    Log::error('❌ TOPIC APPROVAL - Status update failed', [
                        'project_id' => $project->id,
                        'error_message' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }

                // Update slug based on current title (set during topic selection)
                if ($project->title) {
                    Log::info('🔄 TOPIC APPROVAL - Updating slug from title', [
                        'project_id' => $project->id,
                        'current_title' => $project->title,
                    ]);

                    try {
                        $newSlug = $project->generateSlugFromText($project->title);
                        $project->update(['slug' => $newSlug]);

                        Log::info('✅ TOPIC APPROVAL - Slug updated from title', [
                            'project_id' => $project->id,
                            'title' => $project->title,
                            'new_slug' => $newSlug,
                            'old_slug' => $project->getOriginal('slug'),
                        ]);

                    } catch (\Exception $e) {
                        Log::error('❌ TOPIC APPROVAL - Slug update failed', [
                            'project_id' => $project->id,
                            'title' => $project->title,
                            'error_message' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }

                // Generate project title from topic if not already set (fallback)
                if (! $project->title && $project->topic) {
                    Log::info('🔄 TOPIC APPROVAL - Generating title from topic', [
                        'project_id' => $project->id,
                        'topic' => $project->topic,
                    ]);

                    try {
                        $title = $this->generateTitleFromTopic($project->topic);
                        $newSlug = $project->generateSlugFromText($title);

                        $project->update([
                            'title' => $title,
                            'slug' => $newSlug,
                        ]);

                        Log::info('✅ TOPIC APPROVAL - Title and slug generated from topic', [
                            'project_id' => $project->id,
                            'generated_title' => $title,
                            'generated_slug' => $newSlug,
                        ]);

                    } catch (\Exception $e) {
                        Log::error('❌ TOPIC APPROVAL - Title generation failed', [
                            'project_id' => $project->id,
                            'topic' => $project->topic,
                            'error_message' => $e->getMessage(),
                        ]);
                        throw $e;
                    }
                }

                // Dispatch outline generation job to background queue
                Log::info('🚀 TOPIC APPROVAL - Dispatching outline generation job', [
                    'project_id' => $project->id,
                ]);

                try {
                    GenerateProjectOutline::dispatch($project->fresh());

                    Log::info('✅ TOPIC APPROVAL - Outline generation job dispatched', [
                        'project_id' => $project->id,
                    ]);

                } catch (\Exception $e) {
                    Log::error('❌ TOPIC APPROVAL - Failed to dispatch outline job', [
                        'project_id' => $project->id,
                        'error_message' => $e->getMessage(),
                    ]);
                    // Don't throw here - approval can still succeed without outline generation
                }

            } else {
                Log::info('👎 TOPIC APPROVAL - Rejecting topic', [
                    'project_id' => $project->id,
                ]);

                try {
                    $project->update([
                        'topic_status' => 'not_started',
                        'status' => 'topic_selection',
                        'topic' => null,
                        'title' => null,
                    ]);

                    Log::info('✅ TOPIC APPROVAL - Topic rejected successfully', [
                        'project_id' => $project->id,
                        'new_status' => 'topic_selection',
                        'new_topic_status' => 'not_started',
                    ]);

                } catch (\Exception $e) {
                    Log::error('❌ TOPIC APPROVAL - Topic rejection failed', [
                        'project_id' => $project->id,
                        'error_message' => $e->getMessage(),
                        'error_trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }

            // Refresh project to get latest values
            $freshProject = $project->fresh();

            Log::info('🏆 TOPIC APPROVAL - Processing completed successfully', [
                'project_id' => $project->id,
                'after_status' => $freshProject->status,
                'after_topic_status' => $freshProject->topic_status,
                'after_title' => $freshProject->title,
                'after_slug' => $freshProject->slug,
            ]);

            try {
                $targetRoute = $freshProject->status->value === 'writing' ? 'projects.writing' : 'projects.guidance';

                $response = response()->json([
                    'success' => true,
                    'status' => $freshProject->status,
                    'topic_status' => $freshProject->topic_status,
                    'slug' => $freshProject->slug, // Return updated slug for correct redirects
                    'mode' => $freshProject->mode,
                    'redirect_route' => $targetRoute,
                    'redirect_url' => route($targetRoute, $freshProject->slug),
                ]);

                Log::info('✅ TOPIC APPROVAL - Response prepared successfully', [
                    'project_id' => $project->id,
                    'response_data' => [
                        'success' => true,
                        'status' => $freshProject->status,
                        'topic_status' => $freshProject->topic_status,
                        'slug' => $freshProject->slug,
                        'redirect_route' => $targetRoute,
                        'redirect_url' => route($targetRoute, $freshProject->slug),
                    ],
                ]);

                return $response;

            } catch (\Exception $e) {
                Log::error('❌ TOPIC APPROVAL - Response preparation failed', [
                    'project_id' => $project->id,
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('💥 TOPIC APPROVAL - Unexpected error occurred', [
                'project_id' => $project->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update approval status',
                'message' => 'An unexpected error occurred while processing the topic approval.',
            ], 500);
        }
    }

    /**
     * EXPORT TOPIC PROPOSAL TO PDF
     * Generates a professional PDF document for supervisor review
     * Uses Browsershot for reliable PDF generation
     */
    public function exportPdf(Project $project)
    {
        return $this->exportTopicPdfAction->execute($project);
    }

    private function getMinimumTopicBalance(): int
    {
        return (int) config('pricing.minimum_balance.topic_generation', 300);
    }

    private function calculateTopicWordCount(array $topics): int
    {
        return array_sum(array_map(function ($topic) {
            $value = is_array($topic) ? ($topic['title'] ?? ($topic['topic'] ?? '')) : (string) $topic;

            return $value ? str_word_count(strip_tags($value)) : 0;
        }, $topics));
    }

    private function deductTopicGenerationWords(?User $user, Project $project, int $wordCount, int $topicCount, string $source): void
    {
        if (! $user || $wordCount <= 0) {
            return;
        }

        try {
            $this->wordBalanceService->deductForGeneration(
                $user,
                $wordCount,
                sprintf('Topic generation for %s', $project->title ?? 'project'),
                'topic_generation',
                $project->id,
                [
                    'topic_count' => $topicCount,
                    'source' => $source,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to deduct words for topic generation', [
                'project_id' => $project->id,
                'user_id' => $user->id ?? null,
                'words' => $wordCount,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get previously generated topics for this project
     * Returns ALL topics without any filtering
     */
    private function getProjectGeneratedTopics(Project $project): array
    {
        $project->loadMissing([
            'universityRelation:id,name',
            'facultyRelation:id,name',
            'departmentRelation:id,name',
        ]);

        $savedTopics = $this->topicLibraryService
            ->getSavedTopicsForProject($project, 50)
            ->map(function (ProjectTopic $topic) {
                $payload = TopicTransformer::toArray($topic);
                $payload['description'] = $this->cleanTopicDescription(
                    $this->convertMarkdownToHtml(
                        $payload['description'] ?? 'Research topic in '.$payload['field_of_study']
                    )
                );

                return $payload;
            })
            ->toArray();

        if (! app()->isProduction()) {
            Log::info('Retrieved saved project topics', [
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'total_topics' => count($savedTopics),
            ]);
        }

        return $savedTopics;
    }
}
