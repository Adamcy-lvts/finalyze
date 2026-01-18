<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FeedbackRequest;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\WordTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'source' => ['required', 'string', 'max:64'],
            'context' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $project = Project::query()->findOrFail($validated['project_id']);
        $eligibility = $this->checkEligibility($user, $project);
        if (! $eligibility['eligible']) {
            return response()->json([
                'message' => $eligibility['reason'] ?? 'Not eligible for feedback prompt.',
            ], 409);
        }

        $feedbackRequest = FeedbackRequest::create([
            'user_id' => $user->id,
            'requestable_type' => Project::class,
            'requestable_id' => $project->id,
            'source' => $validated['source'],
            'status' => 'shown',
            'shown_at' => now(),
            'context' => $validated['context'] ?? null,
        ]);

        ActivityLog::record(
            'feedback.prompt_shown',
            'Feedback prompt shown',
            $project,
            $user,
            [
                'feedback_request_id' => $feedbackRequest->id,
                'source' => $validated['source'],
                'context' => $validated['context'] ?? null,
            ]
        );

        return response()->json([
            'id' => $feedbackRequest->id,
            'status' => $feedbackRequest->status,
        ]);
    }

    public function eligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'source' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $project = Project::query()->findOrFail($validated['project_id']);
        $eligibility = $this->checkEligibility($user, $project, true);

        return response()->json($eligibility);
    }

    public function submit(Request $request, FeedbackRequest $feedbackRequest): JsonResponse
    {
        $this->ensureOwner($request, $feedbackRequest);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $comment = trim((string) ($validated['comment'] ?? ''));
        if ($validated['rating'] < 3 && $comment === '') {
            return response()->json([
                'message' => 'Please tell us what went wrong so we can improve.',
                'errors' => ['comment' => ['Comment is required when rating is less than 3.']],
            ], 422);
        }

        $feedbackRequest->fill([
            'rating' => $validated['rating'],
            'comment' => $comment !== '' ? $comment : null,
            'status' => 'submitted',
            'submitted_at' => now(),
        ])->save();

        ActivityLog::record(
            'feedback.submitted',
            'Feedback submitted',
            $feedbackRequest->requestable,
            $request->user(),
            [
                'feedback_request_id' => $feedbackRequest->id,
                'rating' => $validated['rating'],
                'has_comment' => $comment !== '',
                'source' => $feedbackRequest->source,
            ]
        );

        return response()->json(['status' => $feedbackRequest->status]);
    }

    public function dismiss(Request $request, FeedbackRequest $feedbackRequest): JsonResponse
    {
        $this->ensureOwner($request, $feedbackRequest);

        $feedbackRequest->fill([
            'status' => 'dismissed',
            'dismissed_at' => now(),
            'cooldown_until' => now()->addHours($this->getSettingInt('feedback.cooldown_hours', 72)),
        ])->save();

        ActivityLog::record(
            'feedback.dismissed',
            'Feedback prompt dismissed',
            $feedbackRequest->requestable,
            $request->user(),
            [
                'feedback_request_id' => $feedbackRequest->id,
                'source' => $feedbackRequest->source,
            ]
        );

        return response()->json(['status' => $feedbackRequest->status]);
    }

    private function ensureOwner(Request $request, FeedbackRequest $feedbackRequest): void
    {
        if ($feedbackRequest->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    private function checkEligibility($user, Project $project, bool $includeExisting = false): array
    {
        if ($project->user_id !== $user->id) {
            return ['eligible' => false, 'reason' => 'Project not owned by user.'];
        }

        $minAccountAgeDays = $this->getSettingInt('feedback.minimum_account_age_days', 10);
        $requiredWordsUsed = $this->getSettingInt('feedback.minimum_words_used', 7000);
        $maxPromptShows = $this->getSettingInt('feedback.max_prompt_shows', 3);
        $cooldownHours = $this->getSettingInt('feedback.cooldown_hours', 72);

        if (! $user->created_at || $user->created_at->diffInDays(now()) < $minAccountAgeDays) {
            return ['eligible' => false, 'reason' => 'Account age requirement not met.'];
        }

        if (($user->total_words_used ?? 0) < $requiredWordsUsed) {
            return ['eligible' => false, 'reason' => 'Word usage requirement not met.'];
        }

        $topicGenerationUsed = WordTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', WordTransaction::TYPE_USAGE)
            ->where('reference_type', 'topic_generation')
            ->exists();

        if (! $topicGenerationUsed) {
            return ['eligible' => false, 'reason' => 'Topic generation requirement not met.'];
        }

        $featureUsageQuery = WordTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', WordTransaction::TYPE_USAGE)
            ->where('reference_type', 'chapter')
            ->where(function ($query) {
                $query->where('description', 'like', 'Manual editor:%')
                    ->orWhere('description', 'like', 'Chapter generation%')
                    ->orWhere('description', 'like', '%autocomplete accepted%')
                    ->orWhere('description', 'like', '%chapter starter accepted%');
            });

        if (! $featureUsageQuery->exists()) {
            return ['eligible' => false, 'reason' => 'Editor feature usage requirement not met.'];
        }

        $submitted = FeedbackRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'submitted')
            ->exists();

        if ($submitted) {
            return ['eligible' => false, 'reason' => 'Feedback already submitted.'];
        }

        $shownCount = FeedbackRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('shown_at')
            ->count();

        if ($shownCount >= $maxPromptShows) {
            return ['eligible' => false, 'reason' => 'Prompt show limit reached.'];
        }

        $latest = FeedbackRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('shown_at')
            ->first();

        if ($latest && $latest->cooldown_until && $latest->cooldown_until->isFuture()) {
            return ['eligible' => false, 'reason' => 'Cooldown active.'];
        }

        if ($includeExisting) {
            $existing = FeedbackRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'shown')
                ->whereNull('submitted_at')
                ->whereNull('dismissed_at')
                ->orderByDesc('shown_at')
                ->first();

            if ($existing) {
                return [
                    'eligible' => true,
                    'existing_request_id' => $existing->id,
                ];
            }
        }

        return [
            'eligible' => true,
            'cooldown_hours' => $cooldownHours,
        ];
    }

    private function getSettingInt(string $key, int $default): int
    {
        $setting = SystemSetting::query()->where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        $value = $setting->value;
        if (is_array($value)) {
            $value = $value['value'] ?? $default;
        }

        return is_numeric($value) ? (int) $value : $default;
    }
}
