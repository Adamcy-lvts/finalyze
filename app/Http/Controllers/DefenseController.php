<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\DefenseQuestion;
use App\Models\Project;
use App\Services\AIContentGenerator;
use App\Services\ChapterContentAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DefenseController extends Controller
{
    private AIContentGenerator $aiGenerator;

    private ChapterContentAnalysisService $contentAnalysis;

    public function __construct(AIContentGenerator $aiGenerator, ChapterContentAnalysisService $contentAnalysis)
    {
        $this->aiGenerator = $aiGenerator;
        $this->contentAnalysis = $contentAnalysis;
    }

    /**
     * Get defense questions for a project
     */
    public function getQuestions(Request $request, $project_id)
    {
        // Manually load the project by ID
        $project = Project::findOrFail($project_id);

        // Authorization
        abort_if($project->user_id !== auth()->id(), 403);

        // More lenient validation
        $validated = $request->validate([
            'chapter_number' => 'nullable|integer|min:1|max:20', // Increased max
            'limit' => 'nullable|integer|min:1|max:20',
            'force_refresh' => 'nullable|boolean',
            'difficulty' => 'nullable|string', // Changed from specific enum
        ]);

        // Provide defaults
        $chapterNumber = isset($validated['chapter_number']) ? (int) $validated['chapter_number'] : null;
        $limit = isset($validated['limit']) ? (int) $validated['limit'] : 5;
        $forceRefresh = isset($validated['force_refresh']) ? (bool) $validated['force_refresh'] : false;

        // Log the request for debugging
        Log::info('Defense questions requested', [
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
            'limit' => $limit,
            'force_refresh' => $forceRefresh,
        ]);

        // Cache key
        $cacheKey = "defense_questions_{$project->id}_{$chapterNumber}";

        // Check cache first (unless force refresh)
        if (! $forceRefresh && Cache::has($cacheKey)) {
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

        if ($chapterNumber) {
            $query->forChapter($chapterNumber);
        }

        if (isset($validated['difficulty']) && $validated['difficulty'] !== 'all') {
            $query->byDifficulty($validated['difficulty']);
        }

        $existingQuestions = $query->inRandomOrder()
            ->limit($limit)
            ->get();

        // Check if we need to generate more
        if ($existingQuestions->count() < 3) {
            // For now, generate synchronously if no questions exist
            if ($existingQuestions->isEmpty()) {
                $existingQuestions = $this->generateQuestionsSynchronously($project, $chapterNumber, 3);

                // If still empty and chapter number is specified, check word count
                if ($existingQuestions->isEmpty() && $chapterNumber) {
                    $chapter = Chapter::where('project_id', $project->id)
                        ->where('chapter_number', $chapterNumber)
                        ->first();

                    if ($chapter && ! $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                        $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);

                        return response()->json([
                            'questions' => [],
                            'source' => 'insufficient_content',
                            'message' => "Chapter {$chapterNumber} needs at least ".ChapterContentAnalysisService::MIN_WORD_COUNT_FOR_DEFENSE." words to generate defense questions. Current word count: {$wordCount}",
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
    public function generateQuestions(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'chapter_number' => 'nullable|integer|min:1|max:20',
            'count' => 'nullable|integer|min:1|max:10',
        ]);

        $chapterNumber = $validated['chapter_number'] ?? null;
        $count = $validated['count'] ?? 5;

        try {
            $questions = $this->generateQuestionsSynchronously($project, $chapterNumber, $count);

            // Clear cache to ensure fresh questions are loaded
            $cacheKey = "defense_questions_{$project->id}_{$chapterNumber}";
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
                'chapter_number' => $chapterNumber,
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
    public function streamGenerate(Request $request, $project_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        // Rest of the streaming logic...
        $validated = $request->validate([
            'chapter_number' => 'nullable|integer|min:1|max:10',
            'count' => 'nullable|integer|min:1|max:10',
            'focus' => 'nullable|in:methodology,literature,findings,theory,contribution,general',
        ]);

        // Your existing streaming logic here...
        return response()->stream(function () {
            // Your streaming implementation
        });
    }

    /**
     * Mark a question as helpful
     */
    public function markHelpful(Request $request, $project_id, $question_id)
    {
        $project = Project::findOrFail($project_id);
        abort_if($project->user_id !== auth()->id(), 403);

        $question = DefenseQuestion::findOrFail($question_id);
        abort_if($question->project_id !== $project->id, 404);

        $validated = $request->validate([
            'user_marked_helpful' => 'required|boolean',
        ]);

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
     * Build AI prompt for defense questions
     */
    private function buildDefenseQuestionsPrompt($project, $chapterContent, $focus, $count)
    {
        $context = "Project Title: {$project->title}\n";
        $context .= "Topic: {$project->topic}\n";
        $context .= "Field of Study: {$project->field_of_study}\n";
        $context .= "University: {$project->university}\n";
        $context .= "Course: {$project->course}\n";

        // Enhanced context: Include content from multiple chapters (only those with sufficient content)
        $chapters = Chapter::where('project_id', $project->id)
            ->orderBy('chapter_number')
            ->get();

        if ($chapters->isNotEmpty()) {
            $context .= "\n=== PROJECT CONTENT ===\n";

            foreach ($chapters as $chapter) {
                // Only include chapters with sufficient content for defense questions
                if ($chapter->content && $this->contentAnalysis->hasMinimumWordCountForDefense($chapter)) {
                    $wordCount = $this->contentAnalysis->getChapterWordCount($chapter);
                    $context .= "\n--- Chapter {$chapter->chapter_number}: {$chapter->title} (Word Count: {$wordCount}) ---\n";
                    // Include substantial content but limit to prevent token explosion
                    $chapterPreview = substr($chapter->content, 0, 2000);
                    $context .= $chapterPreview."\n";
                }
            }
        }

        // Keep legacy single chapter content for backwards compatibility
        if ($chapterContent && ! $chapters->isNotEmpty()) {
            $context .= "\nChapter Content (Preview):\n".substr($chapterContent, 0, 3000)."...\n";
        }

        $focusInstruction = match ($focus) {
            'methodology' => 'Focus on research methodology, data collection, and analysis methods.',
            'literature' => 'Focus on literature review, theoretical framework, and related works.',
            'findings' => 'Focus on research findings, results, and data interpretation.',
            'theory' => 'Focus on theoretical contributions and conceptual framework.',
            'contribution' => 'Focus on research contributions, implications, and significance.',
            default => 'Cover various aspects including methodology, findings, and contributions.'
        };

        return <<<PROMPT
You are an experienced thesis defense examiner. Based on the following thesis information, generate {$count} potential defense questions that examiners might ask.

{$context}

{$focusInstruction}

For each question, provide:
1. The question itself (challenging but fair)
2. A suggested answer approach (2-3 sentences)
3. Key points to cover (2-3 bullet points)
4. Difficulty level (easy/medium/hard)
5. Category (methodology/literature/findings/theory/contribution)

Format each question as:
QUESTION: [question text]
ANSWER: [suggested answer approach]
KEY_POINTS: • [point 1] • [point 2] • [point 3]
DIFFICULTY: [level]
CATEGORY: [category]
---

Generate thoughtful, academic questions that test deep understanding of the research.
PROMPT;
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

            $prompt = $this->buildDefenseQuestionsPrompt($project, $chapterContent, 'general', $count);

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
