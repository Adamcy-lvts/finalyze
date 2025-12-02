<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class AIProvisioningUpdated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $metrics,
        public array $usageByModel,
        public array $snapshot
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.ai')];
    }

    public function broadcastAs(): string
    {
        return 'ai.provisioning.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'metrics' => $this->metrics,
            'usageByModel' => $this->usageByModel,
            'snapshot' => $this->snapshot,
        ];
    }
}
