<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterSection extends Model
{
    protected $fillable = [
        'project_outline_id',
        'section_number',
        'section_title',
        'section_description',
        'target_word_count',
        'display_order',
        'is_required',
        'is_completed',
        'current_word_count',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_completed' => 'boolean',
            'target_word_count' => 'integer',
            'current_word_count' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function outline(): BelongsTo
    {
        return $this->belongsTo(ProjectOutline::class, 'project_outline_id');
    }

    /**
     * Get completion percentage for this section
     */
    public function getCompletionPercentageAttribute(): float
    {
        if ($this->target_word_count === 0) {
            return 0;
        }

        return round(min(($this->current_word_count / $this->target_word_count) * 100, 100), 2);
    }

    /**
     * Check if section meets word count threshold (80% by default)
     */
    public function meetsWordCountThreshold(int $threshold = 80): bool
    {
        return $this->completion_percentage >= $threshold;
    }

    /**
     * Update completion status based on word count
     */
    public function updateCompletionStatus(): bool
    {
        $wasCompleted = $this->is_completed;
        $this->is_completed = $this->meetsWordCountThreshold();

        if ($wasCompleted !== $this->is_completed) {
            $this->save();

            return true;
        }

        return false;
    }
}
