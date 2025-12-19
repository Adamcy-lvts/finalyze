<?php

namespace App\Services;

use App\Services\AI\SystemPromptService;
use App\Models\Chapter;
use App\Models\ChapterContextAnalysis;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(
        private AIContentGenerator $aiGenerator,
        private SystemPromptService $systemPromptService
    ) {}

    /**
     * Send chat message with full context awareness
     */
    public function sendMessage(Chapter $chapter, string $message, array $history): array
    {
        $context = $this->buildContext($chapter);
        $userPrompt = $this->buildChatPrompt($message, $history, $context);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPromptService->getChatSystemPrompt()],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        try {
            $response = $this->aiGenerator->generateMessages($messages, [
                'temperature' => 0.8,
                'max_tokens' => 1000,
            ]);

            return [
                'message' => $response,
                'context_used' => $context,
            ];
        } catch (\Exception $e) {
            return [
                'message' => "I'm having trouble connecting to the AI service right now. Please try again in a moment.",
                'context_used' => $context,
                'error' => true,
            ];
        }
    }

    /**
     * Build context for chat
     */
    private function buildContext(Chapter $chapter): array
    {
        $project = $chapter->project;
        $analysis = ChapterContextAnalysis::where('chapter_id', $chapter->id)->first();

        return [
            'chapter_number' => $chapter->chapter_number,
            'chapter_title' => $chapter->title,
            'current_word_count' => $chapter->word_count,
            'target_word_count' => $chapter->target_word_count,
            'project_title' => $project->title,
            'project_type' => $project->projectType,
            'course' => $project->course,
            'field_of_study' => $project->field_of_study,
            'content_summary' => Str::limit(strip_tags($chapter->content ?? ''), 500),
            'detected_issues' => $analysis?->detected_issues ?? [],
            'quality_metrics' => $analysis?->content_quality_metrics ?? [],
            'citation_count' => $analysis?->citation_count ?? 0,
            'table_count' => $analysis?->table_count ?? 0,
        ];
    }

    /**
     * Build chat prompt with context and history
     */
    private function buildChatPrompt(string $message, array $history, array $context): string
    {
        $historyText = $this->formatHistory($history);
        $contextText = $this->formatContext($context);

        return <<<PROMPT
{$contextText}

Previous Conversation:
{$historyText}

Student's Question: {$message}

Task: Provide a helpful, encouraging, and specific response to the student's question.
- Be concise (under 200 words unless asked for more detail)
- Reference the chapter context when relevant
- Suggest actionable next steps
- Maintain a supportive, academic tone

Your Response:
PROMPT;
    }

    /**
     * Format chat history for prompt
     */
    private function formatHistory(array $history): string
    {
        if (empty($history)) {
            return '(This is the start of the conversation)';
        }

        $formatted = [];
        // Limit to last 10 messages to avoid token limits
        $recentHistory = array_slice($history, -10);

        foreach ($recentHistory as $msg) {
            $role = $msg['role'] === 'user' ? 'Student' : 'Assistant';
            $content = $msg['content'] ?? $msg['message'] ?? '';
            $formatted[] = "{$role}: {$content}";
        }

        return implode("\n", $formatted);
    }

    /**
     * Format context for prompt
     */
    private function formatContext(array $context): string
    {
        $issues = ! empty($context['detected_issues'])
            ? implode(', ', $context['detected_issues'])
            : 'none detected';

        return <<<CONTEXT
Chapter Context:
- Chapter {$context['chapter_number']}: {$context['chapter_title']}
- Progress: {$context['current_word_count']} / {$context['target_word_count']} words
- Citations: {$context['citation_count']}, Tables: {$context['table_count']}
- Detected issues: {$issues}

Project Context:
- Title: {$context['project_title']}
- Type: {$context['project_type']}
- Course: {$context['course']}
- Field: {$context['field_of_study']}

Current Content Summary:
{$context['content_summary']}
CONTEXT;
    }
}
