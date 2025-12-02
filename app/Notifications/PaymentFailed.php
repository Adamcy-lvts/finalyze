<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment,
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
        $message = (new MailMessage)
            ->subject('Payment Failed')
            ->level('error')
            ->greeting('Payment Failed')
            ->line("Unfortunately, your payment of {$this->payment->formatted_amount} could not be processed.");

        if ($this->reason) {
            $message->line("**Reason:** {$this->reason}");
        }

        $message->line('**Payment Details:**')
            ->line("Reference: {$this->payment->paystack_reference}")
            ->line("Amount: {$this->payment->formatted_amount}")
            ->action('Try Again', route('pricing'))
            ->line('If you continue to experience issues, please contact our support team.');

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
            'type' => 'payment_failed',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->formatted_amount,
            'reference' => $this->payment->paystack_reference,
            'reason' => $this->reason,
            'message' => "Payment of {$this->payment->formatted_amount} failed.".($this->reason ? " Reason: {$this->reason}" : ''),
        ];
    }
}
