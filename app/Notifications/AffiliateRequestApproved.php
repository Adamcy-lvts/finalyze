<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateRequestApproved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Affiliate Request Was Approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('Great news! Your affiliate request has been approved.')
            ->line('Please set up your affiliate account details to start earning commissions.')
            ->action('Set Up My Affiliate Account', route('affiliate.dashboard'))
            ->line('If you have any questions, reply to this email and we will be happy to help.');
    }
}
