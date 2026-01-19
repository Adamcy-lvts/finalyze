<?php

namespace App\Http\Resources\Defense;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property int $session_id
 * @property string $role
 * @property string|null $panelist_persona
 * @property bool|null $is_follow_up
 * @property string $content
 * @property string|null $audio_url
 * @property float|null $audio_duration_seconds
 * @property int|null $tokens_used
 * @property int|null $response_time_ms
 * @property array|null $ai_feedback
 * @property \Illuminate\Support\Carbon|string|null $created_at
 */
class DefenseMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'role' => $this->role,
            'panelist_persona' => $this->panelist_persona,
            'is_follow_up' => $this->is_follow_up,
            'content' => $this->content,
            'audio_url' => $this->audio_url,
            'audio_duration_seconds' => $this->audio_duration_seconds,
            'tokens_used' => $this->tokens_used,
            'response_time_ms' => $this->response_time_ms,
            'ai_feedback' => $this->ai_feedback,
            'created_at' => $this->created_at,
        ];
    }
}
