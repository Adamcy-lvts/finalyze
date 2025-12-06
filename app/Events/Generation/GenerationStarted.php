<?php

namespace App\Events\Generation;

class GenerationStarted extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.started';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'started',
            'estimated_duration' => $this->payload['estimated_duration'] ?? '15-20 minutes',
            'total_chapters' => $this->payload['total_chapters'] ?? 5,
            'is_resume' => $this->payload['is_resume'] ?? false,
        ];
    }
}
