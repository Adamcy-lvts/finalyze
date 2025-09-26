<?php

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\Log;

class ChapterReviewService
{
    public function __construct(
        private AIContentGenerator $aiGenerator,
        private ChapterContentAnalysisService $analysisService
    ) {}

    /**
     * Generate chat response based on task type and context
     */
    public function getChatResponse(string $message, Chapter $chapter, string $taskType = 'assist'): string
    {
        $model = $this->getModelForTask($taskType);
        $systemPrompt = $this->buildSystemPrompt($chapter, $taskType);
        $context = $this->buildContext($chapter, $taskType);

        Log::info('Chat request', [
            'chapter_id' => $chapter->id,
            'task_type' => $taskType,
            'model' => $model,
            'message_length' => strlen($message),
            'context_length' => strlen($context),
        ]);

        try {
            return $this->aiGenerator->generate(
                $systemPrompt."\n\n".$context."\n\nUser: ".$message,
                ['model' => $model]
            );
        } catch (\Exception $e) {
            Log::error('Chat response generation failed', [
                'error' => $e->getMessage(),
                'chapter_id' => $chapter->id,
                'task_type' => $taskType,
            ]);

            return $this->getFallbackResponse($taskType);
        }
    }

    /**
     * Stream chat response for real-time experience
     */
    public function streamChatResponse(string $message, Chapter $chapter, string $taskType = 'assist'): \Generator
    {
        $model = $this->getModelForTask($taskType);
        $systemPrompt = $this->buildSystemPrompt($chapter, $taskType);
        $context = $this->buildContext($chapter, $taskType);

        try {
            yield from $this->aiGenerator->streamResponse(
                $systemPrompt."\n\n".$context,
                $message,
                ['model' => $model]
            );
        } catch (\Exception $e) {
            Log::error('Chat streaming failed', [
                'error' => $e->getMessage(),
                'chapter_id' => $chapter->id,
                'task_type' => $taskType,
            ]);

            yield $this->getFallbackResponse($taskType);
        }
    }

    /**
     * Handle quick action prompts
     */
    public function handleQuickAction(string $action, Chapter $chapter, string $taskType = 'review'): string
    {
        $quickActions = config('chat.quick_actions.'.$taskType, []);

        if (! isset($quickActions[$action])) {
            return "I'm not sure how to handle that action. Please try asking me a specific question about the chapter.";
        }

        $prompt = $quickActions[$action];

        return $this->getChatResponse($prompt, $chapter, $taskType);
    }

    /**
     * Generate review questions based on chapter content
     */
    public function generateReviewQuestions(Chapter $chapter, int $count = 5): array
    {
        $prompt = "Based on this chapter content, generate {$count} probing academic questions that would test deep understanding of the material. Include questions about:
        - Key concepts and theories
        - Arguments and evidence
        - Connections to broader field
        - Critical analysis points
        - Practical applications

        Return each question on a new line, numbered.";

        try {
            $response = $this->getChatResponse($prompt, $chapter, 'review');
            $questions = array_filter(explode("\n", $response));

            return array_map('trim', $questions);
        } catch (\Exception $e) {
            Log::error('Review question generation failed', [
                'error' => $e->getMessage(),
                'chapter_id' => $chapter->id,
            ]);

            return [
                'What are the main arguments presented in this chapter?',
                'How does this chapter relate to your overall project topic?',
                'What evidence supports the key claims made?',
                'Are there any weaknesses in the reasoning presented?',
                'How could this chapter be improved?',
            ];
        }
    }

    /**
     * Build system prompt with chapter and project context
     */
    private function buildSystemPrompt(Chapter $chapter, string $taskType): string
    {
        $prompts = config('chat.prompts');
        $template = $prompts[$taskType] ?? $prompts['assist'];

        // Replace placeholders with actual data
        return str_replace([
            '{field_of_study}',
            '{project_type}',
            '{chapter_title}',
            '{target_words}',
            '{word_count}',
            '{project_topic}',
            '{university}',
            '{course}',
        ], [
            $chapter->project->field_of_study ?? 'General Studies',
            ucfirst($chapter->project->type ?? 'undergraduate'),
            $chapter->title ?? 'Chapter '.$chapter->chapter_number,
            $chapter->target_word_count ?? 'Not set',
            $this->analysisService->getChapterWordCount($chapter),
            $chapter->project->topic ?? 'Not set',
            $chapter->project->university ?? 'University',
            $chapter->project->course ?? 'Course',
        ], $template);
    }

    /**
     * Build comprehensive context for AI
     */
    private function buildContext(Chapter $chapter, string $taskType): string
    {
        $context = "CHAPTER CONTENT:\n{$chapter->content}\n\n";

        if ($taskType === 'review') {
            // Add chapter outline if available
            if ($chapter->outline) {
                $context .= "CHAPTER OUTLINE:\n".json_encode($chapter->outline, JSON_PRETTY_PRINT)."\n\n";
            }

            // Use existing ChapterContentAnalysisService for analysis
            $analysis = $this->analysisService->analyzeChapterContent($chapter);

            $context .= "CONTENT ANALYSIS:\n";
            $context .= "- Word Count: {$analysis['word_count']}\n";
            $context .= '- Completion: '.number_format($analysis['completion_percentage'], 1)."%\n";
            $context .= "- Paragraphs: {$analysis['paragraph_count']}\n";
            $context .= "- Sentences: {$analysis['sentence_count']}\n";
            $context .= "- Reading Time: {$analysis['reading_time_minutes']} minutes\n";
            $context .= '- Content Quality Score: '.$this->analysisService->getContentQualityScore($chapter)."/100\n";
            $context .= '- Meets Defense Requirement: '.($analysis['meets_defense_requirement'] ? 'Yes' : 'No')."\n\n";

            // Add citations for review context
            $citations = $chapter->verifiedCitations()->count();
            $unverifiedCitations = $chapter->unverifiedCitations()->count();
            $context .= "CITATIONS:\n";
            $context .= "- Verified: {$citations}\n";
            $context .= "- Unverified: {$unverifiedCitations}\n";
            $context .= '- Total: '.($citations + $unverifiedCitations)."\n\n";
        }

        // Truncate context if too long
        $maxLength = config('chat.settings.max_context_length', 16000);
        if (strlen($context) > $maxLength) {
            $context = substr($context, 0, $maxLength)."\n\n[Content truncated due to length...]";
        }

        return $context;
    }

    /**
     * Get appropriate AI model for task type
     */
    private function getModelForTask(string $taskType): string
    {
        $models = config('chat.models');

        return $models[$taskType] ?? $models['assist'];
    }

    /**
     * Fallback responses when AI service fails
     */
    private function getFallbackResponse(string $taskType): string
    {
        $fallbacks = [
            'review' => "I'm having trouble analyzing your chapter right now. Please try again, or ask me a specific question about a particular section you'd like me to review.",
            'assist' => "I'm experiencing some technical difficulties. Please try rephrasing your question or ask about a specific aspect of your writing that you'd like help with.",
        ];

        return $fallbacks[$taskType] ?? $fallbacks['assist'];
    }

    /**
     * Get a streaming chat response for real-time interaction
     */
    public function getChatResponseStream(
        string $message,
        Chapter $chapter,
        string $taskType = 'assist',
        string $chapterContent = '',
        string $selectedText = '',
        array $conversationHistory = [],
        string $sessionId = ''
    ): \Generator {
        $model = $this->getModelForTask($taskType);
        $systemPrompt = $this->buildSystemPrompt($chapter, $taskType);
        $context = $this->buildContextWithContent($chapter, $taskType, $chapterContent, $selectedText, $sessionId);

        // Build conversation history for personality continuity
        $conversationContext = $this->buildConversationContext($conversationHistory, $taskType);

        // Add personality enhancement for greetings and casual conversation
        $enhancedMessage = $this->enhanceMessageForPersonality($message, $taskType);

        Log::info('Streaming chat request', [
            'chapter_id' => $chapter->id,
            'task_type' => $taskType,
            'model' => $model,
            'message_length' => strlen($message),
            'context_length' => strlen($context),
            'has_chapter_content' => ! empty($chapterContent),
            'has_selected_text' => ! empty($selectedText),
            'conversation_messages' => count($conversationHistory),
        ]);

        try {
            foreach ($this->aiGenerator->streamGenerate(
                $systemPrompt."\n\n".$context."\n\n".$conversationContext."\n\nUser: ".$enhancedMessage,
                [
                    'model' => $model,
                    'temperature' => 0.8, // Slightly higher for more personality
                    'max_tokens' => 2000,
                ]
            ) as $chunk) {
                yield $chunk;
            }
        } catch (\Exception $e) {
            Log::error('Streaming chat response generation failed', [
                'error' => $e->getMessage(),
                'chapter_id' => $chapter->id,
                'task_type' => $taskType,
            ]);

            yield "I apologize, but I'm having trouble processing your request. Please try again.";
        }
    }

    /**
     * Build context with additional chapter content and selected text
     */
    private function buildContextWithContent(Chapter $chapter, string $taskType, string $chapterContent, string $selectedText, string $sessionId = ''): string
    {
        $context = $this->buildContext($chapter, $taskType);

        // Add current chapter content if provided
        if (! empty($chapterContent)) {
            $context .= "\n\nCURRENT CHAPTER CONTENT:\n";
            $context .= $chapterContent."\n";
        }

        // Add selected text if provided
        if (! empty($selectedText)) {
            $context .= "\n\nUSER SELECTED TEXT:\n";
            $context .= '"'.$selectedText.'"'."\n";
            $context .= "The user has specifically selected this text for discussion.\n";
        }

        // Add uploaded files context if available
        if (! empty($sessionId)) {
            $uploadedFiles = \App\Models\ChatFileUpload::where('session_id', $sessionId)
                ->where('project_id', $chapter->project_id)
                ->where('chapter_number', $chapter->chapter_number)
                ->active()
                ->completed()
                ->orderBy('created_at', 'desc')
                ->limit(3) // Limit to 3 most recent files
                ->get();

            if ($uploadedFiles->count() > 0) {
                $context .= "\n\nUPLOADED DOCUMENTS FOR ANALYSIS:\n";
                foreach ($uploadedFiles as $file) {
                    $context .= "\n".$file->getSummaryForChat();

                    // Include a portion of the file content for context
                    $fileContent = substr($file->extracted_text, 0, 2000);
                    if (strlen($file->extracted_text) > 2000) {
                        $fileContent .= '...[content continues]';
                    }
                    $context .= "\nContent Preview: {$fileContent}\n";
                    $context .= "---\n";
                }
                $context .= "You can reference these documents in your responses and analysis.\n";
            }
        }

        // Truncate context if too long
        $maxLength = config('chat.settings.max_context_length', 16000);
        if (strlen($context) > $maxLength) {
            $context = substr($context, 0, $maxLength)."\n\n[Content truncated due to length...]";
        }

        return $context;
    }

    /**
     * Build conversation history context for personality continuity
     */
    private function buildConversationContext(array $conversationHistory, string $taskType): string
    {
        if (empty($conversationHistory)) {
            return '';
        }

        $context = "RECENT CONVERSATION HISTORY:\n";
        $maxMessages = config('chat.settings.max_history_messages', 10);

        // Get the last few messages for context
        $recentMessages = array_slice($conversationHistory, -$maxMessages);

        foreach ($recentMessages as $msg) {
            $role = $msg['type'] === 'user' ? 'Student' : 'You';
            $content = substr($msg['content'], 0, 200); // Truncate for context
            if (strlen($msg['content']) > 200) {
                $content .= '...';
            }
            $context .= "{$role}: {$content}\n";
        }

        $context .= "\nRemember: Reference our previous conversation when relevant. Build on what we've discussed!\n\n";

        return $context;
    }

    /**
     * Enhance message to trigger appropriate personality responses
     */
    private function enhanceMessageForPersonality(string $message, string $taskType): string
    {
        // Disable personality enhancement - return message as-is for professional responses
        return $message;
    }
}
