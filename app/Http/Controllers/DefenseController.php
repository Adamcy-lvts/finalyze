<?php

namespace App\Http\Controllers;

use App\Http\Requests\Defense\AnalyzeOpeningStatementRequest;
use App\Http\Requests\Defense\GenerateDefenseQuestionsRequest;
use App\Http\Requests\Defense\GetDefenseQuestionsRequest;
use App\Http\Requests\Defense\MarkDefenseQuestionHelpfulRequest;
use App\Http\Requests\Defense\NextDefenseQuestionRequest;
use App\Http\Requests\Defense\StartDefenseSessionRequest;
use App\Http\Requests\Defense\StreamGenerateDefenseQuestionsRequest;
use App\Http\Requests\Defense\SubmitDefenseResponseRequest;
use App\Http\Resources\Defense\DefenseFeedbackResource;
use App\Http\Resources\Defense\DefenseMessageResource;
use App\Http\Resources\Defense\DefenseSessionResource;
use App\DTOs\Defense\StartDefenseSessionData;
use App\DTOs\Defense\SubmitDefenseResponseData;
use App\DTOs\Defense\NextDefenseQuestionData;
use App\DTOs\Defense\DefenseQuestionsQueryData;
use App\DTOs\Defense\GenerateDefenseQuestionsData;
use App\DTOs\Defense\StreamDefenseQuestionsData;
use App\Models\Chapter;
use App\Models\DefenseFeedback;
use App\Models\DefensePreparation;
use App\Models\DefenseQuestion;
use App\Models\DefenseSession;
use App\Models\Project;
use App\Services\AIContentGenerator;
use App\Services\ChapterContentAnalysisService;
use App\Services\Defense\DefenseCreditService;
use App\Services\Defense\DefensePromptBuilder;
use App\Services\Defense\DefenseSimulationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DefenseController extends Controller
{
    private AIContentGenerator $aiGenerator;

    private ChapterContentAnalysisService $contentAnalysis;

    private DefenseSimulationService $defenseSimulation;

    private DefenseCreditService $defenseCredit;

    private DefensePromptBuilder $promptBuilder;

    public function __construct(
        AIContentGenerator $aiGenerator,
        ChapterContentAnalysisService $contentAnalysis,
        DefenseSimulationService $defenseSimulation,
        DefenseCreditService $defenseCredit,
        DefensePromptBuilder $promptBuilder
    ) {
        $this->aiGenerator = $aiGenerator;
        $this->contentAnalysis = $contentAnalysis;
        $this->defenseSimulation = $defenseSimulation;
        $this->defenseCredit = $defenseCredit;
        $this->promptBuilder = $promptBuilder;
    }

    /**
     * Get defense questions for a project
     */
    public function getQuestions(GetDefenseQuestionsRequest $request, $project_id)
    {
        // Manually load the project by ID
        $project = Project::findOrFail($project_id);

        // Authorization
        abort_if($project->user_id !== auth()->id(), 403);

        // More lenient validation
        $data = DefenseQuestionsQueryData::fromArray($request->validated());

        // Log the request for debugging
        Log::info('Defense questions requested', [
            'project_id' => $project->id,
            'chapter_number' => $data->chapterNumber,
            'limit' => $data->limit,
            'force_refresh' => $data->forceRefresh,
            'skip_generation' => $data->skipGeneration,
        ]);

        // Cache key
        $cacheKey = "defense_questions_{$project->id}_{$data->chapterNumber}";

        // Check cache first (unless force refresh)
        if (! $data->forceRefresh && Cache::has($cacheKey)) {
            $cachedQuestions = Cache::get($cacheKey);

            return response()->json([
                'questions' => $cachedQuestions,
                'source' => 'cache',
                'next_refresh' => now()->addHours(6),
            ]);
        }

        // Get existing questions from database
        $query = DefenseQuestion::where('project_id', $project->id)
            ->active()
            ->notRecentlyShown(24);

        if ($data->chapterNumber) {
            $query->forChapter($data->chapterNumber);
        }

        if ($data->difficulty && $data->difficulty !== 'all') {
            $query->byDifficulty($data->difficulty);
        }

        $existingQuestions = $query->inRandomOrder()
            ->limit($data->limit)
            ->get();

        // Check if we need to generate more
        if (! $data->skipGeneration && $existingQuestions->count() < 3) {
            // For now, generate synchronously if no questions exist
            if ($existingQuestions->isEmpty()) {
                $existingQuestions = $this->generateQuestionsSynchronously($project, $data->chapterNumber, 3);

                // If still empty and chapter number is specified, check word count
                if ($existingQuestions->isEmpty() && $data->chapterNumber) {
                    $chapter = Chapter::where('project_id', $project->id)
                        ->where('chapter_number', $data->chapterNumber)
                        ->first();

                    if ($chapter && ! $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                        $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);

                        return response()->json([
                            'questions' => [],
                            'source' => 'insufficient_content',
                            'message' => "Chapter {$data->chapterNumber} needs at least ".ChapterContentAnalysisService::MIN_WORD_COUNT_FOR_DEFENSE." words to generate defense questions. Current word count: {$wordCount}",
                            'word_count' => $wordCount,
                            'minimum_required' => ChapterContentAnalysisService::MIN_WORD_COUNT_FOR_DEFENSE,
                            'next_refresh' => null,
                        ]);
                    }
                }
            }
        }

        // Mark questions as viewed
        $existingQuestions->each->markAsViewed();

        // Cache for 6 hours
        Cache::put($cacheKey, $existingQuestions, now()->addHours(6));

        $response = [
            'questions' => $existingQuestions,
            'source' => 'database',
            'total_available' => DefenseQuestion::where('project_id', $project->id)->active()->count(),
            'next_refresh' => now()->addHours(6),
        ];

        // Debug: Log the response
        Log::info('Defense questions API response', [
            'questions_count' => $existingQuestions->count(),
            'questions_sample' => $existingQuestions->take(2)->toArray(),
            'response_structure' => array_keys($response),
        ]);

        return response()->json($response);
    }

    /**
     * Generate new defense questions (synchronous)
     */
    public function generateQuestions(GenerateDefenseQuestionsRequest $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $data = GenerateDefenseQuestionsData::fromArray($request->validated());

        try {
            $questions = $this->generateQuestionsSynchronously($project, $data->chapterNumber, $data->count);

            // Clear cache to ensure fresh questions are loaded
            $cacheKey = "defense_questions_{$project->id}_{$data->chapterNumber}";
            Cache::forget($cacheKey);

            return response()->json([
                'success' => true,
                'questions' => $questions,
                'count' => $questions->count(),
                'message' => "Generated {$questions->count()} new defense questions",
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate defense questions', [
                'project_id' => $project->id,
                'chapter_number' => $data->chapterNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate defense questions. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Stream generate new defense questions
     */
    public function streamGenerate(StreamGenerateDefenseQuestionsRequest $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        // Rest of the streaming logic...
        $data = StreamDefenseQuestionsData::fromArray($request->validated());

        // Your existing streaming logic here...
        return response()->stream(function () {
            // Your streaming implementation
        });
    }

    /**
     * Mark a question as helpful
     */
    public function markHelpful(MarkDefenseQuestionHelpfulRequest $request, $project_id, $question_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $question = DefenseQuestion::findOrFail($question_id);
        abort_if($question->project_id !== $project->id, 404);

        $validated = $request->validated();

        $question->markAsHelpful($validated['user_marked_helpful']);

        return response()->json([
            'success' => true,
            'question_id' => $question->id,
            'user_marked_helpful' => $validated['user_marked_helpful'],
        ]);
    }

    /**
     * Hide/deactivate a question
     */
    public function hideQuestion($project_id, $question_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $question = DefenseQuestion::findOrFail($question_id);
        abort_if($question->project_id !== $project->id, 404);

        $question->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }

    /**
     * Start a defense simulation session (text mode)
     */
    public function startSession(StartDefenseSessionRequest $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validated();

        if (! $this->defenseCredit->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for defense simulation.',
            ], 402);
        }

        $session = $this->defenseSimulation->startSession(
            $project,
            StartDefenseSessionData::fromArray($validated)
        );

        return response()->json([
            'success' => true,
            'session' => new DefenseSessionResource($session->fresh()),
        ]);
    }

    /**
     * Get session details
     */
    public function getSession(Request $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'session' => new DefenseSessionResource($session->load('messages', 'feedback')),
        ]);
    }

    /**
     * List defense sessions for a project
     */
    public function listSessions(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $sessions = DefenseSession::where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $overallReadiness = DefenseSession::where('project_id', $project->id)
            ->whereNotNull('readiness_score')
            ->avg('readiness_score');

        return response()->json([
            'success' => true,
            'sessions' => DefenseSessionResource::collection($sessions),
            'overall_readiness_score' => $overallReadiness !== null ? (int) round($overallReadiness) : null,
        ]);
    }

    /**
     * Get active defense session for a project (in_progress)
     */
    public function getActiveSession(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('status', 'in_progress')
            ->orderByDesc('started_at')
            ->first();

        return response()->json([
            'success' => true,
            'session' => $session ? new DefenseSessionResource($session) : null,
        ]);
    }

    /**
     * Fetch next panelist question
     */
    public function getNextQuestion(NextDefenseQuestionRequest $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        $validated = $request->validated();

        try {
            $result = $this->defenseSimulation->generatePanelistQuestion(
                $session,
                NextDefenseQuestionData::fromArray($validated)
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'Insufficient credit balance') ? 402 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => new DefenseMessageResource($result->message),
            'session' => new DefenseSessionResource($result->session),
        ]);
    }

    /**
     * Submit a student response
     */
    public function submitResponse(SubmitDefenseResponseRequest $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        $validated = $request->validated();

        try {
        $result = $this->defenseSimulation->processStudentResponse(
            $session,
            SubmitDefenseResponseData::fromArray($validated)
        );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'Insufficient credit balance') ? 402 : 500;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => new DefenseMessageResource($result->message),
            'evaluation' => $result->evaluation,
            'performance_metrics' => $result->metrics,
        ]);
    }

    /**
     * End a defense session
     */
    public function endSession(Request $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        Log::info('Defense endSession requested', [
            'session_id' => $session->id,
            'project_id' => $project->id,
            'status' => $session->status,
            'questions_asked' => $session->questions_asked,
            'started_at' => $session->started_at,
        ]);

        if ($session->status === 'completed') {
            $feedback = DefenseFeedback::where('session_id', $session->id)->first();

            Log::info('Defense endSession already completed', [
                'session_id' => $session->id,
                'feedback_exists' => (bool) $feedback,
            ]);

            return response()->json([
                'success' => true,
                'session' => new DefenseSessionResource($session->fresh()),
                'feedback' => $feedback ? new DefenseFeedbackResource($feedback) : null,
            ]);
        }

        try {
            $feedback = $this->defenseSimulation->endSession($session);
        } catch (\RuntimeException $e) {
            Log::warning('Defense endSession runtime exception', [
                'session_id' => $session->id,
                'message' => $e->getMessage(),
                'status_before' => $session->status,
            ]);
            if ($session->status !== 'completed') {
                $durationSeconds = $session->started_at
                    ? (int) max(0, abs(now()->diffInSeconds($session->started_at, false)))
                    : $session->session_duration_seconds;
                $session->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'session_duration_seconds' => $durationSeconds,
                ]);
            }
            $feedback = DefenseFeedback::where('session_id', $session->id)->first();

            Log::info('Defense endSession forced completion', [
                'session_id' => $session->id,
                'status_after' => $session->fresh()->status,
                'feedback_exists' => (bool) $feedback,
            ]);

            return response()->json([
                'success' => true,
                'session' => new DefenseSessionResource($session->fresh()),
                'feedback' => $feedback ? new DefenseFeedbackResource($feedback) : null,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $status = str_contains($e->getMessage(), 'Insufficient credit balance') ? 402 : 500;

            Log::error('Defense endSession failed', [
                'session_id' => $session->id,
                'message' => $e->getMessage(),
                'status_code' => $status,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'session' => new DefenseSessionResource($session->fresh()),
            'feedback' => $feedback ? new DefenseFeedbackResource($feedback) : null,
        ]);
    }

    /**
     * Abandon a session (manual stop without feedback)
     */
    public function abandonSession(Request $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        $session->update([
            'status' => 'abandoned',
            'completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'session' => new DefenseSessionResource($session->fresh()),
        ]);
    }

    /**
     * Get feedback for a session
     */
    public function getFeedback(Request $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        $feedback = DefenseFeedback::where('session_id', $session->id)->first();

        return response()->json([
            'success' => true,
            'feedback' => $feedback ? new DefenseFeedbackResource($feedback) : null,
        ]);
    }

    /**
     * Get session transcript
     */
    public function getTranscript(Request $request, $project_id, $session_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $session = DefenseSession::where('project_id', $project->id)
            ->where('id', $session_id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'messages' => DefenseMessageResource::collection($session->messages()->orderBy('created_at')->get()),
        ]);
    }

    /**
     * Defense executive briefing (AI-generated)
     */
    public function getExecutiveBriefing(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $preparation = DefensePreparation::firstOrCreate([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
        ]);

        $forceRefresh = (bool) $request->boolean('force_refresh');

        if ($preparation->executive_briefing && ! $forceRefresh) {
            return response()->json([
                'success' => true,
                'briefing' => $preparation->executive_briefing,
                'cached' => true,
            ]);
        }

        if (! $this->defenseCredit->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for defense briefing.',
            ], 402);
        }

        $prompt = $this->promptBuilder->buildExecutiveBriefingPrompt($project);
        $briefing = $this->aiGenerator->generate($prompt, [
            'feature' => 'defense_briefing',
            'model' => 'gpt-4o',
            'temperature' => 0.3,
            'user_id' => $request->user()?->id,
        ]);

        $this->defenseCredit->deductForTextExchange(
            $request->user(),
            null,
            $briefing,
            'Defense executive briefing'
        );

        $preparation->update([
            'executive_briefing' => $briefing,
        ]);

        return response()->json([
            'success' => true,
            'briefing' => $briefing,
        ]);
    }

    /**
     * Analyze opening statement
     */
    public function analyzeOpeningStatement(AnalyzeOpeningStatementRequest $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $preparation = DefensePreparation::firstOrCreate([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
        ]);

        if (! $this->defenseCredit->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for opening statement analysis.',
            ], 402);
        }

        $validated = $request->validated();

        $prompt = $this->promptBuilder->buildOpeningStatementPrompt($project, $validated['opening_statement']);
        $analysis = $this->aiGenerator->generate($prompt, [
            'feature' => 'defense_opening_analysis',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'user_id' => $request->user()?->id,
        ]);

        $this->defenseCredit->deductForTextExchange(
            $request->user(),
            null,
            $analysis,
            'Opening statement analysis'
        );

        $preparation->update([
            'opening_statement' => $validated['opening_statement'],
            'opening_analysis' => $analysis,
        ]);

        return response()->json([
            'success' => true,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Generate opening statement
     */
    public function getOpeningStatement(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $preparation = DefensePreparation::firstOrCreate([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
        ]);

        $forceRefresh = (bool) $request->boolean('force_refresh');

        if ($preparation->opening_statement && ! $forceRefresh) {
            return response()->json([
                'success' => true,
                'opening_statement' => $preparation->opening_statement,
                'cached' => true,
            ]);
        }

        if (! $this->defenseCredit->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for opening statement generation.',
            ], 402);
        }

        $prompt = $this->promptBuilder->buildOpeningStatementGenerationPrompt($project);
        $statement = $this->aiGenerator->generate($prompt, [
            'feature' => 'defense_opening_statement',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.35,
            'user_id' => $request->user()?->id,
        ]);

        $this->defenseCredit->deductForTextExchange(
            $request->user(),
            null,
            $statement,
            'Opening statement generation'
        );

        $preparation->update([
            'opening_statement' => $statement,
        ]);

        return response()->json([
            'success' => true,
            'opening_statement' => $statement,
        ]);
    }

    /**
     * Presentation guide
     */
    public function getPresentationGuide(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $preparation = DefensePreparation::firstOrCreate([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
        ]);

        $forceRefresh = (bool) $request->boolean('force_refresh');

        if ($preparation->presentation_guide && ! $forceRefresh) {
            return response()->json([
                'success' => true,
                'guide' => $preparation->presentation_guide,
                'cached' => true,
            ]);
        }

        if (! $this->defenseCredit->hasEnoughCredits($request->user(), 'text')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit balance for presentation guide.',
            ], 402);
        }

        $prompt = $this->promptBuilder->buildPresentationGuidePrompt($project);
        $guide = $this->aiGenerator->generate($prompt, [
            'feature' => 'defense_presentation_guide',
            'model' => 'gpt-4o-mini',
            'temperature' => 0.3,
            'user_id' => $request->user()?->id,
        ]);

        $this->defenseCredit->deductForTextExchange(
            $request->user(),
            null,
            $guide,
            'Defense presentation guide'
        );

        $preparation->update([
            'presentation_guide' => $guide,
        ]);

        return response()->json([
            'success' => true,
            'guide' => $guide,
        ]);
    }

    /**
     * Get saved defense preparation content
     */
    public function getPreparation(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $preparation = DefensePreparation::where('project_id', $project->id)
            ->where('user_id', $project->user_id)
            ->first();

        return response()->json([
            'success' => true,
            'preparation' => $preparation,
        ]);
    }

    /**
     * Estimate defense cost
     */
    public function estimateCost(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        return response()->json([
            'success' => true,
            'estimates' => $this->defenseCredit->estimateSessionCost('text', 30),
            'minimum_balance' => config('pricing.minimum_balance.defense', 500),
        ]);
    }

    /**
     * Parse questions from streamed buffer
     */
    // In DefenseController.php

    private function parseQuestionsFromBuffer(&$buffer)
    {
        $complete = [];

        // More flexible pattern that handles variations in AI output including asterisks and numbers
        $pattern = '/\*{0,2}\s*QUESTION\s*\d*\*{0,2}:\s*\*{0,2}\s*(.*?)(?:\n|$).*?\*{0,2}\s*ANSWER\*{0,2}:\s*\*{0,2}\s*(.*?)(?:\n|$).*?\*{0,2}\s*KEY_POINTS\*{0,2}:\s*(.*?)(?:\n|$).*?\*{0,2}\s*DIFFICULTY\*{0,2}:\s*\*{0,2}\s*(.*?)(?:\n|$).*?\*{0,2}\s*CATEGORY\*{0,2}:\s*\*{0,2}\s*(.*?)(?:\n---|\z)/si';

        if (preg_match_all($pattern, $buffer, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Parse key points more flexibly
                $keyPointsText = trim($match[3] ?? '');
                $keyPoints = [];

                if (! empty($keyPointsText)) {
                    // Handle different bullet formats
                    $keyPoints = preg_split('/[•●▪︎\-\*]\s*/', $keyPointsText);
                    $keyPoints = array_filter(array_map('trim', $keyPoints));
                    $keyPoints = array_values($keyPoints); // Re-index array
                }

                // Clean up the extracted values
                $question = trim(preg_replace('/^\*+\s*/', '', $match[1] ?? 'Defense question'));
                $answer = trim(preg_replace('/^\*+\s*/', '', $match[2] ?? 'Prepare a comprehensive answer addressing the question.'));
                $difficulty = strtolower(trim(preg_replace('/^\*+\s*/', '', $match[4] ?? 'medium')));
                $category = strtolower(trim(preg_replace('/^\*+\s*/', '', $match[5] ?? 'general')));

                // Ensure we have at least empty arrays for required fields
                $complete[] = [
                    'question' => $question,
                    'answer' => $answer,
                    'key_points' => ! empty($keyPoints) ? $keyPoints : ['Review your research', 'Prepare clear explanations'],
                    'difficulty' => $difficulty,
                    'category' => $category,
                ];
            }

            // Remove parsed questions from buffer
            if (! empty($matches)) {
                $lastMatch = end($matches);
                $lastPos = strpos($buffer, $lastMatch[0]);
                if ($lastPos !== false) {
                    $buffer = substr($buffer, $lastPos + strlen($lastMatch[0]));
                }
            }
        }

        return [
            'complete' => $complete,
            'remaining' => $buffer,
        ];
    }

    /**
     * Generate questions synchronously (for first load)
     */
    // app/Http/Controllers/DefenseController.php

    private function generateQuestionsSynchronously($project, $chapterNumber, $count)
    {
        try {
            // Get chapter content if available
            $chapterContent = null;
            $chapter = null;
            if ($chapterNumber) {
                $chapter = Chapter::where('project_id', $project->id)
                    ->where('chapter_number', $chapterNumber)
                    ->first();
                $chapterContent = $chapter ? $chapter->content : null;

                // Check if chapter has sufficient content for defense questions
                if ($chapter && ! $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                    $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);
                    Log::warning('Chapter does not meet minimum word count for defense questions', [
                        'project_id' => $project->id,
                        'chapter_number' => $chapterNumber,
                        'word_count' => $wordCount,
                        'minimum_required' => ChapterContentAnalysisService::MIN_WORD_COUNT_FOR_DEFENSE,
                    ]);

                    // Return empty collection with informative message
                    return collect();
                }
            }

            $prompt = $this->promptBuilder->buildDefenseQuestionsPrompt($project, $chapterContent, 'general', $count);

            // Check if AI generator is available
            if (! $this->aiGenerator) {
                Log::error('AI Generator not initialized');

                return collect();
            }

            $content = $this->aiGenerator->generate($prompt);

            // Debug: Log the raw AI response
            Log::info('Raw AI response for defense questions', [
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 500),
                'content_full' => $content,
            ]);

            $parsed = $this->parseQuestionsFromBuffer($content);

            // Debug: Log parsed results
            Log::info('Parsed defense questions', [
                'complete_count' => count($parsed['complete']),
                'complete_questions' => $parsed['complete'],
                'remaining_buffer' => substr($parsed['remaining'], 0, 200),
            ]);

            $questions = collect();
            foreach ($parsed['complete'] as $questionData) {
                $question = DefenseQuestion::create([
                    'user_id' => $project->user_id,
                    'project_id' => $project->id,
                    'chapter_number' => $chapterNumber,
                    'question' => $questionData['question'],
                    'suggested_answer' => $questionData['answer'],
                    'key_points' => $questionData['key_points'] ?? [],
                    'difficulty' => $questionData['difficulty'] ?? 'medium',
                    'category' => $questionData['category'] ?? 'general',
                    'ai_model' => method_exists($this->aiGenerator, 'getActiveProvider')
                        ? $this->aiGenerator->getActiveProvider()->getName()
                        : 'unknown',
                    'generation_batch' => $this->getNextBatch($project->id),
                ]);
                $questions->push($question);
            }

            return $questions;
        } catch (\Exception $e) {
            Log::error('Synchronous question generation failed', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return mock questions as fallback for testing
            return $this->createMockQuestions($project, $chapterNumber, $count);
        }
    }

    // Add mock questions method for testing
    private function createMockQuestions($project, $chapterNumber, $count)
    {
        $questions = collect();

        $mockData = [
            [
                'question' => 'How does your methodology address the research objectives outlined in Chapter 1?',
                'answer' => 'Explain the alignment between methodology and objectives, highlighting specific methods chosen for each objective.',
                'key_points' => [
                    'Link each method to specific objectives',
                    'Justify methodological choices',
                    'Address any limitations',
                ],
                'difficulty' => 'medium',
                'category' => 'methodology',
            ],
            [
                'question' => 'What are the key contributions of your research to the existing body of knowledge?',
                'answer' => 'Identify unique contributions, theoretical advancements, and practical implications of your findings.',
                'key_points' => [
                    'Highlight novel findings',
                    'Compare with existing literature',
                    'Discuss practical applications',
                ],
                'difficulty' => 'hard',
                'category' => 'contribution',
            ],
            [
                'question' => 'How did you ensure the validity and reliability of your data collection methods?',
                'answer' => 'Discuss validation techniques, pilot testing, and reliability measures implemented in your research.',
                'key_points' => [
                    'Explain validation procedures',
                    'Discuss pilot study results',
                    'Address potential biases',
                ],
                'difficulty' => 'medium',
                'category' => 'methodology',
            ],
        ];

        foreach (array_slice($mockData, 0, $count) as $data) {
            $question = DefenseQuestion::create([
                'user_id' => $project->user_id,
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
                'question' => $data['question'],
                'suggested_answer' => $data['answer'],
                'key_points' => $data['key_points'],
                'difficulty' => $data['difficulty'],
                'category' => $data['category'],
                'ai_model' => 'mock',
                'generation_batch' => $this->getNextBatch($project->id),
            ]);
            $questions->push($question);
        }

        return $questions;
    }

    /**
     * Queue background generation
     */
    private function generateQuestionsInBackground($project, $chapterNumber)
    {
        // Dispatch job to generate more questions
        // This would be a queued job in production
        dispatch(function () use ($project, $chapterNumber) {
            $this->generateQuestionsSynchronously($project, $chapterNumber, 10);
        })->afterResponse();
    }

    /**
     * Get next batch number
     */
    private function getNextBatch($projectId)
    {
        $lastBatch = DefenseQuestion::where('project_id', $projectId)
            ->max('generation_batch');

        return ($lastBatch ?? 0) + 1;
    }

    /**
     * Send SSE message
     */
    private function sendSSEMessage($data)
    {
        echo 'data: '.json_encode($data)."\n\n";
        ob_flush();
        flush();
    }
}
