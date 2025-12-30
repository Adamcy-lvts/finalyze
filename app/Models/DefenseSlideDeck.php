<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefenseSlideDeck extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'slides_json',
        'status',
        'extraction_data',
        'extraction_status',
        'pptx_path',
        'ai_models',
        'error_message',
    ];

    protected $casts = [
        'slides_json' => 'array',
        'ai_models' => 'array',
        'extraction_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
