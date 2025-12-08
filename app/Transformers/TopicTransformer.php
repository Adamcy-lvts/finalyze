<?php

namespace App\Transformers;

use App\Models\ProjectTopic;
use Illuminate\Support\Collection;

class TopicTransformer
{
    /**
        * Transform a collection of ProjectTopic models into a simple array payload.
        *
        * @param Collection<int,ProjectTopic> $topics
        */
    public static function collection(Collection $topics): array
    {
        return $topics->values()->map(fn(ProjectTopic $topic, int $index) => self::toArray($topic, $index))->all();
    }

    public static function toArray(ProjectTopic $topic, ?int $index = null): array
    {
        return [
            'id' => is_null($index) ? $topic->id : $index + 1,
            'title' => $topic->title,
            'description' => $topic->description ?? 'Research topic suggestion',
            'difficulty' => $topic->difficulty ?? 'Intermediate',
            'timeline' => $topic->timeline ?? '6-9 months',
            'resource_level' => $topic->resource_level ?? 'Medium',
            'feasibility_score' => $topic->feasibility_score ?? 75,
            'keywords' => $topic->keywords ?: [],
            'research_type' => $topic->research_type ?? 'Applied Research',
            'field_of_study' => $topic->field_of_study ?? 'General',
            'faculty' => $topic->faculty,
            'course' => $topic->course ?? '',
            'academic_level' => $topic->academic_level ?? 'undergraduate',
        ];
    }
}
