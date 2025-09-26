<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectOutline extends Model
{
    protected $fillable = [
        'project_id',
        'chapter_number',
        'chapter_title',
        'target_word_count',
        'completion_threshold',
        'description',
        'display_order',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'target_word_count' => 'integer',
            'completion_threshold' => 'integer',
            'display_order' => 'integer',
            'chapter_number' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChapterSection::class)->orderBy('display_order');
    }

    /**
     * Get the current chapter for this outline
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'chapter_number', 'chapter_number')
            ->where('project_id', $this->project_id);
    }

    /**
     * Calculate completion percentage based on sections
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalSections = $this->sections()->where('is_required', true)->count();
        $completedSections = $this->sections()->where('is_required', true)->where('is_completed', true)->count();

        if ($totalSections === 0) {
            return 0;
        }

        return round(($completedSections / $totalSections) * 100, 2);
    }

    /**
     * Get current word count from related chapter
     */
    public function getCurrentWordCountAttribute(): int
    {
        return $this->chapter?->word_count ?? 0;
    }

    /**
     * Check if chapter meets completion threshold
     */
    public function getIsCompleteAttribute(): bool
    {
        return $this->completion_percentage >= $this->completion_threshold;
    }

    /**
     * Get next incomplete section
     * Prioritize completely missing sections over sections that just need more words
     */
    public function getNextSectionAttribute(): ?ChapterSection
    {
        // First, look for sections with no content at all (completely missing)
        $missingSection = $this->sections()
            ->where('is_required', true)
            ->where('current_word_count', 0)
            ->orderBy('display_order')
            ->first();

        if ($missingSection) {
            return $missingSection;
        }

        // If no completely missing sections, find incomplete ones that need more content
        return $this->sections()
            ->where('is_required', true)
            ->where('is_completed', false)
            ->where('current_word_count', '>', 0) // Has some content but below threshold
            ->orderBy('display_order')
            ->first();
    }
}
