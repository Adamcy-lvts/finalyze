<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Registered;

class LogUserRegistered
{
    public function handle(Registered $event): void
    {
        $user = $event->user;
        $email = $user?->email ?? 'unknown';

        ActivityLog::record(
            'user.registered',
            "New signup: {$email}",
            $user,
            $user
        );
    }
}

