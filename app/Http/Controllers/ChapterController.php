<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Jobs\CollectPapersForProject;
use App\Models\Chapter;
use App\Models\ChatConversation;
use App\Models\ChatFileUpload;
use App\Models\Project;
use App\Services\AI\SystemPromptService;
use App\Services\AIContentGenerator;
use App\Services\ChapterReviewService;
use App\Services\ChapterContentAnalysisService;
use App\Services\ChapterCitationService;
use App\Services\CitationWhitelistService;
use App\Services\DocumentAnalysisService;
use App\Services\FacultyStructureService;
use App\Services\PaperCollectionService;
use App\Services\ProjectOutlineService;
use App\Services\ProjectTypeDetector;
use App\Services\PromptSystem\PromptRouter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Orientation;
use Spatie\LaravelPdf\Facades\Pdf;

class ChapterController extends Controller
{
    private $aiGenerator;

    private $paperService;

    public function __construct(
        AIContentGenerator $aiGenerator,
        PaperCollectionService $paperService,
        private ProjectOutlineService $outlineService,
        private ChapterReviewService $reviewService,
        private DocumentAnalysisService $documentService,
        private ProjectTypeDetector $projectTypeDetector,
        private FacultyStructureService $facultyStructureService,
        private PromptRouter $promptRouter,
        private SystemPromptService $systemPromptService
    ) {
        $this->aiGenerator = $aiGenerator;
        $this->paperService = $paperService;
    }

    /**
     * AI CHAPTER GENERATION - AUTO MODE
     * When user chooses auto mode, AI generates complete chapters
     * Supports both progressive (chapter by chapter) and bulk generation
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'chapter_number' => 'required|integer|min:1|max:10',
            'generation_type' => 'required|in:single,bulk,progressive',
            // Continuation parameters
            'continue_from_position' => 'sometimes|boolean',
            'cursor_position' => 'sometimes|integer',
            'context_text' => 'sometimes|string',
            'current_content' => 'sometimes|string',
            'target_words' => 'sometimes|integer',
            'prepare_only' => 'sometimes|boolean',
        ]);

        $project = Project::with(['chapters', 'category'])->findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Ensure project topic is approved before chapter generation
        abort_if($project->status !== 'topic_approved', 400, 'Project topic must be approved first');

        // Ensure papers are collected before generation
        $this->ensurePapersAreCollected($project);

        switch ($validated['generation_type']) {
            case 'single':
                $chapter = $this->generateSingleChapter($project, $validated['chapter_number']);

                return response()->json(['chapter' => $chapter]);

            case 'progressive':
                $chapter = $this->generateProgressiveChapter($project, $validated['chapter_number']);

                return response()->json(['chapter' => $chapter]);

            case 'bulk':
                $chapters = $this->generateAllChapters($project);

                return response()->json(['chapters' => $chapters]);

            default:
                return response()->json(['error' => 'Invalid generation type'], 400);
        }
    }

    /**
     * Fixed streaming endpoint with proper SSE implementation
     */
    public function stream(Request $request, Project $project, int $chapterNumber)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        // Extend execution time for long chapter generation (10 minutes)
        set_time_limit(600);
        ini_set('max_execution_time', 600);

        // Streaming requests should not start until verified papers are available.
        // The frontend should have kicked off paper collection, but we also guard
        // here to avoid races where prompts are built before papers are stored.
        Log::info('STREAM - Preflight paper collection status', [
            'project_id' => $project->id,
            'paper_collection_status' => $project->paper_collection_status,
            'generation_type' => $request->input('generation_type'),
        ]);

        $validated = $request->validate([
            'generation_type' => 'required|in:single,progressive,section,improve,rephrase,expand',
            'section_type' => 'sometimes|string',
            'selected_text' => 'sometimes|string|max:1000',
            'style' => 'sometimes|string',
        ]);

        if (in_array($validated['generation_type'], ['single', 'progressive', 'section'], true)) {
            $waitSeconds = 20;
            $start = microtime(true);
            while (true) {
                $project->refresh();
                $hasRecentPapers = $project->collectedPapers()->recent()->exists();

                if ($hasRecentPapers) {
                    break;
                }

                if ($project->paper_collection_status !== 'collecting_papers') {
                    break;
                }

                if ((microtime(true) - $start) >= $waitSeconds) {
                    break;
                }

                usleep(250_000); // 250ms
            }

            $project->refresh();
            $hasRecentPapers = $project->collectedPapers()->recent()->exists();

            if (! $hasRecentPapers) {
                // If collection is still running, tell the client to wait.
                if ($project->paper_collection_status === 'collecting_papers') {
                    abort(423, 'Verified source collection is still in progress. Please wait for it to complete, then retry generation.');
                }

                // If collection failed or never started, generation can still proceed, but citations will be constrained.
                Log::warning('STREAM - No recent verified papers available for prompt injection', [
                    'project_id' => $project->id,
                    'paper_collection_status' => $project->paper_collection_status,
                ]);
            }
        }

        // Get or create chapter
        $chapter = Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => '',
                'word_count' => 0,
                'status' => 'draft',
            ]
        );

        // Build the prompt based on generation type
        if ($request->input('generation_type') === 'section' && $request->has('section_type')) {
            // Section-specific generation
            $prompt = $this->buildSectionPrompt($project, $chapterNumber, $request->input('section_type'));
        } elseif ($request->input('generation_type') === 'improve') {
            // Chapter improvement generation
            $style = $request->input('style');
            if ($style === 'Auto') { $style = null; }
            $prompt = $this->buildImprovePrompt($project, $chapter, $style);
        } elseif ($request->input('generation_type') === 'rephrase') {
            // Text rephrasing generation
            $selectedText = $request->input('selected_text', '');
            $style = $request->input('style', 'Academic Formal');

            Log::info('🎯 REPHRASE - Starting text rephrasing', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'selected_text_length' => strlen($selectedText),
                'selected_text_preview' => substr($selectedText, 0, 100).(strlen($selectedText) > 100 ? '...' : ''),
                'style' => $style,
                'word_count' => str_word_count($selectedText),
                'user_id' => auth()->id(),
            ]);

            $prompt = $this->buildRephrasePrompt($project, $selectedText, $style);
        } elseif ($request->input('generation_type') === 'expand') {
            // Text expansion generation
            $selectedText = $request->input('selected_text', '');

            Log::info('📈 EXPAND - Starting text expansion', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'selected_text_length' => strlen($selectedText),
                'selected_text_preview' => substr($selectedText, 0, 100).(strlen($selectedText) > 100 ? '...' : ''),
                'word_count' => str_word_count($selectedText),
                'user_id' => auth()->id(),
            ]);

            $style = $request->input('style');
            if ($style === 'Auto') { $style = null; }
            $prompt = $this->buildExpandPrompt($project, $selectedText, $chapter, $style);
        } else {
            // Regular chapter generation
            $style = $request->input('style');
            // If style is "Auto", treat it as null so we don't enforce a specific style
            if ($style === 'Auto') {
                $style = null;
            }

            $prompt = $validated['generation_type'] === 'progressive'
                ? $this->buildProgressivePrompt($project, $chapterNumber, $style)
                : $this->buildSinglePrompt($project, $chapterNumber, $style);
        }

        // Log before starting stream
        Log::info('STREAM - About to start streaming response', [
            'chapter_id' => $chapter->id,
            'chapter_number' => $chapterNumber,
            'generation_type' => $request->input('generation_type'),
            'section_type' => $request->input('section_type'),
        ]);

        // Return streaming response
        return response()->stream(function () use ($chapter, $prompt, $chapterNumber, $request, $project) {
            Log::info('STREAM - Inside streaming function', [
                'chapter_id' => $chapter->id,
                'generation_type' => $request->input('generation_type'),
            ]);

            // Clean any existing output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Start output buffering for streaming
            ob_start();

            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable Nginx buffering

            Log::info('STREAM - Headers set, starting generation', [
                'chapter_id' => $chapter->id,
            ]);

            // Check if AI service is available
            if (! $this->aiGenerator->isAvailable()) {
                $this->sendSSEMessage([
                    'type' => 'error',
                    'message' => 'AI services are currently unavailable. Please check your internet connection and try again.',
                    'code' => 'OFFLINE_MODE',
                ]);
                $this->sendSSEMessage(['type' => 'complete']);

                return;
            }

            // Send initial ping
            $this->sendSSEMessage(['type' => 'start', 'message' => 'Initializing AI generation...']);

            try {
                // Check if this is section generation that should append to existing content
                $isSecondGeneration = $request->input('generation_type') === 'section' && ! empty($chapter->content);
                $isRephrasing = $request->input('generation_type') === 'rephrase';
                $isExpanding = $request->input('generation_type') === 'expand';

                // Generate unique generation ID for tracking
                $generationId = \Illuminate\Support\Str::uuid()->toString();

                // Check if resuming from previous generation
                $resumeFrom = $request->input('resume_from', 0);
                $isResuming = $resumeFrom > 0 && ! empty($chapter->content);

                // Initialize content appropriately
                if ($isResuming) {
                    // Resume from existing partial content
                    $fullContent = $chapter->content;
                    $newSectionContent = '';
                    Log::info('📥 RESUME - Resuming generation from previous content', [
                        'resume_from_words' => $resumeFrom,
                        'existing_content_length' => strlen($fullContent),
                    ]);
                } elseif ($isSecondGeneration) {
                    // For section generation, start with existing content
                    $fullContent = $chapter->content;
                    $newSectionContent = '';
                } elseif ($isRephrasing) {
                    // For rephrasing, we'll replace the content entirely with the new version
                    $fullContent = '';
                    $newSectionContent = '';
                    Log::info('🔄 REPHRASE - Initialized for text replacement');
                } elseif ($isExpanding) {
                    // For expanding, we'll replace the content entirely with the new version
                    $fullContent = '';
                    $newSectionContent = '';
                    Log::info('📈 EXPAND - Initialized for text replacement');
                } else {
                    // For new chapter generation, start fresh
                    $fullContent = '';
                    $newSectionContent = '';
                }

                // Mark generation as in progress (for chapter/progressive generation only)
                $shouldTrackGeneration = in_array($request->input('generation_type'), ['progressive', 'single', 'section']);
                if ($shouldTrackGeneration) {
                    $chapter->update([
                        'generation_in_progress' => true,
                        'generation_id' => $generationId,
                        'generation_started_at' => now(),
                        'generation_last_saved_words' => 0,
                    ]);
                }

                // Log the start of AI generation
                Log::info('AI Generation - Starting stream request', [
                    'chapter_id' => $chapter->id,
                    'chapter_number' => $chapterNumber,
                    'generation_type' => $request->input('generation_type'),
                    'generation_id' => $generationId,
                    'is_section_append' => $isSecondGeneration,
                    'is_resuming' => $isResuming,
                    'existing_content_length' => strlen($chapter->content ?? ''),
                    'prompt_length' => strlen($prompt),
                ]);

                // Adjust prompt for resume to avoid repeating existing content
                $promptToUse = $prompt;
                if ($isResuming && $request->input('generation_type') === 'progressive') {
                    $plainContent = trim(strip_tags($chapter->content ?? ''));
                    $currentWordCount = str_word_count($plainContent);
                    $targetWordCount = $this->getChapterWordCount($project, $chapterNumber);
                    $remainingWords = max(200, $targetWordCount - $currentWordCount);
                    $contextWindow = 800;
                    $contextText = substr($plainContent, -$contextWindow);
                    $cursorPosition = strlen($plainContent);

                    $promptToUse = $this->buildContinuationPrompt(
                        $project,
                        $chapterNumber,
                        $cursorPosition,
                        $contextText,
                        $plainContent,
                        $remainingWords
                    );
                }

                // Use optimized generation based on chapter type
                $chapterType = $this->getChapterType($chapterNumber);

                // Periodic save configuration
                $lastSaveWordCount = $resumeFrom; // Start from resume point
                $saveInterval = 500; // Save every 500 words
                $lastSaveTime = time();
                $saveTimeInterval = 30; // Also save every 30 seconds

                // For progressive generation, use simplified single-pass generation with stopping logic
                if ($request->input('generation_type') === 'progressive' && ! $isResuming) {
                    // ALWAYS recalculate target word count to use updated values (don't trust old DB values)
                    $targetWordCount = $this->getChapterWordCount($project, $chapterNumber);
                    $maxWordCount = intval($targetWordCount * 1.1); // 110% of target (stop point)

                    Log::info('PROGRESSIVE STREAM - Starting simplified generation', [
                        'target_word_count' => $targetWordCount,
                        'maximum_word_count' => $maxWordCount,
                        'chapter_id' => $chapter->id,
                        'chapter_number' => $chapterNumber,
                        'generation_id' => $generationId,
                    ]);

                    $fullContent = $this->generateStreamingContentSimplified($project, $promptToUse, $chapterType, $targetWordCount, $maxWordCount);
                    $wordCount = $this->computeWordCount($fullContent);
                } else {
                    // Use regular streaming generation for other types
                    $chunkCount = 0;
                    $wordCount = 0;
                    $systemPrompt = $this->getSystemPromptForGenerationType($project, (string) $request->input('generation_type'));
                    $messages = [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $promptToUse],
                    ];

                    foreach ($this->aiGenerator->generateOptimizedMessages($messages, $chapterType) as $chunk) {
                        $chunkCount++;

                        if ($isSecondGeneration) {
                            // For section generation, collect the new content separately
                            $newSectionContent .= $chunk;
                            $fullContent = $chapter->content."\n\n".$newSectionContent;
                        } else {
                            // For full chapter generation, build normally
                            $fullContent .= $chunk;
                        }

                        $wordCount = $this->computeWordCount($fullContent);

                        // PERIODIC AUTO-SAVE: Save every 500 words or 30 seconds
                        $currentTime = time();
                        $shouldSave = ($wordCount - $lastSaveWordCount >= $saveInterval) ||
                                      ($currentTime - $lastSaveTime >= $saveTimeInterval);

                        if ($shouldSave && $shouldTrackGeneration) {
                            try {
                                $chapter->update([
                                    'content' => $fullContent,
                                    'word_count' => $wordCount,
                                    'status' => 'draft',
                                    'generation_last_saved_words' => $wordCount,
                                ]);
                                $lastSaveWordCount = $wordCount;
                                $lastSaveTime = $currentTime;

                                Log::debug('💾 AUTO-SAVE - Periodic save during generation', [
                                    'chapter_id' => $chapter->id,
                                    'word_count' => $wordCount,
                                    'generation_id' => $generationId,
                                ]);

                                // Notify client of save
                                $this->sendSSEMessage([
                                    'type' => 'autosave',
                                    'word_count' => $wordCount,
                                    'generation_id' => $generationId,
                                ]);
                            } catch (\Exception $saveError) {
                                Log::warning('Auto-save failed during generation', [
                                    'error' => $saveError->getMessage(),
                                    'chapter_id' => $chapter->id,
                                ]);
                            }
                        }

                        // Log rephrase progress every 10 chunks
                        if ($isRephrasing && $chunkCount % 10 === 0) {
                            Log::info('📝 REPHRASE - Generation progress', [
                                'chunk_count' => $chunkCount,
                                'current_length' => strlen($fullContent),
                                'word_count' => $wordCount,
                                'chunk_preview' => substr($chunk, 0, 50).'...',
                            ]);
                        }

                        // Log expand progress every 10 chunks
                        if ($isExpanding && $chunkCount % 10 === 0) {
                            Log::info('📈 EXPAND - Generation progress', [
                                'chunk_count' => $chunkCount,
                                'current_length' => strlen($fullContent),
                                'word_count' => $wordCount,
                                'chunk_preview' => substr($chunk, 0, 50).'...',
                            ]);
                        }

                        // Send content update
                        $this->sendSSEMessage([
                            'type' => 'content',
                            'content' => $chunk,
                            'word_count' => $wordCount,
                            'is_section_append' => $isSecondGeneration,
                            'generation_id' => $generationId,
                        ]);

                        // Add delay to slow down streaming for better UX
                        usleep(50000); // 50ms delay between chunks

                        // Flush every 100 words to avoid timeout
                        if ($wordCount % 100 === 0) {
                            $this->sendSSEMessage(['type' => 'heartbeat']);
                        }
                    }
                }

                // Save the generated content and clear generation tracking
                $chapter->update([
                    'content' => $fullContent,
                    'word_count' => $wordCount,
                    'status' => 'draft',
                    'ai_generated' => true,
                    'last_ai_generation' => now(),
                    // Clear generation tracking
                    'generation_in_progress' => false,
                    'generation_id' => null,
                    'generation_started_at' => null,
                    'generation_last_saved_words' => 0,
                ]);

                Log::info('Generation output preview', [
                    'chapter_id' => $chapter->id,
                    'chapter_number' => $chapterNumber,
                    'generation_type' => $request->input('generation_type'),
                    'starts_with_html' => str_starts_with(trim($fullContent), '<'),
                    'preview' => substr($fullContent, 0, 200),
                ]);

                app(ChapterCitationService::class)->validateGeneratedCitations($chapter);

                // Update section progress if this was section generation
                if ($isSecondGeneration) {
                    $this->outlineService->updateSectionProgress($project, $chapterNumber, $fullContent);

                    Log::info('Section progress updated after generation', [
                        'chapter_id' => $chapter->id,
                        'generation_type' => 'section',
                        'final_word_count' => $wordCount,
                    ]);
                }

                // Log completion for rephrase
                if ($isRephrasing) {
                    Log::info('✅ REPHRASE - Generation completed successfully', [
                        'chapter_id' => $chapter->id,
                        'original_text_length' => strlen($request->input('selected_text', '')),
                        'rephrased_text_length' => strlen($fullContent),
                        'final_word_count' => $wordCount,
                        'style' => $request->input('style', 'Academic Formal'),
                        'generation_time' => microtime(true) - LARAVEL_START,
                        'rephrased_preview' => substr($fullContent, 0, 100).(strlen($fullContent) > 100 ? '...' : ''),
                    ]);
                }

                // Log completion for expand
                if ($isExpanding) {
                    Log::info('✅ EXPAND - Generation completed successfully', [
                        'chapter_id' => $chapter->id,
                        'original_text_length' => strlen($request->input('selected_text', '')),
                        'expanded_text_length' => strlen($fullContent),
                        'final_word_count' => $wordCount,
                        'expansion_ratio' => strlen($fullContent) / max(strlen($request->input('selected_text', '')), 1),
                        'generation_time' => microtime(true) - LARAVEL_START,
                        'expanded_preview' => substr($fullContent, 0, 100).(strlen($fullContent) > 100 ? '...' : ''),
                    ]);
                }

                // Send completion message
                $completionMessage = 'Generation complete!';
                if ($isRephrasing) {
                    $completionMessage = 'Text rephrasing complete!';
                } elseif ($isExpanding) {
                    $completionMessage = 'Text expansion complete!';
                }

                $this->sendSSEMessage([
                    'type' => 'complete',
                    'message' => $completionMessage,
                    'final_word_count' => $wordCount,
                    'content_updated' => true,
                ]);

            } catch (\Exception $e) {
                // RESILIENCE: Try to save any partial content that was generated
                $savedWords = 0;
                $hasPartialContent = isset($fullContent) && ! empty($fullContent) && $this->computeWordCount($fullContent) > 100;

                if ($hasPartialContent && isset($shouldTrackGeneration) && $shouldTrackGeneration) {
                    try {
                        $savedWords = $this->computeWordCount($fullContent);
                        $chapter->update([
                            'content' => $fullContent,
                            'word_count' => $savedWords,
                            'status' => 'draft',
                            'generation_in_progress' => false, // Mark as not in progress
                            'generation_last_saved_words' => $savedWords,
                        ]);

                        Log::info('💾 RECOVERY - Saved partial content on error', [
                            'chapter_id' => $chapter->id,
                            'saved_words' => $savedWords,
                            'error' => $e->getMessage(),
                        ]);
                    } catch (\Exception $saveError) {
                        Log::error('Failed to save partial content during error recovery', [
                            'error' => $saveError->getMessage(),
                            'chapter_id' => $chapter->id,
                        ]);
                    }
                } else {
                    // Clear generation tracking even if no partial content
                    try {
                        $chapter->update([
                            'generation_in_progress' => false,
                            'generation_id' => null,
                        ]);
                    } catch (\Exception $e) {
                        // Ignore errors here
                    }
                }

                if ($request->input('generation_type') === 'rephrase') {
                    Log::error('❌ REPHRASE - Generation failed', [
                        'error' => $e->getMessage(),
                        'chapter_id' => $chapter->id,
                        'selected_text_length' => strlen($request->input('selected_text', '')),
                        'style' => $request->input('style', 'Academic Formal'),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $this->sendSSEMessage([
                        'type' => 'error',
                        'message' => 'Text rephrasing failed. Please try again.',
                    ]);
                } elseif ($request->input('generation_type') === 'expand') {
                    Log::error('❌ EXPAND - Generation failed', [
                        'error' => $e->getMessage(),
                        'chapter_id' => $chapter->id,
                        'selected_text_length' => strlen($request->input('selected_text', '')),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    $this->sendSSEMessage([
                        'type' => 'error',
                        'message' => 'Text expansion failed. Please try again.',
                    ]);
                } else {
                    Log::error('AI Generation failed', [
                        'error' => $e->getMessage(),
                        'chapter_id' => $chapter->id,
                        'partial_content_saved' => $hasPartialContent,
                        'saved_words' => $savedWords,
                        'generation_id' => $generationId ?? null,
                    ]);

                    $this->sendSSEMessage([
                        'type' => 'error',
                        'message' => 'Generation failed. Please try again.',
                        'partial_saved' => $hasPartialContent,
                        'saved_word_count' => $savedWords,
                        'can_resume' => $hasPartialContent,
                        'generation_id' => $generationId ?? null,
                    ]);
                }
            }

            // End stream
            $this->sendSSEMessage(['type' => 'end']);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Quick action (non-stream): rephrase selected text.
     * Uses POST body to avoid URL length limits and returns JSON.
     */
    public function rephraseQuickAction(Request $request, Project $project, int $chapterNumber)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
            'style' => 'sometimes|string|max:100',
        ]);

        $style = $validated['style'] ?? 'Academic Formal';

        $chapter = Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => '',
                'word_count' => 0,
                'status' => 'draft',
            ]
        );

        try {
            $prompt = $this->buildRephrasePrompt($project, $validated['text'], $style);
            $messages = [
                ['role' => 'system', 'content' => $this->getSystemPromptForGenerationType($project, 'rephrase')],
                ['role' => 'user', 'content' => $prompt],
            ];
            $text = trim($this->aiGenerator->generateMessages($messages, [
                'temperature' => 0.8,
                'max_tokens' => 900,
            ]));

            return response()->json([
                'success' => true,
                'text' => $text,
                'word_count' => str_word_count(strip_tags($text)),
                'style' => $style,
                'chapter_id' => $chapter->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Quick action rephrase failed', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'chapter_id' => $chapter->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to rephrase text. Please try again.',
            ], 500);
        }
    }

    /**
     * Quick action (non-stream): expand selected text.
     * Uses POST body to avoid URL length limits and returns JSON.
     */
    public function expandQuickAction(Request $request, Project $project, int $chapterNumber)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $chapter = Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => '',
                'word_count' => 0,
                'status' => 'draft',
            ]
        );

        try {
            $prompt = $this->buildExpandPrompt($project, $validated['text'], $chapter);
            $messages = [
                ['role' => 'system', 'content' => $this->getSystemPromptForGenerationType($project, 'expand')],
                ['role' => 'user', 'content' => $prompt],
            ];
            $text = trim($this->aiGenerator->generateMessages($messages, [
                'temperature' => 0.7,
                'max_tokens' => 1100,
            ]));

            return response()->json([
                'success' => true,
                'text' => $text,
                'word_count' => str_word_count(strip_tags($text)),
                'chapter_id' => $chapter->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Quick action expand failed', [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'chapter_id' => $chapter->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to expand text. Please try again.',
            ], 500);
        }
    }

    private function sendSSEMessage($data)
    {
        echo 'data: '.json_encode($data)."\n\n";

        // Only flush if there's actually a buffer to flush
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    private function getSystemPromptForGenerationType(Project $project, string $generationType): string
    {
        return match ($generationType) {
            'rephrase', 'expand', 'improve' => $this->systemPromptService->getEditorSystemPrompt(),
            default => $this->systemPromptService->getChapterSystemPrompt(
                $project,
                $this->promptRouter->getSystemPrompt($project)
            ),
        };
    }

    /**
     * CHAPTER WRITING MODE
     * For creating new chapter content with AI assistance
     */
    public function write(Project $project, int $chapterNumber)
    {
        Log::info('📝 WRITE METHOD - Starting', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'project_mode' => $project->mode,
            'chapter_number' => $chapterNumber,
            'user_id' => auth()->id(),
        ]);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Get or create chapter for writing
        $chapterModel = Chapter::firstOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $this->getDefaultChapterTitle($chapterNumber),
            'content' => '',
            'word_count' => 0,
            'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

        Log::info('✅ WRITE METHOD - Chapter retrieved/created', [
            'chapter_id' => $chapterModel->id,
            'chapter_number' => $chapterModel->chapter_number,
            'was_created' => $chapterModel->wasRecentlyCreated,
        ]);

        // Redirect to manual editor for manual mode projects
        if ($project->mode === 'manual') {
            $redirectUrl = route('projects.manual-editor.show', [
                'project' => $project->slug,
                'chapter' => $chapterModel->id,
            ]);

            Log::info('🔀 WRITE METHOD - Redirecting to manual editor', [
                'redirect_url' => $redirectUrl,
                'chapter_id' => $chapterModel->id,
            ]);

            return redirect()->route('projects.manual-editor.show', [
                'project' => $project->slug,
                'chapter' => $chapterModel->id,
            ]);
        }

        Log::info('📄 WRITE METHOD - Rendering ChapterEditor for auto mode');

        $allChapters = $project->chapters()->orderBy('chapter_number')->get();
        $outlines = $project->load(['outlines.sections'])->outlines->map(function ($outline) {
            return [
                'id' => $outline->id,
                'chapter_number' => $outline->chapter_number,
                'chapter_title' => $outline->chapter_title,
                'target_word_count' => $outline->target_word_count,
                'completion_threshold' => $outline->completion_threshold,
                'description' => $outline->description,
                'sections' => $outline->sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_number' => $section->section_number,
                        'section_title' => $section->section_title,
                        'section_description' => $section->section_description,
                        'target_word_count' => $section->target_word_count,
                        'current_word_count' => $section->current_word_count,
                        'is_completed' => $section->is_completed,
                        'is_required' => $section->is_required,
                    ];
                }),
            ];
        });

        return Inertia::render('projects/ChapterEditor', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'type' => $project->type,
                'status' => $project->status,
                'mode' => $project->mode,
                'field_of_study' => $project->field_of_study,
                'university' => $project->universityRelation?->name,
                'course' => $project->course,
                'settings' => $project->settings,
                'outlines' => $outlines,
            ],
            'chapter' => $chapterModel,
            'allChapters' => $allChapters,
            'facultyChapters' => $this->facultyStructureService->getChapterStructure($project),
            'mode' => 'write', // Indicate this is writing mode
        ]);
    }

    /**
     * CHAPTER EDITING - MANUAL MODE
     * For users who want to edit existing chapter content
     */
    public function edit(Project $project, int $chapterNumber)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Get or create chapter
        $chapter = Chapter::firstOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $this->getDefaultChapterTitle($chapterNumber),
            'content' => '',
            'word_count' => 0,
            'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

        // Redirect to manual editor for manual mode projects
        if ($project->mode === 'manual') {
            return redirect()->route('projects.manual-editor.show', [
                'project' => $project->slug,
                'chapter' => $chapter->id,
            ]);
        }

        $allChapters = $project->chapters()->orderBy('chapter_number')->get();
        $outlines = $project->load(['outlines.sections'])->outlines->map(function ($outline) {
            return [
                'id' => $outline->id,
                'chapter_number' => $outline->chapter_number,
                'chapter_title' => $outline->chapter_title,
                'target_word_count' => $outline->target_word_count,
                'completion_threshold' => $outline->completion_threshold,
                'description' => $outline->description,
                'sections' => $outline->sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_number' => $section->section_number,
                        'section_title' => $section->section_title,
                        'section_description' => $section->section_description,
                        'target_word_count' => $section->target_word_count,
                        'current_word_count' => $section->current_word_count,
                        'is_completed' => $section->is_completed,
                        'is_required' => $section->is_required,
                    ];
                }),
            ];
        });

        return Inertia::render('projects/ChapterEditor', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'type' => $project->type,
                'status' => $project->status,
                'mode' => $project->mode,
                'field_of_study' => $project->field_of_study,
                'university' => $project->universityRelation?->name,
                'course' => $project->course,
                'settings' => $project->settings,
                'outlines' => $outlines,
            ],
            'chapter' => $chapter,
            'allChapters' => $allChapters,
            'facultyChapters' => $this->facultyStructureService->getChapterStructure($project),
            'mode' => 'edit', // Indicate this is edit mode
        ]);
    }

    /**
     * CHAPTER AI GENERATION EDITOR (AUTO MODE)
     * Clean editor surface to migrate generation UI incrementally.
     */
    public function aiGenerate(Project $project, int $chapterNumber)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Redirect to manual editor for manual mode projects
        if ($project->mode === 'manual') {
            $chapter = Chapter::firstOrCreate([
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ], [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => '',
                'word_count' => 0,
                'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
                'status' => 'draft',
            ]);

            return redirect()->route('projects.manual-editor.show', [
                'project' => $project->slug,
                'chapter' => $chapter->id,
            ]);
        }

        $chapter = Chapter::firstOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $this->getDefaultChapterTitle($chapterNumber),
            'content' => '',
            'word_count' => 0,
            'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

        $allChapters = $project->chapters()->orderBy('chapter_number')->get();
        $outlines = $project->load(['outlines.sections'])->outlines->map(function ($outline) {
            return [
                'id' => $outline->id,
                'chapter_number' => $outline->chapter_number,
                'chapter_title' => $outline->chapter_title,
                'target_word_count' => $outline->target_word_count,
                'completion_threshold' => $outline->completion_threshold,
                'description' => $outline->description,
                'sections' => $outline->sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'section_number' => $section->section_number,
                        'section_title' => $section->section_title,
                        'section_description' => $section->section_description,
                        'target_word_count' => $section->target_word_count,
                        'current_word_count' => $section->current_word_count,
                        'is_completed' => $section->is_completed,
                        'is_required' => $section->is_required,
                    ];
                }),
            ];
        });

        return Inertia::render('projects/ChapterAIGenerationEditor', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'type' => $project->type,
                'status' => $project->status,
                'mode' => $project->mode,
                'field_of_study' => $project->field_of_study,
                'university' => $project->universityRelation?->name,
                'course' => $project->course,
                'settings' => $project->settings,
                'outlines' => $outlines,
            ],
            'chapter' => $chapter,
            'allChapters' => $allChapters,
            'facultyChapters' => $this->facultyStructureService->getChapterStructure($project),
            'mode' => 'edit',
        ]);
    }

    /**
     * SAVE CHAPTER CONTENT
     * Auto-save functionality for both auto and manual modes
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'chapter_number' => 'required|integer|min:1|max:10',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'auto_save' => 'boolean',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $chapter = Chapter::updateOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $validated['chapter_number'],
        ], [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'word_count' => $this->computeWordCount($validated['content']),
            'target_word_count' => $this->getChapterWordCount($project, $validated['chapter_number']),
            'status' => $validated['auto_save'] ? 'draft' : 'in_review',
            'updated_at' => now(),
        ]);

        // Update project status if first chapter completed
        if ($validated['chapter_number'] === 1 && ! $validated['auto_save']) {
            $project->update(['status' => 'writing']);
        }

        return response()->json([
            'success' => true,
            'chapter' => $chapter,
            'word_count' => $chapter->word_count,
        ]);
    }

    /**
     * Mark a chapter as complete once minimum word count is met.
     */
    public function markComplete(Request $request, Project $project, int $chapter)
    {
        Log::info('🟢 [MARK COMPLETE] Method called', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'chapter_number' => $chapter,
            'user_id' => auth()->id(),
        ]);

        abort_if($project->user_id !== auth()->id(), 403);

        $chapterModel = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapter)
            ->firstOrFail();

        Log::info('🟢 [MARK COMPLETE] Chapter found', [
            'chapter_id' => $chapterModel->id,
            'chapter_title' => $chapterModel->title,
            'current_status' => $chapterModel->status,
            'word_count' => $chapterModel->word_count,
        ]);

        // Align minimum requirement with ManualEditor: flat 500 words
        $minimumWords = 500;

        if ($chapterModel->word_count < $minimumWords) {
            Log::warning('🔴 [MARK COMPLETE] Word count validation failed', [
                'chapter_id' => $chapterModel->id,
                'word_count' => $chapterModel->word_count,
                'min_required' => $minimumWords,
            ]);

            return redirect()->back()->with('error', "Chapter must have at least {$minimumWords} words to be marked as complete. Current: {$chapterModel->word_count} words");
        }

        Log::info('🟢 [MARK COMPLETE] Updating chapter status to completed');

        $chapterModel->update([
            'status' => ChapterStatus::Completed->value,
        ]);

        Log::info('✅ [MARK COMPLETE] Chapter marked as complete successfully', [
            'chapter_id' => $chapterModel->id,
            'new_status' => $chapterModel->status,
        ]);

        return redirect()->back()->with('success', 'Chapter marked as complete successfully!');
    }

    /**
     * GENERATE SINGLE CHAPTER WITH AI
     * Creates one chapter at a time for progressive writing
     */
    private function generateSingleChapter(Project $project, int $chapterNumber): Chapter
    {
        $prompt = $this->buildSinglePrompt($project, $chapterNumber);
        $aiContent = $this->callAiService($project, $prompt);

        $chapter = Chapter::updateOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $this->getDefaultChapterTitle($chapterNumber),
            'content' => $aiContent,
            'word_count' => $this->computeWordCount($aiContent),
            'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

        app(ChapterCitationService::class)->validateGeneratedCitations($chapter);

        return $chapter;
    }

    /**
     * GENERATE PROGRESSIVE CHAPTER
     * Builds on previous chapters for contextual writing
     */
    public function generateProgressiveChapter(Project $project, int $chapterNumber, $collectedPapers = null, ?string $chapterTitle = null, ?int $targetWordCount = null, ?string $style = null): Chapter
    {
        // Get previous chapters for context
        $previousChapters = Chapter::where('project_id', $project->id)
            ->where('chapter_number', '<', $chapterNumber)
            ->orderBy('chapter_number')
            ->get();

        // Get collected papers if not provided
        if ($collectedPapers === null) {
            $collectedPapers = \App\Models\CollectedPaper::forProject($project->id)->get();
        }

        $prompt = $this->buildProgressiveChapterPrompt($project, $chapterNumber, $previousChapters, $collectedPapers, $style);
        $targetWordCount = $targetWordCount ?? $this->getChapterWordCount($project, $chapterNumber);

        // Use word count validation to ensure we reach at least 90% of target
        $aiContent = $this->callAiServiceWithWordTarget($project, $prompt, $targetWordCount);

        $chapter = Chapter::updateOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $chapterTitle ?? $this->getDefaultChapterTitle($chapterNumber),
            'content' => $aiContent,
            'word_count' => $this->computeWordCount($aiContent),
            'target_word_count' => $targetWordCount ?? $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

        app(ChapterCitationService::class)->validateGeneratedCitations($chapter);

        return $chapter;
    }

    /**
     * GENERATE ALL CHAPTERS AT ONCE
     * Bulk generation for users who want complete draft
     */
    private function generateAllChapters(Project $project): array
    {
        $chapters = [];
        $totalChapters = $this->getChapterCount($project);

        for ($i = 1; $i <= $totalChapters; $i++) {
            $chapter = $this->generateProgressiveChapter($project, $i);
            $chapters[] = $chapter;
        }

        // Update project status after bulk generation
        $project->update(['status' => 'writing']);

        return $chapters;
    }

    /**
     * BUILD AI PROMPT FOR CHAPTER GENERATION
     * Creates contextual prompts based on project details and faculty structure
     */
    private function buildChapterPrompt(Project $project, int $chapterNumber, ?string $style = null): string
    {
        // Get faculty structure for context-aware generation
        $facultyStructureService = app(\App\Services\FacultyStructureService::class);
        $chapterStructure = $facultyStructureService->getChapterStructure($project);
        $terminology = $facultyStructureService->getTerminology($project);

        // Find specific chapter details from faculty structure
        $chapterDetails = collect($chapterStructure)->firstWhere('number', $chapterNumber);
        $chapterTitle = $chapterDetails['title'] ?? $this->getDefaultChapterTitle($chapterNumber);
        $targetWordCount = $chapterDetails['word_count'] ?? $this->getChapterWordCount($project, $chapterNumber);
        $chapterDescription = $chapterDetails['description'] ?? '';

        $prompt = "Generate Chapter {$chapterNumber}: {$chapterTitle} for an academic {$project->type} project.

Project Details:
- Title: {$project->title}
- Topic: {$project->topic}
- Field of Study: {$project->field_of_study}
- Faculty: {$project->getEffectiveFaculty()}
- University: {$project->universityRelation?->name}
- Course: {$project->course}
- Academic Level: {$project->type}";

        if ($style) {
            $guidelines = $this->getStyleGuidelines($style);
            $prompt .= "\n\nWRITING STYLE REQUIREMENTS ({$style}):\n";
            $prompt .= $guidelines;
        } else {
             // Default academic tone if no style specified
             $prompt .= "\n- Writing Style: Academic Formal";
        }

        // Add chapter-specific context from faculty structure
        if ($chapterDescription) {
            $prompt .= "\n\nChapter Description: {$chapterDescription}";
        }

        // Add faculty-specific terminology if available
        if (! empty($terminology)) {
            $prompt .= "\n\nFaculty-Specific Terminology:";
            foreach (array_slice($terminology, 0, 10) as $term => $definition) {
                $prompt .= "\n- {$term}: {$definition}";
            }
        }

        $prompt .= "\n\nCHAPTER REQUIREMENT:\n";
        $prompt .= "- Target word count: {$targetWordCount} words\n\n";
        $prompt .= 'Write the complete chapter content now. Follow the system instructions.';

        return $prompt;
    }

    /**
     * BUILD PROGRESSIVE CHAPTER PROMPT
     * Includes context from previous chapters and collected papers for citations
     */
    private function buildProgressiveChapterPrompt(Project $project, int $chapterNumber, $previousChapters, $collectedPapers = null, ?string $style = null): string
    {
        $chapter = $this->ensureChapterForPrompt($project, $chapterNumber);
        $basePrompt = $this->buildChapterPrompt($project, $chapterNumber, $style);

        // Add previous chapters context
        if ($previousChapters->count() > 0) {
            $context = "\n\nPrevious Chapters Context:\n";
            foreach ($previousChapters as $prev) {
                $context .= "Chapter {$prev->chapter_number}: {$prev->title}\n";
                $context .= 'Summary: '.substr(strip_tags($prev->content), 0, 500)."...\n\n";
            }
            $basePrompt .= $context;
            $basePrompt .= 'Ensure this chapter builds logically on the previous content.';
        }

        // Add collected papers for citations
        if ($collectedPapers && $collectedPapers->count() > 0) {
            app(CitationWhitelistService::class)->prepareWhitelistForChapter($chapter, $collectedPapers->take(10));

            $citationContext = "\n\nREFERENCE SOURCES FOR CITATIONS:\n";
            $citationContext .= "Use ONLY the following verified research papers for citations in this chapter:\n\n";

            foreach ($collectedPapers->take(10) as $paper) { // Limit to top 10 for prompt length
                $citationContext .= "• {$paper->title}\n";
                $citationContext .= "  Authors: {$paper->authors}\n";
                $citationContext .= "  Year: {$paper->year}\n";
                if ($paper->abstract) {
                    $citationContext .= '  Abstract: '.substr($paper->abstract, 0, 200)."...\n";
                }
                $citationContext .= "  Citation format: ({$paper->authors}, {$paper->year})\n\n";
            }

            $basePrompt .= $citationContext;
            $basePrompt .= "\nCITATION REQUIREMENTS:\n";
            $basePrompt .= "- Use ONLY the papers listed above for citations\n";
            $basePrompt .= "- Include 3-5 relevant citations per major point\n";
            $basePrompt .= "- Format citations as (Author, Year) within the text\n";
            $basePrompt .= "- Ensure citations support and strengthen your arguments\n";
            $basePrompt .= "- Never fabricate or make up citations - use only provided sources\n\n";
        }

        return $basePrompt;
    }

    /**
     * CALL AI SERVICE FOR CONTENT GENERATION
     * Mock implementation - replace with actual AI API
     */
    private function callAiService(Project $project, string $prompt, array $options = [], ?string $systemPrompt = null): string
    {
        try {
            $systemPrompt = $systemPrompt ?? $this->systemPromptService->getChapterSystemPrompt(
                $project,
                $this->promptRouter->getSystemPrompt($project)
            );

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ];

            $genOptions = array_merge([
                'model' => 'gpt-4o',
                'temperature' => 0.7,
                'max_tokens' => 8000, // Increased for longer content
            ], $options);

            Log::info('Generating real AI content for chapter (message envelope)', [
                'system_prompt_length' => strlen($systemPrompt),
                'user_prompt_length' => strlen($prompt),
                'message_count' => count($messages),
                'model' => $genOptions['model'] ?? null,
            ]);

            $response = $this->aiGenerator->generateMessages($messages, $genOptions);

            Log::info('AI content generated successfully', ['response_length' => strlen($response)]);

            return $response;
        } catch (\Exception $e) {
            Log::error('AI content generation failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
                'project_id' => $project->id,
            ]);

            // Fallback to a basic template instead of showing the prompt
            return '<h2>Chapter Content Generation Error</h2>
            <p>Unable to generate AI content at this time. Please try again later or contact support.</p>
            <p>Error: '.$e->getMessage().'</p>';
        }
    }

    /**
     * Generate chapter content with target word count validation
     * Ensures at least 90% of target word count is reached
     */
    public function callAiServiceWithWordTarget(Project $project, string $prompt, int $targetWordCount): string
    {
        $minWordCount = intval($targetWordCount * 0.9); // 90% of target
        $maxAttempts = 3;
        $attempt = 1;
        $systemPrompt = $this->systemPromptService->getChapterSystemPrompt(
            $project,
            $this->promptRouter->getSystemPrompt($project)
        );

        Log::info('Starting AI generation with word count target', [
            'target_word_count' => $targetWordCount,
            'min_word_count' => $minWordCount,
            'prompt_length' => strlen($prompt),
        ]);

        while ($attempt <= $maxAttempts) {
            try {
                $content = $this->callAiService($project, $prompt, [], $systemPrompt);
                $wordCount = str_word_count(strip_tags($content));

                Log::info("AI generation attempt {$attempt}", [
                    'generated_word_count' => $wordCount,
                    'target_word_count' => $targetWordCount,
                    'min_required' => $minWordCount,
                    'meets_target' => $wordCount >= $minWordCount,
                ]);

                // If we've reached at least 90% of target, return the content
                if ($wordCount >= $minWordCount) {
                    Log::info('Target word count achieved', [
                        'final_word_count' => $wordCount,
                        'target_percentage' => round(($wordCount / $targetWordCount) * 100, 1).'%',
                    ]);

                    return $content;
                }

                // If content is too short, try extending it
                if ($attempt < $maxAttempts) {
                    $extensionPrompt = $this->buildExtensionPrompt($prompt, $content, $targetWordCount, $wordCount);
                    $prompt = $extensionPrompt; // Use extension prompt for next attempt
                }

                $attempt++;
            } catch (\Exception $e) {
                Log::error("AI generation attempt {$attempt} failed", [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                $attempt++;
            }
        }

        // If all attempts failed to reach target, return the last attempt
        Log::warning('Failed to reach target word count after all attempts', [
            'final_attempt' => $maxAttempts,
            'target_word_count' => $targetWordCount,
        ]);

        return $this->callAiService($project, $prompt, [], $systemPrompt);
    }

    /**
     * Generate chapter content with word count target and real-time progress callback.
     *
     * This method is designed for bulk generation jobs where we need to broadcast
     * progress via WebSocket. It uses streaming internally and reports progress
     * at regular intervals through the callback.
     *
     * Key features:
     * - Tracks "high water mark" to never report word count decreases
     * - Uses streaming for initial generation (real-time progress)
     * - Uses blocking calls for extension attempts (no progress drops)
     * - Up to 3 attempts to reach 90% of target word count
     *
     * @param  string  $prompt  The generation prompt
     * @param  int  $targetWordCount  Target word count for the chapter
     * @param  callable|null  $onProgress  Callback: fn(int $wordCount, int $progressPercent, string $description) => void
     * @param  int  $progressIntervalWords  How often to report progress (in words)
     * @return string The generated content
     */
    public function generateWithRealtimeProgress(
        string $prompt,
        int $targetWordCount,
        ?callable $onProgress = null,
        int $progressIntervalWords = 150
    ): string {
        $minWordCount = intval($targetWordCount * 0.9);
        $maxAttempts = 3;
        $attempt = 1;

        // Track peak values to never report decreases (high water mark)
        $peakWordCount = 0;
        $peakChapterPercent = 20;

        Log::info('Starting AI generation with real-time progress', [
            'target_word_count' => $targetWordCount,
            'min_word_count' => $minWordCount,
            'max_attempts' => $maxAttempts,
            'progress_interval' => $progressIntervalWords,
        ]);

        // Helper to report progress while respecting high water mark
        $reportProgress = function (int $wordCount, string $phase = 'Generating') use ($targetWordCount, $onProgress, &$peakWordCount, &$peakChapterPercent) {
            if (! $onProgress) {
                return;
            }

            // Never report a decrease in word count
            if ($wordCount <= $peakWordCount) {
                return;
            }

            $peakWordCount = $wordCount;

            // Calculate progress percentage (20-80% range for content generation)
            $rawPercent = min(($wordCount / max($targetWordCount, 1)) * 100, 100);
            $chapterPercent = intval(20 + ($rawPercent * 0.6));

            // Never decrease chapter percent
            if ($chapterPercent > $peakChapterPercent) {
                $peakChapterPercent = $chapterPercent;
            } else {
                $chapterPercent = $peakChapterPercent;
            }

            $description = sprintf(
                '%s content... (%s / %s words)',
                $phase,
                number_format($peakWordCount),
                number_format($targetWordCount)
            );

            $onProgress($peakWordCount, $chapterPercent, $description);
        };

        // Create streaming callback that respects high water mark
        $streamingCallback = $onProgress ? function (int $wordCount, string $content) use ($reportProgress) {
            $reportProgress($wordCount, 'Generating');
        } : null;

        $content = '';
        $wordCount = 0;

        while ($attempt <= $maxAttempts) {
            try {
                Log::info("AI generation attempt {$attempt}", [
                    'target_word_count' => $targetWordCount,
                    'current_word_count' => $wordCount,
                    'peak_word_count' => $peakWordCount,
                ]);

                if ($attempt === 1) {
                    // First attempt: use streaming for real-time progress
                    $content = $this->aiGenerator->generateWithProgress(
                        $prompt,
                        [
                            'model' => 'gpt-4o',
                            'temperature' => 0.7,
                            'max_tokens' => 16000,
                        ],
                        $streamingCallback,
                        $progressIntervalWords
                    );
                } else {
                    // Extension attempts: use BLOCKING call to avoid progress drops
                    // The extension prompt asks AI to return complete expanded content
                    $extensionPrompt = $this->buildExtensionPrompt($prompt, $content, $targetWordCount, $wordCount);

                    // Report that we're extending (without dropping progress)
                    if ($onProgress) {
                        $onProgress($peakWordCount, $peakChapterPercent, 'Extending content to meet word target...');
                    }

                    Log::info("Extension attempt {$attempt}: using blocking generation", [
                        'current_word_count' => $wordCount,
                        'target' => $targetWordCount,
                    ]);

                    // Use blocking generate() - no streaming, no progress drops
                    $extendedContent = $this->aiGenerator->generate(
                        $extensionPrompt,
                        [
                            'model' => 'gpt-4o',
                            'temperature' => 0.7,
                            'max_tokens' => 16000,
                        ]
                    );

                    $extendedWordCount = str_word_count(strip_tags($extendedContent));

                    Log::info("Extension attempt {$attempt} result", [
                        'previous_word_count' => $wordCount,
                        'extended_word_count' => $extendedWordCount,
                        'improvement' => $extendedWordCount - $wordCount,
                    ]);

                    // Only use extended content if it's actually longer
                    if ($extendedWordCount > $wordCount) {
                        $content = $extendedContent;

                        // Report the improved word count
                        $reportProgress($extendedWordCount, 'Extended');
                    }
                }

                $wordCount = str_word_count(strip_tags($content));

                Log::info("AI generation attempt {$attempt} completed", [
                    'generated_word_count' => $wordCount,
                    'target_word_count' => $targetWordCount,
                    'min_required' => $minWordCount,
                    'meets_target' => $wordCount >= $minWordCount,
                ]);

                // If we've reached at least 90% of target, we're done
                if ($wordCount >= $minWordCount) {
                    Log::info('Target word count achieved', [
                        'final_word_count' => $wordCount,
                        'target_percentage' => round(($wordCount / $targetWordCount) * 100, 1).'%',
                    ]);

                    return $content;
                }

                // Content too short, try extension on next attempt
                $attempt++;

            } catch (\Exception $e) {
                Log::error("AI generation attempt {$attempt} failed", [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);

                if ($attempt >= $maxAttempts) {
                    // If we have any content, return it rather than failing
                    if (strlen($content) > 100) {
                        Log::warning('Returning partial content after failed attempts', [
                            'word_count' => str_word_count(strip_tags($content)),
                        ]);

                        return $content;
                    }
                    throw $e;
                }
                $attempt++;
            }
        }

        // Return whatever we have after all attempts
        Log::warning('Failed to reach target word count after all attempts', [
            'final_word_count' => $wordCount,
            'target_word_count' => $targetWordCount,
            'attempts' => $maxAttempts,
        ]);

        return $content;
    }

    /**
     * Build prompt for extending short content to reach target word count
     */
    private function buildExtensionPrompt(string $originalPrompt, string $currentContent, int $targetWordCount, int $currentWordCount): string
    {
        $additionalWords = $targetWordCount - $currentWordCount;

        return "The following chapter content was generated but is too short. Please expand it to reach the target word count.

CURRENT CONTENT ({$currentWordCount} words):
{$currentContent}

REQUIREMENTS:
- Target total word count: {$targetWordCount} words
- Current word count: {$currentWordCount} words
- Additional words needed: approximately {$additionalWords} words
- Expand existing sections with more depth, examples, and academic rigor
- Add new relevant subsections if needed
- Maintain academic quality and proper citations
- Ensure smooth flow and logical structure
- Do not repeat existing content

Please provide the complete expanded chapter content that meets the target word count of {$targetWordCount} words.

ORIGINAL PROMPT CONTEXT:
{$originalPrompt}";
    }

    /**
     * GET DEFAULT CHAPTER TITLES
     * Standard academic chapter structure
     */
    private function getDefaultChapterTitle(int $chapterNumber): string
    {
        $titles = [
            1 => 'Introduction',
            2 => 'Literature Review',
            3 => 'Methodology',
            4 => 'Design and Implementation',
            5 => 'Results and Analysis',
            6 => 'Conclusion and Recommendations',
        ];

        return $titles[$chapterNumber] ?? "Chapter {$chapterNumber}";
    }

    /**
     * GET CHAPTER COUNT BASED ON PROJECT TYPE
     * Different project types have different chapter requirements
     */
    private function getChapterCount(Project $project): int
    {
        return match ($project->type) {
            'undergraduate' => 5,
            'masters' => 6,
            'phd' => 8,
            default => 5
        };
    }

    /**
     * GET TARGET WORD COUNT PER CHAPTER
     * Based on project category and chapter number
     */
    public function getChapterWordCount(Project $project, int $chapterNumber): int
    {
        // Try to get faculty-specific word count first
        // Load faculty relationship if not loaded
        $project->loadMissing('facultyRelation.structure');

        $facultyStructure = $project->facultyRelation?->structure;

        // Load the specific chapter if faculty structure exists
        if ($facultyStructure) {
            $facultyStructure->load(['chapters' => function ($query) use ($chapterNumber) {
                $query->where('chapter_number', $chapterNumber);
            }]);
        }

        if ($facultyStructure && ! $facultyStructure->chapters->isEmpty()) {
            $facultyChapter = $facultyStructure->chapters->first();

            // Use FULL word count from faculty structure (removed artificial 60% reduction)
            return intval($facultyChapter->target_word_count);
        }

        // Fallback to FULL target word counts (removed artificial reduction)
        return match ($chapterNumber) {
            1 => 2800,  // Introduction - full target
            2 => 3500,  // Literature Review - full target
            3 => 3000,  // Methodology - full target
            4 => 3500,  // Results/Analysis - full target
            5 => 3000,  // Discussion - full target
            6 => 2500,  // Conclusion - full target
            default => 3000, // Default for any additional chapters
        };
    }

    /**
     * BUILD CONTEXT FOR STREAMING AI GENERATION
     * Creates contextual prompts with previous chapters for better continuity
     */
    private function buildChapterContext(Project $project, int $chapterNumber, string $generationType): string
    {
        $chapterTitle = $this->getDefaultChapterTitle($chapterNumber);
        $targetWordCount = $this->getChapterWordCount($project, $chapterNumber);

        $context = "Generate Chapter {$chapterNumber}: {$chapterTitle} for an academic {$project->type} project.\n\n";

        $context .= "Project Details:\n";
        $context .= "- Title: {$project->title}\n";
        $context .= "- Topic: {$project->topic}\n";
        $context .= "- Field of Study: {$project->field_of_study}\n";
        $context .= "- University: {$project->universityRelation?->name}\n";
        $context .= "- Course: {$project->course}\n";
        $context .= "- Academic Level: {$project->type}\n\n";

        // For progressive generation, include context from previous chapters
        if ($generationType === 'progressive' && $chapterNumber > 1) {
            $previousChapters = $project->chapters()
                ->where('chapter_number', '<', $chapterNumber)
                ->where('content', '!=', '')
                ->orderBy('chapter_number')
                ->get();

            if ($previousChapters->count() > 0) {
                $context .= "Previous Chapters Context:\n";
                foreach ($previousChapters as $prevChapter) {
                    $summary = substr($prevChapter->content, 0, 300).'...';
                    $context .= "Chapter {$prevChapter->chapter_number}: {$prevChapter->title}\n";
                    $context .= "Summary: {$summary}\n\n";
                }
            }
        }

        $context .= "Requirements:\n";
        $context .= "- Write in formal academic style with proper citations in APA format\n";
        $context .= "- Target approximately {$targetWordCount} words\n";
        $context .= "- Use clear headings and subheadings\n";
        $context .= "- Ensure content flows logically from any previous chapters\n";
        $context .= "- Write comprehensive, well-researched content\n";
        $context .= "- Include relevant examples and case studies where appropriate\n\n";

        $context .= "CITATION REQUIREMENTS:\n";
        $context .= "- ONLY cite from the verified papers provided above - never invent or reference other sources\n";
        $context .= "- Format all citations in proper APA style: (Author, Year) for in-text citations\n";
        $context .= "- Use exact author names and years as provided in the verified papers list\n";
        $context .= "- If no relevant papers are available from the verified list, write '[Citation needed]' instead\n";
        $context .= "- Do NOT fabricate or create any citations outside the provided verified list\n\n";

        $context .= 'Please write the complete chapter content now:';

        return $context;
    }

    public function buildProgressivePrompt($project, $chapterNumber, ?string $style = null)
    {
        $chapter = $this->ensureChapterForPrompt($project, $chapterNumber);

        // Get previous chapters for context
        $previousChapters = Chapter::where('project_id', $project->id)
            ->where('chapter_number', '<', $chapterNumber)
            ->orderBy('chapter_number')
            ->get();

        $prompt = "You are writing Chapter {$chapterNumber} of an academic thesis.\n\n";
        $prompt .= "Project Topic: {$project->topic}\n";
        $prompt .= "Field of Study: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n\n";

        // Add collected papers for citation constraint
        $prompt .= $this->getCollectedPapersForAI($project, $chapterNumber, null, $chapter);

        if ($previousChapters->isNotEmpty()) {
            $prompt .= "Previous Chapters Context:\n";
            foreach ($previousChapters as $prev) {
                // Include summary of previous chapters
                $summary = $this->summarizeChapter($prev->content);
                $prompt .= "Chapter {$prev->chapter_number} ({$prev->title}): {$summary}\n\n";
            }
            $prompt .= "Build upon the previous chapters and maintain consistency.\n\n";
        }

        $prompt .= $this->getFacultySpecificInstructions($project, $chapterNumber);

        $targetWordCount = $this->getChapterWordCount($project, $chapterNumber);

        $prompt .= "\n\n⚠️ CRITICAL LENGTH REQUIREMENT ⚠️\n";
        $prompt .= "TARGET: You MUST write AT LEAST {$targetWordCount} words for this ENTIRE chapter.\n";
        $prompt .= "DO NOT STOP until you reach this word count.\n\n";

        // Add context-aware content generation instructions based on project type
        $prompt .= $this->projectTypeDetector->getContextualInstructions($project, $chapterNumber);

        // Add intelligent prompt system instructions (faculty-specific templates, tables, diagrams)
        $prompt .= $this->promptRouter->buildPrompt($project, $chapterNumber);

        return $prompt;
    }

    private function buildSinglePrompt($project, $chapterNumber, ?string $style = null)
    {
        $chapter = $this->ensureChapterForPrompt($project, $chapterNumber);

        $prompt = "You are writing Chapter {$chapterNumber} of an academic thesis.\n\n";
        $prompt .= "Project Topic: {$project->topic}\n";
        $prompt .= "Field of Study: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Course: {$project->course}\n\n";

        // Add collected papers for citation constraint
        $prompt .= $this->getCollectedPapersForAI($project, $chapterNumber, null, $chapter);

        $prompt .= $this->getChapterSpecificInstructions($chapterNumber);

        // Add context-aware content generation instructions based on project type
        $prompt .= $this->projectTypeDetector->getContextualInstructions($project, $chapterNumber);

        if ($style) {
            $guidelines = $this->getStyleGuidelines($style);
            $prompt .= "\n\nWRITING STYLE REQUIREMENTS ({$style}):\n";
            $prompt .= $guidelines;
        }

        // Add intelligent prompt system instructions (faculty-specific templates, tables, diagrams)
        $prompt .= $this->promptRouter->buildPrompt($project, $chapterNumber);

        return $prompt;
    }

    private function buildImprovePrompt(Project $project, Chapter $chapter, ?string $style = null): string
    {
        $chapterContent = $chapter->content ?? '';
        $chapterTitle = $chapter->title ?? $this->getDefaultChapterTitle($chapter->chapter_number);

        // Determine chapter type and get specific improvement checklist
        $chapterType = $this->getChapterType($chapter->chapter_number);
        $improvementChecklist = $this->getImprovementChecklist($chapterType);

        $prompt = "Improve Chapter {$chapter->chapter_number}: {$chapterTitle}.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Course: {$project->course}\n\n";

        if ($style) {
            $guidelines = $this->getStyleGuidelines($style);
            $prompt .= "WRITING STYLE TARGET ({$style}):\n";
            $prompt .= $guidelines . "\n\n";
        }

        $prompt .= "CURRENT CHAPTER CONTENT:\n";
        $prompt .= "===================================\n";
        $prompt .= $chapterContent;
        $prompt .= "\n===================================\n\n";

        $prompt .= "IMPROVEMENT TASK:\n";
        $prompt .= "Analyze the current chapter content against this academic writing checklist for {$chapterType} chapters:\n\n";
        $prompt .= $improvementChecklist;

        $prompt .= "\nIMPROVEMENT INSTRUCTIONS:\n";
        $prompt .= "1. SELECTIVE IMPROVEMENTS: Only rewrite and improve sections that need enhancement\n";
        $prompt .= "2. PRESERVE QUALITY CONTENT: Keep existing good content unchanged\n";
        $prompt .= "3. TARGETED EDITS: Focus on areas that fail the checklist requirements\n";
        $prompt .= "4. ACADEMIC STANDARDS: Ensure all improvements meet high academic writing standards\n";
        $prompt .= "5. STRUCTURAL INTEGRITY: Maintain the overall chapter structure and flow\n";
        $prompt .= "6. CITATION ACCURACY: Verify and improve citations where needed\n";
        $prompt .= "7. CLARITY & COHERENCE: Enhance readability and logical progression\n\n";

        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "Provide the improved chapter content with:\n";
        $prompt .= "- Enhanced academic language and style\n";
        $prompt .= "- Better transitions between sections\n";
        $prompt .= "- Improved argument structure and evidence\n";
        $prompt .= "- Corrected grammar and formatting\n";
        $prompt .= "- Strengthened conclusions and analysis\n\n";

        $prompt .= "Remember: Only improve what needs improvement. Your goal is to elevate the chapter to publication-ready academic standards.\n";

        return $prompt;
    }

    private function buildRephrasePrompt(Project $project, string $selectedText, string $style): string
    {
        $prompt = "Rephrase the selected text.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Course: {$project->course}\n\n";

        $prompt .= "SELECTED TEXT TO REPHRASE:\n";
        $prompt .= "===================================\n";
        $prompt .= $selectedText;
        $prompt .= "\n===================================\n\n";

        $prompt .= "REPHRASING TASK:\n";
        $prompt .= "Rephrase the selected text above in the '{$style}' style while:\n\n";

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. PRESERVE MEANING: Keep the exact same meaning and key information\n";
        $prompt .= "2. ENHANCE STYLE: Improve the writing style to match '{$style}' standards\n";
        $prompt .= "3. IMPROVE CLARITY: Make the text clearer and more readable\n";
        $prompt .= "4. MAINTAIN LENGTH: Keep similar length (±20% word count is acceptable)\n";
        $prompt .= "5. ACADEMIC TONE: Ensure appropriate academic language for {$project->type} level\n";
        $prompt .= "6. PROPER GRAMMAR: Fix any grammatical issues or awkward phrasing\n";
        $prompt .= "7. LOGICAL FLOW: Improve sentence structure and logical progression\n\n";

        $style_guidelines = $this->getStyleGuidelines($style);
        $prompt .= "STYLE GUIDELINES FOR '{$style}':\n";
        $prompt .= $style_guidelines."\n\n";

        $prompt .= "OUTPUT INSTRUCTIONS:\n";
        $prompt .= "- Provide ONLY the rephrased text, no explanations or comments\n";
        $prompt .= "- Do not include quotation marks or any formatting markup\n";
        $prompt .= "- Ensure the rephrased text flows naturally and reads professionally\n";
        $prompt .= "- The output should be ready to replace the original selected text directly\n\n";

        $prompt .= "Remember: Your goal is to enhance the writing while preserving the original meaning and information.\n";

        return $prompt;
    }

    private function buildExpandPrompt(Project $project, string $selectedText, Chapter $chapter, ?string $style = null): string
    {
        $prompt = "Expand the selected text.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Course: {$project->course}\n";
        $prompt .= "Chapter: {$chapter->title}\n\n";

        if ($style) {
            $guidelines = $this->getStyleGuidelines($style);
            $prompt .= "WRITING STYLE TARGET ({$style}):\n";
            $prompt .= $guidelines . "\n\n";
        }

        $prompt .= "CURRENT CHAPTER CONTEXT:\n";
        $prompt .= "===================================\n";
        $current_content = strip_tags($chapter->content ?: '');
        $context_preview = strlen($current_content) > 500
            ? substr($current_content, 0, 500).'...'
            : $current_content;
        $prompt .= $context_preview;
        $prompt .= "\n===================================\n\n";

        $prompt .= "SELECTED TEXT TO EXPAND:\n";
        $prompt .= "===================================\n";
        $prompt .= $selectedText;
        $prompt .= "\n===================================\n\n";

        $prompt .= "EXPANSION TASK:\n";
        $prompt .= "Expand the selected text above into a more comprehensive, cohesive academic passage that:\n\n";

        $selectedWordCount = str_word_count($selectedText);
        $targetWordCount = $selectedWordCount * 2;

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. MAINTAIN CORE MEANING: Preserve all key points and information from the original text\n";
        $prompt .= "2. PRESERVE STRUCTURE: If the text contains headers, subheadings, or formatting, maintain them exactly\n";
        $prompt .= "3. ADD DEPTH: Provide more detailed explanations, examples, or supporting information\n";
        $prompt .= "4. ACADEMIC RIGOR: Include appropriate academic language and scholarly depth\n";
        $prompt .= "5. LOGICAL FLOW: Ensure smooth transitions and coherent argument development\n";
        $prompt .= "6. CONTEXTUAL FIT: Align with the chapter context and overall project topic\n";
        $prompt .= "7. EVIDENCE-BASED: Add supporting details, examples, or theoretical backing where appropriate\n";
        $prompt .= "8. CONTROLLED LENGTH: Target exactly 2x the original word count (~{$targetWordCount} words from {$selectedWordCount} words)\n";
        $prompt .= "9. PROPER STRUCTURE: Use clear paragraphs and logical organization\n\n";

        $prompt .= "EXPANSION STRATEGIES:\n";
        $prompt .= "• Elaborate on key concepts with more detailed explanations\n";
        $prompt .= "• Add relevant examples or case studies to illustrate points\n";
        $prompt .= "• Include theoretical frameworks or methodological considerations\n";
        $prompt .= "• Provide background context or historical perspective where relevant\n";
        $prompt .= "• Discuss implications, significance, or applications\n";
        $prompt .= "• Add transitional sentences for better flow\n";
        $prompt .= "• Include qualifying statements or nuanced perspectives\n\n";

        $prompt .= "ACADEMIC STANDARDS:\n";
        $prompt .= "• Use formal academic tone appropriate for {$project->type} level\n";
        $prompt .= "• Employ discipline-specific terminology correctly\n";
        $prompt .= "• Maintain objective, analytical perspective\n";
        $prompt .= "• Follow logical argument structure\n";
        $prompt .= "• Use precise, clear language\n\n";

        $prompt .= "OUTPUT INSTRUCTIONS:\n";
        $prompt .= "- Provide ONLY the expanded text, no explanations or comments\n";
        $prompt .= "- Do not include quotation marks or any formatting markup\n";
        $prompt .= "- If the original text contains headers (like '### Introduction'), preserve them exactly\n";
        $prompt .= "- Target approximately {$targetWordCount} words (2x the original {$selectedWordCount} words)\n";
        $prompt .= "- Stop writing when you reach the target word count to avoid over-expansion\n";
        $prompt .= "- Ensure the expanded text flows naturally from and to surrounding content\n";
        $prompt .= "- The output should be ready to replace the original selected text directly\n\n";

        $prompt .= "Remember: Expand to exactly 2x length while preserving all structural elements (headers, formatting) and maintaining academic quality.\n";

        return $prompt;
    }

    private function getStyleGuidelines(string $style): string
    {
        $guidelines = [
            'Academic Formal' => "- Use formal academic vocabulary and sentence structures\n".
                               "- Employ third-person perspective and passive voice where appropriate\n".
                               "- Use complex sentence structures with subordinate clauses\n".
                               "- Avoid contractions, colloquialisms, and casual language\n".
                               "- Include appropriate hedging language (e.g., 'suggests', 'indicates')\n".
                               '- Maintain objective, scholarly tone throughout',

            'Academic Casual' => "- Use accessible academic language without overly complex jargon\n".
                               "- Allow some first-person perspective where appropriate\n".
                               "- Use clear, direct sentence structures\n".
                               "- Balance formal vocabulary with readable explanations\n".
                               "- Maintain academic credibility while being approachable\n".
                               '- Use active voice more frequently than formal academic style',

            'Technical' => "- Use precise, technical terminology specific to the field\n".
                         "- Employ clear, methodical explanations of processes and concepts\n".
                         "- Use numbered lists, bullet points, and structured presentations where helpful\n".
                         "- Focus on accuracy and precision over literary elegance\n".
                         "- Include specific measurements, data, and technical specifications\n".
                         '- Maintain logical, step-by-step progression of ideas',

            'Analytical' => "- Focus on critical examination and evaluation of evidence\n".
                          "- Use comparative and contrastive language structures\n".
                          "- Employ analytical transitions (however, furthermore, consequently)\n".
                          "- Present arguments with clear reasoning and evidence\n".
                          "- Use conditional and hypothetical language where appropriate\n".
                          '- Maintain balanced, objective evaluation of different perspectives',

            'Research-Heavy' => "- Emphasize evidence-based statements and claims\n".
                              "- Use language that supports citation and referencing\n".
                              "- Employ terminology that indicates research methodology\n".
                              "- Focus on findings, results, and scholarly conclusions\n".
                              "- Use language that connects to broader research contexts\n".
                              '- Maintain focus on empirical evidence and scholarly discourse',
        ];

        return $guidelines[$style] ?? $guidelines['Academic Formal'];
    }

    private function getImprovementChecklist(string $chapterType): string
    {
        $checklists = [
            'introduction' => "□ Clear problem statement with context and significance\n".
                             "□ Well-defined research objectives and questions\n".
                             "□ Appropriate background information and rationale\n".
                             "□ Clear thesis statement or research hypothesis\n".
                             "□ Overview of methodology and approach\n".
                             "□ Chapter structure preview\n".
                             "□ Academic tone and formal language\n".
                             '□ Proper citations for background claims',

            'literature_review' => "□ Comprehensive coverage of relevant literature\n".
                                  "□ Critical analysis rather than just summary\n".
                                  "□ Clear thematic organization\n".
                                  "□ Identification of research gaps\n".
                                  "□ Synthesis of conflicting viewpoints\n".
                                  "□ Proper APA citations throughout\n".
                                  "□ Logical flow between sections\n".
                                  '□ Connection to research questions',

            'methodology' => "□ Clear research design and rationale\n".
                            "□ Detailed data collection procedures\n".
                            "□ Appropriate sampling strategy\n".
                            "□ Data analysis methods explained\n".
                            "□ Ethical considerations addressed\n".
                            "□ Limitations acknowledged\n".
                            "□ Reproducible methodology\n".
                            '□ Justification for chosen methods',

            'results' => "□ Clear presentation of findings\n".
                        "□ Appropriate use of tables and figures\n".
                        "□ Objective reporting without interpretation\n".
                        "□ Statistical significance properly reported\n".
                        "□ Results organized logically\n".
                        "□ All research questions addressed\n".
                        "□ Data visualization is clear and accurate\n".
                        '□ No unsupported claims or speculation',

            'discussion' => "□ Interpretation of results in context\n".
                           "□ Connection to research questions and literature\n".
                           "□ Implications clearly articulated\n".
                           "□ Limitations honestly addressed\n".
                           "□ Suggestions for future research\n".
                           "□ Critical analysis of findings\n".
                           "□ Theoretical contributions identified\n".
                           '□ Practical applications discussed',

            'conclusion' => "□ Summary of main findings\n".
                           "□ Achievement of research objectives\n".
                           "□ Theoretical contributions highlighted\n".
                           "□ Practical implications stated\n".
                           "□ Research limitations acknowledged\n".
                           "□ Future research directions\n".
                           "□ No new information introduced\n".
                           '□ Strong closing statement',

            'general' => "□ Clear introduction and conclusion\n".
                        "□ Logical structure and flow\n".
                        "□ Strong evidence and argumentation\n".
                        "□ Proper academic language\n".
                        "□ Adequate citations and references\n".
                        "□ Coherent paragraph structure\n".
                        "□ Clear topic sentences\n".
                        '□ Effective transitions between ideas',
        ];

        return $checklists[$chapterType] ?? $checklists['general'];
    }

    /**
     * Get faculty-specific chapter instructions based on project field and chapter number
     */
    private function getFacultySpecificInstructions(Project $project, int $chapterNumber): string
    {
        // Get faculty structure through relationship
        $project->loadMissing('facultyRelation.structure.chapters.sections');

        $facultyStructure = $project->facultyRelation?->structure;

        if (! $facultyStructure) {
            // Fallback to generic instructions if no faculty structure found
            error_log("No faculty structure found for faculty: {$project->getEffectiveFaculty()}");

            return $this->getChapterSpecificInstructions($chapterNumber);
        }

        // Filter chapters by chapter number and academic level
        $facultyChapter = $facultyStructure->chapters
            ->where('chapter_number', $chapterNumber)
            ->filter(function ($chapter) use ($project) {
                return $chapter->academic_level === $project->type || $chapter->academic_level === 'all';
            })
            ->first();

        if (! $facultyChapter) {
            // Fallback to generic instructions if no chapter found
            error_log("No chapters found for faculty: {$project->getEffectiveFaculty()}, chapter: {$chapterNumber}, academic_level: {$project->type}");

            return $this->getChapterSpecificInstructions($chapterNumber);
        }

        error_log("Using database structure for faculty: {$project->getEffectiveFaculty()}, chapter: {$chapterNumber}, academic_level: {$project->type}");

        $instructions = "Write a comprehensive {$facultyChapter->chapter_title} chapter that includes:\n";

        foreach ($facultyChapter->sections as $section) {
            $instructions .= "• {$section->section_number} {$section->section_title}";

            if ($section->target_word_count > 0) {
                $instructions .= " ({$section->target_word_count} words)";
            }

            if ($section->description) {
                $instructions .= ": {$section->description}";
            }

            if ($section->writing_guidance) {
                $instructions .= " — {$section->writing_guidance}";
            }

            $instructions .= "\n";
        }

        $instructions .= "\nTotal Target: {$facultyChapter->target_word_count} words\n";

        // Add chapter-specific depth requirements
        $instructions .= $this->getChapterDepthRequirements($chapterNumber, $facultyChapter->chapter_title);

        // Add source availability and citation guidance
        $instructions .= $this->getSourceCitationGuidance($project, $chapterNumber);

        // Add originality and comprehensive analysis requirements
        $instructions .= $this->getOriginalityAndAnalysisRequirements($chapterNumber, $project->type);

        // Add References section if this is the last chapter
        $lastChapterNumber = $this->getLastChapterNumber($project);
        if ($chapterNumber === $lastChapterNumber) {
            $instructions .= $this->getReferencesGuidance($project);
        }

        $instructions .= "\nFACULTY-SPECIFIC GUIDANCE:\n";
        $instructions .= "- Follow the exact structure defined for {$facultyStructure->faculty_name} faculty\n";
        $fieldOfStudy = $project->field_of_study ?: $facultyStructure->faculty_name;
        $instructions .= "- Use terminology and conventions appropriate for {$fieldOfStudy} field\n";
        $instructions .= "- Ensure each section meets the specified word count for comprehensive coverage\n";
        $instructions .= "- Maintain academic rigor expected at {$project->type} level\n";

        return $instructions;
    }

    /**
     * Get the last chapter number for a project based on faculty structure
     */
    private function getLastChapterNumber(Project $project): int
    {
        // Load faculty relationship if not loaded
        $project->loadMissing('facultyRelation.structure.chapters');

        $facultyStructure = $project->facultyRelation?->structure;

        if (! $facultyStructure) {
            // Default to 5 chapters if no faculty structure found
            return 5;
        }

        $structure = $facultyStructure->getStructureForLevel($project->type);
        $chapters = $structure['chapters']['default'] ?? [];

        if (empty($chapters)) {
            return 5; // Default fallback
        }

        // Get the highest chapter number
        $maxChapterNumber = 0;
        foreach ($chapters as $chapter) {
            if (isset($chapter['number']) && $chapter['number'] > $maxChapterNumber) {
                $maxChapterNumber = $chapter['number'];
            }
        }

        return $maxChapterNumber ?: 5; // Default to 5 if no chapters found
    }

    /**
     * Get References section guidance for the last chapter
     */
    private function getReferencesGuidance(Project $project): string
    {
        $guidance = "\n📚 REFERENCES SECTION REQUIREMENT:\n";
        $guidance .= "CRITICAL: This is the LAST CHAPTER in your project structure. You MUST include a comprehensive References section at the end of this chapter.\n\n";

        // Get collected papers to determine reference formatting
        $collectedPapers = $project->collectedPapers()
            ->recent()
            ->forProject($project->id)
            ->get();

        if ($collectedPapers->isNotEmpty()) {
            $guidance .= "REFERENCES SECTION INSTRUCTIONS:\n";
            $guidance .= "Add a section titled 'REFERENCES' at the end of this chapter that includes:\n";
            $guidance .= "1. ALL sources cited throughout the entire project (from all chapters)\n";
            $guidance .= "2. Format all references in proper APA style\n";
            $guidance .= "3. List references alphabetically by author surname\n";
            $guidance .= "4. Include ONLY the {$collectedPapers->count()} verified papers provided in this prompt\n";
            $guidance .= "5. Each reference must follow this APA format:\n";
            $guidance .= "   Author, A. A. (Year). Title of paper. Journal Name, Volume(Issue), pages. DOI\n\n";

            $guidance .= "REFERENCE FORMATTING EXAMPLES:\n";
            foreach ($collectedPapers->take(3) as $index => $paper) {
                $guidance .= 'Example '.($index + 1).":\n";
                $guidance .= "{$paper->authors} ({$paper->year}). {$paper->title}. {$paper->venue}";
                if ($paper->doi) {
                    $guidance .= ". https://doi.org/{$paper->doi}";
                }
                $guidance .= "\n\n";
            }

            $guidance .= "IMPORTANT REFERENCE GUIDELINES:\n";
            $guidance .= "Only include sources that were actually cited in the text throughout all chapters. Do NOT add references that were not cited. Each reference must correspond to at least one in-text citation in your project.\n\n";
        } else {
            $guidance .= "REFERENCES SECTION INSTRUCTIONS:\n";
            $guidance .= "Add a section titled 'REFERENCES' at the end of this chapter.\n";
            $guidance .= "Since no verified papers are available, include a note: '[References will be added based on sources cited throughout the project]'\n";
            $guidance .= "Format any theoretical or foundational sources mentioned in proper APA style.\n\n";
        }

        return $guidance;
    }

    /**
     * Get chapter-specific depth requirements based on chapter type
     */
    private function getChapterDepthRequirements(int $chapterNumber, string $chapterTitle): string
    {
        $requirements = "\nCHAPTER-SPECIFIC DEPTH REQUIREMENTS:\n";

        switch ($chapterNumber) {
            case 2: // Literature Review
                $requirements .= "🔍 LITERATURE REVIEW - MAXIMUM DEPTH REQUIRED:\n";
                $requirements .= "- COMPREHENSIVE ANALYSIS: Each study must be thoroughly analyzed, not just mentioned\n";
                $requirements .= "- CRITICAL EVALUATION: Critically assess methodologies, findings, and limitations of each source\n";
                $requirements .= "- THEMATIC ORGANIZATION: Group studies by themes, not chronologically\n";
                $requirements .= "- SYNTHESIS: Connect different studies, identify patterns and contradictions\n";
                $requirements .= "- THEORETICAL DEPTH: Extensively discuss theoretical frameworks with 400-600 words per theory\n";
                $requirements .= "- COMPARATIVE ANALYSIS: Compare and contrast different approaches and findings\n";
                $requirements .= "- RESEARCH GAPS: Clearly identify and justify gaps with detailed explanations\n";
                $requirements .= "- CONCEPTUAL FRAMEWORK: Develop detailed conceptual model with relationships\n";
                $requirements .= "- MINIMUM 3-4 paragraphs per major study with detailed analysis\n";
                $requirements .= "- Each section should demonstrate deep scholarly engagement, not surface-level review\n";
                break;

            case 1: // Introduction
                $requirements .= "📍 INTRODUCTION - COMPREHENSIVE CONTEXT:\n";
                $requirements .= "- DETAILED BACKGROUND: Provide extensive context and current state of field\n";
                $requirements .= "- PROBLEM JUSTIFICATION: Thoroughly justify why the problem matters\n";
                $requirements .= "- CLEAR RATIONALE: Build compelling case for research necessity\n";
                $requirements .= "- SCOPE DEFINITION: Clearly define boundaries and focus areas\n";
                break;

            case 3: // Methodology
                $requirements .= "🔬 METHODOLOGY - DETAILED JUSTIFICATION:\n";
                $requirements .= "- DESIGN RATIONALE: Thoroughly justify methodological choices\n";
                $requirements .= "- DETAILED PROCEDURES: Step-by-step explanation of methods\n";
                $requirements .= "- VALIDATION MEASURES: Explain validity and reliability measures\n";
                $requirements .= "- ALTERNATIVE CONSIDERATIONS: Discuss why other methods were not used\n";
                break;

            case 4: // Results/Analysis/Implementation
                $requirements .= "📊 RESULTS/ANALYSIS - COMPREHENSIVE PRESENTATION:\n";
                $requirements .= "- DETAILED FINDINGS: Thorough presentation of all results\n";
                $requirements .= "- ANALYTICAL DEPTH: Deep analysis of patterns and relationships\n";
                $requirements .= "- VISUAL INTEGRATION: Reference and explain all tables/figures\n";
                $requirements .= "- INTERPRETATION: Provide meaningful interpretation of findings\n";
                break;

            case 5: // Discussion
                $requirements .= "💭 DISCUSSION - CRITICAL ANALYSIS:\n";
                $requirements .= "- DEEP INTERPRETATION: Thorough interpretation of results\n";
                $requirements .= "- LITERATURE CONNECTION: Connect findings to existing research\n";
                $requirements .= "- IMPLICATION ANALYSIS: Discuss theoretical and practical implications\n";
                $requirements .= "- LIMITATION DISCUSSION: Honest discussion of study limitations\n";
                break;

            default:
                $requirements .= "📝 COMPREHENSIVE WRITING:\n";
                $requirements .= "- DETAILED ANALYSIS: Provide thorough analysis throughout\n";
                $requirements .= "- SCHOLARLY DEPTH: Demonstrate deep understanding of subject matter\n";
                $requirements .= "- CRITICAL THINKING: Show critical evaluation and original insights\n";
        }

        return $requirements."\n";
    }

    /**
     * Get source availability and citation guidance for the chapter based on actual injected sources
     */
    private function getSourceCitationGuidance(Project $project, int $chapterNumber): string
    {
        $guidance = "SOURCE AVAILABILITY & CITATION STRATEGY:\n";

        // Get the actual papers that will be injected into the prompt
        $collectedPapers = $project->collectedPapers()
            ->recent()
            ->forProject($project->id)
            ->get();

        $collectedPapersCount = $collectedPapers->count();

        if ($collectedPapersCount === 0) {
            $guidance .= "❌ NO VERIFIED SOURCES AVAILABLE:\n";
            $guidance .= "- Write chapter without citations but maintain academic rigor\n";
            $guidance .= "- Use theoretical frameworks and established concepts\n";
            $guidance .= "- Note '[Citation needed]' where sources would strengthen arguments\n";
            $guidance .= "- Focus on developing strong logical arguments and methodology\n";
            $guidance .= "- This source limitation should be acknowledged in the research limitations\n\n";

            return $guidance;
        }

        // Analyze source characteristics
        $recentSources = $collectedPapers->filter(function ($paper) {
            return $paper->year >= (date('Y') - 7); // Within last 7 years
        })->count();

        $highQualitySources = $collectedPapers->filter(function ($paper) {
            return $paper->quality_score >= 0.7; // High quality threshold
        })->count();

        $sourcesWithAbstracts = $collectedPapers->filter(function ($paper) {
            return ! empty($paper->abstract) && strlen($paper->abstract) > 100;
        })->count();

        $guidance .= "📊 INJECTED SOURCE ANALYSIS:\n";
        $guidance .= "- Total verified sources: {$collectedPapersCount}\n";
        $guidance .= "- Recent sources (last 7 years): {$recentSources}\n";
        $guidance .= "- High quality sources (score ≥0.7): {$highQualitySources}\n";
        $guidance .= "- Sources with detailed abstracts: {$sourcesWithAbstracts}\n\n";

        if ($chapterNumber == 2) { // Literature Review needs special attention
            $guidance .= "📚 LITERATURE REVIEW CITATION STRATEGY:\n";

            if ($collectedPapersCount >= 15) {
                $guidance .= "✅ EXCELLENT SOURCE AVAILABILITY ({$collectedPapersCount} verified sources):\n";
                $guidance .= "- Use EXTENSIVE CITATIONS throughout (aim for 40+ citations with {$collectedPapersCount} sources)\n";
                $guidance .= "- Cite multiple sources per argument to show comprehensive coverage\n";
                $guidance .= "- Group sources thematically - use 3-4 sources per major theme/argument\n";
                $guidance .= "- Leverage the {$highQualitySources} high-quality sources for key theoretical points\n";
                $guidance .= "- Use {$recentSources} recent sources to show current state of research\n";
                $guidance .= "- Extract multiple insights from sources with detailed abstracts ({$sourcesWithAbstracts} available)\n";
            } elseif ($collectedPapersCount >= 8) {
                $guidance .= "⚠️ MODERATE SOURCE AVAILABILITY ({$collectedPapersCount} verified sources):\n";
                $guidance .= "- MAXIMIZE depth of analysis for each of the {$collectedPapersCount} available sources\n";
                $guidance .= "- Focus on the {$highQualitySources} high-quality sources for core arguments\n";
                $guidance .= "- Dedicate 2-3 paragraphs per high-quality source with detailed analysis\n";
                $guidance .= "- Use sources with abstracts ({$sourcesWithAbstracts}) for comprehensive analysis\n";
                $guidance .= "- Compensate limited quantity with EXCEPTIONAL analytical depth\n";
                $guidance .= "- Acknowledge the focused nature of available research as context\n";
            } else {
                $guidance .= "❗ LIMITED SOURCE AVAILABILITY ({$collectedPapersCount} sources available):\n";
                $guidance .= "- MAXIMIZE analysis depth for each of the {$collectedPapersCount} sources\n";
                $guidance .= "- Dedicate 3-4 detailed paragraphs per source with comprehensive analysis\n";
                $guidance .= "- Extract EVERY possible insight from available sources\n";
                if ($highQualitySources > 0) {
                    $guidance .= "- Leverage the {$highQualitySources} high-quality source(s) as foundation\n";
                }
                if ($sourcesWithAbstracts > 0) {
                    $guidance .= "- Use detailed abstracts from {$sourcesWithAbstracts} source(s) for comprehensive analysis\n";
                }
                $guidance .= "- Build extensive theoretical framework to supplement limited empirical sources\n";
                $guidance .= "- Frame limited sources as research gap opportunity\n";
                $guidance .= "- Use this limitation to justify your research contribution\n";
            }

            // Source quality-specific guidance
            if ($highQualitySources >= $collectedPapersCount * 0.7) {
                $guidance .= "🌟 HIGH-QUALITY SOURCE ADVANTAGE:\n";
                $guidance .= "- Excellent source quality enables authoritative arguments\n";
                $guidance .= "- Use high scores to build strong theoretical foundation\n";
            } elseif ($highQualitySources < $collectedPapersCount * 0.3) {
                $guidance .= "⚠️ QUALITY COMPENSATION NEEDED:\n";
                $guidance .= "- Some sources have lower quality scores - compensate with deeper analysis\n";
                $guidance .= "- Focus on extracting valid insights while acknowledging limitations\n";
            }

            // Recency analysis
            if ($recentSources >= $collectedPapersCount * 0.6) {
                $guidance .= "📅 CURRENT RESEARCH ADVANTAGE:\n";
                $guidance .= "- Strong representation of recent research enables contemporary analysis\n";
                $guidance .= "- Use recent sources to establish current state of the field\n";
            } elseif ($recentSources < $collectedPapersCount * 0.3) {
                $guidance .= "📅 HISTORICAL PERSPECTIVE:\n";
                $guidance .= "- Sources trend toward historical perspective\n";
                $guidance .= "- Frame as foundational research that established the field\n";
                $guidance .= "- Note the need for more recent research as a research gap\n";
            }

        } else {
            // For other chapters - contextual source usage
            if ($collectedPapersCount >= 10) {
                $guidance .= "✅ GOOD SOURCE AVAILABILITY ({$collectedPapersCount} sources): Use relevant citations strategically\n";
            } else {
                $guidance .= "⚠️ STRATEGIC SOURCE USAGE ({$collectedPapersCount} sources): Focus on most relevant citations\n";
            }
        }

        $guidance .= "\n📋 CITATION IMPLEMENTATION RULES:\n";
        $guidance .= "- ONLY cite from the {$collectedPapersCount} verified papers provided in the prompt\n";
        $guidance .= "- Each citation must add value - explain relevance to your argument\n";
        $guidance .= "- Use author-date format: (Author, Year) for in-text citations\n";
        $guidance .= "- Vary citation integration: synthesis, support, contrast, and analysis\n";
        $guidance .= "- For points lacking relevant sources, write '[Citation needed]' and continue\n";
        $guidance .= "- NEVER invent or guess citations - authenticity is paramount\n";

        return $guidance."\n";
    }

    /**
     * Get originality and comprehensive analysis requirements for academic rigor
     */
    private function getOriginalityAndAnalysisRequirements(int $chapterNumber, string $projectType): string
    {
        $requirements = "🎯 ORIGINALITY & COMPREHENSIVE ANALYSIS REQUIREMENTS:\n";

        // Base academic rigor requirements
        $requirements .= "💡 ORIGINALITY REQUIREMENTS:\n";
        $requirements .= "- UNIQUE PERSPECTIVE: Bring fresh insights and original interpretations to existing research\n";
        $requirements .= "- NOVEL CONNECTIONS: Draw innovative connections between different theories, concepts, or findings\n";
        $requirements .= "- CRITICAL STANCE: Don't just summarize - critique, question, and evaluate existing work\n";
        $requirements .= "- INTELLECTUAL CONTRIBUTION: Each section should add new understanding or perspective\n";
        $requirements .= "- SYNTHESIS INNOVATION: Create new frameworks by combining existing theories creatively\n";

        $requirements .= "\n🔬 COMPREHENSIVE ANALYSIS STANDARDS:\n";
        $requirements .= "- MULTI-LAYERED EXAMINATION: Analyze topics from multiple theoretical and practical angles\n";
        $requirements .= "- DEPTH OVER BREADTH: Better to analyze fewer topics thoroughly than many superficially\n";
        $requirements .= "- CONTEXTUAL ANALYSIS: Consider historical, cultural, social, and economic contexts\n";
        $requirements .= "- COMPARATIVE EVALUATION: Compare different approaches, methods, and findings systematically\n";
        $requirements .= "- IMPLICATIONS EXPLORATION: Discuss theoretical, practical, and methodological implications\n";

        // Chapter-specific originality requirements
        switch ($chapterNumber) {
            case 2: // Literature Review
                $requirements .= "\n📚 LITERATURE REVIEW ORIGINALITY:\n";
                $requirements .= "- THEMATIC INNOVATION: Organize literature using novel thematic frameworks\n";
                $requirements .= "- PATTERN IDENTIFICATION: Identify previously unnoticed patterns across studies\n";
                $requirements .= "- THEORETICAL SYNTHESIS: Create new theoretical frameworks by combining existing ones\n";
                $requirements .= "- CRITICAL GAPS ANALYSIS: Identify not just what's missing, but WHY it's missing\n";
                $requirements .= "- METHODOLOGICAL CRITIQUE: Analyze methodological trends and limitations across studies\n";
                $requirements .= "- EVOLUTION MAPPING: Trace how understanding has evolved and predict future directions\n";
                break;

            case 1: // Introduction
                $requirements .= "\n🎯 INTRODUCTION ORIGINALITY:\n";
                $requirements .= "- PROBLEM REFRAMING: Present the research problem from a unique angle\n";
                $requirements .= "- SIGNIFICANCE ARTICULATION: Articulate WHY this research matters beyond obvious reasons\n";
                $requirements .= "- CONTEXTUAL POSITIONING: Position your work within broader academic and societal contexts\n";
                break;

            case 3: // Methodology
                $requirements .= "\n🔧 METHODOLOGY ORIGINALITY:\n";
                $requirements .= "- APPROACH JUSTIFICATION: Defend methodological choices with sophisticated reasoning\n";
                $requirements .= "- INNOVATION DISCUSSION: Explain any novel adaptations or combinations of methods\n";
                $requirements .= "- PHILOSOPHICAL GROUNDING: Connect methodology to underlying philosophical assumptions\n";
                break;
        }

        // Project-type specific requirements
        if (in_array(strtolower($projectType), ['phd', 'doctoral', 'dissertation'])) {
            $requirements .= "\n🎓 DOCTORAL-LEVEL EXPECTATIONS:\n";
            $requirements .= "- PARADIGM-SHIFTING POTENTIAL: Each chapter should have potential to influence the field\n";
            $requirements .= "- THEORETICAL SOPHISTICATION: Demonstrate mastery of complex theoretical frameworks\n";
            $requirements .= "- METHODOLOGICAL INNOVATION: Show awareness of cutting-edge methodological developments\n";
            $requirements .= "- INTERDISCIPLINARY AWARENESS: Draw from multiple disciplines where appropriate\n";
        } elseif (in_array(strtolower($projectType), ['masters', 'msc', 'ma', 'thesis'])) {
            $requirements .= "\n🎓 MASTERS-LEVEL EXPECTATIONS:\n";
            $requirements .= "- SCHOLARLY MATURITY: Demonstrate deep understanding of key concepts and debates\n";
            $requirements .= "- ANALYTICAL SOPHISTICATION: Move beyond description to analysis and evaluation\n";
            $requirements .= "- RESEARCH COMPETENCY: Show ability to engage with and contribute to scholarly conversation\n";
        }

        $requirements .= "\n🏆 ACADEMIC EXCELLENCE MARKERS:\n";
        $requirements .= "- THIRD PERSON WRITING: Maintain formal academic voice using 'this study', 'the research', 'the findings' instead of 'I', 'we', 'our'\n";
        $requirements .= "- EVIDENCE-BASED ARGUMENTS: Every claim supported by evidence or logical reasoning\n";
        $requirements .= "- NUANCED DISCUSSION: Acknowledge complexity, contradictions, and limitations\n";
        $requirements .= "- PROFESSIONAL TONE: Maintain scholarly voice while being accessible\n";
        $requirements .= "- INTELLECTUAL HUMILITY: Acknowledge uncertainties and areas for further research\n";
        $requirements .= "- FORWARD-THINKING: Discuss implications and future research directions\n";

        return $requirements."\n";
    }

    private function getChapterSpecificInstructions($chapterNumber)
    {
        $instructions = [
            1 => "Write a comprehensive introduction that includes:\n".
                 "• 1.1 Background of the Study (400-500 words): Provide detailed context, current state of the field, and relevant background information\n".
                 "• 1.2 Problem Statement (200-300 words): Clearly articulate the specific problem being addressed with supporting evidence\n".
                 "• 1.3 Research Objectives (150-200 words): List and explain specific, measurable objectives\n".
                 "• 1.4 Research Questions (100-150 words): Formulate clear, focused questions that guide the research\n".
                 "• 1.5 Significance of the Study (200-250 words): Explain importance, potential impact, and contributions to the field\n".
                 "• 1.6 Scope and Limitations (150-200 words): Define boundaries and acknowledge constraints\n".
                 'Target: 1200-1500 words',

            2 => "Write a thorough literature review that includes:\n".
                 "• 2.1 Theoretical Framework (400-500 words): Establish theoretical foundation with key theories and models\n".
                 "• 2.2 Review of Related Studies (800-1000 words): Comprehensive analysis of relevant research, organized thematically\n".
                 "• 2.3 Research Gaps (200-300 words): Identify specific gaps in current knowledge that justify your research\n".
                 "• 2.4 Conceptual Framework (300-400 words): Present your conceptual model showing relationships between variables\n".
                 'Target: 1500-2000 words',

            3 => "Write a detailed methodology chapter that includes:\n".
                 "• 3.1 Research Design (300-400 words): Justify choice of research approach and design with detailed rationale\n".
                 "• 3.2 Population and Sampling (250-350 words): Define target population and explain sampling methodology\n".
                 "• 3.3 Data Collection Methods (400-500 words): Describe instruments, procedures, and data collection protocols\n".
                 "• 3.4 Data Analysis Techniques (300-400 words): Explain analytical methods and statistical procedures\n".
                 "• 3.5 Validity and Reliability (250-350 words): Address measures to ensure research quality and credibility\n".
                 'Target: 1500-2000 words',

            4 => "Write a results and discussion chapter that includes:\n".
                 "• 4.1 Presentation of Results (500-600 words): Present comprehensive findings with tables, figures, and statistical analysis\n".
                 "• 4.2 Analysis and Interpretation (700-800 words): Detailed analysis and interpretation of findings in context\n".
                 "• 4.3 Discussion of Findings (400-500 words): Relate findings to research questions and existing literature\n".
                 "• 4.4 Implications (400-500 words): Discuss theoretical and practical implications of the results\n".
                 'Target: 2000-2500 words',

            5 => "Write a summary, conclusion, and recommendations chapter that includes:\n".
                 "• 5.1 Summary of the Study (300-400 words): Comprehensive overview of the entire research process and key findings\n".
                 "• 5.2 Conclusion (400-500 words): Main conclusions drawn from the research findings and analysis\n".
                 "• 5.3 Recommendations (300-400 words): Practical recommendations based on research findings\n".
                 "• 5.4 Areas for Further Research (200-300 words): Suggestions for future research directions\n".
                 "Use proper academic numbering for all sections and subsections.\n".
                 'Target: 1200-1600 words',

            6 => "Write a conclusion and recommendations chapter that includes:\n".
                 "• 6.1 Summary of Findings (300-400 words): Concise overview of key research findings and results\n".
                 "• 6.2 Conclusions Drawn (300-400 words): Present main conclusions based on analysis and interpretation\n".
                 "• 6.3 Recommendations (300-400 words): Provide practical recommendations based on findings\n".
                 "• 6.4 Future Research Directions (200-250 words): Suggest areas for further investigation\n".
                 "- 6.5 Final Remarks (100-150 words): Closing thoughts on research contributions and significance\n".
                 'Target: 1200-1500 words',
        ];

        return $instructions[$chapterNumber] ?? "Write a comprehensive chapter with proper academic structure.\n".
                                              "- Organize content into 3-4 major sections with appropriate subsections\n".
                                              "- Ensure each section is substantial (300-500 words per major section)\n".
                                              "- Provide detailed analysis, examples, and supporting evidence\n".
                                              "- Use proper academic section numbering and formatting\n".
                                              'Target: 1500-2000 words';
    }

    private function summarizeChapter($content)
    {
        if (empty($content)) {
            return 'No content available';
        }

        // Get first 200 characters as summary
        $summary = strip_tags($content);
        $summary = substr($summary, 0, 200);

        // Find the last complete sentence
        $lastPeriod = strrpos($summary, '.');
        if ($lastPeriod !== false) {
            $summary = substr($summary, 0, $lastPeriod + 1);
        }

        return $summary.'...';
    }

    /**
     * Get chapter type for AI optimization
     */
    private function getChapterType(int $chapterNumber): string
    {
        return match ($chapterNumber) {
            1 => 'introduction',        // High quality for first impression
            2 => 'literature_review',   // Structured, cost-effective
            3 => 'methodology',         // Structured, cost-effective
            4 => 'general',            // Standard generation
            5 => 'general',            // Results and analysis
            6 => 'conclusion',         // High quality for final chapter
            default => 'general'
        };
    }

    /**
     * GET CHAT HISTORY (Simple - backward compatibility)
     * Retrieve chat conversation history for a chapter
     */
    public function getChatHistory(Project $project, int $chapterNumber)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        // Get the latest session ID first
        $latestSession = ChatConversation::query()
            ->forChapter($project->id, $chapterNumber)
            ->where('user_id', auth()->id())
            ->latest('created_at')
            ->first();

        // If no session exists, return empty
        if (! $latestSession) {
            return response()->json([
                'messages' => [],
                'current_session_id' => null,
            ]);
        }

        // Only load messages from the most recent session to avoid confusion
        $messages = ChatConversation::query()
            ->forChapter($project->id, $chapterNumber)
            ->where('user_id', auth()->id())
            ->where('session_id', $latestSession->session_id)
            ->orderBy('message_order')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'type' => $message->message_type,
                    'content' => $message->content,
                    'timestamp' => $message->created_at,
                    'session_id' => $message->session_id,
                ];
            });

        return response()->json([
            'messages' => $messages,
            'current_session_id' => $latestSession->session_id,
        ]);
    }

    /**
     * AI CHAT ASSISTANT
     * Real-time chat interface for writing assistance
     */
    public function chat(Request $request, Project $project, int $chapterNumber)
    {
        Log::info('=== CHAPTER CONTROLLER CHAT METHOD STARTED ===', [
            'user_id' => auth()->id(),
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
            'message_length' => strlen($request->input('message', '')),
            'request_data' => $request->all(),
        ]);

        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'message' => 'required_without:quick_action|string|max:1000',
            'context' => 'nullable|string|max:50000',
            'selected_text' => 'nullable|string|max:5000',
            'session_id' => 'nullable|string|max:36',
            'task_type' => 'nullable|string|in:review,assist',
            'quick_action' => 'nullable|string',
            'chat_history' => 'nullable|array',
            'chat_history.*.type' => 'required|string|in:user,ai,system',
            'chat_history.*.content' => 'required|string|max:10000',
        ]);

        // Generate or use existing session ID
        $sessionId = $validated['session_id'] ?? Str::uuid()->toString();

        Log::info('Chat validation passed', [
            'message' => substr($validated['message'], 0, 100),
            'has_context' => ! empty($validated['context']),
            'has_selected_text' => ! empty($validated['selected_text']),
            'session_id' => $sessionId,
            'chat_history_count' => count($validated['chat_history'] ?? []),
        ]);

        // Get or create chapter for context
        $chapter = Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => '',
                'word_count' => 0,
                'status' => 'draft',
            ]
        );

        Log::info('Chapter loaded for chat', [
            'chapter_id' => $chapter->id,
            'chapter_title' => $chapter->title,
        ]);

        try {
            // Get the next message order for this session
            $nextMessageOrder = ChatConversation::where('session_id', $sessionId)->max('message_order') + 1;

            // Save user message
            $userMessage = ChatConversation::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'session_id' => $sessionId,
                'message_order' => $nextMessageOrder,
                'message_type' => 'user',
                'content' => $validated['message'],
                'context_data' => [
                    'selected_text' => $validated['selected_text'] ?? null,
                    'chapter_content_length' => strlen($validated['context'] ?? ''),
                ],
            ]);

            // Determine task type and handle quick actions
            $taskType = $validated['task_type'] ?? 'assist';
            $message = $validated['message'];

            // Handle quick actions
            if (! empty($validated['quick_action'])) {
                $message = $validated['quick_action'];
                $taskType = 'review'; // Quick actions are typically review tasks
            }

            // Get AI response using enhanced service
            Log::info('Starting AI response generation with enhanced service');
            $startTime = microtime(true);
            $aiResponse = $this->reviewService->getChatResponse($message, $chapter, $taskType);
            $responseTime = microtime(true) - $startTime;

            Log::info('AI response generated', [
                'response_length' => strlen($aiResponse),
                'response_preview' => substr($aiResponse, 0, 100),
                'response_time' => $responseTime,
            ]);

            // Save AI response
            $aiMessage = ChatConversation::create([
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'session_id' => $sessionId,
                'message_order' => $nextMessageOrder + 1,
                'message_type' => 'ai',
                'content' => $aiResponse,
                'ai_model' => config("chat.models.{$taskType}", 'gpt-4o-mini'),
                'response_time' => $responseTime,
                'context_data' => [
                    'user_message_id' => $userMessage->id,
                    'task_type' => $taskType,
                    'quick_action' => $validated['quick_action'] ?? null,
                ],
            ]);

            // Log chat interaction
            Log::info('AI Chat - Message processed', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'message_length' => strlen($validated['message']),
                'response_length' => strlen($aiResponse),
            ]);

            return response()->json([
                'success' => true,
                'response' => $aiResponse,
                'timestamp' => now()->toISOString(),
                'session_id' => $sessionId,
                'user_message_id' => $userMessage->id,
                'ai_message_id' => $aiMessage->id,
                'task_type' => $taskType,
                'model_used' => config("chat.models.{$taskType}", 'gpt-4o-mini'),
            ]);

        } catch (\Exception $e) {
            Log::error('AI Chat failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Unable to process your message. Please try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Build context for AI chat assistant from database history
     */
    private function buildChatContextFromHistory(Project $project, Chapter $chapter, string $sessionId, string $chapterContent, string $selectedText): string
    {
        $context = "You are an AI writing assistant helping with academic writing.\n\n";

        $context .= "Project Context:\n";
        $context .= "- Title: {$project->title}\n";
        $context .= "- Topic: {$project->topic}\n";
        $context .= "- Field: {$project->field_of_study}\n";
        $context .= "- Level: {$project->type}\n";
        $context .= "- University: {$project->universityRelation?->name}\n";
        $context .= "- Course: {$project->course}\n\n";

        $context .= "Current Chapter: {$chapter->chapter_number} - {$chapter->title}\n\n";

        if ($chapterContent) {
            $summary = substr($chapterContent, 0, 1000);
            $context .= "Chapter Content Summary:\n{$summary}...\n\n";
        }

        if ($selectedText) {
            $context .= "User Selected Text:\n\"{$selectedText}\"\n\n";
        }

        // Get recent chat history from database
        $recentMessages = ChatConversation::query()
            ->forSession($sessionId)
            ->where('user_id', auth()->id())
            ->orderBy('message_order')
            ->limit(10)
            ->get();

        if ($recentMessages->isNotEmpty()) {
            $context .= "Recent Chat History:\n";
            foreach ($recentMessages as $msg) {
                $speaker = $msg->message_type === 'user' ? 'User' : 'Assistant';
                $context .= "{$speaker}: {$msg->content}\n";
            }
            $context .= "\n";
        }

        $context .= "Guidelines:\n";
        $context .= "- Provide specific, actionable advice\n";
        $context .= "- Be encouraging and constructive\n";
        $context .= "- Focus on academic writing best practices\n";
        $context .= "- Suggest concrete improvements when possible\n";
        $context .= "- Keep responses concise but helpful\n\n";

        return $context;
    }

    /**
     * Build context for AI chat assistant (legacy method for backward compatibility)
     */
    private function buildChatContext(Project $project, Chapter $chapter, string $chapterContent, string $selectedText, array $chatHistory): string
    {
        $context = "You are an AI writing assistant helping with academic writing.\n\n";

        $context .= "Project Context:\n";
        $context .= "- Title: {$project->title}\n";
        $context .= "- Topic: {$project->topic}\n";
        $context .= "- Field: {$project->field_of_study}\n";
        $context .= "- Level: {$project->type}\n";
        $context .= "- University: {$project->universityRelation?->name}\n";
        $context .= "- Course: {$project->course}\n\n";

        $context .= "Current Chapter: {$chapter->chapter_number} - {$chapter->title}\n\n";

        if ($chapterContent) {
            $summary = substr($chapterContent, 0, 1000);
            $context .= "Chapter Content Summary:\n{$summary}...\n\n";
        }

        if ($selectedText) {
            $context .= "User Selected Text:\n\"{$selectedText}\"\n\n";
        }

        if (! empty($chatHistory)) {
            $context .= "Recent Chat History:\n";
            $recentHistory = array_slice($chatHistory, -5); // Last 5 messages
            foreach ($recentHistory as $msg) {
                $speaker = $msg['type'] === 'user' ? 'User' : 'Assistant';
                $context .= "{$speaker}: {$msg['content']}\n";
            }
            $context .= "\n";
        }

        $context .= "Guidelines:\n";
        $context .= "- Provide specific, actionable advice\n";
        $context .= "- Be encouraging and constructive\n";
        $context .= "- Focus on academic writing best practices\n";
        $context .= "- Suggest concrete improvements when possible\n";
        $context .= "- Keep responses concise but helpful\n\n";

        return $context;
    }

    /**
     * Get AI response for chat message using AIContentGenerator
     */
    private function getAIChatResponse(string $context, string $userMessage): string
    {
        // Build the full prompt for AI chat
        $prompt = $context."\n\nUser Question: \"{$userMessage}\"\n\n";
        $prompt .= 'Please provide a helpful, specific response as an AI writing assistant. ';
        $prompt .= 'Focus on actionable advice that will improve their academic writing. ';
        $prompt .= 'Keep your response concise but comprehensive (2-4 sentences). ';
        $prompt .= 'Be encouraging and constructive in your tone.';

        try {
            // Use the AI service to generate a response
            // Use gpt-4o-mini for cost-effective chat responses
            $response = $this->aiGenerator->generate($prompt, [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.7,
                'max_tokens' => 300,
            ]);

            return trim($response);

        } catch (\Exception $e) {
            Log::error('AI Chat Response Generation failed', [
                'error' => $e->getMessage(),
                'prompt_length' => strlen($prompt),
            ]);

            // Fallback to contextual mock response if AI fails
            return $this->getFallbackChatResponse($userMessage);
        }
    }

    /**
     * Fallback chat responses when AI service fails
     */
    private function getFallbackChatResponse(string $userMessage): string
    {
        $responses = [
            'structure' => 'I can help you improve the structure of this chapter. Consider organizing it with clear headings like Introduction, Main Arguments, Supporting Evidence, and Conclusion. Each section should flow logically into the next.',

            'argument' => 'To strengthen your argument, try adding more evidence to support your main points. Consider including recent studies, expert opinions, or statistical data. Also, address potential counterarguments to make your position more robust.',

            'citation' => "For proper citations, make sure you're following your university's required style guide (APA, MLA, Chicago, etc.). Include page numbers for direct quotes, and ensure all sources appear in your reference list.",

            'clarity' => 'To improve clarity, try breaking down complex sentences into shorter ones. Use transition words to connect your ideas, and define technical terms when you first introduce them.',

            'grammar' => 'For better grammar and flow, try reading your text aloud to catch awkward phrasing. Use tools like Grammarly for additional support, and ensure subject-verb agreement throughout.',

            'default' => "I'm here to help with your academic writing! I can assist with structure, arguments, citations, clarity, grammar, and more. What specific aspect would you like to focus on?",
        ];

        // Simple keyword-based response selection
        $message = strtolower($userMessage);

        if (str_contains($message, 'structure') || str_contains($message, 'organize')) {
            return $responses['structure'];
        } elseif (str_contains($message, 'argument') || str_contains($message, 'evidence') || str_contains($message, 'support')) {
            return $responses['argument'];
        } elseif (str_contains($message, 'citation') || str_contains($message, 'reference') || str_contains($message, 'source')) {
            return $responses['citation'];
        } elseif (str_contains($message, 'clear') || str_contains($message, 'understand') || str_contains($message, 'confus')) {
            return $responses['clarity'];
        } elseif (str_contains($message, 'grammar') || str_contains($message, 'sentence') || str_contains($message, 'writing')) {
            return $responses['grammar'];
        } else {
            return $responses['default']."\n\nRegarding your question: \"$userMessage\" - I'd be happy to provide more specific guidance if you can tell me what aspect of your writing you'd like to focus on.";
        }
    }

    /**
     * STREAMING AI CHAT ASSISTANT
     * Context-aware streaming chat for real-time writing assistance
     */
    public function streamChat(Request $request, Project $project, int $chapterNumber)
    {
        Log::info('=== STREAMING CHAT STARTED ===', [
            'user_id' => auth()->id(),
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
            'request_params' => $request->query(),
        ]);

        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        // Log incoming request data for debugging
        Log::info('STREAMING CHAT - Incoming request data', [
            'all_data' => $request->all(),
            'message' => $request->input('message'),
            'quick_action' => $request->input('quick_action'),
            'task_type' => $request->input('task_type'),
        ]);

        try {
            $validated = $request->validate([
                'message' => 'required_without:quick_action|nullable|string|max:2000',
                'chapter_content' => 'nullable|string|max:100000', // Full chapter content for context
                'selected_text' => 'nullable|string|max:5000',
                'session_id' => 'nullable|string|max:36',
                'task_type' => 'nullable|string|in:review,assist',
                'quick_action' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('STREAMING CHAT - Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all(),
            ]);
            throw $e;
        }

        // Debug logging for quick actions
        Log::info('Chat validation passed', [
            'message' => $validated['message'] ?? '',
            'quick_action' => $validated['quick_action'] ?? null,
            'task_type' => $validated['task_type'] ?? null,
            'has_chapter_content' => ! empty($validated['chapter_content']),
            'has_selected_text' => ! empty($validated['selected_text']),
        ]);

        // Generate or use existing session ID
        $sessionId = $validated['session_id'] ?? Str::uuid()->toString();

        // Determine task type early
        $taskType = $validated['task_type'] ?? 'review'; // Default to review mode

        // Get or create chapter for context
        $chapter = Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => $validated['chapter_content'] ?? '',
                'word_count' => 0,
                'status' => 'draft',
            ]
        );

        // Return streaming response
        return response()->stream(function () use ($project, $chapter, $validated, $sessionId, $taskType) {
            // Clean any existing output buffers
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Start output buffering for streaming
            ob_start();

            // Set headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable Nginx buffering

            try {
                // Get next message order
                $nextMessageOrder = ChatConversation::where('session_id', $sessionId)->max('message_order') + 1;

                // Save user message first
                // For quick actions, save the action name as the content, otherwise save the message
                $messageContent = ! empty($validated['quick_action'])
                    ? 'Quick Action: '.$validated['quick_action']
                    : $validated['message'];

                $userMessage = ChatConversation::create([
                    'user_id' => auth()->id(),
                    'project_id' => $project->id,
                    'chapter_number' => $chapter->chapter_number,
                    'session_id' => $sessionId,
                    'message_order' => $nextMessageOrder,
                    'message_type' => 'user',
                    'content' => $messageContent,
                    'context_data' => [
                        'selected_text' => $validated['selected_text'] ?? null,
                        'chapter_content_length' => strlen($validated['chapter_content'] ?? ''),
                        'has_full_chapter_context' => ! empty($validated['chapter_content']),
                        'task_type' => $taskType,
                        'quick_action' => $validated['quick_action'] ?? null,
                    ],
                ]);

                // Send initial ping
                $this->sendSSEMessage([
                    'type' => 'start',
                    'message' => 'AI assistant is thinking...',
                    'session_id' => $sessionId,
                ]);

                // Prepare message
                $message = $validated['message'] ?? '';

                // Handle quick actions
                if (! empty($validated['quick_action'])) {
                    $quickActionMessage = config("chat.quick_actions.{$taskType}.{$validated['quick_action']}");
                    if ($quickActionMessage) {
                        $message = $quickActionMessage;
                    } else {
                        Log::warning('Quick action message not found', [
                            'task_type' => $taskType,
                            'quick_action' => $validated['quick_action'],
                        ]);
                        $message = "I'd like to help with: ".$validated['quick_action'];
                    }
                }

                Log::info('Streaming chat processing', [
                    'task_type' => $taskType,
                    'has_quick_action' => ! empty($validated['quick_action']),
                    'has_chapter_content' => ! empty($validated['chapter_content']),
                    'has_selected_text' => ! empty($validated['selected_text']),
                ]);

                // Stream AI response using the enhanced review service
                $fullResponse = '';
                $startTime = microtime(true);

                // Get conversation history for personality continuity
                $conversationHistory = ChatConversation::where('session_id', $sessionId)
                    ->orderBy('message_order', 'asc')
                    ->get(['message_type as type', 'content'])
                    ->toArray();

                // Clean and sanitize content to prevent UTF-8 encoding issues
                $chapterContent = $validated['chapter_content'] ?? '';
                if (! empty($chapterContent)) {
                    // Strip HTML tags and decode entities
                    $chapterContent = strip_tags($chapterContent);
                    $chapterContent = html_entity_decode($chapterContent, ENT_QUOTES, 'UTF-8');
                    // Ensure valid UTF-8 encoding
                    $chapterContent = mb_convert_encoding($chapterContent, 'UTF-8', 'UTF-8');
                    // Remove any malformed characters
                    $chapterContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $chapterContent);
                }

                $selectedText = $validated['selected_text'] ?? '';
                if (! empty($selectedText)) {
                    // Clean selected text as well
                    $selectedText = strip_tags($selectedText);
                    $selectedText = html_entity_decode($selectedText, ENT_QUOTES, 'UTF-8');
                    $selectedText = mb_convert_encoding($selectedText, 'UTF-8', 'UTF-8');
                    $selectedText = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $selectedText);
                }

                // Use the ChapterReviewService for enhanced streaming
                foreach ($this->reviewService->getChatResponseStream(
                    $message,
                    $chapter,
                    $taskType,
                    $chapterContent,
                    $selectedText,
                    $conversationHistory,
                    $sessionId
                ) as $chunk) {
                    $fullResponse .= $chunk;

                    // Send content update
                    $this->sendSSEMessage([
                        'type' => 'content',
                        'content' => $chunk,
                        'session_id' => $sessionId,
                    ]);

                    // Add delay to slow down streaming for better UX
                    usleep(50000); // 50ms delay between chunks

                    // Send heartbeat every 50 characters to keep connection alive
                    if (strlen($fullResponse) % 50 === 0) {
                        $this->sendSSEMessage(['type' => 'heartbeat']);
                    }
                }

                $responseTime = microtime(true) - $startTime;

                // Save AI response
                $aiMessage = ChatConversation::create([
                    'user_id' => auth()->id(),
                    'project_id' => $project->id,
                    'chapter_number' => $chapter->chapter_number,
                    'session_id' => $sessionId,
                    'message_order' => $nextMessageOrder + 1,
                    'message_type' => 'ai',
                    'content' => $fullResponse,
                    'ai_model' => config("chat.models.{$taskType}", 'gpt-4o-mini'),
                    'response_time' => $responseTime,
                    'context_data' => [
                        'user_message_id' => $userMessage->id,
                        'streaming_response' => true,
                        'task_type' => $taskType,
                        'has_quick_action' => ! empty($validated['quick_action']),
                    ],
                ]);

                // Send completion message
                $this->sendSSEMessage([
                    'type' => 'complete',
                    'message' => 'Response complete',
                    'session_id' => $sessionId,
                    'response_time' => $responseTime,
                    'user_message_id' => $userMessage->id,
                    'ai_message_id' => $aiMessage->id,
                ]);

                Log::info('Streaming chat completed successfully', [
                    'response_length' => strlen($fullResponse),
                    'response_time' => $responseTime,
                    'session_id' => $sessionId,
                ]);

            } catch (\Exception $e) {
                Log::error('Streaming chat failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'project_id' => $project->id,
                    'chapter_number' => $chapter->chapter_number,
                ]);

                $this->sendSSEMessage([
                    'type' => 'error',
                    'message' => 'Unable to generate response. Please try again.',
                    'error_details' => config('app.debug') ? $e->getMessage() : null,
                ]);
            }

            // End stream
            $this->sendSSEMessage(['type' => 'end']);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Build comprehensive context for AI chat with full chapter awareness
     */
    private function buildComprehensiveChatContext(Project $project, Chapter $chapter, string $sessionId, string $chapterContent, string $selectedText): string
    {
        $context = 'You are an intelligent AI writing assistant specializing in academic writing. ';
        $context .= "You have full awareness of the user's chapter content and can provide specific, contextual advice.\n\n";

        // Project context
        $context .= "PROJECT CONTEXT:\n";
        $context .= "- Title: {$project->title}\n";
        $context .= "- Topic: {$project->topic}\n";
        $context .= "- Field of Study: {$project->field_of_study}\n";
        $context .= "- Academic Level: {$project->type}\n";
        $context .= "- University: {$project->universityRelation?->name}\n";
        $context .= "- Course: {$project->course}\n\n";

        // Current chapter context
        $context .= "CURRENT CHAPTER: {$chapter->chapter_number} - {$chapter->title}\n";
        $context .= "Word Count: {$chapter->word_count} words\n\n";

        // Full chapter content if provided
        if (! empty($chapterContent)) {
            $context .= "FULL CHAPTER CONTENT:\n";
            $context .= "===================\n";
            $context .= $chapterContent."\n";
            $context .= "===================\n\n";
        }

        // Selected text context
        if (! empty($selectedText)) {
            $context .= "USER SELECTED TEXT:\n";
            $context .= "\"{$selectedText}\"\n\n";
            $context .= "The user has specifically selected this text, so they likely want advice related to this section.\n\n";
        }

        // Get recent chat history
        $recentMessages = ChatConversation::query()
            ->forSession($sessionId)
            ->where('user_id', auth()->id())
            ->orderBy('message_order')
            ->limit(8) // More history for better context
            ->get();

        if ($recentMessages->isNotEmpty()) {
            $context .= "RECENT CONVERSATION HISTORY:\n";
            foreach ($recentMessages as $msg) {
                $speaker = $msg->message_type === 'user' ? 'User' : 'AI Assistant';
                $context .= "{$speaker}: {$msg->content}\n";
            }
            $context .= "\n";
        }

        // Assistant capabilities and guidelines
        $context .= "YOUR CAPABILITIES & GUIDELINES:\n";
        $context .= "- You can read and analyze the entire chapter content\n";
        $context .= "- Provide specific suggestions based on what you see in their writing\n";
        $context .= "- Point out strengths and areas for improvement\n";
        $context .= "- Suggest structural changes, argument improvements, clarity enhancements\n";
        $context .= "- Help with citations, grammar, flow, and academic style\n";
        $context .= "- Be encouraging and constructive in your tone\n";
        $context .= "- Give actionable, specific advice rather than generic tips\n";
        $context .= "- Reference specific parts of their chapter when relevant\n";
        $context .= "- Act as a knowledgeable companion who understands their work\n\n";

        $context .= "When responding:\n";
        $context .= "- Be conversational but professional\n";
        $context .= "- Provide concrete examples when possible\n";
        $context .= "- Ask follow-up questions to better understand their needs\n";
        $context .= "- Acknowledge their progress and effort\n";
        $context .= "- Keep responses focused and actionable (2-4 sentences typically)\n\n";

        return $context;
    }

    /**
     * Ensure papers are collected for the project before AI generation
     */
    private function ensurePapersAreCollected(Project $project): void
    {
        // Check if papers were recently collected (last 7 days)
        $recentPapers = $project->collectedPapers()->recent()->count();

        if ($recentPapers > 0) {
            Log::info("Using {$recentPapers} existing collected papers for project: {$project->title}");

            return;
        }

        // Check if collection is already in progress
        if ($project->paper_collection_status === 'collecting_papers') {
            abort(423, 'Paper collection is in progress. Please wait for it to complete before generating chapters.');
        }

        // If no recent papers and not collecting, start collection
        Log::info("Starting automatic paper collection for project: {$project->title}");

        $project->update([
            'paper_collection_status' => 'collecting_papers',
            'paper_collection_message' => 'Automatically collecting papers for AI generation...',
            'citation_guaranteed' => true,
        ]);

        // Dispatch the job and wait for it to complete (for small collections)
        CollectPapersForProject::dispatchSync($project, false);

        // Refresh project to get updated status
        $project->refresh();

        // Verify collection completed successfully
        if ($project->paper_collection_status !== 'completed') {
            abort(500, 'Paper collection failed. Please try again or contact support.');
        }

        Log::info("Paper collection completed successfully for project: {$project->title}. Found {$project->paper_collection_count} papers.");
    }

    private function ensureChapterForPrompt(Project $project, int $chapterNumber): Chapter
    {
        return Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $this->getDefaultChapterTitle($chapterNumber),
                'content' => null,
                'word_count' => 0,
                'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
                'status' => 'draft',
            ]
        );
    }

    private function computeWordCount(string $content): int
    {
        return app(ChapterContentAnalysisService::class)->getWordCount($content);
    }

    /**
     * Get collected papers formatted for AI consumption
     */
    private function getCollectedPapersForAI(Project $project, ?int $chapterNumber = null, ?string $sectionType = null, ?Chapter $chapter = null): string
    {
        $papers = $project->collectedPapers()
            ->recent()
            ->forProject($project->id)
            ->get();

        if ($papers->isEmpty()) {
            return "\n## CITATION CONSTRAINT:\nNo verified papers available. Please write the chapter without citations and note that citations need to be added manually.\n\n";
        }

        $chapterType = null;
        $chapterTitle = null;
        $sectionTitle = null;
        if ($chapterNumber !== null) {
            $chapterType = $this->getChapterType($chapterNumber);
            $chapterTitle = $this->getDefaultChapterTitle($chapterNumber);
        }
        if ($sectionType !== null) {
            $sectionTitle = $this->getSectionTitle($sectionType);
        }

        $maxPapers = (int) config('ai.prompt_injection.max_papers', 12);
        if (is_string($chapterType) && $chapterType !== '') {
            $byType = config("ai.prompt_injection.max_papers_by_chapter_type.{$chapterType}", null);
            if ($byType !== null) {
                $maxPapers = (int) $byType;
            }
        }
        $maxPapers = max(1, min(60, $maxPapers));

        $abstractMaxChars = (int) config('ai.prompt_injection.abstract_max_chars', 300);
        $abstractMaxChars = max(0, min(2000, $abstractMaxChars));

        $queryTextParts = [
            $project->topic,
            $project->field_of_study,
            $chapterTitle,
            $chapterType,
            $sectionTitle,
        ];
        $queryText = implode(' ', array_filter($queryTextParts, fn ($p) => is_string($p) && trim($p) !== ''));
        $queryTokens = $this->tokenizeForRelevance($queryText);

        $ranked = $papers->map(function ($paper) use ($queryTokens) {
            $text = trim(($paper->title ?? '').' '.($paper->abstract ?? ''));
            $paperTokens = $this->tokenizeForRelevance($text);
            $overlap = 0;
            foreach ($queryTokens as $token => $_) {
                if (isset($paperTokens[$token])) {
                    $overlap++;
                }
            }
            $overlapScore = count($queryTokens) > 0 ? ($overlap / count($queryTokens)) : 0.0;
            $quality = (float) ($paper->quality_score ?? 0.0);
            $score = ($overlapScore * 0.7) + ($quality * 0.3);

            return [
                'paper' => $paper,
                'score' => $score,
            ];
        })
            ->sortByDesc('score')
            ->take($maxPapers)
            ->values()
            ->map(fn ($item) => $item['paper']);

        if ($chapter) {
            app(CitationWhitelistService::class)->prepareWhitelistForChapter($chapter, $ranked);
        }

        $papersText = "\n## VERIFIED SOURCES (CITATION WHITELIST):\n";
        $papersText .= "Citations are allowed ONLY from the list below (selected for relevance to this chapter/section).\n";
        $papersText .= "Do not cite anything outside this list.\n\n";
        $papersText .= "### AVAILABLE PAPERS:\n\n";

        $allowedCitations = [];

        foreach ($ranked as $index => $paper) {
            $num = $index + 1;
            $firstAuthorLastName = $this->extractFirstAuthorLastName((string) $paper->authors);
            $year = is_numeric($paper->year) ? (int) $paper->year : null;
            $allowedCitation = null;
            if ($firstAuthorLastName && $year) {
                $allowedCitation = "({$firstAuthorLastName}, {$year})";
                $allowedCitations[] = $allowedCitation;
            }

            $papersText .= "**Paper {$num}:**\n";
            $papersText .= "- Title: {$paper->title}\n";
            $papersText .= "- Authors: {$paper->authors}\n";
            $papersText .= "- Year: {$paper->year}\n";
            $papersText .= "- Venue: {$paper->venue}\n";

            if ($paper->doi) {
                $papersText .= "- DOI: {$paper->doi}\n";
            }

            if ($paper->abstract) {
                $abstract = (string) $paper->abstract;
                if ($abstractMaxChars > 0 && strlen($abstract) > $abstractMaxChars) {
                    $abstract = substr($abstract, 0, $abstractMaxChars).'...';
                }
                $papersText .= "- Abstract: {$abstract}\n";
            }

            $papersText .= '- Quality Score: '.number_format($paper->quality_score, 2)."\n";
            $papersText .= "- Source: {$paper->source_api}\n\n";

            if ($allowedCitation) {
                $papersText .= "- Allowed in-text citation (use EXACTLY): {$allowedCitation}\n\n";
            } else {
                $papersText .= "- Allowed in-text citation: (NOT AVAILABLE - missing author/year)\n\n";
            }
        }

        $papersText .= "### STRICT CITATION POLICY:\n";
        $papersText .= "1. You may ONLY use in-text citations that EXACTLY match one of the allowed citation strings shown above.\n";
        $papersText .= "2. Use ONLY the format (FirstAuthorLastName, Year). Do NOT use 'et al.' and do NOT include titles, DOIs, or URLs in citations.\n";
        $papersText .= "3. If you cannot support a sentence with an allowed in-text citation, write the sentence without a citation and add: [Citation needed]\n";
        $papersText .= "4. Do NOT create a References/Bibliography section in this chapter.\n";
        $papersText .= "5. Never invent author names, years, DOIs, URLs, journals, or paper titles.\n\n";
        $papersText .= 'Total verified papers available for this project: '.$papers->count()."\n";
        $papersText .= 'Papers injected into this prompt: '.$ranked->count()."\n";
        $papersText .= 'Total allowed in-text citations: '.count($allowedCitations)."\n\n";

        return $papersText;
    }

    /**
     * Tokenize text for simple relevance scoring.
     * Returns an associative array for O(1) membership checks.
     */
    private function tokenizeForRelevance(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\\s]+/', ' ', $text);
        $text = preg_replace('/\\s+/', ' ', trim((string) $text));
        if ($text === '') {
            return [];
        }

        $stop = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'into', 'using', 'use',
            'study', 'research', 'analysis', 'based', 'system', 'framework', 'approach',
            'between', 'among', 'within', 'across', 'effects', 'effect', 'impact',
        ];
        $stopSet = array_fill_keys($stop, true);

        $tokens = [];
        foreach (explode(' ', $text) as $token) {
            $token = trim($token);
            if ($token === '' || strlen($token) < 3) {
                continue;
            }
            if (isset($stopSet[$token])) {
                continue;
            }
            $tokens[$token] = true;
        }

        return $tokens;
    }

    private function extractFirstAuthorLastName(string $authors): ?string
    {
        $authors = trim($authors);
        if ($authors === '' || strcasecmp($authors, 'Unknown Authors') === 0) {
            return null;
        }

        // Take the first author (split on comma) and grab the last word as last name.
        $first = trim(explode(',', $authors)[0] ?? '');
        if ($first === '') {
            return null;
        }

        $first = preg_replace('/\s+/', ' ', $first);
        $parts = array_values(array_filter(explode(' ', $first), fn ($p) => trim($p) !== ''));
        if (empty($parts)) {
            return null;
        }

        $lastName = preg_replace("/[^A-Za-z\\-']+/", '', end($parts));
        $lastName = trim((string) $lastName);

        return $lastName !== '' ? $lastName : null;
    }

    /**
     * BUILD CONTINUATION PROMPT - Specialized for continuing from cursor position
     */
    private function buildContinuationPrompt(
        $project,
        int $chapterNumber,
        int $cursorPosition,
        string $contextText,
        string $currentContent,
        int $targetWords = 500
    ): string {
        $chapter = $this->ensureChapterForPrompt($project, $chapterNumber);
        $currentWordCount = str_word_count(strip_tags($currentContent));
        $chapterTitle = $this->getDefaultChapterTitle($chapterNumber);

        $prompt = "You are an academic writing assistant continuing Chapter {$chapterNumber} from a specific position.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Chapter: {$chapterTitle}\n\n";

        $prompt .= "CURRENT WRITING STATUS:\n";
        $prompt .= "- Current word count: {$currentWordCount} words\n";
        $prompt .= "- Target additional words: ~{$targetWords} words\n";
        $prompt .= "- Cursor position: {$cursorPosition}\n\n";

        $prompt .= "CONTEXT WHERE WRITING SHOULD CONTINUE:\n";
        $prompt .= "\"...{$contextText}\"\n\n";

        $prompt .= "CONTINUATION INSTRUCTIONS:\n";
        $prompt .= "- CONTINUE naturally from the provided context text\n";
        $prompt .= "- DO NOT restart, rewrite, or repeat the existing content\n";
        $prompt .= "- Maintain the established academic tone and writing style\n";
        $prompt .= "- Flow seamlessly from where the author left off\n";
        $prompt .= "- Add approximately {$targetWords} words of meaningful content\n";
        $prompt .= "- Use proper academic structure and transitions\n";
        $prompt .= "- Include relevant citations in APA format when appropriate\n";
        $prompt .= "- Stop at a natural paragraph or section break\n";
        $prompt .= "- Do not add chapter titles, headings, or structural elements unless contextually appropriate\n\n";

        // Add collected papers for citation constraint
        $prompt .= $this->getCollectedPapersForAI($project, $chapterNumber, null, $chapter);

        $prompt .= "CRITICAL REQUIREMENTS:\n";
        $prompt .= "- Never use the & symbol - always write 'and'\n";
        $prompt .= "- Use only REAL, VERIFIABLE sources for citations\n";
        $prompt .= "- Format citations as (Author, Year)\n";
        $prompt .= "- If you cannot support a point with an allowed in-text citation from the verified list, write: [Citation needed]\n";
        $prompt .= "- Continue the content naturally without repetition\n";
        $prompt .= "- Focus on adding value to the existing academic argument\n\n";

        $prompt .= 'Begin writing immediately from where the context text ends:';

        return $prompt;
    }

    /**
     * BUILD SUB-SECTION PROMPT - Generate specific sub-sections within chapters (like 3.1, 3.2, etc)
     */
    private function buildSectionPrompt($project, int $chapterNumber, string $sectionType): string
    {
        $chapterTitle = $this->getDefaultChapterTitle($chapterNumber);
        $sectionNumber = $this->getSectionNumber($chapterNumber, $sectionType);
        $sectionTitle = $this->getSectionTitle($sectionType);

        $prompt = "You are writing section {$sectionNumber} ({$sectionTitle}) for Chapter {$chapterNumber}: {$chapterTitle} of an academic thesis.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Chapter: {$chapterTitle}\n";
        $prompt .= "Section: {$sectionNumber} {$sectionTitle}\n\n";

        // Get existing chapter content for context
        $existingChapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->first();
        $chapter = $existingChapter ?: $this->ensureChapterForPrompt($project, $chapterNumber);

        if ($existingChapter && $existingChapter->content) {
            $prompt .= "EXISTING CHAPTER CONTENT (for context and flow):\n";
            $prompt .= substr(strip_tags($existingChapter->content), 0, 800)."...\n\n";
            $prompt .= "IMPORTANT: Build upon the existing content naturally. Do not repeat what has already been written.\n\n";
        }

        // Chapter and section-specific instructions
        $prompt .= $this->getChapterSectionInstructions($chapterNumber, $sectionType);

        // Add collected papers for citation constraint - CRITICAL FOR ACCURACY
        $prompt .= $this->getCollectedPapersForAI($project, $chapterNumber, $sectionType, $chapter);

        $prompt .= "WRITING REQUIREMENTS:\n";
        $prompt .= "- Write this as section {$sectionNumber} {$sectionTitle}\n";
        $prompt .= "- Begin with the section heading: '{$sectionNumber} {$sectionTitle}'\n";
        $prompt .= "- Write 500-800 words for this specific section\n";
        $prompt .= "- Use proper academic language and structure\n";
        $prompt .= "- Include relevant citations from the verified paper list above\n";
        $prompt .= "- Format citations in APA style: (Author, Year)\n";
        $prompt .= "- Never use the & symbol - always write 'and'\n";
        $prompt .= "- Use only REAL, VERIFIABLE sources from the provided list\n";
        $prompt .= "- If you cannot support a point with an allowed in-text citation from the verified list, write: [Citation needed]\n";
        $prompt .= "- Write content that flows naturally with existing chapter content\n";
        $prompt .= "- Focus specifically on the {$sectionTitle} aspect of the chapter\n\n";

        $prompt .= "Write section {$sectionNumber} {$sectionTitle} now:";

        return $prompt;
    }

    /**
     * AI-POWERED SECTION SUGGESTION
     * Intelligently suggests the next section based on project context
     */
    public function suggestNextSection(Request $request, int $project_id, int $chapter_id)
    {
        // Find project and chapter by ID
        $project = Project::findOrFail($project_id);
        $chapter = Chapter::findOrFail($chapter_id);

        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        // Validate chapter belongs to project
        abort_if($chapter->project_id !== $project_id, 403);

        // Validate and get current chapter content from request
        $validated = $request->validate([
            'current_content' => 'nullable|string|max:100000', // Allow up to 100KB of content
        ]);

        // Use the current content from frontend (more accurate than stored content)
        $currentContent = $validated['current_content'] ?? $chapter->content ?? '';

        try {
            // Update section progress based on current content
            $this->outlineService->updateSectionProgress($project, $chapter->chapter_number, $currentContent);

            // Get structured completion analysis
            $analysisData = $this->getStructuredChapterAnalysis($project, $chapter->chapter_number, $currentContent);

            return response()->json([
                'success' => true,
                'analysis' => $analysisData,
                'structured' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Structured Section Analysis failed, falling back to AI', [
                'project_id' => $project->id,
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to AI-based analysis if structured approach fails
            return $this->fallbackToAIAnalysis($project, $chapter, $currentContent);
        }
    }

    /**
     * Get structured chapter analysis based on project outline
     */
    private function getStructuredChapterAnalysis(Project $project, int $chapterNumber, string $currentContent): array
    {
        // Get the outline for this chapter
        $outline = $project->outlines()->where('chapter_number', $chapterNumber)->first();

        if (! $outline) {
            throw new \Exception('No outline found for chapter');
        }

        // Calculate current progress
        $totalSections = $outline->sections()->where('is_required', true)->count();
        $completedSections = $outline->sections()->where('is_required', true)->where('is_completed', true)->count();
        $currentWordCount = str_word_count($currentContent);

        // Get next incomplete section
        $nextSection = $outline->next_section;

        // Check if content is effectively empty (less than 50 words)
        $isEmpty = $currentWordCount < 50;

        // Determine status and recommendation
        // If content is empty, always show generation buttons regardless of stored completion status
        if ($isEmpty) {
            return [
                'status' => 'NEEDS_CONTENT',
                'recommendation' => 'START_WRITING',
                'section' => [
                    'number' => $nextSection ? $nextSection->section_number : '1.1',
                    'name' => $nextSection ? $nextSection->section_title : 'Introduction',
                    'description' => $nextSection ? $nextSection->section_description : 'Start writing your chapter introduction',
                ],
                'rationale' => 'Chapter is empty. Ready to start writing! Begin with '.($nextSection ? $nextSection->section_title : 'Introduction'),
                'show_section_button' => true,
                'show_full_chapter_button' => true,
                'completion_percentage' => 0,
                'word_count_progress' => [
                    'current' => $currentWordCount,
                    'target' => $outline->target_word_count,
                    'percentage' => 0,
                ],
            ];
        }

        if ($outline->is_complete && ! $isEmpty) {
            return [
                'status' => 'COMPLETE',
                'recommendation' => 'NONE',
                'section' => [
                    'number' => 'NONE',
                    'name' => 'NONE',
                    'description' => 'NONE',
                ],
                'rationale' => "Chapter complete! {$completedSections}/{$totalSections} sections finished with {$currentWordCount} words ({$outline->completion_percentage}% of target)",
                'show_section_button' => false,
                'show_full_chapter_button' => false,
                'completion_percentage' => $outline->completion_percentage,
                'word_count_progress' => [
                    'current' => $currentWordCount,
                    'target' => $outline->target_word_count,
                    'percentage' => $outline->target_word_count > 0 ? round(($currentWordCount / $outline->target_word_count) * 100, 2) : 0,
                ],
            ];
        }

        if (! $nextSection) {
            // No more required sections but not complete - likely word count issue
            return [
                'status' => 'PARTIAL',
                'recommendation' => 'IMPROVE_CONTENT',
                'section' => [
                    'number' => 'NONE',
                    'name' => 'Content Enhancement',
                    'description' => 'Expand existing sections to meet word count targets',
                ],
                'rationale' => "All sections written but need more content. Current: {$currentWordCount} words, Target: {$outline->target_word_count} words",
                'show_section_button' => false,
                'show_full_chapter_button' => true,
                'completion_percentage' => $outline->completion_percentage,
                'word_count_progress' => [
                    'current' => $currentWordCount,
                    'target' => $outline->target_word_count,
                    'percentage' => $outline->target_word_count > 0 ? round(($currentWordCount / $outline->target_word_count) * 100, 2) : 0,
                ],
            ];
        }

        // Has next section to write
        $isEmpty = $this->isContentEmpty($currentContent);

        return [
            'status' => $isEmpty ? 'EMPTY' : 'PARTIAL',
            'recommendation' => $isEmpty ? 'START_CHAPTER' : 'NEXT_SECTION',
            'section' => [
                'number' => $nextSection->section_number,
                'name' => $nextSection->section_title,
                'description' => $nextSection->section_description,
            ],
            'rationale' => $isEmpty
                ? "Ready to start writing! Begin with {$nextSection->section_title}"
                : "Continue with next section: {$nextSection->section_title}. Progress: {$completedSections}/{$totalSections} sections complete",
            'show_section_button' => true,
            'show_full_chapter_button' => $isEmpty,
            'completion_percentage' => $outline->completion_percentage,
            'word_count_progress' => [
                'current' => $currentWordCount,
                'target' => $outline->target_word_count,
                'percentage' => $outline->target_word_count > 0 ? round(($currentWordCount / $outline->target_word_count) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Fallback to AI analysis if structured approach fails
     */
    private function fallbackToAIAnalysis(Project $project, Chapter $chapter, string $currentContent): \Illuminate\Http\JsonResponse
    {
        try {
            // Build AI prompt for comprehensive chapter analysis
            $analysisPrompt = $this->buildChapterAnalysisPrompt(
                $project,
                $chapter->chapter_number,
                $currentContent,
                $chapter->title ?? $this->getDefaultChapterTitle($chapter->chapter_number)
            );

            // Use AI to analyze and suggest next section
            $suggestion = $this->aiGenerator->generate($analysisPrompt);

            // Parse the AI response to extract recommendation
            $analysisData = $this->parseChapterAnalysis($suggestion, $chapter->chapter_number);

            return response()->json([
                'success' => true,
                'analysis' => $analysisData,
                'fallback_ai' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('AI Section Suggestion also failed', [
                'project_id' => $project->id,
                'chapter_id' => $chapter->id,
                'error' => $e->getMessage(),
            ]);

            // Final fallback to basic analysis
            $fallbackAnalysis = $this->getFallbackChapterAnalysis($chapter->chapter_number, $currentContent);

            return response()->json([
                'success' => true,
                'analysis' => $fallbackAnalysis,
                'fallback_basic' => true,
            ]);
        }
    }

    /**
     * Build prompt for AI section analysis
     */
    private function buildSectionAnalysisPrompt(Project $project, int $chapterNumber, string $existingContent, string $chapterTitle): string
    {
        $prompt = "You are an academic writing expert analyzing a thesis chapter to suggest the next logical sub-section.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Chapter {$chapterNumber}: {$chapterTitle}\n\n";

        if (trim($existingContent)) {
            $prompt .= "EXISTING CHAPTER CONTENT:\n";
            $prompt .= "```\n".substr(strip_tags($existingContent), 0, 1500)."\n```\n\n";
            $prompt .= "ANALYSIS TASK:\n";
            $prompt .= "Based on the existing content above, suggest the next logical sub-section that should be written.\n\n";
        } else {
            $prompt .= "ANALYSIS TASK:\n";
            $prompt .= "The chapter is currently empty. Suggest the first sub-section that should be written for this chapter.\n\n";
        }

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. Consider the project topic and field of study\n";
        $prompt .= "2. Analyze what content already exists\n";
        $prompt .= "3. Suggest the most logical next sub-section\n";
        $prompt .= "4. Consider standard academic chapter structures for this field\n";
        $prompt .= "5. Make the suggestion specific to this project's needs\n\n";

        $prompt .= "RESPONSE FORMAT (provide ONLY this format):\n";
        $prompt .= "SECTION_ID: [short_identifier]\n";
        $prompt .= "SECTION_TITLE: [Full Section Title]\n";
        $prompt .= "SECTION_NUMBER: {$chapterNumber}.[number]\n";
        $prompt .= "DESCRIPTION: [Brief description of what this section should contain]\n";
        $prompt .= "RATIONALE: [Why this section is the logical next step]\n";

        return $prompt;
    }

    /**
     * Parse AI suggestion response into structured data
     */
    private function parseSectionSuggestion(string $aiResponse, int $chapterNumber): array
    {
        $lines = explode("\n", $aiResponse);
        $sectionData = [
            'id' => 'introduction',
            'title' => 'Introduction',
            'number' => "{$chapterNumber}.1",
            'description' => 'Chapter introduction and overview',
            'rationale' => 'Starting with an introduction provides context',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'SECTION_ID:') === 0) {
                $sectionData['id'] = trim(str_replace('SECTION_ID:', '', $line));
            } elseif (strpos($line, 'SECTION_TITLE:') === 0) {
                $sectionData['title'] = trim(str_replace('SECTION_TITLE:', '', $line));
            } elseif (strpos($line, 'SECTION_NUMBER:') === 0) {
                $sectionData['number'] = trim(str_replace('SECTION_NUMBER:', '', $line));
            } elseif (strpos($line, 'DESCRIPTION:') === 0) {
                $sectionData['description'] = trim(str_replace('DESCRIPTION:', '', $line));
            } elseif (strpos($line, 'RATIONALE:') === 0) {
                $sectionData['rationale'] = trim(str_replace('RATIONALE:', '', $line));
            }
        }

        return $sectionData;
    }

    /**
     * Fallback section suggestion if AI fails
     */
    private function getFallbackSectionSuggestion(int $chapterNumber, string $existingContent): array
    {
        $hasContent = trim($existingContent) !== '';

        if (! $hasContent) {
            return [
                'id' => 'introduction',
                'title' => 'Introduction',
                'number' => "{$chapterNumber}.1",
                'description' => 'Chapter introduction and overview',
                'rationale' => 'Starting with an introduction provides context for the chapter',
            ];
        }

        // Simple fallback based on common patterns
        $content = strtolower($existingContent);
        if (! str_contains($content, 'introduction')) {
            return [
                'id' => 'introduction',
                'title' => 'Introduction',
                'number' => "{$chapterNumber}.1",
                'description' => 'Chapter introduction and overview',
                'rationale' => 'Introduction section is missing',
            ];
        }

        return [
            'id' => 'content',
            'title' => 'Main Content',
            'number' => "{$chapterNumber}.2",
            'description' => 'Main chapter content',
            'rationale' => 'Continue with main chapter content',
        ];
    }

    /**
     * Get chapter and section-specific writing instructions
     */
    private function getChapterSectionInstructions(int $chapterNumber, string $sectionType): string
    {
        $instructions = "\n";

        // Chapter-specific context
        $chapterContext = $this->getChapterContext($chapterNumber);
        $instructions .= "CHAPTER CONTEXT:\n{$chapterContext}\n";

        // Section-specific instructions based on chapter and section type
        switch ($chapterNumber) {
            case 1: // Introduction Chapter
                $instructions .= $this->getIntroductionChapterSectionInstructions($sectionType);
                break;
            case 2: // Literature Review Chapter
                $instructions .= $this->getLiteratureChapterSectionInstructions($sectionType);
                break;
            case 3: // Methodology Chapter
                $instructions .= $this->getMethodologyChapterSectionInstructions($sectionType);
                break;
            case 4: // Results/Implementation Chapter
                $instructions .= $this->getResultsChapterSectionInstructions($sectionType);
                break;
            case 5: // Discussion Chapter
                $instructions .= $this->getDiscussionChapterSectionInstructions($sectionType);
                break;
            default:
                $instructions .= $this->getGeneralSectionInstructions($sectionType);
        }

        return $instructions."\n";
    }

    private function getSectionNumber(int $chapterNumber, string $sectionType): string
    {
        $sectionMap = [
            'introduction' => '1',
            'background' => '2',
            'problem_statement' => '3',
            'objectives' => '4',
            'scope' => '5',
            'theoretical_framework' => '2',
            'related_work' => '3',
            'research_gaps' => '4',
            'research_design' => '2',
            'data_collection' => '3',
            'system_development' => '4',
            'analytical_techniques' => '5',
            'system_implementation' => '2',
            'results' => '3',
            'analysis' => '4',
            'discussion' => '2',
            'implications' => '3',
            'limitations' => '4',
            'conclusion' => '6',
        ];

        $sectionNum = $sectionMap[$sectionType] ?? '1';

        return "{$chapterNumber}.{$sectionNum}";
    }

    private function getSectionTitle(string $sectionType): string
    {
        $titleMap = [
            'introduction' => 'Introduction',
            'background' => 'Background',
            'problem_statement' => 'Problem Statement',
            'objectives' => 'Objectives',
            'scope' => 'Scope and Limitations',
            'theoretical_framework' => 'Theoretical Framework',
            'related_work' => 'Related Work',
            'research_gaps' => 'Research Gaps',
            'research_design' => 'Research Design',
            'data_collection' => 'Data Collection Methods',
            'system_development' => 'System Development Process',
            'analytical_techniques' => 'Analytical Techniques',
            'system_implementation' => 'System Implementation',
            'results' => 'Results',
            'analysis' => 'Analysis',
            'discussion' => 'Discussion',
            'implications' => 'Implications',
            'limitations' => 'Limitations',
            'conclusion' => 'Conclusion',
        ];

        return $titleMap[$sectionType] ?? ucwords(str_replace('_', ' ', $sectionType));
    }

    private function getChapterContext(int $chapterNumber): string
    {
        $contexts = [
            1 => 'This is the Introduction chapter that establishes the research foundation, problem statement, and objectives.',
            2 => 'This is the Literature Review chapter that examines existing research and establishes theoretical framework.',
            3 => 'This is the Methodology chapter that explains the research approach, methods, and development process.',
            4 => 'This is the Implementation/Results chapter that presents the system implementation and findings.',
            5 => 'This is the Discussion chapter that interprets results and discusses implications.',
            6 => 'This is the Conclusion chapter that summarizes findings and suggests future work.',
        ];

        return $contexts[$chapterNumber] ?? 'This chapter focuses on the specific aspects of the research project.';
    }

    private function getMethodologyChapterSectionInstructions(string $sectionType): string
    {
        switch ($sectionType) {
            case 'introduction':
                return "SECTION FOCUS - METHODOLOGY INTRODUCTION:\n".
                       "- Introduce the chapter's purpose and structure\n".
                       "- Explain the methodological approach overview\n".
                       "- Preview the development and evaluation methods\n";

            case 'research_design':
                return "SECTION FOCUS - RESEARCH DESIGN:\n".
                       "- Explain the overall research approach (experimental, descriptive, etc.)\n".
                       "- Justify the chosen research design\n".
                       "- Describe the research framework and methodology\n".
                       "- Include citations to support methodological choices\n";

            case 'data_collection':
                return "SECTION FOCUS - DATA COLLECTION METHODS:\n".
                       "- Describe primary and secondary data sources\n".
                       "- Explain data gathering techniques and instruments\n".
                       "- Justify data collection methods chosen\n".
                       "- Address data quality and reliability measures\n";

            case 'system_development':
                return "SECTION FOCUS - SYSTEM DEVELOPMENT PROCESS:\n".
                       "- Outline the development methodology (Agile, Waterfall, etc.)\n".
                       "- Describe requirement analysis, design, implementation, testing phases\n".
                       "- Explain tools and technologies used\n".
                       "- Include development timeline and milestones\n";

            case 'analytical_techniques':
                return "SECTION FOCUS - ANALYTICAL TECHNIQUES:\n".
                       "- Describe quantitative and qualitative analysis methods\n".
                       "- Explain statistical techniques and software tools\n".
                       "- Justify analytical approaches chosen\n".
                       "- Address validity and reliability of analysis methods\n";

            default:
                return "SECTION FOCUS - METHODOLOGY SECTION:\n".
                       "- Focus on methodological aspects of the research\n".
                       "- Provide detailed explanations and justifications\n".
                       "- Include relevant citations and references\n";
        }
    }

    private function getIntroductionChapterSectionInstructions(string $sectionType): string
    {
        // Similar structure for other chapters...
        return "SECTION FOCUS - INTRODUCTION CHAPTER:\n".
               "- Focus on establishing research context and foundation\n".
               "- Provide clear problem definition and objectives\n".
               "- Include relevant background and motivation\n";
    }

    private function getLiteratureChapterSectionInstructions(string $sectionType): string
    {
        return "SECTION FOCUS - LITERATURE REVIEW CHAPTER:\n".
               "- Review and analyze existing research\n".
               "- Establish theoretical foundations\n".
               "- Identify research gaps and opportunities\n".
               "- Include extensive citations from verified sources\n";
    }

    private function getResultsChapterSectionInstructions(string $sectionType): string
    {
        return "SECTION FOCUS - RESULTS CHAPTER:\n".
               "- Present implementation details and findings\n".
               "- Show system performance and evaluation results\n".
               "- Include data analysis and interpretation\n";
    }

    private function getDiscussionChapterSectionInstructions(string $sectionType): string
    {
        return "SECTION FOCUS - DISCUSSION CHAPTER:\n".
               "- Interpret and analyze the results\n".
               "- Connect findings to existing literature\n".
               "- Discuss implications and applications\n";
    }

    private function getGeneralSectionInstructions(string $sectionType): string
    {
        return "SECTION FOCUS - GENERAL:\n".
               "- Write comprehensive academic content\n".
               "- Maintain logical flow and structure\n".
               "- Support arguments with evidence and citations\n";
    }

    /**
     * Build comprehensive chapter analysis prompt
     */
    private function buildChapterAnalysisPrompt(Project $project, int $chapterNumber, string $currentContent, string $chapterTitle): string
    {
        $prompt = "You are an academic writing expert analyzing a thesis chapter to provide intelligent recommendations.\n\n";

        $prompt .= "PROJECT CONTEXT:\n";
        $prompt .= "Topic: {$project->topic}\n";
        $prompt .= "Field: {$project->field_of_study}\n";
        $prompt .= "Level: {$project->type}\n";
        $prompt .= "University: {$project->universityRelation?->name}\n";
        $prompt .= "Chapter {$chapterNumber}: {$chapterTitle}\n\n";

        $contentLength = strlen(trim($currentContent));
        $wordCount = $contentLength > 0 ? str_word_count($currentContent) : 0;

        if ($contentLength > 0) {
            $prompt .= "CURRENT CHAPTER CONTENT:\n";
            $prompt .= "Word Count: {$wordCount}\n";
            $prompt .= "Content Length: {$contentLength} characters\n";
            $prompt .= "```\n".substr(strip_tags($currentContent), 0, 3000)."\n```\n\n";
        } else {
            $prompt .= "CURRENT CHAPTER STATUS: EMPTY\n\n";
        }

        $prompt .= "ANALYSIS TASK:\n";
        $prompt .= "Analyze the current chapter content and provide a recommendation.\n\n";

        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "1. Determine if the chapter is: EMPTY, PARTIAL, or COMPLETE\n";
        $prompt .= "2. If EMPTY: Recommend starting with first section\n";
        $prompt .= "3. If PARTIAL: Suggest the next logical sub-section\n";
        $prompt .= "4. If COMPLETE: Indicate no further sections needed\n";
        $prompt .= "5. Consider standard academic chapter structures for this field\n";
        $prompt .= "6. Analyze existing sections to avoid duplication\n\n";

        $prompt .= "RESPONSE FORMAT (provide ONLY this format):\n";
        $prompt .= "STATUS: [EMPTY|PARTIAL|COMPLETE]\n";
        $prompt .= "RECOMMENDATION: [NONE|START_CHAPTER|NEXT_SECTION]\n";
        $prompt .= "SECTION_NUMBER: [x.y format or NONE]\n";
        $prompt .= "SECTION_TITLE: [Section Title or NONE]\n";
        $prompt .= "DESCRIPTION: [Brief description or NONE]\n";
        $prompt .= "RATIONALE: [Why this is the recommended action]\n";

        return $prompt;
    }

    /**
     * Parse comprehensive chapter analysis response
     */
    private function parseChapterAnalysis(string $aiResponse, int $chapterNumber): array
    {
        $lines = explode("\n", $aiResponse);
        $analysisData = [
            'status' => 'EMPTY',
            'recommendation' => 'START_CHAPTER',
            'section' => [
                'number' => "{$chapterNumber}.1",
                'name' => 'Introduction',
                'description' => 'Chapter introduction and overview',
            ],
            'rationale' => 'Chapter is empty, starting with introduction',
            'show_section_button' => true,
            'show_full_chapter_button' => true,
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'STATUS:') === 0) {
                $analysisData['status'] = trim(str_replace('STATUS:', '', $line));
            } elseif (strpos($line, 'RECOMMENDATION:') === 0) {
                $analysisData['recommendation'] = trim(str_replace('RECOMMENDATION:', '', $line));
            } elseif (strpos($line, 'SECTION_NUMBER:') === 0) {
                $sectionNumber = trim(str_replace('SECTION_NUMBER:', '', $line));
                if ($sectionNumber !== 'NONE') {
                    $analysisData['section']['number'] = $sectionNumber;
                }
            } elseif (strpos($line, 'SECTION_TITLE:') === 0) {
                $sectionTitle = trim(str_replace('SECTION_TITLE:', '', $line));
                if ($sectionTitle !== 'NONE') {
                    $analysisData['section']['name'] = $sectionTitle;
                }
            } elseif (strpos($line, 'DESCRIPTION:') === 0) {
                $description = trim(str_replace('DESCRIPTION:', '', $line));
                if ($description !== 'NONE') {
                    $analysisData['section']['description'] = $description;
                }
            } elseif (strpos($line, 'RATIONALE:') === 0) {
                $analysisData['rationale'] = trim(str_replace('RATIONALE:', '', $line));
            }
        }

        // Set UI button states based on analysis
        $analysisData['show_section_button'] = $analysisData['recommendation'] === 'NEXT_SECTION' || $analysisData['recommendation'] === 'START_CHAPTER';
        $analysisData['show_full_chapter_button'] = $analysisData['status'] === 'EMPTY' || $analysisData['status'] === 'PARTIAL';

        return $analysisData;
    }

    /**
     * Fallback chapter analysis if AI fails
     */
    private function getFallbackChapterAnalysis(int $chapterNumber, string $currentContent): array
    {
        $contentLength = strlen(trim($currentContent));
        $wordCount = $contentLength > 0 ? str_word_count($currentContent) : 0;

        if ($contentLength === 0) {
            return [
                'status' => 'EMPTY',
                'recommendation' => 'START_CHAPTER',
                'section' => [
                    'number' => "{$chapterNumber}.1",
                    'name' => 'Introduction',
                    'description' => 'Chapter introduction and overview',
                ],
                'rationale' => 'Chapter is empty, starting with introduction',
                'show_section_button' => true,
                'show_full_chapter_button' => true,
            ];
        } elseif ($wordCount < 1000) {
            return [
                'status' => 'PARTIAL',
                'recommendation' => 'NEXT_SECTION',
                'section' => [
                    'number' => "{$chapterNumber}.2",
                    'name' => 'Main Content',
                    'description' => 'Continue with main chapter content',
                ],
                'rationale' => 'Chapter has some content but appears incomplete',
                'show_section_button' => true,
                'show_full_chapter_button' => true,
            ];
        } else {
            return [
                'status' => 'COMPLETE',
                'recommendation' => 'NONE',
                'section' => [
                    'number' => 'NONE',
                    'name' => 'NONE',
                    'description' => 'NONE',
                ],
                'rationale' => 'Chapter appears to be complete',
                'show_section_button' => false,
                'show_full_chapter_button' => false,
            ];
        }
    }

    /**
     * Check if content is truly empty (handles HTML tags from rich text editor)
     */
    private function isContentEmpty(string $content): bool
    {
        // Remove all HTML tags and get plain text
        $plainText = strip_tags($content);

        // Remove all whitespace characters (spaces, tabs, newlines, etc.)
        $cleanText = preg_replace('/\s+/', '', $plainText);

        // Check if there's any actual content left
        return empty($cleanText);
    }

    /**
     * Upload and analyze file for chat context
     */
    public function uploadChatFile(Request $request, Project $project, int $chapterNumber)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'session_id' => 'required|string|max:36',
        ]);

        try {
            // Process the uploaded file
            $result = $this->documentService->processUploadedFile(
                $validated['file'],
                auth()->id(),
                $project->id
            );

            // Save to database
            $upload = ChatFileUpload::create([
                'upload_id' => $result['upload_id'],
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'session_id' => $validated['session_id'],
                'original_filename' => $result['file_name'],
                'stored_path' => $result['stored_path'],
                'mime_type' => $result['mime_type'],
                'file_size' => $result['file_size'],
                'extracted_text' => $result['text_content'],
                'analysis_results' => $result['analysis'],
                'word_count' => $result['analysis']['word_count'] ?? 0,
                'citations_found' => $result['analysis']['citations_found'] ?? 0,
                'main_topics' => $result['analysis']['main_topics'] ?? [],
                'status' => 'completed',
            ]);

            Log::info('File uploaded for chat analysis', [
                'upload_id' => $upload->upload_id,
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'file_name' => $result['file_name'],
                'file_size' => $result['file_size'],
                'word_count' => $upload->word_count,
            ]);

            return response()->json([
                'success' => true,
                'upload' => [
                    'id' => $upload->upload_id,
                    'filename' => $upload->original_filename,
                    'size' => $upload->formatted_file_size,
                    'word_count' => $upload->word_count,
                    'citations_found' => $upload->citations_found,
                    'main_topics' => array_slice($upload->main_topics ?: [], 0, 5),
                    'summary' => $upload->getSummaryForChat(),
                    'analysis' => $upload->analysis_results['ai_analysis'] ?? 'Analysis completed',
                ],
                'message' => 'File uploaded and analyzed successfully!',
            ]);

        } catch (\InvalidArgumentException $e) {
            Log::warning('File upload validation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'file_name' => $validated['file']->getClientOriginalName() ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            Log::error('File upload processing failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $validated['file']->getClientOriginalName() ?? 'unknown',
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process file. Please try again or contact support.',
            ], 500);
        }
    }

    /**
     * Get uploaded files for a chat session
     */
    public function getChatFiles(Request $request, Project $project, int $chapterNumber)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'session_id' => 'required|string|max:36',
        ]);

        $uploads = ChatFileUpload::where('user_id', auth()->id())
            ->where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->where('session_id', $validated['session_id'])
            ->active()
            ->completed()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'files' => $uploads->map(function ($upload) {
                return [
                    'id' => $upload->upload_id,
                    'filename' => $upload->original_filename,
                    'size' => $upload->formatted_file_size,
                    'word_count' => $upload->word_count,
                    'citations_found' => $upload->citations_found,
                    'main_topics' => array_slice($upload->main_topics ?: [], 0, 5),
                    'uploaded_at' => $upload->created_at->diffForHumans(),
                    'summary' => $upload->getSummaryForChat(),
                ];
            }),
        ]);
    }

    /**
     * Delete an uploaded file
     */
    public function deleteChatFile(Request $request, Project $project, int $chapterNumber, string $uploadId)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        $upload = ChatFileUpload::where('upload_id', $uploadId)
            ->where('user_id', auth()->id())
            ->where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();

        if ($upload->deleteFile()) {
            Log::info('Chat file deleted', [
                'upload_id' => $uploadId,
                'user_id' => auth()->id(),
                'filename' => $upload->original_filename,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Failed to delete file',
        ], 500);
    }

    /**
     * Search chat history for a chapter
     */
    public function searchChatHistory(Request $request, Project $project, int $chapterNumber)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'q' => 'required|string|min:2|max:255',
            'type' => 'nullable|string|in:user,ai,system',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $validated['q'];
        $messageType = $validated['type'] ?? null;
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 10;

        try {
            // Build the search query
            $searchQuery = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->where('content', 'like', "%{$query}%");

            // Filter by message type if specified
            if ($messageType) {
                $searchQuery->where('message_type', $messageType);
            }

            // Get total count for pagination
            $totalResults = $searchQuery->count();

            // Get paginated results
            $results = $searchQuery
                ->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get(['id', 'content', 'message_type', 'session_id', 'created_at']);

            // Process results for display
            $processedResults = $results->map(function ($message) use ($query) {
                // Highlight search terms in content
                $highlighted = $this->highlightSearchTerms($message->content, $query);

                // Get context (surrounding messages)
                $context = $this->getMessageContext($message->id, $message->session_id);

                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'message_type' => $message->message_type,
                    'timestamp' => $message->created_at->toISOString(),
                    'session_id' => $message->session_id,
                    'highlight' => $highlighted,
                    'context' => $context,
                ];
            });

            Log::info('Chat history search performed', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'query' => $query,
                'type_filter' => $messageType,
                'results_count' => $processedResults->count(),
                'total_results' => $totalResults,
            ]);

            return response()->json([
                'success' => true,
                'results' => $processedResults,
                'total' => $totalResults,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalResults / $perPage),
            ]);

        } catch (\Exception $e) {
            Log::error('Chat history search failed', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Search failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Highlight search terms in content
     */
    private function highlightSearchTerms(string $content, string $query): string
    {
        // Escape special regex characters in the query
        $escapedQuery = preg_quote($query, '/');

        // Split query into individual words
        $words = preg_split('/\s+/', trim($escapedQuery));

        // Highlight each word
        foreach ($words as $word) {
            if (strlen($word) >= 2) {
                $content = preg_replace(
                    '/('.$word.')/i',
                    '<span class="search-highlight">$1</span>',
                    $content
                );
            }
        }

        return $content;
    }

    /**
     * Get context around a message (previous and next messages)
     */
    private function getMessageContext(int $messageId, string $sessionId): string
    {
        try {
            // Get the message and its order
            $message = ChatConversation::find($messageId);
            if (! $message) {
                return '';
            }

            // Get surrounding messages
            $contextMessages = ChatConversation::where('session_id', $sessionId)
                ->where('id', '!=', $messageId)
                ->where('message_order', '>=', $message->message_order - 1)
                ->where('message_order', '<=', $message->message_order + 1)
                ->orderBy('message_order')
                ->get(['content', 'message_type']);

            if ($contextMessages->isEmpty()) {
                return '';
            }

            // Format context
            $context = $contextMessages->map(function ($msg) {
                $prefix = $msg->message_type === 'user' ? 'You:' : 'AI:';
                $content = substr($msg->content, 0, 100);
                if (strlen($msg->content) > 100) {
                    $content .= '...';
                }

                return $prefix.' '.$content;
            })->implode(' | ');

            return $context;

        } catch (\Exception $e) {
            Log::warning('Failed to get message context', [
                'message_id' => $messageId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Get chat history for a chapter (organized by sessions)
     */
    public function getChatHistorySessions(Request $request, Project $project, int $chapterNumber)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:20',
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 10;

        try {
            // Get total sessions count first (without aliases)
            $totalSessions = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->distinct('session_id')
                ->count('session_id');

            // Get sessions with data
            $sessions = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->select('session_id', DB::raw('MIN(created_at) as session_start'), DB::raw('MAX(created_at) as session_end'), DB::raw('COUNT(*) as message_count'))
                ->groupBy('session_id')
                ->orderBy(DB::raw('MIN(created_at)'), 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $sessionsWithMessages = $sessions->map(function ($session) {
                // Get messages for this session
                $messages = ChatConversation::where('session_id', $session->session_id)
                    ->orderBy('message_order')
                    ->get(['id', 'content', 'message_type', 'created_at']);

                // Get first user message for session title
                $firstUserMessage = $messages->where('message_type', 'user')->first();
                $sessionTitle = $firstUserMessage
                    ? substr($firstUserMessage->content, 0, 50).(strlen($firstUserMessage->content) > 50 ? '...' : '')
                    : 'Chat Session';

                return [
                    'session_id' => $session->session_id,
                    'title' => $sessionTitle,
                    'message_count' => $session->message_count,
                    'session_start' => $session->session_start,
                    'session_end' => $session->session_end,
                    'started_at' => \Carbon\Carbon::parse($session->session_start)->diffForHumans(),
                    'duration' => $this->getSessionDuration($session->session_start, $session->session_end),
                    'messages' => $messages->map(function ($message) {
                        return [
                            'id' => $message->id,
                            'content' => $message->content,
                            'message_type' => $message->message_type,
                            'timestamp' => $message->created_at->toISOString(),
                            'formatted_time' => $message->created_at->format('H:i'),
                        ];
                    }),
                ];
            });

            Log::info('Chat history retrieved', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'sessions_count' => $sessionsWithMessages->count(),
                'page' => $page,
            ]);

            return response()->json([
                'success' => true,
                'sessions' => $sessionsWithMessages,
                'total_sessions' => $totalSessions,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalSessions / $perPage),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve chat history', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load chat history. Please try again.',
            ], 500);
        }
    }

    /**
     * Delete a specific chat session
     */
    public function deleteChatSession(Request $request, Project $project, int $chapterNumber, string $sessionId)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Verify session belongs to user and chapter
            $messageCount = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->where('session_id', $sessionId)
                ->count();

            if ($messageCount === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Chat session not found.',
                ], 404);
            }

            // Delete all messages in the session
            $deletedCount = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->where('session_id', $sessionId)
                ->delete();

            Log::info('Chat session deleted', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'session_id' => $sessionId,
                'messages_deleted' => $deletedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Chat session deleted successfully. {$deletedCount} messages removed.",
                'deleted_messages' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete chat session', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete chat session. Please try again.',
            ], 500);
        }
    }

    /**
     * Delete specific chat message
     */
    public function deleteChatMessage(Request $request, Project $project, int $chapterNumber, int $messageId)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Find and verify message belongs to user and chapter
            $message = ChatConversation::where('id', $messageId)
                ->where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->first();

            if (! $message) {
                return response()->json([
                    'success' => false,
                    'error' => 'Message not found.',
                ], 404);
            }

            $sessionId = $message->session_id;
            $messageType = $message->message_type;

            // Delete the message
            $message->delete();

            Log::info('Chat message deleted', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'message_id' => $messageId,
                'session_id' => $sessionId,
                'message_type' => $messageType,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message deleted successfully.',
                'session_id' => $sessionId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete chat message', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete message. Please try again.',
            ], 500);
        }
    }

    /**
     * Clear all chat history for a chapter
     */
    public function clearChatHistory(Request $request, Project $project, int $chapterNumber)
    {
        // Validate user owns project
        abort_if($project->user_id !== auth()->id(), 403);

        try {
            // Count existing messages
            $totalMessages = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->count();

            if ($totalMessages === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No chat history to clear.',
                    'deleted_messages' => 0,
                ]);
            }

            // Delete all messages for this chapter
            $deletedCount = ChatConversation::where('user_id', auth()->id())
                ->where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->delete();

            Log::info('All chat history cleared for chapter', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'messages_deleted' => $deletedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => "All chat history cleared. {$deletedCount} messages deleted.",
                'deleted_messages' => $deletedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear chat history', [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to clear chat history. Please try again.',
            ], 500);
        }
    }

    /**
     * Calculate session duration in human readable format
     */
    private function getSessionDuration(string $startTime, string $endTime): string
    {
        try {
            $start = \Carbon\Carbon::parse($startTime);
            $end = \Carbon\Carbon::parse($endTime);

            $diffInMinutes = (int) $start->diffInMinutes($end);

            if ($diffInMinutes < 1) {
                return 'Less than a minute';
            } elseif ($diffInMinutes < 60) {
                return $diffInMinutes.' minute'.($diffInMinutes !== 1 ? 's' : '');
            } else {
                $hours = (int) floor($diffInMinutes / 60);
                $minutes = (int) ($diffInMinutes % 60);
                $duration = $hours.' hour'.($hours !== 1 ? 's' : '');
                if ($minutes > 0) {
                    $duration .= ', '.$minutes.' minute'.($minutes !== 1 ? 's' : '');
                }

                return $duration;
            }
        } catch (\Exception $e) {
            return 'Unknown duration';
        }
    }

    /**
     * Generate streaming content with simplified single-pass approach for progressive chapters
     */
    private function generateStreamingContentSimplified(
        Project $project,
        string $prompt,
        string $chapterType,
        int $targetWordCount,
        int $maxWordCount
    ): string {
        $fullContent = '';
        $chunkCount = 0;

        Log::info('PROGRESSIVE STREAM - Starting single-pass generation', [
            'target_word_count' => $targetWordCount,
            'maximum_word_count' => $maxWordCount,
        ]);

        $systemPrompt = $this->promptRouter->getSystemPrompt($project);
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ];

        // Stream the generation and collect content with smart stopping
        foreach ($this->aiGenerator->generateOptimizedMessages($messages, $chapterType) as $chunk) {
            $chunkCount++;
            $fullContent .= $chunk;
            $wordCount = $this->computeWordCount($fullContent);

            // Stop if we've reached target word count (allow completion)
            if ($wordCount >= $targetWordCount) { // Changed from 90% to 100% to ensure chapters are complete
                Log::info('PROGRESSIVE STREAM - Stopping: reached target word count', [
                    'current_word_count' => $wordCount,
                    'target_word_count' => $targetWordCount,
                    'percentage_achieved' => round(($wordCount / $targetWordCount) * 100),
                    'chunk_count' => $chunkCount,
                ]);

                $this->sendSSEMessage([
                    'type' => 'generation_completed',
                    'word_count' => $wordCount,
                    'target_word_count' => $targetWordCount,
                    'message' => 'Chapter generation completed successfully.',
                ]);
                break;
            }

            // Absolute maximum stop point to prevent over-generation
            if ($wordCount >= $maxWordCount) {
                Log::info('PROGRESSIVE STREAM - Stopping: maximum word count reached', [
                    'current_word_count' => $wordCount,
                    'max_word_count' => $maxWordCount,
                    'chunk_count' => $chunkCount,
                ]);

                $this->sendSSEMessage([
                    'type' => 'generation_stopped',
                    'reason' => 'maximum_word_count_reached',
                    'word_count' => $wordCount,
                    'message' => 'Generation stopped at reasonable word count limit.',
                ]);
                break;
            }

            // Send content update for real-time display
            $this->sendSSEMessage([
                'type' => 'content',
                'content' => $chunk,
                'word_count' => $wordCount,
                'target_word_count' => $targetWordCount,
                'max_word_count' => $maxWordCount,
                'progress_percentage' => round(($wordCount / $targetWordCount) * 100),
                'is_section_append' => false,
            ]);

            // Add delay to slow down streaming for better UX
            usleep(50000); // 50ms delay between chunks

            // Flush every 100 words to avoid timeout
            if ($wordCount % 100 === 0) {
                $this->sendSSEMessage(['type' => 'heartbeat', 'word_count' => $wordCount]);
            }
        }

        $finalWordCount = $this->computeWordCount($fullContent);

        Log::info('PROGRESSIVE STREAM - Generation completed', [
            'final_word_count' => $finalWordCount,
            'target_word_count' => $targetWordCount,
            'percentage_achieved' => round(($finalWordCount / $targetWordCount) * 100),
            'total_chunks' => $chunkCount,
        ]);

        return $fullContent;
    }

    /**
     * Delete a chapter
     */
    public function destroy(Project $project, Chapter $chapter)
    {
        \Illuminate\Support\Facades\Log::info('🗑️ DESTROY METHOD CALLED', [
            'method' => 'ChapterController@destroy',
            'request_url' => request()->fullUrl(),
            'route_params' => request()->route()->parameters(),
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'user_id' => $project->user_id,
                'title' => $project->title,
            ],
            'chapter' => [
                'id' => $chapter->id,
                'slug' => $chapter->slug,
                'project_id' => $chapter->project_id,
                'title' => $chapter->title,
                'chapter_number' => $chapter->chapter_number,
            ],
            'current_user_id' => auth()->id(),
            'is_authenticated' => auth()->check(),
        ]);

        // Check if user owns the project
        if ($project->user_id !== auth()->id()) {
            \Illuminate\Support\Facades\Log::error('❌ AUTHORIZATION FAILED - User does not own project', [
                'project_user_id' => $project->user_id,
                'current_user_id' => auth()->id(),
                'project_id' => $project->id,
            ]);
            abort(403, 'You do not own this project');
        }

        // Check if chapter belongs to the project
        if ($chapter->project_id !== $project->id) {
            \Illuminate\Support\Facades\Log::error('❌ AUTHORIZATION FAILED - Chapter does not belong to project', [
                'chapter_project_id' => $chapter->project_id,
                'route_project_id' => $project->id,
                'chapter_id' => $chapter->id,
                'project_id' => $project->id,
            ]);
            abort(403, 'Chapter does not belong to this project');
        }

        \Illuminate\Support\Facades\Log::info('✅ AUTHORIZATION PASSED - Proceeding with deletion', [
            'chapter_id' => $chapter->id,
            'project_id' => $project->id,
            'user_id' => auth()->id(),
        ]);

        // Delete the chapter
        $deleted = $chapter->delete();

        \Illuminate\Support\Facades\Log::info('🏁 DELETION COMPLETED', [
            'deleted' => $deleted,
            'chapter_id' => $chapter->id,
            'chapter_slug' => $chapter->slug,
        ]);

        // If this is an Inertia request, handle accordingly
        if (request()->header('X-Inertia')) {
            // Check if we're currently on the chapter editor page for this specific chapter
            $currentUrl = request()->headers->get('X-Inertia-Location') ?? request()->headers->get('referer');
            $isViewingDeletedChapter = str_contains($currentUrl, "/chapters/{$chapter->slug}/write");

            if ($isViewingDeletedChapter) {
                // Redirect to project writing page with flash message
                return redirect()->route('projects.writing', $project->slug)->with('flash', [
                    'type' => 'success',
                    'message' => 'Chapter deleted successfully',
                ]);
            }
        }

        // For other cases, redirect back with flash message
        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Chapter deleted successfully',
        ]);
    }

    /**
     * EXPORT CHAPTER TO PDF
     * Generates a professional PDF document for the chapter
     * Uses Browsershot for reliable PDF generation
     */
    public function exportChapterPdf(Project $project, int $chapterNumber)
    {
        $startTime = microtime(true);

        Log::info('Chapter PDF Export Request Received', [
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
            'user_id' => auth()->id(),
            'project_slug' => $project->slug,
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            // Ensure user owns the project
            Log::info('Chapter PDF Export: Checking user authorization', [
                'project_user_id' => $project->user_id,
                'auth_user_id' => auth()->id(),
                'is_authorized' => $project->user_id === auth()->id(),
            ]);
            abort_if($project->user_id !== auth()->id(), 403);

            // Get the chapter
            $chapter = Chapter::where('project_id', $project->id)
                ->where('chapter_number', $chapterNumber)
                ->firstOrFail();

            Log::info('Chapter PDF Export: Chapter found', [
                'chapter_id' => $chapter->id,
                'chapter_title' => $chapter->title,
                'has_content' => ! empty($chapter->content),
                'word_count' => $chapter->word_count,
            ]);

            // Ensure chapter has content to export
            abort_if(empty($chapter->content), 404, 'No content available for export');

            // Load project with all necessary relationships for PDF generation
            $project->load(['user', 'category']);

            Log::info('Chapter PDF Export: Project data loaded', [
                'project_id' => $project->id,
                'has_user_relation' => $project->user !== null,
                'has_category_relation' => $project->category !== null,
                'user_name' => $project->user->name ?? 'N/A',
            ]);

            // Convert Tiptap JSON content to HTML for PDF
            $chapterContent = $this->convertTiptapToHtml($chapter->content);

            // Get formatted chapter references for single chapter export
            $chapterReferenceService = app(\App\Services\ChapterReferenceService::class);
            $formattedReferences = $chapterReferenceService->formatChapterReferencesSection($chapter);

            // Strip inline references from chapter content since we'll append them separately
            $chapterContent = $this->stripReferencesFromHtml($chapterContent);

            // Create a unique filename
            $fileName = sprintf(
                'chapter_%d_%s_%s.pdf',
                $chapterNumber,
                Str::slug($chapter->title),
                now()->format('Ymd-His')
            );

            // Create directory if it doesn't exist
            $directory = storage_path('app/public/chapter-exports/'.date('Y/m'));
            if (! File::isDirectory($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            $filePath = $directory.'/'.$fileName;

            try {
                // Generate PDF using Spatie PDF with Browsershot for reliability
                $pdf = Pdf::view('pdf.chapter', [
                    'project' => $project,
                    'chapter' => $chapter,
                    'chapterContent' => $chapterContent,
                    'formattedReferences' => $formattedReferences,
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

                        Log::info('Chapter PDF Generation: Chrome Path Detection', [
                            'available_paths' => $chromePaths,
                            'config_chrome_path' => config('app.chrome_path'),
                        ]);

                        $chromePath = null;
                        foreach ($chromePaths as $path) {
                            Log::debug("Chapter PDF Generation: Testing Chrome path: {$path}");
                            if ($path && file_exists($path) && is_executable($path)) {
                                $chromePath = $path;
                                Log::info("Chapter PDF Generation: Chrome path found: {$chromePath}");
                                break;
                            }
                        }

                        if (! $chromePath) {
                            Log::error('Chapter PDF Generation: No Chrome path found!', [
                                'tested_paths' => $chromePaths,
                            ]);
                            throw new \Exception('Chrome/Chromium browser not found for PDF generation');
                        }

                        Log::info('Chapter PDF Generation: Configuring Browsershot', [
                            'chrome_path' => $chromePath,
                            'format' => 'A4',
                            'margins' => '20x20x20x20',
                            'timeout' => 120,
                        ]);

                        $browsershot->setChromePath($chromePath)
                            ->format('A4')
                            ->windowSize(800, 1131)
                            ->scale(1.2)
                            ->deviceScaleFactor(2.0)
                            ->margins(20, 20, 20, 20) // Professional academic margins
                            ->showBackground()
                            ->waitUntilNetworkIdle() // Wait for all resources to load
                            ->userAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                            ->timeout(120)
                            ->showBrowserHeaderAndFooter()
                            ->hideHeader()
                            ->footerHtml('<div style="text-align: center; font-size: 10px; color: #6b7280; font-family: Times New Roman, serif; padding: 8px 0; width: 100%; display: block;">Generated by Finalyze AI Academic Assistant | '.now()->format('F j, Y \a\t g:i A').'</div>')
                            ->noSandbox()
                            ->setOption('disable-web-security', true)
                            ->setOption('allow-running-insecure-content', true);
                    });

                Log::info('Chapter PDF Generation: Starting PDF creation', [
                    'view' => 'pdf.chapter',
                    'output_path' => $filePath,
                    'chapter_data' => [
                        'id' => $chapter->id,
                        'number' => $chapter->chapter_number,
                        'title' => $chapter->title,
                        'has_content' => ! empty($chapterContent),
                    ],
                ]);

                $pdf->save($filePath);

                Log::info('Chapter PDF Generation: Save operation completed', [
                    'file_path' => $filePath,
                    'file_exists' => File::exists($filePath),
                ]);

                if (! File::exists($filePath)) {
                    Log::error('Chapter PDF Generation: File was not created', [
                        'expected_path' => $filePath,
                        'directory_exists' => File::exists(dirname($filePath)),
                        'directory_writable' => is_writable(dirname($filePath)),
                    ]);
                    throw new \Exception("PDF file was not created at: {$filePath}");
                }

                // Validate PDF file format
                $fileSize = File::size($filePath);
                $fileHeader = file_get_contents($filePath, false, null, 0, 4);

                Log::info('Chapter PDF Generation: File validation', [
                    'file_size' => $fileSize,
                    'file_header' => bin2hex($fileHeader),
                    'is_valid_pdf' => $fileHeader === '%PDF',
                ]);

                if ($fileHeader !== '%PDF') {
                    Log::error('Chapter PDF Generation: Invalid PDF format detected', [
                        'file_path' => $filePath,
                        'file_size' => $fileSize,
                        'header_hex' => bin2hex($fileHeader),
                        'first_100_chars' => substr(file_get_contents($filePath), 0, 100),
                    ]);
                }

                // Log successful PDF generation
                $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('Chapter PDF Generated Successfully', [
                    'file' => $filePath,
                    'execution_time_ms' => $executionTime,
                ]);

                // Log download preparation
                Log::info('Chapter PDF Export: Preparing download response', [
                    'filename' => $fileName,
                    'content_type' => 'application/pdf',
                    'file_size' => File::size($filePath),
                    'project_id' => $project->id,
                    'chapter_number' => $chapterNumber,
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
                Log::error('Chapter PDF Generation Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file_path' => $filePath ?? 'not_set',
                ]);

                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Chapter PDF Export Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $project->id ?? null,
                'chapter_number' => $chapterNumber,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'error' => 'Failed to generate PDF. Please try again.',
                'message' => 'PDF generation encountered an error: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert Tiptap JSON content to HTML
     */
    /**
     * Remove References section from HTML content
     * Used for single chapter exports where references are appended separately
     */
    private function stripReferencesFromHtml(string $html): string
    {
        // Remove div.references-section wrapper if present
        $html = preg_replace(
            '/<div[^>]*class="references-section"[^>]*>.*?<\/div>/is',
            '',
            $html
        );

        // Remove References heading and all content until next heading or end
        $html = preg_replace(
            '/<h[12][^>]*>\s*REFERENCES?\s*<\/h[12]>.*?(?=<h[12]|$)/is',
            '',
            $html
        );

        return trim($html);
    }

    private function convertTiptapToHtml(string $content): string
    {
        // If content is already HTML, process it for mermaid blocks and return
        if (str_starts_with(trim($content), '<')) {
            return $this->processMermaidInHtml($content);
        }

        // Try to decode as JSON (Tiptap format)
        $json = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If not JSON, treat as plain text
            return nl2br(e($content));
        }

        // Basic Tiptap to HTML conversion
        return $this->tiptapNodeToHtml($json);
    }

    /**
     * Process HTML content to convert mermaid data attributes to proper mermaid divs
     */
    private function processMermaidInHtml(string $html): string
    {
        // Convert data-mermaid divs to proper mermaid class divs for Mermaid.js
        $html = preg_replace_callback(
            '/<div[^>]*data-mermaid[^>]*data-mermaid-code="([^"]*)"[^>]*>.*?<\/div>/s',
            function ($matches) {
                $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');

                return '<div class="mermaid">'."\n".$code."\n".'</div>';
            },
            $html
        );

        // Also handle pre/code blocks with language-mermaid class
        $html = preg_replace_callback(
            '/<pre[^>]*>\s*<code[^>]*class="[^"]*language-mermaid[^"]*"[^>]*>([\s\S]*?)<\/code>\s*<\/pre>/s',
            function ($matches) {
                $code = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                $code = strip_tags($code); // Remove any nested HTML tags

                return '<div class="mermaid">'."\n".trim($code)."\n".'</div>';
            },
            $html
        );

        return $html;
    }

    /**
     * Recursively convert Tiptap nodes to HTML
     */
    private function tiptapNodeToHtml(array $node): string
    {
        $html = '';

        if (! isset($node['type'])) {
            return $html;
        }

        $type = $node['type'];
        $content = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];
        $attrs = $node['attrs'] ?? [];

        // Handle text nodes
        if ($type === 'text') {
            $text = $node['text'] ?? '';

            // Apply marks (bold, italic, etc.)
            foreach ($marks as $mark) {
                $markType = $mark['type'];
                switch ($markType) {
                    case 'bold':
                        $text = "<strong>{$text}</strong>";
                        break;
                    case 'italic':
                        $text = "<em>{$text}</em>";
                        break;
                    case 'underline':
                        $text = "<u>{$text}</u>";
                        break;
                    case 'code':
                        $text = "<code>{$text}</code>";
                        break;
                    case 'link':
                        $href = $mark['attrs']['href'] ?? '#';
                        $text = "<a href=\"{$href}\">{$text}</a>";
                        break;
                }
            }

            return $text;
        }

        // Handle block nodes
        $childrenHtml = '';
        foreach ($content as $child) {
            $childrenHtml .= $this->tiptapNodeToHtml($child);
        }

        switch ($type) {
            case 'doc':
                return $childrenHtml;
            case 'paragraph':
                return "<p>{$childrenHtml}</p>";
            case 'heading':
                $level = $attrs['level'] ?? 1;

                return "<h{$level}>{$childrenHtml}</h{$level}>";
            case 'bulletList':
                return "<ul>{$childrenHtml}</ul>";
            case 'orderedList':
                return "<ol>{$childrenHtml}</ol>";
            case 'listItem':
                return "<li>{$childrenHtml}</li>";
            case 'blockquote':
                return "<blockquote>{$childrenHtml}</blockquote>";
            case 'codeBlock':
                return "<pre><code>{$childrenHtml}</code></pre>";
            case 'hardBreak':
                return '<br>';
            case 'horizontalRule':
                return '<hr>';
            case 'mermaid':
                $code = $attrs['code'] ?? $childrenHtml;

                return '<div class="mermaid">'."\n".$code."\n".'</div>';
            case 'table':
                return "<table>{$childrenHtml}</table>";
            case 'tableRow':
                return "<tr>{$childrenHtml}</tr>";
            case 'tableHeader':
                return "<th>{$childrenHtml}</th>";
            case 'tableCell':
                return "<td>{$childrenHtml}</td>";
            default:
                return $childrenHtml;
        }
    }
}
