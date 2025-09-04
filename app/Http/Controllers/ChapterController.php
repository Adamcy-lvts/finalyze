<?php

namespace App\Http\Controllers;

use App\Models\Chapter;
use App\Models\ChatConversation;
use App\Models\Project;
use App\Services\AIContentGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use OpenAI\Laravel\Facades\OpenAI;

class ChapterController extends Controller
{
    private $aiGenerator;

    public function __construct(AIContentGenerator $aiGenerator)
    {
        $this->aiGenerator = $aiGenerator;
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
        ]);

        $project = Project::with(['chapters', 'category'])->findOrFail($validated['project_id']);

        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        // Ensure project topic is approved before chapter generation
        abort_if($project->status !== 'topic_approved', 400, 'Project topic must be approved first');

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

        $validated = $request->validate([
            'generation_type' => 'required|in:single,progressive',
        ]);

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
        $prompt = $validated['generation_type'] === 'progressive'
            ? $this->buildProgressivePrompt($project, $chapterNumber)
            : $this->buildSinglePrompt($project, $chapterNumber);

        // Return streaming response
        return response()->stream(function () use ($chapter, $prompt, $chapterNumber) {
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

            // Send initial ping
            $this->sendSSEMessage(['type' => 'start', 'message' => 'Initializing AI generation...']);

            try {
                // Use the AI service to generate content
                $fullContent = '';
                $wordCount = 0;

                // Log the start of AI generation
                Log::info('AI Generation - Starting stream request', [
                    'chapter_id' => $chapter->id,
                    'chapter_number' => $chapterNumber,
                    'prompt_length' => strlen($prompt),
                ]);

                // Use optimized generation based on chapter type
                $chapterType = $this->getChapterType($chapterNumber);

                foreach ($this->aiGenerator->generateOptimized($prompt, $chapterType) as $chunk) {
                    $fullContent .= $chunk;
                    $wordCount = str_word_count($fullContent);

                    // Send content update
                    $this->sendSSEMessage([
                        'type' => 'content',
                        'content' => $chunk,
                        'word_count' => $wordCount,
                    ]);

                    // Flush every 100 words to avoid timeout
                    if ($wordCount % 100 === 0) {
                        $this->sendSSEMessage(['type' => 'heartbeat']);
                    }
                }

                // Save the generated content
                $chapter->update([
                    'content' => $fullContent,
                    'word_count' => $wordCount,
                    'status' => 'draft',
                    'ai_generated' => true,
                    'last_ai_generation' => now(),
                ]);

                // Send completion message
                $this->sendSSEMessage([
                    'type' => 'complete',
                    'message' => 'Generation complete!',
                    'final_word_count' => $wordCount,
                ]);

            } catch (\Exception $e) {
                Log::error('AI Generation failed', [
                    'error' => $e->getMessage(),
                    'chapter_id' => $chapter->id,
                ]);

                $this->sendSSEMessage([
                    'type' => 'error',
                    'message' => 'Generation failed. Please try again.',
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

    private function sendSSEMessage($data)
    {
        echo 'data: '.json_encode($data)."\n\n";

        // Only flush if there's actually a buffer to flush
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * CHAPTER EDITING - MANUAL MODE
     * For users who want to write manually with full-featured editor
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

        return Inertia::render('projects/ChapterEditor', [
            'project' => $project,
            'chapter' => $chapter,
            'allChapters' => $project->chapters()->orderBy('chapter_number')->get(),
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
            'word_count' => str_word_count(strip_tags($validated['content'])),
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
     * GENERATE SINGLE CHAPTER WITH AI
     * Creates one chapter at a time for progressive writing
     */
    private function generateSingleChapter(Project $project, int $chapterNumber): Chapter
    {
        $prompt = $this->buildChapterPrompt($project, $chapterNumber);
        $aiContent = $this->callAiService($prompt);

        $chapter = Chapter::updateOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $this->getDefaultChapterTitle($chapterNumber),
            'content' => $aiContent,
            'word_count' => str_word_count(strip_tags($aiContent)),
            'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

        return $chapter;
    }

    /**
     * GENERATE PROGRESSIVE CHAPTER
     * Builds on previous chapters for contextual writing
     */
    private function generateProgressiveChapter(Project $project, int $chapterNumber): Chapter
    {
        // Get previous chapters for context
        $previousChapters = Chapter::where('project_id', $project->id)
            ->where('chapter_number', '<', $chapterNumber)
            ->orderBy('chapter_number')
            ->get();

        $prompt = $this->buildProgressiveChapterPrompt($project, $chapterNumber, $previousChapters);
        $aiContent = $this->callAiService($prompt);

        $chapter = Chapter::updateOrCreate([
            'project_id' => $project->id,
            'chapter_number' => $chapterNumber,
        ], [
            'title' => $this->getDefaultChapterTitle($chapterNumber),
            'content' => $aiContent,
            'word_count' => str_word_count(strip_tags($aiContent)),
            'target_word_count' => $this->getChapterWordCount($project, $chapterNumber),
            'status' => 'draft',
        ]);

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
     * Creates contextual prompts based on project details
     */
    private function buildChapterPrompt(Project $project, int $chapterNumber): string
    {
        $chapterTitle = $this->getDefaultChapterTitle($chapterNumber);

        return "Generate Chapter {$chapterNumber}: {$chapterTitle} for an academic {$project->type} project.

Project Details:
- Title: {$project->title}
- Topic: {$project->topic}
- Field of Study: {$project->field_of_study}
- University: {$project->university}
- Course: {$project->course}
- Academic Level: {$project->type}

Requirements:
- Write in formal academic style
- Include proper citations and references
- Target word count: ".$this->getChapterWordCount($project, $chapterNumber).' words
- Use clear headings and subheadings
- Ensure content is original and well-researched

Focus on making this chapter comprehensive and academically rigorous.';
    }

    /**
     * BUILD PROGRESSIVE CHAPTER PROMPT
     * Includes context from previous chapters
     */
    private function buildProgressiveChapterPrompt(Project $project, int $chapterNumber, $previousChapters): string
    {
        $basePrompt = $this->buildChapterPrompt($project, $chapterNumber);

        if ($previousChapters->count() > 0) {
            $context = "\n\nPrevious Chapters Context:\n";
            foreach ($previousChapters as $prev) {
                $context .= "Chapter {$prev->chapter_number}: {$prev->title}\n";
                $context .= 'Summary: '.substr(strip_tags($prev->content), 0, 500)."...\n\n";
            }
            $basePrompt .= $context;
            $basePrompt .= 'Ensure this chapter builds logically on the previous content.';
        }

        return $basePrompt;
    }

    /**
     * CALL AI SERVICE FOR CONTENT GENERATION
     * Mock implementation - replace with actual AI API
     */
    private function callAiService(string $prompt): string
    {
        // Mock AI response for development
        // Replace this with actual OpenAI, Claude, or other AI service integration
        return "<h2>AI Generated Content</h2>
        <p>This is a mock AI-generated chapter content. In production, this would call your preferred AI service API with the following prompt:</p>
        <blockquote>{$prompt}</blockquote>
        <p>The AI would generate comprehensive, academic-quality content based on the project topic and requirements.</p>";
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
    private function getChapterWordCount(Project $project, int $chapterNumber): int
    {
        $totalWords = $project->category?->target_word_count ?? 10000;
        $chapterCount = $this->getChapterCount($project);

        // Chapter 1 and conclusion typically shorter
        if ($chapterNumber === 1 || $chapterNumber === $chapterCount) {
            return intval($totalWords * 0.15);
        }

        // Other chapters share remaining word count
        return intval(($totalWords * 0.7) / ($chapterCount - 2));
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
        $context .= "- University: {$project->university}\n";
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
        $context .= "- Write in formal academic style with proper citations\n";
        $context .= "- Target approximately {$targetWordCount} words\n";
        $context .= "- Use clear headings and subheadings\n";
        $context .= "- Ensure content flows logically from any previous chapters\n";
        $context .= "- Write comprehensive, well-researched content\n";
        $context .= "- Include relevant examples and case studies where appropriate\n\n";

        $context .= 'Please write the complete chapter content now:';

        return $context;
    }

    private function buildProgressivePrompt($project, $chapterNumber)
    {
        // Get previous chapters for context
        $previousChapters = Chapter::where('project_id', $project->id)
            ->where('chapter_number', '<', $chapterNumber)
            ->orderBy('chapter_number')
            ->get();

        $prompt = "You are writing Chapter {$chapterNumber} of an academic thesis.\n\n";
        $prompt .= "Project Topic: {$project->topic}\n";
        $prompt .= "Field of Study: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n\n";

        if ($previousChapters->isNotEmpty()) {
            $prompt .= "Previous Chapters Context:\n";
            foreach ($previousChapters as $prev) {
                // Include summary of previous chapters
                $summary = $this->summarizeChapter($prev->content);
                $prompt .= "Chapter {$prev->chapter_number} ({$prev->title}): {$summary}\n\n";
            }
            $prompt .= "Build upon the previous chapters and maintain consistency.\n\n";
        }

        $prompt .= $this->getChapterSpecificInstructions($chapterNumber);

        return $prompt;
    }

    private function buildSinglePrompt($project, $chapterNumber)
    {
        $prompt = "You are writing Chapter {$chapterNumber} of an academic thesis.\n\n";
        $prompt .= "Project Topic: {$project->topic}\n";
        $prompt .= "Field of Study: {$project->field_of_study}\n";
        $prompt .= "Academic Level: {$project->type}\n";
        $prompt .= "University: {$project->university}\n";
        $prompt .= "Course: {$project->course}\n\n";

        $prompt .= $this->getChapterSpecificInstructions($chapterNumber);

        return $prompt;
    }

    private function getChapterSpecificInstructions($chapterNumber)
    {
        $instructions = [
            1 => "Write a comprehensive introduction that includes:\n".
                 "- Background of the study\n".
                 "- Problem statement\n".
                 "- Research objectives\n".
                 "- Research questions\n".
                 "- Significance of the study\n".
                 "- Scope and limitations\n".
                 'Target: 2000-2500 words',

            2 => "Write a thorough literature review that includes:\n".
                 "- Theoretical framework\n".
                 "- Review of related studies\n".
                 "- Research gaps\n".
                 "- Conceptual framework\n".
                 'Target: 3000-4000 words',

            3 => "Write a detailed methodology chapter that includes:\n".
                 "- Research design\n".
                 "- Population and sampling\n".
                 "- Data collection methods\n".
                 "- Data analysis techniques\n".
                 "- Validity and reliability\n".
                 'Target: 2500-3000 words',

            4 => "Write a design and implementation chapter that includes:\n".
                 "- System design\n".
                 "- Implementation details\n".
                 "- Testing procedures\n".
                 "- Development challenges\n".
                 'Target: 3000-3500 words',

            5 => "Write a results and analysis chapter that includes:\n".
                 "- Data presentation\n".
                 "- Statistical analysis\n".
                 "- Discussion of findings\n".
                 "- Interpretation of results\n".
                 'Target: 3000-4000 words',

            6 => "Write a conclusion and recommendations chapter that includes:\n".
                 "- Summary of findings\n".
                 "- Conclusions drawn\n".
                 "- Recommendations\n".
                 "- Future work\n".
                 "- Final remarks\n".
                 'Target: 2000-2500 words',
        ];

        return $instructions[$chapterNumber] ?? "Write a comprehensive chapter with proper academic structure.\nTarget: 2500-3000 words";
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
     * GET CHAT HISTORY
     * Retrieve chat conversation history for a chapter
     */
    public function getChatHistory(Project $project, int $chapterNumber)
    {
        abort_if($project->user_id !== auth()->id(), 403);

        $messages = ChatConversation::query()
            ->forChapter($project->id, $chapterNumber)
            ->where('user_id', auth()->id())
            ->orderBy('created_at')
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

        // Get the latest session ID for continuation
        $latestSession = ChatConversation::query()
            ->forChapter($project->id, $chapterNumber)
            ->where('user_id', auth()->id())
            ->latest('created_at')
            ->first();

        return response()->json([
            'messages' => $messages,
            'current_session_id' => $latestSession?->session_id,
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
            'message' => 'required|string|max:1000',
            'context' => 'nullable|string|max:50000',
            'selected_text' => 'nullable|string|max:5000',
            'session_id' => 'nullable|string|max:36',
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

            // Build chat context from database history
            $context = $this->buildChatContextFromHistory(
                $project,
                $chapter,
                $sessionId,
                $validated['context'] ?? '',
                $validated['selected_text'] ?? ''
            );

            Log::info('Chat context built', [
                'context_length' => strlen($context),
                'user_message' => $validated['message'],
                'session_id' => $sessionId,
            ]);

            // Get AI response
            Log::info('Starting AI response generation');
            $startTime = microtime(true);
            $aiResponse = $this->getAIChatResponse($context, $validated['message']);
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
                'ai_model' => 'gpt-4o-mini',
                'response_time' => $responseTime,
                'context_data' => [
                    'user_message_id' => $userMessage->id,
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
        $context .= "- University: {$project->university}\n";
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
        $context .= "- University: {$project->university}\n";
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

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'chapter_content' => 'nullable|string|max:100000', // Full chapter content for context
            'selected_text' => 'nullable|string|max:5000',
            'session_id' => 'nullable|string|max:36',
        ]);

        // Generate or use existing session ID
        $sessionId = $validated['session_id'] ?? Str::uuid()->toString();

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
        return response()->stream(function () use ($project, $chapter, $validated, $sessionId) {
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
                $userMessage = ChatConversation::create([
                    'user_id' => auth()->id(),
                    'project_id' => $project->id,
                    'chapter_number' => $chapter->chapter_number,
                    'session_id' => $sessionId,
                    'message_order' => $nextMessageOrder,
                    'message_type' => 'user',
                    'content' => $validated['message'],
                    'context_data' => [
                        'selected_text' => $validated['selected_text'] ?? null,
                        'chapter_content_length' => strlen($validated['chapter_content'] ?? ''),
                        'has_full_chapter_context' => ! empty($validated['chapter_content']),
                    ],
                ]);

                // Send initial ping
                $this->sendSSEMessage([
                    'type' => 'start',
                    'message' => 'AI assistant is thinking...',
                    'session_id' => $sessionId,
                ]);

                // Build comprehensive context with full chapter content
                $context = $this->buildComprehensiveChatContext(
                    $project,
                    $chapter,
                    $sessionId,
                    $validated['chapter_content'] ?? '',
                    $validated['selected_text'] ?? ''
                );

                Log::info('Streaming chat context built', [
                    'context_length' => strlen($context),
                    'has_chapter_content' => ! empty($validated['chapter_content']),
                    'has_selected_text' => ! empty($validated['selected_text']),
                ]);

                // Stream AI response using the content generator
                $fullResponse = '';
                $startTime = microtime(true);

                // Build the AI prompt for contextual conversation
                $prompt = $context."\n\nUser Message: \"{$validated['message']}\"\n\n";
                $prompt .= 'As an AI writing assistant, provide a helpful, specific response. ';
                $prompt .= 'Use the full chapter context and selected text (if any) to give personalized advice. ';
                $prompt .= 'Be encouraging, constructive, and conversational. ';
                $prompt .= 'Suggest specific improvements or highlight strengths in their writing when relevant.';

                // Use the AI generator for streaming
                foreach ($this->aiGenerator->generateOptimized($prompt, 'conversational') as $chunk) {
                    $fullResponse .= $chunk;

                    // Send content update
                    $this->sendSSEMessage([
                        'type' => 'content',
                        'content' => $chunk,
                        'session_id' => $sessionId,
                    ]);

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
                    'ai_model' => 'gpt-4o-mini',
                    'response_time' => $responseTime,
                    'context_data' => [
                        'user_message_id' => $userMessage->id,
                        'streaming_response' => true,
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
                    'chapter_number' => $chapterNumber,
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
        $context .= "- University: {$project->university}\n";
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
}
