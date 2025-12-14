<?php

namespace App\Listeners;

use App\Events\AdminNotificationCreated;
use App\Models\AdminNotification;
use App\Models\User;
use App\Notifications\NewUserSignedUp;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;

class NotifyAdminsOfNewUserSignup
{
    public function handle(Registered $event): void
    {
        $newUser = $event->user;

        $admins = User::role(['super_admin', 'admin', 'support'])->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewUserSignedUp($newUser));
        }

        $adminNotification = AdminNotification::create([
            'type' => 'new_user_signup',
            'title' => 'New user signup',
            'message' => "{$newUser->name} ({$newUser->email}) just created an account.",
            'severity' => 'info',
            'data' => [
                'user_id' => $newUser->id,
                'user_name' => $newUser->name,
                'user_email' => $newUser->email,
            ],
        ]);

        broadcast(new AdminNotificationCreated($adminNotification));
    }
}
