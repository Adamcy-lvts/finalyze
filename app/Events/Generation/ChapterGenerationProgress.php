<?php

namespace App\Events\Generation;

class ChapterGenerationProgress extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.chapter.progress';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'chapter_progress',
            'chapter_number' => $this->payload['chapter_number'],
            'chapter_progress' => $this->payload['chapter_progress'] ?? 0, // 0-100 for this chapter
            'current_word_count' => $this->payload['current_word_count'] ?? 0,
            'target_word_count' => $this->payload['target_word_count'] ?? 0,
            'stage_description' => $this->payload['stage_description'] ?? 'Generating content...',
        ];
    }
}
