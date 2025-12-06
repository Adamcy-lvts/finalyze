<?php

namespace App\Events\Generation;

class LiteratureMiningProgress extends BaseGenerationEvent
{
    public function broadcastAs(): string
    {
        return 'generation.literature_mining';
    }

    public function broadcastWith(): array
    {
        return [
            ...parent::broadcastWith(),
            'event_type' => 'literature_mining',
            'source' => $this->payload['source'] ?? null,
            'papers_found' => $this->payload['papers_found'] ?? 0,
            'total_papers' => $this->payload['total_papers'] ?? 0,
            'sub_stage' => $this->payload['sub_stage'] ?? null,
        ];
    }
}
