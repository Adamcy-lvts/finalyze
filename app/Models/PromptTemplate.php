<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptTemplate extends Model
{
    protected $fillable = [
        'context_type',
        'context_value',
        'parent_template_id',
        'chapter_type',
        'table_requirements',
        'diagram_requirements',
        'calculation_requirements',
        'code_requirements',
        'placeholder_rules',
        'recommended_tools',
        'system_prompt',
        'chapter_prompt_template',
        'mock_data_config',
        'citation_requirements',
        'formatting_rules',
        'priority',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'table_requirements' => 'array',
            'diagram_requirements' => 'array',
            'calculation_requirements' => 'array',
            'code_requirements' => 'array',
            'placeholder_rules' => 'array',
            'recommended_tools' => 'array',
            'mock_data_config' => 'array',
            'citation_requirements' => 'array',
            'formatting_rules' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the parent template (for inheritance)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'parent_template_id');
    }

    /**
     * Get child templates
     */
    public function children(): HasMany
    {
        return $this->hasMany(PromptTemplate::class, 'parent_template_id');
    }

    /**
     * Scope to filter by context type
     */
    public function scopeForContext($query, string $type, string $value)
    {
        return $query->where('context_type', $type)
            ->where('context_value', $value);
    }

    /**
     * Scope to filter by chapter type
     */
    public function scopeForChapter($query, string $chapterType)
    {
        return $query->where(function ($q) use ($chapterType) {
            $q->where('chapter_type', $chapterType)
                ->orWhereNull('chapter_type');
        });
    }

    /**
     * Scope to get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get merged requirements with parent template
     */
    public function getMergedTableRequirements(): array
    {
        $parentReqs = $this->parent?->getMergedTableRequirements() ?? [];

        return array_merge($parentReqs, $this->table_requirements ?? []);
    }

    /**
     * Get merged diagram requirements with parent
     */
    public function getMergedDiagramRequirements(): array
    {
        $parentReqs = $this->parent?->getMergedDiagramRequirements() ?? [];

        return array_merge($parentReqs, $this->diagram_requirements ?? []);
    }
}
