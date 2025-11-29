<?php

namespace App\Events\Generation;

use App\Models\ProjectGeneration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for all generation-related broadcast events.
 *
 * Uses ShouldBroadcastNow to ensure immediate delivery without queue delay.
 * This is critical for real-time progress tracking.
 */
abstract class BaseGenerationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProjectGeneration $generation,
        public array $payload = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     * Uses a private channel to ensure only authorized users can listen.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("project.{$this->generation->project_id}.generation"),
        ];
    }

    /**
     * Get the data to broadcast.
     * Combines standard generation data with event-specific payload.
     */
    public function broadcastWith(): array
    {
        return [
            'generation_id' => $this->generation->id,
            'project_id' => $this->generation->project_id,
            'status' => $this->generation->status,
            'progress' => $this->generation->progress,
            'current_stage' => $this->generation->current_stage,
            'message' => $this->generation->message,
            'timestamp' => now()->toISOString(),
            ...$this->payload,
        ];
    }

    /**
     * The event's broadcast name.
     * Override in child classes for specific event names.
     */
    public function broadcastAs(): string
    {
        return 'generation.update';
    }
}
