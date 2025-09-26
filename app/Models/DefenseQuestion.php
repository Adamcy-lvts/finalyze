<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefenseQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'chapter_id',
        'chapter_number',
        'question',
        'suggested_answer',
        'key_points',
        'difficulty',
        'category',
        'is_active',
        'times_viewed',
        'user_marked_helpful',
        'last_shown_at',
        'ai_model',
        'generation_batch',
    ];

    protected $casts = [
        'key_points' => 'array',
        'is_active' => 'boolean',
        'user_marked_helpful' => 'boolean',
        'last_shown_at' => 'datetime',
        'times_viewed' => 'integer',
        'generation_batch' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotRecentlyShown($query, $hours = 24)
    {
        return $query->where(function ($q) use ($hours) {
            $q->whereNull('last_shown_at')
                ->orWhere('last_shown_at', '<=', now()->subHours($hours));
        });
    }

    public function scopeForChapter($query, $chapterNumber)
    {
        return $query->where('chapter_number', $chapterNumber);
    }

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    // Methods
    public function markAsViewed()
    {
        $this->increment('times_viewed');
        $this->update(['last_shown_at' => now()]);
    }

    public function markAsHelpful($helpful = true)
    {
        $this->update(['user_marked_helpful' => $helpful]);
    }
}
