<?php

namespace App\Http\Controllers;

use App\Enums\ChapterStatus;
use App\Models\Chapter;
use App\Models\ChapterContextAnalysis;
use App\Models\ChapterProgressGuidance;
use App\Models\Project;
use App\Models\UserChapterSuggestion;
use App\Services\AIContentGenerator;
use App\Services\ChatService;
use App\Services\FacultyStructureService;
use App\Services\ProgressiveGuidanceService;
use App\Services\SmartSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ManualEditorController extends Controller
{
    public function __construct(
        private SmartSuggestionService $suggestionService,
        private ChatService $chatService,
        private ProgressiveGuidanceService $guidanceService,
        private AIContentGenerator $aiGenerator,
        private FacultyStructureService $facultyStructureService
    ) {}

    /**
     * Show manual editor interface
     */
    public function show(Request $request, Project $project, int $chapterNumber): Response
    {
        abort_if($project->mode !== 'manual', 403, 'This editor is for manual mode only');

        // Get faculty chapter structure to get proper chapter title
        $facultyChapters = $this->facultyStructureService->getChapterStructure($project);
        $chapterStructure = collect($facultyChapters)->firstWhere('chapter_number', $chapterNumber);

        // Determine chapter title from faculty structure or use fallback
        $chapterTitle = $chapterStructure['title'] ?? "Chapter $chapterNumber";

        // Create chapter if it doesn't exist, or retrieve existing one
        $chapter = Chapter::firstOrCreate(
            [
                'project_id' => $project->id,
                'chapter_number' => $chapterNumber,
            ],
            [
                'title' => $chapterTitle,
                'slug' => \Illuminate\Support\Str::slug($chapterTitle.'-'.\Illuminate\Support\Str::random(6)),
                'content' => null,
                'word_count' => 0,
                'status' => 'not_started',
                'target_word_count' => $chapterStructure['target_word_count'] ?? 2000,
            ]
        );

        Log::info('🎨 MANUAL EDITOR - Show method called', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'project_mode' => $project->mode,
            'chapter_id' => $chapter->id,
            'chapter_number' => $chapter->chapter_number,
            'chapter_title' => $chapterTitle,
            'chapter_was_created' => $chapter->wasRecentlyCreated,
            'user_id' => auth()->id(),
        ]);

        // Get or generate initial suggestion
        $currentSuggestion = UserChapterSuggestion::where('chapter_id', $chapter->id)
            ->where('status', 'pending')
            ->latest('shown_at')
            ->first();

        if (! $currentSuggestion && $chapter->word_count === 0) {
            Log::info('💡 MANUAL EDITOR - Generating initial guidance for empty chapter');
            $currentSuggestion = $this->suggestionService->generateInitialGuidance($chapter);
        }

        $latestProgressGuidance = ChapterProgressGuidance::query()
            ->where('user_id', auth()->id())
            ->where('chapter_id', $chapter->id)
            ->where('meta->algo_version', ProgressiveGuidanceService::ALGO_VERSION)
            ->latest('id')
            ->first();

        $initialProgressGuidance = null;
        if ($latestProgressGuidance) {
            $completed = $latestProgressGuidance->completed_step_ids ?? [];
            $initialProgressGuidance = [
                'guidance_id' => $latestProgressGuidance->id,
                'stage' => $latestProgressGuidance->stage,
                'stage_label' => $latestProgressGuidance->stage_label,
                'completion_percentage' => $latestProgressGuidance->completion_percentage,
                'contextual_tip' => $latestProgressGuidance->contextual_tip,
                'completed_step_ids' => $completed,
                'writing_milestones' => $latestProgressGuidance->writing_milestones,
                'next_steps' => collect($latestProgressGuidance->next_steps ?? [])->map(function ($step) use ($completed) {
                    if (is_array($step) && isset($step['id'])) {
                        $step['completed'] = in_array($step['id'], $completed, true);
                    }
                    return $step;
                })->values()->all(),
            ];
        }

        Log::info('✅ MANUAL EDITOR - Rendering ManualEditor view', [
            'has_suggestion' => $currentSuggestion !== null,
            'has_progress_guidance' => $initialProgressGuidance !== null,
        ]);

        return Inertia::render('projects/ManualEditor', [
            'project' => $project->load([
                'category',
                'universityRelation',
                'facultyRelation',
                'departmentRelation',
                'outlines.sections', // Load outlines for navigation
            ]),
            'chapter' => $chapter,
            'allChapters' => $project->chapters()->orderBy('chapter_number')->get(), // For navigation
            'facultyChapters' => $this->facultyStructureService->getChapterStructure($project), // For navigation context
            'currentSuggestion' => $currentSuggestion,
            'contextAnalysis' => ChapterContextAnalysis::where('chapter_id', $chapter->id)->first(),
            'initialProgressGuidance' => $initialProgressGuidance,
            'chatHistory' => [],
        ]);
    }

    /**
     * Auto-save chapter content
     */
    public function save(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $chapter->update([
            'content' => $validated['content'],
            'word_count' => str_word_count(strip_tags($validated['content'])),
        ]);

        return response()->json([
            'success' => true,
            'chapter' => $chapter,
        ]);
    }

    /**
     * Mark chapter as complete
     */
    public function markComplete(Request $request, Project $project, int $chapterNumber)
    {
        Log::info('🟢 [BACKEND] markComplete method called', [
            'project_id' => $project->id,
            'project_slug' => $project->slug,
            'chapter_number' => $chapterNumber,
            'project_mode' => $project->mode,
        ]);

        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();

        Log::info('🟢 [BACKEND] Chapter found', [
            'chapter_id' => $chapter->id,
            'chapter_title' => $chapter->title,
            'current_status' => $chapter->status,
            'word_count' => $chapter->word_count,
        ]);

        abort_if($project->mode !== 'manual', 403);

        // Validate minimum word count
        $minWordCount = 500;
        if ($chapter->word_count < $minWordCount) {
            Log::warning('🔴 [BACKEND] Word count validation failed', [
                'chapter_id' => $chapter->id,
                'word_count' => $chapter->word_count,
                'min_required' => $minWordCount,
            ]);

            return redirect()->route('projects.manual-editor.show', [
                'project' => $project->slug,
                'chapter' => $chapterNumber,
            ])->with('error', "Chapter must have at least {$minWordCount} words to be marked as complete. Current: {$chapter->word_count} words");
        }

        Log::info('🟢 [BACKEND] Updating chapter status to completed');

        // Update chapter status to completed
        $chapter->update([
            'status' => ChapterStatus::Completed->value,
        ]);

        Log::info('✅ [BACKEND] Chapter marked as complete successfully', [
            'chapter_id' => $chapter->id,
            'new_status' => $chapter->status,
        ]);

        return redirect()->route('projects.manual-editor.show', [
            'project' => $project->slug,
            'chapter' => $chapterNumber,
        ])->with('success', 'Chapter marked as complete successfully!');
    }

    /**
     * Analyze chapter and generate suggestion based on frontend analysis
     */
    public function analyzeAndSuggest(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'analysis' => 'required|array',
            'analysis.word_count' => 'required|integer',
            'analysis.citation_count' => 'required|integer',
            'analysis.table_count' => 'required|integer',
            'analysis.figure_count' => 'required|integer',
            'analysis.claim_count' => 'integer',
            'analysis.has_introduction' => 'boolean',
            'analysis.has_conclusion' => 'boolean',
            'analysis.detected_issues' => 'array',
            'analysis.quality_metrics' => 'array',
        ]);

        $suggestion = $this->suggestionService->generateFromAnalysis(
            $chapter,
            $validated['analysis']
        );

        return response()->json([
            'suggestion' => $suggestion,
            'analysis' => ChapterContextAnalysis::where('chapter_id', $chapter->id)->first(),
        ]);
    }

    /**
     * Save suggestion for later reference
     */
    public function saveSuggestion(
        Request $request,
        Project $project,
        int $chapterNumber,
        UserChapterSuggestion $suggestion
    ) {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);
        abort_if($suggestion->chapter_id !== $chapter->id, 404);

        $suggestion->update([
            'status' => 'saved',
            'actioned_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Clear/dismiss suggestion
     */
    public function clearSuggestion(
        Request $request,
        Project $project,
        int $chapterNumber,
        UserChapterSuggestion $suggestion
    ) {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);
        abort_if($suggestion->chapter_id !== $chapter->id, 404);

        $suggestion->update([
            'status' => 'dismissed',
            'actioned_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark suggestion as applied
     */
    public function applySuggestion(
        Request $request,
        Project $project,
        int $chapterNumber,
        UserChapterSuggestion $suggestion
    ) {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);
        abort_if($suggestion->chapter_id !== $chapter->id, 404);

        $suggestion->update([
            'status' => 'applied',
            'actioned_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Chat with AI assistant
     */
    public function chat(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'history' => 'array',
        ]);

        $response = $this->chatService->sendMessage(
            $chapter,
            $validated['message'],
            $validated['history'] ?? []
        );

        return response()->json($response);
    }

    /**
     * Get progressive guidance based on current content
     */
    public function progressiveGuidance(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'analysis' => 'required|array',
            'analysis.word_count' => 'required|integer',
            'analysis.citation_count' => 'integer',
            'analysis.table_count' => 'integer',
            'analysis.figure_count' => 'integer',
            'analysis.claim_count' => 'integer',
            'analysis.has_introduction' => 'boolean',
            'analysis.has_conclusion' => 'boolean',
            'analysis.detected_issues' => 'array',
            'analysis.quality_metrics' => 'array',
            'analysis.outline' => 'array',
            'analysis.outline.*' => 'string',
            'analysis.content_excerpt' => 'string',
            'analysis.completed_step_ids' => 'array',
            'analysis.completed_step_ids.*' => 'string',
        ]);

        $analysis = $validated['analysis'];

        // Use a stable fingerprint so guidance doesn't regenerate on every small edit.
        $wordCount = (int) ($analysis['word_count'] ?? 0);
        $wordBucket = (int) (floor($wordCount / 100) * 100);
        $outline = array_slice(array_values($analysis['outline'] ?? []), 0, 30);
        $outlineHash = hash('sha1', json_encode($outline, JSON_UNESCAPED_UNICODE));

        $fingerprint = hash('sha256', json_encode([
            'chapter_id' => $chapter->id,
            'algo_version' => ProgressiveGuidanceService::ALGO_VERSION,
            'metrics' => [
                'word_bucket' => $wordBucket,
                'citation_count' => (int) ($analysis['citation_count'] ?? 0),
                'table_count' => (int) ($analysis['table_count'] ?? 0),
                'figure_count' => (int) ($analysis['figure_count'] ?? 0),
                'claim_count' => (int) ($analysis['claim_count'] ?? 0),
                'has_introduction' => (bool) ($analysis['has_introduction'] ?? false),
                'has_conclusion' => (bool) ($analysis['has_conclusion'] ?? false),
            ],
            'outline_hash' => $outlineHash,
        ], JSON_UNESCAPED_UNICODE));

        $incomingCompleted = array_values(array_unique(array_filter(
            $analysis['completed_step_ids'] ?? [],
            fn ($id) => is_string($id) && $id !== ''
        )));

        $existing = ChapterProgressGuidance::query()
            ->where('user_id', auth()->id())
            ->where('chapter_id', $chapter->id)
            ->where('fingerprint', $fingerprint)
            ->where('meta->algo_version', ProgressiveGuidanceService::ALGO_VERSION)
            ->latest('id')
            ->first();

        if ($existing) {
            // If an older cached record contains a raw machine-readable block as text, try to repair it.
            $suspectText = collect($existing->next_steps ?? [])
                ->pluck('text')
                ->filter(fn ($t) => is_string($t) && stripos($t, '<NEXT_STEPS_JSON>') !== false)
                ->first();

            if (is_string($suspectText) && $suspectText !== '') {
                $repaired = $this->guidanceService->parseNextStepsResponse($suspectText);
                if (count($repaired) >= 3) {
                    $existing->update([
                        'next_steps' => $repaired,
                        'completed_step_ids' => [],
                    ]);
                } else {
                    // If repair fails, force a cache miss so we can fall back cleanly.
                    $existing->delete();
                    $existing = null;
                }
            }
        }

        if ($existing) {
            $completed = array_values(array_unique(array_merge($existing->completed_step_ids ?? [], $incomingCompleted)));
            if ($completed !== ($existing->completed_step_ids ?? [])) {
                $existing->update(['completed_step_ids' => $completed]);
            }

            $steps = collect($existing->next_steps)->map(function ($step) use ($completed) {
                if (is_array($step) && isset($step['id'])) {
                    $step['completed'] = in_array($step['id'], $completed, true);
                }
                return $step;
            })->values();

            // Prefer showing only critical steps (to avoid endless guidance churn).
            $critical = $steps->filter(fn ($s) => is_array($s) && (($s['priority'] ?? null) === 'critical'));
            if ($critical->isNotEmpty()) {
                $steps = $critical->values();
            }

            $payload = [
                'guidance_id' => $existing->id,
                'stage' => $existing->stage,
                'stage_label' => $existing->stage_label,
                'completion_percentage' => $existing->completion_percentage,
                'contextual_tip' => $existing->contextual_tip,
                'completed_step_ids' => $completed,
                'writing_milestones' => $existing->writing_milestones,
                'next_steps' => $steps->all(),
            ];

            // If chapter is marked completed, force 100% completion.
            if ($chapter->status === ChapterStatus::Completed->value) {
                $payload['completion_percentage'] = 100;
                $payload['stage'] = 'refinement';
                $payload['stage_label'] = 'Completed';
            }

            return response()->json($payload);
        }

        $guidance = $this->guidanceService->analyzeAndGuide($chapter, $analysis);

        $completed = array_values(array_unique(array_merge(
            $incomingCompleted,
            collect($guidance['next_steps'] ?? [])->filter(fn ($s) => ! empty($s['completed']))->pluck('id')->all(),
        )));

        $guidance['next_steps'] = collect($guidance['next_steps'] ?? [])->map(function ($step) use ($completed) {
            if (is_array($step) && isset($step['id'])) {
                $step['completed'] = in_array($step['id'], $completed, true);
            }
            return $step;
        })->values()->all();

        $stored = ChapterProgressGuidance::create([
            'user_id' => auth()->id(),
            'project_id' => $project->id,
            'chapter_id' => $chapter->id,
            'chapter_number' => $chapterNumber,
            'fingerprint' => $fingerprint,
            'stage' => $guidance['stage'],
            'stage_label' => $guidance['stage_label'],
            'completion_percentage' => (int) ($guidance['completion_percentage'] ?? 0),
            'contextual_tip' => $guidance['contextual_tip'],
            'next_steps' => $guidance['next_steps'],
            'writing_milestones' => $guidance['writing_milestones'],
            'completed_step_ids' => $completed,
            'meta' => [
                'algo_version' => ProgressiveGuidanceService::ALGO_VERSION,
                'cached' => false,
                'source' => 'ai_or_fallback',
            ],
        ]);

        $guidance['guidance_id'] = $stored->id;
        $guidance['completed_step_ids'] = $completed;

        if ($chapter->status === ChapterStatus::Completed->value) {
            $guidance['completion_percentage'] = 100;
            $guidance['stage'] = 'refinement';
            $guidance['stage_label'] = 'Completed';
        }

        return response()->json($guidance);
    }

    /**
     * Persist step completion without re-generating guidance.
     */
    public function updateProgressiveGuidanceSteps(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'completed_step_ids' => 'required|array',
            'completed_step_ids.*' => 'string',
        ]);

        $completed = array_values(array_unique(array_filter(
            $validated['completed_step_ids'],
            fn ($id) => is_string($id) && $id !== ''
        )));

        $latest = ChapterProgressGuidance::query()
            ->where('user_id', auth()->id())
            ->where('chapter_id', $chapter->id)
            ->latest('id')
            ->first();

        if (! $latest) {
            return response()->json(['success' => true, 'stored' => false]);
        }

        $latest->update(['completed_step_ids' => $completed]);

        return response()->json(['success' => true, 'stored' => true]);
    }

    /**
     * Improve selected text with AI
     */
    public function improveText(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
            'context' => 'string|max:500',
        ]);

        $prompt = <<<PROMPT
Improve the following text from an academic project (Chapter {$chapter->chapter_number}: {$chapter->title}).

Original text:
{$validated['text']}

Task: Rewrite this text to be clearer, more academic, and better structured. Maintain the core meaning but improve:
- Clarity and precision
- Academic tone
- Sentence structure
- Word choice

Return ONLY the improved text without any explanation or labels.
PROMPT;

        try {
            $improvedText = $this->aiGenerator->generate($prompt, [
                'temperature' => 0.6,
                'max_tokens' => 800,
            ]);

            return response()->json([
                'improvedText' => trim($improvedText),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to improve text. Please try again.',
            ], 500);
        }
    }

    /**
     * Expand selected text with AI
     */
    public function expandText(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
            'context' => 'string|max:500',
        ]);

        $prompt = <<<PROMPT
Expand the following text from an academic project (Chapter {$chapter->chapter_number}: {$chapter->title}).

Original text:
{$validated['text']}

Task: Elaborate on this text by:
- Adding more detail and explanation
- Providing examples or context
- Developing the ideas further
- Maintaining academic tone

Aim for about 50% more content. Return ONLY the expanded text without any labels.
PROMPT;

        try {
            $expandedText = $this->aiGenerator->generate($prompt, [
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            return response()->json([
                'expandedText' => trim($expandedText),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to expand text. Please try again.',
            ], 500);
        }
    }

    /**
     * Get citation suggestions for selected text
     */
    public function suggestCitations(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $prompt = <<<PROMPT
Analyze the following text from an academic project and suggest what types of citations would be appropriate.

Text:
{$validated['text']}

Project context:
- Title: {$chapter->project->title}
- Field: {$chapter->project->field_of_study}
- Chapter: {$chapter->title}

Task: Identify claims or statements that need citation support and suggest:
1. What type of sources to cite (journals, books, reports, etc.)
2. What specific topics/keywords to search for
3. Brief explanation of why citation is needed

Format as HTML with <ul> and <li> tags. Be specific and actionable.
PROMPT;

        try {
            $suggestions = $this->aiGenerator->generate($prompt, [
                'temperature' => 0.6,
                'max_tokens' => 600,
            ]);

            return response()->json([
                'suggestions' => $suggestions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate citation suggestions. Please try again.',
            ], 500);
        }
    }

    /**
     * Rephrase selected text with AI
     */
    public function rephraseText(Request $request, Project $project, int $chapterNumber)
    {
        $chapter = Chapter::where('project_id', $project->id)
            ->where('chapter_number', $chapterNumber)
            ->firstOrFail();
        abort_if($project->mode !== 'manual', 403);

        $validated = $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $prompt = <<<PROMPT
Rephrase the following text from an academic project in different words while maintaining the same meaning.

Original text:
{$validated['text']}

Task: Provide 2-3 alternative ways to express this same idea. Each alternative should:
- Use different words and sentence structure
- Maintain academic tone
- Keep the same meaning
- Be clearly numbered (1., 2., 3.)

Return the alternatives without labels like "Alternative 1:" - just use numbers.
PROMPT;

        try {
            $alternatives = $this->aiGenerator->generate($prompt, [
                'temperature' => 0.8,
                'max_tokens' => 800,
            ]);

            return response()->json([
                'alternatives' => $alternatives,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to rephrase text. Please try again.',
            ], 500);
        }
    }
}
