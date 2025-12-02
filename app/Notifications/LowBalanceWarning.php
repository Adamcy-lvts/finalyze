<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceWarning extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $currentBalance,
        public int $thresholdWords = 1000
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $formattedBalance = number_format($this->currentBalance);

        return (new MailMessage)
            ->subject('Low Word Balance Warning')
            ->level('warning')
            ->greeting('Low Word Balance!')
            ->line("Your word balance is running low. You currently have **{$formattedBalance} words** remaining.")
            ->line('To continue generating content without interruption, we recommend purchasing more words.')
            ->action('Purchase Words', route('pricing'))
            ->line('Need help? Contact our support team anytime.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_balance_warning',
            'current_balance' => $this->currentBalance,
            'threshold' => $this->thresholdWords,
            'message' => "Your word balance is low ({$this->currentBalance} words remaining). Consider purchasing more words.",
        ];
    }
}
