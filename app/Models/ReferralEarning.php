<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralEarning extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'payment_id',
        'payment_amount',
        'commission_amount',
        'commission_rate',
        'status',
        'paystack_split_code',
        'paystack_split_response',
    ];

    protected $casts = [
        'payment_amount' => 'integer',
        'commission_amount' => 'integer',
        'commission_rate' => 'decimal:2',
        'paystack_split_response' => 'array',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeForReferrer($query, int $userId)
    {
        return $query->where('referrer_id', $userId);
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get commission amount in Naira
     */
    public function getCommissionInNairaAttribute(): float
    {
        return $this->commission_amount / 100;
    }

    /**
     * Get formatted commission amount
     */
    public function getFormattedCommissionAttribute(): string
    {
        return '₦'.number_format($this->commission_in_naira, 0);
    }

    /**
     * Get payment amount in Naira
     */
    public function getPaymentInNairaAttribute(): float
    {
        return $this->payment_amount / 100;
    }

    /**
     * Get formatted payment amount
     */
    public function getFormattedPaymentAttribute(): string
    {
        return '₦'.number_format($this->payment_in_naira, 0);
    }

    /**
     * Check if earning is paid
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if earning is pending
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Mark earning as paid
     */
    public function markAsPaid(?array $paystackResponse = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paystack_split_response' => $paystackResponse,
        ]);
    }

    /**
     * Mark earning as failed
     */
    public function markAsFailed(?array $paystackResponse = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'paystack_split_response' => $paystackResponse,
        ]);
    }

    /**
     * Mark earning as refunded
     */
    public function markAsRefunded(): void
    {
        $this->update([
            'status' => self::STATUS_REFUNDED,
        ]);
    }
}
