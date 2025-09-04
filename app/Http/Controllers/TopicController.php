<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTopic;
use App\Services\AIContentGenerator;
use Illuminate\Support\Facades\DB;

// Add request logging
\Illuminate\Support\Facades\Log::info('TopicController loaded', [
    'time' => now()->toDateTimeString(),
    'url' => request()->fullUrl(),
    'method' => request()->method(),
    'route' => request()->route()?->getName(),
]);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Orientation;
use Spatie\LaravelPdf\Facades\Pdf;

class TopicController extends Controller
{
    public function __construct(private AIContentGenerator $aiGenerator)
    {
        //
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'regenerate' => 'boolean',
        ]);

        $project = Project::with('category')->findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $topics = $this->generateTopicsWithAI($project);

        // Add metadata to topics
        $enrichedTopics = $this->enrichTopicsWithMetadata($topics, $project);

        return response()->json([
            'topics' => $enrichedTopics,
        ]);
    }

    public function stream(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'regenerate' => 'boolean',
        ]);

        $project = Project::with('category')->findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Set up Server-Sent Events headers
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
        ];

        return response()->stream(function () use ($project) {
            // Disable output buffering
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Send connection established event
            $this->sendSSEMessage('start', [
                'message' => 'Connected to AI service - preparing topic generation...',
            ]);

            try {
                // Check for cached topics first
                $cachedTopics = $this->getCachedTopicsForAcademicContext($project);
                $recentTopicRequest = $this->hasRecentTopicRequest($project);

                if (! $recentTopicRequest && count($cachedTopics) >= 8) {
                    $this->sendSSEMessage('content', [
                        'message' => 'Using cached topics for faster response...',
                        'topics' => collect($cachedTopics)->pluck('topic')->toArray(),
                        'from_cache' => true,
                    ]);

                    $this->sendSSEMessage('complete', [
                        'message' => 'Topics loaded from cache',
                        'total_topics' => count($cachedTopics),
                    ]);

                    $this->trackTopicRequest($project);

                    return;
                }

                // Generate fresh topics with streaming
                $this->sendSSEMessage('progress', [
                    'message' => 'Generating fresh topics with AI...',
                ]);

                // Build academic context for intelligent model selection
                $academicContext = [
                    'field_of_study' => $project->field_of_study,
                    'academic_level' => $project->type,
                    'faculty' => $project->settings['faculty'] ?? '',
                    'university' => $project->university,
                ];

                $this->sendSSEMessage('progress', [
                    'message' => 'Using intelligent model selection based on your academic context...',
                    'context' => $academicContext,
                ]);

                $systemPrompt = $this->buildSystemPrompt($project);
                $userPrompt = $this->buildContextualPrompt($project);
                $fullPrompt = $systemPrompt."\n\n".$userPrompt;

                // Stream the AI generation
                $generatedContent = '';
                $wordCount = 0;

                foreach ($this->aiGenerator->generateTopicsOptimized($fullPrompt, $academicContext) as $chunk) {
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

                $topics = $this->parseTopicsFromAIResponse($generatedContent);
                $enrichedTopics = $this->enrichTopicsWithMetadata($topics, $project);

                // Cache the generated topics
                $this->cacheGeneratedTopics($topics, $project);
                $this->trackTopicRequest($project);

                // Send final result
                $this->sendSSEMessage('complete', [
                    'message' => 'Topics generated successfully!',
                    'topics' => $enrichedTopics,
                    'total_topics' => count($enrichedTopics),
                    'word_count' => $wordCount,
                ]);

            } catch (\Exception $e) {
                Log::error('Topic streaming generation failed', [
                    'project_id' => $project->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->sendSSEMessage('error', [
                    'message' => 'Failed to generate topics: '.$e->getMessage(),
                ]);
            }

            // Send end event
            $this->sendSSEMessage('end', []);

        }, 200, $headers);
    }

    /**
     * Send Server-Sent Event message
     */
    private function sendSSEMessage(string $type, array $data = []): void
    {
        $message = [
            'type' => $type,
            'timestamp' => now()->toISOString(),
            ...$data,
        ];

        echo 'data: '.json_encode($message)."\n\n";

        // Ensure data is sent immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    public function select(Request $request)
    {
        Log::info('🚀 TOPIC SELECTION - Request received', [
            'user_id' => auth()->id(),
            'request_data' => $request->all(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            $validated = $request->validate([
                'project_id' => 'required|exists:projects,id',
                'topic' => 'required|string|max:500',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:2000',
            ]);

            Log::info('✅ TOPIC SELECTION - Validation passed', [
                'validated_data' => $validated,
                'user_id' => auth()->id(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ TOPIC SELECTION - Validation failed', [
                'user_id' => auth()->id(),
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            throw $e;
        }

        $project = Project::findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

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
            ]);

            Log::info('✅ TOPIC SELECTION - Project updated successfully', [
                'project_id' => $project->id,
                'updated_fields' => [
                    'topic' => $validated['topic'],
                    'title' => $validated['title'],
                    'description' => $validated['description'] ? 'Updated' : 'Null',
                    'status' => 'topic_pending_approval',
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

    public function approve(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'approved' => 'required|boolean',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        Log::info('TOPIC APPROVAL - Processing Approval', [
            'project_id' => $project->id,
            'approved' => $validated['approved'],
            'before_status' => $project->status,
            'before_title' => $project->title,
            'before_slug' => $project->slug,
        ]);

        if ($validated['approved']) {
            $project->update([
                'status' => 'topic_approved',
            ]);

            // Update slug based on current title (set during topic selection)
            if ($project->title) {
                $newSlug = $project->generateSlugFromText($project->title);
                $project->update(['slug' => $newSlug]);

                Log::info('TOPIC APPROVAL - Updated Slug from Title', [
                    'project_id' => $project->id,
                    'title' => $project->title,
                    'new_slug' => $newSlug,
                    'old_slug' => $project->getOriginal('slug'),
                ]);
            }

            // Generate project title from topic if not already set (fallback)
            if (! $project->title && $project->topic) {
                $title = $this->generateTitleFromTopic($project->topic);
                $newSlug = $project->generateSlugFromText($title);

                $project->update([
                    'title' => $title,
                    'slug' => $newSlug,
                ]);

                Log::info('TOPIC APPROVAL - Generated Title and Slug from Topic', [
                    'project_id' => $project->id,
                    'generated_title' => $title,
                    'generated_slug' => $newSlug,
                ]);
            }
        } else {
            $project->update([
                'status' => 'topic_selection',
                'topic' => null,
                'title' => null,
            ]);
        }

        Log::info('TOPIC APPROVAL - After Processing', [
            'project_id' => $project->id,
            'after_status' => $project->fresh()->status,
            'after_title' => $project->fresh()->title,
            'after_slug' => $project->fresh()->slug,
        ]);

        return response()->json([
            'success' => true,
            'status' => $project->status,
            'slug' => $project->fresh()->slug, // Return updated slug for correct redirects
        ]);
    }

    /**
     * EXPORT TOPIC PROPOSAL TO PDF
     * Generates a professional PDF document for supervisor review
     * Uses Browsershot for reliable PDF generation
     */
    public function exportPdf(Project $project)
    {
        $startTime = microtime(true);

        Log::info('PDF Export Request Received', [
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'project_slug' => $project->slug,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            // Ensure user owns the project
            Log::info('PDF Export: Checking user authorization', [
                'project_user_id' => $project->user_id,
                'auth_user_id' => auth()->id(),
                'is_authorized' => $project->user_id === auth()->id(),
            ]);
            abort_if($project->user_id !== auth()->id(), 403);

            // Ensure project has a topic to export
            Log::info('PDF Export: Checking project topic', [
                'has_topic' => ! empty($project->topic),
                'topic_length' => $project->topic ? strlen($project->topic) : 0,
            ]);
            abort_if(empty($project->topic), 404, 'No topic available for export');

            // Load project with all necessary relationships for PDF generation
            $project->load(['user', 'category']);

            Log::info('PDF Export: Project data loaded', [
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'project_slug' => $project->slug,
                'has_user_relation' => $project->user !== null,
                'has_category_relation' => $project->category !== null,
                'user_name' => $project->user->name ?? 'N/A',
                'category_name' => $project->category->name ?? 'N/A',
            ]);

            // Create a unique filename
            $fileName = sprintf(
                'project_topic_proposal_%s_%s.pdf',
                $project->slug,
                now()->format('Ymd-His')
            );

            // Create directory if it doesn't exist
            $directory = storage_path('app/public/topic-proposals/'.date('Y/m'));
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $filePath = $directory.'/'.$fileName;

            try {
                // Generate PDF using Spatie PDF with Browsershot for reliability
                $pdf = Pdf::view('pdf.topic-proposal', [
                    'project' => $project,
                    'isPdfMode' => true,
                ])
                    ->format('A4')
                    ->orientation(Orientation::Portrait)
                    ->withBrowsershot(function (Browsershot $browsershot) {
                        // Try to find installed browsers in the system
                        $chromePaths = [
                            config('app.chrome_path'), // First try the configured path
                            '/usr/bin/chromium-browser',
                            '/usr/bin/chromium',
                            '/usr/bin/google-chrome',
                            '/usr/bin/google-chrome-stable',
                            '/snap/bin/chromium',
                            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', // macOS
                        ];

                        Log::info('PDF Generation: Chrome Path Detection', [
                            'available_paths' => $chromePaths,
                            'config_chrome_path' => config('app.chrome_path'),
                        ]);

                        $chromePath = null;
                        foreach ($chromePaths as $path) {
                            Log::debug("PDF Generation: Testing Chrome path: {$path}");
                            if ($path && file_exists($path) && is_executable($path)) {
                                $chromePath = $path;
                                Log::info("PDF Generation: Chrome path found: {$chromePath}");
                                break;
                            }
                        }

                        if (! $chromePath) {
                            Log::error('PDF Generation: No Chrome path found!', [
                                'tested_paths' => $chromePaths,
                            ]);
                            throw new \Exception('Chrome/Chromium browser not found for PDF generation');
                        }

                        Log::info('PDF Generation: Configuring Browsershot', [
                            'chrome_path' => $chromePath,
                            'format' => 'A4',
                            'margins' => '20x20x20x20',
                            'timeout' => 120,
                        ]);

                        $browsershot->setChromePath($chromePath)
                            ->format('A4')
                            ->margins(20, 20, 20, 20) // Professional academic margins
                            ->showBackground()
                            ->waitUntilNetworkIdle() // Wait for all resources to load
                            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                            ->deviceScaleFactor(1.5) // Higher resolution for better quality
                            ->timeout(120)
                            ->showBrowserHeaderAndFooter()
                            ->hideHeader()
                            ->footerHtml('<div style="text-align: center; font-size: 10px; color: #6b7280; font-family: Times New Roman, serif; padding: 8px 0; width: 100%; display: block;">Generated by Finalyze AI Academic Assistant | '.now()->format('F j, Y \a\t g:i A').'</div>')
                            ->noSandbox()
                            ->setOption('disable-web-security', true)
                            ->setOption('allow-running-insecure-content', true);
                    });

                Log::info('PDF Generation: Starting PDF creation', [
                    'view' => 'pdf.topic-proposal',
                    'output_path' => $filePath,
                    'project_data' => [
                        'id' => $project->id,
                        'slug' => $project->slug,
                        'has_title' => ! empty($project->title),
                        'has_topic' => ! empty($project->topic),
                        'has_user' => ! empty($project->user),
                        'has_category' => ! empty($project->category),
                    ],
                ]);

                $pdf->save($filePath);

                Log::info('PDF Generation: Save operation completed', [
                    'file_path' => $filePath,
                    'file_exists' => File::exists($filePath),
                ]);

                if (! File::exists($filePath)) {
                    Log::error('PDF Generation: File was not created', [
                        'expected_path' => $filePath,
                        'directory_exists' => File::exists(dirname($filePath)),
                        'directory_writable' => is_writable(dirname($filePath)),
                    ]);
                    throw new \Exception("PDF file was not created at: {$filePath}");
                }

                // Validate PDF file format
                $fileSize = File::size($filePath);
                $fileHeader = file_get_contents($filePath, false, null, 0, 4);

                Log::info('PDF Generation: File validation', [
                    'file_size' => $fileSize,
                    'file_header' => bin2hex($fileHeader),
                    'is_valid_pdf' => $fileHeader === '%PDF',
                ]);

                if ($fileHeader !== '%PDF') {
                    Log::error('PDF Generation: Invalid PDF format detected', [
                        'file_path' => $filePath,
                        'file_size' => $fileSize,
                        'header_hex' => bin2hex($fileHeader),
                        'first_100_chars' => substr(file_get_contents($filePath), 0, 100),
                    ]);
                }

                // Log successful PDF generation
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('PDF Topic Proposal Generated Successfully', [
                    'file' => $filePath,
                    'execution_time_ms' => $executionTime,
                ]);

                // Log download preparation
                Log::info('PDF Export: Preparing download response', [
                    'filename' => $fileName,
                    'content_type' => 'application/pdf',
                    'file_size' => File::size($filePath),
                    'project_id' => $project->id,
                    'project_slug' => $project->slug,
                    'user_id' => auth()->id(),
                    'execution_time_ms' => $executionTime,
                    'delete_after_send' => true,
                ]);

                // Return file download response
                return response()->download($filePath, $fileName, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                ])->deleteFileAfterSend(true);

            } catch (\Exception $e) {
                Log::error('PDF Generation Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    // 'project_id' => $project->id,
                    'file_path' => $filePath ?? 'not_set',
                ]);

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Topic Proposal PDF Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $project->id ?? null,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF. Please try again.',
                'message' => 'PDF generation encountered an error: '.$e->getMessage(),
            ], 500);
        }
    }

    private function generateTopicsWithAI(Project $project): array
    {
        // First, try to get cached topics from database based on academic context
        $cachedTopics = $this->getCachedTopicsForAcademicContext($project);

        // Check if user has recently requested topics (within 2 minutes) - indicates they want fresh ideas
        $recentTopicRequest = $this->hasRecentTopicRequest($project);

        if (! $recentTopicRequest && count($cachedTopics) >= 8) {
            Log::info('Using cached topics for academic context', [
                'project_id' => $project->id,
                'course' => $project->course,
                'university' => $project->university,
                'cached_count' => count($cachedTopics),
            ]);

            // Track this topic generation request for future smart decisions
            $this->trackTopicRequest($project);

            return collect($cachedTopics)->pluck('topic')->toArray();
        }

        // User wants fresh topics - either no cache or recent request detected
        Log::info('Generating fresh topics', [
            'project_id' => $project->id,
            'reason' => $recentTopicRequest ? 'Recent request detected - user wants fresh ideas' : 'Insufficient cached topics',
            'cached_count' => count($cachedTopics),
        ]);

        // Track this topic request for future intelligent decisions
        $this->trackTopicRequest($project);

        // If we don't have enough cached topics, generate new ones via AI
        try {
            $startTime = microtime(true);

            // Build academic context for intelligent model selection
            $academicContext = [
                'field_of_study' => $project->field_of_study,
                'academic_level' => $project->type,
                'faculty' => $project->settings['faculty'] ?? '',
                'university' => $project->university,
            ];

            Log::info('AI Topic Generation - Starting with intelligent selection', [
                'project_id' => $project->id,
                'academic_context' => $academicContext,
                'timestamp' => now()->toDateTimeString(),
            ]);

            $systemPrompt = $this->buildSystemPrompt($project);
            $userPrompt = $this->buildContextualPrompt($project);
            $fullPrompt = $systemPrompt."\n\n".$userPrompt;

            $aiStartTime = microtime(true);

            // Collect all chunks from the generator into a single string
            $generatedContent = '';
            foreach ($this->aiGenerator->generateTopicsOptimized($fullPrompt, $academicContext) as $chunk) {
                $generatedContent .= $chunk;
            }

            $aiEndTime = microtime(true);
            $aiDuration = ($aiEndTime - $aiStartTime) * 1000; // Convert to milliseconds

            Log::info('AI Topic Generation - Intelligent generation completed', [
                'project_id' => $project->id,
                'ai_response_time_ms' => round($aiDuration, 2),
                'active_provider' => $this->aiGenerator->getActiveProvider()?->getName(),
                'academic_context' => $academicContext,
            ]);
            $parseStartTime = microtime(true);
            $newTopics = $this->parseAndValidateTopics($generatedContent, $project);
            $parseEndTime = microtime(true);
            $parseDuration = ($parseEndTime - $parseStartTime) * 1000;

            // Store new topics in database for future use
            $dbStartTime = microtime(true);
            $this->storeTopicsInDatabase($newTopics, $project);
            $dbEndTime = microtime(true);
            $dbDuration = ($dbEndTime - $dbStartTime) * 1000;

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
            ]);

            return $newTopics;

        } catch (\Exception $e) {
            Log::error('AI Topic Generation Failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to cached topics if available, otherwise enhanced mock topics
            if (count($cachedTopics) > 0) {
                return collect($cachedTopics)->map(function ($topic) {
                    return $topic->title;
                })->toArray();
            }

            return $this->generateEnhancedMockTopics($project);
        }
    }

    private function buildSystemPrompt(Project $project): string
    {
        $categoryName = $project->category->name ?? 'Final Year Project';
        $academicLevel = $this->getAcademicLevelDescription($project->type);

        return "You are an expert academic advisor specializing in research topic generation for Nigerian university students.

CONTEXT:
- Academic Level: {$academicLevel}
- Project Type: {$categoryName}
- Institution: {$project->university}
- Geographic Focus: Nigeria/West Africa

REQUIREMENTS:
1. Generate EXACTLY 8-10 unique, high-quality research topics
2. Topics must be academically rigorous yet feasible for the academic level
3. Consider local Nigerian context and emerging global trends
4. Ensure topics are specific enough to be manageable but broad enough to be significant
5. Include mix of theoretical, practical, and applied research approaches
6. Consider available resources in Nigerian academic institutions

FORMAT:
Return ONLY a numbered list of topics, one per line:
1. [Topic title]
2. [Topic title]
...

No additional text, explanations, or formatting.";
    }

    private function buildContextualPrompt(Project $project): string
    {
        $requirements = $this->getProjectRequirements($project);

        $categoryName = $project->category->name ?? 'Final Year Project';

        return "Generate research topics for:

FIELD OF STUDY: {$project->field_of_study}
COURSE: {$project->course}  
UNIVERSITY: {$project->university}
PROJECT TYPE: {$categoryName}

FOCUS AREAS:
- Current industry trends and challenges in {$project->field_of_study}
- Emerging technologies applicable to {$project->field_of_study}
- Nigerian/African context and local problems to solve
- Practical applications and real-world impact
- Interdisciplinary approaches where relevant

REQUIREMENTS:
{$requirements}

Generate topics that are:
✓ Original and innovative
✓ Feasible with standard university resources
✓ Relevant to current industry needs
✓ Appropriate for the academic level
✓ Aligned with Nigerian educational and economic priorities";
    }

    private function getProjectRequirements(Project $project): string
    {
        $categoryName = $project->category->name ?? '';

        if (str_contains(strtolower($categoryName), 'thesis') || str_contains(strtolower($categoryName), 'dissertation')) {
            return '- Comprehensive literature review required
- Original research contribution expected  
- Statistical analysis and data collection needed
- Duration: 12-18 months
- Significant theoretical and practical contribution';
        }

        return '- Practical implementation component preferred
- Literature review and analysis required
- Prototype/system development expected
- Duration: 6-12 months  
- Clear problem-solution approach';
    }

    private function getAcademicLevelDescription(string $type): string
    {
        return match (strtolower($type)) {
            'undergraduate', 'bachelor' => 'Undergraduate/Bachelor\'s degree level',
            'masters', 'msc', 'ma' => 'Master\'s degree level',
            'phd', 'doctorate' => 'Doctoral/PhD level',
            default => 'Final year project level'
        };
    }

    private function parseAndValidateTopics(string $generatedContent, Project $project): array
    {
        // Extract numbered list items
        preg_match_all('/^\d+\.\s*(.+)$/m', $generatedContent, $matches);

        if (empty($matches[1])) {
            // Try alternative parsing if numbered list format fails
            $lines = array_filter(array_map('trim', explode("\n", $generatedContent)));
            $topics = array_slice($lines, 0, 10); // Take first 10 non-empty lines
        } else {
            $topics = $matches[1];
        }

        // Clean and validate topics
        $cleanedTopics = [];
        foreach ($topics as $topic) {
            $cleaned = trim($topic);
            // Remove any remaining numbering or formatting
            $cleaned = preg_replace('/^\d+\.\s*/', '', $cleaned);

            if (strlen($cleaned) >= 20 && strlen($cleaned) <= 200) {
                $cleanedTopics[] = $cleaned;
            }
        }

        // Ensure we have at least 5 topics
        if (count($cleanedTopics) < 5) {
            Log::warning('AI generated insufficient topics', [
                'generated_count' => count($cleanedTopics),
                'raw_content' => $generatedContent,
            ]);

            return $this->generateEnhancedMockTopics($project);
        }

        return array_slice($cleanedTopics, 0, 10); // Max 10 topics
    }

    private function generateEnhancedMockTopics(Project $project): array
    {
        $field = $project->field_of_study;
        $university = $project->university;

        $templates = [
            'Development and Implementation of {technology} Solutions for {field} Applications in Nigerian Context',
            'Comparative Analysis of {field} Practices: A Study of {university} and Similar Institutions',
            'Machine Learning Applications in {field}: Opportunities and Challenges in West African Universities',
            'Digital Transformation Impact on {field} Education and Practice in Nigeria',
            'Design and Development of Mobile-Based {field} Management System for Nigerian Students',
            'Blockchain Technology Integration in {field}: Security and Efficiency Enhancement Study',
            'Internet of Things (IoT) Applications for {field} Monitoring and Optimization',
            'Cloud Computing Solutions for {field} Data Management in Resource-Constrained Environments',
            'Artificial Intelligence-Powered {field} Decision Support System Development',
            'Cybersecurity Framework Development for {field} Information Systems in Nigerian Institutions',
        ];

        $technologies = ['AI-Powered', 'Cloud-Based', 'Mobile-First', 'IoT-Enabled', 'Blockchain-Secured'];

        $topics = [];
        foreach (array_slice($templates, 0, 8) as $template) {
            $topic = str_replace(['{field}', '{university}', '{technology}'],
                [$field, $university, $technologies[array_rand($technologies)]],
                $template);
            $topics[] = $topic;
        }

        return $topics;
    }

    private function enrichTopicsWithMetadata(array $topics, Project $project): array
    {
        $enrichedTopics = [];

        foreach ($topics as $index => $topic) {
            $metadata = $this->analyzeTopicMetadata($topic, $project);
            $description = $this->generateTopicDescription($topic, $project);

            $enrichedTopics[] = [
                'id' => $index + 1,
                'title' => $topic,
                'description' => $description,
                'difficulty' => $metadata['difficulty'],
                'timeline' => $metadata['timeline'],
                'resource_level' => $metadata['resource_level'],
                'feasibility_score' => $metadata['feasibility_score'],
                'keywords' => $metadata['keywords'],
                'research_type' => $metadata['research_type'],
            ];
        }

        // Store enriched topics in database for future caching
        $this->storeTopicsInDatabase($enrichedTopics, $project);

        return $enrichedTopics;
    }

    private function analyzeTopicMetadata(string $topic, Project $project): array
    {
        // Try AI-powered analysis first
        try {
            return $this->analyzeTopicMetadataWithAI($topic, $project);
        } catch (\Exception $e) {
            Log::warning('AI Topic Metadata Analysis Failed - Using Fallback', [
                'topic' => $topic,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to rule-based analysis
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

        $academicLevel = $this->getAcademicLevelDescription($project->type);
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
            'temperature' => 0.2, // Low temperature for consistent analysis
            'max_tokens' => 300,
        ]);
        $aiEndTime = microtime(true);
        $aiDuration = ($aiEndTime - $aiStartTime) * 1000;

        $generatedContent = trim($response->choices[0]->message->content);

        // Clean any markdown formatting
        $generatedContent = preg_replace('/```json|```/', '', $generatedContent);
        $generatedContent = trim($generatedContent);

        $analysisData = json_decode($generatedContent, true);

        if (! $analysisData || json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from AI analysis: '.json_last_error_msg());
        }

        // Validate required fields
        $requiredFields = ['difficulty', 'timeline', 'resource_level', 'feasibility_score', 'research_type', 'keywords'];
        foreach ($requiredFields as $field) {
            if (! isset($analysisData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Ensure feasibility score is within range
        $analysisData['feasibility_score'] = max(60, min(100, (int) $analysisData['feasibility_score']));

        // Ensure keywords is array
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

        // Analyze difficulty
        $difficulty = $this->analyzeDifficulty($topicLower, $project);

        // Analyze timeline
        $timeline = $this->analyzeTimeline($topicLower, $difficulty);

        // Analyze resource requirements
        $resourceLevel = $this->analyzeResourceRequirements($topicLower);

        // Calculate feasibility score
        $feasibilityScore = $this->calculateFeasibilityScore($difficulty, $resourceLevel, $project);

        // Extract keywords
        $keywords = $this->extractKeywords($topic);

        // Determine research type
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

        // Adjust for difficulty
        if ($difficulty === 'Advanced') {
            $score -= 20;
        }
        if ($difficulty === 'Intermediate') {
            $score -= 10;
        }

        // Adjust for resource requirements
        if ($resourceLevel === 'High') {
            $score -= 15;
        }
        if ($resourceLevel === 'Medium') {
            $score -= 5;
        }

        // Adjust for academic level match
        $academicLevel = strtolower($project->type);
        if ($academicLevel === 'undergraduate' && $difficulty === 'Advanced') {
            $score -= 25;
        }

        return max(60, min(100, $score)); // Keep between 60-100%
    }

    private function extractKeywords(string $topic): array
    {
        // Simple keyword extraction
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

    private function generateTitleFromTopic(string $topic): string
    {
        // Simple title generation - could be enhanced with AI
        return ucwords(strtolower($topic));
    }

    /**
     * Generate AI-powered topic description to help students understand the topic
     */
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
- Maximum 150 words";

            $userPrompt = "Generate a description for this research topic:

TOPIC: {$topic}

CONTEXT:
- Field of Study: {$project->field_of_study}
- Course: {$project->course}
- University: {$project->full_university_name}
- Academic Level: ".ucfirst($project->type).'

The description should help a student understand what this topic involves and why they should consider choosing it.';

            $fullPrompt = $systemPrompt."\n\n".$userPrompt;

            // Build academic context for intelligent model selection
            $academicContext = [
                'field_of_study' => $project->field_of_study,
                'academic_level' => $project->type,
                'faculty' => $project->settings['faculty'] ?? '',
            ];

            $aiStartTime = microtime(true);
            $description = $this->aiGenerator->generateTopicDescriptionOptimized($fullPrompt, $academicContext);
            $aiEndTime = microtime(true);
            $aiDuration = ($aiEndTime - $aiStartTime) * 1000; // Convert to milliseconds

            $description = trim($description);
            $totalDuration = (microtime(true) - $startTime) * 1000;

            Log::info('AI Topic Description Generation - Success', [
                'topic' => Str::limit($topic, 50).'...',
                'project_id' => $project->id,
                'description_length' => strlen($description),
                'description_preview' => Str::limit($description, 100).'...',
                'total_time_ms' => round($totalDuration, 2),
                'ai_time_ms' => round($aiDuration, 2),
                'ai_percentage' => round(($aiDuration / $totalDuration) * 100, 1).'%',
                'tokens_used' => $response->usage->totalTokens ?? 'unknown',
                'prompt_tokens' => $response->usage->promptTokens ?? 'unknown',
                'completion_tokens' => $response->usage->completionTokens ?? 'unknown',
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

            // Fallback to a generic description
            return "This research topic focuses on {$project->field_of_study} and involves investigating current trends, methodologies, and practical applications in the field. The study will provide valuable insights and contribute to academic knowledge while developing your research and analytical skills.";
        }
    }

    /**
     * Get cached topics for academic context
     * Returns previously generated topics for similar academic contexts to improve performance
     */
    private function getCachedTopicsForAcademicContext(Project $project): array
    {
        // Get faculty and department from project settings
        $faculty = $project->settings['faculty'] ?? null;
        $department = $project->settings['department'] ?? null;

        // Look for topics with similar academic context stored directly in ProjectTopic
        $cachedTopics = ProjectTopic::where('course', $project->course)
            ->where('academic_level', $project->type)
            ->where('university', $project->university)
            ->when($faculty, fn ($q) => $q->where('faculty', $faculty))
            ->when($department, fn ($q) => $q->where('department', $department))
            ->when($project->field_of_study, fn ($q) => $q->where('field_of_study', $project->field_of_study))
            ->limit(10)
            ->get()
            ->map(function ($topic) {
                return [
                    'topic' => $topic->title,
                    'title' => $topic->title,
                    'description' => $topic->description ?? 'Research topic in '.$topic->field_of_study,
                ];
            })
            ->toArray();

        Log::info('Retrieved cached topics for academic context', [
            'project_id' => $project->id,
            'course' => $project->course,
            'university' => $project->university,
            'faculty' => $faculty,
            'department' => $department,
            'cached_topics_count' => count($cachedTopics),
        ]);

        return $cachedTopics;
    }

    /**
     * Store topics in database for future caching
     */
    private function storeTopicsInDatabase(array $topics, Project $project): void
    {
        try {
            // Get faculty and department from project settings
            $faculty = $project->settings['faculty'] ?? null;
            $department = $project->settings['department'] ?? null;

            foreach ($topics as $topic) {
                // Handle both string and array topic formats
                if (is_string($topic)) {
                    $topicData = [
                        'title' => $topic,
                        'description' => 'Research topic in '.($project->field_of_study ?? $project->course),
                        'difficulty' => 'moderate',
                        'timeline' => '6 months',
                        'resourceLevel' => 'moderate',
                        'feasibilityScore' => 75,
                        'keywords' => [],
                        'researchType' => 'applied',
                    ];
                } elseif (is_array($topic)) {
                    $topicData = $topic;
                } else {
                    // Skip invalid topic format
                    continue;
                }

                // Check if topic already exists to avoid duplicates
                $existingTopic = ProjectTopic::where('title', $topicData['title'])
                    ->where('course', $project->course)
                    ->where('academic_level', $project->type)
                    ->first();

                if (! $existingTopic) {
                    ProjectTopic::create([
                        'field_of_study' => $project->field_of_study,
                        'faculty' => $faculty,
                        'department' => $department,
                        'course' => $project->course,
                        'university' => $project->university,
                        'academic_level' => $project->type,
                        'title' => $topicData['title'],
                        'description' => $topicData['description'] ?? 'Research topic in '.($project->field_of_study ?? $project->course),
                        'difficulty' => $topicData['difficulty'] ?? 'moderate',
                        'timeline' => $topicData['timeline'] ?? '6 months',
                        'resource_level' => $topicData['resourceLevel'] ?? 'moderate',
                        'feasibility_score' => $topicData['feasibilityScore'] ?? 75,
                        'keywords' => $topicData['keywords'] ?? [],
                        'research_type' => $topicData['researchType'] ?? 'applied',
                        'selection_count' => 0,
                        'last_selected_at' => null,
                    ]);
                }
            }

            Log::info('Topics stored in database', [
                'project_id' => $project->id,
                'topics_count' => count($topics),
                'course' => $project->course,
                'university' => $project->university,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store topics in database', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw the exception as this is not critical for the main flow
        }
    }

    /**
     * Check if user has made a recent topic request (within 1.5-5 minutes)
     * Indicates they reviewed previous topics and want fresh alternatives
     */
    private function hasRecentTopicRequest(Project $project): bool
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

        // Check for any request within last 5 minutes (too recent for fresh topics)
        $veryRecentRequest = DB::table('user_topic_requests')
            ->where('user_id', $userId)
            ->where('academic_context_hash', $academicContextHash)
            ->where('created_at', '>', $ninetySecondsAgo) // Within last 90 seconds
            ->orderBy('created_at', 'desc')
            ->first();

        // Check for any request older than 5 minutes (indicates user wants fresh topics)
        $olderRequest = DB::table('user_topic_requests')
            ->where('user_id', $userId)
            ->where('academic_context_hash', $academicContextHash)
            ->where('created_at', '<', $fiveMinutesAgo) // Older than 5 minutes
            ->orderBy('created_at', 'desc')
            ->first();

        // Generate fresh topics if:
        // 1. There's an older request (>5 min ago) - user had time to review
        // 2. No very recent request (<90 sec ago) - not spam clicking
        $hasRecentRequest = $olderRequest !== null && $veryRecentRequest === null;

        Log::info('Recent request check result', [
            'has_recent_request' => $hasRecentRequest,
            'very_recent_request' => $veryRecentRequest ? $veryRecentRequest->created_at : null,
            'older_request' => $olderRequest ? $olderRequest->created_at : null,
            'decision_reason' => $hasRecentRequest ? 'User had time to review (>5min) and not spam clicking (<90sec)' : 'No qualifying requests found',
        ]);

        return $hasRecentRequest;
    }

    /**
     * Track when user requests topics for smart future decisions
     */
    private function trackTopicRequest(Project $project): void
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
                'university' => $project->university,
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

    /**
     * Generate a consistent hash for academic context to enable intelligent caching
     */
    private function generateAcademicContextHash(Project $project): string
    {
        $contextData = [
            'course' => $project->course,
            'university' => $project->university,
            'academic_level' => $project->type,
            'field_of_study' => $project->field_of_study,
        ];

        return hash('sha256', json_encode($contextData));
    }
}
