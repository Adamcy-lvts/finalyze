<?php

namespace App\Events\Generation;

class ChapterGenerationCompleted extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.chapter.completed';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'chapter_completed',
            'chapter_number' => $this->payload['chapter_number'],
            'chapter_title' => $this->payload['chapter_title'] ?? "Chapter {$this->payload['chapter_number']}",
            'word_count' => $this->payload['word_count'] ?? 0,
            'target_word_count' => $this->payload['target_word_count'] ?? 0,
            'generation_time' => $this->payload['generation_time'] ?? 0,
            'chapters_completed' => $this->payload['chapters_completed'] ?? 0,
            'total_chapters' => $this->payload['total_chapters'] ?? 5,
        ];
    }
}
