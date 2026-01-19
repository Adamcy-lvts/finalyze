<?php

namespace App\Http\Resources\Defense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property string $mode
 * @property string $status
 * @property array|null $selected_panelists
 * @property string|null $difficulty_level
 * @property int|null $time_limit_minutes
 * @property int|null $question_limit
 * @property int $session_duration_seconds
 * @property int $questions_asked
 * @property \Illuminate\Support\Carbon|string|null $started_at
 * @property \Illuminate\Support\Carbon|string|null $completed_at
 * @property array|null $performance_metrics
 * @property int|null $readiness_score
 * @property int|null $words_consumed
 */
class DefenseSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'project_id' => $this->project_id,
            'mode' => $this->mode,
            'status' => $this->status,
            'selected_panelists' => $this->selected_panelists,
            'difficulty_level' => $this->difficulty_level,
            'time_limit_minutes' => $this->time_limit_minutes,
            'question_limit' => $this->question_limit,
            'session_duration_seconds' => $this->session_duration_seconds,
            'questions_asked' => $this->questions_asked,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'performance_metrics' => $this->performance_metrics,
            'readiness_score' => $this->readiness_score,
            'words_consumed' => $this->words_consumed,
            'messages' => DefenseMessageResource::collection($this->whenLoaded('messages')),
            'feedback' => new DefenseFeedbackResource($this->whenLoaded('feedback')),
        ];
    }
}
