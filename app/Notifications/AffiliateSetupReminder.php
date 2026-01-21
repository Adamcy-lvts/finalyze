<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AffiliateSetupReminder extends Notification implements ShouldQueue
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
            ->subject('Reminder: Finish Setting Up Your Affiliate Account')
            ->greeting("Hi {$notifiable->name},")
            ->line('You are approved as a Finalyze affiliate, but your payout details are not set up yet.')
            ->line('Please complete your affiliate account setup so you can start earning commissions.')
            ->action('Complete Affiliate Setup', route('affiliate.dashboard'))
            ->line('If you need help, reply to this email and we will assist you.');
    }
}
