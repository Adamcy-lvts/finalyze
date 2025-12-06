<?php

namespace App\Events\Generation;

class GenerationCompleted extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.completed';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'completed',
            'total_word_count' => $this->payload['total_word_count'] ?? 0,
            'total_chapters' => $this->payload['total_chapters'] ?? 0,
            'total_duration' => $this->payload['total_duration'] ?? 0,
            'papers_collected' => $this->payload['papers_collected'] ?? 0,
            'download_links' => $this->payload['download_links'] ?? null,
        ];
    }
}
