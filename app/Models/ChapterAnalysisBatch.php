<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChapterAnalysisBatch extends Model
{
    protected $fillable = [
        'project_id',
        'status',
        'total_chapters',
        'completed_chapters',
        'failed_chapters',
        'required_words',
        'consumed_words',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChapterAnalysisBatchItem::class, 'batch_id');
    }
}
