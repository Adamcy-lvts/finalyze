<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterContextAnalysis extends Model
{
    protected $table = 'chapter_context_analysis';

    protected $fillable = [
        'project_id',
        'chapter_id',
        'word_count',
        'citation_count',
        'table_count',
        'figure_count',
        'claim_count',
        'has_introduction',
        'has_conclusion',
        'detected_issues',
        'content_quality_metrics',
        'last_analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'has_introduction' => 'boolean',
            'has_conclusion' => 'boolean',
            'detected_issues' => 'array',
            'content_quality_metrics' => 'array',
            'last_analyzed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
