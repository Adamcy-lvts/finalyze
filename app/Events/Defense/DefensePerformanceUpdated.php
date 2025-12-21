<?php

namespace App\Events\Defense;

use App\Models\DefenseSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DefensePerformanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public DefenseSession $session;

    public array $metrics;

    public function __construct(DefenseSession $session, array $metrics)
    {
        $this->session = $session;
        $this->metrics = $metrics;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('defense.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'defense.performance.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'performance_metrics' => $this->metrics,
        ];
    }
}
