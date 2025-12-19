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
        return $this->getMergedJson('table_requirements', default: []);
    }

    /**
     * Get merged diagram requirements with parent
     */
    public function getMergedDiagramRequirements(): array
    {
        return $this->getMergedJson('diagram_requirements', default: []);
    }

    public function getMergedCalculationRequirements(): array
    {
        return $this->getMergedJson('calculation_requirements', default: []);
    }

    public function getMergedCodeRequirements(): array
    {
        return $this->getMergedJson('code_requirements', default: []);
    }

    public function getMergedPlaceholderRules(): array
    {
        return $this->getMergedJson('placeholder_rules', default: []);
    }

    public function getMergedRecommendedTools(): array
    {
        return $this->getMergedJson('recommended_tools', default: []);
    }

    public function getMergedMockDataConfig(): array
    {
        return $this->getMergedJson('mock_data_config', default: []);
    }

    public function getMergedCitationRequirements(): array
    {
        return $this->getMergedJson('citation_requirements', default: []);
    }

    public function getMergedFormattingRules(): array
    {
        return $this->getMergedJson('formatting_rules', default: []);
    }

    public function getMergedSystemPrompt(): ?string
    {
        $this->loadMissing('parent');
        $own = $this->system_prompt;
        if (is_string($own) && trim($own) !== '') {
            return $own;
        }

        return $this->parent?->getMergedSystemPrompt();
    }

    public function getMergedChapterPromptTemplate(): ?string
    {
        $this->loadMissing('parent');
        $own = $this->chapter_prompt_template;
        if (is_string($own) && trim($own) !== '') {
            return $own;
        }

        return $this->parent?->getMergedChapterPromptTemplate();
    }

    private function getMergedJson(string $field, array $default = []): array
    {
        $this->loadMissing('parent');

        $parentVal = $this->parent?->getMergedJson($field, $default) ?? $default;
        $ownVal = $this->getAttribute($field);

        if (! is_array($ownVal)) {
            return $parentVal;
        }

        return $this->mergeDeep($parentVal, $ownVal);
    }

    private function mergeDeep(array $base, array $override): array
    {
        if ($this->isList($base) && $this->isList($override)) {
            return $this->mergeLists($base, $override);
        }

        if (! $this->isList($base) && ! $this->isList($override)) {
            $merged = $base;
            foreach ($override as $key => $value) {
                if (array_key_exists($key, $merged) && is_array($merged[$key]) && is_array($value)) {
                    $merged[$key] = $this->mergeDeep($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }
            }
            return $merged;
        }

        return $override;
    }

    private function mergeLists(array $base, array $override): array
    {
        $baseByType = $this->indexByTypeIfPossible($base);
        $overrideByType = $this->indexByTypeIfPossible($override);

        if ($baseByType !== null && $overrideByType !== null) {
            return array_values(array_merge($baseByType, $overrideByType));
        }

        $out = $base;
        $seen = [];
        foreach ($out as $item) {
            $seen[$this->stableHash($item)] = true;
        }
        foreach ($override as $item) {
            $hash = $this->stableHash($item);
            if (isset($seen[$hash])) {
                continue;
            }
            $seen[$hash] = true;
            $out[] = $item;
        }

        return $out;
    }

    private function indexByTypeIfPossible(array $list): ?array
    {
        if (! $this->isList($list)) {
            return null;
        }

        $indexed = [];
        foreach ($list as $item) {
            if (! is_array($item)) {
                return null;
            }
            $type = $item['type'] ?? null;
            if (! is_string($type) || $type === '') {
                return null;
            }
            $indexed[$type] = $item;
        }

        return $indexed;
    }

    private function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }
        $expected = 0;
        foreach (array_keys($value) as $k) {
            if ($k !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private function stableHash(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        $normalized = $this->normalizeForHash($value);

        return md5(json_encode($normalized));
    }

    private function normalizeForHash(array $value): array
    {
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->normalizeForHash($v);
            }
        }

        if (! $this->isList($value)) {
            ksort($value);
        }

        return $value;
    }
}
