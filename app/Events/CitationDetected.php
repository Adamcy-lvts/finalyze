<?php

namespace App\Events;

use App\Models\CitationVerification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public CitationVerification $citationVerification,
        public string $sessionId
    ) {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("citations.{$this->sessionId}"),
        ];
    }

    /**
     * Get the data that should be broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'citation' => [
                'id' => $this->citationVerification->id,
                'raw' => $this->citationVerification->raw_citation,
                'status' => $this->citationVerification->status,
                'detected_format' => $this->citationVerification->detected_format,
            ],
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'CitationDetected';
    }
}
