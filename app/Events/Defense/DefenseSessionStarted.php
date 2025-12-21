<?php

namespace App\Events\Defense;

use App\Models\DefenseSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DefenseSessionStarted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public DefenseSession $session;

    public function __construct(DefenseSession $session)
    {
        $this->session = $session;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('defense.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'defense.session.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => $this->session->toArray(),
        ];
    }
}
