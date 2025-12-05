<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Broadcast event for real-time word balance updates.
 *
 * Uses ShouldBroadcastNow to ensure immediate delivery without queue delay.
 * This ensures users see balance changes immediately in the UI.
 */
class WordBalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var array The balance data to broadcast
     */
    public array $balanceData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public User $user,
        public string $reason = 'update'
    ) {
        $this->balanceData = $user->getWordBalanceData();

        Log::info("[Balance] Event: user={$user->id}, reason={$reason}, balance={$this->balanceData['balance']}");
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->user->id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'balance.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'balance' => $this->balanceData,
            'reason' => $this->reason,
            'timestamp' => now()->toISOString(),
        ];
    }
}
