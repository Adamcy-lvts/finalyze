<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserSignedUp extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $newUser
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New User Signup')
            ->greeting('New user registered')
            ->line("Name: {$this->newUser->name}")
            ->line("Email: {$this->newUser->email}")
            ->action('View user', route('admin.users.show', $this->newUser))
            ->line('This is an automated admin notification.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_user_signup',
            'user_id' => $this->newUser->id,
            'user_name' => $this->newUser->name,
            'user_email' => $this->newUser->email,
            'message' => "New user signup: {$this->newUser->email}",
        ];
    }
}

