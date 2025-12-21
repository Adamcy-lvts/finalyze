<?php

namespace App\Services\Defense;

use App\Events\Defense\DefenseMessageSent;
use App\Events\Defense\DefensePerformanceUpdated;
use App\Events\Defense\DefenseSessionEnded;
use App\Events\Defense\DefenseSessionStarted;
use App\Models\DefenseFeedback;
use App\Models\DefenseMessage;
use App\Models\DefenseSession;
use App\Models\Project;
use App\Services\AIContentGenerator;
use Illuminate\Support\Facades\Log;

class DefenseSimulationService
{
    private AIContentGenerator $aiGenerator;

    private PanelistPersonaService $personas;

    private DefenseCreditService $credits;

    private DefensePerformanceService $performance;

    public function __construct(
        AIContentGenerator $aiGenerator,
        PanelistPersonaService $personas,
        DefenseCreditService $credits,
        DefensePerformanceService $performance
    ) {
        $this->aiGenerator = $aiGenerator;
        $this->personas = $personas;
        $this->credits = $credits;
        $this->performance = $performance;
    }

    public function startSession(Project $project, array $config): DefenseSession
    {
        $panelists = $config['selected_panelists'] ?? $this->personas->getDefaultPersonaIds();

        $session = DefenseSession::create([
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'mode' => 'text',
            'status' => 'in_progress',
            'selected_panelists' => $panelists,
            'difficulty_level' => $config['difficulty_level'] ?? 'undergraduate',
            'time_limit_minutes' => $config['time_limit_minutes'] ?? null,
            'question_limit' => $config['question_limit'] ?? null,
            'started_at' => now(),
        ]);

        $this->credits->deductSessionBase($project->user, $session);

        broadcast(new DefenseSessionStarted($session));

        return $session;
    }

    public function generatePanelistQuestion(
        DefenseSession $session,
        ?string $personaId = null,
        bool $requestHint = false
    ): DefenseMessage
    {
        $this->ensureSessionActive($session);
        $this->ensureHasWordBalance($session, 'continue the defense simulation');

        $personaId = $personaId ?: $this->personas->pickNextPersonaId($session);
        $project = $session->project()->with('chapters', 'universityRelation')->firstOrFail();

        $lastQuestion = $session->messages()
            ->where('role', 'panelist')
            ->latest()
            ->first();
        $followUpPersona = $lastQuestion?->panelist_persona ?? $personaId;
        $followUpPrompt = $this->buildFollowUpPrompt($session, $followUpPersona, $requestHint);
        if ($followUpPrompt && $lastQuestion?->panelist_persona) {
            $personaId = $lastQuestion->panelist_persona;
        }

        $systemPrompt = $this->personas->buildSystemPrompt($personaId, $project, $session->difficulty_level);
        $history = $this->buildConversationHistory($session);

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $followUpPrompt ?? $this->personas->buildQuestionPrompt($personaId, $session)]]
        );

        $response = $this->aiGenerator->generate('', [
            'messages' => $messages,
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'feature' => 'defense_simulation',
            'user_id' => $session->user_id,
        ]);

        $question = $this->cleanQuestion($response);

        $message = $session->messages()->create([
            'role' => 'panelist',
            'panelist_persona' => $personaId,
            'is_follow_up' => (bool) $followUpPrompt,
            'content' => $question,
        ]);

        if (! $followUpPrompt) {
            $session->increment('questions_asked');
        }
        $this->credits->deductForTextExchange($session->user, $session, $question, 'Defense panel question');

        broadcast(new DefenseMessageSent($session, $message));

        return $message;
    }

    public function processStudentResponse(DefenseSession $session, string $response, ?int $responseTimeMs = null): array
    {
        $this->ensureSessionActive($session);
        $this->ensureHasWordBalance($session, 'continue the defense simulation');

        $message = $session->messages()->create([
            'role' => 'student',
            'content' => $response,
            'response_time_ms' => $responseTimeMs,
        ]);

        $question = $session->messages()
            ->where('role', 'panelist')
            ->latest()
            ->first();

        $evaluation = $question
            ? $this->evaluateResponse($session, $question, $message)
            : $this->defaultEvaluation();

        $message->update([
            'ai_feedback' => $evaluation,
        ]);

        $metrics = $this->performance->calculatePerformanceMetrics($session->fresh());
        $session->update([
            'performance_metrics' => $metrics,
            'readiness_score' => $metrics['readiness_score'] ?? 0,
        ]);

        broadcast(new DefensePerformanceUpdated($session, $metrics));

        return [
            'message' => $message->fresh(),
            'evaluation' => $evaluation,
            'metrics' => $metrics,
        ];
    }

    public function endSession(DefenseSession $session, bool $asyncFeedback = true): DefenseFeedback
    {
        Log::info('DefenseSimulationService endSession start', [
            'session_id' => $session->id,
            'status' => $session->status,
            'questions_asked' => $session->questions_asked,
        ]);
        $this->ensureSessionActive($session, allowCompleted: true, allowAbandoned: true, ignoreLimits: true);

        $metrics = $this->performance->calculatePerformanceMetrics($session->fresh());
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
            'session_duration_seconds' => $this->calculateDurationSeconds($session),
            'performance_metrics' => $metrics,
            'readiness_score' => $metrics['readiness_score'] ?? 0,
        ]);

        Log::info('DefenseSimulationService endSession updated', [
            'session_id' => $session->id,
            'status' => $session->fresh()->status,
            'readiness_score' => $session->fresh()->readiness_score,
        ]);

        $feedback = DefenseFeedback::where('session_id', $session->id)->first();
        if (! $feedback) {
            $feedback = DefenseFeedback::create([
                'session_id' => $session->id,
                'overall_score' => $metrics['readiness_score'] ?? null,
                'strengths' => [],
                'weaknesses' => [],
                'recommendations' => 'Feedback is being generated...',
                'generated_at' => null,
            ]);
        }

        if ($asyncFeedback) {
            $this->dispatchFeedbackGeneration($session->id, $metrics);
        } else {
            $feedback = $this->generateFeedbackForSession($session->id, $metrics) ?? $feedback;
        }

        Log::info('DefenseSimulationService endSession complete', [
            'session_id' => $session->id,
            'feedback_id' => $feedback->id ?? null,
        ]);

        broadcast(new DefenseSessionEnded($session, $feedback));

        return $feedback;
    }

    public function generateFeedbackForSession(int $sessionId, array $metrics): ?DefenseFeedback
    {
        $session = DefenseSession::find($sessionId);
        if (! $session) {
            return null;
        }

        try {
            if (! $this->hasEnoughBalanceForSession($session)) {
                $feedback = DefenseFeedback::updateOrCreate(
                    ['session_id' => $session->id],
                    [
                        'overall_score' => $metrics['readiness_score'] ?? 0,
                        'strengths' => [],
                        'weaknesses' => [],
                        'recommendations' => 'Feedback could not be generated because your credit balance is low. Top up to generate AI feedback.',
                        'generated_at' => now(),
                    ]
                );

                broadcast(new DefenseSessionEnded($session, $feedback));

                return $feedback;
            }

            $payload = $this->buildFeedbackPayload($session, $metrics);
            $feedback = DefenseFeedback::updateOrCreate(
                ['session_id' => $session->id],
                [
                    'overall_score' => $payload['overall_score'] ?? ($metrics['readiness_score'] ?? 0),
                    'strengths' => $payload['strengths'] ?? [],
                    'weaknesses' => $payload['weaknesses'] ?? [],
                    'recommendations' => $payload['recommendations'] ?? 'Continue practicing concise, evidence-driven responses.',
                    'generated_at' => now(),
                ]
            );

            $this->credits->deductFeedbackCost($session->user, $session);

            broadcast(new DefenseSessionEnded($session, $feedback));

            return $feedback;
        } catch (\Throwable $e) {
            Log::warning('Defense feedback generation failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function evaluateResponse(DefenseSession $session, DefenseMessage $question, DefenseMessage $answer): array
    {
        $prompt = <<<PROMPT
You are a defense examiner. Score the student's response on clarity and technical depth.
Return JSON only with keys: clarity, technical_depth, feedback, strengths, improvements.
Scores are 0-100. strengths and improvements are arrays of short phrases.

Question: {$question->content}

Student Response: {$answer->content}
PROMPT;

        $raw = $this->aiGenerator->generate($prompt, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'feature' => 'defense_evaluation',
            'user_id' => $session->user_id,
        ]);

        $parsed = $this->parseJsonResponse($raw);

        if (! $parsed) {
            Log::warning('Defense evaluation parse failed', [
                'session_id' => $session->id,
                'raw' => $raw,
            ]);

            return $this->defaultEvaluation();
        }

        return array_merge($this->defaultEvaluation(), $parsed);
    }

    private function buildFeedbackPayload(DefenseSession $session, array $metrics): array
    {
        $transcript = $session->messages()->orderBy('created_at')->get()->map(function ($message) {
            $speaker = $message->role === 'panelist' ? ($message->panelist_persona ?? 'panelist') : 'student';
            return strtoupper($speaker).": {$message->content}";
        })->implode("\n");

        $prompt = <<<PROMPT
You are a defense coach. Produce a short post-session report.
Return JSON only with keys: overall_score, strengths, weaknesses, recommendations.
Strengths/weaknesses should be arrays of short phrases. Recommendations should be a concise paragraph.

Metrics: clarity {$metrics['clarity']}, technical_depth {$metrics['technical_depth']}.

Transcript:
{$transcript}
PROMPT;

        $raw = $this->aiGenerator->generate($prompt, [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'feature' => 'defense_feedback',
            'user_id' => $session->user_id,
        ]);

        return $this->parseJsonResponse($raw) ?: [];
    }

    private function buildConversationHistory(DefenseSession $session, int $limit = 8): array
    {
        $messages = $session->messages()->orderByDesc('created_at')->limit($limit)->get()->reverse();

        return $messages->map(function (DefenseMessage $message) {
            $role = $message->role === 'student' ? 'user' : 'assistant';
            $prefix = $message->role === 'panelist'
                ? strtoupper($message->panelist_persona ?? 'panelist').': '
                : '';

            return [
                'role' => $role,
                'content' => $prefix.$message->content,
            ];
        })->values()->all();
    }

    private function cleanQuestion(string $content): string
    {
        $cleaned = trim($content);
        $cleaned = preg_replace('/^\s*(question:|q:)/i', '', $cleaned);

        return trim($cleaned);
    }

    private function parseJsonResponse(string $raw): ?array
    {
        $trimmed = trim($raw);
        $trimmed = preg_replace('/^```(?:json)?/i', '', $trimmed);
        $trimmed = preg_replace('/```$/', '', $trimmed);
        $trimmed = trim($trimmed);

        $data = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        return null;
    }

    private function defaultEvaluation(): array
    {
        return [
            'clarity' => 0,
            'technical_depth' => 0,
            'feedback' => 'Awaiting evaluation.',
            'strengths' => [],
            'improvements' => [],
        ];
    }

    private function buildFollowUpPrompt(DefenseSession $session, string $personaId, bool $requestHint): ?string
    {
        $lastStudent = $session->messages()
            ->where('role', 'student')
            ->latest()
            ->first();

        $lastQuestion = $session->messages()
            ->where('role', 'panelist')
            ->latest()
            ->first();

        if (! $lastStudent || ! $lastQuestion) {
            return null;
        }

        $evaluation = $lastStudent->ai_feedback ?? [];
        $needsFollowUp = $this->needsFollowUp($evaluation, $requestHint);

        if (! $needsFollowUp) {
            return null;
        }

        $previousStudent = $session->messages()
            ->where('role', 'student')
            ->orderByDesc('created_at')
            ->skip(1)
            ->first();

        $secondFailure = false;
        if ($previousStudent && $previousStudent->ai_feedback) {
            $secondFailure = $this->needsFollowUp($previousStudent->ai_feedback, false);
        }

        return $this->personas->buildFollowUpPrompt(
            $personaId,
            $lastQuestion->content,
            $lastStudent->content,
            $evaluation,
            $secondFailure,
            $requestHint
        );
    }

    private function needsFollowUp(array $evaluation, bool $requestHint): bool
    {
        $clarity = (int) ($evaluation['clarity'] ?? 0);
        $depth = (int) ($evaluation['technical_depth'] ?? 0);
        $improvements = $evaluation['improvements'] ?? [];

        if ($requestHint) {
            return true;
        }

        return $clarity < 45 || $depth < 45 || count($improvements) >= 2;
    }

    private function calculateDurationSeconds(DefenseSession $session): int
    {
        if (! $session->started_at) {
            return 0;
        }

        $seconds = now()->diffInSeconds($session->started_at, false);

        return (int) max(0, abs($seconds));
    }

    private function dispatchFeedbackGeneration(int $sessionId, array $metrics): void
    {
        dispatch(function () use ($sessionId, $metrics) {
            app(self::class)->generateFeedbackForSession($sessionId, $metrics);
        })->afterResponse();
    }

    private function hasEnoughBalanceForSession(DefenseSession $session): bool
    {
        $user = $session->user ?? $session->user()->first();
        if (! $user) {
            return true;
        }

        return $this->credits->hasEnoughCredits($user, $session->mode ?? 'text');
    }

    private function ensureHasWordBalance(DefenseSession $session, string $context): void
    {
        if (! $this->hasEnoughBalanceForSession($session)) {
            throw new \Exception("Insufficient credit balance. Please top up to {$context}.");
        }
    }

    private function ensureSessionActive(
        DefenseSession $session,
        bool $allowCompleted = false,
        bool $allowAbandoned = false,
        bool $ignoreLimits = false
    ): void
    {
        if ($session->status === 'completed' && ! $allowCompleted) {
            throw new \RuntimeException('Session already completed.');
        }

        if ($session->status === 'abandoned' && ! $allowAbandoned) {
            throw new \RuntimeException('Session has been abandoned.');
        }

        if (! $ignoreLimits) {
            if ($session->time_limit_minutes && $session->started_at) {
                $elapsed = now()->diffInMinutes($session->started_at);
                if ($elapsed >= $session->time_limit_minutes) {
                    throw new \RuntimeException('Session time limit reached.');
                }
            }

            if ($session->question_limit && $session->questions_asked >= $session->question_limit) {
                throw new \RuntimeException('Session question limit reached.');
            }
        }
    }
}
