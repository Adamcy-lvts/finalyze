<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\TopicTextHelpers;
use App\Http\Requests\Topics\DeleteTopicSessionRequest;
use App\Http\Requests\Topics\RenameTopicSessionRequest;
use App\Http\Requests\Topics\SaveRefinedTopicRequest;
use App\Http\Requests\Topics\TopicChatRequest;
use App\Models\Project;
use App\Models\ProjectTopic;
use App\Services\AIContentGenerator;
use App\Services\Topics\TopicLibraryService;
use App\Services\WordBalanceService;
use App\Transformers\TopicTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TopicLabController extends Controller
{
    use TopicTextHelpers;

    public function __construct(
        private AIContentGenerator $aiGenerator,
        private TopicLibraryService $topicLibraryService,
    ) {
        //
    }

    /**
     * Topic Lab - Interactive chat interface for topic refinement
     */
    public function lab(Request $request, Project $project)
    {
        // Ensure user owns the project
        abort_if($project->user_id !== auth()->id(), 403);

        $project->load('category');
        $topics = $this->getProjectGeneratedTopics($project);
        $returnToSelection = $request->boolean('return_to_selection');

        // Fetch ALL existing chat sessions for this project (for history sidebar)
        // Include both topic_refinement and null task_type for backward compatibility
        $allSessions = \App\Models\ChatConversation::where('project_id', $project->id)
            ->where('user_id', auth()->id())
            ->whereNull('chapter_number') // Topic Lab chats don't have chapter numbers
            ->select('session_id', 'session_name', 'created_at', 'context_data')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('session_id');

        // Check if session_id is provided in request, otherwise use the most recent session or create new
        $sessionId = $request->input('session_id');
        $forceNew = $request->boolean('new');

        if ($forceNew) {
            // User explicitly wants a new session
            $sessionId = Str::uuid()->toString();
        } elseif (! $sessionId) {
            // No session_id provided - check for existing sessions
            if ($allSessions->isNotEmpty()) {
                // Use the most recent session
                $sessionId = $allSessions->keys()->first();
            } else {
                // No existing sessions - generate a new one
                $sessionId = Str::uuid()->toString();
            }
        }

        $initialMessages = [];
        $initialTopic = null;

        // Load messages for the active session
        $chatMessages = \App\Models\ChatConversation::where('session_id', $sessionId)
            ->orderBy('message_order')
            ->get();

        if ($chatMessages->isNotEmpty()) {
            $initialMessages = $chatMessages->map(function ($msg) {
                return [
                    'id' => (string) $msg->id,
                    'role' => $msg->message_type === 'ai' ? 'assistant' : 'user',
                    'content' => $msg->content,
                ];
            })->toArray();

            // Try to find the topic from the first user message
            $firstUserMsg = $chatMessages->firstWhere('message_type', 'user');
            if ($firstUserMsg && ! empty($firstUserMsg->context_data['topic_title'])) {
                $initialTopic = [
                    'id' => $firstUserMsg->context_data['topic_id'] ?? 0,
                    'title' => $firstUserMsg->context_data['topic_title'],
                    'description' => '',
                    'difficulty' => 'Intermediate',
                ];

                // Try to match with generated topics to get full details
                $matchedTopic = collect($topics)->firstWhere('title', $initialTopic['title']);
                if ($matchedTopic) {
                    $initialTopic = $matchedTopic;
                }
            }
        }

        // Allow TopicSelection to open Topic Lab with a pre-selected topic.
        if ($chatMessages->isEmpty()) {
            $prefillTitle = $request->input('topic_title');
            $prefillDescription = $request->input('topic_description');
            if (is_string($prefillTitle) && trim($prefillTitle) !== '') {
                $initialTopic = [
                    'id' => 0,
                    'title' => trim($prefillTitle),
                    'description' => is_string($prefillDescription) ? $prefillDescription : '',
                    'difficulty' => 'Intermediate',
                    'resource_level' => 'Medium',
                    'feasibility_score' => 75,
                ];
            }
        }

        // Format history sessions for sidebar
        $historySessions = $allSessions
            ->map(function ($messages, $sid) {
                // Get the first message for date/count info
                $firstMsg = $messages->first();
                $contextData = $firstMsg->context_data ?? [];

                // Find session_name from any message in this session (prefer one that has it set)
                $messageWithName = $messages->first(fn ($m) => ! empty($m->session_name));
                $sessionName = $messageWithName?->session_name
                    ?? $contextData['topic_title']
                    ?? 'Untitled Discussion';

                return [
                    'id' => $sid,
                    'title' => $sessionName,
                    'date' => $firstMsg->created_at->diffForHumans(),
                    'timestamp' => $firstMsg->created_at->timestamp,
                    'message_count' => $messages->count(),
                ];
            })
            ->values()
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();

        return Inertia::render('projects/TopicsLab', [
            'project' => [
                'id' => $project->id,
                'slug' => $project->slug,
                'title' => $project->title,
                'topic' => $project->topic,
                'status' => $project->status,
                'type' => $project->type,
                'field_of_study' => $project->field_of_study,
                'course' => $project->course,
                'university' => $project->universityRelation?->name ?? $project->university,
            ],
            'topics' => $topics,
            'sessionId' => $sessionId,
            'historySessions' => $historySessions,
            'initialMessages' => $initialMessages,
            'initialTopic' => $initialTopic,
            'returnToSelection' => $returnToSelection,
        ]);
    }

    /**
     * Chat about a specific topic (Streamed)
     */
    public function chat(TopicChatRequest $request, Project $project)
    {
        $validated = $request->validated();

        // Check if user has enough words (estimate ~500 words for chat response)
        $user = auth()->user();
        $estimatedWords = 500; // Estimated average chat response length

        if (! $user->hasEnoughWords($estimatedWords)) {
            return response()->json([
                'error' => 'Insufficient word balance',
                'message' => "You need at least {$estimatedWords} words in your balance to use the chat. Your current balance is {$user->word_balance} words.",
                'balance' => $user->word_balance,
                'required' => $estimatedWords,
            ], 402); // Payment Required status code
        }

        $messages = $validated['messages'];
        $topic = $validated['topic_context'];
        $sessionId = $validated['session_id'];

        // Get the latest user message
        $lastUserMessage = end($messages);

        // Get or generate session name
        $existingSession = \App\Models\ChatConversation::where('session_id', $sessionId)
            ->whereNotNull('session_name')
            ->first();

        $sessionName = $existingSession?->session_name
            ?? Str::limit(strip_tags($topic['title'] ?? 'New Discussion'), 60);

        // 1. Store User Message
        try {
            $messageData = [
                'user_id' => auth()->id(),
                'project_id' => $project->id,
                'chapter_number' => null, // No chapter yet
                'session_id' => $sessionId,
                'session_name' => $sessionName, // Always set session name
                'message_order' => count($messages) - 1, // approximate order
                'message_type' => 'user',
                'task_type' => 'topic_refinement',
                'content' => $lastUserMessage['content'],
                'context_data' => ['topic_id' => $topic['id'] ?? null, 'topic_title' => $topic['title'] ?? ''],
            ];

            \App\Models\ChatConversation::create($messageData);
        } catch (\Exception $e) {
            Log::error('Failed to log user chat message', ['error' => $e->getMessage()]);
        }

        // Build the system prompt
        $systemPrompt = "You are an expert academic research advisor helping a student refine their research topic.
        
Current Topic Context:
Title: {$topic['title']}
Description: ".strip_tags($topic['description'] ?? '')."
Field of Study: {$project->field_of_study}
Academic Level: {$project->type}

Your goal is to help the student refine this topic, clarify the scope, suggest methodologies, or answer questions about feasibility.
Keep your responses helpful, encouraging, and academically rigorous but accessible.
Avoid lengthy lectures; be conversational and interactive.
If the student asks to change the topic significantly, guide them on how it aligns with their field.

IMPORTANT OUTPUT FORMAT (for automatic extraction):
- If your response includes a refined research topic the student can adopt, you MUST include exactly ONE machine-readable block at the very end of your response:
<REFINED_TOPIC_JSON>{\"title\":\"...\",\"description\":\"...\"}</REFINED_TOPIC_JSON>
- The JSON must be valid.
- \"title\" must be a single line (<= 180 chars).
- \"description\" must be plain text (no markdown), <= 1500 chars.
- Do NOT include any other text after the closing </REFINED_TOPIC_JSON> tag.";

        // Construct the full prompt for the AI
        $fullPrompt = $systemPrompt."\n\nChat History:\n";
        foreach ($messages as $msg) {
            $role = ucfirst($msg['role']);
            $fullPrompt .= "{$role}: {$msg['content']}\n";
        }
        $fullPrompt .= 'Assistant:';

        // Stream the response
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($fullPrompt, $project, $sessionId, $topic, $messages) {
            $aiContentAccumulated = '';
            $startTime = microtime(true);

            try {
                $options = [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                    'feature' => 'topics_lab_chat',
                    'user_id' => auth()->id(),
                ];

                foreach ($this->aiGenerator->streamGenerate($fullPrompt, $options) as $chunk) {
                    $aiContentAccumulated .= $chunk;
                    echo 'data: '.json_encode(['content' => $chunk])."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // 2. Store AI Message (after generation)
                $duration = microtime(true) - $startTime;

                // Count words in AI response for billing
                $wordsUsed = str_word_count(strip_tags($aiContentAccumulated));

                try {
                    \App\Models\ChatConversation::create([
                        'user_id' => auth()->id(),
                        'project_id' => $project->id,
                        'chapter_number' => null,
                        'session_id' => $sessionId,
                        'message_order' => count($messages),
                        'message_type' => 'ai',
                        'task_type' => 'topic_refinement',
                        'content' => $aiContentAccumulated,
                        'context_data' => ['topic_id' => $topic['id'] ?? null],
                        'ai_model' => $options['model'],
                        'tokens_used' => $wordsUsed,
                        'response_time' => $duration,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to log AI chat message', ['error' => $e->getMessage()]);
                }

                // 3. Deduct words from user balance
                if ($wordsUsed > 0) {
                    try {
                        $wordBalanceService = app(WordBalanceService::class);
                        $user = auth()->user();

                        $wordBalanceService->deductForGeneration(
                            $user,
                            $wordsUsed,
                            sprintf('Topic Lab chat: %s', Str::limit(strip_tags($topic['title'] ?? 'Topic Discussion'), 50)),
                            'topic_chat',
                            $project->id,
                            [
                                'session_id' => $sessionId,
                                'topic_id' => $topic['id'] ?? null,
                                'model' => $options['model'],
                                'response_time' => $duration,
                            ]
                        );

                        Log::info('Words deducted for topic chat', [
                            'user_id' => $user->id,
                            'project_id' => $project->id,
                            'words' => $wordsUsed,
                            'new_balance' => $user->word_balance,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to deduct words for topic chat', [
                            'user_id' => auth()->id(),
                            'words' => $wordsUsed,
                            'error' => $e->getMessage(),
                        ]);
                        // Don't fail the request if word deduction fails
                    }
                }

                echo 'data: '.json_encode(['done' => true, 'words_used' => $wordsUsed])."\n\n";
                flush();

            } catch (\Exception $e) {
                echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
                flush();
            }
        }, 200, $headers);
    }

    /**
     * Rename a chat session
     */
    public function renameSession(RenameTopicSessionRequest $request, Project $project)
    {
        $validated = $request->validated();

        // Update all messages in this session with the new name
        $updated = \App\Models\ChatConversation::where('session_id', $validated['session_id'])
            ->where('user_id', auth()->id())
            ->where('project_id', $project->id)
            ->update(['session_name' => $validated['name']]);

        if ($updated > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Session renamed successfully',
                'name' => $validated['name'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Session not found',
        ], 404);
    }

    /**
     * Delete a chat session
     */
    public function deleteSession(DeleteTopicSessionRequest $request, Project $project)
    {
        $validated = $request->validated();

        // Delete all messages in this session
        $deleted = \App\Models\ChatConversation::where('session_id', $validated['session_id'])
            ->where('user_id', auth()->id())
            ->where('project_id', $project->id)
            ->delete();

        if ($deleted > 0) {
            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully',
                'deleted_count' => $deleted,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Session not found',
        ], 404);
    }

    /**
     * Save a refined topic from chat to the project_topics table
     */
    public function saveRefinedTopic(SaveRefinedTopicRequest $request, Project $project)
    {
        $validated = $request->validated();

        // Get project context details
        $faculty = $project->settings['faculty'] ?? $project->field_of_study;
        $department = $project->settings['department'] ?? '';

        // Create topic with full project context
        $topic = ProjectTopic::create([
            'user_id' => auth()->id(),
            'project_id' => $project->id,
            'field_of_study' => $project->field_of_study ?? '',
            'faculty' => $faculty,
            'department' => $department,
            'course' => $project->course ?? '',
            'university' => $project->university ?? '',
            'academic_level' => $project->type ?? 'undergraduate',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'difficulty' => 'Intermediate',
            'timeline' => '3-6 months',
            'resource_level' => 'Medium',
            'feasibility_score' => 75,
            'keywords' => [],
            'research_type' => 'qualitative',
        ]);

        Log::info('Refined topic saved from chat', [
            'project_id' => $project->id,
            'topic_id' => $topic->id,
            'title' => $validated['title'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topic saved successfully',
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'description' => $topic->description,
            ],
        ]);
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
