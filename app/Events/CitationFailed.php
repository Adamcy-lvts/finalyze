<?php

namespace App\Events;

use App\Models\CitationVerification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitationFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public CitationVerification $citationVerification,
        public array $suggestions,
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
                'status' => 'failed',
            ],
            'result' => [
                'suggestions' => $this->suggestions,
                'message' => 'Citation verification failed. Please review and correct.',
            ],
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'CitationFailed';
    }
}
