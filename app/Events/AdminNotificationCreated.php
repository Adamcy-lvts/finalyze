<?php

namespace App\Events;

use App\Models\AdminNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class AdminNotificationCreated implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public function __construct(
        public AdminNotification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.notifications')];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification->toArray(),
        ];
    }
}

