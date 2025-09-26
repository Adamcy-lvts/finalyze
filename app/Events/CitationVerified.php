<?php

namespace App\Events;

use App\Models\Citation;
use App\Models\CitationVerification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitationVerified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public CitationVerification $citationVerification,
        public Citation $citation,
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
                'status' => 'verified',
            ],
            'result' => [
                'id' => $this->citation->id,
                'title' => $this->citation->title,
                'authors' => $this->citation->authors,
                'year' => $this->citation->year,
                'journal' => $this->citation->journal,
                'doi' => $this->citation->doi,
                'confidence' => $this->citation->confidence_score,
                'formatted' => $this->citation->getFormattedCitation('apa'),
            ],
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'CitationVerified';
    }
}
