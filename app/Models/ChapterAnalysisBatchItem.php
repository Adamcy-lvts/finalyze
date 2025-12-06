<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChapterAnalysisBatchItem extends Model
{
    protected $fillable = [
        'batch_id',
        'chapter_id',
        'analysis_result_id',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ChapterAnalysisBatch::class, 'batch_id');
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function analysisResult(): BelongsTo
    {
        return $this->belongsTo(ChapterAnalysisResult::class, 'analysis_result_id');
    }
}
