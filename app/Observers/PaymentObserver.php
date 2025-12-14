<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Payment;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_SUCCESS) {
            return;
        }

        $userEmail = $payment->user?->email ?? "user#{$payment->user_id}";

        ActivityLog::record(
            'payment.success',
            "Payment of ₦".number_format($payment->amount / 100, 0)." by {$userEmail}",
            $payment,
            $payment->user
        );
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        if ($payment->status !== Payment::STATUS_SUCCESS) {
            return;
        }

        if ($payment->getOriginal('status') === Payment::STATUS_SUCCESS) {
            return;
        }

        $userEmail = $payment->user?->email ?? "user#{$payment->user_id}";

        ActivityLog::record(
            'payment.success',
            "Payment of ₦".number_format($payment->amount / 100, 0)." by {$userEmail}",
            $payment,
            $payment->user
        );
    }
}

