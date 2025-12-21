<?php

namespace App\Events\Defense;

use App\Models\DefenseFeedback;
use App\Models\DefenseSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DefenseSessionEnded implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public DefenseSession $session;

    public DefenseFeedback $feedback;

    public function __construct(DefenseSession $session, DefenseFeedback $feedback)
    {
        $this->session = $session;
        $this->feedback = $feedback;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('defense.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'defense.session.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => $this->session->toArray(),
            'feedback' => $this->feedback->toArray(),
        ];
    }
}
