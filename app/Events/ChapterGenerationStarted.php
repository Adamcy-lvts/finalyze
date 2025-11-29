<?php

namespace App\Events\Generation;

class ChapterGenerationStarted extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.chapter.started';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'chapter_started',
            'chapter_number' => $this->payload['chapter_number'],
            'chapter_title' => $this->payload['chapter_title'] ?? "Chapter {$this->payload['chapter_number']}",
            'target_word_count' => $this->payload['target_word_count'] ?? 0,
            'total_chapters' => $this->payload['total_chapters'] ?? 5,
        ];
    }
}
