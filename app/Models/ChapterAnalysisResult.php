<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterAnalysisResult extends Model
{
    protected $fillable = [
        'chapter_id',
        'grammar_style_score',
        'readability_score',
        'structure_score',
        'citations_score',
        'originality_score',
        'argument_score',
        'total_score',
        'word_count',
        'character_count',
        'paragraph_count',
        'sentence_count',
        'citation_count',
        'verified_citation_count',
        'completion_percentage',
        'reading_time_minutes',
        'meets_defense_requirement',
        'meets_completion_threshold',
        'grammar_issues',
        'readability_metrics',
        'structure_feedback',
        'citation_analysis',
        'suggestions',
        'analyzed_at',
    ];

    protected $casts = [
        'grammar_style_score' => 'decimal:1',
        'readability_score' => 'decimal:1',
        'structure_score' => 'decimal:1',
        'citations_score' => 'decimal:1',
        'originality_score' => 'decimal:1',
        'argument_score' => 'decimal:1',
        'total_score' => 'decimal:1',
        'completion_percentage' => 'decimal:2',
        'reading_time_minutes' => 'decimal:1',
        'meets_defense_requirement' => 'boolean',
        'meets_completion_threshold' => 'boolean',
        'grammar_issues' => 'array',
        'readability_metrics' => 'array',
        'structure_feedback' => 'array',
        'citation_analysis' => 'array',
        'suggestions' => 'array',
        'analyzed_at' => 'datetime',
    ];

    /**
     * Get the chapter that owns this analysis result
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * Check if analysis meets the 80% completion threshold
     */
    public function passesCompletionThreshold(): bool
    {
        return $this->total_score >= 80.0;
    }

    /**
     * Get score breakdown by category
     */
    public function getScoreBreakdown(): array
    {
        return [
            'Grammar & Style' => [
                'score' => $this->grammar_style_score,
                'max' => 0, // Disabled
                'percentage' => 0,
                'status' => 'disabled',
            ],
            'Readability' => [
                'score' => $this->readability_score,
                'max' => 0, // Disabled
                'percentage' => 0,
                'status' => 'disabled',
            ],
            'Structure & Organization' => [
                'score' => $this->structure_score,
                'max' => 25,
                'percentage' => $this->structure_score > 0 ? ($this->structure_score / 25) * 100 : 0,
            ],
            'Citations & References' => [
                'score' => $this->citations_score,
                'max' => 30,
                'percentage' => $this->citations_score > 0 ? ($this->citations_score / 30) * 100 : 0,
            ],
            'Originality' => [
                'score' => $this->originality_score,
                'max' => 30,
                'percentage' => $this->originality_score > 0 ? ($this->originality_score / 30) * 100 : 0,
            ],
            'Argument Strength' => [
                'score' => $this->argument_score,
                'max' => 15,
                'percentage' => $this->argument_score > 0 ? ($this->argument_score / 15) * 100 : 0,
            ],
        ];
    }

    /**
     * Get quality level based on total score
     */
    public function getQualityLevel(): string
    {
        return match (true) {
            $this->total_score >= 90 => 'Excellent',
            $this->total_score >= 80 => 'Good',
            $this->total_score >= 70 => 'Satisfactory',
            $this->total_score >= 60 => 'Needs Improvement',
            default => 'Poor'
        };
    }

    /**
     * Get all improvement suggestions as a formatted array
     */
    public function getFormattedSuggestions(): array
    {
        $suggestions = $this->suggestions ?? [];
        $formatted = [];

        foreach ($suggestions as $category => $items) {
            $formatted[$category] = is_array($items) ? $items : [$items];
        }

        return $formatted;
    }
}
