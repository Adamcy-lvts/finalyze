<?php

namespace App\Transformers;

use App\Models\Chapter;
use Illuminate\Support\Collection;

class ChapterTransformer
{
    /**
     * @param Collection<int,Chapter> $chapters
     */
    public static function collection(Collection $chapters): array
    {
        return $chapters->values()->map(fn(Chapter $chapter) => self::toArray($chapter))->all();
    }

    public static function toArray(Chapter $chapter): array
    {
        return [
            'id' => $chapter->id,
            'chapter_number' => $chapter->chapter_number,
            'title' => $chapter->title,
            'content' => $chapter->content,
            'target_word_count' => $chapter->target_word_count,
            'word_count' => $chapter->word_count,
            'status' => $chapter->status,
            'updated_at' => $chapter->updated_at?->toISOString(),
        ];
    }
}
