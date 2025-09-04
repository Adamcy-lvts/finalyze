<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversation extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'chapter_number',
        'session_id',
        'message_order',
        'message_type',
        'content',
        'context_data',
        'ai_model',
        'tokens_used',
        'response_time',
    ];

    protected function casts(): array
    {
        return [
            'context_data' => 'array',
            'tokens_used' => 'integer',
            'response_time' => 'decimal:3',
            'chapter_number' => 'integer',
            'message_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId)
            ->orderBy('message_order');
    }

    public function scopeForChapter(Builder $query, int $projectId, int $chapterNumber): Builder
    {
        return $query->where('project_id', $projectId)
            ->where('chapter_number', $chapterNumber);
    }

    public function scopeLatestSession(Builder $query, int $projectId, int $chapterNumber): Builder
    {
        return $query->forChapter($projectId, $chapterNumber)
            ->latest('created_at')
            ->take(1);
    }

    public function scopeUserMessages(Builder $query): Builder
    {
        return $query->where('message_type', 'user');
    }

    public function scopeAiMessages(Builder $query): Builder
    {
        return $query->where('message_type', 'ai');
    }
}
