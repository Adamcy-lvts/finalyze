<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowCreditAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $status,
        public array $metrics
    ) {}

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
        $subject = $this->status === 'critical'
            ? 'CRITICAL: OpenAI Credit Balance Low'
            : 'Warning: OpenAI Credit Balance Running Low';

        $availableUsd = $this->metrics['snapshot']?->available_usd ?? $this->metrics['snapshot']['available_usd'] ?? 0;
        $liabilityTokens = $this->metrics['liability_tokens'] ?? 0;
        $runwayDays = $this->metrics['runway_days'] ?? 'N/A';

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($this->status === 'critical' ? 'Urgent Action Required!' : 'Attention Required')
            ->line('Your OpenAI credit balance requires attention.')
            ->line('')
            ->line('**Current Status:**')
            ->line('- Available Credit: $'.number_format($availableUsd, 2))
            ->line('- Liability: '.number_format($liabilityTokens).' tokens')
            ->line('- Estimated Runway: '.$runwayDays.' days')
            ->line('')
            ->action('View AI Dashboard', url('/admin/ai'))
            ->line('Please top up your OpenAI credit to avoid service interruption.');

        if ($this->status === 'critical') {
            $message->salutation('This is a critical alert. Please take immediate action.');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'status' => $this->status,
            'available_usd' => $this->metrics['snapshot']?->available_usd ?? $this->metrics['snapshot']['available_usd'] ?? 0,
            'liability_tokens' => $this->metrics['liability_tokens'] ?? 0,
            'runway_days' => $this->metrics['runway_days'] ?? null,
        ];
    }
}
