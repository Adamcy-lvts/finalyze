<?php

namespace App\Transformers;

use App\Models\ProjectOutline;
use Illuminate\Support\Collection;

class OutlineTransformer
{
    /**
     * @param Collection<int,ProjectOutline> $outlines
     */
    public static function collection(Collection $outlines): array
    {
        return $outlines->values()->map(fn(ProjectOutline $outline) => self::toArray($outline))->all();
    }

    public static function toArray(ProjectOutline $outline): array
    {
        return [
            'id' => $outline->id,
            'chapter_number' => $outline->chapter_number,
            'chapter_title' => $outline->chapter_title,
            'target_word_count' => $outline->target_word_count,
            'completion_threshold' => $outline->completion_threshold,
            'description' => $outline->description,
            'sections' => $outline->sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'section_number' => $section->section_number,
                    'section_title' => $section->section_title,
                    'section_description' => $section->section_description,
                    'target_word_count' => $section->target_word_count,
                    'current_word_count' => $section->current_word_count,
                    'is_completed' => $section->is_completed,
                    'is_required' => $section->is_required,
                ];
            }),
        ];
    }
}
