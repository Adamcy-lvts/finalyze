<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ChapterContextAnalysis;
use App\Models\Project;
use App\Services\AI\SystemPromptService;
use App\Services\PromptSystem\PromptRouter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminPromptPreviewController extends Controller
{
    public function __construct(
        private PromptRouter $promptRouter,
        private SystemPromptService $systemPromptService
    ) {}

    public function index()
    {
        $projects = Project::query()
            ->with(['user:id,name,email'])
            ->select(['id', 'slug', 'title', 'topic', 'type', 'course', 'field_of_study', 'user_id', 'updated_at'])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(function (Project $p) {
                return [
                    'id' => $p->id,
                    'slug' => $p->slug,
                    'title' => $p->title,
                    'topic' => $p->topic,
                    'type' => $p->type,
                    'course' => $p->course,
                    'field_of_study' => $p->field_of_study,
                    'user' => [
                        'id' => $p->user?->id,
                        'name' => $p->user?->name,
                        'email' => $p->user?->email,
                    ],
                    'updated_at' => optional($p->updated_at)->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/System/PromptPreview', [
            'projects' => $projects,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:chapter,editor,chat,analysis',
            'project_id' => 'required|integer|exists:projects,id',
            'chapter_number' => 'sometimes|integer|min:1|max:20',
            'chapter_id' => 'sometimes|integer|exists:chapters,id',
            'editor_action' => 'sometimes|string|in:rephrase,expand,improve',
            'style' => 'sometimes|string|max:100',
            'selected_text' => 'sometimes|string|max:5000',
            'chat_message' => 'sometimes|string|max:2000',
            'chat_history' => 'sometimes|array',
            'chat_history.*.role' => 'required_with:chat_history|string|in:user,assistant',
            'chat_history.*.content' => 'required_with:chat_history|string|max:2000',
            'analysis_kind' => 'sometimes|string|in:structure,citations,originality,argument',
        ]);

        $project = Project::query()
            ->with([
                'user:id,name,email',
                'universityRelation:id,name',
            ])
            ->findOrFail((int) $data['project_id']);
        $type = $data['type'];

        $systemPrompt = '';
        $userPrompt = '';
        $meta = [
            'type' => $type,
            'project_id' => $project->id,
            'project_slug' => $project->slug,
        ];

        if ($type === 'chapter') {
            $chapterNumber = (int) ($data['chapter_number'] ?? 1);
            $templateSystem = $this->promptRouter->getSystemPrompt($project);
            $systemPrompt = $this->systemPromptService->getChapterSystemPrompt($project, $templateSystem);
            $userPrompt = $this->promptRouter->buildPrompt($project, $chapterNumber);

            $meta['chapter_number'] = $chapterNumber;
        } elseif ($type === 'editor') {
            $action = (string) ($data['editor_action'] ?? 'rephrase');
            $style = (string) ($data['style'] ?? 'Academic Formal');
            $selectedText = (string) ($data['selected_text'] ?? '[Paste selected text here]');

            $systemPrompt = $this->systemPromptService->getEditorSystemPrompt();
            $userPrompt = $this->buildEditorUserPrompt($project, $action, $selectedText, $style, $data['chapter_id'] ?? null);

            $meta['editor_action'] = $action;
            $meta['style'] = $style;
        } elseif ($type === 'chat') {
            $systemPrompt = $this->systemPromptService->getChatSystemPrompt();

            $chapter = null;
            if (isset($data['chapter_id'])) {
                $chapter = Chapter::query()
                    ->with('project')
                    ->find((int) $data['chapter_id']);
            }

            $chatMessage = (string) ($data['chat_message'] ?? 'How can I improve this section?');
            $chatHistory = $data['chat_history'] ?? [];

            $userPrompt = $this->buildChatUserPrompt($project, $chapter, $chatMessage, $chatHistory);

            $meta['chapter_id'] = $chapter?->id;
        } elseif ($type === 'analysis') {
            $systemPrompt = $this->systemPromptService->getAnalysisSystemPrompt();
            $kind = (string) ($data['analysis_kind'] ?? 'structure');
            $userPrompt = $this->buildAnalysisUserPrompt($project, $kind, $data['chapter_id'] ?? null);
            $meta['analysis_kind'] = $kind;
        }

        return response()->json([
            'success' => true,
            'system_prompt' => $systemPrompt,
            'user_prompt' => $userPrompt,
            'meta' => $meta,
            'estimates' => [
                'system' => $this->estimateTokens($systemPrompt),
                'user' => $this->estimateTokens($userPrompt),
                'total' => $this->estimateTokens($systemPrompt."\n\n".$userPrompt),
            ],
        ]);
    }

    private function estimateTokens(string $text): int
    {
        $words = str_word_count(strip_tags($text));

        return (int) max(0, round($words * 1.3));
    }

    private function buildEditorUserPrompt(Project $project, string $action, string $selectedText, string $style, ?int $chapterId): string
    {
        $chapterTitle = null;
        $chapterContentPreview = null;

        if ($chapterId) {
            $chapter = Chapter::query()->where('project_id', $project->id)->find($chapterId);
            if ($chapter) {
                $chapterTitle = $chapter->title;
                $raw = strip_tags($chapter->content ?? '');
                $chapterContentPreview = strlen($raw) > 500 ? substr($raw, 0, 500).'...' : $raw;
            }
        }

        $baseContext = "PROJECT CONTEXT:\n".
            "- Topic: {$project->topic}\n".
            "- Field: {$project->field_of_study}\n".
            "- Academic Level: {$project->type}\n".
            "- University: {$project->universityRelation?->name}\n".
            "- Course: {$project->course}\n";

        if ($chapterTitle) {
            $baseContext .= "- Chapter: {$chapterTitle}\n";
        }

        if ($action === 'expand') {
            $selectedWordCount = str_word_count($selectedText);
            $targetWordCount = max(50, $selectedWordCount * 2);

            $prompt = "Expand the selected text.\n\n{$baseContext}\n";
            if ($chapterContentPreview) {
                $prompt .= "\nCURRENT CHAPTER CONTEXT (preview):\n{$chapterContentPreview}\n";
            }
            $prompt .= "\nSELECTED TEXT TO EXPAND:\n{$selectedText}\n\n";
            $prompt .= "REQUIREMENTS:\n";
            $prompt .= "- Preserve meaning and structure; keep any headers exactly as-is\n";
            $prompt .= "- Add depth, clarity, and academic rigor\n";
            $prompt .= "- Target about {$targetWordCount} words (≈2× original)\n";
            $prompt .= "- Output ONLY the expanded text\n";

            return $prompt;
        }

        if ($action === 'improve') {
            $prompt = "Improve this chapter content (selective edits only).\n\n{$baseContext}\n";
            $prompt .= "\nCONTENT TO IMPROVE:\n{$selectedText}\n\n";
            $prompt .= "REQUIREMENTS:\n";
            $prompt .= "- Improve clarity, coherence, and academic tone\n";
            $prompt .= "- Preserve good parts; rewrite only what needs improvement\n";
            $prompt .= "- Output ONLY the improved content\n";

            return $prompt;
        }

        // default: rephrase
        $prompt = "Rephrase the selected text.\n\n{$baseContext}\n";
        $prompt .= "\nSTYLE: {$style}\n\n";
        $prompt .= "SELECTED TEXT TO REPHRASE:\n{$selectedText}\n\n";
        $prompt .= "REQUIREMENTS:\n";
        $prompt .= "- Preserve meaning\n";
        $prompt .= "- Improve clarity and flow\n";
        $prompt .= "- Keep similar length (±20%)\n";
        $prompt .= "- Output ONLY the rephrased text\n";

        return $prompt;
    }

    private function buildChatUserPrompt(Project $project, ?Chapter $chapter, string $message, array $history): string
    {
        $chapterNumber = $chapter?->chapter_number;
        $chapterTitle = $chapter?->title;
        $summary = $chapter ? strip_tags($chapter->content ?? '') : '';
        $summary = $summary !== '' ? (strlen($summary) > 500 ? substr($summary, 0, 500).'...' : $summary) : '(No chapter selected)';

        $analysis = $chapter ? ChapterContextAnalysis::where('chapter_id', $chapter->id)->first() : null;
        $issues = $analysis?->detected_issues ?? [];
        $issuesText = ! empty($issues) ? implode(', ', $issues) : 'none detected';

        $historyText = '(This is the start of the conversation)';
        if (! empty($history)) {
            $lines = [];
            $recent = array_slice($history, -10);
            foreach ($recent as $msg) {
                $role = ($msg['role'] ?? 'user') === 'user' ? 'Student' : 'Assistant';
                $content = (string) ($msg['content'] ?? '');
                $lines[] = "{$role}: {$content}";
            }
            $historyText = implode("\n", $lines);
        }

        $chapterLine = $chapterNumber ? "Chapter {$chapterNumber}: {$chapterTitle}" : 'Chapter: (not selected)';

        return <<<PROMPT
Chapter Context:
- {$chapterLine}
- Detected issues: {$issuesText}

Project Context:
- Title: {$project->title}
- Type: {$project->projectType}
- Course: {$project->course}
- Field: {$project->field_of_study}

Current Content Summary:
{$summary}

Previous Conversation:
{$historyText}

Student's Question: {$message}

Task: Provide a helpful, encouraging, and specific response.
- Be concise (under 200 words unless asked for more detail)
- Reference the chapter context when relevant
- Suggest actionable next steps

Your Response:
PROMPT;
    }

    private function buildAnalysisUserPrompt(Project $project, string $kind, ?int $chapterId): string
    {
        $chapter = null;
        if ($chapterId) {
            $chapter = Chapter::query()->where('project_id', $project->id)->find($chapterId);
        }

        $content = $chapter ? strip_tags($chapter->content ?? '') : '';
        $content = $content !== '' ? $content : '[Paste chapter content here]';

        return match ($kind) {
            'citations' => "Analyze the citations quality in this chapter content and respond with the exact JSON format requested by the analysis prompt.\n\nCHAPTER CONTENT:\n{$content}",
            'originality' => "Analyze originality and redundancy in this chapter content and respond with the exact JSON format requested by the analysis prompt.\n\nCHAPTER CONTENT:\n{$content}",
            'argument' => "Analyze argument strength in this chapter content and respond with the exact JSON format requested by the analysis prompt.\n\nCHAPTER CONTENT:\n{$content}",
            default => "Analyze structure and organization in this chapter content and respond with the exact JSON format requested by the analysis prompt.\n\nCHAPTER CONTENT:\n{$content}",
        };
    }
}
