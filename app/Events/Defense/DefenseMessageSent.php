<?php

namespace App\Events\Defense;

use App\Models\DefenseMessage;
use App\Models\DefenseSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DefenseMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public DefenseSession $session;

    public DefenseMessage $message;

    public function __construct(DefenseSession $session, DefenseMessage $message)
    {
        $this->session = $session;
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('defense.'.$this->session->id)];
    }

    public function broadcastAs(): string
    {
        return 'defense.message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'message' => $this->message->toArray(),
        ];
    }
}
