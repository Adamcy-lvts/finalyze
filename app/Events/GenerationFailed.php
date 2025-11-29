<?php

namespace App\Events\Generation;

class GenerationFailed extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.failed';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'failed',
            'error_message' => $this->payload['error_message'] ?? 'An unknown error occurred',
            'failed_stage' => $this->payload['failed_stage'] ?? $this->generation->current_stage,
            'failed_chapter' => $this->payload['failed_chapter'] ?? null,
            'can_resume' => $this->payload['can_resume'] ?? ($this->generation->progress > 0),
            'last_successful_chapter' => $this->payload['last_successful_chapter'] ?? null,
        ];
    }
}
