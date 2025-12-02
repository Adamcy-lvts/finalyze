<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RefundProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment,
        public int $wordsRefunded,
        public ?string $reason = null
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
        $formattedWords = number_format($this->wordsRefunded);

        $message = (new MailMessage)
            ->subject('Refund Processed')
            ->greeting('Refund Processed')
            ->line("A refund has been processed for your payment of {$this->payment->formatted_amount}.")
            ->line("**{$formattedWords} words** have been deducted from your account.");

        if ($this->reason) {
            $message->line("**Reason:** {$this->reason}");
        }

        $message->line('**Refund Details:**')
            ->line("Reference: {$this->payment->paystack_reference}")
            ->line("Amount: {$this->payment->formatted_amount}")
            ->line("Words Deducted: {$formattedWords}")
            ->line('If you have any questions about this refund, please contact our support team.');

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
            'type' => 'refund_processed',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->formatted_amount,
            'words_refunded' => $this->wordsRefunded,
            'reference' => $this->payment->paystack_reference,
            'reason' => $this->reason,
            'message' => "Refund of {$this->payment->formatted_amount} processed. {$this->wordsRefunded} words deducted.".($this->reason ? " Reason: {$this->reason}" : ''),
        ];
    }
}
