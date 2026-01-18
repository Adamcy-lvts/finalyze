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
        'is_wysiwyg',
        'editor_version',
        'theme_config',
        'status',
        'extraction_data',
        'extraction_status',
        'pptx_path',
        'ai_models',
        'error_message',
    ];

    protected $casts = [
        'slides_json' => 'array',
        'is_wysiwyg' => 'boolean',
        'theme_config' => 'array',
        'ai_models' => 'array',
        'extraction_data' => 'array',
    ];

    /**
     * Check if this deck uses the WYSIWYG editor format.
     */
    public function usesWysiwygEditor(): bool
    {
        return $this->is_wysiwyg === true;
    }

    /**
     * Get slides in a normalized format.
     * For WYSIWYG slides, returns element-based structure.
     * For legacy slides, returns bullet/paragraph structure.
     */
    public function getNormalizedSlides(): array
    {
        return $this->slides_json ?? [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
