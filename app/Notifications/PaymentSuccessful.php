<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessful extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Payment $payment
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
        return (new MailMessage)
            ->subject('Payment Successful - Words Credited')
            ->greeting('Payment Successful!')
            ->line("Your payment of {$this->payment->formatted_amount} has been successfully processed.")
            ->line("**{$this->payment->words_purchased} words** have been credited to your account.")
            ->line('**Payment Details:**')
            ->line("Reference: {$this->payment->paystack_reference}")
            ->line("Payment Method: {$this->payment->payment_method}")
            ->line("Date: {$this->payment->paid_at->format('F j, Y g:i A')}")
            ->action('View My Projects', route('projects.index'))
            ->line('Thank you for using Finalyze!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_successful',
            'payment_id' => $this->payment->id,
            'amount' => $this->payment->formatted_amount,
            'words_credited' => $this->payment->words_purchased,
            'reference' => $this->payment->paystack_reference,
            'payment_method' => $this->payment->payment_method,
            'message' => "Payment of {$this->payment->formatted_amount} successful. {$this->payment->words_purchased} words credited.",
        ];
    }
}
